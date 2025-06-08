<?php 
// AW-forecast.php script by Ken True - webmaster@saratoga-weather.org
//    Forecast from aerisweather.com (api.aerisapi.com) - based on AW-forecast.php V1.09 - 23-Jan-2019
//
// Version 1.00 - 10-Apr-2020 - initial release
// Version 1.01 - 13-Apr-2020 - fix for day icon shown for night forecast in some cases
// Version 1.02 - 19-Jan-2022 - fix for PHP 8.1 Deprecated errata
// Version 1.03 - 27-Dec-2022 - fixes for PHP 8.2
//
$Version = "AW-forecast.php (ML) Version 1.03 - 27-Dec-2022";
//
// error_reporting(E_ALL);  // uncomment to turn on full error reporting
//
// script available at http://saratoga-weather.org/scripts.php
//  
// you may copy/modify/use this script as you see fit,
// no warranty is expressed or implied.
//
// This script parses the api.aerisapi.com forecast JSON API and loads icons/text into
//  arrays so you can use them in your weather website.  
//
//
// output: creates XHTML 1.0-Strict HTML page (or inclusion)
//
// Options on URL:
//
//   inc=Y            - omit <HTML><HEAD></HEAD><BODY> and </BODY></HTML> from output
//   heading=n        - (default)='y' suppress printing of heading (forecast city/by/date)
//   icons=n          - (default)='y' suppress printing of the icons+conditions+temp+wind+UV
//   text=n           - (default)='y' suppress printing of the periods/forecast text
//
//
//  You can also invoke these options directly in the PHP like this
//
//    $doIncludeAW = true;
//    include("AW-forecast.php");  for just the text
//  or ------------
//    $doPrintAW = false;
//    include("AW-forecast.php");  for setting up the $AWforecast... variables without printing
//
//  or ------------
//    $doIncludeAW = true;
//    $doPrintConditions = true;
//    $doPrintHeadingAW = true;
//    $doPrintIconsAW = true;
//    $doPrintTextAW = false
//    include("AW-forecast.php");  include mode, print only heading and icon set
//
// Variables returned (useful for printing an icon or forecast or two...)
//
// $AWforecastcity 		- Name of city from AW Forecast header
//
// The following variables exist for $i=0 to $i= number of forecast periods minus 1
//  a loop of for ($i=0;$i<count($AWforecastday);$i++) { ... } will loop over the available 
//  values.
//
// $AWforecastday[$i]	- period of forecast
// $AWforecasttext[$i]	- text of forecast 
// $AWforecasttemp[$i]	- Temperature with text and formatting
// $AWforecastpop[$i]	- Number - Probabability of Precipitation ('',10,20, ... ,100)
// $AWforecasticon[$i]   - base name of icon graphic to use
// $AWforecastcond[$i]   - Short legend for forecast icon 
// $AWforecasticons[$i]  - Full icon with Period, <img> and Short legend.
// $AWforecastwarnings = styled text with hotlinks to advisories/warnings
// $AWcurrentConditions = table with current conds at point close to lat/long selected
//
// Settings ---------------------------------------------------------------
// REQUIRED: api.aerisapi.com API Access ID and Secret keys.
// If you are uploading to pwsweather.com, you can get a free key at https://www.aerisweather.com/signup/pws/
$AWAPIkey = 'specify-for-standalone-use-here';    // Aeris Access ID; use this only for standalone / non-template use
$AWAPIsecret = 'specify-for-standalone-use-here'; // Aeris Secret Key; use this only for standalone / non-template use 

// NOTE: if using the Saratoga template, add to Settings.php a line with:
//    $SITE['AWAPIkey'] = 'your-api-client-key-here';
//    $SITE['AWAPIsecret'] = 'your-api-secret-key-here';
// and that will enable the script to operate correctly in your template
//
$iconDir ='./forecast/images/';	// directory for carterlake icons './forecast/images/'
$iconType = '.jpg';				// default type='.jpg' 
//                        use '.gif' for animated icons fromhttp://www.meteotreviglio.com/
//
// The forecast(s) .. make sure the first entry is the default forecast location.
// The contents will be replaced by $SITE['AWforecasts'] if specified in your Settings.php

$AWforecasts = array(
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
'Greenville, ME|45.4608,-69.5904',
'Tehachapi, CA|35.1322,-118.4491',
); 

//
$maxWidth = '640px';                      // max width of tables (could be '100%')
$maxIcons = 10;                           // max number of icons to display
$maxForecasts = 14;                       // max number of Text forecast periods to display
$maxForecastLegendWords = 4;              // more words in forecast legend than this number will use our forecast words 
$numIconsInFoldedRow = 10;                 // if words cause overflow of $maxWidth pixels, then put this num of icons in rows
$autoSetTemplate = true;                  // =true set icons based on wide/narrow template design
$cacheFileDir = './';                     // default cache file directory
$cacheName = "AW-forecast-json.txt";      // locally cached page from AW
$refetchSeconds = 3600;                   // cache lifetime (3600sec = 60 minutes)
//
// Units: 
// si: SI units (C,m/s,hPa,mm,km)
// ca: same as si, except that windSpeed and windGust are in kilometers per hour
// uk2: same as si, except that nearestStormDistance and visibility are in miles, and windSpeed and windGust in miles per hour
// us: Imperial units (F,mph,inHg,in,miles)
// 
$showUnitsAs  = 'us'; // ='us' for imperial, , ='si' for metric, ='ca' for canada, ='uk2' for UK
//
$charsetOutput = 'ISO-8859-1';        // default character encoding of output
//$charsetOutput = 'UTF-8';            // for standalone use if desired
$lang = 'en';	// default language
$foldIconRow = false;  // =true to display icons in rows of 5 if long texts are found
$timeFormat = 'Y-m-d H:i T';  // default time display format

$showConditions = true; // set to true to show current conditions box

// ---- end of settings ---------------------------------------------------

// overrides from Settings.php if available
global $SITE;
if (isset($SITE['AWforecasts']))   {$AWforecasts = $SITE['AWforecasts']; }
if (isset($SITE['AWAPIkey']))	{$AWAPIkey = $SITE['AWAPIkey']; } // new V3.00
if (isset($SITE['AWAPIsecret']))	{$AWAPIsecret = $SITE['AWAPIsecret']; } // new V3.00
if (isset($SITE['AWshowUnitsAs'])) { $showUnitsAs = $SITE['AWshowUnitsAs']; }
if (isset($SITE['fcsticonsdir'])) 	{$iconDir = $SITE['fcsticonsdir'];}
if (isset($SITE['fcsticonstype'])) 	{$iconType = $SITE['fcsticonstype'];}
if (isset($SITE['xlateCOP']))	{$xlateCOP = $SITE['xlateCOP'];}
if (isset($LANGLOOKUP['Chance of precipitation'])) {
  $xlateCOP = $LANGLOOKUP['Chance of precipitation'];
}
if (isset($SITE['charset']))	{$charsetOutput = strtoupper($SITE['charset']); }
if (isset($SITE['lang']))		{$lang = $SITE['lang'];}
if (isset($SITE['cacheFileDir']))     {$cacheFileDir = $SITE['cacheFileDir']; }
if (isset($SITE['foldIconRow']))     {$foldIconRow = $SITE['foldIconRow']; }
if (isset($SITE['RTL-LANG']))     {$RTLlang = $SITE['RTL-LANG']; }
if (isset($SITE['timeFormat']))   {$timeFormat = $SITE['timeFormat']; }
if (isset($SITE['AWshowConditions'])) {$showConditions = $SITE['AWshowConditions'];} // new V1.05
// end of overrides from Settings.php
//
// -------------------begin code ------------------------------------------

$RTLlang = ',he,jp,cn,';  // languages that use right-to-left order

if (isset($_REQUEST['sce']) && strtolower($_REQUEST['sce']) == 'view' ) {
   //--self downloader --
   $filenameReal = __FILE__;
   $download_size = filesize($filenameReal);
   header('Pragma: public');
   header('Cache-Control: private');
   header('Cache-Control: no-cache, must-revalidate');
   header("Content-type: text/plain,charset=ISO-8859-1");
   header("Accept-Ranges: bytes");
   header("Content-Length: $download_size");
   header('Connection: close');
   
   readfile($filenameReal);
   exit;
}

$Status = "<!-- $Version on PHP ".phpversion()." -->\n";

$AWcurrentConditions = ''; // HTML for table of current conditions
//------------------------------------------------

if(preg_match('|specify|i',$AWAPIkey)) {
	print "<p>Note: the AW-forecast.php script requires an API keys from aerisweather.com to operate.<br/>";
	print "Visit <a href=\"https://www.aerisweather.com/signup\">api.aerisapi.com</a> to ";
	print "register for an API key.<br/>\n";
	print "If you are uploading data to pwsweather.com, you can get free API keys from ";
	print "<a href=\"https://www.aerisweather.com/signup/pws/\">here</a> to use.</p>\n";
	if( isset($SITE['fcsturlAW']) ) {
		print "<p>Insert in Settings.php an entries for:<br/><br/>\n";
		print "\$SITE['AWAPIkey'] = '<i>your-key-here</i>'; // Access ID<br/><br/>\n";
		print "\$SITE['AWAPIsecret'] = '<i>your-key-here</i>' // Client Secret;<br/><br/>\n";
		print "replacing <i>your-key-here</i> with your corresponding keys.</p>\n";
	}
	return;
}

$NWSiconlist = array(
// api.aerisapi.com ICON definitions
  'am_pcloudy.png' => 'sct.jpg',
  'am_pcloudyr.png' => 'hi_shwrs.jpg',
  'am_showers.png' => 'hi_shwrs.jpg',
  'am_snowshowers.png' => 'sn.jpg',
  'am_tstorm.png' => 'scttsra.jpg',
  'blizzard.png' => 'blizzard.jpg',
  'blizzardn.png' => 'nblizzard.jpg',
  'blowingsnow.png' => 'sn.jpg',
  'blowingsnown.png' => 'nsn.jpg',
  'chancetstorm.png' => 'tsra.jpg',
  'chancetstormn.png' => 'ntsra.jpg',
  'clear.png' => 'skc.jpg',
  'clearn.png' => 'nskc.jpg',
  'clearw.png' => 'wind.jpg',
  'clearwn.png' => 'nwind.jpg',
  'cloudy.png' => 'ovc.jpg',
  'cloudyn.png' => 'novc.jpg',
  'cloudyw.png' => 'wind_ovc.jpg',
 'cold.png' => 'cold.jpg',
 'coldn.png' => 'ncold.jpg',
  'cloudywn.png' => 'nwind_ovc.jpg',
  'drizzle.png' => 'fg.jpg',
  'drizzlef.png' => 'fg.jpg',
  'drizzlen.png' => 'nfg.jpg',
  'dust.png' => 'du.jpg',
 'dustn.png' => 'ndu.jpg',
  'fair.png' => 'few.jpg',
  'fairn.png' => 'nfew.jpg',
  'fairw.png' => 'wind_few.jpg',
  'fairwn.png' => 'nwind_few.jpg',
  'fdrizzle.png' => 'ra_fzra.jpg',
  'fdrizzlen.png' => 'nra_fzra.jpg',
  'flurries.png' => 'sn.jpg',
  'flurriesn.png' => 'sn.jpg',
  'flurriesw.png' => 'sn.jpg',
  'flurrieswn.png' => 'nsn.jpg',
  'fog.png' => 'fg.jpg',
  'fogn.png' => 'nfg.jpg',
  'freezingrain.png' => 'fzra.jpg',
  'freezingrainn.png' => 'nfzra.jpg',
  'hazy.png' => 'hz.jpg',
  'hazyn.png' => 'hz.jpg',
 'hot.png' => 'hot.jpg',
  'mcloudy.png' => 'bkn.jpg',
  'mcloudyn.png' => 'nbkn.jpg',
  'mcloudyr.png' => 'ra.jpg',
  'mcloudyrn.png' => 'nra.jpg',
  'mcloudyrw.png' => 'ra.jpg',
  'mcloudyrwn.png' => 'nra.jpg',
  'mcloudys.png' => 'sn.jpg',
 'mcloudysn.png' => 'nsn.jpg',
  'mcloudysfn.png' => 'nsn.jpg',
  'mcloudysfw.png' => 'sn.jpg',
  'mcloudysfwn.png' => 'nsn.jpg',
  'mcloudysn.png' => 'sn.jpg',
  'mcloudysw.png' => 'sn.jpg',
  'mcloudyswn.png' => 'nsn.jpg',
  'mcloudyt.png' => 'scttsra.jpg',
  'mcloudytn.png' => 'nscttsra.jpg',
  'mcloudytw.png' => 'scttsra.jpg',
  'mcloudytwn.png' => 'nscttsra.jpg',
  'mcloudyw.png' => 'wind_bkn.jpg',
  'mcloudywn.png' => 'nwind_bkn.jpg',
  'na.png' => 'na.jpg',
  'pcloudy.png' => 'sct.jpg',
  'pcloudyn.png' => 'nsct.jpg',
  'pcloudyr.png' => 'ra.jpg',
  'pcloudyrn.png' => 'nra.jpg',
  'pcloudyrw.png' => 'ra.jpg',
  'pcloudyrwn.png' => 'nra.jpg',
  'pcloudys.png' => 'sn.jpg',
  'pcloudysf.png' => 'sn.jpg',
  'pcloudysfn.png' => 'nsn.jpg',
  'pcloudysfw.png' => 'sn.jpg',
  'pcloudysfwn.png' => 'nsn.jpg',
  'pcloudysn.png' => 'nsn.jpg',
  'pcloudysw.png' => 'sn.jpg',
  'pcloudyswn.png' => 'nsn.jpg',
  'pcloudyt.png' => 'scttsra.jpg',
  'pcloudytn.png' => 'nscttsra.jpg',
  'pcloudytw.png' => 'scttsra.jpg',
  'pcloudytwn.png' => 'nscttsra.jpg',
  'pcloudyw.png' => 'wind_sct.jpg',
  'pcloudywn.png' => 'nwind_sct.jpg',
  'pm_pcloudy.png' => 'sct.jpg',
  'pm_pcloudyr.png' => 'ra.jpg',
  'pm_showers.png' => 'ra.jpg',
  'pm_snowshowers.png' => 'sn.jpg',
  'pm_tstorm.png' => 'scttsra.jpg',
  'rain.png' => 'ra.jpg',
  'rainandsnow.png' => 'rasn.jpg',
  'rainandsnown.png' => 'nrasn.jpg',
  'rainn.png' => 'nra.jpg',
  'raintosnow.png' => 'rasn.jpg',
  'raintosnown.png' => 'nrasn.jpg',
  'rainw.png' => 'ra.jpg',
  'showers.png' => 'hi_shwrs.jpg',
  'showersn.png' => 'hi_nshwrs.jpg',
  'showersw.png' => 'hi_shwrs.jpg',
 'showerswn.png' => 'hi_nshwrs.jpg',
  'sleet.png' => 'ip.jpg',
  'sleetn.png' => 'nip.jpg',
  'sleetsnow.png' => 'snip.jpg',
 'sleetsnown.png' => 'nsnip.jpg',
  'smoke.png' => 'fu.jpg',
  'smoken.png' => 'nfu.jpg',
  'snow.png' => 'sn.jpg',
  'snown.png' => 'nsn.jpg',
  'snowshowers.png' => 'sn.jpg',
  'snowshowersn.png' => 'nsn.jpg',
  'snowshowersw.png' => 'sn.jpg',
  'snowshowerswn.png' => 'nsn.jpg',
  'snowtorain.png' => 'ra_sn.jpg',
  'snowtorainn.png' => 'nra_sn.jpg',
  'snoww.png' => 'sn.jpg',
  'snowwn.png' => 'nsn.jpg',
  'sunny.png' => 'skc.jpg',
  'sunnyn.png' => 'nskc.jpg',
  'sunnyw.png' => 'wind_skc.jpg',
  'sunnywn.png' => 'nwind_skc.jpg',
  'tstorm.png' => 'tsra.jpg',
  'tstormn.png' => 'ntsra.jpg',

  'tstorms.png' => 'tsra.jpg',
  'tstormsn.png' => 'ntsra.jpg',
  'tstormsw.png' => 'tsra.jpg',
  'tstormswn.png' => 'ntsra.jpg',
  'tstormw.png' => 'tsra.jpg',
  'tstormwn.png' => 'ntsra.jpg',
  'wind.png' => 'wind.jpg',
  'wintrymix.png' => 'mix.jpg',
  'wintrymixn.png' => 'nmix.jpg',
	);
//

$windUnits = array(
 'us' => 'mph',
 'ca' => 'km/h',
 'si' => 'm/s',
 'uk2' => 'mph'
);
$UnitsTab = array(
 'si' => array('T'=>'&deg;C','W'=>'m/s','P'=>'hPa','R'=>'mm','D'=>'km','I'=>'mm','S'=>'cm'),
 'ca' => array('T'=>'&deg;C','W'=>'km/s','P'=>'hPa','R'=>'mm','D'=>'km','I'=>'mm','S'=>'cm'),
 'uk2' => array('T'=>'&deg;C','W'=>'mph','P'=>'mb','R'=>'mm','D'=>'mi','I'=>'mm','S'=>'cm'),
 'us' => array('T'=>'&deg;F','W'=>'mph','P'=>'inHg','R'=>'in','D'=>'mi','I'=>'in','S'=>'in'),
);

if(isset($UnitsTab[$showUnitsAs])) {
  $Units = $UnitsTab[$showUnitsAs];
} else {
	$Units = $UnitsTab['si'];
}

global $Units;

if(!function_exists('langtransstr')) {
	// shim function if not running in template set
	function langtransstr($input) { return($input); }
}

if(!function_exists('json_last_error')) {
	// shim function if not running PHP 5.3+
	function json_last_error() { return('- N/A'); }
	$Status .= "<!-- php V".phpversion()." json_last_error() stub defined -->\n";
	if(!defined('JSON_ERROR_NONE')) { define('JSON_ERROR_NONE',0); }
	if(!defined('JSON_ERROR_DEPTH')) { define('JSON_ERROR_DEPTH',1); }
	if(!defined('JSON_ERROR_STATE_MISMATCH')) { define('JSON_ERROR_STATE_MISMATCH',2); }
	if(!defined('JSON_ERROR_CTRL_CHAR')) { define('JSON_ERROR_CTRL_CHAR',3); }
	if(!defined('JSON_ERROR_SYNTAX')) { define('JSON_ERROR_SYNTAX',4); }
	if(!defined('JSON_ERROR_UTF8')) { define('JSON_ERROR_UTF8',5); }
}

AW_loadLangDefaults (); // set up the language defaults

if($charsetOutput == 'UTF-8') {
	foreach ($AWlangCharsets as $l => $cs) {
		$AWlangCharsets[$l] = 'UTF-8';
	}
	$Status .= "<!-- charsetOutput UTF-8 selected for all languages. -->\n";
	$Status .= "<!-- AWlangCharsets\n".print_r($AWlangCharsets,true)." \n-->\n";	
}

$AWLANG = 'en'; // Default to English for API
$lang = strtolower($lang); 	
if( isset($AWlanguages[$lang]) ) { // if $lang is specified, use it
	$SITE['lang'] = $lang;
	$AWLANG = $AWlanguages[$lang];
	$charsetOutput = (isset($AWlangCharsets[$lang]))?$AWlangCharsets[$lang]:$charsetOutput;
}

if(isset($_GET['lang']) and isset($AWlanguages[strtolower($_GET['lang'])]) ) { // template override
	$lang = strtolower($_GET['lang']);
	$SITE['lang'] = $lang;
	$AWLANG = $AWlanguages[$lang];
	$charsetOutput = (isset($AWlangCharsets[$lang]))?$AWlangCharsets[$lang]:$charsetOutput;
}

$doRTL = (strpos($RTLlang,$lang) !== false)?true:false;  // format RTL language in Right-to-left in output
if(isset($SITE['copyr']) and $doRTL) { 
 // running in a Saratoga template.  Turn off $doRTL
 $Status .= "<!-- running in Saratoga Template. doRTL set to false as template handles formatting -->\n";
 $doRTL = false;
}
if(isset($doShowConditions)) {$showConditions = $doShowConditions;}
if($doRTL) {$RTLopt = ' style="direction: rtl;"'; } else {$RTLopt = '';}; 

// get the selected forecast location code
$haveIndex = '0';
if (!empty($_GET['z']) && preg_match("/^[0-9]+$/i", htmlspecialchars($_GET['z']))) {
  $haveIndex = htmlspecialchars(strip_tags($_GET['z']));  // valid zone syntax from input
} 

if(!isset($AWforecasts[0])) {
	// print "<!-- making NWSforecasts array default -->\n";
	$AWforecasts = array("Saratoga|37.27465,-122.02295"); // create default entry
}

//  print "<!-- NWSforecasts\n".print_r($AWforecasts,true). " -->\n";
// Set the default zone. The first entry in the $SITE['NWSforecasts'] array.
list($Nl,$Nn) = explode('|',$AWforecasts[0].'|||');
$FCSTlocation = $Nl;
$AW_LATLONG = $Nn;

if(!isset($AWforecasts[$haveIndex])) {
	$haveIndex = 0;
}

// locations added to the drop down menu and set selected zone values
$dDownMenu = '';
for ($m=0;$m<count($AWforecasts);$m++) { // for each locations
  list($Nlocation,$Nname) = explode('|',$AWforecasts[$m].'|||');
  $seltext = '';
  if($haveIndex == $m) {
    $FCSTlocation = $Nlocation;
    $AW_LATLONG = $Nname;
	$seltext = ' selected="selected" ';
  }
  $dDownMenu .= "     <option value=\"$m\"$seltext>".langtransstr($Nlocation)."</option>\n";
}

// build the drop down menu
$ddMenu = '';

// create menu if at least two locations are listed in the array
if (isset($AWforecasts[0]) and isset($AWforecasts[1])) {
	$ddMenu .= '<tr align="center">
      <td style="font-size: 14px; font-family: Arial, Helvetica, sans-serif">
      <script type="text/javascript">
        <!--
        function menu_goto( menuform ){
         selecteditem = menuform.logfile.selectedIndex ;
         logfile = menuform.logfile.options[ selecteditem ].value ;
         if (logfile.length != 0) {
          location.href = logfile ;
         }
        }
        //-->
      </script>
     <form action="" method="get">
     <p><select name="z" onchange="this.form.submit()"'.$RTLopt.'>
     <option value=""> - '.langtransstr('Select Forecast').' - </option>
' . $dDownMenu .
		$ddMenu . '     </select></p>
     <div><noscript><pre><input name="submit" type="submit" value="'.langtransstr('Get Forecast').'" /></pre></noscript></div>
     </form>
    </td>
   </tr>
';
}

$Force = false;

if (isset($_REQUEST['force']) and  $_REQUEST['force']=="1" ) {
  $Force = true;
}

$doDebug = false;
if (isset($_REQUEST['debug']) and strtolower($_REQUEST['debug'])=='y' ) {
  $doDebug = true;
}
$showTempsAs = ($showUnitsAs == 'us')? 'F':'C';
$Status .= "<!-- temps in $showTempsAs -->\n";

$fileName = "https://api.aerisapi.com/forecasts/?p=$AW_LATLONG" .
      "&format=json&filter=daynight,precise&limit=14&client_id=$AWAPIkey&client_secret=$AWAPIsecret";

if ($doDebug) {
  $Status .= "<!-- AW URL: $fileName -->\n";
}


if ($autoSetTemplate and isset($_SESSION['CSSwidescreen'])) {
	if($_SESSION['CSSwidescreen'] == true) {
	   $maxWidth = '900px';
	   $maxIcons = 8;
	   $maxForecasts = 8;
	   $numIconsInFoldedRow = 7;
	   $Status .= "<!-- autoSetTemplate using ".$SITE['CSSwideOrNarrowDefault']." aspect. -->\n";	
	}
	if($_SESSION['CSSwidescreen'] == false) {
	   $maxWidth = '640px';
	   $maxIcons = 8;
	   $maxForecasts = 8;
	   $numIconsInFoldedRow = 7;
	   $Status .= "<!-- autoSetTemplate using ".$SITE['CSSwideOrNarrowDefault']." aspect. -->\n";	
	}
}

$cacheName = $cacheFileDir . $cacheName;
$cacheName = preg_replace('|\.txt|is',"-$haveIndex-$showUnitsAs-$lang.txt",$cacheName); // unique cache per language used

$APIfileName = $fileName; 

if($showConditions) {
	$refetchSeconds = 15*60; // shorter refresh time so conditions will be 'current'
}

if (! $Force and file_exists($cacheName) and filemtime($cacheName) + $refetchSeconds > time()) {
      $html = implode('', file($cacheName)); 
      $Status .= "<!-- loading from $cacheName (" . strlen($html) . " bytes) -->\n"; 
  } else { 
      $Status .= "<!-- loading from $APIfileName. -->\n"; 
      $html = AW_fetchUrlWithoutHanging($APIfileName,false); 
	  
    $RC = '';
	if (preg_match("|^HTTP\/\S+ (.*)\r\n|",$html,$matches)) {
	    $RC = trim($matches[1]);
	}
	$Status .= "<!-- RC=$RC, bytes=" . strlen($html) . " -->\n";
	if (preg_match('|30\d |',$RC)) { // handle possible blocked redirect
	   preg_match('|Location: (\S+)|is',$html,$matches);
	   if(isset($matches[1])) {
		  $sURL = $matches[1];
		  if(preg_match('|opendns.com|i',$sURL)) {
			  $Status .= "<!--  NOT following to $sURL --->\n";
		  } else {
			$Status .= "<!-- following to $sURL --->\n";
		
			$html = AW_fetchUrlWithoutHanging($sURL,false);
			$RC = '';
			if (preg_match("|^HTTP\/\S+ (.*)\r\n|",$html,$matches)) {
				$RC = trim($matches[1]);
			}
			$Status .= "<!-- RC=$RC, bytes=" . strlen($html) . " -->\n";
		  }
	   }
    }
		if(preg_match('!weatherPrimaryCoded!is',$html)) {
      $fp = fopen($cacheName, "w"); 
			if (!$fp) { 
				$Status .= "<!-- unable to open $cacheName for writing. -->\n"; 
			} else {
        $write = fputs($fp, $html); 
        fclose($fp);  
			$Status .= "<!-- saved cache to $cacheName (". strlen($html) . " bytes) -->\n";
			} 
		} else {
			$Status .= "<!-- bad return from $APIfileName\n".print_r($html,true)."\n -->\n";
			if(file_exists($cacheName) and filesize($cacheName) > 3000) {
				$html = implode('', file($cacheName));
				$Status .= "<!-- reloaded stale cache $cacheName temporarily -->\n";
			} else {
				$Status .= "<!-- cache $cacheName missing or contains invalid contents -->\n";
				print $Status;
				print "<p>Sorry.. the Aerisweather forecast is not available.</p>\n";
				return;
			}
		}
} 

 $charsetInput = 'UTF-8';
  
 $doIconv = ($charsetInput == $charsetOutput)?false:true; // only do iconv() if sets are different
 if($charsetOutput == 'UTF-8') {
	 $doIconv = false;
 }
 $Status .= "<!-- using charsetInput='$charsetInput' charsetOutput='$charsetOutput' doIconv='$doIconv' doRTL='$doRTL' -->\n";
 $tranTab = AW_load_builtin_translate($lang); // boilerplate lookups for legends in $tranTab from AW-forecast-lang.php
 $LangTranTab = AW_load_parser_trans($lang);  // use the plaintext-parser language translations for conditions
 
  $i = strpos($html,"\r\n\r\n");
  $headers = substr($html,0,$i-1);
  $content = substr($html,$i+4);
 //  process the file .. select out the 7-day forecast part of the page
  $UnSupported = false;

// --------------------------------------------------------------------------------------------------
  
 $Status .= "<!-- processing JSON entries for forecast -->\n";
  $i = strpos($html,"\r\n\r\n");
  $headers = substr($html,0,$i-1);
  $content = substr($html,$i+4);
 

  $rawJSON = $content;
  $Status .= "<!-- rawJSON size is ".strlen($rawJSON). " bytes -->\n";

  $rawJSON = AW_prepareJSON($rawJSON);
  $JSONF = json_decode($rawJSON,true); // get as associative array
  $Status .= AW_decode_JSON_error();
  $Status .= "<!-- Aerisweather JSON success='".$JSONF['success']."' error='".$JSONF['error']."' -->\n";

  $JSON = $JSONF['response'][0];
  if($doDebug) {$Status .= "<!-- JSON\n".var_export($JSON,true)." -->\n";}
  

if(isset($JSON['periods'][0]['icon'])) { // got good JSON .. process it
   $UnSupported = false;

   $AWforecastcity = $FCSTlocation;
	 
   if($doIconv) {$AWforecastcity = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$AWforecastcity);}
   if($doDebug) {
     $Status .= "<!-- AWforecastcity='$AWforecastcity' -->\n";
   }
   //$AWtitle = langtransstr("Forecast");
//	 $AWtitle = $tranTab['DarkSky Forecast for:']; // **FIX**
   $AWtitle = $tranTab['Aerisweather Forecast for:'];
   if($doIconv) {$AWtitle = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$AWtitle);}
   if($doDebug) {
     $Status .= "<!-- AWtitle='$AWtitle' -->\n";
   }
/*
JSON
array (
  'loc' => 
  array (
    'long' => -122.023,
    'lat' => 37.275,
  ),
  'interval' => 'daynight',
  'periods' => 
  array (
    0 => 
    array (
      'timestamp' => 1586224800,
      'validTime' => '2020-04-06T19:00:00-07:00',
      'dateTimeISO' => '2020-04-06T19:00:00-07:00',
      'maxTempC' => NULL,
      'maxTempF' => NULL,
      'minTempC' => 5,
      'minTempF' => 41,
      'avgTempC' => 7,
      'avgTempF' => 45,
      'tempC' => NULL,
      'tempF' => NULL,
      'pop' => 36,
      'precipMM' => 0.68,
      'precipIN' => 0.03,
      'iceaccum' => NULL,
      'iceaccumMM' => NULL,
      'iceaccumIN' => NULL,
      'maxHumidity' => 93,
      'minHumidity' => 71,
      'humidity' => 87,
      'uvi' => 0,
      'pressureMB' => 1016,
      'pressureIN' => 30,
      'sky' => 40,
      'snowCM' => 0,
      'snowIN' => 0,
      'feelslikeC' => 9.5,
      'feelslikeF' => 49,
      'minFeelslikeC' => 5.6,
      'minFeelslikeF' => 42,
      'maxFeelslikeC' => 9.5,
      'maxFeelslikeF' => 49,
      'avgFeelslikeC' => 7,
      'avgFeelslikeF' => 45,
      'dewpointC' => 6.2,
      'dewpointF' => 43,
      'maxDewpointC' => 6.2,
      'maxDewpointF' => 43,
      'minDewpointC' => 3.9,
      'minDewpointF' => 39,
      'avgDewpointC' => 5,
      'avgDewpointF' => 41,
      'windDirDEG' => 290,
      'windDir' => 'WNW',
      'windDirMaxDEG' => 150,
      'windDirMax' => 'SSE',
      'windDirMinDEG' => 150,
      'windDirMin' => 'SSE',
      'windGustKTS' => 1,
      'windGustKPH' => 2,
      'windGustMPH' => 1,
      'windSpeedKTS' => 1,
      'windSpeedKPH' => 2,
      'windSpeedMPH' => 1,
      'windSpeedMaxKTS' => 1,
      'windSpeedMaxKPH' => 2,
      'windSpeedMaxMPH' => 1,
      'windSpeedMinKTS' => 1,
      'windSpeedMinKPH' => 2,
      'windSpeedMinMPH' => 1,
      'windDir80mDEG' => 320,
      'windDir80m' => 'NW',
      'windDirMax80mDEG' => 150,
      'windDirMax80m' => 'SSE',
      'windDirMin80mDEG' => 150,
      'windDirMin80m' => 'SSE',
      'windGust80mKTS' => 6,
      'windGust80mKPH' => 12,
      'windGust80mMPH' => 7,
      'windSpeed80mKTS' => 4,
      'windSpeed80mKPH' => 7,
      'windSpeed80mMPH' => 4,
      'windSpeedMax80mKTS' => 6,
      'windSpeedMax80mKPH' => 12,
      'windSpeedMax80mMPH' => 7,
      'windSpeedMin80mKTS' => 2,
      'windSpeedMin80mKPH' => 3,
      'windSpeedMin80mMPH' => 2,
      'weather' => 'Partly Cloudy with Scattered Showers',
      'weatherCoded' => 
      array (
        0 => 
        array (
          'timestamp' => 1586224800,
          'wx' => 'C:L:RW',
          'dateTimeISO' => '2020-04-06T19:00:00-07:00',
        ),
      ),
      'weatherPrimary' => 'Scattered Showers',
      'weatherPrimaryCoded' => 'C:L:RW',
      'cloudsCoded' => 'SC',
      'icon' => 'pcloudyrn.png',
      'solradWM2' => 68,
      'solradMinWM2' => 0,
      'solradMaxWM2' => 68,
      'isDay' => false,
    ),
 
 ...
 
  ),
  'profile' => 
  array (
    'tz' => 'America/Los_Angeles',
    'elevM' => 125,
    'elevFT' => 410,
  ),
)

*/
  if(isset($JSON['profile']['tz'])) {
		date_default_timezone_set($JSON['profile']['tz']);
		$Status .= "<!-- using '".$JSON['profile']['tz']."' for timezone -->\n";
	}
	if(isset($JSON['periods'][0]['timestamp'])) {
		$AWupdated = $tranTab['Updated:'];
		if($doIconv) { 
		  $AWupdated = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$AWupdated). ' '; 
		}
	  $AWupdated .= date($timeFormat,$JSON['periods'][0]['timestamp']);
	} else {
		$AWupdated = '';
	}
	
	if($doDebug) {
		$Status .= "\n<!-- JSON periods count=" . count( $JSON['periods']) . "-->\n";
	}
	if(isset($windUnits[$showUnitsAs])) {
		$windUnit = $windUnits[$showUnitsAs];
		$Status .= "<!-- wind unit for '$showUnitsAs' set to '$windUnit' -->\n";
		if(isset($tranTab[$windUnit])) {
			$windUnit = $tranTab[$windUnit];
			$Status .= "<!-- wind unit translation for '$showUnitsAs' set to '$windUnit' -->\n";
		}
	} else {
		$windUnit = '';
	}

  $n = 0;
  foreach ($JSON['periods'] as $i => $FCpart) {
#   process each daily entry ---------------------------------------------------------
/*
array (
      'timestamp' => 1586224800,
      'validTime' => '2020-04-06T19:00:00-07:00',
      'dateTimeISO' => '2020-04-06T19:00:00-07:00',
      'maxTempC' => NULL,
      'maxTempF' => NULL,
      'minTempC' => 5,
      'minTempF' => 41,
      'avgTempC' => 7,
      'avgTempF' => 45,
      'tempC' => NULL,
      'tempF' => NULL,
      'pop' => 36,
      'precipMM' => 0.68,
      'precipIN' => 0.03,
      'iceaccum' => NULL,
      'iceaccumMM' => NULL,
      'iceaccumIN' => NULL,
      'maxHumidity' => 93,
      'minHumidity' => 71,
      'humidity' => 87,
      'uvi' => 0,
      'pressureMB' => 1016,
      'pressureIN' => 30,
      'sky' => 40,
      'snowCM' => 0,
      'snowIN' => 0,
      'feelslikeC' => 9.5,
      'feelslikeF' => 49,
      'minFeelslikeC' => 5.6,
      'minFeelslikeF' => 42,
      'maxFeelslikeC' => 9.5,
      'maxFeelslikeF' => 49,
      'avgFeelslikeC' => 7,
      'avgFeelslikeF' => 45,
      'dewpointC' => 6.2,
      'dewpointF' => 43,
      'maxDewpointC' => 6.2,
      'maxDewpointF' => 43,
      'minDewpointC' => 3.9,
      'minDewpointF' => 39,
      'avgDewpointC' => 5,
      'avgDewpointF' => 41,
      'windDirDEG' => 290,
      'windDir' => 'WNW',
      'windDirMaxDEG' => 150,
      'windDirMax' => 'SSE',
      'windDirMinDEG' => 150,
      'windDirMin' => 'SSE',
      'windGustKTS' => 1,
      'windGustKPH' => 2,
      'windGustMPH' => 1,
      'windSpeedKTS' => 1,
      'windSpeedKPH' => 2,
      'windSpeedMPH' => 1,
      'windSpeedMaxKTS' => 1,
      'windSpeedMaxKPH' => 2,
      'windSpeedMaxMPH' => 1,
      'windSpeedMinKTS' => 1,
      'windSpeedMinKPH' => 2,
      'windSpeedMinMPH' => 1,
      'windDir80mDEG' => 320,
      'windDir80m' => 'NW',
      'windDirMax80mDEG' => 150,
      'windDirMax80m' => 'SSE',
      'windDirMin80mDEG' => 150,
      'windDirMin80m' => 'SSE',
      'windGust80mKTS' => 6,
      'windGust80mKPH' => 12,
      'windGust80mMPH' => 7,
      'windSpeed80mKTS' => 4,
      'windSpeed80mKPH' => 7,
      'windSpeed80mMPH' => 4,
      'windSpeedMax80mKTS' => 6,
      'windSpeedMax80mKPH' => 12,
      'windSpeedMax80mMPH' => 7,
      'windSpeedMin80mKTS' => 2,
      'windSpeedMin80mKPH' => 3,
      'windSpeedMin80mMPH' => 2,
      'weather' => 'Partly Cloudy with Scattered Showers',
      'weatherCoded' => 
      array (
        0 => 
        array (
          'timestamp' => 1586224800,
          'wx' => 'C:L:RW',
          'dateTimeISO' => '2020-04-06T19:00:00-07:00',
        ),
      ),
      'weatherPrimary' => 'Scattered Showers',
      'weatherPrimaryCoded' => 'C:L:RW',
      'cloudsCoded' => 'SC',
      'icon' => 'pcloudyrn.png',
      'solradWM2' => 68,
      'solradMinWM2' => 0,
      'solradMaxWM2' => 68,
      'isDay' => false,
    )
*/

		list($tDay,$tTime) = explode(" ",date('l H:i:s',$FCpart['timestamp']));
		if ($doDebug) {
				$Status .= "<!-- period $n ='$tDay $tTime' -->\n";
		}
		if(!$FCpart['isDay']) { $tDay .= ' night'; }
		$AWforecastdayname[$n] = $tDay;
			
		if(isset($tranTab[$tDay])) {
			$AWforecastday[$n] = $tranTab[$tDay];
		} else {
			$AWforecastday[$n] = $tDay;
		}
    if($doIconv) {
		  $AWforecastday[$n] = iconv("UTF-8",$charsetOutput.'//IGNORE',$AWforecastday[$n]);
	  }
		$AWforecasttitles[$n] = $AWforecastday[$n];
		if ($doDebug) {
				$Status .= "<!-- AWforecastday[$n]='" . $AWforecastday[$n] . "' -->\n";
		}	
		$AWforecastcloudcover[$n] = $FCpart['sky'];

#  extract the temperature
    if($FCpart['isDay']) {
	    $AWforecasttemp[$n] = "<span style=\"color: #ff0000;\">".AW_conv_units('T',$FCpart['maxTempC'])."&deg;$showTempsAs</span>";
		} else {
	    $AWforecasttemp[$n] = "<span style=\"color: #0000ff;\">".AW_conv_units('T',$FCpart['minTempC'])."&deg;$showTempsAs</span>";
		}

#  extract the icon to use
	  $AWforecasticon[$n] = $FCpart['icon'];
	if ($doDebug) {
      $Status .= "<!-- AWforecasticon[$n]='" . $AWforecasticon[$n] . "' -->\n";
	}	

	if(isset($FCpart['pop'])) {
	  $AWforecastpop[$n] = round($FCpart['pop'],-1);
	} else {
		$AWforecastpop[$n] = 0;
	}
	if ($doDebug) {
      $Status .= "<!-- AWforecastpop[$n]='" . $AWforecastpop[$n] . "' -->\n";
	}
	
	if(isset($FCpart['precipType'])) { // **FIX**
		$AWforecastpreciptype[$n] = $FCpart['precipType'];
	} else {
		$AWforecastpreciptype[$n] = '';
	}


	$AWforecasttext[$n] =  // replace problematic characters in forecast text
	   str_replace(
		 array('<',   '>',  'â€“','cm.','in.','.)'),
		 array('&lt;','&gt;','-', 'cm', 'in',')'),
	   trim($FCpart['weather']));
	$tText = AW_do_parser_trans( trim($FCpart['weather']),$LangTranTab);
	if($LangTranTab['charset'] !== 'UTF-8') {
		// convert to UTF-8 since a later UTF-8 -> ISO may be done
		$tText = iconv($LangTranTab['charset'],'UTF-8//TRANSLIT',$tText);
	}

	$Status .= "<!-- AWforecasttext='".$AWforecasttext[$n]."'\n".
	           "              trans='".$tText."' -->\n";
	if(trim($FCpart['weather']) <> $tText) {
		$AWforecasttext[$n] = $tText;
		$Status .= "<!-- using translated conditions -->\n";
	}

	$AWforecastcond[$n] = $AWforecasttext[$n];
	if($doIconv) {
		$AWforecastcond[$n] = iconv("UTF-8",$charsetOutput.'//IGNORE',$AWforecastcond[$n]);
	}
	 
	if ($doDebug) {
      $Status .= "<!-- forecastcond[$n]='" . $AWforecastcond[$n] . "' -->\n";
	}
  $AWforecasttext[$n] .= '.';
 
	

# Add info to the forecast text
	if($AWforecastpop[$n] > 0) {
		$tstr = '';
		if(!empty($AWforecastpreciptype[$n])) {
			$t = explode(',',$AWforecastpreciptype[$n].',');
			foreach ($t as $k => $ptype) {
				if(!empty($ptype)) {$tstr .= $tranTab[$ptype].',';}
			}
			if(strlen($tstr)>0) {
				$tstr = '('.substr($tstr,0,strlen($tstr)-1) .') ';
			}
		}
		$AWforecasttext[$n] .= " ".
		   $tranTab['Chance of precipitation']." $tstr".$AWforecastpop[$n]."%. ";
	}

  if($FCpart['isDay'] == true) {
    $AWforecasttext[$n] .= " ".$tranTab['High:']." ".AW_conv_units('T',$FCpart['maxTempC'])."&deg;$showTempsAs. ";
	} else {
    $AWforecasttext[$n] .= " ".$tranTab['Low:']." ".AW_conv_units('T',$FCpart['minTempC'])."&deg;$showTempsAs. ";
	}

	$tWdir = AW_WindDir(round($FCpart['windDirDEG'],0));
  $AWforecasttext[$n] .= " ".$tranTab['Wind']." ".AW_WindDirTrans($tWdir);
  $AWforecasttext[$n] .= " ".
	     AW_conv_units('W',$FCpart['windSpeedKPH'])."-&gt;".AW_conv_units('W',$FCpart['windGustKPH']) .
	     " $windUnit.";

	if(isset($FCpart['uvi']) and $FCpart['uvi'] > 1) {
    $AWforecasttext[$n] .= " ".$tranTab['UV index']." ".round($FCpart['uvi'],0).".";
	}
	if(isset($FCpart['precipMM']) and $FCpart['precipMM'] > 0) {
		$AWforecasttext[$n] .= " ".$tranTab['rain']." &asymp; ".AW_conv_units('R',$FCpart['precipMM']).$Units['R'].'.';
	}

	if(isset($FCpart['snowCM']) and $FCpart['snowCM'] > 0) {
		$AWforecasttext[$n] .= " ".$tranTab['snow']." &asymp; ".AW_conv_units('S',$FCpart['snowCM']).$Units['S'].'.';
	}

	if(isset($FCpart['iceaccumMM']) and $FCpart['iceaccumMM'] > 0) {
		$AWforecasttext[$n] .= " ".$tranTab['sleet']." &asymp; ".AW_conv_units('I',$FCpart['iceaccumMM']).$Units['I'].'.';
	}

  if($doIconv) {
		$AWforecasttext[$n] = iconv("UTF-8",$charsetOutput.'//IGNORE',$AWforecasttext[$n]);
	}

	if ($doDebug) {
      $Status .= "<!-- AWforecasttext[$n]='" . $AWforecasttext[$n] . "' -->\n";
	}
	
	if(isset($FCpart['weatherPrimaryCoded'])) {
		 $Status .= "<!-- AW_decode_conds code='".$FCpart['weatherPrimaryCoded']."' decoded as '".
		 AW_decode_conds($FCpart['weatherPrimaryCoded']).
		 "'\n   FCpart['weather']='".trim($FCpart['weather'])."' -->\n";
	}

	$AWforecasticons[$n] = $AWforecastday[$n] . "<br/>" .
	     AW_img_replace(
			   $AWforecasticon[$n],$AWforecastcond[$n],$AWforecastpop[$n],$AWforecastcloudcover[$n]) . 
				  "<br/>" .
		 $AWforecastcond[$n];
	$n++;
  } // end of process text forecasts

  // process alerts if any are available 
	$AWforecastwarnings = '';
	
} // end got good JSON decode/process


// end process JSON style --------------------------------------------------------------------

// All finished with parsing, now prepare to print

  $wdth = intval(100/count($AWforecasticons));
  $ndays = intval(count($AWforecasticon)/2);
  
  $doNumIcons = $maxIcons;
  if(count($AWforecasticons) < $maxIcons) { $doNumIcons = count($AWforecasticons); }

  $IncludeMode = false;
  $PrintMode = true;

  if (isset($doPrintAW) && ! $doPrintAW ) {
      print $Status;
      return;
  }
  if (isset($_REQUEST['inc']) && 
      strtolower($_REQUEST['inc']) == 'noprint' ) {
      print $Status;
	  return;
  }

if (isset($_REQUEST['inc']) && strtolower($_REQUEST['inc']) == 'y') {
  $IncludeMode = true;
}
if (isset($doIncludeAW)) {
  $IncludeMode = $doIncludeAW;
}

$printHeading = true;
$printIcons = true;
$printText = true;

if (isset($doPrintHeadingAW)) {
  $printHeading = $doPrintHeadingAW;
}
if (isset($_REQUEST['heading']) ) {
  $printHeading = substr(strtolower($_REQUEST['heading']),0,1) == 'y';
}

if (isset($doPrintIconsAW)) {
  $printIcons = $doPrintIconsAW;
}
if (isset($_REQUEST['icons']) ) {
  $printIcons = substr(strtolower($_REQUEST['icons']),0,1) == 'y';
}
if (isset($doPrintTextAW)) {
  $printText = $doPrintTextAW;
}
if (isset($_REQUEST['text']) ) {
  $printText = substr(strtolower($_REQUEST['text']),0,1) == 'y';
}


if (! $IncludeMode and $PrintMode) { 
header('Content-type: text/html,charset='.$charsetOutput); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title><?php echo $AWtitle . ' - ' . $AWforecastcity; ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo $charsetOutput; ?>" />
</head>
<body style="font-family:Verdana, Arial, Helvetica, sans-serif; font-size:12px; background-color:#FFFFFF">

<?php
} // end printmode and not includemode
print $Status;
// if the forecast text is blank, prompt the visitor to force an update

if($UnSupported) {

  print <<< EONAG
<h1>Sorry.. this <a href="https://api.aerisapi.com/forecast/$AW_LATLONG/{$showUnitsAs}12/$AWLANG">forecast</a> can not be processed at this time.</h1>


EONAG
;
}

if (strlen($AWforecasttext[0])<2 and $PrintMode and ! $UnSupported ) {

  echo '<br/><br/>'.langtransstr('Forecast blank?').' <a href="' . $PHP_SELF . '?force=1">' .
	 langtransstr('Force Update').'</a><br/><br/>';

} 
if ($PrintMode and ($printHeading or $printIcons)) { 

?>
  <table width="<?php print $maxWidth; ?>" style="border: none;" class="AWforecast">
  <?php echo $ddMenu ?>
<?php
  if ($showConditions) {
	  print "<tr><td align=\"center\">\n";
    print $AWcurrentConditions;
	  print "</td></tr>\n";
  }

?>
    <?php if($printHeading) { ?>
    <tr align="center" style="background-color: #FFFFFF;<?php 
		if($doRTL) { echo 'direction: rtl;'; } ?>">
      <td><b><?php echo $AWtitle; ?></b> <span style="color: green;">
	   <?php echo $AWforecastcity; ?></span>
     <?php if(strlen($AWupdated) > 0) {
			 echo "<br/>$AWupdated\n";
		 }
		 ?>
      </td>
    </tr>
  </table>
  <p>&nbsp;</p>
    <h2><?php 
$t = $tranTab['Daily Forecast'];
if($doIconv) { 
	$t = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$t). ' '; 
}
echo $t; ?></h2>
    <div style="width: 99%;">

  <table width="<?php print $maxWidth; ?>" style="border: none;" class="AWforecast">
	<?php } // end print heading
	
	if ($printIcons) {
	?>
    <tr>
      <td align="center">
	    <table width="100%" border="0" cellpadding="0" cellspacing="0">  
	<?php
	  // see if we need to fold the icon rows due to long text length
	  $doFoldRow = false; // don't assume we have to fold the row..
	  if($foldIconRow) {
		  $iTitleLen =0;
		  $iTempLen = 0;
		  $iCondLen = 0;
		  for($i=0;$i<$doNumIcons;$i++) {
			$iTitleLen += strlen(strip_tags($AWforecasttitles[$i]));
			$iCondLen += strlen(strip_tags($AWforecastcond[$i]));
			$iTempLen += strlen(strip_tags($AWforecasttemp[$i]));  
		  }
		  print "<!-- lengths title=$iTitleLen cond=$iCondLen temps=$iTempLen -->\n";
		  $maxChars = 135;
		  if($iTitleLen >= $maxChars or 
		     $iCondLen >= $maxChars or
			 $iTempLen >= $maxChars ) {
				 print "<!-- folding icon row -->\n";
				 $doFoldRow = true;
			 } 
			 
	  }
	  $startIcon = 0;
	  $finIcon = $doNumIcons;
	  $incr = $doNumIcons;
		$doFoldRow = false;
	  if ($doFoldRow) { $wdth = $wdth*2; $incr = $numIconsInFoldedRow; }
  print "<!-- numIconsInFoldedRow=$numIconsInFoldedRow startIcon=$startIcon doNumIcons=$doNumIcons incr=$incr -->\n";
	for ($k=$startIcon;$k<$doNumIcons-1;$k+=$incr) { // loop over icon rows, 5 at a time until done
	  $startIcon = $k;
	  if ($doFoldRow) { 
		  $finIcon = $startIcon+$numIconsInFoldedRow; 
		} else { 
		  $finIcon = $doNumIcons; 
		}
	  $finIcon = min($finIcon,$doNumIcons);
	  print "<!-- start=$startIcon fin=$finIcon num=$doNumIcons -->\n";
    print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
	  
	  for ($i=$startIcon;$i<$finIcon;$i++) {
		$ni = $doRTL?$numIconsInFoldedRow-1-$i+$startIcon+$k:$i; 
		print "<!-- doRTL:$doRTL i=$i k=$k -->\n"; 
	    print "<td style=\"width: $wdth%; text-align: center;\"><span style=\"font-size: 8pt;\">$AWforecasttitles[$ni]</span><!-- $ni '".$AWforecastdayname[$ni]."' --></td>\n";
		
	  }
	
print "          </tr>\n";	
    print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
	
	  for ($i=$startIcon;$i<$finIcon;$i++) {
		$ni = $doRTL?$numIconsInFoldedRow-1-$i+$startIcon+$k:$i;  
	    print "<td style=\"width: $wdth%;\">" . AW_img_replace($AWforecasticon[$ni],$AWforecastcond[$ni],$AWforecastpop[$ni],$AWforecastcloudcover[$ni]) . "<!-- $ni --></td>\n";
	  }
	?>
          </tr>	
	      <tr valign ="top" align="center">
	<?php
	  for ($i=$startIcon;$i<$finIcon;$i++) {
		$ni = $doRTL?$numIconsInFoldedRow-1-$i+$startIcon+$k:$i;  

	    print "<td style=\"width: $wdth%; text-align: center;\"><span style=\"font-size: 8pt;\">$AWforecastcond[$ni]</span><!-- $ni '".$AWforecastdayname[$ni]."' --></td>\n";
	  }
	
      print "	      </tr>\n";	
      print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
	  
	  for ($i=$startIcon;$i<$finIcon;$i++) {
		$ni = $doRTL?$numIconsInFoldedRow-1-$i+$startIcon+$k:$i;  
	    print "<td style=\"width: $wdth%; text-align: center;\">$AWforecasttemp[$ni]</td>\n";
	  }
	  ?>
          </tr>
	<?php if(! $iconDir) { // print a PoP row since they aren't using icons 
    print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
	
	  for ($i=$startIcon;$i<$finIcon;$i++) {
		$ni = $doRTL?$numIconsInFoldedRow-1-$i+$startIcon+$k:$i;  
	    print "<td style=\"width: $wdth%; text-align: center;\">";
	    if($AWforecastpop[$ni] > 0) {
  		  print "<span style=\"font-size: 8pt; color: #009900;\">PoP: $AWforecastpop[$ni]%</span>";
		} else {
		  print "&nbsp;";
		}
		print "</td>\n";
		
	  }
	?>
          </tr>	
	  <?php } // end if iconDir ?>
      <?php if ($doFoldRow) { 
    print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
	  
	  for ($i=$startIcon;$i<$finIcon;$i++) {
	    print "<td style=\"width: $wdth%; text-align: center;\">&nbsp;<!-- $i --></td>\n";
      
	  }
		print "</tr>\n";
      } // end doFoldRow ?>
  <?php } // end of foldIcon loop ?>
        </table><!-- end icon table -->
     </td>
   </tr><!-- end print icons -->
   	<?php } // end print icons ?>
</table>
<br/>
<?php } // end print header or icons

if ($PrintMode and $printText) { ?>
<br/>
<table style="border: 0" width="<?php print $maxWidth; ?>" class="AWforecast">
	<?php
	  for ($i=0;$i<count($AWforecasttitles);$i++) {
        print "<tr valign =\"top\"$RTLopt>\n";
		if(!$doRTL) { // normal Left-to-right
	      print "<td style=\"width: 20%;\"><b>$AWforecasttitles[$i]</b><br />&nbsp;<br /></td>\n";
	      print "<td style=\"width: 80%;\">$AWforecasttext[$i]</td>\n";
		} else { // print RTL format
	      print "<td style=\"width: 80%; text-align: right;\">$AWforecasttext[$i]</td>\n";
	      print "<td style=\"width: 20%; text-align: right;\"><b>$AWforecasttitles[$i]</b><br />&nbsp;<br /></td>\n";
		}
		print "</tr>\n";
	  }
	?>
   </table>
<?php } // end print text ?>
<?php if ($PrintMode) { ?>
</div>
<p>&nbsp;</p>
<p><?php echo $AWforecastcity.' '; print langtransstr('forecast by');?> <a href="https://www.aerisweather.com/">Aerisweather.com</a>. 
<?php if($iconType <> '.jpg') {
	print "<br/>".langtransstr('Animated forecast icons courtesy of')." <a href=\"http://www.meteotreviglio.com/\">www.meteotreviglio.com</a>.";
}
print "</p>\n";
 
?>
<?php
} // end printmode

 if (! $IncludeMode and $PrintMode ) { ?>
</body>
</html>
<?php 
}  

 
// Functions --------------------------------------------------------------------------------

// get contents from one URL and return as string 
function AW_fetchUrlWithoutHanging($url,$useFopen) {
  global $Status, $needCookie;
  
  $overall_start = time();
  if (! $useFopen) {
   // Set maximum number of seconds (can have floating-point) to wait for feed before displaying page without feed
   $numberOfSeconds=4;   

// Thanks to Curly from ricksturf.com for the cURL fetch functions

  $data = '';
  $domain = parse_url($url,PHP_URL_HOST);
  $theURL = str_replace('nocache','?'.$overall_start,$url);        // add cache-buster to URL if needed
  $Status .= "<!-- curl fetching '$theURL' -->\n";
  $ch = curl_init();                                           // initialize a cURL session
  curl_setopt($ch, CURLOPT_URL, $theURL);                         // connect to provided URL
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);                 // don't verify peer certificate
  curl_setopt($ch, CURLOPT_USERAGENT, 
    'Mozilla/5.0 (AW-forecast.php - saratoga-weather.org)');
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $numberOfSeconds);  //  connection timeout
  curl_setopt($ch, CURLOPT_TIMEOUT, $numberOfSeconds);         //  data timeout
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);              // return the data transfer
  curl_setopt($ch, CURLOPT_NOBODY, false);                     // set nobody
  curl_setopt($ch, CURLOPT_HEADER, true);                      // include header information
  if (isset($needCookie[$domain])) {
    curl_setopt($ch, $needCookie[$domain]);                    // set the cookie for this request
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);             // and ignore prior cookies
    $Status .=  "<!-- cookie used '" . $needCookie[$domain] . "' for GET to $domain -->\n";
  }

  $data = curl_exec($ch);                                      // execute session

  if(curl_error($ch) <> '') {                                  // IF there is an error
   $Status .= "<!-- Error: ". curl_error($ch) ." -->\n";        //  display error notice
  }
  $cinfo = curl_getinfo($ch);                                  // get info on curl exec.
/*
curl info sample
Array
(
[url] => http://saratoga-weather.net/clientraw.txt
[content_type] => text/plain
[http_code] => 200
[header_size] => 266
[request_size] => 141
[filetime] => -1
[ssl_verify_result] => 0
[redirect_count] => 0
  [total_time] => 0.125
  [namelookup_time] => 0.016
  [connect_time] => 0.063
[pretransfer_time] => 0.063
[size_upload] => 0
[size_download] => 758
[speed_download] => 6064
[speed_upload] => 0
[download_content_length] => 758
[upload_content_length] => -1
  [starttransfer_time] => 0.125
[redirect_time] => 0
[redirect_url] =>
[primary_ip] => 74.208.149.102
[certinfo] => Array
(
)

[primary_port] => 80
[local_ip] => 192.168.1.104
[local_port] => 54156
)
*/
  $Status .= "<!-- HTTP stats: " .
    " RC=".$cinfo['http_code'] .
    " dest=".$cinfo['primary_ip'] ;
	if(isset($cinfo['primary_port'])) { 
	  $Status .= " port=".$cinfo['primary_port'] ;
	}
	if(isset($cinfo['local_ip'])) {
	  $Status .= " (from sce=" . $cinfo['local_ip'] . ")";
	}
	$Status .= 
	"\n      Times:" .
    " dns=".sprintf("%01.3f",round($cinfo['namelookup_time'],3)).
    " conn=".sprintf("%01.3f",round($cinfo['connect_time'],3)).
    " pxfer=".sprintf("%01.3f",round($cinfo['pretransfer_time'],3));
	if($cinfo['total_time'] - $cinfo['pretransfer_time'] > 0.0000) {
	  $Status .=
	  " get=". sprintf("%01.3f",round($cinfo['total_time'] - $cinfo['pretransfer_time'],3));
	}
    $Status .= " total=".sprintf("%01.3f",round($cinfo['total_time'],3)) .
    " secs -->\n";

  //$Status .= "<!-- curl info\n".print_r($cinfo,true)." -->\n";
  curl_close($ch);                                              // close the cURL session
  //$Status .= "<!-- raw data\n".$data."\n -->\n"; 
  $i = strpos($data,"\r\n\r\n");
  $headers = substr($data,0,$i);
  $content = substr($data,$i+4);
  if($cinfo['http_code'] <> 200) {
    $Status .= "<!-- headers:\n".$headers."\n -->\n"; 
  }
  return $data;                                                 // return headers+contents

 } else {
//   print "<!-- using file_get_contents function -->\n";
   $STRopts = array(
	  'http'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (AW-forecast.php - saratoga-weather.org)\r\n" .
				"Accept: text/plain,text/html\r\n"
	  ),
	  'https'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (AW-forecast.php - saratoga-weather.org)\r\n" .
				"Accept: text/plain,text/html\r\n"
	  )
	);
	
   $STRcontext = stream_context_create($STRopts);

   $T_start = AW_fetch_microtime();
   $xml = file_get_contents($url,false,$STRcontext);
   $T_close = AW_fetch_microtime();
   $headerarray = get_headers($url,0);
   $theaders = join("\r\n",$headerarray);
   $xml = $theaders . "\r\n\r\n" . $xml;

   $ms_total = sprintf("%01.3f",round($T_close - $T_start,3)); 
   $Status .= "<!-- file_get_contents() stats: total=$ms_total secs -->\n";
   $Status .= "<-- get_headers returns\n".$theaders."\n -->\n";
//   print " file() stats: total=$ms_total secs.\n";
   $overall_end = time();
   $overall_elapsed =   $overall_end - $overall_start;
   $Status .= "<!-- fetch function elapsed= $overall_elapsed secs. -->\n"; 
//   print "fetch function elapsed= $overall_elapsed secs.\n"; 
   return($xml);
 }

}    // end AW_fetch_URL

// ------------------------------------------------------------------

function AW_fetch_microtime()
{
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + (float)$sec);
}


// -------------------------------------------------------------------------------------------
   
 function AW_img_replace ( $AWimage, $AWcondtext,$AWpop,$AWcloudcover) {
//
// optionally replace the WeatherUnderground icon with an NWS icon instead.
// 
 global $NWSiconlist,$iconDir,$iconType,$Status;
 
 $curicon = isset($NWSiconlist[$AWimage])?$NWSiconlist[$AWimage]:''; // translated icon (if any)
 	$tCCicon = AW_octets($AWcloudcover);

 if (!$curicon) { // no change.. use AW icon
   return("<img src=\"{$iconDir}na.jpg\" width=\"55\" height=\"55\" 
  alt=\"$AWcondtext\" title=\"$AWcondtext\"/>"); 
 }
 // override icon with cloud coverage octets for Images of partly-cloudy-* and clear-*
 if(preg_match('/^(partly|clear)/i',$AWimage)) {
	 $curicon = $tCCicon.'.jpg';
	 if(strpos($AWimage,'n.png') !==false) {
		 $curicon = 'n'.$curicon;
	 }
	 $Status .= "<!-- AW_img_replace using curicon=$curicon instead based on cloud coverage -->\n";
 }
 if(preg_match('/^wind/i',$AWimage) and $iconType !== '.gif') {
	 // note: Meteotriviglio icons do not have the wind_{sky}.gif icons, only wind.gif
	 $curicon = 'wind_'.$tCCicon.'.jpg';
	 if(strpos($AWimage,'n.png') !==false) {
		 $curicon = 'n'.$curicon;
	 }
	 $Status .= "<!-- AW_img_replace using curicon=$curicon instead based on cloud coverage -->\n";
 }
 
  if($iconType <> '.jpg') {
	  $curicon = preg_replace('|\.jpg|',$iconType,$curicon);
  }
  $Status .= "<!-- AW_img_replace replace icon '$AWimage' with ";
  if ($AWpop > 0) {
	$testicon = preg_replace('|'.$iconType.'|',$AWpop.$iconType,$curicon);
		if (file_exists("$iconDir$testicon")) {
			$newicon = $testicon;
		} else {
			$newicon = $curicon;
		}
  } else {
		$newicon = $curicon;
  }
  $Status .= "'$newicon' pop=$AWpop -->\n";

  return("<img src=\"$iconDir$newicon\" width=\"55\" height=\"55\" 
  alt=\"$AWcondtext\" title=\"$AWcondtext\"/>"); 
 
 
 }

// -------------------------------------------------------------------------------------------
 
function AW_prepareJSON($input) {
	global $Status;
   
   //This will convert ASCII/ISO-8859-1 to UTF-8.
   //Be careful with the third parameter (encoding detect list), because
   //if set wrong, some input encodings will get garbled (including UTF-8!)

   list($isUTF8,$offset,$msg) = AW_check_utf8($input);
   
   if(!$isUTF8) {
	   $Status .= "<!-- AW_prepareJSON: Oops, non UTF-8 char detected at $offset. $msg. Doing utf8_encode() -->\n";
	   $str = utf8_encode($input);
       list($isUTF8,$offset,$msg) = AW_check_utf8($str);
	   $Status .= "<!-- AW_prepareJSON: after utf8_encode, i=$offset. $msg. -->\n";   
   } else {
	   $Status .= "<!-- AW_prepareJSON: $msg. -->\n";
	   $str = $input;
   }
  
   //Remove UTF-8 BOM if present, json_decode() does not like it.
   if(substr($str, 0, 3) == pack("CCC", 0xEF, 0xBB, 0xBF)) $str = substr($str, 3);
   
   return $str;
}

// -------------------------------------------------------------------------------------------

function AW_check_utf8($str) {
// check all the characters for UTF-8 compliance so json_decode() won't choke
// Sometimes, an ISO international character slips in the AW text string.	  
     $len = strlen($str); 
     for($i = 0; $i < $len; $i++){ 
         $c = ord($str[$i]); 
         if ($c > 128) { 
             if (($c > 247)) return array(false,$i,"c>247 c='$c'"); 
             elseif ($c > 239) $bytes = 4; 
             elseif ($c > 223) $bytes = 3; 
             elseif ($c > 191) $bytes = 2; 
             else return false; 
             if (($i + $bytes) > $len) return array(false,$i,"i+bytes>len bytes=$bytes,len=$len"); 
             while ($bytes > 1) { 
                 $i++; 
                 $b = ord($str[$i]); 
                 if ($b < 128 || $b > 191) return array(false,$i,"128<b or b>191 b=$b"); 
                 $bytes--; 
             } 
         } 
     } 
     return array(true,$i,"Success. Valid UTF-8"); 
 } // end of check_utf8

// -------------------------------------------------------------------------------------------
 
function AW_decode_JSON_error() {
	
  $Status = '';
  $Status .= "<!-- json_decode returns ";
  switch (json_last_error()) {
	case JSON_ERROR_NONE:
		$Status .= ' - No errors';
	break;
	case JSON_ERROR_DEPTH:
		$Status .= ' - Maximum stack depth exceeded';
	break;
	case JSON_ERROR_STATE_MISMATCH:
		$Status .= ' - Underflow or the modes mismatch';
	break;
	case JSON_ERROR_CTRL_CHAR:
		$Status .= ' - Unexpected control character found';
	break;
	case JSON_ERROR_SYNTAX:
		$Status .= ' - Syntax error, malformed JSON';
	break;
	case JSON_ERROR_UTF8:
		$Status .= ' - Malformed UTF-8 characters, possibly incorrectly encoded';
	break;
	default:
		$Status .= ' - Unknown error, json_last_error() returns \''.json_last_error(). "'";
	break;
   } 
   $Status .= " -->\n";
   return($Status);
}

// -------------------------------------------------------------------------------------------

function AW_loadLangDefaults () {
	global $AWlanguages, $AWlangCharsets;
/*
    en - [DEFAULT] English
    ar - Arabic
    az - Azerbaijani
    be - Belarusian
    bg - Bulgarian
    bs - Bosnian
    ca - Catalan
    cz - Czech
    da - Danish
    de - German
    fi - Finnish
    fr - French
    el - Greek
    et - Estonian
    hr - Croation
    hu - Hungarian
    id - Indonesian
    it - Italian
    is - Icelandic
    kw - Cornish
    lt - Lithuanian
    nb - Norwegian Bokmål
    nl - Dutch
    pl - Polish
    pt - Portuguese
    ro - Romanian
    ru - Russian
    sk - Slovak
    sl - Slovenian
    sr - Serbian
    sv - Swedish
    tr - Turkish
    uk - Ukrainian

*/
 
 $AWlanguages = array(  // our template language codes v.s. lang:LL codes for JSON
	'af' => 'en',
	'bg' => 'bg',
	'cs' => 'cs',
	'ct' => 'ca',
	'dk' => 'da',
	'nl' => 'nl',
	'en' => 'en',
	'fi' => 'fi',
	'fr' => 'fr',
	'de' => 'de',
	'el' => 'el',
	'ga' => 'en',
	'it' => 'it',
	'he' => 'he',
	'hu' => 'hu',
	'no' => 'nb',
	'pl' => 'pl',
	'pt' => 'pt',
	'ro' => 'ro',
	'es' => 'es',
	'se' => 'sv',
	'si' => 'sl',
	'sk' => 'sk',
	'sr' => 'sr',
  );

  $AWlangCharsets = array(
	'bg' => 'ISO-8859-5',
	'cs' => 'ISO-8859-2',
	'el' => 'ISO-8859-7',
	'he' => 'UTF-8', 
	'hu' => 'ISO-8859-2',
	'ro' => 'ISO-8859-2',
	'pl' => 'ISO-8859-2',
	'si' => 'ISO-8859-2',
	'sk' => 'Windows-1250',
	'sr' => 'Windows-1250',
	'ru' => 'ISO-8859-5',
  );

} // end loadLangDefaults

function AW_load_builtin_translate ($lang) {
	global $Status;
	
/*
Note: We packed up the translation array as it is a mix of various character set
types and editing the raw text can easily change the character presentation.
The TRANTABLE was created by using

	$transSerial = serialize($transArray);
	$b64 = base64_encode($transSerial);
	print "\n";
	$tArr = str_split($b64,72);
	print "define('TRANTABLE',\n'";
	$tStr = '';
	foreach($tArr as $rec) {
		$tStr .= $rec."\n";
	}
	$tStr = trim($tStr);
	print $tStr;
	print "'); // end of TRANTABLE encoded\n";
	
and that result included here.

It will reconstitute with unserialize(base64_decode(TRANTABLE)) to look like:
 ... 
 
 'dk' => array ( 
    'charset' => 'ISO-8859-1',
    'Sunday' => 'Søndag',
    'Monday' => 'Mandag',
    'Tuesday' => 'Tirsdag',
    'Wednesday' => 'Onsdag',
    'Thursday' => 'Torsdag',
    'Friday' => 'Fredag',
    'Saturday' => 'Lørdag',
    'Sunday night' => 'Søndag nat',
    'Monday night' => 'Mandag nat',
    'Tuesday night' => 'Tirsdag nat',
    'Wednesday night' => 'Onsdag nat',
    'Thursday night' => 'Torsdag nat',
    'Friday night' => 'Fredag nat',
    'Saturday night' => 'Lørdag nat',
    'Today' => 'I dag',
    'Tonight' => 'I nat',
    'This afternoon' => 'I eftermiddag',
    'Rest of tonight' => 'Resten af natten',
  ), // end dk 
...

and the array for the chosen language will be returned, or the English version if the 
language is not in the array.

*/
if(!file_exists("AW-forecast-lang.php")) {
	print "<p>Warning: AW-forecast-lang.php translation file was not found.  It is required";
	print " to be in the same directory as AW-forecast.php.</p>\n";
	exit;
	}
include_once("AW-forecast-lang.php");

$default = array(
    'charset' => 'ISO-8859-1',
    'Sunday' => 'Sunday',
    'Monday' => 'Monday',
    'Tuesday' => 'Tuesday',
    'Wednesday' => 'Wednesday',
    'Thursday' => 'Thursday',
    'Friday' => 'Friday',
    'Saturday' => 'Saturday',
    'Sunday night' => 'Sunday night',
    'Monday night' => 'Monday night',
    'Tuesday night' => 'Tuesday night',
    'Wednesday night' => 'Wednesday night',
    'Thursday night' => 'Thursday night',
    'Friday night' => 'Friday night',
    'Saturday night' => 'Saturday night',
    'Today' => 'Today',
    'Tonight' => 'Tonight',
    'This afternoon' => 'This afternoon',
    'Rest of tonight' => 'Rest of tonight',
		'High:' => 'High:',
    'Low:' =>  'Low:',
		'Updated:' => 'Updated:',
		'Aerisweather Forecast for:' => 'Aerisweather Forecast for:',
    'NESW' =>  'NESW', // cardinal wind directions
		'Wind' => 'Wind',
    'UV index' => 'UV Index',
    'Chance of precipitation' =>  'Chance of precipitation',
		 'mph' => 'mph',
     'kph' => 'km/h',
     'mps' => 'm/s',
		 'Temperature' => 'Temperature',
		 'Barometer' => 'Barometer',
		 'Dew Point' => 'Dew Point',
		 'Humidity' => 'Humidity',
		 'Visibility' => 'Visibility',
		 'Wind chill' => 'Wind chill',
		 'Heat index' => 'Heat index',
		 'Humidex' => 'Humidex',
		 'Sunrise' => 'Sunrise',
		 'Sunset' => 'Sunset',
		 'Currently' => 'Currently',
		 'rain' => 'rain',
		 'snow' => 'snow',
		 'sleet' => 'sleet',
		 'Weather conditions at 999 from forecast point.' => 
		   'Weather conditions at 999 from forecast point.',
		 'Daily Forecast' => 'Daily Forecast',
		 'Hourly Forecast' => 'Hourly Forecast',
		 'Meteogram' => 'Meteogram',



);

 $t = unserialize(base64_decode(TRANTABLE));
 
 if(isset($t[$lang])) {
	 $Status .= "<!-- loaded translations for lang='$lang' for period names -->\n";
	 return($t[$lang]);
 } else {
	 $Status .= "<!-- loading English period names -->\n";
	 return($default);
 }
 
}
// ------------------------------------------------------------------

//  convert degrees into wind direction abbreviation   
function AW_WindDir ($degrees) {
   // figure out a text value for compass direction
// Given the wind direction, return the text label
// for that value.  16 point compass
   $winddir = $degrees;
   if ($winddir == "n/a") { return($winddir); }

  if (!isset($winddir)) {
    return "---";
  }
  if (!is_numeric($winddir)) {
	return($winddir);
  }
  $windlabel = array ("N","NNE", "NE", "ENE", "E", "ESE", "SE", "SSE", "S",
	 "SSW","SW", "WSW", "W", "WNW", "NW", "NNW");
  $dir = $windlabel[ (integer)fmod((($winddir + 11) / 22.5),16) ];
  return($dir);

} // end function AW_WindDir
// ------------------------------------------------------------------

function AW_WindDirTrans($inwdir) {
	global $tranTab, $Status;
	$wdirs = $tranTab['NESW'];  // default directions
	$tstr = $inwdir;
	$Status .= "<!-- AW_WindDirTrans in=$inwdir using ";
	if(strlen($wdirs) == 4) {
		$tstr = strtr($inwdir,'NESW',$wdirs); // do translation
		$Status .= " strtr for ";
	} elseif (preg_match('|,|',$wdirs)) { //multichar translation
		$wdirsmc = explode(',',$wdirs);
		$wdirs = array('N','E','S','W');
		$wdirlook = array();
		foreach ($wdirs as $n => $d) {
			$wdirlook[$d] = $wdirsmc[$n];
		} 
		$tstr = ''; // get ready to pass once through the string
		for ($n=0;$n<strlen($inwdir);$n++) {
			$c = substr($inwdir,$n,1);
			if(isset($wdirlook[$c])) {
				$tstr .= $wdirlook[$c]; // use translation
			} else {
				$tstr .= $c; // use regular
			}
		}
		$Status .= " array substitute for ";
	}
	$Status .= "NESW=>'".$tranTab['NESW']."' output='$tstr' -->\n";

  return($tstr);
}
// ------------------------------------------------------------------

function AW_round($item,$dp) {
	$t = round($item,$dp);
	if ($t == '-0') {
		$t = 0;
	}
	return ($t);
}
// ------------------------------------------------------------------

function AW_octets ($coverage) {
	global $Status;
	
	$octets = round($coverage / 12.5,1);
	$Status .= "<!-- AW_octets in=$coverage octets=$octets ";
	if($octets < 1.0) {
		$Status .= " clouds=skc -->\n";
		return('skc');
	} 
	elseif ($octets < 3.0) {
		$Status .= " clouds=few -->\n";
		return('few');
	}
	elseif ($octets < 5.0) {
		$Status .= " clouds=sct -->\n";
		return('sct');
	}
	elseif ($octets < 8.0) {
		$Status .= " clouds=bkn -->\n";
		return('bkn');
	} else {
		$Status .= " clouds=ovc -->\n";
		return('ovc');
	}
	
}
// ------------------------------------------------------------------

function AW_conv_baro($hPa) {
	# even 'us' imperial returns pressure in hPa so we need to convert
	global $showUnitsAs;
	
	if($showUnitsAs == 'us') {
		$t = (float)$hPa * 0.02952998751;
		return(sprintf("%01.2f",$t));
	} else {
		return( sprintf("%01.1f",$hPa) );
	}
}
// ------------------------------------------------------------------

function AW_conv_units($type,$value) {
	global $Units,$Status,$doDebug;
	
	$output = $value; // default is no conversion
	
  if($type == 'W') {
		//input wind in km/h 
		switch ($Units[$type]) {
			case 'm/s':
				$t = (float)$value * 0.277778;
		    $output = sprintf("%01.1f",round($t,1));
				break;
			case 'km/h':
			  $output = $value;
				break;
			case 'mph':
				$t = (float)$value * 0.621371;
		    $output = round($t,0);
				break;
			case 'kts':
				$t = (float)$value * 0.539957;
		    $output = round($t,0);
				break;
		}
	}
  if($type == 'T') {
		//input temperature in C 
		switch ($Units[$type]) {
			case '&deg;C':
		    $output = sprintf("%01.1f",$value);
				break;
			case '&deg;F':
			  // (0°C × 9/5) + 32
				$t = ((float)$value * 9.0)/5.0 + 32.0;
		    $output = round($t,0);
				break;
		}
	}
  if($type == 'R') {
		//input rain in mm
		switch ($Units[$type]) {
			case 'mm':
		    $output = sprintf("%01.1f",$value);
				break;
			case 'in':
				$t = (float)$value * 0.0393701;
		    $output = sprintf("%01.2f",$t);
				break;
		}
	}
  if($type == 'S') {
		//input snow in CM
		switch ($Units[$type]) {
			case 'cm':
		    $output = sprintf("%01.1f",$value);
				break;
			case 'in':
				$t = (float)$value * 0.393701;
		    $output = sprintf("%01.2f",$t);
				break;
		}
	}
  if($type == 'I') {
		//input ice in mm
		switch ($Units[$type]) {
			case 'mm':
		    $output = sprintf("%01.1f",$value);
				break;
			case 'in':
				$t = (float)$value * 0.0393701;
		    $output = sprintf("%01.2f",$t);
				break;
		}
	}
  if($type == 'P') {
		//input pressure in hPa/millibars
		switch ($Units[$type]) {
			case 'mb':
		    $output = sprintf("%01.1f",$value);
				break;
			case 'hPa':
		    $output = sprintf("%01.1f",$value);
				break;
			case 'inHg':
				$t = (float)$value * 0.02952998751;
		    $output = sprintf("%01.2f",$t);
				break;
		}
	}
	if($doDebug) {
		$Status .= "<!-- AW_conv_units: type='$type' units='".$Units[$type]."' in='$value' out='$output' -->\n";
	}
	return($output);
	
} // end AW_conf_units
// ------------------------------------------------------------------

function AW_decode_conds($condcode) {
	$output = '';
	
/*
The weather codes are given in the following standard format:

[coverage]:[intensity]:[weather]

For example, the following code suggests that there is a slight chance (S) for light (L) rain showers (RW) for the period:

S:L:RW

If a weather type is not expected for any period, then the clouds code will be provided in place of the weather field along with empty values for coverage and intensity:

::FW
*/
//Cloud Codes

//Cloud codes indicate the percentage of the sky that is covered by clouds.
$CloudCodes = array(
  'CL' => 'Clear ', // Cloud coverage is 0-7% of the sky.
  'FW' => 'Mostly Clear ', // Cloud coverage is 7-32% of the sky.
  'SC' => 'Partly Cloudy ', // Cloud coverage is 32-70% of the sky.
  'BK' => 'Mostly Cloudy ', // Cloud coverage is 70-95% of the sky.
  'OV' => 'Overcast ', // Cloud coverage is 95-100% of the sky.
);

//Coverage Codes

//Coverage codes are used with coded weather strings to provide information regarding the coverage of precipitation or other weather types. This can be used to determine how widespread a specific weather type will be, from isolated to definite.
$CoverageCodes = array(
  'AR' => 'Areas of ', // 
  'BR' => 'Brief ', // 
  'C' => 'Chance of ', // 
  'D' => '', // was 'Definite '
  'FQ' => 'Frequent ', // 
  'IN' => 'Intermittent ', // 
  'IS' => 'Isolated ', // 
  'L' => 'Likely ', // 
  'NM' => 'Numerous ', // 
  'O' => 'Occasional ', // 
  'PA' => 'Patchy ', // 
  'PD' => 'Periods of ', // 
  'S' => 'Slight chance ', // 
  'SC' => 'Scattered ', // 
  'VC' => 'Nearby ', // 
  'WD' => 'Widespread ', // 
);

//Intensity Codes

//Intensity codes indicate how heavy or light the accompanying weather type is or will be.
$IntensityCodes = array(
  'VL' => 'Very light ', // 
  'L' => 'Light ', // 
  'H' => 'Heavy ', // 
  'VH' => 'Very heavy ', // 
//	Moderate (if no intensity is provided, moderate weather type is assumed) 	
);

//Weather Codes

//Weather codes indicate the type of weather that is observed or forecasted. These codes are typically combined with the Coverage and Intensity codes to provide more specifics regarding the weather conditions.
$WeatherCodes = array(
  'A' => 'Hail ', // 
  'BD' => 'Blowing dust ', // 
  'BN' => 'Blowing sand ', // 
  'BR' => 'Mist ', // 
  'BS' => 'Blowing snow ', // 
  'BY' => 'Blowing spray ', // 
  'F' => 'Fog ', // 
  'FR' => 'Frost ', // 
  'H' => 'Haze ', // 
  'IC' => 'Ice crystals ', // 
  'IF' => 'Ice fog ', // 
  'IP' => 'Sleet ', // 
  'K' => 'Smoke ', // 
  'L' => 'Drizzle ', // 
  'R' => 'Rain ', // 
  'RW' => 'Rain showers ', // 
  'RS' => 'Rain/snow mix ', // 
  'SI' => 'Snow/sleet mix ', // 
  'WM' => 'Wintry mix ', // 
  'S' => 'Snow ', // 
  'SW' => 'Snow showers ', // 
  'T' => 'Thunderstorms ', // 
  'UP' => 'Unk precip ', // May occur in an automated observation station, which cannot determine the precipitation type falling.
  'VA' => 'Volcanic ash ', // 
  'WP' => 'Waterspouts ', // 
  'ZF' => 'Freezing fog ', // 
  'ZL' => 'Freezing drizzle ', // 
  'ZR' => 'Freezing rain ', // 
  'ZY' => 'Freezing spray ', // 
);
	
	list($cover,$intensity,$weather) = explode(':',$condcode.'::');
	
	if(empty($cover) and empty($intensity) and isset($CloudCodes[$weather])) {
		return(trim($CloudCodes[$weather]));
	}
	if(!empty($cover) and isset($CoverageCodes[$cover])) {
		$output .= $CoverageCodes[$cover];
	}

	if(!empty($intensity) and isset($IntensityCodes[$intensity])) {
		$output .= $IntensityCodes[$intensity];
	}

	if(!empty($weather) and isset($WeatherCodes[$weather])) {
		$output .= $WeatherCodes[$weather];
	}
	
	$output = ucfirst(strtolower(trim($output)));
	return($output);
	
}
// ------------------------------------------------------------------

function AW_load_parser_trans($lang) {
	global $Status;
	$Language = array();
	$Language['charset'] = 'ISO-8859-1'; // default for languages
	if($lang == 'en') {return($Language);}
	
	$file = "./AWlang/plaintext-parser-lang-$lang.txt";
	$config = array();
  if (file_exists("$file") ) {
    $config = file("$file");
    $Status .= "<!-- translation file $file loaded -->\n";
  } else {
		$Status .= "<!-- translation file '$file' NOT FOUND -->\n";
	}
  foreach ($config as $key => $rec) { // load the parser condition strings
    $recin = trim($rec);
    if ($recin and substr($recin,0,1) <> '#') { // got a non comment record
      list($type,$keyword,$dayicon,$nighticon,$condition) = explode('|',$recin . '|||||');

			if (isset($type) and strtolower($type) == 'lang' and isset($dayicon)) {
				$Language["$keyword"] = "$dayicon";
			} 
			if (isset($type) and strtolower($type) == 'charset' and isset($keyword)) {
				$Language['charset'] = trim($keyword);
				$Status .= "<!-- using charset '$keyword' -->\n";
			} 
		}
	}
	
	return($Language);
		
}

// ------------------------------------------------------------------

function AW_do_parser_trans($text,$Language) {
	$t = str_replace(' with ',', ',$text);
	$t = str_replace(' of ',' ',$t);
	reset ($Language); // process in order of the file
	foreach ($Language as $key => $replacement) {
		$t = str_replace($key,$replacement,$t);
	}
	return($t);	
}

// End of functions --------------------------------------------------------------------------
