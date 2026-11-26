<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Resource;
use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Models\PortfolioType;
use App\Support\Templates;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin → Portfolio types: what each type offers (categories, modules, starting sections, compatible templates),
 * plus the template × type compatibility matrix. Changes apply to new choices; existing portfolios keep their content.
 */
class PortfolioTypeController extends Controller
{
    public function index()
    {
        return view('admin.types.index', [
            'types' => PortfolioType::ordered()->get(),
            'counts' => Portfolio::query()->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type'),
            'templates' => Templates::available(),
            'modules' => config('portfolio.modules', []),
        ]);
    }

    public function create()
    {
        return view('admin.types.form', $this->formData(new PortfolioType(['is_active' => true, 'modules' => ['general'],
            'sections' => array_keys(Portfolio::DEFAULT_SECTIONS), 'templates' => array_keys(Templates::available())])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $type = PortfolioType::create($data + ['sort_order' => (int) PortfolioType::max('sort_order') + 1]);

        return redirect()->route('admin.types.edit', $type)->with('success', "Type “{$type->name}” created. It is offered on /create while it is active.");
    }

    public function edit(PortfolioType $type)
    {
        return view('admin.types.form', $this->formData($type));
    }

    public function update(Request $request, PortfolioType $type)
    {
        $type->update($this->validated($request, $type));

        return redirect()->route('admin.types.edit', $type)->with('success', 'Saved. New choices use it straight away; existing portfolios keep all their content.');
    }

    public function destroy(PortfolioType $type)
    {
        $used = Portfolio::query()->where('type', $type->slug)->orWhereJsonContains('disciplines', $type->slug)->count();
        if ($used || in_array($type->slug, [PortfolioType::DEFAULT, PortfolioType::HYBRID], true)) {
            return back()->withErrors(['type' => $used ? "“{$type->name}” is used by {$used} portfolio(s) — switch it off instead." : "“{$type->name}” is built in — switch it off instead."]);
        }
        $type->delete();

        return redirect()->route('admin.types.index')->with('success', "Type “{$type->name}” deleted.");
    }

    /** The matrix: templates[type slug][] = template keys. A type needs at least one template. */
    public function compatibility(Request $request)
    {
        $types = PortfolioType::ordered()->get();
        $templates = array_keys(Templates::available());
        $data = $request->validate([
            'compat' => ['required', 'array'],
            'compat.*' => ['array', 'min:1'],
            'compat.*.*' => ['string', Rule::in($templates)],
        ], ['compat.*.min' => 'Every type needs at least one template.']);

        if ($missing = $types->first(fn ($t) => empty($data['compat'][$t->slug]))) {
            return back()->withErrors(['compat' => "“{$missing->name}” needs at least one template."]);
        }
        foreach ($types as $type) {
            $picked = (array) $data['compat'][$type->slug];
            // Keep the type's recommended order for templates it already had; new ones go after.
            $type->update(['templates' => array_values(array_unique([...array_intersect((array) $type->templates, $picked), ...array_intersect($templates, $picked)]))]);
        }

        return back()->with('success', 'Template compatibility saved.');
    }

    private function formData(PortfolioType $type): array
    {
        return [
            'type' => $type,
            'modules' => array_map(fn ($m) => $m['name'].' — '.$m['description'], config('portfolio.modules', [])),
            'sections' => array_map(fn ($s) => $s[2], Portfolio::sectionCatalog()),
            'templates' => array_map(fn ($t) => $t['name'], Templates::available()),
        ];
    }

    private function validated(Request $request, ?PortfolioType $type): array
    {
        $catalog = array_keys(Portfolio::sectionCatalog());
        $hybrid = $type?->isHybrid() ?? false;
        $data = $request->validate([
            'slug' => $type ? ['exclude'] : ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,38}$/', Rule::unique('portfolio_types', 'slug')],
            'name' => ['required', 'string', 'max:80'],
            'icon' => ['nullable', 'string', 'max:16'],
            'description' => ['nullable', 'string', 'max:300'],
            'example' => ['nullable', 'string', 'max:300'],
            'categories' => ['nullable', 'string', 'max:3000'],
            'modules' => [$hybrid ? 'nullable' : 'required', 'array'],
            'modules.*' => ['string', Rule::in(array_keys(config('portfolio.modules', [])))],
            'sections' => ['required', 'array'],
            'sections.*' => ['string', Rule::in($catalog)],
            'templates' => ['required', 'array', 'min:1'],
            'templates.*' => ['string', Rule::in(array_keys(Templates::available()))],
            'is_active' => ['nullable', 'boolean'],
        ], ['slug.regex' => 'Lower-case letters, digits and underscores, starting with a letter.']);

        $categories = array_slice(array_map(fn ($c) => mb_substr($c, 0, 100), Resource::splitLines($data['categories'] ?? '')), 0, 30);
        // Sections keep the type's existing order; newly ticked ones follow in catalogue order. Home is always first.
        $sections = array_values(array_unique(['home', ...array_intersect((array) $type?->sections, $data['sections']), ...array_intersect($catalog, $data['sections'])]));

        return [
            'name' => $data['name'], 'icon' => $data['icon'] ?? null, 'description' => $data['description'] ?? null, 'example' => $data['example'] ?? null,
            'categories' => $categories, 'modules' => $hybrid ? [] : array_values($data['modules']), 'sections' => $sections,
            'templates' => array_values(array_unique([...array_intersect((array) $type?->templates, $data['templates']), ...$data['templates']])),
            'is_active' => $request->boolean('is_active'),
        ] + ($type ? [] : ['slug' => $data['slug']]);
    }
}
