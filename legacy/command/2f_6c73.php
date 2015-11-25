<?PHP
/*
 *  command : \ls
 */

$iMax = 10;

$oDB = new MySQLite();
$sQuery = "SELECT id, jid, content FROM comment WHERE status = \"normal\" ORDER BY date_c DESC LIMIT ".$iMax;
$oDB->query($sQuery);

$sOut = "the Last ".$iMax." Messages from All:\n\n";

if ($oResult = $oDB->query($sQuery)) {
	$bEmpty = TRUE;
	while ($aRow = $oResult->fetch_assoc()) {
		$bEmpty = FALSE;
		list($aRow["jid"]) = explode("@", $aRow["jid"], 2);
		$sOut .= $aRow["jid"]." : ".$aRow["content"]."\n";
	}
	if ($bEmpty) {
		$sOut .= "No message yet, you can type some";
	}
} else {
	$sOut .= "Error: Maybe Database is dead.";
}

$this->_sendMessage($sFrom, $sOut);
?>