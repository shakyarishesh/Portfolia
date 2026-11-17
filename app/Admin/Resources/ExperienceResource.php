<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\Experience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class ExperienceResource extends Resource
{
    public const KEY = 'experience';

    public const SLUG = 'experiences';

    public const LABEL = 'Experience';

    public const SINGULAR = 'Experience';

    public const MODEL = Experience::class;

    public const SEARCH = ['company', 'role', 'summary'];

    public const NATURAL_KEY = ['company'];

    public static function fields(): array
    {
        return [
            'company' => ['type' => 'text', 'label' => 'Company', 'rules' => ['required', 'string', 'max:150']],
            'company_url' => ['type' => 'url', 'label' => 'Company website', 'rules' => ['nullable', 'url:http,https', 'max:255']],
            'role' => ['type' => 'text', 'label' => 'Role / title', 'rules' => ['required', 'string', 'max:150']],
            'employment_type' => ['type' => 'select', 'label' => 'Type', 'options' => Experience::TYPES, 'rules' => ['required', Rule::in(array_keys(Experience::TYPES))], 'default' => 'full_time'],
            'location' => ['type' => 'text', 'label' => 'Location', 'rules' => ['nullable', 'string', 'max:150']],
            'start_date' => ['type' => 'date', 'label' => 'Start date', 'rules' => ['required', 'date']],
            'end_date' => ['type' => 'date', 'label' => 'End date', 'rules' => ['nullable', 'date', 'after_or_equal:start_date']],
            'is_current' => ['type' => 'checkbox', 'label' => 'I currently work here', 'rules' => ['nullable', 'boolean']],
            'summary' => ['type' => 'textarea', 'label' => 'Summary', 'rules' => ['nullable', 'string', 'max:2000']],
            'highlights' => ['type' => 'lines', 'label' => 'Responsibilities & achievements', 'help' => 'One per line.', 'rules' => ['nullable', 'string', 'max:5000']],
            'technologies' => ['type' => 'tags', 'label' => 'Tech stack', 'help' => 'Comma separated.', 'rules' => ['nullable', 'string', 'max:1000']],
            'status' => static::statusField(),
            'source_note' => static::sourceNoteField(),
        ];
    }

    public static function columns(): array
    {
        return ['company' => 'Company', 'role' => 'Role', 'period' => fn ($r) => $r->period()];
    }

    public static function query(): Builder
    {
        return Experience::query()->with('technologies');
    }

    public static function title(\Illuminate\Database\Eloquent\Model $record): string
    {
        return "{$record->role} — {$record->company}";
    }
}
