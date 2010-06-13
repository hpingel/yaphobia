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

class reports{

	//sql patterns often used
	const
		SQL_LEFT_JOIN_PROVIDER_DETAILS_ID = 
			'LEFT JOIN provider_details pd ON pd.provider_id = ',
		SQL_SNIPPET_TOTALDURATION = 
			"CONCAT(SUM(cpt.estimated_duration) DIV 60, 'h ', SUM(cpt.estimated_duration) MOD 60, 'm')", 
		SQL_SNIPPET_TOTALDURATION_AS = "total_duration",
		SQL_SNIPPET_TOTALBILLEDDURATION = 
			"CONCAT(SUM(CEIL(cpt.billed_duration/60)) DIV 60, 'h ', SUM(CEIL(cpt.billed_duration/60)) MOD 60, 'm')",
		SQL_SNIPPET_TOTALBILLEDDURATION_AS = "total_billed_duration",
		SQL_SNIPPET_TOTALCOSTS = 
			"CONCAT(FORMAT(SUM(cpt.billed_cost),2),' EUR')",
		SQL_SNIPPET_TOTALCOSTS_AS = "total_costs",
		XML_REPORT_MONTHLY_BILL = 1,
		XML_REPORT_UNMATCHED_BILLED_ORPHANS = 2,
		XML_REPORT_INCOMING_CALLS = 3,
		XML_REPORT_PROVIDER_DETAILS = 4,
		XML_REPORT_RATE_TYPE = 5,
		XML_REPORT_UNMATCHED_PROTOCOL_ORPHANS = 6,
		XML_REPORT_UNMATCHED_BILLED_ORPHANS_TOTAL = 7,
		XML_REPORT_ANNUAL_OVERVIEW_MONTH = 8,
		XML_REPORT_ANNUAL_OVERVIEW_WEEK = 9,
		XML_REPORT_ANNUAL_OVERVIEW_YEAR = 10,
		XML_REPORT_INCOMING_CALL_LENGTH = 11,
		XML_REPORT_POPULAR_COMM_PARTNERS = 12,
		XML_REPORT_MOST_EXPENSIVE_COMM_PARTNERS = 13,
		XML_REPORT_USER_LIST = 14,
		XML_REPORT_CONTACT_LIST = 15,		
		XML_REPORT_MONTHLY_BILL_SUM = 16;
		
	protected
		$db,
		$dbh;
	private
		$buffered_user_data = array(),
		$user_list_loaded = false;
	
	/*
	 * constructor
	 * 
	 */
	function __construct(){
		$this->db = new dbMan();
		$this->dbh = $this->db->getDBHandle();
		
	}

	/*
	 * getFullResultArray (soon obsolete in this class)
	 * 
	 */
	protected function getFullResultArray($result){
		$table = array();
		while ($row = mysql_fetch_assoc($result)) {
			$table[] = $row;
		}
		return $table;
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
	 * provider details
	 * 
	 */
	protected function sqlProviderDetails(){
		$rm = new reportManager( $this->dbh, self::XML_REPORT_PROVIDER_DETAILS );
		$rm->setTitle('Provider-Infos');
		$rm->addColumn('Provider' , 'provider_name'                    , 'provider_name');
		$rm->addColumn('Guthaben' , 'CONCAT( current_credit, \' EUR\')', 'credit');
		$rm->addColumn('Stand von', 'current_credit_timestamp'         , 'current_credit_timestamp');
		$rm->addSelectFromTable('provider_details pd');
		return $rm;
	}
	
	/*
	 * sqlRateTypeCheck
	 * 
	 */
	protected function sqlRateTypes(){
		$rm = new reportManager( $this->dbh, self::XML_REPORT_RATE_TYPE );
		$rm->setTitle('Tarife der Provider');
		$rm->addColumn('Provider' , 'pd.provider_name', 'provider_name');
		$rm->addColumn('Tarif' , ' prt.rate_type', 'rate_type' );
		$rm->addColumn('Preis pro Minute', 'CONCAT( prt.price_per_minute, \' EUR\')', 'price');
        $rm->addColumn('derzeit aktiv', 'IF (CURRENT_TIMESTAMP() >= prt.valid_from AND CURRENT_TIMESTAMP() <= prt.valid_to, \'ja\',\'nein\')', 'accepted');
        $rm->addColumn('gültig von', 'prt.valid_from', 'valid_from');
        $rm->addColumn('gültig bis', 'prt.valid_to', 'valid_to');
		$rm->addSelectFromTable('provider_rate_types prt '. self::SQL_LEFT_JOIN_PROVIDER_DETAILS_ID . 'prt.provider_id');
		return $rm;
	}

	/*
	 * sqlUnmatchedOrphansInProtocol
	 * 
	 */
	protected function sqlUnmatchedOrphansInProtocol(){
		$rm = new reportManager( $this->dbh, self::XML_REPORT_UNMATCHED_PROTOCOL_ORPHANS );
		$rm->setTitle('Buchungscheck: Protokollierte kostenpflichtige Anrufe, die keinen Buchungsdatensatz haben');
		$rm->addColumn('Datum / Uhrzeit' , 'cp.date', 'date');
		$rm->addColumn('Telefonnummer' , 'cp.phonenumber', 'phonenumber');
		$rm->addColumn('Identit&auml;t' , 'uc.identity', 'identity');
		$rm->addColumn('Dauer Sch&auml;tzung' , 'cp.estimated_duration', 'estimated_duration');
		$rm->addColumn('Provider' , 'pd.provider_name', 'provider_name');
		$rm->addSelectFromTable('callprotocol cp'.
			' ' . self::SQL_LEFT_JOIN_PROVIDER_DETAILS_ID . 'cp.provider_id'.
			' LEFT JOIN user_contacts uc ON cp.phonenumber = uc.phonenumber '.
			' WHERE cp.provider_id > 0 AND cp.calltype=3 AND ISNULL(cp.billed)');
		return $rm;
	}

	/*
	 * annual overview
	 */
	protected function sqlAnnualOverview( $modeId, $year ){
					
		$datedisplay = "monthname(cpt.date)";
		if ($modeId == self::XML_REPORT_ANNUAL_OVERVIEW_YEAR){
			//365 tage
			$mode = "DAYOFYEAR";
			$idlimit = 365; 
			$datedisplay = "CONCAT(dayofmonth(cpt.date), '. ', monthname(cpt.date))";  // FIXME: achtung: schaltjahr!!!
		}
		elseif ($modeId == self::XML_REPORT_ANNUAL_OVERVIEW_MONTH){
			//12 monate	
			$idlimit = 12;
			$mode = "MONTH";
		}
		else{
			//52 wochen
			$mode = "WEEKOFYEAR";
			$idlimit = 52;
		}

		$rm = new reportManager( $this->dbh, $modeId );
		$rm->setTitle('Overview (' . $mode . ')');
		$rm->addColumn('Monat' , 'cdm.id', 'id');
		$rm->addColumn('Monatsname' , $datedisplay, 'monthname');
		$rm->addColumn('Anzahl Gespraeche' , "CONCAT(count(cpt.date), ' Gespraeche')", 'sumofcalls');
		$rm->addColumn('Gespraechsdauer' , self::SQL_SNIPPET_TOTALDURATION, self::SQL_SNIPPET_TOTALDURATION_AS);
		$rm->addColumn('berechnete Gespraechsdauer' , self::SQL_SNIPPET_TOTALBILLEDDURATION, self::SQL_SNIPPET_TOTALBILLEDDURATION_AS);
		$rm->addColumn('Kosten' , self::SQL_SNIPPET_TOTALCOSTS, self::SQL_SNIPPET_TOTALCOSTS_AS);
		$rm->addSelectFromTable("calendar_dummy_data cdm ".
			"LEFT JOIN callprotocol cpt ON cdm.id = $mode(cpt.date) AND YEAR(cpt.date)=$year ".
			"WHERE cdm.id <= $idlimit ".
			"GROUP BY cdm.id");
		return $rm;
	}	

	private function sqlFlexStats( $title, $fields, $where, $orderby, $limit, $year, $month, $modeId ){
		$rm = new reportManager( $this->dbh, $modeId );
		$rm->setTitle($title);
		$rm->addColumn('Identitaet' , 'uc.identity', 'identity');
		$rm->addColumn('Telefonnummer' , 'cpt.phonenumber', 'phonenumber');
		foreach ($fields as $column){
			$rm->addColumn($column[0], $column[1], $column[2]);
		}
		$rm->addSelectFromTable('callprotocol cpt '.
			'LEFT JOIN user_contacts uc ON uc.phonenumber = cpt.phonenumber '.
			'WHERE ' . $where . ' ' . $this->sqlWhereTimeperiod( 'cpt', $month, $year) . ' '. 
			'GROUP BY cpt.phonenumber '.
			"ORDER BY SUM( $orderby ) DESC ".
			'LIMIT '.intval($limit)
		);
		return $rm;			
	}
	
	protected function sqlIncomingCallLength( $limit, $year, $month ){
		return $this->sqlFlexStats( 
			$title = 'Incoming calls length: Show people that like to talk to us (sorted by total call length)', 
			$fields = array( array('Summe Gespraechsdauer', self::SQL_SNIPPET_TOTALDURATION, self::SQL_SNIPPET_TOTALDURATION_AS)),
			$where = 'cpt.calltype != 3 ',
			$orderby = 'cpt.estimated_duration',
			$limit,
			$year,
			$month,
			self::XML_REPORT_INCOMING_CALL_LENGTH
		);
	}

	protected function sqlPopularCommPartners( $limit, $year, $month ){
		return $this->sqlFlexStats( 
			$title = 'Incoming and outgoing calls: Most popular communication partners (sorted by total call length)', 
			$fields = array(
				array('Summe Gespraechsdauer', self::SQL_SNIPPET_TOTALDURATION, self::SQL_SNIPPET_TOTALDURATION_AS),
				array('Summe Gespraechskosten', self::SQL_SNIPPET_TOTALCOSTS, self::SQL_SNIPPET_TOTALCOSTS_AS)
			),
			$where = '1=1',
			$orderby = 'cpt.estimated_duration',
			$limit,
			$year,
			$month,
			self::XML_REPORT_POPULAR_COMM_PARTNERS
		);
	}

	protected function sqlMostExpensiveCommPartners( $limit, $year, $month ){
		return $this->sqlFlexStats( 
			$title = 'Outgoing calls: Most expensive communication partners', 
			$fields = array(
				array('Summe Gespraechskosten', self::SQL_SNIPPET_TOTALCOSTS, self::SQL_SNIPPET_TOTALCOSTS_AS), 
				array('Summe Gespraechsdauer', self::SQL_SNIPPET_TOTALDURATION, self::SQL_SNIPPET_TOTALDURATION_AS)
			),
			$where = 'cpt.calltype = 3 ',
			$orderby = 'cpt.billed_cost',
			$limit,
			$year,
			$month,
			self::XML_REPORT_MOST_EXPENSIVE_COMM_PARTNERS
		);
	}
	
	//this is not a report, only an internal representation of data!!!			
	protected function getUserList(){
		//TODO: turn this into a singleton object
		if (!$this->user_list_loaded){
			$query = "SELECT * FROM users";
			$result = mysql_query( $query, $this->dbh );
			if (mysql_errno() != 0){
				print mysql_error();
				die();	
			}
			$this->buffered_user_data =  $this->getFullResultArray($result);
			$this->user_list_loaded = true;
			
		}
		return $this->buffered_user_data;
	}
	
	protected function sqlUserList(){
		
		$rm = new reportManager( $this->dbh, self::XML_REPORT_USER_LIST );
		$rm->setTitle('Users');
		$rm->addColumn('Nutzer-ID' , 'id', 'id');
		$rm->addColumn('Nutzername' , 'username', 'username');
		$rm->addSelectFromTable('users');
		return $rm;
	}
	
	protected function sqlContactList(){
		$rm = new reportManager( $this->dbh, self::XML_REPORT_CONTACT_LIST );
		$rm->setTitle('Contacts and related users');
		$rm->addColumn('Identitaet'          , 'uc.identity', 'identity');
		$rm->addColumn('Telefonnummer'       , 'uc.phonenumber', 'phonenumber');
		$rm->addColumn('zugeordneter Nutzer' , 'us.username', 'username');
		$rm->addColumn('Zahl der Telefonate' , 'COUNT(cpt.phonenumber)', 'number_of_calls');
		$rm->addSelectFromTable('user_contacts uc'.
			' LEFT JOIN users us ON uc.related_user=us.id'.
			' LEFT JOIN callprotocol cpt ON cpt.phonenumber=uc.phonenumber GROUP BY cpt.phonenumber'.
			' ORDER BY identity');
		return $rm;
	}

	protected function sqlIncomingCalls( $timeperiod ){
		$rm = new reportManager( $this->dbh, self::XML_REPORT_INCOMING_CALLS );
		$rm->setTitle('Eingehende Anrufe (inkl. nicht angenommene)');
		$rm->addColumn('Datum', "DATE_FORMAT(c.date,'%d. %b %W')", 'date');
		$rm->addColumn('Uhrzeit', "DATE_FORMAT(c.date,'%H:%i:%s')", 'time');
		$rm->addColumn('Telefonnummer', 'c.phonenumber', 'phonenumber');
		$rm->addColumn('Identit&auml;t', 'uc.identity', 'identity');
		$rm->addColumn('angenommen', 'IF (c.calltype = 1 AND c.usedphone != \'Anrufbeantworter\', \'ja\',\'nein\')', 'accepted');
		$rm->addColumn('Dauer Sch&auml;tzung', 'c.estimated_duration', 'estimated_duration');
		$rm->addColumn('vermittelnder Provider', 'c.providerstring', 'providerstring');
		$rm->addSelectFromTable('callprotocol c '.
			'LEFT JOIN user_contacts uc ON (c.phonenumber = uc.phonenumber ) '.
			'WHERE calltype!=\'3\''.$timeperiod);
		return $rm;
	}

	/*
	 * sqlUnmatchedBilledOrphans
	 * 
	 */
	protected function sqlUnmatchedBilledOrphans( $timeperiod ){
		if ($timeperiod == ''){
			$date = "DATE_FORMAT(c.date,'%Y %d. %b %W')"; //also display year
			$id = self::XML_REPORT_UNMATCHED_BILLED_ORPHANS_TOTAL; 
		}
		else{
			$date = "DATE_FORMAT(c.date,'%d. %b %W')";
			$id = self::XML_REPORT_UNMATCHED_BILLED_ORPHANS;
		}
		
		$rm = new reportManager( $this->dbh, $id );
		$rm->setTitle('Buchungscheck: Anrufe aus EVN\'s, die nicht im Protokoll gefunden worden sind');
		$rm->addColumn('Datum', $date, 'date');
		$rm->addColumn('Uhrzeit', "DATE_FORMAT(c.date,'%H:%i:%s')", 'time');		
		$rm->addColumn('Telefonnummer', 'c.phonenumber', 'phonenumber');
		$rm->addColumn('Identit&auml;t', 'uc.identity', 'identity');
		$rm->addColumn('Dauer', 'CEIL(c.billed_duration/60)', 'billed_duration'); 
		$rm->addColumn('Provider', 'pd.provider_name', 'provider_name'); 
		$rm->addColumn('Tariftyp', 'c.rate_type', 'rate_type');
		$rm->addColumn('Kosten', 'c.billed_cost', 'billed_cost'); 	
		$rm->addSelectFromTable('unmatched_calls c '.
			'LEFT JOIN user_contacts uc ON c.phonenumber = uc.phonenumber '.
			self::SQL_LEFT_JOIN_PROVIDER_DETAILS_ID . 'c.provider_id '.
			'WHERE 1=1 ' . $timeperiod . ' ORDER BY date');
		return $rm;
	}

	/*
	 * 
	 * @ boolean
	 * @ string
	 * @ boolean
	 * 
	 */
	private function sqlPhoneBillBase( $sum_mode, $timeperiod, $user_cols ){

		if (!$sum_mode){
			$id = self::XML_REPORT_MONTHLY_BILL;
			$rm = new reportManager( $this->dbh, $id );
			$rm->addColumn('Datum', 'DATE_FORMAT(c.date,\'%b %d. %H:%i:%s\')', 'date');			
			$rm->addColumn('Wochentag', 'DATE_FORMAT(c.date,\'%W\')', 'time');			
			$rm->addColumn('Telefonnr.', 'c.phonenumber', 'phonenumber');			
			$rm->addColumn('Identit&auml;t', 'uc.identity', 'identity');			
			//$rm->addColumn('Dauer Sch&auml;tzung', 'c.estimated_duration', 'estimated_duration');
			$rm->addColumn('Dauer', 'CEIL(c.billed_duration/60)', 'billed_duration');
			$rm->addColumn('Provider', 'pd.provider_name', 'provider_name');
			$rm->addColumn('Tariftyp', 'c.rate_type', 'rate_type');
			$rm->addColumn('Tarifcheck', 'IF( ABS( prt.price_per_minute * CEIL( c.billed_duration / 60 ) - c.billed_cost ) = 0, \'OK\', \'FEHLER\')', 'xcheck');
			$rm->addColumn('ungebucht', 'IF( ISNULL(u.username) AND ISNULL(c.user), CONCAT(c.billed_cost, \' EUR\'), \'\')', 'unbooked_calls');			
			$rm->setTitle('Outgoing calls');		
		}
		else{
			$id = self::XML_REPORT_MONTHLY_BILL_SUM;
			$rm = new reportManager( $this->dbh, $id );
			//$rm->addColumn('invisible_1', 'SUM(c.estimated_duration)', 'sum_estimated_duration');
			$rm->addColumn('Dauer', 'CEIL(SUM(c.billed_duration)/60)', 'sum_billed_duration');
			$rm->addColumn('Summe Kosten', "CONCAT(FORMAT(SUM(c.billed_cost),4),' EUR')", 'sum_cost');
			$rm->addColumn('ungebucht', "CONCAT( FORMAT( SUM( IF( ISNULL(u.username) AND ISNULL(c.user), CONCAT(c.billed_cost, ' EUR'), 0)), 4), ' EUR')", 'unbooked_costs');

			$rm->setTitle('Outgoing calls - Sums');		
		}
		
		if ($user_cols === true){
			//get user list from db
			$userlist = $this->getUserList();
			//add user specific cols to sql select statement
			foreach ($userlist as $user) {
				$uid = intval($user['id']);
				$usermatch_condition = ' (ISNULL(c.user) AND uc.related_user = '.$uid. ') OR c.user = '.$uid. ' ';
				if ($sum_mode){
					$rm->addColumn($user['username'], "CONCAT( FORMAT( SUM( IF( ".$usermatch_condition.", c.billed_cost, 0 )), 4), ' EUR')", "user_col_". $uid);
				}
				else{
					$rm->addColumn($user['username'], "IF( ".$usermatch_condition.", CONCAT(c.billed_cost, ' EUR'), '')", "user_col_". $uid);
				}
			}
		}
		else{
			$rm->addColumn('Kosten', "CONCAT(c.billed_cost, ' EUR')", 'billed_cost');
			$rm->addColumn('User', 'u.username', 'username');
		}

		$rm->addSelectFromTable('callprotocol c '.
			self::SQL_LEFT_JOIN_PROVIDER_DETAILS_ID . 'c.provider_id '.
			'LEFT JOIN provider_rate_types prt ON (c.provider_id = prt.provider_id AND c.rate_type = prt.rate_type AND c.date >= prt.valid_from AND c.date <= prt.valid_to ) '.
			'LEFT JOIN user_contacts uc ON (c.phonenumber = uc.phonenumber ) '.
			'LEFT JOIN users u ON (uc.related_user = u.id ) '.
			' WHERE c.calltype = 3 '.$timeperiod);
			
		return $rm;
	}
	
	protected function sqlPhoneBill( $timeperiod, $user_cols ){
		return $this->sqlPhoneBillBase( false, $timeperiod, $user_cols );
	}

	protected function sqlPhoneBillSumRow($timeperiod, $user_cols ){
		return $this->sqlPhoneBillBase( true, $timeperiod, $user_cols );
	}

}
	
?>