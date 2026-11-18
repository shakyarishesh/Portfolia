{{-- Identity, owner, status and SEO fields shared by create and edit. $portfolio, $users. --}}
<div class="form-grid">
  @include('admin.partials.field', ['name' => 'name', 'field' => ['type' => 'text', 'label' => 'Display name', 'rules' => ['required']], 'value' => $portfolio->name])
  @include('admin.partials.field', ['name' => 'username', 'field' => ['type' => 'text', 'label' => 'Username', 'rules' => ['required'],
      'help' => 'Public address '.url('/').'/username. Lower-case letters, numbers and single hyphens, 3–40 characters.'.($portfolio->exists ? ' Changing it breaks the old URL (no redirect is kept).' : '')], 'value' => $portfolio->username])
  @include('admin.partials.field', ['name' => 'user_id', 'field' => ['type' => 'select', 'label' => 'Owner', 'placeholder' => 'No owner',
      'options' => $users->mapWithKeys(fn ($u) => [$u->id => "{$u->name} ({$u->email})"])->all(),
      'help' => 'Optional. The owner can preview this portfolio while it is unpublished.'], 'value' => $portfolio->user_id])
  @include('admin.partials.field', ['name' => 'status', 'field' => ['type' => 'select', 'label' => 'Status', 'rules' => ['required'], 'options' => \App\Models\Portfolio::STATUSES,
      'help' => 'Only published portfolios are visible to the public.'], 'value' => $portfolio->status])
  @include('admin.partials.field', ['name' => 'seo_title', 'field' => ['type' => 'text', 'label' => 'SEO title', 'help' => 'Browser tab and search result title. Leave empty to use the name.'], 'value' => $portfolio->seo_title])
  @include('admin.partials.field', ['name' => 'og_image_url', 'field' => ['type' => 'url', 'label' => 'Social preview image URL', 'help' => 'Optional https:// image shown when the link is shared.'], 'value' => $portfolio->og_image_url])
  @include('admin.partials.field', ['name' => 'seo_description', 'field' => ['type' => 'textarea', 'label' => 'SEO description', 'rows' => 2, 'help' => 'Up to 300 characters.'], 'value' => $portfolio->seo_description])
</div>
