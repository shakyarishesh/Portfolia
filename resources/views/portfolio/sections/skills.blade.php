@php $allSkills = $skillCategories->flatMap->skills; @endphp
<div class="wrap">
  <p class="eyebrow reveal">{{ $section->eyebrow ?: 'Toolbox' }}</p>
  <h2 class="title reveal">{{ $section->title }}</h2>

  @if ($allSkills->count() > 5)
    <div class="marquee reveal" aria-hidden="true"><div>
      @foreach ([1, 2] as $_)@foreach ($allSkills as $s)<span>{{ $s->name }}</span>@endforeach @endforeach
    </div></div>
  @endif

  <div class="skills">
    @foreach ($skillCategories as $category)
      <div class="skill-group reveal tilt">
        <h3>{{ $category->name }}</h3>
        @if ($category->description)<p class="group-desc">{{ $category->description }}</p>@endif
        <ul class="skill-list">
          @foreach ($category->skills as $skill)
            <li>
              <span class="skill-name">
                @if ($projectTech->contains(mb_strtolower($skill->name)))
                  <button type="button" class="skill-link" data-tech="{{ mb_strtolower($skill->name) }}" title="See projects using {{ $skill->name }}">{{ $skill->name }} <span aria-hidden="true">↗</span></button>
                @else
                  {{ $skill->name }}
                @endif
                @if ($skill->description)<small>{{ $skill->description }}</small>@endif
              </span>
              @if ($skill->level)
                <span class="meter" role="img" aria-label="{{ $skill->levelLabel() }}" title="{{ $skill->levelLabel() }}">@for ($i = 1; $i <= 4; $i++)<i class="{{ $i <= $skill->level ? 'on' : '' }}"></i>@endfor</span>
              @endif
            </li>
          @endforeach
        </ul>
      </div>
    @endforeach
  </div>
  @if ($projectTech->isNotEmpty())
    <p class="legend reveal">Skills marked ↗ link to the projects that use them.</p>
  @endif
  @if ($allSkills->whereNotNull('level')->isNotEmpty())
    <p class="legend reveal">Proficiency: {{ collect(\App\Models\Skill::LEVELS)->map(fn ($l, $i) => "{$i} = {$l}")->implode(' · ') }}</p>
  @endif
</div>
