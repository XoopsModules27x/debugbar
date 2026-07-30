# DebugBar Module - profile storage
#
# One compact row per profiled request (Development/Debug mode only).
# Ring-buffer semantics: trimmed by retention days / max rows from config.
# NO foreign keys by design (XOOPS SqlUtility does not prefix REFERENCES
# targets, and this table needs no referential integrity anyway).
#
# Keep this in step with the CREATE TABLE in include/install.php; a test pins
# the two together, because they drifted once and cost the vitals columns.

CREATE TABLE debugbar_profiles (
    profile_id      INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    request_id      CHAR(16)          NOT NULL DEFAULT '',
    created         INT UNSIGNED      NOT NULL DEFAULT 0,
    url             VARCHAR(500)      NOT NULL DEFAULT '',
    url_hash        CHAR(32)          NOT NULL DEFAULT '',
    dirname         VARCHAR(64)       NOT NULL DEFAULT '',
    is_fragment     TINYINT(1)        NOT NULL DEFAULT 0,
    is_admin_side   TINYINT(1)        NOT NULL DEFAULT 0,
    total_ms        DECIMAL(10,1)     NOT NULL DEFAULT 0.0,
    boot_ms         DECIMAL(10,1)     NOT NULL DEFAULT 0.0,
    query_count     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    query_ms        DECIMAL(10,1)     NOT NULL DEFAULT 0.0,
    slowest_ms      DECIMAL(10,1)     NOT NULL DEFAULT 0.0,
    slowest_fp      VARCHAR(255)      NOT NULL DEFAULT '',
    n_plus_one      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    peak_mem_kb     INT UNSIGNED      NOT NULL DEFAULT 0,
    payload_bytes   INT UNSIGNED      NOT NULL DEFAULT 0,
    blocks_cached   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    blocks_uncached SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    flags           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    lcp_ms          DECIMAL(10,1)     NULL DEFAULT NULL,
    inp_ms          DECIMAL(10,1)     NULL DEFAULT NULL,
    cls             DECIMAL(6,4)      NULL DEFAULT NULL,
    PRIMARY KEY (profile_id),
    KEY idx_request (request_id),
    KEY idx_created (created),
    KEY idx_url_created (url_hash, created),
    KEY idx_dirname_created (dirname, created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
