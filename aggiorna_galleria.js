/* ============================================================================
 *  GALLERIA METEO – Aggiornamento automatico (ES5-compatible)
 * ============================================================================
 *
 *  Cosa fa:
 *  - Scarica periodicamente i record dal backend (JSON)
 *  - Aggiorna immagine principale + overlay data/temperatura
 *  - Ricostruisce la griglia di miniature con overlay centrato (temp/ora/vento/HR/pressione)
 *  - Gestisce click su miniature (apre lightbox se disponibile)
 *
 *  Dipendenze:
 *  - Backend: ENDPOINT_AGGIORNAMENTO → restituisce array di record:
 *      [{ src, data_ora, temp, hr, p_hpa, vento, dir, ... }, ...]
 *  - CSS: classi .overlay-mini, .temp-line, .ora-line, .meta-line, .icon, .icon-outline
 *  - JS esterno: openLightbox(index) e updateNavButtons() se presenti
 *
 *  Note compatibilità:
 *  - Niente optional chaining (?.) / nullish coalescing (??) / let/const / => / template string
 *  - Solo ES5 puro per motori JS legacy (anche embedded in device/webview)
 * ========================================================================== */

/* =============================== Config ================================== */

/** Intervallo aggiornamento (5 minuti) – 59s per ridurre drift */
var AGGIORNAMENTO_INTERVALLO = 5 * 59 * 1000;

/** Endpoint backend che fornisce i dati JSON */
var ENDPOINT_AGGIORNAMENTO = 'aggiorna_galleria.php';


/* ============================== Helpers ================================== */

/** Uguaglianza stretta a numero finito (poly-like) */
function isFiniteNumber(n) {
  return typeof n === 'number' && isFinite(n);
}

/** Converte un valore in numero, oppure null se assente/non numerico */
function numOrNull(v) {
  return (v === null || v === '' || !isFinite(+v)) ? null : (+v);
}

/** Sicuro: ritorna obj[key] se definito, altrimenti null */
function get(obj, key) {
  return (obj && obj[key] !== null) ? obj[key] : null;
}

/** Sicuro: come sopra ma sempre stringa ("" se assente) */
function getStr(obj, key) {
  var v = get(obj, key);
  return (v === null) ? '' : String(v);
}

/** Ritorna il primo campo definito tra quelli passati (altrimenti null) */
function pickFirstDefined(obj, keys) {
  if (!obj) return null;
  for (var i = 0; i < keys.length; i++) {
    if (obj[keys[i]] !== null) return obj[keys[i]];
  }
  return null;
}

/** Converte gradi in direzione bussola (N, NE, …) */
function degToCompass(deg) {
  var dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
  var d = +deg;
  if (!isFinite(d)) return null;
  var i = Math.round(((d % 360) / 22.5)) % 16;
  return dirs[i < 0 ? i + 16 : i];
}

/** Estrae HH:MM da item.data_ora (es. "dd/mm/yyyy HH:MM") */
function estraiOraDaItem(item) {
  var s = getStr(item, 'data_ora');
  var m = s.match(/\b(\d{2}):(\d{2})\b/);
  return m ? (m[1] + ':' + m[2]) : 'N/D';
}

/**
 * Mappa temperatura → classe colore CSS
 *  >35        → temp-red
 *  25..35     → temp-orange
 *  15..24.9   → temp-green
 *   0..14.9   → temp-lightblue
 *  <0         → temp-blue
 *  altrimenti → temp-default
 */
function getTempColorClassJS(temp) {
  var t = parseFloat(temp);
  if (isNaN(t)) return 'temp-default';
  if (t > 35)              return 'temp-red';
  if (t >= 25 && t <= 35)  return 'temp-orange';
  if (t >= 15 && t < 25)   return 'temp-green';
  if (t >= 0 && t < 15)    return 'temp-lightblue';
  if (t < 0)               return 'temp-blue';
  return 'temp-default';
}


/* ===================== Main image: render overlay ======================== */
/**
 * Aggiorna la scritta “Ultima immagine (dir. NO): …” e la temperatura
 * nell’overlay dell’immagine principale.
 * @param {string} nuovaData  es. "09/01/2025 14:30"
 * @param {number|string} temp in °C (arrotondata all’unità)
 */
function renderMainDate(nuovaData, temp) {
  var dateSpan = document.getElementById('date-label');
  var tempSpan = document.getElementById('temp-label');

  if (!dateSpan || !tempSpan) {
    console.error('❌ Mancano #date-label o #temp-label nel DOM');
    return;
  }

  // Data/ora
  dateSpan.textContent = 'Ultima immagine (dir. NO): ' + (nuovaData || 'N/D');

  // Temperatura arrotondata all’unità + colore dinamico
  var t = numOrNull(temp);
  var display = (t === null ? null : parseFloat(t).toFixed(1)); // 1 decimale // ESLint-friendly
  var colorClass = (t === null ? 'temp-default' : getTempColorClassJS(t));

  tempSpan.textContent = (display === null ? 'N/D' : (display + '°C'));
  tempSpan.className = 'temp-data ' + colorClass;
}


/* ==================== Miniatura: costruzione nodo DOM ==================== */
/**
 * Costruisce il DOM per una miniatura (thumb) con overlay centrale.
 * @param {Object} item  record immagine+meteo
 * @param {Number} index indice per lightbox
 * @returns {HTMLElement} wrapper completo pronto da appendere
 */
function createThumbnailNode(item, index) {
  // Wrapper thumb
  var wrap = document.createElement('div');
  wrap.className = 'thumb';

  // Immagine + cache-busting
  var img = document.createElement('img');
  var baseSrc = getStr(item, 'src').trim();
  img.src = baseSrc + '?t=' + Date.now();
  img.alt = 'Immagine webcam';

  // Click → lightbox (se disponibile)
  img.onclick = function () {
    if (typeof openLightbox === 'function') openLightbox(index);
  };

  // --- Parsing valori record ------------------------------------------------
  var tNum     = numOrNull(get(item, 'temp'));
  var tDisplay = (tNum === null ? null : parseFloat(tNum).toFixed(1));

  var ora      = estraiOraDaItem(item);
  var hrVal    = numOrNull(get(item, 'hr'));

  // vento: backend storico "vento" (m/s) oppure "wind_kmh"
  var vMs      = numOrNull(pickFirstDefined(item, ['vento', 'wind_ms']));
  var wKmh     = (vMs !== null) ? Math.round(vMs * 3.6) : numOrNull(get(item, 'wind_kmh'));

  // direzione: "dir" (gradi o testo) o "Dir_text"
  var dirRaw   = pickFirstDefined(item, ['dir', 'dir_text', 'Dir_text']);
  var dirDeg   = numOrNull(dirRaw);
  var wDir     = (dirDeg !== null) ? degToCompass(dirDeg) : (dirRaw !== null ? String(dirRaw) : null);

  // pressione: supporto alias multipli
  var pRaw = pickFirstDefined(item, [
    'p_hpa', 'press_hpa', 'pressione_hpa',
    'pressure_hpa', 'pressure', 'mbar', 'press_mb'
  ]);
  var pVal = (pRaw === null ? null : +pRaw);

  // --- Overlay centrale (solo classi CSS) ----------------------------------
  var overlay = document.createElement('span');
  var tempClass = (isFinite(+get(item, 'temp')) ? getTempColorClassJS(+get(item, 'temp')) : 'temp-default');
  overlay.className = 'overlay-mini ' + tempClass;

  // RIGA 1: Temperatura
  var elTemp = document.createElement('span');
  elTemp.className = 'temp-line';
  elTemp.textContent = (tDisplay === null ? 'N/D' : (tDisplay + '°C'));

  // RIGA 2: Ora (rossa) con icona clessidra (outline)
  var dataSolo = estraiDataDaItem(item); // ← nuovo
  /** Estrae la data dd/mm/yyyy da item.data_ora */
  /** Estrae data breve "dd/mm" da item.data_ora */
function estraiDataDaItem(item) {
  var s = getStr(item, 'data_ora');
  // match tipo "09/01/2025"
  var m = s.match(/\b(\d{2}\/\d{2})\/\d{4}\b/);
  return m ? m[1] : 'N/D';
}

  var elOra = document.createElement('span');
  elOra.className = 'ora-line';
  elOra.innerHTML =
    '<svg class="icon icon-outline" viewBox="0 0 24 24">' +
    '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"></circle>' +
    '<line x1="12" y1="12" x2="12" y2="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"></line>' +
    '<line x1="12" y1="12" x2="15" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"></line>' +
  '</svg> ' +
    dataSolo + ' ' + ora; // <--- DATA + ORA


  // RIGA 3: Vento (km/h + direzione) — verde
  var elVento = document.createElement('span');
  elVento.className = 'meta-line vento-line';
  elVento.innerHTML =
    '<svg class="icon icon-outline" viewBox="0 0 24 24">' +
      '<path d="M4 12h9a3 3 0 1 0-3-3"></path>' +
      '<path d="M4 18h11a4 4 0 1 0-4-4"></path>' +
    '</svg> ' +
    (wKmh !== null ? (wKmh + ' km/h') : 'N/D') + (wDir ? (' ' + wDir) : '');

  // RIGA 4: Umidità — verde
  var elHR = document.createElement('span');
  elHR.className = 'meta-line hr-line';
  elHR.innerHTML =
    '<svg class="icon" viewBox="0 0 24 24">' +
      '<path d="M12 2s7 8 7 12a7 7 0 1 1-14 0C5 10 12 2 12 2Z"></path>' +
    '</svg> ' +
    (hrVal !== null ? (Math.round(hrVal) + '%') : 'N/D');

  // RIGA 5: Pressione (molla outline) — verde
  var elPress = document.createElement('span');
  elPress.className = 'meta-line press-line';
  elPress.innerHTML =
    '<svg class="icon icon-outline" viewBox="0 0 24 24">' +
      '<path d="M8 4c0 2 8 2 8 0"></path>' +
      '<path d="M8 8c0 2 8 2 8 0"></path>' +
      '<path d="M8 12c0 2 8 2 8 0"></path>' +
      '<path d="M8 16c0 2 8 2 8 0"></path>' +
    '</svg> ' +
    (pVal != null ? (Math.round(pVal) + ' hPa') : 'N/D');

  // Montaggio overlay
  overlay.appendChild(elTemp);
  overlay.appendChild(elOra);
  overlay.appendChild(elVento);
  overlay.appendChild(elHR);
  overlay.appendChild(elPress);

  // Montaggio finale thumb
  wrap.appendChild(img);
  wrap.appendChild(overlay);

  return wrap;
}


/* ========================== Fetch + Render all =========================== */
/**
 * Scarica i dati dal backend, aggiorna main image/overlay e ricostruisce
 * la galleria delle miniature.
 */
function aggiornaGalleria() {
  var logTime = new Date().toLocaleTimeString();
  console.log('⏳ [' + logTime + '] ========================================');
  console.log('⏳ [' + logTime + '] Inizio aggiornamento galleria...');

  fetch(ENDPOINT_AGGIORNAMENTO)
    .then(function (response) {
      if (!response.ok) throw new Error('HTTP ' + response.status);
      return response.json();
    })
    .then(function (dati) {
      console.log('✅ [' + logTime + '] Ricevuti ' + dati.length + ' record dal server');

      // 1) Aggiorna array globale (usato anche altrove)
      window.images = dati;

      // 2) Main image + overlay data/temperatura
      var mainImage = document.getElementById('main-image');
      var mainDate  = document.getElementById('main-image-date');

      if (mainImage && mainDate) {
        var rec = window.images[0];
        if (!rec) {
          console.warn('⚠️ [' + logTime + '] Nessun record disponibile per la main image');
        } else {
          var nuovoSrc = getStr(rec, 'src') + '?t=' + Date.now(); // cache-busting
          mainImage.src = nuovoSrc;
          mainImage.onclick = function () {
            if (typeof openLightbox === 'function') openLightbox(0);
          };

          var nuovaData = getStr(rec, 'data_ora') || 'N/D';
          renderMainDate(nuovaData, get(rec, 'temp'));

          console.log('🖼️ [' + logTime + '] Main image aggiornata: ' + nuovoSrc);
        }
      } else {
        console.warn('⚠️ [' + logTime + '] mainImage o mainDate non trovati nel DOM');
      }

      // 3) Ricostruisci galleria miniature
      var gallery = document.querySelector('.gallery');
      if (!gallery) {
        console.warn('⚠️ [' + logTime + '] .gallery non trovata nel DOM');
      } else {
        gallery.innerHTML = '';
        var list = window.images || [];
        for (var i = 0; i < list.length; i++) {
          try {
            var node = createThumbnailNode(list[i], i);
            gallery.appendChild(node);
          } catch (e) {
            console.error('❌ Errore costruendo miniatura', i, e);
          }
        }
        console.log('🖼️ [' + logTime + '] Galleria ricostruita con ' + list.length + ' miniature');
      }

      // 4) Aggiorna stato bottoni navigazione lightbox (se definita)
      if (typeof updateNavButtons === 'function') {
        updateNavButtons();
        console.log('🔄 [' + logTime + '] Bottoni navigazione aggiornati');
      }

      console.log('✅ [' + logTime + '] Aggiornamento completato');
      console.log('⏳ [' + logTime + '] ========================================');
    })
    .catch(function (err) {
      console.error('❌ [' + logTime + '] Errore durante aggiornamento galleria:', err);
      console.log('⏳ [' + logTime + '] ========================================');
    });
}


/* ========================= Init / Timer / Debug ========================== */

/** Avvio periodico */
var intervalId = setInterval(aggiornaGalleria, AGGIORNAMENTO_INTERVALLO);
console.log('⏰ Timer aggiornamento automatico: ogni ' + (AGGIORNAMENTO_INTERVALLO / 1000) + ' secondi');

/** Primo popolamento all’avvio pagina */
document.addEventListener('DOMContentLoaded', function () {
  console.log('🚀 Pagina caricata, eseguo primo aggiornamento…');
  aggiornaGalleria();
});

/** Debug helpers (richiamabili da console) */
function stopAggiornamentoAutomatico() {
  clearInterval(intervalId);
  console.log('⏹️ Aggiornamento automatico fermato');
}
function forzaAggiornamento() {
  console.log('🔄 Aggiornamento forzato manualmente');
  aggiornaGalleria();
}
window.stopAggiornamentoAutomatico = stopAggiornamentoAutomatico;
window.forzaAggiornamento = forzaAggiornamento;

console.log('📋 Funzioni debug disponibili:');
console.log('   - stopAggiornamentoAutomatico()');
console.log('   - forzaAggiornamento()');
