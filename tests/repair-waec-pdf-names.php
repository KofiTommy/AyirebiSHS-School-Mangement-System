<?php
// Names-only repair from the original saved PDF. CLI only; defaults to a dry run.
if(PHP_SAPI !== 'cli'){ http_response_code(403); exit; }
require __DIR__.'/waec-analysis-regression.php';
require_once __DIR__.'/../waec-comparison-utils.php';
$options=getopt('',array('upload:','apply'));
$id=$options['upload'] ?? '';
if(!is_string($id) || $id==='') throw new RuntimeException('Usage: php tests/repair-waec-pdf-names.php --upload=ID [--apply]');
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
if($source['error']!=='' || (!$source['rows'] && !$source['withheld_rows'])) throw new RuntimeException('PDF extraction failed; no repair performed.');
$expected=array();
foreach(array_merge($source['rows'],$source['withheld_rows']) as $row){
    $candidate=$row['candidate_no']; $name=trim(preg_replace('/\s+/',' ',$row['student_name']));
    if($name==='' || $name==='Unknown') throw new RuntimeException('Unresolved source name; repair stopped.');
    if(isset($expected[$candidate]) && $expected[$candidate]!==$name) throw new RuntimeException('Conflicting names in source; repair stopped.');
    $expected[$candidate]=$name;
}
// Include every column in the snapshot: verification permits student_name changes only.
function pdf_name_snapshot($con,$id,$lock=false){
    $snapshot=array();
    foreach(array('tblwaecresult','tblwaecwithheld') as $table){
        $stmt=mysqli_prepare($con,"SELECT * FROM $table WHERE uploadid=?".($lock ? ' FOR UPDATE':''));
        mysqli_stmt_bind_param($stmt,'s',$id); mysqli_stmt_execute($stmt);
        $snapshot[$table]=mysqli_fetch_all(mysqli_stmt_get_result($stmt),MYSQLI_ASSOC); mysqli_stmt_close($stmt);
    }
    return $snapshot;
}
function pdf_name_sorted($snapshot,$ignoreName=false){
    foreach($snapshot as &$rows){
        if($ignoreName) foreach($rows as &$row){ unset($row['student_name']); } unset($row);
        usort($rows,function($a,$b){ return strcmp(json_encode($a),json_encode($b)); });
    }unset($rows);
    return $snapshot;
}
$before=pdf_name_snapshot($con,$id);
$saved=array_merge($before['tblwaecresult'],$before['tblwaecwithheld']);
if(wac_analyze($saved)['fingerprint']!==wac_analyze(array_merge($source['rows'],$source['withheld_rows']))['fingerprint']){
    throw new RuntimeException('Source candidate/subject/outcome identities differ from saved results. No names changed.');
}
$changedCandidates=array(); $changes=0; $shortAfter=0;
foreach($expected as $name) if(strpos($name,' ')===false) $shortAfter++;
foreach($saved as $row){
    $candidate=$row['candidate_no']; $old=trim(preg_replace('/\s+/',' ',$row['student_name']));
    if(!isset($expected[$candidate])) throw new RuntimeException('Saved candidate not present in source.');
    $name=$expected[$candidate];
    if($row['student_name']===$name) continue;
    // Permit source-backed extensions, removal of leaked demographic text, or a
    // previously unresolved name. Candidate number and every outcome must agree.
    $cleanOld=waec_clean_name_fragment($old);
    if($old!=='Unknown' && ($cleanOld==='' || (strcasecmp($name,$cleanOld)!==0 && stripos($name,$cleanOld.' ')!==0))){
        throw new RuntimeException('A name is not a safe source-backed correction; manual review required.');
    }
    $changedCandidates[$candidate]=true; $changes++;
}
echo json_encode(array('candidates'=>count($expected),'candidates_to_repair'=>count($changedCandidates),
    'rows_to_update'=>$changes,'single_word_names_remaining'=>$shortAfter,'outcomes_identical'=>true),JSON_PRETTY_PRINT).PHP_EOL;
if(!$changes){ echo "Saved names already match the original PDF. No changes needed.\n"; exit; }
if(!isset($options['apply'])){ echo "Dry run only; no database changes.\n"; exit; }
$tables=array('tblwaecresult','tblwaecwithheld'); $backups=array();
foreach($tables as $table){
    $engine=mysqli_fetch_assoc(mysqli_query($con,"SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table'"));
    if(!$engine || strcasecmp($engine['ENGINE'],'InnoDB')!==0) throw new RuntimeException('Transactional result tables required.');
}
$suffix='_names_bak_'.date('YmdHis').'_'.bin2hex(random_bytes(2));
foreach($tables as $table){
    $backup=$table.$suffix; mysqli_query($con,"CREATE TABLE `$backup` LIKE `$table`"); $backups[$table]=$backup;
}
mysqli_begin_transaction($con);
try{
    $locked=pdf_name_snapshot($con,$id,true);
    if(pdf_name_sorted($locked)!==pdf_name_sorted($before)) throw new RuntimeException('Saved results changed during validation; rerun repair.');
    foreach($backups as $table=>$backup){
        $stmt=mysqli_prepare($con,"INSERT INTO `$backup` SELECT * FROM `$table` WHERE uploadid=?");
        mysqli_stmt_bind_param($stmt,'s',$id); mysqli_stmt_execute($stmt);
        if(mysqli_stmt_affected_rows($stmt)!==count($before[$table])) throw new RuntimeException('Backup row count failed.');
        mysqli_stmt_close($stmt);
        // Use the same prepared-result type handling as the original snapshot.
        $verify=mysqli_prepare($con,"SELECT * FROM `$backup`"); mysqli_stmt_execute($verify);
        $backupRows=mysqli_fetch_all(mysqli_stmt_get_result($verify),MYSQLI_ASSOC); mysqli_stmt_close($verify);
        if(pdf_name_sorted(array($table=>$backupRows))!==pdf_name_sorted(array($table=>$before[$table]))) throw new RuntimeException('Backup verification failed.');
        $stmt=mysqli_prepare($con,"UPDATE `$table` SET student_name=? WHERE uploadid=? AND candidate_no=?");
        foreach($changedCandidates as $candidate=>$unused){
            $candidate=(string)$candidate; $name=$expected[$candidate];
            mysqli_stmt_bind_param($stmt,'sss',$name,$id,$candidate); mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);
    }
    $after=pdf_name_snapshot($con,$id);
    if(pdf_name_sorted($before,true)!==pdf_name_sorted($after,true)) throw new RuntimeException('A non-name field changed; rolling back.');
    foreach(array_merge($after['tblwaecresult'],$after['tblwaecwithheld']) as $row){
        if($row['student_name']!==$expected[$row['candidate_no']]) throw new RuntimeException('Name reconciliation failed; rolling back.');
    }
    mysqli_commit($con);
    echo 'Names-only repair committed. Recovery tables: '.implode(', ',$backups).PHP_EOL;
}catch(Throwable $error){ mysqli_rollback($con); throw $error; }
