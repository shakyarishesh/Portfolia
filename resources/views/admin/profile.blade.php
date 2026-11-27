@extends('admin.layout')
@section('title', 'Personal info & CV')
@section('content')
<div class="split">
  <form class="card" method="post" action="{{ route('admin.profile.update') }}">
    @csrf @method('PUT')
    <div class="form-grid">
      @foreach (\App\Admin\ProfileForm::fields() as $name => $field)
        @include('admin.partials.field', ['name' => $name, 'field' => $field, 'value' => $values[$name]])
      @endforeach
    </div>
    <p class="help small muted" style="margin-top:12px">Intro, about and list items support **bold** and [links](https://example.com).</p>
    <div class="form-actions"><button class="btn primary" type="submit" data-loading="Saving…">Save profile</button></div>
  </form>

  <div class="stack">
    <div class="card stack">
      <h2>Profile photo</h2>
      @if ($profile->avatarSrc())<img class="avatar-preview" src="{{ $profile->avatarSrc() }}" alt="Current profile photo">@endif
      <p class="small muted">@if ($profile->avatar) Uploaded: {{ $profile->avatar->original_name }} @elseif ($profile->avatar_url) Using the avatar URL. @else No photo yet — the hero still looks fine without one. @endif</p>
      <form method="post" action="{{ route('admin.profile.upload') }}" enctype="multipart/form-data" class="stack">
        @csrf <input type="hidden" name="collection" value="avatar">
        <input type="file" name="file" accept="image/png,image/jpeg,image/webp" data-max-kb="{{ config('portfolio.uploads.image_max_kb') }}" required aria-label="Profile photo">
        <span class="small muted">JPG, PNG or WebP, up to {{ config('portfolio.uploads.image_max_kb') / 1024 }} MB.</span>
        <button class="btn" type="submit" data-loading="Uploading…">Upload photo</button>
      </form>
      @if ($profile->avatar)
        <form method="post" action="{{ route('admin.profile.upload.destroy') }}" data-confirm="Remove the uploaded photo?">@csrf @method('DELETE')<input type="hidden" name="collection" value="avatar"><button class="btn danger sm" type="submit">Remove uploaded photo</button></form>
      @endif
    </div>

    <div class="card stack">
      <h2>Resume / CV</h2>
      @if ($profile->resumes->isEmpty())
        <p class="small muted">No resume uploaded — the “Download CV” buttons stay hidden.</p>
      @else
        <div class="table-wrap"><table>
          <thead><tr><th>Version</th><th>Downloads</th><th></th></tr></thead>
          <tbody>
          @foreach ($profile->resumes as $cv)
            <tr>
              <td>@if ($cv->is_active)<span class="badge published">active</span> @endif{{ $cv->original_name }}<br><span class="small muted">{{ number_format($cv->size / 1024) }} KB · uploaded {{ $cv->created_at->format('j M Y') }}</span></td>
              <td>{{ $resumeDownloads[$cv->id] ?? 0 }}</td>
              <td class="actions">
                @unless ($cv->is_active)
                  <form class="inline" method="post" action="{{ route('admin.profile.resumes.activate', $cv) }}">@csrf<button class="btn sm">Make active</button></form>
                @endunless
                <form class="inline" method="post" action="{{ route('admin.profile.resumes.destroy', $cv) }}" data-confirm="Delete this CV version{{ $cv->is_active ? '? It is the active one — the download buttons will disappear until you activate another' : '' }}?">@csrf @method('DELETE')<button class="btn sm danger">Delete</button></form>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table></div>
      @endif
      <form method="post" action="{{ route('admin.profile.upload') }}" enctype="multipart/form-data" class="stack">
        @csrf <input type="hidden" name="collection" value="resume">
        <input type="file" name="file" accept="application/pdf" data-max-kb="{{ config('portfolio.uploads.resume_max_kb') }}" required aria-label="Resume PDF">
        <span class="small muted">PDF only, up to {{ config('portfolio.uploads.resume_max_kb') / 1024 }} MB. The new upload becomes active; older versions are kept.</span>
        <button class="btn" type="submit" data-loading="Uploading…">Upload new version</button>
      </form>
    </div>
    @include('admin.partials.history', ['record' => $profile])
  </div>
</div>
@endsection
