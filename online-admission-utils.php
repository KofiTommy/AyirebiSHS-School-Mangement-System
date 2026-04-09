<?php
if(!function_exists('online_admission_is_admin')){
function online_admission_is_admin(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "administrator" &&
        ($_SESSION['SYSTEMTYPE'] === "normal_user" || $_SESSION['SYSTEMTYPE'] === "super_user");
}
}

if(!function_exists('online_admission_landing_page')){
function online_admission_landing_page(){
    if(online_admission_is_admin()){
        return ($_SESSION['SYSTEMTYPE'] === "super_user") ? "super.php" : "admin.php";
    }
    return "index.php";
}
}

if(!function_exists('ensure_online_admission_tables')){
function ensure_online_admission_tables($con){
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tbladmissionpostedstudent (
        postingid VARCHAR(40) NOT NULL PRIMARY KEY,
        beceindexnumber VARCHAR(60) NOT NULL,
        birthdate DATE NOT NULL,
        firstname VARCHAR(80) NOT NULL,
        surname VARCHAR(80) NOT NULL,
        othernames VARCHAR(80) NULL,
        gender VARCHAR(20) NULL,
        admissionyear VARCHAR(20) NOT NULL,
        offeredprogram VARCHAR(120) NULL,
        offeredclass VARCHAR(120) NULL,
        residentialstatus VARCHAR(40) NULL,
        mobile VARCHAR(30) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        branchid VARCHAR(30) NOT NULL,
        UNIQUE KEY uq_posted_scope (beceindexnumber, admissionyear, branchid),
        INDEX idx_posted_birth (birthdate),
        INDEX idx_posted_status (status),
        INDEX idx_posted_branch (branchid)
    )");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblonlineadmissionapplication (
        applicationid VARCHAR(40) NOT NULL PRIMARY KEY,
        postingid VARCHAR(40) NOT NULL,
        beceindexnumber VARCHAR(60) NOT NULL,
        admissionyear VARCHAR(20) NOT NULL,
        firstname VARCHAR(80) NOT NULL,
        surname VARCHAR(80) NOT NULL,
        othernames VARCHAR(80) NULL,
        gender VARCHAR(20) NULL,
        birthdate DATE NOT NULL,
        email VARCHAR(120) NULL,
        mobile VARCHAR(30) NULL,
        residencetype VARCHAR(40) NULL,
        hometown VARCHAR(120) NULL,
        postaladdress VARCHAR(255) NULL,
        homeaddress VARCHAR(255) NULL,
        religion VARCHAR(40) NULL,
        guardianname VARCHAR(120) NULL,
        guardianrelationship VARCHAR(60) NULL,
        guardiancontact VARCHAR(30) NULL,
        medicalnotes VARCHAR(255) NULL,
        studentnote VARCHAR(255) NULL,
        filename VARCHAR(190) NULL,
        uploadeddatetime DATETIME NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        submittedat DATETIME NULL,
        verificationtoken VARCHAR(40) NULL,
        tokenissuedat DATETIME NULL,
        tokenlastusedat DATETIME NULL,
        guardiansmssentat DATETIME NULL,
        guardiansmsstatus VARCHAR(60) NULL,
        updatedat DATETIME NOT NULL,
        reviewedby VARCHAR(30) NULL,
        reviewnote VARCHAR(255) NULL,
        revieweddatetime DATETIME NULL,
        branchid VARCHAR(30) NOT NULL,
        UNIQUE KEY uq_application_posting (postingid),
        INDEX idx_application_status (status),
        INDEX idx_application_bece (beceindexnumber),
        INDEX idx_application_branch (branchid)
    )");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblonlineadmissionpaymentsetting (
        settingid VARCHAR(40) NOT NULL PRIMARY KEY,
        branchid VARCHAR(30) NOT NULL,
        portalenabled TINYINT(1) NOT NULL DEFAULT 1,
        paymentgateway VARCHAR(20) NOT NULL DEFAULT 'paystack',
        feeamount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        currency VARCHAR(10) NOT NULL DEFAULT 'GHS',
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        payablestatus VARCHAR(20) NOT NULL DEFAULT 'verified',
        note VARCHAR(255) NULL,
        updatedat DATETIME NOT NULL,
        updatedby VARCHAR(30) NULL,
        UNIQUE KEY uq_admission_paymentsetting_branch (branchid)
    )");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblonlineadmissionpayment (
        paymentid VARCHAR(40) NOT NULL PRIMARY KEY,
        applicationid VARCHAR(40) NOT NULL,
        postingid VARCHAR(40) NOT NULL,
        beceindexnumber VARCHAR(60) NOT NULL,
        admissionyear VARCHAR(20) NOT NULL,
        branchid VARCHAR(30) NOT NULL,
        gateway VARCHAR(20) NOT NULL DEFAULT 'paystack',
        reference VARCHAR(120) NOT NULL,
        accesscode VARCHAR(120) NULL,
        authorizationurl VARCHAR(255) NULL,
        gatewaytransactionid VARCHAR(80) NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        currency VARCHAR(10) NOT NULL DEFAULT 'GHS',
        email VARCHAR(120) NULL,
        mobile VARCHAR(30) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'initialized',
        gatewayresponse VARCHAR(255) NULL,
        admissioncode VARCHAR(40) NULL,
        codeissuedat DATETIME NULL,
        rawresponse TEXT NULL,
        paidat DATETIME NULL,
        verifiedat DATETIME NULL,
        studentsmssentat DATETIME NULL,
        studentsmsstatus VARCHAR(60) NULL,
        createdat DATETIME NOT NULL,
        updatedat DATETIME NOT NULL,
        UNIQUE KEY uq_onlineadmissionpayment_reference (reference),
        INDEX idx_onlineadmissionpayment_application (applicationid),
        INDEX idx_onlineadmissionpayment_status (status),
        INDEX idx_onlineadmissionpayment_branch (branchid)
    )");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblonlineadmissionhelprequest (
        requestid VARCHAR(40) NOT NULL PRIMARY KEY,
        applicationid VARCHAR(40) NULL,
        postingid VARCHAR(40) NULL,
        beceindexnumber VARCHAR(60) NULL,
        admissionyear VARCHAR(20) NULL,
        studentname VARCHAR(150) NOT NULL,
        contactphone VARCHAR(30) NULL,
        verificationtoken VARCHAR(40) NULL,
        helpmessage TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        adminnote VARCHAR(255) NULL,
        requestedat DATETIME NOT NULL,
        updatedat DATETIME NOT NULL,
        branchid VARCHAR(30) NOT NULL,
        INDEX idx_admissionhelp_branch (branchid),
        INDEX idx_admissionhelp_status (status),
        INDEX idx_admissionhelp_requested (requestedat)
    )");

    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissionpayment LIKE 'admissioncode'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissionpayment ADD COLUMN admissioncode VARCHAR(40) NULL AFTER gatewayresponse");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissionpayment LIKE 'codeissuedat'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissionpayment ADD COLUMN codeissuedat DATETIME NULL AFTER admissioncode");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissionpayment LIKE 'studentsmssentat'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissionpayment ADD COLUMN studentsmssentat DATETIME NULL AFTER verifiedat");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissionpayment LIKE 'studentsmsstatus'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissionpayment ADD COLUMN studentsmsstatus VARCHAR(60) NULL AFTER studentsmssentat");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissionpaymentsetting LIKE 'portalenabled'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissionpaymentsetting ADD COLUMN portalenabled TINYINT(1) NOT NULL DEFAULT 1 AFTER branchid");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissionapplication LIKE 'verificationtoken'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissionapplication ADD COLUMN verificationtoken VARCHAR(40) NULL AFTER submittedat");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissionapplication LIKE 'tokenissuedat'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissionapplication ADD COLUMN tokenissuedat DATETIME NULL AFTER verificationtoken");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissionapplication LIKE 'tokenlastusedat'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissionapplication ADD COLUMN tokenlastusedat DATETIME NULL AFTER tokenissuedat");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissionapplication LIKE 'guardiansmssentat'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissionapplication ADD COLUMN guardiansmssentat DATETIME NULL AFTER tokenlastusedat");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissionapplication LIKE 'guardiansmsstatus'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissionapplication ADD COLUMN guardiansmsstatus VARCHAR(60) NULL AFTER guardiansmssentat");
    }
}
}

if(!function_exists('online_admission_generate_id')){
function online_admission_generate_id($prefix){
    return $prefix.date("YmdHis")."_".substr(md5(uniqid('', true)), 0, 10);
}
}

if(!function_exists('online_admission_payment_reference')){
function online_admission_payment_reference(){
    return "ADMPAY-".date("YmdHis")."-".strtoupper(substr(md5(uniqid('', true)), 0, 10));
}
}

if(!function_exists('online_admission_default_branch_context')){
function online_admission_default_branch_context($con){
    $context = array(
        "branchid" => "",
        "location" => "Current Branch",
        "telephone1" => "",
        "company" => "School Management System"
    );
    $sql = "SELECT br.branchid, br.location, br.telephone1, cm.fullname
            FROM tblbranch br
            LEFT JOIN tblcompany cm ON cm.companyid = br.companyid
            WHERE br.status='active'
            ORDER BY br.branchid ASC
            LIMIT 1";
    $res = mysqli_query($con, $sql);
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        $context["branchid"] = (string)$row["branchid"];
        if(trim((string)$row["location"]) !== ""){
            $context["location"] = trim((string)$row["location"]);
        }
        $context["telephone1"] = trim((string)$row["telephone1"]);
        if(trim((string)$row["fullname"]) !== ""){
            $context["company"] = trim((string)$row["fullname"]);
        }
    }
    return $context;
}
}

if(!function_exists('online_admission_normalize_bece')){
function online_admission_normalize_bece($value){
    $value = strtoupper(trim((string)$value));
    $value = preg_replace('/\s+/', '', $value);
    return $value;
}
}

if(!function_exists('online_admission_normalize_date')){
function online_admission_normalize_date($value){
    $value = trim((string)$value);
    if($value === ""){
        return "";
    }
    if(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])){
        return $value;
    }
    if(preg_match('/^(\d{4})-(\d{2})-(\d{2})\s+\d{2}:\d{2}:\d{2}$/', $value, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])){
        return sprintf("%04d-%02d-%02d", $m[1], $m[2], $m[3]);
    }
    if(preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $value, $m) && checkdate((int)$m[2], (int)$m[1], (int)$m[3])){
        return sprintf("%04d-%02d-%02d", $m[3], $m[2], $m[1]);
    }
    $timestamp = strtotime($value);
    if($timestamp !== false){
        return date("Y-m-d", $timestamp);
    }
    return false;
}
}

if(!function_exists('online_admission_status_label')){
function online_admission_status_label($status){
    $status = strtolower(trim((string)$status));
    if($status === "submitted"){ return "Submitted"; }
    if($status === "reviewed"){ return "Reviewed"; }
    if($status === "needs_attention"){ return "Needs Attention"; }
    return "Draft";
}
}

if(!function_exists('online_admission_find_posted_student')){
function online_admission_find_posted_student($con, $branchId, $beceIndex, $birthdate, $admissionYear){
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $beceEsc = mysqli_real_escape_string($con, online_admission_normalize_bece($beceIndex));
    $birthEsc = mysqli_real_escape_string($con, (string)$birthdate);
    $yearEsc = mysqli_real_escape_string($con, trim((string)$admissionYear));
    $sql = "SELECT *
            FROM tbladmissionpostedstudent
            WHERE branchid='$branchIdEsc'
              AND beceindexnumber='$beceEsc'
              AND birthdate='$birthEsc'
              AND admissionyear='$yearEsc'
              AND status='active'
            LIMIT 1";
    $res = mysqli_query($con, $sql);
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        return $row;
    }
    return null;
}
}

if(!function_exists('online_admission_get_posted_student_by_id')){
function online_admission_get_posted_student_by_id($con, $branchId, $postingId){
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $postingIdEsc = mysqli_real_escape_string($con, (string)$postingId);
    $res = mysqli_query($con, "SELECT *
        FROM tbladmissionpostedstudent
        WHERE postingid='$postingIdEsc'
          AND branchid='$branchIdEsc'
          AND status='active'
        LIMIT 1");
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        return $row;
    }
    return null;
}
}

if(!function_exists('online_admission_get_application_by_posting')){
function online_admission_get_application_by_posting($con, $postingId){
    $postingIdEsc = mysqli_real_escape_string($con, (string)$postingId);
    $res = mysqli_query($con, "SELECT * FROM tblonlineadmissionapplication WHERE postingid='$postingIdEsc' LIMIT 1");
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        return $row;
    }
    return null;
}
}

if(!function_exists('online_admission_get_application_by_id')){
function online_admission_get_application_by_id($con, $applicationId){
    $applicationIdEsc = mysqli_real_escape_string($con, (string)$applicationId);
    $res = mysqli_query($con, "SELECT * FROM tblonlineadmissionapplication WHERE applicationid='$applicationIdEsc' LIMIT 1");
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        return $row;
    }
    return null;
}
}

if(!function_exists('online_admission_generate_verification_token')){
function online_admission_generate_verification_token($con){
    do{
        $token = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', md5(uniqid('', true))), 0, 8));
        $tokenEsc = mysqli_real_escape_string($con, $token);
        $exists = mysqli_query($con, "SELECT applicationid FROM tblonlineadmissionapplication WHERE verificationtoken='$tokenEsc' LIMIT 1");
    }while($exists && mysqli_num_rows($exists) > 0);
    return $token;
}
}

if(!function_exists('online_admission_attach_payments_to_application')){
function online_admission_attach_payments_to_application($con, $postingId, $applicationId){
    $postingIdEsc = mysqli_real_escape_string($con, (string)$postingId);
    $applicationIdEsc = mysqli_real_escape_string($con, (string)$applicationId);
    return mysqli_query($con, "UPDATE tblonlineadmissionpayment
        SET applicationid='$applicationIdEsc', updatedat=NOW()
        WHERE postingid='$postingIdEsc'
          AND (applicationid='' OR applicationid IS NULL)");
}
}

if(!function_exists('online_admission_ensure_application_for_posting')){
function online_admission_ensure_application_for_posting($con, $postedStudent){
    if(!is_array($postedStudent) || empty($postedStudent) || trim((string)(isset($postedStudent["postingid"]) ? $postedStudent["postingid"] : "")) === ""){
        return null;
    }
    $existing = online_admission_get_application_by_posting($con, $postedStudent["postingid"]);
    if($existing){
        online_admission_attach_payments_to_application($con, $postedStudent["postingid"], $existing["applicationid"]);
        return $existing;
    }

    $applicationId = online_admission_generate_id("ADM_");
    $applicationIdEsc = mysqli_real_escape_string($con, $applicationId);
    $postingIdEsc = mysqli_real_escape_string($con, (string)$postedStudent["postingid"]);
    $beceEsc = mysqli_real_escape_string($con, (string)$postedStudent["beceindexnumber"]);
    $yearEsc = mysqli_real_escape_string($con, (string)$postedStudent["admissionyear"]);
    $firstEsc = mysqli_real_escape_string($con, (string)$postedStudent["firstname"]);
    $surnameEsc = mysqli_real_escape_string($con, (string)$postedStudent["surname"]);
    $otherEsc = mysqli_real_escape_string($con, (string)$postedStudent["othernames"]);
    $genderEsc = mysqli_real_escape_string($con, (string)$postedStudent["gender"]);
    $birthEsc = mysqli_real_escape_string($con, (string)$postedStudent["birthdate"]);
    $mobileEsc = mysqli_real_escape_string($con, (string)$postedStudent["mobile"]);
    $residenceEsc = mysqli_real_escape_string($con, (string)$postedStudent["residentialstatus"]);
    $branchIdEsc = mysqli_real_escape_string($con, (string)$postedStudent["branchid"]);

    mysqli_query($con, "INSERT INTO tblonlineadmissionapplication(
        applicationid, postingid, beceindexnumber, admissionyear, firstname, surname, othernames, gender, birthdate,
        email, mobile, residencetype, hometown, postaladdress, homeaddress, religion, guardianname,
        guardianrelationship, guardiancontact, medicalnotes, studentnote, filename, uploadeddatetime, status,
        submittedat, verificationtoken, tokenissuedat, tokenlastusedat, updatedat, branchid
    ) VALUES(
        '$applicationIdEsc', '$postingIdEsc', '$beceEsc', '$yearEsc', '$firstEsc', '$surnameEsc', '$otherEsc', '$genderEsc', '$birthEsc',
        '', '$mobileEsc', '$residenceEsc', '', '', '', '', '', '', '', '', '', '', NULL, 'draft',
        NULL, NULL, NULL, NULL, NOW(), '$branchIdEsc'
    )");

    $application = online_admission_get_application_by_posting($con, $postedStudent["postingid"]);
    if($application){
        online_admission_attach_payments_to_application($con, $postedStudent["postingid"], $application["applicationid"]);
    }
    return $application;
}
}

if(!function_exists('online_admission_ensure_application_token')){
function online_admission_ensure_application_token($con, $application){
    if(!is_array($application) || empty($application) || trim((string)(isset($application["applicationid"]) ? $application["applicationid"] : "")) === ""){
        return null;
    }
    if(trim((string)(isset($application["verificationtoken"]) ? $application["verificationtoken"] : "")) !== ""){
        return $application;
    }
    $token = online_admission_generate_verification_token($con);
    $applicationIdEsc = mysqli_real_escape_string($con, (string)$application["applicationid"]);
    $tokenEsc = mysqli_real_escape_string($con, $token);
    $updated = mysqli_query($con, "UPDATE tblonlineadmissionapplication
        SET verificationtoken='$tokenEsc', tokenissuedat=NOW(), updatedat=NOW()
        WHERE applicationid='$applicationIdEsc'
        LIMIT 1");
    if(!$updated){
        return $application;
    }
    $refreshed = online_admission_get_application_by_id($con, $application["applicationid"]);
    return $refreshed ? $refreshed : $application;
}
}

if(!function_exists('online_admission_find_application_by_access')){
function online_admission_find_application_by_access($con, $branchId, $beceIndex, $birthdate, $token){
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $beceEsc = mysqli_real_escape_string($con, online_admission_normalize_bece($beceIndex));
    $birthEsc = mysqli_real_escape_string($con, (string)$birthdate);
    $tokenEsc = mysqli_real_escape_string($con, strtoupper(trim((string)$token)));
    $res = mysqli_query($con, "SELECT app.*
        FROM tblonlineadmissionapplication app
        INNER JOIN tbladmissionpostedstudent post ON post.postingid=app.postingid
        WHERE app.branchid='$branchIdEsc'
          AND app.verificationtoken='$tokenEsc'
          AND post.beceindexnumber='$beceEsc'
          AND post.birthdate='$birthEsc'
          AND post.status='active'
        LIMIT 1");
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        return $row;
    }
    return null;
}
}

if(!function_exists('online_admission_mark_token_used')){
function online_admission_mark_token_used($con, $applicationId){
    $applicationIdEsc = mysqli_real_escape_string($con, (string)$applicationId);
    return mysqli_query($con, "UPDATE tblonlineadmissionapplication
        SET tokenlastusedat=NOW(), updatedat=NOW()
        WHERE applicationid='$applicationIdEsc'
        LIMIT 1");
}
}

if(!function_exists('online_admission_get_payment_setting')){
function online_admission_get_payment_setting($con, $branchId){
    $defaults = array(
        "settingid" => "",
        "branchid" => (string)$branchId,
        "portalenabled" => 1,
        "paymentgateway" => "paystack",
        "feeamount" => "0.00",
        "currency" => "GHS",
        "enabled" => 0,
        "payablestatus" => "verified",
        "note" => ""
    );
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $res = mysqli_query($con, "SELECT * FROM tblonlineadmissionpaymentsetting WHERE branchid='$branchIdEsc' LIMIT 1");
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        return array_merge($defaults, $row);
    }
    return $defaults;
}
}

if(!function_exists('online_admission_save_payment_setting')){
function online_admission_save_payment_setting($con, $branchId, $data, $updatedBy){
    $setting = online_admission_get_payment_setting($con, $branchId);
    $settingId = $setting["settingid"] !== "" ? (string)$setting["settingid"] : online_admission_generate_id("ADMPAYCFG_");
    $portalEnabled = !empty($data["portalenabled"]) ? 1 : 0;
    $enabled = !empty($data["enabled"]) ? 1 : 0;
    $feeAmount = isset($data["feeamount"]) ? (float)$data["feeamount"] : 0;
    if($feeAmount < 0){
        $feeAmount = 0;
    }
    $currency = strtoupper(trim((string)(isset($data["currency"]) ? $data["currency"] : "GHS")));
    if($currency === ""){
        $currency = "GHS";
    }
    $payableStatus = trim((string)(isset($data["payablestatus"]) ? $data["payablestatus"] : "verified"));
    if(!in_array($payableStatus, array("verified", "submitted", "reviewed"), true)){
        $payableStatus = "verified";
    }
    $note = trim((string)(isset($data["note"]) ? $data["note"] : ""));

    $stmt = mysqli_prepare($con, "INSERT INTO tblonlineadmissionpaymentsetting(
        settingid, branchid, portalenabled, paymentgateway, feeamount, currency, enabled, payablestatus, note, updatedat, updatedby
    ) VALUES(
        ?, ?, ?, 'paystack', ?, ?, ?, ?, ?, NOW(), ?
    ) ON DUPLICATE KEY UPDATE
        portalenabled=VALUES(portalenabled),
        paymentgateway=VALUES(paymentgateway),
        feeamount=VALUES(feeamount),
        currency=VALUES(currency),
        enabled=VALUES(enabled),
        payablestatus=VALUES(payablestatus),
        note=VALUES(note),
        updatedat=NOW(),
        updatedby=VALUES(updatedby)");
    if(!$stmt){
        return false;
    }
    mysqli_stmt_bind_param($stmt, "ssidsisss", $settingId, $branchId, $portalEnabled, $feeAmount, $currency, $enabled, $payableStatus, $note, $updatedBy);
    $saved = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $saved;
}
}

if(!function_exists('online_admission_portal_is_open')){
function online_admission_portal_is_open($setting){
    return (int)(isset($setting["portalenabled"]) ? $setting["portalenabled"] : 1) === 1;
}
}

if(!function_exists('online_admission_hubtel_config')){
function online_admission_hubtel_config(){
    $config = array(
        "client_id" => trim((string)getenv("HUBTEL_CLIENT_ID")),
        "client_secret" => trim((string)getenv("HUBTEL_CLIENT_SECRET")),
        "request_money_url_template" => trim((string)getenv("HUBTEL_REQUEST_MONEY_URL_TEMPLATE")),
        "callback_path" => "online-admission-hubtel-callback.php",
        "return_path" => "online-admission-hubtel-return.php",
        "cancel_path" => "online-admission-hubtel-cancel.php",
        "title" => trim((string)getenv("HUBTEL_PAYMENT_TITLE")),
        "description" => trim((string)getenv("HUBTEL_PAYMENT_DESCRIPTION")),
        "logo_url" => trim((string)getenv("HUBTEL_LOGO_URL"))
    );
    $configFile = __DIR__.DIRECTORY_SEPARATOR."online-admission-hubtel-config.php";
    if(file_exists($configFile)){
        $loaded = include $configFile;
        if(is_array($loaded)){
            foreach($loaded as $key => $value){
                if(in_array($key, array("callback_path", "return_path", "cancel_path"), true) && trim((string)$value) !== ""){
                    $config[$key] = trim((string)$value);
                }elseif(trim((string)$value) !== ""){
                    $config[$key] = trim((string)$value);
                }
            }
        }
    }
    return $config;
}
}

if(!function_exists('online_admission_hubtel_is_ready')){
function online_admission_hubtel_is_ready($config){
    return isset($config["client_id"], $config["client_secret"], $config["request_money_url_template"]) &&
        trim((string)$config["client_id"]) !== "" &&
        trim((string)$config["client_secret"]) !== "" &&
        trim((string)$config["request_money_url_template"]) !== "";
}
}

if(!function_exists('online_admission_paystack_config')){
function online_admission_paystack_config(){
    $config = array(
        "public_key" => trim((string)getenv("PAYSTACK_PUBLIC_KEY")),
        "secret_key" => trim((string)getenv("PAYSTACK_SECRET_KEY")),
        "callback_path" => "online-admission-paystack-callback.php"
    );
    $configFile = __DIR__.DIRECTORY_SEPARATOR."online-admission-paystack-config.php";
    if(file_exists($configFile)){
        $loaded = include $configFile;
        if(is_array($loaded)){
            foreach($loaded as $key => $value){
                if($key === "callback_path" && trim((string)$value) !== ""){
                    $config[$key] = trim((string)$value);
                }elseif(trim((string)$value) !== ""){
                    $config[$key] = trim((string)$value);
                }
            }
        }
    }
    return $config;
}
}

if(!function_exists('online_admission_paystack_is_ready')){
function online_admission_paystack_is_ready($config){
    return isset($config["secret_key"]) && trim((string)$config["secret_key"]) !== "";
}
}

if(!function_exists('online_admission_app_url')){
function online_admission_app_url($path){
    $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") || (isset($_SERVER["SERVER_PORT"]) && (string)$_SERVER["SERVER_PORT"] === "443");
    $scheme = $https ? "https" : "http";
    $host = isset($_SERVER["HTTP_HOST"]) && trim((string)$_SERVER["HTTP_HOST"]) !== "" ? trim((string)$_SERVER["HTTP_HOST"]) : "localhost";
    $scriptName = isset($_SERVER["SCRIPT_NAME"]) ? (string)$_SERVER["SCRIPT_NAME"] : "";
    $basePath = str_replace("\\", "/", dirname($scriptName));
    if($basePath === "/" || $basePath === "\\"){
        $basePath = "";
    }
    return $scheme."://".$host.rtrim($basePath, "/")."/".ltrim($path, "/");
}
}

if(!function_exists('online_admission_payment_callback_url')){
function online_admission_payment_callback_url($config = array(), $pathKey = "callback_path", $defaultPath = "online-admission-hubtel-callback.php"){
    $path = isset($config[$pathKey]) && trim((string)$config[$pathKey]) !== "" ? trim((string)$config[$pathKey]) : $defaultPath;
    return online_admission_app_url($path);
}
}

if(!function_exists('online_admission_hubtel_callback_url')){
function online_admission_hubtel_callback_url($config = array()){
    return online_admission_payment_callback_url($config, "callback_path", "online-admission-hubtel-callback.php");
}
}

if(!function_exists('online_admission_hubtel_return_url')){
function online_admission_hubtel_return_url($config, $reference){
    return online_admission_payment_callback_url($config, "return_path", "online-admission-hubtel-return.php")."?reference=".rawurlencode((string)$reference);
}
}

if(!function_exists('online_admission_hubtel_cancel_url')){
function online_admission_hubtel_cancel_url($config, $reference){
    return online_admission_payment_callback_url($config, "cancel_path", "online-admission-hubtel-cancel.php")."?reference=".rawurlencode((string)$reference);
}
}

if(!function_exists('online_admission_payment_customer_email')){
function online_admission_payment_customer_email($application){
    $email = trim((string)(isset($application["email"]) ? $application["email"] : ""));
    if($email !== "" && filter_var($email, FILTER_VALIDATE_EMAIL)){
        return $email;
    }
    return "ayirebishs@ges.gov.gh";
}
}

if(!function_exists('online_admission_money_minor_units')){
function online_admission_money_minor_units($amount){
    return (string)max(0, (int)round(((float)$amount) * 100));
}
}

if(!function_exists('online_admission_normalize_mobile_money_number')){
function online_admission_normalize_mobile_money_number($value){
    $value = trim((string)$value);
    if($value === ""){
        return "";
    }
    if(substr($value, 0, 1) === "+"){
        $digits = "+".preg_replace('/\D+/', '', substr($value, 1));
        return preg_match('/^\+233\d{9}$/', $digits) ? $digits : false;
    }
    $digits = preg_replace('/\D+/', '', $value);
    if(preg_match('/^233\d{9}$/', $digits)){
        return "+".$digits;
    }
    if(preg_match('/^0\d{9}$/', $digits)){
        return "+233".substr($digits, 1);
    }
    if(preg_match('/^[1-9]\d{8}$/', $digits)){
        return "+233".$digits;
    }
    return false;
}
}

if(!function_exists('online_admission_normalize_sms_phone')){
function online_admission_normalize_sms_phone($value){
    $normalized = online_admission_normalize_mobile_money_number($value);
    if($normalized !== false && $normalized !== ""){
        return $normalized;
    }
    $digits = preg_replace('/\D+/', '', trim((string)$value));
    if(preg_match('/^233\d{9}$/', $digits)){
        return "+".$digits;
    }
    if(preg_match('/^0\d{9}$/', $digits)){
        return "+233".substr($digits, 1);
    }
    return "";
}
}

if(!function_exists('online_admission_candidate_name')){
function online_admission_candidate_name($record){
    $parts = array();
    foreach(array("firstname", "othernames", "surname") as $field){
        if(isset($record[$field]) && trim((string)$record[$field]) !== ""){
            $parts[] = trim((string)$record[$field]);
        }
    }
    return trim(implode(" ", $parts));
}
}

if(!function_exists('online_admission_help_status_label')){
function online_admission_help_status_label($status){
    $status = strtolower(trim((string)$status));
    if($status === "contacted"){ return "Contacted"; }
    if($status === "resolved"){ return "Resolved"; }
    return "Open";
}
}

if(!function_exists('online_admission_create_help_request')){
function online_admission_create_help_request($con, $data){
    $requestId = online_admission_generate_id("HELP_");
    $applicationId = isset($data["applicationid"]) ? trim((string)$data["applicationid"]) : "";
    $postingId = isset($data["postingid"]) ? trim((string)$data["postingid"]) : "";
    $beceIndex = online_admission_normalize_bece(isset($data["beceindexnumber"]) ? $data["beceindexnumber"] : "");
    $admissionYear = trim((string)(isset($data["admissionyear"]) ? $data["admissionyear"] : ""));
    $studentName = trim((string)(isset($data["studentname"]) ? $data["studentname"] : ""));
    $contactPhone = trim((string)(isset($data["contactphone"]) ? $data["contactphone"] : ""));
    $normalizedPhone = online_admission_normalize_sms_phone($contactPhone);
    if($normalizedPhone !== ""){
        $contactPhone = $normalizedPhone;
    }
    $verificationToken = strtoupper(trim((string)(isset($data["verificationtoken"]) ? $data["verificationtoken"] : "")));
    $helpMessage = trim((string)(isset($data["helpmessage"]) ? $data["helpmessage"] : ""));
    $branchId = trim((string)(isset($data["branchid"]) ? $data["branchid"] : ""));

    $stmt = mysqli_prepare($con, "INSERT INTO tblonlineadmissionhelprequest(
        requestid, applicationid, postingid, beceindexnumber, admissionyear, studentname,
        contactphone, verificationtoken, helpmessage, status, adminnote, requestedat, updatedat, branchid
    ) VALUES(
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, 'open', NULL, NOW(), NOW(), ?
    )");
    if(!$stmt){
        return false;
    }
    mysqli_stmt_bind_param(
        $stmt,
        "ssssssssss",
        $requestId, $applicationId, $postingId, $beceIndex, $admissionYear, $studentName,
        $contactPhone, $verificationToken, $helpMessage, $branchId
    );
    $saved = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if(!$saved){
        return false;
    }
    return $requestId;
}
}

if(!function_exists('online_admission_get_recent_help_requests')){
function online_admission_get_recent_help_requests($con, $branchId, $limit = 20){
    $requests = array();
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $limit = max(1, (int)$limit);
    $res = mysqli_query($con, "SELECT *
        FROM tblonlineadmissionhelprequest
        WHERE branchid='$branchIdEsc'
        ORDER BY requestedat DESC
        LIMIT ".$limit);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $requests[] = $row;
        }
    }
    return $requests;
}
}

if(!function_exists('online_admission_update_help_request')){
function online_admission_update_help_request($con, $branchId, $requestId, $status, $adminNote = ""){
    $allowedStatuses = array("open", "contacted", "resolved");
    $status = strtolower(trim((string)$status));
    if(!in_array($status, $allowedStatuses, true)){
        $status = "open";
    }
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $requestIdEsc = mysqli_real_escape_string($con, (string)$requestId);
    $statusEsc = mysqli_real_escape_string($con, $status);
    $noteEsc = mysqli_real_escape_string($con, trim((string)$adminNote));
    return mysqli_query($con, "UPDATE tblonlineadmissionhelprequest SET
        status='$statusEsc',
        adminnote='$noteEsc',
        updatedat=NOW()
        WHERE requestid='$requestIdEsc' AND branchid='$branchIdEsc'
        LIMIT 1");
}
}

if(!function_exists('online_admission_sms_gateway_send')){
function online_admission_sms_gateway_send($phone, $message, &$resultCode = null){
    include_once(__DIR__.DIRECTORY_SEPARATOR."house-master-utils.php");
    if(!function_exists('send_bulk_sms_message')){
        $resultCode = "SMS_GATEWAY_UNAVAILABLE";
        return false;
    }
    return send_bulk_sms_message($phone, $message, $resultCode);
}
}

if(!function_exists('online_admission_mark_payment_student_sms')){
function online_admission_mark_payment_student_sms($con, $paymentId, $status, $sent){
    $updates = array(
        "studentsmsstatus" => trim((string)$status) !== "" ? trim((string)$status) : ($sent ? "SENT" : "FAILED")
    );
    if($sent){
        $updates["studentsmssentat"] = date("Y-m-d H:i:s");
    }
    return online_admission_update_payment_record($con, $paymentId, $updates);
}
}

if(!function_exists('online_admission_mark_guardian_submission_sms')){
function online_admission_mark_guardian_submission_sms($con, $applicationId, $status, $sent){
    $applicationIdEsc = mysqli_real_escape_string($con, (string)$applicationId);
    $statusEsc = mysqli_real_escape_string($con, trim((string)$status) !== "" ? trim((string)$status) : ($sent ? "SENT" : "FAILED"));
    $updates = array("guardiansmsstatus='$statusEsc'", "updatedat=NOW()");
    if($sent){
        $updates[] = "guardiansmssentat=NOW()";
    }
    return mysqli_query($con, "UPDATE tblonlineadmissionapplication SET ".implode(", ", $updates)." WHERE applicationid='$applicationIdEsc' LIMIT 1");
}
}

if(!function_exists('online_admission_send_payment_token_sms')){
function online_admission_send_payment_token_sms($con, $application, $postedStudent, $payment, $schoolName = ""){
    $result = array("sent" => false, "status" => "", "phone" => "", "skipped" => true);
    if(!is_array($application) || empty($application) || !is_array($payment) || empty($payment)){
        $result["status"] = "INVALID_CONTEXT";
        return $result;
    }
    if(trim((string)(isset($payment["studentsmssentat"]) ? $payment["studentsmssentat"] : "")) !== ""){
        $result["status"] = trim((string)(isset($payment["studentsmsstatus"]) ? $payment["studentsmsstatus"] : "")) !== "" ? trim((string)$payment["studentsmsstatus"]) : "ALREADY_SENT";
        return $result;
    }
    $token = trim((string)(isset($application["verificationtoken"]) ? $application["verificationtoken"] : ""));
    if($token === ""){
        $result["status"] = "NO_TOKEN";
        return $result;
    }
    $phone = "";
    foreach(array(
        isset($application["mobile"]) ? $application["mobile"] : "",
        is_array($postedStudent) && isset($postedStudent["mobile"]) ? $postedStudent["mobile"] : ""
    ) as $candidatePhone){
        $phone = online_admission_normalize_sms_phone($candidatePhone);
        if($phone !== ""){
            break;
        }
    }
    if($phone === ""){
        $result["status"] = "NO_STUDENT_PHONE";
        online_admission_mark_payment_student_sms($con, $payment["paymentid"], $result["status"], false);
        return $result;
    }
    $schoolLabel = trim((string)$schoolName) !== "" ? trim((string)$schoolName) : "The school";
    $message = $schoolLabel.": Admission payment confirmed. Token: ".$token.". Log in again with your BECE index number, date of birth and token to open your form.";
    $statusCode = "";
    $sent = online_admission_sms_gateway_send($phone, $message, $statusCode);
    online_admission_mark_payment_student_sms($con, $payment["paymentid"], $statusCode, $sent);
    $result["sent"] = $sent;
    $result["status"] = $statusCode !== "" ? $statusCode : ($sent ? "SENT" : "FAILED");
    $result["phone"] = $phone;
    $result["skipped"] = false;
    return $result;
}
}

if(!function_exists('online_admission_send_guardian_submission_sms')){
function online_admission_send_guardian_submission_sms($con, $application, $schoolName = ""){
    $result = array("sent" => false, "status" => "", "phone" => "", "skipped" => true);
    if(!is_array($application) || empty($application) || trim((string)(isset($application["applicationid"]) ? $application["applicationid"] : "")) === ""){
        $result["status"] = "INVALID_CONTEXT";
        return $result;
    }
    if(trim((string)(isset($application["guardiansmssentat"]) ? $application["guardiansmssentat"] : "")) !== ""){
        $result["status"] = trim((string)(isset($application["guardiansmsstatus"]) ? $application["guardiansmsstatus"] : "")) !== "" ? trim((string)$application["guardiansmsstatus"]) : "ALREADY_SENT";
        return $result;
    }
    $phone = online_admission_normalize_sms_phone(isset($application["guardiancontact"]) ? $application["guardiancontact"] : "");
    if($phone === ""){
        $result["status"] = "NO_GUARDIAN_PHONE";
        return $result;
    }
    $studentName = online_admission_candidate_name($application);
    if($studentName === ""){
        $studentName = "Your ward";
    }
    $schoolLabel = trim((string)$schoolName) !== "" ? trim((string)$schoolName) : "The school";
    $message = $schoolLabel.": ".$studentName."'s online admission form has been submitted successfully. The school will review it and contact you if any correction is needed.";
    $statusCode = "";
    $sent = online_admission_sms_gateway_send($phone, $message, $statusCode);
    online_admission_mark_guardian_submission_sms($con, $application["applicationid"], $statusCode, $sent);
    $result["sent"] = $sent;
    $result["status"] = $statusCode !== "" ? $statusCode : ($sent ? "SENT" : "FAILED");
    $result["phone"] = $phone;
    $result["skipped"] = false;
    return $result;
}
}

if(!function_exists('online_admission_payment_status_label')){
function online_admission_payment_status_label($status){
    $status = strtolower(trim((string)$status));
    if($status === "success"){ return "Paid"; }
    if($status === "pending"){ return "Pending Verification"; }
    if($status === "initialized"){ return "Awaiting Payment"; }
    if($status === "failed"){ return "Failed"; }
    if($status === "abandoned"){ return "Abandoned"; }
    return "Not Started";
}
}

if(!function_exists('online_admission_payment_required_status')){
function online_admission_payment_required_status($setting){
    $requiredStatus = strtolower(trim((string)(isset($setting["payablestatus"]) ? $setting["payablestatus"] : "verified")));
    if(!in_array($requiredStatus, array("verified", "submitted", "reviewed"), true)){
        $requiredStatus = "verified";
    }
    return $requiredStatus;
}
}

if(!function_exists('online_admission_payment_open_for_student')){
function online_admission_payment_open_for_student($postedStudent, $application, $setting){
    if(!is_array($postedStudent) || empty($postedStudent)){
        return false;
    }
    if((int)(isset($setting["enabled"]) ? $setting["enabled"] : 0) !== 1){
        return false;
    }
    if((float)(isset($setting["feeamount"]) ? $setting["feeamount"] : 0) <= 0){
        return false;
    }
    $requiredStatus = online_admission_payment_required_status($setting);
    if($requiredStatus === "verified"){
        return true;
    }
    if(!is_array($application) || empty($application)){
        return false;
    }
    $status = strtolower(trim((string)$application["status"]));
    if($requiredStatus === "submitted"){
        return in_array($status, array("submitted", "needs_attention", "reviewed"), true);
    }
    return $status === "reviewed";
}
}

if(!function_exists('online_admission_get_latest_payment_by_application')){
function online_admission_get_latest_payment_by_application($con, $applicationId){
    $applicationIdEsc = mysqli_real_escape_string($con, (string)$applicationId);
    $res = mysqli_query($con, "SELECT * FROM tblonlineadmissionpayment WHERE applicationid='$applicationIdEsc' ORDER BY createdat DESC LIMIT 1");
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        return $row;
    }
    return null;
}
}

if(!function_exists('online_admission_get_latest_payment_by_posting')){
function online_admission_get_latest_payment_by_posting($con, $postingId){
    $postingIdEsc = mysqli_real_escape_string($con, (string)$postingId);
    $res = mysqli_query($con, "SELECT * FROM tblonlineadmissionpayment WHERE postingid='$postingIdEsc' ORDER BY createdat DESC LIMIT 1");
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        return $row;
    }
    return null;
}
}

if(!function_exists('online_admission_get_successful_payment_by_posting')){
function online_admission_get_successful_payment_by_posting($con, $postingId){
    $postingIdEsc = mysqli_real_escape_string($con, (string)$postingId);
    $res = mysqli_query($con, "SELECT * FROM tblonlineadmissionpayment WHERE postingid='$postingIdEsc' AND status='success' ORDER BY paidat DESC, createdat DESC LIMIT 1");
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        return $row;
    }
    return null;
}
}

if(!function_exists('online_admission_get_successful_payment_by_application')){
function online_admission_get_successful_payment_by_application($con, $applicationId){
    $applicationIdEsc = mysqli_real_escape_string($con, (string)$applicationId);
    $res = mysqli_query($con, "SELECT * FROM tblonlineadmissionpayment
        WHERE applicationid='$applicationIdEsc'
          AND status='success'
        ORDER BY paidat DESC, createdat DESC
        LIMIT 1");
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        return $row;
    }
    return null;
}
}

if(!function_exists('online_admission_get_payment_by_reference')){
function online_admission_get_payment_by_reference($con, $reference){
    $referenceEsc = mysqli_real_escape_string($con, (string)$reference);
    $res = mysqli_query($con, "SELECT * FROM tblonlineadmissionpayment WHERE reference='$referenceEsc' LIMIT 1");
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        return $row;
    }
    return null;
}
}

if(!function_exists('online_admission_minor_units_to_amount')){
function online_admission_minor_units_to_amount($value){
    return ((float)$value) / 100;
}
}

if(!function_exists('online_admission_list_recent_payments')){
function online_admission_list_recent_payments($con, $branchId, $limit = 40){
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $limit = max(1, (int)$limit);
    $payments = array();
    $res = mysqli_query($con, "SELECT pay.*, app.firstname, app.surname, app.othernames
        FROM tblonlineadmissionpayment pay
        LEFT JOIN tblonlineadmissionapplication app ON app.applicationid=pay.applicationid
        WHERE pay.branchid='$branchIdEsc'
        ORDER BY pay.createdat DESC
        LIMIT $limit");
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $payments[] = $row;
        }
    }
    return $payments;
}
}

if(!function_exists('online_admission_create_payment_record')){
function online_admission_create_payment_record($con, $data){
    $paymentId = isset($data["paymentid"]) && trim((string)$data["paymentid"]) !== "" ? trim((string)$data["paymentid"]) : online_admission_generate_id("ADMPAY_");
    $gateway = isset($data["gateway"]) && trim((string)$data["gateway"]) !== "" ? trim((string)$data["gateway"]) : "paystack";
    $stmt = mysqli_prepare($con, "INSERT INTO tblonlineadmissionpayment(
        paymentid, applicationid, postingid, beceindexnumber, admissionyear, branchid, gateway, reference,
        accesscode, authorizationurl, gatewaytransactionid, amount, currency, email, mobile, status,
        gatewayresponse, admissioncode, codeissuedat, rawresponse, paidat, verifiedat, createdat, updatedat
    ) VALUES(
        ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, NOW(), NOW()
    )");
    if(!$stmt){
        return false;
    }
    $amountFormatted = number_format((float)$data["amount"], 2, ".", "");
    $status = isset($data["status"]) ? (string)$data["status"] : "initialized";
    $gatewayResponse = isset($data["gatewayresponse"]) ? (string)$data["gatewayresponse"] : "";
    $admissionCode = isset($data["admissioncode"]) ? (string)$data["admissioncode"] : null;
    $codeIssuedAt = isset($data["codeissuedat"]) ? (string)$data["codeissuedat"] : null;
    $rawResponse = isset($data["rawresponse"]) ? (string)$data["rawresponse"] : "";
    $paidAt = isset($data["paidat"]) ? (string)$data["paidat"] : null;
    $verifiedAt = isset($data["verifiedat"]) ? (string)$data["verifiedat"] : null;
    mysqli_stmt_bind_param(
        $stmt,
        "sssssssssssdssssssssss",
        $paymentId,
        $data["applicationid"],
        $data["postingid"],
        $data["beceindexnumber"],
        $data["admissionyear"],
        $data["branchid"],
        $gateway,
        $data["reference"],
        $data["accesscode"],
        $data["authorizationurl"],
        $data["gatewaytransactionid"],
        $amountFormatted,
        $data["currency"],
        $data["email"],
        $data["mobile"],
        $status,
        $gatewayResponse,
        $admissionCode,
        $codeIssuedAt,
        $rawResponse,
        $paidAt,
        $verifiedAt
    );
    $saved = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $saved ? $paymentId : false;
}
}

if(!function_exists('online_admission_update_payment_record')){
function online_admission_update_payment_record($con, $paymentId, $data){
    $paymentIdEsc = mysqli_real_escape_string($con, (string)$paymentId);
    $updates = array();
    foreach(array("applicationid", "accesscode", "authorizationurl", "gatewaytransactionid", "mobile", "status", "gatewayresponse", "admissioncode", "codeissuedat", "rawresponse", "paidat", "verifiedat", "studentsmssentat", "studentsmsstatus") as $field){
        if(array_key_exists($field, $data)){
            if($data[$field] === null){
                $updates[] = $field."=NULL";
            }else{
                $updates[] = $field."='".mysqli_real_escape_string($con, (string)$data[$field])."'";
            }
        }
    }
    $updates[] = "updatedat=NOW()";
    if(empty($updates)){
        return true;
    }
    return mysqli_query($con, "UPDATE tblonlineadmissionpayment SET ".implode(", ", $updates)." WHERE paymentid='$paymentIdEsc' LIMIT 1");
}
}

if(!function_exists('online_admission_payment_is_paid')){
function online_admission_payment_is_paid($payment){
    return is_array($payment) && strtolower(trim((string)$payment["status"])) === "success";
}
}

if(!function_exists('online_admission_generate_payment_code')){
function online_admission_generate_payment_code($con){
    do{
        $code = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', md5(uniqid('', true))), 0, 8));
        $codeEsc = mysqli_real_escape_string($con, $code);
        $exists = mysqli_query($con, "SELECT paymentid FROM tblonlineadmissionpayment WHERE admissioncode='$codeEsc' LIMIT 1");
    }while($exists && mysqli_num_rows($exists) > 0);
    return $code;
}
}

if(!function_exists('online_admission_http_json_request')){
function online_admission_http_json_request($method, $url, $headers, $payload, &$errorMessage){
    $errorMessage = "";
    if(!function_exists("curl_init")){
        $errorMessage = "cURL is not enabled on this server.";
        return false;
    }
    $ch = curl_init($url);
    if(!$ch){
        $errorMessage = "The payment request could not be started.";
        return false;
    }
    $httpHeaders = array();
    foreach($headers as $header => $value){
        $httpHeaders[] = $header.": ".$value;
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper((string)$method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
    if($payload !== null){
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $response = curl_exec($ch);
    if($response === false){
        $errorMessage = curl_error($ch);
        curl_close($ch);
        return false;
    }
    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode($response, true);
    if(!is_array($decoded)){
        $errorMessage = "Unexpected payment gateway response.";
        return false;
    }
    $decoded["_http_status"] = $statusCode;
    $decoded["_raw"] = $response;
    return $decoded;
}
}

if(!function_exists('online_admission_paystack_initialize')){
function online_admission_paystack_initialize($config, $payload, &$errorMessage){
    $errorMessage = "";
    if(!online_admission_paystack_is_ready($config)){
        $errorMessage = "Paystack is not configured yet.";
        return false;
    }
    $response = online_admission_http_json_request(
        "POST",
        "https://api.paystack.co/transaction/initialize",
        array(
            "Authorization" => "Bearer ".trim((string)$config["secret_key"]),
            "Content-Type" => "application/json",
            "Cache-Control" => "no-cache"
        ),
        $payload,
        $errorMessage
    );
    if($response === false){
        return false;
    }
    if(empty($response["status"])){
        $errorMessage = isset($response["message"]) ? (string)$response["message"] : "Paystack could not initialize this payment.";
        return false;
    }
    return $response;
}
}

if(!function_exists('online_admission_paystack_verify')){
function online_admission_paystack_verify($config, $reference, &$errorMessage){
    $errorMessage = "";
    if(!online_admission_paystack_is_ready($config)){
        $errorMessage = "Paystack is not configured yet.";
        return false;
    }
    $response = online_admission_http_json_request(
        "GET",
        "https://api.paystack.co/transaction/verify/".rawurlencode((string)$reference),
        array(
            "Authorization" => "Bearer ".trim((string)$config["secret_key"]),
            "Cache-Control" => "no-cache"
        ),
        null,
        $errorMessage
    );
    if($response === false){
        return false;
    }
    if(empty($response["status"])){
        $errorMessage = isset($response["message"]) ? (string)$response["message"] : "Paystack could not verify this payment.";
        return false;
    }
    return $response;
}
}

if(!function_exists('online_admission_paystack_signature_is_valid')){
function online_admission_paystack_signature_is_valid($config, $rawPayload, $signature){
    $secret = trim((string)(isset($config["secret_key"]) ? $config["secret_key"] : ""));
    $signature = trim((string)$signature);
    if($secret === "" || $signature === "" || $rawPayload === ""){
        return false;
    }
    $expected = hash_hmac("sha512", $rawPayload, $secret);
    return hash_equals($expected, $signature);
}
}

if(!function_exists('online_admission_resolve_payment_context')){
function online_admission_resolve_payment_context($con, $payment){
    $application = null;
    $postedStudent = null;
    $branchId = trim((string)(isset($payment["branchid"]) ? $payment["branchid"] : ""));
    if(trim((string)(isset($payment["applicationid"]) ? $payment["applicationid"] : "")) !== ""){
        $application = online_admission_get_application_by_id($con, $payment["applicationid"]);
    }
    if(!$application && $branchId !== "" && trim((string)(isset($payment["postingid"]) ? $payment["postingid"] : "")) !== ""){
        $postedStudent = online_admission_get_posted_student_by_id($con, $branchId, $payment["postingid"]);
        if($postedStudent){
            $application = online_admission_ensure_application_for_posting($con, $postedStudent);
        }
    }
    if($application){
        $application = online_admission_ensure_application_token($con, $application);
        online_admission_attach_payments_to_application($con, $application["postingid"], $application["applicationid"]);
        if(!$postedStudent && $branchId !== ""){
            $postedStudent = online_admission_get_posted_student_by_id($con, $branchId, $application["postingid"]);
        }
    }
    return array(
        "application" => $application,
        "postedStudent" => $postedStudent
    );
}
}

if(!function_exists('online_admission_process_paystack_payment_result')){
function online_admission_process_paystack_payment_result($con, $payment, $data, $rawResponse = "", $responseMessage = ""){
    $context = online_admission_resolve_payment_context($con, $payment);
    $application = $context["application"];
    $postedStudent = $context["postedStudent"];

    $gatewayStatus = strtolower(trim((string)(isset($data["status"]) ? $data["status"] : "pending")));
    if($gatewayStatus === ""){
        $gatewayStatus = "pending";
    }
    $storedStatus = $gatewayStatus;
    if(!in_array($storedStatus, array("success", "pending", "initialized", "failed", "abandoned"), true)){
        $storedStatus = ($gatewayStatus === "success") ? "success" : "pending";
    }

    $paidAt = null;
    if(isset($data["paid_at"]) && trim((string)$data["paid_at"]) !== ""){
        $paidTimestamp = strtotime((string)$data["paid_at"]);
        if($paidTimestamp !== false){
            $paidAt = date("Y-m-d H:i:s", $paidTimestamp);
        }
    }

    $responseReference = trim((string)(isset($data["reference"]) ? $data["reference"] : ""));
    $responseCurrency = strtoupper(trim((string)(isset($data["currency"]) ? $data["currency"] : "")));
    $responseAmount = isset($data["amount"]) ? online_admission_minor_units_to_amount($data["amount"]) : 0;
    $expectedCurrency = strtoupper(trim((string)(isset($payment["currency"]) ? $payment["currency"] : "")));
    $expectedAmount = number_format((float)(isset($payment["amount"]) ? $payment["amount"] : 0), 2, ".", "");

    $referenceMatches = ($responseReference !== "" && hash_equals((string)$payment["reference"], $responseReference));
    $amountMatches = (number_format($responseAmount, 2, ".", "") === $expectedAmount);
    $currencyMatches = ($responseCurrency !== "" && $responseCurrency === $expectedCurrency);
    $applicationMatches = true;
    $postingMatches = true;
    if(isset($data["metadata"]) && is_array($data["metadata"])){
        if($application && isset($data["metadata"]["applicationid"]) && trim((string)$data["metadata"]["applicationid"]) !== ""){
            $applicationMatches = hash_equals((string)$application["applicationid"], trim((string)$data["metadata"]["applicationid"]));
        }
        if(isset($data["metadata"]["postingid"]) && trim((string)$data["metadata"]["postingid"]) !== ""){
            $postingMatches = hash_equals((string)$payment["postingid"], trim((string)$data["metadata"]["postingid"]));
        }
    }

    $integrityFailed = ($storedStatus === "success" && (!$referenceMatches || !$amountMatches || !$currencyMatches || !$applicationMatches || !$postingMatches));
    if($integrityFailed){
        $storedStatus = "pending";
    }

    $admissionCode = trim((string)(isset($payment["admissioncode"]) ? $payment["admissioncode"] : ""));
    $codeIssuedAt = trim((string)(isset($payment["codeissuedat"]) ? $payment["codeissuedat"] : ""));
    if($storedStatus === "success" && $admissionCode === ""){
        $admissionCode = online_admission_generate_payment_code($con);
        $codeIssuedAt = date("Y-m-d H:i:s");
    }

    online_admission_update_payment_record($con, $payment["paymentid"], array(
        "applicationid" => $application ? (string)$application["applicationid"] : (trim((string)$payment["applicationid"]) !== "" ? (string)$payment["applicationid"] : null),
        "accesscode" => isset($data["access_code"]) ? (string)$data["access_code"] : (string)$payment["accesscode"],
        "authorizationurl" => isset($data["authorization_url"]) ? (string)$data["authorization_url"] : (string)$payment["authorizationurl"],
        "gatewaytransactionid" => isset($data["id"]) ? (string)$data["id"] : (string)$payment["gatewaytransactionid"],
        "status" => $storedStatus,
        "gatewayresponse" => $integrityFailed
            ? "Payment verification failed local integrity checks."
            : (isset($data["gateway_response"]) ? (string)$data["gateway_response"] : ($responseMessage !== "" ? (string)$responseMessage : ucfirst($storedStatus))),
        "admissioncode" => $admissionCode !== "" ? $admissionCode : null,
        "codeissuedat" => $codeIssuedAt !== "" ? $codeIssuedAt : null,
        "rawresponse" => (string)$rawResponse,
        "paidat" => $paidAt,
        "verifiedat" => date("Y-m-d H:i:s")
    ));

    $updatedPayment = online_admission_get_payment_by_reference($con, (string)$payment["reference"]);
    if($storedStatus === "success" && $application){
        online_admission_send_payment_token_sms($con, $application, $postedStudent, $updatedPayment ? $updatedPayment : $payment);
    }

    return array(
        "application" => $application,
        "postedStudent" => $postedStudent,
        "stored_status" => $storedStatus,
        "integrity_failed" => $integrityFailed,
        "payment" => $updatedPayment ? $updatedPayment : $payment
    );
}
}

if(!function_exists('online_admission_hubtel_request_money_url')){
function online_admission_hubtel_request_money_url($config, $mobileNumber){
    $template = trim((string)(isset($config["request_money_url_template"]) ? $config["request_money_url_template"] : ""));
    if($template === ""){
        return "";
    }
    if(strpos($template, "{mobileNumber}") !== false){
        return str_replace("{mobileNumber}", rawurlencode((string)$mobileNumber), $template);
    }
    if(strpos($template, "{mobile}") !== false){
        return str_replace("{mobile}", rawurlencode((string)$mobileNumber), $template);
    }
    return rtrim($template, "/")."/request-money/".rawurlencode((string)$mobileNumber);
}
}

if(!function_exists('online_admission_hubtel_request_money')){
function online_admission_hubtel_request_money($config, $mobileNumber, $payload, &$errorMessage){
    $errorMessage = "";
    if(!online_admission_hubtel_is_ready($config)){
        $errorMessage = "Hubtel is not configured yet.";
        return false;
    }
    $url = online_admission_hubtel_request_money_url($config, $mobileNumber);
    if($url === ""){
        $errorMessage = "The Hubtel request-money URL is missing.";
        return false;
    }
    $basicAuth = base64_encode(trim((string)$config["client_id"]).":".trim((string)$config["client_secret"]));
    $response = online_admission_http_json_request(
        "POST",
        $url,
        array(
            "Authorization" => "Basic ".$basicAuth,
            "Content-Type" => "application/json",
            "Accept" => "application/json",
            "Cache-Control" => "no-cache"
        ),
        $payload,
        $errorMessage
    );
    if($response === false){
        return false;
    }
    if(!isset($response["data"]) || !is_array($response["data"]) || trim((string)(isset($response["data"]["paylinkUrl"]) ? $response["data"]["paylinkUrl"] : "")) === ""){
        $errorMessage = isset($response["message"]) ? (string)$response["message"] : "Hubtel could not start this payment.";
        return false;
    }
    return $response;
}
}

if(!function_exists('online_admission_hubtel_callback_status')){
function online_admission_hubtel_callback_status($callbackData){
    $status = strtolower(trim((string)(isset($callbackData["status"]) ? $callbackData["status"] : "")));
    if(in_array($status, array("success", "successful", "paid", "completed"), true)){
        return "success";
    }
    if(in_array($status, array("failed", "error", "declined"), true)){
        return "failed";
    }
    if(in_array($status, array("cancelled", "canceled", "abandoned"), true)){
        return "abandoned";
    }
    // Hubtel documents this webhook as a post-payment callback, so unknown statuses fall back to success.
    return "success";
}
}

if(!function_exists('online_admission_photo_src')){
function online_admission_photo_src($filename){
    $filename = trim((string)$filename);
    if($filename !== "" && file_exists(__DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR.$filename)){
        return "uploads/".rawurlencode($filename);
    }
    return "uploads/comm.gif";
}
}

if(!function_exists('online_admission_store_image')){
function online_admission_store_image($file, &$errorMessage){
    $errorMessage = "";
    if(!isset($file["error"]) || $file["error"] === UPLOAD_ERR_NO_FILE || !isset($file["name"]) || trim((string)$file["name"]) === ""){
        return "";
    }
    if($file["error"] !== UPLOAD_ERR_OK){
        $errorMessage = "Image upload failed.";
        return false;
    }
    if(!isset($file["tmp_name"]) || !is_uploaded_file($file["tmp_name"])){
        $errorMessage = "The selected image upload is invalid.";
        return false;
    }
    if(isset($file["size"]) && (int)$file["size"] > 5 * 1024 * 1024){
        $errorMessage = "The image is too large. Please use a file smaller than 5MB.";
        return false;
    }

    $ext = strtolower(pathinfo((string)$file["name"], PATHINFO_EXTENSION));
    $allowedExtensions = array("jpg", "jpeg", "png", "gif", "webp");
    if(!in_array($ext, $allowedExtensions, true)){
        $errorMessage = "Please upload a JPG, PNG, GIF, or WEBP image.";
        return false;
    }

    $mime = "";
    if(function_exists("finfo_open")){
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if($finfo){
            $mime = (string)@finfo_file($finfo, $file["tmp_name"]);
            finfo_close($finfo);
        }
    }
    $allowedMimes = array("image/jpeg", "image/png", "image/gif", "image/webp");
    if($mime !== "" && !in_array($mime, $allowedMimes, true)){
        $errorMessage = "The selected file is not a valid image.";
        return false;
    }

    $uploadDir = __DIR__.DIRECTORY_SEPARATOR."uploads";
    if(!is_dir($uploadDir)){
        $errorMessage = "The uploads folder is missing on the server.";
        return false;
    }

    $storedName = "admission-".date("YmdHis")."-".substr(md5(uniqid('', true)), 0, 8).".".$ext;
    $destination = $uploadDir.DIRECTORY_SEPARATOR.$storedName;
    if(!move_uploaded_file($file["tmp_name"], $destination)){
        $errorMessage = "The image could not be moved to the uploads folder.";
        return false;
    }
    return $storedName;
}
}
