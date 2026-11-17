<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ProfileForm;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\PageView;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function edit()
    {
        $profile = Profile::query()->with(['avatar', 'resume', 'resumes'])->firstOrFail();

        return view('admin.profile', [
            'profile' => $profile,
            'resumeDownloads' => PageView::query()->where('kind', 'resume')->selectRaw('subject, COUNT(*) as n')->groupBy('subject')->pluck('n', 'subject'),
            'values' => ProfileForm::formValues($profile),
            'history' => $profile->auditLogs()->with('user')->limit(10)->get(),
        ]);
    }

    public function update(Request $request)
    {
        ProfileForm::save(Profile::query()->firstOrFail(), $request->validate(ProfileForm::rules()));

        return back()->with('success', 'Profile saved.');
    }

    public function upload(Request $request)
    {
        $profile = Profile::query()->firstOrFail();
        $isResume = $request->input('collection') === 'resume';
        $max = config($isResume ? 'portfolio.uploads.resume_max_kb' : 'portfolio.uploads.image_max_kb');
        $request->validate([
            'collection' => ['required', 'in:avatar,resume'],
            'file' => $isResume
                ? ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', "max:{$max}"]
                : ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$max}", 'dimensions:min_width=100,min_height=100,max_width=6000,max_height=6000'],
        ]);

        $collection = $request->input('collection');
        $file = $request->file('file');
        if ($isResume) {
            // Keep earlier CVs as versions; the new upload becomes the active one.
            $profile->media()->where('collection', 'resume')->update(['is_active' => false]);
        } else {
            $profile->media()->where('collection', $collection)->get()->each->delete();
        }
        $profile->media()->create([
            'collection' => $collection,
            'disk' => $isResume ? 'local' : 'public', // the CV is served through a download route, not a public URL
            'path' => $file->store($collection, $isResume ? 'local' : 'public'),
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 250),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'alt' => $isResume ? null : $profile->full_name,
            'is_active' => $isResume,
        ]);

        return back()->with('success', $isResume ? 'Resume uploaded.' : 'Avatar uploaded.');
    }

    public function activateResume(Media $media)
    {
        abort_unless($media->collection === 'resume' && $media->model_type === 'profile', 404);
        DB::transaction(function () use ($media) {
            // Plain queries: the bound model's in-memory flag can be stale, which would skip the save.
            Media::query()->where('collection', 'resume')->whereKeyNot($media->id)->update(['is_active' => false]);
            Media::query()->whereKey($media->id)->update(['is_active' => true]);
        });

        return back()->with('success', "“{$media->original_name}” is now the CV visitors download.");
    }

    public function destroyResume(Media $media)
    {
        abort_unless($media->collection === 'resume' && $media->model_type === 'profile', 404);
        $media->delete();

        return back()->with('success', "Deleted “{$media->original_name}”.");
    }

    public function removeUpload(Request $request)
    {
        $collection = $request->validate(['collection' => ['required', 'in:avatar,resume']])['collection'];
        Profile::query()->firstOrFail()->media()->where('collection', $collection)->get()->each->delete();

        return back()->with('success', ucfirst($collection).' removed.');
    }
}
