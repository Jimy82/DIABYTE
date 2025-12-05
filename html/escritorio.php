<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION["user_id"])) { header("Location: /login.php", true, 303); exit; }
require __DIR__."/config/db.php";
$pdo = db();
$uid = (int)$_SESSION["user_id"];

// Contar ingestas de hoy (intenta v_intakes y cae a intakes si no existe)
$todayCount = 0;
try {
  $st = $pdo->prepare("SELECT COUNT(*) FROM v_intakes WHERE user_id=? AND DATE(occurred_at)=CURDATE()");
  $st->execute([$uid]);
  $todayCount = (int)$st->fetchColumn();
} catch (Throwable $e) {
  $st = $pdo->prepare("SELECT COUNT(*) FROM intakes WHERE user_id=? AND DATE(occurred_at)=CURDATE()");
  $st->execute([$uid]);
  $todayCount = (int)$st->fetchColumn();
}

// Mensaje (p.ej. ?msg=menu_eliminado)
$msg = isset($_GET["msg"]) ? (string)$_GET["msg"] : null;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <link rel="stylesheet" href="/estilo.css">
  <title>Escritorio</title>
  <!--<style>
    body{font-family:sans-serif;max-width:880px;margin:1.2rem auto;padding:0 1rem}
    .btn{padding:.5rem .8rem;background:#eee;border:1px solid #ccc;border-radius:.4rem;text-decoration:none;display:inline-block;margin:.2rem 0}
    .row{margin:.6rem 0}
    .msg{color:green;margin:.5rem 0}
  </style>-->
</head>
<body>
  <h1>Escritorio</h1>
  <?php if ($msg === "menu_eliminado"): ?>
    <p class="msg">Menú eliminado.</p>
  <?php endif; ?>

  <div class="row"><strong>Ingestas de hoy:</strong> <?= (int)$todayCount ?></div>

  <div class="row">
    <a href="/editar_menu.php" class="btn">Editar menú</a>
    <a href="/alimentos.php" class="btn">Alimentos</a>
    <a href="/calculadora.php" class="btn">Calculadora</a>
    <a href="/historial.php" class="btn">Historial</a>
    <a href="/parametros.php" class="btn">Parámetros personales</a>
    <a href="/logout.php" class="btn">Salir</a>
  </div>
</body>
</html>
