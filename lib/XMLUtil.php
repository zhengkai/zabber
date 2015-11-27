<?php
class XMLUtil {

	public static function pretty($sXML) {
		$o = new DOMDocument('1.0');
		$o->preserveWhiteSpace = FALSE;
		$o->formatOutput = TRUE;
		$o->loadXML($sXML);
		return $o->saveXML();
	}
}
