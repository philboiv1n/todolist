<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>To-Do List</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="css/uikit.min.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
</head>
<body class="uk-background-muted" data-user-id="<?php echo (int)($currentUserId ?? 0); ?>">
<?php
$csrf = \TodoApp\Security::h($csrfToken);
$today = date('Y-m-d');
$username = (string)($currentUsername ?? '');
$supportsRecurrence = !empty($supportsRecurrence);
$logoutHref = 'index.php?logout=1';
$refreshHref = 'index.php';
$mainClass = $currentUserId
    ? 'uk-section uk-section-small uk-padding-remove-top'
    : 'uk-section uk-section-small';
$editableLists = array_values(array_filter(
    $userLists,
    static fn(array $list): bool => !empty($list['can_edit'])
));
?>

<?php if ($currentUserId): ?>
    <?php
    $activePage = 'lists';
    $showAdminButton = !empty($currentUser['is_admin']);
    require __DIR__ . '/partials/layout/header.view.php';
    ?>
<?php endif; ?>

<main id="main" class="<?php echo $mainClass; ?>">
    <div class="uk-container">
        <?php if (!$currentUserId): ?>
	            <div class="uk-flex uk-flex-center">
	                <div class="uk-width-large">
	                    <div class="uk-card uk-card-default uk-card-body">
	                        <h2 class="uk-h4 uk-margin-small-bottom">Login</h2>
	                        <?php if ($loginError): ?>
	                            <div class="uk-alert uk-alert-danger" role="alert">
	                                <p><?php echo \TodoApp\Security::h($loginError); ?></p>
	                            </div>
	                        <?php endif; ?>

                        <form method="post" action="index.php" class="uk-form-stacked">
                            <input type="hidden" name="action" value="login">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                            <div class="uk-margin">
                                <label class="uk-form-label" for="username">Username</label>
                                <div class="uk-form-controls">
                                    <input id="username" class="uk-input" type="text" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required>
                                </div>
                            </div>

                            <div class="uk-margin">
                                <label class="uk-form-label" for="password">Password</label>
                                <div class="uk-form-controls">
                                    <input id="password" class="uk-input" type="password" name="password" autocomplete="current-password" required>
                                </div>
                            </div>

                            <button type="submit" class="uk-button uk-button-primary uk-width-1-1">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php if (empty($userLists)): ?>
                <div class="uk-alert uk-alert-warning" role="status">
                    <p>No lists yet. Create one in <a href="settings.php">Settings</a>.</p>
                </div>
	            <?php else: ?>
	                <?php if (!empty($editableLists)): ?>
	                    <div class="uk-card uk-card-default uk-card-body uk-margin todo-accent-border">
	                        <h2 class="uk-h4 uk-margin-small-bottom">Add Task</h2>

	                        <form method="post" action="index.php" class="uk-form-stacked" data-ajax="1">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                            <div class="uk-grid-small" uk-grid>
                                <div class="uk-width-auto@s">
                                    <label class="uk-form-label" for="new-list-id">List</label>
                                    <div class="uk-form-controls">
                                        <select id="new-list-id" class="uk-select" name="list_id" required>
                                            <?php foreach ($editableLists as $list): ?>
                                                <?php
                                                $listId = (int)($list['id'] ?? 0);
                                                $listName = \TodoApp\Security::h((string)($list['name'] ?? ''));
                                                ?>
                                                <option value="<?php echo $listId; ?>"><?php echo $listName; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="uk-width-expand@s">
                                    <label class="uk-form-label" for="new-title">Task</label>
                                    <div class="uk-form-controls">
                                        <input id="new-title" class="uk-input" type="text" name="title" required>
                                    </div>
                                </div>

                                <div class="todo-grid-break uk-visible@s uk-hidden@m" aria-hidden="true"></div>

                                <div class="uk-width-auto@s">
                                    <label class="uk-form-label" for="new-due-date">Due date</label>
                                    <div class="uk-form-controls">
                                        <input id="new-due-date" class="uk-input" type="date" name="due_date" value="<?php echo \TodoApp\Security::h($today); ?>">
                                    </div>
                                </div>

                                <div class="uk-width-auto@s">
                                    <label class="uk-form-label" for="new-repeat">Repeat</label>
                                    <div class="uk-form-controls">
                                        <select id="new-repeat" class="uk-select" name="repeat" <?php echo $supportsRecurrence ? '' : 'disabled'; ?>>
                                            <option value="none">Never</option>
                                            <option value="daily">Every day</option>
                                            <option value="weekdays">Every weekday</option>
                                            <option value="weekly">Every week</option>
                                            <option value="monthly">Every month</option>
                                            <option value="yearly">Every year</option>
                                        </select>
                                        <?php if (!$supportsRecurrence): ?>
                                            <div class="uk-text-meta">Run the migration to enable repeating tasks.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="uk-width-auto@s uk-flex uk-flex-bottom">
                                    <button type="submit" class="uk-button uk-button-primary" aria-label="Add task" title="Add task">
                                        <span class="todo-icon-bold" uk-icon="plus" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <div id="lists" class="uk-child-width-1-1 uk-grid-small" uk-grid>
                    <?php foreach ($userLists as $list): ?>
                        <?php require __DIR__ . '/partials/list-card.view.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php
$showSignedIn = (bool)$currentUserId;
$signedInUsername = $username;
require __DIR__ . '/partials/layout/footer.view.php';
?>

<script src="js/uikit.min.js" defer></script>
<script src="js/uikit-icons.min.js" defer></script>
<script src="js/todo.js" defer></script>
</body>
</html>
