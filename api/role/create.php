<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Controllers\RoleController;

$controller = new RoleController();
$controller->create();
