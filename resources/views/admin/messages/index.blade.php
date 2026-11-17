@extends('admin.layout')
@section('title', request('box') === 'archived' ? 'Archived messages' : 'Messages')
@section('actions')
  @if (request('box') === 'archived')
    <a class="btn" href="{{ route('admin.messages.index') }}">← Inbox</a>
  @else
    <a class="btn" href="{{ route('admin.messages.index', ['box' => 'archived']) }}">Archived</a>
  @endif
@endsection
@section('content')
<form class="filters" method="get" role="search">
  @if (request('box'))<input type="hidden" name="box" value="{{ request('box') }}">@endif
  <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, email, subject or text…" aria-label="Search messages">
  <label class="check"><input type="checkbox" name="unread" value="1" @checked(request('unread'))> Unread only</label>
  <button class="btn" type="submit">Filter</button>
</form>
<div class="card" style="padding:0">
  @if ($messages->isEmpty())
    <div class="empty">No messages{{ request()->hasAny(['q', 'unread']) ? ' match these filters' : ' yet' }}.</div>
  @else
    <div class="table-wrap"><table>
      <thead><tr><th>From</th><th>Subject</th><th>Status</th><th>Received</th><th></th></tr></thead>
      <tbody>
      @foreach ($messages as $m)
        <tr>
          <td><a href="{{ route('admin.messages.show', $m) }}"><b>{{ $m->name }}</b></a><br><span class="small muted">{{ $m->email }}</span></td>
          <td>{{ $m->subject ?: \Illuminate\Support\Str::limit($m->message, 60) }}</td>
          <td>
            @if (! $m->read_at)<span class="badge pending">new</span>@elseif ($m->replied_at)<span class="badge published">replied</span>@else<span class="badge">read</span>@endif
          </td>
          <td class="small">{{ $m->created_at->diffForHumans() }}</td>
          <td class="actions">
            <form class="inline" method="post" action="{{ route('admin.messages.state', $m) }}">@csrf<input type="hidden" name="field" value="archived_at"><input type="hidden" name="on" value="{{ $m->archived_at ? 0 : 1 }}"><button class="btn sm">{{ $m->archived_at ? 'Unarchive' : 'Archive' }}</button></form>
            <form class="inline" method="post" action="{{ route('admin.messages.destroy', $m) }}" data-confirm="Delete this message?">@csrf @method('DELETE')<button class="btn sm danger">Delete</button></form>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table></div>
  @endif
</div>
{{ $messages->links('admin.partials.pagination') }}
@endsection
