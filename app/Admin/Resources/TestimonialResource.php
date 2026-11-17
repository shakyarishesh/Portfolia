<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\Testimonial;

class TestimonialResource extends Resource
{
    public const KEY = 'testimonial';

    public const SLUG = 'testimonials';

    public const LABEL = 'Testimonials';

    public const SINGULAR = 'Testimonial';

    public const MODEL = Testimonial::class;

    public const SEARCH = ['name', 'company', 'quote'];

    public const IMPORTABLE = false;

    public const NATURAL_KEY = ['name'];

    public static function fields(): array
    {
        return [
            'name' => ['type' => 'text', 'label' => 'Name', 'rules' => ['required', 'string', 'max:120']],
            'position' => ['type' => 'text', 'label' => 'Position', 'rules' => ['nullable', 'string', 'max:120']],
            'company' => ['type' => 'text', 'label' => 'Company', 'rules' => ['nullable', 'string', 'max:120']],
            'url' => ['type' => 'url', 'label' => 'Profile URL (e.g. LinkedIn)', 'rules' => ['nullable', 'url:http,https', 'max:255']],
            'quote' => ['type' => 'textarea', 'label' => 'Testimonial', 'rules' => ['required', 'string', 'max:2000']],
            'status' => static::statusField(),
            'source_note' => static::sourceNoteField() + ['help' => 'Record how and when the person agreed to be quoted. Only publish testimonials you have permission for.'],
        ];
    }

    public static function columns(): array
    {
        return ['name' => 'Name', 'company' => fn ($r) => collect([$r->position, $r->company])->filter()->implode(', ')];
    }
}
