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

require_once( "../classes/class.trace.php");
require_once( "../classes/class.db_manager.php");
require_once( "../classes/class.callImportManager.php");
require_once( "../classes/class.installHelpers.php");

define('CATEGORY_MONTHLY_BILLS', 	1);
define('CATEGORY_RATE_TYPE_CHECK', 	2);
define('CATEGORY_MATCH_CHECK', 		3);
define('CATEGORY_ANNUAL_OVERVIEW', 	4);
define('CATEGORY_MIXED_STATS', 		5);
define('CATEGORY_DATA_IMPORT', 		6);
define('CATEGORY_CONFIG_CHECK',		7);
define('CATEGORY_CONTACTS',			8);
define('CATEGORY_LOGOUT', 			100);

define( 'PATH_TO_SETTINGS', str_replace("htdocs","",dirname(__FILE__)) . 'config/settings.php' );

class htmlFrontend{
	
	private
		$printview,
		$cat,
		$months,
		$db,
		$dbh
		;
	
	function __construct() {
		
		$this->months = array(
			'Januar',
			'Februar',
			'Maerz',
			'April',
			'Mai',
			'Juni',
			'Juli',
			'August',
			'September',
			'Oktober',
			'November',
			'Dezember'
		);			
		$this->cat = isset($_REQUEST["category"]) ? intval($_REQUEST["category"]) : 1;
		$this->printview = isset($_REQUEST["printview"]) ? (intval($_REQUEST["printview"]) == 1 ? true : false ) : false;
		
		/*
		 * check for show stoppers:
		 * 1) presence of settings file
		 * 2) correct authentication
		 * 3) mandatory configuration constants
		 */
		
		if (file_exists(PATH_TO_SETTINGS)){
			require_once(PATH_TO_SETTINGS);
			define('AUTHENTICATION_ENABLED',defined('YAPHOBIA_WEB_INTERFACE_PASSWORD') && (constant('YAPHOBIA_WEB_INTERFACE_PASSWORD') != ""));
			if ( AUTHENTICATION_ENABLED ){
				session_start();
				//check if session is authenticated
				if (!session_is_registered('AUTHENTICATED')){
					//check password
					if (isset($_POST['PW']) && $_POST['PW'] == YAPHOBIA_WEB_INTERFACE_PASSWORD ){
						session_register('AUTHENTICATED');
					}
					else{
						if (!session_is_registered('MULTIPLE_LOGIN_ATTEMPT')){
							session_register('MULTIPLE_LOGIN_ATTEMPT');
							$message = "Welcome! Please enter your password.";
						}
						else{
							//session_unregister('FIRST_LOGIN_ATTEMPT');
							$message = "Wrong password!";
						}
						define('CLOSE_GATE', 
							'<div class="welcome"><h1>'.$message.'</h1>'.
							'<form method="post" action="index.php">Please enter your password: '.
							'<input type="password" name="PW" value="" />'.
							'<input type="submit" value="OK"/>'.
							'</form></div>'
						);
					}
				}
			}
			$ih = new installHelpers();
			$sermon = $ih->proofreadMandatorySettings();
			if ($sermon != "") 
				define('CLOSE_GATE', '<div class="welcome" style="text-align: left;">' . $sermon . '</div>');
		}
		else{
			define('CLOSE_GATE', '<div class="welcome"><p>ERROR: There is no configuration file <b>settings.php</b>!<br/>Please copy <b>settings.defaults.php</b> to <b>settings.php</b> and change the options within the file according to your needs.</p></div>');
		}
		
		
		//header("Content-type: text/xml"); //disabled until we solved some problems
		print '<?xml version="1.0" encoding="utf-8"?>';
		
		define(CR,"\n");

?>
<!DOCTYPE html
     PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:svg="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
<head>
	<title>Yaphobia <?php print htmlspecialchars(YAPHOBIA_VERSION); ?></title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<link rel="stylesheet" type="text/css" href="themes/standard/styles.css" />
<?php 
	if ($this->printview){
		print '<link rel="stylesheet" type="text/css" href="themes/standard/printview.css" />' . CR;
	}
?>	
	
</head>
	<body>
		<div class="content_wrap">

<?php

		if ( AUTHENTICATION_ENABLED ){
			$category_menu[CATEGORY_LOGOUT] = 'Logout';
		}
		
		if ($this->printview === false){ 
			print '<div class="yaph_header"><h1>Yaphobia</h1>';
			
			if (!defined('CLOSE_GATE') && ($this->cat != CATEGORY_LOGOUT) || !AUTHENTICATION_ENABLED ) {
			
				$category_menu = array(
					CATEGORY_MONTHLY_BILLS 		=> 'Monatsrechnungen',
					CATEGORY_RATE_TYPE_CHECK 	=> 'Tarifcheck',
					CATEGORY_MATCH_CHECK 		=> 'Buchungscheck',
					CATEGORY_ANNUAL_OVERVIEW	=> 'Jahresüberblick',
					CATEGORY_MIXED_STATS 		=> 'Weitere Statistiken',
					CATEGORY_CONTACTS			=> 'Kontakte',
					CATEGORY_DATA_IMPORT 		=> 'Datenimport',
					CATEGORY_CONFIG_CHECK		=> 'Konfigurations-Check'
				);	
				print '<p class="category_menu">';
				foreach ($category_menu as $id=>$desc){
					$class= ($id == $this->cat)? ' class="active"' : '';
					print '<a'.$class.' href="?category='.intval($id).'">'.htmlspecialchars($desc).'</a> ';
				}
				print "</p>";
			}
			
			print "</div><br/>";
		}
		//stop here if a showstopper has occured above
		if (defined('CLOSE_GATE')){
			print(CLOSE_GATE . "<br/>");
		}
		else{
			$this->db = new dbMan();
			$this->dbh = $this->db->getDBHandle();
			$this->actions();	
		}

?>
			<hr />
			<p><b>Yaphobia <?php print htmlspecialchars(YAPHOBIA_VERSION);?></b> - Yet Another Phone Bill Application - Licensed under GPL - Get it for free and contribute at <a href="https://sourceforge.net/projects/yaphobia/">https://sourceforge.net/projects/yaphobia/</a>!</p>
		</div>
	</body>
</html>
<?php
		
	} // end of constructor


	private function actions(){
	
		$category = $this->cat;
		//set config values to default values in case they are undefined
		//also collect protocol information about this in $sermonOptionalSettings  
		$ih = new installHelpers();
		$sermonOptionalSettings = $ih->proofreadOptionalSettings();
		
		//check if yaphobia database is empty. if it is empty, offer to create all tables
		$result = mysql_query( 'SHOW TABLES', $this->dbh );
		if (mysql_num_rows($result) === 0){
			print '<div class="welcome"><h2>Welcome!</h2><p>It seems that your MySQL database is completely empty.<br/>You are probably running Yaphobia for the first time.</br> '.
				'Needed database tables will be created in the database now...</p><pre></div>';
			$ih = new installHelpers();
			$ih->createDBTables($this->dbh);
			print "</pre>";
			$category = 0;
		}
		elseif (mysql_num_rows($result) < 6){ //FIXME: improve this value with constant or something...
			print '<div class="welcome"><h2>Welcome!</h2><p>It seems that your MySQL database is missing some tables.<br/> '.
				'Needed database tables will be created in the database now...</p><pre></div>';
			$ih = new installHelpers();
			$ih->createDBTables($this->dbh);	
			print "</pre>";
			$category = 0;
		}
		
		//check if yaphobia callprotocol table is empty
		$result = mysql_query( 'SELECT COUNT(*) as calls FROM callprotocol', $this->dbh );
		$row = mysql_fetch_assoc($result);
		if ($row["calls"] == 0){
			print '<div class="welcome"><h2>Welcome!</h2><p>It seems that the call protocol of Yaphobia is completely empty!<br/>You are probably running Yaphobia for the first time.</br> '.
				'Please import call protocol data into Yaphobia!</p></div>';
				$category = 6;
		}
		
		
		$totalduration = "CONCAT(SUM(cpt.estimated_duration) DIV 60, 'h ', SUM(cpt.estimated_duration) MOD 60, 'm') AS total_duration";
		$totalbilledduration = "CONCAT(SUM(CEIL(cpt.billed_duration/60)) DIV 60, 'h ', SUM(CEIL(cpt.billed_duration/60)) MOD 60, 'm') AS total_billed_duration";
		$totalcosts = "CONCAT(FORMAT(SUM(cpt.billed_cost),2),' EUR') AS total_costs";
	
		$year = '2008';
		
		switch ($category){
		case CATEGORY_MONTHLY_BILLS:
			$month = intval($_REQUEST["m"]);
			$year = intval($_REQUEST["y"]);
			$this->getMonthlyReport($month,$year, false, true);
			break;
			
		case CATEGORY_RATE_TYPE_CHECK:
			$query="SELECT pd.provider_name, prt.rate_type, concat( prt.price_per_minute, ' EUR') FROM provider_rate_types prt LEFT JOIN provider_details pd ON (prt.provider_id = pd.provider_id)";
			$result = mysql_query( $query, $this->dbh );
			$table_headers = array(
				'Nr.',
				'Provider',
				'Tarif',
				'Preis pro Minute',
				);
			print "<h1>Tarife der Provider</h1>";
			print $this->getTableContent($table_headers, $result, "");
			$this->getMonthlyReport(0,0, true, false);
			break;
			
		case CATEGORY_MATCH_CHECK:
			$query="SELECT c.date, c.phonenumber, c.identity, c.estimated_duration, d.provider_name  FROM callprotocol c LEFT JOIN provider_details d ON (c.provider_id=d.provider_id) WHERE c.provider_id > 0 AND c.calltype=3 AND ISNULL(c.billed)";
			$result = mysql_query( $query, $this->dbh );
			$table_headers = array(
				'Nr.',
				'Datum / Uhrzeit',
				'Telefonnummer',
				'Identit&auml;t',
				'Dauer<br/>Sch&auml;tzung',
				'Provider'
				);
			print "<h1>Buchungscheck: Protokollierte kostenpflichtige Anrufe, die keinen Buchungsdatensatz haben</h1>";
			print $this->getTableContent($table_headers, $result, "");
			
			$result = mysql_query( "SELECT * FROM unmatched_calls", $this->dbh );
			$table_headers = array(
				'Nr.',
				'Provider',
				'Datum / Uhrzeit',
				'Dauer',
				'Tariftyp',
				'Kosten',
				'Telefonnummer'
				);
			print "<h1>Buchungscheck: Anrufe aus EVN's, die nicht im Protokoll gefunden worden sind.</h1>";
			print $this->getTableContent($table_headers, $result, "");
			break;
				
		case CATEGORY_ANNUAL_OVERVIEW:
			$table_headers = array(
				'Nr.',
				'Monat',
				'Monatsname',
				'Anzahl<br/>Gespraeche',
				'Gespraechsdauer',
				'berechnete<br/>Gespraechsdauer',
				'Kosten'
				);
				
			//12 monate	
			$timestep_level = "MONTH";
			$idlimit = 12;
			$datedisplay = "monthname(cpt.date)";
			
			$query="SELECT cdm.id, ".
				"$datedisplay, CONCAT(count(cpt.date), ' Gespraeche') AS sumofcalls, $totalduration, $totalbilledduration, $totalcosts ".
				"FROM calendar_dummy_data cdm LEFT JOIN callprotocol cpt ON cdm.id = $timestep_level(cpt.date) AND YEAR(cpt.date)=$year ".
				"WHERE cdm.id <= $idlimit ".
				"GROUP BY cdm.id";
			
			$result = mysql_query( $query, $this->dbh );
			print "<h1>2008: Overview ($timestep_level)</h1>";
			print $this->getTableContent($table_headers, $result, "");
	
			//52 wochen
			$timestep_level = "WEEKOFYEAR";
			$idlimit = 52;
			$datedisplay = "monthname(cpt.date)";
			
			$query="SELECT cdm.id, ".
				"$datedisplay, CONCAT(count(cpt.date), ' Gespraeche') AS sumofcalls, $totalduration, $totalbilledduration, $totalcosts ".
				"FROM calendar_dummy_data cdm LEFT JOIN callprotocol cpt ON cdm.id = $timestep_level(cpt.date) AND YEAR(cpt.date)=$year ".
				"WHERE cdm.id <= $idlimit ".
				"GROUP BY cdm.id";
			
			$result = mysql_query( $query, $this->dbh );
			print "<h1>2008: Overview ($timestep_level)</h1>";
			print $this->getTableContent($table_headers, $result, "");	
			
			//365 tage
			$timestep_level = "DAYOFYEAR"; 
			$idlimit = 365; 
			$datedisplay = "CONCAT(dayofmonth(cpt.date), '. ', monthname(cpt.date))";  // achtung: schaltjahr!!!
			
			$query="SELECT cdm.id, ".
				"$datedisplay, CONCAT(count(cpt.date), ' Gespraeche') AS sumofcalls, $totalduration, $totalbilledduration, $totalcosts ".
				"FROM calendar_dummy_data cdm LEFT JOIN callprotocol cpt ON cdm.id = $timestep_level(cpt.date) AND YEAR(cpt.date)=$year ".
				"WHERE cdm.id <= $idlimit ".
				"GROUP BY cdm.id";
			
			$result = mysql_query( $query, $this->dbh );
			print "<h1>2008: Overview ($timestep_level)</h1>";
			print $this->getTableContent($table_headers, $result, "");
			break;
			
		case CATEGORY_MIXED_STATS:
			$query=
				"SELECT uc.identity, cpt.phonenumber, $totalduration FROM callprotocol cpt ".
				"LEFT JOIN user_contacts uc ON cpt.phonenumber = uc.phonenumber ".
				"WHERE cpt.calltype != 3 GROUP BY cpt.phonenumber ORDER BY SUM(cpt.estimated_duration) DESC LIMIT 20";
			$result = mysql_query( $query, $this->dbh );
			$table_headers = array(
				'Nr.',
				'Identitaet',
				'Telefonnummer',
				'Summe Gespraechsdauer'
				);
			print "<h1>Incoming calls length: Show people that like to talk to us (sorted by total call length)</h1>";
			print $this->getTableContent($table_headers, $result, "");	
			
			$query="SELECT uc.identity, cpt.phonenumber, $totalduration, $totalcosts ".
				"FROM callprotocol cpt ".
				"LEFT JOIN user_contacts uc ON cpt.phonenumber = uc.phonenumber ".
				"GROUP BY cpt.phonenumber ".
				"ORDER BY SUM(cpt.estimated_duration) DESC LIMIT 20";
			$result = mysql_query( $query, $this->dbh );
			$table_headers = array(
				'Nr.',
				'Identitaet',
				'Telefonnummer',
				'Summe Gespraechsdauer',
				'Summe Gespraechskosten'
				);
			print "<h1>Incoming and outgoing calls: Most popular communication partners (sorted by total call length)</h1>";
			print $this->getTableContent($table_headers, $result, "");
	
			$query="SELECT uc.identity, cpt.phonenumber, $totalcosts, $totalduration FROM callprotocol cpt ".
				"LEFT JOIN user_contacts uc ON cpt.phonenumber = uc.phonenumber ".
				"WHERE cpt.calltype = 3 ".
				"GROUP BY cpt.phonenumber ".
				"ORDER BY SUM(cpt.billed_cost) DESC LIMIT 20";
			$result = mysql_query( $query, $this->dbh );
			$table_headers = array(
				'Nr.',
				'Identitaet',
				'Telefonnummer',
				'Summe Gespraechskosten',
				'Summe Gespraechsdauer'
			);
			print "<h1>Outgoing calls: Most expensive communication partners</h1>";
			print $this->getTableContent($table_headers, $result, "");
			break;
			
		case CATEGORY_CONTACTS:
	
			$query = "SELECT username FROM users";
			$result = mysql_query( $query, $this->dbh );
			$table_headers = array(
				'Nr.',
				'Nutzername'
			);
			print "<h1>Users</h1>";
			print $this->getTableContent($table_headers, $result, "");
			
			$query = "SELECT identity, phonenumber, username FROM user_contacts LEFT JOIN users ON related_user=id ORDER BY identity";
			$result = mysql_query( $query, $this->dbh );
			$table_headers = array(
				'Nr.',
				'Identitaet',
				'Telefonnummer',
				'zugeordneter Nutzer'
			);
			print "<h1>Contacts and related users</h1>";
			print $this->getTableContent($table_headers, $result, "");
			break;
			
		case CATEGORY_DATA_IMPORT:
			$importType = isset($_REQUEST['import_type']) ? intval($_REQUEST['import_type']) : 0;
			if ( $importType == 0){
				define('IMPORT_LINK_START', '<h2><a href="?category='.intval(CATEGORY_DATA_IMPORT).'&import_type=');
				define('IMPORT_LINK_END', '</a></h2>'); 
				print IMPORT_LINK_START . '1">Anrufliste aus Fritzbox importieren' . IMPORT_LINK_END;
				if (SIPGATE_ACTIVE){
					print IMPORT_LINK_START . '2">EVN des aktuellen Monats von sipgate importieren' . IMPORT_LINK_END;
					print IMPORT_LINK_START . '3">Komplette EVN-Historie von sipgate importieren' . IMPORT_LINK_END;
				}
				if (DUSNET_ACTIVE)
					print IMPORT_LINK_START . '4">EVN des aktuellen Monats von dus.net importieren' . IMPORT_LINK_END;
			}
			else{
			}
	
			$tr = new trace('html');
			$call_import = new callImportManager($this->dbh, $tr);
					
			if ( $importType == 1){
				print '<h2>Anrufliste aus Fritzbox importieren</h2>';
				$call_import->getFritzBoxCallerList();
			}
			elseif ( $importType == 2 && SIPGATE_ACTIVE){
				print '<h2>EVN des aktuellen Monats von sipgate importieren</h2>';
				$sg_callist = $call_import->getSipgateCallsOfCurrentMonth( SIPGATE_USERNAME, SIPGATE_PASSWORD);
				$call_import->putSipgateCallArrayIntoDB($sg_callist, SIPGATE_PROVIDER_ID);
			}
			elseif ( $importType == 3 && SIPGATE_ACTIVE){
				print '<h2>Komplette EVN-Historie von sipgate importieren</h2>';
				//set month and year to empty values to persuade sipgate to return a complete call history
				$call_import->setMonth("");
				$call_import->setYear("");
				$sg_callist = $call_import->getSipgateCallsOfCurrentMonth( SIPGATE_USERNAME, SIPGATE_PASSWORD);
				$call_import->putSipgateCallArrayIntoDB($sg_callist, SIPGATE_PROVIDER_ID);
			}
			elseif ( $importType == 4 && DUSNET_ACTIVE){
				print '<h2>EVN des aktuellen Monats von dus.net importieren</h2>';
				$dusnet_callist = $call_import->getDusNetCalls( DUSNET_SIPACCOUNT, DUSNET_USERNAME, DUSNET_PASSWORD );
				$call_import->putDusNetCallArrayIntoDB($dusnet_callist, DUSNET_PROVIDER_ID);
			}
			break;
			
		case CATEGORY_CONFIG_CHECK:
			print "<p>This check can help you to find problems in your setup</p>";
			print '<div class="welcome" style="text-align: left;"><h1>Checking optional configuration parameters</h1>' . $sermonOptionalSettings . '</div>';
			break;
		case CATEGORY_LOGOUT:
			if (AUTHENTICATION_ENABLED){
				session_unregister('AUTHENTICATED'); //logout
				session_unregister('MULTIPLE_LOGIN_ATTEMPT');
				print '<div class="welcome"><h1>Logout successful.</h1>'.
					'<p><a href="index.php">Login again</a></p></div>';
			}
			break;
		}
	}

	/*
	 * creates html form with month selector
	 * 
	 */
	private function monthFlicker($month, $year){
		
		define('CAT','&category='.$this->cat);
		
		print '<div class="date_nav">';
		if ($month > 1)
			print '<a class="date_nav" href="?m='.($month-1 ) . '&y='.$year.CAT.'">&lt; '.$this->months[$month -2 ].'</a> | ';
		elseif ($month == 1)
			print '<a class="date_nav" href="?m=12&y='.($year-1).CAT.'">&lt; '.$months[11].' ' . ($year-1) . '</a> | ';
			
		print '<span class="date_nav_active">'.$this->months[ $month -1  ].'</span>';
		if ($month < 12)
			print ' | <a class="date_nav" href="?m='.($month +1) . '&y='.$year.CAT.'">'.$this->months[intval($month) ].' &gt;</a> ';
		elseif ($month == 12)
			print ' | <a class="date_nav" href="?m=1&y='.($year+1).CAT.'">'.$this->months[0].' ' . ($year+1) . ' &gt;</a>';
		
		print '</div>';
		$this->monthPickerForm($month, $year);
	
	}
	
	private function monthPickerForm($month, $year){
		print '<form name="monthpicker" action="index.php" method="post">'."\n";
		print '<br/><select name="m">'."\n";
		for ($z=0; $z < count($this->months); $z++){
			$selected = ($month == ($z + 1)) ? 'selected="selected"' : ""; 
			print '<option '.$selected.' value="'.($z+1).'">'.$this->months[$z].'</option>'. "\n";
		}
		print '</select>';
		
		$current_year = date('Y', time());
		print ' <select name="y">'."\n";
		for ($z = 2000; $z <= $current_year; $z++){
			$selected = ($year == $z ) ? 'selected="selected"' : ""; 
			print '<option '.$selected.' value="'.$z.'">'.$z.'</option>'. "\n";
		}
		print '</select>';
		
		print 
			'<input type="hidden" name="category" value="'.$this->cat.'"/>
			<input type="submit" value="OK"/>';
		
			
		print '</form><br/>';	
	}
	
	/*
	 * 
	 * $all (true / false)
	 * $user_cols (true / false)
	 */
	private function getMonthlyReport( $month, $year, $all, $user_cols){
	
		if ($all === false){
			$month = intval($month);
			if ($month == 0) $month = date('m', time());
			$month = ($month > 12 || $month < 1) ? 1 : $month; //default value: january
			$year = intval($year);
			if ($year == 0) $year = date('Y', time());
			$year = ($year > 2100 || $year < 2000) ? 2008 : $year; //default value: january
			$timeperiod = " AND MONTH(c.date) = '".$month."' AND YEAR(c.date) = '".$year."'";
			if ($this->printview === false){ 
				$this->monthFlicker($month, $year, 1);
			}
			$timeframe = htmlspecialchars($month . '/' . $year);
			
		}
		else{
			$timeframe = '(zeitlich unbegrenzt)';
			$timeperiod = "";
		}
		
		print "<h1>Telefonreport $timeframe</h1>";
		
		$query = 
			"SELECT c.date, c.phonenumber, uc.identity, CEIL(c.billed_duration/60) AS billed_duration, d.provider_name, c.rate_type, c.billed_cost ".
			"FROM unmatched_calls c ".
			"LEFT JOIN user_contacts uc ON (c.phonenumber = uc.phonenumber ) ".
			"LEFT JOIN provider_details d ON (c.provider_id = d.provider_id) ".
			"WHERE 1=1 " . $timeperiod;
		$result = mysql_query( $query, $this->dbh);
		if (mysql_errno() != 0){
			print mysql_error();
			die();	
		}
		if (mysql_num_rows($result) > 0){
			$table_headers = array(
				'Nr.',
				'Datum / Uhrzeit',
				'Telefonnummer',
				'Identity',
				'Dauer',
				'Provider',
				'Tariftyp',
				'Kosten'
			);
			print "<h2>Achtung: Folgende kostenpflichtige Verbindungen sind nicht im Protokoll gefunden worden.</h2>";
			print $this->getTableContent($table_headers, $result, "");
		}
		
		$users = array();
		$user_cols_string = "";
		$sum_user_cols_string = "";
		$user_cols_count = 1;
		if ($user_cols === true){
			//get user list from db
			$result = mysql_query( 'SELECT id, username from users', $this->dbh );
			while ($row = mysql_fetch_assoc($result)) {
				$users[$row['id']] = $row['username'];
			}
			//add user specific cols to sql select statement
			foreach ($users as $uid=>$username) {
				$user_cols_string .= ", IF( uc.related_user = ".intval($uid).", CONCAT(c.billed_cost, ' EUR'), '') AS user_col_". intval($uid);
				$sum_user_cols_string .= ", CONCAT( FORMAT( SUM( IF( uc.related_user = ".intval($uid).", c.billed_cost, 0 )), 2), ' EUR') AS user_col_". intval($uid);
			}
			$user_cols_count = count($users);
		}
		else{
			$user_cols_string .= ", u.username as username";
		}
		
		$from = 
			" FROM callprotocol c ".
			"LEFT JOIN provider_details d ON (c.provider_id = d.provider_id) ".
			"LEFT JOIN provider_rate_types prt ON (c.provider_id = prt.provider_id AND c.rate_type = prt.rate_type ) ".
			"LEFT JOIN user_contacts uc ON (c.phonenumber = uc.phonenumber ) ".
			"LEFT JOIN users u ON (uc.related_user = u.id ) ".
			"";
		
		$where = 
			" WHERE c.calltype='3'".$timeperiod;
		 		
		$query = 
			"SELECT".
			" DATE_FORMAT(c.date,'%d. %H:%i:%s') AS date".
			", c.phonenumber".
			", uc.identity".
			", c.estimated_duration".
			", CEIL(c.billed_duration/60) AS billed_duration".
			", d.provider_name".
			", c.rate_type" .
			", IF( ABS( prt.price_per_minute * CEIL( c.billed_duration / 60 ) - c.billed_cost ) = 0, 'OK', 'FEHLER') AS xcheck ".
			", IF( ISNULL(u.username), CONCAT(c.billed_cost, ' EUR'), '') AS unbooked_calls";
		
		if ($user_cols === false){
			$query .= ", CONCAT(c.billed_cost, ' EUR') AS billed_cost";
		}
		
		$query .=		
			$user_cols_string.
			$from.
			$where;
			
	//		print $query . CR;
		$sum_query = 
			"SELECT".
			" SUM(c.estimated_duration) AS sum_estimated_duration".
			", CEIL(SUM(c.billed_duration)/60) AS sum_billed_duration".
			", CONCAT(FORMAT(SUM(c.billed_cost),2),' EUR') AS sum_cost" .
			", CONCAT( FORMAT( SUM( IF( ISNULL(u.username), CONCAT(c.billed_cost, ' EUR'), 0)), 2), ' EUR') AS unbooked_costs". 
			$sum_user_cols_string. 
			$from.
			$where;
			
			//print $sum_query. CR;
		
		$table_headers = array(
			'Nr.',
			'Datum / Uhrzeit',
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
			foreach ($users as $uid=>$username) {
				$table_headers[] = $username;
			}	
		}
		else{
			$table_headers[] = 'Kosten'; 		
			$table_headers[] = 'User';
		}
	
		print "<h2>Outgoing calls</h2>";
		
		if ($this->printview === false && $all === false){ 
			print "<p><a href=\"index.php?category=".CATEGORY_MONTHLY_BILLS."&printview=1&y=".$year."&m=".$month."\" target=\"_blank\">Print view</a></p>";
		}
		//execute sum query and get sum row
		$result = mysql_query( $sum_query, $this->dbh );
		if (mysql_errno() != 0){
			print mysql_error();
			die();	
		}
		$row = mysql_fetch_assoc($result);
		$amount_total = $row["sum_cost"];
		$amount_unbooked = $row["unbooked_costs"];
		$amount_all_users = 0;
		$sum_row_content .=  '<tr class="sum-row">'.CR.
			'<td colspan="4"></td>'.CR.
			'<td class="right">'.$row["sum_estimated_duration"].'</td>'.CR.
			'<td class="right">'.$row["sum_billed_duration"].'</td>'.CR.
			'<td colspan="3"></td>'.CR.
			'<td class="right">'.$amount_unbooked.'</td>'.CR;
		
		if ($user_cols === true){
			foreach ($users as $uid=>$username) {
				$users_costs = $row["user_col_". $uid];
				$sum_row_content .= '<td class="right">'.$users_costs.'</td>'.CR;
				$amount_all_users += floatval($users_costs);
			}	
		}
		else{
			$sum_row_content .= 
				'<td class="right">'.$amount_total.'</td>'.CR.		
				'<td></td>'.CR;
		}
		
		$sum_row_content .= '</tr>';
	
		$result = mysql_query( $query, $this->dbh );
		print $this->getTableContent($table_headers, $result, $sum_row_content);
		
		if ($user_cols === true){	
			$check = round( floatval($amount_total) - $amount_all_users - floatval($amount_unbooked) , 2);
			print "<p>Total costs: <b>".$amount_total."</b></p>";
			print "<p>Cost Check: " . $amount_total . " - " . $amount_all_users . " EUR - ". $amount_unbooked . " = <b>" . $check . " EUR</b> / <b>". ($check != 0 ? 'CHECK UNSUCCESSFUL!' : "CHECK OK")."</b></p>";
		}
		
		
		if ($this->printview === false){ 
			print "<h2>Eingehende Anrufe</h2>";
			$table_headers = array(
				'Nr.',
				'Datum / Uhrzeit',
				'Telefonnummer',
				'Identit&auml;t',
				'Dauer<br/>Sch&auml;tzung',
				'vermittelnder Provider',
			);
			$query = 
				"SELECT c.date, c.phonenumber, uc.identity, c.estimated_duration, c.providerstring FROM callprotocol c ".
				"LEFT JOIN user_contacts uc ON (c.phonenumber = uc.phonenumber ) ".
				"WHERE calltype='1'".$timeperiod;
			//print $query;
			$result = mysql_query( $query, $this->dbh );
			print $this->getTableContent($table_headers, $result, "");
		}
		
	}
	
	/*
	 * 
	 * 
	 */
	private function getTableContent($table_headers, $result, $sum_row_content){
		$nr = 1;
		$table_content = '<table>'.CR.'<tr>'.CR;
		foreach ($table_headers as $header){
			$table_content .= '<th>'.$header.'</th>'.CR;
		}
		$table_content .= '</tr>'.CR;
		while ($row = mysql_fetch_assoc($result)) {
			$table_content .= '<tr>'.CR.'<td class="right">'.$nr++. '</td>'.CR;
			foreach ($row as $key=>$value){
				$attributes = "";
				if (stristr($key,"cost") !== false || stristr($key, "duration") !== false || stristr($key, "sum") !== false){
					$attributes = ' class="right"';
				}
				if ($key == "xcheck"){
					if ($value == "FEHLER"){
						$attributes = ' class="error"';
					}
					else{
						$attributes = ' class="correct"';
					}
				}
				if ($key == 'username' && $value == ''){
					$attributes = ' class="error"';
					$value = 'unbooked';
				}
				if ($key == 'unbooked_calls' && floatval($value) > 0){
					$attributes = ' class="error"';
				} 
				
				$table_content .= "<td$attributes>" . htmlspecialchars($value) . "</td>".CR;
		    }
			$table_content .= "</tr>".CR;
		}
		$table_content .= $sum_row_content."</table>";
		return $table_content;
	}

} //end of class

$hf = new htmlFrontend();


?>