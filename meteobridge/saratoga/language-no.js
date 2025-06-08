/* Norwegian settings for ajaxWDwx.js */

var langPauseMsg = 'Oppdateringer stanset - restart ved å oppdatere siden'; // substitute this for ajaxindicator when
                             // maxupdates has been reached and updating paused.

var langMonths = new Array ( "Januar","Februar","Mars","April","Mai",
			"Juni","Juli","August","September","Oktober","November","Desember");
var langDays = new Array ( "Søn","Man","Tir","Ons","Tor","Fre","Lør","Søn");	

var langBaroTrend = new Array (
 "Stabilt", "Langsomt stigende", "Kraftig stigende", "Langsomt fallende", "Kraftigt fallende");

var langUVWords = new Array (
 "Ingen", "Lav", "Moderat", "Høy",
 "Svært&nbsp;Høy", /* be sure to include &nbsp; for space */
 "Ekstrem" );

var langBeaufort = new Array ( /* Beaufort 0 to 12 in array */
 "Stille", "Flau vind", "Svak vind", "Lett bris", "Laber bris", "Frisk bris",
 "Liten kuling", "Stiv kuling", "Sterk kuling", "Liten storm", "Full storm",
 "Sterk storm", "Orkan"
);


var langWindDir = new Array( /* used for alt and title tags on wind dir arrow and wind direction display */
	"N", "NNØ", "NØ", "ØNØ", 
	"Ø", "ØSØ", "SØ", "SSØ", 
	"S", "SSV", "SV", "VSV", 
	"V", "VNV", "NV", "NNV");

var langHeatWords = new Array ( /* used for Heat Color Word */
'Ukjent', 'Ekstrem hete fare!!', 'Hete fare!', 'Ekstremt hett', 'Ekstremt varmt', 'Ubehagelig varmt',
'Svært varmt', 'Varmt', 'Komfortabelt', 'Kjøligt', 'Kaldt', 'Ubehagelig kaldt', 'Svært kaldt', 'Ekstremt kaldt' );


var langWindCalm = 'Stille';

var langGustNone = 'Ingen';

var langWindFrom = 'Vind fra '; /* used on alt/title tags on wind direction arrow*/

var langBaroRising = 'Stigende: + %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */

var langBaroFalling = 'Fallende: - %s ';

var langBaroPerHour = '/time'; /* will be assembled as rising/falling + value + uom + perhour text */

var langThermoCurrently = 'Nå: '; /* used on alt/title tags for thermometer */

var langThermoMax     = 'Max: ';

var langThermoMin     = 'Min: ';

var langTempRising = 'Varmere: + %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */

var langTempFalling = 'Kaldere: - %s ';

var langTempLastHour = ' siste time.';
