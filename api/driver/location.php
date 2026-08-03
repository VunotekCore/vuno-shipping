<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
use App\Controllers\DriverLocationController;
$controller = new DriverLocationController();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $controller->get();
} else {
    $controller->post();
}
