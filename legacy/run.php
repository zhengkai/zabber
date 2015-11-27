#! /usr/bin/env php
<?PHP
chdir(dirname(realpath($_SERVER["SCRIPT_NAME"])));

require_once("zabber.class.php");
require_once("zabberex.class.php");
require_once("mysqlite.class.php");

/*
 * Connect to Google Talk Server
 */

$aConfig = require __DIR__ . '/config.inc.php';

$oBot = new ZabberEx();
$oBot->sHost     = "chat.hipchat.com";
$oBot->sUser     = $aConfig['user'];
$oBot->sPassword = $aConfig['password'];
$oBot->sStatus   = "Zabber Ex Demo - Input \"help\" to get help information";
$oBot->run();
