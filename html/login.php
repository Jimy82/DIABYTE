<?php
declare(strict_types=1);

/**
 * login.php
 * Inicio de sesión con validación, CSRF, verificación bcrypt, rate limit y control de sesión.
 * Requisitos:
 *  - PHP 8.1+
 *  - Sesiones habilitadas
 *  - Tabla `users` con columnas: id, email, password_hash, full_name, is_active
 *  - Config PDO en config/db.php
 */

session_start();

// Cabeceras de seguridad
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// Redirige si ya está autenticado
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard', true, 303);
    exit;
}

// Conexión BD
require __DIR__ . "/config/db.php";
$pdo = db();
$pdo = db();

// CSRF
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_token(): string { return $_SESSION['csrf']; }
function csrf_check(string $t): bool { return hash_equals($_SESSION['csrf'] ?? '', $t); }

// Helpers
function clean(string $s): string {
    return trim(preg_replace('/\s+/u', ' ', $s));
}
function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . ($base === '' ? '' : $base);
}
function redirect(string $path, int $code = 303): void {
    header('Location: ' . rtrim(base_url(), '/') . '/' . ltrim($path, '/'), true, $code);
    exit;
}

// Rate limit en sesión (5 intentos por 10 minutos)
$RL_KEY = 'login_rl';
if (!isset($_SESSION[$RL_KEY])) {
    $_SESSION[$RL_KEY] = ['cnt' => 0, 'ts' => time()];
}
function rl_allowed(): bool {
    $w = &$_SESSION['login_rl'];
    $window = 600; // 10 min
    if (time() - $w['ts'] > $window) { $w = ['cnt' => 0, 'ts' => time()]; }
    return $w['cnt'] < 5;
}
function rl_hit(): void {
    $_SESSION['login_rl']['cnt']++;
}
function rl_block_msg(): string {
    $w = $_SESSION['login_rl'];
    $wait = max(0, 600 - (time() - $w['ts']));
    return 'Demasiados intentos. Inténtalo más tarde (≈ ' . ceil($wait/60) . ' min).';
}

$errors = [];
$old = ['email' => ''];

// POST: autenticar
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token    = $_POST['csrf'] ?? '';
    $email    = strtolower(clean($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    // CSRF
    if (!csrf_check($token)) {
        http_response_code(400);
        $errors['csrf'] = 'Token CSRF inválido.';
    }

    // Rate limit
    if (!$errors && !rl_allowed()) {
        $errors['rate'] = rl_block_msg();
    }

    // Validación básica
    if (!$errors) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email no válido.';
        }
        if ($password === '') {
            $errors['password'] = 'Introduce la contraseña.';
        }
    }

    // Búsqueda y verificación
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id, email, password_hash, full_name, is_active FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $ok = $user && (int)$user['is_active'] === 1 && password_verify($password, $user['password_hash']);
        if (!$ok) {
            rl_hit();
            $errors['auth'] = 'Credenciales no válidas.';
        }
    }

    // Login
    if (!$errors) {
        // Rotación de ID de sesión para evitar fijación
        session_regenerate_id(true);
        $_SESSION['user_id']    = (int)$user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name']  = $user['full_name'];

        // Reset rate limit y rotar CSRF
        $_SESSION[$RL_KEY] = ['cnt' => 0, 'ts' => time()];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));

        redirect('dashboard');
    }

    $old['email'] = $email;
}

// GET o POST con errores: render
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Iniciar sesión</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root { font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif; }
    body { margin:0; padding:2rem; background:#f6f7fb; }
    .card { max-width: 520px; margin: 0 auto; background:#fff; border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,.08); padding:1.5rem 1.75rem; }
    h1 { font-size:1.25rem; margin:0 0 1rem; }
    .field { margin-bottom:1rem; }
    label { display:block; font-size:.9rem; margin-bottom:.35rem; }
    input[type="email"], input[type="password"] {
      width:100%; padding:.7rem .8rem; border:1px solid #d7dbdf; border-radius:8px; outline:none;
    }
    .error { color:#b00020; font-size:.85rem; margin-top:.35rem; }
    .alert { background:#ffe8e8; color:#8a1c1c; padding:.6rem .8rem; border-radius:8px; margin-bottom:1rem; border:1px solid #f4bcbc; }
    .actions { margin-top:1.25rem; display:flex; gap:.75rem; align-items:center; }
    button { border:0; background:#1f6feb; color:#fff; padding:.7rem 1rem; border-radius:8px; font-weight:600; cursor:pointer; }
    a { color:#1f6feb; text-decoration:none; }
  </style>
</head>
<body>
  <main class="card">
    <h1>Iniciar sesión</h1>

    <?php if ($errors): ?>
      <div class="alert">Revisa los campos marcados.</div>
    <?php endif; ?>

    <?php if (isset($errors['auth'])): ?><div class="error"><?= $errors['auth'] ?></div><?php endif; ?>
    <?php if (isset($errors['rate'])): ?><div class="error"><?= $errors['rate'] ?></div><?php endif; ?>
    <?php if (isset($errors['csrf'])): ?><div class="error"><?= $errors['csrf'] ?></div><?php endif; ?>

    <form method="post" action="">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

      <div class="field">
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email" autocomplete="email" required maxlength="190"
               value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <?php if (isset($errors['email'])): ?><div class="error"><?= $errors['email'] ?></div><?php endif; ?>
      </div>

      <div class="field">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <?php if (isset($errors['password'])): ?><div class="error"><?= $errors['password'] ?></div><?php endif; ?>
      </div>

      <div class="actions">
        <button type="submit">Entrar</button>
        <a href="registro.php">Crear cuenta</a>
      </div>
    </form>
  </main>
</body>
</html>
