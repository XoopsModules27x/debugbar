CREATE TABLE debugbar_profiles (
    profile_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id CHAR(16) NOT NULL DEFAULT '', created INT UNSIGNED NOT NULL DEFAULT 0,
    url VARCHAR(500) NOT NULL DEFAULT '', url_hash CHAR(32) NOT NULL DEFAULT '',
    dirname VARCHAR(64) NOT NULL DEFAULT '', is_fragment TINYINT(1) NOT NULL DEFAULT 0,
    is_admin_side TINYINT(1) NOT NULL DEFAULT 0, total_ms DECIMAL(10,1) NOT NULL DEFAULT 0,
    boot_ms DECIMAL(10,1) NOT NULL DEFAULT 0, query_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    query_ms DECIMAL(10,1) NOT NULL DEFAULT 0, slowest_ms DECIMAL(10,1) NOT NULL DEFAULT 0,
    slowest_fp VARCHAR(255) NOT NULL DEFAULT '', n_plus_one SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    peak_mem_kb INT UNSIGNED NOT NULL DEFAULT 0, payload_bytes INT UNSIGNED NOT NULL DEFAULT 0,
    flags SMALLINT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (profile_id), KEY idx_created (created),
    KEY idx_url_created (url_hash, created), KEY idx_dirname_created (dirname, created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
