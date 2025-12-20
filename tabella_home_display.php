<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/*
┌─────────────────────────────────────────────────────────────────────────────┐
│ GUIDA RAPIDA PER SVILUPPATORI                                               │
├─────────────────────────────────────────────────────────────────────────────┤
│ SCOPO: Tabella meteo responsive + calcoli radianza solare + lightbox foto  │
│        alba/tramonto (ultime 20h)                                           │
├─────────────────────────────────────────────────────────────────────────────┤
│ DIPENDENZE FILE:                                                            │
│  • meteobridge/tabella_home.php (array $parametri)                         │
│  • datetime_helper.php (get_now(), get_datetime(), get_day_of_year())     │
│  • env_tables_helper.php (table_name() per test/produzione)               │
│  • ../envelop_lettura.php ($pdo_lettura)                                  │
│  • meteobridge/dati_temperatura.txt (CSV: data,ora,param1,...,paramN)     │
├─────────────────────────────────────────────────────────────────────────────┤
│ DATABASE (PDO):                                                             │
│  • solar_data_siena: dati teorici radianza (giorno_anno, irradianza_max,  │
│                      ora_massima_utc, energia_totale_wh_m2)               │
│  • dati_meteo_simignano: dati effettivi (data_ora, radianza_int_whm2)     │
│  • DB_immagini_36h: foto alba/tramonto (FILE, DATA_ORA, Temp, alba_tramonto)│
├─────────────────────────────────────────────────────────────────────────────┤
│ COSTANTI GLOBALI:                                                           │
│  OGGI_GIORNO_ANNO: int (1-366)                                             │
│  OGGI_DATA_SQL: string 'YYYY-MM-DD%' per LIKE queries                      │
├─────────────────────────────────────────────────────────────────────────────┤
│ FUNZIONI CHIAVE:                                                            │
│                                                                              │
│ • getSolareMassimoGiornaliero($pdo): Ritorna "Teor Max e ora: 850 @ 13:45" │
│   → Query solar_data_siena, converte UTC→Europe/Rome                       │
│                                                                              │
│ • getSolareteoricoMezzaGiornata($pdo): Array con %12h e %24h               │
│   → 12h: calcolo base (radianza/teorico), poi cerca picco storico se dopo  │
│          ora_massima_utc. Garantisce sempre risultato.                     │
│   → 24h: semplice radianza_attuale/teorico_24h                             │
│   → Ritorna ['cumulato_percent_12h' => float|'N/A',                        │
│               'cumulato_percent_24h' => float|'N/A']                        │
│                                                                              │
│ • pulisciValoreNumerico($val): Valida numeri, ritorna 'NA' se invalido     │
│                                                                              │
│ • createIndicator($value): SVG pallini colorati per:                      │
│   - Delta temperatura (5 soglie: >2, 0.6-2, -0.5/+0.5, -0.6/-2, <-2)      │
│   - Comfort dewpoint (6 soglie BOM: <8, 8-9, 10-15, 16-19, 20-23, ≥24)    │
│   - Pressione (soglie hPa: >3, 1-3, -1/+1, -1/-3, <-3)                    │
│   - Windchill/Heat (3 soglie: <-2, -2/+2, >2)                             │
│                                                                              │
│ • cropImageBottom(src, px, callback): Taglia px dal basso via Canvas       │
│                                                                              │
│ • apriLightboxFiltrato(flag): Filtra foto per alba(1)/tramonto(2) + 23h58'    │
│   → Filtro temporale: new Date(Date.now() - (2060601000))              │
├─────────────────────────────────────────────────────────────────────────────┤
│ LIGHTBOX NAVIGAZIONE:                                                       │
│  Tastiera: ← (più recente), → (più vecchia), Esc (chiudi)                 │
│  Touch: swipe left/right (soglia 50px)                                     │
│  Pulsanti: prev/next disabilitati agli estremi                             │
├─────────────────────────────────────────────────────────────────────────────┤
│ RESPONSIVE:                                                                 │
│  Mobile (<768px): font 11px, tabella 95%, icone 12×12px                    │
│  Desktop (≥768px): font 16px, tabella 75%, icone 12×12px                   │
│  Mobile (<480px): icone alba/tramonto 14×14px, label 8px                   │
├─────────────────────────────────────────────────────────────────────────────┤
│ GESTIONE ERRORI:                                                            │
│  • Valori mancanti → 'N/A' (mai null/0 ambigui)                           │
│  • log_delta.txt: errori PDO                                               │
│  • log_funz.txt: errori SQL/parsing date                                   │
│  • cumulato_radianza.txt: debug calcoli con timestamp                      │
├─────────────────────────────────────────────────────────────────────────────┤
│ SICUREZZA:                                                                  │
│  • htmlspecialchars() su tutti gli output                                  │
│  • Prepared statements per query DB                                        │
│  • Validazione input con regex [^0-9.-]                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│ ESTENSIBILITÀ:                                                              │
│  • Nuovo parametro: modifica meteobridge/tabella_home.php + CSV            │
│  • Cambia soglie: edit funzioni create*Indicator()                         │
│  • Modifica filtro 20h: cambia moltiplicatore (((23 * 60 * 60)+(58*60)) * 1000))       │
├─────────────────────────────────────────────────────────────────────────────┤
│ TROUBLESHOOTING:                                                            │
│  Tabella vuota → verifica CSV esiste e ha permessi lettura                │
│  Radianza N/A → controlla $pdo_lettura e log_funz.txt                     │
│  Lightbox vuoto → verifica record DB con alba_tramonto=1/2 nelle ultime 23h58'
per non generare sovrapposizioni tra due tramonti│
│  Icone invisibili → controlla CSS caricato, usa !important se necessario   │
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

    "mbsystem-sunrise" => (function() {

    $svg_alba = '
    <span class="icon-sun-inline">
        <a href="#" onclick="apriLightboxFiltrato(1); return false;" data-filter="1" title="Mostra foto alba">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
                 stroke="#FFA500" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 18a5 5 0 0 0-10 0"/>
              <line x1="12" y1="2" x2="12" y2="9"/>
              <polyline points="5 12 12 5 19 12"/>
              <line x1="4" y1="22" x2="20" y2="22"/>
            </svg>
        </a>
        <span class="icon-label" style="color:#FFA500;">Alba</span>
    </span>';

    $svg_tramonto = '
    <span class="icon-sun-inline">
        <a href="#" onclick="apriLightboxFiltrato(2); return false;" data-filter="2" title="Mostra foto tramonto">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
                 stroke="#FF4500" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 18a5 5 0 0 0-10 0"/>
              <line x1="12" y1="9" x2="12" y2="2"/>
              <polyline points="19 12 12 19 5 12"/>
              <line x1="4" y1="22" x2="20" y2="22"/>
            </svg>
        </a>
        <span class="icon-label" style="color:#FF4500;">Tramonto</span>
    </span>';

    return '<span class="icon-sun-wrapper">' . $svg_alba . $svg_tramonto . '</span>';
})(),


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
  #close-btn {
  position: fixed;       /* ⬅️ non absolute */
  top: 12px;
  right: 12px;
  z-index: 10001;        /* ⬅️ sopra tutto */
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
    
    /*====ICONE ALBA TRAMONTO =====*/

   /* Wrapper orizzontale icone alba/tramonto */
.icon-sun-wrapper {
    display: inline-flex;
    align-items: center;
    gap: 14px; /* distanza tra le due icone */
}

/* Blocco icona + label sulla stessa riga */
.icon-sun-inline {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* Label piccola e colorata */
.icon-label {
    font-size: 10px;
    font-weight: bold;
    opacity: 0.8;
}

/* Hover migliorato */
.icon-sun-inline a svg {
    cursor: pointer;
    transition: transform 0.20s ease, stroke 0.2s ease;
}

.icon-sun-inline a:hover svg {
    transform: scale(1.15);
}

/* Icona attiva (filtro premuto) */
.icon-sun-inline a.active svg {
    stroke: red !important;
}


    
}
/* ========== LIGHTBOX CSS ========== */
.lightbox {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.95);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.lightbox.active {
    display: flex;
}

.lightbox-content {
    max-width: 90%;
    max-height: 90%;
    position: relative;
}

.lightbox-content img {
    max-width: 100%;
    max-height: 80vh;
    object-fit: contain;
}

.lightbox-info {
    color: white;
    text-align: center;
    margin-top: 15px;
    font-size: 16px;
}
@media (max-width: 480px) {
  .lightbox-info {
    font-size: 10px;    /* mobile = metà circa */
    margin-top: 6px;
  }
}
.lightbox-control-btn {
    position: absolute;
    background: rgba(255,255,255,0.2);
    border: none;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

#close-btn {
    top: 20px;
    right: 20px;
}

.nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.2);
    border: none;
    border-radius: 50%;
    width: 60px;
    height: 60px;
    cursor: pointer;
}

.nav-btn.prev {
    left: 20px;
}

.nav-btn.next {
    right: 20px;
}

.nav-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

/* =====================================================
   MOBILE – RIDUZIONE DIMENSIONI (OVERRIDE MINIMO)
   ===================================================== */
@media (max-width: 480px) {

  /* Alba / Tramonto: riduci SOLO dimensioni */
  .icon-sun-inline svg {
    width: 14px !important;
    height: 14px !important;
  }

  .icon-label {
    font-size: 8px !important;
  }

  /* Lightbox: pulsanti dimezzati */
  .lightbox-control-btn,
  .nav-btn {
    width: 26px !important;
    height: 26px !important;
  }

  .lightbox-control-btn svg,
  .nav-btn svg {
    width: 16px !important;
    height: 16px !important;
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


<?php
// =====================================
// =========ALBA_TRAMONTO==============
// =====================================

// ========== CONNESSIONE DB ==========
 // già definita in envelop_lettura.php
 $table_name_bis = table_name('DB_immagini_36h');
//impostazioni data odierna
$oggi_sql = get_now('Y-m-d');   // ← questo rispetta USE_TEST_MODE
$ieri_sql = date('Y-m-d', strtotime($oggi_sql . ' -1 day'));


// ========== QUERY UNICA ==========
$sql = "SELECT FILE, DATA_ORA, Temp, HR, P_hPa, vento_kmh, Dir_text, alba_tramonto 
        FROM $table_name_bis
        ORDER BY DATA_ORA DESC";

$stmt = $pdo_lettura->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$images = [];
foreach ($rows as $row) {

    // DEBUG AGGIUNTO
    //echo "<pre style='color:red;font-size:12px'>DEBUG ROW: " . print_r($row, true) . "</pre>";

    $images[] = [
        'src' => 'FoscamCamera_E8ABFAA799FE/snap/' . $row['FILE'],
        'data_ora' => date('d/m/Y H:i', strtotime($row['DATA_ORA'])),
        'data_ora_sql' => $row['DATA_ORA'], // Mantieni timestamp completo
        'temp' => $row['Temp'],
        'hr' => $row['HR'],
        'p_hpa' => $row['P_hPa'],
        'wind_kmh' => $row['vento_kmh'],
        'dir_text' => $row['Dir_text'],
        'alba_tramonto' => $row['alba_tramonto']
    ];
}
?>

<!-- ========== LIGHTBOX HTML ========== -->
<div class="lightbox" id="lightbox">
    <button id="close-btn" class="lightbox-control-btn lightbox-close" aria-label="Chiudi"  onclick="closeLightbox()">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="red">
            <path d="M18 6L6 18M6 6l12 12" stroke="red" stroke-width="5" stroke-linecap="round" />
        </svg>
    </button>

    <div class="lightbox-content">
        <img id="lightbox-img" src="" alt="Immagine ingrandita">
        <div id="lightbox-info" class="lightbox-info"></div>
    </div>
    
    <button class="nav-btn prev" onclick="prevImage(event)" aria-label="Immagine precedente">
        <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="red" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="9 18 15 12 9 6" />
        </svg>
    </button>
    
    <button class="nav-btn next" onclick="nextImage(event)" aria-label="Immagine successiva">
        <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="red" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6" />
        </svg>
    </button>
</div>

<!-- ========== JAVASCRIPT ========== -->


<script>
    // Passa le date odierna e ieri a JavaScript
    window.todaySQL = "<?php echo $oggi_sql; ?>";
    window.yesterdaySQL = "<?php echo $ieri_sql; ?>";
    window.allImages = <?php echo json_encode($images); ?>;
    window.images = window.allImages;
</script>

<script>
  window.phpNowTs = <?php echo get_time() * 1000; ?>;  // coerente con TEST/PROD
</script>


<script>
    // Passa i dati a JavaScript

    
    window.allImages = <?php echo json_encode($images); ?>;
  window.images = window.allImages;

  function apriLightboxFiltrato(flag) {
    var tutte = window.allImages || [];
    var oggi = window.todaySQL;
    var ieri = window.yesterdaySQL;

    window.images = [];

    // tempo deciso dal PHP (test o prod)
    var nowTs = window.phpNowTs;
    if (!nowTs) nowTs = Date.now(); // fallback se dimentichi phpNowTs

    var limiteTs = nowTs - (((23 * 60 * 60) + (58 * 60)) * 1000);

    for (var k = 0; k < tutte.length; k++) {
      var img = tutte[k];

      // timestamp immagine (MEGLIO: usare img.data_ts dal PHP)
      var imgTs = img.data_ts ? +img.data_ts : Date.parse(img.data_ora_sql);

      var matchData = !isNaN(imgTs) && imgTs >= limiteTs;
      var matchFlag = (parseInt(img.alba_tramonto, 10) === parseInt(flag, 10));

      if (matchFlag && matchData) {
        window.images.push(img);
      }
    }

    // aggiorna icone attive
    var links = document.querySelectorAll('a[data-filter]');
    for (var j = 0; j < links.length; j++) {
      links[j].classList.remove('active');
      if (links[j].getAttribute('data-filter') == flag) {
        links[j].classList.add('active');
      }
    }

    if (window.images.length > 0) {
      openLightbox(0);
    } else {
      alert("Nessuna immagine trovata (filtro=" + flag + ") nella finestra 23h58m. Oggi (" + oggi + ") / ieri (" + ieri + ")");
    }
  }

  window.apriLightboxFiltrato = apriLightboxFiltrato;
</script>

<!-- ========== LIGHTBOX JAVASCRIPT AUTONOMO SOLO PER ALBA/TRAMONTO========== -->

<script>
(function() {
    'use strict';
    
    let currentIndex = 0;

    // ========== UTILITY FUNCTIONS ==========
    
    /** Numero finito? (ES5-safe) */
    function isFiniteNumber(n) { 
        return typeof n === 'number' && isFinite(n); 
    }

    /** Numero o null */
    function numOrNull(v) {
        return (v === null || v === '' || !isFinite(+v)) ? null : (+v);
    }

    /** Getter sicuro */
    function get(obj, key) {
        return (obj && obj[key] !== null) ? obj[key] : null;
    }

    /** Stringa sicura */
    function getStr(obj, key) {
        var v = get(obj, key);
        return (v === null) ? '' : String(v);
    }

    /** Primo tra più campi definiti */
    function pickFirstDefined(obj, keys) {
        if (!obj) return null;
        for (var i = 0; i < keys.length; i++) {
            if (obj[keys[i]] !== null) return obj[keys[i]];
        }
        return null;
    }

    /** Direzione in testo: converte gradi → N/NE/E/... o restituisce stringa */
    function dirTesto(v) {
        if (v === null) return '--';
        var deg = +v;
        if (isFinite(deg)) {
            var dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
            var i = Math.round((deg % 360) / 22.5) % 16;
            return dirs[i < 0 ? i + 16 : i];
        }
        return String(v);
    }

    /** Crop verticale dell'immagine (taglia px in basso). Ritorna dataURL. */
    function cropImageBottom(src, cropBottomPx, cb) {
        var tempImg = new Image();
        tempImg.onload = function () {
            try {
                var w = tempImg.width;
                var h = Math.max(1, tempImg.height - cropBottomPx);
                var canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(tempImg, 0, 0, w, h, 0, 0, w, h);
                cb(canvas.toDataURL());
            } catch (e) {
                cb(src); // fallback se canvas fallisce
            }
        };
        tempImg.onerror = function () { cb(src); };
        tempImg.src = src;
    }

    /** Costruisce la stringa info dell'immagine corrente. */
    function buildInfoText(record) {
        // Data/ora
        var d = record.data_ora || 'N/A';

        // Temperatura
        var t = parseFloat(record.temp);
        var tTxt = isFinite(t) ? Math.round(t) + '°C' : 'N/A';

        // Umidità
        var hr = parseFloat(record.hr);
        var hTxt = isFinite(hr) ? Math.round(hr) + '%' : 'N/A';

        // Pressione
        var p = parseFloat(record.p_hpa);
        var pTxt = isFinite(p) ? Math.round(p) + ' hPa' : 'N/A';

        // Vento
        var windKmh = parseFloat(record.wind_kmh);
        var wTxt = isFinite(windKmh) ? windKmh + ' km/h' : 'N/A';

        // Direzione (converti gradi → testo)
        var dirGradi = parseFloat(record.dir_text);
        var dTxt = isFinite(dirGradi) ? dirTesto(dirGradi) : record.dir_text || 'N/A';

        // Alba/Tramonto (solo se flag presente)
        var sunPhase = '';
        if (record.alba_tramonto) {
            var flag = parseInt(record.alba_tramonto);
            if (flag === 1) {
                sunPhase = ' | Alba';
            } else if (flag === 2) {
                sunPhase = ' | Tramonto';
            }
        }

        return d + ' | T ' + tTxt + ' | UR ' + hTxt + ' | ' + pTxt + ' | Vento ' + wTxt + ', ' + dTxt + sunPhase;
    }

    // ========== LIGHTBOX FUNCTIONS ==========

   function openLightbox(index) {
    if (!window.images || window.images.length === 0) return;

    // 🔒 CLAMP DELL’INDICE (FONDAMENTALE)
    if (index < 0) index = 0;
    if (index > window.images.length - 1) {
        index = window.images.length - 1;
    }

    currentIndex = index;

    const lightbox = document.getElementById('lightbox');
    const img = document.getElementById('lightbox-img');
    const info = document.getElementById('lightbox-info');

    const current = window.images[currentIndex];
    if (!current) return;

    info.innerHTML = buildInfoText(current);

    cropImageBottom(current.src, 80, function (croppedSrc) {
        img.src = croppedSrc;
    });

    lightbox.classList.add('active');
    updateNavButtons();
}


    function closeLightbox() {
        document.getElementById('lightbox').classList.remove('active');
    }

    function prevImage(event) {
        event.stopPropagation();
        if (currentIndex > 0) {
            openLightbox(currentIndex - 1);
        }
    }

    function nextImage(event) {
        event.stopPropagation();
        if (currentIndex < window.images.length - 1) {
            openLightbox(currentIndex + 1);
        }
    }

    function updateNavButtons() {
        const prevBtn = document.querySelector('.nav-btn.prev');
        const nextBtn = document.querySelector('.nav-btn.next');
        
        if (prevBtn) prevBtn.disabled = (currentIndex === 0);
        if (nextBtn) nextBtn.disabled = (currentIndex === window.images.length - 1);
    }
    
    function aggiornaLightbox() {
  
          var items = window.images || [];
          var record = items[currentIndex];
          if (!record) return;
        
          var src = getStr(record, 'src').trim();
          if (!src) return;
        
          // Crop e set immagine
          cropImageBottom(src, 80, function (croppedSrc) {
            var imgEl = document.getElementById('lightbox-img');
            if (imgEl) {
              imgEl.src = croppedSrc;
              
            }
          });

  // Info text
  var infoEl = document.getElementById('lightbox-info');
  if (infoEl) infoEl.textContent = buildInfoText(record);
}

    // ========== EVENT LISTENERS ==========
    
    // Keydown con auto-repeat su frecce
    document.addEventListener('keydown', function (event) {
      var lb = document.getElementById('lightbox');
      if (!lb || !lb.classList.contains('active')) return;
    
      var key = event.key || event.code;
    
      if (key === ' ' || key === 'Spacebar') {
        event.preventDefault();
        if (isRewinding) rewindToCurrent();
        else if (isForwarding) forwardToNewest();
        return;
      }
    
      if (key === 'Escape' || key === 'Esc') {
        closeLightbox();
        return;
      }
    
      if (key === 'ArrowLeft') {
        var items = window.images || [];
        if (currentIndex < items.length - 1) {
          currentIndex++; aggiornaLightbox(); updateNavButtons();
        }
      }
    
      if (key === 'ArrowRight') {
        if (currentIndex > 0) {
          currentIndex--; aggiornaLightbox(); updateNavButtons();
        }
        
        }
    });
    
    
    // Touch swipe su lightbox
    document.addEventListener('DOMContentLoaded', function () {
      var lightbox = document.getElementById('lightbox');
      if (!lightbox) return;
    
      var touchStartX = 0;
      var touchEndX   = 0;
    
      lightbox.addEventListener('touchstart', function (e) {
        touchStartX = e.changedTouches[0].screenX;
      });
    
      lightbox.addEventListener('touchend', function (e) {
        touchEndX = e.changedTouches[0].screenX;
        var threshold = 50;
        if (touchEndX < touchStartX - threshold) {
          // swipe left → avanti nel tempo (indice +1)
          var items = window.images || [];
          if (currentIndex < items.length - 1) {
            currentIndex++; aggiornaLightbox(); updateNavButtons();
          }
        } else if (touchEndX > touchStartX + threshold) {
          // swipe right → indietro nel tempo (indice -1)
          if (currentIndex > 0) {
            currentIndex--; aggiornaLightbox(); updateNavButtons();
          }
        }
      });
    });

    // ========== GLOBAL EXPORTS ==========
    
    window.openLightbox = openLightbox;
    window.closeLightbox = closeLightbox;
    window.prevImage = prevImage;
    window.nextImage = nextImage;
})();
</script>


