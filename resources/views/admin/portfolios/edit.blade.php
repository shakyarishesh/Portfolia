@extends('admin.layout')
@section('title', $portfolio->name)
@section('crumbs')<a href="{{ route('admin.portfolios.index') }}">Portfolios</a> / {{ $portfolio->username }}@endsection
@section('actions')
  @unless ($isCurrent)
    <form class="inline" method="post" action="{{ route('admin.portfolios.switch', $portfolio) }}">@csrf<button class="btn">Edit this portfolio's content</button></form>
  @endunless
  <a class="btn" href="{{ route('admin.portfolios.preview', $portfolio) }}" target="_blank" rel="noopener">Preview ↗</a>
@endsection
@section('content')
@php $settingsSchema = $templates[$settingsKey]['settings'] ?? []; @endphp
<div class="split">
  <div class="stack">
    @if ($portfolio->draft_template && isset($templates[$portfolio->draft_template]))
      <div class="alert warn draft-bar">
        <span><b>Draft template:</b> {{ $templates[$portfolio->draft_template]['name'] }} — not live yet. Visitors still see {{ $templates[$portfolio->template]['name'] ?? $portfolio->template }}.</span>
        <span class="actions">
          <a class="btn sm" href="{{ route('admin.portfolios.preview', [$portfolio, 'template' => $portfolio->draft_template]) }}" target="_blank" rel="noopener">Preview draft ↗</a>
          <form class="inline" method="post" action="{{ route('admin.portfolios.template.publish', $portfolio) }}" data-confirm="Make {{ $templates[$portfolio->draft_template]['name'] }} the live template? Content is not changed.">@csrf<button class="btn sm primary">Publish template</button></form>
          <form class="inline" method="post" action="{{ route('admin.portfolios.template', $portfolio) }}">@csrf<input type="hidden" name="template" value="{{ $portfolio->template }}"><button class="btn sm">Discard draft</button></form>
        </span>
      </div>
    @endif

    <form class="card" method="post" action="{{ route('admin.portfolios.update', $portfolio) }}">
      @csrf @method('PUT')
      <h2>Details</h2>
      @include('admin.portfolios._fields')

      <h2 style="margin-top:24px">{{ $templates[$settingsKey]['name'] }} settings @if ($settingsKey === $portfolio->draft_template)<span class="badge pending">draft</span>@endif</h2>
      <input type="hidden" name="settings_template" value="{{ $settingsKey }}">
      @if ($settingsSchema)
        <div class="form-grid">
          @foreach ($settingsSchema as $name => $field)
            @include('admin.partials.field', ['name' => $name, 'prefix' => 'settings', 'value' => $settings[$name] ?? null,
                'field' => ['type' => $field['type'] === 'boolean' ? 'checkbox' : $field['type'], 'label' => $field['label'], 'options' => $field['options'] ?? [], 'placeholder' => 'Default', 'rows' => 4]])
          @endforeach
        </div>
        <p class="small muted">Saved per template — settings you chose for other templates are kept.</p>
      @else
        <p class="small muted">This template has no extra settings.</p>
      @endif

      <div class="form-actions">
        <button class="btn primary" type="submit" data-loading="Saving…">Save changes</button>
        <a class="btn" href="{{ route('admin.portfolios.index') }}">Cancel</a>
      </div>
    </form>

    <section class="card" aria-labelledby="templates-heading">
      <h2 id="templates-heading">Template</h2>
      <p class="small muted">Choosing a template saves it as a draft. Preview it, then publish. Content is shared by all templates and never changes when you switch.</p>
      <div class="template-grid">
        @foreach ($templates as $key => $meta)
          @php $live = $key === $portfolio->template; $draft = $key === $portfolio->draft_template; @endphp
          <div class="template-card {{ $live ? 'live' : '' }} {{ $draft ? 'draft' : '' }}">
            @include('admin.portfolios._template-card')
            <div class="template-card-actions">
              @if ($live)<span class="badge published">Live</span>@endif
              @if ($draft)<span class="badge pending">Draft</span>@endif
              @php $previewUrl = route('admin.portfolios.preview', [$portfolio, 'template' => $key]); @endphp
              <a class="btn sm" href="{{ $previewUrl }}" target="_blank" rel="noopener" aria-label="Preview {{ $meta['name'] }} (desktop, new tab)">Preview ↗</a>
              <a class="btn sm" href="{{ $previewUrl }}" target="_blank" rel="noopener" data-popup="390x844" aria-label="Preview {{ $meta['name'] }} at phone size">Mobile</a>
              @unless ($live || $draft)
                <form class="inline" method="post" action="{{ route('admin.portfolios.template', $portfolio) }}">@csrf<input type="hidden" name="template" value="{{ $key }}"><button class="btn sm primary">Choose</button></form>
              @endunless
            </div>
          </div>
        @endforeach
      </div>
    </section>
  </div>

  <div class="stack">
    <div class="card stack">
      <h2>Public address</h2>
      <p><a href="{{ $portfolio->url() }}" target="_blank" rel="noopener">{{ $portfolio->url() }} ↗</a></p>
      <p><span class="badge {{ $portfolio->status }}">{{ \App\Models\Portfolio::STATUSES[$portfolio->status] ?? $portfolio->status }}</span>
        @if ($portfolio->published_at)<span class="small muted">first published {{ $portfolio->published_at->toFormattedDateString() }}</span>@endif</p>
      @unless ($portfolio->isPublic())<p class="small muted">Not visible to the public. You (and the owner) see it with a PREVIEW banner.</p>@endunless
    </div>
    <div class="card stack">
      <h2>Content &amp; sections</h2>
      @if ($isCurrent)
        <p class="small">The content screens are editing this portfolio.</p>
        <p class="small"><a href="{{ route('admin.profile.edit') }}">Personal info &amp; CV</a> · <a href="{{ route('admin.resources.index', 'projects') }}">Projects</a> · <a href="{{ route('admin.sections.index') }}">Sections &amp; order</a></p>
      @else
        <p class="small muted">The content screens (profile, projects, sections…) edit one portfolio at a time. Switch to this one first.</p>
        <form method="post" action="{{ route('admin.portfolios.switch', $portfolio) }}">@csrf<button class="btn sm">Switch to this portfolio</button></form>
      @endif
      <p class="small muted">Section visibility and order are set on <b>Sections &amp; order</b> and apply to the switched-to portfolio.</p>
    </div>
    <form class="card stack" id="delete" method="post" action="{{ route('admin.portfolios.destroy', $portfolio) }}">
      @csrf @method('DELETE')
      <h2>Danger zone</h2>
      <p class="small">Deleting permanently removes this portfolio <b>and all of its content</b> — profile, projects, posts, uploads, messages, imports and history. This cannot be undone. To hide it instead, set the status to Archived.</p>
      <div class="field">
        <label for="confirm_username">Type <code>{{ $portfolio->username }}</code> to confirm</label>
        <input type="text" id="confirm_username" name="confirm_username" autocomplete="off" required data-confirm-text="{{ $portfolio->username }}" @error('confirm_username') aria-invalid="true" @enderror>
        @error('confirm_username')<span class="error">{{ $message }}</span>@enderror
      </div>
      <button class="btn danger" type="submit">Delete portfolio permanently</button>
    </form>
  </div>
</div>
@endsection
