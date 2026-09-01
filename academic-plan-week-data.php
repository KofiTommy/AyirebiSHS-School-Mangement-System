<?php
session_start(); include('dbstring.php'); include('check-login.php'); include_once('academic-plan-utils.php'); academic_plan_ensure_table($con);
header('Content-Type: application/json; charset=utf-8');
$includeDrafts=(isset($_SESSION['ACCESSLEVEL']) && $_SESSION['ACCESSLEVEL']==='administrator') || (isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL']==='user' && $_SESSION['SYSTEMTYPE']==='AssistantHeadAdministration'); $rows=academic_plan_rows($con,'',$includeDrafts); $data=array();
foreach($rows as $row){ $data[]=array('planid'=>(string)$row['planid'],'title'=>(string)$row['title'],'eventtype'=>(string)$row['eventtype'],'startdate'=>(string)$row['startdate'],'enddate'=>(string)$row['enddate'],'description'=>(string)$row['description'],'batchid'=>(string)$row['batchid'],'termname'=>(string)$row['termname'],'weeknumber'=>(int)$row['weeknumber']); }
echo json_encode($data);
?>
