{{-- /create progress. $current: type | basics | template | details | review. On the form page apply.js moves aria-current between steps. --}}
@php
    $steps = ['type' => 'Portfolio type', 'basics' => 'Your details', 'template' => 'Template', 'details' => 'Your work', 'review' => 'Preview & submit'];
    $at = array_search($current, array_keys($steps), true);
@endphp
<nav aria-label="Progress">
  <ol class="progress-steps" data-progress>
    @foreach (array_keys($steps) as $i => $key)
      <li data-progress-step="{{ $key }}" @class(['done' => $i < $at]) @if ($i === $at) aria-current="step" @endif>
        <span class="progress-num" aria-hidden="true">{{ $i + 1 }}</span>
        @if ($key === 'type' && $i < $at && isset($changeTypeUrl))<a href="{{ $changeTypeUrl }}">{{ $steps[$key] }}</a>@else<span>{{ $steps[$key] }}</span>@endif
      </li>
    @endforeach
  </ol>
</nav>
