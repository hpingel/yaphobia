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

define( 'FR_TYPE',       'FR_TYPE' );
define( 'FR_TYPE_GET',   'FR_TYPE_GET' ); 
define( 'FR_TYPE_POST',  'FR_TYPE_POST' );
define( 'FR_TYPE_BINARY',  'FR_TYPE_BINARY' );

define( 'FR_PATH',       'FR_PATH' );
define( 'FR_POSTVARS',   'FR_POSTVARS' ); 
define( 'FR_CHECKS',     'FR_CHECKS' );
define( 'FR_COMMENT',    'FR_COMMENT' ); 
define( 'FR_IGNORE',     'FR_IGNORE' ); 

class curllib {
	
	var $cookieJarEnabled = false,
		$baseurl = "",
		$requestType = "",
		$postValues = "",
		$cookieJarPath = "",
		$cookieJarInitialized = false,
		$binaryTransfer = false,
		$trace = "",
		$tr;
		
	function __construct ($tr){
		$this->cookieJarEnabled = false;
		$this->baseurl = "";
		$this->requestType = "get";
		$this->postValues = "";
		$this->cookieJarPath = "";
		$this->cookieJarInitialized = false;
		$this->binaryTransfer = false;
		$this->trace = "";
		$this->tr = $tr;
		
		if (!function_exists( "curl_init")){
			$this->tr->addToTrace(0, "Please enable the PHP Extension curl in php.ini file.");
		}
	}
	
	function __destruct (){
		if ( $this->cookieJarEnabled ){
			$this->deleteCookieJarFile();
		}
	}
	
	public function getTraceString (){
		return $this->trace;
	}

	public function resetTraceString (){
		$this->trace = "";
	}
	
	public function enableCookieJar ( $path){
		$this->cookieJarEnabled = true;
		$this->cookieJarPath = $path;
	}

	private function deleteCookieJarFile (){
		if ( file_exists($this->cookieJarPath)){
			@unlink($this->cookieJarPath);
		}
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

	protected function executeFlexRequest( $comment, $request, $search, $replace){
		$response = "";
		if (is_array( $request)){
			if ( !array_key_exists( FR_TYPE, $request) && isset($request[0]) && is_array( $request[0] )) {
				$this->tr->addToTrace(4, "detected multiple requests: ". count($request));
				
				foreach ($request as $single_request){
					$single_request[ FR_COMMENT ] = str_replace($search, $replace, $single_request[ FR_COMMENT ]);
					$response .= $this->executeFlexRequest( $single_request[ FR_COMMENT ], $single_request, $search, $replace);
				}
			}
			else{
				$this->tr->addToTrace(4, "Detected single request.");
				if ((is_array($search) && is_array($replace) && count($search) == count($replace) && count($replace) > 1) || (is_string($search) && is_string($replace))){
					foreach ($request as $key => $property){
						$request[$key] = str_replace($search, $replace, $property);
					}
				}
				else{
					$this->tr->addToTrace(4, "replace operation was skipped");
				}
				$response .= $this->executeSingleFlexRequest( $comment, $request);
			}
		}
		else{
			$this->tr->addToTrace(1, "ERROR: A flex request should always be of type array: '".$request."'");
		}
		return $response;
	}	
	
	
	protected function executeSingleFlexRequest( $comment, $request){
		$this->tr->addToTrace(4, print_r($request, true));
		switch ($request[ FR_TYPE ]) {
		case FR_TYPE_GET:
		    $response = $this->getRequest ($comment, $request[ FR_PATH ]);
			break;
		case FR_TYPE_POST:
		    $response = $this->postRequest ($comment, $request[ FR_POSTVARS ], $request[ FR_PATH ]);
		    break;
		case FR_TYPE_BINARY:
		    $response = $this->binaryTransfer ($comment, $request[ FR_PATH ]);
			break;
		}
		if ($request[ FR_IGNORE ] == true){
			$response = "";
			$this->tr->addToTrace(3, "Content of this request will not be added to resultset.");
		} 
		return $response;
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
		$this->tr->addToTrace(4, "---------------------------------------------------");
		$this->tr->addToTrace(3, "REQUEST: $comment");
		$this->tr->addToTrace(4, "---------------------------------------------------");
		$ch = curl_init();		
		curl_setopt($ch, CURLOPT_URL, $this->baseurl . $urlSuffix);
		$this->tr->addToTrace(4, "URL: (" . $this->requestType . ") ". $this->baseurl . $urlSuffix );
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; U; Linux x86_64; de; rv:1.9.0.1) Gecko/2008072820 Firefox/3.0.1");
		//FIXME: Make user agent + proxy settings flexible
		curl_setopt($ch, CURLOPT_HEADER, 0);
		//FIXME: we don't want to disable CURLOPT_SSL_VERIFYPEER for security reasons!!!
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		//curl_setopt($ch, CURLOPT_USERPWD, '[username]:[password]');		
		if ($this->binaryTransfer === true){
			$this->tr->addToTrace(4, "Binary transfer is turned on.");
			curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
		}
		if ($this->requestType == "post"){
			//curl_setopt($ch, CURLOPT_POST, TRUE);		
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->postValues);
			$this->tr->addToTrace(4, "Postvars: " . $this->postValues );
		}
		
		if ( $this->cookieJarEnabled ){
			if (!$this->cookieJarInitialized){
				if (file_exists($this->cookieJarPath)){
					$this->deleteCookieJarFile();
					$this->tr->addToTrace(1,"Old cookiejar file was still there at $this->cookieJarPath");
				}
				$this->tr->addToTrace(4,"Cookie jar is turned on and was initialized ($this->cookieJarPath)");
				curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJarPath );
				$this->cookieJarInitialized = true;
			}
			else{
				if (!file_exists($this->cookieJarPath)){
					$this->tr->addToTrace(1,"Cookiejar file doesn't exist at $this->cookieJarPath");
				}
				else{
					$this->tr->addToTrace(4,"Existing cookie jar is used.");
					
				}
				curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJarPath );
			}
			
		}
		$my_response = curl_exec($ch);
		  
		$this->tr->addToTrace(5, print_r(curl_getinfo ( $ch ), true) );
		
		curl_close($ch);
		
		return $my_response;
	}

	/*
	 * checks if a file can be created in the export_dir
	 * and saves it there
	 */
	public function createFileInExportDir( $filename, $content){
		if (@file_put_contents($filename, $content) !== false){
			return "File '$filename' has been successfully written.\n";
		}
		else{
			return "ERROR: File '$filename' could not be created.\n";
			
		}
	}	
}

?>