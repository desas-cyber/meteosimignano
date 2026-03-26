<?php
/**
 * ============================================================================
 * TABELLA RECORD PIOGGIA - DISPLAY LAYER
 * ============================================================================
 * Struttura: 5 colonne (Parametro | 1h | 6h | 12h | 24h)
 * Pensato per essere caricato come iframe in stat_display.php
 *
 * PARAMETRI GET passati direttamente a getStat3Data():
 *   ?mese=YYYY-MM | ?anno=YYYY
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../envelop.php';
require_once __DIR__ . '/api/api_tabella_stat_data.php';

$response = getStat3Data();

if (!$response['success']) {
    echo "<div style='font-family:Arial;font-size:13px;color:#c00;padding:10px;'>
            Errore: " . htmlspecialchars($response['error'] ?? 'Dati non disponibili') . "
          </div>";
    exit;
}

$righe     = $response['righe'];
$meta      = $response['meta'];
$copertura = $response['copertura'] ?? 1.0;
$scarso    = ($copertura < 0.75);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Pioggia</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 4px 0 8px 0;
            background: #fff;
        }
        table {
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 11px;
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
        }
        tr { height: 3.1em; }
        th {
            background-color: rgba(173, 173, 173, 0.8);
            font-weight: bold;
            text-align: center;
        }
        th:first-child, td:first-child {
            text-align: left;
            width: 28%;
        }
        .riga-separatore td {
            border-bottom: 3px solid #666 !important;
            padding-bottom: 4px;
        }
        tr.riga-grigia td {
            background-color: rgba(200, 200, 200, 0.5);
            color: #444;
        }
        td.col-val { text-align: center; }
        .stat-data-date {
            font-size: 9px;
            color: #777;
            white-space: nowrap;
        }
        .dato-scarso::after {
            content: '*';
            color: #c00;
            font-size: 0.85em;
            vertical-align: super;
            margin-left: 1px;
        }
        .stat-footer {
            text-align: center;
            font-size: clamp(5px, 2vw, 9px);
            color: #aaa;
            margin-top: 4px;
            white-space: nowrap;
        }
        .cal-icon {
            display: inline-block;
            font-size: 0.85em;
            cursor: pointer;
            opacity: 0.65;
            vertical-align: middle;
            margin-left: 2px;
            line-height: 1;
            user-select: none;
        }
        .cal-icon:hover { opacity: 1; }
        .refresh-icon {
            display: inline-block;
            font-size: 1em;
            cursor: pointer;
            opacity: 0.5;
            vertical-align: middle;
            margin-left: 3px;
            line-height: 1;
            user-select: none;
        }
        .refresh-icon:hover { opacity: 1; color: #3366cc; }
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
            font-weight: normal;
            color: #333;
            white-space: nowrap;
        }
        .cal-popup.open { display: block; }
        .cal-mesi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 3px;
            margin-top: 4px;
        }
        .cal-btn {
            padding: 3px 2px;
            cursor: pointer;
            border: 1px solid #ccc;
            border-radius: 3px;
            background: #f5f5f5;
            font-size: 10px;
            text-align: center;
        }
        .cal-btn:hover { background: #dde8f5; border-color: #6699cc; }
        .cal-btn.selected { background: #6699cc; color: #fff; border-color: #4477aa; }
        .cal-nav {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-bottom: 4px;
        }
        .cal-nav-btn {
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
            padding: 0 4px;
            color: #555;
            user-select: none;
        }
        .cal-nav-btn:hover { color: #000; }
        .cal-nav-year { font-weight: bold; min-width: 36px; }
        @media (max-width: 480px) {
            table { font-size: 9px; }
            th, td { padding: 2px 1px; }
            .stat-data-date { font-size: 8px; }
        }
        @media (min-width: 768px) {
            table { font-size: 16px; width: 85%; max-width: 75%; }
            th, td { padding: 5px 1px 5px 5px; }
            tr { height: auto; }
            .stat-data-date { font-size: 12px; }
        }
    </style>
</head>
<body>

<table border='1' cellpadding='10' cellspacing='0'>
    <thead>
        <tr>
            <th>
                <span class="cal-icon" data-cal="cal-pioggia">&#128197;</span>
                <small style="font-size:9px;font-weight:normal;"><?= htmlspecialchars($meta["label_col"]) ?></small>
                <span class="refresh-icon" onclick="window.location.href=window.location.pathname" title="Ripristina default">&#8635;</span>
            </th>
            <th>1h</th>
            <th>6h</th>
            <th>12h</th>
            <th>24h</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($righe as $riga): ?>
        <?php
            $cls = [];
            if ($riga['grigio']     ?? false) $cls[] = 'riga-grigia';
            if ($riga['separatore'] ?? false) $cls[] = 'riga-separatore';
            $cls_str = !empty($cls) ? 'class="' . implode(' ', $cls) . '"' : '';
        ?>
        <tr <?= $cls_str ?>>
            <td><?= $riga['label'] ?><?php if($riga['scarso'] ?? false):?><span style="color:#c00;font-size:0.85em;vertical-align:super;">*</span><?php endif;?></td>
            <td class="col-val"><?= $riga['c1h'] ?></td>
            <td class="col-val"><?= $riga['c6h'] ?></td>
            <td class="col-val"><?= $riga['c12h'] ?></td>
            <td class="col-val"><?= $riga['c24h'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Popup calendario fuori dalla tabella -->
<div id="cal-pioggia" class="cal-popup"></div>

<div class="stat-footer">
    aggiornato: <?= htmlspecialchars($meta["generato_il"]) ?>
    <?php if ($scarso): ?>
    &nbsp;|&nbsp; <span style="color:#c00;">* dato con copertura &lt;75%</span>
    <?php endif; ?>
</div>

<script>
var CTX3 = {
    oggi_reale: '<?= $meta["oggi_reale"] ?>',
    modo:       '<?= $meta["modo"] ?>',
    anno_rif:   '<?= $meta["anno_rif"] ?>',
    inizio:     '<?= $meta["inizio"] ?>',
};

var MESI_IT3 = ['','gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];
var CAL3_STATE = {};

function parseDate3(s) { var p=s.split('-'); return {y:+p[0],m:+p[1],d:+p[2]}; }
function zeroPad3(n)   { return n<10?'0'+n:''+n; }

function navigaA3(params) {
    var base  = window.location.pathname;
    var parts = [];
    for (var k in params) {
        if (params[k] !== null && params[k] !== undefined)
            parts.push(encodeURIComponent(k)+'='+encodeURIComponent(params[k]));
    }
    window.location.href = base + (parts.length ? '?'+parts.join('&') : '');
}

document.addEventListener('click', function(e) {
    var icon = e.target.closest('.cal-icon');
    if (icon) {
        e.stopPropagation();
        var id = icon.dataset.cal;
        var popup = document.getElementById(id);
        if (!popup) return;
        var isOpen = popup.classList.contains('open');
        document.querySelectorAll('.cal-popup.open').forEach(function(p){ p.classList.remove('open'); });
        if (isOpen) return;
        if (!CAL3_STATE[id]) initCal3State(id);
        renderCal3(id);
        var r = icon.getBoundingClientRect();
        popup.style.visibility = 'hidden';
        popup.style.display = 'block';
        var pw = popup.offsetWidth;
        popup.style.display = '';
        popup.style.visibility = '';
        var left = Math.max(4, Math.round((window.innerWidth - pw) / 2));
        popup.style.left = left + 'px';
        popup.style.top  = (r.bottom + 4) + 'px';
        popup.classList.add('open');
        return;
    }
    if (!e.target.closest('.cal-popup')) {
        document.querySelectorAll('.cal-popup.open').forEach(function(p){ p.classList.remove('open'); });
    }
});

function initCal3State(id) {
    var ref = parseDate3(CTX3.inizio);
    CAL3_STATE[id] = {
        livello: 'mese',
        y: ref.y,
        selY: ref.y,
        selM: CTX3.modo === 'mese' ? ref.m : null
    };
}

function renderCal3(id) {
    var popup = document.getElementById(id);
    var st    = CAL3_STATE[id];
    var oggi  = parseDate3(CTX3.oggi_reale);
    var html  = '';

    // Calendario sempre a mese: navigazione anno + griglia 12 mesi
    html += '<div class="cal-nav">'
          + '<span class="cal-nav-btn" data-cal3="'+id+'" data-nav3="-1">&#8249;</span>'
          + '<span class="cal-nav-year">'+st.y+'</span>'
          + '<span class="cal-nav-btn" data-cal3="'+id+'" data-nav3="1">&#8250;</span>'
          + '</div><div class="cal-mesi-grid">';
    for (var m=1; m<=12; m++) {
        var futuro = (st.y > oggi.y) || (st.y === oggi.y && m > oggi.m);
        var sel    = (st.y === st.selY && m === st.selM);
        if (futuro) {
            html += '<span class="cal-btn" style="opacity:0.35;">'+MESI_IT3[m]+'</span>';
        } else {
            html += '<span class="cal-btn'+(sel?' selected':'')+'" data-cal3="'+id+'" data-sel3="mese:'+st.y+'-'+zeroPad3(m)+'">'+MESI_IT3[m]+'</span>';
        }
    }
    html += '</div>';

    popup.innerHTML = html;
}

document.addEventListener('click', function(e) {
    var el = e.target;
    if (!el.dataset || !el.dataset.cal3) return;
    var id = el.dataset.cal3;
    var st = CAL3_STATE[id];
    if (!st) return;
    e.stopPropagation();

    if (el.dataset.nav3 !== undefined) {
        st.y += parseInt(el.dataset.nav3);
        renderCal3(id);
        return;
    }
    if (el.dataset.switch3 !== undefined) {
        st.livello = el.dataset.switch3;
        renderCal3(id);
        return;
    }
    if (el.dataset.sel3 !== undefined) {
        var parts = el.dataset.sel3.split(':');
        if (parts[0] === 'mese') navigaA3({mese: parts[1]});
        else                     navigaA3({anno: parts[1]});
    }
});
</script>

</body>
</html>