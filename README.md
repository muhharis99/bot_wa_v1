# Bot WA Notifikasi Berangkat Kerja

Bot WhatsApp pribadi berbasis **Baileys + Node.js**, dashboard **PHP native + Bootstrap 5**, dan **MySQL** untuk mengirim notifikasi berangkat kerja otomatis berdasarkan jadwal shift.

## Fitur v1

- Login WhatsApp dengan QR dan session persisten.
- QR tersedia di terminal dan dashboard web.
- Auto-reconnect saat koneksi WhatsApp terputus.
- Scheduler otomatis setiap 30 detik dengan timezone `Asia/Jakarta`.
- Shift P/W/S dengan dua opsi jam berangkat sesuai kebutuhan.
- Satu jadwal per tanggal dan mekanisme claim untuk mengurangi risiko kirim ganda.
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
│   ├── config.php
│   ├── functions.php
│   ├── login.php
│   ├── logout.php
│   └── .env.example
├── database/
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

## Instalasi ringkas

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

Panduan deployment shared hosting cPanel lengkap ada di **`DEPLOY_CPANEL.md`**.

## Catatan penting

Baileys menggunakan protokol WhatsApp Web dan bukan API resmi WhatsApp Business. Gunakan untuk akun sendiri dan volume rendah, jangan untuk spam atau broadcast massal. Shared hosting juga harus benar-benar mendukung proses Node.js/WebSocket yang berjalan terus; bila provider sering menghentikan proses background, VPS lebih cocok untuk scheduler yang harus andal 24/7.
