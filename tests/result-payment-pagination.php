<?php
if(PHP_SAPI !== 'cli'){ http_response_code(403); exit; }
require_once __DIR__.'/../result-access-payment-list-utils.php';
require __DIR__.'/../dbstring.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
set_error_handler(function($severity,$message,$file,$line){ throw new ErrorException($message,0,$severity,$file,$line); });
$checks=0;
function payment_check($ok,$message){ global $checks; if(!$ok) throw new RuntimeException($message); $checks++; }
// Temporary tables shadow the application tables for this connection only.
mysqli_query($con,'CREATE TEMPORARY TABLE tblresultaccesspayment (paymentid VARCHAR(50) PRIMARY KEY,userid VARCHAR(100),reference VARCHAR(100),classid VARCHAR(100),batchid VARCHAR(100),academicyear VARCHAR(20),termname INT,status VARCHAR(20),amount DECIMAL(10,2),paidat DATETIME NULL,createdat DATETIME,gatewayresponse VARCHAR(255))');
mysqli_query($con,'CREATE TEMPORARY TABLE tblsystemuser (userid VARCHAR(100) PRIMARY KEY,firstname VARCHAR(100),surname VARCHAR(100),othernames VARCHAR(100),status VARCHAR(20))');
mysqli_query($con,'CREATE TEMPORARY TABLE tblclassentry (class_entryid VARCHAR(100) PRIMARY KEY,class_name VARCHAR(100))');
mysqli_query($con,'CREATE TEMPORARY TABLE tblbatch (batchid VARCHAR(100) PRIMARY KEY,batch VARCHAR(100))');
mysqli_query($con,"INSERT INTO tblsystemuser VALUES ('A','Ada','Example','','active'),('B','Kojo','Example','','active')");
mysqli_query($con,"INSERT INTO tblclassentry VALUES ('C','Synthetic class')");
mysqli_query($con,"INSERT INTO tblbatch VALUES ('B1','2024'),('B2','2025')");
$stmt=mysqli_prepare($con,"INSERT INTO tblresultaccesspayment VALUES (?, ?, ?, 'C', ?, '2025',2,?,10.00,NULL,'2025-10-01 12:00:00','Fixture response')");
for($i=1;$i<=602;$i++){
    $id='PAY'.str_pad((string)$i,5,'0',STR_PAD_LEFT); $user=$i%2 ? 'A':'B'; $reference='REF-'.$id; $batch=$i%2 ? 'B2':'B1'; $status=$i%2 ? 'success':'initialized';
    mysqli_stmt_bind_param($stmt,'sssss',$id,$user,$reference,$batch,$status); mysqli_stmt_execute($stmt);
}
mysqli_stmt_close($stmt);
$default=rap_payment_filters(array()); $one=rap_load_payment_page($con,$default);
payment_check(count($one['payments'])===25 && $one['total']===602 && $one['pages']===25,'Default loads 25 rows, not all payments');
payment_check($one['first']===1 && $one['last']===25,'First-page range');
payment_check((int)$one['totals']['successful_count']===301 && (float)$one['totals']['received_amount']===3010.0,'Totals cover entire filtered dataset');
$two=rap_load_payment_page($con,rap_payment_filters(array('page'=>'2')));
payment_check(count($two['payments'])===25 && $two['first']===26 && $two['last']===50,'Second-page range');
payment_check(!array_intersect(array_column($one['payments'],'paymentid'),array_column($two['payments'],'paymentid')),'Equal-date pages do not overlap');
payment_check($one['payments'][0]['paymentid']==='PAY00602' && $two['payments'][0]['paymentid']==='PAY00577','Unique ID stabilises tied dates');
$last=rap_load_payment_page($con,rap_payment_filters(array('page'=>'9999')));
payment_check($last['page']===25 && count($last['payments'])===2 && $last['last']===602,'Out-of-range page clamped; records beyond former 500-row cap accessible');
foreach(array('50'=>50,'100'=>100,'500'=>25,'all'=>25) as $value=>$size){
    $result=rap_load_payment_page($con,rap_payment_filters(array('per_page'=>(string)$value)));
    payment_check(count($result['payments'])===$size,'Bounded page size '.$value);
}
foreach(array('-1','0','2 OR 1=1',array('2'),'999999999999999999999999') as $page){
    payment_check(rap_payment_filters(array('page'=>$page))['page']===1,'Malformed page defaults safely');
}
$filters=rap_payment_filters(array('search'=>'Ada','status'=>'success','batchid'=>'B2','academicyear'=>'2025','termname'=>'2','from'=>'2025-10-01','to'=>'2025-10-01','per_page'=>'50','page'=>'2'));
$filtered=rap_load_payment_page($con,$filters);
payment_check($filtered['total']===301 && count($filtered['payments'])===50,'Combined filters work for rows and totals');
payment_check((float)$filtered['totals']['received_amount']===3010.0,'Name-search summary has required student join and qualified columns');
parse_str(parse_url(rap_payment_page_url($filters,3),PHP_URL_QUERY),$linked);
foreach(array('search','status','batchid','academicyear','termname','from','to','per_page') as $key) payment_check((string)$linked[$key]===(string)$filters[$key],'Pagination retains '.$key);
payment_check($linked['page']==='3','Pagination changes page');
$empty=rap_load_payment_page($con,rap_payment_filters(array('search'=>"' OR 1=1 --",'page'=>'9')));
payment_check($empty['total']===0 && $empty['first']===0 && $empty['last']===0 && $empty['page']===1,'No matches and injection-like search handled');
payment_check(rap_payment_filters(array('from'=>'2025-02-31','search'=>array()))['from']==='','Invalid dates ignored safely');
mysqli_query($con,"INSERT INTO tblresultaccesspayment VALUES ('ORPHAN','MISSING','REF-ORPHAN','MISSING','MISSING','2024',1,'failed',0,NULL,'2024-01-01 00:00:00','Fixture')");
$orphan=rap_load_payment_page($con,rap_payment_filters(array('search'=>'REF-ORPHAN')));
payment_check($orphan['total']===1 && count($orphan['payments'])===1,'Payment remains visible when student/class/batch missing');

// Render the real template with synthetic data only.
function rap_esc($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function rap_money($v){ return 'GHS '.number_format((float)$v,2); }
$source=file_get_contents(__DIR__.'/../result-access-payments.php');
$view=substr($source,strpos($source,'?>')+2);
$view=str_replace("<?php include('links.php');?>",'<meta name="viewport" content="width=device-width, initial-scale=1">',$view);
$listing=$filtered; $payments=$listing['payments']; $totals=$listing['totals'];
$search=$filters['search']; $status=$filters['status']; $batch=$filters['batchid']; $year=$filters['academicyear']; $term=(int)$filters['termname']; $from=$filters['from']; $to=$filters['to'];
$homePage='admin.php'; $batches=array(array('batchid'=>'B2','batch'=>'2025'));
ob_start(); eval('?>'.$view); $html=ob_get_clean();
payment_check(strpos($html,'Showing 51–100 of 301')!==false,'Visible filtered range');
payment_check(strpos($html,'aria-current="page">2')!==false && strpos($html,'rel="next"')!==false && strpos($html,'rel="prev"')!==false,'Accessible page controls rendered');
payment_check(strpos($html,'name="page"')===false,'Filter submission resets to page one');
if(in_array('--preview',$argv,true)){
    $dir=sys_get_temp_dir().DIRECTORY_SEPARATOR.'result-payments-'.bin2hex(random_bytes(6)); mkdir($dir);
    file_put_contents($dir.DIRECTORY_SEPARATOR.'payments.html',$html);
    echo 'Synthetic preview: '.$dir.PHP_EOL;
}
mysqli_close($con);
echo $checks." payment pagination checks passed. No saved payments changed.\n";
