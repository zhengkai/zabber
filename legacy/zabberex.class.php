<?PHP
class ZabberEx extends Zabber {

	protected $_aCommandPrefix = array("/", "\\", "!");
	protected $_sCommandPath = "command";
	protected $_aCommandAlias = array(
			"ls"     => "\ls",
			"me"     => "\me",
			"uptime" => "!uptime",
			"help"   => "\help",
			"hello"  => "\help",
			"?"      => "\help",
		);

	protected function _eventMessage($sFrom, $sContent, $bOffline = FALSE) {
		if (empty($sContent)) {
			return FALSE;
		}
		$sFrom = parent::_getCleanJID($sFrom);
		$sReply = $bOffline ? "Offline Message Received." : "Got It.";

		$this->_sendMessage($sFrom, 'Got it. from "' . $sFrom . '" strlen: '. strlen($sContent));
		return;

		$sCommandAlias = strtolower(trim(substr($sContent, 0, 20)));
		if (isset($this->_aCommandAlias[$sCommandAlias])) {
			$sContent = $this->_aCommandAlias[$sCommandAlias];
		}

		$sCommandPrefix = substr($sContent, 0, 1);

		if (in_array($sCommandPrefix, $this->_aCommandPrefix)) {

			// Command

			if ($sCommandPrefix == "\\") {
				$sCommandPrefix = "/";
			}
			$aCommand = explode(" ", mb_substr($sContent, 1), 2);
			$sCommand =& $aCommand[0];
			$aCommand[0] = strtolower($aCommand[0]);
			if (isset($aCommand[1])) {
				$sArgument =& $aCommand[1];
			}
			if (empty($sCommand)) {
				$this->_sendMessage($sFrom, "Command Error: Empty");
			} else if (strlen($sCommand) > 50){
				$this->_sendMessage($sFrom, "Command Error: Too Long");
			}
			$sCommandFile = $this->_sCommandPath."/".bin2hex($sCommandPrefix)."_".bin2hex($sCommand).".php";
			// I use "bin2hex" filename to skip safety problems and multi-byte char command issues
			if (file_exists($sCommandFile)) {
				require($sCommandFile);
			} else {
				$this->_sendMessage($sFrom, "Command Error: Unknown");
			}
		} else {

			// Message

			$bHidden = FALSE;
			if (substr($sContent, 0, 1) == "#") {
				$bHidden = TRUE;
				$sContent = substr($sContent, 1);
				$sReply .= " (Secret)";
			}
			if (mb_strlen($sContent) > 255) {
				$sReply .= " 但是你鸭的留言太长了，我每句话最多只能保留前 255 个字. ";
				$sContent = mb_substr($sContent, 0, 300);
			}

			/*
			$oDB = new MySQLite();
			$sQuery = "INSERT INTO comment SET "
				."jid = \"".addslashes($sFrom)."\", "
				."content = \"".addslashes($sContent)."\", "
				."date_c = NOW()";
			if ($bHidden) {
				$sQuery .= ", status = \"hidden\"";
			}
			$oDB->query($sQuery);
			 */

			$this->_sendMessage($sFrom, $sReply);
		}
	}
}
?>
