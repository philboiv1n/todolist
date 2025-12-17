<?php
/** @var bool $showSignedIn */
/** @var string $signedInUsername */
/** @var string $logoutHref */
/** @var string $refreshHref */
?>
<footer class="uk-section uk-section-xsmall uk-text-center uk-text-muted">
    <div class="uk-container">
        <?php if (!empty($showSignedIn)): ?>
            <p class="uk-margin-remove">
                <small>
                    Signed in as <?php echo \TodoApp\Security::h((string)($signedInUsername ?? '')); ?> - 
                    <a href="<?php echo $logoutHref; ?>">Sign-out</span></a> - 
                    <a href="<?php echo $refreshHref; ?>">Refresh</span></a>
                </small>
            </p>
        <?php endif; ?>
        <p class="uk-margin-remove">
            <small>To-Do List <span uk-icon="check"></span> <?php echo \TodoApp\Security::h(TODO_APP_VERSION); ?></small>
        </p>
    </div>
</footer>

