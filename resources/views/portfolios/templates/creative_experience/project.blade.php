@extends('portfolios.templates.creative_experience.layout')

@php
    use Illuminate\Support\Str;
    use App\Support\Markdown;
    $md = fn (?string $text) => Str::inlineMarkdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    $nav = [[route('home'), 'Home'], [route('home').'#projects', 'Work'], [route('home').'#contact', 'Contact']];
    $meta = [
        'title' => "{$project->name} — {$profile->full_name}",
        'description' => $project->meta_description ?: ($project->tagline ?: Str::limit((string) $project->description, 155)),
        'image' => $project->thumbnail?->url() ?? $profile->avatarSrc(),
        'ogType' => 'article',
        'canonical' => route('projects.show', ['project' => $project]),
    ];
    // Case-study chapters, numbered in the order they appear.
    $chapters = array_filter([
        'overview' => $project->description ? 'Overview' : null,
        'problem' => $project->problem ? $portfolio->projectLabel('problem', 'The problem') : null,
        'built' => $project->features ? 'What I built' : null,
        'architecture' => ($project->architecture || $project->diagram) ? 'Architecture' : null,
        'challenges' => $project->challenges ? $portfolio->projectLabel('challenges', 'Challenges & solutions') : null,
        'highlights' => $highlights->isNotEmpty() ? 'Engineering highlights' : null,
        'screens' => $project->screenshots->isNotEmpty() ? ($portfolio->hasModule('technical') ? 'Screens' : 'Gallery') : null,
    ]);
    $num = fn (string $key) => sprintf('%02d', array_search($key, array_keys($chapters), true) + 1);
@endphp

@section('head')
  @if ($preview)<meta name="robots" content="noindex">@endif
  <script type="application/ld+json">{!! json_encode(array_filter([
      '@context' => 'https://schema.org',
      '@type' => 'CreativeWork',
      'name' => $project->name,
      'description' => $project->tagline,
      'url' => route('projects.show', ['project' => $project]),
      'image' => $project->thumbnail?->url(),
      'author' => ['@type' => 'Person', 'name' => $profile->full_name, 'url' => route('home')],
      'keywords' => $project->technologies->pluck('name')->implode(', ') ?: null,
  ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@section('content')
  @if ($preview)
    <p class="note" role="status">Preview — status: <b>{{ $project->status }}</b></p>
  @endif
  <article class="case">
    <header class="case-hero">
      <div class="hero-art" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span></div>
      <a class="back" href="{{ route('home') }}#projects"><span aria-hidden="true">←</span> All projects</a>
      <p class="eyebrow">{{ collect([$project->category, $project->experience?->company, $project->period()])->filter()->implode(' · ') }}</p>
      <h1 class="case-title">{{ $project->name }}</h1>
      @if ($project->tagline)<p class="lead">{{ $project->tagline }}</p>@endif
      <dl class="facts row">
        @if ($project->role)<div><dt>Role</dt><dd>{{ $project->role }}</dd></div>@endif
        @if ($project->period())<div><dt>When</dt><dd>{{ $project->period() }}</dd></div>@endif
        @if ($project->technologies->isNotEmpty())<div><dt>Stack</dt><dd><ul class="tags">@foreach ($project->technologies as $t)<li>{{ $t->name }}</li>@endforeach</ul></dd></div>@endif
      </dl>
      @if ($project->live_url || $project->repo_url)
        <p class="cta">
          @if ($project->live_url)<a class="btn primary" href="{{ $project->live_url }}" target="_blank" rel="noopener">Visit live <span aria-hidden="true">↗</span></a>@endif
          @if ($project->repo_url)<a class="btn" href="{{ $project->repo_url }}" target="_blank" rel="noopener">View code <span aria-hidden="true">↗</span></a>@endif
        </p>
      @endif
    </header>

    @if ($project->thumbnail)
      <figure class="case-shot"><img src="{{ $project->thumbnail->url() }}" alt="{{ $project->thumbnail->alt ?: $project->name }}" width="1200" height="750"></figure>
    @endif

    <div class="case-body">
      @if (count($chapters) > 1)
        <nav class="case-toc" aria-label="Case study sections">
          <ol>@foreach ($chapters as $id => $label)<li><a href="#{{ $id }}"><span aria-hidden="true">{{ $num($id) }}</span> {{ $label }}</a></li>@endforeach</ol>
        </nav>
      @endif
      <div class="case-main">
        @foreach (['overview' => 'description', 'problem' => 'problem'] as $id => $field)
          @isset($chapters[$id])
            <section class="chapter-block" aria-labelledby="{{ $id }}" data-reveal>
              <h2 id="{{ $id }}"><span class="num" aria-hidden="true">{{ $num($id) }}</span>{{ $chapters[$id] }}</h2>
              @foreach (Markdown::paragraphs($project->{$field}) as $p)<p>{!! $p !!}</p>@endforeach
            </section>
          @endisset
        @endforeach

        @isset($chapters['built'])
          <section class="chapter-block" aria-labelledby="built" data-reveal>
            <h2 id="built"><span class="num" aria-hidden="true">{{ $num('built') }}</span>{{ $chapters['built'] }}</h2>
            <ul class="feature-grid">@foreach ($project->features as $f)<li>{!! $md($f) !!}</li>@endforeach</ul>
          </section>
        @endisset

        @isset($chapters['architecture'])
          <section class="chapter-block" aria-labelledby="architecture" data-reveal>
            <h2 id="architecture"><span class="num" aria-hidden="true">{{ $num('architecture') }}</span>{{ $chapters['architecture'] }}</h2>
            @foreach (Markdown::paragraphs($project->architecture) as $p)<p>{!! $p !!}</p>@endforeach
            @if ($project->diagram)
              <figure class="diagram">
                <pre tabindex="0" aria-label="Architecture diagram source (scrollable)"><code>{{ $project->diagram }}</code></pre>
                <figcaption>Diagram source — a simplified, public-safe view.</figcaption>
              </figure>
            @endif
          </section>
        @endisset

        @isset($chapters['challenges'])
          <section class="chapter-block" aria-labelledby="challenges" data-reveal>
            <h2 id="challenges"><span class="num" aria-hidden="true">{{ $num('challenges') }}</span>{{ $chapters['challenges'] }}</h2>
            @foreach (Markdown::paragraphs($project->challenges) as $p)<p>{!! $p !!}</p>@endforeach
          </section>
        @endisset

        @include('portfolios.partials.project-details')

        @isset($chapters['highlights'])
          <section class="chapter-block" aria-labelledby="highlights" data-reveal>
            <h2 id="highlights"><span class="num" aria-hidden="true">{{ $num('highlights') }}</span>{{ $chapters['highlights'] }}</h2>
            @foreach ($highlights as $h)
              <h3>{{ $h->title }}</h3>
              @if ($h->summary)<p>{!! $md($h->summary) !!}</p>@endif
              @if ($h->details)<ul class="ticks">@foreach ($h->details as $d)<li>{!! $md($d) !!}</li>@endforeach</ul>@endif
            @endforeach
          </section>
        @endisset

        @isset($chapters['screens'])
          <section class="chapter-block" aria-labelledby="screens">
            <h2 id="screens"><span class="num" aria-hidden="true">{{ $num('screens') }}</span>{{ $chapters['screens'] }}</h2>
            <div class="shots">
              @foreach ($project->screenshots as $shot)
                <a href="{{ $shot->url() }}" target="_blank" rel="noopener"><img src="{{ $shot->url() }}" alt="{{ $shot->alt ?: $project->name.' screenshot '.$loop->iteration }}" width="800" height="500" loading="lazy"></a>
              @endforeach
            </div>
          </section>
        @endisset
      </div>
    </div>

    @if ($related->isNotEmpty())
      <section class="sec related" aria-labelledby="h-related">
        <h2 id="h-related">Related projects</h2>
        <ul class="posts">
          @foreach ($related as $r)
            <li><span class="eyebrow">{{ $r->category ?: $r->period() }}</span><h3><a href="{{ route('projects.show', ['project' => $r]) }}">{{ $r->name }}</a></h3>@if ($r->tagline)<p class="muted">{{ $r->tagline }}</p>@endif</li>
          @endforeach
        </ul>
      </section>
    @endif
  </article>
@endsection
