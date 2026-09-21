<?php
/**
 * ============================================================================
 * SEQUENZE SECCHE E PIOVOSE - DATA LAYER  api/api_sequenze_data.php
 * ============================================================================
 *
 * RESPONSABILITA':
 * - CLIMATOLOGIA (in cache, cambia una volta al giorno):
 *     per ogni MESE DI INIZIO (1..12) e per ciascun tipo (secca / piovosa),
 *     la distribuzione delle durate: n, mediana, p75, p90, massimo storico.
 *     La sequenza viene attribuita al mese in cui INIZIA: e' il regime di
 *     quel mese che l'ha generata.
 * - SEQUENZA IN CORSO (mai in cache, cambia ogni giorno):
 *     tipo, durata, data e mese di inizio, posizione rispetto alla
 *     climatologia del proprio mese, e statistica CONDIZIONATA: fra le
 *     sequenze storiche che hanno raggiunto la stessa durata, quanto sono
 *     poi arrivate a durare.
 * - ULTIMA SEQUENZA CONCLUSA per ciascun tipo (anch'essa fresca):
 *     e' la sequenza chiusa piu' recente di quel tipo. Per il tipo in corso
 *     e' quella che lo precede a parita' di tipo; per l'altro tipo e' quella
 *     interrotta proprio dall'inizio della sequenza in corso.
 *     NON puo' stare in cache: se oggi la pioggia supera la soglia, la
 *     sequenza secca si chiude nel corso della giornata e l'ultima conclusa
 *     cambia all'istante.
 *
 * PERCHE' LA CONDIZIONATA:
 *   La sequenza in corso e' "censurata": non sappiamo quanto durera', solo
 *   che ha gia' raggiunto N giorni. Confrontarla con TUTTE le sequenze
 *   concluse e' distorto, perche' quella distribuzione contiene anche le
 *   sequenze piu' corte di N, ormai escluse dai fatti. Il confronto corretto
 *   guarda solo alle sequenze che sono arrivate a N.
 *
 * DEFINIZIONI (ETCCDI):
 * - giorno piovoso: RR >= 1.0 mm    (SEQ_SOGLIA)
 * - sequenza secca:   giorni consecutivi con RR <  soglia
 * - sequenza piovosa: giorni consecutivi con RR >= soglia
 *
 * REGOLE DI INTEGRITA':
 * - Una sequenza si interrompe se manca un giorno: il confronto e' sempre
 *   fra date CONSECUTIVE reali, mai fra righe adiacenti in tabella.
 * - Servono i giorni con dettaglio affidabile: il conteggio dipende
 *   dall'attribuzione al singolo giorno, quindi flag = 1 SEMPRE (a
 *   differenza dei mm, che sul periodo intero reggono anche flag = 0).
 * - Gli anni con copertura insufficiente sono esclusi in blocco: con molti
 *   buchi si producono tante sequenze corte spurie.
 *
 * CONVENZIONI PROGETTO:
 * - PDO: $pdo (envelop.php) / $pdo_lettura (envelop_lettura.php)
 * - datetime_helper.php: get_now() al posto di date()
 * - env_tables_helper.php: table_name() per il suffisso _test
 */

require_once __DIR__ . '/../datetime_helper.php';
require_once __DIR__ . '/../env_tables_helper.php';

// ---------------------------------------------------------------------------
// CONFIGURAZIONE
// ---------------------------------------------------------------------------
const SEQ_TABELLA_BASE   = 'pluvio_giornaliero';
const SEQ_CACHE_BASE     = 'pluvio_stat_cache';   // stessa tabella cache delle altre API
const SEQ_COL_DATA       = 'data';
const SEQ_COL_MM         = 'cumulato_24h';
const SEQ_COL_FLAG       = 'usa_per_giornaliero';
const SEQ_SOGLIA         = 1.0;    // mm: giorno piovoso (standard WMO/ETCCDI)
const SEQ_ANNO_MIN       = 2001;
const SEQ_COPERTURA_MIN  = 0.90;
const SEQ_MIN_SEQUENZE   = 8;      // sotto questa soglia il mese non ha percentili
const SEQ_CACHE_ATTIVA   = true;
const SEQ_CACHE_VERSIONE = 2;      // INCREMENTARE ad ogni modifica del payload

const SEQ_MESI_BREVI = ['', 'gen', 'feb', 'mar', 'apr', 'mag', 'giu',
                        'lug', 'ago', 'set', 'ott', 'nov', 'dic'];


// ---------------------------------------------------------------------------
// CONNESSIONI
// ---------------------------------------------------------------------------
function seqPdoLettura(): ?PDO {
    if (isset($GLOBALS['pdo_lettura']) && $GLOBALS['pdo_lettura'] instanceof PDO) return $GLOBALS['pdo_lettura'];
    if (isset($GLOBALS['pdo'])         && $GLOBALS['pdo']         instanceof PDO) return $GLOBALS['pdo'];
    return null;
}
function seqPdoScrittura(): ?PDO {
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) return $GLOBALS['pdo'];
    return null;
}


// ---------------------------------------------------------------------------
// FUNZIONE PRINCIPALE
// ---------------------------------------------------------------------------
/**
 * @param string|null $data_rif 'Y-m-d' (default: oggi via get_now)
 * @return array { success, meta, mesi:{secca:[12], piovosa:[12]}, corrente, ultime }
 */
function getSequenzeData($data_rif = null) {
    $pdo_r = seqPdoLettura();
    if (!$pdo_r) {
        return ['success' => false,
                'error'   => 'Connessione PDO non trovata: includere envelop.php o envelop_lettura.php prima dell\'API'];
    }

    try {
        $oggi = new DateTime($data_rif ?: get_now('Y-m-d'));
    } catch (Exception $e) {
        $oggi = new DateTime(get_now('Y-m-d'));
    }
    $oggi_str = $oggi->format('Y-m-d');

    // ---- climatologia: cache giornaliera ----
    $clima = SEQ_CACHE_ATTIVA ? seqCacheLeggi($oggi_str) : null;
    if ($clima === null) {
        $clima = seqClimatologia($pdo_r, $oggi_str);
        if (empty($clima['success'])) return $clima;
        if (SEQ_CACHE_ATTIVA) seqCacheScrivi($oggi_str, $clima);
    }

    // ---- sequenza in corso: sempre fresca ----
    $corr = seqCorrente($pdo_r, $oggi_str, $clima['durate']);
    if (empty($corr['success'])) return $corr;

    return [
        'success'  => true,
        'meta'     => array_merge($clima['meta'], [
            'generato_il'         => get_now('d/m/Y H:i'),
            'correnti_aggiornati' => get_now('H:i'),
        ]),
        'mesi'     => $clima['mesi'],
        'corrente' => $corr['corrente'],
        'ultime'   => $corr['ultime'],
    ];
}


// ---------------------------------------------------------------------------
// CLIMATOLOGIA
// ---------------------------------------------------------------------------
/**
 * Una sola SELECT sull'intera serie (~9-10k righe, tre colonne), poi tutto
 * in PHP. Costa come le altre API e, grazie alla cache, gira una volta al
 * giorno.
 */
function seqClimatologia(PDO $pdo_r, string $oggi_str): array {
    $tab = table_name(SEQ_TABELLA_BASE);
    $cd  = SEQ_COL_DATA; $cm = SEQ_COL_MM; $cf = SEQ_COL_FLAG;

    try {
        $stmt = $pdo_r->prepare("SELECT $cd AS d, $cm AS mm
                                 FROM $tab
                                 WHERE $cd >= :ini AND $cd <= :oggi AND $cf = 1
                                 ORDER BY $cd");
        $stmt->execute([':ini' => SEQ_ANNO_MIN . '-01-01', ':oggi' => $oggi_str]);
        $righe = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Errore query sequenze: ' . $e->getMessage()];
    }
    if (!$righe) {
        return ['success' => false, 'error' => 'Nessun dato giornaliero disponibile'];
    }

    // ---- copertura per anno: un anno con buchi genera sequenze spurie ----
    $presenti = [];
    foreach ($righe as $r) {
        $y = (int)substr((string)$r['d'], 0, 4);
        $presenti[$y] = ($presenti[$y] ?? 0) + 1;
    }
    $anni_ok = [];
    $esclusi = [];
    foreach ($presenti as $anno => $n) {
        $attesi = (int)(new DateTime("$anno-12-31"))->format('z') + 1;
        // l'anno in corso e' parziale per definizione: si misura sui giorni trascorsi
        if ($anno === (int)substr($oggi_str, 0, 4)) {
            $attesi = (int)(new DateTime($oggi_str))->format('z') + 1;
        }
        $cop = $attesi > 0 ? $n / $attesi : 0;
        if ($cop >= SEQ_COPERTURA_MIN) $anni_ok[$anno] = true;
        else $esclusi[] = ['anno' => $anno, 'copertura' => round($cop, 3)];
    }

    // ---- scansione: estrazione delle sequenze ----
    $durate = ['secca' => [], 'piovosa' => []];   // tipo => mese_inizio => [durate]
    // Record del mese: durata massima e data in cui quella sequenza e' iniziata.
    // Serve a mostrare "massimo storico: 7 gg (set 2011)" invece del solo numero.
    $record = ['secca' => [], 'piovosa' => []];
    for ($m = 1; $m <= 12; $m++) {
        $durate['secca'][$m] = []; $durate['piovosa'][$m] = [];
        $record['secca'][$m] = null; $record['piovosa'][$m] = null;
    }

    $tipo_cur = null; $len = 0; $mese_ini = null; $data_ini = null; $prec_ts = null;
    $ultimo_ds = null;

    $chiudi = function () use (&$tipo_cur, &$len, &$mese_ini, &$data_ini, &$ultimo_ds, &$durate, &$record) {
        if ($tipo_cur !== null && $len > 0 && $mese_ini !== null) {
            $durate[$tipo_cur][$mese_ini][] = $len;
            $prec = $record[$tipo_cur][$mese_ini];
            // A parita' di durata tiene il primo osservato: il record piu' antico
            if ($prec === null || $len > $prec['len']) {
                $record[$tipo_cur][$mese_ini] = [
                    'len'    => $len,
                    'inizio' => $data_ini,
                    'fine'   => $ultimo_ds,
                ];
            }
        }
        $tipo_cur = null; $len = 0; $mese_ini = null; $data_ini = null;
    };

    foreach ($righe as $r) {
        $ds = substr((string)$r['d'], 0, 10);
        $anno = (int)substr($ds, 0, 4);
        if (!isset($anni_ok[$anno])) { $chiudi(); $prec_ts = null; continue; }

        $ts = strtotime($ds);
        // Giorno non consecutivo al precedente: la sequenza si interrompe.
        if ($prec_ts !== null && ($ts - $prec_ts) !== 86400) $chiudi();

        $tipo = ((float)$r['mm'] >= SEQ_SOGLIA) ? 'piovosa' : 'secca';
        if ($tipo === $tipo_cur) {
            $len++;
        } else {
            $chiudi();
            $tipo_cur = $tipo; $len = 1;
            $mese_ini = (int)substr($ds, 5, 2);
            $data_ini = $ds;
        }
        $ultimo_ds = $ds;   // ultimo giorno incluso nella sequenza aperta
        $prec_ts = $ts;
    }
    // L'ultima sequenza NON viene chiusa: e' quella in corso, la calcola
    // seqCorrente() sui dati freschi. Chiuderla qui la falserebbe, perche'
    // e' troncata dalla data odierna e non dalla pioggia.

    // ---- statistiche per mese ----
    $mesi = ['secca' => [], 'piovosa' => []];
    $tot = 0;
    foreach (['secca', 'piovosa'] as $tipo) {
        for ($m = 1; $m <= 12; $m++) {
            $v = $durate[$tipo][$m];
            $tot += count($v);
            $mesi[$tipo][] = seqStatMese($m, $v, $record[$tipo][$m]);
        }
    }

    $anni = array_keys($anni_ok);
    sort($anni);

    return [
        'success' => true,
        'meta'    => [
            'oggi'          => $oggi_str,
            'soglia'        => SEQ_SOGLIA,
            'copertura_min' => SEQ_COPERTURA_MIN,
            'min_sequenze'  => SEQ_MIN_SEQUENZE,
            'n_sequenze'    => $tot,
            'clima_label'   => $anni ? (min($anni) . "\u{2013}" . max($anni)) : 'n/d',
            'n_anni'        => count($anni),
            'esclusi'       => $esclusi,
        ],
        'mesi'    => $mesi,
        'durate'  => $durate,   // serve alla statistica condizionata, non va al display
    ];
}

/** Statistiche di un mese: n, mediana, p75, p90, massimo e data del record. */
function seqStatMese(int $mese, array $v, ?array $record = null): array {
    $n = count($v);
    $base = [
        'mese'  => $mese,
        'nome'  => SEQ_MESI_BREVI[$mese],
        'n'     => $n,
        'p50'   => null, 'p75' => null, 'p90' => null, 'max' => null,
        'media' => null,
        'max_inizio' => null, 'max_fine' => null, 'max_anno' => null,
    ];
    if ($n < SEQ_MIN_SEQUENZE) return $base;
    sort($v);
    $base['p50']   = round(seqPercentile($v, 50), 1);
    $base['p75']   = round(seqPercentile($v, 75), 1);
    $base['p90']   = round(seqPercentile($v, 90), 1);
    $base['max']   = (int)max($v);
    $base['media'] = round(array_sum($v) / $n, 1);
    if ($record !== null) {
        $base['max_inizio'] = $record['inizio'];
        $base['max_fine']   = $record['fine'];
        $base['max_anno']   = (int)substr((string)$record['inizio'], 0, 4);
    }
    return $base;
}


// ---------------------------------------------------------------------------
// SEQUENZA IN CORSO
// ---------------------------------------------------------------------------
/**
 * Legge gli ultimi giorni e risale all'indietro ricostruendo le sequenze una
 * dopo l'altra. Ne servono tre: quella in corso, quella che la precede (di
 * tipo opposto) e la precedente dello stesso tipo. Da queste si ricava
 * l'ULTIMA CONCLUSA di ciascun tipo.
 *
 * Finestra di 400 giorni: copre abbondantemente tre sequenze anche nel caso
 * peggiore (massimo storico 80 giorni) senza mai leggere l'intera serie.
 *
 * Una sequenza risalendo all'indietro e' CHIUSA solo se la risalita l'ha
 * terminata incontrando un giorno di tipo diverso. Se finisce perche' i dati
 * si interrompono (buco o fine finestra), la sua durata sarebbe sottostimata
 * e viene scartata.
 */
function seqCorrente(PDO $pdo_r, string $oggi_str, array $durate): array {
    $tab = table_name(SEQ_TABELLA_BASE);
    $cd  = SEQ_COL_DATA; $cm = SEQ_COL_MM; $cf = SEQ_COL_FLAG;

    $da = (new DateTime($oggi_str))->modify('-400 day')->format('Y-m-d');

    try {
        $stmt = $pdo_r->prepare("SELECT $cd AS d, $cm AS mm
                                 FROM $tab
                                 WHERE $cd >= :da AND $cd <= :oggi AND $cf = 1
                                 ORDER BY $cd DESC");
        $stmt->execute([':da' => $da, ':oggi' => $oggi_str]);
        $righe = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Errore query sequenza in corso: ' . $e->getMessage()];
    }
    if (!$righe) {
        return ['success' => true, 'corrente' => null,
                'ultime' => ['secca' => null, 'piovosa' => null]];
    }

    // ---- risalita: al massimo tre sequenze ----
    $run  = [];   // sequenze dalla piu' recente alla piu' vecchia
    $cur  = null;
    $prec_ts = null;
    foreach ($righe as $r) {
        $ds = substr((string)$r['d'], 0, 10);
        $ts = strtotime($ds);
        if ($prec_ts !== null && ($prec_ts - $ts) !== 86400) break;   // buco: si ferma
        $t = ((float)$r['mm'] >= SEQ_SOGLIA) ? 'piovosa' : 'secca';

        if ($cur === null || $t !== $cur['tipo']) {
            if ($cur !== null) {
                $cur['chiusa'] = true;       // terminata da un giorno di tipo diverso
                $run[] = $cur;
                if (count($run) >= 3) { $cur = null; break; }
            }
            $cur = ['tipo' => $t, 'len' => 0, 'inizio' => $ds, 'fine' => $ds, 'chiusa' => false];
        }
        $cur['len']++;
        $cur['inizio'] = $ds;
        $prec_ts = $ts;
    }
    if ($cur !== null) { $run[] = $cur; }   // resta aperta: dati finiti, non tipo diverso

    if (!$run) {
        return ['success' => true, 'corrente' => null,
                'ultime' => ['secca' => null, 'piovosa' => null]];
    }

    // ---- sequenza in corso: la prima della risalita ----
    $c    = $run[0];
    $tipo = $c['tipo'];
    $len  = $c['len'];
    $data_ini = $c['inizio'];
    $ultimo   = $c['fine'];
    $mese_ini = (int)substr($data_ini, 5, 2);
    $storiche = $durate[$tipo][$mese_ini] ?? [];

    // Statistica CONDIZIONATA: fra quelle che hanno raggiunto $len giorni,
    // quanto sono poi durate? E' la lettura corretta per una sequenza aperta.
    $arrivate = [];
    foreach ($storiche as $x) if ($x >= $len) $arrivate[] = $x;
    $cond = ['n' => count($arrivate), 'p50' => null, 'p90' => null, 'max' => null];
    if (count($arrivate) >= 4) {
        sort($arrivate);
        $cond['p50'] = round(seqPercentile($arrivate, 50), 1);
        $cond['p90'] = round(seqPercentile($arrivate, 90), 1);
        $cond['max'] = (int)max($arrivate);
    }

    $corrente = seqDescrivi($c, $durate) + ['cond' => $cond, 'ultimo_dato' => $ultimo];

    // ---- ultima conclusa per tipo ----
    // La prima chiusa incontrata risalendo, tipo per tipo: per il tipo in
    // corso e' run[2], per l'altro e' run[1].
    $ultime = ['secca' => null, 'piovosa' => null];
    for ($i = 1; $i < count($run); $i++) {
        $r = $run[$i];
        if (!$r['chiusa']) continue;
        if ($ultime[$r['tipo']] === null) $ultime[$r['tipo']] = seqDescrivi($r, $durate);
    }

    return ['success' => true, 'corrente' => $corrente, 'ultime' => $ultime];
}

/** Descrizione comune di una sequenza: durata, date, posizione nel suo mese. */
function seqDescrivi(array $r, array $durate): array {
    $mese_ini = (int)substr($r['inizio'], 5, 2);
    $storiche = $durate[$r['tipo']][$mese_ini] ?? [];

    // Posizione fra le sequenze concluse dello stesso mese di inizio
    $perc = null;
    if (count($storiche) >= SEQ_MIN_SEQUENZE) {
        $sotto = 0;
        foreach ($storiche as $x) if ($x < $r['len']) $sotto++;
        $perc = round($sotto / count($storiche) * 100, 1);
    }

    return [
        'tipo'        => $r['tipo'],
        'giorni'      => $r['len'],
        'data_inizio' => $r['inizio'],
        'data_fine'   => $r['fine'],
        'mese_inizio' => $mese_ini,
        'nome_mese'   => SEQ_MESI_BREVI[$mese_ini],
        'percentile'  => $perc,
        'n_storiche'  => count($storiche),
    ];
}


// ---------------------------------------------------------------------------
// STATISTICA
// ---------------------------------------------------------------------------
/** Percentile con interpolazione lineare (stesso metodo delle altre API). */
function seqPercentile(array $v, float $p): float {
    $v = array_values(array_map('floatval', $v));
    sort($v);
    $n = count($v);
    if ($n === 0) return 0.0;
    if ($n === 1) return $v[0];
    $pos = ($p / 100) * ($n - 1);
    $lo  = (int)floor($pos);
    $hi  = (int)ceil($pos);
    return $lo === $hi ? $v[$lo] : $v[$lo] + ($pos - $lo) * ($v[$hi] - $v[$lo]);
}


// ---------------------------------------------------------------------------
// CACHE (stessa tabella delle altre API, chiave 'seq|vN')
// ---------------------------------------------------------------------------
function seqCacheChiave(): string {
    return 'seq|v' . SEQ_CACHE_VERSIONE;
}

function seqCacheLeggi(string $data_rif): ?array {
    $pdo_r = seqPdoLettura();
    if (!$pdo_r) return null;
    try {
        $tab  = table_name(SEQ_CACHE_BASE);
        $stmt = $pdo_r->prepare("SELECT payload FROM $tab WHERE data_rif = :d AND modo = :m LIMIT 1");
        $stmt->execute([':d' => $data_rif, ':m' => seqCacheChiave()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $dec = json_decode($row['payload'], true);
        return (is_array($dec) && !empty($dec['success'])) ? $dec : null;
    } catch (PDOException $e) {
        return null;
    }
}

function seqCacheScrivi(string $data_rif, array $payload): void {
    $pdo_w = seqPdoScrittura();
    if (!$pdo_w) return;
    try {
        $tab = table_name(SEQ_CACHE_BASE);
        $pdo_w->exec("CREATE TABLE IF NOT EXISTS $tab (
                        data_rif  DATE        NOT NULL,
                        modo      VARCHAR(24) NOT NULL,
                        payload   MEDIUMTEXT  NOT NULL,
                        creato_il TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (data_rif, modo)
                      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmt = $pdo_w->prepare("REPLACE INTO $tab (data_rif, modo, payload) VALUES (:d, :m, :p)");
        $stmt->execute([':d' => $data_rif, ':m' => seqCacheChiave(),
                        ':p' => json_encode($payload, JSON_UNESCAPED_UNICODE)]);
        $del = $pdo_w->prepare("DELETE FROM $tab WHERE data_rif < DATE_SUB(:d, INTERVAL 7 DAY)");
        $del->execute([':d' => $data_rif]);
    } catch (PDOException $e) {
        // cache best-effort
    }
}