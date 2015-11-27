<?php
require_once __DIR__ . '/legacy/xmlize.inc.php';

$s = file_get_contents(__DIR__ . '/demo.xml');
print_r(xmlize($s));
