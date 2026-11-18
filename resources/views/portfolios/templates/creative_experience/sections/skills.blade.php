<section class="sec" id="skills" aria-labelledby="h-skills">
  <header class="sec-head"><p class="eyebrow">{{ $section->eyebrow ?: 'Toolbox' }}</p><h2 id="h-skills">{{ $section->title ?: 'Skills' }}</h2></header>
  <div class="kits">
    @foreach ($skillCategories as $category)
      <div class="kit" data-reveal>
        <h3>{{ $category->name }}</h3>
        @if ($category->description)<p class="muted">{{ $category->description }}</p>@endif
        <ul class="chips">
          @foreach ($category->skills as $skill)
            <li class="lv{{ (int) $skill->level }}">{{ $skill->name }}@if ($skill->levelLabel())<small>{{ $skill->levelLabel() }}</small>@endif</li>
          @endforeach
        </ul>
      </div>
    @endforeach
  </div>
</section>
