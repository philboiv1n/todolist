<?php
// Standalone migration script to set up and patch the database schema.
echo "Starting migration...\n";

// --- DATABASE SETUP ---
$dbDir = __DIR__ . '/..';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0777, true);
}
$dbPath = $dbDir . '/todo.sqlite';
$db = new SQLite3($dbPath);
$db->exec('PRAGMA foreign_keys = ON');

echo "Database connection established at: {$dbPath}\n";

// --- MIGRATION HELPERS (copied from bootstrap.php) ---
function column_exists(SQLite3 $db, string $table, string $column): bool {
    $res = $db->query("PRAGMA table_info($table)");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        if ($row['name'] === $column) {
            return true;
        }
    }
    return false;
}

function ensure_list_schema(SQLite3 $db): void {
    echo "Ensuring core tables exist (users, lists, list_access, todos)...\n";
    $db->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            is_admin INTEGER NOT NULL DEFAULT 0
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS lists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            created_by INTEGER,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS list_access (
            list_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            can_edit INTEGER NOT NULL DEFAULT 1,
            PRIMARY KEY (list_id, user_id),
            FOREIGN KEY(list_id) REFERENCES lists(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS todos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            list_id INTEGER,
            title TEXT NOT NULL,
            due_date TEXT,
            is_done INTEGER NOT NULL DEFAULT 0,
            is_shared INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
        )'
    );

    // Patch older schemas
    if (!column_exists($db, 'todos', 'list_id')) {
        echo "Adding column 'list_id' to 'todos' table.\n";
        $db->exec('ALTER TABLE todos ADD COLUMN list_id INTEGER');
    }
    if (!column_exists($db, 'todos', 'due_date')) {
        echo "Adding column 'due_date' to 'todos' table.\n";
        $db->exec('ALTER TABLE todos ADD COLUMN due_date TEXT');
    }
    if (!column_exists($db, 'users', 'is_admin')) {
        echo "Adding column 'is_admin' to 'users' table.\n";
        $db->exec('ALTER TABLE users ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0');
    }

    // Used by Security::isLoginRateLimited() to store per-IP login failures.
    $db->exec(
        'CREATE TABLE IF NOT EXISTS login_attempts (
            ip TEXT NOT NULL,
            ts INTEGER NOT NULL
        )'
    );
}

function ensure_personal_list(SQLite3 $db, int $userId, string $username): int {
    // Starter list: create only if the user doesn't own any list yet.
    // This avoids re-creating a "Personal list" if the user renamed it.
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
        echo "Created 'Personal list' for user {$username} (ID: {$userId}).\n";
    }

    $stmt = $db->prepare(
        'INSERT OR IGNORE INTO list_access (list_id, user_id, can_edit) VALUES (:lid, :uid, 1)'
    );
    $stmt->bindValue(':lid', $listId, SQLITE3_INTEGER);
    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $stmt->execute();

    return $listId;
}

function ensure_personal_lists_for_all(SQLite3 $db): void {
    echo "Checking for missing personal lists for all users...\n";
    $res = $db->query('SELECT id, username FROM users');
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        ensure_personal_list($db, (int)$row['id'], $row['username']);
    }
}

function ensure_default_shared_list(SQLite3 $db, bool $createIfMissing = false): ?int {
    $res = $db->query("SELECT id FROM lists WHERE name = 'Shared'");
    $row = $res->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        return (int)$row['id'];
    }
    if (!$createIfMissing) {
        return null;
    }

    echo "Creating default 'Shared' list.\n";
    $db->exec("INSERT INTO lists (name) VALUES ('Shared')");
    $listId = (int)$db->lastInsertRowID();

    $userRes = $db->query('SELECT id FROM users');
    while ($u = $userRes->fetchArray(SQLITE3_ASSOC)) {
        $stmt = $db->prepare(
            'INSERT OR IGNORE INTO list_access (list_id, user_id, can_edit) VALUES (:lid, :uid, 1)'
        );
        $stmt->bindValue(':lid', $listId, SQLITE3_INTEGER);
        $stmt->bindValue(':uid', (int)$u['id'], SQLITE3_INTEGER);
        $stmt->execute();
    }
    echo "Granted all users access to 'Shared' list.\n";

    return $listId;
}

function migrate_todos_to_lists(SQLite3 $db): void {
    echo "Checking for legacy todos to migrate to the new list structure...\n";
    $todosToMigrate = (int)$db->querySingle('SELECT COUNT(*) FROM todos WHERE list_id IS NULL');
    if ($todosToMigrate === 0) {
        echo "No legacy todos found. Migration skipped.\n";
        return;
    }

    echo "Found {$todosToMigrate} legacy todos. Migrating them...\n";
    $sharedListId = ensure_default_shared_list($db, false);
    $res = $db->query('SELECT id, user_id, is_shared FROM todos WHERE list_id IS NULL');
    $migratedCount = 0;
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $listId = $sharedListId;
        $uid = $row['user_id'] ? (int)$row['user_id'] : null;

        if ($row['is_shared'] == 0 && $uid !== null) {
            $userRes = $db->query("SELECT username FROM users WHERE id = $uid");
            $userRow = $userRes->fetchArray(SQLITE3_ASSOC);
            if ($userRow) {
                $listId = ensure_personal_list($db, $uid, $userRow['username']);
            }
        } else {
            if ($listId === null) {
                $listId = ensure_default_shared_list($db, true);
                $sharedListId = $listId; // Cache for next loop
            }
        }
        $stmt = $db->prepare('UPDATE todos SET list_id = :lid WHERE id = :id');
        $stmt->bindValue(':lid', $listId, SQLITE3_INTEGER);
        $stmt->bindValue(':id', (int)$row['id'], SQLITE3_INTEGER);
        $stmt->execute();
        $migratedCount++;
    }
    echo "Migrated {$migratedCount} todos successfully.\n";
}

function ensure_default_user(SQLite3 $db): bool {
    $userCount = (int)$db->querySingle("SELECT COUNT(*) FROM users");
    if ($userCount > 0) {
        return false;
    }
    echo "No users found. Creating default 'admin' user with password 'changeme'.\n";
    $username = 'admin';
    $password = 'changeme';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password_hash, is_admin) VALUES (:u, :p, 1)");
    $stmt->bindValue(':u', $username, SQLITE3_TEXT);
    $stmt->bindValue(':p', $hash, SQLITE3_TEXT);
    $stmt->execute();
    echo "IMPORTANT: Default user 'admin' created. Please change the password!\n";
    return true;
}

function ensure_admin_present(SQLite3 $db): void {
    $adminCount = (int)$db->querySingle("SELECT COUNT(*) FROM users WHERE is_admin = 1");
    if ($adminCount > 0) {
        return;
    }
    echo "No admin user found. Attempting to promote an existing user...\n";
    // This logic is a bit arbitrary, but we preserve it from the original bootstrap
    $db->exec("UPDATE users SET is_admin = 1 WHERE username = 'parent'");
    $promotedCount = $db->changes();

    if ($promotedCount === 0) {
        $db->exec("UPDATE users SET is_admin = 1 WHERE id = (SELECT id FROM users ORDER BY id LIMIT 1)");
        $promotedCount = $db->changes();
    }

    if ($promotedCount > 0) {
        echo "Promoted {$promotedCount} user(s) to admin.\n";
    } else {
        echo "Could not find a user to promote to admin.\n";
    }
}

function ensure_indexes(SQLite3 $db): void {
    echo "Ensuring indexes exist...\n";

    // Speeds up: Query::getAccessibleLists (filter by user_id)
    $db->exec('CREATE INDEX IF NOT EXISTS idx_list_access_user_id ON list_access(user_id)');

    // Speeds up: todo fetches and counts by list_id
    $db->exec('CREATE INDEX IF NOT EXISTS idx_todos_list_id ON todos(list_id)');

    // Speeds up: login rate-limiting lookups (ip + time window)
    $db->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_ts ON login_attempts(ip, ts)');
}

// --- EXECUTE MIGRATIONS ---
echo "--- Running Schema & Data Migrations ---\n";
ensure_list_schema($db);
ensure_default_user($db);
ensure_admin_present($db);
ensure_personal_lists_for_all($db);
migrate_todos_to_lists($db);
ensure_indexes($db);
echo "--- Migration Complete ---\n";
