<?php
############################################################################
# A Project of TNET Services, Inc. and Saratoga-Weather.org (WD-World template set)
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
#
# Version 1.01 - 02-Dec-2018 - added support for DarkSky forecasts
# Version 1.02 - 23-Jan-2018 - added tabber CSS for DarkSky forecast day/hour display
# Version 1.03 - 11-Apr-2020 - added support for Aerisweather forecasts
# Version 1.04 - 13-May-2020 - Changed WU to WU/TWC (WC-forecast) script support
# Version 1.05 - 18-Jan-2023 - added support for Pirateweather forecasts
# Version 1.06 - 07-Feb-2023 - added support for OpenWeatherMap and VirtualCrossing forecasts
# Version 1.07 - 09-Feb-2023 - fixes for ec-forecast alert display
# Version 1.08 - 03-Apr-2023 - removed DarkSky forecast (API shutdown 31-Mar-2023)
# Version 1.09 - 18-May-2023 - added styling for EC 'advisory' alert
############################################################################
#	This document uses Tab 4 Settings
############################################################################
require_once("Settings.php");
require_once("common.php");
############################################################################
$TITLE= $SITE['organ'] . " - Forecast";
$showGizmo = true;  // set to false to exclude the gizmo
include("top.php");
############################################################################
// testing parameters
$fcstby = isset($_REQUEST['fcstby'])?strtoupper($_REQUEST['fcstby']):'';
if ($fcstby == 'NWS') {
$SITE['fcstscript']		= 'advforecast2.php';  // USA-only NWS Forecast script
$SITE['fcstorg']		= 'NWS';    // set to 'NWS' for NOAA NWS
}

if ($fcstby == 'EC' or $SITE['fcstorg'] == 'EC') {

$SITE['fcstscript']   = 'ec-forecast.php';    // Canada forecasts from Environment Canada
$SITE['fcstorg']		= 'EC';    // set to 'EC' for Environment Canada
print '
<style type="text/css">
/* styling for EC alert boxes */ 
.ECwarning a:link,
.ECstatement a:link,
.ECadvisory a:link,
.ECended a:link,
.ECended a:visited
{
	color:white !important;
}
.ECwarning a:hover,
.ECended a:hover {
	color:black !important;
}

.ECwarning a:visited,
.ECstatement a:visited,
.ECadvisory a:visited
{
	color:white !important;
}

.ECwatch a:link,
.ECwatch a:visited
{
	color:black !important;
}

.ECstatement a:hover,
.ECwatch a:hover {
	color:red !important;
}
abbr[title]{cursor:help;}
</style>
';
}
if ($fcstby == 'WU') {

$SITE['fcstscript']	= 'WU-forecast.php';    // Non-USA, Non-Canada Wunderground Forecast Script
$SITE['fcstorg']		= 'WU';    // set to 'WU' for WeatherUnderground
}
if ($fcstby == 'WC') {

$SITE['fcstscript']	= 'WC-forecast.php';    // Non-USA, Non-Canada Wunderground Forecast Script
$SITE['fcstorg']		= 'WU/TWC';    // set to 'WU' for WeatherUnderground
}
if ($fcstby == 'AW') {

$SITE['fcstscript']	= 'AW-forecast.php';    // Non-USA, Non-Canada Aerisweather Forecast Script
$SITE['fcstorg']		= 'Aerisweather';    // set to 'Aerisweather' for Aerisweather.com
}
if ($fcstby == 'WXSIM') {

$SITE['fcstscript']	= 'plaintext-parser.php';    // WXSIM forecast
$SITE['fcstorg']		= 'WXSIM';    // set to 'WXSIM' for WXSIM forecast
}
if ($fcstby == 'PW' or $SITE['fcstorg'] == 'PW' or
		$fcstby == 'OWM' or $SITE['fcstorg'] == 'OWM' or
		$fcstby == 'VC' or $SITE['fcstorg'] == 'VC') {

print '
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
';
}
if ($fcstby == 'PW' or $SITE['fcstorg'] == 'PW') {

$SITE['fcstscript']	= 'PW-forecast.php';    // Pirateweather forecast
$SITE['fcstorg']		= 'PW';    // set to 'PW' for Pirateweather.net forecast
}
if ($fcstby == 'OWM' or $SITE['fcstorg'] == 'OWM') {

$SITE['fcstscript']	= 'OWM-forecast.php';    // OpenWeatherMap forecast
$SITE['fcstorg']		= 'OWM';    // set to 'OWM' for OpenWeatherMap.org forecast
}
if ($fcstby == 'VC' or $SITE['fcstorg'] == 'VC') {

$SITE['fcstscript']	= 'VC-forecast.php';    // OpenWeatherMap forecast
$SITE['fcstorg']		= 'VC';    // set to 'OWM' for OpenWeatherMap.org forecast
}
// end of special testing parms

?>
</head>
<body>
<?php
############################################################################
include("header.php");
############################################################################
include("menubar.php");
############################################################################
?>

<div id="main-copy">
         <?php 
		 $doIncludeNWS = true; // handle advforecast2.php include
		 $doIncludeWC  = true; // handle WU/TWC-forecast include also
		 $doIncludePW  = true; // handle PW-forecast include also
		 $doIncludeAW  = true; // handle AW-forecast include also
		 $doIncludeOWM = true; // handle OWM-forecast include also
		 $doIncludeVC  = true; // handle VC-forecast include also
		 $doInclude	   = true; // handle ec-forecast and WXSIM include also
		 $doPrint	   = true; //  ec-forecast.php setting
		 include_once($SITE['fcstscript']); ?>

</div><!-- end main-copy -->

<?php
############################################################################
include("footer.php");
############################################################################
# End of Page
############################################################################
?>