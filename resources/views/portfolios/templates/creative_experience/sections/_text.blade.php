{{-- Creative Experience: a free-text section (Teaching philosophy, Publications, custom …). --}}
<section class="sec" id="{{ $section->key }}" aria-labelledby="h-{{ $section->key }}">
  <header class="sec-head">@if ($section->eyebrow)<p class="eyebrow">{{ $section->eyebrow }}</p>@endif<h2 id="h-{{ $section->key }}">{{ $section->title }}</h2></header>
  <div data-reveal>@include('portfolios.partials.text-section', ['key' => $section->key])</div>
</section>
