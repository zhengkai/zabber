<?php
function json($mData = null) {
	return json_encode($mData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function jsonf($mData = null) {
	return json_encode($mData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
