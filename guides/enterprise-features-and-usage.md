# Daftar Fitur Enterprise dan Cara Penggunaannya

Tanggal inventarisasi: 17 November 2026.

Dokumen ini mencatat fitur enterprise pada repository PasPapan sebagai referensi sebelum pemisahan Community Edition. Cakupan ditentukan dari konfigurasi RBAC, route, pemeriksaan lisensi, template UI, dokumentasi, dan skenario test yang tersedia. Alur di bawah adalah panduan berdasarkan sumber tersebut; belum merupakan hasil uji operasional seluruh modul pada deployment aktif.

## Prioritas pengembangan Community Edition

Fitur **4, 5, 7, dan 8** diprioritaskan untuk implementasi terbuka pada rilis awal CE, dengan urutan tersebut. Keputusan ini menetapkan arah pengembangan dan belum berarti fiturnya selesai tersedia tanpa runtime enterprise. Lihat [backlog dan kriteria penerimaan CE](./community-edition-priorities.md).

## Status dan batas pembacaan

- Runtime enterprise masih ada dalam repository. Sebagian implementasi berbentuk PHP terenkripsi sehingga detail internalnya tidak dapat diverifikasi dari source terbaca.
- Persiapan Community Edition sudah mengubah binding attendance, payroll, reporting, dan audit ke implementasi community. Karena itu, alur enterprise yang didokumentasikan di sini tidak otomatis aktif pada kondisi working tree saat ini.
- Penanda `enterprise` di RBAC tidak selalu sama dengan pembatasan route atau ketergantungan runtime. Dokumen ini mencatat ketiganya agar keputusan pelepasan modul tidak hanya mengandalkan label menu.
- “Bergantung runtime” berarti halaman atau service memerlukan artifact enterprise, meskipun pemeriksaan entitlement lisensi spesifik mungkin tidak dipakai.

## Ringkasan fitur

| No. | Fitur | Pengguna utama | Lokasi akses | Bukti keterkaitan enterprise |
| --- | --- | --- | --- | --- |
| 1 | Payroll dan slip gaji | HR, finance, karyawan | `/admin/payrolls`, `/payroll` | Entitlement `payroll`, route enterprise, runtime terenkripsi |
| 2 | Pengaturan payroll | HR, finance | `/admin/payrolls/settings` | Entitlement `payroll`, RBAC enterprise, runtime terenkripsi |
| 3 | Kasbon dan kasbon tim | Karyawan, atasan, finance | `/my-kasbon`, `/team-kasbon`, `/admin/manage-kasbon` | Entitlement `cash_advance`, route enterprise, runtime terenkripsi |
| 4 | Analytics absensi | HR, manajemen | `/admin/analytics` | Entitlement `analytics`, RBAC enterprise, runtime terenkripsi |
| 5 | Appraisal dan KPI | HR, atasan, karyawan | `/admin/appraisals`, `/admin/settings/kpi`, `/my-performance` | Entitlement `appraisal`, RBAC enterprise, runtime terenkripsi |
| 6 | Aset perusahaan | Admin aset, karyawan | `/admin/assets`, `/my-assets` | Entitlement `asset_management` pada helper; route memakai label `assets` |
| 7 | Impor/ekspor karyawan | HR, IT | `/admin/import-export/users` | Entitlement `reporting`, runtime background terenkripsi |
| 8 | Impor/ekspor absensi dan laporan PDF | HR, IT | `/admin/import-export/attendances`, `/admin/reports/export-pdf` | Entitlement `reporting`, runtime background terenkripsi |
| 9 | Audit trail dan ekspor log | IT, auditor, admin | `/admin/activity-logs` | Route memakai `audit`; service audit enterprise terenkripsi |
| 10 | Dokumen karyawan dan template | HR, karyawan | `/admin/document-requests`, `/admin/document-templates`, `/document-requests` | RBAC enterprise dan runtime terenkripsi; entitlement dokumen sudah dilepas pada alur tertentu |
| 11 | Checklist onboarding/offboarding | HR, atasan, karyawan | `/admin/hr-checklists`, `/hr-tasks` | Halaman admin memakai route enterprise; sebagian workflow terbaca |
| 12 | Backup, restore, dan maintenance | IT, administrator | `/admin/system-maintenance` | Runtime terenkripsi; helper backup memeriksa `system_backup` |
| 13 | Operational health | IT, administrator | `/admin/operational-health` | Controller terenkripsi dan fallback ketika runtime tidak tersedia |
| 14 | Pengelolaan sesi dan API token | IT, administrator | `/admin/user-sessions` | RBAC non-enterprise, tetapi route memakai `security` yang dipetakan ke pemeriksaan audit |
| 15 | Pengelolaan integrasi API | IT, administrator | `/admin/api-integrations` | RBAC non-enterprise, tetapi route memakai `security` dan komponen terenkripsi |
| 16 | Add-on Toko/POS | Kasir, gudang, purchasing, finance, owner | `/admin/toko` dan subhalamannya | Entitlement `toko_pos`, runtime add-on dan RBAC tersendiri |
| 17 | Administrasi lisensi enterprise | Administrator berizin | `/admin/settings`, tab Enterprise | Lisensi offline, hardware ID, dan konfigurasi runtime |

## Prasyarat penggunaan versi enterprise

Pada desain enterprise sebelum cutover:

1. Administrator menyiapkan deployment dengan artifact enterprise dan deployment key yang cocok dengan build artifact.
2. Lisensi dimasukkan melalui **Admin > Settings > Enterprise**, kemudian diterapkan oleh pengguna dengan izin `admin.settings.license`. Konfigurasi environment juga didukung sebagai fallback.
3. Lisensi harus mencakup fitur yang dibutuhkan. Lisensi untuk satu fitur tidak otomatis membuka semua modul. Payload dapat memuat daftar fitur atau wildcard `*`.
4. Administrator memberikan role dan permission aksi kepada operator. Lisensi dan role memiliki fungsi berbeda: lisensi membuka kemampuan modul, sedangkan permission membatasi pengguna dan tindakannya.
5. Company scope harus sesuai dengan data yang diakses. Untuk proses background, queue worker dan scheduler perlu berjalan serta storage privat dapat ditulis.

Jika runtime atau entitlement tidak memenuhi pemeriksaan, route dapat menampilkan penguncian fitur atau mengembalikan respons JSON 423. Perilaku tepatnya bergantung pada route, middleware, dan policy modul tersebut.

Sumber: [LicenseGuard](../app/Services/Enterprise/LicenseGuard.php), [helper edisi](../app/Helpers/Editions.php), [middleware penguncian](../app/Http/Middleware/RedirectLockedEnterpriseFeature.php), [enterprise packaging](./enterprise-packaging.md).

## 1. Payroll dan slip gaji

**Kegunaan:** menyiapkan gaji per periode, meninjau komponen penghasilan/potongan, menerbitkan payroll, menandai pembayaran, dan menyediakan slip bagi karyawan.

**Cara penggunaan:**

1. HR/finance menyiapkan data gaji karyawan dan aturan melalui pengaturan payroll.
2. Buka `/admin/payrolls`, pilih periode, lalu jalankan pembuatan/perhitungan payroll melalui UI.
3. Tinjau hasil per karyawan sebelum menerbitkannya. Template UI dan test mencakup aksi publish serta penandaan paid, termasuk aksi massal untuk status yang memenuhi syarat.
4. Setelah pembayaran, karyawan mengakses slip melalui `/payroll`.
5. Untuk pengiriman PDF slip melalui email, karyawan perlu mengatur password slip. Test mencakup PDF terenkripsi ketika password tersedia dan permintaan pengaturan password ketika belum tersedia.

Cakupan produk menyebut payroll bulanan/harian, BPJS, PPh21/Coretax, THR, dan payment instruction. Ketersediaan setiap perhitungan pada jalur UI perlu diuji tersendiri; keberadaan service perhitungan tidak membuktikan semua skenario otomatis aktif di layar payroll.

**Izin utama:** `admin.payroll.view`; pengaturan memakai `admin.payroll_settings.manage`. Slip karyawan mengikuti policy kepemilikan.

Sumber: [route payroll](../routes/web/payroll.php), [UI payroll](../resources/views/livewire/admin/payroll-manager.blade.php), [test status payroll dan slip](../tests/Feature/PayrollStatusActionTest.php), [test kalkulator Indonesia](../tests/Feature/IndonesiaPayrollCalculatorTest.php).

## 2. Pengaturan payroll

**Kegunaan:** mengelola aturan dan komponen yang dipakai dalam persiapan gaji.

**Cara penggunaan:** buka `/admin/payrolls/settings` dengan role finance/HR yang memiliki izin pengaturan, atur komponen dan aturan yang tersedia pada form, simpan, lalu periksa hasilnya pada payroll periode berikutnya. Hindari menganggap perubahan aturan akan menghitung ulang payroll lama secara otomatis tanpa menjalankan alur yang disediakan UI.

Sumber: [route payroll](../routes/web/payroll.php), [UI pengaturan payroll](../resources/views/livewire/admin/payroll-settings.blade.php).

## 3. Kasbon dan persetujuan finance

**Kegunaan:** pengajuan pinjaman/kasbon karyawan, review atasan, tindak lanjut finance, dan pemantauan kewajiban.

**Cara penggunaan:**

1. Karyawan membuka `/my-kasbon`, mengisi pengajuan, lalu mengirimkannya. Test mencakup pengajuan dalam batas yang terkait gaji.
2. Atasan yang berhak meninjau bawahan membuka `/team-kasbon` atau alur approval tim.
3. Finance membuka `/admin/manage-kasbon` untuk menindaklanjuti pengajuan sesuai status dan kewenangan.
4. Pantau daftar dan ringkasan kasbon. Skenario test memasukkan status menunggu finance ke total yang belum lunas.

Alur approval dapat memakai direct manager dan approval matrix sesuai konfigurasi. Detail pencairan atau pelunasan harus mengikuti aksi yang tersedia pada UI karena service kasbon utama terenkripsi.

**Izin utama:** `admin.cash_advances.manage`; akses review tim memakai gate `reviewSubordinateRequests`.

Sumber: [route karyawan](../routes/web/user.php), [route finance admin](../routes/web/admin/payroll.php), [test kasbon](../tests/Feature/MyCashAdvancesTest.php), [panduan fitur](./features.md).

## 4. Analytics absensi

**Kegunaan:** meninjau analitik kehadiran dan tren operasional per periode.

**Cara penggunaan:** buka `/admin/analytics`, pilih bulan dan tahun, lalu tinjau ringkasan serta grafik yang ditampilkan. Gunakan hasilnya untuk menemukan tren dan menentukan data yang perlu ditinjau lebih lanjut; bukan sebagai pengganti pemeriksaan detail absensi.

**Izin utama:** `admin.analytics.view` dan entitlement `analytics`.

Sumber: [route dashboard](../routes/web/admin/dashboard.php), [test filter analytics](../tests/Feature/AnalyticsDashboardFilterTest.php).

## 5. Appraisal dan KPI

**Kegunaan:** konfigurasi indikator penilaian, evaluasi karyawan, kalibrasi hasil, dan akses hasil performa pribadi.

**Cara penggunaan:**

1. HR mengatur indikator dan bobot di `/admin/settings/kpi`.
2. Buka `/admin/appraisals`, pilih karyawan/periode melalui kontrol yang tersedia, lalu mulai evaluasi.
3. Isi penilaian dan simpan. UI menyediakan aksi kalibrasi approved/rejected.
4. Karyawan melihat hasil yang diizinkan melalui `/my-performance`; ekspor PDF tersedia melalui route `/appraisal/{appraisal}/export-pdf` dengan policy khusus.

**Izin utama:** `admin.appraisals.view`, `admin.appraisals.manage`, dan `admin.kpi_settings.manage` sesuai aksi.

Sumber: [route aset/appraisal](../routes/web/admin/assets.php), [route pengaturan KPI](../routes/web/admin/settings.php), [UI appraisal](../resources/views/livewire/admin/appraisal-manager.blade.php), [route karyawan](../routes/web/user.php).

## 6. Lifecycle aset perusahaan

**Kegunaan:** mendata aset, menugaskannya kepada karyawan, dan mencatat pengembalian atau penarikan kembali.

**Cara penggunaan:**

1. Admin aset membuka `/admin/assets`, menyiapkan data aset, lalu menetapkan karyawan pemegangnya.
2. Karyawan membuka `/my-assets` untuk melihat aset yang menjadi tanggung jawabnya.
3. Saat pengembalian, ikuti alur OTP yang disediakan. Pengguna hanya boleh meminta OTP untuk aset yang ditugaskan kepadanya.
4. Admin dapat menjalankan pengambilan kembali sesuai kewenangan. Test menunjukkan pengembalian/penarikan mengubah aset menjadi ready dan membersihkan assignment terkait.

**Izin utama:** `admin.assets.view` dan izin pengelolaan aset sesuai role.

Sumber: [route aset](../routes/web/admin/assets.php), [test lifecycle aset](../tests/Feature/AssetLifecycleFlowTest.php), [test keamanan aset pribadi](../tests/Feature/MyAssetsSecurityTest.php).

## 7. Impor dan ekspor karyawan

**Kegunaan:** memindahkan atau memperbarui data karyawan secara massal melalui proses background.

**Cara penggunaan:**

1. Buka `/admin/import-export/users` dan unduh template yang disediakan halaman.
2. Isi data sesuai kolom template. Panduan menyebut pencocokan pembaruan berdasarkan ID, email, atau NIP; manager dapat dicocokkan lewat NIP/email.
3. Unggah file dan jalankan impor.
4. Pantau run berstatus queued, running, completed, atau failed; periksa progres dan error baris. Konflik identitas perlu ditinjau meskipun baris valid lain berhasil diproses.
5. Untuk ekspor, pilih cakupan yang tersedia, jalankan, lalu unduh hasil setelah run selesai.

**Izin utama:** `admin.import_export_users.view`, `.import`, dan `.export` sesuai aksi.

Sumber: [route impor/ekspor](../routes/web/admin/import-export.php), [alur background](./features.md#import-export-dan-report-background), [test template karyawan](../tests/Feature/UserImportTemplateTest.php).

## 8. Impor/ekspor absensi dan laporan PDF

**Kegunaan:** impor data absensi, ekspor data terfilter, dan pembuatan laporan absensi melalui antrean.

**Cara penggunaan:** buka `/admin/import-export/attendances`, gunakan template absensi terbaru, lalu isi waktu masuk/keluar sebagai datetime lengkap. Untuk shift malam, gunakan tanggal keluar yang benar. Jalankan impor/ekspor dan periksa progres run. Laporan PDF menggunakan route `/admin/reports/export-pdf`; hasil background diunduh setelah proses selesai.

**Izin utama:** `admin.import_export_attendances.view`, `.import`, `.export`; laporan PDF memakai izin ekspor laporan absensi.

Panduan menyebut run terminal lebih dari 24 jam disembunyikan dari daftar terbaru dan dipangkas scheduler. Unduh hasil yang dibutuhkan sebelum retensi berakhir.

Sumber: [route impor/ekspor](../routes/web/admin/import-export.php), [route laporan](../routes/web/admin/reports.php), [alur background](./features.md#import-export-dan-report-background), [test retensi](../tests/Feature/ImportExportRunRetentionTest.php).

## 9. Audit trail dan ekspor aktivitas

**Kegunaan:** meninjau jejak aktivitas dan perubahan sensitif, serta mengekspor log untuk pemeriksaan.

**Cara penggunaan:** buka `/admin/activity-logs`, gunakan filter yang tersedia, tinjau aktivitas, lalu jalankan ekspor jika role memiliki izin. Ekspor menggunakan proses background. Untuk perubahan sensitif, periksa detail perubahan yang dicatat sistem sesuai tampilan yang tersedia.

Audit dasar memiliki jalur pencatatan tersendiri di kode terbaca. Karena itu, penghapusan service enterprise tidak boleh langsung disamakan dengan penghapusan seluruh pencatatan aktivitas.

**Izin utama:** `admin.activity_logs.view` dan `.export`. RBAC menandai modul log sebagai non-enterprise, tetapi route memakai `feature.lock:audit`.

Sumber: [route keamanan](../routes/web/admin/security.php), [route ekspor log](../routes/web/admin/import-export.php), [provider audit](../app/Providers/AuditServiceProvider.php), [detail perubahan sensitif](../app/Support/SensitiveAuditTrail.php).

## 10. Dokumen karyawan dan template

**Kegunaan:** permintaan surat/dokumen, pengumpulan file karyawan, pembuatan PDF dari template, dan pemberitahuan status.

**Cara penggunaan:**

1. HR mengelola template di `/admin/document-templates`.
2. Karyawan meminta dokumen melalui `/document-requests`; HR juga dapat membuat permintaan untuk karyawan dari `/admin/document-requests`.
3. HR memeriksa permintaan, membuat dokumen dari template atau meminta unggahan karyawan sesuai kebutuhan.
4. Karyawan mengunggah file saat diminta. HR dapat memproses atau menolak dengan catatan.
5. Setelah siap, karyawan menerima pemberitahuan dan mengunduh dokumen melalui route terproteksi. Test juga mencakup lampiran PDF pada email status dan pemrosesan permintaan massal.

**Catatan klasifikasi:** RBAC masih menandainya enterprise dan komponen utamanya terenkripsi. Namun, test `DocumentWorkflowUnlockedTest` secara eksplisit memastikan halaman admin/template tidak lagi mewajibkan entitlement dokumen. Jangan menyebutnya sepenuhnya dikunci lisensi; ketergantungan runtime tetap ada.

Sumber: [route dokumen admin](../routes/web/admin/hr-checklists.php), [route dokumen karyawan](../routes/web/user.php), [test workflow dokumen](../tests/Feature/EmployeeDocumentRequestFlowTest.php), [test pelepasan entitlement](../tests/Feature/DocumentWorkflowUnlockedTest.php), [policy dokumen](../app/Policies/EmployeeDocumentRequestPolicy.php).

## 11. Checklist onboarding dan offboarding

**Kegunaan:** membagi tugas HR, karyawan, dan direct manager selama proses masuk/keluar kerja.

**Cara penggunaan:**

1. HR membuka `/admin/hr-checklists`, membuat case onboarding/offboarding untuk karyawan, dan menetapkan tanggal efektif.
2. Gunakan template default atau atur item serta assignee sesuai proses perusahaan.
3. Karyawan/atasan membuka `/hr-tasks` untuk tugas yang diberikan kepadanya.
4. Perbarui status pending, done, skipped, atau blocked beserta catatan. Case menjadi completed ketika semua tugas ditutup sebagai done/skipped.
5. HR memantau progres dan dapat membatalkan case jika memiliki izin manage.

**Catatan klasifikasi:** halaman admin bergantung pada route/komponen enterprise, sedangkan halaman HR Tasks dan sebagian fondasi workflow terbaca. Pisahkan keduanya saat menentukan cakupan Community Edition.

Sumber: [panduan HR checklist](./features.md#hr-checklist-onboarding-dan-offboarding), [route admin](../routes/web/admin/hr-checklists.php), [test workflow checklist](../tests/Feature/HrChecklistFlowTest.php).

## 12. Backup, restore, dan maintenance

**Kegunaan:** operasi cache/maintenance, pencadangan database/aplikasi, jadwal backup, retensi, dan pemulihan database.

**Cara penggunaan:**

1. Administrator membuka `/admin/system-maintenance` dengan izin maintenance yang sesuai.
2. Gunakan Backup Center untuk backup SQL langsung atau antrekan backup database/aplikasi.
3. Pantau Backup Job Runs dan Retained Backups; unduh hasil melalui aksi terotorisasi.
4. Untuk otomatisasi, aktifkan jadwal harian/mingguan dan atur retensi. Worker, scheduler, dan storage privat harus siap.
5. Untuk restore database, gunakan backup SQL yang ditandatangani aplikasi dan ikuti konfirmasi UI, termasuk mengetik `RESTORE`. Panduan operasi menganjurkan backup baru sebelum pemulihan.
6. Operasi cache, maintenance mode, dan database cleanup tersedia pada bagian konsol terkait; cleanup mengikuti guardrail dan konfirmasi UI.

**Izin utama:** `admin.system_maintenance.view` dan `.manage`, ditambah policy backup/restore.

**Catatan klasifikasi:** RBAC menandai maintenance non-enterprise; halaman dan service masih terenkripsi. Route memakai label `system_maintenance`, helper backup memeriksa `system_backup`, dan command backup terjadwal memeriksa deployment key. Ketiga pemeriksaan tersebut tidak identik.

Sumber: [route sistem](../routes/web/system.php), [UI maintenance](../resources/views/livewire/admin/system-maintenance.blade.php), [operasi backup](./operations.md), [test backup jobs](../tests/Feature/SystemMaintenanceBackupJobsTest.php).

## 13. Operational health

**Kegunaan:** pemeriksaan kondisi layanan operasional aplikasi.

**Cara penggunaan:** administrator membuka `/admin/operational-health` untuk memperoleh respons pemeriksaan yang disediakan controller. Jika runtime enterprise tidak tersedia, route diarahkan ke controller penguncian. Untuk diagnosis queue/scheduler, cocokkan dengan panduan operasi dan runbook; format respons internal controller terenkripsi belum dapat dipastikan dari source terbaca.

**Akses:** memakai policy `SystemBackupRun@viewAny` dan middleware dengan label `operational_health`.

Sumber: [registrasi route operational health](../routes/web/admin/settings.php), [panduan operasi](./operations.md), [runbook](./runbooks.md).

## 14. Pengelolaan sesi dan token pengguna

**Kegunaan:** mengatasi akun tertahan oleh sesi perangkat aktif serta memeriksa/mencabut token API.

**Cara penggunaan:** admin berizin membuka `/admin/user-sessions`, memilih pengguna, lalu memutus sesi yang bermasalah. Pemeriksaan dan pencabutan token mengikuti hak akses akun pelaksana. Test mencakup larangan admin biasa menangani sesi/token superadmin.

**Izin utama:** `admin.user_sessions.manage`. RBAC non-enterprise, tetapi route memakai `feature.lock:security`, yang pada middleware saat ini dipetakan ke pemeriksaan audit.

Sumber: [route keamanan](../routes/web/admin/security.php), [test manajemen sesi/token](../tests/Feature/AdminUserSessionManagerTest.php).

## 15. Pengelolaan integrasi API

**Kegunaan:** menerbitkan, merotasi, dan mencabut kredensial integrasi pihak ketiga, termasuk integrasi mesin absensi.

**Cara penggunaan:**

1. Buka `/admin/api-integrations` sebagai admin berizin.
2. Buat integration client dengan nama, source, dan kemampuan yang sesuai; gunakan preset jika tersedia.
3. Konfigurasikan gateway/perangkat menggunakan endpoint dan petunjuk autentikasi pada halaman/panduan integrasi.
4. Untuk integrasi mesin absensi, ikuti aturan API key, HMAC, source, scope, dan idempotency pada panduan.
5. Rotasi atau cabut client ketika kredensial perlu diganti atau integrasi dihentikan.

**Izin utama:** `admin.api_integrations.manage`. Halaman pengelola masih terenkripsi dan dikunci route security; hal ini tidak berarti semua endpoint integrasi backend otomatis merupakan modul enterprise.

Sumber: [route keamanan](../routes/web/admin/security.php), [test integration client](../tests/Feature/IntegrationClientManagementTest.php), [panduan integrasi absensi](./attendance-integration.md).

## 16. Add-on Toko/POS

**Kegunaan:** operasional retail yang terhubung ke login, company scope, karyawan, dan accounting PasPapan.

| Area | Lokasi | Cara penggunaan utama |
| --- | --- | --- |
| Dashboard | `/admin/toko` | Pilih company/branch sesuai akses, tinjau ringkasan operasional dan keuangan toko. |
| Produk | `/admin/toko/products` | Kelola barang, kategori, brand, barcode, serta pemantauan stok menipis. |
| Kasir | `/admin/toko/pos` | Cari/scan barang, isi keranjang, selesaikan penjualan tunai atau invoice belum lunas, lalu cetak/cetak ulang nota. Dokumentasi mencakup split tender, QRIS, transfer, kartu, void, dan refund. |
| Pelanggan | `/admin/toko/customers` | Kelola pelanggan/member serta tinjau piutang dan riwayat pendapatan. |
| Vendor | `/admin/toko/vendors` | Kelola referensi vendor yang digunakan pada pembelian. |
| Pembelian | `/admin/toko/purchases` | Buat pembelian, isi barang/vendor/jatuh tempo, terima barang, dan tindak lanjuti pembayaran AP parsial/penuh. |
| Inventory | `/admin/toko/inventory` | Catat stok masuk/keluar, opname, dan periksa kartu stok serta audit mutasi. |
| Retur | `/admin/toko/returns` | Proses retur melalui workflow yang tersedia dan periksa dampaknya pada stok/transaksi. |
| Quotation | `/admin/toko/quotations` | Buat penawaran, kelola status, lalu konversi ke penjualan/invoice sesuai alur yang tersedia. |
| Surat jalan | `/admin/toko/delivery-letters` | Siapkan dokumen pengiriman dan cetak PDF sesuai transaksi/izin. |
| Kas dan biaya | `/admin/toko/cash` | Catat biaya operasional, kelola tipe biaya/rekening pembayaran, dan periksa mutasi terkait. |
| Laporan | `/admin/toko/reports` | Tinjau penjualan, pembelian, valuasi, margin/laba-rugi, pergerakan barang, dan aging AR/AP; jalankan ekspor yang tersedia. |
| Migrasi | `/admin/toko/migration` | Operator head/import menyiapkan dan memeriksa impor data transisi. Matikan lewat `toko_pos.migration_enabled=false` setelah cutover bila tidak diperlukan. |

**Contoh alur harian:** purchasing memasukkan pembelian → gudang memeriksa stok → kasir menjual barang → finance memantau pembayaran/biaya → owner meninjau laporan dan kontribusi Toko di Accounting Workspace.

**Izin utama:** `admin.toko_pos.view`, `.manage`, `.import`, `.export`. Admin company dibatasi ke company sendiri; superadmin memiliki kemampuan lintas company sesuai desain. Migrasi tidak diberikan kepada operator biasa. Runtime add-on juga memerlukan konfigurasi key/salt yang cocok dengan artifact.

Dokumentasi add-on menyebut baseline siap UAT/cutover. Pernyataan tersebut adalah status dari dokumen repository, bukan verifikasi ulang kesiapan produksi dalam inventarisasi ini.

Sumber: [panduan add-on dan training operator](./premium-toko-pos-addon.md), [PRD POS](./premium-toko-pos-prd.md), [route operasi/POS](../routes/web/admin/operations.php), [RBAC](../config/rbac.php).

## 17. Administrasi lisensi dan hardware ID

**Kegunaan:** mengelola aktivasi entitlement pada deployment enterprise lama.

**Cara penggunaan:** administrator memperoleh hardware ID melalui fasilitas yang tersedia atau `php artisan enterprise:hwid`, menyediakan identitas deployment yang diperlukan penerbit lisensi, kemudian memasukkan lisensi ke tab Enterprise di Settings. Status validasi digunakan untuk menilai apakah lisensi cocok dengan deployment dan fitur yang diminta.

Validasi kode mencakup signature, masa berlaku, domain/hardware binding, identitas perusahaan/kontak, dan jumlah pengguna sesuai payload. License key berbeda dari deployment key untuk membuka runtime terenkripsi. Prosedur penerbitan lisensi privat bukan bagian dari penggunaan aplikasi publik.

Sumber: [pengaturan lisensi](../app/Services/Admin/SettingsManagementService.php), [validasi lisensi](../app/Services/Enterprise/LicenseGuard.php), [enterprise packaging](./enterprise-packaging.md).

## Fitur yang perlu dibedakan dari modul enterprise

Jangan langsung memasukkan semua fitur berikut ke daftar penghapusan hanya karena disebut bersama enterprise dalam materi produk:

- **Face ID, foto, GPS, barcode, Dynamic QR:** jalur attendance community memiliki implementasi penyimpanan foto dan enrollment wajah yang terbaca. Entitlement `attendance` pada service lama tidak membuktikan seluruh absensi dasar berbayar.
- **Attendance risk scoring:** tersedia service dan panduan terbaca. Penggunaannya adalah triase manual berdasarkan skor, foto, lokasi, dan konteks; bukan keputusan sanksi otomatis. Perlu pemetaan dependensi tersendiri sebelum digolongkan untuk penghapusan.
- **RBAC dan isolasi company:** konfigurasi serta fondasi akses digunakan lintas modul. Penyebutan RBAC di bagian enterprise pada panduan lama tidak cukup untuk menghapus kontrol akses core.
- **Operations, commercial, accounting, collaboration, custom forms:** konfigurasi RBAC menandai workspace ini non-enterprise. POS berintegrasi dengannya, tetapi workspace core dan add-on POS perlu dipisahkan dalam inventaris dependensi.
- **Cuti, lembur, WFH, reimbursement, jadwal, koreksi absensi:** memiliki route dan workflow tersendiri di luar daftar enterprise di atas. Approval matrix juga dipakai lintas reimbursement/kasbon.
- **Pusat laporan operasional:** halaman Reports berisi ekspor non-enterprise dan ekspor terkait payroll/reporting. Klasifikasi perlu dilakukan per aksi, bukan menghapus seluruh pusat laporan.

Sumber: [RBAC](../config/rbac.php), [attendance community](../app/Services/Attendance/CommunityService.php), [route laporan](../routes/web/admin/reports.php), [panduan risk scoring](./attendance-risk-scoring.md), [panduan fitur](./features.md).

## Penggunaan dokumen untuk pemisahan Community Edition

Gunakan daftar fitur ini bersama [inventaris cutover](./community-edition-cutover.md). Untuk setiap modul, keputusan pelepasan harus mencakup halaman admin/karyawan, route unduhan, job, notifikasi, policy, menu, dan integrasi ke modul core. Jumlah file terenkripsi tidak sama dengan jumlah fitur produk; satu fitur dapat tersebar pada banyak file, sedangkan fitur core dapat berbagi model dan tabel dengan enterprise.
