{{-- Classic: a free-text section (Teaching philosophy, Publications, custom …). --}}
<div class="wrap">
  @if ($section->eyebrow)<p class="eyebrow reveal">{{ $section->eyebrow }}</p>@endif
  <h2 class="title reveal">{{ $section->title }}</h2>
  <div class="card reveal">@include('portfolios.partials.text-section', ['key' => $section->key])</div>
</div>
