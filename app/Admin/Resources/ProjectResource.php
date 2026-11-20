<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\Experience;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ProjectResource extends Resource
{
    public const KEY = 'project';

    public const SLUG = 'projects';

    public const LABEL = 'Projects';

    public const SINGULAR = 'Project';

    public const MODEL = Project::class;

    public const SEARCH = ['name', 'tagline', 'description'];

    public const NATURAL_KEY = ['name'];

    /** Fields only shown when one of the portfolio's modules asks for them (config portfolio.modules.*.project_fields). */
    public const MODULE_FIELDS = ['problem', 'features', 'architecture', 'challenges', 'diagram', 'repo_url', 'technologies'];

    /** Form order. Profession-specific fields sit between the overview and the role/dates. */
    private const ORDER = ['name', 'slug', 'tagline', 'category', 'disciplines', 'description',
        'problem', 'client', 'industry', 'subject', 'audience', 'team_size', 'features', 'responsibilities', 'process', 'strategy',
        'execution', 'methodology', 'materials', 'architecture', 'challenges', 'diagram', 'outcome', 'impact', 'metrics', 'budget',
        'role', 'experience_id', 'start_date', 'end_date', 'is_current', 'live_url', 'repo_url', 'video_url', 'technologies',
        'meta_description', 'is_featured', 'status', 'source_note'];

    /** The form for the current portfolio's type (Technical & IT when there is none). */
    public static function fields(): array
    {
        $portfolio = \App\Support\CurrentPortfolio::get();

        return static::fieldsFor($portfolio?->moduleKeys() ?? ['technical'], $portfolio?->isHybrid() ? $portfolio->disciplineSlugs() : []);
    }

    /**
     * Project fields for these modules: the common ones plus each module's own, labelled by the first module that
     * uses a field (so a Hybrid portfolio never gets the same field twice). $disciplines (Hybrid) adds a "Disciplines" choice.
     */
    public static function fieldsFor(array $modules, array $disciplines = []): array
    {
        $wanted = $labels = [];
        foreach ($modules as $module) {
            foreach (config("portfolio.modules.{$module}.project_fields", []) as $name => $label) {
                $wanted[$name] ??= $label ?? false;
            }
            foreach (config("portfolio.modules.{$module}.labels", []) as $name => $label) {
                $labels[$name] ??= $label;
            }
        }
        $all = static::catalog($disciplines);
        $details = Project::detailKeys();

        $fields = [];
        foreach (self::ORDER as $name) {
            $optional = in_array($name, self::MODULE_FIELDS, true) || (in_array($name, $details, true) && $name !== 'disciplines');
            if (! isset($all[$name]) || ($optional && ! array_key_exists($name, $wanted)) || ($name === 'disciplines' && count($disciplines) < 2)) {
                continue;
            }
            $label = ($wanted[$name] ?? null) ?: ($labels[$name] ?? null);
            $fields[$name] = $label ? ['label' => $label] + $all[$name] : $all[$name];
        }

        return $fields;
    }

    /** Every project field a module can use. */
    private static function catalog(array $disciplines): array
    {
        $id = request()->route('id');
        $fields = [
            'name' => ['type' => 'text', 'label' => 'Project name', 'rules' => ['required', 'string', 'max:150']],
            'slug' => ['type' => 'text', 'label' => 'URL slug', 'help' => 'Leave blank to generate from the name.',
                'rules' => ['nullable', 'alpha_dash', 'max:160', Rule::unique('projects', 'slug')->where('portfolio_id', \App\Support\CurrentPortfolio::id())->ignore($id)]],
            'tagline' => ['type' => 'text', 'label' => 'Tagline', 'rules' => ['nullable', 'string', 'max:255']],
            'category' => ['type' => 'text', 'label' => 'Category', 'help' => 'Groups related work; visitors can filter by it.', 'rules' => ['nullable', 'string', 'max:60']],
            'description' => ['type' => 'textarea', 'label' => 'Overview', 'rules' => ['nullable', 'string', 'max:5000']],
            'problem' => ['type' => 'textarea', 'label' => 'Problem & objectives', 'rules' => ['nullable', 'string', 'max:5000']],
            'features' => ['type' => 'lines', 'label' => 'Key features / what I built', 'help' => 'One per line.', 'rules' => ['nullable', 'string', 'max:8000']],
            'architecture' => ['type' => 'textarea', 'label' => 'Technical architecture', 'rules' => ['nullable', 'string', 'max:5000']],
            'challenges' => ['type' => 'textarea', 'label' => 'Challenges & solutions', 'rows' => 6, 'help' => 'Blank line between paragraphs. Start a paragraph with **Challenge:** to make it stand out.', 'rules' => ['nullable', 'string', 'max:8000']],
            'diagram' => ['type' => 'textarea', 'label' => 'Architecture diagram (Mermaid)', 'rows' => 8, 'help' => 'Optional Mermaid source, e.g. "flowchart LR; Client-->API". Use sanitized, public-safe structure only.', 'rules' => ['nullable', 'string', 'max:8000']],
            'role' => ['type' => 'text', 'label' => 'My role', 'rules' => ['nullable', 'string', 'max:150']],
            'experience_id' => ['type' => 'select', 'label' => 'Built while at', 'placeholder' => 'Independent / not linked',
                'options' => Experience::ordered()->get()->mapWithKeys(fn ($e) => [$e->id => "{$e->company} ({$e->role})"])->all(),
                'rules' => ['nullable', 'integer', Rule::exists('experiences', 'id')]],
            'start_date' => ['type' => 'date', 'label' => 'Start date', 'rules' => ['nullable', 'date']],
            'end_date' => ['type' => 'date', 'label' => 'End date', 'rules' => ['nullable', 'date', 'after_or_equal:start_date']],
            'is_current' => ['type' => 'checkbox', 'label' => 'Ongoing', 'rules' => ['nullable', 'boolean']],
            'live_url' => ['type' => 'url', 'label' => 'Live URL', 'rules' => ['nullable', 'url:http,https', 'max:255']],
            'repo_url' => ['type' => 'url', 'label' => 'Repository URL', 'rules' => ['nullable', 'url:http,https', 'max:255']],
            'technologies' => ['type' => 'tags', 'label' => 'Technologies', 'help' => 'Comma separated.', 'rules' => ['nullable', 'string', 'max:1000']],
            'meta_description' => ['type' => 'text', 'label' => 'SEO description', 'wide' => true, 'help' => 'Defaults to the tagline.', 'rules' => ['nullable', 'string', 'max:300']],
            'is_featured' => ['type' => 'checkbox', 'label' => 'Featured', 'rules' => ['nullable', 'boolean']],
            'status' => static::statusField(),
            'source_note' => static::sourceNoteField(),
        ];

        foreach (config('portfolio.project_details', []) as $name => $d) {
            $field = ['type' => $d['type'], 'label' => $d['label']] + array_intersect_key($d, array_flip(['help', 'private']));
            $field['rules'] = match ($d['type']) {
                'text' => ['nullable', 'string', 'max:'.($d['max'] ?? 255)],
                'textarea' => ['nullable', 'string', 'max:'.($d['max'] ?? 5000)],
                'url' => ['nullable', 'url:http,https', 'max:255'],
                'number' => ['nullable', 'integer', 'min:1', 'max:1000000'],
                'lines' => ['nullable', 'string', 'max:'.($d['max'] ?? 4000)],
                'checkboxes' => ['nullable', 'array'],
            };
            $fields[$name] = $field;
        }
        $types = \App\Models\PortfolioType::query()->whereIn('slug', $disciplines)->pluck('name', 'slug');
        $fields['disciplines']['options'] = collect($disciplines)->filter(fn ($s) => $types->has($s))->mapWithKeys(fn ($s) => [$s => $types[$s]])->all();
        $fields['disciplines']['help'] = 'Which of your disciplines this belongs to — tick more than one if it spans them.';

        return $fields;
    }

    public static function rules(?Model $record = null): array
    {
        $fields = static::fields();
        $rules = collect($fields)->mapWithKeys(fn (array $f, string $name) => [$name => $f['rules'] ?? ['nullable']])->all();
        if (isset($fields['disciplines'])) {
            $rules['disciplines.*'] = ['string', Rule::in(array_keys($fields['disciplines']['options']))];
        }

        return $rules;
    }

    public static function columns(): array
    {
        return ['name' => 'Project', 'period' => fn ($r) => $r->period(), 'featured' => fn ($r) => $r->is_featured ? 'Featured' : ''];
    }

    public static function query(): Builder
    {
        return Project::query()->with(['technologies', 'thumbnail']);
    }

    protected static function beforeSave(Model $record): void
    {
        if (blank($record->slug)) {
            $record->slug = static::uniqueSlug($record->name, $record->id);
        }
    }
}
