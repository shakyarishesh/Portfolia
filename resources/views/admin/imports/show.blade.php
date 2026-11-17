@extends('admin.layout')
@section('title', $import->original_name ?: 'Pasted text')
@section('crumbs')<a href="{{ route('admin.imports.index') }}">Imports</a>@endsection
@section('actions')
  <a class="btn primary" href="{{ route('admin.proposals.index', ['import' => $import->id]) }}">Review {{ $proposalCounts['pending'] ?? 0 }} pending</a>
@endsection
@section('content')
<div class="grid cols-4">
  <div class="card stat"><b>{{ $import->conversation_count }}</b><span>conversations</span></div>
  <div class="card stat"><b>{{ $proposalCounts['pending'] ?? 0 }}</b><span>pending proposals</span></div>
  <div class="card stat"><b>{{ $proposalCounts['applied'] ?? 0 }}</b><span>applied</span></div>
  <div class="card stat"><b><span class="badge {{ $import->status }}">{{ str_replace('_', ' ', $import->status) }}</span></b><span>ran: {{ implode(', ', $import->extractors ?? []) ?: '—' }}</span></div>
</div>
@if (in_array($import->status, ['ai_queued', 'ai_running'], true))
  <div class="alert info" style="margin-top:16px">AI extraction is {{ $import->status === 'ai_queued' ? 'queued' : 'running' }}. It needs a queue worker (<code>php artisan queue:work</code>). Refresh this page to see new proposals.</div>
@endif
@if ($import->error)
  <div class="alert warn" style="margin-top:16px; white-space:pre-line"><b>Some conversations could not be processed:</b>
{{ $import->error }}</div>
@endif

<form method="post" action="{{ route('admin.imports.ai', $import) }}" class="card" style="margin-top:16px; padding:0">
  @csrf
  <div class="topbar" style="padding:16px 16px 0; margin:0">
    <h2 style="margin:0">Conversations</h2>
    @if ($aiConfigured)
      <div class="actions">
        <button class="btn sm" type="button" data-select-all=".ai-pick" data-limit="{{ $aiLimit }}">Select first {{ $aiLimit }}</button>
        <button class="btn primary sm" type="submit" data-loading="Queuing…">Run AI extraction on selected</button>
      </div>
    @endif
  </div>
  <div class="table-wrap"><table>
    <thead><tr>@if ($aiConfigured)<th><span class="sr-only">Select</span></th>@endif<th>Title</th><th>Date</th><th>Messages</th><th>Pending</th></tr></thead>
    <tbody>
    @foreach ($conversations as $c)
      <tr>
        @if ($aiConfigured)<td><input class="ai-pick" type="checkbox" name="conversations[]" value="{{ $c->id }}" aria-label="Select {{ $c->title }}"></td>@endif
        <td><a href="{{ route('admin.imports.conversation', [$import, $c]) }}">{{ $c->title }}</a></td>
        <td class="small">{{ $c->started_at?->format('j M Y') ?? '—' }}</td>
        <td>{{ $c->message_count }}</td>
        <td>{{ $c->pending_count ?: '' }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
</form>
{{ $conversations->links('admin.partials.pagination') }}
@endsection
