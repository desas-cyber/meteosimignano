function aggiornaGalleria() {
  const ora = new Date().toLocaleTimeString();
  console.log(`⏳ [${ora}] Inizio aggiornamento galleria...`);

  fetch('aggiorna_galleria.php')// ******************
    .then(response => response.json())
    .then(dati => {
      console.log(`✅ [${ora}] Ricevute ${dati.length} immagini`);

      const gallery = document.querySelector('.gallery');
      gallery.innerHTML = '';
      images = dati;

      // 🔽 AGGIORNA MAIN IMAGE + DATA
      const mainImage = document.getElementById('main-image');
        const mainDate = document.getElementById('main-image-date');
        
        if (!mainImage || !mainDate) {
          console.warn("⚠️ mainImage o mainDate non trovati nel DOM");
        } else {
          const nuovoSrc = images[0].src + "?t=" + new Date().getTime();
          console.log("🧪 Nuovo src:", nuovoSrc);
        
          mainImage.src = nuovoSrc;
          console.log("🔄 Main image aggiornata a:", nuovoSrc);
        
          mainImage.onclick = () => {
            console.log("🖱️ Click su MAIN image");
            openLightbox(0);
          };
        
          // ✅ Compatibilità anche senza `??`
          const nuovaData = (images[0].data_ora !== undefined && images[0].data_ora !== null)
            ? images[0].data_ora
            : "N/A";
        // TODO: Migliorare in futuro maindatetex, sotto - separare parte statica e dinamica del testo
        // Attualmente sovrascrive tutto il contenuto di <h2>, ma idealmente 
        // si potrebbe aggiornare solo la parte della data con uno <span>
          mainDate.textContent = `Ultima immagine (dir. NO): ${nuovaData}`;
          console.log("📅 Data aggiornata:", nuovaData);
        
      }

      // 🔽 RICOSTRUISCI GALLERIA
      images.forEach((item, index) => {
        const img = document.createElement('img');
        img.src = item.src + "?t=" + new Date().getTime();
        img.alt = "Immagine";
        img.onclick = () => {
          console.log(`🖱️ [${ora}] Click su miniatura ${index}`);
          openLightbox(index);
        };
        gallery.appendChild(img);
      });

      console.log(`🖼️ [${ora}] Galleria aggiornata con successo`);
      updateNavButtons();
    })
    .catch(err => console.error(`❌ [${ora}] Errore durante aggiornamento galleria:`, err));
}

// Ogni 5 minuti
setInterval(aggiornaGalleria, 5 * 59 * 1000);

// Al primo caricamento
document.addEventListener("DOMContentLoaded", aggiornaGalleria);