<?php

namespace TodoApp;

use SQLite3;

class AdminController
{
    /**
     * Controller for admin-only actions.
     *
     * Handles POST actions (user/list/access management) and returns a view-model
     * array for the admin template.
     */
    private SQLite3 $db;
    private array $currentUser;
    private string $csrfToken;
    private ?string $msg = null;
    private ?string $error = null;

    public function __construct(SQLite3 $db)
    {
        $this->db = $db;
    }

    /**
     * Handle the current request and return a view-model for `admin.view.php`.
     *
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        // Logout is handled early because it destroys the session and redirects.
        $this->handleLogout();

        // Ensures the current session is for an admin user.
        $this->currentUser = requireAdmin($this->db, 'index.php');
        $this->csrfToken = Security::csrfToken();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Handle admin POST actions (errors are surfaced in the view).
        $this->handlePost();

        $adminData = Query::getAdminDashboardData($this->db);

        return array_merge($adminData, [
            'currentUser' => $this->currentUser,
            'csrfToken' => $this->csrfToken,
            'msg' => $this->msg,
            'error' => $this->error,
        ]);
    }

    private function handleLogout(): void
    {
        if (!isset($_GET['logout'])) {
            return;
        }
        session_destroy();
        redirect('index.php');
    }

    private function handlePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        if (!Security::csrfValid($_POST['csrf_token'] ?? '')) {
            http_response_code(400);
            echo 'Invalid CSRF token.';
            exit;
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'add_user') {
            $this->handleAddUser();
        } elseif ($action === 'create_list') {
            $this->handleCreateList();
        } elseif ($action === 'rename_list') {
            $this->handleRenameList();
        } elseif ($action === 'add_access') {
            $this->handleAddAccess();
        } elseif ($action === 'remove_access') {
            $this->handleRemoveAccess();
        } elseif ($action === 'reset_password') {
            $this->handleResetPassword();
        } elseif ($action === 'toggle_admin') {
            $this->handleToggleAdmin();
        } elseif ($action === 'delete_user') {
            $this->handleDeleteUser();
        } elseif ($action === 'delete_list') {
            $this->handleDeleteList();
        } elseif ($action === 'clear_done_list') {
            $this->handleClearDoneList();
        }
    }

    private function handleAddUser(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $isAdmin = isset($_POST['is_admin']) ? 1 : 0;

        if ($username === '' || $password === '') {
            $this->error = 'Username and password are required.';
            return;
        }
        if (Security::exceedsMaxLength($username) || Security::exceedsMaxLength($password)) {
            $this->error = 'Username or password is too long (max 256 characters).';
            return;
        }
        if (Query::usernameExists($this->db, $username)) {
            $this->error = 'Username already exists.';
            return;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $newUserId = Query::createUser($this->db, $username, $hash, $isAdmin);
        Query::ensurePersonalList($this->db, $newUserId, $username);
        $this->msg = "User '$username' created.";
    }

    private function handleCreateList(): void
    {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $this->error = 'List name is required.';
            return;
        }
        if (Security::exceedsMaxLength($name)) {
            $this->error = 'List name is too long (max 256 characters).';
            return;
        }

        $newListId = Query::createList($this->db, $name, (int)$this->currentUser['id']);
        Query::addOrUpdateListAccess($this->db, $newListId, (int)$this->currentUser['id'], true);
        $this->msg = "List '$name' created.";
    }

    private function handleRenameList(): void
    {
        $listId = (int)($_POST['list_id'] ?? 0);
        $newName = trim($_POST['name'] ?? '');

        if ($listId <= 0 || $newName === '') {
            $this->error = 'List ID and new name are required.';
            return;
        }
        if (Security::exceedsMaxLength($newName)) {
            $this->error = 'List name is too long (max 256 characters).';
            return;
        }
        if (!Query::listExists($this->db, $listId)) {
            $this->error = 'List not found.';
            return;
        }

        Query::renameList($this->db, $listId, $newName);
        $this->msg = "List renamed to '$newName'.";
    }

    private function handleAddAccess(): void
    {
        $listId = (int)($_POST['list_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $canEdit = isset($_POST['can_edit']);

        if ($listId <= 0 || $userId <= 0) {
            $this->error = 'List and user are required.';
            return;
        }

        Query::addOrUpdateListAccess($this->db, $listId, $userId, $canEdit);
        $this->msg = 'Access updated.';
    }

    private function handleRemoveAccess(): void
    {
        $listId = (int)($_POST['list_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($listId <= 0 || $userId <= 0) {
            $this->error = 'List and user are required.';
            return;
        }

        Query::removeListAccess($this->db, $listId, $userId);
        $this->msg = 'Access removed.';
    }

    private function handleResetPassword(): void
    {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';

        if ($userId <= 0 || $newPass === '') {
            $this->error = 'User and new password required.';
            return;
        }
        if (Security::exceedsMaxLength($newPass)) {
            $this->error = 'Password is too long (max 256 characters).';
            return;
        }

        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        Query::setUserPassword($this->db, $userId, $hash);
        $this->msg = "Password updated for user ID $userId.";
    }

    private function handleToggleAdmin(): void
    {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0 || $userId === (int)$this->currentUser['id']) {
            $this->error = 'Cannot change your own admin status here.';
            return;
        }

        Query::toggleUserAdmin($this->db, $userId);
        $this->msg = "Admin flag toggled for user ID $userId.";
    }

    private function handleDeleteUser(): void
    {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0 || $userId === (int)$this->currentUser['id']) {
            $this->error = 'Cannot delete yourself.';
            return;
        }

        Query::deleteUser($this->db, $userId);
        $this->msg = "User ID $userId deleted (their todos stay, but owner becomes NULL).";
    }

    private function handleDeleteList(): void
    {
        $listId = (int)($_POST['list_id'] ?? 0);
        if ($listId <= 0) {
            $this->error = 'List id required.';
            return;
        }

        Query::deleteListAndTodos($this->db, $listId);
        $this->msg = 'List deleted.';
    }

    private function handleClearDoneList(): void
    {
        $listId = (int)($_POST['list_id'] ?? 0);
        if ($listId <= 0) {
            return;
        }
        Query::clearCompletedTodos($this->db, $listId);
        $this->msg = 'All completed tasks for that list have been deleted.';
    }

}
