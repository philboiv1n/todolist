<?php

namespace TodoApp;

use SQLite3;

/**
 * Centralized DB access layer.
 *
 * This app is small, so this stays as a single static class rather than
 * multiple repositories/services.
 */
class Query
{
    /** @var ?array<string, bool> */
    private static ?array $todosColumnCache = null;

    /** @var ?array<string, bool> */
    private static ?array $listAccessColumnCache = null;

    /** @var ?bool */
    private static ?bool $appMetaTableCache = null;

    // --- USERS ---

    /** Fetch a user row by username. */
    public static function getUserByUsername(SQLite3 $db, string $username): ?array
    {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :u");
        $stmt->bindValue(':u', $username, SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        return $row ?: null;
    }

    /** Fetch a user row by ID. */
    public static function getUserById(SQLite3 $db, int $id): ?array
    {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        return $row ?: null;
    }

    // --- LISTS & ACCESS ---

    /** Fetch a list row by ID. */
    public static function getListById(SQLite3 $db, int $listId): ?array
    {
        $stmt = $db->prepare('SELECT * FROM lists WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $listId, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        return $row ?: null;
    }

    /**
     * Return lists the user has access to, including an access flag (`can_edit`).
     *
     * Also computes a helper flag (`is_personal`) for reference.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAccessibleLists(SQLite3 $db, int $userId): array
    {
        $supportsSortOrder = self::listAccessSupportsSortOrder($db);
        $sortOrderSelect = $supportsSortOrder ? 'list_access.sort_order AS sort_order,' : '0 AS sort_order,';
        $sortOrderClause = $supportsSortOrder ? 'list_access.sort_order ASC,' : '';
        $supportsExpandedState = self::listAccessSupportsExpandedState($db);
        $expandedSelect = $supportsExpandedState ? 'list_access.is_expanded AS is_expanded,' : '0 AS is_expanded,';

        $stmt = $db->prepare(
            "SELECT lists.*,
                    list_access.can_edit AS can_edit,
                    {$sortOrderSelect}
                    {$expandedSelect}
                    CASE
                        WHEN lists.id = (
                            SELECT id
                            FROM lists
                            WHERE created_by = :uid
                            ORDER BY created_at, id
                            LIMIT 1
                        ) THEN 1
                        ELSE 0
                    END AS is_personal
             FROM lists
             INNER JOIN list_access ON list_access.list_id = lists.id
             WHERE list_access.user_id = :uid
             ORDER BY {$sortOrderClause} lists.name"
        );
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $res = $stmt->execute();

        $lists = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $lists[] = $row;
        }
        return $lists;
    }

    /**
     * Return a single list the user can access (including `can_edit`), or null.
     *
     * @return ?array<string, mixed>
     */
    public static function getAccessibleListById(SQLite3 $db, int $userId, int $listId): ?array
    {
        $supportsSortOrder = self::listAccessSupportsSortOrder($db);
        $sortOrderSelect = $supportsSortOrder ? 'list_access.sort_order AS sort_order,' : '0 AS sort_order,';
        $supportsExpandedState = self::listAccessSupportsExpandedState($db);
        $expandedSelect = $supportsExpandedState ? 'list_access.is_expanded AS is_expanded,' : '0 AS is_expanded,';

        $stmt = $db->prepare(
            "SELECT lists.*,
                    list_access.can_edit AS can_edit,
                    {$sortOrderSelect}
                    {$expandedSelect}
                    CASE
                        WHEN lists.id = (
                            SELECT id
                            FROM lists
                            WHERE created_by = :uid
                            ORDER BY created_at, id
                            LIMIT 1
                        ) THEN 1
                        ELSE 0
                    END AS is_personal
             FROM lists
             INNER JOIN list_access ON list_access.list_id = lists.id
             WHERE list_access.user_id = :uid
               AND lists.id = :lid
             LIMIT 1"
        );
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':lid', $listId, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        return $row ?: null;
    }

    public static function listAccessSupportsSortOrder(SQLite3 $db): bool
    {
        $columns = self::getListAccessColumns($db);
        return isset($columns['sort_order']);
    }

    public static function listAccessSupportsExpandedState(SQLite3 $db): bool
    {
        $columns = self::getListAccessColumns($db);
        return isset($columns['is_expanded']);
    }

    /**
     * Return the global sync token for list/todo changes, or null if unsupported.
     */
    public static function getAppChangeToken(SQLite3 $db): ?int
    {
        if (!self::appMetaTableExists($db)) {
            return null;
        }

        $stmt = $db->prepare('SELECT value FROM app_meta WHERE meta_key = :k LIMIT 1');
        $stmt->bindValue(':k', 'last_change', SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        if (!$row) {
            return 0;
        }

        $value = $row['value'] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '' && ctype_digit($value)) {
            return (int)$value;
        }

        return 0;
    }

    /**
     * Update the global sync token after a list/todo change.
     */
    public static function touchAppChange(SQLite3 $db): bool
    {
        if (!self::appMetaTableExists($db)) {
            return false;
        }

        $now = (int)floor(microtime(true) * 1000);

        $stmt = $db->prepare(
            'INSERT OR IGNORE INTO app_meta (meta_key, value) VALUES (:k, :v)'
        );
        $stmt->bindValue(':k', 'last_change', SQLITE3_TEXT);
        $stmt->bindValue(':v', (string)$now, SQLITE3_TEXT);
        $stmt->execute();

        $stmt = $db->prepare('UPDATE app_meta SET value = :v WHERE meta_key = :k');
        $stmt->bindValue(':k', 'last_change', SQLITE3_TEXT);
        $stmt->bindValue(':v', (string)$now, SQLITE3_TEXT);
        $stmt->execute();

        return true;
    }

    /**
     * Check whether a user has access to a list.
     *
     * If `$requireEdit` is true, the access row must also have `can_edit = 1`.
     */
    public static function userHasListAccess(SQLite3 $db, int $userId, int $listId, bool $requireEdit = false): bool
    {
        $sql = "SELECT 1 FROM list_access WHERE list_id = :lid AND user_id = :uid";
        if ($requireEdit) {
            $sql .= " AND can_edit = 1";
        }
        $sql .= " LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':lid', $listId, SQLITE3_INTEGER);
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $res = $stmt->execute();
        return (bool)$res->fetchArray(SQLITE3_ASSOC);
    }

    /**
     * Return lists owned by the user (created_by = user ID), including owner username.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getListsOwnedByUser(SQLite3 $db, int $userId): array
    {
        $stmt = $db->prepare(
            'SELECT lists.id, lists.name, lists.created_by, lists.created_at, users.username AS owner_name
             FROM lists
             LEFT JOIN users ON users.id = lists.created_by
             WHERE lists.created_by = :uid
             ORDER BY lists.created_at, lists.id'
        );
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $res = $stmt->execute();

        $lists = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $lists[] = $row;
        }
        return $lists;
    }

    // --- TODOS ---

    /**
     * Fetch a todo by ID if the user can edit it.
     *
     * @return ?array<string, mixed>
     */
    public static function fetchAccessibleTodo(SQLite3 $db, int $userId, int $todoId): ?array
    {
        $stmt = $db->prepare(
            "SELECT todos.*
             FROM todos
             INNER JOIN list_access ON list_access.list_id = todos.list_id
             WHERE list_access.user_id = :uid
               AND list_access.can_edit = 1
               AND todos.id = :id
             LIMIT 1"
        );
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $todoId, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        return $row ?: null;
    }

    /**
     * Create a new todo item and return its ID.
     *
     * `$dueDate` is stored as an ISO date string (`YYYY-MM-DD`) or NULL.
     */
    public static function createTodo(
        SQLite3 $db,
        ?int $userId,
        int $listId,
        string $title,
        ?string $dueDate = null,
        ?string $repeatRule = null,
        ?int $repeatSourceId = null
    ): int
    {
        $columns = self::getTodosColumns($db);
        $insertCols = ['user_id', 'list_id', 'title', 'due_date'];
        $insertVals = [':uid', ':lid', ':title', ':due_date'];
        if (isset($columns['repeat_rule'])) {
            $insertCols[] = 'repeat_rule';
            $insertVals[] = ':repeat_rule';
        }
        if (isset($columns['repeat_source_id'])) {
            $insertCols[] = 'repeat_source_id';
            $insertVals[] = ':repeat_source_id';
        }

        $stmt = $db->prepare(
            "INSERT INTO todos (" . implode(', ', $insertCols) . ")
             VALUES (" . implode(', ', $insertVals) . ")"
        );
        if ($userId === null) {
            $stmt->bindValue(':uid', null, SQLITE3_NULL);
        } else {
            $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        }
        $stmt->bindValue(':lid', $listId, SQLITE3_INTEGER);
        $stmt->bindValue(':title', $title, SQLITE3_TEXT);

        if ($dueDate === null || $dueDate === '') {
            $stmt->bindValue(':due_date', null, SQLITE3_NULL);
        } else {
            $stmt->bindValue(':due_date', $dueDate, SQLITE3_TEXT);
        }

        if (isset($columns['repeat_rule'])) {
            if ($repeatRule === null || $repeatRule === '') {
                $stmt->bindValue(':repeat_rule', null, SQLITE3_NULL);
            } else {
                $stmt->bindValue(':repeat_rule', $repeatRule, SQLITE3_TEXT);
            }
        }
        if (isset($columns['repeat_source_id'])) {
            if ($repeatSourceId === null || $repeatSourceId <= 0) {
                $stmt->bindValue(':repeat_source_id', null, SQLITE3_NULL);
            } else {
                $stmt->bindValue(':repeat_source_id', $repeatSourceId, SQLITE3_INTEGER);
            }
        }

        $stmt->execute();
        self::touchAppChange($db);
        return (int)$db->lastInsertRowID();
    }

    /** Update a todo due date (`due_date`) by ID. */
    public static function updateTodoDueDate(SQLite3 $db, int $todoId, ?string $dueDate): void
    {
        $stmt = $db->prepare('UPDATE todos SET due_date = :due_date WHERE id = :id');
        if ($dueDate === null || $dueDate === '') {
            $stmt->bindValue(':due_date', null, SQLITE3_NULL);
        } else {
            $stmt->bindValue(':due_date', $dueDate, SQLITE3_TEXT);
        }
        $stmt->bindValue(':id', $todoId, SQLITE3_INTEGER);
        $stmt->execute();
        self::touchAppChange($db);
    }

    /** Toggle a todo's done flag (`is_done`). */
    public static function toggleTodoDone(SQLite3 $db, int $todoId): void
    {
        $stmt = $db->prepare(
            "UPDATE todos
             SET is_done = 1 - is_done
             WHERE id = :id"
        );
        $stmt->bindValue(':id', $todoId, SQLITE3_INTEGER);
        $stmt->execute();
        self::touchAppChange($db);
    }

    /**
     * Toggle `is_done` and, if the todo has a recurrence rule, create/remove the next occurrence.
     *
     * The provided `$todo` row should come from `fetchAccessibleTodo()` (it includes `todos.*`).
     *
     * @param array<string, mixed> $todo
     */
    public static function toggleTodoDoneWithRecurrence(SQLite3 $db, array $todo, ?int $actingUserId = null): void
    {
        $todoId = (int)($todo['id'] ?? 0);
        if ($todoId <= 0) {
            return;
        }

        $columns = self::getTodosColumns($db);
        $supportsRecurrence = isset($columns['repeat_rule'], $columns['repeat_source_id']);

        $wasDone = !empty($todo['is_done']);
        $markDone = !$wasDone;

        $stmt = $db->prepare('UPDATE todos SET is_done = :done WHERE id = :id');
        $stmt->bindValue(':done', $markDone ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':id', $todoId, SQLITE3_INTEGER);

        // Keep the toggle + any recurrence inserts/deletes atomic to avoid duplicates.
        $db->exec('BEGIN IMMEDIATE');
        try {
            $stmt->execute();
            self::touchAppChange($db);

            if (!$supportsRecurrence) {
                $db->exec('COMMIT');
                return;
            }

            $repeatRule = $todo['repeat_rule'] ?? null;
            $repeatRule = is_string($repeatRule) ? trim($repeatRule) : '';
            if ($repeatRule === '') {
                $db->exec('COMMIT');
                return;
            }

            if ($markDone) {
                // Only create a next occurrence once for this completion.
                $stmt = $db->prepare('SELECT id FROM todos WHERE repeat_source_id = :src LIMIT 1');
                $stmt->bindValue(':src', $todoId, SQLITE3_INTEGER);
                $res = $stmt->execute();
                $existing = $res->fetchArray(SQLITE3_ASSOC);
                if ($existing) {
                    $db->exec('COMMIT');
                    return;
                }

                $title = (string)($todo['title'] ?? '');
                $listId = (int)($todo['list_id'] ?? 0);
                if ($title === '' || $listId <= 0) {
                    $db->exec('COMMIT');
                    return;
                }

                $dueDate = $todo['due_date'] ?? null;
                $dueDate = is_string($dueDate) ? $dueDate : null;

                $nextDue = Recurrence::nextDueDate($dueDate, $repeatRule);
                if ($nextDue === null) {
                    $db->exec('COMMIT');
                    return;
                }

                $creatorId = $todo['user_id'] ?? null;
                $creatorId = is_int($creatorId) ? $creatorId : (is_string($creatorId) && ctype_digit($creatorId) ? (int)$creatorId : null);
                if ($creatorId === null) {
                    $creatorId = $actingUserId;
                }

                self::createTodo($db, $creatorId, $listId, $title, $nextDue, $repeatRule, $todoId);
                $db->exec('COMMIT');
                return;
            }

            // Un-done: remove the auto-generated next occurrence (if it still exists and isn't completed).
            $stmt = $db->prepare('DELETE FROM todos WHERE repeat_source_id = :src AND is_done = 0');
            $stmt->bindValue(':src', $todoId, SQLITE3_INTEGER);
            $stmt->execute();

            $db->exec('COMMIT');
        } catch (\Throwable $e) {
            $db->exec('ROLLBACK');
            throw $e;
        }
    }

    public static function todosSupportRecurrence(SQLite3 $db): bool
    {
        $columns = self::getTodosColumns($db);
        return isset($columns['repeat_rule'], $columns['repeat_source_id']);
    }

    /**
     * @return array<string, bool> Map: column name => true
     */
    private static function getTodosColumns(SQLite3 $db): array
    {
        if (self::$todosColumnCache !== null) {
            return self::$todosColumnCache;
        }

        $cols = [];
        $res = $db->query('PRAGMA table_info(todos)');
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $name = $row['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $cols[$name] = true;
            }
        }

        self::$todosColumnCache = $cols;
        return $cols;
    }

    /**
     * @return array<string, bool> Map: column name => true
     */
    private static function getListAccessColumns(SQLite3 $db): array
    {
        if (self::$listAccessColumnCache !== null) {
            return self::$listAccessColumnCache;
        }

        $cols = [];
        $res = $db->query('PRAGMA table_info(list_access)');
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $name = $row['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $cols[$name] = true;
            }
        }

        self::$listAccessColumnCache = $cols;
        return $cols;
    }

    private static function appMetaTableExists(SQLite3 $db): bool
    {
        if (self::$appMetaTableCache !== null) {
            return self::$appMetaTableCache;
        }

        $stmt = $db->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'app_meta' LIMIT 1");
        $res = $stmt->execute();
        $exists = (bool)$res->fetchArray(SQLITE3_ASSOC);
        self::$appMetaTableCache = $exists;
        return $exists;
    }

    /** Delete a todo by ID. */
    public static function deleteTodo(SQLite3 $db, int $todoId): void
    {
        $stmt = $db->prepare('DELETE FROM todos WHERE id = :id');
        $stmt->bindValue(':id', $todoId, SQLITE3_INTEGER);
        $stmt->execute();
        self::touchAppChange($db);
    }

    /**
     * Fetch all todos for a user for the given lists.
     *
     * @return array<int, array<int, array<string, mixed>>> Map: list_id => todo rows.
     */
    public static function getAllUserTodosByList(SQLite3 $db, int $userId, array $listIds): array
    {
        if (empty($listIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($listIds), '?'));
        $sql = "
            SELECT todos.*
            FROM todos
            INNER JOIN list_access ON list_access.list_id = todos.list_id
            WHERE list_access.user_id = ?
              AND todos.list_id IN ({$placeholders})
            ORDER BY
                todos.list_id,
                todos.is_done ASC,
                (todos.due_date IS NULL) ASC,
                todos.due_date ASC,
                todos.created_at DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
        foreach ($listIds as $i => $listId) {
            $stmt->bindValue($i + 2, $listId, SQLITE3_INTEGER);
        }

        $res = $stmt->execute();
        $todosByList = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $listId = (int)$row['list_id'];
            $todosByList[$listId][] = $row;
        }
        return $todosByList;
    }

    /**
     * Ensure the user has a "Personal list" and access to it; return its list ID.
     */
    public static function ensurePersonalList(SQLite3 $db, int $userId, string $username): int
    {
        // Treat the personal list as a default starter list: create one only if the
        // user doesn't own any list yet, and allow renaming/deleting like any other.
        $stmt = $db->prepare('SELECT id FROM lists WHERE created_by = :uid ORDER BY created_at, id LIMIT 1');
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            $listId = (int)$row['id'];
        } else {
            $stmt = $db->prepare('INSERT INTO lists (name, created_by) VALUES (:n, :uid)');
            $stmt->bindValue(':n', 'Personal list', SQLITE3_TEXT);
            $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
            $stmt->execute();
            $listId = (int)$db->lastInsertRowID();
        }

        $stmt = $db->prepare(
            'INSERT OR IGNORE INTO list_access (list_id, user_id, can_edit) VALUES (:lid, :uid, 1)'
        );
        $stmt->bindValue(':lid', $listId, SQLITE3_INTEGER);
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $stmt->execute();

        return $listId;
    }

    /** Check if a username already exists. */
    public static function usernameExists(SQLite3 $db, string $username): bool
    {
        $stmt = $db->prepare('SELECT 1 FROM users WHERE username = :u LIMIT 1');
        $stmt->bindValue(':u', $username, SQLITE3_TEXT);
        $res = $stmt->execute();
        return (bool)$res->fetchArray(SQLITE3_ASSOC);
    }

    /** Create a user and return their ID. */
    public static function createUser(SQLite3 $db, string $username, string $passwordHash, int $isAdmin = 0): int
    {
        $stmt = $db->prepare(
            'INSERT INTO users (username, password_hash, is_admin) VALUES (:u, :p, :a)'
        );
        $stmt->bindValue(':u', $username, SQLITE3_TEXT);
        $stmt->bindValue(':p', $passwordHash, SQLITE3_TEXT);
        $stmt->bindValue(':a', $isAdmin, SQLITE3_INTEGER);
        $stmt->execute();
        return (int)$db->lastInsertRowID();
    }

    /** Set the password hash for a user. */
    public static function setUserPassword(SQLite3 $db, int $userId, string $passwordHash): void
    {
        $stmt = $db->prepare('UPDATE users SET password_hash = :p WHERE id = :id');
        $stmt->bindValue(':p', $passwordHash, SQLITE3_TEXT);
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $stmt->execute();
    }

    /** Toggle a user's admin flag (`is_admin`). */
    public static function toggleUserAdmin(SQLite3 $db, int $userId): void
    {
        $stmt = $db->prepare(
            'UPDATE users
             SET is_admin = 1 - is_admin
             WHERE id = :id'
        );
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $stmt->execute();
    }

    /** Delete a user by ID. */
    public static function deleteUser(SQLite3 $db, int $userId): void
    {
        $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $stmt->execute();
    }

    /**
     * Return all users for the admin UI (no password hashes).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAllUsers(SQLite3 $db): array
    {
        $res = $db->query('SELECT id, username, is_admin FROM users ORDER BY username');
        $users = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $users[] = $row;
        }
        return $users;
    }

    /** Create a list and return its ID. */
    public static function createList(SQLite3 $db, string $name, int $creatorId): int
    {
        $stmt = $db->prepare('INSERT INTO lists (name, created_by) VALUES (:n, :uid)');
        $stmt->bindValue(':n', $name, SQLITE3_TEXT);
        $stmt->bindValue(':uid', $creatorId, SQLITE3_INTEGER);
        $stmt->execute();
        self::touchAppChange($db);
        return (int)$db->lastInsertRowID();
    }

    /** Check if a list exists. */
    public static function listExists(SQLite3 $db, int $listId): bool
    {
        $stmt = $db->prepare('SELECT 1 FROM lists WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $listId, SQLITE3_INTEGER);
        $res = $stmt->execute();
        return (bool)$res->fetchArray(SQLITE3_ASSOC);
    }

    /** Rename a list. */
    public static function renameList(SQLite3 $db, int $listId, string $newName): void
    {
        $stmt = $db->prepare('UPDATE lists SET name = :n WHERE id = :id');
        $stmt->bindValue(':n', $newName, SQLITE3_TEXT);
        $stmt->bindValue(':id', $listId, SQLITE3_INTEGER);
        $stmt->execute();
        self::touchAppChange($db);
    }

    /** Grant or update access for a user to a list. */
    public static function addOrUpdateListAccess(SQLite3 $db, int $listId, int $userId, bool $canEdit): void
    {
        if (self::listAccessSupportsSortOrder($db)) {
            $stmt = $db->prepare(
                'INSERT OR IGNORE INTO list_access (list_id, user_id, can_edit, sort_order)
                 VALUES (:lid, :uid, :ce, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM list_access WHERE user_id = :uid))'
            );
            $stmt->bindValue(':lid', $listId, SQLITE3_INTEGER);
            $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':ce', $canEdit ? 1 : 0, SQLITE3_INTEGER);
            $stmt->execute();
        } else {
            $stmt = $db->prepare(
                'INSERT OR IGNORE INTO list_access (list_id, user_id, can_edit) VALUES (:lid, :uid, :ce)'
            );
            $stmt->bindValue(':lid', $listId, SQLITE3_INTEGER);
            $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':ce', $canEdit ? 1 : 0, SQLITE3_INTEGER);
            $stmt->execute();
        }

        $stmt = $db->prepare(
            'UPDATE list_access SET can_edit = :ce WHERE list_id = :lid AND user_id = :uid'
        );
        $stmt->bindValue(':lid', $listId, SQLITE3_INTEGER);
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':ce', $canEdit ? 1 : 0, SQLITE3_INTEGER);
        $stmt->execute();

        self::touchAppChange($db);
    }

    /**
     * Persist a per-user ordering for the given list ids (first id => smallest sort order).
     *
     * @param array<int, int> $orderedListIds
     */
    public static function setUserListSortOrder(SQLite3 $db, int $userId, array $orderedListIds): void
    {
        if (!self::listAccessSupportsSortOrder($db)) {
            return;
        }

        $ordered = [];
        foreach ($orderedListIds as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ordered[] = $id;
            }
        }

        if (empty($ordered)) {
            return;
        }

        $db->exec('BEGIN');
        try {
            $stmt = $db->prepare(
                'UPDATE list_access SET sort_order = :so WHERE user_id = :uid AND list_id = :lid'
            );
            $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);

            foreach ($ordered as $i => $listId) {
                $stmt->bindValue(':lid', $listId, SQLITE3_INTEGER);
                $stmt->bindValue(':so', $i + 1, SQLITE3_INTEGER);
                $stmt->execute();
            }

            self::touchAppChange($db);
            $db->exec('COMMIT');
        } catch (\Throwable $e) {
            $db->exec('ROLLBACK');
            throw $e;
        }
    }

    public static function setUserListExpandedState(SQLite3 $db, int $userId, int $listId, bool $isExpanded): bool
    {
        if (!self::listAccessSupportsExpandedState($db)) {
            return false;
        }

        $stmt = $db->prepare(
            'UPDATE list_access SET is_expanded = :ie WHERE user_id = :uid AND list_id = :lid'
        );
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':lid', $listId, SQLITE3_INTEGER);
        $stmt->bindValue(':ie', $isExpanded ? 1 : 0, SQLITE3_INTEGER);
        $stmt->execute();
        return true;
    }

    /** Remove a user's access to a list. */
    public static function removeListAccess(SQLite3 $db, int $listId, int $userId): void
    {
        $stmt = $db->prepare('DELETE FROM list_access WHERE list_id = :lid AND user_id = :uid');
        $stmt->bindValue(':lid', $listId, SQLITE3_INTEGER);
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $stmt->execute();
        self::touchAppChange($db);
    }

    /** Delete all completed todos for a list. */
    public static function clearCompletedTodos(SQLite3 $db, int $listId): void
    {
        $stmt = $db->prepare('DELETE FROM todos WHERE list_id = :lid AND is_done = 1');
        $stmt->bindValue(':lid', $listId, SQLITE3_INTEGER);
        $stmt->execute();
        self::touchAppChange($db);
    }

    /** Delete a list and all related rows (todos + access). */
    public static function deleteListAndTodos(SQLite3 $db, int $listId): void
    {
        $stmt = $db->prepare('DELETE FROM todos WHERE list_id = :lid');
        $stmt->bindValue(':lid', $listId, SQLITE3_INTEGER);
        $stmt->execute();

        $stmt = $db->prepare('DELETE FROM list_access WHERE list_id = :lid');
        $stmt->bindValue(':lid', $listId, SQLITE3_INTEGER);
        $stmt->execute();

        $stmt = $db->prepare('DELETE FROM lists WHERE id = :lid');
        $stmt->bindValue(':lid', $listId, SQLITE3_INTEGER);
        $stmt->execute();

        self::touchAppChange($db);
    }

    /**
     * Return all lists for the admin UI, including owner username.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAllListsWithOwners(SQLite3 $db): array
    {
        $res = $db->query(
            'SELECT lists.id, lists.name, lists.created_by, lists.created_at, users.username AS owner_name
             FROM lists
             LEFT JOIN users ON users.id = lists.created_by
             ORDER BY lists.name'
        );

        $lists = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $lists[] = $row;
        }
        return $lists;
    }

    /**
     * Return access rows grouped by list ID.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function getListAccessByList(SQLite3 $db): array
    {
        $res = $db->query(
            'SELECT list_access.list_id, list_access.user_id, list_access.can_edit, users.username
             FROM list_access
             JOIN users ON users.id = list_access.user_id
             ORDER BY users.username'
        );

        $listAccess = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $lid = (int)$row['list_id'];
            if (!isset($listAccess[$lid])) {
                $listAccess[$lid] = [];
            }
            $listAccess[$lid][] = $row;
        }
        return $listAccess;
    }

    /**
     * Return access rows grouped by list ID for a set of list IDs.
     *
     * @param array<int, int> $listIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public static function getListAccessByListIds(SQLite3 $db, array $listIds): array
    {
        $listIds = array_values(array_unique(array_map('intval', $listIds)));
        $listIds = array_values(array_filter($listIds, static fn(int $id): bool => $id > 0));
        if (empty($listIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($listIds), '?'));
        $stmt = $db->prepare(
            'SELECT list_access.list_id, list_access.user_id, list_access.can_edit, users.username
             FROM list_access
             JOIN users ON users.id = list_access.user_id
             WHERE list_access.list_id IN (' . $placeholders . ')
             ORDER BY users.username'
        );
        foreach ($listIds as $i => $listId) {
            $stmt->bindValue($i + 1, $listId, SQLITE3_INTEGER);
        }
        $res = $stmt->execute();

        $listAccess = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $lid = (int)$row['list_id'];
            if (!isset($listAccess[$lid])) {
                $listAccess[$lid] = [];
            }
            $listAccess[$lid][] = $row;
        }
        return $listAccess;
    }

    /**
     * Count todos for each list ID.
     *
     * @return array<int, int> Map: list_id => count
     */
    public static function countTodosByList(SQLite3 $db): array
    {
        $res = $db->query('SELECT list_id, COUNT(*) AS c FROM todos GROUP BY list_id');
        $counts = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $counts[(int)$row['list_id']] = (int)$row['c'];
        }
        return $counts;
    }

    /**
     * Count todos for a set of list IDs.
     *
     * @param array<int, int> $listIds
     * @return array<int, int> Map: list_id => count
     */
    public static function countTodosByListIds(SQLite3 $db, array $listIds): array
    {
        $listIds = array_values(array_unique(array_map('intval', $listIds)));
        $listIds = array_values(array_filter($listIds, static fn(int $id): bool => $id > 0));
        if (empty($listIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($listIds), '?'));
        $stmt = $db->prepare(
            'SELECT list_id, COUNT(*) AS c
             FROM todos
             WHERE list_id IN (' . $placeholders . ')
             GROUP BY list_id'
        );
        foreach ($listIds as $i => $listId) {
            $stmt->bindValue($i + 1, $listId, SQLITE3_INTEGER);
        }
        $res = $stmt->execute();

        $counts = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $counts[(int)$row['list_id']] = (int)$row['c'];
        }
        return $counts;
    }

    /**
     * Convenience wrapper for everything the admin dashboard needs.
     *
     * @return array{users:array, lists:array, listAccess:array, listCounts:array}
     */
    public static function getAdminDashboardData(SQLite3 $db): array
    {
        return [
            'users' => self::getAllUsers($db),
            'lists' => self::getAllListsWithOwners($db),
            'listAccess' => self::getListAccessByList($db),
            'listCounts' => self::countTodosByList($db),
        ];
    }

    // --- LOGIN ATTEMPTS (admin visibility) ---

    private static function ensureLoginAttemptsTable(SQLite3 $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS login_attempts (
                ip TEXT NOT NULL,
                ts INTEGER NOT NULL
            )'
        );
        $db->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_ts ON login_attempts(ip, ts)');
    }

    /**
     * Return a per-IP summary of failed login attempts since `$sinceTs`.
     *
     * @return array<int, array{ip:string, c:int, last_ts:int}>
     */
    public static function getLoginAttemptsSummary(SQLite3 $db, int $sinceTs): array
    {
        self::ensureLoginAttemptsTable($db);

        $stmt = $db->prepare(
            'SELECT ip, COUNT(*) AS c, MAX(ts) AS last_ts
             FROM login_attempts
             WHERE ts >= :since
             GROUP BY ip
             ORDER BY last_ts DESC'
        );
        $stmt->bindValue(':since', $sinceTs, SQLITE3_INTEGER);
        $res = $stmt->execute();

        $rows = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Return recent failed login attempts since `$sinceTs` (newest first).
     *
     * @return array<int, array{ip:string, ts:int}>
     */
    public static function getRecentLoginAttempts(SQLite3 $db, int $sinceTs, int $limit = 200): array
    {
        self::ensureLoginAttemptsTable($db);

        $limit = max(1, min($limit, 1000));
        $stmt = $db->prepare(
            'SELECT ip, ts
             FROM login_attempts
             WHERE ts >= :since
             ORDER BY ts DESC
             LIMIT ' . $limit
        );
        $stmt->bindValue(':since', $sinceTs, SQLITE3_INTEGER);
        $res = $stmt->execute();

        $rows = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
    }
}
