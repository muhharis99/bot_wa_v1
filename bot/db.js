const mysql = require('mysql2/promise');
require('dotenv').config();

const appEnv = (process.env.APP_ENV || 'production').toLowerCase();
const isLocal = appEnv === 'local' || appEnv === 'development';

const pool = mysql.createPool({
    host: process.env.DB_HOST || (isLocal ? '127.0.0.1' : 'localhost'),
    port: Number(process.env.DB_PORT || (isLocal ? 3307 : 3306)),
    user: process.env.DB_USER || (isLocal ? 'root' : ''),
    password: process.env.DB_PASS || '',
    database: process.env.DB_NAME || 'bot_wa_v1',
    waitForConnections: true,
    connectionLimit: Number(process.env.DB_CONNECTION_LIMIT || 5),
    queueLimit: 0,
    timezone: process.env.DB_TIMEZONE || '+07:00',
    charset: 'utf8mb4',
    ssl: String(process.env.DB_SSL || 'false').toLowerCase() === 'true' ? { rejectUnauthorized: String(process.env.DB_SSL_REJECT_UNAUTHORIZED || 'true').toLowerCase() === 'true' } : undefined
});

async function query(sql, params = []) {
    const [rows] = await pool.execute(sql, params);
    return rows;
}

module.exports = { pool, query, appEnv, isLocal };
