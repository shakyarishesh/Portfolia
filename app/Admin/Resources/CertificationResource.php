<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\Certification;

class CertificationResource extends Resource
{
    public const KEY = 'certification';

    public const SLUG = 'certifications';

    public const LABEL = 'Certifications';

    public const SINGULAR = 'Certification';

    public const MODEL = Certification::class;

    public const SEARCH = ['name', 'issuer'];

    public const NATURAL_KEY = ['name'];

    public static function fields(): array
    {
        return [
            'name' => ['type' => 'text', 'label' => 'Certification', 'rules' => ['required', 'string', 'max:150']],
            'issuer' => ['type' => 'text', 'label' => 'Issuer', 'rules' => ['required', 'string', 'max:150']],
            'issued_on' => ['type' => 'date', 'label' => 'Issued on', 'rules' => ['nullable', 'date']],
            'expires_on' => ['type' => 'date', 'label' => 'Expires on', 'rules' => ['nullable', 'date', 'after_or_equal:issued_on']],
            'credential_id' => ['type' => 'text', 'label' => 'Credential ID', 'rules' => ['nullable', 'string', 'max:150']],
            'credential_url' => ['type' => 'url', 'label' => 'Verification URL', 'rules' => ['nullable', 'url:http,https', 'max:255']],
            'status' => static::statusField(),
            'source_note' => static::sourceNoteField(),
        ];
    }

    public static function columns(): array
    {
        return ['name' => 'Certification', 'issuer' => 'Issuer', 'issued' => fn ($r) => $r->issued_on?->format('M Y')];
    }
}
