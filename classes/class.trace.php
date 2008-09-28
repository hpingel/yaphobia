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

class trace{
	
	private 
		$trace,
		$type;
	
	function __construct( $type){
		$this->trace = "";
		if ($type != "html" && $type != "text"){
			die("Unknown trace type: '$type'!\n");
		}
		$this->type = $type;
		
	}

	public function addToTrace( $level, $message){
		if ($level <= TRACE_LEVEL){
			if ($this->type == "html"){
				print '<pre class="traceLevel'.$level.'">('.intval($level). ") " . htmlspecialchars($message) ."</pre>";
			}
			else{
				print "(". $level . ") " . $message . "\n";
			}
		}
		if ($level == 0){
			die();
		}
		
	}
	
	
}


?>