/* Greek text for ajaxWDwx.js */

var langPauseMsg = 'Παύση Αυτόματης Ενημέρωσης!Φορτώστε πάλι τη σελίδα για συνέχεια!'; // substitute this for ajaxindicator when
                             // maxupdates has been reached and updating paused.

var langMonths = new Array ( "Ιανουάριος","Φεβρουάριος","Μάρτιος","Απρίλιος","Μάϊος",
			"Ιούνιος","Ιούλιος","Αύγουστος","Σεπτέμβριος","Οκτώβριος","Νοέμβριος","Δεκέμβριος");
var langDays = new Array ( "Κυρ.","Δευτ.","Τρ.","Τετ.","Πέμ.","Παρ.","Σαβ.","Κυρ.");	

var langBaroTrend = new Array (
 "Σταθερή", "Αργά Ανοδική", "Εντονα Ανοδική", "Αργά Πτωτική", "Εντονα Πτωτική");

var langUVWords = new Array (
 "Μηδέν", "Χαμηλός", "Μεσαίος", "Υψηλός",
 "Πολύ&nbsp;Υψηλός", /* be sure to include &nbsp; for space */
 "Ακραίος" );

var langBeaufort = new Array ( /* Beaufort 0 to 12 in array */
 "Απνοια", "Σχεδόν Απνοια", "Πολύ Ασθενής", "Ασθενής", "Σχεδόν Μέτριος", "Μέτριος",
 "Ισχυρός", "Σχεδόν Θυελλώδης", "Θυελλώδης", "Πολύ Θυελλώδης", "Θύελλα",
 "Ισχυρή Θύελλα", "Τυφώνας"
);

var langWindDir = new Array( /* used for alt and title tags on wind dir arrow and wind direction display */
	"Β", "ΒΒΑ", "ΒΑ", "ΑΒΑ", 
	"Α", "ΑΝΑ", "ΝΑ", "ΝΝΑ", 
	"Ν", "ΝΝΔ", "ΝΔ", "ΔΝΔ", 
	"Δ", "ΔΒΔ", "ΒΔ", "ΒΒΔ");

var langWindCalm = 'Απνοια';
var langGustNone = 'Μηδέν';
var langWindFrom = 'Ανεμος από '; /* used on alt/title tags on wind direction arrow*/

var langBaroRising = 'Ανοδική %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langBaroFalling = 'Πτωτική %s ';
var langBaroPerHour = '/ώρα.'; /* will be assembled as rising/falling + value + uom + perhour text */

var langThermoCurrently = 'Τρέχουσα: '; /* used on alt/title tags for thermometer */
var langThermoMax     = 'Μέγ: ';
var langThermoMin     = 'Ελάχ: ';

var langTempRising = 'Θερμότερη %s '; /* used for trend arrow alt/title tags .. %s marks where value will be placed */
var langTempFalling = 'Ψυχρότερη %s ';
var langTempLastHour = ' από την τελευταία ώρα.';

var langHeatWords = new Array ( /* used for Heat Color Word */
 'Αγνωστο', 'Κίνδυνος Ακραίας Ζέστης', 'Κίνδυνος Καύσωνα', 'Ειδοποίηση Καύσωνα', 'Εξαιρετικά Ζεστός', 'Δυσάρεστα Ζεστός',
 'Ζεστός', 'Θερμός', 'Ευχάριστος', 'Ψύχρα', 'Κρύος', 'Δυσάρεστα Κρύος', 'Πολύ κρύος', 'Εξαιρετικά Κρύος' );

