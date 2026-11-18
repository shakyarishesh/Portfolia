@extends('admin.layout')
@section('title', $status === 'rejected' ? 'Rejected applications' : 'Applications')
@section('actions')
  @if ($status === 'rejected')
    <a class="btn" href="{{ route('admin.applications.index') }}">← Pending ({{ $counts['pending'] ?? 0 }})</a>
  @else
    <a class="btn" href="{{ route('admin.applications.index', ['status' => 'rejected']) }}">Rejected ({{ $counts['rejected'] ?? 0 }})</a>
  @endif
  <a class="btn" href="{{ route('apply.create') }}" target="_blank" rel="noopener">Public form ↗</a>
@endsection
@section('content')
<p class="muted small" style="margin-bottom:14px">People who used the public “Create your portfolio” form. Review what they submitted, then approve to publish it at its username.</p>
<div class="card" style="padding:0">
  @if ($applications->isEmpty())
    <div class="empty">{{ $status === 'rejected' ? 'No rejected applications.' : 'No applications waiting for review.' }}</div>
  @else
    <div class="table-wrap"><table>
      <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Template</th><th>Submitted</th><th><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>
      @foreach ($applications as $p)
        <tr>
          <td><a href="{{ route('admin.applications.show', $p) }}"><b>{{ $p->name }}</b></a></td>
          <td>/{{ $p->username }}</td>
          <td>@if ($p->profile?->email)<a href="mailto:{{ $p->profile->email }}">{{ $p->profile->email }}</a>@else<span class="muted">—</span>@endif</td>
          <td>{{ \App\Support\Templates::meta($p->template)['name'] }}</td>
          <td class="small muted" title="{{ $p->created_at }}">{{ $p->created_at?->diffForHumans() }}</td>
          <td class="actions"><a class="btn sm primary" href="{{ route('admin.applications.show', $p) }}">Review</a></td>
        </tr>
      @endforeach
      </tbody>
    </table></div>
  @endif
</div>
{{ $applications->links('admin.partials.pagination') }}
@endsection
