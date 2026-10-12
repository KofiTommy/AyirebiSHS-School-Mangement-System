<?php
if(PHP_SAPI !== 'cli'){ http_response_code(403); exit; }
// Render the real query/view code using temporary tables and synthetic candidates.
require __DIR__.'/waec-analysis-regression.php';
require_once __DIR__.'/../waec-comparison-utils.php';
require __DIR__.'/../dbstring.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
set_error_handler(function($severity,$message,$file,$line){
    if(!(error_reporting() & $severity)) return false;
    throw new ErrorException($message,0,$severity,$file,$line);
});
$source=file_get_contents(__DIR__.'/../waec-analysis.php');
$tokens=token_get_all($source);
for($i=0;$i<count($tokens);$i++){
    if(!is_array($tokens[$i]) || $tokens[$i][0]!==T_FUNCTION) continue;
    $n=$i+1; while(is_array($tokens[$n]) && $tokens[$n][0]===T_WHITESPACE) $n++;
    if(!is_array($tokens[$n]) || $tokens[$n][0]!==T_STRING || function_exists($tokens[$n][1])) continue;
    $code=''; $depth=0; $started=false;
    for(;$i<count($tokens);$i++){
        $token=$tokens[$i];
        $code.=is_array($token) ? ($token[0]===T_DIR ? var_export(dirname(__DIR__),true) : $token[1]) : $token;
        if($token==='{'){ $depth++; $started=true; }
        if($token==='}') $depth--;
        if($started && $depth===0) break;
    }
    eval($code);
}
foreach(array('tblwaecupload','tblwaecresult','tblwaecwithheld') as $table){
    $schema=mysqli_fetch_row(mysqli_query($con,"SHOW CREATE TABLE $table"))[1];
    mysqli_query($con,preg_replace('/^CREATE TABLE /','CREATE TEMPORARY TABLE ',$schema));
}
mysqli_query($con,"INSERT INTO tblwaecupload(uploadid,originalname,storedname,fileext,totalrows,totalstudents,uploadedby,branchid,datetimeentry,exam_year,exam_sitting,batch_label)
    VALUES('UI_FIXTURE_2025','Synthetic results 2025.xlsx','','xlsx',8,3,'TEST','TEST',NOW(),2025,'School candidates','Class of 2025')");
foreach(array('001'=>'Male','002'=>'Female') as $candidate=>$gender){
    foreach(wac_core() as $subject){
        $grade=$candidate==='001' ? 'B3' : 'C6'; $point=$candidate==='001' ? 3 : 6;
        $stmt=mysqli_prepare($con,"INSERT INTO tblwaecresult(uploadid,candidate_no,student_name,gender,subject_name,grade,grade_point,datetimeentry) VALUES('UI_FIXTURE_2025',?,'Synthetic Candidate',?,?,?,?,NOW())");
        mysqli_stmt_bind_param($stmt,'ssssi',$candidate,$gender,$subject,$grade,$point);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
}
foreach(wac_core() as $subject){
    $stmt=mysqli_prepare($con,"INSERT INTO tblwaecwithheld(uploadid,candidate_no,student_name,gender,subject_name,grade,datetimeentry) VALUES('UI_FIXTURE_2025','003','Synthetic Absent','Female',?,'X',NOW())");
    mysqli_stmt_bind_param($stmt,'s',$subject); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
}
$queryStart=strpos($source,'$uploads = mysqli_query');
$viewStart=strpos($source,'<!DOCTYPE html>');
$queryCode=substr($source,$queryStart,$viewStart-$queryStart);
$view=substr($source,$viewStart);
$view=preg_replace('/<\?php\s*include\("(?:links|menu)\.php"\);\s*\?>/','',$view);
$style='';
foreach(array('styles.css','table.css','menu.css','formstyle.css','buttonstyle.css','waec-analysis.css') as $file){
    $style.='<style>'.file_get_contents(__DIR__.'/../css/'.$file).'</style>';
}
$view=preg_replace('/<link rel="stylesheet" href="css\/waec-analysis.css[^\"]*">/',$style,$view);
$view=str_replace('</head>','<link rel="stylesheet" href="file:///'.str_replace('\\','/',dirname(__DIR__)).'/css/font-awesome-4.7.0/css/font-awesome.min.css"></head>',$view);
$view=str_replace('<script src="js/waec-analysis-workspace.js?v=20261011"></script>','<script>'.file_get_contents(__DIR__.'/../js/waec-analysis-workspace.js').'</script>',$view);
$previewDir=sys_get_temp_dir().DIRECTORY_SEPARATOR.'waec-workspace-'.bin2hex(random_bytes(6));
mkdir($previewDir);
$states=array('overview'=>array(),'student'=>array('student_query'=>'001'),'filtered'=>array('focus_subject'=>'ENGLISH LANG'),'empty'=>array());
foreach($states as $state=>$query){
    $_GET=$query; $_POST=array(); $currentUploadId=$state==='empty' ? '' : 'UI_FIXTURE_2025'; $message='';
    ob_start(); eval($queryCode); eval('?>'.$view); $html=ob_get_clean();
    foreach(array('waec-overview','waec-subjects','student-result-analysis','waec-coverage','waec-upload') as $id){
        expect_same(1,substr_count($html,'id="'.$id.'"'),$state.' unique panel '.$id);
    }
    expect_same(true,strpos($html,'class="waec-batch-switch"')!==false,$state.' batch selector');
    if($state!=='empty'){
        foreach(array('waec-statement-overview-print','waec-grade-matrix-print','waec-gender-analysis-print','waec-grade-distribution-print','waec-coverage-audit-print') as $id){
            expect_same(1,substr_count($html,'id="'.$id.'"'),$state.' preserved printable section '.$id);
        }
    }
    if($state==='student') expect_same(true,strpos($html,'Print Student Analysis')!==false,'student result and export available');
    file_put_contents($previewDir.DIRECTORY_SEPARATOR.$state.'.html',$html);
}
mysqli_close($con);
echo 'Workspace render checks passed. Synthetic preview directory: '.$previewDir.PHP_EOL;
