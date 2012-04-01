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
		$this->setCreditRegex('/<td.*?>(.*?EUR.*?)<\/td>/');
		$this->describeStandardRequests(
			array(
				self::FR_TASK_LOGON => array(
					self::FR_TYPE     => self::FR_TYPE_POST,
					self::FR_PATH     => "index.php",
					self::FR_POSTVARS => "login=[[USER]]&password=[[PASSWORD]]&action=Login&language=german",
				),
				self::FR_TASK_LOGOUT => array(
					self::FR_TYPE     => self::FR_TYPE_GET,
					self::FR_PATH     => "logout.php"
				),
				self::FR_TASK_GETEVNOFMONTH => array(
					//FIXME: in case the month selected is not the current month, it makes no sense to get the last three days!
					array(
						self::FR_COMMENT  => "get evn data from last 3 days",
						self::FR_TYPE     => self::FR_TYPE_POST,
						self::FR_POSTVARS => "startday=01&startmonth=[[MONTH]]&startyear=[[YEAR]]&endday=31&endmonth=[[MONTH]]&endyear=[[YEAR]]&sip=0&time1=archiv&csvcustomerid=$sipAccount&action=Auswahl+anfordern",
						self::FR_PATH     => "xn/listingcenter/accesslist.php"
					),
					array(
						self::FR_COMMENT  => "get evn data from [[YEAR]]-[[MONTH]]-01 to [[YEAR]]-[[MONTH]]-31",
						self::FR_TYPE     => self::FR_TYPE_POST,
						self::FR_POSTVARS => "startday=01&startmonth=[[MONTH]]&startyear=[[YEAR]]&endday=31&endmonth=[[MONTH]]&endyear=[[YEAR]]&sip=0&time1=archiv&csvcustomerid=$sipAccount&action=Auswahl+anfordern",
						self::FR_PATH     => "xn/listingcenter/accesslist.php"
					)
				),
				self::FR_TASK_GETCREDIT => array(
					self::FR_TYPE     => self::FR_TYPE_GET,
					self::FR_PATH     => "xn/news.php"
				)
			)
		);
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
				self::FR_COMMENT  => $comment,
				self::FR_TYPE     => self::FR_TYPE_POST,
				self::FR_POSTVARS => $postvalues,
				self::FR_PATH     => "voip_access/evn.php"
			)
		);

	}

	private function createCallerListArray(){
		$pattern = "/<tr class=\'(even|odd)'>.*?<\/tr>/s";
		$pattern_data = "/<td(| align=\"right\")>(.*?)<\/td>/s";

		/*
		alt und obsolet:

		0 Datum
		1 CLID
		2 Anschluss
		3 Ziel
		4 SIP-User
		5 Dauer
		6 Länge [Dies ist die Basis der Berechnung]
		7 Kosten in Cent [Abgerechnet laut Tarif]
		8 Zielnetz

		neu 2012:

		0 Datum
		1 Uhrzeit
		2 Anschluss "CLIP"
                3 <leer>
		4 Ziel
		5 Länge
		6 Kosten in Cent
                  <!-- ct -->
		7 Zielnetz

		*/

		if (preg_match_all( $pattern, $this->callerString, $hits2) === false){
			$this->trace .=  "STRANGE: No match in rows - no calls.\n";
		}
		else{
			//$this->trace .= print_r($hits, true);
			$hits = array_reverse($hits2[0]);
			foreach ($hits as $hit){
				if (preg_match_all  ( $pattern_data, $hit, $data) === false){
					$this->tr->addToTrace(3, "STRANGE: No match in table cells - no calls.");
				}
				else{
					if (count($data[2]) != 8){
						$this->tr->addToTrace(1, "ERROR: 8 items are expected!");
					}
					$data = $data[2];
					//$this->trace .= print_r($data, true);

					$durationarray = explode(":",$data[5]);
					if (count($durationarray) == 2){
						$hours   = 0;
						$minutes = $durationarray[0];
						$seconds = $durationarray[1];
						$durationstring = '00:' . $data[5];
					}
					elseif (count($durationarray) == 3){
						$hours   = $durationarray[0];
						$minutes = $durationarray[1];
						$seconds = $durationarray[2];
						$durationstring = $data[5];
					}
					else{
						$this->tr->addToTrace(1, "ERROR: Duration format should either be mm:ss or hh:mm:ss.");
						$hours   = 0;
						$minutes = 0;
						$seconds = 0;
						$durationstring = "?";
					}

					$date = explode( ".", trim($data[0]));

					$this->callerList[] = array(
						"Datum" => $date[2] . "-" . $date[1] . "-" . $date[0] . " " . trim($data[1]),
						"Nummer" => $data[4],
						"Tarif" => $data[7],
						"Dauer" => $durationstring,
						"DauerInSekunden" => intval($hours) * 3600 + intval($minutes) * 60 + intval($seconds),
						"Minuten" => intval($hours) * 60 + intval($minutes) + ((intval($seconds) > 0)?1:0) , //manually
						"Kosten" => strtr(floatval(strtr($data[6],',','.')) / 100, '.',',') . "€"//in cent
					);
				}
			}
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