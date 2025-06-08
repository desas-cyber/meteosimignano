<?php
/*
 File: MB-defs.php

 Purpose: provide a bridge to naming of weather variables from the native Meteobridge software package to
          Weather-Display names used in common scripts like ajax-dashboard.php and ajax-gizmo.php

 
 Author: Ken True - webmaster@saratoga-weather.org

 (created by gen-defs.php - V1.09 - 02-Mar-2013)
 Generated on 2013-03-02 11:03:58 PST

//Version MB-defs.php - V1.00 - 08-Mar-2013
//Version MB-defs.php - V1.01 - 09-Mar-2013 - fixes for stations w/o solar and/or UV sensors
//Version MB-defs.php - V1.02 - 17-Mar-2013 - added processing for new Meteobridge system variables
//Version MB-defs.php - V1.03 - 19-Aug-2013 - added Davis forecast text support Meteobridge 1.8(2198)+
//Version MB-defs.php - V1.04 - 08-Feb-2014 - corrected $yearrn to use rain0rain-yearsum variable
//Version MB-defs.php - V1.05 - 28-May-2020 - fix multiple Notice errata for missing data
//Version MB-defs.php - V1.06 - 26-Jan-2022 - fixes for PHP 8.1
//Version MB-defs.php - V1.07 - 20-Jun-2023 - additional variables added by meteoalmendralejo.es for MB-trends-inc
*/
// --------------------------------------------------------------------------

// allow viewing of generated source

if (isset($_REQUEST["sce"]) and strtolower($_REQUEST["sce"]) == "view" ) {
//--self downloader --
   $filenameReal = __FILE__;
   $download_size = filesize($filenameReal);
   header("Pragma: public");
   header("Cache-Control: private");
   header("Cache-Control: no-cache, must-revalidate");
   header("Content-type: text/plain");
   header("Accept-Ranges: bytes");
   header("Content-Length: $download_size");
   header("Connection: close");
   
   readfile($filenameReal);
   exit;
}
error_reporting(E_ALL);
ini_set('display_errors','1');
$WXsoftware = 'MB';  
// this has WD $varnames = $WX['MB-varnames']; equivalents
 
$uomtemp = $WX['uomTemp'];
$uombaro = $WX['uomBaro'];
$uomwind = $WX['uomWind'];
$uomrain = $WX['uomRain'];
$time = $WX['time'];
$date = $WX['date'];
$temperature = $WX['th0temp-act'];
$tempnodp  = round($temperature,0); // calculated value
$humidity = $WX['th0hum-act'];
$dewpt = $WX['th0dew-act'];
$maxtemp = $WX['th0temp-dmax'];
$mintemp = $WX['th0temp-dmin'];
$maxhum = $WX['th0hum-dmax']; // Añadido por AGallardo
$minhum = $WX['th0hum-dmin']; // Añadido por AGallardo
$windch = $WX['wind0chill-act'];
$heatindexact = isset($WX['th0heatindex-act'])?$WX['th0heatindex-act']:'--'; // Añadido por AGallardo
$windchnodp  = round($windch,0); // calculated value
$maxtempyest = $WX['th0temp-ydmax'];
$mintempyest = $WX['th0temp-ydmin'];
$maxhumyest = $WX['th0hum-ydmax']; // Añadido por AGallardo
$minhumyest = $WX['th0hum-ydmin']; // Añadido por AGallardo
$actspd = $WX['wind0wind-act']; // Añadido por AGallardo
$avgspd = $WX['wind0avgwind-act'];
$gstspd = $WX['wind0wind-hmax'];
$maxgst = $WX['wind0wind-dmax'];
$maxgstye = $WX['wind0wind-ydmax']; // Añadido por AGallardo
$maxgsthr = $WX['wind0wind-hmax'];
$maxavgspdhr = $WX['wind0avgwind-hmax']; // Añadido por AGallardo
$maxavgspddy = $WX['wind0avgwind-dmax']; // Añadido por AGallardo
$maxavgspdye = $WX['wind0avgwind-ydmax']; // Añadido por AGallardo
$dirdeg = $WX['wind0dir-act'];
$dirlabel  = MB_deg2dir($dirdeg); // calculated value
$baro = $WX['thb0seapress-act'];
$dayrn = $WX['rain0total-daysum'];
$monthrn = $WX['rain0total-monthsum'];
$yearrn = $WX['rain0total-yearsum'];
$currentrainratehr = $WX['rain0rate-act'];
$maxrainrate = $WX['rain0rate-dmax'];
$maxrainratehr = $WX['rain0rate-hmax'];
$maxrainrateyest = $WX['rain0rate-ydmax']; // Añadido por AGallardo
$yesterdayrain = $WX['rain0total-ydaysum'];
$mrecordwindgust = $WX['wind0wind-mmax'];
$highbaro = $WX['thb0seapress-dmax'];
$hourrn = $WX['rain0total-sum60'];
$minchillyest = $WX['wind0chill-ydmin'];
$minwindch = $WX['wind0chill-dmin'];
$maxheatindexyest = isset($WX['th0heatindex-ydmax'])?$WX['th0heatindex-ydmax']:'--'; // Añadido por AGallardo
$maxheatindex = isset($WX['th0heatindex-dmax'])?$WX['th0heatindex-dmax']:'--'; // Añadido por AGallardo
$minbaroyest = $WX['thb0seapress-ydmin']; // Añadido por AGallardo
$maxbaroyest = $WX['thb0seapress-ydmax']; // Añadido por AGallardo

// end of generation script

// manual adds

if(MB_isData('mbsystem-swversion')) {
  $wdversiononly = $WX['mbsystem-swversion'];
  $wdbuild = (MB_isData('mbsystem-buildnum'))?$WX['mbsystem-buildnum']:'n/a';
  $wdplatform = (MB_isData('mbsystem-platform'))?$WX['mbsystem-platform']:'';
  $wdversion = $WX['mbsystem-swversion'] . ' build '.$wdbuild. ' '. $wdplatform;
  $wduptime = $WX['mbsystem-uptime']; // Añadido por AGallardo
  if ($wduptime >0) $wduptimetext = floor($wduptime / 86400)." d ".floor(($wduptime % 86400) / 3600)." h ".floor(($wduptime % 3600) / 60)." m"; // Añadido por AGallardo
  $wdlastgooddata = isset($WX['mbsystem-lastgooddata'])?$WX['mbsystem-lastgooddata']:''; // Añadido por AGallardo
  $wdsolarmax = isset($WX['mbsystem-solarmax'])?$WX['mbsystem-solarmax']:'--'; // Añadido por AGallardo
  $Startimedate = $WX['wind0wind-starttime']; // Añadido por AGallardo
  $timeStartimedate = MB_timeOnly($Startimedate); // Añadido por AGallardo
  $dayStartimedate = substr($Startimedate,6,2); // Añadido por AGallardo
  $monthStartimedate = substr($Startimedate,4,2); // Añadido por AGallardo
  $yearStartimedate = substr($Startimedate,0,4); // Añadido por AGallardo
}

$heatidx = MB_calcHeatIndex($temperature,$humidity,$uomtemp);

if(MB_isData('sol0rad-act')) { 
 $VPsolar = $WX['sol0rad-act'];
 if ($wdsolarmax>0) $currentsolarpercent = round(($VPsolar / $wdsolarmax) * 100); // Añadido por AGallardo
 $highsolaryest = $WX['sol0rad-ydmax'];
 $highsolar = $WX['sol0rad-dmax']; // fix this one! max instead of min
 if ($wdsolarmax>0) $highsolarpercent = round(($highsolar / $wdsolarmax) * 100); // Añadido por AGallardo
 $dayevo = isset($WX['sol0evo-daysum'])?$WX['sol0evo-daysum']:'--'; // Añadido por AGallardo
 $yesterdayevo = isset($WX['sol0evo-ydaysum'])?$WX['sol0evo-ydaysum']:'--'; // Añadido por AGallardo
 $hourevo = isset($WX['sol0evo-sum60'])?$WX['sol0evo-sum60']:'--'; // Añadido por AGallardo
 $monthevo = isset($WX['sol0evo-monthsum'])?$WX['sol0evo-monthsum']:'--'; // Añadido por AGallardo
 $yearevo = isset($WX['sol0evo-yearsum'])?$WX['sol0evo-yearsum']:'--'; // Añadido por AGallardo
 
}
if(MB_isData('sol0rad-dmaxtime')) {$highsolartime = MB_timeOnly($WX['sol0rad-dmaxtime']);} 
if(MB_isData('sol0rad-ydmaxtime')) {$highsolaryesttime = MB_timeOnly($WX['sol0rad-ydmaxtime']);}

if(MB_isData('uv0index-act')) {
  $VPuv = $WX['uv0index-act'];
  $highuv = $WX['uv0index-dmax'];
  $highuvyest = $WX['uv0index-ydmax'];
}
if(MB_isData('uv0index-dmaxtime')) {$highuvtime = MB_timeOnly($WX['uv0index-dmaxtime']);}
if(MB_isData('uv0index-ydmaxtime')) {$highuvyesttime = MB_timeOnly($WX['uv0index-ydmaxtime']);}

if(MB_isData('rain0rate-hmaxtime')) {$highrrhtime = MB_timeOnly($WX['rain0rate-hmaxtime']);} // Añadido por AGallardo
if(MB_isData('rain0rate-dmaxtime')) {$highrrtime = MB_timeOnly($WX['rain0rate-dmaxtime']);} // Añadido por AGallardo
if(MB_isData('rain0rate-ydmaxtime')) {$highrryesttime = MB_timeOnly($WX['rain0rate-ydmaxtime']);} // Añadido por AGallardo

if(MB_isData('th0hum-dmaxtime')) {$maxhumt = MB_timeOnly($WX['th0hum-dmaxtime']);} // Añadido por AGallardo
if(MB_isData('th0hum-dmintime')) {$minhumt = MB_timeOnly($WX['th0hum-dmintime']);} // Añadido por AGallardo
if(MB_isData('th0hum-ydmaxtime')) {$maxhumyestt = MB_timeOnly($WX['th0hum-ydmaxtime']);} // Añadido por AGallardo
if(MB_isData('th0hum-ydmintime')) {$minhumyestt = MB_timeOnly($WX['th0hum-ydmintime']);} // Añadido por AGallardo

if(MB_isData('th0temp-dmaxtime')) {$maxtempt = MB_timeOnly($WX['th0temp-dmaxtime']);}
if(MB_isData('th0temp-dmintime')) {$mintempt = MB_timeOnly($WX['th0temp-dmintime']);}
if(MB_isData('th0temp-ydmaxtime')) {$maxtempyestt = MB_timeOnly($WX['th0temp-ydmaxtime']);}
if(MB_isData('th0temp-ydmintime')) {$mintempyestt = MB_timeOnly($WX['th0temp-ydmintime']);}
if(MB_isData('wind0chill-dmintime')) {$minwindcht = MB_timeOnly($WX['wind0chill-dmintime']);}
if(MB_isData('wind0chill-ydmintime')) {$minchillyestt = MB_timeOnly($WX['wind0chill-ydmintime']);} // Añadido por AGallardo
if(MB_isData('th0heatindex-dmaxtime')) {$maxheatindext = MB_timeOnly($WX['th0heatindex-dmaxtime']);} // Añadido por AGallardo
if(MB_isData('th0heatindex-ydmaxtime')) {$maxheatindexyestt = MB_timeOnly($WX['th0heatindex-ydmaxtime']);} // Añadido por AGallardo


if(MB_isData('wind0wind-mmaxtime')) {$mrecordwindgustt = MB_timeOnly($WX['wind0wind-mmaxtime']);}
if(MB_isData('wind0wind-hmaxtime')) {$hrecordwindgustt = MB_timeOnly($WX['wind0wind-hmaxtime']);} // Añadido por AGallardo
if(MB_isData('wind0wind-ydmaxtime')) {$yestrecordwindgustt = MB_timeOnly($WX['wind0wind-ydmaxtime']);} // Añadido por AGallardo

if(MB_isData('wind0avgwind-hmaxtime')) {$hrecordavgwindt = MB_timeOnly($WX['wind0avgwind-hmaxtime']);} // Añadido por AGallardo
if(MB_isData('wind0avgwind-dmaxtime')) {$drecordavgwindt = MB_timeOnly($WX['wind0avgwind-dmaxtime']);} // Añadido por AGallardo
if(MB_isData('wind0avgwind-ydmaxtime')) {$yestrecordavgwindt = MB_timeOnly($WX['wind0avgwind-ydmaxtime']);} // Añadido por AGallardo

if(MB_isData('thb0seapress-dmaxtime')) {$highbarot = MB_timeOnly($WX['thb0seapress-dmaxtime']);}
if(MB_isData('thb0seapress-ydmaxtime')) {$maxbaroyestt = MB_timeOnly($WX['thb0seapress-ydmaxtime']);} // Añadido por AGallardo
if(MB_isData('thb0seapress-ydmintime')) {$minbaroyestt = MB_timeOnly($WX['thb0seapress-ydmintime']);} // Añadido por AGallardo

// change last hour

if(MB_isData('th0temp-val60')) {
	$tempchangehour = $WX['th0temp-act']-$WX['th0temp-val60'];
}

if(MB_isData('th0hum-val60')) {
	$humchangelasthour = $WX['th0hum-act']-$WX['th0hum-val60'];
}

if(MB_isData('th0dew-val60')) {
	$dewchangelasthour = $WX['th0dew-act']-$WX['th0dew-val60'];
}

if(MB_isData('thb0seapress-val60')) {
	$trend = $WX['thb0seapress-act']-$WX['thb0seapress-val60'];
	$pressuretrendname = MB_get_barotrend_text($trend,$uombaro);
} else {
	$pressuretrendname = '--';
}

$lowbaro = $WX['thb0seapress-dmin'];
$lowbarot = MB_timeOnly($WX['thb0seapress-dmintime']);
$maxgstdirectionletter = ' '; // not collected data
$maxgstt = MB_timeOnly($WX['wind0wind-dmaxtime']);

// trends info
$temp0minuteago = $WX['th0temp-act'];
$wind0minuteago = $WX['wind0avgwind-act'];
$dir0minuteagodeg = $WX['wind0dir-act']; // Añadido por AGallardo
$dir0minuteago =  MB_deg2dir($dir0minuteagodeg); // Modificado por AGallardo
$hum0minuteago =  $WX['th0hum-act'];
$dew0minuteago =  $WX['th0dew-act'];
$baro0minuteago = $WX['thb0seapress-act'];
$rain0minuteago = $WX['rain0total-daysum'];
$rainDP = ( preg_match('|mm|i',$uomrain) )?'%01.1F':'%01.2F';
$rainrate0minuteago = $WX['rain0rate-act']; // Añadido por AGallardo

if ( MB_isdata('th0temp-val5') ) { // only set if the variable has valid data
  $temp5minuteago = $WX['th0temp-val5'];
  $wind5minuteago = $WX['wind0avgwind-val5'];
  $dir5minuteagodeg = $WX['wind0dir-val5']; // Añadido por AGallardo
  $dir5minuteago =  MB_deg2dir($dir5minuteagodeg); // Modificado por AGallardo
  $hum5minuteago =  $WX['th0hum-val5'];
  $dew5minuteago =  $WX['th0dew-val5'];
  $baro5minuteago = $WX['thb0seapress-val5'];
  $rain5minuteago = sprintf($rainDP,$WX['rain0total-val5']-$WX['rain0total-ydmin']); // should be dmax!
  $rainrate5minuteago = $WX['rain0rate-val5']; // Añadido por AGallardo
}

if ( MB_isdata('th0temp-val10') ) { // only set if the variable has valid data
  $temp10minuteago = $WX['th0temp-val10'];
  $wind10minuteago = $WX['wind0avgwind-val10'];
  $dir10minuteagodeg = $WX['wind0dir-val10']; // Añadido por AGallardo
  $dir10minuteago =  MB_deg2dir($dir5minuteagodeg); // Modificado por AGallardo
  $hum10minuteago =  $WX['th0hum-val10'];
  $dew10minuteago =  $WX['th0dew-val10'];
  $baro10minuteago = $WX['thb0seapress-val10'];
  $rain10minuteago = sprintf($rainDP,$WX['rain0total-val10']-$WX['rain0total-ydmin']); // should be dmax!
  $rainrate10minuteago = $WX['rain0rate-val10']; // Añadido por AGallardo
}

if ( MB_isdata('th0temp-val15') ) { // only set if the variable has valid data
  $temp15minuteago = $WX['th0temp-val15'];
  $wind15minuteago = $WX['wind0avgwind-val15'];
  $dir15minuteagodeg = $WX['wind0dir-val15']; // Añadido por AGallardo
  $dir15minuteago =  MB_deg2dir($dir5minuteagodeg); // Modificado por AGallardo
  $hum15minuteago =  $WX['th0hum-val15'];
  $dew15minuteago =  $WX['th0dew-val15'];
  $baro15minuteago = $WX['thb0seapress-val15'];
  $rain15minuteago = sprintf($rainDP,$WX['rain0total-val15']-$WX['rain0total-ydmin']); // should be dmax!
  $rainrate15minuteago = $WX['rain0rate-val15']; // Añadido por AGallardo
}

if ( MB_isdata('th0temp-val30') ) { // only set if the variable has valid data
  $temp30minuteago = $WX['th0temp-val30'];
  $wind30minuteago = $WX['wind0avgwind-val30'];
  $dir30minuteagodeg = $WX['wind0dir-val30']; // Añadido por AGallardo
  $dir30minuteago =  MB_deg2dir($dir5minuteagodeg); // Modificado por AGallardo
  $hum30minuteago =  $WX['th0hum-val30'];
  $dew30minuteago =  $WX['th0dew-val30'];
  $baro30minuteago = $WX['thb0seapress-val30'];
  $rain30minuteago = sprintf($rainDP,$WX['rain0total-val30']-$WX['rain0total-ydmin']); // should be dmax!
  $rainrate30minuteago = $WX['rain0rate-val30']; // Añadido por AGallardo
}

if ( MB_isdata('th0temp-val60') ) { // only set if the variable has valid data
  $temp60minuteago = $WX['th0temp-val60'];
  $wind60minuteago = $WX['wind0avgwind-val60'];
  $dir60minuteagodeg = $WX['wind0dir-val60']; // Añadido por AGallardo
  $dir60minuteago =  MB_deg2dir($dir5minuteagodeg); // Modificado por AGallardo
  $hum60minuteago =  $WX['th0hum-val60'];
  $dew60minuteago =  $WX['th0dew-val60'];
  $baro60minuteago = $WX['thb0seapress-val60'];
  $rain60minuteago = sprintf($rainDP,$WX['rain0total-val60']-$WX['rain0total-ydmin']); // should be dmax!
  $rainrate60minuteago = $WX['rain0rate-val60']; // Añadido por AGallardo
}

if ( MB_isdata('th0temp-delta2h') ) { // only set if the variable has valid data por AGallardo
  $temp2hourago = ($temp0minuteago - $WX['th0temp-delta2h']);
  $wind2hourago = ($wind0minuteago - $WX['wind0avgwind-delta2h']);
  $dir2houragodeg = ($dir0minuteagodeg - $WX['wind0dir-delta2h']);
  $dir2hourago =  MB_deg2dir($dir2houragodeg);
  $hum2hourago = ($hum0minuteago - $WX['th0hum-delta2h']);
  $dew2hourago = ($dew0minuteago - $WX['th0dew-delta2h']);
  $baro2hourago = ($baro0minuteago - $WX['thb0seapress-delta2h']);
  $rain2hourago = sprintf($rainDP,($WX['rain0total-act']-$WX['rain0total-delta2h']-$WX['rain0total-ydmin'])); // should be dmax!
  $rainrate2hourago = number_format($rainrate0minuteago - $WX['rain0rate-delta2h'], 1, '.', '');
}

if ( MB_isdata('th0temp-delta3h') ) { // only set if the variable has valid data por AGallardo
  $temp3hourago = ($temp0minuteago - $WX['th0temp-delta3h']);
  $wind3hourago = ($wind0minuteago - $WX['wind0avgwind-delta3h']);
  $dir3houragodeg = ($dir0minuteagodeg - $WX['wind0dir-delta3h']);
  $dir3hourago =  MB_deg2dir($dir3houragodeg);
  $hum3hourago = ($hum0minuteago - $WX['th0hum-delta3h']);
  $dew3hourago = ($dew0minuteago - $WX['th0dew-delta3h']);
  $baro3hourago = ($baro0minuteago - $WX['thb0seapress-delta3h']);
  $rain3hourago = sprintf($rainDP,($WX['rain0total-act']-$WX['rain0total-delta3h']-$WX['rain0total-ydmin'])); // should be dmax!
  $rainrate3hourago = number_format($rainrate0minuteago - $WX['rain0rate-delta3h'], 1, '.', '');
}

if ( MB_isdata('th0temp-delta6h') ) { // only set if the variable has valid data por AGallardo
  $temp6hourago = ($temp0minuteago - $WX['th0temp-delta6h']);
  $wind6hourago = ($wind0minuteago - $WX['wind0avgwind-delta6h']);
  $dir6houragodeg = ($dir0minuteagodeg - $WX['wind0dir-delta6h']);
  $dir6hourago =  MB_deg2dir($dir6houragodeg);
  $hum6hourago = ($hum0minuteago - $WX['th0hum-delta6h']);
  $dew6hourago = ($dew0minuteago - $WX['th0dew-delta6h']);
  $baro6hourago = ($baro0minuteago - $WX['thb0seapress-delta6h']);
  $rain6hourago = sprintf($rainDP,($WX['rain0total-act']-$WX['rain0total-delta6h']-$WX['rain0total-ydmin'])); // should be dmax!
  $rainrate6hourago = number_format($rainrate0minuteago - $WX['rain0rate-delta6h'], 1, '.', '');
}

if ( MB_isdata('th0temp-delta12h') ) { // only set if the variable has valid data por AGallardo
  $temp12hourago = ($temp0minuteago - $WX['th0temp-delta12h']);
  $wind12hourago = ($wind0minuteago - $WX['wind0avgwind-delta12h']);
  $dir12houragodeg = ($dir0minuteagodeg - $WX['wind0dir-delta12h']);
  $dir12hourago =  MB_deg2dir($dir12houragodeg);
  $hum12hourago = ($hum0minuteago - $WX['th0hum-delta12h']);
  $dew12hourago = ($dew0minuteago - $WX['th0dew-delta12h']);
  $baro12hourago = ($baro0minuteago - $WX['thb0seapress-delta12h']);
  $rain12hourago = sprintf($rainDP,($WX['rain0total-act']-$WX['rain0total-delta12h']-$WX['rain0total-ydmin'])); // should be dmax!
  $rainrate12hourago = number_format($rainrate0minuteago - $WX['rain0rate-delta12h'], 1, '.', '');
}

if ( MB_isdata('th0temp-delta24h') ) { // only set if the variable has valid data por AGallardo
  $temp24hourago = ($temp0minuteago - $WX['th0temp-delta24h']);
  $temp24hoursago = $temp24hourago; // Pasamos la variable para utilizarla en el cuadro de mandos
  $wind24hourago = ($wind0minuteago - $WX['wind0avgwind-delta24h']);
  $dir24houragodeg = ($dir0minuteagodeg - $WX['wind0dir-delta24h']);
  $dir24hourago =  MB_deg2dir($dir24houragodeg);
  $hum24hourago = ($hum0minuteago - $WX['th0hum-delta24h']);
  $dew24hourago = ($dew0minuteago - $WX['th0dew-delta24h']);
  $baro24hourago = ($baro0minuteago - $WX['thb0seapress-delta24h']);
  $rain24hourago = sprintf($rainDP,($WX['rain0total-act']-$WX['rain0total-delta24h']-$WX['rain0total-ydmin'])); // should be dmax!
  $rainrate24hourago = number_format($rainrate0minuteago - $WX['rain0rate-delta24h'], 1, '.', '');
}

if ( MB_isdata('th0temp-avg@h48') ) { // only set if the variable has valid data por AGallardo
  $temp2dayago = $WX['th0temp-avg@h48'];
  $wind2dayago = $WX['wind0avgwind-avg@h48'];
  $dir2dayagodeg = $WX['wind0dir-avg@h48'];
  $dir2dayago =  MB_deg2dir($dir2dayagodeg);
  $hum2dayago = $WX['th0hum-avg@h48'];
  $dew2dayago = $WX['th0dew-avg@h48'];
  $baro2dayago = $WX['thb0seapress-avg@h48'];
  $rain2dayago = sprintf($rainDP,($WX['rain0total-avg@h48']-$WX['rain0total-ydmin'])); // should be dmax!
  $rainrate2dayago = $WX['rain0rate-avg@h48']; // Añadido por AGallardo
}

if ( MB_isdata('th0temp-avg@h72') ) { // only set if the variable has valid data por AGallardo
  $temp3dayago = $WX['th0temp-avg@h72'];
  $wind3dayago = $WX['wind0avgwind-avg@h72'];
  $dir3dayagodeg = $WX['wind0dir-avg@h72'];
  $dir3dayago =  MB_deg2dir($dir3dayagodeg);
  $hum3dayago = $WX['th0hum-avg@h72'];
  $dew3dayago = $WX['th0dew-avg@h72'];
  $baro3dayago = $WX['thb0seapress-avg@h72'];
  $rain3dayago = sprintf($rainDP,($WX['rain0total-avg@h72']-$WX['rain0total-ydmin'])); // should be dmax!
  $rainrate3dayago = $WX['rain0rate-avg@h72']; // Añadido por AGallardo
}

if ( MB_isdata('th0temp-avg@h96') ) { // only set if the variable has valid data por AGallardo
  $temp4dayago = $WX['th0temp-avg@h96'];
  $wind4dayago = $WX['wind0avgwind-avg@h96'];
  $dir4dayagodeg = $WX['wind0dir-avg@h96'];
  $dir4dayago =  MB_deg2dir($dir4dayagodeg);
  $hum4dayago = $WX['th0hum-avg@h96'];
  $dew4dayago = $WX['th0dew-avg@h96'];
  $baro4dayago = $WX['thb0seapress-avg@h96'];
  $rain4dayago = sprintf($rainDP,($WX['rain0total-avg@h96']-$WX['rain0total-ydmin'])); // should be dmax!
  $rainrate4dayago = $WX['rain0rate-avg@h96']; // Añadido por AGallardo
}

if ( MB_isdata('th0temp-avg@h120') ) { // only set if the variable has valid data por AGallardo
  $temp5dayago = $WX['th0temp-avg@h120'];
  $wind5dayago = $WX['wind0avgwind-avg@h120'];
  $dir5dayagodeg = $WX['wind0dir-avg@h120'];
  $dir5dayago =  MB_deg2dir($dir5dayagodeg);
  $hum5dayago = $WX['th0hum-avg@h120'];
  $dew5dayago = $WX['th0dew-avg@h120'];
  $baro5dayago = $WX['thb0seapress-avg@h120'];
  $rain5dayago = sprintf($rainDP,($WX['rain0total-avg@h120']-$WX['rain0total-ydmin'])); // should be dmax!
  $rainrate5dayago = $WX['rain0rate-avg@h120']; // Añadido por AGallardo
}

if ( MB_isdata('th0temp-avg@h144') ) { // only set if the variable has valid data por AGallardo
  $temp6dayago = $WX['th0temp-avg@h144'];
  $wind6dayago = $WX['wind0avgwind-avg@h144'];
  $dir6dayagodeg = $WX['wind0dir-avg@h144'];
  $dir6dayago =  MB_deg2dir($dir6dayagodeg);
  $hum6dayago = $WX['th0hum-avg@h144'];
  $dew6dayago = $WX['th0dew-avg@h144'];
  $baro6dayago = $WX['thb0seapress-avg@h144'];
  $rain6dayago = sprintf($rainDP,($WX['rain0total-avg@h144']-$WX['rain0total-ydmin'])); // should be dmax!
  $rainrate6dayago = $WX['rain0rate-avg@h144']; // Añadido por AGallardo
}

if ( MB_isdata('th0temp-avg@h168') ) { // only set if the variable has valid data por AGallardo
  $temp7dayago = $WX['th0temp-avg@h168'];
  $wind7dayago = $WX['wind0avgwind-avg@h168'];
  $dir7dayagodeg = $WX['wind0dir-avg@h168'];
  $dir7dayago =  MB_deg2dir($dir7dayagodeg);
  $hum7dayago = $WX['th0hum-avg@h168'];
  $dew7dayago = $WX['th0dew-avg@h168'];
  $baro7dayago = $WX['thb0seapress-avg@h168'];
  $rain7dayago = sprintf($rainDP,($WX['rain0total-avg@h168']-$WX['rain0total-ydmin'])); // should be dmax!
  $rainrate7dayago = $WX['rain0rate-avg@h168']; // Añadido por AGallardo
}

$mrecordhighbaro = $WX['thb0seapress-mmax'];
$mrecordhighbaroday = substr($WX['thb0seapress-mmaxtime'],6,2);
$mrecordhighbaromonth = substr($WX['thb0seapress-mmaxtime'],4,2);
$mrecordhighbaroyear = substr($WX['thb0seapress-mmaxtime'],0,4);

$mrecordlowbaro = $WX['thb0seapress-mmin'];
$mrecordlowbaroday = substr($WX['thb0seapress-mmintime'],6,2);
$mrecordlowbaromonth = substr($WX['thb0seapress-mmintime'],4,2);
$mrecordlowbaroyear = substr($WX['thb0seapress-mmintime'],0,4);

$mrecordhightemp = $WX['th0temp-mmax'];
$mrecordhightempday = substr($WX['th0temp-mmaxtime'],6,2);
$mrecordhightempmonth = substr($WX['th0temp-mmaxtime'],4,2);
$mrecordhightempyear = substr($WX['th0temp-mmaxtime'],0,4);

$mrecordlowtemp = $WX['th0temp-mmin'];
$mrecordlowtempday = substr($WX['th0temp-mmintime'],6,2);
$mrecordlowtempmonth = substr($WX['th0temp-mmintime'],4,2);
$mrecordlowtempyear = substr($WX['th0temp-mmintime'],0,4);

$mrecordhighhum = $WX['th0hum-mmax']; // Añadido por AGallardo
$mrecordhighhumday = substr($WX['th0hum-mmaxtime'],6,2); // Añadido por AGallardo
$mrecordhighhummonth = substr($WX['th0hum-mmaxtime'],4,2); // Añadido por AGallardo
$mrecordhighhumyear = substr($WX['th0hum-mmaxtime'],0,4); // Añadido por AGallardo

$mrecordlowhum = $WX['th0hum-mmin']; // Añadido por AGallardo
$mrecordlowhumday = substr($WX['th0hum-mmintime'],6,2); // Añadido por AGallardo
$mrecordlowhummonth = substr($WX['th0hum-mmintime'],4,2); // Añadido por AGallardo
$mrecordlowhumyear = substr($WX['th0hum-mmintime'],0,4); // Añadido por AGallardo

$mrecordlowchill = $WX['wind0chill-mmin'];
$mrecordlowchillday = substr($WX['wind0chill-mmintime'],6,2);
$mrecordlowchillmonth = substr($WX['wind0chill-mmintime'],4,2);
$mrecordlowchillyear = substr($WX['wind0chill-mmintime'],0,4);

$mrecordhighhi = isset($WX['th0heatindex-mmax'])?$WX['th0heatindex-mmax']:'--'; // Añadido por AGallardo
if(isset($WX['th0heatindex-mmaxtime'])){
	$mrecordhighhiday = substr($WX['th0heatindex-mmaxtime'],6,2); // Añadido por AGallardo
  $mrecordhighhimonth = substr($WX['th0heatindex-mmaxtime'],4,2); // Añadido por AGallardo
  $mrecordhighhiyear = substr($WX['th0heatindex-mmaxtime'],0,4);  // Añadido por AGallardo
} else {
	$mrecordhighhiday = '01'; // Añadido por AGallardo
  $mrecordhighhimonth = '01'; // Añadido por AGallardo
  $mrecordhighhiyear = '1970';  // Añadido por AGallardo
}
$mrecordhighgust = $WX['wind0wind-mmax'];
$mrecordhighgustday = substr($WX['wind0wind-mmaxtime'],6,2);
$mrecordhighgustmonth = substr($WX['wind0wind-mmaxtime'],4,2);
$mrecordhighgustyear = substr($WX['wind0wind-mmaxtime'],0,4);

$mrecordrainrate = $WX['rain0rate-mmax']; // Añadido por AGallardo
$mrecordrainrateday = substr($WX['rain0rate-mmaxtime'],6,2); // Añadido por AGallardo
$mrecordrainratemonth = substr($WX['rain0rate-mmaxtime'],4,2); // Añadido por AGallardo
$mrecordrainrateyear = substr($WX['rain0rate-mmaxtime'],0,4); // Añadido por AGallardo

$mrecordsolar = $WX['sol0rad-mmax']; // Añadido por AGallardo
$mrecordsolarday = substr($WX['sol0rad-mmaxtime'],6,2); // Añadido por AGallardo
$mrecordsolarmonth = substr($WX['sol0rad-mmaxtime'],4,2); // Añadido por AGallardo
$mrecordsolaryear = substr($WX['sol0rad-mmaxtime'],0,4); // Añadido por AGallardo

$mrecorduv = $WX['uv0index-mmax']; // Añadido por AGallardo
$mrecorduvday = substr($WX['uv0index-mmaxtime'],6,2); // Añadido por AGallardo
$mrecorduvmonth = substr($WX['uv0index-mmaxtime'],4,2); // Añadido por AGallardo
$mrecorduvyear = substr($WX['uv0index-mmaxtime'],0,4); // Añadido por AGallardo

$mrecordavgwind = $WX['wind0avgwind-mmax']; // Añadido por AGallardo
$mrecordavgwindday = substr($WX['wind0avgwind-mmaxtime'],6,2); // Añadido por AGallardo
$mrecordavgwindmonth = substr($WX['wind0avgwind-mmaxtime'],4,2); // Añadido por AGallardo
$mrecordavgwindyear = substr($WX['wind0avgwind-mmaxtime'],0,4); // Añadido por AGallardo


$yrecordhighbaro = $WX['thb0seapress-ymax'];
$yrecordhighbaroday = substr($WX['thb0seapress-ymaxtime'],6,2);
$yrecordhighbaromonth = substr($WX['thb0seapress-ymaxtime'],4,2);
$yrecordhighbaroyear = substr($WX['thb0seapress-ymaxtime'],0,4);

$yrecordlowbaro = $WX['thb0seapress-ymin'];
$yrecordlowbaroday = substr($WX['thb0seapress-ymintime'],6,2);
$yrecordlowbaromonth = substr($WX['thb0seapress-ymintime'],4,2);
$yrecordlowbaroyear = substr($WX['thb0seapress-ymintime'],0,4);

$yrecordhightemp = $WX['th0temp-ymax'];
$yrecordhightempday = substr($WX['th0temp-ymaxtime'],6,2);
$yrecordhightempmonth = substr($WX['th0temp-ymaxtime'],4,2);
$yrecordhightempyear = substr($WX['th0temp-ymaxtime'],0,4);

$yrecordlowtemp = $WX['th0temp-ymin'];
$yrecordlowtempday = substr($WX['th0temp-ymintime'],6,2);
$yrecordlowtempmonth = substr($WX['th0temp-ymintime'],4,2);
$yrecordlowtempyear = substr($WX['th0temp-ymintime'],0,4);

$yrecordhighhum = $WX['th0hum-ymax']; // Añadido por AGallardo
$yrecordhighhumday = substr($WX['th0hum-ymaxtime'],6,2); // Añadido por AGallardo
$yrecordhighhummonth = substr($WX['th0hum-ymaxtime'],4,2); // Añadido por AGallardo
$yrecordhighhumyear = substr($WX['th0hum-ymaxtime'],0,4); // Añadido por AGallardo

$yrecordlowhum = $WX['th0hum-ymin']; // Añadido por AGallardo
$yrecordlowhumday = substr($WX['th0hum-ymintime'],6,2); // Añadido por AGallardo
$yrecordlowhummonth = substr($WX['th0hum-ymintime'],4,2); // Añadido por AGallardo
$yrecordlowhumyear = substr($WX['th0hum-ymintime'],0,4); // Añadido por AGallardo

$yrecordlowchill = isset($WX['wind0chill-ymin'])?$WX['wind0chill-ymin']:'--';
if(isset($WX['wind0chill-ymintime'])){
	$yrecordlowchillday = substr($WX['wind0chill-ymintime'],6,2);
  $yrecordlowchillmonth = substr($WX['wind0chill-ymintime'],4,2);
  $yrecordlowchillyear = substr($WX['wind0chill-ymintime'],0,4);
} else {
	$yrecordlowchillday = '01';
  $yrecordlowchillmonth = '01';
  $yrecordlowchillyear = '1970';
}

$yrecordhighhi = isset($WX['th0heatindex-ymax'])?$WX['th0heatindex-ymax']:'--'; // Añadido por AGallardo
if(isset($WX['th0heatindex-ymaxtime'])) {
	$yrecordhighhiday = substr($WX['th0heatindex-ymaxtime'],6,2); // Añadido por AGallardo
  $yrecordhighhimonth = substr($WX['th0heatindex-ymaxtime'],4,2); // Añadido por AGallardo
  $yrecordhighhiyear = substr($WX['th0heatindex-ymaxtime'],0,4); // Añadido por AGallardo
} else {
	$yrecordhighhiday = '01'; // Añadido por AGallardo
  $yrecordhighhimonth = '01'; // Añadido por AGallardo
  $yrecordhighhiyear = '1970'; // Añadido por AGallardo
}
$yrecordwindgust = $WX['wind0wind-ymax'];
$yrecordhighgustday = substr($WX['wind0wind-ymaxtime'],6,2);
$yrecordhighgustmonth = substr($WX['wind0wind-ymaxtime'],4,2);
$yrecordhighgustyear = substr($WX['wind0wind-ymaxtime'],0,4);

$yrecordrainrate = $WX['rain0rate-ymax']; // Añadido por AGallardo
$yrecordrainrateday = substr($WX['rain0rate-ymaxtime'],6,2); // Añadido por AGallardo
$yrecordrainratemonth = substr($WX['rain0rate-ymaxtime'],4,2); // Añadido por AGallardo
$yrecordrainrateyear = substr($WX['rain0rate-ymaxtime'],0,4); // Añadido por AGallardo

$yrecordsolar = $WX['sol0rad-ymax']; // Añadido por AGallardo
$yrecordsolarday = substr($WX['sol0rad-ymaxtime'],6,2); // Añadido por AGallardo
$yrecordsolarmonth = substr($WX['sol0rad-ymaxtime'],4,2); // Añadido por AGallardo
$yrecordsolaryear = substr($WX['sol0rad-ymaxtime'],0,4); // Añadido por AGallardo

$yrecorduv = $WX['uv0index-ymax']; // Añadido por AGallardo
$yrecorduvday = substr($WX['uv0index-ymaxtime'],6,2); // Añadido por AGallardo
$yrecorduvmonth = substr($WX['uv0index-ymaxtime'],4,2); // Añadido por AGallardo
$yrecorduvyear = substr($WX['uv0index-ymaxtime'],0,4); // Añadido por AGallardo

$yrecordavgwind = $WX['wind0avgwind-ymax']; // Añadido por AGallardo
$yrecordavgwindday = substr($WX['wind0avgwind-ymaxtime'],6,2); // Añadido por AGallardo
$yrecordavgwindmonth = substr($WX['wind0avgwind-ymaxtime'],4,2); // Añadido por AGallardo
$yrecordavgwindyear = substr($WX['wind0avgwind-ymaxtime'],0,4); // Añadido por AGallardo

$arecordhighbaro = $WX['thb0seapress-amax']; // Añadido por AGallardo
$arecordhighbaroday = substr($WX['thb0seapress-amaxtime'],6,2); // Añadido por AGallardo
$arecordhighbaromonth = substr($WX['thb0seapress-amaxtime'],4,2); // Añadido por AGallardo
$arecordhighbaroyear = substr($WX['thb0seapress-amaxtime'],0,4); // Añadido por AGallardo

$arecordlowbaro = $WX['thb0seapress-amin']; // Añadido por AGallardo
$arecordlowbaroday = substr($WX['thb0seapress-amintime'],6,2); // Añadido por AGallardo
$arecordlowbaromonth = substr($WX['thb0seapress-amintime'],4,2); // Añadido por AGallardo
$arecordlowbaroyear = substr($WX['thb0seapress-amintime'],0,4); // Añadido por AGallardo

$arecordhightemp = $WX['th0temp-amax']; // Añadido por AGallardo
$arecordhightempday = substr($WX['th0temp-amaxtime'],6,2); // Añadido por AGallardo
$arecordhightempmonth = substr($WX['th0temp-amaxtime'],4,2); // Añadido por AGallardo
$arecordhightempyear = substr($WX['th0temp-amaxtime'],0,4); // Añadido por AGallardo

$arecordlowtemp = $WX['th0temp-amin']; // Añadido por AGallardo
$arecordlowtempday = substr($WX['th0temp-amintime'],6,2); // Añadido por AGallardo
$arecordlowtempmonth = substr($WX['th0temp-amintime'],4,2); // Añadido por AGallardo
$arecordlowtempyear = substr($WX['th0temp-amintime'],0,4); // Añadido por AGallardo

$arecordhighhum = $WX['th0hum-amax']; // Añadido por AGallardo
$arecordhighhumday = substr($WX['th0hum-amaxtime'],6,2); // Añadido por AGallardo
$arecordhighhummonth = substr($WX['th0hum-amaxtime'],4,2); // Añadido por AGallardo
$arecordhighhumyear = substr($WX['th0hum-amaxtime'],0,4); // Añadido por AGallardo

$arecordlowhum = $WX['th0hum-amin']; // Añadido por AGallardo
$arecordlowhumday = substr($WX['th0hum-amintime'],6,2); // Añadido por AGallardo
$arecordlowhummonth = substr($WX['th0hum-amintime'],4,2); // Añadido por AGallardo
$arecordlowhumyear = substr($WX['th0hum-amintime'],0,4); // Añadido por AGallardo

$arecordlowchill = $WX['wind0chill-amin']; // Añadido por AGallardo
$arecordlowchillday = substr($WX['wind0chill-amintime'],6,2); // Añadido por AGallardo
$arecordlowchillmonth = substr($WX['wind0chill-amintime'],4,2); // Añadido por AGallardo
$arecordlowchillyear = substr($WX['wind0chill-amintime'],0,4); // Añadido por AGallardo

$arecordhighhi = isset($WX['th0heatindex-amax'])?$WX['th0heatindex-amax']:'--'; // Añadido por AGallardo
if(isset($WX['th0heatindex-amaxtime'])){
	$arecordhighhiday = substr($WX['th0heatindex-amaxtime'],6,2); // Añadido por AGallardo
  $arecordhighhimonth = substr($WX['th0heatindex-amaxtime'],4,2); // Añadido por AGallardo
  $arecordhighhiyear = substr($WX['th0heatindex-amaxtime'],0,4); // Añadido por AGallardo
}else {
	$arecordhighhiday   = '01';
	$arecordhighhimonth = '01';
	$arecordhighhiyear  = '1970';
}
$arecordwindgust = $WX['wind0wind-amax']; // Añadido por AGallardo
$arecordhighgustday = substr($WX['wind0wind-amaxtime'],6,2); // Añadido por AGallardo
$arecordhighgustmonth = substr($WX['wind0wind-amaxtime'],4,2); // Añadido por AGallardo
$arecordhighgustyear = substr($WX['wind0wind-amaxtime'],0,4); // Añadido por AGallardo

$arecordrainrate = $WX['rain0rate-amax']; // Añadido por AGallardo
$arecordrainrateday = substr($WX['rain0rate-amaxtime'],6,2); // Añadido por AGallardo
$arecordrainratemonth = substr($WX['rain0rate-amaxtime'],4,2); // Añadido por AGallardo
$arecordrainrateyear = substr($WX['rain0rate-amaxtime'],0,4); // Añadido por AGallardo

$arecordsolar = isset($WX['sol0rad-amax'])?$WX['sol0rad-amax']:'--'; // Añadido por AGallardo
if(isset($WX['sol0rad-amaxtime'])){
	$arecordsolarday = substr($WX['sol0rad-amaxtime'],6,2); // Añadido por AGallardo
  $arecordsolarmonth = substr($WX['sol0rad-amaxtime'],4,2); // Añadido por AGallardo
  $arecordsolaryear = substr($WX['sol0rad-amaxtime'],0,4); // Añadido por AGallardo
} else {
	$arecordsolarday = '01'; // Añadido por AGallardo
  $arecordsolarmonth = '01'; // Añadido por AGallardo
  $arecordsolaryear = '1970'; // Añadido por AGallardo
}
$arecorduv = isset($WX['uv0index-amax'])?$WX['uv0index-amax']:'--'; // Añadido por AGallardo
if(isset($WX['uv0index-amaxtime'])){
	$arecorduvday = substr($WX['uv0index-amaxtime'],6,2); // Añadido por AGallardo
  $arecorduvmonth = substr($WX['uv0index-amaxtime'],4,2); // Añadido por AGallardo
  $arecorduvyear = substr($WX['uv0index-amaxtime'],0,4); // Añadido por AGallardo
} else {
	$arecorduvday = '01'; // Añadido por AGallardo
  $arecorduvmonth = '01'; // Añadido por AGallardo
  $arecorduvyear = '1970'; // Añadido por AGallardo
}
$arecordavgwind = $WX['wind0avgwind-amax']; // Añadido por AGallardo
$arecordavgwindday = substr($WX['wind0avgwind-amaxtime'],6,2); // Añadido por AGallardo
$arecordavgwindmonth = substr($WX['wind0avgwind-amaxtime'],4,2); // Añadido por AGallardo
$arecordavgwindyear = substr($WX['wind0avgwind-amaxtime'],0,4); // Añadido por AGallardo

$avgwindirdeg = isset($WX['wind0dir-avg10'])?$WX['wind0dir-avg10']:'--'; // Añadido por AGallardo
$havgwindirdeg = isset($WX['wind0dir-havg'])?$WX['wind0dir-havg']:'--'; // Añadido por AGallardo
$davgwindirdeg = isset($WX['wind0dir-davg'])?$WX['wind0dir-davg']:'--'; // Añadido por AGallardo
$yestavgwindirdeg = isset($WX['wind0dir-ydavg'])?$WX['wind0dir-ydavg']:'--'; // Añadido por AGallardo
$mavgwindirdeg = isset($WX['wind0dir-mavg'])?$WX['wind0dir-mavg']:'--'; // Añadido por AGallardo
$yavgwindirdeg = isset($WX['wind0dir-yavg'])?$WX['wind0dir-yavg']:'--'; // Añadido por AGallardo
$aavgwindirdeg = isset($WX['wind0dir-aavg'])?$WX['wind0dir-aavg']:'--'; // Añadido por AGallardo

$avgwindir = MB_deg2dir($avgwindirdeg); // Añadido por AGallardo
$havgwindir = MB_deg2dir($havgwindirdeg); // Añadido por AGallardo
$davgwindir = MB_deg2dir($davgwindirdeg); // Añadido por AGallardo
$yestavgwindir = MB_deg2dir($yestavgwindirdeg); // Añadido por AGallardo
$mavgwindir = MB_deg2dir($mavgwindirdeg); // Añadido por AGallardo
$yavgwindir = MB_deg2dir($yavgwindirdeg); // Añadido por AGallardo
$aavgwindir = MB_deg2dir($aavgwindirdeg); // Añadido por AGallardo

if(isset($WX['forecast-text']) and $WX['forecast-text'] <> '') {
	$vpforecasttext = $WX['forecast-text'];
	$vpforecasttextes = isset($WX['forecast-texteshtml'])?$WX['forecast-texteshtml']:$WX['forecast-text']; // Añadido por AGallardo
}


# MB unique functions included from MB-functions-inc.txt 
#-------------------------------------------------------------------------------------
# function processed WD variables
#-------------------------------------------------------------------------------------

global $SITE;

$SITE['commaDecimal'] = strpos($temperature,',') !==false?true:false; // using comma for decimal point?
if(!isset($SITE['WDdateMDY'])) {$WDdateMDY = false;} else {$WDdateMDY = $SITE['WDdateMDY'];}
if(isset($SITE['conditionsMETAR'])) { // override with METAR conditions for text and icon if requested.
	include_once("get-metar-conditions-inc.php");
	list($sunrise,$sunset) = MB_getSuntimes("$date $time",$SITE['latitude'],$SITE['longitude']);
	list($Currentsolardescription,$iconnumber) = mtr_conditions($SITE['conditionsMETAR'], $time, $sunrise, $sunset);
}
# generate the separate date/time variables by dissection of input date/time and format
list($date_year,$date_month,$date_day,$time_hour,$time_minute,$monthname,$dayname)
  = MB_setDateTimes($date,$time,$WDdateMDY);

$beaufortnum =  MB_beaufortNumber($avgspd,$uomwind);
$bftspeedtext = MB_beaufortText($beaufortnum);

list($chandler,$chandlertxt,$chandlerimg) = MB_CBI($temperature,$uomtemp,$humidity);

if(!isset($wdversion) and isset($SITE['WXsoftwareVersion'])) {$wdversion = $SITE['WXsoftwareVersion']; }

$humidex = MB_calcHumidex($temperature,$humidity,$uomtemp); // WD Sample= '61.6°F'

list($feelslike,$heatcolourword) = MB_setFeelslike ($temperature,$windch,$humidex,$uomtemp);

$date = MB_dateOnly($date_year.$date_month.$date_day,$WDdateMDY); // convert YYYYMMDD to DD/MM/YYYY or MM/DD/YYYY

#-------------------------------------------------------------------------------------
# MB support function - MB_getSuntimes($time,$stationlatitude,$stationlongitude);
#-------------------------------------------------------------------------------------
function MB_getSuntimes($stationtime,$stationlatitude,$stationlongitude) {
	
	$tstamp = strtotime($stationtime);
	if(function_exists('date_sun_info')) {
	  $info = date_sun_info($tstamp,$stationlatitude,$stationlongitude);
	  $t = $info['sunrise'] . ' ' . $info['sunset'];
	} else {
	  $t = 'n/a n/a';
	}
	
	return(explode(' ',$t));
	
} // end MB_getSuntimes

#-------------------------------------------------------------------------------------
# MB support function - MB_isdata
#-------------------------------------------------------------------------------------

function MB_isdata( $variable ) {
  global $WX;
  
// see if $WX array has valid data contents
  if(isset($WX[$variable]) and $WX[$variable] !== '--') { return (true); } else { return (false); }	
}


#-------------------------------------------------------------------------------------
# MB support function - MB_calcHumidex
#-------------------------------------------------------------------------------------

function MB_calcHumidex ($temp,$humidity,$useunit) {
// Calculate Humidex from temperature and humidity
// Source of calculation: http://www.physlink.com/reference/weather.cfm	
  global $Debug,$WX;
	if($temp == '--' or $humidity == '--') {return('--'); }
  if(preg_match('|F|i',$WX['uomTemp'])) {
    $T= MB_convertTemp($temp,'C');
  } else {
	$T = $temp;
  }
  $H = $humidity;
  
  $t=7.5*$T/(237.7+$T);
  $et=pow(10,$t);
  $e=6.112*$et*($H/100);
  $humidex=$T+(5/9)*($e-10);
  $Debug .= "<!-- calcHumidex T=$T C, H=$H calc=$humidex ";
  if ($humidex < $T) {
	 $humidex=$T;
     $Debug .= " set to T, ";
  }
  if(preg_match('|F|i',$useunit)) {
     # convert to F
     $humidex = sprintf("%01.1f",round((1.8 * $humidex) + 32.0,1));	  
  }
  $humidex = round($humidex,1);
  $Debug .= " humidex=$humidex $useunit -->\n"; 
  return($humidex);	
}

#-------------------------------------------------------------------------------------
# MB support function - MB_calcHeatIndex
#-------------------------------------------------------------------------------------

function MB_calcHeatIndex ($temp,$humidity,$useunit) {
// Calculate Heat Index from temperature and humidity
// Source of calculation: http://woody.cowpi.com/phpscripts/getwx.php.txt	
  global $Debug;
  if(preg_match('|C|i',$useunit)) {
    $tempF = round(1.8 * $temp + 32,1);
  } else {
	$tempF = round($temp,1);
  }
  $rh = $humidity;
  
  
  // Calculate Heat Index based on temperature in F and relative humidity (65 = 65%)
  if ($tempF > 79 && $rh > 39) {
	  $hiF = -42.379 + 2.04901523 * $tempF + 10.14333127 * $rh - 0.22475541 * $tempF * $rh;
	  $hiF += -0.00683783 * pow($tempF, 2) - 0.05481717 * pow($rh, 2);
	  $hiF += 0.00122874 * pow($tempF, 2) * $rh + 0.00085282 * $tempF * pow($rh, 2);
	  $hiF += -0.00000199 * pow($tempF, 2) * pow($rh, 2);
	  $hiF = round($hiF,1);
	  $hiC = round(($hiF - 32) / 1.8,1);
  } else {
	  $hiF = $tempF;
	  $hiC = round(($hiF - 32) / 1.8,1);
  }
  $Debug .= "<!-- MB_calcHeatIndex temp=$temp ($tempF F) C, rh=$rh calc=$hiF F, $hiC C ";
  if(preg_match('|F|i',$useunit)) {
     $heatIndex = $hiF;	  
  } else {
	 $heatIndex = $hiC;
  }
  $Debug .= " heatIndex=$heatIndex $useunit -->\n"; 
  return($heatIndex);	
}

#-------------------------------------------------------------------------------------
# MB support function - MB_convertTemp
#-------------------------------------------------------------------------------------

function MB_convertTemp ($rawtemp,$useunit) {
	 $dpTemp = 1;
	 if(!is_numeric($rawtemp)) { return($rawtemp); } // no conversions for missing values
	 if (preg_match('|C|i',$useunit))  { // convert F to C
		return( sprintf("%01.{$dpTemp}f",round(($rawtemp-32.0) / 1.8,$dpTemp)));
	 } else {  // leave as F
		return (sprintf("%01.{$dpTemp}f", round($rawtemp*1.0,$dpTemp)));
	 }
}

#-------------------------------------------------------------------------------------
# MB support function - MB_timeOnly
#-------------------------------------------------------------------------------------

function MB_timeOnly ($indatetime) {
// Return HH:MM (24hr time format)
// expecting
// 0....+....1....+
// 20110622061003
// yyyymmddhhMMss
  return(substr($indatetime,8,2).':'.substr($indatetime,10,2));
}

#-------------------------------------------------------------------------------------
# MB support function - MB_dateOnly
#-------------------------------------------------------------------------------------

function MB_dateOnly ($indatetime,$MDY=true) {
// Return dd/mm/yyyy or mm/dd/yyyy
// expecting
// 0....+....1....+
// 20110622061003
// yyyymmddhhMMss
  if($MDY) {
    return(substr($indatetime,4,2).'/'.substr($indatetime,6,2).'/'.substr($indatetime,0,4));
  } else {
    return(substr($indatetime,6,2).'/'.substr($indatetime,4,2).'/'.substr($indatetime,0,4));
  }
}

#-------------------------------------------------------------------------------------
# MB support function - MB_WDrecordDate
#-------------------------------------------------------------------------------------

function MB_WDrecordDate ($indatetime) {
// extract Y, M, D and return as array
// expecting
// 0....+....1....+
// 20110622061003
// yyyymmddhhMMss
  return(array(substr($indatetime,0,4),substr($indatetime,4,2),substr($indatetime,6,2)));
}

#-------------------------------------------------------------------------------------
# MB support function - MB_fixupTime
#-------------------------------------------------------------------------------------

function MB_fixupTime ($intime) {
  global $Debug;
  $tfixed = preg_replace('/^(\S+)\s+(\S+)$/is',"$2",$intime);
  $t = explode(':',$tfixed);
  if (preg_match('/p/i',$tfixed)) { $t[0] = $t[0] + 12; }
  if ($t[0] > 23) {$t[0] = 12; }
  if (preg_match('/^12.*a/i',$tfixed)) { $t[0] = 0; }
  if ($t[0] < '10') {$t[0] = sprintf("%02d",$t[0]); } // leading zero on hour.
  $t2 = join(':',$t); // put time back to gether;
  $t2 = preg_replace('/[^\d\:]/is','',$t2); // strip out the am/pm if any
  $Debug .= "<!-- MB_fixupTime in='$intime' tfixed='$tfixed' out='$t2' -->\n";
  return($t2);
  	
} // end MB_fixupTime

#-------------------------------------------------------------------------------------
# MB support function - MB_setDateTimes
#-------------------------------------------------------------------------------------

function MB_setDateTimes ($indate,$intime,$MDYformat=true) {
// returns: $date_year,$date_month,$date_day,$time_hour,$time_minute,$date_month,$monthname,$dayname
  global $Debug;
  $Debug .= "<!-- MB_setDateTimes date='$indate' time=$intime' MDY=$MDYformat -->\n";
  
  $MBtime = strtotime("$indate $intime");
   
  $MBtime = date('Y m d H i F l',$MBtime);
  $Debug .= "<!-- MB_setDateTimes MBtime='$MBtime' values set -->\n";
  if(isset($_REQUEST['debug'])) {echo $Debug; } 
  return(explode(' ',$MBtime)); // results returned in array for list() assignment
  	
} // end MB_setDateTimes

#-------------------------------------------------------------------------------------
# MB support function - MB_deg2dir - Convert wind direction degrees to cardinal name
#-------------------------------------------------------------------------------------

function MB_deg2dir ($degrees) {
   // figure out a text value for compass direction
// Given the wind direction, return the text label
// for that value.  16 point compass
   $winddir = $degrees;
   if ($winddir == "--") { return($winddir); }

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

} // end function MB_deg2dir


#-------------------------------------------------------------------------------------
# MB support function - MB_beaufortNumber
#-------------------------------------------------------------------------------------

function MB_beaufortNumber ($inWind,$usedunit) {
   global $Debug;
   if($inWind == '--') {return ("0"); } // fix Notice errata on missing data
   $rawwind = $inWind;
// first convert all winds to knots
   if(strpos($inWind,',') !== false) {
	   $rawwind = preg_replace('|,|','.',$inWind);
   }
   $WINDkts = 0.0;
   if       (preg_match('/kts|knot/i',$usedunit)) {
	   $WINDkts = $rawwind * 1.0;
   } elseif (preg_match('/mph/i',$usedunit)) {
	   $WINDkts = $rawwind * 0.8689762;
   } elseif (preg_match('/mps|m\/s/i',$usedunit)) {
	   $WINDkts = $rawwind * 1.94384449;
   } elseif  (preg_match('/kmh|km\/h/i',$usedunit)) {
	   $WINDkts = $rawwind * 0.539956803;
   } else {
	   $Debug .= "<!-- MB_beaufortNumber .. unknown input unit '$usedunit' for wind=$rawwind -->\n";
	   $WINDkts = $rawwind * 1.0;
   }

// return a number for the beaufort scale based on wind in knots
  if ($WINDkts < 1 ) {return(0); }
  if ($WINDkts < 4 ) {return(1); }
  if ($WINDkts < 7 ) {return(2); }
  if ($WINDkts < 11 ) {return(3); }
  if ($WINDkts < 17 ) {return(4); }
  if ($WINDkts < 22 ) {return(5); }
  if ($WINDkts < 28 ) {return(6); }
  if ($WINDkts < 34 ) {return(7); }
  if ($WINDkts < 41 ) {return(8); }
  if ($WINDkts < 48 ) {return(9); }
  if ($WINDkts < 56 ) {return(10); }
  if ($WINDkts < 64 ) {return(11); }
  if ($WINDkts >= 64 ) {return(12); }
  return("0");
} // end MB_beaufortNumber

#-------------------------------------------------------------------------------------
# MB support function - MB_beaufortText
#-------------------------------------------------------------------------------------

function MB_beaufortText ($beaufortnumber) {

  $B = array( /* Beaufort 0 to 12 in English */
   "Calm", "Light air", "Light breeze", "Gentle breeze", "Moderate breeze", "Fresh breeze",
   "Strong breeze", "Near gale", "Gale", "Strong gale", "Storm",
   "Violent storm", "Hurricane"
  );

  if(isset($B[$beaufortnumber])) {
	return $B[$beaufortnumber];
  } else {
    return "Unknown $beaufortnumber Bft";
  }
	
	
} // end MB_beaufortText

#-------------------------------------------------------------------------------------
# MB support function - MB_setFeelslike
#-------------------------------------------------------------------------------------


function MB_setFeelslike ($temp,$windchill,$heatindex,$tempUOM) {
global $Debug;
// establish the feelslike temperature and return a word describing how it feels

$HeatWords = array(
 'Unknown', 'Extreme Heat Danger', 'Heat Danger', 'Extreme Heat Caution', 'Extremely Hot', 'Uncomfortably Hot',
 'Hot', 'Warm', 'Comfortable', 'Cool', 'Cold', 'Uncomfortably Cold', 'Very Cold', 'Extreme Cold' );

// first convert all temperatures to Centigrade if need be
  $TC = $temp;
  $WC = $windchill;
  $HC = $heatindex;
  if($TC == '--' or $WC == '--' or $HC == '--') {return array('--','n/a'); } // fix Notice errata on missing data
  if(strpos($TC,',') !== false) {
	$TC = preg_replace('|,|','.',$temp);
	$WC = preg_replace('|,|','.',$windchill);
	$HC = preg_replace('|,|','.',$heatindex);
  }
  
  if (preg_match('|F|i',$tempUOM))  { // convert F to C if need be
	 $TC = sprintf("%01.1f",round(($TC-32.0) / 1.8,1));
	 $WC = sprintf("%01.1f",round(($WC-32.0) / 1.8,1));
	 $HC = sprintf("%01.1f",round(($HC-32.0) / 1.8,1));
  }
 
 // Feelslike
 
  if ($TC <= 16.0 ) {
	$feelslike = $WC; //use WindChill
  } elseif ($TC >=27.0) {
	$feelslike = $HC; //use HeatIndex
  } else {
	$feelslike = $TC;   // use temperature
  }

  if (preg_match('|F|i',$tempUOM))  { // convert C back to F if need be
	$feelslike = (1.8 * $feelslike) + 32.0;
  }
  $feelslike = round($feelslike,0);

// determine the 'heat color word' to use  
 $hcWord = $HeatWords[0];
 $hcFound = false;
 if ($TC > 32 and $HC > 29) {
	if ($HC > 54 and ! $hcFound) { $hcWord = $HeatWords[1]; $hcFound = true;}
	if ($HC > 45 and ! $hcFound) { $hcWord = $HeatWords[2]; $hcFound = true; }
	if ($HC > 39 and ! $hcFound) { $hcWord = $HeatWords[4]; $hcFound = true; }
	if ($HC > 29 and ! $hcFound) { $hcWord = $HeatWords[6]; $hcFound = true; }
 } elseif ($WC < 16 ) {
	if ($WC < -18 and ! $hcFound) { $hcWord = $HeatWords[13]; $hcFound = true; }
	if ($WC < -9 and ! $hcFound)  { $hcWord = $HeatWords[12]; $hcFound = true; }
	if ($WC < -1 and ! $hcFound)  { $hcWord = $HeatWords[11]; $hcFound = true; }
	if ($WC < 8 and ! $hcFound)   { $hcWord = $HeatWords[10]; $hcFound = true; }
	if ($WC < 16 and ! $hcFound)  { $hcWord = $HeatWords[9]; $hcFound = true; }
 } elseif ($WC >= 16 and $TC <= 32) {
	if ($TC <= 26 and ! $hcFound) { $hcWord = $HeatWords[8]; $hcFound = true; }
	if ($TC <= 32 and ! $hcFound) { $hcWord = $HeatWords[7]; $hcFound = true; }
 }

 if(isset($_REQUEST['debug'])) {
  echo "<!-- MB_setFeelslike input T,WC,HI,U='$temp,$windchill,$heatindex,$tempUOM' cnvt T,WC,HI='$TC,$WC,$HC' feelslike=$feelslike hcWord=$hcWord -->\n";
 }

 return(array($feelslike,$hcWord));
	
} // end of MB_setFeelslike

#-------------------------------------------------------------------------------------
# MB support function - MB_CBI - Chandler Burning Index
#-------------------------------------------------------------------------------------

function MB_CBI($inTemp,$inTempUOM,$inHumidity) {
	// thanks to Chris from sloweather.com for the CBI calculation script
	// modified by Ken True for template usage
	if($inTemp == '--' or $inHumidity == '--') { return(array('--','n/a','')); } // no Notice error on missing data
	preg_match('/([\d\.\,\+\-]+)/',$inTemp,$t); // strip non-numeric from inTemp if any
	$ctemp = $t[1];
	if(strpos($ctemp,',') !== false) {
		$ctemp = preg_replace('|,|','.',$ctemp);
	}
	if(!preg_match('|C|i',$inTempUOM)) {
	  $ctemp = ($ctemp-32.0) / 1.8; // convert from Fahrenheit	
	}
	preg_match('/([\d\.\,\+\-]+)/',$inHumidity,$t); // strip non-numeric from inHumidity if any
	$rh = $t[1];
	if(strpos($rh,',') !== false) {
		$rh = preg_replace('|,|','.',$rh);
	}

	// Start Index Calcs
	
	// Chandler Index
	$cbi = (((110 - 1.373 * $rh) - 0.54 * (10.20 - $ctemp)) * (124 * pow(10,-0.0142 * $rh) ))/60;
	// CBI = (((110 - 1.373*RH) - 0.54 * (10.20 - T)) * (124 * 10**(-0.0142*RH)))/60
	
	//Sort out the Chandler Index
	$cbi = round($cbi,1);
	if ($cbi > "97.5") {
		$cbitxt = "EXTREME";
		$cbiimg= "fdl_extreme.gif";
	
	} elseif ($cbi >="90") {
		$cbitxt = "VERY HIGH";
		$cbiimg= "fdl_vhigh.gif";
	
	} elseif ($cbi >= "75") {
		$cbitxt = "HIGH";
		$cbiimg= "fdl_high.gif";
	
	} elseif ($cbi >= "50") {
		$cbitxt = "MODERATE";
		$cbiimg= "fdl_moderate.gif";
	
	} else {
		$cbitxt="LOW";
		$cbiimg= "fdl_low.gif";
	}
	 $data = array($cbi,$cbitxt,$cbiimg);
	 return $data;
	 
} // end MB_CBI

#-------------------------------------------------------------------------------------
# MB support function - MB_get_barotrend_text
#-------------------------------------------------------------------------------------

function MB_get_barotrend_text($rawpress,$usedunit='hPa') {
  global $Debug;
// routine from Anole's wxsticker PHP (adapted)  
//   Barometric Trend(3 hour)

// Change Rates
// Rapidly: =.06" inHg; 1.5 mm Hg; 2 hPa; 2 mb
// Slowly: =.02" inHg; 0.5 mm Hg; 0.7 hPa; 0.7 mb

// 5 Arrow Positions:
// Rising Rapidly
// Rising Slowly
// Steady
// Falling Slowly
// Falling Rapidly

// Page 52 of the PDF Manual
// http://www.davisnet.com/product_documents/weather/manuals/07395.234-VP2_Manual.pdf

// first convert to hPa for comparisons
	 if (preg_match('/hPa|mb/i',$usedunit)) {
		$btrend = sprintf("%02.1f",round($rawpress * 1.0,1)); // leave in hPa
	 } elseif (preg_match('/mm/i',$usedunit)) {
	   $btrend = sprintf("%02.1f",round($rawpress * 1.333224,1)); 
	 } else { // convert from inHg
		$btrend = sprintf("%02.1f",round($rawpress  * 33.86388158,1));
	 }

   // figure out a text value for barometric pressure trend
   (float)$baromtrend = $btrend;
//   settype($baromtrend, "float");
   switch (TRUE) {
      case (($baromtrend >= -0.6) and ($baromtrend <= 0.6)):
        $baromtrendwords = "Steady";
      break;
      case (($baromtrend > 0.6) and ($baromtrend < 2.0)):
        $baromtrendwords = "Rising Slowly";
      break;
      case ($baromtrend >= 2.0):
        $baromtrendwords = "Rising Rapidly";
      break;
      case (($baromtrend < -0.6) and ($baromtrend > -2.0)):
        $baromtrendwords = "Falling Slowly";
      break;
      case ($baromtrend <= -2.0):
        $baromtrendwords = "Falling Rapidly";
      break;
   } // end switch
   $Debug .= "<!-- MB_get_barotrend_text in=$rawpress $usedunit change out=$btrend hPa change [$baromtrend] ($baromtrendwords) -->\n";
  return($baromtrendwords);
}

#-------------------------------------------------------------------------------------
# end of MB support functions
#-------------------------------------------------------------------------------------

?>