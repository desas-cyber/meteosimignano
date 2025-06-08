<?php 
// OWM-forecast.php script by Ken True - webmaster@saratoga-weather.org
//    Forecast from openweathermap.org - based on DS-forecast.php Version 1.11 - 27-Dec-2022
//
// Version 1.00 - 16-Nov-2018 - initial release
// Version 1.01 - 17-Nov-2018 - added wind unit translation, fixed -0 temp display, added alerts (only English available)
// Version 1.02 - 19-Nov-2018 - added Updated: and Forecast by: display/translations
// Version 1.03 - 29-Nov-2018 - added fixes for summaries with embedded UTF symbols.
// Version 1.04 - 04-Dec-2018 - added Serbian (sr) language support
// Version 1.05 - 08-Dec-2018 - added optional current conditions display box, cloud-cover now used for better icon choices
// Version 1.06 - 05-Jan-2019 - fixed Hebrew forecast display for Saratoga template
// Version 1.07 - 07-Jan-2019 - formatting fix for Hebrew display in Saratoga template
// Version 1.08 - 15-Jan-2019 - added check for good JSON return before saving cache file
// Version 1.09 - 23-Jan-2019 - added hourly forecast and tabbed display
// Version 1.10 - 19-Jan-2022 - fix for PHP 8.1 Deprecated errata
// Version 1.11 - 27-Dec-2022 - fixes for PHP 8.2
// Version 2.00 - 04-Feb-2023 - rewrite for OpenWeatherMap API V3.0 use
// Version 2.01 - 07-Feb-2023 - added units conversions and si,ca,uk,us for ShowUnitsAs compatibility
// Version 2.02 - 06-Mar-2023 - added diagnostics for 40x API failures
//
$Version = "OWM-forecast.php (ML) Version 2.02 - 06-Mar-2023";
//
// error_reporting(E_ALL);  // uncomment to turn on full error reporting
//
// script available at http://saratoga-weather.org/scripts.php
//  
// you may copy/modify/use this script as you see fit,
// no warranty is expressed or implied.
//
// This script parses the openweathermap.org forecast JSON API and loads icons/text into
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
//    $doIncludeOWM = true;
//    include("OWM-forecast.php");  for just the text
//  or ------------
//    $doPrintOWM = false;
//    include("OWM-forecast.php");  for setting up the $OWMforecast... variables without printing
//
//  or ------------
//    $doIncludeOWM = true;
//    $doPrintConditions = true;
//    $doPrintHeadingOWM = true;
//    $doPrintIconsOWM = true;
//    $doPrintTextOWM = false
//    include("OWM-forecast.php");  include mode, print only heading and icon set
//
// Variables returned (useful for printing an icon or forecast or two...)
//
// $OWMforecastcity 		- Name of city from OWM Forecast header
//
// The following variables exist for $i=0 to $i= number of forecast periods minus 1
//  a loop of for ($i=0;$i<count($OWMforecastday);$i++) { ... } will loop over the available 
//  values.
//
// $OWMforecastday[$i]	- period of forecast
// $OWMforecasttext[$i]	- text of forecast 
// $OWMforecasttemp[$i]	- Temperature with text and formatting
// $OWMforecastpop[$i]	- Number - Probabability of Precipitation ('',10,20, ... ,100)
// $OWMforecasticon[$i]   - base name of icon graphic to use
// $OWMforecastcond[$i]   - Short legend for forecast icon 
// $OWMforecasticons[$i]  - Full icon with Period, <img> and Short legend.
// $OWMforecastwarnings = styled text with hotlinks to advisories/warnings
// $OWMcurrentConditions = table with current conds at point close to lat/long selected
//
// Settings ---------------------------------------------------------------
// REQUIRED: a openweathermap.org API KEY.. sign up at https://www.openweathermap.org/api
$OWMAPIkey = 'specify-for-standalone-use-here'; // use this only for standalone / non-template use
// NOTE: if using the Saratoga template, add to Settings.php a line with:
//    $SITE['OWMAPIkey'] = 'your-api-key-here';
// and that will enable the script to operate correctly in your template
//
$iconDir ='./forecast/images/';	// directory for carterlake icons './forecast/images/'
$iconType = '.jpg';				// default type='.jpg' 
//                        use '.gif' for animated icons from http://www.meteotreviglio.com/
//
// The forecast(s) .. make sure the first entry is the default forecast location.
// The contents will be replaced by $SITE['OWMforecasts'] if specified in your Settings.php

$OWMforecasts = array(
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

//
$maxWidth = '640px';                      // max width of tables (could be '100%')
$maxIcons = 10;                           // max number of icons to display
$maxForecasts = 14;                       // max number of Text forecast periods to display
$maxForecastLegendWords = 4;              // more words in forecast legend than this number will use our forecast words 
$numIconsInFoldedRow = 8;                 // if words cause overflow of $maxWidth pixels, then put this num of icons in rows
$autoSetTemplate = true;                  // =true set icons based on wide/narrow template design
$cacheFileDir = './';                     // default cache file directory
$cacheName = "OWM-forecast-json.txt";      // locally cached page from OWM
$refetchSeconds = 3600;                   // cache lifetime (3600sec = 60 minutes)
//
// Units: Temp,Baro,Wind,Rain,Snow,Distance
// 'si' = C,hPa,m/s,mm,mm,km
// 'ca' = C,hPa,km/h,mm,mm,km
// 'uk' = C,mb,mph,mm,mm,km
// 'us' = F,inHg,mph,in,in,km
// 
$showUnitsAs  = 'ca'; //

$charsetOutput = 'ISO-8859-1';        // default character encoding of output
//$charsetOutput = 'UTF-8';            // for standalone use if desired
$lang = 'en';	// default language
$foldIconRow = false;  // =true to display icons in rows of 5 if long texts are found
$timeFormat = 'Y-m-d H:i T';  // default time display format

$showConditions = true; // set to true to show current conditions box

// ---- end of settings ---------------------------------------------------

// overrides from Settings.php if available
global $SITE;
if (isset($SITE['OWMforecasts']))   {$OWMforecasts = $SITE['OWMforecasts']; }
if (isset($SITE['OWMAPIkey']))	{$OWMAPIkey = $SITE['OWMAPIkey']; } // new V3.00
if (isset($SITE['OWMshowUnitsAs'])) { $showUnitsAs = $SITE['OWMshowUnitsAs']; }
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
if (isset($SITE['OWMshowConditions'])) {$showConditions = $SITE['OWMshowConditions'];} // new V1.05
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
   header("Content-type: text/plain");
   header("Accept-Ranges: bytes");
   header("Content-Length: $download_size");
   header('Connection: close');
   
   readfile($filenameReal);
   exit;
}

$Status = "<!-- $Version on PHP ".phpversion()." -->\n";

$OWMcurrentConditions = ''; // HTML for table of current conditions
//------------------------------------------------

if(preg_match('|specify|i',$OWMAPIkey)) {
	print "<p>Note: the OWM-forecast.php script requires an API key from openweathermap.org to operate.<br/>";
	print "Visit <a href=\"https://openweathermap.org/\">openweathermap.org</a> to ";
	print "register for an API key.</p>\n";
	if( isset($SITE['fcsturlOWM']) ) {
		print "<p>Insert in Settings.php an entry for:<br/><br/>\n";
		print "\$SITE['OWMAPIkey'] = '<i>your-key-here</i>';<br/><br/>\n";
		print "replacing <i>your-key-here</i> with your OWM API key.</p>\n";
	}
	return;
}

$UnitsTab = array(
 'si' => array('U'=>'metric','T'=>'&deg;C','W'=>'m/s','P'=>'hPa','R'=>'mm','D'=>'km','S'=>'mm'),
 'ca' => array('U'=>'metric','T'=>'&deg;C','W'=>'km/h','P'=>'hPa','R'=>'mm','D'=>'km','S'=>'mm'),
 'uk' => array('U'=>'metric','T'=>'&deg;C','W'=>'mph','P'=>'mb','R'=>'mm','D'=>'km','S'=>'cm'),
 'us' => array('U'=>'imperial','T'=>'&deg;F','W'=>'mph','P'=>'inHg','R'=>'in','D'=>'km','S'=>'in'),
);


if(isset($UnitsTab[$showUnitsAs])) {
  $Units = $UnitsTab[$showUnitsAs];
} else {
	$Units = $UnitsTab['ca'];
}

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

OWM_loadLangDefaults (); // set up the language defaults

if($charsetOutput == 'UTF-8') {
	foreach ($OWMlangCharsets as $l => $cs) {
		$OWMlangCharsets[$l] = 'UTF-8';
	}
	$Status .= "<!-- charsetOutput UTF-8 selected for all languages. -->\n";
	$Status .= "<!-- OWMlangCharsets\n".print_r($OWMlangCharsets,true)." \n-->\n";	
}

$OWMLANG = 'en'; // Default to English for API
$lang = strtolower($lang); 	
if( isset($OWMlanguages[$lang]) ) { // if $lang is specified, use it
	$SITE['lang'] = $lang;
	$OWMLANG = $OWMlanguages[$lang];
	$charsetOutput = (isset($OWMlangCharsets[$lang]))?$OWMlangCharsets[$lang]:$charsetOutput;
}

if(isset($_GET['lang']) and isset($OWMlanguages[strtolower($_GET['lang'])]) ) { // template override
	$lang = strtolower($_GET['lang']);
	$SITE['lang'] = $lang;
	$OWMLANG = $OWMlanguages[$lang];
	$charsetOutput = (isset($OWMlangCharsets[$lang]))?$OWMlangCharsets[$lang]:$charsetOutput;
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

if(!isset($OWMforecasts[0])) {
	// print "<!-- making NWSforecasts array default -->\n";
	$OWMforecasts = array("Saratoga|37.27465,-122.02295"); // create default entry
}

//  print "<!-- NWSforecasts\n".print_r($OWMforecasts,true). " -->\n";
// Set the default zone. The first entry in the $SITE['NWSforecasts'] array.
list($Nl,$Nn) = explode('|',$OWMforecasts[0].'|||');
$FCSTlocation = $Nl;
$OWM_LATLONG = $Nn;

if(!isset($OWMforecasts[$haveIndex])) {
	$haveIndex = 0;
}

// locations added to the drop down menu and set selected zone values
$dDownMenu = '';
for ($m=0;$m<count($OWMforecasts);$m++) { // for each locations
  list($Nlocation,$Nname) = explode('|',$OWMforecasts[$m].'|||');
  $seltext = '';
  if($haveIndex == $m) {
    $FCSTlocation = $Nlocation;
    $OWM_LATLONG = $Nname;
	$seltext = ' selected="selected" ';
  }
  $dDownMenu .= "     <option value=\"$m\"$seltext>".langtransstr($Nlocation)."</option>\n";
}

// build the drop down menu
$ddMenu = '';

// create menu if at least two locations are listed in the array
if (isset($OWMforecasts[0]) and isset($OWMforecasts[1])) {
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

list($OWMlat,$OWMlong) = explode(',',$OWM_LATLONG);
$showUnitsAs = $Units['U'];  // convert to 'imperial' or 'metric' (we don't use 'standard' for API)
$showTempsAs = ($showUnitsAs == 'imperial')? 'F':'C';
$Status .= "<!-- temps in $showTempsAs -->\n";
$fileName = "https://api.openweathermap.org/data/3.0/onecall?lat=$OWMlat&lon=$OWMlong&exclude=minutely" .
  "&units=$showUnitsAs&lang=$OWMLANG&appid=$OWMAPIkey";

if ($doDebug) {
  $Status .= "<!-- OWM URL: $fileName -->\n";
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
$cacheName = preg_replace('|\.txt|is',"-$haveIndex-$showUnitsAs-$lang.txt",$cacheName); // unique cache per units & language used

$APIfileName = $fileName; 

if($showConditions) {
	$refetchSeconds = 15*60; // shorter refresh time so conditions will be 'current'
}

if (! $Force and file_exists($cacheName) and filemtime($cacheName) + $refetchSeconds > time()) {
      $html = implode('', file($cacheName)); 
      $Status .= "<!-- loading from $cacheName (" . strlen($html) . " bytes) -->\n"; 
  } else { 
      $Status .= "<!-- loading from $APIfileName. -->\n"; 
      $html = OWM_fetchUrlWithoutHanging($APIfileName,false); 
	  
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
		
			$html = OWM_fetchUrlWithoutHanging($sURL,false);
			$RC = '';
			if (preg_match("|^HTTP\/\S+ (.*)\r\n|",$html,$matches)) {
				$RC = trim($matches[1]);
			}
			$Status .= "<!-- RC=$RC, bytes=" . strlen($html) . " -->\n";
		  }
	   }
    }
		if(strpos($RC,'200') === false) {
			$stuff = explode("\r\n\r\n",$html); // maybe we have more than one header due to redirects.
      $content = (string)array_pop($stuff); // last one is the content
      $headers = (string)array_pop($stuff); // next-to-last-one is the headers
      $rawJSON = $content;
      $Status .= "<!-- rawJSON size is ".strlen($rawJSON). " bytes -->\n";
			$JSON = json_decode($rawJSON,true);
			if(isset($JSON['cod']) and isset($JSON['message'])) {
				print $Status;
				print "<p><b>Error: code=".$JSON['cod']."</b><br/>\n";
				print "<b>Message:</b> ".$JSON['message']."<br/>\n";
				print "Correct the error to obtain a forecast.</p>\n";
			} else {
				print "<p> ERROR: Raw JSON returns<br/>\n".$rawJSON."<br/>Correct the error to obtain forecast.</p>\n";
			}
			return;
		}

		if(preg_match('!pressure!is',$html)) {
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
				print "<p>Sorry.. the OpenWeatherMap forecast is not available.</p>\n";
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
 $tranTab = OWM_loadTranslate($lang);
 
 //  process the file .. select out the 7-day forecast part of the page
  $UnSupported = false;

// --------------------------------------------------------------------------------------------------
  
 $Status .= "<!-- processing JSON entries for forecast -->\n";
  $stuff = explode("\r\n\r\n",$html); // maybe we have more than one header due to redirects.
  $content = (string)array_pop($stuff); // last one is the content
  $headers = (string)array_pop($stuff); // next-to-last-one is the headers

  $rawJSON = $content;
  $Status .= "<!-- rawJSON size is ".strlen($rawJSON). " bytes -->\n";

  $rawJSON = OWM_prepareJSON($rawJSON);
  $JSON = json_decode($rawJSON,true); // get as associative array
  $Status .= OWM_decode_JSON_error();
  if($doDebug) {$Status .= "<!-- JSON\n".print_r($JSON,true)." -->\n";}
 
if(isset($JSON['daily'][0]['dt'])) { // got good JSON .. process it
   $UnSupported = false;

   $OWMforecastcity = $FCSTlocation;
	 
   if($doIconv) {$OWMforecastcity = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$OWMforecastcity);}
   if($doDebug) {
     $Status .= "<!-- OWMforecastcity='$OWMforecastcity' -->\n";
   }
   //$OWMtitle = langtransstr("Forecast");
	 $OWMtitle = $tranTab['OpenWeatherMap Forecast for:'];
   if($doIconv) {$OWMtitle = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$OWMtitle);}
   if($doDebug) {
     $Status .= "<!-- OWMtitle='$OWMtitle' -->\n";
   }

/*
  "daily": [{
      "dt": 1675195200,
      "sunrise": 1675177924,
      "sunset": 1675215051,
      "moonrise": 1675199160,
      "moonset": 1675165080,
      "moon_phase": 0.35,
      "temp": {
        "day": 12.89,
        "min": 2.92,
        "max": 14.35,
        "night": 5.47,
        "eve": 10.3,
        "morn": 3.07
      },
      "feels_like": {
        "day": 10.91,
        "night": 5.47,
        "eve": 8.3,
        "morn": 3.07
      },
      "pressure": 1021,
      "humidity": 26,
      "dew_point": -5.45,
      "wind_speed": 1.68,
      "wind_deg": 12,
      "wind_gust": 1.82,
      "weather": [{
          "id": 800,
          "main": "Clear",
          "description": "clear sky",
          "icon": "01d"
        }
      ],
      "clouds": 8,
      "pop": 0,
      "uvi": 2.85
    }, {
*/
  if(isset($JSON['timezone'])) {
		date_default_timezone_set($JSON['timezone']);
		$Status .= "<!-- using '".$JSON['timezone']."' for timezone -->\n";
	}
	if(isset($JSON['daily'][0]['dt'])) {
		$OWMupdated = $tranTab['Updated:'];
		if($doIconv) { 
		  $OWMupdated = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$OWMupdated). ' '; 
		}
		if(isset($JSON['hourly'][0]['dt'])) {
		  $OWMupdated .= date($timeFormat,$JSON['hourly'][0]['dt']);
		} else {
		  $OWMupdated .= date($timeFormat,$JSON['daily'][0]['dt']);
		}
	} else {
		$OWMupdated = '';
	}
	
	if($doDebug) {
		$Status .= "\n<!-- JSON daily count=" . count( $JSON['daily']) . "-->\n";
	}
	$windUnit = $Units['W'];
	$Status .= "<!-- wind unit for '$showUnitsAs' set to '$windUnit' -->\n";
	if(isset($tranTab[$windUnit])) {
		$windUnit = $tranTab[$windUnit];
		$Status .= "<!-- wind unit translation for '$showUnitsAs' set to '$windUnit' -->\n";
	}

  $n = 0;
  foreach ($JSON['daily'] as $i => $FCpart) {
#   process each daily entry

		list($tDay,$tTime) = explode(" ",date('l H:i:s',$FCpart['dt']));
		if ($doDebug) {
				$Status .= "<!-- period $n ='$tDay $tTime' -->\n";
		}
		$OWMforecastdayname[$n] = $tDay;	
		if(isset($tranTab[$tDay])) {
			$OWMforecastday[$n] = $tranTab[$tDay];
		} else {
			$OWMforecastday[$n] = $tDay;
		}
    if($doIconv) {
		  $OWMforecastday[$n] = iconv("UTF-8",$charsetOutput.'//IGNORE',$OWMforecastday[$n]);
	  }
		$OWMforecasttitles[$n] = $OWMforecastday[$n];
		if ($doDebug) {
				$Status .= "<!-- OWMforecastday[$n]='" . $OWMforecastday[$n] . "' -->\n";
		}	
		$OWMforecastcloudcover[$n] = $FCpart['clouds'];

#  extract the temperatures

	  $OWMforecasttemp[$n] = "<span style=\"color: #ff0000;\">".OWM_round($FCpart['temp']['max'],0)."&deg;$showTempsAs</span>";
	  $OWMforecasttemp[$n] .= "<br/><span style=\"color: #0000ff;\">".OWM_round($FCpart['temp']['min'],0)."&deg;$showTempsAs</span>";

#  extract the icon to use
	  $OWMforecasticon[$n] = $FCpart['weather'][0]['icon'];
	if ($doDebug) {
      $Status .= "<!-- OWMforecasticon[$n]='" . $OWMforecasticon[$n] . "' -->\n";
	}	
	
	  $OWMforecastcode[$n] = $FCpart['weather'][0]['id'];
	if ($doDebug) {
      $Status .= "<!-- OWMforecastcode[$n]='" . $OWMforecastcode[$n] . "' -->\n";
	}		
		

	if(isset($FCpart['pop'])) {
	  $OWMforecastpop[$n] = round($FCpart['pop']*100,-1);
	} else {
		$OWMforecastpop[$n] = 0;
	}
	if ($doDebug) {
      $Status .= "<!-- OWMforecastpop[$n]='" . $OWMforecastpop[$n] . "' from pop='".$FCpart['pop']."'-->\n";
	}
	
	if(isset($FCpart['rain'])) {
		$OWMforecastpreciptype[$n] = 'rain,';
	} else {
		$OWMforecastpreciptype[$n] = '';
	}

	if(isset($FCpart['snow'])) {
		$OWMforecastpreciptype[$n] .= 'snow,';
	}


	$OWMforecasttext[$n] =  // replace problematic characters in forecast text
	   str_replace(
		 array('<',   '>',  'â€“','cm.','in.','.)'),
		 array('&lt;','&gt;','-', 'cm', 'in',')'),
	   trim($FCpart['weather'][0]['description'])). '. ';

# Add info to the forecast text
	if($OWMforecastpop[$n] > 0) {
		$tstr = '';
		if(!empty($OWMforecastpreciptype[$n])) {
			$t = explode(',',$OWMforecastpreciptype[$n].',');
			foreach ($t as $k => $ptype) {
				if(!empty($ptype)) {
				  if($ptype == 'rain') {$useUnit = $Units['R'];} else {$useUnit = $Units['S'];}
					$tstr .= $tranTab[$ptype].' '.OWM_rain_convert($FCpart[$ptype],$useUnit).$useUnit.',';
				}
			}
		}
		if(strlen($tstr)>0) {
			$tstr = ' ('.substr($tstr,0,strlen($tstr)-1) .').';
		} else {
			$tstr = '.';
		}
		$OWMforecasttext[$n] .= " ".
		   $tranTab['Chance of precipitation']." ".$OWMforecastpop[$n]."%$tstr";
	}

  $OWMforecasttext[$n] .= " ".$tranTab['High:']." ".OWM_round($FCpart['temp']['max'],0)."&deg;$showTempsAs. ";

  $OWMforecasttext[$n] .= " ".$tranTab['Low:']." ".OWM_round($FCpart['temp']['min'],0)."&deg;$showTempsAs. ";

	$tWdir = OWM_WindDir(round($FCpart['wind_deg'],0));
  $OWMforecasttext[$n] .= " ".$tranTab['Wind']." ".OWM_WindDirTrans($tWdir);
  $OWMforecasttext[$n] .= " ".
	     OWM_wind_convert($FCpart['wind_speed'],$Units['W'])."-&gt;".OWM_wind_convert($FCpart['wind_gust'],$Units['W']) .
	     " $windUnit.";

	if(isset($FCpart['uvi']) and $FCpart['uvi'] > 1) {
    $OWMforecasttext[$n] .= " ".$tranTab['UV index']." ".round($FCpart['uvi'],0).".";
	}

  if($doIconv) {
		$OWMforecasttext[$n] = iconv("UTF-8",$charsetOutput.'//IGNORE',$OWMforecasttext[$n]);
	}

	if ($doDebug) {
      $Status .= "<!-- OWMforecasttext[$n]='" . $OWMforecasttext[$n] . "' -->\n";
	}

	$OWMforecastcond[$n] = $FCpart['weather'][0]['description'];
  if($doIconv) {
		$OWMforecastcond[$n] = iconv("UTF-8",$charsetOutput.'//IGNORE',$OWMforecastcond[$n]);
	}
	if ($doDebug) {
      $Status .= "<!-- forecastcond[$n]='" . $OWMforecastcond[$n] . "' -->\n";
	}

	$OWMforecasticons[$n] = $OWMforecastday[$n] . "<br/>" .
	     OWM_img_replace(
			   $OWMforecasticon[$n],
				 $FCpart['weather'][0]['description'],
				 $OWMforecastpop[$n],
				 $OWMforecastcloudcover[$n],
				 $OWMforecastcode[$n]) . 
				  "<br/>" .
		 $OWMforecastcond[$n];
	$n++;
  } // end of process text forecasts

  // process alerts if any are available 
	$OWMforecastwarnings = '';
  if (isset($JSON['alerts']) and is_array($JSON['alerts']) and count($JSON['alerts']) > 0) {
    $Status.= "<!-- preparing " . count($JSON['alerts']) . " warning links -->\n";
    foreach($JSON['alerts'] as $i => $ALERT) {
			$expireUTC = $ALERT['end'];
      $expires = date('Y-m-d H:i T',$ALERT['end']);
      $Status.= "<!-- alert expires $expires (" . $ALERT['end'] . ") -->\n";
			$regions = '';
			if(isset($ALERT['regions']) and is_array($ALERT['regions'])) {
				foreach ($ALERT['regions'] as $i => $reg) {
					$regions .= $reg . ', ';
				}
				$regions = substr($regions,0,strlen($regions)-2);
			}
					
      if (time() < $expireUTC) {
        $OWMforecastwarnings .= '<a href="#"' . ' title="' . $ALERT['event'] . " expires $expires\n$regions\n---\n" . $ALERT['description'] . '">' . '<strong><span style="color: red">' . $ALERT['event'] . "</span></strong></a><br/>\n";
      }
      else {
        #$Status.= "<!-- alert " . $ALERT['title'] . " " . " expired - " . $ALERT['expires'] . " -->\n";
      }
    }
  }
  else {
    $Status.= "<!-- no current hazard alerts found-->\n";
  }

// make the Current conditions table from $currently array
$currently = $JSON['current'];
/*
  "current": {
    "dt": 1675204434,
    "sunrise": 1675177924,
    "sunset": 1675215051,
    "temp": 14.35,
    "feels_like": 12.44,
    "pressure": 1020,
    "humidity": 23,
    "dew_point": -5.77,
    "uvi": 1.1,
    "clouds": 20,
    "visibility": 10000,
    "wind_speed": 2.57,
    "wind_deg": 300,
    "weather": [{
        "id": 801,
        "main": "Clouds",
        "description": "few clouds",
        "icon": "02d"
      }
    ]
  },
*/
$nCols = 3; // number of columns in the conditions table
	
if (isset($currently['dt']) ) { // only generate if we have the data
	if (isset($currently['weather'][0]['icon']) and ! $currently['weather'][0]['icon'] ) { $nCols = 2; };
	
	
	$OWMcurrentConditions = '<table class="OWMforecast" cellpadding="3" cellspacing="3" style="border: 1px solid #909090;">' . "\n";
	
	$OWMcurrentConditions .= '
  <tr><td colspan="' . $nCols . '" align="center" '.$RTLopt.'><small>' . 
  $tranTab['Currently'].': '. date($timeFormat,$currently['dt']) . "<br/>\n";
#	$t = $tranTab['Weather conditions at 999 from forecast point.'];
#	$t = str_replace('999',round($JSON['flags']['nearest-station'],1).' '.$Units['D'],$t);
	$OWMcurrentConditions .= 
  '</small></td></tr>' . "\n<tr$RTLopt>\n";
  if (isset($currently['weather'][0]['icon'])) {
    $OWMcurrentConditions .= '
    <td align="center" valign="middle">' . 
       OWM_img_replace(
			 $currently['weather'][0]['icon'],
			 $currently['weather'][0]['description'],
			 0,
			 $currently['clouds'],
			 $currently['weather'][0]['id']) . "<br/>\n" .
			 $currently['weather'][0]['description'];
	$OWMcurrentConditions .= '    </td>
';  
    } // end of icon
    $OWMcurrentConditions .= "
    <td valign=\"middle\">\n";

	if (isset($currently['temp'])) {
	  $OWMcurrentConditions .= $tranTab['Temperature'].": <b>".
	  OWM_round($currently['temp'],0) . $Units['T'] . "</b><br/>\n";
	}
	if (isset($currently['wind_chill'])) {
	  $OWMcurrentConditions .= $tranTab['Wind chill'].": <b>".
	  OWM_round($currently['wind_chill'],0) . $Units['T']. "</b><br/>\n";
	}
	if (isset($currently['heat_index'])) {
	  $OWMcurrentConditions .= $tranTab['Heat index'].": <b>" .
	  OWM_round($currently['heat_index']) . $Units['T']. "</b><br/>\n";
	}
	if (isset($currently['wind_speed'])) {
		$tWdir = OWM_WindDir(round($currently['wind_deg'],0));
		$OWMcurrentConditions .= $tranTab['Wind'].": <b>".OWM_WindDirTrans($tWdir);
		$OWMcurrentConditions .= " ".OWM_wind_convert($currently['wind_speed'],$Units['W']);
		if(isset($currently['wind_gust'])) {
			 $OWMcurrentConditions .= "-&gt;".OWM_wind_convert($currently['wind_gust'],$Units['W']);
		}
		$OWMcurrentConditions .=  " $windUnit.</b><br/>\n";
	}
	if (isset($currently['humidity'])) {
	  $OWMcurrentConditions .= $tranTab['Humidity'].": <b>".
	  round($currently['humidity'],1) . "%</b><br/>\n";
	}
	if (isset($currently['dew_point'])) {
	  $OWMcurrentConditions .= $tranTab['Dew Point'].": <b>".
	  OWM_round($currently['dew_point'],0) . $Units['T'] . "</b><br/>\n";
	}
	
	$OWMcurrentConditions .= $tranTab['Barometer'].": <b>".
	OWM_conv_baro($currently['pressure']) . " " . $Units['P'] . "</b><br/>\n";
	
	if (isset($currently['visibility'])) {
	  $OWMcurrentConditions .= $tranTab['Visibility'].": <b>".
	  round($currently['visibility'] / 1000.0,1) . " " . $Units['D']. "</b>\n" ;
	}

	if (isset($currently['uvi'])) {
	  $OWMcurrentConditions .= '<br/>'.$tranTab['UV index'].": <b>".
	  round($currently['uvi'],0) .  "</b>\n" ;
	}
	
	$OWMcurrentConditions .= '	   </td>
';
	$OWMcurrentConditions .= '    <td valign="middle">
';
	if(isset($JSON['daily'][0]['sunrise']) and 
	   isset($JSON['daily'][0]['sunset']) ) {
	  $OWMcurrentConditions .= 
	  $tranTab['Sunrise'].': <b>'. 
		   date('H:i',$JSON['daily'][0]['sunrise']) . 
			 "</b><br/>\n" .
		$tranTab['Sunset'].': <b>'.
	     date('H:i',$JSON['daily'][0]['sunset']) . 
			 "</b><br/>\n" ;
	}
	$OWMcurrentConditions .= '
	</td>
  </tr>
';
  if(isset($JSON['daily']['summary'])) {
		if($doRTL) {
  $OWMcurrentConditions .= '
	<tr><td colspan="' . $nCols . '" align="center" style="width: 350px;direction: rtl;"><small>' .
	$JSON['daily']['summary'] . 
	'</small></td>
	</tr>
'; } else {
  $OWMcurrentConditions .= '
	<tr><td colspan="' . $nCols . '" align="center" style="width: 350px;"><small>' .
	$JSON['daily']['summary'] . 
	'</small></td>
	</tr>
';	
}
	}
  $OWMcurrentConditions .= '
</table>
';
  if($doIconv) {
		$OWMcurrentConditions = 
		  iconv('UTF-8',$charsetOutput.'//TRANSLIT',$OWMcurrentConditions);
	}
		
} // end of if isset($currently['cityobserved'])
// end of current conditions mods

if(isset($JSON['hourly'][0]['dt'])) { // process Hourly forecast data
/*
	"hourly": {
		"summary": "Mostly cloudy throughout the day.",
		"icon": "partly-cloudy-night",
		"data": [{
				"time": 1548018000,
				"summary": "Mostly Cloudy",
				"icon": "partly-cloudy-day",
				"precipIntensity": 0.1422,
				"precipProbability": 0.29,
				"precipType": "rain",
				"temperature": 14.91,
				"apparentTemperature": 14.91,
				"dewPoint": 11.49,
				"humidity": 0.8,
				"pressure": 1017.89,
				"windSpeed": 10.8,
				"windGust": 24.54,
				"windBearing": 226,
				"cloudCover": 0.88,
				"uvIndex": 2,
				"visibility": 14.11,
				"ozone": 289.95
			}, {
*/
  foreach($JSON['hourly'] as $i => $FCpart) {
    $OWMforecasticonHR[$i] = OWM_gen_hourforecast($FCpart);
		
		if($doIconv) { 
		  $OWMforecasticonHR[$i]['icon'] = 
			  iconv($charsetInput,$charsetOutput.'//TRANSLIT',$OWMforecasticonHR[$i]['icon']). ' '; 
		  $OWMforecasticonHR[$i]['temp'] = 
			  iconv($charsetInput,$charsetOutput.'//TRANSLIT',$OWMforecasticonHR[$i]['temp']). ' '; 
		  $OWMforecasticonHR[$i]['wind'] = 
			  iconv($charsetInput,$charsetOutput.'//TRANSLIT',$OWMforecasticonHR[$i]['wind']). ' '; 
		  $OWMforecasticonHR[$i]['precip'] = 
			  iconv($charsetInput,$charsetOutput.'//TRANSLIT',$OWMforecasticonHR[$i]['precip']). ' '; 
		}
		if($doDebug) {
		  $Status .= "<!-- hour $i ".var_export($OWMforecasticonHR[$i],true)." -->\n";
		}
	} // end each hourly forecast parsing
} // end process hourly forecast data
  
} // end got good JSON decode/process

// end process JSON style --------------------------------------------------------------------

// All finished with parsing, now prepare to print

  $wdth = intval(100/count($OWMforecasticons));
  $ndays = intval(count($OWMforecasticon)/2);
  
  $doNumIcons = $maxIcons;
  if(count($OWMforecasticons) < $maxIcons) { $doNumIcons = count($OWMforecasticons); }

  $IncludeMode = false;
  $PrintMode = true;

  if (isset($doPrintOWM) && ! $doPrintOWM ) {
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
if (isset($doIncludeOWM)) {
  $IncludeMode = $doIncludeOWM;
}

$printHeading = true;
$printIcons = true;
$printText = true;

if (isset($doPrintHeadingOWM)) {
  $printHeading = $doPrintHeadingOWM;
}
if (isset($_REQUEST['heading']) ) {
  $printHeading = substr(strtolower($_REQUEST['heading']),0,1) == 'y';
}

if (isset($doPrintIconsOWM)) {
  $printIcons = $doPrintIconsOWM;
}
if (isset($_REQUEST['icons']) ) {
  $printIcons = substr(strtolower($_REQUEST['icons']),0,1) == 'y';
}
if (isset($doPrintTextOWM)) {
  $printText = $doPrintTextOWM;
}
if (isset($_REQUEST['text']) ) {
  $printText = substr(strtolower($_REQUEST['text']),0,1) == 'y';
}


if (! $IncludeMode and $PrintMode) { ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title><?php echo $OWMtitle . ' - ' . $OWMforecastcity; ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $charsetOutput; ?>" />
<style type="text/css">
/*--------------------------------------------------
  tabbertab 
  --------------------------------------------------*/
/* $Id: example.css,v 1.5 2006/03/27 02:44:36 pat Exp $ */

/*--------------------------------------------------
  REQUIRED to hide the non-active tab content.
  But do not hide them in the print stylesheet!
  --------------------------------------------------*/
.tabberlive .tabbertabhide {
 display:none;
}

/*--------------------------------------------------
  .tabber = before the tabber interface is set up
  .tabberlive = after the tabber interface is set up
  --------------------------------------------------*/
.tabber {
}
.tabberlive {
 margin-top:1em;
}

/*--------------------------------------------------
  ul.tabbernav = the tab navigation list
  li.tabberactive = the active tab
  --------------------------------------------------*/
ul.tabbernav
{
 margin:0 0 3px 0;
 padding: 0 3px ;
 border-bottom: 0px solid #778;
 font: bold 12px Verdana, sans-serif;
}

ul.tabbernav li
{
 list-style: none;
 margin: 0;
 min-height:40px;
 display: inline;
}

ul.tabbernav li a
{
 padding: 3px 0.5em;
	min-height: 40px;
	border-top-left-radius: 5px;
	border-top-right-radius: 5px;
 margin-left: 3px;
 border: 1px solid #778;
 border-bottom: none;
 background: #DDE  !important;
 text-decoration: none !important;
}

ul.tabbernav li a:link { color: #448  !important;}
ul.tabbernav li a:visited { color: #667 !important; }

ul.tabbernav li a:hover
{
 color: #000;
 background: #AAE !important;
 border-color: #227;
}

ul.tabbernav li.tabberactive a
{
 background-color: #fff !important;
 border-bottom: none;
}

ul.tabbernav li.tabberactive a:hover
{
 color: #000;
 background: white !important;
 border-bottom: 1px solid white;
}

/*--------------------------------------------------
  .tabbertab = the tab content
  Add style only after the tabber interface is set up (.tabberlive)
  --------------------------------------------------*/
.tabberlive .tabbertab {
 padding:5px;
 border:0px solid #aaa;
 border-top:0;
	overflow:auto;

}

/* If desired, hide the heading since a heading is provided by the tab */
.tabberlive .tabbertab h2 {
 display:none;
}
.tabberlive .tabbertab h3 {
 display:none;
}
</style>	
</head>
<body style="font-family:Verdana, Arial, Helvetica, sans-serif; font-size:12px; background-color:#FFFFFF">

<?php
} // end printmode and not includemode
print $Status;
// if the forecast text is blank, prompt the visitor to force an update
setup_tabber(); // print the tabber JavaScript so it is available

if($UnSupported) {

  print <<< EONAG
<h1>Sorry.. this <a href="https://openweathermap.org/forecast/$OWM_LATLONG/{$showUnitsAs}12/$OWMLANG">forecast</a> can not be processed at this time.</h1>


EONAG
;
}

if (strlen($OWMforecasttext[0])<2 and $PrintMode and ! $UnSupported ) {

  echo '<br/><br/>'.langtransstr('Forecast blank?').' <a href="' . $PHP_SELF . '?force=1">' .
	 langtransstr('Force Update').'</a><br/><br/>';

} 
if ($PrintMode and ($printHeading or $printIcons)) { 

?>
  <table width="<?php print $maxWidth; ?>" style="border: none;" class="OWMforecast">
  <?php echo $ddMenu ?>
<?php
  if ($showConditions) {
	  print "<tr><td align=\"center\">\n";
    print $OWMcurrentConditions;
	  print "</td></tr>\n";
  }

?>
    <?php if($printHeading) { ?>
    <tr align="center" style="background-color: #FFFFFF;<?php 
		if($doRTL) { echo 'direction: rtl;'; } ?>">
      <td><b><?php echo $OWMtitle; ?></b> <span style="color: green;">
	   <?php echo $OWMforecastcity; ?></span>
     <?php if(strlen($OWMupdated) > 0) {
			 echo "<br/>$OWMupdated\n";
		 }
		 ?>
      </td>
    </tr>
  </table>
  <p>&nbsp;</p>
<div class="tabber" style="width: 99%; margin: 0 auto;"><!-- Day Forecast tab begin -->
  <div class="tabbertab" style="padding: 0;">
    <h2><?php 
$t = $tranTab['Daily Forecast'];
if($doIconv) { 
	$t = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$t). ' '; 
}
echo $t; ?></h2>
    <div style="width: 99%;">

  <table width="<?php print $maxWidth; ?>" style="border: none;" class="OWMforecast">
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
			$iTitleLen += strlen(strip_tags($OWMforecasttitles[$i]));
			$iCondLen += strlen(strip_tags($OWMforecastcond[$i]));
			$iTempLen += strlen(strip_tags($OWMforecasttemp[$i]));  
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
	    print "<td style=\"width: $wdth%; text-align: center;\"><span style=\"font-size: 8pt;\">$OWMforecasttitles[$ni]</span><!-- $ni '".$OWMforecastdayname[$ni]."' --></td>\n";
		
	  }
	
print "          </tr>\n";	
    print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
	
	  for ($i=$startIcon;$i<$finIcon;$i++) {
		$ni = $doRTL?$numIconsInFoldedRow-1-$i+$startIcon+$k:$i;  
	    print "<td style=\"width: $wdth%;\">" . 
			OWM_img_replace($OWMforecasticon[$ni],
			  $OWMforecastcond[$ni],
				$OWMforecastpop[$ni],
				$OWMforecastcloudcover[$ni],
				$OWMforecastcode[$ni]) . "<!-- $ni --></td>\n";
	  }
	?>
          </tr>	
	      <tr valign ="top" align="center">
	<?php
	  for ($i=$startIcon;$i<$finIcon;$i++) {
		$ni = $doRTL?$numIconsInFoldedRow-1-$i+$startIcon+$k:$i;  

	    print "<td style=\"width: $wdth%; text-align: center;\"><span style=\"font-size: 8pt;\">$OWMforecastcond[$ni]</span><!-- $ni '".$OWMforecastdayname[$ni]."' --></td>\n";
	  }
	
      print "	      </tr>\n";	
      print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
	  
	  for ($i=$startIcon;$i<$finIcon;$i++) {
		$ni = $doRTL?$numIconsInFoldedRow-1-$i+$startIcon+$k:$i;  
	    print "<td style=\"width: $wdth%; text-align: center;\">$OWMforecasttemp[$ni]</td>\n";
	  }
	  ?>
          </tr>
	<?php if(! $iconDir) { // print a PoP row since they aren't using icons 
    print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
	
	  for ($i=$startIcon;$i<$finIcon;$i++) {
		$ni = $doRTL?$numIconsInFoldedRow-1-$i+$startIcon+$k:$i;  
	    print "<td style=\"width: $wdth%; text-align: center;\">";
	    if($OWMforecastpop[$ni] > 0) {
  		  print "<span style=\"font-size: 8pt; color: #009900;\">PoP: $OWMforecastpop[$ni]%</span>";
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
<?php
  if ($OWMforecastwarnings <> '') {
		if($doIconv) { 
		  $OWMforecastwarnings = 
			  iconv($charsetInput,$charsetOutput.'//IGNORE',$OWMforecastwarnings); 
		}
		$tW = 'width: 640px;';
		if($doRTL) {$tW .= 'direction: rtl;';}
    print "<p class=\"OWMforecast\"$tW>$OWMforecastwarnings</p>\n";
  }
?>
<br/>
<table style="border: 0" width="<?php print $maxWidth; ?>" class="OWMforecast">
	<?php
	  for ($i=0;$i<count($OWMforecasttitles);$i++) {
        print "<tr valign =\"top\"$RTLopt>\n";
		if(!$doRTL) { // normal Left-to-right
	      print "<td style=\"width: 20%;\"><b>$OWMforecasttitles[$i]</b><br />&nbsp;<br /></td>\n";
	      print "<td style=\"width: 80%;\">$OWMforecasttext[$i]</td>\n";
		} else { // print RTL format
	      print "<td style=\"width: 80%; text-align: right;\">$OWMforecasttext[$i]</td>\n";
	      print "<td style=\"width: 20%; text-align: right;\"><b>$OWMforecasttitles[$i]</b><br />&nbsp;<br /></td>\n";
		}
		print "</tr>\n";
	  }
	?>
   </table>
<?php } // end print text ?>
<?php if ($PrintMode) { ?>
   </div>
 </div> <!-- end first tab --> 

  <div class="tabbertab" style="padding: 0;"><!-- begin second tab -->
    <h2><?php $t = $tranTab['Hourly Forecast'];
if($doIconv) { 
	$t = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$t). ' '; 
}
echo $t; ?></h2>
    <div style="width: 99%;">
    <table style="border: 0" width="<?php print $maxWidth; ?>" class="OWMforecast">
	 <?php 
     for ($row=0;$row<4;$row++) {
       print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
			 for ($n=$row*8;$n<$row*8+8;$n++) {
		     $ni = $doRTL?($row+1)*8-$n-1+($row*8):$n;  
				 if(isset($OWMforecasticonHR[$ni]['icon'])) {
					 print '<td>'.$OWMforecasticonHR[$ni]['icon']."<!-- n=$n ni=$ni --></td>";;
				 } else {
					 print "<td>&nbsp;</td>";
				 }
			 }
		   print "</tr>\n";
       print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
			 for ($n=$row*8;$n<$row*8+8;$n++) {
		     $ni = $doRTL?($row+1)*8-$n-1+($row*8):$n;  
				 if(isset($OWMforecasticonHR[$ni]['temp'])) {
					 print '<td>'.$OWMforecasticonHR[$ni]['temp']."</td>";;
				 } else {
					 print "<td>&nbsp;</td>";
				 }
			 }
		   print "</tr>\n";
       print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
			 for ($n=$row*8;$n<$row*8+8;$n++) {
		     $ni = $doRTL?($row+1)*8-$n-1+($row*8):$n;  
				 if(isset($OWMforecasticonHR[$ni]['UV'])) {
					 print '<td>'.$OWMforecasticonHR[$ni]['UV']."</td>";;
				 } else {
					 print "<td>&nbsp;</td>";
				 }
			 }
		   print "</tr>\n";
       print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
			 for ($n=$row*8;$n<$row*8+8;$n++) {
		     $ni = $doRTL?($row+1)*8-$n-1+($row*8):$n;  
				 if(isset($OWMforecasticonHR[$ni]['wind'])) {
					 print '<td>'.$OWMforecasticonHR[$ni]['wind']."</td>";;
				 } else {
					 print "<td>&nbsp;</td>";
				 }
			 }
		   print "</tr>\n";
       print "	      <tr valign=\"top\" align=\"center\"$RTLopt>\n";
			 for ($n=$row*8;$n<$row*8+8;$n++) {
		     $ni = $doRTL?($row+1)*8-$n-1+($row*8):$n;  
				 if(isset($OWMforecasticonHR[$ni]['precip'])) {
					 print '<td>'.$OWMforecasticonHR[$ni]['precip']."</td>";;
				 } else {
					 print "<td>&nbsp;</td>";
				 }
			 }
		   print "</tr>\n";
			 print "<tr><td colspan=\"8\"><hr/></td></tr>\n";
		 } // end rows
?>
    </table>
    </div>
</div>
</div>
<p>&nbsp;</p>
<p><?php echo $OWMforecastcity.' '; print langtransstr('forecast by');?> <a href="https://openweathermap.org/">openweathermap.org</a>. 
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
function OWM_fetchUrlWithoutHanging($url,$useFopen) {
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
    'Mozilla/5.0 (OWM-forecast.php - saratoga-weather.org)');
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
				"User-agent: Mozilla/5.0 (OWM-forecast.php - saratoga-weather.org)\r\n" .
				"Accept: text/plain,text/html\r\n"
	  ),
	  'https'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (OWM-forecast.php - saratoga-weather.org)\r\n" .
				"Accept: text/plain,text/html\r\n"
	  )
	);
	
   $STRcontext = stream_context_create($STRopts);

   $T_start = OWM_fetch_microtime();
   $xml = file_get_contents($url,false,$STRcontext);
   $T_close = OWM_fetch_microtime();
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

}    // end OWM_fetch_URL

// ------------------------------------------------------------------

function OWM_fetch_microtime()
{
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + (float)$sec);
}

// -------------------------------------------------------------------------------------------
 
function OWM_prepareJSON($input) {
	global $Status;
   
   //This will convert ASCII/ISO-8859-1 to UTF-8.
   //Be careful with the third parameter (encoding detect list), because
   //if set wrong, some input encodings will get garbled (including UTF-8!)

   list($isUTF8,$offset,$msg) = OWM_check_utf8($input);
   
   if(!$isUTF8) {
	   $Status .= "<!-- OWM_prepareJSON: Oops, non UTF-8 char detected at $offset. $msg. Doing utf8_encode() -->\n";
	   $str = utf8_encode($input);
       list($isUTF8,$offset,$msg) = OWM_check_utf8($str);
	   $Status .= "<!-- OWM_prepareJSON: after utf8_encode, i=$offset. $msg. -->\n";   
   } else {
	   $Status .= "<!-- OWM_prepareJSON: $msg. -->\n";
	   $str = $input;
   }
  
   //Remove UTF-8 BOM if present, json_decode() does not like it.
   if(substr($str, 0, 3) == pack("CCC", 0xEF, 0xBB, 0xBF)) $str = substr($str, 3);
   
   return $str;
}

// -------------------------------------------------------------------------------------------

function OWM_check_utf8($str) {
// check all the characters for UTF-8 compliance so json_decode() won't choke
// Sometimes, an ISO international character slips in the OWM text string.	  
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
 
function OWM_decode_JSON_error() {
	
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

function OWM_fixup_text($text) {
	global $Status;
	// attempt to convert Imperial forecast temperatures to Metric in the text forecast
	
	if(preg_match_all('!([-|\d]+)([Â Âº]*F)!s',$text,$m)) {
		//$newtext = str_replace('ºF','F',$text);
		$newtext = $text;
		foreach ($m[1] as $i => $tF) {
			$tI = $m[2][$i];
			$tC = (float)(($tF - 32) / 1.8 );
			$tC = round($tC,0);
//			$newtext = str_replace("{$tF}F","{$tC}C({$tF}F)",$newtext);
			$newtext = str_replace("{$tF}{$tI}","{$tC}C",$newtext);
			$Status .= "<!-- replaced {$tF}F with {$tC}C in text forecast. -->\n";
		}
		return($newtext);
	} else {
		return($text);  // no changes
	}
	
	
}

// -------------------------------------------------------------------------------------------

function OWM_loadLangDefaults () {
	global $OWMlanguages, $OWMlangCharsets;
/*

    af Afrikaans
    al Albanian
    ar Arabic
    az Azerbaijani
    bg Bulgarian
    ca Catalan
    cz Czech
    da Danish
    de German
    el Greek
    en English
    eu Basque
    fa Persian (Farsi)
    fi Finnish
    fr French
    gl Galician
    he Hebrew
    hi Hindi
    hr Croatian
    hu Hungarian
    id Indonesian
    it Italian
    ja Japanese
    kr Korean
    la Latvian
    lt Lithuanian
    mk Macedonian
    no Norwegian
    nl Dutch
    pl Polish
    pt Portuguese
    pt_br Português Brasil
    ro Romanian
    ru Russian
    sv, se	Swedish
    sk Slovak
    sl Slovenian
    sp, es	Spanish
    sr Serbian
    th Thai
    tr Turkish
    ua, uk Ukrainian
    vi Vietnamese
    zh_cn Chinese Simplified
    zh_tw Chinese Traditional
    zu Zulu

*/
 
 $OWMlanguages = array(  // our template language codes v.s. lang:LL codes for JSON
	'af' => 'af',
	'bg' => 'bg',
	'cs' => 'cz',
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
	'no' => 'no',
	'pl' => 'pl',
	'pt' => 'pt',
	'ro' => 'ro',
	'es' => 'es',
	'se' => 'sv',
	'si' => 'sl',
	'sk' => 'sk',
	'sr' => 'sr',
  );

  $OWMlangCharsets = array(
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

// -------------------------------------------------------------------------------------------

function OWM_loadTranslate ($lang) {
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
if(!file_exists("OWM-forecast-lang.php")) {
	print "<p>Warning: OWM-forecast-lang.php translation file was not found.  It is required";
	print " to be in the same directory as OWM-forecast.php.</p>\n";
	exit;
	}
include_once("OWM-forecast-lang.php");

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
		'OpenWeatherMap Forecast for:' => 'OpenWeatherMap Forecast for:',
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

function OWM_WindDir ($degrees) {
 //  convert degrees into wind direction abbreviation   
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

} // end function OWM_WindDir

// ------------------------------------------------------------------

function OWM_WindDirTrans($inwdir) {
	global $tranTab, $Status;
	$wdirs = $tranTab['NESW'];  // default directions
	$tstr = $inwdir;
	$Status .= "<!-- OWM_WindDirTrans in=$inwdir using ";
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

function OWM_round($item,$dp) {
	$t = round($item,$dp);
	if ($t == '-0') {
		$t = 0;
	}
	return ($t);
}

// ------------------------------------------------------------------

function OWM_octets ($coverage) {
	global $Status;
	
	$octets = round($coverage*100 / 12.5,1);
	$Status .= "<!-- OWM_octets in=$coverage octets=$octets ";
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

function OWM_conv_baro($hPa) {
	# even 'us' imperial returns pressure in hPa so we need to convert
	global $showUnitsAs;
	
	if($showUnitsAs == 'imperial') {
		$t = (float)$hPa * 0.02952998751;
		return(sprintf("%01.2f",$t));
	} else {
		return( sprintf("%01.1f",$hPa) );
	}
}

// ------------------------------------------------------------------

function OWM_gen_hourforecast($FCpart) {
	global $doDebug,$Status,$showTempsAs,$tranTab,$windUnit,$Units,$showUnitsAs;
	/* $FCpart =
 "hourly": [
    {
      "dt": 1675206000,
      "temp": 13.77,
      "feels_like": 11.88,
      "pressure": 1020,
      "humidity": 26,
      "dew_point": -4.77,
      "uvi": 1.1,
      "clouds": 16,
      "visibility": 10000,
      "wind_speed": 1.68,
      "wind_deg": 12,
      "wind_gust": 1.82,
      "weather": [
        {
          "id": 801,
          "main": "Clouds",
          "description": "few clouds",
          "icon": "02d"
        }
      ],
      "pop": 0
    },
*/
  $OWMH = array();
	
  //$newIcon = '<td>';
  if($showUnitsAs == 'imperial') {
	  $t = explode(' ',date('g:ia n/j l',$FCpart['dt']));
	} else {
	  $t = explode(' ',date('H:i j/n l',$FCpart['dt']));
	}
	
	$newIcon = '<b>'.$t[0].'<br/>'.$tranTab[$t[2]]."</b><br/>\n";
	
  $cloudcover = $FCpart['clouds'];
	if(isset($FCpart['pop'])) {
	  $pop = round($FCpart['pop']*100,-1);
	} else {
		$pop = 0;
	}
	$temp = explode('.',$FCpart['weather'][0]['description'].'.'); // split as sentences (sort of).
	
	$condition = trim($temp[0]); // take first one as summary.

	$icon = $FCpart['weather'][0]['icon'];
	$code = $FCpart['weather'][0]['id'];

	$newIcon .= "<br/>" .
	     OWM_img_replace(
			   $icon,$condition,$pop,$cloudcover,$code) . 
				  "<br/>" .
		 $condition;
	$OWMH['icon'] = $newIcon;

	$OWMH['temp'] = '<b>'.OWM_round($FCpart['temp'],0)."</b>&deg;$showTempsAs";
	$OWMH['UV'] = 'UV: <b>'.$FCpart['uvi']."</b>";

	$tWdir = OWM_WindDir(round($FCpart['wind_deg'],0));
  $OWMH['wind'] = $tranTab['Wind']." <b>".OWM_WindDirTrans($tWdir);
  $OWMH['wind'] .= " ".
	     OWM_wind_convert($FCpart['wind_speed'],$Units['W'])."-&gt;".OWM_wind_convert($FCpart['wind_gust'],$Units['W']) .
	     "</b> $windUnit\n";

  $preciptype = '';
	if(isset($FCpart['rain']['1h'])) {
		$preciptype .= $tranTab['rain'] . ': '. OWM_rain_convert($FCpart['rain']['1h'],$Units['R']) . $Units['R'].',';
	}

	if(isset($FCpart['snow']['1h'])) {
		$preciptype .= $tranTab['snow'] . ': '. OWM_rain_convert($FCpart['snow']['1h'],$Units['S']) . $Units['S'].',';
	}

	$accum = '';
	if($pop > 0) {
		if(!empty($preciptype)) {
			$t = explode(',',$preciptype.',');

			foreach ($t as $k => $ptype) {
				if(!empty($ptype)) {
				$accum = ' <b>' . $ptype."</b>";
				}
			}

		}
	}
  $OWMH['precip'] = "$accum";
	//$newIcon .= "</td>\n";
	return($OWMH);
}

// ------------------------------------------------------------------

Function OWM_rain_convert($in,$unit) {
	// input is always in MM
	if(strpos($unit,'in') !== false) {
		return (sprintf("%01.2f",round($in/25.4,2)));
	} elseif(strpos($unit,'cm') !== false) {
		return(sprintf("%01.1f",round($in,1)/10.0));
	} else {
	  return (sprintf("%01.1f",round($in,1)));
  }
}

// ------------------------------------------------------------------

 function OWM_img_replace ( $OWMimage, $OWMcondtext,$OWMpop,$OWMcloudcover,$code) {
   global $NWSiconlist,$iconDir,$iconType,$Status;
 
   list($dayicon,$nighticon) = OWM_code_to_icon($code);
   $curicon = (strpos($OWMimage,'n') !== false)?$nighticon:$dayicon;
 
   if (!$curicon) { // no change.. use OWM icon
     return("<img src=\"{$iconDir}na.jpg\" width=\"55\" height=\"55\" " .
       "alt=\"$OWMcondtext\" title=\"$OWMcondtext\"/>"); 
   }
   if($iconType <> '.jpg') {
	   $curicon = preg_replace('|\.jpg|',$iconType,$curicon);
   }
   $Status .= "<!-- replace icon '$OWMimage' with ";
   if ($OWMpop > 0) {
	  $testicon = preg_replace('|'.$iconType.'|',$OWMpop.$iconType,$curicon);
		if (file_exists("$iconDir$testicon")) {
			$newicon = $testicon;
		} else {
			$newicon = $curicon;
		}
  } else {
		$newicon = $curicon;
  }
  $Status .= "'$newicon' pop=$OWMpop -->\n";

  return("<img src=\"$iconDir$newicon\" width=\"55\" height=\"55\" 
  alt=\"$OWMcondtext\" title=\"$OWMcondtext\"/>"); 
}

// -------------------------------------------------------------------------------------------
   
function OWM_wind_convert ($in,$unit) {
  global $showUnitsAs;
	if ($showUnitsAs == 'imperial') {
		return(round($in,0));
	}
		switch ($unit) {
			case '': 
			  return(round($in,0)); 
				break; 
			case 'm/s': 
			  return(round($in,1)); 
				break;
			case 'km/h': 
			  return(round($in*3.6,0)); 
				break;
			case 'mph': 
			  return(round($in*2.237,0)); 
				break;
			default: 
			  return(round($in,0));
	}
	return(round($in,1)); // for standard in m/s
}

// -------------------------------------------------------------------------------------------
   
function OWM_code_to_icon($code) {
	$Codes = array(
// ID 	Main 	Description 	Icon
 '200' => array('tsra.jpg','ntsra.jpg'),  // Thunderstorm 	thunderstorm with light rain 	11d
 '201' => array('tsra.jpg','ntsra.jpg'),  // Thunderstorm 	thunderstorm with rain 	11d
 '202' => array('tsra.jpg','ntsra.jpg'),  // Thunderstorm 	thunderstorm with heavy rain 	11d
 '210' => array('tsra.jpg','ntsra.jpg'),  // Thunderstorm 	light thunderstorm 	11d
 '211' => array('tsra.jpg','ntsra.jpg'),  // Thunderstorm 	thunderstorm 	11d
 '212' => array('tsra.jpg','ntsra.jpg'),  // Thunderstorm 	heavy thunderstorm 	11d
 '221' => array('tsra.jpg','ntsra.jpg'),  // Thunderstorm 	ragged thunderstorm 	11d
 '230' => array('tsra.jpg','ntsra.jpg'),  // Thunderstorm 	thunderstorm with light drizzle 	11d
 '231' => array('tsra.jpg','ntsra.jpg'),  // Thunderstorm 	thunderstorm with drizzle 	11d
 '232' => array('tsra.jpg','ntsra.jpg'),  // Thunderstorm 	thunderstorm with heavy drizzle 	11d
 '300' => array('ra.jpg','nra.jpg'),  // Drizzle 	light intensity drizzle 	09d
 '301' => array('ra.jpg','nra.jpg'),  // Drizzle 	drizzle 	09d
 '302' => array('ra.jpg','nra.jpg'),  // Drizzle 	heavy intensity drizzle 	09d
 '310' => array('ra.jpg','nra.jpg'),  // Drizzle 	light intensity drizzle rain 	09d
 '311' => array('ra.jpg','nra.jpg'),  // Drizzle 	drizzle rain 	09d
 '312' => array('ra.jpg','nra.jpg'),  // Drizzle 	heavy intensity drizzle rain 	09d
 '313' => array('ra.jpg','nra.jpg'),  // Drizzle 	shower rain and drizzle 	09d
 '314' => array('ra.jpg','nra.jpg'),  // Drizzle 	heavy shower rain and drizzle 	09d
 '321' => array('ra.jpg','nra.jpg'),  // Drizzle 	shower drizzle 	09d
 '500' => array('ra.jpg','nra.jpg'),  // Rain 	light rain 	10d
 '501' => array('ra.jpg','nra.jpg'),  // Rain 	moderate rain 	10d
 '502' => array('ra.jpg','nra.jpg'),  // Rain 	heavy intensity rain 	10d
 '503' => array('ra.jpg','nra.jpg'),  // Rain 	very heavy rain 	10d
 '504' => array('ra.jpg','nra.jpg'),  // Rain 	extreme rain 	10d
 '511' => array('ra.jpg','nra.jpg'),  // Rain 	freezing rain 	13d
 '520' => array('ra.jpg','nra.jpg'),  // Rain 	light intensity shower rain 	09d
 '521' => array('ra.jpg','nra.jpg'),  // Rain 	shower rain 	09d
 '522' => array('ra.jpg','nra.jpg'),  // Rain 	heavy intensity shower rain 	09d
 '531' => array('ra.jpg','nra.jpg'),  // Rain 	ragged shower rain 	09d
 '600' => array('sn.jpg','nsn.jpg'),  // Snow 	light snow 	13d
 '601' => array('sn.jpg','nsn.jpg'),  // Snow 	Snow 	13d
 '602' => array('sn.jpg','nsn.jpg'),  // Snow 	Heavy snow 	13d
 '611' => array('ip.jpg','nip.jpg'),  // Snow 	Sleet 	13d
 '612' => array('ip.jpg','nip.jpg'),  // Snow 	Light shower sleet 	13d
 '613' => array('ip.jpg','nip.jpg'),  // Snow 	Shower sleet 	13d
 '615' => array('rasn.jpg','nrasn.jpg'),  // Snow 	Light rain and snow 	13d
 '616' => array('rasn.jpg','nrasn.jpg'),  // Snow 	Rain and snow 	13d
 '620' => array('sn.jpg','nsn.jpg'),  // Snow 	Light shower snow 	13d
 '621' => array('sn.jpg','nsn.jpg'),  // Snow 	Shower snow 	13d
 '622' => array('sn.jpg','nsn.jpg'),  // Snow 	Heavy shower snow 	13d
 '701' => array('mist.jpg','nmist.jpg'),  // Mist 	mist 	50d
 '711' => array('fu.jpg','nfu.jpg'),  // Smoke 	Smoke 	50d
 '721' => array('hz.jpg','nhz.jpg'),  // Haze 	Haze 	50d
 '731' => array('du.jpg','ndu.jpg'),  // Dust 	sand/ dust whirls 	50d
 '741' => array('fg.jpg','nfg.jpg'),  // Fog 	fog 	50d
 '751' => array('du.jpg','ndu.jpg'),  // Sand 	sand 	50d
 '761' => array('du.jpg','ndu.jpg'),  // Dust 	dust 	50d
 '762' => array('du.jpg','ndu.jpg'),  // Ash 	volcanic ash 	50d
 '771' => array('na.jpg','na.jpg'),  // Squall 	squalls 	50d
 '781' => array('tor.jpg','ntor.jpg'),  // Tornado 	tornado 	50d
 '800' => array('skc.jpg','nskc.jpg'),  // Clear 	clear sky 	01d 01n
 '801' => array('few.jpg','nfew.jpg'),  // Clouds 	few clouds: 11-25% 	02d 02n
 '802' => array('sct.jpg','nsct.jpg'),  // Clouds 	scattered clouds: 25-50% 	03d 03n
 '803' => array('bkn.jpg','nbkn.jpg'),  // Clouds 	broken clouds: 51-84% 	04d 04n
 '804' => array('ovc.jpg','novc.jpg'),  // Clouds 	overcast clouds: 85-100% 	04d 04n
);

 if(isset($Codes[$code])) {
	 return $Codes[$code];
 } else {
	 return array('na.jpg','na.jpg');
 }
	
}

// ------------------------------------------------------------------

function setup_tabber() {
?>	
<script type="text/javascript">
// <![CDATA[
/*==================================================
  $Id: tabber.js,v 1.9 2006/04/27 20:51:51 pat Exp $
  tabber.js by Patrick Fitzgerald pat@barelyfitz.com

  Documentation can be found at the following URL:
  http://www.barelyfitz.com/projects/tabber/

  License (http://www.opensource.org/licenses/mit-license.php)

  Copyright (c) 2006 Patrick Fitzgerald

  Permission is hereby granted, free of charge, to any person
  obtaining a copy of this software and associated documentation files
  (the "Software"), to deal in the Software without restriction,
  including without limitation the rights to use, copy, modify, merge,
  publish, distribute, sublicense, and/or sell copies of the Software,
  and to permit persons to whom the Software is furnished to do so,
  subject to the following conditions:

  The above copyright notice and this permission notice shall be
  included in all copies or substantial portions of the Software.

  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
  EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
  MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
  NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS
  BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
  ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
  CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
  SOFTWARE.
  ==================================================*/

function tabberObj(argsObj)
{
  var arg; /* name of an argument to override */

  /* Element for the main tabber div. If you supply this in argsObj,
     then the init() method will be called.
  */
  this.div = null;

  /* Class of the main tabber div */
  this.classMain = "tabber";

  /* Rename classMain to classMainLive after tabifying
     (so a different style can be applied)
  */
  this.classMainLive = "tabberlive";

  /* Class of each DIV that contains a tab */
  this.classTab = "tabbertab";

  /* Class to indicate which tab should be active on startup */
  this.classTabDefault = "tabbertabdefault";

  /* Class for the navigation UL */
  this.classNav = "tabbernav";

  /* When a tab is to be hidden, instead of setting display='none', we
     set the class of the div to classTabHide. In your screen
     stylesheet you should set classTabHide to display:none.  In your
     print stylesheet you should set display:block to ensure that all
     the information is printed.
  */
  this.classTabHide = "tabbertabhide";

  /* Class to set the navigation LI when the tab is active, so you can
     use a different style on the active tab.
  */
  this.classNavActive = "tabberactive";

  /* Elements that might contain the title for the tab, only used if a
     title is not specified in the TITLE attribute of DIV classTab.
  */
  this.titleElements = ['h2','h3','h4','h5','h6'];

  /* Should we strip out the HTML from the innerHTML of the title elements?
     This should usually be true.
  */
  this.titleElementsStripHTML = true;

  /* If the user specified the tab names using a TITLE attribute on
     the DIV, then the browser will display a tooltip whenever the
     mouse is over the DIV. To prevent this tooltip, we can remove the
     TITLE attribute after getting the tab name.
  */
  this.removeTitle = true;

  /* If you want to add an id to each link set this to true */
  this.addLinkId = false;

  /* If addIds==true, then you can set a format for the ids.
     <tabberid> will be replaced with the id of the main tabber div.
     <tabnumberzero> will be replaced with the tab number
       (tab numbers starting at zero)
     <tabnumberone> will be replaced with the tab number
       (tab numbers starting at one)
     <tabtitle> will be replaced by the tab title
       (with all non-alphanumeric characters removed)
   */
  this.linkIdFormat = '<tabberid>nav<tabnumberone>';

  /* You can override the defaults listed above by passing in an object:
     var mytab = new tabber({property:value,property:value});
  */
  for (arg in argsObj) { this[arg] = argsObj[arg]; }

  /* Create regular expressions for the class names; Note: if you
     change the class names after a new object is created you must
     also change these regular expressions.
  */
  this.REclassMain = new RegExp('\\b' + this.classMain + '\\b', 'gi');
  this.REclassMainLive = new RegExp('\\b' + this.classMainLive + '\\b', 'gi');
  this.REclassTab = new RegExp('\\b' + this.classTab + '\\b', 'gi');
  this.REclassTabDefault = new RegExp('\\b' + this.classTabDefault + '\\b', 'gi');
  this.REclassTabHide = new RegExp('\\b' + this.classTabHide + '\\b', 'gi');

  /* Array of objects holding info about each tab */
  this.tabs = new Array();

  /* If the main tabber div was specified, call init() now */
  if (this.div) {

    this.init(this.div);

    /* We don't need the main div anymore, and to prevent a memory leak
       in IE, we must remove the circular reference between the div
       and the tabber object. */
    this.div = null;
  }
}


/*--------------------------------------------------
  Methods for tabberObj
  --------------------------------------------------*/


tabberObj.prototype.init = function(e)
{
  /* Set up the tabber interface.

     e = element (the main containing div)

     Example:
     init(document.getElementById('mytabberdiv'))
   */

  var
  childNodes, /* child nodes of the tabber div */
  i, i2, /* loop indices */
  t, /* object to store info about a single tab */
  defaultTab=0, /* which tab to select by default */
  DOM_ul, /* tabbernav list */
  DOM_li, /* tabbernav list item */
  DOM_a, /* tabbernav link */
  aId, /* A unique id for DOM_a */
  headingElement; /* searching for text to use in the tab */

  /* Verify that the browser supports DOM scripting */
  if (!document.getElementsByTagName) { return false; }

  /* If the main DIV has an ID then save it. */
  if (e.id) {
    this.id = e.id;
  }

  /* Clear the tabs array (but it should normally be empty) */
  this.tabs.length = 0;

  /* Loop through an array of all the child nodes within our tabber element. */
  childNodes = e.childNodes;
  for(i=0; i < childNodes.length; i++) {

    /* Find the nodes where class="tabbertab" */
    if(childNodes[i].className &&
       childNodes[i].className.match(this.REclassTab)) {
      
      /* Create a new object to save info about this tab */
      t = new Object();
      
      /* Save a pointer to the div for this tab */
      t.div = childNodes[i];
      
      /* Add the new object to the array of tabs */
      this.tabs[this.tabs.length] = t;

      /* If the class name contains classTabDefault,
	 then select this tab by default.
      */
      if (childNodes[i].className.match(this.REclassTabDefault)) {
	defaultTab = this.tabs.length-1;
      }
    }
  }

  /* Create a new UL list to hold the tab headings */
  DOM_ul = document.createElement("ul");
  DOM_ul.className = this.classNav;
  
  /* Loop through each tab we found */
  for (i=0; i < this.tabs.length; i++) {

    t = this.tabs[i];

    /* Get the label to use for this tab:
       From the title attribute on the DIV,
       Or from one of the this.titleElements[] elements,
       Or use an automatically generated number.
     */
    t.headingText = t.div.title;

    /* Remove the title attribute to prevent a tooltip from appearing */
    if (this.removeTitle) { t.div.title = ''; }

    if (!t.headingText) {

      /* Title was not defined in the title of the DIV,
	 So try to get the title from an element within the DIV.
	 Go through the list of elements in this.titleElements
	 (typically heading elements ['h2','h3','h4'])
      */
      for (i2=0; i2<this.titleElements.length; i2++) {
	headingElement = t.div.getElementsByTagName(this.titleElements[i2])[0];
	if (headingElement) {
	  t.headingText = headingElement.innerHTML;
	  if (this.titleElementsStripHTML) {
	    t.headingText.replace(/<br>/gi," ");
	    t.headingText = t.headingText.replace(/<[^>]+>/g,"");
	  }
	  break;
	}
      }
    }

    if (!t.headingText) {
      /* Title was not found (or is blank) so automatically generate a
         number for the tab.
      */
      t.headingText = i + 1;
    }

    /* Create a list element for the tab */
    DOM_li = document.createElement("li");

    /* Save a reference to this list item so we can later change it to
       the "active" class */
    t.li = DOM_li;

    /* Create a link to activate the tab */
    DOM_a = document.createElement("a");
    DOM_a.appendChild(document.createTextNode(t.headingText));
    DOM_a.href = "javascript:void(null);";
    DOM_a.title = t.headingText;
    DOM_a.onclick = this.navClick;

    /* Add some properties to the link so we can identify which tab
       was clicked. Later the navClick method will need this.
    */
    DOM_a.tabber = this;
    DOM_a.tabberIndex = i;

    /* Do we need to add an id to DOM_a? */
    if (this.addLinkId && this.linkIdFormat) {

      /* Determine the id name */
      aId = this.linkIdFormat;
      aId = aId.replace(/<tabberid>/gi, this.id);
      aId = aId.replace(/<tabnumberzero>/gi, i);
      aId = aId.replace(/<tabnumberone>/gi, i+1);
      aId = aId.replace(/<tabtitle>/gi, t.headingText.replace(/[^a-zA-Z0-9\-]/gi, ''));

      DOM_a.id = aId;
    }

    /* Add the link to the list element */
    DOM_li.appendChild(DOM_a);

    /* Add the list element to the list */
    DOM_ul.appendChild(DOM_li);
  }

  /* Add the UL list to the beginning of the tabber div */
  e.insertBefore(DOM_ul, e.firstChild);

  /* Make the tabber div "live" so different CSS can be applied */
  e.className = e.className.replace(this.REclassMain, this.classMainLive);

  /* Activate the default tab, and do not call the onclick handler */
  this.tabShow(defaultTab);

  /* If the user specified an onLoad function, call it now. */
  if (typeof this.onLoad == 'function') {
    this.onLoad({tabber:this});
  }

  return this;
};


tabberObj.prototype.navClick = function(event)
{
  /* This method should only be called by the onClick event of an <A>
     element, in which case we will determine which tab was clicked by
     examining a property that we previously attached to the <A>
     element.

     Since this was triggered from an onClick event, the variable
     "this" refers to the <A> element that triggered the onClick
     event (and not to the tabberObj).

     When tabberObj was initialized, we added some extra properties
     to the <A> element, for the purpose of retrieving them now. Get
     the tabberObj object, plus the tab number that was clicked.
  */

  var
  rVal, /* Return value from the user onclick function */
  a, /* element that triggered the onclick event */
  self, /* the tabber object */
  tabberIndex, /* index of the tab that triggered the event */
  onClickArgs; /* args to send the onclick function */

  a = this;
  if (!a.tabber) { return false; }

  self = a.tabber;
  tabberIndex = a.tabberIndex;

  /* Remove focus from the link because it looks ugly.
     I don't know if this is a good idea...
  */
  a.blur();

  /* If the user specified an onClick function, call it now.
     If the function returns false then do not continue.
  */
  if (typeof self.onClick == 'function') {

    onClickArgs = {'tabber':self, 'index':tabberIndex, 'event':event};

    /* IE uses a different way to access the event object */
    if (!event) { onClickArgs.event = window.event; }

    rVal = self.onClick(onClickArgs);
    if (rVal === false) { return false; }
  }

  self.tabShow(tabberIndex);

  return false;
};


tabberObj.prototype.tabHideAll = function()
{
  var i; /* counter */

  /* Hide all tabs and make all navigation links inactive */
  for (i = 0; i < this.tabs.length; i++) {
    this.tabHide(i);
  }
};


tabberObj.prototype.tabHide = function(tabberIndex)
{
  var div;

  if (!this.tabs[tabberIndex]) { return false; }

  /* Hide a single tab and make its navigation link inactive */
  div = this.tabs[tabberIndex].div;

  /* Hide the tab contents by adding classTabHide to the div */
  if (!div.className.match(this.REclassTabHide)) {
    div.className += ' ' + this.classTabHide;
  }
  this.navClearActive(tabberIndex);

  return this;
};


tabberObj.prototype.tabShow = function(tabberIndex)
{
  /* Show the tabberIndex tab and hide all the other tabs */

  var div;

  if (!this.tabs[tabberIndex]) { return false; }

  /* Hide all the tabs first */
  this.tabHideAll();

  /* Get the div that holds this tab */
  div = this.tabs[tabberIndex].div;

  /* Remove classTabHide from the div */
  div.className = div.className.replace(this.REclassTabHide, '');

  /* Mark this tab navigation link as "active" */
  this.navSetActive(tabberIndex);

  /* If the user specified an onTabDisplay function, call it now. */
  if (typeof this.onTabDisplay == 'function') {
    this.onTabDisplay({'tabber':this, 'index':tabberIndex});
  }

  return this;
};

tabberObj.prototype.navSetActive = function(tabberIndex)
{
  /* Note: this method does *not* enforce the rule
     that only one nav item can be active at a time.
  */

  /* Set classNavActive for the navigation list item */
  this.tabs[tabberIndex].li.className = this.classNavActive;

  return this;
};


tabberObj.prototype.navClearActive = function(tabberIndex)
{
  /* Note: this method does *not* enforce the rule
     that one nav should always be active.
  */

  /* Remove classNavActive from the navigation list item */
  this.tabs[tabberIndex].li.className = '';

  return this;
};


/*==================================================*/


function tabberAutomatic(tabberArgs)
{
  /* This function finds all DIV elements in the document where
     class=tabber.classMain, then converts them to use the tabber
     interface.

     tabberArgs = an object to send to "new tabber()"
  */
  var
    tempObj, /* Temporary tabber object */
    divs, /* Array of all divs on the page */
    i; /* Loop index */

  if (!tabberArgs) { tabberArgs = {}; }

  /* Create a tabber object so we can get the value of classMain */
  tempObj = new tabberObj(tabberArgs);

  /* Find all DIV elements in the document that have class=tabber */

  /* First get an array of all DIV elements and loop through them */
  divs = document.getElementsByTagName("div");
  for (i=0; i < divs.length; i++) {
    
    /* Is this DIV the correct class? */
    if (divs[i].className &&
	divs[i].className.match(tempObj.REclassMain)) {
      
      /* Now tabify the DIV */
      tabberArgs.div = divs[i];
      divs[i].tabber = new tabberObj(tabberArgs);
    }
  }
  
  return this;
}


/*==================================================*/


function tabberAutomaticOnLoad(tabberArgs)
{
  /* This function adds tabberAutomatic to the window.onload event,
     so it will run after the document has finished loading.
  */
  var oldOnLoad;

  if (!tabberArgs) { tabberArgs = {}; }

  /* Taken from: http://simon.incutio.com/archive/2004/05/26/addLoadEvent */

  oldOnLoad = window.onload;
  if (typeof window.onload != 'function') {
    window.onload = function() {
      tabberAutomatic(tabberArgs);
    };
  } else {
    window.onload = function() {
      oldOnLoad();
      tabberAutomatic(tabberArgs);
    };
  }
}

/*==================================================*/

/* Run tabberAutomaticOnload() unless the "manualStartup" option was specified */

if (typeof tabberOptions == 'undefined') {

    tabberAutomaticOnLoad();

} else {

  if (!tabberOptions['manualStartup']) {
    tabberAutomaticOnLoad(tabberOptions);
  }

}
// ]]>
</script>
<?php // end tabber JS
}

// End of functions --------------------------------------------------------------------------
