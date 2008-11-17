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

if (!defined("PATH_TO_YAPHOBIA")) die("Please define constant PATH_TO_YAPHOBIA before including this script.");

require_once( PATH_TO_YAPHOBIA. "classes/class.trace.php");
require_once( PATH_TO_YAPHOBIA. "classes/class.db_manager.php");
require_once( PATH_TO_YAPHOBIA. "classes/class.installHelpers.php");
require_once( PATH_TO_YAPHOBIA. "classes/class.settingsValidator.php");

//test scripts need different settings files
if (!defined('PATH_TO_SETTINGS')) 
	define( 'PATH_TO_SETTINGS', PATH_TO_YAPHOBIA . 'config/settings.php' );
	
define( 'UGLY_LINE', "==================================================\n");

class cliEnvironment{

	protected
		$traceObj,
		$db,
		$dbh,
		$ih;
	
	function __construct( $infotext ){
		$this->traceObj = new trace('text');
		
		print 
			UGLY_LINE.
			" Welcome to Yaphobia!\n".
			" Yet another phone bill application...\n".
			" You are using Yaphobia version ".YAPHOBIA_VERSION."\n".
			UGLY_LINE.
			$infotext.
			UGLY_LINE;		
		
		//check for settings file
		if (file_exists(PATH_TO_SETTINGS)){
			require_once(PATH_TO_SETTINGS);	
		}
		else{
			die('ERROR: There is no configuration file settings.php!Please copy settings.defaults.php to settings.php and change the options within the file according to your needs.');
		}
		
		$this->sv = new settingsValidator();
		//check presence of mandatory settings
		$sermon = $this->sv->proofreadMandatorySettings();
		if ($sermon !=''){
			die($sermon);
		}
		
		$this->db = new dbMan();
		$this->dbh = $this->db->getDBHandle();
		$this->ih = new installHelpers($this->dbh);
		$this->ih->createDBTables();
	}
	
	protected function checkOptionalConfig(){
		//add default settings for optional settings
		$sermonOptionalSettings = $this->sv->proofreadOptionalSettings();
		//print $sermonOptionalSettings;
	}

	function __destruct(){
		print 
			UGLY_LINE.
			" End of script\n".
			UGLY_LINE;
	}
	
}

?>