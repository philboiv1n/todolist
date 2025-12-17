<?php

namespace TodoApp;

use SQLite3;

class SettingsController
{
    /**
     * Settings page controller (for all logged-in users).
     *
     * Users can change their own password and manage their own lists (create,
     * rename, delete, clear completed, and share with other users).
     */
    private SQLite3 $db;
    private array $currentUser = [];
    private int $currentUserId = 0;
    private bool $isAdmin = false;
    private string $csrfToken = '';
    private ?string $msg = null;
    private ?string $error = null;

    public function __construct(SQLite3 $db)
    {
        $this->db = $db;
    }

    /**
     * Handle the current request and return a view-model for `settings.view.php`.
     *
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $this->currentUserId = requireLogin('index.php');
        $this->loadCurrentUser();

        $this->handlePost();

        $this->csrfToken = Security::csrfToken();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $ownedLists = Query::getListsOwnedByUser($this->db, $this->currentUserId);
        $listIds = array_map(static fn(array $l): int => (int)($l['id'] ?? 0), $ownedLists);
        $listIds = array_values(array_filter($listIds, static fn(int $id): bool => $id > 0));

        return [
            'currentUser' => $this->currentUser,
            'csrfToken' => $this->csrfToken,
            'msg' => $this->msg,
            'error' => $this->error,
            'ownedLists' => $ownedLists,
            'users' => Query::getAllUsers($this->db),
            'listAccess' => Query::getListAccessByListIds($this->db, $listIds),
            'listCounts' => Query::countTodosByListIds($this->db, $listIds),
        ];
    }

    private function loadCurrentUser(): void
    {
        $user = Query::getUserById($this->db, $this->currentUserId);
        if (!$user) {
            session_destroy();
            redirect('index.php');
        }
        $this->currentUser = $user;
        $this->isAdmin = !empty($user['is_admin']);
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

        if ($action === 'change_password') {
            $this->handleChangePassword();
        } elseif ($action === 'create_list') {
            $this->handleCreateList();
        } elseif ($action === 'rename_list') {
            $this->handleRenameList();
        } elseif ($action === 'delete_list') {
            $this->handleDeleteList();
        } elseif ($action === 'clear_done_list') {
            $this->handleClearDoneList();
        } elseif ($action === 'add_access') {
            $this->handleAddAccess();
        } elseif ($action === 'remove_access') {
            $this->handleRemoveAccess();
        }
    }

    private function requireManageableList(int $listId): array
    {
        if ($listId <= 0) {
            http_response_code(400);
            echo 'List id required.';
            exit;
        }

        $list = Query::getListById($this->db, $listId);
        if (!$list) {
            http_response_code(404);
            echo 'List not found.';
            exit;
        }

        $ownerId = (int)($list['created_by'] ?? 0);
        $canManage = $this->isAdmin || ($ownerId > 0 && $ownerId === $this->currentUserId);
        if (!$canManage) {
            http_response_code(403);
            echo 'Access denied.';
            exit;
        }

        return $list;
    }

    private function handleChangePassword(): void
    {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['new_password_confirm'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            $this->error = 'All password fields are required.';
            return;
        }
        if ($new !== $confirm) {
            $this->error = 'New passwords do not match.';
            return;
        }
        if (!password_verify($current, (string)($this->currentUser['password_hash'] ?? ''))) {
            $this->error = 'Current password is incorrect.';
            return;
        }

        $hash = password_hash($new, PASSWORD_DEFAULT);
        Query::setUserPassword($this->db, $this->currentUserId, $hash);

        // Rotate the session ID after a credential change.
        session_regenerate_id(true);

        $this->msg = 'Password updated.';
    }

    private function handleCreateList(): void
    {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $this->error = 'List name is required.';
            return;
        }

        $newListId = Query::createList($this->db, $name, $this->currentUserId);
        Query::addOrUpdateListAccess($this->db, $newListId, $this->currentUserId, true);
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

        $this->requireManageableList($listId);
        Query::renameList($this->db, $listId, $newName);
        $this->msg = "List renamed to '$newName'.";
    }

    private function handleDeleteList(): void
    {
        $listId = (int)($_POST['list_id'] ?? 0);
        $this->requireManageableList($listId);

        Query::deleteListAndTodos($this->db, $listId);
        $this->msg = 'List deleted.';
    }

    private function handleClearDoneList(): void
    {
        $listId = (int)($_POST['list_id'] ?? 0);
        $this->requireManageableList($listId);

        Query::clearCompletedTodos($this->db, $listId);
        $this->msg = 'All completed tasks for that list have been deleted.';
    }

    private function handleAddAccess(): void
    {
        $listId = (int)($_POST['list_id'] ?? 0);
        $list = $this->requireManageableList($listId);

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->error = 'User is required.';
            return;
        }

        $user = Query::getUserById($this->db, $userId);
        if (!$user) {
            $this->error = 'User not found.';
            return;
        }

        $ownerId = (int)($list['created_by'] ?? 0);
        $canEdit = isset($_POST['can_edit']);
        if ($ownerId > 0 && $userId === $ownerId) {
            $canEdit = true;
        }

        Query::addOrUpdateListAccess($this->db, $listId, $userId, $canEdit);
        $this->msg = 'Access updated.';
    }

    private function handleRemoveAccess(): void
    {
        $listId = (int)($_POST['list_id'] ?? 0);
        $list = $this->requireManageableList($listId);

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->error = 'User is required.';
            return;
        }

        $ownerId = (int)($list['created_by'] ?? 0);
        if ($ownerId > 0 && $userId === $ownerId) {
            $this->error = 'You cannot remove the list owner.';
            return;
        }

        Query::removeListAccess($this->db, $listId, $userId);
        $this->msg = 'Access removed.';
    }
}

