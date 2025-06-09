<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// $parametri arriva da questo file
require_once __DIR__ . '/meteobridge/tabella_home.php';

// Funzione per leggere i dati dal file
function readDataFromFile() {
    $file = 'meteobridge/dati_temperatura.txt';
    if (file_exists($file)) {
        return file_get_contents($file);
    }
    return null;
}


// Connessione al database usando envelop.php
        //require_once '/home/erbielqv/envelop_lettura.php';DA USARE IN INTERNET
        require_once __DIR__ . '/../envelop_lettura.php';

if (!isset($pdo_lettura)) {
    file_put_contents('log_delta.txt', "[DEBUG] ❌ Errore: \$pdo _lettura non definito\n", FILE_APPEND);
    return ['Err', 'Err'];
}

//restituisce massimo solare e ora
function getSolareMassimoGiornaliero(?PDO $pdo_lettura): string
{
    // 1) Se non ho un PDO valido, torno subito fallback
    if ($pdo_lettura === null) {
        return "Teor Max e ora: N/A";
    }

    try {
        // 2) Calcolo oggi come giorno dell'anno (1-based)
        $oggi = intval(date('z')) + 1;  // es. 152 per il 31 maggio 2025

        // 3) Eseguo la query per irradianza e ora UTC
        $sql = "
            SELECT 
                irradianza_max_w_m2, 
                ora_massima_utc, 
                giorno_anno
            FROM 
                solar_data_siena 
            WHERE 
                giorno_anno = :oggi
            ORDER BY 
                ora_massima_utc DESC
            LIMIT 1
        ";
        $stmt = $pdo_lettura->prepare($sql);
        $stmt->execute([':oggi' => $oggi]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // 4) Se non c’è alcun record per il giorno_anno, fallback
        if (!$row) {
            return "Teor Max e ora: N/A";
        }

        // 5) Prelevo i valori dal DB
        $irradianza = $row['irradianza_max_w_m2'];   // es. 970.83
        $oraUtc     = $row['ora_massima_utc'];       // es. "11:15:00"
        $giornoAnno = intval($row['giorno_anno']);   // es. 152

        // 6) Ricostruisco il DateTime UTC partendo da:
        //    - Anno corrente
        //    - Giorno dell'anno (0-based per DateTime crea da 1° gennaio)
        $annoCorrente = intval(date('Y'));       // es. 2025
        $zUtc         = $giornoAnno - 1;         // per createFromFormat('Y z H:i:s'), z è 0-based

        // Creo la stringa "YYYY z HH:MM:SS"
        //   ad esempio "2025 151 11:15:00" per il 152° giorno a ore 11:15 UTC
        $datetimeUtcStr = sprintf('%d %d %s', $annoCorrente, $zUtc, $oraUtc);

        // 7) Creo l’oggetto DateTime in UTC
        $dtUtc = DateTime::createFromFormat(
            'Y z H:i:s',
            $datetimeUtcStr,
            new DateTimeZone('UTC')
        );
        if (!$dtUtc) {
            // Se il parsing fallisce per qualche motivo, fallback con sola irradianza
            return "Teor Max e ora: {$irradianza} @ N/A";
        }

        // 8) Converto UTC -> Europe/Rome (PHP applica automaticamente DST)
        $dtUtc->setTimezone(new DateTimeZone('Europe/Rome'));
        $oraLocale = $dtUtc->format('H:i');       // es. "13:15"

        // 9) Ritorno la stringa finale
        return "Teor Max e ora: {$irradianza} @ {$oraLocale}";

    } catch (\Throwable $e) {
        // 10) In caso di qualunque errore PDO o altro, loggo e ritorno fallback
        file_put_contents(
            __DIR__ . '/log_funz.txt',
            "[DEBUG] ❌ Errore getSolareMassimoGiornaliero: " 
             . $e->getMessage() . ' (' . date('Y-m-d H:i:s') . ")\n",
            FILE_APPEND
        );
        return "Max e ora: N/A";
    }
}
//cumulato radianza solare********************
function getSolareteoricoMezzaGiornata(?PDO $pdo_lettura)
{
    if ($pdo_lettura === null) {
        return "cumulato_12_24h: N/A";
    }
    
    try {
        
        
        // dichiarazione iniziale
        $cumulato_percent_12h = "N/A"; 
        $cumulato_percent_24h = "N/A";
        $oggi = (int)date('z') + 1;  // 'z' = zero-based day of year
        //echo "📅 Oggi: $oggi";
        $ora_attuale = (new DateTime('now', new DateTimeZone('Europe/Rome')))->format('H:i:s');
        $pdo_lettura->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        //CHIAMATA PARAMETRI RADIANZA TEORICA 
        $sql = "
            SELECT 
                energia_totale_wh_m2/2 AS teorico_12h,
                energia_totale_wh_m2 AS teorico_24h,
                ora_massima_utc
            FROM 
                solar_data_siena 
            WHERE 
                giorno_anno = :oggi
            ";    

            $stmt = $pdo_lettura->prepare($sql);
            $stmt->execute([':oggi' => $oggi]);
            $row1 = $stmt->fetch(PDO::FETCH_ASSOC);
            $ora_massima_utc = $row1['ora_massima_utc'] ?? null;
            
        //CHIAMATA PARAMETRI RADIANZA EFFETTIVA 
        $sql = "
            SELECT data_ora, radianza_int_whm2
            FROM dati_meteo_simignano
            WHERE data_ora BETWEEN NOW() - INTERVAL 15 MINUTE AND NOW()
            AND DATE(data_ora) = CURDATE()
            AND radianza_int_whm2 IS NOT NULL
            ORDER BY data_ora DESC
            LIMIT 1
            ";
            $stmt = $pdo_lettura->prepare($sql);
            $stmt->execute();
            $row2 = $stmt->fetch(PDO::FETCH_ASSOC);
/*        
echo "<pre>";
print_r($row2);
echo "</pre>";
*/ 

            //CHIAMATA PARAMETRI RADIANZA CUMULATA ALLE 12 H

            $radianza_max_cumulo_12h = "N/A";
            $oggi = date("Y-m-d");  // ad esempio: "2025-06-01"
            
            $ora_massima_utc_obj = new DateTime($ora_massima_utc, new DateTimeZone('UTC'));
            $ora_massima_loc = $ora_massima_utc_obj->setTimezone(new DateTimeZone('Europe/Rome'))->format('H:i:s');
            
            $ora_massima_loc_start = date("Y-m-d H:i:s", strtotime("$ora_massima_loc -5 minutes"));
            $ora_massima_loc_end = date("Y-m-d H:i:s", strtotime("$ora_massima_loc +5 minutes"));
                
            $sql = "
                SELECT radianza_int_whm2 AS radianza_max_cumulo_12h
                FROM dati_meteo_simignano
                WHERE data_ora BETWEEN :ora_massima_loc_start AND :ora_massima_loc_end
                  AND radianza_int_whm2 IS NOT NULL
                  AND data_ora LIKE :oggi_pattern
                LIMIT 1
            ";

                
        
        $stmt = $pdo_lettura->prepare($sql);
        $stmt->execute([
            ':ora_massima_loc_start' => $ora_massima_loc_start,
            ':ora_massima_loc_end' => $ora_massima_loc_end,
            ':oggi_pattern' => $oggi . '%'
        ]);
        $row3 = $stmt->fetch(PDO::FETCH_ASSOC);
        
        
        $radianza_int_whm2 = $row2['radianza_int_whm2'] ?? null;
        $teorico_12h = $row1['teorico_12h'] ?? null;
        $radianza_max_cumulo_12h = $row3['radianza_max_cumulo_12h'] ?? null;
        
        /*echo "💡 radianza massima: $radianza_max_cumulo_12h<br>";
        echo "📐 teorico 12h: $teorico_12h<br>";
        */
        //CUMULATO 12H
        if ($ora_massima_loc !== null && $ora_attuale <= $ora_massima_loc) {
            if ($teorico_12h === null || $radianza_int_whm2 === null) {
                $cumulato_percent_12h = "N/A";
            } else {
                $cumulato_percent_12h = ($radianza_int_whm2 / $teorico_12h) * 100/0.83;
            }
        } else {
            if (is_numeric($radianza_max_cumulo_12h) && is_numeric($teorico_12h)) {
            $cumulato_percent_12h = ($radianza_max_cumulo_12h / $teorico_12h) * 100/0.83;//0.83 fattore di correzzione dello strumento
            
        } else {
            $cumulato_percent_12h = "N/A";
        }
        }
        
        
        //CUMULATO 24H
        $teorico_24h = $row1['teorico_24h'] ?? null;
        if ($teorico_24h === null || $radianza_int_whm2 === null) {
            $cumulato_percent_24h = "N/A";
        } else {
            $cumulato_percent_24h = ($radianza_int_whm2 / $teorico_24h) * 100/0.83;//0.83 fattore di correzzione dello strumento
        }
        
        // 🔽 scrivi entrambi solo ora
        
/*echo "<pre>";
echo '💡 teorico_12h: ' . var_export(round($teorico_12h), true)
     . '  💡 teorico_24h: ' . var_export(round($teorico_24h), true)
     . '<br>';

  echo 'cumulato_att: ' . var_export(round($radianza_int_whm2), true)
     . '  cumulato%_12h: ' . var_export(round($cumulato_percent_12h), true)
     . '  cumulato%_24h: ' . var_export(round($cumulato_percent_24h), true);
echo "</pre>";    
*/        
        $output = "12h: " . ($cumulato_percent_12h !== null ? $cumulato_percent_12h : "N/A") . "\n24h: " . ($cumulato_percent_12h !== null ? $cumulato_percent_24h : "N/A") . "\n";
    
        file_put_contents('cumulato_radianza.txt', $output);
        file_put_contents('debug.txt', "12h = " . var_export($cumulato_percent_12h, true) . "\n24h = " . var_export($cumulato_percent_24h, true) . "\n", FILE_APPEND);
        //return "Scrittura completata";
            
        } catch (\Throwable $e) {
            file_put_contents(__DIR__ . '/log_funz.txt',
                "[DEBUG] ❌ Errore getSolaregiornaliero: " 
                . $e->getMessage() . ' (' . date('Y-m-d H:i:s') . ")\n",
                FILE_APPEND
                
            );
            return "cumulato_percent_h: N/A";
        }

 return [
    'cumulato_percent_12h' => $cumulato_percent_12h,
    'cumulato_percent_24h' => $cumulato_percent_24h
    ];
}



//SOLO CHIAMATA
//echo getSolareteoricoMezzaGiornata($pdo_lettura);

// Funzione per creare un pallino SVG colorato in base al valore delta
function createDeltaIndicator($deltaValue) {
    $deltaValue = floatval($deltaValue);
    
    // Determina il colore in base al valore
    if ($deltaValue > 2.0) {
        $color = '#ff4444'; // Rosso per aumenti significativi
        $title = 'Aumento significativo';
    } elseif ($deltaValue > 0.5) {
        $color = '#ff8800'; // Arancione per aumenti moderati
        $title = 'Aumento moderato';
    } elseif ($deltaValue >= -0.5) {
        $color = '#44aa44'; // Verde per variazioni minime
        $title = 'Stabile';
    } elseif ($deltaValue > -2.0) {
        $color = '#3399FF'; // celeste per diminuzioni moderate
        $title = 'Diminuzione moderata';
    } else {
        $color = '#4444FF'; // Blu scuro per diminuzioni significative
        $title = 'Diminuzione significativa';
    }
    
    return '<svg width="12" height="12" style="vertical-align: middle; margin-right: 5px;" title="' . $title . '">
              <circle cx="6" cy="6" r="4" fill="' . $color . '" stroke="#333" stroke-width="0.5"/>
            </svg>';
}

// Funzione per creare un pallino per la tendenza pressione
function createPressureTrendIndicator($deltaValue) {
    $deltaValue = floatval($deltaValue);
    
    // Per la pressione, soglie diverse (in hPa/mbar)
    if ($deltaValue > 3) {
        $color = '#ff4444'; // Rosso per aumento rapido
        $title = 'Pressione in rapido aumento';
    } elseif ($deltaValue > 1) {
        $color = '#ff8800'; // Arancione per aumento
        $title = 'Pressione in aumento';
    } elseif ($deltaValue > -1) {
        $color = '#44aa44'; // Verde per stabile
        $title = 'Pressione stabile';
    } elseif ($deltaValue > -3) {
        $color = '#3399FF'; // celeste per diminuzione
        $title = 'Pressione in diminuzione';
    } else {
        $color = '#4444FF'; // Blu scuro per diminuzione rapida
        $title = 'Pressione in rapida diminuzione';
    }
    
    return '<svg width="12" height="12" style="vertical-align: middle; margin-right: 5px;" title="' . $title . '">
              <circle cx="6" cy="6" r="4" fill="' . $color . '" stroke="#333" stroke-width="0.5"/>
            </svg>';
}

// Funzione per creare un pallino per il comfort (dewpoint)
function createComfortIndicator($dewpointValue) {
    $dewpointValue = floatval($dewpointValue);
    
   if ($dewpointValue < 8) {
    $color = '#FFF'; // Celeste scuro
    $title = 'NA';
} elseif ($dewpointValue >= 8 && $dewpointValue < 10) {
    $color = '#ADD8E6'; // Celeste
    $title = 'Secco';
} elseif ($dewpointValue >= 10 && $dewpointValue < 16) {
    $color = '#44aa44'; // Verde
    $title = 'Confortevole';
} elseif ($dewpointValue >= 16 && $dewpointValue < 20) {
    $color = '#FFFF99'; // Giallo chiaro
    $title = 'Umido ma confortevole';
} elseif ($dewpointValue >= 20 && $dewpointValue < 24) {
    $color = '#FFA500'; // Arancione
    $title = 'Umido e scomodo';
} else { // >= 24
    $color = '#ff4444'; // Rosso
    $title = 'Opprimente, rischio colpo di calore';
}
    
    return '<svg width="12" height="12" style="vertical-align: middle; margin-right: 5px;" title="' . $title . '">
              <circle cx="6" cy="6" r="4" fill="' . $color . '" stroke="#333" stroke-width="1"/>
            </svg>';
}

function createWindchillHeatIndicator($differenza) {
    // Forza la conversione sicura
    if (!is_numeric($differenza)) {
        $differenza = 0;
    } else {
        $differenza = floatval($differenza);
    }

    if ($differenza < -2) {
        $color = '#0088ff';
        $title = 'Sensazione di freddo significativa';
    } elseif ($differenza > 3) {
        $color = '#ff4444';
        $title = 'Sensazione di caldo significativa';
    } else {
        $color = '#ffffff';
        $title = 'Sensazione neutra';
    }

    return '<svg width="12" height="12" style="vertical-align: middle; margin: 0 3px;" title="' . $title . '">
              <circle cx="6" cy="6" r="4" fill="' . $color . '" stroke="#333" stroke-width="1"/>
            </svg>';
}

    $data = readDataFromFile();
    if (!$data) {
        echo "Nessun dato disponibile.";
        exit;
    }
    
 // Funzione per determinare la fase lunare
    function getFaseLunare($segment) {
        switch ($segment) {
            case 0: return "nuova";
            case 1:
            case 2:
            case 3: return "crescente";
            case 4: return "piena";
            case 5:
            case 6:
            case 7: return "calante";
            default: return "sconosciuta";
        }
    }
    
    $values = explode(',', $data);
    $data_ora = $values[0] ?? 'N/A';
    $ora      = $values[1] ?? 'N/A';

// Funzione per processare il valore della luna
function processLunarValue($valoreLuna) {
    $valoreLuna = trim($valoreLuna);
    
    // Il formato potrebbe essere "7 - 10.7%" o "7-10.7%" o "7 10.7%"
    $parti = [];
    
    if (strpos($valoreLuna, ' - ') !== false) {
        // Formato: "7 - 10.7%" (spazio-trattino-spazio)
        $parti = explode(" - ", $valoreLuna, 2);
        
    } elseif (strpos($valoreLuna, '-') !== false) {
        // Formato: "7-10.7%" (solo trattino)
        $parti = explode("-", $valoreLuna, 2);
        
    } elseif (strpos($valoreLuna, ' ') !== false) {
        // Formato: "7 10.7%" (solo spazio)
        $parti = explode(" ", $valoreLuna, 2);
        
    }
    
    if (count($parti) >= 2) {
        // Se il primo elemento contiene "/", prendi solo la parte prima del "/"
        $primaParte = trim($parti[0]);
        
        if (strpos($primaParte, '/') !== false) {
            $subParti = explode('/', $primaParte);
            $numeroFase = intval($subParti[0] ?? 0);
            
        } else {
            $numeroFase = intval($primaParte);
            
        }
        
        $descrizione = getFaseLunare($numeroFase);
        $percentuale = trim($parti[1]);
        
        // Usa UN SOLO trattino con spazi
        $risultato = $descrizione . ' - ' . $percentuale;
        return $risultato;
    }
    
    return $valoreLuna; // Ritorna il valore originale se non può essere processato
}




// Creiamo una mappa diretta parametro -> valore
$valoriParametri = [];
$keys = array_keys($parametri);
for ($i = 0; $i < count($keys); $i++) {
    $valoriParametri[$keys[$i]] = $values[$i + 2] ?? null;
}
if (!array_key_exists('th0temp-wchill', $valoriParametri)) {
    
}
/// Funzione helper per estrarre il valore numerico dalla stringa temperatura
function extractTemperatureValue($tempString) {
    if (empty($tempString) || $tempString === 'N/A') {
        return null;
    }
    // Rimuove gli ultimi 8 caratteri (spazio + ora formato " HH:MM")
    $tempWithoutTime = substr($tempString, 0, -8);
    // Rimuove il simbolo °C e converte in float
    $tempValue = str_replace('°C', '', $tempWithoutTime);
    return floatval($tempValue);
}

// NOTE CENTRALI, associate a parametri principali
$noteCentrali = [
    // Temperatura attuale → mostra anche delta24 con pallino
    "th0temp-act" => createDeltaIndicator($valoriParametri["th0temp-delta24"] ?? 0) . "\u{0394}24h: " . ($valoriParametri["th0temp-delta24"] ?? 'N/A'),
    
    // Temperatura max → calcola differenza con ieri e mostra pallino + valore di ieri
    "th0temp-dmax" => (function() use ($valoriParametri) {
        $oggi = extractTemperatureValue($valoriParametri["th0temp-dmax"] ?? '');
        $ieri = floatval($valoriParametri["th0temp-ydmax"] ?? 'N/A');
        $delta = ($oggi !== null && $ieri !== null) ? ($ieri-$oggi) : 0;
        
        /* Stampa la differenza e il risultato
    echo "<p>Delta temperatura max (oggi vs ieri): <strong>" 
         . number_format($delta, 1) 
         . "°C</strong> – ";
        */ 
        
        
        return createDeltaIndicator($delta) . "ieri: " . ($valoriParametri["th0temp-ydmax"] ?? 'N/A');
    })(),
    
    // Temperatura min → calcola differenza con ieri e mostra pallino + valore di ieri
    "th0temp-dmin" => (function() use ($valoriParametri) {
        $oggi = extractTemperatureValue($valoriParametri["th0temp-dmin"] ?? '');
        $ieri = floatval($valoriParametri["th0temp-ydmin"] ?? 'N/A');
        $delta = ($oggi !== null && $ieri !== null) ? ($ieri-$oggi) : 0;
        /* Stampa la differenza e il risultato
    echo "<p>Delta temperatura minima (oggi vs ieri): <strong>" 
         . number_format($delta, 1) 
         . "°C</strong> – ";
         */
         
        return createDeltaIndicator($delta) . "ieri: " . ($valoriParametri["th0temp-ydmin"] ?? 'N/A');
    })(),
    
    
    
    // Confort: pallino colorato basato sul valore dewpoint
    "th0dew-act" => "Confort: " . createComfortIndicator($valoriParametri["th0dew-act"] ?? 0),
    
    // Pressione → mostra anche delta24 con pallino
    "thb0press-act" => createPressureTrendIndicator($valoriParametri["thb0press-delta24"] ?? 0) . 
                       "\u{0394}24h: " . ($valoriParametri["thb0press-delta24"] ?? 'N/A'),
    
    // Windchill → pallini per differenza
    "wind0chill-act" => "Impatto " . 
                        createWindchillHeatIndicator($valoriParametri["wind0chill-act"] ?? 0),
    
    // Heat Index → pallini per differenza  
    "th0heatindex-act" => "Impatto " . 
                        createWindchillHeatIndicator($valoriParametri["th0heatindex-act"] ?? 0),
                        
      // radiazione solare: cumulato 12h                  
     "sol0rad-act" => getSolareMassimoGiornaliero($pdo_lettura),
     
     // radiazione solare: cumulato mezza giornata
     //"sol0rad-sum24h" => "Mezza giornata: "                   
];



// COSTRUZIONE ARRAY DATI FINALI
$datiFinali = [];

// Aggiungi data e ora all'inizio
$datiFinali[] = [
    'descrizione' => 'Ultima connessione',
    'valore' => $data_ora . ' - ' . $ora,
    'nota' => ''
];





foreach ($keys as $key) {
    // Se questo parametro è usato come NOTA per un altro, lo saltiamo
    if (in_array($key, [
        "th0temp-delta24",
        "th0temp-ydmax", 
        "th0temp-ydmin",
        "thb0press-delta24"
        // NOTA: "th0dew-act" non si salta, perché è la riga principale con la nota
])) {
    continue;
}

$nota = '';
if (isset($noteCentrali[$key])) {
    $nota = $noteCentrali[$key];
}

// Prendi il valore dalla mappa
$valore = $valoriParametri[$key];

// Gestione speciale per il valore della luna
if ($key === 'mbsystem-lunarpercent') {
    $valore = processLunarValue($valore);
}

$datiFinali[] = [
    'descrizione' => $parametri[$key],
    'nota' => $nota,
    'valore' => $valore
];
}

// Visualizzazione dei dati
//echo "<h2>Dati Meteo</h2>";
echo "<style>
  /* =========================
     Stili base – Mobile first
     ========================= */
  table {
    border-collapse: collapse;
    font-family: Arial, sans-serif;
    font-size: 11px;   /* 11px sui cellulari */
    max-width: 95%;
    margin: 0 auto;
    width: 100%;
    table-layout: fixed;
  }
  th, td {
    padding: 2px 1px 2px 4px;  /* top 2px, right 1px, bottom 2px, left 2px */
    vertical-align: middle;
    overflow: hidden;
    white-space: normal;
  }
  /* Altezza riga minima per mobile: 3 × line-height
     (line-height implicito ≈ 1.2 → 3.6em ) */
  tr {
    height: 3.1em;
  }

  th {
    background-color: #f0f0f0;
    font-weight: bold;
    text-align: left;
  }
 /* Classe per righe con bordo spesso e ombra */
  .riga-separatore {
    border-bottom: 3px solid #666 !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    position: relative;
  }
  
  .riga-separatore td {
    border-bottom: 3px solid #666 !important;
    padding-bottom: 4px; /* Più spazio per l'ombra */
  }
  /* ===============================
     Override per schermi ≥ 768px
     =============================== */
  @media (min-width: 768px) {
    table {
      font-size: 16px; /* testo più grande su desktop */
      width: 85%;
      max-width: 75%;
    }
    th, td {
      padding: 5px 1px 5px 5px; /* left 2px anche su desktop */
      white space: normal;
    }
    /* Rimuovo l’altezza forzata: lascia che si adatti al contenuto */
    tr {
      height: auto;
    }
    .riga-separatore {
      border-bottom: 4px solid #666 !important;
      box-shadow: 0 3px 6px rgba(0,0,0,0.25);
    }
    
    .riga-separatore td {
      border-bottom: 4px solid #666 !important;
      padding-bottom: 6px;
    }
  }
</style>";
echo "<table border='1' cellpadding='10' cellspacing='0'>";
echo "<tr><th style='vertical-align: bottom; background-color: rgba(173, 173, 173, 0.8); position: relative;'>TABELLA METEO:<br>Parametro</th><th style='vertical-align: bottom; background-color: rgba(173, 173, 173, 0.8);'>Note</th><th style='vertical-align: bottom; background-color: rgba(173, 173, 173, 0.8);'>Dati</th></tr>";

$risultati_solari = getSolareteoricoMezzaGiornata($pdo_lettura);
$cumulato_percent_12h = $risultati_solari['cumulato_percent_12h'];
$cumulato_percent_24h = $risultati_solari['cumulato_percent_24h'];

foreach ($datiFinali as $dato) {
    
    // Definisci qui le descrizioni che devono avere il bordo spesso
    $righe_con_bordo_spesso = [
        'Ultima connessione',
        'Luna: Fase e luce disco',
        'Δ °C e hPa - ultima ora',
        'Temperatura min e ora',
        'Umidità min e ora',
        'Punto rugiada min e ora',
        'Pressione @lm min e ora',
        'Direzione del vento',
        'Δ per indice di calore',
        'Radianza cumulata giornaliera',
        'Ultima dato vento'
        // Aggiungi altre descrizioni secondo necessità
    ];
    
    // Controlla se questa riga deve avere il bordo spesso
    $classe_css = in_array($dato['descrizione'], $righe_con_bordo_spesso) ? 'class="riga-separatore"' : '';
    
    echo "<tr $classe_css>";
    echo "<td>" . htmlspecialchars($dato['descrizione']) . "</td>";
    
    // Sostituisci i valori per la riga con descrizione specifica e gestisci il caso di na
    if ($dato['descrizione'] == 'Radianza cumulata giornaliera') {
    $nota_string = "prima metà: " . (in_array($cumulato_percent_12h, ["N/A", "NA"]) || !is_numeric($cumulato_percent_12h) ? "N/A" : round($cumulato_percent_12h) . "%");
    $valore_string = "giorno intero: " . (in_array($cumulato_percent_24h, ["N/A", "NA"]) || !is_numeric($cumulato_percent_24h) ? "N/A" : round($cumulato_percent_24h) . "%");
        
        echo "<td>" . $nota_string . "</td>";
        echo "<td>" . htmlspecialchars($valore_string) . "</td>";
    } else {
        echo "<td>" . $dato['nota'] . "</td>";
        echo "<td>" . htmlspecialchars($dato['valore'] ?? 'N/A') . "</td>";
    }
    
    echo "</tr>";
}
echo "</table>";

// Legenda dei colori
echo "<div style='margin-top: 2px; padding: 2px; font-size: 11px;   background-color: #f9f9f9; border: 1px solid #ddd; font-family: monospace; line-height: 1.0'>";
echo "<h3>Legenda pallini colorati:</h3>";
echo "<div style='display: flex; flex-wrap: wrap; gap: 8px;'>";
echo "<div>" . createDeltaIndicator(3) . " Aumento significativo (>2°C)</div>";
echo "<div>" . createDeltaIndicator(1) . " Aumento moderato (0.6-2°C)</div>";
echo "<div>" . createDeltaIndicator(0) . " Stabile (-0.5 / +0.5°C)</div>";
echo "<div>" . createDeltaIndicator(-1) . " Diminuzione moderata (-0.6/-2°C)</div>";
echo "<div>" . createDeltaIndicator(-3) . " Diminuzione significativa (<-2°C)</div>";
echo "</div>";
echo "<p><em>Per la pressione le soglie sono in hPa: >3, 1-3, -1/+1, -1/-3, <-3</em></p>";
echo "<h4>Comfort (Dewpoint, fonte: bom.gov.au):</h4>";
echo "<div style='display: flex; flex-wrap: wrap; gap: 5px;'>";
echo "<div>" . createComfortIndicator(7) . " N/A (<8°C)</div>";
echo "<div>" . createComfortIndicator(8) . " Secco (8-9°C)</div>";
echo "<div>" . createComfortIndicator(15) . " Gradevole (10-15°C)</div>";
echo "<div>" . createComfortIndicator(16) . " Gradevole-Umido (16-19°C)</div>";
echo "<div>" . createComfortIndicator(20) . " Umido-scomodo (20-23°C)</div>";
echo "<div>" . createComfortIndicator(24) . " Condizioni estreme (>24°C)</div>";
echo "</div>";
echo "<h4>Windchill / Heat Index:</h4>";
echo "<div style='display: flex; flex-wrap: wrap; gap: 5px;'>";
echo "<div>" . createWindchillHeatIndicator(-3) . " Sensazione di freddo (<-2°C)</div>";
echo "<div>" . createWindchillHeatIndicator(0) . " Sensazione neutra (-2°C / +3°C)</div>";
echo "<div>" . createWindchillHeatIndicator(3.2) . " Sensazione di caldo (>+3°C)</div>";
echo "</div>";
echo "</div>";
?>