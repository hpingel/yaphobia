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

class reportManager{
	
	private
		$dbh = null,
		$xmlId = null,
		$selectFromTable = '',
		$sumRow = '',
		$query = '',
		$title = '',
		$columms = array(),
		$colummHeaders = array(),
		$result = null;
		
	function __construct($dbh, $xmlId){
		$this->dbh = $dbh;
		$this->xmlId = $xmlId;
		
	}

	public function getXmlId(){
		return $this->xmlId;
	}
	
	public function getQuery(){
		return $this->query;
	}

	
	public function setTitle( $title ){
		$this->title = $title;
	}
	
	public function getTitle(){
		return $this->title;
	}
	
	public function setSumRow( $sumRow ){
		$this->sumRow = $title;
	}
	
	public function getSumRow(){
		return $this->sumRow;
	}
	
	public function addColumn( $header, $sql, $fieldname  ){
		$this->columnHeaders[] = $header;
		$this->columns[$fieldname] = array( 
			'sql' => $sql,
			'title' => $header,
			//TODO: add width, align etc.
		);
	}

	public function addSelectFromTable( $table ){
		$this->selectFromTable = $table;
	}
	
	public function executeQuery(){
		$this->query = "SELECT ";
		foreach  ($this->columns as $fieldname => $columndata){
			$this->query .= $columndata['sql'] . ' AS ' . $fieldname . ', ';
		}
		$this->query = substr($this->query, 0, strlen($this->query) - 2 ) . ' FROM ' . $this->selectFromTable;
		$result = mysql_query( $this->query, $this->dbh );
		if (mysql_errno() != 0){
			print $this->query . ' / ' . mysql_error();
			die();	
		}
		$this->result = $this->getFullResultArray($result);		
	}
	
	public function getColumnHeaders(){
		return $this->columnHeaders;
	}

	public function getColumns(){
		return $this->columns;
	}
	
	public function getQueryResultArray(){
		return $this->result;
	}
	
	/*
	 * getFullResultArray
	 * 
	 */
	private function getFullResultArray($result){
		$table = array();
		while ($row = mysql_fetch_assoc($result)) {
			$table[] = $row;
		}
		return $table;
	}	
	
}	


?>