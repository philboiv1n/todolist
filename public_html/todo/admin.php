<?php
define('TODO_PUBLIC_DIR', __DIR__);
require __DIR__ . '/../../private_todo/app/bootstrap.php';

use TodoApp\View;
use TodoApp\AdminController;

$controller = new AdminController($db);
$viewData = $controller->handle();

View::render('admin.view.php', $viewData);
