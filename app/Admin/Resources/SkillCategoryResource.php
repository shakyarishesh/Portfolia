<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\SkillCategory;
use Illuminate\Validation\Rule;

class SkillCategoryResource extends Resource
{
    public const KEY = 'skill_category';

    public const SLUG = 'skill-categories';

    public const LABEL = 'Skill categories';

    public const SINGULAR = 'Skill category';

    public const MODEL = SkillCategory::class;

    public const SEARCH = ['name', 'description'];

    public const NATURAL_KEY = ['name'];

    public static function fields(): array
    {
        $id = request()->route('id');

        return [
            'name' => ['type' => 'text', 'label' => 'Name', 'rules' => ['required', 'string', 'max:100', Rule::unique('skill_categories', 'name')->where('portfolio_id', \App\Support\CurrentPortfolio::id())->ignore($id)]],
            'description' => ['type' => 'text', 'label' => 'Description', 'rules' => ['nullable', 'string', 'max:255']],
            'status' => static::statusField(),
            'source_note' => static::sourceNoteField(),
        ];
    }

    public static function columns(): array
    {
        return ['name' => 'Name', 'skills' => fn ($r) => $r->skills_count.' skills'];
    }

    public static function query(): \Illuminate\Database\Eloquent\Builder
    {
        return SkillCategory::query()->withCount('skills');
    }
}
