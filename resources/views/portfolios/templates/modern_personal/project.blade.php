@php
    use Illuminate\Support\Str;
    use App\Support\Markdown;
    $md = fn (?string $text) => Str::inlineMarkdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    $page = 'project';
    $nav = [[route('home').'#projects', 'Work'], [route('home').'#contact', 'Contact']];
    $meta = [
        'title' => "{$project->name} — {$profile->full_name}",
        'description' => $project->meta_description ?: ($project->tagline ?: Str::limit((string) $project->description, 155)),
        'image' => $project->thumbnail?->url() ?? $profile->avatarSrc(),
        'ogType' => 'article',
        'canonical' => route('projects.show', ['project' => $project]),
    ];
    $facts = array_filter([
        'Role' => $project->role,
        'When' => $project->period(),
        'Company' => $project->experience?->company,
        'Type' => $project->category,
    ]);
    $prose = array_filter([
        'overview' => ['Overview', $project->description],
        'problem' => ['Problem & objectives', $project->problem],
        'architecture' => ['Architecture', $project->architecture],
        'challenges' => ['Challenges & solutions', $project->challenges],
    ], fn ($pair) => filled($pair[1]));
@endphp
@extends('portfolios.templates.modern_personal.layout')

@section('body')
  @if ($preview)
    <p class="mp-notice" role="status">Preview — status: <b>{{ $project->status }}</b>. Only you can see this page.</p>
  @endif

  <article>
    <header class="mp-page-head mp-grid">
      <p class="mp-track mp-meta"><a href="{{ route('home') }}#projects">← All work</a></p>
      <div>
        <h1 class="mp-title">{{ $project->name }}</h1>
        @if ($project->tagline)<p class="mp-statement">{{ $project->tagline }}</p>@endif
      </div>
    </header>

    <div class="mp-grid mp-sec">
      <aside class="mp-track" aria-label="Project facts">
        @if ($facts || $project->technologies->isNotEmpty())
          <dl class="mp-facts-dl">
            @foreach ($facts as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach
            @if ($project->technologies->isNotEmpty())<div><dt>Stack</dt><dd>{{ $project->technologies->pluck('name')->implode(', ') }}</dd></div>@endif
          </dl>
        @endif
        @if ($project->live_url || $project->repo_url)
          <p class="mp-links mp-stack">
            @if ($project->live_url)<a class="mp-btn" href="{{ $project->live_url }}" target="_blank" rel="noopener">Visit site ↗<span class="mp-sr"> (opens in a new tab)</span></a>@endif
            @if ($project->repo_url)<a href="{{ $project->repo_url }}" target="_blank" rel="noopener">View source ↗<span class="mp-sr"> (opens in a new tab)</span></a>@endif
          </p>
        @endif
      </aside>

      <div class="mp-article">
        @if ($project->thumbnail)
          <img class="mp-hero" src="{{ $project->thumbnail->url() }}" alt="{{ $project->thumbnail->alt ?: $project->name }}" width="1200" height="750">
        @endif

        @foreach ($prose as $id => [$heading, $text])
          <h2 id="{{ $id }}">{{ $heading }}</h2>
          @foreach (Markdown::paragraphs($text) as $para)<p>{!! $para !!}</p>@endforeach
          @if ($id === 'architecture' && $project->diagram)
            <figure class="mp-diagram">
              <pre tabindex="0" aria-label="Architecture diagram source (scrollable)"><code>{{ $project->diagram }}</code></pre>
              <figcaption>Architecture diagram (Mermaid source).</figcaption>
            </figure>
          @endif
        @endforeach

        @if ($project->features)
          <h2 id="built">What I built</h2>
          <ul>@foreach ($project->features as $feature)<li>{!! $md($feature) !!}</li>@endforeach</ul>
        @endif

        @if ($highlights->isNotEmpty())
          <h2 id="highlights">Highlights</h2>
          @foreach ($highlights as $h)
            <h3>{{ $h->title }}</h3>
            @if ($h->summary)<p>{!! $md($h->summary) !!}</p>@endif
            @if ($h->details)<ul>@foreach ($h->details as $detail)<li>{!! $md($detail) !!}</li>@endforeach</ul>@endif
          @endforeach
        @endif

        @if ($project->screenshots->isNotEmpty())
          <h2 id="screenshots">Screenshots</h2>
          @foreach ($project->screenshots as $shot)
            <figure class="mp-shot">
              <a href="{{ $shot->url() }}"><img src="{{ $shot->url() }}" alt="{{ $shot->alt ?: $project->name.' screenshot' }}" loading="lazy" width="1200" height="750"></a>
              @if ($shot->alt)<figcaption>{{ $shot->alt }}</figcaption>@endif
            </figure>
          @endforeach
        @endif
      </div>
    </div>
  </article>

  @if ($related->isNotEmpty())
    <nav class="mp-sec mp-grid" aria-labelledby="related-h">
      <h2 id="related-h" class="mp-h2">Related work</h2>
      <ul class="mp-rows mp-body">
        @foreach ($related as $r)
          <li><a href="{{ route('projects.show', ['project' => $r]) }}">{{ $r->name }}</a>@if ($r->tagline)<span class="mp-muted"> — {{ $r->tagline }}</span>@endif</li>
        @endforeach
      </ul>
    </nav>
  @endif
@endsection
