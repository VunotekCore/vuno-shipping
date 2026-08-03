<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
use App\Controllers\RouteStopController;
$controller = new RouteStopController();
$controller->photo();
