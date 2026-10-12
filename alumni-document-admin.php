<?php
session_start();
require_once __DIR__.'/dbstring.php';
require_once __DIR__.'/check-login.php';
require_once __DIR__.'/user-management-utils.php';
require_once __DIR__.'/alumni-document-utils.php';
require_once __DIR__.'/alumni-document-view.php';
if(!um_is_admin_manager()) { http_response_code(403); exit('Access denied.'); }
header('Cache-Control: private, no-store');
ald_schema($con);
$error=''; $actor=(string)($_SESSION['USERID']??'');
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if(!alumni_csrf_valid($_POST['csrf']??'')) { throw new InvalidArgumentException('Your form expired. Refresh and try again.'); }
        $action=ald_text($_POST,'action',20);
        if($action==='service') {
            $id=ald_text($_POST,'id',20); $name=ald_text($_POST,'name',150); $description=ald_text($_POST,'description',1000);
            if($name==='') { throw new InvalidArgumentException('Enter a service name.'); }
            $fee=ald_money(ald_text($_POST,'fee',20)); $deliveryfee=ald_money(ald_text($_POST,'deliveryfee',20));
            $pickup=isset($_POST['pickup'])?1:0; $email=isset($_POST['email'])?1:0; $physical=isset($_POST['physical'])?1:0; $enabled=isset($_POST['enabled'])?1:0;
            if(!$pickup && !$email && !$physical) { throw new InvalidArgumentException('Enable at least one delivery method.'); }
            if(stripos($name,'WAEC')!==false && stripos($name,'certificate')!==false && $email) { throw new InvalidArgumentException('Original WAEC certificates cannot be delivered by email. Use collection or physical delivery.'); }
            if($id!=='') {
                ald_query($con,'UPDATE tblalumnidocservice SET name=?,description=?,fee=?,deliveryfee=?,pickup=?,email=?,physical=?,enabled=?,updatedby=? WHERE id=?',array($name,$description,$fee,$deliveryfee,$pickup,$email,$physical,$enabled,$actor,$id));
            } else {
                ald_query($con,'INSERT INTO tblalumnidocservice(name,description,fee,deliveryfee,pickup,email,physical,enabled,updatedby) VALUES(?,?,?,?,?,?,?,?,?)',array($name,$description,$fee,$deliveryfee,$pickup,$email,$physical,$enabled,$actor));
            }
            ald_flash('Document service saved. Existing requests keep their original fees.',true);
        } elseif($action==='status') {
            ald_update_status($con,ald_text($_POST,'id',20),ald_text($_POST,'status',30),ald_text($_POST,'note',2000),$actor);
            ald_flash('Request updated. The alumnus can see your update in their account.',true);
        } elseif($action==='verify') {
            $rows=ald_rows($con,'SELECT reference FROM tblalumnidocrequest WHERE id=?',array(ald_text($_POST,'id',20)));
            if(!$rows) { throw new InvalidArgumentException('Request not found.'); }
            $gatewayError=''; $result=online_admission_paystack_verify(online_admission_paystack_config(),$rows[0]['reference'],$gatewayError);
            $ok=$result && ald_record_payment($con,$rows[0]['reference'],$result['data']??array());
            ald_flash($ok?'Payment verified. The request is ready for processing.':'Payment is not confirmed. Check the gateway before collecting another payment.',true);
        }
    } catch(InvalidArgumentException $e) { $error=$e->getMessage(); }
    catch(Throwable $e) { error_log('Alumni document administration failed: '.get_class($e)); $error='The change could not be saved. Check for a duplicate service name, refresh and try again.'; }
}
ald_page_start(true);
if(online_admission_paystack_is_ready(online_admission_paystack_config()) && !online_admission_paystack_is_live_mode(online_admission_paystack_config())) { ?><div class="notice error">Paystack is in test mode. No real funds will be collected. Use live configuration before accepting actual document orders.</div><?php }
if($error!=='') { ?><div class="notice error" role="alert"><?php echo alumni_safe($error); ?></div><?php }
if(!online_admission_paystack_is_ready(online_admission_paystack_config())) { ?><div class="notice">Online payments are not configured. Set up the existing Paystack integration before enabling paid services.</div><?php }
if(isset($_GET['id'])) {
    $rows=ald_rows($con,'SELECT r.*,a.fullname,a.graduationyear FROM tblalumnidocrequest r LEFT JOIN tblalumni a ON a.alumniid=r.alumniid WHERE r.id=?',array((int)$_GET['id']));
    if($rows) { ?><p><?php echo alumni_safe(($rows[0]['fullname']??'Alumnus').' · Class of '.($rows[0]['graduationyear']??'')); ?></p><?php ald_request_detail($con,$rows[0],true); }
}
$filter=is_string($_GET['status']??null)?$_GET['status']:'';
?>
<form method="get" class="card"><label>Filter requests<select name="status"><option value="">All statuses</option><?php foreach(array('submitted','awaiting_payment','processing','needs_attention','ready','dispatched','completed','rejected') as $s) { ?><option value="<?php echo $s; ?>" <?php echo $filter===$s?'selected':''; ?>><?php echo ald_label($s); ?></option><?php } ?></select></label><div class="actions"><button>Apply filter</button></div></form>
<?php ald_list($con,null,$filter); ?>
<section class="card"><h2>Services and fees</h2><p>Set the school-approved charge in Ghana cedis, choose delivery methods and enable the service. A fee of 0.00 makes a service free after review. Fees below are school service charges, not a statement of official WAEC fees.</p><p class="muted">Physical delivery has a fixed additional fee per request. Describe supported locations and turnaround times below, and confirm each destination before approval. Disable delivery where the fixed charge is not suitable. Staff must confirm identity before releasing original documents.</p></section>
<div class="grid">
<?php $services=ald_rows($con,'SELECT * FROM tblalumnidocservice ORDER BY id'); $services[]=array('id'=>'','name'=>'','description'=>'','fee'=>null,'deliveryfee'=>0,'pickup'=>1,'email'=>0,'physical'=>0,'enabled'=>0); foreach($services as $s) { ?>
<form method="post" class="card"><?php ald_csrf(); ?><input type="hidden" name="id" value="<?php echo alumni_safe($s['id']); ?>"><h3><?php echo $s['id']===''?'Add another service':alumni_safe($s['name']); ?></h3><label>Service name<input name="name" maxlength="150" required value="<?php echo alumni_safe($s['name']); ?>"></label><label>Description / requirements<textarea name="description" maxlength="1000"><?php echo alumni_safe($s['description']); ?></textarea></label><div class="fields"><label>Service fee (GHS)<input type="number" name="fee" min="0" max="999999.99" step="0.01" required placeholder="Set fee" value="<?php echo $s['fee']===null?'':number_format($s['fee']/100,2,'.',''); ?>"></label><label>Physical delivery fee (GHS)<input type="number" name="deliveryfee" min="0" max="999999.99" step="0.01" required value="<?php echo number_format($s['deliveryfee']/100,2,'.',''); ?>"></label></div><div class="checks"><?php foreach(array('pickup'=>'School collection','email'=>'Email','physical'=>'Physical delivery') as $key=>$label) { ?><label><input type="checkbox" name="<?php echo $key; ?>" value="1" <?php echo $s[$key]?'checked':''; ?>><?php echo $label; ?></label><?php } ?></div><label><input type="checkbox" name="enabled" value="1" <?php echo $s['enabled']?'checked':''; ?>>Available for alumni requests</label><div class="actions"><button name="action" value="service">Save service</button></div></form>
<?php } ?></div></main></body></html>
