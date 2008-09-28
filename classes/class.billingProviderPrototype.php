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

class billingProviderPrototype extends curllib implements billingProvider {
	
	var $providerName,
		$callerList,
		$callerString,
		$requestDescriptions,
		$csvFilenameSuffix;
		
	function __construct($name, $baseURL){
		parent::__construct();
		$this->providerName = $name;
		$this->callerList = array();	
		$this->callerString = "";	
		$this->setBaseUrl($baseURL);
		$this->requestDescriptions = null;
		$this->csvFilenameSuffix = "";
		
	}

	protected function setProviderName( $name ){
		$this->providerName = $name;
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