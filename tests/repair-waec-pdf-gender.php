<?php
// Explicit, backed-up gender-only repair. CLI only; dry run unless --apply is supplied.
if(PHP_SAPI !== 'cli'){ http_response_code(403); exit; }
require __DIR__.'/waec-analysis-regression.php';
require_once __DIR__.'/../waec-comparison-utils.php';
$options=getopt('',array('upload:','apply'));
$id=$options['upload'] ?? '';
if(!is_string($id) || $id==='') throw new RuntimeException('Usage: php tests/repair-waec-pdf-gender.php --upload=ID [--apply]');
require __DIR__.'/../dbstring.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$stmt=mysqli_prepare($con,'SELECT * FROM tblwaecupload WHERE uploadid=?');
mysqli_stmt_bind_param($stmt,'s',$id); mysqli_stmt_execute($stmt);
$upload=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
if(!$upload || strtolower($upload['fileext'])!=='pdf') throw new RuntimeException('Select an existing PDF upload.');
$directory=realpath(__DIR__.'/../uploads');
$path=realpath($directory.DIRECTORY_SEPARATOR.$upload['storedname']);
if(!$path || dirname($path)!==$directory || !is_file($path)) throw new RuntimeException('Original PDF must exist directly inside uploads.');
$source=waec_extract_listing_rows_from_pdf($path);
if($source['error']!=='' || (!$source['rows'] && !$source['withheld_rows'])) throw new RuntimeException('PDF extraction failed.');
$expected=array();
foreach(array_merge($source['rows'],$source['withheld_rows']) as $row){
    $candidate=$row['candidate_no']; $gender=waec_normalize_gender($row['gender']);
    if($candidate==='' || $gender==='') throw new RuntimeException('Unresolved source identity or gender; review required.');
    if(isset($expected[$candidate]) && $expected[$candidate]!==$gender) throw new RuntimeException('Conflicting source gender; review required.');
    $expected[$candidate]=$gender;
}
function pdf_gender_snapshot($con,$id,$lock=false){
    $snapshot=array();
    foreach(array('tblwaecresult','tblwaecwithheld') as $table){
        $stmt=mysqli_prepare($con,"SELECT * FROM `$table` WHERE uploadid=?".($lock ? ' FOR UPDATE':''));
        mysqli_stmt_bind_param($stmt,'s',$id); mysqli_stmt_execute($stmt);
        $snapshot[$table]=mysqli_fetch_all(mysqli_stmt_get_result($stmt),MYSQLI_ASSOC); mysqli_stmt_close($stmt);
    }
    return $snapshot;
}
function pdf_gender_sorted($snapshot,$ignoreGender=false){
    foreach($snapshot as &$rows){
        if($ignoreGender) foreach($rows as &$row){ unset($row['gender']); } unset($row);
        usort($rows,function($a,$b){ return strcmp(json_encode($a),json_encode($b)); });
    }unset($rows);
    return $snapshot;
}
$before=pdf_gender_snapshot($con,$id);
$saved=array_merge($before['tblwaecresult'],$before['tblwaecwithheld']);
if(wac_analyze($saved)['fingerprint']!==wac_analyze(array_merge($source['rows'],$source['withheld_rows']))['fingerprint']){
    throw new RuntimeException('Candidate/subject/outcome identities differ from saved data. Repair stopped.');
}
$changedCandidates=array(); $changes=0;
foreach($saved as $row){
    $candidate=$row['candidate_no'];
    if(!isset($expected[$candidate])) throw new RuntimeException('Saved candidate absent from source.');
    if($row['gender']===$expected[$candidate]) continue;
    if(trim((string)$row['gender'])!=='') throw new RuntimeException('Existing gender conflicts with source. Manual review required.');
    $changedCandidates[$candidate]=true; $changes++;
}
echo json_encode(array('candidates'=>count($expected),'source_genders'=>array_count_values($expected),
    'candidates_to_repair'=>count($changedCandidates),'rows_to_update'=>$changes,'outcomes_identical'=>true),JSON_PRETTY_PRINT).PHP_EOL;
if(!$changes){ echo "Saved genders already match the PDF. No changes needed.\n"; exit; }
if(!isset($options['apply'])){ echo "Dry run only; no database changes.\n"; exit; }
$tables=array('tblwaecresult','tblwaecwithheld'); $backups=array();
foreach($tables as $table){
    $engine=mysqli_fetch_assoc(mysqli_query($con,"SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table'"));
    if(!$engine || strcasecmp($engine['ENGINE'],'InnoDB')!==0) throw new RuntimeException('Transactional result tables required.');
}
$suffix='_gender_bak_'.date('YmdHis').'_'.bin2hex(random_bytes(2));
foreach($tables as $table){
    $backup=$table.$suffix; mysqli_query($con,"CREATE TABLE `$backup` LIKE `$table`"); $backups[$table]=$backup;
}
mysqli_begin_transaction($con);
try{
    if(pdf_gender_sorted(pdf_gender_snapshot($con,$id,true))!==pdf_gender_sorted($before)) throw new RuntimeException('Saved data changed during validation; rerun.');
    foreach($backups as $table=>$backup){
        $stmt=mysqli_prepare($con,"INSERT INTO `$backup` SELECT * FROM `$table` WHERE uploadid=?");
        mysqli_stmt_bind_param($stmt,'s',$id); mysqli_stmt_execute($stmt);
        if(mysqli_stmt_affected_rows($stmt)!==count($before[$table])) throw new RuntimeException('Backup row count failed.');
        mysqli_stmt_close($stmt);
        $verify=mysqli_prepare($con,"SELECT * FROM `$backup`"); mysqli_stmt_execute($verify);
        $backupRows=mysqli_fetch_all(mysqli_stmt_get_result($verify),MYSQLI_ASSOC); mysqli_stmt_close($verify);
        if(pdf_gender_sorted(array($table=>$backupRows))!==pdf_gender_sorted(array($table=>$before[$table]))) throw new RuntimeException('Backup verification failed.');
        $stmt=mysqli_prepare($con,"UPDATE `$table` SET gender=? WHERE uploadid=? AND candidate_no=? AND (gender='' OR gender IS NULL)");
        foreach($changedCandidates as $candidate=>$unused){
            $candidate=(string)$candidate; $gender=$expected[$candidate];
            mysqli_stmt_bind_param($stmt,'sss',$gender,$id,$candidate); mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
    }
    $after=pdf_gender_snapshot($con,$id);
    if(pdf_gender_sorted($before,true)!==pdf_gender_sorted($after,true)) throw new RuntimeException('A non-gender field changed; rolling back.');
    foreach(array_merge($after['tblwaecresult'],$after['tblwaecwithheld']) as $row){
        if($row['gender']!==$expected[$row['candidate_no']]) throw new RuntimeException('Gender reconciliation failed; rolling back.');
    }
    mysqli_commit($con);
    echo 'Gender-only repair committed. Recovery tables: '.implode(', ',$backups).PHP_EOL;
}catch(Throwable $error){ mysqli_rollback($con); throw $error; }
