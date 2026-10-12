<?php
session_start();
require_once __DIR__.'/dbstring.php';
require_once __DIR__.'/alumni-document-utils.php';
header('Cache-Control: private, no-store');
$member=ald_member($con);
if(!$member) { header('Location: alumni-join.php#old-student-login'); exit; }
try {
    $reference=ald_text($_GET,'reference',80);
    $rows=ald_rows($con,'SELECT id FROM tblalumnidocrequest WHERE reference=? AND alumniid=?',array($reference,$member['alumniid']));
    if(!$rows) { ald_flash('That payment request was not found in your account.'); }
    $error=''; $response=online_admission_paystack_verify(online_admission_paystack_config(),$reference,$error);
    $ok=$response && ald_record_payment($con,$reference,$response['data']??array());
    ald_flash($ok?'Payment confirmed. The school can now process your document request.':'Payment is not yet confirmed. If you were debited, do not pay again. Use Check payment later or contact the school.');
} catch(Throwable $e) {
    error_log('Alumni document payment callback failed: '.get_class($e));
    ald_flash('Your payment confirmation is temporarily unavailable. Please check the request again before making another payment.');
}
