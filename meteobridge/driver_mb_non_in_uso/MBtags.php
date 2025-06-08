<?php
/*
 File: MBtags.php

 Purpose: load Meteobridge variables into a $WX[] array for use with the Canada/World/USA template sets
 NOTE: this file must be processed by Meteobridge as a template file and uploaded to the website
   as MBtags.php using the Meteobridge extended Push Services configuration.

 Author: Ken True - webmaster@saratoga-weather.org

 (created by gen-MBtags.php - V1.05 - 20-Jun-2023)

 These tags generated on 2025-04-11 21:04:55 GMT
   From MBtags-template.txt updated 2025-03-29 21:03:19 GMT

*/
// --------------------------------------------------------------------------

// allow viewing of generated source

if (isset($_REQUEST["sce"]) and strtolower($_REQUEST["sce"]) == "view" ) {
//--self downloader --
   $filenameReal = __FILE__;
   $download_size = filesize($filenameReal);
   $lmod = filemtime(__FILE__);
   header("Last-modified: ".gmdate(DATE_RFC822,$lmod));
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
$WXsoftware = 'MB';  
$defsFile = 'MB-defs.php';  // filename with $varnames = $WX['MB-varnames']; equivalents
 
$rawdatalines = '
date|2025-04-12|// local date:|:
time|12:03:47|// local time:|:
dateUTC|2025-04-12|// UTC date:|:
timeUTC|10:03:47|// UTCtime:|:
uomTemp| &deg;C|// UOM temperature:|:
uomWind| m/s|// UOM wind:|:
uomBaro| hPa|// UOM barometer:|:
uomRain| mm|// UOM rain:|:
mbsystem-swversion|6.1|// Meteobridge version string (example: "1.1"):|:
mbsystem-buildnum|5053|// build number as integer (example: 1673):|:
mbsystem-platform|TL-MR3020V3|// string that specifies hw platform (example: "TL-MR3020"):|:
mbsystem-station|Ecowitt-Custom-Upload|// string that specifies selected weather station (expample: "WMR-200"):|:
mbsystem-language|Italian|// language used on Meteobridge web interface (example: "English"):|:
mbsystem-timezone|Europe/Rome|// defined timezone (example: "Europe/Berlin"):|:
mbsystem-latitude|43.292009|// latitude as float (example: 53.875120):|:
mbsystem-longitude|11.167249|// longitude as float (example: 9.885357):|:
mbsystem-lastgooddata|12|// seconds passed since last piece of meaningfull sensor data recorded, returns -1 if no sensor data recorded so far:|:
mbsystem-uptime|188381|// uptime of Meteobridge in seconds:|:
mbsystem-cpuload1m|0.1|// shows average cpu load during last 1 minute:|:
mbsystem-cpuload5m|0.1|// shows average cpu load during last 5 minutes:|:
mbsystem-cpuload15m|0.1|// shows average cpu load during last 15 minutes:|:
mbsystem-lunarage|14|// days passes since new moon as integer (example: 28):|:
mbsystem-lunarpercent|99.7|// lunarphase given as percentage from 0% (new moon) to 100% (full moon):|:
mbsystem-lunarsegment|4|// lunarphase segment as integer (0 = new moon, 1-3 = growing moon: quarter, half, three quarters, 4 = full moon, 5-7 = shrinking moon: three quarter, half, quarter):|:
mbsystem-daylength|13:17|// length of day (example: "11:28"):|:
mbsystem-civildaylength|14:16|// alternative method for daylength computation (example: "12:38"):|:
mbsystem-nauticaldaylength|15:27|// alternative method for daylength computation (example: "14:00"):|:
mbsystem-sunrise|06:37|// time of sunrise in local time. Can be converted to UTC by applying "=utc" to the variable (example: "06:47", resp. "05:47"):|:
mbsystem-sunset|19:55|// time of sunset in local time. Can be converted to UTC by applying "=utc" to the variable (example: "18:15", resp. "17:15"):|:
mbsystem-civilsunrise|06:08|// alternative computation for sunrise.:|:
mbsystem-civilsunset|20:24|// alternative computation for sunset.:|:
mbsystem-nauticalsunrise|05:33|// alternative computation for sunrise.:|:
mbsystem-nauticalsunset|20:59|// alternative alternative computation for sunset..:|:
mbsystem-daynightflag|D|// returns "D" when there is daylight, otherwise "N".:|:
mbsystem-moonrise|19:39|// time of moonrise in local time. Please notice that not every day has a moonrise time, therefore, variable can be non-existent on certain days (example: "05:46", resp. "04:46"):|:
mbsystem-moonset|06:13|// time of moonset in local time. Please notice that not every day has a moonset time, therefore, variable can be non-existent on certain days.:|:
mbsystem-solarmax|845|// maximum possible solar radiation on that day at that point of the earth. Needs latitude and longitude set and pressure data available.:|:
forecast-rule|-1|// Davis forecast rule number:|:
forecast-text||// Davis forecast reports in English:|:
forecast-texteshtml||// Davis forecast reports in Spanish:|:
th0temp-act|17.7|// outdoor temperature most recent:|:
th0temp-val5|17.6|// outdoor temperature value 5 minutes ago:|:
th0temp-val10|17.8|// outdoor temperature value 10 minutes ago:|:
th0temp-val15|17.5|// outdoor temperature value 15 minutes ago:|:
th0temp-val30|17.2|// outdoor temperature value 30 minutes ago:|:
th0temp-val60|17.1|// outdoor temperature value 60 minutes ago:|:
th0temp-delta2h|2.0|// outdoor temperature delta 2 hours ago:|:
th0temp-delta3h|4.8|// outdoor temperature delta 3 hours ago:|:
th0temp-delta6h|11.3|// outdoor temperature delta 6 hours ago:|:
th0temp-delta12h|10.2|// outdoor temperature delta 12 hours ago:|:
th0temp-delta24h|0.7|// outdoor temperature delta 24 hours ago:|:
th0temp-avg@h48|--|// outdoor temperature avg 48 hours ago:|:
th0temp-avg@h72|--|// outdoor temperature avg 72 hours ago:|:
th0temp-avg@h96|--|// outdoor temperature avg 96 hours ago:|:
th0temp-avg@h120|--|// outdoor temperature avg 120 hours ago:|:
th0temp-avg@h144|--|// outdoor temperature avg 144 hours ago:|:
th0temp-avg@h168|--|// outdoor temperature avg 168 hours ago:|:
th0temp-hmin|17.7|// outdoor temperature min of this hour:|:
th0temp-hmintime|20250412120005|// outdoor temperature timestamp min of this hour:|:
th0temp-hmax|17.7|// outdoor temperature max of this hour:|:
th0temp-hmaxtime|20250412120005|// outdoor temperature timestamp max of this hour:|:
th0temp-dmin|6.1|// outdoor temperature min of today:|:
th0temp-dmintime|20250412051652|// outdoor temperature timestamp min of today:|:
th0temp-dmax|17.8|// outdoor temperature max of today:|:
th0temp-dmaxtime|20250412111834|// outdoor temperature timestamp max of today:|:
th0temp-ydmin|5.1|// outdoor temperature min of yesterday:|:
th0temp-ydmintime|20250411060938|// outdoor temperature timestamp min of yesterday:|:
th0temp-ydmax|20.0|// outdoor temperature max of yesterday:|:
th0temp-ydmaxtime|20250411145054|// outdoor temperature timestamp max of yesterday:|:
th0temp-mmin|4.0|// outdoor temperature min of this month:|:
th0temp-mmintime|20250409030301|// outdoor temperature timestamp min of this month:|:
th0temp-mmax|21.2|// outdoor temperature max of this month:|:
th0temp-mmaxtime|20250404155724|// outdoor temperature timestamp max of this month:|:
th0temp-ymin|-1.1|// outdoor temperature min of this year:|:
th0temp-ymintime|20250216011334|// outdoor temperature timestamp min of this year:|:
th0temp-ymax|21.2|// outdoor temperature max of this year:|:
th0temp-ymaxtime|20250404155724|// outdoor temperature timestamp max of this year:|:
th0temp-amin|-1.1|// outdoor temperature min of all time:|:
th0temp-amintime|20250216011334|// outdoor temperature timestamp min of all time:|:
th0temp-amax|21.2|// outdoor temperature max of all time:|:
th0temp-amaxtime|20250404155724|// outdoor temperature timestamp max of all time:|:
th0temp-starttime|20250216011134|// outdoor temperature timestamp of first recorded data:|:
th0hum-act|55|// outdoor humidity most recent:|:
th0hum-val5|55|// outdoor humidity value 5 minutes ago:|:
th0hum-val10|57|// outdoor humidity value 10 minutes ago:|:
th0hum-val15|56|// outdoor humidity value 15 minutes ago:|:
th0hum-val30|55|// outdoor humidity value 30 minutes ago:|:
th0hum-val60|51|// outdoor humidity value 60 minutes ago:|:
th0hum-delta2h|-2|// outdoor humidity delta 2 hours ago:|:
th0hum-delta3h|-20|// outdoor humidity delta 3 hours ago:|:
th0hum-delta6h|-36|// outdoor humidity delta 6 hours ago:|:
th0hum-delta12h|-35|// outdoor humidity delta 12 hours ago:|:
th0hum-delta24h|-1|// outdoor humidity delta 24 hours ago:|:
th0hum-avg@h48|--|// outdoor humidity avg 48 hours ago:|:
th0hum-avg@h72|--|// outdoor humidity avg 72 hours ago:|:
th0hum-avg@h96|--|// outdoor humidity avg 96 hours ago:|:
th0hum-avg@h120|--|// outdoor humidity avg 120 hours ago:|:
th0hum-avg@h144|--|// outdoor humidity avg 144 hours ago:|:
th0hum-avg@h168|--|// outdoor humidity avg 168 hours ago:|:
th0hum-hmin|55|// outdoor humidity min of this hour:|:
th0hum-hmintime|20250412120235|// outdoor humidity timestamp min of this hour:|:
th0hum-hmax|56|// outdoor humidity max of this hour:|:
th0hum-hmaxtime|20250412120005|// outdoor humidity timestamp max of this hour:|:
th0hum-dmin|48|// outdoor humidity min of today:|:
th0hum-dmintime|20250412111533|// outdoor humidity timestamp min of today:|:
th0hum-dmax|93|// outdoor humidity max of today:|:
th0hum-dmaxtime|20250412020716|// outdoor humidity timestamp max of today:|:
th0hum-ydmin|35|// outdoor humidity min of yesterday:|:
th0hum-ydmintime|20250411153656|// outdoor humidity timestamp min of yesterday:|:
th0hum-ydmax|94|// outdoor humidity max of yesterday:|:
th0hum-ydmaxtime|20250411004358|// outdoor humidity timestamp max of yesterday:|:
th0hum-mmin|32|// outdoor humidity min of this month:|:
th0hum-mmintime|20250404162725|// outdoor humidity timestamp min of this month:|:
th0hum-mmax|97|// outdoor humidity max of this month:|:
th0hum-mmaxtime|20250406064456|// outdoor humidity timestamp max of this month:|:
th0hum-ymin|29|// outdoor humidity min of this year:|:
th0hum-ymintime|20250331174205|// outdoor humidity timestamp min of this year:|:
th0hum-ymax|97|// outdoor humidity max of this year:|:
th0hum-ymaxtime|20250406064456|// outdoor humidity timestamp max of this year:|:
th0hum-amin|29|// outdoor humidity min of all time:|:
th0hum-amintime|20250331174205|// outdoor humidity timestamp min of all time:|:
th0hum-amax|97|// outdoor humidity max of all time:|:
th0hum-amaxtime|20250406064456|// outdoor humidity timestamp max of all time:|:
th0hum-starttime|20250216011134|// outdoor humidity timestamp of first recorded data:|:
th0dew-act|8.5|// outdoor dewpoint most recent:|:
th0dew-val5|8.5|// outdoor dewpoint value 5 minutes ago:|:
th0dew-val10|9.2|// outdoor dewpoint value 10 minutes ago:|:
th0dew-val15|8.6|// outdoor dewpoint value 15 minutes ago:|:
th0dew-val30|8.1|// outdoor dewpoint value 30 minutes ago:|:
th0dew-val60|6.9|// outdoor dewpoint value 60 minutes ago:|:
th0dew-delta2h|1.3|// outdoor dewpoint delta 2 hours ago:|:
th0dew-delta3h|-0.1|// outdoor dewpoint delta 3 hours ago:|:
th0dew-delta6h|3.5|// outdoor dewpoint delta 6 hours ago:|:
th0dew-delta12h|2.5|// outdoor dewpoint delta 12 hours ago:|:
th0dew-delta24h|0.3|// outdoor dewpoint delta 24 hours ago:|:
th0dew-avg@h48|--|// outdoor dewpoint avg 48 hours ago:|:
th0dew-avg@h72|--|// outdoor dewpoint avg 72 hours ago:|:
th0dew-avg@h96|--|// outdoor dewpoint avg 96 hours ago:|:
th0dew-avg@h120|--|// outdoor dewpoint avg 120 hours ago:|:
th0dew-avg@h144|--|// outdoor dewpoint avg 144 hours ago:|:
th0dew-avg@h168|--|// outdoor dewpoint avg 168 hours ago:|:
th0dew-hmin|8.5|// outdoor dewpoint min of this hour:|:
th0dew-hmintime|20250412120235|// outdoor dewpoint timestamp min of this hour:|:
th0dew-hmax|8.8|// outdoor dewpoint max of this hour:|:
th0dew-hmaxtime|20250412120005|// outdoor dewpoint timestamp max of this hour:|:
th0dew-dmin|4.7|// outdoor dewpoint min of today:|:
th0dew-dmintime|20250412051652|// outdoor dewpoint timestamp min of today:|:
th0dew-dmax|9.2|// outdoor dewpoint max of today:|:
th0dew-dmaxtime|20250412115235|// outdoor dewpoint timestamp max of today:|:
th0dew-ydmin|3.1|// outdoor dewpoint min of yesterday:|:
th0dew-ydmintime|20250411154926|// outdoor dewpoint timestamp min of yesterday:|:
th0dew-ydmax|9.0|// outdoor dewpoint max of yesterday:|:
th0dew-ydmaxtime|20250411103516|// outdoor dewpoint timestamp max of yesterday:|:
th0dew-mmin|-2.0|// outdoor dewpoint min of this month:|:
th0dew-mmintime|20250409051206|// outdoor dewpoint timestamp min of this month:|:
th0dew-mmax|10.6|// outdoor dewpoint max of this month:|:
th0dew-mmaxtime|20250406140639|// outdoor dewpoint timestamp max of this month:|:
th0dew-ymin|-3.0|// outdoor dewpoint min of this year:|:
th0dew-ymintime|20250331174935|// outdoor dewpoint timestamp min of this year:|:
th0dew-ymax|10.6|// outdoor dewpoint max of this year:|:
th0dew-ymaxtime|20250406140639|// outdoor dewpoint timestamp max of this year:|:
th0dew-amin|-3.0|// outdoor dewpoint min of all time:|:
th0dew-amintime|20250331174935|// outdoor dewpoint timestamp min of all time:|:
th0dew-amax|10.6|// outdoor dewpoint max of all time:|:
th0dew-amaxtime|20250406140639|// outdoor dewpoint timestamp max of all time:|:
th0dew-starttime|20250216011134|// outdoor dewpoint timestamp of first recorded data:|:
thb0temp-act|16.4|// indoor temperature most recent:|:
thb0temp-val5|16.4|// indoor temperature value 5 minutes ago:|:
thb0temp-val10|16.4|// indoor temperature value 10 minutes ago:|:
thb0temp-val15|16.4|// indoor temperature value 15 minutes ago:|:
thb0temp-val30|16.4|// indoor temperature value 30 minutes ago:|:
thb0temp-val60|16.5|// indoor temperature value 60 minutes ago:|:
thb0temp-hmin|16.4|// indoor temperature min of this hour:|:
thb0temp-hmintime|20250412120035|// indoor temperature timestamp min of this hour:|:
thb0temp-hmax|16.4|// indoor temperature max of this hour:|:
thb0temp-hmaxtime|20250412120035|// indoor temperature timestamp max of this hour:|:
thb0temp-dmin|16.1|// indoor temperature min of today:|:
thb0temp-dmintime|20250412000042|// indoor temperature timestamp min of today:|:
thb0temp-dmax|16.9|// indoor temperature max of today:|:
thb0temp-dmaxtime|20250412075627|// indoor temperature timestamp max of today:|:
thb0temp-ydmin|16.0|// indoor temperature min of yesterday:|:
thb0temp-ydmintime|20250411100415|// indoor temperature timestamp min of yesterday:|:
thb0temp-ydmax|17.1|// indoor temperature max of yesterday:|:
thb0temp-ydmaxtime|20250411020430|// indoor temperature timestamp max of yesterday:|:
thb0temp-mmin|15.7|// indoor temperature min of this month:|:
thb0temp-mmintime|20250401174918|// indoor temperature timestamp min of this month:|:
thb0temp-mmax|19.1|// indoor temperature max of this month:|:
thb0temp-mmaxtime|20250401000001|// indoor temperature timestamp max of this month:|:
thb0temp-ymin|15.4|// indoor temperature min of this year:|:
thb0temp-ymintime|20250321215324|// indoor temperature timestamp min of this year:|:
thb0temp-ymax|19.9|// indoor temperature max of this year:|:
thb0temp-ymaxtime|20250329001509|// indoor temperature timestamp max of this year:|:
thb0temp-amin|15.4|// indoor temperature min of all time:|:
thb0temp-amintime|20250321215324|// indoor temperature timestamp min of all time:|:
thb0temp-amax|19.9|// indoor temperature max of all time:|:
thb0temp-amaxtime|20250329001509|// indoor temperature timestamp max of all time:|:
thb0temp-starttime|20250216011134|// indoor temperature timestamp of first recorded data:|:
thb0hum-act|59|// indoor humidity most recent:|:
thb0hum-val5|59|// indoor humidity value 5 minutes ago:|:
thb0hum-val10|59|// indoor humidity value 10 minutes ago:|:
thb0hum-val15|59|// indoor humidity value 15 minutes ago:|:
thb0hum-val30|59|// indoor humidity value 30 minutes ago:|:
thb0hum-val60|60|// indoor humidity value 60 minutes ago:|:
thb0hum-hmin|59|// indoor humidity min of this hour:|:
thb0hum-hmintime|20250412120035|// indoor humidity timestamp min of this hour:|:
thb0hum-hmax|59|// indoor humidity max of this hour:|:
thb0hum-hmaxtime|20250412120035|// indoor humidity timestamp max of this hour:|:
thb0hum-dmin|57|// indoor humidity min of today:|:
thb0hum-dmintime|20250412020016|// indoor humidity timestamp min of today:|:
thb0hum-dmax|60|// indoor humidity max of today:|:
thb0hum-dmaxtime|20250412093730|// indoor humidity timestamp max of today:|:
thb0hum-ydmin|53|// indoor humidity min of yesterday:|:
thb0hum-ydmintime|20250411153626|// indoor humidity timestamp min of yesterday:|:
thb0hum-ydmax|58|// indoor humidity max of yesterday:|:
thb0hum-ydmaxtime|20250411015830|// indoor humidity timestamp max of yesterday:|:
thb0hum-mmin|41|// indoor humidity min of this month:|:
thb0hum-mmintime|20250405161756|// indoor humidity timestamp min of this month:|:
thb0hum-mmax|62|// indoor humidity max of this month:|:
thb0hum-mmaxtime|20250406140939|// indoor humidity timestamp max of this month:|:
thb0hum-ymin|41|// indoor humidity min of this year:|:
thb0hum-ymintime|20250405161756|// indoor humidity timestamp min of this year:|:
thb0hum-ymax|67|// indoor humidity max of this year:|:
thb0hum-ymaxtime|20250314235328|// indoor humidity timestamp max of this year:|:
thb0hum-amin|41|// indoor humidity min of all time:|:
thb0hum-amintime|20250405161756|// indoor humidity timestamp min of all time:|:
thb0hum-amax|67|// indoor humidity max of all time:|:
thb0hum-amaxtime|20250314235328|// indoor humidity timestamp max of all time:|:
thb0hum-starttime|20250216011134|// indoor humidity timestamp of first recorded data:|:
thb0dew-act|8.4|// indoor dewpoint most recent:|:
thb0dew-val5|8.4|// indoor dewpoint value 5 minutes ago:|:
thb0dew-val10|8.4|// indoor dewpoint value 10 minutes ago:|:
thb0dew-val15|8.4|// indoor dewpoint value 15 minutes ago:|:
thb0dew-val30|8.4|// indoor dewpoint value 30 minutes ago:|:
thb0dew-val60|8.7|// indoor dewpoint value 60 minutes ago:|:
thb0dew-hmin|8.4|// indoor dewpoint min of this hour:|:
thb0dew-hmintime|20250412120035|// indoor dewpoint timestamp min of this hour:|:
thb0dew-hmax|8.4|// indoor dewpoint max of this hour:|:
thb0dew-hmaxtime|20250412120035|// indoor dewpoint timestamp max of this hour:|:
thb0dew-dmin|7.6|// indoor dewpoint min of today:|:
thb0dew-dmintime|20250412020016|// indoor dewpoint timestamp min of today:|:
thb0dew-dmax|8.8|// indoor dewpoint max of today:|:
thb0dew-dmaxtime|20250412082428|// indoor dewpoint timestamp max of today:|:
thb0dew-ydmin|6.5|// indoor dewpoint min of yesterday:|:
thb0dew-ydmintime|20250411153626|// indoor dewpoint timestamp min of yesterday:|:
thb0dew-ydmax|8.6|// indoor dewpoint max of yesterday:|:
thb0dew-ydmaxtime|20250411030632|// indoor dewpoint timestamp max of yesterday:|:
thb0dew-mmin|4.9|// indoor dewpoint min of this month:|:
thb0dew-mmintime|20250405161756|// indoor dewpoint timestamp min of this month:|:
thb0dew-mmax|9.2|// indoor dewpoint max of this month:|:
thb0dew-mmaxtime|20250403025818|// indoor dewpoint timestamp max of this month:|:
thb0dew-ymin|4.9|// indoor dewpoint min of this year:|:
thb0dew-ymintime|20250405161756|// indoor dewpoint timestamp min of this year:|:
thb0dew-ymax|10.9|// indoor dewpoint max of this year:|:
thb0dew-ymaxtime|20250329001509|// indoor dewpoint timestamp max of this year:|:
thb0dew-amin|4.9|// indoor dewpoint min of all time:|:
thb0dew-amintime|20250405161756|// indoor dewpoint timestamp min of all time:|:
thb0dew-amax|10.9|// indoor dewpoint max of all time:|:
thb0dew-amaxtime|20250329001509|// indoor dewpoint timestamp max of all time:|:
thb0dew-starttime|20250216011134|// indoor dewpoint timestamp of first recorded data:|:
thb0press-act|969.3|// station pressure most recent:|:
thb0press-val5|969.3|// station pressure value 5 minutes ago:|:
thb0press-val10|969.4|// station pressure value 10 minutes ago:|:
thb0press-val15|969.5|// station pressure value 15 minutes ago:|:
thb0press-val30|969.5|// station pressure value 30 minutes ago:|:
thb0press-val60|969.3|// station pressure value 60 minutes ago:|:
thb0press-hmin|969.3|// station pressure min of this hour:|:
thb0press-hmintime|20250412120035|// station pressure timestamp min of this hour:|:
thb0press-hmax|969.4|// station pressure max of this hour:|:
thb0press-hmaxtime|20250412120135|// station pressure timestamp max of this hour:|:
thb0press-dmin|967.8|// station pressure min of today:|:
thb0press-dmintime|20250412062424|// station pressure timestamp min of today:|:
thb0press-dmax|970.0|// station pressure max of today:|:
thb0press-dmaxtime|20250412000942|// station pressure timestamp max of today:|:
thb0press-ydmin|969.2|// station pressure min of yesterday:|:
thb0press-ydmintime|20250411184632|// station pressure timestamp min of yesterday:|:
thb0press-ydmax|973.7|// station pressure max of yesterday:|:
thb0press-ydmaxtime|20250411021731|// station pressure timestamp max of yesterday:|:
thb0press-mmin|959.4|// station pressure min of this month:|:
thb0press-mmintime|20250406161743|// station pressure timestamp min of this month:|:
thb0press-mmax|974.6|// station pressure max of this month:|:
thb0press-mmaxtime|20250409012758|// station pressure timestamp max of this month:|:
thb0press-ymin|952.1|// station pressure min of this year:|:
thb0press-ymintime|20250329044017|// station pressure timestamp min of this year:|:
thb0press-ymax|975.7|// station pressure max of this year:|:
thb0press-ymaxtime|20250304235809|// station pressure timestamp max of this year:|:
thb0press-amin|952.1|// station pressure min of all time:|:
thb0press-amintime|20250329044017|// station pressure timestamp min of all time:|:
thb0press-amax|975.7|// station pressure max of all time:|:
thb0press-amaxtime|20250304235809|// station pressure timestamp max of all time:|:
thb0press-starttime|20250216011134|// station pressure timestamp of first recorded data:|:
thb0seapress-act|969.3|// sealevel pressure most recent:|:
thb0seapress-val5|969.3|// sealevel pressure value 5 minutes ago:|:
thb0seapress-val10|969.4|// sealevel pressure value 10 minutes ago:|:
thb0seapress-val15|969.5|// sealevel pressure value 15 minutes ago:|:
thb0seapress-val30|969.5|// sealevel pressure value 30 minutes ago:|:
thb0seapress-val60|969.3|// sealevel pressure value 60 minutes ago:|:
thb0seapress-delta2h|0.0|// sealevel pressure delta 2 hours ago:|:
thb0seapress-delta3h|1.0|// sealevel pressure delta 3 hours ago:|:
thb0seapress-delta6h|1.4|// sealevel pressure delta 6 hours ago:|:
thb0seapress-delta12h|-0.5|// sealevel pressure delta 12 hours ago:|:
thb0seapress-delta24h|-3.1|// sealevel pressure delta 24 hours ago:|:
thb0seapress-avg@h48|--|// sealevel pressure avg 48 hours ago:|:
thb0seapress-avg@h72|--|// sealevel pressure avg 72 hours ago:|:
thb0seapress-avg@h96|--|// sealevel pressure avg 96 hours ago:|:
thb0seapress-avg@h120|--|// sealevel pressure avg 120 hours ago:|:
thb0seapress-avg@h144|--|// sealevel pressure avg 144 hours ago:|:
thb0seapress-avg@h168|--|// sealevel pressure avg 168 hours ago:|:
thb0seapress-hmin|969.3|// sealevel pressure min of this hour:|:
thb0seapress-hmintime|20250412120035|// sealevel pressure timestamp min of this hour:|:
thb0seapress-hmax|969.4|// sealevel pressure max of this hour:|:
thb0seapress-hmaxtime|20250412120135|// sealevel pressure timestamp max of this hour:|:
thb0seapress-dmin|967.8|// sealevel pressure min of today:|:
thb0seapress-dmintime|20250412062424|// sealevel pressure timestamp min of today:|:
thb0seapress-dmax|970.0|// sealevel pressure max of today:|:
thb0seapress-dmaxtime|20250412000942|// sealevel pressure timestamp max of today:|:
thb0seapress-ydmin|969.2|// sealevel pressure min of yesterday:|:
thb0seapress-ydmintime|20250411184632|// sealevel pressure timestamp min of yesterday:|:
thb0seapress-ydmax|973.7|// sealevel pressure max of yesterday:|:
thb0seapress-ydmaxtime|20250411021731|// sealevel pressure timestamp max of yesterday:|:
thb0seapress-mmin|967.8|// sealevel pressure min of this month:|:
thb0seapress-mmintime|20250412062424|// sealevel pressure timestamp min of this month:|:
thb0seapress-mmax|1022.0|// sealevel pressure max of this month:|:
thb0seapress-mmaxtime|20250403102201|// sealevel pressure timestamp max of this month:|:
thb0seapress-ymin|953.2|// sealevel pressure min of this year:|:
thb0seapress-ymintime|20250314194420|// sealevel pressure timestamp min of this year:|:
thb0seapress-ymax|1022.0|// sealevel pressure max of this year:|:
thb0seapress-ymaxtime|20250403102201|// sealevel pressure timestamp max of this year:|:
thb0seapress-amin|953.2|// sealevel pressure min of all time:|:
thb0seapress-amintime|20250314194420|// sealevel pressure timestamp min of all time:|:
thb0seapress-amax|1022.0|// sealevel pressure max of all time:|:
thb0seapress-amaxtime|20250403102201|// sealevel pressure timestamp max of all time:|:
thb0seapress-starttime|20250216011134|// sealevel pressure timestamp of first recorded data:|:
wind0wind-act|2.6|// windspeed most recent:|:
wind0wind-val5|3.6|// windspeed value 5 minutes ago:|:
wind0wind-val10|4.1|// windspeed value 10 minutes ago:|:
wind0wind-val15|3.6|// windspeed value 15 minutes ago:|:
wind0wind-val30|2.6|// windspeed value 30 minutes ago:|:
wind0wind-val60|1.5|// windspeed value 60 minutes ago:|:
wind0wind-hmin|2.0|// windspeed min of this hour:|:
wind0wind-hmintime|20250412120005|// windspeed timestamp min of this hour:|:
wind0wind-hmax|2.6|// windspeed max of this hour:|:
wind0wind-hmaxtime|20250412120235|// windspeed timestamp max of this hour:|:
wind0wind-dmin|0.0|// windspeed min of today:|:
wind0wind-dmintime|20250412001042|// windspeed timestamp min of today:|:
wind0wind-dmax|4.6|// windspeed max of today:|:
wind0wind-dmaxtime|20250412090959|// windspeed timestamp max of today:|:
wind0wind-ydmin|0.0|// windspeed min of yesterday:|:
wind0wind-ydmintime|20250411000027|// windspeed timestamp min of yesterday:|:
wind0wind-ydmax|11.7|// windspeed max of yesterday:|:
wind0wind-ydmaxtime|20250411142653|// windspeed timestamp max of yesterday:|:
wind0wind-mmin|0.0|// windspeed min of this month:|:
wind0wind-mmintime|20250401000001|// windspeed timestamp min of this month:|:
wind0wind-mmax|12.2|// windspeed max of this month:|:
wind0wind-mmaxtime|20250402135925|// windspeed timestamp max of this month:|:
wind0wind-ymin|0.0|// windspeed min of this year:|:
wind0wind-ymintime|20250216011134|// windspeed timestamp min of this year:|:
wind0wind-ymax|12.2|// windspeed max of this year:|:
wind0wind-ymaxtime|20250331115724|// windspeed timestamp max of this year:|:
wind0wind-amin|0.0|// windspeed min of all time:|:
wind0wind-amintime|20250216011134|// windspeed timestamp min of all time:|:
wind0wind-amax|12.2|// windspeed max of all time:|:
wind0wind-amaxtime|20250331115724|// windspeed timestamp max of all time:|:
wind0wind-starttime|20250216011134|// windspeed timestamp of first recorded data:|:
wind0avgwind-act|1.2|// average windspeed most recent:|:
wind0avgwind-val5|1.9|// average windspeed value 5 minutes ago:|:
wind0avgwind-val10|3.2|// average windspeed value 10 minutes ago:|:
wind0avgwind-val15|2.4|// average windspeed value 15 minutes ago:|:
wind0avgwind-val30|1.1|// average windspeed value 30 minutes ago:|:
wind0avgwind-val60|0.5|// average windspeed value 60 minutes ago:|:
wind0avgwind-delta2h|0.8|// average windspeed delta 2 hours ago:|:
wind0avgwind-delta3h|-0.5|// average windspeed delta 3 hours ago:|:
wind0avgwind-delta6h|1.2|// average windspeed delta 6 hours ago:|:
wind0avgwind-delta12h|0.6|// average windspeed delta 12 hours ago:|:
wind0avgwind-delta24h|-2.0|// average windspeed delta 24 hours ago:|:
wind0avgwind-avg@h48|--|// average windspeed avg 48 hours ago:|:
wind0avgwind-avg@h72|--|// average windspeed avg 72 hours ago:|:
wind0avgwind-avg@h96|--|// average windspeed avg 96 hours ago:|:
wind0avgwind-avg@h120|--|// average windspeed avg 120 hours ago:|:
wind0avgwind-avg@h144|--|// average windspeed avg 144 hours ago:|:
wind0avgwind-avg@h168|--|// average windspeed avg 168 hours ago:|:
wind0avgwind-hmin|0.8|// average windspeed min of this hour:|:
wind0avgwind-hmintime|20250412120135|// average windspeed timestamp min of this hour:|:
wind0avgwind-hmax|2.2|// average windspeed max of this hour:|:
wind0avgwind-hmaxtime|20250412120305|// average windspeed timestamp max of this hour:|:
wind0avgwind-dmin|0.0|// average windspeed min of today:|:
wind0avgwind-dmintime|20250412000212|// average windspeed timestamp min of today:|:
wind0avgwind-dmax|3.6|// average windspeed max of today:|:
wind0avgwind-dmaxtime|20250412090959|// average windspeed timestamp max of today:|:
wind0avgwind-ydmin|0.0|// average windspeed min of yesterday:|:
wind0avgwind-ydmintime|20250411000027|// average windspeed timestamp min of yesterday:|:
wind0avgwind-ydmax|7.3|// average windspeed max of yesterday:|:
wind0avgwind-ydmaxtime|20250411150625|// average windspeed timestamp max of yesterday:|:
wind0avgwind-mmin|0.0|// average windspeed min of this month:|:
wind0avgwind-mmintime|20250401000001|// average windspeed timestamp min of this month:|:
wind0avgwind-mmax|9.6|// average windspeed max of this month:|:
wind0avgwind-mmaxtime|20250406185754|// average windspeed timestamp max of this month:|:
wind0avgwind-ymin|0.0|// average windspeed min of this year:|:
wind0avgwind-ymintime|20250216011134|// average windspeed timestamp min of this year:|:
wind0avgwind-ymax|9.6|// average windspeed max of this year:|:
wind0avgwind-ymaxtime|20250406185754|// average windspeed timestamp max of this year:|:
wind0avgwind-amin|0.0|// average windspeed min of all time:|:
wind0avgwind-amintime|20250216011134|// average windspeed timestamp min of all time:|:
wind0avgwind-amax|9.6|// average windspeed max of all time:|:
wind0avgwind-amaxtime|20250406185754|// average windspeed timestamp max of all time:|:
wind0avgwind-starttime|20250216011134|// average windspeed timestamp of first recorded data:|:
wind0dir-act|84|// wind direction most recent:|:
wind0dir-val5|105|// wind direction value 5 minutes ago:|:
wind0dir-val10|96|// wind direction value 10 minutes ago:|:
wind0dir-val15|110|// wind direction value 15 minutes ago:|:
wind0dir-val30|95|// wind direction value 30 minutes ago:|:
wind0dir-val60|171|// wind direction value 60 minutes ago:|:
wind0dir-delta2h|0|// wind direction delta 2 hours ago:|:
wind0dir-delta3h|0|// wind direction delta 3 hours ago:|:
wind0dir-delta6h|0|// wind direction delta 6 hours ago:|:
wind0dir-delta12h|0|// wind direction delta 12 hours ago:|:
wind0dir-delta24h|0|// wind direction delta 24 hours ago:|:
wind0dir-avg@h48|--|// wind direction avg 48 hours ago:|:
wind0dir-avg@h72|--|// wind direction avg 72 hours ago:|:
wind0dir-avg@h96|--|// wind direction avg 96 hours ago:|:
wind0dir-avg@h120|--|// wind direction avg 120 hours ago:|:
wind0dir-avg@h144|--|// wind direction avg 144 hours ago:|:
wind0dir-avg@h168|--|// wind direction avg 168 hours ago:|:
wind0dir-avg10|84|// wind direction average 10 minutes:|:
wind0dir-havg|85|// wind direction average of this hour:|:
wind0dir-davg|121|// wind direction average of today:|:
wind0dir-ydavg|224|// wind direction average of yesterday:|:
wind0dir-mavg|158|// wind direction average of this month:|:
wind0dir-yavg|160|// wind direction average of this year:|:
wind0dir-aavg|160|// wind direction average of all time:|:
wind0dir-starttime|20250216011134|// wind direction timestamp of first recorded data:|:
wind0chill-act|17.7|// outdoor wind chill temperature most recent:|:
wind0chill-val5|17.4|// outdoor wind chill temperature value 5 minutes ago:|:
wind0chill-val10|17.5|// outdoor wind chill temperature value 10 minutes ago:|:
wind0chill-val15|17.3|// outdoor wind chill temperature value 15 minutes ago:|:
wind0chill-val30|17.2|// outdoor wind chill temperature value 30 minutes ago:|:
wind0chill-val60|17.1|// outdoor wind chill temperature value 60 minutes ago:|:
wind0chill-hmin|17.7|// outdoor wind chill temperature min of this hour:|:
wind0chill-hmintime|20250412120005|// outdoor wind chill temperature timestamp min of this hour:|:
wind0chill-hmax|17.7|// outdoor wind chill temperature max of this hour:|:
wind0chill-hmaxtime|20250412120005|// outdoor wind chill temperature timestamp max of this hour:|:
wind0chill-dmin|4.3|// outdoor wind chill temperature min of today:|:
wind0chill-dmintime|20250412054923|// outdoor wind chill temperature timestamp min of today:|:
wind0chill-dmax|17.8|// outdoor wind chill temperature max of today:|:
wind0chill-dmaxtime|20250412111834|// outdoor wind chill temperature timestamp max of today:|:
wind0chill-ydmin|4.2|// outdoor wind chill temperature min of yesterday:|:
wind0chill-ydmintime|20250411060608|// outdoor wind chill temperature timestamp min of yesterday:|:
wind0chill-ydmax|20.0|// outdoor wind chill temperature max of yesterday:|:
wind0chill-ydmaxtime|20250411145054|// outdoor wind chill temperature timestamp max of yesterday:|:
wind0chill-mmin|1.3|// outdoor wind chill temperature min of this month:|:
wind0chill-mmintime|20250402020633|// outdoor wind chill temperature timestamp min of this month:|:
wind0chill-mmax|21.2|// outdoor wind chill temperature max of this month:|:
wind0chill-mmaxtime|20250404155724|// outdoor wind chill temperature timestamp max of this month:|:
wind0chill-ymin|-1.1|// outdoor wind chill temperature min of this year:|:
wind0chill-ymintime|20250216011334|// outdoor wind chill temperature timestamp min of this year:|:
wind0chill-ymax|21.2|// outdoor wind chill temperature max of this year:|:
wind0chill-ymaxtime|20250404155724|// outdoor wind chill temperature timestamp max of this year:|:
wind0chill-amin|-1.1|// outdoor wind chill temperature min of all time:|:
wind0chill-amintime|20250216011334|// outdoor wind chill temperature timestamp min of all time:|:
wind0chill-amax|21.2|// outdoor wind chill temperature max of all time:|:
wind0chill-amaxtime|20250404155724|// outdoor wind chill temperature timestamp max of all time:|:
wind0chill-starttime|20250216011134|// outdoor wind chill temperature timestamp of first recorded data:|:
rain0rate-act|--|// rain rate most recent:|:
rain0rate-val5|--|// rain rate value 5 minutes ago:|:
rain0rate-val10|--|// rain rate value 10 minutes ago:|:
rain0rate-val15|--|// rain rate value 15 minutes ago:|:
rain0rate-val30|--|// rain rate value 30 minutes ago:|:
rain0rate-val60|--|// rain rate value 60 minutes ago:|:
rain0rate-delta2h|--|// rain rate delta 2 hours ago:|:
rain0rate-delta3h|--|// rain rate  delta 3 hours ago:|:
rain0rate-delta6h|--|// rain rate delta 6 hours ago:|:
rain0rate-delta12h|--|// rain rate delta 12 hours ago:|:
rain0rate-delta24h|--|// rain rate delta 24 hours ago:|:
rain0rate-avg@h48|--|// rain rate avg 48 hours ago:|:
rain0rate-avg@h72|--|// rain rate avg 72 hours ago:|:
rain0rate-avg@h96|--|// rain rate avg 96 hours ago:|:
rain0rate-avg@h120|--|// rain rate avg 120 hours ago:|:
rain0rate-avg@h144|--|// rain rate avg 144 hours ago:|:
rain0rate-avg@h168|--|// rain rate avg 168 hours ago:|:
rain0rate-hmin|--|// rain rate min of this hour:|:
rain0rate-hmintime|--|// rain rate timestamp min of this hour:|:
rain0rate-hmax|--|// rain rate max of this hour:|:
rain0rate-hmaxtime|--|// rain rate timestamp max of this hour:|:
rain0rate-dmin|--|// rain rate min of today:|:
rain0rate-dmintime|--|// rain rate timestamp min of today:|:
rain0rate-dmax|--|// rain rate max of today:|:
rain0rate-dmaxtime|--|// rain rate timestamp max of today:|:
rain0rate-ydmin|--|// rain rate min of yesterday:|:
rain0rate-ydmintime|--|// rain rate timestamp min of yesterday:|:
rain0rate-ydmax|--|// rain rate max of yesterday:|:
rain0rate-ydmaxtime|--|// rain rate timestamp max of yesterday:|:
rain0rate-mmin|--|// rain rate min of this month:|:
rain0rate-mmintime|--|// rain rate timestamp min of this month:|:
rain0rate-mmax|--|// rain rate max of this month:|:
rain0rate-mmaxtime|--|// rain rate timestamp max of this month:|:
rain0rate-ymin|--|// rain rate min of this year:|:
rain0rate-ymintime|--|// rain rate timestamp min of this year:|:
rain0rate-ymax|--|// rain rate max of this year:|:
rain0rate-ymaxtime|--|// rain rate timestamp max of this year:|:
rain0rate-amin|--|// rain rate min of all time:|:
rain0rate-amintime|--|// rain rate timestamp min of all time:|:
rain0rate-amax|--|// rain rate max of all time:|:
rain0rate-amaxtime|--|// rain rate timestamp max of all time:|:
rain0rate-starttime|--|// rain rate timestamp of first recorded data:|:
rain0total-act|--|// rain most recent:|:
rain0total-val5|--|// rain value 5 minutes ago:|:
rain0total-val10|--|// rain value 10 minutes ago:|:
rain0total-val15|--|// rain value 15 minutes ago:|:
rain0total-val30|--|// rain value 30 minutes ago:|:
rain0total-val60|--|// rain value 60 minutes ago:|:
rain0total-delta2h|--|// rain delta 2 hours ago:|:
rain0total-delta3h|--|// rain delta 3 hours ago:|:
rain0total-delta6h|--|// rain delta 6 hours ago:|:
rain0total-delta12h|--|// rain delta 12 hours ago:|:
rain0total-delta24h|--|// rain delta 24 hours ago:|:
rain0total-avg@h48|--|// rain avg 48 hours ago:|:
rain0total-avg@h72|--|// rain avg 72 hours ago:|:
rain0total-avg@h96|--|// rain avg 96 hours ago:|:
rain0total-avg@h120|--|// rain avg 120 hours ago:|:
rain0total-avg@h144|--|// rain avg 144 hours ago:|:
rain0total-avg@h168|--|// rain avg 168 hours ago:|:
rain0total-hmin|--|// rain min of this hour:|:
rain0total-hmintime|--|// rain timestamp min of this hour:|:
rain0total-hmax|--|// rain max of this hour:|:
rain0total-hmaxtime|--|// rain timestamp max of this hour:|:
rain0total-dmin|--|// rain min of today:|:
rain0total-dmintime|--|// rain timestamp min of today:|:
rain0total-dmax|--|// rain max of today:|:
rain0total-dmaxtime|--|// rain timestamp max of today:|:
rain0total-ydmin|--|// rain min of yesterday:|:
rain0total-ydmintime|--|// rain timestamp min of yesterday:|:
rain0total-ydmax|--|// rain max of yesterday:|:
rain0total-ydmaxtime|--|// rain timestamp max of yesterday:|:
rain0total-mmin|--|// rain min of this month:|:
rain0total-mmintime|--|// rain timestamp min of this month:|:
rain0total-mmax|--|// rain max of this month:|:
rain0total-mmaxtime|--|// rain timestamp max of this month:|:
rain0total-ymin|--|// rain min of this year:|:
rain0total-ymintime|--|// rain timestamp min of this year:|:
rain0total-ymax|--|// rain max of this year:|:
rain0total-ymaxtime|--|// rain timestamp max of this year:|:
rain0total-amin|--|// rain min of all time:|:
rain0total-amintime|--|// rain timestamp min of all time:|:
rain0total-amax|--|// rain max of all time:|:
rain0total-amaxtime|--|// rain timestamp max of all time:|:
rain0total-starttime|--|// rain timestamp of first recorded data:|:
uv0index-act|3.0|// uv index most recent:|:
uv0index-val5|4.0|// uv index value 5 minutes ago:|:
uv0index-val10|3.0|// uv index value 10 minutes ago:|:
uv0index-val15|4.0|// uv index value 15 minutes ago:|:
uv0index-val30|3.0|// uv index value 30 minutes ago:|:
uv0index-val60|3.0|// uv index value 60 minutes ago:|:
uv0index-hmin|3.0|// uv index min of this hour:|:
uv0index-hmintime|20250412120135|// uv index timestamp min of this hour:|:
uv0index-hmax|4.0|// uv index max of this hour:|:
uv0index-hmaxtime|20250412120005|// uv index timestamp max of this hour:|:
uv0index-dmin|0.0|// uv index min of today:|:
uv0index-dmintime|20250412000003|// uv index timestamp min of today:|:
uv0index-dmax|4.0|// uv index max of today:|:
uv0index-dmaxtime|20250412103732|// uv index timestamp max of today:|:
uv0index-ydmin|0.0|// uv index min of yesterday:|:
uv0index-ydmintime|20250411000027|// uv index timestamp min of yesterday:|:
uv0index-ydmax|5.0|// uv index max of yesterday:|:
uv0index-ydmaxtime|20250411123220|// uv index timestamp max of yesterday:|:
uv0index-mmin|0.0|// uv index min of this month:|:
uv0index-mmintime|20250401000001|// uv index timestamp min of this month:|:
uv0index-mmax|7.0|// uv index max of this month:|:
uv0index-mmaxtime|20250402125923|// uv index timestamp max of this month:|:
uv0index-ymin|0.0|// uv index min of this year:|:
uv0index-ymintime|20250216011134|// uv index timestamp min of this year:|:
uv0index-ymax|7.0|// uv index max of this year:|:
uv0index-ymaxtime|20250330124843|// uv index timestamp max of this year:|:
uv0index-amin|0.0|// uv index min of all time:|:
uv0index-amintime|20250216011134|// uv index timestamp min of all time:|:
uv0index-amax|7.0|// uv index max of all time:|:
uv0index-amaxtime|20250330124843|// uv index timestamp max of all time:|:
uv0index-starttime|20250216011134|// uv index timestamp of first recorded data:|:
sol0rad-act|540|// solar rad most recent:|:
sol0rad-val5|571|// solar rad value 5 minutes ago:|:
sol0rad-val10|548|// solar rad value 10 minutes ago:|:
sol0rad-val15|595|// solar rad value 15 minutes ago:|:
sol0rad-val30|562|// solar rad value 30 minutes ago:|:
sol0rad-val60|505|// solar rad value 60 minutes ago:|:
sol0rad-hmin|532|// solar rad min of this hour:|:
sol0rad-hmintime|20250412120305|// solar rad timestamp min of this hour:|:
sol0rad-hmax|575|// solar rad max of this hour:|:
sol0rad-hmaxtime|20250412120105|// solar rad timestamp max of this hour:|:
sol0rad-dmin|0|// solar rad min of today:|:
sol0rad-dmintime|20250412000003|// solar rad timestamp min of today:|:
sol0rad-dmax|618|// solar rad max of today:|:
sol0rad-dmaxtime|20250412115135|// solar rad timestamp max of today:|:
sol0rad-ydmin|0|// solar rad min of yesterday:|:
sol0rad-ydmintime|20250411000027|// solar rad timestamp min of yesterday:|:
sol0rad-ydmax|829|// solar rad max of yesterday:|:
sol0rad-ydmaxtime|20250411133552|// solar rad timestamp max of yesterday:|:
sol0rad-mmin|0|// solar rad min of this month:|:
sol0rad-mmintime|20250401000001|// solar rad timestamp min of this month:|:
sol0rad-mmax|1077|// solar rad max of this month:|:
sol0rad-mmaxtime|20250402130153|// solar rad timestamp max of this month:|:
sol0rad-ymin|0|// solar rad min of this year:|:
sol0rad-ymintime|20250216011134|// solar rad timestamp min of this year:|:
sol0rad-ymax|1077|// solar rad max of this year:|:
sol0rad-ymaxtime|20250402130153|// solar rad timestamp max of this year:|:
sol0rad-amin|0|// solar rad min of all time:|:
sol0rad-amintime|20250216011134|// solar rad timestamp min of all time:|:
sol0rad-amax|1077|// solar rad max of all time:|:
sol0rad-amaxtime|20250402130153|// solar rad timestamp max of all time:|:
sol0rad-starttime|20250216011134|// solar rad timestamp of first recorded data:|:
rain0total-daysum|--|// rain total today:|:
rain0total-monthsum|--|// rain total this month:|:
rain0total-yearsum|--|// rain total this year:|:
rain0total-ydaysum|--|// rain total yesterday:|:
rain0total-sum60|--|// rain total last 60 minutes:|:
sol0evo-daysum|--|// solar evo total today:|:
sol0evo-monthsum|--|// solar evo total this month:|:
sol0evo-yearsum|--|// solar evo total this year:|:
sol0evo-ydaysum|--|// solar evo total yesterday:|:
sol0evo-sum60|--|// solar evo total last 60 minutes:|:
sol0evo-sum24h|--|// solar evo total last 24 hours:|:
sol0evo-allsum|--|// solar evo total all time:|:
th0heatindex-act|17.7|// outdoor heat index most recent:|:
th0heatindex-val5|17.6|// outdoor heat index value 5 minutes ago:|:
th0heatindex-val10|17.8|// outdoor heat index value 10 minutes ago:|:
th0heatindex-val15|17.5|// outdoor heat index value 15 minutes ago:|:
th0heatindex-val30|17.2|// outdoor heat index value 30 minutes ago:|:
th0heatindex-val60|17.1|// outdoor heat index value 60 minutes ago:|:
th0heatindex-hmin|17.7|// outdoor heat index min of this hour:|:
th0heatindex-hmintime|20250412120005|// outdoor heat index timestamp min of this hour:|:
th0heatindex-hmax|17.7|// outdoor heat index max of this hour:|:
th0heatindex-hmaxtime|20250412120005|// outdoor heat index timestamp max of this hour:|:
th0heatindex-dmin|6.1|// outdoor heat index min of today:|:
th0heatindex-dmintime|20250412051652|// outdoor heat index timestamp min of today:|:
th0heatindex-dmax|17.8|// outdoor heat index max of today:|:
th0heatindex-dmaxtime|20250412111834|// outdoor heat index timestamp max of today:|:
th0heatindex-ydmin|5.1|// outdoor heat index min of yesterday:|:
th0heatindex-ydmintime|20250411060938|// outdoor heat index timestamp min of yesterday:|:
th0heatindex-ydmax|20.0|// outdoor heat index max of yesterday:|:
th0heatindex-ydmaxtime|20250411145054|// outdoor heat index timestamp max of yesterday:|:
th0heatindex-mmin|4.0|// outdoor heat index min of this month:|:
th0heatindex-mmintime|20250409030301|// outdoor heat index timestamp min of this month:|:
th0heatindex-mmax|21.2|// outdoor heat index max of this month:|:
th0heatindex-mmaxtime|20250404155724|// outdoor heat index timestamp max of this month:|:
th0heatindex-ymin|-1.1|// outdoor heat index min of this year:|:
th0heatindex-ymintime|20250216011334|// outdoor heat index timestamp min of this year:|:
th0heatindex-ymax|21.2|// outdoor heat index max of this year:|:
th0heatindex-ymaxtime|20250404155724|// outdoor heat index timestamp max of this year:|:
th0heatindex-amin|-1.1|// outdoor heat index min of all time:|:
th0heatindex-amintime|20250216011334|// outdoor heat index timestamp min of all time:|:
th0heatindex-amax|21.2|// outdoor heat index max of all time:|:
th0heatindex-amaxtime|20250404155724|// outdoor heat index timestamp max of all time:|:
th0heatindex-starttime|20250216011134|// outdoor heat index timestamp of first recorded data:|:
wind0dir-min10|48|// wind direction min 10 minutes:|:
wind0dir-max10|154|// wind direction max 10 minutes:|:
'; // END_OF_RAW_DATA_LINES;

// end of generation script

// put data in  array
//
$WX = array();
global $WX;
$WXComment = array();
$data = explode(":|:",$rawdatalines);
$nscanned = 0;
foreach ($data as $v => $line) {
  list($vname,$vval,$vcomment) = explode("|",trim($line).'|||');
  if ($vname <> "" and strpos($vval,'$') === false) {
    $WX[$vname] = trim($vval);
    if($vcomment <> "") { $WXComment[$vname] = trim($vcomment); }
  }
  $nscanned++;
}
if(isset($_REQUEST['debug'])) {
  print "<!-- loaded $nscanned $WXsoftware \$WX[] entries -->\n";
}

if (isset($_REQUEST["sce"]) and strtolower($_REQUEST["sce"]) == "dump" ) {

  print "<pre>\n";
  print "// \$WX[] array size = $nscanned entries.\n";
  foreach ($WX as $key => $val) {
	  $t =  "\$WX['$key'] = '$val';";
	  if(isset($WXComment[$key])) {$t .=  " $WXComment[$key]"; }
	  print "$t\n";
  }
  print "</pre>\n";

}
if(file_exists("MB-defs.php")) { include_once("MB-defs.php"); }
?>