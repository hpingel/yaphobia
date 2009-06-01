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


define( 'PATH_TO_YAPHOBIA', dirname(__FILE__) . '/../' );
require_once( PATH_TO_YAPHOBIA. "classes/class.cliEnvironment.php");
require_once( PATH_TO_YAPHOBIA. "classes/class.callImportManager.php");

class cliRecheckUnmatchedCalls extends cliEnvironment { 

	function __construct(){
		parent::__construct(
			" recheckUnmatchedCalls.php is a debug script to solve\n".
			" problems with unmatched calls.\n"
		);
		$this->checkOptionalConfig();
		$this->start();	
	}

	private function start(){
		$call_import = new callImportManager($this->dbh, $this->traceObj);
		$call_import->recheckUnmatchedCalls();
	}
}

$cgc = new cliRecheckUnmatchedCalls();

?>