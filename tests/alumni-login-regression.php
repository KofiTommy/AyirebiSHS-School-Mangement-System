<?php
if(PHP_SAPI !== 'cli'){ http_response_code(403); exit; }
require __DIR__.'/../alumni-auth-utils.php';
require __DIR__.'/../dbstring.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
set_error_handler(function($severity,$message,$file,$line){ throw new ErrorException($message,0,$severity,$file,$line); });
$_SESSION=array('alumni_csrf'=>'fixture-token','alumni_join_csrf'=>'join-fixture');
$checks=0;
function login_check($ok,$message){ global $checks; if(!$ok) throw new RuntimeException($message); $checks++; }
// Isolated temporary tables: no real accounts, approvals or login attempts changed.
mysqli_query($con,'CREATE TEMPORARY TABLE tblalumni (alumniid VARCHAR(60) PRIMARY KEY,email VARCHAR(255),mobile VARCHAR(40),passwordhash VARCHAR(255),status VARCHAR(30))');
mysqli_query($con,'CREATE TEMPORARY TABLE tblalumniloginattempt (attemptid INT AUTO_INCREMENT PRIMARY KEY,identityhash CHAR(64),ipaddress VARCHAR(64),success TINYINT,createdat DATETIME DEFAULT CURRENT_TIMESTAMP)');
$hash=password_hash('Fixture-password-123',PASSWORD_DEFAULT);
$stmt=mysqli_prepare($con,'INSERT INTO tblalumni VALUES (?,?,?,?,?)');
foreach(array('active','pending','inactive') as $status){
    $id=$status; $email=$status.'@example.test'; $phone=$status==='active' ? '0240000000' : $status;
    mysqli_stmt_bind_param($stmt,'sssss',$id,$email,$phone,$hash,$status); mysqli_stmt_execute($stmt);
}mysqli_stmt_close($stmt);
function try_login($identity,$password='Fixture-password-123',$token='fixture-token'){
    global $con; return alumni_authenticate_login($con,$identity,$password,'127.0.0.1',$token);
}
$success=try_login('active@example.test');
login_check($success['member_id']==='active' && $success['error']==='','Approved member can log in with email');
login_check(try_login('0240000000')['member_id']==='active','Registered phone works');
login_check(try_login(' active@example.test ')['member_id']==='active','Whitespace around identity ignored');
$invalid=try_login('active@example.test','incorrect');
login_check($invalid['member_id']==='' && $invalid['error']!=='','Incorrect password rejected');
login_check(try_login('pending@example.test')['member_id']==='','Pending registration stays blocked');
login_check(try_login('inactive@example.test')['member_id']==='','Inactive registration blocked');
login_check(try_login('missing@example.test')['error']===$invalid['error'],'Unknown account uses same generic notice');
login_check(try_login("' OR 1=1 --")['member_id']==='','SQL injection-like identity rejected');
foreach(array('', 'wrong-token',array('fixture-token')) as $token) login_check(try_login('active@example.test','Fixture-password-123',$token)['member_id']==='','Missing, invalid or malformed CSRF rejected');
login_check(try_login(array('active@example.test'))['member_id']==='','Malformed identity rejected');
login_check(try_login('active@example.test',array('password'))['member_id']==='','Malformed password rejected');
login_check(try_login('','')['member_id']==='','Empty credentials rejected');
login_check(try_login(str_repeat('a',256))['member_id']==='','Overlong identity rejected');
for($i=0;$i<5;$i++) try_login('rate-limit@example.test','incorrect');
login_check(strpos(try_login('rate-limit@example.test')['error'],'15 minutes')!==false,'Five failed attempts enforce cooldown');
$count=mysqli_fetch_assoc(mysqli_query($con,"SELECT COUNT(*) n FROM tblalumniloginattempt WHERE identityhash='".hash('sha256','rate-limit@example.test')."'"));
login_check((int)$count['n']===5,'Blocked attempts do not proceed to password check/log');
mysqli_query($con,"UPDATE tblalumniloginattempt SET createdat=DATE_SUB(NOW(),INTERVAL 16 MINUTE)");
login_check(strpos(try_login('rate-limit@example.test')['error'],'15 minutes')===false,'Expired attempts no longer block sign-in');
login_check(!isset($_SESSION['ALUMNI_MEMBER']),'Helper does not authenticate a session by itself');
login_check(mysqli_fetch_assoc(mysqli_query($con,"SELECT status FROM tblalumni WHERE alumniid='pending'"))['status']==='pending','Login never approves accounts');
$controller=file_get_contents(__DIR__.'/../alumni-community.php');
login_check(strpos($controller,'session_regenerate_id(true)')!==false,'Controller rotates session on success');
login_check(strpos($controller,"header('location:alumni-join.php#old-student-login',true,303)")!==false,'Failed join login returns to the form at a fixed URL');
$source=file_get_contents(__DIR__.'/../alumni-join.php');
$view=substr($source,strpos($source,'<!doctype html>'));
$view=str_replace("<?php include('alumni-help-widget.php'); ?>",'',$view);
$view=str_replace('<script src="js/alumni-join-login.js?v=20261012-modal"></script>','<script>'.file_get_contents(__DIR__.'/../js/alumni-join-login.js').'</script>',$view);
$message=''; $messageTone='error'; $loginError='';
ob_start(); eval('?>'.$view); $html=ob_get_clean();
$xmlErrors=libxml_use_internal_errors(true);
$dom=new DOMDocument(); $dom->loadHTML($html); $xpath=new DOMXPath($dom);
libxml_clear_errors(); libxml_use_internal_errors($xmlErrors);
$loginForms=$xpath->query('//dialog[@id="old-student-login"]//form');
login_check($loginForms->length===1,'One separate login dialog form');
login_check($loginForms->item(0)->getAttribute('action')==='alumni-community.php','Form uses existing community endpoint');
login_check($xpath->query('//dialog[@id="old-student-login"]//input[@name="csrf" and @value="fixture-token"]')->length===1,'Login includes session CSRF token');
login_check($xpath->query('//dialog[@id="old-student-login"]//input[@type="password" and @autocomplete="current-password"]')->length===1,'Login password is protected and supports password managers');
login_check($xpath->query('//section[@id="alumni-registration"]//form[@enctype="multipart/form-data"]')->length===1,'Registration/photo form retained separately');
login_check($xpath->query('//dialog[@id="old-student-login"]//a[@href="alumni-claim.php"]')->length===1,'Existing imported profiles can be activated');
login_check(strpos($html,'Fixture-password-123')===false,'Passwords never rendered');
login_check($xpath->query('//dialog[@id="old-student-login" and not(@open)]')->length===1,'Login initially closed');
login_check($xpath->query('//button[@data-open-login and @type="button" and not(ancestor::form)]')->length===1,'Login trigger is below and outside registration form');
login_check($xpath->query('//form//form')->length===0,'Login and registration forms are not nested');
login_check($xpath->query('//button[@data-close-login and @aria-label="Close login"]')->length===1,'Accessible close control');
login_check($xpath->query('//noscript//a[@href="alumni-community.php"]')->length===1,'No-JavaScript sign-in fallback');
$loginError='<script>alert(1)</script>';
ob_start(); eval('?>'.$view); $errorHtml=ob_get_clean();
login_check(strpos($errorHtml,'<script>alert(1)</script>')===false && strpos($errorHtml,'&lt;script&gt;')!==false,'Error notice escaped');
if(in_array('--preview',$argv,true)){
    $dir=sys_get_temp_dir().DIRECTORY_SEPARATOR.'alumni-login-'.bin2hex(random_bytes(6)); mkdir($dir);
    $html=preg_replace('/<link[^>]*href="https:\/\/fonts\.[^"]*"[^>]*>/','',$html);
    foreach(array('alumni-register.css','alumni-join-login.css') as $file){
        $html=preg_replace('/<link[^>]*href="css\/'.preg_quote($file,'/').'[^\"]*"[^>]*>/','<style>'.file_get_contents(__DIR__.'/../css/'.$file).'</style>',$html);
    }
    $base='file:///'.str_replace('\\','/',dirname(__DIR__)).'/';
    $html=str_replace(array('href="css/font-awesome','src="logo/'),array('href="'.$base.'css/font-awesome','src="'.$base.'logo/'),$html);
    file_put_contents($dir.DIRECTORY_SEPARATOR.'login.html',$html);
    echo 'Synthetic preview: '.$dir.PHP_EOL;
}
mysqli_close($con);
echo $checks." alumni login checks passed. No saved accounts changed.\n";
