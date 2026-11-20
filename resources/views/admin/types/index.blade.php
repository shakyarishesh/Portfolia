@extends('admin.layout')
@section('title', 'Portfolio types')
@section('actions')
  <a class="btn primary" href="{{ route('admin.types.create') }}">+ New type</a>
@endsection
@section('content')
<p class="muted" style="margin-bottom:16px">What applicants choose first on /create. A type decides the project fields (its modules), the sections a new portfolio starts with and which templates are offered. Changing a type never touches existing portfolios' content.</p>

<div class="card" style="padding:0">
  <div class="table-wrap"><table>
    <thead><tr><th>Type</th><th>Modules</th><th>Templates</th><th>Portfolios</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    @foreach ($types as $t)
      <tr>
        <td><a href="{{ route('admin.types.edit', $t) }}"><b>{{ $t->icon }} {{ $t->name }}</b></a><br><span class="small muted">{{ $t->slug }}</span></td>
        <td class="small">{{ $t->isHybrid() ? 'From the disciplines picked' : collect($t->moduleKeys())->map(fn ($m) => $modules[$m]['name'])->implode(', ') }}</td>
        <td class="small">{{ count((array) $t->templates) }}</td>
        <td><a href="{{ route('admin.portfolios.index', ['type' => $t->slug]) }}">{{ $counts[$t->slug] ?? 0 }}</a></td>
        <td>@if ($t->is_active)<span class="badge published">Active</span>@else<span class="badge draft">Off</span>@endif</td>
        <td class="actions"><a class="btn sm" href="{{ route('admin.types.edit', $t) }}">Edit</a> <a class="btn sm" href="{{ route('demo.sample', $t) }}" target="_blank" rel="noopener">Sample ↗</a></td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
</div>

<form class="card" method="post" action="{{ route('admin.types.compatibility') }}" style="margin-top:20px">
  @csrf @method('PUT')
  <h2 style="margin-top:0">Template compatibility</h2>
  <p class="small muted">Which templates each type offers (on /create and in a portfolio's settings). A portfolio's current template always stays available to it.</p>
  @error('compat')<p class="error">{{ $message }}</p>@enderror
  <div class="table-wrap"><table class="matrix">
    <thead><tr><th scope="col">Template</th>@foreach ($types as $t)<th scope="col" title="{{ $t->name }}">{{ $t->icon }} <span class="small">{{ $t->name }}</span></th>@endforeach</tr></thead>
    <tbody>
    @foreach ($templates as $key => $meta)
      <tr>
        <th scope="row">{{ $meta['name'] }}</th>
        @foreach ($types as $t)
          <td><input type="checkbox" name="compat[{{ $t->slug }}][]" value="{{ $key }}" @checked(in_array($key, (array) $t->templates, true)) aria-label="{{ $meta['name'] }} for {{ $t->name }}"></td>
        @endforeach
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="form-actions"><button class="btn primary" type="submit" data-loading="Saving…">Save compatibility</button></div>
</form>

<div class="card" style="margin-top:20px">
  <h2 style="margin-top:0">Profession-specific modules</h2>
  <p class="small muted">Modules are defined in config/portfolio.php (modules, project_details) and switched on per type above. A Hybrid portfolio gets the modules of each discipline it combines.</p>
  <ul class="stack" style="padding-left:1.1em">
    @foreach ($modules as $key => $m)<li><b>{{ $m['name'] }}</b> <span class="small muted">({{ $key }})</span> — {{ $m['description'] }}</li>@endforeach
  </ul>
</div>
@endsection
