@extends('admin.layout')
@section('title', $record ? 'Edit '.strtolower($resource::SINGULAR) : 'New '.strtolower($resource::SINGULAR))
@section('crumbs')<a href="{{ route('admin.resources.index', $resource::SLUG) }}">{{ $resource::LABEL }}</a>@if ($record) / {{ $resource::title($record) }}@endif @endsection
@section('actions')
  @if ($record && $resource::KEY === 'project')
    <a class="btn" href="{{ route('admin.projects.preview', $record) }}" target="_blank" rel="noopener">Preview ↗</a>
    @if ($record->isPublished())<a class="btn" href="{{ route('projects.show', $record) }}" target="_blank" rel="noopener">View live ↗</a>@endif
  @endif
@endsection
@section('content')
<div class="split">
  <form class="card" method="post" action="{{ $record ? route('admin.resources.update', [$resource::SLUG, $record->id]) : route('admin.resources.store', $resource::SLUG) }}">
    @csrf
    @if ($record) @method('PUT') @endif
    <div class="form-grid">
      @foreach ($resource::fields() as $name => $field)
        @include('admin.partials.field', ['name' => $name, 'field' => $field, 'value' => $values[$name] ?? null])
      @endforeach
    </div>
    <div class="form-actions">
      <button class="btn primary" type="submit" data-loading="Saving…">{{ $record ? 'Save changes' : 'Create' }}</button>
      <a class="btn" href="{{ route('admin.resources.index', $resource::SLUG) }}">Cancel</a>
    </div>
  </form>

  <div class="stack">
    @if ($record && $resource::KEY === 'project')
      @php $record->loadMissing('media'); @endphp
      <div class="card stack">
        <h2>Thumbnail</h2>
        @php $thumb = $record->media->firstWhere('collection', 'thumbnail'); @endphp
        @if ($thumb)
          <div class="thumbs"><figure><img src="{{ $thumb->url() }}" alt="{{ $thumb->alt }}"><figcaption>
            <form method="post" action="{{ route('admin.media.destroy', $thumb) }}" data-confirm="Remove the thumbnail?">@csrf @method('DELETE')<button class="btn sm danger">Remove</button></form>
          </figcaption></figure></div>
        @else
          <p class="small muted">No thumbnail — the card shows text only.</p>
        @endif
        <form method="post" action="{{ route('admin.projects.media.store', $record) }}" enctype="multipart/form-data" class="stack">
          @csrf <input type="hidden" name="collection" value="thumbnail">
          <input type="file" name="images[]" accept="image/png,image/jpeg,image/webp,image/gif" required aria-label="Thumbnail image">
          <input type="text" name="alt" placeholder="Alt text (describe the image)" aria-label="Alt text">
          <button class="btn" type="submit" data-loading="Uploading…">{{ $thumb ? 'Replace' : 'Upload' }} thumbnail</button>
        </form>
      </div>
      <div class="card stack">
        <h2>Screenshots</h2>
        @php $shots = $record->media->where('collection', 'screenshot'); @endphp
        @if ($shots->isNotEmpty())
          <div class="thumbs">
            @foreach ($shots as $shot)
              <figure><img src="{{ $shot->url() }}" alt="{{ $shot->alt }}"><figcaption>
                <form method="post" action="{{ route('admin.media.update', $shot) }}" class="stack">@csrf @method('PATCH')<input type="text" name="alt" value="{{ $shot->alt }}" placeholder="Alt text" aria-label="Alt text"><button class="btn sm">Save alt</button></form>
                <form method="post" action="{{ route('admin.media.destroy', $shot) }}" data-confirm="Delete this screenshot?">@csrf @method('DELETE')<button class="btn sm danger">Delete</button></form>
              </figcaption></figure>
            @endforeach
          </div>
        @endif
        <form method="post" action="{{ route('admin.projects.media.store', $record) }}" enctype="multipart/form-data" class="stack">
          @csrf <input type="hidden" name="collection" value="screenshot">
          <input type="file" name="images[]" accept="image/png,image/jpeg,image/webp,image/gif" multiple required aria-label="Screenshots">
          <span class="small muted">Up to 10 at once · {{ config('portfolio.uploads.image_max_kb') / 1024 }} MB each.</span>
          <button class="btn" type="submit" data-loading="Uploading…">Upload screenshots</button>
        </form>
      </div>
    @elseif (! $record && $resource::KEY === 'project')
      <div class="card small muted">Save the project first, then add a thumbnail and screenshots.</div>
    @endif
    @if ($resource::KEY === 'post')
      <div class="card stack">
        <h2>Cover image</h2>
        @if (! $record)
          <p class="small muted">Save the article first, then add a cover image.</p>
        @else
          @php $cover = $record->media()->where('collection', 'cover')->latest('id')->first(); @endphp
          @if ($cover)
            <div class="thumbs"><figure><img src="{{ $cover->url() }}" alt="{{ $cover->alt }}"><figcaption>
              <form method="post" action="{{ route('admin.media.destroy', $cover) }}" data-confirm="Remove the cover image?">@csrf @method('DELETE')<button class="btn sm danger">Remove</button></form>
            </figcaption></figure></div>
          @endif
          <form method="post" action="{{ route('admin.posts.media.store', $record) }}" enctype="multipart/form-data" class="stack">
            @csrf <input type="hidden" name="collection" value="cover">
            <input type="file" name="images[]" accept="image/png,image/jpeg,image/webp,image/gif" required aria-label="Cover image">
            <input type="text" name="alt" placeholder="Alt text (describe the image)" aria-label="Alt text">
            <button class="btn" type="submit" data-loading="Uploading…">{{ $cover ? 'Replace' : 'Upload' }} cover</button>
          </form>
        @endif
      </div>
      @if ($record)
        <a class="btn" href="{{ route('admin.posts.preview', $record) }}" target="_blank" rel="noopener">Preview article ↗</a>
      @endif
    @endif
    @if ($record)
      @include('admin.partials.history', ['record' => $record])
      <form class="card" method="post" action="{{ route('admin.resources.destroy', [$resource::SLUG, $record->id]) }}" data-confirm="Delete “{{ $resource::title($record) }}”? You can restore it from the audit history.">
        @csrf @method('DELETE')
        <h2>Danger zone</h2>
        <button class="btn danger" type="submit">Delete {{ strtolower($resource::SINGULAR) }}</button>
      </form>
    @endif
  </div>
</div>
@endsection
