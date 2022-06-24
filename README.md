# PHP fetch mail

PHP-library to fetch e-mails from an SMTP mailbox to store the e-mail and attachment(s).
It's based on https://github.com/kirilkirkov/PHP-IMAP-Messages-Fetcher and extended with the option to use it via command line .

> This script allows to fetch e-mails from a mailbox via PHP IMAP functions.
> It reads the mails and write them to .eml-files and also writes the attachments if some exist.

The following PHP extensions are needed to run the project:

* ext-json
* ext-simplexml
* ext-imap
* ext-mbstring
* ext-iconv

The installation with the package manager [Composer](https://getcomposer.org/) is recommended.

Install it with **composer require ehaerer/php-fetch-mail**.

## 1 Features

* Use it via commandline
* Use it via webserver

## 2 Usage

### 2.1 Minimal setup

#### Create config file

Copy the file config.sample.php within the /login folder to /login/config.php and add the credentials for your mailbox.

### 2.2 Execute

#### Execute via command line

On Windows you can execute the script via commandline with PHP .exe build files:

```
C:\mypath-to-php\php-7.4.15\php.exe -e -c C:\mypath-to-php\php-7.4.15\php.ini C:\mypath-to-fetch-mail\fetch-mail\public\index.php --inputDir "C:\mypath-to-input-dir\fetch-mail\public\input\" --credentials 0 --allowedFileExt pdf  --saveToFile 1
```

Possible arguments via commandline:

---

```
--inputDir "C:\mypath-to-input-dir\fetch-mail\public\input\"
```

Full path to the input directory where to write the .eml files and attachments.

---

```
--limit 10
```

The limit of files to read from mailbox. Default is 100.

---

```
--credentials key
```

The key of the credentials to read from config.php file. It is possible to define multiple credentials there.

---

```
--allowedFileExt pdf,csv,txt
```

The file extensions of allowed file types.

---

```
--saveToFile 0
```

Save email to eml file or not, could be 0 or 1; default is 1.

---

```
--delete 0
```

Delete email after reading it from server, could be 0 or 1; default is 0.

---

```
--mailTargetFolder "INBOX/Processed"
```

Move email after reading it from server to this destination folder on server, e.g. to "INBOX/Processed".

---

```
--attachmentsRequired 1
```

If set, only mails with the attachments defined in allowedFileExt will be processed; could be 0 or 1; default is 0.

---

#### Execute via webserver, e.g. ddev

Use a local development environment like [ddev local](https://github.com/drud/ddev/) to use a local webserver.

Point your webserver to the public folder (a ddev configuration is still in the project).

Run the local URL https://php-fetch-mail.ddev.site/index.php e.g. with ddev.

