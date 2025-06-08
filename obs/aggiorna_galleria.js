
function aggiornaGalleria() {
  const ora = new Date().toLocaleTimeString();
  console.log(`⏳ [${ora}] Inizio aggiornamento galleria...`);

  fetch('aggiorna_galleria.php')
    .then(r => r.json())
    .then(images => {
      console.log(`✅ [${ora}] Ricevute ${images.length} immagini`);
      // qui NON costruisci più il DOM, limitiamoci a inviare i dati
      document.dispatchEvent(
        new CustomEvent('galleryUpdated', { detail: images })
      );
    })
    .catch(err => console.error(`❌ Errore aggiornamento galleria:`, err));
}

document.addEventListener("DOMContentLoaded", aggiornaGalleria);
setInterval(aggiornaGalleria, 5 * 60 * 1000);