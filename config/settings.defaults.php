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
 * The following constants should be configured correctly to make Yaphobia run.
 * Before changing the values, please copy this file to settings.php.
 * Leave it in the same folder as settings.defaults.php.
 * 
 */


	define( 'YAPHOBIA_DB_NAME', 'mysql_database_name');
	define( 'YAPHOBIA_DB_USER', 'mysql_user_name');
	define( 'YAPHOBIA_DB_PASSWORD', 'mysql_user_password');
	define( 'YAPHOBIA_DB_HOST', 'localhost');
	define( 'YAPHOBIA_WORK_DIR', "/home/your_user/Dokumente/telefonrechnung");
	define( 'PATH_TO_YAPHOBIA', str_replace("/config","",dirname(__FILE__))  );	
	//cookiejar dir must be writable from web root
	define( 'YAPHOBIA_COOKIEJAR_DIR', PATH_TO_YAPHOBIA. '/cookiejar/' ); //ends with a slash
	
	define( 'FRITZBOX_PASSWORD', 'your_individual_password');
	
	/*
	 * sipgate related settings
	 * if you don't use sipgate, leave SIPGATE_ACTIVE set to false.
	 *  
	 */
	
	define( 'SIPGATE_ACTIVE',      false );  //true or false, false means all sipgate related stuff will be omitted 
	define( 'SIPGATE_PROVIDER_ID', 1); //id for internal use, each provider should have its own id
	define( 'SIPGATE_USERNAME',    "your_individual_username"); 
	define( 'SIPGATE_PASSWORD',    "your_individual_password");
	define( 'SIPGATE_SAVE_CSV_DATA_TO_WORKDIR', false);
	
	/*
	 * dus.net related settings
	 * if you don't use dus.net, leave DUSNET_ACTIVE set to false.
	 *  
	 */
	
	define( 'DUSNET_ACTIVE',      false ); //true or false, false means all dusnet related stuff will be omitted 
	define( 'DUSNET_PROVIDER_ID', 2); //id for internal use, each provider should have its own id
	define( 'DUSNET_SIPACCOUNT',  'your_individual_account_number'); 
	define( 'DUSNET_USERNAME',    'your_individual_username'); 
	define( 'DUSNET_PASSWORD',    'your_individual_password');
	define( 'DUSNET_SAVE_CSV_DATA_TO_WORKDIR', false);
	
	/*
	 * phone number strings used in fritz!box call protocol to determine 
	 * the voip number that was used for a call
	 * 
	 * Example : 'Internet: 0123456789'
	 */ 
	
	//FRITZBOX_PROTOCOL_SIPGATE_ID_WRONG will be obsolete soon, can be left empty
	define( 'FRITZBOX_PROTOCOL_SIPGATE_ID_CORRECT', 'see_fritzbox_anruferliste');
	define( 'FRITZBOX_PROTOCOL_SIPGATE_ID_WRONG',   ''); 
	define( 'FRITZBOX_PROTOCOL_DUSNET_ID'       ,   'see_fritzbox_anruferliste');
	
	/*
	 * land line provider settings
	 * 
	 * If you have a phone flatrate for with your land line connection and 
	 * you don't get a list of outgoing calls from the provider, set 
	 * AUTOBILL_REMAINING_FLATRATE_CALLS to true. Then any outgoing call that
	 * went out by you land line provider will be automatically set to status "billed" with
	 * zero costs 
	 */
	
	define( 'AUTOBILL_REMAINING_FLATRATE_CALLS', true); // true or false
	define( 'FLATRATE_PROVIDER_ID', 0); //id for internal use, each provider should have its own id

	/*
	 *  tolerance settings for matching protocolled calls with billed calls
	 */
	
	define( 'TOLERANCE_CALL_BEGIN', 120); //in seconds
	define( 'TOLERANCE_CALL_DURATION', 180); //in seconds




?>
