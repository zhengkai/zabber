<?PHP
require_once("xmlize.inc.php");

class Zabber {

	public $sHost = "localhost";
	public $iPort = 5222;

	public $sHostTo = "";

	public $iTimeout = 20;

	public $sStatus = "Zabber - a PHP Jabber Class";

	public $sUser     = "";
	public $sPassword = "";

	public $sResource = "Zabber";
	public $sJID      = "";
	public $bDebug    = TRUE;

	protected $_hStream    = null;
	protected $_hLogWork   = null;
	protected $_hLogPocket = null;
	protected $_iStreamID  = null;

	protected $_iUniqueID = 0;

	protected $_aPresenceType = array("unavailable", "subscribe", "subscribed", "unsubscribe", "unsubscribed", "probe", "error");

	protected $_aXMLChar    = array("&", "<", ">", "]");
	protected $_aXMLCharOut = array();
	protected $_iStartTime  = 0;

	protected $_bAuth = FALSE;

	public function __construct() {
		$this->_log("\n\n\n\nClass Begin\n\n");
	}

	// 主运行
	public function run() {
		$this->_iStartTime = time();
		$this->_connect();
		while (TRUE) { // death cycle, need to add some judgment
			sleep(1);
			$aData = $this->_receive();
			if (empty($aData)) {
				continue;
			}
			switch (TRUE) {
				case isset($aData["stream:stream"]):
					$this->_parseStream($aData);
					break;
				case isset($aData["presence"]):
					$this->_parsePresence($aData);
					break;
				case isset($aData["message"]):
					$this->_parseMessage($aData);
					break;
				case isset($aData["iq"]):
					$this->_parseIq($aData);
					break;
				case isset($aData["proceed"]):
					$this->_parseProceed($aData);
					break;
				case isset($aData["success"]):
					$this->_parseSuccess($aData);
					break;
				case isset($aData["failure"]):
					$this->_parseFailure($aData);
				default:
					// unparsed pocket, maybe you need to record and parse it
					break;
			}
		}
	}

	/*
	 *  parse methods
	 *
	 *  read and parse any received pocket,
	 *  named by begin element name, "<stream:stream", "<iq", etc
	 *  then throw it to a _event method, or do other actions
	 */

	protected function _parseStream($aData) {
		if ( // $aData["stream:stream"]['@']['from'] == $this->sHostTo &&
			$aData["stream:stream"]['@']['xmlns'] != "jabber:client"
			|| $aData["stream:stream"]['@']["xmlns:stream"] != "http://etherx.jabber.org/streams")
		{
			$this->_log("Unrecognized stream packet");
		}

		if (empty($this->sHostTo)) {
			$this->sHostTo = $aData["stream:stream"]['@']['from'];
		}
		$this->_iStreamID = $aData["stream:stream"]['@']['id'];
		$this->_eventConnected();

		$aDataStreamFeatures =& $aData["stream:stream"]["#"]["stream:features"][0];

		if (isset($aData["stream:stream"]["#"]["stream:error"])) {
			$aError = $aData["stream:stream"]["#"]["stream:error"][0]["#"];
			if (isset($aError["host-unknown"])) {
				if ($this->sHostTo == $this->sHost) {
					$aTemp = explode("@", $this->sUser, 2);
					if (!empty($aTemp[1])) {
						$sHostTo = trim($aTemp[1]);
					}
					if (!empty($sHostTo)&&($this->sHostTo != $sHostTo)) {
						fclose($this->_hStream);
						$this->_hStream = null;
						$this->sHostTo = $sHostTo;
						$this->_connect();
					} else {
						$this->_log("Stream host \"".$this->sHost."\" is not accpeted, define a right \$this->sHostTo");
					}
				} else {
					$this->_log("Stream host \"".$this->sHost."\" and \"".$this->sHostTo."\" are not accpeted, define a right \$this->sHostTo");
				}
			}
			return FALSE;
		}

		if (isset($aDataStreamFeatures["#"]["starttls"])&&($aDataStreamFeatures["#"]["starttls"][0]["@"]["xmlns"] == "urn:ietf:params:xml:ns:xmpp-tls")) {

			// TLS Connect
			$this->_log("Start TLS Connect");
			$this->_send("<starttls xmlns=\"urn:ietf:params:xml:ns:xmpp-tls\" />");

		} else if (isset($aDataStreamFeatures["#"]["mechanisms"])&&($aDataStreamFeatures["#"]["mechanisms"][0]["@"]["xmlns"] == "urn:ietf:params:xml:ns:xmpp-sasl")) {

			// Auth
			$this->_log("Authenticating ...");
			$aMechanism = array();
			foreach ($aDataStreamFeatures["#"]["mechanisms"][0]["#"]["mechanism"] as $aRow) {
				$aMechanism[] = $aRow["#"];
			}

			switch (TRUE) {
				case in_array("DIGEST-MD5", $aMechanism):

					$sAuth = "<iq type=\"set\" id=\"".$this->_getUniqueID()."\"><query xmlns=\"jabber:iq:auth\">"
						."<username>".$this->sUser."</username>"
						."<resource>".$this->sResource."</resource>"
						."<digest>".sha1($this->_iStreamID.$this->sPassword)."</digest>"
						."</query></iq>";
					break;
				case in_array("PLAIN", $aMechanism):

					$sAuth = "<auth xmlns=\"urn:ietf:params:xml:ns:xmpp-sasl\" mechanism=\"PLAIN\" >"
						.base64_encode(chr(0).$this->sUser.chr(0).$this->sPassword)
						."</auth>";
				default:
					// other: CRAM-MD5, ANONYMOUS ... etc.
					break;
			}
			$this->_send($sAuth);

		} else if (isset($aDataStreamFeatures["#"]["bind"])&&($aDataStreamFeatures["#"]["bind"][0]["@"]["xmlns"] == "urn:ietf:params:xml:ns:xmpp-bind")) {

			// Bind
			$this->_log("Server Binding Feature");
			$this->_send("<iq type=\"set\" id=\"".$this->_getUniqueID()."\">"
				."<bind xmlns=\"urn:ietf:params:xml:ns:xmpp-bind\">"
				."<resource>".$this->sResource."</resource>"
				."</bind></iq>");
		}
	}

	protected function _parsePresence($aData) {
		if (isset($aData["presence"]["@"]["type"])) {
			switch ($aData["presence"]["@"]["type"]) {
				case "subscribe":
					$this->_eventSubscribe($aData["presence"]["@"]["from"]);
					break;
			}
		}
	}

	protected function _parseMessage($aData) {
		$aData = $aData["message"];

		if (isset($aData["@"]["type"])&&($aData["@"]["type"] == "chat")) {
			// 收到消息
			$sFrom = $aData["@"]["from"];
			if (isset($aData["#"]["body"][0]["#"])) {
				$sContent = $aData["#"]["body"][0]["#"];
				$this->_eventMessage($sFrom, $sContent);
			}
			// 还有个 jabber:x:event
		} else if (isset($aData["#"]["x"][0]["@"]["xmlns"])&&($aData["#"]["x"][0]["@"]["xmlns"] == "jabber:x:delay")) {
			// offline message
			$sFrom = $aData["@"]["from"];
			$sContent = $aData["#"]["body"][0]["#"];
			$this->_eventMessage($sFrom, $sContent, TRUE);
		}
	}

	protected function _parseIq($aData) {
		if (empty($this->_bAuth)&&isset($aData["iq"]["@"]["type"])&&($aData["iq"]["@"]["type"] == "result")) {
			if (isset($aData["iq"]["@"]["to"])) {
				$this->_eventBind($aData["iq"]["@"]["to"]);
			}
		}

		if (isset($aData["iq"]["#"]["bind"])) {
			$this->_eventBind($aData["iq"]["#"]["bind"][0]["#"]["jid"][0]["#"]);

		} else if (isset($aData["iq"]["#"]["query"])) {
			$sNameSpace =& $aData["iq"]["#"]["query"][0]["@"]["xmlns"];
			switch ($sNameSpace) {
				case "jabber:iq:roster":
					$aDataSub = $aData["iq"]["#"]["query"][0]["#"]["item"];
					foreach ($aDataSub as $aRoster) {
						$aRoster = $aRoster["@"];
						if ($aRoster["subscription"] == "none") {
							$this->_eventSubscribe($aRoster["jid"]);
						}
					}
					break;
				case "http://jabber.org/protocol/disco#info":
					break;
			}
		}
	}

	protected function _parseProceed($aData) {
		// TLS Connect
		if ($aData["proceed"]["@"]["xmlns"] == "urn:ietf:params:xml:ns:xmpp-tls") {
			stream_set_blocking($this->_hStream, 1);
			stream_socket_enable_crypto($this->_hStream, TRUE, STREAM_CRYPTO_METHOD_TLS_CLIENT);
			stream_set_blocking($this->_hStream, 0);
			$this->_connect();
		}
	}

	protected function _parseSuccess($aData) {
		if ($aData["success"]['@']['xmlns'] == "urn:ietf:params:xml:ns:xmpp-sasl") {
			$this->_log("Authentication Success");
			$this->_sendStream();
		}
	}

	protected function _parseFailure($aData) {
		// <failure xmlns="urn:ietf:params:xml:ns:xmpp-sasl"><not-authorized/></failure></stream:stream>
		if ($aData["failure"]['@']['xmlns'] == "urn:ietf:params:xml:ns:xmpp-sasl") {
			$this->_log("Authentication Failure");
			print_r($aData);
			// reAuth or close connect ? but I don't know how to close connect -_-
		}
	}

	/*
	 *  event methods
	 *
	 *  make a extends class methods to replace them, do actions what you want
	 *  I prefer this way than function handling functions
	 */

	protected function _eventConnected() {
		$this->_log("Connected, Stream ID = ".$this->_iStreamID);
	}

	protected function _eventMessage($sFrom, $sContent, $bOffline = FALSE) {
		$this->_log("Message ".($bOffline ? "(Offline)" : "")."from ".$sFrom.": ".$sContent);
		if ($bOffline) {
			continue;
		}
		// $sReply = $bOffline ? "Offline Message Received." : "Got It.";
		$sReply = "Got It. " . strlen($sContent);
		$this->_sendMessage($sFrom, $sReply);
	}

	protected function _eventSubscribe($sJID) {
		$this->_sendPresence("subscribed", $sJID); // appect any subscribe quest
	}

	protected function _eventBind($sJID) {
		$this->_log("Login Over, JID = ".$sJID);
		$this->sJID = $sJID;
		$this->_bAuth = TRUE;
		$this->_sendServiceDiscovery();
		$this->_sendIqGet("version");
	//	$this->_sendIqGet("browse");
	//	$this->_sendIqGet("roster");
	//	$this->_sendSetStatus($this->sStatus);
	}

	/*
	 *  send methods, some common send format
	 */

	protected function _sendMessage($sTo, $sContent) {
		$sXML  = "<message type=\"chat\" from=\"".$this->sJID."\" to=\"".$sTo."\">";
		$sXML .= "<body>".$this->_xmlOut($sContent)."</body>";
		$sXML .= "</message>";
		$this->_send($sXML);
	}

	protected function _sendIqGet($sType) {
		$this->_send("<iq type=\"get\" id=\"".$this->_getUniqueID()."\"><query xmlns=\"jabber:iq:".$sType."\"/></iq>");
	}

	protected function _sendServiceDiscovery() {
		$this->_send("<iq type=\"get\" to=\"".$this->sHostTo."\"><query xmlns=\"http://jabber.org/protocol/disco#info\"/></iq>");
	}

	protected function _sendPresence($sType, $sTo) {
		if (!in_array($sType, $this->_aPresenceType)) {
			$this->_log("_sendPresence Method, \$sType Error");
			return FALSE;
		}
		$this->_send("<presence from=\"".$this->sJID."\" to=\"".$sTo."\" type=\"".$sType."\" />");
	}

	protected function _sendSetStatus($sStatus = null, $sShow = "chat") {
		$sXML = "<presence>";
		$sXML .= "<show>".$sShow."</show>";
		if ($sStatus) {
			$sXML .= "<status>".$sStatus."</status>";
		}
		$sXML .= "</presence>";
		$this->_send($sXML);
	}

	protected function _sendStream() {
		$sData = "<stream:stream to=\"".$this->sHostTo."\" "
			."xmlns:stream=\"http://etherx.jabber.org/streams\" "
			."xmlns=\"jabber:client\" version=\"1.0\">";
		$this->_send($sData);
	}

	// Connect to server, or reconnect it because TLS
	protected function _connect() {

		if (empty($this->_hStream)) {
			$this->_log("Connecting");
			if ($this->_hStream = fsockopen($this->sHost, $this->iPort, $iError, $sError, $this->iTimeout)) {
				$this->_log("Connect Success");
				stream_set_blocking($this->_hStream, 0);
				stream_set_timeout($this->_hStream, 3600 * 24);
			} else {
				$this->_log("Connect Failure : ".$iError." ".$sError);
				return FALSE;
			}
		} else {
			$this->_log("ReConnecting");
		}

		if (empty($this->sHostTo)) {
			$this->sHostTo = $this->sHost;
		}

		$this->_send("<?xml version=\"1.0\" encoding=\"UTF-8\" ?>");
		$this->_sendStream();
	}

	/*
	 *  2 most frequent methods
	 *
	 *  the base way to talk with XMPP server
	 */

	// Send Pocket
	protected function _send($sData) {
		$this->_logPocket($sData, TRUE);
		return fwrite($this->_hStream, $sData."\n");
	}

	// Receive Pocket, and cover XML to Array
	protected function _receive() {

		set_magic_quotes_runtime(0);
		$sReturn = "";
		for ($i = 0; $i < 100; $i++) {
			$sRead = fread($this->_hStream, 1048576);
			if (empty($sRead)) {
				break;
			}
			$sReturn .= $sRead;
		}
		set_magic_quotes_runtime(get_magic_quotes_runtime());
		$sReturn = trim($sReturn);

		if (empty($sReturn)) {
			return FALSE;
		} else {
			$this->_logPocket($sReturn, FALSE);
			$aXML = xmlize($sReturn);
			if (!empty($this->bDebug)) {
				file_put_contents("last_xml.txt", print_r($aXML, TRUE));
			}
			return $aXML;
		}
	}

	/*
	 *  2 log methods, when $this->bDebug = FALSE, they will stop work
	 *
	 *  Recommended read log.txt and log_pocket.txt by "tail -f"
	 *  If you are in Windows Platform,
	 *  you can get it from http://unxutils.sf.net
	 */

	// main log
	protected function _log($sMessage) {
		if (empty($this->bDebug)) {
			return FALSE;
		}
		if (empty($this->_hLogWork)) {
			$this->_hLogWork = fopen("log.txt", "ab");
		}
		fwrite($this->_hLogWork,
	}

	// pocket log,
	protected function _logPocket($sMessage, $bSend = TRUE) {
		if (empty($this->bDebug)) {
			return FALSE;
		}
		if (empty($this->_hLogPocket)) {
			$this->_hLogPocket = fopen("log_pocket.txt", "wb");
		}
		$sLog = "\n - ".date("H:i:s")." - ".($bSend ? "SEND >>" : "RECV <<")." :\n".$sMessage."\n";
		fwrite($this->_hLogPocket, $sLog);
	}

	/*
	 *  some utility methods
	 */

	// XML char filter (see also $this->_aXMLChar)
	protected function _xmlOut($sContent) {
		if (empty($this->_aXMLCharOut)) {
			foreach ($this->_aXMLChar as $sChar) {
				$this->_aXMLCharOut[] = "&#".sprintf("%02d", ord($sChar)).";";
			}
		}
		$sContent = str_replace($this->_aXMLChar, $this->_aXMLCharOut, $sContent);
		return $sContent;
	}

	protected function _getUniqueID($sType = null) {
		$this->_iUniqueID++;
		return $this->_iUniqueID;
		// I think there is no need to use complex unique id now, like following line
		return $sType."_".sprintf("%08s", dechex(crc32($sType."\n".microtime(TRUE)."\n".$this->sPassword."\n".$this->sHostTo."\n".$this->sUser."\n".$this->_iUniqueID)));
	}

	protected function _getCleanJID($sJID) {
		$aJID = explode("/", $sJID, 2);
		return $aJID[0];
	}
}
?>
