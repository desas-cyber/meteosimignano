function aggiornaGalleria() {
    fetch('aggiorna_contenuto.php')
        .then(response => response.json())
        .then(data => {
            console.log('Lista immagini aggiornata:', data);
            
            // Controlla se ci sono immagini
            if (data.length > 0) {
                // Aggiorna l'immagine principale
                const mainImageContainer = document.querySelector('.main-container');
                if (mainImageContainer) {
                    mainImageContainer.innerHTML = `
                        <img src="${data[0].src}" alt="Immagine principale" class="main-image" onclick="openLightbox(0)">
                        <h2 class="date-text">Ultima immagine (dir. NO): ${data[0].date}</h2>
                    `;
                } else {
                    console.error('Elemento .main-container non trovato');
                }

                // Aggiorna la galleria
                const galleryContainer = document.querySelector('.gallery');
                if (galleryContainer) {
                    galleryContainer.innerHTML = data.map((img, index) => 
                        `<img src="${img.src}" alt="Immagine" onclick="openLightbox(${index})">`
                    ).join('');
                } else {
                    console.error('Elemento .gallery non trovato');
                }
            } else {
                console.log('Nessuna immagine trovata');
                // Puoi aggiungere qui del codice per gestire il caso di nessuna immagine
            }
            
            document.getElementById("tabella-meteo").innerHTML = tempDiv.querySelector("#tabella-meteo").innerHTML;
        })
        .catch(error => {
            console.error('Errore durante l\'aggiornamento della galleria:', error);
        });
}

// Esegui l'aggiornamento ogni 5 minuti (300000 millisecondi)
setInterval(aggiornaGalleria, 300000);

// Esegui l'aggiornamento all'avvio della pagina
document.addEventListener('DOMContentLoaded', aggiornaGalleria);