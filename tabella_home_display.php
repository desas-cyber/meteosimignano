<?php
/**
 * ============================================================================
 * TABELLA HOME - DISPLAY LAYER
 * ============================================================================
 * 
 * RESPONSABILITÀ:
 * - Include dati da tabella_home_data.php
 * - Rendering HTML della tabella
 * - Gestione interattività (click/tooltip)
 * - NESSUN calcolo o query diretta
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../envelop_lettura.php';
require_once __DIR__ . '/tabella_home_data.php';

// ✅ DEBUG: Verifica funzioni caricate
echo "<!-- DEBUG FUNZIONI CARICATE -->\n";
echo "<!-- File tabella_home_data.php incluso: " . (file_exists(__DIR__ . '/tabella_home_data.php') ? 'SÌ' : 'NO') . " -->\n";
echo "<!-- createDeltaIndicator: " . (function_exists('createDeltaIndicator') ? 'OK' : 'MANCA') . " -->\n";
echo "<!-- createComfortIndicator: " . (function_exists('createComfortIndicator') ? 'OK' : 'MANCA') . " -->\n";
echo "<!-- createWindchillHeatIndicator: " . (function_exists('createWindchillHeatIndicator') ? 'OK' : 'MANCA') . " -->\n";
echo "<!-- createPressureTrendIndicator: " . (function_exists('createPressureTrendIndicator') ? 'OK' : 'MANCA') . " -->\n";
echo "<!-- Fine debug -->\n\n";

// ============================================================================
// RECUPERA DATI
// ============================================================================
$response = getMeteoData($pdo);

if (!$response['success']) {
    echo "<div class='error-message'>{$response['error']}</div>";
    exit;
}

$data = $response['raw_data'];
$rows = $response['rows'];
$alerts = $response['alerts'];
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabella Meteo Simignano</title>
    <style>
        /* ===== STILI ESISTENTI (mantieni i tuoi) ===== */
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
        }
        
        tr { height: 3.1em; }
        
        th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: left;
        }
        
        .riga-separatore {
            border-bottom: 3px solid #666 !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            position: relative;
        }
        
        .riga-separatore td {
            border-bottom: 3px solid #666 !important;
            padding-bottom: 4px;
        }
        
        /* ===== NUOVI STILI PER INTERATTIVITÀ ===== */
        
        
        .clickable-value:hover {
            color: #ff4444;
            text-decoration: underline solid;
        }
        
        /* ===== STILE ICONE ALBA/TRAMONTO ===== */
        .icon-sun-wrapper {
    display: inline-flex !important;     /* Orizzontale SEMPRE */
    align-items: center;                 /* Allineamento verticale centrato */
    gap: 8px;                            /* Spazio tra icona-testo-icona */
}

/* Link icone */
.icon-sun-link {
    display: inline-flex;
    align-items: center;
}

.icon-sun-link svg {
    cursor: pointer;
    transition: transform 0.20s ease, stroke 0.2s ease;
}

.icon-sun-link:hover svg {
    transform: scale(1.15);              /* Ingrandimento on hover */
}

.icon-sun-link.active svg {
    stroke: red !important;              /* Attivo = rosso */
}

/* Blocco testo centrale (verticale) */
.icon-sun-labels {
    display: flex;
    flex-direction: column;              /* Alba SOPRA, Tramonto SOTTO */
    align-items: center;                 /* Centrato */
    gap: 2px;                            /* Spazio minimo */
    line-height: 1.1;
}

/* Label singola */
.icon-label {
    font-size: 10px;
    font-weight: bold;
    opacity: 0.9;
    white-space: nowrap;
}

/* Mobile: solo riduzione dimensioni */
@media (max-width: 599px) {
    .icon-sun-wrapper {
        gap: 6px;                        /* Gap più stretto */
    }
    
    .icon-sun-link svg {
        width: 18px;
        height: 18px;
    }
    
    .icon-label {
        font-size: 9px;
    }
    
    .icon-sun-labels {
        gap: 1px;
    }
}
        
        /* ===== MODAL ===== */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 90%;
            max-height: 90%;
            overflow: auto;
            position: relative;
        }
        
        .modal-close {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }
        
        .modal-close:hover {
            color: #333;
        }
        
        @media (min-width: 768px) {
            table {
                font-size: 16px;
                width: 85%;
                max-width: 75%;
            }
            
            th, td {
                padding: 5px 1px 5px 5px;
                white-space: normal;
            }
            
            tr { height: auto; }
        }

         /*====ICONE ALBA TRAMONTO =====*/

   /* Wrapper orizzontale icone alba/tramonto */
.icon-sun-wrapper {
    display: inline-flex;
    align-items: center;
    gap: 14px; /* distanza tra le due icone */
}

/* Blocco icona + label sulla stessa riga */
.icon-sun-inline {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* Label piccola e colorata */
.icon-label {
    font-size: 10px;
    font-weight: bold;
    opacity: 0.8;
}

/* Hover migliorato */
.icon-sun-inline a svg {
    cursor: pointer;
    transition: transform 0.20s ease, stroke 0.2s ease;
}

.icon-sun-inline a:hover svg {
    transform: scale(1.15);
}

/* Icona attiva (filtro premuto) */
.icon-sun-inline a.active svg {
    stroke: red !important;
}


    

/* ========== LIGHTBOX CSS ========== */
.lightbox {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.95);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.lightbox.active {
    display: flex;
}

.lightbox-content {
    max-width: 90%;
    max-height: 90%;
    position: relative;
}

.lightbox-content img {
    max-width: 100%;
    max-height: 80vh;
    object-fit: contain;
}

.lightbox-info {
    color: white;
    text-align: center;
    margin-top: 15px;
    font-size: 16px;
}
@media (max-width: 480px) {
  .lightbox-info {
    font-size: 10px;    /* mobile = metà circa */
    margin-top: 6px;
  }
}
.lightbox-control-btn {
    position: absolute;
    background: rgba(255,255,255,0.2);
    border: none;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

#close-btn {
    top: 20px;
    right: 20px;
}

.nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.2);
    border: none;
    border-radius: 50%;
    width: 60px;
    height: 60px;
    cursor: pointer;
}

.nav-btn.prev {
    left: 20px;
}

.nav-btn.next {
    right: 20px;
}

.nav-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

/* =====================================================
   MOBILE – RIDUZIONE DIMENSIONI (OVERRIDE MINIMO)
   ===================================================== */
@media (max-width: 480px) {

  /* Alba / Tramonto: riduci SOLO dimensioni */
  .icon-sun-inline svg {
    width: 14px !important;
    height: 14px !important;
  }

  .icon-label {
    font-size: 8px !important;
  }

  /* Lightbox: pulsanti dimezzati */
  .lightbox-control-btn,
  .nav-btn {
    width: 26px !important;
    height: 26px !important;
  }

  .lightbox-control-btn svg,
  .nav-btn svg {
    width: 16px !important;
    height: 16px !important;
  }
}

    </style>
<script>
// ========== GESTIONE POPUP LEGENDA ==========
function toggleLegenda() {
    const modal = document.getElementById('legenda-modal');
    if (modal) {
        modal.classList.add('active');
        console.log('✅ Legenda aperta');
    } else {
        console.error('❌ Modal legenda non trovato!');
    }
}

function closeLegenda() {
    const modal = document.getElementById('legenda-modal');
    if (modal) {
        modal.classList.remove('active');
        console.log('✅ Legenda chiusa');
    }
}

// Chiudi con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('legenda-modal');
        if (modal && modal.classList.contains('active')) {
            closeLegenda();
        }
    }
});

// Chiudi cliccando fuori
window.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('legenda-modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            // Chiudi solo se clicchi sullo sfondo nero, non sul contenuto
            if (e.target === modal) {
                closeLegenda();
            }
        });
        console.log('✅ Legenda listener registrato');
    }
});
</script>

<!-- ========== MODAL GRAFICI (separato) ========== -->
<div id="modal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <div id="modal-body"></div>
    </div>
</div>

<!-- ========== JAVASCRIPT INTERAZIONI ========== -->
<script src="js/tabella_interactions.js"></script>
    
</head>
<body>

<!-- ========== ALERTS ========== -->
<?php if (!empty($alerts)): ?>
    <?php foreach ($alerts as $alert): ?>
        <div class="alert-banner <?= $alert['severity'] ?>">
            <strong>⚠️ <?= htmlspecialchars($alert['type']) ?>:</strong>
            <?= htmlspecialchars($alert['message']) ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- ========== TABELLA ========== -->
<table border='1' cellpadding='10' cellspacing='0'>
    <thead>
        <tr>
            <th style='background-color: rgba(173, 173, 173, 0.8);'>TABELLA METEO:<br>Parametro</th>
            <th style='background-color: rgba(173, 173, 173, 0.8);'>Note</th>
            <th style='background-color: rgba(173, 173, 173, 0.8);'>Dati</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
    <tr <?= ($row['separator'] ?? false) ? 'class="riga-separatore"' : '' ?>
        <?php if (isset($row['link'])): ?>
            onclick="window.top.location.href='<?= htmlspecialchars($row['link']) ?>';"
            style="cursor: pointer;"
            title="<?= htmlspecialchars($row['interactive']['tooltip'] ?? 'Clicca per aprire') ?>"
        <?php endif; ?>>
        
        <!-- Colonna 1: Label -->
        <td><?= htmlspecialchars($row['label']) ?></td>
        
        <!-- Colonna 2: Note -->
        <td><?= $row['note'] ?></td>
        
        <!-- Colonna 3: Valore (potenzialmente cliccabile) -->
        <td>
            <?php if (isset($row['interactive']['clickable']) && $row['interactive']['clickable']): ?>
                <span class="clickable-value" 
                      data-action='<?= htmlspecialchars(json_encode($row['interactive']['action'] ?? []), ENT_QUOTES) ?>'
                      title="<?= htmlspecialchars($row['interactive']['tooltip'] ?? '') ?>">
                    <?= $row['value'] ?>
                </span>
            <?php else: ?>
                <?= $row['value'] ?>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>

<!-- JavaScript per gestire i link delle righe -->
<script>
document.querySelectorAll('tr[data-link]').forEach(function(row) {
    row.addEventListener('click', function() {
        window.location.href = this.getAttribute('data-link');
    });
});
</script>
    </tbody>
</table>
<!-- ========== LEGENDA PALLINI (POPUP CON TESTO) ========== -->
<div style="text-align: center; margin: 10px 0;">
    <button id="legenda-btn" class="legenda-toggle" onclick="toggleLegenda()">
        📊 Legenda Pallini Colorati
    </button>
</div>

<!-- Modal Legenda -->
<div id="legenda-modal" class="modal-legenda">
    <div class="legenda-content-text">
        <span class="modal-close-legenda" onclick="closeLegenda()">&times;</span>
        
        <!-- Contenuto stile testo puro -->
        <div style='margin-top: 2px; padding: 2px; font-size: 11px; background-color: #f9f9f9; border: 1px solid #ddd; font-family: monospace; line-height: 1.0'>
            <h3>Legenda pallini colorati:</h3>
            <div style='display: flex; flex-wrap: wrap; gap: 8px;'>
                <div><?= createDeltaIndicator(3) ?> Aumento significativo (&gt;2°C)</div>
                <div><?= createDeltaIndicator(1) ?> Aumento moderato (0.6-2°C)</div>
                <div><?= createDeltaIndicator(0) ?> Stabile (-0.5 / +0.5°C)</div>
                <div><?= createDeltaIndicator(-1) ?> Diminuzione moderata (-0.6/-2°C)</div>
                <div><?= createDeltaIndicator(-3) ?> Diminuzione significativa (&lt;-2°C)</div>
            </div>
            <p><em>Per la pressione le soglie sono in hPa: &gt;3, 1-3, -1/+1, -1/-3, &lt;-3</em></p>

            <h4>Comfort (Dewpoint, fonte: bom.gov.au):</h4>
            <div style='display: flex; flex-wrap: wrap; gap: 5px;'>
                <div><?= createComfortIndicator(7) ?> N/A (&lt;8°C)</div>
                <div><?= createComfortIndicator(8) ?> Secco (8-9°C)</div>
                <div><?= createComfortIndicator(15) ?> Gradevole (10-15°C)</div>
                <div><?= createComfortIndicator(16) ?> Gradevole-Umido (16-19°C)</div>
                <div><?= createComfortIndicator(20) ?> Umido-scomodo (20-23°C)</div>
                <div><?= createComfortIndicator(24) ?> Condizioni estreme (&gt;24°C)</div>
            </div>

            <h4>Windchill / Heat Index:</h4>
            <div style='display: flex; flex-wrap: wrap; gap: 5px;'>
                <div><?= createWindchillHeatIndicator(-3) ?> Sensazione di freddo (&lt;-1°C)</div>
                <div><?= createWindchillHeatIndicator(0) ?> Sensazione neutra (-1°C 0°C)</div>
            </div>
        </div>
        
        <p style="text-align: center; margin: 8px 0 0 0; color: #999; font-size: 11px;">
            <em>ESC o click fuori per chiudere</em>
        </p>
    </div>
</div>


<style>
/* ========== BOTTONE LEGENDA (RESPONSIVE) ========== */
.legenda-toggle {
    background: linear-gradient(135deg, #e0e0e0 0%, #9e9e9e 100%);
    color: #000000;
    border: 2px solid #000000;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: bold;
    border-radius: 20px;
    cursor: pointer;
    background: transparent;    /* NESSUN riempimento */
    box-shadow: none;
    transition: all 0.2s ease;
}

.legenda-toggle:hover {
    transform: translateY(-1px);
    color: #ea0e0eff;
    box-shadow: 0 4px 12px rgba(158, 158, 158, 0.5);
    background: linear-gradient(135deg, #d0d0d0 0%, #8e8e8e 100%);
}

/* Mobile: bottone ridotto a metà */
@media (max-width: 768px) {
    .legenda-toggle {
        padding: 6px 12px;      /* ← -40% */
        font-size: 11px;        /* ← -21% */
        border-radius: 14px;    /* ← -30% */
    }
}

/* Mobile molto piccolo: ancora più compatto */
@media (max-width: 480px) {
    .legenda-toggle {
        padding: 5px 10px;      /* ← -50% */
        font-size: 10px;        /* ← -29% */
        border-radius: 12px;    /* ← -40% */
    }
}
/* ===== NUOVI STILI PER INTERATTIVITÀ ===== */
.clickable-value {
    cursor: pointer;
    color: #000000;
    text-decoration: none;
    padding: 2px 6px;
    border-radius: 12px;
    border: 1px solid #9e9e9e;
    display: inline-block;
    transition: all 0.2s ease;

}

.clickable-value:hover {
    background: linear-gradient(135deg, #e0e0e0 0%, #9e9e9e 100%);
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(158, 158, 158, 0.4);
}
/* ========== MODAL LEGENDA ========== */
.modal-legenda {
    display: none !important;
    position: fixed;
    z-index: 10001;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.85);
    align-items: center;
    justify-content: center;
}

.modal-legenda.active {
    display: flex !important;
}

.legenda-content-text {
    max-width: 90%;
    max-height: 85vh;
    overflow-y: auto;
    background: white;
    padding: 20px;
    border-radius: 8px;
    position: relative;
}

/* Mobile: meno padding */
@media (max-width: 768px) {
    .legenda-content-text {
        padding: 15px;
        max-width: 95%;
    }
}

/* ========== X CHIUSURA ========== */
.modal-close-legenda {
    position: absolute;
    top: 8px;
    right: 8px;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    color: #999;
    line-height: 1;
    width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
    background: white;
    border-radius: 50%;
}

.modal-close-legenda:hover {
    color: #f44336;
    background: #ffebee;
}
</style>

<script>
// ========== GESTIONE POPUP LEGENDA ==========
function toggleLegenda() {
    const modal = document.getElementById('legenda-modal');
    if (modal) {
        modal.classList.add('active');
        console.log('✅ Legenda aperta');
    }
}

function closeLegenda() {
    const modal = document.getElementById('legenda-modal');
    if (modal) {
        modal.classList.remove('active');
        console.log('✅ Legenda chiusa');
    }
}

// Chiudi con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('legenda-modal');
        if (modal && modal.classList.contains('active')) {
            closeLegenda();
        }
    }
});

// Chiudi cliccando fuori
window.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('legenda-modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeLegenda();
            }
        });
        console.log('✅ Legenda listener registrato');
    }
});
</script>


</body>
</html>