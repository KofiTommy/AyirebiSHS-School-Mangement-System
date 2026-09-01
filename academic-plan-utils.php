<?php
if(!function_exists('academic_plan_ensure_table')){
function academic_plan_ensure_table($con){
    @mysqli_query($con,"CREATE TABLE IF NOT EXISTS tblacademicplan (
        planid VARCHAR(60) NOT NULL,
        title VARCHAR(180) NOT NULL,
        eventtype VARCHAR(60) NOT NULL DEFAULT 'Academic Activity',
        startdate DATE NOT NULL,
        enddate DATE DEFAULT NULL,
        description TEXT DEFAULT NULL,
        batchid VARCHAR(60) NOT NULL DEFAULT '',
        termname VARCHAR(20) NOT NULL DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'published',
        recordedby VARCHAR(60) NOT NULL DEFAULT '',
        datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(planid), KEY idx_academic_plan_dates(startdate), KEY idx_academic_plan_scope(batchid,termname,status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $columns=array(
        'weeknumber'=>"INT NOT NULL DEFAULT 0 AFTER termname",
        'audience'=>"VARCHAR(30) NOT NULL DEFAULT 'All School' AFTER weeknumber",
        'classid'=>"VARCHAR(60) NOT NULL DEFAULT '' AFTER audience",
        'venue'=>"VARCHAR(180) NOT NULL DEFAULT '' AFTER classid",
        'activityowner'=>"VARCHAR(180) NOT NULL DEFAULT '' AFTER venue",
        'versionnote'=>"VARCHAR(255) NOT NULL DEFAULT '' AFTER activityowner"
    );
    foreach($columns as $name=>$definition){
        $check=@mysqli_query($con,"SHOW COLUMNS FROM tblacademicplan LIKE '$name'");
        if(!$check || mysqli_num_rows($check)===0){ @mysqli_query($con,"ALTER TABLE tblacademicplan ADD COLUMN $name $definition"); }
    }
}
function academic_plan_escape($value){ return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function academic_plan_event_types(){ return array('Reopening','Teaching & Learning','Continuous Assessment','Mid-Semester Examination','Revision','End of Semester Examination','Marking & Moderation','Holiday / Break','Staff / PTA Meeting','Co-curricular Activity','Result Processing','Result Release','Closing'); }
function academic_plan_audiences(){ return array('All School','Teachers Only','Students Only','Specific Class'); }
function academic_plan_id(){ try { return 'AP'.date('YmdHis').bin2hex(random_bytes(3)); } catch(Exception $e){ return 'AP'.date('YmdHis').uniqid(); } }
function academic_plan_rows($con,$batchId='',$includeDrafts=false,$audience='All School'){
    $batchIdEsc=mysqli_real_escape_string($con,trim((string)$batchId)); $statusSql=$includeDrafts ? "" : "AND ap.status='published'";
    $scopeSql=$batchIdEsc !== '' ? "AND (ap.batchid='' OR ap.batchid='$batchIdEsc')" : '';
    $audienceEsc=mysqli_real_escape_string($con,$audience); $audienceSql=$includeDrafts ? '' : "AND (ap.audience='All School' OR ap.audience='$audienceEsc')";
    $rows=array(); $res=mysqli_query($con,"SELECT ap.*,b.batch,ce.class_name FROM tblacademicplan ap LEFT JOIN tblbatch b ON b.batchid=ap.batchid LEFT JOIN tblclassentry ce ON ce.class_entryid=ap.classid WHERE 1=1 $statusSql $scopeSql $audienceSql ORDER BY ap.startdate ASC,ap.enddate ASC,ap.title ASC");
    if($res){ while($row=mysqli_fetch_array($res,MYSQLI_ASSOC)){ $rows[]=$row; } } return $rows;
}
function academic_plan_viewer_batch($con,$userId,$systemType){
    $userIdEsc=mysqli_real_escape_string($con,(string)$userId);
    if($systemType==='Student'){
        $res=mysqli_query($con,"SELECT batchid FROM tblclass WHERE userid='$userIdEsc' AND status='active' ORDER BY datetimeentry DESC LIMIT 1");
        if($res && ($row=mysqli_fetch_array($res,MYSQLI_ASSOC))){ return (string)$row['batchid']; }
    }
    $res=mysqli_query($con,"SELECT batchid FROM tblbatch WHERE status='active' ORDER BY datetimeentry DESC LIMIT 1");
    if($res && ($row=mysqli_fetch_array($res,MYSQLI_ASSOC))){ return (string)$row['batchid']; }
    return '';
}
}
?>
