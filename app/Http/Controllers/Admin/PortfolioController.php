<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveAdminPortfolio;
use App\Models\Media;
use App\Models\Portfolio;
use App\Models\User;
use App\Support\Templates;
use App\Support\Username;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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

        return view('admin.portfolios.index', [
            'portfolios' => $query->latest('updated_at')->paginate(25)->withQueryString(),
            'templates' => Templates::available(),
            'currentId' => session(ResolveAdminPortfolio::SESSION_KEY),
        ]);
    }

    public function create()
    {
        Gate::authorize('create', Portfolio::class);

        return view('admin.portfolios.create', ['portfolio' => new Portfolio(['status' => 'draft', 'template' => Templates::DEFAULT]),
            'users' => $this->users(), 'templates' => Templates::available()]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Portfolio::class);
        $data = $this->validated($request, null, ['template' => ['required', Rule::in(array_keys(Templates::available()))]]);

        $portfolio = Portfolio::provision($this->withPublishedAt($data), ['full_name' => $data['name']]);
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
            'templates' => Templates::available(),
            'settingsKey' => $settingsKey,
            'settings' => $portfolio->settingsFor($settingsKey),
            'isCurrent' => (int) session(ResolveAdminPortfolio::SESSION_KEY) === $portfolio->id,
        ]);
    }

    public function update(Request $request, Portfolio $portfolio)
    {
        Gate::authorize('update', $portfolio);
        $data = $this->validated($request, $portfolio);

        $key = $request->validate(['settings_template' => ['nullable', Rule::in(array_keys(Templates::available()))]])['settings_template'] ?? null;
        if ($key) {
            $settings = $request->validate(Templates::rules($key))['settings'] ?? [];
            $all = (array) $portfolio->template_settings;
            $clean = Templates::clean($key, $settings);
            $all['_shared'] = Arr::only($clean, Templates::sharedKeys()); // same for every template
            $all[$key] = Arr::except($clean, Templates::sharedKeys()); // other templates' saved settings stay as they are
            $data['template_settings'] = $all;
        }

        $renamed = $data['username'] !== $portfolio->username;
        $portfolio->update($this->withPublishedAt($data, $portfolio));

        return redirect()->route('admin.portfolios.edit', $portfolio)
            ->with('success', 'Portfolio saved.'.($renamed ? " Its public address is now {$portfolio->url()} — the old URL no longer works." : ''));
    }

    public function template(Request $request, Portfolio $portfolio)
    {
        Gate::authorize('update', $portfolio);
        $key = $request->validate(['template' => ['required', 'string', Rule::in(array_keys(Templates::available()))]])['template'];

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

        return back(fallback: route('admin.dashboard'))->with('success', "Now editing “{$portfolio->name}”.");
    }

    private function validated(Request $request, ?Portfolio $portfolio, array $extra = []): array
    {
        $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => Username::rules($portfolio),
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => ['required', Rule::in(array_keys(Portfolio::STATUSES))],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:300'],
            'og_image_url' => ['nullable', 'url:http,https', 'max:255'],
        ] + $extra);
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
