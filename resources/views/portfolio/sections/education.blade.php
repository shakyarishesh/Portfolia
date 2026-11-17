<div class="wrap">
  <p class="eyebrow reveal">{{ $section->eyebrow ?: 'Education' }}</p>
  <h2 class="title reveal">{{ $section->title }}</h2>
  <div class="cards">
    @foreach ($education as $edu)
      <article class="card reveal">
        @if ($edu->period())<span class="tag">{{ $edu->period() }}</span>@endif
        <h3>{{ $edu->degree }}@if ($edu->field), {{ $edu->field }}@endif</h3>
        <p>{{ $edu->institution }}@if ($edu->location) · {{ $edu->location }}@endif</p>
        @if ($edu->description)<p>{!! $md($edu->description) !!}</p>@endif
      </article>
    @endforeach
    @foreach ($certifications as $cert)
      <article class="card reveal">
        <span class="tag">Certification @if ($cert->issued_on)· {{ $cert->issued_on->format('M Y') }}@endif</span>
        <h3>{{ $cert->name }}</h3>
        <p>{{ $cert->issuer }}</p>
        @if ($cert->credential_url)<a class="live" href="{{ $cert->credential_url }}" target="_blank" rel="noopener">Verify ↗</a>@endif
      </article>
    @endforeach
  </div>
</div>
