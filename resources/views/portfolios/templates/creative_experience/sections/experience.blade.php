<section class="sec" id="experience" aria-labelledby="h-experience">
  <header class="sec-head"><p class="eyebrow">{{ $section->eyebrow ?: 'Experience' }}</p><h2 id="h-experience">{{ $section->title ?: 'Experience' }}</h2></header>
  @if ($experiences->isNotEmpty())
    <ol class="chapters">
      @foreach ($experiences as $job)
        <li class="chapter" data-reveal>
          <div class="ch-when">
            <span class="ch-num" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span>
            <span>{{ $job->period() }}</span>
            @if ($job->is_current)<span class="sticker">Now</span>@endif
          </div>
          <div class="ch-body">
            <h3>{{ $job->role }} <span class="at">at</span> @if ($job->company_url)<a href="{{ $job->company_url }}" target="_blank" rel="noopener">{{ $job->company }}</a>@else{{ $job->company }}@endif</h3>
            @if ($job->location)<p class="muted">{{ $job->location }}</p>@endif
            @if ($job->summary)<p>{!! $md($job->summary) !!}</p>@endif
            @if ($job->highlights)<ul class="ticks">@foreach ($job->highlights as $h)<li>{!! $md($h) !!}</li>@endforeach</ul>@endif
            @if ($job->technologies->isNotEmpty())<ul class="tags" aria-label="Technologies">@foreach ($job->technologies as $t)<li>{{ $t->name }}</li>@endforeach</ul>@endif
          </div>
        </li>
      @endforeach
    </ol>
  @endif
  @if ($milestones->isNotEmpty())
    <h3 class="sub">Milestones</h3>
    <ol class="steps">
      @foreach ($milestones as $m)
        <li data-reveal>@if ($m->date_label)<span class="eyebrow">{{ $m->date_label }}</span>@endif<h4>{{ $m->title }}</h4>@if ($m->summary)<p>{!! $md($m->summary) !!}</p>@endif</li>
      @endforeach
    </ol>
  @endif
</section>
