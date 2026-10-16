<?php
require_once __DIR__.'/course-registration-utils.php';

function course_registration_all_class_progress($con, $batchId, $year, $term) {
    if($batchId==='' || $year==='' || !in_array($term,array('1','2'),true)) { return array(); }
    $yearSql=semester_registry_resolved_year_sql('tr');
    // Count each eligible student once, even if duplicate term-registry rows exist.
    // A historic submission from outside this class/session must not inflate progress.
    $sql="SELECT ce.class_entryid AS classid,ce.class_name,w.windowid,w.status,w.openfrom,w.closeat,
        COUNT(DISTINCT su.userid) AS eligible_count,
        COUNT(DISTINCT CASE WHEN s.status='submitted' THEN su.userid END) AS submitted_count
        FROM tblclassentry ce
        LEFT JOIN tblcourseregwindow w ON w.classid=ce.class_entryid AND w.batchid=? AND w.academicyear=? AND w.termname=?
        LEFT JOIN tbltermregistry tr ON tr.class_entryid=ce.class_entryid AND tr.batchid=? AND ".$yearSql."=? AND tr.termname=? AND tr.status='active'
        LEFT JOIN tblsystemuser su ON su.userid=tr.userid AND su.systemtype='Student' AND su.status='active'
        LEFT JOIN tblstudentcourseregstatus s ON s.windowid=w.windowid AND s.userid=su.userid
        GROUP BY ce.class_entryid,ce.class_name,w.windowid,w.status,w.openfrom,w.closeat
        HAVING COUNT(DISTINCT su.userid)>0
        ORDER BY ce.class_name,ce.class_entryid";
    $stmt=mysqli_prepare($con,$sql);
    mysqli_stmt_bind_param($stmt,'ssssss',$batchId,$year,$term,$batchId,$year,$term);
    mysqli_stmt_execute($stmt);
    $result=mysqli_stmt_get_result($stmt); $rows=array();
    while($row=mysqli_fetch_assoc($result)) {
        $row['eligible_count']=(int)$row['eligible_count'];
        $row['submitted_count']=(int)$row['submitted_count'];
        $row['outstanding_count']=$row['eligible_count']-$row['submitted_count'];
        $row['progress_percent']=$row['eligible_count'] ? round(100*$row['submitted_count']/$row['eligible_count'],1) : 0;
        $rows[]=$row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}
