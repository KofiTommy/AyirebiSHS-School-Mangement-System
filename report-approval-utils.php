<?php
if(!function_exists('xschool_schema_cache_is_fresh')){
function xschool_schema_cache_is_fresh($key, $ttlSeconds = 900){
    $key = trim((string)$key);
    if($key === ''){
        return false;
    }
    $ttlSeconds = (int)$ttlSeconds;
    if($ttlSeconds <= 0){
        $ttlSeconds = 900;
    }
    $cacheBag = isset($_SESSION['_xschool_schema_cache']) && is_array($_SESSION['_xschool_schema_cache'])
        ? $_SESSION['_xschool_schema_cache']
        : array();
    if(!isset($cacheBag[$key])){
        return false;
    }
    return ((time() - (int)$cacheBag[$key]) < $ttlSeconds);
}
}

if(!function_exists('xschool_schema_cache_mark')){
function xschool_schema_cache_mark($key){
    $key = trim((string)$key);
    if($key === ''){
        return;
    }
    if(!isset($_SESSION['_xschool_schema_cache']) || !is_array($_SESSION['_xschool_schema_cache'])){
        $_SESSION['_xschool_schema_cache'] = array();
    }
    $_SESSION['_xschool_schema_cache'][$key] = time();
}
}

if(!function_exists('report_approval_normalize_year')){
function report_approval_normalize_year($academicYear){
    $academicYear = trim((string)$academicYear);
    if($academicYear === ''){
        return '';
    }
    if(is_numeric($academicYear)){
        return (string)((int)$academicYear);
    }
    return $academicYear;
}
}

if(!function_exists('report_approval_scope_requires_release')){
function report_approval_scope_requires_release($academicYear, $termName){
    $year = (int)report_approval_normalize_year($academicYear);
    $term = (int)trim((string)$termName);
    if($year <= 0 || $term <= 0){
        return false;
    }
    if($year > 2026){
        return true;
    }
    return ($year === 2026 && $term >= 2);
}
}

if(!function_exists('report_approval_is_admin_user')){
function report_approval_is_admin_user(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE'])
        && $_SESSION['ACCESSLEVEL'] === 'administrator'
        && in_array($_SESSION['SYSTEMTYPE'], array('normal_user', 'super_user'), true);
}
}

if(!function_exists('report_approval_is_student_user')){
function report_approval_is_student_user(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE'])
        && $_SESSION['ACCESSLEVEL'] === 'user'
        && $_SESSION['SYSTEMTYPE'] === 'Student';
}
}

if(!function_exists('report_approval_is_headmaster_user')){
function report_approval_is_headmaster_user(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE'])
        && $_SESSION['ACCESSLEVEL'] === 'user'
        && $_SESSION['SYSTEMTYPE'] === 'Headmaster';
}
}

if(!function_exists('report_approval_scope_cache_key')){
function report_approval_scope_cache_key($batchId, $academicYear, $termName, $classId){
    return trim((string)$batchId).'|'.report_approval_normalize_year($academicYear).'|'.(int)trim((string)$termName).'|'.trim((string)$classId);
}
}

if(!function_exists('report_approval_scope_cache_forget')){
function report_approval_scope_cache_forget($batchId, $academicYear, $termName, $classId){
    $cacheKey = report_approval_scope_cache_key($batchId, $academicYear, $termName, $classId);
    if(isset($GLOBALS['_report_approval_scope_meta_cache'][$cacheKey])){
        unset($GLOBALS['_report_approval_scope_meta_cache'][$cacheKey]);
    }
}
}

if(!function_exists('report_approval_column_exists')){
function report_approval_column_exists($con, $tableName, $columnName){
    if(!$con){
        return false;
    }
    $tableSafe = mysqli_real_escape_string($con, trim((string)$tableName));
    $columnSafe = mysqli_real_escape_string($con, trim((string)$columnName));
    $sql = "SHOW COLUMNS FROM `".$tableSafe."` LIKE '".$columnSafe."'";
    $result = mysqli_query($con, $sql);
    return ($result && mysqli_num_rows($result) > 0);
}
}

if(!function_exists('report_approval_ensure_table')){
function report_approval_ensure_table($con){
    if(!$con){
        return;
    }
    if(function_exists('xschool_schema_cache_is_fresh') && xschool_schema_cache_is_fresh('schema_tblclassreportapproval_v3')){
        return;
    }
    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblclassreportapproval (
        approvalid BIGINT NOT NULL AUTO_INCREMENT,
        batchid VARCHAR(100) NOT NULL,
        academicyear VARCHAR(10) NOT NULL DEFAULT '',
        termname INT NOT NULL DEFAULT 0,
        classid VARCHAR(100) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        approvedby VARCHAR(100) NOT NULL DEFAULT '',
        approveddatetime DATETIME NULL,
        headapprovalstatus VARCHAR(20) NOT NULL DEFAULT '',
        headapprovalnote VARCHAR(255) NOT NULL DEFAULT '',
        headapprovedby VARCHAR(100) NOT NULL DEFAULT '',
        headapprovedname VARCHAR(150) NOT NULL DEFAULT '',
        headapproveddatetime DATETIME NULL,
        headsignaturefile VARCHAR(255) NOT NULL DEFAULT '',
        datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updateddatetime DATETIME NULL,
        PRIMARY KEY (approvalid),
        UNIQUE KEY uq_report_scope (batchid, academicyear, termname, classid),
        KEY idx_report_scope_status (batchid, academicyear, termname, classid, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if(!report_approval_column_exists($con, 'tblclassreportapproval', 'headapprovalstatus')){
        @mysqli_query($con, "ALTER TABLE tblclassreportapproval ADD COLUMN headapprovalstatus VARCHAR(20) NOT NULL DEFAULT '' AFTER approveddatetime");
    }
    if(!report_approval_column_exists($con, 'tblclassreportapproval', 'headapprovalnote')){
        @mysqli_query($con, "ALTER TABLE tblclassreportapproval ADD COLUMN headapprovalnote VARCHAR(255) NOT NULL DEFAULT '' AFTER headapprovalstatus");
    }
    if(!report_approval_column_exists($con, 'tblclassreportapproval', 'headapprovedby')){
        @mysqli_query($con, "ALTER TABLE tblclassreportapproval ADD COLUMN headapprovedby VARCHAR(100) NOT NULL DEFAULT '' AFTER headapprovalnote");
    }
    if(!report_approval_column_exists($con, 'tblclassreportapproval', 'headapprovedname')){
        @mysqli_query($con, "ALTER TABLE tblclassreportapproval ADD COLUMN headapprovedname VARCHAR(150) NOT NULL DEFAULT '' AFTER headapprovedby");
    }
    if(!report_approval_column_exists($con, 'tblclassreportapproval', 'headapproveddatetime')){
        @mysqli_query($con, "ALTER TABLE tblclassreportapproval ADD COLUMN headapproveddatetime DATETIME NULL AFTER headapprovedname");
    }
    if(!report_approval_column_exists($con, 'tblclassreportapproval', 'headsignaturefile')){
        @mysqli_query($con, "ALTER TABLE tblclassreportapproval ADD COLUMN headsignaturefile VARCHAR(255) NOT NULL DEFAULT '' AFTER headapproveddatetime");
    }
    if(!report_approval_column_exists($con, 'tblclassreportapproval', 'scoreeditoverride')){
        @mysqli_query($con, "ALTER TABLE tblclassreportapproval ADD COLUMN scoreeditoverride TINYINT(1) NOT NULL DEFAULT 0 AFTER headsignaturefile");
    }
    if(!report_approval_column_exists($con, 'tblclassreportapproval', 'scoreeditoverrideby')){
        @mysqli_query($con, "ALTER TABLE tblclassreportapproval ADD COLUMN scoreeditoverrideby VARCHAR(100) NOT NULL DEFAULT '' AFTER scoreeditoverride");
    }
    if(!report_approval_column_exists($con, 'tblclassreportapproval', 'scoreeditoverridedatetime')){
        @mysqli_query($con, "ALTER TABLE tblclassreportapproval ADD COLUMN scoreeditoverridedatetime DATETIME NULL AFTER scoreeditoverrideby");
    }
    if(function_exists('xschool_schema_cache_mark')){
        xschool_schema_cache_mark('schema_tblclassreportapproval_v3');
    }
}
}

if(!function_exists('report_approval_scope_headmaster_reference')){
function report_approval_scope_headmaster_reference($batchId, $academicYear, $termName, $classId, $approvedBy = '', $approvedDatetime = ''){
    $batchId = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim((string)$batchId)));
    $classId = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim((string)$classId)));
    $termName = (int)trim((string)$termName);
    $approvedBy = trim((string)$approvedBy);
    $approvedDatetime = trim((string)$approvedDatetime);
    $stamp = strtotime($approvedDatetime);
    $timePart = $stamp ? date('YmdHi', $stamp) : date('YmdHi');
    $tail = $classId !== '' ? substr($classId, -4) : 'RPT';
    $seed = $batchId.'|'.$academicYear.'|'.$termName.'|'.$classId.'|'.$approvedBy.'|'.$approvedDatetime;
    return 'RPT-HM-'.$timePart.'-'.$tail.'-'.strtoupper(substr(sha1($seed), 0, 8));
}
}

if(!function_exists('report_approval_signature_file_name')){
function report_approval_signature_file_name($value = ''){
    $value = trim((string)$value);
    if($value !== ''){
        return $value;
    }
    return 'heads-signature.png';
}
}

if(!function_exists('report_approval_scope_meta')){
function report_approval_scope_meta($con, $batchId, $academicYear, $termName, $classId){
    $batchId = trim((string)$batchId);
    $academicYear = report_approval_normalize_year($academicYear);
    $termName = (int)trim((string)$termName);
    $classId = trim((string)$classId);
    $cacheKey = report_approval_scope_cache_key($batchId, $academicYear, $termName, $classId);
    if(isset($GLOBALS['_report_approval_scope_meta_cache'][$cacheKey])){
        return $GLOBALS['_report_approval_scope_meta_cache'][$cacheKey];
    }

    $required = report_approval_scope_requires_release($academicYear, $termName);
    $meta = array(
        'required' => $required,
        'approved' => false,
        'allowed' => !$required,
        'status' => $required ? 'pending' : 'not_required',
        'status_label' => $required ? 'Awaiting Admin Approval' : 'No Approval Needed',
        'approvedby' => '',
        'approveddatetime' => '',
        'admin_approved' => false,
        'admin_status' => $required ? 'pending' : 'not_required',
        'headmaster_required' => $required,
        'headmaster_approved' => false,
        'headapprovalstatus' => $required ? 'pending_admin' : 'not_required',
        'headapprovalstatus_label' => $required ? 'Awaiting Admin Approval First' : 'No Headmaster Signature Needed',
        'headapprovalnote' => '',
        'headapprovedby' => '',
        'headapprovedname' => '',
        'headapproveddatetime' => '',
        'headsignaturefile' => '',
        'headapproval_reference' => '',
        'score_edit_locked' => false,
        'score_edit_allowed' => true,
        'score_edit_override_enabled' => false,
        'score_edit_status' => 'open',
        'score_edit_status_label' => $required ? 'Open Until Admin Approval' : 'Open for Score Entry',
        'score_edit_override_by' => '',
        'score_edit_override_datetime' => ''
    );

    if(!$required || !$con || $batchId === '' || $academicYear === '' || $termName <= 0 || $classId === ''){
        $GLOBALS['_report_approval_scope_meta_cache'][$cacheKey] = $meta;
        return $meta;
    }

    report_approval_ensure_table($con);
    $batchIdEsc = mysqli_real_escape_string($con, $batchId);
    $academicYearEsc = mysqli_real_escape_string($con, $academicYear);
    $classIdEsc = mysqli_real_escape_string($con, $classId);
    $sql = "SELECT
                status,
                approvedby,
                approveddatetime,
                COALESCE(headapprovalstatus, '') AS headapprovalstatus,
                COALESCE(headapprovalnote, '') AS headapprovalnote,
                COALESCE(headapprovedby, '') AS headapprovedby,
                COALESCE(headapprovedname, '') AS headapprovedname,
                headapproveddatetime,
                COALESCE(headsignaturefile, '') AS headsignaturefile,
                COALESCE(scoreeditoverride, 0) AS scoreeditoverride,
                COALESCE(scoreeditoverrideby, '') AS scoreeditoverrideby,
                scoreeditoverridedatetime
            FROM tblclassreportapproval
            WHERE batchid='$batchIdEsc'
              AND academicyear='$academicYearEsc'
              AND termname='$termName'
              AND classid='$classIdEsc'
            LIMIT 1";
    $res = mysqli_query($con, $sql);
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        $status = strtolower(trim((string)$row['status']));
        $headStatus = strtolower(trim((string)$row['headapprovalstatus']));
        $approvedAt = trim((string)$row['approveddatetime']);
        $headApprovedAt = trim((string)$row['headapproveddatetime']);
        $headApproved = ($status === 'approved'
            && $headStatus === 'approved'
            && $headApprovedAt !== ''
            && $headApprovedAt !== '0000-00-00 00:00:00');
        $overrideEnabled = ((int)$row['scoreeditoverride'] === 1);

        $meta['approvedby'] = trim((string)$row['approvedby']);
        $meta['approveddatetime'] = $approvedAt;
        $meta['admin_status'] = $status !== '' ? $status : 'pending';
        $meta['admin_approved'] = ($status === 'approved');
        $meta['headapprovalnote'] = trim((string)$row['headapprovalnote']);
        $meta['headapprovedby'] = trim((string)$row['headapprovedby']);
        $meta['headapprovedname'] = trim((string)$row['headapprovedname']);
        $meta['headapproveddatetime'] = $headApprovedAt;
        $meta['headsignaturefile'] = report_approval_signature_file_name($row['headsignaturefile']);
        $meta['score_edit_override_by'] = trim((string)$row['scoreeditoverrideby']);
        $meta['score_edit_override_datetime'] = trim((string)$row['scoreeditoverridedatetime']);

        if($status === 'approved'){
            $meta['headapprovalstatus'] = $headApproved ? 'approved' : 'pending';
            $meta['headapprovalstatus_label'] = $headApproved ? 'Digitally Signed By Headmaster' : 'Waiting For Headmaster Signature';
            $meta['score_edit_locked'] = !$overrideEnabled;
            $meta['score_edit_allowed'] = $overrideEnabled ? true : false;
            $meta['score_edit_override_enabled'] = $overrideEnabled;
            $meta['score_edit_status'] = $overrideEnabled ? 'temporary_override' : 'locked_after_admin_approval';
            $meta['score_edit_status_label'] = $overrideEnabled ? 'Temporary Correction Window' : 'Locked Pending Final Signature';

            if($headApproved){
                $meta['approved'] = true;
                $meta['allowed'] = true;
                $meta['status'] = 'approved';
                $meta['status_label'] = 'Approved For Students';
                $meta['headmaster_approved'] = true;
                $meta['headapproval_reference'] = report_approval_scope_headmaster_reference(
                    $batchId,
                    $academicYear,
                    $termName,
                    $classId,
                    $meta['headapprovedby'],
                    $meta['headapproveddatetime']
                );
                if(!$overrideEnabled){
                    $meta['score_edit_status'] = 'locked_after_headmaster_signature';
                    $meta['score_edit_status_label'] = 'Locked After Final Signature';
                }
            }else{
                $meta['status'] = 'awaiting_headmaster';
                $meta['status_label'] = 'Awaiting Headmaster Signature';
            }
        }else{
            $meta['status'] = 'pending';
            $meta['status_label'] = 'Awaiting Admin Approval';
            $meta['headapprovalstatus'] = 'pending_admin';
            $meta['headapprovalstatus_label'] = 'Awaiting Admin Approval First';
            $meta['score_edit_status'] = 'open';
            $meta['score_edit_status_label'] = 'Open Until Admin Approval';
        }
    }

    $GLOBALS['_report_approval_scope_meta_cache'][$cacheKey] = $meta;
    return $meta;
}
}

if(!function_exists('report_approval_set_scope_status')){
function report_approval_set_scope_status($con, $batchId, $academicYear, $termName, $classId, $status, $approvedBy){
    if(!$con){
        return false;
    }
    $batchId = trim((string)$batchId);
    $academicYear = report_approval_normalize_year($academicYear);
    $termName = (int)trim((string)$termName);
    $classId = trim((string)$classId);
    $status = strtolower(trim((string)$status)) === 'approved' ? 'approved' : 'pending';
    $approvedBy = trim((string)$approvedBy);

    if($status === 'approved' && file_exists(__DIR__.DIRECTORY_SEPARATOR.'department-result-workflow-utils.php')){
        include_once(__DIR__.DIRECTORY_SEPARATOR.'department-result-workflow-utils.php');
        $departmentWorkflow = drw_scope_ready_for_admin_release($con, $batchId, $academicYear, $termName, $classId);
        if(!empty($departmentWorkflow['required']) && empty($departmentWorkflow['ready'])){
            return false;
        }
    }

    if($batchId === '' || $academicYear === '' || $termName <= 0 || $classId === ''){
        return false;
    }

    report_approval_ensure_table($con);
    $batchIdEsc = mysqli_real_escape_string($con, $batchId);
    $academicYearEsc = mysqli_real_escape_string($con, $academicYear);
    $classIdEsc = mysqli_real_escape_string($con, $classId);
    $statusEsc = mysqli_real_escape_string($con, $status);
    $approvedByToStore = ($status === 'approved') ? $approvedBy : '';
    $approvedByEsc = mysqli_real_escape_string($con, $approvedByToStore);
    $approvalTimeSql = ($status === 'approved') ? 'NOW()' : 'NULL';
    $headApprovalStatusEsc = mysqli_real_escape_string($con, $status === 'approved' ? 'pending' : '');
    $headApprovalNoteEsc = mysqli_real_escape_string($con, $status === 'approved' ? 'Awaiting headmaster digital signature.' : '');
    $result = @mysqli_query($con, "INSERT INTO tblclassreportapproval(batchid, academicyear, termname, classid, status, approvedby, approveddatetime, datetimeentry, updateddatetime)
        VALUES('$batchIdEsc', '$academicYearEsc', '$termName', '$classIdEsc', '$statusEsc', '$approvedByEsc', $approvalTimeSql, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            status=VALUES(status),
            approvedby=VALUES(approvedby),
            approveddatetime=$approvalTimeSql,
            headapprovalstatus='$headApprovalStatusEsc',
            headapprovalnote='$headApprovalNoteEsc',
            headapprovedby='',
            headapprovedname='',
            headapproveddatetime=NULL,
            headsignaturefile='',
            scoreeditoverride=0,
            scoreeditoverrideby='',
            scoreeditoverridedatetime=NULL,
            updateddatetime=NOW()");
    if($result){
        report_approval_scope_cache_forget($batchId, $academicYear, $termName, $classId);
    }
    return (bool)$result;
}
}

if(!function_exists('report_approval_set_headmaster_status')){
if(!function_exists('report_approval_notify_students_of_release')){
function report_approval_notify_students_of_release($con, $batchId, $academicYear, $termName, $classId, $senderId = ''){
    if(!$con){ return false; }
    $scopeValue=trim((string)$classId).'|'.trim((string)$batchId).'|'.(int)$termName;
    if(trim((string)$classId)===''||trim((string)$batchId)===''||(int)$termName<1){ return false; }
    $classEsc=mysqli_real_escape_string($con,trim((string)$classId));
    $batchEsc=mysqli_real_escape_string($con,trim((string)$batchId));
    $className='your class'; $batchName='';
    $r=mysqli_query($con,"SELECT ce.class_name,b.batch FROM tblclassentry ce LEFT JOIN tblbatch b ON b.batchid='$batchEsc' WHERE ce.class_entryid='$classEsc' LIMIT 1");
    if($r&&($row=mysqli_fetch_assoc($r))){ $className=trim((string)$row['class_name'])!==''?trim((string)$row['class_name']):$className; $batchName=trim((string)$row['batch']); }
    $message='Your '.($className!==''?$className.' ':'').'Semester '.(int)$termName.' result for '.trim((string)$academicYear).($batchName!==''?' ('.$batchName.')':'').' has been released. Open Terminal Report to view it.';
    $id=mysqli_real_escape_string($con,'MSG_'.strtoupper(substr(sha1(uniqid('',true)),0,18)));
    $messageEsc=mysqli_real_escape_string($con,$message); $scopeEsc=mysqli_real_escape_string($con,$scopeValue); $senderEsc=mysqli_real_escape_string($con,trim((string)$senderId));
    return (bool)@mysqli_query($con,"INSERT INTO tblmessages(messageid,messages,datetimeentry,status,sentby,recipient_group,recipient_type,recipient_value,recipient_label) VALUES('$id','$messageEsc',NOW(),'active','$senderEsc','students','class_scope','$scopeEsc','Released result notification')");
}
}
function report_approval_set_headmaster_status($con, $batchId, $academicYear, $termName, $classId, $status, $approvedBy, $approvedName, $approvalNote = '', $signatureFile = 'heads-signature.png'){
    if(!$con){
        return false;
    }
    $batchId = trim((string)$batchId);
    $academicYear = report_approval_normalize_year($academicYear);
    $termName = (int)trim((string)$termName);
    $classId = trim((string)$classId);
    $status = strtolower(trim((string)$status)) === 'approved' ? 'approved' : 'pending';
    $approvedBy = trim((string)$approvedBy);
    $approvedName = trim((string)$approvedName);
    if($approvedName === ''){
        $approvedName = 'Headmaster';
    }
    $approvalNote = trim((string)$approvalNote);
    $signatureFile = report_approval_signature_file_name($signatureFile);

    if($batchId === '' || $academicYear === '' || $termName <= 0 || $classId === ''){
        return false;
    }

    $currentMeta = report_approval_scope_meta($con, $batchId, $academicYear, $termName, $classId);
    if(!$currentMeta['required'] || empty($currentMeta['admin_approved'])){
        return false;
    }

    report_approval_ensure_table($con);
    $batchIdEsc = mysqli_real_escape_string($con, $batchId);
    $academicYearEsc = mysqli_real_escape_string($con, $academicYear);
    $classIdEsc = mysqli_real_escape_string($con, $classId);
    $statusEsc = mysqli_real_escape_string($con, $status);
    $approvedByEsc = mysqli_real_escape_string($con, $approvedBy);
    $approvedNameEsc = mysqli_real_escape_string($con, $approvedName);
    $approvalNoteEsc = mysqli_real_escape_string($con, $approvalNote !== '' ? $approvalNote : ($status === 'approved' ? 'Digitally signed by the headmaster.' : ''));
    $signatureFileEsc = mysqli_real_escape_string($con, $status === 'approved' ? $signatureFile : '');
    $approvedAtSql = ($status === 'approved') ? 'NOW()' : 'NULL';
    $approvedBySql = ($status === 'approved') ? "'$approvedByEsc'" : "''";
    $approvedNameSql = ($status === 'approved') ? "'$approvedNameEsc'" : "''";

    $result = @mysqli_query($con, "UPDATE tblclassreportapproval
        SET headapprovalstatus='$statusEsc',
            headapprovalnote='$approvalNoteEsc',
            headapprovedby=$approvedBySql,
            headapprovedname=$approvedNameSql,
            headapproveddatetime=$approvedAtSql,
            headsignaturefile='$signatureFileEsc',
            updateddatetime=NOW()
        WHERE batchid='$batchIdEsc'
          AND academicyear='$academicYearEsc'
          AND termname='$termName'
          AND classid='$classIdEsc'
          AND status='approved'
        LIMIT 1");
    if($result){
        report_approval_scope_cache_forget($batchId, $academicYear, $termName, $classId);
    }
    if($result && $status === 'approved'){
        report_approval_notify_students_of_release($con, $batchId, $academicYear, $termName, $classId, $approvedBy);
    }
    return (bool)$result;
}
}

if(!function_exists('report_approval_fetch_headmaster_queue')){
function report_approval_fetch_headmaster_queue($con, $branchId = '', $limit = 12){
    $rows = array();
    if(!$con){
        return $rows;
    }

    report_approval_ensure_table($con);
    $limit = max(1, min(200, (int)$limit));
    $branchId = trim((string)$branchId);
    $branchWhereSql = '';
    if($branchId !== ''){
        $branchIdEsc = mysqli_real_escape_string($con, $branchId);
        $branchWhereSql = " AND EXISTS(
            SELECT 1
            FROM tbltermregistry tr_scope
            INNER JOIN tblsystemuser su_scope ON su_scope.userid=tr_scope.userid
            WHERE tr_scope.batchid=ra.batchid
              AND COALESCE(NULLIF(TRIM(tr_scope.academicyear), ''), DATE_FORMAT(tr_scope.datetimeentry, '%Y'))=ra.academicyear
              AND tr_scope.termname=ra.termname
              AND tr_scope.class_entryid=ra.classid
              AND su_scope.systemtype='Student'
              AND su_scope.status='active'
              AND su_scope.branchid='$branchIdEsc'
        )";
    }

    $sql = "SELECT
            ra.*,
            COALESCE(ce.class_name, ra.classid) AS class_name,
            COALESCE(bh.batch, ra.batchid) AS batch_label,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(adminu.firstname,''), ' ', COALESCE(adminu.othernames,''), ' ', COALESCE(adminu.surname,''))), ''), ra.approvedby) AS approved_by_name,
            (
                SELECT COUNT(DISTINCT tr_count.userid)
                FROM tbltermregistry tr_count
                INNER JOIN tblsystemuser su_count ON su_count.userid=tr_count.userid
                WHERE tr_count.batchid=ra.batchid
                  AND COALESCE(NULLIF(TRIM(tr_count.academicyear), ''), DATE_FORMAT(tr_count.datetimeentry, '%Y'))=ra.academicyear
                  AND tr_count.termname=ra.termname
                  AND tr_count.class_entryid=ra.classid
                  AND su_count.systemtype='Student'
                  AND su_count.status='active'".($branchId !== '' ? " AND su_count.branchid='".mysqli_real_escape_string($con, $branchId)."'" : "")."
            ) AS student_total
        FROM tblclassreportapproval ra
        LEFT JOIN tblclassentry ce ON ce.class_entryid=ra.classid
        LEFT JOIN tblbatch bh ON bh.batchid=ra.batchid
        LEFT JOIN tblsystemuser adminu ON adminu.userid=ra.approvedby
        WHERE ra.status='approved'
          AND (ra.headapprovalstatus IS NULL OR ra.headapprovalstatus<>'approved' OR ra.headapproveddatetime IS NULL OR ra.headapproveddatetime='0000-00-00 00:00:00')
          $branchWhereSql
        ORDER BY COALESCE(ra.approveddatetime, ra.datetimeentry) ASC
        LIMIT $limit";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('report_approval_set_score_edit_override')){
function report_approval_set_score_edit_override($con, $batchId, $academicYear, $termName, $classId, $enabled, $updatedBy){
    if(!$con){
        return false;
    }
    $batchId = trim((string)$batchId);
    $academicYear = report_approval_normalize_year($academicYear);
    $termName = (int)trim((string)$termName);
    $classId = trim((string)$classId);
    $updatedBy = trim((string)$updatedBy);
    $enabled = ($enabled ? 1 : 0);

    if($batchId === '' || $academicYear === '' || $termName <= 0 || $classId === ''){
        return false;
    }

    $currentMeta = report_approval_scope_meta($con, $batchId, $academicYear, $termName, $classId);
    if(!$currentMeta['required'] || empty($currentMeta['admin_approved'])){
        return false;
    }

    report_approval_ensure_table($con);
    $batchIdEsc = mysqli_real_escape_string($con, $batchId);
    $academicYearEsc = mysqli_real_escape_string($con, $academicYear);
    $classIdEsc = mysqli_real_escape_string($con, $classId);
    $updatedByEsc = mysqli_real_escape_string($con, $updatedBy);
    $overrideTimeSql = $enabled ? 'NOW()' : 'NULL';
    $overrideBySql = $enabled ? "'".$updatedByEsc."'" : "''";
    $result = @mysqli_query($con, "UPDATE tblclassreportapproval
        SET scoreeditoverride='$enabled',
            scoreeditoverrideby=$overrideBySql,
            scoreeditoverridedatetime=$overrideTimeSql,
            updateddatetime=NOW()
        WHERE batchid='$batchIdEsc'
          AND academicyear='$academicYearEsc'
          AND termname='$termName'
          AND classid='$classIdEsc'
          AND status='approved'
        LIMIT 1");
    if($result){
        report_approval_scope_cache_forget($batchId, $academicYear, $termName, $classId);
    }
    return (bool)$result;
}
}

if(!function_exists('report_approval_assignment_scope')){
function report_approval_assignment_scope($con, $assignmentId){
    if(!$con){
        return null;
    }
    $assignmentId = trim((string)$assignmentId);
    if($assignmentId === ''){
        return null;
    }
    $assignmentIdEsc = mysqli_real_escape_string($con, $assignmentId);
    $sql = "SELECT
            sa.assignmentid,
            sa.classid,
            sa.batchid,
            sa.termname,
            DATE_FORMAT(sa.datetimeentry, '%Y') AS assignment_year
        FROM tblsubjectassignment sa
        WHERE sa.assignmentid='$assignmentIdEsc'
        LIMIT 1";
    $result = mysqli_query($con, $sql);
    if($result && ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))){
        return $row;
    }
    return null;
}
}

if(!function_exists('report_approval_assignment_scope_meta')){
function report_approval_assignment_scope_meta($con, $assignmentId){
    $scope = report_approval_assignment_scope($con, $assignmentId);
    if(!$scope){
        return null;
    }
    $meta = report_approval_scope_meta($con, $scope['batchid'], $scope['assignment_year'], $scope['termname'], $scope['classid']);
    $meta['scope'] = $scope;
    return $meta;
}
}

if(!function_exists('report_approval_mark_scope_meta')){
function report_approval_mark_scope_meta($con, $markId){
    if(!$con){
        return null;
    }
    $markId = trim((string)$markId);
    if($markId === ''){
        return null;
    }
    $markIdEsc = mysqli_real_escape_string($con, $markId);
    $sql = "SELECT
            mk.markid,
            mk.assignmentid,
            sa.classid,
            sa.batchid,
            sa.termname,
            DATE_FORMAT(sa.datetimeentry, '%Y') AS assignment_year
        FROM tblmark mk
        INNER JOIN tblsubjectassignment sa ON sa.assignmentid=mk.assignmentid
        WHERE mk.markid='$markIdEsc'
        LIMIT 1";
    $result = mysqli_query($con, $sql);
    if(!$result || !($row = mysqli_fetch_array($result, MYSQLI_ASSOC))){
        return null;
    }
    $meta = report_approval_scope_meta($con, $row['batchid'], $row['assignment_year'], $row['termname'], $row['classid']);
    $meta['scope'] = $row;
    return $meta;
}
}

if(!function_exists('report_approval_score_edit_locked_message')){
function report_approval_score_edit_locked_message(){
    return "This score sheet is locked because the class result has already been approved for release. Ask the administrator to reopen score editing for this class and semester. Any headmaster signature will need to be applied again after corrections.";
}
}
