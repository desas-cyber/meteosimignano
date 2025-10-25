/**
 * ==========================================================================
 *  AGGIORNAMENTO AUTOMATICO GALLERIA METEO
 * ==========================================================================
 *
 *  Cosa fa questo script:
 *  - Aggiorna periodicamente l'immagine principale e la griglia delle miniature
 *  - Sincronizza i dati meteo dal backend (PHP) e li visualizza in overlay
 *  - Gestisce il click sulle miniature per aprire la lightbox
 *
 *  Dipendenze:
 *  - window.images: array popolato dal backend con i record immagini+meteo
 *  - galleria-lightbox.js: fornisce openLightbox() e updateNavButtons()
 *  - CSS: classi per overlay (overlay-mini, temp-line, ora-line, meta-line, …)
 *
 *  Backend:
 *  - ENDPOINT_AGGIORNAMENTO (aggiorna_galleria.php) restituisce JSON:
 *    [{ src, data_ora, temp, hr, p_hpa, vento, dir }, ...]
 *
 *  Convenzioni colore temperatura (CSS):
 *  - temp-blue       (<   0°C)
 *  - temp-lightblue  (0–14°C)
 *  - temp-green      (15–24.9°C)
 *  - temp-orange     (≥25–35°C)
 *  - temp-red        (>  35°C)
 *  - temp-default    (valore non valido)
 *
 *  Manutenzione:
 *  - Tutto l'I/O di rete è in aggiornaGalleria()
 *  - Rendering main image in renderMainDate()
 *  - Creazione singola miniatura: createThumbnailNode(item, index)
 *
 *  Autore: MeteoSimignano
 *  Versione: 2.1 (refactor commenti e leggibilità)
 */

/* ==========================================================================
 *  COSTANTI DI CONFIGURAZIONE
 * ========================================================================== */

/** Intervallo aggiornamento (5 minuti); 59s per ridurre drift */
const AGGIORNAMENTO_INTERVALLO = 5 * 59 * 1000;

/** Endpoint backend che restituisce l’array di record */
const ENDPOINT_AGGIORNAMENTO = 'aggiorna_galleria.php';


/* ==========================================================================
 *  HELPER GENERICI (numeri, direzione vento, parsing ora)
 * ========================================================================== */

/**
 * Converte un valore in numero oppure torna null se non valido.
 */
function numOrNull(v) {
  return (v !== null && v !== undefined && v !== '' && Number.isFinite(+v)) ? +v : null;
}

/**
 * Converte gradi in direzione bussola (N, NE, …).
 */
function degToCompass(deg) {
  const dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
  const i = Math.round(((deg % 360) / 22.5)) % 16;
  return dirs[i < 0 ? i + 16 : i];
}

/**
 * Estrae l’ora in formato HH:MM dal campo item.data_ora (es. "dd/mm/yyyy HH:MM").
 * Se non trovata, restituisce "N/D".
 */
function estraiOraDaItem(item) {
  const s = item?.data_ora || '';
  const m = s.match(/\b(\d{2}):(\d{2})\b/);
  return m ? `${m[1]}:${m[2]}` : 'N/D';
}


/* ==========================================================================
 *  COLORI TEMPERATURA → CLASSI CSS
 * ========================================================================== */
/**
 * Ritorna la classe CSS per colorare la temperatura.
 * Soglie:
 *   > 35           → temp-red
 *   25 ≤ t ≤ 35    → temp-orange
 *   15 ≤ t < 25    → temp-green
 *    0 ≤ t < 15    → temp-lightblue
 *   t < 0          → temp-blue
 *   altro          → temp-default
 */
function getTempColorClassJS(temp) {
  const t = parseFloat(temp);
  if (isNaN(t)) return 'temp-default';

  if (t > 35)              return 'temp-red';
  if (t >= 25 && t <= 35)  return 'temp-orange';
  if (t >= 15 && t < 25)   return 'temp-green';
  if (t >= 0 && t < 15)    return 'temp-lightblue';
  if (t < 0)               return 'temp-blue';

  return 'temp-default';
}


/* ==========================================================================
 *  RENDER OVERLAY IMMAGINE PRINCIPALE (data + temperatura)
 * ========================================================================== */
/**
 * Aggiorna la scritta “Ultima immagine (dir. NO): …” e la temperatura
 * nell’overlay dell’immagine principale.
 *
 * @param {string} nuovaData - es. "09/01/2025 14:30"
 * @param {number|string} temp - temperatura in °C (arrotondata in output)
 */
function renderMainDate(nuovaData, temp) {
  console.log('🔧 renderMainDate:', nuovaData, temp);

  const dateSpan = document.getElementById('date-label');
  const tempSpan = document.getElementById('temp-label');

  if (!dateSpan || !tempSpan) {
    console.error('❌ Mancano #date-label o #temp-label nel DOM');
    return;
  }

  // Data/ora
  dateSpan.textContent = `Ultima immagine (dir. NO): ${nuovaData || 'N/D'}`;

  // Temperatura (arrotondata all’unità) + colore dinamico
  const t = numOrNull(temp);
  const display = (t != null) ? Math.round(t) : null;
  const colorClass = (t != null) ? getTempColorClassJS(t) : 'temp-default';

  tempSpan.textContent = (display != null) ? `${display}°C` : 'N/D';
  tempSpan.className = `temp-data ${colorClass}`;

  console.log('✅ Main overlay aggiornato:', tempSpan.textContent, colorClass);
}


/* ==========================================================================
 *  CREAZIONE SINGOLA MINIATURA (nodo DOM)
 * ========================================================================== */
/**
 * Costruisce il DOM per una singola miniatura (thumb) con overlay centrale.
 * Non appende alla galleria: delegato al chiamante.
 */
function createThumbnailNode(item, index) {
  // Wrapper thumb
  const wrap = document.createElement('div');
  wrap.className = 'thumb';

  // Immagine + cache-busting
  const img = document.createElement('img');
  img.src = (item?.src || '').trim() + '?t=' + Date.now();
  img.alt = 'Immagine webcam';

  // Click → lightbox (se disponibile)
  img.onclick = () => {
    if (typeof openLightbox === 'function') openLightbox(index);
  };

  // --- Parsing valori record ------------------------------------------------
  const tNum  = numOrNull(item.temp);
  const tDisplay = (tNum != null ? Math.round(tNum) : null);   // arrotondato all’unità

  const ora   = estraiOraDaItem(item);

  const hrVal = numOrNull(item.hr);                             // %
  const vMs   = numOrNull(item.vento);                          // m/s
  const wKmh  = (vMs != null) ? Math.round(vMs * 3.6) : null;   // km/h

  const wDeg  = numOrNull(item.dir);
  const wDir  = (wDeg != null) ? degToCompass(wDeg) : null;

  const pVal  = numOrNull(
    item.p_hpa ?? item.press_hpa ?? item.pressione_hpa ??
    item.pressure_hpa ?? item.pressure ?? item.mbar ?? item.press_mb
  );

  // --- Overlay centrale (usa solo classi CSS) ------------------------------
  const overlay = document.createElement('span');
  overlay.className =
    'overlay-mini ' + (Number.isFinite(+item?.temp) ? getTempColorClassJS(+item.temp) : 'temp-default');

  // Riga 1: Temperatura (colore dinamico via classe su overlay)
  const elTemp = document.createElement('span');
  elTemp.className = 'temp-line';
  elTemp.textContent = (tDisplay != null) ? (tDisplay + '°C') : 'N/D';

  // Riga 2: Ora (rossa) con icona clessidra (outline)
  const elOra = document.createElement('span');
  elOra.className = 'ora-line';
  elOra.innerHTML = `
    <svg class="icon icon-outline" viewBox="0 0 24 24">
      <path d="M6 2h12M6 22h12M6 2c0 5 6 6 6 10s-6 5-6 10M18 2c0 5-6 6-6 10s6 5 6 10"/>
    </svg>
    ${ora}
  `;

  // Riga 3: Vento (km/h + direzione) — verde
  const elVento = document.createElement('span');
  elVento.className = 'meta-line vento-line';
  elVento.innerHTML = `
    <svg class="icon icon-outline" viewBox="0 0 24 24">
      <path d="M4 12h9a3 3 0 1 0-3-3" />
      <path d="M4 18h11a4 4 0 1 0-4-4" />
    </svg>
    ${wKmh != null ? (wKmh + ' km/h') : 'N/D'}${wDir ? ' ' + wDir : ''}
  `;

  // Riga 4: Umidità — verde
  const elHR = document.createElement('span');
  elHR.className = 'meta-line hr-line';
  elHR.innerHTML = `
    <svg class="icon" viewBox="0 0 24 24">
      <path d="M12 2s7 8 7 12a7 7 0 1 1-14 0C5 10 12 2 12 2Z"/>
    </svg>
    ${hrVal != null ? (Math.round(hrVal) + '%') : 'N/D'}
  `;

  // Riga 5: Pressione (molla outline) — verde
  const elPress = document.createElement('span');
  elPress.className = 'meta-line press-line';
  elPress.innerHTML = `
    <svg class="icon icon-outline" viewBox="0 0 24 24">
      <path d="M8 4c0 2 8 2 8 0" />
      <path d="M8 8c0 2 8 2 8 0" />
      <path d="M8 12c0 2 8 2 8 0" />
      <path d="M8 16c0 2 8 2 8 0" />
    </svg>
    ${pVal != null ? Math.round(pVal) + ' hPa' : 'N/D'}
  `;

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


/* ==========================================================================
 *  FETCH + RENDER COMPLETO (main image + griglia)
 * ========================================================================== */
/**
 * Scarica i dati dal backend, aggiorna main image/overlay e ricostruisce
 * la galleria delle miniature.
 */
function aggiornaGalleria() {
  const logTime = new Date().toLocaleTimeString();
  console.log(`⏳ [${logTime}] ========================================`);
  console.log(`⏳ [${logTime}] Inizio aggiornamento galleria...`);

  fetch(ENDPOINT_AGGIORNAMENTO)
    .then((response) => {
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      return response.json();
    })
    .then((dati) => {
      console.log(`✅ [${logTime}] Ricevuti ${dati.length} record dal server`);

      // 1) Aggiorna array globale (usato anche altrove)
      window.images = dati;

      // 2) Main image + overlay data/temperatura
      const mainImage = document.getElementById('main-image');
      const mainDate  = document.getElementById('main-image-date');

      if (mainImage && mainDate) {
        const rec = window.images[0];
        if (!rec) {
          console.warn(`⚠️ [${logTime}] Nessun record disponibile per la main image`);
        } else {
          const nuovoSrc = rec.src + '?t=' + Date.now(); // cache-busting
          mainImage.src = nuovoSrc;
          mainImage.onclick = () => { if (typeof openLightbox === 'function') openLightbox(0); };

          const nuovaData = rec.data_ora || 'N/D';
          renderMainDate(nuovaData, rec.temp);

          console.log(`🖼️ [${logTime}] Main image aggiornata: ${nuovoSrc}`);
        }
      } else {
        console.warn(`⚠️ [${logTime}] mainImage o mainDate non trovati nel DOM`);
      }

      // 3) Ricostruisci galleria miniature
      const gallery = document.querySelector('.gallery');
      if (!gallery) {
        console.warn(`⚠️ [${logTime}] .gallery non trovata nel DOM`);
      } else {
        gallery.innerHTML = '';
        (window.images || []).forEach((item, index) => {
          try {
            const node = createThumbnailNode(item, index);
            gallery.appendChild(node);
          } catch (e) {
            console.error('❌ Errore costruendo miniatura', index, e);
          }
        });
        console.log(`🖼️ [${logTime}] Galleria ricostruita con ${ (window.images || []).length } miniature`);
      }

      // 4) Aggiorna stato bottoni navigazione lightbox (se definita)
      if (typeof updateNavButtons === 'function') {
        updateNavButtons();
        console.log(`🔄 [${logTime}] Bottoni navigazione aggiornati`);
      }

      console.log(`✅ [${logTime}] Aggiornamento completato`);
      console.log(`⏳ [${logTime}] ========================================`);
    })
    .catch((err) => {
      console.error(`❌ [${logTime}] Errore durante aggiornamento galleria:`, err);
      console.log(`⏳ [${logTime}] ========================================`);
    });
}


/* ==========================================================================
 *  INIZIALIZZAZIONE / TIMER
 * ========================================================================== */

/** Avvio periodico */
const intervalId = setInterval(aggiornaGalleria, AGGIORNAMENTO_INTERVALLO);
console.log(`⏰ Timer aggiornamento automatico: ogni ${AGGIORNAMENTO_INTERVALLO / 1000} secondi`);

/** Primo popolamento all’avvio pagina */
document.addEventListener('DOMContentLoaded', () => {
  console.log('🚀 Pagina caricata, eseguo primo aggiornamento…');
  aggiornaGalleria();
});


/* ==========================================================================
 *  UTILITÀ DEBUG (richiamabili da console)
 * ========================================================================== */

function stopAggiornamentoAutomatico() {
  clearInterval(intervalId);
  console.log('⏹️ Aggiornamento automatico fermato');
}

function forzaAggiornamento() {
  console.log('🔄 Aggiornamento forzato manualmente');
  aggiornaGalleria();
}

// Espone le utility in window per comodità da console
window.stopAggiornamentoAutomatico = stopAggiornamentoAutomatico;
window.forzaAggiornamento = forzaAggiornamento;

console.log('📋 Funzioni debug disponibili:');
console.log('   - stopAggiornamentoAutomatico()');
console.log('   - forzaAggiornamento()');
