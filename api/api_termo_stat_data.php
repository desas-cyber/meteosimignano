<?php
/**
 * ============================================================================
 * STATISTICA TERMICA (STI) - DATA LAYER  api/api_termo_stat_data.php
 * ============================================================================
 *
 * RESPONSABILITA':
 * - Mantiene aggiornata la tabella termo_stat_mensile: quando un mese si
 *   chiude, lo calcola dai dati al minuto e lo scrive (una volta sola).
 * - Calcola le medie mobili su 1, 3, 6, 12 mesi e l'indice termico
 *   standardizzato STI = (valore - media_rif) / sd_rif, con la classe McKee.
 * - Restituisce: ultimo mese chiuso, serie completa, normali mensili e il
 *   mese in corso come valore provvisorio (mai salvato).
 * - Fornisce la norma di un giorno o di un periodo qualsiasi
 *   (termoNormaPeriodo), per le anomalie in gradi di tabella_stat_display.
 *
 * REGOLE (claude/metodo_serie_simignano.md):
 * - giorno valido : almeno il 90% delle letture attese (1296 su 1440;
 *                   nei giorni del cambio d'ora l'attesa e' 1380 o 1500)
 * - mese valido   : almeno 25 giorni validi
 * - finestra      : valida solo se tutti i suoi mesi sono validi
 * - riferimento   : versione attiva in termo_stat_versione (oggi
 *                   rif_2003_2025_v1), parametri in termo_stat_riferimento
 *
 * CONVENZIONI PROGETTO:
 * - Connessioni PDO: $pdo (envelop.php, lettura+scrittura),
 *   $pdo_lettura (envelop_lettura.php, lettura).
 *   Le letture usano $pdo_lettura; la scrittura dei mesi chiusi usa $pdo se
 *   disponibile, altrimenti il mese viene calcolato al volo e non salvato.
 * - datetime_helper.php: get_now() al posto di date() (ambiente test locale)
 * - env_tables_helper.php: table_name() per il suffisso _test (test globale)
 *
 * MATERIALIZZAZIONE PIGRA:
 * - La prima richiesta dopo la chiusura di un mese lo calcola e lo scrive;
 *   tutte le successive lo leggono gia' pronto (una SELECT su ~300 righe).
 * - Idempotente: SELECT, poi INSERT del mese nuovo o UPDATE di quello gia' scritto.
 * - Concorrenza: GET_LOCK impedisce che due richieste simultanee calcolino
 *   lo stesso mese; chi non ottiene il lock serve i dati gia' presenti.
 * - Dati arrivati in ritardo: gli ultimi TERMO_RICONTROLLO_MESI mesi scritti
 *   dall'API vengono ricalcolati. I mesi del seed (serie ricostruita,
 *   origine = 'seed') non vengono MAI toccati.
 *
 * PREREQUISITI:
 * - claude/termo_stat_schema.sql eseguito sul DB principale
 * - dati_meteo_simignano con indice su data_ora (e' gia' UNIQUE)
 * - MySQL 8.0.16+ oppure MariaDB 10.3+ (nessuna sintassi specifica dell'uno o dell'altro)
 */

require_once __DIR__ . '/../datetime_helper.php';   // helper per ambiente test locale
require_once __DIR__ . '/../env_tables_helper.php'; // helper per ambiente test globale

// ---------------------------------------------------------------------------
// CONFIGURAZIONE
// ---------------------------------------------------------------------------
// Tutte le tabelle passano da table_name() (env_tables_helper.php): in
// ambiente test si usano le copie con suffisso _test, in produzione quelle
// senza suffisso. Ogni ambiente ha quindi il suo set completo di quattro
// tabelle (dati al minuto, mesi, versioni, parametri di riferimento).
const TERMO_TAB_MINUTI        = 'dati_meteo_simignano';
const TERMO_TAB_MENSILE       = 'termo_stat_mensile';
const TERMO_TAB_RIF           = 'termo_stat_riferimento';
const TERMO_TAB_VERSIONE      = 'termo_stat_versione';
const TERMO_COL_DATA          = 'data_ora';
const TERMO_COL_TEMP          = 'temperatura_C';

const TERMO_TEMP_MIN          = -30.0;  // range fisico plausibile (come le altre API)
const TERMO_TEMP_MAX          = 50.0;
const TERMO_QUOTA_GIORNO      = 0.90;   // quota di letture per un giorno valido
const TERMO_GIORNI_MESE_MIN   = 25;     // giorni validi per un mese valido
const TERMO_FINESTRE          = [1, 3, 6, 12];
const TERMO_VARIABILI         = ['media' => 't_media', 'media_max' => 't_max_media', 'media_min' => 't_min_media'];
const TERMO_RICONTROLLO_MESI  = 2;      // mesi 'api' ricalcolati per dati tardivi
const TERMO_MAX_MESI_PER_RICHIESTA = 6; // tetto al recupero in una singola richiesta
const TERMO_LOCK_NOME         = 'termo_stat_aggiornamento';

// STIMA DEL MESE IN CORSO (pallino "oggi")
// - correzione stagionale: la media dei giorni disponibili viene riportata al
//   mese intero sottraendo quanto quei giorni sono normalmente piu' caldi o
//   freddi del mese (ciclo annuo da 3 armoniche adattate alle normali mensili)
// - incertezza: sigma = S * (giorni mancanti / giorni del mese), con S tarato
//   sui mesi reali apr 2025 - ago 2026 (a meta' mese: media 1,1 °C,
//   massime 1,6 °C, minime 1,3 °C). Da ritarare quando ci saranno piu' anni.
const TERMO_PROVV_GIORNI_MIN  = 5;      // sotto questa soglia niente stima
const TERMO_PROVV_S           = ['media' => 2.00, 'media_max' => 2.75, 'media_min' => 2.36];


// ---------------------------------------------------------------------------
// CONNESSIONI (convenzione progetto: PDO)
// ---------------------------------------------------------------------------
/** Connessione PDO per le query di lettura. */
function termoPdoLettura(): ?PDO {
    if (isset($GLOBALS['pdo_lettura']) && $GLOBALS['pdo_lettura'] instanceof PDO) return $GLOBALS['pdo_lettura'];
    if (isset($GLOBALS['pdo'])         && $GLOBALS['pdo']         instanceof PDO) return $GLOBALS['pdo'];
    return null;
}

/** Connessione PDO con permessi di scrittura. Null = i mesi nuovi non vengono salvati. */
function termoPdoScrittura(): ?PDO {
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) return $GLOBALS['pdo'];
    return null;
}


// ---------------------------------------------------------------------------
// FUNZIONE PRINCIPALE
// ---------------------------------------------------------------------------
/**
 * ORCHESTRATORE.
 *
 * @param bool $con_serie  true = include la serie mensile completa (per i
 *                         grafici); false = solo ultimo mese, normali e
 *                         provvisorio (per una tabella riassuntiva)
 * @return array { success, meta, ultimo, provvisorio, normali, serie? }
 */
function getTermoStatData(bool $con_serie = true): array {
    $pdo_r = termoPdoLettura();
    if (!$pdo_r) {
        return ['success' => false,
                'error'   => 'Connessione PDO non trovata: includere envelop.php o envelop_lettura.php prima dell\'API'];
    }

    $oggi = new DateTime(get_now('Y-m-d'));
    // Primo giorno del mese in corso e dell'ultimo mese chiuso
    $mese_corrente = (clone $oggi)->modify('first day of this month');
    $ultimo_chiuso = (clone $mese_corrente)->modify('-1 month');

    // ---- 1. materializzazione pigra dei mesi chiusi mancanti ----
    $aggiornamento = termoAggiornaMensili($pdo_r, $ultimo_chiuso);
    if ($aggiornamento['stato'] === 'tabella vuota') {
        return ['success' => false,
                'error'   => 'La tabella ' . table_name(TERMO_TAB_MENSILE) . ' e\' vuota: caricare i mesi del seed'];
    }

    // ---- 2. lettura di mensili e riferimento ----
    try {
        $versione = termoVersioneAttiva($pdo_r);
        if ($versione === null) {
            return ['success' => false, 'error' => 'Nessuna versione di riferimento attiva in ' . table_name(TERMO_TAB_VERSIONE)];
        }
        $rif     = termoLeggiRiferimento($pdo_r, $versione['versione']);
        $mensili = termoLeggiMensili($pdo_r);
    } catch (PDOException $e) {
        error_log('getTermoStatData lettura: ' . $e->getMessage());
        if ($e->getCode() === '42S02') {   // tabella inesistente
            return ['success' => false, 'error' => 'Tabella mancante (' . $e->getMessage() . '): '
                . (table_name(TERMO_TAB_MENSILE) !== TERMO_TAB_MENSILE
                    ? 'ambiente TEST, servono le tabelle termo_stat_*_test'
                    : 'ambiente PRODUZIONE, servono le tabelle termo_stat_* senza suffisso (termo_stat_produzione.sql)')];
        }
        return ['success' => false, 'error' => 'Errore DB statistica termica: ' . $e->getMessage()];
    }

    // Se la scrittura non e' disponibile, i mesi calcolati al volo si
    // aggiungono in memoria: la risposta resta completa, solo non persiste.
    foreach ($aggiornamento['non_salvati'] as $riga) {
        $mensili[$riga['mese']] = $riga;
    }
    ksort($mensili);

    // ---- 3. medie mobili e STI ----
    $serie = termoCalcolaSti($mensili, $rif);

    // Ultimo mese chiuso presente in serie (di norma = $ultimo_chiuso)
    $ultimo = null;
    for ($k = end($serie); $k !== false; $k = prev($serie)) {
        if ($k['valido']) { $ultimo = $k; break; }
    }

    // ---- 4. mese in corso: provvisorio, mai salvato ----
    $provvisorio = null;
    try {
        $ieri = (clone $oggi)->modify('-1 day');
        if ($ieri >= $mese_corrente) {
            $provvisorio = termoCalcolaMese($pdo_r, $mese_corrente, (clone $ieri)->modify('+1 day'));
            $provvisorio['fino_al'] = $ieri->format('Y-m-d');
        }
    } catch (PDOException $e) {
        error_log('getTermoStatData provvisorio: ' . $e->getMessage());
    }

    // ---- 4b. stima del mese in corso al giorno corrente ----
    $stima_oggi = termoStimaOggi($provvisorio, $mensili, $rif, $mese_corrente);

    // ---- 5. normali mensili (finestra 1 del riferimento) ----
    $normali = [];
    for ($m = 1; $m <= 12; $m++) {
        $riga = ['mese' => $m];
        foreach (array_keys(TERMO_VARIABILI) as $var) {
            $p = $rif[$var][1][$m] ?? null;
            $riga[$var] = $p ? ['media' => $p['media_rif'], 'sd' => $p['sd_rif']] : null;
        }
        $normali[] = $riga;
    }

    $out = [
        'success'     => true,
        'meta'        => [
            'versione'        => $versione['versione'],
            'periodo_rif'     => $versione['periodo_da'] . "\u{2013}" . $versione['periodo_a'],
            'ultimo_chiuso'   => $ultimo_chiuso->format('Y-m-d'),
            'oggi'            => $oggi->format('Y-m-d'),
            'mesi_calcolati'  => $aggiornamento['calcolati'],
            'mesi_non_salvati'=> array_keys($aggiornamento['non_salvati']),
            'aggiornamento'   => $aggiornamento['stato'],
            'errore_aggiornamento' => $aggiornamento['errore'] ?? null,
            'giorni_mese_min' => TERMO_GIORNI_MESE_MIN,
            'quota_giorno'    => TERMO_QUOTA_GIORNO,
            'generato_il'     => get_now('d/m/Y H:i'),
        ],
        'ultimo'      => $ultimo,
        'provvisorio' => $provvisorio,
        'normali'     => $normali,
        'stima_oggi'  => $stima_oggi,
        // parametri compatti: [variabile][finestra] = 12 coppie [media_rif, sd_rif]
        'parametri'   => termoParametriCompatti($rif),
    ];
    if ($con_serie) $out['serie'] = array_values($serie);
    return $out;
}


// ---------------------------------------------------------------------------
// MATERIALIZZAZIONE DEI MESI CHIUSI
// ---------------------------------------------------------------------------
/**
 * Porta termo_stat_mensile fino a $ultimo_chiuso.
 *
 * Mesi da calcolare:
 *   - quelli successivi all'ultimo presente in tabella, fino all'ultimo chiuso
 *   - gli ultimi TERMO_RICONTROLLO_MESI con origine 'api' (dati tardivi)
 *
 * @return array { stato, calcolati[], non_salvati[mese => riga] }
 */
function termoAggiornaMensili(PDO $pdo_r, DateTime $ultimo_chiuso): array {
    $esito = ['stato' => 'nessuno', 'calcolati' => [], 'non_salvati' => []];
    $tab   = table_name(TERMO_TAB_MENSILE);

    try {
        $ultimo_in_tab = $pdo_r->query("SELECT MAX(mese) FROM $tab")->fetchColumn();
        if (!$ultimo_in_tab) {
            // Tabella vuota: il seed non e' stato caricato. Non si ricostruisce
            // lo storico dai dati al minuto (partono dal 2025).
            $esito['stato'] = 'tabella vuota';
            return $esito;
        }

        $da_calcolare = [];
        $cur = (new DateTime($ultimo_in_tab))->modify('+1 month');
        while ($cur <= $ultimo_chiuso && count($da_calcolare) < TERMO_MAX_MESI_PER_RICHIESTA) {
            $da_calcolare[] = $cur->format('Y-m-d');
            $cur->modify('+1 month');
        }

        // Ricontrollo degli ultimi mesi scritti dall'API (mai quelli del seed)
        $stmt = $pdo_r->prepare("SELECT mese FROM $tab WHERE origine = 'api'
                                 ORDER BY mese DESC LIMIT " . (int)TERMO_RICONTROLLO_MESI);
        $stmt->execute();
        $ricontrollo = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log('termoAggiornaMensili: ' . $e->getMessage());
        $esito['stato'] = 'errore lettura';
        $esito['errore'] = $e->getMessage();
        return $esito;
    }

    if (!$da_calcolare && !$ricontrollo) return $esito;

    $pdo_w = termoPdoScrittura();

    // Ricontrollo: solo se c'e' la scrittura (senza, non cambierebbe nulla)
    // e al massimo una volta al giorno per mese, confrontando calcolato_il.
    if ($pdo_w && $ricontrollo) {
        $oggi = get_now('Y-m-d');
        $stmt = $pdo_r->prepare("SELECT mese FROM $tab WHERE mese IN ("
                . implode(',', array_fill(0, count($ricontrollo), '?'))
                . ") AND DATE(calcolato_il) < ?");
        $stmt->execute(array_merge($ricontrollo, [$oggi]));
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $m) {
            if (!in_array($m, $da_calcolare, true)) $da_calcolare[] = $m;
        }
    }
    if (!$da_calcolare) return $esito;
    sort($da_calcolare);

    // ---- lock: una sola richiesta alla volta calcola ----
    $lock_ok = false;
    if ($pdo_w) {
        try {
            $lock_ok = (int)$pdo_w->query("SELECT GET_LOCK(" . $pdo_w->quote(TERMO_LOCK_NOME) . ", 0)")->fetchColumn() === 1;
        } catch (PDOException $e) {
            $lock_ok = false;
            $esito['errore'] = 'GET_LOCK: ' . $e->getMessage();
        }
        if (!$lock_ok) {
            $esito['stato'] = 'aggiornamento gia in corso';
            return $esito;
        }
    }

    try {
        foreach ($da_calcolare as $mese) {
            $inizio = new DateTime($mese);
            $fine   = (clone $inizio)->modify('+1 month');
            $riga   = termoCalcolaMese($pdo_r, $inizio, $fine);

            $scritto = false;
            if ($pdo_w) {
                try {
                    termoScriviMese($pdo_w, $riga);
                    $scritto = true;
                } catch (PDOException $e) {
                    // scrittura fallita: il mese si mostra comunque, calcolato al volo
                    error_log('termoScriviMese ' . $riga['mese'] . ': ' . $e->getMessage());
                    $esito['errore'] = 'scrittura ' . $riga['mese'] . ': ' . $e->getMessage();
                }
            }
            if (!$scritto) $esito['non_salvati'][$riga['mese']] = $riga;
            $esito['calcolati'][] = $riga['mese'];
        }
        $esito['stato'] = !$pdo_w ? 'calcolato senza scrittura'
                        : (empty($esito['non_salvati']) ? 'aggiornato' : 'errore scrittura');
    } catch (PDOException $e) {
        error_log('termoAggiornaMensili calcolo: ' . $e->getMessage());
        $esito['stato'] = 'errore calcolo';
        $esito['errore'] = $e->getMessage();
    } finally {
        if ($pdo_w && $lock_ok) {
            try { $pdo_w->query("SELECT RELEASE_LOCK(" . $pdo_w->quote(TERMO_LOCK_NOME) . ")"); }
            catch (PDOException $e) { /* il lock decade comunque a fine connessione */ }
        }
    }
    return $esito;
}

/**
 * Calcola un mese (o una sua parte) dai dati al minuto.
 *
 * Una query GROUP BY giorno (~30 righe) sulla finestra [inizio, fine):
 * i giorni validi si selezionano in PHP confrontando le letture con quelle
 * attese per QUEL giorno (1440, oppure 1380/1500 al cambio d'ora).
 *
 * @return array riga compatibile con termo_stat_mensile
 */
function termoCalcolaMese(PDO $pdo_r, DateTime $inizio, DateTime $fine): array {
    $tab  = table_name(TERMO_TAB_MINUTI);
    $cd   = TERMO_COL_DATA;
    $ct   = TERMO_COL_TEMP;

    $stmt = $pdo_r->prepare("
        SELECT DATE($cd)  AS g,
               COUNT(*)   AS n,
               AVG($ct)   AS t_med,
               MAX($ct)   AS t_max,
               MIN($ct)   AS t_min
        FROM $tab
        WHERE $cd >= :inizio AND $cd < :fine
          AND $ct BETWEEN :tmin AND :tmax
        GROUP BY g
        ORDER BY g
    ");
    $stmt->execute([
        ':inizio' => $inizio->format('Y-m-d 00:00:00'),
        ':fine'   => $fine->format('Y-m-d 00:00:00'),
        ':tmin'   => TERMO_TEMP_MIN,
        ':tmax'   => TERMO_TEMP_MAX,
    ]);

    $med = []; $max = []; $min = []; $lista = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $attese = termoLettureAttese($r['g']);
        if ((int)$r['n'] >= (int)ceil(TERMO_QUOTA_GIORNO * $attese)) {
            $lista[] = (int)substr($r['g'], 8, 2);
            $med[] = (float)$r['t_med'];
            $max[] = (float)$r['t_max'];
            $min[] = (float)$r['t_min'];
        }
    }

    $gv     = count($med);
    $valido = $gv >= TERMO_GIORNI_MESE_MIN;
    $avg    = fn(array $a) => $a ? round(array_sum($a) / count($a), 2) : null;

    return [
        'mese'          => $inizio->format('Y-m-d'),
        't_media'       => $valido ? $avg($med) : null,
        't_max_media'   => $valido ? $avg($max) : null,
        't_min_media'   => $valido ? $avg($min) : null,
        // valori sui soli giorni validi, utili per il provvisorio
        'parziale'      => ['t_media' => $avg($med), 't_max_media' => $avg($max), 't_min_media' => $avg($min)],
        'giorni_validi' => $gv,
        'giorni_lista'  => $lista,   // giorni del mese validi (per la stima provvisoria)
        'fonte'         => 'reale',
        'valido'        => $valido ? 1 : 0,
        'origine'       => 'api',
        'note'          => $valido ? null : 'meno di ' . TERMO_GIORNI_MESE_MIN . ' giorni validi',
    ];
}

/** Letture al minuto attese in un giorno locale (1440; 1380/1500 al cambio d'ora). */
function termoLettureAttese(string $giorno): int {
    $a = new DateTime($giorno . ' 00:00:00');
    $b = (clone $a)->modify('+1 day');
    return (int)(($b->getTimestamp() - $a->getTimestamp()) / 60);
}

/**
 * Scrittura idempotente di un mese (chiave primaria = mese).
 * SELECT + INSERT oppure UPDATE: sintassi valida su MySQL e MariaDB, senza
 * VALUES() (deprecata in MySQL 8) ne' alias "AS nuovo" (assente in MariaDB).
 * Si esegue sotto GET_LOCK, quindi non ci sono scritture concorrenti; se
 * comunque un'altra richiesta inserisce lo stesso mese nel frattempo, la
 * chiave duplicata viene intercettata e si passa all'UPDATE.
 * I mesi del seed (origine = 'seed') non vengono mai modificati.
 */
function termoScriviMese(PDO $pdo_w, array $r): void {
    $tab = table_name(TERMO_TAB_MENSILE);
    $par = [
        ':mese'   => $r['mese'],
        ':tm'     => $r['t_media'],
        ':tx'     => $r['t_max_media'],
        ':tn'     => $r['t_min_media'],
        ':gv'     => $r['giorni_validi'],
        ':valido' => $r['valido'],
        ':note'   => $r['note'],
    ];
    $sql_update = "
        UPDATE $tab
           SET t_media = :tm, t_max_media = :tx, t_min_media = :tn,
               giorni_validi = :gv, valido = :valido, note = :note,
               calcolato_il = NOW()
         WHERE mese = :mese AND origine = 'api'";

    $sel = $pdo_w->prepare("SELECT origine FROM $tab WHERE mese = :mese");
    $sel->execute([':mese' => $r['mese']]);
    $origine = $sel->fetchColumn();

    if ($origine === 'seed') {
        return;                                   // serie ricostruita: intoccabile
    }
    if ($origine === 'api') {
        $pdo_w->prepare($sql_update)->execute($par);
        return;
    }
    try {
        $pdo_w->prepare("
            INSERT INTO $tab
                (mese, t_media, t_max_media, t_min_media, giorni_validi, fonte, valido, origine, note)
            VALUES
                (:mese, :tm, :tx, :tn, :gv, :fonte, :valido, 'api', :note)
        ")->execute($par + [':fonte' => $r['fonte']]);
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') throw $e;  // non e' una chiave duplicata
        $pdo_w->prepare($sql_update)->execute($par);
    }
}


// ---------------------------------------------------------------------------
// LETTURE
// ---------------------------------------------------------------------------
function termoVersioneAttiva(PDO $pdo_r): ?array {
    $tab = table_name(TERMO_TAB_VERSIONE);
    $row = $pdo_r->query("SELECT versione, periodo_da, periodo_a FROM $tab
                          WHERE attiva = 1 ORDER BY creato_il DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** @return array [variabile][finestra][mese_finale] => {media_rif, sd_rif} */
function termoLeggiRiferimento(PDO $pdo_r, string $versione): array {
    $tab  = table_name(TERMO_TAB_RIF);
    $stmt = $pdo_r->prepare("SELECT variabile, finestra, mese_finale, media_rif, sd_rif
                             FROM $tab WHERE versione = :v");
    $stmt->execute([':v' => $versione]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['variabile']][(int)$r['finestra']][(int)$r['mese_finale']] = [
            'media_rif' => (float)$r['media_rif'],
            'sd_rif'    => (float)$r['sd_rif'],
        ];
    }
    return $out;
}

/** @return array [Y-m-d => riga] ordinato per mese */
function termoLeggiMensili(PDO $pdo_r): array {
    $tab = table_name(TERMO_TAB_MENSILE);
    $out = [];
    foreach ($pdo_r->query("SELECT mese, t_media, t_max_media, t_min_media, giorni_validi,
                                   fonte, valido, origine, note
                            FROM $tab ORDER BY mese")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['mese']] = $r;
    }
    return $out;
}


// ---------------------------------------------------------------------------
// CALCOLO STI
// ---------------------------------------------------------------------------
/**
 * Per ogni mese: medie mobili su 1/3/6/12 mesi e STI per le tre variabili.
 *
 * La finestra si costruisce per DATA (mese - k), non per posizione nella
 * lista: un mese assente in tabella rende la finestra non valida invece di
 * farla scivolare sui mesi sbagliati.
 */
function termoCalcolaSti(array $mensili, array $rif): array {
    // Valori validi indicizzati per mese
    $val = [];
    foreach ($mensili as $mese => $r) {
        foreach (TERMO_VARIABILI as $var => $col) {
            $val[$var][$mese] = ((int)$r['valido'] === 1 && $r[$col] !== null) ? (float)$r[$col] : null;
        }
    }

    $serie = [];
    foreach ($mensili as $mese => $r) {
        $dt        = new DateTime($mese);
        $mese_num  = (int)$dt->format('n');
        $riga = [
            'mese'          => $mese,
            'fonte'         => $r['fonte'],
            'valido'        => (int)$r['valido'] === 1,
            'giorni_validi' => $r['giorni_validi'] !== null ? (int)$r['giorni_validi'] : null,
            'origine'       => $r['origine'],
            'note'          => $r['note'],
        ];

        foreach (array_keys(TERMO_VARIABILI) as $var) {
            foreach (TERMO_FINESTRE as $w) {
                // Media della finestra che termina in questo mese
                $somma = 0.0; $completa = true;
                for ($k = 0; $k < $w; $k++) {
                    $m = (clone $dt)->modify("-$k month")->format('Y-m-d');
                    $v = $val[$var][$m] ?? null;
                    if ($v === null) { $completa = false; break; }
                    $somma += $v;
                }
                $valore = $completa ? $somma / $w : null;
                $p      = $rif[$var][$w][$mese_num] ?? null;
                $sti    = ($valore !== null && $p && $p['sd_rif'] > 0)
                        ? ($valore - $p['media_rif']) / $p['sd_rif'] : null;

                $riga[$var][$w] = [
                    'valore' => $valore !== null ? round($valore, 2) : null,
                    'sti'    => $sti    !== null ? round($sti, 2)    : null,
                    'classe' => termoClasse($sti),
                ];
            }
        }
        $serie[$mese] = $riga;
    }
    return $serie;
}

/** Classi come nell'SPI di McKee. */
function termoClasse(?float $sti): ?array {
    if ($sti === null) return null;
    if ($sti >=  2.0) return ['id' => 'E+', 'label' => 'estremo caldo'];
    if ($sti >=  1.5) return ['id' => 'M+', 'label' => 'molto caldo'];
    if ($sti >=  1.0) return ['id' => 'm+', 'label' => 'moderato caldo'];
    if ($sti >  -1.0) return ['id' => 'N',  'label' => 'nella norma'];
    if ($sti >  -1.5) return ['id' => 'm-', 'label' => 'moderato freddo'];
    if ($sti >  -2.0) return ['id' => 'M-', 'label' => 'molto freddo'];
    return                   ['id' => 'E-', 'label' => 'estremo freddo'];
}


// ---------------------------------------------------------------------------
// STIMA DEL MESE IN CORSO
// ---------------------------------------------------------------------------
/**
 * Stima al giorno corrente, per le tre variabili e le quattro finestre.
 *
 * 1. Valore dei giorni validi del mese in corso (media delle medie, delle
 *    massime, delle minime giornaliere).
 * 2. Correzione stagionale: si sottrae la differenza fra la normale di quei
 *    giorni e la normale del mese intero, lette dal ciclo annuo.
 * 3. Finestre: il mese stimato prende il posto del mese in corso accanto ai
 *    mesi chiusi precedenti (tutti validi, altrimenti la finestra salta).
 * 4. STI con i parametri del mese in corso; l'incertezza si divide per la
 *    lunghezza della finestra.
 *
 * @return array|null { fino_al, giorni, giorni_mese, var: { media: {...}, ... } }
 */
function termoStimaOggi(?array $prov, array $mensili, array $rif, DateTime $mese_corrente): ?array {
    if (!$prov || ($prov['giorni_validi'] ?? 0) < TERMO_PROVV_GIORNI_MIN) return null;

    $mese_num = (int)$mese_corrente->format('n');
    $N        = (int)$mese_corrente->format('t');
    $n        = (int)$prov['giorni_validi'];
    $lista    = $prov['giorni_lista'];

    $out = [
        'mese'        => $mese_corrente->format('Y-m-d'),
        'fino_al'     => $prov['fino_al'] ?? null,
        'giorni'      => $n,
        'giorni_mese' => $N,
        'var'         => [],
    ];

    foreach (TERMO_VARIABILI as $var => $col) {
        $parz = $prov['parziale'][$col] ?? null;
        if ($parz === null || empty($rif[$var][1])) continue;

        $coef  = termoCicloAnnuo($rif[$var][1]);
        $delta = termoCicloMedia($coef, $mese_num, $lista) - termoCicloMedia($coef, $mese_num, range(1, $N));
        $t_eq  = $parz - $delta;
        $sig   = TERMO_PROVV_S[$var] * ($N - $n) / $N;

        $finestre = [];
        foreach (TERMO_FINESTRE as $w) {
            $somma = $t_eq; $ok = true;
            for ($k = 1; $k < $w; $k++) {
                $m = (clone $mese_corrente)->modify("-$k month")->format('Y-m-d');
                $r = $mensili[$m] ?? null;
                if (!$r || (int)$r['valido'] !== 1 || $r[$col] === null) { $ok = false; break; }
                $somma += (float)$r[$col];
            }
            $p = $rif[$var][$w][$mese_num] ?? null;
            if (!$ok || !$p) { $finestre[$w] = null; continue; }
            $val = $somma / $w;
            $sti = ($val - $p['media_rif']) / $p['sd_rif'];
            $finestre[$w] = [
                'valore'  => round($val, 2),
                'sti'     => round($sti, 2),
                'classe'  => termoClasse($sti),
                'sig_t'   => round($sig / $w, 2),
                'sig_sti' => round($sig / $w / $p['sd_rif'], 2),
            ];
        }
        $out['var'][$var] = [
            'parziale' => round($parz, 2),
            'delta'    => round($delta, 2),
            'stimato'  => round($t_eq, 2),
            'sigma'    => round($sig, 2),
            'finestre' => $finestre,
        ];
    }
    return $out;
}

/**
 * Ciclo annuo giornaliero: 3 armoniche (7 coefficienti) adattate ai minimi
 * quadrati in modo che la media di ogni mese riproduca la normale mensile.
 * Anno di 365 giorni; il 29 febbraio usa il valore del 28.
 *
 * @param array $norm [mese => {media_rif, sd_rif}] della finestra 1
 * @return array 7 coefficienti
 */
function termoCicloAnnuo(array $norm): array {
    static $cache = [];
    $chiave = md5(json_encode($norm));
    if (isset($cache[$chiave])) return $cache[$chiave];

    // Righe della matrice: media della base sui giorni di ciascun mese
    $A = []; $b = [];
    for ($m = 1; $m <= 12; $m++) {
        $A[] = termoCicloBaseMedia($m, range(1, (int)(new DateTime("2025-$m-01"))->format('t')));
        $b[] = (float)$norm[$m]['media_rif'];
    }
    // Equazioni normali (A'A) c = A'b, risolte con eliminazione di Gauss
    $k = 7; $M = []; $v = [];
    for ($i = 0; $i < $k; $i++) {
        for ($j = 0; $j < $k; $j++) {
            $M[$i][$j] = 0.0;
            for ($r = 0; $r < 12; $r++) $M[$i][$j] += $A[$r][$i] * $A[$r][$j];
        }
        $v[$i] = 0.0;
        for ($r = 0; $r < 12; $r++) $v[$i] += $A[$r][$i] * $b[$r];
    }
    for ($i = 0; $i < $k; $i++) {
        $piv = $i;
        for ($r = $i + 1; $r < $k; $r++) if (abs($M[$r][$i]) > abs($M[$piv][$i])) $piv = $r;
        [$M[$i], $M[$piv]] = [$M[$piv], $M[$i]];
        [$v[$i], $v[$piv]] = [$v[$piv], $v[$i]];
        for ($r = $i + 1; $r < $k; $r++) {
            $f = $M[$r][$i] / $M[$i][$i];
            for ($c = $i; $c < $k; $c++) $M[$r][$c] -= $f * $M[$i][$c];
            $v[$r] -= $f * $v[$i];
        }
    }
    $coef = array_fill(0, $k, 0.0);
    for ($i = $k - 1; $i >= 0; $i--) {
        $s = $v[$i];
        for ($c = $i + 1; $c < $k; $c++) $s -= $M[$i][$c] * $coef[$c];
        $coef[$i] = $s / $M[$i][$i];
    }
    return $cache[$chiave] = $coef;
}

/** Media della base armonica sui giorni indicati di un mese. */
function termoCicloBaseMedia(int $mese, array $giorni): array {
    $acc = array_fill(0, 7, 0.0);
    foreach ($giorni as $g) {
        $g   = min($g, $mese === 2 ? 28 : 31);
        $doy = (int)(new DateTime(sprintf('2025-%02d-%02d', $mese, $g)))->format('z');
        $w   = 2 * M_PI * $doy / 365;
        $base = [1, cos($w), sin($w), cos(2 * $w), sin(2 * $w), cos(3 * $w), sin(3 * $w)];
        foreach ($base as $i => $x) $acc[$i] += $x;
    }
    $n = max(1, count($giorni));
    return array_map(fn($x) => $x / $n, $acc);
}

/** Valore medio del ciclo annuo sui giorni indicati di un mese. */
function termoCicloMedia(array $coef, int $mese, array $giorni): float {
    $base = termoCicloBaseMedia($mese, $giorni);
    $s = 0.0;
    foreach ($base as $i => $x) $s += $coef[$i] * $x;
    return $s;
}

// ---------------------------------------------------------------------------
// NORMA GIORNALIERA (anomalie in °C di un giorno o di un periodo qualsiasi)
// ---------------------------------------------------------------------------
// Diversa dal ciclo a 3 armoniche qui sopra, che approssima le normali
// mensili (scarto fino a 0,3 °C, ininfluente per la stima del mese perche'
// li' conta solo una differenza interna al mese). Per un'anomalia conta il
// livello, quindi questa curva e' VINCOLATA: la sua media sui giorni di ogni
// mese e' esattamente la normale di quel mese.
//
// Costruzione: ciclo a 3 armoniche + correzione lineare a tratti con 12 nodi
// a meta' mese, i cui valori si ricavano da un sistema 12x12 imponendo che la
// media mensile della correzione sia il residuo del mese.
// Anno di 365 giorni; il 29 febbraio usa il valore del 28.

/**
 * Curva della norma, un valore per giorno dell'anno (indice 0 = 1 gennaio).
 *
 * @param array $norm [mese => {media_rif, ...}] della finestra 1 (12 mesi)
 * @return float[] 365 valori
 */
function termoNormaGiornaliera(array $norm): array {
    static $cache = [];
    $chiave = md5(json_encode($norm));
    if (isset($cache[$chiave])) return $cache[$chiave];

    // Calendario di un anno non bisestile: inizio e lunghezza di ogni mese
    $inizio = []; $lung = []; $g0 = 0;
    for ($m = 1; $m <= 12; $m++) {
        $lung[$m]   = (int)(new DateTime("2025-$m-01"))->format('t');
        $inizio[$m] = $g0;
        $g0 += $lung[$m];
    }

    // 1. base: ciclo a 3 armoniche
    $coef = termoCicloAnnuo($norm);
    $base = [];
    for ($d = 0; $d < 365; $d++) {
        $w = 2 * M_PI * $d / 365;
        $b = [1, cos($w), sin($w), cos(2 * $w), sin(2 * $w), cos(3 * $w), sin(3 * $w)];
        $v = 0.0;
        foreach ($b as $i => $x) $v += $coef[$i] * $x;
        $base[$d] = $v;
    }

    // 2. pesi dei 12 nodi (meta' mese) su ogni giorno: interpolazione lineare periodica
    $nodo = [];
    for ($m = 1; $m <= 12; $m++) $nodo[$m - 1] = $inizio[$m] + ($lung[$m] - 1) / 2;
    $peso = [];   // [giorno][nodo]
    for ($d = 0; $d < 365; $d++) {
        $peso[$d] = array_fill(0, 12, 0.0);
        for ($j = -1; $j < 12; $j++) {
            $xa = $j < 0   ? $nodo[11] - 365 : $nodo[$j];
            $xb = $j >= 11 ? $nodo[0] + 365  : $nodo[$j + 1];
            if ($d >= $xa && $d <= $xb) {
                $t = ($d - $xa) / ($xb - $xa);
                $peso[$d][($j + 12) % 12] += 1 - $t;
                $peso[$d][($j + 1) % 12]  += $t;
                break;
            }
        }
    }

    // 3. sistema: media mensile della correzione = residuo del mese
    $M = []; $r = [];
    for ($m = 1; $m <= 12; $m++) {
        $riga = array_fill(0, 12, 0.0); $sb = 0.0;
        for ($d = $inizio[$m]; $d < $inizio[$m] + $lung[$m]; $d++) {
            $sb += $base[$d];
            foreach ($peso[$d] as $k => $p) $riga[$k] += $p;
        }
        $M[] = array_map(fn($x) => $x / $lung[$m], $riga);
        $r[] = (float)$norm[$m]['media_rif'] - $sb / $lung[$m];
    }
    $k = termoRisolvi($M, $r);

    $curva = [];
    for ($d = 0; $d < 365; $d++) {
        $v = $base[$d];
        foreach ($peso[$d] as $i => $p) $v += $p * $k[$i];
        $curva[$d] = $v;
    }
    return $cache[$chiave] = $curva;
}

/** Sistema lineare quadrato, eliminazione di Gauss con pivot parziale. */
function termoRisolvi(array $M, array $v): array {
    $n = count($v);
    for ($i = 0; $i < $n; $i++) {
        $piv = $i;
        for ($r = $i + 1; $r < $n; $r++) if (abs($M[$r][$i]) > abs($M[$piv][$i])) $piv = $r;
        [$M[$i], $M[$piv]] = [$M[$piv], $M[$i]];
        [$v[$i], $v[$piv]] = [$v[$piv], $v[$i]];
        for ($r = $i + 1; $r < $n; $r++) {
            $f = $M[$r][$i] / $M[$i][$i];
            for ($c = $i; $c < $n; $c++) $M[$r][$c] -= $f * $M[$i][$c];
            $v[$r] -= $f * $v[$i];
        }
    }
    $x = array_fill(0, $n, 0.0);
    for ($i = $n - 1; $i >= 0; $i--) {
        $s = $v[$i];
        for ($c = $i + 1; $c < $n; $c++) $s -= $M[$i][$c] * $x[$c];
        $x[$i] = $s / $M[$i][$i];
    }
    return $x;
}

/**
 * Norma media di un periodo [da, a] (estremi inclusi, Y-m-d), anche a cavallo
 * di due mesi o di due anni. Un solo giorno: da = a.
 *
 * @param array  $normali  elemento 'normali' di getTermoStatData()
 * @param string $var      media | media_max | media_min
 * @return float|null      null se le normali sono incomplete o il periodo non e' valido
 */
function termoNormaPeriodo(array $normali, string $var, string $da, string $a): ?float {
    $norm = [];
    foreach ($normali as $r) {
        if (!isset($r[$var]['media'])) return null;
        $norm[(int)$r['mese']] = ['media_rif' => (float)$r[$var]['media']];
    }
    if (count($norm) !== 12) return null;

    $d0 = DateTime::createFromFormat('!Y-m-d', $da);
    $d1 = DateTime::createFromFormat('!Y-m-d', $a);
    if (!$d0 || !$d1 || $d1 < $d0 || $d0->diff($d1)->days > 366) return null;

    $curva = termoNormaGiornaliera($norm);
    $s = 0.0; $n = 0;
    for ($g = clone $d0; $g <= $d1; $g->modify('+1 day')) {
        $z = (int)$g->format('z');
        // anno bisestile: dal 29 febbraio in poi si arretra di un giorno
        if ($g->format('L') === '1' && $z >= 59) $z--;
        $s += $curva[$z]; $n++;
    }
    return $n ? $s / $n : null;
}

/** Parametri in forma compatta per il client: [variabile][finestra] = 12 x [media, sd]. */
function termoParametriCompatti(array $rif): array {
    $out = [];
    foreach ($rif as $var => $per_w) {
        foreach ($per_w as $w => $per_m) {
            $riga = [];
            for ($m = 1; $m <= 12; $m++) {
                $riga[] = isset($per_m[$m]) ? [$per_m[$m]['media_rif'], $per_m[$m]['sd_rif']] : null;
            }
            $out[$var][$w] = $riga;
        }
    }
    return $out;
}