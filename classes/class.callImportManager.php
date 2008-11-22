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

define( 'PATH_TO_YAPHOBIA_CIM', dirname(__FILE__) . '/../' ); 

require_once( PATH_TO_YAPHOBIA_CIM. "interfaces/interface.billingProvider.php");
require_once( PATH_TO_YAPHOBIA_CIM. "classes/class.curllib.php");
require_once( PATH_TO_YAPHOBIA_CIM. "classes/class.billingProviderPrototype.php");
require_once( PATH_TO_YAPHOBIA_CIM. "billing_provider/dusnet.php");
require_once( PATH_TO_YAPHOBIA_CIM. "billing_provider/sipgate.php");
require_once( PATH_TO_YAPHOBIA_CIM. "protocol_provider/fritzbox.php");

class callImportManager{
	
	protected
		$dbh,
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
		$this->updateProviderCredit($credit, $p->getProviderName());
		if ($csv_file_flag) $p->createCsvFile();
		return $calllist;
	}
	
	/*
	 * write credit value of a provider to database table provider_details
	 * 
	 */
	private function updateProviderCredit($credit, $name){
		//store credit in database
		$query = 
			'UPDATE provider_details SET'.
			' current_credit = '. floatval($credit) . 
			', current_credit_timestamp = NOW()'.
			' WHERE provider_name = \'' . mysql_real_escape_string($name) . '\'' ;
		$this->tr->addToTrace(3, "updateProviderCredit: " . $query);
		mysql_query($query, $this->dbh);
		if (mysql_errno() != 0){
			print mysql_error();
			die();	
		}		
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
				'providerid' => 	$providerid,
				'number' => 		$call[1],
				'date' => 			$call[0],
				'duration' => 		$duration,
				'rate_description'=>$call[2],
				'billed_cost' =>	$call[5]
			));
		}
		//searches through database to see if there are new call rates to add to the list
		$this->checkForNewRateTypes();
	}
	
	/*
	 * getFritzBoxCallerList
	 */
	public function getFritzBoxCallerList(){
		
		define('FRITZBOX_CALL_CALLTYPE', 		0);
		define('FRITZBOX_CALL_DATE', 			1);
		define('FRITZBOX_CALL_IDENTITY', 		2);
		define('FRITZBOX_CALL_PHONENUMBER', 	3);
		define('FRITZBOX_CALL_USED_PHONE',	 	4);
		define('FRITZBOX_CALL_PROVIDER_ID', 	5);
		define('FRITZBOX_CALL_EST_DURATION', 	6);
				
		$fb = new fritzBoxRemote($this->tr);
		//$fb->loadCallerListsFromDir( $path . "/fritzbox_alte_anruflisten");
		$fb->logon( FRITZBOX_PASSWORD ); 
		$fb->loadCallerListFromBox();
		$fb->logout(); //dummy
		$calllist = $fb->getCallerListArray();
		foreach ($calllist as $call){
			$date = mysql_real_escape_string(
				'20' . 
				substr($call[FRITZBOX_CALL_DATE],6,2) .'-'. 
				substr($call[FRITZBOX_CALL_DATE],3,2) .'-'. 
				substr($call[FRITZBOX_CALL_DATE],0,2) . ' ' . 
				substr($call[FRITZBOX_CALL_DATE],9,5) . ':00',
				$this->dbh
			);
			list($hours, $minutes) = explode(":",$call[FRITZBOX_CALL_EST_DURATION]);
			$duration = mysql_real_escape_string( intval($minutes) + intval($hours) * 60, $this->dbh);
			//fix typo in number
			//FIXME: this is not relevant for other users, only for me!!!
			if ($call[FRITZBOX_CALL_PROVIDER_ID] == FRITZBOX_PROTOCOL_SIPGATE_ID_WRONG){
				$call[FRITZBOX_CALL_PROVIDER_ID] = FRITZBOX_PROTOCOL_SIPGATE_ID_CORRECT;
			}
			
			if ($call[FRITZBOX_CALL_PROVIDER_ID] == FRITZBOX_PROTOCOL_SIPGATE_ID_CORRECT){
				$providerid = abs(intval(SIPGATE_PROVIDER_ID));
			}
			elseif ($call[FRITZBOX_CALL_PROVIDER_ID] == FRITZBOX_PROTOCOL_DUSNET_ID ){
				$providerid = abs(intval(DUSNET_PROVIDER_ID));
			}
			else{
				$providerid = abs(intval(FLATRATE_PROVIDER_ID));
			}
			
			//security: prevent the possiblity of sql injections
			foreach ($call as $key=>$value){
				$call[$key] = mysql_real_escape_string( $value, $this->dbh);
			}
	
			$insert_array = array(
				'date'				=> $date, 
				'identity'			=> $call[FRITZBOX_CALL_IDENTITY], 
				'phonenumber'		=> $call[FRITZBOX_CALL_PHONENUMBER], 
				'calltype'			=> $call[FRITZBOX_CALL_CALLTYPE], 
				'usedphone' 		=> $call[FRITZBOX_CALL_USED_PHONE], 
				'providerstring' 	=> $call[FRITZBOX_CALL_PROVIDER_ID], 
				'provider_id' 		=> $providerid, 
				'estimated_duration'=> $duration
			);
			
			$this->tr->addToTrace( 5, print_r($insert_array, true));
			$this->insertMonitoredCall( $insert_array );
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
	private function insertMonitoredCall( $call_array ){
		$col_list = "";
		$value_list = "";		
		foreach ($call_array as $key=>$value){
			//omit identity field for table callprotocol,
			//identity is stored in user_contacts
			if ($key != 'identity'){
				$col_list .= $key . ', ';
				$value_list .= "'$value', ";
			}		
		}
		$col_list = substr($col_list, 0, strlen($col_list) - 2);
		$value_list = substr($value_list, 0, strlen($value_list) - 2);		
		
		$query = "INSERT INTO callprotocol ($col_list)".
			 	" VALUES (" . $value_list . ")";
		$this->tr->addToTrace( 3,"Checking presence of call: $value_list");
		
		$result = mysql_query($query,$this->dbh);
		if (!$result) {
			if (mysql_errno() == 1062){
				$this->tr->addToTrace( 4,"Duplicate call was skipped! Is already in database.");
			}
			else
	    		$this->tr->addToTrace( 1,'Invalid query: ' . mysql_errno() . ") ". mysql_error()); 
		}
		else{
			$this->tr->addToTrace( 4,"Call added to database.");
		}
		
		//try to insert empty identity fields in table user_contacts
		$insert_query = 
			"INSERT INTO user_contacts (phonenumber, identity) VALUES ('".
				mysql_real_escape_string($call_array["phonenumber"])."', '".
				mysql_real_escape_string($call_array["identity"])."')";
    	$this->tr->addToTrace( 4, 'Trying to insert identity into user contacts: ' . $insert_query  );
		$insert_result = mysql_query($insert_query,$this->dbh);
		if (!$insert_result) {
    		$this->tr->addToTrace( 1, 'Error on insert: ' . mysql_errno() . ") ". mysql_error() );
    		if (mysql_errno() == 1062){
				//try to update empty identity fields in table user_contacts
				if ($call_array["identity"] != ""){
					$update_query = 
						"UPDATE user_contacts SET identity = '".mysql_real_escape_string($call_array["identity"])."' ".
						"WHERE phonenumber = '".mysql_real_escape_string($call_array["phonenumber"])."' AND identity = ''";
    				$this->tr->addToTrace( 4, 'Trying to update identity if empty in user contacts: ' . $update_query  );
					$update_result = mysql_query($update_query,$this->dbh);
					if (!$update_result){
			    		$this->tr->addToTrace( 1, 'Error on update attempt: ' . mysql_errno() . ") ". mysql_error() );
					}
					elseif (mysql_affected_rows() == 1){
			    		$this->tr->addToTrace( 4, 'Update of user_contacts successful: ' . $call_array["identity"] );
					}
					elseif (mysql_affected_rows() != 1){
			    		$this->tr->addToTrace( 4, 'Update attempt of user_contacts useless: ' . $call_array["identity"] );
					}
				}
    		}
		}
		else{
    		$this->tr->addToTrace( 4, 'Insert was successful: ' . $insert_result  );
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
					//avoid quotes for float type!
					if ( $key != 'billed_cost')
						$query .= $key . " = '" . $value . "' AND ";
					else 
						$query .= $key . " = " . $value . " AND ";
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
	 * tries to buffer a call from a billing provider that couldn't be matched to an existing call from the protocol
	 * store it in db table unmatched_call
	 * if the call already is in the database table, it will not be added another time
	 */
	private function insertUnmatchedCall( $values ){
		$query = "INSERT INTO unmatched_calls (provider_id, date, phonenumber, billed_duration, billed_cost, rate_type)".
				" VALUES (" . $values . ")";
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

		//provider_id, date, phonenumber, billed_duration, billed_cost, rate_type
		$unmatchedCallString = 
			"'" . 
			$x['providerid'] . "','" . 
			$x['date'] . "','" .
			$x['number'] . "','" .
			$x['duration'] . "','" .
			$x['billed_cost'] . "','" .
			$x['rate_description'] . "'"; 
				
		$update= 
			"billed = '1', ".
			"dateoffset = TIMESTAMPDIFF(SECOND, date,'".$x['date']."'), ".
			"rate_type = '".$x['rate_description']."', ".
			"rate_type_id = '0', ".
			"billed_duration = '".$x['duration']."', ".
			"billed_cost = '". $x['billed_cost'] ."'";
				
		$whereStart = "WHERE calltype='3' AND provider_id = '".$x['providerid']."' AND ";
		$matches = 0;
		$first_try = true;
		while ($first_try == true || ($matches > 1 && $tolerance_span_call_begin >= 1)){
			if ($first_try == false){
				$tolerance_span_call_begin = abs($tolerance_span_call_begin/2);
				$this->tr->addToTrace( 2, "Trying to sovle conflict with $matches matches by lowering tolerance value for call begin to $tolerance_span_call_begin"); 			
			}
			else
				$first_try = false;
			$where = 
				"phonenumber = '".$x['number']."' AND ".
				"ABS(TIMESTAMPDIFF(SECOND, date,'".$x['date']."')) < $tolerance_span_call_begin AND ".
				"ABS( estimated_duration *60 - ".$x['duration'].") < $tolerance_span_duration ";
			
			$query = "SELECT * FROM callprotocol $whereStart $where"; 
			//print "Query: $query\n";
			$result = mysql_query( $query, $this->dbh );
			$matches = mysql_num_rows($result);
		}
		if ($matches == 1 && $tolerance_span_call_begin != TOLERANCE_CALL_BEGIN){
			$this->tr->addToTrace( 2, "solved conflict by lowering tolerance value for call begin "); 			
		}
		if ($matches > 1){
			$tr_buffer =  
				"Not able to match following call in protocol:" . 
				print_r($x, true).
				"See possible matches here:\n";
			while ($row = mysql_fetch_assoc($result)) {
			    $tr_buffer .= print_r($row, true);
			}
			$this->tr->addToTrace( 2, $tr_buffer); 
			$this->insertUnmatchedCall ($unmatchedCallString);
		}
		elseif ($matches == 0){
			$this->tr->addToTrace( 2, "No match in call protocol for following call:\n" . print_r ($x, true));
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
				"billed_duration = estimated_duration*60 ".
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