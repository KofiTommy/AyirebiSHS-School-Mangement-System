<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
// Read only the markup/script: never execute the login controller or connect to DB.
$source=file_get_contents(__DIR__.'/../index.php');
if(!preg_match('/<form id="formID"\s.*?<\/form>/s',$source,$match))throw new RuntimeException('Login form missing.');
$form=$match[0];
preg_match_all('/<script>.*?<\/script>/s',$source,$scripts);
$script=end($scripts[0]);
$checks=0;
function portal_submit_check($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
$dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML($form);$xpath=new DOMXPath($dom);
portal_submit_check($xpath->query('//input[@type="hidden" and @name="login" and @value="1"]')->length===1,'Persistent login action marker');
portal_submit_check($xpath->query('//button[@id="login" and @name="login"]')->length===0,'Submit button no longer owns action marker');
portal_submit_check($xpath->query('//input[@name="username" and @required]')->length===1,'Username required without plugins');
portal_submit_check($xpath->query('//input[@name="password" and @required]')->length===1,'Password required without plugins');
portal_submit_check(strpos($script,"window.addEventListener('pageshow', resetLoginButton)")!==false,'Back navigation resets loading state');
portal_submit_check(strpos($script,'event.defaultPrevented')!==false,'Cancelled submissions do not lock the button');
portal_submit_check(strpos($script,'<?php')===false && strpos($form,'<?php')===false,'Preview contains no executable PHP');
if(in_array('--preview',$argv,true)){
    $dir=sys_get_temp_dir().'/portal-login-submit-'.bin2hex(random_bytes(5));mkdir($dir);
    $form=str_replace('action="index.php"','action="#" data-portal-login-fixture="1"',$form);
    $css='*{box-sizing:border-box}body{font:15px system-ui;margin:0;padding:20px;background:#f2f7f8}.landing-auth__card{max-width:450px;margin:auto;padding:20px;background:white;border-radius:16px}input:not([type=hidden]):not([type=checkbox]){width:100%;padding:12px;margin:8px 0}button{min-height:44px;padding:10px}.landing-form__meta{margin:12px 0}.pl-caps{display:none}';
    file_put_contents($dir.'/login-submit.html','<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.$css.'</style></head><body><aside id="portal-login"><div class="landing-auth__card"><h1>Login submission test</h1>'.$form.'</div></aside>'.$script.'</body></html>');
    echo 'Preview: '.$dir.PHP_EOL;
}
echo 'PASS: '.$checks.' login submission checks; no accounts or passwords changed.'.PHP_EOL;
