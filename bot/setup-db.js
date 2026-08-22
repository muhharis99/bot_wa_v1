require('dotenv').config();

const fs = require('fs');
const path = require('path');
const mysql = require('mysql2/promise');

async function ensureMasterData(conn) {
    await conn.query(`
        INSERT INTO shift (kode_shift,nama_shift,jam_mulai,jam_selesai) VALUES
        ('P','Pagi','07:00:00','14:00:00'),
        ('W','Siang','10:00:00','17:00:00'),
        ('S','Sore','14:00:00','20:00:00')
        ON DUPLICATE KEY UPDATE
            nama_shift=VALUES(nama_shift),
            jam_mulai=VALUES(jam_mulai),
            jam_selesai=VALUES(jam_selesai)
    `);

    const defaults = [
        ['P', '06:20:00'], ['P', '06:30:00'],
        ['W', '09:00:00'], ['W', '09:15:00'],
        ['S', '13:00:00'], ['S', '13:20:00']
    ];

    for (const [kode, jam] of defaults) {
        await conn.query(`
            INSERT IGNORE INTO opsi_jam_berangkat (shift_id,jam_berangkat)
            SELECT id, ? FROM shift WHERE kode_shift=?
        `, [jam, kode]);
    }

    await conn.query('INSERT INTO pengaturan (id) VALUES (1) ON DUPLICATE KEY UPDATE id=id');
    await conn.query('INSERT INTO bot_runtime (id) VALUES (1) ON DUPLICATE KEY UPDATE id=id');
}

async function main() {
    const host = process.env.DB_HOST || '127.0.0.1';
    const port = Number(process.env.DB_PORT || 3306);
    const user = process.env.DB_USER || 'root';
    const password = process.env.DB_PASS || '';
    const database = process.env.DB_NAME || 'bot_wa_v1';

    console.log('=== Setup Database Bot WA ===');
    console.log(`Host     : ${host}:${port}`);
    console.log(`Database : ${database}`);

    const conn = await mysql.createConnection({
        host,
        port,
        user,
        password,
        charset: 'utf8mb4',
        multipleStatements: true
    });

    try {
        await conn.query(`CREATE DATABASE IF NOT EXISTS \`${database.replace(/`/g, '``')}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`);
        await conn.query(`USE \`${database.replace(/`/g, '``')}\``);

        const [rows] = await conn.query(
            'SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = ?',
            [database]
        );

        const tableCount = Number(rows[0]?.total || 0);
        if (tableCount === 0) {
            const schemaPath = path.resolve(__dirname, '..', 'database', 'schema.sql');
            if (!fs.existsSync(schemaPath)) throw new Error(`File schema tidak ditemukan: ${schemaPath}`);

            let sql = fs.readFileSync(schemaPath, 'utf8');
            sql = sql
                .replace(/^CREATE DATABASE[^;]*;\s*/gim, '')
                .replace(/^USE\s+[^;]+;\s*/gim, '');

            await conn.query(sql);
            console.log('[OK] Schema berhasil di-import.');
        } else {
            console.log(`[OK] Database sudah memiliki ${tableCount} tabel. Schema tidak diimport ulang.`);
        }

        const requiredTables = ['shift', 'opsi_jam_berangkat', 'pengaturan', 'bot_runtime'];
        const [existingTables] = await conn.query(
            `SELECT table_name FROM information_schema.tables WHERE table_schema=? AND table_name IN (${requiredTables.map(() => '?').join(',')})`,
            [database, ...requiredTables]
        );
        const tableSet = new Set(existingTables.map(row => row.TABLE_NAME || row.table_name));
        const missing = requiredTables.filter(name => !tableSet.has(name));
        if (missing.length) throw new Error(`Struktur database belum lengkap. Tabel tidak ditemukan: ${missing.join(', ')}`);

        await ensureMasterData(conn);

        const [shiftRows] = await conn.query('SELECT kode_shift,nama_shift,jam_mulai,jam_selesai FROM shift ORDER BY id');
        console.log(`[OK] Master shift siap: ${shiftRows.map(s => s.kode_shift).join(', ')}.`);

        const [afterRows] = await conn.query(
            'SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = ?',
            [database]
        );
        console.log(`[OK] Database siap. Total tabel: ${Number(afterRows[0]?.total || 0)}.`);
    } finally {
        await conn.end();
    }
}

main().catch(err => {
    console.error('[GAGAL] Setup database:', err.message);
    process.exit(1);
});
