<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class SkillResource extends Resource
{
    public const KEY = 'skill';

    public const SLUG = 'skills';

    public const LABEL = 'Skills';

    public const SINGULAR = 'Skill';

    public const MODEL = Skill::class;

    public const SEARCH = ['name'];

    public const NATURAL_KEY = ['name'];

    public const ORDER_SCOPE = 'skill_category_id';

    public static function fields(): array
    {
        return [
            'skill_category_id' => ['type' => 'select', 'label' => 'Category', 'options' => SkillCategory::ordered()->pluck('name', 'id')->all(),
                'rules' => ['required', 'integer', Rule::exists('skill_categories', 'id')]],
            'name' => ['type' => 'text', 'label' => 'Skill', 'help' => 'Use the same spelling as project technologies to link them.', 'rules' => ['required', 'string', 'max:100']],
            'description' => ['type' => 'text', 'label' => 'Short description', 'wide' => true, 'rules' => ['nullable', 'string', 'max:255']],
            'level' => ['type' => 'select', 'label' => 'Proficiency', 'options' => Skill::LEVELS, 'placeholder' => 'Not rated (no meter shown)',
                'rules' => ['nullable', 'integer', 'between:1,4'], 'help' => 'Rate honestly — leave unrated if unsure.'],
            'status' => static::statusField(),
            'source_note' => static::sourceNoteField(),
        ];
    }

    public static function columns(): array
    {
        return ['name' => 'Skill', 'category' => fn ($r) => $r->category?->name, 'level' => fn ($r) => $r->levelLabel() ?? '—'];
    }

    public static function query(): Builder
    {
        return Skill::query()->with('category');
    }

    /** Includes the category name so imported "skill_category" values can be compared. */
    public static function comparableValues(\Illuminate\Database\Eloquent\Model $record, ?array $only = null): array
    {
        $values = parent::comparableValues($record, $only);
        if ($only === null || in_array('skill_category', $only, true)) {
            $values['skill_category'] = $record->category?->name;
        }

        return $values;
    }

    public static function filters(): array
    {
        return ['skill_category_id' => ['Category', SkillCategory::ordered()->pluck('name', 'id')->all()]];
    }
}
