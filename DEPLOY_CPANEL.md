# Deployment cPanel Shared Hosting

## 1. Persyaratan

- cPanel dengan **Setup Node.js App** / Application Manager.
- Node.js minimal 18, disarankan Node.js 20 LTS jika tersedia.
- PHP 8.1+ dengan PDO MySQL.
- MySQL/MariaDB.
- Domain/subdomain HTTPS.
- Hosting mengizinkan proses Node.js tetap aktif. Jika provider mematikan proses idle/background, scheduler tidak akan andal dan sebaiknya gunakan VPS.

## 2. Upload source

Clone repository melalui cPanel Terminal/Git Version Control atau upload ZIP. Contoh struktur aman:

```text
/home/USER/apps/bot_wa_v1/bot
/home/USER/apps/bot_wa_v1/public
```

Arahkan document root domain/subdomain dashboard ke folder `public` bila cPanel mengizinkan. Jangan jadikan folder `bot` sebagai document root.

## 3. Database

1. Buka **MySQL Databases**.
2. Buat database dan user.
3. Berikan **ALL PRIVILEGES** untuk user tersebut pada database.
4. Import `database/schema.sql` melalui phpMyAdmin.
5. Jika nama database wajib memakai prefix cPanel, boleh hapus dua baris pertama `CREATE DATABASE` dan `USE`, lalu pilih database yang benar sebelum import.

## 4. Environment dashboard PHP

Salin:

```bash
cp public/.env.example public/.env
```

Isi DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DASHBOARD_USER, dan DASHBOARD_PASS. File `.env` sudah di-ignore oleh Git.

Untuk keamanan, gunakan password dashboard yang panjang dan acak. Aktifkan HTTPS.

## 5. Setup Node.js App

Di **Setup Node.js App**:

- Node.js version: 20.x jika tersedia, minimal 18.x.
- Application mode: Production.
- Application root: path folder `bot`, misalnya `apps/bot_wa_v1/bot`.
- Application startup file: `server.js`.
- Application URL tidak wajib dipakai oleh aplikasi ini karena komunikasi dashboard ↔ bot memakai MySQL.

Masuk ke terminal environment Node.js yang diberikan cPanel, lalu:

```bash
cd /home/USER/apps/bot_wa_v1/bot
cp .env.example .env
npm install --omit=dev
```

Isi `bot/.env` dengan kredensial database yang sama.

## 6. Menjalankan bot

Cara standar lokal/VPS:

```bash
cd bot
node server.js
```

Di cPanel, gunakan tombol **Restart Application** setelah `npm install` atau perubahan source. Passenger/Application Manager akan menjalankan startup file.

Saat login pertama kali, status dashboard akan berubah menjadi `butuh_scan` dan QR akan muncul. Scan dari WhatsApp → **Perangkat tertaut**.

Folder `bot/sessions` menyimpan credential Baileys. Jangan dihapus bila tidak ingin scan ulang dan jangan pernah commit folder ini.

## 7. Penggunaan

1. Login dashboard.
2. Isi nomor tujuan. Format `0812...` atau `62812...` sama-sama diterima; service akan normalisasi ke kode negara Indonesia.
3. Isi template pesan.
4. Pilih tanggal, shift, dan salah satu opsi jam berangkat.
5. Scheduler mengecek setiap 30 detik.
6. Jadwal hanya dieksekusi jika waktunya sudah tiba dan masih dalam toleransi 15 menit.
7. Tombol **Kirim Sekarang** membuat antrean manual di database dan diproses oleh service Node.js.

## 8. Catatan reliabilitas

Shared hosting bukan lingkungan ideal untuk koneksi WebSocket WhatsApp yang harus hidup 24/7. Fitur ini hanya cocok bila provider benar-benar mendukung long-running Node.js app. Untuk penggunaan harian yang kritis, VPS kecil dengan systemd/PM2 lebih stabil.

Baileys adalah library tidak resmi yang berinteraksi dengan WhatsApp Web. Gunakan hanya untuk akun sendiri, volume rendah, dan hindari spam/otomasi massal. Perubahan protokol WhatsApp dapat sewaktu-waktu membuat koneksi perlu pembaruan library.

## 9. Troubleshooting

### QR tidak muncul

- Pastikan service Node.js hidup dan heartbeat dashboard berubah.
- Cek kredensial MySQL pada `bot/.env`.
- Bila session rusak/logout, stop aplikasi, hapus `bot/sessions`, lalu restart untuk menghasilkan QR baru.

### Dashboard connected tetapi pesan tidak terkirim

- Cek nomor tujuan.
- Lihat tabel/log dashboard untuk pesan error.
- Pastikan aplikasi Node.js tidak di-suspend oleh provider.

### Jadwal terlewat

Sistem sengaja membatasi toleransi 15 menit agar restart server berjam-jam kemudian tidak mengirim pesan lama. Edit `INTERVAL 15 MINUTE` pada `bot/scheduler.js` bila ingin toleransi berbeda.
