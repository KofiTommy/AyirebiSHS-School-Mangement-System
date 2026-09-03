<?php
session_start();
include('dbstring.php');
include('check-login.php');

$userId=trim((string)($_SESSION['USERID']??''));
$systemType='';
if($userId!==''){
    $stmt=mysqli_prepare($con,'SELECT systemtype FROM tblsystemuser WHERE userid=? LIMIT 1');
    mysqli_stmt_bind_param($stmt,'s',$userId);
    mysqli_stmt_execute($stmt);
    $result=mysqli_stmt_get_result($stmt);
    if($result && ($account=mysqli_fetch_assoc($result))) $systemType=(string)$account['systemtype'];
    mysqli_stmt_close($stmt);
}
$dashboards=['Teacher'=>'teacher-page.php','AssistantHeadAdministration'=>'assistant-head-administration-page.php','SafeguardingLead'=>'safeguarding-dashboard.php'];
header('Location: '.($dashboards[$systemType]??'index.php'));
exit();
?>