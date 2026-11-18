{{-- Shown on every template when the page differs from what the public sees (unpublished portfolio or unpublished template). Self-styled so it works in any template. --}}
@if (! empty($previewing) && isset($portfolio))
  <div role="status" style="position:sticky;top:0;z-index:2147483000;display:flex;flex-wrap:wrap;gap:.5rem 1rem;align-items:center;justify-content:center;padding:.5rem 1rem;background:#fde047;color:#1c1917;font:600 14px/1.4 system-ui,sans-serif;text-align:center;border-bottom:2px solid #1c1917">
    <span>PREVIEW — not the live site.
      Template: <b>{{ \App\Support\Templates::meta($template)['name'] }}</b>@if ($template !== $portfolio->template) (live: {{ \App\Support\Templates::meta($portfolio->template)['name'] }})@endif ·
      Status: <b>{{ \App\Models\Portfolio::STATUSES[$portfolio->status] ?? $portfolio->status }}</b></span>
    @auth
      <form method="post" action="{{ route('admin.portfolios.preview.exit', $portfolio) }}" style="margin:0">@csrf<button type="submit" style="font:inherit;padding:.15rem .6rem;border:2px solid #1c1917;background:#fff;color:#1c1917;border-radius:4px;cursor:pointer">Exit preview</button></form>
    @endauth
  </div>
@endif
