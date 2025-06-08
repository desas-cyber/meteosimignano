/* Dutch text for ajaxWDwx.js courtesy of http://www.doornenburgweer.nl/  */

var langPauseMsg = 'Updates paused - reload page to start'; // substitute this for ajaxindicator when
                             // maxupdates has been reached and updating paused.

var langMonths = new Array ( "januari","february","maart","april","mei",
			"juni","juli","augustus","september","oktober","november","december");
var langDays = new Array ( "Zon","Maan","Dins","Woens","Donder","Vrij","Zater","Zon");	

var langBaroTrend = new Array (
 "Bestendig", "Langzaam stijgend", "Snel stijgend", "Langzaam dalend", "Snel dalend");

var langUVWords = new Array (
 "geen", "Laag", "Gemiddeld", "Hoog",
 "Erg&nbsp;Hoog", /* be sure to include &nbsp; for space */
 "Extreem" );

var langBeaufort = new Array ( /* Beaufort 0 to 12 in array */
 "Kalm", "Zwak", "Zwak", "Matig", "Matig", "Vrij krachtig",
 "Krachtig", "Hard", "Stormachtig", "Storm", "Zware storm",
 "Zeer zware storm", "Orkaankracht"
);

var langWindDir = new Array( /* used for alt and title tags on wind dir arrow and wind direction display */
	"N", "NNO", "NO", "ONO", 
	"O", "OZO", "ZO", "ZZO", 
	"Z", "ZZW", "ZW", "WZW", 
	"W", "WNW", "NW", "NNW");

var langWindCalm = 'kalm';
var langGustNone = 'geen';
var langWindFrom = 'Wind van '; /* used on alt/title tags on wind direction arrow*/

var langBaroRising = '%s stijging '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langBaroFalling = '%s daling ';
var langBaroPerHour = '/uur.'; /* will be assembled as rising/falling + value + uom + perhour text */

var langThermoCurrently = 'Huidige: '; /* used on alt/title tags for thermometer */
var langThermoMax     = 'Max: ';
var langThermoMin     = 'Min: ';

var langTempRising = '%s warmer '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langTempFalling ='%s koeler ';
var langTempLastHour = ' dan afgelopen uurr.';

var langHeatWords = new Array ( /* used for Heat Color Word */
 'niet gekend', 'Extreem gevaarlijk warm', 'Gevaarlijk warm', 'Extreem warm Opletten', 'Extreem warm', 'Onconfortabel warm',
 'Zeer warm', 'Warm', 'Comfortabel', 'Koel', 'Koud', 'Oncomfortabel Koud', 'Zeer Koud', 'Extreem Koud' );


