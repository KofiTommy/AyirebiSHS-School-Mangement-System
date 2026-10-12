<?php
if(PHP_SAPI!=='cli') { http_response_code(403); exit; }
// No real gateway calls: every transport entry point is replaced before loading helpers.
$gatewayCalls=0; $verifyData=false; $initFailure=false; $gatewayMode='live';
function online_admission_paystack_config() { global $gatewayMode; return array('public_key'=>'pk_'.$gatewayMode.'_fixture','secret_key'=>'sk_'.$gatewayMode.'_fixture'); }
function online_admission_paystack_initialize($config,$payload,&$error) {
    global $gatewayCalls,$initFailure; $gatewayCalls++;
    if($initFailure) return false;
    return array('status'=>true,'data'=>array('reference'=>$payload['reference'],'authorization_url'=>'https://checkout.paystack.com/fixture'));
}
function online_admission_paystack_verify($config,$reference,&$error) { global $verifyData; return $verifyData; }
require __DIR__.'/../alumni-document-utils.php';
require __DIR__.'/../alumni-document-view.php';
require __DIR__.'/../alumni-document-notifications.php';
require __DIR__.'/../dbstring.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
set_error_handler(function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
$_SESSION=array('ALUMNI_MEMBER'=>'fixture','alumni_csrf'=>'fixture-csrf','ald_token'=>str_repeat('a',64));
$_SERVER['HTTP_HOST']='localhost'; $_SERVER['SCRIPT_NAME']='/alumni-document-requests.php'; $_POST=array(); $_GET=array();
$checks=0;
function check_doc($ok,$label) { global $checks; if(!$ok)throw new RuntimeException($label); $checks++; }
function rejects_doc($fn,$label) { try{$fn();}catch(InvalidArgumentException $e){check_doc(true,$label);return;}throw new RuntimeException($label); }
// These connection-local tables shadow production names and disappear on disconnect.
foreach(ald_schema_statements() as $sql) { mysqli_query($con,str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',$sql)); }
mysqli_query($con,"CREATE TEMPORARY TABLE tblsystemchangelog(logid INT AUTO_INCREMENT PRIMARY KEY,actor_userid VARCHAR(60),actor_name VARCHAR(180),actor_type VARCHAR(40),action_type VARCHAR(80),target_userid VARCHAR(60),details TEXT,page_name VARCHAR(120),ip_address VARCHAR(64),status VARCHAR(20),datetimeentry DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
mysqli_query($con,'CREATE TEMPORARY TABLE tblalumni (alumniid VARCHAR(60) PRIMARY KEY,fullname VARCHAR(180),status VARCHAR(30),email VARCHAR(180),mobile VARCHAR(40),graduationyear INT)');
ald_query($con,"INSERT INTO tblalumni VALUES('fixture','Example Alumnus','active','alumnus@example.test','0240000000',2020),('pending','Pending Member','pending','','',2020)");
$member=ald_member($con); check_doc($member['alumniid']==='fixture','Active alumni access');
$_SESSION['ALUMNI_MEMBER']='pending'; check_doc(ald_member($con)===null,'Pending alumni blocked'); $_SESSION['ALUMNI_MEMBER']='fixture';
foreach(array('0'=>0,'1'=>100,'12.3'=>1230,'12.34'=>1234,'999999.99'=>99999999) as $value=>$expected) check_doc(ald_money((string)$value)===$expected,'Exact fee parsing');
foreach(array('-1','1.234','1e2','','1000000','1,000',array()) as $value) rejects_doc(function()use($value){ald_money($value);},'Invalid fee rejected');
ald_query($con,"INSERT INTO tblalumnidocservice(name,fee,deliveryfee,pickup,email,physical,enabled) VALUES('Academic transcript',15000,2500,1,1,1,1),('Disabled',500,0,1,0,0,0),('Free testimonial',0,0,1,0,0,1)");
$input=array('service'=>'1','delivery'=>'physical','recipient'=>'Example Recipient','phone'=>'0240000000','receiptemail'=>'receipt@example.test','address'=>'House 1, Example Street','gps'=>'GA-000-0000','city'=>'Accra','country'=>'Ghana','email'=>'delivery@example.test','examyear'=>'2020','purpose'=>'University application','fee'=>'1','total'=>'1');
$id=ald_create($con,$member,$input,str_repeat('b',64));
$get=function($id)use($con){return ald_rows($con,'SELECT * FROM tblalumnidocrequest WHERE id=?',array($id))[0];};
$r=$get($id); check_doc((int)$r['total']===17500,'Server prices override posted amount');
check_doc($r['status']==='submitted' && $r['paymentstatus']==='unpaid','Review before payment');
check_doc(json_decode($r['details'],true)['email']==='','Unneeded address data discarded');
check_doc(ald_create($con,$member,$input,str_repeat('b',64))===$id,'Duplicate submission idempotent');
check_doc(ald_pending_review_count($con)===1,'Pending request appears in admin reminder');
$notice=ald_rows($con,'SELECT * FROM tblsystemchangelog');
check_doc(count($notice)===1 && $notice[0]['action_type']==='ALUMNI_DOCUMENT_REQUEST_SUBMITTED' && $notice[0]['status']==='unread' && (int)$notice[0]['target_userid']===$id,'One unread admin notification per request with direct review target');
check_doc(strpos($notice[0]['details'],'House 1')===false && strpos($notice[0]['details'],'receipt@example.test')===false,'Admin notification excludes destination data');
check_doc(ald_member_payment_notices($con,'fixture')===array(),'No payment prompt before approval');
ald_query($con,'UPDATE tblalumnidocservice SET fee=20000 WHERE id=1'); check_doc((int)$get($id)['total']===17500,'Existing quote preserved');
foreach(array(array('service'=>'2'),array('delivery'=>'courier'),array('receiptemail'=>'bad'),array('address'=>'','gps'=>''),array('recipient'=>''),array('city'=>''),array('examyear'=>'abc'),array('purpose'=>str_repeat('a',1001))) as $changes) {
    rejects_doc(function()use($con,$member,$input,$changes){ald_create($con,$member,array_merge($input,$changes),bin2hex(random_bytes(32)));},'Invalid request rejected');
}
rejects_doc(function()use($con,$id){ald_start_payment($con,$id,'fixture');},'Unapproved request cannot pay');
rejects_doc(function()use($con,$id){ald_update_status($con,$id,'completed','','admin');},'Cannot bypass payment');
ald_update_status($con,$id,'awaiting_payment','Confirmed available','admin');
check_doc(ald_pending_review_count($con)===0,'Approval clears pending admin reminder');
check_doc(count(ald_member_payment_notices($con,'fixture'))===1,'Approval creates actionable payment prompt');
check_doc(ald_member_payment_notices($con,'another-member')===array(),'Payment prompt private to request owner');
ald_update_status($con,$id,'awaiting_payment','Confirmed available','admin');
check_doc(count(ald_member_payment_notices($con,'fixture'))===1,'Repeated save does not duplicate prompt');
ob_start(); ald_render_member_payment_notices($con,'fixture'); $noticeHtml=ob_get_clean();
check_doc(strpos($noticeHtml,'Review &amp; pay')!==false && strpos($noticeHtml,'?id='.$id.'#request-detail')!==false,'Prompt links directly to payable request');
$adminSource=file_get_contents(__DIR__.'/../admin.php');
check_doc(strpos($adminSource,'ALUMNI_DOCUMENT_REQUEST_SUBMITTED')!==false && strpos($adminSource,'Review Document Request')!==false,'Admin notification has review action');
$menuSource=file_get_contents(__DIR__.'/../menu.php');
check_doc(strpos($menuSource,"um_is_admin_manager() && um_current_user_can_access_module(\$con, 'alumni_management')")!==false,'Pending count is restricted to permitted administrators');
ald_query($con,"UPDATE tblalumni SET status='pending' WHERE alumniid='fixture'");
check_doc(ald_member_payment_notices($con,'fixture')===array(),'Inactive member cannot retrieve payment prompt');
ald_query($con,"UPDATE tblalumni SET status='active' WHERE alumniid='fixture'");
rejects_doc(function()use($con,$id){ald_start_payment($con,$id,'another-member');},'Cross-member payment denied');
check_doc(ald_start_payment($con,$id,'fixture')==='https://checkout.paystack.com/fixture','Checkout starts after approval');
check_doc($gatewayCalls===1,'One initialization');
check_doc(ald_start_payment($con,$id,'fixture')==='https://checkout.paystack.com/fixture' && $gatewayCalls===1,'Retry reuses checkout/reference');
$r=$get($id); $valid=array('status'=>'success','reference'=>$r['reference'],'currency'=>'GHS','amount'=>17500,'id'=>123456,'domain'=>'live');
foreach(array(array('amount'=>1),array('currency'=>'NGN'),array('reference'=>'different'),array('status'=>'failed'),array('id'=>'bad'),array('amount'=>'17500.5')) as $changes) check_doc(!ald_record_payment($con,$r['reference'],array_merge($valid,$changes)),'Gateway integrity enforced');
check_doc($get($id)['paymentstatus']==='unpaid','Invalid callbacks never mark paid');
check_doc(!ald_record_payment($con,$r['reference'],array_merge($valid,array('domain'=>'test'))),'Test domain cannot credit live request');
check_doc(ald_record_payment($con,$r['reference'],$valid),'Valid gateway payment recorded');
check_doc($get($id)['status']==='processing','Paid request moves to processing');
check_doc(ald_member_payment_notices($con,'fixture')===array(),'Payment removes prompt immediately on next render');
check_doc(ald_record_payment($con,$r['reference'],$valid),'Duplicate webhook idempotent');
check_doc((int)ald_rows($con,"SELECT COUNT(*) n FROM tblalumnidocevent WHERE requestid=? AND event='payment_verified'",array($id))[0]['n']===1,'One paid audit event');
check_doc(!ald_record_payment($con,$r['reference'],array_merge($valid,array('status'=>'failed'))),'Late failed event ignored');
check_doc($get($id)['paymentstatus']==='paid','Paid state never regresses');
ald_update_status($con,$id,'ready','Prepared for delivery','admin');
ald_update_status($con,$id,'dispatched','Tracking: TEST-123','admin');
ald_update_status($con,$id,'completed','Delivered','admin'); check_doc($get($id)['status']==='completed','Fulfilment lifecycle');
check_doc(ald_record_payment($con,$r['reference'],$valid) && $get($id)['status']==='completed','Webhook replay preserves completed state');
rejects_doc(function()use($con,$id){ald_update_status($con,$id,'submitted','','admin');},'Completed state cannot revert');
$freeInput=array_merge($input,array('service'=>'3','delivery'=>'pickup'));
$free=ald_create($con,$member,$freeInput,str_repeat('c',64)); ald_update_status($con,$free,'awaiting_payment','','admin');
check_doc($get($free)['paymentstatus']==='not_required' && $get($free)['status']==='processing','Free service approved without gateway');
check_doc(ald_member_payment_notices($con,'fixture')===array(),'Free approval does not ask for payment');
ald_update_status($con,$free,'ready','','admin'); rejects_doc(function()use($con,$free){ald_update_status($con,$free,'dispatched','','admin');},'Pickup is not dispatch');
$rejected=ald_create($con,$member,$input,str_repeat('d',64));
rejects_doc(function()use($con,$rejected){ald_update_status($con,$rejected,'rejected','','admin');},'Rejection needs explanation');
ald_update_status($con,$rejected,'rejected','Document unavailable','admin');
rejects_doc(function()use($con,$rejected){ald_start_payment($con,$rejected,'fixture');},'Rejected requests cannot pay');
$pending=ald_create($con,$member,$input,str_repeat('e',64)); ald_update_status($con,$pending,'awaiting_payment','','admin');
$initFailure=true;
rejects_doc(function()use($con,$pending){ald_start_payment($con,$pending,'fixture');},'Ambiguous initialization handled');
$ref=$get($pending)['reference']; rejects_doc(function()use($con,$pending){ald_start_payment($con,$pending,'fixture');},'Immediate parallel retry blocked');
check_doc($ref===$get($pending)['reference'],'Timeout retains fixed reference');
$initFailure=false; ald_query($con,'UPDATE tblalumnidocrequest SET initat=DATE_SUB(NOW(),INTERVAL 3 MINUTE) WHERE id=?',array($pending));
check_doc(ald_start_payment($con,$pending,'fixture')==='https://checkout.paystack.com/fixture','Delayed initialization retry works');
$verifyData=array('status'=>true,'data'=>array('status'=>'success','reference'=>$ref,'amount'=>22500,'currency'=>'GHS','id'=>123457,'domain'=>'live'));
check_doc(ald_start_payment($con,$pending,'fixture')==='' && $get($pending)['paymentstatus']==='paid','Resume detects already-paid checkout');
$duplicate=ald_create($con,$member,$input,bin2hex(random_bytes(32))); ald_update_status($con,$duplicate,'awaiting_payment','','admin');
$duplicateData=array_merge($verifyData['data'],array('reference'=>$get($duplicate)['reference']));
try { ald_record_payment($con,$duplicateData['reference'],$duplicateData); throw new RuntimeException('Duplicate transaction ID accepted'); }
catch(mysqli_sql_exception $e) { check_doc($get($duplicate)['paymentstatus']==='unpaid','Duplicate gateway ID rollback leaves request unpaid'); }
$gatewayMode='test'; $testData=array_merge($duplicateData,array('domain'=>'test','id'=>123458));
check_doc(ald_record_payment($con,$testData['reference'],$testData),'Test payment recorded in test mode');
check_doc($get($duplicate)['paymentmode']==='test','Test domain persisted for future live mode');
$gatewayMode='live';
rejects_doc(function()use($con,$duplicate){ald_update_status($con,$duplicate,'ready','','admin');},'Test-paid order cannot be fulfilled after switching to live mode');
foreach(array('http://checkout.paystack.com/x','https://evil.test/x','https://checkout.paystack.com.evil.test/x','https://user@checkout.paystack.com/x','javascript:alert(1)') as $url) check_doc(!ald_checkout_url($url),'Unsafe redirect blocked');
check_doc(alumni_csrf_valid('fixture-csrf') && !alumni_csrf_valid('wrong'),'CSRF enforcement helper');
// Render real member and admin templates against synthetic data only.
$_GET=array('id'=>$id); $error='';
$source=file_get_contents(__DIR__.'/../alumni-document-requests.php');
$template=substr($source,strpos($source,'$services=ald_rows'));
ob_start(); eval($template); $memberHtml=ob_get_clean();
check_doc(strpos($memberHtml,'Start a document request')!==false,'Member template renders');
$noticePreview=str_replace('</header>','</header>'.$noticeHtml,$memberHtml);
ald_query($con,"UPDATE tblalumnidocrequest SET staffnote='<script>alert(1)</script>' WHERE id=?",array($id));
$source=file_get_contents(__DIR__.'/../alumni-document-admin.php');
$template=substr($source,strpos($source,'ald_page_start(true);'));
ob_start(); eval($template); $adminHtml=ob_get_clean();
check_doc(strpos($adminHtml,'<script>alert(1)</script>')===false && strpos($adminHtml,'&lt;script&gt;')!==false,'Stored notes escaped');
check_doc(strpos($adminHtml,'Services and fees')!==false,'Admin template renders');
$_GET=array('page'=>999); ob_start(); ald_list($con,'fixture'); $list=ob_get_clean(); check_doc(strpos($list,'Page 1 of 1')!==false,'Pagination bounds clamped');
for($n=0;$n<25;$n++) { ald_create($con,$member,$input,bin2hex(random_bytes(32))); }
$_GET=array('page'=>1); ob_start(); ald_list($con,'fixture'); $list=ob_get_clean();
check_doc(substr_count($list,'<tr>')===21,'First page contains 20 requests plus header');
check_doc(strpos($list,'Page 1 of 2')!==false && strpos($list,'Next')!==false,'Pagination offers next page');
$_GET=array('page'=>999); ob_start(); ald_list($con,'fixture'); $list=ob_get_clean();
check_doc(strpos($list,'Page 2 of 2')!==false && substr_count($list,'<tr>')<21,'Last page clamped and limited');
$_GET=array(); ob_start(); ald_list($con,'another-member'); $list=ob_get_clean(); check_doc(strpos($list,'No requests to show yet')!==false,'Another member cannot list fixture requests');
check_doc(strpos(file_get_contents(__DIR__.'/../alumni-document-requests.php'),'AND alumniid=?')!==false,'Member detail/verification ownership guards');
$webhook=file_get_contents(__DIR__.'/../online-admission-paystack-webhook.php');
check_doc(strpos($webhook,'online_admission_paystack_signature_is_valid')<strpos($webhook,"strpos(\$reference, 'ALMDOC_')"),'Webhook signature checked before document routing');
if(in_array('--preview',$argv,true)) {
    $dir=sys_get_temp_dir().'/alumni-documents-'.bin2hex(random_bytes(5)); mkdir($dir);
    foreach(array('documents.html'=>$memberHtml,'admin.html'=>$adminHtml,'notifications.html'=>$noticePreview) as $filename=>$html) {
        $html=str_replace('<link rel="stylesheet" href="css/alumni-documents.css?v=20261012">','<style>'.file_get_contents(__DIR__.'/../css/alumni-documents.css').'</style>',$html);
        $html=str_replace('<script src="js/alumni-documents.js?v=20261012"></script>','<script>'.file_get_contents(__DIR__.'/../js/alumni-documents.js').'</script>',$html);
        file_put_contents($dir.'/'.$filename,$html);
    }
    echo 'Preview: '.$dir.PHP_EOL;
}
echo 'PASS: '.$checks.' document service checks; temporary tables and mocked payments only.'.PHP_EOL;
