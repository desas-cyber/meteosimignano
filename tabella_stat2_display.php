<?php
/**
 * ============================================================================
 * TABELLA SOGLIE TERMICHE - DISPLAY LAYER
 * Versione: 2.1 - 2026-11-13
 * ============================================================================
 * Tabella unica, due righe per soglia:
 *   - riga principale : stagione in corso (primo giorno, ultimo giorno, n. giorni)
 *   - sotto-riga      : ultima stagione completa
 * Con ref_data la tabella mostra la situazione come se fossimo quel giorno:
 * stagione in corso fino a ref_data e ultima stagione completa a quella data.
 *
 * PARAMETRI GET:
 *   ?ref_data=YYYY-MM-DD
 *   ?custom_campo=temp_min_abs|temp_max_abs
 *   ?custom_op=>=|>|<=|<
 *   ?custom_val=NUM
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../envelop.php';
require_once __DIR__ . '/api/api_tabella_stat_data.php';

$response = getStat2Data();

if (!$response['success']) {
    echo "<div style='font-family:Arial;font-size:13px;color:#c00;padding:10px;'>
            Errore: " . htmlspecialchars($response['error'] ?? 'Dati non disponibili') . "
          </div>";
    exit;
}

$righe = $response['righe'];
$meta  = $response['meta'];

$cur_campo     = isset($_GET['custom_campo']) ? $_GET['custom_campo'] : 'temp_min_abs';
$cur_op        = isset($_GET['custom_op'])    ? $_GET['custom_op']    : '<=';
$cur_val       = isset($_GET['custom_val'])   ? $_GET['custom_val']   : '';
$custom_attivo = ($cur_val !== '');
$ref_data      = $meta['ref_data'] ?? '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soglie Termiche</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 4px 0 8px 0;
            background: #fff;
        }

        /* ---- barra cima ---- */
        .top-bar {
            display: flex;
            align-items: stretch;
            justify-content: flex-end;
            max-width: 95%;
            margin: 4px auto 4px auto;
            gap: 6px;
        }
        .custom-trigger {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 10px;
            font-weight: bold;
            cursor: pointer;
            padding: 4px 10px;
            border-radius: 3px;
            border: 2px solid black;
            background: transparent;
            color: black;
            user-select: none;
            white-space: nowrap;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .custom-trigger:hover { border-color: red; color: red; }
        .custom-trigger.attivo {
            border-color: #4477aa;
            background: #6699cc;
            color: #fff;
        }
        .refresh-icon {
            display: inline-block;
            font-size: 1em;
            cursor: pointer;
            opacity: 0.5;
            vertical-align: middle;
            user-select: none;
        }
        .refresh-icon:hover { opacity: 1; color: #3366cc; }

        /* ---- popup personalizza ---- */
        .custom-popup {
            display: none;
            position: fixed;
            background: #fff;
            border: 1px solid #aaa;
            border-radius: 5px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.22);
            z-index: 9999;
            padding: 10px 12px;
            min-width: 210px;
            font-size: 11px;
            color: #333;
        }
        .custom-popup.open { display: block; }
        .pop-title {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 8px;
            color: #444;
            border-bottom: 1px solid #eee;
            padding-bottom: 4px;
        }
        .pop-row {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 6px;
        }
        .pop-row label { color: #666; font-size: 10px; width: 44px; }
        .custom-popup select,
        .custom-popup input[type=number] {
            font-size: 11px;
            padding: 2px 4px;
            border: 1px solid #bbb;
            border-radius: 3px;
            background: #fff;
        }
        .custom-popup input[type=number] { width: 54px; }
        .pop-btns { display: flex; gap: 5px; margin-top: 8px; }
        .pop-btns button {
            font-size: 10px;
            padding: 3px 10px;
            border-radius: 3px;
            cursor: pointer;
            border: 1px solid #6699cc;
            background: #dde8f5;
            color: #333;
        }
        .pop-btns button:hover { background: #6699cc; color: #fff; }
        .btn-reset { border-color: #c00 !important; background: #fff0f0 !important; color: #c00 !important; }
        .btn-reset:hover { background: #c00 !important; color: #fff !important; }

        /* ---- titolo ---- */
        .sez-title {
            font-size: 10px;
            font-weight: bold;
            color: #555;
            text-align: center;
            margin: 8px auto 2px auto;
            max-width: 95%;
            letter-spacing: 0.03em;
        }
        .sez-title .rif-info { font-weight: normal; color: #6699cc; text-transform: none; }

        /* ---- tabella ---- */
        table {
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            max-width: 95%;
            margin: 0 auto;
            width: 100%;
            table-layout: fixed;
        }
        th, td {
            padding: 2px 1px 2px 4px;
            vertical-align: middle;
            overflow: hidden;
            white-space: normal;
            font-size: 13px;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
        }
        th {
            background-color: rgba(173,173,173,0.8);
            font-weight: bold;
            text-align: center;
            border: 1px solid #000;
        }
        th:first-child, td:first-child { text-align: left; }
        th:nth-child(1), td:nth-child(1) { width: 34%; }
        th:nth-child(2), td:nth-child(2),
        th:nth-child(3), td:nth-child(3) { width: 23%; text-align: center; }
        th:nth-child(4), td:nth-child(4) { width: 20%; text-align: center; }

        /* riga principale: stagione in corso */
        tr.riga-att td { border-top: 1px solid #000; padding-top: 4px; padding-bottom: 1px; }
        /* sotto-riga: stagione di riferimento */
        tr.riga-rif td {
            border-bottom: 1px solid #000;
            font-size: 11px;
            color: #666;
            padding-top: 0;
            padding-bottom: 4px;
        }
        tr.riga-rif td:first-child { padding-left: 12px; }

        tr.riga-grigia td { background-color: rgba(200,200,200,0.5); }
        tr.riga-custom td { background-color: rgba(180,210,255,0.4); }

        .stag { font-size: 10px; color: #888; font-weight: normal; white-space: nowrap; }
        .parziale {
            font-size: 9px;
            color: #b36b00;
            border: 1px solid #e0b060;
            border-radius: 3px;
            padding: 0 3px;
            white-space: nowrap;
            cursor: help;
        }
        .da-inizio { font-size: 10px; font-style: italic; cursor: help; }

        /* ---- calendario giorno ---- */
        .cal-popup {
            display: none;
            position: fixed;
            background: #fff;
            border: 1px solid #aaa;
            border-radius: 4px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.25);
            z-index: 9999;
            padding: 6px;
            min-width: 170px;
            text-align: center;
            font-size: 11px;
            color: #333;
            white-space: nowrap;
        }
        .cal-popup.open { display: block; }
        .cal-days-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            margin-top: 3px;
        }
        .cal-day-head { font-size: 9px; color: #888; text-align: center; padding: 1px 0; }
        .cal-day {
            font-size: 9px; text-align: center; padding: 2px 1px;
            cursor: pointer; border-radius: 2px;
        }
        .cal-day:hover { background: #dde8f5; }
        .cal-day.empty { cursor: default; }
        .cal-day.selected-day { background: #6699cc; color: #fff; }
        .cal-day.today-day { font-weight: bold; color: #c00; }
        .cal-nav { display: flex; align-items: center; justify-content: center; gap: 6px; margin-bottom: 4px; }
        .cal-nav-btn { cursor: pointer; font-weight: bold; font-size: 13px; padding: 0 4px; color: #555; user-select: none; }
        .cal-nav-btn:hover { color: #000; }
        .cal-nav-year { font-weight: bold; min-width: 36px; }
        .cal-icon {
            display: inline-block; font-size: 0.85em; cursor: pointer;
            opacity: 0.65; vertical-align: middle; margin-left: 2px;
            line-height: 1; user-select: none;
        }
        .cal-icon:hover { opacity: 1; }

        /* ---- footer ---- */
        .stat-footer {
            text-align: center;
            font-size: clamp(5px, 2vw, 9px);
            color: #aaa;
            margin-top: 4px;
        }

        /* ---- responsive ---- */
        @media (min-width: 768px) {
            table { width: 85%; max-width: 75%; }
            th, td { font-size: 16px; padding: 5px 1px 5px 5px; }
            tr.riga-rif td { font-size: 13px; }
            .stag { font-size: 12px; }
            .parziale { font-size: 11px; }
            .da-inizio { font-size: 13px; }
            .sez-title { font-size: 13px; }
            .custom-trigger { font-size: 13px; padding: 3px 10px; }
            .custom-popup { font-size: 13px; min-width: 260px; }
            .custom-popup select,
            .custom-popup input[type=number] { font-size: 13px; }
            .pop-btns button { font-size: 12px; padding: 4px 14px; }
        }
    </style>
</head>
<body>

<!-- ============================================================
     BARRA CIMA
     ============================================================ -->
<div class="top-bar">
    <span class="refresh-icon" onclick="window.location.href=window.location.pathname" title="Ripristina">&#8635;</span>
    <span class="custom-trigger<?= $custom_attivo ? ' attivo' : '' ?>" id="custom-trigger">
        &#9881;<?php if ($custom_attivo): ?>&nbsp;<?= htmlspecialchars(($cur_campo === 'temp_max_abs' ? 'Max' : 'Min') . ' ' . $cur_op . ' ' . $cur_val) ?>&#176;C<?php else: ?>&nbsp;Personalizza<?php endif; ?>
    </span>
    <span class="custom-trigger" id="btn-grafico" title="Mostra grafico soglie">&#128200;&nbsp;Grafico</span>
</div>

<!-- Popup personalizza -->
<div id="custom-popup" class="custom-popup">
    <div class="pop-title">&#9881; Soglia personalizzata</div>
    <div class="pop-row">
        <label>Campo</label>
        <select id="f-campo">
            <option value="temp_min_abs"<?= $cur_campo === 'temp_min_abs' ? ' selected' : '' ?>>Min</option>
            <option value="temp_max_abs"<?= $cur_campo === 'temp_max_abs' ? ' selected' : '' ?>>Max</option>
        </select>
    </div>
    <div class="pop-row">
        <label>Soglia</label>
        <select id="f-op">
            <option value=">="<?= $cur_op === '>=' ? ' selected' : '' ?>>&ge;</option>
            <option value=">"<?=  $cur_op === '>'  ? ' selected' : '' ?>>&gt;</option>
            <option value="<="<?= $cur_op === '<=' ? ' selected' : '' ?>>&le;</option>
            <option value="<"<?=  $cur_op === '<'  ? ' selected' : '' ?>>&lt;</option>
        </select>
        <input type="number" id="f-val" step="0.5" min="-30" max="50"
               value="<?= htmlspecialchars($cur_val) ?>" placeholder="es. 15">
        &#176;C
    </div>
    <div class="pop-btns">
        <button onclick="applicaCustom()">Applica</button>
        <?php if ($custom_attivo): ?>
        <button class="btn-reset" onclick="resetCustom()">&#10005; Rimuovi</button>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================
     TITOLO + DATA DI RIFERIMENTO
     ============================================================ -->
<div class="sez-title">
    SOGLIE TERMICHE
    <span class="cal-icon" data-cal="cal-rif" title="Mostra la situazione a una data passata">&#128197;</span>
    <?php if ($ref_data !== ''): ?>
        <span class="rif-info">(situazione al <?= htmlspecialchars($meta['ref_label']) ?>)</span>
        <span class="refresh-icon" onclick="resetRefData()" title="Torna alla situazione attuale" style="opacity:0.7;font-size:0.9em;">&#8635;</span>
    <?php else: ?>
        <span class="rif-info">(confronto: ultima stagione completa)</span>
    <?php endif; ?>
</div>

<!-- ============================================================
     TABELLA UNICA
     ============================================================ -->
<table>
    <thead>
        <tr>
            <th>Soglia</th>
            <th>Primo giorno</th>
            <th>Ultimo giorno</th>
            <th>N&#176; giorni</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($righe as $r):
        $cls = $r['custom'] ? ' riga-custom' : ($r['grigio'] ? ' riga-grigia' : '');
        $a   = $r['attuale'];
        $p   = $r['rif'];
        $tip_parz = 'Stagione con dati incompleti'
                  . ($meta['primo_dato_label'] ? ' (dati termici dal ' . $meta['primo_dato_label'] . ')' : '');
    ?>
        <tr class="riga-att<?= $cls ?>">
            <td><?= $r['label'] ?> <span class="stag"><?= htmlspecialchars($a['stagione']) ?></span><?php if (!empty($a['parziale'])): ?> <span class="parziale" title="<?= htmlspecialchars($tip_parz) ?>">parziale</span><?php endif; ?></td>
            <td><?= $a['primo'] ?></td>
            <td><?= $a['ultimo'] ?></td>
            <td><?= $a['n'] ?></td>
        </tr>
        <tr class="riga-rif<?= $cls ?>">
            <td>&#8627; <?= htmlspecialchars($p['stagione']) ?><?php if ($p['parziale']): ?> <span class="parziale" title="<?= htmlspecialchars($tip_parz) ?>">parziale</span><?php endif; ?></td>
            <td><?= $p['primo'] ?></td>
            <td><?= $p['ultimo'] ?></td>
            <td><?= $p['n'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Popup calendario -->
<div id="cal-rif" class="cal-popup"></div>

<div class="stat-footer">
    stagione calda dal 15 gen, fredda dal 15 lug &middot; aggiornato: <?= htmlspecialchars($meta['generato_il']) ?>
</div>

<script>
var CTX2 = {
    oggi_reale:   '<?= $meta['oggi_reale'] ?>',
    ieri:         '<?= $meta['ieri'] ?>',
    ref_data:     '<?= htmlspecialchars($ref_data) ?>',
    custom_campo: '<?= htmlspecialchars($cur_campo) ?>',
    custom_op:    '<?= htmlspecialchars($cur_op) ?>',
    custom_val:   '<?= htmlspecialchars($cur_val) ?>'
};

var CAL2_STATE = {};

function parseDate2(s) { var p = s.split('-'); return {y:+p[0], m:+p[1], d:+p[2]}; }
function daysInMonth2(y, m) { return new Date(y, m, 0).getDate(); }
function isoDate2(y, m, d) { return y + '-' + (m < 10 ? '0' + m : m) + '-' + (d < 10 ? '0' + d : d); }

function buildUrl(params) {
    var base = window.location.pathname;
    var parts = [];
    for (var k in params)
        if (params[k] !== null && params[k] !== undefined && params[k] !== '')
            parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
    return base + (parts.length ? '?' + parts.join('&') : '');
}

// Parametri correnti: soglia custom (se attiva) e data di riferimento (se impostata)
function paramsCorrenti(conCustom, conRif) {
    var p = {};
    if (conCustom && CTX2.custom_val !== '') {
        p['custom_campo'] = CTX2.custom_campo;
        p['custom_op']    = CTX2.custom_op;
        p['custom_val']   = CTX2.custom_val;
    }
    if (conRif && CTX2.ref_data !== '') p['ref_data'] = CTX2.ref_data;
    return p;
}

function applicaCustom() {
    var campo = document.getElementById('f-campo').value;
    var op    = document.getElementById('f-op').value;
    var val   = document.getElementById('f-val').value;
    if (val === '' || isNaN(parseFloat(val))) { alert('Inserire un valore numerico'); return; }
    var f = parseFloat(val);
    if (f < -30 || f > 50) { alert('Valore fuori range (-30 / +50)'); return; }
    var params = paramsCorrenti(false, true);
    params['custom_campo'] = campo;
    params['custom_op']    = op;
    params['custom_val']   = val;
    document.getElementById('custom-popup').classList.remove('open');
    window.location.href = buildUrl(params);
}

function resetCustom()  { window.location.href = buildUrl(paramsCorrenti(false, true)); }
function resetRefData() { window.location.href = buildUrl(paramsCorrenti(true, false)); }

// ---- toggle popup personalizza ----
document.getElementById('custom-trigger').addEventListener('click', function(e) {
    e.stopPropagation();
    document.querySelectorAll('.cal-popup.open').forEach(function(p){ p.classList.remove('open'); });
    var popup = document.getElementById('custom-popup');
    var opening = !popup.classList.contains('open');
    popup.classList.toggle('open', opening);
    if (opening) {
        var r = this.getBoundingClientRect();
        popup.style.top   = (r.bottom + 4) + 'px';
        popup.style.right = '5px';
        popup.style.left  = 'auto';
        setTimeout(function(){ document.getElementById('f-val').focus(); }, 80);
    }
});

document.getElementById('f-val').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') applicaCustom();
});

// ---- click: apertura calendario / chiusura popup ----
document.addEventListener('click', function(e) {
    if (!e.target.closest('#custom-popup') && !e.target.closest('#custom-trigger'))
        document.getElementById('custom-popup').classList.remove('open');

    var icon = e.target.closest('.cal-icon');
    if (icon) {
        e.stopPropagation();
        var id = icon.dataset.cal;
        var popup = document.getElementById(id);
        if (!popup) return;
        var isOpen = popup.classList.contains('open');
        document.querySelectorAll('.cal-popup.open').forEach(function(p){ p.classList.remove('open'); });
        if (isOpen) return;
        if (!CAL2_STATE[id]) {
            var r0 = parseDate2(CTX2.ref_data !== '' ? CTX2.ref_data : CTX2.ieri);
            CAL2_STATE[id] = { y: r0.y, m: r0.m, sel: CTX2.ref_data };
        }
        renderCal2(id);
        var r = icon.getBoundingClientRect();
        popup.style.visibility = 'hidden';
        popup.style.display = 'block';
        var pw = popup.offsetWidth;
        popup.style.display = '';
        popup.style.visibility = '';
        popup.style.left = Math.max(4, Math.round((window.innerWidth - pw) / 2)) + 'px';
        popup.style.top  = (r.bottom + 4) + 'px';
        popup.classList.add('open');
    } else if (!e.target.closest('.cal-popup')) {
        document.querySelectorAll('.cal-popup.open').forEach(function(p){ p.classList.remove('open'); });
    }
});

function renderCal2(id) {
    var popup = document.getElementById(id);
    var st    = CAL2_STATE[id];
    var lim   = parseDate2(CTX2.ieri);
    var limD  = new Date(lim.y, lim.m - 1, lim.d);
    var y = st.y, m = st.m;
    var primo = (new Date(y, m - 1, 1).getDay() + 6) % 7;
    var ngg   = daysInMonth2(y, m);
    var mesi_long = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                     'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
    var html = '<div class="cal-nav">'
             + '<span class="cal-nav-btn" data-cal2="' + id + '" data-nav2="-1">&#8249;</span>'
             + '<span class="cal-nav-year">' + mesi_long[m] + ' ' + y + '</span>'
             + '<span class="cal-nav-btn" data-cal2="' + id + '" data-nav2="1">&#8250;</span>'
             + '</div><div class="cal-days-grid">';
    ['L','M','M','G','V','S','D'].forEach(function(g){ html += '<span class="cal-day-head">' + g + '</span>'; });
    for (var i = 0; i < primo; i++) html += '<span class="cal-day empty"></span>';
    for (var d = 1; d <= ngg; d++) {
        var ds = isoDate2(y, m, d);
        if (new Date(y, m - 1, d) > limD) {
            html += '<span class="cal-day empty" style="color:#ccc;">' + d + '</span>';
        } else {
            var cls = 'cal-day' + (ds === st.sel ? ' selected-day' : '') + (ds === CTX2.ieri ? ' today-day' : '');
            html += '<span class="' + cls + '" data-cal2="' + id + '" data-rif="' + ds + '">' + d + '</span>';
        }
    }
    html += '</div>';
    html += '<div style="margin-top:5px;font-size:9px;cursor:pointer;color:#6699cc;" data-cal2="' + id + '" data-rif="reset">&#8635; torna a oggi</div>';
    popup.innerHTML = html;
}

document.addEventListener('click', function(e) {
    var el = e.target;
    if (!el.dataset || !el.dataset.cal2) return;
    var id = el.dataset.cal2;
    var st = CAL2_STATE[id];
    if (!st) return;
    e.stopPropagation();
    if (el.dataset.nav2 !== undefined) {
        st.m += parseInt(el.dataset.nav2, 10);
        if (st.m > 12) { st.m = 1;  st.y++; }
        if (st.m < 1)  { st.m = 12; st.y--; }
        renderCal2(id);
        return;
    }
    if (el.dataset.rif !== undefined) {
        var params = paramsCorrenti(true, false);
        if (el.dataset.rif !== 'reset') params['ref_data'] = el.dataset.rif;
        window.location.href = buildUrl(params);
    }
});

// ---- pulsante grafico: apre grafico_stat2_display.php nel padre ----
document.getElementById('btn-grafico').addEventListener('click', function() {
    // Se c'e' una soglia custom attiva la usa, altrimenti default 30/35/5/0
    var s1 = 30, s2 = 35, s3 = 5, s4 = 0;
    if (CTX2.custom_val !== '') {
        var v = parseFloat(CTX2.custom_val);
        if (!isNaN(v)) {
            if (CTX2.custom_campo === 'temp_max_abs') { s1 = v; s2 = Math.max(v, 35); }
            else                                       { s3 = v; s4 = Math.min(v, 0); }
        }
    }
    var src = 'grafico_stat2_display.php?s1=' + s1 + '&s2=' + s2 + '&s3=' + s3 + '&s4=' + s4;
    window.parent.postMessage({
        action:   'mostraGrafico',
        iframeId: 'stat-iframe-tab2',
        src:      src
    }, '*');
});

// ---- comunica altezza reale al padre (per resize iframe) ----
function sendResize() {
    window.parent.postMessage({
        action:   'resize',
        iframeId: 'stat-iframe-tab2',
        height:   document.body.scrollHeight
    }, '*');
}
window.addEventListener('load', function() {
    sendResize();
    setTimeout(sendResize, 300);
});
</script>

</body>
</html>
