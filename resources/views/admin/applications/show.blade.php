@extends('admin.layout')
@section('title', 'Application: '.$portfolio->name)
@section('crumbs')<a href="{{ route('admin.applications.index') }}">Applications</a>@endsection
@php
  $link = fn (?string $url) => $url ? new \Illuminate\Support\HtmlString('<a href="'.e($url).'" target="_blank" rel="noopener noreferrer">'.e($url).'</a>') : '—';
  $tech = fn ($m) => $m->technologies->pluck('name')->implode(', ') ?: '—';
@endphp
@section('actions')
  <a class="btn" href="{{ route('admin.portfolios.preview', $portfolio) }}" target="_blank" rel="noopener">Preview the site ↗</a>
  @if (in_array($portfolio->status, ['pending', 'rejected'], true))
    <form class="inline" method="post" action="{{ route('admin.applications.approve', $portfolio) }}">@csrf<button class="btn primary">Approve &amp; publish</button></form>
  @endif
  @if ($portfolio->status === 'pending')
    <form class="inline" method="post" action="{{ route('admin.applications.reject', $portfolio) }}" data-confirm="Reject “{{ $portfolio->name }}”? It stays hidden; the data is kept.">@csrf<button class="btn danger">Reject</button></form>
  @endif
@endsection
@section('content')
<div class="card">
  <h2>Address &amp; template</h2>
  <div class="table-wrap"><table>
    <tr><th>Status</th><td><span class="badge {{ $portfolio->status }}">{{ \App\Models\Portfolio::STATUSES[$portfolio->status] ?? $portfolio->status }}</span></td></tr>
    <tr><th>Address</th><td>{{ $portfolio->url() }}</td></tr>
    <tr><th>Template</th><td>{{ \App\Support\Templates::meta($portfolio->template)['name'] }}</td></tr>
    <tr><th>Submitted</th><td>{{ $portfolio->created_at?->format('j M Y, H:i') }}</td></tr>
  </table></div>
</div>

<div class="card">
  <h2>Profile</h2>
  @if ($profile->avatar)<p><img class="avatar-preview" src="{{ $profile->avatar->url() }}" alt="Uploaded photo of {{ $profile->full_name }}"></p>@endif
  <div class="table-wrap"><table>
    <tr><th>Full name</th><td>{{ $profile->full_name }}</td></tr>
    <tr><th>Headline</th><td>{{ $profile->headline ?: '—' }}</td></tr>
    <tr><th>Email</th><td><a href="mailto:{{ $profile->email }}">{{ $profile->email }}</a></td></tr>
    <tr><th>Phone</th><td>{{ $profile->phone ?: '—' }}@if ($profile->phone) <span class="small muted">({{ $profile->show_phone ? 'shown publicly' : 'hidden' }})</span>@endif</td></tr>
    <tr><th>Location</th><td>{{ $profile->location ?: '—' }}</td></tr>
    <tr><th>Open to work</th><td>{{ $profile->open_to_work ? 'Yes' : 'No' }}</td></tr>
    <tr><th>CV</th><td>@if ($profile->resume)<a href="{{ $portfolio->url('resume') }}">{{ $profile->resume->original_name }}</a> <span class="small muted">({{ number_format($profile->resume->size / 1024) }} KB)</span>@else—@endif</td></tr>
    <tr><th>Intro</th><td><div class="excerpt">{{ $profile->intro ?: '—' }}</div></td></tr>
    <tr><th>About</th><td><div class="excerpt">{{ $profile->about ?: '—' }}</div></td></tr>
  </table></div>
</div>

<div class="card">
  <h2>Experience ({{ $experiences->count() }})</h2>
  @forelse ($experiences as $e)
    <div class="stack" style="margin-bottom:16px">
      <h3>{{ $e->role }} — {{ $e->company }} <span class="small muted">· {{ \App\Models\Experience::TYPES[$e->employment_type] ?? $e->employment_type }} · {{ $e->period() }}{{ $e->location ? ' · '.$e->location : '' }}</span></h3>
      <p class="small">Website: {{ $link($e->company_url) }} · Tech: {{ $tech($e) }}</p>
      @if ($e->summary)<div class="excerpt">{{ $e->summary }}</div>@endif
      @if ($e->highlights)<ul style="margin-left:18px">@foreach ($e->highlights as $h)<li>{{ $h }}</li>@endforeach</ul>@endif
    </div>
  @empty<p class="muted">None.</p>@endforelse
</div>

<div class="card">
  <h2>Projects ({{ $projects->count() }})</h2>
  @forelse ($projects as $pr)
    <div class="stack" style="margin-bottom:16px">
      <h3>{{ $pr->name }}@if ($pr->is_featured) <span class="badge info">featured</span>@endif <span class="small muted">{{ collect([$pr->category, $pr->role, $pr->period()])->filter()->implode(' · ') }}</span></h3>
      @if ($pr->tagline)<p>{{ $pr->tagline }}</p>@endif
      <p class="small">Live: {{ $link($pr->live_url) }} · Repo: {{ $link($pr->repo_url) }} · Tech: {{ $tech($pr) }}</p>
      @if ($pr->description)<div class="excerpt">{{ $pr->description }}</div>@endif
    </div>
  @empty<p class="muted">None.</p>@endforelse
</div>

@php
  $tables = [
      'Skills' => [['Category', 'Skills'], $skillCategories->map(fn ($c) => [$c->name, $c->skills->pluck('name')->implode(', ')])],
      'Education' => [['Institution', 'Degree', 'Field', 'Location', 'Years'], $education->map(fn ($e) => [$e->institution, $e->degree, $e->field, $e->location, collect([$e->start_year, $e->end_year])->filter()->implode('–')])],
      'Certifications' => [['Name', 'Issuer', 'Issued', 'Credential'], $certifications->map(fn ($c) => [$c->name, $c->issuer, $c->issued_on?->format('j M Y'), $link($c->credential_url)])],
      'Professional links' => [['Platform', 'URL'], $links->map(fn ($l) => [$l->label, $link($l->url)])],
      'Areas of expertise' => [['Area', 'Description'], $expertise->map(fn ($x) => [$x->title, $x->description])],
      'Testimonials' => [['Name', 'Position', 'Company', 'Quote'], $testimonials->map(fn ($t) => [$t->name, $t->position, $t->company, $t->quote])],
  ];
@endphp
@foreach ($tables as $title => [$head, $rows])
  <div class="card">
    <h2>{{ $title }} ({{ $rows->count() }})</h2>
    @if ($rows->isEmpty())
      <p class="muted">None.</p>
    @else
      <div class="table-wrap"><table>
        <thead><tr>@foreach ($head as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
        <tbody>@foreach ($rows as $row)<tr>@foreach ($row as $cell)<td>{{ filled($cell) ? $cell : '—' }}</td>@endforeach</tr>@endforeach</tbody>
      </table></div>
    @endif
  </div>
@endforeach
@endsection
