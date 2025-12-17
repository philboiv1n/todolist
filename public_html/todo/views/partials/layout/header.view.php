<?php
/** @var string $activePage */
/** @var bool $showAdminButton */
?>
<header class="uk-navbar-container uk-navbar-transparent">
    <div class="uk-container">
        <nav uk-navbar aria-label="Primary">
            <div class="uk-navbar-left">
                <div class="uk-navbar-item uk-padding-remove-horizontal">
                    <?php $isLists = (string)($activePage ?? '') === 'lists'; ?>
                    <a class="uk-button uk-button-default uk-button-small white-bg" href="index.php" <?php echo $isLists ? 'aria-current="page"' : ''; ?>>
                        Lists<span class="todo-nav-icon" uk-icon="list"></span>
                    </a>
                    <?php $isSettings = (string)($activePage ?? '') === 'settings'; ?>
                    <a class="uk-button uk-button-default uk-button-small uk-margin-small-left white-bg" href="settings.php" <?php echo $isSettings ? 'aria-current="page"' : ''; ?>>
                        Settings<span class="todo-nav-icon" uk-icon="user"></span>
                    </a>
                    <?php if (!empty($showAdminButton)): ?>
                        <?php $isAdmin = (string)($activePage ?? '') === 'admin'; ?>
                        <a class="uk-button uk-button-default uk-button-small uk-margin-small-left white-bg" href="admin.php" <?php echo $isAdmin ? 'aria-current="page"' : ''; ?>>
                            Admin<span class="todo-nav-icon" uk-icon="cog"></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </div>
</header>
