# Bot WA Notifikasi Berangkat Kerja

Bot WhatsApp pribadi berbasis Baileys + Node.js, dashboard PHP native, dan MySQL untuk mengirim notifikasi berangkat kerja otomatis berdasarkan jadwal shift.

## Fitur

- Login WhatsApp dengan QR dan session persisten.
- QR tersedia di terminal dan dashboard web.
- Scheduler otomatis setiap 30 detik dengan timezone Asia/Jakarta.
- Shift P/W/S dengan dua opsi jam berangkat.
- Kirim manual dari dashboard.
- Status koneksi dan log pengiriman.
- Login dashboard sederhana.
- Konfigurasi rahasia melalui `.env`.

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
└── README.md
```

## Instalasi ringkas

1. Buat database MySQL lalu import `database/schema.sql`.
2. Salin `bot/.env.example` menjadi `bot/.env` dan isi kredensial database.
3. Salin `public/.env.example` menjadi `public/.env` dan isi kredensial database serta login dashboard.
4. Jalankan `cd bot && npm install`.
5. Jalankan `node server.js`.
6. Buka folder `public` melalui domain/subdomain HTTPS, login, lalu scan QR.

Dokumentasi deployment cPanel lengkap tersedia pada bagian akhir README setelah seluruh source v1 selesai ditambahkan.
