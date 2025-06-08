<?php
// Esempio di chiavi da associare ai dati ricevuti
$chiavi = [
  'data', 'ora', 'temperatura esterna', 'umidità esterna', 'punto di rugiada',
  'vento medio', 'vento attuale', 'direzione vento', 'pressione',
  'direzione vento (ripetuto)', 'vento in Beaufort', 'unità (m/s)', 'unità (°C)', 'unità (hPa)', 'unità (mm)',
  'pressione 1h fa', 'temperatura interna', 'umidità interna', 'wind chill',
  'temperatura 1h fa', 'temp max', 'ora temp max', 'temp min', 'ora temp min',
  'vento medio max', 'ora vento medio max', 'raffica max', 'ora raffica max',
  'pressione max', 'ora pressione max', 'pressione min', 'ora pressione min',
  'versione software', 'build', 'raffica max ultimi 10 min',
  'spazio1', 'spazio2', 'indice UV', 'spazio3', 'radiazione solare',
  'direzione media 10min', 'spazio4', 'day/night', 'spazio5',
  'direzione media 10min (ripetuto)', 'spazio6', 'unità (m)',
  'spazio7', 'lunghezza del giorno', 'spazio8', 'spazio9', 'indice UV max'
];

// Riceve la query string
if (isset($_GET['d'])) {
  $valori = explode(',', $_GET['d']);

  echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Dati Meteo</title>";
  echo "<style>table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ccc; padding: 8px; }</style>";
  echo "</head><body>";
  echo "<h2>Dati Ricevuti</h2><table>";
  echo "<tr><th>Chiave</th><th>Valore</th></tr>";

  foreach ($valori as $i => $val) {
    $chiave = isset($chiavi[$i]) ? $chiavi[$i] : "Campo $i";
    echo "<tr><td>$chiave</td><td>$val</td></tr>";
  }

  echo "</table></body></html>";
} else {
  echo "Nessun dato ricevuto. Usa ?d=val1,val2,val3,... nell'URL.";
}
?>
