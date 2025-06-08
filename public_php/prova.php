<?php
$directory = "/home/erbielqv/public_html/FoscamCamera_E8ABFAA799FE/snap";

// Controlla se la directory esiste
if (file_exists($directory)) {
    echo "La directory esiste!<br>";
    
    // Mostra i permessi della cartella
    echo "Permessi: " . substr(sprintf('%o', fileperms($directory)), -4) . "<br>";
    
    // Lista i file nella cartella
    $files = scandir($directory);
    if ($files !== false) {
        echo "File nella cartella:<br>";
        foreach ($files as $file) {
            echo $file . "<br>";
        }
    } else {
        echo "Impossibile leggere i file nella cartella.<br>";
    }
} else {
    echo "La directory NON esiste o non è accessibile.";
}
?>

