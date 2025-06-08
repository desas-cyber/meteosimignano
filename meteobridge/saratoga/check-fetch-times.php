<?php
$Version = "check-fetch-times.php Version 1.57 - 28-Aug-2024";
/*
Utility diagnostic script to support the Saratoga-Weather.org AJAX/PHP template sets.

Author: Ken True - webmaster@saratoga-weather.org

Note: there are no user customizations expected in this utility.  Please replace the
  entire script with a newer version when available.

*/
//--self downloader --
if(isset($_REQUEST['sce']) and strtolower($_REQUEST['sce']) == 'view') {
   $filenameReal = __FILE__;
   $download_size = filesize($filenameReal);
   header('Pragma: public');
   header('Cache-Control: private');
   header('Cache-Control: no-cache, must-revalidate');
   header("Content-type: text/plain,charset=ISO-8859-1");
   header("Accept-Ranges: bytes");
   header("Content-Length: $download_size");
   header('Connection: close');
   
   readfile($filenameReal);
   exit;
 }
error_reporting(E_ALL);
if(!isset($doQuiet)) {$doQuiet = false;}
if(isset($doVersionCheck) and $doVersionCheck) {
	$_REQUEST['show'] = 'versions';
	$doQuiet = true;
}
global $doQuiet,$quietText,$SITE;
$quietText = ''; // for include-wxstatus display messages
// ------------------------------------------------------------------
if(isset($_REQUEST['show']) and preg_match('|settings|i',$_REQUEST['show'])) {
	$toShow = array("Settings.php","Settings-weather.php","Settings-language.php");
	$doneHeaders = false;
	$doHighlight = preg_match('|settingsr|i',$_REQUEST['show'])?false:true;
	foreach ($toShow as $n => $showFilename) {
	  if(!$doneHeaders) { 
	    printHeaders(); 
		$doneHeaders = true;
		printInfo(); 
	    print "</p>\n<h2>Contents of Settings files</h2>\n";
	  }
	  if(file_exists($showFilename)) {
			$perms = fileperms($showFilename);
      $permsdecoded = decode_permissions($perms);
	    $permsoctal = substr(sprintf('%o', $perms), -4);
		  print "<h3>$showFilename <small>permissions=$permsdecoded [$permsoctal]</small></h3>\n";
		  if($doHighlight) { 
		    highlight_file_num($showFilename);
		  } else {
				print "<pre style=\"border: 1px solid black;\">\n";
				$flines = file($showFilename);
				$num = 1;
				foreach ($flines as $n => $line) {
					$line = preg_replace('|<\?php|i','<&#63;php',$line);
					$pnum = sprintf('%6d',$num);
					print "$pnum:\t$line";
					$num++;
				}
				print "</pre>\n<hr/>\n";
		  }
	  } else {
		  // print "<h2>$showFilename is not found.</h2>\n<hr/>\n";
	  }
	}
   if($doneHeaders) {
	  print "  </body>\n";
	  print "</html>\n";
   }
	
   exit;
}
// ------------------------------------------------------------------
if(isset($_REQUEST['show']) and preg_match('|structure|i',$_REQUEST['show'])) {
	$toShow = array(
	'top.php',
	'header.php',
	'menubar.php',
	'footer.php',
	'common.php',
	'include-style-switcher.php',
	'flyout-menu.php');
	$doneHeaders = false;
	$doHighlight = preg_match('|structurer|i',$_REQUEST['show'])?false:true;
	foreach ($toShow as $n => $showFilename) {
	  if(!$doneHeaders) { 
	    printHeaders(); 
		printInfo();
		$doneHeaders = true;
		 
	    print "</p>\n<h2>Contents of Website Structure files</h2>\n";
	  }
	  if(file_exists($showFilename)) {
			$perms = fileperms($showFilename);
      $permsdecoded = decode_permissions($perms);
	    $permsoctal = substr(sprintf('%o', $perms), -4);
		  print "<h3>$showFilename <small>permissions=$permsdecoded [$permsoctal]</small></h3>\n";
		  if($doHighlight) { 
		    highlight_file_num($showFilename);
		  } else {
				print "<pre style=\"border: 1px solid black;\">\n";
				$flines = file($showFilename);
				$num = 1;
				foreach ($flines as $n => $line) {
					$line = preg_replace('|<\?php|i','<&#63;php',$line);
					$pnum = sprintf('%6d',$num);
					print "$pnum:\t$line";
					$num++;
				}
				print "</pre>\n<hr/>\n";
		  }
	  } else {
		  // print "<h2>$showFilename is not found.</h2>\n<hr/>\n";
	  }
	}
   if($doneHeaders) {
	  print "  </body>\n";
	  print "</html>\n";
   }
	
   exit;
}
// ------------------------------------------------------------------
if(isset($_REQUEST['show']) and preg_match('|altdash|i',$_REQUEST['show'])) {
	$toShow = array(
	'AltAjaxDashboardConfig6.php',
	'raintodate.php',
	'get-aqi-rss.php'
	,'heavens.php',
	'ajax-dashboard6.php',
	'cloud-base.php',
	'index.php');
	$doneHeaders = false;
	$doHighlight = preg_match('|indexr|i',$_REQUEST['show'])?false:true;
	foreach ($toShow as $n => $showFilename) {
	  if(!$doneHeaders) { 
	    printHeaders(); 
		  printInfo();
		  $doneHeaders = true;
	    print "</p>\n<h2><a name=\"TOP\"></a>Contents of Website Alt-Dashboard files</h2>\n";
			print "<table style=\"border: none\">\n";
			print "<tr><th>Name</th><th>Size</th><th>Updated</th></tr>\n";
			foreach ($toShow as $k => $F) {
				if(file_exists($F)) {
					$FB = filesize($F);
					$FUPD = gmdate('r',filemtime($F));
					print "<tr><td><a href=\"#N$k\">$F</a></td><td style=\"text-align:right;\">$FB</td><td>$FUPD</td></tr>\n";
				} else {
					print "<tr><td>$F</td><td colspan=\"2\">(not found)</td></tr>\n";
				}
					
			}
			print "</table>\n";
	  }
	  if(file_exists($showFilename)) {
			$perms = fileperms($showFilename);
      $permsdecoded = decode_permissions($perms);
	    $permsoctal = substr(sprintf('%o', $perms), -4);
		  print "<h3><a name=\"N$n\"></a>$showFilename <small>permissions=$permsdecoded [$permsoctal]</small></h3>\n";
			print "<p><a href=\"#TOP\">Top of page.</a></p>\n";
		  if($doHighlight) { 
		    highlight_file_num($showFilename);
				print "<p><a href=\"#TOP\">Top of page.</a></p>\n";
		  } else {
				print "<pre style=\"border: 1px solid black;\">\n";
				$flines = file($showFilename);
				$num = 1;
				foreach ($flines as $n => $line) {
					$line = preg_replace('|<\?php|i','<&#63;php',$line);
					$pnum = sprintf('%6d',$num);
					print "$pnum:\t$line";
					$num++;
				}
				print "</pre>\n<hr/>\n";
				print "<p><a href=\"#TOP\">Top of page.</a></p>\n";
		  }
	  } else {
		  // print "<h2>$showFilename is not found.</h2>\n<hr/>\n";
	  }
	}
   if($doneHeaders) {
	  print "  </body>\n";
	  print "</html>\n";
   }
	
   exit;
}
// ------------------------------------------------------------------
if(isset($_REQUEST['show']) and preg_match('|(wx\S+\.php)|i',$_REQUEST['show'],$F)) {
	$toShow = array($F[1]);
	$doneHeaders = false;
	$doHighlight = preg_match('|r$|i',$_REQUEST['show'])?false:true;
	foreach ($toShow as $n => $showFilename) {
	  if(!$doneHeaders) { 
	    printHeaders(); 
		printInfo();
		$doneHeaders = true;
		 
	    print "</p>\n<h2>Contents of Website wx...php page</h2>\n";
	  }
	  if(file_exists($showFilename)) {
			$perms = fileperms($showFilename);
      $permsdecoded = decode_permissions($perms);
	    $permsoctal = substr(sprintf('%o', $perms), -4);
		  print "<h3>$showFilename <small>permissions=$permsdecoded [$permsoctal]</small></h3>\n";
		  if($doHighlight) { 
		    highlight_file_num($showFilename);
		  } else {
				print "<pre style=\"border: 1px solid black;\">\n";
				$flines = file($showFilename);
				$num = 1;
				foreach ($flines as $n => $line) {
					$line = preg_replace('|<\?php|i','<&#63;php',$line);
					$pnum = sprintf('%6d',$num);
					print "$pnum:\t$line";
					$num++;
				}
				print "</pre>\n<hr/>\n";
		  }
	  } else {
		   print "<h2>file '$showFilename' is not found.</h2>\n\n";
	  }
	}
   if($doneHeaders) {
	  print "  </body>\n";
	  print "</html>\n";
   }
	
   exit;
}
// ------------------------------------------------------------------
if(isset($_REQUEST['show']) and preg_match('|index|i',$_REQUEST['show'])) {
	$toShow = array('index.php','wxindex.php');
	$doneHeaders = false;
	$doHighlight = preg_match('|indexr|i',$_REQUEST['show'])?false:true;
	foreach ($toShow as $n => $showFilename) {
	  if(!$doneHeaders) { 
	    printHeaders(); 
		printInfo();
		$doneHeaders = true;
		 
	    print "</p>\n<h2>Contents of Website Index files</h2>\n";
	  }
	  if(file_exists($showFilename)) {
			$perms = fileperms($showFilename);
      $permsdecoded = decode_permissions($perms);
	    $permsoctal = substr(sprintf('%o', $perms), -4);
		  print "<h3>$showFilename <small>permissions=$permsdecoded [$permsoctal]</small></h3>\n";
		  if($doHighlight) { 
		    highlight_file_num($showFilename);
		  } else {
				print "<pre style=\"border: 1px solid black;\">\n";
				$flines = file($showFilename);
				$num = 1;
				foreach ($flines as $n => $line) {
					$line = preg_replace('|<\?php|i','<&#63;php',$line);
					$pnum = sprintf('%6d',$num);
					print "$pnum:\t$line";
					$num++;
				}
				print "</pre>\n<hr/>\n";
		  }
	  } else {
		  // print "<h2>$showFilename is not found.</h2>\n<hr/>\n";
	  }
	}
   if($doneHeaders) {
	  print "  </body>\n";
	  print "</html>\n";
   }
	
   exit;
}
// ------------------------------------------------------------------
// view the Weather data setup+ tags + defs files
//
if(isset($_REQUEST['show']) and preg_match('|wx|i',$_REQUEST['show'])) {
    if(isset($_REQUEST['wx']) and file_exists('Settings-weather-'.strtoupper($_REQUEST['wx']).'.php')) {
	  $wxSettingFileName = 'Settings-weather-'.strtoupper($_REQUEST['wx']).'.php';
    } else {
	  $wxSettingFileName = 'Settings-weather.php';
    }
	$wxTags = '';
	$wxDefs = '';
	if(file_exists($wxSettingFileName)) {
	  $wxSettingFile = file_get_contents($wxSettingFileName);
	} else {
	  $wxSettingFile = '';
	}
	if(preg_match('/\$SITE\[\'WXtags\'\]\s+=\s+[\'|"]([^\'|"]+)[\'|"]/is',$wxSettingFile,$M)) {
      $wxTags = $M[1];
	}
	
	if ($wxTags <> '') {
		$wxDefs = str_replace('tags.php','-defs.php',$wxTags);
	}
	
	$toShow = array($wxSettingFileName,$wxTags,$wxDefs);
	$doneHeaders = false;
	$doHighlight = preg_match('|wxr|i',$_REQUEST['show'])?false:true;
	foreach ($toShow as $n => $showFilename) {
	  if(!$doneHeaders) { 
	    printHeaders(); 
		printInfo();
		$doneHeaders = true;
		 
	    print "</p>\n<h2>Contents of Weather Data files</h2>\n";
	  }
	  if(file_exists($showFilename)) {
			$perms = fileperms($showFilename);
      $permsdecoded = decode_permissions($perms);
	    $permsoctal = substr(sprintf('%o', $perms), -4);
		  print "<h3>$showFilename <small>permissions=$permsdecoded [$permsoctal]</small></h3>\n";
		  if($doHighlight) { 
		    highlight_file_num($showFilename);
		  } else {
				print "<pre style=\"border: 1px solid black;\">\n";
				$flines = file($showFilename);
				$num = 1;
				foreach ($flines as $n => $line) {
					$line = preg_replace('|<\?php|i','<&#63;php',$line);
					$pnum = sprintf('%6d',$num);
					print "$pnum:\t$line";
					$num++;
				}
				print "</pre>\n<hr/>\n";
		  }
	  } else {
		  // print "<h2>$showFilename is not found.</h2>\n<hr/>\n";
	  }
	}
   if($doneHeaders) {
	  print "  </body>\n";
	  print "</html>\n";
   }
	
   exit;
}
// ------------------------------------------------------------------

if(isset($_REQUEST['show']) and strtolower($_REQUEST['show']) == 'info') {
  if(file_exists("Settings.php")) {
	include_once("Settings.php");
  }
# set the Timezone abbreviation automatically based on $SITE['tzname'];
# Set timezone in PHP5/PHP4 manner
  if (!function_exists('date_default_timezone_set')) {
	 putenv("TZ=" . $SITE['tz']);
//	 print "<!-- using putenv(\"TZ=". $SITE['tz']. "\") -->\n";
    } else {
	 date_default_timezone_set($SITE['tz']);
//	 print "<!-- using date_default_timezone_set(\"". $SITE['tz']. "\") -->\n";
   }
  printHeaders(); 
  printInfo();
	$toCheck = array('simplexml_load_file','iconv','json_decode',
	                 'curl_init','curl_setopt','curl_exec','curl_error','curl_close','curl_getinfo',
									 'imagecreatefrompng','imagecreatefromjpeg','imagecreatefromgif',
									 'imagettfbbox','imagettftext','gregoriantojd');

	print "<h2>Status of needed built-in PHP functions</h2><p>\n";

	foreach ($toCheck as $n => $chkName) {
		print "function <b>$chkName</b> ";
		if(function_exists($chkName)) {
			print " is <span style=\"color: green;\">available</span><br/>\n";
		} else {
			print " is <b><span style=\"color:red;\">NOT available</span></b><br/>\n";
		}
		
	}
#	print "class <b>Imagick</b> ";
#	print (class_exists("Imagick"))?" is available":" is NOT available";
#	print " (but is not required/used by the Saratoga templates)<br/>\n";
  print "</p>\n";
	
	if(function_exists('curl_version')) {
		// Get curl version array
		print "<h2>Current required cURL features status:</h2>\n";
		$version = curl_version();
		/*
		Array
		(
				[version_number] => 463623
				[age] => 3
				[features] => 1597
				[ssl_version_number] => 0
				[version] => 7.19.7
				[host] => x86_64-redhat-linux-gnu
				[ssl_version] => NSS/3.27.1
				[libz_version] => 1.2.3
				[protocols] => Array
						(
								[0] => tftp
								[1] => ftp
								[2] => telnet
								[3] => dict
								[4] => ldap
								[5] => ldaps
								[6] => http
								[7] => file
								[8] => https
								[9] => ftps
								[10] => scp
								[11] => sftp
						)
		
		)
		*/
		
			print "<p>cURL version: <strong>".$version['version']."</strong><br/>\n";
			if(isset($version['ssl_version'])) {
				print "cURL SSL version: <strong>".$version['ssl_version']."</strong><br/>\n";
			} else {
				print "cURL SSL not enabled in PHP<br/>\n";
			}
			if(isset($version['libz_version'])) {
				print "cURL libz version: <strong>".$version['libz_version']."</strong><br/>\n";
			} else {
				print "cURL libz not enabled in PHP<br/>\n";
			}
			
		
		// These are the bitfields that can be used 
		// to check for features in the curl build
		$bitfields = Array(
								'CURL_VERSION_SSL' => "SSL", 
								'CURL_VERSION_LIBZ' => "LIBZ"
								);
	
		foreach($bitfields as $feature => $fname)
		{
				echo $fname . ($version['features'] & constant($feature) ? ' is available' : ' is NOT AVAILABLE but REQUIRED');
				echo "<br/>\n";
		}
		print "cURL protocols supported: <strong>";
		$protocols = $version['protocols'];
		sort($protocols,SORT_STRING);
		print join(', ',$protocols)."</strong><br/>\n";
	
		print "</p>\n";
	 } else {
		print "<h2>cURL functions are not found (but are REQUIRED)</h2>\n";
}

  	
  print "<h2>Current GD (image handling functions) status:</h2>\n";
  echo describeGDdyn();

  if(!file_exists("Settings.php")) {
		$settingsLoad = "Unable to find Settings.php.. directory testing skipped.\n";
		print $settingsLoad;
		print "  </body>\n";
		print "</html>\n";
			
		exit;
  }
  
  $Base = '';
  $WXdirs = array( // specific directories from Settings-weather.php $SITE and files to check
    'CU' => array('graphImageDir'=> 'temp.png','NOAAdir' => 'NOAAYRyyyy.txt'),
	  'MH' => array('graphImageDir'=> 'tdpb2day.png'),
	  'VWS'=> array('graphImageDir'=> 'vws742.jpg', 'NOAAdir'=> 'noaayr.txt'),
	  'WCT'=> array('graphImageDir'=> 'temperature1.jpg'),
	  'WD' => array('graphImageDir'=> 'curr24hourgraph.gif',
		            'HistoryFilesDir'=>'MONTHyyyy.htm',
	              'HistoryGraphsDir'=>'yyyymmdd.gif',
								'NOAAdir'=>'noaareportyearyyyy.htm'),
    'WL' => array('graphImageDir'=> 'OutsideTempHistory.gif','NOAACurDir'=>'NOAAMO.TXT'),
//  'WLCOM' = array( ); //none to check
//	'WSN' => array('graphImageDir'=> '?????'),  // no graphs for WeatherSnoop
	  'WV'=> array('graphImageDir'=> 'tempdaycomp.png', 'NOAAdir'=> 'NOAA-yyyy.txt'),
//	'WXS' => array('graphImageDir'=> '?????'),  // no graphs for WXSolution
    'WEEWX' => array('graphImageDir'=>'daytempdew.png','NOAAdir'=>'NOAA-yyyy.txt'),
	
  
  );
  
  if(isset($SITE['fcsturlNWS']) or isset($SITE['NWSforecasts'])) {
	  $Base = 'USA';
  }
  if(isset($SITE['fcsturlEC']) or isset($SITE['ecradar']) or isset($SITE['ECforecasts'])) {
	  $Base = 'Canada';
  }

  if(isset($SITE['EUwarnings']) or isset($SITE['EUminLevel']) or $Base == '') {
	  $Base = 'World';
  }
  
  if(isset($SITE['WXsoftware']) ){
	  $wxsftw=$SITE['WXsoftware'];
  } else {
	  $wxsftw = 'N/A';
  }
  print "<h2>Directories/files status for Base-$Base, $wxsftw-Plugin</h2>\n";
  print "<p>Status of needed subdirectories/images:<br/><br/>\n\n";
  print "Settings.php <b>Cache file directory</b> in \$SITE['cacheFileDir']='<b>".$SITE['cacheFileDir']. "</b>' ";
  if(is_dir($SITE['cacheFileDir'])) {
	  $perms = fileperms($SITE['cacheFileDir']);
      $permsdecoded = decode_permissions($perms);
	  $permsoctal = substr(sprintf('%o', $perms), -4);
	  print " exists, with permissions=$permsdecoded [$permsoctal]<br/>\n";
	  $tfile = $SITE['cacheFileDir'] . "test.txt";
	  $tstring = "Test of cache directory file create and write by $Version.<br/>\n";
	  $fp = fopen($tfile,'w');
	  if($fp) {
		$write = fputs($fp, $tstring); 
		fclose($fp);
		print "..Wrote ".strlen($tstring). " bytes to $tfile successfully, ";
		$deleted = unlink($tfile);
		print $deleted?"then deleted test file. <b>Cache directory is fully functional</b>.<br/>\n":" but unable to delete $tfile so <b>Cache directory is NOT fully functional</b>.<br/>\n";
	  } else {
		print "<br/><b>Error: Unable to open/write to $tfile file</b> so so <b>Cache directory is NOT fully functional</b>.<br/>\n";
	  }
  } else {
	  print "<b>does not exist</b> so some scripts will be <b>not functional</b>.<br/>\n";
  }
  print "<br/>\n";
  print "Settings.php <b>ajax-images file directory</b> in \$SITE['imagesDir']='<b>".$SITE['imagesDir']. "</b>' ";
  if(is_dir($SITE['imagesDir'])) {
	  print " exists; ";
	  print file_exists($SITE['imagesDir'].'skc.jpg')?
	    " and appears to have contents.<br/>\n":" but <b>does not have contents</b>.  Be sure to upload contents for proper template operation.<br/>\n";
  } else {
	  print " <b>is not on website.</b>  Be sure to upload contents for proper template operation.<br/>\n";
  }
  print "<br/>\n";
  
  if(isset($SITE['NWSalertsCodes']) and file_exists('nws-alerts-config.php')) {
	include_once('nws-alerts-config.php');
	$tFolder = $icons_folder . '/';
	print "nws-alerts-config.php <b>alert-images file directory</b> \$icons_folder='<b>$icons_folder</b>' ";
	if(is_dir($tFolder)) {
		print " exists; ";
		print file_exists($tFolder.'A-none.png')?
		  " and appears to have contents.<br/>\n":" but <b>does not have contents</b>.  Be sure to upload contents for proper template operation.<br/>\n";
	} else {
		print " <b>is not on website.</b>  Be sure to upload contents for proper template operation.<br/>\n";
	}
    print "<br/>\n";

  }

  if(isset($SITE['fcsticonsdirEC']) and file_exists('ec-forecast.php')) {
	$tFolder = $SITE['fcsticonsdirEC'];
	print "Settings.php <b>ec-icons file directory</b> \$SITE['fcsticonsdirEC']='<b>$tFolder</b>' ";
	if(is_dir($tFolder)) {
		print " exists; ";
		$iconType = '.gif';
		if (isset($SITE['ECiconType']))     {$iconType = $SITE['ECiconType']; }
		print file_exists($tFolder."01$iconType")?
		  " and appears to have contents.<br/>\n":" but <b>does not have contents</b> (01$iconType checked).  Be sure to upload contents for proper template operation.<br/>\n";
	} else {
		print " <b>is not on website.</b>  Be sure to upload contents for proper template operation.<br/>\n";
	}
   print "<br/>\n";

  }
  
  print "Settings.php <b>forecast images file directory</b> in \$SITE['fcsticonsdir']='<b>".$SITE['fcsticonsdir']. "</b>' ";
  if(is_dir($SITE['fcsticonsdir'])) {
	  print " exists; ";
	  $fType = '.jpg';
	  if (isset($SITE['fcsticonstype'])) {$fType = $SITE['fcsticonstype']; }
	  print file_exists($SITE['fcsticonsdir'].'skc'.$fType)?
	    " and appears to have <b>$fType</b> image contents.<br/>\n":" but <b>does not have <b>$fType</b? image contents</b> (skc$fType checked).  Be sure to upload contents for proper template operation.<br/>\n";
  } else {
	  print " <b>is not on website.</b>  Be sure to upload contents for proper template operation.<br/>\n";
  }
  print "<br/>\n";
  
  if(isset($WXdirs[$wxsftw])) { // check weather-software specific directories
    $toCheck = $WXdirs[$wxsftw]; // get the list.
	foreach ($toCheck as $siteVar => $chkFile) {
	  if(isset($SITE[$siteVar])) {
		$chkDir = $SITE[$siteVar];
		print "Settings-weather.php \$SITE['$siteVar']='<b>".$chkDir. "</b>' ";
		if(is_dir($chkDir)) {
			print " exists; ";
			list($nowYear,$nowMonth,$nowMM,$nowDD) = explode(" ",date('Y F m d',time()-24*60*60));
			$chkFile = preg_replace('|yyyy|',$nowYear,$chkFile);
			$chkFile = preg_replace('|yy|',substr($nowYear,2,2),$chkFile);
			$chkFile = preg_replace('|MONTH|',$nowMonth,$chkFile);
			$chkFile = preg_replace('|mm|',$nowMM,$chkFile);
			$chkFile = preg_replace('|dd|',$nowDD,$chkFile);
			
			print file_exists($chkDir.$chkFile)?
			  " and appears to have contents. ($chkFile tested)<br/>\n":" but <b>does not have contents ($chkFile tested)</b>.  Set $wxsftw software to upload contents for proper template operation.<br/>\n";
		} else {
			print " <b>is not on website.</b>  Set $wxsftw software to upload contents for proper template operation.<br/>\n";
		}
		  
	  }
      print "<br/>\n";
	  
	}
  
 
  }
  $coreFiles = array(
	'Settings.php' => 'template main settings file',
	'common.php' => 'template common routines',
	'top.php' => 'webpage top of page',
	'header.php' => 'webpage header area',
	'ajax-gizmo.php' => 'webpage rotating conditions display',
	'ajaxgizmo.js' => 'webpage rotating conditions JavaScript',
	'menubar.php' => 'webpage menubar area',
	
	'footer.php' => 'webpage footer area',
	'flyout-menu.php' => 'webpage flyout menu routines',
	'flyout-menu.xml' => 'webpage flyout menu control file',
	'include-style-switcher.php' => 'webpage style selector routines',
  );
  print "</p><h2>Check for presence of required core template files</h2><p>\n";
  foreach ($coreFiles as $chkFile => $description) {
	 print "<b>$chkFile</b> ($description): ";
	 print file_exists($chkFile)?"found on website.":"<b>NOT FOUND</b> on website, but <b>required</b> for proper operation.";
	 print "<br/>\n";  
	  
  }
  print "<br/>Note: use <a href=\"?show=versions\">check-fetch-times.php?show=versions</a> to check for all required files for your base and weather station plugin combination.\n";
  print "</p>\n";
/*
  $updateDate = file_exists("common.php")?filemtime("common.php"):'unknown';
  if($updateDate <> 'unknown') {$updateDate = gmdate('D, d-M-Y g:ia T',$updateDate); }
  print "<p>common.php last updated: $updateDate</p>\n";	
  $updateDate = file_exists("language-en.txt")?filemtime("language-en.txt"):'unknown';
  if($updateDate <> 'unknown') {$updateDate = gmdate('D, d-M-Y g:ia T',$updateDate); }
  print "<p>language-en.txt last updated: $updateDate</p>\n";	
*/
  print "  </body>\n";
  print "</html>\n";
	
  exit;
}

$time_init = time();


if(file_exists("Settings.php")) {
  $T_start = microtime_float();
  include_once("Settings.php");
  $T_stop = microtime_float();
  $settingsLoad = "Included Settings.php time=" . sprintf("%01.3f",round($T_stop - $T_start,3)) . " secs.</p>\n\n";
} else {
  $settingsLoad = "Unable to find Settings.php.. testing skipped.\n";
  print $settingsLoad;
  return;
}

if(isset($_REQUEST['show']) and strtolower($_REQUEST['show']) == 'fullphpinfo') {
  phpinfo();
	return;
}

printHeaders();
// ------------------------------------------------------------------
//
// do version checking for key scripts (part of V1.06+)
//
if(isset($_REQUEST['show']) and strtolower($_REQUEST['show']) == 'versions') { // do Version checking
  // Template updates are all based in Pacific time in the distribution .zip files
  $ourTZ = 'America/Los_Angeles';
	$siteTZ = date_default_timezone_get();
  # set the Timezone abbreviation automatically based on $SITE['tzname'];
  # Set timezone in PHP5/PHP4 manner
	if (!function_exists('date_default_timezone_set')) {
	   putenv("TZ=" . $ourTZ);
  //	 print "<!-- using putenv(\"TZ=". $SITE['tz']. "\") -->\n";
	  } else {
	   date_default_timezone_set($ourTZ);
  //	 print "<!-- using date_default_timezone_set(\"". $SITE['tz']. "\") -->\n";
	 }
  global $SITE;
  $Lang = 'en';
  $cacheFileDir = './';
	$outputText = '';
	include_once('Settings.php');
  
  if(isset($SITE['lang'])) {$Lang = $SITE['lang'];}
  if(isset($SITE['cacheFileDir'])) {$cacheFileDir = $SITE['cacheFileDir'];}
	if(isset($SITE['skipMissingFilesCheck'])) {
		$skipMissing = $SITE['skipMissingFilesCheck'];
	} else {
		$skipMissing = false;
	}

  $templateVersionsFile = 'template-version-info.txt';
  $templateVersionsURL = 'https://saratoga-weather.org/wxtemplates/'.$templateVersionsFile;  

  $outputText .= $settingsLoad;
  
  # fetch/cache template version info file from master (if available)
  $TESTURL = $templateVersionsURL;
  $CACHE = $cacheFileDir.$templateVersionsFile;
  $outputText .= printInfo();
  $outputText .= "<pre>\n";  
  if (!isset($_REQUEST['force']) and file_exists($CACHE) and filemtime($CACHE) + 600 > time()) {  // 1800
    $outputText .=  "..loading $CACHE for version information.\n";
  } else {
	$outputText .=  "..fetching recent version information.\n";
	$rawhtml = fetchUrlWithoutHanging($TESTURL,false);
	$RC = '';
	if (preg_match("|^HTTP\/\S+ (.*)\r\n|",$rawhtml,$matches)) {
		$RC = trim($matches[1]);
	}
	$outputText .=  "RC=$RC, bytes=" . strlen($rawhtml) . "\n";
	$i = strpos($rawhtml,"\r\n\r\n");
	$headers = substr($rawhtml,0,$i-1);
	$content = substr($rawhtml,$i+2);
	$html = explode("\n",$content);  // put HTML area as separate lines
	$age = -1;
	$udate = 'unknown';
	$budate = 0;
	if(preg_match('|\nLast-Modified: (.*)\n|Ui',$headers,$match)) {
		$udate = trim($match[1]);
		$budate = strtotime($udate);
		$age = abs(time() - $budate); // age in seconds
		$outputText .=  "Data age=$age sec '$udate'\n";
	}
	  
	if (!preg_match('| 200|',$headers)) {
	  $outputText .=  "------------\nHeaders returned:\n\n$headers\n------------\n";
	  $outputText .=  "\nSkipped cache write to $CACHE file.\n";
	} elseif ($CACHE <> '') {
		$fp = fopen($CACHE,'w');
		if($fp) {
		  $write = fputs($fp, $rawhtml); 
		  fclose($fp);
		  $outputText .=  "Wrote ".strlen($rawhtml). " bytes to $CACHE successfully.\n";
		} else {
		  $outputText .=  "Error: Unable to write to $CACHE file.\n";
		}
	} 

  } // end fetch new version info from saratoga-weather.org 
 
 # now load up the version info which looks like:
/*
# template-version-info updated 2012-08-06 08:38 PDT by( version-info V1.00 - 05-Aug-2012 )
#Base	File	ModDate	Size	Index	ZipSize	MD5	Version	VDate	VersionDesc
Base-Canada	wxuvforecast.php	2012-03-31 05:20 PDT	7376	299	7193	abfb72a9504fc73812e8a4eb8822831a	1.01	2012-03-31	1.01 - 31-Mar-2012 - day-of-week fix for get-UV-forecast-inc.php V1.07
Base-Canada	wxtrends.php	2011-01-19 11:04 PST	2914	298	2842	ff4f1a25ebeb60a130b291303005817e	n/a	2011-01-19	(not specified)
*/ 
    $MasterVersions = array();
	$nVersions = 0;
    $VFile = file($CACHE);
	if(count($VFile) < 10) {
		$outputText .=  "Error: $CACHE file is not complete..skipping testing.\n";
		$quietText .= $outputText;
		return;
	}
	foreach ($VFile as $n => $rec) {
	  $recin = trim($rec);
	  if ($recin and substr($recin,0,1) <> '#') { // got a non comment record
        list($Base,$File,$ModDate,$Size,$Index,$ZipSize,$FileMD5,$Fversion,$FvDate,$FvDesc) = explode("\t",$recin . "\t\t\t\t\t\t\t\t\t\t");
		$MasterVersions["$Base\t$File"] = "$ModDate\t$Size\t$FileMD5\t$Fversion\t$FvDate\t$FvDesc";
		$nVersions++;
	  }
	}
  $outputText .=  "..loaded $nVersions version descriptors from $CACHE file.<br/>\n";
 
  # end of get new template version info file
# set of files to do version checking  
  $templateFiles = array(
	
	 'Deprecated' => array(
	 // old files that no longer function due to source website changes
	  'WU-forecast.php','get-nnvl-iod.php',
		'WU-radar-inc.php','WU-radar-testpage.php',
		'swfobject.js','wxlive.php',
		'ec-radar.php','ec-radar-cachetest.php',
		'DS-forecast.php','DS-forecast-lang.php',
		'atom-advisory.php','atom-top-warning.php',
		), 

   'Common' => array(
//	  'ajaxgizmo.js',
	  'ajax-gizmo.php','ajax-dashboard.php','common.php','check-fetch-times.php',
	  'flyout-menu.php','include-style-switcher.php',
	  'get-metar-conditions-inc.php','get-USNO-sunmoon.php','get-UV-forecast-inc.php',
	  'include-metar-display.php','include-wxstatus.php',
		'plaintext-parser.php','plaintext-parser-data.txt',
		'quake-json.php','quake-json.js','quake-json.css',
    'sunposa.php','sunposa-lang.php',
	  'thermometer.php',
		'wxforecast.php','wxgraphs.php','wxmetar.php','wxquake.php'),

  'USA' => array(
	  'advforecast2.php','DualImage.php',
		'GR3-radar-inc.php','floatTop.js',
	  'nws-alerts.php','nws-alerts-details-inc.php','nws-alerts-summary-inc.php',
	  'nws-alertmap.js','nws-all-zones-inc.php','nws-alerts.css','wxnws-details.php',
		'nws-alerts-log.php','wxnws-alerts-log.php','wxadvisory.php',
	  'radar-status.php',
		'wxnwsradar.php','wxnwsradar-inc.php','wxnwsradar-iframe.php','hanis_min.js',
		'USA-regional-maps-inc.php','NWS-regional-radar-animate.php','WU-satellite-animate.php'),

  'Canada' => array(
	   'ec-forecast.php','ec-forecast-lookup.txt','ec-lightning.php','quake-Canada.php','wxradar.php',
		 'wxecradar-inc.php','wxecradar-iframe.php','wxecradar-list-inc.php','hanis_min.js'),

  'World' => array(
	  'get-meteoalarm-warning-inc.php','meteoalarm-geocode-aliases.php','meteoalarm-codenames.json',
		'quake-UK.php',
	  'Settings-language.php',
		'AW-forecast.php','AW-forecast-lang.php',
		'PW-forecast.php','PW-forecast-lang.php',
		'VC-forecast.php','VC-forecast-lang.php',
		'OWM-forecast.php','OWM-forecast-lang.php',
		'WC-forecast.php'),
	'AWN' => array('ajaxAWNwx.js','AWN-defs.php','AWNtags.php','AWNlast24.php',
		  'saveYesterday.php','AWNrealtime.php','AWNrealtimegauges.php',
		  'wxsummaryawn.php','include-wxsummary-AWN.php'),
	'CU' => array('ajaxCUwx.js','CU-defs.php','gen-CUtags.php','tags.txt',
	   'wxnoaaclimatereports.php','include-NOAA-reports.php'),
	'MB' => array('ajaxMBwx.js','MB-defs.php','MB-trends-inc.php','gen-MBtags.php',
	      'MBtags-template.txt','conds.php','MBrealtime-template.txt'),
	'MH' => array('ajaxMHwx.js','MH-defs.php','yesterday.php','MH-trends-inc.php'),
	'VWS' => array('ajaxVWSwx.js','VWStags.php','VWS-defs.php','gen-VWStags.php','tags.txt',
	    'wxnoaaclimatereports.php','include-NOAA-reports.php'),
	'WCT' => array('ajaxWCTwx.js','WCT-defs.php','gen-WCTtags.php','WeatherCat-webtags.txt'),
  'WD' => array(
	    'ajaxWDwx.js','WD-trends-inc.php','include-wxhistory.php',
			'wxnoaaclimatereports.php','include-NOAA-reports.php'),
	'WL' => array('ajaxWLwx.js','WL-defs.php','gen-WLtags.php','WLtags.txt',
	    'wxnoaaclimatereports.php','include-NOAA-reports.php'),
	'WLCOM' => array('ajaxWLCOMwx.js','WLCOM-defs.php','WLCOMtags.php',
		  'saveYesterday.php','WLrealtime.php','WLrealtimegauges.php',
		  'wxsummary.php','include-wxsummary.php'),
	'WSN' => array('WSNtags.php','WSN-defs.php'),
	'WV' => array('WV-defs.php','gen-WVtags.php','tags.txt',
	    'wxnoaaclimatereports.php','include-NOAA-reports.php'),
	'WXS' => array('WXS-defs.php','gen-WXStags.php','tags.txt'),
	'WEEWX' => array('ajaxWEEWXwx.js','post_clientraw.php','WEEWX-defs.php',
	    'wxnoaaclimatereports.php','include-NOAA-reports.php','WEEWX-trends-inc.php','clientraw.php'),
	 
  );
#
# filenames requiring customization before replacing in current template
#
$filesToCustomize = 'Settings.php,Settings-weather.php,
ajaxAWNwx.js,ajaxCUwx.js,ajaxWDwx.js,ajaxWLwx.js,ajaxWLCOMwx.js,ajaxVWSwx.js,ajaxMBwx.js,
ajaxMHwx.js,ajaxWCTwx.js,ajaxWSNwx.js,ajaxWEEWXwx.js,
top.php,header.php,menubar.php,footer.php,MH-sensors.php,flyout-menu.xml,
WSNtags.php,wxabout.php,wxstatus.php,wxquake.php,wxquakew.php,wxssgauges.php,DualImage.php,gauges.js,
cron-yday.txt,wxnwsradar.php,
wxabout-af.html
wxabout-bg.html,
wxabout-ct.html,
wxabout-de.html,
wxabout-dk.html,
wxabout-el.html,
wxabout-en.html,
wxabout-es.html,
wxabout-fi.html,
wxabout-fr.html,
wxabout-ga.html,
wxabout-he.html,
wxabout-it.html,
wxabout-nl.html,
wxabout-no.html,
wxabout-pl.html,
wxabout-pt.html,
wxabout-ro.html,
wxabout-se.html,
wxabout-si.html,
nws-alerts-config.php,wxindex.php,NWS-regional-radar-animate.php,
cuwx/cumulusmxcharts.js';
$filesToRegenerate = 'MHtags.php,MH-defs.php,';
$filesToReloadMB = 'gen-MBtags.php,MBtags-template.txt,MBrealtime-template.txt';
#  
$ignoreFiles = 'testtags.php,VWStags.php,CUtags.php,WLtags.php,MBtags.php,MHtags.php,MH-defs.php,weather.json,WEEWXtags.php,WCTtags.php,WVtags.php,WXStags.php,MBrealtime.txt,MBrealtimegauges.txt'; // Don't care about those files .. created by WX station
#
############################################################################
	

  $selectedVersions = array();
  $selectedVersionsType = array();
  
  $toCheckFiles = $templateFiles['Common'];
  $toCheckLegend = 'Common Files';
  foreach ($templateFiles['Common'] as $key => $val) {$selectedVersionsType[$val] = 'Common'; }

  $toCheckFiles = array_merge($toCheckFiles,$templateFiles['Deprecated']);
  $toCheckLegend .= ', Deprecated';
  foreach ($templateFiles['Deprecated'] as $key => $val) {$selectedVersionsType[$val] = 'Deprecated'; }

  $updateBasePlugin = '';
  if(isset($SITE['fcsturlNWS']) or isset($SITE['NWSforecasts'])) {
	  $toCheckFiles = array_merge($toCheckFiles,$templateFiles['USA']);
	  $toCheckLegend .= ', Base-USA';
	  load_selected_array('Base-USA');
	  $updateBasePlugin = 'Base-USA';
	  foreach ($templateFiles['USA'] as $key => $val) {$selectedVersionsType[$val] = 'USA'; }
  }
  if(isset($SITE['fcsturlEC']) or isset($SITE['ecradar'])) {
	  $toCheckFiles = array_merge($toCheckFiles,$templateFiles['Canada']);
	  $toCheckLegend .= ', Base-Canada';
	  load_selected_array('Base-Canada');
	  $updateBasePlugin = 'Base-Canada';
	  foreach ($templateFiles['Canada'] as $key => $val) {$selectedVersionsType[$val] = 'Canada'; }
  }

  if(isset($SITE['EUwarnings']) or
     isset($SITE['installedLanguages']['dk']) ) {
	  $toCheckFiles = array_merge($toCheckFiles,$templateFiles['World']);
	  $toCheckLegend .= ', Base-World';
	  load_selected_array('Base-World');
	  $updateBasePlugin = 'Base-World';
	  foreach ($templateFiles['World'] as $key => $val) {$selectedVersionsType[$val] = 'World'; }
  }
  
  if(isset($SITE['WXsoftware']) and isset($templateFiles[ $SITE['WXsoftware'] ]) ){
	  $wxsftw=$SITE['WXsoftware'];
	  $toCheckFiles = array_merge($toCheckFiles,$templateFiles[$wxsftw]);
	  $toCheckLegend .= ', '.$wxsftw.'-plugin';
	  load_selected_array($wxsftw.'-plugin');
	  foreach ($templateFiles[$wxsftw] as $key => $val) {$selectedVersionsType[$val] = $wxsftw.'-plugin'; }
	  
	  if( isset($SITE['WXsoftwareLongName']) ) {
		  $toCheckLegend .= ' (for '.$SITE['WXsoftwareLongName'].' weather software)';
	  }
	  $updateBasePlugin .= ', '.$wxsftw.'-plugin';

  }
  $outputText .=  "</pre>\n";

  $outputText .=  "<h3>Version information for selected <strong>$toCheckLegend</strong> key template files</h3>\n";
  $outputText .=  "<p style=\"border: 2px dotted red; background-color: #FFCC00; padding: 5px;\">";
  $outputText .=  "<strong>Note</strong>: only selected key template files are checked with this script. Files with customary user modifications (Settings.php, Settings-weather.php, top.php, header.php, menubar.php, footer.php, most wx...php files, etc.) and graphics and weather tags files are NOT checked as they either do not contain version information or they are expected to be different from the distribution versions due to normal website  customization.";
	$outputText .= "<br/>The <strong>Recommend</strong> column indicates how the file should be handled.  Mouse over a recommendation line to see additional information in a tooltip.";
  
  $outputText .=  "</p>\n";
  
  $outputText .=  "<table style=\"border: 1px;\" cellpadding=\"2\" cellspacing=\"2\">\n";
  $outputText .=  "<tr><th>Script<br/>Origin</th><th>Script<br/>Name</th><th>Installed Script<br/>Version Status</th><th>Recommend</th><th>Release Script<br/>Version</th><th>Installed Script<br/>Version</th><th>Installed Script Internal<br/>Version Description</th></tr>\n";
  $earliestDate = '9999-99-99';
  
  natcasesort($toCheckFiles);
  $idx = 0;
  foreach ($toCheckFiles as $n => $checkFile) {
	  if ($idx % 5 <> 0) { $TRclass = 'row-even'; } else { $TRclass = 'row-odd'; }
	  list($mDate,$vNumber,$vDate,$vInfo,$FileMD5,$fStatus) = chk_file_version($checkFile);
		if($vInfo == '') {continue; } // skip deprecated and not-found files
	  $instVer = '';
	  if($vNumber <> '' and $vDate <> '') {$instVer = "V$vNumber - $vDate"; }
	  $distVer = '';
	  if(isset($selectedVersions[$checkFile])) { 
		 list($mstModDate,$mstSize,$mstFileMD5,$mstFversion,$mstFvDate,$mstFvDesc) = 
			explode("\t",$selectedVersions[$checkFile]);
		 $distVer = "V$mstFversion - $mstFvDate";
	  }
	  if(isset($selectedVersionsType[$checkFile]) ) {
			 $fSource = $selectedVersionsType[$checkFile];
		} else {
			$fSource = '';
		}
		$code = '&nbsp;&nbsp;';
		$recommend = 'Installed version is up-to-date -- no update needed.';
		if(strpos($fStatus,'Installed') !== false) {
			$code = '&nbsp;*';
			$recommend = 'Installed version is ahead of distributed version.';
		}
		if(strpos($fStatus,'Need') !== false) {
		  list($code,$recommend) = get_update_recommendation($checkFile);
		}
		if(strpos($fStatus,'delete') !== false) {
			$code = 'Delete';
			$recommend = 'File is no longer used .. you can remove from website.';
		}
		
	  $outputText .=  "<tr class=\"$TRclass\"><td>$fSource</td><td><strong>$checkFile</strong></td><td>$fStatus</td><td><span class=\"recommend\" title=\"$recommend\">$code</span></td><td>$distVer</td><td>$instVer</td><td>$vInfo</td></tr>\n";
		if(strpos($fStatus,'Need update') !== false or 
		   (!$skipMissing and (strpos($fStatus,'not installed') !== false or strpos($fStatus,'to delete') !==false) ) ) {
			$quietText .= "$checkFile $instVer $fStatus\n";
		}
			 
	  $idx++;
  }
	  
  $outputText .=  "</table>\n";	  

  if($earliestDate <> '9999-99-99') {
	  //found some updates
	 $updateBasePluginDate = date('d-M-Y',strtotime($earliestDate));
	 
	 $outputText .=  "<h3>To update your template set to current script version(s), use <a href=\"https://saratoga-weather.org/wxtemplates/updates.php\"><strong>the updates tool page</strong></a> with a query set for <strong>$updateBasePluginDate</strong> for ";
	 $outputText .=  "<strong>$updateBasePlugin</strong></h3>\n"; 
	  
  }


 $outputText .=  "<pre>\n";
 date_default_timezone_set($siteTZ); // restore original timezone setting
// end of version checking  

if(!$doQuiet) { 
  print $outputText; 
 } else {
	$quietText = str_replace('<br/>',' ',$quietText);
	return;
}
  
} else { // do fetch file checking

// ------------------------------------------------------------------

// file fetch checking

  printInfo();
  print "</p>\n<h2>Checking access to key websites for your template set</h2>\n";

  print '
  <p>This script will check the load times and the ability to save cache files for the included support
  scripts with your template package.</p>
  <p>
  ';
  
  print $settingsLoad;
  
  
  global $SITE, $Debug,$doDebug;
  $Lang = 'en';
  $cacheFileDir = './';
  
  if(isset($SITE['lang'])) {$Lang = $SITE['lang'];}
  if(isset($SITE['cacheFileDir'])) {$cacheFileDir = $SITE['cacheFileDir'];}

  $doDebug = true;
  
  
  $Tests = array(
  'fcsturlNWS' => 'NWS Forecast URL|forecast.txt|NWSforecasts|2|forecast-0.txt',
  'noaazone'   => 'NWS Warning Zone ATOM/CAP Feed',
  'UVscript'   => 'UV Forecast from temis.nl|uv-forecast.txt',
  'fcsturlEC'  => 'EC Forecast URL|ec-forecast-LL.txt|ECforecasts|2|ec-forecast-0-LL.txt',
  //'ecradar'    => 'EC Radar URL',
  //'fcsturlWU'  => 'WU Forecast URL|WU-forecast-LL.txt|WUforecasts|1|WU-forecast-0-LL.txt',
	'DSAPIkey'   => 'DarkSky Forecast for Lat/Long||DSforecasts|1|',
	'AWAPIkey'   => 'Aerisweather Forecast for Lat/Long||AWforecasts|1|',
	'WCAPIkey'  => 'WU/TWC Forecast for Lat/Long||WCforecasts|1|',
	'PWAPIkey'  => 'Pirateweather Forecast for Lat/long||PWforecasts|1|',
	'VCAPIkey'  => 'VirtualCrossing Forecast for Lat/long||VCforecasts|1|',
	'OWMAPIkey'  => 'OpenWebMap Forecast for Lat/long||OWMforecasts|1|',
  #'EUwarningURL' => 'METEOalarm warning URL|meteoalarm-LL.txt' // discontinued
  );

  print "<p>Using lang=$Lang as default for testing</p>\n<pre>\n";
  global $TOTALtime;
  $TOTALtime = $T_stop - $T_start;
  
  foreach ($Tests as $sname => $sval) {
	$useAltUrl = '';
	$Debug = '';
	list($sdescript,$cname,$altvar,$altindex,$altcname) = explode('|',$sval.'||||');
	if($altvar <> '' and isset($SITE[$altvar][0]) ) { // fetch first entry in multiforecast variable
	   $vars = explode('|',$SITE[$altvar][0].'||||');
	   $useAltUrl = $vars[$altindex];
       $cname = preg_replace('|LL|',$Lang,$altcname);
	} else {
	  $cname = preg_replace('|LL|',$Lang,$cname);
	}
	if(isset($SITE[$sname])) {
	  print "--checking $sdescript --\n";
	  $TESTURL = $SITE[$sname];
	  $CACHE = '';
	  if($useAltUrl) {
		 $TESTURL = $useAltUrl;
	  }
	  if($cname <> '') {$CACHE = $cacheFileDir.$cname; }
	  
	  if($sname == 'UVscript') {
		$TESTURL = "https://www.temis.nl/uvradiation/nrt/uvindex.php?lon=" .$SITE['longitude'] . "&lat=" . $SITE['latitude'];
	  }
	  if($sname == 'noaazone') {
		$TESTURL = "https://api.weather.gov/alerts/active.atom?zone=".$SITE['noaazone'];
		$CACHE = $cacheFileDir."nws-alerts-".$SITE['noaazone'].".txt";
	  }
	  if($sname == 'fcsturlEC') {
		// autochange Old EC URL if present
		$TESTURL = preg_replace('|weatheroffice|i','weather',$TESTURL); 
    $TESTURL = preg_replace('|_.\.html|',"_e.html",$TESTURL);
		$TESTURL = str_replace('http://','https://',$TESTURL);
		$CACHE = $cacheFileDir."ec-forecast-en.txt";
	  }
	  if($sname == 'ecradar') {
		$TESTURL = 'https://weather.gc.ca/radar/index_e.html?id=' . $SITE['ecradar'];
		$CACHE = "./radar/ec-radar-en.txt";
	  }
	  if($useAltUrl <> '') {
		  print "Using first entry in Settings.php \$SITE['$altvar'] for test.\n";
	  } else {
		  print "Using Settings.php \$SITE['$sname'] entry for test.\n";
	  }
	  if($sname == 'fcsturlNWS') {
		  list($TESTURL,$FCSTURL) = convert_NWS_filename($TESTURL,$SITE['noaazone']);
		  $CACHE = $cacheFileDir."forecast-".$SITE['noaazone']."-meta-json.txt";
	  }
	  if($sname == 'DSAPIkey') {
			if(isset($SITE['DSAPIkey']) and ! preg_match('|specify|i',$SITE['DSAPIkey'])) {
				list($DS_LOC,$DS_LATLONG) = explode('|',$SITE['DSforecasts'][0]);
				$DSAPIkey = $SITE['DSAPIkey'];
				$DSLANG = $SITE['lang'];
				$showUnitsAs = $SITE['DSshowUnitsAs'];
		    $TESTURL = "https://api.darksky.net/forecast/$DSAPIkey/$DS_LATLONG" .
          "?exclude=minutely&lang=en&units=$showUnitsAs";
		    $CACHE = $cacheFileDir."DS-forecast-0-$showUnitsAs-en.txt";
			
			} else {
				print "DarkSky test bypassed .. \$SITE['DSAPIkey'] not configured.\n";
				continue;
			}
	  }
	  if($sname == 'PWAPIkey') {
			if(isset($SITE['PWAPIkey']) and ! preg_match('|specify|i',$SITE['PWAPIkey'])) {
				list($PW_LOC,$PW_LATLONG) = explode('|',$SITE['PWforecasts'][0]);
				$PWAPIkey = $SITE['PWAPIkey'];
				$PWLANG = $SITE['lang'];
				$showUnitsAs = $SITE['PWshowUnitsAs'];
		    $TESTURL = "https://api.pirateweather.net/forecast/$PWAPIkey/$PW_LATLONG" .
          "?exclude=minutely&extend=hourly&units=$showUnitsAs";
		    $CACHE = $cacheFileDir."PW-forecast-0-en.txt";

			} else {
				print "Pirateweather test bypassed .. \$SITE['PSAPIkey'] not configured.\n";
				continue;
			}
	  }
	  if($sname == 'VCAPIkey') {
			if(isset($SITE['VCAPIkey']) and ! preg_match('|specify|i',$SITE['VCAPIkey'])) {
				list($VC_LOC,$VC_LATLONG) = explode('|',$SITE['VCforecasts'][0]);
				$VCAPIkey = $SITE['VCAPIkey'];
				$VCLANG = $Lang;
				$showUnitsAs = $SITE['VCshowUnitsAs'];
		    $TESTURL = "https://weather.visualcrossing.com/VisualCrossingWebServices/rest/services/timeline/"."$VC_LATLONG/" .
      "?key=$VCAPIkey&include=days,hours,alerts,current&lang=$VCLANG&unitGroup=$showUnitsAs&iconSet=icons2";
		    $CACHE = $cacheFileDir."VC-forecast-0-$showUnitsAs-$Lang.txt";

			} else {
				print "VisualCrossing test bypassed .. \$SITE['VCAPIkey'] not configured.\n";
				continue;
			}
	  }
	  if($sname == 'OWMAPIkey') {
			if(isset($SITE['OWMAPIkey']) and ! preg_match('|specify|i',$SITE['OWMAPIkey'])) {
				list($OWM_LOC,$OWM_LATLONG) = explode('|',$SITE['OWMforecasts'][0]);
        list($OWMlat,$OWMlong) = explode(',',$OWM_LATLONG);
				$OWMAPIkey = $SITE['OWMAPIkey'];
				$OWMLANG = $Lang;
				$showUnitsAs = ($SITE['OWMshowUnitsAs'] == 'us')?'imperial':'metric';
		    $TESTURL = "https://api.openweathermap.org/data/3.0/onecall?" .
        "lat=$OWMlat&lon=$OWMlong&exclude=minutely" .
        "&units=$showUnitsAs&lang=$OWMLANG&appid=$OWMAPIkey";
		    $CACHE = $cacheFileDir."OWM-forecast-0-$showUnitsAs-$Lang.txt";

			} else {
				print "OpenWeatherMap test bypassed .. \$SITE['OWMAPIkey'] not configured.\n";
				continue;
			}
	  }
	  if($sname == 'AWAPIkey') {
			if(isset($SITE['AWAPIkey']) and 
			   ! preg_match('|specify|i',$SITE['AWAPIkey']) and
				 isset($SITE['AWAPIsecret']) and
				 ! preg_match('|specify|i',$SITE['AWAPIsecret']) ) {
				list($AW_LOC,$AW_LATLONG) = explode('|',$SITE['AWforecasts'][0]);
				$AWAPIkey = $SITE['AWAPIkey'];
				$AWAPIsecret = $SITE['AWAPIsecret'];
				$AWLANG = $SITE['lang'];
				$showUnitsAs = $SITE['AWshowUnitsAs'];
		    $TESTURL = "https://api.aerisapi.com/forecasts/?p=$AW_LATLONG" .
             "&format=json&filter=daynight,precise&limit=14&client_id=$AWAPIkey&client_secret=$AWAPIsecret";
		    $CACHE = $cacheFileDir."AW-forecast-0-$showUnitsAs-en.txt";
			
			} else {
				print "Aerisweather test bypassed .. \$SITE['AWAPIkey'] and/or \$SITE['AWAPIsecret']not configured.\n";
				continue;
			}
	  }
	  if($sname == 'WCAPIkey') {
			if(isset($SITE['WCAPIkey']) and ! preg_match('|specify|i',$SITE['WCAPIkey']) and 
			   isset($SITE['WCforecasts']) and isset($SITE['WCunits']) ) {
				if(isset($SITE['WC_LOC'])) {
				  list($WC_LOC,$WC_LATLONG) = explode('|',$SITE['WC_LOC']);
				} else {
				  list($WC_LOC,$WC_LATLONG) = explode('|',$SITE['WCforecasts'][0]);
				}
				$WCAPIkey = $SITE['WCAPIkey'];
				$WCLANG = $SITE['lang'];
				$WCunits = $SITE['WCunits'];
				$TESTURL = 'https://api.weather.com/v3/wx/forecast/daily/5day?'. 
				           'format=json&units=%s&language=%s&apiKey=%s&geocode=%s';

	      $TESTURL = sprintf($TESTURL,$WCunits,'en_EN',$WCAPIkey,$WC_LATLONG); 

		    $CACHE = $cacheFileDir."WC-forecast-0-$WCunits-en.txt";
			
			} else {
				print "WC-forecast test bypassed .. \$SITE['WCAPIkey'] or \$SITE['WCforecasts'] or \$SITE['WCunits'] not configured.\n";
				continue;
			}
	  }
		$printURL = url_sanitize($TESTURL);
	  print "URL: $printURL\n";
	  if($CACHE <> '') {
		print "Cache: $CACHE\n";
	  }
	  $rawhtml = fetchUrlWithoutHanging($TESTURL,false);
	  $Debug = str_replace('<!--','',$Debug);
	  $Debug = str_replace('-->','',$Debug);
	  print $Debug;
    $stuff = explode("\r\n\r\n",$rawhtml); // maybe we have more than one header due to redirects.
    $content = (string)array_pop($stuff); // last one is the content
    $headers = (string)array_pop($stuff); // next-to-last-one is the headers
	  $html = explode("\n",$content);  // put HTML area as separate lines
	  $RC = '';
	  if (preg_match("|^HTTP\/\S+ (\d+)|",$headers,$matches)) {
		  $RC = trim($matches[1]);
	  }
	  print "RC=$RC, bytes=" . strlen($rawhtml) . "\n";

	  $age = -1;
	  $udate = 'unknown';
	  $budate = 0;
	  if(preg_match('|\nLast-Modified: (.*)\n|Ui',$headers,$match)) {
		  $udate = trim($match[1]);
		  $budate = strtotime($udate);
		  $age = abs(time() - $budate); // age in seconds
		  print "Data age=$age sec '$udate'\n";
	  }
		
	  if (!preg_match('| 200 |',$headers)) {
		print "------------\nContent returned:\n\n".strip_ltgt($content)."\n------------\n";
		print "\nSkipped cache write test to $CACHE file.\n";
		print "Test was NOT successful.\n";
	  } elseif ($CACHE <> '') {
		  $fp = fopen($CACHE,'w');
		  if($fp) {
			$write = fputs($fp, $rawhtml); 
			fclose($fp);
			print "Wrote ".strlen($rawhtml). " bytes to $CACHE successfully.\n";
			print "Test was SUCCESSFUL.\n";
		  } else {
			print "Error: Unable to write to $CACHE file.\n";
			print "Test was NOT successful.\n";
		  }
	  } 
			  
  
	
	  print "--end $sdescript check --\n\n";
	}
  
  
  }
  
  print "\nTotal time taken = " . sprintf("%01.3f",round($TOTALtime,3)) . " secs.\n";
  $time_finished = time();
  $time_elapsed = $time_finished - $time_init;
  print "Elapsed $time_elapsed seconds.\n\n";
} // end fetch-time-checking

if(!$doQuiet) {
print "PHP Version " . phpversion() . "\n";
print "Memory post_max_size " . ini_get('post_max_size') . " bytes.\n";
print "Memory usage " . memory_get_usage() . " bytes.\n";
print "Memory peak usage " . memory_get_peak_usage() . " bytes.\n";
print "</pre>\n";
}

if(!$doQuiet) {
  print '
</body>
</html>
';
}

// ------------------------------------------------------------------
function url_sanitize($inurl) {
	// replace embedded API keys, and change & to &amp; in URL args
	$toFind = array('apiKey','client_id','client_secret');

	$outurl = $inurl;
	// special DarkSky - API key in URL itself
	$outurl = preg_replace('!api.darksky.net/forecast/[^\/]+/!','api.darksky.net/forecast/-redacted-/',$outurl);
	
	$U = parse_url($inurl);
	if(isset($U['query'])) {
		$A = explode('&',$U['query'].'&');
	  $newArgs = '';
		$sep = '';
		// print '<!-- A='.print_r($A,true)." -->\n";
		foreach ($A as $q) { // examine and maybe replace each query arg
			list($arg,$val) = explode('=',$q.'=');
			if(empty($arg)) {continue;}
			if(in_array($arg,$toFind)) {
				$newArgs .= "$sep$arg=-redacted-";
			} else {
				$newArgs .= "$sep$arg=$val";
			}
			$sep = '&';
		}
		$outurl = preg_replace('!\?.*$!','?'.$newArgs,$outurl);
	}
	
	$outurl = str_replace('&','&amp;',$outurl);
	
	return($outurl);
}

// ------------------------------------------------------------------
function strip_ltgt($in) {
	$t = str_replace('<','&lt;',$in);
	$t = str_replace('>','&gt;',$t);
	return($t);
}

// ------------------------------------------------------------------
function printHeaders() {
  global $Version, $doQuiet;
  $outputText = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>Saratoga-weather.org Template Test Utility</title>
<meta http-equiv="Robots" content="noindex,nofollow,noarchive" />
<meta name="author" content="Ken True" />
<meta name="copyright" content="&copy; 2012-'.gmdate('Y').', Saratoga-Weather.org" />
<meta name="Description" content="Saratoga-weather.org AJAX/PHP website template diagnostic utility." />
<style type="text/css">
.row-odd  {background-color: #96C6F5; }
.row-even {background-color: #EFEFEF; }
.num { 
        float: left; 
        color: gray; 
        font-size: 13px;    
        font-family: monospace; 
        text-align: right; 
        margin-right: 6pt; 
        padding-right: 6pt; 
        border-right: 1px solid gray;
} 

body {margin: 0px; margin-left: 5px;} 
td {    vertical-align: top;
        font-size: 13px;    
        font-family: monospace; 
} 
code {white-space: nowrap;
        font-size: 13px;    
        font-family: monospace; 
}
.recommend {
	color: blue;
	text-decoration-line: underline;
	font-weight: bold;
	cursor: help;
}
</style>
</head>
<body style="background-color:#FFFFFF; font-family:Arial, Helvetica, sans-serif;font-size: 10pt;">
<h3>'.$Version.'</h3>
';
	if(!$doQuiet) {
		print $outputText;
	} else {
		return($outputText);
	}
}
// ------------------------------------------------------------------
// Retrieve information about the currently installed GD library
// script by phpnet at furp dot com (08-Dec-2004 06:59)
//   from the PHP usernotes about gd_info
function describeGDdyn() {
 echo "\n<ul><li>GD support: ";
 if(function_exists("gd_info")){
   echo "<span style=\"color:#00ff00\">is available.</span>";
   $info = gd_info();
   $keys = array_keys($info);
   for($i=0; $i<count($keys); $i++) {
	  if(is_bool($info[$keys[$i]])) {
		echo "</li>\n<li>" . $keys[$i] .": " . yesNo($info[$keys[$i]]);
	  } else {
		echo "</li>\n<li>" . $keys[$i] .": " . $info[$keys[$i]];
	  }
   }
 } else { 
   echo "<span style=\"color:#ff0000\">is NOT AVAILABLE but required.</span>"; 
 }
 echo "</li></ul>\n";
}

// ------------------------------------------------------------------

function yesNo($bool){
 if($bool) {
	 return "<span style=\"color:#00ff00\"> is available</span>";
 } else {
	 return "<span style=\"#ff0000\"> is NOT available</span>";
 }
}  

// ------------------------------------------------------------------

function microtime_float()
{
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + (float)$sec);
}
// ------------------------------------------------------------------
 function fetchUrlWithoutHanging($url,$useFopen) {

// get contents from one URL and return as string 
  global $Debug, $needCookie,$timeStamp,$TOTALtime;
  $overall_start = time();
  if (true or ! $useFopen) {
   // Set maximum number of seconds (can have floating-point) to wait for feed before displaying page without feed
   $numberOfSeconds=4;   

// Thanks to Curly from ricksturf.com for the cURL fetch functions

    $data = '';
    $domain = parse_url($url, PHP_URL_HOST);
    $theURL = str_replace('nocache', '?' . $overall_start, $url); // add cache-buster to URL if needed
		$thePrintURL = url_sanitize($theURL);
    $Debug.= "<!-- curl fetching '$thePrintURL' -->\n";
    $ch = curl_init(); // initialize a cURL session
    curl_setopt($ch, CURLOPT_URL, $theURL); // connect to provided URL
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // don't verify peer certificate
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (check-fetch-times.php - saratoga-weather.org)');
//    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:58.0) Gecko/20100101 Firefox/58.0');
//    curl_setopt($ch, CURLOPT_HTTPHEADER, // request LD-JSON format
//    array(
//      "Accept: *"
//    ));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $numberOfSeconds); //  connection timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, $numberOfSeconds); //  data timeout
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // return the data transfer
    curl_setopt($ch, CURLOPT_NOBODY, false); // set nobody
    curl_setopt($ch, CURLOPT_HEADER, true); // include header information

    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);              // follow Location: redirect
    curl_setopt($ch, CURLOPT_MAXREDIRS, 1);                      //   but only one time

    if (isset($needCookie[$domain])) {
      curl_setopt($ch, $needCookie[$domain]); // set the cookie for this request
      curl_setopt($ch, CURLOPT_COOKIESESSION, true); // and ignore prior cookies
      $Debug.= "<!-- cookie used '" . $needCookie[$domain] . "' for GET to $domain -->\n";
    }

    $data = curl_exec($ch); // execute session
    if (curl_error($ch) <> '') { // IF there is an error
      $Debug.= "<!-- curl Error: " . curl_error($ch) . " -->\n"; //  display error notice
    }
   
    $cinfo = curl_getinfo($ch); // get info on curl exec.
    /*
    curl info sample
    Array
    (
    [url] => http://saratoga-weather.net/clientraw.txt
    [content_type] => text/plain
    [http_code] => 200
    [header_size] => 266
    [request_size] => 141
    [filetime] => -1
    [ssl_verify_result] => 0
    [redirect_count] => 0
    [total_time] => 0.125
    [namelookup_time] => 0.016
    [connect_time] => 0.063
    [pretransfer_time] => 0.063
    [size_upload] => 0
    [size_download] => 758
    [speed_download] => 6064
    [speed_upload] => 0
    [download_content_length] => 758
    [upload_content_length] => -1
    [starttransfer_time] => 0.125
    [redirect_time] => 0
    [redirect_url] =>
    [primary_ip] => 74.208.149.102
    [certinfo] => Array
    (
    )
    [primary_port] => 80
    [local_ip] => 192.168.1.104
    [local_port] => 54156
    )
    */
		if($url != $cinfo['url']) {
			$Debug .= "<!-- note: fetched '".$cinfo['url']."' after redirect was followed. -->\n";
			//$Debug .= "<!-- cinfo=".print_r($cinfo,true)." -->\n";
		}

    $Debug.= "<!-- HTTP stats: " . " RC=" . $cinfo['http_code'];
		if (isset($cinfo['primary_ip'])) {
			$Debug .= " dest=" . $cinfo['primary_ip'];
		}
    if (isset($cinfo['primary_port'])) {
      $Debug .= " port=" . $cinfo['primary_port'];
    }

    if (isset($cinfo['local_ip'])) {
      $Debug.= " (from sce=" . $cinfo['local_ip'] . ")";
    }

    $Debug.= "\n      Times:" . 
		" dns=" . sprintf("%01.3f", round($cinfo['namelookup_time'], 3)) . 
		" conn=" . sprintf("%01.3f", round($cinfo['connect_time'], 3)) . 
		" pxfer=" . sprintf("%01.3f", round($cinfo['pretransfer_time'], 3));
    if ($cinfo['total_time'] - $cinfo['pretransfer_time'] > 0.0000) {
      $Debug.= " get=" . sprintf("%01.3f", round($cinfo['total_time'] - $cinfo['pretransfer_time'], 3));
    }

    $Debug.= " total=" . sprintf("%01.3f", round($cinfo['total_time'], 3)) . " secs -->\n";

    // $Debug .= "<!-- curl info\n".print_r($cinfo,true)." -->\n";
    if(isset($cinfo['header_size'])) {
			$headerSize = $cinfo['header_size'];
			$headerArray = explode("\r\n\r\n",substr($data,0,$headerSize-4));
			//$Debug .= "<!-- headerArray=".print_r($headerArray,true)." -->\n";
		} else {
			$headerSize = 0;
		}
    curl_close($ch); // close the cURL session

    // $Debug .= "<!-- raw data\n".$data."\n -->\n";
    //$stuff = explode("\r\n\r\n",$data); // maybe we have more than one header due to redirects.
    //$content = (string)array_pop($stuff); // last one is the content
    //$headers = (string)array_pop($stuff); // next-to-last-one is the headers
		if($headerSize > 0) {
			$headers = (string)array_pop($headerArray); // take the last header
			$content = substr($data,$headerSize);
			//$Debug .= "<!-- header size=$headerSize -->\n";
		} else {
			$i = strpos($data,"\r\n\r\n"); // find the first header
			$headers = substr($data,0,$i);
			$content = substr($data,$i+4);
			//$Debug .= "<!-- header size=$i found -->\n";
		}
			
    if ($cinfo['http_code'] <> '200') {
      $Debug.= "<!-- headers returned:\n" . $headers . "\n -->\n";
    }
		$firstRC = $cinfo['http_code'];
    if(preg_match('|^HTTP\/\S+ (.*)\r\n|',$data,$m)) {$firstRC = trim($m[1]); }
		if ($firstRC == '200') {
			return $data;
		} else {
      return $headers."\r\n\r\n".$content; // return headers+contents
		}
  }
  else {

    //   print "<!-- using file_get_contents function -->\n";

    $STRopts = array(
      'http' => array(
        'method' => "GET",
        'protocol_version' => 1.1,
        'header' => "Cache-Control: no-cache, must-revalidate\r\n" . 
					"Cache-control: max-age=0\r\n" . 
					"Connection: close\r\n" . 
					"User-agent: Mozilla/5.0 (check-fetch-times.php - saratoga-weather.org)\r\n" . 
					"Accept: application/ld+json\r\n"
      ) ,
      'https' => array(
        'method' => "GET",
        'protocol_version' => 1.1,
        'header' => "Cache-Control: no-cache, must-revalidate\r\n" . 
					"Cache-control: max-age=0\r\n" . 
					"Connection: close\r\n" . 
					"User-agent: Mozilla/5.0 (check-fetch-times.php - saratoga-weather.org)\r\n" . 
					"Accept: application/ld+json\r\n"
      )
    );
    $STRcontext = stream_context_create($STRopts);
    $T_start = ADV_fetch_microtime();
    $xml = file_get_contents($url, false, $STRcontext);
    $T_close = ADV_fetch_microtime();
    $headerarray = get_headers($url, 0);
    $theaders = join("\r\n", $headerarray);
    $xml = $theaders . "\r\n\r\n" . $xml;
    $ms_total = sprintf("%01.3f", round($T_close - $T_start, 3));
    $Debug.= "<!-- file_get_contents() stats: total=$ms_total secs -->\n";
    $Debug.= "<-- get_headers returns\n" . $theaders . "\n -->\n";

    //   print " file() stats: total=$ms_total secs.\n";

    $overall_end = time();
    $overall_elapsed = $overall_end - $overall_start;
    $Debug.= "<!-- fetch function elapsed= $overall_elapsed secs. -->\n";

    //   print "fetch function elapsed= $overall_elapsed secs.\n";

    return ($xml);
  }
}    // end fetchUrlWithoutHanging

// ------------------------------------------------------------------
#---------------------------------------------------------  
# load file to string for version checking
#--------------------------------------------------------- 
function chk_file_version($inFile) {
   global $selectedVersions,$selectedVersionsType,$earliestDate;
	 
	 if(isset($selectedVersionsType[$inFile]) and $selectedVersionsType[$inFile] == 'Deprecated' and file_exists($inFile)) {
		 return(
	  array('n/a','','',"<strong>$inFile is deprecated.</strong>",'','<strong>Ok to delete from website.</strong>')); 
	 }
	 if(isset($selectedVersionsType[$inFile]) and $selectedVersionsType[$inFile] == 'Deprecated' and !file_exists($inFile)) {
		 return(
	  array('n/a','','',"",'','')); 
	 }
   if(!file_exists($inFile)) {
	  return(
	  array('n/a','','',"<strong>$inFile file not found.</strong>",'','<strong>File not installed</strong>')); 
   }
   $mDate = date('Y-m-d H:i T',filemtime($inFile));
   $tContents = file_get_contents($inFile);
   $vInfo = scan_for_version_string($tContents);
	$tContents = preg_replace('|\r|is','',$tContents);
	$FileMD5 = md5($tContents);

   if(strlen($vInfo) > 120) {$vInfo = '(not specified)'; }
   if(preg_match('!(\d+\.\d+)[^\d]*(\d+-\S{3}-\d{4})!',$vInfo,$matches)) {
	$vNumber = $matches[1];
	$vDate = date('Y-m-d',strtotime($matches[2]));
   } else {
	$vNumber = 'n/a';
	$vDate = 'n/a';
   }
   $fStatus = 'unknown';
   if(isset($selectedVersions[$inFile])) { 
     list($mstModDate,$mstSize,$mstFileMD5,$mstFversion,$mstFvDate,$mstFvDesc) = 
	    explode("\t",$selectedVersions[$inFile]);
	 $MD5matches = ($mstFileMD5 == $FileMD5)?true:false;
	 $VerMatches = ($vNumber <> 'n/a' and $mstFversion <> 'n/a' and 
	    strcmp($vNumber,$mstFversion) === 0)?true:false;

	 if ($MD5matches) { $fStatus = "Current<!-- MD5 matched -->"; }
	 if ($fStatus == 'unknown' and $VerMatches) {$fStatus = 'Current<!-- version matched -->'; }
	 if ($fStatus == 'unknown' and $vNumber <> 'n/a' and $mstFversion <> 'n/a' and 
	    strcmp($vNumber,$mstFversion) < 0) {
		  $fStatus = "<strong>Need update to<br/>V$mstFversion - $mstFvDate</strong>"; 
		  $earliestDate = ($mstFvDate < $earliestDate)?$mstFvDate:$earliestDate;
	 }
	 if ($fStatus == 'unknown' and $vNumber <> 'n/a' and $mstFversion <> 'n/a' and 
	    strcmp($vNumber,$mstFversion) > 0) {
		  $fStatus = "<strong>Installed version is more recent</strong>"; 

	 }
	 if ($fStatus == 'unknown' and $mstFversion <> 'n/a' and $mstFvDate <> 'n/a') {
		  $fStatus = "<strong>Need update to<br/>V$mstFversion - $mstFvDate</strong>";
		  $earliestDate = ($mstFvDate < $earliestDate)?$mstFvDate:$earliestDate;
	 }
  
   }
	 $vInfo = str_replace('--><?php','',$vInfo); // in case some weird stuff is in the version string
   return(array($mDate,$vNumber,$vDate,$vInfo,$FileMD5,$fStatus));
}

#---------------------------------------------------------  
# scan for a version string in a PHP/JS/TXT file
#---------------------------------------------------------  
function scan_for_version_string($input) {

	$vstring = '(not specified)';
	
	preg_match('/\$\S*Version\s+=\s+[\'|"]([^\'|"]+)[\'|"];/Uis',$input,$matches);
	if(isset($matches[1])) {
		$vstring = $matches[1];
//		print "<!--  1:found $vstring  -->\n";
		return(trim($vstring));
	}
    
	preg_match_all('/Version (.*)\n/Uis',$input,$matches);
	
//	print "---2:Matches\n".print_r($matches,true)."\n---\n";
	
	if (isset($matches[1]) and count($matches[1]) > 0) {
		for($i=count($matches[1])-1;$i>=0;$i--) {
           $tstring = $matches[1][$i];		    
		   if(preg_match('|\d+-\S{3}-\d{4}|',$tstring)) {
		     $vstring = $tstring;
//		     print "<!--  2:found $vstring  -->\n";
		     return(trim($vstring));
		   }
	   }

	}
	
	return($vstring);
	
} // end scan_for_version_string

#---------------------------------------------------------  
# load the to-scan array with the filenames to look for
#---------------------------------------------------------  
function load_selected_array($key) {
	global $MasterVersions,$selectedVersions,$doQuiet;
	$n = 0;
	foreach ($MasterVersions as $k => $data) {
		list($base,$file) = explode("\t",$k);
		if($base == $key) {
			$selectedVersions["$file"] = $data;
			$n++;
		}
		
	}
	$outputText = "..loaded $n version descriptors for $key.<br/>\n";
	if(!$doQuiet) {
		print $outputText;
	} else {
		return($outputText);
	}
} // end load_selected_array

#---------------------------------------------------------  
# display file with PHP highlighting and line numbers
#---------------------------------------------------------  

function highlight_file_num($file) 
{ 
  global $doQuiet;
  $lines = implode('<br />',range(1, count(file($file)))); 
  //$content = highlight_file($file, true); 
  $recs = file($file);
	$content = '';
	foreach ($recs as $n => $rec) {
		$content .= preg_replace('!(\$\S+(API|pw|AWNkey)\S+)\s*=\s*[\'\"]\S+[\'\"];!U',"$1 = '--redacted--'; // key not displayed ",$rec);
	}
  $content = highlight_string($content,true);
  $outputText = "<table style=\"border: 1px solid black;\"><tr><td class=\"num\">\n$lines\n</td><td>\n$content\n</td></tr></table>";
	if(!$doQuiet) {
		print $outputText;
	} else {
		return($outputText);
	}

 } 

#---------------------------------------------------------  
# decode unix file permissions
#---------------------------------------------------------  

function decode_permissions($perms) {

  if (($perms & 0xC000) == 0xC000) {
	  // Socket
	  $info = 's';
  } elseif (($perms & 0xA000) == 0xA000) {
	  // Symbolic Link
	  $info = 'l';
  } elseif (($perms & 0x8000) == 0x8000) {
	  // Regular
	  $info = '-';
  } elseif (($perms & 0x6000) == 0x6000) {
	  // Block special
	  $info = 'b';
  } elseif (($perms & 0x4000) == 0x4000) {
	  // Directory
	  $info = 'd';
  } elseif (($perms & 0x2000) == 0x2000) {
	  // Character special
	  $info = 'c';
  } elseif (($perms & 0x1000) == 0x1000) {
	  // FIFO pipe
	  $info = 'p';
  } else {
	  // Unknown
	  $info = 'u';
  }
  
  // Owner
  $info .= (($perms & 0x0100) ? 'r' : '-');
  $info .= (($perms & 0x0080) ? 'w' : '-');
  $info .= (($perms & 0x0040) ?
			  (($perms & 0x0800) ? 's' : 'x' ) :
			  (($perms & 0x0800) ? 'S' : '-'));
  
  // Group
  $info .= (($perms & 0x0020) ? 'r' : '-');
  $info .= (($perms & 0x0010) ? 'w' : '-');
  $info .= (($perms & 0x0008) ?
			  (($perms & 0x0400) ? 's' : 'x' ) :
			  (($perms & 0x0400) ? 'S' : '-'));
  
  // World
  $info .= (($perms & 0x0004) ? 'r' : '-');
  $info .= (($perms & 0x0002) ? 'w' : '-');
  $info .= (($perms & 0x0001) ?
			  (($perms & 0x0200) ? 't' : 'x' ) :
			  (($perms & 0x0200) ? 'T' : '-'));
  
  return $info;
}

//--------------------------------------------------

function WU_get_APIURL ($rawURL) {
	global $WUAPIkey,$WULANG,$Debug,$doDebug;

// try to generate an API request URL from a WU page URL	
/*
'Saratoga|http://www.wunderground.com/cgi-bin/findweather/getForecast?query=95070',
'Aarhus|http://www.wunderground.com/global/stations/06075.html',
'Auckland|http://english.wunderground.com/cgi-bin/findweather/getForecast?query=-36.910%2C174.771&sp=IAUCKLAN110', // Awhitu, Waiuku New Zealand
'Amsterdam|http://www.wunderground.com/cgi-bin/findweather/getForecast?query=Amsterdam%2C+Netherlands',
'Paris|http://www.wunderground.com/cgi-bin/findweather/getForecast?query=Paris%2C+France',
'Stockholm|http://www.wunderground.com/cgi-bin/findweather/getForecast?query=Stockholm%2C+Sweden',
'Oslo|http://www.wunderground.com/cgi-bin/findweather/getForecast?query=Oslo%2C+Norway',
'Moscow|http://www.wunderground.com/global/stations/27612.html',
'Athens|http://www.wunderground.com/cgi-bin/findweather/getForecast?query=Athens%2C+Greece',
'Tel Aviv|http://www.wunderground.com/cgi-bin/findweather/getForecast?query=Tel+Aviv%2C+Israel',
'Madrid|http://www.wunderground.com/cgi-bin/findweather/getForecast?query=Madrid%2C+Spain',
'Helsinki|http://www.wunderground.com/cgi-bin/findweather/getForecast?query=Helsinki%2C+Finland',
'Castrop-Rauxel|http://www.wunderground.com/cgi-bin/findweather/getForecast?query=51.572%2C7.376&sp=INORDRHE72',
'Southampton|http://www.wunderground.com/global/stations/03865.html',
'Canvey Island, Essex|http://www.wunderground.com/weather-forecast/zmw:00000.57.03691',
'Saratoga PWS|http://www.wunderground.com/cgi-bin/findweather/getForecast?query=pws:KCASARAT1',
'St. Nicholas|http://www.wunderground.com/q/locid:UKEN1390',
'Alberta (Canada)|http://www.wunderground.com/q/locid:CAXX4520',
'Andover (Middle Wallop)|http://www.wunderground.com/cgi-bin/findweather/getForecast?query=zmw:00000.1.03749',
'Assen (Holland)|http://www.wunderground.com/q/zmw:00000.6.06280',
'Honolulu|http://www.wunderground.com/US/HI/Honolulu.html',
'Malta|http://www.wunderground.com/global/ML.html',

as:

http://api.wunderground.com/api/$WUAPIkey/forecast10day/geolookup/lang:$WULANG/q/$WUQUERY.json

// Note: new formats for the query:
//  CA/San_Francisco	US state/city
//  60290	US zipcode
//  Australia/Sydney	country/city
//  37.8,-122.4	latitude,longitude
//  KJFK	airport code
//  pws:KCASANFR70	PWS id

*/
   $newURL = 'http://api.wunderground.com/api/%s/forecast10day/geolookup/lang:%s/q/%s.json';

   $Status = "<!-- WU_API Raw URL='$rawURL' -->\n";
   if(preg_match("|query=([^\&]+)|i",$rawURL,$matches)) {
	 $rawQuery = urldecode(trim($matches[1]));
	 
	 if(preg_match('|^[\d\-\.\,]+$|',$rawQuery)) { // likely lat,long query.. use it
	 
	 } else { // likely a City, State query
		$t = explode(', ',$rawQuery);
		if(isset($t[1])) { $rawQuery = $t[1].'/'.$t[0]; }
		$rawQuery = preg_replace('| |','_',$rawQuery);
	 }
	 if($doDebug) {$Status .= "<!-- query='$rawQuery' -->\n"; }
	 $newURL = sprintf($newURL,$WUAPIkey,$WULANG,$rawQuery);
     $Status .= "<!-- WU API New URL='$newURL' -->\n";
	 return($newURL);
   } // end query= processing
   
   if(preg_match('|global/stations/(\d+).html|i',$rawURL,$matches)) {
	 $rawQuery = 'zmw:00000.1.'.trim($matches[1]);
	 $newURL = sprintf($newURL,$WUAPIkey,$WULANG,$rawQuery);
     $Status .= "<!-- WU API New URL='$newURL' -->\n";
	 return($newURL);
   }
   
   if(preg_match('|weather-forecast/zmw:([\d\.]+)|i',$rawURL,$matches)) {
	 $rawQuery = 'zmw:'.trim($matches[1]);
	 $newURL = sprintf($newURL,$WUAPIkey,$WULANG,$rawQuery);
     $Status .= "<!-- WU API New URL='$newURL' -->\n";
	 return($newURL);
   }
   
   if(preg_match('|/q/([^\s]+)|i',$rawURL,$matches)) { // handle alternate locid:, zmw: 
	 $rawQuery = trim($matches[1]);
	 $newURL = sprintf($newURL,$WUAPIkey,$WULANG,$rawQuery);
     $Status .= "<!-- WU API New URL='$newURL' -->\n";
	 return($newURL);
   }

   if(preg_match('|/US/(.*)$|i',$rawURL,$matches)) { // handle US ST/Cityname
	 $rawQuery = trim($matches[1]);
	 $newURL = sprintf($newURL,$WUAPIkey,$WULANG,$rawQuery);
     $Status .= "<!-- WU API New URL='$newURL' -->\n";
	 return($newURL);
   }

   if(preg_match('|/global/([^\s]+)\.html|i',$rawURL,$matches)) { // handle US ST/Cityname
	 $rawQuery = trim($matches[1]);
	 $newURL = sprintf($newURL,$WUAPIkey,$WULANG,$rawQuery);
     $Status .= "<!-- WU API New URL='$newURL' -->\n";
	 return($newURL);
   }
	 
	 $turl = urldecode($rawURL);
	 if(preg_match('|/([\d\-\.\,]+)$|is',$turl,$matches)) {
		 $rawQuery = $matches[1];
     $newURL = sprintf($newURL,$WUAPIkey,$WULANG,$rawQuery);
     $Status .= "<!-- WU API New URL='$newURL' -->\n";
		 return($newURL);
	 }
  
   return('');
	
}

//-------------------------------------------------

function printInfo() {
	global $doQuiet;
  $outputText = "<h2>Website PHP information</h2>\n";
	$ourHost = '';
	if(isset($_SERVER['SCRIPT_URI'])) {
	  $U = parse_url($_SERVER['SCRIPT_URI']);
	  $ourHost = isset($U['host'])?$U['host']:'';
	}
	if(isset($_SERVER['HTTP_HOST']) and $ourHost == '') {
		$ourHost = $_SERVER['HTTP_HOST'];
	}
	if($ourHost !== '') {
		$ourIP = gethostbyname($ourHost);
		$ourIPName = gethostbyaddr($ourIP);
	} else {
		$ourHost = 'unk';
		$ourIP = 'unk';
		$ourIPName = 'unk';
	}

  $outputText .= "<p>\n";
  $outputText .= "Running on webhost '<b>$ourHost</b>' @ IP='<b>$ourIP</b>' (reverse IP Name='<b>$ourIPName</b>')<br/>\n";  
  $outputText .= "Webserver OS: <b>".php_uname()."</b><br/>\n";
  $outputText .= "PHP Version: <b>".PHP_VERSION."</b> built for <b>".PHP_OS."</b><br/>\n";
  if (version_compare(PHP_VERSION, '5.6.0', '<') ) {
    $outputText .= "<span style=\"color: red;\"><b>NOTE: some scripts require PHP 5.6+ for proper operation.</b></span><br/>\n";
  }
  $outputText .= "PHP cmd location: <b>".PHP_BINDIR."/php</b><br/>\n";
  
  $outputText .= "Document root: <b>".$_SERVER['DOCUMENT_ROOT']."</b><br/>\n";
  $outputText .= "Template root: <b>".__DIR__."</b><br/>\n";
  $outputText .= "allow_url_fopen = <b>";
  $outputText .= ini_get("allow_url_fopen")?"ON":"off";
  $outputText .= "</b><br/>\n";
  $outputText .= "allow_url_include = <b>";
  $outputText .= ini_get("allow_url_include")?"ON":"off";
  $outputText .= "</b><br/>\n";
  $outputText .= "request_order = <b>".ini_get('request_order');
  $outputText .= "</b><br/>\n";
		$streams = stream_get_wrappers();
	$outputText .= "Stream support for <b>http</b> ";
	$outputText .= in_array('http',$streams)?'is available':'is <b>NOT available but REQUIRED.</b>';
	$outputText .= "<br/>\n";
	$outputText .= "Stream support for <b>https</b> ";
	$outputText .= in_array('https',$streams)?'is available':'is <b>NOT available but REQUIRED.</b>';
	$outputText .= "<br/>\n";
	if(sort($streams,SORT_STRING)) {
	  $outputText .= "Streams supported: <strong>".join(', ',$streams)."</strong><br/>";
	}
	$xportlist = stream_get_transports();
	if(sort($xportlist,SORT_STRING)) {
	  $outputText .= "Socket transports: <strong>".join(', ',$xportlist)."</strong><br/>";
	}
	
	$outputText .= "\n";

	if(!$doQuiet) {
		print $outputText;
	} else {
		return($outputText);
	}

}

//-------------------------------------------------

function convert_NWS_filename($inURL,$NOAAZone) {
  // for advforecast2.php V5.00 with new forecast.weather.gov API site use
  // this converts old format URLs in settings to new format requests if needed
  define('APIURL',"https://api.weather.gov");
  define('FCSTURL',"https://forecast.weather.gov");
  
  global $Debug;
  $fileName = $inURL;
  
  // handle OLD formats of NWS URLS
  // autocorrect the point-forecast URL if need be
  /*
  // from: http://forecast.weather.gov/MapClick.php?CityName=Rathdrum&state=ID&site=MTR&textField1=47.828&textField2=-116.842&e=0&TextType=2
  // to: 
  https://api.weather.gov/points/47.82761,-116.842/forecast
  
  NOTE: the lat,long must be decimal numbers with up-to 4 decimal places and no trailing zeroes
  that's why the funky code:
	$t = sprintf("%01.4f",$matches[1]);  // forces 4 decimal places on number
	$t = (float)$t;                      // trims trailing zeroes by casting to float type.
  is used to enforce those API limits.
  
  */
  
  if(preg_match('|textField1=|i',$fileName)) {
	  $newlatlong = '';
	  preg_match('|textField1=([\d\.]+)|i',$fileName,$matches);
	  if(isset($matches[1])) {$t = sprintf("%01.4f",$matches[1]); $t = (float)$t; $newlatlong .= $t;}
	  preg_match('|textField2=([-\d\.]+)|i',$fileName,$matches);
	  if(isset($matches[1])) {$t = sprintf("%01.4f",$matches[1]); $t = (float)$t; $newlatlong .= ",$t";}
	  $newurl = APIURL.'/points/'.$newlatlong;
	  $pointURL = FCSTURL.'/point/'.$newlatlong;
	  return(array($newurl,$pointURL));
  }
  /*
  // from: 
  http://forecast.weather.gov/MapClick.php?lat=38.36818&lon=-75.5976&unit=0&lg=english&FcstType=text&TextType=2
  // to: 
  https://api.weather.gov/points/38.36818,-75.5976/forecast
  */
  if(preg_match('|lat=|i',$fileName)) {
	  $newlatlong = '';
	  preg_match('|lat=([\d\.]+)|i',$fileName,$matches);
	  if(isset($matches[1])) {$t = sprintf("%01.4f",$matches[1]); $t = (float)$t; $newlatlong .= $t;}
	  preg_match('|lon=([-\d\.]+)|i',$fileName,$matches);
	  if(isset($matches[1])) {$t = sprintf("%01.4f",$matches[1]); $t = (float)$t; $newlatlong .= ",$t";}
	  $newurl = APIURL.'/points/'.$newlatlong;
	  $pointURL = FCSTURL.'/point/'.$newlatlong;
	  return(array($newurl,$pointURL));
  }
  
  // handle NEW format of point URL
  if(preg_match('|/point/([\d\.]+),([\-\d\.]+)|i',$fileName,$matches)) {
	  $newlatlong = '';
	  if(isset($matches[1])) {$t = sprintf("%01.4f",$matches[1]); $t = (float)$t; $newlatlong .= $t;}
	  if(isset($matches[2])) {$t = sprintf("%01.4f",$matches[2]); $t = (float)$t; $newlatlong .= ",$t";}
	  $newurl = APIURL.'/points/'.$newlatlong;
	  $pointURL = FCSTURL.'/point/'.$newlatlong;
	  return(array($newurl,$pointURL));
  }
   return(array('unk',$inURL));
}

#---------------------------------------------------------  
#  based on filename, produce a update recommentation
#---------------------------------------------------------  

function get_update_recommendation($file) {
  global $filesToCustomize,$filesToRegenerate,$filesToReloadMB;
  
  $recommend = 'Replace current copy with updated copy.';
  
  $p = pathinfo($file);
  $fn = $p['basename'];
	$code = 'Replace';
	$recommend = 'Replace current copy with new copy';
	
  print "<!-- get_update_recommendation file='$file' fn='$fn' -->\n";
  if(stripos($filesToCustomize,$fn) !== false) {
		$code = 'Config+Repl';
	  $recommend = 'Replace current copy with updated copy *after customization*.';
  }
  if (preg_match('|prototype|i',$file)) {
		$code = 'Update+config';
	  $recommend = 'Replace current copy with updated copy and run config utility page to generate new file.';
  }
  if (preg_match('|language-\S\S\.txt|i',$file)) {
		$code = 'Replace';
	  $recommend = 'Replace current copy with update if you have made no changes to file, otherwise merge changes to your copy.';
  }
  if(stripos($filesToRegenerate,$fn) !== false) {
		$code = 'Regenerate';
	  $recommend = 'Do not replace this sample file. Use config utility page to generate your customized file instead.';
  }

  if(stripos($filesToReloadMB,$fn) !== false) {
		$code = 'Replace,Reload in MB';
	  $recommend = 'Replace current copy with updated copy and reload template in Meteobridge web Push Services.';
  }
  
  return(array($code,$recommend));
	
	
} // end get_update_recommendation

