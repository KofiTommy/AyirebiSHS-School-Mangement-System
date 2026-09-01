<?php
function staff_permission_ensure_table($con){
    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblstaffpermissionrequest (request_id int unsigned NOT NULL AUTO_INCREMENT, requester_id varchar(30) NOT NULL, approver_role varchar(40) NOT NULL, permission_type varchar(60) NOT NULL, reason text NOT NULL, start_datetime datetime NOT NULL, end_datetime datetime NOT NULL, status enum('pending','approved','declined') NOT NULL DEFAULT 'pending', decision_note varchar(500) DEFAULT NULL, decided_by varchar(30) DEFAULT NULL, decided_at datetime DEFAULT NULL, created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(request_id), KEY requester_status(requester_id,status), KEY approver_status(approver_role,status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function staff_permission_escape($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function staff_permission_role(){
    $type = isset($_SESSION['SYSTEMTYPE']) ? $_SESSION['SYSTEMTYPE'] : '';
    if($type === 'Teacher'){ return 'teacher'; }
    if($type === 'AssistantHeadAcademic'){ return 'assistant_head_academic'; }
    if($type === 'AssistantHeadAdministration'){ return 'assistant_head_administration'; }
    if($type === 'Headmaster'){ return 'headmaster'; }
    if(isset($_SESSION['ACCESSLEVEL']) && $_SESSION['ACCESSLEVEL'] === 'administrator' && $type === 'super_user'){ return 'super_admin'; }
    return '';
}
function staff_permission_approver_options(){
    $role = staff_permission_role();
    if($role === 'teacher'){ return array('assistant_head_academic'=>'Headmaster Academics','assistant_head_administration'=>'Headmaster Administration','headmaster'=>'Headmaster'); }
    if($role === 'assistant_head_academic'){ return array('headmaster'=>'Headmaster'); }
    if($role === 'headmaster'){ return array('super_admin'=>'Super Administrator'); }
    return array();
}
function staff_permission_can_review($approverRole){ return staff_permission_role() === $approverRole; }

function staff_permission_notify_approvers($con, $approverRole, $requesterName, $permissionType, $startDateTime, &$summary = null){
    $summary = array('total'=>0,'sms_sent'=>0,'sms_failed'=>0,'no_phone'=>0);
    $roleMap = array('assistant_head_academic'=>'AssistantHeadAcademic','assistant_head_administration'=>'AssistantHeadAdministration','headmaster'=>'Headmaster','super_admin'=>'super_user');
    if(!isset($roleMap[$approverRole])){ return false; }
    $roleValue = mysqli_real_escape_string($con, $roleMap[$approverRole]);
    $where = $approverRole === 'super_admin' ? "su.accesslevel='administrator' AND su.systemtype='$roleValue'" : "su.systemtype='$roleValue'";
    $res = mysqli_query($con, "SELECT userid,mobile FROM tblsystemuser su WHERE $where AND su.status='active'");
    if(!$res){ return false; }
    $message = 'Staff permission request from '.trim($requesterName).': '.trim($permissionType).'. From '.trim($startDateTime).'. Please review.';
    while($row=mysqli_fetch_assoc($res)){
        $summary['total']++; $recipient=trim((string)$row['userid']); $messageId=strtoupper(substr(md5(uniqid((string)mt_rand(),true)),0,30));
        $messageEsc=mysqli_real_escape_string($con,$message); $recipientEsc=mysqli_real_escape_string($con,$recipient);
        @mysqli_query($con, "INSERT INTO tblmessages(messageid,messages,datetimeentry,status,sentby,recipient_group,recipient_type,recipient_value,recipient_label) VALUES('$messageId','$messageEsc',NOW(),'active','SYSTEM','', 'user','$recipientEsc','Staff Permission')");
        $phone=trim((string)$row['mobile']); if($phone===''){ $summary['no_phone']++; continue; }
        $code=''; $ok=function_exists('send_bulk_sms_message') ? send_bulk_sms_message($phone,$message,$code) : false;
        if($ok){ $summary['sms_sent']++; } else { $summary['sms_failed']++; }
    }
    return true;
}
?>
