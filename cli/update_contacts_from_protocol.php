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

define( 'PATH_TO_YAPHOBIA', str_replace("cli","",dirname(__FILE__)) );
require_once( PATH_TO_YAPHOBIA. "classes/class.cliEnvironment.php");
require_once( PATH_TO_YAPHOBIA. "classes/class.callImportManager.php");

class updateContactsFromProtocol extends cliEnvironment { 

	function __construct(){
		parent::__construct(
			" updateContactsFromProtocol.php tries to fill the database\n".
			" table user_contacts with phonenumbers and identities from\n".
			" table callprotocol. This script only needs to be called \n".
			" once, before we delete obsolete column identity from table \n".
			" callprotocol. This script won't work any more, once the \n".
			" column identity was deleted from callprotocol as of\n".
			" trunk subversion revision 31.\n"
			);
		$this->checkOptionalConfig();
		$this->populateContacts('identity');	
	}

	/*
	 * tries to update table user_contacts with phonenumbers from table callprotocol that are not yet in there.
	 * also tries to fill as many identity strings in table user_contacts as possible
	 * this function may not be needed if identities are only stored in user_contaces in the future and not 
	 * any more in callprotocol
	 */
	
	private function populateContacts($id_col_name){
		$id_col_name = mysql_real_escape_string( $id_col_name );
		$query = "SELECT phonenumber, $id_col_name FROM callprotocol WHERE $id_col_name != '' GROUP BY phonenumber ORDER BY date DESC";
		$result = mysql_query($query,$this->dbh);
		if (!$result) {
    		$this->traceObj->addToTrace( 1, 'Invalid query: ' . mysql_errno() . ") ". mysql_error() );
		}
		while ($row = mysql_fetch_assoc($result)) {
			if ($row[$id_col_name] != ""){
				$update_query = 
					"UPDATE user_contacts SET identity = '".mysql_real_escape_string($row[$id_col_name])."' ".
					"WHERE phonenumber = '".mysql_real_escape_string($row["phonenumber"])."'";
				$update_result = mysql_query($update_query,$this->dbh);
				if (!$update_result){
		    		$this->traceObj->addToTrace( 2, 'Update statement was incorrect: ' . mysql_errno() . ") ". mysql_error() );
				}
		    	else {
		    		//also try to insert
					$insert_query = 
						"INSERT INTO user_contacts (phonenumber, identity) VALUES ('".
							mysql_real_escape_string($row["phonenumber"])."', '".
							mysql_real_escape_string($row[$id_col_name])."')";
					$insert_result = mysql_query($insert_query,$this->dbh);
					if (!$insert_result) {
			    		$this->traceObj->addToTrace( 2, 'Error on insert: ' . mysql_errno() . ") ". mysql_error() );
					}
					else{
			    		$this->traceObj->addToTrace( 3, 'Insert was successful: ' . $insert_query  );
					}
				}
			}
		}
	}
}

$cgc = new updateContactsFromProtocol();

?>