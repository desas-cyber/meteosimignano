/* Polish text for ajaxWDwx.js */

var langPauseMsg = 'Aktualizacja wstrzymana - od¶wie¿ stronê'; // substitute this for ajaxindicator when
                             // maxupdates has been reached and updating paused.

var langMonths = new Array ( "Styczeñ","Luty","Marzec","Kwiecieñ","Maj",
			"Czerwiec","Lipiec","Sierpieñ","Wrzesieñ","Pa¼dziernik","Listopad","Grudzieñ");
var langDays = new Array ( "Nd","Pn","Wt","¦r","Cz","Pt","So","Nd");
//, , , , , , 

var langBaroTrend = new Array (
 "Stabilnie", "Ro¶nie powoli", "Ro¶nie szybko", "Spada powoli", "Spada szybko");

var langUVWords = new Array (
 "Brak", "Niski", "¦redni", "Wysoki",
 "Bardzo wysoki", /* be sure to include   for space */
 "Ekstremalny" );

var langBeaufort = new Array ( /* Beaufort 0 to 12 in array */
 "Cisza", "Powiew", "S³aby wiatr", "£agodny wiatr", "Umiarkowany wiatr", "Do¶æ silny wiatr",
 "Silny wiatr", "Bardzo silny wiatr", "Sztorm", "Silny sztorm", "Bardzo silny sztorm",
 "Gwa³towny sztorm", "Huragan"
);

var langWindDir = new Array( /* used for alt and title tags on
   wind dir arrow and wind direction display */
     "Pn", "PnPnZd", "PnWs", "WsPnWs",
     "Ws", "WsPdWs", "PdWs", "PdPdWs",
     "Pd", "PdPdZd", "PdZd", "ZdPdZa",
     "Zd", "ZdPnZd", "PnZd", "PnPnZd");

var langWindCalm = 'Cisza';
var langGustNone = 'None';
var langWindFrom = 'Wiatr z '; /* used on alt/title tags on wind direction arrow*/

var langBaroRising = 'Wzros³a %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langBaroFalling = 'Spad³a %s ';
var langBaroPerHour = '/h.'; /* will be assembled as rising/falling + value + uom + perhour text */

var langThermoCurrently = 'Aktualnie: '; /* used on alt/title tags for thermometer */
var langThermoMax     = 'Max: ';
var langThermoMin     = 'Min: ';

var langTempRising = 'Cieplej %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langTempFalling = 'Ch³odniej %s ';
var langTempLastHour = ' ni¿ w ostatniej godzinie.';

var langHeatWords = new Array ( /* used for Heat Color Word */
 'Unknown', 'Ekstremalny upa³ Niebezpieczeñstwo', 'Upa³ Niebezpieczeñstwo', 'Upa³ Ostrze¿enie', 'Ekstremalnie gor±co', 'Niekomfortowo gor±co',
 'Gor±co', 'Ciep³o', 'Komfortowo', 'Ch³odno', 'Zimno', 'Niekomfortowo zimno', 'Bardzo zimno', 'Ekstremalnie zimno' );

