meteosimignano.it
    sito di raccolta/analisi dati meteo e visualizzazione di immagini di webcam relative ai dati raccolti


Struttura

public_html
|
|
|--- index.php 
|--- tabella_home_display.php (visualizzazione dati meteo in tabella con varie funzionalità)
|--- astro_helper.php (esegue i calcoli ogni volta che si visualizza tabella_home per alba.tarmomto-luna-radianza)
|--- belle.php (pagina dedicata a selezione di immagini)
|--- aggiorna_dati_meteo.php (aggiorna iframe per il js)
|--- aggiorna_dati_meteo.js (ricarica periodicamnte index.php)
|--- camera_config.php (configurazione esterna per adattare il sito a dverse telcamere)
|--- galleria-lightbox.js (gestisce il lightbox e la galleria in index.php)
|--- galleria-lightbox.css
|--- aggiorna_galleria.js (aggiorna la galleria in index.php)
|--- aggorna_galleria.php (fornisce i dati al js-json con due array, uno per galleria, uno per slow motion)
|--- aggiorna_cartella_immagini.php (aggiorna la cartella immagini con i dati proveniente dal db per la galleria in index.php)
|---date_time_helper.php (gestisce le impostaioni del tempo tra test e prod)
|--- env_tables_helper.php (alterna l'utilizzo dell tabelle test o no a secondia che siamo in test o prod)
|--- meteobridge (interfaccia con stazione meteo che invia i dati in formato get) ---- OBS-------
|           |--- tabella_home.php (legge i dati dale get di meteobridge per la tabela in index.php)-obs
|           |--- tabella_mete_DB_simignano.php (aggiorna il db da chiamata get di meteobridge)
|           |--- dati_temperatura.txt (stringa di servizio scritta da tabella_home.php)-obs
|
|--- api
|     |---- ecowitt_receiver.php (riceve chiamata POST da interfaccia GW 3000 con i dati meteo, li elabora ed invia al DB dati_meteo_simignano)
|     |---- api_grafici_termo_plotly.php (back end della parte grafica -> invia a grafici_termo_plotly.php)
|    |-----atabella_home_data.php (gestisce ed elabora i dati solo da mysql che passa al visualizzatore tabella_home_display.php) 
|
|
|
|--- public_php (file cronjob)
|           |--- aggiorna_DB_belle.php (aggiorna il db cdi belle incrociando i dati con DB_dati_meteo_simignano  e immagini salvate in belle)
|           |--- copia_in_belle_alba-tramonto.php (copi ogni giorno l foto di alba e tramonto)
|           |--- elimina_magg_24h.php (aggiorna la cartella con le immagini mantenendo sempre le ultime 36h e aggiornando il DB dedicato indicizzando anche alba e tramonto)
|           |--- aggiorna_DB_pluvio.php (aggiorna il db pluvio necessario ai dati di tabella_home, uindi db precipitazioni cfr
|                giornaliero pluvio, record mensili)
|
|
|--- belle (cartella immagini belle)
|
|--- FoscamCamera_E8ABFAA799FE
|                |
|                |---snap (cartella immagine con le ultime 36 h, gestita da elimina_mag_24h)
|
|--------- sql
|           |--- aggiorna_radianza_DB_simignano.php (effettua l'integrale dei valori di radianza raccolti nel db con
|               l'interpolazione dei trapezi, verificato ottima nel caso di gap temporali)
|           |--- val_sett_DB.php
|         