#! /usr/bin/env php
<?php
require_once __DIR__ . '/bootstrap.php';

$s = "<stream:features><stream:stream xmlns:stream='http://etherx.jabber.org/streams' xmlns='jabber:client' version='1.0' from='chat.hipchat.com' id='19c3660fd55632e7'><stream:features><auth xmlns='http://jabber.org/features/iq-auth'/><auth xmlns='http://hipchat.com'/><mechanisms xmlns='urn:ietf:params:xml:ns:xmpp-sasl'><mechanism>PLAIN</mechanism><mechanism>X-HIPCHAT-OAUTH2</mechanism></mechanisms><authrestartlogic xmlns='http://hipchat.com'/><compression xmlns='http://jabber.org/features/compress'><method>zlib</method></compression></stream:features>";

$s = '<stream:stream xmlns:stream="http://etherx.jabber.org/streams" xmlns="jabber:client" version="1.0" from="chat.hipchat.com" id="0a45597b3986aba3">
  <stream:features>
    <starttls xmlns="urn:ietf:params:xml:ns:xmpp-tls">
      <required/>
    </starttls>
  </stream:features>
</stream:stream>';

$s = file_get_contents(__DIR__ . '/demo.xml');

echo $s = XMLUtil::pretty($s);

print_r(XMLUtil::getPrettyError());

print_r(XMLUtil::toArray($s));
