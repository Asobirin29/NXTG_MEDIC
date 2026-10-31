@extends('layouts.app', ['title' => 'Audit Log'])

@section('content')
  <x-page-head title="Audit Log" subtitle="Jejak siapa, kapan, dari mana, dan apa yang berubah. Tidak bisa diubah atau dihapus." />

  <div class="card">
    <div class="card-header">
      <form class="row g-2 align-items-center w-100" method="GET">
        <div class="col-sm-4 col-lg-3">
          <select class="form-select" name="action" aria-label="Filter aksi">
            <option value="">Semua aksi</option>
            @foreach ($actions as $action)
              <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-4 col-lg-3">
          <input class="form-control" type="date" name="date" value="{{ request('date') }}" aria-label="Tanggal">
        </div>
        <div class="col-auto"><button class="btn btn-soft-secondary" type="submit" data-allow-resubmit>Terapkan</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table mb-0 nx-table-compact small">
        <thead>
          <tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Objek</th><th>Perubahan</th><th>IP</th></tr>
        </thead>
        <tbody>
          @forelse ($logs as $log)
            <tr>
              <td class="nx-num text-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
              <td>{{ $log->user?->name ?? 'Sistem' }}</td>
              <td><span class="badge badge-soft-primary">{{ $log->action }}</span></td>
              <td class="text-nowrap">{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</td>
              <td style="max-width:420px">
                @if ($log->new_values || $log->old_values)
                  <details>
                    <summary class="text-muted">{{ count($log->new_values ?? $log->old_values ?? []) }} kolom</summary>
                    <pre class="mb-0 mt-1 small" style="white-space:pre-wrap">{{ json_encode(['lama' => $log->old_values, 'baru' => $log->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                  </details>
                @endif
                @if ($log->reason)<div>Alasan: {{ $log->reason }}</div>@endif
              </td>
              <td class="nx-num">{{ $log->ip_address }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada catatan.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($logs->hasPages())
      <div class="card-footer">{{ $logs->links() }}</div>
    @endif
  </div>
@endsection
