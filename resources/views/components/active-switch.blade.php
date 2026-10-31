@props(['checked', 'label' => 'Aktif', 'name' => 'is_active'])

<div class="form-check form-switch">
  <input type="hidden" name="{{ $name }}" value="0">
  <input class="form-check-input @error($name) is-invalid @enderror" type="checkbox" role="switch" id="{{ $name }}" name="{{ $name }}" value="1" @checked(old($name, $checked))>
  <label class="form-check-label" for="{{ $name }}">{{ $label }}</label>
  @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
