<?php
/** @var string $csrf */
/** @var array $lists */
/** @var array $users */
/** @var array $listAccess */
/** @var array $listCounts */
?>
<section aria-labelledby="lists-title" class="uk-card uk-card-default uk-card-body">
    <h2 id="lists-title" class="uk-h3 uk-margin-small-bottom">Lists</h2>

    <section aria-labelledby="create-list-title" class="uk-margin">
        <h3 id="create-list-title" class="uk-h4 uk-margin-small-bottom">Create list</h3>
        <form method="post" action="admin.php" class="uk-form-stacked">
            <input type="hidden" name="action" value="create_list">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

            <div class="uk-grid-small" uk-grid>
                <div class="uk-width-expand@s">
                    <label class="uk-form-label" for="new-list-name">List name</label>
                    <div class="uk-form-controls">
                        <input id="new-list-name" class="uk-input" type="text" name="name" required>
                    </div>
                </div>
                <div class="uk-width-auto@s uk-flex uk-flex-bottom">
                    <button type="submit" class="uk-button uk-button-primary">Create</button>
                </div>
            </div>
        </form>
    </section>

    <hr class="uk-divider-small">

    <section aria-labelledby="existing-lists-title">
        <h3 id="existing-lists-title" class="uk-h4 uk-margin-small-bottom">Existing lists</h3>

        <?php if (empty($lists)): ?>
            <div class="uk-alert uk-alert-warning" role="status">
                <p>No lists yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($lists as $list): ?>
                <?php require __DIR__ . '/list-details.view.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</section>

