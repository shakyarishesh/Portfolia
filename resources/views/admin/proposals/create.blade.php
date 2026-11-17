@extends('admin.layout')
@section('title', 'New proposal: '.strtolower($entityLabel))
@section('crumbs')@if ($conversation)<a href="{{ route('admin.imports.conversation', [$conversation->conversation_import_id, $conversation]) }}">{{ $conversation->title }}</a>@else<a href="{{ route('admin.proposals.index') }}">Review changes</a>@endif @endsection
@section('content')
<form class="card" method="post" action="{{ route('admin.proposals.store') }}">
  @csrf
  <input type="hidden" name="entity_type" value="{{ $entityType }}">
  @if ($conversation)<input type="hidden" name="conversation_id" value="{{ $conversation->id }}">@endif
  <div class="form-grid">
    @if ($entityType !== 'profile')
      @include('admin.partials.field', ['name' => 'match_name', 'field' => ['type' => 'text', 'label' => 'Updates existing record (optional)', 'help' => 'Exact name/title of an existing record to update. Leave blank to match automatically.'], 'value' => null])
    @endif
    @include('admin.partials.field', ['name' => 'excerpt', 'field' => ['type' => 'textarea', 'label' => 'Evidence excerpt', 'rows' => 3], 'value' => $excerpt])
    @foreach ($fields as $name => $field)
      @include('admin.partials.field', ['name' => $name, 'field' => $field + ['rules' => []], 'value' => null, 'prefix' => 'values'])
    @endforeach
  </div>
  <div class="form-actions"><button class="btn primary" type="submit" data-loading="Saving…">Save for review</button></div>
</form>
@endsection
