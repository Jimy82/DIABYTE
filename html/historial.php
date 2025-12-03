<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION["user_id"])) {
    header("Location: /login.php", true, 303);
    exit;
}

require __DIR__."/config/db.php";
$pdo = db();
$uid = (int)$_SESSION["user_id"];

/*
   En el nuevo schema:
   - intakes.source_type = 'food' --> source_id referencia foods.id
   - intakes.source_type = 'recipe' --> source_id referencia recipes.id
*/

$sql = "
    SELECT
        i.id,
        i.occurred_at,
        i.grams,
        i.carbs_g,
        i.dose_units,
        i.pre_bg,
        i.post_bg,
        CASE
            WHEN i.source_type = 'food' THEN f.name
            WHEN i.source_type = 'recipe' THEN r.name
            ELSE '—'
        END AS name
    FROM intakes i
    LEFT JOIN foods f   ON (i.source_type = 'food'   AND f.id = i.source_id)
    LEFT JOIN recipes r ON (i.source_type = 'recipe' AND r.id = i.source_id)
    WHERE i.user_id = ?
    ORDER BY i.occurred_at DESC
    LIMIT 200
";

$st = $pdo->prepare($sql);
$st->execute([$uid]);
$rows = $st->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Historial</title>
  <style>
    table{border-collapse:collapse;width:100%}
    th,td{border:1px solid #ccc;padding:.4rem;text-align:left}
    .btn{padding:.4rem .6rem;border:1px solid #ccc;background:#eee;border-radius:.3rem;text-decoration:none}
  </style>
</head>
<body>
  <h1>Historial</h1>
  <p><a class="btn" href="/escritorio.php">Volver</a></p>

  <?php if (!$rows): ?>
    <p>No hay ingestas aún.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Fecha</th><th>Alimento</th><th>Gramos</th><th>HC (g)</th><th>U insulina</th><th>Pre</th><th>Post</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?=htmlspecialchars($r["occurred_at"])?></td>
          <td><?=htmlspecialchars($r["name"] ?? "—")?></td>
          <td><?=number_format((float)$r["grams"],2,",",".")?></td>
          <td><?=number_format((float)$r["carbs_g"],2,",",".")?></td>
          <td><?=number_format((float)$r["dose_units"],2,",",".")?></td>
          <td><?=is_null($r["pre_bg"])?"—":(int)$r["pre_bg"]?></td>
          <td><?=is_null($r["post_bg"])?"—":(int)$r["post_bg"]?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</body>
</html>
