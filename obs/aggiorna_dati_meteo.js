function aggiornaDati() {
    fetch('aggiorna_dati_meteo.php')
        .then(response => {
            // Se la risposta non è OK, esci
            if (!response.ok) throw new Error('Errore di rete');
            
            return response.json();
        })
        .then(data => {
            if (data.html) {
                document.getElementById("tabella-meteo").innerHTML = data.html;
            } else {
                console.warn('Nessun contenuto trovato.');
            }
        })
        .catch(error => {
            console.error('Errore:', error);
        });
        console.log('Aggiornato iframe meteo');
}

// Esegui l'aggiornamento 5 minuti
setInterval(aggiornaDati, 300000);

// Esegui l'aggiornamento all'avvio della pagina
document.addEventListener('DOMContentLoaded', aggiornaDati);
