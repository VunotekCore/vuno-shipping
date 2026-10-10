<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Controllers\VehicleController;

$controller = new VehicleController();
$controller->kmRegister();
