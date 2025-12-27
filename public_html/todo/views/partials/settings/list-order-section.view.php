<?php
/** @var string $csrf */
/** @var bool $supportsListOrdering */
/** @var array $orderedLists */
?>
<section id="list-order" aria-labelledby="list-order-title" class="uk-card uk-card-default uk-card-body">
    <h2 id="list-order-title" class="uk-h4 uk-margin-small-bottom">List order</h2>

    <?php if (empty($supportsListOrdering)): ?>
        <div class="uk-alert uk-alert-warning" role="status">
            <p>List ordering is not enabled yet. Run the migration script to enable it.</p>
        </div>
    <?php elseif (empty($orderedLists)): ?>
        <p class="uk-text-muted">No other lists to reorder yet.</p>
    <?php else: ?>
        <p class="uk-text-meta uk-margin-small">
            Reorder how lists appear on the home page. Your personal list stays pinned at the top.
        </p>

        <ul class="uk-list uk-list-divider uk-margin-small-top">
            <?php foreach ($orderedLists as $orderedList): ?>
                <?php
                $orderedListId = (int)($orderedList['id'] ?? 0);
                $orderedListName = \TodoApp\Security::h((string)($orderedList['name'] ?? ''));
                $canMoveUp = !empty($orderedList['can_move_up']);
                $canMoveDown = !empty($orderedList['can_move_down']);
                ?>
                <li>
                    <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">
                        <div class="uk-margin-small-right">
                            <span><?php echo $orderedListName; ?></span>
                        </div>
                        <div class="uk-flex uk-flex-middle">
                            <form method="post" action="settings.php" class="uk-margin-remove uk-display-inline-block" data-ajax="1">
                                <input type="hidden" name="action" value="reorder_list">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="list_id" value="<?php echo $orderedListId; ?>">
                                <input type="hidden" name="direction" value="up">
                                <button type="submit" class="uk-button uk-button-default uk-button-small" <?php echo $canMoveUp ? '' : 'disabled'; ?> aria-label="Move list up" title="Move up">↑</button>
                            </form>

                            <form method="post" action="settings.php" class="uk-margin-remove uk-display-inline-block uk-margin-small-left" data-ajax="1">
                                <input type="hidden" name="action" value="reorder_list">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="list_id" value="<?php echo $orderedListId; ?>">
                                <input type="hidden" name="direction" value="down">
                                <button type="submit" class="uk-button uk-button-default uk-button-small" <?php echo $canMoveDown ? '' : 'disabled'; ?> aria-label="Move list down" title="Move down">↓</button>
                            </form>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
