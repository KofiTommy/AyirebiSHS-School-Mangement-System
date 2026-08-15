<?php
/* Compatibility wrapper. SMS credentials are loaded only from the ignored local config. */
include_once(dirname(__DIR__).DIRECTORY_SEPARATOR.'online-admission-utils.php');
$resultCode = '';
$ok = online_admission_sms_gateway_send(
    online_admission_normalize_sms_phone(isset($phone) ? $phone : ''),
    isset($message) ? $message : '',
    $resultCode
);
$_SESSION['Message'] = $ok
    ? "<div style='padding:8px;background-color:#efe;color:green;'>Message Successfully Sent</div>"
    : "<div style='padding:8px;background-color:#fee;color:red;'>Message failed to send. Please check the SMS configuration or provider balance.</div>";
?>
