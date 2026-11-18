@extends('admin.layout')
@section('title', 'Dashboard')
@section('actions')
  @can('admin')<a class="btn" href="{{ route('admin.imports.index') }}">Import a conversation</a>@endcan
  <a class="btn primary" href="{{ route('home') }}" target="_blank" rel="noopener">View site ↗</a>
@endsection
@section('content')
<div class="grid cols-4">
  @can('admin')
    <div class="card stat"><a href="{{ route('admin.proposals.index') }}"><b>{{ $pendingProposals }}</b><span>proposed changes to review</span></a></div>
  @else
    @php $mine = \App\Support\CurrentPortfolio::get(); @endphp
    <div class="card stat"><b>{{ \App\Models\Portfolio::STATUSES[$mine?->status] ?? '—' }}</b><span>{{ $mine?->isPublic() ? 'your portfolio is live' : ($mine?->status === 'pending' ? 'an admin reviews it before it goes live' : 'not visible to the public') }}</span></div>
  @endcan
  <div class="card stat"><b>{{ $needsReview }}</b><span>items marked “needs review” (hidden from the site)</span></div>
  <div class="card stat"><a href="{{ route('admin.messages.index', ['unread' => 1]) }}"><b>{{ $unreadMessages }}</b><span>unread messages</span></a></div>
  <div class="card stat"><b>{{ $completeness['percent'] }}%</b><span>profile completeness</span></div>
</div>

<div class="grid cols-4" style="margin-top:16px">
  <div class="card stat"><a href="{{ route('admin.analytics') }}"><b>{{ number_format($traffic['views']) }}</b><span>page views · 30 days</span></a></div>
  <div class="card stat"><a href="{{ route('admin.analytics') }}"><b>{{ number_format($traffic['downloads']) }}</b><span>CV downloads · 30 days</span></a></div>
  <div class="card stat"><a href="{{ route('admin.messages.index') }}"><b>{{ number_format($traffic['messages']) }}</b><span>contact messages · 30 days</span></a></div>
  <div class="card stat"><a href="{{ route('admin.resources.index', 'posts') }}"><b>{{ $traffic['articles'] }}</b><span>published articles</span></a></div>
</div>
@if ($traffic['top']->isNotEmpty())
  <div class="card" style="margin-top:16px">
    <h2>Most popular content · 30 days</h2>
    @foreach ($traffic['top'] as $row)
      <p class="small"><span class="badge">{{ $row->kind }}</span> <a href="{{ $row->kind === 'project' ? route('projects.show', $row->subject) : route('blog.show', $row->subject) }}" target="_blank" rel="noopener">{{ $row->subject }}</a> <span class="muted">· {{ $row->n }} views</span></p>
    @endforeach
  </div>
@endif

<div class="split" style="margin-top:16px">
  <div class="stack">
    <div class="card">
      <h2>Content</h2>
      <div class="table-wrap"><table>
        <thead><tr><th>Type</th><th>Published</th><th>Draft</th><th>Needs review</th><th></th></tr></thead>
        <tbody>
        @foreach ($stats as $row)
          @php $r = $row['resource']; $c = $row['counts']; @endphp
          <tr>
            <td><a href="{{ route('admin.resources.index', $r::SLUG) }}">{{ $r::LABEL }}</a></td>
            <td>{{ $c['published'] ?? 0 }}</td>
            <td>{{ $c['draft'] ?? 0 }}</td>
            <td>@if ($c['needs_review'] ?? 0)<a href="{{ route('admin.resources.index', [$r::SLUG, 'status' => 'needs_review']) }}"><span class="badge needs_review">{{ $c['needs_review'] }}</span></a>@else 0 @endif</td>
            <td class="actions"><a class="btn sm" href="{{ route('admin.resources.create', $r::SLUG) }}">+ Add</a></td>
          </tr>
        @endforeach
        </tbody>
      </table></div>
    </div>
    <div class="card">
      <h2>Recent activity</h2>
      @forelse ($recent as $log)
        <p class="small">
          <span class="badge">{{ $log->event }}</span> {{ str_replace('_', ' ', $log->auditable_type) }} #{{ $log->auditable_id }}
          <span class="muted">· {{ $log->source }} · {{ $log->user?->name ?? 'system' }} · {{ $log->created_at->diffForHumans() }}</span>
        </p>
      @empty
        <p class="muted">No changes yet.</p>
      @endforelse
      <a class="small" href="{{ route('admin.audit.index') }}">Audit history →</a>
    </div>
  </div>
  <div class="card">
    <h2>Profile completeness</h2>
    <div class="progress" role="progressbar" aria-valuenow="{{ $completeness['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Profile completeness"><span style="width: {{ $completeness['percent'] }}%"></span></div>
    <ul class="checklist" style="margin-top:14px">
      @foreach ($completeness['items'] as $item)
        <li class="{{ $item['done'] ? 'done' : '' }}">@if ($item['done']){{ $item['label'] }}@else<a href="{{ $item['link'] }}">{{ $item['label'] }}</a>@endif</li>
      @endforeach
    </ul>
  </div>
</div>
@endsection
