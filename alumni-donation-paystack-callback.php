<?php
session_start();
require_once('dbstring.php');
require_once('alumni-utils.php');
require_once('online-admission-utils.php');
require_once('audit_notifications.php');
alumni_ensure_tables($con);
function alumni_donation_callback_back($message,$tone='error'){
    $_SESSION['alumni_donation_flash']=array('message'=>$message,'tone'=>$tone);
    header('location:alumni-donate.php');exit();
}
$reference=trim((string)($_GET['reference']??''));
if($reference==='')alumni_donation_callback_back('Paystack did not return a payment reference.');
$stmt=mysqli_prepare($con,'SELECT * FROM tblalumnidonationpayment WHERE referenceid=? LIMIT 1');mysqli_stmt_bind_param($stmt,'s',$reference);mysqli_stmt_execute($stmt);$result=mysqli_stmt_get_result($stmt);$payment=$result?mysqli_fetch_assoc($result):null;mysqli_stmt_close($stmt);
if(!$payment)alumni_donation_callback_back('We could not match this payment to an Alumni donation.');
if((string)($_SESSION['ALUMNI_MEMBER']??'')!==$payment['alumniid'])alumni_donation_callback_back('Please sign in to the Alumni account that started this donation.');
$config=online_admission_paystack_config();
if(!online_admission_paystack_is_ready($config))alumni_donation_callback_back('Online donations are not configured yet. Please contact AYISEC.');
$error='';$response=online_admission_paystack_verify($config,$reference,$error);
if($response===false||empty($response['data'])){
    $stmt=mysqli_prepare($con,"UPDATE tblalumnidonationpayment SET status='pending',gatewayresponse=? WHERE referenceid=?");$note=$error!==''?$error:'Verification could not be completed.';mysqli_stmt_bind_param($stmt,'ss',$note,$reference);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
    alumni_donation_callback_back($note,'error');
}
$data=$response['data'];$paid=isset($data['status'])&&$data['status']==='success';$amountMatch=((int)($data['amount']??0)===online_admission_money_minor_units((float)$payment['amount']));$currencyMatch=strtoupper((string)($data['currency']??''))==='GHS';
if($paid&&$amountMatch&&$currencyMatch){
    $alreadyRecorded=((string)$payment['status']==='success');$status='success';$transaction=(string)($data['id']??'');$note=(string)($response['message']??'Verified by Paystack');$stmt=mysqli_prepare($con,"UPDATE tblalumnidonationpayment SET status=?,gatewaytransactionid=?,gatewayresponse=?,paidat=NOW() WHERE referenceid=?");mysqli_stmt_bind_param($stmt,'ssss',$status,$transaction,$note,$reference);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
    if(!$alreadyRecorded)logExternalSystemChange($con,(string)$payment['donorname'],'Alumni','ALUMNI_DONATION_RECEIVED','Confirmed Alumni donation of GHS '.number_format((float)$payment['amount'],2).'.',(string)$payment['alumniid']);
    alumni_donation_callback_back('Thank you. Your donation has been received and recorded successfully.','success');
}
$status=$paid?'review':'failed';$note=$paid?'The payment details did not match the recorded donation. Please contact AYISEC.':(string)($data['gateway_response']??'Payment was not completed.');$stmt=mysqli_prepare($con,'UPDATE tblalumnidonationpayment SET status=?,gatewayresponse=? WHERE referenceid=?');mysqli_stmt_bind_param($stmt,'sss',$status,$note,$reference);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);alumni_donation_callback_back($note,'error');
