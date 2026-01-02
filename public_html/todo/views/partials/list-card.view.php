<?php
/** @var array $list */
/** @var string $csrf */
/** @var bool $supportsRecurrence */

$listId = (int)($list['id'] ?? 0);
$canEdit = !empty($list['can_edit']);
$isExpanded = !empty($list['is_expanded']);
$listName = \TodoApp\Security::h((string)($list['name'] ?? ''));
$todos = is_array($list['todos'] ?? null) ? $list['todos'] : [];
$taskCount = count($todos);
$overdueCutoff = (new DateTimeImmutable('today'))->modify('-3 days');
$supportsRecurrence = isset($supportsRecurrence) ? (bool)$supportsRecurrence : false;
?>

<div data-list-id="<?php echo $listId; ?>">
    <section aria-labelledby="list-<?php echo $listId; ?>">
        <details class="uk-card uk-card-default todo-list-details"<?php echo $isExpanded ? ' open' : ''; ?>>
            <summary class="todo-details-summary uk-card-header uk-padding-remove">
                <div class="uk-padding-small">
                    <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">
                        <div class="uk-margin-small-right uk-flex uk-flex-middle">
                            <span id="list-<?php echo $listId; ?>" class="uk-h4 uk-margin-remove-bottom">
                                <?php echo $listName; ?>
                            </span>
                            <span class="todo-list-toggle-icon uk-margin-small-left" uk-icon="chevron-down" aria-hidden="true"></span>
                        </div>
                        <span class="uk-text-meta todo-list-task-count"><?php echo $taskCount; ?> tasks</span>
                    </div>
                </div>
            </summary>

            <div id="list-body-<?php echo $listId; ?>" class="uk-card-body todo-list-body">
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
                            }
                            $repeatPreset = $supportsRecurrence ? \TodoApp\Recurrence::presetFromRule($repeatRule) : 'none';
                            $repeatText = null;
                            if ($supportsRecurrence) {
                                $repeatText = match ($repeatPreset) {
                                    'daily' => 'Repeat every day',
                                    'weekdays' => 'Repeat weekdays',
                                    'weekly' => 'Repeat every week',
                                    'monthly' => 'Repeat every month',
                                    'quarterly' => 'Repeat every 3 months',
                                    'yearly' => 'Repeat every year',
                                    default => null,
                                };
                                if ($repeatText === null && $repeatLabel) {
                                    $repeatText = 'Repeat ' . $repeatLabel;
                                }
                            } elseif ($repeatLabel) {
                                $repeatText = 'Repeat ' . $repeatLabel;
                            }
                            $displayDueText = $dueDate ?? 'No due date';
                            $deleteConfirmAttr = $isDone ? '' : ' onclick="return confirm(\'Delete this task?\');"';
                            ?>
                            <li data-todo-id="<?php echo $todoId; ?>">
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
                                                aria-describedby="due-<?php echo $todoId; ?>"
                                                aria-label="Toggle task: <?php echo $todoTitle; ?>"
                                            >
                                            <div class="uk-flex-1 uk-margin-small-left">
                                                <button type="button" class="todo-edit-toggle" data-todo-edit-toggle="1" aria-expanded="false" title="Tap title to edit">
                                                    <span class="todo-item-title uk-text-break<?php echo $isDone ? ' todo-item-title-done' : ''; ?>">
                                                        <?php echo $todoTitle; ?>
                                                    </span>
                                                    <span id="due-<?php echo $todoId; ?>" class="todo-item-due uk-text-meta uk-display-block">
                                                        <?php echo $displayDueText; ?>
                                                        <?php if ($repeatText): ?> <span class="todo-item-repeat-text">(<?php echo \TodoApp\Security::h($repeatText); ?>)</span><?php endif; ?>
                                                        <?php if ($isOverdue): ?> <span class="todo-nav-icon" uk-icon="icon: warning; ratio: 0.8" title="Overdue"></span><?php endif; ?>
                                                    </span>
                                                </button>
                                                <form method="post" action="index.php" class="uk-margin-remove todo-edit-fields" data-ajax="1">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                                    <input type="hidden" name="action" value="update_todo">
                                                    <input type="hidden" name="id" value="<?php echo $todoId; ?>">

                                                    <input
                                                        id="todo-title-<?php echo $todoId; ?>"
                                                        class="todo-item-title todo-title-input uk-input uk-form-small uk-text-break<?php echo $isDone ? ' todo-item-title-done' : ''; ?>"
                                                        type="text"
                                                        name="title"
                                                        value="<?php echo $todoTitle; ?>"
                                                        maxlength="<?php echo \TodoApp\Security::MAX_INPUT_LENGTH; ?>"
                                                        aria-label="Task title"
                                                        required
                                                        onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
                                                    >

                                                    <span class="todo-item-due uk-text-meta uk-display-block">
                                                        <span class="todo-edit-label">Date:</span>
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

                                                    <?php if ($supportsRecurrence): ?>
                                                        <span class="todo-item-repeat uk-text-meta uk-display-block">
                                                            Repeat:
                                                            <select
                                                                id="todo-repeat-<?php echo $todoId; ?>"
                                                                class="todo-repeat-select uk-select uk-form-small"
                                                                name="repeat"
                                                                aria-label="Repeat"
                                                                onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
                                                            >
                                                                <option value="none"<?php echo $repeatPreset === 'none' ? ' selected' : ''; ?>>Never</option>
                                                                <option value="daily"<?php echo $repeatPreset === 'daily' ? ' selected' : ''; ?>>Every day</option>
                                                                <option value="weekdays"<?php echo $repeatPreset === 'weekdays' ? ' selected' : ''; ?>>Every weekday</option>
                                                                <option value="weekly"<?php echo $repeatPreset === 'weekly' ? ' selected' : ''; ?>>Every week</option>
                                                                <option value="monthly"<?php echo $repeatPreset === 'monthly' ? ' selected' : ''; ?>>Every month</option>
                                                                <option value="quarterly"<?php echo $repeatPreset === 'quarterly' ? ' selected' : ''; ?>>Every 3 months</option>
                                                                <option value="yearly"<?php echo $repeatPreset === 'yearly' ? ' selected' : ''; ?>>Every year</option>
                                                            </select>
                                                        </span>
                                                    <?php elseif ($repeatLabel): ?>
                                                        <span class="todo-item-repeat uk-text-meta uk-display-block">
                                                            Repeats: <?php echo \TodoApp\Security::h($repeatLabel); ?>
                                                        </span>
                                                    <?php endif; ?>

                                                    <noscript>
                                                        <button type="submit" class="uk-button uk-button-default uk-button-small uk-margin-small-left">Save</button>
                                                    </noscript>
                                                </form>
                                            </div>
                                        </div>

                                        <form method="post" action="index.php" class="uk-flex-none uk-margin-remove" data-ajax="1">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $todoId; ?>">
                                            <button
                                                type="submit"
                                                class="uk-button uk-button-link uk-text-danger uk-margin-xsmall-top"
                                                <?php echo $deleteConfirmAttr; ?>
                                                aria-label="Delete task: <?php echo $todoTitle; ?>"
                                                title="Delete"
                                            ><span uk-icon="close"></span></button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="uk-flex uk-flex-top uk-flex-wrap">
	                                        <input id="todo-<?php echo $todoId; ?>" class="uk-checkbox uk-flex-none uk-margin-xsmall-top" type="checkbox" <?php echo $isDone ? 'checked' : ''; ?> disabled>
	                                        <div class="uk-flex-1 uk-margin-small-left">
	                                            <span class="todo-item-title uk-text-break<?php echo $isDone ? ' todo-item-title-done' : ''; ?>"><?php echo $todoTitle; ?></span>
	                                            <span class="todo-item-due uk-text-meta uk-display-block">
                                                    <?php echo $displayDueText; ?>
                                                    <?php if ($repeatText): ?> <span class="todo-item-repeat-text">(<?php echo \TodoApp\Security::h($repeatText); ?>)</span><?php endif; ?>
                                                    <?php if ($isOverdue): ?> <span class="todo-nav-icon" uk-icon="icon: warning; ratio: 0.8" title="Overdue"></span><?php endif; ?>
                                                </span>
                                            </div>
	                                    </div>
	                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </details>
    </section>
</div>
