{{-- Portfolio type + Hybrid disciplines. $portfolio, $types (slug => PortfolioType, active plus the current one). --}}
<div class="form-grid">
  @include('admin.partials.field', ['name' => 'type', 'field' => ['type' => 'select', 'label' => 'Portfolio type', 'rules' => ['required'],
      'options' => collect($types)->mapWithKeys(fn ($t) => [$t->slug => trim($t->icon.' '.$t->name).($t->is_active ? '' : ' (switched off)')])->all(),
      'help' => 'Decides the project fields, suggested sections and recommended templates. Content is never removed when it changes.'], 'value' => $portfolio->type])
  @include('admin.partials.field', ['name' => 'disciplines', 'field' => ['type' => 'checkboxes', 'label' => 'Hybrid only: disciplines combined (two or more)',
      'options' => \App\Models\PortfolioType::disciplineOptions()], 'value' => (array) $portfolio->disciplines])
</div>
