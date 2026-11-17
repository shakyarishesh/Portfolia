<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\ExpertiseArea;

class ExpertiseAreaResource extends Resource
{
    public const KEY = 'expertise_area';

    public const SLUG = 'expertise';

    public const LABEL = 'Technical expertise';

    public const SINGULAR = 'Expertise area';

    public const MODEL = ExpertiseArea::class;

    public const SEARCH = ['title', 'description'];

    public const NATURAL_KEY = ['title'];

    public static function fields(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Area', 'rules' => ['required', 'string', 'max:100']],
            'description' => ['type' => 'textarea', 'label' => 'What I do here', 'rules' => ['nullable', 'string', 'max:1000']],
            'status' => static::statusField(),
            'source_note' => static::sourceNoteField(),
        ];
    }

    public static function columns(): array
    {
        return ['title' => 'Area'];
    }
}
