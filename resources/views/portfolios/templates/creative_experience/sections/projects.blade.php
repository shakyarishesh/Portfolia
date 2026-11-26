@php use Illuminate\Support\Str; @endphp
<section class="sec work" id="projects" aria-labelledby="h-projects">
  <header class="sec-head"><p class="eyebrow">{{ $section->eyebrow ?: 'Selected work' }}</p><h2 id="h-projects">{{ $section->title ?: 'Projects' }}</h2></header>
  @if ($projects->isNotEmpty())
    <div class="showcase">
      {{-- Project selector: hidden until script.js turns the stacked panels into tabs. Without JS every panel is shown. --}}
      <div class="selector" role="tablist" aria-label="Choose a project" data-selector hidden>
        @foreach ($projects as $p)
          <button type="button" role="tab" id="tab-{{ $p->slug }}" aria-controls="project-{{ $p->slug }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}">
            <span class="n" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span><span class="t">{{ $p->name }}</span>
            @if ($p->category || $p->period())<small>{{ $p->category ?: $p->period() }}</small>@endif
          </button>
        @endforeach
      </div>
      <noscript>
        <nav class="proj-index" aria-label="Project index">
          <ol>@foreach ($projects as $p)<li><a href="#project-{{ $p->slug }}">{{ $p->name }}</a></li>@endforeach</ol>
        </nav>
      </noscript>
      <div class="stage">
        @foreach ($projects as $p)
          <article class="panel" id="project-{{ $p->slug }}" aria-labelledby="pt-{{ $p->slug }}" style="--ang: {{ 120 + $loop->index * 53 }}deg">
            <a class="visual" href="{{ route('projects.show', ['project' => $p]) }}" tabindex="-1" aria-hidden="true">
              @if ($p->thumbnail)
                <img src="{{ $p->thumbnail->url() }}" alt="" width="1200" height="750" loading="lazy">
              @else
                <span class="glyph">{{ Str::upper(Str::substr($p->name, 0, 1)) }}</span>
              @endif
            </a>
            <div class="info">
              <p class="eyebrow">{{ collect([$p->category, $p->experience?->company, $p->period()])->filter()->implode(' · ') }}@if ($p->is_featured) <span class="sticker">Featured</span>@endif</p>
              <h3 id="pt-{{ $p->slug }}">{{ $p->name }}</h3>
              @if ($p->tagline)<p class="tagline">{{ $p->tagline }}</p>@endif
              <dl class="facts">
                @if ($p->problem)<div><dt>{{ $portfolio->projectLabel('problem', 'Problem') }}</dt><dd>{{ Str::limit($p->problem, 240) }}</dd></div>
                @elseif ($p->description)<div><dt>Overview</dt><dd>{{ Str::limit($p->description, 240) }}</dd></div>@endif
                @if ($p->role)<div><dt>Role</dt><dd>{{ $p->role }}</dd></div>@endif
                @if ($p->technologies->isNotEmpty())<div><dt>Stack</dt><dd><ul class="tags">@foreach ($p->technologies as $t)<li>{{ $t->name }}</li>@endforeach</ul></dd></div>@endif
              </dl>
              @if ($p->features)
                <h4>Key features</h4>
                <ul class="ticks">@foreach (array_slice($p->features, 0, 4) as $f)<li>{!! $md($f) !!}</li>@endforeach</ul>
              @endif
              <p class="cta">
                <a class="btn primary" href="{{ route('projects.show', ['project' => $p]) }}">Read the case study<span class="sr-only">: {{ $p->name }}</span></a>
                @if ($p->live_url)<a class="btn" href="{{ $p->live_url }}" target="_blank" rel="noopener">Live <span aria-hidden="true">↗</span><span class="sr-only">: {{ $p->name }} (opens in a new tab)</span></a>@endif
                @if ($p->repo_url)<a class="btn" href="{{ $p->repo_url }}" target="_blank" rel="noopener">Code <span aria-hidden="true">↗</span><span class="sr-only">: {{ $p->name }} (opens in a new tab)</span></a>@endif
              </p>
            </div>
          </article>
        @endforeach
      </div>
    </div>
  @endif
  @if ($highlights->isNotEmpty())
    <h3 class="sub">Wins along the way</h3>
    <ul class="tiles">
      @foreach ($highlights as $h)
        <li data-reveal>
          @if ($h->label)<span class="num">{{ $h->label }}</span>@endif
          <b>{{ $h->title }}</b>
          @if ($h->summary)<span>{!! $md($h->summary) !!}</span>@endif
          @if ($h->project)<a href="{{ route('projects.show', ['project' => $h->project]) }}">{{ $h->project->name }} <span aria-hidden="true">→</span></a>@endif
        </li>
      @endforeach
    </ul>
  @endif
</section>
