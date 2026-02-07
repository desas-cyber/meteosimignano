<?php
/**
 * Visualizzatore dati pluviometrici CFR Simignano
 */
 
 ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../envelop.php';
require_once __DIR__ . '/env_tables_helper.php';

$TABLE = table_name('precipitazioni_cfr');

// Query ultimi 50 record
$sql = "SELECT * FROM $TABLE ORDER BY data_import DESC LIMIT 10";
$stmt = $pdo->query($sql);
$dati = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ultimo aggiornamento
$ultimo = $dati[0] ?? null;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dati Pluviometrici Simignano - CFR Toscana</title>
    <link rel="stylesheet" href="header_shared.css">
    <style>
        /* ====================================================================
           STILI GENERALI (identici a index.php)
           ==================================================================== */
        
        body {
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0;
            padding: 0;
        }
        
        
        
        
/* ====================================================================
   VOCI DEL SOTTO MENU - IN ALTO A DX NELLA PAGINA
   ==================================================================== */

/* Container del menu */
.header-menu-container {
    position: relative;
}

/* Pulsante menu */
.menu-toggle {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
}

/* Sottomenu */
.submenu {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 10px;
    background: white;
    border: 2px solid #333;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    min-width: max-content;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s ease;
    z-index: 1000;
}

/* Sottomenu visibile */
.submenu.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

/* Voci del sottomenu */
.submenu-item {
    display: block;
    padding: 12px 16px;
    color: #333;
    text-decoration: none;
    font-size: 14px;
    text-align: left;
    transition: background-color 0.2s ease;
    border-bottom: 1px solid #ddd;
}

.submenu-item:last-child {
    border-bottom: none;
}

.submenu-item:hover {
    background-color: #f0f0f0;
}

.submenu-item:first-child {
    border-radius: 6px 6px 0 0;
}

.submenu-item:last-child {
    border-radius: 0 0 6px 6px;
}


   .submenu-item {
    display: block;
    padding: 12px 20px;
    color: #333;
    text-decoration: none;
    font-size: 14px;
    transition: background-color 0.2s ease;
    border-bottom: 1px solid #f0f0f0;
}

.submenu-item:last-child {
    border-bottom: none;
}

.submenu-item:hover {
    background-color: #f8f8f8;
}

.submenu-item:first-child {
    border-radius: 8px 8px 0 0;
}

.submenu-item:last-child {
    border-radius: 0 0 8px 8px;
}


/*==========================
**GESTIONE CONTENUTO**
/*==========================*/

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
            
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .stats {
    display: grid;
    gap: 16px;                          /* un po' più arioso */
    margin-bottom: 30px;
}

/* Regola base (desktop largo): 4 colonne, ma con larghezza minima ragionevole */
.stats {
    grid-template-columns: repeat(4, minmax(220px, 1fr));
}

/* Su schermi medi (tablet landscape, desktop piccolo) */
@media (max-width: 1100px) {
    .stats {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
}

/* FORZA 4 colonne strette in landscape mobile */
@media (orientation: landscape) and (max-width: 1000px) {
    .stats {
        grid-template-columns: repeat(4, minmax(140px, 1fr));   /* minimo basso per stare in riga */
        gap: 10px;
    }
    
    .stat-card {
        padding: 14px 10px;             /* meno imbottitura interna */
    }
    
    .stat-card h3 {
        font-size: 12.5px;
    }
    
    .stat-card .value {
        font-size: 26px;
    }
    
    .stat-card .unit {
        font-size: 14px;
    }
}

/* Portrait mobile: 1 o 2 colonne */
@media (max-width: 600px) {
    .stats {
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
    }
    
    .stat-card {
        padding: 16px;
    }
    
    .stat-card .value {
        font-size: 28px;
    }
}
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #695df3ff 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
        }
        
        .stat-card .unit {
            font-size: 16px;
            opacity: 0.8;
        }
        
        .info {
            background: #f0f4ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #667eea;
        }
        
        .info strong {
            color: #667eea;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }
        
        thead {
            background: #667eea;
            color: white;
        }
        
        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        tbody tr:hover {
            background: #f5f7ff;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .refresh {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            margin-top: 20px;
            transition: background 0.3s;
        }
        
        .refresh:hover {
            background: #5568d3;
        }
        
        @media (max-width: 768px) {
            
            .sub-title { font-size: 9px; }/*per pagine successive alla home*/
            .container {
                padding: 15px;
            }
            
            h1 {
                font-size: 18px;
            }
            
            table {
                font-size: 12px;
            }
            
            th, td {
                padding: 8px 5px;
            }
            
            .stat-card .value {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <header class="main-header">
        <!-- Icona Grafici (sinistra) -->
        <a href="pluvio.html" class="header-icon left-icon" title="Dati Pluviometro CFR Toscana">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2s7 8 7 12a7 7 0 1 1-14 0c0-4 7-12 7-12z"></path>
            <path d="M16 16l-4 4-4-4"></path>
        </svg>
        <span class="icon-label">Pluvio</span>
    </a>
        
        <!-- Titolo centrale e coordinate -->
         <div class="header-content">
            <h1 class="main-title">MeteoSimignano</h1>
            <h2 class="sub-title">43°17′32.5″N 11°10′01.49″E @ 418m slm</h2>
        </div>

        <!-- Icona Menu/Indice (destra) -->
        <div class="header-menu-container">
            <button class="header-icon right-icon menu-toggle" title="Menu">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
                <span class="icon-label">Menu</span>
            </button>
            
            <div class="submenu">
                <a href="index.php" class="submenu-item">Home</a>
                <a href="\grafici_termo_plotly.php" class="submenu-item">Grafici</a>
                <a href="\belle.php" class="submenu-item">Diario del cielo</a>
            </div>
        </div>
    </header>
    <div class="container">
        <h1>🌧 Dati Pluviometrici Simignano</h1>
        <p class="subtitle">Fonte: Centro Funzionale Regione Toscana</p>
        
        <?php if ($ultimo): ?>
            <div class="stats">
                <div class="stat-card">
                    <h3>Precipitazioni 1h</h3>
                    <div class="value"><?= number_format($ultimo['prec_1h'], 1) ?></div>
                    <div class="unit">mm</div>
                </div>
                <div class="stat-card">
                    <h3>Precipitazioni 6h</h3>
                    <div class="value"><?= number_format($ultimo['prec_6h'], 1) ?></div>
                    <div class="unit">mm</div>
                </div>
                <div class="stat-card">
                    <h3>Precipitazioni 12h</h3>
                    <div class="value"><?= number_format($ultimo['prec_12h'], 1) ?></div>
                    <div class="unit">mm</div>
                </div>
                <div class="stat-card">
                    <h3>Precipitazioni 24h</h3>
                    <div class="value"><?= number_format($ultimo['prec_24h'], 1) ?></div>
                    <div class="unit">mm</div>
                </div>
            </div>
            
            <div class="info">
                <strong>Ultima rilevazione CFR:</strong> <?= htmlspecialchars($ultimo['ultimi_dati']) ?><br>
                <strong>Ultimo import:</strong> <?= date('d/m/Y H:i:s', strtotime($ultimo['data_import'])) ?><br>
                <strong>Stazione:</strong> <?= htmlspecialchars($ultimo['nome_stazione']) ?>
            </div>
        <?php endif; ?>
        
        <h2 style="margin-top: 30px; margin-bottom: 15px; color: #333;">Storico Ultimi 10 Rilevamenti</h2>
        
        <?php if (empty($dati)): ?>
            <div class="no-data">
                <p>ðŸ“­ Nessun dato disponibile</p>
                <p style="font-size: 12px; margin-top: 10px;">Esegui lo script di import per popolare il database</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Data Import</th>
                            <th>Rilevazione CFR</th>
                            <th>1h (mm)</th>
                            <th>6h (mm)</th>
                            <th>12h (mm)</th>
                            <th>24h (mm)</th>
                            <th>Stazione</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dati as $row): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($row['data_import'])) ?></td>
                            <td><?= htmlspecialchars($row['ultimi_dati']) ?></td>
                            <td><?= number_format($row['prec_1h'], 1) ?></td>
                            <td><?= number_format($row['prec_6h'], 1) ?></td>
                            <td><?= number_format($row['prec_12h'], 1) ?></td>
                            <td><?= number_format($row['prec_24h'], 1) ?></td>
                            <td><?= htmlspecialchars($row['nome_stazione']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <a href="?refresh=1" class="refresh">🔄 Aggiorna Pagina</a>
    </div>
    <script>
// Toggle menu
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.querySelector('.menu-toggle');
    const submenu = document.querySelector('.submenu');
    
    menuToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        submenu.classList.toggle('active');
    });
    
    // Chiudi menu cliccando fuori
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.header-menu-container')) {
            submenu.classList.remove('active');
        }
    });
}); 
</script>
</body>
</html>