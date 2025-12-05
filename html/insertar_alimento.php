<?php
declare(strict_types=1);

/**
 * insertar_alimento.php
 * Inserta un nuevo alimento en la tabla `foods`.
 * Requiere sesión iniciada y rol válido (usuario o administrador).
 */

session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 303);
    exit;
}

header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

require __DIR__ . '/config/db.php';
$pdo = db();
$userId = (int)$_SESSION['user_id'];

// === Funciones ===
function clean(string $s): string { return trim(preg_replace('/\s+/u', ' ', $s)); }
function csrf_token(): string {
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(string $t): bool {
    return hash_equals($_SESSION['csrf'] ?? '', $t);
}
function redirect(string $path, array $qs = [], int $code = 303): never {
    $url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/' . ltrim($path, '/');
    if ($qs) { $url .= '?' . http_build_query($qs); }
    header('Location: ' . $url, true, $code);
    exit;
}

$errors = [];
$success = false;

// === Procesar POST ===
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!csrf_check($token)) {
        http_response_code(400);
        $errors[] = 'Token CSRF inválido.';
    } else {
        $name  = clean($_POST['name'] ?? '');
        $carbs = (float)($_POST['carbs_per_100g'] ?? 0);
        $gi    = (int)($_POST['glycemic_index'] ?? 0);
        $unit  = clean($_POST['unit'] ?? 'g');

        if ($name === '') $errors[] = 'Nombre requerido.';
        if ($carbs <= 0 || $carbs > 100) $errors[] = 'Los hidratos deben estar entre 0 y 100.';
        if ($gi < 0 || $gi > 120) $errors[] = 'Índice glucémico fuera de rango.';

        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO foods (name, carbs_per_100g, glycemic_index, unit, created_by) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $carbs, $gi, $unit, $userId]);
            $success = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Nuevo alimento</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/estilo.css">
</head>
<body>
  <div class="container card">
    <h1>Registrar nuevo alimento</h1>

    <?php if ($success): ?>
      <div class="alert alert-ok">Alimento añadido correctamente.</div>
    <?php elseif ($errors): ?>
      <div class="alert alert-err">
        <?php foreach ($errors as $e): ?>
          <div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

      <label for="name">Nombre</label>
      <input type="text" id="name" name="name" required maxlength="190" value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <label for="carbs_per_100g">Hidratos por 100 g</label>
      <input type="number" id="carbs_per_100g" name="carbs_per_100g" step="0.1" min="0" max="100" required value="<?= htmlspecialchars($_POST['carbs_per_100g'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <label for="glycemic_index">Índice glucémico</label>
      <input type="number" id="glycemic_index" name="glycemic_index" step="1" min="0" max="120" value="<?= htmlspecialchars($_POST['glycemic_index'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <label for="unit">Unidad (por defecto: g)</label>
      <input type="text" id="unit" name="unit" maxlength="10" value="<?= htmlspecialchars($_POST['unit'] ?? 'g', ENT_QUOTES, 'UTF-8') ?>">

      <input type="submit" value="Guardar alimento">
    </form>

    <nav class="mt-2">
      <a href="alimentos.php">Volver</a>
    </nav>
  </div>
</body>
</html>
