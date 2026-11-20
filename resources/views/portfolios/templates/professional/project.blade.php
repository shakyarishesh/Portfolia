@php
    use Illuminate\Support\Str;
    use App\Support\Markdown;
    $md = fn (?string $text) => Str::inlineMarkdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    $icon = fn (string $name) => '<svg class="pro-i" aria-hidden="true" focusable="false"><use href="#pro-i-'.e($name).'"/></svg>';
    $page = 'project';
    $meta = [
        'title' => "{$project->name} — {$profile->full_name}",
        'description' => $project->meta_description ?: ($project->tagline ?: Str::limit(strip_tags($md($project->description)), 155)),
        'image' => $project->thumbnail?->url() ?? $profile->avatarSrc(),
        'ogType' => 'article',
        'canonical' => route('projects.show', ['project' => $project]),
    ];
    $navItems = [[route('home'), 'Overview'], [route('home').'#projects', $portfolio->term('projects')]];
    $prose = [
        'overview' => ['Overview', $project->description],
        'problem' => [$portfolio->projectLabel('problem', 'Problem & objectives'), $project->problem],
    ];
    $facts = array_filter([
        'Role' => $project->role,
        'Timeline' => $project->period(),
        'Company' => $project->experience?->company,
        'Category' => $project->category,
    ]);
@endphp
@extends('portfolios.templates.professional.layout')

@section('body')
  <div class="pro-wrap pro-page">
    @if ($preview)
      <p class="pro-notice" role="status">Preview — status: <b>{{ $project->status }}</b>. Only you can see this page.</p>
    @endif

    <nav class="pro-crumbs" aria-label="Breadcrumb">
      <ol><li><a href="{{ route('home') }}">{{ $profile->full_name }}</a></li><li><a href="{{ route('home') }}#projects">{{ $portfolio->term('projects') }}</a></li><li aria-current="page">{{ $project->name }}</li></ol>
    </nav>

    <header class="pro-page-head">
      @if ($project->is_featured)<p class="pro-kicker"><span class="pro-pill">{!! $icon('star') !!}Featured project</span></p>@endif
      <h1 class="pro-page-title">{{ $project->name }}</h1>
      @if ($project->tagline)<p class="pro-lead">{{ $project->tagline }}</p>@endif
      @if ($project->live_url || $project->repo_url)
        <p class="pro-ctas">
          @if ($project->live_url)<a class="pro-btn pro-btn-primary" href="{{ $project->live_url }}" target="_blank" rel="noopener">{{ $portfolio->term('live') }}{!! $icon('external') !!}<span class="pro-sr"> (opens in a new tab)</span></a>@endif
          @if ($project->repo_url)<a class="pro-btn pro-btn-ghost" href="{{ $project->repo_url }}" target="_blank" rel="noopener">{!! $icon('code') !!}View source<span class="pro-sr"> (opens in a new tab)</span></a>@endif
        </p>
      @endif
    </header>

    <div class="pro-detail @if ($facts || $project->technologies->isNotEmpty()) has-aside @endif">
      <article class="pro-article">
        @if ($project->thumbnail)
          <img class="pro-cover" src="{{ $project->thumbnail->url() }}" alt="{{ $project->thumbnail->alt ?: $project->name }}" width="1280" height="800">
        @endif

        <div class="pro-prose">
          @foreach ($prose as $id => [$heading, $text])
            @if ($text)
              <h2 id="{{ $id }}">{{ $heading }}</h2>
              @foreach (Markdown::paragraphs($text) as $para)<p>{!! $para !!}</p>@endforeach
            @endif
          @endforeach

          @if ($project->features)
            <h2 id="built">What I built</h2>
            <ul class="pro-checks">@foreach ($project->features as $feature)<li>{!! $icon('check') !!}<span>{!! $md($feature) !!}</span></li>@endforeach</ul>
          @endif

          @if ($project->architecture)
            <h2 id="architecture">Architecture</h2>
            @foreach (Markdown::paragraphs($project->architecture) as $para)<p>{!! $para !!}</p>@endforeach
          @endif
          @if ($project->diagram)
            <figure class="pro-diagram">
              <pre tabindex="0" aria-label="Architecture diagram source (scrollable)"><code>{{ $project->diagram }}</code></pre>
              <figcaption>Architecture diagram (Mermaid source).</figcaption>
            </figure>
          @endif

          @if ($project->challenges)
            <h2 id="challenges">{{ $portfolio->projectLabel('challenges', 'Challenges & solutions') }}</h2>
            @foreach (Markdown::paragraphs($project->challenges) as $para)<p>{!! $para !!}</p>@endforeach
          @endif

          @include('portfolios.partials.project-details')
          @if ($highlights->isNotEmpty())
            <h2 id="highlights">Key achievements</h2>
            @foreach ($highlights as $h)
              <h3>{{ $h->title }}</h3>
              @if ($h->summary)<p>{!! $md($h->summary) !!}</p>@endif
              @if ($h->details)<ul>@foreach ($h->details as $detail)<li>{!! $md($detail) !!}</li>@endforeach</ul>@endif
            @endforeach
          @endif
        </div>

        @if ($project->screenshots->isNotEmpty())
          <section class="pro-shots" aria-labelledby="shots-h">
            <h2 id="shots-h" class="pro-h3">{{ $portfolio->term('screenshots') }}</h2>
            <div class="pro-grid pro-grid-2">
              @foreach ($project->screenshots as $shot)
                <figure class="pro-card">
                  <a href="{{ $shot->url() }}"><img src="{{ $shot->url() }}" alt="{{ $shot->alt ?: $project->name.' screenshot' }}" loading="lazy" decoding="async" width="640" height="400"></a>
                  @if ($shot->alt)<figcaption>{{ $shot->alt }}</figcaption>@endif
                </figure>
              @endforeach
            </div>
          </section>
        @endif
      </article>

      @if ($facts || $project->technologies->isNotEmpty())
        <aside class="pro-card pro-facts-card" aria-label="Project details">
          @if ($facts)
            <dl>
              @foreach ($facts as $label => $value)
                <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
              @endforeach
            </dl>
          @endif
          @if ($project->technologies->isNotEmpty())
            <p class="pro-h4">{{ $portfolio->term('technologies') }}</p>
            <ul class="pro-tags">@foreach ($project->technologies as $tech)<li>{{ $tech->name }}</li>@endforeach</ul>
          @endif
        </aside>
      @endif
    </div>

    @if ($related->isNotEmpty())
      <section class="pro-related" aria-labelledby="related-h">
        <h2 id="related-h" class="pro-h3">Related projects</h2>
        <div class="pro-grid pro-grid-3">
          @foreach ($related as $r)
            <article class="pro-card pro-mini">
              <h3 class="pro-card-title"><a class="pro-title-link" href="{{ route('projects.show', ['project' => $r]) }}">{{ $r->name }}</a></h3>
              @if ($r->tagline)<p class="pro-muted">{{ $r->tagline }}</p>@endif
            </article>
          @endforeach
        </div>
      </section>
    @endif
  </div>
@endsection
