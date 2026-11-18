@extends('admin.layout')
@section('title', 'Portfolios')
@section('actions')
  <a class="btn primary" href="{{ route('admin.portfolios.create') }}">+ New portfolio</a>
@endsection
@section('content')
<form class="filters" method="get" role="search">
  <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, username, owner…" aria-label="Search portfolios">
  <select name="template" aria-label="Template">
    <option value="">All templates</option>
    @foreach ($templates as $k => $t)<option value="{{ $k }}" @selected(request('template') === $k)>{{ $t['name'] }}</option>@endforeach
  </select>
  <select name="status" aria-label="Status">
    <option value="">All statuses</option>
    @foreach (\App\Models\Portfolio::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
  </select>
  <button class="btn" type="submit">Filter</button>
  @if (request()->hasAny(['q', 'template', 'status']))<a class="btn" href="{{ route('admin.portfolios.index') }}">Clear</a>@endif
</form>

<div class="card" style="padding:0">
  @if ($portfolios->isEmpty())
    <div class="empty">No portfolios found. <a href="{{ route('admin.portfolios.create') }}">Create one</a>.</div>
  @else
    <div class="table-wrap"><table>
      <thead><tr><th>Name</th><th>Username</th><th>Owner</th><th>Template</th><th>Status</th><th>Updated</th><th><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>
      @foreach ($portfolios as $p)
        <tr>
          <td><a href="{{ route('admin.portfolios.edit', $p) }}"><b>{{ $p->name }}</b></a>@if ((int) $currentId === $p->id) <span class="badge info">editing</span>@endif</td>
          <td><a href="{{ $p->url() }}" target="_blank" rel="noopener">/{{ $p->username }} ↗</a></td>
          <td>@if ($p->user){{ $p->user->name }}<br><span class="small muted">{{ $p->user->email }}</span>@else<span class="muted">—</span>@endif</td>
          <td>{{ $templates[$p->template]['name'] ?? $p->template }}@if ($p->draft_template)<br><span class="badge pending">draft: {{ $templates[$p->draft_template]['name'] ?? $p->draft_template }}</span>@endif</td>
          <td><span class="badge {{ $p->status }}">{{ \App\Models\Portfolio::STATUSES[$p->status] ?? $p->status }}</span></td>
          <td class="small muted" title="{{ $p->updated_at }}">{{ $p->updated_at?->diffForHumans() }}</td>
          <td class="actions">
            <a class="btn sm" href="{{ route('admin.portfolios.edit', $p) }}">Edit</a>
            <a class="btn sm" href="{{ route('admin.portfolios.preview', $p) }}" target="_blank" rel="noopener">Preview ↗</a>
            @if ((int) $currentId !== $p->id)
              <form class="inline" method="post" action="{{ route('admin.portfolios.switch', $p) }}">@csrf<button class="btn sm">Switch to</button></form>
            @endif
            @if ($p->status !== 'published')
              <form class="inline" method="post" action="{{ route('admin.portfolios.status', $p) }}">@csrf<input type="hidden" name="status" value="published"><button class="btn sm ok">Publish</button></form>
            @else
              <form class="inline" method="post" action="{{ route('admin.portfolios.status', $p) }}">@csrf<input type="hidden" name="status" value="draft"><button class="btn sm">Unpublish</button></form>
            @endif
            @if ($p->status !== 'archived')
              <form class="inline" method="post" action="{{ route('admin.portfolios.status', $p) }}" data-confirm="Archive “{{ $p->name }}”? It disappears from the public site; content is kept.">@csrf<input type="hidden" name="status" value="archived"><button class="btn sm">Archive</button></form>
            @endif
            <a class="btn sm danger" href="{{ route('admin.portfolios.edit', $p) }}#delete">Delete…</a>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table></div>
  @endif
</div>
{{ $portfolios->links('admin.partials.pagination') }}
@endsection
