<?php
// Run in SSH/CLI only. Does not connect to the database or change configuration.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$failures=0;
function php84_check($ok,$message) {
    global $failures;
    echo ($ok?'PASS: ':'FAIL: ').$message.PHP_EOL;
    if(!$ok) $failures++;
}
echo 'PHP runtime: '.PHP_VERSION.' ('.PHP_SAPI.')'.PHP_EOL;
php84_check(PHP_VERSION_ID>=80427 && PHP_VERSION_ID<80500, 'Target is PHP 8.4.27 or newer within 8.4; vendor backports require separate confirmation.');
foreach(array('mysqli','mysqlnd','mbstring','iconv','curl','openssl','fileinfo','gd','zip','dom','SimpleXML','xml','zlib','session','ctype','filter','tokenizer') as $ext) {
    php84_check(extension_loaded($ext),'Extension '.$ext);
}
php84_check(function_exists('mysqli_stmt_get_result'),'mysqli prepared-result support');
foreach(array('vendor/autoload.php','fpdf181/fpdf.php','third_party/fpdi/src/autoload.php') as $file) {
    php84_check(is_readable(dirname(__DIR__).'/'.$file),'Library deployed: '.$file);
}
php84_check(is_writable(sys_get_temp_dir()),'Temporary directory writable');
php84_check(is_dir(dirname(__DIR__).'/uploads') && is_writable(dirname(__DIR__).'/uploads'),'Uploads directory writable');
echo 'CLI and website PHP settings may differ: verify the website runtime with Hostinger too.'.PHP_EOL;
echo 'SmartMark Python service, PDF command-line helpers, database access and external services need separate staging checks.'.PHP_EOL;
exit($failures?1:0);
