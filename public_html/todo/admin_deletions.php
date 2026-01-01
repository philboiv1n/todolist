<?php
define('TODO_PUBLIC_DIR', __DIR__);
require __DIR__ . '/../../private_todo/app/bootstrap.php';

use TodoApp\View;
use TodoApp\AdminTodoDeletionsController;

$controller = new AdminTodoDeletionsController($db);
$viewData = $controller->handle();

View::render('admin_deletions.view.php', $viewData);
