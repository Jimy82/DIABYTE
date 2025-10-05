<?php
declare(strict_types=1);

/**
 * logout.php
 * Cierra la sesión y destruye cualquier rastro de autenticación.
 */

session_start();

// Cabeceras seguras
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// Invalidar variables de sesión
$_SESSION = [];

// Eliminar cookie de sesión
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Lax'
        ]
    );
}

// Destruir sesión
session_destroy();

// Redirigir a login
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$target = $scheme . '://' . $host . ($base === '' ? '' : $base) . '/login.php';

header('Location: ' . $target, true, 303);
exit;
