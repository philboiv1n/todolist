<?php

namespace TodoApp;

use SQLite3;

class AdminTodoDeletionsController
{
    private SQLite3 $db;

    public function __construct(SQLite3 $db)
    {
        $this->db = $db;
    }

    /**
     * Handle the current request and return a view-model for `admin_deletions.view.php`.
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

        $supportsDeletions = Query::todoDeletionsSupported($this->db);
        $deletions = $supportsDeletions ? Query::getRecentTodoDeletions($this->db, 200) : [];

        return [
            'currentUser' => $currentUser,
            'csrfToken' => $csrfToken,
            'supportsDeletions' => $supportsDeletions,
            'deletions' => $deletions,
        ];
    }
}
