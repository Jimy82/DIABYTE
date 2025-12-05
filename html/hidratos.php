<?php
declare(strict_types=1);

/**
 * hidratos.php
 * Calculadora de raciones de insulina según hidratos de carbono.
 * Permite seleccionar alimentos, gramos y glucemia actual para estimar dosis.
 */

session_start();

// Bloqueo si no autenticado
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
function round_to(float $value, float $step = 0.5): float { return round($value / $step) * $step; }

// === Cargar parámetros personales ===
$stmt = $pdo->prepare('SELECT carb_ratio, insulin_sensitivity, target_bg FROM insulin_params WHERE user_id=? LIMIT 1');
$stmt->execute([$userId]);
$params = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$params) {
    $params = ['carb_ratio' => 10.0, 'insulin_sensitivity' => 50.0, 'target_bg' => 100];
}

$carbRatio = (float)$params['carb_ratio'];
$sens      = (float)$params['insulin_sensitivity'];
$targetBg  = (int)$params['target_bg'];

// === Cargar alimentos ===
$foods = $pdo->query('SELECT id, name, carbs_per_100g FROM foods ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);

// === Procesamiento ===
$result = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $foodId = (int)($_POST['food_id'] ?? 0);
    $grams  = (float)($_POST['grams'] ?? 0);
    $bgNow  = (float)($_POST['bg_now'] ?? 0);

    if ($foodId > 0 && $grams > 0) {
        $stmt = $pdo->prepare('SELECT carbs_per_100g FROM foods WHERE id=?');
        $stmt->execute([$foodId]);
        $food = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($food) {
            $carbs = ($food['carbs_per_100g'] * $grams) / 100.0;
            $doseCarbs = $carbs / $carbRatio;
            $corr = ($bgNow > 0) ? (($bgNow - $targetBg) / $sens) : 0.0;
            $totalDose = max(round_to($doseCarbs + $corr, 0.5), 0.0);

            $result = [
                'carbs' => round($carbs, 2),
                'dose' => $totalDose,
                'dose_carbs' => round($doseCarbs, 2),
                'corr' => round($corr, 2),
                'bg_now' => $bgNow,
                'target_bg' => $targetBg
            ];
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
</head>
<body>
  <div class="container card">
    <h1>Calculadora de insulina</h1>
    <form method="post" action="">
      <label for="food_id">Alimento</label>
      <select name="food_id" id="food_id" required>
        <option value="">-- Selecciona un alimento --</option>
        <?php foreach ($foods as $f): ?>
          <option value="<?= (int)$f['id'] ?>" <?= (isset($_POST['food_id']) && (int)$_POST['food_id'] === (int)$f['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?> (<?= $f['carbs_per_100g'] ?> g HC / 100g)
          </option>
        <?php endforeach; ?>
      </select>

      <label for="grams">Cantidad (gramos)</label>
      <input type="number" step="0.1" name="grams" id="grams" required value="<?= htmlspecialchars($_POST['grams'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <label for="bg_now">Glucemia actual (mg/dL)</label>
      <input type="number" step="0.1" name="bg_now" id="bg_now" value="<?= htmlspecialchars($_POST['bg_now'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <input type="submit" value="Calcular dosis">
    </form>

    <?php if ($result): ?>
      <div class="card" style="margin-top:1rem;">
        <h2>Resultado</h2>
        <p><strong>Hidratos:</strong> <?= $result['carbs'] ?> g</p>
        <p><strong>Dosis por hidratos:</strong> <?= $result['dose_carbs'] ?> U</p>
        <p><strong>Corrección:</strong> <?= $result['corr'] ?> U</p>
        <p><strong>Total recomendado:</strong> <span style="font-size:1.2rem; font-weight:bold; color:#1f6feb;"><?= $result['dose'] ?> U</span></p>
        <?php if ($result['bg_now'] > 0): ?>
          <p><span class="text-muted">Objetivo: <?= $result['target_bg'] ?> mg/dL</span></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <nav class="mt-2">
      <a href="escritorio.php">Volver al escritorio</a>
    </nav>
  </div>
</body>
</html>
