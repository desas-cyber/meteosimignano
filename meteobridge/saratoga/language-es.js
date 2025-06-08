/* Spanish text for ajaxWDwx.js */

var langPauseMsg = 'Actualización en pausa - Recarga la página para comenzar de nuevo'; // substitute this for ajaxindicator when
                             // maxupdates has been reached and updating paused.

var langMonths = new Array ( "Enero","Febrero","Marzo","Abril","Mayo",
			"Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");
var langDays = new Array ( "Sáb", "Dom", "Lun", "Mar", "Miér", "Jue", "Viér", "Sáb");	
// 
var langBaroTrend = new Array (
 "Estable", "Subiendo lentamente", "Subiendo rápidamente", "Bajando lentamente", "Bajando rápidamente");

var langUVWords = new Array (
 "Nulo", "Bajo", "Medio", "Alto",
 "Muy&nbsp;alto", /* be sure to include &nbsp; for space */
 "Extremo" );

var langBeaufort = new Array ( /* Beaufort 0 to 12 in array */
 "Calma", "Ventolina", "Brisa muy débil", "Brisa débil", "Brisa moderada", "Brisa fresca",
 "Brisa fuerte", "Viento fuerte", "Viento duro", "Muy duro", "Temporal",
 "Borrasca", "Huracán"
);

var langWindDir = new Array( /* used for alt and title tags on wind dir arrow and wind direction display */
	"N", "NNE", "NE", "ENE", 
	"E", "ESE", "SE", "SSE", 
	"S", "SSO", "SO", "OSO", 
	"O", "ONO", "NO", "NNO");

var langWindCalm = 'Calma';
var langGustNone = 'Nula';
var langWindFrom = 'Viento del '; /* used on alt/title tags on wind direction arrow*/

var langBaroRising = 'Subiendo %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langBaroFalling = 'Bajando %s ';
var langBaroPerHour = '/hora.'; /* will be assembled as rising/falling + value + uom + perhour text */

var langThermoCurrently = 'Actual: '; /* used on alt/title tags for thermometer */
var langThermoMax     = 'Máx: ';
var langThermoMin     = 'Mín: ';

var langTempRising = '%s más'; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langTempFalling = '%s menos';
var langTempLastHour = ' que en la última hora.';

var langHeatWords = new Array ( /* used for Heat Color Word */
 'Desconocido', 'Calor insoportable', 'Ahogo', 'Bochorno extremo', 'Bochorno', 'Calor extremo',
 'Mucho calor', 'Calor', 'Agradable', 'Fresco', 'Frío', 'Frío intenso', 'Helada', 'Frío polar' );

