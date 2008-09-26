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


/*
 * sipgateRemote
 * 
 * 
 */

class sipgateRemote {
	
	var $callerList;
	var $callerString;
		
	function __construct(){
		$this->callerList = array();	
		$this->callerString = "";	
		$this->sg_remote = new curllib();
		$this->sg_remote->setBaseUrl("https://secure.sipgate.de/user/");
		$this->sg_remote->enableCookieJar( YAPHOBIA_COOKIEJAR_DIR . 'cookiejar_sipgate.txt' );
		
	}
	
	function logon($user, $password){
		$comment = "logon to sipgate";
		$response = $this->sg_remote->postRequest(
			$comment, 
			"uname=$user&passw=$password&okey.x=7&okey.y=8&lasturi=%2Fuser%2Findex.php&jsh=1", 
			"index.php"
		);
		//print $response;
	}
	
	function logout(){
		$comment = "logoff from sipgate";
		$response = $this->sg_remote->getRequest(
			$comment, 
			"logout.php"
		);
	}

	function getEvnOfMonth( $month ){
		$comment = "trigger evn on sipgate for month $month";
		$response = $this->sg_remote->getRequest(
			$comment, 
			"konto_einzel.php?show=all&timeperiod=simple&timeperiod_simpletimeperiod=$month"
		);
		//print $response;		
		$comment = "download csv from sipgate for month $month";		
		$response = $this->sg_remote->binaryTransfer(
			$comment, 
			"download_evn.php"
		);
		$this->callerString .= $response;
		return $response;
	} 

	function getCallerListArray(){
		$responselines = explode("\n", $this->callerString);
		
		foreach($responselines as $line){
			$details = split(";",$line);
			if (count($details) == 6 && $details[0] != "Datum"){
				$this->callerList[] = $details;
			}
			else{
				print "Line '$line' was skipped because it doesn't represent the expected call format.\n";
			}
		}
		return $this->callerList;
	}	
}


?>