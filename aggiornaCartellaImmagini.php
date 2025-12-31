<?php
/**
 * ============================================================================
 *  getImageDataFromFolder()
 * ============================================================================
 *
 *  Scopo
 *  -----
 *  Costruisce, su richiesta, una fotografia istantanea dello stato
 *  filesystem + database per una cartella di immagini.
 *
 *  La funzione:
 *  - legge i file immagine presenti nella directory indicata
 *  - recupera i metadati associati dal database
 *  - combina filesystem e DB in un array coerente per il frontend
 *
 *  Ciclo di vita
 *  -------------
 *  - La funzione NON mantiene stato
 *  - L'array restituito vive solo per la durata della request PHP
 *  - Alla request successiva, filesystem e DB vengono riletti da zero
 *  - Non esiste cache interna o persistenza tra richieste
 *
 *  Utilizzo tipico
 *  ---------------
 *  - Preparazione dati per rendering iniziale galleria
 *  - Fornitura dataset JSON/JS per lightbox o frontend dinamico
 *
 *  Nota
 *  ----
 *  Questa funzione NON:
 *  - aggiorna il database
 *  - gestisce eventi runtime (scroll, click, navigazione)
 *  - mantiene uno stato applicativo
 *

 *
 *  Output (array associativo)
 *  --------------------------
 *  [
 *    'error'      => string|null,       // eventuale messaggio di errore
 *    'mainImage'  => string,            // src della prima immagine (se esiste)
 *    'count'      => int,               // numero record inclusi
 *    'records'    => [
 *      [
 *        'src'           => string,     // path completo al file immagine
 *        'file'          => string,     // solo nome file (basename)
 *        'data_ora'      => string|null,// es. "dd/mm/yyyy HH:MM"
 *        'temp'          => float|null, // Â°C (come da DB)
 *        'hr'            => float|null, // % umiditÃ 
 *        'p_hpa'         => float|null, // pressione hPa
 *        'vento'         => float|null, // m/s   (calcolato da km/h se serve)
 *        'wind_ms'       => float|null, // m/s   (alias esplicito)
 *        'wind_kmh'      => float|null, // km/h  (come da DB se presente)
 *        'dir'           => float|string|null, // gradi o testo, dipende DB
 *        'dir_text'      => string|null // testo direzione (se disponibile)
 *      ],
 *      ...
 *    ]
 *  ]
 *
 *  NOTE sui campi vento/dir
 *  ------------------------
 *  - Se il DB fornisce solo km/h, convertiamo anche in m/s:
 *      vento (m/s) = vento_kmh / 3.6
 *  - Se il DB fornisce solo testo direzione (es. "NE"), lo mettiamo in 'dir_text'
 *    e lasciamo 'dir' invariato (null o testo). Se nel DB ci sono gradi, li
 *    passiamo come float in 'dir'.
 *
 *  Sicurezza
 *  ---------
 *  - Il nome tabella viene whitelistanato: sono consentiti solo [A-Za-z0-9_]
 *    per prevenire injection via identificatore.
 *
 *  @param PDO    $pdo         Connessione PDO giÃ  aperta
 *  @param string $directory   Percorso cartella immagini (con o senza / finale)
 *  @param string $tableName   Nome tabella con i metadati (colonna FILE obbligatoria)
 *  @param array  $opts        Opzioni:
 *                             - 'limit' (int)         q.tÃ  max immagini da restituire (default 200)
 *                             - 'extensions' (array)  estensioni ammesse (default jpg,jpeg,png,gif)
 *                             - 'order' ('desc'|'asc') ordinamento per nome file (default 'desc')
 *
 *  @return array Vedi struttura "Output" sopra
 */
function getImageDataFromFolder(PDO $pdo, string $directory, string $tableName, array $opts = []): array
{
    // ---------------------------
    // 1) Normalizzazione/Opzioni
    // ---------------------------
    $limit      = isset($opts['limit']) && is_int($opts['limit']) ? max(1, $opts['limit']) : 200;
    $extensions = isset($opts['extensions']) && is_array($opts['extensions'])
                    ? $opts['extensions']
                    : ['jpg','jpeg','png','gif'];
    $order      = (isset($opts['order']) && strtolower($opts['order']) === 'asc') ? 'asc' : 'desc';

    // Whitelist per nome tabella (evita injection su identificatori)
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
        return [
            'error'     => "Nome tabella non valido.",
            'mainImage' => '',
            'count'     => 0,
            'records'   => []
        ];
    }

    // Normalizza directory: assicura trailing slash
    $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    // ---------------------------
    // 2) Validazione cartella
    // ---------------------------
    if (!is_dir($directory)) {
        return [
            'error'     => "La cartella '$directory' non esiste.",
            'mainImage' => '',
            'count'     => 0,
            'records'   => []
        ];
    }

    // ---------------------------
    // 3) Raccolta file immagine
    // ---------------------------
    // Costruisce un pattern GLOB con estensioni ammesse
    $patternParts = [];
    foreach ($extensions as $ext) {
        $patternParts[] = '*.' . ltrim(strtolower($ext), '.');
    }
    $pattern = '{' . implode(',', $patternParts) . '}';

    $files = glob($directory . $pattern, GLOB_BRACE);

    if (!$files || count($files) === 0) {
        return [
            'error'     => "Nessuna immagine trovata nella cartella '$directory'.",
            'mainImage' => '',
            'count'     => 0,
            'records'   => []
        ];
    }

    // Ordina per nome file (desc/asc) e applica limit
    // Nota: rsort() fa reverse sort; sort() fa ascendente
    if ($order === 'desc') {
        rsort($files, SORT_STRING);
    } else {
        sort($files, SORT_STRING);
    }
    if (count($files) > $limit) {
        $files = array_slice($files, 0, $limit);
    }

    // ---------------------------
    // 4) Preparazione batch query
    // ---------------------------
    // Estrae solo i basename (per join col DB sulla colonna FILE)
    $basenames = array_map('basename', $files);

    // Costruisce placeholders (?, ?, ?, ...)
    $placeholders = implode(',', array_fill(0, count($basenames), '?'));

    // Query: recupera i metadati per TUTTI i file in un colpo solo
    // - Colonna FILE obbligatoria
    // - Le altre colonne sono opzionali: usa COALESCE o leggi come sono
    $sql = "SELECT *
            FROM {$tableName}
            WHERE FILE IN ($placeholders)";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($basenames);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [
            'error'     => "Errore nella query SQL: " . $e->getMessage(),
            'mainImage' => '',
            'count'     => 0,
            'records'   => []
        ];
    }

    // Indicizza righe per FILE per lookup rapido
    $byFile = [];
    foreach ($rows as $r) {
        if (!isset($r['FILE'])) continue;
        $byFile[$r['FILE']] = $r;
    }

    // ---------------------------
    // 5) Costruzione records
    // ---------------------------
    $records = [];
    foreach ($files as $path) {
        $file = basename($path);
        $row  = $byFile[$file] ?? null;

        // --- 1. LETTURA DEI CAMPI DAL DB (INCLUSA sun_phase) ---
        $data_ora = $row['DATA_ORA'] ?? null;
        $temp     = isset($row['Temp'])     ? (float)$row['Temp']     : null;
        $hr       = isset($row['HR'])       ? (float)$row['HR']       : null;
        $p_hpa    = isset($row['P_hPa'])    ? (float)$row['P_hPa']    : null;
        $windKmh  = isset($row['vento_kmh']) ? (float)$row['vento_kmh'] : null;
        $dirText  = $row['Dir_text'] ?? null;
        
        // NUOVA RIGA: Legge il valore numerico grezzo (1, 2, o null)
        $sunPhaseRaw = isset($row['sun_phase']) ? (int)$row['sun_phase'] : null;


        // --- 2. CONVERSIONE E LOGICA ---
        $windMs   = ($windKmh !== null) ? ($windKmh / 3.6) : null;
        $dir      = $dirText; 
        
        // NUOVA LOGICA: Conversione da numero a testo
        $sunPhaseText = 'N/D';
        if ($sunPhaseRaw === 1) {
            $sunPhaseText = 'Alba ðŸŒ…';
        } elseif ($sunPhaseRaw === 2) {
            $sunPhaseText = 'Tramonto ðŸŒ‡';
        }


        // --- 3. CREAZIONE DEL RECORD (INCLUSO sun_phase) ---
        $records[] = [
            'src'       => $path,      // path completo sul filesystem/virtual host
            'file'      => $file,      // nome file (basename)
            'data_ora'  => $data_ora,  // "dd/mm/yyyy HH:MM" come da tua console
            'temp'      => $temp,      // Â°C
            'hr'        => $hr,        // %
            'p_hpa'     => $p_hpa,     // hPa

            // Vento
            'vento'     => $windMs,
            'wind_ms'   => $windMs,
            'wind_kmh'  => $windKmh,

            // Direzione
            'dir'       => $dir,
            'dir_text'  => $dirText,
            
            // NUOVO CAMPO: Fase del Sole (testo)
            'sun_phase' => $sunPhaseText 
        ];
    }

    // ---------------------------
    // 6) Risposta finale
    // ---------------------------
    return [
        'error'     => null,
        'mainImage' => $records[0]['src'] ?? '',
        'count'     => count($records),
        'records'   => $records
    ];
}
?>