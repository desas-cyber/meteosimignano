<?php
/**
 * ============================================================================
 * calcola_giornaliero.php
 * ============================================================================
 *
 * SCOPO:
 *   Consolida i dati minutali di dati_meteo_simignano in una riga giornaliera
 *   nella tabella dati_meteo_giornaliero_simignano.
 *
 * CAMPI CALCOLATI:
 *   - Temperatura  : media, max abs (+ora), min abs (+ora)
 *   - Pressione    : media, max, min
 *   - Vento (dom)  : direzione dominante (moda 16 settori), percentuale
 *                    dei record nel settore modale, velocita media in quel settore
 *   - Radianza     : percentuale cumulato giornaliero sul teorico (radianza_int_whm2)
 *
 * USO CLI:
 *   php calcola_giornaliero.php                        # ieri (default)
 *   php calcola_giornaliero.php --data=2025-01-15      # giorno singolo
 *   php calcola_giornaliero.php --da=2025-01-01 --a=2025-01-31  # backfill
 *
 * CRON (ogni notte alle 00:05 per consolidare il giorno precedente):
 *   5 0 * * * /usr/bin/php /path/to/calcola_giornaliero.php >> /path/to/giornaliero.log 2>&1
 *
 * DIPENDENZE:
 *   - envelop.php           : connessione PDO ($pdo)
 *   - env_tables_helper.php : table_name()
 *   - datetime_helper.php   : get_now()
 *
 * NOTE - DIREZIONE DOMINANTE (moda su 16 settori):
 *   Ogni record con vento > 0 vota il proprio settore della rosa dei venti
 *   (16 settori da 22.5 deg: N, NNE, NE, ... NNO).
 *   Il settore con piu voti e' la direzione dominante.
 *   dirDomPerc indica quanto e' netta la dominanza (% record nel settore modale).
 *   Vantaggio: robusto in caso di vento bimodale (due dir. opposte non si cancellano).

 * ============================================================================
 */

define('IS_CLI', PHP_SAPI === 'cli');

// ============================================================================
// BOOTSTRAP
// ============================================================================

$base_dir = __DIR__;
require_once $base_dir . '/../envelop.php';
require_once $base_dir . '/env_tables_helper.php';
require_once $base_dir . '/datetime_helper.php';
require_once $base_dir . '/astro_helper.php';   // per calculateSolarRadiationTheoretical()

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fatalError('Connessione PDO non disponibile.');
}

// ============================================================================
// CONFIGURAZIONE
// ============================================================================

$TABLE_SRC  = table_name('dati_meteo_simignano');
$TABLE_DEST = table_name('dati_meteo_giornaliero_simignano');

// Numero minimo di record per considerare il giorno elaborabile
define('MIN_RECORD_VALIDI', 100);

// Soglia minima di completezza per considerare valida una metrica (75%)
// Se COUNT(metrica) < soglia -> il campo viene salvato come 9999 (dato mancante)
// La soglia e' calcolata dinamicamente su record_attesi_giorno, derivato dalla
// mediana degli intervalli tra record degli ultimi 7 giorni: funziona quindi
// per qualsiasi frequenza di campionamento (1 min, 2 min, 5 min, ...).
define('SOGLIA_COMPLETEZZA', 0.75);

// Fallback record attesi se non ci sono dati storici sufficienti per la mediana
define('RECORD_ATTESI_FALLBACK', 1440);

// Valore sentinella scritto nel DB quando una metrica non raggiunge la soglia 75%
// Usato invece di NULL per distinguere 'dato non affidabile' da 'dato mai ricevuto'
define('SENTINELLA', 9999);



// ============================================================================
// PARSING ARGOMENTI CLI
// ============================================================================

$giorni_da_calcolare = [];

if (IS_CLI) {
    $opts = getopt('', ['data:', 'da:', 'a:']);

    if (isset($opts['data'])) {
        $giorni_da_calcolare = [validaData($opts['data'])];

    } elseif (isset($opts['da']) && isset($opts['a'])) {
        $da = new DateTime(validaData($opts['da']));
        $a  = new DateTime(validaData($opts['a']));
        $periodo = new DatePeriod($da, new DateInterval('P1D'), $a->modify('+1 day'));
        foreach ($periodo as $giorno) {
            $giorni_da_calcolare[] = $giorno->format('Y-m-d');
        }

    } else {
        $giorni_da_calcolare = [date('Y-m-d', strtotime('-1 day'))];
    }
} else {
    // Da web: legge i parametri GET oppure calcola ieri come default
    // Esempi URL:
    //   ?data=2025-01-15                          -> giorno singolo
    //   ?da=2024-01-01&a=2025-03-03               -> backfill range
    //   (nessun parametro)                        -> ieri

    if (isset($_GET['data'])) {
        $giorni_da_calcolare = [validaData($_GET['data'])];

    } elseif (isset($_GET['da']) && isset($_GET['a'])) {
        $da = new DateTime(validaData($_GET['da']));
        $a  = new DateTime(validaData($_GET['a']));
        $periodo = new DatePeriod($da, new DateInterval('P1D'), $a->modify('+1 day'));
        foreach ($periodo as $giorno) {
            $giorni_da_calcolare[] = $giorno->format('Y-m-d');
        }

    } else {
        $giorni_da_calcolare = [date('Y-m-d', strtotime('-1 day'))];
    }
}


// ============================================================================
// LOOP PRINCIPALE
// ============================================================================

$ts_avvio = microtime(true);

// Header HTML solo da browser
if (!IS_CLI) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
    echo "<title>Calcolo giornaliero meteo</title>";
    echo "<style>body{font-family:monospace;font-size:13px;padding:20px;} .ok{color:green;} .err{color:red;} .skip{color:orange;}</style>";
    echo "</head><body>\n";
    echo "<h3>calcola_giornaliero.php — " . count($giorni_da_calcolare) . " giorni da elaborare</h3>\n";
    echo "<pre>\n";
}
log_msg('=== calcola_giornaliero.php avviato ===');
log_msg('Giorni da calcolare: ' . count($giorni_da_calcolare));

// ============================================================
// DEBUG - rimuovere dopo aver risolto il problema
// ============================================================
log_msg('[DEBUG] PHP version: ' . PHP_VERSION);
log_msg('[DEBUG] TABLE_SRC  : ' . $TABLE_SRC);
log_msg('[DEBUG] TABLE_DEST : ' . $TABLE_DEST);
try {
    $dbname = $pdo->query('SELECT DATABASE()')->fetchColumn();
    log_msg('[DEBUG] Database connesso: ' . $dbname);
    $cnt = $pdo->query("SELECT COUNT(*) FROM {$TABLE_SRC}")->fetchColumn();
    log_msg('[DEBUG] Righe totali in TABLE_SRC: ' . $cnt);
    $minmax = $pdo->query("SELECT MIN(DATE(data_ora)), MAX(DATE(data_ora)) FROM {$TABLE_SRC}")->fetch(PDO::FETCH_NUM);
    log_msg('[DEBUG] Range date in TABLE_SRC: ' . $minmax[0] . ' -> ' . $minmax[1]);
    $campione = $pdo->query("SELECT COUNT(*) FROM {$TABLE_SRC} WHERE DATE(data_ora) = '2024-07-19'")->fetchColumn();
    log_msg('[DEBUG] Righe per 2024-07-19: ' . $campione);
} catch (Exception $e) {
    log_msg('[DEBUG] ERRORE query debug: ' . $e->getMessage());
}
// ============================================================

$ok = 0;
$ko = 0;

foreach ($giorni_da_calcolare as $giorno) {
    try {
        $risultato = consolidaGiorno($pdo, $TABLE_SRC, $TABLE_DEST, $giorno);
        if ($risultato['success']) {
            log_msg(sprintf(
                '[OK] %s - %d record | T %.1f/%.1f/%.1f°C | P %.1f hPa | Vdom %d° (%d%%) %.1f km/h | Rad %s%%',
                $giorno,
                $risultato['n_record'],
                $risultato['temp_min_abs']        ?? 0,
                $risultato['temp_media']          ?? 0,
                $risultato['temp_max_abs']        ?? 0,
                $risultato['press_media']         ?? 0,
                $risultato['vento_dir_dom_deg']   ?? 0,
                $risultato['vento_dir_dom_perc']  ?? 0,
                $risultato['vento_dom_kmh']       ?? 0,
                $risultato['rad_percent_24h'] !== null ? $risultato['rad_percent_24h'] : 'N/A'
            ));
            $ok++;
        } else {
            log_msg("[SKIP] {$giorno} - {$risultato['motivo']}");
        }
    } catch (Throwable $e) {
        log_msg("[ERRORE] {$giorno} - " . $e->getMessage());
        $ko++;
    }
}

$elapsed = round(microtime(true) - $ts_avvio, 2);
log_msg("=== Fine: {$ok} ok, {$ko} errori, {$elapsed}s ===");

if (!IS_CLI) {
    echo "</pre></body></html>\n";
}


// ============================================================================
// FUNZIONE PRINCIPALE DI CONSOLIDAMENTO
// ============================================================================

function consolidaGiorno(PDO $pdo, string $src, string $dest, string $giorno): array
{
    // ----------------------------------------------------------------
    // 1. QUERY BASE: temperatura, pressione, vento aggregati giornalieri
    // ----------------------------------------------------------------
    $sqlBase = "
        SELECT
            COUNT(*)                                AS n_record,

            -- Temperatura
            AVG(temperatura_C)                      AS temp_media,
            MAX(temperatura_C)                      AS temp_max_abs,
            MIN(temperatura_C)                      AS temp_min_abs,

            -- Ora prima occorrenza del massimo di temperatura
            TIME(MIN(
                CASE WHEN temperatura_C = (
                    SELECT MAX(t2.temperatura_C)
                    FROM {$src} t2
                    WHERE DATE(t2.data_ora) = :g1
                      AND t2.temperatura_C IS NOT NULL
                ) THEN data_ora END
            ))                                      AS temp_max_abs_ora,

            -- Ora prima occorrenza del minimo di temperatura
            TIME(MIN(
                CASE WHEN temperatura_C = (
                    SELECT MIN(t3.temperatura_C)
                    FROM {$src} t3
                    WHERE DATE(t3.data_ora) = :g2
                      AND t3.temperatura_C IS NOT NULL
                ) THEN data_ora END
            ))                                      AS temp_min_abs_ora,

            -- Pressione
            AVG(pressione_hPa)                      AS press_media,
            MAX(pressione_hPa)                      AS press_max,
            MIN(pressione_hPa)                      AS press_min,

            -- Conteggio record validi per metrica (usato per soglia 75%)
            COUNT(CASE WHEN temperatura_C  IS NOT NULL THEN 1 END)  AS n_temp,
            COUNT(CASE WHEN pressione_hPa  IS NOT NULL THEN 1 END)  AS n_press,
            COUNT(CASE WHEN vento_kmh      IS NOT NULL
                        AND direzione_vento_deg IS NOT NULL THEN 1 END)  AS n_vento

        FROM {$src}
        WHERE DATE(data_ora) = :g3
          AND (
              temperatura_C  IS NOT NULL OR
              pressione_hPa  IS NOT NULL OR
              vento_kmh      IS NOT NULL
          )
    ";

    $stmt = $pdo->prepare($sqlBase);
    $stmt->execute([':g1' => $giorno, ':g2' => $giorno, ':g3' => $giorno]);
    $raw = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$raw || (int)$raw['n_record'] < MIN_RECORD_VALIDI) {
        return [
            'success' => false,
            'motivo'  => 'Record insufficienti (' . ($raw['n_record'] ?? 0) . ' < ' . MIN_RECORD_VALIDI . ')',
        ];
    }

    // ----------------------------------------------------------------
    // 1b. CONTROLLO COMPLETEZZA PER METRICA (soglia 75% dei record attesi)
    //     I record attesi vengono calcolati dinamicamente dalla mediana
    //     degli intervalli tra record degli ultimi 7 giorni: in questo modo
    //     il controllo e' corretto per qualsiasi frequenza di campionamento.
    // ----------------------------------------------------------------
    $recordAttesi = calcolaRecordAttesiGiorno($pdo, $src, $giorno);
    $soglia       = (int)ceil($recordAttesi * SOGLIA_COMPLETEZZA);
    $tempOk       = (int)$raw['n_temp']  >= $soglia;
    $pressOk      = (int)$raw['n_press'] >= $soglia;
    $ventoOk      = (int)$raw['n_vento'] >= $soglia;

    // ----------------------------------------------------------------
    // 2. DIREZIONE DOMINANTE: moda sui 16 settori della rosa dei venti
    //    Ogni record con vento > 0 vota il proprio settore (0..15).
    //    Il settore con piu voti e' la direzione dominante.
    //    dirDomPerc: % dei record con vento nel settore modale.
    // ----------------------------------------------------------------
    $dirDomDeg    = null;
    $dirDomPerc   = null;
    $velDirDomKmh = null;

    if ($ventoOk) {
    $sqlModa = "
        SELECT
            ROUND(direzione_vento_deg / 22.5) % 16   AS settore,
            COUNT(*)                                  AS n_settore,
            AVG(vento_kmh)                            AS vel_media_settore
        FROM {$src}
        WHERE DATE(data_ora) = :giorno
          AND vento_kmh           IS NOT NULL
          AND direzione_vento_deg IS NOT NULL
          AND vento_kmh > 0
        GROUP BY settore
        ORDER BY n_settore DESC
        LIMIT 1
    ";
    $stmtModa = $pdo->prepare($sqlModa);
    $stmtModa->execute([':giorno' => $giorno]);
    $rowModa  = $stmtModa->fetch(PDO::FETCH_ASSOC);

    if ($rowModa) {
        $settoreMod   = (int)$rowModa['settore'];
        $dirDomDeg    = (float)($settoreMod * 22.5);   // centro del settore
        $velDirDomKmh = arrotonda($rowModa['vel_media_settore'], 2);

        // Totale record con vento > 0 per la percentuale
        $sqlTotVento = "
            SELECT COUNT(*) AS n_tot
            FROM {$src}
            WHERE DATE(data_ora) = :giorno
              AND vento_kmh           IS NOT NULL
              AND direzione_vento_deg IS NOT NULL
              AND vento_kmh > 0
        ";
        $stmtTot  = $pdo->prepare($sqlTotVento);
        $stmtTot->execute([':giorno' => $giorno]);
        $nTotVento = (int)($stmtTot->fetchColumn() ?? 0);

        if ($nTotVento > 0) {
            $dirDomPerc = (int)round((int)$rowModa['n_settore'] / $nTotVento * 100);
        }
    }
    }  // fine if ($ventoOk)

    // ----------------------------------------------------------------
    // 5. RADIANZA: percentuale cumulato giornaliero sul teorico
    //    - Cumulato reale : ultimo valore di radianza_int_whm2 del giorno
    //                       (campo progressivo, l'ultimo record = totale)
    //    - Teorico        : calculateSolarRadiationTheoretical($giorno)
    //                       da astro_helper.php, stessa funzione usata in
    //                       api_tabella_home_data.php, accetta date arbitrarie
    // ----------------------------------------------------------------
    $radPercent24h = null;

    try {
        $sqlRad = "
            SELECT radianza_int_whm2
            FROM {$src}
            WHERE DATE(data_ora) = :giorno
              AND radianza_int_whm2 IS NOT NULL
              AND radianza_int_whm2 > 0
            ORDER BY data_ora DESC
            LIMIT 1
        ";
        $stmtRad = $pdo->prepare($sqlRad);
        $stmtRad->execute([':giorno' => $giorno]);
        $rowRad = $stmtRad->fetch(PDO::FETCH_ASSOC);

        if ($rowRad && is_numeric($rowRad['radianza_int_whm2'])) {
            $cumulatoReale  = (float)$rowRad['radianza_int_whm2'];
            $teorico        = calculateSolarRadiationTheoretical($giorno);
            $energiaTeorica = (float)($teorico['energia_totale_whm2'] ?? 0);

            if ($energiaTeorica > 0) {
                $radPercent24h = arrotonda(($cumulatoReale / $energiaTeorica) * 100, 1);
            }
        }
    } catch (Throwable $e) {
        error_log("calcola_giornaliero: errore radianza {$giorno}: " . $e->getMessage());
        $radPercent24h = null;
    }

    // ----------------------------------------------------------------
    // 6. ASSEMBLAGGIO DEI DATI DA SALVARE
    //    I campi devono corrispondere ESATTAMENTE alle colonne della tabella
    // ----------------------------------------------------------------
    $data = [
        'data_giorno'            => $giorno,

        // Temperatura: 9999 se meno del 75% dei record e' valido
        'temp_media'             => $tempOk ? arrotonda($raw['temp_media'], 2)   : SENTINELLA,
        'temp_max_abs'           => $tempOk ? arrotonda($raw['temp_max_abs'], 2) : SENTINELLA,
        'temp_max_abs_ora'       => $tempOk ? ($raw['temp_max_abs_ora'] ?? null)  : null,
        'temp_min_abs'           => $tempOk ? arrotonda($raw['temp_min_abs'], 2) : SENTINELLA,
        'temp_min_abs_ora'       => $tempOk ? ($raw['temp_min_abs_ora'] ?? null)  : null,

        // Pressione: 9999 se meno del 75% dei record e' valido
        'press_media'            => $pressOk ? arrotonda($raw['press_media'], 2) : SENTINELLA,
        'press_max'              => $pressOk ? arrotonda($raw['press_max'], 2)   : SENTINELLA,
        'press_min'              => $pressOk ? arrotonda($raw['press_min'], 2)   : SENTINELLA,

        // Vento: 9999 se meno del 75% dei record e' valido
        'vento_dir_dom_deg'      => $ventoOk ? $dirDomDeg     : SENTINELLA,
        'vento_dir_dom_perc'     => $ventoOk ? $dirDomPerc    : null,
        'vento_dom_kmh'          => $ventoOk ? $velDirDomKmh  : SENTINELLA,

        // Radianza: 9999 se non calcolabile (dati insufficienti o notte)
        'rad_percent_24h'        => $radPercent24h,

        'n_record'               => (int)$raw['n_record'],
        'calcolato_il'           => date('Y-m-d H:i:s'),
    ];

    // ----------------------------------------------------------------
    // 7. UPSERT — INSERT ... ON DUPLICATE KEY UPDATE
    //    data_giorno e' PRIMARY KEY (implicitamente UNIQUE):
    //    se il giorno esiste gia' lo sovrascrive (utile per ricalcoli)
    // ----------------------------------------------------------------
    $cols       = array_keys($data);
    $places     = array_map(function($c) { return ':' . $c; }, $cols);
    $updateCols = array_filter($cols, function($c) { return $c !== 'data_giorno'; });
    $updateStr  = implode(', ', array_map(function($c) { return "{$c} = VALUES({$c})"; }, $updateCols));

    $sql = "
        INSERT INTO {$dest}
            (" . implode(', ', $cols) . ")
        VALUES
            (" . implode(', ', $places) . ")
        ON DUPLICATE KEY UPDATE
            {$updateStr}
    ";

    $stmtInsert = $pdo->prepare($sql);
    $stmtInsert->execute($data);

    return array_merge(['success' => true], $data);
}


// ============================================================================
// FUNZIONI HELPER
// ============================================================================

/**
 * Calcola l'energia solare teorica giornaliera (Wh/m²) per una data specifica.
 *
 * Usa la stessa logica del simulatore esistente (getSolarRadiationTheoretical)
 * ma accetta una data arbitraria — indispensabile per il backfill.
 *
 * Coordinate stazione: 43.2924° N, 11.1671° E, 418 m slm
 * Metodo: integrazione trapezoidale dell'irradianza teorica ora per ora.
 *
 * @param string $giorno  Data nel formato Y-m-d
 * @return float  Energia teorica giornaliera in Wh/m² (0 se non calcolabile)
 */
/**
 * Calcola il numero di record attesi in un giorno per questa stazione.
 *
 * Strategia: mediana degli intervalli in secondi tra record consecutivi
 * negli ultimi 7 giorni (escluso il giorno corrente, che potrebbe essere
 * ancora incompleto). Da quella mediana si ricava il passo tipico e quindi
 * quanti record ci si aspetta in 86400 secondi.
 *
 * Questo rende il controllo di completezza scalabile: funziona uguale per
 * stazioni che campionano ogni 1 min, 2 min, 5 min, ecc.
 *
 * @param PDO    $pdo    Connessione DB
 * @param string $src    Nome tabella sorgente
 * @param string $giorno Data di riferimento (Y-m-d); si guardano i 7 giorni prima
 * @return int  Record attesi in un giorno (minimo 1, fallback RECORD_ATTESI_FALLBACK)
 */
function calcolaRecordAttesiGiorno(PDO $pdo, string $src, string $giorno): int
{
    // Prendo i timestamp degli ultimi 7 giorni prima del giorno corrente
    $sqlTs = "
        SELECT data_ora
        FROM {$src}
        WHERE data_ora >= DATE_SUB(:g, INTERVAL 7 DAY)
          AND data_ora <  :g
        ORDER BY data_ora ASC
    ";
    $stmtTs = $pdo->prepare($sqlTs);
    $stmtTs->execute([':g' => $giorno]);
    $timestamps = $stmtTs->fetchAll(PDO::FETCH_COLUMN);

    if (count($timestamps) < 2) {
        return RECORD_ATTESI_FALLBACK;
    }

    // Calcola tutti gli intervalli consecutivi in secondi
    $intervalli = [];
    $n = count($timestamps);
    for ($i = 1; $i < $n; $i++) {
        $diff = strtotime($timestamps[$i]) - strtotime($timestamps[$i - 1]);
        // Ignora buchi grandi (> 10 minuti): sono interruzioni, non il passo normale
        if ($diff > 0 && $diff <= 600) {
            $intervalli[] = $diff;
        }
    }

    if (empty($intervalli)) {
        return RECORD_ATTESI_FALLBACK;
    }

    // Mediana degli intervalli
    sort($intervalli);
    $mid   = (int)floor(count($intervalli) / 2);
    $passo = (count($intervalli) % 2 === 0)
           ? ($intervalli[$mid - 1] + $intervalli[$mid]) / 2
           : $intervalli[$mid];

    if ($passo <= 0) {
        return RECORD_ATTESI_FALLBACK;
    }

    return max(1, (int)round(86400 / $passo));
}

/**
 * Arrotonda a N decimali; restituisce null se il valore e' null o non numerico.
 */
function arrotonda($valore, $decimali)
{
    if ($valore === null || !is_numeric($valore)) {
        return null;
    }
    return round((float)$valore, $decimali);
}

/**
 * Valida e normalizza una stringa data in formato Y-m-d.
 */
function validaData(string $input): string
{
    $dt = DateTime::createFromFormat('Y-m-d', trim($input));
    if ($dt === false || $dt->format('Y-m-d') !== trim($input)) {
        fatalError("Data non valida: '{$input}'. Usa il formato YYYY-MM-DD.");
    }
    return $dt->format('Y-m-d');
}

/**
 * Scrive un messaggio su stdout (CLI) o nel browser (web).
 * Con flush() immediato per vedere il progresso riga per riga.
 */
function log_msg(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if (IS_CLI) {
        echo $line . "\n";
    } else {
        echo htmlspecialchars($line) . "<br>\n";
        if (ob_get_level()) ob_flush();
        flush();
    }
}

/**
 * Termina lo script con errore fatale.
 */
function fatalError($msg)
{
    log_msg('[FATALE] ' . $msg);
    exit(1);
}