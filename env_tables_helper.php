<?php
declare(strict_types=1);

// Imposta qui l'ambiente: true = TEST, false = PRODUZIONE
// Definisci solo se non già definita

    define('USE_TEST_MODE_TABLES', true); // o false per PROD


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
/*-------chiamato da elimina_mag_24h.php---------*/
?>