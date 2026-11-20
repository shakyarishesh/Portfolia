@extends('admin.layout')
@section('title', 'Sections & order')
@section('content')
<p class="muted" style="margin-bottom:16px">These are the tabs on the public site. Hidden sections, and sections with nothing published, are left out automatically.
  Text sections (Teaching philosophy, Publications, your own …) show the text you write here.</p>
<div class="stack">
  @foreach ($sections as $section)
    @php $freeText = \App\Models\Portfolio::isFreeTextSection($section->key); @endphp
    <form class="card" method="post" action="{{ route('admin.sections.update', $section) }}" id="section-{{ $section->id }}">
      @csrf @method('PUT')
      <div class="topbar" style="margin-bottom:12px">
        <h2 style="margin:0">{{ $loop->iteration }}. {{ $section->nav_label }} <span class="badge">{{ $freeText ? 'text' : $section->key }}</span> @if ($freeText && blank($section->body))<span class="badge pending">empty — not shown</span>@endif @unless ($section->is_visible)<span class="badge draft">hidden</span>@endunless</h2>
        <div class="actions">
          <button class="icon-btn" type="submit" form="up-{{ $section->id }}" aria-label="Move {{ $section->nav_label }} up" @disabled($loop->first)>↑</button>
          <button class="icon-btn" type="submit" form="down-{{ $section->id }}" aria-label="Move {{ $section->nav_label }} down" @disabled($loop->last)>↓</button>
        </div>
      </div>
      <div class="form-grid">
        @include('admin.partials.field', ['name' => 'nav_label', 'field' => ['type' => 'text', 'label' => 'Tab label', 'rules' => ['required']], 'value' => $section->nav_label])
        @include('admin.partials.field', ['name' => 'eyebrow', 'field' => ['type' => 'text', 'label' => 'Small heading'], 'value' => $section->eyebrow])
        @include('admin.partials.field', ['name' => 'title', 'field' => ['type' => 'text', 'label' => 'Title', 'rules' => ['required']], 'value' => $section->title])
        @if ($section->key !== 'home')
          @include('admin.partials.field', ['name' => 'is_visible', 'field' => ['type' => 'checkbox', 'label' => 'Show this section'], 'value' => $section->is_visible])
        @else
          <input type="hidden" name="is_visible" value="1">
        @endif
        @if ($freeText)
          @include('admin.partials.field', ['name' => 'body', 'field' => ['type' => 'textarea', 'label' => 'Text', 'rows' => 8,
              'help' => 'Markdown: blank line between paragraphs, **bold**, [link](https://…), "- " for lists, ![alt](https://… image). Leave empty to keep the section off the public site.'], 'value' => $section->body])
        @endif
      </div>
      <div class="form-actions">
        <button class="btn primary sm" type="submit" data-loading="Saving…">Save</button>
        @if ($freeText)<button class="btn sm danger" type="submit" form="delete-{{ $section->id }}">Remove section</button>@endif
      </div>
    </form>
    @if ($freeText)
      <form id="delete-{{ $section->id }}" method="post" action="{{ route('admin.sections.destroy', $section) }}" data-confirm="Remove “{{ $section->title }}” and its text?" hidden>@csrf @method('DELETE')</form>
    @endif
    <form id="up-{{ $section->id }}" method="post" action="{{ route('admin.sections.move', $section) }}" hidden>@csrf<input type="hidden" name="direction" value="up"></form>
    <form id="down-{{ $section->id }}" method="post" action="{{ route('admin.sections.move', $section) }}" hidden>@csrf<input type="hidden" name="direction" value="down"></form>
  @endforeach

  <form class="card" method="post" action="{{ route('admin.sections.store') }}">
    @csrf
    <h2 style="margin-top:0">Add a section</h2>
    <div class="form-grid">
      @include('admin.partials.field', ['name' => 'key', 'field' => ['type' => 'select', 'label' => 'Section', 'rules' => ['required'],
          'options' => $addable + ['custom' => 'Custom section (your own title)…'], 'help' => 'Ready-made sections for your type are listed first.'], 'value' => old('key')])
      @include('admin.partials.field', ['name' => 'title', 'field' => ['type' => 'text', 'label' => 'Title (custom section only)'], 'value' => old('title')])
    </div>
    <div class="form-actions"><button class="btn sm" type="submit">Add section</button></div>
  </form>
</div>
@endsection
