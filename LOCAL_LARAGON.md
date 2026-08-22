# Menjalankan Bot WA v1 di Local Laragon

Panduan ini disesuaikan dengan environment local:

- Folder project: `C:\laragon\www\bot_wa_v1`
- Apache Laragon: port 80/443
- MySQL Laragon: port `3307`
- PHP: 8.4.x
- Node.js: 22.x
- Database: `bot_wa_v1`
- Timezone: `Asia/Jakarta`

## Cara tercepat

1. Pastikan Apache dan MySQL pada Laragon dalam kondisi **Started**.
2. Buka terminal pada `C:\laragon\www\bot_wa_v1`.
3. Pull source terbaru:

```bash
git pull origin main
```

4. Jalankan:

```bat
setup-local.bat
```

Script tersebut akan:

- membuat `bot/.env` untuk MySQL `127.0.0.1:3307`;
- membuat `public/.env` untuk MySQL yang sama;
- menggunakan user MySQL local default `root` dengan password kosong;
- menetapkan `APP_ENV=local`;
- menjalankan `npm install` pada folder `bot`.

File `.env` tidak masuk Git sehingga konfigurasi local tidak akan menimpa konfigurasi cPanel.

## Membuat database

Buka HeidiSQL/phpMyAdmin Laragon lalu buat database:

```sql
CREATE DATABASE bot_wa_v1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Setelah itu import:

```text
database/schema.sql
```

Untuk database project lama yang sudah pernah dibuat, jalankan migration terbaru pada folder:

```text
database/migrations/
```

Jangan import ulang schema ke database production yang sudah berisi data.

## Menjalankan bot

Double click:

```text
start-bot.bat
```

atau dari terminal:

```bash
cd C:\laragon\www\bot_wa_v1\bot
npm run check
npm start
```

`npm run check` akan memeriksa koneksi database dan tabel wajib sebelum bot dijalankan.

## Membuka dashboard

Akses:

```text
http://localhost/bot_wa_v1/public/
```

Login local default dari `setup-local.bat`:

```text
Username: admin
Password: admin123
```

Login tersebut hanya untuk local development. Untuk cPanel gunakan password yang kuat pada `public/.env`.

## QR WhatsApp

Saat `start-bot.bat` aktif dan belum ada session WhatsApp:

1. buka dashboard local;
2. tunggu status `butuh_scan`;
3. QR akan muncul pada dashboard;
4. WhatsApp HP → Perangkat tertaut → Tautkan perangkat;
5. scan QR.

Session disimpan di:

```text
bot/sessions/
```

Folder tersebut di-ignore Git. Jangan hapus jika tidak ingin scan ulang.

## Jika MySQL local berbeda

Jika MySQL Anda tidak memakai port 3307, edit dua file berikut:

```text
bot/.env
public/.env
```

Contoh jika kembali memakai port standar 3306:

```env
APP_ENV=local
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=bot_wa_v1
DB_USER=root
DB_PASS=
```

Nilai database pada `bot/.env` dan `public/.env` harus sama.

## Local dan cPanel tidak bentrok

Source code yang sama digunakan di kedua environment. Perbedaannya hanya `.env`:

### Local Laragon

```env
APP_ENV=local
DB_HOST=127.0.0.1
DB_PORT=3307
DB_NAME=bot_wa_v1
DB_USER=root
DB_PASS=
```

### cPanel

```env
APP_ENV=production
DB_HOST=localhost
DB_PORT=3306
DB_NAME=cpanelprefix_botwa
DB_USER=cpanelprefix_user
DB_PASS=password_database
```

Node dan PHP membaca konfigurasi masing-masing dari `.env`, sehingga tidak perlu mengubah source saat berpindah environment.

## Update source berikutnya

```bash
cd C:\laragon\www\bot_wa_v1
git pull origin main
cd bot
npm install
npm run check
npm start
```

Tidak perlu menjalankan `setup-local.bat` lagi kecuali ingin membuat ulang konfigurasi local. Menjalankan script itu kembali akan menimpa `.env` local dengan nilai default Laragon.
