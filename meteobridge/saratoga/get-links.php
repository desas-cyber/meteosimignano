<?php
// proxy fetch for links files from http://www.northamericanweather.net/ for the global network map
// settings -------------------------------------------------------------------
$doTargetBlank = true; // =true to change links to have target="_blank"
$refreshTime = 600;  // 10 minute cache time
//-----------------------------------------------------------------------------

if(file_exists('Settings.php')) {include_once('Settings.php'); }

$fileSet = array(
  'network-list-inc.html' => 'http://www.northamericanweather.net/network-list-inc.html',
  'network-links-inc.html' => 'http://www.northamericanweather.net/network-links-inc.html',
  'member-count.txt' => 'http://www.northamericanweather.net/member-count.txt',
  'members-list-inc.html' => 'http://www.northamericanweather.net/members-list-inc.html'
);
$fileSetML = array(
  'network-list-inc.html' => 'http://www.northamericanweather.net/network-list-inc-ml.html',
  'network-links-inc.html' => 'http://www.northamericanweather.net/network-links-inc-ml.html',
  'member-count.txt' => 'http://www.northamericanweather.net/member-count-ml.txt',
  'members-list-inc.html' => 'http://www.northamericanweather.net/members-list-inc-ml.html'
);

if(isset($SITE['lang'])) {$fileSet = $fileSetML; }

// use foreach ($fileSetML below if using multilingual template set
foreach ($fileSet as $cacheName => $URL) {
  

if (file_exists($cacheName) and filemtime($cacheName) + $refreshTime > time() and filesize($cacheName) > 100) {
	$udate = gmdate("D, d M Y H:i:s", filemtime($cacheName));
    print "<!-- cache $cacheName lmod=$udate GMT -->\n";
    continue;
}


  $rawhtml = fetchUrlWithoutHangingLINKS($URL,false);
  
  list($headers,$html) = explode("\r\n\r\n",$rawhtml);
  $RC = '';
  if (preg_match("|^HTTP\/\S+ (.*)\r\n|",$rawhtml,$matches)) {
	$RC = trim($matches[1]);
  }
  
  if($doTargetBlank and preg_match('|\.html|i',$URL)) { // adjust all the links to have target="_blank"
    $html = preg_replace('|<a (.*)">(.*)</a>|Uis',"<a $1\" target=\"_blank\">$2</a>",$html);
    print "<!-- $cacheName target=\"_blank\" added to links -->\n";
  }

  if(preg_match('|200 |',$RC) and strlen($html) > 50) {
	$udate = gmdate("D, d M Y H:i:s", time()) . " GMT";
 
   $fp = fopen($cacheName, "w");
   if ($fp) {
	$write = fputs($fp, $html);
	if ($write) {
	  print "<!-- $cacheName lmod=$udate -->\n";
	}
	fclose($fp);
	
   } else {
	print "<!-- unable to write cache file $cacheName -->\n";
   }
 
  
  } else {
    print "<!-- Problem fetching $cacheName with RC=$RC , html length=".strlen($html) . "<br/>\n";
	print "Headers returned are:\n";
	print "<pre>\n$headers\n</pre>\n";
	print "cache not saved. -->\n";
   }
   
}

return;




// get contents from one URL and return as string 
 function fetchUrlWithoutHangingLINKS($url,$useFopen) {
// thanks to Tom at Carterlake.org for this script fragment
  global $Debug, $needCookie,$timeStamp;
  if (! $useFopen) {
   // Set maximum number of seconds (can have floating-point) to wait for feed before displaying page without feed
   $numberOfSeconds=4;   

   // Suppress error reporting so Web site visitors are unaware if the feed fails
   error_reporting(0);

   // Extract resource path and domain from URL ready for fsockopen
   $FullUrl = $url;
   $url = str_replace("http://","",$url);
   $urlComponents = explode("/",$url);
   $domain = $urlComponents[0];
   $resourcePath = str_replace($domain,"",$url);
   $resourcePath = preg_replace('|nocache|',$timeStamp,$resourcePath);
   $Debug .= "<!-- GET $resourcePath HTTP/1.1 \n      Host: $domain -->\n";

   // Establish a connection
   $socketConnection = fsockopen($domain, 80, $errno, $errstr, $numberOfSeconds);

   if (!$socketConnection)
       {
       // You may wish to remove the following debugging line on a live Web site
          print($Debug . "<!-- Network error: $errstr ($errno) -->");
       }    // end if
   else    {
       $xml = '';
	   $getString = "GET $resourcePath HTTP/1.1\r\nHost: $domain\r\nConnection: close\r\nUser-agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.11) Gecko/20071127 Firefox/2.0.0.11 global-conditions SaratogaWX)\r\n";
	   
	   $getString .= "\r\n";
       fputs($socketConnection, $getString);
   
       // Loop until end of file
       while (!feof($socketConnection))
           {
           $xml .= fgets($socketConnection, 8192);
           }    // end while

       fclose ($socketConnection);

       }    // end else

   return($xml);
 } else {
//   print "<!-- using file function -->\n";
   $xml = implode('',file($url));
   return($xml);
 }

   }    // end fetchUrlWithoutHanging
// ------------------------------------------------------------------


?>