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

define( 'PATH_TO_SETTINGS', dirname(__FILE__). '/../config/settings.php' );

require_once( "../classes/class.trace.php");
require_once( "../classes/class.db_manager.php");
require_once( "../classes/class.callImportManager.php");
require_once( "../classes/class.installHelpers.php");
require_once( "../classes/class.settingsValidator.php");
require_once( "../classes/class.reports.php");
require_once( "../classes/class.htmlFrontend.php");

$hf = new htmlFrontend();


?>