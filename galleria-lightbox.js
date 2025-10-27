/* =================================================================
 *  LIGHTBOX GALLERIA — Script ES5 compatibile
 * =================================================================
 *
 *  Cosa fa:
 *  - Apre la lightbox alla miniatura cliccata
 *  - Mostra l’immagine (con crop verticale) e una riga info (data/ora, T, UR, p, vento)
 *  - Navigazione: tastiera (← →, ESC), bottoni prev/next, swipe touch
 *  - “Rewind” e “Forward” automatici (play inverso/avanti) con toggle pausa
 *  - Aggiorna lo stato dei bottoni in modo consistente
 *
 *  Dipendenze:
 *  - window.images: array di record [{src, data_ora, temp, hr, p_hpa, vento/wind_ms/wind_kmh, dir/dir_text}, …]
 *  - HTML con:
 *      #lightbox, #lightbox-img, #lightbox-info
 *      .nav-btn.prev, .nav-btn.next
 *      (opzionali): #close-btn, #rewind-btn(#rewind-icon), #forward-btn(#forward-icon)
 *
 *  Note:
 *  - Nessun uso di ?. o ?? o arrow => compatibile con motori JS datati.
 *  - La funzione dirTesto gestisce sia gradi che sigle testuali già pronte.
 *  - Il testo “info” usa km/h se disponibili, altrimenti prova da m/s→km/h.
 * ================================================================= */

/* ========================== STATO =============================== */

var currentIndex     = 0;

var rewindInterval   = null;  // timer per rewind (indietro automatico)
var isRewinding      = false;

var forwardInterval  = null;  // timer per forward (avanti automatico)
var isForwarding     = false;

var leftHoldInterval = null;  // ripetizione continua freccia sinistra
var rightHoldInterval= null;  // ripetizione continua freccia destra
var HOLD_DELAY       = 1000 / 3; // ~333ms → 3 passi/sec

/* ========================== HELPER ============================== */

/** Numero finito? (ES5-safe) */
function isFiniteNumber(n) { return typeof n === 'number' && isFinite(n); }

/** Numero o null */
function numOrNull(v) {
  return (v === null || v === '' || !isFinite(+v)) ? null : (+v);
}

/** Getter sicuro */
function get(obj, key) {
  return (obj && obj[key] !== null) ? obj[key] : null;
}

/** Stringa sicura */
function getStr(obj, key) {
  var v = get(obj, key);
  return (v === null) ? '' : String(v);
}

/** Primo tra più campi definiti */
function pickFirstDefined(obj, keys) {
  if (!obj) return null;
  for (var i = 0; i < keys.length; i++) {
    if (obj[keys[i]] !== null) return obj[keys[i]];
  }
  return null;
}

/** Direzione in testo:
 *  - se input è numerico (gradi), converte in N/NE/...
 *  - se è stringa già “NE”, la restituisce così com’è
 */
function dirTesto(v) {
  if (v === null) return '--';
  var deg = +v;
  if (isFinite(deg)) {
    var dirs = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
    var i = Math.round((deg % 360) / 22.5) % 16;
    return dirs[i < 0 ? i + 16 : i];
  }
  // valore già testuale (es. "NE")
  return String(v);
}

/** Crop verticale dell’immagine (taglia 80px in basso). Ritorna dataURL. */
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
      // fallback: nessun crop se canvas fallisce
      cb(src);
    }
  };
  tempImg.onerror = function () { cb(src); };
  tempImg.src = src;
}

/** Costruisce la stringa info dell’immagine corrente. */
function buildInfoText(record) {
  // data/ora formattata (già “dd/mm/yyyy HH:MM” lato backend)
  var d = getStr(record, 'data_ora') || 'N/A';

  // temperatura / umidità / pressione
  var t = numOrNull(get(record,'temp'));
  var tTxt = (t === null ? 'N/A' : Math.round(t) + '°C');

  var hr = numOrNull(get(record,'hr'));
  var hTxt = (hr === null ? 'N/A' : Math.round(hr) + '%');

  var p  = numOrNull(pickFirstDefined(record, ['p_hpa','press_hpa','pressione_hpa','pressure_hpa','pressure','mbar','press_mb']));
  var pTxt = (p === null ? 'N/A' : Math.round(p) + ' hPa');

  // vento: preferisci km/h se disponibile, altrimenti m/s→km/h
  var windKmh = numOrNull(get(record,'wind_kmh'));
  var windMs  = numOrNull(pickFirstDefined(record, ['vento','wind_ms'])); // alias legacy
  if (windKmh === null && windMs !== null) windKmh = Math.round(windMs * 3.6);

  var wTxt = (windKmh === null ? 'N/A' : windKmh + ' km/h');

  // direzione
  var dTxt = dirTesto(pickFirstDefined(record, ['dir','dir_text','Dir_text'])) || 'N/A';

  return d + ' | T ' + tTxt + ' | UR ' + hTxt + ' | ' + pTxt + ' | Vento ' + wTxt + (dTxt ? (', ' + dTxt) : '');
}

/* ======================= RENDERING CORE =========================== */

/**
 * Aggiorna l’immagine e la riga info in base a currentIndex.
 * - Esegue crop in basso (80px)
 * - Imposta #lightbox-img.src e #lightbox-info.textContent
 */
function aggiornaLightbox() {
  var items = window.images || [];
  var record = items[currentIndex];
  if (!record) return;

  var src = getStr(record, 'src').trim();
  if (!src) return;

  // Crop e set immagine
  cropImageBottom(src, 80, function (croppedSrc) {
    var imgEl = document.getElementById('lightbox-img');
    if (imgEl) imgEl.src = croppedSrc;
  });

  // Info text
  var infoEl = document.getElementById('lightbox-info');
  if (infoEl) infoEl.textContent = buildInfoText(record);
}

/* ======================== NAVIGAZIONE =========================== */

function openLightbox(index) {
  var items = window.images || [];
  if (!items.length) return;

  currentIndex = Math.max(0, Math.min(index, items.length - 1));
  aggiornaLightbox();

  var lb = document.getElementById('lightbox');
  if (lb) lb.classList.add('active');

  // mostra eventuali pulsanti extra
  var map = [
    { id: 'close-btn',   display: 'block' },
    { id: 'rewind-btn',  display: 'flex'  },
    { id: 'forward-btn', display: 'flex'  }
  ];
  for (var i = 0; i < map.length; i++) {
    var btn = document.getElementById(map[i].id);
    if (btn) { btn.style.display = map[i].display; btn.disabled = false; }
  }
  updateNavButtons();
}

function closeLightbox() {
  var lb = document.getElementById('lightbox');
  if (lb) lb.classList.remove('active');

  var b;
  b = document.getElementById('close-btn');   if (b) b.style.display = 'none';
  b = document.getElementById('rewind-btn');  if (b) b.style.display = 'none';
  b = document.getElementById('forward-btn'); if (b) b.style.display = 'none';

  // stop timer
  if (rewindInterval)  { clearInterval(rewindInterval);  rewindInterval  = null; }
  if (forwardInterval) { clearInterval(forwardInterval); forwardInterval = null; }
  isRewinding = false; isForwarding = false;

  // ripristina icone se presenti
  var rewindIcon  = document.getElementById('rewind-icon');
  var forwardIcon = document.getElementById('forward-icon');
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

/** Bottoni prev/next base */
function prevImage(event) {
  if (event && event.stopPropagation) event.stopPropagation();
  var items = window.images || [];
  if (currentIndex > 0) {
    currentIndex--;
    aggiornaLightbox();
    updateNavButtons();
  }
}

function nextImage(event) {
  if (event && event.stopPropagation) event.stopPropagation();
  var items = window.images || [];
  if (currentIndex < items.length - 1) {
    currentIndex++;
    aggiornaLightbox();
    updateNavButtons();
  }
}

/** Aggiorna stato bottoni (enabled/disabled) */
function updateNavButtons() {
  var items = window.images || [];
  var lastIndex = items.length - 1;

  var prevNav = document.querySelector('.nav-btn.prev');
  var nextNav = document.querySelector('.nav-btn.next');
  if (prevNav) prevNav.disabled = (currentIndex === 0);
  if (nextNav) nextNav.disabled = (currentIndex === lastIndex);

  var rewind  = document.getElementById('rewind-btn');
  var forward = document.getElementById('forward-btn');
  if (rewind)  rewind.disabled  = (currentIndex === lastIndex);
  if (forward) forward.disabled = (currentIndex === 0);
}

/* ================== PLAYBACK (REWIND / FORWARD) ==================== */

function rewindToCurrent() {
  var items = window.images || [];
  var rewindIcon = document.getElementById('rewind-icon');
  if (!items.length) return;

  if (isRewinding) {
    // pausa
    clearInterval(rewindInterval);
    rewindInterval = null;
    isRewinding = false;
    if (rewindIcon) {
      rewindIcon.innerHTML =
        '<path d="M11 12L20 6V18L11 12Z"></path>' +
        '<path d="M4 12L13 6V18L4 12Z"></path>';
    }
    return;
  }

  // avvio rewind: dalla fine verso currentIndex
  var targetIndex = currentIndex;
  currentIndex = items.length - 1;
  aggiornaLightbox(); updateNavButtons();
  isRewinding = true;

  if (rewindIcon) {
    rewindIcon.innerHTML =
      '<rect x="6" y="4" width="5" height="16"></rect>' +
      '<rect x="14" y="4" width="5" height="16"></rect>';
  }

  rewindInterval = setInterval(function () {
    if (currentIndex <= targetIndex) {
      clearInterval(rewindInterval);
      rewindInterval = null;
      isRewinding = false;
      if (rewindIcon) {
        rewindIcon.innerHTML =
          '<path d="M11 12L20 6V18L11 12Z"></path>' +
          '<path d="M4 12L13 6V18L4 12Z"></path>';
      }
      return;
    }
    currentIndex--;
    aggiornaLightbox(); updateNavButtons();
  }, 300);
}

function forwardToNewest() {
  var items = window.images || [];
  var forwardBtn  = document.getElementById('forward-btn');
  var forwardIcon = document.getElementById('forward-icon');
  if (!items.length) return;

  if (isForwarding) {
    clearInterval(forwardInterval);
    forwardInterval = null;
    isForwarding = false;
    if (forwardIcon) {
      forwardIcon.innerHTML =
        '<path d="M11 12L20 6V18L11 12Z"></path>' +
        '<path d="M4 12L13 6V18L4 12Z"></path>';
    }
    return;
  }

  if (currentIndex === 0) {
    if (forwardBtn) forwardBtn.disabled = true;
    if (forwardIcon) {
      forwardIcon.innerHTML =
        '<path d="M11 12L20 6V18L11 12Z"></path>' +
        '<path d="M4 12L13 6V18L4 12Z"></path>';
    }
    return;
  }

  // se rewind attivo → fermalo
  if (rewindInterval) {
    clearInterval(rewindInterval);
    rewindInterval = null;
    isRewinding = false;
    var rewindIcon = document.getElementById('rewind-icon');
    if (rewindIcon) {
      rewindIcon.innerHTML =
        '<path d="M13 12L4 6V18L13 12Z"></path>' +
        '<path d="M20 12L11 6V18L20 12Z"></path>';
    }
  }

  isForwarding = true;
  if (forwardIcon) {
    forwardIcon.innerHTML =
      '<rect x="6" y="4" width="5" height="16"></rect>' +
      '<rect x="14" y="4" width="5" height="16"></rect>';
  }

  forwardInterval = setInterval(function () {
    if (currentIndex <= 0) {
      clearInterval(forwardInterval);
      forwardInterval = null;
      isForwarding = false;
      if (forwardIcon) {
        forwardIcon.innerHTML =
          '<path d="M11 12L20 6V18L11 12Z"></path>' +
          '<path d="M4 12L13 6V18L4 12Z"></path>';
      }
      if (forwardBtn) forwardBtn.disabled = true;
      return;
    }
    currentIndex--;
    aggiornaLightbox(); updateNavButtons();
  }, 300);
}

/* ======================= TASTIERA & TOUCH ======================== */

// Keydown con auto-repeat su frecce
document.addEventListener('keydown', function (event) {
  var lb = document.getElementById('lightbox');
  if (!lb || !lb.classList.contains('active')) return;

  var key = event.key || event.code;

  if (key === ' ' || key === 'Spacebar') {
    event.preventDefault();
    if (isRewinding) rewindToCurrent();
    else if (isForwarding) forwardToNewest();
    return;
  }

  if (key === 'Escape' || key === 'Esc') {
    closeLightbox();
    return;
  }

  if (key === 'ArrowLeft') {
    var items = window.images || [];
    if (currentIndex < items.length - 1) {
      currentIndex++; aggiornaLightbox(); updateNavButtons();
    }
    if (leftHoldInterval === null) {
      leftHoldInterval = setInterval(function () {
        if (currentIndex < (window.images ? window.images.length - 1 : 0)) {
          currentIndex++; aggiornaLightbox(); updateNavButtons();
        } else {
          clearInterval(leftHoldInterval); leftHoldInterval = null;
        }
      }, HOLD_DELAY);
    }
  }

  if (key === 'ArrowRight') {
    if (currentIndex > 0) {
      currentIndex--; aggiornaLightbox(); updateNavButtons();
    }
    if (rightHoldInterval === null) {
      rightHoldInterval = setInterval(function () {
        if (currentIndex > 0) {
          currentIndex--; aggiornaLightbox(); updateNavButtons();
        } else {
          clearInterval(rightHoldInterval); rightHoldInterval = null;
        }
      }, HOLD_DELAY);
    }
  }
});

// Interrompi auto-repeat al rilascio
document.addEventListener('keyup', function (event) {
  var key = event.key || event.code;
  if (key === 'ArrowLeft' && leftHoldInterval !== null) {
    clearInterval(leftHoldInterval); leftHoldInterval = null;
  }
  if (key === 'ArrowRight' && rightHoldInterval !== null) {
    clearInterval(rightHoldInterval); rightHoldInterval = null;
  }
});

// Touch swipe su lightbox
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
    if (touchEndX < touchStartX - threshold) {
      // swipe left → avanti nel tempo (indice +1)
      var items = window.images || [];
      if (currentIndex < items.length - 1) {
        currentIndex++; aggiornaLightbox(); updateNavButtons();
      }
    } else if (touchEndX > touchStartX + threshold) {
      // swipe right → indietro nel tempo (indice -1)
      if (currentIndex > 0) {
        currentIndex--; aggiornaLightbox(); updateNavButtons();
      }
    }
  });
});

/* ======================== BOOTSTRAP ============================ */

// Wire dei bottoni e delle miniature
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

  // Espone alcune funzioni in window se servono altrove
  window.openLightbox       = openLightbox;
  window.closeLightbox      = closeLightbox;
  window.prevImage          = prevImage;
  window.nextImage          = nextImage;
  window.updateNavButtons   = updateNavButtons;
});
