@extends('admin.layout')
@section('title', $type->exists ? 'Edit type: '.$type->name : 'New portfolio type')
@section('crumbs')<a href="{{ route('admin.types.index') }}">Portfolio types</a>@endsection
@section('content')
<form method="post" action="{{ $type->exists ? route('admin.types.update', $type) : route('admin.types.store') }}" class="stack">
  @csrf @if ($type->exists) @method('PUT') @endif
  <div class="card">
    <h2>What applicants see</h2>
    <div class="form-grid">
      @unless ($type->exists)
        @include('admin.partials.field', ['name' => 'slug', 'field' => ['type' => 'text', 'label' => 'Key', 'rules' => ['required'], 'help' => 'Lower-case, e.g. healthcare. Cannot be changed later.'], 'value' => $type->slug])
      @endunless
      @include('admin.partials.field', ['name' => 'name', 'field' => ['type' => 'text', 'label' => 'Name', 'rules' => ['required']], 'value' => $type->name])
      @include('admin.partials.field', ['name' => 'icon', 'field' => ['type' => 'text', 'label' => 'Icon (an emoji)', 'help' => 'e.g. 🩺'], 'value' => $type->icon])
      @include('admin.partials.field', ['name' => 'is_active', 'field' => ['type' => 'checkbox', 'label' => 'Active — offered on /create and in settings'], 'value' => $type->is_active])
      @include('admin.partials.field', ['name' => 'description', 'field' => ['type' => 'textarea', 'label' => 'Short description', 'rows' => 2], 'value' => $type->description])
      @include('admin.partials.field', ['name' => 'example', 'field' => ['type' => 'textarea', 'label' => 'What it showcases (example)', 'rows' => 2], 'value' => $type->example])
      @include('admin.partials.field', ['name' => 'categories', 'field' => ['type' => 'lines', 'label' => 'Professional categories', 'help' => 'One per line, e.g. Nurses. Shown on the type card.'], 'value' => (array) $type->categories])
    </div>
  </div>
  <div class="card">
    <h2>What the portfolio gets</h2>
    <div class="form-grid">
      @if ($type->isHybrid())
        <p class="small muted wide">Hybrid portfolios use the modules of the disciplines the person combines.</p>
      @else
        @include('admin.partials.field', ['name' => 'modules', 'field' => ['type' => 'checkboxes', 'label' => 'Modules (project fields and wording)', 'options' => $modules], 'value' => (array) $type->modules])
      @endif
      @include('admin.partials.field', ['name' => 'sections', 'field' => ['type' => 'checkboxes', 'label' => 'Sections a new portfolio starts with (Home always)', 'options' => $sections], 'value' => (array) $type->sections])
      @include('admin.partials.field', ['name' => 'templates', 'field' => ['type' => 'checkboxes', 'label' => 'Compatible templates (the first is the default)', 'options' => $templates], 'value' => (array) $type->templates])
    </div>
  </div>
  <div class="form-actions">
    <button class="btn primary" type="submit" data-loading="Saving…">{{ $type->exists ? 'Save type' : 'Create type' }}</button>
    <a class="btn" href="{{ route('admin.types.index') }}">Cancel</a>
  </div>
</form>
@if ($type->exists)
  <form method="post" action="{{ route('admin.types.destroy', $type) }}" class="card stack" style="margin-top:20px" data-confirm="Delete “{{ $type->name }}”?">
    @csrf @method('DELETE')
    <h2>Delete</h2>
    <p class="small muted">Only types no portfolio uses can be deleted; otherwise switch it off above.</p>
    @error('type')<p class="error">{{ $message }}</p>@enderror
    <div><button class="btn danger" type="submit">Delete type</button></div>
  </form>
@endif
@endsection
