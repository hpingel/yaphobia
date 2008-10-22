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
require_once( PATH_TO_YAPHOBIA. "classes/class.cliEnvironment.php");
require_once( PATH_TO_YAPHOBIA. "classes/class.callImportManager.php");

class cliGatherCalls extends cliEnvironment { 

	function __construct(){
		parent::__construct(
			" gather_calls.php is an example script to show how\n".
			" call data is imported into Yaphobia's database\n".
			" automatically.\n"
		);
		$this->checkOptionalConfig();
		$this->start();	
		
	}

	private function start(){
		$call_import = new callImportManager($this->dbh, $this->traceObj);
		$call_import->getFritzBoxCallerList();
		
		if (DUSNET_ACTIVE){ 
			$dusnet_callist = $call_import->getDusNetCalls( DUSNET_SIPACCOUNT, DUSNET_USERNAME, DUSNET_PASSWORD );
			$call_import->putDusNetCallArrayIntoDB($dusnet_callist, DUSNET_PROVIDER_ID);
		}
			
		if (SIPGATE_ACTIVE){ 
			//set month and year to empty values to persuade sipgate to return a complete call history
			//$call_import->setMonth("");
			//$call_import->setYear("");
			$sg_callist = $call_import->getSipgateCallsOfCurrentMonth( SIPGATE_USERNAME, SIPGATE_PASSWORD);
			$call_import->putSipgateCallArrayIntoDB($sg_callist, SIPGATE_PROVIDER_ID);
		}
	}
}

$cgc = new cliGatherCalls();

?>