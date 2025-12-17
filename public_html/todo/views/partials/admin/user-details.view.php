<?php
/** @var array $user */
/** @var string $csrf */
/** @var int $selfUserId */

$userId = (int)($user['id'] ?? 0);
$username = \TodoApp\Security::h((string)($user['username'] ?? ''));
$isAdmin = !empty($user['is_admin']);
$isSelf = $userId === $selfUserId;
$newPasswordId = "new-password-{$userId}";
?>

<details class="uk-margin">
    <summary class="uk-card uk-card-default uk-card-body uk-padding-small">
        <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">
            <div class="uk-margin-small-right">
                <span class="uk-text-bold"><?php echo $username; ?></span>
                <span class="uk-text-meta">#<?php echo $userId; ?></span>
                <?php if ($isSelf): ?>
                    <span class="uk-text-meta">(You)</span>
                <?php endif; ?>
            </div>
            <?php if ($isAdmin): ?>
                <span class="uk-label uk-label-success">Admin</span>
            <?php else: ?>
                <span class="uk-text-muted">User</span>
            <?php endif; ?>
        </div>
    </summary>

    <div class="uk-card uk-card-default uk-card-body uk-margin-small-top">
        <div class="uk-grid-small uk-child-width-1-2@m" uk-grid>
            <div>
                <h4 class="uk-h4 uk-margin-small-bottom">Password</h4>

                <form method="post" action="admin.php" class="uk-form-stacked uk-margin-remove">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="user_id" value="<?php echo $userId; ?>">

                    <div class="uk-grid-small" uk-grid>
                        <div class="uk-width-expand@s">
                            <label class="uk-form-label" for="<?php echo $newPasswordId; ?>">New password</label>
                            <div class="uk-form-controls">
                                <input id="<?php echo $newPasswordId; ?>" class="uk-input uk-form-small" type="password" name="new_password" autocomplete="new-password" required>
                            </div>
                        </div>
                        <div class="uk-width-auto@s uk-flex uk-flex-bottom">
                            <button type="submit" class="uk-button uk-button-default uk-button-small">Set</button>
                        </div>
                    </div>
                </form>
            </div>

            <div>
                <h4 class="uk-h4 uk-margin-small-bottom">Actions</h4>

                <?php if ($isSelf): ?>
                    <p class="uk-text-meta uk-margin-remove">You can’t change your own admin flag or delete yourself here.</p>
                <?php else: ?>
                    <div class="uk-flex uk-flex-wrap uk-flex-middle">
                        <form method="post" action="admin.php" class="uk-margin-small-right uk-margin-small-bottom">
                            <input type="hidden" name="action" value="toggle_admin">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                            <button type="submit" class="uk-button uk-button-default uk-button-small">
                                <?php echo $isAdmin ? 'Remove admin' : 'Make admin'; ?>
                            </button>
                        </form>

                        <form method="post" action="admin.php" class="uk-margin-small-bottom" onsubmit="return confirm('Delete this user? Their todos will remain but be detached.');">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                            <button type="submit" class="uk-button uk-button-danger uk-button-small">Delete user</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</details>

