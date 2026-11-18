# Pemetaan Data Template Analytics Community Edition

Tanggal pemetaan: 17 November 2026.

Menyelesaikan checklist **“Petakan data yang dibutuhkan template analytics yang ada”** pada [prioritas CE-1](./community-edition-priorities.md#ce-1--analytics-absensi).

Pemetaan ini berdasarkan template Blade, JavaScript pada template, model, scope, konfigurasi, dan test yang terbaca. Komponen `AnalyticsDashboard.php` saat ini terenkripsi; rumus dan query implementasi enterprise tidak dianggap sudah diketahui. Bagian **kontrak teramati** menjelaskan kebutuhan UI yang dapat dibuktikan, sedangkan **arah implementasi CE** adalah rancangan untuk pekerjaan berikutnya.

## Status implementasi Community Edition

Checklist pemetaan selesai. Halaman analytics CE sekarang memakai komponen terbaca `CommunityAnalyticsDashboard` dan [service `CommunityAnalyticsService`](../app/Services/Analytics/CommunityAnalyticsService.php), pada route yang tetap dilindungi permission `viewAnalyticsDashboard`.

UI CE mempertahankan ringkasan jumlah karyawan aktif dalam scope admin, jumlah record, record present dan late, jumlah leave approved/pending/rejected, record lain, serta hitungan record per tanggal. Service mengembalikan semua properti yang dibaca view tersebut: `month`, `year`, `period`, `monthLabel`, `employeeCount`, `totals`, dan `daily`.

Layar CE memakai hitungan record yang tercatat. Hari tanpa record tidak ditafsirkan sebagai absen. Attendance rate, rata-rata per hari kerja, divisi, estimasi gaji, demografi, leaderboard, peta asal karyawan, dan kartu modul lain belum dipertahankan pada tampilan CE. Kontrak 17 input di bawah mendeskripsikan template enterprise lama untuk membantu pemetaan lanjutan.

## 1. Kontrak input halaman yang teramati

Template menerima **17 input utama**. Variabel lokal hasil perhitungan Blade dicatat terpisah agar tidak keliru menjadi parameter service.

| Input Blade | Tipe/bentuk yang dibutuhkan | Pemakaian UI | Kandidat sumber terbaca dan catatan |
| --- | --- | --- | --- |
| `month` | Integer 1–12 | Filter GET `month`, nama periode | Input request/state Livewire; validasi server diperlukan. |
| `year` | Integer tahun | Filter GET `year`, nama periode | UI saat ini menawarkan tahun berjalan dan satu tahun sebelumnya; batas server belum dapat disimpulkan dari template. |
| `summary` | Array dengan empat key wajib, lihat bagian 2 | Kartu dan gauge | Agregasi `User`, `Attendance`, serta kalender kerja jika menghitung coverage. |
| `estimatedPayroll` | Angka rupiah, bukan string terformat | Kartu Est. Basic Payroll | Kandidat `User.basic_salary`; merupakan estimasi gaji pokok, bukan total payroll atau gaji bersih. Memerlukan izin data gaji tersendiri. |
| `workHoursPerDay` | Angka jam/hari | Badge Work Standard | `Setting` key `attendance.work_hours_per_day`; default terbaca `8`. Bukan bukti jumlah hari kerja. |
| `trend` | Array `labels`, `present`, `late`, `absent`; masing-masing list sejajar | Grafik tren | `Attendance.date`, `status`, `user_id`; definisi absent belum dijelaskan UI. |
| `metrics` | Map status → jumlah integer | Attendance Mix dan grafik status | UI mengenali `present`, `late`, `sick`, `excused`, `alpha`, `absent`. Model juga memiliki request status lain; butuh normalisasi eksplisit. |
| `divisionStats` | Array `labels: list<string>`, `data: list<number>` | Grafik divisi dan lima divisi teratas | `User.division_id` → `Division`, record absensi terfilter. Grafik berlabel Present; bukan skor KPI atau appraisal. |
| `lateBuckets` | Map label bucket → jumlah integer | Pie Late Analysis | Kandidat `Attendance.time_in`, `date`, `shift_id` → `Shift.start_time`; batas bucket dan toleransi belum diketahui. |
| `absentStats` | Map alasan → jumlah integer | Doughnut Absence Reasons | Kandidat status/leave type pada `Attendance`; jangan mengirim teks bebas `note` sebagai alasan tanpa normalisasi dan pemeriksaan privasi. |
| `regionDistribution` | List item `{name, region, lat, lng}` | Peta, Top Employee Regions | `User.name` dan relasi `provinsi/kabupaten/kecamatan/kelurahan` ke `Wilayah`. Sumber koordinat profil belum teridentifikasi; model `Wilayah` hanya mengekspos `kode`, `nama`. |
| `genderDemographics` | Map kategori → jumlah integer; UI mengenali `male`, `female` | Grafik gender; variabel breakdown lokal | `User.gender`; data tidak dikenal perlu aturan kategori tersendiri. |
| `headcountStats` | Array `labels: list<string>`, `data: list<number>` | Grafik Headcount Distribution | Kandidat pengelompokan `User` berdasarkan divisi/jabatan. Dimensi sebenarnya tidak ditentukan oleh template. |
| `topDiligent` | Iterable objek `{name, avg_check_in}` | Daftar Early Birds | Kandidat rata-rata `Attendance.time_in` per user. `avg_check_in` harus angka detik sejak tengah malam karena dipakai `gmdate('H:i', ...)`. |
| `topLate` | Iterable objek `{name, late_count}` | Daftar Frequent Late | Hitungan keterlambatan per user dari `Attendance.status`. |
| `topEarlyLeavers` | Iterable objek `{name, early_leave_count}` | Daftar Early Runners | Kandidat perbandingan `Attendance.time_out` dengan akhir shift; perlu kalender tanggal dan penanganan shift malam. |
| `operationsMetrics` | Array empat key opsional pada pembacaan UI, lihat bagian 5 | Empat kartu operasional | Reimbursement, kasbon, dokumen, dan checklist HR; merupakan data lintas modul. |

`period` bukan input yang dibaca Blade, tetapi merupakan state komponen yang diwajibkan test lama: format `YYYY-MM`. Test juga mengharapkan `selectMonth()`, `selectYear()`, dan event `chart-update`.

Sumber: [template analytics](../resources/views/livewire/admin/analytics-dashboard.blade.php), [test filter](../tests/Feature/AnalyticsDashboardFilterTest.php), [model User](../app/Models/User.php), [Attendance](../app/Models/Attendance.php), [default pengaturan](../app/Support/DefaultApplicationSettings.php), [Wilayah](../app/Models/Wilayah.php).

## 2. Struktur summary dan turunan Blade

| Key summary | Kontrak UI | Kebutuhan definisi sebelum service ditulis |
| --- | --- | --- |
| `total_employees` | Angka, diakses langsung tanpa fallback | UI menyebut active employees. Tentukan apakah snapshot saat ini atau populasi historis periode; `User` memiliki `employment_status`, `created_at`, `resigned_at`, dan field lifecycle, tetapi itu tidak otomatis merupakan riwayat status lengkap. |
| `attendance_rate` | Persen tanpa karakter `%`, dipakai pada gauge 0–100 | Tetapkan numerator, denominator, jadwal kerja, izin yang disetujui, masa kerja, dan batas tanggal berjalan. Jangan memakai total hari kalender sebagai total hari kerja. |
| `late_rate` | Persen tanpa `%` | Hint UI menyatakan proporsi keterlambatan terhadap kehadiran tercatat. Rancangan awal: `late / (present + late) × 100`, dengan deduplikasi per user/tanggal dan denominator nol ditangani eksplisit. |
| `avg_daily_attendance` | Angka rata-rata | Hint menyebut per workday. Memerlukan daftar tanggal kerja yang terdefinisi; jangan membagi dengan jumlah hari kalender secara diam-diam. |

Turunan yang sudah dihitung di Blade:

- `selectedPeriod`: label bulan/tahun dari `month` dan `year`.
- `presentTotal`, `lateTotal`, `sickTotal`, `excusedTotal`: pembacaan key `metrics`, fallback nol.
- `alphaTotal`: penjumlahan `metrics.alpha` dan `metrics.absent`. Kedua kategori harus saling eksklusif agar tidak menghitung record sama dua kali.
- `attendanceMixTotal`: saat ini `max(present + late + sick + excused + alphaTotal, 1)`. Nilai ini sekaligus dipakai sebagai jumlah yang ditampilkan dan denominator progress bar. Akibatnya data kosong menampilkan total **1**. Implementasi berikutnya perlu memisahkan jumlah aktual dari denominator aman.
- `divisionLeaders`: pasangan `divisionStats.labels/data`, urut menurun, ambil lima. Label Top Performing Divisions tidak membuktikan nilai berupa persentase atau performance score.
- `topRegions`: jumlah item `regionDistribution` per `region`, ambil lima terbesar. Persentasenya dibagi total **lima wilayah teratas**, bukan total seluruh workforce. Item yang tidak masuk payload peta juga tidak masuk hitungan wilayah ini.
- `genderBreakdown` dan `genderTotal`: dihitung di bagian awal Blade, tetapi tidak dipakai lagi dalam markup yang diperiksa. Grafik memakai `genderDemographics` melalui payload.
- `summaryCards` dan `analyticsPayload`: rakitan lokal Blade; tidak perlu disediakan service sebagai input tambahan.

## 3. Kontrak payload JavaScript dan event

### Payload saat render pertama

Blade menerbitkan `window.analyticsChartsPayload` dengan delapan key berikut:

```text
trend              <- trend
metrics            <- metrics
division           <- divisionStats
late               <- lateBuckets
absent             <- absentStats
regionDistribution <- regionDistribution
gender             <- genderDemographics
headcount          <- headcountStats
```

Bentuk seri minimum:

```json
{
  "trend": {"labels": [], "present": [], "late": [], "absent": []},
  "metrics": {"present": 0, "late": 0, "sick": 0, "excused": 0, "alpha": 0, "absent": 0},
  "division": {"labels": [], "data": []},
  "late": {},
  "absent": {},
  "regionDistribution": [],
  "gender": {"male": 0, "female": 0},
  "headcount": {"labels": [], "data": []}
}
```

Objek contoh ini hanya kontrak bentuk data, bukan hasil agregasi. Untuk tren harian CE, rancangan berikutnya dapat mengisi semua tanggal periode dengan nol agar sumbu stabil; tanggal mendatang harus dibedakan dari ketidakhadiran aktual.

### Event `chart-update`

Handler `updateCharts()` menerima key dengan nama **berbeda** dari sebagian payload awal:

| Key event | Tujuan state JavaScript |
| --- | --- |
| `trend` | `data.trend` |
| `metrics` | `data.metrics` |
| `divisionStats` | `data.division` |
| `lateBuckets` | `data.late` |
| `absentStats` | `data.absent` |
| `regionDistribution` | `data.regionDistribution` |

Handler menimpa seluruh enam key tersebut tanpa fallback per key. Event parsial dapat menghasilkan `undefined`, dan pembacaan seperti `this.data.trend.labels` atau `this.data.division.labels` dapat gagal. Komponen baru perlu mengirim payload lengkap atau terlebih dahulu memperbaiki handler.

### Event `hris-update`

Handler `updateHrisCharts()` membaca `genderDemographics` dan `headcountStats`, kemudian menyimpannya ke `data.gender` dan `data.headcount`.

Kedua handler memakai `normalizePayload()`: object langsung diterima, sedangkan array dibaca dari elemen pertama. Kontrak baru sebaiknya menggunakan object dengan nama field eksplisit. Kartu summary dan leaderboard dirender oleh Blade; kedua event bukan pengganti pembaruan state server untuk kartu/daftar tersebut.

### Dependensi tampilan

Grafik membutuhkan global `Chart` (Chart.js). Peta membutuhkan `L` (Leaflet) dan `L.markerClusterGroup`. Payload kosong tidak menghilangkan kebutuhan library bila blok peta/grafik tetap dipasang. Pergantian tema dan navigasi Livewire juga memiliki lifecycle cleanup pada template.

## 4. Model sumber, scope, dan periode

### Data karyawan dan batas akses

Mulai dari `User::query()->where('group', 'user')->managedBy($actor)` sebagai kandidat populasi karyawan. Scope existing memakai `MultiCompanyService::guardUserQuery()` lalu aturan company/global/provinsi/kabupaten. Scope ini tidak berarti semata-mata bawahan langsung manager; jangan menggantinya dengan asumsi tersebut.

Semua agregasi, ranking, demografi, dan peta harus berasal dari populasi yang sama. `Attendance::managedBy()` memiliki jalur bypass untuk superadmin/global scope dan selain itu mendelegasikan scope melalui relasi user. Jika hanya memakai scope attendance tanpa pembatasan populasi karyawan, record milik kelompok admin dapat ikut terhitung; service baru perlu menyelaraskan cohort user dan attendance.

Permission halaman saat ini adalah `viewAnalyticsDashboard`, yang menggabungkan entitlement analytics dengan `admin.analytics.view`. Pada CE, pelepasan entitlement harus mempertahankan permission dan scope. Izin melihat analytics tidak otomatis memberi izin melihat gaji atau permintaan finance/dokumen.

### Absensi

Field sumber yang tersedia: `user_id`, `date`, `status`, `approval_status`, `time_in`, `time_out`, `shift_id`, `leave_type_id`. Model mencatat `date` sebagai datetime berformat tanggal dan check-in/out sebagai datetime.

- Agregasi periode sebaiknya menggunakan tanggal operasional `Attendance.date` secara konsisten; check-out shift malam dapat jatuh pada hari berikutnya.
- Status request model mencakup `sick`, `excused`, `permission`, `leave`, `rejected`, sedangkan grafik lama tidak memiliki normalisasi eksplisit untuk semuanya.
- Pending/rejected leave tidak boleh diam-diam dianggap izin yang sudah disetujui.
- Definisikan deduplikasi per user/tanggal sebelum menggunakan jumlah record sebagai jumlah orang-hari.
- Missing record tidak sama dengan `alpha`/`absent` tercatat; hitungan mangkir turunan membutuhkan kalender kewajiban kerja.

### Kalender kerja dan waktu shift

`Schedule` menyediakan `user_id`, `date`, `shift_id`, `is_off`. `Shift` menyediakan waktu mulai/akhir dan accessor overnight; akhir yang kurang dari atau sama dengan awal dianggap lintas tanggal. `Holiday` memiliki helper hari libur. Field lifecycle user dapat membantu membatasi masa kerja, tetapi kelengkapan histori perlu diperiksa pada implementasi service.

`attendance.work_hours_per_day` adalah jam standar, bukan jadwal wajib semua karyawan. `OperationalKpiService` sudah memiliki agregasi terbaca, tetapi expected attendance-nya memakai seluruh hari kalender. Hasilnya tidak dapat langsung dianggap sesuai hint workday pada template ini.

### Wilayah dan lokasi

Relasi user menunjuk `Wilayah` melalui kode wilayah. Model terbaca hanya mendeklarasikan `kode` dan `nama`; tidak ada field koordinat yang dapat diasumsikan dari model ini. Koordinat check-in/out pada Attendance merupakan lokasi kehadiran, bukan asal/domisili karyawan. Jangan menggunakannya sebagai pengganti tanpa mengubah makna tampilan.

Jika peta dipertahankan, tentukan sumber koordinat dan granularitasnya. Peta saat ini menerima nama karyawan per marker, bukan hanya agregat wilayah. Arah CE-1 adalah menunda peta personal sampai sumber data dan aturan aksesnya jelas.

Sumber: [User dan scope managedBy](../app/Models/User.php), [scope company](../app/Support/MultiCompanyService.php), [Attendance](../app/Models/Attendance.php), [Schedule](../app/Models/Schedule.php), [Shift](../app/Models/Shift.php), [Holiday](../app/Models/Holiday.php), [OperationalKpiService](../app/Support/OperationalKpiService.php), [gate analytics](../app/Providers/AuthServiceProvider.php).

## 5. Kartu lintas modul

| Key `operationsMetrics` | Model/relasi kandidat | Hal yang belum ditentukan template |
| --- | --- | --- |
| `pending_reimbursements` | `Reimbursement` → `user` | Status yang dihitung: pending saja atau termasuk pending_finance/pending_matrix; backlog saat ini atau periode terpilih. |
| `pending_cash_advances` | `CashAdvance` → `user` | Batas pending, tahap approval finance, dan cakupan tanggal. Kasbon di luar prioritas awal CE. |
| `pending_document_requests` | `EmployeeDocumentRequest` → `user` | Pending/requested/uploaded/generated memiliki arti berbeda; judul Pending issuance belum menetapkan daftar status. |
| `pending_hr_tasks` | `HrChecklistTask` → `case`/`assignee` | Task pending/blocked, case aktif, serta apakah scope melalui pemilik case atau assignee. Model task tidak memiliki `user_id` langsung. |

Semua pembacaan kartu menggunakan fallback nol. Fallback itu hanya mekanisme tampilan; jangan mengartikan nol sebagai “tidak ada permintaan” ketika modul/izin memang tidak tersedia. Untuk tahap CE-1, blok yang belum didukung disembunyikan secara eksplisit. Jangan memuat service enterprise hanya untuk mengisi kartu tambahan.

Sumber: [Reimbursement](../app/Models/Reimbursement.php), [CashAdvance](../app/Models/CashAdvance.php), [EmployeeDocumentRequest](../app/Models/EmployeeDocumentRequest.php), [HrChecklistTask](../app/Models/HrChecklistTask.php), [contoh query dashboard terbaca](../app/Support/AdminDashboardQueryService.php).

## 6. Arah implementasi CE-1 dari hasil pemetaan

Bagian ini menentukan batas pekerjaan berikutnya, bukan menyatakan implementasi sudah tersedia.

| Kelompok | Arah CE-1 | Implikasi pada template/service |
| --- | --- | --- |
| Filter, metrics, trend, jumlah workforce, late rate | Prioritas utama | Sediakan input lengkap dengan scope dan tipe konsisten; definisikan status yang dinormalisasi. |
| Attendance rate dan avg daily attendance | Memerlukan definisi kalender kerja | Hindari persentase/rata-rata palsu; sebelum denominator tersedia, ubah UI untuk menampilkan belum tersedia, bukan nol yang menyesatkan. |
| Division performance | Pertahankan sebagai volume kehadiran per divisi setelah definisi ditetapkan | Label jumlah kehadiran, bukan skor performa/KPI; label/data harus sejajar. |
| Late buckets, absence reasons | Setelah aturan kategori ditetapkan | Tentukan interval menit, toleransi, dan kategori izin yang disetujui sebelum agregasi. |
| Gender dan headcount | Tambahan demografi | Tentukan cohort snapshot dan dimensi headcount; perlakuan unknown tidak boleh hilang diam-diam. |
| Early Birds, Frequent Late, Early Runners | Tambahan ranking | Tentukan top-N, tie-break, syarat minimal data, dan perbandingan per shift; rata-rata jam mentah tidak adil untuk shift berbeda. |
| Estimated payroll | Tunda dari tampilan CE-1 | Hilangkan blok atau beri pemeriksaan izin gaji yang tepat; tidak perlu mengaktifkan payroll enterprise. |
| Kartu operasional | Tunda dari tampilan CE-1 | Sembunyikan blok yang belum didukung/diizinkan. |
| Peta dan Top Employee Regions | Tunda sampai sumber koordinat dan akses jelas | Hilangkan blok dan dependensi map jika tidak dipakai; jangan sekadar mengisi array kosong sambil menampilkan kesimpulan wilayah. |

Jika template lama tetap dipakai utuh untuk kompatibilitas, semua 17 input wajib disediakan sesuai bentuknya, termasuk iterable kosong untuk leaderboard. Jika blok ditunda, hapus juga pembacaan variabel di awal Blade serta pemanggilan renderer JavaScript terkait agar tidak tersisa dependensi tersembunyi.

## 7. Checklist verifikasi untuk tahap implementasi berikutnya

- [ ] Service menyediakan setiap input yang dipakai blok UI yang dipertahankan, tanpa variabel Blade tidak terdefinisi.
- [ ] Payload awal, `chart-update`, dan `hris-update` menggunakan nama field yang benar.
- [ ] Seri label/data memiliki panjang dan urutan yang sama; nilai numerik benar-benar dikirim sebagai angka.
- [ ] Record luar periode, admin non-karyawan, dan karyawan luar scope tidak masuk agregasi.
- [ ] Pending/rejected leave dan record duplikat ditangani sesuai definisi metrik.
- [ ] Data kosong menampilkan jumlah aktual nol; denominator aman tidak mengubah angka yang ditampilkan.
- [ ] Tanggal mendatang tidak dianggap mangkir; shift malam memakai tanggal operasional yang benar.
- [ ] Perubahan periode memperbarui kartu, leaderboard, dan grafik, bukan hanya event grafik.
- [ ] Field gaji, nama/lokasi personal, dan ringkasan modul lain tidak bocor lewat payload meskipun blok UI disembunyikan.

Pemetaan selesai melalui inspeksi statis. Service agregasi, penggantian runtime, perbaikan template, definisi metrik final, dan pengujian runtime tetap merupakan pekerjaan terpisah pada backlog CE-1.
