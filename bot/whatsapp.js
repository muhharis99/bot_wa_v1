const path = require('path');
const QRCode = require('qrcode');
const pino = require('pino');
const { Boom } = require('@hapi/boom');
const { default: makeWASocket, DisconnectReason, useMultiFileAuthState } = require('@whiskeysockets/baileys');
const { query } = require('./db');

let sock = null;
let connected = false;
let reconnectTimer = null;

async function setStatus(status, info = null) {
    await query('UPDATE pengaturan SET status_koneksi_bot=? WHERE id=1', [status]);
    await query('UPDATE bot_runtime SET info=?, heartbeat_at=NOW() WHERE id=1', [info]);
}

async function startWhatsApp() {
    const sessionDir = path.resolve(process.env.SESSION_DIR || './sessions');
    const { state, saveCreds } = await useMultiFileAuthState(sessionDir);

    await setStatus('menghubungkan', 'Menghubungkan ke WhatsApp');

    sock = makeWASocket({
        auth: state,
        logger: pino({ level: 'silent' }),
        printQRInTerminal: true,
        browser: ['Bot WA Berangkat Kerja', 'Chrome', '1.0.0'],
        markOnlineOnConnect: false,
        syncFullHistory: false
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async ({ connection, lastDisconnect, qr }) => {
        try {
            if (qr) {
                const dataUrl = await QRCode.toDataURL(qr, { width: 360, margin: 2 });
                await query('UPDATE bot_runtime SET qr_base64=?, qr_updated_at=NOW(), info=? WHERE id=1', [dataUrl, 'Scan QR dari dashboard']);
                await setStatus('butuh_scan', 'Menunggu scan QR');
                console.log('QR baru tersedia. Scan dari terminal atau dashboard.');
            }

            if (connection === 'open') {
                connected = true;
                await query('UPDATE bot_runtime SET qr_base64=NULL, qr_updated_at=NULL, heartbeat_at=NOW(), info=? WHERE id=1', ['WhatsApp terhubung']);
                await setStatus('connected', 'WhatsApp terhubung');
                console.log('WhatsApp connected.');
            }

            if (connection === 'close') {
                connected = false;
                const code = new Boom(lastDisconnect?.error)?.output?.statusCode;
                await setStatus('terputus', `Koneksi terputus (${code || 'unknown'})`);

                if (code === DisconnectReason.loggedOut) {
                    console.error('Session logout. Hapus folder sessions bila ingin login ulang.');
                    await setStatus('butuh_scan', 'Session logout, perlu scan ulang');
                    return;
                }

                clearTimeout(reconnectTimer);
                reconnectTimer = setTimeout(() => startWhatsApp().catch(console.error), 5000);
            }
        } catch (err) {
            console.error('connection.update error:', err.message);
        }
    });

    return sock;
}

function normalizeNumber(number) {
    let value = String(number || '').replace(/\D/g, '');
    if (value.startsWith('0')) value = '62' + value.slice(1);
    if (!value.startsWith('62')) value = '62' + value;
    return `${value}@s.whatsapp.net`;
}

async function sendText(number, text) {
    if (!sock || !connected) throw new Error('WhatsApp belum connected');
    return sock.sendMessage(normalizeNumber(number), { text });
}

function isConnected() {
    return connected;
}

module.exports = { startWhatsApp, sendText, isConnected, setStatus };
