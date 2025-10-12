<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=UTF-8');

// Requiere login
if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'unauth']);
  exit;
}

require __DIR__ . '/../_csrf.php';
require __DIR__ . '/../config/db.php';
$pdo = db();

// Lee JSON crudo
$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) {
  http_response_code(400);
  echo json_encode(['error' => 'json']);
  exit;
}

// CSRF
$csrf = (string)($in['csrf'] ?? '');
if (!csrf_check($csrf)) {
  http_response_code(400);
  echo json_encode(['error' => 'csrf']);
  exit;
}

// Entradas
$food_id = (int)($in['food_id'] ?? 0);
$grams   = (float)($in['grams']   ?? 0);
$pre_bg  = isset($in['pre_bg']) && $in['pre_bg'] !== '' ? (float)$in['pre_bg'] : null;

if ($food_id <= 0 || $grams <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'input']);
  exit;
}

// Carga alimento
$st = $pdo->prepare('SELECT id,name,carbs_per_100g,unit,glycemic_index FROM foods WHERE id=?');
$st->execute([$food_id]);
$food = $st->fetch(PDO::FETCH_ASSOC);
if (!$food) {
  http_response_code(404);
  echo json_encode(['error' => 'food']);
  exit;
}

$carbs_per_100g = (float)$food['carbs_per_100g'];
$unit           = (string)($food['unit'] ?? 'g');
$carbs_g        = round($grams * $carbs_per_100g / 100.0, 2);

// Carga parámetros personales (si existen)
$uid = (int)$_SESSION['user_id'];
$st = $pdo->prepare('
  SELECT carb_ratio_g_per_unit, correction_mgdl_per_unit, target_bg
  FROM insulin_params
  WHERE user_id = ?
  LIMIT 1
');
$st->execute([$uid]);
$p = $st->fetch(PDO::FETCH_ASSOC);

// Calcula dosis estimada
$dose_units = null;
if ($p && $p['carb_ratio_g_per_unit'] !== null) {
  $ratio_g_u = (float)$p['carb_ratio_g_per_unit'];        // gramos de HC por 1 U
  if ($ratio_g_u > 0) {
    $dose_units = $carbs_g / $ratio_g_u;
  }
  // Corrección por glucemia si hay datos
  if ($pre_bg !== null && $p['correction_mgdl_per_unit'] !== null && $p['target_bg'] !== null) {
    $sens   = (float)$p['correction_mgdl_per_unit'];      // mg/dL que baja 1 U
    $target = (float)$p['target_bg'];
    if ($sens > 0) {
      $corr = ($pre_bg - $target) / $sens;                // puede ser negativa
      $dose_units = ($dose_units ?? 0) + $corr;
    }
  }
  if ($dose_units !== null) {
    // No proponemos negativos
    $dose_units = max(0.0, round($dose_units, 2));
  }
}

// Respuesta
echo json_encode([
  'ok'    => 1,
  'food'  => [
    'id'   => (int)$food['id'],
    'name' => (string)$food['name'],
    'unit' => $unit,
  ],
  'result'=> [
    'carbs_g'    => $carbs_g,
    'dose_units' => $dose_units, // puede ser null si no hay parámetros personales
  ]
], JSON_UNESCAPED_UNICODE);
