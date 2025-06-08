/* English text for ajaxWDwx.js */

var langPauseMsg = 'Mises à jour suspendues - pour démarrer, recharger la page'; // substitute this for ajaxindicator when
                             // maxupdates has been reached and updating paused.

var langMonths = new Array ( "janvier","février","mars","avril","mai",
			"juin","juillet","août","septembre","octobre","novembre","décembre");
var langDays = new Array ( "dim","lun","mar","mer","jeu","ven","sam","dim");	

var langBaroTrend = new Array (
 "Soutenu", "Monte lentement", "Monte rapidment", "Tombe lentement", "Tombe rapidement");

var langUVWords = new Array (
 "aucun", "faible", "moyen", "élevé",
 "très&nbsp;élevé", /* be sure to include &nbsp; for space */
 "extrême" );

var langBeaufort = new Array ( /* Beaufort 0 to 12 in array */
 "calme", "très légère brise", "légère brise", "petite brise", "jolie brise", "bonne brise",
 "vent frais", "grand frais", "coup de vent", "fort coup de vent", "tempête",
 "violente tempête", "ouragan"
);

var langWindDir = new Array( /* used for alt and title tags on wind dir arrow and wind direction display */
	"N", "NNE", "NE", "ENE", 
	"E", "ESE", "SE", "SSE", 
	"S", "SSO", "SO", "OSO", 
	"O", "ONO", "NO", "NNO");

var langWindCalm = 'calme';
var langGustNone = 'aucune';
var langWindFrom = 'vent '; /* used on alt/title tags on wind direction arrow*/

var langBaroRising = 'Monte %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langBaroFalling = 'Baisse %s ';
var langBaroPerHour = '/heure.'; /* will be assembled as rising/falling + value + uom + perhour text */

var langThermoCurrently = 'actuellement: '; /* used on alt/title tags for thermometer */
var langThermoMax     = 'maxi: ';
var langThermoMin     = 'mini: ';

var langTempRising = 'plus chaud %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langTempFalling = 'plus froid %s ';
var langTempLastHour = " qu'il y a une heure.";

var langHeatWords = new Array ( /* used for Heat Color Word */
 'inconnu', 'extrême danger de chaleur', 'danger de chaleur', 'fort risque de chaleur', 'extrêmement chaud', 'inconfortablement chaud',
 'chaud', 'modérément chaud', 'confortable', 'frais', 'froid', 'inconfortablement froid', 'très froid', 'extrêmement froid' );

