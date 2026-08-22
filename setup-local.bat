@echo off
setlocal
cd /d "%~dp0"

 echo ==================================================
 echo  BOT WA V1 - SETUP LOCAL LARAGON
 echo ==================================================
 echo.

where node >nul 2>nul
if errorlevel 1 (
    echo [GAGAL] Node.js tidak ditemukan di PATH.
    echo Pastikan perintah: node -v bisa dijalankan.
    pause
    exit /b 1
)

for /f "tokens=*" %%i in ('node -v') do set NODE_VERSION=%%i
echo [OK] Node.js %NODE_VERSION%

echo Membuat konfigurasi local...
(
    echo APP_ENV=local
    echo DB_HOST=127.0.0.1
    echo DB_PORT=3307
    echo DB_NAME=bot_wa_v1
    echo DB_USER=root
    echo DB_PASS=
    echo DB_CONNECTION_LIMIT=5
    echo DB_TIMEZONE=+07:00
    echo DB_SSL=false
    echo TZ=Asia/Jakarta
    echo SCHEDULER_INTERVAL_MS=30000
    echo SESSION_DIR=./sessions
) > "bot\.env"

(
    echo APP_ENV=local
    echo DB_HOST=127.0.0.1
    echo DB_PORT=3307
    echo DB_NAME=bot_wa_v1
    echo DB_USER=root
    echo DB_PASS=
    echo DASHBOARD_USER=admin
    echo DASHBOARD_PASS=admin123
) > "public\.env"

echo [OK] bot\.env dan public\.env dibuat untuk Laragon MySQL port 3307.

echo.
echo Menginstall dependency Node.js...
pushd bot
call npm install
if errorlevel 1 (
    echo [GAGAL] npm install gagal.
    popd
    pause
    exit /b 1
)

echo.
echo Menyiapkan database MySQL...
call npm run setup:db
if errorlevel 1 (
    echo.
    echo [GAGAL] Database belum berhasil disiapkan.
    echo Pastikan MySQL Laragon aktif di port 3307.
    echo Jika user root memakai password, edit bot\.env dan public\.env.
    popd
    pause
    exit /b 1
)

echo.
echo Memeriksa environment...
call npm run check
if errorlevel 1 (
    echo [GAGAL] Pemeriksaan environment gagal.
    popd
    pause
    exit /b 1
)
popd

echo.
echo ==================================================
echo  SETUP LOCAL SELESAI
echo ==================================================
echo Database dan tabel sudah dicek otomatis.
echo.
echo Jalankan bot dengan:
echo   start-bot.bat
echo.
echo Buka dashboard:
echo   http://localhost/bot_wa_v1/public/
echo.
echo Login awal local:
echo   Username: admin
echo   Password: admin123
echo ==================================================
pause
