<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

// Public site
Route::get('/', [PortfolioController::class, 'index'])->name('home');
Route::get('/projects/{project:slug}', [PortfolioController::class, 'project'])->name('projects.show');
Route::get('/resume', [PortfolioController::class, 'resume'])->name('resume');
Route::post('/contact', [PortfolioController::class, 'contact'])->middleware('throttle:contact')->name('contact.store');
Route::get('/api/portfolio', [PortfolioController::class, 'api'])->middleware('throttle:60,1')->name('api.portfolio');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{post:slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/playground', [PortfolioController::class, 'playground'])->name('playground');
Route::post('/assistant', AssistantController::class)->middleware('throttle:assistant')->name('assistant');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots']);

// Admin authentication (no public registration; create admins with `php artisan portfolio:admin`)
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [LoginController::class, 'show'])->name('login');
    Route::post('/admin/login', [LoginController::class, 'login'])->middleware('throttle:login');
});
Route::post('/admin/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:admin'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('/profile', [Admin\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [Admin\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/upload', [Admin\ProfileController::class, 'upload'])->name('profile.upload');
    Route::delete('/profile/upload', [Admin\ProfileController::class, 'removeUpload'])->name('profile.upload.destroy');
    Route::post('/profile/resumes/{media}/activate', [Admin\ProfileController::class, 'activateResume'])->name('profile.resumes.activate');
    Route::delete('/profile/resumes/{media}', [Admin\ProfileController::class, 'destroyResume'])->name('profile.resumes.destroy');

    Route::get('/analytics', Admin\AnalyticsController::class)->name('analytics');

    Route::get('/sections', [Admin\SectionController::class, 'index'])->name('sections.index');
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

    Route::get('/imports', [Admin\ImportController::class, 'index'])->name('imports.index');
    Route::post('/imports', [Admin\ImportController::class, 'store'])->name('imports.store');
    Route::get('/imports/{import}', [Admin\ImportController::class, 'show'])->name('imports.show');
    Route::delete('/imports/{import}', [Admin\ImportController::class, 'destroy'])->name('imports.destroy');
    Route::post('/imports/{import}/ai', [Admin\ImportController::class, 'runAi'])->name('imports.ai');
    Route::get('/imports/{import}/conversations/{conversation}', [Admin\ImportController::class, 'conversation'])->name('imports.conversation');

    Route::get('/proposals', [Admin\ProposalController::class, 'index'])->name('proposals.index');
    Route::get('/proposals/new', [Admin\ProposalController::class, 'create'])->name('proposals.create');
    Route::post('/proposals', [Admin\ProposalController::class, 'store'])->name('proposals.store');
    Route::post('/proposals/dismiss-duplicates', [Admin\ProposalController::class, 'dismissDuplicates'])->name('proposals.dismiss-duplicates');
    Route::get('/proposals/{proposal}/edit', [Admin\ProposalController::class, 'edit'])->name('proposals.edit');
    Route::put('/proposals/{proposal}', [Admin\ProposalController::class, 'update'])->name('proposals.update');
    Route::post('/proposals/{proposal}/approve', [Admin\ProposalController::class, 'approve'])->name('proposals.approve');
    Route::post('/proposals/{proposal}/reject', [Admin\ProposalController::class, 'reject'])->name('proposals.reject');

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
