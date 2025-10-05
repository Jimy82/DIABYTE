<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['user_id'])) { header('Location: /login.php', true, 303); exit; }
require __DIR__ . '/config/db.php';
$pdo = db(); // devuelve PDO

// CSRF
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];

// Filtros y paginación
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page = max(1, (int)($_GET['p'] ?? 1));
$per = 25;
$off = ($page-1)*$per;

$params = [];
$where = '';
if ($q !== '') { $where = 'WHERE name LIKE :q'; $params[':q'] = "%$q%"; }

// total
$st = $pdo->prepare("SELECT COUNT(*) FROM foods $where");
$st->execute($params);
$total = (int)$st->fetchColumn();

// datos
$sql = "SELECT id,name,carbs_per_100g,glycemic_index FROM foods $where ORDER BY name LIMIT :per OFFSET :off";
$st = $pdo->prepare($sql);
foreach ($params as $k=>$v) { $st->bindValue($k,$v,PDO::PARAM_STR); }
$st->bindValue(':per',$per,PDO::PARAM_INT);
$st->bindValue(':off',$off,PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><title>Alimentos</title>
<link rel="stylesheet" href="/estilo.css">
<style>
form.inline{display:inline}
table{border-collapse:collapse;width:100%}
th,td{border:1px solid #ddd;padding:.4rem;font-size:.95rem}
thead{background:#f3f3f3}
input[type=text],input[type=number]{padding:.35rem}
.actions{display:flex;gap:.4rem}
</style>
</head><body>
<nav><a href="/escritorio.php">← Volver</a></nav>
<h1>Alimentos</h1>

<section>
  <h2>Añadir alimento</h2>
  <form method="post" action="/api_foods_add.php">
    <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>">
    <label>Nombre <input name="name" type="text" required></label>
    <label>HC/100g <input name="carbs" type="number" step="0.01" min="0" required></label>
    <label>IG <input name="gi" type="number" min="0" max="150"></label>
    <button type="submit">Añadir</button>
  </form>
</section>

<hr>

<section>
  <form method="get" action="/alimentos.php" style="margin:.5rem 0">
    <input type="text" name="q" placeholder="Buscar por nombre…" value="<?=htmlspecialchars($q)?>" style="max-width:320px">
    <button type="submit">Buscar</button>
    <span style="margin-left:.6rem">Total: <?=$total?></span>
  </form>

  <table>
    <thead><tr>
      <th>Nombre</th><th>HC/100g</th><th>IG</th><th>Acciones</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="4">Sin resultados</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td><?=htmlspecialchars($r['name'])?></td>
        <td><?=number_format((float)$r['carbs_per_100g'],2,',','.')?></td>
        <td><?=is_null($r['glycemic_index'])?'—':(int)$r['glycemic_index']?></td>
        <td class="actions">
          <a href="/calculadora.php?food_id=<?=$r['id']?>"><button type="button">Usar</button></a>
          <form class="inline" method="post" action="/api_foods_delete.php" onsubmit="return confirm('¿Eliminar este alimento?')">
            <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>">
            <input type="hidden" name="id" value="<?=$r['id']?>">
            <button type="submit">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</section>
</body></html>
