<?php
require_once __DIR__.'/alumni-utils.php';
require_once __DIR__.'/online-admission-utils.php';
require_once __DIR__.'/audit_notifications.php';

function ald_query($con, $sql, $params = array()) {
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) { throw new RuntimeException('Document storage unavailable.'); }
    if ($params) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if (!mysqli_stmt_execute($stmt)) { throw new RuntimeException('Document storage unavailable.'); }
    return $stmt;
}
function ald_rows($con, $sql, $params = array()) {
    $stmt = ald_query($con, $sql, $params);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}
function ald_schema_statements() {
    return array(
        "CREATE TABLE IF NOT EXISTS tblalumnidocservice (
            id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL UNIQUE, seedkey VARCHAR(30) NULL UNIQUE,
            description VARCHAR(1000) NOT NULL DEFAULT '', fee INT UNSIGNED NULL,
            deliveryfee INT UNSIGNED NOT NULL DEFAULT 0, pickup TINYINT NOT NULL DEFAULT 1,
            email TINYINT NOT NULL DEFAULT 0, physical TINYINT NOT NULL DEFAULT 0,
            enabled TINYINT NOT NULL DEFAULT 0, updatedby VARCHAR(60) NOT NULL DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS tblalumnidocrequest (
            id INT AUTO_INCREMENT PRIMARY KEY, alumniid VARCHAR(60) NOT NULL, serviceid INT NOT NULL,
            token VARCHAR(64) NOT NULL, servicename VARCHAR(150) NOT NULL, fee INT UNSIGNED NOT NULL,
            deliveryfee INT UNSIGNED NOT NULL, total INT UNSIGNED NOT NULL, delivery VARCHAR(20) NOT NULL,
            details TEXT NOT NULL, receiptemail VARCHAR(180) NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'submitted',
            paymentstatus VARCHAR(30) NOT NULL DEFAULT 'unpaid', reference VARCHAR(80) NOT NULL UNIQUE,
            checkout VARCHAR(500) NOT NULL DEFAULT '', initat DATETIME NULL, gatewayid VARCHAR(80) NULL UNIQUE,
            paymentmode VARCHAR(10) NOT NULL DEFAULT '',
            paidat DATETIME NULL, staffnote TEXT NULL, updatedby VARCHAR(60) NOT NULL DEFAULT '',
            createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updatedat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY idx_submit (alumniid,token), KEY idx_member (alumniid,id), KEY idx_status (status,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS tblalumnidocevent (
            id INT AUTO_INCREMENT PRIMARY KEY, requestid INT NOT NULL, actor VARCHAR(80) NOT NULL,
            event VARCHAR(60) NOT NULL, note TEXT NOT NULL, createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_request (requestid,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}
function ald_schema($con) {
    ensureSystemChangeLogTable($con);
    foreach (ald_schema_statements() as $statement) { if (!mysqli_query($con, $statement)) { throw new RuntimeException('Document setup unavailable.'); } }
    foreach (array('waec'=>'WAEC certificate (school-held original)', 'testimonial'=>'Testimonial', 'transcript'=>'Academic transcript', 'recommendation'=>'Recommendation letter', 'leaving'=>'School leaving letter') as $key=>$name) {
        ald_query($con, 'INSERT IGNORE INTO tblalumnidocservice(name,seedkey) VALUES(?,?)', array($name,$key));
    }
}
function ald_member($con) {
    $rows = ald_rows($con, "SELECT * FROM tblalumni WHERE alumniid=? AND status='active' LIMIT 1", array($_SESSION['ALUMNI_MEMBER'] ?? ''));
    return $rows[0] ?? null;
}
function ald_text($input, $key, $max = 180) {
    $value = $input[$key] ?? '';
    if (!is_string($value) || strlen(trim($value)) > $max) { throw new InvalidArgumentException('Please shorten or correct the '.$key.' field.'); }
    return trim($value);
}
function ald_money($value) {
    if (!is_string($value) || !preg_match('/^\d{1,6}(?:\.\d{1,2})?$/D', trim($value))) { throw new InvalidArgumentException('Enter a valid fee with up to two decimal places.'); }
    $parts = explode('.', trim($value));
    return (int)$parts[0] * 100 + (int)str_pad($parts[1] ?? '', 2, '0');
}
function ald_price($minor) { return 'GHS '.number_format((int)$minor / 100, 2); }
function ald_event($con, $id, $actor, $event, $note = '') {
    ald_query($con, 'INSERT INTO tblalumnidocevent(requestid,actor,event,note) VALUES(?,?,?,?)', array($id,$actor,$event,$note));
}
function ald_details($input, $service) {
    $mode = ald_text($input, 'delivery', 20);
    if (!in_array($mode, array('pickup','email','physical'), true) || empty($service[$mode])) { throw new InvalidArgumentException('Choose a delivery method offered for this document.'); }
    $details = array();
    foreach (array('recipient'=>180,'phone'=>40,'email'=>180,'address'=>600,'gps'=>40,'city'=>100,'region'=>100,'country'=>100,'indexnumber'=>40,'examyear'=>4,'purpose'=>1000) as $key=>$max) {
        $details[$key] = ald_text($input, $key, $max);
    }
    if ($details['recipient']==='' || $details['phone']==='') { throw new InvalidArgumentException('Enter the recipient name and contact number.'); }
    if ($mode==='email' && !filter_var($details['email'], FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException('Enter a valid delivery email address.'); }
    if ($mode==='physical' && (($details['address']==='' && $details['gps']==='') || $details['city']==='' || $details['country']==='')) {
        throw new InvalidArgumentException('Physical delivery needs a house/postal address or GhanaPost GPS address, plus town/city and country.');
    }
    if ($details['examyear']!=='' && !preg_match('/^(19|20)\d{2}$/D', $details['examyear'])) { throw new InvalidArgumentException('Enter a four-digit examination year.'); }
    // Do not retain irrelevant destination fields.
    if ($mode!=='email') { $details['email']=''; }
    if ($mode!=='physical') { foreach(array('address','gps','city','region','country') as $key) { $details[$key]=''; } }
    return array($mode,$details);
}
function ald_create($con, $member, $input, $token) {
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) { throw new InvalidArgumentException('Refresh this page before submitting.'); }
    $existing = ald_rows($con, 'SELECT id FROM tblalumnidocrequest WHERE alumniid=? AND token=?', array($member['alumniid'],$token));
    if ($existing) { return (int)$existing[0]['id']; }
    $services = ald_rows($con, 'SELECT * FROM tblalumnidocservice WHERE id=? AND enabled=1 AND fee IS NOT NULL', array(ald_text($input,'service',20)));
    if (!$services) { throw new InvalidArgumentException('This service is not currently available.'); }
    $service=$services[0]; list($mode,$details)=ald_details($input,$service);
    $email=ald_text($input,'receiptemail');
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException('Enter a valid email for payment receipts.'); }
    $deliveryfee=$mode==='physical' ? (int)$service['deliveryfee'] : 0;
    $reference='ALMDOC_'.strtoupper(bin2hex(random_bytes(16)));
    mysqli_begin_transaction($con);
    try {
        ald_query($con, 'INSERT INTO tblalumnidocrequest(alumniid,serviceid,token,servicename,fee,deliveryfee,total,delivery,details,receiptemail,reference) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
            array($member['alumniid'],$service['id'],$token,$service['name'],$service['fee'],$deliveryfee,(int)$service['fee']+$deliveryfee,$mode,json_encode($details,JSON_THROW_ON_ERROR),$email,$reference));
        $id=mysqli_insert_id($con); ald_event($con,$id,$member['alumniid'],'submitted');
        // The request and admin notification commit together; retries return the existing request above.
        ald_query($con,"INSERT INTO tblsystemchangelog(actor_userid,actor_name,actor_type,action_type,target_userid,details,page_name,ip_address,status) VALUES(?,?,'Alumni','ALUMNI_DOCUMENT_REQUEST_SUBMITTED',?,?,'alumni-document-requests.php','','unread')",
            array($member['alumniid'],$member['fullname'],(string)$id,'Document request #'.$id.': '.$service['name'].'. Review availability and approve to enable payment.'));
        mysqli_commit($con); return $id;
    } catch(Throwable $e) { mysqli_rollback($con); throw $e; }
}
function ald_update_status($con, $id, $status, $note, $actor) {
    $allowed=array('submitted'=>array('awaiting_payment','rejected'),'awaiting_payment'=>array(),
        'processing'=>array('ready','needs_attention'),'needs_attention'=>array('processing'),
        'ready'=>array('dispatched','completed','needs_attention'),'dispatched'=>array('completed','needs_attention'),'completed'=>array(),'rejected'=>array());
    mysqli_begin_transaction($con);
    try {
        $rows=ald_rows($con,'SELECT * FROM tblalumnidocrequest WHERE id=? FOR UPDATE',array($id));
        if (!$rows) { throw new InvalidArgumentException('Request not found.'); }
        $r=$rows[0];
        if ($r['paymentmode']==='test' && $status!==$r['status']) { throw new InvalidArgumentException('This is a test payment. Do not release documents against it.'); }
        if ($status!==$r['status'] && !in_array($status,$allowed[$r['status']] ?? array(),true)) { throw new InvalidArgumentException('That status change is not available. Refresh the request.'); }
        if ($status==='rejected' && $note==='') { throw new InvalidArgumentException('Please explain why the request cannot be fulfilled.'); }
        $payment=$r['paymentstatus'];
        if ($status==='awaiting_payment' && (int)$r['total']===0) { $status='processing'; $payment='not_required'; }
        if (in_array($status,array('processing','ready','dispatched','completed'),true) && !in_array($payment,array('paid','not_required'),true)) { throw new InvalidArgumentException('Verified payment is required before fulfilment.'); }
        if ($status==='dispatched' && $r['delivery']==='pickup') { throw new InvalidArgumentException('Use Completed after school collection.'); }
        ald_query($con,'UPDATE tblalumnidocrequest SET status=?,paymentstatus=?,staffnote=?,updatedby=? WHERE id=?',array($status,$payment,$note,$actor,$id));
        ald_event($con,$id,$actor,$status,$note); mysqli_commit($con);
    } catch(Throwable $e) { mysqli_rollback($con); throw $e; }
}
// Call only with server-verified Paystack data or a signature-validated webhook.
function ald_record_payment($con, $reference, $data) {
    mysqli_begin_transaction($con);
    try {
        $rows=ald_rows($con,'SELECT * FROM tblalumnidocrequest WHERE reference=? FOR UPDATE',array($reference));
        if (!$rows) { mysqli_commit($con); return false; }
        $r=$rows[0];
        $mode=online_admission_paystack_is_live_mode(online_admission_paystack_config())?'live':'test';
        $valid=($data['status'] ?? '')==='success' && ($data['reference'] ?? '')===$reference && ($data['currency'] ?? '')==='GHS'
            && ($data['domain'] ?? '')===$mode
            && isset($data['amount']) && is_numeric($data['amount']) && (string)$data['amount']===(string)$r['total'] && (int)$r['total']>0
            && isset($data['id']) && preg_match('/^\d{1,30}$/D',(string)$data['id']);
        if (!$valid) { mysqli_commit($con); return false; }
        if ($r['paymentstatus']==='paid') { mysqli_commit($con); return (string)$r['gatewayid']===(string)$data['id']; }
        if ($r['status']!=='awaiting_payment') { mysqli_commit($con); return false; }
        ald_query($con,"UPDATE tblalumnidocrequest SET paymentstatus='paid',status='processing',gatewayid=?,paymentmode=?,paidat=NOW() WHERE id=?",array((string)$data['id'],$mode,$r['id']));
        ald_event($con,$r['id'],'paystack','payment_verified'); mysqli_commit($con); return true;
    } catch(Throwable $e) { mysqli_rollback($con); throw $e; }
}
function ald_checkout_url($url) {
    $p=parse_url((string)$url);
    return is_array($p) && ($p['scheme'] ?? '')==='https' && ($p['host'] ?? '')==='checkout.paystack.com' && !isset($p['user']) && !isset($p['pass']) && !isset($p['port']);
}
function ald_start_payment($con, $id, $memberid) {
    $config=online_admission_paystack_config();
    if (!online_admission_paystack_is_ready($config)) { throw new InvalidArgumentException('Online payment is not configured yet. Please contact the school.'); }
    $rows=ald_rows($con,'SELECT * FROM tblalumnidocrequest WHERE id=? AND alumniid=?',array($id,$memberid));
    if (!$rows || $rows[0]['status']!=='awaiting_payment' || (int)$rows[0]['total']<=0) { throw new InvalidArgumentException('This request is not awaiting payment.'); }
    $r=$rows[0]; $error='';
    if ($r['initat']!==null) {
        $verified=online_admission_paystack_verify($config,$r['reference'],$error);
        if ($verified && ald_record_payment($con,$r['reference'],$verified['data'] ?? array())) { return ''; }
    }
    if (ald_checkout_url($r['checkout'])) { return $r['checkout']; }
    // A fixed reference is never replaced on timeout. Concurrent retries use the same transaction.
    $stmt=ald_query($con,"UPDATE tblalumnidocrequest SET initat=NOW() WHERE id=? AND status='awaiting_payment' AND (initat IS NULL OR initat<DATE_SUB(NOW(),INTERVAL 2 MINUTE))",array($id));
    if (mysqli_stmt_affected_rows($stmt)!==1) { throw new InvalidArgumentException('Payment is being prepared. Please wait two minutes, then check again.'); }
    $payload=array('reference'=>$r['reference'],'email'=>$r['receiptemail'],'amount'=>(int)$r['total'],'currency'=>'GHS',
        'callback_url'=>online_admission_payment_callback_url(array('callback_path'=>'alumni-document-paystack-callback.php'),'callback_path','alumni-document-paystack-callback.php'),
        'metadata'=>array('document_request'=>(int)$r['id']));
    $response=online_admission_paystack_initialize($config,$payload,$error);
    $url=$response['data']['authorization_url'] ?? '';
    if (!$response || ($response['data']['reference'] ?? '')!==$r['reference'] || !ald_checkout_url($url)) {
        throw new InvalidArgumentException('Checkout could not be opened. If you have paid, use Check payment; otherwise try again later or contact the school with your request number.');
    }
    ald_query($con,'UPDATE tblalumnidocrequest SET checkout=? WHERE id=?',array($url,$id));
    return $url;
}
function ald_flash($message, $admin=false) {
    $_SESSION['ald_flash']=$message;
    header('Location: '.($admin?'alumni-document-admin.php':'alumni-document-requests.php'),true,303); exit;
}
