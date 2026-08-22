@echo off
setlocal
cd /d "%~dp0bot"

if not exist ".env" (
    echo [GAGAL] bot\.env belum ada.
    echo Jalankan setup-local.bat terlebih dahulu.
    pause
    exit /b 1
)

if not exist "node_modules" (
    echo node_modules belum ada. Menjalankan npm install...
    call npm install
    if errorlevel 1 (
        echo [GAGAL] npm install gagal.
        pause
        exit /b 1
    )
)

echo Mengecek environment dan database...
call npm run check
if errorlevel 1 (
    echo.
    echo [GAGAL] Bot belum dijalankan karena environment/database belum siap.
    echo Pastikan database bot_wa_v1 sudah dibuat dan schema.sql sudah di-import.
    pause
    exit /b 1
)

echo.
echo Menjalankan Bot WA...
echo Jangan tutup jendela ini selama pengujian local.
echo Tekan Ctrl+C untuk menghentikan bot.
echo.
call npm start
pause
