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

class curllib {
	
	var $cookieJarEnabled = false,
		$baseurl = "",
		$requestType = "",
		$postValues = "",
		$cookieJarPath = "",
		$cookieJarInitialized = false,
		$binaryTransfer = false;
		
	function __construct (){
		$this->cookieJarEnabled = false;
		$this->baseurl = "";
		$this->requestType = "get";
		$this->postValues = "";
		$this->cookieJarPath = "";
		$this->cookieJarInitialized = false;
		$this->binaryTransfer = false;
	}
	
	public function enableCookieJar ( $path){
		$this->cookieJarEnabled = true;
		$this->cookieJarPath = $path;
	}

	public function enableBinaryTransfer (){
		$this->binaryTransfer = true;
	}

	public function disableBinaryTransfer (){
		$this->binaryTransfer = false;
	}
	
	public function setBaseUrl ( $url ){
		$this->baseurl = $url;
	}

	public function setRequestType ( $type ){
		$this->requestType = $type;
	}

	public function setPostVars ( $vars ){
	//	if ($vars != "") $this->setRequestType( "post" );
		$this->postValues = $vars;
	}
	
	public function postRequest ($comment, $postfields, $urlSuffix){
		$this->setRequestType( "post" );
		$this->setPostVars( $postfields );
		$response = $this->curlRequest( $comment, $urlSuffix );
		$this->setPostVars ( "" );
		$this->setRequestType( "" );
		return $response; 
	}

	public function getRequest ($comment, $urlSuffix){
		$this->setPostVars ( "" );
		$this->setRequestType( "get" );
		$response = $this->curlRequest( $comment, $urlSuffix );
		return $response; 
	}
	
	
	public function binaryTransfer ( $comment, $urlSuffix){
		$this->setRequestType( "" );
		$this->enableBinaryTransfer();
		$response = $this->curlRequest( $comment, $urlSuffix );
		$this->disableBinaryTransfer();
		return $response;
	}
	
	private function curlRequest ($comment, $urlSuffix ){
		print "---------------------------------------------------\n";
		print "REQUEST: $comment\n";
		print "---------------------------------------------------\n";
		$ch = curl_init();		
		curl_setopt($ch, CURLOPT_URL, $this->baseurl . $urlSuffix);
		print "URL: (" . $this->requestType . ") ". $this->baseurl . $urlSuffix . "\n";
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; U; Linux x86_64; de; rv:1.9.0.1) Gecko/2008072820 Firefox/3.0.1");
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1 );
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		//curl_setopt($ch, CURLOPT_USERPWD, '[username]:[password]');		
		if ($this->binaryTransfer === true){
			print "Binary transfer is turned on.\n";
			curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
		}
		if ($this->requestType == "post"){
			print "Post mode is turned on.\n";
			//curl_setopt($ch, CURLOPT_POST, TRUE);		
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->postValues);
		}
		
		if ( $this->cookieJarEnabled ){
			if (!$this->cookieJarInitialized){
				print "Cookie jar is turned on and was initialized.\n";
				curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJarPath );
				$this->cookieJarInitialized = true;
			}
			else{
				print "Existing cookie jar is used.\n";
				curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJarPath );
			}
			
		}
		$my_response = curl_exec($ch);
		  
		$feedback =  curl_getinfo ( $ch );
		//print_r ($feedback);
		print "\n";
		
		curl_close($ch);
		return $my_response;
	}
	
}

?>