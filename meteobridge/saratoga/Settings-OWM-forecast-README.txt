Added to Base-World on 2023-02-07 is the capability to use OpenWeatherMap weather forecasts.
See https://saratoga-weather.org/scripts-OWMforecast.php for additional details.

You will need a free API key to use the OWM-forecast.php script.

To enable in your Base-* template, add the following lines to your Settings.php:

//-----code below----- (don't include this line)

//*
// --- OpenWeatherMap forecast variables ---
$SITE['fcstscript'] = 'OWM-forecast.php';
$SITE['fcstorg']    = 'OWM'; // set to 'OWM' for OpenWeatherMap.org

$SITE['OWMAPIkey'] = '-specify-your-api-key-here-'; // Set API access key

// Units: Temp,Baro,Wind,Rain,Snow,Distance
// 'si' = C,hPa,m/s,mm,mm,km
// 'ca' = C,hPa,km/h,mm,mm,km
// 'uk' = C,mb,mph,mm,mm,km
// 'us' = F,inHg,mph,in,in,km

$SITE['OWMshowUnitsAs'] = 'us';

$SITE['OWMforecasts'] = array(
 // Location|lat,long  (separated by | characters)
'Saratoga, CA, USA|37.27465,-122.02295',
'Auckland, NZ|-36.910,174.771', // Awhitu, Waiuku New Zealand
'Assen, NL|53.02277,6.59037',
'Blankenburg, DE|51.8089941,10.9080649',
'Cheyenne, WY, USA|41.144259,-104.83497',
'Carcassonne, FR|43.2077801,2.2790407',
'Braniewo, PL|54.3793635,19.7853585',
'Omaha, NE, USA|41.19043,-96.13114',
'Johanngeorgenstadt, DE|50.439339,12.706085',
'Athens, GR|37.97830,23.715363',
'Haifa, IL|32.7996029,34.9467358',
);
// --- end of OpenWeatherMap forecast variables ---
//*/

//-----code above----- (don't include this line)