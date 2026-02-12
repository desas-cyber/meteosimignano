<?php
/**
 * camera_config.php — Configurazione specifica della telecamera
 *
 * Per adattare il sito a un'altra camera, basta cambiare questi valori.
 * Tutti i file PHP (index.php, aggiorna_galleria.php, aggiornaCartellaImmagini.php)
 * leggono da qui.
 */

$CAMERA_CONFIG = [
    // ---- Percorsi ----
    // Percorso fisico della cartella immagini (con trailing slash)
    'directory'   => __DIR__ . '/FoscamCamera_E8ABFAA799FE/snap/',

    // Prefisso web per il src delle immagini (relativo alla root web)
    'src_prefix'  => '/FoscamCamera_E8ABFAA799FE/snap/',

    // ---- Crop watermark ----
    // Pixel da tagliare dal basso in gallery mode (0 = nessun crop)
    'crop_bottom_px'  => 80,

    // Equivalente percentuale per clip-path in time-lapse ('' = nessun crop)
    'crop_bottom_pct' => '5.5%',

    // ---- File naming ----
    // Estensioni ammesse (senza punto)
    'extensions'  => ['jpg', 'jpeg'],

    // Pattern nome file (regex). Usato per:
    //   - validare che un file appartenga a questa camera
    //   - estrarre data/ora dal nome se il DB non ha il campo DATA_ORA
    // Gruppi cattura: (1)=anno (2)=mese (3)=giorno (4)=ora (5)=minuto (6)=secondo
    // Foscam:  Schedule_YYYYMMDD-HHMMSS.jpg
    'filename_pattern' => '/^Schedule_(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})\.jpe?g$/i',

    // Se true, l'ordinamento alfabetico dei nomi file corrisponde all'ordine cronologico.
    // Se false, i file vengono ordinati estraendo il timestamp dal filename_pattern.
    'alpha_sort_is_chrono' => true,

    // In camera_config.php, aggiungi:

/**
 * Estrae un timestamp ordinabile (YYYYMMDDHHMMSS) dal nome file.
 * Restituisce stringa vuota se il nome non matcha il pattern.
 */
'extract_timestamp' => function(string $filename) use (&$CAMERA_CONFIG): string {
    if (!preg_match($CAMERA_CONFIG['filename_pattern'], $filename, $m)) {
        return '';
    }
    $g  = $CAMERA_CONFIG['filename_groups'];
    $yp = $CAMERA_CONFIG['year_prefix'];
    return $yp . $m[$g[0]] . $m[$g[1]] . $m[$g[2]] . $m[$g[3]] . $m[$g[4]] . $m[$g[5]];
},

];