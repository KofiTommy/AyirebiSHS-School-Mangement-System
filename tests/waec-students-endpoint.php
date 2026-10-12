<?php
// Test the real endpoint against connection-local tables. Never inserts saved student data.
if(PHP_SAPI !== 'cli'){ http_response_code(403); exit; }
if(isset($argv[1])){
    require __DIR__.'/../dbstring.php';
    require __DIR__.'/../waec-comparison-utils.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    mysqli_query($con,'CREATE TEMPORARY TABLE tblwaecupload (uploadid VARCHAR(80),branchid VARCHAR(80),exam_year INT,batch_label VARCHAR(120),exam_sitting VARCHAR(80))');
    mysqli_query($con,"INSERT INTO tblwaecupload VALUES ('fixture-A','A',2025,'Test batch','School candidates'),('fixture-B','B',2025,'Other branch','School candidates')");
    foreach(array('tblwaecresult','tblwaecwithheld') as $table) mysqli_query($con,"CREATE TEMPORARY TABLE $table (uploadid VARCHAR(80),candidate_no VARCHAR(120),student_name VARCHAR(255),subject_name VARCHAR(120),grade VARCHAR(20))");
    $stmt=mysqli_prepare($con,'INSERT INTO tblwaecresult VALUES (?,?,?,?,?)');
    for($i=1;$i<=53;$i++){
        $upload='fixture-A'; $number=str_pad((string)$i,4,'0',STR_PAD_LEFT); $name=$i===1 ? '+Formula example':'Synthetic student'; $subject='English'; $grade='A1';
        mysqli_stmt_bind_param($stmt,'sssss',$upload,$number,$name,$subject,$grade); mysqli_stmt_execute($stmt);
    }
    mysqli_query($con,"INSERT INTO tblwaecresult VALUES ('fixture-B','SECRET','Other branch student','English','A1')");
    $_SESSION=array('USERID'=>'fixture','ACCESSLEVEL'=>'administrator','SYSTEMTYPE'=>'normal_user','BRANCHID'=>'A');
    $_GET=array('upload'=>'fixture-A','metric'=>'english','group'=>'confirmed','page'=>'2');
    function um_is_academic_lead_user(){ return false; }
    if($argv[1]==='csv') $_GET['export']='csv';
    if($argv[1]==='csv-search'){ $_GET['export']='csv'; $_GET['q']='+Formula'; }
    if($argv[1]==='foreign'){ $_GET['upload']='fixture-B'; $_GET['export']='csv'; }
    if($argv[1]==='role'){ $_SESSION['ACCESSLEVEL']='student'; $_GET['export']='csv'; }
    if($argv[1]==='malformed') $_GET['metric']=array('english');
    $source=file_get_contents(__DIR__.'/../waec-achievement-students.php');
    $source=str_replace(array('session_start();',"require_once __DIR__.'/check-login.php';","require_once __DIR__.'/waec-comparison-utils.php';","<?php include('links.php'); ?>","<?php include('menu.php'); ?>"),'',$source);
    eval('?>'.$source); exit;
}
$checks=0;
function endpoint($case){
    $pipes=array();
    $process=proc_open(array(PHP_BINARY,__FILE__,$case),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
    fclose($pipes[0]); $result=stream_get_contents($pipes[1]); fclose($pipes[1]);
    $errors=stream_get_contents($pipes[2]); fclose($pipes[2]); $code=proc_close($process);
    if($code!==0 || $errors!=='') throw new RuntimeException('Endpoint fixture failed: '.$errors);
    return $result;
}
function expectEndpoint($condition,$message){ global $checks; if(!$condition) throw new RuntimeException($message); $checks++; }
$html=endpoint('page');
expectEndpoint(strpos($html,'53 matching students')!==false && strpos($html,'Page 2 of 2')!==false,'Pagination total');
expectEndpoint(strpos($html,'0051')!==false && strpos($html,'0053')!==false && strpos($html,'>0001<')===false,'Second page includes only remaining three students');
$csv=endpoint('csv');
expectEndpoint(substr_count($csv,'SYNTHETIC STUDENT')===52 && strpos($csv,"'+FORMULA EXAMPLE")!==false,'CSV exports all matches, not only visible page, and guards formulas');
expectEndpoint(strpos($csv,'SECRET')===false,'Export isolated to selected upload');
$csv=endpoint('csv-search');
expectEndpoint(strpos($csv,"'+FORMULA EXAMPLE")!==false && strpos($csv,'SYNTHETIC STUDENT')===false,'Export honours search');
$denied=endpoint('foreign');
expectEndpoint(strpos($denied,'outside your branch')!==false && strpos($denied,'SECRET')===false && stripos($denied,'Other branch student')===false,'Cross-branch CSV request denied');
$denied=endpoint('role');
expectEndpoint(strpos($denied,'do not have permission')!==false && stripos($denied,'Synthetic student')===false,'Student role cannot export');
$invalid=endpoint('malformed');
expectEndpoint(strpos($invalid,'Invalid student filter')!==false && stripos($invalid,'Synthetic student')===false,'Malformed filter exposes no student data');
echo $checks.' student endpoint integration checks passed.'.PHP_EOL;
