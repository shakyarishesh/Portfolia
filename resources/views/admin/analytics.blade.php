@extends('admin.layout')
@section('title', 'Analytics')
@section('actions')
  <form method="get" class="inline">
    <label class="sr-only" for="days">Date range</label>
    <select id="days" name="days" onchange="this.form.submit()">
      @foreach ($ranges as $d => $label)<option value="{{ $d }}" @selected($d === $days)>{{ $label }}</option>@endforeach
    </select>
    <noscript><button class="btn sm">Apply</button></noscript>
  </form>
@endsection
@section('content')
<div class="grid cols-5">
  @foreach ($cards as [$label, $value])
    <div class="card stat"><b>{{ is_int($value) ? number_format($value) : $value }}</b><span>{{ $label }}</span></div>
  @endforeach
</div>

<div class="card" style="margin-top:16px">
  <h2>Views per day <span class="muted small">since {{ $from->format('j M Y') }}</span></h2>
  @if ($series->sum('n') === 0)
    <div class="empty">No visits recorded in this range yet.</div>
  @else
    <div class="bars" role="img" aria-label="Page views per day">
      @foreach ($series as $point)
        <span style="--h: {{ round($point['n'] / $max * 100) }}%" title="{{ \Illuminate\Support\Carbon::parse($point['date'])->format('D j M') }}: {{ $point['n'] }}"></span>
      @endforeach
    </div>
  @endif
</div>

<div class="grid cols-2" style="margin-top:16px">
  @foreach ([['Most viewed projects', $projects, $projectNames], ['Popular articles', $posts, $postNames], ['Pages', $pages, collect()], ['Traffic sources', $referrers, collect()]] as [$title, $rows, $names])
    <div class="card">
      <h2>{{ $title }}</h2>
      @if ($rows->isEmpty())
        <p class="muted small">Nothing yet.</p>
      @else
        @php $top = max(1, $rows->max()); @endphp
        <ul class="meter-list">
          @foreach ($rows as $key => $n)
            <li><span>{{ $names[$key] ?? $key }}</span><b>{{ $n }}</b><i style="--w: {{ round($n / $top * 100) }}%"></i></li>
          @endforeach
        </ul>
      @endif
    </div>
  @endforeach
</div>
<p class="small muted" style="margin-top:16px">Privacy: no IP addresses, cookies or user agents are stored. Each visitor gets a salted hash that changes every day, so “unique visitors” counts visitor-days. Bots and Do-Not-Track browsers are not counted.</p>
@endsection
