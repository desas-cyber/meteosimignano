/* Bulgarian text for ajaxWDwx.js */

var langPauseMsg = 'Updates paused - reload page to start'; // substitute this for ajaxindicator when
                             // maxupdates has been reached and updating paused.

var langMonths = new Array ( "Януари","Февруари","Март","Април","Май",
			"Юни","Юли","Август","Септември","Октомври","Ноември","Декември");
var langDays = new Array ( "Нед","Пон","Вто","Сря","Чет","Пет","Съб","Нед");	

var langBaroTrend = new Array (
 "Постоянно", "Бавно понижавашо се", "Бързо покачващо се", "Бавно понижавашо се", "Бързо понижаващо се");

var langUVWords = new Array (
 "Без промяна", "Нисък", "Среден", "Висок",
 "Много&nbsp;Висок", /* be sure to include &nbsp; for space */
 "Екстремен" );

var langBeaufort = new Array ( /* Beaufort 0 to 12 in array */
 "Тихо", "Лек полъх", "Лек бриз", "Слаб бриз", "Умерен бриз", "Свеж бриз",
 "Силен бриз", "Засилен вятър", "Силен вятър", "Много силен вятър", "Буря",
 "Силна буря", "Ураган"
);

var langWindDir = new Array( /* used for alt and title tags on wind dir arrow and wind direction display */
	"С", "ССИ", "СИ", "ИСИ", 
	"И", "ИЮИ", "ЮИ", "ЮЮИ", 
	"Ю", "ЮЮЗ", "ЮЗ", "ЗЮЗ", 
	"З", "ЗСЗ", "СЗ", "ССЗ");

var langWindCalm = 'Тихо';
var langGustNone = 'Без';
var langWindFrom = 'Вятър от '; /* used on alt/title tags on wind direction arrow*/

var langBaroRising = 'Покачващо се %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langBaroFalling = 'Понижаващо се %s ';
var langBaroPerHour = '/ч.'; /* will be assembled as rising/falling + value + uom + perhour text */

var langThermoCurrently = 'Текуща: '; /* used on alt/title tags for thermometer */
var langThermoMax     = 'Max: ';
var langThermoMin     = 'Min: ';

var langTempRising = 'По-топло %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langTempFalling = 'По-студено %s ';
var langTempLastHour = ' в сравнение с посл. час.';

var langHeatWords = new Array ( /* used for Heat Color Word */
 'Неясно', 'Опасност - изкл. горещо', 'Опасност от горещини', 'Внимание - изкл. горещо', 'Изкл. горещо', 'Неприятно горещо',
 'Горещо', 'Топло', 'Приятно', 'Хладно', 'Студено', 'Неприятно студено', 'Много студено', 'Искл. студено' );
