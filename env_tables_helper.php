<?php
declare(strict_types=1);

// Imposta qui l'ambiente: true = TEST, false = PRODUZIONE
//per selezionare i DB TEST o PRODUZIONE
// Definisci solo se non già definita

    define('USE_TEST_MODE_TABLES', false); // o false per PROD


/**
 * Verifica se siamo in TEST
 */
function is_test_mode(): bool {
    return USE_TEST_MODE_TABLES;
}

/**
 * Ritorna il nome tabella corretto
 */
function table_name(string $base): string {
    return USE_TEST_MODE_TABLES ? "{$base}_test" : $base;
}

/* Normalizza i valori provenienti dal database
 * Converte stringhe 'null', 'NULL' e stringhe vuote in vero null
 * 
 * @param mixed $value Il valore da normalizzare
 * @return mixed Il valore normalizzato o null
 */
function normalize_value($value) {
    if ($value === null || $value === 'null' || $value === '' || $value === 'NULL') {
        return null;
    }
    return $value;
}

/* =========================================================
   LOG GIORNALIERO (UN SOLO FILE, RESET OGNI 24H)
   ========================================================= */
   
   $logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

define('DB_TABLE_LOG', __DIR__ . '/logs/db_tables.log');

function log_table_usage(string $tableName): void
{
    static $initialized = false;

    // ---------- reset giornaliero ----------
    if (!$initialized) {
        $today = date('Y-m-d');

        if (file_exists(DB_TABLE_LOG)) {
            $fileDay = date('Y-m-d', filemtime(DB_TABLE_LOG));

            // Se il file è di ieri → lo azzero
            if ($fileDay !== $today) {
                @file_put_contents(DB_TABLE_LOG, '');
            }
        }

        $initialized = true;
    }

    // ---------- info chiamante ----------
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $callerFile = isset($trace[1]['file'])
        ? basename($trace[1]['file'])
        : 'unknown';

    // ---------- ambiente ----------
    $env = USE_TEST_MODE_TABLES ? 'TEST' : 'PROD';

    // ---------- database reale ----------
    global $pdo;
    $dbName = 'unknown_db';
    if (isset($pdo)) {
        try {
            $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
        } catch (Throwable $e) {}
    }

    // ---------- riga di log ----------
    $line = sprintf(
        "%s ----- %s/%s ---- %s ---- %s\n",
        $callerFile,
        $dbName,
        $tableName,
        $env,
        date('d/m/Y H:i:s')
    );

    @file_put_contents(DB_TABLE_LOG, $line, FILE_APPEND | LOCK_EX);
}




/*-------chiamato da elimina_mag_24h.php---------*/
?>