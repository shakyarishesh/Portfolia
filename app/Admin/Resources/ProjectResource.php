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

    public static function fields(): array
    {
        $id = request()->route('id');

        return [
            'name' => ['type' => 'text', 'label' => 'Project name', 'rules' => ['required', 'string', 'max:150']],
            'slug' => ['type' => 'text', 'label' => 'URL slug', 'help' => 'Leave blank to generate from the name.',
                'rules' => ['nullable', 'alpha_dash', 'max:160', Rule::unique('projects', 'slug')->ignore($id)]],
            'tagline' => ['type' => 'text', 'label' => 'Tagline', 'rules' => ['nullable', 'string', 'max:255']],
            'category' => ['type' => 'text', 'label' => 'Category', 'help' => 'e.g. HR & Payroll, Telephony, Education. Used by the project filters.', 'rules' => ['nullable', 'string', 'max:60']],
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
