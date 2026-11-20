<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveAdminPortfolio;
use App\Models\Media;
use App\Models\Portfolio;
use App\Models\PortfolioType;
use App\Support\CurrentPortfolio;
use App\Models\User;
use App\Support\Templates;
use App\Support\Username;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Manage portfolios: identity, owner, status, SEO, template (draft → preview → publish) and template settings. */
class PortfolioController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Portfolio::class);
        $query = Portfolio::query()->with('user');

        if ($q = trim((string) $request->query('q'))) {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('username', 'like', $like)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)));
        }
        if (Templates::exists((string) $request->query('template'))) {
            $query->where('template', $request->query('template'));
        }
        if (array_key_exists((string) $request->query('status'), Portfolio::STATUSES)) {
            $query->where('status', $request->query('status'));
        }
        $types = PortfolioType::ordered()->get()->keyBy('slug');
        if ($types->has((string) $request->query('type'))) {
            // A Hybrid portfolio also shows under each discipline it combines.
            $query->where(fn ($w) => $w->where('type', $request->query('type'))->orWhereJsonContains('disciplines', $request->query('type')));
        }

        return view('admin.portfolios.index', [
            'types' => $types,
            'portfolios' => $query->latest('updated_at')->paginate(25)->withQueryString(),
            'templates' => Templates::available(),
            'currentId' => session(ResolveAdminPortfolio::SESSION_KEY),
        ]);
    }

    public function create()
    {
        Gate::authorize('create', Portfolio::class);

        return view('admin.portfolios.create', ['portfolio' => new Portfolio(['status' => 'draft', 'template' => Templates::DEFAULT, 'type' => PortfolioType::DEFAULT]),
            'users' => $this->users(), 'templates' => Templates::available(), 'types' => $this->types()]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Portfolio::class);
        $type = $this->validatedType($request);
        $data = $this->validated($request, null, ['template' => ['required', Rule::in(array_keys(Templates::forType($type['type'])))]],
            messages: ['template.in' => 'Pick a template recommended for this portfolio type (Admin → Portfolio types sets which ones).']);

        $portfolio = Portfolio::provision($this->withPublishedAt($data) + $type, ['full_name' => $data['name']]);
        $request->session()->put(ResolveAdminPortfolio::SESSION_KEY, $portfolio->id);

        return redirect()->route('admin.portfolios.edit', $portfolio)
            ->with('success', "Portfolio “{$portfolio->name}” created and selected. Fill in its content from Personal info & CV, Projects and the other content screens.");
    }

    public function edit(Portfolio $portfolio)
    {
        Gate::authorize('update', $portfolio);
        // Settings form follows the draft template if there is one, else the live one.
        $settingsKey = $portfolio->draft_template && Templates::exists($portfolio->draft_template) ? $portfolio->draft_template
            : (Templates::exists((string) $portfolio->template) ? $portfolio->template : Templates::DEFAULT);

        return view('admin.portfolios.edit', [
            'portfolio' => $portfolio,
            'users' => $this->users(),
            'templates' => Templates::forType($portfolio->type, array_filter([$portfolio->template, $portfolio->draft_template])),
            'types' => $this->types($portfolio),
            'settingsKey' => $settingsKey,
            'settings' => $portfolio->settingsFor($settingsKey),
            'isCurrent' => (int) session(ResolveAdminPortfolio::SESSION_KEY) === $portfolio->id,
            'owner' => $portfolio->user,
        ]);
    }

    public function update(Request $request, Portfolio $portfolio)
    {
        Gate::authorize('update', $portfolio);
        // Owners edit the name, SEO and template settings; status, username and owner are admin decisions.
        $data = $this->validated($request, $portfolio, manage: Gate::allows('manage', $portfolio));

        $key = $request->validate(['settings_template' => ['nullable', Rule::in(array_keys(Templates::available()))]])['settings_template'] ?? null;
        if ($key) {
            $settings = $request->validate(Templates::rules($key))['settings'] ?? [];
            $all = (array) $portfolio->template_settings;
            $clean = Templates::clean($key, $settings);
            $all['_shared'] = Arr::only($clean, Templates::sharedKeys()); // same for every template
            $all[$key] = Arr::except($clean, Templates::sharedKeys()); // other templates' saved settings stay as they are
            $data['template_settings'] = $all;
        }

        $renamed = isset($data['username']) && $data['username'] !== $portfolio->username;
        $portfolio->update(isset($data['status']) ? $this->withPublishedAt($data, $portfolio) : $data);

        return redirect()->route('admin.portfolios.edit', $portfolio)
            ->with('success', 'Portfolio saved.'.($renamed ? " Its public address is now {$portfolio->url()} — the old URL no longer works." : ''));
    }

    public function template(Request $request, Portfolio $portfolio)
    {
        Gate::authorize('update', $portfolio);
        $key = $request->validate(['template' => ['required', 'string', Rule::in(array_keys(Templates::forType($portfolio->type, [$portfolio->template])))]],
            ['template.in' => 'That template is not recommended for this portfolio type.'])['template'];

        if ($key === $portfolio->template) {
            $portfolio->update(['draft_template' => null]);
            $request->session()->forget(Templates::previewKey($portfolio));

            return back()->with('success', 'That is already the live template — draft cleared.');
        }
        $portfolio->update(['draft_template' => $key]);

        return back()->with('success', 'Saved as draft. Preview it, then publish.');
    }

    public function publishTemplate(Request $request, Portfolio $portfolio)
    {
        Gate::authorize('update', $portfolio);
        if (! $portfolio->draft_template || ! Templates::exists($portfolio->draft_template)) {
            return back()->withErrors(['template' => 'There is no draft template to publish.']);
        }

        // Only the template pointer changes; content tables are never touched.
        $portfolio->update(['template' => $portfolio->draft_template, 'draft_template' => null]);
        $request->session()->forget(Templates::previewKey($portfolio));

        return back()->with('success', 'Template published: '.Templates::meta($portfolio->template)['name'].' is now live.');
    }

    /**
     * Change the portfolio type. Nothing is removed or hidden: content of the old type stays (and comes back if the type
     * is switched back); the new type's sections are added, and optionally the Projects/Skills tabs take its words.
     */
    public function type(Request $request, Portfolio $portfolio)
    {
        Gate::authorize('update', $portfolio);
        $data = $this->validatedType($request, $portfolio);
        $portfolio->update($data);
        $name = $portfolio->portfolioType?->name ?? $data['type'];

        $added = CurrentPortfolio::using($portfolio, function () use ($portfolio, $request) {
            if ($request->boolean('relabel')) {
                foreach (Arr::only(Portfolio::sectionCatalog($portfolio->moduleKeys()), ['projects', 'skills']) as $key => [$label, $eyebrow, $title]) {
                    \App\Models\Section::query()->where('key', $key)->first()?->update(['nav_label' => $label, 'eyebrow' => $eyebrow, 'title' => $title]);
                }
            }

            return $portfolio->addTypeSections();
        });

        $message = "Portfolio type is now {$name}. Nothing was removed or hidden.";
        if ($added) {
            $message .= ' New sections to fill in: '.implode(', ', $added).' — see Sections & order (they stay off the public site until they have content).';
        }
        if (! isset(Templates::forType($portfolio->type)[$portfolio->template])) {
            $message .= ' The current template is not one recommended for this type; it keeps working, and the recommended ones are listed under Template.';
        }

        return back()->with('success', $message);
    }

    public function status(Request $request, Portfolio $portfolio)
    {
        Gate::authorize('update', $portfolio);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Portfolio::STATUSES))]]);
        $portfolio->update($this->withPublishedAt($data, $portfolio));

        return back()->with('success', "“{$portfolio->name}” is now ".strtolower(Portfolio::STATUSES[$portfolio->status]).'.');
    }

    public function destroy(Request $request, Portfolio $portfolio)
    {
        Gate::authorize('delete', $portfolio);
        $request->validate(['confirm_username' => ['required', 'string', Rule::in([$portfolio->username])]],
            ['confirm_username.in' => 'Type the username exactly to confirm deletion.']);

        // Content rows go with the portfolio via ON DELETE CASCADE; uploaded files are removed here.
        $files = Media::withoutGlobalScopes()->where('portfolio_id', $portfolio->id)->get(['disk', 'path']);
        DB::transaction(fn () => $portfolio->delete());
        $files->each(fn ($m) => Storage::disk($m->disk)->delete($m->path));

        if ((int) $request->session()->get(ResolveAdminPortfolio::SESSION_KEY) === $portfolio->id) {
            $request->session()->forget(ResolveAdminPortfolio::SESSION_KEY);
        }
        $request->session()->forget(Templates::previewKey($portfolio));

        return redirect()->route('admin.portfolios.index')->with('success', "Portfolio “{$portfolio->name}” and all its content were deleted.");
    }

    public function switch(Request $request, Portfolio $portfolio)
    {
        Gate::authorize('update', $portfolio);
        $request->session()->put(ResolveAdminPortfolio::SESSION_KEY, $portfolio->id);

        return redirect($this->afterSwitch($portfolio))->with('success', "Now editing “{$portfolio->name}”.");
    }

    /**
     * Where to land after switching: the same screen when it edits "the current portfolio" (profile, a content list…),
     * but a portfolio's own settings page becomes the new one's, and a single record (a project, a message…) of the old
     * portfolio goes back to its list — it would 404 now.
     */
    private function afterSwitch(Portfolio $portfolio): string
    {
        $previous = url()->previous();
        try {
            $route = app('router')->getRoutes()->match(Request::create($previous));
        } catch (\Throwable) {
            return route('admin.dashboard');
        }
        $name = (string) $route->getName();
        $params = array_diff($route->parameterNames(), ['slug']);

        return match (true) {
            ! str_starts_with($name, 'admin.') => route('admin.dashboard'),
            $name === 'admin.portfolios.edit' => route('admin.portfolios.edit', $portfolio),
            str_starts_with($name, 'admin.resources.') => route('admin.resources.index', $route->parameter('slug')),
            $params !== [] => Route::has($index = Str::beforeLast($name, '.').'.index') ? route($index) : route('admin.dashboard'),
            default => $previous,
        };
    }

    /** Create a sign-in for this portfolio's owner, or set a new password for the existing one. */
    public function owner(Request $request, Portfolio $portfolio)
    {
        Gate::authorize('manage', $portfolio);
        $owner = $portfolio->user;
        $request->merge(['owner_phone' => User::normalizePhone($request->input('owner_phone'))]);
        $data = $request->validate([
            'owner_name' => [$owner ? 'exclude' : 'required', 'string', 'max:120'],
            'owner_email' => [$owner ? 'exclude' : 'required', 'email', 'max:150', Rule::unique('users', 'email')],
            'owner_phone' => ['nullable', 'string', 'max:40', Rule::unique('users', 'phone')->ignore($owner?->id)],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], ['owner_name' => 'name', 'owner_email' => 'email', 'owner_phone' => 'phone']);

        if ($owner) {
            $owner->update(['password' => $data['password']] + array_filter(['phone' => $data['owner_phone']]));

            return back()->with('success', "New password set for {$owner->email}.");
        }
        $user = User::create(['name' => $data['owner_name'], 'email' => $data['owner_email'], 'phone' => $data['owner_phone'],
            'password' => $data['password'], 'is_admin' => false]);
        $portfolio->update(['user_id' => $user->id]);

        return back()->with('success', "Login created: {$user->email} can now sign in at ".route('login').' and edit this portfolio.');
    }

    /** type + disciplines (Hybrid: two or more other types, stored in the platform's order; otherwise null). */
    private function validatedType(Request $request, ?Portfolio $portfolio = null): array
    {
        $options = array_keys(PortfolioType::disciplineOptions());
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys($this->types($portfolio)))],
            'disciplines' => ['exclude_unless:type,'.PortfolioType::HYBRID, 'required', 'array', 'min:2'],
            'disciplines.*' => ['string', Rule::in($options)],
        ], ['disciplines.required' => 'A hybrid portfolio combines at least two disciplines.', 'disciplines.min' => 'A hybrid portfolio combines at least two disciplines.']);

        return ['type' => $data['type'], 'disciplines' => $data['type'] === PortfolioType::HYBRID ? array_values(array_intersect($options, $data['disciplines'])) : null];
    }

    /** Types to choose from: the active ones, plus the portfolio's current type even if it was switched off. */
    private function types(?Portfolio $portfolio = null): array
    {
        return PortfolioType::query()->ordered()->where(fn ($q) => $q->where('is_active', true)->orWhere('slug', $portfolio?->type))->get()->keyBy('slug')->all();
    }

    private function validated(Request $request, ?Portfolio $portfolio, array $extra = [], bool $manage = true, array $messages = []): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:300'],
            'og_image_url' => ['nullable', 'url:http,https', 'max:255'],
        ];
        if ($manage) {
            $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);
            $rules += [
                'username' => Username::rules($portfolio),
                'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
                'status' => ['required', Rule::in(array_keys(Portfolio::STATUSES))],
            ];
        }

        return $request->validate($rules + $extra, $messages);
    }

    private function withPublishedAt(array $data, ?Portfolio $portfolio = null): array
    {
        if ($data['status'] === 'published' && ! $portfolio?->published_at) {
            $data['published_at'] = now();
        }

        return $data;
    }

    private function users()
    {
        return User::query()->orderBy('name')->get(['id', 'name', 'email']);
    }
}
