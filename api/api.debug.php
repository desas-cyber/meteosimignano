<?php
// debug_stat.php - USA E GETTA, cancellalo dopo
require_once __DIR__ . '/api_tabella_stat_data.php';

$data_da_testare = '2025-09-02'; // <-- metti qui la data che risulta mancante nel DB

$r = getStatData($data_da_testare, true);

echo "success: " . var_export($r['success'], true) . "\n";
if ($r['success']) {
    foreach ($r['righe'] as $riga) {
        if (in_array($riga['label'], ['T media', 'Max abs', 'Min abs'])) {
            echo $riga['label'] . " -> raw['oggi'] = " . var_export($riga['raw']['oggi'] ?? 'CHIAVE ASSENTE', true) . "\n";
        }
    }
} else {
    echo "errore: " . $r['error'] . "\n";
}