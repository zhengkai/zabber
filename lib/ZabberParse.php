<?php
trait ZabberParse {

	protected $_iUniqueID = 0;
	protected $_aXMLChar    = ['&', '<', '>', ']'];
	protected $_aXMLCharOut;

	protected function _parseStream($aData) {

		if (
			$aData['@']['xmlns'] != 'jabber:client'
			|| $aData['@']['xmlns:stream'] != 'http://etherx.jabber.org/streams')
		{
			$this->_log('Unrecognized stream packet');
		}

		if (!$this->sHostTo) {
			$this->sHostTo = $aData['@']['from'];
		}
		$this->_iStreamID = $aData['@']['id'];
		$this->_log('Connected, Stream ID = ' . $this->_iStreamID);

		if (!empty($aData['#']['stream:error'])) {
			echo 'stop at error ' . jsonf($aData['#']['stream:error']);
			return FALSE;
		}
		$sKey = 'stream:features';
		$aFeature =& $aData['#'][$sKey]['#'];

		if (!$aFeature) {
			$this->_log('Error: no "' . $sKey . '"');
			exit;
		}

		if (isset($aFeature['starttls'])) {

			$this->_log('Start TLS Connect');

			$sNS = 'urn:ietf:params:xml:ns:xmpp-tls';
			if ($aFeature['starttls']['@']['xmlns'] !== $sNS) {
				$this->_log('NS error' . $sNS);
				return FALSE;
			}

			$this->_send('<starttls xmlns="' . $sNS . '" />');
			return TRUE;
		}

		if (isset($aFeature['mechanisms'])) {

			$this->_log('Authenticating ...');

			$lMechanism = array_column($aFeature['mechanisms']['#']['mechanism'], '#');

			$sAuth = '';
			switch (TRUE) {

				case in_array('DIGEST-MD5', $lMechanism):

					$sAuth = '<iq type="set" id="' . $this->_getUniqueID() . '"><query xmlns="jabber:iq:auth">'
						. '<username>' . $this->sUser . '</username>'
						. '<resource>' . $this->sResource . '</resource>'
						. '<digest>' . sha1($this->_iStreamID . $this->sPassword) . '</digest>'
						. '</query></iq>';
					break;

				case in_array('PLAIN', $lMechanism):
					$sAuth = '<auth xmlns="urn:ietf:params:xml:ns:xmpp-sasl" mechanism="PLAIN">'
						. base64_encode(chr(0) . $this->sUser . chr(0) . $this->sPassword)
						. '</auth>';
					break;

				default:
					break;
			}

			if (!$sAuth) {
				$this->_log('unknown auth type: ' . implode(', ', $lMechanism));
				echo jsonf($aFeature['mechanisms']['#']);
				echo jsonf($aFeature);
				return FALSE;
			}

			$this->_send($sAuth);
			return TRUE;
		}

		if (isset($aFeature['bind'])) {

			$this->_send('<iq type="set" id="' . $this->_getUniqueID() . '">'
				. '<bind xmlns="urn:ietf:params:xml:ns:xmpp-bind">'
				. '<resource>' . $this->sResource . '</resource>'
				. '</bind></iq>');
			return TRUE;
		}

		var_dump($aFeature);

		return FALSE;
	}

	protected function _sendServiceDiscovery() {
		$this->_send('<iq type="get" to="' . $this->sHostTo . '">'
			. '<query xmlns="http://jabber.org/protocol/disco#info"/>'
			. '</iq>');
	}

	protected function _parsePresence($aData) {
		return TRUE;
	}

	protected function _parseProceed($aData) {

		$sNS = 'urn:ietf:params:xml:ns:xmpp-tls';
		if (
			empty($aData['@']['xmlns'])
			|| $aData['@']['xmlns'] !== $sNS
		) {
			$this->_log('Error: unknown action with proceed');
			return FALSE;
		}

		// TLS Connect
		stream_set_blocking($this->_hStream, 1);
		stream_socket_enable_crypto($this->_hStream, TRUE, STREAM_CRYPTO_METHOD_TLS_CLIENT);
		stream_set_blocking($this->_hStream, 0);
		$this->_connect();

		return TRUE;
	}

	protected function _parseSuccess($aData) {
		// echo __METHOD__, "\n", jsonf($aData), "\n";
		if ($aData['@']['xmlns'] === 'urn:ietf:params:xml:ns:xmpp-sasl') {
			$this->_log('Authentication Success');
			$this->_sendStream();
			return TRUE;
		}
	}

	protected function _getUniqueID($sType = null) {
		$this->_iUniqueID++;
		return $this->_iUniqueID;
	}

	protected function _parseIq($aData) {

		if (isset($aData['#']['bind'])) {
			$this->_eventBind($aData['#']['bind']['#']['jid']['#']);
			return TRUE;
		}

		$this->_log('unknown iq, ignore');
		return TRUE;
	}

	protected function _sendIqGet($sType) {
		$this->_send('<iq type="get" id="' . $this->_getUniqueID() . '">'
			. '<query xmlns="jabber:iq:'.$sType.'"/></iq>');
	}

	protected function _eventBind(string $sJID) {
		if (!$sJID) {
			throw new Exception('empty jid');
		}
		$this->_log('Login Over, JID = '.$sJID);
		$this->sJID = $sJID;
		$this->_sendServiceDiscovery();
		// $this->_sendIqGet('version');
	}

	protected function _xmlOut($sContent) {
		if (!$this->_aXMLCharOut) {
			foreach ($this->_aXMLChar as $sChar) {
				$this->_aXMLCharOut[] = '&#'.sprintf('%02d', ord($sChar)).';';
			}
		}
		$sContent = str_replace($this->_aXMLChar, $this->_aXMLCharOut, $sContent);
		return $sContent;
	}
}
