/* English text for ajaxWDwx.js */

var langPauseMsg = 'Updates paused - reload page to start'; // substitute this for ajaxindicator when
                             // maxupdates has been reached and updating paused.

var langMonths = new Array ( "Január","Február","Március","Április","Május",
			"Junius","Július","Augusztus","Szeptember","Octóber","November","December");
var langDays = new Array ( "Nap","Hétfõ","Kedd","Szerda","Csütörtök","Péntek","Szombat","Nap");

var langBaroTrend = new Array (
 "Nem változik", "Emelkedik", "Emelkedik", "Csökken", "Csökken");

var langUVWords = new Array (
 "Nincs", "Alacsony", "Normál", "Magas",
 "Nagyon&nbsp;Magas", /* be sure to include &nbsp; for space */
 "Extrém" );

var langBeaufort = new Array ( /* Beaufort 0 to 12 in array */
 "Szélcsend", "Leheletszerü", "", "Szellõ", "Gyenge", "Mérsékelt",
 "Élénk", "Erõs", "Igen erõs", "Viharos", "Vihar",
 "Erõs vihar", "Szélvihar"
);

var langWindDir = new Array( /* used for alt and title tags on wind dir arrow and wind direction display */
    "N", "NNE", "NE", "ENE",
	"E", "ESE", "SE", "SSE",
	"S", "SSW", "SW", "WSW",
	"W", "WNW", "NW", "NNW");

var langWindCalm = 'calm';
var langGustNone = 'NINCS';
var langWindFrom = 'Wind from '; /* used on alt/title tags on wind direction arrow*/

var langBaroRising = 'Emelkedik %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langBaroFalling = 'Csökken %s ';
var langBaroPerHour = '/óra.'; /* will be assembled as rising/falling + value + uom + perhour text */

var langThermoCurrently = 'Currently: '; /* used on alt/title tags for thermometer */
var langThermoMax     = 'Max: ';
var langThermoMin     = 'Min: ';

var langTempRising = 'Melegebb %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langTempFalling = 'Hüvösebb %s ';
var langTempLastHour = ' Az elmult órában.';

var langHeatWords = new Array ( /* used for Heat Color Word */
 'Unknown', 'Extrém hõveszély', 'Hõség veszély', 'Extrém meleg', 'Rendkivüli meleg', 'Hõség',
 'Nagyon meleg', 'Meleg', 'Kellemes', 'Hüvös', 'Nagyon Hüvös', 'Hideg', 'Extrém hideg', 'Extreme Cold' );

