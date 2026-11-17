<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\Achievement;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class AchievementResource extends Resource
{
    public const KEY = 'achievement';

    public const SLUG = 'achievements';

    public const LABEL = 'Achievements & milestones';

    public const SINGULAR = 'Achievement';

    public const MODEL = Achievement::class;

    public const SEARCH = ['title', 'summary', 'category'];

    public const NATURAL_KEY = ['title'];

    public const ORDER_SCOPE = 'kind';

    public static function fields(): array
    {
        return [
            'kind' => ['type' => 'select', 'label' => 'Kind', 'options' => Achievement::KINDS, 'rules' => ['required', Rule::in(array_keys(Achievement::KINDS))], 'default' => 'highlight',
                'help' => 'Highlights show as cards under Projects; milestones form the career timeline.'],
            'title' => ['type' => 'text', 'label' => 'Title', 'rules' => ['required', 'string', 'max:150']],
            'label' => ['type' => 'text', 'label' => 'Tag', 'help' => 'Small label above the title, e.g. "Payroll · Core".', 'rules' => ['nullable', 'string', 'max:60']],
            'summary' => ['type' => 'textarea', 'label' => 'Summary', 'rules' => ['nullable', 'string', 'max:2000']],
            'details' => ['type' => 'lines', 'label' => 'Details', 'help' => 'One per line (shown when expanded).', 'rules' => ['nullable', 'string', 'max:5000']],
            'category' => ['type' => 'text', 'label' => 'Filter category', 'help' => 'Highlights only, e.g. payroll, tax, platform. Becomes a filter chip.', 'rules' => ['nullable', 'string', 'max:40', 'alpha_dash']],
            'date_label' => ['type' => 'text', 'label' => 'Date label', 'help' => 'Milestones only, e.g. "Oct 2024" or "Mid 2025".', 'rules' => ['nullable', 'string', 'max:60']],
            'project_id' => ['type' => 'select', 'label' => 'Project', 'placeholder' => 'Not linked', 'options' => Project::ordered()->pluck('name', 'id')->all(),
                'rules' => ['nullable', 'integer', Rule::exists('projects', 'id')]],
            'status' => static::statusField(),
            'source_note' => static::sourceNoteField(),
        ];
    }

    public static function columns(): array
    {
        return ['title' => 'Title', 'kind' => fn ($r) => Achievement::KINDS[$r->kind] ?? $r->kind, 'when' => fn ($r) => $r->category ?: $r->date_label];
    }

    public static function query(): Builder
    {
        return Achievement::query()->with('project');
    }

    public static function filters(): array
    {
        return ['kind' => ['Kind', Achievement::KINDS]];
    }
}
