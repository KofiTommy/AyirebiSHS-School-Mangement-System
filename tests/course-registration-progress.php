<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require __DIR__.'/../course-registration-progress-utils.php';
require __DIR__.'/../dbstring.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$schemas=array(
    'tblclassentry'=>'class_entryid VARCHAR(60),class_name VARCHAR(180)',
    'tblcourseregwindow'=>'windowid VARCHAR(40),classid VARCHAR(60),batchid VARCHAR(60),academicyear VARCHAR(10),termname VARCHAR(10),status VARCHAR(20),openfrom DATETIME NULL,closeat DATETIME NULL',
    'tbltermregistry'=>'userid VARCHAR(60),class_entryid VARCHAR(60),batchid VARCHAR(60),academicyear VARCHAR(10),termname VARCHAR(10),status VARCHAR(20),datetimeentry DATETIME',
    'tblsystemuser'=>'userid VARCHAR(60),systemtype VARCHAR(30),status VARCHAR(20)',
    'tblstudentcourseregstatus'=>'windowid VARCHAR(40),userid VARCHAR(60),status VARCHAR(20)'
);
foreach($schemas as $table=>$columns){mysqli_query($con,"CREATE TEMPORARY TABLE $table ($columns)");}
for($i=1;$i<=18;$i++){mysqli_query($con,"INSERT INTO tblclassentry VALUES('c$i','Class $i')");}
mysqli_query($con,"INSERT INTO tblcourseregwindow VALUES('w1','c1','b1','2027','1','open',NULL,NULL),('w2','c2','b1','2027','1','closed',NULL,NULL),('other','c1','b1','2026','1','open',NULL,NULL)");
mysqli_query($con,"INSERT INTO tblsystemuser VALUES('s1','Student','active'),('s2','Student','active'),('s3','Student','inactive'),('t1','Teacher','active'),('s4','Student','active'),('s5','Student','active'),('s6','Student','active')");
mysqli_query($con,"INSERT INTO tbltermregistry VALUES
('s1','c1','b1','2027','1','active','2027-01-01'),('s1','c1','b1','2027','1','active','2027-01-01'),
('s2','c1','b1','2027','1','active','2027-01-01'),('s3','c1','b1','2027','1','active','2027-01-01'),
('t1','c1','b1','2027','1','active','2027-01-01'),('s4','c1','b2','2027','1','active','2027-01-01'),
('s5','c1','b1','2027','2','active','2027-01-01'),('s6','c3','b1','','1','active','2027-01-01')");
mysqli_query($con,"INSERT INTO tblstudentcourseregstatus VALUES('w1','s1','submitted'),('w1','s1','submitted'),('w1','s2','draft'),('w1','s3','submitted'),('w1','s4','submitted'),('other','s2','submitted')");
mysqli_query($con,"INSERT INTO tbltermregistry VALUES
('s4','c4','b2','2027','1','active','2027-01-01'),
('s5','c5','b1','2026','1','active','2026-01-01'),
('s5','c6','b1','2027','2','active','2027-01-01'),
('s3','c7','b1','2027','1','active','2027-01-01')");
for($i=8;$i<=18;$i++){
    mysqli_query($con,"INSERT INTO tblsystemuser VALUES('extra$i','Student','active')");
    mysqli_query($con,"INSERT INTO tbltermregistry VALUES('extra$i','c$i','b1','2027','1','active','2027-01-01')");
}
mysqli_query($con,"INSERT INTO tblcourseregwindow VALUES('w8','c8','b1','2027','1','open',NULL,NULL)");
mysqli_query($con,"INSERT INTO tblstudentcourseregstatus VALUES('w8','extra8','submitted')");
$checks=0;
function progress_check($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
$rows=course_registration_all_class_progress($con,'b1','2027','1');$map=array_column($rows,null,'classid');
progress_check(count($rows)===13,'All matching classes included beyond recent-ten limit');
progress_check($map['c1']['eligible_count']===2,'Distinct active students only in selected scope');
progress_check($map['c1']['submitted_count']===1,'Distinct eligible submissions exclude drafts, stale windows and other batches');
progress_check($map['c1']['outstanding_count']===1 && $map['c1']['progress_percent']==50,'Pending and percentage correct');
progress_check(!isset($map['c2']),'Window alone does not include a class without eligible enrolments');
foreach(array('c4','c5','c6','c7') as $excluded){progress_check(!isset($map[$excluded]),'Other batch/year/semester or inactive students excluded: '.$excluded);}
$batchTwo=array_column(course_registration_all_class_progress($con,'b2','2027','1'),null,'classid');
progress_check(count($batchTwo)===2 && isset($batchTwo['c1'],$batchTwo['c4']),'Changing batch loads only its own classes');
progress_check(course_registration_all_class_progress($con,'missing','2027','1')===array(),'Batch with no enrolments has no classes');
progress_check($map['c3']['windowid']===null && $map['c3']['eligible_count']===1 && $map['c3']['outstanding_count']===1,'Not-set-up classes retain eligible and pending counts with legacy year fallback');
progress_check(course_registration_all_class_progress($con,'','2027','1')===array(),'Incomplete scope not queried');
progress_check(course_registration_all_class_progress($con,'b1','2027','3')===array(),'Invalid semester rejected');
$other=course_registration_all_class_progress($con,"b1' OR 1=1 --",'2027','1');
progress_check(array_sum(array_column($other,'eligible_count'))===0,'Batch parameter safely bound');
// Exercise the real template using isolated data and the page's existing URL helpers.
$source=file_get_contents(__DIR__.'/../course-registration-admin.php');
foreach(array(array('function cra_safe(', 'function cra_flash('),array('function cra_scope_link(', 'function cra_export_csv(')) as $bounds){$start=strpos($source,$bounds[0]);eval(substr($source,$start,strpos($source,$bounds[1],$start)-$start));}
$progressMode='all';$progressReady=true;$allClassProgress=$rows;$batchOptions=array(array('batchid'=>'b1','batch'=>'Batch 1'));
$selectedClassId='c1';$selectedBatchId='b1';$selectedAcademicYear='2027';$selectedTermName='1';
ob_start();include __DIR__.'/../course-registration-progress-view.php';$html=ob_get_clean();
progress_check(substr_count($html,'class="cr-progress-card"')===13,'Only matching classes rendered');
progress_check(strpos($html,'Not set up')!==false && strpos($html,'50% complete')!==false,'Status and progress labels rendered');
progress_check(strpos($html,'classid=c15&amp;batchid=b1&amp;academicyear=2027&amp;termname=1&amp;progressview=all')!==false,'Load link preserves monitoring scope');
progress_check($progressTotal===14 && $progressSubmitted===2 && $progressPending===12 && $progressPercent===14.3,'Summary totals and weighted completion match selected enrolments');
progress_check(substr_count($html,'data-progress-state="complete"')===1 && substr_count($html,'data-progress-state="pending"')===12,'Completed and pending filters classify cards correctly');
progress_check(strpos($html,'data-tone="not-started"')!==false && strpos($html,'data-tone="pending"')!==false,'Not started and in-progress labels available without colour alone');
progress_check(strpos($html,'class="cr-progress-tools" hidden')!==false,'Without JavaScript all cards remain visible and unusable filters stay hidden');
if(in_array('--preview',$argv,true)){
    $dir=sys_get_temp_dir().'/course-progress-'.bin2hex(random_bytes(5));mkdir($dir);
    $css=file_get_contents(__DIR__.'/../css/course-registration.css').file_get_contents(__DIR__.'/../css/course-registration-progress.css');
    $html.='<script>'.file_get_contents(__DIR__.'/../js/course-registration-progress.js').'</script>';
    file_put_contents($dir.'/progress.html','<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.$css.'</style></head><body class="cr-page cr-admin-dashboard"><main class="cr-shell"><div class="cr-layout"><section class="cr-panel"><h1>Registration Window</h1><p>Synthetic preview — existing setup panel remains here.</p></section><aside class="cr-panel cr-panel--side">'.$html.'</aside></div></main></body></html>');
    echo 'Preview: '.$dir.PHP_EOL;
}
echo 'PASS: '.$checks.' progress checks; temporary tables only.'.PHP_EOL;
