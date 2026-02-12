<?php

/**
 * aggiorna_galleria.php
 * 
 * SCOPO PRINCIPALE:
 *   - Leggere tutte le immagini dalla cartella
 *   - Recuperare i metadati dal database
 *   - Restituire UN SOLO JSON con DUE array:
 *     1) "full"     -> array leggero (solo src + data_ora) per il TIME-LAPSE veloce
 *     2) "gallery"  -> array completo (con meteo, alba_tramonto, ecc.) + fullIndex per le miniature
 *
 * REGOLE PER LA GALLERY (miniature visibili):
 *   - Obiettivo: una foto rappresentativa ogni ~20 minuti
 *   - Slot target: minuti 00, 20, 40 di ogni ora
 *   - Priorita 1: se esiste foto ESATTAMENTE a xx:00 / xx:20 / xx:40 -> quella
 *   - Priorita 2: altrimenti, tra tutte le foto entro ±3 minuti -> quella piu vicina (minima distanza temporale)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Non mostrare errori in JSON

require_once __DIR__ . '/../envelop.php';
require_once __DIR__ . '/env_tables_helper.php';
require_once __DIR__ . '/datetime_helper.php';  

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['error' => 'Connessione PDO non disponibile']);
    exit;
}

$table_name = table_name('DB_immagini_36h');

// Percorsi
require_once __DIR__ . '/camera_config.php';
$directory = $CAMERA_CONFIG['directory'];
$webPath   = '/FoscamCamera_E8ABFAA799FE/snap/';

// Verifica cartella
if (!is_dir($directory) || !is_readable($directory)) {
    echo json_encode(['full' => [], 'gallery' => [], 'stats' => ['error' => 'Directory non trovata']]);
    exit;
}

// Raccolta file
$images = glob($directory . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
if (!$images || count($images) === 0) {
    echo json_encode(['full' => [], 'gallery' => [], 'stats' => ['error' => 'Nessuna immagine trovata']]);
    exit;
}

// Ordine decrescente per nome file (piu recente prima)
rsort($images, SORT_STRING);

// Statement per metadati
$sql = "SELECT * FROM {$table_name} WHERE FILE = :file LIMIT 1";
$stmt = $pdo->prepare($sql);

// Costruzione array completo con tutti i dati
$records = [];

foreach ($images as $imagePath) {
    $filename = basename($imagePath);
    
    $stmt->execute([':file' => $filename]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    
    $records[] = buildRecord($webPath, $filename, $row);
}

// Funzione buildRecord           
function buildRecord(string $publicBasePath, string $filename, $row): array
{
    $data_ora = null;
    $temp     = null;
    $hr       = null;
    $p_hpa    = null;
    $windKmh  = null;
    $windMs   = null;
    $dir      = null;

    if (is_array($row)) {
        if (!empty($row['DATA_ORA'])) {
            try {
                $data_ora = (new DateTime((string)$row['DATA_ORA']))->format('d/m/Y H:i');
            } catch (Throwable $ignored) {}
        }

        $temp  = (isset($row['Temp'])  && is_numeric($row['Temp']))  ? (float)$row['Temp']  : null;
        $hr    = (isset($row['HR'])    && is_numeric($row['HR']))    ? (float)$row['HR']    : null;
        $p_hpa = (isset($row['P_hPa']) && is_numeric($row['P_hPa'])) ? (float)$row['P_hPa'] : null;

        if (isset($row['vento_kmh']) && is_numeric($row['vento_kmh'])) {
            $windKmh = (float)$row['vento_kmh'];
            $windMs  = $windKmh / 3.6;
        }

        $dir = isset($row['Dir_text']) ? (string)$row['Dir_text'] : null;
    }

    return [
        'src'       => rtrim($publicBasePath, '/') . '/' . ltrim($filename, '/'),
        'file'      => $filename,
        'data_ora'  => $data_ora,
        'temp'      => $temp,
        'hr'        => $hr,
        'p_hpa'     => $p_hpa,
        'vento'     => $windMs,
        'wind_ms'   => $windMs,
        'wind_kmh'  => $windKmh,
        'dir'       => $dir,
        'dir_text'  => $dir,
    ];
}

// ============================================================================
// SELEZIONE GALLERY
// ============================================================================
// $records e' gia' in ordine decrescente (piu recente prima)
$sorted_desc = $records;

$gallery = [];

if (!empty($sorted_desc)) {
    $foto_piu_recente = $sorted_desc[0];
    
    // Parsing data piu recente
    $ultima_dt = DateTime::createFromFormat('d/m/Y H:i', $foto_piu_recente['data_ora'] ?? '');
    if ($ultima_dt === false) {
        $ultima_dt = new DateTime(get_now('Y-m-d H:i:s'));
    }
    
    // Slot iniziale
    $minuto = (int)$ultima_dt->format('i');
    $target_min = (int)(floor($minuto / 20) * 20);
    
    $current_target = clone $ultima_dt;
    $current_target->setTime((int)$ultima_dt->format('H'), $target_min, 0, 0);
    
    // Limite (36 ore fa)
    $limite_inferiore = clone $ultima_dt;
    $limite_inferiore->modify('-36 hours');
    $limite_ts = (int)floor($limite_inferiore->getTimestamp());
    
    $iter = 0;
    $max_iter = 200; // sicurezza anti-loop infinito (36h * 3 foto/ora = 108 slot)
    
    while ($current_target->getTimestamp() >= $limite_ts && $iter < $max_iter) {
        $iter++;
        $target_ts = (int)floor($current_target->getTimestamp());
        
        $trovata_esatta = false;
        $migliore_foto = null;
        $migliore_dist = 999999;
        
        foreach ($sorted_desc as $idx => $foto) {
            $dt_foto = DateTime::createFromFormat('d/m/Y H:i', $foto['data_ora'] ?? '');
            if ($dt_foto === false) continue;
            
            $ts_foto = (int)floor($dt_foto->getTimestamp());
            $distanza = abs($ts_foto - $target_ts);
            
            // Tolleranza: +/- 4 minuti = 240 secondi
            if ($distanza <= 240) {
                // Cerchiamo la foto piu vicina
                if ($distanza < $migliore_dist) {
                    $migliore_dist = $distanza;
                    $migliore_foto = $foto;
                }
                
                // Priorita: foto esatta al minuto 00/20/40
                $min_foto = (int)$dt_foto->format('i');
                if (in_array($min_foto, [0, 20, 40]) && $distanza <= 60) {
                    $trovata_esatta = true;
                    $full_idx = array_search($foto['src'], array_column($records, 'src'));
                    if ($full_idx !== false) {
                        $gallery[] = $foto + ['fullIndex' => $full_idx];
                    }
                    break; // esci dal foreach
                }
            }
        }
        
        // Se non trovata esatta, usa la migliore entro tolleranza
        if (!$trovata_esatta && $migliore_foto !== null) {
            $full_idx = array_search($migliore_foto['src'], array_column($records, 'src'));
            if ($full_idx !== false) {
                $gallery[] = $migliore_foto + ['fullIndex' => $full_idx];
            }
        }
        
        $current_target->modify('-20 minutes');
    }
}

// Ordine decrescente finale (piu recente prima)
usort($gallery, function($a, $b) {
    return strcmp($b['data_ora'], $a['data_ora']);
});

// Array full leggero (solo src + data_ora per time-lapse)
$full = array_map(function($r) {
    return [
        'src'     => $r['src'],
        'data_ora' => $r['data_ora']
    ];
}, $records);

// Output JSON finale
echo json_encode([
    'full'    => $full,
    'gallery' => $gallery,
    'stats'   => [
        'total_full'     => count($full),
        'gallery_count'  => count($gallery),
        'generated_at'   => date('c'),
        'note'           => 'Gallery: foto ogni ~20 min con +/-3 min tolleranza'
    ]
], JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
?>