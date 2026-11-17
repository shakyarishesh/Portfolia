<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Import\Extractors\AnthropicExtractor;
use App\Services\Import\Extractors\LlmExtractor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\App\Services\Portfolio\Assistant::class, fn ($app) => new \App\Services\Portfolio\Assistant($app->make(\App\Services\Portfolio\PortfolioData::class), config('portfolio.assistant')));

        // Only one provider today; add a match arm here to support another.
        $this->app->bind(LlmExtractor::class, fn () => match (config('portfolio.ai.provider')) {
            'anthropic' => new AnthropicExtractor(config('portfolio.ai.anthropic')),
            default => throw new \RuntimeException('Unsupported AI provider: '.config('portfolio.ai.provider')),
        });
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'profile' => \App\Models\Profile::class,
            'section' => \App\Models\Section::class,
            'skill_category' => \App\Models\SkillCategory::class,
            'skill' => \App\Models\Skill::class,
            'experience' => \App\Models\Experience::class,
            'project' => \App\Models\Project::class,
            'education' => \App\Models\Education::class,
            'certification' => \App\Models\Certification::class,
            'achievement' => \App\Models\Achievement::class,
            'expertise_area' => \App\Models\ExpertiseArea::class,
            'social_link' => \App\Models\SocialLink::class,
            'post' => \App\Models\Post::class,
            'testimonial' => \App\Models\Testimonial::class,
            'user' => User::class,
        ]);

        Gate::define('admin', fn (User $user) => $user->is_admin);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('contact', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('demo-api', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('assistant', fn (Request $request) => [Limit::perMinute(5)->by($request->ip()), Limit::perDay(40)->by($request->ip())]);
    }
}
