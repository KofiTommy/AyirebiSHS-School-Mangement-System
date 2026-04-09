<?php
if(!function_exists('class_teacher_is_admin')){
function class_teacher_is_admin(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "administrator" &&
        ($_SESSION['SYSTEMTYPE'] === "normal_user" || $_SESSION['SYSTEMTYPE'] === "super_user");
}
}

if(!function_exists('class_teacher_is_teacher')){
function class_teacher_is_teacher(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "user" &&
        $_SESSION['SYSTEMTYPE'] === "Teacher";
}
}

if(!function_exists('class_teacher_landing_page')){
function class_teacher_landing_page(){
    if(class_teacher_is_admin()){
        return ($_SESSION['SYSTEMTYPE'] === "super_user") ? "super.php" : "admin.php";
    }
    if(class_teacher_is_teacher()){
        return "teacher-page.php";
    }
    if(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL'] === "user" && $_SESSION['SYSTEMTYPE'] === "User"){
        return "user.php";
    }
    if(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL'] === "user" && $_SESSION['SYSTEMTYPE'] === "Student"){
        return "student-page.php";
    }
    return "index.php";
}
}

if(!function_exists('ensure_class_teacher_table')){
function ensure_class_teacher_table($con){
    $sql = "CREATE TABLE IF NOT EXISTS tblclassteacher (
        assignmentid VARCHAR(40) NOT NULL PRIMARY KEY,
        userid VARCHAR(30) NOT NULL,
        classid VARCHAR(30) NOT NULL,
        batchid VARCHAR(30) NOT NULL,
        termname INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        INDEX idx_teacher (userid),
        INDEX idx_class_batch_term (classid,batchid,termname),
        INDEX idx_status (status)
    )";
    mysqli_query($con, $sql);
}
}

if(!function_exists('ensure_student_terminal_term_column')){
function ensure_student_terminal_term_column($con){
    $colRes = mysqli_query($con, "SHOW COLUMNS FROM tblstudentterminalreport LIKE 'termname'");
    if(!$colRes || mysqli_num_rows($colRes) === 0){
        mysqli_query($con, "ALTER TABLE tblstudentterminalreport ADD COLUMN termname INT NOT NULL DEFAULT 0 AFTER batchid");
        mysqli_query($con, "CREATE INDEX idx_terminal_user_batch_term ON tblstudentterminalreport(userid,batchid,termname)");
    }
}
}

if(!function_exists('class_teacher_is_assigned')){
function class_teacher_is_assigned($con, $teacherId, $classId, $batchId, $termName){
    $teacherId = mysqli_real_escape_string($con, (string)$teacherId);
    $classId = mysqli_real_escape_string($con, (string)$classId);
    $batchId = mysqli_real_escape_string($con, (string)$batchId);
    $termName = (int)$termName;
    $sql = "SELECT assignmentid FROM tblclassteacher
            WHERE userid='$teacherId' AND classid='$classId' AND batchid='$batchId'
              AND termname='$termName' AND status='active' LIMIT 1";
    $res = mysqli_query($con, $sql);
    return ($res && mysqli_num_rows($res) > 0);
}
}

if(!function_exists('class_teacher_can_manage_student_batch')){
function class_teacher_can_manage_student_batch($con, $teacherId, $studentId, $batchId, $termName = null){
    $teacherId = mysqli_real_escape_string($con, (string)$teacherId);
    $studentId = mysqli_real_escape_string($con, (string)$studentId);
    $batchId = mysqli_real_escape_string($con, (string)$batchId);
    $termClause = "";
    if($termName !== null){
        $termClause = " AND ct.termname='".((int)$termName)."'";
    }
    $sql = "SELECT ct.assignmentid
            FROM tbltermregistry tr
            INNER JOIN tblclassteacher ct
                ON ct.classid=tr.class_entryid
               AND ct.batchid=tr.batchid
               AND ct.termname=tr.termname
               AND ct.status='active'
            WHERE ct.userid='$teacherId'
              AND tr.userid='$studentId'
              AND tr.batchid='$batchId'
              $termClause
            LIMIT 1";
    $res = mysqli_query($con, $sql);
    return ($res && mysqli_num_rows($res) > 0);
}
}
?>
