<?php
/* SmartMark: teacher-supervised objective-test marking helpers. */
if(!function_exists('smartmark_ensure_tables')){
function smartmark_ensure_tables($con){
    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblsmartmarkassessment (
        assessmentid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        teacherid VARCHAR(30) NOT NULL,
        assignmentid VARCHAR(30) NOT NULL,
        classid VARCHAR(40) NOT NULL,
        classificationid VARCHAR(30) NOT NULL,
        batchid VARCHAR(30) NOT NULL,
        termname INT NOT NULL,
        title VARCHAR(160) NOT NULL,
        questioncount SMALLINT UNSIGNED NOT NULL,
        optioncount TINYINT UNSIGNED NOT NULL DEFAULT 4,
        marksperquestion DECIMAL(8,2) NOT NULL DEFAULT 1.00,
        answerkey LONGTEXT NOT NULL,
        mastertemplate LONGTEXT NULL,
        masterconfirmed TINYINT(1) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (assessmentid),
        KEY teacher_status (teacherid,status,createdat),
        KEY assignment_scope (assignmentid,status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    @mysqli_query($con, "ALTER TABLE tblsmartmarkassessment ADD COLUMN mastertemplate LONGTEXT NULL AFTER answerkey");
    @mysqli_query($con, "ALTER TABLE tblsmartmarkassessment ADD COLUMN masterconfirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER mastertemplate");
    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblsmartmarkattempt (
        attemptid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        assessmentid BIGINT UNSIGNED NOT NULL,
        studentid VARCHAR(30) NOT NULL,
        answers LONGTEXT NOT NULL,
        correctcount SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        incorrectcount SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        blankcount SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        multiplecount SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        reviewcount SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        score DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        totalscore DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        percentage DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        status VARCHAR(20) NOT NULL DEFAULT 'review',
        markedby VARCHAR(30) NOT NULL,
        createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (attemptid),
        UNIQUE KEY assessment_student (assessmentid,studentid),
        KEY assessment_status (assessmentid,status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}
}
if(!function_exists('smartmark_escape')){ function smartmark_escape($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); } }
if(!function_exists('smartmark_options')){
function smartmark_options($count){ return array_slice(array('A','B','C','D','E'), 0, max(2, min(5, (int)$count))); }
}
if(!function_exists('smartmark_decode_key')){
function smartmark_decode_key($raw, $questionCount, $optionCount){
    $decoded=json_decode((string)$raw,true); $decoded=is_array($decoded)?$decoded:array(); $valid=smartmark_options($optionCount); $key=array();
    for($i=1;$i<=(int)$questionCount;$i++){ $answer=strtoupper(trim((string)($decoded[$i]??''))); $key[$i]=in_array($answer,$valid,true)?$answer:''; }
    return $key;
}
}
if(!function_exists('smartmark_teacher_assignments')){
function smartmark_teacher_assignments($con,$teacherId){
    $teacherEsc=mysqli_real_escape_string($con,(string)$teacherId); $rows=array();
    $sql="SELECT sa.assignmentid,sa.classid,sa.classificationid,sa.batchid,sa.termname,ce.class_name,bh.batch,sub.subject
        FROM tblsubjectassignment sa INNER JOIN tblclassentry ce ON ce.class_entryid=sa.classid
        INNER JOIN tblbatch bh ON bh.batchid=sa.batchid INNER JOIN tblsubjectclassification sc ON sc.classificationid=sa.classificationid
        INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid WHERE sa.userid='$teacherEsc' AND sa.status='active' AND bh.status='active'
        ORDER BY bh.datetimeentry DESC,sa.termname DESC,ce.class_name,sub.subject";
    $result=@mysqli_query($con,$sql); if($result){while($row=mysqli_fetch_array($result,MYSQLI_ASSOC)){$rows[$row['assignmentid']]=$row;}} return $rows;
}
}
if(!function_exists('smartmark_students_for_assessment')){
function smartmark_students_for_assessment($con,$assessment){
    if(file_exists(__DIR__.DIRECTORY_SEPARATOR.'score-entry-utils.php')){ include_once('score-entry-utils.php'); }
    $ids=function_exists('score_entry_assignment_student_context') ? score_entry_assignment_student_context($con,$assessment['assignmentid'],$assessment['classid'],$assessment['batchid'],date('Y'),$assessment['termname']) : array('userids'=>array());
    $ids=isset($ids['userids'])?$ids['userids']:array();
    /* Older installations may not have course-registration windows. Fall back to
       the active class register, matching the portal's task scheduler behaviour. */
    if(!count($ids)){
        $classEsc=mysqli_real_escape_string($con,(string)$assessment['classid']);
        $batchEsc=mysqli_real_escape_string($con,(string)$assessment['batchid']);
        $classRes=@mysqli_query($con,"SELECT DISTINCT userid FROM tblclass WHERE class_entryid='$classEsc' AND batchid='$batchEsc' AND status='active'");
        if($classRes){while($classRow=mysqli_fetch_array($classRes,MYSQLI_ASSOC)){if(trim((string)$classRow['userid'])!==''){$ids[]=$classRow['userid'];}}}
    }
    if(!count($ids)){return array();}
    $quoted=array(); foreach($ids as $id){$quoted[]="'".mysqli_real_escape_string($con,$id)."'";}
    $rows=array(); $res=@mysqli_query($con,"SELECT userid,CONCAT_WS(' ',NULLIF(TRIM(firstname),''),NULLIF(TRIM(othernames),''),NULLIF(TRIM(surname),'')) AS student_name FROM tblsystemuser WHERE userid IN (".implode(',',$quoted).") ORDER BY firstname,othernames,surname");
    if($res){while($row=mysqli_fetch_array($res,MYSQLI_ASSOC)){$rows[]=$row;}} return $rows;
}
}
if(!function_exists('smartmark_grade_answers')){
function smartmark_grade_answers($key,$optionCount,$marksPerQuestion,$postedAnswers,$postedConfidence){
    $valid=smartmark_options($optionCount); $answers=array(); $summary=array('correct'=>0,'incorrect'=>0,'blank'=>0,'multiple'=>0,'review'=>0,'score'=>0);
    foreach($key as $number=>$correct){
        $raw=isset($postedAnswers[$number])?$postedAnswers[$number]:''; $selected=is_array($raw)?array_values(array_unique(array_filter(array_map('strtoupper',$raw),function($v)use($valid){return in_array(trim($v),$valid,true);}))):array();
        $confidence=isset($postedConfidence[$number])?(float)$postedConfidence[$number]:100; $confidence=max(0,min(100,$confidence));
        $state='incorrect'; $answer='';
        if(count($selected)===0){$state='blank';$summary['blank']++;} elseif(count($selected)>1){$state='multiple';$answer=implode('/',$selected);$summary['multiple']++;} else { $answer=$selected[0]; if($answer===$correct){$state='correct';$summary['correct']++;$summary['score']+=(float)$marksPerQuestion;} else {$summary['incorrect']++;} }
        $needsReview=$confidence<80 || $state==='multiple'; if($needsReview){$summary['review']++;}
        $answers[$number]=array('selected'=>$answer,'correct'=>$correct,'state'=>$state,'confidence'=>$confidence,'review'=>$needsReview);
    }
    return array($answers,$summary);
}
}
?>
