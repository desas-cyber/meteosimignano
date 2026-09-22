<?php
/**
 * ============================================================================
 * TABELLA PIOGGIA 3BIS - DATA LAYER  api/api_tabella_stat3bis_data.php
 * ============================================================================
 *
 * RESPONSABILITA':
 *   Per tre periodi che terminano OGGI:
 *     - anno       1 gen -> oggi
 *     - mese       1 del mese -> oggi
 *     - trimestre  ultimi 3 mesi mobili (es. 21 set -> 22 giu - 21 set)
 *   calcola cinque metriche:
 *     mm cumulati | gg pioggia (>=1mm) | gg >=20mm |
 *     sequenza piovosa max | sequenza secca max
 *   e le colloca rispetto allo STESSO PERIODO degli anni precedenti
 *   (stessa data di fine, stessa regola di inizio).
 *
 * COSA VA IN CACHE E COSA NO (come api_pluvio_stat_data.php):
 *   - CLIMATOLOGIA (valori degli anni passati per periodo e metrica):
 *     cambia una volta al giorno -> cache in pluvio_stat_cache,
 *     chiave modo = 'stat3bis|vN'.
 *   - VALORI CORRENTI: cambiano ad ogni pioggia -> sempre ricalcolati,
 *     con una query sul solo ultimo anno e poco piu'.
 *
 * REGOLE DI CALCOLO (decise per questa tabella):
 *   - Giorno piovoso: RR >= 1.0 mm, stessa soglia di api_sequenze_data.php.
 *   - Serve SEMPRE il dettaglio giornaliero (flag = 1): tutte le finestre
 *     sono tagliate a un giorno preciso, anche per i mm.
 *   - Sequenze TRONCATE alla finestra: conta solo la parte interna.
 *     Se la sequenza massima tocca l'inizio della finestra ed era gia'
 *     iniziata prima, per il periodo corrente si restituisce anche la data
 *     d'inizio reale (nota "in corso dal ..." / "iniziata il ...").
 *   - Un giorno mancante interrompe la sequenza (date consecutive reali).
 *   - A parita' di durata vale la sequenza PIU' RECENTE (e' quella che
 *     interessa leggere in tabella, spesso quella in corso).
 *   - "gg >= 20mm" e' solo informativo: niente percentile, niente classe.
 *
 * PERCENTILE CON I PAREGGI ("regola della fascia"):
 *   Con molti anni a pari valore il percentile non e' un punto ma una
 *   fascia: da (anni minori)/n a (anni minori o uguali)/n.
 *     - se la fascia tocca 40-60  -> classe 'norma' (grigio)
 *     - altrimenti                -> centro della fascia (rango medio)
 *   Senza pareggi la fascia si riduce a un punto: stesso risultato del
 *   percentile classico, quindi nessun caso speciale.
 *
 * CLASSI (il display le mappa sui colori, qui nessun colore):
 *   secco3 <P10 | secco2 P10-25 | secco1 P25-40 | norma P40-60 |
 *   umido1 P60-75 | umido2 P75-90 | umido3 >=P90
 *   Sequenza secca: scala INVERTITA (piu' lunga = piu' secca).
 *
 * CONVENZIONI PROGETTO:
 * - PDO: $pdo (envelop.php) / $pdo_lettura (envelop_lettura.php),
 *   inclusi dal display prima di questa API
 * - datetime_helper.php: get_now() al posto di date()
 * - env_tables_helper.php: table_name() per il suffisso _test
 */

require_once __DIR__ . '/../datetime_helper.php';
require_once __DIR__ . '/../env_tables_helper.php';

// ---------------------------------------------------------------------------
// CONFIGURAZIONE
// ---------------------------------------------------------------------------
const S3B_TABELLA_BASE   = 'pluvio_giornaliero';
const S3B_CACHE_BASE     = 'pluvio_stat_cache';   // stessa tabella cache delle altre API
const S3B_COL_DATA       = 'data';
const S3B_COL_MM         = 'cumulato_24h';
const S3B_COL_FLAG       = 'usa_per_giornaliero';
const S3B_SOGLIA         = 1.0;    // mm: giorno piovoso (come SEQ_SOGLIA)
const S3B_SOGLIA_FORTE   = 20.0;   // mm: giorno di pioggia forte
const S3B_ANNO_MIN       = 2001;
const S3B_COPERTURA_MIN  = 0.90;   // anno climatologico valido nella finestra
const S3B_COPERTURA_AST  = 0.75;   // sotto: asterisco rosso sul valore corrente
const S3B_MIN_ANNI       = 10;     // sotto: niente percentile ne' classe
const S3B_MARGINE_GG     = 200;    // giorni letti prima della finestra (inizio reale sequenze)
const S3B_CACHE_ATTIVA   = true;
const S3B_CACHE_VERSIONE = 1;      // INCREMENTARE ad ogni modifica del payload

const S3B_MESI_BREVI = ['', 'gen', 'feb', 'mar', 'apr', 'mag', 'giu',
                        'lug', 'ago', 'set', 'ott', 'nov', 'dic'];
const S3B_MESI_LUNGHI = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
                         'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];

// Ordine delle righe in tabella
const S3B_PERIODI = ['anno', 'mese', 'trimestre'];

// Metriche: percentile si/no, scala invertita si/no
const S3B_METRICHE = [
    'mm'        => ['perc' => true,  'inverti' => false],
    'gg'        => ['perc' => true,  'inverti' => false],
    'gg20'      => ['perc' => false, 'inverti' => false],
    'seq_piov'  => ['perc' => true,  'inverti' => false],
    'seq_secca' => ['perc' => true,  'inverti' => true],
];


// ---------------------------------------------------------------------------
// CONNESSIONI
// ---------------------------------------------------------------------------
function s3bPdoLettura(): ?PDO {
    if (isset($GLOBALS['pdo_lettura']) && $GLOBALS['pdo_lettura'] instanceof PDO) return $GLOBALS['pdo_lettura'];
    if (isset($GLOBALS['pdo'])         && $GLOBALS['pdo']         instanceof PDO) return $GLOBALS['pdo'];
    return null;
}
function s3bPdoScrittura(): ?PDO {
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) return $GLOBALS['pdo'];
    return null;
}


// ---------------------------------------------------------------------------
// FUNZIONE PRINCIPALE
// ---------------------------------------------------------------------------
/**
 * @param string|null $data_rif 'Y-m-d' (default: oggi via get_now)
 * @return array { success, meta, righe:[3] }
 */
function getStat3bisData($data_rif = null): array {
    $pdo_r = s3bPdoLettura();
    if (!$pdo_r) {
        return ['success' => false,
                'error'   => 'Connessione PDO non trovata: includere envelop.php o envelop_lettura.php prima dell\'API'];
    }

    $oggi_str = s3bNormalizzaData($data_rif) ?: s3bNormalizzaData(get_now('Y-m-d'));
    $anno_oggi = (int)substr($oggi_str, 0, 4);

    // ---- climatologia: cache giornaliera ----
    $clima = S3B_CACHE_ATTIVA ? s3bCacheLeggi($oggi_str) : null;
    if ($clima === null) {
        $clima = s3bClimatologia($pdo_r, $oggi_str);
        if (empty($clima['success'])) return $clima;
        if (S3B_CACHE_ATTIVA) s3bCacheScrivi($oggi_str, $clima);
    }

    // ---- valori correnti: sempre freschi ----
    $finestre = s3bFinestre($oggi_str, $anno_oggi);
    $da = s3bSpostaGiorni(min(array_column($finestre, 'ini')), -S3B_MARGINE_GG);
    try {
        $serie = s3bCaricaSerie($pdo_r, $da, $oggi_str);
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Errore query valori correnti: ' . $e->getMessage()];
    }

    $righe = [];
    $scarso_globale = false;
    foreach (S3B_PERIODI as $pid) {
        $f   = $finestre[$pid];
        $met = s3bMetriche($serie, $f['ini'], $f['fine']);
        $scarso = ($met['copertura'] < S3B_COPERTURA_AST);
        if ($scarso) $scarso_globale = true;

        $celle = [];
        foreach (S3B_METRICHE as $mid => $conf) {
            $valore = s3bValoreMetrica($met, $mid);
            $cella  = ['val' => s3bFmtValore($mid, $valore), 'classe' => null];

            if ($conf['perc']) {
                $storici = $clima['valori'][$pid][$mid] ?? [];
                $pos = s3bPosizione($storici, (float)$valore, $conf['inverti']);
                $cella['classe'] = $pos['classe'];
                // Percentile mostrato solo per le sequenze (sempre quello VERO,
                // non invertito: e' la classe ad essere gia' invertita)
                if ($mid === 'seq_piov' || $mid === 'seq_secca') {
                    $cella['perc']     = $pos['perc'];
                    $cella['perc_lo']  = $pos['perc_lo'];
                    $cella['perc_hi']  = $pos['perc_hi'];
                    $cella['perc_txt'] = s3bFmtPercentile($pos);
                    $cella['freq_txt'] = s3bFmtFrequenza($pos);
                }
            }

            if ($mid === 'seq_piov' || $mid === 'seq_secca') {
                $tipo = ($mid === 'seq_piov') ? 'piovosa' : 'secca';
                $seq  = $met[$mid];
                $ctx  = s3bContestoSequenza($serie, $seq, $tipo, $f['ini'], $f['fine']);
                $cella['inizio']       = $seq['ini'];
                $cella['fine']         = $seq['fine'];
                $cella['inizio_reale'] = $ctx['inizio_reale'];
                $cella['aperta']       = $ctx['aperta'];
                $cella['date_txt']     = $seq['len'] > 0 ? s3bFmtIntervallo($seq['ini'], $seq['fine'], false) : '';
                $cella['nota_txt']     = s3bNotaSequenza($ctx, $anno_oggi);
            }
            $celle[$mid] = $cella;
        }

        $righe[] = [
            'id'          => $pid,
            'label'       => s3bEtichetta($pid, $oggi_str),
            'periodo_txt' => s3bFmtIntervallo($f['ini'], $f['fine'], true),
            'inizio'      => $f['ini'],
            'fine'        => $f['fine'],
            'copertura'   => round($met['copertura'], 3),
            'scarso'      => $scarso,
            'n_anni'      => count($clima['anni'][$pid] ?? []),
            'celle'       => $celle,
        ];
    }

    return [
        'success' => true,
        'meta'    => [
            'oggi'          => $oggi_str,
            'generato_il'   => get_now('d/m/Y H:i'),
            'soglia'        => S3B_SOGLIA,
            'soglia_forte'  => S3B_SOGLIA_FORTE,
            'clima_label'   => $clima['meta']['clima_label'],
            'esclusi'       => $clima['meta']['esclusi'],
            'scarso'        => $scarso_globale,
        ],
        'righe'   => $righe,
    ];
}


// ---------------------------------------------------------------------------
// FINESTRE
// ---------------------------------------------------------------------------
/**
 * Le tre finestre di un anno Y, tutte con la data di fine di oggi (giorno e
 * mese) portata nell'anno Y. Il 29 febbraio negli anni non bisestili
 * diventa 28.
 * Trimestre: dallo stesso giorno di 3 mesi prima (+1) a oggi.
 *   21 set -> 22 giu - 21 set;  31 mag -> 1 mar - 31 mag;
 *   15 feb -> 16 nov (anno prima) - 15 feb.
 */
function s3bFinestre(string $oggi_str, int $anno): array {
    $m = (int)substr($oggi_str, 5, 2);
    $d = (int)substr($oggi_str, 8, 2);
    $fine = s3bData($anno, $m, $d);

    $m3 = $m - 3; $y3 = $anno;
    if ($m3 < 1) { $m3 += 12; $y3--; }
    $ini_trim = s3bSpostaGiorni(s3bData($y3, $m3, $d), 1);

    return [
        'anno'      => ['ini' => sprintf('%04d-01-01', $anno),        'fine' => $fine],
        'mese'      => ['ini' => sprintf('%04d-%02d-01', $anno, $m),  'fine' => $fine],
        'trimestre' => ['ini' => $ini_trim,                            'fine' => $fine],
    ];
}

/** Data valida: il giorno viene ridotto all'ultimo del mese se serve. */
function s3bData(int $y, int $m, int $d): string {
    $gg_mese = (int)gmdate('t', gmmktime(0, 0, 0, $m, 1, $y));
    return sprintf('%04d-%02d-%02d', $y, $m, min($d, $gg_mese));
}

/** Aritmetica sulle date in UTC: niente sorprese col cambio dell'ora legale. */
function s3bSpostaGiorni(string $ds, int $n): string {
    return gmdate('Y-m-d', strtotime($ds . ' 00:00:00 UTC') + $n * 86400);
}

function s3bNormalizzaData($s): ?string {
    if (!is_string($s) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $mm)) return null;
    return checkdate((int)$mm[2], (int)$mm[3], (int)$mm[1]) ? "$mm[1]-$mm[2]-$mm[3]" : null;
}


// ---------------------------------------------------------------------------
// LETTURA DATI
// ---------------------------------------------------------------------------
/** Serie giornaliera [Y-m-d => mm] con flag = 1. Lancia PDOException. */
function s3bCaricaSerie(PDO $pdo_r, string $da, string $a): array {
    $tab = table_name(S3B_TABELLA_BASE);
    $cd  = S3B_COL_DATA; $cm = S3B_COL_MM; $cf = S3B_COL_FLAG;

    $stmt = $pdo_r->prepare("SELECT $cd AS d, $cm AS mm
                             FROM $tab
                             WHERE $cd >= :da AND $cd <= :a AND $cf = 1
                             ORDER BY $cd");
    $stmt->execute([':da' => $da, ':a' => $a]);

    $serie = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($r['mm'] === null) continue;              // riga senza valore = giorno mancante
        $serie[substr((string)$r['d'], 0, 10)] = (float)$r['mm'];
    }
    return $serie;
}


// ---------------------------------------------------------------------------
// METRICHE DI UNA FINESTRA
// ---------------------------------------------------------------------------
/**
 * Un solo passaggio sui giorni della finestra.
 * Le sequenze sono troncate ai bordi della finestra e interrotte dai
 * giorni mancanti.
 */
function s3bMetriche(array $serie, string $ini, string $fine): array {
    $presenti = 0; $attesi = 0;
    $mm = 0.0; $gg = 0; $gg20 = 0;

    $vuota = ['len' => 0, 'ini' => null, 'fine' => null];
    $best  = ['piovosa' => $vuota, 'secca' => $vuota];
    $tipo = null; $len = 0; $s_ini = null; $s_fine = null;

    $chiudi = function () use (&$tipo, &$len, &$s_ini, &$s_fine, &$best) {
        // >= : a parita' di durata vince la sequenza piu' recente
        if ($tipo !== null && $len > 0 && $len >= $best[$tipo]['len']) {
            $best[$tipo] = ['len' => $len, 'ini' => $s_ini, 'fine' => $s_fine];
        }
        $tipo = null; $len = 0; $s_ini = null; $s_fine = null;
    };

    $ts     = strtotime($ini . ' 00:00:00 UTC');
    $ts_end = strtotime($fine . ' 00:00:00 UTC');
    for (; $ts <= $ts_end; $ts += 86400) {
        $ds = gmdate('Y-m-d', $ts);
        $attesi++;
        if (!isset($serie[$ds])) { $chiudi(); continue; }

        $v = $serie[$ds];
        $presenti++;
        $mm += $v;
        if ($v >= S3B_SOGLIA)       $gg++;
        if ($v >= S3B_SOGLIA_FORTE) $gg20++;

        $t = ($v >= S3B_SOGLIA) ? 'piovosa' : 'secca';
        if ($t === $tipo) {
            $len++; $s_fine = $ds;
        } else {
            $chiudi();
            $tipo = $t; $len = 1; $s_ini = $ds; $s_fine = $ds;
        }
    }
    $chiudi();

    return [
        'presenti'  => $presenti,
        'attesi'    => $attesi,
        'copertura' => $attesi > 0 ? $presenti / $attesi : 0.0,
        'mm'        => round($mm, 1),
        'gg'        => $gg,
        'gg20'      => $gg20,
        'seq_piov'  => $best['piovosa'],
        'seq_secca' => $best['secca'],
    ];
}

function s3bValoreMetrica(array $met, string $mid) {
    if ($mid === 'seq_piov' || $mid === 'seq_secca') return $met[$mid]['len'];
    return $met[$mid];
}

/**
 * Solo per il periodo corrente:
 * - inizio_reale: se la sequenza parte dal primo giorno della finestra,
 *   risale all'indietro finche' il tipo resta uguale e i giorni sono
 *   presenti. Null se la sequenza non era gia' iniziata prima.
 * - aperta: la sequenza arriva all'ultimo giorno della finestra (oggi).
 */
function s3bContestoSequenza(array $serie, array $seq, string $tipo, string $ini_f, string $fine_f): array {
    $out = ['inizio_reale' => null, 'aperta' => false];
    if ($seq['len'] <= 0) return $out;

    $out['aperta'] = ($seq['fine'] === $fine_f);

    if ($seq['ini'] === $ini_f) {
        $ds = s3bSpostaGiorni($ini_f, -1);
        $primo = null;
        while (isset($serie[$ds])) {
            $t = ($serie[$ds] >= S3B_SOGLIA) ? 'piovosa' : 'secca';
            if ($t !== $tipo) break;
            $primo = $ds;
            $ds = s3bSpostaGiorni($ds, -1);
        }
        $out['inizio_reale'] = $primo;
    }
    return $out;
}


// ---------------------------------------------------------------------------
// CLIMATOLOGIA
// ---------------------------------------------------------------------------
/**
 * Una sola SELECT sull'intera serie, poi tutto in PHP.
 * Per ogni anno passato e ogni periodo: metriche sulla stessa finestra;
 * l'anno entra solo se la copertura della finestra e' >= S3B_COPERTURA_MIN.
 * I valori vengono salvati ordinati: il percentile del corrente si calcola
 * a ogni richiesta, in pochi microsecondi.
 */
function s3bClimatologia(PDO $pdo_r, string $oggi_str): array {
    $anno_oggi = (int)substr($oggi_str, 0, 4);
    try {
        // Da ottobre dell'anno prima: il trimestre di gen-mar parte a fine anno precedente
        $serie = s3bCaricaSerie($pdo_r, (S3B_ANNO_MIN - 1) . '-10-01', $oggi_str);
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Errore query climatologia: ' . $e->getMessage()];
    }
    if (!$serie) {
        return ['success' => false, 'error' => 'Nessun dato giornaliero disponibile'];
    }

    $valori = []; $anni = []; $esclusi = [];
    foreach (S3B_PERIODI as $pid) {
        $anni[$pid] = []; $esclusi[$pid] = [];
        foreach (S3B_METRICHE as $mid => $conf) {
            if ($conf['perc']) $valori[$pid][$mid] = [];
        }
    }

    for ($y = S3B_ANNO_MIN; $y < $anno_oggi; $y++) {
        $finestre = s3bFinestre($oggi_str, $y);
        foreach (S3B_PERIODI as $pid) {
            $met = s3bMetriche($serie, $finestre[$pid]['ini'], $finestre[$pid]['fine']);
            if ($met['copertura'] < S3B_COPERTURA_MIN) {
                $esclusi[$pid][] = ['anno' => $y, 'copertura' => round($met['copertura'], 3)];
                continue;
            }
            $anni[$pid][] = $y;
            foreach ($valori[$pid] as $mid => $_) {
                $valori[$pid][$mid][] = (float)s3bValoreMetrica($met, $mid);
            }
        }
    }

    foreach ($valori as $pid => $per_metrica) {
        foreach ($per_metrica as $mid => $v) {
            sort($v);
            $valori[$pid][$mid] = $v;
        }
    }

    $tutti = array_unique(array_merge(...array_values($anni)));
    sort($tutti);

    return [
        'success' => true,
        'meta'    => [
            'oggi'        => $oggi_str,
            'clima_label' => $tutti ? (min($tutti) . "\u{2013}" . max($tutti)) : 'n/d',
            'esclusi'     => $esclusi,
        ],
        'anni'    => $anni,
        'valori'  => $valori,
    ];
}


// ---------------------------------------------------------------------------
// POSIZIONE NELLA DISTRIBUZIONE (regola della fascia)
// ---------------------------------------------------------------------------
/**
 * @param array $storici valori degli anni passati
 * @param float $v       valore corrente
 * @param bool  $inverti true per la sequenza secca
 * @return array { perc, perc_lo, perc_hi: int|null (percentili veri, non invertiti),
 *                 classe: string|null }
 */
function s3bPosizione(array $storici, float $v, bool $inverti): array {
    $n = count($storici);
    if ($n < S3B_MIN_ANNI) return ['perc' => null, 'perc_lo' => null, 'perc_hi' => null,
                                   'n' => $n, 'minori' => null, 'uguali' => null, 'maggiori' => null,
                                   'classe' => null];

    $minori = 0; $uguali = 0;
    foreach ($storici as $x) {
        if (abs($x - $v) < 1e-9) $uguali++;
        elseif ($x < $v)         $minori++;
    }
    $lo = $minori / $n * 100;
    $hi = ($minori + $uguali) / $n * 100;
    $centro = ($lo + $hi) / 2;

    // Per la classe la scala si ribalta: [lo, hi] -> [100-hi, 100-lo]
    if ($inverti) { $c_lo = 100 - $hi; $c_hi = 100 - $lo; }
    else          { $c_lo = $lo;       $c_hi = $hi; }

    // La fascia tocca la zona 40-60 -> nella norma.
    // Senza pareggi (c_lo = c_hi) equivale a 40 <= p < 60.
    if ($c_lo < 60 && $c_hi >= 40) {
        $classe = 'norma';
    } else {
        $classe = s3bClasse(($c_lo + $c_hi) / 2);
    }
    return [
        'perc'    => (int)round($centro),   // centro della fascia (rango medio)
        'perc_lo' => (int)round($lo),       // estremi della fascia: coincidono
        'perc_hi' => (int)round($hi),       // se non ci sono pareggi
        'n'        => $n,                   // conteggi grezzi: servono alla
        'minori'   => $minori,              // frequenza empirica mostrata in
        'uguali'   => $uguali,              // tabella (s3bFmtFrequenza)
        'maggiori' => $n - $minori - $uguali,
        'classe'  => $classe,
    ];
}

function s3bClasse(float $p): string {
    if ($p < 10) return 'secco3';
    if ($p < 25) return 'secco2';
    if ($p < 40) return 'secco1';
    if ($p < 60) return 'norma';
    if ($p < 75) return 'umido1';
    if ($p < 90) return 'umido2';
    return 'umido3';
}


// ---------------------------------------------------------------------------
// FORMATTAZIONE (il display stampa e basta)
// ---------------------------------------------------------------------------
function s3bFmtValore(string $mid, $v): string {
    if ($mid === 'mm') return number_format((float)$v, 1, '.', '');
    if ($mid === 'seq_piov' || $mid === 'seq_secca') return (int)$v . ' gg';
    return (string)(int)$v;
}

function s3bEtichetta(string $pid, string $oggi_str): string {
    $y = substr($oggi_str, 0, 4);
    $m = (int)substr($oggi_str, 5, 2);
    if ($pid === 'anno') return "Anno $y";
    if ($pid === 'mese') return S3B_MESI_LUNGHI[$m] . " $y";
    return 'Ultimo trimestre';
}

/** "21 set" (con anno se diverso da $anno_rif) */
function s3bFmtGiorno(string $ds, ?int $anno_rif = null): string {
    $y = (int)substr($ds, 0, 4);
    $txt = (int)substr($ds, 8, 2) . ' ' . S3B_MESI_BREVI[(int)substr($ds, 5, 2)];
    return ($anno_rif !== null && $y !== $anno_rif) ? "$txt $y" : $txt;
}

/**
 * Intervallo compatto.
 *   stesso giorno:   "21 set"
 *   stesso mese:     "9–10 set"      (periodo: "1 – 21 set")
 *   mesi diversi:    "18 lug–17 ago" (periodo: "1 gen – 21 set")
 *   anni diversi:    l'anno compare sulla data iniziale
 */
function s3bFmtIntervallo(string $ini, string $fine, bool $spazi): string {
    $sep = $spazi ? " \u{2013} " : "\u{2013}";
    if ($ini === $fine) return s3bFmtGiorno($fine);

    $anno_fine = (int)substr($fine, 0, 4);
    if (substr($ini, 0, 7) === substr($fine, 0, 7)) {
        return (int)substr($ini, 8, 2) . $sep . s3bFmtGiorno($fine);
    }
    return s3bFmtGiorno($ini, $anno_fine) . $sep . s3bFmtGiorno($fine);
}

/**
 * "P58" se il valore occupa un solo punto della distribuzione,
 * "P5–55" se ci sono pareggi (fascia): e' la stessa informazione che
 * decide il colore, cosi' testo e barra non si contraddicono.
 */
function s3bFmtPercentile(array $pos): string {
    if ($pos['perc_lo'] === null) return '';
    if ($pos['perc_lo'] === $pos['perc_hi']) return 'P' . $pos['perc_lo'];
    return 'P' . $pos['perc_lo'] . "\u{2013}" . $pos['perc_hi'];
}

/**
 * Frequenza empirica dalla parte della coda piu' vicina:
 *   valore alto  -> "≥ in 4/20 anni"  (anni che hanno raggiunto o superato)
 *   valore basso -> "≤ in 11/20 anni" (anni rimasti uguali o sotto)
 * I pari sono inclusi: "quante volte e' capitato almeno/al massimo questo".
 * La coda si sceglie sul percentile VERO (non invertito), quindi per la
 * sequenza secca "≥" indica sempre durate lunghe.
 */
function s3bFmtFrequenza(array $pos): string {
    if ($pos['minori'] === null) return '';
    if ($pos['perc'] >= 50) {
        return 'f:' . ($pos['uguali'] + $pos['maggiori']) . '/' . $pos['n'] . ' anni';
    }
        return 'f: ' . ($pos['minori'] + $pos['uguali']) . '/' . $pos['n'] . ' anni';
}

function s3bNotaSequenza(array $ctx, int $anno_rif): string {
    if (!$ctx['inizio_reale']) return '';
    $giorno = s3bFmtGiorno($ctx['inizio_reale'], $anno_rif);
    return $ctx['aperta'] ? "in corso dal $giorno" : "iniziata il $giorno";
}


// ---------------------------------------------------------------------------
// CACHE (stessa tabella delle altre API, chiave 'stat3bis|vN')
// ---------------------------------------------------------------------------
function s3bCacheChiave(): string {
    return 'stat3bis|v' . S3B_CACHE_VERSIONE;
}

function s3bCacheLeggi(string $data_rif): ?array {
    $pdo_r = s3bPdoLettura();
    if (!$pdo_r) return null;
    try {
        $tab  = table_name(S3B_CACHE_BASE);
        $stmt = $pdo_r->prepare("SELECT payload FROM $tab WHERE data_rif = :d AND modo = :m LIMIT 1");
        $stmt->execute([':d' => $data_rif, ':m' => s3bCacheChiave()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $dec = json_decode($row['payload'], true);
        return (is_array($dec) && !empty($dec['success'])) ? $dec : null;
    } catch (PDOException $e) {
        return null;
    }
}

function s3bCacheScrivi(string $data_rif, array $payload): void {
    $pdo_w = s3bPdoScrittura();
    if (!$pdo_w) return;
    try {
        $tab = table_name(S3B_CACHE_BASE);
        $pdo_w->exec("CREATE TABLE IF NOT EXISTS $tab (
                        data_rif  DATE        NOT NULL,
                        modo      VARCHAR(24) NOT NULL,
                        payload   MEDIUMTEXT  NOT NULL,
                        creato_il TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (data_rif, modo)
                      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmt = $pdo_w->prepare("REPLACE INTO $tab (data_rif, modo, payload) VALUES (:d, :m, :p)");
        $stmt->execute([':d' => $data_rif, ':m' => s3bCacheChiave(),
                        ':p' => json_encode($payload, JSON_UNESCAPED_UNICODE)]);
        $del = $pdo_w->prepare("DELETE FROM $tab WHERE data_rif < DATE_SUB(:d, INTERVAL 7 DAY) AND modo = :m");
        $del->execute([':d' => $data_rif, ':m' => s3bCacheChiave()]);
    } catch (PDOException $e) {
        // cache best-effort
    }
}

// ===========================================================================
// GRAFICO 3BIS  (usato da grafico_stat3bis_display.php)
// ===========================================================================
/*
 * Per una data di riferimento D (default: ultimo giorno disponibile):
 *   - per ogni anno 2001 .. anno(D)-1:
 *       valori sull'ANNO INTERO (1 gen - 31 dic)
 *       valori sul PERIODO      (1 gen - stesso giorno/mese di D)
 *   - valori dell'anno di D fino a D (periodo corrente)
 *   - se l'anno di D e' gia' concluso: valori del suo anno intero
 *   - percentili P10/P25/P50/P75/P90 degli anni passati, separati per
 *     anno intero e periodo, per le 4 metriche con percentile.
 *
 * Stesse regole della tabella: flag = 1, soglia 1 mm, copertura >= 90%,
 * sequenze troncate alla finestra, almeno S3B_MIN_ANNI anni per le statistiche.
 *
 * Unica eccezione: anni senza dettaglio giornaliero (flag = 0, es. 2001-2003).
 * Se S3B_G_TOT_FALLBACK e' attivo e l'anno ha comunque le righe con i mm,
 * il totale ANNUO dei mm viene preso da quelle righe (nod = true):
 * serve la barra dal 2001 e il totale annuo e' affidabile anche senza il
 * dettaglio giornaliero. Periodo e altre metriche restano nulli.
 *
 * Niente cache: una SELECT sull'intera serie (~9000 righe) e ~50 passaggi
 * annuali in PHP costano pochi millisecondi.
 */

const S3B_G_METRICHE     = ['mm', 'gg', 'seq_piov', 'seq_secca'];
const S3B_G_PERCENTILI   = [10, 25, 50, 75, 90];
const S3B_G_TOT_FALLBACK = true;

/**
 * @param string|null $data_rif 'Y-m-d' (default: ultimo giorno disponibile)
 */
function getGrafico3bisData($data_rif = null): array {
    $pdo_r = s3bPdoLettura();
    if (!$pdo_r) {
        return ['success' => false,
                'error'   => 'Connessione PDO non trovata: includere envelop.php o envelop_lettura.php prima dell\'API'];
    }

    $oggi = s3bNormalizzaData(get_now('Y-m-d'));
    try {
        $serie  = s3bCaricaSerie($pdo_r, S3B_ANNO_MIN . '-01-01', $oggi);
        $totali = S3B_G_TOT_FALLBACK ? s3bGTotaliAnnui($pdo_r, $oggi) : [];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Errore query grafico: ' . $e->getMessage()];
    }
    if (!$serie) {
        return ['success' => false, 'error' => 'Nessun dato giornaliero disponibile'];
    }

    // ---- intervallo di date selezionabili ----
    $giorni     = array_keys($serie);
    $primo      = $giorni[0];
    $ultimo     = end($giorni);
    $data_max   = ($ultimo < $oggi) ? $ultimo : $oggi;
    $data_min   = s3bGDataMin($serie, $primo, $data_max);

    $data = s3bNormalizzaData($data_rif) ?: $data_max;
    if ($data > $data_max) $data = $data_max;
    if ($data < $data_min) $data = $data_min;

    $Y = (int)substr($data, 0, 4);
    $m = (int)substr($data, 5, 2);
    $d = (int)substr($data, 8, 2);

    // ---- anni passati ----
    $anni = [];
    for ($y = S3B_ANNO_MIN; $y < $Y; $y++) {
        $ma = s3bMetriche($serie, "$y-01-01", "$y-12-31");
        $mp = s3bMetriche($serie, "$y-01-01", s3bData($y, $m, $d));

        $riga = [
            'anno'     => $y,
            'cop_anno' => round($ma['copertura'], 3),
            'cop_per'  => round($mp['copertura'], 3),
            'nod'      => false,   // true = totale annuo senza dettaglio giornaliero
            'v_anno'   => null,    // [mid => valore]
            'v_per'    => null,
        ];

        if ($ma['copertura'] >= S3B_COPERTURA_MIN) {
            $riga['v_anno'] = s3bGValori($ma);
        } elseif (isset($totali[$y]) && $totali[$y]['cop'] >= S3B_COPERTURA_MIN) {
            $riga['nod']    = true;
            $riga['v_anno'] = ['mm' => $totali[$y]['mm'], 'gg' => null,
                               'seq_piov' => null, 'seq_secca' => null];
        }
        if ($mp['copertura'] >= S3B_COPERTURA_MIN) {
            $riga['v_per'] = s3bGValori($mp);
        }
        $anni[] = $riga;
    }

    // ---- periodo corrente ----
    $mc = s3bMetriche($serie, "$Y-01-01", $data);
    $corrente = [
        'v'       => s3bGValori($mc),
        'cop'     => round($mc['copertura'], 3),
        'scarso'  => ($mc['copertura'] < S3B_COPERTURA_AST),
        'perc'    => [],   // [mid => 'P38' | 'P5–55' | '']
    ];

    // ---- anno di D gia' concluso: come e' finito ----
    $fine_anno = null;
    if ("$Y-12-31" <= $data_max && $data < "$Y-12-31") {
        $mf = s3bMetriche($serie, "$Y-01-01", "$Y-12-31");
        if ($mf['copertura'] >= S3B_COPERTURA_MIN) $fine_anno = s3bGValori($mf);
    }

    // ---- statistiche climatologiche ----
    $stat = ['anno' => [], 'periodo' => []];
    foreach (S3B_G_METRICHE as $mid) {
        $stat['anno'][$mid]    = s3bGStatistiche($anni, 'v_anno', $mid);
        $stat['periodo'][$mid] = s3bGStatistiche($anni, 'v_per',  $mid);

        $storici = array_values(array_filter(array_map(function ($r) use ($mid) {
            return $r['v_per'][$mid] ?? null;
        }, $anni), function ($v) { return $v !== null; }));
        $pos = s3bPosizione($storici, (float)$corrente['v'][$mid], false);
        $corrente['perc'][$mid] = s3bFmtPercentile($pos);
    }

    return [
        'success' => true,
        'meta'    => [
            'data'        => $data,
            'data_txt'    => s3bFmtGiorno($data) . " $Y",
            'periodo_txt' => s3bFmtIntervallo("$Y-01-01", $data, true),
            'giorno_txt'  => s3bFmtGiorno($data),          // "8 set", per i tooltip
            'anno'        => $Y,
            'data_min'    => $data_min,
            'data_max'    => $data_max,
            'oggi'        => $oggi,
            'soglia'      => S3B_SOGLIA,
            'generato_il' => get_now('d/m/Y H:i'),
        ],
        'anni'      => $anni,
        'corrente'  => $corrente,
        'fine_anno' => $fine_anno,
        'stat'      => $stat,
    ];
}

/**
 * Prima data selezionabile: 1 gennaio del primo anno preceduto da almeno
 * S3B_MIN_ANNI anni con copertura giornaliera >= S3B_COPERTURA_MIN
 * (senza, il box del periodo non si puo' calcolare).
 */
function s3bGDataMin(array $serie, string $primo, string $data_max): string {
    $per_anno = [];
    foreach ($serie as $ds => $_) {
        $y = (int)substr($ds, 0, 4);
        $per_anno[$y] = ($per_anno[$y] ?? 0) + 1;
    }
    $validi = 0;
    $y_max  = (int)substr($data_max, 0, 4);
    for ($y = (int)substr($primo, 0, 4); $y <= $y_max; $y++) {
        if ($validi >= S3B_MIN_ANNI) return sprintf('%04d-01-01', $y);
        $gg = (int)gmdate('z', gmmktime(0, 0, 0, 12, 31, $y)) + 1;
        if (($per_anno[$y] ?? 0) / $gg >= S3B_COPERTURA_MIN) $validi++;
    }
    return substr($data_max, 0, 4) . '-01-01';
}

/** Le 4 metriche del grafico da un risultato di s3bMetriche(). */
function s3bGValori(array $met): array {
    $out = [];
    foreach (S3B_G_METRICHE as $mid) {
        $v = s3bValoreMetrica($met, $mid);
        $out[$mid] = ($mid === 'mm') ? round((float)$v, 1) : (int)$v;
    }
    return $out;
}

/**
 * Totali annui dei mm SENZA filtro sul flag: usati solo per gli anni senza
 * dettaglio giornaliero. [anno => ['mm' => float, 'cop' => righe/giorni]]
 */
function s3bGTotaliAnnui(PDO $pdo_r, string $fino_a): array {
    $tab = table_name(S3B_TABELLA_BASE);
    $cd  = S3B_COL_DATA; $cm = S3B_COL_MM;
    $stmt = $pdo_r->prepare("SELECT YEAR($cd) AS y, COUNT(*) AS n, SUM($cm) AS mm
                             FROM $tab
                             WHERE $cd >= :da AND $cd <= :a AND $cm IS NOT NULL
                             GROUP BY YEAR($cd)");
    $stmt->execute([':da' => S3B_ANNO_MIN . '-01-01', ':a' => $fino_a]);

    $out = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $y   = (int)$r['y'];
        $gg  = (int)gmdate('z', gmmktime(0, 0, 0, 12, 31, $y)) + 1;
        $out[$y] = ['mm' => round((float)$r['mm'], 1), 'cop' => (int)$r['n'] / $gg];
    }
    return $out;
}

/**
 * Percentili degli anni validi per una metrica.
 * @return array|null { p10, p25, p50, p75, p90, n, da, a } | null se n < S3B_MIN_ANNI
 */
function s3bGStatistiche(array $anni, string $campo, string $mid): ?array {
    $val = []; $yy = [];
    foreach ($anni as $r) {
        $v = $r[$campo][$mid] ?? null;
        if ($v === null) continue;
        $val[] = (float)$v; $yy[] = $r['anno'];
    }
    $n = count($val);
    if ($n < S3B_MIN_ANNI) return null;
    sort($val);

    $out = [];
    foreach (S3B_G_PERCENTILI as $p) {
        $out['p' . $p] = round(s3bGQuantile($val, $p), 1);
    }
    $out['n']  = $n;
    $out['da'] = min($yy);
    $out['a']  = max($yy);
    return $out;
}

/** Quantile con interpolazione lineare (tipo 7, come Excel PERCENTILE). */
function s3bGQuantile(array $ord, float $p): float {
    $n = count($ord);
    if ($n === 1) return $ord[0];
    $h  = ($n - 1) * $p / 100;
    $lo = (int)floor($h);
    $hi = min($lo + 1, $n - 1);
    return $ord[$lo] + ($ord[$hi] - $ord[$lo]) * ($h - $lo);
}