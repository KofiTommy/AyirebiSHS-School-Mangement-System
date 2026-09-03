<?php
session_start();
include('dbstring.php');
include('check-login.php');
include_once('user-management-utils.php');
include_once('alumni-utils.php');
if(!um_is_admin_manager()){header('location:index.php');exit();}
alumni_ensure_tables($con);
$campaignId=trim((string)($_POST['campaignid']??''));
if($_SERVER['REQUEST_METHOD']!=='POST'||!alumni_csrf_valid($_POST['csrf']??'')||$campaignId===''){header('location:alumni-hub.php');exit();}
list($pending,$sent,$failed)=alumni_process_sms_campaign($con,$campaignId,6);
$_SESSION['alumni_flash']=$sent.' SMS sent and '.$failed.' failed in this batch.'.($pending?' Delivery is continuing safely in the background page.':' Campaign delivery is complete.');
if(!$pending){header('Location: alumni-hub.php');exit();}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Delivering alumni SMS</title><style>body{margin:0;font-family:Arial;background:#f4f7fb;color:#162b4d;display:grid;place-items:center;min-height:100vh}.box{background:#fff;border-radius:16px;padding:32px;max-width:470px;text-align:center;box-shadow:0 8px 25px #162b4d1a}.spin{width:40px;height:40px;border:4px solid #dfe7f0;border-top-color:#08766f;border-radius:50%;margin:0 auto 18px;animation:r 1s linear infinite}@keyframes r{to{transform:rotate(360deg)}}</style></head><body><main class="box"><div class="spin"></div><h1>Sending alumni campaign</h1><p>Delivering messages in small safe batches. Please keep this page open.</p><form id="nextBatch" method="post"><input type="hidden" name="csrf" value="<?php echo alumni_safe(alumni_csrf_token()); ?>"><input type="hidden" name="campaignid" value="<?php echo alumni_safe($campaignId); ?>"></form></main><script>setTimeout(function(){document.getElementById('nextBatch').submit();},900);</script></body></html>
