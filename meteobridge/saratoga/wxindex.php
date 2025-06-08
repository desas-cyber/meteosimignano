<?php
############################################################################
# A Project of TNET Services, Inc. and Saratoga-Weather.org (Canada/World-ML template set)
############################################################################
#
#   Project:    Sample Included Website Design
#   Module:     sample.php
#   Purpose:    Sample Page
#   Authors:    Kevin W. Reed <kreed@tnet.com>
#               TNET Services, Inc.
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
# Version 1.01 - 18-May-2022 - enable summary warnings from meteoalarm.eu display
# Version 1.02 - 03-Apr-2023 - correct ajax-dashboard loading from $SITE
require_once("Settings.php");
require_once("common.php");
############################################################################
$TITLE = langtransstr($SITE['organ']) . " - " .langtransstr('Home');
$showGizmo = true;  // set to false to exclude the gizmo
$useUTF8 = true; // UTF-8 required for meteoalarm.org alert information
include("top.php");
############################################################################
?>
</head>
<body>
<?php
############################################################################
include("header.php");
############################################################################
include("menubar.php");
############################################################################
?>

<div id="main-copy">
<div class="column-dark">
	<img src="<?php echo $SITE['imagesDir']; ?>spacer.gif" alt="spacer"
	height="2" width="620" style="padding:0; margin:0; border: none" />
  <?php 
	if(isset($SITE['useMeteoalarm']) and $SITE['useMeteoalarm']
	   and file_exists('get-meteoalarm-warning-inc.php')) {
    include_once("get-meteoalarm-warning-inc.php"); // Use the EU Meteoalarm warning
		if(file_exists($warn_summary)) {
			readfile($warn_summary);
		}
	}
	?>
	<div align="center">
	<?php if(isset($SITE['ajaxDashboard']) and file_exists($SITE['ajaxDashboard']))
	 { include_once($SITE['ajaxDashboard']);
	   } else {
		print "<p>&nbsp;</p>\n";
		print "<p>&nbsp;</p>\n";
		print "<p>Note: ajax-dashboard not included since weather station not yet specified.</p>\n";
        for ($i=0;$i<10;$i++) { print "<p>&nbsp;</p>\n"; }
	}?>
	</div>
</div><!-- end column-dark -->

</div><!-- end main-copy -->

<?php
############################################################################
include("footer.php");
############################################################################
# End of Page
############################################################################
?>