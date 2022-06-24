<?php

$privatePath = str_replace('public', '', __DIR__);
require $privatePath . 'Classes/Imap.php';

use EHAERER\Mail\Imap;

$options = [
    'DISABLE_AUTHENTICATOR' => 'GSSAPI',
];

$imap = new Imap();
$connection_result = $imap->connect(1, null, 1, $options);
if ($connection_result !== true) {
    print 'No results';
    exit;
}

print "--- Reading mailbox... ---";
$messages = $imap->getMessages('desc', true, 100, 'input/', '', false, '', false);
//$imap->deleteAllMessages($messages);
$imap->disconnect();
$imap->printOutMessages($messages);
exit(0);
