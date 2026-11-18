@php
    use Illuminate\Support\Str;
    use App\Support\Markdown;
    $md = fn (?string $text) => Str::inlineMarkdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
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
        'Category' => $project->category,
        'At' => $project->experience?->company,
        'Stack' => $project->technologies->pluck('name')->implode(', '),
    ]);
    $prose = array_filter([
        'Problem' => $project->problem,
        'Architecture' => $project->architecture,
        'Challenges' => $project->challenges,
    ]);
@endphp
@extends('portfolios.templates.content_journal.layout')

@section('main')
  @if ($preview)
    <p class="cj-notice" role="status">Preview — status: <strong>{{ $project->status }}</strong>.</p>
  @endif

  <article class="cj-article">
    <header class="cj-pagehead">
      <p class="cj-kicker"><a href="{{ route('home') }}#projects">Projects</a></p>
      <h1>{{ $project->name }}</h1>
      @if ($project->tagline)<p class="cj-lede">{{ $project->tagline }}</p>@endif
      @if ($project->live_url || $project->repo_url)
        <p class="cj-links">
          @if ($project->live_url)<a href="{{ $project->live_url }}" rel="noopener">Visit live site <span aria-hidden="true">↗</span></a>@endif
          @if ($project->repo_url)<a href="{{ $project->repo_url }}" rel="noopener">Source code <span aria-hidden="true">↗</span></a>@endif
        </p>
      @endif
    </header>

    @if ($facts)
      <dl class="cj-facts-table">
        @foreach ($facts as $k => $v)<div><dt>{{ $k }}</dt><dd>{{ $v }}</dd></div>@endforeach
      </dl>
    @endif

    @if ($project->thumbnail)
      <img class="cj-cover" src="{{ $project->thumbnail->url() }}" alt="{{ $project->thumbnail->alt ?: '' }}" loading="lazy">
    @endif

    <div class="cj-prose">
      @foreach (Markdown::paragraphs($project->description) as $p)<p>{!! $p !!}</p>@endforeach
      @if ($project->features)
        <h2>What I built</h2>
        <ul>@foreach ($project->features as $f)<li>{!! $md($f) !!}</li>@endforeach</ul>
      @endif
      @foreach ($prose as $heading => $text)
        <h2>{{ $heading }}</h2>
        @foreach (Markdown::paragraphs($text) as $p)<p>{!! $p !!}</p>@endforeach
      @endforeach

      @if ($highlights->isNotEmpty())
        <h2>Outcomes</h2>
        <ul>@foreach ($highlights as $hl)<li><strong>{{ $hl->title }}</strong>@if ($hl->summary) — {{ $hl->summary }}@endif</li>@endforeach</ul>
      @endif
    </div>

    @if ($project->screenshots->isNotEmpty())
      <section aria-labelledby="shots-h">
        <h2 id="shots-h">Screenshots</h2>
        <div class="cj-shots">
          @foreach ($project->screenshots as $shot)
            <figure>
              <a href="{{ $shot->url() }}"><img src="{{ $shot->url() }}" alt="{{ $shot->alt ?: $project->name.' screenshot' }}" loading="lazy"></a>
              @if ($shot->alt)<figcaption>{{ $shot->alt }}</figcaption>@endif
            </figure>
          @endforeach
        </div>
      </section>
    @endif
  </article>

  @if ($related->isNotEmpty())
    <section class="cj-block" aria-labelledby="related-h">
      <h2 id="related-h">Related projects</h2>
      <ul class="cj-plain">
        @foreach ($related as $r)
          <li><a href="{{ route('projects.show', ['project' => $r]) }}">{{ $r->name }}</a>@if ($r->tagline) — {{ $r->tagline }}@endif</li>
        @endforeach
      </ul>
    </section>
  @endif
@endsection
