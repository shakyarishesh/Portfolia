{{-- Human-readable rendering of a stored value. --}}
@if (is_array($value))
  @if ($value === [])<span class="muted">—</span>@else<ul>@foreach ($value as $v)<li>{{ is_array($v) ? json_encode($v) : $v }}</li>@endforeach</ul>@endif
@elseif (is_bool($value))
  {{ $value ? 'Yes' : 'No' }}
@elseif ($value === null || $value === '')
  <span class="muted">—</span>
@else
  {{ \Illuminate\Support\Str::limit((string) $value, 600) }}
@endif
