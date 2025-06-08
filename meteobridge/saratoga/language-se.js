/* Swedish settings for ajaxWDwx.js */

var langPauseMsg = 'Uppdateringarna pausade - ladda om sidan för att återuppta'; // substitute this for ajaxindicator when
                             // maxupdates has been reached and updating paused.

var langMonths = new Array ( "Januari","Februari","Mars","April","Maj",
			"Juni","Juli","Augusti","September","Oktober","November","December");
var langDays = new Array ( "Sön","Mån","Tis","Ons","Tor","Fre","Lör","Sön");	

var langBaroTrend = new Array (
 "Stabilt", "Långsamt stigande", "Snabbt stigande", "Långsamt fallande", "Snabbt fallande");

var langUVWords = new Array (
 "Ingen", "Låg", "Medel", "Hög",
 "Mycket&nbsp;Högt", /* be sure to include &nbsp; for space */
 "Extremt" );

var langBeaufort = new Array ( /* Beaufort 0 to 12 in array */
 "Vindstilla", "Nästan stilla", "Svag vind", "Lätt vind", "Måttlig vind", "Frisk vind",
 "Hård vind", "Styv kuling", "Hård kuling", "Halv storm", "Storm",
 "Svår storm", "Orkan"
);


var langWindDir = new Array( /* used for alt and title tags on wind dir arrow and wind direction display */
	"N", "NNO", "NO", "ONO", 
	"O", "OSO", "SO", "SSO", 
	"S", "SSV", "SV", "VSV", 
	"V", "VNV", "NV", "NNV");

var langWindCalm = 'Vindstilla';
var langGustNone = 'Ingen';
var langWindFrom = 'Vind från '; /* used on alt/title tags on wind direction arrow*/

var langBaroRising = 'Stigande %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langBaroFalling = 'Fallande %s ';
var langBaroPerHour = '/tim'; /* will be assembled as rising/falling + value + uom + perhour text */

var langThermoCurrently = 'Nu: '; /* used on alt/title tags for thermometer */
var langThermoMax     = 'Max: ';
var langThermoMin     = 'Min: ';

var langTempRising = 'Varmare %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langTempFalling = 'Kallare %s ';
var langTempLastHour = ' sista timmen.';

var langHeatWords = new Array ( /* used for Heat Color Word */
 'Okänt', 'Extrem värme fara', 'Hetta', 'Extrem hetta', 'Extremt varmt', 'Väldigt varmt',
 'Mycket varmt', 'Varmt', 'Komfortabelt', 'Kyligt', 'Kallt', 'Väldigt kallt', 'Mycket kallt', 'Extremt kallt' );