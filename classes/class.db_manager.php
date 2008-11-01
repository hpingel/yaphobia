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

define ('YAPHOBIA_VERSION', '0.0.1-dev');

class dbMan {
	
	var $db;
	
	/*
	 * constructor, connect to database
	 * 
	 */
	function __construct(){
		$this->db = @mysql_connect( YAPHOBIA_DB_HOST, YAPHOBIA_DB_USER, YAPHOBIA_DB_PASSWORD );
		if (mysql_errno() != 0){
			die(
				'<div class="welcome">'."\n".
				'<h2>Couldn\'t connect to database!</h2>'."\n".
				'<p>Error message:<br/><b>'. mysql_error().'</b>.<br/>'."\n".
				'Please check your database parameters in config/settings.php.</p>'."\n".
				'</div>'."\n"
			);
		}
		if ( mysql_select_db( YAPHOBIA_DB_NAME , $this->db ) === false){
			die(
				'<div class="welcome">'.
				'<h2>Problem on accessing database "'.YAPHOBIA_DB_NAME.'".<br/></h2>'."\n".
				'<p>Error on database selection attempt:<br/><b>'. mysql_error().'</b>.<br/>'."\n".
				'Please check your database parameters in config/settings.php.</p>'."\n".
				'</div>'."\n"
			);
		}
	}

	/*
	 * send query to MySQL server, returns result
	 */
	public function sendQuery( $query ){	
			$result = mysql_query( $query, $this->db );
			return $result;
	}

	/*
	 * returns avtive db handle 
	 */
	public function getDBHandle( ){	
			return $this->db;
	}
	
	/*
	 * closes mysql connection
	 */
	function __destruct(){
		@mysql_close($this->db);
		//print "MySQL connection was closed.\n";
	}
}


?>