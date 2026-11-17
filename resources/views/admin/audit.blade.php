@extends('admin.layout')
@section('title', 'Audit history')
@section('content')
<form class="filters" method="get">
  <select name="type" aria-label="Content type">
    <option value="">All content</option>
    @foreach ($types as $t)<option value="{{ $t }}" @selected(request('type') === $t)>{{ str_replace('_', ' ', $t) }}</option>@endforeach
  </select>
  <select name="source" aria-label="Source">
    <option value="">All sources</option>
    @foreach (['admin', 'import', 'restore', 'seed', 'console'] as $s)<option value="{{ $s }}" @selected(request('source') === $s)>{{ $s }}</option>@endforeach
  </select>
  @if (request('record'))<input type="hidden" name="record" value="{{ request('record') }}"><span class="badge info">record #{{ request('record') }}</span>@endif
  <button class="btn" type="submit">Filter</button>
  @if (request()->hasAny(['type', 'source', 'record']))<a class="btn" href="{{ route('admin.audit.index') }}">Clear</a>@endif
</form>
<div class="stack">
@forelse ($logs as $log)
  <div class="card">
    <div class="topbar" style="margin-bottom:8px">
      <div>
        <span class="badge">{{ $log->event }}</span>
        <b>{{ str_replace('_', ' ', $log->auditable_type) }} #{{ $log->auditable_id }}</b>
        <span class="small muted">· {{ $log->source }}@if ($log->proposed_change_id) (proposal #{{ $log->proposed_change_id }})@endif · {{ $log->user?->name ?? 'system' }} · {{ $log->created_at->format('j M Y H:i') }}</span>
      </div>
      <div class="actions">
        @if ($log->restored_at)
          <span class="badge">reverted {{ $log->restored_at->diffForHumans() }}</span>
        @else
          <form class="inline" method="post" action="{{ route('admin.audit.restore', $log) }}" data-confirm="{{ ['updated' => 'Put back the previous values?', 'deleted' => 'Restore the deleted record?', 'created' => 'Delete the record this created?'][$log->event] ?? 'Revert?' }}">
            @csrf <button class="btn sm" type="submit">{{ ['updated' => 'Restore previous values', 'deleted' => 'Restore record', 'created' => 'Undo creation'][$log->event] ?? 'Revert' }}</button>
          </form>
        @endif
      </div>
    </div>
    <details @if ($loop->first && $log->event === 'updated') open @endif>
      <summary class="small">{{ count($log->diff()) }} field(s)</summary>
      <div class="table-wrap"><table class="diff">
        <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
        <tbody>@foreach ($log->diff() as $field => [$old, $new])<tr><td><b>{{ $field }}</b></td><td class="old">@include('admin.partials.value', ['value' => $old])</td><td class="new">@include('admin.partials.value', ['value' => $new])</td></tr>@endforeach</tbody>
      </table></div>
    </details>
  </div>
@empty
  <div class="card empty">No changes recorded yet.</div>
@endforelse
</div>
{{ $logs->links('admin.partials.pagination') }}
@endsection
