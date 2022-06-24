<?php
/** @noinspection PhpMissingParamTypeInspection */

namespace EHAERER\FetchMail;

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2021-2022 Ephraim Härer <mail@ephra.im>
 *  GitHub repo: https://github.com/kephson
 *  This class is based on https://github.com/kirilkirkov/PHP-IMAP-Messages-Fetcher
 *  Usage example:
 *  1.  $imap = new Imap();
 *  2.  $imap->connect($credentialsIdFromConfigFile, $imapOpenOptions, $imapOpenNRetries, $imapOpenParams);
 *  3.  $messages = $imap->getMessages($sorting, $saveToFile, $limit, $inputDir); // returns an array of messages and writes them to file if set
 *  4a. $imap->deleteAllMessages($messages); // deletes all messages from mailbox in given folder
 *  4b. $imap->deleteMessage($messageId); // delete a single message by uid
 *  5.  $imap->disconnect(); // closes the connection
 *  6.  $imap->printOutMessages($messages); // printout messages (e.g. to browser or commandline)
 *  in $inputDir property set directory for attachments and eml files
 *  in the __destructor set errors log
 * ************************************************************* */

use Exception;
use RuntimeException;

class Imap
{

    /**
     * @var
     */
    private $imapStream;

    /**
     * @var string
     */
    private $plaintextMessage;

    /**
     * @var string
     */
    private $htmlMessage;

    /**
     * @var array|bool
     */
    private $emails = [];

    /**
     * @var array
     */
    private $errors = [];

    /**
     * @var array
     */
    private $inlineAttachments = [];

    /**
     * @var array
     */
    private $attachments = [];

    /**
     * @var string
     */
    private $inputDir;

    /**
     * Default read limit of mails
     *
     * @var int
     */
    private $limit = 100;

    /**
     * @var object
     */
    private $headers;

    /**
     * filename of the attachment
     *
     * @var string
     */
    private $filename = '';

    /**
     * extension of the attachment
     *
     * @var string
     */
    private $fileExt = '';

    /**
     * @var string
     */
    private $encoding = '';

    /**
     * @var array
     */
    private $rawMail = [
        'header' => '',
        'body' => '',
    ];

    /**
     * Private path of the classes
     *
     * @var string
     */
    private $privatePath;

    /**
     * read argv from CLI
     *
     * @param array
     */
    private $argv = [];

    /**
     * stored login credentials from config.php
     * login credentials
     *
     * @param array
     */
    private $loginCredentials = [];

    /**
     * Only write attachments with this extensions to file system
     *
     * @var array
     */
    private $allowedFileExt = [];

    /**
     * Only process mail if the required attachments from allowedFileExt could be found
     *
     * @var bool
     */
    private $attachmentsRequired = false;

    /**
     * Save message to file system as eml file
     *
     * @var bool
     */
    private $saveToFile = true;

    /**
     * Delete message on IMAP server after reading
     *
     * @var bool
     */
    private $delete = false;

    /**
     * Move message to this target folder on IMAP server after reading
     *
     * @var string
     */
    private $mailTargetFolder = '';

    /**
     * Imap constructor.
     */
    public function __construct()
    {
        $this->privatePath = str_replace('Classes', '', __DIR__);
        if (isset($GLOBALS['argv']) && $this->isCli()) {
            $this->argv = $GLOBALS['argv'];
            $this->setCliParams();
        }
    }

    /**
     * @param int $limit Limit of mails
     * @return void
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @param string $inputDir Absolute path to save the files to
     */
    public function setInputDir(string $inputDir): void
    {
        if (!is_dir($inputDir) && !mkdir($inputDir, 0777, true) && !is_dir($inputDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $inputDir));
        }
        $this->inputDir = $inputDir;
    }

    /**
     * set the login credentials of the mailbox, exit if no credentials could be found
     *
     * @param mixed $key
     * @return void
     */
    public function setCredentials($key): void
    {
        $configFile = $this->privatePath . 'login/config.php';
        $loginDataFound = false;
        if (is_file($configFile)) {
            $loginData = include $this->privatePath . 'login/config.php';
            if (isset($loginData[$key])) {
                $this->loginCredentials = $loginData[$key];
                $loginDataFound = true;
            }
        }
        if (!$loginDataFound) {
            print 'No config file found!';
            exit(-1);
        }
    }

    /**
     * @param string $allowedFileExt
     * @return void
     */
    public function setAllowedFileExt(string $allowedFileExt): void
    {
        if (!empty($allowedFileExt)) {
            $this->allowedFileExt = explode(',', $allowedFileExt);
        }
    }

    /**
     * @param bool $saveToFile
     * @return void
     */
    public function setSaveToFile(bool $saveToFile): void
    {
        $this->saveToFile = $saveToFile;
    }

    /**
     * @param int $credentials
     * @param int $options
     * @param int $n_retries
     * @param array $params
     * @return bool
     */
    public function connect($credentials = 0, $options = 0, $n_retries = 0, $params = [])
    {
        if (!$this->isCli()) {
            $this->setCredentials($credentials);
        }
        $connection = imap_open(
            $this->loginCredentials['hostname'] . $this->loginCredentials['defaultFolder'],
            $this->loginCredentials['username'],
            $this->loginCredentials['password'],
            $options,
            $n_retries,
            $params
        ) or die('Cannot connect to Mail: ' . imap_last_error());
        $this->imapStream = $connection;
        return true;
    }

    /**
     * @return bool
     */
    public function disconnect()
    {
        return imap_close($this->imapStream);
    }

    /**
     * Read messages from folder, save to eml file if second param is true
     *
     * @param string $sort
     * @param bool $saveToFile
     * @param int $limit
     * @param string $inputDir
     * @param string $allowedFileExt
     * @param bool $delete Delete message after reading
     * @param string $mailTargetFolder Move message to folder after reading
     * @param bool $attachmentsRequired
     * @return array
     * @throws Exception
     */
    public function getMessages(
        $sort = 'asc',
        $saveToFile = false,
        $limit = 100,
        $inputDir = 'input/',
        $allowedFileExt = '',
        $delete = false,
        $mailTargetFolder = '',
        $attachmentsRequired = false
    )
    {
        if (!$this->isCli()) {
            $this->setLimit($limit);
            $this->setInputDir($inputDir);
            $this->setAllowedFileExt($allowedFileExt);
            $this->setSaveToFile($saveToFile);
            $this->delete = $delete;
            $this->mailTargetFolder = $mailTargetFolder;
            $this->attachmentsRequired = $attachmentsRequired;
        }
        $this->emails = imap_search($this->imapStream, 'ALL');
        $messages = [];
        if ($this->emails) {
            if (strtolower($sort) === 'desc') {
                krsort($this->emails);
            }
            $i = 1;
            foreach ($this->emails as $emailNumber) {
                /* clear attachments */
                $this->attachments = [];
                $uid = imap_uid($this->imapStream, $emailNumber);
                $messages[$emailNumber] = $this->loadMessage($uid, $emailNumber);
                $process = $messages[$emailNumber]['process'];
                if ($this->delete && $process) {
                    $this->deleteMessage($uid);
                }
                if (!empty($this->mailTargetFolder) && $process) {
                    $this->moveMessage($uid);
                }
                if ($i === $this->limit) {
                    break;
                }
                $i++;
            }
        }
        return $messages;
    }

    private function setCliParams()
    {
        if (!empty($this->argv)) {
            $argv = [];
            foreach ($this->argv as $k => $val) {
                if ($k > 0 && strpos($val, '--') !== false) {
                    $key = str_replace('--', '', $val);
                    $searchKey = $k + 1;
                    if (isset($this->argv[$searchKey])) {
                        $argv[$key] = $this->argv[$searchKey];
                    } else {
                        $argv[$key] = '';
                    }
                }
            }
            if (isset($argv['limit'])) {
                $this->setLimit((int)$argv['limit']);
            }
            if (isset($argv['inputDir'])) {
                $this->setInputDir($argv['inputDir']);
            }
            if (isset($argv['credentials'])) {
                $this->setCredentials($argv['credentials']);
            }
            if (isset($argv['allowedFileExt'])) {
                $this->setAllowedFileExt($argv['allowedFileExt']);
            }
            if (isset($argv['saveToFile'])) {
                $this->setSaveToFile((bool)$argv['saveToFile']);
            }
            if (isset($argv['delete'])) {
                $this->delete = (bool)$argv['delete'];
            }
            if (isset($argv['mailTargetFolder'])) {
                $this->mailTargetFolder = (string)$argv['mailTargetFolder'];
            }
            if (isset($argv['attachmentsRequired'])) {
                $this->attachmentsRequired = (bool)$argv['attachmentsRequired'];
            }
        }
    }

    /**
     * Give message_number from
     * returned array from - getMessages
     * if you want to delete message by UID, not Message number
     * set FT_UID to $uid.
     * Example $imap->deleteMessage(221); - 221 is the uid of the email
     *
     * @param int $uid
     */
    public function deleteMessage($uid = 0)
    {
        imap_delete($this->imapStream, $uid, FT_UID);
        /* really delete the as delete marked files */
        imap_expunge($this->imapStream);
    }

    /**
     * @param array $messages
     */
    public function deleteAllMessages($messages)
    {
        foreach ($messages as $message) {
            imap_delete($this->imapStream, $message['uid'], FT_UID);
        }
        /* really delete the as delete marked files */
        imap_expunge($this->imapStream);
    }

    /**
     * move message to target folder
     * Example $imap->moveMessage(221); - 221 is the number of the message
     *
     * @param int uid
     */
    public function moveMessage($uid = 0)
    {
        if ($uid > 0) {
            imap_mail_move($this->imapStream, $uid, $this->mailTargetFolder, FT_UID);
            imap_expunge($this->imapStream);
        }
    }

    /**
     * @param int $uid
     * @param int $emailNumber
     * @return array
     * @throws Exception
     */
    private function loadMessage($uid, $emailNumber): array
    {
        $overview = $this->getOverview($uid);

        $message = [];
        $message['subject'] = isset($overview->subject) ? $this->decode($overview->subject) : '';
        $message['date'] = strtotime($overview->date);
        $message['message_id'] = $overview->message_id;
        $message['message_number'] = $emailNumber;
        $message['uid'] = $overview->uid;
        $message['references'] = $overview->references ?? 0;

        $headers = $this->getHeaders($uid);
        $message['from'] = isset($headers->from) ? $this->processAddressObject($headers->from) : [''];
        $message['cc'] = isset($headers->cc) ? $this->processAddressObject($headers->cc) : [''];

        $structure = $this->getStructure($uid);
        if (!isset($structure->parts)) {
            // not multipart
            $this->processStructure($uid, $structure, '', $message['date']);
        } else {
            // multipart
            foreach ($structure->parts as $id => $part) {
                $this->processStructure($uid, $part, $id + 1, $message['date']);
            }
        }
        $message['message']['text'] = $this->replaceInlineImagesSrcWithRealPath($this->plaintextMessage);
        $message['message']['html'] = $this->replaceInlineImagesSrcWithRealPath($this->htmlMessage);
        $message['attachments'] = $this->attachments;
        $message['process'] = true;
        if (empty($this->attachments) && $this->attachmentsRequired) {
            $message['process'] = false;
        }
        $message['raw'] = $this->rawMail;

        if ($this->saveToFile) {
            $this->saveMailToFile($message);
        }

        return $message;
    }

    /**
     * @param int $uid
     * @param object|null $structure
     * @param string $partIdentifier
     * @param string|int $date
     * @return void
     * @throws Exception
     */
    private function processStructure($uid, $structure, $partIdentifier = '', $date = 0): void
    {
        if ($date === 0) {
            $date = time();
        }
        $parameters = $this->getParametersFromStructure($structure);

        if ((isset($parameters['name']) || isset($parameters['filename'])) ||
            (isset($structure->subtype) && strtolower($structure->subtype) === 'rfc822')
        ) {
            if (isset($parameters['filename'])) {
                $this->setFileName($parameters['filename'], $uid, $date);
            } elseif (isset($parameters['name'])) {
                $this->setFileName($parameters['name'], $uid, $date);
            }
            $this->encoding = $structure->encoding;
            $resultSave = $this->saveToDirectory($this->inputDir, $uid, $partIdentifier);
            if ($resultSave === true) {
                $this->attachments[] = $this->filename;
            }
            /*
             * If there is an inline image in email body
             * set array with key of cid and value of filename
             * after that we replace it in html body
             */
            if ($parameters['disposition'] === 'INLINE') {
                $parameters['id'] = str_replace('<', '', $parameters['id']);
                $parameters['id'] = str_replace('>', '', $parameters['id']);
                $this->inlineAttachments[$parameters['id']] = $this->filename;
            }
        } elseif ($structure->type === 0 || $structure->type === 1) {
            if (isset($partIdentifier)) {
                $messageBody = imap_fetchbody($this->imapStream, $uid, $partIdentifier, FT_UID | FT_PEEK);
                $messageBodyRaw = imap_body($this->imapStream, $uid, FT_UID | FT_PEEK);
            } else {
                $messageBody = imap_body($this->imapStream, $uid, FT_UID | FT_PEEK);
                $messageBodyRaw = $messageBody;
            }
            $this->rawMail['body'] = $messageBodyRaw;
            $messageBody = $this->decodeMessage($messageBody, $structure->encoding);
            $this->encoding = $structure->encoding;
            if (!empty($parameters['charset']) && $parameters['charset'] !== 'UTF-8') {
                if (function_exists('mb_convert_encoding')) {
                    if (!in_array($parameters['charset'], mb_list_encodings(), true)) {
                        if ($structure->encoding === 0) {
                            $parameters['charset'] = 'US-ASCII';
                        } else {
                            $parameters['charset'] = 'UTF-8';
                        }
                    }
                    $messageBody = mb_convert_encoding($messageBody, 'UTF-8', $parameters['charset']);
                } else {
                    $messageBody = iconv($parameters['charset'], 'UTF-8//TRANSLIT', $messageBody);
                }
            }

            if (strtolower($structure->subtype) === 'plain' || ($structure->type === 1 && strtolower(
                        $structure->subtype
                    ) !== 'alternative')) {
                $this->plaintextMessage = '';
                $this->plaintextMessage .= trim(htmlentities($messageBody));
                $this->plaintextMessage = nl2br($this->plaintextMessage);
            } elseif (strtolower($structure->subtype) === 'html') {
                $this->htmlMessage = '';
                $this->htmlMessage .= $messageBody;
            }
        }
        if (isset($structure->parts)) {
            foreach ($structure->parts as $partIndex => $part) {
                $partId = $partIndex + 1;
                if (isset($partIdentifier)) {
                    $partId = $partIdentifier . '.' . $partId;
                }
                $this->processStructure($uid, $part, $partId, $date);
            }
        }
    }

    /**
     * @param string $message
     * @return false|mixed|string
     */
    private function replaceInlineImagesSrcWithRealPath($message)
    {
        /*
         * If have inline attachments saved
         * replace images src with real path of attachments if are same
         */
        if (isset($this->inlineAttachments) && !empty($this->inlineAttachments)) {
            preg_match('/"cid:(.*?)"/', $message, $cids);
            if (!empty($cids)) {
                $message = mb_ereg_replace(
                    '/"cid:(.*?)"/',
                    '"' . $this->inputDir . $this->inlineAttachments[$cids[1]] . '"',
                    $message
                );
            }
        }
        return $message;
    }

    /**
     * @param string $text
     * @param int $mailUid
     * @param string|int $date
     * @param int $append
     * @return void
     */
    private function setFileName($text, $mailUid, $date, $append = '')
    {
        $appendix = $append ? '_' . $append : '';
        $this->fileExt = pathinfo($this->decode($text), PATHINFO_EXTENSION);
        $filename = str_replace('.' . $this->fileExt, '', $text) . $appendix . '.' . $this->fileExt;
        if (file_exists($this->inputDir . $filename)) {
            $appendRaised = $append + 1;
            $this->setFileName($text, $mailUid, $date, $appendRaised);
        }
        $this->filename = $filename;
    }

    /**
     * save attachments to directory
     *
     * @param string $path
     * @param int $uid
     * @param string $partIdentifier
     * @return bool
     * @throws Exception
     */
    private function saveToDirectory($path, $uid, $partIdentifier = ''): bool
    {
        $allowSave = true;
        $result = false;
        if (!empty($this->allowedFileExt) && !in_array(strtolower($this->fileExt), $this->allowedFileExt, true)) {
            $allowSave = false;
        }

        /* save only if file extension is allowed or not set */
        if ($allowSave) {
            $path = rtrim($path, '/') . '/';

            if (!is_writable($path)) {
                $this->errors[] = 'Attachments directory is not writable! Message ID:' . $uid;
                return false;
            }

            if (false === ($filePointer = fopen($path . $this->filename, 'wb+'))) {
                $this->errors[] = 'Cant open file at imap class to save attachment file! Message ID:' . $uid;
                return false;
            }

            switch ($this->encoding) {
                case 3: //base64
                    $streamFilter = stream_filter_append($filePointer, 'convert.base64-decode', STREAM_FILTER_WRITE);
                    break;

                case 4: //quoted-printable
                    $streamFilter = stream_filter_append(
                        $filePointer,
                        'convert.quoted-printable-decode',
                        STREAM_FILTER_WRITE
                    );
                    break;

                default:
                    $streamFilter = null;
            }

            $result = imap_savebody($this->imapStream, $filePointer, $uid, $partIdentifier ?: 1, FT_UID);
            if ($streamFilter) {
                stream_filter_remove($streamFilter);
            }
            fclose($filePointer);
        }
        return $result;
    }

    /**
     * @param string $data
     * @param string $encoding
     * @return string
     */
    private function decodeMessage($data, $encoding): string
    {
        if (!is_numeric($encoding)) {
            $encoding = strtolower($encoding);
        }
        switch ($encoding) {
            # 8BIT
            case 1:
                return quoted_printable_decode(imap_8bit($data));
            # BINARY
            case 2:
                return imap_binary($data);
            # BASE64
            case 3:
                return imap_base64($data);
            # QUOTED-PRINTABLE
            case 4:
                return quoted_printable_decode($data);
            # 7BIT, OTHER or UNKNOWN
            default:
                return $data;
        }
    }

    /**
     * @param object|null $structure
     * @return array
     */
    private function getParametersFromStructure($structure)
    {
        $parameters = [];
        if (isset($structure->parameters)) {
            foreach ($structure->parameters as $parameter) {
                $parameters[strtolower($parameter->attribute)] = $parameter->value;
            }
        }
        if (isset($structure->dparameters)) {
            foreach ($structure->dparameters as $parameter) {
                $parameters[strtolower($parameter->attribute)] = $parameter->value;
            }
        }
        if ($structure->ifdisposition) {
            $parameters['disposition'] = $structure->disposition;
        }
        if ($structure->ifid) {
            $parameters['id'] = $structure->id;
        }

        return $parameters;
    }

    /**
     * @param int $uid
     * @return mixed
     */
    private function getOverview($uid)
    {
        $results = imap_fetch_overview($this->imapStream, $uid, FT_UID);
        $messageOverview = array_shift($results);
        if (!isset($messageOverview->date)) {
            $messageOverview->date = null;
        }
        return $messageOverview;
    }

    /**
     * @param string|null $text
     * @return string|null
     */
    private function decode($text)
    {
        if (null === $text) {
            return null;
        }
        $result = '';
        foreach (imap_mime_header_decode($text) as $word) {
            $ch = 'default' === $word->charset ? 'ascii' : $word->charset;
            $result .= iconv($ch, 'utf-8', $word->text);
        }
        return $result;
    }

    /**
     * @param object|null $addresses
     * @return array
     */
    private function processAddressObject($addresses)
    {
        $outputAddresses = array();
        if (is_array($addresses) && count($addresses) > 0) {
            foreach ($addresses as $address) {
                if (property_exists($address, 'mailbox') && $address->mailbox !== 'undisclosed-recipients') {
                    $currentAddress = array();
                    $currentAddress['address'] = $address->mailbox . '@' . $address->host;
                    if (isset($address->personal)) {
                        $currentAddress['name'] = $this->decode($address->personal);
                    }
                    $outputAddresses[] = $currentAddress;
                }
            }
        }
        return $outputAddresses;
    }

    /**
     * @param int $uid
     * @return object
     */
    private function getHeaders($uid)
    {
        $rawHeaders = $this->getRawHeaders($uid);
        $headerObject = imap_rfc822_parse_headers($rawHeaders);
        if (isset($headerObject->date)) {
            $headerObject->udate = strtotime($headerObject->date);
        } else {
            $headerObject->date = null;
            $headerObject->udate = null;
        }
        $this->rawMail['header'] = $rawHeaders;
        $this->headers = $headerObject;
        return $this->headers;
    }

    /**
     * @param int $uid
     * @return string
     */
    private function getRawHeaders($uid)
    {
        return imap_fetchheader($this->imapStream, $uid, FT_UID);
    }

    /**
     * @param int $uid
     * @return object
     */
    private function getStructure($uid)
    {
        return imap_fetchstructure($this->imapStream, $uid, FT_UID);
    }

    /**
     * save errors to error log file
     */
    public function __destruct()
    {
        $directory = $this->privatePath . 'logs/';
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }
        $file = $directory . 'errors.txt';
        if (!empty($this->errors)) {
            foreach ($this->errors as $error) {
                $fileHandle = fopen($file, 'ab+');
                fwrite($fileHandle, "\r\n" . date('d.m.Y H:i') . ' - ' . $error);
                fclose($fileHandle);
            }
        }
    }

    /**
     * @param array $message The preprocessed mail
     * @return bool
     */
    private function saveMailToFile(
        $message
    ): bool
    {
        $fileExtension = 'eml';
        $contentFromTemplate = file_get_contents($this->privatePath . "templates/template.eml");
        $content = str_replace(
            ['[TPL_RAW_HEADER]', '[TPL_MESSAGE]'],
            [$this->rawMail['header'], $this->rawMail['body']],
            $contentFromTemplate
        );
        $messageFrom = $message['from'][0]['address'] ? '_' . $message['from'][0]['address'] : '';
        $file = $this->inputDir . date('Ymd-Hi', $message['date']) . $messageFrom . '.' . $fileExtension;
        $fileHandle = fopen($file, 'wb+');
        $success = fwrite($fileHandle, $content);
        fclose($fileHandle);

        return $success;
    }

    /**
     * check if environment is cli
     *
     * @return bool
     */
    public function isCli()
    {
        if (defined('STDIN')) {
            return true;
        }

        if (PHP_SAPI === 'cli') {
            return true;
        }

        if (array_key_exists('SHELL', $_ENV)) {
            return true;
        }

        if (empty($_SERVER['REMOTE_ADDR']) and !isset($_SERVER['HTTP_USER_AGENT']) and count($_SERVER['argv']) > 0) {
            return true;
        }

        if (!array_key_exists('REQUEST_METHOD', $_SERVER)) {
            return true;
        }

        return false;
    }

    /**
     * @param array|null $messages
     * @return void
     */
    public function printOutMessages($messages)
    {
        $messageCount = count($messages);
        $isCli = $this->isCli();
        if ($isCli) {
            $lineBreak = "\r\n";
        } else {
            $lineBreak = "<br />";
        }
        if ($messageCount > 0) {
            foreach ($messages as $message) {
                $processed = $message['process'] ? 'processed' : 'skipped';
                print $lineBreak . $message['uid'] . ' - ' . date(
                        'd.m.Y H:i',
                        $message['date']
                    ) . ' - ' . $message['subject'] . ' - ' . $message['from'][0]['address'] . ' - ' . $processed;
            }
        }
        print $lineBreak . sprintf(
                '--> Read %s message(s) and wrote %s message(s) and attachment(s) to output folder.',
                $messageCount,
                $messageCount
            ) . $lineBreak;
        exit(0);
    }

    /**
     * list all folders of mailbox
     * Example $imap->listFolders();
     *
     * @return void
     */
    public function listFolders(): void
    {
        $isCli = $this->isCli();
        if ($isCli) {
            $lineBreak = "\r\n";
        } else {
            $lineBreak = "<br />";
        }
        $folders = imap_list($this->imapStream, $this->loginCredentials['hostname'], '*');
        if ($folders === false) {
            print "Failed to list folders in mailbox";
        } else {
            if (is_array($folders)) {
                print $lineBreak . 'Found following folders:' . $lineBreak;
                foreach ($folders as $folder) {
                    print $folder . $lineBreak;
                }
            } else {
                print $lineBreak . "No folders found." . $lineBreak;
            }
        }
    }
}
