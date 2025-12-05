<?php
declare(strict_types=1);

session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /login.php', true, 303);
    exit;
}

require __DIR__ . '/_csrf.php';
require __DIR__ . '/config/db.php';

$pdo = db();

// Si viene food_id por GET, precargamos ese alimento
$foodId = (int)($_GET['food_id'] ?? 0);
$food   = null;

if ($foodId > 0) {
    $st = $pdo->prepare(
        'SELECT id, name, carbs_per_100g, unit, glycemic_index
         FROM foods
         WHERE id = ?'
    );
    $st->execute([$foodId]);
    $food = $st->fetch(PDO::FETCH_ASSOC);
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Registro</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/estilo.css">
  <link rel="icon" type="image/png" href="diabyte-logo-v1.png">
    <!--<style>
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
        }
        .result {
            font-size: 1.1rem;
            padding: .5rem;
            border: 1px solid #ddd;
            border-radius: .5rem;
        }
    </style>-->
</head>
<body>
<div class="container card">
    <h1>Calculadora de hidratos</h1>

    <form id="calcForm" class="form-grid" onsubmit="return false">
        <input type="hidden" id="csrf" value="<?=htmlspecialchars($csrf)?>">

        <label for="food_id">Alimento</label>
        <select id="food_id" required>
            <?php if ($food): ?>
                <option value="<?=$food['id']?>">
                    <?=htmlspecialchars($food['name'])?>
                    (<?=$food['carbs_per_100g']?> HC/100<?=$food['unit']?>)
                </option>
            <?php endif; ?>
        </select>

        <label for="grams">Gramos</label>
        <input type="number" id="grams" step="1" min="1" required value="100">

        <label for="pre_bg">Glucemia previa (mg/dL)</label>
        <input type="number" id="pre_bg" step="1" min="40" max="400" placeholder="opcional">

        <div></div>
        <button type="button" id="btnCalc">Calcular</button>
    </form>

    <div class="result" id="out" style="margin-top:1rem;display:none"></div>

    <div class="row" style="margin-top:1rem;display:none" id="saveRow">
        <label for="post_bg">Glucemia posterior (mg/dL, opcional)</label>
        <input type="number" id="post_bg" step="1" min="40" max="400">
        <button type="button" id="btnSave">Guardar ingesta</button>
        <a class="btn" href="/historial.php">Ver historial</a>
    </div>

    <div class="mt-2">
        <a href="/alimentos.php">Volver a alimentos</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const sel    = document.getElementById('food_id');
    const grams  = document.getElementById('grams');
    const preBg  = document.getElementById('pre_bg');
    const out    = document.getElementById('out');
    const saveRow= document.getElementById('saveRow');
    const csrf   = document.getElementById('csrf').value;

    // Rellenar combo si viene vacío
    if (sel.options.length === 0) {
        try {
            const rsp   = await fetch('/api_foods.php');
            if (rsp.ok) {
                const foods = await rsp.json();
                foods.slice(0, 300).forEach(f => {
                    const o = document.createElement('option');
                    o.value = f.id;
                    o.textContent = `${f.name} (${f.carbs_per_100g} HC/100${f.unit})`;
                    sel.appendChild(o);
                });
            }
        } catch (e) {
            console.error(e);
        }
    }

    document.getElementById('btnCalc').addEventListener('click', async () => {
        const body = {
            csrf: csrf,
            food_id: parseInt(sel.value || '0', 10),
            grams: parseFloat(grams.value || '0'),
            pre_bg: preBg.value ? parseFloat(preBg.value) : null
        };

        const r = await fetch('/api/calc.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(body)
        });

        const data = await r.json();

        if (data.error) {
            out.style.display = 'block';
            out.textContent   = 'Error: ' + data.error;
            saveRow.style.display = 'none';
            return;
        }

        const carbs = data.result.carbs_g.toFixed(2);
        const dose  = (data.result.dose_units !== null)
            ? data.result.dose_units.toFixed(2)
            : '—';

        out.style.display = 'block';
        out.innerHTML =
            `<b>${data.food.name}</b><br>` +
            `Gramos: ${body.grams} ${data.food.unit} · ` +
            `HC: <b>${carbs} g</b> · ` +
            `Dosis estimada: <b>${dose} U</b>`;

        out.dataset.payload = JSON.stringify({
            csrf: body.csrf,
            food_id: body.food_id,
            grams: body.grams,
            pre_bg: body.pre_bg,
            carbs_g: data.result.carbs_g,
            dose_units: data.result.dose_units
        });

        saveRow.style.display = 'grid';
    });

    document.getElementById('btnSave').addEventListener('click', async () => {
        if (!out.dataset.payload) return;

        const payload = JSON.parse(out.dataset.payload);
        const postBg  = document.getElementById('post_bg').value;
        if (postBg) payload.post_bg = parseFloat(postBg);

        const r = await fetch('/api/save_intake.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });

        const data = await r.json();
        alert(data.ok ? 'Guardado' : 'Error al guardar');
    });
});
</script>
</body>
</html>
