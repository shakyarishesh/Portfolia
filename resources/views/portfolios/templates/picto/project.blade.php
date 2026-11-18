@php
    use Illuminate\Support\Str;
    use App\Support\Markdown;
    $md = fn (?string $text) => Str::inlineMarkdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    $page = 'project';
    $meta = [
        'title' => "{$project->name} — {$profile->full_name}",
        'description' => $project->meta_description ?: ($project->tagline ?: Str::limit((string) $project->description, 155)),
        'image' => $project->thumbnail?->url() ?? $profile->avatarSrc(),
        'ogType' => 'article',
        'canonical' => route('projects.show', ['project' => $project]),
    ];
    $nav = [
        ['href' => route('home'), 'emoji' => '🏠', 'label' => 'Home'],
        ['href' => route('home').'#projects', 'emoji' => '🚀', 'label' => 'Projects'],
    ];
    $facts = array_filter([
        ['🎭', 'Role', $project->role],
        ['📅', 'When', $project->period()],
        ['🏢', 'Company', $project->experience?->company],
        ['🏷️', 'Type', $project->category],
    ], fn ($f) => filled($f[2]));
    $blocks = array_filter([
        ['overview', '📖', 'Overview', $project->description],
        ['problem', '🎯', 'Problem & objectives', $project->problem],
        ['architecture', '🏗️', 'Architecture', $project->architecture],
        ['challenges', '🧗', 'Challenges & solutions', $project->challenges],
    ], fn ($b) => filled($b[3]));
@endphp
@extends('portfolios.templates.picto._layout')

@section('body')
  @if ($preview)
    <p class="pc-notice pc-wrap" role="status">Preview — status: <b>{{ $project->status }}</b>. Only you can see this page.</p>
  @endif

  <article class="pc-article pc-wrap">
    <header class="pc-article-head">
      <p class="pc-eyebrow"><a href="{{ route('home') }}#projects"><span aria-hidden="true">🚀</span> Projects</a></p>
      <h1>{{ $project->name }}</h1>
      @if ($project->tagline)<p class="pc-lede">{{ $project->tagline }}</p>@endif
      @if ($project->live_url || $project->repo_url)
        <p class="pc-ctas">
          @if ($project->live_url)<a class="pc-btn pc-btn-main" href="{{ $project->live_url }}" target="_blank" rel="noopener"><span aria-hidden="true">🌐</span> Visit live site<span class="sr-only"> (opens in a new tab)</span></a>@endif
          @if ($project->repo_url)<a class="pc-btn" href="{{ $project->repo_url }}" target="_blank" rel="noopener"><span aria-hidden="true">💻</span> View source<span class="sr-only"> (opens in a new tab)</span></a>@endif
        </p>
      @endif
    </header>

    @if ($facts || $project->technologies->isNotEmpty())
      <dl class="pc-facts">
        @foreach ($facts as [$icon, $label, $value])
          <div class="pc-card pc-tint c{{ $loop->index % 4 + 1 }}"><dt><span aria-hidden="true">{{ $icon }}</span> {{ $label }}</dt><dd>{{ $value }}</dd></div>
        @endforeach
        @if ($project->technologies->isNotEmpty())
          <div class="pc-card pc-facts-wide"><dt><span aria-hidden="true">🧰</span> Stack</dt><dd><ul class="pc-tags">@foreach ($project->technologies as $tech)<li>{{ $tech->name }}</li>@endforeach</ul></dd></div>
        @endif
      </dl>
    @endif

    @if ($project->thumbnail)
      <img class="pc-hero-img" src="{{ $project->thumbnail->url() }}" alt="{{ $project->thumbnail->alt ?: $project->name }}" width="1280" height="800">
    @endif

    <div class="pc-prose">
      @foreach ($blocks as [$id, $icon, $heading, $text])
        <h2 id="{{ $id }}"><span class="pc-mini-marker" aria-hidden="true">{{ $icon }}</span> {{ $heading }}</h2>
        @foreach (Markdown::paragraphs($text) as $para)<p>{!! $para !!}</p>@endforeach
        @if ($id === 'overview' && $project->features)
          <h2 id="built"><span class="pc-mini-marker" aria-hidden="true">🛠️</span> What I built</h2>
          <ul class="pc-checks">@foreach ($project->features as $feature)<li>{!! $md($feature) !!}</li>@endforeach</ul>
        @endif
        @if ($id === 'architecture' && $project->diagram)
          <figure class="pc-diagram">
            <pre tabindex="0" aria-label="Architecture diagram source (scrollable)"><code>{{ $project->diagram }}</code></pre>
            <figcaption>Architecture diagram (Mermaid source), simplified and public-safe.</figcaption>
          </figure>
        @endif
      @endforeach
      @if (! $project->description && $project->features)
        <h2 id="built"><span class="pc-mini-marker" aria-hidden="true">🛠️</span> What I built</h2>
        <ul class="pc-checks">@foreach ($project->features as $feature)<li>{!! $md($feature) !!}</li>@endforeach</ul>
      @endif

      @if ($highlights->isNotEmpty())
        <h2 id="highlights"><span class="pc-mini-marker" aria-hidden="true">💡</span> Engineering highlights</h2>
        @foreach ($highlights as $h)
          <div class="pc-card pc-tint c{{ $loop->index % 4 + 1 }} pc-highlight">
            <h3>{{ $h->title }}</h3>
            @if ($h->summary)<p>{!! $md($h->summary) !!}</p>@endif
            @if ($h->details)<ul>@foreach ($h->details as $detail)<li>{!! $md($detail) !!}</li>@endforeach</ul>@endif
          </div>
        @endforeach
      @endif
    </div>

    @if ($project->screenshots->isNotEmpty())
      <section class="pc-shots" aria-labelledby="shots-h">
        <h2 id="shots-h"><span class="pc-mini-marker" aria-hidden="true">📸</span> Screenshots</h2>
        <div class="pc-grid">
          @foreach ($project->screenshots as $shot)
            <figure class="pc-card">
              <a href="{{ $shot->url() }}"><img src="{{ $shot->url() }}" alt="{{ $shot->alt ?: $project->name.' screenshot' }}" loading="lazy" width="640" height="400"></a>
              @if ($shot->alt)<figcaption>{{ $shot->alt }}</figcaption>@endif
            </figure>
          @endforeach
        </div>
      </section>
    @endif
  </article>

  @if ($related->isNotEmpty())
    <nav class="pc-related pc-wrap" aria-labelledby="related-h">
      <h2 id="related-h"><span class="pc-mini-marker" aria-hidden="true">🔗</span> Related projects</h2>
      <ul class="pc-grid">
        @foreach ($related as $r)
          <li class="pc-card pc-tint c{{ $loop->index % 4 + 1 }}"><a class="pc-stretch" href="{{ route('projects.show', ['project' => $r]) }}">{{ $r->name }}</a>@if ($r->tagline)<p>{{ $r->tagline }}</p>@endif</li>
        @endforeach
      </ul>
    </nav>
  @endif
@endsection
