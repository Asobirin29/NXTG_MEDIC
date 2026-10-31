# Apotek Digital & Klinik (RME Terpadu): PRD, System Design, dan UI/UX

**Versi 0.2**, Pembaruan Integrasi EMR/RME, 30 Oktober 2026

---

## 1. Ringkasan

**Apotek Digital & RME** adalah aplikasi web berbasis langganan (SaaS) yang menyatukan **Rekam Medis Elektronik (RME/EMR)** dan **Manajemen Farmasi serta Kasir (POS)** untuk puskesmas dan klinik. Aplikasi ini mencakup alur pelayanan fasilitas kesehatan (faskes) dari hulu ke hilir: pendaftaran kunjungan pasien, pemeriksaan klinis oleh perawat dan dokter (SOAP dan diagnosis ICD-10), peresepan elektronik (*e-Prescription*) termasuk obat racikan, pengecekan otomatis alergi serta interaksi obat, manajemen stok obat per batch (FEFO), hingga kasir dengan jenis pembayaran fleksibel.

Target awalnya adalah puskesmas dan klinik swasta sekaligus. Satu sistem melayani banyak faskes dengan isolasi data yang terpisah dan aman.

---

## 2. Konteks dan Masalah

Pencatatan medis, resep, dan stok obat di banyak faskes saat ini masih tersebar di buku atau kertas rekam medis, kertas resep, Excel, dan beberapa aplikasi kasir yang terpisah. Akibatnya:

- **Rekam medis terfragmentasi:** Dokter kesulitan melihat riwayat kunjungan, diagnosis masa lalu, dan riwayat obat yang pernah dikonsumsi pasien secara utuh.
- **Salah baca resep dan salah hitung racikan:** Resep kertas dari ruang poli sering sulit dibaca petugas farmasi, dan resep racikan yang dihitung manual rawan salah dosis serta salah jumlah.
- **Alergi dan interaksi obat bergantung pada ingatan:** Peringatan alergi dan interaksi obat tidak terdeteksi sejak dokter menulis resep maupun saat obat disiapkan di farmasi.
- **Dokter meresepkan obat yang stoknya kosong:** Tidak ada visibilitas stok farmasi dari ruang periksa dokter, sehingga apoteker harus bolak-balik mengonfirmasi penggantian obat.
- **Stok sulit dilacak:** Obat kedaluwarsa baru ketahuan terlambat dan pencatatan lintas batch tidak akurat.
- **Perbedaan cara kerja komersial vs layanan publik:** Puskesmas (pelayanan BPJS atau gratis) dan klinik swasta (umum/asuransi) punya cara penagihan berbeda, tetapi membutuhkan satu alur klinis dan farmasi yang sama.

Sistem lama tidak dibuang begitu saja. Data dari buku, Excel, dan aplikasi lama tetap harus bisa diimpor ke dalam sistem.

---

## 3. Pengguna dan Role

| Role | Tugas Utama |
| :--- | :--- |
| **Petugas Pendaftaran / Loket / Kasir** | Mendaftarkan pasien baru/lama, memasukkan pasien ke antrean poli, melayani pembayaran tindakan medis dan obat sesuai jenis pembayaran, mencetak struk. |
| **Perawat / Bidan** | Melakukan asesmen awal (triase), mencatat tanda-tanda vital (tensi, suhu, berat badan, tinggi badan, dll.), serta mencatat keluhan awal dan alergi pasien. |
| **Dokter** | Melihat riwayat RME pasien, mengisi catatan perkembangan pasien (SOAP), menetapkan diagnosis (ICD-10), mencatat tindakan medis, dan membuat resep elektronik (*e-Prescription*) dengan melihat ketersediaan stok. |
| **Apoteker / Tenaga Teknis Kefarmasian** | Memverifikasi resep elektronik dari dokter (atau input resep manual), menyetujui peringatan berat, menyiapkan obat & mencetak etiket aturan pakai, serta mengelola master obat, stok batch, dan tabel interaksi. |
| **Super Admin Platform** | Membuat tenant faskes baru, mengatur paket langganan, dan menangguhkan akun. Ini adalah pemilik produk. |

Perangkat yang dipakai: komputer desktop di meja pendaftaran, ruang poli dokter, dan ruang farmasi, serta tablet atau HP. Internet diasumsikan stabil, sehingga mode offline tidak termasuk cakupan versi awal.

---

## 4. Tujuan dan Non-Tujuan

### Tujuan
1. **Alur Klinis Tanpa Kertas (*Paperless*):** Kunjungan pasien dari pendaftaran, pemeriksaan dokter (RME), peresepan, hingga penyerahan obat di farmasi terhubung dalam satu alur digital.
2. **Pasien Aman Sejak Ruang Periksa:** Alergi dan interaksi obat terdeteksi otomatis saat dokter menyusun resep di RME maupun sebelum obat diserahkan di farmasi/kasir.
3. **Akurasi Stok dan Biaya:** Setiap obat yang keluar (dijual maupun dibagikan gratis/BPJS) dan tindakan medis tercatat akurat, serta stok terlacak sampai tingkat batch (FEFO) dalam satuan terkecil.
4. **Adopsi Cepat & Patuh Regulasi:** Faskes baru bisa mulai beroperasi dengan cepat lewat impor data lama dan memenuhi standar pencatatan RME Kemenkes.

### Bukan Tujuan Versi Awal
- Integrasi API langsung (*live bridging*) ke BPJS P-Care dan SATUSEHAT (struktur data ICD-10, KFA, dan kunjungan sudah disiapkan di Fase 1, konektor API dikerjakan di Fase 3).
- Mode offline.
- Manajemen rawat inap (manajemen bangsal/kamar) dan integrasi alat laboratorium/radiologi lanjut (LIS/DICOM). Fokus awal adalah **Rawat Jalan dan Farmasi**.
- Akuntansi lengkap (neraca, buku besar keuangan).
- Manajemen multi-cabang dalam satu faskes.

---

## 5. Ruang Lingkup dan Fase

Dengan masuknya EMR/RME sebagai kebutuhan utama, alur pendaftaran kunjungan, RME dasar (Tanda Vital, SOAP, ICD-10), dan *e-Prescription* ditarik ke Fase 1 agar faskes bisa langsung menjalankan operasional dari poli hingga apotek.

| Fase | Isi Cakupan |
| :--- | :--- |
| **Fase 1 (MVP: RME Rawat Jalan + Farmasi + POS)** | - **Pendaftaran & Antrean:** Data pasien dasar, alergi berbasis zat aktif, pendaftaran kunjungan ke poli.<br>- **EMR / RME Inti:** Asesmen awal perawat (Tanda Vital), CPPT Dokter (SOAP), pencarian Diagnosis (ICD-10), pencatatan tindakan medis dasar, dan **e-Prescription** (resep reguler).<br>- **Cek Klinis (CDS):** Deteksi alergi dan interaksi obat otomatis di layar resep dokter dan di keranjang POS.<br>- **Farmasi & Stok:** Master obat (standar kode KFA), stok per batch (FEFO), konversi kemasan ke satuan terkecil.<br>- **Kasir (POS) & Etiket:** Penarikan tagihan otomatis dari resep/tindakan + penjualan obat bebas (OTC), harga per jenis pembayaran (Umum, BPJS, Gratis), cetak struk & cetak etiket obat.<br>- **Onboarding:** Laporan obat terbatas sederhana & impor data Excel/CSV. |
| **Fase 2 (Klinis Lanjutan & Racikan)** | - **Resep Racikan (*Compounding*):** Kalkulator konversi dosis (mg) otomatis ke jumlah tablet/puyer/kapsul.<br>- **Dokumen Medis & Poli Spesifik:** Cetak Surat Keterangan Sakit, Surat Sehat, Rujukan, serta Odontogram Poli Gigi dasar.<br>- **Laporan Regulasi:** Laporan Obat Terbatas lanjutan, LPLPO Puskesmas, dan Laporan 10 Besar Penyakit (ICD-10). |
| **Fase 3 (Integrasi & Ekosistem)** | - **Adapter Integrasi:** Konektor API SATUSEHAT (*Encounter, Condition, MedicationRequest, MedicationDispense*) dan bridging BPJS.<br>- **Analitik Lanjutan:** Laporan eksekutif lanjutan dan penyempurnaan berdasarkan masukan pengguna. |

---

## 6. Kebutuhan Fungsional Fase 1

### 6.1 Pendaftaran, Pasien, Alergi, dan Antrean Kunjungan
- **Data Pasien Dasar:** Memuat nama lengkap, tanggal lahir, jenis kelamin, NIK (terenkripsi dengan *blind index*), Nomor Rekam Medis (No. RM otomatis per faskes), kontak, dan nomor peserta jaminan (BPJS/Asuransi) bila ada.
- **Alergi Berbasis Zat Aktif:** Alergi obat dicatat berdasarkan zat aktif (`substances`), bukan nama dagang, sehingga merek lain dengan kandungan zat aktif yang sama tetap terdeteksi. Mendukung catatan alergi non-obat (makanan/cuaca).
- **Antrean Kunjungan (*Encounter*):** Petugas loket mendaftarkan pasien ke poli tujuan (misal: Poli Umum, Poli Gigi, KIA) dan memilih jenis pembayaran di awal kunjungan. Status kunjungan bergerak dari: `QUEUED` (Menunggu) $\rightarrow$ `NURSE_ASSESSED` (Diperiksa Perawat) $\rightarrow$ `IN_CONSULTATION` (Diperiksa Dokter) $\rightarrow$ `WAITING_PHARMACY` (Menunggu Obat) $\rightarrow$ `READY_BILLING` (Siap Bayar/Serah) $\rightarrow$ `COMPLETED` (Selesai).

### 6.2 Rekam Medis Elektronik (EMR / RME) & e-Prescription
- **Ringkasan Riwayat Pasien:** Saat dokter membuka layar pemeriksaan, tampil riwayat kunjungan sebelumnya secara kronologis (tanggal, tanda vital, diagnosis ICD-10, tindakan, dan obat yang pernah diberikan).
- **Tanda-Tanda Vital (*Vital Signs*) & Antropometri:** Tekanan darah (sistolik/diastolik), detak jantung (HR), laju pernapasan (RR), suhu tubuh, saturasi oksigen ($SpO_2$), berat badan (BB), dan tinggi badan (TB). Berat badan pasien anak ditampilkan menonjol di layar dokter dan farmasi sebagai acuan verifikasi dosis.
- **Pemeriksaan Metode SOAP & ICD-10:**
  - **S (*Subjective*):** Keluhan utama dan riwayat penyakit sekarang (dilengkapi *quick-text template* agar dokter mengetik lebih cepat).
  - **O (*Objective*):** Hasil pemeriksaan fisik dan tanda-tanda vital.
  - **A (*Assessment*):** Diagnosis primer dan sekunder menggunakan kamus standar **ICD-10** (pencarian kode dan nama penyakit Bahasa Indonesia/Inggris).
  - **P (*Plan*):** Rencana terapi, edukasi pasien, tindakan medis poli beserta tarifnya, dan pembuatan **Resep Elektronik (*e-Prescription*)**.
- **e-Prescription Terintegrasi Stok:** Dokter mencari obat dari master obat faskes dengan melihat **sisa stok aktual** secara langsung, menentukan jumlah, satuan kemasan, dan aturan pakai (*signa*, misal: `3 x 1 tablet sesudah makan`).
- **Kunci Rekam Medis & Addendum:** Setelah pemeriksaan diselesaikan dokter, catatan SOAP dikunci. Koreksi di kemudian hari wajib melalui mekanisme *Addendum* (catatan asli tidak terhapus dan riwayat perubahan tercatat).

### 6.3 Pengecekan Alergi dan Interaksi Obat (Clinical Decision Support)
- **Tabel Aturan Interaksi:** Pasangan zat aktif A dan B, tingkat keparahan (ringan, sedang, berat), keterangan dampak klinis, dan saran tindakan.
- **Template & Kustomisasi:** Faskes baru mendapat template awal yang dikurasi apoteker mitra. Setiap faskes dapat mengubah dan menambah aturannya sendiri.
- **Titik Pengecekan Ganda:**
  1. **Di Layar Dokter (Saat Buat e-Resep):** Berjalan otomatis setiap obat ditambahkan ke resep terhadap alergi pasien, obat lain di resep, dan duplikasi zat aktif. Dokter dapat mengganti obat atau melakukan *override* dengan alasan klinis tertulis.
  2. **Di Layar Farmasi/POS:** Berjalan otomatis saat apoteker memproses resep atau melayani pembelian obat langsung (OTC). Peringatan berat di kasir/farmasi hanya boleh dilanjutkan oleh apoteker dengan alasan tertulis melalui PIN apoteker di layar kasir atau persetujuan ke perangkat apoteker.
- **Penafian Klinis:** Sistem ini merupakan alat bantu pendukung keputusan klinis. Keputusan akhir tetap berada pada tenaga kesehatan.

### 6.4 Obat, Standar KFA, Stok Batch (FEFO), dan Konversi Satuan
- **Master Obat:** Memuat nama obat, kode standar **KFA (Kamus Farmasi dan Alat Kesehatan Kemenkes)**, bentuk sediaan, kekuatan, zat aktif (satu atau lebih), kategori (bebas, bebas terbatas, keras, psikotropika, narkotika), satuan terkecil (*base unit*), dan struktur konversi kemasan (misal: 1 Box = 10 Strip = 100 Tablet).
- **Penyimpanan Satuan Terkecil:** Seluruh saldo kuantitas stok di database disimpan dalam satuan terkecil (tablet, kapsul, ml, ampul) untuk menjamin akurasi pemotongan stok lintas batch.
- **Penerimaan & FEFO:** Penerimaan barang membuat batch baru (nomor batch, tanggal kedaluwarsa, harga beli, jumlah, dan pemasok). Pengeluaran obat otomatis mengambil batch dengan kedaluwarsa paling dekat (**FEFO**). Batch yang sudah kedaluwarsa otomatis diblokir.
- **Buku Besar Stok (*Immutable*):** Setiap perubahan stok dicatat sebagai pergerakan stok (`stock_movements`) yang tidak bisa diedit. Koreksi wajib dilakukan lewat penyesuaian stok (*adjustment*) atau stok opname dengan alasan tercatat.
- **Indikator Peringatan Stok:** Notifikasi stok menipis (ambang batas per obat) dan batch yang mendekati kedaluwarsa dalam 90 hari.

### 6.5 Verifikasi Farmasi, Harga, Kasir (POS), dan Cetak Etiket
- **Dua Jalur Pelayanan di Farmasi/POS:**
  1. **Jalur Resep Poliklinik (Dari EMR):** Resep dari ruang dokter otomatis masuk ke daftar antrean farmasi. Apoteker memverifikasi resep, sistem mengalokasikan batch secara FEFO, dan apoteker mencetak **Etiket Obat** (nama pasien, tanggal, nama obat, aturan pakai/signa, kedaluwarsa).
  2. **Jalur Langsung / OTC (Tanpa EMR):** Pencarian obat lewat ketik nama atau scan barcode. Obat bertanda keras, psikotropika, dan narkotika yang dibeli lewat resep kertas luar wajib mengisi referensi resep (nomor resep, nama dokter, tanggal) dan masuk ke laporan khusus.
- **Harga & Jenis Pembayaran:**
  - Jenis pembayaran dapat diatur per faskes: Umum, BPJS, Asuransi, Program/Gratis, dan jenis lain yang ditambahkan faskes.
  - Tagihan kasir menggabungkan **biaya tindakan medis poli** dan **biaya obat**.
  - Untuk BPJS dan Gratis, nominal tagihan ke pasien adalah Rp0, tetapi nilai HPP/harga obat tetap dicatat agar laporan valuasi pemakaian akurat.
- **Tahan Transaksi, Void, dan Retur:**
  - Transaksi bisa ditahan (*hold*) dan dilanjutkan kembali (alokasi batch dikunci dan dihitung ulang secara aktual saat tombol Bayar ditekan).
  - **Void:** Pembatalan oleh kasir dengan alasan tercatat untuk transaksi yang obatnya belum diserahkan ke pasien.
  - **Retur:** Untuk transaksi yang sudah diserahkan, wajib melalui verifikasi apoteker untuk menentukan apakah obat dikembalikan ke **Stok Layak Jual** atau dipisahkan ke **Stok Karantina/Rusak**. Void atau retur obat psikotropika dan narkotika wajib mendapat persetujuan apoteker.

### 6.6 Impor Data
- Impor master obat, stok awal per batch, master tindakan medis, dan data pasien dari Excel atau CSV dengan fitur pratinjau dan laporan kesalahan per baris.

---

## 7. Matriks Hak Akses

| Kemampuan | Apoteker | Kasir / Loket | Dokter | Perawat |
| :--- | :---: | :---: | :---: | :---: |
| Pendaftaran pasien & antrean kunjungan | Tidak | **Ya** | Tidak | **Ya** |
| Lihat identitas dasar dan alergi pasien | **Ya** | **Ya** | **Ya** | **Ya** |
| Input tanda vital & asesmen awal (RME) | Tidak | Tidak | **Ya** | **Ya** |
| Isi pemeriksaan SOAP, ICD-10 & buat e-Resep | Tidak | Tidak | **Ya** | Tidak |
| Lihat riwayat obat dan rekam medis (RME) | Terbatas (Farmasi) | Tidak | **Ya (Penuh)** | **Ya** |
| Telaah resep, alokasi obat & cetak etiket | **Ya** | Tidak | Tidak | Tidak |
| Kelola master obat, harga, stok & tabel interaksi | **Ya** | Tidak | Tidak | Tidak |
| Transaksi pembayaran POS & cetak struk | **Ya** | **Ya** | Tidak | Tidak |
| Menyetujui peringatan interaksi/alergi berat | **Ya (Farmasi/POS)** | Tidak | **Ya (Saat Resep)** | Tidak |
| Void transaksi belum diserahkan | **Ya** | **Ya (Dengan Alasan)** | Tidak | Tidak |
| Retur dan void obat terbatas | **Ya** | Mengajukan | Tidak | Tidak |
| Laporan operasional & klinis | **Ya** | Terbatas | Terbatas (Klinis) | Tidak |

---

## 8. User Story Utama

- **Sebagai perawat**, saya ingin mencatat tensi, berat badan, dan alergi pasien sebelum pasien masuk ke ruang dokter, supaya dokter bisa langsung fokus memeriksa.
- **Sebagai dokter**, saya ingin melihat riwayat penyakit dan obat pasien di masa lalu serta membuat resep dengan melihat sisa stok apotek secara langsung, supaya saya tidak meresepkan obat yang sedang habis atau memicu alergi.
- **Sebagai apoteker**, saya ingin menerima resep digital langsung dari layar dokter lengkap dengan diagnosis dan berat badan pasien, supaya saya tidak salah membaca tulisan tangan dan bisa memverifikasi dosis dengan cepat.
- **Sebagai apoteker**, saya ingin mencetak label etiket aturan pakai secara otomatis dari data resep dokter dan melihat batch yang hampir kedaluwarsa, supaya pelayanan aman dan obat mendekati kedaluwarsa bisa dikeluarkan lebih dulu.
- **Sebagai kasir**, saya ingin memilih jenis pembayaran sekali di awal kunjungan sehingga biaya tindakan dokter dan obat dihitung otomatis di kasir, supaya antrean loket tidak menumpuk.
- **Sebagai pemilik produk**, saya ingin menambah faskes baru tanpa menyentuh server, supaya bisa berjualan ke banyak faskes dengan cepat.

---

## 9. Kebutuhan Non-Fungsional

- **Kecepatan:** Pencarian obat, pencarian kode ICD-10, dan pencarian NIK pasien di bawah 1 detik pada katalog puluhan ribu item; perpindahan antrean poli ke farmasi dan proses checkout terasa instan.
- **Ketepatan:** Kalkulasi konversi kemasan ke satuan terkecil dan perhitungan dosis tidak boleh salah. Semua fungsi perhitungan memiliki uji otomatis (*unit test*).
- **Ketersediaan:** Target 99,5% pada jam layanan faskes, dengan pencadangan harian terenkripsi dan pengujian pemulihan berkala.
- **Audit Medis & Farmasi:** Setiap pembuatan/perubahan RME, perubahan stok, *override* peringatan, void, dan akses data pasien mencatat siapa, kapan, alamat IP, dan alasannya.
- **Aksesibilitas & Bahasa:** Antarmuka Bahasa Indonesia yang jelas, kontras tinggi, fokus keyboard terlihat, dan target sentuh besar di tablet.

---

## 10. System Design

### 10.1 Arsitektur Modular Monolith
Satu aplikasi dengan modul domain terpisah (**Identity & Tenant, Patient & Queue, EMR & e-Prescription, Clinical Decision Support, Inventory & Pharmacy, POS & Billing, Reporting & Adapter**) dan satu database PostgreSQL. Microservices tidak dipakai karena terlalu berat untuk tahap ini, namun batas antar-modul dijaga rapi agar mudah dipecah bila diperlukan.

- **Frontend dan Backend:** TypeScript full-stack (Next.js). Satu bahasa, tipe data ketat untuk kalkulasi konversi dan dosis, UI responsif untuk desktop dan tablet.
- **Database:** PostgreSQL.
- **Antrean Tugas (*Job Queue*):** Untuk impor data Excel/CSV, pembuatan laporan besar, dan sinkronisasi integrasi eksternal (SATUSEHAT/BPJS).
- **Hosting:** Netral. Aplikasi dikemas dalam container (Docker) dengan konfigurasi lewat environment variables, sehingga bisa dijalankan di cloud mana pun atau di server lokal klien.

### 10.2 Multi-Tenancy
Satu database dengan kolom `tenant_id` di semua tabel operasional, dilindungi **Row-Level Security (RLS) PostgreSQL** sehingga bug di kode aplikasi tidak bisa membocorkan data antar-faskes. Klien besar yang meminta isolasi penuh dapat dipindah ke database sendiri tanpa mengubah kode. Pengaturan per tenant mencakup: jenis pembayaran, ambang stok, cara persetujuan override, template SOAP, dan tabel interaksi.

### 10.3 Model Data Inti (Fase 1 - Terintegrasi EMR)
- **Tenant & Pengguna:** `tenants`, `users` (role, nomor SIP/STR untuk tenaga medis, PIN opsional).
- **Pasien & Kunjungan:**
  - `patients` (No. RM, NIK terenkripsi, `nik_blind_index`, data demografi).
  - `patient_allergies` (relasi ke `substances` + catatan reaksi).
  - `encounters` (kunjungan pasien: waktu daftar, poli, dokter pemeriksa, `payment_type_id`, status antrean).
- **EMR / Rekam Medis & e-Prescription:**
  - `vital_signs` (relasi ke `encounters`: tensi sistolik/diastolik, suhu, BB, TB, nadi, RR, $SpO_2$).
  - `clinical_notes` (SOAP: *subjective, objective, assessment, plan*, status kunci, log addendum).
  - `icd10_masters` (kamus referensi ICD-10) & `encounter_diagnoses` (diagnosis primer/sekunder per kunjungan).
  - `medical_services` (master tindakan/jasa poli) & `encounter_services` (tindakan yang diberikan ke pasien).
  - `prescriptions` (header resep dari dokter, status: *Draft, Menunggu Verifikasi, Disiapkan, Diserahkan*).
  - `prescription_items` (obat yang diresepkan, kuantitas, satuan, signa/aturan pakai, catatan dokter).
- **Farmasi, Stok & Kasir:**
  - `substances` (zat aktif), `drugs` (termasuk kode KFA & satuan terkecil), `drug_substances` (komposisi), `drug_packagings` (konversi ke satuan terkecil), `drug_prices` (per jenis pembayaran).
  - `batches`, `stock_movements` (buku besar yang tidak bisa diubah, saldo dalam satuan terkecil per batch).
  - `interaction_rules` (zat aktif A, zat aktif B, tingkat keparahan, keterangan, saran; versi template dan versi per tenant).
  - `payment_types`.
  - `sales`, `sale_items` (mencakup item obat dengan alokasi batch & item jasa tindakan medis), `sale_returns` (status layak jual vs karantina/rusak).
  - `alert_overrides` (siapa, kapan, konteks resep/POS, alasan, aturan yang di-override).
  - `audit_logs`.

*Disiapkan untuk Fase 2:* `compounds` dan `compound_items` (komposisi obat racikan dan konversi kekuatan dosis mg).

### 10.4 Alur Checkout Atomik
Satu transaksi database yang atomik saat penyelesaian di kasir:
1. Validasi keranjang (resep poli maupun OTC) dan jalankan pengecekan alergi serta interaksi.
2. Bila ada peringatan berat yang belum disetujui, minta persetujuan apoteker dan simpan alasannya di `alert_overrides`.
3. Kunci batch terkait (`SELECT ... FOR UPDATE`), diurutkan menurut tanggal kedaluwarsa terdekat.
4. Alokasikan jumlah secara FEFO dalam satuan terkecil, bisa lintas batch.
5. Catat pergerakan stok di `stock_movements`, simpan transaksi `sales` dan `sale_items` (obat + tindakan), hitung nilai sesuai jenis pembayaran, dan ubah status `encounters` menjadi `COMPLETED`.
6. Commit transaksi database, lalu cetak struk pembayaran.

*Pengaman:* Kunci idempotensi (*idempotency key*) agar klik ganda tidak memotong stok dua kali, dan batasan database (`CHECK constraint`) yang melarang saldo stok negatif.

### 10.5 Keamanan & Blind Indexing
- Password di-hash (Argon2/bcrypt), MFA opsional untuk apoteker dan dokter, sesi pendek, ganti user cepat dengan PIN di komputer bersama (loket/farmasi).
- Enkripsi koneksi (TLS) dan enkripsi data diam (*encryption at rest*).
- **Enkripsi NIK dengan Blind Indexing:** Kolom sensitif seperti NIK dienkripsi menggunakan `AES-256`. Agar pencarian NIK tetap berjalan di bawah 1 detik, sistem menyimpan kolom `nik_blind_index` berupa hash satu arah `HMAC-SHA256` yang diindeks dengan B-Tree.
- Isolasi data antar-tenant dengan Row-Level Security (RLS) PostgreSQL.
- Audit log untuk setiap akses dan perubahan data rekam medis pasien.
-Pemisahan panel Admin Platform dari aplikasi operasional faskes.

### 10.6 Siap Integrasi SATUSEHAT
Integrasi SATUSEHAT dikerjakan lewat lapisan adapter terpisah di Fase 3. Sejak Fase 1, struktur database telah disejajarkan dengan standar HL7 FHIR Kemenkes: identitas NIK pasien dan tenaga kesehatan, entitas `encounters` (*Encounter*), kode diagnosis **ICD-10** (*Condition*), dan kode obat standar **KFA** (*MedicationRequest* & *MedicationDispense*).

---

## 11. UI/UX

### 11.1 Prinsip Desain
- **Cepat untuk pengguna rutin:** Dapat dipakai dengan keyboard dan barcode scanner di kasir, serta dilengkapi *quick-text template* SOAP dan fitur *"Salin Resep Kunjungan Terakhir"* di layar dokter agar pemeriksaan selesai di bawah 3 menit.
- **Cegah kesalahan klinis:** Kemasan selalu tampil jelas (strip atau tablet, kekuatan mg, kedaluwarsa) dan berat badan anak selalu tampil di dekat form resep.
- **Peringatan berjenjang:** Ringan berupa catatan kecil, sedang berupa banner kuning/oranye, berat berupa dialog modal merah yang menahan proses. Tingkat dibedakan dengan ikon dan label teks eksplisit, tidak hanya warna.
- **Halaman awal sesuai role:** Loket/kasir ke Pendaftaran & POS; Perawat & Dokter ke Antrean Poli & RME; Apoteker ke Antrean Resep Masuk, stok menipis, dan obat mendekati kedaluwarsa.

### 11.2 Struktur Navigasi
Beranda, Antrean & Pendaftaran, Ruang Periksa (RME), Farmasi & Kasir (POS), Obat & Stok, Pasien, Interaksi, Laporan, Pengaturan (pengguna, poli, tindakan, jenis pembayaran, harga). Panel Admin Platform terpisah.

### 11.3 Layar Kritis 1: Ruang Periksa Dokter (EMR Workspace)
Tata letak tiga kolom untuk layar desktop dan tablet lanskap:
- **Kolom Kiri (25% — Konteks Pasien):** Identitas pasien, usia, berat badan, **Banner Merah Alergi Obat**, tanda-tanda vital hari ini (diinput perawat), dan daftar riwayat kunjungan masa lalu (klik untuk melihat SOAP & resep terdahulu).
- **Kolom Tengah (45% — Pemeriksaan SOAP & ICD-10):** Form pengisian Subjective, Objective, pencarian cepat kode ICD-10 (Assessment), dan pemilihan tindakan medis poli.
- **Kolom Kanan (30% — e-Prescription & Peringatan Klinis):** Pencarian obat yang menampilkan **sisa stok aktual**, input jumlah dan aturan pakai (`3x1`), panel deteksi langsung alergi/interaksi obat, serta tombol **"Simpan & Kirim ke Farmasi"**.

### 11.4 Layar Kritis 2: Farmasi & Kasir (POS)
Tata letak tiga area:
- **Area Kiri:** Tab **"Antrean Resep Poli"** (dilengkapi badge jumlah resep baru dari dokter) dan tab pencarian/scan obat langsung (OTC) beserta stok dan kedaluwarsa.
- **Area Tengah:** Keranjang berisi item resep/obat dengan kemasan, aturan pakai (signa), batch yang dialokasikan secara FEFO, tombol **Cetak Etiket**, serta rincian jasa tindakan medis dari poli.
- **Area Kanan:** Identitas pasien, alergi, panel peringatan interaksi, pilihan jenis pembayaran, total tagihan, dan tombol **Bayar, Tahan, Batal**.

### 11.5 Arah Visual
Tenang dan klinis, bukan tampilan SaaS generik. Latar netral berwarna sejuk, satu warna aksen hijau kebiruan untuk aksi utama, dan warna status khusus untuk peringatan klinis. Satu keluarga huruf sans-serif yang jelas terbaca pada ukuran kecil dan angka.

---

## 12. Regulasi dan Kepatuhan

*Catatan awal dari penelusuran regulasi untuk dikonfirmasi lebih lanjut bersama konsultan hukum dan apoteker mitra:*

- **Permenkes No. 24 Tahun 2022 tentang Rekam Medis:** Mewajibkan seluruh fasilitas pelayanan kesehatan, termasuk puskesmas dan klinik, menyelenggarakan Rekam Medis Elektronik (RME).
- **Kewajiban Interoperabilitas:** Regulasi yang sama mewajibkan RME dapat berinteroperasi dengan platform **SATUSEHAT** Kemenkes.
- **UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP):** Menempatkan data kesehatan dan rekam medis pasien sebagai data pribadi spesifik/sensitif yang wajib dijaga keamanan, keutuhan, kerahasiaan, dan ketersediaannya.
- **Pelaporan Obat Terbatas:** Obat keras, psikotropika, dan narkotika memiliki aturan penyerahan dan pelaporan tersendiri (SIPNAP/pelaporan regulasi). Format laporan divalidasi bersama apoteker mitra.

---

## 13. Risiko dan Mitigasi

| Risiko | Mitigasi |
| :--- | :--- |
| **Tabel interaksi tidak lengkap atau salah** | Seeding awal dari referensi klinis terstandar yang divalidasi apoteker mitra, versi dan riwayat perubahan, serta penafian bahwa sistem adalah alat bantu klinis. |
| **Kesalahan hitung konversi atau dosis** | Penyimpanan saldo stok wajib dalam satuan terkecil (*base unit*), uji otomatis 100%, satuan eksplisit di setiap layar, dan verifikasi apoteker. |
| **Kebocoran data rekam medis antar-faskes** | Row-Level Security (RLS) PostgreSQL, enkripsi AES-256 + Blind Index, uji penetrasi, dan audit log. |
| **Puskesmas menuntut integrasi SATUSEHAT lebih cepat** | Adapter terpisah dan struktur data (ICD-10, KFA, Encounter) sudah disiapkan kompatibel sejak Fase 1. |
| **Data lama berantakan saat impor** | Pratinjau impor, validasi per baris, laporan kesalahan, dan pendampingan onboarding. |
| **Dokter merasa input RME memperlambat pelayanan** | Tanda vital diinput lebih dulu oleh perawat, tersedia *quick-text template* SOAP, dan fitur salin resep kunjungan sebelumnya. |

---

## 14. Pertanyaan Terbuka dan Langkah Berikutnya

### Pertanyaan Terbuka
1. Nama resmi produk dan model harga langganan per tier faskes.
2. Siapa apoteker mitra dan dokter penasihat yang mengkurasi template interaksi, template SOAP, dan memvalidasi laporan obat terbatas.
3. Konfirmasi cakupan poli di Fase 1 (disarankan mengunci fokus pada Poli Umum terlebih dahulu, sementara Odontogram Poli Gigi masuk di Fase 2).
4. Kebijakan retensi dan penghapusan data rekam medis pasien sesuai regulasi.
5. Penentuan lokasi hosting cloud/server produksi di Indonesia.

### Langkah Berikutnya
1. Review dokumen PRD v0.2 ini bersama atasan, dokter penasihat, dan apoteker mitra.
2. Desain visual: pembuatan wireframe layar kritis (Ruang Periksa Dokter 3 Kolom & Layar Farmasi/POS) serta sistem komponen.
3. Penyusunan skema database rinci (`Schema.md`), penyiapan data master ICD-10 & KFA, serta kontrak API.
4. Rencana sprint Fase 1 dan pembuatan prototipe interaktif berdasarkan wireframe yang disetujui.