<?php
############################################################################
# A Project of TNET Services, Inc. and Saratoga-Weather.org (Canada/World-ML template set)
############################################################################
#
#   Project:    Sample Included Website Design
#   Module:     sample.php
#   Purpose:    Sample Page
#   Authors:    Kevin W. Reed <kreed@tnet.com>
#               TNET Services, Inc.
#
# 	Copyright:	(c) 1992-2007 Copyright TNET Services, Inc.
############################################################################
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA
############################################################################
#	This document uses Tab 4 Settings
############################################################################
//Version 1.01 - 9-Aug-2011 - added Meteohub support
//Version 1.02 - 24-Mar-2012 - added WeatherCat support
//Version 1.03 - 03-Jul-2012 - added wview support
//Version 1.04 - 16-Feb-2013 - added Cumulus solar/uv graph displays
//Version 1.05 - 13-May-2020 - added CumulusMX graphs support (requires cumx/ library files)
//Version 1.06 - 14-May-2020 - fixed Notice errata
//Version 1.07 - 14-May-2021 - added WeeWX graphs support
require_once("Settings.php");
require_once("common.php");
############################################################################
$TITLE= $SITE['organ'] . " - " . langtransstr("Trend Graphs");
$showGizmo = true;  // set to false to exclude the gizmo
if(isset($SITE['WXtags']) and file_exists($SITE['WXtags'])) {
	include_once($SITE['WXtags']);
}
if($SITE['WXsoftware'] == 'CU' and substr($wdversion,0,1) !== '1') { 
  $useUTF8 = true; // must use UTF-8 due to embedded degree sign in highchart legends
}
include("top.php");
############################################################################
?>
<?php if($SITE['WXsoftware'] == 'CU' and substr($wdversion,0,1) !== '1') { // Cumulus MX highchart graph styling 
	print "<!-- begin CumulusMX setup -->\n";
?>
<style type="text/css">
    #chartcontainer  {
      min-height: 610px;
      height: 610px;
      margin-top:50px;
      margin-bottom: 10px;
      background-color:white;
    }
    .button {
      -moz-box-shadow:inset 0px 1px 0px 0px #ffffff;
      -webkit-box-shadow:inset 0px 1px 0px 0px #ffffff;
      box-shadow:inset 0px 1px 0px 0px #ffffff;
      background:-webkit-gradient( linear, left top, left bottom, color-stop(0.05, #f9f9f9), color-stop(1, #e9e9e9) );
      background:-moz-linear-gradient( center top, #f9f9f9 5%, #e9e9e9 100% );
      filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#f9f9f9', endColorstr='#e9e9e9');
      background-color:#f9f9f9;
      -webkit-border-top-left-radius:0px;
      -moz-border-radius-topleft:0px;
      border-top-left-radius:0px;
      -webkit-border-top-right-radius:0px;
      -moz-border-radius-topright:0px;
      border-top-right-radius:0px;
      -webkit-border-bottom-right-radius:0px;
      -moz-border-radius-bottomright:0px;
      border-bottom-right-radius:0px;
      -webkit-border-bottom-left-radius:0px;
      -moz-border-radius-bottomleft:0px;
      border-bottom-left-radius:0px;
      text-indent:0;
      border:1px solid #dcdcdc;
      display:inline-block;
      color:#666666;
      font-family:Arial;
      font-size:12px;
      font-weight:bold;
      font-style:normal;
      height:40px;
      line-height:40px;
      width:110px;
      text-decoration:none;
      text-align:center;
      text-shadow:1px 1px 0px #ffffff;
    }
    .button:hover {
      background:-webkit-gradient( linear, left top, left bottom, color-stop(0.05, #e9e9e9), color-stop(1, #f9f9f9) );
      background:-moz-linear-gradient( center top, #e9e9e9 5%, #f9f9f9 100% );
      filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#e9e9e9', endColorstr='#f9f9f9');
      background-color:#e9e9e9;
    }
    .button:active {
      position:relative;
      top:1px;
    }
    .td_left {
      width: 25px;
    }
</style>
<?php 
  load_cumx_translate();
	print "<!-- end CumulusMX setup -->\n";
} // end cumulusMX ?>
</head>
<body>
<?php
############################################################################
include("header.php");
############################################################################
include("menubar.php");
############################################################################
$graphImageDir = './';
if(isset($SITE['graphImageDir'])) {$graphImageDir = $SITE['graphImageDir']; }
?>

<div id="main-copy">
  
	<h1><?php langtrans('Weather Trend Graphs'); ?></h1>
    <?php if($SITE['WXsoftware'] <> '') { // have software defined ?> 
    <p><?php print langtrans('Graphs generated by')." ".$SITE['WXsoftwareLongName']." "; ?>
	<?php if(isset($wdversion)) {echo " (".$wdversion.")";} ?></p>
    <?php } else { // software not defined yet ?>
    <p>&nbsp;</p>
    <p>Weather software not specified in configuration.</p>
    <?php } // end software not defined yet ?>
    
<?php if($SITE['WXsoftware'] == 'WL') { // WeatherLink graph names ?>
    <h2><?php echo langtransstr('Temperature') . ' / ' . langtransstr('Humidity'); ?></h2>
	<?php genImageLink('OutsideTempHistory.gif','Temperature'); ?>
    <?php genImageLink('OutsideHumidityHistory.gif','Humidity'); ?>
    <br />
    <?php genImageLink('DewPointHistory.gif','Dew Point'); ?>
    <?php genImageLink('THWHistory.gif','THW Index'); ?>
    <br />
    <?php genImageLink('WindChillHistory.gif','Wind Chill'); ?>
    <?php genImageLink('HeatIndexHistory.gif','Heat Index'); ?>
    <br />
    <h2><?php echo langtransstr('Wind') . ' / ' . langtransstr('Barometer'); ?></h2>
    <?php genImageLink('WindSpeedHistory.gif','Wind Speed'); ?>
    <?php genImageLink('WindDirectionHistory.gif','Wind Direction'); ?>
    <br />
    <?php genImageLink('HiWindSpeedHistory.gif','High Wind Speed'); ?>
    <?php genImageLink('BarometerHistory.gif','Barometer'); ?>
    <br />
    <h2><?php langtrans('Rain'); ?></h2>
    <?php genImageLink('RainHistory.gif','Rain'); ?>
    <?php genImageLink('RainRateHistory.gif','Rain Rate'); ?>
    <br />
    <?php // figure out to display solar and/or UV based on site settings
	if($SITE['SOLAR'] and $SITE['UV']) { // have both sensors ?>
    <h2><?php echo langtransstr('Solar Radiation') . ' / ' . langtransstr('UV Index'); ?></h2>
    <?php genImageLink('SolarRadHistory.gif','Solar Radiation'); ?>
    <?php genImageLink('UVHistory.gif','UV Index'); ?>
    <?php } elseif ($SITE['SOLAR']) { ?>
     <h2><?php echo langtransstr('Solar Radiation'); ?></h2>
    <?php genImageLink('SolarRadHistory.gif','Solar Radiation'); ?>
    <?php } elseif ($SITE['UV']) { ?>
     <h2><?php echo langtransstr('UV Index'); ?></h2>
    <?php genImageLink('UVHistory.gif','UV Index'); ?>
    <?php } // end solar/uv selection ?>
    
<?php } // end WeatherLink graph names    ?>

<?php if($SITE['WXsoftware'] == 'VWS') { // VWS graph names ?>
     <h2><?php echo langtransstr('Temperature') . ' / ' . langtransstr('Humidity'); ?></h2>
    <?php genImageLink('vws742.jpg','Temperature'); ?>
    <?php genImageLink('vws740.jpg','Humidity'); ?>
    <br />
    <?php genImageLink('vws757.jpg','Dew Point'); ?>
    <?php genImageLink('vws2811.jpg','THW Index'); ?>
    <br />
    <?php genImageLink('vws754.jpg','Wind Chill'); ?>
    <?php genImageLink('vws756.jpg','Heat Index'); ?>
    <br />
    <h2><?php echo langtransstr('Wind') . ' / ' . langtransstr('Barometer'); ?></h2>
    <?php genImageLink('vws737.jpg','Wind Speed'); ?>
    <?php genImageLink('vws736.jpg','Wind Direction'); ?>
    <br />
    <?php genImageLink('vws1325.jpg','High Wind Speed'); ?>
    <?php genImageLink('vws758.jpg','Barometer'); ?>
    <br />
    <h2><?php langtrans('Rain'); ?></h2>
    <?php genImageLink('vws744.jpg','Rain'); ?>
    <?php genImageLink('vws859.jpg','Rain Rate'); ?>
    <br />
    <?php // figure out to display solar and/or UV based on site settings
	if($SITE['SOLAR'] and $SITE['UV']) { // have both sensors ?>
    <h2><?php echo langtransstr('Solar Radiation') . ' / ' . langtransstr('UV Index'); ?></h2>
    <?php genImageLink('vws753.jpg','Solar Radiation'); ?>
    <?php genImageLink('vws752.jpg','UV Index'); ?>
    <?php } elseif ($SITE['SOLAR']) { ?>
     <h2><?php echo langtransstr('Solar Radiation'); ?></h2>
    <?php genImageLink('vws753.jpg','Solar Radiation'); ?>
    <?php } elseif ($SITE['UV']) { ?>
     <h2><?php echo langtransstr('UV Index'); ?></h2>
    <?php genImageLink('vws752.jpg','UV Index'); ?>
    <?php } // end solar/uv selection ?>
<?php } // end VWS graph names    ?>

<?php if($SITE['WXsoftware'] == 'CU' and substr($wdversion,0,1) == '1') { // Cumulus V1.9.4 graph names ?>
   <h2><?php echo langtransstr('Temperature') . ' / ' . langtransstr('Humidity'); ?></h2>
  <?php genImageLink('temp.png','Temperature',620,248); ?>
  <br />
  <?php genImageLink('hum.png','Humidity',620,248); ?>
  <br />
  <h2><?php echo langtransstr('Wind') . ' / ' . langtransstr('Barometer'); ?></h2>
  <?php genImageLink('wind.png','Wind Speed',620,248); ?>
  <br />
  <?php genImageLink('windd.png','Wind Direction',620,248); ?>
  <br />
  <?php genImageLink('press.png','Barometer',620,248); ?>
  <br />
  <h2><?php langtrans('Rain'); ?></h2>
  <?php genImageLink('raint.png','Rain',620,248); ?>
  <br />
  <?php genImageLink('rain.png','Rain Rate',620,248); ?>
  <br />
  <?php // figure out to display solar and/or UV based on site settings
   if ($SITE['SOLAR']) { ?>
   <h2><?php echo langtransstr('Solar Radiation'); ?></h2>
  <?php genImageLink('solar.png','Solar Radiation',620,248); 
   } ?>
  <br />
  <?php if ($SITE['UV']) { ?>
   <h2><?php echo langtransstr('UV Index'); ?></h2>
  <?php genImageLink('uv.png','UV Index',620,248); ?>
  <?php } // end solar/uv selection ?>
  <?php if ($SITE['SOLAR']) { ?>
   <h2><?php echo langtransstr('Sunshine Hours'); ?></h2>
  <?php genImageLink('sunshine.png','Sunshine Hours',620,248); ?>
  <?php } // end solar/uv selection ?>
<?php } // end CU graph names    ?>

<?php if($SITE['WXsoftware'] == 'WD') { // Weather-Display graph names ?>
	<h1><?php langtrans('Last 24 Hours'); ?></h1> 
		<?php genImageLink('curr24hourgraph.gif','Last 24 hours',469,555); ?>
	<br /><br />
	<h1><?php langtrans('Last 72 Hours'); ?></h1> 
		<?php genImageLink('curr72hourgraph.gif','Last 72 hours',469,555); ?>
	<br /><br />
	<h1><?php langtrans('Month to Date'); ?></h1> 
		<?php genImageLink('monthtodate.gif','Month to Date',469,555); ?>
<?php } // end WD graph names    ?>

<?php if($SITE['WXsoftware'] == 'MH') { // Meteohub graph names ?>
	<h2><?php echo langtransstr('Temperature') . ' / ' . langtransstr('Dew Point'). ' / ' . langtransstr('Barometer'); ?></h2> 
		<?php genImageLink('tdpb2day.png','Last 48 hours',610,300); ?>
	<br /><br />
	<h2><?php echo langtransstr('Wind') . ' / ' . langtransstr('Rain'); ?></h2> 
		<?php genImageLink('windrain2day.png','Last 48 hours',610,300); ?>

    <?php if(isset($VPsolar) and isset($VPuv) and file_exists($graphImageDir.'soluv2day.png')) { ?>
			<br /><br />
	<h2><?php echo langtransstr('Solar') . ' / ' . langtransstr('UV Index'); ?></h2> 
		<?php genImageLink('soluv2day.png','Last 48 hours',610,300); ?>
    <?php } // solar/uv only if both have sensors present ?>

    <?php if(!isset($VPuv) and isset($VPsolar) and file_exists($graphImageDir.'solhi2day.png')) { ?>
			<br /><br />
	<h2><?php echo langtransstr('Solar') . ' / ' . langtransstr('Heat Index'); ?></h2> 
		<?php genImageLink('solhi2day.png','Last 48 hours',610,300); ?>
    <?php } // only Solar sensor present ?>

    <?php if(!isset($VPsolar) and isset($VPuv) and file_exists($graphImageDir.'uvhi2day.png')) { ?>
			<br /><br />
	<h2><?php echo langtransstr('UV Index') . ' / ' . langtransstr('Heat Index'); ?></h2> 
		<?php genImageLink('uvhi2day.png','Last 48 hours',610,300); ?>
    <?php } // only UV sensor present ?>

<?php } // end MH graph names    ?>

<?php if($SITE['WXsoftware'] == 'WCT') { // WeatherCat graph names
/*
$WX['GRTEMPO'] = 'temperature1.jpg'; // Current outside temperature graph.
$WX['GRDEW'] = 'dewpoint1.jpg'; // Current dew point graph.
$WX['GRHUMO'] = 'rh1.jpg'; // Current outside humidity graph.
$WX['GRWINDCH'] = 'windchill1.jpg'; // Current wind chill graph.
$WX['GRHEATI'] = 'heatindex1.jpg'; // Current heat index graph.
$WX['GRSOLAR'] = 'solarrad1.jpg'; // Current solar radiation graph.
$WX['GRUV'] = 'uv1.jpg'; // Current U.V. graph. Note: There's no U.V. sensor on the station here.
$WX['GRWINDD'] = 'winddirection1.jpg'; // Current wind direction graph.
$WX['GRWINDS'] = 'windspeed1.jpg'; // Current wind speed graph.
$WX['GRPRESSURE'] = 'pressure1.jpg'; // Current barometric pressure graph.
$WX['GRCLOUDB'] = 'cloudbase1.jpg'; // Current calculated cloud base graph.
$WX['GRRAIN1'] = 'precipitation1.jpg'; // Current 1 hour rain rate graph.
$WX['GRRAIN24'] = 'precipitationc1.jpg'; // Current daily rain rate graph.

*/
 ?>
    <h2><?php echo langtransstr('Temperature') . ' / ' . langtransstr('Humidity'); ?></h2>
	<?php genImageLink($WX['GRTEMPO'],'Temperature'); ?>
    <?php genImageLink($WX['GRHUMO'],'Humidity'); ?>
    <br />
    <?php genImageLink($WX['GRWINDCH'],'Wind Chill'); ?>
    <?php genImageLink($WX['GRHEATI'],'Heat Index'); ?>
    <h2><?php echo langtransstr('Dew Point') . ' / ' . langtransstr('Barometer'); ?></h2>
    <?php genImageLink($WX['GRDEW'],'Dew Point'); ?>
    <?php genImageLink($WX['GRPRESSURE'],'Barometer'); ?>
    <br />
    <h2><?php echo langtransstr('Wind'); ?></h2>
    <?php genImageLink($WX['GRWINDS'],'Wind Speed'); ?>
    <?php genImageLink($WX['GRWINDD'],'Wind Direction'); ?>
    <br />
    <h2><?php langtrans('Rain'); ?></h2>
    <?php genImageLink($WX['GRRAIN24'],'Rain'); ?>
    <?php genImageLink($WX['GRRAIN1'],'Rain Rate'); ?>
    <br />
    <?php // figure out to display solar and/or UV based on site settings
	if($SITE['SOLAR'] and $SITE['UV']) { // have both sensors ?>
    <h2><?php echo langtransstr('Solar Radiation') . ' / ' . langtransstr('UV Index'); ?></h2>
    <?php genImageLink($WX['GRSOLAR'],'Solar Radiation'); ?>
    <?php genImageLink($WX['GRUV'],'UV Index'); ?>
    <?php } elseif ($SITE['SOLAR']) { ?>
     <h2><?php echo langtransstr('Solar Radiation'); ?></h2>
    <?php genImageLink($WX['GRSOLAR'],'Solar Radiation'); ?>
    <?php } elseif ($SITE['UV']) { ?>
     <h2><?php echo langtransstr('UV Index'); ?></h2>
    <?php genImageLink($WX['GRUV'],'UV Index'); ?>
    <?php } // end solar/uv selection ?>
    
<?php } // end WeatherCat graph names    ?>

<?php if($SITE['WXsoftware'] == 'WV') { // wview graph names
  $WVgraphDescs = array(
     'd' => 'Daily',
	 'w' => 'Weekly',
	 'm' => 'Monthly',
	 'y' => 'Yearly',
  );
  $WVgraphPeriods = array(
     'd' => '24h',
	 'w' => '7d',
	 'm' => '28d',
	 'y' => '365d',
  );

$WVgraphs = array(
# 24h graphs
'24h-temp' => 'tempdaycomp.png',
'24h-hc' => 'heatchillcomp.png',
'24h-hum' => 'humidday.png',
'24h-baro' => 'baromday.png',
'24h-rain' => 'rainday.png',
'24h-wdir' => 'wdirday.png',
'24h-wsp' => 'wspeeddaycomp.png',
'24h-rad' => 'radiationDay.png',
'24h-uv' => 'UVDay.png',

# 7d graphs
'7d-temp' => 'tempweekcomp.png',
'7d-hc' => 'heatchillweekcomp.png',
'7d-hum' => 'humidweek.png',
'7d-baro' => 'baromweek.png',
'7d-rain' => 'rainweek.png',
'7d-wdir' => 'wdirweek.png',
'7d-wsp' => 'wspeedweekcomp.png',
'7d-rad' => 'radiationWeek.png',
'7d-uv' => 'UVWeek.png',

# 28d graphs
'28d-temp' => 'tempmonthcomp.png',
'28d-hc' => 'heatchillmonthcomp.png',
'28d-hum' => 'humidmonth.png',
'28d-baro' => 'barommonth.png',
'28d-rain' => 'rainmonth.png',
'28d-wdir' => 'wdirmonth.png',
'28d-wsp' => 'wspeedmonthcomp.png',
'28d-rad' => 'radiationMonth.png',
'28d-uv' => 'UVMonth.png',

# 365d graphs
'365d-temp' => 'tempyear.png',
'365d-hum' => 'humidyear.png',
# http://192.168.189.133/weather/wchillyear.png',
# http://192.168.189.133/weather/hindexyear.png',
'365d-hc' => 'hindexyear.png',
'365d-baro' => 'baromyear.png',
'365d-rain' => 'rainyear.png',
# http://192.168.189.133/weather/dewyear.png',
'365d-wsp' => 'wspeedyear.png',
'365d-wdir' => 'wdiryear.png',
# http://192.168.189.133/weather/hiwspeedyear.png',
'365d-rad' => 'radiationYear.png',
'365d-uv' => 'UVYear.png',

);

$WVgraphIdx = 'w';  // default is 7-day set
$WVgraphName = $WVgraphDescs[$WVgraphIdx];
$WVgraphSet =  $WVgraphPeriods[$WVgraphIdx];


if(isset($_REQUEST['graph'])) { // check out selection
  $req = strtolower($_REQUEST['graph']);
  foreach ($WVgraphDescs as $i => $v) {
	  if ($req == $i) {
		$WVgraphIdx = $i;  // default is 7-day set
		$WVgraphName = $WVgraphDescs[$i];
		$WVgraphSet =  $WVgraphPeriods[$i];
		break;  
	  }
  }
}
// generate the links for the available periods to display
print "<p style=\"text-align: center\">";
foreach ($WVgraphDescs as $i => $v) {
	if ($i == $WVgraphIdx) {
		print "<strong>".langtransstr($v)."</strong>&nbsp;&nbsp;";
	} else {
		print "<a href=\"?graph=$i\">".langtransstr($v)."</a>&nbsp;&nbsp;";
	}
}
print "</p>\n";	
 ?>
    <h2><?php echo langtransstr('Temperature') . ' - ' . langtransstr('Dew Point') . ' / ' . langtransstr('Wind Chill') . ' - ' .langtransstr('Heat Index'); ?></h2>
	<?php genImageLink($WVgraphs[$WVgraphSet."-temp"],'Temperature'); ?>
    <?php genImageLink($WVgraphs[$WVgraphSet."-hc"],'Wind Chill'); ?>
    <br />
    <h2><?php echo langtransstr('Humidity') . ' / ' . langtransstr('Barometer'); ?></h2>
    <?php genImageLink($WVgraphs[$WVgraphSet."-hum"],'Humidity'); ?>
    <?php genImageLink($WVgraphs[$WVgraphSet."-baro"],'Barometer'); ?>
    <br />
    <h2><?php echo langtransstr('Wind'); ?></h2>
    <?php genImageLink($WVgraphs[$WVgraphSet."-wsp"],'Wind Speed'); ?>
    <?php genImageLink($WVgraphs[$WVgraphSet."-wdir"],'Wind Direction'); ?>
    <br />
    <h2><?php langtrans('Rain'); ?></h2>
    <?php genImageLink($WVgraphs[$WVgraphSet."-rain"],'Rain'); ?>
    <br />
        <?php // figure out to display solar and/or UV based on site settings
	if($SITE['SOLAR'] and $SITE['UV']) { // have both sensors ?>
    <h2><?php echo langtransstr('Solar Radiation') . ' / ' . langtransstr('UV Index'); ?></h2>
    <?php genImageLink($WVgraphs[$WVgraphSet."-rad"],'Solar Radiation'); ?>
    <?php genImageLink($WVgraphs[$WVgraphSet."-uv"],'UV Index'); ?>
    <?php } elseif ($SITE['SOLAR']) { ?>
     <h2><?php echo langtransstr('Solar Radiation'); ?></h2>
    <?php genImageLink($WVgraphs[$WVgraphSet."-rad"],'Solar Radiation'); ?>
    <?php } elseif ($SITE['UV']) { ?>
     <h2><?php echo langtransstr('UV Index'); ?></h2>
    <?php genImageLink($WVgraphs[$WVgraphSet."-uv"],'UV Index'); ?>
    <?php } // end solar/uv selection ?>

    
<?php } // end wview graph names    ?>

<?php if($SITE['WXsoftware'] == 'WEEWX') { // wview graph names
  $WEEWXgraphDescs = array(
   'd' => 'Daily',
	 'w' => 'Weekly',
	 'm' => 'Monthly',
	 'y' => 'Yearly',
  );
  $WEEWXgraphPeriods = array(
   'd' => '24h',
	 'w' => '7d',
	 'm' => '28d',
	 'y' => '365d',
  );

$WEEWXgraphs = array(
# 24h graphs
'24h-temp' => 'daytempdew.png',
'24h-hc' => 'daytempchill.png',
'24h-hum' => 'dayhumidity.png',
'24h-baro' => 'daybarometer.png',
'24h-rain' => 'dayrain.png',
'24h-wdir' => 'daywinddir.png',
'24h-wsp' => 'daywind.png',
'24h-rad' => 'dayradiation.png',
'24h-uv' => 'dayuv.png',

# 7d graphs
'7d-temp' => 'weektempdew.png',
'7d-hc' => 'weektempchill.png',
'7d-hum' => 'weekhumidity.png',
'7d-baro' => 'weekbarometer.png',
'7d-rain' => 'weekrain.png',
'7d-wdir' => 'weekwinddir.png',
'7d-wsp' => 'weekwind.png',
'7d-rad' => 'weekradiation.png',
'7d-uv' => 'weekuv.png',

# 28d graphs
'28d-temp' => 'monthtempdew.png',
'28d-hc' => 'monthtempchill.png',
'28d-hum' => 'monthhumidity.png',
'28d-baro' => 'monthbarometer.png',
'28d-rain' => 'monthrain.png',
'28d-wdir' => 'monthwinddir.png',
'28d-wsp' => 'monthwind.png',
'28d-rad' => 'monthradiation.png',
'28d-uv' => 'monthuv.png',

# 365d graphs
'365d-temp' => 'yeartempdew.png',
'365d-hum' => 'yearhumidity.png',
# http://192.168.189.133/weather/wchillyear.png',
# http://192.168.189.133/weather/hindexyear.png',
'365d-hc' => 'yeartempchill.png',
'365d-baro' => 'yearbarometer.png',
'365d-rain' => 'yearrain.png',
# http://192.168.189.133/weather/dewyear.png',
'365d-wsp' => 'yearwind.png',
'365d-wdir' => 'yearwinddir.png',
# http://192.168.189.133/weather/hiwspeedyear.png',
'365d-rad' => 'yearradiation.png',
'365d-uv' => 'yearuv.png',

);

$WEEWXgraphIdx = 'w';  // default is 7-day set
$WEEWXgraphName = $WEEWXgraphDescs[$WEEWXgraphIdx];
$WEEWXgraphSet =  $WEEWXgraphPeriods[$WEEWXgraphIdx];


if(isset($_REQUEST['graph'])) { // check out selection
  $req = strtolower($_REQUEST['graph']);
  foreach ($WEEWXgraphDescs as $i => $v) {
	  if ($req == $i) {
		$WEEWXgraphIdx = $i;  // default is 7-day set
		$WEEWXgraphName = $WEEWXgraphDescs[$i];
		$WEEWXgraphSet =  $WEEWXgraphPeriods[$i];
		break;  
	  }
  }
}
// generate the links for the available periods to display
print "<p style=\"text-align: center\">";
foreach ($WEEWXgraphDescs as $i => $v) {
	if ($i == $WEEWXgraphIdx) {
		print "<strong>".langtransstr($v)."</strong>&nbsp;&nbsp;";
	} else {
		print "<a href=\"?graph=$i\">".langtransstr($v)."</a>&nbsp;&nbsp;";
	}
}
print "</p>\n";	
 ?>
    <h2><?php echo langtransstr('Temperature') . ' - ' . langtransstr('Dew Point') . ' / ' . langtransstr('Wind Chill') . ' - ' .langtransstr('Heat Index'); ?></h2>
	<?php genImageLink($WEEWXgraphs[$WEEWXgraphSet."-temp"],'Temperature'); ?>
    <?php genImageLink($WEEWXgraphs[$WEEWXgraphSet."-hc"],'Wind Chill'); ?>
    <br />
    <h2><?php echo langtransstr('Humidity') . ' / ' . langtransstr('Barometer'); ?></h2>
    <?php genImageLink($WEEWXgraphs[$WEEWXgraphSet."-hum"],'Humidity'); ?>
    <?php genImageLink($WEEWXgraphs[$WEEWXgraphSet."-baro"],'Barometer'); ?>
    <br />
    <h2><?php echo langtransstr('Wind'); ?></h2>
    <?php genImageLink($WEEWXgraphs[$WEEWXgraphSet."-wsp"],'Wind Speed'); ?>
    <?php genImageLink($WEEWXgraphs[$WEEWXgraphSet."-wdir"],'Wind Direction'); ?>
    <br />
    <h2><?php langtrans('Rain'); ?></h2>
    <?php genImageLink($WEEWXgraphs[$WEEWXgraphSet."-rain"],'Rain'); ?>
    <br />
        <?php // figure out to display solar and/or UV based on site settings
	if($SITE['SOLAR'] and $SITE['UV']) { // have both sensors ?>
    <h2><?php echo langtransstr('Solar Radiation') . ' / ' . langtransstr('UV Index'); ?></h2>
    <?php genImageLink($WEEWXgraphs[$WEEWXgraphSet."-rad"],'Solar Radiation'); ?>
    <?php genImageLink($WEEWXgraphs[$WEEWXgraphSet."-uv"],'UV Index'); ?>
    <?php } elseif ($SITE['SOLAR']) { ?>
     <h2><?php echo langtransstr('Solar Radiation'); ?></h2>
    <?php genImageLink($WEEWXgraphs[$WEEWXgraphSet."-rad"],'Solar Radiation'); ?>
    <?php } elseif ($SITE['UV']) { ?>
     <h2><?php echo langtransstr('UV Index'); ?></h2>
    <?php genImageLink($WEEWXgraphs[$WEEWXgraphSet."-uv"],'UV Index'); ?>
    <?php } // end solar/uv selection ?>

    
<?php } // end weewx graph names    ?>

<?php if($SITE['WXsoftware'] == 'CU' and substr($wdversion,0,1) !== '1') { // Cumulus MX graph names ?>
<?php if(strpos($SITE['CSSscreen'],'narrow') !== false) {$buttonCount = 5; } else {$buttonCount = 10;} ?>
<table cellpadding="0" cellspacing="0" id="Graph_menu" style="margin: 0 auto">
  <tbody>
    <tr>
      <td colspan="<?php echo $buttonCount; ?>"><?php langtrans('Click on a button to show the corresponding graph below.');?></td>
    </tr>
    <tr>
      <td class="td_left">
        <input name="btnTemp" class="button" tabindex="1" type="button" value="<?php langtrans('Temperature');?>" onClick="changeGraph(&quot;temp&quot;);"/>
      </td>
      <td class="td_left">
        <input name="btnDailyTemp" class="button" tabindex="2" type="button" value="<?php langtrans('Daily Temp');?>" onClick="changeGraph(&quot;dailytemp&quot;);"/>
      </td>
      <td class="td_left">
        <input name="btnPress" class="button" tabindex="3" type="button" value="<?php langtrans('Barometer');?>" onClick="changeGraph(&quot;press&quot;);"/>
      </td>
      <td class="td_left">
        <input name="btnWind" class="button" tabindex="4" type="button" value="<?php langtrans('Wind');?>" onClick="changeGraph(&quot;wind&quot;);"/>
      </td>
      <td class="td_left">
        <input name="btnWindDir" class="button" tabindex="5" type="button" value="<?php langtrans('Wind Direction');?>" onClick="changeGraph(&quot;windDir&quot;);"/>
      </td>
<?php if($buttonCount == 5) { ?>
    </tr>
    <tr>
<?php } // end narrow new row ?>
      <td class="td_left">
        <input name="btnHum" class="button" tabindex="6" type="button" value="<?php langtrans('Humidity');?>" onClick="changeGraph(&quot;humidity&quot;);"/>
      </td>
      <td class="td_left">
        <input name="btnRain" class="button" tabindex="7" type="button" value="<?php langtrans('Rain Today');?>" onClick="changeGraph(&quot;rain&quot;);"/>
      </td>
      <td class="td_left">
        <input name="btnDailyRain" class="button" tabindex="8" type="button" value="<?php langtrans('Daily Rain Totals');?>" onClick="changeGraph(&quot;dailyrain&quot;);"/>
      </td>
<?php if(file_exists('solardata.json')) { ?>
      <td class="td_left">
        <input name="btnSolar" class="button" tabindex="9" type="button" value="<?php langtrans('Solar Radiation');?>" onClick="changeGraph(&quot;solar&quot;);"/>
      </td>
<?php } else { ?>
      <td class="td_left">&nbsp;</td>
<?php } // end if exists solardata.json ?>
<?php if(file_exists('sunhours.json')) { ?>
      <td class="td_left">
        <input name="btnSunHours" class="button" tabindex="10" type="button" value="<?php langtrans('Sunshine Hours');?>" onClick="changeGraph(&quot;sunhours&quot;);"/>
      </td>
<?php } else { ?>
      <td class="td_left">&nbsp;</td>
<?php } // end if exists sunhours.json ?>
    </tr>
  </tbody>
</table>
<div id="chartcontainer"></div>
<p><small><?php langtrans('Graphs generated by'); ?> <a href="https://www.highcharts.com/" >Highcharts.com</a> 
<a href="http://creativecommons.org/licenses/by-nc/3.0/">Creative Commons (CC) Attribution-NonCommercial licence</a></small></p>
<script type="text/javascript" src="cumx/jquery/jquery-latest.min.js"></script>
<script type="text/javascript" src="https://code.highcharts.com/stock/8.0/highstock.js"></script>
<script type="text/javascript" src="https://code.highcharts.com/themes/grid.js"></script>
<script type="text/javascript" src="cumx/cumulusmxcharts.js"></script>
<?php } // end cumulusMX ?>
</div><!-- end main-copy -->

<?php
#--------------------------------------------------------------------------
# function genImageLink
#--------------------------------------------------------------------------

function genImageLink ( $imagename, $alttext, $width=310, $height=200) {
	global $graphImageDir;
	
	if(false and !file_exists($graphImageDir.$imagename)) {
		print "$graphImageDir$imagename not found.";
	} else {
		print "<img src=\"$graphImageDir$imagename\" alt=\"$alttext\" width=\"$width\" height=\"$height\" />\n";
	}

}
#--------------------------------------------------------------------------
# end genImageLink function
#--------------------------------------------------------------------------

#--------------------------------------------------------------------------
# function load_cumx_translate
#--------------------------------------------------------------------------

function load_cumx_translate() {
	$enPhrases = array(
  'Temperature',
	'Dew Point',
	'Feels like',
	'Wind Chill',
	'Heat Index',
	'Indoor',
	'Daily Temperature',
	'Average temperature',
	'Minimum temperature',
	'Maximum temperature',
	'Min',
	'Max',
  'Daily Temp',
  'Barometer',
  'Wind',
	'Gust',
  'Wind Direction',
	'Bearing',
	'Avg Bearing',
  'Humidity',
  'Rain Today',
	'Rain Rate',
	'/hr',
	'Rain',
	'Daily Rain Totals',
	'Today',
  'Solar Radiation',
	'Theoretical Max',
  'UV Index',
  'Sunshine Hours',
	// wind cardinal directions
	'N', 'NNE', 'NE', 'ENE', 
	'E', 'ESE', 'SE', 'SSE', 
	'S', 'SSW', 'SW', 'WSW', 
	'W', 'WNW', 'NW', 'NNW'
  );

  print "<script type=\"text/javascript\">\n";
	print "// <![CDATA[ \n";
	print "// language lookup for cumulusmxgraphs.js\n";
	print "var cumxLang = new Array;\n";
	foreach ($enPhrases as $n => $english) {
		
		print " cumxLang['$english'] = '".str_replace("'","\'",langtransstr($english))."';\n";
	}
	$winds = array(); 
	
	print "// ]]>\n";
	print "</script>\n";
	
}
#--------------------------------------------------------------------------
# function load_cumx_translate
#--------------------------------------------------------------------------


############################################################################
include("footer.php");
############################################################################
# End of Page
############################################################################
?>