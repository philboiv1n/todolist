<?php
/** @var string $csrf */
?>
<section aria-labelledby="account-title" class="uk-card uk-card-default uk-card-body">
    <h2 id="account-title" class="uk-h4 uk-margin-small-bottom">Change password</h2>

    <form method="post" action="settings.php" class="uk-form-stacked">
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

        <div class="uk-margin">
            <label class="uk-form-label" for="current-password">Current password</label>
            <div class="uk-form-controls">
                <input id="current-password" class="uk-input" type="password" name="current_password" autocomplete="current-password" maxlength="<?php echo \TodoApp\Security::MAX_INPUT_LENGTH; ?>" required>
            </div>
        </div>

        <div class="uk-margin">
            <label class="uk-form-label" for="new-password">New password</label>
            <div class="uk-form-controls">
                <input id="new-password" class="uk-input" type="password" name="new_password" autocomplete="new-password" maxlength="<?php echo \TodoApp\Security::MAX_INPUT_LENGTH; ?>" required>
            </div>
        </div>

        <div class="uk-margin">
            <label class="uk-form-label" for="new-password-confirm">Confirm new password</label>
            <div class="uk-form-controls">
                <input id="new-password-confirm" class="uk-input" type="password" name="new_password_confirm" autocomplete="new-password" maxlength="<?php echo \TodoApp\Security::MAX_INPUT_LENGTH; ?>" required>
            </div>
        </div>

        <button type="submit" class="uk-button uk-button-primary uk-width-1-1 uk-width-auto@s">Update password</button>
    </form>
</section>
