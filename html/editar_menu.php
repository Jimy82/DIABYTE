<?php
declare(strict_types=1);

/**
 * editar_menu.php
 * Edición del menú diario (desayuno/comida/cena/snack) para el usuario autenticado.
 * Funciones:
 *  - Crea el menú del día si no existe.
 *  - Añadir item (food/recipe) con gramos a un bloque (desayuno|comida|cena|snack).
 *  - Editar gramos de un item.
 *  - Eliminar item.
 * Seguridad:
 *  - Requiere sesión iniciada.
 *  - CSRF en todas las acciones POST.
 *  - Consultas con PDO preparado.
 */

session_start();

// Bloqueo si no autenticado
if (empty($_SESSION['user_id'])) {
    header('Location: login.php', true, 303);
    exit;
}

// Cabeceras de seguridad
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

require __DIR__ . '/config/db.php';
$pdo = db();

// ==== Utilidades comunes ====
function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . ($base === '' ? '' : $base);
}
function redirect(string $path, array $qs = [], int $code = 303): never {
    $url = rtrim(base_url(), '/') . '/' . ltrim($path, '/');
    if ($qs) { $url .= '?' . http_build_query($qs); }
    header('Location: ' . $url, true, $code);
    exit;
}
function clean(string $s): string {
    return trim(preg_replace('/\s+/u', ' ', $s));
}
function valid_date(string $d): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    [$y,$m,$day] = array_map('intval', explode('-', $d));
    return checkdate($m, $day, $y);
}

// CSRF
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_token(): string { return $_SESSION['csrf']; }
function csrf_check(string $t): bool { return hash_equals($_SESSION['csrf'] ?? '', $t); }

// ==== Entrada: fecha del menú ====
$userId = (int)$_SESSION['user_id'];
$date   = isset($_GET['date']) ? clean($_GET['date']) : (new DateTimeImmutable('today'))->format('Y-m-d');
if (!valid_date($date)) {
    $date = (new DateTimeImmutable('today'))->format('Y-m-d');
}

// ==== Asegurar existencia del menú de ese día ====
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT id, user_id FROM menus WHERE user_id=? AND date=? LIMIT 1');
    $stmt->execute([$userId, $date]);
    $menu = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$menu) {
        $ins = $pdo->prepare('INSERT INTO menus (user_id, date) VALUES (?, ?)');
        $ins->execute([$userId, $date]);
        $menuId = (int)$pdo->lastInsertId();
    } else {
        if ((int)$menu['user_id'] !== $userId) {
            throw new RuntimeException('Acceso no autorizado al menú.');
        }
        $menuId = (int)$menu['id'];
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(403);
    echo 'Error de acceso al menú.';
    exit;
}

// ==== Acciones POST: add/update/delete ====
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!csrf_check($token)) {
        http_response_code(400);
        $errors[] = 'CSRF inválido.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'add') {
                $type        = $_POST['type'] ?? '';
                $sourceType  = $_POST['source_type'] ?? '';
                $sourceId    = (int)($_POST['source_id'] ?? 0);
                $grams       = (float)($_POST['grams'] ?? 0);

                if (!in_array($type, ['desayuno','comida','cena','snack'], true)) { $errors[] = 'Bloque inválido.'; }
                if (!in_array($sourceType, ['food','recipe'], true)) { $errors[] = 'Tipo de fuente inválido.'; }
                if ($sourceId <= 0) { $errors[] = 'Elemento no válido.'; }
                if ($grams <= 0 || $grams > 100000) { $errors[] = 'Gramos no válidos.'; }

                if (!$errors) {
                    // Verificación de existencia del elemento
                    if ($sourceType === 'food') {
                        $check = $pdo->prepare('SELECT id FROM foods WHERE id=? LIMIT 1');
                    } else {
                        // Solo recetas del usuario
                        $check = $pdo->prepare('SELECT id FROM recipes WHERE id=? AND user_id=? LIMIT 1');
                    }
                    if ($sourceType === 'food') { $check->execute([$sourceId]); }
                    else { $check->execute([$sourceId, $userId]); }

                    if (!$check->fetchColumn()) { throw new RuntimeException('Elemento inexistente.'); }

                    $ins = $pdo->prepare('INSERT INTO menu_items (menu_id, type, source_type, source_id, grams) VALUES (?, ?, ?, ?, ?)');
                    $ins->execute([$menuId, $type, $sourceType, $sourceId, $grams]);
                }
            } elseif ($action === 'update') {
                $itemId = (int)($_POST['item_id'] ?? 0);
                $grams  = (float)($_POST['grams'] ?? 0);
                if ($itemId <= 0 || $grams <= 0 || $grams > 100000) { $errors[] = 'Datos no válidos.'; }
                if (!$errors) {
                    // Asegurar pertenencia al menú del usuario
                    $own = $pdo->prepare('SELECT mi.id FROM menu_items mi INNER JOIN menus m ON m.id=mi.menu_id WHERE mi.id=? AND m.user_id=? AND m.id=? LIMIT 1');
                    $own->execute([$itemId, $userId, $menuId]);
                    if (!$own->fetchColumn()) { throw new RuntimeException('Item no accesible.'); }

                    $upd = $pdo->prepare('UPDATE menu_items SET grams=? WHERE id=?');
                    $upd->execute([$grams, $itemId]);
                }
            } elseif ($action === 'delete') {
                $itemId = (int)($_POST['item_id'] ?? 0);
                if ($itemId <= 0) { $errors[] = 'Item no válido.'; }
                if (!$errors) {
                    $own = $pdo->prepare('SELECT mi.id FROM menu_items mi INNER JOIN menus m ON m.id=mi.menu_id WHERE mi.id=? AND m.user_id=? AND m.id=? LIMIT 1');
                    $own->execute([$itemId, $userId, $menuId]);
                    if (!$own->fetchColumn()) { throw new RuntimeException('Item no accesible.'); }

                    $del = $pdo->prepare('DELETE FROM menu_items WHERE id=?');
                    $del->execute([$itemId]);
                }
            }
        } catch (Throwable $e) {
            $errors[] = 'Operación no realizada.';
        }
    }

    // Rotar CSRF tras POST
    $_SESSION['csrf'] = bin2hex(random_bytes(32));

    // Redirección POST/Redirect/GET para evitar reenvíos
    redirect('editar_menu.php', ['date' => $date, 'ok' => (int)empty($errors)]);
}

// ==== Datos para render ====

/** Obtener items del menú con nombres y macros por 100g cuando aplique */
$itemsStmt = $pdo->prepare("
    SELECT mi.id, mi.type, mi.source_type, mi.source_id, mi.grams,
           CASE WHEN mi.source_type='food' THEN f.name ELSE r.name END AS name,
           CASE WHEN mi.source_type='food' THEN f.carbs_per_100g ELSE NULL END AS carbs100,
           CASE WHEN mi.source_type='food' THEN f.protein_per_100g ELSE NULL END AS protein100,
           CASE WHEN mi.source_type='food' THEN f.fat_per_100g ELSE NULL END AS fat100,
           CASE WHEN mi.source_type='food' THEN f.kcal_per_100g ELSE NULL END AS kcal100
    FROM menu_items mi
    LEFT JOIN foods f   ON (mi.source_type='food'   AND mi.source_id=f.id)
    LEFT JOIN recipes r ON (mi.source_type='recipe' AND mi.source_id=r.id AND r.user_id=?)
    WHERE mi.menu_id=?
    ORDER BY FIELD(mi.type,'desayuno','comida','cena','snack'), mi.id ASC
");
$itemsStmt->execute([$userId, $menuId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

/** Cargar alimentos (para el selector) */
$foods = $pdo->query("SELECT id, name, default_serving_g FROM foods ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

/** Cargar recetas del usuario (para el selector) */
$recStmt = $pdo->prepare("SELECT id, name FROM recipes WHERE user_id=? ORDER BY name ASC");
$recStmt->execute([$userId]);
$recipes = $recStmt->fetchAll(PDO::FETCH_ASSOC);

/** Agrupar items por bloque */
$groups = ['desayuno'=>[], 'comida'=>[], 'cena'=>[], 'snack'=>[]];
foreach ($items as $it) { $groups[$it['type']][] = $it; }

/** Totales simples para alimentos (recetas no agregadas aquí) */
function calc_carbs(?float $c100, float $g): ?float { return $c100 === null ? null : round(($c100 * $g) / 100.0, 2); }

// Parámetros de vista
$okFlag = isset($_GET['ok']) && (int)$_GET['ok'] === 1;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Registro</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="diabyte-logo-v1.png">
  <link rel="stylesheet" href="/estilo.css">
  <link rel="icon" type="image/png" href="/diabyte-logo.png">

  <!--<style>
    :root { font-family: system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Helvetica Neue",Arial,"Noto Sans",sans-serif; }
    body { margin:0; padding:1rem; background:#f6f7fb; }
    header { max-width:1100px; margin:0 auto 1rem; display:flex; justify-content:space-between; align-items:center; }
    .container { max-width:1100px; margin:0 auto; display:grid; grid-template-columns:1fr; gap:1rem; }
    .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }
    @media (max-width: 900px) { .grid { grid-template-columns:1fr; } }
    .card { background:#fff; border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,.08); padding:1rem 1.25rem; }
    h2 { font-size:1rem; margin:.2rem 0 .8rem; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:.5rem .6rem; border-bottom:1px solid #e9edf2; text-align:left; }
    th { font-weight:600; background:#f2f5fb; }
    .row-actions { display:flex; gap:.4rem; }
    .small { font-size:.9rem; color:#555; }
    .ok { background:#e6ffed; border:1px solid #a7f3d0; color:#065f46; padding:.5rem .7rem; border-radius:8px; margin-bottom:.6rem; display:inline-block; }
    .err { background:#ffe8e8; border:1px solid #f4bcbc; color:#8a1c1c; padding:.5rem .7rem; border-radius:8px; margin-bottom:.6rem; display:inline-block; }
    input, select { padding:.45rem .5rem; border:1px solid #d7dbdf; border-radius:8px; }
    button { border:0; background:#1f6feb; color:#fff; padding:.55rem .8rem; border-radius:8px; font-weight:600; cursor:pointer; }
    .muted { color:#889099; }
  </style>-->
</head>
<body>
  <div class="desk-buttons">
    <a href="/Escritorio.php" class="btn">Escritorio</a>
    <a href="/alimentos.php" class="btn">Alimentos</a>
    <a href="/calculadora.php" class="btn">Calculadora</a>
    <a href="/parametros.php" class="btn">Parámetros</a>
    <a href="/logout.php" class="btn">Salir</a>
  </div>
  <header>
    <div>
      <div class="small muted">Menú de</div>
      <h1 style="margin:.1rem 0; font-size:1.25rem;"><?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
  </header>

  <main class="container">
    <section class="card">
      <?php if ($okFlag): ?><div class="ok">Cambios guardados.</div><?php endif; ?>
      <?php foreach ($errors as $e): ?><div class="err"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>

      <form method="get" action="" class="small" style="display:flex; gap:.5rem; align-items:center; margin-bottom:.8rem;">
        <label for="date">Fecha</label>
        <input type="date" id="date" name="date" value="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit">Ir</button>
      </form>

      <details open class="card" style="padding:.75rem 1rem; margin-bottom:1rem;">
        <summary style="cursor:pointer; font-weight:600;">Añadir elemento</summary>
        <form method="post" action="" class="small" style="display:grid; grid-template-columns:repeat(6,1fr); gap:.5rem; margin-top:.8rem;">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="add">

          <label>Bloque
            <select name="type" required>
              <option value="desayuno">Desayuno</option>
              <option value="comida">Comida</option>
              <option value="cena">Cena</option>
              <option value="snack">Snack</option>
            </select>
          </label>

          <label>Tipo
            <select name="source_type" id="source_type" required onchange="
              document.getElementById('sel_food').style.display = this.value==='food' ? 'block':'none';
              document.getElementById('sel_recipe').style.display = this.value==='recipe' ? 'block':'none';
            ">
              <option value="food">Alimento</option>
              <option value="recipe">Receta</option>
            </select>
          </label>

          <label id="sel_food">Alimento
            <select name="source_id">
              <?php foreach ($foods as $f): ?>
                <option value="<?= (int)$f['id'] ?>">
                  <?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?>
                  <?php if (!is_null($f['default_serving_g'])): ?>
                    (<?= (float)$f['default_serving_g'] ?> g)
                  <?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <label id="sel_recipe" style="display:none;">Receta
            <select name="source_id">
              <?php foreach ($recipes as $r): ?>
                <option value="<?= (int)$r['id'] ?>">
                  <?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <label>Gramos
            <input type="number" step="0.1" min="1" max="100000" name="grams" value="100" required>
          </label>

          <div style="display:flex; align-items:end;">
            <button type="submit">Añadir</button>
          </div>
        </form>
      </details>

      <div class="grid">
        <?php foreach (['desayuno'=>'Desayuno','comida'=>'Comida','cena'=>'Cena','snack'=>'Snack'] as $key=>$label): ?>
          <section class="card">
            <h2><?= $label ?></h2>
            <table>
              <thead>
                <tr>
                  <th>Elemento</th>
                  <th class="small">Tipo</th>
                  <th class="small">Gramos</th>
                  <th class="small">HC (aprox)</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($groups[$key])): ?>
                <tr><td colspan="5" class="muted small">Sin elementos.</td></tr>
              <?php else: foreach ($groups[$key] as $it): ?>
                <?php
                    $hc = calc_carbs($it['carbs100'] !== null ? (float)$it['carbs100'] : null, (float)$it['grams']);
                ?>
                <tr>
                  <td><?= htmlspecialchars((string)$it['name'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="small"><?= $it['source_type'] === 'food' ? 'Alimento' : 'Receta' ?></td>
                  <td class="small">
                    <form method="post" action="" style="display:flex; gap:.3rem; align-items:center;">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                      <input type="number" name="grams" step="0.1" min="1" max="100000" value="<?= (float)$it['grams'] ?>" style="width:7rem;">
                      <button type="submit">Guardar</button>
                    </form>
                  </td>
                  <td class="small"><?= $hc === null ? '—' : $hc . ' g' ?></td>
                  <td>
                    <form method="post" action="" class="row-actions" onsubmit="return confirm('Eliminar elemento?');">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                      <button type="submit" style="background:#e11d48;">Eliminar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </section>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
</body>
</html>
