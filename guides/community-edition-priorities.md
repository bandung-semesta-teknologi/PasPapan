# Prioritas Pengembangan Community Edition

Ditetapkan: 17 November 2026.

## Keputusan cakupan

Rilis awal Community Edition memprioritaskan empat area dari [daftar fitur enterprise](./enterprise-features-and-usage.md): **4. Analytics absensi**, **5. Appraisal dan KPI**, **7. Impor/ekspor karyawan**, serta **8. Impor/ekspor absensi dan laporan**.

Keempat area ditargetkan tersedia dengan source terbuka berlisensi MIT, tanpa lisensi enterprise, deployment key, maupun runtime terenkripsi. RBAC, policy, pembatasan company, dan perlindungan file tetap menjadi bagian implementasi.

Urutan pengerjaan mengikuti urutan prioritas yang dipilih: **4 → 5 → 7 → 8**. Fondasi bersama impor/ekspor dikerjakan pada tahap 7 dan dipakai kembali pada tahap 8.

Dokumen ini menetapkan prioritas dan backlog. Implementasi empat fitur belum selesai; progres pekerjaan dicatat pada checklist di bawah. Perubahan binding service community dari pekerjaan sebelumnya belum menyediakan fungsi reporting karena implementasi reporting community saat ini masih mengembalikan `null`.

## Urutan rilis dan hasil yang diharapkan

| Tahap | Referensi | Hasil yang harus bisa digunakan | Ketergantungan utama |
| --- | --- | --- | --- |
| CE-1 | 4 | Admin melihat ringkasan dan tren absensi dengan filter periode | Data absensi, scope pengguna, permission analytics |
| CE-2 | 5 | HR mengatur KPI, karyawan mengisi penilaian, penilai meninjau hasil | Master KPI, periode penilaian, policy dan perhitungan skor |
| CE-3 | 7 | Admin mengimpor/memperbarui karyawan dan mengekspor data melalui background job | Validasi spreadsheet, run tracking, worker, private storage |
| CE-4 | 8 | Admin mengimpor/mengekspor absensi dan mengunduh laporan periode | Fondasi CE-3, filter absensi, penanganan shift lintas tanggal, PDF |

Satu tahap dinyatakan selesai ketika alur pengguna, akses tanpa key enterprise, pembatasan data, dan test penerimaannya lulus. Menampilkan menu atau melepas feature lock saja belum memenuhi target.

## CE-1 — Analytics absensi

### Cakupan awal

- Halaman `/admin/analytics` menggunakan komponen dan service terbaca.
- Filter bulan/tahun dengan nilai yang divalidasi dan periode yang konsisten.
- Ringkasan status absensi berdasarkan data aktual: hadir, terlambat, dan kategori lain yang didukung model.
- Tren harian untuk periode terpilih dan kondisi kosong ketika belum ada data.
- Ringkasan dibatasi ke karyawan yang boleh dilihat operator.
- Menu analytics tersedia berdasarkan permission; akses tidak diputuskan oleh lisensi.

Definisi metrik harus dicatat sebelum implementasi. Khususnya, jangan menghitung semua hari kalender sebagai hari kerja atau menganggap tidak adanya record pasti berarti mangkir tanpa mempertimbangkan roster, hari libur, dan masa kerja.

### Pekerjaan

- [x] Petakan data yang dibutuhkan template analytics yang ada. Hasil: [pemetaan data analytics CE](./community-analytics-data-map.md).
- [x] Sediakan service agregasi terbaca untuk ringkasan record bulanan dengan filter periode dan scope pengguna/company. Definisi attendance rate dan kalender kerja masih menunggu keputusan metrik.
- [x] Ganti route ke komponen Livewire Community yang terbaca; layar CE tidak memakai komponen analytics terenkripsi.
- [x] Lepas entitlement analytics dari gate/menu/route analytics; permission `admin.analytics.view` tetap diwajibkan.
- [x] Filter CE mempertahankan nilai periode `month`, `year`, dan `period`. Method/event chart enterprise tidak dipakai oleh tampilan CE yang baru.
- [ ] Ubah test analytics agar berjalan tanpa helper aktivasi enterprise.
- [ ] Dokumentasikan arti setiap metrik dan penanganan data kosong.

### Kriteria penerimaan

- Admin berizin dapat membuka halaman tanpa key enterprise; pengguna tanpa izin ditolak.
- Mengganti bulan/tahun mengubah agregasi dan grafik sesuai data periode tersebut.
- Data company lain tidak masuk agregasi admin company.
- Nilai periode yang tidak valid ditolak atau dinormalisasi secara konsisten.
- Tidak ada pemuatan kelas secured melalui jalur halaman dan service analytics.

Sumber awal: [route dashboard](../routes/web/admin/dashboard.php), [test filter analytics](../tests/Feature/AnalyticsDashboardFilterTest.php), [OperationalKpiService](../app/Support/OperationalKpiService.php).

## CE-2 — Appraisal dan KPI

### Cakupan awal

- Pengaturan grup KPI, indikator, bobot, status aktif, dan jendela periode penilaian.
- Inisialisasi appraisal per karyawan dan periode tanpa membuat duplikat.
- Self-assessment karyawan dan penilaian manager/HR sesuai kewenangan.
- Perhitungan skor dengan aturan dan skala yang didokumentasikan.
- Review/kalibrasi, penyelesaian penilaian, serta acknowledgement sesuai workflow yang ditetapkan.
- Halaman hasil pribadi dan PDF melalui policy.

Bobot kehadiran dan KPI perlu memiliki aturan validasi yang jelas. Perubahan template juga perlu dibedakan dari evaluasi historis agar hasil penilaian lama tidak berubah diam-diam.

### Pekerjaan

- [ ] Tetapkan transisi status dan peran pelaksana untuk setiap aksi.
- [ ] Tulis ulang `AppraisalService` dan komponen admin dalam source terbaca.
- [ ] Tinjau kontrak yang digunakan `MyPerformance` serta ekspor PDF.
- [ ] Pertahankan data/model yang ada; gunakan migrasi tambahan hanya jika diperlukan.
- [ ] Tambahkan otorisasi pada setiap aksi mutasi KPI dan appraisal, termasuk request Livewire langsung.
- [ ] Terapkan company scope pada view, evaluasi, kalibrasi, dan PDF.
- [ ] Validasi skor, bobot, periode, batas waktu, dan kepemilikan evaluation ID di server.
- [ ] Lepas entitlement appraisal dari route, helper, gate, dan policy yang relevan.
- [ ] Uji alur self-assessment hingga selesai tanpa key enterprise.

### Kriteria penerimaan

- HR berizin dapat mengelola KPI dan membuat appraisal untuk karyawan dalam scope.
- Karyawan hanya mengisi dan melihat appraisal miliknya sesuai status/periode.
- Penilai hanya dapat mengubah data yang menjadi kewenangannya.
- Inisialisasi ulang appraisal tidak menggandakan appraisal/evaluation.
- Skor dihitung konsisten, termasuk ketika KPI tidak aktif atau data absensi kosong.
- Akses lintas company, skor di luar rentang, dan transisi status tidak sah ditolak.
- PDF dan hasil akhir cocok dengan data appraisal yang tersimpan.

Sumber awal: [model appraisal](../app/Models/Appraisal.php), [evaluation](../app/Models/AppraisalEvaluation.php), [pengaturan KPI](../app/Livewire/Admin/Settings/KpiSettings.php), [self-service performa](../app/Livewire/User/MyPerformance.php), [policy appraisal](../app/Policies/AppraisalPolicy.php).

## CE-3 — Impor dan ekspor karyawan

### Cakupan awal

- Halaman `/admin/import-export/users`, template impor, upload tervalidasi, dan pilihan ekspor.
- Pembuatan atau pembaruan karyawan melalui pencocokan identitas yang konsisten.
- Validasi konflik ID/NIP/email/telepon dan referensi organisasi/manager.
- Hasil per baris, ringkasan berhasil/gagal, dan file keluaran di storage privat.
- Proses background dengan status queued, running, completed, failed dan progres.
- Unduhan terproteksi serta retensi file/run yang konsisten dengan konfigurasi yang digunakan.

### Fondasi bersama untuk CE-3 dan CE-4

- [ ] Implementasikan service antrean run dan job terbaca untuk resource yang diprioritaskan.
- [ ] Gunakan model `ImportExportRun` yang sudah ada, termasuk waktu eksekusi dan detail kegagalan.
- [ ] Buat pemeriksaan spreadsheet yang terbaca untuk format, header, ukuran, dan batas baris.
- [ ] Pastikan worker membawa identitas/scope peminta dan memeriksa ulang akses sebelum memproses data.
- [ ] Tetapkan perilaku retry agar tidak menggandakan mutasi impor atau hasil run yang sudah selesai.
- [ ] Sediakan penanganan kegagalan job dan pembersihan file sementara.
- [ ] Pastikan daftar run dan unduhan tidak membocorkan data lintas company.
- [ ] Gunakan kebijakan retensi existing secara konsisten di daftar run, scheduler, dan dokumentasi.

### Pekerjaan khusus karyawan

- [ ] Audit dan gunakan kembali `UsersImport`, `UsersExport`, dan `UsersTemplateExport` yang terbaca.
- [ ] Batasi impor role/group, company, gaji, serta referensi manager sesuai kewenangan operator.
- [ ] Cegah file impor mengambil alih akun admin/superadmin atau memperbarui pengguna lintas company.
- [ ] Ganti komponen halaman, service, dan job users yang masih terenkripsi.
- [ ] Lepas pemeriksaan runtime/entitlement reporting pada controller dan gate users yang relevan.
- [ ] Hubungkan template, proses impor/ekspor, progres, error baris, dan unduhan.
- [ ] Uji konflik identitas, campuran baris valid/tidak valid, retry, dan batas akses.

### Kriteria penerimaan

- Admin berizin mengunduh template, mengunggah file, dan melihat hasil run tanpa key enterprise.
- Baris valid menghasilkan data sesuai aturan; baris tidak valid memiliki alasan yang dapat ditindaklanjuti.
- Pembaruan tidak menggandakan identitas atau mengubah pengguna di luar scope operator.
- Ekspor hanya memuat pengguna/kolom yang boleh diakses dan tidak menyertakan secret/password.
- Gagal dispatch, gagal worker, dan retry menghasilkan status run yang dapat dipahami.
- File hasil tidak dapat diunduh melalui URL publik atau pengguna tanpa permission.

Sumber awal: [UsersImport](../app/Imports/UsersImport.php), [UsersExport](../app/Exports/UsersExport.php), [model run](../app/Models/ImportExportRun.php), [policy unduhan](../app/Policies/ImportExportRunPolicy.php), [retensi run](../app/Support/ImportExportRunRetention.php).

## CE-4 — Impor/ekspor absensi dan laporan

### Cakupan awal

- Halaman `/admin/import-export/attendances`, template absensi, impor dan ekspor.
- Filter periode serta referensi organisasi yang konsisten antara halaman dan file hasil.
- Impor datetime lengkap untuk check-in/check-out, termasuk shift malam lintas tanggal.
- Laporan absensi PDF dan spreadsheet melalui proses background.
- Progres, error per baris, unduhan, keamanan file, dan retensi memakai fondasi CE-3.

### Pekerjaan

- [ ] Audit dan gunakan kembali `AttendancesImport`, export absensi, dan template yang terbaca.
- [ ] Tetapkan aturan duplikasi record absensi dan perilaku pembaruan/retry.
- [ ] Validasi employee lookup terhadap company/scope peminta.
- [ ] Tetapkan parsing tanggal/waktu dan timezone secara eksplisit; tangani shift malam.
- [ ] Tulis ulang job import, export, dan monthly report tanpa runtime secured.
- [ ] Implementasikan fungsi reporting community yang dibutuhkan; jangan biarkan method mengembalikan `null` untuk alur yang dinyatakan tersedia.
- [ ] Lepas entitlement reporting pada route/controller/gate yang terkait absensi.
- [ ] Gunakan satu definisi filter dan data untuk ekspor spreadsheet serta PDF.
- [ ] Uji kegagalan parsial, data kosong, volume data representatif, dan company isolation.

### Kriteria penerimaan

- Impor shift lintas tengah malam menyimpan tanggal/jam masuk-keluar dengan benar.
- Record invalid tidak merusak data valid; duplikasi ditangani menurut aturan terdokumentasi.
- Ekspor dan PDF cocok dengan periode, filter, dan scope yang dipilih.
- File PDF/spreadsheet terbentuk dan dapat dibuka setelah run selesai.
- Daftar/unduhan run menolak akses lintas company dan pengguna tanpa izin.
- Worker menyelesaikan run dengan progres dan status akhir yang benar tanpa key enterprise.

Sumber awal: [AttendancesImport](../app/Imports/AttendancesImport.php), [AttendancesExport](../app/Exports/AttendancesExport.php), [template absensi](../app/Exports/AttendanceTemplateExport.php), [route laporan](../routes/web/admin/reports.php), [kontrak reporting](../app/Contracts/ReportingServiceInterface.php).

## Penyesuaian cutover dan modul lain

- Kode terenkripsi yang mendukung empat prioritas diganti bertahap dengan implementasi terbaca setelah alur penggantinya lulus test.
- Halaman, model, tabel, dan data yang dibutuhkan empat prioritas tidak dihapus dalam pembersihan enterprise umum.
- Analytics, appraisal, dan reporting pada helper/gate perlu dipisahkan dari lisensi. Pemeriksaan modul lain tidak dibuka secara global.
- Service run bersama dapat masih memiliki pemanggil enterprise di luar cakupan. Penggantian wajib meninjau pemanggil tersebut agar tidak mengklaim fitur lain sudah tersedia.
- Payroll, kasbon, aset, POS, backup/restore, dan modul enterprise lainnya tidak masuk prioritas implementasi rilis pertama ini. Status kelanjutannya ditentukan terpisah.
- Penghapusan data lama, riwayat migrasi, dan deployment produksi bukan bagian penetapan prioritas ini.

## Gerbang penyelesaian rilis awal

- [ ] Keempat alur berjalan dari instalasi community tanpa key/lisensi enterprise.
- [ ] Tidak ada runtime terenkripsi yang dipanggil oleh keempat alur beserta unduhan/job-nya.
- [ ] Test fitur yang diprioritaskan berjalan tanpa skip karena key enterprise tidak tersedia.
- [ ] Permission, ownership, company isolation, dan input tidak valid diuji.
- [ ] Route registration, kompilasi view, serta pemeriksaan format kode lulus.
- [ ] Worker/scheduler, private storage, dan retensi terdokumentasi sesuai implementasi.
- [ ] Dokumentasi fitur diperbarui dengan status implementasi nyata dan cara penggunaan CE.
