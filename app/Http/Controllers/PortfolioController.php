<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\ContactMessage;
use App\Models\PageView;
use App\Models\Portfolio;
use App\Models\Profile;
use App\Models\Project;
use App\Services\Portfolio\Assistant;
use App\Services\Portfolio\PortfolioData;
use App\Support\CurrentPortfolio;
use App\Support\Templates;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PortfolioController extends Controller
{
    /**
     * Render a page of the current portfolio with its active template (allow-listed in Templates).
     * Every template view gets: portfolio, template, settings (validated template settings) and
     * previewing (true when the page is not what the public sees — shows a banner, noindex).
     */
    public static function render(string $page, array $data): Response
    {
        $portfolio = CurrentPortfolio::get() ?? abort(404);
        $template = Templates::active($portfolio);
        $previewing = ! $portfolio->isPublic() || $template !== $portfolio->template || ($data['preview'] ?? false);

        $response = response()->view(Templates::view($template, $page), $data + [
            'portfolio' => $portfolio,
            'template' => $template,
            'settings' => $portfolio->settingsFor($template),
            'previewing' => $previewing,
        ]);
        if ($previewing) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }

    /** The portfolio legacy single-site URLs and the bare /api/portfolio redirect to. */
    public static function defaultPortfolio(): ?Portfolio
    {
        $username = config('portfolio.default_username');

        return Portfolio::query()->where('status', 'published')
            ->when($username, fn ($q) => $q->where('username', mb_strtolower($username)))
            ->orderBy('id')->first();
    }

    /** /projects/x, /blog, /resume … from the single-portfolio days → /{default}/… */
    public function legacy(string $legacy)
    {
        $portfolio = self::defaultPortfolio() ?? abort(404);

        return redirect($portfolio->url($legacy), 301);
    }

    public function index(Request $request, PortfolioData $data, Assistant $assistant)
    {
        PageView::record($request, 'page', '/');

        return self::render('home', $data->get() + ['assistant' => $assistant->enabled()]);
    }

    public function api(PortfolioData $data, ?Portfolio $portfolio = null)
    {
        if (! $portfolio) {
            CurrentPortfolio::set(self::defaultPortfolio() ?? abort(404));
        }

        return response()->json($data->toApi());
    }

    public function playground(Request $request)
    {
        PageView::record($request, 'page', '/playground');

        return view('playground', ['profile' => Profile::query()->with('avatar')->firstOrFail()]);
    }

    public function project(Request $request, Portfolio $portfolio, Project $project)
    {
        abort_unless($project->isPublished(), 404);
        PageView::record($request, 'project', $project->slug);

        return $this->renderProject($project, false);
    }

    public function renderProject(Project $project, bool $preview)
    {
        $project->load(['technologies', 'thumbnail', 'screenshots', 'experience']);
        $highlights = Achievement::published()->ordered()->where('project_id', $project->id)->where('kind', 'highlight')->get();

        // Related: other published projects ranked by how many technologies they share.
        $techIds = $project->technologies->pluck('id');
        $related = Project::published()->whereKeyNot($project->id)->with(['technologies', 'thumbnail', 'experience'])->ordered()->get()
            ->map(fn ($p) => [$p, $p->technologies->pluck('id')->intersect($techIds)->count()])
            ->filter(fn ($pair) => $pair[1] > 0)->sortByDesc(fn ($pair) => $pair[1])->take(3)->pluck(0);

        return self::render('project', [
            'project' => $project,
            'profile' => Profile::query()->with('avatar')->firstOrFail(),
            'highlights' => $highlights,
            'related' => $related,
            'preview' => $preview,
        ]);
    }

    public function resume(Request $request)
    {
        $resume = Profile::query()->firstOrFail()->resume;
        abort_unless($resume && Storage::disk($resume->disk)->exists($resume->path), 404);
        PageView::record($request, 'resume', (string) $resume->id);
        $name = Str::slug(Profile::query()->value('full_name')).'-cv.pdf';

        return Storage::disk($resume->disk)->download($resume->path, $name, ['Content-Type' => 'application/pdf']);
    }

    public function contact(Request $request)
    {
        abort_unless(Profile::query()->value('contact_form_enabled'), 404);

        // Bots fill the hidden field; pretend success so they don't retry.
        if (filled($request->input('website'))) {
            return redirect()->to(route('home').'#contact')->with('contact_sent', true);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);
        // The form lives on the Contact tab, so send errors back there rather than to the top of the page.
        if ($validator->fails()) {
            return redirect()->to(route('home').'#contact')->withErrors($validator)->withInput();
        }

        $message = ContactMessage::create($validator->validated() + ['ip_address' => $request->ip()]);
        $this->notify($message);

        return redirect()->to(route('home').'#contact')->with('contact_sent', true);
    }

    /** Email the owner after the response is sent; a mail failure never loses the stored message. */
    private function notify(ContactMessage $message): void
    {
        $to = config('portfolio.contact.notify_email') ?: Profile::query()->value('email');
        if (! $to || ! config('portfolio.contact.notify')) {
            return;
        }
        dispatch(function () use ($message, $to) {
            try {
                Mail::raw(
                    "New message from your portfolio\n\nFrom: {$message->name} <{$message->email}>\nSubject: ".($message->subject ?: '—')."\n\n{$message->message}\n\n".route('admin.messages.show', $message),
                    fn ($mail) => $mail->to($to)->replyTo($message->email, $message->name)->subject('Portfolio: '.($message->subject ?: "message from {$message->name}"))
                );
            } catch (\Throwable $e) {
                report($e);
            }
        })->afterResponse();
    }
}
