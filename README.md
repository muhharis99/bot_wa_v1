# Bot WA Notifikasi Berangkat Kerja

Bot WhatsApp pribadi berbasis **Baileys + Node.js**, dashboard **PHP native + Bootstrap 5**, dan **MySQL** untuk mengirim notifikasi berangkat kerja otomatis berdasarkan jadwal shift.

## Fitur

- Login WhatsApp dengan QR dan session persisten.
- QR tersedia di terminal dan dashboard web.
- Auto-reconnect saat koneksi WhatsApp terputus.
- Scheduler otomatis setiap 30 detik dengan timezone `Asia/Jakarta`.
- Shift P/W/S dengan dua opsi jam berangkat sesuai kebutuhan.
- Jadwal mingguan 7 hari, Senin–Minggu.
- Navigasi minggu sebelumnya, minggu ini, dan minggu berikutnya.
- Auto-save jadwal via AJAX tanpa reload halaman.
- Pilihan `Libur` tanpa pengiriman WhatsApp.
- Tombol **Salin Jadwal Minggu Lalu**.
- Jadwal lampau yang sudah terkirim dibuat read-only.
- Named lock MySQL pada scheduler untuk mengurangi risiko kirim ganda.
- Tombol **Kirim Sekarang** melalui antrean database.
- Status koneksi, heartbeat service Node.js, dan riwayat log.
- Login dashboard sederhana + CSRF token.
- Satu source untuk **Laragon local** dan **shared hosting/cPanel** melalui `APP_ENV` + `.env`.
- `npm run check` untuk pemeriksaan environment/database sebelum bot dijalankan.

## Struktur

```text
bot_wa_v1/
├── bot/
│   ├── server.js
│   ├── db.js
│   ├── check-env.js
│   ├── whatsapp.js
│   ├── scheduler.js
│   ├── package.json
│   └── .env.example
├── public/
│   ├── index.php
│   ├── ajax_jadwal.php
│   ├── config.php
│   ├── functions.php
│   ├── login.php
│   ├── logout.php
│   └── .env.example
├── database/
│   ├── migrations/
│   │   └── 2026_08_22_jadwal_mingguan.sql
│   └── schema.sql
├── setup-local.bat
├── start-bot.bat
├── LOCAL_LARAGON.md
├── DEPLOY_CPANEL.md
├── .gitignore
└── README.md
```

## Shift default

| Kode | Jam kerja | Opsi berangkat |
|---|---|---|
| P | 07:00–14:00 | 06:20 / 06:30 |
| W | 10:00–17:00 | 09:00 / 09:15 |
| S | 14:00–20:00 | 13:00 / 13:20 |

## Local Laragon Windows

Untuk environment Laragon dengan project di `C:\laragon\www\bot_wa_v1`, MySQL port `3307`, dan Node.js 22.x:

```bat
setup-local.bat
```

Setelah database `bot_wa_v1` dibuat dan `database/schema.sql` di-import:

```bat
start-bot.bat
```

Dashboard:

```text
http://localhost/bot_wa_v1/public/
```

Panduan lengkap: **`LOCAL_LARAGON.md`**.

## Instalasi hosting/cPanel

```bash
git clone https://github.com/muhharis99/bot_wa_v1.git
cd bot_wa_v1
```

1. Buat database MySQL lalu import `database/schema.sql`.
2. Salin `bot/.env.example` menjadi `bot/.env` dan isi `APP_ENV=production` serta kredensial database cPanel.
3. Salin `public/.env.example` menjadi `public/.env` dan isi database serta login dashboard.
4. Jalankan:

```bash
cd bot
npm install --omit=dev
npm run check
npm start
```

5. Arahkan domain/subdomain dashboard ke folder `public`.
6. Jalankan `server.js` melalui Setup Node.js App/Application Manager.
7. Login dashboard lalu scan QR WhatsApp.

Panduan lengkap: **`DEPLOY_CPANEL.md`**.

## Konsep environment

Source PHP dan Node.js tidak perlu diedit ketika berpindah dari local ke hosting. Yang berbeda hanya file `.env`, dan file tersebut tidak masuk Git.

Local Laragon:

```env
APP_ENV=local
DB_HOST=127.0.0.1
DB_PORT=3307
DB_NAME=bot_wa_v1
DB_USER=root
DB_PASS=
```

cPanel:

```env
APP_ENV=production
DB_HOST=localhost
DB_PORT=3306
DB_NAME=cpanelprefix_botwa
DB_USER=cpanelprefix_user
DB_PASS=password_database
```

## Upgrade dari versi jadwal harian lama

Jangan import ulang `schema.sql` ke database yang sudah berisi data. Jalankan satu kali migrasi:

```text
database/migrations/2026_08_22_jadwal_mingguan.sql
```

Migrasi tersebut mengubah status lama `pending/diproses` menjadi `terjadwal`, membuat `shift_id` dan `jam_berangkat_terpilih` nullable untuk hari libur, serta menyesuaikan index scheduler.

Sesudah migrasi, restart aplikasi Node.js agar `bot/scheduler.js` versi terbaru digunakan.

## Catatan penting

Baileys menggunakan protokol WhatsApp Web dan bukan API resmi WhatsApp Business. Gunakan untuk akun sendiri dan volume rendah, jangan untuk spam atau broadcast massal. Shared hosting juga harus benar-benar mendukung proses Node.js/WebSocket yang berjalan terus; bila provider sering menghentikan proses background, VPS lebih cocok untuk scheduler yang harus andal 24/7.
