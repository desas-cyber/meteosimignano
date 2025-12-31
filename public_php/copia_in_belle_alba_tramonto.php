<?php
/**
 * ============================================================================
 * COPIA IMMAGINI ALBA/TRAMONTO IN BELLE (con aggiornamento DB)
 * ============================================================================
 * 
 * SCOPO:
 * ------
 * Copia le foto di alba e tramonto del giorno corrente dalla cartella 36h 
 * alla cartella "belle" e aggiorna il database DB_immagini_belle.
 * 
 * LOGICA:
 * -------
 * 1. Legge dal DB_immagini_36h le foto con sun_phase = 1 (alba) o 2 (tramonto)
 * 2. Per ogni foto trovata:
 *    a) Verifica se il file esiste fisicamente
 *    b) Verifica se il record esiste già in DB_immagini_belle
 *    c) Se esiste e sun_phase è NULL → aggiorna sun_phase
 *    d) Se non esiste → inserisce nuovo record e copia il file
 * 3. Copia il file solo se non esiste già in destinazione
 * 
 * ESECUZIONE:
 * -----------
 * Cronjob giornaliero (es. alle 22:00 per catturare alba e tramonto del giorno)
 * 
 * DIPENDENZE:
 * -----------
 * - datetime_helper.php (per gestione date test/prod)
 * - env_tables_helper.php (per nomi tabelle test/prod)
 * - envelop.php (connessione PDO)
 * 
 * @author MeteoSimignano
 * @version 2.0
 */

// ============================================================================
// CONFIGURAZIONE E DEBUG
// ============================================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================================================================
// CARICAMENTO DIPENDENZE
// ============================================================================

require_once __DIR__ . '/../datetime_helper.php';
require_once __DIR__ . '/../env_tables_helper.php';
require_once __DIR__ . '/../../envelop.php';

// ============================================================================
// CONFIGURAZIONE PERCORSI E TABELLE
// ============================================================================

$cartella_sorgente = __DIR__ . '/../FoscamCamera_E8ABFAA799FE/snap/';
$cartella_destinazione = __DIR__ . '/../belle/';

$table_36h = table_name('DB_immagini_36h');
$table_belle = table_name('DB_immagini_belle');

// ============================================================================
// VALIDAZIONE AMBIENTE
// ============================================================================

// Verifica connessione database
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("❌ ERRORE: Connessione database non disponibile\n");
}

// Verifica esistenza cartelle
if (!is_dir($cartella_sorgente)) {
    die("❌ ERRORE: Cartella sorgente non trovata: $cartella_sorgente\n");
}

if (!is_dir($cartella_destinazione)) {
    die("❌ ERRORE: Cartella destinazione non trovata: $cartella_destinazione\n");
}

// Verifica permessi scrittura
if (!is_writable($cartella_destinazione)) {
    die("❌ ERRORE: Cartella destinazione non scrivibile: $cartella_destinazione\n");
}

// ============================================================================
// FUNZIONI HELPER
// ============================================================================

/**
 * Verifica se un record esiste già in DB_immagini_belle
 * 
 * @param PDO $pdo Connessione database
 * @param string $table Nome tabella
 * @param string $filename Nome file
 * @return array|null Record se esiste, null altrimenti
 */
function recordEsisteInBelle(PDO $pdo, string $table, string $filename): ?array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE FILE = :file LIMIT 1");
        $stmt->execute([':file' => $filename]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    } catch (PDOException $e) {
        echo "⚠️  ERRORE query recordEsisteInBelle: " . $e->getMessage() . "\n";
        return null;
    }
}

/**
 * Inserisce un nuovo record in DB_immagini_belle
 * Nota: DB_immagini_belle usa "sun_phase", mentre DB_immagini_36h usa "alba_tramonto"
 * 
 * @param PDO $pdo Connessione database
 * @param string $table Nome tabella
 * @param array $dati Dati da inserire (con campo 'alba_tramonto' dal DB_36h)
 * @return bool True se successo, false altrimenti
 */
function inserisciRecordBelle(PDO $pdo, string $table, array $dati): bool {
    try {
        $sql = "INSERT INTO {$table} 
                (FILE, DATA_ORA, Temp, HR, P_hPa, vento_kmh, Dir_text, sun_phase) 
                VALUES 
                (:file, :data_ora, :temp, :hr, :p_hpa, :vento_kmh, :dir_text, :sun_phase)";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':file' => $dati['FILE'],
            ':data_ora' => $dati['DATA_ORA'],
            ':temp' => $dati['Temp'],
            ':hr' => $dati['HR'],
            ':p_hpa' => $dati['P_hPa'],
            ':vento_kmh' => $dati['vento_kmh'],
            ':dir_text' => $dati['Dir_text'],
            ':sun_phase' => $dati['alba_tramonto']  // ← MAPPING: alba_tramonto → sun_phase
        ]);
    } catch (PDOException $e) {
        echo "⚠️  ERRORE inserimento record: " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * Aggiorna solo il campo sun_phase di un record esistente
 * 
 * @param PDO $pdo Connessione database
 * @param string $table Nome tabella
 * @param string $filename Nome file
 * @param int $sun_phase Valore sun_phase (1=alba, 2=tramonto)
 * @return bool True se successo, false altrimenti
 */
function aggiornaSunPhase(PDO $pdo, string $table, string $filename, int $sun_phase): bool {
    try {
        $stmt = $pdo->prepare("UPDATE {$table} SET sun_phase = :sun_phase WHERE FILE = :file");
        return $stmt->execute([
            ':sun_phase' => $sun_phase,
            ':file' => $filename
        ]);
    } catch (PDOException $e) {
        echo "⚠️  ERRORE aggiornamento sun_phase: " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * Copia un file dalla sorgente alla destinazione
 * 
 * @param string $sorgente Percorso file sorgente
 * @param string $destinazione Percorso file destinazione
 * @return bool True se successo, false altrimenti
 */
function copiaFile(string $sorgente, string $destinazione): bool {
    try {
        // Verifica che il file sorgente esista
        if (!file_exists($sorgente)) {
            echo "⚠️  File sorgente non trovato: $sorgente\n";
            return false;
        }
        
        // Verifica che il file destinazione NON esista già
        if (file_exists($destinazione)) {
            // File già presente, nessuna azione necessaria
            return true;
        }
        
        // Copia il file
        if (copy($sorgente, $destinazione)) {
            // Imposta gli stessi permessi del file originale
            $perms = fileperms($sorgente);
            chmod($destinazione, $perms);
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        echo "⚠️  ERRORE copia file: " . $e->getMessage() . "\n";
        return false;
    }
}

// ============================================================================
// MAIN LOGIC
// ============================================================================

echo "\n";
echo "════════════════════════════════════════════════════════════════════════\n";
echo "  COPIA ALBA/TRAMONTO → BELLE\n";
echo "════════════════════════════════════════════════════════════════════════\n";
echo "Esecuzione: " . date('Y-m-d H:i:s') . "\n";
echo "Tabella sorgente: $table_36h\n";
echo "Tabella destinazione: $table_belle\n";
echo "────────────────────────────────────────────────────────────────────────\n\n";

// Ottieni la data corrente (rispetta USE_TEST_MODE)
$data_oggi = get_now('Y-m-d');

try {
    // ========================================================================
    // STEP 1: Query foto alba/tramonto del giorno
    // ========================================================================
    
    $sql = "SELECT * FROM {$table_36h} 
            WHERE DATE(DATA_ORA) = :data_oggi 
            AND alba_tramonto IN (1, 2)
            ORDER BY DATA_ORA ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':data_oggi' => $data_oggi]);
    $foto_da_copiare = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totale_foto = count($foto_da_copiare);
    echo "📸 Foto alba/tramonto trovate per $data_oggi: $totale_foto\n\n";
    
    if ($totale_foto === 0) {
        echo "ℹ️  Nessuna foto da processare.\n";
        exit(0);
    }
    
    // ========================================================================
    // STEP 2: Processamento foto
    // ========================================================================
    
    $contatori = [
        'copiate' => 0,
        'aggiornate' => 0,
        'gia_presenti' => 0,
        'errori' => 0
    ];
    
    foreach ($foto_da_copiare as $foto) {
        $filename = $foto['FILE'];
        $alba_tramonto = (int)$foto['alba_tramonto'];  // ← CORREZIONE: usa alba_tramonto
        $tipo = ($alba_tramonto === 1) ? 'Alba 🌅' : 'Tramonto 🌇';
        
        echo "──────────────────────────────────────────────────────────────────\n";
        echo "📷 File: $filename\n";
        echo "   Tipo: $tipo\n";
        echo "   Data/Ora: {$foto['DATA_ORA']}\n";
        
        // --------------------------------------------------------------------
        // STEP 2.1: Verifica esistenza file fisico
        // --------------------------------------------------------------------
        
        $percorso_sorgente = $cartella_sorgente . $filename;
        
        if (!file_exists($percorso_sorgente)) {
            echo "   ⚠️  ERRORE: File sorgente non trovato\n";
            $contatori['errori']++;
            continue;
        }
        
        echo "   ✅ File sorgente: OK\n";
        
        // --------------------------------------------------------------------
        // STEP 2.2: Verifica se record esiste in DB_belle
        // --------------------------------------------------------------------
        
        $record_esistente = recordEsisteInBelle($pdo, $table_belle, $filename);
        
        if ($record_esistente !== null) {
            // Record esiste già
            echo "   📋 Record in DB_belle: ESISTE\n";
            
            $sun_phase_attuale = $record_esistente['sun_phase'];
            echo "   🌓 sun_phase attuale: " . ($sun_phase_attuale ?? 'NULL') . "\n";
            
            // Se sun_phase è NULL → aggiorna
            if ($sun_phase_attuale === null) {
                echo "   🔄 Aggiornamento sun_phase → $alba_tramonto... ";
                
                if (aggiornaSunPhase($pdo, $table_belle, $filename, $alba_tramonto)) {
                    echo "✅\n";
                    $contatori['aggiornate']++;
                } else {
                    echo "❌\n";
                    $contatori['errori']++;
                }
            } else {
                echo "   ℹ️  sun_phase già impostato, nessuna azione\n";
                $contatori['gia_presenti']++;
            }
            
            // Verifica anche il file fisico
            $percorso_destinazione = $cartella_destinazione . $filename;
            if (!file_exists($percorso_destinazione)) {
                echo "   📁 File mancante in /belle, copio... ";
                if (copiaFile($percorso_sorgente, $percorso_destinazione)) {
                    echo "✅\n";
                } else {
                    echo "❌\n";
                }
            }
            
        } else {
            // Record NON esiste → inserisci e copia
            echo "   📋 Record in DB_belle: NON ESISTE\n";
            echo "   ➕ Inserimento nuovo record... ";
            
            if (inserisciRecordBelle($pdo, $table_belle, $foto)) {
                echo "✅\n";
                
                // Ora copia il file
                $percorso_destinazione = $cartella_destinazione . $filename;
                echo "   📁 Copia file... ";
                
                if (copiaFile($percorso_sorgente, $percorso_destinazione)) {
                    echo "✅\n";
                    $contatori['copiate']++;
                } else {
                    echo "❌\n";
                    $contatori['errori']++;
                }
            } else {
                echo "❌\n";
                $contatori['errori']++;
            }
        }
    }
    
    // ========================================================================
    // STEP 3: Riepilogo finale
    // ========================================================================
    
    echo "\n";
    echo "════════════════════════════════════════════════════════════════════════\n";
    echo "  RIEPILOGO\n";
    echo "════════════════════════════════════════════════════════════════════════\n";
    echo "📸 Foto processate: $totale_foto\n";
    echo "➕ Nuove copiate: {$contatori['copiate']}\n";
    echo "🔄 Record aggiornati: {$contatori['aggiornate']}\n";
    echo "✓  Già presenti: {$contatori['gia_presenti']}\n";
    echo "❌ Errori: {$contatori['errori']}\n";
    echo "════════════════════════════════════════════════════════════════════════\n\n";
    
    // Exit code appropriato
    exit($contatori['errori'] > 0 ? 1 : 0);
    
} catch (PDOException $e) {
    echo "\n❌ ERRORE FATALE DATABASE: " . $e->getMessage() . "\n\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ ERRORE FATALE: " . $e->getMessage() . "\n\n";
    exit(1);
}
?>