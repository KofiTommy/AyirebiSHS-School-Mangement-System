<?php
if(!function_exists('alumni_ensure_tables')){
function alumni_ensure_tables($con){
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblalumni (
        alumniid VARCHAR(60) NOT NULL PRIMARY KEY,
        studentid VARCHAR(60) DEFAULT NULL,
        fullname VARCHAR(180) NOT NULL,
        graduationyear INT DEFAULT NULL,
        programme VARCHAR(120) DEFAULT NULL,
        mobile VARCHAR(40) DEFAULT NULL,
        email VARCHAR(120) DEFAULT NULL,
        locationtext VARCHAR(160) DEFAULT NULL,
        occupation VARCHAR(160) DEFAULT NULL,
        organisation VARCHAR(160) DEFAULT NULL,
        contactpreference VARCHAR(30) NOT NULL DEFAULT 'Phone',
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        recordedby VARCHAR(60) DEFAULT NULL,
        createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_alumni_year (graduationyear), KEY idx_alumni_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblalumniachievement (
        achievementid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        alumniid VARCHAR(60) NOT NULL,
        title VARCHAR(180) NOT NULL,
        description TEXT DEFAULT NULL,
        achieveddate DATE DEFAULT NULL,
        recordedby VARCHAR(60) DEFAULT NULL,
        createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_achievement_alumni (alumniid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblalumnimentorship (
        mentorshipid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        alumniid VARCHAR(60) NOT NULL,
        expertise VARCHAR(180) NOT NULL,
        availability VARCHAR(120) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'available',
        createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_mentorship_alumni (alumniid), KEY idx_mentorship_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblalumnidonation (
        donationid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        alumniid VARCHAR(60) NOT NULL,
        donationtype VARCHAR(100) NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        currency VARCHAR(12) DEFAULT 'GH₵',
        pledgeddate DATE DEFAULT NULL,
        receiveddate DATE DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pledged',
        notes TEXT DEFAULT NULL,
        recordedby VARCHAR(60) DEFAULT NULL,
        createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_donation_alumni (alumniid), KEY idx_donation_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblalumnismscampaign (
        campaignid VARCHAR(60) NOT NULL PRIMARY KEY,
        targetyear INT DEFAULT NULL,
        messagebody TEXT NOT NULL,
        recipientcount INT NOT NULL DEFAULT 0,
        sentcount INT NOT NULL DEFAULT 0,
        failedcount INT NOT NULL DEFAULT 0,
        status VARCHAR(30) NOT NULL DEFAULT 'queued',
        recordedby VARCHAR(60) DEFAULT NULL,
        createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completedat DATETIME DEFAULT NULL,
        KEY idx_alumni_sms_campaign_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblalumnismsrecipient (
        recipientid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        campaignid VARCHAR(60) NOT NULL,
        alumniid VARCHAR(60) NOT NULL,
        mobile VARCHAR(40) NOT NULL,
        sendstatus VARCHAR(20) NOT NULL DEFAULT 'queued',
        gatewayresponse VARCHAR(255) DEFAULT NULL,
        attempts INT NOT NULL DEFAULT 0,
        sentat DATETIME DEFAULT NULL,
        createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_alumni_sms_recipient (campaignid, alumniid),
        KEY idx_alumni_sms_recipient_status (campaignid, sendstatus)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblalumnimemory (
        memoryid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        imagepath VARCHAR(255) NOT NULL,
        caption VARCHAR(180) NOT NULL,
        memoryyear INT DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'published',
        recordedby VARCHAR(60) DEFAULT NULL,
        createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_alumni_memory_status (status, memoryyear)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblalumnichatmessage (
        messageid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        alumniid VARCHAR(60) NOT NULL,
        chatroom VARCHAR(40) NOT NULL DEFAULT 'general',
        messagebody TEXT NOT NULL,
        createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_alumni_chat_room (chatroom, createdat)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblalumniloginattempt (
        attemptid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        identityhash CHAR(64) NOT NULL,
        ipaddress VARCHAR(64) NOT NULL,
        success TINYINT(1) NOT NULL DEFAULT 0,
        createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_alumni_login_attempt (identityhash, ipaddress, createdat)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblalumniclaimtoken (
        tokenid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        alumniid VARCHAR(60) NOT NULL,
        codehash CHAR(64) NOT NULL,
        expiresat DATETIME NOT NULL,
        usedat DATETIME DEFAULT NULL,
        createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_alumni_claim (alumniid, expiresat)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblalumnichatreport (
        reportid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        messageid INT NOT NULL,
        reporterid VARCHAR(60) NOT NULL,
        reason VARCHAR(500) DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_alumni_chat_report (status, createdat)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblalumnievent (
        eventid VARCHAR(60) NOT NULL PRIMARY KEY,
        title VARCHAR(180) NOT NULL,
        eventdate DATETIME NOT NULL,
        venue VARCHAR(180) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'planned',
        recordedby VARCHAR(60) DEFAULT NULL,
        createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_event_date (eventdate), KEY idx_event_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
}
if(!function_exists('alumni_ensure_column')){function alumni_ensure_column($con,$column,$definition){$result=mysqli_query($con,"SHOW COLUMNS FROM tblalumni LIKE '".mysqli_real_escape_string($con,$column)."'");if(!$result||mysqli_num_rows($result)===0)@mysqli_query($con,"ALTER TABLE tblalumni ADD COLUMN $column $definition");}}
alumni_ensure_column($con,'profileimage','VARCHAR(255) DEFAULT NULL');
alumni_ensure_column($con,'directoryconsent','TINYINT(1) NOT NULL DEFAULT 0');
alumni_ensure_column($con,'photoconsent','TINYINT(1) NOT NULL DEFAULT 0');
alumni_ensure_column($con,'chatconsent','TINYINT(1) NOT NULL DEFAULT 0');
alumni_ensure_column($con,'passwordhash','VARCHAR(255) DEFAULT NULL');
alumni_ensure_column($con,'smsoptin','TINYINT(1) NOT NULL DEFAULT 0');
alumni_ensure_column($con,'chathidden','TINYINT(1) NOT NULL DEFAULT 0');
if(!function_exists('alumni_new_id')){ function alumni_new_id($prefix='ALM'){ return $prefix.date('ymdHis').strtoupper(bin2hex(random_bytes(4))); } }
if(!function_exists('alumni_safe')){ function alumni_safe($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); } }
if(!function_exists('alumni_store_profile_image')){function alumni_store_profile_image($file){$allowed=array('image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp');$mime=($file&&($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK&&function_exists('mime_content_type'))?mime_content_type($file['tmp_name']):'';if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return array(true,'');if(($file['error']??0)!==UPLOAD_ERR_OK||$file['size']>3145728||!isset($allowed[$mime])||@getimagesize($file['tmp_name'])===false)return array(false,'');$folder=__DIR__.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'alumni'.DIRECTORY_SEPARATOR.'profiles';if(!is_dir($folder)&&!@mkdir($folder,0755,true))return array(false,'');$path='uploads/alumni/profiles/profile-'.date('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.$allowed[$mime];return move_uploaded_file($file['tmp_name'],$folder.DIRECTORY_SEPARATOR.basename($path))?array(true,$path):array(false,'');}}
if(!function_exists('alumni_csrf_token')){function alumni_csrf_token(){if(empty($_SESSION['alumni_csrf']))$_SESSION['alumni_csrf']=bin2hex(random_bytes(32));return $_SESSION['alumni_csrf'];}}
if(!function_exists('alumni_csrf_valid')){function alumni_csrf_valid($token){return !empty($_SESSION['alumni_csrf'])&&is_string($token)&&hash_equals($_SESSION['alumni_csrf'],$token);}}

if(!function_exists('alumni_sync_archived_graduates')){
function alumni_sync_archived_graduates($con){
    $sql = "SELECT su.userid,su.firstname,su.othernames,su.surname,su.mobile,su.email,cl.status AS classstatus,cl.datetimeentry,ce.class_name
        FROM tblsystemuser su
        LEFT JOIN tblclass cl ON cl.userid=su.userid
        LEFT JOIN tblclassentry ce ON ce.class_entryid=cl.class_entryid
        WHERE su.systemtype='Student' AND LOWER(TRIM(COALESCE(su.status,''))) IN ('alumni','archived')
        ORDER BY su.userid, CASE WHEN LOWER(TRIM(COALESCE(cl.status,''))) IN ('graduated','archived') THEN 0 ELSE 1 END, cl.datetimeentry DESC";
    $result = mysqli_query($con, $sql);
    if(!$result){ return 0; }
    $handled = array(); $created = 0;
    $insert = mysqli_prepare($con, "INSERT INTO tblalumni(alumniid,fullname,graduationyear,programme,mobile,email,contactpreference,status,recordedby) VALUES(?,?,?,?,?,?,?,?,?)");
    if(!$insert){ return 0; }
    while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
        $sourceUser = trim((string)$row['userid']);
        if($sourceUser === '' || isset($handled[$sourceUser])){ continue; }
        $handled[$sourceUser] = true;
        $fullName = trim((string)$row['firstname'].' '.(string)$row['othernames'].' '.(string)$row['surname']);
        if($fullName === ''){ continue; }
        $programme = trim((string)($row['class_name'] ?? ''));
        $graduationYear = (int)date('Y', strtotime((string)($row['datetimeentry'] ?? 'now')));
        if($graduationYear < 1950 || $graduationYear > 2100){ $graduationYear = (int)date('Y'); }
        $mobile = trim((string)($row['mobile'] ?? ''));
        $email = trim((string)($row['email'] ?? ''));
        $check = mysqli_prepare($con, "SELECT alumniid FROM tblalumni WHERE fullname=? AND graduationyear=? AND programme=? LIMIT 1");
        $exists = false;
        if($check){ mysqli_stmt_bind_param($check, 'sis', $fullName, $graduationYear, $programme); mysqli_stmt_execute($check); mysqli_stmt_store_result($check); $exists = mysqli_stmt_num_rows($check) > 0; mysqli_stmt_close($check); }
        if($exists){ continue; }
        $alumniId = alumni_new_id('ALM'); $contact = $mobile !== '' ? 'Phone' : ($email !== '' ? 'Email' : 'Phone'); $status = 'pending'; $recordedBy = 'Automatic graduate transfer';
        mysqli_stmt_bind_param($insert, 'ssissssss', $alumniId, $fullName, $graduationYear, $programme, $mobile, $email, $contact, $status, $recordedBy);
        if(mysqli_stmt_execute($insert)){ $created++; }
    }
    mysqli_stmt_close($insert);
    return $created;
}
}
if(!function_exists('alumni_create_sms_campaign')){
function alumni_create_sms_campaign($con,$actor,$message,$targetYears=array()){
    include_once(__DIR__.DIRECTORY_SEPARATOR.'online-admission-utils.php');
    $message=trim((string)$message); if(!is_array($targetYears)){$targetYears=array($targetYears);} $years=array(); foreach($targetYears as $targetYear){$targetYear=trim((string)$targetYear);if(ctype_digit($targetYear)&&$targetYear>=1950&&$targetYear<=2100)$years[(int)$targetYear]=(int)$targetYear;} $years=array_values($years);
    if($message==='') return array(false,'Write the SMS message first.',null,0);
    if(strlen($message)>612) return array(false,'Keep the campaign message within 612 characters.',null,0);
    $sql="SELECT alumniid,mobile FROM tblalumni WHERE status='active' AND smsoptin=1 AND TRIM(COALESCE(mobile,''))<>''";
    if($years) $sql.=' AND graduationyear IN ('.implode(',',array_map('intval',$years)).')';
    $res=mysqli_query($con,$sql); $recipients=array(); $seen=array();
    while($res && ($row=mysqli_fetch_assoc($res))){$mobile=online_admission_normalize_sms_phone($row['mobile']);if($mobile!==''&&!isset($seen[$mobile])){$seen[$mobile]=true;$recipients[]=array('alumniid'=>$row['alumniid'],'mobile'=>$mobile);}}
    if(!$recipients) return array(false,'No approved alumni with valid mobile numbers match this audience.',null,0);
    $campaignId=alumni_new_id('SMS'); $yearValue=count($years)===1?$years[0]:null; $count=count($recipients);
    $stmt=mysqli_prepare($con,'INSERT INTO tblalumnismscampaign(campaignid,targetyear,messagebody,recipientcount,recordedby) VALUES(?,?,?,?,?)');
    mysqli_stmt_bind_param($stmt,'sisis',$campaignId,$yearValue,$message,$count,$actor); $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); if(!$ok)return array(false,'Campaign could not be saved.',null,0);
    $stmt=mysqli_prepare($con,'INSERT INTO tblalumnismsrecipient(campaignid,alumniid,mobile) VALUES(?,?,?)');
    foreach($recipients as $recipient){mysqli_stmt_bind_param($stmt,'sss',$campaignId,$recipient['alumniid'],$recipient['mobile']);mysqli_stmt_execute($stmt);} mysqli_stmt_close($stmt);
    return array(true,'Campaign queued for '.count($recipients).' old student(s).',$campaignId,count($recipients));
}}

if(!function_exists('alumni_process_sms_campaign')){
function alumni_process_sms_campaign($con,$campaignId,$limit=6){
    include_once(__DIR__.DIRECTORY_SEPARATOR.'online-admission-utils.php');
    $campaignId=mysqli_real_escape_string($con,trim((string)$campaignId)); $limit=max(1,min(10,(int)$limit));
    mysqli_query($con,"UPDATE tblalumnismscampaign SET status='sending' WHERE campaignid='$campaignId' AND status='queued'");
    $rows=mysqli_query($con,"SELECT recipientid,mobile FROM tblalumnismsrecipient WHERE campaignid='$campaignId' AND sendstatus='queued' ORDER BY recipientid ASC LIMIT $limit");
    $campaign=mysqli_fetch_assoc(mysqli_query($con,"SELECT messagebody FROM tblalumnismscampaign WHERE campaignid='$campaignId' LIMIT 1")); if(!$campaign)return array(0,0,0);
    $sent=0;$failed=0;while($rows&&($row=mysqli_fetch_assoc($rows))){$response='';$ok=online_admission_sms_gateway_send($row['mobile'],$campaign['messagebody'],$response);$status=$ok?'sent':'failed';if($ok)$sent++;else $failed++;$stmt=mysqli_prepare($con,'UPDATE tblalumnismsrecipient SET sendstatus=?,gatewayresponse=?,attempts=attempts+1,sentat=IF(?="sent",NOW(),sentat) WHERE recipientid=?');mysqli_stmt_bind_param($stmt,'sssi',$status,$response,$status,$row['recipientid']);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);}
    $counts=mysqli_fetch_assoc(mysqli_query($con,"SELECT SUM(sendstatus='queued') pending,SUM(sendstatus='sent') sent,SUM(sendstatus='failed') failed FROM tblalumnismsrecipient WHERE campaignid='$campaignId'"));$pending=(int)($counts['pending']??0);$sentTotal=(int)($counts['sent']??0);$failedTotal=(int)($counts['failed']??0);$status=$pending?'sending':'completed';mysqli_query($con,"UPDATE tblalumnismscampaign SET sentcount=$sentTotal,failedcount=$failedTotal,status='$status',completedat=".($pending?'NULL':'NOW()')." WHERE campaignid='$campaignId'");return array($pending,$sent,$failed);
}}
