<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';

$blocked = $path === '/config.php'
    || $path === '/config.local.php'
    || $path === '/local-router.php'
    || $path === '/db.php'
    || $path === '/auth.php'
    || $path === '/ui.php'
    || $path === '/clubs.php'
    || str_starts_with($path, '/sql/')
    || str_starts_with($path, '/data/')
    || str_starts_with($path, '/vps/');

if ($blocked) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

return false;
