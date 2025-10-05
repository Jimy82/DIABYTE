<?php
declare(strict_types=1);

/**
 * registro.php
 * Alta de usuarios con validación, CSRF, hash seguro y control de duplicados.
 * Dependencias:
 *  - PHP 8.1+
 *  - Sesiones habilitadas
 *  - Tabla `users` con columnas: id, email, password_hash, full_name, created_at, updated_at, is_active
 *  - Fichero de conexión PDO en config/db.php (ver stub más abajo)
 */

session_start();

// Cabeceras de seguridad mínimas
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// === Configuración de base de datos (PDO) ===
// Ajusta la ruta si tu bootstrap central ya prepara $pdo.
require __DIR__ . '/config/db.php';
$pdo = db();

// === Utilidades CSRF ===
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_token(): string { return $_SESSION['csrf']; }
function csrf_check(string $t): bool { return hash_equals($_SESSION['csrf'] ?? '', $t); }

// === Helpers ===
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
function clean(string $s): string {
    return trim(preg_replace('/\s+/u', ' ', $s));
}

// === Estado de la vista ===
$errors = [];
$old = ['full_name' => '', 'email' => ''];

// === POST: procesar alta ===
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token      = $_POST['csrf'] ?? '';
    $full_name  = clean($_POST['full_name'] ?? '');
    $email      = strtolower(clean($_POST['email'] ?? ''));
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['password_confirm'] ?? '';

    // CSRF
    if (!csrf_check($token)) {
        http_response_code(400);
        $errors['csrf'] = 'Token CSRF inválido.';
    }

    // Nombre
    if ($full_name === '' || mb_strlen($full_name) < 2 || mb_strlen($full_name) > 120) {
        $errors['full_name'] = 'Nombre entre 2 y 120 caracteres.';
    }

    // Email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email no válido.';
    } elseif (mb_strlen($email) > 190) {
        $errors['email'] = 'Email demasiado largo.';
    }

    // Password (política simple: mín 10, al menos 1 letra y 1 dígito)
    $len = strlen($password);
    if ($len < 10) {
        $errors['password'] = 'La contraseña debe tener al menos 10 caracteres.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Incluye letras y números.';
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'Las contraseñas no coinciden.';
    }

    // Duplicados
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) {
            $errors['email'] = 'Ya existe una cuenta con ese email.';
        }
    }

    // Alta
    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);

        $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, full_name, is_active) VALUES (?,?,?,1)');
        $stmt->execute([$email, $hash, $full_name]);

        // Autologin
        $userId = (int)$pdo->lastInsertId();
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name']  = $full_name;

        // Rotar token CSRF tras acción sensible
        $_SESSION['csrf'] = bin2hex(random_bytes(32));

        // Redirigir a dashboard o a configuración inicial de parámetros de insulina
        redirect('dashboard');
    }

    // Mantener los datos introducidos salvo password
    $old['full_name'] = $full_name;
    $old['email'] = $email;
}

// === GET o POST con errores: renderizar formulario ===
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Registro</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root { font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif; }
    body { margin:0; padding:2rem; background:#f6f7fb; }
    .card { max-width: 520px; margin: 0 auto; background:#fff; border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,.08); padding:1.5rem 1.75rem; }
    h1 { font-size:1.25rem; margin:0 0 1rem; }
    .field { margin-bottom:1rem; }
    label { display:block; font-size:.9rem; margin-bottom:.35rem; }
    input[type="text"], input[type="email"], input[type="password"] {
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
    <h1>Crear cuenta</h1>

    <?php if ($errors): ?>
      <div class="alert">Revisa los campos marcados.</div>
    <?php endif; ?>

    <form method="post" action="">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

      <div class="field">
        <label for="full_name">Nombre y apellidos</label>
        <input type="text" id="full_name" name="full_name" autocomplete="name" required maxlength="120"
               value="<?= htmlspecialchars($old['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <?php if (isset($errors['full_name'])): ?><div class="error"><?= $errors['full_name'] ?></div><?php endif; ?>
      </div>

      <div class="field">
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email" autocomplete="email" required maxlength="190"
               value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <?php if (isset($errors['email'])): ?><div class="error"><?= $errors['email'] ?></div><?php endif; ?>
      </div>

      <div class="field">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" autocomplete="new-password" required>
        <?php if (isset($errors['password'])): ?><div class="error"><?= $errors['password'] ?></div><?php endif; ?>
      </div>

      <div class="field">
        <label for="password_confirm">Repite la contraseña</label>
        <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
        <?php if (isset($errors['password_confirm'])): ?><div class="error"><?= $errors['password_confirm'] ?></div><?php endif; ?>
      </div>

      <div class="actions">
        <button type="submit">Registrarme</button>
        <a href="login.php">¿Ya tienes cuenta? Inicia sesión</a>
      </div>
    </form>
  </main>
</body>
</html>
