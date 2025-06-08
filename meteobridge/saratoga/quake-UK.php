<?php
// PHP script by Ken True, webmaster@saratoga-weather.org
// for Martin Collins at  http://www.hebrides-photos.com/
// Version 1.01 - 27-Feb-2008 -- Initial release
// Version 1.02 - 12-Mar-2008 -- added settings awareness for Carterlake/WD/PHP template Settings.php
// Version 1.03 - 26-Apr-2008 -- fixed UTC-to-local timezone difference issue for some webservers
// Version 1.04 - 03-Jul-2009 -- PHP5 timezone setting support added
// Version 1.05 - 26-Jan-2011 -- added support for $cacheFileDir global
// Version 1.06 - 30-May-2011 -- fix Notice: error for $doneHeader
// Version 1.07 - 23-Aug-2011 -- fixes for BGS website changes
// Version 1.08 - 15-Jul-2023 -- update to use curl for data fetch
// 
//
  $Version = 'quake-UK.php V1.08 - 15-Jul-2023';
//
// you may copy/modify/use this script as you see fit,
// no warranty is expressed or implied.
//
// Customized for: Quakes reported by UK BGS (
//   http://www.earthquakes.bgs.ac.uk/recent_events/recent_events.html
//

/* NOTE: this script uses the data from the BGS with permission as long as the phrase

<p>Reproduced with the permission of the <a href=\"http://www.earthquakes.bgs.ac.uk/recent_events/recent_events.html\">
British Geological Survey</a> &copy; NERC. All rights Reserved.</p>

appears in the output.

See http://bgs.ac.uk/about/copyright/published.html for more details.

Martin has received permission from the BGS for use of their data by this
script for personal weather websites (non-commercial).

*/

//
// output: creates XHTML 1.0-Strict HTML page (default)
// Options on URL:
//      tablesonly=Y    -- returns only the body code for inclusion
//                         in other webpages.  Omit to return full HTML.
//      magnitude=N.N   -- screens results looking for Richter magnitudes of
//                          N.N or greater.
//      distance=MMM    -- display quakes with epicenters only within 
//                         MMM km of your location
// example URL:
//  http://your.website/quake-UK.php?tablesonly=Y&magnitude=2.1&distance=45
//  would return data without HTML header/footer for earthquakes of
//  magnitude 2.1 or larger within a 45 mile radius of your location.
//
// Usage:
//  you can use this webpage standalone (customize the HTML portion below)
//  or you can include it in an existing page:
/*
//            <?php $doIncludeQuake = true;
//                  include("quake-UK.php");
//            ?> 
*/
//  no parms:    include("quake-UK.php"); 
//  parms:    include("http://your.website/quake-UK.php?tableonly=Y&magnitude=2.0&distance=50");
//
//
// settings:  
//  set $ourTZ to your time zone
//    other settings are optional
//
// cacheName is name of file used to store cached USGS webpage
// 
//
  $ourTZ = "Europe/London";  //NOTE: this *MUST* be set correctly to
// translate UTC times to your LOCAL time for the displays.
//  http://saratoga-weather.org/timezone.txt  has the list of timezone names
//  pick the one that is closest to your location and put in $ourTZ
// also available is the list of country codes (helpful to pick your zone
//  from the timezone.txt table
//  http://saratoga-weather.org/country-codes.txt : list of country codes
 $myLat = '51.417';
 $myLong = '-0.434';

 $highRichter = "3.0"; //change color for quakes >= this magnitude
 
//  pick a format for the time to display ..uncomment one (or make your own)
//$timeFormat = 'D, Y-m-d H:i:s T';  // Fri, 2006-03-31 14:03:22 TZone
//$timeFormat = 'D, Y-M-d H:i:s T';  // Fri, 31-Mar-2006 14:03:22 TZone
  $timeFormat = 'H:i:s T D, d-M-y';  // 14:03:22 TZone Fri, 31-Mar-06
  $cacheFileDir = './';   // default cache file directory
  $cacheName = "quakesUK.txt";  // used to store the file so we don't have to
  //                          fetch it each time
  $refetchSeconds = 1800;     // refetch every nnnn seconds

// end of settings

// Constants
// don't change $baseURL or $fileName or script may break ;-)
  $baseURL = "http://www.quakes.bgs.ac.uk";  //BGS website (omit trailing slash)
  $fileName = "http://www.quakes.bgs.ac.uk/earthquakes/recent_uk_events.html";
// end of constants

//
if (isset($_REQUEST['sce']) && ( strtolower($_REQUEST['sce']) == 'view' or
    strtolower($_REQUEST['sce']) == 'show') ) {
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

// overrides from Settings.php if available
global $SITE,$Debug;
if (isset($SITE['latitude'])) 	{$myLat = $SITE['latitude'];}
if (isset($SITE['longitude'])) 	{$myLong = $SITE['longitude'];}
if (isset($SITE['tz'])) {$ourTZ = $SITE['tz']; }
if (isset($SITE['timeFormat'])) {$timeFormat = $SITE['timeFormat'];}
if(isset($SITE['cacheFileDir']))     {$cacheFileDir = $SITE['cacheFileDir']; }
// end of overrides from Settings.php

// ------ start of code -------
// Check parameters and force defaults/ranges
$Debug = '';
if ( ! isset($_REQUEST['tablesonly']) ) {
        $_REQUEST['tablesonly']="";
}
if (isset($doIncludeQuake) and $doIncludeQuake ) {
  $tablesOnly = "Y";
} else {
  $tablesOnly = $_REQUEST['tablesonly']; // any nonblank is ok
}

if ($tablesOnly) {$tablesOnly = "Y";}
// for testing only 
if ( isset($_REQUEST['lat']) ) { $myLat = $_REQUEST['lat']; }
if ( isset($_REQUEST['lon']) ) { $myLong = $_REQUEST['lon']; }

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


// omit HTML <HEAD>...</HEAD><BODY> if only tables wanted	
// --------------- customize HTML if you like -----------------------
if (! $tablesOnly) {
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Cache-Control" content="no-cache" />
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>Recent UK Earthquakes from BGS</title>
</head>
<body style="background-color:#FFFFFF;">
<?php
}

// ------------- code starts here -------------------

// Establish timezone offset for time display
# Set timezone in PHP5/PHP4 manner
  if (!function_exists('date_default_timezone_set')) {
	putenv("TZ=" . $ourTZ);
#	$Status .= "<!-- using putenv(\"TZ=$ourTZ\") -->\n";
    } else {
	date_default_timezone_set("$ourTZ");
#	$Status .= "<!-- using date_default_timezone_set(\"$ourTZ\") -->\n";
   }

 print("<!-- $Version -->\n");
 print("<!-- server lcl time is: " . date($timeFormat) . " -->\n");
 print("<!-- server GMT time is: " . gmdate($timeFormat) . " -->\n");
 print("<!-- server timezone for this script is: " . getenv('TZ')." -->\n");
 $timediff = date('Z');
 print "<!-- TZ Delta = $timediff seconds (" . $timediff/3600 . " hours) -->\n";


// refresh cached copy of page if needed
// fetch/cache code by Tom at carterlake.org

$cacheName = $cacheFileDir.$cacheName;
if (isset($_REQUEST['cache'])) {$refetchSeconds = 1; }

if (file_exists($cacheName) and filemtime($cacheName) + $refetchSeconds > time()) {
      echo "<!-- using Cached version of $cacheName -->\n";
      $html = implode('', file($cacheName));
    } else {
      echo "<!-- loading $cacheName from $fileName -->\n";
      $html = fetchUrlWithoutHangingUKQ($fileName);
			print $Debug;
      $fp = fopen($cacheName, "w");
      if ($fp) {
        $write = fputs($fp, $html);
        fclose($fp);
      } else {
            print "<!-- unable to write cache file $cacheName -->\n";
      }
      echo "<!-- loading finished. -->\n";
	}
	
 // note: new website design does not include an 'updated' timestamp..
 // use cache file modification time or current time as 'updated'
 if(file_exists($cacheName)) {
	 $UTCdate = filemtime($cacheName);
 } else {
	 $UTCdate = time();
 }
	
 $updatedUTC = gmdate($timeFormat,$UTCdate);
 $updated =  date($timeFormat,$UTCdate);
 //print "<!-- updatedUTC $updatedUTC -->\n"; 
 print "<!-- updated    $updated -->\n";

// Select the middle part of the page for processing
  preg_match('|<table class="bodyTable".*>(.*)</table>|Uis',$html,$betweenspan);
  //print "<!-- betweenspan \n" . print_r($betweenspan,true) . " -->\n";
  
/*
<table class="bodyTable" cellspacing="1" cellpadding="5" border="0">
<tr> 
<th align="center"> Date </th> 
<th align="center"> Time </th> 
<th align="center">Lat </th> 
<th align="center">Lon </th> 
<th align="center">Depth </th> 
<th align="center">Mag   </th> 
<th align="center">Int   </th> 
<th>Region </th> 
<th>Comment  </th> 
</tr>
<tr><td valign="top" align="center" nowrap>
<A HREF="/earthquakes/recent_events/20110822031500.html">
2011/08/22</A></td>
<td valign="top" align="center" nowrap>   03:15:18.8 </td>
<td valign="top" align="center" nowrap>   56.852 </td>
<td valign="top" align="center" nowrap>    -5.766  </td>
<td valign="top" align="center" nowrap>    12.7 </td>
<td valign="top" align="center" nowrap> 0.7 </td>
<td valign="top" align="center" nowrap>  </td>
<td valign="top" align="left" nowrap> LOCHAILORT,HIGHLAND </td> 
<td valign="top" align="left" nowrap>                      </td></tr>
<tr><td valign="top" align="center" nowrap>
<A HREF="/earthquakes/recent_events/20110822023700.html">
2011/08/22</A></td>
<td valign="top" align="center" nowrap>   02:37:46.5 </td>
<td valign="top" align="center" nowrap>   56.862 </td>
<td valign="top" align="center" nowrap>    -5.707  </td>
<td valign="top" align="center" nowrap>    12.3 </td>
<td valign="top" align="center" nowrap> 0.5 </td>
<td valign="top" align="center" nowrap>  </td>
<td valign="top" align="left" nowrap> LOCHAILORT,HIGHLAND </td> 
<td valign="top" align="left" nowrap>                      </td></tr>
<tr><td valign="top" align="center" nowrap>

*/
  preg_match_all('|<tr>(.*)</tr>|Uis',$betweenspan[1],$rawrows);
  // print "<!-- rawrows \n".print_r($rawrows,true)." -->\n";  
  $quakesFound = 0;
  $doneHeader = 0;
// scan the results and process by 1s 
  foreach ($rawrows[1] as $n => $row) {
	if(preg_match('|<th>|is',$row)) { continue; }
    $quakesFound++;
	preg_match_all('|<td.*>(.*)</td>|Uis',$row,$qdata);
	print "<!-- qdata[1] \n".print_r($qdata[1],true)." -->\n";
/*
Date    [0] => 
<A HREF="/earthquakes/recent_events/20110807171120.html">
2011/08/07</A>
Time    [1] =>    17:11:57.9 
Lat     [2] =>    53.154 
Long    [3] =>     -4.770  
Deph    [4] =>     11.9 
Mag     [5] =>  0.8 
Int     [6] =>   3  
Regn    [7] =>  CAERNARFON BAY 
Cmnt    [8] =>  20KM SW OF HOLYHEAD  
*/	
   preg_match('|<a href="(.*)">(.*)</a>|Uis',$qdata[1][0],$matches);
   
   $link = trim($matches[1]);
   $url = $baseURL . $link;
   $utcdate = trim($matches[2]);
   $utctime = trim($qdata[1][1]);

   $quaketime = date($timeFormat, strtotime("$utcdate $utctime UTC"));
   
   $magnitude = trim($qdata[1][5]);
   $location =  trim($qdata[1][7]);
   $latitude = trim($qdata[1][2]);
   $longitude = trim($qdata[1][3]);

   // provide highlighting for quakes >= $highRichter
   if ($magnitude >= $highRichter) {
	 $magnitude = "<span style=\"color: red\">$magnitude</span>";
	 $location = "<span style=\"color: red;\">$location</span>";
   }
   
   $distanceM = round(distance($myLat,$myLong,$latitude,$longitude,"M"));
   $distanceK = round(distance($myLat,$myLong,$latitude,$longitude,"K"));

   
//   print "<!-- link='$link' -->\n";
//   print "<!-- utc='$utctime' quaketime='$quaketime' -->\n";
//   print "<!-- magnitude='$magnitude' loc='$location' -->\n";
//   print "<!-- latitude='$latitude' longitude='$longitude' distanceM='$distanceM' distanceK='$distanceK' -->\n";
  
  
	  if (! $doneHeader) {  // print the header if needed
// --------------- customize HTML if you like -----------------------
	    print "
<table>
<tr><th colspan=\"5\" align=\"center\">UK Earthquakes in the last 50 days</th></tr>
<tr><th colspan=\"5\" align=\"center\">Updated: $updated</th></tr>
<tr><th>Epicenter Near</th><th>Magnitude</th><th>Distance to <br />Epicenter</th><th>Local Time</th><th>Link to<br />Map</th></tr>
";
	    $doneHeader = 1;
	  } // end doneHeader
// --------------- customize HTML if you like -----------------------
	    print "
<tr>
  <td><a href=\"$url\">$location</a></td>
  <td align=\"center\"><b>$magnitude</b></td>
  <td><b>$distanceM</b> mi (<b>$distanceK</b> km)</td>
  <td>$quaketime</td>
  <td align=\"center\"><a href=\"$url\">Map</a></td>
</tr>\n";

  } // end foreach loop

// finish up.  Write trailer info
 
	  if ($doneHeader) {
// --------------- customize HTML if you like -----------------------
	     print "</table><p>$quakesFound UK Earthquakes in the last 50 days.</p><p>Reproduced with the permission of the <a href=\"http://www.quakes.bgs.ac.uk/earthquakes/recent_uk_events.html\">British Geological Survey</a> &copy; NERC. All rights Reserved.</p>\n";
	  
	  } else {
// --------------- customize HTML if you like -----------------------
	    print "<p>No UK Earthquakes recorded for the last 50 days.</p><p>Reproduced with the permission of the <a href=\"http://www.quakes.bgs.ac.uk/earthquakes/recent_uk_events.html\">British Geological Survey</a> &copy; NERC. All rights Reserved.</p>\n";
	  
	  }	 

// print footer of page if needed    
// --------------- customize HTML if you like -----------------------
if (! $tablesOnly ) {   
?>

</body>
</html>

<?php
}

// ----------------------------functions ----------------------------------- 
 
 function fetchUrlWithoutHangingUKQ($url)  {// thanks to Tom at Carterlake.org for this script fragment
// get contents from one URL and return as string 
  global $Debug, $needCookie;
  $useFopen = false;
  $overall_start = time();
  if (! $useFopen) {
   // Set maximum number of seconds (can have floating-point) to wait for feed before displaying page without feed
   $numberOfSeconds=6;   

// Thanks to Curly from ricksturf.com for the cURL fetch functions

  $data = '';
  $domain = parse_url($url,PHP_URL_HOST);
  $theURL = str_replace('nocache','?'.$overall_start,$url);        // add cache-buster to URL if needed
  $Debug .= "<!-- curl fetching '$theURL' -->\n";
  $ch = curl_init();                                           // initialize a cURL session
  curl_setopt($ch, CURLOPT_URL, $theURL);                         // connect to provided URL
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);                 // don't verify peer certificate
  curl_setopt($ch, CURLOPT_USERAGENT, 
    'Mozilla/5.0 (quake-UK.php - saratoga-weather.org)');

  curl_setopt($ch,CURLOPT_HTTPHEADER,                          // request LD-JSON format
     array (
         "Accept: text/html,text/plain"
     ));

  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $numberOfSeconds);  //  connection timeout
  curl_setopt($ch, CURLOPT_TIMEOUT, $numberOfSeconds);         //  data timeout
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);              // return the data transfer
  curl_setopt($ch, CURLOPT_NOBODY, false);                     // set nobody
  curl_setopt($ch, CURLOPT_HEADER, true);                      // include header information
//  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);              // follow Location: redirect
//  curl_setopt($ch, CURLOPT_MAXREDIRS, 1);                      //   but only one time
  if (isset($needCookie[$domain])) {
    curl_setopt($ch, $needCookie[$domain]);                    // set the cookie for this request
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);             // and ignore prior cookies
    $Debug .=  "<!-- cookie used '" . $needCookie[$domain] . "' for GET to $domain -->\n";
  }

  $data = curl_exec($ch);                                      // execute session

  if(curl_error($ch) <> '') {                                  // IF there is an error
   $Debug .= "<!-- curl Error: ". curl_error($ch) ." -->\n";        //  display error notice
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
  $Debug .= "<!-- HTTP stats: " .
    " RC=".$cinfo['http_code'] .
    " dest=".$cinfo['primary_ip'] ;
	if(isset($cinfo['primary_port'])) { 
	  $Debug .= " port=".$cinfo['primary_port'] ;
	}
	if(isset($cinfo['local_ip'])) {
	  $Debug .= " (from sce=" . $cinfo['local_ip'] . ")";
	}
	$Debug .= 
	"\n      Times:" .
    " dns=".sprintf("%01.3f",round($cinfo['namelookup_time'],3)).
    " conn=".sprintf("%01.3f",round($cinfo['connect_time'],3)).
    " pxfer=".sprintf("%01.3f",round($cinfo['pretransfer_time'],3));
	if($cinfo['total_time'] - $cinfo['pretransfer_time'] > 0.0000) {
	  $Debug .=
	  " get=". sprintf("%01.3f",round($cinfo['total_time'] - $cinfo['pretransfer_time'],3));
	}
    $Debug .= " total=".sprintf("%01.3f",round($cinfo['total_time'],3)) .
    " secs -->\n";

  //$Debug .= "<!-- curl info\n".print_r($cinfo,true)." -->\n";
  curl_close($ch);                                              // close the cURL session
  //$Debug .= "<!-- raw data\n".$data."\n -->\n"; 
  $stuff = explode("\r\n\r\n",$data); // maybe we have more than one header due to redirects.
  $content = (string)array_pop($stuff); // last one is the content
  $headers = (string)array_pop($stuff); // next-to-last-one is the headers
  if($cinfo['http_code'] <> '200') {
    $Debug .= "<!-- headers returned:\n".$headers."\n -->\n"; 
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
				"User-agent: Mozilla/5.0 (quake-UK.php - saratoga-weather.org)\r\n" .
				"Accept: application/ld+json\r\n"
	  ),
	  'https'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (quake-UK.php - saratoga-weather.org)\r\n" .
				"Accept: application/ld+json\r\n"
	  )
	);
	
   $STRcontext = stream_context_create($STRopts);

   $T_start = fetch_microtimeUKQ();
   $xml = file_get_contents($url,false,$STRcontext);
   $T_close = fetch_microtimeUKQ();
   $headerarray = get_headers($url,0);
   $theaders = join("\r\n",$headerarray);
   $xml = $theaders . "\r\n\r\n" . $xml;

   $ms_total = sprintf("%01.3f",round($T_close - $T_start,3)); 
   $Debug .= "<!-- file_get_contents() stats: total=$ms_total secs -->\n";
   $Debug .= "<-- get_headers returns\n".$theaders."\n -->\n";
//   print " file() stats: total=$ms_total secs.\n";
   $overall_end = time();
   $overall_elapsed =   $overall_end - $overall_start;
   $Debug .= "<!-- fetch function elapsed= $overall_elapsed secs. -->\n"; 
//   print "fetch function elapsed= $overall_elapsed secs.\n"; 
   return($xml);
 }

}    // end ECF_fetch_URL

// ------------------------------------------------------------------

function fetch_microtimeUKQ()
{
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + (float)$sec);
}
   
// ------------ distance calculation function ---------------------
   
    //**************************************
    //     
    // Name: Calculate Distance and Radius u
    //     sing Latitude and Longitude in PHP
    // Description:This function calculates 
    //     the distance between two locations by us
    //     ing latitude and longitude from ZIP code
    //     , postal code or postcode. The result is
    //     available in miles, kilometers or nautic
    //     al miles based on great circle distance 
    //     calculation. 
    // By: ZipCodeWorld
    //
    //This code is copyrighted and has
	// limited warranties.Please see http://
    //     www.Planet-Source-Code.com/vb/scripts/Sh
    //     owCode.asp?txtCodeId=1848&lngWId=8    //for details.    //**************************************
    //     
/*::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::*/
    /*:: :*/
    /*:: This routine calculates the distance between two points (given the :*/
    /*:: latitude/longitude of those points). It is being used to calculate :*/
    /*:: the distance between two ZIP Codes or Postal Codes using our:*/
    /*:: ZIPCodeWorld(TM) and PostalCodeWorld(TM) products. :*/
    /*:: :*/
    /*:: Definitions::*/
    /*::South latitudes are negative, east longitudes are positive:*/
    /*:: :*/
    /*:: Passed to function::*/
    /*::lat1, lon1 = Latitude and Longitude of point 1 (in decimal degrees) :*/
    /*::lat2, lon2 = Latitude and Longitude of point 2 (in decimal degrees) :*/
    /*::unit = the unit you desire for results:*/
    /*::where: 'M' is statute miles:*/
    /*:: 'K' is kilometers (default):*/
    /*:: 'N' is nautical miles :*/
    /*:: United States ZIP Code/ Canadian Postal Code databases with latitude & :*/
    /*:: longitude are available at http://www.zipcodeworld.com :*/
    /*:: :*/
    /*:: For enquiries, please contact sales@zipcodeworld.com:*/
    /*:: :*/
    /*:: Official Web site: http://www.zipcodeworld.com :*/
    /*:: :*/
    /*:: Hexa Software Development Center © All Rights Reserved 2004:*/
    /*:: :*/
    /*::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::*/
    function distance($lat1, $lon1, $lat2, $lon2, $unit) { 
    $theta = $lon1 - $lon2; 
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta)); 
    $dist = acos($dist); 
    $dist = rad2deg($dist); 
    $miles = $dist * 60 * 1.1515;
    $unit = strtoupper($unit);
    if ($unit == "K") {
    return ($miles * 1.609344); 
    } else if ($unit == "N") {
    return ($miles * 0.8684);
    } else {
    return $miles;
    }
  }
  
//To calculate the delta between the local time and UTC:
function tzdelta ( $iTime = 0 )
{
   if ( 0 == $iTime ) { $iTime = time(); }
   $ar = localtime ( $iTime );
   $ar[5] += 1900; $ar[4]++;
   $iTztime = gmmktime ( $ar[2], $ar[1], $ar[0],
       $ar[4], $ar[3], $ar[5], $ar[8] );
   return ( $iTztime - $iTime );
}
  
// --------------end of functions ---------------------------------------


?>
