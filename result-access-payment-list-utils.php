<?php
// Read-only payment listing helpers. Authorization remains in the dashboard.
function rap_payment_filters($input){
    $filters=array();
    foreach(array('search','status','batchid','academicyear','termname','from','to') as $key){
        $filters[$key]=isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : '';
    }
    if(!in_array($filters['status'],array('success','initialized','failed'),true)) $filters['status']='';
    $filters['termname']=in_array($filters['termname'],array('1','2','3'),true) ? $filters['termname'] : '';
    foreach(array('from','to') as $key){
        $date=$filters[$key];
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date) || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))) $filters[$key]='';
    }
    $filters['per_page']=isset($input['per_page']) && is_string($input['per_page']) && in_array($input['per_page'],array('25','50','100'),true) ? (int)$input['per_page'] : 25;
    $filters['page']=isset($input['page']) && is_string($input['page']) && preg_match('/^[1-9][0-9]{0,8}$/D',$input['page']) ? (int)$input['page'] : 1;
    return $filters;
}
function rap_payment_page_url($filters,$page){
    $query=array_intersect_key($filters,array_flip(array('search','status','batchid','academicyear','termname','from','to','per_page')));
    $query['page']=max(1,(int)$page);
    return 'result-access-payments.php?'.http_build_query($query).'#payment-results';
}
function rap_load_payment_page($con,$filters){
    $where=array('1=1');
    if($filters['search']!==''){
        $search=mysqli_real_escape_string($con,$filters['search']);
        $where[]="(p.userid LIKE '%$search%' OR p.reference LIKE '%$search%' OR su.firstname LIKE '%$search%' OR su.surname LIKE '%$search%' OR su.othernames LIKE '%$search%')";
    }
    foreach(array('status','batchid','academicyear') as $key){
        if($filters[$key]!=='') $where[]="p.$key='".mysqli_real_escape_string($con,$filters[$key])."'";
    }
    if($filters['termname']!=='') $where[]='p.termname='.(int)$filters['termname'];
    if($filters['from']!=='') $where[]="p.createdat>='".mysqli_real_escape_string($con,$filters['from'])." 00:00:00'";
    if($filters['to']!=='') $where[]="p.createdat<='".mysqli_real_escape_string($con,$filters['to'])." 23:59:59'";
    $whereSql=implode(' AND ',$where);
    // Use the same student join for totals and rows, including name searches.
    $base="FROM tblresultaccesspayment p LEFT JOIN tblsystemuser su ON su.userid=p.userid";
    $summary=mysqli_query($con,"SELECT COUNT(*) total_count,
        SUM(CASE WHEN p.status='success' THEN 1 ELSE 0 END) successful_count,
        SUM(CASE WHEN p.status='success' THEN p.amount ELSE 0 END) received_amount,
        SUM(CASE WHEN p.status='initialized' THEN 1 ELSE 0 END) pending_count,
        SUM(CASE WHEN p.status='failed' THEN 1 ELSE 0 END) failed_count
        $base WHERE $whereSql");
    if(!$summary) throw new RuntimeException('Payment summary could not be loaded.');
    $totals=mysqli_fetch_assoc($summary); mysqli_free_result($summary);
    $total=(int)$totals['total_count']; $perPage=$filters['per_page'];
    $pages=max(1,(int)ceil($total/$perPage)); $page=min($filters['page'],$pages); $offset=($page-1)*$perPage;
    // Fetch only this page. The unique ID breaks ties between identical timestamps.
    $sql="SELECT p.*, CONCAT_WS(' ',su.firstname,su.othernames,su.surname) student_name, ce.class_name, bh.batch batch_name
        $base LEFT JOIN tblclassentry ce ON ce.class_entryid=p.classid LEFT JOIN tblbatch bh ON bh.batchid=p.batchid
        WHERE $whereSql ORDER BY COALESCE(p.paidat,p.createdat) DESC,p.paymentid DESC LIMIT $perPage OFFSET $offset";
    $result=mysqli_query($con,$sql);
    if(!$result) throw new RuntimeException('Payment records could not be loaded.');
    $payments=mysqli_fetch_all($result,MYSQLI_ASSOC); mysqli_free_result($result);
    return array('totals'=>$totals,'payments'=>$payments,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage,'total'=>$total,
        'first'=>$payments ? $offset+1 : 0,'last'=>$payments ? $offset+count($payments) : 0);
}
