<?php
declare(strict_types=1);
require __DIR__ . "/config/db.php";
$pdo = db();
session_start();
if (empty($_SESSION["user_id"])) { header("Location: /login.php", true, 303); exit; }
require __DIR__."/config/db.php";

$uid = (int)$_SESSION["user_id"];

// CSRF
if (empty($_SESSION["csrf"])) { $_SESSION["csrf"] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION["csrf"];

// Upsert fila del usuario por si no existe
$pdo->exec("INSERT IGNORE INTO insulin_params (user_id,carb_ratio,insulin_sensitivity,target_bg,active_insulin_time)
            VALUES ($uid,10,50,100,240)");

// POST: guardar
$ok = null; $err = null;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $in = $_POST;
    if (($in["csrf"] ?? "") !== $csrf) {
        http_response_code(400); $err = "CSRF inválido";
    } else {
        $cr   = isset($in["carb_ratio"]) ? (float)$in["carb_ratio"] : 0;
        $isf  = isset($in["insulin_sensitivity"]) ? (float)$in["insulin_sensitivity"] : 0;
        $tbg  = isset($in["target_bg"]) ? (int)$in["target_bg"] : 0;
        $ait  = isset($in["active_insulin_time"]) ? (int)$in["active_insulin_time"] : 0;

        if ($cr<=0 || $isf<=0 || $tbg<=0 || $ait<=0) {
            $err = "Valores no válidos.";
        } else {
            $st = $pdo->prepare("UPDATE insulin_params
                                 SET carb_ratio=?, insulin_sensitivity=?, target_bg=?, active_insulin_time=?
                                 WHERE user_id=?");
            $st->execute([$cr,$isf,$tbg,$ait,$uid]);
            $ok = "Parámetros guardados.";
        }
    }
}

// Cargar actuales
$st = $pdo->prepare("SELECT carb_ratio,insulin_sensitivity,target_bg,active_insulin_time
                     FROM insulin_params WHERE user_id=?");
$st->execute([$uid]);
$p = $st->fetch(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="es">
<head>
  <meta charset="utf-8">
  <title>Registro</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="diabyte-logo-v1.png">
  <link rel="stylesheet" href="/estilo.css">
  <link rel="icon" type="image/png" href="/diabyte-logo.png">

<!--<style>
  form{max-width:520px;margin:2rem auto;padding:1rem;border:1px solid #ddd;border-radius:12px}
  label{display:block;margin:.6rem 0 .2rem}
  input{width:100%;padding:.5rem}
  .msg{margin:.8rem 0}
</style>-->
</head>
<body>
  <div class="desk-buttons">
    <a href="/escritorio.php" class="btn">Escritorio</a>
    <a href="/alimentos.php" class="btn">Alimentos</a>
    <a href="/calculadora.php" class="btn">Calculadora</a>
    <a href="/editar_menu.php" class="btn">Editar menú</a>
    <a href="/logout.php" class="btn">Salir</a>
  </div>

  <form method="post">
    <h2>Parámetros</h2>
    <?php if ($ok): ?><div class="msg" style="color:green"><?=htmlspecialchars($ok)?></div><?php endif; ?>
    <?php if ($err): ?><div class="msg" style="color:#b00"><?=htmlspecialchars($err)?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>">

    <label>Ratio de carbohidratos (g HC por 1U insulina)</label>
    <input type="number" name="carb_ratio" step="0.1" min="0.1" value="<?=htmlspecialchars((string)$p["carb_ratio"])?>">

    <label>Sensibilidad a la insulina (mg/dL bajados por 1U)</label>
    <input type="number" name="insulin_sensitivity" step="1" min="1" value="<?=htmlspecialchars((string)$p["insulin_sensitivity"])?>">

    <label>Glucemia objetivo (mg/dL)</label>
    <input type="number" name="target_bg" step="1" min="50" value="<?=htmlspecialchars((string)$p["target_bg"])?>">

    <label>Insulina activa (minutos)</label>
    <input type="number" name="active_insulin_time" step="5" min="30" value="<?=htmlspecialchars((string)$p["active_insulin_time"])?>">

    <div style="margin-top:1rem;display:flex;gap:.5rem">
      <button type="submit">Guardar</button>
      <a href="/escritorio.php"><button type="button">Volver</button></a>
    </div>
  </form>
</body></html>
