<?php
############################################################################
# A Project of TNET Services, Inc. and Saratoga-Weather.org (Canada/World-ML template set)
############################################################################
#
#	Project:	Sample Included Website Design
#	Module:		common.php
#	Purpose:	Provides common functions used throughout the website
# 	Authors:	Kevin W. Reed <kreed@tnet.com>
#				TNET Services, Inc.
#               Ken True <webmaster@saratoga-weather.org>
#               Saratoga-Weather.org
#
# 	Copyright:	(c) 1992-2007 Copyright TNET Services, Inc.
############################################################################
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA
############################################################################
#	This document uses Tab 4 Settings
############################################################################
require_once("Settings.php");
global $forwardTrans,$reverseTrans,$missingTrans;
############################################################################
# Version 1.04 - 29-Nov-2011 - improved language translations for conditions
# Version 1.05 - 01-Dec-2012 - minor fix to cGetSeasonInfo
# Version 1.06 - 05-Feb-2013 - added UTF-8 conversion features for ISO-8859-n language files
# Version 1.07 - 09-Feb-2013 - added tman1991 cGetMoonInfo() mods for cell.php add-on
# Version 1.08 - 12-Aug-2015 - fixed deprecated /e preg_replace with preg_replace_callback
# Version 1.09 - 17-Aug-2015 - fixed HTML5 validation for print_language_selects()
# Version 1.10 - 19-Apr-2019 - updated season/moon tables for 2018-2030 years
# Version 1.11 - 07-Dec-2020 - minor fix for PHP 8.0.0
# Version 1.12 - 02-Jun-2021 - added cookie for persistent language selection (lang=)
# Version 1.13 - 29-Nov-2021 - add SameSite=Lax to lang= browser cookie
# Version 1.14 - 27-Dec-2022 - fixes for PHP 8.2
$CMNVersion = 'common.php - Version 1.14 - 27-Dec-2022';
# Common Functions
############################################################################

function Mklink($url, $title, $desc, $remote) {
    global $SITE;

    if ($remote) {
        echo "<div class='link'>";
        echo "<a href='{$url}' title='{$title}' \n";
        echo "$SITE[remote] >";
        echo "$desc</a> <img src=\"./images/offsite.gif\" alt=\"*EXT*\" /></div>";

    } else {
        echo "<a href='{$url}' title='{$title}'>{$desc}</a>";
    }
}

#####################################################################
function langtrans ( $item ) {
  global $LANGLOOKUP,$missingTrans;
  
  if(isset($LANGLOOKUP[$item])) {
     echo $LANGLOOKUP[$item];
  } else {
	 if(isset($item) and $item <> '') {$missingTrans[$item] = true; }
     echo $item;
  }

}
#####################################################################
function langtransstr ( $item ) {
  global $LANGLOOKUP,$missingTrans;
  
  if(isset($LANGLOOKUP[$item])) {
     return $LANGLOOKUP[$item];
  } else {
	  if(isset($item) and $item <> '') {$missingTrans[$item] = true; }
	 return $item;
  }

}
#####################################################################
function langtranssubs ( $item ) {
  global $LANGSUBS;

    reset ($LANGSUBS); // process in order of the file
    $text = $item;
    foreach ($LANGSUBS as $key => $replacement) {
      $text = str_replace($key,$replacement,$text);
    }
	// put back translated text, fixing capitalization for sentence starts (except the first one).
	if(count($LANGSUBS) >=1) {
      $text = preg_replace_callback('!\.\s+([a-z])!s',
	  function ($matches) { return('.  ' . strtoupper($matches[1]) ); }
	  ,$text);
	}
    if (isset($_REQUEST['debug']) ) { echo "<!-- translated '$item' to '$text' -->\n"; }
 
  return($text);

}

#####################################################################
# load language translation settings (if any)

function load_langtrans () {
  global $LANGLOOKUP, $LANGSUBS, $SITE, $forwardTrans, $reverseTrans, $missingTrans,$CMNVersion;
  $LANGLOOKUP = array();
  $LANGSUBS = array();
  $forwardTrans = array();
  $reverseTrans = array();
  $missingTrans = array();
  
  $Status = "<!-- $CMNVersion -->\n";

  $lang = 'en'; // default language
	$setCookieLang = false;
	$Status .= "<!-- input load_langtrans ";
  if (isset($SITE['lang']) ) {    $lang_input = strtolower($SITE['lang']); 
	   $Status .= "SITE['lang'] = '".$SITE['lang']."; ";}
  if (isset($_SESSION['lang'])) { $lang_input = strtolower($_SESSION['lang']); 
	   $Status .= "SESSION['lang'] = '".$_SESSION['lang']."; ";}
	if (isset($_COOKIE['lang']))  { $lang_input = strtolower($_COOKIE['lang']); 
	   $Status .= "COOKIE['lang'] = '".$_COOKIE['lang']."; ";}
  if (isset($_REQUEST['lang'])) { $lang_input = strtolower($_REQUEST['lang']); $setCookieLang = true;
	   $Status .= "REQUEST['lang'] = '".$_REQUEST['lang']."; ";}
		 $Status .= " -->\n";

  # allow valid input only
  # allows en, en-us, en-gb, dk, etc.
  if ((isset($lang_input) && preg_match('/^[a-z]{2}$/', $lang_input))
  || (isset($lang_input) && preg_match('/^[a-z]{2}-[a-z]{2}$/', $lang_input)) ) {
		$Status .= "<!-- lang_input='$lang_input' used for \$lang -->\n";
    $lang = $lang_input;
  }
#  if($setCookieLang or !isset($_COOKIE['lang']) or $_COOKIE['lang'] !== $lang) {
		$Status .= "<!-- set_lang_cookie ".set_lang_cookie('lang',$lang)." -->\n";;
#		$_COOKIE['lang'] = $lang;
#	}

  # always store in a PHP Session
  $_SESSION['lang'] = $lang;
  $_REQUEST['lang'] = $lang;  // establish for other scripts too
  $SITE['lang'] = $lang;

	$Status .= "<!-- result load_langtrans ";
  if (isset($SITE['lang']) ) { 
	   $Status .= "SITE['lang'] = '".$SITE['lang']."; ";}
  if (isset($_SESSION['lang'])) { 
	   $Status .= "SESSION['lang'] = '".$_SESSION['lang']."; ";}
	if (isset($_COOKIE['lang']))  { 
	   $Status .= "COOKIE['lang'] = '".$_COOKIE['lang']."; ";}
  if (isset($_REQUEST['lang'])) {
	   $Status .= "REQUEST['lang'] = '".$_REQUEST['lang']."; ";}
		 $Status .= " -->\n";


  $langfile = 'language-' . $lang . '.txt';
  if (! file_exists($langfile) ) {
		$Status .= "<!-- load_langtrans fail. language-$lang.txt file not found. Using 'en' for language -->\n";
    $_SESSION['lang'] = 'en';
    $_REQUEST['lang'] = 'en';  // establish for other scripts too
    $Status .= "<!-- load_langtrans finished -->\n";
    if (isset($_REQUEST['debug']) ) { echo $Status; }
    return; // use english throughout
  }

  $lfile = file($langfile);
  $Status .= "<!-- langfile '$langfile' loading -->\n";
  
  $langfile = 'language-' . $lang . '-local.txt';
  if (file_exists($langfile)) { // load local translation file if it exists
     $lfile2 = file($langfile); 
	 $Status .= "<!-- langfile '$langfile' loading -->\n";
	 $lfile = array_merge($lfile,$lfile2);
   }
/*  $langfile = 'plaintext-parser-lang-' . $lang . '.txt';
   if (file_exists($langfile)) { // load local translation file if it exists
     $lfile2 = file($langfile); 
	 $Status .= "<!-- langfile '$langfile' loading -->\n";
	 $lfile = array_merge($lfile,$lfile2);
   }
*/  
  $n = 0;
  $nsite = 0;
  $nlang = 0;
  $langDefaultCharset = 'iso-8859-1';  // overall default
  $transtable = get_html_translation_table(HTML_ENTITIES,ENT_NOQUOTES);
  $doLoadTrans = false;
  
  foreach ($lfile as $rec) {  // process the control file(s)
    $recin = trim($rec);
	if (substr($recin,0,1) <> '#' and $recin <> '') { // process non blank, non comment records
		list($type, $item,$translation) = explode('|',$recin . '|||||');
		$type = trim($type);
		$item = trim($item);
		$translation = trim($translation);
		if (strtolower($type) == 'conditions' and strtolower($item) == 'begin' ) {
		    $doLoadTrans = true;
			continue;
		}
		if (strtolower($type) == 'conditions' and strtolower($item) == 'end' ) {
		    $doLoadTrans = false;
			continue;
		}
		if (isset($type) and strtolower($type) == 'charset' and isset($item)) {
			$SITE['charset'] = trim($item);
			$SITE['langDefaultCharset'] = trim($item); // save for language conversion
			$Status .= "<!-- using charset '" . $SITE['charset'] . "' -->\n";
			continue;
		}
		if ($type == 'langlookup' and $item and $translation) {
			$LANGLOOKUP[$item] = strtr($translation,$transtable);
			$LANGLOOKUP[$item] = $translation;
			$LANGLOOKUP[$item] = preg_replace('|\&amp;nbsp;|Uis','&nbsp;',$LANGLOOKUP[$item]);
			if ($doLoadTrans) {
			   $forwardTrans[$item] = $translation;
			   $reverseTrans[$translation] = $item;
			   $t = ucfirst(strtolower($item));
			   if(strcmp($item,$t) !== 0) {
			     $LANGLOOKUP[$t]= $translation; // add case normalize translation English term
			     $Status .= "<!-- added langlookup|$t|$translation| for '$item' entry -->\n";
			   }
			   $t = strtolower($item);
			   if(strcmp($item,$t) !== 0) {
			     $LANGLOOKUP[$t]= $translation; // add case normalize translation English term
			     $Status .= "<!-- added langlookup|$t|$translation| for '$item' entry -->\n";
			   }
			   $t = ucwords(strtolower($item));
			   if(strcmp($item,$t) !== 0) {
			     $LANGLOOKUP[$t]= $translation; // add case normalize translation English term
			     $Status .= "<!-- added langlookup|$t|$translation| for '$item' entry -->\n";
			   }
			}
			$n++;
			continue;
		}
		if ($type == 'lang' and $item and $translation) {
		  $LANGSUBS[$item] = $translation;
		  $nlang++;
		} 
		
		if ($type == 'site' and $item and $translation) {
			$SITE[$item] = $translation;
			$nsite++;
			$Status .= "<!-- override site['$item'] = '$translation' -->\n";
			continue;
		}
	
	} // end if nonblank, non comment

  } // end foreach

  $Status .= "<!-- loaded $n langtrans entries, $nsite site entries -->\n";
  if (isset($SITE['langCharset'][$lang])) {
    $SITE['charset'] = $SITE['langCharset'][$lang];
  }
  $SITE['lang'] = $lang;
  
  # Change WU Forecast url for language if we have enough data for it
 if(isset($SITE['fcsturlWU']) and isset($SITE['WULanguages'][$lang])) {
    $Status .= "<!-- WU URL is '".$SITE['fcsturlWU']."' -->\n";
     $SITE['fcsturlWU'] = preg_replace('|http://([^\.]+)\.(.*)$|is',
	    "http://".$SITE['WULanguages'][$lang].".\\2",
		$SITE['fcsturlWU']);
	$Status .= "<!-- WU URL set to '".$SITE['fcsturlWU']."' -->\n";
  
  }
  if (isset($_SESSION['lastLang']) and $_SESSION['lastLang'] <> $lang) {
    $_REQUEST['force'] = "1";  // trick WU-forecast into cache reload with new language
	$Status .= "<!-- lastLang ='".$_SESSION['lastLang'].
	"' - requesting cache refresh for WU-forecast for $lang=".$SITE['WULanguages'][$lang]." -->\n";
  }
   $_SESSION['lastLang'] = $lang;
   
   $SITE['xlateCOP'] = langtransstr('Chance of precipitation');

   if(file_exists("language-".$lang.".js")) { // load extra translations from JavaScript spec
     $langEntries = array(
	   'langMonths',
	   'langDays',
	   'langUVWords',
	   'langBaroTrend',
	   'langBeaufort',
	   'langHeatWords',
	   'langWindDir'
	 );
	 $Status .= "<!-- loading language-$lang.js -->\n";  
     $jsfile = file("language-".$lang.".js");
     $recs = implode('',$jsfile);
     $recs = preg_replace('|new Array|Uis','array',$recs);
     $recs = preg_replace('|var lang(\S+)\s+|Uis','$JSV[\'lang\\1\']',$recs);
	 $recs = preg_replace('|langTransLookup|Uis','// langTransLookup',$recs);
	 if(isset($_REQUEST['show']) and $_REQUEST['show'] == 'eval') {
		 print "<!-- eval request is\n".print_r($recs,true)."\n-->\n";
	 }
     eval($recs);
     foreach ($langEntries as $l) {
        if(isset($JSV[$l])) {
  		  $SITE[$l] = $JSV[$l];
		  $Status .= "<!-- $l='" . print_r($SITE[$l],true) . " -->\n";
	    } else {
		  $Status .= "<!-- '$l' not found in JS conversion -->\n";
		}
     }
	 if(isset($SITE['langWindDir'])) { // use JavaScript winddir defs for PHP too
	   $cardinalDirs = array(
	   	"N", "NNE", "NE", "ENE", 
		"E", "ESE", "SE", "SSE", 
		"S", "SSW", "SW", "WSW", 
		"W", "WNW", "NW", "NNW");
	 
	   foreach ($SITE['langWindDir'] as $n => $trans) {
	     $LANGLOOKUP[$cardinalDirs[$n]] = $trans;
	   
	   }
	 
	 } // if translated winddirs exist
	 
	 if(isset($SITE['langHeatWords'])) { // use JavaScript defs for PHP too
	   $engWords = array(
	    'Unknown', 'Extreme Heat Danger', 'Heat Danger', 'Extreme Heat Caution', 'Extremely Hot', 
		'Uncomfortably Hot',  'Hot', 'Warm', 
		'Comfortable', 
		'Cool', 'Cold', 'Uncomfortably Cold', 'Very Cold', 'Extreme Cold'
		);
	   foreach ($SITE['langHeatWords'] as $n => $trans) {
	     $LANGLOOKUP[$engWords[$n]] = $trans;
	   
	   }
	 } // if translated langHeatWords exist
	 
	 if(isset($SITE['langBeaufort'])) { // use JavaScript defs for PHP too
	   $engWords = array ( /* Beaufort 0 to 12 in array */
		 "Calm", "Light air", "Light breeze", "Gentle breeze", "Moderate breeze", "Fresh breeze",
		 "Strong breeze", "Near gale", "Gale", "Strong gale", "Storm",
		 "Violent storm", "Hurricane"
	   );
	   foreach ($SITE['langBeaufort'] as $n => $trans) {
	     $LANGLOOKUP[$engWords[$n]] = $trans;
	   
	   }
	 } // if translated langBeaufort exists

	 if(isset($SITE['langMonths'])) { // use JavaScript defs for PHP too
	   $engWords = array ( /* English Months */
	   "January","February","March","April","May",
			"June","July","August","September","October","November","December");
	   foreach ($SITE['langMonths'] as $n => $trans) {
	     $LANGLOOKUP[ucwords($engWords[$n])] = $trans;
	   
	   }

     } // if translated langMonths exists

	 if(isset($SITE['langDays'])) { // use JavaScript defs for PHP too
	   $engWords = array ( /* English Days */
	   "Sun","Mon","Tue","Wed","Thu","Fri","Sat","Sun");
	   foreach ($SITE['langDays'] as $n => $trans) {
	     $LANGLOOKUP[ucwords($engWords[$n])] = $trans;
	   
	   }

     } // if translated langDays exists

	 if(isset($SITE['langBaroTrend'])) { // use JavaScript defs for PHP too
	   $engWords = array ( /* English Baro Trend */
	   "Steady", "Rising Slowly", "Rising Rapidly", "Falling Slowly", "Falling Rapidly");
	   foreach ($SITE['langBaroTrend'] as $n => $trans) {
	     $LANGLOOKUP[$engWords[$n]] = $trans;
//	     $Status .= "<!-- baro '".$engWords[$n]."'='$trans' -->\n";
	   }

     } // if translated langBaroTrend exists
	 
   }
   
   $Status .= "<!-- load_langtrans finished -->\n";
   if (isset($_REQUEST['debug']) ) { echo $Status; }

} // end load_langtrans function
#####################################################################
# set a language memory cookie
function set_lang_cookie ($name, $val) {
   global $SITE;
   # this function sets a cookie
   # set expiration date 6 months ahead
   $expire = strtotime("+6 month");
   if ( isset($SITE['cookie_path']) ) {
     $path = $SITE['cookie_path']; // this can be set in Settings.php
   } else {
     $scripturlparts = explode('/', $_SERVER['PHP_SELF']);
     $scriptfilename = $scripturlparts[count($scripturlparts)-1];
     $scripturl = preg_replace("/$scriptfilename$/i", '', $_SERVER['PHP_SELF']);
     $path = $scripturl;
   }
 
   if(!headers_sent()) {
			 if(PHP_VERSION_ID < 70300) {
				 $didit = setcookie("$name",$val,$expire,$path.'; SameSite=Lax');
			 } else {
				 $arr_cookie_options = array (
						'expires' => $expire,
						'path' => $path,
					#	'domain' => $_SERVER['SERVER_NAME'], // leading dot for compatibility or use subdomain
						'secure' => false,     // or true
						'httponly' => false,    // or true
						'samesite' => 'Lax' // None || Lax  || Strict
				 );
				 $didit = setcookie("$name",$val,$arr_cookie_options);
			 }
		 } else {
		 $didit = false;
		 }
	$rtn = "setcookie('$name','$val'); ";
  $rtn .= $didit?"was successful":"was NOT successful";
	return($rtn);
}

#####################################################################
# convert language translation to UTF-8

function set_langtrans_UTF8 () {
  global $LANGLOOKUP, $LANGSUBS, $SITE, $forwardTrans, $reverseTrans;
  if($SITE['charset'] == 'UTF-8') {
	  if (isset($_REQUEST['debug']) ) { echo "<!-- set_langtrans_UTF8 no conversion performed -- already in UTF-8 -->\n"; }
	  $SITE['origCharset'] = $SITE['charset'];
	  return; // nothing to do
  }
  $Debug = '';
  // convert the existing $LANGLOOKUP array to UTF-8
  $sceCharset = $SITE['charset'];
  $SITE['convertJS'] = true;  // indicate that the language-LL.js is to be converted too
  
  foreach ($LANGLOOKUP as $english => $trans) {
	  $LANGLOOKUP[$english] = iconv($sceCharset,'UTF-8//TRANSLIT',$trans);
  }
  $Debug .= "<!-- set_langtrans_UTF8 - converted '$sceCharset' to 'UTF-8' for ".count($LANGLOOKUP). " langtrans entries -->\n";
  // now convert the other likely used items created by load_langtrans
       $langEntries = array(
	   'langMonths',
	   'langDays',
	   'langUVWords',
	   'langBaroTrend',
	   'langBeaufort',
	   'langHeatWords',
	   'langWindDir'
	 );

  foreach ($langEntries as $idx => $key) {
	  
	  if(isset($SITE[$key])) {
		  foreach ($SITE[$key] as $n => $val) {
			  $SITE[$key][$n] = iconv($sceCharset,'UTF-8//TRANSLIT',$val);
		  }
		  $Debug .= "<!-- set_langtrans_UTF8 - converted ".count($SITE[$key])." SITE $key for UTF-8 -->\n";
	  }
  }
  if(count($forwardTrans)>0) {
	foreach ($forwardTrans as $english => $trans) {
		$forwardTrans[$english] = iconv($sceCharset,'UTF-8//TRANSLIT',$trans);
	}
	$Debug .= "<!-- set_langtrans_UTF8 - converted forwardTrans to 'UTF-8' for ".count($forwardTrans). " langtrans entries -->\n";
  }
    
  $SITE['origCharset'] = $sceCharset; // remember the original character set
  $SITE['charset'] = 'UTF-8'; // change our default character set
  if (isset($_REQUEST['debug']) ) { echo $Debug; }
	
}  // end set_langtrans_UTF8 function
#####################################################################

function print_language_selects() {
	global $SITE;
	if(isset($SITE['languageSelectDropdown']) and $SITE['languageSelectDropdown'] ) {
		$stuff = print_language_selects_dropdown();
	} else {
		$stuff = print_language_selects_linear();
	}
	return $stuff;
}

#####################################################################

# this function by mchallis prints (language selection flags or language selection text links)
# based on the setting $SITE['useLanguageFlags'] true or false
function print_language_selects_linear() {
 global $SITE;

 $string1 = '';
 $arr = $SITE['installedLanguages'];
 if (is_array($arr)){
    foreach ($arr as $k => $v) {

      if($SITE['useLanguageFlags'] == true) {
           $string .= '<a href="'. $_SERVER['PHP_SELF'] .'?lang='.$k.'" title="'. $v .'">
<img src="'. $SITE['imagesDir'] . 'flag-'. $k .'.gif" alt="'. $v .'" title="'. $v .'" style="border: none;" /></a>
';
      }else{
           $k_print = $k;
           if ($SITE['lang'] == $k) {
              $k_print = "[$k]";
            }
            $string .= '<a href="'. $_SERVER['PHP_SELF'] .'?lang='.$k.'" title="'. $v .'">
<span style="font-size: 10px">'. $k_print .'</span></a>
';
     }
    } // end foreach

    # text links use bracket for indicator, image links print lang code
    if($SITE['useLanguageFlags'] == true) {
          $string1 = '<span style="font-size: 10px">' . langtransstr('Language') .': ' . $_REQUEST['lang'] . '</span> ' . $string;
    }else{
          $string1 = '<span style="font-size: 10px">' . langtransstr('Language') .': </span>' . $string;

    }
 } // end is_array
 return $string1;

}// end print_language_selects_linear function
#####################################################################
# this function by mchallis prints (language selection flags or language selection text links)
# based on the setting $SITE['useLanguageFlags'] true or false
# Revised by K. True for option box display instead.
function print_language_selects_dropdown() {
 global $SITE;
 $use_onchange_submit = true;
 if(isset($SITE['languageSelectButton']) ) {
	$use_onchange_submit = ! $SITE['languageSelectButton'];
 }
 $string1 = '';
 $arr = $SITE['installedLanguages'];
 if (is_array($arr)){
    $string = '
<form method="get" name="lang_select" action="#" style="padding: 0px; margin: 0px">
';
    # text links use bracket for indicator, image links print lang code
    if($SITE['useLanguageFlags'] == true) {
          $string .= '<span style="font-size: 10px">' . langtransstr('Language') .':&nbsp; </span>';
    }else{
          $string .= '<span style="font-size: 10px">' . langtransstr('Language') .':&nbsp; </span>';

    }

if($use_onchange_submit == false) {
$string .= '<select id="lang" name="lang" style="font-size: 9px; padding: 0px; margin: 0px;">
';
}
else {
$string .= '<select id="lang" name="lang"  style="font-size: 9px" onchange="this.form.submit();">';
}
$flag = '';
    foreach ($arr as $k => $v) {

	  if($SITE['lang'] == $k) {
	    $selected = ' selected="selected"';
        $flag = '<img src="'. $SITE['imagesDir'] . 'flag-'. $k .'.gif" alt="'. $v .'" title="'. $v .'" style="border: none" />';
	  } else {
	    $selected = '';
	  }
      $string .= '<option value="'.$k.'"'.$selected.'>'.$v.'</option>'."\n";
    } // end foreach
	$string .= '</select>
';
if($use_onchange_submit == false) {
  $string .= '<input type="submit" name="' . langtransstr('Set') .'" value="' . langtransstr('Set') .'" style="font-size: 9px" />';
} else {
  $string .= '<noscript><input type="submit" name="' . langtransstr('Set') .'" value="' . langtransstr('Set') .'" style="font-size: 9px" /></noscript>';
	
}
    # text links use bracket for indicator, image links print lang code
    if($SITE['useLanguageFlags'] == true) {
          $string .= '  ' . $flag;
    }else{
          $string .= ' ';

    }

$string .= '
</form>';


 } // end is_array
 return $string;

}// end print_language_selects_dropdown function

# load the language translation file and set up $LANGLOOKUP array
load_langtrans();

// -----------------------------------------------------------------------------
// MOON FUNTIONS  Courtesy of Bashewa Weather, PHP conversion by WebsterWeather from ajaxWDwx.js V9.13 (WD)                                                             .
// -----------------------------------------------------------------------------
function cGetMoonInfo ($hh=0,$mm=0,$ss=0,$MM=0,$DD=0,$YY=0) { // very crude way of determining moon phase (but very accurate)
// ------------- start of USNO moon data -----------------------------
// PHP tables generated from USNO moon ephemeris data https://api.usno.navy.mil/moon/phase?date=1/1/yyyy&nump=60
// for years 2009 to 2030 
// Ken True - Saratoga-weather.org generated by get-USNO-moonphases.php - Version 2.01 - 19-Apr-2019 on 19 April 2019 09:31 PDT

$newMoons = array( // unixtime values in UTC/GMT
/* 2009 */ /* 26-Jan-2009 07:55 */ 1232956500, 1235525700, 1238083560, 1240629780, 1243167060, 1245699300, 1248230100, 1250762520, 1253299440, 1255843980, 1258398840, 1260964920, 
/* 2010 */ /* 15-Jan-2010 07:11 */ 1263539460, 1266115860, 1268686860, 1271248140, 1273799040, 1276341300, 1278877200, 1281409680, 1283941800, 1286477040, 1289019120, 1291570560, 
/* 2011 */ /* 04-Jan-2011 09:03 */ 1294131780, 1296700260, 1299271560, 1301841120, 1304405460, 1306962180, 1309510440, 1312051200, 1314587040, 1317121740, 1319658960, 1322201400, 1324749960, 
/* 2012 */ /* 23-Jan-2012 07:39 */ 1327304340, 1329863700, 1332427020, 1334992680, 1337557620, 1340118120, 1342671840, 1345218840, 1347761460, 1350302520, 1352844480, 1355388120, 
/* 2013 */ /* 11-Jan-2013 19:44 */ 1357933440, 1360480800, 1363031460, 1365586500, 1368145680, 1370706960, 1373267640, 1375825860, 1378380960, 1380933240, 1383483000, 1386030120, 
/* 2014 */ /* 01-Jan-2014 11:14 */ 1388574840, 1391117880, 1393660800, 1396205100, 1398752040, 1401302400, 1403856480, 1406414520, 1408975980, 1411539240, 1414101420, 1416659520, 1419212160, 
/* 2015 */ /* 20-Jan-2015 13:14 */ 1421759640, 1424303220, 1426844160, 1429383420, 1431922380, 1434463500, 1437009840, 1439563980, 1442126460, 1444694760, 1447264020, 1449829740, 
/* 2016 */ /* 10-Jan-2016 01:30 */ 1452389400, 1454942340, 1457488440, 1460028240, 1462562940, 1465095600, 1467630060, 1470170640, 1472720580, 1475280660, 1477849080, 1480421880, 1482994380, 
/* 2017 */ /* 28-Jan-2017 00:07 */ 1485562020, 1488121080, 1490669820, 1493208960, 1495741440, 1498271460, 1500803160, 1503340200, 1505885400, 1508440320, 1511005320, 1513578600, 
/* 2018 */ /* 17-Jan-2018 02:17 */ 1516155420, 1518728700, 1521292320, 1523843820, 1526384880, 1528918980, 1531450080, 1533981480, 1536516060, 1539056820, 1541606520, 1544167200, 
/* 2019 */ /* 06-Jan-2019 01:28 */ 1546738080, 1549314240, 1551888240, 1554454200, 1557009900, 1559556120, 1562094960, 1564629120, 1567161420, 1569695160, 1572233880, 1574780760, 1577337180, 
/* 2020 */ /* 24-Jan-2020 21:42 */ 1579902120, 1582471920, 1585042080, 1587608760, 1590169140, 1592721660, 1595266380, 1597804920, 1600340400, 1602876660, 1605416820, 1607962560, 
/* 2021 */ /* 13-Jan-2021 05:00 */ 1610514000, 1613070360, 1615630860, 1618194660, 1620759600, 1623322380, 1625879820, 1628430600, 1630975920, 1633518300, 1636060440, 1638603780, 
/* 2022 */ /* 02-Jan-2022 18:33 */ 1641148380, 1643694360, 1646242500, 1648794240, 1651350480, 1653910200, 1656471120, 1659030900, 1661588220, 1664142840, 1666694940, 1669244220, 1671790620, 
/* 2023 */ /* 21-Jan-2023 20:53 */ 1674334380, 1676876760, 1679419380, 1681963920, 1684511580, 1687063020, 1689618720, 1692178680, 1694742000, 1697306100, 1699867620, 1702423920, 
/* 2024 */ /* 11-Jan-2024 11:57 */ 1704974220, 1707519540, 1710061200, 1712600460, 1715138520, 1717677480, 1720220220, 1722769980, 1725328500, 1727894940, 1730465220, 1733034060, 1735597620, 
/* 2025 */ /* 29-Jan-2025 12:36 */ 1738154160, 1740703500, 1743245880, 1745782260, 1748314920, 1750847460, 1753384260, 1755929160, 1758484440, 1761049500, 1763621220, 1766194980, 
/* 2026 */ /* 18-Jan-2026 19:52 */ 1768765920, 1771329660, 1773883380, 1776426720, 1778961660, 1781492040, 1784022180, 1786556220, 1789097220, 1791647400, 1794207720, 1796777520, 
/* 2027 */ /* 07-Jan-2027 20:24 */ 1799353440, 1801929360, 1804498140, 1807055460, 1809601080, 1812138000, 1814670120, 1817201100, 1819734060, 1822271760, 1824816960, 1827372240, 1829938320, 
/* 2028 */ /* 26-Jan-2028 15:12 */ 1832512320, 1835087820, 1837657860, 1840218420, 1842768960, 1845311220, 1847847720, 1850381040, 1852914240, 1855450620, 1857993480, 1860545160, 
/* 2029 */ /* 14-Jan-2029 17:24 */ 1863105840, 1865673060, 1868242740, 1870810800, 1873374120, 1875930600, 1878479460, 1881021360, 1883558640, 1886094840, 1888633440, 1891176720, 
/* 2030 */ /* 04-Jan-2030 02:49 */ 1893725340, 1896278820, 1898836440, 1901397720, 1903961520, 1906525260, 1909085640, 1911640260, 1914188820, 1916733240, 1919276220, 1921819560, 1924363920
 ); /* end of newMoons array */

$Q1Moons = array( // unixtime values in UTC/GMT
/* 2009 */ /* 02-Feb-2009 23:13 */ 1233616380, 1236152760, 1238682840, 1241210640, 1243740120, 1246274880, 1248818400, 1251373320, 1253940600, 1256517720, 1259098740, 1261676160, 
/* 2010 */ /* 23-Jan-2010 10:53 */ 1264243980, 1266799320, 1269342000, 1271874000, 1274398980, 1276921740, 1279447800, 1281982440, 1284529800, 1287091620, 1289666340, 1292248740, 
/* 2011 */ /* 12-Jan-2011 11:31 */ 1294831860, 1297408680, 1299973500, 1302523500, 1305059580, 1307585460, 1310106540, 1312628880, 1315157940, 1317698100, 1320251880, 1322819520, 
/* 2012 */ /* 01-Jan-2012 06:15 */ 1325398500, 1327983000, 1330564860, 1333136460, 1335693420, 1338236160, 1340767800, 1343292960, 1345816440, 1348342860, 1350876720, 1353421860, 1355980740, 
/* 2013 */ /* 18-Jan-2013 23:45 */ 1358552700, 1361133060, 1363714020, 1366288260, 1368851640, 1371403440, 1373944680, 1376477760, 1379005680, 1381532520, 1384063020, 1386601920, 
/* 2014 */ /* 08-Jan-2014 03:39 */ 1389152340, 1391714520, 1394285220, 1396859460, 1399432500, 1402000740, 1404561540, 1407113400, 1409656260, 1412191980, 1414723680, 1417255560, 1419791460, 
/* 2015 */ /* 27-Jan-2015 04:48 */ 1422334080, 1424884440, 1427442180, 1430006100, 1432574340, 1435143720, 1437710640, 1440271860, 1442825940, 1445373060, 1447914420, 1450451640, 
/* 2016 */ /* 16-Jan-2016 23:26 */ 1452986760, 1455522360, 1458061380, 1460606340, 1463158920, 1465719000, 1468284720, 1470853260, 1473421740, 1475987580, 1478548260, 1481101380, 
/* 2017 */ /* 05-Jan-2017 19:47 */ 1483645620, 1486181940, 1488713520, 1491244740, 1493779620, 1496320920, 1498870260, 1501428180, 1503994380, 1506567180, 1509142920, 1511715780, 1514280000, 
/* 2018 */ /* 24-Jan-2018 22:20 */ 1516832400, 1519373340, 1521905700, 1524433560, 1526960940, 1529491860, 1532029920, 1534578480, 1537139700, 1539712920, 1542293640, 1544874540, 
/* 2019 */ /* 14-Jan-2019 06:45 */ 1547448300, 1550010360, 1552559220, 1555095960, 1557623520, 1560146340, 1562669700, 1565199060, 1567739400, 1570294020, 1572862980, 1575442680, 
/* 2020 */ /* 03-Jan-2020 04:45 */ 1578026700, 1580607720, 1583179020, 1585736460, 1588279080, 1590809400, 1593332160, 1595853120, 1598378280, 1600912500, 1603459380, 1606020300, 1608594060, 
/* 2021 */ /* 20-Jan-2021 21:01 */ 1611176460, 1613760420, 1616337600, 1618901940, 1621451580, 1623988440, 1626516660, 1629040740, 1631565540, 1634095500, 1636634760, 1639186500, 
/* 2022 */ /* 09-Jan-2022 18:11 */ 1641751860, 1644328200, 1646909100, 1649486880, 1652055660, 1654613280, 1657160040, 1659697560, 1662228480, 1664756040, 1667284620, 1669818960, 1672363200, 
/* 2023 */ /* 28-Jan-2023 15:19 */ 1674919140, 1677485160, 1680057120, 1682630400, 1685200920, 1687765800, 1690322820, 1692871020, 1695411120, 1697945340, 1700477400, 1703011140, 
/* 2024 */ /* 18-Jan-2024 03:52 */ 1705549920, 1708095660, 1710648660, 1713208380, 1715773680, 1718342280, 1720910940, 1723475940, 1726034700, 1728586500, 1731131700, 1733671560, 
/* 2025 */ /* 06-Jan-2025 23:56 */ 1736207760, 1738742520, 1741278660, 1743819300, 1746366720, 1748922060, 1751484600, 1754052060, 1756621500, 1759190040, 1761754860, 1764313140, 1766862600, 
/* 2026 */ /* 26-Jan-2026 04:47 */ 1769402820, 1771936020, 1774466280, 1776997920, 1779534660, 1782078900, 1784631900, 1787193960, 1789764240, 1792339920, 1794916080, 1797486120, 
/* 2027 */ /* 15-Jan-2027 20:34 */ 1800045240, 1802591880, 1805127900, 1807656960, 1810183440, 1812711360, 1815244740, 1817787240, 1820341860, 1822909620, 1825488000, 1828070520, 
/* 2028 */ /* 05-Jan-2028 01:40 */ 1830649200, 1833217800, 1835773320, 1838315700, 1840847160, 1843371360, 1845893400, 1848418800, 1850952960, 1853500200, 1856062380, 1858637640, 1861220700, 
/* 2029 */ /* 22-Jan-2029 19:23 */ 1863804180, 1866381000, 1868945580, 1871495400, 1874031360, 1876557240, 1879078440, 1881600900, 1884130140, 1886670540, 1889224500, 1891792140, 
/* 2030 */ /* 11-Jan-2030 14:06 */ 1894370760, 1896954540, 1899535620, 1902106620, 1904663460, 1907206560, 1909738920, 1912264980, 1914789300, 1917316560, 1919850960, 1922396220, 1924954560
 ); /* end of Q1Moons array */

$fullMoons = array( // unixtime values in UTC/GMT
/* 2009 */ /* 09-Feb-2009 14:49 */ 1234190940, 1236739080, 1239288960, 1241841660, 1244398320, 1246958460, 1249520100, 1252080180, 1254636600, 1257189240, 1259739000, 1262286780, 
/* 2010 */ /* 30-Jan-2010 06:18 */ 1264832280, 1267375080, 1269915900, 1272457080, 1275001620, 1277551800, 1280108220, 1282669500, 1285233420, 1287797760, 1290360420, 1292919180, 
/* 2011 */ /* 19-Jan-2011 21:21 */ 1295472060, 1298018160, 1300558200, 1303094640, 1305630540, 1308168840, 1310712000, 1313261820, 1315819620, 1318385160, 1320956160, 1323527760, 
/* 2012 */ /* 09-Jan-2012 07:30 */ 1326094200, 1328651640, 1331199540, 1333739940, 1336275300, 1338808320, 1341341520, 1343878020, 1346421480, 1348975140, 1351540140, 1354113960, 1356690060, 
/* 2013 */ /* 27-Jan-2013 04:38 */ 1359261480, 1361823960, 1364376420, 1366919820, 1369455900, 1371987120, 1374516900, 1377049500, 1379589180, 1382139480, 1384701360, 1387272480, 
/* 2014 */ /* 16-Jan-2014 04:52 */ 1389847920, 1392421980, 1394989680, 1397547720, 1400094960, 1402632660, 1405164300, 1407694140, 1410226680, 1412765460, 1415312580, 1417868820, 
/* 2015 */ /* 05-Jan-2015 04:53 */ 1420433580, 1423004940, 1425578700, 1428149100, 1430710920, 1433261940, 1435803600, 1438339380, 1440873300, 1443408600, 1445947500, 1448491440, 1451041860, 
/* 2016 */ /* 24-Jan-2016 01:46 */ 1453599960, 1456165200, 1458734460, 1461302640, 1463865240, 1466420520, 1468968960, 1471512360, 1474052700, 1476591780, 1479131520, 1481673900, 
/* 2017 */ /* 12-Jan-2017 11:34 */ 1484220840, 1486773180, 1489330440, 1491890880, 1494452520, 1497013800, 1499573220, 1502129460, 1504681380, 1507228800, 1509772980, 1512316020, 
/* 2018 */ /* 02-Jan-2018 02:24 */ 1514859840, 1517405220, 1519951860, 1522499820, 1525049880, 1527603540, 1530161580, 1532722800, 1535284560, 1537843920, 1540399500, 1542951540, 1545500940, 
/* 2019 */ /* 21-Jan-2019 05:16 */ 1548047760, 1550591580, 1553132580, 1555672320, 1558213860, 1560760260, 1563313080, 1565872140, 1568435580, 1571000880, 1573565640, 1576127520, 
/* 2020 */ /* 10-Jan-2020 19:21 */ 1578684060, 1581233580, 1583776080, 1586313300, 1588848300, 1591384320, 1593924240, 1596470340, 1599024120, 1601586300, 1604155740, 1606728600, 1609298880, 
/* 2021 */ /* 28-Jan-2021 19:16 */ 1611861360, 1614413820, 1616957280, 1619494260, 1622027640, 1624560000, 1627094220, 1629633720, 1632182100, 1634741820, 1637312220, 1639888500, 
/* 2022 */ /* 17-Jan-2022 23:48 */ 1642463280, 1645030560, 1647587820, 1650135300, 1652674440, 1655207520, 1657737480, 1660268160, 1662803940, 1665348900, 1667905320, 1670472480, 
/* 2023 */ /* 06-Jan-2023 23:08 */ 1673046480, 1675621680, 1678192800, 1680755640, 1683308040, 1685850120, 1688384340, 1690914720, 1693445700, 1695981420, 1698524640, 1701076560, 1703637180, 
/* 2024 */ /* 25-Jan-2024 17:54 */ 1706205240, 1708777800, 1711350000, 1713916140, 1716472380, 1719018480, 1721557020, 1724091960, 1726626840, 1729164360, 1731706080, 1734253320, 
/* 2025 */ /* 13-Jan-2025 22:27 */ 1736807220, 1739368380, 1741935300, 1744503720, 1747068960, 1749627840, 1752179820, 1754726100, 1757268540, 1759808820, 1762348740, 1764890040, 
/* 2026 */ /* 03-Jan-2026 10:03 */ 1767434580, 1769983740, 1772537880, 1775095920, 1777656180, 1780217100, 1782777360, 1785335760, 1787890680, 1790441340, 1792987920, 1795531980, 1798075680, 
/* 2027 */ /* 22-Jan-2027 12:17 */ 1800620220, 1803165780, 1805712240, 1808260020, 1810810740, 1813365840, 1815925500, 1818487740, 1821049380, 1823608020, 1826162760, 1828714140, 
/* 2028 */ /* 12-Jan-2028 04:03 */ 1831262580, 1833807840, 1836349560, 1838888760, 1841428140, 1843970940, 1846519860, 1849075800, 1851637620, 1854203100, 1856769420, 1859334000, 1861894080, 
/* 2029 */ /* 30-Jan-2029 06:03 */ 1864447380, 1866993000, 1869531960, 1872067020, 1874601420, 1877138520, 1879680960, 1882230660, 1884788940, 1887355620, 1889928180, 1892501160, 
/* 2030 */ /* 19-Jan-2030 15:54 */ 1895068440, 1897626000, 1900173360, 1902712800, 1905247140, 1907779260, 1910311920, 1912848240, 1915391880, 1917946020, 1920511800, 1923086400
 ); /* end of fullMoons array */

$Q3Moons = array( // unixtime values in UTC/GMT
/* 2009 */ /* 16-Feb-2009 21:37 */ 1234820220, 1237398420, 1239975360, 1242545160, 1245104100, 1247651580, 1250189700, 1252721760, 1255251360, 1257782160, 1260317580, 
/* 2010 */ /* 07-Jan-2010 10:39 */ 1262860740, 1265413680, 1267976520, 1270546620, 1273119300, 1275689580, 1278254100, 1280811540, 1283361720, 1285905120, 1288442760, 1290976560, 1293509880, 
/* 2011 */ /* 26-Jan-2011 12:57 */ 1296046620, 1298589960, 1301141220, 1303699620, 1306263120, 1308829680, 1311397320, 1313963640, 1316525940, 1319081400, 1321628940, 1324169280, 
/* 2012 */ /* 16-Jan-2012 09:08 */ 1326704880, 1329239040, 1331774700, 1334314200, 1336859220, 1339411260, 1341971280, 1344538500, 1347110100, 1349681580, 1352248560, 1354807860, 
/* 2013 */ /* 05-Jan-2013 03:58 */ 1357358280, 1359899760, 1362433980, 1364963820, 1367493240, 1370026680, 1372567980, 1375119780, 1377682500, 1380254100, 1382830800, 1385407680, 1387979280, 
/* 2014 */ /* 24-Jan-2014 05:19 */ 1390540740, 1393089300, 1395625560, 1398153120, 1400677140, 1403203140, 1405735680, 1408278360, 1410833100, 1413400320, 1415978100, 1418561460, 
/* 2015 */ /* 13-Jan-2015 09:46 */ 1421142360, 1423713000, 1426268880, 1428810240, 1431340560, 1433864520, 1436387040, 1438912980, 1441446840, 1443992760, 1446553440, 1449128400, 
/* 2016 */ /* 02-Jan-2016 05:30 */ 1451712600, 1454297280, 1456873860, 1459437420, 1461986940, 1464523920, 1467051540, 1469574000, 1472096460, 1474624560, 1477163640, 1479717180, 1482285360, 
/* 2017 */ /* 19-Jan-2017 22:13 */ 1484863980, 1487446380, 1490025480, 1492595820, 1495153980, 1497699180, 1500233160, 1502759700, 1505283900, 1507811100, 1510346160, 1512892260, 
/* 2018 */ /* 08-Jan-2018 22:25 */ 1515450300, 1518018840, 1520594400, 1523171820, 1525745340, 1528309920, 1530863460, 1533406680, 1535942220, 1538473500, 1541004000, 1543537140, 1546076040, 
/* 2019 */ /* 27-Jan-2019 21:10 */ 1548623400, 1551180480, 1553746200, 1556317080, 1558888440, 1561455960, 1564017480, 1566572160, 1569120060, 1571661540, 1574197860, 1576731420, 
/* 2020 */ /* 17-Jan-2020 12:58 */ 1579265880, 1581805020, 1584351240, 1586904960, 1589464980, 1592029440, 1594596540, 1597164300, 1599729960, 1602290340, 1604843160, 1607387760, 
/* 2021 */ /* 06-Jan-2021 09:37 */ 1609925820, 1612460220, 1614994200, 1617530520, 1620071400, 1622618640, 1625173860, 1627737360, 1630307580, 1632880620, 1635451500, 1638016080, 1640571840, 
/* 2022 */ /* 25-Jan-2022 13:41 */ 1643118060, 1645655520, 1648186620, 1650714960, 1653244980, 1655781060, 1658326740, 1660883760, 1663451520, 1666026900, 1668605220, 1671180960, 
/* 2023 */ /* 15-Jan-2023 02:10 */ 1673748600, 1676304060, 1678846080, 1681377060, 1683901680, 1686425460, 1688953680, 1691490480, 1694038860, 1696600080, 1699173420, 1701755340, 
/* 2024 */ /* 04-Jan-2024 03:30 */ 1704339000, 1706915880, 1709479380, 1712027700, 1714562820, 1717089180, 1719611580, 1722135060, 1724664360, 1727203800, 1729756980, 1732325280, 1734905880, 
/* 2025 */ /* 21-Jan-2025 20:31 */ 1737491460, 1740072720, 1742642940, 1745199300, 1747742340, 1750274340, 1752799080, 1755321120, 1757845980, 1760379180, 1762925280, 1765486320, 
/* 2026 */ /* 10-Jan-2026 15:48 */ 1768060080, 1770640980, 1773221880, 1775796660, 1778361000, 1780912800, 1783452540, 1785982860, 1788508260, 1791033900, 1793564880, 1796105280, 1798657140, 
/* 2027 */ /* 29-Jan-2027 10:55 */ 1801220100, 1803791760, 1806368040, 1808943480, 1811512680, 1814072040, 1816620900, 1819160820, 1821694800, 1824226140, 1826758080, 1829293860, 
/* 2028 */ /* 18-Jan-2028 19:26 */ 1831836360, 1834387680, 1836948180, 1839515820, 1842086580, 1844656020, 1847220960, 1849779900, 1852332360, 1854878220, 1857417960, 1859953140, 
/* 2029 */ /* 07-Jan-2029 13:26 */ 1862486760, 1865022720, 1867564260, 1870113060, 1872668880, 1875230340, 1877795820, 1880363700, 1882931580, 1885496220, 1888054320, 1890604080, 1893145740, 
/* 2030 */ /* 26-Jan-2030 18:14 */ 1895681640, 1898215080, 1900749060, 1903286340, 1905829020, 1908379140, 1910938020, 1913505300, 1916078160, 1918651800, 1921221120, 1923782460
 ); /* end of Q3Moons array */

// ------------- end of USNO moon data -----------------------------

   if ($hh==0) $hh=idate("H");
   if ($mm==0) $mm=idate("i");
   if ($ss==0) $ss=idate("s");
   if ($MM==0) $MM=idate("m");
   if ($DD==0) $DD=idate("d");
   if ($YY==0) $YY=idate("Y");

   $date = mktime($hh,$mm,$ss,$MM,$DD,$YY);  // Unix date from local time
	 $info = new STDclass;
   @$info->date = $date;
   $info->datetxt = gmdate('D, d-M-Y H:i T',$date);
   
   if ($date < $newMoons[1]) {
	   $info->error = "Date must be after " .date("r",$newMoons[1]);
	   return $info;
   }
   if ($date > $newMoons[count($newMoons)-1]) {
	   $info->error = "Date must be before ".date("r",$newMoons[count($newMoons)-1]);
	   return $info;
   }

   foreach ($newMoons as $mi=>$newMoon) { // find next New Moon from given date
      if ($newMoon>$date) {break;}
   }
   // Get Moon dates
   $NM = $newMoons [$mi-1]; // previous new moon
   $Q1 = $Q1Moons  [$mi-1]; // 1st Q end
   $Q2 = $fullMoons[$mi-1]; // 2nd Q end - Full moon
   $Q3 = $Q3Moons  [$mi-1]; // 3rd Q end
   $Q4 = $newMoons [$mi  ]; // 4th Q end - next new moon

   // Divide each phase into 7 periods (4 phases x 7 = 28 periods)
   $Q1p = round(($Q1-$NM)/7);
   $Q2p = round(($Q2-$Q1)/7);
   $Q3p = round(($Q3-$Q2)/7);
   $Q4p = round(($Q4-$Q3)/7);

   // Determine start and end times for major phases (lasting 1 period of 28)
   $NMe = $NM+($Q1p/2);                         //  0% .... - New moon
   $Q1s = $Q1-($Q1p/2);  $Q1e = $Q1+($Q2p/2);   // 50% 1stQ - First Quarter
   $Q2s = $Q2-($Q2p/2);  $Q2e = $Q2+($Q3p/2);   //100% 2ndQ - Full moon
   $Q3s = $Q3-($Q3p/2);  $Q3e = $Q3+($Q4p/2);   // 50% 3rdQ - Last Quarter
   $NMs = $Q4-($Q4p/2);                         //  0% 4thQ - New Moon

// Determine age of moon in days since last new moon
   $age = ($date - $newMoons[$mi-1])/86400; // age in days since last new moon
   $dd  = intval($age);
   $hh  = intval(($age-$dd)*24);
   $mm  = intval(((($age-$dd)*24)-$hh)*60);
   $info->age = $dd.' days, '.$hh.' hours, '.$mm.' minutes';

// Illumination
   switch (true) { // Determine moon age in degrees (0 to 360)
   case ($date<=$Q1): $ma = ($date - $NM) * (90 / ($Q1 - $NM))+  0; break; // NM to Q1
   case ($date<=$Q2): $ma = ($date - $Q1) * (90 / ($Q2 - $Q1))+ 90; break; // Q1 to FM
   case ($date<=$Q3): $ma = ($date - $Q2) * (90 / ($Q3 - $Q2))+180; break; // FM to Q3
   case ($date<=$Q4): $ma = ($date - $Q3) * (90 / ($Q4 - $Q3))+270; break; // Q3 to NM
   }
   $info->ill = abs(round(100*(1+cos($ma*(M_PI/180)))/2)-100);

// Deterime picture number (0-27) and moon phase
   switch (true) {
   case ($date<=$NMe): $pic =  0;                        $ph = 'New Moon';          break;
   case ($date< $Q1s): $pic =  1  +(($date-$NMe)/$Q1p);  $ph = 'Waxing Crescent';   break; // Waxing Crescent
   case ($date<=$Q1e): $pic =  7;                        $ph = 'First Quarter';     break;
   case ($date< $Q2s): $pic =  7.5+(($date-$Q1e)/$Q2p);  $ph = 'Waxing Gibbous';    break;
   case ($date<=$Q2e): $pic = 14;                        $ph = 'Full Moon';         break;
   case ($date< $Q3s): $pic = 14.5+(($date-$Q2e)/$Q3p);  $ph = 'Waning Gibbous';    break;
   case ($date<=$Q3e): $pic = 21;                        $ph = 'Last Quarter';      break;
   case ($date< $NMs): $pic = 21.5+(($date-$Q3e)/$Q4p);  $ph = 'Waning Crescent';   break; // Waning Crecent
   default           : $pic =  0;                        $ph = 'New Moon';
   }
   $info->pic   = round($pic);
   $info->phase = $ph;
   $info->NM    = $NM;
   $info->NMGMT    = gmdate('D, d-M-Y H:i T',$NM);
   $info->NMWD    = gmdate('H:i T d F Y',$NM);
   $info->Q1    = $Q1;
   $info->Q1GMT    = gmdate('D, d-M-Y H:i T',$Q1);
   $info->Q1WD    = gmdate('H:i T d F Y',$Q1);
   $info->FM    = $Q2;
   $info->FMGMT    = gmdate('D, d-M-Y H:i T',$Q2);
   $info->FMWD    = gmdate('H:i T d F Y',$Q2);
   $info->Q3    = $Q3;
   $info->Q3GMT    = gmdate('D, d-M-Y H:i T',$Q3);
   $info->Q3WD    = gmdate('H:i T d F Y',$Q3);
   $info->Q4    = $Q4;
   $info->Q4GMT    = gmdate('D, d-M-Y H:i T',$Q4);
   $info->Q4WD    = gmdate('H:i T d F Y',$Q4);
   $info->FM2   = $fullMoons[$mi];
   $info->FM2GMT   = gmdate('D, d-M-Y H:i T',$fullMoons[$mi]);
   $info->FM2WD   = gmdate('H:i T d F Y',$fullMoons[$mi]);

#  tman1991 mods for cell.php add-on 
   $moonD = array($NM       , $Q1            , $Q2        , $Q3           , $Q4       , $Q1Moons[$mi]  , $fullMoons[$mi], $Q3Moons[$mi] );
   $moonP = array("New Moon", "First Quarter", "Full Moon", "Last Quarter", "New Moon", "First Quarter", "Full Moon"    , "Last Quarter");
   $moonI = array("NM"      , "Q1"           , "FM"       , "Q3"          , "NM"      , "Q1"           , "FM"           , "Q3"          );
   foreach($moonD as $key=>$mdate) {
      if ($mdate>$date) {
         $info->moons[] = array ($moonP[$key], $mdate, $moonI[$key], date("r",$mdate));
	  }
   }
#  end tman1991 mods for cell.php add-on 

return $info;
}
// -----------------------------------------------------------------------------
// SEASON FUNCTIONS  return season dates based on USNO dates for Spring, Summer, Fall, Winter                                                             .
// -----------------------------------------------------------------------------
function cGetSeasonInfo ($YY=0) { // feed it the year

$seasonList = array( // seasons from USNO in WD date format
// generated from https://api.usno.navy.mil/seasons?year=YYYY&tz=0&dst=false JSON
// year => 'Spring|Summer|Autumn|Winter' (Northern Hemisphere)
 '2018' => '16:15 GMT 20 March 2018|10:07 GMT 21 June 2018|01:54 GMT 23 September 2018|22:23 GMT 21 December 2018|',
 '2019' => '21:58 GMT 20 March 2019|15:54 GMT 21 June 2019|07:50 GMT 23 September 2019|04:19 GMT 22 December 2019|',
 '2020' => '03:50 GMT 20 March 2020|21:44 GMT 20 June 2020|13:31 GMT 22 September 2020|10:02 GMT 21 December 2020|',
 '2021' => '09:37 GMT 20 March 2021|03:32 GMT 21 June 2021|19:21 GMT 22 September 2021|15:59 GMT 21 December 2021|',
 '2022' => '15:33 GMT 20 March 2022|09:14 GMT 21 June 2022|01:04 GMT 23 September 2022|21:48 GMT 21 December 2022|',
 '2023' => '21:24 GMT 20 March 2023|14:58 GMT 21 June 2023|06:50 GMT 23 September 2023|03:27 GMT 22 December 2023|',
 '2024' => '03:06 GMT 20 March 2024|20:51 GMT 20 June 2024|12:44 GMT 22 September 2024|09:20 GMT 21 December 2024|',
 '2025' => '09:01 GMT 20 March 2025|02:42 GMT 21 June 2025|18:19 GMT 22 September 2025|15:03 GMT 21 December 2025|',
 '2026' => '14:46 GMT 20 March 2026|08:24 GMT 21 June 2026|00:05 GMT 23 September 2026|20:50 GMT 21 December 2026|',
 '2027' => '20:25 GMT 20 March 2027|14:11 GMT 21 June 2027|06:02 GMT 23 September 2027|02:42 GMT 22 December 2027|',
 '2028' => '02:17 GMT 20 March 2028|20:02 GMT 20 June 2028|11:45 GMT 22 September 2028|08:19 GMT 21 December 2028|',
 '2029' => '08:02 GMT 20 March 2029|01:48 GMT 21 June 2029|17:38 GMT 22 September 2029|14:14 GMT 21 December 2029|',
 '2030' => '13:52 GMT 20 March 2030|07:31 GMT 21 June 2030|23:27 GMT 22 September 2030|20:09 GMT 21 December 2030|',
); // end of seasonList
  if($YY<2009) {$YY = idate('Y');} // use current year 
  $info = new stdClass();
  if(!isset($seasonList[$YY])) {
	   $info->error = "Year $YY not in list";
	   return $info;
   }
   list($spring,$summer,$fall,$winter) = explode('|',$seasonList[$YY]);
   $info->spring = $spring;
   $info->summer = $summer;
   $info->fall   = $fall;
   $info->winter = $winter;
   
   return $info;

}

?>