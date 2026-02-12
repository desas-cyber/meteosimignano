<?php
/**
 * camera_config.php — Configurazione specifica della telecamera
 *
 * Per adattare il sito a un'altra camera, basta cambiare questi valori.
 * Tutti i file PHP (index.php, aggiorna_galleria.php, aggiornaCartellaImmagini.php)
 * leggono da qui.
 */

$CAMERA_CONFIG = [
    // Percorso fisico della cartella immagini (con trailing slash)
    'directory'   => __DIR__ . '/FoscamCamera_E8ABFAA799FE/snap/',

    // Prefisso web per il src delle immagini (deve corrispondere al percorso relativo dalla root web)
    'src_prefix'  => '/FoscamCamera_E8ABFAA799FE/snap/',

    // Pixel da tagliare dal basso in gallery mode (watermark camera)
    'crop_bottom_px'  => 80,

    // Equivalente percentuale per clip-path in time-lapse mode
    'crop_bottom_pct' => '5.5%',
];