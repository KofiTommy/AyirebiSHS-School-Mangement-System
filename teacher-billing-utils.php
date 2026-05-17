<?php
if(!function_exists('xschool_schema_cache_is_fresh')){
function xschool_schema_cache_is_fresh($key, $ttlSeconds = 900){
    static $memoryCache = array();
    $key = trim((string)$key);
    if($key === ''){
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
    if($key === ''){
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

include_once("user-management-utils.php");
include_once("class-teacher-utils.php");

if(!function_exists('teacher_billing_is_admin')){
function teacher_billing_is_admin(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'administrator' &&
        in_array($_SESSION['SYSTEMTYPE'], array('normal_user', 'super_user'), true);
}
}

if(!function_exists('teacher_billing_is_teacher')){
function teacher_billing_is_teacher(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === 'user' &&
        $_SESSION['SYSTEMTYPE'] === 'Teacher';
}
}

if(!function_exists('teacher_billing_landing_page')){
function teacher_billing_landing_page(){
    if(teacher_billing_is_admin()){
        return ($_SESSION['SYSTEMTYPE'] === 'super_user') ? 'super.php' : 'admin.php';
    }
    if(teacher_billing_is_teacher()){
        return 'teacher-page.php';
    }
    if(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL'] === 'user' && $_SESSION['SYSTEMTYPE'] === 'User'){
        return 'user.php';
    }
    if(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL'] === 'user' && $_SESSION['SYSTEMTYPE'] === 'Student'){
        return 'student-page.php';
    }
    return 'index.php';
}
}

if(!function_exists('ensure_teacher_billing_table')){
function ensure_teacher_billing_table($con){
    if(!$con){
        return;
    }
    if(xschool_schema_cache_is_fresh('schema_tblteacherbillingassignment_v1')){
        return;
    }
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblteacherbillingassignment (
        assignmentid VARCHAR(40) NOT NULL PRIMARY KEY,
        userid VARCHAR(30) NOT NULL,
        classid VARCHAR(30) NOT NULL,
        batchid VARCHAR(30) NOT NULL,
        termname INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        INDEX idx_teacher (userid),
        INDEX idx_scope (classid,batchid,termname),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    xschool_schema_cache_mark('schema_tblteacherbillingassignment_v1');
}
}

if(!function_exists('ensure_teacher_billing_item_table')){
function ensure_teacher_billing_item_table($con){
    if(!$con){
        return;
    }
    if(xschool_schema_cache_is_fresh('schema_tblteacherbillingassignmentitem_v1')){
        return;
    }
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblteacherbillingassignmentitem (
        rowid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        assignmentid VARCHAR(40) NOT NULL,
        itempriceid VARCHAR(40) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        UNIQUE KEY uq_assignment_item (assignmentid,itempriceid),
        KEY idx_assignment (assignmentid),
        KEY idx_itemprice (itempriceid),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    xschool_schema_cache_mark('schema_tblteacherbillingassignmentitem_v1');
}
}

if(!function_exists('teacher_billing_can_manage_assignments')){
function teacher_billing_can_manage_assignments(){
    return teacher_billing_is_admin();
}
}

if(!function_exists('teacher_billing_teacher_has_module')){
function teacher_billing_teacher_has_module($con, $teacherId){
    $teacherId = trim((string)$teacherId);
    if($teacherId === '' || !$con){
        return false;
    }
    if(teacher_billing_is_admin()){
        return true;
    }
    $userRow = function_exists('um_fetch_user_row') ? um_fetch_user_row($con, $teacherId) : null;
    if(!$userRow){
        return false;
    }
    return function_exists('um_user_has_module') ? um_user_has_module($con, 'billing', $userRow) : false;
}
}

if(!function_exists('teacher_billing_has_any_assignment')){
function teacher_billing_has_any_assignment($con, $teacherId){
    ensure_teacher_billing_table($con);
    $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$teacherId));
    if($teacherIdEsc === ''){
        return false;
    }
    $sql = "SELECT assignmentid FROM tblteacherbillingassignment
        WHERE userid='$teacherIdEsc' AND status='active'
        LIMIT 1";
    $res = mysqli_query($con, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return true;
    }
    return false;
}
}

if(!function_exists('teacher_billing_is_assigned')){
function teacher_billing_is_assigned($con, $teacherId, $classId, $batchId, $termName){
    ensure_teacher_billing_table($con);
    $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$teacherId));
    $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $termName = (int)$termName;
    if($teacherIdEsc === '' || $classIdEsc === '' || $batchIdEsc === '' || $termName <= 0){
        return false;
    }
    $sql = "SELECT assignmentid FROM tblteacherbillingassignment
        WHERE userid='$teacherIdEsc'
          AND classid='$classIdEsc'
          AND batchid='$batchIdEsc'
          AND termname='$termName'
          AND status='active'
        LIMIT 1";
    $res = mysqli_query($con, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return true;
    }
    return false;
}
}

if(!function_exists('teacher_billing_is_assigned_pair')){
function teacher_billing_is_assigned_pair($con, $teacherId, $classId, $batchId){
    ensure_teacher_billing_table($con);
    $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$teacherId));
    $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    if($teacherIdEsc === '' || $classIdEsc === '' || $batchIdEsc === ''){
        return false;
    }
    $sql = "SELECT assignmentid FROM tblteacherbillingassignment
        WHERE userid='$teacherIdEsc'
          AND classid='$classIdEsc'
          AND batchid='$batchIdEsc'
          AND status='active'
        LIMIT 1";
    $res = mysqli_query($con, $sql);
    if($res && mysqli_num_rows($res) > 0){
        return true;
    }
    return false;
}
}

if(!function_exists('teacher_billing_terms_for_pair')){
function teacher_billing_terms_for_pair($con, $teacherId, $classId, $batchId){
    ensure_teacher_billing_table($con);
    $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$teacherId));
    $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $terms = array();
    if($teacherIdEsc === '' || $classIdEsc === '' || $batchIdEsc === ''){
        return $terms;
    }
    $sql = "SELECT DISTINCT termname
        FROM tblteacherbillingassignment
        WHERE userid='$teacherIdEsc'
          AND classid='$classIdEsc'
          AND batchid='$batchIdEsc'
          AND status='active'
        ORDER BY termname ASC";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $termName = (int)$row['termname'];
            if($termName > 0){
                $terms[$termName] = $termName;
            }
        }
    }
    return array_values($terms);
}
}

if(!function_exists('teacher_billing_current_user_can_use_pages')){
function teacher_billing_current_user_can_use_pages($con){
    if(teacher_billing_is_admin()){
        return true;
    }
    if(!teacher_billing_is_teacher()){
        return false;
    }
    $teacherId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
    if($teacherId === '' || !teacher_billing_teacher_has_module($con, $teacherId)){
        return false;
    }
    $scriptName = isset($_SERVER['PHP_SELF']) ? basename((string)$_SERVER['PHP_SELF']) : '';
    return ($scriptName === 'payments.php');
}
}

if(!function_exists('teacher_billing_enforce_page_access')){
function teacher_billing_enforce_page_access($con){
    ensure_teacher_billing_table($con);
    if(teacher_billing_current_user_can_use_pages($con)){
        return true;
    }

    if(teacher_billing_is_teacher()){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white;padding:8px;'>You do not have access to the billing module.</div>";
    }

    header("location:".teacher_billing_landing_page());
    exit();
}
}

if(!function_exists('teacher_billing_allowed_scope_sql')){
function teacher_billing_allowed_scope_sql($con, $teacherId, $classField, $batchField, $termField = ''){
    ensure_teacher_billing_table($con);
    if(teacher_billing_is_admin()){
        return '1=1';
    }
    $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$teacherId));
    $classField = trim((string)$classField);
    $batchField = trim((string)$batchField);
    $termField = trim((string)$termField);
    if($teacherIdEsc === '' || $classField === '' || $batchField === ''){
        return '1=0';
    }
    $sql = "(EXISTS (
        SELECT 1
        FROM tblteacherbillingassignment tba
        WHERE tba.userid='$teacherIdEsc'
          AND tba.status='active'
          AND tba.classid=$classField
          AND tba.batchid=$batchField";
    if($termField !== ''){
        $sql .= " AND tba.termname=$termField";
    }
    $sql .= "))";
    return $sql;
}
}

if(!function_exists('teacher_billing_enforce_scope_or_redirect')){
function teacher_billing_enforce_scope_or_redirect($con, $classId, $batchId, $termName = null){
    if(teacher_billing_is_admin()){
        return true;
    }
    $teacherId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
    $termValue = ($termName === null ? 0 : (int)$termName);
    if($classId === '' || $batchId === '' || $termValue <= 0 || !teacher_billing_is_assigned($con, $teacherId, $classId, $batchId, $termValue)){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white;padding:8px;'>You are not assigned billing access for that class, batch, and semester.</div>";
        header("location:".teacher_billing_landing_page());
        exit();
    }
    return true;
}
}

if(!function_exists('teacher_billing_fetch_assignments')){
function teacher_billing_fetch_assignments($con, $teacherId){
    ensure_teacher_billing_table($con);
    ensure_teacher_billing_item_table($con);
    $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$teacherId));
    $rows = array();
    if($teacherIdEsc === ''){
        return $rows;
    }
    $sql = "SELECT tba.*, ce.class_name, bh.batch,
        (
            SELECT COUNT(*)
            FROM tblteacherbillingassignmentitem tbai
            WHERE tbai.assignmentid=tba.assignmentid
              AND tbai.status='active'
        ) AS selected_item_count
        FROM tblteacherbillingassignment tba
        INNER JOIN tblclassentry ce ON ce.class_entryid=tba.classid
        INNER JOIN tblbatch bh ON bh.batchid=tba.batchid
        WHERE tba.userid='$teacherIdEsc' AND tba.status='active'
        ORDER BY bh.datetimeentry DESC, tba.termname DESC, ce.class_name ASC";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('teacher_billing_class_options')){
function teacher_billing_class_options($con){
    $rows = array();
    if(teacher_billing_is_admin()){
        $res = mysqli_query($con, "SELECT class_entryid, class_name FROM tblclassentry ORDER BY class_name ASC");
        if($res){
            while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
                $rows[] = $row;
            }
        }
        return $rows;
    }

    $teacherId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
    $teacherIdEsc = mysqli_real_escape_string($con, $teacherId);
    $res = mysqli_query($con, "SELECT DISTINCT ce.class_entryid, ce.class_name
        FROM tblteacherbillingassignment tba
        INNER JOIN tblclassentry ce ON ce.class_entryid=tba.classid
        WHERE tba.userid='$teacherIdEsc' AND tba.status='active'
        ORDER BY ce.class_name ASC");
    if($res){
            while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
                $rows[] = $row;
            }
        }
    return $rows;
}
}

if(!function_exists('teacher_billing_batch_options')){
function teacher_billing_batch_options($con){
    $rows = array();
    if(teacher_billing_is_admin()){
        $res = mysqli_query($con, "SELECT batchid, batch FROM tblbatch ORDER BY datetimeentry DESC");
        if($res){
            while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
                $rows[] = $row;
            }
        }
        return $rows;
    }

    $teacherId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
    $teacherIdEsc = mysqli_real_escape_string($con, $teacherId);
    $res = mysqli_query($con, "SELECT DISTINCT bh.batchid, bh.batch, bh.datetimeentry
        FROM tblteacherbillingassignment tba
        INNER JOIN tblbatch bh ON bh.batchid=tba.batchid
        WHERE tba.userid='$teacherIdEsc' AND tba.status='active'
        ORDER BY bh.datetimeentry DESC");
    if($res){
            while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
                $rows[] = $row;
            }
        }
    return $rows;
}
}

if(!function_exists('teacher_billing_assignment_row')){
function teacher_billing_assignment_row($con, $assignmentId){
    ensure_teacher_billing_table($con);
    ensure_teacher_billing_item_table($con);
    $assignmentIdEsc = mysqli_real_escape_string($con, trim((string)$assignmentId));
    if($assignmentIdEsc === ''){
        return null;
    }
    $sql = "SELECT tba.*, ce.class_name, bh.batch, su.firstname, su.othernames, su.surname
        FROM tblteacherbillingassignment tba
        INNER JOIN tblclassentry ce ON ce.class_entryid=tba.classid
        INNER JOIN tblbatch bh ON bh.batchid=tba.batchid
        INNER JOIN tblsystemuser su ON su.userid=tba.userid
        WHERE tba.assignmentid='$assignmentIdEsc'
        LIMIT 1";
    $res = mysqli_query($con, $sql);
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return $row;
    }
    return null;
}
}

if(!function_exists('teacher_billing_assignment_item_rows')){
function teacher_billing_assignment_item_rows($con, $assignmentId){
    ensure_teacher_billing_item_table($con);
    $rows = array();
    $assignmentIdEsc = mysqli_real_escape_string($con, trim((string)$assignmentId));
    if($assignmentIdEsc === ''){
        return $rows;
    }
    $sql = "SELECT tbai.*, ip.class_entryid, ip.batch, ip.term, ip.price, itm.itemname
        FROM tblteacherbillingassignmentitem tbai
        INNER JOIN tblitemprice ip ON ip.itempriceid=tbai.itempriceid
        INNER JOIN tblitem itm ON itm.itemid=ip.itemid
        WHERE tbai.assignmentid='$assignmentIdEsc' AND tbai.status='active'
        ORDER BY itm.itemname ASC";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('teacher_billing_scope_itemprice_rows')){
function teacher_billing_scope_itemprice_rows($con, $classId, $batchId, $termName){
    $rows = array();
    $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $termName = (int)$termName;
    if($classIdEsc === '' || $batchIdEsc === '' || $termName <= 0){
        return $rows;
    }
    $sql = "SELECT ip.*, itm.itemname
        FROM tblitemprice ip
        INNER JOIN tblitem itm ON itm.itemid=ip.itemid
        WHERE ip.class_entryid='$classIdEsc'
          AND ip.batch='$batchIdEsc'
          AND ip.term='$termName'
          AND ip.status='active'
          AND itm.status='active'
        ORDER BY itm.itemname ASC";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('teacher_billing_assignment_replace_items')){
function teacher_billing_assignment_replace_items($con, $assignmentId, $itemPriceIds, $recordedBy){
    ensure_teacher_billing_item_table($con);
    $assignmentIdEsc = mysqli_real_escape_string($con, trim((string)$assignmentId));
    if($assignmentIdEsc === ''){
        return false;
    }
    mysqli_query($con, "DELETE FROM tblteacherbillingassignmentitem WHERE assignmentid='$assignmentIdEsc'");
    $recordedByEsc = mysqli_real_escape_string($con, trim((string)$recordedBy));
    $inserted = true;
    $seen = array();
    foreach((array)$itemPriceIds as $itemPriceId){
        $itemPriceId = trim((string)$itemPriceId);
        if($itemPriceId === '' || isset($seen[$itemPriceId])){
            continue;
        }
        $seen[$itemPriceId] = true;
        $itemPriceIdEsc = mysqli_real_escape_string($con, $itemPriceId);
        $sql = "INSERT INTO tblteacherbillingassignmentitem(assignmentid,itempriceid,status,datetimeentry,recordedby)
            VALUES('$assignmentIdEsc','$itemPriceIdEsc','active',NOW(),'$recordedByEsc')";
        if(!mysqli_query($con, $sql)){
            $inserted = false;
        }
    }
    return $inserted;
}
}

if(!function_exists('teacher_billing_allowed_itemprice_ids')){
function teacher_billing_allowed_itemprice_ids($con, $teacherId, $classId, $batchId, $termName){
    ensure_teacher_billing_table($con);
    ensure_teacher_billing_item_table($con);
    $teacherIdEsc = mysqli_real_escape_string($con, trim((string)$teacherId));
    $classIdEsc = mysqli_real_escape_string($con, trim((string)$classId));
    $batchIdEsc = mysqli_real_escape_string($con, trim((string)$batchId));
    $termName = (int)$termName;
    $itemIds = array();
    if($teacherIdEsc === '' || $classIdEsc === '' || $batchIdEsc === '' || $termName <= 0){
        return $itemIds;
    }
    $sql = "SELECT DISTINCT tbai.itempriceid
        FROM tblteacherbillingassignment tba
        INNER JOIN tblteacherbillingassignmentitem tbai ON tbai.assignmentid=tba.assignmentid
        WHERE tba.userid='$teacherIdEsc'
          AND tba.classid='$classIdEsc'
          AND tba.batchid='$batchIdEsc'
          AND tba.termname='$termName'
          AND tba.status='active'
          AND tbai.status='active'";
    $res = mysqli_query($con, $sql);
    if($res){
        while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){
            $itemPriceId = trim((string)($row['itempriceid'] ?? ''));
            if($itemPriceId !== ''){
                $itemIds[$itemPriceId] = $itemPriceId;
            }
        }
    }
    return array_values($itemIds);
}
}

if(!function_exists('teacher_billing_scope_has_item_filter')){
function teacher_billing_scope_has_item_filter($con, $teacherId, $classId, $batchId, $termName){
    $allowed = teacher_billing_allowed_itemprice_ids($con, $teacherId, $classId, $batchId, $termName);
    return count($allowed) > 0;
}
}

if(!function_exists('teacher_billing_itemprice_is_allowed')){
function teacher_billing_itemprice_is_allowed($con, $teacherId, $classId, $batchId, $termName, $itemPriceId){
    $itemPriceId = trim((string)$itemPriceId);
    if($itemPriceId === ''){
        return false;
    }
    $allowed = teacher_billing_allowed_itemprice_ids($con, $teacherId, $classId, $batchId, $termName);
    if(empty($allowed)){
        return true;
    }
    return in_array($itemPriceId, $allowed, true);
}
}
?>
