<section class="sec" id="education" aria-labelledby="h-education">
  <header class="sec-head"><p class="eyebrow">{{ $section->eyebrow ?: 'Education' }}</p><h2 id="h-education">{{ $section->title ?: 'Education' }}</h2></header>
  <ul class="tickets">
    @foreach ($education as $edu)
      <li data-reveal>
        @if ($edu->period())<span class="eyebrow">{{ $edu->period() }}</span>@endif
        <h3>{{ $edu->degree }}@if ($edu->field), {{ $edu->field }}@endif</h3>
        <p>{{ $edu->institution }}@if ($edu->location) · {{ $edu->location }}@endif</p>
        @if ($edu->description)<p class="muted">{!! $md($edu->description) !!}</p>@endif
      </li>
    @endforeach
    @foreach ($certifications as $cert)
      <li data-reveal>
        <span class="eyebrow">Certification @if ($cert->issued_on)· {{ $cert->issued_on->format('M Y') }}@endif</span>
        <h3>{{ $cert->name }}</h3>
        @if ($cert->issuer)<p>{{ $cert->issuer }}</p>@endif
        @if ($cert->credential_url)<a href="{{ $cert->credential_url }}" target="_blank" rel="noopener">Verify <span aria-hidden="true">↗</span><span class="sr-only">{{ $cert->name }} (opens in a new tab)</span></a>@endif
      </li>
    @endforeach
  </ul>
</section>
