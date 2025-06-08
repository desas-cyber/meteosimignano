Version 1.00 - 02-Dec-2018 - initial release
Version 1.01 - 11-Apr-2020 - update regarding loss of new DarkSky API keys

NOTE: Darksky.net was purchased by Apple, Inc. on 31-Mar-2020.  They stopped issuing new API keys on the same date and
announced the API will be turned off at the end of 2021.
If you ALREADY have an API key, the DS-forecast.php script should work until the end of 2021.
If you DO NOT HAVE AN API KEY, then you will need to use the Aerisweather or WeatherUnderground scripts for your forecasts.
----------------------------------------------------------------------------------------------------------

Since WeatherUnderground has stopped issuing API keys in June, 2018 and plans to close the API December 31,2018, you MUST change
your Base-World site to use the new DarkSky forecast script (DS-forecast.php) for both new and old installations.
At the same time, updated versions of ajax-dashboard.php and wxforecast.php are required to complete the changeover from
WeatherUnderground to DarkSky forecasts for your Base-World site.

First, visit https://darksky.net/dev , sign in and acquire an API key to use DarkSky.net API.  Put the key value
in the $SITE['DSAPIkey'] variable as shown below.  Also acquire the Latitude/Longitude (in decimal numbers) and names
to include in the $SITE['DSforecasts'] array below.  The first entry will be the default forecast area and appear
on your site in the ajax-dashboard on the home page.  Change the units used if you like -- the default is
to use SI units (C,hPa,mm,km) with wind in km/h instead of m/s.

After changing those three items, edit your Settings.php file and ADD the following 
near the bottom of the user configurable area:

// --- DarkSky.net forecast variables ---
$SITE['fcstscript'] = 'DS-forecast.php';
$SITE['fcstorg']    = 'DarkSky'; // set to 'DarkSky' for DarkSky.net

$SITE['DSAPIkey'] = 'specify-your-DarkSky-API-key-here'; // Your API key from https://darksky.net/dev 

// DarkSky display Units: 
// si: SI units (C,m/s,hPa,mm,km)
// ca: same as si, except that windSpeed and windGust are in kilometers per hour
// uk2: same as si, except that nearestStormDistance and visibility are in miles, and windSpeed and windGust in miles per hour
// us: Imperial units (F,mph,inHg,in,miles)

$SITE['DSshowUnitsAs'] = 'ca'; // ='us' for imperial, , ='si' for metric, ='ca' for canada, ='uk2' for UK
$SITE['DSforecasts'] = array(
 // Location|lat,long  (separated by | characters)
'Saratoga, CA, USA|37.27465,-122.02295',
'Auckland, NZ|-36.910,174.771', // Awhitu, Waiuku New Zealand
'Assen, NL|53.02277,6.59037',
'Blankenburg, DE|51.8089941,10.9080649',
'Carcassonne, FR|43.2077801,2.2790407',
'Braniewo, PL|54.3793635,19.7853585',
); 
// --- end of DarkSky forecast variables ---

For more information on customization of the DS-forecast.php script, please see the documentation at
https://saratoga-weather.org/scripts-DSforecast.php

Ken True - webmaster@saratoga-weather.org - 02-Dec-2018