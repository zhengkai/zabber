#! /usr/bin/env php
<?php
require_once __DIR__ . '/bootstrap.php';

$s = file_get_contents(__DIR__ . '/last.xml');

// echo $s = XMLUtil::pretty($s);

// echo jsonf(XMLUtil::getPrettyError());

echo jsonf(XMLUtil::toArray($s));
