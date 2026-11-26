{{-- Thumbnail, name and description of one template. $key, $meta. --}}
<img src="{{ asset($meta['preview']) }}" alt="" loading="lazy" width="640" height="400">
<div class="template-card-body">
  <h3>{{ $meta['name'] }}</h3>
  <p class="small muted">{{ $meta['description'] }}</p>
  @if (! empty($meta['reference']))
    <p class="small"><a href="{{ $meta['reference'] }}" target="_blank" rel="noopener noreferrer">Design reference ↗</a> <span class="muted">(external inspiration, not this portfolio)</span></p>
  @endif
</div>
