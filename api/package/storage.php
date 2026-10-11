<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
use App\Controllers\PackageStorageController;
$controller = new PackageStorageController();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $controller->get();
} else {
    $controller->update();
}
