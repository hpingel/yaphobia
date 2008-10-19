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
 */

final class sipgateRemote extends billingProviderPrototype{

	/*
	 * constructor
	 */
	function __construct( $traceObj ){
		parent::__construct("sipgate", "https://secure.sipgate.de/user/", $traceObj);
		$this->handleSessionCookies();
		$this->setCreditRegex("/<td.*?>(.*?).&euro;<\/td>/");
		$this->describeStandardRequests(
			array(
				FR_TASK_LOGON => array(
					FR_TYPE     => FR_TYPE_POST,
					FR_PATH     => "index.php",
					FR_POSTVARS => "uname=[[USER]]&passw=[[PASSWORD]]&okey.x=7&okey.y=8&lasturi=%2Fuser%2Findex.php&jsh=1"
				),
				FR_TASK_LOGOUT => array(
					FR_TYPE     => FR_TYPE_GET,
					FR_PATH     => "logout.php"
				),
				FR_TASK_GETEVNOFMONTH => array(
					array(
						FR_COMMENT => "trigger evn for [[YEAR]]-[[MONTH]]",
						FR_TYPE    => FR_TYPE_GET,
						FR_PATH    => "konto_einzel.php?show=all&timeperiod=simple&timeperiod_simpletimeperiod=[[YEAR]]-[[MONTH]]",
						FR_IGNORE  => true //ignore content of request
					),
					//it seems that leaving $month empty returns a complete list of
					//the complete call history TODO: Check this. 
					array(
						FR_COMMENT => "download csv for month [[YEAR]]-[[MONTH]]",
						FR_TYPE    => FR_TYPE_BINARY,
						FR_PATH    => "download_evn.php"
					)
				),
				FR_TASK_GETCREDIT => array(
					FR_TYPE     => FR_TYPE_GET,
					FR_PATH     => "start.php"
				)
			)
		);
	}
	
	/*
	 * converts the callerString to an array 
	 * where each item represents a single call and returns the array
	 */
	function getCallerListArray(){
		$responselines = explode("\n", $this->callerString);
		foreach($responselines as $line){
			$details = split(";",$line);
			if (count($details) == 6 && $details[0] != "Datum"){
				$this->callerList[] = $details;
			}
			else{
				$this->trace .= $this->providerName . ": Line '$line' was skipped because it doesn't represent the expected call format.\n";
			}
		}
		return $this->callerList;
	}
	
	public function getCsvData(){
		return $this->getCallerString();
	}
}


?>