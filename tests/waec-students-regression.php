<?php
if(PHP_SAPI !== 'cli'){ http_response_code(403); exit; }
require_once __DIR__.'/../waec-comparison-utils.php';
set_error_handler(function($severity,$message,$file,$line){ throw new ErrorException($message,0,$severity,$file,$line); });
$checks = 0;
function verify($condition,$message){ global $checks; if(!$condition) throw new RuntimeException($message); $checks++; }
$rows = array();
foreach(array('001','002') as $number){
    foreach(array_merge(wac_core(),array('BIOLOGY','CHEMISTRY')) as $subject){
        $rows[] = array('candidate_no'=>$number,'student_name'=>'Example '.$number,'subject_name'=>$subject,'grade'=>'C6');
    }
}
$rows[] = array('candidate_no'=>'002','student_name'=>'Example 002','subject_name'=>'SOCIAL STUDIES','grade'=>'W');
$rows[] = array('candidate_no'=>'003','student_name'=>'<script>alert(1)</script>','subject_name'=>'English','grade'=>'X');
$rows[] = array('candidate_no'=>'004','student_name'=>'+Formula example','subject_name'=>'English','grade'=>'D7');
$rows[] = array('candidate_no'=>'004','student_name'=>'+Formula example','subject_name'=>'Core Mathematics','grade'=>'P');
$rows[] = array('candidate_no'=>'005','student_name'=>'Withheld example','subject_name'=>'English','grade'=>'W');
$rows[] = array('candidate_no'=>'006','student_name'=>'Unknown example','subject_name'=>'English','grade'=>'??');
$rows[] = $rows[0];
$analysis = wac_analyze($rows,true);
verify(count($analysis['students'])===6,'Candidate deduplication');
verify($analysis['students'][0]['number']==='001','Leading zeros retained');
$aggregate = wac_analyze($rows);
$details = $analysis; unset($details['students']);
verify($aggregate===$details,'Detail mode leaves aggregates and fingerprint unchanged');
verify(!isset($aggregate['students']),'Default analysis does not expose names');
verify(wac_analyze(array_reverse($rows),true)['students']===$analysis['students'],'Detail order independent of input');
foreach(wac_achievement_labels() as $key=>$label){
    $confirmed = wac_filter_students($analysis['students'],wac_student_filters(array('metric'=>$key)));
    $other = wac_filter_students($analysis['students'],wac_student_filters(array('metric'=>$key,'group'=>'not_confirmed')));
    verify(count($confirmed)===$analysis['achievement']['metrics'][$key]['achieved'],$key.' confirmed reconciliation');
    verify(count($other)===$analysis['achievement']['metrics'][$key]['not_confirmed'],$key.' unconfirmed reconciliation');
    verify(count($confirmed)+count($other)===6,$key.' exhaustive groups');
}
$expected = array('below_credit'=>1,'absent'=>1,'withheld'=>1,'source_status'=>1,'missing'=>4,'conflict'=>1,'unknown'=>1);
foreach($expected as $flag=>$count){
    $filtered = wac_filter_students($analysis['students'],wac_student_filters(array('group'=>'all','flag'=>$flag)));
    verify(count($filtered)===$count,$flag.' classified separately');
}
verify(!isset(wac_student_flags($analysis['students'][1],'english')['conflict']),'Flags limited to selected criterion');
$conflict = $analysis['students'][1];
verify($conflict['outcomes']['SOCIAL STUDIES']===array('C6','W'),'Conflicting evidence preserved');
verify($conflict['credits']===5,'Conflicting credit not counted');
verify(count(wac_filter_students($analysis['students'],wac_student_filters(array('group'=>'all','q'=>'001'))))===1,'Candidate search');
verify(count(wac_filter_students($analysis['students'],wac_student_filters(array('group'=>'all','q'=>'WITHHELD'))))===1,'Case insensitive name search');
verify(count(wac_filter_students($analysis['students'],wac_student_filters(array('group'=>'all','q'=>'no match'))))===0,'Empty search result');
foreach(array(array('metric'=>'invalid'),array('group'=>'invalid'),array('flag'=>'invalid'),array('page'=>'0'),array('page'=>'-1'),array('q'=>array()),array('q'=>str_repeat('a',121))) as $input){
    $rejected=false; try{ wac_student_filters($input); }catch(InvalidArgumentException $e){ $rejected=true; }
    verify($rejected,'Reject malformed filters');
}
verify(wac_csv_cell('+Formula example')==="'+Formula example",'CSV formula guard');
verify(!wac_accessible(array('branchid'=>'B'),array('ACCESSLEVEL'=>'administrator','SYSTEMTYPE'=>'normal_user','BRANCHID'=>'A')),'Cross-branch access denied');
verify(!wac_accessible(array('branchid'=>''),array()),'Empty branch denied');
$endpoint=file_get_contents(__DIR__.'/../waec-achievement-students.php');
verify(strpos($endpoint,'!wac_accessible')<strpos($endpoint,'wac_load_rows'),'Upload access checked before loading student records');
verify(strpos($endpoint,"if(!\$allowed)")<strpos($endpoint,"if((\$_GET['export']"),'Export follows role authorization');
verify(strpos($endpoint,'Cache-Control: private, no-store')!==false,'Private response not cached');
$comparison=file_get_contents(__DIR__.'/../waec-comparison.php');
verify(strpos($comparison,"wac_student_url(\$b['uploadid'],\$metricKey,'not_confirmed')")!==false,'Summary links use current metric');

// Render the actual template using only synthetic students.
$view=substr($endpoint,strpos($endpoint,'<!DOCTYPE html>'));
$view=str_replace(array("<?php include('links.php'); ?>","<?php include('menu.php'); ?>"),'',$view);
$error=''; $uploadId='fixture-A'; $title='2025 · Example batch · School candidates';
$labels=wac_achievement_labels(); $flagLabels=wac_flag_labels();
$groups=array('confirmed'=>'Confirmed','not_confirmed'=>'Not confirmed','all'=>'All candidates');
$filters=wac_student_filters(array('group'=>'all')); $students=wac_filter_students($analysis['students'],$filters);
$metric=$analysis['achievement']['metrics'][$filters['metric']];
$total=count($students); $pages=1; $pageStudents=$students;
$baseQuery=array_merge(array('upload'=>$uploadId),$filters);
$exportUrl='waec-achievement-students.php?'.http_build_query(array_merge($baseQuery,array('export'=>'csv')));
ob_start(); eval('?>'.$view); $html=ob_get_clean();
verify(stripos($html,'<script>alert(1)</script>')===false && stripos($html,'&lt;script&gt;')!==false,'Student names HTML escaped');
verify(strpos($html,'6 matching students')!==false,'Matching total rendered');
verify(strpos($html,'<h1>Student Achievement</h1>')!==false,'Plain student report heading');
verify(strpos($html,'C6 / W')!==false,'Conflicting outcomes shown');
verify(strpos($html,'Export all matches (CSV)')!==false && strpos($html,'Print this page')!==false,'Export scope clear');
$students=array(); $total=0; $pageStudents=array();
ob_start(); eval('?>'.$view); $empty=ob_get_clean();
verify(strpos($empty,'No students match these filters')!==false,'Empty state rendered');
$error='This upload is unavailable or outside your branch.';
ob_start(); eval('?>'.$view); $denied=ob_get_clean();
verify(strpos($denied,'Example 001')===false && strpos($denied,'outside your branch')!==false,'Denied template exposes no records');
if(in_array('--preview',$argv,true)){
    $previewDir=sys_get_temp_dir().DIRECTORY_SEPARATOR.'waec-students-'.bin2hex(random_bytes(6)); mkdir($previewDir);
    $styles='<meta name="viewport" content="width=device-width, initial-scale=1">';
    foreach(array('styles.css','table.css','menu.css','formstyle.css','buttonstyle.css','waec-comparison.css') as $file) $styles.='<style>'.file_get_contents(__DIR__.'/../css/'.$file).'</style>';
    file_put_contents($previewDir.DIRECTORY_SEPARATOR.'students.html',str_replace('<link rel="stylesheet" href="css/waec-comparison.css?v=20261012-students">',$styles,$html));
    echo 'Preview directory: '.$previewDir.PHP_EOL;
}
echo $checks.' WAEC student drilldown checks passed.'.PHP_EOL;
