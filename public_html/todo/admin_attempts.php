<?php
define('TODO_PUBLIC_DIR', __DIR__);
require __DIR__ . '/../../private_todo/app/bootstrap.php';

use TodoApp\View;
use TodoApp\AdminLoginAttemptsController;

$controller = new AdminLoginAttemptsController($db);
$viewData = $controller->handle();

View::render('admin_attempts.view.php', $viewData);

