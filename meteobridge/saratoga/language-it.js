/* Italian text for ajaxWDwx.js */

var langPauseMsg = 'Aggiornamenti sospesi - Aggiornare la pagine per riprendere'; // substitute this for ajaxindicator when
                             // maxupdates has been reached and updating paused.

var langMonths = new Array ( "Gennaio","Febbraio","Marzo","Aprile","Maggio",
			"Giugnio","Luglio","Agosto","Settembre","Ottobre","Novembre","Dicembre");
var langDays = new Array ( "Dom","Lun","Mar","Mer","Gio","Ven","Sab","Dom");	

var langBaroTrend = new Array (
 "Stabile", "Aumento lento", "Aumento rapido", "Diminuzione lenta", "Diminuzione rapida");

var langUVWords = new Array (
 "Nul", "Basso", "Medio", "Alto",
 "Molto&nbsp;Alto", /* be sure to include &nbsp; for space */
 "Estremo" );

var langBeaufort = new Array ( /* Beaufort 0 to 12 in array */
 "Calma", "Bava di vento", "Brezza leggera", "Brezza tesa", "Vento moderato", "Vento teso",
 "Vento fresco", "Vento forte", "Burrasca", "Burrasca forte", "Tempesta",
 "Tempesta violenta", "Uragano"
);

var langWindDir = new Array( /* used for alt and title tags on wind dir arrow and wind direction display */
	"N", "NNE", "NE", "ENE", 
	"E", "ESE", "SE", "SSE", 
	"S", "SSO", "SO", "OSO", 
	"O", "ONO", "NO", "NNO");

var langWindCalm = 'Calma';
var langGustNone = 'Nul';
var langWindFrom = 'Vento da '; /* used on alt/title tags on wind direction arrow*/

var langBaroRising = 'Aumento %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langBaroFalling = 'Diminuzione %s ';
var langBaroPerHour = '/h.'; /* will be assembled as rising/falling + value + uom + perhour text */

var langThermoCurrently = 'Currently: '; /* used on alt/title tags for thermometer */
var langThermoMax     = 'Max: ';
var langThermoMin     = 'Min: ';

var langTempRising = 'Più caldo %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langTempFalling = 'Più fresco %s ';
var langTempLastHour = ' di un ora fa.';

var langHeatWords = new Array ( /* used for Heat Color Word */
 'Sconosciuto', 'Caldo estremamente pericoloso', 'Caldo pericoloso', 'Estrema cautela', 'Estremamente caldo', 'Caldo fastidioso',
 'Caldo', 'Caldo sopportabile', 'Confortevole', 'Fresco', 'Freddo', 'Freddo fastidioso', 'Molto freddo', 'Freddo estremo' );

