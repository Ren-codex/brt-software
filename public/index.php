<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Laravel 11's bundled config uses PDO::MYSQL_ATTR_SSL_CA, deprecated in PHP 8.5, and it
// loads before Laravel's error handler exists, so the notice prints straight onto the page.
// HandleExceptions resets error_reporting right after config loads, so only that window is muted.
error_reporting(E_ALL & ~E_DEPRECATED);

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
