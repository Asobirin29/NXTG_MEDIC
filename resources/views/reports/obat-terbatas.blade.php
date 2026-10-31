@extends('layouts.app', ['title' => $title])

@section('content')
  <x-page-head :title="$title" :subtitle="'Psikotropika & narkotika · '.$from->translatedFormat('d M Y').' – '.$to->translatedFormat('d M Y')" :crumbs="['Analitik', 'Laporan' => route('reports.index')]" />

  @include('reports.partials.filter')

  <div class="card">
    <div class="table-responsive">
      <table class="table mb-0 nx-table-compact">
        <thead>
          <tr><th>Obat</th><th>Golongan</th><th>Satuan</th><th class="text-end">Stok awal</th><th class="text-end">Penerimaan</th><th class="text-end">Pengeluaran</th><th class="text-end">Koreksi</th><th class="text-end">Stok akhir</th></tr>
        </thead>
        <tbody>
          @forelse ($rows as $r)
            <tr>
              <td class="fw-semibold" style="color:var(--nx-text)">@can('inventory.view')<a class="text-reset" href="{{ route('stock.show', $r['drug']) }}">{{ $r['drug']->name }}</a>@else{{ $r['drug']->name }}@endcan</td>
              <td><span class="badge {{ $r['drug']->category->badge() }}">{{ $r['drug']->category->label() }}</span></td>
              <td class="small">{{ $r['drug']->base_unit }}</td>
              @foreach (['opening', 'in', 'out', 'correction', 'closing'] as $col)
                <td @class(['text-end nx-num', 'fw-semibold' => $col === 'closing'])>{{ $col === 'correction' && $r[$col] > 0 ? '+' : '' }}{{ number_format($r[$col], 0, ',', '.') }}</td>
              @endforeach
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada obat psikotropika/narkotika di master obat.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer small text-muted">
      Dihitung dari buku besar stok (semua batch, termasuk karantina). Pengeluaran = penyerahan ke pasien dikurangi void & retur; koreksi = penyesuaian & stok opname.
      Stok akhir = stok awal + penerimaan − pengeluaran + koreksi. Format ini bahan pengisian SIPNAP; cocokkan dengan kartu stok sebelum dikirim.
    </div>
  </div>
@endsection
