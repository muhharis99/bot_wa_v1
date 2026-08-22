require('dotenv').config();
process.env.TZ = process.env.TZ || 'Asia/Jakarta';

const { query } = require('./db');
const { startWhatsApp, setStatus } = require('./whatsapp');
const { startScheduler } = require('./scheduler');

async function main() {
    console.log('Bot WA v1 mulai...');
    await query('SELECT 1');
    console.log('Database connected.');
    await startWhatsApp();
    startScheduler();
}

async function shutdown(signal) {
    console.log(`${signal} diterima, menghentikan service...`);
    try { await setStatus('terputus', `Service berhenti: ${signal}`); } catch (_) {}
    process.exit(0);
}

process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('unhandledRejection', err => console.error('Unhandled rejection:', err));
process.on('uncaughtException', err => console.error('Uncaught exception:', err));

main().catch(err => {
    console.error('Gagal menjalankan bot:', err);
    process.exit(1);
});
