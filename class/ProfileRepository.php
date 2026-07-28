<?php

declare(strict_types=1);

namespace XoopsModules\Debugbar;

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/** Persistence boundary for compact request profiles. */
final class ProfileRepository
{
    private ?bool $tableExists = null;

    public function __construct(private readonly ?\XoopsMySQLDatabase $db = null)
    {
    }

    private function connection(): ?\XoopsMySQLDatabase
    {
        $db = $this->db;
        if ($db === null && isset($GLOBALS['xoopsDB']) && $GLOBALS['xoopsDB'] instanceof \XoopsMySQLDatabase) {
            $db = $GLOBALS['xoopsDB'];
        }

        return $db;
    }

    private function table(\XoopsMySQLDatabase $db): string
    {
        return $db->prefix('debugbar_profiles');
    }

    public function exists(): bool
    {
        if ($this->tableExists !== null) {
            return $this->tableExists;
        }

        $db = $this->connection();
        if ($db === null) {
            return $this->tableExists = false;
        }

        try {
            $table = addcslashes($this->table($db), '\\%_');
            $sql = 'SHOW TABLES LIKE ' . $db->quote($table);
            $result = $db->query($sql);

            return $this->tableExists = $db->isResultSet($result)
                && $result instanceof \mysqli_result
                && false !== $db->fetchRow($result);
        } catch (\Throwable) {
            return $this->tableExists = false;
        }
    }

    /** @param array<string, mixed> $row */
    public function insert(array $row, int $retentionDays = 7, int $maxRows = 10000): bool
    {
        try {
            $config = DebugbarCoreConfig::get();
            if (array_key_exists('profiles_enable', $config) && ! (bool) $config['profiles_enable']) {
                return false;
            }
        } catch (\Throwable) {
        }
        $db = $this->connection();
        if ($db === null || ! $this->exists()) {
            return false;
        }
        // Truncate to each column's real width, not one shared cap. A slowest_fp
        // is a normalised SQL fingerprint and routinely exceeds its VARCHAR(255);
        // under MySQL strict mode the oversized INSERT raises error 1406, which the
        // catch below swallows, so the profile would silently never be stored.
        $q = static fn (string $v, int $max): string => $db->quote(substr($v, 0, $max));
        $sql = sprintf(
            'INSERT INTO %s (request_id,created,url,url_hash,dirname,is_fragment,is_admin_side,total_ms,boot_ms,query_count,query_ms,slowest_ms,slowest_fp,n_plus_one,peak_mem_kb,payload_bytes,flags) VALUES (%s,%u,%s,%s,%s,%u,%u,%.1F,%.1F,%u,%.1F,%.1F,%s,%u,%u,%u,%u)',
            $this->table($db),
            $q((string) ($row['request_id'] ?? ''), 16),
            (int) ($row['created'] ?? time()),
            $q((string) ($row['url'] ?? ''), 500),
            $q((string) ($row['url_hash'] ?? ''), 32),
            $q((string) ($row['dirname'] ?? ''), 64),
            (int) (bool) ($row['is_fragment'] ?? false),
            (int) (bool) ($row['is_admin_side'] ?? false),
            (float) ($row['total_ms'] ?? 0),
            (float) ($row['boot_ms'] ?? 0),
            (int) ($row['query_count'] ?? 0),
            (float) ($row['query_ms'] ?? 0),
            (float) ($row['slowest_ms'] ?? 0),
            $q((string) ($row['slowest_fp'] ?? ''), 255),
            (int) ($row['n_plus_one'] ?? 0),
            (int) ($row['peak_mem_kb'] ?? 0),
            (int) ($row['payload_bytes'] ?? 0),
            (int) ($row['flags'] ?? 0)
        );

        try {
            $ok = $db->exec($sql);
            if ($ok && random_int(1, 25) === 1) {
                $this->trim($retentionDays, $maxRows);
            }

            return $ok;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function aggregates(int $days = 7, int $limit = 25): array
    {
        $db = $this->connection();
        if ($db === null) {
            return [];
        }

        return $this->fetch(sprintf('SELECT MAX(url) AS url,MAX(dirname) AS dirname,COUNT(*) AS hits,AVG(total_ms) AS avg_ms,MAX(total_ms) AS max_ms,AVG(query_count) AS avg_queries,MAX(n_plus_one) AS max_nplus1,SUM(flags <> 0) AS violations FROM %s WHERE created > %u GROUP BY url_hash ORDER BY avg_ms DESC LIMIT %u', $this->table($db), time() - max(1, $days) * 86400, max(1, $limit)));
    }

    /** @return list<array<string, mixed>> */
    public function worstUrls(int $days = 7, int $limit = 25): array
    {
        return $this->aggregates($days, $limit);
    }

    /** @return list<array<string, mixed>> */
    public function nPlusOneLeaders(int $days = 7, int $limit = 25): array
    {
        $db = $this->connection();
        if ($db === null) {
            return [];
        }

        return $this->fetch(sprintf(
            'SELECT MAX(url) AS url,MAX(dirname) AS dirname,COUNT(*) AS hits,MAX(n_plus_one) AS max_nplus1,AVG(query_count) AS avg_queries,MAX(slowest_fp) AS sample_fp FROM %s WHERE created > %u AND n_plus_one > 0 GROUP BY url_hash ORDER BY max_nplus1 DESC,avg_queries DESC LIMIT %u',
            $this->table($db),
            time() - max(1, $days) * 86400,
            max(1, $limit)
        ));
    }

    /** @return list<array<string, mixed>> */
    public function moduleAggregates(int $days = 7, int $limit = 100): array
    {
        $db = $this->connection();
        if ($db === null) {
            return [];
        }

        return $this->fetch(sprintf(
            "SELECT CASE WHEN dirname = '' THEN '—' ELSE dirname END AS dirname,COUNT(*) AS hits,AVG(total_ms) AS avg_ms,AVG(query_count) AS avg_queries,AVG(payload_bytes) / 1024 AS avg_payload_kb,SUM(is_fragment <> 0) AS fragment_hits,SUM(flags <> 0) AS violations FROM %s WHERE created > %u GROUP BY dirname ORDER BY avg_ms DESC LIMIT %u",
            $this->table($db),
            time() - max(1, $days) * 86400,
            max(1, $limit)
        ));
    }

    /** @return list<array<string, mixed>> */
    public function recentViolations(int $limit = 30): array
    {
        $db = $this->connection();
        if ($db === null) {
            return [];
        }

        return $this->fetch(sprintf('SELECT request_id,created,url,dirname,total_ms,query_count,n_plus_one,flags FROM %s WHERE flags <> 0 ORDER BY created DESC LIMIT %u', $this->table($db), max(1, $limit)));
    }

    public function count(): int
    {
        $db = $this->connection();
        if ($db === null) {
            return 0;
        }
        $rows = $this->fetch('SELECT COUNT(*) AS cnt FROM ' . $this->table($db));

        return (int) ($rows[0]['cnt'] ?? 0);
    }

    /** Alias of count() for the AnalyticsBuilder repository contract. */
    public function countRows(): int
    {
        return $this->count();
    }

    /** Alias of exists() for the AnalyticsBuilder repository contract. */
    public function tableExists(): bool
    {
        return $this->exists();
    }

    /**
     * Per-URL Core Web Vitals aggregates from stored RUM samples.
     *
     * @return list<array<string, mixed>>
     */
    public function vitalsByUrl(int $sinceDays = 7, int $limit = 20): array
    {
        $db = $this->connection();
        if ($db === null) {
            return [];
        }

        return $this->fetch(sprintf(
            'SELECT MAX(url) AS url, COUNT(*) AS samples,'
            . ' AVG(lcp_ms) AS avg_lcp, MAX(lcp_ms) AS max_lcp,'
            . ' AVG(inp_ms) AS avg_inp, MAX(inp_ms) AS max_inp,'
            . ' AVG(cls) AS avg_cls, MAX(cls) AS max_cls,'
            . ' AVG(total_ms) AS avg_server_ms'
            . ' FROM %s WHERE created > %u AND lcp_ms IS NOT NULL'
            . ' GROUP BY url_hash ORDER BY avg_lcp DESC LIMIT %u',
            $this->table($db),
            time() - ($sinceDays * 86400),
            max(1, $limit)
        ));
    }

    /**
     * Record client-reported Core Web Vitals against an existing recent
     * profile row. Bounds every value defensively and only touches rows from
     * the last hour keyed by a well-formed request id.
     */
    public function updateVitals(string $requestId, ?float $lcpMs, ?float $inpMs, ?float $cls): bool
    {
        $db = $this->connection();
        if ($db === null || ! $this->exists() || 1 !== preg_match('/^[0-9a-f]{16}$/', $requestId)) {
            return false;
        }

        $sets = [];
        if (null !== $lcpMs) {
            $sets[] = sprintf('lcp_ms = %.1F', max(0.0, min(600000.0, $lcpMs)));
        }
        if (null !== $inpMs) {
            $sets[] = sprintf('inp_ms = %.1F', max(0.0, min(600000.0, $inpMs)));
        }
        if (null !== $cls) {
            $sets[] = sprintf('cls = %.4F', max(0.0, min(99.0, $cls)));
        }
        if ([] === $sets) {
            return false;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE request_id = %s AND created > %u',
            $this->table($db),
            implode(', ', $sets),
            $db->quote($requestId),
            time() - 3600
        );

        try {
            return $db->exec($sql);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    private function fetch(string $sql): array
    {
        $db = $this->connection();
        if ($db === null || ! $this->exists()) {
            return [];
        }

        try {
            $result = $db->query($sql);
            if (! $db->isResultSet($result) || ! ($result instanceof \mysqli_result)) {
                return [];
            }
            $rows = [];
            while (false !== ($row = $db->fetchArray($result))) {
                $rows[] = $row;
            }

            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    private function trim(int $days, int $maxRows): void
    {
        $db = $this->connection();
        if ($db === null) {
            return;
        }

        try {
            if ($days > 0) {
                $db->exec(sprintf('DELETE FROM %s WHERE created < %u', $this->table($db), time() - $days * 86400));
            }
            $count = $this->count();
            if ($maxRows > 0 && $count > $maxRows) {
                $db->exec(sprintf('DELETE FROM %s ORDER BY profile_id ASC LIMIT %u', $this->table($db), $count - $maxRows));
            }
        } catch (\Throwable) {
        }
    }
}
