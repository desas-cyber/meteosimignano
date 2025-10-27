<?php
/**
 * Endpoint: aggiorna_galleria.php (versione lineare e sicura)
 *
 * - Legge le immagini dalla cartella
 * - Per ognuna cerca (al massimo) una riga in DB con i metadati
 * - Costruisce un array di record per il frontend
 * - Restituisce SOLO JSON pulito (niente echo/notice/HTML)
 */

/* 0) Impostazioni per non “sporcare” il JSON con warning/notice ------------- */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

/* 1) Connessione al database ------------------------------------------------ */
require_once __DIR__ . '/../envelop.php'; // deve definire $pdo (istanza PDO)
if (!isset($pdo) || !($pdo instanceof PDO)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['error' => 'Connessione PDO non disponibile']);
    exit;
}

/* 2) Percorsi (filesystem vs URL pubblico) ---------------------------------- */
$directory = __DIR__ . '/FoscamCamera_E8ABFAA799FE/snap/'; // FS path
$webPath   = '/FoscamCamera_E8ABFAA799FE/snap/';           // URL base

/* 3) Legge le immagini presenti nella cartella ------------------------------ */
$images = glob($directory . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
if (!$images || count($images) === 0) {
    // Nessuna immagine → rispondi con array vuoto (il JS lo gestisce)
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([]);
    exit;
}
rsort($images, SORT_STRING); // Ordina dalla più recente per nome file

/* 4) Prepara la query (riusiamo lo statement per evitare overhead) ---------- */
$sql  = "SELECT * FROM DB_immagini_36h WHERE FILE = :file LIMIT 1";
$stmt = $pdo->prepare($sql);

/* 5) Costruisce l'array record in modo sicuro (no warning se riga assente) -- */
$records = [];

foreach ($images as $image) {
    $nome_file = basename($image);

    // Esegui lookup DB per questa immagine
    $stmt->execute(['file' => $nome_file]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC); // può essere array oppure false

    // Protezione: se non esiste riga in DB, $row === false
    $data_ora = null;
    $temp     = null;
    $hr       = null;
    $p_hpa    = null;
    $windKmh  = null;
    $windMs   = null;
    $dir      = null;

    if (is_array($row)) {
        // Data/ora formattata (se presente in DB)
        if (!empty($row['DATA_ORA'])) {
            try {
                $data_ora = (new DateTime($row['DATA_ORA']))->format('d/m/Y H:i');
            } catch (Throwable $e) {
                // se stringa non parse-abile, la lasciamo null
            }
        }

        // Cast numerici “soft” (null se vuoti/non numerici)
        $temp  = (isset($row['Temp'])   && is_numeric($row['Temp']))   ? (float)$row['Temp']   : null;
        $hr    = (isset($row['HR'])     && is_numeric($row['HR']))     ? (float)$row['HR']     : null;
        $p_hpa = (isset($row['P_hPa'])  && is_numeric($row['P_hPa']))  ? (float)$row['P_hPa']  : null;

        // Vento: nel tuo DB è in km/h → calcoliamo anche i m/s (compat con JS)
        if (isset($row['vento_kmh']) && is_numeric($row['vento_kmh'])) {
            $windKmh = (float)$row['vento_kmh'];
            $windMs  = $windKmh / 3.6;
        }

        // Direzione: nel tuo DB hai "Dir_text" (es. "NE")
        $dir = isset($row['Dir_text']) ? (string)$row['Dir_text'] : null;
    }

    // Costruzione record per il frontend
    $records[] = [
        'src'       => $webPath . $nome_file, // URL pubblico per <img>
        'data_ora'  => $data_ora,
        'temp'      => $temp,
        'hr'        => $hr,
        'p_hpa'     => $p_hpa,

        // Vento: alias coerenti con il JS
        'vento'     => $windMs,   // m/s (quello che il tuo JS si aspetta)
        'wind_ms'   => $windMs,   // m/s
        'wind_kmh'  => $windKmh,  // km/h

        // Direzione
        'dir'       => $dir,      // testuale (NE/NW/…)
        'dir_text'  => $dir
    ];
}

/* 6) Output JSON pulito ----------------------------------------------------- */
header('Content-Type: application/json; charset=utf-8');
echo json_encode($records, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
