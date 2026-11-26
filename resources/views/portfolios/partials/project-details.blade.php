{{--
  Profession-specific project details (projects.details): key facts, text blocks, performance metrics (figures + bars),
  video and supporting documents. Shared by every template's project page; inherits its colours and fonts.
  Private fields (budget) are never shown. Only filled fields render, so technical projects look exactly as before.
--}}
@php
    $defs = config('portfolio.project_details', []);
    // Only this type's fields: details kept from an earlier type stay stored but private until the type uses them again.
    $shown = $portfolio->projectFieldKeys();
    $has = fn (string $k) => in_array($k, $shown, true) && filled($project->{$k});
    $label = fn (string $k) => $portfolio->projectLabel($k, $defs[$k]['label'] ?? \Illuminate\Support\Str::headline($k));
    $facts = collect(['client', 'industry', 'subject', 'audience', 'team_size'])->filter($has)
        ->mapWithKeys(fn ($k) => [$label($k) => $project->{$k}]);
    $texts = collect(['responsibilities', 'process', 'strategy', 'execution', 'methodology', 'materials', 'outcome', 'impact'])->filter($has);
    $metrics = $has('metrics') ? $project->metricRows() : [];
    $disciplines = collect($has('disciplines') ? (array) $project->disciplines : [])->map(fn ($s) => config("portfolio.types.{$s}.name", \Illuminate\Support\Str::headline($s)));
    // YouTube / Vimeo → privacy-friendly embed; anything else stays a link.
    $video = $has('video_url') ? $project->video_url : null;
    $embed = null;
    if ($video && preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,15})~', $video, $m)) {
        $embed = 'https://www.youtube-nocookie.com/embed/'.$m[1];
    } elseif ($video && preg_match('~vimeo\.com/(?:video/)?(\d{5,12})~', $video, $m)) {
        $embed = 'https://player.vimeo.com/video/'.$m[1].'?dnt=1';
    }
    $documents = $project->relationLoaded('documents') ? $project->documents : ($project->exists ? $project->documents()->get() : collect());
@endphp
@if ($facts->isNotEmpty() || $texts->isNotEmpty() || $metrics || $video || $documents->isNotEmpty() || $disciplines->isNotEmpty())
<section class="pf-details" aria-label="{{ $portfolio->term('project') }} details">
  @if ($disciplines->isNotEmpty())
    <p class="pf-disciplines">@foreach ($disciplines as $d)<span>{{ $d }}</span>@endforeach</p>
  @endif
  @if ($facts->isNotEmpty())
    <dl class="pf-facts">
      @foreach ($facts as $term => $value)<div><dt>{{ $term }}</dt><dd>{{ $value }}</dd></div>@endforeach
    </dl>
  @endif
  @foreach ($texts as $k)
    <h2>{{ $label($k) }}</h2>
    @if ($k === 'responsibilities')
      <ul>@foreach ((array) $project->responsibilities as $line)<li>{!! \Illuminate\Support\Str::inlineMarkdown($line, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</li>@endforeach</ul>
    @else
      @foreach (\App\Support\Markdown::paragraphs($project->{$k}) as $para)<p>{!! $para !!}</p>@endforeach
    @endif
  @endforeach
  @if ($metrics)
    <h2>{{ $label('metrics') }}</h2>
    <ul class="pf-metrics">
      @foreach ($metrics as [$name, $value, $percent])
        <li>
          <strong>{{ $value }}</strong><span>{{ $name }}</span>
          @if ($percent !== null)<span class="pf-bar" aria-hidden="true"><i style="width: {{ $percent }}%"></i></span>@endif
        </li>
      @endforeach
    </ul>
  @endif
  @if ($video)
    <h2>Video</h2>
    @if ($embed)
      <div class="pf-video"><iframe src="{{ $embed }}" title="Video: {{ $project->name }}" loading="lazy" allow="fullscreen; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>
    @else
      <p><a href="{{ $video }}" target="_blank" rel="noopener">Watch the video<span class="pf-sr"> (opens in a new tab)</span></a></p>
    @endif
  @endif
  @if ($documents->isNotEmpty())
    <h2>Documents</h2>
    <ul class="pf-docs">
      @foreach ($documents as $doc)
        <li><a href="{{ $doc->url() }}" target="_blank" rel="noopener">{{ $doc->alt ?: pathinfo($doc->original_name, PATHINFO_FILENAME) }}</a> <small>PDF · {{ max(1, round($doc->size / 1024)) }} KB</small></li>
      @endforeach
    </ul>
  @endif
</section>
@once
  <style>
    .pf-details { margin-block: 2rem; }
    .pf-details h2 { font-size: 1.25rem; margin: 1.75rem 0 .6rem; }
    .pf-disciplines { display: flex; flex-wrap: wrap; gap: .4rem; margin: 0 0 1rem; }
    .pf-disciplines span { font-size: .8rem; padding: .15rem .6rem; border-radius: 999px; border: 1px solid color-mix(in srgb, currentColor 25%, transparent); }
    .pf-facts { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: .75rem 1.5rem; margin: 0 0 1rem; }
    .pf-facts dt { font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; opacity: .7; }
    .pf-facts dd { margin: .15rem 0 0; font-weight: 600; }
    .pf-details .pf-metrics { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: .75rem; }
    .pf-details .pf-metrics li { list-style: none; margin: 0; padding: 1rem; border-radius: 12px; border: 1px solid color-mix(in srgb, currentColor 18%, transparent); display: flex; flex-direction: column; gap: .2rem; }
    .pf-metrics strong { font-size: 1.6rem; line-height: 1.1; }
    .pf-metrics span { font-size: .88rem; opacity: .8; }
    .pf-bar { display: block; height: 6px; border-radius: 3px; margin-top: .5rem; background: color-mix(in srgb, currentColor 12%, transparent); overflow: hidden; }
    .pf-bar i { display: block; height: 100%; border-radius: inherit; background: currentColor; opacity: .75; }
    .pf-video { position: relative; aspect-ratio: 16 / 9; border-radius: 12px; overflow: hidden; background: #000; }
    .pf-video iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
    .pf-docs { padding-left: 1.2em; }
    .pf-docs small { opacity: .7; }
    .pf-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
  </style>
@endonce
@endif
