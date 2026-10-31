# NXTG Medic — Apotek Digital & RME

SaaS Rekam Medis Elektronik + Farmasi + POS untuk puskesmas dan klinik. Spesifikasi lengkap di [PRD.md](PRD.md).

- **Stack:** Laravel 12 · PHP 8.3 · MySQL 8.4 (Laragon) · Blade + Bootstrap 5.3 + jQuery 3.7
- **UI:** template NexaDash (`dashboard-template-main/`, referensi saja). Aset yang dipakai ada di `public/assets/`.
- **Tema:** Teal Klinis (default) dan Indigo NexaDash, dipilih admin faskes di *Pengaturan → Tampilan*.

## Menjalankan lokal

```bash
composer install
cp .env.example .env   # isi DB_* dan BLIND_INDEX_KEY
php artisan key:generate
php artisan migrate:fresh --seed
```

Buka lewat Laragon (`http://nxtg_medic.test`, klik **Reload** di Laragon agar vhost mengarah ke `public/`) atau `php artisan serve`.

Test memakai database MySQL `nxtg_medic_test`:

```bash
php artisan test
```

## Data referensi

- Zat aktif, contoh ICD-10, dan template interaksi obat ada di `database/data/*.php` (dimuat `ReferenceDataSeeder`).
  Template interaksi **wajib divalidasi apoteker mitra** sebelum produksi.
- Kamus ICD-10 lengkap diimpor dari CSV (`kode,nama_indonesia,nama_inggris`):

```bash
php artisan medic:import-icd10 storage/app/icd10.csv
```

## Impor data onboarding

Menu **Administrasi → Impor Data** (admin: tindakan & pasien; apoteker: master obat & stok awal per batch).
Unggah `.xlsx`/`.csv` (unduh templatnya di halaman itu) → sistem memeriksa semua baris dan menampilkan
pratinjau + kesalahan per baris → setelah dikonfirmasi baris valid disimpan. File ≤ 300 baris diproses
langsung; yang lebih besar lewat antrean, jadi jalankan worker:

```bash
php artisan queue:work
```

File sumber dihapus setelah diproses (bisa berisi NIK); NIK disamarkan di pratinjau.

## Akun demo (seeder)

Password semua akun `password`, PIN `1234`. Hanya untuk lokal.

| Akun | Role |
| --- | --- |
| `admin@sehat.test` | Admin Faskes |
| `kasir@sehat.test` | Petugas Loket / Kasir |
| `perawat@sehat.test` | Perawat |
| `dokter@sehat.test` | Dokter |
| `apoteker@sehat.test` | Apoteker |
| `platform@nxtg.test` | Admin Platform (`/platform/login`) |

## Struktur

- `app/Modules/<Modul>/` — Models, Services, Http/Controllers per domain (Identity, Facility, Patient, Emr, Cds, Inventory, Pos, Import, Reporting).
- `app/Support/Tenancy` — `BelongsToTenant` + `TenantScope`: isolasi data per faskes (pengganti RLS).
- `app/Support/Audit` — `Auditable` + `AuditLogger`: jejak audit append-only.
- `config/medic.php` — matriks hak akses (PRD §7) dan navigasi sidebar.
