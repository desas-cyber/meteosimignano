<?php
/*
Script diagnostico per identificare perché i "buchi" non vengono reintegrati
*/

ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set("Europe/Rome");

require_once __DIR__ . '/../../envelop.php';
require_once __DIR__ . '/../../envelop_lettura.php';
require_once __DIR__ . '/../datetime_helper.php';
require_once __DIR__ . '/../env_tables_helper.php';

$table_name = table_name('DB_immagini_36h');
$directory = __DIR__ . "/../FoscamCamera_E8ABFAA799FE/snap";
$threshold_sec = 128400;

echo "<h2>🔍 DIAGNOSTICA BUCHI NEL DB</h2>\n";
echo "<pre>\n";

// 1. Leggi tutti i file dalla directory
echo "=== STEP 1: Lettura file dalla directory ===\n";
$file_list = scandir($directory);
$now = get_time();
$files_vivi = [];
$count_files = 0;

foreach ($file_list as $file) {
    if ($file === '.' || $file === '..') continue;
    
    $path = "$directory/$file";
    if (!is_file($path)) continue;
    
    if (!preg_match('/Schedule_\d{8}-\d{6}\.jpg$/', $file)) {
        continue;
    }
    
    $data_str = substr($file, 9, 15);
    $dt = DateTime::createFromFormat('Ymd-His', $data_str);
    if (!$dt) continue;
    
    $data_ora = $dt->format('Y-m-d H:i:s');
    $timestamp_file = get_strtotime($data_ora);
    $file_age = $now - $timestamp_file;
    
    if ($file_age <= $threshold_sec) {
        $files_vivi[$file] = $data_ora;
        $count_files++;
    }
}

echo "Trovati {$count_files} file vivi nella directory\n\n";

// 2. Leggi tutti i record dal DB
echo "=== STEP 2: Lettura record dal DB ===\n";
$sql = "SELECT FILE, DATA_ORA FROM {$table_name} ORDER BY DATA_ORA";
$stmt = $pdo->query($sql);
$files_nel_db = [];
$count_db = 0;

while ($riga = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $files_nel_db[$riga['FILE']] = $riga['DATA_ORA'];
    $count_db++;
}

echo "Trovati {$count_db} record nel DB\n\n";

// 3. Trova i BUCHI: file che esistono in directory ma NON nel DB
echo "=== STEP 3: Identificazione BUCHI (file in directory ma non in DB) ===\n";
$buchi = [];
foreach ($files_vivi as $filename => $data_ora) {
    if (!isset($files_nel_db[$filename])) {
        $buchi[$filename] = $data_ora;
    }
}

$count_buchi = count($buchi);
echo "Trovati {$count_buchi} BUCHI\n";

if ($count_buchi > 0) {
    echo "\n🔴 LISTA BUCHI (primi 20):\n";
    $i = 0;
    foreach ($buchi as $filename => $data_ora) {
        echo "  - {$filename} → {$data_ora}\n";
        $i++;
        if ($i >= 20) {
            echo "  ... (altri " . ($count_buchi - 20) . " buchi)\n";
            break;
        }
    }
} else {
    echo "✅ Nessun buco trovato!\n";
}

// 4. Trova EXTRA: file nel DB ma non in directory (dovrebbero essere eliminati)
echo "\n=== STEP 4: Identificazione EXTRA (file in DB ma non in directory) ===\n";
$extra = [];
foreach ($files_nel_db as $filename => $data_ora) {
    if (!isset($files_vivi[$filename])) {
        $extra[$filename] = $data_ora;
    }
}

$count_extra = count($extra);
echo "Trovati {$count_extra} file EXTRA nel DB\n";

if ($count_extra > 0) {
    echo "\n⚠️ LISTA EXTRA (primi 10):\n";
    $i = 0;
    foreach ($extra as $filename => $data_ora) {
        echo "  - {$filename} → {$data_ora}\n";
        $i++;
        if ($i >= 10) break;
    }
}

// 5. Verifica temporale dei buchi
if ($count_buchi > 0) {
    echo "\n=== STEP 5: Analisi temporale dei buchi ===\n";
    
    // Converti i buchi in array ordinato per data
    $buchi_ordinati = [];
    foreach ($buchi as $filename => $data_ora) {
        $buchi_ordinati[] = ['file' => $filename, 'data_ora' => $data_ora];
    }
    usort($buchi_ordinati, function($a, $b) {
        return strcmp($a['data_ora'], $b['data_ora']);
    });
    
    echo "Primo buco: {$buchi_ordinati[0]['data_ora']}\n";
    echo "Ultimo buco: {$buchi_ordinati[count($buchi_ordinati)-1]['data_ora']}\n";
    
    // Verifica se sono distribuiti o concentrati
    $ore_coperte = [];
    foreach ($buchi_ordinati as $item) {
        $ora = substr($item['data_ora'], 11, 2);
        $ore_coperte[$ora] = isset($ore_coperte[$ora]) ? $ore_coperte[$ora] + 1 : 1;
    }
    
    echo "\nDistribuzione per ora:\n";
    ksort($ore_coperte);
    foreach ($ore_coperte as $ora => $count) {
        echo "  Ora {$ora}:00 → {$count} buchi\n";
    }
}

// 6. Verifica corrispondenza ESATTA nome per nome
echo "\n=== STEP 6: Verifica corrispondenza ESATTA nomi file ===\n";

// Prendiamo i primi 20 file dalla directory e verifichiamo se esistono nel DB
$sample_dir = array_slice(array_keys($files_vivi), 0, 20, true);
echo "Primi 20 file in DIRECTORY:\n";
foreach ($sample_dir as $filename) {
    $in_db = isset($files_nel_db[$filename]) ? "✅ IN DB" : "❌ NON IN DB";
    echo "  {$filename} → {$in_db}\n";
}

// Prendiamo i primi 20 record dal DB e verifichiamo se esistono in directory
$sample_db = array_slice(array_keys($files_nel_db), 0, 20, true);
echo "\nPrimi 20 file in DB:\n";
foreach ($sample_db as $filename) {
    $in_dir = isset($files_vivi[$filename]) ? "✅ IN DIR" : "❌ NON IN DIR";
    echo "  {$filename} → {$in_dir}\n";
}

// 7. Verifica pattern temporale - cerca buchi temporali nei timestamp
echo "\n=== STEP 7: Verifica BUCHI TEMPORALI (sequenza timestamp) ===\n";

// Ordina tutti i file per data/ora
$tutti_files_ordinati = [];
foreach ($files_nel_db as $filename => $data_ora) {
    $tutti_files_ordinati[] = ['file' => $filename, 'data_ora' => $data_ora, 'fonte' => 'DB'];
}
usort($tutti_files_ordinati, function($a, $b) {
    return strcmp($a['data_ora'], $b['data_ora']);
});

// Cerca gap temporali > 3 minuti (180 secondi)
$buchi_temporali = [];
for ($i = 1; $i < count($tutti_files_ordinati); $i++) {
    $prev = new DateTime($tutti_files_ordinati[$i-1]['data_ora']);
    $curr = new DateTime($tutti_files_ordinati[$i]['data_ora']);
    $diff_seconds = $curr->getTimestamp() - $prev->getTimestamp();
    
    if ($diff_seconds > 180) { // Gap > 3 minuti
        $buchi_temporali[] = [
            'dopo' => $tutti_files_ordinati[$i-1]['data_ora'],
            'prima' => $tutti_files_ordinati[$i]['data_ora'],
            'gap_minuti' => round($diff_seconds / 60, 1)
        ];
    }
}

$count_buchi_temporali = count($buchi_temporali);
echo "Trovati {$count_buchi_temporali} buchi temporali (gap > 3 minuti)\n";

if ($count_buchi_temporali > 0) {
    echo "\n🔴 LISTA BUCHI TEMPORALI:\n";
    foreach ($buchi_temporali as $buco) {
        echo "  Gap di {$buco['gap_minuti']} minuti tra:\n";
        echo "    {$buco['dopo']}\n";
        echo "    {$buco['prima']}\n\n";
    }
}

// 8. Verifica se nella directory esistono file che coprirebbero questi buchi
if ($count_buchi_temporali > 0) {
    echo "=== STEP 8: Verifica file mancanti nei gap temporali ===\n";
    
    foreach ($buchi_temporali as $buco) {
        $dopo = new DateTime($buco['dopo']);
        $prima = new DateTime($buco['prima']);
        
        echo "\n🔍 Cerco file tra {$buco['dopo']} e {$buco['prima']}...\n";
        
        // Cerca file nella directory che cadono in questo gap
        $files_nel_gap = [];
        foreach ($files_vivi as $filename => $data_ora) {
            $dt = new DateTime($data_ora);
            if ($dt > $dopo && $dt < $prima) {
                $files_nel_gap[] = $filename;
            }
        }
        
        if (count($files_nel_gap) > 0) {
            echo "  ❌ Trovati " . count($files_nel_gap) . " file in DIRECTORY ma NON nel DB:\n";
            foreach ($files_nel_gap as $f) {
                echo "    - {$f}\n";
            }
        } else {
            echo "  ✅ Nessun file disponibile per questo gap\n";
        }
    }
}

echo "\n=== RIEPILOGO ===\n";
echo "File in directory: {$count_files}\n";
echo "Record in DB: {$count_db}\n";
echo "Differenza: " . ($count_files - $count_db) . "\n";
echo "BUCHI nomenclatura: {$count_buchi}\n";
echo "BUCHI temporali: {$count_buchi_temporali}\n";
echo "EXTRA da eliminare: {$count_extra}\n";

echo "</pre>\n";
?>