<?php
if(PHP_SAPI !== 'cli'){ http_response_code(403); exit; }
require_once __DIR__.'/../waec-comparison-utils.php';
error_reporting(E_ALL);
set_error_handler(function($severity,$message,$file,$line){ throw new ErrorException($message,0,$severity,$file,$line); });
$checks=0;
function check($condition,$label){
    global $checks; $checks++;
    if(!$condition){ fwrite(STDERR,'FAIL: '.$label.PHP_EOL); exit(1); }
}
function rejects($callback,$label){
    try{ $callback(); check(false,$label); }catch(InvalidArgumentException $e){ check(true,$label); }
}
function record($candidate,$subject,$grade){ return array('candidate_no'=>$candidate,'student_name'=>'Example '.$candidate,'subject_name'=>$subject,'grade'=>$grade); }
$rows=array(
    record('001','English Language','A1'), record('001','English','A1'),
    record('002','English','F9'), record('003','English','X'), record('004','English','W'),
    record('005','English','*'), record('006','English','P'),
    record('001','Core Maths','C6'), record('002','Mathematics (Core)','D7'),
    record('003','Core Maths','A1'), record('003','Core Mathematics','F9'),
    record('001','Biology','B3'), record('006','Biology','UNKNOWN'),
    array('candidate_no'=>'','student_name'=>'','subject_name'=>'English','grade'=>'A1')
);
$analysis=wac_analyze($rows); $total=$analysis['overall']; $english=$analysis['subjects']['ENGLISH LANG'];
check($analysis['candidates']===6,'candidates include status-only candidates');
check($analysis['duplicates']===1,'identical aliases count once');
check($analysis['unidentified']===1,'unidentified records flagged');
check($total['graded']===5 && $total['credits']===3,'only unambiguous real grades count');
check($total['credit_rate']===60.0,'overall credit denominator excludes statuses');
check($total['average']===5.2,'grade points calculated from canonical grade');
check($total['conflicts']===1 && $total['unknown']===1,'conflicting grades and unknown codes flagged');
check($total['X']===1 && $total['W']===1 && $total['*']===1 && $total['P']===1,'all statuses remain separate');
check($english['credit_rate']===50.0 && $english['recorded']===6,'subject denominator and coverage');
check($english['missing']===0,'status markers are not missing outcomes');
check($analysis['subjects']['MATHEMATICS(CORE)']['missing']===3,'core gaps across upload candidates');
check($analysis['subjects']['SOCIAL STUDIES']['missing']===6,'entire missing core subject flagged');
check($analysis['subjects']['BIOLOGY']['missing']===null,'elective registration never inferred');
check(wac_analyze(array())['overall']['credit_rate']===null,'empty batch rate is unavailable');
check(wac_analyze(array(record('1','English','X')))['overall']['credit_rate']===null,'absent-only rate is unavailable');
check(wac_analyze(array(record('1','English','F9')))['overall']['credit_rate']===0.0,'real zero credit rate is retained');
check(wac_rate(null)==='N/A' && wac_rate(0)==='0.0%','zero and missing display differently');
check(wac_delta(null,90)===null && wac_delta(60,72)===12,'percentage point calculation');
check(wac_delta_text(-5)==='-5.0 pp' && wac_delta_text(0)==='No change','delta labels');
$reversed=array_reverse($rows);
check(wac_analyze($reversed)['fingerprint']===$analysis['fingerprint'],'row order independent duplicate fingerprint');
$withoutDuplicate=$rows; unset($withoutDuplicate[1]);
check(wac_analyze($withoutDuplicate)['fingerprint']===$analysis['fingerprint'],'duplicate rows do not change fingerprint');
$different=$rows; $different[2]['grade']='C6';
check(wac_analyze($different)['fingerprint']!==$analysis['fingerprint'],'changed result changes fingerprint');
check(wac_analyze(array(record('001','English','A1'),record('1','English','F9')))['candidates']===2,'leading zeros retained in candidate identity');
$small=wac_analyze(array(record('s1','English','A1'),record('s2','English','F9')));
$large=wac_analyze(array(record('b1','English','A1'),record('b2','English','F9'),record('b3','English','C6'),record('b4','English','F9')));
check($small['overall']['credit_rate']===$large['overall']['credit_rate'],'unequal cohort sizes compare by percentages');
check(wac_metadata(array('exam_year'=>'2025','exam_sitting'=>' School candidates ','batch_label'=>' Batch A '))['batch_label']==='Batch A','metadata trimmed');
rejects(function(){wac_metadata(array('exam_year'=>'2025'));},'metadata incomplete');
rejects(function(){wac_metadata(array('exam_year'=>array('2025'),'exam_sitting'=>'A','batch_label'=>'B'));},'array input rejected');
rejects(function(){wac_metadata(array('exam_year'=>'2025x','exam_sitting'=>'A','batch_label'=>'B'));},'malformed year rejected');
rejects(function(){wac_metadata(array('exam_year'=>'2025','exam_sitting'=>str_repeat('x',81),'batch_label'=>'B'));},'oversized sitting rejected');
$uploads=array();
foreach(array('A'=>2023,'B'=>2024,'C'=>2025) as $id=>$year){
    $uploads[$id]=array('uploadid'=>$id,'exam_year'=>$year,'exam_sitting'=>'School candidates','batch_label'=>'Batch '.$id,'branchid'=>'1','originalname'=>'results.xlsx','datetimeentry'=>'2026-10-11 00:00:00');
}
$selected=wac_selection(array('C','A','B'),$uploads);
check(array_column($selected,'uploadid')===array('A','B','C'),'sort by exam year rather than selection/upload order');
check(count(wac_selection(array('A','B',''),$uploads))===2,'optional third selector');
rejects(function()use($uploads){wac_selection(array('A'),$uploads);},'requires two batches');
rejects(function()use($uploads){wac_selection(array('A','A'),$uploads);},'same upload rejected');
rejects(function()use($uploads){wac_selection(array('A','B','C','D'),$uploads);},'more than three rejected');
rejects(function()use($uploads){wac_selection(array('A','secret'),$uploads);},'unavailable upload rejected');
rejects(function()use($uploads){wac_selection(array('A',array('B')),$uploads);},'nested selector rejected');
$unlabelled=$uploads; $unlabelled['B']['exam_year']=null;
rejects(function()use($unlabelled){wac_selection(array('A','B'),$unlabelled);},'unlabelled legacy upload rejected');
check(wac_accessible($uploads['A'],array('BRANCHID'=>'1')),'own branch available');
check(!wac_accessible($uploads['A'],array('BRANCHID'=>'2')),'other branch rejected');
check(!wac_accessible(array('branchid'=>''),array('BRANCHID'=>'')),'blank branch fails closed');
check(wac_accessible($uploads['A'],array('ACCESSLEVEL'=>'administrator','SYSTEMTYPE'=>'super_user')),'super admin cross branch');
check(wac_csv_cell('=HYPERLINK("example")')[0]==="'",'CSV formula protection');
check(wac_h('<script>')==='&lt;script&gt;','HTML escaping');

// Student-level metrics use the whole observed cohort, not graded-entry totals.
$achievementRows=array();
foreach(array('a','b') as $candidate){
    foreach(array_merge(wac_core(),array('BIOLOGY','CHEMISTRY')) as $subject){
        $achievementRows[]=record($candidate,$subject,'C6');
    }
}
$achievementRows[]=record('a','English Language','C6'); // alias duplicate, not a seventh credit
$achievementRows[]=record('b','SOCIAL STUDIES','W'); // conflict removes one confirmed credit
$achievementRows[]=record('c','English','X');
$achievementRows[]=record('d','English','A1');
$achievementRows[]=record('d','Core Maths','D7');
$achievementRows[]=record('d','Integrated Science','P');
$studentAnalysis=wac_analyze($achievementRows);
$achievement=$studentAnalysis['achievement'];
check($achievement['population']===4,'achievement cohort includes status-only students');
check($achievement['metrics']['six_credits']['achieved']===1,'six distinct unambiguous credits required');
check($achievement['metrics']['six_credits']['rate']===25.0,'student rate uses four students, not number of entries');
check($achievement['metrics']['six_credits']['not_confirmed']===3,'not confirmed includes incomplete and below-credit records');
check($achievement['metrics']['english']['achieved']===3,'single core credits count each student once');
check($achievement['metrics']['english_maths']['achieved']===2,'paired credits belong to same student');
check($achievement['metrics']['all_core']['achieved']===1,'all four cores require four credits, not science OR social');
check($achievement['coverage']===array('fully_graded_core'=>1,'missing_core'=>2,'ungraded_core'=>3),'coverage counts students and overlapping groups accurately');
check($achievement['credit_buckets'][6]===1 && $achievement['credit_buckets'][5]===1 && $achievement['credit_buckets'][1]===1 && $achievement['credit_buckets'][0]===1,'observed credit histogram');
check(array_sum($achievement['credit_buckets'])===4,'credit buckets partition the whole cohort');
check(wac_analyze(array())['achievement']['metrics']['six_credits']['rate']===null,'empty achievement cohort has N/A not zero');
$statusOnly=wac_analyze(array(record('x','English','W'),record('y','Core Maths','*')));
check($statusOnly['achievement']['metrics']['six_credits']['rate']===0.0 && $statusOnly['achievement']['metrics']['six_credits']['not_confirmed']===2,'status-only cohort has zero confirmed achievement, not zero exam performance');
$manyCredits=array();
foreach(array_merge(wac_core(),array('BIOLOGY','CHEMISTRY','PHYSICS','GEOGRAPHY','ECONOMICS')) as $subject) $manyCredits[]=record('nine',$subject,'A1');
check(wac_analyze($manyCredits)['achievement']['credit_buckets']['8+']===1,'eight-plus bucket includes nine credits');
$differentCores=wac_analyze(array(record('one','English','A1'),record('two','Core Maths','A1')));
check($differentCores['achievement']['metrics']['english_maths']['achieved']===0,'credits cannot be combined across candidates');
$nameOnly=record('','English','B3'); $nameOnly['student_name']='Named candidate';
check(wac_analyze(array($nameOnly,$nameOnly))['achievement']['metrics']['english']['achieved']===1,'existing name fallback deduplicates students');
check(wac_analyze(array_reverse($achievementRows))['achievement']===$achievement,'student metrics independent of input order');

$batches=$selected;
$batches[0]['analysis']=$small; $batches[1]['analysis']=$large; $batches[2]['analysis']=$analysis;
$summary=wac_summary($batches);
check(count($summary)>=2 && strpos($summary[0],'No change')!==false,'adjacent batch narrative');
check(strpos(implode(' ',$summary),'Confirmed achievements only')!==false,'narrative qualifies achievement changes');
$achievementBatches=$selected;
$achievementBatches[0]['analysis']=$studentAnalysis;
$achievementBatches[1]['analysis']=wac_analyze($manyCredits);
$achievementBatches[2]['analysis']=$statusOnly;
$achievementCsv=wac_achievement_csv_rows($achievementBatches);
$sixCsv=array_values(array_filter($achievementCsv,function($row){ return ($row[0] ?? '')==='At least six credits across distinct subjects'; }));
check(count($sixCsv)===3,'CSV includes every selected batch');
check($sixCsv[0][5]===1 && $sixCsv[0][6]===4 && $sixCsv[0][7]===25.0 && $sixCsv[0][8]===3,'CSV matches student counts, denominator and rate');
check($sixCsv[1][9]===75.0 && $sixCsv[2][9]===-100.0,'CSV calculates adjacent achievement changes');

// Exercise the actual view with synthetic fixtures, without running authentication or writes.
$source=file_get_contents(__DIR__.'/../waec-comparison.php');
$view=substr($source,strpos($source,'<!DOCTYPE html>'));
$view=str_replace(array("<?php include('links.php'); ?>","<?php include('menu.php'); ?>"),'',$view);
$ready=$uploads; $selectedValues=array('A','B','C'); $warnings=array('Synthetic coverage warning');
$errors=array(); $notice=''; $csrf='fixture-token'; $editingId=''; $editingValues=array();
$subjects=array_keys($analysis['subjects']); $exportUrl='waec-comparison.php?export=csv';
$batches[0]['batch_label']='<script>alert(1)</script>';
ob_start(); eval('?>'.$view); $html=ob_get_clean();
check(strpos($html,'<script>alert(1)</script>')===false && strpos($html,'&lt;script&gt;')!==false,'view escapes batch labels');
check(strpos($html,'id="wac-print"')!==false && strpos($html,'Download CSV')!==false,'report includes print and CSV controls');
check(strpos($html,'Coverage &amp; ungraded outcomes')!==false,'coverage visible in report');
check(strpos($html,'id="student-achievement"')!==false && strpos($html,'Not confirmed does not mean failed.')!==false,'achievement table and qualifications rendered');
check(strpos($html,'Observed credit counts per student')!==false,'student credit buckets rendered for printing');
if(in_array('--preview',$argv,true)){
    // Disposable visual fixture: synthetic records only, never a public PHP endpoint.
    $previewDir=sys_get_temp_dir().DIRECTORY_SEPARATOR.'waec-comparison-'.bin2hex(random_bytes(6));
    mkdir($previewDir);
    $previewStyles='<meta name="viewport" content="width=device-width, initial-scale=1">';
    foreach(array('styles.css','table.css','menu.css','formstyle.css','buttonstyle.css') as $style){
        $previewStyles.='<style>'.file_get_contents(__DIR__.'/../css/'.$style).'</style>';
    }
    $previewHtml=str_replace('<link rel="stylesheet" href="css/waec-comparison.css?v=20261012">',$previewStyles.'<style>'.file_get_contents(__DIR__.'/../css/waec-comparison.css').'</style>',$html);
    file_put_contents($previewDir.DIRECTORY_SEPARATOR.'comparison.html',$previewHtml);
    echo 'Preview directory: '.$previewDir.PHP_EOL;
}
$batches=array(); $uploads=array(); $ready=array(); $summary=array();
ob_start(); eval('?>'.$view); $emptyHtml=ob_get_clean();
check(strpos($emptyHtml,'Your comparison workspace is ready')!==false,'empty state supports future uploads');
check(strpos($emptyHtml,'id="wac-print"')===false,'empty state does not pretend to have a report');

if(in_array('--database',$argv,true)){
    require __DIR__.'/../dbstring.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    // Connection-local tables shadow saved data; no permanent records are altered.
    foreach(array('tblwaecresult','tblwaecwithheld') as $table){
        mysqli_query($con,"CREATE TEMPORARY TABLE $table (uploadid VARCHAR(80),candidate_no VARCHAR(120),student_name VARCHAR(255),subject_name VARCHAR(120),grade VARCHAR(20))");
    }
    mysqli_query($con,"INSERT INTO tblwaecresult VALUES ('test-A','001','Example','English Language','A1'),('test-B','002','Another','English','F9')");
    mysqli_query($con,"INSERT INTO tblwaecwithheld VALUES ('test-A','003','Example status','English','X')");
    $loaded=wac_analyze(wac_load_rows($con,'test-A'));
    check($loaded['candidates']===2 && $loaded['overall']['X']===1,'database loader includes both outcome tables');
    check($loaded['overall']['credit_rate']===100.0,'database loader isolates selected upload');
    check(count(wac_load_rows($con,"test-A' OR '1'='1"))===0,'prepared selection prevents SQL injection');
    mysqli_close($con);
}
echo $checks.' WAEC comparison regression checks passed.'.PHP_EOL;
