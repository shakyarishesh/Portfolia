@extends('admin.layout')
@section('title', $resource::label())
@section('actions')
  <a class="btn primary" href="{{ route('admin.resources.create', $resource::SLUG) }}">+ Add {{ strtolower($resource::singular()) }}</a>
@endsection
@section('content')
<form class="filters" method="get" role="search">
  <input type="search" name="q" value="{{ request('q') }}" placeholder="Search {{ strtolower($resource::label()) }}…" aria-label="Search">
  <select name="status" aria-label="Status">
    <option value="">All statuses</option>
    @foreach (\App\Support\ContentStatus::LABELS as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
  </select>
  @foreach ($resource::filters() as $col => [$label, $options])
    <select name="{{ $col }}" aria-label="{{ $label }}">
      <option value="">All {{ strtolower($label) }}</option>
      @foreach ($options as $k => $l)<option value="{{ $k }}" @selected((string) request($col) === (string) $k)>{{ $l }}</option>@endforeach
    </select>
  @endforeach
  <button class="btn" type="submit">Filter</button>
  @if (request()->hasAny(['q', 'status']) || collect($resource::filters())->keys()->contains(fn ($k) => request()->filled($k)))<a class="btn" href="{{ route('admin.resources.index', $resource::SLUG) }}">Clear</a>@endif
</form>

<div class="card" style="padding:0">
  @if ($records->isEmpty())
    <div class="empty">Nothing here yet. <a href="{{ route('admin.resources.create', $resource::SLUG) }}">Add the first {{ strtolower($resource::singular()) }}</a>.</div>
  @else
    <div class="table-wrap"><table>
      <thead><tr>
        @if ($canReorder)<th><span class="sr-only">Order</span></th>@endif
        @foreach ($resource::columns() as $col => $label)<th>{{ is_string($label) ? $label : \Illuminate\Support\Str::headline($col) }}</th>@endforeach
        <th>Status</th><th><span class="sr-only">Actions</span></th>
      </tr></thead>
      <tbody>
      @foreach ($records as $record)
        <tr>
          @if ($canReorder)
            <td class="actions" style="text-align:left">
              <form class="inline" method="post" action="{{ route('admin.resources.move', [$resource::SLUG, $record->id]) }}">@csrf<input type="hidden" name="direction" value="up"><button class="icon-btn" aria-label="Move up">↑</button></form>
              <form class="inline" method="post" action="{{ route('admin.resources.move', [$resource::SLUG, $record->id]) }}">@csrf<input type="hidden" name="direction" value="down"><button class="icon-btn" aria-label="Move down">↓</button></form>
            </td>
          @endif
          @foreach ($resource::columns() as $col => $label)
            <td>
              @php $cell = is_string($label) ? $record->{$col} : $label($record); @endphp
              @if ($loop->first)<a href="{{ route('admin.resources.edit', [$resource::SLUG, $record->id]) }}"><b>{{ $cell }}</b></a>@else {{ $cell }} @endif
            </td>
          @endforeach
          <td><span class="badge {{ $record->status }}">{{ \App\Support\ContentStatus::LABELS[$record->status] ?? $record->status }}</span></td>
          <td class="actions">
            @if ($record->status !== 'published')
              <form class="inline" method="post" action="{{ route('admin.resources.status', [$resource::SLUG, $record->id]) }}">@csrf<input type="hidden" name="status" value="published"><button class="btn sm ok">Publish</button></form>
            @else
              <form class="inline" method="post" action="{{ route('admin.resources.status', [$resource::SLUG, $record->id]) }}">@csrf<input type="hidden" name="status" value="draft"><button class="btn sm">Unpublish</button></form>
            @endif
            <a class="btn sm" href="{{ route('admin.resources.edit', [$resource::SLUG, $record->id]) }}">Edit</a>
            <form class="inline" method="post" action="{{ route('admin.resources.destroy', [$resource::SLUG, $record->id]) }}" data-confirm="Delete “{{ $resource::title($record) }}”? You can restore it from the audit history.">@csrf @method('DELETE')<button class="btn sm danger">Delete</button></form>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table></div>
  @endif
</div>
@if (! $canReorder)<p class="small muted" style="margin-top:8px">Clear search and status filters to reorder.</p>@endif
{{ $records->links('admin.partials.pagination') }}
@endsection
