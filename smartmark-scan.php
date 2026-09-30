<?php
/* Private bridge from the teacher portal to the Python SmartMark OMR service. */
session_start(); require_once('check-login.php'); require_once('smartmark-utils.php'); smartmark_ensure_tables($con);
if(!(isset($_SESSION['ACCESSLEVEL'],$_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL']==='user' && $_SESSION['SYSTEMTYPE']==='Teacher')){http_response_code(403);exit('Access denied.');}
$teacherEsc=mysqli_real_escape_string($con,(string)$_SESSION['USERID']); $assessmentId=(int)($_POST['assessmentid']??0);
$result=@mysqli_query($con,"SELECT questioncount,optioncount,mastertemplate,masterconfirmed FROM tblsmartmarkassessment WHERE assessmentid=$assessmentId AND teacherid='$teacherEsc' AND status='active' LIMIT 1");$assessment=$result?mysqli_fetch_array($result,MYSQLI_ASSOC):null;
if(!$assessment){http_response_code(404);exit('Assessment not found.');}if(empty($assessment['masterconfirmed'])){http_response_code(422);exit('Confirm a master answer sheet before scanning student papers.');}
if(empty($_FILES['answer_sheet']) || $_FILES['answer_sheet']['error']!==UPLOAD_ERR_OK){http_response_code(422);exit('A valid answer-sheet image is required.');}
if((int)$_FILES['answer_sheet']['size']>8*1024*1024){http_response_code(413);exit('Image exceeds 8 MB.');}
if(!function_exists('curl_init')){http_response_code(500);exit('PHP cURL extension is not enabled.');}
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['answer_sheet']['tmp_name']); if(!in_array($mime,array('image/jpeg','image/png','image/webp'),true)){http_response_code(422);exit('Use a JPG, PNG, or WEBP image.');}
$config=require __DIR__.'/smartmark-service-config.example.php'; if(file_exists(__DIR__.'/smartmark-service-config.local.php')){$config=require __DIR__.'/smartmark-service-config.local.php';}
$curl=curl_init(rtrim((string)($config['url']??'http://127.0.0.1:8765'),'/').'/detect'); $headers=!empty($config['token'])?array('X-SmartMark-Token: '.$config['token']):array();
$post=array('image'=>new CURLFile($_FILES['answer_sheet']['tmp_name'],$mime,$_FILES['answer_sheet']['name']),'question_count'=>(int)$assessment['questioncount'],'option_count'=>(int)$assessment['optioncount'],'template'=>(string)$assessment['mastertemplate']); curl_setopt_array($curl,array(CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>max(5,(int)($config['timeout_seconds']??30))));
$body=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); $error=curl_error($curl); curl_close($curl);
if($body===false){http_response_code(502);exit('Scanner service unavailable: '.$error);} http_response_code($status?:502); header('Content-Type: application/json; charset=utf-8'); echo $body;
?>
