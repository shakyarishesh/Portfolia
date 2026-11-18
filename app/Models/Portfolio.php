<?php

namespace App\Models;

use App\Support\CurrentPortfolio;
use App\Support\Templates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/** One public portfolio at /{username}. Content lives in the BelongsToPortfolio tables; this row holds identity, template and status. */
class Portfolio extends Model
{
    public const STATUSES = [
        'pending' => 'Pending approval', // submitted through the public /create form, waiting for an admin
        'draft' => 'Draft',
        'published' => 'Published',
        'rejected' => 'Rejected',
        'disabled' => 'Disabled',
        'archived' => 'Archived',
    ];

    /** Sections every new portfolio starts with: key => [nav label, eyebrow, title]. */
    public const DEFAULT_SECTIONS = [
        'home' => ['Home', "Hello, I'm", 'About me'],
        'experience' => ['Experience', 'Experience', 'Where I have worked'],
        'projects' => ['Projects', 'Selected work', 'Things I have built'],
        'skills' => ['Skills', 'Toolbox', 'What I work with'],
        'education' => ['Education', 'Education', 'Where I studied'],
        'contact' => ['Contact', 'Contact', 'Get in touch'],
        'blog' => ['Blog', null, 'Writing'],
        'notes' => ['Notes', null, 'Notes'],
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'template_settings' => 'array',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(fn (Portfolio $p) => $p->username = mb_strtolower(trim((string) $p->username)));
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relations used for route-binding scoping ({username}/projects/{project:slug}).
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function isPublic(): bool
    {
        return $this->status === 'published';
    }

    public function url(string $path = ''): string
    {
        return url($this->username.($path !== '' ? '/'.ltrim($path, '/') : ''));
    }

    /** Settings for $template (default: live one), merged over the template's defaults and filtered to its allow-list. */
    public function settingsFor(?string $template = null): array
    {
        $template ??= $this->template;

        return Templates::settings($template, (array) ($this->template_settings[$template] ?? []));
    }

    /** Create a portfolio with an empty profile and the default sections, so it renders immediately. */
    public static function provision(array $attributes, array $profile = []): self
    {
        return DB::transaction(function () use ($attributes, $profile) {
            $portfolio = static::create($attributes + ['template' => Templates::DEFAULT, 'status' => 'draft']);

            CurrentPortfolio::using($portfolio, function () use ($portfolio, $profile) {
                Profile::create($profile + ['full_name' => $portfolio->name, 'headline' => '']);
                $order = 0;
                foreach (self::DEFAULT_SECTIONS as $key => [$label, $eyebrow, $title]) {
                    Section::create(['key' => $key, 'nav_label' => $label, 'eyebrow' => $eyebrow, 'title' => $title, 'sort_order' => ++$order]);
                }
            });

            return $portfolio;
        });
    }
}
