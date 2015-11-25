<?PHP
chdir(dirname(realpath($_SERVER["SCRIPT_NAME"])));

require_once("zabber.class.php");
require_once("zabberex.class.php");
require_once("mysqlite.class.php");

/*
 * Try to connect Localhost Openfire Server
 *
 * you can get Openfire from URL:
 * http://www.igniterealtime.org/projects/openfire/index.jsp
 */

$oBot = new ZabberEx();
$oBot->sHost     = "localhost";
$oBot->sHostTo   = "computer_name"; /*
	replace it with your computer name,
	you can see it in Openfire Admin Panel -> Server Settings -> Server Name
	*/

$oBot->sUser     = "admin"; // it's default account, or change it
$oBot->sPassword = "admin";
$oBot->sStatus   = "Zabber Ex Demo - Input \"help\" to get help information";
$oBot->run();
?>