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
  $id = (int)($data['id'] ?? 0);
} else {
  if (empty($_SESSION['csrf']) || ($_POST['csrf'] ?? '') !== $_SESSION['csrf']) { http_response_code(400); echo 'CSRF'; exit; }
  $id = (int)($_POST['id'] ?? 0);
}

if ($id <= 0) { http_response_code(422); echo json_encode(['error'=>'invalid']); exit; }

$pdo->prepare("DELETE FROM foods WHERE id=:id")->execute([':id'=>$id]);

if ($isJson) { echo json_encode(['ok'=>1]); }
else { header('Location: /alimentos.php?del=1', true, 303); }
