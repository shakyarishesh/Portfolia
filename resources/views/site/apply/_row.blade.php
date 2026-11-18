{{-- One repeatable row of group $key. $i is the row index ("__INDEX__" inside the <template>), $row its values. --}}
<div class="repeat-row" data-row>
  <h3>{{ ucfirst($group['singular']) }}</h3>
  <div class="grid-2">
    @foreach ($group['fields'] as $name => $field)
      @include('site.apply._field', [
          'name' => "{$key}[{$i}][{$name}]", 'id' => "{$key}-{$i}-{$name}", 'errorKey' => "{$key}.{$i}.{$name}",
          'value' => $row[$name] ?? ($row ? null : ($field['default'] ?? null)), 'required' => false,
      ])
    @endforeach
  </div>
  <button type="button" class="btn btn-danger" data-remove hidden>Remove this {{ $group['singular'] }}</button>
</div>
