<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION["user_id"])) { header("Location: /login.php", true, 303); exit; }

require __DIR__."/config/db.php";
$pdo = db();
$uid = (int)$_SESSION["user_id"];

// Contador
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

$msg = $_GET["msg"] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Escritorio</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/estilo.css">
  <link rel="icon" type="image/png" href="/diabyte-logo.png">


  <style>
    .desk-wrap {
      width: 100%;
      max-width: none;         /* rompe el límite de 880px de tu CSS */
      margin: 0;
      padding: 2rem 0;
      text-align: center;      /* centro horizontal */
    }
    .desk-logo {
      width: 350px;
      max-width: 90%;
      display: block;
      margin: 2rem auto 3rem auto; /* igual que la maqueta */
    }
    .desk-buttons {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;       /* botones centrados */
      gap: 1rem;
      margin-top: 2rem;
    }
  </style>
</head>

<body>

<div class="desk-wrap">

  <h1></h1>
  <!--<p><strong>Ingestas de hoy:</strong> <?= $todayCount ?></p>-->

  <div class="logo-circle">
    <img src="diabyte-logo1.png" alt="DIABYTE">
</div>


  <?php if ($msg === "menu_eliminado"): ?>
    <p class="msg">Menú eliminado.</p>
  <?php endif; ?>

  <div class="desk-buttons">
    <a href="/alimentos.php" class="btn">Alimentos</a>
    <a href="/calculadora.php" class="btn">Calculadora</a>
    <a href="/editar_menu.php" class="btn">Editar menú</a>
    <a href="/historial.php" class="btn">Historial</a>
    <a href="/parametros.php" class="btn">Parámetros</a>
    <a href="/logout.php" class="btn">Salir</a>
  </div>

</div>

</body>
</html>
