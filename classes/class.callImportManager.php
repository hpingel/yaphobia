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

define( 'PATH_TO_YAPHOBIA_CIM', str_replace("classes","",dirname(__FILE__)) ); 

require_once( PATH_TO_YAPHOBIA_CIM. "interfaces/interface.billingProvider.php");
require_once( PATH_TO_YAPHOBIA_CIM. "classes/class.curllib.php");
require_once( PATH_TO_YAPHOBIA_CIM. "classes/class.billingProviderPrototype.php");
require_once( PATH_TO_YAPHOBIA_CIM. "billing_provider/dusnet.php");
require_once( PATH_TO_YAPHOBIA_CIM. "billing_provider/sipgate.php");
require_once( PATH_TO_YAPHOBIA_CIM. "protocol_provider/fritzbox.php");

class callImportManager{
	
	var $dbh,
		$tr,
		$currentMonth,
		$currentYear;
		
	/*
	 * constructor
	 */
	function __construct($dbh, $traceObj){
		$this->tr = $traceObj;
		$this->dbh = $dbh;
		$this->currentMonth = date('m', time());
		$this->currentYear = date('Y', time());
	}
	
	/*
	 * sets the year to an arbitrary value
	 */
	public function setYear( $year ){
		$this->currentYear = intval($year);
		//FIXME: add range check
	}

	/*
	 * sets the month to an arbitrary value
	 */
	public function setMonth( $month ){
		$this->currentMonth = intval($month);
		//FIXME: add range check
	}
	
	/*
	 * getProviderCalls
	 */
	private function getBillingProviderCalls( $p, $username, $password, $csv_file_flag){
		
		$p->logon($username , $password);
		$p->getEvnOfMonth( $this->currentYear, $this->currentMonth );
		$p->determineCredit();
		$p->logout();
		$calllist = $p->getCallerListArray();
		$credit = $p->getCredit();
		$this->tr->addToTrace(3, "Current credit: " . $credit);
		if ($csv_file_flag) $p->createCsvFile();
		return $calllist;
	}

	/*
	 * getDusNetCalls
	 */
	public function getDusNetCalls( $sipAccount, $username, $password){
		$dn = new dusnetRemote($sipAccount, $this->tr);
		return $this->getBillingProviderCalls($dn, $username, $password, DUSNET_SAVE_CSV_DATA_TO_WORKDIR);
	}

	/*
	 * put dusnet calls into db if they are not in there
	 */
	public function putDusNetCallArrayIntoDB($calllist, $providerId){		
		foreach ($calllist as $call){
			$result = $this->checkCallUniqueness( array(
				'providerid'      => $providerId,
				'number'          => $call["Nummer"],
				'date'            => $call["Datum"],
				'duration'        => $call["DauerInSekunden"],
				'rate_description'=> $call["Tarif"],
				'billed_cost'     => $call["Kosten"]
			));
		}
		//searches through database to see if there are new call rates to add to the list
		$this->checkForNewRateTypes();
	}
	
	/*
	 * returns the calls in an array, is also able to save the calls as a CSV file to the harddisk
	 */
	public function getSipgateCallsOfCurrentMonth( $username, $password ){
		$sg = new sipgateRemote($this->tr);
		return $this->getBillingProviderCalls( $sg, $username, $password, SIPGATE_SAVE_CSV_DATA_TO_WORKDIR);
	}
	
	/*
	 * takes the array of calls and tries to match them to the call protocol calls
	 */
	public function putSipgateCallArrayIntoDB($calllist, $providerid){
		//Datum;Nummer;Tarif;Dauer;;Kosten
		foreach ($calllist as $call){
			$duration = 0;
			$dur_fragments = explode(":",$call[3]);
			if (count($dur_fragments) == 1){
				$duration = intval($dur_fragments[0]); //seconds 
			}
			elseif (count($dur_fragments) == 2){
				$duration = intval($dur_fragments[0]) * 60 + intval($dur_fragments[1]); 
			}
			elseif (count($dur_fragments) == 3){
				$duration = intval($dur_fragments[0]) * 3600 + intval($dur_fragments[1]) * 60 + intval($dur_fragments[2]); 
			}
			else{
				$this->tr->addToTrace(0, "ERROR: Strange duration $call[3]");
			}
			
			$result = $this->checkCallUniqueness( array(
				'providerid' => $providerid,
				'number' => $call[1],
				'date' => $call[0],
				'duration' => $duration,
				'rate_description'=> $call[2],
				'billed_cost' => $call[5]
			));
		}
		//searches through database to see if there are new call rates to add to the list
		$this->checkForNewRateTypes();
	}
	
	/*
	 * getFritzBoxCallerList
	 */
	public function getFritzBoxCallerList(){
		$fb = new fritzBoxRemote($this->tr);
		//$fb->loadCallerListsFromDir( $path . "/fritzbox_alte_anruflisten");
		$fb->logon( FRITZBOX_PASSWORD ); 
		$fb->loadCallerListFromBox();
		$fb->logout(); //dummy
		$calllist = $fb->getCallerListArray();
		foreach ($calllist as $call){
			//date, identity, phonenumber, calltype, usedphone, providerstring, provider_id, estimated_duration
			$date = mysql_real_escape_string('20' . substr($call[1],6,2) .'-'. substr($call[1],3,2) .'-'. substr($call[1],0,2) . ' ' . substr($call[1],9,5) . ':00', $this->dbh);
			list($hours, $minutes) = explode(":",$call[6]);
			$duration = mysql_real_escape_string( intval($minutes) + intval($hours) * 60, $this->dbh);
			//fix typo in number
			if ($call[5] == FRITZBOX_PROTOCOL_SIPGATE_ID_WRONG){
				$call[5] = FRITZBOX_PROTOCOL_SIPGATE_ID_CORRECT;
			}
			
			if ($call[5] == FRITZBOX_PROTOCOL_SIPGATE_ID_CORRECT){
				$providerid = abs(intval(SIPGATE_PROVIDER_ID));
			}
			elseif ($call[5] == FRITZBOX_PROTOCOL_DUSNET_ID ){
				$providerid = abs(intval(DUSNET_PROVIDER_ID));
			}
			else{
				$providerid = abs(intval(FLATRATE_PROVIDER_ID));
			}
			
			//security: prevent the possiblity of sql injections
			foreach ($call as $key=>$value){
				$call[$key] = mysql_real_escape_string( $value, $this->dbh);
			}
			
			$insertstring = "'$date','$call[2]','$call[3]','$call[0]','$call[4]','$call[5]','$providerid','$duration'";
			$this->tr->addToTrace( 5, $insertstring);
			$this->insertMonitoredCall( $insertstring );
		}
		
		if (FRITZBOX_SAVE_CALLER_PROTOCOL_TO_EXPORT_DIR){
			$fb->createFileInExportDir( YAPHOBIA_DATA_EXPORT_DIR."FRITZ_Box_Anrufliste.csv", $fb->getCallerListString());
		}

		if (AUTOBILL_REMAINING_FLATRATE_CALLS)
			$this->markFlateRateCallsAsBilled('0', 'Flatrate Festnetz');
		
		$this->recheckUnmatchedCalls();
	}

	
	/*
	 * tries to insert a call from a call protocol (for example from Fritz!Box) to the database
	 * if the call already is in the database table, it will not be added another time
	 */
	private function insertMonitoredCall( $values ){
		$query = "INSERT INTO callprotocol (date, identity, phonenumber, calltype, usedphone, providerstring, provider_id, estimated_duration)".
		$query .= " VALUES (" . $values . ")";
		$this->tr->addToTrace( 3,"Checking presence of call: $values");
		
		$result = mysql_query($query,$this->dbh);
		if (!$result) {
			if (mysql_errno() == 1062){
				$this->tr->addToTrace( 3,"Duplicate call was skipped! Is already in database.");
			}
			else
	    		$this->tr->addToTrace( 1,'Invalid query: ' . mysql_errno() . ") ". mysql_error()); 
		}
		else{
			$this->tr->addToTrace( 3,"Call added to database.");
		}
	}
	
	/*
	 * check all entries of database table unmatched_calls if we can now find matching entries
	 * in table call_protocoll
	 */
	public function recheckUnmatchedCalls(){
		$query = "SELECT * FROM unmatched_calls";
		$result = mysql_query($query, $this->dbh);
		while ($row = mysql_fetch_assoc($result)) {
			$call_array = array(
				'providerid' => $row['provider_id'],
				'number' => $row['phonenumber'],
				'date' => $row['date'],
				'duration' => $row['billed_duration'],
				'rate_description'=> $row['rate_type'],
				'billed_cost' => $row['billed_cost']
			);
			$success = $this->checkCallUniqueness( $call_array );
			//$this->tr->addToTrace(3, "value of success is: '$success' " . ($success == true));
			if ($success == true){
				//delete row
				$query = "DELETE FROM unmatched_calls WHERE ";
				foreach ($row as $key => $value){
					$query .= $key . " = '" . $value . "' AND "; 
				}
				$query = substr($query, 0, strlen($query) - 4); 
				$this->tr->addToTrace(4, $query);
				$delete_result = mysql_query($query, $this->dbh);
				if (!$delete_result) {
			    	$this->tr->addToTrace( 1,'Invalid query: ' . mysql_error() );
				}
				else{
					$this->tr->addToTrace( 3,'Row from unmatched_calls was successfully deleted.');
				}
				
			}
			else{
				$this->tr->addToTrace( 3,'Matching call was not found. Maybe here we have a problem with billed status!!!');
			}
		}
	}
	
	/*
	 * tries to buffer a call from a call provider that couldn't be matched to an existing call from the protocol
	 * store it in db table unmatched_call
	 * if the call already is in the database table, it will not be added another time
	 */
	private function insertUnmatchedCall( $values ){
		$query = "INSERT INTO unmatched_calls (provider_id, date, phonenumber, billed_duration, billed_cost, rate_type)".
		$query .= " VALUES (" . $values . ")";
		$this->tr->addToTrace(4, "Checking presence of unmatched call: $values");
					
		$result = mysql_query($query,$this->dbh);
		if (!$result) {
			if (mysql_errno() == 1062){
				$this->tr->addToTrace(4, "Duplicate unmatched call was skipped! Is already in database.");
				$success = true;
			}
			else
	    		$this->tr->addToTrace(1, 'Invalid query: ' . mysql_errno() . ") ". mysql_error());
				$success = false;
		}
		else{
			$this->tr->addToTrace(4,"Unmatched call added to database.");
			$success = true;
		}
		return $success;
	}
		
	/*
	 * check if we can find a matching call from the call protocol for a call from a phone bill
	 * if this is possible (single call is found) we update the call protocol entry with the 
	 * billing information 
	 */
	private function checkCallUniqueness($x){
		$success = false;
		$tolerance_span_call_begin = TOLERANCE_CALL_BEGIN; //in seconds
		$tolerance_span_duration = TOLERANCE_CALL_DURATION; //in seconds
		
		//security: prevent the possiblity of sql injections
		foreach ($x as $key=>$value){
			$x[$key] = mysql_real_escape_string( $value, $this->dbh);
		}
		
		//reformat cost value
		$x['billed_cost'] = floatval(str_replace(',','.',$x['billed_cost']));
		
		$update= 
			"billed = '1', ".
			"dateoffset = TIMESTAMPDIFF(SECOND, date,'".$x['date']."'), ".
			"rate_type = '".$x['rate_description']."', ".
			"rate_type_id = '0', ".
			"billed_duration = '".$x['duration']."', ".
			"billed_cost = '". $x['billed_cost'] ."'";
		
		$where = 
			"phonenumber = '".$x['number']."' AND ".
			"ABS(TIMESTAMPDIFF(SECOND, date,'".$x['date']."')) < $tolerance_span_call_begin AND ".
			"ABS( estimated_duration *60 - ".$x['duration'].") < $tolerance_span_duration ";
		
		$whereStart = "WHERE calltype='3' AND provider_id = '".$x['providerid']."' AND "; 
		$query = "SELECT * FROM callprotocol $whereStart $where"; 
		//print "Query: $query\n";
		$result = mysql_query( $query, $this->dbh );
		$matches = mysql_num_rows($result);
		if ($matches > 1){
			$tr_buffer =  
				"Not able to match following call in protocol:" . 
				print_r($x, true).
				"See possible matches here:\n";
			while ($row = mysql_fetch_assoc($result)) {
			    $tr_buffer .= print_r($row, true);
			}
			$this->tr->addToTrace( 2, $tr_buffer); 
		}
		elseif ($matches == 0){
			$this->tr->addToTrace( 2, "No match in call protocol for following call:\n" . print_r ($x, true));
			//provider_id, date, phonenumber, billed_duration, billed_cost, rate_type
			$unmatchedCallString = 
				"'" . 
				$x['providerid'] . "','" . 
				$x['date'] . "','" .
				$x['number'] . "','" .
				$x['duration'] . "','" .
				$x['billed_cost'] . "','" .
				$x['rate_description'] . "'"; 
			$this->insertUnmatchedCall ($unmatchedCallString);
		}
		else{
			$row = mysql_fetch_assoc($result);
			$success = true;
			if ($row['billed'] != '1'){
				$this->tr->addToTrace( 3, "Call (". $x["date"] . " ". $x["number"] . "): Found, updating call info.");
				$query = "UPDATE callprotocol SET $update $whereStart $where";
				$this->tr->addToTrace( 5, "Query: $query\n");
				$result = mysql_query( $query, $this->dbh );
			}
			else{
				$this->tr->addToTrace( 3, "Call (". $x["date"] . " ". $x["number"] . "): Already billed. Skipped.");
			}
		}
		return $success;
	}
	
	
	/*
	 * checkForNewRateTypes
	 */
	public function checkForNewRateTypes(){
		$this->tr->addToTrace( 3,"check for new rate types in fritz!box call protocol");
		
		$result = mysql_query("SELECT provider_id, rate_type FROM callprotocol GROUP BY (rate_type)",$this->dbh);
		while ($row = mysql_fetch_assoc($result)) {
			$result2 = mysql_query("INSERT INTO provider_rate_types (provider_id, rate_type) VALUES ('". $row["provider_id"] ."','". $row["rate_type"] ."')", $this->dbh);
			if (!$result2) {
				if (mysql_errno() == 1062){
					$this->tr->addToTrace( 3,"Duplicate rate was skipped! Is already in database.");
				}
				else
			    	$this->tr->addToTrace( 1,'Invalid query: ' . mysql_error() );
			}
			else{
				$this->tr->addToTrace( 3, "Rate added to database.");
			}
		}
	}
	

	/*
	 * needed for providers with flatrate options (kabelbw) who don't send a bill or evn for free calls
	 * we simulate here that the call has been billed by a billing_provider
	 * todo: at the moment this function is not aware of non-festnetz-phonenumbers which would not belong to the flatrate
	 */
	public function markFlateRateCallsAsBilled($provider_id, $rate_type){
		
		$this->tr->addToTrace( 3,"Marking FlateRate calls as billed");
		
		$query="UPDATE callprotocol SET ".
				"billed_cost = 0, ".
				"billed = 2, ". // 2 means that it was done without an evn
				"dateoffset=0, ".
				"rate_type ='".mysql_real_escape_string($rate_type, $this->dbh)."', ".
				"billed_duration = CEIL(estimated_duration/60) ".
			"WHERE ".
				"provider_id = ".intval($provider_id)." AND ".
				"ISNULL(rate_type) AND ".
				"ISNULL(billed) AND ".
				"ISNULL(billed_duration) AND ".
				"ISNULL(billed_cost) AND ".
				"ISNULL(dateoffset)";
		$this->tr->addToTrace( 5,$query);
		$result = mysql_query($query,$this->dbh);
		if (!$result) {
    		$this->tr->addToTrace( 1, 'Invalid query: ' . mysql_errno() . ") ". mysql_error() );
		}
		else{
			$this->tr->addToTrace( 3, "Unbilled flatrate calls have been auto-billed.");
		}
	}
}

?>