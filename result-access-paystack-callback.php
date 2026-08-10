<?php
session_start(); include('dbstring.php'); include_once('result-access-utils.php'); include_once('online-admission-utils.php');
$reference=trim((string)(isset($_GET['reference'])?$_GET['reference']:''));
if($reference===''){ exit('Payment reference was not returned by Paystack.'); }
$payment=result_access_get_payment_by_reference($con,$reference); if(!$payment){ exit('We could not match this payment attempt.'); }
$config=online_admission_paystack_config(); $error=''; $response=online_admission_paystack_verify($config,$reference,$error);
if($response===false||empty($response['data'])||!is_array($response['data'])){ exit('Payment verification is pending. Please wait a moment and open your report again.'); }
$result=result_access_mark_payment_from_paystack($con,$payment,$response['data'],isset($response['_raw'])?$response['_raw']:'');
if(!empty($result['success'])){ $_SESSION['RESULT_ACCESS_MESSAGE']='Payment confirmed. Your result is now available.'; header('Location: student-page.php'); exit(); }
exit('The payment could not be confirmed. No result access was granted. Please contact the school with reference '.htmlspecialchars($reference).'.');
?>
