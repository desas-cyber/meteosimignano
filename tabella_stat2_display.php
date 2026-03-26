<?php
/**
 * ============================================================================
 * TABELLA SOGLIE TERMICHE - DISPLAY LAYER
 * ============================================================================
 * Struttura: 2 colonne (Parametro | Periodo selezionato)
 * Pensato per essere caricato come iframe in stat_display.php
 *
 * PARAMETRI GET passati direttamente a getStat2Data():
 *   ?anno=YYYY | ?mese=YYYY-MM
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
    <title>Soglie Termiche</title>
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
            width: 55%;
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
        /* icone */
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
        /* popup */
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
        /* responsive */
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
            <th>Parametro <span class="refresh-icon" onclick="window.location.href=window.location.pathname" title="Ripristina default">&#8635;</span></th>
            <th>
                <?= htmlspecialchars($meta["label_col"]) ?>
                <span class="cal-icon" data-cal="cal-periodo">&#128197;</span>
            </th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($righe as $riga): ?>
        <?php
            $cls = [];
            if ($riga['grigio']     ?? false) $cls[] = 'riga-grigia';
            if ($riga['separatore'] ?? false) $cls[] = 'riga-separatore';
            $cls_str = !empty($cls) ? 'class="' . implode(' ', $cls) . '"' : '';
            // Asterisco solo se la riga e' soggetta a copertura (sezione B)
            // e la copertura del periodo e' sotto soglia
            $cls_val = ($riga['scarso'] && $scarso) ? ' dato-scarso' : '';
        ?>
        <tr <?= $cls_str ?>>
            <td><?= $riga['label'] ?></td>
            <td class="col-val<?= $cls_val ?>"><?= $riga['valore'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Popup calendario fuori dalla tabella -->
<div id="cal-periodo" class="cal-popup"></div>

<div class="stat-footer">
    aggiornato: <?= htmlspecialchars($meta["generato_il"]) ?>
    <?php if ($scarso): ?>
    &nbsp;|&nbsp; <span style="color:#c00;">* dato con copertura &lt;75%</span>
    <?php endif; ?>
</div>

<script>
var CTX2 = {
    oggi_reale: '<?= $meta["oggi_reale"] ?>',
    modo:       '<?= $meta["modo"] ?>',
    anno_rif:   '<?= $meta["anno_rif"] ?>',
    inizio:     '<?= $meta["inizio"] ?>',
};

var MESI_IT2      = ['','gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];
var MESI_IT2_LONG = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                     'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

var CAL2_STATE = {};

function parseDate2(s) { var p=s.split('-'); return {y:+p[0],m:+p[1],d:+p[2]}; }
function zeroPad2(n)   { return n<10?'0'+n:''+n; }

function navigaA2(params) {
    var base  = window.location.pathname;
    var parts = [];
    for (var k in params) {
        if (params[k] !== null && params[k] !== undefined)
            parts.push(encodeURIComponent(k)+'='+encodeURIComponent(params[k]));
    }
    window.location.href = base + (parts.length ? '?'+parts.join('&') : '');
}

// ---- apertura popup ----
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
        if (!CAL2_STATE[id]) initCal2State(id);
        renderCal2(id);
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

function initCal2State(id) {
    var oggi = parseDate2(CTX2.oggi_reale);
    // Popup unico: mostra prima mesi dell'anno corrente, poi anni
    // Struttura: livello 'mese' se modo=mese, altrimenti 'anno+mese'
    var ref = parseDate2(CTX2.inizio);
    CAL2_STATE[id] = {
        livello: 'mese',   // 'mese' | 'anno'
        y: ref.y,
        selY: ref.y,
        selM: CTX2.modo === 'mese' ? ref.m : null
    };
}

function renderCal2(id) {
    var popup = document.getElementById(id);
    var st    = CAL2_STATE[id];
    var oggi  = parseDate2(CTX2.oggi_reale);
    var html  = '';

    if (st.livello === 'mese') {
        // Mesi dell'anno st.y
        html += '<div class="cal-nav">'
              + '<span class="cal-nav-btn" data-cal2="'+id+'" data-nav2="-1">&#8249;</span>'
              + '<span class="cal-nav-year" data-cal2="'+id+'" data-switch2="anno" style="cursor:pointer;text-decoration:underline;font-size:10px;">'+st.y+'</span>'
              + '<span class="cal-nav-btn" data-cal2="'+id+'" data-nav2="1">&#8250;</span>'
              + '</div><div class="cal-mesi-grid">';
        for (var m=1; m<=12; m++) {
            var futuro = (st.y > oggi.y) || (st.y === oggi.y && m > oggi.m);
            var sel    = (st.y === st.selY && m === st.selM);
            if (futuro) {
                html += '<span class="cal-btn" style="opacity:0.35;">'+MESI_IT2[m]+'</span>';
            } else {
                html += '<span class="cal-btn'+(sel?' selected':'')+'" data-cal2="'+id+'" data-sel2="mese:'+st.y+'-'+zeroPad2(m)+'">'+MESI_IT2[m]+'</span>';
            }
        }
        html += '</div>';
        // Link per vedere anni
        html += '<div style="margin-top:5px;font-size:9px;cursor:pointer;color:#6699cc;" data-cal2="'+id+'" data-switch2="anno">Seleziona anno &rarr;</div>';

    } else {
        // Lista anni
        html += '<div style="font-size:10px;margin-bottom:3px;">Seleziona anno</div>';
        html += '<div class="cal-mesi-grid">';
        for (var y=oggi.y; y>=2020; y--) {
            var sel = (y === st.selY && st.selM === null);
            html += '<span class="cal-btn'+(sel?' selected':'')+'" data-cal2="'+id+'" data-sel2="anno:'+y+'">'+y+'</span>';
        }
        html += '</div>';
        html += '<div style="margin-top:5px;font-size:9px;cursor:pointer;color:#6699cc;" data-cal2="'+id+'" data-switch2="mese">&#8592; Per mese</div>';
    }

    popup.innerHTML = html;
}

// ---- handler click dentro popup ----
document.addEventListener('click', function(e) {
    var el = e.target;
    if (!el.dataset || !el.dataset.cal2) return;
    var id = el.dataset.cal2;
    var st = CAL2_STATE[id];
    if (!st) return;
    e.stopPropagation();

    if (el.dataset.nav2 !== undefined) {
        st.y += parseInt(el.dataset.nav2);
        renderCal2(id);
        return;
    }
    if (el.dataset.switch2 !== undefined) {
        st.livello = el.dataset.switch2;
        renderCal2(id);
        return;
    }
    if (el.dataset.sel2 !== undefined) {
        var parts = el.dataset.sel2.split(':');
        if (parts[0] === 'mese') navigaA2({mese: parts[1]});
        else                     navigaA2({anno: parts[1]});
    }
});
</script>

</body>
</html>