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

class settingsValidator{

	public function proofreadMandatorySettings(){
		$sermon = "";
		
		$mandatory_constants = array(
			'YAPHOBIA_DB_NAME',
			'YAPHOBIA_DB_USER',
			'YAPHOBIA_DB_PASSWORD',
			'YAPHOBIA_DB_HOST',
		);

		$sermon_items = array();		
		foreach ($mandatory_constants as $const){
			if (!defined( $const )){
				$sermon_items[] = $this->pleaseDefineMandatoryConstant($const);
			}
			/*
			else{
				print "$const: ".constant($const)."\n";
			}
			*/
		}
		
		if (count($sermon_items) > 0){
			$sermon .= "<p>Mandatory constants are missing in your configuration. Please add them to /config/settings.php:</p>\n<ul>\n";
			foreach ($sermon_items as $item){
				$sermon .= "<li>".$item."</li>\n";		
			}
			$sermon .= "</ul>\n";
		}
		if ($sermon != ""){
			$sermon = "<h1>Please check your Yaphobia configuration settings</h1>\n<p>All configuration settings are managed in <b>/config/settings.php</b></p>" . $sermon;
		}
		
		return $sermon;		
	}
	
	public function proofreadOptionalSettings(){
		$sermon = "";

		$pathToYaphobia = str_replace("classes","",dirname(__FILE__));
		$optional_constants = array(
			'TRACE_LEVEL' => 2, //0-5
			'EXTJS_UI_ENABLED' => false, //experimental
			'PATH_TO_YAPHOBIA' => $pathToYaphobia,
			'YAPHOBIA_COOKIEJAR_DIR'  => $pathToYaphobia. 'cookiejar/',
			'YAPHOBIA_DATA_EXPORT_DIR' => $pathToYaphobia. 'data_export/',
			'YAPHOBIA_WEB_INTERFACE_PASSWORD' => "", //authentication is disabled
		
			'FRITZBOX_HOSTNAME' => 'fritz.box',
			'FRITZBOX_PASSWORD' => '',
			'FRITZBOX_SAVE_CALLER_PROTOCOL_TO_EXPORT_DIR' => false,
		
			'AUTOBILL_REMAINING_FLATRATE_CALLS' => false,
			'FLATRATE_PROVIDER_ID' => 0,
			'TOLERANCE_CALL_BEGIN'=> 120, //in seconds
			'TOLERANCE_CALL_DURATION' => 180, //in seconds
		
			//FIXME: the following stuff will be reorganized soon into sub-arrays 
		
			'SIPGATE_ACTIVE' => false,
			'SIPGATE_PROVIDER_ID' => 1,
			'SIPGATE_USERNAME' => '', 
			'SIPGATE_PASSWORD' => '',
			'SIPGATE_SAVE_CSV_DATA_TO_WORKDIR' => false,
		
			'DUSNET_ACTIVE' => false,
			'DUSNET_PROVIDER_ID' => 2,
			'DUSNET_SIPACCOUNT' => '', 
			'DUSNET_USERNAME' => '', 
			'DUSNET_PASSWORD' => '',
			'DUSNET_SAVE_CSV_DATA_TO_WORKDIR' => false,
			'FRITZBOX_PROTOCOL_SIPGATE_ID_CORRECT' => '',
			'FRITZBOX_PROTOCOL_DUSNET_ID' => ''
		);

		$sermon_items = array();		
		foreach ($optional_constants as $const => $default){
			if (!defined( $const )){
				$sermon_items[] = $this->pleaseDefineOptionalConstant($const, $default);
				define( $const, $default);
			}
			else{
				$sermon_items[] = $this->optionalConstantExists($const);
			}
		}

		if (count($sermon_items) > 0){
			$sermon .= "<p>To change the situation, please adjust the content of <b>/config/settings.php</b></p><ul>\n";
			foreach ($sermon_items as $item){
				$sermon .= "<li>".$item."</li>\n";		
			}
			$sermon .= "</ul>\n";
		}
		
		return $sermon;
		
	}
	
	private function pleaseDefineMandatoryConstant($const){
		return "Constant <b>'".htmlspecialchars($const)."'</b> is not defined. Sorry, but setting this constant is mandatory to run Yaphobia.";
		
	}
	
	private function pleaseDefineOptionalConstant($const, $default){
		return "<b>Undefined</b>: Constant '<b>".htmlspecialchars($const)."</b>' is being set to default value '<b>".htmlspecialchars($default)."</b>'.";
		
	}
	
	private function optionalConstantExists($const){
		$value = constant($const);
		if (stristr($const, 'PASSWORD') !== false) $value = '*** SECRET PASSWORD ***';
		if (stristr($const, 'USERNAME') !== false) $value = '*** SECRET USERNAME ***';
		return "<b>Found</b>: Constant '<b>".htmlspecialchars($const)."</b>' with value '<b>".$value."</b>'.";
	}
}
?>