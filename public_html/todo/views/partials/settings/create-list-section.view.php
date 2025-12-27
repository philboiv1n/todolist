<?php
/** @var string $csrf */
?>
<section aria-labelledby="create-list-title" class="uk-card uk-card-default uk-card-body">
    <h2 id="create-list-title" class="uk-h4 uk-margin-small-bottom">Create a list</h2>

    <form method="post" action="settings.php" class="uk-form-stacked">
        <input type="hidden" name="action" value="create_list">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

        <div class="uk-grid-small" uk-grid>
            <div class="uk-width-expand@s">
                <label class="uk-form-label" for="new-owned-list-name">List name</label>
                <div class="uk-form-controls">
                    <input id="new-owned-list-name" class="uk-input" type="text" name="name" required>
                </div>
            </div>
            <div class="uk-width-auto@s uk-flex uk-flex-bottom">
                <button type="submit" class="uk-button uk-button-primary">Create</button>
            </div>
        </div>
    </form>
</section>

