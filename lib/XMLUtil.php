<?php
class XMLUtil {

	protected static $_lPrettyError = [];

	/**
	 * 美观输出 XML
	 *
	 * @param string $sXML
	 * @static
	 * @access public
	 * @return string
	 */
	public static function pretty($sXML, $bStoreError = FALSE) {

		$o = new DOMDocument('1.0');
		$o->recover = TRUE;
		$o->strictErrorChecking = TRUE;
		$o->preserveWhiteSpace = FALSE;
		$o->formatOutput = TRUE;

		libxml_use_internal_errors(TRUE);
		$o->loadXML($sXML);
		if ($bStoreError) {
			self::$_lPrettyError = libxml_get_errors();
		}
		libxml_clear_errors();

		$s = $o->saveXML();
		if ($s === "<?xml version=\"1.0\"?>\n") {
			return FALSE;
		}
		return $s;
	}

	/**
	 * 获取美化 xml 输出时产生的报错
	 *
	 * @static
	 * @access public
	 * @return array
	 */
	public static function getPrettyError() {
		return self::$_lPrettyError;
	}

	/**
	 * 将 xml 转为方便 php 读取的数组
	 *
	 * 参考自 xmlize（by Hans Anderson, me@hansanderson.com）
	 *
	 * @param string $sXML
	 * @static
	 * @access public
	 * @return array
	 */
	public static function toArray($sXML, $sEncoding = 'UTF-8') {

		$lIndex  = [];
		$lVal    = [];
		$lReturn = [];

		$sXML = trim($sXML);
		$oParser = xml_parser_create($sEncoding);
		xml_parser_set_option($oParser, XML_OPTION_CASE_FOLDING, 0);
		xml_parser_set_option($oParser, XML_OPTION_SKIP_WHITE, 1);
		xml_parse_into_struct($oParser, $sXML, $lConvert);
		xml_parser_free($oParser);

		if (!$lConvert) {
			throw new Exception('XML Syntax error');
		}

		return self::_toArray($lConvert);
	}

	/**
	 * 怕函数太长所以把核心部分摘出来
	 *
	 * 想法就是通过引用（=&）来避免递归，
	 * 因为还有回退，所以做了个引用池（$lPointer）
	 *
	 * @static
	 * @access protected
	 * @return array
	 */
	protected static function _toArray($lConvert) {

		$lReturn = [];

		$iPointer = 0;
		$lPointer = [];

		$lPointer[$iPointer] =& $lReturn;

		foreach ($lConvert as $aRow) {

			$aRow += [
				'attributes' => [],
				'value' => FALSE,
			];

			switch ($aRow['type']) {

				case 'open':

					$iNextPointer = $iPointer + 1;
					$lPointer[$iNextPointer] =& $lPointer[$iPointer]['#'][$aRow['tag']][];
					$iPointer = $iNextPointer;

					$lPointer[$iPointer] = [
						'@' => $aRow['attributes'],
						'#' => $aRow['value'] ? [$aRow['value']] : [],
					];

					break;

				case 'complete':

					$lPointer[$iPointer]['#'][$aRow['tag']][] = [
						'@' => $aRow['attributes'],
						'#' => $aRow['value'] ?: '',
					];
					break;

				case 'cdata':

					$lPointer[$iPointer]['#'][] = $aRow['value'];
					break;

				case 'close':

					$lSub = $lPointer[$iPointer]['#'];
					if ($lSub = self::_removeLayer($lSub)) {
						$lPointer[$iPointer]['#'] = $lSub;
					}

					unset($lPointer[$iPointer]);
					$iPointer--;
					break;
			}
		}

		// 保证不完整的 xml（如“<a><b/>”）也能正确排除多余数组层
		foreach (range($iPointer, 0) as $i) {
			$lSub = $lPointer[$i]['#'];
			if ($lSub && $lSub = self::_removeLayer($lSub)) {
				$lPointer[$i]['#'] = $lSub;
			}
			unset($lPointer[$i]);
		}

		$lReturn = $lReturn['#'];
		$sKey = key($lReturn);
		$lReturn = [
			$sKey => $lReturn[$sKey],
		];

		return $lReturn;
	}

	/**
	 * _removeLayer
	 *
	 * @param array $lSub
	 * @static
	 * @access protected
	 * @return array|false
	 */
	protected static function _removeLayer(array $lSub) {

		$bChange = FALSE;
		foreach ($lSub as $sTag => $lRow) {
			if (!is_array($lRow)) {
				continue;
			}
			if (count($lRow) === 1) {
				$bChange = TRUE;
			}
			$lSub[$sTag] = current($lRow);
		}

		return $bChange ? $lSub : FALSE;
	}
}
