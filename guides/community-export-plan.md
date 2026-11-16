# Breakdown Pekerjaan: Ekspor Community untuk Pegawai dan Absensi

## Hasil akhir

Menyediakan ekspor Excel pegawai dan absensi bagi admin Community. Pengguna memilih filter, mengajukan ekspor, melihat status proses, lalu mengunduh file yang selesai. Proses memakai antrean, riwayat tersimpan, unduhan privat, serta pembersihan file kedaluwarsa.

Cakupan versi ini hanya ekspor pegawai dan absensi. Import, PDF, payroll, audit log, dan ekspor modul lain tidak termasuk.

## Fondasi dan rancangan

- Gunakan kembali library Excel, model data, relasi, dan pemetaan kolom yang sudah ada pada exporter pegawai dan absensi.
- Buat halaman, controller, service pengatur proses, dan job Community tersendiri. Padanan Enterprise saat ini terlindungi.
- Gunakan tabel dan model `ImportExportRun` yang ada; tidak perlu migrasi baru.
- Bedakan proses melalui nilai resource `community_users` dan `community_attendances`, dengan operation `export`.
- Simpan filter yang sudah divalidasi dalam metadata proses dan simpan identitas pemohon pada kolom pemohon.
- Pertahankan alur Enterprise dan import yang sudah ada.

Antarmuka baru:

| Metode | Endpoint | Fungsi |
|---|---|---|
| GET | `/admin/community-exports/users` | Halaman ekspor pegawai dan riwayat |
| POST | `/admin/community-exports/users` | Mengantrekan ekspor pegawai |
| GET | `/admin/community-exports/attendances` | Halaman ekspor absensi dan riwayat |
| POST | `/admin/community-exports/attendances` | Mengantrekan ekspor absensi |

Unduhan memakai endpoint hasil ekspor yang sudah tersedia, dengan aturan policy tambahan bagi kedua resource Community.

## Daftar pekerjaan

### 1. Hak akses dan cakupan data

- [ ] Tambahkan gate Community yang memeriksa permission ekspor yang sudah ada dan tidak bergantung pada lisensi Enterprise.
- [ ] Wajibkan login, akses panel admin, dan permission yang sesuai pada halaman serta endpoint pengajuan.
- [ ] Terapkan scope `managedBy` pada query agar ekspor mengikuti batas perusahaan dan wilayah pengguna.
- [ ] Batasi pilihan filter pada data yang dapat diakses pemohon.
- [ ] Periksa kembali akun dan permission saat job mulai diproses.
- [ ] Batasi daftar riwayat dan unduhan kepada pemohon yang memiliki akses yang sesuai.
- [ ] Tolak unduhan jika akses perusahaan pemohon berubah sejak proses diajukan.

### 2. Pembentuk Excel dan validasi

- [ ] Buat exporter Community dengan pemetaan kolom yang sudah ada dan query dengan scope pemohon.
- [ ] Untuk pegawai, ekspor akun kelompok `user`; akun admin dan superadmin tidak termasuk.
- [ ] Sediakan filter pegawai: divisi, jabatan, dan pendidikan.
- [ ] Sediakan filter absensi: tanggal mulai/akhir, divisi, jabatan, dan pendidikan.
- [ ] Validasi filter melalui Form Request; tanggal akhir harus sama dengan atau setelah tanggal awal.
- [ ] Tulis NIP dan nomor telepon sebagai teks agar nol di depan tetap ada.
- [ ] Tulis nilai teks pengguna sebagai teks spreadsheet agar tidak dievaluasi sebagai formula.
- [ ] Gunakan eager loading dan pemrosesan bertahap agar ekspor besar tidak memuat seluruh dataset ke memori.
- [ ] Hasil tanpa data tetap menghasilkan file Excel yang valid dengan judul kolom.

### 3. Antrean dan siklus proses

- [ ] Tambahkan service Community untuk membuat record proses dan mengirim job setelah record tersimpan.
- [ ] Buat job terpisah untuk ekspor pegawai dan absensi menggunakan koneksi antrean aplikasi.
- [ ] Terapkan status `queued`, `running`, `completed`, dan `failed`.
- [ ] Tampilkan progres berdasarkan tahap proses; jangan mengklaim progres jumlah baris bila tidak diukur.
- [ ] Simpan file pada disk privat `local` menggunakan path unik per proses.
- [ ] Simpan nama file, path, MIME type, ukuran, jumlah baris, dan waktu penyelesaian.
- [ ] Cegah dua worker memproses record yang sama secara bersamaan.
- [ ] Tetapkan timeout job 60 detik dan pastikan `retry_after` koneksi antrean lebih panjang; konfigurasi database saat ini 90 detik.
- [ ] Tangani exception, timeout, dan percobaan ulang; hapus file parsial sebelum pengulangan.
- [ ] Tampilkan pesan gagal yang ringkas dan catat detail teknis melalui logging.
- [ ] Sediakan pengajuan ulang melalui formulir untuk proses yang gagal.

### 4. Halaman dan navigasi

- [ ] Tambahkan tautan “Ekspor Community” pada navigasi sesuai permission pengguna.
- [ ] Buat halaman pegawai dan absensi memakai komponen UI proyek.
- [ ] Sediakan formulir filter, tombol pengajuan, validasi, dan notifikasi ketika proses masuk antrean.
- [ ] Cegah pengiriman ganda saat permintaan sedang diproses.
- [ ] Tampilkan delapan proses terbaru milik pemohon.
- [ ] Perbarui status setiap lima detik selama masih ada proses aktif.
- [ ] Tampilkan status, waktu pengajuan, jumlah baris, nama file, dan pesan kegagalan.
- [ ] Aktifkan unduhan hanya untuk proses selesai dengan file yang tersedia.
- [ ] Tambahkan seluruh teks UI melalui sistem terjemahan aplikasi.

### 5. Unduhan dan retensi

- [ ] Tambahkan resource Community pada policy unduhan.
- [ ] Gunakan pemeriksaan path dan mekanisme unduhan privat yang sudah ada.
- [ ] Tolak unduhan untuk proses belum selesai, file hilang, atau akses yang tidak sah.
- [ ] Gunakan retensi 12 jam yang sudah tersedia untuk menghapus file dan record hasil selesai/gagal yang kedaluwarsa.
- [ ] Pastikan riwayat tidak menampilkan tautan unduhan untuk file kedaluwarsa.
- [ ] Pastikan scheduler pembersihan mencakup resource Community.

## Pengujian dan kriteria penerimaan

- [ ] Halaman, pengajuan, job, dan unduhan berjalan tanpa lisensi atau kunci runtime Enterprise.
- [ ] Pengguna tanpa permission ditolak, termasuk melalui akses URL langsung.
- [ ] Admin perusahaan A tidak dapat mengekspor atau mengunduh data perusahaan B.
- [ ] Scope wilayah dan akses superadmin mengikuti aturan aplikasi.
- [ ] Filter menghasilkan baris yang sesuai; tanggal atau kombinasi filter yang tidak valid ditolak.
- [ ] Verifikasi isi file: judul kolom, tanggal, NIP, nomor telepon, dan teks menyerupai formula.
- [ ] Ekspor tanpa hasil menghasilkan file valid.
- [ ] Kegagalan penyimpanan dan percobaan ulang tidak menampilkan status sukses atau file parsial.
- [ ] Perubahan permission atau perusahaan pemohon ditangani saat pemrosesan dan unduhan.
- [ ] Retensi menghapus file dan record yang telah kedaluwarsa.
- [ ] Jalankan uji antrean dengan worker dan dataset contoh 10.000 baris; catat durasi serta penggunaan memori.
- [ ] Pastikan alur import dan ekspor Enterprise yang ada tidak mengalami regresi.
- [ ] Jalankan pengujian terkait serta pemeriksaan format, UI, dan enterprise boundary.

Definisi selesai: admin berizin dapat mengajukan kedua ekspor, melihat proses selesai, dan mengunduh Excel sesuai cakupan akses pada instalasi Community tanpa kunci Enterprise.

## Dokumentasi dan operasional

- [ ] Dokumentasikan endpoint, permission, filter, kolom hasil, dan batas versi pertama.
- [ ] Dokumentasikan kebutuhan queue worker, scheduler, disk privat, serta retensi 12 jam.
- [ ] Tambahkan panduan untuk proses tertahan/gagal dan file yang hilang.
- [ ] Lakukan smoke test staging sebelum rilis dan periksa log serta antrean gagal.

## Estimasi awal

Perkiraan satu engineer Laravel: **4–6 hari kerja**, termasuk implementasi, pengujian, dan dokumentasi. Estimasi ini lebih besar dari alur unduh langsung karena mencakup halaman Community, pengaturan antrean, status, riwayat, dan unduhan. Validasi akhir bergantung pada uji runtime dan volume data.
