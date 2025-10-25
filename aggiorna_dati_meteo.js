function reloadIframeMeteo() {
  const iframe = document.getElementById('tabella-meteo-iframe')
              || document.querySelector('.tabella-meteo iframe');

  if (!iframe) {
    console.warn('⚠️ Iframe meteo non trovato → aggiornaDati saltato');
    return;
  }

  // Evita cache con timestamp
  const base = (iframe.getAttribute('data-src') || iframe.src).split('?')[0];
  iframe.src = `${base}?t=${Date.now()}`;
  console.log('🔄 Iframe meteo ricaricato');
}

// Ricarica al caricamento pagina
document.addEventListener('DOMContentLoaded', reloadIframeMeteo);

// Ricarica ogni 5 minuti
setInterval(reloadIframeMeteo, 5 * 60 * 1000);

// (opzionale) se la tab torna visibile, ricarica
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible') reloadIframeMeteo();
});
