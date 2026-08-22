require('dotenv').config();
const { query, appEnv, isLocal } = require('./db');

async function main() {
    console.log('=== Bot WA Environment Check ===');
    console.log(`APP_ENV : ${appEnv}`);
    console.log(`Node    : ${process.version}`);
    console.log(`DB Host : ${process.env.DB_HOST || (isLocal ? '127.0.0.1' : 'localhost')}`);
    console.log(`DB Port : ${process.env.DB_PORT || (isLocal ? '3307' : '3306')}`);
    console.log(`DB Name : ${process.env.DB_NAME || 'bot_wa_v1'}`);
    console.log(`Timezone: ${process.env.TZ || 'Asia/Jakarta'}`);

    const versionRows = await query('SELECT VERSION() AS version, NOW() AS now_db');
    console.log(`MySQL   : ${versionRows[0].version}`);
    console.log(`DB Time : ${versionRows[0].now_db}`);

    const requiredTables = ['pengaturan', 'shift', 'opsi_jam_berangkat', 'jadwal_harian', 'log_pesan', 'bot_runtime', 'perintah_bot'];
    const tableRows = await query('SHOW TABLES');
    const existing = new Set(tableRows.map(row => String(Object.values(row)[0])));
    const missing = requiredTables.filter(table => !existing.has(table));

    if (missing.length) throw new Error(`Tabel belum lengkap: ${missing.join(', ')}. Import database/schema.sql terlebih dahulu.`);

    const shifts = await query('SELECT kode_shift,nama_shift FROM shift ORDER BY id');
    console.log(`Shift   : ${shifts.map(s => s.kode_shift).join(', ') || '-'}`);
    console.log('STATUS  : OK - environment dan database siap.');
    process.exit(0);
}

main().catch(err => {
    console.error(`STATUS  : GAGAL - ${err.message}`);
    process.exit(1);
});
