@php
    use Illuminate\Support\Str;
    use App\Support\Markdown as Paragraphs;
    $md = fn (?string $text) => Str::inlineMarkdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
@endphp
<!doctype html>
<html lang="en" class="doc">
<head>
  @include('portfolio.partials.head', [
      'title' => "{$project->name} — {$profile->full_name}",
      'description' => $project->meta_description ?: ($project->tagline ?: Str::limit((string) $project->description, 155)),
      'image' => $project->thumbnail?->url() ?? $profile->avatarSrc(),
      'ogType' => 'article',
      'canonical' => $preview ? null : route('projects.show', $project),
  ])
  @if ($preview)<meta name="robots" content="noindex">@endif
  <script type="application/ld+json">{!! json_encode(array_filter([
      '@context' => 'https://schema.org',
      '@type' => 'CreativeWork',
      'name' => $project->name,
      'description' => $project->tagline,
      'url' => route('projects.show', $project),
      'image' => $project->thumbnail?->url(),
      'author' => ['@type' => 'Person', 'name' => $profile->full_name, 'url' => route('home')],
      'keywords' => $project->technologies->pluck('name')->implode(', ') ?: null,
  ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
</head>
<body>
  @include('portfolios.partials.preview-banner')
  @if ($preview)
    <div class="preview-bar" role="status">Preview — status: <b>{{ $project->status }}</b>. <a href="{{ route('admin.resources.edit', ['projects', $project->id]) }}">Back to editor</a></div>
  @endif
  <div class="bg" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><div class="grid"></div></div>
  <main class="page">
    <div class="wrap narrow">
      <a class="back" href="{{ route('home') }}#projects">← All projects</a>
      <p class="eyebrow">{{ collect([$project->category, $project->experience?->company, $project->period()])->filter()->implode(' · ') }}</p>
      <h1 class="title big">{{ $project->name }}</h1>
      @if ($project->tagline)<p class="lead">{{ $project->tagline }}</p>@endif
      <div class="cta">
        @if ($project->live_url)<a class="btn primary" href="{{ $project->live_url }}" target="_blank" rel="noopener">Visit live ↗</a>@endif
        @if ($project->repo_url)<a class="btn" href="{{ $project->repo_url }}" target="_blank" rel="noopener">View code ↗</a>@endif
      </div>

      @if ($project->thumbnail)
        <img class="hero-shot" src="{{ $project->thumbnail->url() }}" alt="{{ $project->thumbnail->alt ?: $project->name }}">
      @endif

      <div class="doc-grid">
        <div>
          @if ($project->description)<h2 id="overview">Overview</h2>@foreach (Paragraphs::paragraphs($project->description) as $p)<p>{!! $p !!}</p>@endforeach @endif
          @if ($project->problem)<h2 id="problem">Problem &amp; objectives</h2>@foreach (Paragraphs::paragraphs($project->problem) as $p)<p>{!! $p !!}</p>@endforeach @endif
          @if ($project->features)<h2 id="built">What I built</h2><ul>@foreach ($project->features as $f)<li>{!! $md($f) !!}</li>@endforeach</ul>@endif
          @if ($project->architecture)<h2 id="architecture">Architecture</h2>@foreach (Paragraphs::paragraphs($project->architecture) as $p)<p>{!! $p !!}</p>@endforeach @endif
          @if ($project->diagram)
            <figure class="diagram">
              <div class="diagram-scroll" tabindex="0" aria-label="Architecture diagram (scrollable)"><pre class="mermaid">{{ $project->diagram }}</pre></div>
              <figcaption><button type="button" class="linkish" data-diagram-expand>Expand diagram</button> · Simplified, public-safe view.</figcaption>
            </figure>
          @endif
          @if ($project->challenges)<h2 id="challenges">Challenges &amp; solutions</h2>@foreach (Paragraphs::paragraphs($project->challenges) as $p)<p>{!! $p !!}</p>@endforeach @endif
          @if ($highlights->isNotEmpty())
            <h2>Engineering highlights</h2>
            @foreach ($highlights as $h)
              <h3>{{ $h->title }}</h3>@if ($h->summary)<p>{!! $md($h->summary) !!}</p>@endif
              @if ($h->details)<ul>@foreach ($h->details as $d)<li>{!! $md($d) !!}</li>@endforeach</ul>@endif
            @endforeach
          @endif
        </div>
        <aside>
          @if ($project->role)<h2>Role</h2><p>{{ $project->role }}</p>@endif
          @if ($project->technologies->isNotEmpty())<h2>Stack</h2><div class="techs">@foreach ($project->technologies as $t)<span>{{ $t->name }}</span>@endforeach</div>@endif
        </aside>
      </div>

      @if ($project->screenshots->isNotEmpty())
        <h2>Screenshots</h2>
        <div class="shots">@foreach ($project->screenshots as $shot)<a href="{{ $shot->url() }}" target="_blank" rel="noopener"><img src="{{ $shot->url() }}" alt="{{ $shot->alt ?: $project->name.' screenshot' }}" loading="lazy"></a>@endforeach</div>
      @endif

      @if ($related->isNotEmpty())
        <h2>Related projects</h2>
        <div class="related">
          @foreach ($related as $r)
            <a class="card" href="{{ route('projects.show', $r) }}"><span class="tag">{{ $r->category ?: $r->period() }}</span><h3>{{ $r->name }}</h3>@if ($r->tagline)<p>{{ $r->tagline }}</p>@endif</a>
          @endforeach
        </div>
      @endif
    </div>
  </main>
  @if ($project->diagram)
    <script type="module">
      import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@10.9.1/dist/mermaid.esm.min.mjs';
      const dark = (document.documentElement.dataset.theme || (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark')) === 'dark';
      mermaid.initialize({ startOnLoad: true, securityLevel: 'strict', theme: dark ? 'dark' : 'neutral' });
      document.querySelector('[data-diagram-expand]')?.addEventListener('click', e => {
        const fig = e.target.closest('.diagram');
        fig.classList.toggle('expanded');
        e.target.textContent = fig.classList.contains('expanded') ? 'Collapse diagram' : 'Expand diagram';
      });
    </script>
  @endif
</body>
</html>
