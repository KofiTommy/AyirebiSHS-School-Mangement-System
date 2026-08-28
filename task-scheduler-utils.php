<?php
/* Shared task scheduler helpers for teachers and students. */
if(!function_exists('task_scheduler_ensure_tables')){
function task_scheduler_ensure_tables($con){
    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblteachertask (
        taskid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        teacherid VARCHAR(30) NOT NULL,
        assignmentid VARCHAR(30) NOT NULL,
        classid VARCHAR(40) NOT NULL,
        classificationid VARCHAR(30) NOT NULL,
        batchid VARCHAR(30) NOT NULL,
        termname INT NOT NULL,
        title VARCHAR(160) NOT NULL,
        instructions TEXT NULL,
        tasktype VARCHAR(30) NOT NULL DEFAULT 'Homework',
        dueat DATETIME NOT NULL,
        publishat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(20) NOT NULL DEFAULT 'published',
        createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (taskid),
        KEY teacher_status (teacherid,status,dueat),
        KEY class_scope (classid,batchid,termname,status,publishat)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblteachertasksubmission (
        submissionid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        taskid BIGINT UNSIGNED NOT NULL,
        studentid VARCHAR(30) NOT NULL,
        response TEXT NULL,
        submittedat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(20) NOT NULL DEFAULT 'submitted',
        teacherfeedback TEXT NULL,
        reviewedat DATETIME NULL,
        PRIMARY KEY (submissionid),
        UNIQUE KEY task_student (taskid,studentid),
        KEY student_status (studentid,status,submittedat)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}
}
if(!function_exists('task_scheduler_escape')){
function task_scheduler_escape($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}
if(!function_exists('task_scheduler_due_label')){
function task_scheduler_due_label($value){
    $timestamp = strtotime((string)$value);
    return $timestamp ? date('D, j M Y \\a\\t g:i A', $timestamp) : 'No deadline set';
}
}
if(!function_exists('task_scheduler_student_open_count')){
function task_scheduler_student_open_count($con, $studentId){
    $studentEsc = mysqli_real_escape_string($con, (string)$studentId);
    $sql = "SELECT COUNT(DISTINCT t.taskid) AS total
        FROM tblteachertask t
        INNER JOIN tblclass cl ON cl.class_entryid=t.classid AND cl.batchid=t.batchid AND cl.userid='$studentEsc' AND cl.status='active'
        WHERE t.status='published' AND t.publishat<=NOW()
          AND NOT EXISTS (SELECT 1 FROM tblteachertasksubmission s WHERE s.taskid=t.taskid AND s.studentid='$studentEsc')";
    $result = @mysqli_query($con, $sql);
    $row = $result ? mysqli_fetch_array($result, MYSQLI_ASSOC) : null;
    return $row ? (int)$row['total'] : 0;
}
}
?>
