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

define('SQL_LEFT_JOIN_PROVIDER_DETAILS_ID','LEFT JOIN provider_details pd ON pd.provider_id = ');

//sql patterns often used
define('SQL_SNIPPET_TOTALDURATION', "CONCAT(SUM(cpt.estimated_duration) DIV 60, 'h ', SUM(cpt.estimated_duration) MOD 60, 'm') AS total_duration");
define('SQL_SNIPPET_TOTALBILLEDDURATION',"CONCAT(SUM(CEIL(cpt.billed_duration/60)) DIV 60, 'h ', SUM(CEIL(cpt.billed_duration/60)) MOD 60, 'm') AS total_billed_duration");
define('SQL_SNIPPET_TOTALCOSTS',"CONCAT(FORMAT(SUM(cpt.billed_cost),2),' EUR') AS total_costs");
	
class reports{

	protected
		$db,
		$dbh;
	private
		$buffered_user_data = array(),
		$user_list_loaded = false,
		$db_tables_present = 0;
	
	/*
	 * constructor
	 * 
	 */
	function __construct(){
		$this->db = new dbMan();
		$this->dbh = $this->db->getDBHandle();
		
	}

	/*
	 * getFullResultArray
	 * 
	 */
	protected function getFullResultArray($result){
		$table = array();
		while ($row = mysql_fetch_assoc($result)) {
			$table[] = $row;
		}
		return $table;
	}	

	protected function getNumberOfDBTables(){
		if ($this->db_tables_present === 0){
			$result = mysql_query( 'SHOW TABLES', $this->dbh );
			$this->db_tables_present = mysql_num_rows($result);
		}
		return $this->db_tables_present;
	}

	protected function callProtocolIsEmpty(){
		$result = mysql_query( 'SELECT COUNT(*) as calls FROM callprotocol', $this->dbh );
		$row = mysql_fetch_assoc($result);
		if ($row["calls"] == 0)
			$feedback = true;
		else
			$feedback = false;
		return $feedback;
	}		
	
	/*
	 * where clause part for defining a time period
	 * 
	 */
	protected function sqlWhereTimeperiod( $tablename, $month, $year){
		$string = ' AND YEAR(' . mysql_real_escape_string($tablename) . '.date) = ' . intval($year) . ' ';
		if ($month != 0)
			$string .= 'AND MONTH('. mysql_real_escape_string($tablename) . '.date) = ' . intval($month) . ' ';
		return $string;
	}
	
	/*
	 * sqlRateTypeCheck
	 * 
	 */
	protected function sqlRateTypes(){
		$query = 
			'SELECT pd.provider_name'.
			', prt.rate_type'.
			', CONCAT( prt.price_per_minute, \' EUR\')'.
			' FROM provider_rate_types prt'.
			' '. SQL_LEFT_JOIN_PROVIDER_DETAILS_ID . 'prt.provider_id';
		$result = mysql_query( $query, $this->dbh );
		if (mysql_errno() != 0){
			print mysql_error();
			die();	
		}		
		$data = array(
			'title' => 'Tarife der Provider',
			'table' => $this->getFullResultArray($result),
			'query' => $query, 
			'headers' => array(
				'Provider',
				'Tarif',
				'Preis pro Minute',
			),
			'sum_row' => ''
		);			
		return $data;
	}

	/*
	 * sqlUnmatchedOrphansInProtocol
	 * 
	 */
	protected function sqlUnmatchedOrphansInProtocol(){
		$query=
			'SELECT cp.date, cp.phonenumber, uc.identity, cp.estimated_duration, pd.provider_name'.
			' FROM callprotocol cp'.
			' ' . SQL_LEFT_JOIN_PROVIDER_DETAILS_ID . 'cp.provider_id'.
			' LEFT JOIN user_contacts uc ON cp.phonenumber = uc.phonenumber '.
			' WHERE cp.provider_id > 0 AND cp.calltype=3 AND ISNULL(cp.billed)';
		$result = mysql_query( $query, $this->dbh );
		if (mysql_errno() != 0){
			print mysql_error();
			die();	
		}		
		$data = array(
			'title' => 'Buchungscheck: Protokollierte kostenpflichtige Anrufe, die keinen Buchungsdatensatz haben',
			'table' => $this->getFullResultArray($result),
			'query' => $query, 
			'headers' => array(
				'Datum / Uhrzeit',
				'Telefonnummer',
				'Identit&auml;t',
				'Dauer<br/>Sch&auml;tzung',
				'Provider'
			),
			'sum_row' => ''
		);			
		return $data;
	}


	/*
	 * annual overview
	 */
	protected function sqlAnnualOverview( $mode, $year ){
					
		$datedisplay = "monthname(cpt.date)";
		if ($mode == "DAYOFYEAR"){
			//365 tage
			$idlimit = 365; 
			$datedisplay = "CONCAT(dayofmonth(cpt.date), '. ', monthname(cpt.date))";  // FIXME: achtung: schaltjahr!!!
		}
		elseif ($mode == "MONTH"){
			//12 monate	
			$idlimit = 12;
		}
		else{
			//52 wochen
			$mode = "WEEKOFYEAR";
			$idlimit = 52;
		}
		
		$query = "SELECT cdm.id, ".
			"$datedisplay, CONCAT(count(cpt.date), ' Gespraeche') AS sumofcalls, " . SQL_SNIPPET_TOTALDURATION . ", " . SQL_SNIPPET_TOTALBILLEDDURATION . ", " . SQL_SNIPPET_TOTALCOSTS . " ".
			"FROM calendar_dummy_data cdm ".
			"LEFT JOIN callprotocol cpt ON cdm.id = $mode(cpt.date) AND YEAR(cpt.date)=$year ".
			"WHERE cdm.id <= $idlimit ".
			"GROUP BY cdm.id";
		
		$result = mysql_query( $query, $this->dbh );
		if (mysql_errno() != 0){
			print mysql_error();
			die();	
		}		
		$data = array(
			'title' => 'Overview (' . $mode . ')',
			'table' => $this->getFullResultArray($result),
			'query' => $query, 
			'headers' => array(
				'Monat',
				'Monatsname',
				'Anzahl<br/>Gespraeche',
				'Gespraechsdauer',
				'berechnete<br/>Gespraechsdauer',
				'Kosten'
			),
			'sum_row' => ''
		);			
		return $data;		
	}	

	private function sqlFlexStats( $title, $fields, $where, $orderby, $limit, $year, $month ){
		$headers = array(
			'Identitaet',
			'Telefonnummer'
		);
		$sql_fields = '';
		foreach ($fields as $key=>$value){
			$headers[]= $key;
			$sql_fields .= $value .', ';
		}
		$sql_fields = substr($sql_fields, 0, strlen($sql_fields) -2 );
		
		$query=
			'SELECT uc.identity, cpt.phonenumber, ' . $sql_fields . ' '.
			'FROM callprotocol cpt '.
			'LEFT JOIN user_contacts uc ON uc.phonenumber = cpt.phonenumber '.
			'WHERE ' . $where . ' ' . $this->sqlWhereTimeperiod( 'cpt', $month, $year) . ' '. 
			'GROUP BY cpt.phonenumber '.
			"ORDER BY SUM( $orderby ) DESC ".
			'LIMIT '.intval($limit);
			
		$result = mysql_query( $query, $this->dbh );
		if (mysql_errno() != 0){
			print mysql_error();
			die();	
		}	
		$data = array(
			'title' => $title,
			'table' => $this->getFullResultArray($result),
			'query' => $query, 
			'headers' => $headers,
			'sum_row' => ''
		);			
		return $data;			
	}
	
	protected function sqlIncomingCallLength( $limit, $year, $month ){
		return $this->sqlFlexStats( 
			$title = 'Incoming calls length: Show people that like to talk to us (sorted by total call length)', 
			$fields = array('Summe Gespraechsdauer' => SQL_SNIPPET_TOTALDURATION),
			$where = 'cpt.calltype != 3 ',
			$orderby = 'cpt.estimated_duration',
			$limit,
			$year,
			$month
		);
	}

	protected function sqlPopularCommPartners( $limit, $year, $month ){
		return $this->sqlFlexStats( 
			$title = 'Incoming and outgoing calls: Most popular communication partners (sorted by total call length)', 
			$fields = array(
				'Summe Gespraechsdauer' => SQL_SNIPPET_TOTALDURATION,
				'Summe Gespraechskosten' => SQL_SNIPPET_TOTALCOSTS 
			),
			$where = '1=1',
			$orderby = 'cpt.estimated_duration',
			$limit,
			$year,
			$month
		);
	}

	protected function sqlMostExpensiveCommPartners( $limit, $year, $month ){
		return $this->sqlFlexStats( 
			$title = 'Outgoing calls: Most expensive communication partners', 
			$fields = array(
				'Summe Gespraechskosten' => SQL_SNIPPET_TOTALCOSTS, 
				'Summe Gespraechsdauer' => SQL_SNIPPET_TOTALDURATION
			),
			$where = 'cpt.calltype = 3 ',
			$orderby = 'cpt.billed_cost',
			$limit,
			$year,
			$month
		);
	}

	
	protected function getUserList(){
		//TODO: turn this into a singleton object
		if (!$user_list_loaded){
			$query = "SELECT * FROM users";
			$result = mysql_query( $query, $this->dbh );
			if (mysql_errno() != 0){
				print mysql_error();
				die();	
			}
			$this->buffered_user_data =  $this->getFullResultArray($result);
			$user_list_loaded = true;
			
		}
		return $this->buffered_user_data;
	}
	
	protected function sqlUserList(){
		$query = "SELECT username FROM users";
		$result = mysql_query( $query, $this->dbh );
		if (mysql_errno() != 0){
			print mysql_error();
			die();	
		}		
		$data = array(
			'title' => 'Users',
			'table' => $this->getFullResultArray($result),
			'query' => $query, 
			'headers' => array(
				'Nutzername'
			),
			'sum_row' => ''
		);			
		return $data;		
	}
	
	protected function sqlContactList(){	
		$query = 
			'SELECT uc.identity'.
			', uc.phonenumber'.
			', us.username'.
			', COUNT(cpt.phonenumber) AS number_of_calls'.
			' FROM user_contacts uc'.
			' LEFT JOIN users us ON uc.related_user=us.id'.
			' LEFT JOIN callprotocol cpt ON cpt.phonenumber=uc.phonenumber GROUP BY cpt.phonenumber'.
			' ORDER BY identity';
		$result = mysql_query( $query, $this->dbh );
		if (mysql_errno() != 0){
			print mysql_error();
			die();	
		}		
		$data = array(
			'title' => 'Contacts and related users',
			'table' => $this->getFullResultArray($result),
			'query' => $query, 
			'headers' => array(
				'Identitaet',
				'Telefonnummer',
				'zugeordneter Nutzer',
				'Zahl der Telefonate'
			),
			'sum_row' => ''
		);			
		return $data;				
	}

	protected function sqlIncomingCalls( $timeperiod ){
		$query = 
			"SELECT DATE_FORMAT(c.date,'%d. %b %W') AS date".
			", DATE_FORMAT(c.date,'%H:%i:%s') AS time".
			', c.phonenumber'.
			', uc.identity'.
			', if (c.calltype = 1 AND c.usedphone != \'Anrufbeantworter\', \'ja\',\'nein\')'.
			', c.estimated_duration'.
			', c.providerstring'.
			' FROM callprotocol c '.
			"LEFT JOIN user_contacts uc ON (c.phonenumber = uc.phonenumber ) ".
			"WHERE calltype!='3'".$timeperiod;
		$result = mysql_query( $query, $this->dbh );
		if (mysql_errno() != 0){
			print mysql_error();
			die();	
		}		
		$data = array(
			'title' => 'Eingehende Anrufe (inkl. nicht angenommene)',
			'table' => $this->getFullResultArray($result),
			'query' => $query, 
			'headers' => array(
				'Datum',
				'Uhrzeit',
				'Telefonnummer',
				'Identit&auml;t',
				'angenommen',
				'Dauer<br/>Sch&auml;tzung',
				'vermittelnder Provider'
			),
			'sum_row' => ''
		);			
		return $data;
	}

	/*
	 * sqlUnmatchedBilledOrphans
	 * 
	 */
	protected function sqlUnmatchedBilledOrphans( $timeperiod ){
		$query = 
			'SELECT DATE_FORMAT(c.date,\'%d %W\') AS date'.
			', DATE_FORMAT(c.date,\'%H:%i:%s\') AS time'.
			', c.phonenumber'.
			', uc.identity, '.
			'CEIL(c.billed_duration/60) AS billed_duration'.
			', pd.provider_name'.
			', c.rate_type'.
			', c.billed_cost '.
			'FROM unmatched_calls c '.
			'LEFT JOIN user_contacts uc ON c.phonenumber = uc.phonenumber '.
			SQL_LEFT_JOIN_PROVIDER_DETAILS_ID . 'c.provider_id '.
			"WHERE 1=1 " . $timeperiod;
		$result = mysql_query($query , $this->dbh );
		if (mysql_errno() != 0){
			print mysql_error();
			die();	
		}		
		$data = array(
			'title' => 'Buchungscheck: Anrufe aus EVN\'s, die nicht im Protokoll gefunden worden sind.',
			'table' => $this->getFullResultArray($result),
			'query' => $query, 
			'headers' => array(
				'Datum',
				'Uhrzeit',
				'Telefonnummer',
				'Identity',
				'Dauer',
				'Provider',
				'Tariftyp',
				'Kosten'
			),
			'sum_row' => ''
		);			
		return $data;
	}

	/*
	 * 
	 * @ boolean
	 * @ string
	 * @ boolean
	 * 
	 */
	private function sqlPhoneBillBase( $sum_mode, $timeperiod, $user_cols ){
		
		$user_cols_string = "";
		
		if ($user_cols === true){
			//get user list from db
			$userlist = $this->getUserList();
			//add user specific cols to sql select statement
			foreach ($userlist as $user) {
				$uid = intval($user['id']);
				if ($sum_mode)
					$user_cols_string .= ", CONCAT( FORMAT( SUM( IF( uc.related_user = ".$uid.", c.billed_cost, 0 )), 4), ' EUR') AS user_col_". $uid;
				else
					$user_cols_string .= ", IF( uc.related_user = ".$uid.", CONCAT(c.billed_cost, ' EUR'), '') AS user_col_". $uid;
			}
		}
		else{
			$user_cols_string .= ", CONCAT(c.billed_cost, ' EUR') AS billed_cost, u.username as username";
		}

		if ($sum_mode){
			$query = 
				"SELECT".
				" SUM(c.estimated_duration) AS sum_estimated_duration".
				", CEIL(SUM(c.billed_duration)/60) AS sum_billed_duration".
				", CONCAT(FORMAT(SUM(c.billed_cost),4),' EUR') AS sum_cost" .
				", CONCAT( FORMAT( SUM( IF( ISNULL(u.username), CONCAT(c.billed_cost, ' EUR'), 0)), 4), ' EUR') AS unbooked_costs";
			$table_headers = array();
		}
		else{
			$query = 
				"SELECT DATE_FORMAT(c.date,'%d. %b %W') AS date".
				", DATE_FORMAT(c.date,'%H:%i:%s') AS time".
				", c.phonenumber".
				", uc.identity".
				", c.estimated_duration".
				", CEIL(c.billed_duration/60) AS billed_duration".
				", pd.provider_name".
				", c.rate_type" .
				", IF( ABS( prt.price_per_minute * CEIL( c.billed_duration / 60 ) - c.billed_cost ) = 0, 'OK', 'FEHLER') AS xcheck ".
				", IF( ISNULL(u.username), CONCAT(c.billed_cost, ' EUR'), '') AS unbooked_calls";
			$table_headers = array(
				'Datum',
				'Uhrzeit',
				'Telefonnummer',
				'Identit&auml;t',
				'Dauer<br/>Sch&auml;tzung',
				'Dauer<br/>Rechung',
				'Provider',
				'Tariftyp',
				'Tarif-<br/>check',
				'ungebucht'
				);
			
			if ($user_cols === true){	
				//add user columns to table headers
				$userlist = $this->getUserList();
				foreach ($userlist as $user) {
					$table_headers[] = $user['username'];
				}	
			}
			else{
				$table_headers[] = 'Kosten'; 		
				$table_headers[] = 'User';
			}			
		}
		$query .= 
			$user_cols_string . 
			' FROM callprotocol c '.
			SQL_LEFT_JOIN_PROVIDER_DETAILS_ID . 'c.provider_id '.
			'LEFT JOIN provider_rate_types prt ON (c.provider_id = prt.provider_id AND c.rate_type = prt.rate_type ) '.
			'LEFT JOIN user_contacts uc ON (c.phonenumber = uc.phonenumber ) '.
			'LEFT JOIN users u ON (uc.related_user = u.id ) '.
			' WHERE c.calltype = 3 '.$timeperiod;
		
		$result = mysql_query($query , $this->dbh );
		if (mysql_errno() != 0){
			print mysql_error();
			die();	
		}		
		$data = array(
			'title' => 'Outgoing calls',
			'table' => $this->getFullResultArray($result),
			'query' => $query, 
			'headers' => $table_headers,
			'sum_row' => ''
		);			
		return $data;		
	}
	
	protected function sqlPhoneBill( $timeperiod, $user_cols ){
		return $this->sqlPhoneBillBase( false, $timeperiod, $user_cols );
	}

	protected function sqlPhoneBillSumRow($timeperiod, $user_cols ){
		return $this->sqlPhoneBillBase( true, $timeperiod, $user_cols );
	}

}
	
?>