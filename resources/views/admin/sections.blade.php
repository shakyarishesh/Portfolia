@extends('admin.layout')
@section('title', 'Sections & order')
@section('content')
<p class="muted" style="margin-bottom:16px">These are the tabs on the public site. Hidden sections, and sections with nothing published, are left out automatically.</p>
<div class="stack">
  @foreach ($sections as $section)
    <form class="card" method="post" action="{{ route('admin.sections.update', $section) }}">
      @csrf @method('PUT')
      <div class="topbar" style="margin-bottom:12px">
        <h2 style="margin:0">{{ $loop->iteration }}. {{ $section->nav_label }} <span class="badge">{{ $section->key }}</span> @unless ($section->is_visible)<span class="badge draft">hidden</span>@endunless</h2>
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
      </div>
      <div class="form-actions"><button class="btn primary sm" type="submit" data-loading="Saving…">Save</button></div>
    </form>
    <form id="up-{{ $section->id }}" method="post" action="{{ route('admin.sections.move', $section) }}" hidden>@csrf<input type="hidden" name="direction" value="up"></form>
    <form id="down-{{ $section->id }}" method="post" action="{{ route('admin.sections.move', $section) }}" hidden>@csrf<input type="hidden" name="direction" value="down"></form>
  @endforeach
</div>
@endsection
