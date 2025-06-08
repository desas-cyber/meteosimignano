/* Afrikaans text for ajaxWDwx.js courtesy of http://www.namibiaweather.info/  */

var langPauseMsg = 'Updates paused - reload page to start'; // substitute this for ajaxindicator when
                             // maxupdates has been reached and updating paused.

var langMonths = new Array ( "januarie","februarie","maart","april","mei",
			"juni","juli","augustus","september","oktober","november","desember");
var langDays = new Array ( "Son","Maan","Dins","Woens","Donder","Vry","Sater","Zon");	

var langBaroTrend = new Array (
 "Bestendig", "stadig stygend", "vinnig stygend", "stadig dalend", "vinnig dalend");

var langUVWords = new Array (
 "geen", "Laag", "Gemiddeld", "Hoog",
 "Baie&nbsp;Hoog", /* be sure to include &nbsp; for space */
 "Extreem" );

var langBeaufort = new Array ( /* Beaufort 0 to 12 in array */
 "Kalm", "Lig", "Ligte bries", "Matige bries", "Maatig", "taamlik sterk",
 "Kragtig", "Sterk", "Stormagtig", "Storm", "Swaar storm",
 "Baie sterk storm", "Orkaan"
);

var langWindDir = new Array( /* used for alt and title tags on wind dir arrow and wind direction display */
	"N", "NNO", "NO", "ONO", 
	"O", "OSO", "SO", "SSO", 
	"S", "SSW", "SW", "WSW", 
	"W", "WNW", "NW", "NNW");

var langWindCalm = 'kalm';
var langGustNone = 'geen';
var langWindFrom = 'Wind van '; /* used on alt/title tags on wind direction arrow*/

var langBaroRising = '%s stygend '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langBaroFalling = '%s daling ';
var langBaroPerHour = '/uur.'; /* will be assembled as rising/falling + value + uom + perhour text */

var langThermoCurrently = 'Huidige: '; /* used on alt/title tags for thermometer */
var langThermoMax     = 'Max: ';
var langThermoMin     = 'Min: ';

var langTempRising = '%s warmer '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langTempFalling ='%s koeler ';
var langTempLastHour = ' oor afgeloope uur.';

var langHeatWords = new Array ( /* used for Heat Color Word */
 'Onbekend', 'Ekstreem Hitte Gevaar', 'Hitte Gevaar', 'Groot Hitte voorsorg', 'Bloedig warm', 'Ongemaklik warm',
 'baie warm', 'Warm', 'Matig', 'Koel', 'Koud', 'Ongemaklik Koud', 'Baie Koud', 'Ekstreem Koud' );


