<?php
/** @var array $ownedLists */
/** @var array $users */
/** @var array $listAccess */
/** @var array $listCounts */
/** @var int $selfUserId */
?>
<section aria-labelledby="existing-owned-lists-title" class="uk-card uk-card-default uk-card-body">
    <h2 id="existing-owned-lists-title" class="uk-h4 uk-margin-small-bottom">Existing lists</h2>

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

