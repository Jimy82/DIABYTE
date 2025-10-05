<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'unauth']); exit; }
require __DIR__.'/config/db.php';
require __DIR__ . '/config/db.php';
$pdo = db();

$input = file_get_contents('php://input');
$isJson = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;

if ($isJson && $input) {
  $data = json_decode($input, true);
  if (!is_array($data)) { http_response_code(400); echo json_encode(['error'=>'json']); exit; }
  if (empty($_SESSION['csrf']) || ($data['csrf'] ?? '') !== $_SESSION['csrf']) { http_response_code(400); echo json_encode(['error'=>'csrf']); exit; }
  $name = trim((string)($data['name'] ?? ''));
  $carb = (float)($data['carbs'] ?? 0);
  $gi   = isset($data['gi']) && $data['gi'] !== '' ? (int)$data['gi'] : null;
} else {
  if (empty($_SESSION['csrf']) || ($_POST['csrf'] ?? '') !== $_SESSION['csrf']) { http_response_code(400); echo 'CSRF'; exit; }
  $name = trim((string)($_POST['name'] ?? ''));
  $carb = (float)($_POST['carbs'] ?? 0);
  $gi   = isset($_POST['gi']) && $_POST['gi'] !== '' ? (int)$_POST['gi'] : null;
}

if ($name === '' || $carb < 0) { http_response_code(422); echo json_encode(['error'=>'invalid']); exit; }

$stmt = $pdo->prepare("INSERT INTO foods(name,carbs_per_100g,glycemic_index,unit)
VALUES(:n,:c,:g,'g')
ON DUPLICATE KEY UPDATE carbs_per_100g=VALUES(carbs_per_100g), glycemic_index=VALUES(glycemic_index), unit='g'");
$stmt->execute([':n'=>$name, ':c'=>$carb, ':g'=>$gi]);

if ($isJson) { echo json_encode(['ok'=>1]); }
else { header('Location: /alimentos.php?ok=1', true, 303); }
