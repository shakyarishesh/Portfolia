<div class="wrap">
  <p class="eyebrow reveal">{{ $section->eyebrow ?: 'Selected work' }}</p>
  <h2 class="title reveal">{{ $section->title }}</h2>

  @if ($projects->isNotEmpty())
    @php
      $projectCats = $projects->pluck('category')->filter()->unique()->sort()->values();
      $projectTechs = $projects->flatMap->technologies->pluck('name')->unique(fn ($n) => mb_strtolower($n))->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
    @endphp
    @if ($projects->count() > 1)
      <form class="project-tools reveal" role="search" aria-label="Filter projects" onsubmit="return false">
        <label class="sr-only" for="p-q">Search projects</label>
        <input id="p-q" type="search" name="q" placeholder="Search projects…" autocomplete="off">
        @if ($projectCats->count() > 1)
          <label class="sr-only" for="p-cat">Category</label>
          <select id="p-cat" name="category"><option value="">All categories</option>@foreach ($projectCats as $c)<option value="{{ mb_strtolower($c) }}">{{ $c }}</option>@endforeach</select>
        @endif
        <label class="sr-only" for="p-tech">Technology</label>
        <select id="p-tech" name="tech"><option value="">All technologies</option>@foreach ($projectTechs as $t)<option value="{{ mb_strtolower($t) }}">{{ $t }}</option>@endforeach</select>
        <label class="sr-only" for="p-sort">Sort</label>
        <select id="p-sort" name="sort"><option value="">Featured first</option><option value="newest">Newest</option><option value="name">A–Z</option></select>
        <label class="check"><input type="checkbox" name="featured" value="1"> Featured only</label>
      </form>
    @endif
    <div class="cards projects" data-page-size="6">
      @foreach ($projects as $project)
        @include('portfolio.partials.project-card', ['project' => $project])
      @endforeach
    </div>
    <p class="empty-state" hidden role="status">No projects match these filters. <button type="button" class="linkish" data-reset-filters>Clear filters</button></p>
    <button type="button" class="btn more" hidden>Show more projects</button>
  @endif

  @if ($highlights->isNotEmpty())
    <h3 class="section-title sub reveal">Engineering highlights</h3>
    @php $categories = $highlights->pluck('category')->filter()->unique()->values(); @endphp
    @if ($categories->count() > 1)
      <div class="filters reveal" role="group" aria-label="Filter highlights" data-filter-group="highlights">
        <button class="chip active" type="button" data-filter="all" aria-pressed="true">All</button>
        @foreach ($categories as $cat)
          <button class="chip" type="button" data-filter="{{ $cat }}" aria-pressed="false">{{ \Illuminate\Support\Str::headline($cat) }}</button>
        @endforeach
      </div>
    @endif
    <div class="cards">
      @foreach ($highlights as $item)
        <article class="card reveal tilt" data-cat="{{ $item->category }}">
          @if ($item->label)<span class="tag">{{ $item->label }}</span>@endif
          <h3>{{ $item->title }}</h3>
          @if ($item->summary)<p>{!! $md($item->summary) !!}</p>@endif
          @if ($item->details)
            <details><summary>Details</summary><ul>@foreach ($item->details as $d)<li>{!! $md($d) !!}</li>@endforeach</ul></details>
          @endif
          @if ($item->project)<div class="techs"><span>{{ $item->project->name }}</span></div>@endif
        </article>
      @endforeach
    </div>
  @endif
</div>
