<?php
/**
 * ============================================================================
 * TABELLA STATISTICHE PERIODICHE - DISPLAY LAYER
 * ============================================================================
 *
 * RESPONSABILITA':
 * - Include dati da api_tabella_stat_data.php
 * - Rendering HTML della tabella orizzontale (righe = metriche, colonne = periodi)
 * - Pensato per essere caricato come iframe in stat_display.php
 *
 * STRUTTURA COLONNE:
 *   | Parametro | ieri | 10 gg prima | mese | anno |
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../envelop.php';
require_once __DIR__ . '/api/api_tabella_stat_data.php';

$response = getStatData();

if (!$response['success']) {
    echo "<div style='font-family:Arial;font-size:13px;color:#c00;padding:10px;'>
            Errore: " . htmlspecialchars($response['error'] ?? 'Dati non disponibili') . "
          </div>";
    exit;
}

$headers   = $response['headers'];
$righe     = $response['righe'];
$meta      = $response['meta'];
$copertura = $response['copertura'] ?? ['oggi'=>1.0,'p10'=>1.0,'mese'=>1.0,'anno'=>1.0];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiche Periodiche</title>
    <style>
        /* ================================================================
           STILE IDENTICO A tabella_home_display.php
        ================================================================ */
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
            font-size: 11px;
        }

        tr { height: 3.1em; }

        th {
            background-color: rgba(173, 173, 173, 0.8);
            font-weight: bold;
            text-align: center;
        }

        /* Prima colonna: label parametro */
        th:first-child, td:first-child {
            text-align: left;
            width: 22%;
        }

        /* Separatori di sezione (identici all'originale) */
        .riga-separatore {
            border-bottom: 3px solid #666 !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            position: relative;
        }

        .riga-separatore td {
            border-bottom: 3px solid #666 !important;
            padding-bottom: 4px;
        }

        /* Riga date (intestazione dati) */
        tr.riga-data th,
        tr.riga-data td {
            background-color: rgba(173, 173, 173, 0.8);
            font-weight: bold;
            text-align: center;
        }

        tr.riga-data td:first-child {
            text-align: left;
        }

        /* Righe grigie: Max media, Min media, Gg pioggia */
        tr.riga-grigia td {
            background-color: rgba(200, 200, 200, 0.5);
            color: #444;
        }

        /* Colonna oggi: leggero highlight */
        td.col-oggi {
            background-color: rgba(240, 248, 255, 0.7);
        }

        /* Data accanto al valore: "(3mar)" */
        .stat-data-date {
            font-size: 9px;
            color: #777;
            white-space: nowrap;
        }

        /* ================================================================
           RESPONSIVE
        ================================================================ */
        @media (max-width: 480px) {
            table { font-size: 9px; }
            th, td { padding: 2px 1px; }
            .stat-data-date { font-size: 8px; }
            th:first-child, td:first-child { width: 26%; }
        }

        @media (min-width: 768px) {
            table {
                font-size: 16px;
                width: 85%;
                max-width: 75%;
            }
            th, td {
                padding: 5px 1px 5px 5px;
            }
            tr { height: auto; }
            .stat-data-date { font-size: 12px; }
        }

        .stat-footer {
            text-align: center;
            font-size: clamp(5px, 2vw, 9px);
            color: #aaa;
            margin-top: 4px;
            white-space: nowrap;
        }

        /* ---- pulsante grafico ---- */
        .top-bar-stat1 {
            display: flex;
            align-items: stretch;
            justify-content: flex-end;
            max-width: 98%;
            margin: 4px auto 4px auto;
            gap: 6px;
        }
        .btn-grafico-stat1 {
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
            transition: all 0.2s ease;
        }
        .btn-grafico-stat1:hover { border-color: #3366cc; color: #3366cc; }
        @media (min-width: 768px) {
            .btn-grafico-stat1 { font-size: 13px; }
        }

        /* ================================================================
           ASTERISCO COPERTURA DATI
        ================================================================ */
        .dato-scarso::after {
            content: '*';
            color: #c00;
            font-size: 0.85em;
            vertical-align: super;
            margin-left: 1px;
        }

        /* ================================================================
           CALENDARIETTO INTESTAZIONE
        ================================================================ */
        .col-header {
            position: relative;
            cursor: default;
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

        /* Popup calendario - posizionato via JS con coordinate fixed */
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

        /* Griglia mesi */
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
            transition: background 0.15s;
        }
        .cal-btn:hover { background: #dde8f5; border-color: #6699cc; }
        .cal-btn.selected { background: #6699cc; color: #fff; border-color: #4477aa; }

        /* Navigazione anno */
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
            border: none;
            background: none;
            color: #555;
            line-height: 1;
        }
        .cal-nav-btn:hover { color: #000; }
        .cal-nav-year { font-weight: bold; min-width: 36px; }

        /* Mini-calendario giorno */
        .cal-days-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            margin-top: 3px;
            font-size: 9.5px;
        }
        .cal-day-head {
            font-weight: bold;
            color: #888;
            text-align: center;
        }
        .cal-day {
            text-align: center;
            padding: 2px 1px;
            cursor: pointer;
            border-radius: 2px;
        }
        .cal-day:hover { background: #dde8f5; }
        .cal-day.empty { cursor: default; }
        .cal-day.today-day { font-weight: bold; color: #c00; }
        .cal-day.selected-day { background: #6699cc; color: #fff; border-radius: 2px; }

        /* ================================================================
           RESPONSIVE
        ================================================================ */
        @media (max-width: 480px) {
            table { font-size: 9px; }
            th, td { padding: 2px 1px; }
            .stat-data-date { font-size: 8px; }
            th:first-child, td:first-child { width: 26%; }
        }

        @media (min-width: 768px) {
            table {
                font-size: 16px;
                width: 85%;
                max-width: 75%;
            }
            th, td {
                padding: 5px 1px 5px 5px;
            }
            tr { height: auto; }
            .stat-data-date { font-size: 12px; }
        }
    </style>
</head>
<body>

<?php $soglia = 0.75; ?>
<table border='1' cellpadding='10' cellspacing='0'>

    <!-- INTESTAZIONE COLONNE -->
    <!-- I popup stanno fuori dalla tabella: div dentro th = HTML non valido -->
    <thead>
        <tr>
            <th>Parametro <span class="refresh-icon" onclick="window.location.href=window.location.pathname" title="Ripristina dati di default">&#8635;</span></th>
            <th><span>ieri</span><?php if($copertura['oggi']<$soglia):?><span style="color:#c00;font-size:0.85em;vertical-align:super;">*</span><?php endif;?> <span class="cal-icon" data-cal="cal-oggi">&#128197;</span></th>
            <th><span>10 gg</span><?php if($copertura['p10']<$soglia):?><span style="color:#c00;font-size:0.85em;vertical-align:super;">*</span><?php endif;?> <span class="cal-icon" data-cal="cal-p10">&#128197;</span></th>
            <th><span>mese</span><?php if($copertura['mese']<$soglia):?><span style="color:#c00;font-size:0.85em;vertical-align:super;">*</span><?php endif;?> <span class="cal-icon" data-cal="cal-mese">&#128197;</span></th>
            <th><span>anno</span><?php if($copertura['anno']<$soglia):?><span style="color:#c00;font-size:0.85em;vertical-align:super;">*</span><?php endif;?> <span class="cal-icon" data-cal="cal-anno">&#128197;</span></th>
        </tr>
    </thead>

    <!-- CORPO TABELLA -->
    <tbody>
    <?php
    $soglia = 0.75;
    $cls_cov = [
        'oggi' => ($copertura['oggi'] < $soglia) ? ' dato-scarso' : '',
        'p10'  => ($copertura['p10']  < $soglia) ? ' dato-scarso' : '',
        'mese' => ($copertura['mese'] < $soglia) ? ' dato-scarso' : '',
        'anno' => ($copertura['anno'] < $soglia) ? ' dato-scarso' : '',
    ];
    foreach ($righe as $riga):
        $cls = [];
        if ($riga['grigio']     ?? false) $cls[] = 'riga-grigia';
        if ($riga['separatore'] ?? false) $cls[] = 'riga-separatore';
        if (($riga['label'] ?? '') === 'Data') $cls[] = 'riga-data';
        $cls_str = !empty($cls) ? 'class="' . implode(' ', $cls) . '"' : '';
        // Per la riga Data non si applica dato-scarso
        $is_data_row = (($riga['label'] ?? '') === 'Data');
    ?>
        <tr <?= $cls_str ?>>
            <td><?= $riga['label'] ?></td>
            <td class="col-oggi"><?= $riga['oggi'] ?></td>
            <td><?= $riga['p10'] ?></td>
            <td><?= $riga['mese'] ?></td>
            <td><?= $riga['anno'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>

</table>

<!-- POPUP CALENDARIO - fuori dalla tabella per HTML valido -->
<div id="cal-oggi" class="cal-popup"></div>
<div id="cal-p10"  class="cal-popup"></div>
<div id="cal-mese" class="cal-popup"></div>
<div id="cal-anno" class="cal-popup"></div>

<div class="stat-footer">
    aggiornato: <?= htmlspecialchars($meta["generato_il"]) ?>
    <?php if (min($copertura) < 0.75): ?>
    &nbsp;|&nbsp; <span style="color:#c00;">* dato con copertura &lt;75%</span>
    <?php endif; ?>
</div>

<script>
var CTX = {
    oggi:       '<?= $meta["oggi"] ?>',
    oggi_reale: '<?= $meta["oggi_reale"] ?>',
    p10_fine:   '<?= $meta["p10_fine"] ?>',
    mese_fine:  '<?= $meta["mese_fine"] ?>',
    anno_fine:  '<?= $meta["anno_fine"] ?>',
};

var MESI_IT      = ['','gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];
var MESI_IT_LONG = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                    'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];

var CAL_STATE = {}; // stato popup indicizzato per id

// ---- helper date ----
function parseDate(s) { var p=s.split('-'); return {y:+p[0],m:+p[1],d:+p[2]}; }
function zeroPad(n)   { return n<10?'0'+n:''+n; }
function isoDate(y,m,d){ return y+'-'+zeroPad(m)+'-'+zeroPad(d); }
function daysInMonth(y,m){ return new Date(y,m,0).getDate(); }

// ---- navigazione: ricarica iframe con nuovi params ----
function navigaA(params) {
    var base  = window.location.pathname;
    var parts = [];
    for (var k in params) {
        if (params[k] !== null && params[k] !== undefined)
            parts.push(encodeURIComponent(k)+'='+encodeURIComponent(params[k]));
    }
    window.location.href = base + (parts.length ? '?'+parts.join('&') : '');
}

// ---- apri/chiudi popup ----
function apriCal(id, anchorEl) {
    var popup = document.getElementById(id);
    if (!popup) return;

    var isOpen = popup.classList.contains('open');
    // chiudi tutti
    document.querySelectorAll('.cal-popup.open').forEach(function(p){ p.classList.remove('open'); });
    if (isOpen) return;

    // inizializza stato se prima volta
    if (!CAL_STATE[id]) initCalState(id);
    renderCal(id);

    // posiziona centrato orizzontalmente nella viewport
    var r = anchorEl.getBoundingClientRect();
    popup.style.visibility = 'hidden';
    popup.style.display = 'block';
    var pw = popup.offsetWidth;
    popup.style.display = '';
    popup.style.visibility = '';
    var left = Math.max(4, Math.round((window.innerWidth - pw) / 2));
    popup.style.left = left + 'px';
    popup.style.top  = (r.bottom + 4) + 'px';
    popup.classList.add('open');
}

document.addEventListener('click', function(e) {
    var icon = e.target.closest('.cal-icon');
    if (icon) {
        e.stopPropagation();
        apriCal(icon.dataset.cal, icon);
        return;
    }
    // click fuori dai popup: chiudi
    if (!e.target.closest('.cal-popup')) {
        document.querySelectorAll('.cal-popup.open').forEach(function(p){ p.classList.remove('open'); });
    }
});

// ---- inizializza stato ----
function initCalState(id) {
    if (id === 'cal-oggi') {
        var r = parseDate(CTX.oggi);
        CAL_STATE[id] = {tipo:'giorno', param:'data',       y:r.y, m:r.m, sel:CTX.oggi};
    } else if (id === 'cal-p10') {
        var r = parseDate(CTX.p10_fine);
        CAL_STATE[id] = {tipo:'giorno', param:'p10_centro', y:r.y, m:r.m, sel:CTX.p10_fine};
    } else if (id === 'cal-mese') {
        var r = parseDate(CTX.mese_fine);
        CAL_STATE[id] = {tipo:'mese', y:r.y, selY:r.y, selM:r.m};
    } else if (id === 'cal-anno') {
        var r = parseDate(CTX.anno_fine);
        CAL_STATE[id] = {tipo:'anno', selY:r.y};
    }
}

// ---- render ----
function renderCal(id) {
    var popup = document.getElementById(id);
    var st    = CAL_STATE[id];
    var oggi  = parseDate(CTX.oggi_reale);
    var html  = '';

    if (st.tipo === 'giorno') {
        var y=st.y, m=st.m;
        var primo = (new Date(y,m-1,1).getDay()+6)%7;
        var ngg   = daysInMonth(y,m);
        html += '<div class="cal-nav">'
              + '<span class="cal-nav-btn" data-cal="'+id+'" data-nav="-1">&#8249;</span>'
              + '<span class="cal-nav-year">'+MESI_IT_LONG[m]+' '+y+'</span>'
              + '<span class="cal-nav-btn" data-cal="'+id+'" data-nav="1">&#8250;</span>'
              + '</div><div class="cal-days-grid">';
        ['L','M','M','G','V','S','D'].forEach(function(g){ html+='<span class="cal-day-head">'+g+'</span>'; });
        for (var i=0;i<primo;i++) html+='<span class="cal-day empty"></span>';
        for (var d=1;d<=ngg;d++) {
            var ds = isoDate(y,m,d);
            var futuro = new Date(y,m-1,d) > new Date(oggi.y,oggi.m-1,oggi.d);
            if (futuro) {
                html+='<span class="cal-day empty" style="color:#ccc;">'+d+'</span>';
            } else {
                var cls='cal-day'+(ds===st.sel?' selected-day':'')+(y===oggi.y&&m===oggi.m&&d===oggi.d?' today-day':'');
                html+='<span class="'+cls+'" data-cal="'+id+'" data-sel="'+ds+'">'+d+'</span>';
            }
        }
        html += '</div>';

    } else if (st.tipo === 'mese') {
        var y = st.y;
        html += '<div class="cal-nav">'
              + '<span class="cal-nav-btn" data-cal="'+id+'" data-nav="-1">&#8249;</span>'
              + '<span class="cal-nav-year">'+y+'</span>'
              + '<span class="cal-nav-btn" data-cal="'+id+'" data-nav="1">&#8250;</span>'
              + '</div><div class="cal-mesi-grid">';
        for (var m=1;m<=12;m++) {
            var futuro = (y>oggi.y)||(y===oggi.y&&m>oggi.m);
            var sel    = (y===st.selY&&m===st.selM);
            if (futuro) html+='<span class="cal-btn" style="opacity:0.35;">'+MESI_IT[m]+'</span>';
            else        html+='<span class="cal-btn'+(sel?' selected':'')+'" data-cal="'+id+'" data-sel="'+y+'-'+zeroPad(m)+'">'+MESI_IT[m]+'</span>';
        }
        html += '</div>';

    } else if (st.tipo === 'anno') {
        html += '<div style="font-size:10px;margin-bottom:3px;">Seleziona anno</div><div class="cal-mesi-grid">';
        for (var y=oggi.y;y>=2023;y--) {
            html+='<span class="cal-btn'+(y===st.selY?' selected':'')+'" data-cal="'+id+'" data-sel="'+y+'">'+y+'</span>';
        }
        html += '</div>';
    }

    popup.innerHTML = html;
}

// ---- handler click dentro popup (event delegation) ----
document.addEventListener('click', function(e) {
    var el = e.target;
    if (!el.dataset || !el.dataset.cal) return;
    var id = el.dataset.cal;
    var st = CAL_STATE[id];
    if (!st) return;

    e.stopPropagation();

    if (el.dataset.nav !== undefined) {
        var delta = parseInt(el.dataset.nav);
        if (st.tipo==='giorno') {
            st.m += delta;
            if (st.m>12){st.m=1;st.y++;} else if(st.m<1){st.m=12;st.y--;}
        } else if (st.tipo==='mese') {
            st.y += delta;
        }
        renderCal(id);
        return;
    }

    if (el.dataset.sel !== undefined) {
        var val = el.dataset.sel;
        if      (st.tipo==='giorno' && st.param==='data')        navigaA({data:val});
        else if (st.tipo==='giorno' && st.param==='p10_centro')  navigaA({p10_centro:val});
        else if (st.tipo==='mese')  navigaA({mese:val});
        else if (st.tipo==='anno')  navigaA({anno:val});
    }
});

// ---- resize dinamico iframe ----
function sendResize() {
    window.parent.postMessage({
        action:   'resize',
        iframeId: 'stat-iframe-tab1',
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