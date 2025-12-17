<?php

namespace TodoApp;

use SQLite3;

class AdminLoginAttemptsController
{
    private SQLite3 $db;

    public function __construct(SQLite3 $db)
    {
        $this->db = $db;
    }

    /**
     * Handle the current request and return a view-model for `admin_attempts.view.php`.
     *
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $currentUser = requireAdmin($this->db, 'index.php');
        $csrfToken = Security::csrfToken();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $windowSeconds = $this->getAttemptsWindowSeconds();
        $sinceTs = time() - $windowSeconds;

        return [
            'currentUser' => $currentUser,
            'csrfToken' => $csrfToken,
            'windowSeconds' => $windowSeconds,
            'attemptsSummary' => Query::getLoginAttemptsSummary($this->db, $sinceTs),
            'recentAttempts' => Query::getRecentLoginAttempts($this->db, $sinceTs, 200),
        ];
    }

    private function getAttemptsWindowSeconds(): int
    {
        // By default, show the same rolling window used by rate limiting (5 minutes).
        $raw = getenv('TODO_LOGIN_ATTEMPTS_RETENTION_SECONDS');
        if (!is_string($raw) || trim($raw) === '') {
            $raw = $_SERVER['TODO_LOGIN_ATTEMPTS_RETENTION_SECONDS'] ?? '';
        }
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw !== '' && ctype_digit($raw)) {
            $v = (int)$raw;
            if ($v > 0 && $v <= 60 * 60 * 24 * 30) {
                return $v;
            }
        }
        return 300;
    }
}

