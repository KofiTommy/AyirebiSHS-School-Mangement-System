<?php
if(PHP_SAPI !== 'cli'){ http_response_code(403); exit; }
// Stub the transport before loading application helpers. No network request or SMS.
$gatewayCalls=0; $fixtureResponse='{"success":true}'; $fixtureHttp=200;
function online_admission_sms_gateway_send($phone,$message,&$resultCode=null){
    global $gatewayCalls,$fixtureResponse,$fixtureHttp;
    $gatewayCalls++;
    return online_admission_sms_gateway_result($fixtureResponse,$fixtureHttp,$resultCode);
}
require_once __DIR__.'/../online-admission-utils.php';
require_once __DIR__.'/../alumni-utils.php';
set_error_handler(function($severity,$message,$file,$line){ throw new ErrorException($message,0,$severity,$file,$line); });
$checks=0;
function sms_check($condition,$label){ global $checks; if(!$condition) throw new RuntimeException($label); $checks++; }
$cases=array(
    array('1000',200,true),array(" 1000\r\n",200,true),array("\xEF\xBB\xBF1000",200,true),
    array('"1000"',200,true),array('{"code":1000}',200,true),array('{"code":"1000","success":true}',200,true),
    array('{"success":true}',200,true),array('{"success":true,"code":null}',202,true),
    array('{"success":true,"code":""}',200,true),array('{"success":false}',200,false),
    array('{"success":false,"code":1000}',200,false),array('{"success":true,"code":1003}',200,false),
    array('{"success":true,"code":[]}',200,false),array('{"success":true,"code":"unexpected"}',200,false),
    array('{"success":"false"}',200,false),array('{"success":"true"}',200,false),array('{"success":1}',200,false),
    array('1002',200,false),array('1003',200,false),array('1004',200,false),array('1005',200,false),array('1006',200,false),array('1008',200,false),
    array('',200,false),array('null',200,false),array('true',200,false),array('{}',200,false),array('[]',200,false),
    array('{broken',200,false),array('1000',500,false),array('{"success":true}',403,false),array('1000',0,false),
    array('{"success":true,"key":"secret-fixture","to":"private-fixture"}',200,true),
    array('<html>secret-fixture</html>',200,false)
);
foreach($cases as $i=>$case){
    $result=''; $accepted=online_admission_sms_gateway_result($case[0],$case[1],$result);
    sms_check($accepted===$case[2],'Gateway response case '.$i);
    sms_check(strlen($result)<=255 && strpos($result,'secret-fixture')===false && strpos($result,'private-fixture')===false,'Response is short and safe '.$i);
}
sms_check(strpos(alumni_welcome_sms_notice(true,'sent'),'accepted')!==false,'Success notice describes acceptance');
sms_check(strpos(alumni_welcome_sms_notice(true,'already_sent'),'no duplicate')!==false,'Already-sent is not failure');
sms_check(alumni_welcome_sms_notice(true,'sent_unrecorded')==='The SMS service has accepted the welcome message.','Accepted but not recorded uses the requested concise notice');
sms_check(strpos(alumni_welcome_sms_notice(false,'not_opted_in'),'did not opt in')!==false,'Consent notice retained');
sms_check(strpos(alumni_welcome_sms_notice(false,'no_valid_phone'),'no valid Ghana')!==false,'Invalid phone notice retained');
sms_check(strpos(alumni_welcome_sms_notice(false,'failed'),'before retrying')!==false,'Unconfirmed response warns against duplicates');
if(in_array('--database',$argv,true)){
    require __DIR__.'/../dbstring.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    // Connection-local table shadows the real table. No saved alumni are touched.
    mysqli_query($con,"CREATE TEMPORARY TABLE tblalumni (alumniid VARCHAR(60) PRIMARY KEY,fullname VARCHAR(180),mobile VARCHAR(40),smsoptin INT,status VARCHAR(20),welcomesmssentat DATETIME NULL,welcomesmsstatus VARCHAR(30),welcomesmsresponse VARCHAR(255))");
    mysqli_query($con,"INSERT INTO tblalumni VALUES ('FIXTURE','Synthetic Alumnus','0240000000',1,'active',NULL,NULL,NULL)");
    $result=alumni_send_welcome_sms($con,'FIXTURE');
    sms_check($result===array(true,'sent'),'Boolean JSON success reported correctly');
    $row=mysqli_fetch_assoc(mysqli_query($con,"SELECT * FROM tblalumni WHERE alumniid='FIXTURE'"));
    sms_check($row['welcomesmsstatus']==='sent' && $row['welcomesmssentat']!==null,'Accepted send persisted');
    sms_check($row['welcomesmsresponse']==='Gateway accepted message','Safe provider result persisted');
    $calls=$gatewayCalls;
    sms_check(alumni_send_welcome_sms($con,'FIXTURE')===array(true,'already_sent'),'Existing send returns success without resending');
    sms_check($gatewayCalls===$calls,'No duplicate transport call');
    mysqli_query($con,"UPDATE tblalumni SET welcomesmssentat=NULL,smsoptin=0 WHERE alumniid='FIXTURE'");
    sms_check(alumni_send_welcome_sms($con,'FIXTURE')===array(false,'not_opted_in'),'No send without consent');
    sms_check($gatewayCalls===$calls,'Consent rejection does not call gateway');
    mysqli_query($con,"UPDATE tblalumni SET smsoptin=1,mobile='invalid' WHERE alumniid='FIXTURE'");
    sms_check(alumni_send_welcome_sms($con,'FIXTURE')===array(false,'no_valid_phone'),'Invalid phone blocked');
    sms_check($gatewayCalls===$calls,'Invalid phone does not call gateway');
    mysqli_query($con,"UPDATE tblalumni SET mobile='0240000000',status='pending' WHERE alumniid='FIXTURE'");
    sms_check(alumni_send_welcome_sms($con,'FIXTURE')===array(false,'not_approved'),'Pending registration cannot send');
    sms_check($gatewayCalls===$calls,'Pending registration does not call gateway');
    mysqli_query($con,"UPDATE tblalumni SET status='active' WHERE alumniid='FIXTURE'");
    $fixtureResponse='{"success":false,"code":1003}';
    sms_check(alumni_send_welcome_sms($con,'FIXTURE')===array(false,'failed'),'Gateway rejection remains failure');
    $row=mysqli_fetch_assoc(mysqli_query($con,"SELECT * FROM tblalumni WHERE alumniid='FIXTURE'"));
    sms_check($row['welcomesmssentat']===null && $row['welcomesmsstatus']==='failed','Rejected send not marked sent');
    $fixtureResponse='1000';
    sms_check(alumni_send_welcome_sms($con,'FIXTURE')===array(true,'sent'),'Legacy code still accepted');
    $fixtureResponse='{"success":true}';
    sms_check(alumni_resend_welcome_sms($con,'FIXTURE')===array(true,'sent'),'Explicit resend uses the same response parser');
    mysqli_query($con,"UPDATE tblalumni SET welcomesmssentat=NULL WHERE alumniid='FIXTURE'");
    mysqli_query($con,'ALTER TABLE tblalumni DROP COLUMN welcomesmsresponse');
    sms_check(alumni_send_welcome_sms($con,'FIXTURE')===array(true,'sent_unrecorded'),'Database failure after acceptance is not delivery failure');
    mysqli_close($con);
}
echo $checks." alumni SMS checks passed; no SMS or external requests sent.\n";
