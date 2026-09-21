<?php
/**
 * ============================================================================
 * STATISTICHE PLUVIOMETRICHE - DATA LAYER  api/api_pluvio_stat_data.php
 * ============================================================================
 *
 * RESPONSABILITA':
 * - Calcola mediana (p50), percentili (p10/p25/p75/p90) e media aritmetica
 *   di mm cumulati e giorni di pioggia per: MESE corrente, STAGIONE corrente
 *   (dic-feb / mar-mag / giu-ago / set-nov), ANNO corrente.
 *   Il valore centrale del grafico e' la MEDIANA: essendo i baffi dei
 *   percentili, il riferimento coerente e' il p50, non la media. Sulla
 *   pioggia la differenza e' rilevante (distribuzioni molto asimmetriche).
 * - Due modalita':
 *     'parziale'  -> statistiche calcolate fino al giorno corrente
 *                    (es. 9 settembre: mese = 1-9 set, stagione = 1-9 set,
 *                     anno = 1 gen - 9 set, per OGNI anno storico)
 *     'completo'  -> statistiche sull'intero periodo
 *                    (mese intero, stagione intera, anno intero)
 * - Valori correnti (anno in corso) per le righe rossa (mm) e arancione (gg).
 *
 * CONVENZIONI PROGETTO:
 * - Connessioni PDO: $pdo (envelop.php, lettura+scrittura),
 *   $pdo_lettura (envelop_lettura.php, lettura).
 *   Le query di statistica usano la connessione di lettura; la cache usa
 *   $pdo se disponibile, altrimenti la cache viene semplicemente saltata.
 * - datetime_helper.php: get_now() al posto di date() (ambiente test locale)
 * - env_tables_helper.php: table_name() per il suffisso _test (test globale)
 *
 * COSA VA IN CACHE E COSA NO:
 * - La CLIMATOLOGIA (mediana, percentili, media sullo storico) cambia una
 *   volta al giorno -> cache.
 * - I VALORI CORRENTI (mm e giorni dell'anno in corso) cambiano ad ogni
 *   pioggia -> ricalcolati ad ogni richiesta con una query leggera.
 *   Metterli in cache li congelerebbe fino a mezzanotte.
 *
 * STRATEGIA "ECONOMICA":
 * 1. NIENTE percentili in SQL. Una query GROUP BY anno restituisce ~25 coppie
 *    (mm_totali, gg_pioggia) per periodo: i percentili si calcolano in PHP
 *    su 25 numeri. Tre query leggerissime (tabella ~9k righe, indice su data).
 * 2. CACHE GIORNALIERA: il payload viene salvato in pluvio_stat_cache con
 *    chiave (data_rif, modo). I dati cambiano una volta al giorno, quindi
 *    dopo la prima richiesta il costo e' una SELECT su una riga.
 *
 * PREREQUISITI:
 *   - tabella pluvio_giornaliero(data DATE, cumulato_24h DECIMAL, ...)
 *   - indice su data:  ALTER TABLE pluvio_giornaliero ADD INDEX idx_data (data);
 */

require_once __DIR__ . '/../datetime_helper.php';   // helper per ambiente test locale
require_once __DIR__ . '/../env_tables_helper.php'; // helper per ambiente test globale

// ---------------------------------------------------------------------------
// CONFIGURAZIONE
// ---------------------------------------------------------------------------
const PLUVIO_TABELLA_BASE     = 'pluvio_giornaliero';   // passa da table_name()
const PLUVIO_CACHE_BASE       = 'pluvio_stat_cache';    // passa da table_name()
const PLUVIO_COL_DATA         = 'data';
const PLUVIO_COL_MM           = 'cumulato_24h';
const SOGLIA_GIORNO_PIOGGIA   = 1.0;   // mm: un giorno "di pioggia" ha >= 1.0 mm (standard WMO)
const PLUVIO_ANNO_MIN         = 2001;  // primo anno completo della serie
const PLUVIO_CACHE_ATTIVA     = true;

// Versione della struttura del payload. INCREMENTARE ad ogni modifica del
// formato o della logica di calcolo: le righe di cache con versione diversa
// vengono ignorate, altrimenti si continuerebbe a leggere il risultato
// vecchio finche' non cambia il giorno.
const PLUVIO_CACHE_VERSIONE   = 4;

// Colonna che marca i giorni con dettaglio giornaliero affidabile.
// Nella serie storica gli anni 2001-2003 hanno flag 0.
// Mettere stringa vuota per disattivare del tutto la distinzione.
const PLUVIO_COL_FLAG_GG      = 'usa_per_giornaliero';

// COSA RICHIEDE IL DETTAGLIO GIORNALIERO E COSA NO
// -----------------------------------------------
// - GIORNI DI PIOGGIA: contare quanti giorni superano la soglia dipende
//   dall'attribuzione al singolo giorno -> serve SEMPRE flag = 1.
// - MM CUMULATI in modalita' 'completo' (mese intero, stagione intera, anno
//   intero): e' una somma su tutto il periodo, quindi un'attribuzione
//   giornaliera imprecisa non la altera -> si usano ANCHE gli anni con
//   flag 0, guadagnando 2001-2003.
// - MM CUMULATI in modalita' 'parziale' (fino al giorno corrente): la
//   finestra e' tagliata a un giorno preciso -> serve flag = 1.
const PLUVIO_FILTRO_EXTRA     = "AND usa_per_giornaliero = 1";   // usato per i valori correnti

// Copertura minima perche' un anno entri nella climatologia (media, mediana,
// percentili). Un anno con buchi (es. 2013: 36 giorni su 252) conterebbe
// pochissimi giorni di pioggia e schiaccerebbe la distribuzione verso il basso.
const PLUVIO_COPERTURA_MIN    = 0.90;

// Percentili calcolati. Il 50 (mediana) e' il valore centrale del grafico:
// i baffi p25-p75 e p10-p90 sono simmetrici attorno ad esso.
// La media aritmetica resta disponibile come marcatore secondario.
const PLUVIO_PERCENTILI       = [10, 25, 50, 75, 90];


// ---------------------------------------------------------------------------
// CONNESSIONI (convenzione progetto: PDO)
// ---------------------------------------------------------------------------
/** Connessione PDO per le query di lettura. */
function pluvioPdoLettura(): ?PDO {
    if (isset($GLOBALS['pdo_lettura']) && $GLOBALS['pdo_lettura'] instanceof PDO) return $GLOBALS['pdo_lettura'];
    if (isset($GLOBALS['pdo'])         && $GLOBALS['pdo']         instanceof PDO) return $GLOBALS['pdo'];
    return null;
}

/** Connessione PDO con permessi di scrittura (per la cache). Null = niente cache. */
function pluvioPdoScrittura(): ?PDO {
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) return $GLOBALS['pdo'];
    return null;
}


// ---------------------------------------------------------------------------
// FUNZIONE PRINCIPALE
// ---------------------------------------------------------------------------
/**
 * @param string      $modo     'parziale' | 'completo'
 * @param string|null $data_rif 'Y-m-d' giorno di riferimento (default: oggi via get_now)
 * @return array { success, meta, correnti, periodi }
 */
/**
 * ORCHESTRATORE.
 *
 * Separa nettamente le due meta' del risultato, perche' hanno tempi di vita
 * diversi:
 *   - CLIMATOLOGIA (mediana, percentili, media sullo storico): cambia una
 *     volta al giorno -> messa in cache.
 *   - VALORI CORRENTI (mm e giorni dell'anno in corso): cambiano ad ogni
 *     pioggia, anche piu' volte in un'ora -> ricalcolati SEMPRE, con una sola
 *     query leggera sul solo periodo in corso.
 */
function getPluvioStatData($modo = 'parziale', $data_rif = null) {
    $pdo_r = pluvioPdoLettura();
    if (!$pdo_r) {
        return ['success' => false,
                'error'   => 'Connessione PDO non trovata: includere envelop.php o envelop_lettura.php prima dell\'API'];
    }

    $modo = ($modo === 'completo') ? 'completo' : 'parziale';
    try {
        // get_now() rispetta l'ambiente test (datetime_helper.php)
        $oggi = new DateTime($data_rif ?: get_now('Y-m-d'));
    } catch (Exception $e) {
        $oggi = new DateTime(get_now('Y-m-d'));
    }
    $oggi_str = $oggi->format('Y-m-d');

    $ctx = pluvioContesto($oggi);

    // ---- climatologia: dalla cache se c'e', altrimenti calcolo e salvo ----
    $clima = PLUVIO_CACHE_ATTIVA ? pluvioCacheLeggi($oggi_str, $modo) : null;
    if ($clima === null) {
        $clima = pluvioClimatologia($pdo_r, $modo, $oggi_str, $ctx);
        if (empty($clima['success'])) return $clima;
        if (PLUVIO_CACHE_ATTIVA) pluvioCacheScrivi($oggi_str, $modo, $clima);
    }

    // ---- valori correnti: sempre freschi, mai dalla cache ----
    $correnti = pluvioValoriCorrenti($pdo_r, $ctx);
    if (empty($correnti['success'])) return $correnti;

    return [
        'success'            => true,
        'meta'               => array_merge($clima['meta'], [
                                    'generato_il'         => get_now('d/m/Y H:i'),
                                    'correnti_aggiornati' => get_now('H:i'),
                                ]),
        'periodi'            => $clima['periodi'],
        'clima'              => $clima['clima'],
        'correnti'           => $correnti['correnti'],
        'copertura_correnti' => $correnti['copertura'],
    ];
}

/**
 * Contesto temporale condiviso: anno/mese/giorno correnti, stagione in corso,
 * mesi che la compongono e chiave "anno stagione".
 */
function pluvioContesto(DateTime $oggi): array {
    $anno_corr   = (int)$oggi->format('Y');
    $mese_corr   = (int)$oggi->format('n');
    $giorno_corr = (int)$oggi->format('j');

    // ---- definizione stagione corrente ----
    // dic-feb (inverno), mar-mag, giu-ago, set-nov.
    // "Anno stagione": dicembre appartiene alla stagione dell'anno successivo,
    // cosi' dic 2025 + gen/feb 2026 formano un'unica stagione (chiave 2026).
    $stagioni = [
        'inverno'   => [12, 1, 2],
        'primavera' => [3, 4, 5],
        'estate'    => [6, 7, 8],
        'autunno'   => [9, 10, 11],
    ];
    foreach ($stagioni as $nome => $mesi) {
        if (in_array($mese_corr, $mesi, true)) {
            $stag_nome = $nome;
            $stag_mesi = $mesi;
            break;
        }
    }
    // posizione del mese corrente dentro la stagione (0,1,2)
    $stag_pos_corr = array_search($mese_corr, $stag_mesi, true);

    return [
        'oggi'          => $oggi->format('Y-m-d'),
        'anno_corr'     => $anno_corr,
        'mese_corr'     => $mese_corr,
        'giorno_corr'   => $giorno_corr,
        'stag_nome'     => $stag_nome,
        'stag_mesi'     => $stag_mesi,
        'stag_pos_corr' => $stag_pos_corr,
        // dicembre appartiene alla stagione dell'anno successivo
        'stag_key_corr' => $anno_corr + ($mese_corr === 12 ? 1 : 0),
    ];
}


/**
 * CLIMATOLOGIA: mediana, percentili e media sullo storico.
 * E' la parte costosa (tre GROUP BY sull'intera serie) ma cambia una volta
 * al giorno: e' questa che viene messa in cache.
 */
function pluvioClimatologia(PDO $pdo_r, string $modo, string $oggi_str, array $ctx): array {
    $anno_corr     = $ctx['anno_corr'];
    $mese_corr     = $ctx['mese_corr'];
    $giorno_corr   = $ctx['giorno_corr'];
    $stag_nome     = $ctx['stag_nome'];
    $stag_mesi     = $ctx['stag_mesi'];
    $stag_pos_corr = $ctx['stag_pos_corr'];
    $stag_key_corr = $ctx['stag_key_corr'];

    $col_d  = PLUVIO_COL_DATA;
    $col_mm = PLUVIO_COL_MM;
    $tab    = table_name(PLUVIO_TABELLA_BASE);   // rispetta USE_TEST_MODE
    $soglia = SOGLIA_GIORNO_PIOGGIA;
    $extra  = PLUVIO_FILTRO_EXTRA;
    $anno_min = PLUVIO_ANNO_MIN;

    // Espressione SQL "anno stagione" (solo per la stagione)
    $expr_anno_stag = "YEAR($col_d) + (MONTH($col_d) = 12)";

    // ------------------------------------------------------------------
    // PREDICATI PERIODO (parziale vs completo)
    // Nota 29 febbraio: il confronto e' fatto su (mese, giorno), quindi
    // negli anni non bisestili il 29/2 semplicemente non esiste e la media
    // resta coerente.
    // ------------------------------------------------------------------
    if ($modo === 'parziale') {
        // MESE: giorni 1..giorno_corr del mese corrente
        $pred_mese = "MONTH($col_d) = $mese_corr AND DAY($col_d) <= $giorno_corr";

        // STAGIONE: mesi precedenti della stagione per intero + mese corrente
        // fino al giorno corrente. FIELD() da' la posizione (1..3) del mese.
        $lista_mesi = implode(',', $stag_mesi);
        $pred_stag  = "MONTH($col_d) IN ($lista_mesi) AND ("
                    . "FIELD(MONTH($col_d), $lista_mesi) < " . ($stag_pos_corr + 1)
                    . " OR (MONTH($col_d) = $mese_corr AND DAY($col_d) <= $giorno_corr))";

        // ANNO: dal 1 gennaio alla coppia (mese, giorno) corrente
        $pred_anno = "(MONTH($col_d) < $mese_corr"
                   . " OR (MONTH($col_d) = $mese_corr AND DAY($col_d) <= $giorno_corr))";
    } else {
        $pred_mese  = "MONTH($col_d) = $mese_corr";
        $lista_mesi = implode(',', $stag_mesi);
        $pred_stag  = "MONTH($col_d) IN ($lista_mesi)";
        $pred_anno  = "1=1";
    }

    // ------------------------------------------------------------------
    // 3 QUERY GROUP BY ANNO (una riga per anno: ~25 righe ciascuna).
    //
    // Ogni riga porta DUE aggregazioni parallele:
    //   *_tutti -> su tutte le righe        (per i mm in modalita' completo)
    //   *_gg    -> solo su flag = 1         (per i giorni di pioggia, e per i
    //                                        mm in modalita' parziale)
    // Cosi' una sola scansione serve entrambe le basi, senza query aggiuntive.
    // ------------------------------------------------------------------
    $flag = PLUVIO_COL_FLAG_GG;
    $agg  = $flag !== ''
          ? "ROUND(SUM($col_mm),1)                                          AS mm_tutti,
             COUNT(*)                                                       AS n_tutti,
             ROUND(SUM(CASE WHEN $flag = 1 THEN $col_mm ELSE 0 END),1)      AS mm_gg,
             SUM(CASE WHEN $flag = 1 AND $col_mm >= $soglia THEN 1 ELSE 0 END) AS gg,
             SUM(CASE WHEN $flag = 1 THEN 1 ELSE 0 END)                     AS n_gg"
          // Senza colonna flag le due basi coincidono
          : "ROUND(SUM($col_mm),1)                     AS mm_tutti,
             COUNT(*)                                  AS n_tutti,
             ROUND(SUM($col_mm),1)                     AS mm_gg,
             SUM(CASE WHEN $col_mm >= $soglia THEN 1 ELSE 0 END) AS gg,
             COUNT(*)                                  AS n_gg";

    $sql_mese = "SELECT YEAR($col_d) AS k, $agg
                 FROM $tab
                 WHERE $pred_mese AND YEAR($col_d) BETWEEN $anno_min AND $anno_corr
                 GROUP BY k ORDER BY k";

    $sql_stag = "SELECT $expr_anno_stag AS k, $agg
                 FROM $tab
                 WHERE $pred_stag AND $expr_anno_stag BETWEEN $anno_min AND $stag_key_corr
                 GROUP BY k ORDER BY k";

    $sql_anno = "SELECT YEAR($col_d) AS k, $agg
                 FROM $tab
                 WHERE $pred_anno AND YEAR($col_d) BETWEEN $anno_min AND $anno_corr
                 GROUP BY k ORDER BY k";

    try {
        $serie_mese = pluvioQuerySerie($pdo_r, $sql_mese);
        $serie_stag = pluvioQuerySerie($pdo_r, $sql_stag);
        $serie_anno = pluvioQuerySerie($pdo_r, $sql_anno);
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Errore query pluvio: ' . $e->getMessage()];
    }

    // L'anno in corso NON entra nella climatologia (media/percentili):
    // in modalita' completo sarebbe un periodo monco, in modalita' parziale
    // e' il valore stesso che stiamo confrontando.
    unset($serie_mese[$anno_corr], $serie_anno[$anno_corr], $serie_stag[$stag_key_corr]);

    // ------------------------------------------------------------------
    // DUE SERIE DISTINTE PER OGNI PERIODO
    //
    // I mm sul periodo intero sono una somma: reggono anche gli anni senza
    // dettaglio giornaliero affidabile (2001-2003), che vengono cosi'
    // recuperati. I giorni di pioggia no, perche' contare i superamenti di
    // soglia dipende proprio dall'attribuzione al singolo giorno.
    //
    // FILTRO COPERTURA: in entrambi i casi restano fuori gli anni con troppi
    // giorni mancanti, che conterebbero pochi giorni di pioggia e poca acqua
    // apparendo come anni secchi reali.
    // ------------------------------------------------------------------
    $usa_tutti = ($modo === 'completo');   // i mm possono usare la base ampia

    list($mm_mese, $gg_mese, $escl_mese) = pluvioSeparaSerie($serie_mese, 'mese',     $modo, $usa_tutti, $mese_corr, $giorno_corr, $stag_mesi);
    list($mm_stag, $gg_stag, $escl_stag) = pluvioSeparaSerie($serie_stag, 'stagione', $modo, $usa_tutti, $mese_corr, $giorno_corr, $stag_mesi);
    list($mm_anno, $gg_anno, $escl_anno) = pluvioSeparaSerie($serie_anno, 'anno',     $modo, $usa_tutti, $mese_corr, $giorno_corr, $stag_mesi);

    // ------------------------------------------------------------------
    // STATISTICHE (media + percentili) calcolate in PHP su ~25 valori
    // ------------------------------------------------------------------
    $mesi_it = ['','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
    $stag_label = ['inverno'=>'Inverno (dic-feb)','primavera'=>'Primavera (mar-mag)',
                   'estate'=>'Estate (giu-ago)','autunno'=>'Autunno (set-nov)'];

    $periodi = [
        pluvioComponiPeriodo('mese',     $mesi_it[$mese_corr],       $mm_mese, $gg_mese, $escl_mese),
        pluvioComponiPeriodo('stagione', $stag_label[$stag_nome],    $mm_stag, $gg_stag, $escl_stag),
        pluvioComponiPeriodo('anno',     'Anno',                     $mm_anno, $gg_anno, $escl_anno),
    ];

    // Periodo di riferimento complessivo della climatologia: dal primo anno
    // effettivamente usato (dopo i filtri) all'ultimo, con il conteggio.
    $tutti = array_merge(array_keys($mm_mese), array_keys($mm_stag), array_keys($mm_anno),
                         array_keys($gg_mese), array_keys($gg_stag), array_keys($gg_anno));
    $clima_globale = [
        'anno_primo' => $tutti ? min($tutti) : null,
        'anno_ultimo'=> $tutti ? max($tutti) : null,
        'label'      => $tutti ? (min($tutti) . "\u{2013}" . max($tutti)) : 'n/d',
    ];

    return [
        'success'  => true,
        'meta'     => [
            'oggi'          => $oggi_str,
            'modo'          => $modo,
            'soglia_gg'     => $soglia,
            'copertura_min' => PLUVIO_COPERTURA_MIN,
            'percentili'    => PLUVIO_PERCENTILI,
            'anno_min_conf' => PLUVIO_ANNO_MIN,
        ],
        'periodi'  => $periodi,
        'clima'    => $clima_globale,
    ];
}


/**
 * Etichetta del periodo climatologico di una singola serie: primo e ultimo
 * anno effettivamente usati e quanti sono. Per la stagione invernale l'anno
 * e' la "chiave stagione", quindi il primo dicembre utile e' quello prima.
 */
function pluvioEtichettaClima(array $serie, string $periodo): array {
    if (!$serie) return ['anno_primo' => null, 'anno_ultimo' => null, 'n' => 0, 'label' => 'n/d'];
    $anni = array_keys($serie);
    $pri  = min($anni);
    $ult  = max($anni);
    return [
        'anno_primo'  => $pri,
        'anno_ultimo' => $ult,
        'n'           => count($anni),
        'label'       => $pri . "\u{2013}" . $ult,
    ];
}


// ---------------------------------------------------------------------------
// HELPER
// ---------------------------------------------------------------------------

/** Esegue la query e indicizza le righe per chiave anno. */
function pluvioQuerySerie(PDO $pdo, string $sql): array {
    $out = [];
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int)$r['k']] = [
            'mm_tutti' => (float)$r['mm_tutti'],  // somma su tutte le righe
            'n_tutti'  => (int)$r['n_tutti'],     // giorni presenti, tutte le righe
            'mm_gg'    => (float)$r['mm_gg'],     // somma sulle sole righe flag=1
            'gg'       => (int)$r['gg'],          // giorni di pioggia (solo flag=1)
            'n_gg'     => (int)$r['n_gg'],        // giorni presenti con flag=1
        ];
    }
    return $out;
}

/** Giorni di calendario di un mese. */
function pluvioGiorniInMese(int $anno, int $mese): int {
    return (int)(new DateTime(sprintf('%04d-%02d-01', $anno, $mese)))->format('t');
}

/**
 * Giorni ATTESI per un anno/periodo, cioe' quanti giorni dovrebbe contenere
 * la finestra se la serie fosse completa. Serve a misurare la copertura.
 *
 * @param string $periodo 'mese' | 'stagione' | 'anno'
 * @param int    $chiave  anno (per la stagione: "anno stagione", con dicembre
 *                        appartenente all'anno precedente alla chiave)
 */
function pluvioGiorniAttesi(string $periodo, int $chiave, string $modo,
                            int $mese_corr, int $giorno_corr, array $stag_mesi): int {
    if ($periodo === 'mese') {
        $ngg = pluvioGiorniInMese($chiave, $mese_corr);
        return ($modo === 'completo') ? $ngg : min($giorno_corr, $ngg);
    }

    if ($periodo === 'stagione') {
        $inverno  = in_array(12, $stag_mesi, true);
        $pos_corr = array_search($mese_corr, $stag_mesi, true);
        $tot = 0;
        foreach ($stag_mesi as $pos => $m) {
            // Nell'inverno dicembre sta nell'anno precedente alla chiave
            $anno_m = ($inverno && $m === 12) ? $chiave - 1 : $chiave;
            $ngg    = pluvioGiorniInMese($anno_m, $m);
            if ($modo === 'completo')      { $tot += $ngg; continue; }
            if ($pos <  $pos_corr)           $tot += $ngg;
            elseif ($pos === $pos_corr)      $tot += min($giorno_corr, $ngg);
        }
        return $tot;
    }

    // anno
    if ($modo === 'completo') {
        return (int)(new DateTime("$chiave-12-31"))->format('z') + 1;
    }
    $g = min($giorno_corr, pluvioGiorniInMese($chiave, $mese_corr));
    return (int)(new DateTime(sprintf('%04d-%02d-%02d', $chiave, $mese_corr, $g)))->format('z') + 1;
}

/**
 * VALORI CORRENTI: mm e giorni di pioggia del mese, della stagione e
 * dell'anno in corso. MAI messi in cache — cambiano ad ogni pioggia.
 *
 * Costo: UNA query su una finestra di al massimo ~15 mesi (l'anno in corso
 * piu' l'eventuale dicembre precedente per la stagione invernale), quindi
 * poche centinaia di righe. Le tre finestre sono ricavate nello stesso
 * passaggio con SUM(CASE WHEN ...), senza scansioni multiple.
 */
function pluvioValoriCorrenti(PDO $pdo_r, array $ctx): array {
    $col_d  = PLUVIO_COL_DATA;
    $col_mm = PLUVIO_COL_MM;
    $tab    = table_name(PLUVIO_TABELLA_BASE);
    $soglia = SOGLIA_GIORNO_PIOGGIA;
    $extra  = PLUVIO_FILTRO_EXTRA;

    $oggi        = $ctx['oggi'];
    $anno_corr   = $ctx['anno_corr'];
    $mese_corr   = $ctx['mese_corr'];
    $giorno_corr = $ctx['giorno_corr'];
    $stag_mesi   = $ctx['stag_mesi'];

    // Inizio del mese in corso
    $ini_mese = sprintf('%04d-%02d-01', $anno_corr, $mese_corr);

    // Inizio della stagione in corso. Per l'inverno (dic-gen-feb) il primo
    // mese e' dicembre, che a gennaio/febbraio sta nell'anno precedente.
    $primo_mese_stag = $stag_mesi[0];
    $anno_ini_stag   = ($primo_mese_stag === 12 && $mese_corr !== 12)
                     ? $anno_corr - 1
                     : $anno_corr;
    $ini_stag = sprintf('%04d-%02d-01', $anno_ini_stag, $primo_mese_stag);

    // Inizio dell'anno civile in corso
    $ini_anno = sprintf('%04d-01-01', $anno_corr);

    // La finestra da leggere parte dalla piu' remota delle tre
    $ini_finestra = min($ini_mese, $ini_stag, $ini_anno);

    $sql = "SELECT
              ROUND(SUM(CASE WHEN $col_d >= :im THEN $col_mm ELSE 0 END),1)               AS mm_mese,
              SUM(CASE WHEN $col_d >= :im2 AND $col_mm >= $soglia THEN 1 ELSE 0 END)      AS gg_mese,
              SUM(CASE WHEN $col_d >= :im3 THEN 1 ELSE 0 END)                             AS n_mese,
              ROUND(SUM(CASE WHEN $col_d >= :is THEN $col_mm ELSE 0 END),1)               AS mm_stag,
              SUM(CASE WHEN $col_d >= :is2 AND $col_mm >= $soglia THEN 1 ELSE 0 END)      AS gg_stag,
              SUM(CASE WHEN $col_d >= :is3 THEN 1 ELSE 0 END)                             AS n_stag,
              ROUND(SUM(CASE WHEN $col_d >= :ia THEN $col_mm ELSE 0 END),1)               AS mm_anno,
              SUM(CASE WHEN $col_d >= :ia2 AND $col_mm >= $soglia THEN 1 ELSE 0 END)      AS gg_anno,
              SUM(CASE WHEN $col_d >= :ia3 THEN 1 ELSE 0 END)                             AS n_anno
            FROM $tab
            WHERE $col_d >= :inizio AND $col_d <= :oggi $extra";

    try {
        $stmt = $pdo_r->prepare($sql);
        $stmt->execute([
            ':im' => $ini_mese, ':im2' => $ini_mese, ':im3' => $ini_mese,
            ':is' => $ini_stag, ':is2' => $ini_stag, ':is3' => $ini_stag,
            ':ia' => $ini_anno, ':ia2' => $ini_anno, ':ia3' => $ini_anno,
            ':inizio' => $ini_finestra, ':oggi' => $oggi,
        ]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Errore query correnti: ' . $e->getMessage()];
    }

    $corr = [
        'mese'     => ['mm' => (float)($r['mm_mese'] ?? 0), 'gg' => (int)($r['gg_mese'] ?? 0), 'n_gg' => (int)($r['n_mese'] ?? 0)],
        'stagione' => ['mm' => (float)($r['mm_stag'] ?? 0), 'gg' => (int)($r['gg_stag'] ?? 0), 'n_gg' => (int)($r['n_stag'] ?? 0)],
        'anno'     => ['mm' => (float)($r['mm_anno'] ?? 0), 'gg' => (int)($r['gg_anno'] ?? 0), 'n_gg' => (int)($r['n_anno'] ?? 0)],
    ];

    // Copertura: sempre valutata sulla finestra parziale, perche' il periodo
    // in corso e' per definizione ancora incompleto.
    $cop = [
        'mese'     => pluvioCoperturaCorrente($corr['mese'],     'mese',     $anno_corr,           $mese_corr, $giorno_corr, $stag_mesi),
        'stagione' => pluvioCoperturaCorrente($corr['stagione'], 'stagione', $ctx['stag_key_corr'], $mese_corr, $giorno_corr, $stag_mesi),
        'anno'     => pluvioCoperturaCorrente($corr['anno'],     'anno',     $anno_corr,           $mese_corr, $giorno_corr, $stag_mesi),
    ];

    return ['success' => true, 'correnti' => $corr, 'copertura' => $cop];
}


/**
 * Da una serie grezza ricava le DUE serie usate dal grafico:
 *   - serie mm: valore 'mm_tutti' se la modalita' lo consente (periodo intero),
 *               altrimenti 'mm_gg' (solo righe con dettaglio affidabile)
 *   - serie gg: sempre 'gg', che richiede il dettaglio giornaliero
 * Ciascuna con il proprio controllo di copertura, perche' il numero di giorni
 * disponibili e' diverso tra le due basi.
 *
 * @return array [serie_mm, serie_gg, esclusi]
 */
function pluvioSeparaSerie(array $serie, string $periodo, string $modo, bool $usa_tutti,
                           int $mese_corr, int $giorno_corr, array $stag_mesi): array {
    $out_mm = [];
    $out_gg = [];
    $esclusi = [];

    foreach ($serie as $k => $v) {
        $attesi = pluvioGiorniAttesi($periodo, $k, $modo, $mese_corr, $giorno_corr, $stag_mesi);
        if ($attesi <= 0) continue;

        // --- mm ---
        $val_mm = $usa_tutti ? $v['mm_tutti'] : $v['mm_gg'];
        $pres_mm = $usa_tutti ? $v['n_tutti'] : $v['n_gg'];
        $cop_mm = $pres_mm / $attesi;
        if ($cop_mm >= PLUVIO_COPERTURA_MIN) {
            $out_mm[$k] = ['v' => $val_mm];
        }

        // --- giorni di pioggia: sempre sulla base con dettaglio affidabile ---
        $cop_gg = $v['n_gg'] / $attesi;
        if ($cop_gg >= PLUVIO_COPERTURA_MIN) {
            $out_gg[$k] = ['v' => $v['gg']];
        }

        // Un anno e' "escluso" se non entra in nessuna delle due
        if ($cop_mm < PLUVIO_COPERTURA_MIN && $cop_gg < PLUVIO_COPERTURA_MIN) {
            $esclusi[] = ['anno' => $k, 'copertura' => round(max($cop_mm, $cop_gg), 3)];
        }
    }

    return [$out_mm, $out_gg, $esclusi];
}

/** Costruisce la voce di un periodo a partire dalle due serie separate. */
function pluvioComponiPeriodo(string $id, string $label, array $serie_mm, array $serie_gg, array $esclusi): array {
    $stat_mm = pluvioStatistiche(array_column($serie_mm, 'v'));
    $stat_gg = pluvioStatistiche(array_column($serie_gg, 'v'));
    // Ogni metrica dichiara il proprio periodo di riferimento: puo' differire,
    // perche' i mm sul periodo intero recuperano anni che i giorni non hanno.
    $stat_mm['clima'] = pluvioEtichettaClima($serie_mm, $id);
    $stat_gg['clima'] = pluvioEtichettaClima($serie_gg, $id);

    return [
        'id'      => $id,
        'label'   => $label,
        'mm'      => $stat_mm,
        'gg'      => $stat_gg,
        'n_anni'  => max(count($serie_mm), count($serie_gg)),
        'esclusi' => $esclusi,
        'clima'   => pluvioEtichettaClima($serie_mm + $serie_gg, $id),
    ];
}

/** Copertura (0..1) del periodo corrente, sempre valutata sulla finestra parziale. */
function pluvioCoperturaCorrente(array $corr, string $periodo, int $chiave,
                                 int $mese_corr, int $giorno_corr, array $stag_mesi): float {
    $attesi = pluvioGiorniAttesi($periodo, $chiave, 'parziale', $mese_corr, $giorno_corr, $stag_mesi);
    $pres   = $corr['n_gg'] ?? 0;
    return $attesi > 0 ? round($pres / $attesi, 3) : 0.0;
}

/** Media + percentili (interpolazione lineare) su un piccolo array. */
function pluvioStatistiche(array $valori) {
    $valori = array_values(array_map('floatval', $valori));
    $n = count($valori);
    if ($n === 0) {
        $vuoto = ['media' => null, 'n' => 0];
        foreach (PLUVIO_PERCENTILI as $p) $vuoto['p' . $p] = null;
        return $vuoto;
    }
    sort($valori);
    $out = [
        'media' => round(array_sum($valori) / $n, 1),
        'n'     => $n,
    ];
    foreach (PLUVIO_PERCENTILI as $p) {
        $pos = ($p / 100) * ($n - 1);
        $lo  = (int)floor($pos);
        $hi  = (int)ceil($pos);
        $val = ($lo === $hi)
             ? $valori[$lo]
             : $valori[$lo] + ($pos - $lo) * ($valori[$hi] - $valori[$lo]);
        $out['p' . $p] = round($val, 1);
    }
    // Alias esplicito: la mediana e' il valore centrale usato dal grafico
    $out['mediana'] = $out['p50'] ?? null;
    return $out;
}

// ---------------------------------------------------------------------------
// CACHE (tabella creata al volo la prima volta; richiede $pdo con permessi
// di scrittura, altrimenti viene saltata in silenzio: mai bloccante)
// ---------------------------------------------------------------------------
function pluvioCacheTabella(PDO $pdo_w): void {
    static $fatta = false;
    if ($fatta) return;
    $tab = table_name(PLUVIO_CACHE_BASE);
    $pdo_w->exec("CREATE TABLE IF NOT EXISTS $tab (
                    data_rif  DATE        NOT NULL,
                    modo      VARCHAR(24) NOT NULL,
                    payload   MEDIUMTEXT  NOT NULL,
                    creato_il TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (data_rif, modo)
                  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $fatta = true;
}

/** Chiave di cache: include la versione, cosi' i payload vecchi decadono da soli. */
function pluvioCacheChiave(string $modo): string {
    return $modo . '|v' . PLUVIO_CACHE_VERSIONE;
}

function pluvioCacheLeggi(string $data_rif, string $modo): ?array {
    $pdo_r = pluvioPdoLettura();
    if (!$pdo_r) return null;
    $modo = pluvioCacheChiave($modo);
    try {
        $tab  = table_name(PLUVIO_CACHE_BASE);
        $stmt = $pdo_r->prepare("SELECT payload FROM $tab
                                 WHERE data_rif = :d AND modo = :m LIMIT 1");
        $stmt->execute([':d' => $data_rif, ':m' => $modo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $dec = json_decode($row['payload'], true);
        return (is_array($dec) && !empty($dec['success'])) ? $dec : null;
    } catch (PDOException $e) {
        // tabella non ancora esistente o permessi: nessun problema, si calcola
        return null;
    }
}

function pluvioCacheScrivi(string $data_rif, string $modo, array $payload): void {
    $pdo_w = pluvioPdoScrittura();
    if (!$pdo_w) return;   // solo envelop_lettura incluso: niente cache, nessun errore
    $modo = pluvioCacheChiave($modo);
    try {
        pluvioCacheTabella($pdo_w);
        $tab  = table_name(PLUVIO_CACHE_BASE);
        $stmt = $pdo_w->prepare("REPLACE INTO $tab (data_rif, modo, payload)
                                 VALUES (:d, :m, :p)");
        $stmt->execute([':d' => $data_rif, ':m' => $modo,
                        ':p' => json_encode($payload, JSON_UNESCAPED_UNICODE)]);
        // Pulizia leggera: tiene solo gli ultimi 7 giorni di cache
        $del = $pdo_w->prepare("DELETE FROM $tab
                                WHERE data_rif < DATE_SUB(:d, INTERVAL 7 DAY)");
        $del->execute([':d' => $data_rif]);
    } catch (PDOException $e) {
        // cache best-effort: un errore qui non deve mai bloccare la pagina
    }
}