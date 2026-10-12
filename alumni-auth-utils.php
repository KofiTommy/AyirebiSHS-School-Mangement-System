<?php
require_once __DIR__.'/alumni-utils.php';

// Shared login check for the community and the form on alumni-join.php.
// This helper never approves an account or starts an authenticated session.
function alumni_authenticate_login($con,$identity,$password,$ip,$csrf){
    $invalid='Your sign-in details could not be verified. Please check them and make sure your registration has been approved.';
    if(!alumni_csrf_valid($csrf)) return array('member_id'=>'','error'=>'Your sign-in form expired. Please try again.');
    if(!is_string($identity) || !is_string($password)) return array('member_id'=>'','error'=>$invalid);
    $identity=trim($identity);
    if($identity==='' || strlen($identity)>255 || $password==='' || strlen($password)>4096) return array('member_id'=>'','error'=>$invalid);
    $attemptHash=hash('sha256',strtolower($identity)); $ip=substr((string)$ip,0,64);
    $stmt=mysqli_prepare($con,'SELECT COUNT(*) total FROM tblalumniloginattempt WHERE identityhash=? AND ipaddress=? AND success=0 AND createdat>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)');
    mysqli_stmt_bind_param($stmt,'ss',$attemptHash,$ip); mysqli_stmt_execute($stmt);
    $attempts=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
    if((int)$attempts['total']>=5) return array('member_id'=>'','error'=>'Too many sign-in attempts. Please wait 15 minutes and try again.');
    $stmt=mysqli_prepare($con,"SELECT alumniid,passwordhash FROM tblalumni WHERE status='active' AND (email=? OR mobile=?) LIMIT 1");
    mysqli_stmt_bind_param($stmt,'ss',$identity,$identity); mysqli_stmt_execute($stmt);
    $member=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
    $success=$member && !empty($member['passwordhash']) && password_verify($password,$member['passwordhash']);
    $successFlag=$success ? 1:0;
    $stmt=mysqli_prepare($con,'INSERT INTO tblalumniloginattempt(identityhash,ipaddress,success) VALUES(?,?,?)');
    mysqli_stmt_bind_param($stmt,'ssi',$attemptHash,$ip,$successFlag); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    return array('member_id'=>$success ? $member['alumniid']:'','error'=>$success ? '':$invalid);
}
