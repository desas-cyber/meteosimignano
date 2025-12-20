<?php

/* 0) Impostazioni per non "sporcare" il JSON con warning/notice ------------- */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../envelop.php';
require_once __DIR__ . '/aggiornaCartellaImmagini.php';
require_once __DIR__ . '/env_tables_helper.php';

$directory = 'belle/';
$table_name = table_name('DB_immagini_belle');

// ============================================================================
// GESTIONE FILTRI
// ============================================================================

// Recupera parametri filtro dalla query string
$filtro_data_inizio = isset($_GET['data_inizio']) ? $_GET['data_inizio'] : null;
$filtro_data_fine = isset($_GET['data_fine']) ? $_GET['data_fine'] : null;
$filtro_sun_phase = isset($_GET['sun_phase']) ? $_GET['sun_phase'] : 'all';
$filtro_altro = isset($_GET['altro']) ? $_GET['altro'] : 'all';
$filtro_sequenza = isset($_GET['sequenza']) ? (int)$_GET['sequenza'] : 0;

// Determina se ci sono filtri attivi
$filtri_attivi = !empty($filtro_data_inizio) || !empty($filtro_data_fine) || 
                 $filtro_sun_phase !== 'all' || $filtro_altro !== 'all' || 
                 $filtro_sequenza > 0;

// Recupera valori unici di "altro" dal database
$stmt_altro = $pdo->prepare("SELECT DISTINCT altro FROM $table_name WHERE altro IS NOT NULL ORDER BY altro");
$stmt_altro->execute();
$valori_altro = $stmt_altro->fetchAll(PDO::FETCH_COLUMN);

// Ottiene i dati delle immagini con filtri applicati
$data = getImageDataFromFolderFiltered($pdo, $directory, $table_name, [
    'data_inizio' => $filtro_data_inizio,
    'data_fine' => $filtro_data_fine,
    'sun_phase' => $filtro_sun_phase,
    'altro' => $filtro_altro,
    'sequenza' => $filtro_sequenza
]);
  
$records = []; 
$errore_messaggio = null;

if (isset($data['error'])) {
    // Gestione errore
    $errore_messaggio = $data['error'];
    $records = [];
} elseif (empty($data['records'])) {
    // Nessuna immagine trovata
    $errore_messaggio = null; // Gestiremo questo caso separatamente
    $records = [];
} else {
    $records = $data['records'];

    // Ciclo per formattare la data per l'uso nella galleria/lightbox
    foreach ($records as &$rec) { 
        if (!empty($rec['data_ora'])) {
            // Formattazione per la visualizzazione nella miniatura e lightbox
            $rec['data_ora'] = (new DateTime($rec['data_ora']))->format('d/m/Y H:i');
        } else {
            $rec['data_ora'] = 'Data/Ora N/D';
        }
    }
}


/**
 * Funzione modificata per supportare filtri
 */
function getImageDataFromFolderFiltered(PDO $pdo, string $directory, string $tableName, array $filtri = []): array
{
    $limit = 200;
    $extensions = ['jpg','jpeg','png','gif'];

    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
        return ['error' => "Nome tabella non valido.", 'mainImage' => '', 'count' => 0, 'records' => []];
    }

    $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (!is_dir($directory)) {
        return ['error' => "La cartella '$directory' non esiste.", 'mainImage' => '', 'count' => 0, 'records' => []];
    }

    $patternParts = array_map(fn($ext) => '*.' . ltrim(strtolower($ext), '.'), $extensions);
    $pattern = '{' . implode(',', $patternParts) . '}';
    $files = glob($directory . $pattern, GLOB_BRACE);

    if (!$files || count($files) === 0) {
        return ['error' => "Nessuna immagine trovata.", 'mainImage' => '', 'count' => 0, 'records' => []];
    }

    rsort($files, SORT_STRING);
    $basenames = array_map('basename', $files);

    $sql = "SELECT * FROM {$tableName} WHERE FILE IN (" . implode(',', array_fill(0, count($basenames), '?')) . ")";
    $params = $basenames;

    if (!empty($filtri['data_inizio'])) {
        $sql .= " AND DATA_ORA >= ?";
        $params[] = $filtri['data_inizio'] . ' 00:00:00';
    }

    if (!empty($filtri['data_fine'])) {
        $sql .= " AND DATA_ORA <= ?";
        $params[] = $filtri['data_fine'] . ' 23:59:59';
    }

    if (isset($filtri['sun_phase']) && $filtri['sun_phase'] !== 'all') {

    switch ($filtri['sun_phase']) {

        case '1': // Alba
        case '2': // Tramonto
            $sql .= " AND sun_phase = ?";
            $params[] = (int)$filtri['sun_phase'];
            break;

        case 'day': // ☀️ Pieno giorno
            $sql .= " AND sun_phase IS NULL";
            break;

        case 'null': // esplicito "nessun dato"
            $sql .= " AND sun_phase IS NULL";
            break;
            }
        }


    if (isset($filtri['altro']) && $filtri['altro'] !== 'all') {
        $sql .= " AND altro = ?";
        $params[] = (int)$filtri['altro'];
    }

    $sql .= " ORDER BY DATA_ORA DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return ['error' => "Errore SQL: " . $e->getMessage(), 'mainImage' => '', 'count' => 0, 'records' => []];
    }

    $byFile = [];
    foreach ($rows as $r) {
        if (!isset($r['FILE'])) continue;
        $byFile[$r['FILE']] = $r;
    }

    if (!empty($filtri['sequenza']) && $filtri['sequenza'] >= 2) {
        $rows = filtraSequenzeConsecutive($rows, $filtri['sequenza']);
        $byFile = [];
        foreach ($rows as $r) {
            if (!isset($r['FILE'])) continue;
            $byFile[$r['FILE']] = $r;
        }
    }

    $records = [];
    foreach ($files as $path) {
        $file = basename($path);
        $row = $byFile[$file] ?? null;
        if (!$row) continue;

        $records[] = [
            'src' => $path,
            'file' => $file,
            'data_ora' => $row['DATA_ORA'] ?? null,
            'temp' => isset($row['Temp']) ? (float)$row['Temp'] : null,
            'hr' => isset($row['HR']) ? (float)$row['HR'] : null,
            'p_hpa' => isset($row['P_hPa']) ? (float)$row['P_hPa'] : null,
            'wind_kmh' => isset($row['vento_kmh']) ? (float)$row['vento_kmh'] : null,
            'dir_text' => $row['Dir_text'] ?? null,
            'sun_phase' => isset($row['sun_phase']) ? (int)$row['sun_phase'] : null
        ];
    }

    if (count($records) > $limit) {
        $records = array_slice($records, 0, $limit);
    }

    return ['error' => null, 'mainImage' => $records[0]['src'] ?? '', 'count' => count($records), 'records' => $records];
}

/**
 * Filtra per sequenze consecutive
 */
function filtraSequenzeConsecutive(array $rows, int $minSequenza): array {
    if ($minSequenza < 2) return $rows;

    usort($rows, function($a, $b) {
        return strcmp($a['DATA_ORA'] ?? '', $b['DATA_ORA'] ?? '');
    });

    $sequenze = [];
    $sequenza_corrente = [];

    foreach ($rows as $row) {
        if (empty($sequenza_corrente)) {
            $sequenza_corrente[] = $row;
            continue;
        }

        $prev = end($sequenza_corrente);
        $prev_time = strtotime($prev['DATA_ORA'] ?? '');
        $curr_time = strtotime($row['DATA_ORA'] ?? '');

        if ($curr_time - $prev_time <= 1300) {
            $sequenza_corrente[] = $row;
        } else {
            if (count($sequenza_corrente) >= $minSequenza) {
                $sequenze[] = $sequenza_corrente;
            }
            $sequenza_corrente = [$row];
        }
    }

    if (count($sequenza_corrente) >= $minSequenza) {
        $sequenze[] = $sequenza_corrente;
    }

    $result = [];
    foreach ($sequenze as $seq) {
        $result = array_merge($result, $seq);
    }

    usort($result, function($a, $b) {
        return strcmp($b['DATA_ORA'] ?? '', $a['DATA_ORA'] ?? '');
    });

    return $result;
}


/**
 * Funzione PHP per determinare la classe di colore in base alla temperatura.
 * @param float|null $temp Temperatura.
 * @return string Classe CSS.
 */
function getTempColorClass($temp) {
  if (!is_numeric($temp)) {
      return 'temp-default';
  }
  
  if ($temp > 35) {
      return 'temp-red';
  }
  if ($temp >= 25) {
      return 'temp-orange';
  }
  if ($temp >= 15) {
      return 'temp-green';
  }
  if ($temp >= 5) {
      return 'temp-lightblue';
  }
  if ($temp >= -3) {
      return 'temp-blue';
  }
  return 'temp-violet';
}

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.08">
    <title>Meteosimignano_diario_del_cielo</title>
    
    
    <style>
    
/* ==========================================================================
   CSS COMPLETO E CORRETTO PER BELLE.PHP
   ========================================================================== */

body {
    font-family: Arial, sans-serif;
    display: flex;
    flex-direction: column;
    align-items: center;
    margin: 0;
    padding: 0;
}

.main-header {
    width: 100%;
    max-width: 1000px;
    text-align: center;
    padding: 10px;
    box-sizing: border-box;
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    position: relative; 
}

.header-content {
    flex-grow: 1; 
    text-align: center; 
    min-width: 0; 
}

.header-icon {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: #333; 
    min-width: 20px; 
    font-size: 0.7em;
    padding: 0 5px;
    cursor: pointer;
}

.header-icon:hover {
    color: red;
}

.header-icon svg {
    width: 36px;
    height: 36px;
    stroke-width: 2.5; 
}

@media (max-width: 599px) {
    .header-icon svg {
        width: 20px;
        height: 20px;
        padding-left: 20px;
        padding-right: 20px;
        stroke-width: 2.5;
    }
}

.header-icon .icon-label {
    display: block; 
    margin-top: 2px;
}

.main-title {
    font-size: 6vw;
    white-space: nowrap;
    margin: 0;
}

@media (min-width: 600px) {
    .main-title {
        font-size: 55px;
    }
}

.sub-title {
    font-size: 3vw;
    font-weight: normal;
    white-space: nowrap;
    margin: 10px;
}

@media (min-width: 600px) {
    .sub-title {
        font-size: 30px;
    }
}

.gallery-title {
    text-align: center; 
    margin: 20px 0;
    font-size: 5vw;
    max-width: 100%;
    white-space: normal;
    overflow: visible;
    text-overflow: clip;
    min-width: 0;
    font-weight: bold;
    color: black;
}

@media (min-width: 600px) {
    .gallery-title {
        font-size: 30px;
    }
}

/* ==========================================================================
   GALLERIA
   ========================================================================== */
.gallery {
    display: flex;
    flex-wrap: wrap;
     width: 100%;  
    max-width: 1000px;
    max-height: calc(4 * 150px + 30px);
    margin: 0 auto;
    overflow-y: auto;
    padding: 5px;
    box-sizing: border-box;
}

.gallery .thumb {
    position: relative;
    display: inline-block;
    width: calc(33.333% - 20px);
    margin: 10px;
    overflow: hidden;
    border: 4px solid #ddd;
    border-radius: 8px;
    box-sizing: border-box;
    container-type: inline-size;
    aspect-ratio: 4 / 2.79;
}

.gallery .thumb > img {
    display: block;
    width: 110%;
    height: 110%;
    object-fit: cover;
    object-position: center;
    transition: transform 0.2s ease, clip-path 0.2s ease;
    clip-path: inset(0% 0 7% 0);
    cursor: pointer;
}

.gallery .thumb > img:hover {
    transform: scale(1.1);
}

/* Mobile: 2 per riga */
@media (max-width: 480px) {
    .gallery .thumb {
        width: calc(50% - 16px);
        margin: 8px;
    }
    
    .gallery {
        max-height: calc(2 * 200px + 20px);
    }
}

/* ==========================================================================
   OVERLAY MINIATURE
   ========================================================================== */
.overlay-mini {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 60%;
    max-width: 180px;
    text-align: center;
    line-height: 1.05;
    pointer-events: none;
}

.overlay-mini > * {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1px;
}

.overlay-mini > *:last-child {
    margin-bottom: 0;
}

.overlay-mini .temp-line,
.overlay-mini .ora-line {
    text-shadow: 0 0 3px rgba(0,0,0,0.9), 0 0 2px rgba(0,0,0,0.9);
    white-space: nowrap;
    font-size: clamp(12px, 2.8vw, 18px);
}

.temp-line {
    font-weight: 700;
    display: flex !important;
    width: 100%;
    justify-content: center;
    align-self: center;
}

.temp-line sup,
.temp-line .sup {
    vertical-align: baseline;
    position: relative;
    top: -0.1em;
    font-size: 0.8em;
}

.ora-line {
    display: flex;
    align-items: center;
    font-weight: 600;
    color: #ff1e1e;
}

.ora-line > * {
    margin-right: 4px;
}

.ora-line > *:last-child {
    margin-right: 0;
}

/* Mobile font sizes */
@media (max-width: 480px) {
    .overlay-mini {
        width: 72%;
        max-width: 175px;
        line-height: 1.05;
    }
    
    .overlay-mini .ora-line {
        font-size: clamp(11px, 2.2vw, 16px) !important;
    }
    
    .overlay-mini .temp-line {
        font-size: clamp(12px, 3.4vw, 18px) !important;
    }
}

@supports (font-size: 1cqw) {
    .overlay-mini {
        width: min(60cqw, 180px);
        max-width: 70cqw;
    }
    
    .overlay-mini .temp-line,
    .overlay-mini .ora-line {
        font-size: clamp(12px, 5cqw, 18px) !important;
    }
}

@supports (font-size: 1cqw) {
    @media (max-width: 480px) {
        .overlay-mini .ora-line {
            font-size: clamp(11px, 4cqw, 16px) !important;
        }
        
        .overlay-mini .temp-line {
            font-size: clamp(12px, 5cqw, 18px) !important;
        }
    }
}

/* ==========================================================================
   COLORI TEMPERATURA
   ========================================================================== */
.icon {
    width: 1em;
    height: 1em;
    vertical-align: -1px;
    fill: currentColor;
}

.icon-outline {
    fill: currentColor;
}

.temp-red { color: #ec0835; }
.temp-orange { color: #cf7618; }
.temp-green { color: #79f603; }
.temp-lightblue { color: #09e3ce; }
.temp-blue { color: #007bff; }
.temp-violet { color: #8b00ff; }
.temp-default { color: #9c9c9c; }

/* ==========================================================================
   BARRA FILTRI - VERSIONE COMPATTA MOBILE
   ========================================================================== */

.filter-bar {
    width: 100%;
    max-width: 1000px;
    margin: 20px auto;
    background: #f0f0f0;
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    box-sizing: border-box;
    display: none;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.filter-bar.active {
    display: block;
}

.filter-close {
    position: absolute;
    top: 10px;
    right: 10px;
    background: transparent;
    border: none;
    font-size: 24px;
    color: #666;
    cursor: pointer;
    padding: 5px;
    line-height: 1;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.filter-close:hover {
    color: #f44336;
}

.filter-bar form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    min-width: 140px;
}

.filter-group label {
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 4px;
    color: #333;
}

.filter-group input,
.filter-group select {
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

.filter-actions {
    display: flex;
    gap: 8px;
    align-items: flex-end;
}

.filter-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
}

.filter-btn-apply {
    background: #4CAF50;
    color: white;
}

.filter-btn-apply:hover {
    background: #45a049;
}

/* ==========================================================================
   MOBILE: LAYOUT COMPATTO
   ========================================================================== */
@media (max-width: 768px) {
    .filter-bar {
        padding: 10px;
        margin: 10px auto;
    }
    
    .filter-bar form {
        gap: 8px;
    }
    
    /* RIGA 1: Data Inizio + Data Fine (50% ciascuna) */
    .filter-group:nth-child(1),
    .filter-group:nth-child(2) {
        width: calc(50% - 4px);
        min-width: 0;
    }
    
    /* RIGA 2: Alba/Tramonto (100% larghezza) */
    .filter-group:nth-child(3) {
        width: 100%;
        min-width: 0;
    }
    
    /* RIGA 3: Altro + Sequenza (50% ciascuna) */
    .filter-group:nth-child(4),
    .filter-group:nth-child(5) {
        width: calc(50% - 4px);
        min-width: 0;
    }
    
    /* RIGA 4: Bottone (centrato 100%) */
    .filter-actions {
        width: 100%;
        justify-content: center;
    }
    
    /* Altezza ridotta per input/select */
    .filter-group input,
    .filter-group select {
        padding: 4px 6px;
        font-size: 13px;
        height: 32px;
        line-height: 1.2;
        box-sizing: border-box;
    }
    
    /* Fix specifico per input date e number */
    .filter-group input[type="date"],
    .filter-group input[type="number"] {
        padding: 2px 6px;
        height: 32px;
    }
    
    /* Label più compatte */
    .filter-group label {
        font-size: 11px;
        margin-bottom: 3px;
    }
    
    /* Bottone più compatto */
    .filter-btn {
        padding: 6px 12px;
        font-size: 13px;
    }
    
    /* X button leggermente più piccolo */
    .filter-close {
        font-size: 20px;
        width: 26px;
        height: 26px;
        top: 8px;
        right: 8px;
    }
}

/* Mobile molto stretto: tutto ancora più compatto */
@media (max-width: 480px) {
    .filter-bar {
        padding: 8px;
        margin: 8px auto;
    }
    
    .filter-bar form {
        gap: 6px;
    }
    
    .filter-group input,
    .filter-group select {
        padding: 3px 5px;
        font-size: 12px;
        height: 28px;
        line-height: 1.2;
        box-sizing: border-box;
    }
    
    /* Fix specifico per input date e number su mobile stretto */
    .filter-group input[type="date"],
    .filter-group input[type="number"] {
        padding: 1px 5px;
        height: 28px;
    }
    
    .filter-group label {
        font-size: 10px;
        margin-bottom: 2px;
    }
    
    .filter-btn {
        padding: 5px 10px;
        font-size: 12px;
    }
}



/* ==========================================================================
   MESSAGGI DI STATO
   ========================================================================== */
.gallery-container {
    width: 100%;
    display: flex;
    justify-content: center;
    padding: 0;
    margin: 0;
}

.status-message {
    width: 100%;
    max-width: 1000px;
    margin: 40px auto;
    padding: 30px;
    text-align: center;
    font-size: 18px;
    color: #999;
    border: 2px dashed #ddd;
    border-radius: 8px;
    background: #fafafa;
}

.status-message.error {
    color: #c62828;
    border-color: #ffcdd2;
    background: #ffebee;
}

.status-message svg {
    width: 48px;
    height: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* ==========================================================================
   LIGHTBOX
   ========================================================================== */
.lightbox {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.85);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.lightbox.active {
    display: flex;
}

.lightbox-content {
    display: inline-block;
    position: relative;
    margin: 0 auto;
}

.lightbox img {
    max-width: 95vw !important;
    max-height: 95vh !important;
    width: auto !important;
    height: auto !important;
    object-fit: contain !important;
    clip-path: none !important;
}

.lightbox-date,
.lightbox-info {
    position: absolute;
    left: 0;
    right: 0;
    text-align: center;
    background: rgba(0, 0, 0, 0.6);
    color: #fff;
    font-size: 14px;
    font-weight: normal;
    padding: 3px 6px;
    box-sizing: border-box;
    line-height: 1.2;
    border-radius: 5px !important;
}

.lightbox .nav-btn {
    position: fixed;
    bottom: 12px;
    background: rgba(0,0,0,0.35);
    border: none;
    padding: 10px 12px;
    border-radius: 999px;
    cursor: pointer;
    z-index: 2000;
}

.lightbox .nav-btn.prev {
    right: 12px;
}

.lightbox .nav-btn.next {
    left: 12px;
}

.lightbox .nav-btn:hover {
    background: rgba(255,255,255,0.8);
}

.lightbox .nav-btn:disabled {
    opacity: .25;
    pointer-events: none;
}

#close-btn.lightbox-close {
    position: fixed;
    top: 15px;
    left: 50%;
    margin-left: -1.5rem;
    background: transparent;
    color: red;
    font-size: 2rem;
    border: none;
    cursor: pointer;
    z-index: 1001;
}
    </style>
        
</head>
<body>

<header class="main-header">
    <a href="index.php" class="header-icon left-icon" title="Home">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        <span class="icon-label">Home</span>
    </a>
    
    <div class="header-content">
        <h1 class="main-title">MeteoSimignano</h1>
        <h1 class="sub-title">43°17′32.5″N 11°10′01.49″E @ 418m slm</h1>
    </div>    
    
    <a href="#" class="header-icon right-icon" title="Filtri" onclick="toggleFilterBar(); return false;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
        </svg>
        <span class="icon-label">Filtri</span>
    </a>
</header>

<!-- ========== BARRA FILTRI ========== -->
<div class="filter-bar <?php echo $filtri_attivi ? 'active' : ''; ?>" id="filterBar">
    <button class="filter-close" onclick="closeFilterBar()" title="Chiudi">✕</button>
    <form method="GET" action="">
        <div class="filter-group">
            <label for="data_inizio">Data Inizio</label>
            <input type="date" id="data_inizio" name="data_inizio" value="<?php echo htmlspecialchars($filtro_data_inizio ?? ''); ?>">
        </div>

        <div class="filter-group">
            <label for="data_fine">Data Fine</label>
            <input type="date" id="data_fine" name="data_fine" value="<?php echo htmlspecialchars($filtro_data_fine ?? ''); ?>">
        </div>

        <div class="filter-group">
            <label for="sun_phase">Alba/Tramonto</label>
            <select id="sun_phase" name="sun_phase">
                <option value="all" <?php echo $filtro_sun_phase === 'all' ? 'selected' : ''; ?>>Tutti</option>
                <option value="1" <?php echo $filtro_sun_phase === '1' ? 'selected' : ''; ?>>Alba 🌄</option>
                <option value="2" <?php echo $filtro_sun_phase === '2' ? 'selected' : ''; ?>>Tramonto 🌇</option>
                <option value="day" <?php echo $filtro_sun_phase === 'day' ? 'selected' : ''; ?>>
                    Pieno giorno ☀️
                </option>
                <option value="null" <?php echo $filtro_sun_phase === 'null' ? 'selected' : ''; ?>>Nessuno</option>
            </select>
        </div>

        <div class="filter-group">
            <label for="altro">Altro</label>
            <select id="altro" name="altro">
                <option value="all" <?php echo $filtro_altro === 'all' ? 'selected' : ''; ?>>Tutti</option>
                <?php foreach ($valori_altro as $valore): ?>
                    <option value="<?php echo htmlspecialchars($valore); ?>" <?php echo $filtro_altro == $valore ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($valore); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label for="sequenza">Sequenza (≥N img img)</label>
            <input type="number" id="sequenza" name="sequenza" min="0" max="100" value="<?php echo htmlspecialchars($filtro_sequenza); ?>" placeholder="0 = off">
        </div>

        <div class="filter-actions">
            <button type="submit" class="filter-btn filter-btn-apply">Applica Filtri</button>
        </div>
    </form>
</div>

<main>
   <div class="gallery-header-container"> 
        <h2 class="gallery-title">Diario del cielo</h2> 
   </div>
</main>

<!-- Gestione messaggi di stato -->
<?php if ($errore_messaggio !== null): ?>
    <!-- Errore generico -->
    <div class="status-message error">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <div><strong>Errore:</strong> <?php echo htmlspecialchars($errore_messaggio); ?></div>
    </div>

<?php elseif (empty($records)): ?>
    <!-- Nessuna immagine trovata -->
    <div class="status-message">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <circle cx="8.5" cy="8.5" r="1.5"></circle>
            <polyline points="21 15 16 10 5 21"></polyline>
        </svg>
        <div>Nessuna immagine trovata</div>
    </div>

<?php else: ?>
    <!-- Galleria normale -->
    <div class="gallery-container">
        <div class="gallery">
            <?php 
            foreach($records as $index => $item): 
                $temp = isset($item['temp']) ? (float)$item['temp'] : null;
                $tempDisplay = ($temp !== null) ? number_format($temp, 1) . '°C' : 'N/D';
                $tempClass = getTempColorClass($temp); 

                $dataOraCompleta = isset($item['data_ora']) ? $item['data_ora'] : 'Data N/D';
                $oraSolo = substr($dataOraCompleta, -5); 
                $dataSolo = substr($dataOraCompleta, 0, 10); 
            ?>
                <div class="thumb">
                    <img src="<?php echo htmlspecialchars($item['src'] . '?t=' . time()); ?>" 
                         alt="Immagine webcam" 
                         onclick="openLightbox(<?php echo $index; ?>)"
                    >
                    
                    <span class="overlay-mini <?php echo $tempClass; ?>">
                        <span class="temp-line">
                            <?php echo $tempDisplay; ?>
                        </span>
                        
                        <span class="ora-line">
                             <svg class="icon icon-outline" viewBox="0 0 24 24" style="vertical-align: middle; width: 1.2em; height: 1.2em; fill: none; stroke: currentColor; stroke-width: 2;">
                                <circle cx="12" cy="12" r="9" />
                                <line x1="12" y1="12" x2="12" y2="7" stroke-linecap="round"></line>
                                <line x1="12" y1="12" x2="15" y2="12" stroke-linecap="round"></line>
                            </svg> 
                            <?php echo $dataSolo . ' ' . $oraSolo; ?>
                        </span>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- ========== LIGHTBOX (UNA SOLA VERSIONE) ========== -->
<div class="lightbox" id="lightbox">
    <button id="close-btn" class="lightbox-close" aria-label="Chiudi">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="red">
            <path d="M18 6L6 18M6 6l12 12" stroke="red" stroke-width="5" stroke-linecap="round" />
        </svg>
    </button>

    <div class="lightbox-content">
        <img id="lightbox-img" src="" alt="Immagine ingrandita">
        <div id="lightbox-info" class="lightbox-info"></div>
    </div>
    
    <button class="nav-btn prev" aria-label="Immagine precedente">
        <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="red" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6" />
        </svg>
    </button>
    
    <button class="nav-btn next" aria-label="Immagine successiva">
        <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="red" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="9 18 15 12 9 6" />
        </svg>
    </button>
</div>

<!-- ========== JAVASCRIPT ========== -->
<script>
// Passa i dati al JS
window.images = <?php echo json_encode($records); ?>;

// Toggle barra filtri
function toggleFilterBar() {
    const filterBar = document.getElementById('filterBar');
    filterBar.classList.toggle('active');
}

// Chiudi barra filtri e ricarica pagina senza parametri
function closeFilterBar() {
    window.location.href = window.location.pathname;
}
</script>

<script>
(function() {
    'use strict';
    
    let currentIndex = 0;

    // ========== UTILITY FUNCTIONS ==========
    function isFiniteNumber(n) { 
        return typeof n === 'number' && isFinite(n); 
    }

    function numOrNull(v) {
        return (v === null || v === '' || !isFinite(+v)) ? null : (+v);
    }

    function get(obj, key) {
        return (obj && obj[key] !== null) ? obj[key] : null;
    }

    function getStr(obj, key) {
        var v = get(obj, key);
        return (v === null) ? '' : String(v);
    }

    function dirTesto(v) {
        if (v === null) return '--';
        var deg = +v;
        if (isFinite(deg)) {
            var dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
            var i = Math.round((deg % 360) / 22.5) % 16;
            return dirs[i < 0 ? i + 16 : i];
        }
        return String(v);
    }

    function cropImageBottom(src, cropBottomPx, cb) {
        var tempImg = new Image();
        tempImg.onload = function () {
            try {
                var w = tempImg.width;
                var h = Math.max(1, tempImg.height - cropBottomPx);
                var canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(tempImg, 0, 0, w, h, 0, 0, w, h);
                cb(canvas.toDataURL());
            } catch (e) {
                cb(src);
            }
        };
        tempImg.onerror = function () { cb(src); };
        tempImg.src = src;
    }

    function buildInfoText(record) {
        var d = record.data_ora || 'N/A';
        var t = parseFloat(record.temp);
        var tTxt = isFinite(t) ? Math.round(t) + '°C' : 'N/A';
        var hr = parseFloat(record.hr);
        var hTxt = isFinite(hr) ? Math.round(hr) + '%' : 'N/A';
        var p = parseFloat(record.p_hpa);
        var pTxt = isFinite(p) ? Math.round(p) + ' hPa' : 'N/A';
        var windKmh = parseFloat(record.wind_kmh);
        var wTxt = isFinite(windKmh) ? windKmh + ' km/h' : 'N/A';
        var dirGradi = parseFloat(record.dir_text);
        var dTxt = isFinite(dirGradi) ? dirTesto(dirGradi) : record.dir_text || 'N/A';

        var sunPhase = '';
        if (record.alba_tramonto) {
            var flag = parseInt(record.alba_tramonto);
            if (flag === 1) {
                sunPhase = ' | Alba';
            } else if (flag === 2) {
                sunPhase = ' | Tramonto';
            }
        }

        return d + ' | T ' + tTxt + ' | UR ' + hTxt + ' | ' + pTxt + ' | Vento ' + wTxt + ', ' + dTxt + sunPhase;
    }

    // ========== LIGHTBOX FUNCTIONS ==========
    function openLightbox(index) {
        if (!window.images || window.images.length === 0) return;

        if (index < 0) index = 0;
        if (index > window.images.length - 1) {
            index = window.images.length - 1;
        }

        currentIndex = index;

        const lightbox = document.getElementById('lightbox');
        const img = document.getElementById('lightbox-img');
        const info = document.getElementById('lightbox-info');

        const current = window.images[currentIndex];
        if (!current) return;

        info.innerHTML = buildInfoText(current);

        cropImageBottom(current.src, 80, function (croppedSrc) {
            img.src = croppedSrc;
        });

        lightbox.classList.add('active');
        updateNavButtons();
    }

    function closeLightbox() {
        document.getElementById('lightbox').classList.remove('active');
    }

    function prevImage(event) {
        if (event) event.stopPropagation();
        // prev = vai indietro nel tempo = indice piÃƒÂ¹ alto
        if (currentIndex < window.images.length - 1) {
            openLightbox(currentIndex + 1);
        }
    }

    function nextImage(event) {
        if (event) event.stopPropagation();
        // next = vai avanti nel tempo = indice piÃƒÂ¹ basso
        if (currentIndex > 0) {
            openLightbox(currentIndex - 1);
        }
    }

    function updateNavButtons() {
        const prevBtn = document.querySelector('.nav-btn.prev');
        const nextBtn = document.querySelector('.nav-btn.next');
        
        // prev disabilitato quando sei all'ultima (piÃƒÂ¹ vecchia)
        if (prevBtn) prevBtn.disabled = (currentIndex === window.images.length - 1);
        // next disabilitato quando sei alla prima (piÃƒÂ¹ recente)
        if (nextBtn) nextBtn.disabled = (currentIndex === 0);
    }

    // ========== EVENT LISTENERS ==========
    document.addEventListener('DOMContentLoaded', function() {
        // Close button
        const closeBtn = document.getElementById('close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeLightbox);
        }
        
        // Navigation buttons
        const prevBtn = document.querySelector('.nav-btn.prev');
        const nextBtn = document.querySelector('.nav-btn.next');
        if (prevBtn) prevBtn.addEventListener('click', prevImage);
        if (nextBtn) nextBtn.addEventListener('click', nextImage);
    });

    // Keyboard navigation
    document.addEventListener('keydown', function (event) {
        var lb = document.getElementById('lightbox');
        if (!lb || !lb.classList.contains('active')) return;

        var key = event.key || event.code;

        if (key === 'Escape' || key === 'Esc') {
            closeLightbox();
            return;
        }

        // ArrowLeft = indietro nel tempo = indice piÃƒÂ¹ alto
        if (key === 'ArrowLeft') {
            if (currentIndex < window.images.length - 1) {
                openLightbox(currentIndex + 1);
            }
        }

        // ArrowRight = avanti nel tempo = indice piÃƒÂ¹ basso
        if (key === 'ArrowRight') {
            if (currentIndex > 0) {
                openLightbox(currentIndex - 1);
            }
        }
    });

    // Touch swipe
    var lightbox = document.getElementById('lightbox');
    if (lightbox) {
        var touchStartX = 0;
        var touchEndX = 0;

        lightbox.addEventListener('touchstart', function (e) {
            touchStartX = e.changedTouches[0].screenX;
        });

        lightbox.addEventListener('touchend', function (e) {
            touchEndX = e.changedTouches[0].screenX;
            var threshold = 50;
            
            // swipe left = indietro nel tempo
            if (touchEndX < touchStartX - threshold) {
                if (currentIndex < window.images.length - 1) {
                    openLightbox(currentIndex + 1);
                }
            } 
            // swipe right = avanti nel tempo
            else if (touchEndX > touchStartX + threshold) {
                if (currentIndex > 0) {
                    openLightbox(currentIndex - 1);
                }
            }
        });
    }

    // ========== GLOBAL EXPORTS ==========
    window.openLightbox = openLightbox;
    window.closeLightbox = closeLightbox;
    window.prevImage = prevImage;
    window.nextImage = nextImage;
})();
</script>

</body>
</html>