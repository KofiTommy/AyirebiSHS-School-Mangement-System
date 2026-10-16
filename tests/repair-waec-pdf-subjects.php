<?php
// CLI-only, source-verified repair. Dry run unless --apply is explicitly supplied.
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require __DIR__.'/waec-pdf-subject-regression.php';
$options=getopt('',array('upload:','source:','apply'));
$id=$options['upload']??'';$supplied=$options['source']??'';
if(!$id || !$supplied)throw new RuntimeException('Required: --upload=ID --source=PDF [--apply]');
require __DIR__.'/../dbstring.php';mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
function subject_repair_query($con,$sql,$args=array()){
    $stmt=mysqli_prepare($con,$sql);if($args)mysqli_stmt_bind_param($stmt,str_repeat('s',count($args)),...$args);mysqli_stmt_execute($stmt);return $stmt;
}
function subject_repair_rows($con,$table,$id,$lock=false){
    $stmt=subject_repair_query($con,"SELECT * FROM `$table` WHERE uploadid=?".($lock?' FOR UPDATE':''),array($id));
    $rows=mysqli_fetch_all(mysqli_stmt_get_result($stmt),MYSQLI_ASSOC);mysqli_stmt_close($stmt);
    usort($rows,function($a,$b){return strcmp(json_encode($a),json_encode($b));});return $rows;
}
$tables=array('tblwaecupload','tblwaecresult','tblwaecwithheld');$before=array();
foreach($tables as $table)$before[$table]=subject_repair_rows($con,$table,$id);
if(count($before['tblwaecupload'])!==1)throw new RuntimeException('Upload not found.');
$upload=$before['tblwaecupload'][0];
$root=realpath(__DIR__.'/../uploads');$path=realpath($root.DIRECTORY_SEPARATOR.$upload['storedname']);
if(strtolower($upload['fileext'])!=='pdf'||!$path||dirname($path)!==$root)throw new RuntimeException('Saved PDF is unavailable.');
if(!is_file($supplied)||hash_file('sha256',$path)!==hash_file('sha256',$supplied))throw new RuntimeException('Supplied PDF does not match saved upload exactly.');
$error='';$raw=waec_extract_text_from_pdf($path,$error);if(!$raw)throw new RuntimeException('Source extraction failed.');
$ledger=waec_pdf_source_ledger($raw);$parsed=waec_extract_listing_rows_from_text($raw);
$outcomes=array();$candidates=array();$counts=array();
foreach(array_merge($parsed['rows'],$parsed['withheld_rows']) as $r){
    $key=$r['candidate_no'].'|'.$r['subject_name'];if(isset($outcomes[$key]))throw new RuntimeException('Duplicate parsed outcome.');
    $outcomes[$key]=$r['grade'];$candidates[$r['candidate_no']]=true;$counts[$r['subject_name']]=($counts[$r['subject_name']]??0)+1;
}
ksort($outcomes);if(!$outcomes||$outcomes!==$ledger)throw new RuntimeException('Parser does not agree with source ledger.');
$savedIdentities=array();$savedOutcomes=array();
foreach(array_merge($before['tblwaecresult'],$before['tblwaecwithheld']) as $r){
    $who=array($r['student_name'],$r['gender']);$candidate=$r['candidate_no'];
    if(isset($savedIdentities[$candidate])&&$savedIdentities[$candidate]!==$who)throw new RuntimeException('Saved candidate identity inconsistent; review required.');
    $savedIdentities[$candidate]=$who;$key=$candidate.'|'.$r['subject_name'];
    if(isset($savedOutcomes[$key]))throw new RuntimeException('Saved duplicate outcomes require review.');
    $savedOutcomes[$key]=$r['grade'];
}
$oldIds=array_keys($savedIdentities);$newIds=array_keys($candidates);sort($oldIds);sort($newIds);
if($oldIds!==$newIds)throw new RuntimeException('Candidate identities differ; repair stopped.');
ksort($savedOutcomes);
echo json_encode(array('upload'=>$id,'candidates'=>count($candidates),'previous_outcomes'=>count($savedOutcomes),'verified_outcomes'=>count($outcomes),'graded'=>count($parsed['rows']),'source_statuses'=>count($parsed['withheld_rows']),'source_hash_matches'=>true,'names_and_genders_preserved'=>true,'subjects'=>$counts),JSON_PRETTY_PRINT).PHP_EOL;
if($savedOutcomes===$outcomes){echo "Saved outcomes already match. No changes needed.\n";exit;}
if(!isset($options['apply'])){echo "Dry run only; no saved records changed.\n";exit;}
foreach($tables as $table){
    $stmt=subject_repair_query($con,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',array($table));
    $engine=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));if(!$engine||strcasecmp($engine['ENGINE'],'InnoDB')!==0)throw new RuntimeException('Transactional tables required.');
}
$suffix='_subj_bak_'.date('YmdHis').'_'.bin2hex(random_bytes(2));$backups=array();
foreach($tables as $table){$backup=$table.$suffix;mysqli_query($con,"CREATE TABLE `$backup` LIKE `$table`");$backups[$table]=$backup;}
mysqli_begin_transaction($con);
try{
    foreach($tables as $table){
        if(subject_repair_rows($con,$table,$id,true)!==$before[$table])throw new RuntimeException('Concurrent change detected; rerun validation.');
        $backup=$backups[$table];subject_repair_query($con,"INSERT INTO `$backup` SELECT * FROM `$table` WHERE uploadid=?",array($id));
        if(subject_repair_rows($con,$backup,$id)!==$before[$table])throw new RuntimeException('Backup verification failed.');
    }
    foreach(array('tblwaecresult','tblwaecwithheld') as $table)subject_repair_query($con,"DELETE FROM `$table` WHERE uploadid=?",array($id));
    foreach(array('rows'=>'tblwaecresult','withheld_rows'=>'tblwaecwithheld') as $type=>$table){
        foreach($parsed[$type] as $r){
            $who=$savedIdentities[$r['candidate_no']];$args=array($id,$r['candidate_no'],$who[0],$who[1],$r['subject_name'],$r['grade']);
            $extra=$type==='rows'?',grade_point':'';$values=$type==='rows'?',?':'';
            if($type==='rows')$args[]=$r['grade_point'];
            subject_repair_query($con,"INSERT INTO `$table`(uploadid,candidate_no,student_name,gender,subject_name,grade$extra,datetimeentry) VALUES(?,?,?,?,?,?$values,NOW())",$args);
        }
    }
    $actual=array();foreach(array('tblwaecresult','tblwaecwithheld') as $table){foreach(subject_repair_rows($con,$table,$id) as $r){
        if(array($r['student_name'],$r['gender'])!==$savedIdentities[$r['candidate_no']])throw new RuntimeException('Identity preservation failed.');
        if($table==='tblwaecresult' && (int)$r['grade_point']!==waec_points($r['grade']))throw new RuntimeException('Grade points verification failed.');
        $key=$r['candidate_no'].'|'.$r['subject_name'];if(isset($actual[$key]))throw new RuntimeException('Post-write duplicate.');$actual[$key]=$r['grade'];
    }}ksort($actual);if($actual!==$outcomes)throw new RuntimeException('Post-write outcome reconciliation failed.');
    $statusCandidates=array_unique(array_column($parsed['withheld_rows'],'candidate_no'));
    $notes=trim((string)$upload['notes'])."\nPDF wrapped-subject repair verified against original source; recovery suffix: ".$suffix;
    subject_repair_query($con,'UPDATE tblwaecupload SET totalrows=?,totalstudents=?,withheldrows=?,withheldstudents=?,notes=? WHERE uploadid=?',array(count($parsed['rows']),count($candidates),count($parsed['withheld_rows']),count($statusCandidates),$notes,$id));
    mysqli_commit($con);echo 'Repair committed. Recovery tables: '.implode(', ',$backups).PHP_EOL;
}catch(Throwable $e){mysqli_rollback($con);throw $e;}
