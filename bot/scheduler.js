const { pool, query } = require('./db');
const { sendText, isConnected } = require('./whatsapp');

async function getSetting() {
    const rows = await query('SELECT * FROM pengaturan WHERE id=1 LIMIT 1');
    return rows[0];
}

async function processSchedules() {
    if (!isConnected()) return;

    const rows = await query(`SELECT j.id FROM jadwal_harian j WHERE j.status='pending' AND TIMESTAMP(j.tanggal,j.jam_berangkat_terpilih) <= NOW() AND TIMESTAMP(j.tanggal,j.jam_berangkat_terpilih) >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) ORDER BY j.tanggal,j.jam_berangkat_terpilih LIMIT 10`);

    for (const row of rows) {
        const conn = await pool.getConnection();
        try {
            await conn.beginTransaction();
            const [claim] = await conn.execute("UPDATE jadwal_harian SET status='diproses' WHERE id=? AND status='pending'", [row.id]);
            await conn.commit();
            if (!claim.affectedRows) continue;
        } catch (err) {
            await conn.rollback();
            conn.release();
            console.error('Gagal claim jadwal:', err.message);
            continue;
        }
        conn.release();

        const setting = await getSetting();
        try {
            await sendText(setting.nomor_wa_tujuan, setting.template_pesan);
            await query("UPDATE jadwal_harian SET status='terkirim', waktu_terkirim=NOW(), pesan_error=NULL WHERE id=?", [row.id]);
            await query("INSERT INTO log_pesan (jadwal_harian_id,isi_pesan,nomor_tujuan,status,waktu) VALUES (?,?,?,'terkirim',NOW())", [row.id, setting.template_pesan, setting.nomor_wa_tujuan]);
            console.log(`Jadwal #${row.id} terkirim.`);
        } catch (err) {
            await query("UPDATE jadwal_harian SET status='gagal', pesan_error=? WHERE id=?", [err.message, row.id]);
            await query("INSERT INTO log_pesan (jadwal_harian_id,isi_pesan,nomor_tujuan,status,waktu,pesan_error) VALUES (?,?,?,'gagal',NOW(),?)", [row.id, setting.template_pesan, setting.nomor_wa_tujuan, err.message]);
            console.error(`Jadwal #${row.id} gagal:`, err.message);
        }
    }
}

async function processCommands() {
    if (!isConnected()) return;
    const commands = await query("SELECT id FROM perintah_bot WHERE status='pending' ORDER BY id ASC LIMIT 5");

    for (const command of commands) {
        const claim = await query("UPDATE perintah_bot SET status='diproses' WHERE id=? AND status='pending'", [command.id]);
        if (!claim.affectedRows) continue;

        const setting = await getSetting();
        try {
            await sendText(setting.nomor_wa_tujuan, setting.template_pesan);
            await query("UPDATE perintah_bot SET status='selesai', processed_at=NOW(), pesan_error=NULL WHERE id=?", [command.id]);
            await query("INSERT INTO log_pesan (jadwal_harian_id,isi_pesan,nomor_tujuan,status,waktu) VALUES (NULL,?,?, 'terkirim',NOW())", [setting.template_pesan, setting.nomor_wa_tujuan]);
        } catch (err) {
            await query("UPDATE perintah_bot SET status='gagal', processed_at=NOW(), pesan_error=? WHERE id=?", [err.message, command.id]);
            await query("INSERT INTO log_pesan (jadwal_harian_id,isi_pesan,nomor_tujuan,status,waktu,pesan_error) VALUES (NULL,?,?,'gagal',NOW(),?)", [setting.template_pesan, setting.nomor_wa_tujuan, err.message]);
        }
    }
}

async function resetStuckJobs() {
    await query("UPDATE jadwal_harian SET status='pending' WHERE status='diproses' AND updated_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    await query("UPDATE perintah_bot SET status='pending' WHERE status='diproses' AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
}

async function tick() {
    try {
        await query('UPDATE bot_runtime SET heartbeat_at=NOW() WHERE id=1');
        await resetStuckJobs();
        await processSchedules();
        await processCommands();
    } catch (err) {
        console.error('Scheduler error:', err.message);
    }
}

function startScheduler() {
    const interval = Number(process.env.SCHEDULER_INTERVAL_MS || 30000);
    tick();
    return setInterval(tick, interval);
}

module.exports = { startScheduler, tick };
