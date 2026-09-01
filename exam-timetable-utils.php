<?php
if(!function_exists('exam_timetable_ensure_tables')){
function exam_timetable_ensure_tables($con)
{
    $column = @mysqli_query($con,"SHOW COLUMNS FROM tbltimetable LIKE 'invigilators'");
    if(!$column || mysqli_num_rows($column) === 0){
        @mysqli_query($con,"ALTER TABLE tbltimetable ADD COLUMN invigilators VARCHAR(255) NOT NULL DEFAULT '' AFTER subjectid");
    }
    $examTypeColumn = @mysqli_query($con,"SHOW COLUMNS FROM tbltimetable LIKE 'examtype'");
    if(!$examTypeColumn || mysqli_num_rows($examTypeColumn) === 0){
        @mysqli_query($con,"ALTER TABLE tbltimetable ADD COLUMN examtype VARCHAR(50) NOT NULL DEFAULT 'End of Semester Examination' AFTER subjectid");
    }

    @mysqli_query($con,"CREATE TABLE IF NOT EXISTS tblexamtimetableinvigilator (
        invigilatorid INT NOT NULL AUTO_INCREMENT,
        timeid VARCHAR(60) NOT NULL,
        userid VARCHAR(60) NOT NULL,
        datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (invigilatorid),
        UNIQUE KEY uq_exam_timetable_invigilator (timeid,userid),
        KEY idx_exam_timetable_invigilator_user (userid),
        KEY idx_exam_timetable_invigilator_time (timeid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function exam_timetable_teacher_list($con)
{
    $teachers = array();
    $res = mysqli_query($con,"SELECT userid,firstname,othernames,surname
        FROM tblsystemuser
        WHERE systemtype='Teacher' AND status='active'
        ORDER BY firstname ASC,othernames ASC,surname ASC");
    if($res){
        while($row = mysqli_fetch_array($res,MYSQLI_ASSOC)){
            $row['teacher_name'] = trim(implode(' ',array_filter(array(
                trim((string)$row['firstname']),trim((string)$row['othernames']),trim((string)$row['surname'])
            ),function($value){ return $value !== ''; })));
            if($row['teacher_name'] === ''){ $row['teacher_name'] = (string)$row['userid']; }
            $teachers[] = $row;
        }
    }
    return $teachers;
}

function exam_timetable_clean_teacher_ids($teacherIds)
{
    if(!is_array($teacherIds)){ return array(); }
    $clean = array();
    foreach($teacherIds as $teacherId){
        $teacherId = trim((string)$teacherId);
        if($teacherId !== ''){ $clean[$teacherId] = true; }
    }
    return array_keys($clean);
}

function exam_timetable_exam_types()
{
    return array('Mid-Semester Examination','End of Semester Examination');
}

function exam_timetable_normalize_exam_type($examType)
{
    $examType = trim((string)$examType);
    return in_array($examType,exam_timetable_exam_types(),true) ? $examType : 'End of Semester Examination';
}

function exam_timetable_replace_invigilators($con,$timeId,$teacherIds)
{
    $timeId = trim((string)$timeId);
    if($timeId === ''){ return false; }
    $timeIdEsc = mysqli_real_escape_string($con,$timeId);
    $teacherIds = exam_timetable_clean_teacher_ids($teacherIds);
    mysqli_query($con,"DELETE FROM tblexamtimetableinvigilator WHERE timeid='$timeIdEsc'");
    if(count($teacherIds) === 0){ return true; }

    $stmt = mysqli_prepare($con,"INSERT IGNORE INTO tblexamtimetableinvigilator(timeid,userid) VALUES(?,?)");
    if(!$stmt){ return false; }
    foreach($teacherIds as $teacherId){
        mysqli_stmt_bind_param($stmt,'ss',$timeId,$teacherId);
        if(!mysqli_stmt_execute($stmt)){
            mysqli_stmt_close($stmt);
            return false;
        }
    }
    mysqli_stmt_close($stmt);
    return true;
}

function exam_timetable_selected_invigilator_ids($con,$timeId)
{
    $selected = array();
    $timeIdEsc = mysqli_real_escape_string($con,trim((string)$timeId));
    if($timeIdEsc === ''){ return $selected; }
    $res = mysqli_query($con,"SELECT userid FROM tblexamtimetableinvigilator WHERE timeid='$timeIdEsc'");
    if($res){ while($row=mysqli_fetch_array($res,MYSQLI_ASSOC)){ $selected[(string)$row['userid']] = true; } }
    return $selected;
}

function exam_timetable_invigilator_subquery()
{
    return "LEFT JOIN (
        SELECT eti.timeid,GROUP_CONCAT(DISTINCT TRIM(CONCAT_WS(' ',su.firstname,su.othernames,su.surname)) ORDER BY su.firstname,su.othernames,su.surname SEPARATOR ', ') AS selected_invigilators
        FROM tblexamtimetableinvigilator eti
        INNER JOIN tblsystemuser su ON su.userid=eti.userid
        GROUP BY eti.timeid
    ) exam_invigilators ON exam_invigilators.timeid=tt.timeid";
}

function exam_timetable_display_invigilators($row)
{
    $selected = trim((string)(isset($row['selected_invigilators']) ? $row['selected_invigilators'] : ''));
    if($selected !== ''){ return $selected; }
    return trim((string)(isset($row['invigilators']) ? $row['invigilators'] : ''));
}

function exam_timetable_teacher_dashboard_rows($con,$teacherId,$limit=8)
{
    $teacherIdEsc = mysqli_real_escape_string($con,trim((string)$teacherId));
    $limit = max(1,min(30,(int)$limit));
    if($teacherIdEsc === ''){ return array(); }
    $rows = array();
    $sql = "SELECT tt.*,ce.class_name,bch.batch,sub.subject
        FROM tblexamtimetableinvigilator eti
        INNER JOIN tbltimetable tt ON tt.timeid=eti.timeid
        LEFT JOIN tblclassentry ce ON ce.class_entryid=tt.class_entryid
        LEFT JOIN tblbatch bch ON bch.batchid=tt.batchid
        LEFT JOIN tblsubject sub ON sub.subjectid=tt.subjectid
        WHERE eti.userid='$teacherIdEsc'
        ORDER BY CASE WHEN tt.tabledate >= CURDATE() THEN 0 ELSE 1 END,tt.tabledate ASC,tt.tablestarttime ASC
        LIMIT $limit";
    $res = mysqli_query($con,$sql);
    if($res){ while($row=mysqli_fetch_array($res,MYSQLI_ASSOC)){ $rows[]=$row; } }
    return $rows;
}

function exam_timetable_student_dashboard_rows($con,$studentId,$limit=8)
{
    $studentIdEsc = mysqli_real_escape_string($con,trim((string)$studentId));
    $limit = max(1,min(30,(int)$limit));
    if($studentIdEsc === ''){ return array(); }
    $rows = array();
    $sql = "SELECT DISTINCT tt.*,ce.class_name,bch.batch,sub.subject,exam_invigilators.selected_invigilators
        FROM tbltermregistry tr
        INNER JOIN tbltimetable tt ON tt.class_entryid=tr.class_entryid AND tt.batchid=tr.batchid AND tt.termname=tr.termname
        LEFT JOIN tblclassentry ce ON ce.class_entryid=tt.class_entryid
        LEFT JOIN tblbatch bch ON bch.batchid=tt.batchid
        LEFT JOIN tblsubject sub ON sub.subjectid=tt.subjectid
        ".exam_timetable_invigilator_subquery()."
        WHERE tr.userid='$studentIdEsc'
        ORDER BY CASE WHEN tt.tabledate >= CURDATE() THEN 0 ELSE 1 END,tt.tabledate ASC,tt.tablestarttime ASC
        LIMIT $limit";
    $res = mysqli_query($con,$sql);
    if($res){ while($row=mysqli_fetch_array($res,MYSQLI_ASSOC)){ $rows[]=$row; } }
    return $rows;
}
}
?>
