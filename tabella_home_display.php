<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/*
┌─────────────────────────────────────────────────────────────────────────────┐
│ FILE: tabella_home_display.php                                              │
│ SCOPO: Genera tabella HTML con dati meteo + calcoli radianza solare        │
├─────────────────────────────────────────────────────────────────────────────┤
│ GUIDA RAPIDA PER SVILUPPATORI                                               │
├─────────────────────────────────────────────────────────────────────────────┤
│ 1. INPUT: Legge CSV da 'meteobridge/dati_temperatura.txt'                  │
│ 2. OUTPUT: Tabella HTML responsive con indicatori SVG colorati             │
│                                                                              │
│ 3. DIPENDENZE RICHIESTE:                                                    │
│    • meteobridge/tabella_home.php (array $parametri con mappature)         │
│    • datetime_helper.php (funzioni get_now(), get_datetime(), ecc.)        │
│    • env_tables_helper.php (gestione ambiente test/produzione)             │
│    • ../envelop_lettura.php (connessione DB → variabile $pdo_lettura)      │
│                                                                              │
│ 4. DATABASE: Richiede accesso a:                                            │
│    • solar_data_siena (dati teorici radianza per giorno_anno)              │
│    • dati_meteo_simignano (dati effettivi radianza con timestamp)          │
│                                                                              │
│ 5. COSTANTI GLOBALI:                                                        │
│    • OGGI_GIORNO_ANNO: numero giorno dell'anno (1-366)                     │
│    • OGGI_DATA_SQL: data odierna formato 'YYYY-MM-DD%' per LIKE            │
│                                                                              │
│ 6. FUNZIONI PRINCIPALI:                                                     │
│    • getSolareMassimoGiornaliero(): massimo teorico + ora (UTC→locale)     │
│    • getSolareteoricoMezzaGiornata(): calcola % cumulato 12h/24h           │
│    • createDeltaIndicator(): pallini SVG per variazioni temperatura        │
│    • createComfortIndicator(): pallini SVG per comfort (dewpoint)          │
│                                                                              │
│ 7. LOGICA CALCOLO RADIANZA 12H:                                            │
│    • SEMPRE calcola valore base (radianza_attuale / teorico_12h)           │
│    • Se disponibile ora_massima_utc E siamo dopo → cerca picco storico     │
│    • Altrimenti usa valore base (garantisce sempre un risultato)           │
│                                                                              │
│ 8. LOGICA CALCOLO RADIANZA 24H:                                            │
│    • Semplice: radianza_attuale / teorico_24h                              │
│    • Fattore correzione strumento: 0.83 (applicato a entrambi) _ora ad 1           │
│                                                                              │
│ 9. GESTIONE ERRORI:                                                         │
│    • Valori mancanti → 'N/A' (mai null o 0 ambigui)                        │
│    • Log errori → log_funz.txt                                             │
│    • Log debug calcoli → cumulato_radianza.txt                             │
│                                                                              │
│ 10. FILE GENERATI:                                                          │
│     • cumulato_radianza.txt (log calcoli 12h/24h con timestamp)            │
│     • log_funz.txt (errori SQL/parsing date)                               │
│     • log_delta.txt (errori connessione PDO)                               │
│                                                                              │
│ 11. FORMATO CSV INPUT (dati_temperatura.txt):                              │
│     data,ora,param1,param2,...,paramN                                       │
│     Ordine colonne definito in array $parametri da tabella_home.php        │
│                                                                              │
│ 12. VALIDAZIONE VALORI:                                                     │
│     • pulisciValoreNumerico(): accetta solo numeri/punto/meno              │
│     • Ritorna 'NA' per valori non numerici                                 │
│     • Trim automatico e limitazione a 7 caratteri                          │
│                                                                              │
│ 13. RESPONSIVE DESIGN:                                                      │
│     • Mobile: font 11px, tabella 95% larghezza                             │
│     • Desktop (≥768px): font 16px, tabella 75% larghezza                   │
│     • Righe separatore: bordo 3-4px con ombra                              │
│                                                                              │
│ 14. SICUREZZA:                                                              │
│     • Tutti i valori user-facing passano per htmlspecialchars()            │
│     • Query DB usano prepared statements (no SQL injection)                │
│                                                                              │
│ 15. DEBUG:                                                                  │
│     • Abilita echo temporanei per diagnostica                              │
│     • Verifica permessi scrittura su cartella per file .txt                │
│     • Controlla esistenza $pdo_lettura prima delle query                   │
└─────────────────────────────────────────────────────────────────────────────┘
*/

// =====================================
// CONFIGURAZIONE E DIPENDENZE
// =====================================
require_once __DIR__ . '/meteobridge/tabella_home.php';
require_once __DIR__ . '/datetime_helper.php';
require_once __DIR__ . '/env_tables_helper.php';
require_once __DIR__ . '/../envelop_lettura.php';

$table_name = table_name('dati_meteo_simignano');
define('OGGI_GIORNO_ANNO', get_day_of_year());
define('OGGI_DATA_SQL', get_now('Y-m-d').'%');

// Verifica connessione database
if (!isset($pdo_lettura)) {
    file_put_contents('log_delta.txt', "[ERROR] PDO non definito - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    die("Errore: connessione database non disponibile");
}

// =====================================
// FUNZIONI UTILITY
// =====================================

/**
 * Legge il contenuto del file dati temperatura
 */
function readDataFromFile() {
    $file = 'meteobridge/dati_temperatura.txt';
    return file_exists($file) ? file_get_contents($file) : null;
}

/**
 * Pulisce e valida valori numerici (gestisce NA e valori non numerici)
 */
function pulisciValoreNumerico($valore) {
    $val = ltrim($valore);
    $val = substr($val, 0, 7);
    $val = preg_replace('/[^0-9\.\-]/', '', $val);
    return is_numeric($val) ? $valore : 'NA';
}

/**
 * Determina la fase lunare dal segmento (0-7)
 */
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

/**
 * Estrae il valore numerico da una stringa temperatura (rimuove ora e unità)
 */
function extractTemperatureValue($tempString) {
    if (empty($tempString) || $tempString === 'N/A') {
        return null;
    }
    $tempWithoutTime = substr($tempString, 0, -8);
    $tempValue = str_replace('°C', '', $tempWithoutTime);
    return floatval($tempValue);
}

/**
 * Processa il valore della luna da formato "7 - 10.7%" a "calante - 10.7%"
 */
function processLunarValue($valoreLuna) {
    $valoreLuna = trim($valoreLuna);
    $parti = [];
    
    if (strpos($valoreLuna, ' - ') !== false) {
        $parti = explode(" - ", $valoreLuna, 2);
    } elseif (strpos($valoreLuna, '-') !== false) {
        $parti = explode("-", $valoreLuna, 2);
    } elseif (strpos($valoreLuna, ' ') !== false) {
        $parti = explode(" ", $valoreLuna, 2);
    }
    
    if (count($parti) >= 2) {
        $primaParte = trim($parti[0]);
        
        if (strpos($primaParte, '/') !== false) {
            $subParti = explode('/', $primaParte);
            $numeroFase = intval($subParti[0] ?? 0);
        } else {
            $numeroFase = intval($primaParte);
        }
        
        $descrizione = getFaseLunare($numeroFase);
        $percentuale = trim($parti[1]);
        
        return $descrizione . ' - ' . $percentuale;
    }
    
    return $valoreLuna;
}

// =====================================
// FUNZIONI INDICATORI VISIVI (SVG)
// =====================================

function createDeltaIndicator($deltaValue) {
    $deltaValue = floatval($deltaValue);
    
    if ($deltaValue > 2.0) {
        $color = '#ff4444';
        $title = 'Aumento significativo';
    } elseif ($deltaValue > 0.5) {
        $color = '#ff8800';
        $title = 'Aumento moderato';
    } elseif ($deltaValue >= -0.5) {
        $color = '#44aa44';
        $title = 'Stabile';
    } elseif ($deltaValue > -2.0) {
        $color = '#3399FF';
        $title = 'Diminuzione moderata';
    } else {
        $color = '#4444FF';
        $title = 'Diminuzione significativa';
    }
    
    return '<svg width="12" height="12" style="vertical-align: middle; margin-right: 5px;" title="' . $title . '">
              <circle cx="6" cy="6" r="4" fill="' . $color . '" stroke="#333" stroke-width="0.5"/>
            </svg>';
}

function createPressureTrendIndicator($deltaValue) {
    $deltaValue = floatval($deltaValue);
    
    if ($deltaValue > 3) {
        $color = '#ff4444';
        $title = 'Pressione in rapido aumento';
    } elseif ($deltaValue > 1) {
        $color = '#ff8800';
        $title = 'Pressione in aumento';
    } elseif ($deltaValue > -1) {
        $color = '#44aa44';
        $title = 'Pressione stabile';
    } elseif ($deltaValue > -3) {
        $color = '#3399FF';
        $title = 'Pressione in diminuzione';
    } else {
        $color = '#4444FF';
        $title = 'Pressione in rapida diminuzione';
    }
    
    return '<svg width="12" height="12" style="vertical-align: middle; margin-right: 5px;" title="' . $title . '">
              <circle cx="6" cy="6" r="4" fill="' . $color . '" stroke="#333" stroke-width="0.5"/>
            </svg>';
}

function createComfortIndicator($dewpointValue) {
    $dewpointValue = floatval($dewpointValue);
    
    if ($dewpointValue < 8) {
        $color = '#FFF';
        $title = 'NA';
    } elseif ($dewpointValue >= 8 && $dewpointValue < 10) {
        $color = '#ADD8E6';
        $title = 'Secco';
    } elseif ($dewpointValue >= 10 && $dewpointValue < 16) {
        $color = '#44aa44';
        $title = 'Confortevole';
    } elseif ($dewpointValue >= 16 && $dewpointValue < 20) {
        $color = '#FFFF99';
        $title = 'Umido ma confortevole';
    } elseif ($dewpointValue >= 20 && $dewpointValue < 24) {
        $color = '#FFA500';
        $title = 'Umido e scomodo';
    } else {
        $color = '#ff4444';
        $title = 'Opprimente, rischio colpo di calore';
    }
    
    return '<svg width="12" height="12" style="vertical-align: middle; margin-right: 5px;" title="' . $title . '">
              <circle cx="6" cy="6" r="4" fill="' . $color . '" stroke="#333" stroke-width="1"/>
            </svg>';
}

function createWindchillHeatIndicator($differenza) {
    $valore = pulisciValoreNumerico($differenza);
    if ($valore === 'NA') {
        return '<svg width="12" height="12" style="vertical-align: middle; margin: 0 3px;" title="N/A">
                  <circle cx="6" cy="6" r="4" fill="#ffffff" stroke="#333" stroke-width="1"/>
                </svg>';
    }
    
    $differenza = floatval($differenza);

    if ($differenza < -2) {
        $color = '#0088ff';
        $title = 'Sensazione di freddo significativa';
    } elseif ($differenza > 2) {
        $color = '#FFA500';
        $title = 'Sensazione di caldo significativa';
    } else {
        $color = '#ffffff';
        $title = 'Sensazione neutra';
    }

    return '<svg width="12" height="12" style="vertical-align: middle; margin: 0 3px;" title="' . $title . '">
              <circle cx="6" cy="6" r="4" fill="' . $color . '" stroke="#333" stroke-width="1"/>
            </svg>';
}

// =====================================
// FUNZIONI CALCOLO DATI SOLARI
// =====================================

/**
 * Restituisce il massimo teorico giornaliero e l'ora in formato locale
 */
function getSolareMassimoGiornaliero(?PDO $pdo_lettura): string {
    if ($pdo_lettura === null) {
        return "Teor Max e ora: N/A";
    }

    try {
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
        $stmt->execute([':oggi' => OGGI_GIORNO_ANNO]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return "Teor Max e ora: N/A";
        }

        $irradianza = $row['irradianza_max_w_m2'];
        $oraUtc     = $row['ora_massima_utc'];
        $giornoAnno = intval($row['giorno_anno']);

        // Conversione da UTC a Europe/Rome
        $annoCorrente = intval(date('Y'));
        $zUtc = $giornoAnno;
        $datetimeUtcStr = sprintf('%d %d %s', $annoCorrente, $zUtc, $oraUtc);

        $dtUtc = DateTime::createFromFormat(
            'Y z H:i:s',
            $datetimeUtcStr,
            new DateTimeZone('UTC')
        );
        
        if (!$dtUtc) {
            return "Teor Max e ora: {$irradianza} @ N/A";
        }

        $dtUtc->setTimezone(new DateTimeZone('Europe/Rome'));
        $oraLocale = $dtUtc->format('H:i');

        return "Teor Max e ora: {$irradianza} @ {$oraLocale}";

    } catch (\Throwable $e) {
        file_put_contents(
            __DIR__ . '/log_funz.txt',
            "[ERROR] getSolareMassimoGiornaliero: " . $e->getMessage() . ' (' . date('Y-m-d H:i:s') . ")\n",
            FILE_APPEND
        );
        return "Teor Max e ora: N/A";
    }
}

/**
 * Calcola le percentuali di radianza cumulata 12h e 24h
 * FIXATO: ora ritorna correttamente i valori numerici o "N/A"
 */
function getSolareteoricoMezzaGiornata(?PDO $pdo_lettura) {
    if ($pdo_lettura === null) {
        return [
            'cumulato_percent_12h' => 'N/A',
            'cumulato_percent_24h' => 'N/A'
        ];
    }

    try {
        global $table_name;
        $pdo_lettura->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 1. RECUPERA DATI TEORICI
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
        $stmt->execute([':oggi' => OGGI_GIORNO_ANNO]);
        $row1 = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $teorico_12h = isset($row1['teorico_12h']) && is_numeric($row1['teorico_12h']) 
                       ? floatval($row1['teorico_12h']) : null;
        $teorico_24h = isset($row1['teorico_24h']) && is_numeric($row1['teorico_24h']) 
                       ? floatval($row1['teorico_24h']) : null;
        $ora_massima_utc = $row1['ora_massima_utc'] ?? null;

        // 2. RECUPERA RADIANZA ATTUALE (ultimi 15 minuti)
        $sql = "
            SELECT radianza_int_whm2
            FROM $table_name
            WHERE data_ora BETWEEN :start_time AND :end_time
            AND data_ora LIKE :oggi
            AND radianza_int_whm2 IS NOT NULL
            ORDER BY data_ora DESC
            LIMIT 1
        ";
        
        $stmt = $pdo_lettura->prepare($sql);
        $stmt->execute([
            ':start_time' => date("Y-m-d H:i:s", strtotime(get_now() . " -15 minutes")),
            ':end_time' => get_now(),
            ':oggi' => OGGI_DATA_SQL
        ]);
        $row2 = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $radianza_attuale = isset($row2['radianza_int_whm2']) && is_numeric($row2['radianza_int_whm2'])
                            ? floatval($row2['radianza_int_whm2']) : null;

        // 3. CALCOLA PERCENTUALE 24H (sempre basata su valore attuale)
        $cumulato_percent_24h = 'N/A';
        if ($teorico_24h !== null && $radianza_attuale !== null && $teorico_24h > 0) {
            $cumulato_percent_24h = round(($radianza_attuale / $teorico_24h)*100, 1);
        }

        // 4. CALCOLA PERCENTUALE 12H
        // Logica: se abbiamo i dati base, calcola sempre (come fa il 24h)
        // Poi, se disponibile, usa la logica avanzata con ora_massima_utc
        $cumulato_percent_12h = 'N/A';
        $ora_attuale = get_datetime();
        
        if ($teorico_12h !== null && $radianza_attuale !== null && $teorico_12h > 0) {
            // CASO BASE: usa sempre il valore attuale come fallback
            $cumulato_percent_12h = round(($radianza_attuale / $teorico_12h)*100, 1);
            
            // CASO AVANZATO: se abbiamo ora_massima_utc, possiamo raffinare il calcolo
            if ($ora_massima_utc !== null) {
                try {
                    $ora_massima_utc_obj = new DateTime($ora_massima_utc, new DateTimeZone('UTC'));
                    $ora_massima_loc = (clone $ora_massima_utc_obj)->setTimezone(new DateTimeZone('Europe/Rome'));
                    
                    // Se siamo DOPO l'ora massima, cerca il picco storico
                    if ($ora_attuale > $ora_massima_loc) {
                        $ora_massima_loc_start = (clone $ora_massima_loc)->modify('-5 minutes')->format('Y-m-d H:i:s');
                        $ora_massima_loc_end = (clone $ora_massima_loc)->modify('+5 minutes')->format('Y-m-d H:i:s');
                        
                        $sql = "
                            SELECT radianza_int_whm2
                            FROM $table_name
                            WHERE data_ora BETWEEN :ora_start AND :ora_end
                            AND data_ora LIKE :oggi
                            AND radianza_int_whm2 IS NOT NULL
                            ORDER BY radianza_int_whm2 DESC
                            LIMIT 1
                        ";
                        
                        $stmt = $pdo_lettura->prepare($sql);
                        $stmt->execute([
                            ':ora_start' => $ora_massima_loc_start,
                            ':ora_end' => $ora_massima_loc_end,
                            ':oggi' => OGGI_DATA_SQL
                        ]);
                        $row3 = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Se troviamo un picco storico, sovrascrive il valore base
                        if ($row3 && isset($row3['radianza_int_whm2']) && is_numeric($row3['radianza_int_whm2'])) {
                            $radianza_picco_12h = floatval($row3['radianza_int_whm2']);
                            $cumulato_percent_12h = round(($radianza_picco_12h / $teorico_12h)*100, 1);
                        }
                        // Altrimenti mantiene il valore base già calcolato
                    }
                    // Se siamo prima dell'ora massima, mantiene il valore base già calcolato
                } catch (\Exception $e) {
                    // In caso di errore, mantiene il valore base già calcolato
                    file_put_contents(
                        __DIR__ . '/log_funz.txt',
                        "[WARNING] Errore parsing ora_massima_utc per 12h: " . $e->getMessage() . "\n",
                        FILE_APPEND
                    );
                }
            }
            // Se ora_massima_utc è null, mantiene semplicemente il valore base già calcolato
        }

        // 5. LOG DEBUG (opzionale, commentare in produzione)
        $debug_msg = sprintf(
            "[%s] 12h: %s%% (teorico: %s) | 24h: %s%% (teorico: %s)\n",
            date('Y-m-d H:i:s'),
            $cumulato_percent_12h !== 'N/A' ? $cumulato_percent_12h : 'N/A',
            $teorico_12h ?? 'null',
            $cumulato_percent_24h !== 'N/A' ? $cumulato_percent_24h : 'N/A',
            $teorico_24h ?? 'null'
        );
        file_put_contents(__DIR__ . '/cumulato_radianza.txt', $debug_msg);

        return [
            'cumulato_percent_12h' => $cumulato_percent_12h,
            'cumulato_percent_24h' => $cumulato_percent_24h
        ];

    } catch (\Throwable $e) {
        file_put_contents(
            __DIR__ . '/log_funz.txt',
            "[ERROR] getSolareteoricoMezzaGiornata: " . $e->getMessage() . ' (' . date('Y-m-d H:i:s') . ")\n",
            FILE_APPEND
        );
        return [
            'cumulato_percent_12h' => 'N/A',
            'cumulato_percent_24h' => 'N/A'
        ];
    }
}



// =====================================
// LETTURA E PARSING DATI
// =====================================

$data = readDataFromFile();
if (!$data) {
    echo "Nessun dato disponibile.";
    exit;
}

$values = explode(',', $data);
$data_ora = $values[0] ?? 'N/A';
$ora = $values[1] ?? 'N/A';

// Crea mappa parametro => valore
$valoriParametri = [];
$keys = array_keys($parametri);
for ($i = 0; $i < count($keys); $i++) {
    $valoriParametri[$keys[$i]] = $values[$i + 2] ?? null;
}

// =====================================
// CALCOLO DATI SOLARI (una sola volta)
// =====================================
$risultati_solari = getSolareteoricoMezzaGiornata($pdo_lettura);
$cumulato_12h = $risultati_solari['cumulato_percent_12h'];
$cumulato_24h = $risultati_solari['cumulato_percent_24h'];

// =====================================
// COSTRUZIONE NOTE CENTRALI
// =====================================
$noteCentrali = [
    "th0temp-act" => createDeltaIndicator($valoriParametri["th0temp-delta24"] ?? 0) . 
                     "\u{0394}24h (attuale - 24h) = " . ($valoriParametri["th0temp-delta24"] ?? 'N/A'),
    
    "th0temp-dmax" => (function() use ($valoriParametri) {
        $oggi = extractTemperatureValue($valoriParametri["th0temp-dmax"] ?? '');
        $ieri = floatval($valoriParametri["th0temp-ydmax"] ?? 0);
        $delta = ($oggi !== null && $ieri !== 0) ? ($ieri - $oggi) : 0;
        return createDeltaIndicator($delta) . "ieri: " . ($valoriParametri["th0temp-ydmax"] ?? 'N/A');
    })(),
    
    "th0temp-dmin" => (function() use ($valoriParametri) {
        $oggi = extractTemperatureValue($valoriParametri["th0temp-dmin"] ?? '');
        $ieri = floatval($valoriParametri["th0temp-ydmin"] ?? 0);
        $delta = ($oggi !== null && $ieri !== 0) ? ($ieri - $oggi) : 0;
        return createDeltaIndicator($delta) . "ieri: " . ($valoriParametri["th0temp-ydmin"] ?? 'N/A');
    })(),
    
    "th0dew-act" => "Confort: " . createComfortIndicator($valoriParametri["th0dew-act"] ?? 0),
    
    "thb0press-act" => createPressureTrendIndicator($valoriParametri["thb0press-delta24"] ?? 0) . 
                       "\u{0394}24h (attuale - 24h) =  " . ($valoriParametri["thb0press-delta24"] ?? 'N/A'),
    
    "wind0chill-act" => "Impatto " . createWindchillHeatIndicator($valoriParametri["wind0chill-act"] ?? 0),
    
    "th0heatindex-act" => "Impatto " . createWindchillHeatIndicator($valoriParametri["th0heatindex-act"] ?? 0),
    
    "sol0rad-act" => getSolareMassimoGiornaliero($pdo_lettura),

    "th0temp-age" => "minuti dall'ultima connessione"
];

// =====================================
// COSTRUZIONE ARRAY DATI FINALI
// =====================================
$datiFinali = [];

// Riga iniziale: data e ora
$datiFinali[] = [
    'descrizione' => 'Ultima connessione',
    'valore' => $data_ora . ' - ' . $ora,
    'nota' => ''
];

// Parametri da saltare (usati come note)
$parametriDaSaltare = [
    "th0temp-delta24",
    "th0temp-ydmax", 
    "th0temp-ydmin",
    "thb0press-delta24"
];

foreach ($keys as $key) {
    if (in_array($key, $parametriDaSaltare)) {
        continue;
    }
    
    $nota = $noteCentrali[$key] ?? '';
    $valore = $valoriParametri[$key];
    
    // Gestione speciale per alcuni parametri
    if ($key === 'mbsystem-lunarpercent') {
        $valore = processLunarValue($valore);
    } elseif ($key !== 'wind0dir-act' && $key !== 'mbsystem-sunrise' && $key !== 'th0temp-age') {
        $valore = pulisciValoreNumerico($valore);
    }
    
    $datiFinali[] = [
        'descrizione' => $parametri[$key],
        'nota' => $nota,
        'valore' => $valore
    ];
}

// =====================================
// OUTPUT HTML
// =====================================
?>
<style>
  table {
    border-collapse: collapse;
    font-family: Arial, sans-serif;
    font-size: 11px;
    max-width: 95%;
    margin: 0 auto;
    width: 100%;
    table-layout: fixed;
  }
  th, td {
    padding: 2px 1px 2px 4px;
    vertical-align: middle;
    overflow: hidden;
    white-space: normal;
  }
  tr {
    height: 3.1em;
  }
  th {
    background-color: #f0f0f0;
    font-weight: bold;
    text-align: left;
  }
  .riga-separatore {
    border-bottom: 3px solid #666 !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    position: relative;
  }
  .riga-separatore td {
    border-bottom: 3px solid #666 !important;
    padding-bottom: 4px;
  }
  
  @media (min-width: 768px) {
    table {
      font-size: 16px;
      width: 85%;
      max-width: 75%;
    }
    th, td {
      padding: 5px 1px 5px 5px;
      white-space: normal;
    }
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
</style>

<table border='1' cellpadding='10' cellspacing='0'>
<tr>
  <th style='vertical-align: top; background-color: rgba(173, 173, 173, 0.8);'>TABELLA METEO:<br>Parametro</th>
  <th style='vertical-align: top; background-color: rgba(173, 173, 173, 0.8);'>Note</th>
  <th style='vertical-align: top; background-color: rgba(173, 173, 173, 0.8);'>Dati</th>
</tr>

<?php
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
    'Agg. sens t/h-p-vento/rad (min fa)'
];

foreach ($datiFinali as $dato) {
    $classe_css = in_array($dato['descrizione'], $righe_con_bordo_spesso) ? 'class="riga-separatore"' : '';
    
    echo "<tr $classe_css>";
    echo "<td>" . htmlspecialchars($dato['descrizione']) . "</td>";
    
    // FIXATO: gestione corretta della riga radianza cumulata
    if ($dato['descrizione'] == 'Radianza cumulata giornaliera') {
        // Formatta valori 12h e 24h (usa il valore, non la chiave!)
        $nota_12h = ($cumulato_12h !== 'N/A' && is_numeric($cumulato_12h)) 
                    ? round($cumulato_12h) . "%" 
                    : "N/A";
        $valore_24h = ($cumulato_24h !== 'N/A' && is_numeric($cumulato_24h)) 
                      ? round($cumulato_24h) . "%" 
                      : "N/A";
        
        echo "<td>prima metà: " . $nota_12h . "</td>";
        echo "<td>giorno intero: " . $valore_24h . "</td>";
    } else {
        echo "<td>" . $dato['nota'] . "</td>";
        echo "<td>" . htmlspecialchars($dato['valore'] ?? 'N/A') . "</td>";
    }
    
    echo "</tr>";
}
?>
</table>

<!-- Legenda colori -->
<div style='margin-top: 2px; padding: 2px; font-size: 11px; background-color: #f9f9f9; border: 1px solid #ddd; font-family: monospace; line-height: 1.0'>
<h3>Legenda pallini colorati:</h3>
<div style='display: flex; flex-wrap: wrap; gap: 8px;'>
<div><?= createDeltaIndicator(3) ?> Aumento significativo (&gt;2°C)</div>
<div><?= createDeltaIndicator(1) ?> Aumento moderato (0.6-2°C)</div>
<div><?= createDeltaIndicator(0) ?> Stabile (-0.5 / +0.5°C)</div>
<div><?= createDeltaIndicator(-1) ?> Diminuzione moderata (-0.6/-2°C)</div>
<div><?= createDeltaIndicator(-3) ?> Diminuzione significativa (&lt;-2°C)</div>
</div>
<p><em>Per la pressione le soglie sono in hPa: &gt;3, 1-3, -1/+1, -1/-3, &lt;-3</em></p>

<h4>Comfort (Dewpoint, fonte: bom.gov.au):</h4>
<div style='display: flex; flex-wrap: wrap; gap: 5px;'>
<div><?= createComfortIndicator(7) ?> N/A (&lt;8°C)</div>
<div><?= createComfortIndicator(8) ?> Secco (8-9°C)</div>
<div><?= createComfortIndicator(15) ?> Gradevole (10-15°C)</div>
<div><?= createComfortIndicator(16) ?> Gradevole-Umido (16-19°C)</div>
<div><?= createComfortIndicator(20) ?> Umido-scomodo (20-23°C)</div>
<div><?= createComfortIndicator(24) ?> Condizioni estreme (&gt;24°C)</div>
</div>

<h4>Windchill / Heat Index:</h4>
<div style='display: flex; flex-wrap: wrap; gap: 5px;'>
<div><?= createWindchillHeatIndicator(-3) ?> Sensazione di freddo (&lt;-2°C)</div>
<div><?= createWindchillHeatIndicator(0) ?> Sensazione neutra (-2°C / +2°C)</div>
<div><?= createWindchillHeatIndicator(3.2) ?> Sensazione di caldo (&gt;+2°C)</div>
</div>
</div>