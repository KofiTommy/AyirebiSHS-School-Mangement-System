<?php
require_once __DIR__.'/alumni-document-utils.php';

// Derive actionable reminders from current state: no duplicate or stale payment notifications.
// Do not create tables here: this widget is also loaded before the module is first configured.
function ald_pending_review_count($con) {
    try {
        return (int)ald_rows($con,"SELECT COUNT(*) n FROM tblalumnidocrequest WHERE status='submitted'")[0]['n'];
    } catch(mysqli_sql_exception $e) {
        if((int)$e->getCode()!==1146) { error_log('Document notification count unavailable.'); }
        return 0;
    } catch(RuntimeException $e) { return 0; }
}
function ald_member_payment_notices($con, $memberid) {
    if(!is_string($memberid) || $memberid==='') { return array(); }
    try {
        return ald_rows($con,"SELECT r.id,r.servicename,r.total FROM tblalumnidocrequest r INNER JOIN tblalumni a ON a.alumniid=r.alumniid WHERE r.alumniid=? AND a.status='active' AND r.status='awaiting_payment' AND r.paymentstatus='unpaid' AND r.total>0 ORDER BY r.id DESC LIMIT 5",array($memberid));
    } catch(mysqli_sql_exception $e) {
        if((int)$e->getCode()!==1146) { error_log('Alumni payment notices unavailable.'); }
        return array();
    } catch(RuntimeException $e) { return array(); }
}
function ald_render_member_payment_notices($con, $memberid) {
    $notices=ald_member_payment_notices($con,$memberid);
    if(!$notices) { return; }
    ?>
    <section class="ald-payment-notices" aria-labelledby="ald-payment-notices-title" role="status" style="margin:20px 0;padding:20px;border:1px solid #b8d8d4;border-radius:14px;background:#e7f4ef;color:#163a46;overflow-wrap:anywhere">
        <h2 id="ald-payment-notices-title" style="margin:0 0 8px;font:700 20px/1.4 system-ui">Approved — you can now pay</h2>
        <p style="margin:0 0 12px">The school has approved your document request. Open it below to check the details and make payment.</p>
        <ul style="margin:0;padding-left:20px">
        <?php foreach($notices as $notice) { ?>
            <li style="margin:10px 0"><strong><?php echo alumni_safe($notice['servicename']); ?></strong> · Request #<?php echo (int)$notice['id']; ?> · <?php echo ald_price($notice['total']); ?>
                <a href="alumni-document-requests.php?id=<?php echo (int)$notice['id']; ?>#request-detail" style="display:inline-flex;align-items:center;min-height:44px;margin:4px 8px;padding:8px 14px;background:#08766f;border-radius:8px;color:#fff;text-decoration:none">Review &amp; pay</a>
            </li>
        <?php } ?>
        </ul>
        <p style="margin:10px 0 0;font-size:13px">Already debited? Use Check payment inside the request before trying again. See My requests for all requests.</p>
    </section>
    <?php
}
