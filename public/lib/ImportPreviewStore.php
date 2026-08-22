<?php

final class ImportPreviewStore
{
    public function __construct(private PDO $pdo)
    {
        $this->ensureTable();
        $this->cleanup();
    }

    public function save(array $preview): string
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare('INSERT INTO jadwal_import_preview (token,filename,tahun,payload_json,valid_count,warning_count,invalid_count,expires_at) VALUES (?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))');
        $stmt->execute([
            $token,
            $preview['filename'],
            $preview['year'],
            json_encode($preview['rows'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $preview['valid_count'],
            $preview['warning_count'],
            $preview['invalid_count'],
        ]);
        return $token;
    }

    public function get(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $stmt = $this->pdo->prepare('SELECT * FROM jadwal_import_preview WHERE token=? AND expires_at>NOW() LIMIT 1');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $rows = json_decode($row['payload_json'], true);
        if (!is_array($rows)) return null;
        return [
            'token' => $row['token'],
            'filename' => $row['filename'],
            'year' => (int)$row['tahun'],
            'rows' => $rows,
            'valid_count' => (int)$row['valid_count'],
            'warning_count' => (int)$row['warning_count'],
            'invalid_count' => (int)$row['invalid_count'],
        ];
    }

    public function delete(string $token): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM jadwal_import_preview WHERE token=?');
        $stmt->execute([$token]);
    }

    private function cleanup(): void
    {
        $this->pdo->exec('DELETE FROM jadwal_import_preview WHERE expires_at<=NOW()');
    }

    private function ensureTable(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS jadwal_import_preview (
            token CHAR(64) NOT NULL PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            tahun SMALLINT UNSIGNED NOT NULL,
            payload_json MEDIUMTEXT NOT NULL,
            valid_count INT UNSIGNED NOT NULL DEFAULT 0,
            warning_count INT UNSIGNED NOT NULL DEFAULT 0,
            invalid_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            KEY idx_import_preview_expires (expires_at)
        ) ENGINE=InnoDB");
    }
}
