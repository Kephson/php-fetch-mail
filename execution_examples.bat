REM example listing of mailbox folders
C:\mypath-to-php\php-7.4.15\php.exe C:\mypath-to-fetch-mail\fetch-mail\public\list_folders.php -c C:\mypath-to-php\php-7.4.15\php.ini --credentials 1

REM example without writing mail to eml file and only saving pdf attachments
C:\mypath-to-php\php-7.4.15\php.exe C:\mypath-to-fetch-mail\fetch-mail\public\index.php -c C:\mypath-to-php\php-7.4.15\php.ini --credentials 1 --allowedFileExt pdf --inputDir "C:\\my-path-to-input-folder\\input-test\\with spaces\\" --saveToFile 0

REM example with writing email to eml file, saving only pdf and png attachments, moving mails to given target folder and do not delete them
C:\mypath-to-php\php-7.4.15\php.exe C:\mypath-to-fetch-mail\fetch-mail\public\index.php -c C:\mypath-to-php\php-7.4.15\php.ini --credentials 1 --allowedFileExt pdf,png --inputDir "C:\\my-path-to-input-folder\\input-test\\with spaces\\" --saveToFile 1 --targetFolder "INBOX/Processed" --delete 0

REM example without writing email to eml file, saving only pdf attachments, moving mails to given target folder and process only emails with attachment
C:\mypath-to-php\php-7.4.15\php.exe C:\mypath-to-fetch-mail\fetch-mail\public\index.php -c C:\mypath-to-php\php-7.4.15\php.ini --credentials 1 --allowedFileExt pdf --inputDir "C:\\my-path-to-input-folder\\input-test\\with spaces\\" --saveToFile 0 --targetFolder "INBOX/Processed" --attachmentsRequired 1
