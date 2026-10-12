<?php
// Explicit CLI repair: default is verification only. Never reachable from a browser.
if(PHP_SAPI !== 'cli'){ http_response_code(403); exit; }
require __DIR__.'/waec-analysis-regression.php';
$options = getopt('', array('xlsx:', 'csv:', 'upload:', 'apply'));
foreach(array('xlsx','csv','upload') as $required){
    if(empty($options[$required])) throw new RuntimeException('Required: --xlsx=PATH --csv=PATH --upload=ID [--apply]');
}
$xlsx = waec_extract_listing_rows_from_xlsx($options['xlsx']);
$csv = waec_extract_listing_rows_from_rows(waec_read_csv_rows($options['csv']));
if($xlsx['error'] !== '' || $csv['error'] !== '') throw new RuntimeException('Source parsing failed: '.$xlsx['error'].' '.$csv['error']);
function repair_keyed($data){
    $out=array();
    foreach(array_merge($data['rows'],$data['withheld_rows']) as $row){
        $key=$row['candidate_no'].'|'.$row['subject_name'];
        if(isset($out[$key])) throw new RuntimeException('Duplicate source outcome.');
        $out[$key]=$row;
    }
    ksort($out); return $out;
}
$expected=repair_keyed($xlsx);
if(!$expected || $expected !== repair_keyed($csv)) throw new RuntimeException('Excel and CSV do not agree candidate by candidate.');
$candidates=array();$statusCandidates=array();$counts=array();
foreach($expected as $r){$candidates[$r['candidate_no']]=true;$counts[$r['grade']]=($counts[$r['grade']]??0)+1;}
foreach($xlsx['withheld_rows'] as $r)$statusCandidates[$r['candidate_no']]=true;
require __DIR__.'/../dbstring.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$id=(string)$options['upload'];$idEsc=mysqli_real_escape_string($con,$id);
$upload=mysqli_fetch_assoc(mysqli_query($con,"SELECT * FROM tblwaecupload WHERE uploadid='$idEsc'"));
if(!$upload) throw new RuntimeException('Upload not found.');
if(!in_array(strtolower($upload['originalname']),array(strtolower(basename($options['csv'])),strtolower(basename($options['xlsx']))),true)) throw new RuntimeException('Source filename does not match this upload.');
$storedCandidates=mysqli_fetch_all(mysqli_query($con,"SELECT candidate_no FROM tblwaecresult WHERE uploadid='$idEsc' UNION SELECT candidate_no FROM tblwaecwithheld WHERE uploadid='$idEsc'"),MYSQLI_ASSOC);
$oldIds=array_map('strval',array_column($storedCandidates,'candidate_no'));$newIds=array_map('strval',array_keys($candidates));sort($oldIds);sort($newIds);
if($oldIds !== $newIds) throw new RuntimeException('Candidate identities differ: repair needs review.');
echo json_encode(array('candidates'=>count($candidates),'graded'=>count($xlsx['rows']),'statuses'=>count($xlsx['withheld_rows']),'outcome_counts'=>$counts,'sources_identical'=>true),JSON_PRETTY_PRINT)."\n";
$currentRows=mysqli_fetch_all(mysqli_query($con,"SELECT candidate_no,student_name,gender,subject_name,grade FROM tblwaecresult WHERE uploadid='$idEsc' UNION ALL SELECT candidate_no,student_name,gender,subject_name,grade FROM tblwaecwithheld WHERE uploadid='$idEsc'"),MYSQLI_ASSOC);
$expectedRows=$expected;
foreach($expectedRows as &$r)unset($r['grade_point']);unset($r);
if($expectedRows === repair_keyed(array('rows'=>$currentRows,'withheld_rows'=>array()))){
    echo "The saved analysis already matches both sources exactly. No replacement needed.\n";
    exit;
}
if(!isset($options['apply'])){echo "Verified only; no database changes.\n";exit;}
$tables=array('tblwaecupload','tblwaecresult','tblwaecwithheld');
foreach($tables as $table){
    $engine=mysqli_fetch_assoc(mysqli_query($con,"SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table'"));
    if(!$engine || strcasecmp($engine['ENGINE'],'InnoDB') !== 0) throw new RuntimeException('Transactional tables required for repair.');
}
$suffix='_bak_'.date('YmdHis');$backups=array();
foreach($tables as $table){
    $backup=$table.$suffix;
    mysqli_query($con,"CREATE TABLE `$backup` LIKE `$table`");
    $backups[$table]=$backup;
}
$stored='waec_reconciled_'.preg_replace('/[^a-zA-Z0-9_-]/','_',$id).'.xlsx';
$path=__DIR__.'/../uploads/'.$stored;
if(file_exists($path)){
    if(hash_file('sha256',$path)!==hash_file('sha256',$options['xlsx'])) throw new RuntimeException('Recovery source path already contains different data.');
}elseif(!copy($options['xlsx'],$path)) throw new RuntimeException('Could not preserve the verified source.');
mysqli_begin_transaction($con);
try{
    mysqli_query($con,"SELECT uploadid FROM tblwaecupload WHERE uploadid='$idEsc' FOR UPDATE");
    foreach($backups as $table=>$backup){
        mysqli_query($con,"SELECT * FROM `$table` WHERE uploadid='$idEsc' FOR UPDATE");
        mysqli_query($con,"INSERT INTO `$backup` SELECT * FROM `$table` WHERE uploadid='$idEsc'");
        $old=mysqli_fetch_row(mysqli_query($con,"SELECT COUNT(*) FROM `$table` WHERE uploadid='$idEsc'"))[0];
        $saved=mysqli_fetch_row(mysqli_query($con,"SELECT COUNT(*) FROM `$backup` WHERE uploadid='$idEsc'"))[0];
        if($old !== $saved) throw new RuntimeException('Backup verification failed.');
    }
    mysqli_query($con,"DELETE FROM tblwaecresult WHERE uploadid='$idEsc'");
    mysqli_query($con,"DELETE FROM tblwaecwithheld WHERE uploadid='$idEsc'");
    $gradeInsert=mysqli_prepare($con,'INSERT INTO tblwaecresult(uploadid,candidate_no,student_name,gender,subject_name,grade,grade_point,datetimeentry) VALUES(?,?,?,?,?,?,?,NOW())');
    foreach($xlsx['rows'] as $r){
        mysqli_stmt_bind_param($gradeInsert,'ssssssi',$id,$r['candidate_no'],$r['student_name'],$r['gender'],$r['subject_name'],$r['grade'],$r['grade_point']);
        mysqli_stmt_execute($gradeInsert);
    }
    $statusInsert=mysqli_prepare($con,'INSERT INTO tblwaecwithheld(uploadid,candidate_no,student_name,gender,subject_name,grade,datetimeentry) VALUES(?,?,?,?,?,?,NOW())');
    foreach($xlsx['withheld_rows'] as $r){
        mysqli_stmt_bind_param($statusInsert,'ssssss',$id,$r['candidate_no'],$r['student_name'],$r['gender'],$r['subject_name'],$r['grade']);
        mysqli_stmt_execute($statusInsert);
    }
    $actual=mysqli_fetch_all(mysqli_query($con,"SELECT candidate_no,student_name,gender,subject_name,grade FROM tblwaecresult WHERE uploadid='$idEsc' UNION ALL SELECT candidate_no,student_name,gender,subject_name,grade FROM tblwaecwithheld WHERE uploadid='$idEsc'"),MYSQLI_ASSOC);
    $actualMap=repair_keyed(array('rows'=>$actual,'withheld_rows'=>array()));
    $compare=$expected;
    foreach($compare as &$r)unset($r['grade_point']);unset($r);
    if($compare !== $actualMap)throw new RuntimeException('Post-write reconciliation failed.');
    $gradeCount=count($xlsx['rows']);$candidateCount=count($candidates);$statusCount=count($xlsx['withheld_rows']);$statusCandidateCount=count($statusCandidates);
    $notes='Reconciled from matching Excel/CSV candidate records. X='.($counts['X']??0).'; W='.($counts['W']??0).'; *='.($counts['*']??0).'; P='.($counts['P']??0).'. Backup suffix: '.$suffix;
    $original=basename($options['xlsx']);
    $update=mysqli_prepare($con,"UPDATE tblwaecupload SET originalname=?,storedname=?,fileext='xlsx',totalrows=?,totalstudents=?,withheldrows=?,withheldstudents=?,notes=?,status='active' WHERE uploadid=?");
    mysqli_stmt_bind_param($update,'ssiiiiss',$original,$stored,$gradeCount,$candidateCount,$statusCount,$statusCandidateCount,$notes,$id);
    mysqli_stmt_execute($update);
    mysqli_commit($con);
    echo 'Repair committed. Recovery tables: '.implode(', ',array_values($backups))."\n";
}catch(Throwable $error){mysqli_rollback($con);throw $error;}
