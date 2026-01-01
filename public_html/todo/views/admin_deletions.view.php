<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>To-Do List - Deleted tasks</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="css/uikit.min.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
</head>
<body class="uk-background-muted">
<?php
/** @var array $currentUser */
/** @var bool $supportsDeletions */
/** @var array $deletions */

$signedInUsername = (string)($currentUser['username'] ?? '');
$supportsDeletions = !empty($supportsDeletions);
?>

<?php
$activePage = 'admin';
$showAdminButton = !empty($currentUser['is_admin']);
require __DIR__ . '/partials/layout/header.view.php';
?>

<main id="main" class="uk-section uk-section-small uk-padding-remove-top">
    <div class="uk-container">
        <div class="uk-card uk-card-default uk-card-body uk-margin">
            <div class="uk-flex uk-flex-middle uk-flex-between">
                <h2 class="uk-h4 uk-margin-remove">Deleted tasks log</h2>
                <a class="uk-button uk-button-default uk-button-small" href="admin.php">Back to admin</a>
            </div>
            <p class="uk-text-muted uk-margin-small-top">
                <small>Showing the most recent 200 deletions.</small>
            </p>
        </div>

        <?php if (!$supportsDeletions): ?>
            <div class="uk-alert uk-alert-warning" role="status">
                <p>Deletion logging is not enabled yet. Run the migration script to enable it.</p>
            </div>
        <?php elseif (empty($deletions)): ?>
            <div class="uk-card uk-card-default uk-card-body uk-margin">
                <p class="uk-text-muted uk-margin-remove">No deletions logged yet.</p>
            </div>
        <?php else: ?>
            <div class="uk-card uk-card-default uk-card-body uk-margin">
                <table class="uk-table uk-table-small uk-table-divider">
                    <thead>
                    <tr>
                        <th>Deleted at</th>
                        <th>List</th>
                        <th>Task</th>
                        <th>Owner</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($deletions as $row): ?>
                        <?php
                        $deletedAt = (string)($row['deleted_at'] ?? '');
                        $listName = (string)($row['list_name'] ?? '');
                        $listId = (int)($row['list_id'] ?? 0);
                        $todoTitle = (string)($row['todo_title'] ?? '');
                        $ownerName = (string)($row['owner_username'] ?? '');
                        $ownerId = (int)($row['owner_id'] ?? 0);

                        $listLabel = $listName !== '' ? $listName : ($listId > 0 ? "List #{$listId}" : 'Unknown');
                        $ownerLabel = $ownerName !== '' ? $ownerName : ($ownerId > 0 ? "User #{$ownerId}" : 'Unknown');
                        ?>
                        <tr>
                            <td><?php echo \TodoApp\Security::h($deletedAt); ?></td>
                            <td><?php echo \TodoApp\Security::h($listLabel); ?></td>
                            <td><?php echo \TodoApp\Security::h($todoTitle); ?></td>
                            <td><?php echo \TodoApp\Security::h($ownerLabel); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
$showSignedIn = true;
$logoutHref = 'index.php?logout=1';
$refreshHref = 'admin_deletions.php';
require __DIR__ . '/partials/layout/footer.view.php';
?>

<script src="js/uikit.min.js" defer></script>
<script src="js/uikit-icons.min.js" defer></script>
</body>
</html>
