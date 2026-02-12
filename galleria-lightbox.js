/* =================================================================
 *  LIGHTBOX GALLERIA - Time-lapse FLUIDO (v3.0)
 * =================================================================
 *
 *  OTTIMIZZAZIONI RISPETTO ALLA VERSIONE PRECEDENTE:
 *  1. Preload buffer: precarica N immagini avanti durante il time-lapse
 *  2. CSS clip-path al posto di canvas crop: elimina ~100ms/frame
 *  3. requestAnimationFrame al posto di setInterval: timing preciso
 *  4. Replay "R": a fine time-lapse il bottone diventa R per ripetere
 *
 *  LOGICA DEFINITIVA:
 *  - Bottone SX (rewind):  parte da piu vecchia -> fino alla corrente
 *  - Bottone DX (forward): parte dalla corrente -> fino a #0 (piu recente)
 *
 *  DISABILITAZIONE:
 *  - SX: disabilitato SOLO se sei sulla piu vecchia
 *  - DX: disabilitato SOLO se sei sulla piu recente (#0)
 *
 *  ORDINE ARRAY:
 *  - Index 0 = foto PIU RECENTE
 *  - Index MAX = foto PIU VECCHIA
 *
 *  COMPATIBILITA: ES5 puro (niente let/const, arrow, template string, ?.)
 * ================================================================= */

/* ======================== CAMERA CONFIG ========================= */

/**
 * Configurazione specifica della telecamera.
 * Per adattare il sito a un'altra camera, basta cambiare questi valori.
 */
var CAM_CONFIG = {
  cropBottomPx:  80,       // px da tagliare in basso (watermark) in gallery mode
  cropBottomPct: '5.5%'   // equivalente % per clip-path in time-lapse mode
};

/* ========================== STATO =============================== */

var currentIndex     = 0;

var isRewinding      = false;
var isForwarding     = false;

var leftHoldInterval = null;
var rightHoldInterval= null;
var HOLD_DELAY       = 1000 / 3;

window.isTimelapseMode = false;

/* ==================== REPLAY STATE ============================== */

/**
 * Memorizza i parametri dell'ultimo time-lapse eseguito,
 * cosi da poterlo ripetere identico premendo "R".
 * Viene resettato alla chiusura del lightbox.
 */
var lastTimelapseType       = null;  // 'rewind' o 'forward'
var lastTimelapseStartIndex = null;  // indice di partenza nel fullImages
var lastTimelapseTargetIndex= null;  // indice di arrivo nel fullImages
var isWaitingReplay         = false; // true = bottone mostra "R"

/**
 * Mostra l'icona "R" (Replay) sul bottone che ha completato il time-lapse.
 * @param {string} which  'rewind' o 'forward'
 */
function showReplayIcon(which) {
  isWaitingReplay = true;
  var btnId = (which === 'rewind') ? 'rewind-btn' : 'forward-btn';
  var iconId = (which === 'rewind') ? 'rewind-icon' : 'forward-icon';
  var btn = document.getElementById(btnId);
  var icon = document.getElementById(iconId);
  if (btn) btn.disabled = false; // NON disabilitare: deve essere cliccabile
  if (icon) {
    // "R" centrata dentro la SVG viewBox 0 0 24 24
    icon.innerHTML =
      '<text x="12" y="17" text-anchor="middle" font-size="16" font-weight="bold" ' +
      'fill="red" font-family="Arial, sans-serif">R</text>';
  }
}

/**
 * Ripristina le icone play normali e resetta lo stato replay.
 */
function clearReplayState() {
  isWaitingReplay          = false;
  lastTimelapseType        = null;
  lastTimelapseStartIndex  = null;
  lastTimelapseTargetIndex = null;
}

/* ==================== PRELOAD BUFFER ============================ */

/** Quante immagini precaricare nella direzione di playback */
var PRELOAD_AHEAD = 12;

/** Cache: { src: Image } - le immagini gia precaricate */
var preloadCache = {};

/** Contatore per limitare la dimensione cache ed evitare memory leak */
var preloadCacheKeys = [];
var PRELOAD_CACHE_MAX = 200;

/**
 * Precarica immagini intorno all'indice corrente nella direzione data.
 * @param {number} index   Indice corrente nel fullImages array
 * @param {number} dir     -1 = verso 0 (forward/piu recente), +1 = verso max (rewind/piu vecchio)
 */
function preloadAround(index, dir) {
  var items = window.fullImages || [];
  if (!items.length) return;

  var start, end;
  if (dir < 0) {
    // Forward: precarica da index verso 0
    start = Math.max(0, index - PRELOAD_AHEAD);
    end = index;
  } else {
    // Rewind: precarica da index verso max
    start = index;
    end = Math.min(items.length - 1, index + PRELOAD_AHEAD);
  }

  for (var i = start; i <= end; i++) {
    var src = (items[i] && items[i].src) ? items[i].src.trim() : '';
    if (src && !preloadCache[src]) {
      var img = new Image();
      img.src = src;
      preloadCache[src] = img;
      preloadCacheKeys.push(src);
    }
  }

  // Evita memory leak: se la cache cresce troppo, elimina le piu vecchie
  while (preloadCacheKeys.length > PRELOAD_CACHE_MAX) {
    var oldKey = preloadCacheKeys.shift();
    delete preloadCache[oldKey];
  }
}

/* =================== TIMELAPSE rAF ENGINE ======================= */

/**
 * Velocita time-lapse in ms/frame.
 */
var TIMELAPSE_SPEED_MS = 250;

/** Handle per requestAnimationFrame (null = non in playback) */
var timelapseRAF = null;

/** Timestamp dell'ultimo frame renderizzato */
var timelapseLastFrameTime = 0;

/** Indice target verso cui ci si muove */
var timelapseTargetIndex = 0;

/** Direzione corrente: -1 verso 0 (forward), nessun +1 perche rewind decrementa comunque */
/* In entrambi i casi currentIndex-- per andare verso le piu recenti */

/**
 * Loop principale del time-lapse basato su requestAnimationFrame.
 * Viene chiamato ricorsivamente finche il playback e attivo.
 */
function timelapseLoop(timestamp) {
  // Se non siamo piu in playback, esci
  if (!isRewinding && !isForwarding) {
    timelapseRAF = null;
    return;
  }

  // Controlla se e passato abbastanza tempo dall'ultimo frame
  var elapsed = timestamp - timelapseLastFrameTime;
  if (elapsed < TIMELAPSE_SPEED_MS) {
    timelapseRAF = requestAnimationFrame(timelapseLoop);
    return;
  }
  timelapseLastFrameTime = timestamp;

  // Avanza di un frame (entrambe le direzioni decrementano verso 0)
  currentIndex--;

  // Precarica le prossime immagini
  preloadAround(currentIndex, -1);

  // Controlla se abbiamo raggiunto il target
  if (currentIndex <= timelapseTargetIndex) {
    currentIndex = Math.max(0, timelapseTargetIndex);
    aggiornaLightbox();
    updateNavButtons();
    stopTimelapse(true); // true = completato, mostra "R"
    return;
  }

  aggiornaLightbox();
  updateNavButtons();

  // Prossimo frame
  timelapseRAF = requestAnimationFrame(timelapseLoop);
}

/**
 * Ferma il time-lapse.
 * @param {boolean} completed  true = finito naturalmente (mostra R), false = pausa manuale
 */
function stopTimelapse(completed) {
  if (timelapseRAF) {
    cancelAnimationFrame(timelapseRAF);
    timelapseRAF = null;
  }

  var wasRewinding = isRewinding;
  var wasForwarding = isForwarding;

  isRewinding = false;
  isForwarding = false;

  var rewindIcon = document.getElementById('rewind-icon');
  var forwardIcon = document.getElementById('forward-icon');

  if (completed) {
    // Time-lapse terminato naturalmente: mostra "R" sul bottone usato
    var which = wasRewinding ? 'rewind' : 'forward';
    showReplayIcon(which);

    // Ripristina l'ALTRO bottone alla sua icona play normale
    if (wasRewinding && forwardIcon) {
      forwardIcon.innerHTML =
        '<path d="M11 12L20 6V18L11 12Z"></path>' +
        '<path d="M4 12L13 6V18L4 12Z"></path>';
    }
    if (wasForwarding && rewindIcon) {
      rewindIcon.innerHTML =
        '<path d="M11 12L20 6V18L11 12Z"></path>' +
        '<path d="M4 12L13 6V18L4 12Z"></path>';
    }
  } else {
    // Pausa manuale: ripristina entrambe le icone play
    clearReplayState();
    if (rewindIcon) {
      rewindIcon.innerHTML =
        '<path d="M11 12L20 6V18L11 12Z"></path>' +
        '<path d="M4 12L13 6V18L4 12Z"></path>';
    }
    if (forwardIcon) {
      forwardIcon.innerHTML =
        '<path d="M11 12L20 6V18L11 12Z"></path>' +
        '<path d="M4 12L13 6V18L4 12Z"></path>';
    }
  }
}

/**
 * Avvia il loop rAF del time-lapse.
 * @param {number} target  Indice target (dove fermarsi)
 */
function startTimelapseLoop(target) {
  timelapseTargetIndex = target;
  timelapseLastFrameTime = 0; // forza il primo frame subito

  // Primo frame sincrono
  aggiornaLightbox();
  updateNavButtons();

  // Avvia il loop rAF
  timelapseRAF = requestAnimationFrame(timelapseLoop);
}


/* ========================== HELPER ============================== */

function isFiniteNumber(n) { return typeof n === 'number' && isFinite(n); }

function numOrNull(v) {
  return (v === null || v === '' || !isFinite(+v)) ? null : (+v);
}

function get(obj, key) {
  return (obj && obj[key] !== null) ? obj[key] : null;
}

function getStr(obj, key) {
  var v = get(obj, key);
  return (v === null) ? '' : String(v);
}

function pickFirstDefined(obj, keys) {
  if (!obj) return null;
  for (var i = 0; i < keys.length; i++) {
    if (obj[keys[i]] !== null) return obj[keys[i]];
  }
  return null;
}

function dirTesto(v) {
  if (v === null) return '--';
  var deg = +v;
  if (isFinite(deg)) {
    var dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
    var i = Math.round((deg % 360) / 22.5) % 16;
    return dirs[i < 0 ? i + 16 : i];
  }
  return String(v);
}

/**
 * Crop via canvas - usato SOLO in modalita gallery (non time-lapse).
 * Durante il time-lapse il crop avviene via CSS clip-path (zero latenza).
 */
function cropImageBottom(src, cropBottomPx, cb) {
  var tempImg = new Image();
  tempImg.onload = function () {
    try {
      var w = tempImg.width;
      var h = Math.max(1, tempImg.height - cropBottomPx);
      var canvas = document.createElement('canvas');
      canvas.width = w;
      canvas.height = h;
      var ctx = canvas.getContext('2d');
      ctx.drawImage(tempImg, 0, 0, w, h, 0, 0, w, h);
      cb(canvas.toDataURL());
    } catch (e) {
      cb(src);
    }
  };
  tempImg.onerror = function () { cb(src); };
  tempImg.src = src;
}

function buildInfoText(record) {
  var d = record.data_ora || 'N/A';

  if (window.isTimelapseMode) {
    return d;
  }

  var t = parseFloat(record.temp);
  var tTxt = isFinite(t) ? Math.round(t) + '\u00B0C' : 'N/A';
  var hr = parseFloat(record.hr);
  var hTxt = isFinite(hr) ? Math.round(hr) + '%' : 'N/A';
  var p = parseFloat(record.p_hpa);
  var pTxt = isFinite(p) ? Math.round(p) + ' hPa' : 'N/A';
  var windKmh = parseFloat(record.wind_kmh);
  var wTxt = isFinite(windKmh) ? windKmh + ' km/h' : 'N/A';
  var dirGradi = parseFloat(record.dir_text);
  var dTxt = isFinite(dirGradi) ? dirTesto(dirGradi) : 'N/A';
  var sunPhase = '';
  if (record.alba_tramonto) {
    var flag = parseInt(record.alba_tramonto);
    if (flag === 1) sunPhase = ' | Alba';
    else if (flag === 2) sunPhase = ' | Tramonto';
  }
  return d + ' | T ' + tTxt + ' | UR ' + hTxt + ' | ' + pTxt + ' | Vento ' + wTxt + ', ' + dTxt + sunPhase;
}

function applicaClassiMinMaxLightbox(index) {
  var lightboxContent = document.querySelector('.lightbox-content');
  if (!lightboxContent) return;

  lightboxContent.classList.remove('is-min', 'is-max');

  // In time-lapse non applichiamo min/max (rallenta)
  if (window.isTimelapseMode) return;

  var items = window.galleryImages || [];
  if (!items[index]) return;

  var item = items[index];
  var t = numOrNull(get(item, 'temp'));
  var minMaxData = trovaMinMaxTempOggi(window.galleryImages);

  if (minMaxData && t !== null) {
    var dataPiuRecente = estraiDataDaItem(items[0]);
    var dataItem = estraiDataDaItem(item);

    if (dataItem === dataPiuRecente) {
      var tempArrotondata = Math.round(t * 10) / 10;
      if (tempArrotondata === minMaxData.min) {
        lightboxContent.classList.add('is-min');
      } else if (tempArrotondata === minMaxData.max) {
        lightboxContent.classList.add('is-max');
      }
    }
  }
}

/* ======================= RENDERING CORE =========================== */

/**
 * Aggiorna l'immagine e le info nel lightbox.
 *
 * OTTIMIZZAZIONE CHIAVE:
 * - In time-lapse: assegna src direttamente + crop via CSS clip-path
 *   (zero latenza, niente canvas, niente callback asincrono)
 * - In gallery: usa cropImageBottom con canvas (qualita, una tantum)
 */
function aggiornaLightbox() {
  var currentArray = window.isTimelapseMode ? window.fullImages : window.galleryImages;

  var item = currentArray[currentIndex];
  if (!item) return;

  var src = getStr(item, 'src').trim();
  if (!src) return;

  var imgEl = document.getElementById('lightbox-img');
  if (!imgEl) return;

  if (window.isTimelapseMode) {
    // =========================================================
    // TIME-LAPSE: rendering ultra-veloce senza canvas
    // =========================================================

    // Forza il contenitore a dimensioni esplicite cosi la info bar
    // si ancora alle stesse dimensioni dell'immagine visibile
    var contentEl = document.querySelector('.lightbox-content');
    if (contentEl) {
      contentEl.style.width = '95vw';
      contentEl.style.maxHeight = '95vh';
      contentEl.style.overflow = 'hidden';
    }

    // L'immagine riempie il contenitore; il crop-path serve per mascherare
    // il watermark Foscam in basso (~5.5%)
    imgEl.setAttribute('style',
      'display:block !important;' +
      'width:100% !important;' +
      'height:auto !important;' +
      'max-width:100% !important;' +
      'max-height:95vh !important;' +
      'object-fit:cover !important;' +
      'object-position:top center !important;' +
      'clip-path:inset(0 0 ' + CAM_CONFIG.cropBottomPct + ' 0) !important;'
    );
    imgEl.src = src;

    // Info sovrapposta in basso, larga quanto l'immagine, alta 5%
    var infoEl = document.getElementById('lightbox-info');
    if (infoEl) {
      infoEl.textContent = item.data_ora || '';
      infoEl.style.bottom = '0';
      infoEl.style.left = '0';
      infoEl.style.right = '0';
      infoEl.style.width = '100%';
      infoEl.style.height = '5%';
      infoEl.style.display = 'flex';
      infoEl.style.alignItems = 'center';
      infoEl.style.justifyContent = 'center';
      infoEl.style.borderRadius = '0';
      infoEl.style.fontSize = 'clamp(12px, 2.5vw, 16px)';
      infoEl.style.padding = '0';
    }

  } else {
    // =========================================================
    // GALLERY: crop classico via canvas (piu preciso, una tantum)
    // =========================================================

    // Ripristina contenitore
    var contentEl = document.querySelector('.lightbox-content');
    if (contentEl) {
      contentEl.style.width = '';
      contentEl.style.maxHeight = '';
      contentEl.style.overflow = '';
    }

    // Ripristina stile immagine (rimuovi tutti gli inline del time-lapse)
    imgEl.removeAttribute('style');

    // Ripristina info bar (rimuovi stili time-lapse)
    var infoEl = document.getElementById('lightbox-info');
    if (infoEl) {
      infoEl.style.bottom = '';
      infoEl.style.left = '';
      infoEl.style.right = '';
      infoEl.style.width = '';
      infoEl.style.height = '';
      infoEl.style.display = '';
      infoEl.style.alignItems = '';
      infoEl.style.justifyContent = '';
      infoEl.style.borderRadius = '';
      infoEl.style.fontSize = '';
      infoEl.style.padding = '';
    }

    cropImageBottom(src, CAM_CONFIG.cropBottomPx, function (croppedSrc) {
      imgEl.src = croppedSrc;

      imgEl.onload = function() {
        setTimeout(function() {
          applicaClassiMinMaxLightbox(currentIndex);
        }, 10);
      };

      if (imgEl.complete) {
        setTimeout(function() {
          applicaClassiMinMaxLightbox(currentIndex);
        }, 10);
      }
    });

    var infoEl = document.getElementById('lightbox-info');
    if (infoEl) infoEl.textContent = buildInfoText(item);
  }
}

/* ======================== NAVIGAZIONE =========================== */

function openLightbox(index) {
  var items = window.galleryImages || [];
  if (!items.length) return;

  currentIndex = Math.max(0, Math.min(index, items.length - 1));
  window.isTimelapseMode = false;

  var lb = document.getElementById('lightbox');
  if (lb) lb.classList.add('active');

  var map = [
    { id: 'close-btn',   display: 'block' },
    { id: 'rewind-btn',  display: 'flex'  },
    { id: 'forward-btn', display: 'flex'  }
  ];
  for (var i = 0; i < map.length; i++) {
    var btn = document.getElementById(map[i].id);
    if (btn) { btn.style.display = map[i].display; btn.disabled = false; }
  }

  aggiornaLightbox();
  updateNavButtons();
}

function closeLightbox() {
  var lb = document.getElementById('lightbox');
  if (lb) lb.classList.remove('active');

  var b;
  b = document.getElementById('close-btn');   if (b) b.style.display = 'none';
  b = document.getElementById('rewind-btn');  if (b) b.style.display = 'none';
  b = document.getElementById('forward-btn'); if (b) b.style.display = 'none';

  // Ferma qualsiasi playback
  stopTimelapse(false);

  // Resetta stato replay
  clearReplayState();

  window.isTimelapseMode = false;

  // Ripristina stile immagine (rimuovi tutti gli inline del time-lapse)
  var imgEl = document.getElementById('lightbox-img');
  if (imgEl) {
    imgEl.removeAttribute('style');
  }

  // Ripristina contenitore
  var contentEl = document.querySelector('.lightbox-content');
  if (contentEl) {
    contentEl.style.width = '';
    contentEl.style.maxHeight = '';
    contentEl.style.overflow = '';
  }

  // Ripristina stile info bar (rimuovi overlay da time-lapse)
  var infoEl = document.getElementById('lightbox-info');
  if (infoEl) {
    infoEl.style.bottom = '';
    infoEl.style.left = '';
    infoEl.style.right = '';
    infoEl.style.width = '';
    infoEl.style.height = '';
    infoEl.style.display = '';
    infoEl.style.alignItems = '';
    infoEl.style.justifyContent = '';
    infoEl.style.borderRadius = '';
    infoEl.style.fontSize = '';
    infoEl.style.padding = '';
  }
}

function prevImage(event) {
  if (event && event.stopPropagation) event.stopPropagation();

  var currentArray = window.isTimelapseMode ? window.fullImages : window.galleryImages;

  if (currentIndex > 0) {
    currentIndex--;
    aggiornaLightbox();
    updateNavButtons();
  }
}

function nextImage(event) {
  if (event && event.stopPropagation) event.stopPropagation();

  var currentArray = window.isTimelapseMode ? window.fullImages : window.galleryImages;

  if (currentIndex < currentArray.length - 1) {
    currentIndex++;
    aggiornaLightbox();
    updateNavButtons();
  }
}

function updateNavButtons() {
  var currentArray = window.isTimelapseMode ? window.fullImages : window.galleryImages;
  var lastIndex = currentArray.length - 1;

  var prevNav = document.querySelector('.nav-btn.prev');
  var nextNav = document.querySelector('.nav-btn.next');
  if (prevNav) prevNav.disabled = (currentIndex === 0);
  if (nextNav) nextNav.disabled = (currentIndex === lastIndex);

  var rewind  = document.getElementById('rewind-btn');
  var forward = document.getElementById('forward-btn');

  // I bottoni rewind/forward ragionano SEMPRE su fullImages
  if (window.isTimelapseMode) {
    var fullLastIdx = (window.fullImages || []).length - 1;
    if (rewind && !isRewinding) {
      rewind.disabled = (currentIndex === fullLastIdx);
    }
    if (forward && !isForwarding) {
      forward.disabled = (currentIndex === 0);
    }
  } else {
    var galleryItem = window.galleryImages[currentIndex];
    var fullIdx = galleryItem ? galleryItem.fullIndex : 0;
    var fullLastIdx = (window.fullImages || []).length - 1;

    if (rewind && !isRewinding) {
      rewind.disabled = (fullIdx >= fullLastIdx);
    }
    if (forward && !isForwarding) {
      forward.disabled = (fullIdx <= 0);
    }
  }
}

/* ================== PLAYBACK (REWIND / FORWARD) ==================== */

/**
 * REWIND (Bottone SX):
 * SEMPRE parte dalla foto PIU VECCHIA e va fino alla corrente.
 * Se il bottone mostra "R" (replay), ripete l'ultimo time-lapse identico.
 */
function rewindToCurrent() {
  var rewindIcon = document.getElementById('rewind-icon');

  // Se stiamo aspettando replay E il click e sul bottone giusto: REPLAY
  if (isWaitingReplay && lastTimelapseType === 'rewind') {
    // Ripeti l'esatto time-lapse
    var savedStart = lastTimelapseStartIndex;
    var savedTarget = lastTimelapseTargetIndex;
    clearReplayState();

    // Ri-salva i parametri per il PROSSIMO replay
    lastTimelapseType = 'rewind';
    lastTimelapseStartIndex = savedStart;
    lastTimelapseTargetIndex = savedTarget;

    window.isTimelapseMode = true;
    currentIndex = savedStart;
    isRewinding = true;

    if (rewindIcon) {
      rewindIcon.innerHTML =
        '<rect x="6" y="4" width="5" height="16"></rect>' +
        '<rect x="14" y="4" width="5" height="16"></rect>';
    }

    preloadAround(currentIndex, -1);
    startTimelapseLoop(savedTarget);
    return;
  }

  if (isRewinding) {
    // PAUSA
    stopTimelapse(false);
    return;
  }

  // Se stava andando forward, fermalo
  if (isForwarding) {
    stopTimelapse(false);
  }

  // Pulisci eventuale stato replay precedente
  clearReplayState();

  var items = window.fullImages || [];
  if (!items.length) return;

  var lastIdx = items.length - 1;
  var targetIndex;

  if (!window.isTimelapseMode) {
    var galleryItem = window.galleryImages[currentIndex];
    var fullIdx = galleryItem ? galleryItem.fullIndex : 0;

    if (fullIdx >= lastIdx) {
      var rewindBtn = document.getElementById('rewind-btn');
      if (rewindBtn) rewindBtn.disabled = true;
      return;
    }

    window.isTimelapseMode = true;
    targetIndex = fullIdx;
    currentIndex = lastIdx;  // SEMPRE parte da piu vecchia
  } else {
    if (currentIndex >= lastIdx) {
      var rewindBtn = document.getElementById('rewind-btn');
      if (rewindBtn) rewindBtn.disabled = true;
      return;
    }
    targetIndex = currentIndex;
    currentIndex = lastIdx;
  }

  // Salva i parametri per eventuale replay
  lastTimelapseType = 'rewind';
  lastTimelapseStartIndex = currentIndex;
  lastTimelapseTargetIndex = targetIndex;

  isRewinding = true;

  if (rewindIcon) {
    rewindIcon.innerHTML =
      '<rect x="6" y="4" width="5" height="16"></rect>' +
      '<rect x="14" y="4" width="5" height="16"></rect>';
  }

  // Precarica un blocco iniziale di immagini
  preloadAround(currentIndex, -1);

  // Avvia il loop rAF
  startTimelapseLoop(targetIndex);
}

/**
 * FORWARD (Bottone DX):
 * SEMPRE parte dalla corrente e va fino alla PIU RECENTE (#0).
 * Se il bottone mostra "R" (replay), ripete l'ultimo time-lapse identico.
 */
function forwardToNewest() {
  var forwardIcon = document.getElementById('forward-icon');

  // Se stiamo aspettando replay E il click e sul bottone giusto: REPLAY
  if (isWaitingReplay && lastTimelapseType === 'forward') {
    var savedStart = lastTimelapseStartIndex;
    var savedTarget = lastTimelapseTargetIndex;
    clearReplayState();

    // Ri-salva i parametri per il PROSSIMO replay (dopo che finisce di nuovo)
    lastTimelapseType = 'forward';
    lastTimelapseStartIndex = savedStart;
    lastTimelapseTargetIndex = savedTarget;

    window.isTimelapseMode = true;
    currentIndex = savedStart;
    isForwarding = true;

    if (forwardIcon) {
      forwardIcon.innerHTML =
        '<rect x="6" y="4" width="5" height="16"></rect>' +
        '<rect x="14" y="4" width="5" height="16"></rect>';
    }

    preloadAround(currentIndex, -1);
    startTimelapseLoop(savedTarget);
    return;
  }

  if (isForwarding) {
    // PAUSA
    stopTimelapse(false);
    return;
  }

  // Se stava andando rewind, fermalo
  if (isRewinding) {
    stopTimelapse(false);
  }

  // Pulisci eventuale stato replay precedente
  clearReplayState();

  var items = window.fullImages || [];
  if (!items.length) return;

  var startIdx; // salveremo il punto di partenza

  if (!window.isTimelapseMode) {
    var galleryItem = window.galleryImages[currentIndex];
    var fullIdx = galleryItem ? galleryItem.fullIndex : 0;

    if (fullIdx <= 0) {
      var forwardBtn = document.getElementById('forward-btn');
      if (forwardBtn) forwardBtn.disabled = true;
      return;
    }

    window.isTimelapseMode = true;
    currentIndex = fullIdx;
    startIdx = fullIdx;
  } else {
    if (currentIndex <= 0) {
      var forwardBtn = document.getElementById('forward-btn');
      if (forwardBtn) forwardBtn.disabled = true;
      return;
    }
    startIdx = currentIndex;
  }

  // Salva i parametri per eventuale replay
  lastTimelapseType = 'forward';
  lastTimelapseStartIndex = startIdx;
  lastTimelapseTargetIndex = 0;

  isForwarding = true;

  if (forwardIcon) {
    forwardIcon.innerHTML =
      '<rect x="6" y="4" width="5" height="16"></rect>' +
      '<rect x="14" y="4" width="5" height="16"></rect>';
  }

  // Precarica un blocco iniziale verso le piu recenti
  preloadAround(currentIndex, -1);

  // Target = 0 (la piu recente)
  startTimelapseLoop(0);
}

/* ======================= TASTIERA & TOUCH ======================== */

document.addEventListener('keydown', function (event) {
  var lb = document.getElementById('lightbox');
  if (!lb || !lb.classList.contains('active')) return;

  var key = event.key || event.code;

  // SPAZIO: pausa/riprendi
  if (key === ' ' || key === 'Spacebar') {
    event.preventDefault();
    if (isRewinding) rewindToCurrent();
    else if (isForwarding) forwardToNewest();
    return;
  }

  // ESCAPE: chiudi
  if (key === 'Escape' || key === 'Esc') {
    closeLightbox();
    return;
  }

  // FRECCIA SINISTRA: immagine piu vecchia
  if (key === 'ArrowLeft') {
    var currentArray = window.isTimelapseMode ? window.fullImages : window.galleryImages;
    if (currentIndex < currentArray.length - 1) {
      currentIndex++;
      aggiornaLightbox();
      updateNavButtons();
    }
    if (leftHoldInterval === null) {
      leftHoldInterval = setInterval(function () {
        var arr = window.isTimelapseMode ? window.fullImages : window.galleryImages;
        if (currentIndex < arr.length - 1) {
          currentIndex++;
          aggiornaLightbox();
          updateNavButtons();
        } else {
          clearInterval(leftHoldInterval);
          leftHoldInterval = null;
        }
      }, HOLD_DELAY);
    }
  }

  // FRECCIA DESTRA: immagine piu recente
  if (key === 'ArrowRight') {
    if (currentIndex > 0) {
      currentIndex--;
      aggiornaLightbox();
      updateNavButtons();
    }
    if (rightHoldInterval === null) {
      rightHoldInterval = setInterval(function () {
        if (currentIndex > 0) {
          currentIndex--;
          aggiornaLightbox();
          updateNavButtons();
        } else {
          clearInterval(rightHoldInterval);
          rightHoldInterval = null;
        }
      }, HOLD_DELAY);
    }
  }
});

document.addEventListener('keyup', function (event) {
  var key = event.key || event.code;
  if (key === 'ArrowLeft' && leftHoldInterval !== null) {
    clearInterval(leftHoldInterval);
    leftHoldInterval = null;
  }
  if (key === 'ArrowRight' && rightHoldInterval !== null) {
    clearInterval(rightHoldInterval);
    rightHoldInterval = null;
  }
});

document.addEventListener('DOMContentLoaded', function () {
  var lightbox = document.getElementById('lightbox');
  if (!lightbox) return;

  var touchStartX = 0;
  var touchEndX   = 0;

  lightbox.addEventListener('touchstart', function (e) {
    touchStartX = e.changedTouches[0].screenX;
  });

  lightbox.addEventListener('touchend', function (e) {
    touchEndX = e.changedTouches[0].screenX;
    var threshold = 50;
    var currentArray = window.isTimelapseMode ? window.fullImages : window.galleryImages;

    if (touchEndX < touchStartX - threshold) {
      if (currentIndex < currentArray.length - 1) {
        currentIndex++;
        aggiornaLightbox();
        updateNavButtons();
      }
    } else if (touchEndX > touchStartX + threshold) {
      if (currentIndex > 0) {
        currentIndex--;
        aggiornaLightbox();
        updateNavButtons();
      }
    }
  });
});

/* ======================== BOOTSTRAP ============================ */

document.addEventListener('DOMContentLoaded', function () {
  var closeBtn   = document.getElementById('close-btn');
  var rewindBtn  = document.getElementById('rewind-btn');
  var forwardBtn = document.getElementById('forward-btn');

  if (closeBtn)   closeBtn.addEventListener('click', closeLightbox);
  if (rewindBtn)  rewindBtn.addEventListener('click', rewindToCurrent);
  if (forwardBtn) forwardBtn.addEventListener('click', forwardToNewest);

  var thumbs = document.querySelectorAll('.gallery img');
  for (var i = 0; i < thumbs.length; i++) {
    (function (idx) {
      thumbs[idx].addEventListener('click', function () { openLightbox(idx); });
    })(i);
  }

  window.openLightbox       = openLightbox;
  window.closeLightbox      = closeLightbox;
  window.prevImage          = prevImage;
  window.nextImage          = nextImage;
  window.updateNavButtons   = updateNavButtons;
});