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

class installHelpers{

	private
		$tables = array(
			'calendar_dummy_data' =>
		
			"CREATE TABLE `calendar_dummy_data` (
			  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
			  PRIMARY KEY (`id`)
			)ENGINE = InnoDB DEFAULT CHARSET=utf8",
		 
			'callprotocol' =>
		
			"CREATE TABLE `callprotocol` (
			  `date` datetime NOT NULL,
			  `phonenumber` varchar(50) NOT NULL,
			  `calltype` tinyint(3) unsigned NOT NULL,
			  `usedphone` varchar(50) NOT NULL,
			  `providerstring` varchar(50) NOT NULL,
			  `provider_id` tinyint(3) unsigned NOT NULL,
			  `estimated_duration` tinyint(3) unsigned NOT NULL,
			  `billed` tinyint(1) default NULL,
			  `dateoffset` int(11) default NULL COMMENT 'Difference in seconds between protocolled starting time and billed starting time.',
			  `rate_type` varchar(50) default NULL,
			  `rate_type_id` int(11) default NULL,
			  `billed_duration` int(11) default NULL,
			  `billed_cost` float(8,4) default NULL,
			  `user` tinyint(3) unsigned default NULL,
			  PRIMARY KEY  USING BTREE (`date`,`phonenumber`,`calltype`,`providerstring`,`provider_id`,`estimated_duration`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='fritzbox call monitor data'",

			'provider_details' =>
				
			"CREATE TABLE  `provider_details` (
			  `provider_id` tinyint(4) NOT NULL,
			  `provider_name` varchar(50) NOT NULL,
			  `fritzbox_ident_string` varchar(50) NOT NULL,
  			  `current_credit` float(4,2),
  			  `current_credit_timestamp` datetime default NULL,
  			  PRIMARY KEY  (`provider_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8",
		
			'provider_rate_types' =>
		
			"CREATE TABLE `provider_rate_types` (
			  `provider_id` tinyint(4) NOT NULL,
			  `rate_type` varchar(50) NOT NULL,
			  `price_per_minute` float(8,4) NOT NULL,
			  `valid_from` datetime NOT NULL,
			  `valid_to` datetime NOT NULL,
			  PRIMARY KEY  USING BTREE (`provider_id`,`rate_type`,`valid_from`,`valid_to`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Lists rates to different locations including price'",		

			'unmatched_calls' =>
		
			"CREATE TABLE  `unmatched_calls` (
			  `provider_id` tinyint(4) NOT NULL,
			  `date` datetime NOT NULL,
			  `billed_duration` int(11) NOT NULL,
			  `rate_type` varchar(50) NOT NULL,
			  `billed_cost` float(8,4) NOT NULL,
			  `phonenumber` varchar(50) NOT NULL,
			  PRIMARY KEY  USING BTREE (`provider_id`,`date`,`billed_duration`,`rate_type`,`billed_cost`,`phonenumber`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8",

			'users' =>
		
			"CREATE TABLE `users` (
			  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
			  `username` VARCHAR(25)  NOT NULL,
			  PRIMARY KEY (`id`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci",

			'user_contacts' =>
		
			"CREATE TABLE `user_contacts` (
			  `phonenumber` VARCHAR(50)  NOT NULL,
			  `identity` varchar(50) NOT NULL,
			  `related_user` TINYINT UNSIGNED NOT NULL,
			  `obsolete` tinyint(1) default NULL,
			  `typo` tinyint(1) default NULL,
			  PRIMARY KEY (`phonenumber`)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci"
		),
		$dbh,
		$db_tables_present = 0;
	
	function __construct($dbh){
		$this->dbh = $dbh;		
	}

	public function getDBTableNames(){
		return array_keys($this->tables);
	}
	
	public function getMandatoryNumberOfDBTables(){
		return count($this->tables);
	}
	
	public function getNumberOfDBTables(){
		if ($this->db_tables_present === 0){
			$result = mysql_query( 'SHOW TABLES', $this->dbh );
			$this->db_tables_present = mysql_num_rows($result);
		}
		return $this->db_tables_present;
	}

	protected function tableIsEmpty( $table ){
		$result = mysql_query( 'SELECT COUNT(*) as rows FROM '.$table, $this->dbh );
		$row = mysql_fetch_assoc($result);
		if ($row["rows"] == 0)
			$feedback = true;
		else
			$feedback = false;
		return $feedback;
	}
	
	public function callProtocolIsEmpty(){
		return $this->tableIsEmpty( 'callprotocol' );
	}
	
	public function deleteAllDBTables(){
		foreach ($this->getDBTableNames() as $table){
			print "Trying to delete $table :";
			mysql_query( 'DROP TABLE ' . $table, $this->dbh);
			if (mysql_errno() == 0){
				print "OK\n";
			}
			else{
				print "Could not drop table due to MySQL error: ". mysql_errno() . " " . mysql_error(). "\n";
			}			
		}
		$this->db_tables_present = 0; // reset, so that it can be determined again
	}
	
	public function createDBTables(){
		
		if ($this->getMandatoryNumberOfDBTables() != $this->getNumberOfDBTables()){
			foreach ($this->tables as $table => $def){
				print "Trying to create table '$table': ";
				$result = mysql_query( $def, $this->dbh );
				if (mysql_errno() == 0){
					print "Table was created.\n";
				}
				else
					print "Could not create table due to MySQL error: ". mysql_errno() . " " . mysql_error(). "\n";
			}
		}
		else{
			//print "All ".$this->getMandatoryNumberOfDBTables()." database tables seem to be existing.\n";
		}
		//fillDummyDatesTable
		if ($this->tableIsEmpty( 'calendar_dummy_data' )){
			for ($z=0; $z < 366; $z++){
				$query="INSERT INTO calendar_dummy_data SET id = $z";
				$result = mysql_query( $query, $this->dbh );
			}
		}
	}
}


?>