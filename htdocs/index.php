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


//check for show stoppers:
//1) presence of settings file
//2) correct authentication 
//3) mandatory configuration constants

define( 'PATH_TO_SETTINGS', str_replace("htdocs","",dirname(__FILE__)) . 'config/settings.php' );
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
	
</head>
	<body>

		<div class="content_wrap">

<?php

define('CATEGORY_LOGOUT', 100);

$category_menu = array(
	1 => 'Monatsrechnungen',
	2 => 'Tarifcheck',
	3 => 'Buchungscheck',
	4 => 'Jahres&uuml;berblick',
	5 => 'Weitere Statistiken',
	6 => 'Datenimport',
	7 => 'Konfigurations-Check'
);


if ( AUTHENTICATION_ENABLED ){
	$category_menu[CATEGORY_LOGOUT] = 'Logout';
}

$cat = isset($_REQUEST["category"]) ? intval($_REQUEST["category"]) : 1;

print '<div class="yaph_header"><h1>Yaphobia</h1>';

if (!defined('CLOSE_GATE') && ($cat != CATEGORY_LOGOUT) || !AUTHENTICATION_ENABLED ) {
	
	print '<p class="category_menu">';
	foreach ($category_menu as $id=>$desc){
		$class= ($id == $cat)? ' class="active"' : '';
		print '<a'.$class.' href="?category='.$id.'">'.$desc.'</a> ';
	}
	print "</p>";
}

print "</div><br/>";

//output unsuccessful check for settings file
if (defined('CLOSE_GATE')){
	print(CLOSE_GATE . "<br/>");
}
else{
	$db = new dbMan();
	$dbh = $db->getDBHandle();
	actions($cat, $dbh);	
}

function actions($category, $dbh){

	//check if yaphobia database is empty. if it is empty, offer to create all tables
	$query="SHOW TABLES";
	$result = mysql_query( $query, $dbh );
	if (mysql_num_rows($result) === 0){
		print '<div class="welcome"><h2>Welcome!</h2><p>It seems that your MySQL database is completely empty.<br/>You are probably running Yaphobia for the first time.</br> '.
			'Needed database tables will be created in the database now...</p><pre></div>';
		$ih = new installHelpers();
		$ih->createDBTables($dbh);	
		print "</pre>";
		$category = 0;
	}

	//check if yaphobia callprotocol table is empty
	$query="SELECT COUNT(*) as calls FROM callprotocol";
	$result = mysql_query( $query, $dbh );
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
	
	if ($category == 1){
		$month = intval($_REQUEST["m"]);
		$year = intval($_REQUEST["y"]);
		getMonthlyReport($month,$year, false, $dbh);
	}
	elseif ($category == 2){
		$query="SELECT pd.provider_name, prt.rate_type, concat( prt.price_per_minute, ' EUR') FROM provider_rate_types prt LEFT JOIN provider_details pd ON (prt.provider_id = pd.provider_id)";
		$result = mysql_query( $query, $dbh );
		$table_headers = array(
			'Nr.',
			'Provider',
			'Tarif',
			'Preis pro Minute',
			);
		print "<h1>Tarife der Provider</h1>";
		print getTableContent($table_headers, $result, "");
		getMonthlyReport(0,0, true, $dbh);
				
	}
	elseif ($category == 3){
		$query="SELECT c.date, c.phonenumber, c.identity, c.estimated_duration, d.provider_name  FROM callprotocol c LEFT JOIN provider_details d ON (c.provider_id=d.provider_id) WHERE c.provider_id > 0 AND c.calltype=3 AND ISNULL(c.billed)";
		$result = mysql_query( $query, $dbh );
		$table_headers = array(
			'Nr.',
			'Datum / Uhrzeit',
			'Telefonnummer',
			'Identit&auml;t',
			'Dauer<br/>Sch&auml;tzung',
			'Provider'
			);
		print "<h1>Buchungscheck: Protokollierte kostenpflichtige Anrufe, die keinen Buchungsdatensatz haben</h1>";
		print getTableContent($table_headers, $result, "");
		
	}
	elseif ($category == 4){

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
		
		$result = mysql_query( $query, $dbh );
		print "<h1>2008: Overview ($timestep_level)</h1>";
		print getTableContent($table_headers, $result, "");

		//52 wochen
		$timestep_level = "WEEKOFYEAR";
		$idlimit = 52;
		$datedisplay = "monthname(cpt.date)";
		
		$query="SELECT cdm.id, ".
			"$datedisplay, CONCAT(count(cpt.date), ' Gespraeche') AS sumofcalls, $totalduration, $totalbilledduration, $totalcosts ".
			"FROM calendar_dummy_data cdm LEFT JOIN callprotocol cpt ON cdm.id = $timestep_level(cpt.date) AND YEAR(cpt.date)=$year ".
			"WHERE cdm.id <= $idlimit ".
			"GROUP BY cdm.id";
		
		$result = mysql_query( $query, $dbh );
		print "<h1>2008: Overview ($timestep_level)</h1>";
		print getTableContent($table_headers, $result, "");	
		
		//365 tage
		$timestep_level = "DAYOFYEAR"; 
		$idlimit = 365; 
		$datedisplay = "CONCAT(dayofmonth(cpt.date), '. ', monthname(cpt.date))";  // achtung: schaltjahr!!!
		
		$query="SELECT cdm.id, ".
			"$datedisplay, CONCAT(count(cpt.date), ' Gespraeche') AS sumofcalls, $totalduration, $totalbilledduration, $totalcosts ".
			"FROM calendar_dummy_data cdm LEFT JOIN callprotocol cpt ON cdm.id = $timestep_level(cpt.date) AND YEAR(cpt.date)=$year ".
			"WHERE cdm.id <= $idlimit ".
			"GROUP BY cdm.id";
		
		$result = mysql_query( $query, $dbh );
		print "<h1>2008: Overview ($timestep_level)</h1>";
		print getTableContent($table_headers, $result, "");
		
	}
	elseif ($category == 5){
		
		$query="SELECT cpt.identity, cpt.phonenumber, $totalduration FROM callprotocol cpt where cpt.calltype != 3 GROUP BY cpt.phonenumber ORDER BY SUM(cpt.estimated_duration) DESC LIMIT 20";
		$result = mysql_query( $query, $dbh );
		$table_headers = array(
			'Nr.',
			'Identitaet',
			'Telefonnummer',
			'Summe Gespraechsdauer'
			);
		print "<h1>Incoming calls length: Show people that like to talk to us (sorted by total call length)</h1>";
		print getTableContent($table_headers, $result, "");	
		
		$query="SELECT cpt.identity, cpt.phonenumber, $totalduration, $totalcosts ".
			"FROM callprotocol cpt ".
			"GROUP BY cpt.phonenumber ".
			"ORDER BY SUM(cpt.estimated_duration) DESC LIMIT 20";
		$result = mysql_query( $query, $dbh );
		$table_headers = array(
			'Nr.',
			'Identitaet',
			'Telefonnummer',
			'Summe Gespraechsdauer',
			'Summe Gespraechskosten'
			);
		print "<h1>Incoming and outgoing calls: Most popular communication partners (sorted by total call length)</h1>";
		print getTableContent($table_headers, $result, "");

		$query="SELECT cpt.identity, cpt.phonenumber, $totalcosts, $totalduration FROM callprotocol cpt ".
			"WHERE cpt.calltype = 3 ".
			"GROUP BY cpt.phonenumber ".
			"ORDER BY SUM(cpt.billed_cost) DESC LIMIT 20";
		$result = mysql_query( $query, $dbh );
		$table_headers = array(
			'Nr.',
			'Identitaet',
			'Telefonnummer',
			'Summe Gespraechskosten',
			'Summe Gespraechsdauer'
			);
		print "<h1>Outgoing calls: Most expensive communication partners</h1>";
		print getTableContent($table_headers, $result, "");
		
	}
	elseif ($category == 6){

		$importType = isset($_REQUEST['import_type']) ? intval($_REQUEST['import_type']) : 0;
		if ( $importType == 0){
			print '<h2><a href="?category=6&import_type=1">Anrufliste aus Fritzbox importieren</a></h2>';
			if (SIPGATE_ACTIVE)
				print '<h2><a href="?category=6&import_type=2">EVN des aktuellen Monats von sipgate importieren</a></h2>';
			if (DUSNET_ACTIVE)
				print '<h2><a href="?category=6&import_type=3">EVN des aktuellen Monats von dus.net importieren</a></h2>';
		}
		else{
		}

		$tr = new trace('html');
		$call_import = new callImportManager($dbh, $tr);
				
		if ( $importType == 1){
			print '<h2>Anrufliste aus Fritzbox importieren</h2>';
			print "<pre>" . htmlspecialchars($call_import->getFritzBoxCallerList()) ."</pre>";
		}
		elseif ( $importType == 2 && SIPGATE_ACTIVE){
			print '<h2>EVN des aktuellen Monats von sipgate importieren</h2>';
			$sg_callist = $call_import->getSipgateCallsOfCurrentMonth( SIPGATE_USERNAME, SIPGATE_PASSWORD);
			$call_import->putSipgateCallArrayIntoDB($sg_callist, SIPGATE_PROVIDER_ID);
			print "<pre>" . htmlspecialchars( $call_import->getTrace() ) . "</pre>";
					}
		elseif ( $importType == 3 && DUSNET_ACTIVE){
			print '<h2>EVN des aktuellen Monats von dus.net importieren</h2>';
			$dusnet_callist = $call_import->getDusNetCalls( DUSNET_SIPACCOUNT, DUSNET_USERNAME, DUSNET_PASSWORD );
			$call_import->putDusNetCallArrayIntoDB($dusnet_callist, DUSNET_PROVIDER_ID);
			print "<pre>" . htmlspecialchars( $call_import->getTrace() ) . "</pre>";
			
		}
	}
	elseif ( $category == 7 ){
		print "<p>This check can help you to find problems in your setup</p>";
		$ih = new installHelpers();
		$sermon = $ih->proofreadOptionalSettings();
		print '<div class="welcome" style="text-align: left;"><h1>Checking optional configuration parameters</h1>' . $sermon . '</div>';
	}
	elseif ( $category == CATEGORY_LOGOUT && AUTHENTICATION_ENABLED){
		session_unregister('AUTHENTICATED'); //logout
		session_unregister('MULTIPLE_LOGIN_ATTEMPT');
		print '<div class="welcome"><h1>Logout successful.</h1>'.
			'<p><a href="index.php">Login again</a></p></div>';
	}
}

/*
 * creates html form with month selector
 * 
 */
function monthpicker($month, $year, $cat_id){
	$months = array(
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
	print '<form name="monthpicker" action="index.php" method="post">'."\n";
	
	print '<div class="date_nav">';
	if ($month > 1)
		print '<a class="date_nav" href="?m='.($month-1 ) . '&y='.$year.'&category='.$cat_id.'">&lt; '.$months[$month -2 ].'</a> | ';
	print '<span class="date_nav_active">'.$months[ $month -1  ].'</span>';
	if ($month < 12)
		print ' | <a class="date_nav" href="?m='.($month +1) . '&y='.$year.'&category='.$cat_id.'">'.$months[intval($month) ].' &gt;</a> ';
	print '</div>';
		
	print '<br/><select name="m">'."\n";
	for ($z=0; $z < count($months); $z++){
		$selected = ($month == ($z + 1)) ? 'selected="selected"' : ""; 
		print '<option '.$selected.' value="'.($z+1).'">'.$months[$z].'</option>'. "\n";
	}
	print 
	'</select>
	<input type="hidden" name="category" value="'.$cat_id.'"/>
	<input type="submit" value="OK"/>';
	
		
	print '</form><br/>';
}

	
function getMonthlyReport( $month, $year, $all, $dbh){
	
	if ($all === false){
		$month = intval($month);
		if ($month == 0) $month = date('m', time());
		$month = ($month > 12 || $month < 1) ? 1 : $month; //default value: january
		$year = intval($year);
		if ($year == 0) $year = date('y', time());
		$year = ($year > 2100 || $year < 2007) ? 2008 : $year; //default value: january
		$timeperiod = " AND MONTH(c.date) = '".$month."' AND YEAR(c.date) = '".$year."'";
		monthpicker($month, $year, 1);
	}
	else{
		$timeperiod = "";
	}
	
	
	
	$where = 
		"FROM callprotocol c ".
		"LEFT JOIN provider_details d ON (c.provider_id = d.provider_id) ".
		"LEFT JOIN provider_rate_types prt ON (c.provider_id = prt.provider_id AND c.rate_type = prt.rate_type ) ".
//			"WHERE 1=1 ".$timeperiod;  // all calls
		"WHERE c.calltype='3'".$timeperiod;
	 		
	$query = 
		"SELECT c.date, c.phonenumber, c.identity, c.estimated_duration, ".
		"CEIL(c.billed_duration/60) AS billed_duration, ".
		"CONCAT(c.billed_cost, ' EUR') AS billed_cost, ".
		"d.provider_name, c.rate_type, " .
		"IF( ABS( prt.price_per_minute * CEIL( c.billed_duration / 60 ) - c.billed_cost ) = 0, 'OK', 'FEHLER') AS xcheck " .
		$where;
		
//		print $query . CR;
	$sum_query = 
		"SELECT SUM(c.estimated_duration) AS sum_estimated_duration, ".
		"CEIL(SUM(c.billed_duration)/60) AS sum_billed_duration, ".
		"CONCAT(FORMAT(SUM(c.billed_cost),2),' EUR') AS sum_cost " . 
		$where;
		
//		print $sum_query. CR;
	
	$table_headers = array(
		'Nr.',
		'Datum / Uhrzeit',
		'Telefonnummer',
		'Identit&auml;t',
		'Dauer<br/>Sch&auml;tzung',
		'Dauer<br/>Rechung',
		'Kosten',
		'Provider',
		'Tariftyp',
		'Check',
	);
	
	$timeframe = $all ? " (zeitlich unbegrenzt)" : " $month/$year";
	print "<h1>Telefonreport$timeframe</h1>";
	print "<h2>Abgehende Anrufe</h2>";

	//get sum row
	$result = mysql_query( $sum_query, $dbh );
	$row = mysql_fetch_assoc($result);
	$sum_row_content .=  '<tr class="sum-row">'.CR.
		'<td colspan="4"></td>'.CR.
		'<td class="right">'.$row["sum_estimated_duration"].'</td>'.CR.
		'<td class="right">'.$row["sum_billed_duration"].'</td>'.CR.
		'<td class="right">'.$row["sum_cost"].'</td>'.CR.
		'<td colspan="3"></td>'.CR."</tr>";

	$result = mysql_query( $query, $dbh );
	print getTableContent($table_headers, $result, $sum_row_content);
	
	print "<h2>Eingehende Anrufe</h2>";
	$table_headers = array(
		'Nr.',
		'Datum / Uhrzeit',
		'Telefonnummer',
		'Identit&auml;t',
		'Dauer<br/>Sch&auml;tzung',
		'vermittelnder Provider',
	);
	$query = "SELECT c.date, c.phonenumber, c.identity, c.estimated_duration, c.providerstring FROM callprotocol c WHERE calltype='1'".$timeperiod;
	//print $query;
	$result = mysql_query( $query, $dbh );
	print getTableContent($table_headers, $result, "");
	
}

function getTableContent($table_headers, $result, $sum_row_content){
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
			
			$table_content .= "<td$attributes>" . htmlspecialchars($value) . "</td>".CR;
	    }
		$table_content .= "</tr>".CR;
	}
	$table_content .= $sum_row_content."</table>";
	return $table_content;
}


?>
<hr />
<p><b>Yaphobia <?php print htmlspecialchars(YAPHOBIA_VERSION);?></b> - Yet Another Phone Bill Application - Licensed under GPL - Get it for free and contribute at <a href="https://sourceforge.net/projects/yaphobia/">https://sourceforge.net/projects/yaphobia/</a>!</p>

	</div>


	</body>


</html>	


