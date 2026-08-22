require('dotenv').config();

const fs = require('fs');
const path = require('path');
const mysql = require('mysql2/promise');

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

        const [rows] = await conn.query(
            'SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = ?',
            [database]
        );

        const tableCount = Number(rows[0]?.total || 0);
        if (tableCount > 0) {
            console.log(`[OK] Database sudah memiliki ${tableCount} tabel. Import schema dilewati.`);
            return;
        }

        const schemaPath = path.resolve(__dirname, '..', 'database', 'schema.sql');
        if (!fs.existsSync(schemaPath)) throw new Error(`File schema tidak ditemukan: ${schemaPath}`);

        let sql = fs.readFileSync(schemaPath, 'utf8');

        // Schema asli boleh berisi CREATE DATABASE / USE. Untuk local maupun hosting,
        // paksa seluruh CREATE TABLE diarahkan ke database dari .env.
        sql = sql
            .replace(/^CREATE DATABASE[^;]*;\s*/gim, '')
            .replace(/^USE\s+[^;]+;\s*/gim, '');

        await conn.query(`USE \`${database.replace(/`/g, '``')}\``);
        await conn.query(sql);

        const [afterRows] = await conn.query(
            'SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = ?',
            [database]
        );

        console.log(`[OK] Schema berhasil di-import. Total tabel: ${Number(afterRows[0]?.total || 0)}.`);
    } finally {
        await conn.end();
    }
}

main().catch(err => {
    console.error('[GAGAL] Setup database:', err.message);
    process.exit(1);
});
