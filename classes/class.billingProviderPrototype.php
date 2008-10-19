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
 * billingProviderPrototype
 * 
 * 
 */

//constants with semantic information 
define( 'FR_TASK_LOGON', 'FR_TASK_LOGON' ); 
define( 'FR_TASK_LOGOUT', 'FR_TASK_LOGOUT' );
define( 'FR_TASK_GETEVNOFMONTH', 'FR_TASK_GETEVNOFMONTH' );
define( 'FR_TASK_GETCREDIT', 'FR_TASK_GETCREDIT' );

class billingProviderPrototype extends curllib implements billingProvider {
	
	var $providerName,
		$callerList,
		$callerString,
		$requestDescriptions,
		$csvFilenameSuffix,
		$creditRegex,
		$tr,
		$currentCredit;
		
	function __construct($name, $baseURL, $traceObj){
		$this->tr = $traceObj;
		parent::__construct($traceObj);
		$this->providerName = $name;
		$this->callerList = array();	
		$this->callerString = "";	
		$this->setBaseUrl($baseURL);
		$this->requestDescriptions = null;
		$this->csvFilenameSuffix = "";
		$this->creditRegex = "";
		$this->currentCredit = "[undetermined]";
		
	}

	//obsolete, see constructor
	protected function setProviderName( $name ){
		$this->providerName = $name;
	}
	
	protected function setCreditRegex( $regex ){
		$this->creditRegex = $regex;
	}
	
	
	protected function handleSessionCookies(){
		parent::enableCookieJar( YAPHOBIA_COOKIEJAR_DIR . 'cookies_'.$this->providerName.'.txt' );
		//FIXME: For security reasons, we should have a salt in the cookie jar filename!!!
	}
	
	protected function describeStandardRequests( $array ){
		$this->requestDescriptions = $array;
	}
	
	public function logon($user, $password){
		$this->executeFlexRequest( 
			"Logon to " . $this->providerName, 
			$this->requestDescriptions[ FR_TASK_LOGON ], 
			array('[[USER]]', '[[PASSWORD]]'), 
			array( $user, $password)
		);
	}
	
	public function logout(){
		$this->executeFlexRequest( "Log out from " . $this->providerName, $this->requestDescriptions[ FR_TASK_LOGOUT ], null, null );
	}

	/*
	 * downloads the csv data of the itemised bill for a single month
	 * adds the downloaded content to the callerString
	 * @month
	 */
	public function getEvnOfMonth( $year, $month ){
		$this->csvFilenameSuffix = "$year-$month";
		$response = $this->executeFlexRequest(
			"get ".$this->providerName." evn for month $year-$month", 
			$this->requestDescriptions[ FR_TASK_GETEVNOFMONTH ],
			array('[[MONTH]]', '[[YEAR]]'),
			array($month, $year)
		);
		$this->callerString .= $response;
	}

	public function determineCredit(){
		$this->tr->addToTrace(3, "trying to get credit info");
		if ($this->creditRegex != ""){
			$page = $this->executeFlexRequest(
				"get current credit info from ".$this->providerName."", 
				$this->requestDescriptions[ FR_TASK_GETCREDIT ],
				null,
				null
			);
			preg_match( $this->creditRegex, $page, $match); //FIXME use return value???
			if (count($match) == 2){
				$this->tr->addToTrace(3, "credit determination seems to be successful");
				$this->currentCredit = $match[1];
			}
			else{
				$this->tr->addToTrace(1, "credit determination unsuccessful, optimize regex.");
				$this->tr->addToTrace(1, print_r($match, true));
				$this->currentCredit = $match[1];
			}
		}
		else{
			$this->tr->addToTrace(1, "credit determination unsuccessful, set regex.");
			$this->currentCredit =  "[Could not determine credit: no regex set!]";
		}
		 
	}
	
	public function getCredit(){
		return $this->currentCredit;
	}
	
	public function getCallerListArray(){
		return array("[not implemented!]");
	}	

	public function getCallerString(){
		return $this->callerString;
	}

	public function getCsvData(){
		return "[not implemented!]";
	}
	
	public function createCsvFile(){
		$this->trace .= $this->createFileInExportDir(YAPHOBIA_DATA_EXPORT_DIR . $this->providerName . '_csv_connections_' . $this->csvFilenameSuffix. '.csv', $this->getCsvData());
	}
	
}


?>