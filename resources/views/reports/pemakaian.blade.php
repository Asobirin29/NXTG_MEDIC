@extends('layouts.app', ['title' => $title])

@section('content')
  <x-page-head :title="$title" :subtitle="$from->translatedFormat('d M Y').' – '.$to->translatedFormat('d M Y').' · resep poli + OTC, bersih dari retur'" :crumbs="['Analitik', 'Laporan' => route('reports.index')]" />

  @include('reports.partials.filter')

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 nx-table-compact">
        <thead><tr><th>Obat</th><th>Golongan</th><th class="text-end">Jumlah keluar</th><th class="text-end">Diretur</th><th class="text-end">Nilai</th><th class="text-end">HPP</th><th class="text-end">Transaksi</th></tr></thead>
        <tbody>
          @forelse ($rows as $r)
            <tr>
              <td class="fw-semibold" style="color:var(--nx-text)">{{ $r['drug']->name }}</td>
              <td><span class="badge {{ $r['drug']->category->badge() }}">{{ $r['drug']->category->label() }}</span></td>
              <td class="text-end"><x-stock-qty :qty="$r['quantity']" :drug="$r['drug']" /></td>
              <td class="text-end nx-num text-muted">{{ $r['returned'] ?: '—' }}</td>
              <td class="text-end"><x-rupiah :value="$r['value']" /></td>
              <td class="text-end nx-num text-muted">Rp{{ number_format($r['cost'], 0, ',', '.') }}</td>
              <td class="text-end nx-num">{{ $r['transactions'] }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada obat keluar di periode ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
