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

require_once( PATH_TO_YAPHOBIA_CIM. "classes/class.curllib.php");
require_once( PATH_TO_YAPHOBIA_CIM. "billing_provider/dusnet.php");
require_once( PATH_TO_YAPHOBIA_CIM. "billing_provider/sipgate.php");
require_once( PATH_TO_YAPHOBIA_CIM. "protocol_provider/fritzbox.php");

class callImportManager{
	
	var $db,
		$dbh,
		$path,
		$dusnet_trace,
		$sipgate_trace;

	/*
	 * constructor
	 */
	function __construct($db){
		
		$this->db = $db;
		$this->dbh = $this->db->getDBHandle();
		$this->path = YAPHOBIA_DATA_EXPORT_DIR;
		$this->dusnet_trace = "";
		$this->sipgate_trace = "";
		
	}
	
	/*
	 * getDusNetCalls
	 */
	public function getDusNetCalls( $providerId, $sipAccount, $username, $password){
		
		$dusnetCon = new dusnetRemote();
		$dusnetCon->logon($username , $password);
		$dusnetCon->collectCallsOfCurrentMonth($sipAccount);
		$dusnetCon->logout();
		$calllist = $dusnetCon->getCallerListArray();

		//save data as a csv file for backup or testing purposes
		if (DUSNET_SAVE_CSV_DATA_TO_WORKDIR){
			$csvdata = $dusnetCon->getCsvData();
			file_put_contents($this->path."/dusnet_csv_verbindungen.csv", $csvdata);
		}
		
		//put calls into db if they are not in there
		$trace = "";
		foreach ($calllist as $call){
			$trace .= $this->db->checkCallUniqueness( array(
				'providerid'      => $providerId,
				'number'          => $call["Nummer"],
				'date'            => $call["Datum"],
				'duration'        => $call["DauerInSekunden"],
				'rate_description'=> $call["Tarif"],
				'billed_cost'     => $call["Kosten"]
			));
		}
		
		$this->dusnet_trace = $dusnetCon->getTraceString() . $trace . "\nend.\n";
	}
	
	public function getDusNetTrace(){
		return $this->dusnet_trace;
	}

	/*
	 * returns the calls in an array, is also able to save the calls as a CSV file to the harddisk
	 */
	public function getSipgateCallsOfCurrentMonth( $username, $password ){
		$sg = new sipgateRemote();
		$sg->logon($username, $password);
		$csvdata = $sg->getEvnOfMonth( date('Y', time())."-".date('m', time()) );
		$sg->logout();
		if (SIPGATE_SAVE_CSV_DATA_TO_WORKDIR){
			file_put_contents($this->path."/sipgate_csv_verbindungen_$month.csv", $csvdata);
		}
		
		$this->sipgate_trace .= $sg->getTraceString() . "\nend.\n";
		return $sg->getCallerListArray();
	}
	
	public function getSipgateTrace(){
		return $this->sipgate_trace;
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
				$this->sipgate_trace .=  "ERROR: Strange duration $call[3]";
				print $this->sipgate_trace; //exception from the rule
				die();
			}
			
			$this->sipgate_trace .= $this->db->checkCallUniqueness( array(
				'providerid' => $providerid,
				'number' => $call[1],
				'date' => $call[0],
				'duration' => $duration,
				'rate_description'=> $call[2],
				'billed_cost' => $call[5]
			));
		}
		
		$this->sipgate_trace .= "Done.\n";
	}
	
	/*
	 * getFritzBoxCallerList
	 */
	public function getFritzBoxCallerList(){
		$fb = new fritzBoxRemote();
		//$fb->loadCallerListsFromDir( $path . "/fritzbox_alte_anruflisten");
		$fb->logon( FRITZBOX_PASSWORD ); 
		$fb->loadCallerListFromBox();
		$fb->logout(); //dummy
		$trace = "";
		$calllist = $fb->getCallerListArray();
		foreach ($calllist as $call){
			//date, identity, phonenumber, calltype, usedphone, providerstring, provider_id, estimated_duration
			$date = mysql_real_escape_string('20' . substr($call[1],6,2) .'-'. substr($call[1],3,2) .'-'. substr($call[1],0,2) . ' ' . substr($call[1],9,5) . ':00');
			list($hours, $minutes) = explode(":",$call[6]);
			$duration = mysql_real_escape_string( intval($minutes) + intval($hours) * 60);
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
				$call[$key] = mysql_real_escape_string( $value);
			}
			
			$insertstring = "'$date','$call[2]','$call[3]','$call[0]','$call[4]','$call[5]','$providerid','$duration'";
			//print "$insertstring\n";
			$trace .= $this->db->insertMonitoredCall( $insertstring );
		}
		
		//$csvdata = $fb->getCallerListString();
		//file_put_contents($this->path."/FRITZ_Box_Anrufliste.csv", $csvdata);
		return $fb->getTraceString() . $trace;
	}
	
	/*
	 * checkForNewRateTypes
	 */
	public function checkForNewRateTypes(){
		print "==================================================\n";		
		print "check for new rate types in fritz!box call protocol\n";
		print "==================================================\n";
		$result = mysql_query("SELECT provider_id, rate_type FROM callprotocol GROUP BY (rate_type)",$this->dbh);
		while ($row = mysql_fetch_assoc($result)) {
			$result2 = mysql_query("INSERT INTO provider_rate_types (provider_id, rate_type) VALUES ('". $row["provider_id"] ."','". $row["rate_type"] ."')", $this->dbh);
			if (!$result2) {
				if (mysql_errno() == 1062){
					print "Duplicate rate was skipped! Is already in database.\n";
				}
				else
			    	print 'Invalid query: ' . mysql_error() . "\n";
			}
			else{
				print "Rate added to database.\n";
			}
		}
	}
	

	/*
	 * needed for providers with flatrate options (kabelbw) who don't send a bill or evn for free calls
	 * we simulate here that the call has been billed by a billing_provider
	 * todo: at the moment this function is not aware of non-festnetz-phonenumbers which would not belong to the flatrate
	 */
	public function markFlateRateCallsAsBilled($provider_id, $rate_type){
		$trace  = "==================================================\n";
		$trace .= "Marking FlateRate calls as billed\n";
		$trace .= "==================================================\n";
		$query="UPDATE callprotocol SET ".
				"billed_cost = 0, ".
				"billed = 2, ". // 2 means that it was done without an evn
				"dateoffset=0, ".
				"rate_type ='".mysql_real_escape_string($rate_type)."', ".
				"billed_duration = CEIL(estimated_duration/60) ".
			"WHERE ".
				"provider_id = ".intval($provider_id)." AND ".
				"ISNULL(rate_type) AND ".
				"ISNULL(billed) AND ".
				"ISNULL(billed_duration) AND ".
				"ISNULL(billed_cost) AND ".
				"ISNULL(dateoffset)";
		//$trace .= $query . "\n";
		$result = mysql_query($query,$this->dbh);
		if (!$result) {
    		$trace .= 'Invalid query: ' . mysql_errno() . ") ". mysql_error() . "\n";
		}
		else{
			$trace .= "Unbilled flatrate calls have been auto-billed.\n";
		}
		return $trace;
	}
}

?>