README notes about 2011-11-17 update to add Hebrew language to Settings.php


Rather than just recustomizing the new Settings.php, you can manually change your existing Settings.php
by doing the following:

Change in the $SITE['languagesAvailable'] area :

  'de', // 'german',
  'el', // 'greek',
  'hu', // 'hungarian',
  'it', // 'italian',

to:

  'de', // 'german',
  'el', // 'greek',
  'he', // 'hebrew',
  'hu', // 'hungarian',
  'it', // 'italian',

Change:

##########################################################################
# end of configurable settings
##########################################################################
#
# Multilanguage support constants - please do NOT change the settings below
# DO NOT CHANGE THESE SETTINGS
$SITE['installedLanguages'] = array (

to:

##########################################################################
# end of configurable settings
##########################################################################
#
if(file_exists("Settings-language.php")) { include_once("Settings-language.php"); }
  else { //  use defaults built-in 
# Multilanguage support constants - please do NOT change the settings below
# DO NOT CHANGE THESE SETTINGS
$SITE['installedLanguages'] = array (

Change: 

$SITE['installedLanguages'] = $tarray;
# end prune the installedLanguages based on langavail selection
#
# set the Timezone abbreviation automatically based on $SITE['tzname'];

to:

$SITE['installedLanguages'] = $tarray;
# end prune the installedLanguages based on langavail selection
#
  } // end use default language specs
# set the Timezone abbreviation automatically based on $SITE['tzname'];

