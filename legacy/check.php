<?PHP
echo "\nBegin Check ...\n\n";

echo "Check for PHP version: ";
if (version_compare(phpversion(), "5.2.3", ">=")) {
	echo "[ OK ]\n";
} else {
	echo "[ Failed ]\n\n";
	echo "    Your PHP version is lower than 5.2.3,\n";
	echo "    I'm not sure anything in lower version\n\n";
}

echo "Check for Command Line mode: ";
if (PHP_SAPI == "cli") {
	echo "[ OK ]\n";
} else {
	echo "[ Failed ]\n\n";
	echo "    You should run a bot at CLI mode\n";
	echo "    http://www.php.net/features.commandline\n\n";
}

echo "Check for TLS: ";
if (in_array("tls", stream_get_transports())) {
	echo "[ OK ]\n";
} else {
	echo "[ Failed ]\n\n";
	echo "    Your PHP not supports \"tls\"\n";
	echo "    Registered Stream Socket Transports is \"".implode("\", \"", stream_get_transports())."\"\n";
	echo "    Install OpenSSL Library or others\n\n";
}

if (
?>