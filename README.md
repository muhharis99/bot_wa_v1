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
- Konfigurasi rahasia melalui `.env` yang tidak masuk Git.

## Struktur

```text
bot_wa_v1/
├── bot/
│   ├── server.js
│   ├── db.js
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

## Instalasi baru

```bash
git clone https://github.com/muhharis99/bot_wa_v1.git
cd bot_wa_v1
```

1. Buat database MySQL lalu import `database/schema.sql`.
2. Salin `bot/.env.example` menjadi `bot/.env` dan isi kredensial database.
3. Salin `public/.env.example` menjadi `public/.env` dan isi kredensial database serta login dashboard.
4. Jalankan:

```bash
cd bot
npm install
node server.js
```

5. Arahkan domain/subdomain ke folder `public`.
6. Login dashboard lalu scan QR WhatsApp.

## Upgrade dari versi jadwal harian lama

Jangan import ulang `schema.sql` ke database yang sudah berisi data. Jalankan satu kali migrasi:

```text
database/migrations/2026_08_22_jadwal_mingguan.sql
```

Migrasi tersebut mengubah status lama `pending/diproses` menjadi `terjadwal`, membuat `shift_id` dan `jam_berangkat_terpilih` nullable untuk hari libur, serta menyesuaikan index scheduler.

Sesudah migrasi, restart aplikasi Node.js agar `bot/scheduler.js` versi terbaru digunakan.

Panduan deployment shared hosting cPanel lengkap ada di **`DEPLOY_CPANEL.md`**.

## Catatan penting

Baileys menggunakan protokol WhatsApp Web dan bukan API resmi WhatsApp Business. Gunakan untuk akun sendiri dan volume rendah, jangan untuk spam atau broadcast massal. Shared hosting juga harus benar-benar mendukung proses Node.js/WebSocket yang berjalan terus; bila provider sering menghentikan proses background, VPS lebih cocok untuk scheduler yang harus andal 24/7.
