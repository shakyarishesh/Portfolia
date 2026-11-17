@if (isset($history) && $history->isNotEmpty())
  <div class="card">
    <h2>Recent changes</h2>
    <ul class="history">
      @foreach ($history as $log)
        <li><span class="badge">{{ $log->event }}</span> {{ $log->created_at->diffForHumans() }} · {{ $log->user?->name ?? $log->source }}
          · {{ implode(', ', array_slice(array_keys($log->diff()), 0, 4)) }}</li>
      @endforeach
    </ul>
    <a class="small" href="{{ route('admin.audit.index', ['type' => $record->getMorphClass(), 'record' => $record->getKey()]) }}">Full history &amp; restore →</a>
  </div>
@endif
