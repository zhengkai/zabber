#! /usr/bin/env php
<?PHP
chdir(dirname(realpath($_SERVER["SCRIPT_NAME"])));

echo 1;

require_once("zabber.class.php");
require_once("zabberex.class.php");
require_once("mysqlite.class.php");
echo 2;

/*
 * Connect to Google Talk Server
 */

$oBot = new ZabberEx();
$oBot->sHost     = "chat.hipchat.com";
$oBot->sUser     = "";
$oBot->sPassword = "";
$oBot->sStatus   = "Zabber Ex Demo - Input \"help\" to get help information";
$oBot->run();
