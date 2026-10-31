<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.08, maximum-scale=5.0, user-scalable=yes">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <title>MeteoSimignano - Pluvio</title>
    <link rel="stylesheet" href="galleria-lightbox.css">
    <link rel="stylesheet" href="header_shared.css">
    
    <style>
        /* ====================================================================
           STILI GENERALI
           ==================================================================== */
        
        body {
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0;
            padding: 0;
        }
        /** ==============================
        *HEADER - FORZATURA PER MOBILE LANDSCAPE
        *  ==============================*/
        @media (orientation: landscape) and (max-height: 480px){
        .main-header {
            
            max-width: 500px !important;
            
        }
        }
        
        
        
        
        /* ====================================================================
           IFRAME PLUVIOMETRO - Contenitore principale
           ==================================================================== */
        
        .main-container {
            position: relative;
            width: 100%;
            max-width: none; /* Rimuove limite per permettere iframe largo */
            overflow-x: auto; /* Abilita scroll orizzontale se necessario */
        }
        
        /* Iframe con grafico pluviometrico dal sito CFR Toscana */
        .main-container iframe {
            width: 1000px; /* Larghezza fissa per mostrare tutto il contenuto */
            height: 600px; /* Altezza fissa */
            border: none;
            display: block;
        }

/* ====================================================================
   RESPONSIVE MOBILE - Ridimensionamento per schermi piccoli
   ==================================================================== */

        @media (max-width: 599px) {
    .main-header {
        width: 95%;
        margin: 0 auto;
    }
    
    .main-container iframe {
        width: 95%;
    }
}

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
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    min-width: 180px;
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


    </style>
</head>

<body>
    <!-- ====================================================================
         HEADER CON TITOLO E ICONE NAVIGAZIONE
         ==================================================================== -->
    
    <header class="main-header">
        <!-- Icona Grafici (sinistra) -->
        <a href='grafici_termo_plotly.php?range=24h&visible=' class="header-icon left-icon" title="Grafici">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="20" x2="18" y2="10"></line>
                <line x1="12" y1="20" x2="12" y2="4"></line>
                <line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
            <span class="icon-label">Grafici</span>
        </a>
        
        <!-- Titolo centrale e coordinate -->
        <div class="header-content">
            <h1 class="main-title">
                <a href="index.php" style="text-decoration: none; color: inherit;">MeteoSimignano</a>
            </h1>
            <h1 class="sub-title sub-title-row">
                <span>43°17′32.5″N 11°10′01.49″E @ 418m slm</span>
                <span id="page-spinner" class="spinner" aria-label="Caricamento in corso"></span>
            </h1>
        </div>
   

    
     <?php include __DIR__ . '/menu_destro.php'; ?>
    </header>

    <!-- ====================================================================
         CONTENUTO PRINCIPALE - Iframe pluviometro CFR Toscana
         ==================================================================== -->
    
    <main>
        <div class="main-container">
            <p style="font-size: 11px; color: #888; text-align: center; margin: 6px 0 0 0;">
    Orario CET (Europa Centrale Solare)
</p>
            <!-- 
                Iframe che incorpora la pagina del pluviometro dal sito CFR Toscana
                Mostra dati in tempo reale delle precipitazioni
            -->
            <iframe src="https://cfr.toscana.it/monitoraggio/dettaglio.php?id=TOS01001379&title=&type=pluvio" 
                    title="Dati Pluviometro CFR Toscana"
                    frameborder="0">
            </iframe>
            
        </div>
    </main> 


</body>


</html>