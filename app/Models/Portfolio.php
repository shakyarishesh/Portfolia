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
        'disciplines' => 'array',
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

    public function portfolioType(): BelongsTo
    {
        return $this->belongsTo(PortfolioType::class, 'type', 'slug');
    }

    public function isHybrid(): bool
    {
        return $this->type === PortfolioType::HYBRID;
    }

    /** Type slugs whose modules this portfolio uses: its type, or for Hybrid the disciplines picked. */
    public function disciplineSlugs(): array
    {
        return $this->isHybrid() ? array_values((array) $this->disciplines) : [$this->type ?: PortfolioType::DEFAULT];
    }

    /** Profession-specific modules (config portfolio.modules), in discipline order, without duplicates. */
    public function moduleKeys(): array
    {
        return self::modulesFor($this->type, (array) $this->disciplines);
    }

    public static function modulesFor(?string $type, array $disciplines = []): array
    {
        $slugs = $type === PortfolioType::HYBRID ? $disciplines : [$type ?: PortfolioType::DEFAULT];
        $types = PortfolioType::query()->whereIn('slug', $slugs)->get()->sortBy(fn ($t) => array_search($t->slug, $slugs, true));
        $modules = array_values(array_unique($types->flatMap->moduleKeys()->all()));

        // Unknown/empty type (e.g. a type removed by an admin): behave like the original technical portfolio.
        return $modules ?: (array) config('portfolio.types.'.PortfolioType::DEFAULT.'.modules', ['technical']);
    }

    public function hasModule(string $key): bool
    {
        return in_array($key, $this->moduleKeys(), true);
    }

    /** Labels from the first module: work => [tab, small heading, title, one item], skills => [tab, small heading, title]. */
    public function labels(): array
    {
        return self::labelsFor($this->moduleKeys());
    }

    public static function labelsFor(array $modules): array
    {
        $module = config('portfolio.modules.'.($modules[0] ?? 'technical'), []) + config('portfolio.modules.technical');

        return ['work' => $module['work'], 'skills' => $module['skills']];
    }

    /** Every section a portfolio can have: key => [tab, small heading, title], labelled for these modules. */
    public static function sectionCatalog(array $modules = ['technical']): array
    {
        $labels = self::labelsFor($modules);
        $catalog = self::DEFAULT_SECTIONS + config('portfolio.extra_sections', []);
        $catalog['projects'] = array_slice($labels['work'], 0, 3);
        $catalog['skills'] = $labels['skills'];

        return $catalog;
    }

    /** Built-in sections have their own templates; every other section is free text (Section::$body). */
    public static function isFreeTextSection(string $key): bool
    {
        return ! array_key_exists($key, self::DEFAULT_SECTIONS);
    }

    /**
     * Add the sections this portfolio's type starts with that it doesn't have yet (at the end, visible).
     * Never removes, hides or renames anything. Must run inside CurrentPortfolio::using($this).
     *
     * @return list<string> labels of the sections added
     */
    public function addTypeSections(): array
    {
        $catalog = self::sectionCatalog($this->moduleKeys());
        $existing = Section::query()->pluck('key')->all();
        $order = (int) Section::query()->max('sort_order');
        $added = [];
        foreach ($this->typeSectionKeys() as $key) {
            if (in_array($key, $existing, true) || ! isset($catalog[$key])) {
                continue;
            }
            [$label, $eyebrow, $title] = $catalog[$key];
            Section::create(['key' => $key, 'nav_label' => $label, 'eyebrow' => $eyebrow, 'title' => $title, 'sort_order' => ++$order]);
            $added[] = $label;
        }

        return $added;
    }

    /** Section keys a new portfolio of this type starts with (Hybrid: its own list plus each discipline's). */
    public function typeSectionKeys(): array
    {
        $types = PortfolioType::query()->whereIn('slug', array_unique([$this->type, ...($this->isHybrid() ? $this->disciplineSlugs() : [])]))->get()->keyBy('slug');
        $keys = (array) ($types[$this->type]->sections ?? array_keys(self::DEFAULT_SECTIONS));
        foreach ($this->isHybrid() ? $this->disciplineSlugs() : [] as $slug) {
            $keys = array_merge($keys, (array) ($types[$slug]->sections ?? []));
        }

        return array_values(array_unique($keys));
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

        $all = (array) $this->template_settings;

        // Shared settings (welcome intro …) are stored once for every template.
        return Templates::settings($template, (array) ($all['_shared'] ?? []) + (array) ($all[$template] ?? []));
    }

    /**
     * Create a portfolio with an empty profile and its type's sections, so it renders immediately. Built-in sections the
     * type doesn't start with are created hidden, so they can be switched on later.
     */
    public static function provision(array $attributes, array $profile = []): self
    {
        return DB::transaction(function () use ($attributes, $profile) {
            $portfolio = static::create($attributes + ['template' => Templates::DEFAULT, 'status' => 'draft', 'type' => PortfolioType::DEFAULT]);

            CurrentPortfolio::using($portfolio, function () use ($portfolio, $profile) {
                Profile::create($profile + ['full_name' => $portfolio->name, 'headline' => '']);
                $portfolio->addTypeSections();
                $order = (int) Section::query()->max('sort_order');
                $catalog = self::sectionCatalog($portfolio->moduleKeys());
                foreach (array_diff(array_keys(self::DEFAULT_SECTIONS), Section::query()->pluck('key')->all()) as $key) {
                    [$label, $eyebrow, $title] = $catalog[$key];
                    Section::create(['key' => $key, 'nav_label' => $label, 'eyebrow' => $eyebrow, 'title' => $title, 'sort_order' => ++$order, 'is_visible' => false]);
                }
            });

            return $portfolio;
        });
    }
}
