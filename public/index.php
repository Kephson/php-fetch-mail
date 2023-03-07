<?php

$privatePath = str_replace('public', '', __DIR__);
require $privatePath . 'Classes/Imap.php';

use EHAERER\FetchMail\Imap;

$options = [
    'DISABLE_AUTHENTICATOR' => 'GSSAPI',
];

$imap = new Imap();
/* connect with credentials from config file */
$connection_result = $imap->connect(0, null, 1, $options);
/* second option, directly send the credentials
$connection_result = $imap->connect(0, null, 1, $options, [
    'hostname' => '{myimap-server.com.com:993/imap/ssl}',
    'defaultFolder' => 'INBOX',
    'username' => '',
    'password' => '',
]);
*/
if ($connection_result !== true) {
    print 'No results';
    exit;
}

/* list all folders in mailbox */
//$imap->listFolders();

print "--- Reading mailbox... ---";
$messages = $imap->getMessages('desc', true, 100, 'input/', '', false, '', false);
//$imap->deleteAllMessages($messages);
$imap->disconnect();
$imap->printOutMessages($messages);
exit(0);
