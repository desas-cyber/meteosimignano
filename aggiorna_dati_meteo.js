/**
 * =============================================================================
 *  METEO IFRAME AUTO-REFRESH
 * =============================================================================
 *
 *  Scopo
 *  -----
 *  Ricarica periodicamente l'iframe della tabella meteo, forzando il browser
 *  a non usare la cache (aggiunge un timestamp alla query string).
 *
 *  Caratteristiche
 *  ---------------
 *  - Refresh iniziale a DOM pronto
 *  - Refresh automatico ogni N minuti (config)
 *  - Refresh quando la tab torna visibile (con soglia anti-spam)
 *  - Cache busting con parametro `?t=<epoch>`
 *  - API di controllo/esame su window.meteoIframe
 *
 *  HTML atteso (uno dei due è sufficiente)
 *  --------------------------------------
 *    <iframe id="tabella-meteo-iframe"
 *            src="tabella_home_display.php"
 *            data-src="tabella_home_display.php"></iframe>
 *    <!-- oppure -->
 *    <div class="tabella-meteo">
 *      <iframe src="tabella_home_display.php"></iframe>
 *    </div>
 *
 *  Nota
 *  ----
 *  Se è presente l'attributo data-src, viene usato come base URL; altrimenti
 *  si ricava da `iframe.src` (senza querystring).
 */

/* ============================================================================
 *  CONFIGURAZIONE
 * ========================================================================== */

/** Selettore per trovare l'iframe (id esplicito o fallback nel container) */
const METEO_IFRAME_SELECTOR = '#tabella-meteo-iframe, .tabella-meteo iframe';

/** Intervallo di refresh automatico (ms) — default 5 minuti */
const METEO_REFRESH_INTERVAL_MS = 5 * 60 * 1000;

/** Soglia minima fra refresh quando la tab torna visibile (ms) */
const VISIBLE_REFRESH_MIN_GAP_MS = 30 * 1000; // 30 secondi


/* ============================================================================
 *  STATO INTERNO (non modificare dall'esterno)
 * ========================================================================== */

let _intervalId = null;
let _lastRefreshAt = 0;


/* ============================================================================
 *  HELPER
 * ========================================================================== */

/** Trova l'iframe meteo nel DOM (o null se assente). */
function getMeteoIframe() {
  return document.querySelector(METEO_IFRAME_SELECTOR);
}

/** Restituisce l'URL base (senza query) da usare per il reload. */
function getBaseSrc(iframe) {
  const attr = iframe.getAttribute('data-src');
  const current = iframe.src || '';
  const base = (attr && attr.trim()) ? attr.trim() : current.split('?')[0];
  return base;
}

/** Ritorna true se è passato abbastanza tempo dall’ultimo refresh. */
function isPastMinGap(now) {
  return now - _lastRefreshAt >= VISIBLE_REFRESH_MIN_GAP_MS;
}


/* ============================================================================
 *  API PRINCIPALI
 * ========================================================================== */

/**
 * Ricarica l'iframe meteo forzando un cache busting (?t=epoch).
 * @param {string} [reason='manual'] Motivo del reload (solo per logging).
 */
function reloadIframeMeteo(reason = 'manual') {
  const iframe = getMeteoIframe();
  if (!iframe) {
    console.warn('⚠️ [meteo] Iframe non trovato → reload annullato');
    return;
  }

  const base = getBaseSrc(iframe);
  if (!base) {
    console.warn('⚠️ [meteo] Base URL non determinabile → reload annullato');
    return;
  }

  const url = `${base}?t=${Date.now()}`;
  iframe.src = url;
  _lastRefreshAt = Date.now();

  console.log(`🔄 [meteo] Iframe ricaricato (${reason}) → ${url}`);
}

/** Avvia il refresh periodico (se non già attivo). */
function startMeteoAutoRefresh() {
  if (_intervalId != null) {
    console.log('ℹ️ [meteo] Auto-refresh già attivo');
    return;
  }
  _intervalId = setInterval(reloadIframeMeteo, METEO_REFRESH_INTERVAL_MS);
  console.log(`▶️ [meteo] Auto-refresh avviato ogni ${METEO_REFRESH_INTERVAL_MS / 1000}s`);
}

/** Ferma il refresh periodico (se attivo). */
function stopMeteoAutoRefresh() {
  if (_intervalId != null) {
    clearInterval(_intervalId);
    _intervalId = null;
    console.log('⏹️ [meteo] Auto-refresh fermato');
  }
}


/* ============================================================================
 *  BOOTSTRAP (eventi pagina)
 * ========================================================================== */

/** Refresh iniziale quando il DOM è pronto. */
document.addEventListener('DOMContentLoaded', () => {
  reloadIframeMeteo('dom-loaded');  // primo popolamento
  startMeteoAutoRefresh();          // avvia timer periodico
});

/**
 * Se la pagina torna visibile, esegue un refresh (con soglia anti-spam).
 * Utile se la tab è rimasta inattiva a lungo.
 */
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState !== 'visible') return;
  const now = Date.now();
  if (isPastMinGap(now)) {
    reloadIframeMeteo('tab-visible');
  }
});


/* ============================================================================
 *  API DEBUG PUBBLICA (comoda dalla console)
 *    - window.meteoIframe.reload()         // forza un reload ora
 *    - window.meteoIframe.start()          // avvia auto-refresh
 *    - window.meteoIframe.stop()           // ferma auto-refresh
 *    - window.meteoIframe.isRunning()      // true/false
 *    - window.meteoIframe.lastRefreshAt()  // timestamp ms ultimo refresh
 *    - window.dispatchEvent(new Event('meteo:refresh')) // forza reload via evento
 * ========================================================================== */

window.meteoIframe = {
  reload: reloadIframeMeteo,
  start: startMeteoAutoRefresh,
  stop: stopMeteoAutoRefresh,
  isRunning: () => _intervalId !== null,
  lastRefreshAt: () => _lastRefreshAt
};

/** Event-driven refresh opzionale: dispatcha 'meteo:refresh' per ricaricare. */
window.addEventListener('meteo:refresh', () => reloadIframeMeteo('custom-event'));
