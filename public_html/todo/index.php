<?php
define('TODO_PUBLIC_DIR', __DIR__);
require __DIR__ . '/../../private_todo/app/bootstrap.php';

use TodoApp\View;
use TodoApp\IndexController;

$controller = new IndexController($db);
$viewData = $controller->handle();

View::render('index.view.php', $viewData);
