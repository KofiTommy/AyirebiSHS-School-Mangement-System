<?php
session_start();
require_once __DIR__.'/dbstring.php';
require_once __DIR__.'/alumni-document-utils.php';
require_once __DIR__.'/alumni-document-view.php';
require_once __DIR__.'/alumni-document-notifications.php';
header('Cache-Control: private, no-store');
$member=ald_member($con);
if(!$member) { header('Location: alumni-join.php#old-student-login'); exit; }
ald_schema($con);
if(empty($_SESSION['ald_token'])) { $_SESSION['ald_token']=bin2hex(random_bytes(32)); }
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if(!alumni_csrf_valid($_POST['csrf']??'')) { throw new InvalidArgumentException('Your form expired. Refresh the page and try again.'); }
        $action=ald_text($_POST,'action',20);
        if($action==='request') {
            $token=ald_text($_POST,'token',64);
            if(!hash_equals($_SESSION['ald_token'],$token)) { throw new InvalidArgumentException('This form was already submitted or expired. Check My requests before submitting again.'); }
            $id=ald_create($con,$member,$_POST,$token);
            $_SESSION['ald_token']=bin2hex(random_bytes(32));
            ald_flash('Request #'.$id.' submitted. The school will review it before payment is available.');
        } elseif($action==='pay') {
            $url=ald_start_payment($con,ald_text($_POST,'id',20),$member['alumniid']);
            if($url==='') { ald_flash('Payment confirmed. Your document request is being processed.'); }
            header('Location: '.$url,true,303); exit;
        } elseif($action==='verify') {
            $rows=ald_rows($con,'SELECT reference FROM tblalumnidocrequest WHERE id=? AND alumniid=?',array(ald_text($_POST,'id',20),$member['alumniid']));
            if(!$rows) { throw new InvalidArgumentException('Request not found.'); }
            $gatewayError=''; $response=online_admission_paystack_verify(online_admission_paystack_config(),$rows[0]['reference'],$gatewayError);
            $ok=$response && ald_record_payment($con,$rows[0]['reference'],$response['data']??array());
            ald_flash($ok?'Payment confirmed. Thank you.':'Payment is not yet confirmed. If you were debited, do not pay again; check later or contact the school with your request number.');
        }
    } catch(InvalidArgumentException $e) { $error=$e->getMessage(); }
    catch(Throwable $e) { error_log('Alumni document request operation failed: '.get_class($e)); $error='We could not complete that action. Check your requests before trying again, or contact the school.'; }
}
$services=ald_rows($con,'SELECT * FROM tblalumnidocservice WHERE enabled=1 AND fee IS NOT NULL ORDER BY name');
ald_page_start();
ald_render_member_payment_notices($con,$member['alumniid']);
if(online_admission_paystack_is_ready(online_admission_paystack_config()) && !online_admission_paystack_is_live_mode(online_admission_paystack_config())) { ?><div class="notice">Payment testing mode is active. Test payments cannot be used to obtain documents.</div><?php }
if($error!=='') { ?><div class="notice error" role="alert"><?php echo alumni_safe($error); ?></div><?php }
if(isset($_GET['id'])) {
    $rows=ald_rows($con,'SELECT * FROM tblalumnidocrequest WHERE id=? AND alumniid=?',array((int)$_GET['id'],$member['alumniid']));
    if($rows) { ald_request_detail($con,$rows[0]); }
}
ald_list($con,$member['alumniid']);
$value=function($key,$default='') { return alumni_safe(is_string($_POST[$key]??null)?$_POST[$key]:$default); };
?>
<section class="card"><h2>Start a document request</h2>
<?php if(!$services) { ?><p class="muted">Document services are being prepared. Please contact the school office for assistance.</p><?php } else { ?>
<p class="muted">Fees are fixed when you submit. Physical delivery charges apply only where offered by the school. Original certificates cannot be delivered by email.</p>
<form method="post" id="document-request-form"><?php ald_csrf(); ?><input type="hidden" name="action" value="request"><input type="hidden" name="token" value="<?php echo alumni_safe($_SESSION['ald_token']); ?>">
<div class="fields"><label>Document service<select name="service" id="document-service" required><option value="">Choose a document</option><?php foreach($services as $s) { ?><option value="<?php echo (int)$s['id']; ?>" data-fee="<?php echo (int)$s['fee']; ?>" data-deliveryfee="<?php echo (int)$s['deliveryfee']; ?>" data-pickup="<?php echo (int)$s['pickup']; ?>" data-email="<?php echo (int)$s['email']; ?>" data-physical="<?php echo (int)$s['physical']; ?>" data-description="<?php echo alumni_safe($s['description']); ?>" <?php echo ($_POST['service']??'')==(string)$s['id']?'selected':''; ?>><?php echo alumni_safe($s['name'].' — '.ald_price($s['fee'])); ?></option><?php } ?></select></label>
<label>How should we deliver it?<select name="delivery" id="document-delivery" required><option value="">Choose delivery</option><?php foreach(array('pickup'=>'Collect at school','email'=>'Send by email','physical'=>'Physical / postal delivery') as $key=>$label) { ?><option value="<?php echo $key; ?>" <?php echo ($_POST['delivery']??'')===$key?'selected':''; ?>><?php echo $label; ?></option><?php } ?></select></label></div>
<p id="service-description" class="muted"></p><div class="price" id="document-total" aria-live="polite">Choose a service and delivery method to see the total.</div>
<div class="fields"><label>Recipient full name<input name="recipient" maxlength="180" required value="<?php echo $value('recipient',$member['fullname']); ?>"></label><label>Recipient phone<input name="phone" type="tel" maxlength="40" required value="<?php echo $value('phone',$member['mobile']??''); ?>"></label><label>Payment receipt email<input name="receiptemail" type="email" maxlength="180" required value="<?php echo $value('receiptemail',$member['email']??''); ?>"></label><label data-delivery-fields="email">Document delivery email<input name="email" type="email" maxlength="180" value="<?php echo $value('email'); ?>"></label>
<label data-delivery-fields="physical" class="wide">House address / postal address<textarea name="address" maxlength="600" placeholder="House number, street, area, P.O. Box and post office, as applicable"><?php echo $value('address'); ?></textarea></label><label data-delivery-fields="physical">GhanaPost GPS address<input name="gps" maxlength="40" value="<?php echo $value('gps'); ?>"></label><label data-delivery-fields="physical">Town / city<input name="city" maxlength="100" value="<?php echo $value('city'); ?>"></label><label data-delivery-fields="physical">Region / state<input name="region" maxlength="100" value="<?php echo $value('region'); ?>"></label><label data-delivery-fields="physical">Country<input name="country" maxlength="100" value="<?php echo $value('country','Ghana'); ?>"></label>
<label>WAEC index number (if applicable)<input name="indexnumber" maxlength="40" value="<?php echo $value('indexnumber'); ?>"></label><label>Examination / completion year<input name="examyear" inputmode="numeric" pattern="(19|20)[0-9]{2}" maxlength="4" value="<?php echo $value('examyear',(string)($member['graduationyear']??'')); ?>"></label><label class="wide">Purpose and delivery instructions<textarea name="purpose" maxlength="1000" placeholder="Receiving institution, application reference or special instructions. Do not enter payment card details."><?php echo $value('purpose'); ?></textarea></label></div>
<p class="muted">Only authorised school staff can view your delivery details. The school prepares and sends approved documents; payment does not itself issue a document. Requests for lost/replacement WAEC certificates must be directed to WAEC.</p><button type="submit">Submit for school review</button></form><?php } ?></section></main><script src="js/alumni-documents.js?v=20261012"></script></body></html>
