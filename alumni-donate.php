<?php
session_start();
require_once('dbstring.php');
require_once('alumni-utils.php');
require_once('online-admission-utils.php');
alumni_ensure_tables($con);

function alumni_donation_member($con){
    $id=trim((string)($_SESSION['ALUMNI_MEMBER']??''));
    if($id==='')return null;
    $stmt=mysqli_prepare($con,"SELECT * FROM tblalumni WHERE alumniid=? AND status='active' LIMIT 1");
    mysqli_stmt_bind_param($stmt,'s',$id);mysqli_stmt_execute($stmt);$result=mysqli_stmt_get_result($stmt);$member=$result?mysqli_fetch_assoc($result):null;mysqli_stmt_close($stmt);
    return $member;
}
function alumni_donation_back($message,$tone='info'){
    $_SESSION['alumni_donation_flash']=array('message'=>$message,'tone'=>$tone);
    header('location:alumni-donate.php');exit();
}
$member=alumni_donation_member($con);
if(!$member){header('location:alumni-community.php');exit();}
if(empty($_SESSION['alumni_donation_csrf']))$_SESSION['alumni_donation_csrf']=bin2hex(random_bytes(32));

if(isset($_POST['start_donation'])){
    if(!hash_equals($_SESSION['alumni_donation_csrf'],(string)($_POST['csrf']??'')))alumni_donation_back('Your form expired. Please try again.','error');
    $amount=round((float)($_POST['amount']??0),2);$purpose=trim((string)($_POST['purpose']??''));$email=trim((string)($_POST['email']??''));$mobile=trim((string)($_POST['mobile']??''));
    if($amount<1)alumni_donation_back('Enter a donation amount of at least GH₵1.00.','error');
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))alumni_donation_back('Enter a valid email address for the payment receipt.','error');
    $config=online_admission_paystack_config();$config['callback_path']='alumni-donation-paystack-callback.php';
    if(!online_admission_paystack_is_ready($config))alumni_donation_back('Online donations are not configured yet. Please contact AYISEC.','error');
    $reference='ALMDON_'.date('YmdHis').'_'.strtoupper(substr(bin2hex(random_bytes(6)),0,10));
    $payload=array('reference'=>$reference,'email'=>$email,'amount'=>online_admission_money_minor_units($amount),'currency'=>'GHS','callback_url'=>online_admission_payment_callback_url($config,'callback_path','alumni-donation-paystack-callback.php'),'metadata'=>array('alumniid'=>$member['alumniid'],'donor_name'=>$member['fullname'],'donation_purpose'=>$purpose,'custom_fields'=>array(array('display_name'=>'AYISEC Alumnus','variable_name'=>'alumnus_name','value'=>$member['fullname']),array('display_name'=>'Donation purpose','variable_name'=>'donation_purpose','value'=>$purpose!==''?$purpose:'General school support'))));
    $error='';$response=online_admission_paystack_initialize($config,$payload,$error);
    if($response===false||empty($response['data']['authorization_url']))alumni_donation_back($error!==''?$error:'Paystack could not start the donation right now.','error');
    $stmt=mysqli_prepare($con,'INSERT INTO tblalumnidonationpayment(referenceid,alumniid,amount,currency,donationpurpose,donorname,email,mobile,gateway,status,gatewayresponse) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    $currency='GHS';$gateway='paystack';$status='initialized';$gatewayResponse=(string)($response['message']??'Initialized');
    mysqli_stmt_bind_param($stmt,'ssdssssssss',$reference,$member['alumniid'],$amount,$currency,$purpose,$member['fullname'],$email,$mobile,$gateway,$status,$gatewayResponse);
    $saved=mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);
    if(!$saved)alumni_donation_back('Your donation payment could not be recorded. Please try again.','error');
    header('location:'.$response['data']['authorization_url']);exit();
}
$flash=$_SESSION['alumni_donation_flash']??null;unset($_SESSION['alumni_donation_flash']);
$history=mysqli_query($con,"SELECT amount,currency,donationpurpose,status,createdat,paidat FROM tblalumnidonationpayment WHERE alumniid='".mysqli_real_escape_string($con,$member['alumniid'])."' ORDER BY createdat DESC LIMIT 8");
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Support AYISEC | Alumni</title><link rel="icon" type="image/png" href="logo/logo-transparent.png"><style>:root{--n:#102b4b;--t:#08766f;--g:#e3ac36;--p:#f4f7fb;--l:#dce5ed}*{box-sizing:border-box}body{margin:0;background:var(--p);color:#1e293b;font:15px Arial,sans-serif}.wrap{max-width:920px;margin:auto;padding:38px 18px 65px}.hero{background:linear-gradient(120deg,var(--n),var(--t));color:white;padding:32px;border-radius:20px}.hero h1{font:600 32px Georgia,serif;margin:7px 0}.hero p{margin:0;color:#dbeaf0;line-height:1.6}.card{background:#fff;border:1px solid var(--l);border-radius:16px;padding:24px;margin-top:20px;box-shadow:0 7px 18px #102b4b0d}h2{color:var(--n);font:600 23px Georgia,serif;margin:0 0 8px}.note{color:#64748b;line-height:1.55}.form{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:18px}.form label{font-size:12px;font-weight:bold;color:#475569}.form input,.form select{width:100%;margin-top:5px;padding:12px;border:1px solid #c8d6e2;border-radius:9px;font:inherit}.wide{grid-column:span 2}button,a{display:inline-block;border:0;border-radius:9px;padding:12px 15px;background:var(--t);color:white;text-decoration:none;font-weight:bold;cursor:pointer}.back{background:#e9f1f6;color:var(--n);margin-top:16px}.flash{padding:13px;border-radius:9px;margin-top:18px}.flash.error{background:#fff0f0;color:#a12626}.flash.success{background:#e4f8ee;color:#126443}table{width:100%;border-collapse:collapse;margin-top:14px}th,td{text-align:left;padding:11px;border-bottom:1px solid #e7edf2;font-size:13px}th{color:var(--n);background:#eff5f8}@media(max-width:600px){.wrap{padding:22px 12px}.hero,.card{padding:21px}.form{grid-template-columns:1fr}.wide{grid-column:span 1}}</style></head><body><main class="wrap"><section class="hero"><small>AYISEC ALUMNI COMMUNITY</small><h1>Support your school.</h1><p>Your gift is securely processed through AYISEC’s existing Paystack payment platform. Every successful donation is recorded against your Alumni profile.</p><a class="back" href="alumni-community.php">← Back to community</a></section><?php if($flash){?><div class="flash <?php echo alumni_safe($flash['tone']);?>"><?php echo alumni_safe($flash['message']);?></div><?php }?><section class="card"><h2>Make a donation</h2><p class="note">Choose an amount and, if you wish, tell us the school area you would like to support.</p><form method="post" class="form"><input type="hidden" name="csrf" value="<?php echo alumni_safe($_SESSION['alumni_donation_csrf']);?>"><label>Amount (GH₵)<input name="amount" type="number" min="1" step="0.01" required></label><label>Purpose<select name="purpose"><option value="General school support">General school support</option><option value="Student welfare">Student welfare</option><option value="Learning resources">Learning resources</option><option value="Infrastructure project">Infrastructure project</option><option value="Scholarship support">Scholarship support</option></select></label><label>Email for receipt<input name="email" type="email" required value="<?php echo alumni_safe($member['email']);?>"></label><label>Mobile number<input name="mobile" value="<?php echo alumni_safe($member['mobile']);?>"></label><div class="wide"><button name="start_donation">Continue securely to Paystack →</button></div></form></section><section class="card"><h2>My donation history</h2><?php if($history&&mysqli_num_rows($history)>0){?><table><thead><tr><th>Amount</th><th>Purpose</th><th>Status</th><th>Date</th></tr></thead><tbody><?php while($payment=mysqli_fetch_assoc($history)){?><tr><td><?php echo alumni_safe($payment['currency'].' '.number_format((float)$payment['amount'],2));?></td><td><?php echo alumni_safe($payment['donationpurpose']);?></td><td><?php echo alumni_safe(ucfirst($payment['status']));?></td><td><?php echo alumni_safe(date('d M Y',strtotime($payment['paidat']?:$payment['createdat'])));?></td></tr><?php }?></tbody></table><?php }else{?><p class="note">No donations have been recorded from this profile yet.</p><?php }?></section></main></body></html>
