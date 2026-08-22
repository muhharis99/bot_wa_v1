CREATE TABLE IF NOT EXISTS jadwal_import_preview (
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
) ENGINE=InnoDB;
