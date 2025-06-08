<?php
// Specifichiamo il nome del file dove salvare il valore
$file = 'temp.txt';

// Controlliamo se è stato inviato un nuovo valore tramite la query string
if (isset($_GET['temp'])) {
    $temp = $_GET['temp'];
    // Scriviamo il valore nel file
    file_put_contents($file, $temp);
}

// Leggiamo il valore dal file
$temp = file_exists($file) ? file_get_contents($file) : 'Parametro non trovato';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizzazione temperatura</title>
</head>
<body>

<h1>Temperatura Corrente</h1>
<div>
    Il valore di temp è: <strong><?php echo htmlspecialchars($temp); ?></strong>
</div>

</body>
</html>