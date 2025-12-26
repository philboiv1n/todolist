<?php
/** @var array $list */
/** @var string $csrf */

$listId = (int)($list['id'] ?? 0);
$canEdit = !empty($list['can_edit']);
$listName = \TodoApp\Security::h((string)($list['name'] ?? ''));
$todos = is_array($list['todos'] ?? null) ? $list['todos'] : [];
$overdueCutoff = (new DateTimeImmutable('today'))->modify('-3 days');
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
                            $rawDueDate = $todo['due_date'] ?? null;
                            $rawDueDate = is_string($rawDueDate) ? trim($rawDueDate) : '';
                            $dueDateInputValue = $rawDueDate !== '' ? substr($rawDueDate, 0, 10) : '';
                            $dueDate = $dueDateInputValue !== '' ? \TodoApp\Security::h($dueDateInputValue) : null;
                            $isOverdue = false;
                            if (!$isDone && $dueDateInputValue !== '') {
                                if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $dueDateInputValue) === 1) {
                                    try {
                                        $dueDateObj = new DateTimeImmutable($dueDateInputValue);
                                        $isOverdue = $dueDateObj < $overdueCutoff;
                                    } catch (Throwable) {
                                        $isOverdue = false;
                                    }
                                }
                            }
                            $repeatLabel = null;
                            $repeatRule = $todo['repeat_rule'] ?? null;
                            if (is_string($repeatRule) && trim($repeatRule) !== '') {
                                $repeatLabel = \TodoApp\Recurrence::describe($repeatRule);
                                if ($repeatLabel !== null) {
                                    $repeatLabel = \TodoApp\Security::h($repeatLabel);
                                }
                            }
                            ?>
                            <li>
                                <?php if ($canEdit): ?>
                                    <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap">
                                        <div class="uk-flex uk-flex-top uk-width-expand">
                                            <form id="toggle-<?php echo $todoId; ?>" method="post" action="index.php" class="uk-margin-remove" data-ajax="1">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?php echo $todoId; ?>">

                                                <noscript>
                                                    <button type="submit" class="uk-button uk-button-default uk-button-small uk-margin-small-left">Update</button>
                                                </noscript>
                                            </form>

                                            <input
                                                id="todo-<?php echo $todoId; ?>"
                                                form="toggle-<?php echo $todoId; ?>"
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
	                                                <form method="post" action="index.php" class="uk-margin-remove" data-ajax="1">
	                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
	                                                    <input type="hidden" name="action" value="update_due_date">
	                                                    <input type="hidden" name="id" value="<?php echo $todoId; ?>">
	                                                    <span id="due-<?php echo $todoId; ?>" class="todo-item-due uk-text-meta uk-display-block">
		                                                        <input
		                                                            id="todo-due-<?php echo $todoId; ?>"
		                                                            class="todo-due-date-input uk-input uk-form-small"
		                                                            type="date"
		                                                            name="due_date"
		                                                            value="<?php echo \TodoApp\Security::h($dueDateInputValue); ?>"
		                                                            aria-label="Due date"
	                                                            onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
	                                                        >
	                                                        <?php if ($isOverdue): ?> <span class="todo-nav-icon" uk-icon="icon: warning; ratio: 0.8" title="Overdue"></span><?php endif; ?>
	                                                    </span>

	                                                    <noscript>
	                                                        <button type="submit" class="uk-button uk-button-default uk-button-small uk-margin-small-left">Save date</button>
	                                                    </noscript>
	                                                </form>
	                                                <?php if ($repeatLabel): ?>
	                                                    <span class="todo-item-repeat uk-text-meta uk-display-block">
	                                                        Repeats: <?php echo $repeatLabel; ?>
	                                                    </span>
	                                                <?php endif; ?>
                                            </div>
                                        </div>

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
	                                                <span class="todo-item-due uk-text-meta uk-display-block">Due: <?php echo $dueDate; ?><?php if ($isOverdue): ?> <span class="todo-nav-icon" uk-icon="icon: warning; ratio: 0.8" title="Overdue"></span><?php endif; ?></span>
	                                            <?php endif; ?>
	                                            <?php if ($repeatLabel): ?>
	                                                <span class="todo-item-repeat uk-text-meta uk-display-block">
	                                                    Repeats: <?php echo $repeatLabel; ?>
	                                                </span>
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
