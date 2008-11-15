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

define('CATEGORY_MONTHLY_BILLS', 	1);
define('CATEGORY_RATE_TYPE_CHECK', 	2);
define('CATEGORY_MATCH_CHECK', 		3);
define('CATEGORY_ANNUAL_OVERVIEW', 	4);
define('CATEGORY_MIXED_STATS', 		5);
define('CATEGORY_DATA_IMPORT', 		6);
define('CATEGORY_CONFIG_CHECK',		7);
define('CATEGORY_CONTACTS',			8);
define('CATEGORY_LOGOUT', 			100);

define(CR,"\n");

class htmlFrontend extends reports{
	
	private
		$printview,
		$cat,
		$months,
		$year,
		$month,
		$importType,		
		$tr;
	
	function __construct() {
		$this->tr = new trace('html');		
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
		
		//import request vars and sanititze them
		$this->cat 		= isset($_REQUEST['category']) ? intval($_REQUEST['category']) : 1;
		$this->printview= isset($_REQUEST['printview']) ? (intval($_REQUEST['printview']) == 1 ? true : false ) : false;
		$this->year 	= isset($_REQUEST['y']) ? intval($_REQUEST['y']) : date('Y', time());
		$this->month 	= isset($_REQUEST['m']) ? intval($_REQUEST['m']) : date('m', time());
		$this->importType = isset($_REQUEST['import_type']) ? intval($_REQUEST['import_type']) : 0;
				
		//range check (month value 0 is ok, means whole year)
		$this->month 	= ($this->month > 12 || $this->month < 0) ? date('m', time()) : $this->month;
		$this->year 	= ($this->year > 2100 || $this->year < 2000) ? date('Y', time()) : $this->year;
		
		$this->htmlHeader();		

		if ( AUTHENTICATION_ENABLED ){
			$category_menu[CATEGORY_LOGOUT] = 'Logout';
		}
		
		if ($this->printview === false){ 
			print '<div class="yaph_header"><h1>Yaphobia</h1>';
			
			if (!defined('CLOSE_GATE') && ($this->cat != CATEGORY_LOGOUT) || !AUTHENTICATION_ENABLED ) {
			
				$category_menu = array(
					CATEGORY_MONTHLY_BILLS 		=> 'Monatsrechnungen',
					CATEGORY_RATE_TYPE_CHECK 	=> 'Provider/Tarife',
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
			parent::__construct(); //connect to database
			$this->actions();	
		}

		$this->htmlFooter();
		
	} // end of constructor


	/*
	 * render the html header and open the html body
	 * 
	 */
	private function htmlHeader(){
		$printview_css = '';
		if ($this->printview){
			$printview_css = '	<link rel="stylesheet" type="text/css" href="themes/standard/printview.css" />' . CR;
		}
		//header("Content-type: text/xml"); //disabled until we solved some problems
		print 
			'<?xml version="1.0" encoding="utf-8"?>'.CR.
			'<!DOCTYPE html'.CR.
			'     PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"'.CR.
			'     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'.CR.
			'<html xmlns="http://www.w3.org/1999/xhtml">'.CR.
			//xmlns:svg="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"		
			'<head>'.CR.
			'	<title>Yaphobia ' . htmlspecialchars(YAPHOBIA_VERSION) . '</title>' . CR.
			'	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />'.CR.
			'	<link rel="stylesheet" type="text/css" href="themes/standard/styles.css" />'.CR.
			$printview_css.
			'</head>'.CR.
			'<body>'.CR.
			'	<div class="content_wrap">'.CR;
	}

	/*
	 * close the html body and render the footer
	 */
	private function htmlFooter(){
		print
			'<hr />'.CR.
			'<p><b>Yaphobia ' . htmlspecialchars(YAPHOBIA_VERSION) . '</b> - Yet Another Phone Bill Application - Licensed under GPL - Get it for free and contribute at <a href="https://sourceforge.net/projects/yaphobia/">https://sourceforge.net/projects/yaphobia/</a>!</p>'.CR.
			'</div></body></html>'.CR;
	}
	
	/*
	 * tries to react to users selection in menu (action) 
	 * 
	 */
	private function actions(){
	
		$category = $this->cat;
		//set config values to default values in case they are undefined
		//also collect protocol information about this in $sermonOptionalSettings  
		$ih = new installHelpers();
		$sermonOptionalSettings = $ih->proofreadOptionalSettings();
		
		//check if yaphobia database is empty. if it is empty, offer to create all tables
		if ($this->getNumberOfDBTables() === 0){
			print '<div class="welcome"><h2>Welcome!</h2><p>It seems that your MySQL database is completely empty.<br/>You are probably running Yaphobia for the first time.</br> '.
				'Needed database tables will be created in the database now...</p><pre></div>';
			$ih = new installHelpers();
			$ih->createDBTables($this->dbh);
			print "</pre>";
			$category = 0;
		}
		elseif ($this->getNumberOfDBTables() < 6){ //FIXME: improve this value with constant or something...
			print '<div class="welcome"><h2>Welcome!</h2><p>It seems that your MySQL database is missing some tables.<br/> '.
				'Needed database tables will be created in the database now...</p><pre></div>';
			$ih = new installHelpers();
			$ih->createDBTables($this->dbh);	
			print "</pre>";
			$category = 0;
		}
		//check if yaphobia callprotocol table is empty
		if ($this->callProtocolIsEmpty()){
			print '<div class="welcome"><h2>Welcome!</h2><p>It seems that the call protocol of Yaphobia is completely empty!<br/>You are probably running Yaphobia for the first time.</br> '.
				'Please import call protocol data into Yaphobia!</p></div>';
				$category = 6;
		}
		
		switch ($category){
		case CATEGORY_MONTHLY_BILLS:
			$this->getMonthlyReport(false, true);
			break;
			
		case CATEGORY_RATE_TYPE_CHECK:
			$this->outputSQLReport( $this->sqlRateTypes() );
			break;
			
		case CATEGORY_MATCH_CHECK:
			$this->outputSQLReport( $this->sqlUnmatchedOrphansInProtocol() );
			$this->outputSQLReport( $this->sqlUnmatchedBilledOrphans('') );
			break;
				
		case CATEGORY_ANNUAL_OVERVIEW:
			$this->month = 0; //force whole year
			$this->monthPickerForm('');
			print '<h1>'.$this->humanReadableTimeframe() . '<h1>';
			$this->outputSQLReport( $this->sqlAnnualOverview( 'MONTH',      $this->year ) );
			$this->outputSQLReport( $this->sqlAnnualOverview( 'WEEKOFYEAR', $this->year ) );
			$this->outputSQLReport( $this->sqlAnnualOverview( 'DAYOFYEAR',  $this->year ) );
			break;
			
		case CATEGORY_MIXED_STATS:
			$limit = 20;
			$this->monthPickerForm('');
			print '<h1>'.$this->humanReadableTimeframe() . '<h1>';
			$this->outputSQLReport( $this->sqlIncomingCallLength( $limit, $this->year, $this->month ));
			$this->outputSQLReport( $this->sqlPopularCommPartners( $limit, $this->year, $this->month ));			
			$this->outputSQLReport( $this->sqlMostExpensiveCommPartners( $limit, $this->year, $this->month ));
			break;
			
		case CATEGORY_CONTACTS:
			$this->outputSQLReport( $this->sqlUserList());			
			$this->outputSQLReport( $this->sqlContactList());			
			break;
			
		case CATEGORY_DATA_IMPORT:
			if ( $this->importType == 0)
				$this->importManagerMenu();
			else
				$this->importManager();
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
	 * render the menu of the import manager
	 */
	protected function importManagerMenu(){
		define('IMPORT_LINK_START', '<h2><a href="?y='.date('Y', time()).'&m='.date('m', time()).'&category='.intval(CATEGORY_DATA_IMPORT).'&import_type=');
		define('IMPORT_LINK_END', '</a></h2>'); 
		print IMPORT_LINK_START . '1">Anrufliste aus Fritzbox importieren' . IMPORT_LINK_END;
		if (SIPGATE_ACTIVE){
			print "<fieldset><legend>sipgate</legend>";
			print IMPORT_LINK_START . '2">EVN des aktuellen Monats von sipgate importieren' . IMPORT_LINK_END;
			print "<p>EVN eines Monats importieren: ";
			$this->monthPickerForm( '<input type="hidden" name="import_type" value="2">' );
			print IMPORT_LINK_START . '3">Komplette EVN-Historie von sipgate importieren' . IMPORT_LINK_END;
			print "</p>";
			print "</fieldset>";
		}
		if (DUSNET_ACTIVE)
			print "<fieldset><legend>dus.net</legend>";
			print IMPORT_LINK_START . '4">EVN des aktuellen Monats von dus.net importieren' . IMPORT_LINK_END;
			print "<p>EVN eines Monats importieren: ";
			$this->monthPickerForm( '<input type="hidden" name="import_type" value="4">' );
			print "</p>";
			print "</fieldset>";		
	} 
	
	
	/*
	 * start import process depending on users choice
	 * 
	 */
	protected function importManager(){ 
		
		$call_import = new callImportManager($this->dbh, $this->tr);
				
		if ( $this->importType == 1){
			print '<h2>Anrufliste aus Fritzbox importieren</h2>';
			$call_import->getFritzBoxCallerList();
		}
		elseif ( $this->importType == 2 && SIPGATE_ACTIVE){
			print '<h2>EVN des Monats '.$this->month . '/'. $this->year . ' von sipgate importieren</h2>';
			$call_import->setMonth($this->month);
			$call_import->setYear($this->year);
			$sg_callist = $call_import->getSipgateCallsOfCurrentMonth( SIPGATE_USERNAME, SIPGATE_PASSWORD);
			$call_import->putSipgateCallArrayIntoDB($sg_callist, SIPGATE_PROVIDER_ID);
		}
		elseif ( $this->importType == 3 && SIPGATE_ACTIVE){
			print '<h2>Komplette EVN-Historie von sipgate importieren</h2>';
			//set month and year to empty values to persuade sipgate to return a complete call history
			$call_import->setMonth("");
			$call_import->setYear("");
			$sg_callist = $call_import->getSipgateCallsOfCurrentMonth( SIPGATE_USERNAME, SIPGATE_PASSWORD);
			$call_import->putSipgateCallArrayIntoDB($sg_callist, SIPGATE_PROVIDER_ID);
		}
		elseif ( $this->importType == 4 && DUSNET_ACTIVE){
			print '<h2>EVN des Monats '.$this->month . '/'. $this->year . ' von dus.net importieren</h2>';
			$call_import->setMonth($this->month);
			$call_import->setYear($this->year);				
			$dusnet_callist = $call_import->getDusNetCalls( DUSNET_SIPACCOUNT, DUSNET_USERNAME, DUSNET_PASSWORD );
			$call_import->putDusNetCallArrayIntoDB($dusnet_callist, DUSNET_PROVIDER_ID);
		}
		else{
			print '<h2>Error: Unknown importType value. Don\'t know what to do.</h2>';
		}
	}
			
	/*
	 * creates html form with month selector
	 * 
	 */
	private function monthFlicker(){
		
		if ($this->month != 0){
			define('CAT','&category='.$this->cat);
			
			print '<div class="date_nav">';
			if ($this->month > 1)
				print '<a class="date_nav" href="?m='.($this->month-1 ) . '&y='.$this->year.CAT.'">&lt; '.$this->months[$this->month -2 ].'</a> | ';
			elseif ($this->month == 1)
				print '<a class="date_nav" href="?m=12&y='.($this->year-1).CAT.'">&lt; '.$this->months[11].' ' . ($this->year-1) . '</a> | ';
				
			print '<span class="date_nav_active">'.$this->months[ $this->month -1  ].'</span>';
			if ($this->month < 12)
				print ' | <a class="date_nav" href="?m='.($this->month +1) . '&y='.$this->year.CAT.'">'.$this->months[intval($this->month) ].' &gt;</a> ';
			elseif ($this->month == 12)
				print ' | <a class="date_nav" href="?m=1&y='.($this->year+1).CAT.'">'.$this->months[0].' ' . ($this->year+1) . ' &gt;</a>';
			
			print '</div>';
		}
		$this->monthPickerForm('');
	
	}
	
	private function monthPickerForm( $hidden ){
		print '<form name="monthpicker" action="index.php" method="post">'."\n";
		print '<select name="m">'."\n";
		$wholeyear = "[ganzes Jahr]";
		print '<option '.(($this->month == 0) ? 'selected="selected"' : '').' value="0">'.$wholeyear.'</option>'. "\n";
		for ($z=0; $z < count($this->months); $z++){
			$selected = ($this->month == ($z + 1)) ? 'selected="selected"' : ""; 
			print '<option '.$selected.' value="'.($z+1).'">'.$this->months[$z].'</option>'. "\n";
		}
		print '</select>';
		
		$current_year = date('Y', time());
		print ' <select name="y">'."\n";
		for ($z = 2000; $z <= $current_year; $z++){
			$selected = ($this->year == $z ) ? 'selected="selected"' : ""; 
			print '<option '.$selected.' value="'.$z.'">'.$z.'</option>'. "\n";
		}
		print '</select>';
		
		print 
			'<input type="hidden" name="category" value="'.$this->cat.'"/>
			'.$hidden.'
			<input type="submit" value="OK"/>';
		
			
		print '</form><br/>';	
	}

	
	protected function humanReadableTimeframe(){
		if ($this->month != 0)
			$monthstring  = $this->month . '/';
		else
			$monthstring  = ''; // whole year 
		return htmlspecialchars( $monthstring . $this->year);
	}
	
	/*
	 * output monthly report
	 * $all (true / false)
	 * $user_cols (true / false)
	 */
	private function getMonthlyReport($all, $user_cols){
		if ($all === false){
			$timeperiod = $this->sqlWhereTimeperiod( 'c', $this->month, $this->year);
			if ($this->printview === false){ 
				$this->monthFlicker();
			}
			$timeframe = $this->humanReadableTimeframe();
		}
		else{
			$timeframe = '(zeitlich unbegrenzt)';
			$timeperiod = '';
		}
		
		print "<h1>Telefonreport $timeframe</h1>";
		$this->outputSQLReport( $this->sqlUnmatchedBilledOrphans( $timeperiod ));
		if ($this->printview === false && $all === false){ 
			print "<p><a href=\"index.php?category=".CATEGORY_MONTHLY_BILLS."&printview=1&y=".$this->year."&m=".$this->month."\" target=\"_blank\">Print view</a></p>";
		}
		//get sum row
		$sum_row_data = $this->sqlPhoneBillSumRow( $timeperiod, $user_cols );
		$row = $sum_row_data['table'][0];
		$amount = array(
			'total' => $row["sum_cost"],
			'unbooked' => $row["unbooked_costs"],
			'all_users' => 0
		);
		if ($user_cols === true){
			foreach ($this->getUserList() as $user) {
				$amount['all_users'] += floatval($row["user_col_". $user['id']]);
			}	
		}
		
		//get phone bill table
		$phone_bill_table = $this->sqlPhoneBill( $timeperiod, $user_cols );
		$phone_bill_table['sum_row'] = $this->getSumRowAsHTMLTableRow( $user_cols, $amount, $row );
		$this->outputSQLReport( $phone_bill_table );
		//output cost check
		if ($user_cols === true){	
			$check = round( floatval($amount['total']) - floatval($amount['all_users']) - floatval($amount['unbooked']) , 4);
			print "<p>Total costs: <b>".$amount['total']."</b></p>";
			print "<p>Cost Check: " . $amount['total'] . " - " . $amount['all_users'] . " EUR - ". $amount['unbooked'] . " = <b>" . $check . " EUR</b> / <b>". ($check != 0 ? 'CHECK UNSUCCESSFUL!' : "CHECK OK")."</b></p>";
		}
		if ($this->printview === false){
			$this->outputSQLReport( $this->sqlIncomingCalls( $timeperiod ));			
		}
	}
	
	/*
	 * returns a string with a sum table row to be added to a html table
	 */
	private function getSumRowAsHTMLTableRow( $user_cols, $amount, $row ){
		$sum_row_content =  '<tr class="sum-row">'.CR.
			'<td colspan="5"></td>'.CR.
			'<td class="right">'.$row['sum_estimated_duration'].'</td>'.CR.
			'<td class="right">'.$row['sum_billed_duration'].'</td>'.CR.
			'<td colspan="3"></td>'.CR.
			'<td class="right">'.$amount['unbooked'].'</td>'.CR;
		
		if ($user_cols === true){
			foreach ($this->getUserList() as $user) {
				$users_costs = $row["user_col_". $user['id']];
				$sum_row_content .= '<td class="right">'.$users_costs.'</td>'.CR;
			}	
		}
		else{
			$sum_row_content .= 
				'<td class="right">'.$amount['total'].'</td>'.CR.		
				'<td></td>'.CR;
		}
		return $sum_row_content . '</tr>';
	}	
		
	/*
	 * getTableContent
	 * 
	 */
	private function getTableContent($table_headers, $table, $sum_row_content){
		
		$nr = 1;
		$table_content = '<table>'.CR.'<tr>'.CR;
		foreach ($table_headers as $header){
			$table_content .= '<th>'.$header.'</th>'.CR;
		}
		$table_content .= '</tr>'.CR;
		foreach ($table as $table_row) {
			$table_content .= '<tr>'.CR.'<td class="right">'.$nr++. '</td>'.CR;
			foreach ($table_row as $key=>$value){
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

				if ($key == 'date' && (stristr($value,"Saturday") !== false || stristr($value,"Sunday") !== false ) > 0){
					$attributes = ' class="weekend"';
				} 
				
				$table_content .= "<td$attributes>" . htmlspecialchars($value) . "</td>".CR;
		    }
			$table_content .= "</tr>".CR;
		}
		$table_content .= $sum_row_content."</table>";
		return $table_content;
	}

	private function outputSQLReport( $data ){
		if (count($data['table']) > 0){
			print '<h1>'.$data['title'].'</h1>';
			$data['headers'] = array_merge( array('Nr.'), $data['headers']);  
			print $this->getTableContent($data['headers'], $data['table'], $data['sum_row']);
		}
	}
	
} //end of class

?>