<?php
/**
 * ============================================================================
 * TABELLA PIOGGIA 3BIS - DISPLAY LAYER  tabella_stat_3bis_display.php
 * ============================================================================
 * Struttura: 6 colonne
 *   Periodo | mm | gg pioggia | gg >=20mm | seq. piovosa max | seq. secca max
 * Righe: anno, mese, ultimo trimestre (tutti fino a oggi).
 * Pensato per essere caricato come iframe in stat_display.php, PRIMA di
 * tabella_stat3_display.php.
 *
 * Il display non calcola nulla: stampa i testi preparati dall'API e
 * traduce la 'classe' di ogni cella nel colore della barra (CSS .bar.*).
 * "gg >= 20mm" non ha classe -> nessuna barra.
 *
 * PARAMETRI GET: nessuno (i periodi sono sempre riferiti a oggi).
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../envelop.php';
require_once __DIR__ . '/api/api_tabella_stat3bis_data.php';

$response = getStat3bisData();

if (!$response['success']) {
    echo "<div style='font-family:Arial;font-size:13px;color:#c00;padding:10px;'>
            Errore: " . htmlspecialchars($response['error'] ?? 'Dati non disponibili') . "
          </div>";
    exit;
}

$righe = $response['righe'];
$meta  = $response['meta'];

// Colonne nell'ordine di tabella: chiave API => intestazione
$colonne = [
    'mm'        => 'mm',
    'gg'        => 'gg pioggia',
    'gg20'      => 'gg &ge;20mm',
    'seq_piov'  => 'max seq. piovosa',
    'seq_secca' => 'max seq. secca',
];

// Legenda: classe => fascia di percentili (dalla piu' secca alla piu' piovosa).
// Deve restare allineata a s3bClasse() nell'API.
$legenda = [
    'secco3' => '&lt;10',
    'secco2' => '10&ndash;25',
    'secco1' => '25&ndash;40',
    'norma'  => '40&ndash;60',
    'umido1' => '60&ndash;75',
    'umido2' => '75&ndash;90',
    'umido3' => '&ge;90',
];

function s3bDispH($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pioggia rispetto al clima</title>
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
            width: 24%;
        }
        td.col-val {
            text-align: center;
            position: relative;          /* ancora per la barra */
            padding-bottom: 7px;         /* spazio per la barra */
        }
        .stat-data-date {
            display: block;
            font-size: 9px;
            color: #777;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        /* la frequenza non va mai troncata: se non ci sta, va a capo */
        .stat-data-date.stat-freq {
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
        }
        .stat-nota {
            display: block;
            font-size: 9px;
            color: #999;
            font-style: italic;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .asterisco {
            color: #c00;
            font-size: 0.85em;
            vertical-align: super;
        }

        /* ---- BARRA PERCENTILE ----
           Classi prodotte dall'API (s3bClasse). Per la sequenza secca
           l'API restituisce gia' la classe invertita. */
        .bar {
            position: absolute;
            left: 0; right: 0; bottom: 0;
            height: 5px;
        }
        .bar.secco3 { background: #d62828; }   /* < P10   */
        .bar.secco2 { background: #f28c28; }   /* P10-25  */
        .bar.secco1 { background: #f6c945; }   /* P25-40  */
        .bar.norma  { background: #b4b2a9; }   /* P40-60  */
        .bar.umido1 { background: #7fb8ea; }   /* P60-75  */
        .bar.umido2 { background: #1f5fa8; }   /* P75-90  */
        .bar.umido3 { background: #e0218a; }   /* >= P90  */

        .legenda {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 2px;
            margin-top: 6px;
            font-size: 9px;
            color: #777;
        }
        .legenda .voce {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }
        .legenda .sw { display: block; width: 34px; height: 5px; }
        .legenda .pct { font-size: 8px; color: #888; white-space: nowrap; }
        .legenda .lbl { margin: 0 4px; align-self: flex-start; line-height: 5px; }
        .legenda-nota {
            text-align: center;
            font-size: 8px;
            color: #999;
            margin-top: 2px;
        }

        .stat-footer {
            text-align: center;
            font-size: clamp(5px, 2vw, 9px);
            color: #aaa;
            margin-top: 4px;
            white-space: nowrap;
        }
        @media (max-width: 480px) {
            table { font-size: 9px; }
            th, td { padding: 2px 1px; font-size: 11px; }
            th { font-size: 10px; }
            td.col-val { padding-bottom: 7px; }
            .stat-data-date, .stat-nota { font-size: 8px; line-height: 1.2; }
            /* su telefono le celle sono strette: meglio andare a capo
               che troncare date e percentile */
            td.col-val .stat-data-date,
            td.col-val .stat-nota { white-space: normal; }
        }
        @media (min-width: 768px) {
            table { font-size: 16px; width: 85%; max-width: 75%; }
            th, td { padding: 5px 1px 5px 5px; }
            td.col-val { padding-bottom: 9px; }
            tr { height: auto; }
            .stat-data-date, .stat-nota { font-size: 12px; }
            .legenda { font-size: 11px; }
            .legenda .sw { width: 48px; }
            .legenda .pct, .legenda-nota { font-size: 10px; }
        }
    </style>
</head>
<body>

<table border='1' cellpadding='10' cellspacing='0'>
    <thead>
        <tr>
            <th>Periodo</th>
            <?php foreach ($colonne as $titolo): ?>
            <th><?= $titolo ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($righe as $riga): ?>
        <tr>
            <td>
                <?= s3bDispH($riga['label']) ?><?php if ($riga['scarso']): ?><span class="asterisco">*</span><?php endif; ?>
                <span class="stat-data-date"><?= s3bDispH($riga['periodo_txt']) ?></span>
            </td>
            <?php foreach ($colonne as $mid => $titolo): ?>
                <?php
                    $c = $riga['celle'][$mid];
                    $is_seq = ($mid === 'seq_piov' || $mid === 'seq_secca');

                    $date_txt = $is_seq ? $c['date_txt'] : '';
                    $freq_txt = $is_seq ? ($c['freq_txt'] ?? '') : '';
                ?>
            <td class="col-val">
                <?= s3bDispH($c['val']) ?>
                <?php if ($date_txt !== ''): ?>
                <span class="stat-data-date"><?= s3bDispH($date_txt) ?></span>
                <?php endif; ?>
                <?php if ($freq_txt !== ''): ?>
                <!-- frequenza su riga propria: "≥ in 4/20 anni" -->
                <span class="stat-data-date stat-freq"><?= s3bDispH($freq_txt) ?></span>
                <?php endif; ?>
                <?php if ($is_seq && $c['nota_txt'] !== ''): ?>
                <span class="stat-nota"><?= s3bDispH($c['nota_txt']) ?></span>
                <?php endif; ?>
                <?php if (!$is_seq && !empty($c['classe'])): ?>
                <span class="bar <?= s3bDispH($c['classe']) ?>"></span>
                <?php endif; ?>
            </td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="legenda">
    <span class="lbl">secco</span>
    <?php foreach ($legenda as $classe => $fascia): ?>
    <span class="voce">
        <span class="sw bar <?= $classe ?>" style="position:static;"></span>
        <span class="pct">P<?= $fascia ?></span>
    </span>
    <?php endforeach; ?>
    <span class="lbl">piovoso</span>
</div>

<div class="stat-footer">
    clima <?= s3bDispH($meta['clima_label']) ?>
    &nbsp;|&nbsp; aggiornato: <?= s3bDispH($meta['generato_il']) ?>
    <?php if ($meta['scarso']): ?>
    &nbsp;|&nbsp; <span style="color:#c00;">* dato con copertura &lt;75%</span>
    <?php endif; ?>
</div>

<script>
// ---- resize dinamico iframe ----
function sendResize() {
    window.parent.postMessage({
        action:   'resize',
        iframeId: 'stat-iframe-tab3bis',
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