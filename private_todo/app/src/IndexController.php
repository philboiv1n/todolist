<?php

namespace TodoApp;

use SQLite3;

class IndexController
{
    /**
     * Main controller for the non-admin UI.
     *
     * It handles POST actions (login / todo CRUD), then returns a view-model
     * array for the template to render.
     */
    private SQLite3 $db;
    private string $selfPath;
    private ?array $currentUser = null;
    private ?int $currentUserId;
    private ?string $currentUsername = null;
    private ?string $loginError = null;

    public function __construct(SQLite3 $db)
    {
        $this->db = $db;
        $this->currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $this->selfPath = $_SERVER['PHP_SELF'] ?? 'index.php';
    }

    /**
     * Handle the current request and return a view-model for `index.view.php`.
     *
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        // Request flow:
        // - Validate session user (if any)
        // - Handle logout/POST actions (redirect on success)
        // - Build view-model for GET rendering
        $this->loadCurrentUser();
        $this->handleLogout();
        $this->handlePost();

        $csrfToken = Security::csrfToken();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $userLists = $this->getPageData();

        return [
            'currentUserId' => $this->currentUserId,
            'currentUsername' => $this->currentUsername,
            'loginError' => $this->loginError,
            'csrfToken' => $csrfToken,
            'userLists' => $userLists,
            'currentUser' => $this->currentUser,
            'supportsRecurrence' => Query::todosSupportRecurrence($this->db),
        ];
    }

    private function loadCurrentUser(): void
    {
        if (!$this->currentUserId) {
            return;
        }

        $this->currentUser = Query::getUserById($this->db, $this->currentUserId);
        if ($this->currentUser) {
            $this->currentUsername = $this->currentUser['username'] ?? null;
            return;
        }

        session_destroy();
        redirect($this->selfPath);
    }

    private function handleLogout(): void
    {
        if (!isset($_GET['logout'])) {
            return;
        }

        session_destroy();
        redirect($this->selfPath);
    }

    private function handlePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        $wantsJson = $this->wantsJson();
        $action = $_POST['action'] ?? '';

        if ($action === 'login') {
            $this->handleLogin();
            return;
        }

        // All other actions require an authenticated user.
        if (!$this->currentUserId) {
            if ($wantsJson) {
                $this->respondJson(['ok' => false, 'redirect' => $this->selfPath], 401);
            }
            redirect($this->selfPath);
        }

        // Enforce CSRF on all mutating actions.
        if (!Security::csrfValid($_POST['csrf_token'] ?? '')) {
            if ($wantsJson) {
                $this->respondJson(['ok' => false, 'error' => 'Invalid CSRF token.'], 400);
            } else {
                http_response_code(400);
                echo 'Invalid CSRF token.';
                exit;
            }
        }

        $affectedListId = null;
        if ($action === 'set_list_expanded') {
            $result = $this->handleSetListExpanded();
            if ($wantsJson) {
                $this->respondJson(
                    $result,
                    ($result['ok'] ?? false) ? 200 : 400
                );
            }
            redirect($this->selfPath);
        }
        if ($action === 'add') {
            $affectedListId = $this->handleAddTodo();
        } elseif ($action === 'toggle') {
            $affectedListId = $this->handleToggleTodo();
        } elseif ($action === 'delete') {
            $affectedListId = $this->handleDeleteTodo();
        } elseif ($action === 'update_due_date') {
            $affectedListId = $this->handleUpdateDueDate();
        }

        if ($wantsJson) {
            if (!$affectedListId) {
                $this->respondJson(['ok' => false, 'error' => 'Action failed.'], 400);
            }

            $html = $this->renderListCardHtml($affectedListId);
            if ($html === null) {
                $this->respondJson(['ok' => false, 'error' => 'List not found.'], 404);
            }

            $this->respondJson([
                'ok' => true,
                'action' => $action,
                'list_id' => $affectedListId,
                'html' => $html,
            ]);
        }

        redirect($this->selfPath);
    }

    /**
     * Persist the UI expanded/collapsed state for a list (per-user).
     *
     * @return array{ok:bool, error?:string}
     */
    private function handleSetListExpanded(): array
    {
        $listId = (int)($_POST['list_id'] ?? 0);
        if ($listId <= 0) {
            return ['ok' => false, 'error' => 'Invalid list.'];
        }

        if (!Query::userHasListAccess($this->db, (int)$this->currentUserId, $listId)) {
            return ['ok' => false, 'error' => 'No access to that list.'];
        }

        $isExpanded = (int)($_POST['is_expanded'] ?? 0) === 1;
        if (!Query::setUserListExpandedState($this->db, (int)$this->currentUserId, $listId, $isExpanded)) {
            return ['ok' => false, 'error' => 'List state persistence is not enabled. Run the migration script.'];
        }

        return ['ok' => true];
    }

    private function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (is_string($accept) && str_contains($accept, 'application/json')) {
            return true;
        }
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    private function respondJson(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function handleLogin(): void
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $token = $_POST['csrf_token'] ?? '';

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!Security::csrfValid($token)) {
            $this->loginError = 'Security check failed. Please refresh and try again.';
        } elseif (Security::isLoginRateLimited($this->db, $clientIp)) {
            $this->loginError = 'Too many login attempts. Please wait a minute and try again.';
        } elseif ($username === '' || $password === '') {
            $this->loginError = 'Please enter username and password.';
        } else {
            $user = Query::getUserByUsername($this->db, $username);
            if ($user && password_verify($password, $user['password_hash'])) {
                // Prevent session fixation: rotate session ID on successful login.
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                Security::recordLoginAttempt($this->db, $clientIp, true);
                redirect($this->selfPath);
            } else {
                $this->loginError = 'Invalid username or password.';
            }
        }

        if ($this->loginError) {
            Security::recordLoginAttempt($this->db, $clientIp, false);
        }
    }

    private function handleAddTodo(): ?int
    {
        $title = trim($_POST['title'] ?? '');
        $dueDate = trim($_POST['due_date'] ?? '');
        $repeat = trim($_POST['repeat'] ?? '');
        $listId = (int)($_POST['list_id'] ?? 0);

        if ($title === '' || $listId <= 0) {
            return null;
        }
        if (!Query::userHasListAccess($this->db, $this->currentUserId, $listId, true)) {
            return null;
        }

        $repeatRule = Recurrence::buildRuleFromPreset($repeat, $dueDate);
        Query::createTodo($this->db, $this->currentUserId, $listId, $title, $dueDate, $repeatRule);
        return $listId;
    }

    private function handleToggleTodo(): ?int
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $todo = Query::fetchAccessibleTodo($this->db, $this->currentUserId, $id);
        if (!$todo) {
            return null;
        }

        Query::toggleTodoDoneWithRecurrence($this->db, $todo, $this->currentUserId);
        $listId = (int)($todo['list_id'] ?? 0);
        return $listId > 0 ? $listId : null;
    }

    private function handleDeleteTodo(): ?int
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $todo = Query::fetchAccessibleTodo($this->db, $this->currentUserId, $id);
        if (!$todo) {
            return null;
        }

        Query::deleteTodo($this->db, $id);
        $listId = (int)($todo['list_id'] ?? 0);
        return $listId > 0 ? $listId : null;
    }

    private function handleUpdateDueDate(): ?int
    {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $todo = Query::fetchAccessibleTodo($this->db, $this->currentUserId, $id);
        if (!$todo) {
            return null;
        }

        $rawDueDate = trim((string)($_POST['due_date'] ?? ''));
        if ($rawDueDate === '') {
            $dueDate = null;
        } else {
            $dateStr = substr($rawDueDate, 0, 10);
            if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $dateStr) !== 1) {
                return null;
            }

            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateStr);
            $errors = \DateTimeImmutable::getLastErrors();
            if (!$dt || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                return null;
            }

            $dueDate = $dt->format('Y-m-d');
        }

        Query::updateTodoDueDate($this->db, $id, $dueDate);
        $listId = (int)($todo['list_id'] ?? 0);
        return $listId > 0 ? $listId : null;
    }

    private function renderListCardHtml(int $listId): ?string
    {
        if (!$this->currentUserId) {
            return null;
        }

        $list = Query::getAccessibleListById($this->db, $this->currentUserId, $listId);
        if (!$list) {
            return null;
        }

        $todosByList = Query::getAllUserTodosByList($this->db, $this->currentUserId, [$listId]);
        $list['todos'] = $todosByList[$listId] ?? [];

        $csrf = Security::h(Security::csrfToken());

        return View::renderToString('partials/list-card.view.php', [
            'list' => $list,
            'csrf' => $csrf,
        ]);
    }

    private function getPageData(): array
    {
        if (!$this->currentUserId) {
            return [];
        }

        $lists = Query::getAccessibleLists($this->db, $this->currentUserId);
        $listIds = array_map(static fn(array $l): int => (int)$l['id'], $lists);
        $todosByList = Query::getAllUserTodosByList($this->db, $this->currentUserId, $listIds);

        $userLists = [];
        foreach ($lists as $list) {
            $listId = (int)$list['id'];
            $list['is_personal'] = !empty($list['is_personal']);
            $list['todos'] = $todosByList[$listId] ?? [];
            $userLists[] = $list;
        }

        return $userLists;
    }
}
