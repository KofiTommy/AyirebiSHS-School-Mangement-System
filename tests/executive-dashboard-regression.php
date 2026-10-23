<?php
namespace ExecutiveDashboardTest;
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
// Real schema validation uses EXPLAIN only. The rendered page uses synthetic rows.
require __DIR__.'/../dbstring.php';
\mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$queryCount=0;
class HandlerExit extends \Exception {}
function header($value){ $GLOBALS['fixtureRedirect']=$value; }
function session_start() {}
function dashboard_student_population_summary($con,$options){return array();}
function dashboard_student_batch_breakdown_html(...$args){return '<small>Synthetic batch</small>';}
function mysqli_real_escape_string($con,$s){return \mysqli_real_escape_string($con,$s);}
function mysqli_query($con,$sql){
    global $queryCount;
    if(!empty($GLOBALS['allowNotificationWrite']) && strpos($sql,"UPDATE tblsystemchangelog SET status='read'")===0){
        $GLOBALS['notificationWrites']++;
        return true; // Simulated write only; never sent to the database.
    }
    if(!preg_match('/^\s*SELECT\b/i',$sql))throw new \RuntimeException('Unexpected write in dashboard GET');
    \mysqli_query($con,'EXPLAIN '.$sql); $queryCount++;
    $rows=array();
    if(strpos($sql,'tblsystemchangelog')!==false){
        $rows=strpos($sql,'COUNT(*)')!==false ? array(array('c'=>1)) : array(array('logid'=>1,'actor_userid'=>'fixture','actor_name'=>'Synthetic Teacher','actor_type'=>'Teacher','action_type'=>'FIXTURE','target_userid'=>'fixture','details'=>'Synthetic notification','datetimeentry'=>'2026-10-23 10:00:00','status'=>'unread'));
    }elseif(strpos($sql,'AS st, COUNT(*)')!==false){
        $rows=array(array('st'=>'present','c'=>9),array('st'=>'absent','c'=>1));
    }elseif(strpos($sql,'AS score_count')!==false){
        for($i=0;$i<12;$i++)$rows[]=array('subject'=>'Subject '.$i,'score_count'=>1,'score_sum'=>$i<10?100:0,'pass_count'=>$i<10?1:0,'avg_percent'=>$i<10?100:0,'pass_rate'=>$i<10?100:0);
    }elseif(strpos($sql,'SELECT userid, firstname')!==false){
        $rows[]=array('userid'=>'fixture-001','firstname'=>'Synthetic','othernames'=>'','surname'=>'Student','status'=>'active','registereddatetime'=>'2026-10-01');
    }
    return (object)array('rows'=>$rows);
}
function mysqli_fetch_array($result,$mode){return array_shift($result->rows);}
function mysqli_fetch_row($result){$row=array_shift($result->rows);return $row?array_values($row):null;}
$_SESSION=array('USERID'=>'fixture-admin','FULLNAME'=>'Synthetic Administrator','SYSTEMTYPE'=>'normal_user','ACCESSLEVEL'=>'administrator','SYMBOL'=>'GHS');
$_POST=array(); $_GET=array('perf_year'=>'2027'); $_SERVER['PHP_SELF']='executive-dashboard.php';
$_CompanyName='Synthetic School';
$source=file_get_contents(__DIR__.'/../executive-dashboard.php');
// Remove imports instead of executing authentication, tracking or schema migrations.
$source=preg_replace('/\b(?:include|require_once)\s*(?:\([^;]*\)|__DIR__\s*\.\s*\x27[^\x27]*\x27)\s*;/','/* fixture import omitted */',$source);
set_error_handler(function($severity,$message,$file,$line){throw new \ErrorException($message,0,$severity,$file,$line);});
ob_start(); eval('namespace ExecutiveDashboardTest; ?>'.$source); $html=ob_get_clean(); $html=ob_get_clean().$html;
if($perfRowsCount!==10 || $overallAvg!==83.3 || $overallPass!==83.3)throw new \RuntimeException('Overall figures omit subjects outside top ten');
if(strpos($perfWhere,"COALESCE(NULLIF(TRIM(sa.academicyear),'')")===false)throw new \RuntimeException('Wrong year scope');
if(strpos($html,'display-student.php?search-item=fixture-001')===false)throw new \RuntimeException('Student link mismatch');
if(strpos($html,'name="csrf"')===false)throw new \RuntimeException('Notification CSRF missing');
restore_error_handler();
$feed=array(array('when'=>'Yesterday','timestamp'=>100),array('when'=>'9:00 AM','timestamp'=>300),array('when'=>'Friday','timestamp'=>200));
preg_match('/usort\(\$feed,.*?; \}\);/',$source,$sort);
if(!$sort)throw new \RuntimeException('Feed sort missing'); eval($sort[0]);
if(array_column($feed,'timestamp')!==array(300,200,100))throw new \RuntimeException('Feed not chronological');
$postStart=strpos($source,"if(isset(\$_POST['mark_changes_read'])");
$postEnd=strpos($source,'$_ChangeNotifications = array();',$postStart);
$handler=substr($source,$postStart,$postEnd-$postStart);
$handler=preg_replace('/exit\((.*?)\);/', 'throw new \\ExecutiveDashboardTest\\HandlerExit($1);', $handler);
$GLOBALS['allowNotificationWrite']=true;
foreach(array(null,array('bad'),'wrong',$_SESSION['executive_csrf']) as $token){
    $_POST=array('mark_changes_read'=>1,'csrf'=>$token); $GLOBALS['notificationWrites']=0;
    try{eval('namespace ExecutiveDashboardTest; '.$handler);}catch(HandlerExit $e){}
    $valid=is_string($token)&&hash_equals($_SESSION['executive_csrf'],$token);
    if($GLOBALS['notificationWrites']!==($valid?1:0))throw new \RuntimeException('Notification CSRF failed');
}
$_POST=array(); $GLOBALS['allowNotificationWrite']=false;
echo "PASS: $queryCount dashboard SELECTs validated; no writes, all-subject totals, year scope, student links, CSRF and timestamp sorting checked.\n";
if(in_array('--preview',$argv,true)){
    $dir=sys_get_temp_dir().'/executive-dashboard-'.bin2hex(random_bytes(5)); mkdir($dir);
    $root=str_replace('\\','/',dirname(__DIR__));
    $html=str_replace('<head>','<head><base href="file:///'.$root.'/"><script>window.fixtureErrors=[];window.addEventListener("error",function(e){window.fixtureErrors.push(e.message)});window.fetch=function(){return Promise.resolve({ok:true,json:function(){return Promise.resolve({live_users:3})}})};localStorage.setItem("exdash-deck-open","null");</script><link rel="stylesheet" href="css/colour-modes.css">',$html);
    // No real HTTP requests or submissions from the synthetic fixture.
    $html=str_replace('<body ', '<body data-executive-fixture="1" ', $html);
    $html=preg_replace('/<link[^>]+href="https:\/\/fonts\.[^>]+>/', '', $html);
    file_put_contents($dir.'/executive.html',$html);
    echo 'Preview: '.$dir.PHP_EOL;
}
