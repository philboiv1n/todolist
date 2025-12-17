<?php
define('TODO_PUBLIC_DIR', __DIR__);
require __DIR__ . '/../../private_todo/app/bootstrap.php';

use TodoApp\SettingsController;
use TodoApp\View;

$controller = new SettingsController($db);
$viewData = $controller->handle();

View::render('settings.view.php', $viewData);

