<?php
/** @var string $csrf */
/** @var array $users */
/** @var int $selfUserId */
?>
<section aria-labelledby="create-user-title" class="uk-card uk-card-default uk-card-body uk-margin">
    <h2 id="create-user-title" class="uk-h4 uk-margin-small-bottom">Create User</h2>
    <form method="post" action="admin.php" class="uk-form-stacked">
        <input type="hidden" name="action" value="add_user">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

        <div class="uk-grid-small uk-child-width-1-3@s" uk-grid>
            <div>
                <label class="uk-form-label" for="new-username">Username</label>
                <div class="uk-form-controls">
                    <input id="new-username" class="uk-input" type="text" name="username" autocapitalize="none" spellcheck="false" required>
                </div>
            </div>

            <div>
                <label class="uk-form-label" for="new-password">Password</label>
                <div class="uk-form-controls">
                    <input id="new-password" class="uk-input" type="password" name="password" autocomplete="new-password" required>
                </div>
            </div>

            <div class="uk-flex uk-flex-middle uk-flex-between uk-flex-wrap">
                <label class="uk-margin-small-right">
                    <input class="uk-checkbox" type="checkbox" name="is_admin" value="1">
                    Admin
                </label>
                <button type="submit" class="uk-button uk-button-primary">Add user</button>
            </div>
        </div>
    </form>
</section>

<section aria-labelledby="existing-users-title" class="uk-card uk-card-default uk-card-body">
    <h2 id="existing-users-title" class="uk-h4 uk-margin-small-bottom">Existing Users</h2>

    <?php if (empty($users)): ?>
        <p class="uk-text-muted">No users yet.</p>
    <?php else: ?>
        <?php foreach ($users as $user): ?>
            <?php require __DIR__ . '/user-details.view.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
