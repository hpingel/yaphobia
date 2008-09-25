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

	The following constants should be present to make Yaphobia run.
	Copy all lines from this comment to the bottom of this file 
	and set them to the appropriate values. 

	define('YAPHOBIA_DB_NAME', 'telefon_db');
	define('YAPHOBIA_DB_USER', 'telefon_db');
	define('YAPHOBIA_DB_PASSWORD', 'telefon_db');
	define('YAPHOBIA_DB_HOST', '127.0.0.1');
	
	define( 'FRITZBOX_PASSWORD', 'your_individual_password');
	
	define( 'YAPHOBIA_WORK_DIR', "/home/your_user/Dokumente/telefonrechnung");
	
	define( 'DUSNET_ACTIVE',      true ); //true or false, false means all dusnet related stuff will be omitted 
	define( 'DUSNET_PROVIDER_ID', 2); //id for internal use, each provider should have its own id
	define( 'DUSNET_SIPACCOUNT',  'your_individual_account_number'); 
	define( 'DUSNET_USERNAME',    'your_individual_username'); 
	define( 'DUSNET_PASSWORD',    'your_individual_password');
	define( 'DUSNET_SAVE_CSV_DATA_TO_WORKDIR', false);
	
	define( 'SIPGATE_ACTIVE',      false );  //true or false, false means all sipgate related stuff will be omitted 
	define( 'SIPGATE_PROVIDER_ID', 1); //id for internal use, each provider should have its own id
	define( 'SIPGATE_USERNAME',    "your_individual_username"); 
	define( 'SIPGATE_PASSWORD',    "your_individual_password");
	define( 'SIPGATE_SAVE_CSV_DATA_TO_WORKDIR', false);
	
	//phone number strings used in fritz!box call protocol to determine the voip number that was used for a call 
	
	define( 'FRITZBOX_PROTOCOL_SIPGATE_ID_CORRECT', 'see_fritzbox_anruferliste');
	define( 'FRITZBOX_PROTOCOL_SIPGATE_ID_WRONG',   'see_fritzbox_anruferliste'); //will be obsolete soon, can be left empty
	
	define( 'FRITZBOX_PROTOCOL_SIPGATE_ID', "see_fritzbox_anruferliste");
	
	//true or false, only set to true if you have a flatrate for festnetz calls and don't get a detailedbill for this
	define( 'AUTOBILL_REMAINING_FLATRATE_CALLS', true);
	define( 'FLATRATE_PROVIDER_ID', 0); //id for internal use, each provider should have its own id

	define( 'TOLERANCE_CALL_BEGIN', 120); //in seconds
	define( 'TOLERANCE_CALL_DURATION', 180); //in seconds
 
*/

	define('YAPHOBIA_DB_NAME', 'telefon_db');
	define('YAPHOBIA_DB_USER', 'telefon_db');
	define('YAPHOBIA_DB_PASSWORD', 'telefon_db');
	define('YAPHOBIA_DB_HOST', '127.0.0.1');
	
	define( 'FRITZBOX_PASSWORD', 'your_individual_password');
	
	define( 'YAPHOBIA_WORK_DIR', "/home/your_user/Dokumente/telefonrechnung");
	
	define( 'DUSNET_ACTIVE',      true ); //true or false, false means all dusnet related stuff will be omitted 
	define( 'DUSNET_PROVIDER_ID', 2); //id for internal use, each provider should have its own id
	define( 'DUSNET_SIPACCOUNT',  'your_individual_account_number'); 
	define( 'DUSNET_USERNAME',    'your_individual_username'); 
	define( 'DUSNET_PASSWORD',    'your_individual_password');
	define( 'DUSNET_SAVE_CSV_DATA_TO_WORKDIR', false);
	
	define( 'SIPGATE_ACTIVE',      false );  //true or false, false means all sipgate related stuff will be omitted 
	define( 'SIPGATE_PROVIDER_ID', 1); //id for internal use, each provider should have its own id
	define( 'SIPGATE_USERNAME',    "your_individual_username"); 
	define( 'SIPGATE_PASSWORD',    "your_individual_password");
	define( 'SIPGATE_SAVE_CSV_DATA_TO_WORKDIR', false);
	
	//phone number strings used in fritz!box call protocol to determine the voip number that was used for a call 
	
	define( 'FRITZBOX_PROTOCOL_SIPGATE_ID_CORRECT', 'see_fritzbox_anruferliste');
	define( 'FRITZBOX_PROTOCOL_SIPGATE_ID_WRONG',   'see_fritzbox_anruferliste'); //will be obsolete soon, can be left empty
	
	define( 'FRITZBOX_PROTOCOL_SIPGATE_ID', "see_fritzbox_anruferliste");
	
	//true or false, only set to true if you have a flatrate for festnetz calls and don't get a detailedbill for this
	define( 'AUTOBILL_REMAINING_FLATRATE_CALLS', true);
	define( 'FLATRATE_PROVIDER_ID', 0); //id for internal use, each provider should have its own id

	define( 'TOLERANCE_CALL_BEGIN', 120); //in seconds
	define( 'TOLERANCE_CALL_DURATION', 180); //in seconds




?>
