<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version. 
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");

// No errors reported until we read the settings from the DB
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('gd.jpeg_ignore_warning', 1);

// Required Library Files
require_once("lib/app/debug.class.php");
require_once("lib/app/kit.class.php");
require_once("lib/app/pagemanager.class.php");
require_once("lib/app/menumanager.class.php");
require_once("lib/app/app_functions.php");
require_once("lib/modules/module.interface.php");
require_once("lib/modules/module.class.php");
require_once("lib/app/session.class.php");

// Required Config Files
require_once("config/config.class.php");
require_once("config/db_config.php");

// Sort out Magic Quotes
if (get_magic_quotes_gpc()) 
{
    function stripslashes_deep($value)
    {
        $value = is_array($value) ?
                    array_map('stripslashes_deep', $value) :
                    stripslashes($value);

        return $value;
    }

    $_POST = array_map('stripslashes_deep', $_POST);
    $_GET = array_map('stripslashes_deep', $_GET);
    $_COOKIE = array_map('stripslashes_deep', $_COOKIE);
    $_REQUEST = array_map('stripslashes_deep', $_REQUEST);
}

/*
 * Before we do anything else, lets check to see if we have a settings.php file
 * Without that file we can deduce that
 *  a) This is a first time install
 *  b) This is a corrupt or failed install
 */
if (!file_exists("settings.php")) 
{
	include("template/pages/no_settings_error.php");
	die();
}

// parse and init the settings.php
Config::Load();

// create a database class instance
$db = new database();

if (!$db->connect_db($dbhost, $dbuser, $dbpass)) trigger_error($db->error(), E_USER_ERROR);
if (!$db->select_db($dbname)) trigger_error($db->error(), E_USER_ERROR);

date_default_timezone_set(Config::GetSetting($db, "defaultTimezone"));

// Error Handling (our error handler requires a DB connection
set_error_handler(array(new Debug(), "ErrorHandler"));

// Define the VERSION
Config::Version($db);

// Debugging?
if(Config::GetSetting($db, "debug")=="On") error_reporting(E_ALL);

// Create login control system
require_once('modules/' . Config::GetSetting($db, "userModule"));

$user 		= new user();
$session 	= new Session($db);

// Page variable set? Otherwise default to index
$page 		= Kit::GetParam('p', _REQUEST, _WORD, 'index');

// Assign the page name to the session
$session->set_page(session_id(), $page);

// Create Page
$pageManager = new PageManager($db, $page);
$pageManager->Authenticate($user);
$pageManager->Render();

die();
?>