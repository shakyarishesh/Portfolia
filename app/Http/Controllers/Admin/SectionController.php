<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Models\Section;
use App\Support\CurrentPortfolio;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    public function index()
    {
        $portfolio = CurrentPortfolio::get();
        $sections = Section::query()->ordered()->get();

        return view('admin.sections', [
            'sections' => $sections,
            // Ready-made sections not added yet; the type's own come first.
            'addable' => collect(Portfolio::sectionCatalog($portfolio->moduleKeys()))
                ->except($sections->pluck('key')->all())
                ->filter(fn ($s, $key) => Portfolio::isFreeTextSection($key))
                ->sortBy(fn ($s, $key) => in_array($key, $portfolio->typeSectionKeys(), true) ? 0 : 1)
                ->map(fn ($s) => $s[2])->all(),
        ]);
    }

    /** Add a ready-made free-text section (Publications, Languages …) or a custom one with its own title. */
    public function store(Request $request)
    {
        $catalog = Portfolio::sectionCatalog(CurrentPortfolio::get()->moduleKeys());
        $data = $request->validate([
            'key' => ['required', 'string', Rule::in([...array_keys(array_filter($catalog, fn ($s, $k) => Portfolio::isFreeTextSection($k), ARRAY_FILTER_USE_BOTH)), 'custom'])],
            'title' => ['required_if:key,custom', 'nullable', 'string', 'max:150'],
        ]);
        if ($data['key'] === 'custom') {
            $base = 'custom-'.(Str::slug($data['title']) ?: 'section');
            $key = $base;
            for ($i = 2; Section::query()->where('key', $key)->exists(); $i++) {
                $key = "{$base}-{$i}";
            }
            [$label, $eyebrow, $title] = [Str::limit($data['title'], 30, ''), null, $data['title']];
        } else {
            $key = $data['key'];
            [$label, $eyebrow, $title] = $catalog[$key];
            if (Section::query()->where('key', $key)->exists()) {
                return back()->withErrors(['key' => 'That section is already on your portfolio.']);
            }
        }
        $section = Section::create(['key' => $key, 'nav_label' => $label, 'eyebrow' => $eyebrow, 'title' => $title,
            'sort_order' => (int) Section::query()->max('sort_order') + 1]);

        return redirect(route('admin.sections.index').'#section-'.$section->id)->with('success', "“{$section->title}” added — write its text below. It stays off the public site until it has some.");
    }

    /** Only free-text sections can be removed; the built-in ones are hidden instead. */
    public function destroy(Section $section)
    {
        abort_unless(Portfolio::isFreeTextSection($section->key), 403);
        $section->delete();

        return back()->with('success', "“{$section->title}” removed. You can restore it from the audit log.");
    }

    public function update(Request $request, Section $section)
    {
        $data = $request->validate([
            'nav_label' => ['required', 'string', 'max:30'],
            'eyebrow' => ['nullable', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:150'],
            'is_visible' => ['nullable', 'boolean'],
            'body' => ['nullable', 'string', 'max:20000'],
        ]);
        if (! Portfolio::isFreeTextSection($section->key)) {
            unset($data['body']); // built-in sections show records, not text
        }
        $data['is_visible'] = $request->boolean('is_visible');
        if ($section->key === 'home' && ! $data['is_visible']) {
            return back()->withErrors(['is_visible' => 'The Home section is always shown.']);
        }
        $section->update($data);

        return back()->with('success', "“{$section->nav_label}” saved.");
    }

    public function move(Request $request, Section $section)
    {
        $up = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'] === 'up';
        DB::transaction(function () use ($section, $up) {
            $ids = Section::query()->ordered()->lockForUpdate()->pluck('id')->all();
            $pos = array_search($section->id, $ids, true);
            $other = $up ? $pos - 1 : $pos + 1;
            if (isset($ids[$other])) {
                [$ids[$pos], $ids[$other]] = [$ids[$other], $ids[$pos]];
                foreach ($ids as $i => $id) {
                    Section::whereKey($id)->first()->update(['sort_order' => $i + 1]);
                }
            }
        });

        return back();
    }
}
