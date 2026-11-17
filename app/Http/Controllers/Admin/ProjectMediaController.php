<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PortfolioController;
use App\Models\Media;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectMediaController extends Controller
{
    /** Collections that hold a single image (uploading replaces it). */
    private const SINGLE = ['thumbnail', 'cover'];

    public function store(Request $request, Project $project)
    {
        return $this->upload($request, $project, ['thumbnail', 'screenshot'], "projects/{$project->id}");
    }

    public function storeForPost(Request $request, Post $post)
    {
        return $this->upload($request, $post, ['cover'], "posts/{$post->id}");
    }

    private function upload(Request $request, Model $owner, array $collections, string $dir)
    {
        $max = config('portfolio.uploads.image_max_kb');
        $data = $request->validate([
            'collection' => ['required', Rule::in($collections)],
            'images' => ['required', 'array', 'min:1', 'max:10'],
            // No SVG: it can carry scripts.
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp,gif', "max:{$max}", 'dimensions:max_width=6000,max_height=6000'],
            'alt' => ['nullable', 'string', 'max:200'],
        ], [], ['images.*' => 'image']);

        if (in_array($data['collection'], self::SINGLE, true)) {
            $owner->media()->where('collection', $data['collection'])->get()->each->delete();
            $data['images'] = array_slice($data['images'], 0, 1);
        }

        $next = (int) $owner->media()->max('sort_order');
        foreach ($data['images'] as $file) {
            $owner->media()->create([
                'collection' => $data['collection'],
                'disk' => 'public',
                'path' => $file->store($dir, 'public'),
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 250),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'alt' => $data['alt'] ?? null,
                'sort_order' => ++$next,
            ]);
        }

        return back()->with('success', count($data['images']) === 1 ? 'Image uploaded.' : count($data['images']).' images uploaded.');
    }

    public function update(Request $request, Media $media)
    {
        $media->update($request->validate(['alt' => ['nullable', 'string', 'max:200']]));

        return back()->with('success', 'Alt text saved.');
    }

    public function destroy(Media $media)
    {
        $media->delete();

        return back()->with('success', 'Image removed.');
    }

    public function preview(Project $project, PortfolioController $portfolio)
    {
        return $portfolio->renderProject($project, true);
    }
}
