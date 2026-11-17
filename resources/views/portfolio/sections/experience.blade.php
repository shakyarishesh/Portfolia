<div class="wrap">
  <p class="eyebrow reveal">{{ $section->eyebrow ?: 'Experience' }}</p>
  <h2 class="title reveal">{{ $section->title }}</h2>

  <div class="jobs">
    @foreach ($experiences as $job)
      <article class="job reveal">
        <div class="job-meta">
          <time>{{ $job->period() }}</time>
          <span>{{ \App\Models\Experience::TYPES[$job->employment_type] ?? '' }}@if ($job->location) · {{ $job->location }}@endif</span>
        </div>
        <div>
          <h3>{{ $job->role }} <span class="at">@</span>
            @if ($job->company_url)<a href="{{ $job->company_url }}" target="_blank" rel="noopener">{{ $job->company }} ↗</a>@else{{ $job->company }}@endif
          </h3>
          @if ($job->summary)<p>{!! $md($job->summary) !!}</p>@endif
          @if ($job->highlights)
            <ul>@foreach ($job->highlights as $h)<li>{!! $md($h) !!}</li>@endforeach</ul>
          @endif
          @if ($job->technologies->isNotEmpty())
            <div class="techs">@foreach ($job->technologies as $t)<span>{{ $t->name }}</span>@endforeach</div>
          @endif
        </div>
      </article>
    @endforeach
  </div>

  @if ($milestones->isNotEmpty())
    <h3 class="section-title sub reveal">How the work grew</h3>
    <ol class="timeline">
      @foreach ($milestones as $m)
        <li class="reveal">@if ($m->date_label)<time>{{ $m->date_label }}</time>@endif<h3>{{ $m->title }}</h3>@if ($m->summary)<p>{!! $md($m->summary) !!}</p>@endif</li>
      @endforeach
    </ol>
  @endif
</div>
