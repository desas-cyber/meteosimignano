<?php
/**
 * ============================================================================
 * RICONCILIAZIONE BACKUP GW1100 -> PRODUZIONE - riconciliazione_backup_gw1100.php
 * ============================================================================
 *
 * RESPONSABILITA':
 * - Confronta i dati raccolti dal gateway di backup (GW1100) con quelli
 *   gia' presenti in produzione (dati_meteo_simignano)
 * - Il confronto NON e' sul timestamp esatto: i due gateway non sono
 *   sincronizzati al secondo (es. uno legge alle 10:00:00, l'altro alle
 *   10:00:45), quindi il confronto avviene per FASCIA DI MINUTO
 *   (00-59 secondi dello stesso minuto)
 * - Se in produzione manca completamente un dato per un dato minuto,
 *   inserisce la riga di backup corrispondente
 * - Ripulisce dal backup tutte le righe ormai coperte da un dato in
 *   produzione (sia quelle gia' presenti prima, sia quelle appena inserite)
 *
 * QUANDO GIRA:
 * - Pensato per un cron ogni 6 ore (esecuzione da CLI: php riconciliazione_backup_gw1100.php)
 * - Elabora solo i dati di backup piu' vecchi di $margine_minuti (default 10'),
 *   per evitare di confrontare dati che sul gateway principale non sono
 *   ancora arrivati per un semplice ritardo di rete
 *
 * LOG (due file separati):
 * - riconciliazione_backup_gw1100.log: storico completo, ogni esecuzione
 *   (anche quelle con 0 righe inserite/cancellate)
 * - riconciliazione_backup_gw1100_eventi.log: solo le esecuzioni in cui
 *   e' stato effettivamente inserito qualcosa in produzione (inserite > 0),
 *   per individuare a colpo d'occhio i casi significativi
 *
 * SICUREZZA:
 * - Se invocato via HTTP (non da CLI) richiede un token in query string
 *   (?token=abc), per evitare che chiunque possa lanciare la
 *   riconciliazione da browser. Da CLI (cron reale) il controllo e' saltato.
 * - NOTA: 'abc' e' un token banale, usato solo per i test. Prima di
 *   lasciare il file esposto stabilmente, sostituirlo con una stringa
 *   lunga e casuale.
 */

require_once __DIR__ . '/../datetime_helper.php';
require_once __DIR__ . '/../env_tables_helper.php';
require_once __DIR__ . '/../../envelop.php';

// === CONFIGURAZIONE ===
$debug = false;
$log_file = __DIR__ . '/riconciliazione_backup_gw1100.log'; // storico completo, ogni esecuzione
$log_file_eventi = __DIR__ . '/riconciliazione_backup_gw1100_eventi.log'; // solo le esecuzioni con inserimenti > 0
$tabella_backup = 'dati_meteo_backup_gw1100';
$tabella_produzione = 'dati_meteo_simignano';
$margine_minuti = 10; // non tocca dati di backup piu' recenti di N minuti

// Token per invocazione via HTTP (richiesto solo se non lanciato da CLI/cron)
$token_atteso = 'abc';

/*
 * Scrive nel log mantenendo solo le ultime N righe
 */
function scriviLogRicon($messaggio, $log_file, $max_righe = 720) {
    $timestamp = date('Y-m-d H:i:s');
    $nuova_riga = "[$timestamp] $messaggio";

    $righe = [];
    if (file_exists($log_file)) {
        $righe = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    $righe[] = $nuova_riga;

    if (count($righe) > $max_righe) {
        $righe = array_slice($righe, -$max_righe);
    }

    file_put_contents($log_file, implode("\n", $righe) . "\n");
}

/**
 * Esegue la riconciliazione in una singola transazione:
 * 1) inserisce in produzione i minuti presenti in backup ma assenti in produzione
 * 2) cancella dal backup tutte le righe ormai coperte (prima o dopo l'insert)
 *
 * @return array ['inserite' => int, 'cancellate' => int]
 * @throws Exception in caso di errore SQL (la transazione viene annullata)
 */
function eseguiRiconciliazione($pdo, $tabella_backup, $tabella_produzione, $margine_minuti) {
    $margine_minuti = (int)$margine_minuti;

    $pdo->beginTransaction();

    try {
        // === PASSO 1: inserisci i minuti mancanti ===
        $sql_insert = "
            INSERT INTO $tabella_produzione
                (data_ora, temperatura_C, umidita_RH, pressione_hPa,
                 dew_point_C, vento_kmh, direzione_vento_deg,
                 radianza_wm2, radianza_int_whm2)
            SELECT
                b.data_ora, b.temperatura_C, b.umidita_RH, b.pressione_hPa,
                b.dew_point_C, b.vento_kmh, b.direzione_vento_deg,
                b.radianza_wm2, b.radianza_int_whm2
            FROM $tabella_backup b
            LEFT JOIN $tabella_produzione m
                ON m.data_ora >= DATE_FORMAT(b.data_ora, '%Y-%m-%d %H:%i:00')
               AND m.data_ora <= DATE_FORMAT(b.data_ora, '%Y-%m-%d %H:%i:59')
            WHERE b.data_ora <= (NOW() - INTERVAL $margine_minuti MINUTE)
              AND m.data_ora IS NULL
        ";

        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute();
        $inserite = $stmt_insert->rowCount();

        // === PASSO 2: cancella dal backup tutto cio' che ora e' coperto ===
        // (rilegge lo stesso join: le righe inserite al passo 1 risultano
        // ora "coperte" e vengono ripulite insieme a quelle gia' presenti prima)
        $sql_delete = "
            DELETE b FROM $tabella_backup b
            INNER JOIN $tabella_produzione m
                ON m.data_ora >= DATE_FORMAT(b.data_ora, '%Y-%m-%d %H:%i:00')
               AND m.data_ora <= DATE_FORMAT(b.data_ora, '%Y-%m-%d %H:%i:59')
            WHERE b.data_ora <= (NOW() - INTERVAL $margine_minuti MINUTE)
        ";

        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->execute();
        $cancellate = $stmt_delete->rowCount();

        $pdo->commit();

        return ['inserite' => $inserite, 'cancellate' => $cancellate];

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// === MAIN EXECUTION ===
$e_cli = (php_sapi_name() === 'cli');

try {
    // Se invocato via HTTP, richiedi il token (protezione minima)
    if (!$e_cli) {
        $token_ricevuto = $_GET['token'] ?? '';
        if ($token_ricevuto !== $token_atteso) {
            http_response_code(403);
            echo json_encode(['error' => 'Token non valido']);
            scriviLogRicon('ERRORE: tentativo di accesso HTTP con token non valido', $log_file);
            exit;
        }
    }

    $risultato = eseguiRiconciliazione($pdo, $tabella_backup, $tabella_produzione, $margine_minuti);

    $messaggio = sprintf(
        'OK: %d righe inserite in produzione, %d righe cancellate dal backup (margine %d minuti)',
        $risultato['inserite'],
        $risultato['cancellate'],
        $margine_minuti
    );

    scriviLogRicon($messaggio, $log_file);

    // Log separato: solo se c'e' stato almeno un inserimento reale in produzione
    if ($risultato['inserite'] > 0) {
        scriviLogRicon($messaggio, $log_file_eventi);
    }

    $response = [
        'status' => 'success',
        'inserite' => $risultato['inserite'],
        'cancellate' => $risultato['cancellate'],
        'margine_minuti' => $margine_minuti
    ];

    if (!$e_cli) {
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        echo $messaggio . "\n";
    }

} catch (Exception $e) {
    $errore = 'ERRORE riconciliazione: ' . $e->getMessage();
    scriviLogRicon($errore, $log_file);

    if (!$e_cli) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } else {
        fwrite(STDERR, $errore . "\n");
        exit(1);
    }
}