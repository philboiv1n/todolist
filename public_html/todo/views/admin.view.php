<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>To-Do List - Admin</title>
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
/** @var string $csrfToken */
/** @var ?string $msg */
/** @var ?string $error */
/** @var array $users */
/** @var array $lists */
/** @var array $listAccess */
/** @var array $listCounts */

$csrf = \TodoApp\Security::h($csrfToken);
$selfUserId = (int)($currentUser['id'] ?? 0);
$signedInUsername = (string)($currentUser['username'] ?? '');
?>

<?php
$activePage = 'admin';
$showAdminButton = !empty($currentUser['is_admin']);
require __DIR__ . '/partials/layout/header.view.php';
?>

<main id="main" class="uk-section uk-section-small uk-padding-remove-top">
    <div class="uk-container">
        <h1 class="uk-h3 uk-margin-small-top">Administration</h1>
        <?php if ($msg): ?>
            <div class="uk-alert uk-alert-success" role="status">
                <p><?php echo \TodoApp\Security::h($msg); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="uk-alert uk-alert-danger" role="alert">
                <p><?php echo \TodoApp\Security::h($error); ?></p>
            </div>
        <?php endif; ?>

        <div class="uk-margin">
            <a class="uk-button uk-button-default uk-button-small" href="admin_attempts.php">Login attempts</a>
        </div>

        <div class="uk-grid-small uk-child-width-1-1" uk-grid>
            <div>
                <?php require __DIR__ . '/partials/admin/users-section.view.php'; ?>
            </div>

            <div>
                <?php require __DIR__ . '/partials/admin/lists-section.view.php'; ?>
            </div>
        </div>
    </div>
</main>

<?php
$showSignedIn = true;
$logoutHref = 'index.php?logout=1';
$refreshHref = 'admin.php';
require __DIR__ . '/partials/layout/footer.view.php';
?>

<script src="js/uikit.min.js" defer></script>
<script src="js/uikit-icons.min.js" defer></script>
</body>
</html>
