/* Hebrew text for ajaxWDwx.js */
/* Note: use ISO-8859-8 character set */
/* Hebrew translation provided by Eli www.viater.co.il */
// Version 1.00 - 17-Nov-2011 - initial release

var langPauseMsg = 'Updates paused - reload page to start'; // substitute this for ajaxindicator when
                             // maxupdates has been reached and updating paused.

var langMonths = new Array ( 
"ינואר",
"פברואר",
"מרץ",
"אפריל",
"מאי",
"יוני",
"יולי",
"אוגוסט",
"ספטמבר",
"אוקטובר",
"נובמבר",
"דצמבר"
);
var langDays = new Array ( 
"ראשון",
"שני",
"שלישי",
"רביעי",
"חמישי",
"שישי",
"שבת",
"ראשון"
);	

var langBaroTrend = new Array (
 "יציב",
 "עלייה איטית",
 "עלייה מהירה",
 "ירידה איטית",
 "ירידה מהירה"
 );

var langUVWords = new Array (
 "אין",
 "נמוך",
 "בינוני",
 "גבוה",
 "Very&nbsp;High", /* be sure to include &nbsp; for space */
 "Extreme"
 );

var langBeaufort = new Array ( /* Beaufort 0 to 12 in array */
 "שקט",
 "אויר קל",
 "בריזה קלה",
 "בריזה נעימה",
 "בריזה בינונית",
 "בריזה מרעננת",
  "בריזה חזקה",
  "רוח סוערת",
  "סערה",
  "סערה חזקה",
  "סופה",
 "סופה חזקה",
 "הוריקן"
);

var langWindDir = new Array( /* used for alt and title tags on wind dir arrow and wind direction display */
	"'צ",
	"'צצמ",
	"'צמ",
	"'מצמ", 
	"'מ",
	"'מדמ",
	"'דמ",
	"'דדמ", 
	"'ד",
	"'דדמ",
	"'דמ",
	"'מדמ", 
	"'מ",
	"'מצמ",
	"'צמ",
	"'צצמ"
	);

var langWindCalm = 'שקט';
var langGustNone = 'אין';
var langWindFrom = 'רוח מ '; /* used on alt/title tags on wind direction arrow*/

var langBaroRising = 'עלייה %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langBaroFalling = 'ירידה %s ';
var langBaroPerHour = '/שעה.'; /* will be assembled as rising/falling + value + uom + perhour text */

var langThermoCurrently = 'נוכחי: '; /* used on alt/title tags for thermometer */
var langThermoMax     = '\'מקס: ';
var langThermoMin     = '\'מינ: ';

var langTempRising = 'חם מ %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langTempFalling = 'קר מ %s ';
var langTempLastHour = ' מהשעה האחרונה.';

var langHeatWords = new Array ( /* used for Heat Color Word */
 'לא ידוע',
 'סכנת חום קיצוני',
 'סכנת חום',
 'אזהרת חום קיצוני',
 'חום קיצוני',
 'חם ביותר',
  'חם',
  'חמים',
  'נוח',
  'קריר',
  'קר',
  'קר ביותר',
  'קר מאוד',
  'קור קיצוני'
  );

