<?php
/** @var string $csrf */
/** @var array $lists */
/** @var array $users */
/** @var array $listAccess */
/** @var array $listCounts */
?>
<section aria-labelledby="existing-lists-title" class="uk-card uk-card-default uk-card-body">
    <h2 id="existing-lists-title" class="uk-h4 uk-margin-small-bottom">Existing Lists</h2>

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
