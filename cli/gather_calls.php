<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 Henning Pingel
*  All rights reserved
*
*  This script is part of the Yaphobia project. Yaphobia is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*/



define( 'PATH_TO_YAPHOBIA', str_replace("cli","",dirname(__FILE__)) ); 
define( 'PATH_TO_SETTINGS', PATH_TO_YAPHOBIA . 'config/settings.php' );

//check for settings file

if (file_exists(PATH_TO_SETTINGS)){
	require_once(PATH_TO_SETTINGS);	
}
else{
	die('<p>ERROR: There is no configuration file <b>settings.php</b>!<br/>Please copy <b>settings.defaults.php</b> to <b>settings.php</b> and change the options within the file according to your needs.</p>');
}

require_once( PATH_TO_YAPHOBIA. "classes/class.db_manager.php");
require_once( PATH_TO_YAPHOBIA. "classes/class.callImportManager.php");

print "==================================================\n";
print " Welcome to Yaphobia!\n";
print " Yet another phone bill application...\n";
print " You are using Yaphobia version ".YAPHOBIA_VERSION."\n";
print "==================================================\n";
print " gather_calls.php is an example script to show how\n";
print " call data is imported into Yaphobia's database\n";
print " automatically.\n";
print "==================================================\n";

$db = new dbMan();
$call_import = new callImportManager($db);
print $call_import->getFritzBoxCallerList();

if (AUTOBILL_REMAINING_FLATRATE_CALLS)
	print $call_import->markFlateRateCallsAsBilled('0', 'Flatrate Festnetz');

if (DUSNET_ACTIVE){ 
	$call_import->getDusNetCalls( DUSNET_PROVIDER_ID, DUSNET_SIPACCOUNT, DUSNET_USERNAME, DUSNET_PASSWORD );
	print $call_import->getDusNetTrace();
}
	
if (SIPGATE_ACTIVE){ 
	$sg_callist = $call_import->getSipgateCallsOfCurrentMonth( SIPGATE_USERNAME, SIPGATE_PASSWORD);
	$call_import->putSipgateCallArrayIntoDB($sg_callist, SIPGATE_PROVIDER_ID);
	print $call_import->getSipgateTrace();
}

//searches through database to see if there are new call rates to add to the list
$call_import->checkForNewRateTypes();
	
print "==================================================\n";
print "= End of script                                  =\n";
print "==================================================\n";

?>