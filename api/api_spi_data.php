<?php
/**
 * ============================================================================
 * INDICE SPI (3 e 6 mesi) - DATA LAYER  api/api_spi_data.php
 * ============================================================================
 *
 * RESPONSABILITA':
 * - Per ciascuna scala (SPI-3, SPI-6) considera la FINESTRA A MESI DI
 *   CALENDARIO che termina con il mese corrente:
 *       16 settembre -> SPI-3 = lug+ago+set, SPI-6 = apr..set
 *        1 ottobre   -> SPI-3 = ago+set+ott  (giorno 62 di 92)
 *   Il mese in corso e' sempre l'ultimo della finestra.
 * - CLIMATOLOGIA (in cache, cambia una volta al giorno):
 *     * ventaglio p10/p25/p50/p75/p90 della pioggia CUMULATA dall'inizio
 *       della finestra, giorno per giorno, sugli anni storici
 *     * adattamento gamma della cumulata storica al giorno corrente (T) e a
 *       fine finestra (N), da cui SPI attuale e SPI proiettato
 *     * mediana della pioggia caduta storicamente fra T e N (proiezione)
 *     * striscia per anno solare: anno corrente e anno precedente, gen..dic
 * - VALORI CORRENTI (mai in cache, cambiano ad ogni pioggia):
 *     cumulata dell'anno in corso, SPI, percentile, proiezione.
 *
 * CALCOLO SPI (McKee et al. 1993, procedura WMO):
 *   1. distribuzione gamma adattata ai valori storici > 0 (stima di Thom)
 *   2. H(x) = q + (1-q) * G(x), con q = frazione di anni a zero pioggia
 *   3. SPI = inversa della normale standard di H(x)
 *   Con meno di SPI_MIN_ANNI valori utili si ricade sulla posizione empirica.
 *   Il ventaglio disegnato e' invece empirico (percentili interpolati):
 *   il pallino si legge sul ventaglio, il valore SPI viene dalla gamma.
 *
 * CONVENZIONI PROGETTO:
 * - PDO: $pdo (envelop.php) / $pdo_lettura (envelop_lettura.php)
 * - datetime_helper.php: get_now() al posto di date()
 * - env_tables_helper.php: table_name() per il suffisso _test
 *
 * DETTAGLIO GIORNALIERO (colonna usa_per_giornaliero):
 * - cumulate giorno per giorno (ventaglio, valore attuale): solo flag = 1
 * - totali di MESI INTERI (striscia): tutte le righe, quindi anche 2001-2003
 *
 * COSTO: una sola SELECT sull'intera serie (~9-10k righe, colonne minime),
 * eseguita al massimo una volta al giorno grazie alla cache; tutto il resto
 * e' calcolato in PHP. I valori correnti usano una query su <= 6 mesi.
 */

require_once __DIR__ . '/../datetime_helper.php';
require_once __DIR__ . '/../env_tables_helper.php';

// ---------------------------------------------------------------------------
// CONFIGURAZIONE
// ---------------------------------------------------------------------------
const SPI_TABELLA_BASE   = 'pluvio_giornaliero';   // passa da table_name()
const SPI_CACHE_BASE     = 'pluvio_stat_cache';    // stessa tabella cache delle statistiche pluvio
const SPI_COL_DATA       = 'data';
const SPI_COL_MM         = 'cumulato_24h';
const SPI_COL_FLAG       = 'usa_per_giornaliero';
const SPI_SCALE          = [3, 6];
const SPI_ANNO_MIN       = 2001;
const SPI_COPERTURA_MIN  = 0.90;
const SPI_MIN_ANNI       = 10;     // sotto questa soglia niente gamma: posizione empirica
const SPI_PERCENTILI     = [10, 25, 50, 75, 90];
const SPI_CACHE_ATTIVA   = true;
const SPI_CACHE_VERSIONE = 4;      // INCREMENTARE ad ogni modifica del payload o del calcolo

const SPI_MESI_BREVI = ['', 'gen', 'feb', 'mar', 'apr', 'mag', 'giu',
                        'lug', 'ago', 'set', 'ott', 'nov', 'dic'];


// ---------------------------------------------------------------------------
// CONNESSIONI
// ---------------------------------------------------------------------------
function spiPdoLettura(): ?PDO {
    if (isset($GLOBALS['pdo_lettura']) && $GLOBALS['pdo_lettura'] instanceof PDO) return $GLOBALS['pdo_lettura'];
    if (isset($GLOBALS['pdo'])         && $GLOBALS['pdo']         instanceof PDO) return $GLOBALS['pdo'];
    return null;
}
function spiPdoScrittura(): ?PDO {
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) return $GLOBALS['pdo'];
    return null;
}


// ---------------------------------------------------------------------------
// FUNZIONE PRINCIPALE
// ---------------------------------------------------------------------------
/**
 * @param string|null $data_rif 'Y-m-d' (default: oggi via get_now)
 * @return array { success, meta, scale: [ {k, ..., clima, correnti} x2 ] }
 */
function getSpiData($data_rif = null) {
    $pdo_r = spiPdoLettura();
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
    $anno     = (int)$oggi->format('Y');
    $mese     = (int)$oggi->format('n');

    // ---- climatologia: cache giornaliera ----
    $clima = SPI_CACHE_ATTIVA ? spiCacheLeggi($oggi_str) : null;
    if ($clima === null) {
        $clima = spiClimatologia($pdo_r, $oggi);
        if (empty($clima['success'])) return $clima;
        if (SPI_CACHE_ATTIVA) spiCacheScrivi($oggi_str, $clima);
    }

    // ---- valori correnti: sempre freschi ----
    $corr = spiValoriCorrenti($pdo_r, $oggi, $clima['scale']);
    if (empty($corr['success'])) return $corr;

    $scale = [];
    foreach ($clima['scale'] as $i => $s) {
        $s['correnti'] = $corr['scale'][$i];
        $scale[] = $s;
    }

    return [
        'success' => true,
        'meta'    => array_merge($clima['meta'], [
            'generato_il'         => get_now('d/m/Y H:i'),
            'correnti_aggiornati' => get_now('H:i'),
        ]),
        'scale'   => $scale,
    ];
}


// ---------------------------------------------------------------------------
// FINESTRE
// ---------------------------------------------------------------------------
/** Mesi [anno, mese] della finestra di $k mesi che termina con (anno, mese). */
function spiMesiFinestra(int $anno, int $mese, int $k): array {
    $out = [];
    for ($j = $k - 1; $j >= 0; $j--) {
        $m = $mese - $j;
        $y = $anno;
        while ($m < 1) { $m += 12; $y--; }
        $out[] = [$y, $m];
    }
    return $out;
}

/** Giorni del mese (senza estensione calendar, non sempre presente sugli hosting). */
function spiGiorniMese(int $anno, int $mese): int {
    return (int)(new DateTime(sprintf('%04d-%02d-01', $anno, $mese)))->format('t');
}

/** Elenco date 'Y-m-d' della finestra (mesi interi). */
function spiDateFinestra(array $mesi): array {
    $out = [];
    foreach ($mesi as [$y, $m]) {
        $n = spiGiorniMese($y, $m);
        for ($d = 1; $d <= $n; $d++) $out[] = sprintf('%04d-%02d-%02d', $y, $m, $d);
    }
    return $out;
}


// ---------------------------------------------------------------------------
// CLIMATOLOGIA
// ---------------------------------------------------------------------------
function spiClimatologia(PDO $pdo_r, DateTime $oggi): array {
    $oggi_str = $oggi->format('Y-m-d');
    $anno     = (int)$oggi->format('Y');
    $mese     = (int)$oggi->format('n');

    $tab = table_name(SPI_TABELLA_BASE);
    $cd  = SPI_COL_DATA; $cm = SPI_COL_MM; $cf = SPI_COL_FLAG;

    // UNA sola lettura dell'intera serie: poi tutto in PHP
    try {
        $stmt = $pdo_r->prepare("SELECT $cd AS d, $cm AS mm, $cf AS f
                                 FROM $tab
                                 WHERE $cd >= :ini AND $cd <= :oggi");
        $stmt->execute([':ini' => SPI_ANNO_MIN . '-01-01', ':oggi' => $oggi_str]);
        $righe = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Errore query SPI: ' . $e->getMessage()];
    }

    // Indicizzazione per data + totali mensili (tutte le righe)
    $giorni  = [];   // 'Y-m-d' => [mm, flag]
    $mensili = [];   // 'Y-m'   => [somma, n]
    foreach ($righe as $r) {
        $d  = substr((string)$r['d'], 0, 10);
        $mm = (float)$r['mm'];
        $giorni[$d] = [$mm, (int)$r['f']];
        $km = substr($d, 0, 7);
        if (!isset($mensili[$km])) $mensili[$km] = [0.0, 0];
        $mensili[$km][0] += $mm;
        $mensili[$km][1]++;
    }
    unset($righe);

    $scale = [];
    foreach (SPI_SCALE as $k) {
        $scale[] = spiScalaClima($k, $anno, $mese, $oggi, $giorni, $mensili);
    }

    // Periodo climatologico complessivo per il footer
    $primi = array_filter(array_column(array_column($scale, 'clima'), 'anno_primo'));
    $ulti  = array_filter(array_column(array_column($scale, 'clima'), 'anno_ultimo'));

    return [
        'success' => true,
        'meta'    => [
            'oggi'          => $oggi_str,
            'copertura_min' => SPI_COPERTURA_MIN,
            'percentili'    => SPI_PERCENTILI,
            'clima_label'   => ($primi && $ulti) ? (min($primi) . "\u{2013}" . max($ulti)) : 'n/d',
        ],
        'scale'   => $scale,
    ];
}

/**
 * Climatologia di una scala: ventaglio giornaliero, adattamenti gamma a T e N,
 * mediana della pioggia residua, striscia per anno solare su due anni.
 */
function spiScalaClima(int $k, int $anno, int $mese, DateTime $oggi,
                       array $giorni, array $mensili): array {
    $mesi_corr = spiMesiFinestra($anno, $mese, $k);
    $date_corr = spiDateFinestra($mesi_corr);
    $N = count($date_corr);
    $T = array_search($oggi->format('Y-m-d'), $date_corr, true) + 1;   // 1..N

    // chiave mese-giorno -> indice (1..N) nella finestra corrente
    $chiavi = array_map(function ($d) { return substr($d, 5); }, $date_corr);

    // ------------------------------------------------------------------
    // SERIE STORICHE CUMULATE
    // L'anno di riferimento e' quello in cui cade il mese finale della
    // finestra. Allineamento per (mese, giorno): un 29/2 assente nell'anno
    // corrente fa confluire la sua pioggia nel giorno successivo; un 29/2
    // assente nell'anno storico ripete la cumulata del 28/2.
    // ------------------------------------------------------------------
    $serie   = [];   // anno => [0..N] cumulate
    $esclusi = [];
    for ($y = SPI_ANNO_MIN; $y <= $anno - 1; $y++) {
        $shift  = $anno - $y;
        $mesi_h = array_map(function ($ym) use ($shift) { return [$ym[0] - $shift, $ym[1]]; }, $mesi_corr);
        $date_h = spiDateFinestra($mesi_h);
        if ($mesi_h[0][0] < SPI_ANNO_MIN) continue;   // finestra prima dell'inizio serie

        $cum = 0.0; $pres = 0;
        $cumK = []; $presK = [];
        foreach ($date_h as $d) {
            if (isset($giorni[$d]) && $giorni[$d][1] === 1) {
                $cum += $giorni[$d][0];
                $pres++;
            }
            $cumK[substr($d, 5)]  = $cum;
            $presK[substr($d, 5)] = $pres;
        }

        $s = [0.0]; $presT = 0; $prec = 0.0; $precP = 0;
        foreach ($chiavi as $i => $md) {
            $v = $cumK[$md]  ?? $prec;
            $p = $presK[$md] ?? $precP;
            $s[] = $v;
            $prec = $v; $precP = $p;
            if ($i + 1 === $T) $presT = $p;
        }

        $cop_tot = $pres  / count($date_h);
        $cop_T   = $presT / max(1, $T);
        if ($cop_tot >= SPI_COPERTURA_MIN && $cop_T >= SPI_COPERTURA_MIN) {
            $serie[$y] = $s;
        } else {
            $esclusi[] = ['anno' => $y, 'copertura' => round(min($cop_tot, $cop_T), 3)];
        }
    }

    // ------------------------------------------------------------------
    // VENTAGLIO: percentili empirici giorno per giorno
    // ------------------------------------------------------------------
    $fan = [];
    foreach (SPI_PERCENTILI as $p) $fan['p' . $p] = [];
    for ($i = 0; $i <= $N; $i++) {
        $col = array_column($serie, $i);
        foreach (SPI_PERCENTILI as $p) {
            $fan['p' . $p][] = $col ? round(spiPercentile($col, $p), 1) : null;
        }
    }

    // Adattamenti gamma a oggi (T) e a fine finestra (N)
    $val_T = array_column($serie, $T);
    $val_N = array_column($serie, $N);
    $incr  = [];
    foreach ($serie as $s) $incr[] = $s[$N] - $s[$T];

    $anni = array_keys($serie);

    return [
        'k'         => $k,
        'label'     => 'SPI-' . $k,
        'finestra'  => SPI_MESI_BREVI[$mesi_corr[0][1]] . "\u{2013}" . SPI_MESI_BREVI[$mese],
        'inizio'    => $date_corr[0],
        'fine'      => $date_corr[$N - 1],
        'mesi'      => array_map(function ($ym) {
                           return ['nome' => SPI_MESI_BREVI[$ym[1]], 'giorni' => spiGiorniMese($ym[0], $ym[1])];
                       }, $mesi_corr),
        'N'         => $N,
        'T'         => $T,
        'giorno_mese' => (int)$oggi->format('j'),
        'giorni_mese' => spiGiorniMese($anno, $mese),
        'fan'       => $fan,
        'fit_T'     => spiGammaFit($val_T),
        'fit_N'     => spiGammaFit($val_N),
        'incr_mediana' => $incr ? round(spiPercentile($incr, 50), 1) : 0.0,
        'clima'     => [
            'anno_primo'  => $anni ? min($anni) : null,
            'anno_ultimo' => $anni ? max($anni) : null,
            'n'           => count($anni),
            'label'       => $anni ? (min($anni) . "\u{2013}" . max($anni)) : 'n/d',
        ],
        'esclusi'   => $esclusi,
    ] + spiStriscia($k, $anno, $mese, $mensili);
}

/**
 * Striscia dei mesi per ANNO SOLARE, due righe da 12 quadratini (gen..dic):
 *   'striscia'      -> anno corrente: mesi chiusi, mese in corso, mesi futuri
 *   'striscia_prec' -> anno precedente, tutti i mesi chiusi
 *   'anno_riga' / 'anno_riga_prec' -> etichette a sinistra
 * Ogni cella porta 'stato': 'chiuso' | 'corrente' | 'futuro'.
 * - 'corrente': il valore lo mette il display (e' l'SPI attuale, sempre fresco)
 * - 'futuro':   nessun valore
 * - 'chiuso' con spi null: dati incompleti
 * Totali di mesi interi: si usano tutte le righe, flag 0 compreso.
 * Riferimento UNICO per entrambe le righe: finestre che terminano negli anni
 * SPI_ANNO_MIN .. anno-1, cosi' i due anni si confrontano con lo stesso metro.
 */
function spiStriscia(int $k, int $anno, int $mese, array $mensili): array {
    // Totale della finestra di k mesi che termina in (y, m); null se incompleta
    $totale = function (int $y, int $m) use ($k, $mensili): ?float {
        $tot = 0.0;
        foreach (spiMesiFinestra($y, $m, $k) as [$yy, $mm]) {
            $km = sprintf('%04d-%02d', $yy, $mm);
            if (!isset($mensili[$km])) return null;
            if ($mensili[$km][1] / spiGiorniMese($yy, $mm) < SPI_COPERTURA_MIN) return null;
            $tot += $mensili[$km][0];
        }
        return $tot;
    };

    // Un adattamento per mese di calendario, condiviso dalle due righe
    $fit_mese = [];
    $cella = function (int $y, int $m, string $stato) use (&$fit_mese, $totale, $anno): array {
        $base = ['anno' => $y, 'mese' => $m, 'nome' => SPI_MESI_BREVI[$m],
                 'stato' => $stato, 'mm' => null, 'spi' => null];
        if ($stato !== 'chiuso') return $base;

        if (!isset($fit_mese[$m])) {
            $rif = [];
            for ($yr = SPI_ANNO_MIN; $yr <= $anno - 1; $yr++) {
                $t = $totale($yr, $m);
                if ($t !== null) $rif[] = $t;
            }
            $fit_mese[$m] = spiGammaFit($rif);
        }
        $t = $totale($y, $m);
        if ($t !== null) {
            $spi = spiDaValore($t, $fit_mese[$m]);
            $base['mm']  = round($t, 1);
            $base['spi'] = $spi === null ? null : round($spi, 2);
        }
        $base['n_rif'] = $fit_mese[$m]['n'];
        return $base;
    };

    $riga = []; $prec = [];
    for ($m = 1; $m <= 12; $m++) {
        $stato  = $m < $mese ? 'chiuso' : ($m === $mese ? 'corrente' : 'futuro');
        $riga[] = $cella($anno, $m, $stato);
        $prec[] = $cella($anno - 1, $m, 'chiuso');
    }

    return [
        'striscia'       => $riga,
        'striscia_prec'  => $prec,
        'anno_riga'      => $anno,
        'anno_riga_prec' => $anno - 1,
    ];
}


// ---------------------------------------------------------------------------
// VALORI CORRENTI
// ---------------------------------------------------------------------------
function spiValoriCorrenti(PDO $pdo_r, DateTime $oggi, array $scale_clima): array {
    $oggi_str = $oggi->format('Y-m-d');
    $tab = table_name(SPI_TABELLA_BASE);
    $cd  = SPI_COL_DATA; $cm = SPI_COL_MM; $cf = SPI_COL_FLAG;

    // la finestra piu' lunga contiene tutte le altre (terminano tutte oggi)
    $inizio = min(array_column($scale_clima, 'inizio'));

    try {
        $stmt = $pdo_r->prepare("SELECT $cd AS d, $cm AS mm
                                 FROM $tab
                                 WHERE $cd >= :ini AND $cd <= :oggi AND $cf = 1");
        $stmt->execute([':ini' => $inizio, ':oggi' => $oggi_str]);
        $pioggia = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $pioggia[substr((string)$r['d'], 0, 10)] = (float)$r['mm'];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Errore query SPI correnti: ' . $e->getMessage()];
    }

    $out = [];
    foreach ($scale_clima as $s) {
        $T = $s['T'];
        $d = new DateTime($s['inizio']);
        $cum = 0.0; $pres = 0; $serie = [0.0];
        for ($i = 1; $i <= $T; $i++) {
            $ds = $d->format('Y-m-d');
            if (isset($pioggia[$ds])) { $cum += $pioggia[$ds]; $pres++; }
            $serie[] = round($cum, 1);
            $d->modify('+1 day');
        }

        $spi   = spiDaValore($cum, $s['fit_T']);
        $proj  = $cum + $s['incr_mediana'];
        $spiE  = spiDaValore($proj, $s['fit_N']);
        $p50N  = $s['fan']['p50'][$s['N']];
        $p25N  = $s['fan']['p25'][$s['N']];

        $out[] = [
            'mm'          => round($cum, 1),
            'serie'       => $serie,
            'copertura'   => round($pres / max(1, $T), 3),
            'spi'         => $spi === null ? null : round($spi, 2),
            'percentile'  => $spi === null ? null : round(spiPhi($spi) * 100, 1),
            'proj_mm'     => round($proj, 1),
            'proj_spi'    => $spiE === null ? null : round($spiE, 2),
            'proj_perc'   => $spiE === null ? null : round(spiPhi($spiE) * 100, 1),
            // pioggia che servirebbe nei giorni restanti per chiudere alla mediana / al p25
            'serve_p50'   => $p50N === null ? null : round(max(0, $p50N - $cum), 1),
            'serve_p25'   => $p25N === null ? null : round(max(0, $p25N - $cum), 1),
        ];
    }
    return ['success' => true, 'scale' => $out];
}


// ---------------------------------------------------------------------------
// STATISTICA
// ---------------------------------------------------------------------------
/** Percentile con interpolazione lineare (stesso metodo di api_pluvio_stat_data). */
function spiPercentile(array $v, float $p): float {
    $v = array_values(array_map('floatval', $v));
    sort($v);
    $n = count($v);
    if ($n === 1) return $v[0];
    $pos = ($p / 100) * ($n - 1);
    $lo  = (int)floor($pos);
    $hi  = (int)ceil($pos);
    return $lo === $hi ? $v[$lo] : $v[$lo] + ($pos - $lo) * ($v[$hi] - $v[$lo]);
}

/**
 * Adattamento gamma (stima di Thom) con gestione degli zeri.
 * Restituisce sempre i valori ordinati, per il ripiego empirico.
 */
function spiGammaFit(array $x): array {
    $x = array_values(array_map('floatval', $x));
    sort($x);
    $n = count($x);
    $pos = array_values(array_filter($x, function ($v) { return $v > 0; }));
    $np  = count($pos);
    $fit = ['alpha' => null, 'beta' => null, 'q' => $n ? ($n - $np) / $n : 0, 'n' => $n, 'valori' => $x];

    if ($np < SPI_MIN_ANNI) return $fit;
    $media = array_sum($pos) / $np;
    $mlog  = array_sum(array_map('log', $pos)) / $np;
    $A     = log($media) - $mlog;
    if ($A <= 1e-9) return $fit;

    $alpha = (1 + sqrt(1 + 4 * $A / 3)) / (4 * $A);
    $fit['alpha'] = round($alpha, 6);
    $fit['beta']  = round($media / $alpha, 6);
    return $fit;
}

/** SPI di un valore dato un adattamento. null se non c'e' riferimento. */
function spiDaValore(float $v, array $fit): ?float {
    $n = $fit['n'] ?? 0;
    if ($n === 0) return null;

    if ($fit['alpha'] === null) {
        // Ripiego: posizione empirica (Weibull) del valore inserito fra gli n
        // storici, cioe' rango (sotto + 1) su n + 1 valori: H = (sotto+1)/(n+2).
        // Simmetrica: sopra tutti -> (n+1)/(n+2), sotto tutti -> 1/(n+2).
        $sotto = 0;
        foreach ($fit['valori'] as $x) if ($x < $v) $sotto++;
        $H = ($sotto + 1) / ($n + 2);
    } elseif ($v <= 0) {
        $H = $fit['q'] / 2;   // centro della massa a zero pioggia
    } else {
        $H = $fit['q'] + (1 - $fit['q']) * spiGammaP($fit['alpha'], $v / $fit['beta']);
    }
    $H = min(max($H, 0.00135), 0.99865);   // SPI limitato a circa +/-3
    return spiInvNorm($H);
}

/** Logaritmo della funzione gamma (Lanczos). */
function spiLnGamma(float $x): float {
    $c = [76.18009172947146, -86.50532032941677, 24.01409824083091,
          -1.231739572450155, 0.1208650973866179e-2, -0.5395239384953e-5];
    $y = $x; $t = $x + 5.5;
    $t -= ($x + 0.5) * log($t);
    $s = 1.000000000190015;
    foreach ($c as $cj) $s += $cj / ++$y;
    return -$t + log(2.5066282746310005 * $s / $x);
}

/** Gamma incompleta inferiore regolarizzata P(a, x). */
function spiGammaP(float $a, float $x): float {
    if ($x <= 0) return 0.0;
    $gln = spiLnGamma($a);
    if ($x < $a + 1) {
        $ap = $a; $sum = $del = 1 / $a;
        for ($i = 0; $i < 200; $i++) {
            $ap++; $del *= $x / $ap; $sum += $del;
            if (abs($del) < abs($sum) * 1e-10) break;
        }
        return $sum * exp(-$x + $a * log($x) - $gln);
    }
    $b = $x + 1 - $a; $c = 1 / 1e-300; $d = 1 / $b; $h = $d;
    for ($i = 1; $i <= 200; $i++) {
        $an = -$i * ($i - $a); $b += 2;
        $d = $an * $d + $b; if (abs($d) < 1e-300) $d = 1e-300;
        $c = $b + $an / $c; if (abs($c) < 1e-300) $c = 1e-300;
        $d = 1 / $d; $del = $d * $c; $h *= $del;
        if (abs($del - 1) < 1e-10) break;
    }
    return 1 - exp(-$x + $a * log($x) - $gln) * $h;
}

/** Inversa della normale standard (algoritmo di Acklam). */
function spiInvNorm(float $p): float {
    $a = [-3.969683028665376e+01, 2.209460984245205e+02, -2.759285104469687e+02,
          1.383577518672690e+02, -3.066479806614716e+01, 2.506628277459239e+00];
    $b = [-5.447609879822406e+01, 1.615858368580409e+02, -1.556989798598866e+02,
          6.680131188771972e+01, -1.328068155288572e+01];
    $c = [-7.784894002430293e-03, -3.223964580411365e-01, -2.400758277161838e+00,
          -2.549732539343734e+00, 4.374664141464968e+00, 2.938163982698783e+00];
    $d = [7.784695709041462e-03, 3.224671290700398e-01, 2.445134137142996e+00,
          3.754408661907416e+00];
    $pl = 0.02425;
    if ($p < $pl) {
        $q = sqrt(-2 * log($p));
        return ((((($c[0]*$q+$c[1])*$q+$c[2])*$q+$c[3])*$q+$c[4])*$q+$c[5]) /
               (((($d[0]*$q+$d[1])*$q+$d[2])*$q+$d[3])*$q+1);
    }
    if ($p > 1 - $pl) {
        $q = sqrt(-2 * log(1 - $p));
        return -((((($c[0]*$q+$c[1])*$q+$c[2])*$q+$c[3])*$q+$c[4])*$q+$c[5]) /
                (((($d[0]*$q+$d[1])*$q+$d[2])*$q+$d[3])*$q+1);
    }
    $q = $p - 0.5; $r = $q * $q;
    return ((((($a[0]*$r+$a[1])*$r+$a[2])*$r+$a[3])*$r+$a[4])*$r+$a[5])*$q /
           ((((($b[0]*$r+$b[1])*$r+$b[2])*$r+$b[3])*$r+$b[4])*$r+1);
}

/** Funzione di ripartizione della normale standard. */
function spiPhi(float $z): float {
    $t = 1 / (1 + 0.2316419 * abs($z));
    $d = 0.3989422804014327 * exp(-$z * $z / 2);
    $p = $d * $t * (0.319381530 + $t * (-0.356563782 + $t * (1.781477937 + $t * (-1.821255978 + $t * 1.330274429))));
    return $z > 0 ? 1 - $p : $p;
}


// ---------------------------------------------------------------------------
// CACHE (stessa tabella di api_pluvio_stat_data.php, chiave 'spi|vN')
// ---------------------------------------------------------------------------
function spiCacheChiave(): string {
    return 'spi|v' . SPI_CACHE_VERSIONE;
}

function spiCacheLeggi(string $data_rif): ?array {
    $pdo_r = spiPdoLettura();
    if (!$pdo_r) return null;
    try {
        $tab  = table_name(SPI_CACHE_BASE);
        $stmt = $pdo_r->prepare("SELECT payload FROM $tab WHERE data_rif = :d AND modo = :m LIMIT 1");
        $stmt->execute([':d' => $data_rif, ':m' => spiCacheChiave()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $dec = json_decode($row['payload'], true);
        return (is_array($dec) && !empty($dec['success'])) ? $dec : null;
    } catch (PDOException $e) {
        return null;
    }
}

function spiCacheScrivi(string $data_rif, array $payload): void {
    $pdo_w = spiPdoScrittura();
    if (!$pdo_w) return;
    try {
        $tab = table_name(SPI_CACHE_BASE);
        $pdo_w->exec("CREATE TABLE IF NOT EXISTS $tab (
                        data_rif  DATE        NOT NULL,
                        modo      VARCHAR(24) NOT NULL,
                        payload   MEDIUMTEXT  NOT NULL,
                        creato_il TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (data_rif, modo)
                      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmt = $pdo_w->prepare("REPLACE INTO $tab (data_rif, modo, payload) VALUES (:d, :m, :p)");
        $stmt->execute([':d' => $data_rif, ':m' => spiCacheChiave(),
                        ':p' => json_encode($payload, JSON_UNESCAPED_UNICODE)]);
        $del = $pdo_w->prepare("DELETE FROM $tab WHERE data_rif < DATE_SUB(:d, INTERVAL 7 DAY)");
        $del->execute([':d' => $data_rif]);
    } catch (PDOException $e) {
        // cache best-effort
    }
} 