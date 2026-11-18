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
        'problem' => ['Problem & objectives', $project->problem],
    ];
    $facts = array_filter([
        'Role' => $project->role,
        'When' => $project->period(),
        'Company' => $project->experience?->company,
        'Type' => $project->category,
    ]);
@endphp
@extends('portfolios.templates.gradient._layout')

@section('body')
  <div class="gd-wrap gd-narrow">
    @if ($preview)
      <p class="gd-notice" role="status">Preview — status: <b>{{ $project->status }}</b>. Only you can see this page.</p>
    @endif

    <article class="gd-article">
      <header class="gd-article-head">
        <p class="gd-eyebrow"><a href="{{ route('home') }}#projects">Projects</a></p>
        <h1 class="gd-article-title">{{ $project->name }}</h1>
        @if ($project->tagline)<p class="gd-lede">{{ $project->tagline }}</p>@endif
        @if ($project->live_url || $project->repo_url)
          <p class="gd-actions">
            @if ($project->live_url)<a class="gd-btn" href="{{ $project->live_url }}" target="_blank" rel="noopener">Visit live site ↗<span class="sr-only"> (opens in a new tab)</span></a>@endif
            @if ($project->repo_url)<a class="gd-btn gd-btn-ghost" href="{{ $project->repo_url }}" target="_blank" rel="noopener">View source ↗<span class="sr-only"> (opens in a new tab)</span></a>@endif
          </p>
        @endif
      </header>

      @if ($facts || $project->technologies->isNotEmpty())
        <dl class="gd-facts">
          @foreach ($facts as $label => $value)
            <div class="gd-card"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
          @endforeach
          @if ($project->technologies->isNotEmpty())
            <div class="gd-card gd-facts-wide"><dt>Stack</dt><dd><ul class="gd-chips">@foreach ($project->technologies as $tech)<li>{{ $tech->name }}</li>@endforeach</ul></dd></div>
          @endif
        </dl>
      @endif

      @if ($project->thumbnail)
        <figure class="gd-frame"><img src="{{ $project->thumbnail->url() }}" alt="{{ $project->thumbnail->alt ?: $project->name }}" width="1200" height="675"></figure>
      @endif

      <div class="gd-prose gd-article-body">
        @foreach ($prose as $id => [$heading, $text])
          @if ($text)
            <h2 id="{{ $id }}">{{ $heading }}</h2>
            @foreach (Markdown::paragraphs($text) as $para)<p>{!! $para !!}</p>@endforeach
          @endif
        @endforeach

        @if ($project->features)
          <h2 id="built">What I built</h2>
          <ul class="gd-checks">@foreach ($project->features as $feature)<li>{!! $md($feature) !!}</li>@endforeach</ul>
        @endif

        @if ($project->architecture)
          <h2 id="architecture">Architecture</h2>
          @foreach (Markdown::paragraphs($project->architecture) as $para)<p>{!! $para !!}</p>@endforeach
        @endif
        @if ($project->diagram)
          <figure class="gd-diagram">
            <pre tabindex="0" aria-label="Architecture diagram source (scrollable)"><code>{{ $project->diagram }}</code></pre>
            <figcaption>Architecture diagram (Mermaid source), simplified and public-safe.</figcaption>
          </figure>
        @endif

        @if ($project->challenges)
          <h2 id="challenges">Challenges &amp; solutions</h2>
          @foreach (Markdown::paragraphs($project->challenges) as $para)<p>{!! $para !!}</p>@endforeach
        @endif
      </div>

      @if ($highlights->isNotEmpty())
        <section class="gd-block" aria-labelledby="highlights">
          <h2 id="highlights" class="gd-h2">Engineering highlights</h2>
          <ul class="gd-cards gd-cards-2">
            @foreach ($highlights as $h)
              <li class="gd-card gd-edge-top">
                @if ($h->label)<p class="gd-badge">{{ $h->label }}</p>@endif
                <h3 class="gd-h4">{{ $h->title }}</h3>
                @if ($h->summary)<p>{!! $md($h->summary) !!}</p>@endif
                @if ($h->details)<ul class="gd-points">@foreach ($h->details as $detail)<li>{!! $md($detail) !!}</li>@endforeach</ul>@endif
              </li>
            @endforeach
          </ul>
        </section>
      @endif

      @if ($project->screenshots->isNotEmpty())
        <section class="gd-block" aria-labelledby="shots-h">
          <h2 id="shots-h" class="gd-h2">Screenshots</h2>
          <div class="gd-shots">
            @foreach ($project->screenshots as $shot)
              <figure class="gd-frame">
                <a href="{{ $shot->url() }}"><img src="{{ $shot->url() }}" alt="{{ $shot->alt ?: $project->name.' screenshot' }}" loading="lazy" width="800" height="500"></a>
                @if ($shot->alt)<figcaption>{{ $shot->alt }}</figcaption>@endif
              </figure>
            @endforeach
          </div>
        </section>
      @endif
    </article>

    @if ($related->isNotEmpty())
      <nav class="gd-block" aria-labelledby="related-h">
        <h2 id="related-h" class="gd-h2">Related projects</h2>
        <ul class="gd-cards gd-cards-3">
          @foreach ($related as $r)
            <li class="gd-card gd-edge-top">
              <h3 class="gd-h4"><a class="gd-title-link" href="{{ route('projects.show', ['project' => $r]) }}">{{ $r->name }}</a></h3>
              @if ($r->tagline)<p class="gd-muted">{{ $r->tagline }}</p>@endif
            </li>
          @endforeach
        </ul>
      </nav>
    @endif
  </div>
@endsection
