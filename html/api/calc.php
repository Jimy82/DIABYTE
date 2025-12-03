<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauth']);
    exit;
}

require __DIR__ . '/../_csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'json']);
    exit;
}

if (!csrf_check($data['csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'csrf']);
    exit;
}

$foodId = (int)($data['food_id'] ?? 0);
$grams  = (float)($data['grams'] ?? 0);
$preBg  = isset($data['pre_bg']) ? (float)$data['pre_bg'] : null;

if ($foodId <= 0 || $grams <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'params']);
    exit;
}

require __DIR__ . '/../config/db.php';
$pdo = db();

$st = $pdo->prepare('SELECT id, name, carbs_per_100g, glycemic_index, unit FROM foods WHERE id=?');
$st->execute([$foodId]);
$food = $st->fetch(PDO::FETCH_ASSOC);

if (!$food) {
    http_response_code(404);
    echo json_encode(['error' => 'food']);
    exit;
}

$carbs = round(((float)$food['carbs_per_100g'] * $grams) / 100.0, 2);

$st2 = $pdo->prepare('SELECT carb_ratio, insulin_sensitivity, target_bg 
                      FROM insulin_params 
                      WHERE user_id=? 
                      LIMIT 1');
$st2->execute([(int)$_SESSION['user_id']]);
$params = $st2->fetch(PDO::FETCH_ASSOC);

$dose = null;
if ($params) {
    $ratio = (float)$params['carb_ratio'];
    $isf   = (float)$params['insulin_sensitivity'];
    $tgt   = (float)$params['target_bg'];

    $bolus = $ratio > 0 ? ($carbs / $ratio) : 0.0;
    $corr  = ($preBg !== null && $isf > 0) ? max(0.0, ($preBg - $tgt) / $isf) : 0.0;

    $dose  = round($bolus + $corr, 2);
}

echo json_encode([
    'food'   => [
        'id'   => (int)$food['id'],
        'name' => $food['name'],
        'unit' => $food['unit'],
        'gi'   => $food['glycemic_index']
    ],
    'input'  => [
        'grams'  => $grams,
        'pre_bg' => $preBg
    ],
    'result' => [
        'carbs_g'    => $carbs,
        'dose_units' => $dose
    ]
]);
