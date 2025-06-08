/* German text for ajaxWDwx.js */

var langPauseMsg = 'Updates paused - reload page to start'; // substitute this for ajaxindicator when
                             // maxupdates has been reached and updating paused.

var langMonths = new Array ( "Januar","Februar","März","April","Mai",
			"Juni","Juli","August","September","Oktober","November","Dezember");
var langDays = new Array ( "Son","Mon","Die","Mit","Don","Fre","Sam","Son");	

var langBaroTrend = new Array (
 "Gleichbleibend", "Langsam Steigend", "Schnell Steigend", "Langsam Fallend", "Schnell Fallend");

var langUVWords = new Array (
 "Keine", "Wenig", "Mittel", "Hoch",
 "sehr&nbsp;Hoch", /* be sure to include &nbsp; for space */
 "Extreme" );

var langBeaufort = new Array ( /* Beaufort 0 to 12 in array */
 "Windstill", "Nahezu Windstill", "Leichte Briese", "Mäßige Briese", "Mittlere Briese", "Auffrischene Briese",
 "Starke Briese", "Steifer Wind", "Stürmischer Wind", "Stark stürmisch", "Sturm",
 "kräftiger sturm", "Orkan"
);

var langWindDir = new Array( /* used for alt and title tags on wind dir arrow and wind direction display */
	"N", "NNO", "NO", "ONO", 
	"O", "OSO", "SO", "SSO", 
	"S", "SSW", "SW", "WSW", 
	"W", "WNW", "NW", "NNW");

var langWindCalm = 'Windstill';
var langGustNone = 'keine';
var langWindFrom = 'Wind von '; /* used on alt/title tags on wind direction arrow*/

var langBaroRising = 'Steigend %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langBaroFalling = 'Fallend %s ';
var langBaroPerHour = '/Stunde.'; /* will be assembled as rising/falling + value + uom + perhour text */

var langThermoCurrently = 'zur Zeit: '; /* used on alt/title tags for thermometer */
var langThermoMax     = 'Max: ';
var langThermoMin     = 'Min: ';

var langTempRising = 'Wärmer %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langTempFalling = 'Kälter %s ';
var langTempLastHour = ' als letzte Stunde.';

var langHeatWords = new Array ( /* used for Heat Color Word */
 'Unbekannt', 'Extreme Hitzegefahr', 'Hohe Gefahr', 'Extrem Hohe Hitzegefahr', 'Sehr Heis', 'Unangenehm Heiss',
 'Heiss', 'Warm', 'Angenehm', 'Kühl', 'Kalt', 'Unangenehm Kalt', 'sehr Kalt', 'Extrem Kalt' );

