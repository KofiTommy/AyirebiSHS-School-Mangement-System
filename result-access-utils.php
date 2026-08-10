<?php
/* Admin-controlled result access. Rules default to free/open until enabled. */
if(!function_exists('result_access_ensure_tables')){
function result_access_ensure_tables($con){
    @mysqli_query($con,"CREATE TABLE IF NOT EXISTS tblresultaccesssetting (
        settingid VARCHAR(50) NOT NULL PRIMARY KEY, batchid VARCHAR(100) NOT NULL, academicyear VARCHAR(20) NOT NULL,
        termname INT NOT NULL, classid VARCHAR(100) NOT NULL, mode VARCHAR(20) NOT NULL DEFAULT 'free', amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        currency VARCHAR(10) NOT NULL DEFAULT 'GHS', note VARCHAR(255) NULL, enabled TINYINT(1) NOT NULL DEFAULT 0,
        updatedby VARCHAR(100) NULL, updatedat DATETIME NOT NULL, UNIQUE KEY uq_resultaccess_scope(batchid,academicyear,termname,classid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @mysqli_query($con,"CREATE TABLE IF NOT EXISTS tblresultaccessoverride (
        overrideid VARCHAR(50) NOT NULL PRIMARY KEY, userid VARCHAR(100) NOT NULL, batchid VARCHAR(100) NOT NULL,
        academicyear VARCHAR(20) NOT NULL, termname INT NOT NULL, classid VARCHAR(100) NOT NULL, accessmode VARCHAR(20) NOT NULL,
        note VARCHAR(255) NULL, updatedby VARCHAR(100) NULL, updatedat DATETIME NOT NULL,
        UNIQUE KEY uq_resultaccess_override(userid,batchid,academicyear,termname,classid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @mysqli_query($con,"CREATE TABLE IF NOT EXISTS tblresultaccesspayment (
        paymentid VARCHAR(50) NOT NULL PRIMARY KEY, userid VARCHAR(100) NOT NULL, batchid VARCHAR(100) NOT NULL,
        academicyear VARCHAR(20) NOT NULL, termname INT NOT NULL, classid VARCHAR(100) NOT NULL, reference VARCHAR(120) NOT NULL,
        amount DECIMAL(12,2) NOT NULL, currency VARCHAR(10) NOT NULL DEFAULT 'GHS', status VARCHAR(20) NOT NULL DEFAULT 'initialized',
        gatewayresponse VARCHAR(255) NULL, paidat DATETIME NULL, verifiedat DATETIME NULL, createdat DATETIME NOT NULL, updatedat DATETIME NOT NULL,
        UNIQUE KEY uq_resultaccess_reference(reference), KEY idx_resultaccess_paid(userid,batchid,academicyear,termname,classid,status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
}
if(!function_exists('result_access_id')){ function result_access_id($prefix){ return $prefix.strtoupper(substr(sha1(uniqid('',true)),0,18)); } }
if(!function_exists('result_access_scope')){
function result_access_scope($con,$batch,$year,$term,$class){
    result_access_ensure_tables($con); $b=mysqli_real_escape_string($con,trim((string)$batch)); $y=mysqli_real_escape_string($con,trim((string)$year)); $t=(int)$term; $c=mysqli_real_escape_string($con,trim((string)$class));
    $r=mysqli_query($con,"SELECT * FROM tblresultaccesssetting WHERE batchid='$b' AND academicyear='$y' AND termname=$t AND classid='$c' LIMIT 1");
    $row=$r?mysqli_fetch_assoc($r):null; return $row?$row:array('mode'=>'free','enabled'=>0,'amount'=>0,'currency'=>'GHS','note'=>'');
}
}
if(!function_exists('result_access_student_allowed')){
function result_access_student_allowed($con,$user,$batch,$year,$term,$class){
    $scope=result_access_scope($con,$batch,$year,$term,$class); if(empty($scope['enabled']) || $scope['mode']==='free') return array('allowed'=>true,'reason'=>'free','scope'=>$scope);
    $u=mysqli_real_escape_string($con,trim((string)$user)); $b=mysqli_real_escape_string($con,trim((string)$batch)); $y=mysqli_real_escape_string($con,trim((string)$year)); $t=(int)$term; $c=mysqli_real_escape_string($con,trim((string)$class));
    $o=mysqli_query($con,"SELECT accessmode FROM tblresultaccessoverride WHERE userid='$u' AND batchid='$b' AND academicyear='$y' AND termname=$t AND classid='$c' LIMIT 1"); $ov=$o?mysqli_fetch_assoc($o):null;
    if($ov){ return array('allowed'=>$ov['accessmode']==='allow','reason'=>'override','scope'=>$scope); }
    if($scope['mode']==='closed') return array('allowed'=>false,'reason'=>'closed','scope'=>$scope);
    $p=mysqli_query($con,"SELECT paymentid FROM tblresultaccesspayment WHERE userid='$u' AND batchid='$b' AND academicyear='$y' AND termname=$t AND classid='$c' AND status='success' LIMIT 1");
    return array('allowed'=>$p&&mysqli_num_rows($p)>0,'reason'=>'payment','scope'=>$scope);
}
}
if(!function_exists('result_access_payment_reference')){
function result_access_payment_reference(){ return 'RESULT_'.date('YmdHis').'_'.strtoupper(substr(bin2hex(random_bytes(8)),0,12)); }
}
if(!function_exists('result_access_get_payment_by_reference')){
function result_access_get_payment_by_reference($con,$reference){ $r=mysqli_query($con,"SELECT * FROM tblresultaccesspayment WHERE reference='".mysqli_real_escape_string($con,(string)$reference)."' LIMIT 1"); return $r?mysqli_fetch_assoc($r):null; }
}
if(!function_exists('result_access_mark_payment_from_paystack')){
function result_access_mark_payment_from_paystack($con,$payment,$data,$rawResponse=''){
    $reference=isset($payment['reference'])?(string)$payment['reference']:''; $amountExpected=(int)round(((float)$payment['amount'])*100);
    $amountReceived=isset($data['amount'])?(int)$data['amount']:0; $currency=strtoupper(trim((string)(isset($data['currency'])?$data['currency']:'')));
    $status=strtolower(trim((string)(isset($data['status'])?$data['status']:'')));
    $valid=$reference!=='' && hash_equals($reference,trim((string)(isset($data['reference'])?$data['reference']:''))) && $status==='success' && $amountReceived===$amountExpected && $currency==='GHS';
    $id=mysqli_real_escape_string($con,(string)$payment['paymentid']); $gateway=mysqli_real_escape_string($con,substr(trim((string)(isset($data['gateway_response'])?$data['gateway_response']:'')),0,250));
    if($valid){ mysqli_query($con,"UPDATE tblresultaccesspayment SET status='success',gatewayresponse='$gateway',paidat=COALESCE(paidat,NOW()),verifiedat=NOW(),updatedat=NOW() WHERE paymentid='$id' AND status<>'success'"); return array('success'=>true,'integrity_failed'=>false); }
    mysqli_query($con,"UPDATE tblresultaccesspayment SET status='failed',gatewayresponse='$gateway',verifiedat=NOW(),updatedat=NOW() WHERE paymentid='$id' AND status<>'success'"); return array('success'=>false,'integrity_failed'=>true);
}
}
?>
