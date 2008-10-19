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

/*
 * dusnetRemote
 */

final class dusnetRemote extends billingProviderPrototype{
	
	/*
	 * constructor
	 */
	function __construct($sipAccount, $traceObj){	
		parent::__construct("dus.net", "https://my.dus.net/", $traceObj);
		$this->handleSessionCookies();
		$this->setCreditRegex('/<tr.*?><td><IMG.*?><\/td><td>.*?<\/td><td>(.*?EUR.*?)<\/td><\/tr>/');
		$this->describeStandardRequests(
			array(
				FR_TASK_LOGON => array(
					FR_TYPE     => FR_TYPE_POST,
					FR_PATH     => "login.php",
					FR_POSTVARS => "login=[[USER]]&password=[[PASSWORD]]&submit=Login",
				),
				FR_TASK_LOGOUT => array(
					FR_TYPE     => FR_TYPE_GET,
					FR_PATH     => "logout.php"
				),
				FR_TASK_GETEVNOFMONTH => array(
					//FIXME: in case the month selected is not the current month, it makes no sense to get the last three days!
					array(
						FR_COMMENT  => "get evn data from last 3 days",
						FR_TYPE     => FR_TYPE_POST,
						FR_POSTVARS => "sip=$sipAccount&submit=aktualisieren",
						FR_PATH     => "voip_access/evn.php"
					),
					array(
						FR_COMMENT  => "get evn data from [[YEAR]]-[[MONTH]]-01 to [[YEAR]]-[[MONTH]]-31",
						FR_TYPE     => FR_TYPE_POST,
						FR_POSTVARS => "startday=01&startmonth=[[MONTH]]&startyear=[[YEAR]]&endday=31&endmonth=[[MONTH]]&endyear=[[YEAR]]&sip=$sipAccount&archiv=Archiv",
						FR_PATH     => "voip_access/evn.php"
					)
				),
				FR_TASK_GETCREDIT => array(
					FR_TYPE     => FR_TYPE_GET,
					FR_PATH     => "xp/index.php"
				)
			)
		);		
	}
	
	/*
	 * downloads all calls for a given timespan
	 * and adds them to array $this->callerList
	 * @sipAccount
	 * ...
	 */	
	public function collectArchiveCalls($sipAccount,$startD,$startM,$startY,$endD,$endM,$endY){
		$comment = "get evn data from $startY-$startM-$startD to $endY-$endM-$endD";
		$postvalues = "startday=$startD&startmonth=$startM&startyear=$startY&endday=$endD&endmonth=$endM&endyear=$endY&sip=$sipAccount&archiv=Archiv";
		$this->collectCalls($sipAccount,$postvalues, $comment);
	}
	
	/*
	 * last three days always have to be obtained separately
	 */
	public function collectLatestCalls($sipAccount){
		$comment = "get evn data from last 3 days";
		$postvalues = "sip=$sipAccount&submit=aktualisieren";
		$this->collectCalls($sipAccount,$postvalues, $comment);
	}
	
	private function collectCalls($sipAccount,$postvalues, $comment){	
		//$sipAccount can also be "alle"
/*		$this->callerString .= $this->postRequest(
			$comment, 
			$postvalues, 
			"voip_access/evn.php"
		);*/
		$this->callerString .= $this->executeFlexRequest(
			array(
				FR_COMMENT  => $comment,
				FR_TYPE     => FR_TYPE_POST,
				FR_POSTVARS => $postvalues,
				FR_PATH     => "voip_access/evn.php"
			)
		);
		
	}
		
	private function createCallerListArray(){	
		$pattern = "/<tr class=\'(even|odd)'>.*?<\/tr>/s";
		$pattern_data = "/<td(| align=\"right\")>(.*?)<\/td>/s";
		
		/*
		Datum	
		CLID	
		Anschluss	
		Ziel	
		SIP-User	
		Dauer	
		Länge [Dies ist die Basis der Berechnung] 	
		Kosten in Cent [Abgerechnet laut Tarif] 	
		Zielnetz
		
		*/
		
		if (preg_match_all( $pattern, $this->callerString, $hits2) === false){
			$this->trace .=  "STRANGE: No match in rows - no calls.\n";
		}
		else{
			//$this->trace .= print_r($hits, true);
			$hits = array_reverse($hits2[0]);
			foreach ($hits as $hit){
				if (preg_match_all  ( $pattern_data, $hit, $data) === false){
					$this->trace .= "STRANGE: No match in table cells - no calls.\n";
				}
				else{
					if (count($data[2]) != 9){
						$this->trace .= "ERROR: 9 items are expected!";
					} 
					$data = $data[2];
					//$this->trace .= print_r($data, true);
					 
					$durationarray = explode(":",$data[6]);
					if (count($durationarray) == 2){
						$hours   = 0;
						$minutes = $durationarray[0];
						$seconds = $durationarray[1];
						$durationstring = '00:' . $data[6];
					}
					elseif (count($durationarray) == 3){
						$hours   = $durationarray[0];
						$minutes = $durationarray[1];
						$seconds = $durationarray[2];
						$durationstring = $data[6];
					}
					else{
						$this->trace .=  "ERROR: Duration format should either be mm:ss or hh:mm:ss.\n";
						$hours   = 0;
						$minutes = 0;
						$seconds = 0;
						$durationstring = "?";
					}
					
					$this->callerList[] = array(
						"Datum" => $data[0],	
						"Nummer" => $data[3],	
						"Tarif" => $data[8],
						"Dauer" => $durationstring,
						"DauerInSekunden" => intval($hours) * 3600 + intval($minutes) * 60 + intval($seconds),
						"Minuten" => intval($hours) * 60 + intval($minutes) + ((intval($seconds) > 0)?1:0) , //manually
						"Kosten" => strtr(floatval(strtr($data[7],',','.')) / 100, '.',',') . "€"//in cent
					);
				}
			}
			//$this->trace .= print_r($this->callerList, true);
			
		}
	}
	
	/*
	 * returns an multidimensional array containing all collected calls
	 */
	public function getCallerListArray(){
		$this->createCallerListArray();
		return $this->callerList;
	}
	
	/*
	 * returns all collected calls in form of a csv file
	 */
	public function getCsvData(){
		$csvdata = "";
		
		foreach ($this->callerList as $calldata){
			foreach ($calldata as $name=>$element){
				$csvdata .= $element;
				if ($name != "Kosten"){ //last element
					$csvdata .= ";";	
				}	
				else{
					$csvdata .= "\n";	
				}
			}
		}
		return $csvdata;
	}

}


?>