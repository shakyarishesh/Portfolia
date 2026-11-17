<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\SocialLink;
use Illuminate\Validation\Rule;

class SocialLinkResource extends Resource
{
    public const KEY = 'social_link';

    public const SLUG = 'links';

    public const LABEL = 'Professional links';

    public const SINGULAR = 'Link';

    public const MODEL = SocialLink::class;

    public const SEARCH = ['label', 'url'];

    public const NATURAL_KEY = ['url'];

    public static function fields(): array
    {
        return [
            'platform' => ['type' => 'select', 'label' => 'Platform', 'options' => SocialLink::PLATFORMS, 'rules' => ['required', Rule::in(array_keys(SocialLink::PLATFORMS))]],
            'label' => ['type' => 'text', 'label' => 'Button label', 'rules' => ['required', 'string', 'max:60']],
            'url' => ['type' => 'url', 'label' => 'URL', 'rules' => ['required', 'url:http,https', 'max:255']],
            'status' => static::statusField(),
            'source_note' => static::sourceNoteField(),
        ];
    }

    public static function columns(): array
    {
        return ['label' => 'Label', 'url' => 'URL'];
    }
}
