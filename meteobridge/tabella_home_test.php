<?php

// Mappatura delle chiavi con le descrizioni (mantieni questa per compatibilità)
$parametri = [
    "mbsystem-sunrise"         => "Ora alba e tramonto",
    "mbsystem-lunarpercent"    => "Luna: Fase e luce disco", 
    "gradiente_t"              => "\u{0394} °C e hPa - ultima ora",
    "th0temp-delta24"          => "\u{0394}24h",
    "th0temp-act"              => "Temperatura attuale",
    "th0temp-ydmax"            => "Max ieri",
    "th0temp-dmax"             => "Temperatura max e ora",
    "th0temp-ydmin"            => "Min ieri",
    "th0temp-dmin"             => "Temperatura min e ora",
    "th0hum-act"               => "Umidità attuale",
    "th0hum-dmax"              => "Umidità max e ora",
    "th0hum-dmin"              => "Umidità min e ora",
    "th0dew-act"               => "Punto rugiada attuale",
    "th0dew-dmax"              => "Punto rugiada max e ora",
    "th0dew-dmin"              => "Punto rugiada min e ora",
    "thb0press-delta24"        => "\u{0394}24h",
    "thb0press-act"            => "Pressione @lm attuale",
    "thb0seapress-dmax"        => "Pressione @lm max e ora",
    "thb0seapress-dmin"        => "Pressione @lm min e ora",
    "wind0avgwind-act"         => "Vel media vento - attuale",
    "wind0avgwind-dmax"        => "Vel media vento - max e ora",
    "wind0wind-act"            => "Vel vento attuale",
    "wind0wind-dmax"           => "Vel vento max e ora",
    "wind0dir-act"             => "Direzione del vento",
    "wind0chill-act"           => "\u{0394} per windchill",
    "th0heatindex-act"         => "\u{0394} per indice di calore",
    "sol0rad-act"              => "Radianza attuale",
    "sol0rad-sum24h"           => "Radianza cumulata giornaliera",
    "th0temp-age"              => "Ultimo dato t/h",
    "thb0press-age"            => "Ultima dato p",
    "wind0wind-age"            => "Ultima dato vento"
];

// associo i valori della funzione calcola delta alle posizioni nella stringa parametri
    $funzioni_multiple = [
        "calcolaTemperaturaDelta24hConParametro" => [5, 17]
    ];

function estraiNumero($stringa) {
    $pulito = preg_replace('/[^0-9\.\-]/', '', $stringa);
    $valore = floatval($pulito);
    return $valore;
}

// Versione alternativa che accetta temp_attuale come parametro
function calcolaTemperaturaDelta24hConParametro($temp_attuale, $pressione_attuale) {
    
    // Data e ora attuale  
    $data_ora_attuale = date('d/m/Y,H:i');
    
    // Calcola data e ora di 24h fa
    $timestamp_24h_fa = strtotime('-24 hours');
    $data_24h_fa = date('d/m/Y', $timestamp_24h_fa);
    $ora_24h_fa = date('H:i', $timestamp_24h_fa);
    
    
    try {
        // Connessione al database usando envelop.php
        require_once '/home/erbielqv/envelop_lettura.php';
if (!isset($pdo_lettura)) {
    file_put_contents('log_delta.txt', "[DEBUG] ❌ Errore: \$pdo non definito\n", FILE_APPEND);
    return ['Err', 'Err'];
}

// ✅ Query solida, senza parametri
$sql = "SELECT temperatura_C, pressione_hPa, data_ora
        FROM dati_meteo_simignano
        ORDER BY ABS(TIMESTAMPDIFF(SECOND, data_ora, DATE_SUB(NOW(), INTERVAL 24 HOUR)))
        LIMIT 1";

$stmt = $pdo_lettura->prepare($sql);
$stmt->execute();

$risultato = $stmt->fetch(PDO::FETCH_ASSOC);

if ($risultato) {
    $temp_24h_fa = floatval($risultato['temperatura_C']);
    $pressione_24h_fa = floatval($risultato['pressione_hPa']);
    $data_ora_db = $risultato['data_ora'];

    $delta_temp_24h = $temp_attuale - $temp_24h_fa;
    $delta_pressione_24h = $pressione_attuale - $pressione_24h_fa;

    $log = "[DEBUG " . date('Y-m-d H:i:s') . "]\n";
    $log .= "→ Temperatura attuale: {$temp_attuale}°C\n";
    $log .= "→ Pressione attuale: {$pressione_attuale} hPa\n";
    $log .= "→ Valore DB @ {$data_ora_db}:\n";
    $log .= "   - Temp 24h fa: {$temp_24h_fa}°C\n";
    $log .= "   - Pressione 24h fa: {$pressione_24h_fa} hPa\n";
    $log .= "📉 Δ Temp: " . number_format($delta_temp_24h, 1) . "°C\n";
    $log .= "📉 Δ Pressione: " . number_format($delta_pressione_24h, 1) . " hPa\n\n";

    file_put_contents('log_delta.txt', $log, FILE_APPEND);

    return [
        number_format($delta_temp_24h, 1) . '°C',
        number_format($delta_pressione_24h, 1) . 'hPa'
    ];
}
                
            } catch (PDOException $e) {
                error_log("Errore database: " . $e->getMessage());
                
                return 'Err';
            }   
        }



// Funzione principale per processare i valori
function processaValori($stringa_dati) {
    global $funzioni_multiple;

    $valori = explode(',', $stringa_dati); // ✅ Prima cosa!

    // Debug dell'intero array
    foreach ($valori as $index => $val) {
        
    }

    foreach ($funzioni_multiple as $nome_funzione => $posizioni) {
    if (function_exists($nome_funzione)) {
        
        $temp_attuale = estraiNumero($valori[6]);
        $pressione_attuale = estraiNumero($valori[18]);


        // ✅ QUI CHIAMI LA FUNZIONE
        $risultati = $nome_funzione($temp_attuale, $pressione_attuale);

        if (is_array($risultati) && count($risultati) === count($posizioni)) {
            foreach ($posizioni as $i => $posizione) {
                $valori[$posizione] = $risultati[$i];
            }
        }
    }
}

    return implode(',', $valori);
}


// Funzione per salvare i dati in un file
function saveDataToFile($data) {
    $file = 'dati_temperatura_test.txt';
    file_put_contents($file, $data);
}

// Gestione della richiesta GET 
if (isset($_GET['temp'])) {
    $dati_grezzi = $_GET['temp'];
    $dati_processati = processaValori($dati_grezzi);
    saveDataToFile($dati_processati);
    // Silenzioso: nessun output
}

?>