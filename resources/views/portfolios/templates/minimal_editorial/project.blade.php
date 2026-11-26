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
    $prose = [
        'overview' => ['Overview', $project->description],
        'problem' => [$portfolio->projectLabel('problem', 'Problem & objectives'), $project->problem],
    ];
    $facts = array_filter([
        'Role' => $project->role,
        'When' => $project->period(),
        'Company' => $project->experience?->company,
        'Type' => $project->category,
    ]);
@endphp
@extends('portfolios.templates.minimal_editorial._layout')

@section('body')
  @if ($preview)
    <p class="me-notice" role="status">Preview — status: <b>{{ $project->status }}</b>. Only you can see this page.</p>
  @endif

  <article class="me-article">
    <header class="me-article-head">
      <p class="me-kicker"><a href="{{ route('home') }}#projects">{{ $portfolio->term('projects') }}</a></p>
      <h1 class="me-article-title">{{ $project->name }}</h1>
      @if ($project->tagline)<p class="me-lede">{{ $project->tagline }}</p>@endif
      @if ($project->live_url || $project->repo_url)
        <p class="me-links">
          @if ($project->live_url)<a class="me-button" href="{{ $project->live_url }}" target="_blank" rel="noopener">Visit live site ↗<span class="sr-only"> (opens in a new tab)</span></a>@endif
          @if ($project->repo_url)<a href="{{ $project->repo_url }}" target="_blank" rel="noopener">View source ↗<span class="sr-only"> (opens in a new tab)</span></a>@endif
        </p>
      @endif
    </header>

    @if ($facts || $project->technologies->isNotEmpty())
      <dl class="me-facts">
        @foreach ($facts as $label => $value)
          <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
        @endforeach
        @if ($project->technologies->isNotEmpty())
          <div class="me-facts-wide"><dt>Stack</dt><dd><ul class="me-chips">@foreach ($project->technologies as $tech)<li>{{ $tech->name }}</li>@endforeach</ul></dd></div>
        @endif
      </dl>
    @endif

    @if ($project->thumbnail)
      <img class="me-hero" src="{{ $project->thumbnail->url() }}" alt="{{ $project->thumbnail->alt ?: $project->name }}">
    @endif

    <div class="me-body">
      @foreach ($prose as $id => [$heading, $text])
        @if ($text)
          <h2 id="{{ $id }}">{{ $heading }}</h2>
          @foreach (Markdown::paragraphs($text) as $para)<p>{!! $para !!}</p>@endforeach
        @endif
      @endforeach

      @if ($project->features)
        <h2 id="built">What I built</h2>
        <ul>@foreach ($project->features as $feature)<li>{!! $md($feature) !!}</li>@endforeach</ul>
      @endif

      @if ($project->architecture)
        <h2 id="architecture">Architecture</h2>
        @foreach (Markdown::paragraphs($project->architecture) as $para)<p>{!! $para !!}</p>@endforeach
      @endif
      @if ($project->diagram)
        <figure class="me-diagram">
          <pre tabindex="0" aria-label="Architecture diagram source (scrollable)"><code>{{ $project->diagram }}</code></pre>
          <figcaption>Architecture diagram (Mermaid source), simplified and public-safe.</figcaption>
        </figure>
      @endif

      @if ($project->challenges)
        <h2 id="challenges">{{ $portfolio->projectLabel('challenges', 'Challenges & solutions') }}</h2>
        @foreach (Markdown::paragraphs($project->challenges) as $para)<p>{!! $para !!}</p>@endforeach
      @endif

      @include('portfolios.partials.project-details')
      @if ($highlights->isNotEmpty())
        <h2 id="highlights">Engineering highlights</h2>
        @foreach ($highlights as $h)
          <h3>{{ $h->title }}</h3>
          @if ($h->summary)<p>{!! $md($h->summary) !!}</p>@endif
          @if ($h->details)<ul>@foreach ($h->details as $detail)<li>{!! $md($detail) !!}</li>@endforeach</ul>@endif
        @endforeach
      @endif
    </div>

    @if ($project->screenshots->isNotEmpty())
      <section class="me-shots" aria-labelledby="shots-h">
        <h2 id="shots-h">{{ $portfolio->term('screenshots') }}</h2>
        @foreach ($project->screenshots as $shot)
          <figure>
            <a href="{{ $shot->url() }}"><img src="{{ $shot->url() }}" alt="{{ $shot->alt ?: $project->name.' screenshot' }}" loading="lazy"></a>
            @if ($shot->alt)<figcaption>{{ $shot->alt }}</figcaption>@endif
          </figure>
        @endforeach
      </section>
    @endif
  </article>

  @if ($related->isNotEmpty())
    <nav class="me-related" aria-labelledby="related-h">
      <h2 id="related-h" class="me-h2">Related projects</h2>
      <ul class="me-plain">
        @foreach ($related as $r)
          <li><a class="me-title-link" href="{{ route('projects.show', ['project' => $r]) }}">{{ $r->name }}</a>@if ($r->tagline)<span class="me-muted"> — {{ $r->tagline }}</span>@endif</li>
        @endforeach
      </ul>
    </nav>
  @endif
@endsection
