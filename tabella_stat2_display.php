<?php
/**
 * ============================================================================
 * TABELLA SOGLIE TERMICHE - DISPLAY LAYER
 * ============================================================================
 * Struttura: 2 sezioni
 *   A) Primo/Ultimo giorno nella finestra stagionale (3 colonne)
 *   B) Conteggio giorni nel periodo selezionato (2 colonne)
 *
 * PARAMETRI GET:
 *   ?anno=YYYY | ?mese=YYYY-MM
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

$righe_a     = $response['righe_a'];
$righe_b     = $response['righe_b'];
$meta        = $response['meta'];
$copertura   = $response['copertura'] ?? 1.0;
$scarso      = ($copertura < 0.75);

$cur_campo     = isset($_GET['custom_campo']) ? $_GET['custom_campo'] : 'temp_min_abs';
$cur_op        = isset($_GET['custom_op'])    ? $_GET['custom_op']    : '<=';
$cur_val       = isset($_GET['custom_val'])   ? $_GET['custom_val']   : '';
$custom_attivo = ($cur_val !== '');
$ref_data      = isset($_GET['ref_data']) ? $_GET['ref_data'] : $meta['oggi_reale'];
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
            align-items: center;
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

        /* ---- titoli sezione ---- */
        .sez-title {
            font-size: 10px;
            font-weight: bold;
            color: #555;
            text-align: center;
            margin: 8px auto 2px auto;
            max-width: 95%;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        /* ---- tabelle ---- */
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
            background-color: rgba(173,173,173,0.8);
            font-weight: bold;
            text-align: center;
        }
        th:first-child, td:first-child { text-align: left; }

        table.tab-a th:first-child,
        table.tab-a td:first-child { width: 38%; }
        table.tab-a th:nth-child(2),
        table.tab-a td:nth-child(2),
        table.tab-a th:nth-child(3),
        table.tab-a td:nth-child(3) { width: 31%; text-align: center; }

        table.tab-b th:first-child,
        table.tab-b td:first-child { width: 75%; }
        table.tab-b th:nth-child(2),
        table.tab-b td:nth-child(2) { width: 25%; text-align: center; }

        tr.riga-grigia td { background-color: rgba(200,200,200,0.5); color: #444; }
        tr.riga-custom td { background-color: rgba(180,210,255,0.4); }
        td.col-val { text-align: center; }

        .dato-scarso::after {
            content: '*';
            color: #c00;
            font-size: 0.85em;
            vertical-align: super;
            margin-left: 1px;
        }

        /* ---- calendario giorno (griglia) ---- */
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
        .cal-day.selected-day { background: #6699cc; color: #fff; border-radius: 2px; }
        .cal-day.today-day { font-weight: bold; color: #c00; }

        /* ---- popup calendario ---- */
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
        .cal-mesi-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 3px; margin-top: 4px; }
        .cal-btn {
            padding: 3px 2px; cursor: pointer;
            border: 1px solid #ccc; border-radius: 3px;
            background: #f5f5f5; font-size: 10px; text-align: center;
        }
        .cal-btn:hover { background: #dde8f5; border-color: #6699cc; }
        .cal-btn.selected { background: #6699cc; color: #fff; border-color: #4477aa; }
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
        @media (max-width: 480px) {
            table { font-size: 9px; }
            th, td { padding: 2px 1px; }
            .stat-data-date { font-size: 8px; }
        }
        @media (min-width: 768px) {
            table { font-size: 16px; width: 85%; max-width: 75%; }
            th, td { padding: 5px 1px 5px 5px; }
            tr { height: auto; }
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
     SEZIONE A: Primo e Ultimo giorno
     ============================================================ -->
<div class="sez-title">
    Primo e ultimo giorno stagionale
    <span class="cal-icon" data-cal="cal-rif" title="Seleziona data di riferimento">&#128197;</span><?php if ($ref_data !== $meta['oggi_reale']): ?><span class="refresh-icon" onclick="resetRefData()" title="Torna a oggi" style="opacity:0.7;font-size:0.9em;">&#8635;</span><span style="font-size:9px;color:#6699cc;font-weight:normal;">(al <?= htmlspecialchars($ref_data) ?>)</span><?php endif; ?>
</div>
<table class="tab-a" border="1" cellpadding="10" cellspacing="0">
    <thead>
        <tr>
            <th>Soglia</th>
            <th>Primo giorno</th>
            <th>Ultimo giorno</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($righe_a as $i => $riga): ?>
        <?php
            $is_custom = ($i === count($righe_a) - 1);
            if ($is_custom && !$custom_attivo) continue;
            $cls = $is_custom ? 'riga-custom' : (($riga['grigio'] ?? false) ? 'riga-grigia' : '');
        ?>
        <tr<?= $cls ? ' class="'.$cls.'"' : '' ?>>
            <td><?= $riga['label'] ?></td>
            <td class="col-val"><?= $riga['primo'] ?></td>
            <td class="col-val"><?= $riga['ultimo'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- ============================================================
     SEZIONE B: Conteggio giorni nel periodo
     ============================================================ -->
<div class="sez-title" style="margin-top:12px;">
    Giorni nel periodo &mdash; <?= htmlspecialchars($meta['label_col']) ?>
    <span class="cal-icon" data-cal="cal-periodo">&#128197;</span><span class="refresh-icon" onclick="navigaA2({})" title="Ripristina periodo">&#8635;</span>
</div>
<table class="tab-b" border="1" cellpadding="10" cellspacing="0">
    <thead>
        <tr>
            <th>Soglia</th>
            <th>N&#176; giorni</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($righe_b as $i => $riga): ?>
        <?php
            $is_custom = ($i === count($righe_b) - 1);
            if ($is_custom && !$custom_attivo) continue;
            $cls_val = ($riga['scarso'] && $scarso) ? ' dato-scarso' : '';
            $cls_tr  = $is_custom ? 'riga-custom' : (($riga['grigio'] ?? false) ? 'riga-grigia' : '');
        ?>
        <tr<?= $cls_tr ? ' class="'.$cls_tr.'"' : '' ?>>
            <td><?= $riga['label'] ?></td>
            <td class="col-val<?= $cls_val ?>"><?= $riga['valore'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Popup calendari -->
<div id="cal-rif"     class="cal-popup"></div>
<div id="cal-periodo" class="cal-popup"></div>

<div class="stat-footer">
    aggiornato: <?= htmlspecialchars($meta['generato_il']) ?>
    <?php if ($scarso): ?>
    &nbsp;<span style="color:#c00;">* copertura &lt;75%</span>
    <?php endif; ?>
</div>

<script>
var CTX2 = {
    oggi_reale:   '<?= $meta['oggi_reale'] ?>',
    modo:         '<?= $meta['modo'] ?>',
    anno_rif:     '<?= $meta['anno_rif'] ?>',
    inizio:       '<?= $meta['inizio'] ?>',
    ref_data:     '<?= htmlspecialchars($ref_data) ?>',
    custom_campo: '<?= htmlspecialchars($cur_campo) ?>',
    custom_op:    '<?= htmlspecialchars($cur_op) ?>',
    custom_val:   '<?= htmlspecialchars($cur_val) ?>',
};

var MESI_IT2   = ['','gen','feb','mar','apr','mag','giu','lug','ago','set','ott','nov','dic'];
var CAL2_STATE = {};

function parseDate2(s) { var p=s.split('-'); return {y:+p[0],m:+p[1],d:+p[2]}; }
function daysInMonth2(y,m){ return new Date(y,m,0).getDate(); }
function isoDate2(y,m,d){ return y+'-'+(m<10?'0'+m:m)+'-'+(d<10?'0'+d:d); }
function zeroPad2(n)   { return n<10?'0'+n:''+n; }

function buildUrl(params) {
    var base = window.location.pathname;
    var parts = [];
    for (var k in params)
        if (params[k] !== null && params[k] !== undefined && params[k] !== '')
            parts.push(encodeURIComponent(k)+'='+encodeURIComponent(params[k]));
    return base + (parts.length ? '?'+parts.join('&') : '');
}

// Naviga preservando i parametri custom
function navigaA2(params) {
    var all = {};
    if (CTX2.custom_val !== '') {
        all['custom_campo'] = CTX2.custom_campo;
        all['custom_op']    = CTX2.custom_op;
        all['custom_val']   = CTX2.custom_val;
    }
    if (CTX2.ref_data !== CTX2.oggi_reale) {
        all['ref_data'] = CTX2.ref_data;
    }
    for (var k in params) all[k] = params[k];
    window.location.href = buildUrl(all);
}

function applicaCustom() {
    var campo = document.getElementById('f-campo').value;
    var op    = document.getElementById('f-op').value;
    var val   = document.getElementById('f-val').value;
    if (val === '' || isNaN(parseFloat(val))) { alert('Inserire un valore numerico'); return; }
    var f = parseFloat(val);
    if (f < -30 || f > 50) { alert('Valore fuori range (-30 / +50)'); return; }
    var params = { custom_campo: campo, custom_op: op, custom_val: val };
    if (CTX2.modo === 'mese') params['mese'] = CTX2.inizio.substring(0,7);
    else                      params['anno'] = CTX2.anno_rif;
    document.getElementById('custom-popup').classList.remove('open');
    window.location.href = buildUrl(params);
}

function resetCustom() {
    var params = {};
    if (CTX2.modo === 'mese') params['mese'] = CTX2.inizio.substring(0,7);
    else                      params['anno'] = CTX2.anno_rif;
    window.location.href = buildUrl(params);
}

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

// ---- click fuori chiude tutto ----
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
        if (!CAL2_STATE[id]) initCal2State(id);
        renderCal2(id);
        var r = icon.getBoundingClientRect();
        // posiziona centrato orizzontalmente nella viewport
        popup.style.visibility = 'hidden';
        popup.style.display = 'block';
        var pw = popup.offsetWidth;
        popup.style.display = '';
        popup.style.visibility = '';
        var left = Math.max(4, Math.round((window.innerWidth - pw) / 2));
        popup.style.left = left + 'px';
        popup.style.top  = (r.bottom + 4) + 'px';
        popup.classList.add('open');
    } else if (!e.target.closest('.cal-popup')) {
        document.querySelectorAll('.cal-popup.open').forEach(function(p){ p.classList.remove('open'); });
    }
});

function initCal2State(id) {
    if (id === 'cal-rif') {
        var r = parseDate2(CTX2.ref_data);
        CAL2_STATE[id] = { tipo:'giorno', y:r.y, m:r.m, sel:CTX2.ref_data };
    } else {
        var ref = parseDate2(CTX2.inizio);
        CAL2_STATE[id] = { livello:'mese', y:ref.y, selY:ref.y, selM: CTX2.modo==='mese' ? ref.m : null };
    }
}

function renderCal2(id) {
    var popup = document.getElementById(id);
    var st    = CAL2_STATE[id];
    var oggi  = parseDate2(CTX2.oggi_reale);
    var html  = '';

    if (st.tipo === 'giorno') {
        // Calendario giorno per cal-rif
        var y=st.y, m=st.m;
        var primo = (new Date(y,m-1,1).getDay()+6)%7;
        var ngg   = daysInMonth2(y,m);
        var mesi_long = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                         'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
        html += '<div class="cal-nav">'
              + '<span class="cal-nav-btn" data-cal2="'+id+'" data-nav2="mese:-1">&#8249;</span>'
              + '<span class="cal-nav-year">'+mesi_long[m]+' '+y+'</span>'
              + '<span class="cal-nav-btn" data-cal2="'+id+'" data-nav2="mese:1">&#8250;</span>'
              + '</div><div class="cal-days-grid">';
        ['L','M','M','G','V','S','D'].forEach(function(g){ html+='<span class="cal-day-head">'+g+'</span>'; });
        for (var i=0;i<primo;i++) html+='<span class="cal-day empty"></span>';
        for (var d=1;d<=ngg;d++) {
            var ds = isoDate2(y,m,d);
            var futuro = new Date(y,m-1,d) > new Date(oggi.y,oggi.m-1,oggi.d);
            if (futuro) {
                html += '<span class="cal-day empty" style="color:#ccc;">'+d+'</span>';
            } else {
                var cls = 'cal-day'+(ds===st.sel?' selected-day':'')+(y===oggi.y&&m===oggi.m&&d===oggi.d?' today-day':'');
                html += '<span class="'+cls+'" data-cal2="'+id+'" data-rif="'+ds+'">'+d+'</span>';
            }
        }
        html += '</div>';
        // Link reset a oggi
        html += '<div style="margin-top:5px;font-size:9px;cursor:pointer;color:#6699cc;text-align:center;" data-cal2="'+id+'" data-rif="reset">&#8635; oggi</div>';

    } else if (st.livello === 'mese') {
        html += '<div class="cal-nav">'
              + '<span class="cal-nav-btn" data-cal2="'+id+'" data-nav2="-1">&#8249;</span>'
              + '<span class="cal-nav-year" data-cal2="'+id+'" data-switch2="anno" style="cursor:pointer;text-decoration:underline;font-size:10px;">'+st.y+'</span>'
              + '<span class="cal-nav-btn" data-cal2="'+id+'" data-nav2="1">&#8250;</span>'
              + '</div><div class="cal-mesi-grid">';
        for (var m=1; m<=12; m++) {
            var futuro = (st.y > oggi.y) || (st.y === oggi.y && m > oggi.m);
            var sel    = (st.y === st.selY && m === st.selM);
            html += futuro
                ? '<span class="cal-btn" style="opacity:0.35;">'+MESI_IT2[m]+'</span>'
                : '<span class="cal-btn'+(sel?' selected':'')+'" data-cal2="'+id+'" data-sel2="mese:'+st.y+'-'+zeroPad2(m)+'">'+MESI_IT2[m]+'</span>';
        }
        html += '</div><div style="margin-top:5px;font-size:9px;cursor:pointer;color:#6699cc;" data-cal2="'+id+'" data-switch2="anno">Seleziona anno &rarr;</div>';
    } else {
        html += '<div style="font-size:10px;margin-bottom:3px;">Seleziona anno</div><div class="cal-mesi-grid">';
        for (var y=oggi.y; y>=2020; y--) {
            var sel = (y === st.selY && st.selM === null);
            html += '<span class="cal-btn'+(sel?' selected':'')+'" data-cal2="'+id+'" data-sel2="anno:'+y+'">'+y+'</span>';
        }
        html += '</div><div style="margin-top:5px;font-size:9px;cursor:pointer;color:#6699cc;" data-cal2="'+id+'" data-switch2="mese">&#8592; Per mese</div>';
    }
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
        var nav = el.dataset.nav2;
        if (nav.indexOf('mese:') === 0) {
            // navigazione mese per calendario giorno
            st.m += parseInt(nav.split(':')[1]);
            if (st.m > 12) { st.m = 1;  st.y++; }
            if (st.m < 1)  { st.m = 12; st.y--; }
        } else {
            st.y += parseInt(nav);
        }
        renderCal2(id); return;
    }
    if (el.dataset.switch2 !== undefined) { st.livello = el.dataset.switch2;  renderCal2(id); return; }
    if (el.dataset.rif !== undefined) {
        // selezione giorno per cal-rif
        var val = el.dataset.rif;
        var params = {};
        if (CTX2.custom_val !== '') {
            params['custom_campo'] = CTX2.custom_campo;
            params['custom_op']    = CTX2.custom_op;
            params['custom_val']   = CTX2.custom_val;
        }
        if (CTX2.modo === 'mese') params['mese'] = CTX2.inizio.substring(0,7);
        else                      params['anno'] = CTX2.anno_rif;
        if (val !== 'reset') params['ref_data'] = val;
        window.location.href = buildUrl(params);
        return;
    }
    if (el.dataset.sel2 !== undefined) {
        var parts = el.dataset.sel2.split(':');
        if (parts[0] === 'mese') navigaA2({mese: parts[1]});
        else                     navigaA2({anno: parts[1]});
    }
});
function resetRefData() {
    var params = {};
    if (CTX2.custom_val !== '') {
        params['custom_campo'] = CTX2.custom_campo;
        params['custom_op']    = CTX2.custom_op;
        params['custom_val']   = CTX2.custom_val;
    }
    if (CTX2.modo === 'mese') params['mese'] = CTX2.inizio.substring(0,7);
    else                      params['anno'] = CTX2.anno_rif;
    window.location.href = buildUrl(params);
}
</script>

</body>
</html>