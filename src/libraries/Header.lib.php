<?php

/**
 * Header file included at the start of each controller file. It loads several static classes from 
 * the libraries/core sub-folder, each implementing various mechanisms that are used by the entire 
 * website, such as database querying, user log in, template engine, etc. In addition to loading 
 * such classes, this file also performs various initializations, some of which are implemented by 
 * the static classes themselves.
 */

// Gets the time at the very start of any script.
$overallStart = microtime(true);

// Finds the absolute path to the root folder (i.e., absolute path to www/ or equivalent).
$autoWWW = realpath($_SERVER['DOCUMENT_ROOT']).'/';

// Fetches configuration values used to log to the DB.
$configValues = require_once($autoWWW.'config/Config.inc.php');

// Finds the base URL, which is forced to be prefixed with "www." to avoid logging in issues.
$autoHTTP = $_SERVER['SERVER_NAME']; // Some insight: https://stackoverflow.com/questions/2297403
if($configValues['www_prefix'] == true && substr($autoHTTP, 0, 4) !== 'www.')
   $autoHTTP = 'www.'.$autoHTTP;
$autoHTTP = $configValues['protocol'].'://'.$autoHTTP.'/';

// Loads and initializes the essential components
require_once($autoWWW.'/libraries/core/Database.class.php');
require_once($autoWWW.'/libraries/core/PathHandler.class.php');

Database::init(
   $configValues['mysql_host'], 
   $configValues['mysql_db_name'], 
   $configValues['mysql_login'], 
   $configValues['mysql_pwd'], 
   $configValues['mysql_port']
);
PathHandler::init($autoWWW, $autoHTTP, $configValues['paths_js_extension']);

// Loads the template engine and utilities used across all the code (except static classes above)
require_once($autoWWW.'/libraries/core/TemplateEngine.class.php');
require_once($autoWWW.'/libraries/core/Utils.class.php');

// Loads the classes for handling the logged in user and the web page generation
require_once($autoWWW.'/libraries/core/LoggedUser.class.php');
require_once($autoWWW.'/libraries/core/WebpageHandler.class.php');

session_start();
LoggedUser::init();
WebpageHandler::init($overallStart);

// Checks current user is correctly logged in, then copies their browsing preferences.
if(LoggedUser::isLoggedIn() && LoggedUser::$fullData['using_preferences'] === 'yes')
   WebpageHandler::getUserPreferences(LoggedUser::$fullData);
