@extends('admin.layout')
@section('title', 'Review proposed changes')
@section('actions')
  @if ($duplicateCount)
    <form class="inline" method="post" action="{{ route('admin.proposals.dismiss-duplicates') }}" data-confirm="Dismiss {{ $duplicateCount }} duplicate proposal(s)? They add nothing new.">
      @csrf <input type="hidden" name="import" value="{{ request('import') }}">
      <button class="btn" type="submit">Dismiss {{ $duplicateCount }} duplicates</button>
    </form>
  @endif
  <a class="btn" href="{{ route('admin.imports.index') }}">Import more</a>
@endsection
@section('content')
<form class="filters" method="get">
  @if (request('import'))<input type="hidden" name="import" value="{{ request('import') }}">@endif
  <select name="status" aria-label="Status">
    @foreach (['pending' => 'Pending', 'applied' => 'Applied', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach
  </select>
  <select name="classification" aria-label="Classification">
    <option value="">All kinds</option>
    @foreach (\App\Models\ProposedChange::CLASSIFICATIONS as $k => $l)<option value="{{ $k }}" @selected(request('classification') === $k)>{{ $l }}</option>@endforeach
  </select>
  <select name="entity" aria-label="Content type">
    <option value="">All content</option>
    @foreach ($entityTypes as $k => $l)<option value="{{ $k }}" @selected(request('entity') === $k)>{{ $l }}</option>@endforeach
  </select>
  <button class="btn" type="submit">Filter</button>
  @if (request('import'))<a class="btn" href="{{ route('admin.proposals.index') }}">All imports</a>@endif
</form>

<p class="small muted" style="margin-bottom:12px">Contradictions first. Approving applies the change through normal validation and records it in the audit history; new records start as drafts unless you choose otherwise.</p>

<div class="stack">
@forelse ($proposals as $p)
  <article class="card proposal {{ $p->classification }}">
    <header>
      <div>
        <span class="badge {{ $p->classification }}">{{ \App\Models\ProposedChange::CLASSIFICATIONS[$p->classification] }}</span>
        <b>{{ $p->action === 'create' ? 'New' : 'Update' }} {{ strtolower($entityTypes[$p->entity_type] ?? $p->entity_type) }}</b>
        @if ($p->action === 'update' && $p->target_id && $p->entity_type !== 'profile')
          @php $res = \App\Admin\Resources::byKey($p->entity_type); @endphp
          @if ($res)<a class="small" href="{{ route('admin.resources.edit', [$res::SLUG, $p->target_id]) }}">#{{ $p->target_id }}</a>@endif
        @endif
      </div>
      <div class="small muted">
        {{ $p->extractor === 'llm' ? 'AI' : ucfirst($p->extractor) }}@if ($p->confidence !== null) · confidence {{ number_format($p->confidence * 100) }}%@endif
        @if ($p->conversation) · <a href="{{ route('admin.imports.conversation', [$p->conversation_import_id, $p->imported_conversation_id]) }}">{{ \Illuminate\Support\Str::limit($p->conversation->title, 40) }}</a>@endif
        @if (! $p->isPending()) · <span class="badge {{ $p->status }}">{{ $p->status }}</span> by {{ $p->reviewer?->name }} {{ $p->reviewed_at?->diffForHumans() }}@endif
      </div>
    </header>

    <div class="table-wrap"><table class="diff">
      <thead><tr><th>Field</th>@if ($p->current !== null)<th>Current</th>@endif<th>Proposed</th></tr></thead>
      <tbody>
      @foreach ($p->proposed as $field => $value)
        <tr>
          <td><b>{{ str_replace('_', ' ', $field) }}</b></td>
          @php $differs = $p->current === null || ($p->current[$field] ?? null) != $value; @endphp
          @if ($p->current !== null)<td class="{{ $differs && filled($p->current[$field] ?? null) ? 'old' : '' }}">@include('admin.partials.value', ['value' => $p->current[$field] ?? null])</td>@endif
          <td class="{{ $differs ? 'new' : '' }}">@include('admin.partials.value', ['value' => $value])</td>
        </tr>
      @endforeach
      </tbody>
    </table></div>

    @if ($p->source_excerpt)<div class="excerpt">{{ $p->source_excerpt }}</div>@endif
    @if ($p->rationale)<p class="small muted">{{ $p->rationale }}</p>@endif

    @if ($p->isPending())
      <footer>
        <form class="inline filters" style="margin:0" method="post" action="{{ route('admin.proposals.approve', $p) }}">
          @csrf
          @if ($p->action === 'create')
            <select name="new_status" aria-label="Status for the new record">
              <option value="draft">as Draft</option><option value="needs_review">as Needs review</option><option value="published">and Publish</option>
            </select>
          @endif
          @if ($p->classification === 'contradiction')
            <label class="check small"><input type="checkbox" name="confirm_overwrite" value="1" required> Overwrite existing values</label>
          @endif
          <button class="btn primary sm" type="submit" data-loading="Applying…">Approve</button>
        </form>
        <a class="btn sm" href="{{ route('admin.proposals.edit', $p) }}">Edit</a>
        <form class="inline" method="post" action="{{ route('admin.proposals.reject', $p) }}">@csrf<button class="btn sm danger" type="submit">Reject</button></form>
      </footer>
    @endif
  </article>
@empty
  <div class="card empty">No {{ $status === 'all' ? '' : $status }} proposals. <a href="{{ route('admin.imports.index') }}">Import a conversation</a> to find updates.</div>
@endforelse
</div>
{{ $proposals->links('admin.partials.pagination') }}
@endsection
