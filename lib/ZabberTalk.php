<?php
trait ZabberTalk {

	protected function _parseMessage($aData) {
		$sFrom =& $aData['@']['from'];
		$sMessage =& $aData['#']['body']['#'];
		if (!$sMessage) {
			// $this->_log('empty message, ignore');
			return TRUE;
		}

		if (substr($sMessage, 0, 1) === '!') {
			return $this->_command(substr($sMessage, 1), $sFrom);
		}

		return $this->_talk($sMessage, $sFrom);
	}

	protected function _command(string $sCommand, string $sFrom) {
		$sCommand = trim($sCommand);
		switch ($sCommand) {
			case 'uptime':
				$iTime = time() - $this->_iStartTime;
				$sReturn = $iTime . ' sec';
				break;
			case 'help':
				$sReturn = file_get_contents(__DIR__ . '/help.txt');
				break;
			default:
				$sReturn = 'unknown command';
				break;
		}
		$this->_sendMessage($sReturn, $sFrom);

		return TRUE;
	}

	protected function _talk(string $sMessage, string $sFrom) {

		$o = new ReflectionClass(__CLASS__);
		$lMethod = $o->getMethods();
		$lMethod = array_column($lMethod, 'name');
		$lMethod = array_filter($lMethod, function ($sMethod) {
			return preg_match('#^_talk.+#', $sMethod);
		});

		foreach ($lMethod as $sMethod) {
			if ($s = $this->$sMethod($sMessage)) {
				$sReturn = $s;
				break;
			}
		}

		if (empty($sReturn)) {
			$iMBLength = mb_strlen($sMessage);
			$iLength = strlen($sMessage);
			$sReturn = 'Got it. '
				. $iMBLength . ' ' . ($iMBLength === 1 ? 'char' : 'chars') . ', '
				. $iLength . ' ' . ($iLength === 1 ? 'byte' : 'bytes')
				. ' (type "help")';
		}
		$this->_sendMessage($sReturn, $sFrom);

		return TRUE;
	}

	protected function _sendMessage(string $sMessage, string $sTo) {
		$sSend = sprintf(
			'<message type="chat" from="%s" to="%s"><body>%s</body></message>',
			$this->sJID,
			$sTo,
			$this->_xmlOut($sMessage)
		);
		$this->_send($sSend);
	}

	protected function _talkWhois(string $sMessage) {
		$lMatch = [];
		if (!preg_match('#whois ([0-9a-zA-Z\.\-_]+)#', $sMessage, $lMatch)) {
			return FALSE;
		}

		return "/code\n"
			. 'whois ' . $lMatch[1] . "\n"
			. shell_exec('whois ' . escapeshellarg($lMatch[1]) . ' 2>&1');
	}

	protected function _talkIP(string $sMessage) {
		$lMatch = [];
		if (!preg_match('#ip ([0-9a-zA-Z\.\-_]+)#', $sMessage, $lMatch)) {
			return FALSE;
		}

		return "/code\n"
			. 'geoiplookup ' . $lMatch[1] . "\n"
			. shell_exec('geoiplookup ' . escapeshellarg($lMatch[1]) . ' 2>&1');
	}

	protected function _talkUptime(string $sMessage) {
		if (trim($sMessage) !== 'uptime') {
			return FALSE;
		}
		$iTime = time() - $this->_iStartTime;
		return "/code\n" . $iTime . ' sec';
	}

	protected function _talkHelp(string $sMessage) {
		if (trim($sMessage) !== 'help') {
			return FALSE;
		}
		return file_get_contents(__DIR__ . '/help.txt');
	}
}
