# SSH deployment PasPapan — 2026-11-04

Target: `deploy-apps@202.83.121.173:22`, mengikuti akun deployment bersama btek-infra.
Private key hanya berada di WSL, di luar repository; public key dipasang pada akun server.

- [x] Periksa workflow PasPapan, pola btek-infra, dan akses akun server dengan host key yang sudah dipercaya.
- [x] Buat key Ed25519 khusus GitHub Actions PasPapan tanpa menimpa key lain.
- [x] Siapkan known_hosts dari host key yang telah dipercaya.
- [x] Tambahkan public key dengan pembatasan forwarding dan PTY tanpa menghapus key lama.
- [x] Uji autentikasi key baru dan pemeriksaan host ketat.
- [x] Catat file serta pemetaan GitHub Environment production.

## Konfigurasi workflow Compose saat ini

GitHub Environment `production`:
- Secret `HOST`: `202.83.121.173`
- Secret `USER`: `deploy-apps`
- Secret `SSH_KEY`: isi private key khusus PasPapan dari WSL.
- Secret `SSH_KNOWN_HOSTS`: isi file known_hosts khusus PasPapan dari WSL.

Workflow saat ini memakai port default 22 dan path `/var/www/paspapan-compose`.
Pola path btek-infra untuk aplikasi adalah `/opt/apps/<repository>`; penyelarasan path dan integrasi stack berada di luar pembuatan kredensial SSH ini.

## Bukti verifikasi

- Private key: `/home/bteklabs/.ssh/paspapan-github-actions-ed25519` (mode 600).
- Public key: `/home/bteklabs/.ssh/paspapan-github-actions-ed25519.pub`.
- Known hosts: `/home/bteklabs/.ssh/paspapan-known_hosts` (mode 600).
- Fingerprint client: `SHA256:cypJYOZg+WsUI3Ph6opKBnm2o43QN+wD+i6WRqftoHg`.
- Fingerprint host Ed25519: `SHA256:2LaGytmQRSGX1JvkoMP2eKledK/KuOO5WtG97/Pj+4E`.
- Public key ditambahkan dengan opsi `restrict` ke `/home/deploy-apps/.ssh/authorized_keys`, tanpa menghapus key lain. Opsi ini mematikan PTY dan forwarding; perintah SSH/rsync tetap tersedia sesuai hak akun.
- Uji key baru dengan `BatchMode=yes`, `IdentitiesOnly=yes`, dan `StrictHostKeyChecking=yes` berhasil: user `deploy-apps`, Docker Compose `v5.6.1`, `/opt/apps` writable.
- Secret GitHub belum diunggah. Deployment aplikasi belum dijalankan.
- Akun server tetap merupakan akun bersama dan anggota grup Docker; key terpisah ini tidak membatasi aksesnya hanya ke satu aplikasi.
