@extends('admin.layout')
@section('title', $conversation->title)
@section('crumbs')<a href="{{ route('admin.imports.index') }}">Imports</a> / <a href="{{ route('admin.imports.show', $import) }}">{{ $import->original_name ?: 'Pasted text' }}</a>@endsection
@section('content')
<div class="split">
  <div class="card">
    @if ($conversation->transcript['truncated'] ?? false)
      <div class="alert warn small">This conversation was longer than the storage limit; later messages were not imported.</div>
    @endif
    @foreach ($conversation->messages() as $m)
      <div class="msg {{ $m['role'] }}"><span class="who">{{ $m['role'] === 'human' ? 'You' : 'Assistant' }}</span>{{ \Illuminate\Support\Str::limit($m['text'], 6000) }}</div>
    @endforeach
  </div>
  <div class="stack">
    <form class="card stack" method="get" action="{{ route('admin.proposals.create') }}">
      <h2>Propose a change</h2>
      <p class="small muted">Copy a sentence where you describe your work, then choose what it updates. You'll fill in the fields next.</p>
      <input type="hidden" name="conversation" value="{{ $conversation->id }}">
      <div class="field"><label for="excerpt">Excerpt (evidence)</label><textarea id="excerpt" name="excerpt" rows="4" placeholder="Paste the supporting quote"></textarea></div>
      <div class="field"><label for="entity_type">Updates</label>
        <select id="entity_type" name="entity_type">
          <option value="profile">Profile</option>
          @foreach (\App\Admin\Resources::importable() as $r)<option value="{{ $r::KEY }}">{{ $r::SINGULAR }}</option>@endforeach
        </select></div>
      <button class="btn primary" type="submit">Continue</button>
    </form>
    <div class="card">
      <h2>Proposals from this conversation</h2>
      @forelse ($proposals as $p)
        <p class="small"><span class="badge {{ $p->status }}">{{ $p->status }}</span> <span class="badge {{ $p->classification }}">{{ \App\Models\ProposedChange::CLASSIFICATIONS[$p->classification] }}</span> {{ str_replace('_', ' ', $p->entity_type) }}: {{ \Illuminate\Support\Str::limit(collect($p->proposed)->first(fn ($v) => is_string($v)) ?? '', 60) }}</p>
      @empty
        <p class="small muted">None yet.</p>
      @endforelse
    </div>
  </div>
</div>
@endsection
