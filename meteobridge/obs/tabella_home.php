<?php
// Mappatura delle chiavi con le descrizioni
$parametri = [
    "mbsystem-sunrise" => "Ora dell'alba",
    "mbsystem-sunset" => "Ora del tramonto",
    "mbsystem-lunarpercent" => "Luna: Fase e luce disco",
    "gradiente_t" => "Gradiente temperatura ultima ora",
    "gradiente_p" => "Gradiente pressione ultima ora",
    "th0temp-act" => "Temperatura attuale",
    "th0temp-dmax" => "Temperatura max e ora",
    "th0temp-dmin" => "Temperatura min e ora",
    "th0hum-act" => "Umidità attuale",
    "th0hum-dmax" => "Umidità max e ora",
    "th0hum-dmin" => "Umidità min e ora",
    "th0dew-act" => "Punto di rugiada attuale",
    "th0dew-dmax" => "Punto di rugiada max e ora",
    "th0dew-dmin" => "Punto di rugiada min e ora",
    "thb0press-act" => "Pressione @lm attuale",
    "thb0seapress-dmax" => "Pressione @lm max e ora",
    "thb0seapress-dmin" => "Pressione @lm min e ora",
    "wind0avgwind-act" => "Velocità media del vento",
    "wind0avgwind-dmax" => "Velocità media  vento max e ora",
    "wind0wind-act" => "Velocità attuale del vento",
    "wind0wind-dmax" => "Velocità max vento e ora",
    "wind0dir-act" => "Direzione del vento",
    "wind0chill-act" => "Differenza per windchill",
    "th0heatindex-act" => "Differenza per indice di calore",
    "sol0rad-act" => "Radiazione solare attuale",
    "sol0rad-sum24h" => "Radianza totale ultime 24 ore",
    "th0temp-age"=> "Tempo da ultima registrazione t/h",
    "thb0press-age"=>"Tempo da ultima registrazione p",
    "wind0wind-age"=>"Tempo da ultima registrazione vento"
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

    // Estrai Data_Ora ultimo rilevamento (primi due valori)
    $data_ora = isset($values[0]) ? $values[0] : "N/A";
    $ultimo_rilevamento = isset($values[1]) ? $values[1] : "N/A";

    echo "<div style='display: flex; justify-content: center; padding: 0 16px;'>"; // Centra la tabella e aggiunge padding
    echo "<table style='border: none; width: 100%; max-width: 800px;'>"; // Tabella responsive
    echo "<thead><tr><th colspan='2' style='text-align: left; padding-right: 8px; white-space: nowrap;'><strong>Data e ora ultima connessione:</strong> " . htmlspecialchars($data_ora . " " . $ultimo_rilevamento) . "</th></tr></thead>";
    echo "<tbody>";
    
    // Associa i valori ai parametri
    $keys = array_keys($parametri); // Ottieni le chiavi dell'array $parametri
    foreach ($keys as $index => $key) {
        $value = isset($values[$index + 2]) ? $values[$index + 2] : "N/A"; // Ignora i primi due valori (data e ora)
        $description = $parametri[$key];
        
        echo "<tr><td style='text-align: left; min-width: 0px; max-width: 400px; white-space: nowrap; padding-right: 8px;'>" . htmlspecialchars($description) . "</td><td style='text-align: left; white-space: nowrap; '>" . htmlspecialchars($value) . "</td></tr>";

        // Aggiunge uno spazio doppio dopo le righe specifiche
        if (in_array($key, [
            "mbsystem-lunarpercent", // % disco lunare
            "gradiente_p", // Gradiente pressione
            "th0temp-dmin", // Temp min e orario
            "th0hum-dmin", // Umid min e orario
            "th0dew-dmin", // Punto di rug min e ora
            "thb0seapress-dmin", // Pressione min e orario
            "wind0dir-act", // Dire vento
            "th0heatindex-act", // Diff indice cal
            "sol0rad-sum24h" //integrale radianza
        ])) {
            echo "<tr><td colspan='2' style='height: 16px;'></td></tr>"; // Riga vuota con spazio doppio
        }
    }
    
    echo "</tbody></table>";
    echo "</div>"; // Chiudi il div di centratura
} else {
    echo "Nessun dato disponibile.";
}
?>
