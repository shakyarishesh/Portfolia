<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\SeoController;
use App\Http\Middleware\ResolveAdminPortfolio;
use App\Support\Username;
use Illuminate\Support\Facades\Route;

// System and legacy single-site URLs. Everything here is registered before /{username} so it can never be shadowed.
Route::get('/', LandingController::class)->name('root'); // product landing page with the demo form

// Try-it demo: data lives only in the visitor's session, never in the database.
Route::post('/demo', [DemoController::class, 'store'])->middleware('throttle:30,1')->name('demo.store');
Route::get('/demo', [DemoController::class, 'show'])->name('demo.show');
Route::get('/demo/projects/{slug}', [DemoController::class, 'project'])->name('demo.project');
Route::get('/demo/sample/{type}', [DemoController::class, 'sample'])->middleware('throttle:30,1')->name('demo.sample');
Route::delete('/demo', [DemoController::class, 'destroy'])->name('demo.destroy');

// Public "create your portfolio" application → pending portfolio an admin approves. Step 1 is the portfolio type.
Route::get('/create', [ApplicationController::class, 'create'])->name('apply.create');
Route::post('/create/preview', [ApplicationController::class, 'preview'])->middleware('throttle:30,1')->name('apply.preview');
Route::post('/create', [ApplicationController::class, 'store'])->middleware('throttle:applications')->name('apply.store');
Route::get('/create/submitted', [ApplicationController::class, 'submitted'])->name('apply.submitted');
// Offline route: download a plain-text template, fill it in, upload it to pre-fill the form.
Route::get('/create/template', [ApplicationController::class, 'template'])->name('apply.template');
Route::post('/create/import', [ApplicationController::class, 'import'])->middleware('throttle:20,1')->name('apply.import');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/api/portfolio/{username?}', [PortfolioController::class, 'api'])->middleware('throttle:60,1')->name('api.portfolio');
// Old URLs from before multi-portfolio support → the default portfolio's equivalent.
Route::get('/{legacy}', [PortfolioController::class, 'legacy'])
    ->where('legacy', '(projects|blog|notes)(/[A-Za-z0-9_-]+)?|resume|playground|contact')->name('legacy');

// Admin authentication (no public registration; create admins with `php artisan portfolio:admin`)
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [LoginController::class, 'show'])->name('login');
    Route::post('/admin/login', [LoginController::class, 'login'])->middleware('throttle:login');
});
Route::post('/admin/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// The portal: admins manage every portfolio; a portfolio owner signs in to the same screens for their own portfolio only
// (content is scoped by ResolveAdminPortfolio + PortfolioPolicy). Routes marked `can:admin` are admin-only.
Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:portal', ResolveAdminPortfolio::class])->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    // Portfolio management (multi-portfolio). Content screens below edit the portfolio selected here.
    Route::get('/portfolios', [Admin\PortfolioController::class, 'index'])->name('portfolios.index')->middleware('can:admin');
    Route::get('/portfolios/create', [Admin\PortfolioController::class, 'create'])->name('portfolios.create')->middleware('can:admin');
    Route::post('/portfolios', [Admin\PortfolioController::class, 'store'])->name('portfolios.store')->middleware('can:admin');
    Route::get('/portfolios/{portfolio}/edit', [Admin\PortfolioController::class, 'edit'])->name('portfolios.edit');
    Route::put('/portfolios/{portfolio}', [Admin\PortfolioController::class, 'update'])->name('portfolios.update');
    Route::post('/portfolios/{portfolio}/status', [Admin\PortfolioController::class, 'status'])->name('portfolios.status')->middleware('can:admin');
    Route::post('/portfolios/{portfolio}/type', [Admin\PortfolioController::class, 'type'])->name('portfolios.type');
    Route::post('/portfolios/{portfolio}/template', [Admin\PortfolioController::class, 'template'])->name('portfolios.template');
    Route::post('/portfolios/{portfolio}/template/publish', [Admin\PortfolioController::class, 'publishTemplate'])->name('portfolios.template.publish');
    Route::post('/portfolios/{portfolio}/owner', [Admin\PortfolioController::class, 'owner'])->name('portfolios.owner')->middleware('can:admin');
    Route::delete('/portfolios/{portfolio}', [Admin\PortfolioController::class, 'destroy'])->name('portfolios.destroy')->middleware('can:admin');
    Route::post('/portfolios/{portfolio}/switch', [Admin\PortfolioController::class, 'switch'])->name('portfolios.switch');
    Route::get('/portfolios/{portfolio}/preview', [PreviewController::class, 'start'])->name('portfolios.preview');
    Route::post('/portfolios/{portfolio}/preview/exit', [PreviewController::class, 'exit'])->name('portfolios.preview.exit');

    Route::get('/applications', [Admin\ApplicationController::class, 'index'])->name('applications.index')->middleware('can:admin');
    Route::get('/applications/{portfolio}', [Admin\ApplicationController::class, 'show'])->name('applications.show')->middleware('can:admin');
    Route::post('/applications/{portfolio}/approve', [Admin\ApplicationController::class, 'approve'])->name('applications.approve')->middleware('can:admin');
    Route::post('/applications/{portfolio}/reject', [Admin\ApplicationController::class, 'reject'])->name('applications.reject')->middleware('can:admin');

    Route::get('/profile', [Admin\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [Admin\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/upload', [Admin\ProfileController::class, 'upload'])->name('profile.upload');
    Route::delete('/profile/upload', [Admin\ProfileController::class, 'removeUpload'])->name('profile.upload.destroy');
    Route::post('/profile/resumes/{media}/activate', [Admin\ProfileController::class, 'activateResume'])->name('profile.resumes.activate');
    Route::delete('/profile/resumes/{media}', [Admin\ProfileController::class, 'destroyResume'])->name('profile.resumes.destroy');

    Route::get('/analytics', Admin\AnalyticsController::class)->name('analytics');

    Route::get('/sections', [Admin\SectionController::class, 'index'])->name('sections.index');
    Route::post('/sections', [Admin\SectionController::class, 'store'])->name('sections.store');
    Route::delete('/sections/{section}', [Admin\SectionController::class, 'destroy'])->name('sections.destroy');
    Route::put('/sections/{section}', [Admin\SectionController::class, 'update'])->name('sections.update');
    Route::post('/sections/{section}/move', [Admin\SectionController::class, 'move'])->name('sections.move');

    Route::post('/projects/{project:id}/media', [Admin\ProjectMediaController::class, 'store'])->name('projects.media.store');
    Route::get('/projects/{project:id}/preview', [Admin\ProjectMediaController::class, 'preview'])->name('projects.preview');
    Route::patch('/media/{media}', [Admin\ProjectMediaController::class, 'update'])->name('media.update');
    Route::delete('/media/{media}', [Admin\ProjectMediaController::class, 'destroy'])->name('media.destroy');
    Route::post('/posts/{post:id}/media', [Admin\ProjectMediaController::class, 'storeForPost'])->name('posts.media.store');
    Route::get('/posts/{post:id}/preview', fn (\App\Models\Post $post, BlogController $blog) => $blog->render($post, true))->name('posts.preview');

    Route::get('/messages', [Admin\MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{message}', [Admin\MessageController::class, 'show'])->name('messages.show');
    Route::delete('/messages/{message}', [Admin\MessageController::class, 'destroy'])->name('messages.destroy');
    Route::post('/messages/{message}/state', [Admin\MessageController::class, 'state'])->name('messages.state');

    Route::get('/imports', [Admin\ImportController::class, 'index'])->name('imports.index')->middleware('can:admin');
    Route::post('/imports', [Admin\ImportController::class, 'store'])->name('imports.store')->middleware('can:admin');
    Route::get('/imports/{import}', [Admin\ImportController::class, 'show'])->name('imports.show')->middleware('can:admin');
    Route::delete('/imports/{import}', [Admin\ImportController::class, 'destroy'])->name('imports.destroy')->middleware('can:admin');
    Route::post('/imports/{import}/ai', [Admin\ImportController::class, 'runAi'])->name('imports.ai')->middleware('can:admin');
    Route::get('/imports/{import}/conversations/{conversation}', [Admin\ImportController::class, 'conversation'])->name('imports.conversation')->middleware('can:admin');

    Route::get('/proposals', [Admin\ProposalController::class, 'index'])->name('proposals.index')->middleware('can:admin');
    Route::get('/proposals/new', [Admin\ProposalController::class, 'create'])->name('proposals.create')->middleware('can:admin');
    Route::post('/proposals', [Admin\ProposalController::class, 'store'])->name('proposals.store')->middleware('can:admin');
    Route::post('/proposals/dismiss-duplicates', [Admin\ProposalController::class, 'dismissDuplicates'])->name('proposals.dismiss-duplicates')->middleware('can:admin');
    Route::get('/proposals/{proposal}/edit', [Admin\ProposalController::class, 'edit'])->name('proposals.edit')->middleware('can:admin');
    Route::put('/proposals/{proposal}', [Admin\ProposalController::class, 'update'])->name('proposals.update')->middleware('can:admin');
    Route::post('/proposals/{proposal}/approve', [Admin\ProposalController::class, 'approve'])->name('proposals.approve')->middleware('can:admin');
    Route::post('/proposals/{proposal}/reject', [Admin\ProposalController::class, 'reject'])->name('proposals.reject')->middleware('can:admin');

    Route::get('/audit', [Admin\AuditController::class, 'index'])->name('audit.index');
    Route::post('/audit/{log}/restore', [Admin\AuditController::class, 'restore'])->name('audit.restore');

    // Generic content types — keep last so the fixed paths above win.
    Route::get('/{slug}', [Admin\ResourceController::class, 'index'])->name('resources.index');
    Route::get('/{slug}/create', [Admin\ResourceController::class, 'create'])->name('resources.create');
    Route::post('/{slug}', [Admin\ResourceController::class, 'store'])->name('resources.store');
    Route::get('/{slug}/{id}/edit', [Admin\ResourceController::class, 'edit'])->whereNumber('id')->name('resources.edit');
    Route::put('/{slug}/{id}', [Admin\ResourceController::class, 'update'])->whereNumber('id')->name('resources.update');
    Route::delete('/{slug}/{id}', [Admin\ResourceController::class, 'destroy'])->whereNumber('id')->name('resources.destroy');
    Route::post('/{slug}/{id}/status', [Admin\ResourceController::class, 'status'])->whereNumber('id')->name('resources.status');
    Route::post('/{slug}/{id}/move', [Admin\ResourceController::class, 'move'])->whereNumber('id')->name('resources.move');
});

// Public portfolios: /{username}/… — keep this group LAST so explicit routes above always win.
Route::prefix('{username}')->where(['username' => Username::ROUTE_PATTERN])->group(function () {
    Route::get('/', [PortfolioController::class, 'index'])->name('home');
    Route::get('/projects', fn () => redirect(route('home').'#projects'))->name('projects.index');
    Route::get('/projects/{project:slug}', [PortfolioController::class, 'project'])->name('projects.show');
    Route::get('/resume', [PortfolioController::class, 'resume'])->name('resume');
    Route::get('/contact', fn () => redirect(route('home').'#contact'))->name('contact');
    Route::post('/contact', [PortfolioController::class, 'contact'])->middleware('throttle:contact')->name('contact.store');
    Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{post:slug}', [BlogController::class, 'show'])->name('blog.show');
    Route::get('/notes', [BlogController::class, 'notes'])->name('notes.index');
    Route::get('/notes/{post:slug}', [BlogController::class, 'note'])->name('notes.show');
    Route::get('/playground', [PortfolioController::class, 'playground'])->name('playground');
    Route::post('/assistant', AssistantController::class)->middleware('throttle:assistant')->name('assistant');
});
