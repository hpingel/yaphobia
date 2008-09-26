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

class dusnetRemote {
	
	var $dusnet_remote,
		$parsed_array;
	
	/*
	 * constructor
	 */
	function __construct(){	
		$this->dusnet_remote = new curllib();
		$this->dusnet_remote->setBaseUrl("https://my.dus.net/");
		$this->dusnet_remote->enableCookieJar( YAPHOBIA_COOKIEJAR_DIR . 'cookiejar_dusnet.txt' );
		$this->parsed_array = array();
	}
	
	/*
	 * logon to my.dus.net
	 * @username
	 * @password
	 */
	public function logon($user, $password){
		$comment = "logon to mydusnet";
		$response = $this->dusnet_remote->postRequest(
			$comment, 
			"login=$user&password=$password&submit=Login", 
			"login.php"
		);
		//print $response;
		//normal kommt dann redirect to https://my.dus.net/xp/
	}
	
	/*
	 * logout from my.dus.net
	 */
	public function logout(){
		$comment = "logoff from mydusnet";
		$response = $this->dusnet_remote->getRequest(
			$comment, 
			"logout.php"
		);
	}

	/*
	 * downloads all calls billed for the current month
	 * and adds them to array $this->parsed_array
	 * @sipAccount
	 */
	public function collectCallsOfCurrentMonth($sipAccount){
		$this->collectArchiveCalls(
			$sipAccount,
			'01',
			date('m', time()),
			date('Y', time()),
			date('d', time()),
			date('m', time()),
			date('Y', time())
		);
		$this->collectLatestCalls($sipAccount);
	}
	
	/*
	 * downloads all calls for a given timespan
	 * and adds them to array $this->parsed_array
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
		$rawdata = $this->dusnet_remote->postRequest(
			$comment, 
			$postvalues, 
			"voip_access/evn.php"
		);
		//print $response;
							
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
		
		open office calc erwartet sipgate-Format:
		Datum	Nummer	Tarif	Dauer	Minuten	Kosten
		*/
		
		if (preg_match_all( $pattern, $rawdata, $hits2) === false){
			print "Keine Treffer in Zeilen.\n";
		}
		else{
			//print_r($hits);
			$hits = array_reverse($hits2[0]);
			foreach ($hits as $hit){
				if (preg_match_all  ( $pattern_data, $hit, $data) === false){
					print "Keine Treffer in Zellen.\n";
				}
				else{
					if (count($data[2]) != 9){
						print "Fehler: Es werden neun Datenelemente erwartet!";
					} 
					$data = $data[2];
					//print_r($data);
					 
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
						print "Fehler: Angabe der Dauer ist weder mm:ss noch hh:mm:ss.\n";
						$hours   = 0;
						$minutes = 0;
						$seconds = 0;
						$durationstring = "?";
					}
					
					$this->parsed_array[] = array(
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
			//print_r($this->parsed_array);
			
		}
	}
	
	function getCallerListArray(){
		return $this->parsed_array;
	}
	
	function getCsvData(){
		$csvdata = "";
		
		foreach ($this->parsed_array as $calldata){
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