<?php
/** @var string $csrf */
/** @var array $ownedLists */
/** @var bool $supportsListOrdering */
/** @var array $orderedLists */
/** @var array $users */
/** @var array $listAccess */
/** @var array $listCounts */
/** @var int $selfUserId */
?>
<section aria-labelledby="my-lists-title" class="uk-card uk-card-default uk-card-body">
    <h2 id="my-lists-title" class="uk-h3 uk-margin-small-bottom">My lists</h2>

    <section aria-labelledby="create-list-title" class="uk-margin-bottom">
        <h3 id="create-list-title" class="uk-h4 uk-margin-small-bottom">Create a list</h3>
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

    <section aria-labelledby="existing-owned-lists-title" class="uk-margin-bottom">
        <h3 id="existing-owned-lists-title" class="uk-h4 uk-margin-small-bottom">Existing lists</h3>

        <?php if (empty($ownedLists)): ?>
            <div class="uk-alert uk-alert-warning" role="status">
                <p>No lists yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($ownedLists as $list): ?>
                <?php
                $postPath = 'settings.php';
                $disableRemoveUserId = $selfUserId;
                require __DIR__ . '/../admin/list-details.view.php';
                ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <?php require __DIR__ . '/list-order-section.view.php'; ?>
</section>
