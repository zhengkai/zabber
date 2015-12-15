#! /usr/bin/env php
<?php
require_once __DIR__ . '/bootstrap.php';

$oBot = new Bot();

$aConfig = require __DIR__ . '/config.inc.php';

$oBot->sLogPath = __DIR__;

$oBot->sHost = $aConfig['host'];
$oBot->iPort = $aConfig['port'];

$oBot->sUser = $aConfig['user'];
$oBot->sPassword = $aConfig['password'];

$oBot->run();
