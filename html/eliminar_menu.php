<?php
declare(strict_types=1);

/**
 * eliminar_menu.php
 * Borra el menú completo de una fecha del usuario autenticado.
 * Requisitos:
 *  - Sesión iniciada
 *  - Tabla menus (id,user_id,date)
 *  - Tabla menu_items (menu_id)
 */

session_start();

// Bloqueo si no autenticado
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 303);
    exit;
}

// Cabeceras seguras
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

require __DIR__ . '/config/db.php';
$pdo = db();
$userId = (int)$_SESSION['user_id'];

// === Utilidades ===
function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . ($base === '' ? '' : $base);
}
function redirect(string $path, array $qs = [], int $code = 303): never {
    $url = rtrim(base_url(), '/') . '/' . ltrim($path, '/');
    if ($qs) { $url .= '?' . http_build_query($qs); }
    header('Location: ' . $url, true, $code);
    exit;
}
function clean(string $s): string { return trim(preg_replace('/\s+/u', ' ', $s)); }
function valid_date(string $d): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    [$y,$m,$day] = array_map('intval', explode('-', $d));
    return checkdate($m, $day, $y);
}

// CSRF
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_token(): string { return $_SESSION['csrf']; }
function csrf_check(string $t): bool { return hash_equals($_SESSION['csrf'] ?? '', $t); }

// === Entrada ===
$date = isset($_GET['date']) ? clean($_GET['date']) : '';
if (!valid_date($date)) {
    http_response_code(400);
    echo 'Fecha no válida.';
    exit;
}

$errors = [];
$deleted = false;

// === POST: Confirmar eliminación ===
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!csrf_check($token)) {
        http_response_code(400);
        $errors[] = 'Token CSRF inválido.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id FROM menus WHERE user_id=? AND date=? LIMIT 1');
            $stmt->execute([$userId, $date]);
            $menu = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$menu) {
                throw new RuntimeException('Menú no encontrado.');
            }
            $menuId = (int)$menu['id'];

            // Borrar items primero (ON DELETE CASCADE si ya está en la FK)
            $pdo->prepare('DELETE FROM menu_items WHERE menu_id=?')->execute([$menuId]);
            $pdo->prepare('DELETE FROM menus WHERE id=? AND user_id=?')->execute([$menuId, $userId]);
            $pdo->commit();

            $deleted = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            redirect('dashboard', ['msg' => 'menu_eliminado']);
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Error al eliminar el menú.';
        }
    }
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Registro</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="diabyte-logo-v1.png">
  <link rel="stylesheet" href="/estilo.css">
  <!--<style>
    :root { font-family: system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Helvetica Neue",Arial,"Noto Sans",sans-serif; }
    body { margin:0; padding:2rem; background:#f6f7fb; }
    .card { max-width:520px; margin:0 auto; background:#fff; border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,.08); padding:1.5rem 1.75rem; }
    h1 { font-size:1.25rem; margin:0 0 1rem; }
    .err { background:#ffe8e8; border:1px solid #f4bcbc; color:#8a1c1c; padding:.5rem .7rem; border-radius:8px; margin-bottom:.6rem; }
    .warn { background:#fff7e6; border:1px solid #ffe58f; color:#614700; padding:.6rem .8rem; border-radius:8px; margin-bottom:1rem; }
    .actions { display:flex; gap:.75rem; }
    button { border:0; border-radius:8px; padding:.7rem 1rem; font-weight:600; cursor:pointer; }
    .btn-del { background:#e11d48; color:#fff; }
    .btn-cancel { background:#e2e8f0; }
  </style>-->
</head>
<body>
  <main class="card">
    <h1>Eliminar menú de <?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?></h1>

    <?php foreach ($errors as $e): ?>
      <div class="err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endforeach; ?>

    <?php if (!$deleted): ?>
      <div class="warn">
        Esta acción eliminará permanentemente todos los elementos del menú de esta fecha.
      </div>

      <form method="post" action="">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="actions">
          <button type="submit" class="btn-del" onclick="return confirm('¿Eliminar definitivamente el menú del <?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>?');">
            Eliminar
          </button>
          <a href="editar_menu.php?date=<?= urlencode($date) ?>" class="btn-cancel" style="text-decoration:none;display:inline-block;padding:.7rem 1rem;">Cancelar</a>
        </div>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
