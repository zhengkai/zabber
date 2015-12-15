<?php
class Zabber {

	use ZabberParse;
	use ZabberTalk;

	const CONNECT_TIMEOUT = 20;
	protected $_iStartTime  = 0;

	protected $_hStream    = null;

	public $sHost = 'localhost';
	public $iPort = 5222;

	public $sUser = '';
	public $sPassword = '';

	public $sJID;

	protected $_iStreamID;
	public $sResource = 'Zabber';

	public $sHostTo = '';

	public $sLogPath = '/tmp';

	protected $_bWork;

	const METHOD_MAP = [
		'stream:stream',
		'proceed',
		'success',
		'iq',
		'message',
		'presence',
	];

	public function run() {
		$this->_iStartTime = time();
		if (!$this->_connect()) {
			return;
		}

		while (TRUE) {
			usleep(1);

			$aData = $this->_receive();
			if ($aData === FALSE) {
				return FALSE;
			}
			if (!$aData) {
				// echo 'empty ', intval($this->_bWork), "\n";
				continue;
			}

			$bGot = FALSE;
			foreach (self::METHOD_MAP as $sKeyword) {
				if (isset($aData[$sKeyword])) {
					$bGot = TRUE;
					break;
				}
			}

			if (!$bGot) {
				$this->_logFile('unknown_data.json', $aData);
				break;
			}

			$sMethod = $sKeyword;
			if ($sMethod === 'stream:stream') {
				$sMethod = 'stream';
			}
			$sMethod = '_parse' . ucfirst($sMethod);

			if (!$this->$sMethod($aData[$sKeyword])) {

				echo jsonf($aData);
				echo 'end at ' . $sKeyword, "\n";
				break;
			}
		}
	}

	protected function _connect() {

		if ($this->_hStream) {

			$this->_log('ReConnecting');

		} else {

			$this->_log('Connecting');

			$this->_hStream = fsockopen(
				$this->sHost,
				$this->iPort,
				$iError,
				$sError,
				self::CONNECT_TIMEOUT
			);

			if (!$this->_hStream) {
				$this->_log('Connect Failure : ' . $iError . ' ' . $sError);
				return FALSE;
			}

			$this->_log('Connect Success');

			stream_set_blocking($this->_hStream, 0);
			stream_set_timeout($this->_hStream, 3600);
		}

		$this->_send('<?xml version="1.0" encoding="UTF-8" ?>');
		$this->_sendStream();

		return TRUE;
	}

	protected function _sendStream() {
		$sData = '<stream:stream to="' . ($this->sHostTo ?: $this->sHost) . '" '
			. 'xmlns:stream="http://etherx.jabber.org/streams" '
			. 'xmlns="jabber:client" version="1.0">';
		$this->_send($sData);
	}

	protected function _send($sData) {
		$this->_logPocket($sData, TRUE);
		return fwrite($this->_hStream, $sData . "\n");
	}

	protected function _receive() {

		$sReturn = '';
		for ($i = 0; $i < 1024; $i++) {
			$sRead = @fread($this->_hStream, 10240);
			if (!is_string($sRead)) {

				$this->_log('disconnected, reconnecting');

				$this->_hStream = FALSE;
				$this->_connect();
				return;
			}
			if (empty($sRead)) {
				break;
			}
			$sReturn .= $sRead;
		}
		$sReturn = trim($sReturn);

		if (!$sReturn) {
			return;
		}

		$this->_logPocket($sReturn, FALSE);
		$aXML = XMLUtil::toArray($sReturn);
		if (!$aXML) {
			$this->_logFile('last_fail_packet.txt', $sReturn);
			return FALSE;
		}

		$this->_logFile('last.xml', $sReturn);
		$this->_logFile('last.json', $aXML);

		return $aXML;
	}

	protected function _log($sMessage) {
		echo "\n", date('H:i:s'), ' - ', $sMessage, "\n";
	}

	protected function _logPocket($sMessage, $bSend = TRUE) {

		$sLog = sprintf(
			"\n%s - %s :\n%s\n",
			date('H:i:s'),
			$bSend ? 'SEND >>' : 'RECV <<',
			$sMessage
		);
		$this->_logFile('log_stream.txt', $sLog, TRUE);
	}

	protected function _logFile($sName, $sMessage, $bAppend = FALSE) {

		if (!is_string($sMessage)) {
			$sMessage = jsonf($sMessage);
		}

		$sFile = rtrim($this->sLogPath, '/') . '/' . $sName;

		$iArg = $bAppend ? (FILE_APPEND | LOCK_EX) : LOCK_EX;

		file_put_contents($sFile, $sMessage, $iArg);
	}
}
