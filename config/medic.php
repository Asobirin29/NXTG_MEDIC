<?php

return [

    /*
    | Kunci HMAC untuk blind index kolom terenkripsi (NIK). Jangan diganti setelah
    | ada data, karena seluruh index pencarian NIK akan tidak cocok lagi.
    */
    'blind_index_key' => env('BLIND_INDEX_KEY'),

    /*
    | Matriks hak akses (PRD §7). ability => role yang diizinkan.
    | Didaftarkan sebagai Gate di AppServiceProvider.
    */
    'abilities' => [
        'dashboard.view' => ['admin', 'kasir', 'perawat', 'dokter', 'apoteker'],
        'registration.access' => ['admin', 'kasir', 'perawat'],
        'patients.view' => ['admin', 'kasir', 'perawat', 'dokter', 'apoteker'],
        'patients.manage' => ['admin', 'kasir', 'perawat'],
        'allergies.manage' => ['kasir', 'perawat', 'dokter'],
        'emr.access' => ['perawat', 'dokter'],
        'emr.vitals' => ['perawat', 'dokter'],
        'emr.soap' => ['dokter'],
        'emr.history' => ['perawat', 'dokter', 'apoteker'],
        'drugs.search' => ['dokter', 'apoteker', 'kasir'],
        'pharmacy.verify' => ['apoteker'],
        'pos.access' => ['apoteker', 'kasir'],
        'pos.void' => ['apoteker', 'kasir'],
        'pos.return.request' => ['kasir', 'apoteker'],
        'pos.return.approve' => ['apoteker'],
        'cds.override.prescribe' => ['dokter'],
        'cds.override.dispense' => ['apoteker'],
        'inventory.view' => ['apoteker'],
        'inventory.manage' => ['apoteker'],
        'interactions.manage' => ['apoteker'],
        'reports.view' => ['admin', 'apoteker', 'kasir', 'dokter'],
        'settings.manage' => ['admin'],
        'users.manage' => ['admin'],
        'audit.view' => ['admin'],
        'imports.access' => ['admin', 'apoteker'],
    ],

    /*
    | Navigasi sidebar (PRD §11.2). Item hanya tampil bila user lolos `can`.
    | `active` = pola nama route yang menandai menu aktif.
    */
    'navigation' => [
        [
            'label' => 'Utama',
            'items' => [
                ['label' => 'Beranda', 'icon' => 'bi-grid-1x2', 'route' => 'dashboard', 'can' => 'dashboard.view'],
            ],
        ],
        [
            'label' => 'Pelayanan',
            'items' => [
                ['label' => 'Antrean & Pendaftaran', 'icon' => 'bi-person-lines-fill', 'route' => 'registration.index', 'active' => 'registration.*', 'can' => 'registration.access'],
                ['label' => 'Ruang Periksa (RME)', 'icon' => 'bi-clipboard2-pulse', 'route' => 'emr.index', 'active' => ['emr.index', 'emr.assessment*', 'emr.consultation'], 'can' => 'emr.access'],
                ['label' => 'Template SOAP', 'icon' => 'bi-card-text', 'route' => 'emr.templates.index', 'active' => 'emr.templates.*', 'can' => 'emr.soap'],
                ['label' => 'Farmasi & Kasir', 'icon' => 'bi-capsule', 'can' => 'pos.access', 'children' => [
                    ['label' => 'Kasir & Antrean Resep', 'route' => 'pos.index', 'active' => ['pos.index', 'pos.cart*', 'pos.labels']],
                    ['label' => 'Riwayat Transaksi', 'route' => 'pos.sales.index', 'active' => 'pos.sales.*'],
                ]],
            ],
        ],
        [
            'label' => 'Data Klinis',
            'items' => [
                ['label' => 'Pasien', 'icon' => 'bi-people', 'route' => 'patients.index', 'active' => 'patients.*', 'can' => 'patients.view'],
                ['label' => 'Obat & Stok', 'icon' => 'bi-box-seam', 'can' => 'inventory.view', 'children' => [
                    ['label' => 'Master Obat', 'route' => 'drugs.index', 'active' => 'drugs.*'],
                    ['label' => 'Zat Aktif', 'route' => 'substances.index', 'active' => 'substances.*'],
                    ['label' => 'Stok & Batch', 'route' => 'stock.index', 'active' => ['stock.index', 'stock.show']],
                    ['label' => 'Penerimaan Barang', 'route' => 'receipts.index', 'active' => 'receipts.*'],
                    ['label' => 'Stok Opname', 'route' => 'stock.opname', 'active' => 'stock.opname*'],
                    ['label' => 'Pemasok', 'route' => 'suppliers.index', 'active' => 'suppliers.*'],
                ]],
                ['label' => 'Interaksi Obat', 'icon' => 'bi-shield-exclamation', 'route' => 'interactions.index', 'active' => 'interactions.*', 'can' => 'interactions.manage'],
            ],
        ],
        [
            'label' => 'Analitik',
            'items' => [
                ['label' => 'Laporan', 'icon' => 'bi-bar-chart-line', 'route' => 'reports.index', 'active' => 'reports.*', 'can' => 'reports.view'],
            ],
        ],
        [
            'label' => 'Administrasi',
            'items' => [
                ['label' => 'Pengaturan', 'icon' => 'bi-gear', 'can' => 'settings.manage', 'children' => [
                    ['label' => 'Pengguna', 'route' => 'settings.users.index', 'active' => 'settings.users.*'],
                    ['label' => 'Poli', 'route' => 'settings.polis.index', 'active' => 'settings.polis.*'],
                    ['label' => 'Tindakan Medis', 'route' => 'settings.services.index', 'active' => 'settings.services.*'],
                    ['label' => 'Jenis Pembayaran', 'route' => 'settings.payment-types.index', 'active' => 'settings.payment-types.*'],
                    ['label' => 'Tampilan', 'route' => 'settings.appearance', 'active' => 'settings.appearance*'],
                ]],
                ['label' => 'Impor Data', 'icon' => 'bi-file-earmark-arrow-up', 'route' => 'imports.index', 'active' => 'imports.*', 'can' => 'imports.access'],
                ['label' => 'Audit Log', 'icon' => 'bi-journal-text', 'route' => 'audit.index', 'active' => 'audit.*', 'can' => 'audit.view'],
            ],
        ],
    ],

];
