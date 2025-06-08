Added to Base-World on 10-Apr-2020 is the capability to use Aerisweather weather forecasts.
See https://saratoga-weather.org/scripts-AWforecast.php for additional details.

You will need a free API key to use the AW-forecast.php script.  You can get one by uploading
weather data to pwsweather.com

To enable in your Base-* template, add the following lines to your Settings.php:

//-----code below----- (don't include this line)

//*
// --- Aerisweather.com forecast variables ---
$SITE['fcstscript'] = 'AW-forecast.php';
$SITE['fcstorg']    = 'Aerisweather'; // set to 'Aerisweather' for Aerisweather.net

$SITE['AWAPIkey'] = 'your-api-client-key-here'; // Your API Access ID key
$SITE['AWAPIsecret'] = 'your-api-secret-key-here'; // Your API key

// Aerisweather display Units:
// si: SI units (C,m/s,hPa,mm,km)
// ca: same as si, except that windSpeed and windGust are in kilometers per hour
// uk2: same as si, except that nearestStormDistance and visibility are in miles, and windSpeed and windGust in miles per hour
// us: Imperial units (F,mph,inHg,in,miles)

$SITE['AWshowUnitsAs'] = 'us'; // ='us' for imperial, , ='si' for metric, ='ca' for canada, ='uk2' for UK
$SITE['AWforecasts'] = array(
 // Location|lat,long  (separated by | characters)
'Saratoga, CA, USA|37.27465,-122.02295',
'Auckland, NZ|-36.910,174.771', // Awhitu, Waiuku New Zealand
'Assen, NL|53.02277,6.59037',
'Blankenburg, DE|51.8089941,10.9080649',
'Carcassonne, FR|43.2077801,2.2790407',
'Braniewo, PL|54.3793635,19.7853585',
'Omaha, NE, USA|41.19043,-96.13114',
'Johanngeorgenstadt, DE|50.439339,12.706085',
'Athens, GR|37.97830,23.715363',
'Haifa, IL|32.7996029,34.9467358',
);
// --- end of Aerisweather forecast variables ---
//*/

//-----code above----- (don't include this line)