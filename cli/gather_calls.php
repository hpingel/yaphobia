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


//check for settings file

define( 'PATH_TO_SETTINGS', str_replace("/cli","",dirname(__FILE__)) . '/config/settings.php' ); 
if (file_exists(PATH_TO_SETTINGS)){
	require_once(PATH_TO_SETTINGS);	
}
else{
	die('<p>ERROR: There is no configuration file <b>settings.php</b>!<br/>Please copy <b>settings.defaults.php</b> to <b>settings.php</b> and change the options within the file according to your needs.</p>');
}

require_once("../classes/class.curllib.php");
require_once("../classes/class.db_manager.php");
require_once("../billing_provider/dusnet.php");
require_once("../billing_provider/sipgate.php");
require_once("../protocol_provider/fritzbox.php");

$telrechnung = new gatherCallData();

class gatherCallData{
	
	var $db,
		$dbh,
		$path;

	/*
	 * constructor
	 */
	function __construct(){
		
		$this->db = new dbMan();
		$this->dbh = $this->db->getDBHandle();
		$this->path = YAPHOBIA_WORK_DIR;
		
		print "==================================================\n";
		print " Welcome to Yaphobia!\n";
		print " Yet another phone bill application...\n";
		print " You are using Yaphobia version ".YAPHOBIA_VERSION."\n";
		print "==================================================\n";
		
		$this->getFritzBoxCallerList();
		
		if (AUTOBILL_REMAINING_FLATRATE_CALLS)
			$this->markFlateRateCallsAsBilled('0', 'Flatrate Festnetz');
		
		if (DUSNET_ACTIVE) 
			$this->getDusNetCalls( DUSNET_PROVIDER_ID, DUSNET_SIPACCOUNT, DUSNET_USERNAME, DUSNET_PASSWORD );
		if (SIPGATE_ACTIVE) 
			$this->getSipgateCalls(SIPGATE_PROVIDER_ID, SIPGATE_USERNAME, SIPGATE_PASSWORD);

		//searches through database to see if there are new call rates to add to the list
		$this->checkForNewRateTypes();
			
		print "==================================================\n";
		print "= End of script                                  =\n";
		print "==================================================\n";
		
		$this->db = null;
	}
	
	/*
	 * getDusNetCalls
	 */
	function getDusNetCalls( $providerId, $sipAccount, $username, $password){
		
		$dusnetCon = new dusnetRemote();
		$dusnetCon->logon($username , $password);
		$dusnetCon->collectCallsOfCurrentMonth($sipAccount);
		$dusnetCon->logout();
		$calllist = $dusnetCon->getCallerListArray();
		
		//put calls into db if they are not in there
		foreach ($calllist as $call){
			$this->db->checkCallUniqueness( array(
				'providerid'      => $providerId,
				'number'          => $call["Nummer"],
				'date'            => $call["Datum"],
				'duration'        => $call["DauerInSekunden"],
				'rate_description'=> $call["Tarif"],
				'billed_cost'     => $call["Kosten"]
			));
		}
		
		//save data as a csv file for backup or testing purposes
		if (DUSNET_SAVE_CSV_DATA_TO_WORKDIR){
			$csvdata = $dusnetCon->getCsvData();
			file_put_contents($this->path."/dusnet_csv_verbindungen.csv", $csvdata);
		}
		//$dusnetCon = null;
		print "end.\n";
	}

	/*
	 * getSipgateCalls
	 */
	function getSipgateCalls( $providerid, $username, $password ){
		$sg = new sipgateRemote();
		$sg->logon($username, $password);
		$csvdata = $sg->getEvnOfMonth( date('Y', time())."-".date('m', time()) );
		$sg->logout();
		$calllist = $sg->getCallerListArray();
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
				print "ERROR: Strange duration $call[3]";
				die();
			}
			
			$this->db->checkCallUniqueness( array(
				'providerid' => $providerid,
				'number' => $call[1],
				'date' => $call[0],
				'duration' => $duration,
				'rate_description'=> $call[2],
				'billed_cost' => $call[5]
			));
		}
		
		if (SIPGATE_SAVE_CSV_DATA_TO_WORKDIR){
			file_put_contents($this->path."/sipgate_csv_verbindungen_$month.csv", $csvdata);
		}
		print "end.\n";
	}
	
	/*
	 * getFritzBoxCallerList
	 */
	function getFritzBoxCallerList(){
		$fb = new fritzBoxRemote();
		//$fb->loadCallerListsFromDir( $path . "/fritzbox_alte_anruflisten");
		$fb->logon( FRITZBOX_PASSWORD ); 
		$fb->loadCallerListFromBox();
		$fb->logout(); //dummy
		
		$csvdata = $fb->getCallerListString();
		$calllist = $fb->getCallerListArray();
		foreach ($calllist as $call){
			//date, identity, phonenumber, calltype, usedphone, providerstring, provider_id, estimated_duration
			$date = '20' . substr($call[1],6,2) .'-'. substr($call[1],3,2) .'-'. substr($call[1],0,2) . ' ' . substr($call[1],9,5) . ':00';
			list($hours, $minutes) = explode(":",$call[6]);
			$duration = intval($minutes) + intval($hours) * 60;
			//fix typo in number
			if ($call[5] == FRITZBOX_PROTOCOL_SIPGATE_ID_WRONG){
				$call[5] = FRITZBOX_PROTOCOL_SIPGATE_ID_CORRECT;
			}
			
			if ($call[5] == FRITZBOX_PROTOCOL_SIPGATE_ID_CORRECT){
				$providerid = SIPGATE_PROVIDER_ID; //sipgate
			}
			elseif ($call[5] == FRITZBOX_PROTOCOL_DUSNET_ID ){
				$providerid = DUSNET_PROVIDER_ID; //dusnet
			}
			else{
				$providerid = FLATRATE_PROVIDER_ID; //kabelbw
			}
			$insertstring = "'$date','$call[2]','$call[3]','$call[0]','$call[4]','$call[5]','$providerid','$duration'";
			//print "$insertstring\n";
			$this->db->insertMonitoredCall( $insertstring );
		}
		//print_r($calllist);
		file_put_contents($this->path."/FRITZ_Box_Anrufliste.csv", $csvdata);
	}
	
	/*
	 * checkForNewRateTypes
	 */
	function checkForNewRateTypes(){
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
	function markFlateRateCallsAsBilled($provider_id, $rate_type){
		print "==================================================\n";
		print "Marking FlateRate calls as billed\n";
		print "==================================================\n";
		$query="UPDATE callprotocol SET ".
				"billed_cost = 0, ".
				"billed = 2, ". // 2 means that it was done without an evn
				"dateoffset=0, ".
				"rate_type ='$rate_type', ".
				"billed_duration = CEIL(estimated_duration/60) ".
			"WHERE ".
				"provider_id = $provider_id AND ".
				"ISNULL(rate_type) AND ".
				"ISNULL(billed) AND ".
				"ISNULL(billed_duration) AND ".
				"ISNULL(billed_cost) AND ".
				"ISNULL(dateoffset)";
		//print $query . "\n";
		$result = mysql_query($query,$this->dbh);
		if (!$result) {
    		print 'Invalid query: ' . mysql_errno() . ") ". mysql_error() . "\n";
		}
		else{
			print "Unbilled flatrate calls have been auto-billed.\n";
		}
	}
	
	
}

?>