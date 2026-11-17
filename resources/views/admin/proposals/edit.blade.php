@extends('admin.layout')
@section('title', 'Edit proposal')
@section('crumbs')<a href="{{ route('admin.proposals.index', ['import' => $proposal->conversation_import_id]) }}">Review changes</a>@endsection
@section('content')
<div class="split">
  <form class="card" method="post" action="{{ route('admin.proposals.update', $proposal) }}">
    @csrf @method('PUT')
    <p class="small muted" style="margin-bottom:14px">Edit the proposed values. Blank fields are left out of the change. Saving re-compares with your current records; you approve on the next screen.</p>
    <div class="form-grid">
      @foreach ($fields as $name => $field)
        @include('admin.partials.field', ['name' => $name, 'field' => $field + ['rules' => []], 'value' => $proposal->proposed[$name] ?? null, 'prefix' => 'values'])
      @endforeach
    </div>
    <div class="form-actions"><button class="btn primary" type="submit" data-loading="Saving…">Save proposal</button></div>
  </form>
  <div class="card stack">
    <h2>Evidence</h2>
    @if ($proposal->source_excerpt)<div class="excerpt">{{ $proposal->source_excerpt }}</div>@else<p class="muted small">No excerpt.</p>@endif
    @if ($proposal->rationale)<p class="small muted">{{ $proposal->rationale }}</p>@endif
    @if ($proposal->current)
      <h3>Current values</h3>
      <table class="diff"><tbody>@foreach ($proposal->current as $k => $v)<tr><td><b>{{ str_replace('_', ' ', $k) }}</b></td><td>@include('admin.partials.value', ['value' => $v])</td></tr>@endforeach</tbody></table>
    @endif
  </div>
</div>
@endsection
