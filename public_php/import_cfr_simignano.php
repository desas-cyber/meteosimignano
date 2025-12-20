<?php
/**
 * Scraper completo PHP+Node.js per dati pluviometrici Simignano
 * Setup:
 * 1. Installa Node.js: apt-get install nodejs npm
 * 2. Nella stessa cartella di questo file, esegui: npm install puppeteer
 * 3. Esegui: php pluvio_scraper.php
 * 
 * Cronjob:
 * *15 * * * * /usr/bin/php /percorso/pluvio_scraper.php >> /percorso/log.txt 2>&1
 */

$base_dir = __DIR__;
$output_file = $base_dir . '/dati_simignano.json';
$log_file = $base_dir . '/pluvio.log';
$node_script_file = $base_dir . '/scraper_node.js';

function log_msg($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] $message\n";
    file_put_contents($log_file, $log, FILE_APPEND);
    echo $log;
}

log_msg("=== INIZIO SCRAPING ===");

// Crea lo script Node.js se non esiste
if (!file_exists($node_script_file)) {
    log_msg("Creazione script Node.js...");
    
    $node_script = <<<'NODEJS'
const puppeteer = require('puppeteer');
const fs = require('fs');

(async () => {
    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    const page = await browser.newPage();
    await page.goto('https://cfr.toscana.it/monitoraggio/stazioni.php?type=pluvio', {
        waitUntil: 'networkidle0',
        timeout: 30000
    });
    
    await page.waitForSelector('table');
    
    // Usa setTimeout invece di waitForTimeout
    await new Promise(resolve => setTimeout(resolve, 3000));
    
    const dati = await page.evaluate(() => {
        const risultati = [];
        const rows = document.querySelectorAll('table tr');
        
        rows.forEach((row, index) => {
            if (index === 0) return;
            const cells = row.querySelectorAll('td');
            if (cells.length === 0) return;
            
            const raw = Array.from(cells).map(c => c.textContent.trim());
            const testo = raw.join(' ').toLowerCase();
            
            if (testo.includes('simignano')) {
                risultati.push({
                    nome_stazione: raw[1] || '',
                    precipitazioni_1h: raw[5] || '',
                    precipitazioni_6h: raw[6] || '',
                    precipitazioni_12h: raw[7] || '',
                    precipitazioni_24h: raw[8] || '',
                    ultimi_dati: raw[12] || ''
                });
            }
        });
        
        return risultati;
    });
    
    await browser.close();
    
    const output = {
        timestamp: new Date().toISOString().replace('T', ' ').substring(0, 19),
        data_aggiornamento: dati[0]?.ultimi_dati || '',
        dati: dati
    };
    
    fs.writeFileSync('dati_simignano.json', JSON.stringify(output, null, 2));
    console.log(JSON.stringify(output));
})();
NODEJS;
    
    file_put_contents($node_script_file, $node_script);
    log_msg("Script Node.js creato");
}

// Verifica Node.js in diverse posizioni
$possible_node_paths = [
    '/usr/local/bin/node',
    '/usr/bin/node',
    '/opt/homebrew/bin/node',  // Mac M1/M2
    '/usr/local/opt/node/bin/node',
    exec('which node 2>/dev/null')
];

$node_path = null;
foreach ($possible_node_paths as $path) {
    $path = trim($path);
    if (!empty($path) && file_exists($path) && is_executable($path)) {
        $node_path = $path;
        break;
    }
}

if (!$node_path) {
    log_msg("ERRORE: Node.js non trovato!");
    log_msg("Prova a eseguire da terminale: which node");
    log_msg("Poi modifica lo script con il percorso completo");
    
    // Prova a trovarlo comunque
    exec('node --version 2>&1', $test_output, $test_ret);
    if ($test_ret === 0) {
        log_msg("Node.js è installato ma PHP non lo trova nel PATH");
        log_msg("Output: " . implode("\n", $test_output));
    }
    exit(1);
}
log_msg("Node.js trovato: $node_path");

// Verifica Puppeteer in diverse posizioni
$possible_puppeteer_paths = [
    $base_dir . '/node_modules/puppeteer/package.json',
    dirname($base_dir) . '/node_modules/puppeteer/package.json',
    dirname(dirname($base_dir)) . '/node_modules/puppeteer/package.json'
];

$package_json = null;
foreach ($possible_puppeteer_paths as $path) {
    if (file_exists($path)) {
        $package_json = $path;
        break;
    }
}

if (!$package_json) {
    log_msg("ERRORE: Puppeteer non installato!");
    log_msg("Cartella corrente: $base_dir");
    log_msg("Esegui: cd $base_dir && npm install puppeteer");
    exit(1);
}
log_msg("Puppeteer installato in: " . dirname(dirname($package_json)));

// Esegui lo scraper
log_msg("Esecuzione scraper...");
$cmd = "cd $base_dir && $node_path scraper_node.js 2>&1";
exec($cmd, $output, $return);

if ($return !== 0) {
    log_msg("ERRORE durante l'esecuzione dello scraper");
    foreach ($output as $line) {
        log_msg("  $line");
    }
    exit(1);
}

// Verifica output
if (!file_exists($output_file)) {
    log_msg("ERRORE: File JSON non creato");
    exit(1);
}

// Leggi i dati
$json = file_get_contents($output_file);
$dati = json_decode($json, true);

if (!$dati || empty($dati['dati'])) {
    log_msg("ERRORE: Dati non validi");
    exit(1);
}

// Log risultati
log_msg("✓ Dati estratti con successo!");
log_msg("Stazione: " . $dati['dati'][0]['nome_stazione']);
log_msg("Ultima rilevazione: " . $dati['data_aggiornamento']);
log_msg("Precipitazioni 1h:  " . $dati['dati'][0]['precipitazioni_1h'] . " mm");
log_msg("Precipitazioni 6h:  " . $dati['dati'][0]['precipitazioni_6h'] . " mm");
log_msg("Precipitazioni 12h: " . $dati['dati'][0]['precipitazioni_12h'] . " mm");
log_msg("Precipitazioni 24h: " . $dati['dati'][0]['precipitazioni_24h'] . " mm");
log_msg("\nArray completo:");
log_msg(json_encode($dati, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Qui puoi salvare in database, inviare notifiche, ecc.
// Es: salva_in_database($dati);

log_msg("=== FINE SCRAPING ===\n");
exit(0);
?>