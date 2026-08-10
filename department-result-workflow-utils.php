<?php
/* Departmental result workflow: teacher -> HOD -> Assistant Head Academics -> administrator release. */
if(!function_exists('drw_id')){ function drw_id($prefix){ return $prefix.strtoupper(substr(sha1(uniqid('',true)),0,18)); } }
if(!function_exists('drw_ensure_tables')){
function drw_ensure_tables($con){
    @mysqli_query($con,"CREATE TABLE IF NOT EXISTS tbldepartment (departmentid VARCHAR(50) PRIMARY KEY, departmentname VARCHAR(150) NOT NULL, hodid VARCHAR(100) NOT NULL DEFAULT '', status VARCHAR(20) NOT NULL DEFAULT 'active', createdby VARCHAR(100) NOT NULL DEFAULT '', createdat DATETIME NOT NULL, updatedat DATETIME NOT NULL, UNIQUE KEY uq_department_name(departmentname)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @mysqli_query($con,"CREATE TABLE IF NOT EXISTS tbldepartmentteacher (departmentid VARCHAR(50) NOT NULL, teacherid VARCHAR(100) NOT NULL, assignedat DATETIME NOT NULL, assignedby VARCHAR(100) NOT NULL DEFAULT '', PRIMARY KEY(departmentid,teacherid), KEY idx_teacher(teacherid)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @mysqli_query($con,"CREATE TABLE IF NOT EXISTS tbldepartmentsubject (departmentid VARCHAR(50) NOT NULL, classificationid VARCHAR(100) NOT NULL, assignedat DATETIME NOT NULL, assignedby VARCHAR(100) NOT NULL DEFAULT '', PRIMARY KEY(departmentid,classificationid), KEY idx_classification(classificationid)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @mysqli_query($con,"CREATE TABLE IF NOT EXISTS tbldepartmentresultworkflow (workflowid VARCHAR(50) PRIMARY KEY, assignmentid VARCHAR(100) NOT NULL, academicyear VARCHAR(20) NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'draft', teachersubmittedby VARCHAR(100) NOT NULL DEFAULT '', teachersubmittedat DATETIME NULL, hodapprovedby VARCHAR(100) NOT NULL DEFAULT '', hodapprovedat DATETIME NULL, academicapprovedby VARCHAR(100) NOT NULL DEFAULT '', academicapprovedat DATETIME NULL, lastnote VARCHAR(255) NOT NULL DEFAULT '', updatedat DATETIME NOT NULL, UNIQUE KEY uq_assignment_year(assignmentid,academicyear), KEY idx_status(status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
}
if(!function_exists('drw_is_admin')){ function drw_is_admin(){ return isset($_SESSION['ACCESSLEVEL']) && $_SESSION['ACCESSLEVEL']==='administrator'; } }
if(!function_exists('drw_is_academic_lead')){ function drw_is_academic_lead(){ return function_exists('um_is_assistant_head_academics_user') && um_is_assistant_head_academics_user(); } }
if(!function_exists('drw_department_for_teacher')){
function drw_department_for_teacher($con,$teacherId){ $e=mysqli_real_escape_string($con,(string)$teacherId); $r=mysqli_query($con,"SELECT d.* FROM tbldepartment d INNER JOIN tbldepartmentteacher dt ON dt.departmentid=d.departmentid WHERE dt.teacherid='$e' AND d.status='active' LIMIT 1"); return $r?mysqli_fetch_assoc($r):null; }
}
if(!function_exists('drw_departments_for_hod')){
function drw_departments_for_hod($con,$userId){ $e=mysqli_real_escape_string($con,(string)$userId);$rows=array();$r=mysqli_query($con,"SELECT * FROM tbldepartment WHERE hodid='$e' AND status='active'");if($r){while($x=mysqli_fetch_assoc($r))$rows[]=$x;}return $rows; }
}
if(!function_exists('drw_assignment_department')){
function drw_assignment_department($con,$assignmentId){ $e=mysqli_real_escape_string($con,(string)$assignmentId);$r=mysqli_query($con,"SELECT d.departmentid,d.departmentname FROM tblsubjectassignment sa INNER JOIN tbldepartmentsubject ds ON ds.classificationid=sa.classificationid INNER JOIN tbldepartment d ON d.departmentid=ds.departmentid AND d.status='active' WHERE sa.assignmentid='$e' LIMIT 1");return $r?mysqli_fetch_assoc($r):null; }
}
if(!function_exists('drw_scope_ready_for_admin_release')){
function drw_scope_ready_for_admin_release($con,$batchId,$academicYear,$termName,$classId){
    drw_ensure_tables($con);$b=mysqli_real_escape_string($con,(string)$batchId);$y=mysqli_real_escape_string($con,(string)$academicYear);$t=(int)$termName;$c=mysqli_real_escape_string($con,(string)$classId);
    $sql="SELECT sa.assignmentid FROM tblsubjectassignment sa INNER JOIN tbldepartmentsubject ds ON ds.classificationid=sa.classificationid INNER JOIN tbldepartment d ON d.departmentid=ds.departmentid AND d.status='active' WHERE sa.batchid='$b' AND sa.termname=$t AND sa.classid='$c' AND sa.status='active'";
    $r=mysqli_query($con,$sql);$required=0;$pending=0;if($r){while($row=mysqli_fetch_assoc($r)){$required++;$a=mysqli_real_escape_string($con,$row['assignmentid']);$w=mysqli_query($con,"SELECT status FROM tbldepartmentresultworkflow WHERE assignmentid='$a' AND academicyear='$y' LIMIT 1");$wr=$w?mysqli_fetch_assoc($w):null;if(!$wr||$wr['status']!=='academic_approved')$pending++;}}
    return array('required'=>$required,'ready'=>$pending===0,'pending'=>$pending);
}
}
if(!function_exists('drw_update_status')){
function drw_update_status($con,$assignmentId,$academicYear,$status,$actor,$note=''){
    drw_ensure_tables($con);$a=mysqli_real_escape_string($con,(string)$assignmentId);$y=mysqli_real_escape_string($con,(string)$academicYear);$s=mysqli_real_escape_string($con,(string)$status);$u=mysqli_real_escape_string($con,(string)$actor);$n=mysqli_real_escape_string($con,substr(trim((string)$note),0,255));$id=drw_id('DRW_');
    $fields="status='$s',lastnote='$n',updatedat=NOW()";if($status==='submitted')$fields.=",teachersubmittedby='$u',teachersubmittedat=NOW(),hodapprovedby='',hodapprovedat=NULL,academicapprovedby='',academicapprovedat=NULL";if($status==='hod_approved')$fields.=",hodapprovedby='$u',hodapprovedat=NOW()";if($status==='academic_approved')$fields.=",academicapprovedby='$u',academicapprovedat=NOW()";
    return mysqli_query($con,"INSERT INTO tbldepartmentresultworkflow(workflowid,assignmentid,academicyear,status,lastnote,updatedat) VALUES('$id','$a','$y','$s','$n',NOW()) ON DUPLICATE KEY UPDATE $fields");
}
}
if(!function_exists('drw_scope_approval_proof')){
function drw_scope_approval_proof($con,$batchId,$academicYear,$termName,$classId){
    drw_ensure_tables($con);$b=mysqli_real_escape_string($con,(string)$batchId);$y=mysqli_real_escape_string($con,(string)$academicYear);$t=(int)$termName;$c=mysqli_real_escape_string($con,(string)$classId);
    $proof=array('teacher'=>'','hod'=>'','academic'=>'');$sql="SELECT DISTINCT w.teachersubmittedby,w.hodapprovedby,w.academicapprovedby FROM tblsubjectassignment sa INNER JOIN tbldepartmentsubject ds ON ds.classificationid=sa.classificationid INNER JOIN tbldepartmentresultworkflow w ON w.assignmentid=sa.assignmentid AND w.academicyear='$y' WHERE sa.batchid='$b' AND sa.termname=$t AND sa.classid='$c' AND sa.status='active' AND w.status='academic_approved'";$r=mysqli_query($con,$sql);$ids=array('teacher'=>array(),'hod'=>array(),'academic'=>array());if($r)while($row=mysqli_fetch_assoc($r)){foreach(array('teacher'=>'teachersubmittedby','hod'=>'hodapprovedby','academic'=>'academicapprovedby') as $k=>$f)if(trim((string)$row[$f])!=='')$ids[$k][(string)$row[$f]]=(string)$row[$f];}foreach($ids as $k=>$set){if(empty($set))continue;$quoted=array();foreach($set as $id)$quoted[]="'".mysqli_real_escape_string($con,$id)."'";$n=mysqli_query($con,"SELECT CONCAT_WS(' ',firstname,othernames,surname) name FROM tblsystemuser WHERE userid IN (".implode(',',$quoted).")");$names=array();if($n)while($row=mysqli_fetch_assoc($n))$names[]=trim((string)$row['name']);$proof[$k]=implode(', ',$names);}return $proof;
}
}
?>
