<?php

namespace App\Admin;

use App\Models\Profile;

// The single profile record shares the Resource field format so the form partials and import diff work unchanged.
class ProfileForm
{
    public const KEY = 'profile';

    public const SINGULAR = 'Profile';

    public static function fields(): array
    {
        return [
            'full_name' => ['type' => 'text', 'label' => 'Full name', 'rules' => ['required', 'string', 'max:120']],
            'headline' => ['type' => 'text', 'label' => 'Professional headline', 'help' => 'e.g. "Backend Developer".', 'rules' => ['required', 'string', 'max:120']],
            'typed_phrases' => ['type' => 'lines', 'label' => 'Rotating "I build…" phrases', 'help' => 'One per line, shown in the hero.', 'rules' => ['nullable', 'string', 'max:2000']],
            'intro' => ['type' => 'textarea', 'label' => 'Hero introduction', 'rows' => 4, 'rules' => ['nullable', 'string', 'max:1200']],
            'about' => ['type' => 'textarea', 'label' => 'About me', 'rows' => 8, 'help' => 'Separate paragraphs with a blank line.', 'rules' => ['nullable', 'string', 'max:6000']],
            'location' => ['type' => 'text', 'label' => 'Location', 'rules' => ['nullable', 'string', 'max:120']],
            'email' => ['type' => 'email', 'label' => 'Public email', 'rules' => ['nullable', 'email', 'max:150']],
            'phone' => ['type' => 'text', 'label' => 'Phone', 'rules' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s]+$/']],
            'show_phone' => ['type' => 'checkbox', 'label' => 'Show phone on the public site', 'rules' => ['nullable', 'boolean']],
            'avatar_url' => ['type' => 'url', 'label' => 'Avatar URL (fallback)', 'help' => 'Used when no avatar is uploaded, e.g. your GitHub avatar.', 'rules' => ['nullable', 'url:https', 'max:255']],
            'meta_description' => ['type' => 'textarea', 'label' => 'SEO description', 'rows' => 2, 'help' => 'Search results and link previews. ~155 characters.', 'rules' => ['nullable', 'string', 'max:300']],
            'open_to_work' => ['type' => 'checkbox', 'label' => 'Open to new opportunities', 'rules' => ['nullable', 'boolean']],
            'contact_form_enabled' => ['type' => 'checkbox', 'label' => 'Show the contact form', 'rules' => ['nullable', 'boolean']],
            'source_note' => Resource::sourceNoteField(),
        ];
    }

    public static function rules(): array
    {
        return collect(static::fields())->mapWithKeys(fn ($f, $name) => [$name => $f['rules']])->all();
    }

    /** Fields an import may propose changes to. */
    public static function contentFields(): array
    {
        return collect(static::fields())->only(['headline', 'intro', 'about', 'location', 'email'])->all();
    }

    public static function formValues(Profile $profile): array
    {
        return collect(static::fields())->mapWithKeys(fn ($f, $name) => [
            $name => $f['type'] === 'lines' ? implode("\n", (array) $profile->{$name}) : $profile->{$name},
        ])->all();
    }

    public static function comparableValues(Profile $profile, ?array $only = null): array
    {
        return collect(static::contentFields())->keys()
            ->when($only !== null, fn ($keys) => $keys->intersect($only))
            ->mapWithKeys(fn ($name) => [$name => $profile->{$name}])->all();
    }

    public static function save(Profile $profile, array $data): Profile
    {
        foreach (static::fields() as $name => $field) {
            if ($field['type'] === 'checkbox') {
                $profile->{$name} = (bool) ($data[$name] ?? false);
            } elseif (array_key_exists($name, $data)) {
                $profile->{$name} = $field['type'] === 'lines' ? Resource::splitLines($data[$name]) : ($data[$name] === '' ? null : $data[$name]);
            }
        }
        $profile->save();

        return $profile;
    }
}
