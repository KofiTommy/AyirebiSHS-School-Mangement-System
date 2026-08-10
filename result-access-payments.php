<?php
session_start(); include('check-login.php'); include('dbstring.php'); include_once('result-access-utils.php');
if(!isset($_SESSION['ACCESSLEVEL']) || $_SESSION['ACCESSLEVEL']!=='administrator'){ http_response_code(403); exit('Administrator access required.'); }
result_access_ensure_tables($con);
function rap_esc($value){ return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function rap_money($value){ return 'GHS '.number_format((float)$value,2); }
$homePage=(isset($_SESSION['SYSTEMTYPE']) && $_SESSION['SYSTEMTYPE']==='super_user')?'super.php':'admin.php';
$search=trim((string)(isset($_GET['search'])?$_GET['search']:'')); $status=trim((string)(isset($_GET['status'])?$_GET['status']:'')); $batch=trim((string)(isset($_GET['batchid'])?$_GET['batchid']:'')); $year=trim((string)(isset($_GET['academicyear'])?$_GET['academicyear']:'')); $term=(int)(isset($_GET['termname'])?$_GET['termname']:0); $from=trim((string)(isset($_GET['from'])?$_GET['from']:'')); $to=trim((string)(isset($_GET['to'])?$_GET['to']:''));
$where=array('1=1');
if($search!==''){ $s=mysqli_real_escape_string($con,$search); $where[]="(p.userid LIKE '%$s%' OR p.reference LIKE '%$s%' OR su.firstname LIKE '%$s%' OR su.surname LIKE '%$s%' OR su.othernames LIKE '%$s%')"; }
if(in_array($status,array('success','initialized','failed'),true)){ $where[]="p.status='".mysqli_real_escape_string($con,$status)."'"; }
if($batch!==''){ $where[]="p.batchid='".mysqli_real_escape_string($con,$batch)."'"; }
if($year!==''){ $where[]="p.academicyear='".mysqli_real_escape_string($con,$year)."'"; }
if($term>0){ $where[]="p.termname=$term"; }
if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){ $where[]="p.createdat>='".mysqli_real_escape_string($con,$from)." 00:00:00'"; }
if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){ $where[]="p.createdat<='".mysqli_real_escape_string($con,$to)." 23:59:59'"; }
$whereSql=implode(' AND ',$where);
$summary=mysqli_query($con,"SELECT COUNT(*) total_count, SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) successful_count, SUM(CASE WHEN status='success' THEN amount ELSE 0 END) received_amount, SUM(CASE WHEN status='initialized' THEN 1 ELSE 0 END) pending_count, SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) failed_count FROM tblresultaccesspayment p WHERE $whereSql"); $totals=$summary?mysqli_fetch_assoc($summary):array();
$payments=array(); $sql="SELECT p.*, CONCAT_WS(' ',su.firstname,su.othernames,su.surname) student_name, ce.class_name, bh.batch batch_name FROM tblresultaccesspayment p LEFT JOIN tblsystemuser su ON su.userid=p.userid LEFT JOIN tblclassentry ce ON ce.class_entryid=p.classid LEFT JOIN tblbatch bh ON bh.batchid=p.batchid WHERE $whereSql ORDER BY COALESCE(p.paidat,p.createdat) DESC LIMIT 500"; $res=mysqli_query($con,$sql); if($res){ while($row=mysqli_fetch_assoc($res)){ $payments[]=$row; } }
$batches=array(); $br=mysqli_query($con,'SELECT batchid,batch FROM tblbatch ORDER BY batch DESC'); if($br){while($r=mysqli_fetch_assoc($br)){$batches[]=$r;}}
?>
<style>
/* Kept on this page so mobile browsers always show the selected filter text. */
.rap .rap-filter select,.rap .rap-filter input{background:#fff!important;color:#102a43!important;-webkit-text-fill-color:#102a43!important;border:2px solid #7893aa!important;opacity:1!important;color-scheme:light!important}
.rap .rap-filter select option,.rap .rap-filter select option:checked{background:#fff!important;color:#102a43!important;-webkit-text-fill-color:#102a43!important}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.rap .rap-filter select').forEach(function (select) {
        var makeReadable = function () {
            select.style.setProperty('background-color', '#ffffff', 'important');
            select.style.setProperty('color', '#102a43', 'important');
            select.style.setProperty('-webkit-text-fill-color', '#102a43', 'important');
            Array.prototype.forEach.call(select.options, function (option) {
                option.style.setProperty('background-color', option.selected ? '#dff4e8' : '#ffffff', 'important');
                option.style.setProperty('color', '#102a43', 'important');
                option.style.setProperty('-webkit-text-fill-color', '#102a43', 'important');
            });
        };
        makeReadable();
        select.addEventListener('change', makeReadable);
    });
});
</script>
<style>
.rap-custom-select{position:relative;min-width:0}.rap-custom-select__button{width:100%;min-height:43px;padding:10px 36px 10px 11px;border:2px solid #7893aa;border-radius:7px;background:#fff;color:#102a43;font:16px Arial,sans-serif;text-align:left;position:relative}.rap-custom-select__button:after{content:'⌄';position:absolute;right:12px;font-size:20px;top:7px;color:#075a35}.rap-custom-select__list{display:none;position:absolute;z-index:99;left:0;right:0;top:calc(100% + 4px);max-height:250px;overflow:auto;background:#fff;border:2px solid #087443;border-radius:8px;box-shadow:0 8px 20px rgba(23,49,75,.2)}.rap-custom-select.is-open .rap-custom-select__list{display:block}.rap-custom-select__option{display:block;width:100%;border:0;background:#fff;color:#102a43;padding:11px;text-align:left;font:15px Arial,sans-serif}.rap-custom-select__option.is-selected,.rap-custom-select__option:hover{background:#dff4e8;color:#075a35;font-weight:bold}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.rap .rap-filter select').forEach(function (select) {
        if (select.dataset.customised === '1') return;
        select.dataset.customised = '1';
        var wrap = document.createElement('div'); wrap.className = 'rap-custom-select';
        var button = document.createElement('button'); button.type = 'button'; button.className = 'rap-custom-select__button'; button.style.cssText = 'display:block;background:#fff;color:#102a43;border:2px solid #7893aa;text-align:left;';
        var list = document.createElement('div'); list.className = 'rap-custom-select__list';
        var refresh = function () {
            button.textContent = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : 'Select';
            Array.prototype.forEach.call(list.children, function (item) { item.classList.toggle('is-selected', item.dataset.value === select.value); });
        };
        Array.prototype.forEach.call(select.options, function (option) {
            var item = document.createElement('button'); item.type = 'button'; item.className = 'rap-custom-select__option'; item.dataset.value = option.value; item.textContent = option.text; item.style.cssText = 'display:block;background:#fff;color:#102a43;border:0;text-align:left;';
            item.addEventListener('click', function () { select.value = option.value; refresh(); wrap.classList.remove('is-open'); select.dispatchEvent(new Event('change', {bubbles:true})); });
            list.appendChild(item);
        });
        button.addEventListener('click', function () { document.querySelectorAll('.rap-custom-select.is-open').forEach(function (open) { if(open !== wrap) open.classList.remove('is-open'); }); wrap.classList.toggle('is-open'); });
        select.parentNode.insertBefore(wrap, select); wrap.appendChild(button); wrap.appendChild(list); select.style.display = 'none'; wrap.appendChild(select); refresh();
    });
    document.addEventListener('click', function (event) { if (!event.target.closest('.rap-custom-select')) document.querySelectorAll('.rap-custom-select.is-open').forEach(function (open) { open.classList.remove('is-open'); }); });
});
</script>
<!doctype html><html><head><?php include('links.php');?><title>Result Payment Dashboard</title><style>body{background:#f4f8fb;color:#17314b;font-family:Arial,sans-serif}.rap{max-width:1250px;margin:30px auto;background:#fff;padding:28px;border-radius:18px;box-shadow:0 8px 25px #dce6ee}.rap-head{display:flex;justify-content:space-between;gap:14px;align-items:start}.rap a{color:#075a35}.rap-home{background:#e7eef5;padding:11px 14px;border-radius:9px;text-decoration:none;font-weight:bold;white-space:nowrap}.rap-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:22px 0}.rap-card{padding:16px;border:1px solid #dce6ee;border-radius:13px;background:#f8fbfd}.rap-card span{display:block;color:#587086;font-size:13px}.rap-card strong{font-size:23px}.rap-filter{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;background:#f8fbfd;padding:16px;border-radius:13px}.rap-filter input,.rap-filter select{width:100%;box-sizing:border-box;padding:10px;background:#fff;color:#102a43;border:1px solid #9db2c5;border-radius:7px}.rap-filter button{background:#087443;color:#fff;border:0;padding:11px 16px;border-radius:8px;font-weight:bold}.rap-table{overflow:auto;margin-top:20px}.rap-table table{min-width:970px;width:100%;border-collapse:collapse}.rap-table th,.rap-table td{padding:11px;border-bottom:1px solid #e1e9f0;text-align:left;font-size:14px}.rap-badge{padding:5px 8px;border-radius:999px;font-weight:bold;font-size:12px}.rap-success{background:#dcfce7;color:#166534}.rap-initialized{background:#fef3c7;color:#92400e}.rap-failed{background:#fee2e2;color:#991b1b}@media(max-width:720px){.rap{margin:12px;padding:18px}.rap-head{display:block}.rap-home{display:inline-block;margin-top:8px}.rap-grid,.rap-filter{grid-template-columns:1fr 1fr}}</style></head><body><main class="rap"><div class="rap-head"><div><h1>Result Payment Dashboard</h1><p>Verified result-viewing payments and access attempts. Successful payments unlock the student automatically.</p></div><div><a class="rap-home" href="<?php echo rap_esc($homePage); ?>"><i class="fa fa-home"></i> Admin Home</a> &nbsp;<a class="rap-home" href="result-access-admin.php"><i class="fa fa-lock"></i> Result Access Settings</a></div></div><div class="rap-grid"><div class="rap-card"><span>Total attempts</span><strong><?php echo number_format((int)(isset($totals['total_count'])?$totals['total_count']:0));?></strong></div><div class="rap-card"><span>Successful payments</span><strong><?php echo number_format((int)(isset($totals['successful_count'])?$totals['successful_count']:0));?></strong></div><div class="rap-card"><span>Amount received</span><strong><?php echo rap_money(isset($totals['received_amount'])?$totals['received_amount']:0);?></strong></div><div class="rap-card"><span>Pending / Failed</span><strong><?php echo number_format((int)(isset($totals['pending_count'])?$totals['pending_count']:0));?> / <?php echo number_format((int)(isset($totals['failed_count'])?$totals['failed_count']:0));?></strong></div></div><form class="rap-filter" method="get"><input name="search" value="<?php echo rap_esc($search);?>" placeholder="Student name, ID or Paystack reference"><select name="status"><option value="">All statuses</option><?php foreach(array('success'=>'Successful','initialized'=>'Pending','failed'=>'Failed') as $key=>$label){?><option value="<?php echo $key;?>"<?php echo $status===$key?' selected':'';?>><?php echo $label;?></option><?php }?></select><select name="batchid"><option value="">All batches</option><?php foreach($batches as $r){?><option value="<?php echo rap_esc($r['batchid']);?>"<?php echo $batch===(string)$r['batchid']?' selected':'';?>><?php echo rap_esc($r['batch']);?></option><?php }?></select><input name="academicyear" value="<?php echo rap_esc($year);?>" placeholder="Academic year"><select name="termname"><option value="">All semesters</option><option value="1"<?php echo $term===1?' selected':'';?>>Semester 1</option><option value="2"<?php echo $term===2?' selected':'';?>>Semester 2</option><option value="3"<?php echo $term===3?' selected':'';?>>Semester 3</option></select><input type="date" name="from" value="<?php echo rap_esc($from);?>"><input type="date" name="to" value="<?php echo rap_esc($to);?>"><button type="submit"><i class="fa fa-filter"></i> Filter Payments</button></form><div class="rap-table"><table><thead><tr><th>Student</th><th>Result scope</th><th>Amount</th><th>Status</th><th>Paystack reference</th><th>Payment date</th><th>Gateway response</th></tr></thead><tbody><?php if(empty($payments)){?><tr><td colspan="7">No result payment records match these filters.</td></tr><?php } foreach($payments as $p){$name=trim((string)$p['student_name']);?><tr><td><strong><?php echo rap_esc($name!==''?$name:'Student not found');?></strong><br><small><?php echo rap_esc($p['userid']);?></small></td><td><?php echo rap_esc(isset($p['class_name'])&&$p['class_name']!==''?$p['class_name']:$p['classid']);?><br><small><?php echo rap_esc(isset($p['batch_name'])&&$p['batch_name']!==''?$p['batch_name']:$p['batchid']);?> · <?php echo rap_esc($p['academicyear']);?> · Semester <?php echo (int)$p['termname'];?></small></td><td><?php echo rap_money($p['amount']);?></td><td><span class="rap-badge rap-<?php echo rap_esc($p['status']);?>"><?php echo rap_esc(ucfirst($p['status']));?></span></td><td><code><?php echo rap_esc($p['reference']);?></code></td><td><?php echo rap_esc($p['paidat']!==null&&$p['paidat']!==''?$p['paidat']:$p['createdat']);?></td><td><?php echo rap_esc($p['gatewayresponse']);?></td></tr><?php }?></tbody></table></div></main></body></html>
