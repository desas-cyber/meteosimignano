/* ============================================================================
 * GALLERIA METEO – Aggiornamento automatico (ES5-compatible)
 * ============================================================================
 *
 * Cosa fa:
 * - Scarica periodicamente i record dal backend (JSON)
 * - Aggiorna immagine principale + overlay data/temperatura
 * - Ricostruisce la griglia di miniature con overlay centrato (temp/ora/vento/HR/pressione)
 * - Gestisce click su miniature (apre lightbox se disponibile)
 *
 * Dipendenze:
 * - Backend: ENDPOINT_AGGIORNAMENTO → restituisce array di record:
 * [{ src, data_ora, temp, hr, p_hpa, vento, dir, ... }, ...]
 * - CSS: classi .overlay-mini, .temp-line, .ora-line, .meta-line, .icon, .icon-outline
 * - JS esterno: openLightbox(index) e updateNavButtons() se presenti
 *
 * Note compatibilità:
 * - Niente optional chaining (?.) / nullish coalescing (??) / let/const / => / template string
 * - Solo ES5 puro per motori JS legacy (anche embedded in device/webview)
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


/**
 * Mappa temperatura → classe colore CSS */
function getTempColorClassJS(temp) {
  var t = parseFloat(temp);
  if (isNaN(t)) return 'temp-default';
  
  if (t > 35)   return 'temp-red';        // > 35°C
  if (t >= 25)  return 'temp-orange';     // 25-35°C
  if (t >= 15)  return 'temp-green';      // 15-24.9°C
  if (t >= 5)   return 'temp-lightblue';  // 5-14.9°C
  if (t >= -3)  return 'temp-blue';       // -3-4.9°C
  return 'temp-violet';                   // < -3°C
}

// 📌 SPOSTATA QUI: Funzione per estrarre la data breve (risolve ReferenceError)
/**
 * Estrae data breve "dd/mm" da item.data_ora (es. "09/01/2025 14:30" → "09/01")
 */
function estraiDataDaItem(item) {
  var s = getStr(item, 'data_ora');
  // match tipo "dd/mm/yyyy"
  var m = s.match(/\b(\d{2}\/\d{2})\/\d{4}\b/);
  return m ? m[1] : 'N/D';
}

/** Estrae HH:MM da item.data_ora (es. "dd/mm/yyyy HH:MM") */
function estraiOraDaItem(item) {
  var s = getStr(item, 'data_ora');
  var m = s.match(/\b(\d{2}):(\d{2})\b/);
  return m ? (m[1] + ':' + m[2]) : 'N/D';
}


/* ===================== Main image: render overlay ======================== */
/**
 * Aggiorna la scritta “Ultima immagine (dir. NO): …” e la temperatura
 * nell’overlay dell’immagine principale.
 * @param {string} nuovaData  es. "09/01/2025 14:30"
 * @param {number|string} temp in °C (arrotondata all’unità)
 * @param {string} [isMinMaxClass=''] Classe di lampeggiamento ('is-min' o 'is-max'). 🆕
 */
function renderMainDate(nuovaData, temp, isMinMaxClass) {
  var dateSpan = document.getElementById('date-label');
  var tempSpan = document.getElementById('temp-label');

  // Assicura che il parametro opzionale sia trattato come stringa
  isMinMaxClass = isMinMaxClass || ''; 

  if (!dateSpan || !tempSpan) {
    console.error('❌ Mancano #date-label o #temp-label nel DOM');
    return;
  }

  // Data/ora
  dateSpan.textContent = 'Ultima immagine (dir. NO): ' + (nuovaData || 'N/D');

  // Temperatura arrotondata all’unità + colore dinamico
  var t = numOrNull(temp);
  var display = (t === null ? null : parseFloat(t).toFixed(1)); // 1 decimale
  var colorClass = (t === null ? 'temp-default' : getTempColorClassJS(t));

  tempSpan.textContent = (display === null ? 'N/D' : (display + '°C'));
  
  // 📌 AGGIORNATO: Aggiunge la classe di lampeggiamento per la main image
  tempSpan.className = 'temp-data ' + colorClass + ' ' + isMinMaxClass;
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
  
  if (tDisplay === null) {
      elTemp.classList.add('temp-default');
      }
  elTemp.textContent = (tDisplay === null ? 'N/D' : (tDisplay + '°C'));

  // RIGA 2: Ora (rossa) con icona clessidra (outline)
  var dataSolo = estraiDataDaItem(item); // ← usa la funzione spostata
  var elOra = document.createElement('span');
  elOra.className = 'ora-line';
  // Se uno dei due è N/D → testo grigio
if (dataSolo === 'N/D' || ora === 'N/D') {
  elOra.classList.add('temp-default');
}
  elOra.innerHTML =
    '<svg class="icon icon-outline" viewBox="0 0 24 24">' +
    '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"></circle>' +
    '<line x1="12" y1="12" x2="12" y2="7" stroke="currentColor" stroke-width="2" stroke-linecap="round"></line>' +
    '<line x1="12" y1="12" x2="15" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"></line>' +
  '</svg> ' +
    ((dataSolo === 'N/D' || ora === 'N/D') ? 'N/D' : (dataSolo + ' ' + ora));


  // RIGA 3: Vento (km/h + direzione) — verde
  var elVento = document.createElement('span');
  elVento.className = 'meta-line vento-line';
  // Se non c'è il valore → aggiungi colore "temp-default"
  if (wKmh === null) {
      elVento.classList.add('temp-default');
      }
  elVento.innerHTML =
    '<svg class="icon icon-outline" viewBox="0 0 24 24">' +
      '<path d="M4 12h9a3 3 0 1 0-3-3"></path>' +
      '<path d="M4 18h11a4 4 0 1 0-4-4"></path>' +
    '</svg> ' +
    (wKmh !== null ? (wKmh + ' km/h') : 'N/D') + (wDir ? (' ' + wDir) : '');

  // RIGA 4: Umidità — verde
  var elHR = document.createElement('span');
  elHR.className = 'meta-line hr-line';
  if (hrVal === null) {
      elHR.classList.add('temp-default');
      }
  elHR.innerHTML =
    '<svg class="icon" viewBox="0 0 24 24">' +
      '<path d="M12 2s7 8 7 12a7 7 0 1 1-14 0C5 10 12 2 12 2Z"></path>' +
    '</svg> ' +
    (hrVal !== null ? (Math.round(hrVal) + '%') : 'N/D');

  // RIGA 5: Pressione (molla outline) — verde
  var elPress = document.createElement('span');
  elPress.className = 'meta-line press-line';
  if (pVal === null || pVal === "" || isNaN(pVal)) {
      elPress.classList.add('temp-default');
      }
  elPress.innerHTML =
    '<svg class="icon icon-outline" viewBox="0 0 24 24">' +
      '<path d="M8 4c0 2 8 2 8 0"></path>' +
      '<path d="M8 8c0 2 8 2 8 0"></path>' +
      '<path d="M8 12c0 2 8 2 8 0"></path>' +
      '<path d="M8 16c0 2 8 2 8 0"></path>' +
    '</svg> ' +
    (pVal != null && !isNaN(pVal) ? (Math.round(pVal) + ' hPa') : 'N/D');


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
  //console.log('⏳ [' + logTime + '] ========================================');
  //console.log('⏳ [' + logTime + '] Inizio aggiornamento galleria...');

  fetch(ENDPOINT_AGGIORNAMENTO)
    .then(function (response) {
      if (!response.ok) throw new Error('HTTP ' + response.status);
      return response.json();
    })
    .then(function (dati) {
      //console.log('✅ [' + logTime + '] Ricevuti ' + dati.length + ' record dal server');

      // 1) Aggiorna array globale (usato anche altrove)
      window.images = dati;

      // 2) Main image + overlay data/temperatura
      var mainImage = document.getElementById('main-image');
      var mainDate  = document.getElementById('main-image-date');

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
        //console.log('🖼️ [' + logTime + '] Galleria ricostruita con ' + list.length + ' miniature');
      }
      
      // === PASSO 5: Applica classi di lampeggiamento e prepara la classe per la Main Image ===

// === PASSO 5: Applica classi di lampeggiamento e prepara la classe per la Main Image ===
var minMaxData = trovaMinMaxTempOggi(window.images); 
var list = window.images || [];
var gallery = document.querySelector('.gallery');
var thumbs = gallery ? gallery.querySelectorAll('.thumb') : [];
var mainTempClass = ''; // Classe per la main image

if (minMaxData && list.length > 0) {
  var minTemp = minMaxData.min;
  var maxTemp = minMaxData.max;

  // 1) Data di riferimento valida (come fai già)
  var dataPiuRecente = '';
  for (var i = 0; i < list.length; i++) {
    var fullDate = getStr(list[i], 'data_ora');
    if (fullDate && fullDate.length >= 10) {
      dataPiuRecente = estraiDataDaItem(list[i]);
      break;
    }
  }
  if (!dataPiuRecente || dataPiuRecente === 'N/D') {
    console.log('⚠️ Impossibile trovare una data di riferimento valida per il lampeggiamento.');
  } else {
    // 2) Pulisci vecchie classi su TUTTE le thumb
    for (var r = 0; r < thumbs.length; r++) {
      thumbs[r].classList.remove('is-min', 'is-max');
    }

    // 3) Determina classe per la main image (resta uguale)
    var mainTempRaw = numOrNull(get(list[0], 'temp'));
    var mainTemp = mainTempRaw !== null ? Math.round(mainTempRaw * 10) / 10 : null;
    if (mainTemp !== null && estraiDataDaItem(list[0]) === dataPiuRecente) {
      if (mainTemp === minTemp) mainTempClass = 'is-min';
      else if (mainTemp === maxTemp) mainTempClass = 'is-max';
    }

    // 4) Applica classi alle THUMB (non più alla .temp-line!)
    for (var k = 0; k < list.length; k++) {
      if (!thumbs[k]) continue;

      var item = list[k];
      var itemDate = estraiDataDaItem(item);
      if (itemDate !== dataPiuRecente) continue;

      var currentTempRaw = numOrNull(get(item, 'temp'));
      var currentTemp = currentTempRaw !== null ? Math.round(currentTempRaw * 10) / 10 : null;
      if (currentTemp === null) continue;

      if (currentTemp === minTemp) thumbs[k].classList.add('is-min');
      else if (currentTemp === maxTemp) thumbs[k].classList.add('is-max');
    }
  }


    //console.log('✨ Classi min/max applicate correttamente solo ai record del', dataPiuRecente);
}

// 📌 Ritorna al punto 2 per aggiornare la Main Image ORA con la nuova classe.
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
      
      // 📌 CHIAMATA AGGIORNATA: Passa la classe di lampeggiamento
      renderMainDate(nuovaData, get(rec, 'temp'), mainTempClass); 

      //console.log('🖼️ [' + logTime + '] Main image aggiornata: ' + nuovoSrc);
    }
  } else {
    console.warn('⚠️ [' + logTime + '] mainImage o mainDate non trovati nel DOM');
  }

// =======================================================
// 4) Aggiorna stato bottoni navigazione lightbox (se definita)
      if (typeof updateNavButtons === 'function') {
        updateNavButtons();
        //console.log('🔄 [' + logTime + '] Bottoni navigazione aggiornati');
      }

      console.log('✅ [' + logTime + '] Aggiornamento completato');
      //console.log('⏳ [' + logTime + '] ========================================');
    })
    .catch(function (err) {
      console.error('❌ [' + logTime + '] Errore durante aggiornamento galleria:', err);
      //console.log('⏳ [' + logTime + '] ========================================');
    });

}
/* ===== Trova temperatura minima e massima tra le immagini della DATA CORRENTE ========================== */

/**
 * Trova temperatura minima e massima tra le immagini della DATA PIÙ RECENTE
 * * @param {Array} arrayImmagini - Array ordinato per data (più recente per prima)
 * @returns {Object|null} { min: number, max: number } oppure null se non applicabile
 */
function trovaMinMaxTempOggi(arrayImmagini) {
  //console.log('🔍 === DEBUG TROVA MIN/MAX ===');
  
  // 1) Verifica che ci siano immagini
  if (!arrayImmagini || arrayImmagini.length === 0) {
    console.log('⚠️ Nessuna immagine disponibile');
    return null;
  }
  
  console.log('📊 Totale immagini ricevute:', arrayImmagini.length);
  
  // 2) Prendi la data più recente (dalla prima immagine)
  // 2) Prendi la data più recente VALIDA
var dataPiuRecente = null;
var dataOraPiuRecente = null;

for (var i = 0; i < arrayImmagini.length; i++) {
    var item = arrayImmagini[i];
    var fullDate = getStr(item, 'data_ora');
    
    // Controlla che la stringa abbia una lunghezza sufficiente (es. "DD/MM/YYYY")
    if (fullDate && fullDate.length >= 10) { 
        dataPiuRecente = fullDate.substring(0, 10);
        dataOraPiuRecente = fullDate;
        break; // Trovata la prima data valida, usciamo dal ciclo
    }
}

if (!dataPiuRecente) {
    console.log('⚠️ Nessuna data valida trovata in tutto l’array.');
    return null;
}

console.log('🗓️ Data più recente VALIDA nel DB:', dataPiuRecente);
console.log('🗓️ Data/ora prima immagine completa VALIDA:', dataOraPiuRecente);
  // 3) Filtra immagini della data più recente
  var immaginiDataRecente = [];
  var immaginiConTemp = [];
  
  for (var i = 0; i < arrayImmagini.length; i++) {
    var item = arrayImmagini[i];
    var dataItemCompleta = getStr(item, 'data_ora');
    var dataItem = dataItemCompleta.substring(0, 10);
    var temp = numOrNull(get(item, 'temp'));
    
    console.log('Img ' + i + ': data=' + dataItem + ', temp=' + temp);
    
    if (dataItem === dataPiuRecente) {
      immaginiDataRecente.push(item);
      
      if (temp !== null) {
        immaginiConTemp.push({
          index: i,
          temp: temp,
          data_ora: dataItemCompleta
        });
        console.log('  ✅ Temperatura valida aggiunta:', temp);
      } else {
        console.log('  ⚠️ Temperatura N/D');
      }
    }
  }
  
  console.log('📸 Immagini totali della data più recente:', immaginiDataRecente.length);
  console.log('📸 Immagini con temperatura valida:', immaginiConTemp.length);
  
  // Mostra tutte le temperature valide
  if (immaginiConTemp.length > 0) {
    var temps = [];
    for (var k = 0; k < immaginiConTemp.length; k++) {
      temps.push(immaginiConTemp[k].temp.toFixed(1));
    }
    console.log('🌡️ Temperature valide:', temps.join(', '));
  }
  
  // 4) CONDIZIONE 1: Se NON ci sono temperature valide
  if (immaginiConTemp.length === 0) {
    console.log('❌ TUTTE le temperature sono N/D → nessun lampeggio');
    return null;
  }
  
  // 5) CONDIZIONE 2: Se c'è UNA SOLA temperatura valida
  if (immaginiConTemp.length === 1) {
    console.log('❌ Una sola temperatura valida → nessun lampeggio');
    return null;
  }
  
  // 6) Trova min e max
  var tempMin = Infinity;
  var tempMax = -Infinity;
  
  for (var j = 0; j < immaginiConTemp.length; j++) {
    var t = immaginiConTemp[j].temp;
    if (t < tempMin) tempMin = t;
    if (t > tempMax) tempMax = t;
  }
  
  console.log('🌡️ Min raw:', tempMin, '| Max raw:', tempMax);
  
  // 7) Arrotonda a 1 decimale
  tempMin = Math.round(tempMin * 10) / 10;
  tempMax = Math.round(tempMax * 10) / 10;
  
  console.log('🌡️ Min arrotondato:', tempMin, '| Max arrotondato:', tempMax);
  
  // 8) CONDIZIONE 3: Se min === max
  if (tempMin === tempMax) {
    console.log('❌ Tutte le temperature valide sono uguali (' + tempMin + '°C) → nessun lampeggio');
    return null;
  }
  
  console.log('✅ MIN=' + tempMin.toFixed(1) + '°C, MAX=' + tempMax.toFixed(1) + '°C');
  console.log('🔍 === FINE DEBUG ===');
  
  return {
    min: tempMin,
    max: tempMax
  };
}
/* ========================= funzioni helper ========================== */

// Le funzioni helper estraiDataDaItem e estraiOraDaItem sono state spostate più in alto.

/* ========================= Init / Timer / Debug ========================== */

/** Avvio periodico */
var intervalId = setInterval(aggiornaGalleria, AGGIORNAMENTO_INTERVALLO);
//console.log('⏰ Timer aggiornamento automatico: ogni ' + (AGGIORNAMENTO_INTERVALLO / 1000) + ' secondi');

/** Primo popolamento all’avvio pagina */
document.addEventListener('DOMContentLoaded', function () {
  //console.log('🚀 Pagina caricata, eseguo primo aggiornamento…');
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

//console.log('📋 Funzioni debug disponibili:');
//console.log('   - stopAggiornamentoAutomatico()');
//console.log('   - forzaAggiornamento()');