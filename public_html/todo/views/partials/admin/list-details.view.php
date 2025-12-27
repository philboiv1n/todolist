<?php
/** @var array $list */
/** @var string $csrf */
/** @var array $users */
/** @var array $listAccess */
/** @var array $listCounts */
/** @var ?string $postPath */
/** @var ?int $excludeUserId */
/** @var ?int $disableRemoveUserId */

$postPath = isset($postPath) && is_string($postPath) && trim($postPath) !== ''
    ? trim($postPath)
    : 'admin.php';
$postPathAttr = \TodoApp\Security::h($postPath);
$excludeUserId = isset($excludeUserId) ? (int)$excludeUserId : null;
$disableRemoveUserId = isset($disableRemoveUserId) ? (int)$disableRemoveUserId : null;

$listId = (int)($list['id'] ?? 0);
$listName = \TodoApp\Security::h((string)($list['name'] ?? ''));
$ownerName = \TodoApp\Security::h((string)($list['owner_name'] ?? ''));
$createdAt = \TodoApp\Security::h((string)($list['created_at'] ?? ''));
$count = (int)($listCounts[$listId] ?? 0);
$access = $listAccess[$listId] ?? [];
?>

<details class="uk-margin">
    <summary class="uk-card uk-card-default uk-card-body uk-padding-small">
        <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">
            <div class="uk-margin-small-right">
                <span class="uk-text-bold"><?php echo $listName; ?></span>
            </div>
            <span class="uk-text-meta"><?php echo $count; ?> tasks</span>
        </div>
    </summary>

    <div class="uk-card uk-card-default uk-card-body uk-margin-small-top">
        <p class="uk-text-meta uk-margin-small">
            <?php if ($ownerName !== ''): ?>Owner: <?php echo $ownerName; ?><?php else: ?>Owner: none<?php endif; ?>
            <?php if ($createdAt !== ''): ?> · Created: <?php echo $createdAt; ?><?php endif; ?>
        </p>

        <div class="uk-grid-small uk-child-width-1-2@m" uk-grid>
            <div>
                <h4 class="uk-h4 uk-margin-small-bottom">Settings</h4>

	                <form method="post" action="<?php echo $postPathAttr; ?>" class="uk-form-stacked uk-margin-small">
	                    <input type="hidden" name="action" value="rename_list">
	                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
	                    <input type="hidden" name="list_id" value="<?php echo $listId; ?>">

	                    <div class="uk-grid-small uk-child-width-1-1" uk-grid>
	                        <div class="uk-width-expand@s">
	                            <label class="uk-form-label" for="rename-list-<?php echo $listId; ?>">New name</label>
	                            <div class="uk-form-controls">
	                                <input id="rename-list-<?php echo $listId; ?>" class="uk-input" type="text" name="name" value="<?php echo $listName; ?>" required>
	                            </div>
	                        </div>
	                        <div class="uk-width-auto@s uk-flex uk-flex-bottom">
	                            <button type="submit" class="uk-button uk-button-default uk-button-small uk-width-1-1 uk-width-auto@s">Rename</button>
	                        </div>
	                    </div>
	                </form>

		                <div class="uk-grid-small uk-child-width-1-1 uk-child-width-auto@s uk-margin-small-top" uk-grid>
		                    <form method="post" action="<?php echo $postPathAttr; ?>" onsubmit="return confirm('Delete all completed tasks in this list?');">
		                        <input type="hidden" name="action" value="clear_done_list">
		                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
		                        <input type="hidden" name="list_id" value="<?php echo $listId; ?>">
		                        <button type="submit" class="uk-button uk-button-default uk-button-small uk-width-1-1 uk-width-auto@s">Clear completed</button>
		                    </form>

		                    <form method="post" action="<?php echo $postPathAttr; ?>" onsubmit="return confirm('Delete this list and all its tasks?');">
		                        <input type="hidden" name="action" value="delete_list">
		                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
		                        <input type="hidden" name="list_id" value="<?php echo $listId; ?>">
		                        <button type="submit" class="uk-button uk-button-danger uk-button-small uk-width-1-1 uk-width-auto@s">Delete list</button>
		                    </form>
		                </div>
		            </div>

            <div>
                <h4 class="uk-h4 uk-margin-small-bottom">Access</h4>

                <form method="post" action="<?php echo $postPathAttr; ?>" class="uk-form-stacked uk-margin-small">
                    <input type="hidden" name="action" value="add_access">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="list_id" value="<?php echo $listId; ?>">

                    <div class="uk-grid-small" uk-grid>
                        <div class="uk-width-expand@s">
                            <label class="uk-form-label" for="access-user-<?php echo $listId; ?>">User</label>
                            <div class="uk-form-controls">
                                <select id="access-user-<?php echo $listId; ?>" class="uk-select" name="user_id" required>
                                    <?php foreach ($users as $u): ?>
                                        <?php if ($excludeUserId !== null && (int)($u['id'] ?? 0) === $excludeUserId) continue; ?>
                                        <option value="<?php echo (int)$u['id']; ?>">
                                            <?php echo \TodoApp\Security::h((string)($u['username'] ?? '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

	                        <div class="uk-width-auto@s uk-flex uk-flex-middle uk-flex-wrap">
	                            <label class="uk-margin-small-right">
	                                <input class="uk-checkbox" type="checkbox" name="can_edit" value="1" checked>
	                                Can edit
	                            </label>
	                            <button type="submit" class="uk-button uk-button-default">Save</button>
	                        </div>
	                    </div>
	                </form>

                <?php if (empty($access)): ?>
                    <p class="uk-text-muted uk-margin-small-top">No users yet.</p>
                <?php else: ?>
                    <ul class="uk-list uk-list-divider uk-margin-small-top">
                        <?php foreach ($access as $acc): ?>
                            <?php
                            $accUsername = \TodoApp\Security::h((string)($acc['username'] ?? ''));
                            $accMode = !empty($acc['can_edit']) ? 'edit' : 'view';
                            $accUserId = (int)$acc['user_id'];
                            ?>
                            <li>
                                <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">
                                    <div class="uk-margin-small-right">
                                        <?php echo $accUsername; ?>
                                        <span class="uk-text-meta">(<?php echo $accMode; ?>)</span>
                                    </div>
	                                    <?php if ($disableRemoveUserId !== null && $accUserId === $disableRemoveUserId): ?>
	                                        <span class="uk-text-meta">Owner</span>
	                                    <?php else: ?>
	                                        <form method="post" action="<?php echo $postPathAttr; ?>" class="uk-margin-remove">
	                                            <input type="hidden" name="action" value="remove_access">
	                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
	                                            <input type="hidden" name="list_id" value="<?php echo $listId; ?>">
	                                            <input type="hidden" name="user_id" value="<?php echo $accUserId; ?>">
	                                            <button type="submit" class="todo-action-x uk-button uk-button-link uk-text-danger uk-padding-remove-horizontal" aria-label="Remove access for <?php echo $accUsername; ?>" title="Remove">
	                                                ×
	                                            </button>
	                                        </form>
	                                    <?php endif; ?>
	                                </div>
	                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</details>
