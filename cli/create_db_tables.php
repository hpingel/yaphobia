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

//check for settings file

define( 'PATH_TO_YAPHOBIA', str_replace("/cli","",dirname(__FILE__)) ); 
define( 'PATH_TO_SETTINGS', PATH_TO_YAPHOBIA . '/config/settings.php' );

if (file_exists(PATH_TO_SETTINGS)){
	require_once(PATH_TO_SETTINGS);	
}
else{
	die('<p>ERROR: There is no configuration file <b>settings.php</b>!<br/>Please copy <b>settings.defaults.php</b> to <b>settings.php</b> and change the options within the file according to your needs.</p>');
}

require_once( PATH_TO_YAPHOBIA. "/classes/class.db_manager.php");
require_once( PATH_TO_YAPHOBIA. "/classes/class.install_helpers.php");

$cdbt = new createDBTables();


?>