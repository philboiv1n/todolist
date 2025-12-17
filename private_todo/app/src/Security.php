<?php

namespace TodoApp;

use SQLite3;

class Security
{
    /**
     * Small security helper: escaping, CSRF, and lightweight login rate limiting.
     *
     * Keeps security-related primitives in one place so controllers stay focused
     * on request flow and validation.
     */

    /** HTML-escape a string for safe output in templates. */
    public static function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Return the session CSRF token, creating one if needed.
     *
     * Use this to generate hidden form fields. Validate with `csrfValid()`.
     */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Validate a submitted CSRF token against the session token. */
    public static function csrfValid(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Keep only attempts within the rolling window.
     *
     * @param array<int, int> $attempts Timestamps
     * @return array<int, int>
     */
    private static function normalizeIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '' || strlen($ip) > 64) {
            return 'unknown';
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }

    private static function ensureLoginAttemptsTable(SQLite3 $db): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $db->exec(
            'CREATE TABLE IF NOT EXISTS login_attempts (
                ip TEXT NOT NULL,
                ts INTEGER NOT NULL
            )'
        );
        $db->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_ts ON login_attempts(ip, ts)');
        $ready = true;
    }

    private static function getLoginAttemptsRetentionSeconds(int $windowSeconds): int
    {
        $raw = getenv('TODO_LOGIN_ATTEMPTS_RETENTION_SECONDS');
        if (!is_string($raw) || trim($raw) === '') {
            $raw = $_SERVER['TODO_LOGIN_ATTEMPTS_RETENTION_SECONDS'] ?? '';
        }
        $raw = is_string($raw) ? trim($raw) : '';

        // Default: keep only the rate-limit window (small table on shared hosts).
        if ($raw === '' || !ctype_digit($raw)) {
            return $windowSeconds;
        }

        $v = (int)$raw;
        if ($v < $windowSeconds) {
            return $windowSeconds;
        }
        // Safety cap (avoid unbounded growth if misconfigured).
        return min($v, 60 * 60 * 24 * 30);
    }

    /**
     * Check whether an IP is rate-limited for login attempts.
     *
     * This uses SQLite (shared across sessions) so clearing cookies can't bypass
     * the limit.
     */
    public static function isLoginRateLimited(SQLite3 $db, string $ip, int $limit = 5, int $windowSeconds = 300): bool
    {
        self::ensureLoginAttemptsTable($db);

        $ip = self::normalizeIp($ip);
        $now = time();
        $cutoff = $now - $windowSeconds;
        $pruneCutoff = $now - self::getLoginAttemptsRetentionSeconds($windowSeconds);

        $stmt = $db->prepare('DELETE FROM login_attempts WHERE ts < :cutoff');
        $stmt->bindValue(':cutoff', $pruneCutoff, SQLITE3_INTEGER);
        $stmt->execute();

        $stmt = $db->prepare('SELECT COUNT(*) AS c FROM login_attempts WHERE ip = :ip AND ts >= :cutoff');
        $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
        $stmt->bindValue(':cutoff', $cutoff, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);

        return (int)($row['c'] ?? 0) >= $limit;
    }

    /**
     * Record a login attempt. On success, clears the attempt history for the IP.
     */
    public static function recordLoginAttempt(SQLite3 $db, string $ip, bool $success, int $windowSeconds = 300): void
    {
        self::ensureLoginAttemptsTable($db);

        $ip = self::normalizeIp($ip);
        $now = time();
        $pruneCutoff = $now - self::getLoginAttemptsRetentionSeconds($windowSeconds);

        $stmt = $db->prepare('DELETE FROM login_attempts WHERE ts < :cutoff');
        $stmt->bindValue(':cutoff', $pruneCutoff, SQLITE3_INTEGER);
        $stmt->execute();

        if ($success) {
            $stmt = $db->prepare('DELETE FROM login_attempts WHERE ip = :ip');
            $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
            $stmt->execute();
            return;
        }

        $stmt = $db->prepare('INSERT INTO login_attempts (ip, ts) VALUES (:ip, :ts)');
        $stmt->bindValue(':ip', $ip, SQLITE3_TEXT);
        $stmt->bindValue(':ts', $now, SQLITE3_INTEGER);
        $stmt->execute();
    }
}
