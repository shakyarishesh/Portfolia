<?php

namespace App\Admin;

use App\Support\ContentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * One editable content type. Fields drive the admin form, server-side validation,
 * the import review screen and the AI extraction schema, so they live in one place.
 *
 * Field types: text, textarea, url, email, date, datetime, number, select, checkbox, checkboxes (list of options), lines (JSON list), tags (technologies).
 */
abstract class Resource
{
    /** Morph-map key, also the URL segment (plural) owner. */
    public const KEY = '';

    public const SLUG = '';

    public const LABEL = '';

    public const SINGULAR = '';

    /** @var class-string<Model> */
    public const MODEL = Model::class;

    /** Columns searched by the admin list. */
    public const SEARCH = [];

    /** Fields that identify "the same record" when comparing imported data. */
    public const NATURAL_KEY = [];

    /** Offered to the conversation importer (AI/keyword proposals). */
    public const IMPORTABLE = true;

    /** Reorder only among rows sharing this column. */
    public const ORDER_SCOPE = null;

    /** @return array<string, array<string, mixed>> */
    abstract public static function fields(): array;

    /** @return array<string, callable(Model): string|string> list column => label */
    abstract public static function columns(): array;

    public static function query(): Builder
    {
        return static::newModel()->newQuery();
    }

    public static function newModel(): Model
    {
        $class = static::MODEL;

        return new $class;
    }

    public static function title(Model $record): string
    {
        $key = static::NATURAL_KEY[0] ?? 'id';

        return (string) $record->{$key};
    }

    /** Extra list filters: name => [label, options]. */
    public static function filters(): array
    {
        return [];
    }

    public static function statusField(): array
    {
        return ['type' => 'select', 'label' => 'Status', 'options' => ContentStatus::LABELS, 'rules' => ['required', Rule::in(array_keys(ContentStatus::LABELS))], 'default' => 'draft',
            'help' => 'Only "Published" items appear on the public site.'];
    }

    public static function sourceNoteField(): array
    {
        return ['type' => 'textarea', 'label' => 'Source / evidence (private)', 'rules' => ['nullable', 'string', 'max:2000'], 'rows' => 2,
            'help' => 'Where this fact came from. Never shown publicly.'];
    }

    /** @return array<string, array<int, mixed>> */
    public static function rules(?Model $record = null): array
    {
        return collect(static::fields())->mapWithKeys(fn (array $f, string $name) => [$name => $f['rules'] ?? ['nullable']])->all();
    }

    /** Fields offered to importers (no workflow/internal fields). */
    public static function contentFields(): array
    {
        return collect(static::fields())->except(['status', 'source_note', 'slug'])->all();
    }

    /** Validated form data → model attributes (+ technologies synced after save). */
    public static function save(Model $record, array $data): Model
    {
        $fields = static::fields();
        $tags = [];

        foreach ($data as $name => $value) {
            if (! isset($fields[$name])) {
                continue;
            }
            $type = $fields[$name]['type'];
            if ($type === 'tags') {
                $tags[$name] = static::splitTags($value);

                continue;
            }
            $record->{$name} = match ($type) {
                'lines' => static::splitLines($value),
                'checkbox' => (bool) $value,
                default => $value === '' ? null : $value,
            };
        }

        // Unchecked checkboxes are absent from the request.
        foreach ($fields as $name => $field) {
            if (in_array($field['type'], ['checkbox', 'checkboxes'], true) && ! array_key_exists($name, $data)) {
                $record->{$name} = $field['type'] === 'checkbox' ? false : [];
            }
        }

        static::beforeSave($record);

        if (! $record->exists) {
            $scope = static::ORDER_SCOPE;
            $record->sort_order = (int) static::query()->when($scope, fn ($q) => $q->where($scope, $record->{$scope}))->max('sort_order') + 1;
        }

        $record->save();

        foreach ($tags as $name => $names) {
            $record->syncTechnologies($names);
        }

        return $record;
    }

    protected static function beforeSave(Model $record): void {}

    /** Current values in form shape (lines/tags as text). */
    public static function formValues(?Model $record): array
    {
        $values = [];
        foreach (static::fields() as $name => $field) {
            $value = $record?->{$name} ?? null;
            if ($field['type'] === 'tags') {
                $value = $record?->exists ? $record->technologies->pluck('name')->implode(', ') : '';
            } elseif ($field['type'] === 'lines') {
                $value = implode("\n", (array) $value);
            } elseif ($field['type'] === 'date' && $value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d');
            } elseif ($field['type'] === 'datetime' && $value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d\TH:i');
            }
            $values[$name] = $value ?? ($record?->exists ? null : ($field['default'] ?? null));
        }

        return $values;
    }

    /** Comparable values (lines/tags as arrays) — used by the import diff. */
    public static function comparableValues(Model $record, ?array $only = null): array
    {
        $values = [];
        foreach (static::contentFields() as $name => $field) {
            if ($only !== null && ! in_array($name, $only, true)) {
                continue;
            }
            $value = $field['type'] === 'tags' ? $record->technologies->pluck('name')->all() : $record->{$name};
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d');
            }
            $values[$name] = $value;
        }

        return $values;
    }

    public static function splitLines(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value), 'strlen'));
        }

        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $value)), 'strlen'));
    }

    public static function splitTags(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value), 'strlen'));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), 'strlen'));
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        for ($i = 2; static::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
