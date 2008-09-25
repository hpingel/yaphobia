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

require_once('settings.php');

class dbMan {
	
	var $db;
	
	function __construct(){
		$this->db = mysql_connect( YAPHOBIA_DB_HOST, YAPHOBIA_DB_USER, YAPHOBIA_DB_PASSWORD );
		mysql_select_db  ( YAPHOBIA_DB_NAME , $this->db );
	}

	public function sendQuery( $query ){	
			$result = mysql_query( $query, $this->db );
			return $result;
	}

	public function getDBHandle( ){	
			return $this->db;
	}
	
	
	function insertMonitoredCall( $values ){
		
		$query = "INSERT INTO callprotocol (date, identity, phonenumber, calltype, usedphone, providerstring, provider_id, estimated_duration)".
		$query .= " VALUES (" . $values . ")";
		print "Sending: $query\n";
		
		$result = mysql_query($query,$this->db);
		if (!$result) {
			if (mysql_errno() == 1062){
				print "Duplicate call was skipped! Is already in database.\n";
			}
			else
	    		print 'Invalid query: ' . mysql_errno() . ") ". mysql_error() . "\n";
		}
		else{
			print "Call added to database.\n";
		}
	}
	
	function checkCallUniqueness($x){
		$tolerance_span_call_begin = TOLERANCE_CALL_BEGIN; //in seconds
		$tolerance_span_duration = TOLERANCE_CALL_DURATION; //in seconds
		
		$update= 
			"billed = '1', ".
			"dateoffset = TIMESTAMPDIFF(SECOND, date,'".$x['date']."'), ".
			"rate_type = '".$x['rate_description']."', ".
			"rate_type_id = '0', ".
			"billed_duration = '".$x['duration']."', ".
			"billed_cost = '". floatval(str_replace(',','.',$x['billed_cost']))."'";
		
		$where = 
			"phonenumber = '".$x['number']."' AND ".
			"ABS(TIMESTAMPDIFF(SECOND, date,'".$x['date']."')) < $tolerance_span_call_begin AND ".
			"ABS( estimated_duration *60 - ".$x['duration'].") < $tolerance_span_duration ";
		
		$whereStart = "WHERE calltype='3' AND provider_id = '".$x['providerid']."' AND "; 
		$query = "SELECT * FROM callprotocol $whereStart $where"; 
		//print "Query: $query\n";
		$result = mysql_query( $query, $this->db );
		$matches = mysql_num_rows($result);
		if ($matches > 1){
			print "ERROR: Not able to match following call in protocol:\n";
			print_r($x);
			print "See possible matches here:\n";
			while ($row = mysql_fetch_assoc($result)) {
			    print_r($row);
			}		
		}
		elseif ($matches == 0){
			print "ERROR: No match in call protocol for following call:\n";
			print_r ($x);
		}
		else{
			$row = mysql_fetch_assoc($result);
			if ($row['billed'] != '1'){
				print "Call (". $x["date"] . " ". $x["number"] . "): Found, updating call info.\n";
				$query = "UPDATE callprotocol SET $update $whereStart $where";
				//print "Query: $query\n";
				$result = mysql_query( $query, $this->db );
			}
			else{
				print "Call (". $x["date"] . " ". $x["number"] . "): Already billed. Skipped.\n";
			}
			
		}
	}
	
	function __destruct(){
		mysql_close($this->db);
	}
}


?>