<?php
/*
NULL=normale,0= nuvole part, 1=luna, 2=arcobaleno, 3=aur_boreale;

NUVOLE
 0  → Nuvole (generiche — record esistenti, retrocompatibile)
10  → Cirri
11  → Cirrocumuli / Cirrostrati
12  → Altocumuli / Altostrati
13  → Cumuli
14  → Cumulonembi
15  → Strati / Stratocumuli
16  → Nembostrati
17  → Nebbia

PIOGGIA
 6  → Pioggia (generica — record esistenti, retrocompatibile)
60  → (riservato)
61  → (riservato)
62  → (riservato)

NEVE
 4  → Neve (generica — record esistenti, retrocompatibile)
40  → (riservato)
41  → (riservato)
*/



/* 0) Impostazioni per non "sporcare" il JSON con warning/notice ------------- */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../envelop.php';
define('ALLOW_INTERNAL_CALL', true);
require_once __DIR__ . '/aggiornaCartellaImmagini.php';
require_once __DIR__ . '/env_tables_helper.php';
require_once __DIR__ . '/astro_helper.php';

require_once __DIR__ . '/public_php/agg_DB_belle.php';

$directory = 'belle/';
$table_name = table_name('DB_immagini_belle');

// ============================================================================
// GESTIONE FILTRI
// ============================================================================

// Recupera parametri filtro dalla query string
$filtro_data_inizio = isset($_GET['data_inizio']) ? $_GET['data_inizio'] : null;
$filtro_data_fine = isset($_GET['data_fine']) ? $_GET['data_fine'] : null;
$filtro_sun_phase = isset($_GET['sun_phase']) ? $_GET['sun_phase'] : 'all';
// 'night' è un sotto-caso di sun_phase: viene gestito separatamente
// nella logica SQL perché richiede calcoli astronomici per ogni giorno del range.
$filtro_altro = isset($_GET['altro']) ? $_GET['altro'] : 'all';
// altro_sub: sottocategoria contestuale (es. tipo di nuvola).
// Presente solo quando $filtro_altro è una categoria con figli (nuvole, pioggia, neve).
// Valore 'all' = tutte le sottocategorie della categoria padre.
$filtro_altro_sub = isset($_GET['altro_sub']) ? $_GET['altro_sub'] : 'all';
// sequenza: 1 = mostra solo foto marcate come sequenza, 0 = tutti.
// Se attivo, azzera tutti gli altri filtri (gestito sotto prima della query).
$filtro_sequenza = isset($_GET['sequenza']) ? (int)$_GET['sequenza'] : 0;
// con_nota: 1 = mostra solo immagini con una nota compilata, 0 = tutte
$filtro_con_nota = isset($_GET['con_nota']) ? (int)$_GET['con_nota'] : 0;

// Determina se ci sono filtri attivi
$filtri_attivi = !empty($filtro_data_inizio) || !empty($filtro_data_fine) || 
                 $filtro_sun_phase !== 'all' || $filtro_altro !== 'all' || 
                 $filtro_sequenza > 0 || $filtro_con_nota > 0 ||
                 $filtro_altro_sub !== 'all';

// Mappatura valori altro -> etichette descrittive con ordine personalizzato.
// SCHEMA NUMERICO:
//   0   = Nuvole generiche (retrocompatibile con record esistenti)
//   10-16 = Sottotipi nuvole
//   6   = Pioggia generica (retrocompatibile)
//   60-62 = Sottotipi pioggia (riservati per futuro)
//   4   = Neve generica (retrocompatibile)
//   40-41 = Sottotipi neve (riservati per futuro)
//   1,2,3,5 = altre categorie senza sottotipi
$altro_labels = [
    // Nuvole
    '0'  => 'Nuvole',
    '10' => '↳ Cirri',
    '11' => '↳ Cirrocumuli / Cirrostrati',
    '12' => '↳ Altocumuli / Altostrati',
    '13' => '↳ Cumuli',
    '14' => '↳ Cumulonembi',
    '15' => '↳ Strati / Stratocumuli',
    '16' => '↳ Nembostrati',
    '17' => '↳ Nebbia',
    // Pioggia
    '6'  => 'Pioggia',
    // Neve
    '4'  => 'Neve',
    // Altre
    '1'  => 'Luna',
    '2'  => 'Arcobaleno',
    '3'  => 'Aur. boreale',
    '5'  => 'Altro'
];

// Recupera valori unici di "altro" dal database
$stmt_altro = $pdo->prepare("SELECT DISTINCT altro FROM $table_name WHERE altro IS NOT NULL");
$stmt_altro->execute();
$valori_altro_db = $stmt_altro->fetchAll(PDO::FETCH_COLUMN);

// Ordina secondo l'array $altro_labels
$valori_altro = [];
foreach ($altro_labels as $key => $label) {
    if (in_array($key, $valori_altro_db)) {
        $valori_altro[] = $key;
    }
}

// =====================
// PAGINAZIONE per la visualizzazione
// =====================
$limit = 200; // immagini per pagina
$page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1; 
if (!$filtri_attivi && !isset($_GET['page'])) {
    $page = 1;
}

// Se sequenza è attiva, azzera tutti gli altri filtri.
// PRINCIPIO — Separazione delle responsabilità:
//   La regola UX ("sequenza esclude tutto il resto") viene applicata qui,
//   a monte, prima di passare i parametri alla funzione SQL.
//   La funzione non conosce questa regola: riceve solo i parametri finali
//   e costruisce la WHERE di conseguenza. I due livelli restano indipendenti.
if ($filtro_sequenza) {
    $filtri_per_query = [
        'sequenza' => 1,
        'page'     => $page,
        'limit'    => $limit,
    ];
} else {
    $filtri_per_query = [
        'data_inizio' => $filtro_data_inizio,
        'data_fine'   => $filtro_data_fine,
        'sun_phase'   => $filtro_sun_phase,
        'altro'       => $filtro_altro,
        'altro_sub'   => $filtro_altro_sub,
        'con_nota'    => $filtro_con_nota,
        'page'        => $page,
        'limit'       => $limit,
    ];
}

// Ottiene i dati delle immagini con filtri applicati
$data = getImageDataFromFolderFiltered($pdo, $directory, $table_name, $filtri_per_query);
  
$records = []; 
$errore_messaggio = null;

if (isset($data['error'])) {
    // Gestione errore
    $errore_messaggio = $data['error'];
    $records = [];
} elseif (empty($data['records'])) {
    // Nessuna immagine trovata
    $errore_messaggio = null; // Gestiremo questo caso separatamente
    $records = [];
} else {
    $records = $data['records'];
    // Versione "pagina": cambia se cambia la selezione (page/filtri) o se arrivano nuovi scatti
    $pageVersion = 0;
    // Ciclo per formattare la data per l'uso nella galleria/lightbox
    foreach ($records as &$rec) {
    // tieni il valore DB originale
    $rec['_data_ora_raw'] = $rec['data_ora'] ?? null;

    if (!empty($rec['data_ora'])) {
        $rec['data_ora'] = (new DateTime($rec['data_ora']))->format('d/m/Y H:i');
    } else {
        $rec['data_ora'] = 'Data/Ora N/D';
    }
}

$pageVersion = 0;
foreach ($records as $r) {
    if (!empty($r['_data_ora_raw'])) {
        $ts = strtotime($r['_data_ora_raw']);
        if ($ts !== false && $ts > $pageVersion) $pageVersion = $ts;
    }
}

unset($rec);

}



/**
 * Funzione modificata per supportare filtri
 */
function getImageDataFromFolderFiltered(PDO $pdo, string $directory, string $tableName, array $filtri = []): array
{
    $directory = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!is_dir($directory)) {
        return ['error' => "La cartella '$directory' non esiste.", 'mainImage' => '', 'count' => 0, 'records' => []];
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
        return ['error' => "Nome tabella non valido.", 'mainImage' => '', 'count' => 0, 'records' => []];
    }

    $limit = isset($filtri['limit']) ? max(1, (int)$filtri['limit']) : 200;
    $page  = isset($filtri['page'])  ? max(1, (int)$filtri['page'])  : 1;
    $offset = ($page - 1) * $limit;

    // -----------------------
    // WHERE dinamica + params
    // -----------------------
    $where = [];
    $params = [];

    if (!empty($filtri['data_inizio'])) {
        $where[] = "DATA_ORA >= ?";
        $params[] = $filtri['data_inizio'] . " 00:00:00";
    }

    if (!empty($filtri['data_fine'])) {
        $where[] = "DATA_ORA <= ?";
        $params[] = $filtri['data_fine'] . " 23:59:59";
    }

    if (isset($filtri['sun_phase']) && $filtri['sun_phase'] !== 'all') {
        switch ($filtri['sun_phase']) {

            case '1':
            case '2':
                $where[] = "sun_phase = ?";
                $params[] = (int)$filtri['sun_phase'];
                break;

            case 'day':
                // "Pieno giorno": foto senza classificazione alba/tramonto.
                // sun_phase IS NULL = campo non compilato dall'operatore.
                // La voce "Nessuno" è stata rimossa perché produceva lo stesso SQL.
                $where[] = "sun_phase IS NULL";
                break;

            case 'night':
                // Non legge sun_phase: confronta DATA_ORA con gli intervalli
                // astronomici calcolati da getNightIntervals() in astro_helper.php.
                // È ortogonale a sun_phase: filtra per ORA REALE dello scatto,
                // indipendentemente da qualsiasi classificazione manuale.

                // 1) Range date: da filtri utente oppure MIN/MAX del DB
                if (!empty($filtri['data_inizio']) && !empty($filtri['data_fine'])) {
                    $range_from = new DateTime($filtri['data_inizio']);
                    $range_to   = new DateTime($filtri['data_fine']);
                } else {
                    try {
                        $stmtRange = $pdo->query(
                            "SELECT MIN(DATA_ORA) AS min_dt, MAX(DATA_ORA) AS max_dt FROM {$tableName}"
                        );
                        $rangeRow = $stmtRange->fetch(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {
                        $rangeRow = null;
                    }

                    if ($rangeRow && $rangeRow['min_dt'] && $rangeRow['max_dt']) {
                        $range_from = new DateTime($rangeRow['min_dt']);
                        $range_to   = new DateTime($rangeRow['max_dt']);
                    } else {
                        $where[] = "1 = 0";
                        break;
                    }
                }

                // 2) Calcola intervalli tramonto+40' → alba_domani-40'
                $night_intervals = getNightIntervals($range_from, $range_to, 40);

                if (empty($night_intervals)) {
                    $where[] = "1 = 0";
                    break;
                }

                // 3) Costruisce (DATA_ORA >= ? AND DATA_ORA <= ?) OR (...)
                //    Le parentesi esterne sono essenziali: senza, AND e OR
                //    degli altri filtri si combinerebbero in modo scorretto.
                $night_clauses = [];
                foreach ($night_intervals as $interval) {
                    $night_clauses[] = "(DATA_ORA >= ? AND DATA_ORA <= ?)";
                    $params[] = $interval['start'];
                    $params[] = $interval['end'];
                }
                $where[] = "(" . implode(" OR ", $night_clauses) . ")";
                break;
        }
    }

    // ── FILTRO ALTRO + ALTRO_SUB ─────────────────────────────────────────────
    // SCHEMA:
    //   - Se altro='all'   → nessun filtro
    //   - Se altro='0' (Nuvole) + altro_sub='all' → IN (0,10,11,12,13,14,15,16)
    //   - Se altro='0' (Nuvole) + altro_sub='10'  → altro = 10
    //   - Se altro='6' (Pioggia) + altro_sub='all'→ IN (6) [+ futuri 60,61,62]
    //   - Se altro='4' (Neve) + altro_sub='all'   → IN (4) [+ futuri 40,41]
    //   - Qualsiasi altro valore senza figli       → altro = valore
    //
    // PRINCIPIO — Tabella di dispatch:
    //   Definiamo i "figli" di ogni categoria padre in un array PHP.
    //   Questo evita switch enormi e rende banale aggiungere nuovi sottotipi:
    //   basta aggiungere il valore all'array del padre.
    // ─────────────────────────────────────────────────────────────────────────
    
    // Mappa padre → [padre, figlio1, figlio2, ...]
    // Il padre è incluso nell'array perché "Nuvole - tutte" include anche
    // i record con altro=0 (classificati prima che esistessero i sottotipi).
    $subcategories = [
        '0' => ['0', '10', '11', '12', '13', '14', '15', '16'], // Nuvole
        '6' => ['6', '60', '61', '62'],                          // Pioggia (riservati)
        '4' => ['4', '40', '41'],                                // Neve (riservati)
    ];

    if (isset($filtri['altro']) && $filtri['altro'] !== 'all') {
        $cat = (string)$filtri['altro'];
        $sub = isset($filtri['altro_sub']) ? (string)$filtri['altro_sub'] : 'all';

        if (isset($subcategories[$cat])) {
            // Categoria CON sottotipi
            if ($sub === 'all') {
                // Tutte le sottocategorie: uso IN (...)
                // implode costruisce i placeholder ?,?,? in base al numero di figli.
                $children = $subcategories[$cat];
                $ph = implode(',', array_fill(0, count($children), '?'));
                $where[] = "altro IN ($ph)";
                foreach ($children as $v) {
                    $params[] = $v;
                }
            } else {
                // Sottocategoria specifica: filtro esatto
                $where[] = "altro = ?";
                $params[] = $sub;
            }
        } else {
            // Categoria SENZA sottotipi (Luna, Arcobaleno, ecc.): filtro diretto
            $where[] = "altro = ?";
            $params[] = $cat;
        }
    }

    // Filtro note: mostra solo immagini che hanno il campo 'note' compilato nel DB
    if (!empty($filtri['con_nota'])) {
        $where[] = "note IS NOT NULL AND note <> ''";
    }

    // Filtro sequenza: WHERE sequenza = 1
    // Query banale grazie alla colonna dedicata — nessuna logica applicativa,
    // nessun LIKE, nessun calcolo matematico. L'indice su sequenza la rende
    // velocissima anche su tabelle grandi.
    // NOTA: quando questo filtro è attivo, tutti gli altri sono già stati
    // azzerati a monte (vedi blocco "$filtro_sequenza" in belle.php).
    // Quindi questa clausola sarà sempre l'unica nel WHERE quando presente.
    if (!empty($filtri['sequenza'])) {
        $where[] = "sequenza = 1";
    }

    $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

    try {
        // -----------------------
        // 1) COUNT totale (per pagine)
        // -----------------------
        $sqlCount = "SELECT COUNT(*) AS tot FROM {$tableName} {$whereSql}";
        $stmtC = $pdo->prepare($sqlCount);
        $stmtC->execute($params);
        $total = (int)$stmtC->fetchColumn();
        $totalPages = (int)max(1, ceil($total / $limit));

        // Se page oltre limite, clamp
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $limit;
        }

        // -----------------------
        // 2) Righe pagina
        // -----------------------
        $sql = "
            SELECT *
            FROM {$tableName}
            {$whereSql}
            ORDER BY DATA_ORA DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {
        return ['error' => "Errore SQL: " . $e->getMessage(), 'mainImage' => '', 'count' => 0, 'records' => []];
    }

    // -----------------------
    // Costruisci records (e scarta file non presenti)
    // -----------------------
    $records = [];
    foreach ($rows as $row) {
        $file = $row['FILE'] ?? '';
        if ($file === '') continue;

        $path = $directory . $file;

        // se vuoi essere "strict": mostra solo se esiste fisicamente
        if (!is_file($path)) continue;

        $records[] = [
            'src'       => $path,
            'file'      => $file,
            'data_ora'  => $row['DATA_ORA'] ?? null,
            'temp'      => isset($row['Temp']) ? (float)$row['Temp'] : null,
            'hr'        => isset($row['HR']) ? (float)$row['HR'] : null,
            'p_hpa'     => isset($row['P_hPa']) ? (float)$row['P_hPa'] : null,
            'wind_kmh'  => isset($row['vento_kmh']) ? (float)$row['vento_kmh'] : null,
            'dir_text'  => $row['Dir_text'] ?? null,
            'sun_phase' => isset($row['sun_phase']) ? (int)$row['sun_phase'] : null,
            'altro'     => $row['altro'] ?? null,
            'note'      => isset($row['note']) && $row['note'] !== '' ? $row['note'] : null,
            'sequenza'  => !empty($row['sequenza']) ? 1 : 0,
        ];
    }

    return [
        'error'       => null,
        'mainImage'   => $records[0]['src'] ?? '',
        'count'       => count($records),
        'records'     => $records,
        'total'       => $total,
        'page'        => $page,
        'limit'       => $limit,
        'total_pages' => $totalPages
    ];
}


/**
 * Funzione PHP per determinare la classe di colore in base alla temperatura.
 * @param float|null $temp Temperatura.
 * @return string Classe CSS.
 */
function getTempColorClass($temp) {
  if (!is_numeric($temp)) {
      return 'temp-default';
  }
  
  if ($temp > 35) {
      return 'temp-red';
  }
  if ($temp >= 25) {
      return 'temp-orange';
  }
  if ($temp >= 15) {
      return 'temp-green';
  }
  if ($temp >= 5) {
      return 'temp-lightblue';
  }
  if ($temp >= -3) {
      return 'temp-blue';
  }
  return 'temp-violet';
}

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Meteosimignano_diario_del_cielo</title>
    <link rel="stylesheet" href="header_shared.css">
    
    <style>
    
/* ==========================================================================
   CSS COMPLETO E CORRETTO PER BELLE.PHP
   ========================================================================== */
html, body {
    max-width: 100%;
    overflow-x: hidden;
}

*, *::before, *::after {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    display: flex;
    flex-direction: column;
    align-items: center;
    margin: 0;
    padding: 0;
}

main {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

/* Fix right-icon per landscape */
@media (orientation: landscape) and (max-height: 480px) {
    header.main-header .right-icon {
        width: 44px;
        min-width: 44px;
        justify-self: end;
    }
}
.gallery-header-container {
    width: 100%;
    max-width: 1000px;
    margin: 0 auto;                /* centra il blocco */
    padding: 0 5px;                /* stesso padding laterale della gallery */
    box-sizing: border-box;
}


/* Regola principale per il titolo */
.title-container {
    width: 100%;
    max-width: 1000px;           /* deve essere uguale al max-width della galleria */
    margin: 0 auto;              /* † centra tutto il blocco */
    padding: 0 10px;
    box-sizing: border-box;
    display: flex;               /* flex per centrare il contenuto */
    justify-content: center;     /* centra orizzontalmente */
}

.gallery-title-row {
    display: flex;               /* disposizione orizzontale */
    align-items: center;         /* allinea verticalmente */
    gap: 12px;                   /* spazio tra titolo e spinner */
    justify-content: center;     /* centra il contenuto */
}

.gallery-title {
    margin: 24px 0 12px 0;
    font-weight: bold;
    color: black;
    font-size: clamp(16px, 7vw, 38px);
    line-height: 1.15;
    /* rimuovi padding-left se c'era */
}

/* Riduci circa della metÃ  sui telefoni piccoli / medi */
@media (max-width: 480px) {
    .gallery-title {
        font-size: clamp(14px, 5.5vw, 22px);
    }
}

@media (orientation: landscape) and (max-width: 896px) {
    .gallery-title {
        font-size: clamp(18px, 6.2vw, 28px);   /* un po' piÃ¹ leggibile in landscape */
        margin: 16px 0 10px 0;                 /* meno margine verticale */
    }
}

/* ==========================================================================
   GALLERIA
   ========================================================================== */
.gallery {
    display: flex;
    flex-wrap: wrap;
     width: 100%;  
    max-width: 1000px;
    max-height: calc(4 * 150px + 30px);
    margin: 0 auto;
    overflow-y: auto;
    padding: 5px;
    box-sizing: border-box;
}

.gallery .thumb {
    position: relative;
    display: inline-block;
    width: calc(33.333% - 20px);
    margin: 10px;
    overflow: hidden;
    border: 4px solid #ddd;
    border-radius: 8px;
    box-sizing: border-box;
    container-type: inline-size;
    aspect-ratio: 4 / 2.79;
}

.gallery .thumb > img {
    display: block;
    width: 110%;
    height: 110%;
    object-fit: cover;
    object-position: center;
    transition: transform 0.2s ease, clip-path 0.2s ease;
    clip-path: inset(0% 0 7% 0);
    cursor: pointer;
}

.gallery .thumb > img:hover {
    transform: scale(1.1);
}

/* Mobile: 2 per riga */
@media (max-width: 480px) {
    .gallery .thumb {
        width: calc(50% - 16px);
        margin: 8px;
    }
    
    .gallery {
        max-height: calc(2 * 200px + 20px);
    }
}

/* ==========================================================================
   OVERLAY MINIATURE
   ========================================================================== */
.overlay-mini {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 60%;
    max-width: 180px;
    text-align: center;
    line-height: 1.05;
    pointer-events: none;
}

.overlay-mini > * {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1px;
}

.overlay-mini > *:last-child {
    margin-bottom: 0;
}

.overlay-mini .temp-line,
.overlay-mini .ora-line {
    text-shadow: 0 0 3px rgba(0,0,0,0.9), 0 0 2px rgba(0,0,0,0.9);
    white-space: nowrap;
    font-size: clamp(12px, 2.8vw, 18px);
}

.temp-line {
    font-weight: 700;
    display: flex !important;
    width: 100%;
    justify-content: center;
    align-self: center;
}

.temp-line sup,
.temp-line .sup {
    vertical-align: baseline;
    position: relative;
    top: -0.1em;
    font-size: 0.8em;
}

.ora-line {
    display: flex;
    align-items: center;
    font-weight: 600;
    color: #ff1e1e;
}

.ora-line > * {
    margin-right: 4px;
}

.ora-line > *:last-child {
    margin-right: 0;
}

/* Mobile font sizes */
@media (max-width: 480px) {
    .overlay-mini {
        width: 72%;
        max-width: 175px;
        line-height: 1.05;
    }
    
    .overlay-mini .ora-line {
        font-size: clamp(11px, 2.2vw, 16px) !important;
    }
    
    .overlay-mini .temp-line {
        font-size: clamp(12px, 3.4vw, 18px) !important;
    }
}

@supports (font-size: 1cqw) {
    .overlay-mini {
        width: min(60cqw, 180px);
        max-width: 70cqw;
    }
    
    .overlay-mini .temp-line,
    .overlay-mini .ora-line {
        font-size: clamp(12px, 5cqw, 18px) !important;
    }
}

@supports (font-size: 1cqw) {
    @media (max-width: 480px) {
        .overlay-mini .ora-line {
            font-size: clamp(11px, 4cqw, 16px) !important;
        }
        
        .overlay-mini .temp-line {
            font-size: clamp(12px, 5cqw, 18px) !important;
        }
    }
}

/* ==========================================================================
   COLORI TEMPERATURA
   ========================================================================== */
.icon {
    width: 1em;
    height: 1em;
    vertical-align: -1px;
    fill: currentColor;
}

.icon-outline {
    fill: currentColor;
}

.temp-red { color: #ec0835; }
.temp-orange { color: #cf7618; }
.temp-green { color: #79f603; }
.temp-lightblue { color: #09e3ce; }
.temp-blue { color: #007bff; }
.temp-violet { color: #8b00ff; }
.temp-default { color: #9c9c9c; }

/* ==========================================================================
   ICONA NOTE sulla miniatura
   position:absolute funziona perché .thumb ha già position:relative —
   il figlio assoluto si posiziona rispetto al suo contenitore più vicino.
   ========================================================================== */
.note-icon {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 26px;
    height: 26px;
    background: rgba(0, 0, 0, 0.55);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    font-size: 14px;
    line-height: 1;
    transition: background 0.2s, transform 0.15s;
    border: none;
    padding: 0;
    color: #fff;
}

.note-icon:hover {
    background: rgba(255, 220, 50, 0.85);
    transform: scale(1.15);
}

/* Checkbox Sequenza e Note: width auto per stare nella stessa riga del form */
.filter-group-cb {
    min-width: 0;
    width: auto;
    flex-shrink: 0;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
    font-weight: bold;
    color: #333;
    cursor: pointer;
    padding: 6px 0;
    white-space: nowrap;
}

.checkbox-label input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
    accent-color: #4CAF50;
    flex-shrink: 0;
}

/* Stato disabilitato visivo quando sequenza è attiva */
.filter-group-cb.disabled-by-seq {
    opacity: 0.35;
    pointer-events: none;
}

/* ==========================================================================
   MODAL NOTE
   Pattern: display:none → display:flex via classe CSS 'active'.
   JS gestisce solo il *quando*, CSS gestisce il *come appare*.
   ========================================================================== */
.note-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.65);
    z-index: 2000;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
}

.note-modal.active {
    display: flex;
}

.note-modal-box {
    background: #fff;
    border-radius: 12px;
    padding: 24px 28px;
    max-width: 480px;
    width: 100%;
    box-shadow: 0 8px 32px rgba(0,0,0,0.35);
    position: relative;
    box-sizing: border-box;
}

.note-modal-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
    font-size: 17px;
    font-weight: bold;
    color: #333;
}

.note-modal-text {
    font-size: 15px;
    line-height: 1.6;
    color: #444;
    white-space: pre-wrap;
    word-break: break-word;
}

/* Immagini incollate nella nota: larghezza massima del box, con bordo arrotondato */
.note-modal-text img {
    max-width: 100%;
    height: auto;
    display: block;
    border-radius: 6px;
    margin: 8px 0;
}

.note-modal-meta {
    margin-top: 14px;
    font-size: 12px;
    color: #999;
    border-top: 1px solid #eee;
    padding-top: 10px;
}

.note-modal-close {
    position: absolute;
    top: 12px;
    right: 14px;
    background: transparent;
    border: none;
    font-size: 22px;
    color: #aaa;
    cursor: pointer;
    line-height: 1;
    padding: 2px 6px;
}

.note-modal-close:hover {
    color: #e00;
}



.filter-bar {
    width: 100%;
    max-width: 1000px;
    margin: 20px auto;
    background: #f0f0f0;
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    box-sizing: border-box;
    display: none;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.filter-bar.active {
    display: block;
}

.filter-close {
    position: absolute;
    top: 10px;
    right: 10px;
    background: transparent;
    border: none;
    font-size: 24px;
    color: #666;
    cursor: pointer;
    padding: 5px;
    line-height: 1;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.filter-close:hover {
    color: #f44336;
}

.filter-bar form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    min-width: 140px;
}

.filter-group label {
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 4px;
    color: #333;
}

.filter-group input,
.filter-group select {
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

.filter-actions {
    display: flex;
    gap: 8px;
    align-items: flex-end;
}

.filter-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
}

.filter-btn-apply {
    background: #4CAF50;
    color: white;
}

.filter-btn-apply:hover {
    background: #45a049;
}

/* ==========================================================================
   FILTER BAR — RESPONSIVE
   Layout desktop: tutti i gruppi in riga, flex-wrap naturale.
   Layout mobile:  griglia a 2 colonne con assegnazione esplicita per classe,
                   così il comportamento non dipende dall'ordine nel DOM
                   né dalla visibilità di altro-sub-group.
   ========================================================================== */

/* ── MOBILE PORTRAIT (≤ 600px) ─────────────────────────────────────────── */
@media (max-width: 600px) {
    .filter-bar {
        padding: 10px 10px 12px;
        margin: 8px auto;
    }

    .filter-bar form {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px 6px;
        align-items: end;
    }

    /* RIGA 1: date */
    .fg-data-inizio  { grid-column: 1; }
    .fg-data-fine    { grid-column: 2; }

    /* RIGA 2: fase sole (larghezza piena) */
    .fg-sun-phase    { grid-column: 1 / -1; }

    /* RIGA 3: categoria + tipo — larghezza piena per avere spazio alle opzioni */
    .fg-categoria    { grid-column: 1 / -1; }
    .fg-tipo         { grid-column: 1 / -1; }

    /* RIGA 4: checkbox affiancate */
    .fg-checks       { grid-column: 1 / -1; }

    /* RIGA 5: bottone larghezza piena */
    .filter-actions  { grid-column: 1 / -1; justify-content: center; }

    /* Forza tutti i select e input a stare dentro la cella del grid.
       Senza width:100%, il browser usa la larghezza naturale del contenuto
       (es. "Cirrocumuli / Cirrostrati") che sfonda il layout. */
    .filter-group input,
    .filter-group select {
        width: 100%;
        padding: 4px 6px;
        font-size: 13px;
        height: 32px;
        box-sizing: border-box;
        min-width: 0;
    }

    .filter-group input[type="date"] {
        padding: 2px 4px;
        font-size: 12px;
    }

    .filter-group label {
        font-size: 11px;
        margin-bottom: 3px;
    }

    .filter-btn {
        padding: 6px 16px;
        font-size: 13px;
        width: 100%;
    }

    .filter-close {
        font-size: 20px;
        width: 26px;
        height: 26px;
        top: 8px;
        right: 8px;
    }
}

/* ── MOBILE LANDSCAPE (altezza ≤ 500px, larghezza > 500px) ─────────────── */
@media (max-height: 500px) and (orientation: landscape) {
    .filter-bar {
        padding: 8px 12px;
        margin: 4px auto;
        /* In landscape lo spazio verticale è scarso:
           rendiamo la barra scrollabile orizzontalmente se serve */
        overflow-x: auto;
    }

    .filter-bar form {
        display: flex;
        flex-wrap: nowrap;     /* tutto su una riga orizzontale */
        gap: 8px;
        align-items: flex-end;
        min-width: max-content; /* permette scroll se lo schermo è troppo stretto */
    }

    .filter-group,
    .filter-group-cb {
        min-width: 0;
        flex-shrink: 0;
    }

    .fg-data-inizio,
    .fg-data-fine    { width: 120px; }
    .fg-sun-phase    { width: 120px; }
    .fg-categoria    { width: 110px; }
    .fg-tipo         { width: 140px; }
    .filter-group-cb { width: auto; }

    .filter-group input,
    .filter-group select {
        padding: 3px 5px;
        font-size: 12px;
        height: 28px;
        box-sizing: border-box;
    }

    .filter-group input[type="date"] {
        font-size: 11px;
        padding: 2px 3px;
    }

    .filter-group label {
        font-size: 10px;
        margin-bottom: 2px;
    }

    .filter-btn {
        padding: 5px 12px;
        font-size: 12px;
        white-space: nowrap;
    }

    .checkbox-label {
        font-size: 12px;
    }
}



/* ==========================================================================
   MESSAGGI DI STATO
   ========================================================================== */
.gallery-container {
    width: 100%;
    display: flex;
    justify-content: center;
    padding: 0;
    margin: 0;
}

.status-message {
    width: 100%;
    max-width: 1000px;
    margin: 40px auto;
    padding: 30px;
    text-align: center;
    font-size: 18px;
    color: #999;
    border: 2px dashed #ddd;
    border-radius: 8px;
    background: #fafafa;
}

.status-message.error {
    color: #c62828;
    border-color: #ffcdd2;
    background: #ffebee;
}

.status-message svg {
    width: 48px;
    height: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* ==========================================================================
   LIGHTBOX
   ========================================================================== */
.lightbox {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.85);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.lightbox.active {
    display: flex;
}

.lightbox-content {
    display: inline-block;
    position: relative;
    margin: 0 auto;
}

.lightbox img {
    max-width: 95vw !important;
    max-height: 95vh !important;
    width: auto !important;
    height: auto !important;
    object-fit: contain !important;
    clip-path: none !important;
}

.lightbox-date,
.lightbox-info {
    position: absolute;
    left: 0;
    right: 0;
    text-align: center;
    background: rgba(0, 0, 0, 0.6);
    color: #fff;
    font-size: 14px;
    font-weight: normal;
    padding: 3px 6px;
    box-sizing: border-box;
    line-height: 1.2;
    border-radius: 5px !important;
}

.lightbox .nav-btn {
    position: fixed;
    bottom: 12px;
    background: rgba(0,0,0,0.35);
    border: none;
    padding: 10px 12px;
    border-radius: 999px;
    cursor: pointer;
    z-index: 2000;
}

.lightbox .nav-btn.prev {
    right: 12px;
}

.lightbox .nav-btn.next {
    left: 12px;
}

.lightbox .nav-btn:hover {
    background: rgba(255,255,255,0.8);
}

.lightbox .nav-btn:disabled {
    opacity: .25;
    pointer-events: none;
}

#close-btn.lightbox-close {
    position: fixed;
    top: 15px;
    left: 50%;
    margin-left: -1.5rem;
    background: transparent;
    color: red;
    font-size: 2rem;
    border: none;
    cursor: pointer;
    z-index: 1001;
}

/* =========================
   PAGINAZIONE (sempre 1 riga, font ‰¤14px)
   ========================= */
.pager-wrap {
    width: 100%;
    max-width: 1000px;
    margin: 8px auto 16px;          /* centrato come la galleria */
    padding: 10px 0;                /* solo sopra/sotto †’ bordi liberi lateralmente */
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    border: 1px solid #ddd;
    border-radius: 10px;
    background: #fafafa;
    box-sizing: border-box;
}

.pager-item {
    font-size: clamp(11px, 2.8vw, 14px);
    font-weight: 600;
    color: #666;
    white-space: nowrap;
    padding: 6px 14px;              /* respiro interno */
    border-radius: 6px;
    transition: color 0.15s;
    text-decoration: none;          /* rimuove sottolineatura link */
}

.pager-item:hover {
    color: #e00;
}

/* Spingi le frecce fuori fino al bordo reale del wrapper */
.pager-item:first-child {
    margin-left: 0px;             /* regola questo valore: -10 / -12 / -16 / -20 */
}

.pager-item:last-child {
    margin-right: 0px;
}

/* Se usi "Pagina X / Y" al centro, dagli un po' di protezione */
.pager-form {
    padding: 0 5px;                /* evita che tocchi le frecce quando sono vicine */
    text-align: center;
}

/* Mobile stretto: ancora piÃ¹ compatto ma resta su una riga */
@media (max-width: 480px) {
    .pager-wrap {
        padding: 6px 8px;
        margin: 4px auto 8px;
        gap: 6px;
    }
    .pager-item,
    .pager-label,
    .pager-select {
        font-size: clamp(10px, 3.5vw, 13px);
    }
}
/* =========================
 ===== Spinner loading accanto al titolo ===== 
 ========================= */

.spinner{
  width: 24px;                      /* aumentato da 14px †’ piÃ¹ visibile */
  height: 24px;                     /* aumentato da 14px †’ piÃ¹ visibile */
  border: 3px solid rgba(0,0,0,0.15);  /* border piÃ¹ spesso */
  border-top-color: #333;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  flex-shrink: 0;                   /* impedisce che venga schiacciato */
}

.spinner.hidden{
  display: none;
}

@keyframes spin{
  to{ transform: rotate(360deg); }
}

/* FIX overflow orizzontale: i figli flex devono poter restringersi */
.pager-wrap{
  flex-wrap: nowrap;      
  gap: 8px;
}

.pager-item,
.pager-form{
  min-width: 0;         /* IMPORTANTISSIMO nei flex item */
}

/* Se vuoi anche l'ellissi invece dello €œsforamento€ */
.pager-item{
  overflow: hidden;
  text-overflow: ellipsis;
}

.pager-item.is-disabled {
    color: #bbb;                    /* grigio chiaro €“ puoi usare #ccc, #aaa, #999 a seconda di quanto lo vuoi tenue */
    opacity: 0.65;                  /* ulteriore attenuazione (opzionale ma molto efficace) */
    cursor: default;                /* toglie la manina del link */
    pointer-events: none;           /* blocca completamente i click (anche se Ã¨ un <span>) */
    user-select: none;              /* impedisce la selezione del testo */
}

/* ====================================================================
   VOCI DEL SOTTO MENU - IN ALTO A DX NELLA PAGINA
   ==================================================================== */

/* Container del menu */
.header-menu-container {
    position: relative;
}

/* Pulsante menu */
.menu-toggle {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
}

/* Sottomenu */
.submenu {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 10px;
    background: white;
    border: 2px solid #333;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    min-width: max-content;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s ease;
    z-index: 1000;
}

/* Sottomenu visibile */
.submenu.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

/* Voci del sottomenu */
.submenu-item {
    display: block;
    padding: 12px 16px;
    color: #333;
    text-decoration: none;
    font-size: 14px;
    text-align: left;
    transition: background-color 0.2s ease;
    border-bottom: 1px solid #ddd;
}

.submenu-item:last-child {
    border-bottom: none;
}

.submenu-item:hover {
    background-color: #f0f0f0;
}

.submenu-item:first-child {
    border-radius: 6px 6px 0 0;
}

.submenu-item:last-child {
    border-radius: 0 0 6px 6px;
}


   .submenu-item {
    display: block;
    padding: 12px 20px;
    color: #333;
    text-decoration: none;
    font-size: 14px;
    transition: background-color 0.2s ease;
    border-bottom: 1px solid #f0f0f0;
}

.submenu-item:last-child {
    border-bottom: none;
}

.submenu-item:hover {
    background-color: #f8f8f8;
}

.submenu-item:first-child {
    border-radius: 8px 8px 0 0;
}

.submenu-item:last-child {
    border-radius: 0 0 8px 8px;
}
/*=================================
* VOCE CON SOTTO MENU ANNIDIATO *
=================================*/
.submenu-item.has-sub {
    position: relative;
    cursor: pointer;
    padding: 12px 20px;
    border-bottom: 1px solid #f0f0f0;
    user-select: none;
}
.submenu-item.has-sub:hover,
.submenu-item.has-sub.sub-active {
    background-color: #f8f8f8;
}
.sub-submenu {
    position: absolute;
    top: 0;
    left: 100%;
    margin-left: 4px;
    background: white;
    border: 2px solid #333;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    min-width: max-content;
    opacity: 0;
    visibility: hidden;
    transform: translateX(-6px);
    transition: all 0.2s ease;
    z-index: 1100;
}
.submenu-item.has-sub.sub-active .sub-submenu {
    opacity: 1;
    visibility: visible;
    transform: translateX(0);
}
@media (max-width: 768px) {
    .sub-submenu {
        left: auto;
        right: 0;
        top: 100%;
        margin-left: 0;
        margin-top: 2px;
        transform: translateY(-6px);
    }
    .submenu-item.has-sub.sub-active .sub-submenu {
        transform: translateY(0);
    }
}
.submenu-item.has-sub.sub-active .sub-submenu {
    opacity: 1;
    visibility: visible;
    transform: translateX(0);
}
.sub-submenu .submenu-item {
    padding: 6px 16px;
}

    </style>
<link rel="stylesheet" href="header_shared.css">       
</head>
<body>

<header class="main-header">
    <a href="#" class="header-icon left-icon" title="Filtri" onclick="toggleFilterBar(); return false;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
        </svg>
        <span class="icon-label">Filtri</span>
    </a>
    
    <div class="header-content">
    <h1 class="main-title">
        <a href="index.php" style="text-decoration: none; color: inherit;">MeteoSimignano</a>
    </h1>
    <h1 class="sub-title sub-title-row">
        <span>43°17′32.5″N 11°10′01.49″E @ 418m slm</span>
        <span id="page-spinner" class="spinner" aria-label="Caricamento in corso"></span>
    </h1>
    </div>
   

    
     <!-- Icona Menu/Indice (destra) -->
        <div class="header-menu-container">
            <button class="header-icon right-icon menu-toggle" title="Menu">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
                <span class="icon-label">Menu</span>
            </button>
            
            <div class="submenu">
                <a href="index.php" class="submenu-item">Home</a>
                <a href="belle.php" class="submenu-item">Diario del cielo</a>
                <div class="submenu-item has-sub">
                    <span class="submenu-item-label">Statistiche &#9658;</span>
                    <div class="sub-submenu">
                        <a href="stat_display.php" class="submenu-item" target="_blank">Tabelle</a>
                        <a href="lavori_in_corso.html" class="submenu-item" target="_blank">Grafici</a>
                    </div>
                </div>
                <a href='grafici_termo_plotly.php?range=24h&visible=' class="submenu-item">Grafici</a>
                <a href="pluvio.html" class="submenu-item">Pioggia: 24h</a>
                <a href="pluvio_tab.php" class="submenu-item">Pioggia: tabella</a>
            </div>    
         </div>       
    
</header>

<!-- ========== BARRA FILTRI ========== -->
<div class="filter-bar <?php echo $filtri_attivi ? 'active' : ''; ?>" id="filterBar">
    <button class="filter-close" onclick="closeFilterBar()" title="Chiudi">✕</button>
    <form method="GET" action="">
        <div class="filter-group fg-data-inizio">
            <label for="data_inizio">Data Inizio</label>
            <input type="date" id="data_inizio" name="data_inizio" value="<?php echo htmlspecialchars($filtro_data_inizio ?? ''); ?>">
        </div>

        <div class="filter-group fg-data-fine">
            <label for="data_fine">Data Fine</label>
            <input type="date" id="data_fine" name="data_fine" value="<?php echo htmlspecialchars($filtro_data_fine ?? ''); ?>">
        </div>

        <div class="filter-group fg-sun-phase">
            <label for="sun_phase">Alba/Tramonto</label>
            <select id="sun_phase" name="sun_phase">
                <option value="all" <?php echo $filtro_sun_phase === 'all' ? 'selected' : ''; ?>>Tutti</option>
                <option value="1" <?php echo $filtro_sun_phase === '1' ? 'selected' : ''; ?>>Alba</option>
                <option value="2" <?php echo $filtro_sun_phase === '2' ? 'selected' : ''; ?>>Tramonto</option>
                <option value="night" <?php echo $filtro_sun_phase === 'night' ? 'selected' : ''; ?>>Notte</option>
                <option value="day" <?php echo $filtro_sun_phase === 'day' ? 'selected' : ''; ?>>Pieno giorno</option>
            </select>
        </div>

        <div class="filter-group fg-categoria">
            <label for="altro">Categoria</label>
            <select id="altro" name="altro" onchange="aggiornaAltroSub(this.value)">
                <option value="all" <?php echo $filtro_altro === 'all' ? 'selected' : ''; ?>>Tutti</option>
                <?php
                // Mostra solo le categorie padre (senza i sottotipi)
                // I sottotipi appaiono nel secondo select quando necessario.
                $categorie_padre = ['0' => 'Nuvole', '6' => 'Pioggia', '4' => 'Neve',
                                    '1' => 'Luna', '2' => 'Arcobaleno', '3' => 'Aur. boreale', '5' => 'Altro'];
                foreach ($categorie_padre as $valore => $etichetta):
                    // Mostra la voce solo se esistono record nel DB per questa categoria
                    // (inclusi i figli: es. se ci sono Cirri ma non Nuvole generiche, Nuvole deve apparire)
                    $valori_padre_db = array_unique(array_map(function($v) {
                        if ($v >= 10 && $v < 20) return '0';
                        if ($v >= 60 && $v < 70) return '6';
                        if ($v >= 40 && $v < 50) return '4';
                        return (string)$v;
                    }, array_map('intval', $valori_altro_db)));
                    if (!in_array((string)$valore, $valori_padre_db)) continue;
                ?>
                    <option value="<?php echo htmlspecialchars($valore); ?>"
                            <?php echo $filtro_altro == $valore ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($etichetta); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Secondo select: appare solo per categorie con sottotipi -->
        <div class="filter-group fg-tipo" id="altro-sub-group" style="<?php echo in_array($filtro_altro, ['0','6','4']) ? '' : 'display:none'; ?>">
            <label for="altro_sub">Tipo</label>
            <select id="altro_sub" name="altro_sub">
                <?php
                // Definizione sottotipi per ogni categoria padre.
                // PRINCIPIO DRY: stessa struttura usata in PHP per il SQL,
                // qui serializzata in JSON per il JS che gestisce la UI.
                $sottotipi = [
                    '0' => ['all' => 'Tutte le nuvole', '10' => 'Cirri',
                            '11' => 'Cirrocumuli / Cirrostrati', '12' => 'Altocumuli / Altostrati',
                            '13' => 'Cumuli', '14' => 'Cumulonembi',
                            '15' => 'Strati / Stratocumuli', '16' => 'Nembostrati', '17' => 'Nebbia'],
                    '6' => ['all' => 'Tutta la pioggia'],
                    '4' => ['all' => 'Tutta la neve'],
                ];
                $cat_attiva = in_array($filtro_altro, ['0','6','4']) ? $filtro_altro : '0';
                $opzioni_attive = $sottotipi[$cat_attiva] ?? ['all' => 'Tutti'];
                foreach ($opzioni_attive as $v => $l):
                ?>
                    <option value="<?php echo htmlspecialchars($v); ?>"
                            <?php echo $filtro_altro_sub == $v ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($l); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group filter-group-cb">
            <label class="checkbox-label">
                <input type="checkbox" id="sequenza" name="sequenza" value="1"
                    <?php echo $filtro_sequenza ? 'checked' : ''; ?>
                    onchange="gestisciSequenza(this)">
                Sequenza
            </label>
        </div>

        <div class="filter-group filter-group-cb" id="group-con-nota">
            <label class="checkbox-label">
                <input type="checkbox" id="con_nota" name="con_nota" value="1"
                    <?php echo $filtro_con_nota ? 'checked' : ''; ?>
                    <?php echo $filtro_sequenza ? 'disabled' : ''; ?>>
                Note
            </label>
        </div>

        <div class="filter-actions">
            <button type="submit" class="filter-btn filter-btn-apply">Applica Filtri</button>
        </div>
    </form>
</div>

<main>
   <div class="title-container">
    <div class="gallery-title-row">
        <h2 class="gallery-title">Diario del cielo</h2>
        <span id="loading-spinner" class="spinner hidden" aria-label="Caricamento in corso"></span>
    </div>
</div>



        <?php
        $totalPages = $data['total_pages'] ?? 1;
        $pageNow    = $data['page'] ?? ($page ?? 1);
        $query = $_GET;
        ?>
        <div class="pager-wrap">
    <!-- PRECEDENTE -->
        <?php if ($pageNow > 1): ?>
            <?php 
                $query_prev = $query;
                $query_prev['page'] = $pageNow - 1;
            ?>
            <a class="pager-item" href="?<?php echo htmlspecialchars(http_build_query($query_prev)); ?>">Precedente</a>
        <?php else: ?>
            <span class="pager-item is-disabled">Precedente</span>
        <?php endif; ?>

    <form class="pager-form" method="get" action="">
    <?php
    // Mantieni TUTTI i parametri GET (filtri ecc.) tranne page
    foreach ($_GET as $k => $v) {
        if ($k === 'page') continue;

        // supporta anche array (non dovrebbe servirti, ma Ã¨ robusto)
        if (is_array($v)) {
            foreach ($v as $vv) {
                echo '<input type="hidden" name="'.htmlspecialchars($k).'[]" value="'.htmlspecialchars((string)$vv).'">';
            }
        } else {
            echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars((string)$v).'">';
        }
    }
    ?>
    <label class="pager-label" for="pageSelect">Pagina</label>
    <select id="pageSelect" name="page" class="pager-select">
        <?php for ($i = 1; $i <= (int)$totalPages; $i++): ?>
            <option value="<?php echo $i; ?>" <?php echo ($i === (int)$pageNow) ? 'selected' : ''; ?>>
                <?php echo $i . ' / ' . (int)$totalPages; ?>
            </option>
        <?php endfor; ?>
    </select>
</form>

    <!-- SUCCESSIVA -->
<?php if ($pageNow < $totalPages): ?>
    <?php 
        $query_next = $query;
        $query_next['page'] = $pageNow + 1;
    ?>
    <a class="pager-item" href="?<?php echo htmlspecialchars(http_build_query($query_next)); ?>">Successiva</a>
<?php else: ?>
    <span class="pager-item is-disabled">Successiva</span>
<?php endif; ?>
</div>
</div>



<!-- Gestione messaggi di stato -->
<?php if ($errore_messaggio !== null): ?>
    <!-- Errore generico -->
    <div class="status-message error">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <div><strong>Errore:</strong> <?php echo htmlspecialchars($errore_messaggio); ?></div>
    </div>

<?php elseif (empty($records)): ?>
    <!-- Nessuna immagine trovata -->
    <div class="status-message">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
            <circle cx="8.5" cy="8.5" r="1.5"></circle>
            <polyline points="21 15 16 10 5 21"></polyline>
        </svg>
        <div>Nessuna immagine trovata</div>
    </div>

<?php else: ?>
    <!-- Galleria normale -->
    <div class="gallery-container">
        <div class="gallery">
            <?php 
            foreach($records as $index => $item): 
                $temp = isset($item['temp']) ? (float)$item['temp'] : null;
                $tempDisplay = ($temp !== null) ? number_format($temp, 1) . '°C' : 'N/D';
                $tempClass = getTempColorClass($temp); 

                $dataOraCompleta = isset($item['data_ora']) ? $item['data_ora'] : 'Data N/D';
                $oraSolo = substr($dataOraCompleta, -5); 
                $dataSolo = substr($dataOraCompleta, 0, 10); 
            ?>
                <div class="thumb">
                    <img
                  src="<?php echo htmlspecialchars($item['src'] . '?v=' . (int)$pageVersion); ?>"
                  alt="Immagine webcam"
                  onclick="openLightbox(<?php echo (int)$index; ?>)"
                >

                    <?php if (!empty($item['note'])): ?>
                    <button class="note-icon"
                            onclick="event.stopPropagation(); apriNote(<?php echo (int)$index; ?>)"
                            title="Nota presente"
                            aria-label="Leggi nota">📝</button>
                    <?php endif; ?>
                    
                    <span class="overlay-mini <?php echo $tempClass; ?>">
                        <span class="temp-line">
                            <?php echo $tempDisplay; ?>
                        </span>
                        
                        <span class="ora-line">
                             <svg class="icon icon-outline" viewBox="0 0 24 24" style="vertical-align: middle; width: 1.2em; height: 1.2em; fill: none; stroke: currentColor; stroke-width: 2;">
                                <circle cx="12" cy="12" r="9" />
                                <line x1="12" y1="12" x2="12" y2="7" stroke-linecap="round"></line>
                                <line x1="12" y1="12" x2="15" y2="12" stroke-linecap="round"></line>
                            </svg> 
                            <?php echo $dataSolo . ' ' . $oraSolo; ?>
                        </span>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<?php
$totalPages = $data['total_pages'] ?? 1;
$page = $data['page'] ?? 1;

// ricostruisci la querystring mantenendo i filtri
$query = $_GET;
?>



<!-- ========== LIGHTBOX (UNA SOLA VERSIONE) ========== -->
<div class="lightbox" id="lightbox">
    <button id="close-btn" class="lightbox-close" aria-label="Chiudi">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="red">
            <path d="M18 6L6 18M6 6l12 12" stroke="red" stroke-width="5" stroke-linecap="round" />
        </svg>
    </button>

    <div class="lightbox-content">
        <img id="lightbox-img" src="" alt="Immagine ingrandita">
        <div id="lightbox-info" class="lightbox-info"></div>
    </div>
    
    <button class="nav-btn prev" aria-label="Immagine precedente">
        <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="red" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6" />
        </svg>
    </button>
    
    <button class="nav-btn next" aria-label="Immagine successiva">
        <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="red" stroke-width="5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="9 18 15 12 9 6" />
        </svg>
    </button>
</div>
</main>

<!-- ========== MODAL NOTE ========== -->
<div class="note-modal" id="noteModal" role="dialog" aria-modal="true">
    <div class="note-modal-box">
        <button class="note-modal-close" onclick="chiudiNote()" aria-label="Chiudi">✕</button>
        <div class="note-modal-header">
            <span>📝</span>
            <span>Note</span>
        </div>
        <div class="note-modal-text" id="noteModalText"></div>
        <div class="note-modal-meta" id="noteModalMeta"></div>
    </div>
</div>

<script>
(function () {
    'use strict';

    // apriNote(index): legge record.note da window.images e lo mostra nel modal.
    // Il dato è già in memoria (caricato da PHP via json_encode) → zero richieste di rete.
    // textContent (non innerHTML) → prevenzione XSS: il testo viene trattato come testo puro.
    function apriNote(index) {
        var modal  = document.getElementById('noteModal');
        var txtEl  = document.getElementById('noteModalText');
        var metaEl = document.getElementById('noteModalMeta');
        if (!modal || !txtEl || !window.images) return;

        var record = window.images[index];
        if (!record || !record.note) return;

        // innerHTML perché la nota è HTML sanitizzato (strip_tags con whitelist in classifica_immagini.php).
        // Le immagini incollate sono salvate come <img src="note_img/..."> — devono essere renderizzate.
        // textContent le mostrerebbe come testo letterale "<img src=...>" invece dell'immagine vera.
        txtEl.innerHTML = record.note;
        metaEl.textContent = record.data_ora || '';

        modal.classList.add('active');
        document.body.style.overflow = 'hidden'; // blocca scroll pagina
    }

    function chiudiNote() {
        var modal = document.getElementById('noteModal');
        if (modal) modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Click sull'overlay fuori dal box → chiudi
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('noteModal');
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) chiudiNote();
            });
        }
    });

    // ESC → chiudi modal note (stopPropagation: non chiudere anche il lightbox)
    document.addEventListener('keydown', function (e) {
        var key = e.key || e.code;
        var modal = document.getElementById('noteModal');
        if (modal && modal.classList.contains('active') && (key === 'Escape' || key === 'Esc')) {
            chiudiNote();
            e.stopPropagation();
        }
    });

    window.apriNote  = apriNote;
    window.chiudiNote = chiudiNote;
})();
</script>
<!-- ========== JAVASCRIPT ========== -->
<script>
// Passa i dati al JS
window.images = <?php echo json_encode($records); ?>;

// Mappa numerico → etichetta per il campo "altro".
// PRINCIPIO DRY: la mappa è definita una sola volta in PHP ($altro_labels)
// e serializzata qui in JSON. Il JS la riceve come dato, non la ridefinisce.
// Aggiungere un nuovo valore = modifica solo a $altro_labels in PHP.
window.altroLabels = <?php echo json_encode(array_map(function($l) {
    // Rimuove il prefisso "↳ " per il lightbox: più pulito in una riga orizzontale
    return ltrim($l, '↳ ');
}, $altro_labels)); ?>;

// Toggle barra filtri
function toggleFilterBar() {
    const filterBar = document.getElementById('filterBar');
    filterBar.classList.toggle('active');
}

// Chiudi barra filtri e ricarica pagina senza parametri
function closeFilterBar() {
    // accendi spinner SUBITO
    var sp = document.getElementById('loading-spinner');
    if (sp) sp.classList.remove('hidden');
    document.documentElement.classList.add('page-loading');

    // ricarica senza parametri
    window.location.href = window.location.pathname;
}

</script>

<script>
(function() {
    'use strict';
    
    let currentIndex = 0;

    // ========== UTILITY FUNCTIONS ==========
    function isFiniteNumber(n) { 
        return typeof n === 'number' && isFinite(n); 
    }

    function numOrNull(v) {
        return (v === null || v === '' || !isFinite(+v)) ? null : (+v);
    }

    function get(obj, key) {
        return (obj && obj[key] !== null) ? obj[key] : null;
    }

    function getStr(obj, key) {
        var v = get(obj, key);
        return (v === null) ? '' : String(v);
    }

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
                cb(src);
            }
        };
        tempImg.onerror = function () { cb(src); };
        tempImg.src = src;
    }

    function buildInfoText(record) {
        var d = record.data_ora || 'N/A';
        var t = parseFloat(record.temp);
        var tTxt = isFinite(t) ? Math.round(t) + '°C' : 'N/A';
        var hr = parseFloat(record.hr);
        var hTxt = isFinite(hr) ? Math.round(hr) + '%' : 'N/A';
        var p = parseFloat(record.p_hpa);
        var pTxt = isFinite(p) ? Math.round(p) + ' hPa' : 'N/A';
        var windKmh = parseFloat(record.wind_kmh);
        var wTxt = isFinite(windKmh) ? windKmh + ' km/h' : 'N/A';
        var dirGradi = parseFloat(record.dir_text);
        var dTxt = isFinite(dirGradi) ? dirTesto(dirGradi) : record.dir_text || 'N/A';

        // sun_phase: il campo nel record si chiama 'sun_phase' (non 'alba_tramonto')
        var sunPhase = '';
        var sp = parseInt(record.sun_phase);
        if (sp === 1) { sunPhase = ' | 🌅 Alba'; }
        else if (sp === 2) { sunPhase = ' | 🌇 Tramonto'; }

        // altro: traduce il valore numerico in etichetta leggibile
        // usando la mappa serializzata da PHP (window.altroLabels).
        // String() perché le chiavi del JSON sono sempre stringhe.
        var altroTxt = '';
        var altroVal = record.altro;
        if (altroVal !== null && altroVal !== undefined && altroVal !== '') {
            var label = (window.altroLabels || {})[String(altroVal)];
            if (label) { altroTxt = ' | ' + label; }
        }

        return d + ' | T ' + tTxt + ' | UR ' + hTxt + ' | ' + pTxt +
               ' | Vento ' + wTxt + ', ' + dTxt + sunPhase + altroTxt;
    }

    // ========== LIGHTBOX FUNCTIONS ==========
    function openLightbox(index) {
        if (!window.images || window.images.length === 0) return;

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
        if (event) event.stopPropagation();
        // prev = vai indietro nel tempo = indice piÃƒÆ’Ã‚¹ alto
        if (currentIndex < window.images.length - 1) {
            openLightbox(currentIndex + 1);
        }
    }

    function nextImage(event) {
        if (event) event.stopPropagation();
        // next = vai avanti nel tempo = indice piÃƒÆ’Ã‚¹ basso
        if (currentIndex > 0) {
            openLightbox(currentIndex - 1);
        }
    }

    function updateNavButtons() {
        const prevBtn = document.querySelector('.nav-btn.prev');
        const nextBtn = document.querySelector('.nav-btn.next');
        
        // prev disabilitato quando sei all'ultima (piÃƒÆ’Ã‚¹ vecchia)
        if (prevBtn) prevBtn.disabled = (currentIndex === window.images.length - 1);
        // next disabilitato quando sei alla prima (piÃƒÆ’Ã‚¹ recente)
        if (nextBtn) nextBtn.disabled = (currentIndex === 0);
    }

    // ========== EVENT LISTENERS ==========
    document.addEventListener('DOMContentLoaded', function() {
        // Close button
        const closeBtn = document.getElementById('close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeLightbox);
        }
        
        // Navigation buttons
        const prevBtn = document.querySelector('.nav-btn.prev');
        const nextBtn = document.querySelector('.nav-btn.next');
        if (prevBtn) prevBtn.addEventListener('click', prevImage);
        if (nextBtn) nextBtn.addEventListener('click', nextImage);
        
                // ===== Spinner: attivo finchÃ© le miniature non hanno caricato =====
        (function() {
            var spinner = document.getElementById('loading-spinner');
            if (!spinner) return;

            // Se non c'Ã¨ galleria, nascondi subito
            var gallery = document.querySelector('.gallery');
            if (!gallery) { spinner.classList.add('hidden'); return; }

            var imgs = gallery.querySelectorAll('img');
            if (!imgs || imgs.length === 0) { spinner.classList.add('hidden'); return; }

            var done = 0;
            function oneDone() {
                done++;
                if (done >= imgs.length) {
                    spinner.classList.add('hidden');
                }
            }

            // Se alcune sono giÃ  in cache
            for (var i = 0; i < imgs.length; i++) {
                var im = imgs[i];
                if (im.complete) {
                    oneDone();
                } else {
                    im.addEventListener('load', oneDone, { once: true });
                    im.addEventListener('error', oneDone, { once: true });
                }
            }
        })();

    });

    // Keyboard navigation
    document.addEventListener('keydown', function (event) {
        var lb = document.getElementById('lightbox');
        if (!lb || !lb.classList.contains('active')) return;

        var key = event.key || event.code;

        if (key === 'Escape' || key === 'Esc') {
            closeLightbox();
            return;
        }

        // ArrowLeft = indietro nel tempo = indice piÃƒÆ’Ã‚¹ alto
        if (key === 'ArrowLeft') {
            if (currentIndex < window.images.length - 1) {
                openLightbox(currentIndex + 1);
            }
        }

        // ArrowRight = avanti nel tempo = indice piÃƒÆ’Ã‚¹ basso
        if (key === 'ArrowRight') {
            if (currentIndex > 0) {
                openLightbox(currentIndex - 1);
            }
        }
    });

    // Touch swipe
    var lightbox = document.getElementById('lightbox');
    if (lightbox) {
        var touchStartX = 0;
        var touchEndX = 0;

        lightbox.addEventListener('touchstart', function (e) {
            touchStartX = e.changedTouches[0].screenX;
        });

        lightbox.addEventListener('touchend', function (e) {
            touchEndX = e.changedTouches[0].screenX;
            var threshold = 50;
            
            // swipe left = indietro nel tempo
            if (touchEndX < touchStartX - threshold) {
                if (currentIndex < window.images.length - 1) {
                    openLightbox(currentIndex + 1);
                }
            } 
            // swipe right = avanti nel tempo
            else if (touchEndX > touchStartX + threshold) {
                if (currentIndex > 0) {
                    openLightbox(currentIndex - 1);
                }
            }
        });
    }

    // ========== GLOBAL EXPORTS ==========
    window.openLightbox = openLightbox;
    window.closeLightbox = closeLightbox;
    window.prevImage = prevImage;
    window.nextImage = nextImage;
})();
</script>
<script>
(function () {
  'use strict';

  function spinnerOn() {
    var sp = document.getElementById('loading-spinner');
    if (sp) sp.classList.remove('hidden');
    document.documentElement.classList.add('page-loading');
  }

  function spinnerOff() {
    var sp = document.getElementById('loading-spinner');
    if (sp) sp.classList.add('hidden');
    document.documentElement.classList.remove('page-loading');
  }

  function go(url) {
    spinnerOn();
    // 2 frame => repaint garantito prima della navigazione
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        window.location.href = url;
      });
    });
  }

  function submitNative(form) {
    spinnerOn();
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        // submit() non rilancia l€™evento submit => niente loop
        form.submit();
      });
    });
  }

  // 1) CLICK: intercetta PAGINA PRECEDENTE / SUCCESSIVA
  document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('a.pager-item[href]') : null;
    if (a) {
      e.preventDefault();
      go(a.href);
      return;
    }

    // 2) CLICK: bottone "Applica filtri" (submit button)
    var btn = e.target && e.target.closest ? e.target.closest('button[type="submit"]') : null;
    if (btn) {
      var form = btn.form;
      if (form && form.closest && form.closest('.filter-bar')) {
        // accendi subito lo spinner (anche se poi il submit passa da event submit)
        spinnerOn();
      }
    }

    // 3) CLICK: X chiudi filtri
    var x = e.target && e.target.closest ? e.target.closest('.filter-close') : null;
    if (x) {
      e.preventDefault();
      go(window.location.pathname);
      return;
    }
  }, true);

  // 4) SUBMIT: filtri + pager
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form) return;

    var isFilterForm = form.closest && form.closest('.filter-bar');
    var isPagerForm  = form.classList && form.classList.contains('pager-form');

    if (isFilterForm || isPagerForm) {
      e.preventDefault();
      submitNative(form);
    }
  }, true);

  // 5) CHANGE: select pagina => submit form
  document.addEventListener('change', function (e) {
    var el = e.target;
    if (el && el.id === 'pageSelect' && el.form) {
      e.preventDefault();
      submitNative(el.form);
    }
  }, true);

  // 6) BFCache: se torni indietro/avanti, pulisci stato
  window.addEventListener('pageshow', function (ev) {
    // se la pagina viene ripristinata dalla cache, togli spinner
    spinnerOff();
  });

})();
</script>
<script>

// Toggle menu in alto a dx
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.querySelector('.menu-toggle');
    const submenu = document.querySelector('.submenu');
    var autoCloseTimer = null;

    function openMenu() {
        submenu.classList.add('active');
        clearTimeout(autoCloseTimer);
        autoCloseTimer = setTimeout(closeMenu, 5000);
    }

    function closeMenu() {
        submenu.classList.remove('active');
        clearTimeout(autoCloseTimer);
        // chiudi anche eventuali sub-submenu aperti
        document.querySelectorAll('.has-sub.sub-active').forEach(function(el) {
            el.classList.remove('sub-active');
        });
    }

    menuToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (submenu.classList.contains('active')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    // Gestione sub-submenu (click su .has-sub)
    document.querySelectorAll('.has-sub').forEach(function(item) {
        item.addEventListener('click', function(e) {
            e.stopPropagation();
            // resetta timer auto-close: l'utente sta interagendo
            clearTimeout(autoCloseTimer);
            autoCloseTimer = setTimeout(closeMenu, 5000);
            item.classList.toggle('sub-active');
        });
    });

    // Chiudi tutto cliccando fuori
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.header-menu-container')) {
            closeMenu();
        }
    });
});
</script>




<script>
// ── MENU CONTESTUALE SOTTOCATEGORIE ──────────────────────────────────────────
//
// COME FUNZIONA:
//   1. L'utente cambia il select "Categoria" (evento onchange)
//   2. aggiornaAltroSub() riceve il valore scelto
//   3. Se la categoria ha sottotipi → mostra il secondo select e lo popola
//   4. Se la categoria non ha sottotipi → nasconde il secondo select
//
// PERCHÉ in JS e non in PHP?
//   PHP genera HTML statico al caricamento della pagina.
//   La reazione a un'azione dell'utente (cambio select) deve avvenire
//   nel browser, senza ricaricare la pagina → responsabilità del JS.
//
// PRINCIPIO — Separazione dei dati dalla logica:
//   I dati (quale categoria ha quali figli) stanno nell'oggetto `sottotipi`.
//   La logica (aggiorna DOM) sta nella funzione.
//   Aggiungere nuovi sottotipi = modificare solo l'oggetto dati.
// ─────────────────────────────────────────────────────────────────────────────

const sottotipi = {
    '0': { // Nuvole
        'all': 'Tutte le nuvole',
        '10':  'Cirri',
        '11':  'Cirrocumuli / Cirrostrati',
        '12':  'Altocumuli / Altostrati',
        '13':  'Cumuli',
        '14':  'Cumulonembi',
        '15':  'Strati / Stratocumuli',
        '16':  'Nembostrati',
        '17':  'Nebbia'
    },
    '6': { // Pioggia (sottotipi riservati per futuro)
        'all': 'Tutta la pioggia'
    },
    '4': { // Neve (sottotipi riservati per futuro)
        'all': 'Tutta la neve'
    }
    // Per aggiungere nuova categoria con sottotipi:
    // 'X': { 'all': 'Tutti', 'X0': 'Sottotipo 1', ... }
};

function aggiornaAltroSub(valore) {
    const gruppo = document.getElementById('altro-sub-group');
    const sel    = document.getElementById('altro_sub');

    if (!sottotipi[valore]) {
        // Categoria senza sottotipi: nascondi il secondo select
        // e resetta il valore a 'all' per non mandare parametri spuri nella GET
        gruppo.style.display = 'none';
        sel.innerHTML = '<option value="all">Tutti</option>';
        sel.value = 'all';
        return;
    }

    // Categoria con sottotipi: popola e mostra il secondo select
    sel.innerHTML = '';
    for (const [val, label] of Object.entries(sottotipi[valore])) {
        const opt = document.createElement('option');
        opt.value = val;
        opt.textContent = label;
        sel.appendChild(opt);
    }
    sel.value = 'all'; // default: tutte le sottocategorie
    gruppo.style.display = '';
}
</script>

<script>
// ── GESTIONE CHECKBOX SEQUENZA ────────────────────────────────────────────
// Quando l'utente spunta "Sequenza", gli altri controlli del form
// vengono visivamente disabilitati (opacity + pointer-events).
// PERCHÉ solo visivamente e non con disabled=true?
//   Se li disabilitassimo davvero, i loro valori non verrebbero inviati
//   nella GET — ma non serve perché a monte (PHP) li ignoriamo comunque.
//   La disabilitazione visiva serve solo a comunicare all'utente che
//   quei filtri non hanno effetto in questo momento.
function gestisciSequenza(cb) {
    const attivo = cb.checked;

    // 1) Disabilita visivamente tutti i filter-group tranne quello di sequenza
    document.querySelectorAll('#filterBar .filter-group:not(:has(#sequenza))')
        .forEach(function(g) {
            g.style.opacity       = attivo ? '0.35' : '';
            g.style.pointerEvents = attivo ? 'none'  : '';
        });

    // 2) Disabilita/abilita esplicitamente il checkbox con_nota.
    //    PERCHÉ disabled=true e non solo pointer-events?
    //    pointer-events blocca il click ma il valore spuntato verrebbe
    //    comunque inviato nella GET. Con disabled=true il browser
    //    esclude il campo dal submit — coerente con il fatto che PHP
    //    ignora con_nota quando sequenza è attiva.
    var notaCb = document.getElementById('con_nota');
    if (notaCb) {
        notaCb.disabled = attivo;
        if (attivo) notaCb.checked = false;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var cb = document.getElementById('sequenza');
    if (cb) gestisciSequenza(cb);
});


</script>

</body>
</html>