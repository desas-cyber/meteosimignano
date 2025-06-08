<?php
# Settings-language.php is an optional file to specify the languages available.
# it is included by Settings.php.  You should not need to modify this file .. just replace
# with a new copy from the distribution as updates are made available.
#
# Version 1.00 - 17-Nov-2011 - initial release w/ lang=he included
#
if (isset($_REQUEST['sce']) && strtolower($_REQUEST['sce']) == 'view' ) {
   //--self downloader --
   $filenameReal = __FILE__;
   $download_size = filesize($filenameReal);
   header('Pragma: public');
   header('Cache-Control: private');
   header('Cache-Control: no-cache, must-revalidate');
   header("Content-type: text/plain");
   header("Accept-Ranges: bytes");
   header("Content-Length: $download_size");
   header('Connection: close');
   
   readfile($filenameReal);
   exit;
}
$tpath = pathinfo(__FILE__);
$SITE['languagesSpecification'] = $tpath['basename'];
# Multilanguage support constants - please do NOT change the settings below
#DO NOT CHANGE THESE SETTINGS
$SITE['installedLanguages'] = array (
  'af' => 'Afrikaans',
  'bg' => '&#1073;&#1098;&#1083;&#1075;&#1072;&#1088;&#1089;&#1082;&#1080; &#1077;&#1079;&#1080;&#1082;',
  'ct' => 'Catal&agrave;',
  'dk' => 'Dansk',
  'nl' => 'Nederlands',
  'en' => 'English',
  'fi' => 'Suomi',
  'fr' => 'Fran&ccedil;ais',
  'de' => 'Deutsch',
  'el' => '&Epsilon;&lambda;&lambda;&eta;&nu;&iota;&kappa;&#940;',
  'ga' => 'Gaeilge',
  'hu' => 'Magyar',
  'it' => 'Italiano',
  'he' => '&#1506;&#1460;&#1489;&#1456;&#1512;&#1460;&#1497;&#1514;',
  'no' => 'Norsk',
  'pl' => 'Polski',
  'pt' => 'Portugu&ecirc;s',
  'ro' => 'limba rom&#00226;n&#00259;',
  'es' => 'Espa&ntilde;ol',
  'se' => 'Svenska',
  'si' => 'Sloven&#353;&#269;ina',
);
# DO NOT CHANGE THESE SETTINGS
$SITE['ISOLang'] = array ( // ISO 639-1 2-character language abbreviations from country domain 
  'af' => 'af',
  'bg' => 'bg',
  'ct' => 'ca',
  'dk' => 'da',
  'nl' => 'nl',
  'en' => 'en',
  'fi' => 'fi',
  'fr' => 'fr',
  'de' => 'de',
  'el' => 'el',
  'ga' => 'ga',
  'it' => 'it',
  'he' => 'he',
  'hu' => 'hu',
  'no' => 'no',
  'pl' => 'pl',
  'pt' => 'pt',
  'ro' => 'ro',
  'es' => 'es',
  'se' => 'sv',
  'si' => 'sl',
);
$SITE['RTL-LANG'] = ',he,jp,cn,';  // languages that use right-to-left order
$SITE['CSS-RTL'] = 'RTL.css';      // default right-to-left override CSS
# DO NOT CHANGE THESE SETTINGS
$SITE['langCharset'] = array( // for languages that DON'T use ISO-8859-1 (latin) set
 'bg' => 'ISO-8859-5',
 'el' => 'ISO-8859-7',
 'he' => 'UTF-8', 
 'hu' => 'ISO-8859-2',
 'ro' => 'ISO-8859-2',
 'pl' => 'ISO-8859-2',
 'si' => 'ISO-8859-2',
 'ru' => 'UTF-8',
 'gr' => 'UTF-8'
);
# DO NOT CHANGE THESE SETTINGS
$SITE['WULanguages'] = array ( // for WeatherUnderground forecast supported languages
  'af' => 'afrikaans',
  'bg' => 'bulgarian',
  'ct' => 'catalan',
  'dk' => 'danish',
  'nl' => 'dutch',
  'en' => 'english',
  'fi' => 'finnish',
  'fr' => 'french',
  'de' => 'deutsch',
  'el' => 'greek',
  'ga' => 'gaelic',
  'he' => 'hebrew',
  'hu' => 'hungarian',
  'it' => 'italian',
  'no' => 'norwegian',
  'pl' => 'polish',
  'pt' => 'portuguese',
  'ro' => 'romanian',
  'es' => 'espanol',
  'se' => 'swedish',
  'si' => 'slovenian',
);
# End - multilanguage support constants
# Now prune the installedLanguages based on langavail selection
$tarray = array(); 
foreach ($SITE['langavail'] as $n => $k) {
  if(isset($SITE['installedLanguages'][$k])) {
    $tarray[$k] = $SITE['installedLanguages'][$k];
  }
}
$SITE['installedLanguages'] = $tarray;
# end prune the installedLanguages based on langavail selection
?>