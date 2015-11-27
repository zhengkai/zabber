<?php
$lList = stream_get_transports();

print_r($lList);

echo in_array('tls', $lList) ? '[  OK  ]' : '[ Fail ]';
