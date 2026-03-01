<?php
/**
 * ============================================================================
 *  classifica_immagini.php — Pagina privata di classificazione
 * ============================================================================
 *
 *  SCOPO:
 *    Mostra le immagini dalla cartella foscam/snap (tabella DB_immagini_36h)
 *    e permette di salvarle nella tabella DB_immagini_belle, con o senza
 *    classificazione (sun_phase, altro) e note con testo + immagini.
 *
 *  ARCHITETTURA NOTE CON IMMAGINI:
 *    Il campo "note" nel DB è TEXT (64KB) e contiene solo HTML leggero
 *    con link alle immagini. Le immagini incollate vengono:
 *      1. Intercettate dal JS al momento del paste
 *      2. Inviate al server come file binario (non base64)
 *      3. Salvate nella cartella note_img/ con nome univoco
 *      4. Sostituite nell'editor con <img src="note_img/abc123.jpg">
 *    
 *    PERCHÉ questo approccio?
 *      - Il DB resta leggero (solo testo + percorsi file, pochi KB)
 *      - Le immagini stanno nel filesystem (dove devono stare)
 *      - TEXT (64KB) è sufficiente per pagine di testo + decine di link img
 *      - Le immagini possono essere grandi (fino a 2MB ciascuna)
 *      - Principio: "store data where it belongs" — file binari sul disco,
 *        metadati strutturati nel DB
 *
 *  GESTIONE POST — Due azioni nello stesso endpoint:
 *    Il POST gestisce due azioni, distinte dal Content-Type della richiesta:
 *      - application/json     → salvataggio classificazione + note (testo HTML)
 *      - multipart/form-data  → upload immagine nota (file binario)
 *    
 *    PERCHÉ nello stesso file? Per condividere autenticazione e configurazione.
 *    In un progetto più grande sarebbero endpoint separati (/api/classify, /api/upload).
 * ============================================================================
 */

/* ───────────────────────────────────────────────────────────────────────────
   SEZIONE 0 — AUTENTICAZIONE (invariata)
   ─────────────────────────────────────────────────────────────────────────── */

session_start();

define('AUTH_TOKEN',    'abc');
define('COOKIE_SECRET', 'abc');
define('REMEMBER_DAYS', 90);

// TIMEOUT SESSIONE: 45 minuti di inattività → disconnessione automatica
define('SESSION_TIMEOUT', 45 * 60); // 2700 secondi

if (!empty($_SESSION['classifica_auth'])) {
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        // Troppo tempo passato → distruggi tutto e nega accesso
        session_destroy();
        header('Location: classifica_immagini.php?token=');
        exit;
    }
    // Aggiorna il timestamp ad ogni richiesta
    $_SESSION['last_activity'] = time();
}



// Gestione token da URL (?token=abc) o da form POST
$token_inviato = null;
if (isset($_POST['token'])) {
    $token_inviato = $_POST['token'];        // viene dal form
} elseif (isset($_GET['token']) && $_GET['token'] !== '') {
    $token_inviato = $_GET['token'];         // viene dall'URL diretto
}

// QUELLO CHE DEVE ESSERE
if ($token_inviato !== null) {
    if (hash_equals(AUTH_TOKEN, $token_inviato)) {
        $_SESSION['classifica_auth'] = true;
        $clean = strtok($_SERVER['REQUEST_URI'], '?');
        header('Location: ' . $clean);
        exit;
    } else {
        $token_error = 'Token non valido. Riprova.';
        $_POST['token'] = '';   // ← svuota il campo
    }
}

if (empty($_SESSION['classifica_auth']) && (isset($_GET['token']) || !empty($token_error))) 
    {
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Accesso — MeteoSimignano</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                background: #1a1a2e;
            }
            .login-box {
                background: #16213e;
                border: 1px solid #0f3460;
                border-radius: 12px;
                padding: 32px 28px;
                width: 100%;
                max-width: 320px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.4);
                text-align: center;
            }
            .login-box h2 {
                color: #eee;
                margin: 0 0 24px;
                font-size: 18px;
            }
            .login-box input[type="text"] {
                width: 100%;
                padding: 10px 14px;
                border-radius: 6px;
                border: 1px solid #555;
                background: #0f3460;
                color: #eee;
                font-size: 15px;
                box-sizing: border-box;
                text-align: center;
                letter-spacing: 2px;
            }
            .login-box input[type="text"]:focus {
                outline: none;
                border-color: #00d2ff;
            }
            .login-box button {
                width: 100%;
                margin-top: 14px;
                padding: 10px;
                border: none;
                border-radius: 6px;
                background: #00d2ff;
                color: #000;
                font-weight: bold;
                font-size: 15px;
                cursor: pointer;
            }
            .login-box button:hover { background: #00b4d8; }
            .error-msg {
                color: #f44336;
                font-size: 13px;
                margin-top: 12px;
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>🔒 Inserisci il token</h2>
            <form method="POST" action="classifica_immagini.php">
                <input
                    type="text"
                    name="token"
                    value="<?php echo htmlspecialchars($_POST['token'] ?? ''); ?>"
                    placeholder="token"
                    autofocus
                    autocomplete="off"
                >
                <?php if (!empty($token_error)): ?>
                    <div class="error-msg"><?php echo htmlspecialchars($token_error); ?></div>
                <?php endif; ?>
                <button type="submit">Entra</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}


if (empty($_SESSION['classifica_auth'])) {
    http_response_code(403); die('Accesso negato. Usa il link con token.');
}

if (isset($_GET['logout'])) {
    session_destroy();
    ?>
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body>
    <script>
        window.close(); // funziona perché la scheda è stata aperta da window.open()
    </script>
    <p style="font-family:Arial; text-align:center; margin-top:40px; color:#666;">
        Sessione terminata. Puoi chiudere questa scheda.
    </p>
    </body>
    </html>
    <?php
    exit;
}

/* ───────────────────────────────────────────────────────────────────────────
   SEZIONE 1 — BOOTSTRAP
   ─────────────────────────────────────────────────────────────────────────── */

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../envelop.php';
require_once __DIR__ . '/env_tables_helper.php';
require_once __DIR__ . '/camera_config.php';

/* ───────────────────────────────────────────────────────────────────────────
   SEZIONE 2 — CONFIGURAZIONE
   ─────────────────────────────────────────────────────────────────────────── */

$table_36h   = table_name('DB_immagini_36h');
$table_belle = table_name('DB_immagini_belle');
$directory   = $CAMERA_CONFIG['directory'];
$webPath     = $CAMERA_CONFIG['src_prefix'];

// Cartella per le immagini delle note
// PRINCIPIO: Separazione dei dati — le immagini delle note stanno in una
//   cartella dedicata, non mescolate con le foto della webcam o le "belle".
define('NOTE_IMG_DIR', __DIR__ . '/note_img');
define('NOTE_IMG_WEB', 'note_img/');           // percorso relativo per il browser
define('NOTE_IMG_MAX_BYTES', 2 * 1024 * 1024); // 2MB per immagine
define('NOTE_TEXT_MAX_BYTES', 65000);           // ~64KB, entro il limite di TEXT

/* ───────────────────────────────────────────────────────────────────────────
   SEZIONE 3 — OPZIONI DI CLASSIFICAZIONE (DRY)
   ─────────────────────────────────────────────────────────────────────────── */

$sun_phase_options = [
    ''  => '— Nessuno —',
    '1' => 'Alba',
    '2' => 'Tramonto',
];

$altro_options = [
    // ── Nuvole ──────────────────────────────────────────────────────────────
    // Il valore 0 è il "padre": record classificati prima dei sottotipi.
    // I valori 10-16 sono i sottotipi specifici.
    ''   => '— Nessuno —',
    '0'  => 'Nuvole (generiche)',
    '10' => '↳ Cirri',
    '11' => '↳ Cirrocumuli / Cirrostrati',
    '12' => '↳ Altocumuli / Altostrati',
    '13' => '↳ Cumuli',
    '14' => '↳ Cumulonembi',
    '15' => '↳ Strati / Stratocumuli',
    '16' => '↳ Nembostrati',
    '17' => '↳ Nebbia',
    // ── Pioggia ─────────────────────────────────────────────────────────────
    '6'  => 'Pioggia',
    // ── Neve ────────────────────────────────────────────────────────────────
    '4'  => 'Neve',
    // ── Altre categorie senza sottotipi ─────────────────────────────────────
    '1'  => 'Luna',
    '2'  => 'Arcobaleno',
    '3'  => 'Aur. boreale',
    '5'  => 'Altro',
];

/* ───────────────────────────────────────────────────────────────────────────
   SEZIONE 4 — GESTIONE POST (AJAX)
   ─────────────────────────────────────────────────────────────────────────
   Due sotto-azioni, distinte dal Content-Type:
   
   A) multipart/form-data → UPLOAD IMMAGINE NOTA
      - Il JS intercetta il paste nel contenteditable
      - Estrae il blob dell'immagine e lo manda come FormData
      - Il server salva il file in note_img/ e risponde con il percorso
      - Il JS sostituisce il base64 temporaneo con il percorso reale
   
   B) application/json → SALVATAGGIO CLASSIFICAZIONE + NOTE
      - Identico a prima, ma le note contengono <img src="note_img/...">
        invece di <img src="data:base64,...">
      - Il campo note nel DB è TEXT (64KB) → sufficiente per HTML con link
   ─────────────────────────────────────────────────────────────────────────── */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    
    // ── A) UPLOAD IMMAGINE NOTA ──────────────────────────────────────
    // Riconosciamo questa azione dalla presenza di $_FILES (FormData)
    // COME: il JS manda un FormData con il file, non un JSON.
    //   PHP popola $_FILES automaticamente per multipart/form-data.
    if (!empty($_FILES['note_image'])) {
        $upload = $_FILES['note_image'];
        
        // Validazione tipo MIME
        // PRINCIPIO: Non fidarsi dell'estensione del file. Controllare
        //   il tipo MIME reale con finfo (analizza i magic bytes del file).
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($upload['tmp_name']);
        
        if (!in_array($mimeType, $allowedTypes)) {
            echo json_encode(['success' => false, 'error' => 'Tipo file non ammesso: ' . $mimeType]);
            exit;
        }
        
        // Validazione dimensione
        if ($upload['size'] > NOTE_IMG_MAX_BYTES) {
            $mb = round($upload['size'] / (1024*1024), 1);
            echo json_encode(['success' => false, 'error' => "Immagine troppo grande ({$mb}MB). Max 2MB."]);
            exit;
        }
        
        // Crea cartella se non esiste
        if (!is_dir(NOTE_IMG_DIR)) mkdir(NOTE_IMG_DIR, 0755, true);
        
        // Nome univoco per evitare collisioni
        // PRINCIPIO: Non usare MAI il nome originale del file dall'utente.
        //   Potrebbe contenere caratteri speciali, path traversal (../../),
        //   o sovrascrivere file esistenti. Generiamo un nome casuale.
        $ext = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/gif' => '.gif', 'image/webp' => '.webp'];
        $fileName = bin2hex(random_bytes(12)) . ($ext[$mimeType] ?? '.jpg');
        $destPath = NOTE_IMG_DIR . '/' . $fileName;
        
        if (!move_uploaded_file($upload['tmp_name'], $destPath)) {
            echo json_encode(['success' => false, 'error' => 'Errore nel salvataggio immagine']);
            exit;
        }
        
        // Risponde con il percorso web dell'immagine
        echo json_encode(['success' => true, 'src' => NOTE_IMG_WEB . $fileName]);
        exit;
    }
    
    // ── B) SALVATAGGIO CLASSIFICAZIONE + NOTE ─────────────────────────
    $input = json_decode(file_get_contents('php://input'), true);
    
    $file          = trim($input['file'] ?? '');
    $sun_phase_val = $input['sun_phase'] ?? '';
    $altro_val     = $input['altro'] ?? '';
    $note_val      = $input['note'] ?? '';
    $sequenza_val  = !empty($input['sequenza']) ? 1 : 0;
    
    if ($file === '') {
        echo json_encode(['success' => false, 'error' => 'Nome file mancante']); exit;
    }
    if (!array_key_exists((string)$sun_phase_val, $sun_phase_options)) {
        echo json_encode(['success' => false, 'error' => 'Valore sun_phase non valido']); exit;
    }
    if (!array_key_exists((string)$altro_val, $altro_options)) {
        echo json_encode(['success' => false, 'error' => 'Valore altro non valido']); exit;
    }
    
    // Validazione note: max ~64KB (limite TEXT di MySQL)
    // Ora le note contengono solo testo + <img src="note_img/..."> (pochi byte),
    // non più base64 (centinaia di KB). 64KB è più che sufficiente.
    if (strlen($note_val) > NOTE_TEXT_MAX_BYTES) {
        $kb = round(strlen($note_val) / 1024);
        echo json_encode(['success' => false, 'error' => "Note troppo grandi ({$kb}KB). Max 64KB."]); exit;
    }
    
    // Sanitizzazione HTML
    $note_clean = strip_tags($note_val, '<p><br><b><i><u><strong><em><img><div><span>');
    $note_db = (trim(strip_tags($note_clean)) === '' && strpos($note_clean, '<img') === false) ? null : $note_clean;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM {$table_36h} WHERE FILE = ? LIMIT 1");
        $stmt->execute([$file]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'File non trovato nel DB 36h']); exit;
        }
        
        // Copia foto nella cartella belle/
        $srcPath  = rtrim($directory, '/') . '/' . $file;
        $destDir  = __DIR__ . '/belle';
        $destPath = $destDir . '/' . $file;
        
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        
        if (!is_file($srcPath)) {
            echo json_encode(['success' => false, 'error' => 'File immagine non trovato sul disco']); exit;
        }
        if (!is_file($destPath)) {
            if (!copy($srcPath, $destPath)) {
                echo json_encode(['success' => false, 'error' => 'Impossibile copiare il file']); exit;
            }
        }
        
        $sp = ($sun_phase_val !== '') ? (int)$sun_phase_val : null;
        $al = ($altro_val !== '')     ? $altro_val          : null;
        
        $sql = "INSERT INTO {$table_belle} 
                    (FILE, DATA_ORA, Temp, HR, P_hPa, vento_kmh, Dir_text, sun_phase, altro, note, sequenza)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    DATA_ORA   = VALUES(DATA_ORA),
                    Temp       = VALUES(Temp),
                    HR         = VALUES(HR),
                    P_hPa      = VALUES(P_hPa),
                    vento_kmh  = VALUES(vento_kmh),
                    Dir_text   = VALUES(Dir_text),
                    sun_phase  = VALUES(sun_phase),
                    altro      = VALUES(altro),
                    note       = VALUES(note),
                    sequenza   = VALUES(sequenza)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $row['FILE'],
            $row['DATA_ORA'] ?? null,
            $row['Temp'] ?? null,
            $row['HR'] ?? null,
            $row['P_hPa'] ?? null,
            $row['vento_kmh'] ?? null,
            $row['Dir_text'] ?? null,
            $sp,
            $al,
            $note_db,
            $sequenza_val,
        ]);
        
        echo json_encode(['success' => true]);
        
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

/* ───────────────────────────────────────────────────────────────────────────
   SEZIONE 5 — LETTURA IMMAGINI (GET)
   ─────────────────────────────────────────────────────────────────────────── */

$limit  = 30;
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

try {
    $stmtC = $pdo->prepare("SELECT COUNT(*) FROM {$table_36h}");
    $stmtC->execute();
    $total = (int)$stmtC->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $limit));
    if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $limit; }
    
    $stmt = $pdo->prepare("SELECT * FROM {$table_36h} ORDER BY DATA_ORA DESC LIMIT {$limit} OFFSET {$offset}");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fileNames = array_column($rows, 'FILE');
    $existingBelle = [];
    if (!empty($fileNames)) {
        $ph = implode(',', array_fill(0, count($fileNames), '?'));
        $stmtB = $pdo->prepare("SELECT FILE, sun_phase, altro, note, sequenza FROM {$table_belle} WHERE FILE IN ({$ph})");
        $stmtB->execute($fileNames);
        foreach ($stmtB->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $existingBelle[$b['FILE']] = $b;
        }
    }
} catch (Throwable $e) {
    die("Errore DB: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classifica Immagini — MeteoSimignano</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 16px; background: #1a1a2e; color: #eee; }
        h1 { text-align: center; margin: 0 0 4px; font-size: clamp(18px, 4vw, 28px); }
        .pager { display: flex; justify-content: center; align-items: center; gap: 12px; margin: 12px 0; flex-wrap: wrap; }
        .pager a, .pager span { padding: 6px 14px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; }
        .pager a { background: #16213e; color: #00d2ff; border: 1px solid #00d2ff44; }
        .pager a:hover { background: #0f3460; }
        .pager .disabled { color: #555; }
        .pager .current { color: #aaa; font-size: 14px; }
        /* SELECT di navigazione pagine nel pager */
        .pager-select {
            padding: 5px 10px;
            border-radius: 6px;
            border: 1px solid #00d2ff44;
            background: #16213e;
            color: #00d2ff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .pager-select:focus { outline: 2px solid #00d2ff; }
        .grid { display: grid; grid-template-columns: 1fr; gap: 16px; max-width: 900px; margin: 0 auto; }
        @media (min-width: 700px) { .grid { grid-template-columns: 1fr 1fr; } }
        .card { background: #16213e; border-radius: 10px; overflow: hidden; border: 2px solid #0f3460; transition: border-color 0.2s; }
        .card.classified { border-color: #4CAF50; }
        .card img.card-photo { width: 100%; display: block; aspect-ratio: 16/9; object-fit: cover; }
        .controls { padding: 10px 12px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .controls .meta { width: 100%; font-size: 12px; color: #aaa; margin-bottom: 4px; }
        .controls label { font-size: 12px; color: #ccc; margin-right: 2px; }
        .controls select { padding: 4px 8px; border-radius: 4px; border: 1px solid #555; background: #0f3460; color: #eee; font-size: 13px; }
        .controls .save-btn { padding: 5px 14px; border: none; border-radius: 5px; background: #00d2ff; color: #000; font-weight: bold; font-size: 13px; cursor: pointer; margin-left: auto; }
        .controls .save-btn:hover { background: #00b4d8; }
        .controls .save-btn.saved { background: #4CAF50; color: #fff; }
        .seq-label { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #ccc; cursor: pointer; white-space: nowrap; }
        .seq-label input[type="checkbox"] { width: 14px; height: 14px; accent-color: #00d2ff; cursor: pointer; }
        .status-msg { font-size: 11px; margin-left: 8px; }
        .status-msg.ok  { color: #4CAF50; }
        .status-msg.err { color: #f44336; }
        .info-bar { text-align: center; color: #888; font-size: 13px; margin: 8px 0 16px; }

        /* === CAMPO NOTE === */
        .note-wrapper { width: 100%; margin-top: 6px; }
        .note-label { display: block; font-size: 11px; color: #aaa; margin-bottom: 4px; }
        .note-editor {
            width: 100%; min-height: 50px; max-height: 200px;
            overflow-y: auto; padding: 8px;
            background: #0a1628; border: 1px solid #555; border-radius: 6px;
            color: #eee; font-size: 13px; line-height: 1.4;
            outline: none; word-wrap: break-word;
        }
        .note-editor:focus { border-color: #00d2ff; }
        .note-editor:empty::before { content: attr(data-placeholder); color: #555; pointer-events: none; }
        .note-editor img { max-width: 100%; height: auto; border-radius: 4px; margin: 4px 0; display: block; }
        .note-status { font-size: 10px; color: #666; text-align: right; margin-top: 2px; }
        .note-status.uploading { color: #00d2ff; }
        .note-status.error { color: #f44336; }
    </style>
</head>
<body>

<h1>Classifica Immagini-Meteosimignano</h1>
<div style="text-align:center; margin-bottom:8px;">
    <a href="?logout=1" style="color:#f44336; font-size:14px; text-decoration:none;"> Logout e Chiudi</a>
</div>

<div class="info-bar">
    <?php echo $total; ?> foto nelle ultime 36h —
    Pagina <?php echo $page; ?>/<?php echo $totalPages; ?>
    <?php if (count($existingBelle) > 0): ?>
        — <strong><?php echo count($existingBelle); ?></strong> già in belle
    <?php endif; ?>
</div>

<div class="pager">
    <?php if ($page > 1): ?>
        <a href="?page=<?php echo $page - 1; ?>">◀ Prec</a>
    <?php else: ?>
        <span class="disabled">◀ Prec</span>
    <?php endif; ?>

    <?php
    /*
     * PERCHÉ un <form method="GET"> e non un semplice link?
     *
     * Un link (<a href="?page=X">) porta a una sola destinazione fissa.
     * Se l'utente vuole scegliere tra 12 pagine, servirebbero 12 link.
     * Il <form method="GET"> + <select> risolve elegantemente il problema:
     * quando viene inviato (submit), il browser costruisce la URL
     * aggiungendo il valore scelto come parametro GET (?page=N)
     * e ci naviga — esattamente come se l'utente avesse cliccato quel link.
     *
     * Il JS si limita ad ascoltare l'evento "change" sul select
     * e a fare submit() automatico, così non serve un bottone "Vai".
     */
    ?>
    <form method="GET" action="" id="pager-form-top" style="display:inline;">
        <select
            class="pager-select"
            name="page"
            onchange="this.form.submit()"
            title="Vai alla pagina..."
        >
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <option
                    value="<?php echo $i; ?>"
                    <?php echo ($i === $page) ? 'selected' : ''; ?>
                >
                    Pag. <?php echo $i; ?> / <?php echo $totalPages; ?>
                </option>
            <?php endfor; ?>
        </select>
    </form>

    <?php if ($page < $totalPages): ?>
        <a href="?page=<?php echo $page + 1; ?>">Succ ▶</a>
    <?php else: ?>
        <span class="disabled">Succ ▶</span>
    <?php endif; ?>
</div>

<div class="grid">
<?php foreach ($rows as $row):
    $file = $row['FILE'] ?? ''; if ($file === '') continue;
    $src = rtrim($webPath, '/') . '/' . ltrim($file, '/');
    $dataOra = 'N/D';
    if (!empty($row['DATA_ORA'])) {
        try { $dataOra = (new DateTime($row['DATA_ORA']))->format('d/m/Y H:i'); } catch (Throwable $e) { $dataOra = $row['DATA_ORA']; }
    }
    $temp = isset($row['Temp']) ? number_format((float)$row['Temp'], 1) . '°C' : 'N/D';
    $existing = $existingBelle[$file] ?? null;
    $curSP   = $existing['sun_phase'] ?? '';
    $curAL   = $existing['altro']     ?? '';
    $curNote = $existing['note']      ?? '';
    $curSeq  = !empty($existing['sequenza']) ? 1 : 0;
    $isCl    = ($existing !== null);
?>
<div class="card <?php echo $isCl ? 'classified' : ''; ?>" data-file="<?php echo htmlspecialchars($file); ?>">
    <img class="card-photo" src="<?php echo htmlspecialchars($src); ?>" alt="<?php echo htmlspecialchars($file); ?>" loading="lazy">
    <div class="controls">
        <div class="meta">📅 <?php echo htmlspecialchars($dataOra); ?> &nbsp;|&nbsp; 🌡 <?php echo htmlspecialchars($temp); ?></div>

        <label>Fase:</label>
        <select class="sel-sun"
                data-original-value="<?php echo htmlspecialchars((string)$curSP); ?>"
                data-original-label="<?php echo htmlspecialchars($sun_phase_options[(string)$curSP] ?? '— Nessuno —'); ?>">
            <?php foreach ($sun_phase_options as $v => $l): ?>
                <option value="<?php echo htmlspecialchars($v); ?>" <?php echo ((string)$curSP === (string)$v) ? 'selected' : ''; ?>><?php echo htmlspecialchars($l); ?></option>
            <?php endforeach; ?>
        </select>

        <label>Altro:</label>
        <select class="sel-altro"
                data-original-value="<?php echo htmlspecialchars((string)$curAL); ?>"
                data-original-label="<?php echo htmlspecialchars($altro_options[(string)$curAL] ?? '— Nessuno —'); ?>">
            <?php foreach ($altro_options as $v => $l): ?>
                <option value="<?php echo htmlspecialchars($v); ?>" <?php echo ((string)$curAL === (string)$v) ? 'selected' : ''; ?>><?php echo htmlspecialchars($l); ?></option>
            <?php endforeach; ?>
        </select>

        <label class="seq-label">
            <input type="checkbox" class="chk-sequenza"
                   data-original-seq="<?php echo $curSeq; ?>"
                   <?php echo $curSeq ? 'checked' : ''; ?>>
            Seq.
        </label>

        <button class="save-btn" onclick="salva(this)" type="button"><?php echo $isCl ? '✓' : 'Salva'; ?></button>
        <span class="status-msg"></span>

        <div class="note-wrapper">
            <label class="note-label">📝 Note (testo + incolla immagini, max 2MB/img):</label>
            <div class="note-editor" contenteditable="true"
                 data-original-note="<?php echo htmlspecialchars($curNote); ?>"
                 data-placeholder="Scrivi qui o incolla immagini..."
            ><?php echo $curNote; ?></div>
            <div class="note-status"></div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<div class="pager" style="margin-top:20px; margin-bottom:30px;">
    <?php if ($page > 1): ?>
        <a href="?page=<?php echo $page - 1; ?>">◀ Prec</a>
    <?php else: ?>
        <span class="disabled">◀ Prec</span>
    <?php endif; ?>

    <form method="GET" action="" id="pager-form-bottom" style="display:inline;">
        <select
            class="pager-select"
            name="page"
            onchange="this.form.submit()"
            title="Vai alla pagina..."
        >
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <option
                    value="<?php echo $i; ?>"
                    <?php echo ($i === $page) ? 'selected' : ''; ?>
                >
                    Pag. <?php echo $i; ?> / <?php echo $totalPages; ?>
                </option>
            <?php endfor; ?>
        </select>
    </form>

    <?php if ($page < $totalPages): ?>
        <a href="?page=<?php echo $page + 1; ?>">Succ ▶</a>
    <?php else: ?>
        <span class="disabled">Succ ▶</span>
    <?php endif; ?>
</div>

<!-- ────────────────────────────────────────────────────────────────────────
     SEZIONE 7 — JAVASCRIPT
     ─────────────────────────────────────────────────────────────────────
     Due responsabilità:
       1. Intercettare il paste di immagini → upload su server → sostituire src
       2. Salvataggio classificazione + note (HTML con link, non base64)
     ──────────────────────────────────────────────────────────────────────── -->
<script>
/**
 * ── INTERCETTAZIONE PASTE IMMAGINI ───────────────────────────────
 * 
 * COME FUNZIONA:
 *   1. L'utente fa Ctrl+V (o incolla da menu) nel contenteditable
 *   2. L'evento "paste" contiene un clipboardData con gli items
 *   3. Se un item è un'immagine (type: image/*), lo estraiamo come File
 *   4. Lo mandiamo al server come FormData (multipart/form-data)
 *   5. Il server lo salva in note_img/ e risponde con il percorso
 *   6. Inseriamo un <img src="note_img/abc123.jpg"> nell'editor
 *
 * PERCHÉ intercettiamo il paste?
 *   Se non lo facessimo, il browser inserirebbe un <img src="data:base64,...">
 *   che peserebbe centinaia di KB. Noi lo sostituiamo con un link leggero.
 *
 * PRINCIPIO: Event Delegation — un solo listener su document per tutti
 *   gli editor, invece di uno per ogni card. Più efficiente e funziona
 *   anche per card aggiunte dinamicamente.
 */
document.addEventListener('paste', function(e) {
    var editor = e.target.closest('.note-editor');
    if (!editor) return;

    var items = (e.clipboardData || e.originalEvent.clipboardData).items;
    if (!items) return;

    for (var i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image/') === 0) {
            e.preventDefault(); // Blocca il comportamento default (base64)

            var file = items[i].getAsFile();
            if (!file) continue;

            // Validazione dimensione lato client
            if (file.size > 2 * 1024 * 1024) {
                var statusEl = editor.parentNode.querySelector('.note-status');
                if (statusEl) {
                    statusEl.textContent = 'Immagine troppo grande (' + (file.size/1024/1024).toFixed(1) + 'MB). Max 2MB.';
                    statusEl.className = 'note-status error';
                }
                return;
            }

            uploadNoteImage(file, editor);
            return; // gestisci solo la prima immagine
        }
    }
});

/**
 * Manda il file immagine al server e inserisce il risultato nell'editor.
 * 
 * COME: usa FormData, che è il modo standard per mandare file via AJAX.
 *   FormData codifica automaticamente il file come multipart/form-data,
 *   lo stesso formato che usa un <input type="file"> in un form HTML.
 *   
 *   Lato server, PHP popola $_FILES['note_image'] con i dati del file.
 */
function uploadNoteImage(file, editor) {
    var statusEl = editor.parentNode.querySelector('.note-status');
    if (statusEl) {
        statusEl.textContent = 'Caricamento immagine...';
        statusEl.className = 'note-status uploading';
    }

    var formData = new FormData();
    formData.append('note_image', file);

    fetch(window.location.pathname, {
        method: 'POST',
        body: formData   // NOTA: niente Content-Type header! fetch lo imposta da solo per FormData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            // Inserisci l'immagine nell'editor con il percorso dal server
            var img = document.createElement('img');
            img.src = data.src;   // es. "note_img/a1b2c3d4e5f6.jpg"
            editor.appendChild(img);
            editor.appendChild(document.createElement('br'));

            if (statusEl) {
                statusEl.textContent = 'Immagine caricata ✓';
                statusEl.className = 'note-status';
                setTimeout(function() { statusEl.textContent = ''; }, 2000);
            }
        } else {
            if (statusEl) {
                statusEl.textContent = 'Errore: ' + (data.error || '?');
                statusEl.className = 'note-status error';
            }
        }
    })
    .catch(function() {
        if (statusEl) {
            statusEl.textContent = 'Errore di rete nel caricamento';
            statusEl.className = 'note-status error';
        }
    });
}

/**
 * ── SALVATAGGIO CLASSIFICAZIONE + NOTE ───────────────────────────
 */
function salva(btn) {
    var card     = btn.closest('.card');
    var file     = card.dataset.file;
    var sunPhase = card.querySelector('.sel-sun').value;
    var altro    = card.querySelector('.sel-altro').value;
    var seqEl    = card.querySelector('.chk-sequenza');
    var sequenza = seqEl ? (seqEl.checked ? 1 : 0) : 0;
    var noteEl   = card.querySelector('.note-editor');
    var noteHtml = noteEl ? noteEl.innerHTML : '';
    var statusEl = card.querySelector('.status-msg');

    // Popup conferma se già in belle
    if (card.classList.contains('classified')) {
        var selSun    = card.querySelector('.sel-sun');
        var selAltro  = card.querySelector('.sel-altro');
        var oldSun    = selSun.dataset.originalLabel;
        var oldAltro  = selAltro.dataset.originalLabel;
        var oldSeq    = seqEl ? (seqEl.dataset.originalSeq === '1') : false;
        var newSun    = selSun.options[selSun.selectedIndex].text;
        var newAltro  = selAltro.options[selAltro.selectedIndex].text;
        var oldNote   = noteEl ? (noteEl.dataset.originalNote || '') : '';
        var noteChanged = (noteHtml !== oldNote);

        var msg = '⚠️ Questa foto è già salvata in belle.\n\n'
                + 'Classificazione attuale:\n'
                + '  • Fase: '      + oldSun   + '\n'
                + '  • Altro: '     + oldAltro + '\n'
                + '  • Sequenza: '  + (oldSeq ? 'sì' : 'no') + '\n'
                + '  • Note: '      + (oldNote ? 'presenti' : 'vuote') + '\n\n'
                + 'Nuova classificazione:\n'
                + '  • Fase: '      + newSun   + '\n'
                + '  • Altro: '     + newAltro + '\n'
                + '  • Sequenza: '  + (sequenza ? 'sì' : 'no') + '\n'
                + '  • Note: '      + (noteHtml.trim() ? (noteChanged ? 'modificate' : 'invariate') : 'vuote') + '\n\n'
                + 'Vuoi sovrascrivere?';

        if (!confirm(msg)) return;
    }

    btn.disabled = true;
    btn.textContent = '...';
    statusEl.textContent = '';

    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ file: file, sun_phase: sunPhase, altro: altro, sequenza: sequenza, note: noteHtml })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        if (data.success) {
            btn.textContent = '✓';
            btn.classList.add('saved');
            card.classList.add('classified');
            statusEl.textContent = 'Salvato';
            statusEl.className = 'status-msg ok';

            var s1 = card.querySelector('.sel-sun');
            var s2 = card.querySelector('.sel-altro');
            s1.dataset.originalValue = s1.value;
            s1.dataset.originalLabel = s1.options[s1.selectedIndex].text;
            s2.dataset.originalValue = s2.value;
            s2.dataset.originalLabel = s2.options[s2.selectedIndex].text;
            // Aggiorna anche lo stato originale della sequenza
            if (seqEl) seqEl.dataset.originalSeq = sequenza ? '1' : '0';
            if (noteEl) noteEl.dataset.originalNote = noteHtml;

            setTimeout(function() { statusEl.textContent = ''; }, 2000);
        } else {
            btn.textContent = 'Salva';
            statusEl.textContent = 'Errore: ' + (data.error || '?');
            statusEl.className = 'status-msg err';
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.textContent = 'Salva';
        statusEl.textContent = 'Errore rete';
        statusEl.className = 'status-msg err';
    });
}
</script>
</body>
</html>