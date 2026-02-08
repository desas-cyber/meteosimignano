/* =================================================================
 *  LIGHTBOX GALLERIA - Logica time-lapse CORRETTA
 * =================================================================
 *
 *  LOGICA DEFINITIVA:
 *  - Bottone SX (⏪): SEMPRE parte da #99 (più vecchia) → fino alla corrente
 *  - Bottone DX (⏩): SEMPRE parte dalla corrente → fino a #0 (più recente)
 *
 *  DISABILITAZIONE:
 *  - SX: disabilitato SOLO se sei su #99 (più vecchia - niente prima)
 *  - DX: disabilitato SOLO se sei su #0 (più recente - niente dopo)
 *
 *  ORDINE ARRAY:
 *  - Index 0 = foto PIÙ RECENTE
 *  - Index MAX = foto PIÙ VECCHIA
 * ================================================================= */

/* ========================== STATO =============================== */

var currentIndex     = 0;

var rewindInterval   = null;
var isRewinding      = false;

var forwardInterval  = null;
var isForwarding     = false;

var leftHoldInterval = null;
var rightHoldInterval= null;
var HOLD_DELAY       = 1000 / 3;

window.isTimelapseMode = false;

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
  var tTxt = isFinite(t) ? Math.round(t) + '°C' : 'N/A';
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

function aggiornaLightbox() {
  var currentArray = window.isTimelapseMode ? window.fullImages : window.galleryImages;
  
  var item = currentArray[currentIndex];
  if (!item) return;

  var src = getStr(item, 'src').trim();
  if (!src) return;

  cropImageBottom(src, 80, function (croppedSrc) {
    var imgEl = document.getElementById('lightbox-img');
    if (imgEl) {
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
    }
  });

  var infoEl = document.getElementById('lightbox-info');
  if (infoEl) infoEl.textContent = buildInfoText(item);
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

  if (rewindInterval)  { clearInterval(rewindInterval);  rewindInterval  = null; }
  if (forwardInterval) { clearInterval(forwardInterval); forwardInterval = null; }
  isRewinding = false; isForwarding = false;
  
  window.isTimelapseMode = false;

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
  
  // IMPORTANTE: I bottoni rewind/forward ragionano SEMPRE su fullImages
  // Anche quando siamo in gallery mode!
  if (window.isTimelapseMode) {
    // In time-lapse: usa currentIndex direttamente
    var fullLastIdx = (window.fullImages || []).length - 1;
    if (rewind && !isRewinding) {
      rewind.disabled = (currentIndex === fullLastIdx);
    }
    if (forward && !isForwarding) {
      forward.disabled = (currentIndex === 0);
    }
  } else {
    // In gallery: usa il fullIndex dell'elemento corrente
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
 * REWIND (Bottone SX ⏪):
 * SEMPRE parte dalla foto PIÙ VECCHIA (#99) e va fino alla corrente
 */
function rewindToCurrent() {
  var rewindIcon = document.getElementById('rewind-icon');
  
  if (isRewinding) {
    // PAUSA
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

  var items = window.fullImages || [];
  if (!items.length) return;
  
  var lastIdx = items.length - 1;
  var targetIndex;
  
  if (!window.isTimelapseMode) {
    var galleryItem = window.galleryImages[currentIndex];
    var fullIdx = galleryItem ? galleryItem.fullIndex : 0;
    
    // Se siamo già sulla più vecchia, disabilita
    if (fullIdx >= lastIdx) {
      var rewindBtn = document.getElementById('rewind-btn');
      if (rewindBtn) rewindBtn.disabled = true;
      return;
    }
    
    window.isTimelapseMode = true;
    targetIndex = fullIdx;
    currentIndex = lastIdx;  // SEMPRE parte da più vecchia
  } else {
    // Già in time-lapse
    if (currentIndex >= lastIdx) {
      var rewindBtn = document.getElementById('rewind-btn');
      if (rewindBtn) rewindBtn.disabled = true;
      return;
    }
    targetIndex = currentIndex;
    currentIndex = lastIdx;
  }

  isRewinding = true;
  
  if (rewindIcon) {
    rewindIcon.innerHTML =
      '<rect x="6" y="4" width="5" height="16"></rect>' +
      '<rect x="14" y="4" width="5" height="16"></rect>';
  }

  // Primo frame subito
  aggiornaLightbox();
  updateNavButtons();

  rewindInterval = setInterval(function () {
    currentIndex--;  // Vai verso 0 (più recente)
    
    if (currentIndex <= targetIndex) {
      currentIndex = Math.max(0, targetIndex);
      aggiornaLightbox();
      updateNavButtons();
      
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
    
    aggiornaLightbox();
    updateNavButtons();
  }, 200);
}

/**
 * FORWARD (Bottone DX ⏩):
 * SEMPRE parte dalla corrente e va fino alla PIÙ RECENTE (#0)
 */
function forwardToNewest() {
  var forwardIcon = document.getElementById('forward-icon');

  if (isForwarding) {
    // PAUSA
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

  var items = window.fullImages || [];
  if (!items.length) return;

  if (!window.isTimelapseMode) {
    var galleryItem = window.galleryImages[currentIndex];
    var fullIdx = galleryItem ? galleryItem.fullIndex : 0;
    
    // Se siamo già sulla più recente (0), disabilita
    if (fullIdx <= 0) {
      var forwardBtn = document.getElementById('forward-btn');
      if (forwardBtn) forwardBtn.disabled = true;
      return;
    }
    
    window.isTimelapseMode = true;
    currentIndex = fullIdx;  // Parte dalla corrente
  } else {
    // Già in time-lapse
    if (currentIndex <= 0) {
      var forwardBtn = document.getElementById('forward-btn');
      if (forwardBtn) forwardBtn.disabled = true;
      return;
    }
  }

  if (rewindInterval) {
    clearInterval(rewindInterval);
    rewindInterval = null;
    isRewinding = false;
    var rewindIcon = document.getElementById('rewind-icon');
    if (rewindIcon) {
      rewindIcon.innerHTML =
        '<path d="M11 12L20 6V18L11 12Z"></path>' +
        '<path d="M4 12L13 6V18L4 12Z"></path>';
    }
  }

  isForwarding = true;
  if (forwardIcon) {
    forwardIcon.innerHTML =
      '<rect x="6" y="4" width="5" height="16"></rect>' +
      '<rect x="14" y="4" width="5" height="16"></rect>';
  }

  // Primo frame subito
  aggiornaLightbox();
  updateNavButtons();

  forwardInterval = setInterval(function () {
    currentIndex--;  // Vai verso 0 (più recente)
    
    if (currentIndex <= 0) {
      currentIndex = 0;
      aggiornaLightbox();
      updateNavButtons();
      
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
    
    aggiornaLightbox();
    updateNavButtons();
  }, 200);
}

/* ======================= TASTIERA & TOUCH ======================== */

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