<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    public function index()
    {
        return view('admin.sections', ['sections' => Section::query()->ordered()->get()]);
    }

    public function update(Request $request, Section $section)
    {
        $data = $request->validate([
            'nav_label' => ['required', 'string', 'max:30'],
            'eyebrow' => ['nullable', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:150'],
            'is_visible' => ['nullable', 'boolean'],
        ]);
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
