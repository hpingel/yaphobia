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

class createDBTables{

	var $db,
		$dbh;
	
	
	function __construct(){
		$this->db = new dbMan();
		$this->dbh = $this->db->getDBHandle();
		$this->createAllTables();
	}
	
	
	public function createAllTables(){

		$query="
			CREATE TABLE `calendar_dummy_data` (
			  `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
			  PRIMARY KEY (`id`)
			)
			ENGINE = InnoDB;
		";
		$result = mysql_query( $query, $this->dbh );
		
		print "MySQL response: ". mysql_errno() . " " . mysql_error(). "\n";

		//fillDummyDatesTable
		for ($z=0; $z < 366; $z++){
			$query="INSERT INTO calendar_dummy_data SET id = $z";
			$result = mysql_query( $query, $this->dbh );
		}
		
		$query="
			CREATE TABLE `callprotocol` (
			  `date` datetime NOT NULL,
			  `identity` varchar(50) NOT NULL,
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
			  PRIMARY KEY  USING BTREE (`date`,`phonenumber`,`calltype`,`usedphone`,`providerstring`,`provider_id`,`estimated_duration`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='fritzbox call monitor data'
		";
		$result = mysql_query( $query, $this->dbh );

		print "MySQL response: ". mysql_errno() . " " . mysql_error(). "\n";
		
		$query="		
			CREATE TABLE  `provider_details` (
			  `provider_id` tinyint(4) NOT NULL,
			  `provider_name` varchar(50) NOT NULL,
			  `fritzbox_ident_string` varchar(50) NOT NULL,
			  PRIMARY KEY  (`provider_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8		
		";
		$result = mysql_query( $query, $this->dbh );
		
		print "MySQL response: ". mysql_errno() . " " . mysql_error(). "\n";
		
		
		$query="		
			CREATE TABLE `provider_rate_types` (
			  `provider_id` tinyint(4) NOT NULL,
			  `rate_type` varchar(50) NOT NULL,
			  `price_per_minute` float(8,4) NOT NULL,
			  `valid_from` datetime NOT NULL,
			  `valid_to` datetime NOT NULL,
			  PRIMARY KEY  USING BTREE (`provider_id`,`rate_type`,`valid_from`,`valid_to`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Lists rates to different locations including price'		";
		$result = mysql_query( $query, $this->dbh );
		print "MySQL response: ". mysql_errno() . " " . mysql_error(). "\n";
		
	}
	
}


?>