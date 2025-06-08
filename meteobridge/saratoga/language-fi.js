/* Finnish settings for ajaxWDwx.js */

var langPauseMsg = 'P‰ivitys tauonnut - p‰ivit‰ sivu jatkaasesi'; // substitute this for ajaxindicator when
                             // maxupdates has been reached and updating paused.

var langMonths = new Array ( "Tammikuu","Helmikuu","Maaliskuu","Huhtikuu","Toukokuu",
			"Kes‰kuu","Hein‰kuu","Elokuu","Syyskuu","Lokakuu","Marraskuu","Joulukuu");
var langDays = new Array ( "Sun","Maa","Tii","Kes","Tor","Per","Lau","Sun");	

var langBaroTrend = new Array (
 "Vakaa", "Hitaasti nouseva", "Nopeasti nouseva", "Hitaasti laskeva", "Nopeasti laskeva");

var langUVWords = new Array (
 "--", "Matala", "Kohtalainen", "Korkea",
 "Eritt‰in&nbsp;Korkea", "Vaarallisen&nbsp;korkea" );

var langBeaufort = new Array ( /* Beaufort 0 to 12 in array */
 "Tyynt‰", "Heikkoa tuulta", "Heikkoa tuulta", "Kohtalaista tuulta", "Kohtalaista tuulta", "Navakkaa tuulta", "Navakkaa tuulta",
 "Kovaa tuulta", "Kovaa tuulta", "Myrsky‰", "Myrsky‰", "Myrsky‰",
 "Hirmumyrsky"
);

var langHeatWords = new Array ( /* used for Heat Color Word */
 'Ei tiedossa', 'Poikkeuksellisen l‰mmint‰', 'Vaarallisen l‰mmint‰', 'Vaarallisen l‰mmint‰', 'Eritt‰in l‰mmint‰', 'Tukalan l‰mmint‰', 'Kuumaa', 'L‰mmint‰', 'Sopivan l‰mmint‰', 'Viile‰t‰', 'Kylm‰‰', 'Kylm‰‰', 'Eritt‰in kylm‰‰', 'Poikkeuksellisen kylm‰‰' );


var langWindDir = new Array( /* used for alt and title tags on wind dir arrow and wind direction display */
	"P", "PKO", "KO", "IKO", 
	"I", "IKA", "KA", "EKA", 
	"E", "ELO", "LO", "LLO", 
	"L", "LLU", "LU", "PLU");

var langWindCalm = 'Tyynt‰';

var langGustNone = 'Ei puuskaa';

var langWindFrom = 'Tuuli '; /* used on alt/title tags on wind direction arrow*/

var langBaroRising = 'Nouseva %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */

var langBaroFalling = 'Laskeva %s ';

var langBaroPerHour = '/tunnissa'; /* will be assembled as rising/falling + value + uom + perhour text */

var langThermoCurrently = 'Nyt: '; /* used on alt/title tags for thermometer */

var langThermoMax     = 'Maksimi: ';

var langThermoMin     = 'Minimi: ';

var langTempRising = ' %s L‰mpim‰mp‰‰'; /* used for trend arrow alt/title tags .. %s marks where value will be placed */

var langTempFalling = ' %s Kylmemp‰‰';

var langTempLastHour = ' kuin tunti sitten.';
