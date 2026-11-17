<article class="card project reveal tilt"
  data-name="{{ mb_strtolower($project->name.' '.$project->tagline.' '.$project->category) }}"
  data-category="{{ mb_strtolower((string) $project->category) }}"
  data-techs="|{{ $project->technologies->pluck('name')->map(fn ($n) => mb_strtolower($n))->implode('|') }}|"
  data-start="{{ $project->start_date?->format('Y-m-d') }}"
  data-featured="{{ $project->is_featured ? 1 : 0 }}"
  data-order="{{ $loop->index ?? 0 }}">
  @if ($project->thumbnail)
    <img class="thumb" src="{{ $project->thumbnail->url() }}" alt="{{ $project->thumbnail->alt ?: $project->name.' screenshot' }}" loading="lazy">
  @endif
  <span class="tag">@if ($project->is_featured)<b class="featured">★ Featured</b> @endif{{ collect([$project->category, $project->experience?->company, $project->period()])->filter()->implode(' · ') ?: 'Project' }}</span>
  <h3><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></h3>
  @if ($project->tagline)<p>{{ $project->tagline }}</p>@endif
  @if ($project->features)
    <details><summary>Key features</summary><ul>@foreach (array_slice($project->features, 0, 6) as $f)<li>{!! $md($f) !!}</li>@endforeach</ul></details>
  @endif
  @if ($project->technologies->isNotEmpty())
    <div class="techs">@foreach ($project->technologies as $t)<button type="button" data-tech="{{ mb_strtolower($t->name) }}" title="Show projects using {{ $t->name }}">{{ $t->name }}</button>@endforeach</div>
  @endif
  <div class="card-links">
    <a class="live" href="{{ route('projects.show', $project) }}">Case study →</a>
    @if ($project->live_url)<a class="live" href="{{ $project->live_url }}" target="_blank" rel="noopener">Visit live ↗</a>@endif
    @if ($project->repo_url)<a class="live" href="{{ $project->repo_url }}" target="_blank" rel="noopener">Code ↗</a>@endif
  </div>
</article>
