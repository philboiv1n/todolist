<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>To-Do List - Login attempts</title>
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
/** @var int $windowSeconds */
/** @var array $attemptsSummary */
/** @var array $recentAttempts */

$signedInUsername = (string)($currentUser['username'] ?? '');
$windowSeconds = (int)($windowSeconds ?? 300);

$windowText = $windowSeconds . ' seconds';
if ($windowSeconds >= 60) {
    $mins = (int)round($windowSeconds / 60);
    $windowText = $mins . ' minute' . ($mins === 1 ? '' : 's');
}
if ($windowSeconds >= 3600) {
    $hours = (int)round($windowSeconds / 3600);
    $windowText = $hours . ' hour' . ($hours === 1 ? '' : 's');
}
if ($windowSeconds >= 86400) {
    $days = (int)round($windowSeconds / 86400);
    $windowText = $days . ' day' . ($days === 1 ? '' : 's');
}
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
                <h2 class="uk-h4 uk-margin-remove">Login attempts</h2>
                <a class="uk-button uk-button-default uk-button-small" href="admin.php">Back to admin</a>
            </div>
            <p class="uk-text-muted uk-margin-small-top">
                <small>Showing failed login attempts from the last <?php echo \TodoApp\Security::h($windowText); ?>.</small>
            </p>
        </div>

        <div class="uk-grid-small uk-child-width-1-1" uk-grid>
            <div>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <h3 class="uk-h4 uk-margin-small-bottom">By IP</h3>

                    <?php if (empty($attemptsSummary)): ?>
                        <p class="uk-text-muted uk-margin-remove">No failed login attempts in this window.</p>
                    <?php else: ?>
                        <table class="uk-table uk-table-small uk-table-divider">
                            <thead>
                            <tr>
                                <th>IP</th>
                                <th class="uk-table-shrink uk-text-right">Count</th>
                                <th class="uk-table-shrink">Last attempt</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($attemptsSummary as $row): ?>
                                <?php
                                $ip = \TodoApp\Security::h((string)($row['ip'] ?? ''));
                                $count = (int)($row['c'] ?? 0);
                                $lastTs = (int)($row['last_ts'] ?? 0);
                                $lastText = $lastTs > 0 ? date('Y-m-d H:i:s', $lastTs) : '';
                                ?>
                                <tr>
                                    <td><code><?php echo $ip; ?></code></td>
                                    <td class="uk-text-right"><?php echo $count; ?></td>
                                    <td><?php echo \TodoApp\Security::h($lastText); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="uk-card uk-card-default uk-card-body uk-margin">
                    <h3 class="uk-h4 uk-margin-small-bottom">Recent</h3>

                    <?php if (empty($recentAttempts)): ?>
                        <p class="uk-text-muted uk-margin-remove">No recent failed login attempts.</p>
                    <?php else: ?>
                        <ul class="uk-list uk-list-divider uk-margin-remove">
                            <?php foreach ($recentAttempts as $row): ?>
                                <?php
                                $ip = \TodoApp\Security::h((string)($row['ip'] ?? ''));
                                $ts = (int)($row['ts'] ?? 0);
                                $tsText = $ts > 0 ? date('Y-m-d H:i:s', $ts) : '';
                                ?>
                                <li class="uk-flex uk-flex-between uk-flex-middle">
                                    <span><code><?php echo $ip; ?></code></span>
                                    <small class="uk-text-muted"><?php echo \TodoApp\Security::h($tsText); ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <p class="uk-text-muted uk-margin-small-top">
                        <small>Only failed attempts are recorded; a successful login clears attempts for that IP.</small>
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
$showSignedIn = true;
$logoutHref = 'index.php?logout=1';
$refreshHref = 'admin_attempts.php';
require __DIR__ . '/partials/layout/footer.view.php';
?>

<script src="js/uikit.min.js" defer></script>
<script src="js/uikit-icons.min.js" defer></script>
</body>
</html>
