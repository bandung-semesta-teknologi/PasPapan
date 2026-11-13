# Deployment

Target produksi resmi PasPapan adalah VPS. Shared hosting tidak lagi menjadi target utama karena fitur penuh membutuhkan worker, scheduler, storage privat stabil, backup, dan WebSocket.

Branch deployment yang direkomendasikan untuk instalasi baru adalah `main-vps`. Branch `main` tetap dipertahankan sebagai jalur legacy/shared-hosting ringan, tetapi fitur penuh dan hardening terbaru diarahkan ke `main-vps`.

## Kebutuhan Sistem

Minimum produksi:

- PHP `8.3+`
- Composer `2.x`
- Node.js `20+`
- Bun `1.3.6+`
- PostgreSQL `15+`
- ekstensi PHP umum Laravel 13: `pdo_pgsql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip`, `ctype`, `json`, `tokenizer`, `xml`

Baseline runtime resmi PasPapan adalah PHP 8.3+ untuk production, VPS, dan CI. PHP 8.4 direkomendasikan untuk production baru karena dukungan platform lebih panjang dan performa runtime lebih segar. Stack framework resmi saat ini adalah Laravel 13 + Livewire 4.

Default database local/VPS adalah PostgreSQL 15+. MySQL/MariaDB tetap bisa diuji sebagai compatibility path untuk provider tertentu, tetapi bukan baseline deployment utama.

Direkomendasikan untuk VPS:

- Nginx atau Apache dengan document root ke `public/`
- Supervisor atau systemd untuk queue worker
- cron
- SSH

## Matrix Deployment

| Area | Vercel | Shared hosting | VPS |
| --- | --- | --- | --- |
| Target | Demo, staging, instalasi ringan | Legacy compatibility, bukan target release | Production penuh dan enterprise |
| Runtime PHP | `vercel-php@0.7.4` PHP 8.3.x | PHP 8.3+ dari provider | PHP 8.3+; PHP 8.4 direkomendasikan |
| Build frontend | CI/Vercel build dengan Node 20+ dan Bun 1.3.6+ | Build lokal atau host jika tersedia | Build di server/CI dengan Node 20+ dan Bun 1.3.6+ |
| Queue | `sync`; tidak ada worker permanen | Database queue via cron pendek | Worker permanen via Supervisor/systemd |
| Scheduler | Tidak native; butuh cron eksternal | Cron provider | Cron server |
| Storage lokal/private | `/tmp` ephemeral; pakai object storage untuk persisten | Bisa lokal jika root aman dan disk cukup | Lokal/private penuh |
| Backup lokal | Tidak disarankan | Terbatas sesuai quota dan cron | Didukung penuh dengan checksum/drill |
| Import/export besar | Risiko timeout | Terbatas | Didukung worker panjang |
| Reverb/WebSocket | Tidak disarankan | Umumnya tidak tersedia | Didukung dengan proses Reverb dan reverse proxy |
| APK backend production | Hanya untuk demo/ringan | Bisa untuk tim kecil | Direkomendasikan |

VPS adalah target production penuh PasPapan. Vercel hanya untuk demo/staging ringan. Shared hosting hanya dokumentasi kompatibilitas legacy, bukan target release utama.

## SMTP pada deploy Docker Compose

Workflow `.github/workflows/deploy-compose.yml` mengambil konfigurasi SMTP dari GitHub Actions **Secrets** pada environment `production` (Settings → Environments → production → Environment secrets):

| Secret | Nilai |
| --- | --- |
| `MAIL_HOST` | Host SMTP; isi untuk mengaktifkan konfigurasi dari secret terpisah |
| `MAIL_PORT` | Port SMTP, default `587` |
| `MAIL_USERNAME` | Username SMTP; kosong jika server tanpa autentikasi |
| `MAIL_PASSWORD` | Password SMTP; kosong jika server tanpa autentikasi |
| `MAIL_ENCRYPTION` | Enkripsi SMTP, default `tls`; gunakan `null` untuk tanpa enkripsi |
| `MAIL_FROM_ADDRESS` | Alamat pengirim, wajib ketika `MAIL_HOST` diisi |
| `MAIL_FROM_NAME` | Nama pengirim, default `PasPapan` |

Saat `MAIL_HOST` diisi, workflow menetapkan `MAIL_MAILER=smtp`, menghapus `MAIL_URL`, dan mengganti seluruh konfigurasi SMTP di atas dalam `.env` produksi. Nilai secret harus satu baris dan diisi langsung tanpa tanda kutip pembungkus. Jika `MAIL_HOST` tidak diisi, konfigurasi email tetap berasal dari secret multiline `DEPLOY_ENV`.

Secret `DEPLOY_ENV` tetap wajib untuk konfigurasi aplikasi lainnya, termasuk `APP_KEY` dan `DB_PASSWORD`. File `.env` dikirim melalui SSH dengan permission `600` dan dibaca oleh container `app`, `queue`, dan `scheduler`. Jalankan ulang workflow **Deploy with Docker Compose** setelah memperbarui secret.

## Deploy VPS

### 1. Siapkan server

```bash
sudo mkdir -p /var/www/paspapan
sudo chown -R $USER:$USER /var/www/paspapan
cd /var/www/paspapan
```

Install PHP, Composer, PostgreSQL, Bun atau Node.js, web server, dan Supervisor/systemd.

Preflight runtime sebelum install dependency:

```bash
php -v
node -v
bun --version
composer check-platform-reqs
```

### 2. Ambil source dan install dependency

```bash
git clone --branch main-vps https://github.com/RiprLutuk/PasPapan.git .
composer install --no-dev --optimize-autoloader
bun install
cp .env.example .env
php artisan key:generate
```

### 3. Environment produksi

```dotenv
APP_NAME=PasPapan
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
DB_SCHEMA=public
DB_SSLMODE=prefer
# Managed MySQL/MariaDB compatibility path should be validated with
# composer check:database-portability before production use.

QUEUE_CONNECTION=database
SCHEDULE_QUEUE_WORKER=false
SESSION_DRIVER=database
CACHE_STORE=database
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=log
ANNOUNCEMENT_REFRESH_MODE=auto
ANNOUNCEMENT_POLL_INTERVAL=60s

MAIL_MAILER=smtp
MAIL_HOST=your-mail-host
MAIL_PORT=587
MAIL_USERNAME=your-mail-user
MAIL_PASSWORD=your-mail-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

`BROADCAST_CONNECTION=reverb` adalah mode production VPS. Dengan `ANNOUNCEMENT_REFRESH_MODE=auto`, aplikasi fallback ke polling jika driver/key Reverb belum lengkap. Untuk VPS yang siap WebSocket, aktifkan Reverb:

Generate app key/secret Reverb sendiri. Nilai ini tidak perlu berasal dari layanan eksternal, tetapi harus random dan hanya disimpan di `.env` runtime:

```bash
php -r 'echo "REVERB_APP_KEY=".bin2hex(random_bytes(16)).PHP_EOL; echo "REVERB_APP_SECRET=".bin2hex(random_bytes(32)).PHP_EOL;'
```

```dotenv
BROADCAST_CONNECTION=reverb
ANNOUNCEMENT_REFRESH_MODE=auto
COLLABORATION_REALTIME_ENABLED=true

REVERB_APP_ID=paspapan-production
REVERB_APP_KEY=<random-hex>
REVERB_APP_SECRET=<random-hex>
REVERB_HOST=your-domain.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

Gunakan host publik pada `REVERB_HOST`. `REVERB_SERVER_HOST` dan `REVERB_SERVER_PORT` adalah alamat bind proses Reverb di server. `COLLABORATION_REALTIME_ENABLED=true` direkomendasikan pada VPS karena membutuhkan broadcast connection yang berjalan stabil.

Untuk local smoke test, gunakan `REVERB_HOST=127.0.0.1`, `REVERB_PORT=8080`, `REVERB_SCHEME=http`, lalu jalankan `php artisan reverb:start` di terminal terpisah bersama queue worker. `REVERB_APP_ID`, `REVERB_APP_KEY`, dan `REVERB_APP_SECRET` boleh custom, tetapi tetap gunakan nilai random:

```bash
php -r 'echo "REVERB_APP_ID=local-".bin2hex(random_bytes(6)).PHP_EOL; echo "REVERB_APP_KEY=".bin2hex(random_bytes(16)).PHP_EOL; echo "REVERB_APP_SECRET=".bin2hex(random_bytes(32)).PHP_EOL;'
```

Browser akan menerima konfigurasi Echo dari layout aplikasi, termasuk `/broadcasting/auth` dan CSRF header, sehingga private channel seperti `collaboration.company.{id}` bisa diuji tanpa hardcode `VITE_*` key.

### 4. Build dan migrate

```bash
bun run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

`php artisan view:cache` tidak dijadikan default karena beberapa environment bisa terkena limit regex saat compile Blade Livewire.

### 5. Permission

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

Sesuaikan `www-data` dengan user PHP-FPM server Anda.

### 6. Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/paspapan/public;
    index index.php;

    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 7. Supervisor Queue Worker

Buat `/etc/supervisor/conf.d/paspapan-worker.conf`:

```ini
[program:paspapan-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/paspapan/artisan queue:work database --queue=maintenance,default --sleep=3 --tries=3 --timeout=1800
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/paspapan/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start paspapan-worker:*
```

### 8. Scheduler

Tambahkan cron:

```cron
* * * * * cd /var/www/paspapan && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler diperlukan untuk backup terjadwal, cleanup run import/export, dan fallback queue worker jika `SCHEDULE_QUEUE_WORKER=true`.

### 9. Reverb WebSocket Opsional Untuk VPS

Reverb hanya cocok untuk VPS atau hosting yang mengizinkan proses long-running dan WebSocket/reverse proxy. Buat Supervisor process terpisah jika `BROADCAST_CONNECTION=reverb`.

Buat `/etc/supervisor/conf.d/paspapan-reverb.conf`:

```ini
[program:paspapan-reverb]
process_name=%(program_name)s
command=php /var/www/paspapan/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/paspapan/storage/logs/reverb.log
stopwaitsecs=10
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start paspapan-reverb
```

Jika memakai Nginx di HTTPS publik, proxy WebSocket ke Reverb:

```nginx
location /app/ {
    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_set_header Scheme $scheme;
    proxy_set_header SERVER_PORT $server_port;
    proxy_set_header REMOTE_ADDR $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_pass http://127.0.0.1:8080;
}
```

Lalu gunakan:

```dotenv
REVERB_HOST=your-domain.com
REVERB_PORT=443
REVERB_SCHEME=https
```

### 10. Checklist

- domain/document root wajib mengarah ke `public/`, bukan root repo
- root repo tidak boleh bisa diakses langsung dari web; proteksi `.htaccess` hanya fallback, bukan kontrol utama
- `storage/` dan `bootstrap/cache/` writable
- migration terbaru sudah dijalankan, termasuk tabel HR Checklist
- queue worker berjalan
- cron aktif
- login berhasil
- upload/download attachment berhasil
- admin/HR dapat membuka `Master Data > HR Checklists`
- karyawan/manager dapat membuka `HR Tasks`
- queued job seperti backup atau export report berhasil
- akun demo/bootstrap sudah diaudit sebelum go-live

## Legacy Shared Hosting Compatibility

Bagian ini dipertahankan untuk instalasi lama atau provider terbatas. Jalur ini bukan target release utama PasPapan karena fitur penuh membutuhkan worker long-running, scheduler stabil, storage privat persisten, backup, dan Reverb/WebSocket. Gunakan VPS PostgreSQL untuk deployment baru.

Shared hosting hanya layak dicoba jika provider memberi PHP 8.3+, SSH atau terminal, cron, kemampuan mengarahkan document root ke `public/`, dan database managed yang stabil. PHP 8.4 direkomendasikan bila tersedia. Jika provider hanya menyediakan MySQL/MariaDB, jalankan portability smoke dan treat sebagai compatibility path.

### 1. Build lokal

```bash
php -v
composer check-platform-reqs
composer install --no-dev --optimize-autoloader
bun install
bun run build
```

Jika host tidak bisa menjalankan Composer, upload juga `vendor/`. Jika host tidak bisa menjalankan Bun atau Node, upload hasil `public/build/`.

### 2. Upload dan document root

Upload project, lalu arahkan domain ke:

```text
/path/to/your-app/public
```

Jangan meratakan struktur Laravel ke `public_html` kecuali provider benar-benar memaksa dan risiko keamanannya dipahami.

### 3. Environment

Gunakan variabel produksi seperti VPS, tetapi biasanya:

```dotenv
QUEUE_CONNECTION=database
SCHEDULE_QUEUE_WORKER=true
SESSION_DRIVER=database
CACHE_STORE=database
BROADCAST_CONNECTION=log
ANNOUNCEMENT_REFRESH_MODE=auto
ANNOUNCEMENT_POLL_INTERVAL=60s
```

Shared hosting biasa tidak menjalankan Reverb karena butuh proses long-running `php artisan reverb:start` dan WebSocket port/proxy. Biarkan `BROADCAST_CONNECTION=log`; aplikasi akan memakai fallback polling ringan, tetapi fitur realtime penuh tetap target VPS.

### 4. Command Laravel

```bash
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

Migration HR Checklist tidak membutuhkan proses long-running. Setelah migration, pastikan role `admin` dan `hr` memiliki permission `admin.hr_checklists.view` dan `admin.hr_checklists.manage`, terutama pada instalasi yang sudah memiliki role custom sebelum update.

### 5. Cron

```cron
* * * * * cd /home/USER/path-to-app && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler sudah memuat worker pendek saat `SCHEDULE_QUEUE_WORKER=true`. Jika build lama belum punya scheduler worker, pakai fallback:

```cron
* * * * * cd /home/USER/path-to-app && php artisan queue:work database --queue=maintenance,default --stop-when-empty --max-time=55 --tries=1 >> /dev/null 2>&1
```

## Deploy Vercel

PasPapan bisa dideploy ke Vercel memakai komunitas runtime [`vercel-community/php`](https://github.com/vercel-community/php). Cocok untuk demo, staging, atau instalasi ringan. Untuk produksi penuh dengan queue worker, scheduler, backup otomatis, dan storage persisten, gunakan VPS.

Demo publik Vercel tersedia di [paspapan.vercel.app](https://paspapan.vercel.app).

### 1. File apa saja yang dibutuhkan?

Repository sudah menyertakan:

- [`vercel.json`](../vercel.json)
- [`api/index.php`](../api/index.php)
- [`api/php.ini`](../api/php.ini)
- [`.env.vercel.example`](../.env.vercel.example)
- [`.vercelignore`](../.vercelignore)

### 2. Bagaimana struktur entrypoint-nya?

`api/index.php` menjadi entrypoint Laravel untuk serverless function. Semua request non-static diarahkan ke file ini dari `vercel.json`.

### 3. Runtime PHP apa yang dipakai?

Konfigurasi saat ini:

```json
{
  "functions": {
    "api/index.php": {
      "runtime": "vercel-php@0.7.4",
      "memory": 1024,
      "maxDuration": 60
    }
  }
}
```

`vercel-php@0.7.4` memakai PHP 8.3.x dan cocok dengan requirement Composer project ini (`php: ^8.3`). Runtime `vercel-community/php` punya versi lebih baru, tetapi upgrade sebaiknya dites dulu. PHP 8.4 tetap direkomendasikan untuk runtime non-serverless yang mendukungnya.

### 4. Bagaimana routing Laravel di Vercel?

`vercel.json` mengirim asset public tertentu ke `public/`, lalu semua request lain ke `api/index.php`:

```json
{
  "routes": [
    { "src": "/(build|assets|js|models)/(.*)", "dest": "/public/$1/$2" },
    { "src": "/(.*)", "dest": "/api/index.php" }
  ]
}
```

### 5. Bagaimana database-nya?

Vercel tidak menyediakan database lokal. Pakai database PostgreSQL eksternal seperti Railway, Aiven, Neon, Supabase Postgres, VPS database, atau provider lain yang dapat diakses dari Vercel.

Jalankan migration dari mesin lokal atau CI yang bisa mengakses database:

```bash
cp .env.vercel.example .env
php artisan key:generate --show
php artisan migrate --force
```

Simpan output `APP_KEY` ke Vercel Environment Variables.

### 6. Environment variable apa yang wajib?

```dotenv
APP_NAME="PAS Papan"
APP_ENV=production
APP_KEY=base64:your-generated-key
APP_DEBUG=false
APP_URL=https://your-project.vercel.app
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID
APP_STORAGE_PATH=/tmp/storage
APP_MAINTENANCE_DRIVER=file

LOG_CHANNEL=stderr
LOG_STACK=stderr
LOG_LEVEL=info
BCRYPT_ROUNDS=12

DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_PORT=5432
DB_DATABASE=your-db-name
DB_USERNAME=your-db-user
DB_PASSWORD=your-db-password
DB_SCHEMA=public
DB_SSLMODE=require
# MySQL/TiDB managed compatibility alternative:
# DB_CONNECTION=mysql
# DB_PORT=3306
MYSQL_ATTR_SSL_CA=/etc/ssl/cert.pem

CACHE_STORE=array
CACHE_PREFIX=
SESSION_DRIVER=cookie
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=log
ANNOUNCEMENT_REFRESH_MODE=auto
ANNOUNCEMENT_POLL_INTERVAL=60s

MAIL_MAILER=log
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"
```

Default Vercel memakai `SESSION_DRIVER=cookie` dan `CACHE_STORE=array` supaya login/register tidak bergantung pada tabel `sessions` dan `cache` di serverless. Jika SMTP belum dikonfigurasi, gunakan `MAIL_MAILER=log` agar email verifikasi tidak membuat request register gagal.

### 7. Kenapa `QUEUE_CONNECTION=sync`?

Vercel serverless tidak menjalankan worker permanen. `sync` membuat job dieksekusi dalam request yang sama. Ini praktis untuk demo atau workload ringan, tetapi tidak cocok untuk proses besar.

### 8. Kenapa `APP_STORAGE_PATH=/tmp/storage`?

Filesystem serverless hanya writable di `/tmp`. `api/index.php` membuat direktori storage runtime di sana. File di `/tmp` bersifat ephemeral dan bisa hilang antar invocation.

### 9. Bagaimana deploy lewat dashboard?

1. Import repository GitHub ke Vercel.
2. Pilih framework preset `Other`.
3. Biarkan root directory di root repository.
4. Gunakan build command dari `vercel.json`: `bun run build`.
5. Deploy.

### 10. Bagaimana deploy lewat CLI?

```bash
npm i -g vercel
vercel login
vercel
vercel --prod
```

### 11. Apa yang harus dites setelah deploy?

- halaman login tampil
- login admin berhasil
- halaman admin yang membaca database berhasil
- flow absensi ringan berhasil
- SMTP berhasil jika email diaktifkan
- asset dari `public/build` tampil
- cek `Vercel > Functions > Logs` jika ada error 500

### 12. Apa batasan Vercel untuk PasPapan?

- upload ke local disk `/tmp` tidak persisten
- backup center berbasis file lokal tidak cocok
- scheduler Laravel tidak berjalan otomatis tanpa cron eksternal
- queue database tidak diproses tanpa worker eksternal
- export/import besar dan PDF berat dapat kena timeout function
- `php artisan storage:link` tidak relevan untuk filesystem serverless

Jika attachment perlu persisten di Vercel, pindahkan storage ke S3-compatible object storage dan sesuaikan `FILESYSTEM_DISK`, konfigurasi disk, serta flow download/upload.

### 13. Enterprise build artifact

Enterprise packaging memakai tool internal di `secure_tools/` yang tidak dipublikasikan sebagai source OSS. Instalasi open source tidak membutuhkan obfuscator secret: `composer install`, `composer dump-autoload`, dan `php artisan package:discover` harus berhasil tanpa key enterprise. Sebelum rilis enterprise internal, maintainer wajib menjalankan obfuscator private dengan `ENTERPRISE_OBFUSCATOR_KEY` di environment trusted lalu hanya artifact obfuscated salted yang didistribusikan. Runtime enterprise customer juga harus memiliki `ENTERPRISE_OBFUSCATOR_KEY` yang sama. `ENTERPRISE_LICENSE_KEY` hanya untuk validasi lisensi dan tidak dipakai sebagai secret obfuscator. Jangan simpan key private ini di repo, `.env.example`, atau dokumen publik.

Jika runtime enterprise kehilangan `ENTERPRISE_OBFUSCATOR_KEY`, fitur Enterprise saja yang fail-closed: halaman OSS/community tetap bisa dipakai, route Enterprise diarahkan ke modal/halaman terkunci, dan pekerjaan CLI Enterprise dilewati dengan pesan konfigurasi singkat. File obfuscated tetap tidak bisa dibuka tanpa key yang cocok. Jangan aktifkan `APP_DEBUG=true` di server customer.

Source audit OSS tetap harus bebas dari build cache, vendor, `node_modules`, dan generated artifact.
