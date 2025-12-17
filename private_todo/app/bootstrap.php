<?php
// Shared bootstrap for the todo app: autoloading, session setup, and DB connection.
if (!defined('TODO_APP_VERSION')) {
    define('TODO_APP_VERSION', 'v.0.7.0 (Beta)');
}

// Absolute paths (public entrypoints may define TODO_PUBLIC_DIR first).
if (!defined('TODO_PRIVATE_DIR')) {
    define('TODO_PRIVATE_DIR', dirname(__DIR__));
}
if (!defined('TODO_PUBLIC_DIR')) {
    define('TODO_PUBLIC_DIR', dirname(__DIR__, 2) . '/public_html/todo');
}
if (!defined('TODO_VIEWS_DIR')) {
    define('TODO_VIEWS_DIR', TODO_PUBLIC_DIR . '/views');
}

// --- ENV (optional .env file) ---
// On some shared hosts it's hard/impossible to set real environment variables.
// If `private_todo/.env` exists, load KEY=VALUE lines into the process env.
// Note: This does NOT override existing environment variables / server vars.
if (!function_exists('todo_load_dotenv')) {
    function todo_load_dotenv(string $path): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }

            $first = $value[0] ?? '';
            $last = $value !== '' ? $value[strlen($value) - 1] : '';
            if ($first === '"' && $last === '"' && strlen($value) >= 2) {
                $value = stripcslashes(substr($value, 1, -1));
            } elseif ($first === "'" && $last === "'" && strlen($value) >= 2) {
                $value = substr($value, 1, -1);
            } else {
                $commentPos = strpos($value, ' #');
                if ($commentPos !== false) {
                    $value = rtrim(substr($value, 0, $commentPos));
                }
            }

            // Don't override values already set by the server environment.
            if (getenv($key) !== false || array_key_exists($key, $_SERVER)) {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
todo_load_dotenv(TODO_PRIVATE_DIR . '/.env');

// --- AUTOLOADER ---
// Minimal PSR-4-like autoloader for the TodoApp namespace.
spl_autoload_register(static function (string $class): void {
    $prefix = 'TodoApp\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    require __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
});


// --- SESSION SETUP ---
// Keep sessions alive longer (90 days) so users stay logged in.
$longLifetime = 60 * 60 * 24 * 90;
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;

// --- BASIC SECURITY HEADERS (PHP responses only) ---
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if ($isSecure) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

$cookieParams = [
    'lifetime' => $longLifetime,
    'path' => '/',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Lax',
];
session_set_cookie_params($cookieParams);
ini_set('session.cookie_lifetime', (string)$longLifetime);
ini_set('session.gc_maxlifetime', (string)$longLifetime);
ini_set('session.gc_probability', '1');
ini_set('session.gc_divisor', '1000');
// Store session files outside of /tmp (often cleaned on shared hosting).
// If PHP is already configured to use a managed session directory, keep it.
$sessionDir = null;
$sessionDirEnv = getenv('TODO_SESSION_SAVE_PATH');
if (is_string($sessionDirEnv)) {
    $sessionDirEnv = trim($sessionDirEnv);
    if ($sessionDirEnv !== '' && strtolower($sessionDirEnv) !== 'default') {
        $sessionDir = $sessionDirEnv;
    }
}

if ($sessionDir === null) {
    $rawSavePath = (string)ini_get('session.save_path');
    $savePath = $rawSavePath;
    if (str_contains($rawSavePath, ';')) {
        $savePath = substr($rawSavePath, strrpos($rawSavePath, ';') + 1);
    }
    $savePath = trim($savePath);
    $looksLikeTmp = $savePath === '' || str_starts_with($savePath, '/tmp') || str_starts_with($savePath, '/var/tmp');
    if ($looksLikeTmp) {
        $sessionDir = TODO_PRIVATE_DIR . '/sessions';
    }
}

if ($sessionDir !== null) {
    if (!is_dir($sessionDir)) {
        mkdir($sessionDir, 0777, true);
    }
    ini_set('session.save_path', $sessionDir);
}
ini_set('session.use_strict_mode', '1');
session_start();

// --- DATABASE CONNECTION ---
$dbPath = TODO_PRIVATE_DIR . '/todo.sqlite';
if (!file_exists($dbPath)) {
    // If the database does not exist, the user must run the migration script.
    // This prevents the app from crashing with a fatal error.
    http_response_code(500);
    echo "Error: Database not found. Please run the migration script from the command line:\n";
    echo "<pre>php " . realpath(TODO_PRIVATE_DIR . '/migrations/migrate.php') . "</pre>";
    exit;
}
$db = new SQLite3($dbPath);
$db->exec('PRAGMA foreign_keys = ON');
$busyTimeoutMs = getenv('TODO_SQLITE_BUSY_TIMEOUT_MS');
$busyTimeoutMs = is_string($busyTimeoutMs) && ctype_digit($busyTimeoutMs) ? (int)$busyTimeoutMs : 5000;
$db->busyTimeout($busyTimeoutMs);

$journalMode = getenv('TODO_SQLITE_JOURNAL_MODE');
if (is_string($journalMode)) {
    $journalMode = strtoupper(trim($journalMode));
    $allowedJournalModes = ['WAL', 'DELETE', 'TRUNCATE', 'PERSIST', 'MEMORY', 'OFF'];
    if (in_array($journalMode, $allowedJournalModes, true)) {
        $db->exec("PRAGMA journal_mode = {$journalMode}");
    }
}

$synchronousSet = false;
$synchronous = getenv('TODO_SQLITE_SYNCHRONOUS');
if (is_string($synchronous)) {
    $synchronous = strtoupper(trim($synchronous));
    $allowedSynchronous = ['OFF', 'NORMAL', 'FULL', 'EXTRA'];
    if (in_array($synchronous, $allowedSynchronous, true)) {
        $db->exec("PRAGMA synchronous = {$synchronous}");
        $synchronousSet = true;
    }
}
if (!$synchronousSet) {
    $currentJournalMode = $db->querySingle('PRAGMA journal_mode');
    if (is_string($currentJournalMode) && strtoupper($currentJournalMode) === 'WAL') {
        $db->exec('PRAGMA synchronous = NORMAL');
    }
}
$db->exec('PRAGMA temp_store = MEMORY');

// --- FLOW HELPERS ---
if (!function_exists('redirect')) {
    /**
     * Send a redirect header and stop execution.
     *
     * Use for PRG (Post/Redirect/Get) flows so refresh doesn't re-submit forms.
     */
    function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}

if (!function_exists('requireLogin')) {
    /**
     * Ensure a user is logged in.
     *
     * @return int The logged-in user ID.
     */
    function requireLogin(string $redirectTo = 'index.php'): int
    {
        if (!isset($_SESSION['user_id'])) {
            redirect($redirectTo);
        }
        return (int)$_SESSION['user_id'];
    }
}

if (!function_exists('requireAdmin')) {
    /**
     * Ensure the current user is an admin and return their DB row.
     *
     * @return array User row from the `users` table.
     */
    function requireAdmin(SQLite3 $db, string $redirectTo = 'index.php'): array
    {
        $userId = requireLogin($redirectTo);

        // Optional extra hardening: restrict admin pages to specific IPs/CIDRs.
        // Format: comma/space-separated list, e.g. "1.2.3.4, 5.6.7.0/24, ::1".
        $allowlist = getenv('TODO_ADMIN_IP_ALLOWLIST');
        if (!is_string($allowlist) || trim($allowlist) === '') {
            $allowlist = $_SERVER['TODO_ADMIN_IP_ALLOWLIST'] ?? '';
        }
        $allowlist = is_string($allowlist) ? trim($allowlist) : '';
        if ($allowlist !== '') {
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
            $clientIp = is_string($clientIp) ? trim($clientIp) : '';
            $clientIp = filter_var($clientIp, FILTER_VALIDATE_IP) ? $clientIp : '';

            $ipInCidr = static function (string $ip, string $cidr): bool {
                $pos = strpos($cidr, '/');
                if ($pos === false) {
                    return false;
                }
                $baseIp = trim(substr($cidr, 0, $pos));
                $prefix = trim(substr($cidr, $pos + 1));
                if ($baseIp === '' || $prefix === '' || !ctype_digit($prefix)) {
                    return false;
                }

                $ipBin = inet_pton($ip);
                $baseBin = inet_pton($baseIp);
                if ($ipBin === false || $baseBin === false || strlen($ipBin) !== strlen($baseBin)) {
                    return false;
                }

                $prefixBits = (int)$prefix;
                $maxBits = strlen($ipBin) * 8;
                if ($prefixBits < 0 || $prefixBits > $maxBits) {
                    return false;
                }
                if ($prefixBits === 0) {
                    return true;
                }

                $bytes = intdiv($prefixBits, 8);
                $remainder = $prefixBits % 8;
                for ($i = 0; $i < $bytes; $i++) {
                    if ($ipBin[$i] !== $baseBin[$i]) {
                        return false;
                    }
                }
                if ($remainder === 0) {
                    return true;
                }

                $mask = (0xFF << (8 - $remainder)) & 0xFF;
                return (ord($ipBin[$bytes]) & $mask) === (ord($baseBin[$bytes]) & $mask);
            };

            $allowed = false;
            if ($clientIp !== '') {
                $items = preg_split('/[,\s]+/', $allowlist, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach ($items as $item) {
                    $item = trim((string)$item);
                    if ($item === '') {
                        continue;
                    }
                    if (str_contains($item, '/')) {
                        if ($ipInCidr($clientIp, $item)) {
                            $allowed = true;
                            break;
                        }
                        continue;
                    }
                    if (filter_var($item, FILTER_VALIDATE_IP) && $clientIp === $item) {
                        $allowed = true;
                        break;
                    }
                }
            }

            if (!$allowed) {
                http_response_code(403);
                echo 'Access denied.';
                exit;
            }
        }

        $user = \TodoApp\Query::getUserById($db, $userId);
        if (!$user) {
            session_destroy();
            redirect($redirectTo);
        }
        if ((int)$user['is_admin'] !== 1) {
            http_response_code(403);
            echo 'Access denied.';
            exit;
        }
        return $user;
    }
}
