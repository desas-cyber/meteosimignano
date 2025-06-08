<!-- begin MB-trends-inc.php V1.10 - 20-Jun-2023 -->
<?php
// this is the working section of the wxtrends.php page from the
// Carterlake/AJAX/PHP templates from http://saratoga-weather.org/template/
// Version 1.00 - Initial PHP version release
// Version 1.01 - minor fixes
// Version 1.02 - fixed formatRecord for records without &deg; in record string
// Version 1.03 - fixed adjustDate to handle 2 and 4 digit dates
// Version 1.04 - 25-Jun-2008 - now uses testtags.php for data + translation features added
// Version 1.05 - 03-Jun-2010 - replaced deprecated split() with explode()
// Version 1.06 - 08-Aug-2011 - adapted for Meteohub use - needs yesterday.php for yesterday values
// Version 1.07 - 18-Feb-2011 - fixed Notice: errata and one short php tag
// Version 1.08 - 08-Mar-2013 - adapted for Meteobridge use
// Version 1.09 - 30-Sep-2017 - added Dew Point trends display, and current conditions note
// Version 1.10 - 20-Jun-2023 - additional variables added by meteoalmendralejo.es for MB-trends-inc
//
print "<!-- begin MB-trends-inc.php V1.10 - 20-Jun-2023 -->\n";
/* --------------------------------------------------------
Make sure you have these in Settings.php:

$SITE['uomDistance'] = ' miles';  // or ' km' -- used for Wind Run display
$SITE['WDdateMDY'] = true; // for WD date format of month/day/year.  =false for day/month/year
$SITE['dateOnlyFormat'] = 'd-M-Y'; // for 31-Mar-2008 or 'j/n/Y' for Euro format

otherwise the defaults below will be used.
------------------------------------------------------------ */
// --- default settings -----------(will be overriden by Settings.php)---------------
$uomTemp = '&deg;F';
$uomBaro = ' inHg';
$uomWind = ' mph';
$uomRain = ' in';
$uomPerHour = '/hr';
$uomDistance = ' miles';
$timeFormat = 'd-M-Y g:ia';  // 31-Mar-2006 6:35pm
//$timeFormat = 'd-M-Y H:i';   // 31-Mar-2006 18:35
$timeOnlyFormat = 'g:ia';    // h:mm[am|pm];
//$timeOnlyFormat = 'H:i';     // hh:mm
$dateOnlyFormat = 'd-M-Y';   // d-Mon-YYYY
$WDdateMDY = true;     // true=dates by WD are 'month/day/year'
//                     // false=dates by WD are 'day/month/year'
$ourTZ = "PST8PDT";  //NOTE: this *MUST* be set correctly to
// translate UTC times to your LOCAL time for the displays.
//
$haveUV   = true;        // set to false if no UV sensor
$haveSolar = true;       // set to false if no Solar sensor
$graphImageDir = './'; 
// allow viewing of generated source

if ( isset($_REQUEST['sce']) && strtolower($_REQUEST['sce']) == 'view' ) {
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

include_once("Settings.php");
include_once("common.php");
if (isset($SITE['WXtags'])) {
  include_once($SITE['WXtags']);
}

global $SITE;
if (isset($SITE['uomTemp'])) 	{$uomTemp = $SITE['uomTemp'];}
if (isset($SITE['uomBaro'])) 	{$uomBaro = $SITE['uomBaro'];}
if (isset($SITE['uomWind'])) 	{$uomWind = $SITE['uomWind'];}
if (isset($SITE['uomRain'])) 	{$uomRain = $SITE['uomRain'];}
if (isset($SITE['uomPerHour'])) {$uomPerHour = $SITE['uomPerHour'];}
if (isset($SITE['uomDistance'])) {$uomDistance = $SITE['uomDistance'];}
if (isset($SITE['timeFormat'])) {$timeFormat = $SITE['timeFormat'];}
if (isset($SITE['timeOnlyFormat'])) {$timeOnlyFormat = $SITE['timeOnlyFormat'];}
if (isset($SITE['dateOnlyFormat'])) {$dateOnlyFormat = $SITE['dateOnlyFormat'];}
if (isset($SITE['WDdateMDY']))  {$WDdateMDY = $SITE['WDdateMDY'];}
if (isset($SITE['tz'])) 		{$ourTZ = $SITE['tz'];}
if (isset($SITE['UV'])) 		{$haveUV = $SITE['UV'];}
if (isset($SITE['SOLAR'])) 		{$haveSolar = $SITE['SOLAR'];}

if(isset($SITE['graphImageDir'])) {$graphImageDir = $SITE['graphImageDir']; }
// end of overrides from Settings.php
// testing parameters
$DebugMode = false;
if (isset($_REQUEST['debug'])) {$DebugMode = strtolower($_REQUEST['debug']) == 'y'; }
if (isset($_REQUEST['UV'])) {$haveUV = $_REQUEST['UV'] <> '0';}
if (isset($_REQUEST['solar'])) {$haveSolar = $_REQUEST['solar'] <> '0';}
?>
<h2><?php langtrans('Trends as of'); ?> <span class="ajax" id="ajaxdate"><?php echo adjustWDdate($date); ?></span> 
<span class="ajax" id="ajaxindicator"><?php langtrans('at'); ?></span> 
<span class="ajax" id="ajaxtime"><?php echo adjustWDtime($time); ?></span> </h2>
<p>(<small>
<?php langtrans('Note: Only the \'Current\' values may be updated in near-realtime, while the other values are current when the page is loaded.'); ?> 
<?php langtrans('The other data was from ');
 echo '<strong>'.adjustWDdate($date). " " . adjustWDtime($time)."</strong>.  ";
 langtrans('Reload this page for updated data');
  ?>.)</small></p>

<table width="99%" cellpadding="0" cellspacing="0" border="0">

<tr class="table-top" style="text-align: center">
<td><?php langtrans('TIME'); ?></td>
<td><?php langtrans('TEMP'); ?><br/> <?php echo $uomTemp; ?></td>
<td><?php langtrans('WIND SPEED'); ?><br/> <?php echo $uomWind; ?></td>
<td><?php langtrans('WIND DIR'); ?><br/> &nbsp;</td>
<td><?php langtrans('HUMIDITY'); ?><br/> %</td>
<td><?php langtrans('DEW POINT'); ?><br/> <?php echo $uomTemp; ?></td>
<td><?php langtrans('PRESSURE'); ?><br/> <?php echo $uomBaro; ?></td>
<td><?php langtrans('RAIN'); ?><br/> <?php echo $uomRain; ?></td>
<td><?php langtrans('RAIN RATE'); ?><br/> <?php echo $uomRain . '/h'; // Anadido por AGallardo ?></td>
</tr>

<tr class="column-light" style="text-align: center">
<td><?php langtrans('Current'); ?></td>
<td><span class="ajax" id="ajaxtempNoU"><?php echo $temp0minuteago; ?></span></td>
<td><span class="ajax" id="ajaxwindNoU"><?php echo $wind0minuteago; ?></span></td>
<td><span class="ajax" id="ajaxwinddir"><?php langtrans(trim($dir0minuteago)); ?> (<?php echo $dir0minuteagodeg; ?>&deg;)</span></td>
<td><span class="ajax" id="ajaxhumidity"><?php echo $hum0minuteago; ?></span></td>
<td><span class="ajax" id="ajaxdewNoU"><?php echo $dew0minuteago; ?></span></td>
<td><span class="ajax" id="ajaxbaroNoU"><?php echo $baro0minuteago; ?></span></td>
<td><span class="ajax" id="ajaxrainNoU"><?php echo $rain0minuteago; ?></span></td>
<td><span class="ajax" id="ajaxrainratehrNoU"><?php echo $rainrate0minuteago; // Anadido por AGallardo ?></span></td>
</tr>
<?php // Añado RAIN RATE a cada fila AGallardo ?>
<?php if(isset($temp5minuteago)) { ?>
<tr class="column-dark" style="text-align: center">
<td><?php langtrans('5 minutes ago'); ?></td>
<td><?php echo $temp5minuteago; ?></td>
<td><?php echo $wind5minuteago; ?></td>
<td><?php langtrans(trim($dir5minuteago)); ?> (<?php echo $dir5minuteagodeg; ?>&deg;)</td>
<td><?php echo $hum5minuteago; ?></td>
<td><?php echo $dew5minuteago; ?></td>
<td><?php echo $baro5minuteago; ?></td>
<td><?php echo $rain5minuteago; ?></td>
<td><?php echo $rainrate5minuteago; ?></td>
</tr>
<?php } ?>

<?php if(isset($temp10minuteago)) { ?>
<tr class="column-light" style="text-align: center">
<td><?php langtrans('10 minutes ago'); ?></td>
<td><?php echo $temp10minuteago; ?></td>
<td><?php echo $wind10minuteago; ?></td>
<td><?php langtrans(trim($dir10minuteago)); ?> (<?php echo $dir10minuteagodeg; ?>&deg;)</td>
<td><?php echo $hum10minuteago; ?></td>
<td><?php echo $dew10minuteago; ?></td>
<td><?php echo $baro10minuteago; ?></td>
<td><?php echo $rain10minuteago; ?></td>
<td><?php echo $rainrate10minuteago; ?></td>
</tr>
<?php } ?>

<?php if(isset($temp15minuteago)) { ?>
<tr class="column-dark" style="text-align: center">
<td><?php langtrans('15 minutes ago'); ?></td>
<td><?php echo $temp15minuteago; ?></td>
<td><?php echo $wind15minuteago; ?></td>
<td><?php langtrans(trim($dir15minuteago)); ?> (<?php echo $dir15minuteagodeg; ?>&deg;)</td>
<td><?php echo $hum15minuteago; ?></td>
<td><?php echo $dew15minuteago; ?></td>
<td><?php echo $baro15minuteago; ?></td>
<td><?php echo $rain15minuteago; ?></td>
<td><?php echo $rainrate15minuteago; ?></td>
</tr>
<?php } ?>

<?php if(isset($temp30minuteago)) { ?>
<tr class="column-light" style="text-align: center">
<td><?php langtrans('30 minutes ago'); ?></td>
<td><?php echo $temp30minuteago; ?></td>
<td><?php echo $wind30minuteago; ?></td>
<td><?php langtrans(trim($dir30minuteago)); ?> (<?php echo $dir30minuteagodeg; ?>&deg;)</td>
<td><?php echo $hum30minuteago; ?></td>
<td><?php echo $dew30minuteago; ?></td>
<td><?php echo $baro30minuteago; ?></td>
<td><?php echo $rain30minuteago; ?></td>
<td><?php echo $rainrate30minuteago; ?></td>
</tr>
<?php } ?>

<?php if(isset($temp60minuteago)) { ?>
<tr class="column-dark" style="text-align: center">
<td><?php langtrans('60 minutes ago'); ?></td>
<td><?php echo $temp60minuteago; ?></td>
<td><?php echo $wind60minuteago; ?></td>
<td><?php langtrans(trim($dir60minuteago)); ?> (<?php echo $dir60minuteagodeg; ?>&deg;)</td>
<td><?php echo $hum60minuteago; ?></td>
<td><?php echo $dew60minuteago; ?></td>
<td><?php echo $baro60minuteago; ?></td>
<td><?php echo $rain60minuteago; ?></td>
<td><?php echo $rainrate60minuteago; ?></td>
</tr>
<?php } ?>

<?php if(isset($temp2hourago)) { ?>
<tr class="column-light" style="text-align: center">
<td><?php langtrans('2 hours ago'); ?></td>
<td><?php echo $temp2hourago; ?></td>
<td><?php echo $wind2hourago; ?></td>
<td><?php langtrans(trim($dir2hourago)); ?> (<?php echo $dir2houragodeg; ?>&deg;)</td>
<td><?php echo $hum2hourago; ?></td>
<td><?php echo $dew2hourago; ?></td>
<td><?php echo $baro2hourago; ?></td>
<td><?php echo $rain2hourago; ?></td>
<td><?php echo $rainrate2hourago; ?></td>
</tr>
<?php } ?>

<?php if(isset($temp3hourago)) { ?>
<tr class="column-dark" style="text-align: center">
<td><?php langtrans('3 hours ago'); ?></td>
<td><?php echo $temp3hourago; ?></td>
<td><?php echo $wind3hourago; ?></td>
<td><?php langtrans(trim($dir3hourago)); ?> (<?php echo $dir3houragodeg; ?>&deg;)</td>
<td><?php echo $hum3hourago; ?></td>
<td><?php echo $dew3hourago; ?></td>
<td><?php echo $baro3hourago; ?></td>
<td><?php echo $rain3hourago; ?></td>
<td><?php echo $rainrate3hourago; ?></td>
</tr>
<?php } ?>

<?php if(isset($temp6hourago)) { ?>
<tr class="column-light" style="text-align: center">
<td><?php langtrans('6 hours ago'); ?></td>
<td><?php echo $temp6hourago; ?></td>
<td><?php echo $wind6hourago; ?></td>
<td><?php langtrans(trim($dir6hourago)); ?> (<?php echo $dir6houragodeg; ?>&deg;)</td>
<td><?php echo $hum6hourago; ?></td>
<td><?php echo $dew6hourago; ?></td>
<td><?php echo $baro6hourago; ?></td>
<td><?php echo $rain6hourago; ?></td>
<td><?php echo $rainrate6hourago; ?></td>
</tr>
<?php } ?>

<?php if(isset($temp12hourago)) { ?>
<tr class="column-dark" style="text-align: center">
<td><?php langtrans('12 hours ago'); ?></td>
<td><?php echo $temp12hourago; ?></td>
<td><?php echo $wind12hourago; ?></td>
<td><?php langtrans(trim($dir12hourago)); ?> (<?php echo $dir12houragodeg; ?>&deg;)</td>
<td><?php echo $hum12hourago; ?></td>
<td><?php echo $dew12hourago; ?></td>
<td><?php echo $baro12hourago; ?></td>
<td><?php echo $rain12hourago; ?></td>
<td><?php echo $rainrate12hourago; ?></td>
</tr>
<?php } ?>

<?php if(isset($temp24hourago)) { ?>
<tr class="column-light" style="text-align: center">
<td><?php langtrans('24 hours ago'); ?></td>
<td><?php echo $temp24hourago; ?></td>
<td><?php echo $wind24hourago; ?></td>
<td><?php langtrans(trim($dir24hourago)); ?> (<?php echo $dir24houragodeg; ?>&deg;)</td>
<td><?php echo $hum24hourago; ?></td>
<td><?php echo $dew24hourago; ?></td>
<td><?php echo $baro24hourago; ?></td>
<td><?php echo $rain24hourago; ?></td>
<td><?php echo $rainrate24hourago; ?></td>
</tr>
<?php } ?>

<?php if(isset($temp2dayago)) { ?>
<tr class="column-dark" style="text-align: center">
<td><?php langtrans('2 days ago'); ?></td>
<td><?php echo $temp2dayago; ?></td>
<td><?php echo $wind2dayago; ?></td>
<td><?php langtrans(trim($dir2dayago)); ?> (<?php echo $dir2dayagodeg; ?>&deg;)</td>
<td><?php echo $hum2dayago; ?></td>
<td><?php echo $dew2dayago; ?></td>
<td><?php echo $baro2dayago; ?></td>
<td><?php echo $rain2dayago; ?></td>
<td><?php echo $rainrate2dayago; ?></td>
</tr>
<?php } ?>

<?php if(isset($temp3dayago)) { ?>
<tr class="column-light" style="text-align: center">
<td><?php langtrans('3 days ago'); ?></td>
<td><?php echo $temp3dayago; ?></td>
<td><?php echo $wind3dayago; ?></td>
<td><?php langtrans(trim($dir3dayago)); ?> (<?php echo $dir3dayagodeg; ?>&deg;)</td>
<td><?php echo $hum3dayago; ?></td>
<td><?php echo $dew3dayago; ?></td>
<td><?php echo $baro3dayago; ?></td>
<td><?php echo $rain3dayago; ?></td>
<td><?php echo $rainrate3dayago; ?></td>
</tr>
<?php } ?>

<?php if(isset($temp4dayago)) { ?>
<tr class="column-dark" style="text-align: center">
<td><?php langtrans('4 days ago'); ?></td>
<td><?php echo $temp4dayago; ?></td>
<td><?php echo $wind4dayago; ?></td>
<td><?php langtrans(trim($dir4dayago)); ?> (<?php echo $dir4dayagodeg; ?>&deg;)</td>
<td><?php echo $hum4dayago; ?></td>
<td><?php echo $dew4dayago; ?></td>
<td><?php echo $baro4dayago; ?></td>
<td><?php echo $rain4dayago; ?></td>
<td><?php echo $rainrate4dayago; ?></td>
</tr>
<?php } ?>

<?php if(isset($temp5dayago)) { ?>
<tr class="column-light" style="text-align: center">
<td><?php langtrans('5 days ago'); ?></td>
<td><?php echo $temp5dayago; ?></td>
<td><?php echo $wind5dayago; ?></td>
<td><?php langtrans(trim($dir5dayago)); ?> (<?php echo $dir5dayagodeg; ?>&deg;)</td>
<td><?php echo $hum5dayago; ?></td>
<td><?php echo $dew5dayago; ?></td>
<td><?php echo $baro5dayago; ?></td>
<td><?php echo $rain5dayago; ?></td>
<td><?php echo $rainrate5dayago; ?></td>
</tr>
<?php } ?>

<?php if(isset($temp6dayago)) { ?>
<tr class="column-dark" style="text-align: center">
<td><?php langtrans('6 days ago'); ?></td>
<td><?php echo $temp6dayago; ?></td>
<td><?php echo $wind6dayago; ?></td>
<td><?php langtrans(trim($dir6dayago)); ?> (<?php echo $dir6dayagodeg; ?>&deg;)</td>
<td><?php echo $hum6dayago; ?></td>
<td><?php echo $dew6dayago; ?></td>
<td><?php echo $baro6dayago; ?></td>
<td><?php echo $rain6dayago; ?></td>
<td><?php echo $rainrate6dayago; ?></td>
</tr>
<?php } ?>

<?php if(isset($temp7dayago)) { ?>
<tr class="column-light" style="text-align: center">
<td><?php langtrans('7 days ago'); ?></td>
<td><?php echo $temp7dayago; ?></td>
<td><?php echo $wind7dayago; ?></td>
<td><?php langtrans(trim($dir7dayago)); ?> (<?php echo $dir7dayagodeg; ?>&deg;)</td>
<td><?php echo $hum7dayago; ?></td>
<td><?php echo $dew7dayago; ?></td>
<td><?php echo $baro7dayago; ?></td>
<td><?php echo $rain7dayago; ?></td>
<td><?php echo $rainrate7dayago; ?></td>
</tr>
<?php } ?>

</table>

	  <h1 style="margin: 10px 0;"><?php langtrans('Records and Stats'); ?></h1> 

<?php // Añado RAIN RATE a la tabla, Añado HUM, Añado tiempo ALL, Añado HI ET AGallardo ?>
<table width="99%" cellpadding="0" cellspacing="0" border="0">

<tr class="table-top">
<td colspan="2"><?php langtrans('RAIN'); ?></td>
<?php if((isset($dayswithnorain) and isset($dateoflastrainalways)) or
      isset($raincurrentweek) or isset($dayswithrain) or isset($dayswithrainyear)) { ?>
<td colspan="2"><?php langtrans('RAIN HISTORY'); ?></td>
<?php } else { ?>
<td colspan="2"><?php langtrans('RAIN RATE HIGHS'); ?></td>
<?php } ?>
</tr>

<tr class="column-light">
<td><?php langtrans('last hour'); ?></td>
<td><?php echo unUnit($hourrn) . $uomRain; ?></td>
<?php if(isset($dayswithnorain) and isset($dateoflastrainalways)) { ?>
<td>&nbsp;</td>
<td>&nbsp;</td>
<?php } else { ?>
<td><?php langtrans('last hour'); ?></td>
<td><?php echo unUnit($maxrainratehr) . $uomRain . '/h'; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($highrrhtime); ?></td>
<?php } // end dayswithnorain, dateoflastrainalways ?>
</tr>

<tr class="column-dark">
<td><?php langtrans('Today'); ?></td>
<td><?php echo unUnit($dayrn) . $uomRain; ?></td>
<?php if(isset($dayswithnorain) and isset($dateoflastrainalways)) { ?>
<td><?php langtrans('Today'); ?></td>
<td><?php echo $dayswithnorain . ' '; 
$dayswithnorain!=1?langtrans('days since last rain on'):langtrans('day since last rain on'); ?> 
<?php echo adjustWDdate($dateoflastrainalways) . ' ' . adjustWDtime($timeoflastrainalways); ?></td>
<?php } else { ?>
<td><?php langtrans('Today'); ?></td>
<td><?php echo unUnit($maxrainrate) . $uomRain . '/h'; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($highrrtime); ?></td>
<?php } // end dayswithnorain, dateoflastrainalways ?>
</tr>

<tr class="column-light">
<td><?php langtrans('Yest.'); ?></td>
<td><?php echo unUnit($yesterdayrain) . $uomRain; ?></td>
<?php if(isset($raincurrentweek)) { ?>
  <td><?php langtrans('Week'); ?></td>
  <td><?php echo unUnit($raincurrentweek) . $uomRain; ?> <?php langtrans('over last 7 days'); ?>.</td>
<?php } else { ?>
  <td><?php langtrans('Yest.'); ?></td>
  <td><?php echo unUnit($maxrainrateyest) . $uomRain . '/h'; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($highrryesttime); ?></td>
<?php } ?>
</tr>

<tr class="column-dark">
<td><?php langtrans('Month'); ?>&nbsp;</td>
<td><?php echo unUnit($monthrn) . $uomRain; ?> 
<?php if(isset($dayswithrain)) { ?> 
  (<?php echo $dayswithrain . ' '; 
  $dayswithrain!=1?langtrans('rain days this month'):langtrans('rain day this month'); ?>)
<?php } ?></td>
<?php if(isset($raintodatemonthago)) { ?>
<td><?php langtrans('Month'); ?>&nbsp;</td>
<td><?php echo unUnit($raintodatemonthago) . $uomRain; ?> <?php langtrans('last month'); ?>. </td>
<?php } else { ?>
<td><?php langtrans('Month'); ?>&nbsp;</td>
<td><?php echo unUnit($mrecordrainrate) . $uomRain . '/h'; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($mrecordrainrateyear,$mrecordrainratemonth,$mrecordrainrateday); ?></td>
<?php } // end if isset($raintodatemonthago) ?>
</tr>

<tr class="column-light">
<td><?php langtrans('Year'); ?></td>
<td><?php echo unUnit($yearrn) . $uomRain; ?>
<?php if (isset($dayswithrainyear)) { ?>
 (
  <?php echo $dayswithrainyear . ' '; 
  $dayswithrainyear!=1?langtrans('rain days this year'):langtrans('rain day this year'); ?>)
<?php } ?></td>
<?php if(isset($raintodateyearago)) { ?>
<td><?php langtrans('Year'); ?></td>
<td><?php echo unUnit($raintodateyearago) . $uomRain; ?> <?php langtrans('total last year at this time'); ?>.</td>
<?php } else { ?>
<td><?php langtrans('Year'); ?></td>
<td><?php echo unUnit($yrecordrainrate) . $uomRain . '/h'; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($yrecordrainrateyear,$yrecordrainratemonth,$yrecordrainrateday); ?></td>
<?php } // end if isset($raintodateyearago) ?>
</tr>

<tr class="column-dark">
<td>&nbsp;</td>
<td>&nbsp;</td>
<?php if(isset($raintodateyearago)) { ?>
<td><?php langtrans('All'); ?></td>
<td>&nbsp;</td>
<?php } else { ?>
<td><?php langtrans('All'); ?></td>
<td><?php echo unUnit($arecordrainrate) . $uomRain . '/h'; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($arecordrainrateyear,$arecordrainratemonth,$arecordrainrateday); ?></td>
<?php } // end if isset($raintodateyearago) ?>
</tr>

<tr class="table-top">
<td colspan="2"><?php langtrans('TEMPERATURE HIGHS'); ?></td>
<td colspan="2"><?php langtrans('TEMPERATURE LOWS'); ?></td>
</tr>

<tr class="column-light">
<td><?php langtrans('Today'); ?></td>
<td><?php echo unUnit($maxtemp).' '.$uomTemp; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($maxtempt); ?></td>
<td><?php langtrans('Today'); ?></td>
<td><?php echo unUnit($mintemp). " $uomTemp"; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($mintempt); ?></td>
</tr>

<?php if(isset($maxtempyest) and isset($mintempyest)) { ?>
<tr class="column-dark">
<td><?php langtrans('Yest.'); ?></td>
<td><?php echo unUnit($maxtempyest).' '.$uomTemp; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($maxtempyestt); ?></td>
<td><?php langtrans('Yest.'); ?></td>
<td><?php echo unUnit($mintempyest). " $uomTemp"; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($mintempyestt); ?></td>
</tr>
<?php } // end for yesterday min/max temp row ?>
<tr class="column-light">
<td><?php langtrans('Month'); ?>&nbsp;</td>
<td><?php echo unUnit($mrecordhightemp).' '.$uomTemp; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($mrecordhightempyear,$mrecordhightempmonth,$mrecordhightempday); ?></td>
<td><?php langtrans('Month'); ?>&nbsp;</td>
<td><?php echo unUnit($mrecordlowtemp). " $uomTemp"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($mrecordlowtempyear,$mrecordlowtempmonth,$mrecordlowtempday); ?></td>
</tr>

<tr class="column-dark">
<td><?php langtrans('Year'); ?></td>
<td><?php echo unUnit($yrecordhightemp).' '.$uomTemp; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($yrecordhightempyear,$yrecordhightempmonth,$yrecordhightempday); ?>
</td>
<td><?php langtrans('Year'); ?></td>

<td><?php echo unUnit($yrecordlowtemp). " $uomTemp"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($yrecordlowtempyear,$yrecordlowtempmonth,$yrecordlowtempday); ?>
</td>
</tr>
<tr class="column-light">
<td><?php langtrans('All'); ?></td>
<td><?php echo unUnit($arecordhightemp).' '.$uomTemp; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($arecordhightempyear,$arecordhightempmonth,$arecordhightempday); ?>
</td>
<td><?php langtrans('All'); ?></td>

<td><?php echo unUnit($arecordlowtemp). " $uomTemp"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($arecordlowtempyear,$arecordlowtempmonth,$arecordlowtempday); ?>
</td>
</tr>

<tr class="table-top">
<td colspan="2"><?php langtrans('HUMIDITY HIGHS'); ?></td>
<td colspan="2"><?php langtrans('HUMIDITY LOWS'); ?></td>
</tr>

<tr class="column-light">
<td><?php langtrans('Today'); ?></td>
<td><?php echo unUnit($maxhum).' %'; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($maxhumt); ?></td>
<td><?php langtrans('Today'); ?></td>
<td><?php echo unUnit($minhum). " %"; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($minhumt); ?></td>
</tr>

<?php if(isset($maxhumyest) and isset($minhumyest)) { ?>
<tr class="column-dark">
<td><?php langtrans('Yest.'); ?></td>
<td><?php echo unUnit($maxhumyest).' %'; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($maxhumyestt); ?></td>
<td><?php langtrans('Yest.'); ?></td>
<td><?php echo unUnit($minhumyest). " %"; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($minhumyestt); ?></td>
</tr>
<?php } // end for yesterday min/max hum row ?>
<tr class="column-light">
<td><?php langtrans('Month'); ?>&nbsp;</td>
<td><?php echo unUnit($mrecordhighhum).' %'; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($mrecordhighhumyear,$mrecordhighhummonth,$mrecordhighhumday); ?></td>
<td><?php langtrans('Month'); ?>&nbsp;</td>
<td><?php echo unUnit($mrecordlowhum). " %"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($mrecordlowhumyear,$mrecordlowhummonth,$mrecordlowhumday); ?></td>
</tr>

<tr class="column-dark">
<td><?php langtrans('Year'); ?></td>
<td><?php echo unUnit($yrecordhighhum).' %'; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($yrecordhighhumyear,$yrecordhighhummonth,$yrecordhighhumday); ?>
</td>
<td><?php langtrans('Year'); ?></td>

<td><?php echo unUnit($yrecordlowhum). " %"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($yrecordlowhumyear,$yrecordlowhummonth,$yrecordlowhumday); ?>
</td>
</tr>

<tr class="column-light">
<td><?php langtrans('All'); ?></td>
<td><?php echo unUnit($arecordhighhum).' %'; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($arecordhighhumyear,$arecordhighhummonth,$arecordhighhumday); ?>
</td>
<td><?php langtrans('All'); ?></td>

<td><?php echo unUnit($arecordlowhum). " %"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($arecordlowhumyear,$arecordlowhummonth,$arecordlowhumday); ?>
</td>
</tr>

<tr class="table-top">
<td colspan="2"><?php langtrans('BAROMETER HIGHS'); ?></td>
<td colspan="2"><?php langtrans('BAROMETER LOWS'); ?></td>
</tr>

<tr class="column-light">

<td><?php langtrans('Today'); ?></td>
<td><?php echo unUnit($highbaro). $uomBaro; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($highbarot); ?></td>
<td><?php langtrans('Today'); ?></td>
<td><?php echo unUnit($lowbaro). $uomBaro; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($lowbarot); ?></td>
</tr>
<?php if(isset($minbaroyest) and isset($maxbaroyest)) { ?>
<tr class="column-dark">
<td><?php langtrans('Yest.'); ?></td>
<td><?php echo unUnit($maxbaroyest). $uomBaro; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($maxbaroyestt); ?></td>
<td><?php langtrans('Yest.'); ?></td>
<td><?php echo unUnit($minbaroyest). $uomBaro; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($minbaroyestt); ?></td>
</tr>
<?php } // end both minbaro and minwindchill for yesterday exist ?>

<tr class="column-light">
<td><?php langtrans('Month'); ?>&nbsp;</td>
<td><?php echo unUnit($mrecordhighbaro). $uomBaro; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($mrecordhighbaroyear,$mrecordhighbaromonth,$mrecordhighbaroday); ?></td>
<td><?php langtrans('Month'); ?>&nbsp;</td>
<td><?php echo unUnit($mrecordlowbaro). $uomBaro; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($mrecordlowbaroyear,$mrecordlowbaromonth,$mrecordlowbaroday); ?></td>
</tr>

<tr class="column-dark">
<td><?php langtrans('Year'); ?></td>
<td><?php echo unUnit($yrecordhighbaro). $uomBaro; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($yrecordhighbaroyear,$yrecordhighbaromonth,$yrecordhighbaroday); ?></td>
<td><?php langtrans('Year'); ?></td>
<td><?php echo unUnit($yrecordlowbaro). $uomBaro; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($yrecordlowbaroyear,$yrecordlowbaromonth,$yrecordlowbaroday); ?></td>
</tr>

<tr class="column-light">
<td><?php langtrans('All'); ?></td>
<td><?php echo unUnit($arecordhighbaro). $uomBaro; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($arecordhighbaroyear,$arecordhighbaromonth,$arecordhighbaroday); ?></td>
<td><?php langtrans('All'); ?></td>
<td><?php echo unUnit($arecordlowbaro). $uomBaro; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($arecordlowbaroyear,$arecordlowbaromonth,$arecordlowbaroday); ?></td>
</tr>


<tr class="table-top">
<td colspan="2"><?php langtrans('HEAT INDEX HIGHS'); ?></td>
<td colspan="2"><?php langtrans('WIND CHILL LOWS'); ?></td>
</tr>

<tr class="column-light">
<td><?php langtrans('Today'); ?></td>
<td><?php echo unUnit($maxheatindex). " $uomTemp"; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($maxheatindext); ?></td>
<td><?php langtrans('Today'); ?></td>
<td><?php echo unUnit($minwindch). " $uomTemp"; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($minwindcht); ?></td>
</tr>

<?php if(isset($minbaroyest) and isset($minchillyest)) { ?>
<tr class="column-dark">
<td><?php langtrans('Yest.'); ?></td>
<td><?php echo unUnit($maxheatindexyest). " $uomTemp"; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($maxheatindexyestt); ?></td>
<td><?php langtrans('Yest.'); ?></td>
<td><?php echo unUnit($minchillyest). " $uomTemp"; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($minchillyestt); ?></td>
</tr>
<?php } // end both minbaro and minwindchill for yesterday exist ?>

<tr class="column-light">
<td><?php langtrans('Month'); ?>&nbsp;</td>
<td><?php echo unUnit($mrecordhighhi). " $uomTemp"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($mrecordhighhiyear,$mrecordhighhimonth,$mrecordhighhiday); ?></td>
<td><?php langtrans('Month'); ?>&nbsp;</td>
<td><?php echo unUnit($mrecordlowchill). " $uomTemp"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($mrecordlowchillyear,$mrecordlowchillmonth,$mrecordlowchillday); ?></td>
</tr>

<tr class="column-dark">
<td><?php langtrans('Year'); ?></td>
<td><?php echo unUnit($yrecordhighhi). " $uomTemp"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($yrecordhighhiyear,$yrecordhighhimonth,$yrecordhighhiday); ?></td>
<td><?php langtrans('Year'); ?></td>
<td><?php echo unUnit($yrecordlowchill). " $uomTemp"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($yrecordlowchillyear,$yrecordlowchillmonth,$yrecordlowchillday); ?></td>
</tr>

<tr class="column-light">
<td><?php langtrans('All'); ?></td>
<td><?php echo unUnit($arecordhighhi). " $uomTemp"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($arecordhighhiyear,$arecordhighhimonth,$arecordhighhiday); ?></td>
<td><?php langtrans('All'); ?></td>
<td><?php echo unUnit($arecordlowchill). " $uomTemp"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($arecordlowchillyear,$arecordlowchillmonth,$arecordlowchillday); ?></td>
</tr>


<?php if(false and $haveSolar) { 
####################################
# Following section only valid if station has Solar sensor
?>
<tr class="table-top">
<td colspan="2"><?php langtrans('EVAPOTRANSPIRATION'); ?></td>
<td colspan="2"><?php langtrans('RAIN'); ?></td>
</tr>

<tr class="column-light">
<td><?php langtrans('Today'); ?></td>
<td><?php echo unUnit($VPet) . $uomRain; ?></td>
<td><?php langtrans('Today'); ?></td>
<td><?php echo unUnit($dayrn) . $uomRain; ?></td>
</tr>

<tr class="column-dark">
<td><?php langtrans('Yest.'); ?></td>
<td><?php echo unUnit($yesterdaydaviset) . $uomRain; ?></td>
<td><?php langtrans('Yest.'); ?></td>
<td><?php echo unUnit($yesterdayrain) . $uomRain; ?></td>
</tr>

<tr class="column-light">
<td><?php langtrans('Month'); ?>&nbsp;</td>
<td><?php echo unUnit($VPetmonth) . $uomRain; ?></td>
<td><?php langtrans('Month'); ?>&nbsp;</td>
<td><?php echo unUnit($monthrn) . $uomRain; ?></td>
</tr>
<?php } // end if haveSolar 
#############################
?>
<?php
####################### solar and UV depending on $haveSolar and $haveUV settings
if ($haveSolar or $haveUV) {
?>
<tr class="table-top">
<?php if ($haveSolar) { ?>
<td colspan="2"><?php langtrans('SOLAR HIGHS'); ?></td>
<?php }
    if ($haveUV) { ?>
<td colspan="2"><?php langtrans('UV HIGHS'); ?></td>
<?php } ?>
</tr>

<tr class="column-light">
<?php if ($haveSolar) { ?>
<td><?php langtrans('Today'); ?></td>
<td><?php echo $highsolar; ?> W/m<sup>2</sup> <?php langtrans('at'); ?> <?php echo adjustWDtime($highsolartime); ?></td>
<?php }
    if ($haveUV) { ?>
<td><?php langtrans('Today'); ?></td>
<td><?php echo $highuv; ?> uvi <?php langtrans('at'); ?> <?php echo adjustWDtime($highuvtime); ?></td>
<?php } ?>
</tr>

<tr class="column-dark">
<?php if ($haveSolar and isset($highsolaryest)) { ?>
<td><?php langtrans('Yest.'); ?></td>
<td><?php echo $highsolaryest; ?> W/m<sup>2</sup> <?php langtrans('at'); ?> <?php echo adjustWDtime($highsolaryesttime); ?></td>
<?php }
    if ($haveUV and isset($highuvyest)) { ?>
<td><?php langtrans('Yest.'); ?></td>
<td><?php echo $highuvyest; ?> uvi <?php langtrans('at'); ?> <?php echo adjustWDtime($highuvyesttime); ?></td>
<?php } ?>
</tr>

<tr class="column-light">
<?php if ($haveSolar) { ?>
<td><?php langtrans('Month'); ?></td>
<td><?php echo $mrecordsolar; ?> W/m<sup>2</sup> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($mrecordsolaryear,$mrecordsolarmonth,$mrecordsolarday); ?></td>
<?php }
    if ($haveUV) { ?>
<td><?php langtrans('Month'); ?></td>
<td><?php echo $mrecorduv; ?> uvi <?php langtrans('on'); ?> 
<?php echo formatDateYMD($mrecorduvyear,$mrecorduvmonth,$mrecorduvday); ?></td>
<?php } ?>
</tr>

<tr class="column-dark">
<?php if ($haveSolar) { ?>
<td><?php langtrans('Year'); ?></td>
<td><?php echo $yrecordsolar; ?> W/m<sup>2</sup> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($yrecordsolaryear,$yrecordsolarmonth,$yrecordsolarday); ?></td>
<?php }
    if ($haveUV) { ?>
<td><?php langtrans('Year'); ?></td>
<td><?php echo $yrecorduv; ?> uvi <?php langtrans('on'); ?> 
<?php echo formatDateYMD($yrecorduvyear,$yrecorduvmonth,$yrecorduvday); ?></td>
<?php } ?>
</tr>

<tr class="column-light">
<?php if ($haveSolar) { ?>
<td><?php langtrans('All'); ?></td>
<td><?php echo $arecordsolar; ?> W/m<sup>2</sup> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($arecordsolaryear,$arecordsolarmonth,$arecordsolarday); ?></td>
<?php }
    if ($haveUV) { ?>
<td><?php langtrans('All'); ?></td>
<td><?php echo $arecorduv; ?> uvi <?php langtrans('on'); ?> 
<?php echo formatDateYMD($arecorduvyear,$arecorduvmonth,$arecorduvday); ?></td>
<?php } ?>
</tr>


<tr class="table-top">
<td colspan="2"><?php langtrans('EVAPOTRANSPIRATION'); ?></td>
<td colspan="2">&nbsp;</td>
</tr>

<tr class="column-light">
<td><?php langtrans('last hour'); ?></td>
<td><?php echo unUnit($hourevo) . $uomRain; ?></td>
<td>&nbsp;</td>
<td>&nbsp;</td>
</tr>

<tr class="column-dark">
<td><?php langtrans('Today'); ?></td>
<td><?php echo unUnit($dayevo) . $uomRain; ?></td>
<td>&nbsp;</td>
<td>&nbsp;</td>
</tr>

<tr class="column-light">
<td><?php langtrans('Yest.'); ?></td>
<td><?php echo unUnit($yesterdayevo) . $uomRain; ?></td>
<td>&nbsp;</td>
<td>&nbsp;</td>
</tr>

<tr class="column-dark">
<td><?php langtrans('Month'); ?>&nbsp;</td>
<td><?php echo unUnit($monthevo) . $uomRain; ?></td>
<td>&nbsp;</td>
<td>&nbsp;</td>
</tr>

<tr class="column-light">
<td><?php langtrans('Year'); ?></td>
<td><?php echo unUnit($yearevo) . $uomRain; ?></td>
<td>&nbsp;</td>
<td>&nbsp;</td>
</tr>

<?php
} // end $haveSolar or $haveUV
#################### end conditional Solar and/or UV display
?>

</table>

	  <h1 style="margin: 10px 0;"><?php langtrans('Wind Data'); ?></h1> 

<?php // Tabla de viento reconstruida completamente AGallardo ?>
<table width="99%" cellpadding="0" cellspacing="0" border="0">

<tr class="table-top">
<td colspan="2"><?php langtrans('WIND SPEED'); ?> (avg10m)</td>
<td colspan="2"><?php langtrans('WIND GUST'); ?></td>
</tr>

<tr class="column-light">
<td><?php langtrans('Current'); ?></td>
<td><span class="ajax" id="ajaxwind"><?php echo unUnit($avgspd). " $uomWind"; ?></span> <span class="ajax" id="ajaxwinddiravg2"><?php langtrans($avgwindir); ?> (<?php echo $avgwindirdeg; ?>&deg;)</span></td>
<td><?php langtrans('Current'); ?></td>
<td><span class="ajax" id="ajaxwindact"><?php echo unUnit($actspd). " $uomWind"; ?></span> <span class="ajax" id="ajaxwinddir2"><?php langtrans($dirlabel); ?> (<?php echo $dirdeg; ?>&deg;)</span> (<i>Max10m:</i> <span class="ajax" id="ajaxgust"></span>)</td>
</tr>

<tr class="column-dark">
<td><?php langtrans('last hour'); ?></td>
<td><?php echo unUnit($maxavgspdhr). " $uomWind"; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($hrecordavgwindt); ?> <?php langtrans($havgwindir); ?> (<?php echo $havgwindirdeg; ?>&deg;)</td>
<td><?php langtrans('last hour'); ?></td>
<td><?php echo unUnit($maxgsthr). " $uomWind"; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($hrecordwindgustt); ?></td>
</tr>

<tr class="column-light">
<td><?php langtrans('Today'); ?></td>
<td><?php echo unUnit($maxavgspddy). " $uomWind"; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($drecordavgwindt); ?> <?php langtrans($davgwindir); ?> (<?php echo $davgwindirdeg; ?>&deg;)</td>
<td><?php langtrans('Today'); ?></td>
<td><?php echo unUnit($maxgst). " $uomWind"; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($maxgstt); ?></td>
</tr>

<tr class="column-dark">
<td><?php langtrans('Yest.'); ?></td>
<td><?php echo unUnit($maxavgspdye). " $uomWind"; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($yestrecordavgwindt); ?> <?php langtrans($yestavgwindir); ?> (<?php echo $yestavgwindirdeg; ?>&deg;)</td>
<td><?php langtrans('Yest.'); ?></td>
<td><?php echo unUnit($maxgstye). " $uomWind"; ?> <?php langtrans('at'); ?> <?php echo adjustWDtime($yestrecordwindgustt); ?></td>
</tr>

<tr class="column-light">
<td><?php langtrans('Month'); ?></td>
<td><?php echo unUnit($mrecordavgwind). " $uomWind"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($mrecordavgwindyear,$mrecordavgwindmonth,$mrecordavgwindday); ?> <?php langtrans($mavgwindir); ?> (<?php echo $mavgwindirdeg; ?>&deg;)</td>
<td><?php langtrans('Month'); ?></td>
<td><?php echo unUnit($mrecordhighgust). " $uomWind"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($mrecordhighgustyear,$mrecordhighgustmonth,$mrecordhighgustday); ?></td>
</tr>

<tr class="column-dark">
<td><?php langtrans('Year'); ?></td>
<td><?php echo unUnit($yrecordavgwind). " $uomWind"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($yrecordavgwindyear,$yrecordavgwindmonth,$yrecordavgwindday); ?> <?php langtrans($yavgwindir); ?> (<?php echo $yavgwindirdeg; ?>&deg;)</td>
<td><?php langtrans('Year'); ?></td>
<td><?php echo unUnit($yrecordwindgust). " $uomWind"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($yrecordhighgustyear,$yrecordhighgustmonth,$yrecordhighgustday); ?>
</td>
</tr>

<tr class="column-light">
<td><?php langtrans('All'); ?></td>
<td><?php echo unUnit($arecordavgwind). " $uomWind"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($arecordavgwindyear,$arecordavgwindmonth,$arecordavgwindday); ?> <?php langtrans($aavgwindir); ?> (<?php echo $aavgwindirdeg; ?>&deg;)</td>
<td><?php langtrans('All'); ?></td>
<td><?php echo unUnit($arecordwindgust). " $uomWind"; ?> <?php langtrans('on'); ?> 
<?php echo formatDateYMD($arecordhighgustyear,$arecordhighgustmonth,$arecordhighgustday); ?>
</td>
</tr>

<tr class="table-top">
<td colspan="2">&nbsp;<?php //langtrans('CURRENT'); ?></td>
  <?php if(file_exists($graphImageDir."windrose.png")) { ?>
<td rowspan="10" align="center">
  <img src="<?php echo $graphImageDir; ?>windrose.png" width="300" height="300" alt="Wind direction plot"/>
  <?php } else { ?>
<td colspan="2">&nbsp;
  <?php } // end no windrose.png file 
  ?>
</td>
</tr>
<tr class="column-light">
<td colspan="4" style="text-align:center;"><small><?php langtrans('Trends mods by A Gallardo of meteoalmendralejo.es');?></small></td>
</tr>
</table>
<!-- end of trends-inc.php -->
<?php 
//=========================================================================
// change the hh:mm AM/PM to h:mmam/pm format or format spec by $timeOnlyFormat
function adjustWDtime ( $WDtime ) {
  global $timeOnlyFormat,$DebugMode;
  if ($WDtime == "00:00: AM") { return ''; }
  $t = explode(':',$WDtime);
  if(!isset($t[1])) { return( $WDtime ); }
  if (preg_match('/pm/i',$WDtime)) { $t[0] = $t[0] + 12; }
  if ($t[0] > 23) {$t[0] = 12; }
  if (preg_match('/^12.*am/i',$WDtime)) { $t[0] = 0; }
  $t2 = join(':',$t); // put time back to gether;
  $t2 = preg_replace('/[^\d\:]/is','',$t2); // strip out the am/pm if any
  $r = date($timeOnlyFormat , strtotime($t2));
  if ($DebugMode) {
    $r = "<!-- adjustWDtime WDtime='$WDtime' t2='$t2' -->" . $r;
    $r = '<span style="color: green;">' . $r . '</span>'; 
  }
  return ($r);
}

//=========================================================================
// strip trailing units from a measurement
// i.e. '30.01 in. Hg' becomes '30.01'
function unUnit ($data) {
  global $DebugMode;
  preg_match('/([\d\.\,\+\-]+)/',$data,$t);
   $r = $t[1];
  if ($DebugMode) {
    $r = "<!-- unUnit data='$data' -->" . $r;
    $r = '<span style="color: green;">' . $r . '</span>'; 
  }
  return ($r);
}
//=========================================================================
// adjust WD date to desired format
//
function adjustWDdate ($WDdate) {
  global $timeFormat,$timeOnlyFormat,$dateOnlyFormat,$WDdateMDY,$DebugMode;
  $d = explode('/',$WDdate);
  if(!isset($d[2])) { return($WDdate); }
  if ($d[2] > 70 and $d[2] <= 99) {$d[2] += 1900;} // 2 digit dates 70-99 are 1970-1999
  if ($d[2] < 99) {$d[2] += 2000; } // 2 digit dates (left) are assumed 20xx dates.
  if ($WDdateMDY) {
    $new = sprintf('%04d-%02d-%02d',$d[2],$d[0],$d[1]); //  M/D/YYYY -> YYYY-MM-DD
  } else {
    $new = sprintf('%04d-%02d-%02d',$d[2],$d[1],$d[0]); // D/M/YYYY -> YYYY-MM-DD
  }
  
  $r = date($dateOnlyFormat,strtotime($new));
  if ($DebugMode) {
    $r = "<!-- adjustDate WDdate='$WDdate', WDdateUSA='$WDdateMDY' new='$new' -->" . $r;
    $r = '<span style="color: green;">' . $r . '</span>'; 
  }
  return ($r);
}
//=========================================================================
// formatDate from Y, M, D
//
function formatDateYMD ( $Y, $M, $D) {
  global $timeFormat,$timeOnlyFormat,$dateOnlyFormat,$WDdateMDY,$DebugMode;
  
  $t = mktime(0,0,0,$M,$D,$Y);
  
  $r = date($dateOnlyFormat,$t);
  if ($DebugMode) {
    $r = "<!-- formatDateYMD Y='$Y', M='$M', D='$D' -->" . $r;
    $r = '<span style="color: green;">' . $r . '</span>'; 
  }
  return ($r);

}
//=========================================================================
// format weather record like:
//   '56.1&deg;F  on: Mar 01 2008'
//   '22.5&deg;C  on: 01 Mar 2008'
//   to using the uom values and date format
//
function reformatRecord ( $record ) {
  global $uomTemp,$timeFormat,$timeOnlyFormat,$dateOnlyFormat,$WDdateMDY,$DebugMode;
// old:  preg_match('|(.*?)\&deg;(.*)\s+on\:\s+(\S+) (\S+) (\S+)|is',$record,$vals);
  preg_match('|([\d\,\.\-]+)[\&deg;]*(.*)\s+on\:\s+(\S+) (\S+) (\S+)|is',$record,$vals);
/*
    [0] => 62.3&deg;F  on: Mar 03 2008
    [1] => 62.3
    [2] => F 
    [3] => Mar
    [4] => 03
    [5] => 2008
*/
  $t = '';
  if ($DebugMode) {
    $t = "<!-- reformatRecord in='$record' vals\n" . print_r($vals,true) . " -->\n";
  }
  $d = $vals[3] . ' ' . $vals[4] . ' ' . $vals[5];
  $d = date($dateOnlyFormat,strtotime($d));
  
  $r = $t . $vals[1] . ' ' . $uomTemp . ' ' . langtransstr('on') . ' ' . $d;
  if ($DebugMode) {
    $r = '<span style="color: green;">' . $r . '</span>'; 
  }
  return ($r);
  
}
// end of MB-trends-inc.php
