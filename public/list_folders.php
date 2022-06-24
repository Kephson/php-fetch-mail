<?php

$privatePath = str_replace('public', '', __DIR__);
require $privatePath . 'Classes/Imap.php';

use EHAERER\FetchMail\Imap;

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
$imap->listFolders();
$imap->disconnect();
exit(0);
