{{-- Thumbnail, name and description of one template. $key, $meta. --}}
<img src="{{ asset($meta['preview']) }}" alt="" loading="lazy" width="640" height="400">
<div class="template-card-body">
  <h3>{{ $meta['name'] }}</h3>
  <p class="small muted">{{ $meta['description'] }}</p>
</div>
