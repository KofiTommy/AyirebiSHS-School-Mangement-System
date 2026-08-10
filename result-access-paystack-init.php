<?php
session_start();
include('dbstring.php'); include('check-login.php'); include_once('report-approval-utils.php'); include_once('result-access-utils.php'); include_once('online-admission-utils.php');
if(!report_approval_is_student_user()){ http_response_code(403); exit('Only a student account can make a result viewing payment.'); }
$user=isset($_SESSION['USERID'])?(string)$_SESSION['USERID']:''; $batch=trim((string)(isset($_POST['batchid'])?$_POST['batchid']:'')); $year=trim((string)(isset($_POST['academicyear'])?$_POST['academicyear']:'')); $term=(int)(isset($_POST['termid'])?$_POST['termid']:0); $class=trim((string)(isset($_POST['classid'])?$_POST['classid']:''));
if($user===''||$batch===''||$year===''||$term<1||$class===''){ exit('Invalid result payment request. Please return to your report and try again.'); }
$access=result_access_student_allowed($con,$user,$batch,$year,$term,$class); $scope=$access['scope'];
if($access['allowed']){ header('Location: individual-terminal-report.php'); exit(); }
if(empty($scope['enabled'])||$scope['mode']!=='payment'||(float)$scope['amount']<=0){ exit('Result viewing is not available for payment at this time. Please contact the school.'); }
$ur=mysqli_query($con,"SELECT email FROM tblsystemuser WHERE userid='".mysqli_real_escape_string($con,$user)."' LIMIT 1"); $student=$ur?mysqli_fetch_assoc($ur):null; $email=trim((string)(isset($student['email'])?$student['email']:''));
if(!filter_var($email,FILTER_VALIDATE_EMAIL)){ exit('Your student account does not have a valid email address. Please contact the school before paying.'); }
$config=online_admission_paystack_config(); $config['callback_path']='result-access-paystack-callback.php';
if(!online_admission_paystack_is_ready($config)){ exit('Paystack is not configured. Please contact the school.'); }
$reference=result_access_payment_reference(); $amount=(float)$scope['amount'];
$payload=array('reference'=>$reference,'email'=>$email,'amount'=>online_admission_money_minor_units($amount),'currency'=>'GHS','callback_url'=>online_admission_payment_callback_url($config,'callback_path','result-access-paystack-callback.php'),'metadata'=>array('payment_type'=>'result_access','studentid'=>$user,'batchid'=>$batch,'academicyear'=>$year,'termid'=>$term,'classid'=>$class));
$error=''; $response=online_admission_paystack_initialize($config,$payload,$error);
if($response===false||empty($response['data']['authorization_url'])){ exit(htmlspecialchars($error!==''?$error:'Paystack could not start the payment right now.')); }
result_access_ensure_tables($con); $pid=result_access_id('RAP_'); $ue=mysqli_real_escape_string($con,$user);$be=mysqli_real_escape_string($con,$batch);$ye=mysqli_real_escape_string($con,$year);$ce=mysqli_real_escape_string($con,$class);$re=mysqli_real_escape_string($con,$reference);
$ok=mysqli_query($con,"INSERT INTO tblresultaccesspayment(paymentid,userid,batchid,academicyear,termname,classid,reference,amount,currency,status,gatewayresponse,createdat,updatedat) VALUES('$pid','$ue','$be','$ye',$term,'$ce','$re',$amount,'GHS','initialized','Initialized',NOW(),NOW())");
if(!$ok){ exit('The payment session could not be saved. Please try again.'); }
header('Location: '.$response['data']['authorization_url']); exit();
?>
