@php
    use Illuminate\Support\Str;
    use App\Support\Markdown;
    $md = fn (?string $text) => Str::inlineMarkdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    $page = 'home';
    $showThumbs = (bool) ($settings['show_thumbnails'] ?? true);

    // Section order and labels come from the admin; "Writing" slots in before Contact when there are posts or notes.
    $nav = $sections->map(fn ($s) => [
        'key' => $s->key,
        'label' => $s->nav_label ?: Str::headline($s->key),
        'title' => $s->title ?: ($s->nav_label ?: Str::headline($s->key)),
    ])->values();
    if ($hasBlog || $hasNotes) {
        $writing = ['key' => 'writing', 'label' => 'Writing', 'title' => 'Writing'];
        $at = $nav->search(fn ($n) => $n['key'] === 'contact');
        $nav = $at === false ? $nav->push($writing) : $nav->splice(0, $at)->push($writing)->concat($nav)->values();
    }
@endphp
@extends('portfolios.templates.minimal_editorial._layout')

@section('head')
  <script type="application/ld+json">{!! json_encode(array_filter([
      '@context' => 'https://schema.org',
      '@type' => 'Person',
      'name' => $profile->full_name,
      'jobTitle' => $profile->headline ?: null,
      'url' => route('home'),
      'image' => $profile->avatarSrc(),
      'email' => $profile->email ? 'mailto:'.$profile->email : null,
      'address' => $profile->location,
      'worksFor' => $currentJob ? ['@type' => 'Organization', 'name' => $currentJob->company, 'url' => $currentJob->company_url] : null,
      'alumniOf' => $education->map(fn ($e) => ['@type' => 'EducationalOrganization', 'name' => $e->institution])->all() ?: null,
      'sameAs' => $links->pluck('url')->all() ?: null,
      'knowsAbout' => $skillCategories->flatMap->skills->pluck('name')->all() ?: null,
  ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endsection

@section('body')
<div class="me-shell">
  <header class="me-id">
    <div>
      <h1 class="me-name"><a href="{{ route('home') }}">{{ $profile->full_name }}</a></h1>
      @if ($profile->headline)<p class="me-headline">{{ $profile->headline }}</p>@endif
      @if ($profile->intro)<p class="me-intro">{!! $md($profile->intro) !!}</p>@endif
      @if ($profile->open_to_work)<p class="me-status"><span aria-hidden="true">●</span> Open to new opportunities</p>@endif

      <nav class="me-nav" aria-label="Sections">
        <ol>
          @foreach ($nav as $item)
            <li><a href="#{{ $item['key'] }}" data-nav="{{ $item['key'] }}">{{ $item['label'] }}</a></li>
          @endforeach
        </ol>
      </nav>
    </div>

    <ul class="me-social" aria-label="Elsewhere">
      @foreach ($links as $link)
        <li><a href="{{ $link->url }}" rel="me noopener" target="_blank">{{ $link->label ?: Str::headline($link->platform) }}<span class="sr-only"> (opens in a new tab)</span></a></li>
      @endforeach
      @if ($profile->email)<li><a href="mailto:{{ $profile->email }}">Email</a></li>@endif
      @if ($profile->resume)<li><a href="{{ route('resume') }}">Résumé (PDF)</a></li>@endif
    </ul>
  </header>

  <main id="content" class="me-main" tabindex="-1">
    @foreach ($nav as $item)
      <section id="{{ $item['key'] }}" class="me-section" aria-labelledby="{{ $item['key'] }}-h">
        <h2 id="{{ $item['key'] }}-h" class="me-h2">{{ $item['title'] }}</h2>

        @switch($item['key'])
          @case('home')
            @if ($profile->avatarSrc())
              <img class="me-avatar" src="{{ $profile->avatarSrc() }}" alt="Portrait of {{ $profile->full_name }}" width="112" height="112">
            @endif
            @forelse ($profile->aboutParagraphs() as $para)
              <p class="me-prose">{!! $md($para) !!}</p>
            @empty
              @unless ($profile->intro)<p class="me-muted">More about me coming soon.</p>@endunless
            @endforelse
            @if ($profile->location)<p class="me-muted me-small">Based in {{ $profile->location }}.</p>@endif

            @if ($expertise->isNotEmpty())
              <h3 class="me-h3 me-sub">Focus areas</h3>
              <dl class="me-defs">
                @foreach ($expertise as $area)
                  <div><dt>{{ $area->title }}</dt>@if ($area->description)<dd>{!! $md($area->description) !!}</dd>@endif</div>
                @endforeach
              </dl>
            @endif

            @if ($testimonials->isNotEmpty())
              <h3 class="me-h3 me-sub">Kind words</h3>
              @foreach ($testimonials as $t)
                <figure class="me-quote">
                  <blockquote><p>{{ $t->quote }}</p></blockquote>
                  <figcaption>— {{ collect([$t->name, $t->position, $t->company])->filter()->implode(', ') }}</figcaption>
                </figure>
              @endforeach
            @endif
            @break

          @case('experience')
            <ol class="me-list">
              @foreach ($experiences as $job)
                <li class="me-entry reveal">
                  <p class="me-gutter">{{ $job->period() }}</p>
                  <div>
                    <h3 class="me-h3">{{ $job->role }} <span aria-hidden="true">·</span><span class="sr-only">at</span>
                      @if ($job->company_url)<a href="{{ $job->company_url }}" target="_blank" rel="noopener">{{ $job->company }}<span class="sr-only"> (opens in a new tab)</span></a>@else{{ $job->company }}@endif
                    </h3>
                    @if ($job->employment_type || $job->location)<p class="me-meta">{{ collect([$job->employment_type ? Str::of($job->employment_type)->replace('_', '-')->ucfirst() : null, $job->location])->filter()->implode(' · ') }}</p>@endif
                    @if ($job->summary)<p>{!! $md($job->summary) !!}</p>@endif
                    @if ($job->highlights)
                      <ul class="me-points">@foreach ($job->highlights as $point)<li>{!! $md($point) !!}</li>@endforeach</ul>
                    @endif
                    @if ($job->technologies->isNotEmpty())
                      <ul class="me-chips" aria-label="Technologies">@foreach ($job->technologies as $tech)<li>{{ $tech->name }}</li>@endforeach</ul>
                    @endif
                  </div>
                </li>
              @endforeach
            </ol>
            @if ($milestones->isNotEmpty())
              <h3 class="me-h3 me-sub">Milestones</h3>
              <ol class="me-list me-list-tight">
                @foreach ($milestones as $m)
                  <li class="me-entry"><p class="me-gutter">{{ $m->date_label }}</p><div><p class="me-strong">{{ $m->title }}</p>@if ($m->summary)<p class="me-muted">{!! $md($m->summary) !!}</p>@endif</div></li>
                @endforeach
              </ol>
            @endif
            @break

          @case('projects')
            <ol class="me-list">
              @foreach ($projects as $p)
                <li class="me-entry reveal">
                  @if ($showThumbs && $p->thumbnail)
                    <img class="me-thumb" src="{{ $p->thumbnail->url() }}" alt="" loading="lazy" width="160" height="100">
                  @else
                    <p class="me-gutter">{{ $p->period() ?: $p->category }}</p>
                  @endif
                  <div>
                    <h3 class="me-h3"><a class="me-title-link" href="{{ route('projects.show', ['project' => $p]) }}">{{ $p->name }}</a>
                      @if ($p->is_featured)<span class="me-flag">Featured</span>@endif
                    </h3>
                    @php($metaLine = collect([$p->category, $p->experience?->company, $p->role])->filter()->implode(' · '))
                    @if ($metaLine)<p class="me-meta">{{ $metaLine }}</p>@endif
                    @if ($p->tagline)<p>{{ $p->tagline }}</p>@elseif ($p->description)<p>{{ Str::limit(strip_tags($md($p->description)), 220) }}</p>@endif
                    @if ($p->technologies->isNotEmpty())
                      <ul class="me-chips" aria-label="Stack">@foreach ($p->technologies as $tech)<li>{{ $tech->name }}</li>@endforeach</ul>
                    @endif
                    @if ($p->live_url || $p->repo_url)
                      <p class="me-links">
                        @if ($p->live_url)<a href="{{ $p->live_url }}" target="_blank" rel="noopener">Live site<span class="sr-only"> for {{ $p->name }} (opens in a new tab)</span> ↗</a>@endif
                        @if ($p->repo_url)<a href="{{ $p->repo_url }}" target="_blank" rel="noopener">Source<span class="sr-only"> of {{ $p->name }} (opens in a new tab)</span> ↗</a>@endif
                      </p>
                    @endif
                  </div>
                </li>
              @endforeach
            </ol>
            @if ($highlights->isNotEmpty())
              <h3 class="me-h3 me-sub">Engineering highlights</h3>
              <ul class="me-plain">
                @foreach ($highlights as $h)
                  <li class="reveal">
                    <p class="me-strong">{{ $h->title }}@if ($h->project) <span class="me-muted">— <a href="{{ route('projects.show', ['project' => $h->project]) }}">{{ $h->project->name }}</a></span>@endif</p>
                    @if ($h->summary)<p class="me-muted">{!! $md($h->summary) !!}</p>@endif
                  </li>
                @endforeach
              </ul>
            @endif
            @break

          @case('skills')
            <div class="me-skills">
              @foreach ($skillCategories as $cat)
                <div class="reveal">
                  <h3 class="me-h3">{{ $cat->name }}</h3>
                  <ul class="me-chips">
                    @foreach ($cat->skills as $skill)
                      <li @if ($skill->description) title="{{ $skill->description }}" @endif>{{ $skill->name }}@if ($skill->levelLabel())<span class="me-level"> · {{ $skill->levelLabel() }}</span>@endif</li>
                    @endforeach
                  </ul>
                </div>
              @endforeach
            </div>
            @break

          @case('education')
            @if ($education->isNotEmpty())
              <ol class="me-list">
                @foreach ($education as $ed)
                  <li class="me-entry reveal">
                    <p class="me-gutter">{{ $ed->period() }}</p>
                    <div>
                      <h3 class="me-h3">{{ collect([$ed->degree, $ed->field])->filter()->implode(', ') ?: $ed->institution }}</h3>
                      @if ($ed->degree || $ed->field)<p class="me-meta">{{ $ed->institution }}</p>@endif
                      @if ($ed->description)<p>{!! $md($ed->description) !!}</p>@endif
                    </div>
                  </li>
                @endforeach
              </ol>
            @endif
            @if ($certifications->isNotEmpty())
              <h3 class="me-h3 me-sub">Certifications</h3>
              <ul class="me-list me-list-tight">
                @foreach ($certifications as $cert)
                  <li class="me-entry">
                    <p class="me-gutter">{{ $cert->issued_on?->format('M Y') }}</p>
                    <div>
                      <p class="me-strong">@if ($cert->credential_url)<a href="{{ $cert->credential_url }}" target="_blank" rel="noopener">{{ $cert->name }}<span class="sr-only"> (opens in a new tab)</span></a>@else{{ $cert->name }}@endif</p>
                      @if ($cert->issuer)<p class="me-meta">{{ $cert->issuer }}</p>@endif
                    </div>
                  </li>
                @endforeach
              </ul>
            @endif
            @break

          @case('writing')
            @if ($hasBlog)
              <ol class="me-list me-list-tight">
                @foreach ($posts->take(5) as $post)
                  <li class="me-entry reveal">
                    <p class="me-gutter"><time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('M Y') }}</time></p>
                    <div>
                      <h3 class="me-h3"><a class="me-title-link" href="{{ $post->url() }}">{{ $post->title }}</a></h3>
                      @if ($post->excerpt)<p class="me-muted">{{ Str::limit($post->excerpt, 160) }}</p>@endif
                    </div>
                  </li>
                @endforeach
              </ol>
            @endif
            <p class="me-links">
              @if ($hasBlog)<a href="{{ route('blog.index') }}">All articles →</a>@endif
              @if ($hasNotes)<a href="{{ route('notes.index') }}">Notes ({{ $notes->count() }}) →</a>@endif
            </p>
            @break

          @case('contact')
            <p class="me-prose">
              @if ($profile->open_to_work)I'm currently open to new roles and collaborations.@endif
              @if ($profile->email)The quickest way to reach me is <a href="mailto:{{ $profile->email }}">{{ $profile->email }}</a>.@endif
              @if ($profile->show_phone && $profile->phone)You can also call <a href="tel:{{ preg_replace('/[^\d+]/', '', $profile->phone) }}">{{ $profile->phone }}</a>.@endif
            </p>

            @if (session('contact_sent'))
              <p class="me-notice" role="status">Thanks — your message is on its way. I'll reply soon.</p>
            @endif

            @if ($profile->contact_form_enabled)
              <form class="me-form" method="post" action="{{ route('contact.store') }}" novalidate>
                @csrf
                @if ($errors->any())
                  <p class="me-error" role="alert">Please fix the highlighted fields below.</p>
                @endif
                <div class="me-hp" aria-hidden="true">
                  <label for="c-website">Leave this field empty</label>
                  <input id="c-website" type="text" name="website" tabindex="-1" autocomplete="off">
                </div>
                @foreach ([
                    ['name', 'Name', 'text', 'name', true],
                    ['email', 'Email', 'email', 'email', true],
                    ['subject', 'Subject (optional)', 'text', 'off', false],
                ] as [$field, $label, $type, $auto, $required])
                  <div class="me-field">
                    <label for="c-{{ $field }}">{{ $label }}</label>
                    <input id="c-{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field) }}" autocomplete="{{ $auto }}" @if ($required) required @endif
                      @error($field) aria-invalid="true" aria-describedby="c-{{ $field }}-err" @enderror>
                    @error($field)<p id="c-{{ $field }}-err" class="me-field-error">{{ $message }}</p>@enderror
                  </div>
                @endforeach
                <div class="me-field">
                  <label for="c-message">Message</label>
                  <textarea id="c-message" name="message" rows="6" required @error('message') aria-invalid="true" aria-describedby="c-message-err" @enderror>{{ old('message') }}</textarea>
                  @error('message')<p id="c-message-err" class="me-field-error">{{ $message }}</p>@enderror
                </div>
                <button class="me-button" type="submit">Send message</button>
              </form>
            @elseif (! $profile->email)
              <p class="me-muted">Find me through the links on this page.</p>
            @endif
            @break
          @default
            @include('portfolios.partials.text-section', ['key' => $item['key']])
        @endswitch
      </section>
    @endforeach

    <footer class="me-foot">
      <p>© {{ now()->year }} {{ $profile->full_name }}. Set in your system's own typefaces.</p>
    </footer>
  </main>
</div>
@endsection
