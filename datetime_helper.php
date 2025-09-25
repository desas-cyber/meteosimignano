
<?php
define('USE_TEST_MODE', true);
define('TEST_DATETIME', '2025-09-09 00:25:00');
date_default_timezone_set('Europe/Rome');
/**
 * Restituisce la data/ora corrente o di test nel formato specificato
 */
function get_now($format = 'Y-m-d H:i:s') {
    return USE_TEST_MODE ? date($format, strtotime(TEST_DATETIME)) : date($format);
}

/**
 * Restituisce il timestamp corrente o di test
 */
function get_time() {
    return USE_TEST_MODE ? strtotime(TEST_DATETIME) : time();
}

/**
 * Restituisce il giorno dell'anno (1-366)
 */
function get_day_of_year() {
    return USE_TEST_MODE ? date('z', strtotime(TEST_DATETIME)) + 1 : date('z') + 1;
}

/**
 * Formatta una data con il timestamp corrente o di test
 */
function get_date($format = 'Y-m-d H:i:s', $timestamp = null) {
    if ($timestamp === null) {
        return get_now($format);
    }
    return date($format, $timestamp);
}

/**
 * Restituisce un DateTime object per il momento corrente o di test
 */
function get_datetime() {
    return USE_TEST_MODE ? new DateTime(TEST_DATETIME) : new DateTime();
}

/**
 * Converte una stringa di data in timestamp considerando la modalità test
 */
function get_strtotime($datestring, $baseTimestamp = null) {
    if ($baseTimestamp === null && USE_TEST_MODE) {
        $baseTimestamp = strtotime(TEST_DATETIME);
    }
    
    return $baseTimestamp ? strtotime($datestring, $baseTimestamp) : strtotime($datestring);
}

/*-------chiamato da tabella_home_display.php;modifica_mag_24h.php;tabella_home.php---------*/

?>