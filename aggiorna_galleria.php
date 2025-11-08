<?php
declare(strict_types=1);

/**
 * FILE: aggiorna_galleria.php
 * SCOPO: Espone in JSON la lista delle immagini (ultime 36h) con i metadati letti dal DB.
 *
 * COMPORTAMENTO IN BREVE
 *  1) Carica configurazioni e dipendenze (PDO da envelop.php, mapping tabelle da env_tables_helper.php).
 *  2) Legge i file immagine da una cartella sul filesystem.
 *  3) Per ogni file, prova a recuperare una riga di metadati dal DB (al più una, con LIMIT 1).
 *  4) Costruisce un array di record “puliti” per il frontend e lo stampa in JSON.
 *
 * NOTE DI PROGETTO
 *  - L’endpoint deve *sempre* rispondere con JSON valido e *solo* JSON (niente echo, HTML o notice).
 *  - Tutti i warning sono soppressi lato output (display_errors=0). In caso di problemi gravi → HTTP 500 + JSON {error}.
 *  - Il frontend si aspetta alias specifici per il vento: "vento"/"wind_ms" in m/s e "wind_kmh" in km/h.
 *  - La direzione vento è presente come testo (es. "NE") nel campo "Dir_text".
 *
 * SICUREZZA / MANUTENZIONE
 *  - Binding dei parametri con placeholder nominati (":file") per evitare SQL injection.
 *  - Nessuna assunzione sulla validità del contenuto DB: tutto è validato e/o castato “soft”.
 *  - Se non ci sono immagini, risponde con [] (array vuoto), che il JS deve gestire.
 */

// ─────────────────────────────────────────────────────────────────────────────
// 0) Impostazioni runtime per non “sporcare” il JSON con warning/notice
// ─────────────────────────────────────────────────────────────────────────────
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Consistenti intestazioni HTTP per una API JSON
header('Content-Type: application/json; charset=utf-8');
// Se preferisci evitare cache del browser, lascia questa riga; altrimenti rimuovi/adegua.
// header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// ─────────────────────────────────────────────────────────────────────────────
// 1) Dipendenze & connessione al DB
//    - envelop.php deve definire $pdo (istanza PDO *valida*)
//    - env_tables_helper.php deve esporre table_name('DB_immagini_36h')
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../envelop.php';
require_once __DIR__ . '/env_tables_helper.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['error' => 'Connessione PDO non disponibile']);
    exit;
}

$table_name = table_name('DB_immagini_36h'); // es. "DB_immagini_36h" (eventualmente namespaced)

// ─────────────────────────────────────────────────────────────────────────────
// 2) Percorsi (filesystem vs URL pubblico)
//    - $directory: path reale sul server dove risiedono i file
//    - $webPath:  path pubblico usato dal frontend per <img src="...">
// ─────────────────────────────────────────────────────────────────────────────
$directory = __DIR__ . '/FoscamCamera_E8ABFAA799FE/snap/'; // FS path
$webPath   = '/FoscamCamera_E8ABFAA799FE/snap/';           // URL base

// Verifica base: se la cartella non esiste o non è leggibile, restituisce array vuoto
if (!is_dir($directory) || !is_readable($directory)) {
    echo json_encode([]);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 3) Raccolta immagini dalla cartella
//    - Supporta estensioni comuni (jpg/jpeg/png/gif)
//    - Ordinamento decrescente per nome file (assume naming time-based)
// ─────────────────────────────────────────────────────────────────────────────
$images = glob($directory . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);

if (!$images || count($images) === 0) {
    echo json_encode([]);
    exit;
}

// Se i nomi sono timestamp-like, l'ordinamento string decrescente funziona bene.
// In alternativa, per sicurezza su nomi "misti", si può usare SORT_NATURAL|SORT_FLAG_CASE.
rsort($images, SORT_STRING);

// ─────────────────────────────────────────────────────────────────────────────
// 4) Preparazione statement SQL riusabile
//    - Cerchiamo al massimo una riga per FILE
// ─────────────────────────────────────────────────────────────────────────────
$sql = "SELECT * FROM {$table_name} WHERE FILE = :file LIMIT 1";
try {
    $stmt = $pdo->prepare($sql);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Preparazione statement fallita']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 5) Funzione helper: costruisce un record “pulito” per il frontend
// ─────────────────────────────────────────────────────────────────────────────
/**
 * @param string     $publicBasePath  Base URL per <img>
 * @param string     $filename        Nome file (basename)
 * @param array|null $row             Riga DB associata (o null/false se assente)
 * @return array<string, mixed>
 */
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
        // Data/ora: se presente e parse-abile → "dd/mm/YYYY HH:ii"
        if (!empty($row['DATA_ORA'])) {
            try {
                $data_ora = (new DateTime((string)$row['DATA_ORA']))->format('d/m/Y H:i');
            } catch (Throwable $ignored) {
                $data_ora = null;
            }
        }

        // Cast numerici “soft”: se non numerico → null
        $temp  = (isset($row['Temp'])  && is_numeric($row['Temp']))  ? (float)$row['Temp']  : null;
        $hr    = (isset($row['HR'])    && is_numeric($row['HR']))    ? (float)$row['HR']    : null;
        $p_hpa = (isset($row['P_hPa']) && is_numeric($row['P_hPa'])) ? (float)$row['P_hPa'] : null;

        // Vento: nel DB è in km/h → offriamo anche i m/s
        if (isset($row['vento_kmh']) && is_numeric($row['vento_kmh'])) {
            $windKmh = (float)$row['vento_kmh'];
            $windMs  = $windKmh / 3.6;
        }

        // Direzione: testuale (es. "NE")
        $dir = isset($row['Dir_text']) ? (string)$row['Dir_text'] : null;
    }

    return [
        // Percorso pubblico per <img>
        'src'       => rtrim($publicBasePath, '/') . '/' . ltrim($filename, '/'),

        // Metadati temporali e meteo
        'data_ora'  => $data_ora,
        'temp'      => $temp,
        'hr'        => $hr,
        'p_hpa'     => $p_hpa,

        // Vento: alias coerenti con il JS del frontend
        'vento'     => $windMs,   // m/s (alias storico)
        'wind_ms'   => $windMs,   // m/s
        'wind_kmh'  => $windKmh,  // km/h

        // Direzione (testo, es. "NE")
        'dir'       => $dir,
        'dir_text'  => $dir,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// 6) Loop: per ogni immagine, lookup DB e costruzione record
// ─────────────────────────────────────────────────────────────────────────────
$records = [];

foreach ($images as $imagePath) {
    $filename = basename($imagePath);

    try {
        // Binding con placeholder nominato (includere i due punti è più robusto)
        $stmt->execute([':file' => $filename]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        // In caso di errore query per *una* immagine, si salta quel record
        // (meglio degradare con parziale che rompere tutta la risposta)
        $row = null;
    }

    $records[] = buildRecord($webPath, $filename, $row);
}

// ─────────────────────────────────────────────────────────────────────────────
// 7) Output JSON pulito
// ─────────────────────────────────────────────────────────────────────────────
echo json_encode(
    $records,
    JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
);
