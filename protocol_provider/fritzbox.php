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
 * fritzBoxRemote
 * 
 * 
 */

class fritzBoxRemote extends curllib {
	
	var $callerList;
	var $callerString;
	
	function __construct($traceObj){
		parent::__construct($traceObj);
		$this->callerList = array();	
		$this->callerString = "";	
		$this->setBaseUrl("http://".FRITZBOX_HOSTNAME."/");
		//$this->enableCookieJar( YAPHOBIA_COOKIEJAR_DIR . 'cookiejar_fritzbox.txt' );		
	}

	public function logon($password){
		$comment = "logon to fritzbox";
		$response = $this->postRequest(
			$comment, 
			"getpage=../html/de/menus/menu2.html&errorpage=../html/index.html&var:lang=de&var:pagename=home&var:menu=home&login:command/password=$password", 
			"/cgi-bin/webcm?getpage=../html/index_inhalt.html"
		);
		//$this->trace .= $response;
	}

	public function logout(){
		$comment = "logoff from fritzbox";
		/*
		 * hmmm... there is no logout button 
		 *
		$response = $this->getRequest(
			$comment, 
			"???"
		);*/
	}	
	
	public function loadCallerListFromBox(){

		//downloading the caller list without refreshing the caller list before
		//can lead to a caller list where the latest calls are still missing
		
		$comment = "refresh fritzbox caller list";
		$response = $this->getRequest(
			$comment, 
			"cgi-bin/webcm?".
			"getpage=..%2Fhtml%2Fde%2Fmenus%2Fmenu2.html".
			"&errorpage=..%2Fhtml%2Fde%2Fmenus%2Fmenu2.html".
			"&var%3Alang=de".
			"&var%3Apagename=foncalls".
			"&var%3Aerrorpagename=foncalls".
			"&var%3Amenu=home".
			"&var%3Apagemaster=".
			"&time%3Asettings%2Ftime=1222459630%2C-120".
			"&var%3Ashowall=".
			"&var%3AshowStartIndex=0".
			"&var%3AshowDialing=".
			"&var%3AtabFoncalls=".
			"&var%3APhonebookEntryNew=".
			"&var%3APhonebookEntryXCount=".
			"&var%3APhonebookEntryNumber=".
			"&telcfg%3Asettings%2FUseJournal=1"
		);
			
		$comment = "load fritzbox caller list";
		$response = $this->binaryTransfer(
			$comment,
			"cgi-bin/webcm?getpage=..%2Fhtml%2Fde%2FFRITZ%21Box_Anrufliste.csv" 
		);
		//$this->trace .= $response;
		
		$this->callerString .= $response;
	}	

	public function loadCallerListsFromDir($dir){
		$this->trace .= "load fritzbox caller lists from local directory";
		$dircontent = scandir($dir);
		foreach ($dircontent as $file){
			if ($file != "." && $file!= ".."){
				$this->loadCallerListFromFile($dir . "/". $file);
			}
		}
	}		

	public function loadCallerListFromFile($file){
		$this->callerString .= file_get_contents( $file );
	}
	
	
	public function getCallerListString(){
		return $this->callerString;
	}
	
	
	public function getCallerListArray(){
		$responselines = explode("\n", $this->callerString);
		
		foreach($responselines as $line){
			$details = split(";",$line);
			if (count($details) == 7 && $details[0] != "Typ"){
				$this->callerList[] = $details;
			}
			else{
				$this->trace .= "Line '$line' was skipped because it doesn't represent the expected call format.\n";
			}
		}
		return $this->callerList;
	}
	
}


?>