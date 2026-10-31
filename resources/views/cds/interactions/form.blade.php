@extends('layouts.app', ['title' => $rule->exists ? 'Ubah Aturan Interaksi' : 'Tambah Aturan Interaksi'])

@php
  $selected = \App\Modules\Inventory\Models\Substance::whereIn('id', array_filter([
      old('substance_a_id', $rule->substance_a_id), old('substance_b_id', $rule->substance_b_id),
  ]))->pluck('name', 'id');
@endphp

<x-tom-select-assets />

@section('content')
  <x-page-head :title="$rule->exists ? 'Ubah Aturan Interaksi' : 'Tambah Aturan Interaksi'" :crumbs="['Data Klinis', 'Interaksi Obat' => route('interactions.index')]" />

  <div class="row"><div class="col-xl-8">
    <form method="POST" action="{{ $rule->exists ? route('interactions.update', $rule) : route('interactions.store') }}" class="card" novalidate>
      @csrf
      @if ($rule->exists) @method('PUT') @endif
      <div class="card-body">
        @if ($rule->template_id)
          <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>Berasal dari template versi {{ $rule->template_version }}. Perubahan di sini hanya berlaku untuk faskes ini.</div>
        @endif
        <div class="row g-3">
          @foreach (['substance_a_id' => 'Zat aktif A', 'substance_b_id' => 'Zat aktif B'] as $field => $label)
            @php $value = old($field, $rule->{$field}); @endphp
            <div class="col-md-6">
              <label class="form-label" for="{{ $field }}">{{ $label }}</label>
              <select id="{{ $field }}" name="{{ $field }}" class="@error($field) is-invalid @enderror" data-remote-select data-url="{{ route('substances.search') }}" data-secondary-field="atc_code">
                @if ($value)<option value="{{ $value }}" selected>{{ $selected[$value] ?? '#'.$value }}</option>@endif
              </select>
              @error($field)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
          @endforeach
          <div class="col-12">
            <span class="form-label d-block">Tingkat keparahan</span>
            <div class="d-flex flex-wrap gap-3">
              @foreach (\App\Modules\Cds\Enums\Severity::cases() as $sev)
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="severity" id="sev_{{ $sev->value }}" value="{{ $sev->value }}" @checked(old('severity', $rule->severity?->value) === $sev->value)>
                  <label class="form-check-label" for="sev_{{ $sev->value }}"><span class="badge {{ $sev->badge() }}"><i class="bi {{ $sev->icon() }}"></i> {{ $sev->label() }}</span></label>
                </div>
              @endforeach
            </div>
            <div class="form-text">Ringan: catatan kecil · Sedang: banner kuning · Berat: dialog merah yang menahan proses dan butuh alasan tertulis.</div>
          </div>
          <div class="col-12">
            <label class="form-label" for="effect">Dampak klinis</label>
            <textarea class="form-control @error('effect') is-invalid @enderror" id="effect" name="effect" rows="2" required>{{ old('effect', $rule->effect) }}</textarea>
            @error('effect')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-12">
            <label class="form-label" for="recommendation">Saran tindakan</label>
            <textarea class="form-control" id="recommendation" name="recommendation" rows="2">{{ old('recommendation', $rule->recommendation) }}</textarea>
          </div>
          <div class="col-12"><x-active-switch :checked="$rule->is_active" label="Aturan aktif" /></div>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Simpan</button>
        <a class="btn btn-soft-secondary" href="{{ route('interactions.index') }}">Batal</a>
      </div>
    </form>

    @if ($rule->exists)
      <form method="POST" action="{{ route('interactions.destroy', $rule) }}" class="mt-3" data-confirm="Hapus aturan interaksi ini? Tindakan tercatat di audit log.">
        @csrf @method('DELETE')
        <button class="btn btn-sm btn-soft-danger" type="submit"><i class="bi bi-trash me-1"></i>Hapus aturan</button>
      </form>
    @endif
  </div></div>
@endsection
