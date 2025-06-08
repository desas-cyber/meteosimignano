<?php
// Mappatura delle chiavi con le descrizioni
$parametri = [
    "th0temp-age" => "Tempo dall'ultima acquisizione",
    "mbsystem-sunrise" => "Ora dell'alba",
    "mbsystem-sunset" => "Ora del tramonto",
    "mbsystem-lunarpercent" => "% disco lunare",
    "gradiente_t" => "Gradiente temperatura ultima ora",
    "gradiente_p" => "Gradiente pressione ultima ora",
    "th0temp-act" => "Temperatura attuale",
    "th0temp-dmax" => "Temperatura massima e orario",
    "th0temp-dmin" => "Temperatura minima e orario",
    "th0hum-act" => "Umidità attuale",
    "th0hum-dmax" => "Umidità massima e orario",
    "th0hum-dmin" => "Umidità minima e orario",
    "th0dew-act" => "Punto di rugiada attuale",
    "th0dew-dmax" => "Punto di rugiada massimo e orario",
    "th0dew-dmin" => "Punto di rugiada minimo e orario",
    "thb0press-act" => "Pressione atmosferica attuale",
    "thb0seapress-dmax" => "Pressione atmosferica massima e orario",
    "thb0seapress-dmin" => "Pressione atmosferica minima e orario",
    "wind0avgwind-act" => "Velocità media del vento",
    "wind0avgwind-dmax" => "Velocità media del vento massima e orario",
    "wind0wind-act" => "Velocità attuale del vento",
    "wind0wind-dmax" => "Velocità massima del vento e orario",
    "wind0dir-act" => "Direzione del vento",
    "wind0chill-act" => "Differenza per windchill",
    "th0heatindex-act" => "Differenza per indice di calore",
    "sol0rad-act" => "Radiazione solare attuale",
    "sol0rad-sum24h" => "Radiazione solare totale nelle ultime 24 ore"
];


// Funzione per salvare i dati in un file
function saveDataToFile($data) {
    $file = 'dati_temperatura.txt'; // Percorso del file
    file_put_contents($file, $data);
}

// Funzione per leggere i dati dal file
function readDataFromFile() {
    $file = 'dati_temperatura.txt'; // Percorso del file
    if (file_exists($file)) {
        return file_get_contents($file);
    }
    return null;
}

// Se i dati sono stati passati via URL, salvali nel file
if (isset($_GET['temp'])) {
    $data = $_GET['temp'];
    saveDataToFile($data); // Salva i dati nel file
}

// Leggi i dati dal file
$data = readDataFromFile();

if ($data) {
    $values = explode(",", $data); // Splitta i dati in un array
    echo "<table border='1'>";
    echo "<thead><tr><th>Descrizione</th><th>Valore</th></tr></thead><tbody>";
    
    // Associa i valori ai parametri
    $keys = array_keys($parametri); // Ottieni le chiavi dell'array $parametri
    foreach ($keys as $index => $key) {
        $value = isset($values[$index + 2]) ? $values[$index + 2] : "N/A"; // Ignora i primi due valori (data e ora)
        $description = $parametri[$key];
        
        echo "<tr><td>" . htmlspecialchars($description) . "</td><td>" . htmlspecialchars($value) . "</td></tr>";
    }
    
    echo "</tbody></table>";
} else {
    echo "Nessun dato disponibile.";
}
?>
