function aggiornaGalleria() {
    fetch('aggiorna_contenuto.php')  // Recupera i nuovi dati dal server
    .then(response => response.text())  // Converte la risposta in testo HTML
    .then(data => {
        let tempDiv = document.createElement("div"); // Creiamo un div temporaneo
        tempDiv.innerHTML = data; // Inseriamo l'HTML ricevuto

        // Aggiorniamo solo le sezioni necessarie
        document.getElementById("tabella-meteo").innerHTML = tempDiv.querySelector("#tabella-meteo").innerHTML;
        document.querySelector(".gallery").innerHTML = tempDiv.querySelector(".gallery").innerHTML;
        document.querySelector(".main-container").innerHTML = tempDiv.querySelector(".main-container").innerHTML;


        let nuovaGalleria = tempDiv.querySelector(".gallery"); // Selezioniamo la nuova galleria
        let galleriaAttuale = document.querySelector(".gallery"); // Selezioniamo la vecchia galleria

        if (nuovaGalleria && galleriaAttuale) {
            galleriaAttuale.innerHTML = nuovaGalleria.innerHTML; // Aggiorniamo il contenuto
        }
        // Aggiorniamo anche la variabile images con l'elenco aggiornato delle immagini
        images = data.images;
        console.log("Lista immagini aggiornata:", images);
    })
    .catch(error => console.error("Errore nel caricamento:", error));
}

// Aggiorna la galleria ogni 1 minuti (60000 ms)
setInterval(aggiornaGalleria, 60000);

// Esegui subito un primo aggiornamento all'apertura della pagina
aggiornaGalleria();

