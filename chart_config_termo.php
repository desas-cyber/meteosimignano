<?php
/**
 * CONFIGURAZIONE GRAFICO TERMO-IGROMETRICO
 * =========================================
 * 
 * UNICA FONTE DI VERITÀ per il grafico termo-igrometrico.
 * 
 * Viene letto da:
 *   - grafici_termo_plotly.php   (frontend: genera HTML + inietta nel JS)
 *   - api_grafico_termo_plotly.php (backend: valida preset, costruisce traces)
 * 
 * Per creare un nuovo grafico: copia questo file, cambia i valori,
 * e punta il frontend/backend al nuovo file di config.
 */

return [

    // ─── IDENTITÀ DEL GRAFICO ───────────────────────────────────────
    'id'       => 'termo',
    'title'    => 'Grafico Termo-Igrometrico',
    'table'    => 'dati_meteo_simignano',
    'endpoint' => 'api/api_grafico_termo_plotly.php',

    // ─── PRESET TEMPORALI ────────────────────────────────────────────
    // key:          valore query string (?range=24h)
    // label:        testo sul bottone
    // sql_interval: stringa per strtotime() (calcola data inizio)
    // default:      true = attivo al primo caricamento (uno solo!)
    'presets' => [
        ['key' => '24h', 'label' => '24h', 'sql_interval' => '-24 hours', 'default' => true],
        ['key' => '7d',  'label' => '7d',  'sql_interval' => '-7 days',   'default' => false],
        ['key' => '30d', 'label' => '30d', 'sql_interval' => '-30 days',  'default' => false],
    ],

    // ─── ASSE Y SINISTRO (metrica primaria) ──────────────────────────
    'yaxis_left' => [
        'metric'        => 'temperatura',
        'db_column'     => 'temperatura_C',
        'name'          => 'Temperatura',
        'unit'          => '°C',
        'color'         => '#000000',
        'range_padding' => 8,
        'zeroline'      => true,
        'zeroline_color'=> '#FF00FF',

        // Traces aggiuntive sulla stessa scala Y
        'extra_traces' => [
            [
                'key'    => 'dewpoint',
                'name'   => 'Dew Point',
                'db_column' => 'dew_point_C',
                'color'  => '#feca57',
                'unit'   => '°C',
                'segmented' => true,
                'segment_thresholds' => [
                    ['max' => 10, 'color' => '#808080'],
                    ['max' => 20, 'color' => '#27ae60'],
                    ['max' => 24, 'color' => '#f39c12'],
                    ['max' => 99, 'color' => '#e74c3c'],
                ],
            ],
            [
                'key'      => 'media_periodo',
                'name'     => 'Media Periodo',
                'color'    => '#ff6b35',
                'dash'     => 'dot',
                'computed' => 'avg',
            ],
            [
                'key'      => 'media_max_7gg',
                'name'     => 'Media Max 7gg',
                'color'    => '#e74c3c',
                'dash'     => 'dash',
                'computed' => 'avg_daily_max_7d',
            ],
            [
                'key'      => 'media_min_7gg',
                'name'     => 'Media Min 7gg',
                'color'    => '#3498db',
                'dash'     => 'dash',
                'computed' => 'avg_daily_min_7d',
            ],
        ],
    ],

    // ─── ASSE Y DESTRO (metriche intercambiabili, mutua esclusione) ──
    'yaxis_right' => [
        [
            'key'             => 'umidita',
            'name'            => 'Umidita',
            'db_column'       => 'umidita_RH',
            'unit'            => '%',
            'color'           => '#0000FF',
            'range'           => [0, 100],
            'tick_step'       => 10,
            'title'           => 'Umidità (%)',
            'title_mobile'    => 'Umid(%)',
            'default_visible' => true,
        ],
        [
            'key'             => 'pressione',
            'name'            => 'Pressione',
            'db_column'       => 'pressione_hPa',
            'unit'            => 'hPa',
            'color'           => '#05662eff',
            'range'           => 'auto',
            'range_padding'   => 5,
            'range_clamp'     => [980, 1050],
            'tick_step'       => 5,
            'title'           => 'Pressione (hPa)',
            'title_mobile'    => 'Press(hPa)',
            'default_visible' => false,
        ],
        [
            'key'             => 'dirvento',
            'name'            => 'Dir. Vento',
            'db_column'       => 'direzione_vento_deg',
            'unit'            => '°',
            'color'           => '#ff8c00',
            'range'           => [0, 360],
            'fixed_range'     => true,
            'tick_mode'       => 'compass',
            'title'           => 'Direzione',
            'title_mobile'    => 'Dir',
            'default_visible' => false,
            'mode'            => 'markers',
            'marker_size'     => 5,
            'color_by'        => 'vento_kmh',
            'color_scale'     => [
                ['max' =>   5, 'color' => '#b0b0b0', 'label' => 'Calmo',           'range_label' => '0-5 km/h'],
                ['max' =>  10, 'color' => '#87ceeb', 'label' => 'Brezza leggera',  'range_label' => '6-10 km/h'],
                ['max' =>  15, 'color' => '#4169e1', 'label' => 'Brezza moderata', 'range_label' => '11-15 km/h'],
                ['max' =>  25, 'color' => '#00008b', 'label' => 'Brezza tesa',     'range_label' => '16-25 km/h'],
                ['max' => 999, 'color' => '#ff00ff', 'label' => 'Vento forte',     'range_label' => '>25 km/h'],
            ],
        ],
    ],

    // ─── LEGENDA (ordine di visualizzazione) ─────────────────────────
    // name: deve corrispondere al "name" della trace nell'API
    'legend' => [
        ['name' => 'Temperatura',   'label' => 'Temperatura',    'label_mobile' => 'Temp',    'color' => '#000000'],
        ['name' => 'Umidita',       'label' => 'Umidità',        'label_mobile' => 'Umid',    'color' => '#0000FF'],
        ['name' => 'Pressione',     'label' => 'Pressione',      'label_mobile' => 'Press',   'color' => '#27ae60'],
        ['name' => 'Dir. Vento',    'label' => 'Dir. Vento',     'label_mobile' => 'Vento',   'color' => '#ff8c00', 'markers' => true],
        ['name' => 'Dew Point',     'label' => 'Dew Point',      'label_mobile' => 'DP',      'color' => 'gradient'],
        ['name' => 'Media Periodo', 'label' => 'Media Periodo',  'label_mobile' => 'Media',   'color' => '#ff6b35', 'dashed' => 'dot'],
        ['name' => 'Media Max 7gg', 'label' => '*Media Max 7gg', 'label_mobile' => '*Max7gg', 'color' => '#e74c3c', 'dashed' => true],
        ['name' => 'Media Min 7gg', 'label' => '*Media Min 7gg', 'label_mobile' => '*Min7gg', 'color' => '#3498db', 'dashed' => true],
    ],
];