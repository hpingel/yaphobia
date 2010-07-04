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
    
    var $callerList,
        $callerString,
        $tr,
        $sessionID;
    
    function __construct($traceObj){
        parent::__construct($traceObj);
        $this->callerList = array();
        $this->callerString = "";
        $this->setBaseUrl("http://".FRITZBOX_HOSTNAME."/");
        parent::enableCookieJar( YAPHOBIA_COOKIEJAR_DIR . 'cookiejar_fritzbox.txt' );
        $this->tr = $traceObj;
        $sessionID = "0000000000000000";
    }

    public function logon($password){
        $comment = "get challenge from fritzbox logon page";
        $response = $this->getRequest(
            $comment,
            "cgi-bin/webcm?getpage=../html/de/menus/menu2.html"
        );
        preg_match( "/var challenge = \"(.*?)\";/", $response, $match);
        $challenge = $match[1];
        $secondHalf =  $this->addZeroChars($challenge . "-" .$this->fritzBoxMakeDots($password));
        $challengeString = $challenge . "-" . md5( $secondHalf );

        $comment = "logon to fritzbox and get session id";
        $response = $this->postRequest(
            $comment, 
            "errorpage=../html/de/menus/menu2.html".
            "&getpage=../html/de/menus/menu2.html".
            "&login:command/response=".$challengeString.
            "&sid=".$this->sessionID. //still initial
            "&var:pagename=home".
            "&var:menu=home".
            "&var:pagemaster=".
            "&var:activtype=pppoe".
            "&var:tabInetstat=0".
            "&var:weckernr=",
            "cgi-bin/webcm"
        );
        //get new sessionID
        preg_match( "/uri \+= \"\&sid=(................)\"\;/", $response, $match);
        $this->sessionID = $match[1];
    }

    public function logout(){
        $comment = "logoff from fritzbox";
        $response = $this->postRequest(
            $comment, 
            "sid=$this->sessionID".
            "&security:command/logout=".
            "&getpage=../html/confirm_logout.html",
            "cgi-bin/webcm"
        );
    }
    
    public function loadCallerListFromBox(){
        //downloading the caller list without refreshing the caller list before
        //can lead to a caller list where the latest calls are still missing
        
        $comment = "refresh fritzbox caller list";
        $response = $this->getRequest(
            $comment, 
            "cgi-bin/webcm?".
            "sid=$this->sessionID".
            "&getpage=../html/de/menus/menu2.html".
            "&errorpage=../html/de/menus/menu2.html".
            "&var:pagename=foncalls".
            "&var:errorpagename=foncalls".
            "&var:menu=home".
            "&var:pagemaster=".
            "&time:settings/time=1222459630,-120".
            "&var:showDialing=".
            "&var:type=0".
            "&var:vonFoncalls=".
            "&var:PhonebookEntryNew=".
            "&var:PhonebookEntryXCount=".
            "&var:PhonebookEntryNumber=".
            "&telcfg:settings/UseJournal=1".
            "&var:WaehlhilfeVon="
        );
            
        $comment = "load fritzbox caller list";
        $response = $this->binaryTransfer(
            $comment,
            "cgi-bin/webcm?".
            "sid=$this->sessionID".
            "&getpage=../html/de/FRITZ!Box_Anrufliste.csv" 
        );
        $this->callerString .= $response;
    }    

    public function loadCallerListsFromDir($dir){
        $this->tr->addToTrace(3,"load fritzbox caller lists from local directory");
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
                $this->tr->addToTrace(1,"Line '$line' was skipped because it doesn't represent the expected call format.");
            }
        }
        return $this->callerList;
    }

    private function makeDots($str) {
        $newStr = "";
        for ($i = 0; $i < strlen($str); $i++) {
            $active = substr($str, $i,1);
            if (ord($active) > 255) {
                $newStr .= ".";
            }
            else {
                $newStr .= $active;
            }
        }
        return $newStr;
    }
    
    private function addZeroChars($str) {
        $ar = str_split($str);
        $str = implode($ar, chr(0)). chr(0);
        return $str;
    }
}
?>