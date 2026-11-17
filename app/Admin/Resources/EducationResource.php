<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\Education;

class EducationResource extends Resource
{
    public const KEY = 'education';

    public const SLUG = 'education';

    public const LABEL = 'Education';

    public const SINGULAR = 'Education';

    public const MODEL = Education::class;

    public const SEARCH = ['institution', 'degree', 'field'];

    public const NATURAL_KEY = ['institution'];

    public static function fields(): array
    {
        return [
            'institution' => ['type' => 'text', 'label' => 'Institution', 'rules' => ['required', 'string', 'max:150']],
            'degree' => ['type' => 'text', 'label' => 'Degree / qualification', 'rules' => ['required', 'string', 'max:150']],
            'field' => ['type' => 'text', 'label' => 'Field of study', 'rules' => ['nullable', 'string', 'max:150']],
            'location' => ['type' => 'text', 'label' => 'Location', 'rules' => ['nullable', 'string', 'max:150']],
            'start_year' => ['type' => 'number', 'label' => 'Start year', 'rules' => ['nullable', 'integer', 'between:1950,2100']],
            'end_year' => ['type' => 'number', 'label' => 'End year', 'rules' => ['nullable', 'integer', 'between:1950,2100', 'gte:start_year']],
            'description' => ['type' => 'textarea', 'label' => 'Notes', 'rules' => ['nullable', 'string', 'max:2000']],
            'status' => static::statusField(),
            'source_note' => static::sourceNoteField(),
        ];
    }

    public static function columns(): array
    {
        return ['institution' => 'Institution', 'degree' => 'Degree', 'period' => fn ($r) => $r->period()];
    }
}
