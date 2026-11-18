{{-- One repeatable row of group $key. $i is the row index ("__INDEX__" inside the <template>), $row its values. --}}
<div class="repeat-row" data-row>
  <h3>{{ ucfirst($group['singular']) }}</h3>
  <div class="grid-2">
    @foreach ($group['fields'] as $name => $field)
      @continue($name === 'is_current' && isset($group['fields']['end_date'])) {{-- shown as "Present" under the end date --}}
      @include('site.apply._field', [
          'name' => "{$key}[{$i}][{$name}]", 'id' => "{$key}-{$i}-{$name}", 'errorKey' => "{$key}.{$i}.{$name}",
          'value' => $row[$name] ?? ($row ? null : ($field['default'] ?? null)), 'required' => false,
          'present' => $name === 'end_date' && isset($group['fields']['is_current'])
              ? ['name' => "{$key}[{$i}][is_current]", 'id' => "{$key}-{$i}-is_current", 'checked' => ! empty($row['is_current']), 'label' => $group['fields']['is_current']['label']]
              : null,
      ])
    @endforeach
  </div>
  <button type="button" class="btn btn-danger" data-remove hidden>Remove this {{ $group['singular'] }}</button>
</div>
