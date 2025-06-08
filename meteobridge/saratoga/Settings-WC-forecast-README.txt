Version 1.00 - 01-Mar-2019 - initial release
Version 1.01 - 11-Apr-2020 - update to deprecate DS-forecast for Aerisweather AW-forecast

Since WeatherUnderground had stopped issuing API keys in June, 2018 and closed the original API in March, 2019, 
the old WU-forecast.php script is now deprecated.  
A new WC-forecast.php script is now available to use the NEW WU/TWC API.  
This script is provided as an OPTION to use instead of the distributed AW-forecast.php script with data sourced from Aerisweather.
The reason for OPTIONAL is that to acquire an API key, you must be a member of WeatherUnderground.com and have a PWS
submitting weather data to WeatherUnderground.  The Aerisweather API key has a similar requirement that your
station submit data to pwsweather.com.

When implementing this script on your Saratoga template site, also update your copies of 
ajax-dashboard.php and wxforecast.php which are required to complete the changeover from
from DarkSky or Aerisweather to the new WeatherUnderground forecasts for your Base-World site.

First, visit https://www.wunderground.com/member/api-keys , sign in and acquire an API key to use the API.  
Put the key value in the $SITE['WCAPIkey'] variable as shown below.
Also acquire the Latitude/Longitude (in decimal numbers) and location names to include in the $SITE['WCforecasts'] array below.
(Note, you can copy the entries from your existing $SITE['DSforecasts'] array as the $SITE['DSforecasts'] uses the same format.)
The first entry will be the default forecast area and appear on your site in the ajax-dashboard on the home page.
Change the units used if you like -- the default is to use SI units (C,hPa,mm,km) with wind in km/h instead of m/s.

Edit your Settings.php file and ADD the following 
near the bottom of the user configurable area:

// --- WU/TWC  forecast variables ---
$SITE['fcstscript'] = 'WC-forecast.php';    // Non-USA, Non-Canada Wunderground Forecast Script
$SITE['fcstorg']    = 'WU/TWC';    // set to 'WU/TWC' for WeatherUnderground

$SITE['WCAPIkey'] = 'specify-your-WU-API-key-here'; // Your API key from https://www.wunderground.com/member/api-keys 

// Wunderground display Units (uncomment one of the following): 
//$SITE['WCunits']  = 'e';  // 'e'= US units F,mph,inHg,in,in
$SITE['$WCunits']   = 'm';  // 'm'= metric   C,km/h,hPa,mm,cm
//$SITE['$WCunits']  = 'h';  // 'h'= UK units C,mph,mb,mm,cm
//$SITE['$WCunits']  = 's';  // 's'= SI units C,m/s,hPa,mm,cm

$SITE['fcsturlWC'] = 'Saratoga, CA, USA|37.27465,-122.02295'; // default forecast, should match first line in $SITE['WCforecasts']

$SITE['WCforecasts'] = array(
 // Location name to display|lat,long  (separated by | character)
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
'Tahoe Vista, CA, USA|39.2403,-120.0528',
'Auburn, CA, USA|38.8962,-121.0789',
); 
// --- end of WU/TWC forecast variables ---

For more information on customization of the WC-forecast.php script, please see the documentation at
https://saratoga-weather.org/scripts-WCforecast.php

Ken True - webmaster@saratoga-weather.org - 01-Mar-2019