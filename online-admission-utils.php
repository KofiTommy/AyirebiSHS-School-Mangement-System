<?php
include_once(__DIR__.DIRECTORY_SEPARATOR."house-master-utils.php");
if(!function_exists('xschool_schema_cache_is_fresh')){
function xschool_schema_cache_is_fresh($key, $ttlSeconds = 900){
    static $memoryCache = array();
    $key = trim((string)$key);
    if($key === ""){
        return false;
    }
    if(isset($memoryCache[$key])){
        return $memoryCache[$key];
    }
    if(PHP_SAPI === 'cli' || !function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE){
        $memoryCache[$key] = false;
        return false;
    }
    $cacheBag = isset($_SESSION['_xschool_schema_cache']) && is_array($_SESSION['_xschool_schema_cache'])
        ? $_SESSION['_xschool_schema_cache']
        : array();
    $isFresh = isset($cacheBag[$key]) && ((int)$cacheBag[$key] + (int)$ttlSeconds) > time();
    $memoryCache[$key] = $isFresh;
    return $isFresh;
}
}

if(!function_exists('xschool_schema_cache_mark')){
function xschool_schema_cache_mark($key){
    static $memoryCache = array();
    $key = trim((string)$key);
    if($key === ""){
        return;
    }
    $memoryCache[$key] = true;
    if(PHP_SAPI === 'cli' || !function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE){
        return;
    }
    if(!isset($_SESSION['_xschool_schema_cache']) || !is_array($_SESSION['_xschool_schema_cache'])){
        $_SESSION['_xschool_schema_cache'] = array();
    }
    $_SESSION['_xschool_schema_cache'][$key] = time();
}
}

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

if(!function_exists('online_admission_can_manage_portal')){
function online_admission_can_manage_portal($con = null){
    if(online_admission_is_admin()){
        return true;
    }
    if(!$con || !function_exists('um_current_user_can_access_module')){
        return false;
    }
    return um_current_user_can_access_module($con, 'online_admission');
}
}

if(!function_exists('ensure_online_admission_tables')){
function ensure_online_admission_tables($con){
    ensure_house_tables($con);
    if(xschool_schema_cache_is_fresh('schema_online_admission_v4')){
        return;
    }
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
        assignedhouseid VARCHAR(40) NULL,
        assignedhouseat DATETIME NULL,
        linkedstudentid VARCHAR(40) NULL,
        linkedstudentat DATETIME NULL,
        branchid VARCHAR(30) NOT NULL,
        UNIQUE KEY uq_application_posting (postingid),
        INDEX idx_application_status (status),
        INDEX idx_application_bece (beceindexnumber),
        INDEX idx_application_branch (branchid),
        INDEX idx_application_house (assignedhouseid),
        INDEX idx_application_linked_student (linkedstudentid)
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

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblonlineadmissiondocument (
        documentid VARCHAR(40) NOT NULL PRIMARY KEY,
        branchid VARCHAR(30) NOT NULL,
        admissionyear VARCHAR(20) NOT NULL,
        doctype VARCHAR(40) NOT NULL,
        documentgroup VARCHAR(30) NOT NULL DEFAULT 'general',
        targetgender VARCHAR(20) NULL,
        targetresidencetype VARCHAR(20) NULL,
        randomenabled TINYINT(1) NOT NULL DEFAULT 0,
        randompool VARCHAR(120) NULL,
        title VARCHAR(255) NULL,
        filename VARCHAR(255) NOT NULL,
        originalfilename VARCHAR(255) NULL,
        mimetype VARCHAR(120) NULL,
        filesize BIGINT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        uploadedat DATETIME NOT NULL,
        updatedat DATETIME NOT NULL,
        uploadedby VARCHAR(40) NULL,
        UNIQUE KEY uq_admissiondocument_branch_year_type (branchid, admissionyear, doctype),
        INDEX idx_admissiondocument_branch_year (branchid, admissionyear),
        INDEX idx_admissiondocument_status (status)
    )");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblonlineadmissiondocumentassignment (
        assignmentid VARCHAR(40) NOT NULL PRIMARY KEY,
        documentid VARCHAR(40) NOT NULL,
        applicationid VARCHAR(40) NOT NULL,
        randompool VARCHAR(120) NOT NULL,
        admissionyear VARCHAR(20) NOT NULL,
        branchid VARCHAR(30) NOT NULL,
        assignedat DATETIME NOT NULL,
        updatedat DATETIME NOT NULL,
        UNIQUE KEY uq_admissiondocassign_app_pool (applicationid, randompool),
        INDEX idx_admissiondocassign_document (documentid),
        INDEX idx_admissiondocassign_branch_year (branchid, admissionyear)
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
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissionapplication LIKE 'assignedhouseid'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissionapplication ADD COLUMN assignedhouseid VARCHAR(40) NULL AFTER revieweddatetime");
        mysqli_query($con, "CREATE INDEX idx_application_house ON tblonlineadmissionapplication(assignedhouseid)");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissionapplication LIKE 'assignedhouseat'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissionapplication ADD COLUMN assignedhouseat DATETIME NULL AFTER assignedhouseid");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissionapplication LIKE 'linkedstudentid'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissionapplication ADD COLUMN linkedstudentid VARCHAR(40) NULL AFTER assignedhouseat");
        mysqli_query($con, "CREATE INDEX idx_application_linked_student ON tblonlineadmissionapplication(linkedstudentid)");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissionapplication LIKE 'linkedstudentat'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissionapplication ADD COLUMN linkedstudentat DATETIME NULL AFTER linkedstudentid");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissiondocument LIKE 'documentgroup'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissiondocument ADD COLUMN documentgroup VARCHAR(30) NOT NULL DEFAULT 'general' AFTER doctype");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissiondocument LIKE 'targetgender'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissiondocument ADD COLUMN targetgender VARCHAR(20) NULL AFTER documentgroup");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissiondocument LIKE 'targetresidencetype'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissiondocument ADD COLUMN targetresidencetype VARCHAR(20) NULL AFTER targetgender");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissiondocument LIKE 'randomenabled'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissiondocument ADD COLUMN randomenabled TINYINT(1) NOT NULL DEFAULT 0 AFTER targetresidencetype");
    }
    $columnRes = mysqli_query($con, "SHOW COLUMNS FROM tblonlineadmissiondocument LIKE 'randompool'");
    if($columnRes && mysqli_num_rows($columnRes) === 0){
        mysqli_query($con, "ALTER TABLE tblonlineadmissiondocument ADD COLUMN randompool VARCHAR(120) NULL AFTER randomenabled");
    }
    xschool_schema_cache_mark('schema_online_admission_v5');
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

if(!function_exists('online_admission_get_house_by_id')){
function online_admission_get_house_by_id($con, $houseId){
    $houseIdEsc = mysqli_real_escape_string($con, trim((string)$houseId));
    if($houseIdEsc === ""){
        return null;
    }
    $res = mysqli_query($con, "SELECT * FROM tblhouse WHERE houseid='$houseIdEsc' LIMIT 1");
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        return $row;
    }
    return null;
}
}

if(!function_exists('online_admission_application_gender')){
function online_admission_application_gender($application, $postedStudent = null){
    $gender = house_master_normalize_gender_label(is_array($application) && isset($application["gender"]) ? $application["gender"] : "");
    if($gender === "" && is_array($postedStudent)){
        $gender = house_master_normalize_gender_label(isset($postedStudent["gender"]) ? $postedStudent["gender"] : "");
    }
    return $gender;
}
}

if(!function_exists('online_admission_application_residence')){
function online_admission_application_residence($application, $postedStudent = null){
    $residence = "";
    if(is_array($postedStudent)){
        $residence = house_master_normalize_residence_label(isset($postedStudent["residentialstatus"]) ? $postedStudent["residentialstatus"] : "");
    }
    if($residence === ""){
        $residence = house_master_normalize_residence_label(is_array($application) && isset($application["residencetype"]) ? $application["residencetype"] : "");
    }
    return $residence;
}
}

if(!function_exists('online_admission_application_assigned_house')){
function online_admission_application_assigned_house($con, $application){
    if(!is_array($application) || empty($application)){
        return null;
    }
    return online_admission_get_house_by_id($con, isset($application["assignedhouseid"]) ? $application["assignedhouseid"] : "");
}
}

if(!function_exists('online_admission_house_load_total')){
function online_admission_house_load_total($con, $houseId, $branchId, $excludeApplicationId = ""){
    $houseIdEsc = mysqli_real_escape_string($con, trim((string)$houseId));
    $branchIdEsc = mysqli_real_escape_string($con, trim((string)$branchId));
    $excludeApplicationIdEsc = mysqli_real_escape_string($con, trim((string)$excludeApplicationId));
    if($houseIdEsc === ""){
        return PHP_INT_MAX;
    }

    $studentTotal = 0;
    $studentSql = "SELECT COUNT(*) AS total
        FROM tblstudenthouse sh
        INNER JOIN tblsystemuser su ON su.userid=sh.userid
        WHERE sh.houseid='$houseIdEsc'
          AND sh.status='active'
          AND su.status='active'";
    if($branchIdEsc !== ""){
        $studentSql .= " AND su.branchid='$branchIdEsc'";
    }
    $studentRes = mysqli_query($con, $studentSql);
    if($studentRes && ($row = mysqli_fetch_array($studentRes, MYSQLI_ASSOC))){
        $studentTotal = (int)$row["total"];
    }

    $applicationTotal = 0;
    $appSql = "SELECT COUNT(*) AS total
        FROM tblonlineadmissionapplication
        WHERE assignedhouseid='$houseIdEsc'
          AND (linkedstudentid IS NULL OR linkedstudentid='')";
    if($branchIdEsc !== ""){
        $appSql .= " AND branchid='$branchIdEsc'";
    }
    if($excludeApplicationIdEsc !== ""){
        $appSql .= " AND applicationid<>'$excludeApplicationIdEsc'";
    }
    $appRes = mysqli_query($con, $appSql);
    if($appRes && ($row = mysqli_fetch_array($appRes, MYSQLI_ASSOC))){
        $applicationTotal = (int)$row["total"];
    }

    return $studentTotal + $applicationTotal;
}
}

if(!function_exists('online_admission_find_best_house')){
function online_admission_find_best_house($con, $branchId, $gender, $residence, $excludeApplicationId = ""){
    $gender = house_master_normalize_gender_label($gender);
    $residence = house_master_normalize_residence_label($residence);
    if($gender === "" || $residence === ""){
        return null;
    }

    $houses = array();
    $res = mysqli_query($con, "SELECT * FROM tblhouse
        WHERE status='active'
          AND autoassignenabled=1
        ORDER BY housename ASC");
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            if(!house_master_house_profile_matches($row, $gender, $residence)){
                continue;
            }
            $row["_load"] = online_admission_house_load_total($con, $row["houseid"], $branchId, $excludeApplicationId);
            $houses[] = $row;
        }
    }
    if(empty($houses)){
        return null;
    }

    usort($houses, function($left, $right){
        $leftLoad = isset($left["_load"]) ? (int)$left["_load"] : 0;
        $rightLoad = isset($right["_load"]) ? (int)$right["_load"] : 0;
        if($leftLoad === $rightLoad){
            return strcasecmp((string)$left["housename"], (string)$right["housename"]);
        }
        return $leftLoad < $rightLoad ? -1 : 1;
    });
    return $houses[0];
}
}

if(!function_exists('online_admission_assign_house_for_application')){
function online_admission_assign_house_for_application($con, $application, $postedStudent = null){
    if(!is_array($application) || empty($application) || trim((string)(isset($application["applicationid"]) ? $application["applicationid"] : "")) === ""){
        return null;
    }
    if(!online_admission_application_is_submitted($application)){
        return online_admission_application_assigned_house($con, $application);
    }

    if(!$postedStudent && trim((string)(isset($application["postingid"]) ? $application["postingid"] : "")) !== "" && trim((string)(isset($application["branchid"]) ? $application["branchid"] : "")) !== ""){
        $postedStudent = online_admission_get_posted_student_by_id($con, $application["branchid"], $application["postingid"]);
    }

    $paymentSetting = online_admission_get_payment_setting($con, (string)$application["branchid"]);
    $paymentEnabled = (int)$paymentSetting["enabled"] === 1 && (float)$paymentSetting["feeamount"] > 0;
    $successfulPayment = online_admission_get_successful_payment_by_application($con, (string)$application["applicationid"]);
    if(!online_admission_documents_unlocked($application, $successfulPayment, $paymentEnabled ? 1 : 0)){
        return online_admission_application_assigned_house($con, $application);
    }

    $gender = online_admission_application_gender($application, $postedStudent);
    $residence = online_admission_application_residence($application, $postedStudent);
    if($gender === "" || $residence === ""){
        return online_admission_application_assigned_house($con, $application);
    }

    $currentHouse = online_admission_application_assigned_house($con, $application);
    if($currentHouse && house_master_house_profile_matches($currentHouse, $gender, $residence)){
        return $currentHouse;
    }

    $bestHouse = online_admission_find_best_house($con, (string)$application["branchid"], $gender, $residence, (string)$application["applicationid"]);
    if(!$bestHouse){
        return $currentHouse;
    }

    $applicationIdEsc = mysqli_real_escape_string($con, (string)$application["applicationid"]);
    $houseIdEsc = mysqli_real_escape_string($con, (string)$bestHouse["houseid"]);
    mysqli_query($con, "UPDATE tblonlineadmissionapplication
        SET assignedhouseid='$houseIdEsc',
            assignedhouseat=NOW(),
            updatedat=NOW()
        WHERE applicationid='$applicationIdEsc'
        LIMIT 1");

    return online_admission_get_house_by_id($con, $bestHouse["houseid"]);
}
}

if(!function_exists('online_admission_find_unlinked_registration_application')){
function online_admission_find_unlinked_registration_application($con, $branchId, $beceIndex, $birthdate){
    $branchIdEsc = mysqli_real_escape_string($con, trim((string)$branchId));
    $beceEsc = mysqli_real_escape_string($con, online_admission_normalize_bece($beceIndex));
    $birthdateEsc = mysqli_real_escape_string($con, trim((string)$birthdate));
    if($branchIdEsc === "" || $beceEsc === "" || $birthdateEsc === ""){
        return null;
    }
    $res = mysqli_query($con, "SELECT app.*
        FROM tblonlineadmissionapplication app
        INNER JOIN tbladmissionpostedstudent post ON post.postingid=app.postingid
        WHERE app.branchid='$branchIdEsc'
          AND post.beceindexnumber='$beceEsc'
          AND post.birthdate='$birthdateEsc'
          AND (app.linkedstudentid IS NULL OR app.linkedstudentid='')
        ORDER BY app.submittedat DESC, app.updatedat DESC
        LIMIT 1");
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return $row;
    }
    return null;
}
}

if(!function_exists('online_admission_link_registered_student')){
function online_admission_link_registered_student($con, $applicationId, $studentId, $houseId = ""){
    $applicationIdEsc = mysqli_real_escape_string($con, trim((string)$applicationId));
    $studentIdEsc = mysqli_real_escape_string($con, trim((string)$studentId));
    $houseIdEsc = mysqli_real_escape_string($con, trim((string)$houseId));
    if($applicationIdEsc === "" || $studentIdEsc === ""){
        return false;
    }
    $updates = array(
        "linkedstudentid='$studentIdEsc'",
        "linkedstudentat=NOW()",
        "updatedat=NOW()"
    );
    if($houseIdEsc !== ""){
        $updates[] = "assignedhouseid='$houseIdEsc'";
        $updates[] = "assignedhouseat=COALESCE(assignedhouseat, NOW())";
    }
    return mysqli_query($con, "UPDATE tblonlineadmissionapplication SET ".implode(", ", $updates)." WHERE applicationid='$applicationIdEsc' LIMIT 1");
}
}

if(!function_exists('online_admission_finalize_registration_house')){
function online_admission_finalize_registration_house($con, $studentId, $branchId, $beceIndex, $birthdate, $recordedBy, $selectedHouseId = ""){
    $result = array(
        "found" => false,
        "linked" => false,
        "assigned" => false,
        "house" => null,
        "message" => ""
    );
    $application = online_admission_find_unlinked_registration_application($con, $branchId, $beceIndex, $birthdate);
    if(!$application){
        return $result;
    }
    $result["found"] = true;

    $houseId = trim((string)$selectedHouseId);
    if($houseId === ""){
        $houseId = trim((string)(isset($application["assignedhouseid"]) ? $application["assignedhouseid"] : ""));
    }

    if($houseId !== ""){
        $assigned = assign_student_to_house($con, $studentId, $houseId, $recordedBy);
        if(!$assigned){
            $result["message"] = "The online admission house could not be applied during registration.";
            return $result;
        }
        $result["assigned"] = true;
        $result["house"] = online_admission_get_house_by_id($con, $houseId);
    }

    $result["linked"] = online_admission_link_registered_student($con, $application["applicationid"], $studentId, $houseId);
    if(!$result["linked"]){
        $result["message"] = "The student was registered, but the online admission record could not be linked.";
        return $result;
    }

    if($result["assigned"] && is_array($result["house"]) && trim((string)$result["house"]["housename"]) !== ""){
        $result["message"] = "Student information saved and ".$result["house"]["housename"]." was assigned automatically from online admission.";
    }elseif($result["linked"]){
        $result["message"] = "Student information saved and linked to the online admission record.";
    }
    return $result;
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
    if(!isset($GLOBALS['_online_admission_payment_setting_cache']) || !is_array($GLOBALS['_online_admission_payment_setting_cache'])){
        $GLOBALS['_online_admission_payment_setting_cache'] = array();
    }
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
    $cacheKey = (string)$branchId;
    if(isset($GLOBALS['_online_admission_payment_setting_cache'][$cacheKey])){
        return $GLOBALS['_online_admission_payment_setting_cache'][$cacheKey];
    }
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $res = mysqli_query($con, "SELECT * FROM tblonlineadmissionpaymentsetting WHERE branchid='$branchIdEsc' LIMIT 1");
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        $GLOBALS['_online_admission_payment_setting_cache'][$cacheKey] = array_merge($defaults, $row);
        return $GLOBALS['_online_admission_payment_setting_cache'][$cacheKey];
    }
    $GLOBALS['_online_admission_payment_setting_cache'][$cacheKey] = $defaults;
    return $GLOBALS['_online_admission_payment_setting_cache'][$cacheKey];
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
    if($saved){
        $cacheKey = (string)$branchId;
        if(!isset($GLOBALS['_online_admission_payment_setting_cache']) || !is_array($GLOBALS['_online_admission_payment_setting_cache'])){
            $GLOBALS['_online_admission_payment_setting_cache'] = array();
        }
        $GLOBALS['_online_admission_payment_setting_cache'][$cacheKey] = array(
            "settingid" => $settingId,
            "branchid" => (string)$branchId,
            "portalenabled" => $portalEnabled,
            "paymentgateway" => "paystack",
            "feeamount" => number_format((float)$feeAmount, 2, ".", ""),
            "currency" => $currency,
            "enabled" => $enabled,
            "payablestatus" => $payableStatus,
            "note" => $note,
            "updatedby" => (string)$updatedBy
        );
    }
    return $saved;
}
}

if(!function_exists('online_admission_portal_is_open')){
function online_admission_portal_is_open($setting){
    return (int)(isset($setting["portalenabled"]) ? $setting["portalenabled"] : 1) === 1;
}
}

if(!function_exists('online_admission_document_label')){
function online_admission_document_label($docType){
    $docType = trim((string)$docType);
    if($docType === ""){
        return "Admission Document";
    }
    $docType = strtolower($docType);
    if(strpos($docType, "prospectus-") === 0){
        $docType = "prospectus ".substr($docType, strlen("prospectus-"));
    }else{
        $docType = preg_replace('/^document-/', '', $docType);
    }
    return ucwords(str_replace(array("_", "-"), " ", $docType));
}
}

if(!function_exists('online_admission_document_group')){
function online_admission_document_group($document){
    if(!is_array($document) || empty($document)){
        return "general";
    }
    $group = strtolower(trim((string)(isset($document["documentgroup"]) ? $document["documentgroup"] : "")));
    if(!in_array($group, array("general", "prospectus"), true)){
        $group = "general";
    }
    return $group;
}
}

if(!function_exists('online_admission_document_group_label')){
function online_admission_document_group_label($document){
    $group = is_array($document) ? online_admission_document_group($document) : strtolower(trim((string)$document));
    if($group === "prospectus"){
        return "Prospectus";
    }
    return "General Document";
}
}

if(!function_exists('online_admission_document_target_summary')){
function online_admission_document_target_summary($document){
    if(!is_array($document) || empty($document)){
        return "All Students";
    }
    $gender = house_master_normalize_gender_label(isset($document["targetgender"]) ? $document["targetgender"] : "");
    $residence = house_master_normalize_residence_label(isset($document["targetresidencetype"]) ? $document["targetresidencetype"] : "");
    if($gender === "" && $residence === ""){
        return "All Students";
    }
    if($gender !== "" && $residence !== ""){
        return $gender." ".$residence." Students";
    }
    if($gender !== ""){
        return $gender." Students";
    }
    return $residence." Students";
}
}

if(!function_exists('online_admission_document_random_enabled')){
function online_admission_document_random_enabled($document){
    return is_array($document) && !empty($document) && (int)(isset($document["randomenabled"]) ? $document["randomenabled"] : 0) === 1;
}
}

if(!function_exists('online_admission_document_random_pool')){
function online_admission_document_random_pool($document){
    if(!is_array($document) || empty($document)){
        return "";
    }
    $pool = trim((string)(isset($document["randompool"]) ? $document["randompool"] : ""));
    if($pool !== ""){
        return $pool;
    }
    if(online_admission_document_random_enabled($document)){
        $title = trim((string)(isset($document["title"]) ? $document["title"] : ""));
        if($title !== ""){
            return online_admission_document_slug($title);
        }
        return trim((string)(isset($document["doctype"]) ? $document["doctype"] : ""));
    }
    return "";
}
}

if(!function_exists('online_admission_document_delivery_label')){
function online_admission_document_delivery_label($document){
    if(online_admission_document_random_enabled($document)){
        return 'Random Assignment';
    }
    return 'Direct Download';
}
}

if(!function_exists('online_admission_document_matches_application')){
function online_admission_document_matches_application($document, $application, $postedStudent = null){
    if(!is_array($document) || empty($document)){
        return false;
    }
    $targetGender = house_master_normalize_gender_label(isset($document["targetgender"]) ? $document["targetgender"] : "");
    $targetResidence = house_master_normalize_residence_label(isset($document["targetresidencetype"]) ? $document["targetresidencetype"] : "");
    if($targetGender === "" && $targetResidence === ""){
        return true;
    }
    $applicationGender = online_admission_application_gender($application, $postedStudent);
    $applicationResidence = online_admission_application_residence($application, $postedStudent);
    if($targetGender !== "" && $applicationGender !== $targetGender){
        return false;
    }
    if($targetResidence !== "" && $applicationResidence !== $targetResidence){
        return false;
    }
    return true;
}
}

if(!function_exists('online_admission_filter_documents_for_application')){
function online_admission_filter_documents_for_application($documents, $application, $postedStudent = null){
    if(!is_array($documents) || empty($documents)){
        return array();
    }
    $filtered = array();
    foreach($documents as $document){
        if(online_admission_document_matches_application($document, $application, $postedStudent)){
            $filtered[] = $document;
        }
    }
    return $filtered;
}
}

if(!function_exists('online_admission_find_matching_prospectus')){
function online_admission_find_matching_prospectus($documents, $application, $postedStudent = null){
    if(!is_array($documents) || empty($documents)){
        return null;
    }
    foreach($documents as $document){
        if(online_admission_document_group($document) !== "prospectus"){
            continue;
        }
        if(online_admission_document_matches_application($document, $application, $postedStudent)){
            return $document;
        }
    }
    return null;
}
}

if(!function_exists('online_admission_get_document')){
function online_admission_get_document($con, $branchId, $admissionYear, $docType){
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $yearEsc = mysqli_real_escape_string($con, trim((string)$admissionYear));
    $docTypeEsc = mysqli_real_escape_string($con, trim((string)$docType));
    $res = mysqli_query($con, "SELECT *
        FROM tblonlineadmissiondocument
        WHERE branchid='$branchIdEsc'
          AND admissionyear='$yearEsc'
          AND doctype='$docTypeEsc'
          AND status='active'
        LIMIT 1");
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        return $row;
    }
    return null;
}
}

if(!function_exists('online_admission_get_document_by_id')){
function online_admission_get_document_by_id($con, $documentId){
    $documentIdEsc = mysqli_real_escape_string($con, trim((string)$documentId));
    if($documentIdEsc === ""){
        return null;
    }
    $res = mysqli_query($con, "SELECT *
        FROM tblonlineadmissiondocument
        WHERE documentid='$documentIdEsc'
          AND status='active'
        LIMIT 1");
    if($res && $row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
        return $row;
    }
    return null;
}
}

if(!function_exists('online_admission_delete_document')){
function online_admission_delete_document($con, $branchId, $documentId, &$errorMessage){
    $errorMessage = "";
    $branchId = trim((string)$branchId);
    $documentId = trim((string)$documentId);
    if($branchId === "" || $documentId === ""){
        $errorMessage = "Select a valid admission document to delete.";
        return false;
    }
    $document = online_admission_get_document_by_id($con, $documentId);
    if(!$document || (string)$document["branchid"] !== $branchId){
        $errorMessage = "That admission document could not be found.";
        return false;
    }

    $documentIdEsc = mysqli_real_escape_string($con, $documentId);
    mysqli_query($con, "DELETE FROM tblonlineadmissiondocumentassignment WHERE documentid='$documentIdEsc'");
    $deleted = mysqli_query($con, "DELETE FROM tblonlineadmissiondocument WHERE documentid='$documentIdEsc' LIMIT 1");
    if(!$deleted){
        $errorMessage = "The admission document could not be deleted right now.";
        return false;
    }

    $filename = trim((string)(isset($document["filename"]) ? $document["filename"] : ""));
    if($filename !== ""){
        online_admission_remove_document_file_if_unused($con, $filename);
    }
    return $document;
}
}

if(!function_exists('online_admission_get_document_assignment')){
function online_admission_get_document_assignment($con, $applicationId, $randomPool){
    $applicationIdEsc = mysqli_real_escape_string($con, trim((string)$applicationId));
    $randomPoolEsc = mysqli_real_escape_string($con, trim((string)$randomPool));
    if($applicationIdEsc === "" || $randomPoolEsc === ""){
        return null;
    }
    $res = mysqli_query($con, "SELECT * FROM tblonlineadmissiondocumentassignment
        WHERE applicationid='$applicationIdEsc'
          AND randompool='$randomPoolEsc'
        LIMIT 1");
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return $row;
    }
    return null;
}
}

if(!function_exists('online_admission_save_document_assignment')){
function online_admission_save_document_assignment($con, $application, $document){
    if(!is_array($application) || empty($application) || !is_array($document) || empty($document)){
        return false;
    }
    $applicationId = trim((string)(isset($application["applicationid"]) ? $application["applicationid"] : ""));
    $documentId = trim((string)(isset($document["documentid"]) ? $document["documentid"] : ""));
    $randomPool = online_admission_document_random_pool($document);
    if($applicationId === "" || $documentId === "" || $randomPool === ""){
        return false;
    }
    $assignmentId = online_admission_generate_id("ADMDOCASN_");
    $applicationIdEsc = mysqli_real_escape_string($con, $applicationId);
    $documentIdEsc = mysqli_real_escape_string($con, $documentId);
    $randomPoolEsc = mysqli_real_escape_string($con, $randomPool);
    $branchIdEsc = mysqli_real_escape_string($con, trim((string)(isset($application["branchid"]) ? $application["branchid"] : "")));
    $admissionYearEsc = mysqli_real_escape_string($con, trim((string)(isset($application["admissionyear"]) ? $application["admissionyear"] : "")));
    $assignmentIdEsc = mysqli_real_escape_string($con, $assignmentId);
    return mysqli_query($con, "INSERT INTO tblonlineadmissiondocumentassignment(
            assignmentid, documentid, applicationid, randompool, admissionyear, branchid, assignedat, updatedat
        ) VALUES(
            '$assignmentIdEsc', '$documentIdEsc', '$applicationIdEsc', '$randomPoolEsc', '$admissionYearEsc', '$branchIdEsc', NOW(), NOW()
        ) ON DUPLICATE KEY UPDATE
            documentid=VALUES(documentid),
            admissionyear=VALUES(admissionyear),
            branchid=VALUES(branchid),
            updatedat=NOW()");
}
}

if(!function_exists('online_admission_document_assigned_to_application')){
function online_admission_document_assigned_to_application($con, $applicationId, $document){
    $randomPool = online_admission_document_random_pool($document);
    $documentId = trim((string)(is_array($document) && isset($document["documentid"]) ? $document["documentid"] : ""));
    if($randomPool === "" || $documentId === ""){
        return false;
    }
    $assignment = online_admission_get_document_assignment($con, $applicationId, $randomPool);
    return $assignment && trim((string)(isset($assignment["documentid"]) ? $assignment["documentid"] : "")) === $documentId;
}
}

if(!function_exists('online_admission_resolve_random_documents_for_application')){
function online_admission_resolve_random_documents_for_application($con, $documents, $application, $postedStudent = null){
    if(!is_array($documents) || empty($documents) || !is_array($application) || empty($application)){
        return is_array($documents) ? $documents : array();
    }
    $eligibleDocuments = online_admission_filter_documents_for_application($documents, $application, $postedStudent);
    if(empty($eligibleDocuments)){
        return array();
    }

    $resolved = array();
    $poolDocuments = array();
    foreach($eligibleDocuments as $document){
        if(online_admission_document_random_enabled($document)){
            $poolKey = online_admission_document_random_pool($document);
            if($poolKey !== ""){
                if(!isset($poolDocuments[$poolKey])){
                    $poolDocuments[$poolKey] = array();
                }
                $poolDocuments[$poolKey][] = $document;
            }
        }
    }

    $resolvedPools = array();
    foreach($eligibleDocuments as $document){
        if(!online_admission_document_random_enabled($document)){
            $resolved[] = $document;
            continue;
        }
        $poolKey = online_admission_document_random_pool($document);
        if($poolKey === "" || isset($resolvedPools[$poolKey])){
            continue;
        }
        $documentsInPool = isset($poolDocuments[$poolKey]) ? $poolDocuments[$poolKey] : array();
        if(empty($documentsInPool)){
            continue;
        }
        $assignedDocument = null;
        $assignment = online_admission_get_document_assignment($con, (string)$application["applicationid"], $poolKey);
        if($assignment){
            $assignedDocumentId = trim((string)(isset($assignment["documentid"]) ? $assignment["documentid"] : ""));
            foreach($documentsInPool as $poolDocument){
                if((string)$poolDocument["documentid"] === $assignedDocumentId){
                    $assignedDocument = $poolDocument;
                    break;
                }
            }
        }
        if(!$assignedDocument){
            $selectedIndex = array_rand($documentsInPool, 1);
            $assignedDocument = $documentsInPool[$selectedIndex];
            online_admission_save_document_assignment($con, $application, $assignedDocument);
        }
        if($assignedDocument){
            $resolved[] = $assignedDocument;
        }
        $resolvedPools[$poolKey] = true;
    }

    return $resolved;
}
}

if(!function_exists('online_admission_list_documents')){
function online_admission_list_documents($con, $branchId, $admissionYear){
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $yearEsc = mysqli_real_escape_string($con, trim((string)$admissionYear));
    $documents = array();
    $res = mysqli_query($con, "SELECT *
        FROM tblonlineadmissiondocument
        WHERE branchid='$branchIdEsc'
          AND admissionyear='$yearEsc'
          AND status='active'
        ORDER BY uploadedat DESC, title ASC");
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $documents[] = $row;
        }
    }
    return $documents;
}
}

if(!function_exists('online_admission_document_display_title')){
function online_admission_document_display_title($document){
    if(!is_array($document) || empty($document)){
        return "Admission Document";
    }
    $title = trim((string)(isset($document["title"]) ? $document["title"] : ""));
    if($title !== ""){
        return $title;
    }
    $originalFilename = trim((string)(isset($document["originalfilename"]) ? $document["originalfilename"] : ""));
    if($originalFilename !== ""){
        return pathinfo($originalFilename, PATHINFO_FILENAME);
    }
    if(online_admission_document_group($document) === "prospectus"){
        return trim("Prospectus ".str_replace(" Students", "", online_admission_document_target_summary($document)));
    }
    return online_admission_document_label(isset($document["doctype"]) ? $document["doctype"] : "");
}
}

if(!function_exists('online_admission_document_file_path')){
function online_admission_document_file_path($filename){
    $filename = basename(trim((string)$filename));
    if($filename === ""){
        return "";
    }
    $path = __DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR."admission-documents".DIRECTORY_SEPARATOR.$filename;
    return is_file($path) ? $path : "";
}
}

if(!function_exists('online_admission_remove_document_file_if_unused')){
function online_admission_remove_document_file_if_unused($con, $filename){
    $filename = basename(trim((string)$filename));
    if($filename === ""){
        return;
    }
    $filenameEsc = mysqli_real_escape_string($con, $filename);
    $res = mysqli_query($con, "SELECT COUNT(*) AS total
        FROM tblonlineadmissiondocument
        WHERE filename='$filenameEsc'");
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) && (int)$row["total"] > 0){
        return;
    }
    $path = online_admission_document_file_path($filename);
    if($path !== ""){
        @unlink($path);
    }
}
}

if(!function_exists('online_admission_document_slug')){
function online_admission_document_slug($value){
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string)$value, '-');
    return $value !== "" ? $value : "document";
}
}

if(!function_exists('online_admission_store_document_file')){
function online_admission_store_document_file($file, &$errorMessage){
    $errorMessage = "";
    if(!isset($file["error"]) || $file["error"] === UPLOAD_ERR_NO_FILE || !isset($file["name"]) || trim((string)$file["name"]) === ""){
        $errorMessage = "Choose a document file to upload.";
        return false;
    }
    if((int)$file["error"] !== UPLOAD_ERR_OK){
        $errorMessage = "The document upload could not be completed right now.";
        return false;
    }
    if(!isset($file["tmp_name"]) || !is_uploaded_file($file["tmp_name"])){
        $errorMessage = "The uploaded document could not be verified.";
        return false;
    }

    $originalName = trim((string)$file["name"]);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if(!in_array($extension, array("pdf", "doc", "docx"), true)){
        $errorMessage = "Only PDF, DOC, and DOCX files are allowed for admission documents.";
        return false;
    }

    $fileSize = isset($file["size"]) ? (int)$file["size"] : 0;
    if($fileSize > (12 * 1024 * 1024)){
        $errorMessage = "Each admission document must be 12MB or less.";
        return false;
    }

    $uploadDir = __DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR."admission-documents";
    if(!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)){
        $errorMessage = "The admission document folder could not be prepared on the server.";
        return false;
    }

    $storedName = "admission-document-".date("YmdHis")."-".substr(md5(uniqid('', true)), 0, 16).".".$extension;
    $destination = $uploadDir.DIRECTORY_SEPARATOR.$storedName;
    if(!move_uploaded_file($file["tmp_name"], $destination)){
        $errorMessage = "The admission document could not be saved on the server.";
        return false;
    }

    $mimeType = "";
    if(function_exists("finfo_open")){
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if($finfo){
            $mimeType = (string)@finfo_file($finfo, $destination);
            @finfo_close($finfo);
        }
    }
    if($mimeType === "" && isset($file["type"])){
        $mimeType = trim((string)$file["type"]);
    }
    if($mimeType === ""){
        $mimeType = $extension === "pdf" ? "application/pdf" : "application/octet-stream";
    }

    return array(
        "filename" => $storedName,
        "originalfilename" => $originalName,
        "mimetype" => $mimeType,
        "filesize" => is_file($destination) ? (int)filesize($destination) : $fileSize
    );
}
}

if(!function_exists('online_admission_save_document')){
function online_admission_save_document($con, $branchId, $admissionYear, $title, $file, $uploadedBy, &$errorMessage, $options = array()){
    $errorMessage = "";
    $admissionYear = trim((string)$admissionYear);
    $title = trim((string)$title);
    $documentGroup = strtolower(trim((string)(isset($options["documentgroup"]) ? $options["documentgroup"] : "general")));
    if(!in_array($documentGroup, array("general", "prospectus"), true)){
        $documentGroup = "general";
    }
    $targetGender = $documentGroup === "prospectus"
        ? house_master_normalize_gender_label(isset($options["targetgender"]) ? $options["targetgender"] : "")
        : "";
    $targetResidence = $documentGroup === "prospectus"
        ? house_master_normalize_residence_label(isset($options["targetresidencetype"]) ? $options["targetresidencetype"] : "")
        : "";
    $randomEnabled = $documentGroup === "general" && !empty($options["randomenabled"]) ? 1 : 0;
    $randomPool = $randomEnabled === 1
        ? trim((string)(isset($options["randompool"]) ? $options["randompool"] : ""))
        : "";
    if($branchId === "" || $admissionYear === "" || $title === ""){
        $errorMessage = "The admission document details are incomplete.";
        return false;
    }
    if($documentGroup === "prospectus" && ($targetGender === "" || $targetResidence === "")){
        $errorMessage = "Choose the gender and residence this prospectus should be assigned to.";
        return false;
    }
    if($randomEnabled === 1){
        $randomPool = online_admission_document_slug($randomPool !== "" ? $randomPool : $title);
        if($randomPool === ""){
            $errorMessage = "Enter a valid random assignment pool name.";
            return false;
        }
    }

    $storedFile = online_admission_store_document_file($file, $errorMessage);
    if($storedFile === false){
        return false;
    }

    $docType = $documentGroup === "prospectus"
        ? "prospectus-".strtolower($targetGender)."-".strtolower($targetResidence)
        : "document-".online_admission_document_slug($title)."-".substr(md5(uniqid('', true)), 0, 8);
    $existingDocument = ($documentGroup === "prospectus")
        ? online_admission_get_document($con, $branchId, $admissionYear, $docType)
        : null;

    if($existingDocument){
        $documentId = (string)$existingDocument["documentid"];
        $oldFilename = trim((string)$existingDocument["filename"]);
        $stmt = mysqli_prepare($con, "UPDATE tblonlineadmissiondocument SET
                title=?, filename=?, originalfilename=?, mimetype=?, filesize=?, documentgroup=?, targetgender=?, targetresidencetype=?, randomenabled=?, randompool=?, status='active', uploadedat=NOW(), updatedat=NOW(), uploadedby=?
            WHERE documentid=?
            LIMIT 1");
        if(!$stmt){
            $errorMessage = "The admission document could not be prepared for updating right now.";
            online_admission_remove_document_file_if_unused($con, $storedFile["filename"]);
            return false;
        }
        mysqli_stmt_bind_param(
            $stmt,
            "ssssisssisss",
            $title,
            $storedFile["filename"],
            $storedFile["originalfilename"],
            $storedFile["mimetype"],
            $storedFile["filesize"],
            $documentGroup,
            $targetGender,
            $targetResidence,
            $randomEnabled,
            $randomPool,
            $uploadedBy,
            $documentId
        );
        $saved = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if(!$saved){
            $errorMessage = "The admission document could not be updated right now.";
            online_admission_remove_document_file_if_unused($con, $storedFile["filename"]);
            return false;
        }
        if($oldFilename !== "" && $oldFilename !== $storedFile["filename"]){
            online_admission_remove_document_file_if_unused($con, $oldFilename);
        }
        return online_admission_get_document_by_id($con, $documentId);
    }

    $documentId = online_admission_generate_id("ADMDOC_");
    $stmt = mysqli_prepare($con, "INSERT INTO tblonlineadmissiondocument(
        documentid, branchid, admissionyear, doctype, documentgroup, targetgender, targetresidencetype, randomenabled, randompool, title, filename, originalfilename, mimetype, filesize, status, uploadedat, updatedat, uploadedby
    ) VALUES(
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW(), ?
    )");
    if(!$stmt){
        $errorMessage = "The admission document could not be prepared for saving right now.";
        online_admission_remove_document_file_if_unused($con, $storedFile["filename"]);
        return false;
    }
    mysqli_stmt_bind_param(
        $stmt,
        "sssssssisssssis",
        $documentId,
        $branchId,
        $admissionYear,
        $docType,
        $documentGroup,
        $targetGender,
        $targetResidence,
        $randomEnabled,
        $randomPool,
        $title,
        $storedFile["filename"],
        $storedFile["originalfilename"],
        $storedFile["mimetype"],
        $storedFile["filesize"],
        $uploadedBy
    );
    $saved = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if(!$saved){
        $errorMessage = "The admission document could not be saved right now.";
        online_admission_remove_document_file_if_unused($con, $storedFile["filename"]);
        return false;
    }

    return online_admission_get_document_by_id($con, $documentId);
}
}

if(!function_exists('online_admission_application_is_submitted')){
function online_admission_application_is_submitted($application){
    if(!is_array($application) || empty($application)){
        return false;
    }
    $status = strtolower(trim((string)(isset($application["status"]) ? $application["status"] : "")));
    if(in_array($status, array("submitted", "reviewed", "needs_attention"), true)){
        return true;
    }
    return trim((string)(isset($application["submittedat"]) ? $application["submittedat"] : "")) !== "";
}
}

if(!function_exists('online_admission_documents_unlocked')){
function online_admission_documents_unlocked($application, $successfulPayment, $paymentEnabled){
    if(!online_admission_application_is_submitted($application)){
        return false;
    }
    if((int)$paymentEnabled === 1){
        return online_admission_payment_is_paid($successfulPayment);
    }
    return true;
}
}

if(!function_exists('online_admission_year_totals')){
function online_admission_year_totals($con, $branchId, $admissionYear){
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $yearEsc = mysqli_real_escape_string($con, trim((string)$admissionYear));
    $totals = array(
        "admissionyear" => trim((string)$admissionYear),
        "posted_total" => 0,
        "posted_active" => 0,
        "posted_archived" => 0,
        "application_total" => 0,
        "draft_total" => 0,
        "submitted_total" => 0,
        "reviewed_total" => 0,
        "payment_total" => 0,
        "payment_success_total" => 0,
        "help_total" => 0
    );

    $postedRes = mysqli_query($con, "SELECT
        COUNT(*) AS posted_total,
        SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS posted_active,
        SUM(CASE WHEN status='archived' THEN 1 ELSE 0 END) AS posted_archived
        FROM tbladmissionpostedstudent
        WHERE branchid='$branchIdEsc'
          AND admissionyear='$yearEsc'");
    if($postedRes && ($row = mysqli_fetch_array($postedRes, MYSQLI_ASSOC))){
        $totals["posted_total"] = (int)$row["posted_total"];
        $totals["posted_active"] = (int)$row["posted_active"];
        $totals["posted_archived"] = (int)$row["posted_archived"];
    }

    $appRes = mysqli_query($con, "SELECT
        COUNT(*) AS application_total,
        SUM(CASE WHEN status='draft' THEN 1 ELSE 0 END) AS draft_total,
        SUM(CASE WHEN status='submitted' THEN 1 ELSE 0 END) AS submitted_total,
        SUM(CASE WHEN status='reviewed' THEN 1 ELSE 0 END) AS reviewed_total
        FROM tblonlineadmissionapplication
        WHERE branchid='$branchIdEsc'
          AND admissionyear='$yearEsc'");
    if($appRes && ($row = mysqli_fetch_array($appRes, MYSQLI_ASSOC))){
        $totals["application_total"] = (int)$row["application_total"];
        $totals["draft_total"] = (int)$row["draft_total"];
        $totals["submitted_total"] = (int)$row["submitted_total"];
        $totals["reviewed_total"] = (int)$row["reviewed_total"];
    }

    $paymentRes = mysqli_query($con, "SELECT
        COUNT(*) AS payment_total,
        SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) AS payment_success_total
        FROM tblonlineadmissionpayment
        WHERE branchid='$branchIdEsc'
          AND admissionyear='$yearEsc'");
    if($paymentRes && ($row = mysqli_fetch_array($paymentRes, MYSQLI_ASSOC))){
        $totals["payment_total"] = (int)$row["payment_total"];
        $totals["payment_success_total"] = (int)$row["payment_success_total"];
    }

    $helpRes = mysqli_query($con, "SELECT COUNT(*) AS help_total
        FROM tblonlineadmissionhelprequest
        WHERE branchid='$branchIdEsc'
          AND admissionyear='$yearEsc'");
    if($helpRes && ($row = mysqli_fetch_array($helpRes, MYSQLI_ASSOC))){
        $totals["help_total"] = (int)$row["help_total"];
    }

    return $totals;
}
}

if(!function_exists('online_admission_list_year_summaries')){
function online_admission_list_year_summaries($con, $branchId){
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $years = array();
    $sql = "SELECT admissionyear FROM tbladmissionpostedstudent WHERE branchid='$branchIdEsc'
            UNION
            SELECT admissionyear FROM tblonlineadmissionapplication WHERE branchid='$branchIdEsc'
            UNION
            SELECT admissionyear FROM tblonlineadmissionpayment WHERE branchid='$branchIdEsc'
            UNION
            SELECT admissionyear FROM tblonlineadmissionhelprequest WHERE branchid='$branchIdEsc' AND admissionyear IS NOT NULL AND admissionyear<>''
            ORDER BY admissionyear DESC";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $year = trim((string)$row["admissionyear"]);
            if($year !== ""){
                $years[] = $year;
            }
        }
    }

    $summaries = array();
    foreach($years as $year){
        $summaries[] = online_admission_year_totals($con, $branchId, $year);
    }

    return $summaries;
}
}

if(!function_exists('online_admission_clear_year')){
function online_admission_clear_year($con, $branchId, $admissionYear, $backupReference, $clearedBy){
    $branchId = trim((string)$branchId);
    $admissionYear = trim((string)$admissionYear);
    $backupReference = trim((string)$backupReference);
    $clearedBy = trim((string)$clearedBy);

    if($branchId === "" || $admissionYear === ""){
        return array("success" => false, "message" => "Select a valid admission year to clear.");
    }
    if($backupReference === ""){
        return array("success" => false, "message" => "Enter a backup reference before clearing this admission year.");
    }

    $setting = online_admission_get_payment_setting($con, $branchId);
    if(online_admission_portal_is_open($setting) || (int)$setting["enabled"] === 1){
        return array("success" => false, "message" => "Close the public portal and disable online payment before clearing an admission year.");
    }

    $totals = online_admission_year_totals($con, $branchId, $admissionYear);
    if($totals["posted_total"] === 0 && $totals["application_total"] === 0 && $totals["payment_total"] === 0 && $totals["help_total"] === 0){
        return array("success" => false, "message" => "No online admission records were found for ".$admissionYear.".");
    }

    $imageBackup = online_admission_backup_year_images($con, $branchId, $admissionYear, $backupReference);
    if(!$imageBackup["success"]){
        return array("success" => false, "message" => $imageBackup["message"]);
    }

    mysqli_autocommit($con, false);
    $ok = true;
    $branchIdEsc = mysqli_real_escape_string($con, $branchId);
    $yearEsc = mysqli_real_escape_string($con, $admissionYear);

    $ok = $ok && mysqli_query($con, "DELETE FROM tblonlineadmissionhelprequest
        WHERE branchid='$branchIdEsc'
          AND admissionyear='$yearEsc'");
    $ok = $ok && mysqli_query($con, "DELETE FROM tblonlineadmissionpayment
        WHERE branchid='$branchIdEsc'
          AND admissionyear='$yearEsc'");
    $ok = $ok && mysqli_query($con, "DELETE FROM tblonlineadmissionapplication
        WHERE branchid='$branchIdEsc'
          AND admissionyear='$yearEsc'");
    $ok = $ok && mysqli_query($con, "DELETE FROM tbladmissionpostedstudent
        WHERE branchid='$branchIdEsc'
          AND admissionyear='$yearEsc'");

    if($ok){
        mysqli_commit($con);
        mysqli_autocommit($con, true);
        online_admission_remove_year_images_from_uploads($con, $imageBackup["files"]);
        return array(
            "success" => true,
            "message" => "Admission year ".$admissionYear." cleared successfully. Student images were saved to ".$imageBackup["relative_dir"].".",
            "totals" => $totals,
            "image_backup_dir" => $imageBackup["relative_dir"],
            "image_total" => $imageBackup["saved_count"],
            "cleared_by" => $clearedBy
        );
    }

    mysqli_rollback($con);
    mysqli_autocommit($con, true);
    return array("success" => false, "message" => "The admission year could not be cleared right now.");
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
    if($storedStatus === "success" && $application){
        $application = online_admission_ensure_application_token($con, $application);
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
    $filename = basename(trim((string)$filename));
    if($filename !== "" && file_exists(__DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR.$filename)){
        return "online-admission-photo.php?file=".rawurlencode($filename);
    }
    return "online-admission-photo.php";
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

if(!function_exists('online_admission_backup_slug')){
function online_admission_backup_slug($value){
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string)$value, '-');
    return $value !== "" ? $value : "backup";
}
}

if(!function_exists('online_admission_backup_image_student_name')){
function online_admission_backup_image_student_name($row){
    return trim((string)(isset($row["firstname"]) ? $row["firstname"] : "")." ".(string)(isset($row["othernames"]) ? $row["othernames"] : "")." ".(string)(isset($row["surname"]) ? $row["surname"] : ""));
}
}

if(!function_exists('online_admission_backup_image_copy_name')){
function online_admission_backup_image_copy_name($row){
    $studentLabel = online_admission_backup_image_student_name($row);
    $safeStudentLabel = preg_replace('/[^A-Za-z0-9._-]+/', '-', $studentLabel);
    $safeStudentLabel = trim((string)$safeStudentLabel, "-");
    if($safeStudentLabel === ""){
        $safeStudentLabel = "student";
    }
    $originalFilename = trim((string)(isset($row["filename"]) ? $row["filename"] : ""));
    $safeFile = preg_replace('/[^A-Za-z0-9._-]+/', '-', $originalFilename);
    $beceIndex = trim((string)(isset($row["beceindexnumber"]) ? $row["beceindexnumber"] : ""));
    if($beceIndex === ""){
        return $safeStudentLabel."-".$safeFile;
    }
    return $beceIndex."-".$safeStudentLabel."-".$safeFile;
}
}

if(!function_exists('online_admission_backup_year_images')){
function online_admission_backup_year_images($con, $branchId, $admissionYear, $backupReference){
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $yearEsc = mysqli_real_escape_string($con, trim((string)$admissionYear));
    $result = array(
        "success" => false,
        "message" => "",
        "directory" => "",
        "relative_dir" => "",
        "saved_count" => 0,
        "files" => array()
    );

    $files = array();
    $res = mysqli_query($con, "SELECT applicationid, beceindexnumber, firstname, surname, othernames, filename
        FROM tblonlineadmissionapplication
        WHERE branchid='$branchIdEsc'
          AND admissionyear='$yearEsc'
          AND filename IS NOT NULL
          AND filename<>''");
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $files[] = $row;
        }
    }

    $baseDir = __DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR."admission-year-backups";
    $folderName = online_admission_backup_slug((string)$admissionYear)."-".date("YmdHis")."-".online_admission_backup_slug($backupReference);
    $backupDir = $baseDir.DIRECTORY_SEPARATOR.$folderName;
    $imageDir = $backupDir.DIRECTORY_SEPARATOR."images";

    if(!is_dir(__DIR__.DIRECTORY_SEPARATOR."uploads")){
        $result["message"] = "The uploads folder is missing on the server.";
        return $result;
    }

    if(!is_dir($imageDir) && !mkdir($imageDir, 0777, true)){
        $result["message"] = "The backup folder for admission images could not be created.";
        return $result;
    }

    $manifestRows = array();
    foreach($files as $row){
        $filename = trim((string)$row["filename"]);
        if($filename === ""){
            continue;
        }
        $source = __DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR.$filename;
        if(!file_exists($source)){
            continue;
        }

        $studentLabel = online_admission_backup_image_student_name($row);
        $copyName = online_admission_backup_image_copy_name($row);
        $destination = $imageDir.DIRECTORY_SEPARATOR.$copyName;
        if(!@copy($source, $destination)){
            $result["message"] = "One or more student images could not be saved before clearing the admission year.";
            return $result;
        }

        $result["saved_count"]++;
        $result["files"][] = $filename;
        $manifestRows[] = array(
            "applicationid" => (string)$row["applicationid"],
            "beceindexnumber" => (string)$row["beceindexnumber"],
            "studentname" => $studentLabel,
            "originalfile" => $filename,
            "savedfile" => $copyName
        );
    }

    $manifestPath = $backupDir.DIRECTORY_SEPARATOR."image-manifest.csv";
    $manifestHandle = fopen($manifestPath, "w");
    if($manifestHandle){
        fputcsv($manifestHandle, array("applicationid", "beceindexnumber", "studentname", "originalfile", "savedfile"));
        foreach($manifestRows as $manifestRow){
            fputcsv($manifestHandle, $manifestRow);
        }
        fclose($manifestHandle);
    }

    $summaryPath = $backupDir.DIRECTORY_SEPARATOR."backup-summary.txt";
    $summaryText = "Admission Year: ".$admissionYear.PHP_EOL
        ."Backup Reference: ".$backupReference.PHP_EOL
        ."Created At: ".date("Y-m-d H:i:s").PHP_EOL
        ."Saved Images: ".$result["saved_count"].PHP_EOL;
    @file_put_contents($summaryPath, $summaryText);

    $result["success"] = true;
    $result["directory"] = $backupDir;
    $result["relative_dir"] = "uploads/admission-year-backups/".$folderName;
    return $result;
}
}

if(!function_exists('online_admission_remove_year_images_from_uploads')){
function online_admission_remove_year_images_from_uploads($con, $filenames){
    $uniqueFiles = array_values(array_unique(array_filter(array_map('trim', (array)$filenames))));
    foreach($uniqueFiles as $filename){
        $filenameEsc = mysqli_real_escape_string($con, (string)$filename);
        $res = mysqli_query($con, "SELECT COUNT(*) AS total
            FROM tblonlineadmissionapplication
            WHERE filename='$filenameEsc'");
        $stillUsed = false;
        if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
            $stillUsed = (int)$row["total"] > 0;
        }
        if(!$stillUsed){
            $path = __DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR.$filename;
            if(is_file($path)){
                @unlink($path);
            }
        }
    }
}
}
