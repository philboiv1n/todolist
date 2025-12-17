<?php
/** @var array $list */
/** @var string $csrf */

$listId = (int)($list['id'] ?? 0);
$canEdit = !empty($list['can_edit']);
$listName = \TodoApp\Security::h((string)($list['name'] ?? ''));
$todos = is_array($list['todos'] ?? null) ? $list['todos'] : [];
?>

<div data-list-id="<?php echo $listId; ?>">
    <section aria-labelledby="list-<?php echo $listId; ?>">
        <div class="uk-card uk-card-default">
	            <div class="uk-card-header">
	                <h2 id="list-<?php echo $listId; ?>" class="uk-h4 uk-margin-remove-bottom">
	                    <?php echo $listName; ?>
	                </h2>
	            </div>

            <div class="uk-card-body">
                <?php if (!$canEdit): ?>
                    <div class="uk-alert uk-alert-primary" role="note">
                        <p>You have view-only access to this list.</p>
                    </div>
                <?php endif; ?>

                <?php if (empty($todos)): ?>
                    <p class="uk-text-muted">No tasks yet.</p>
                <?php else: ?>
                    <ul class="uk-list uk-list-divider">
                        <?php foreach ($todos as $todo): ?>
                            <?php
                            $todoId = (int)($todo['id'] ?? 0);
                            $todoTitle = \TodoApp\Security::h((string)($todo['title'] ?? ''));
                            $isDone = !empty($todo['is_done']);
                            $dueDate = !empty($todo['due_date']) ? \TodoApp\Security::h((string)$todo['due_date']) : null;
                            ?>
                            <li>
                                <?php if ($canEdit): ?>
                                    <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap">
                                        <form method="post" action="index.php" class="uk-flex uk-flex-top uk-width-expand uk-margin-remove" data-ajax="1">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?php echo $todoId; ?>">

                                            <input
                                                id="todo-<?php echo $todoId; ?>"
                                                class="uk-checkbox uk-flex-none uk-margin-xsmall-top"
                                                type="checkbox"
                                                onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
                                                <?php echo $isDone ? 'checked' : ''; ?>
                                                <?php echo $dueDate ? 'aria-describedby="due-' . $todoId . '"' : ''; ?>
                                            >
	                                            <div class="uk-flex-1 uk-margin-small-left">
	                                                <label for="todo-<?php echo $todoId; ?>" class="todo-item-title uk-text-break<?php echo $isDone ? ' todo-item-title-done' : ''; ?>">
	                                                    <?php echo $todoTitle; ?>
	                                                </label>
	                                                <?php if ($dueDate): ?>
	                                                    <span id="due-<?php echo $todoId; ?>" class="todo-item-due uk-text-meta uk-display-block">
	                                                        Due: <?php echo $dueDate; ?>
	                                                    </span>
	                                                <?php endif; ?>

                                                <noscript>
                                                    <button type="submit" class="uk-button uk-button-default uk-button-small uk-margin-small-left">Update</button>
                                                </noscript>
                                            </div>
                                        </form>

                                        <form method="post" action="index.php" class="uk-flex-none uk-margin-remove" data-ajax="1">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $todoId; ?>">
                                            <button
                                                type="submit"
                                                class="uk-button uk-button-link uk-text-danger uk-margin-xsmall-top"
                                                onclick="return confirm('Delete this task?');"
                                                aria-label="Delete task: <?php echo $todoTitle; ?>"
                                                title="Delete"
                                            ><span uk-icon="close"></span></button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="uk-flex uk-flex-top uk-flex-wrap">
	                                        <input id="todo-<?php echo $todoId; ?>" class="uk-checkbox uk-flex-none uk-margin-xsmall-top" type="checkbox" <?php echo $isDone ? 'checked' : ''; ?> disabled>
	                                        <div class="uk-flex-1 uk-margin-small-left">
	                                            <label for="todo-<?php echo $todoId; ?>" class="todo-item-title uk-text-break<?php echo $isDone ? ' todo-item-title-done' : ''; ?>"><?php echo $todoTitle; ?></label>
	                                            <?php if ($dueDate): ?>
	                                                <span class="todo-item-due uk-text-meta uk-display-block">Due: <?php echo $dueDate; ?></span>
	                                            <?php endif; ?>
	                                        </div>
	                                    </div>
	                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
