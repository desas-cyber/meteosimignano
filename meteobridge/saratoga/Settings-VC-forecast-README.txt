Added to Base-World on 2023-02-07 is the capability to use VisualCrossing weather forecasts.
See https://saratoga-weather.org/scripts-VCforecast.php for additional details.

You will need a free API key to use the VC-forecast.php script.

To enable in your Base-* template, add the following lines to your Settings.php:

//-----code below----- (don't include this line)

/*
// --- VisualCrossing forecast variables ---
$SITE['fcstscript'] = 'VC-forecast.php';
$SITE['fcstorg']    = 'VC'; // set to 'VC' for VisualCrossing.com

$SITE['VCAPIkey'] = '-specify-your-api-key-here-'; // Set API access key

// Units:
// 'base': SI units (K,m/s,hPa,mm,km)
//' metric': same as base, except that temp in C and windSpeed and windGust are in kilometers per hour
// 'uk': same as metric, except that nearestStormDistance and visibility are in miles, and windSpeed and windGust in miles per hour
// 'us': Imperial units (F,mph,inHg,in,miles)
//

$SITE['VCshowUnitsAs'] = 'metric';

$SITE['VCforecasts'] = array(
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
// --- end of VisualCrossing forecast variables ---
//*/

//-----code above----- (don't include this line)