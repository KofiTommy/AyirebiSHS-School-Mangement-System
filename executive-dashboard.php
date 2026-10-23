<?php
ob_start(null, 1048576);
session_start();
include("check-login.php");
if (($_SESSION['ACCESSLEVEL'] ?? '') !== 'administrator') {
    http_response_code(403);
    exit('Administrator access required.');
}
/* Remember this dashboard as the admin's Home target for other pages */
$_SESSION['ADMIN_HOME_DASHBOARD'] = 'executive-dashboard.php';
include("company.php");

/* ============================================================
   Executive dashboard — international-standard overview page.
   Responsive (laptop / tablet / phone) and colour-mode aware.
   ============================================================ */

if(!function_exists('exd_e')){
    function exd_e($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}
if(!function_exists('exd_scalar')){
    function exd_scalar($con, $sql){
        $result = @mysqli_query($con, $sql);
        if(!$result){ return 0; }
        $row = mysqli_fetch_row($result);
        return (float)($row && isset($row[0]) ? $row[0] : 0);
    }
}
if(!function_exists('exd_when')){
    function exd_when($value){
        $ts = strtotime((string)$value);
        if($ts === false){ return ''; }
        $today = strtotime(date('Y-m-d'));
        $day = strtotime(date('Y-m-d', $ts));
        if($day === $today){ return date('g:i A', $ts); }
        if($day === strtotime('-1 day', $today)){ return 'Yesterday'; }
        return date('d M Y', $ts);
    }
}
if(!function_exists('exd_day_label')){
    function exd_day_label($value){
        $ts = strtotime((string)$value);
        if($ts === false){ return ''; }
        $today = strtotime(date('Y-m-d'));
        $day = strtotime(date('Y-m-d', $ts));
        if($day === $today){ return 'Today'; }
        if($day === strtotime('-1 day', $today)){ return 'Yesterday'; }
        return date('l, d F Y', $ts);
    }
}

$_Currency = trim((string)($_SESSION['SYMBOL'] ?? 'GH&#8373;'));
$_FullName = trim((string)($_SESSION['FULLNAME'] ?? 'Administrator'));
if($_FullName === ''){ $_FullName = 'Administrator'; }
$_Role = trim((string)($_SESSION['SYSTEMTYPE'] ?? ''));
$_AccessLevel = trim((string)($_SESSION['ACCESSLEVEL'] ?? ''));
if($_AccessLevel === 'administrator'){
    $_RoleLabel = ($_Role === 'super_user') ? 'Super Administrator' : 'Administrator';
}elseif($_Role !== ''){
    $_RoleLabel = ucwords(str_replace(array('_', '-'), ' ', $_Role));
}else{
    $_RoleLabel = 'Staff';
}
$_NameParts = preg_split('/\s+/', $_FullName);
$_Initials = strtoupper(substr($_NameParts[0], 0, 1) . substr(end($_NameParts), 0, 1));

/* ---------- Topbar indicators (same data as the classic admin menu) ---------- */
$_ShowLiveUsersIndicator = ($_AccessLevel === 'administrator');
$_LiveUsersCount = 0;
if($_ShowLiveUsersIndicator && function_exists('um_count_live_users')){
    $_LiveUsersCount = (int)um_count_live_users($con, 5);
}
$_PendingDocumentReviews = 0;
if(function_exists('um_is_admin_manager') && function_exists('um_current_user_can_access_module')
   && um_is_admin_manager() && um_current_user_can_access_module($con, 'alumni_management')){
    require_once __DIR__.'/alumni-document-notifications.php';
    $_PendingDocumentReviews = (int)ald_pending_review_count($con);
}

/* ---------- System change notifications (same source as the classic admin panel) ---------- */
if (empty($_SESSION['executive_csrf'])) {
    $_SESSION['executive_csrf'] = bin2hex(random_bytes(32));
}
if(isset($_POST['mark_changes_read']) && $_AccessLevel === 'administrator'){
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['executive_csrf'], $_POST['csrf'])) {
        http_response_code(403);
        exit('Your session could not be verified. Reload the dashboard and try again.');
    }
    mysqli_query($con, "UPDATE tblsystemchangelog SET status='read' WHERE status='unread'");
    header("Location: executive-dashboard.php?tab=notifications");
    exit();
}
$_ChangeNotifications = array();
$_UnreadChangeCount = 0;
if($_AccessLevel === 'administrator'){
    // Viewing the dashboard must not delete audit history.
    $_ChangeQ = @mysqli_query($con, "SELECT logid, actor_userid, actor_name, actor_type, action_type, target_userid, details, datetimeentry, status
        FROM tblsystemchangelog
        ORDER BY datetimeentry DESC LIMIT 8");
    if($_ChangeQ){ while($r = mysqli_fetch_array($_ChangeQ, MYSQLI_ASSOC)){ $_ChangeNotifications[] = $r; } }
    $_AllChangeNotifications = array();
    $_AllChangeQ = @mysqli_query($con, "SELECT logid, actor_userid, actor_name, actor_type, action_type, target_userid, details, datetimeentry, status
        FROM tblsystemchangelog
        ORDER BY datetimeentry DESC LIMIT 60");
    if($_AllChangeQ){ while($r = mysqli_fetch_array($_AllChangeQ, MYSQLI_ASSOC)){ $_AllChangeNotifications[] = $r; } }
    $_UnreadQ = @mysqli_query($con, "SELECT COUNT(*) AS c FROM tblsystemchangelog
        WHERE status='unread'");
    if($_UnreadQ && $r = mysqli_fetch_array($_UnreadQ, MYSQLI_ASSOC)){ $_UnreadChangeCount = (int)$r['c']; }
}
/* Review-link mapping — identical to the classic dashboard notification table */
if(!function_exists('exd_change_action')){
    function exd_change_action($row){
        $type = strtoupper(trim((string)$row['action_type']));
        $target = trim((string)$row['target_userid']);
        if($type === 'ONLINE_ADMISSION_SUBMITTED' && $target !== ''){
            return array('online-admission-admin.php?edit_application='.urlencode($target).'#edit-application', 'Review Admission');
        }
        if($type === 'ONLINE_ADMISSION_HEADMASTER_APPROVED' && $target !== ''){
            return array('online-admission-admin.php?edit_application='.urlencode($target).'#edit-application', 'Open Approved Admission');
        }
        if($type === 'ONLINE_ADMISSION_HELP_REQUEST'){
            return array('online-admission-admin.php#help-requests', 'Open Help Requests');
        }
        if($type === 'PORTAL_HELP_REQUEST'){
            return array('admin.php#portal-help-requests', 'Open Portal Help');
        }
        if($type === 'ALUMNI_REGISTRATION_SUBMITTED'){
            return array('alumni-hub.php#pending-approvals', 'Review Alumni Request');
        }
        if($type === 'ALUMNI_DOCUMENT_REQUEST_SUBMITTED' && ctype_digit($target)){
            return array('alumni-document-admin.php?id='.(int)$target.'#request-detail', 'Review Document Request');
        }
        if($type === 'ALUMNI_CONCERN_SUBMITTED'){
            return array('alumni-concerns.php', 'Review Alumni Concern');
        }
        if($type === 'ALUMNI_DONATION_RECEIVED'){
            return array('alumni-donation-report.php', 'View Donation Payment');
        }
        return null;
    }
}

/* ---------- KPI numbers ---------- */
$totalStudents = (int)exd_scalar($con, "SELECT COUNT(*) FROM tblsystemuser WHERE systemtype='Student' AND status='active'");
$teachingStaff = (int)exd_scalar($con, "SELECT COUNT(*) FROM tblsystemuser WHERE systemtype IN ('Teacher','Headmaster','AssistantHeadAcademic') AND status='active'");
$feesBilled    = exd_scalar($con, "SELECT COALESCE(SUM(cost),0) FROM tblbilling WHERE status='active'");
$feesCollected = exd_scalar($con, "SELECT COALESCE(SUM(payment),0) FROM tblpayment WHERE status='active'");

/* ---------- Attendance today ---------- */
$attPresent = 0; $attAbsent = 0; $attLate = 0; $attOther = 0; $attMarked = 0;
$attResult = @mysqli_query($con, "SELECT LOWER(TRIM(e.attendancestatus)) AS st, COUNT(*) AS c
    FROM tblstudentattendanceentry e
    INNER JOIN tblstudentattendancesession s ON s.sessionid = e.sessionid
    WHERE s.attendancedate = CURDATE() AND s.status='active'
    GROUP BY LOWER(TRIM(e.attendancestatus))");
if($attResult){
    while($row = mysqli_fetch_array($attResult, MYSQLI_ASSOC)){
        $c = (int)$row['c'];
        $attMarked += $c;
        $st = (string)$row['st'];
        if($st === 'present'){ $attPresent = $c; }
        elseif($st === 'absent'){ $attAbsent = $c; }
        elseif($st === 'late'){ $attLate = $c; }
        else{ $attOther += $c; }
    }
}
$attPercent = $attMarked > 0 ? round((($attPresent + $attLate) / $attMarked) * 100) : null;
$feesPercent = $feesBilled > 0 ? round(($feesCollected / $feesBilled) * 100) : null;

/* ---------- Enrolment trend: last 12 months ---------- */
$trendLabels = array(); $trendValues = array();
$monthCursor = strtotime(date('Y-m-01') . ' -11 months');
$months = array();
while(count($months) < 12){
    $months[date('Y-m', $monthCursor)] = 0;
    $monthCursor = strtotime('+1 month', $monthCursor);
}
$trendResult = @mysqli_query($con, "SELECT DATE_FORMAT(registereddatetime,'%Y-%m') AS ym, COUNT(*) AS c
    FROM tblsystemuser
    WHERE systemtype='Student' AND registereddatetime IS NOT NULL AND registereddatetime > '2000-01-01'
    GROUP BY ym");
if($trendResult){
    while($row = mysqli_fetch_array($trendResult, MYSQLI_ASSOC)){
        if(isset($months[$row['ym']])){ $months[$row['ym']] = (int)$row['c']; }
    }
}
foreach($months as $ym => $count){
    $trendLabels[] = date('M', strtotime($ym . '-01'));
    $trendValues[] = $count;
}

/* ---------- Student population: gender x residence (same logic as classic dashboard) ---------- */
$popLabels = array('Boys Day', 'Boys Boarding', 'Girls Day', 'Girls Boarding', 'Other / Unspecified');
$popValues = array(0, 0, 0, 0, 0);
$exdResidenceSql = "CASE
    WHEN UPPER(TRIM(COALESCE(su.residencetype, ''))) IN ('DAY','D') THEN 'Day'
    WHEN UPPER(TRIM(COALESCE(su.residencetype, ''))) IN ('BOARDING','BOARDER','B') THEN 'Boarding'
    ELSE '' END";
$popResult = @mysqli_query($con, "SELECT
    CASE
        WHEN UPPER(TRIM(su.gender)) IN ('M','MALE','BOY','BOYS','B') THEN 'Male'
        WHEN UPPER(TRIM(su.gender)) IN ('F','FEMALE','GIRL','GIRLS','G') THEN 'Female'
        ELSE 'Other' END AS gnorm,
    " . $exdResidenceSql . " AS residence_group,
    COUNT(DISTINCT su.userid) AS cnt
    FROM tblsystemuser su
    INNER JOIN tblclass cl ON cl.userid=su.userid
    WHERE su.systemtype='Student' AND su.status='active' AND cl.status='active'
    GROUP BY gnorm, residence_group");
if($popResult){
    while($row = mysqli_fetch_array($popResult, MYSQLI_ASSOC)){
        $g = (string)$row['gnorm']; $r = (string)$row['residence_group']; $c = (int)$row['cnt'];
        if($g === 'Male' && $r === 'Day'){ $popValues[0] = $c; }
        elseif($g === 'Male' && $r === 'Boarding'){ $popValues[1] = $c; }
        elseif($g === 'Female' && $r === 'Day'){ $popValues[2] = $c; }
        elseif($g === 'Female' && $r === 'Boarding'){ $popValues[3] = $c; }
    }
}

/* ---------- Batch breakdown per card (same helper as the classic dashboard) ---------- */
require_once __DIR__.'/dashboard-student-utils.php';
$_ExdBatchSummary = dashboard_student_population_summary($con, array('require_active_class' => true));
$popNoStatus = (int)exd_scalar($con, "SELECT COUNT(DISTINCT su.userid)
    FROM tblsystemuser su
    INNER JOIN tblclass cl ON cl.userid=su.userid
    WHERE su.systemtype='Student' AND su.status='active' AND cl.status='active'");
$popValues[4] = max(0, $popNoStatus - array_sum($popValues));

/* ---------- Subject performance: average vs pass rate (same engine + scope filters as classic dashboard) ---------- */
$_PerfClassFilter = isset($_GET['perf_class']) ? trim((string)$_GET['perf_class']) : '';
$_PerfBatchFilter = isset($_GET['perf_batch']) ? trim((string)$_GET['perf_batch']) : '';
$_PerfYearFilter  = isset($_GET['perf_year'])  ? trim((string)$_GET['perf_year'])  : '';
$_PerfTermFilter  = isset($_GET['perf_term'])  ? trim((string)$_GET['perf_term'])  : '';
$perfClassSafe = mysqli_real_escape_string($con, $_PerfClassFilter);
$perfBatchSafe = mysqli_real_escape_string($con, $_PerfBatchFilter);
$perfYearSafe  = mysqli_real_escape_string($con, $_PerfYearFilter);
$perfTermSafe  = mysqli_real_escape_string($con, $_PerfTermFilter);

$perfScopeParts = array();
if($perfClassSafe !== ''){
    $sc = @mysqli_query($con, "SELECT class_name FROM tblclassentry WHERE class_entryid='".$perfClassSafe."' LIMIT 1");
    if($sc && $r = mysqli_fetch_array($sc, MYSQLI_ASSOC)){ $perfScopeParts[] = (string)$r['class_name']; }
}
if($perfBatchSafe !== ''){
    $sb = @mysqli_query($con, "SELECT batch FROM tblbatch WHERE batchid='".$perfBatchSafe."' LIMIT 1");
    if($sb && $r = mysqli_fetch_array($sb, MYSQLI_ASSOC)){ $perfScopeParts[] = (string)$r['batch']; }
}
if($_PerfYearFilter !== ''){ $perfScopeParts[] = 'AY ' . $_PerfYearFilter; }
if($_PerfTermFilter !== ''){ $perfScopeParts[] = 'Semester ' . $_PerfTermFilter; }
$perfHasFilter = count($perfScopeParts) > 0;
$perfScopeLabel = $perfHasFilter ? implode(' | ', $perfScopeParts) : 'All classes, batches, academic years and semesters';

$perfYearSql = "COALESCE(NULLIF(TRIM(sa.academicyear),''), YEAR(sa.datetimeentry))";
$perfWhere = " WHERE mk.status='active' AND mk.totalmark > 0 AND mk.mark IS NOT NULL ";
if($perfClassSafe !== ''){ $perfWhere .= " AND sa.classid='".$perfClassSafe."' "; }
if($perfBatchSafe !== ''){ $perfWhere .= " AND sa.batchid='".$perfBatchSafe."' "; }
if($perfYearSafe  !== ''){ $perfWhere .= " AND ".$perfYearSql."='".$perfYearSafe."' "; }
if($perfTermSafe  !== ''){ $perfWhere .= " AND sa.termname='".$perfTermSafe."' "; }

/* Filter option lists (same sources as classic dashboard) */
$perfClassOptions = array();
$co = @mysqli_query($con, "SELECT class_entryid, class_name FROM tblclassentry ORDER BY class_name ASC");
if($co){ while($r = mysqli_fetch_array($co, MYSQLI_ASSOC)){ $perfClassOptions[] = $r; } }
$perfBatchOptions = array();
$bo = @mysqli_query($con, "SELECT batchid, batch FROM tblbatch ORDER BY datetimeentry DESC");
if($bo){ while($r = mysqli_fetch_array($bo, MYSQLI_ASSOC)){ $perfBatchOptions[] = $r; } }
$perfYearOptions = array();
$yo = @mysqli_query($con, "SELECT DISTINCT ".$perfYearSql." AS y FROM tblsubjectassignment sa WHERE ".$perfYearSql." IS NOT NULL ORDER BY y DESC");
if($yo){ while($r = mysqli_fetch_array($yo, MYSQLI_ASSOC)){ $perfYearOptions[] = (string)$r['y']; } }
$perfTermOptions = array();
$to = @mysqli_query($con, "SELECT DISTINCT termname FROM tblsubjectassignment ORDER BY termname ASC");
if($to){ while($r = mysqli_fetch_array($to, MYSQLI_ASSOC)){ $perfTermOptions[] = (string)$r['termname']; } }

$perfLabels = array(); $perfAvg = array(); $perfPass = array();
$perfRowsCount = 0; $overallAvg = 0; $overallPass = 0; $bestSubject = ''; $bestSubjectScore = 0;
$perfScoreCount = 0; $perfScoreSum = 0; $perfPassCount = 0;
$perfResult = @mysqli_query($con, "SELECT
    sub.subject,
    COUNT(*) AS score_count,
    SUM((mk.mark / mk.totalmark) * 100) AS score_sum,
    SUM(CASE WHEN (mk.mark / mk.totalmark) >= 0.5 THEN 1 ELSE 0 END) AS pass_count,
    ROUND(AVG(CASE WHEN mk.totalmark > 0 THEN (mk.mark / mk.totalmark) * 100 ELSE 0 END),2) AS avg_percent,
    ROUND(AVG(CASE WHEN mk.totalmark > 0 AND ((mk.mark / mk.totalmark) * 100) >= 50 THEN 100 ELSE 0 END),2) AS pass_rate
    FROM tblmark mk
    INNER JOIN tblsubjectassignment sa ON sa.assignmentid = mk.assignmentid
    INNER JOIN tblsubjectclassification sc ON sa.classificationid = sc.classificationid
    INNER JOIN tblsubject sub ON sc.subjectid = sub.subjectid
    " . $perfWhere . "
    GROUP BY sub.subjectid, sub.subject
    ORDER BY avg_percent DESC, sub.subject ASC");
if($perfResult){
    while($row = mysqli_fetch_array($perfResult, MYSQLI_ASSOC)){
        $perfScoreCount += (int)$row['score_count'];
        $perfScoreSum += (float)$row['score_sum'];
        $perfPassCount += (int)$row['pass_count'];
        if($perfRowsCount >= 10){ continue; }
        $perfLabels[] = (string)$row['subject'];
        $perfAvg[] = (float)$row['avg_percent'];
        $perfPass[] = (float)$row['pass_rate'];
        $perfRowsCount++;
    }
}
if($perfRowsCount > 0){
    $overallAvg = $perfScoreCount > 0 ? round($perfScoreSum / $perfScoreCount, 1) : 0;
    $overallPass = $perfScoreCount > 0 ? round(100 * $perfPassCount / $perfScoreCount, 1) : 0;
    $bestSubject = (string)$perfLabels[0];
    $bestSubjectScore = (float)$perfAvg[0];
}

/* ---------- Recent students ---------- */
$recentStudents = array();
$rs = @mysqli_query($con, "SELECT userid, firstname, othernames, surname, status, registereddatetime
    FROM tblsystemuser WHERE systemtype='Student'
    ORDER BY registereddatetime DESC LIMIT 6");
if($rs){
    while($row = mysqli_fetch_array($rs, MYSQLI_ASSOC)){ $recentStudents[] = $row; }
}

/* ---------- Activity feed (merged from real events) ---------- */
$feed = array();
$fp = mysqli_query($con, "SELECT p.payment, p.datetimepayment AS paymentdate, su.firstname, su.othernames, su.surname
    FROM tblpayment p LEFT JOIN tblbilling b ON b.billid = p.billid
    LEFT JOIN tblsystemuser su ON su.userid = b.userid
    WHERE p.status='active' ORDER BY p.datetimepayment DESC, p.paymentid DESC LIMIT 5");
if($fp){
    while($row = mysqli_fetch_array($fp, MYSQLI_ASSOC)){
        $who = trim($row['firstname'] . ' ' . $row['surname']);
        $feed[] = array(
            'icon' => 'fa-money',
            'tint' => 'gold',
            'title' => 'Fee payment received',
            'detail' => ($who !== '' ? $who : 'A student') . ' · ' . $_Currency . number_format((float)$row['payment'], 2),
            'when' => exd_when($row['paymentdate']),
            'timestamp' => strtotime((string)$row['paymentdate']) ?: 0,
        );
    }
}
$fs = @mysqli_query($con, "SELECT firstname, othernames, surname, registereddatetime
    FROM tblsystemuser WHERE systemtype='Student' ORDER BY registereddatetime DESC LIMIT 5");
if($fs){
    while($row = mysqli_fetch_array($fs, MYSQLI_ASSOC)){
        $who = trim($row['firstname'] . ' ' . $row['surname']);
        $feed[] = array(
            'icon' => 'fa-user-plus',
            'tint' => 'teal',
            'title' => 'New student admitted',
            'detail' => $who !== '' ? $who : 'Student record created',
            'when' => exd_when($row['registereddatetime']),
            'timestamp' => strtotime((string)$row['registereddatetime']) ?: 0,
        );
    }
}
$fa = @mysqli_query($con, "SELECT attendancedate, classid FROM tblstudentattendancesession
    WHERE status='active' ORDER BY attendancedate DESC, datetimeentry DESC LIMIT 5");
if($fa){
    while($row = mysqli_fetch_array($fa, MYSQLI_ASSOC)){
        $feed[] = array(
            'icon' => 'fa-check-square-o',
            'tint' => 'blue',
            'title' => 'Attendance marked',
            'detail' => 'Class ' . $row['classid'] . ' · ' . date('d M Y', strtotime($row['attendancedate'])),
            'when' => exd_when($row['attendancedate']),
            'timestamp' => strtotime((string)$row['attendancedate']) ?: 0,
        );
    }
}
usort($feed, function($a, $b){ return $b['timestamp'] <=> $a['timestamp']; });
$feed = array_slice($feed, 0, 5);
if(count($feed) === 0){
    $feed[] = array('icon' => 'fa-info-circle', 'tint' => 'blue', 'title' => 'Welcome', 'detail' => 'School activity will appear here as events are recorded.', 'when' => '');
}

$_School = trim((string)($_CompanyName ?? ''));
if($_School === ''){ $_School = 'LiveCampus School'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>LiveCampus | Executive Dashboard</title>
<link rel="icon" type="image/png" href="logo/logo-transparent.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="css/font-awesome-4.7.0/css/font-awesome.min.css">
<style>
/* ============================================================
   Aurora — executive dashboard design system (scoped)
   ============================================================ */
:root{
    --ex-navy:#0f2440;
    --ex-ink:#17293d;
    --ex-muted:#5b6b80;
    --ex-line:#e6ebf3;
    --ex-bg:#f3f6fb;
    --ex-card:#ffffff;
    --ex-teal:#14b8a6;
    --ex-blue:#3b82f6;
    --ex-gold:#f59e0b;
    --ex-red:#ef6a6a;
    --ex-violet:#8b5cf6;
    --ex-shadow:0 10px 30px rgba(15,36,64,.08);
    --ex-radius:18px;
    --ex-font:'Plus Jakarta Sans','Segoe UI',-apple-system,BlinkMacSystemFont,Roboto,Arial,sans-serif;
    --ex-grad-brand:linear-gradient(135deg,#3b82f6 0%,#8b5cf6 100%);
    --ex-grad-blue:linear-gradient(135deg,#60a5fa 0%,#3b82f6 55%,#2563eb 100%);
    --ex-grad-teal:linear-gradient(135deg,#2dd4bf 0%,#14b8a6 55%,#0d9488 100%);
    --ex-grad-gold:linear-gradient(135deg,#fbbf24 0%,#f59e0b 55%,#d97706 100%);
    --ex-grad-violet:linear-gradient(135deg,#a78bfa 0%,#8b5cf6 55%,#7c3aed 100%);
}
body.exdash-page{
    margin:0;background:var(--ex-bg);color:var(--ex-ink);
    font-family:var(--ex-font);font-size:15px;-webkit-font-smoothing:antialiased;
    background-image:
        radial-gradient(700px 320px at 6% -6%, rgba(59,130,246,.12), transparent 60%),
        radial-gradient(560px 280px at 96% -4%, rgba(139,92,246,.11), transparent 60%),
        radial-gradient(760px 360px at 50% 112%, rgba(20,184,166,.10), transparent 60%),
        linear-gradient(180deg,#fbfdff 0%,#f2f6fc 58%,#ecf2fa 100%);
    background-attachment:fixed;
}
.exdash-shell{display:flex;min-height:100vh}

/* ---------- Sidebar ---------- */
.exdash-sidebar{
    width:250px;flex:none;position:sticky;top:0;height:100vh;overflow-y:auto;
    display:flex;flex-direction:column;
    background:
        radial-gradient(420px 220px at 15% 0%, rgba(59,130,246,.28), transparent 65%),
        radial-gradient(380px 220px at 100% 30%, rgba(139,92,246,.20), transparent 60%),
        linear-gradient(180deg,#0e2038 0%,#122a4d 58%,#16345e 100%);
    color:#dbe7f6;scrollbar-width:thin;
}
.exdash-sidebar::-webkit-scrollbar{width:6px}
.exdash-sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,.16);border-radius:99px}
.exdash-brand{display:flex;align-items:center;gap:12px;padding:22px 20px 18px}
.exdash-brand-logo{
    width:44px;height:44px;border-radius:13px;background:rgba(255,255,255,.94);
    display:grid;place-items:center;flex:none;box-shadow:0 6px 16px rgba(2,10,25,.35);overflow:hidden;
}
.exdash-brand-logo img{width:36px;height:36px;object-fit:contain}
.exdash-brand-text strong{display:block;font-size:.92rem;color:#ffffff;letter-spacing:.01em}
.exdash-brand-text small{display:block;font-size:.62rem;text-transform:uppercase;letter-spacing:.14em;color:#8fb0d8;margin-top:3px}
.exdash-nav{display:flex;flex-direction:column;gap:4px;padding:6px 14px;flex:1}
.exdash-nav-label{font-size:.62rem;text-transform:uppercase;letter-spacing:.16em;color:#6f92bd;padding:16px 10px 7px}
.exdash-nav a{
    display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:11px;
    color:#b9cde6;text-decoration:none;font-size:.86rem;font-weight:600;transition:background .15s ease,color .15s ease;
}
.exdash-nav a i{width:18px;text-align:center;font-size:.92rem;color:#7fa3cf;transition:color .15s ease}
.exdash-nav a:hover{background:rgba(255,255,255,.07);color:#ffffff}
.exdash-nav a:hover i{color:#93c5fd}
.exdash-nav a.is-active{
    background:var(--ex-grad-brand);color:#ffffff;
    box-shadow:0 8px 18px rgba(76,96,246,.38);
}
.exdash-nav a.is-active i{color:#ffffff}
.exdash-nav .exdash-nav-btn{
    display:flex;align-items:center;gap:11px;margin-top:10px;padding:11px 13px;border:0;cursor:pointer;
    border-radius:12px;background:var(--nav-btn-grad,var(--ex-grad-brand));color:#ffffff;text-decoration:none;
    font-family:inherit;font-size:.86rem;font-weight:700;text-align:left;width:100%;
    box-shadow:0 8px 18px var(--nav-btn-glow,rgba(76,96,246,.35));transition:transform .15s ease,box-shadow .15s ease,filter .15s ease;
}
.exdash-nav .exdash-nav-btn--violet{--nav-btn-grad:var(--ex-grad-violet);--nav-btn-glow:rgba(124,58,237,.4)}
.exdash-nav-btn i{width:18px;text-align:center;font-size:.92rem}
.exdash-nav-btn .exdash-nav-btn-arrow{margin-left:auto;font-size:.72rem;opacity:.85;transition:transform .2s ease}
.exdash-nav-btn:hover{transform:translateY(-1px);box-shadow:0 12px 24px var(--nav-btn-glow,rgba(76,96,246,.45));filter:brightness(1.06)}
.exdash-nav-btn:active{transform:translateY(0)}
.exdash-nav-hint{margin:8px 2px 0;font-size:.68rem;color:#7fa3cf;padding:0 4px}

/* ---------- Sidebar collapsible tool group (classic dropdown, as sidebar buttons) ---------- */
.exdash-nav-details{border-radius:11px;overflow:hidden}
.exdash-nav-details>summary{
    list-style:none;cursor:pointer;user-select:none;display:flex;align-items:center;gap:11px;
    padding:10px 12px;border-radius:11px;color:#b9cde6;font-size:.86rem;font-weight:600;
    transition:background .15s ease,color .15s ease;
}
.exdash-nav-details>summary::-webkit-details-marker{display:none}
.exdash-nav-details>summary i{width:18px;text-align:center;font-size:.92rem;color:#7fa3cf;transition:color .15s ease}
.exdash-nav-details>summary .exdash-nav-details-arrow{margin-left:5px;font-size:.7rem;opacity:.85;transition:transform .2s ease}
.exdash-nav-count{
    margin-left:auto;font-size:.58rem;font-weight:800;background:rgba(255,255,255,.12);
    color:#c6d8ec;padding:1px 8px;border-radius:999px;letter-spacing:.04em;
}
.exdash-nav-details>summary:hover{background:rgba(255,255,255,.07);color:#ffffff}
.exdash-nav-details>summary:hover i{color:#93c5fd}
.exdash-nav-details[open]>summary{color:#ffffff;background:rgba(255,255,255,.06)}
.exdash-nav-details[open]>summary .exdash-nav-details-arrow{transform:rotate(180deg)}
.exdash-nav-sub{padding:2px 4px 8px}
.exdash-nav-sub-label{
    display:block;font-size:.58rem;text-transform:uppercase;letter-spacing:.14em;color:#5f7ea8;
    padding:9px 10px 4px;font-weight:800;
}
.exdash-nav-sub a{
    display:flex;align-items:center;gap:9px;padding:7px 10px 7px 20px;border-radius:9px;
    color:#a9c0dd;text-decoration:none;font-size:.78rem;font-weight:600;
    transition:background .13s ease,color .13s ease;
}
.exdash-nav-sub a i{width:15px;text-align:center;font-size:.72rem;color:#6f92bd;transition:color .13s ease}
.exdash-nav-sub a:hover{background:rgba(255,255,255,.08);color:#ffffff}
.exdash-nav-sub a:hover i{color:#93c5fd}

/* ---------- Module Deck: the classic dropdown menu, reborn ---------- */
.exdeck-scrim{
    position:fixed;inset:0;background:rgba(6,14,30,.5);backdrop-filter:blur(2px);
    opacity:0;pointer-events:none;transition:opacity .25s ease;z-index:70;
}
.exdeck-scrim.is-open{opacity:1;pointer-events:auto}
.exdeck{
    position:fixed;top:0;left:0;bottom:0;width:min(370px,94vw);z-index:71;
    background:linear-gradient(180deg,#0c1a2e 0%,#0e2138 100%);
    display:flex;flex-direction:column;
    transform:translateX(-106%);transition:transform .32s cubic-bezier(.22,.9,.3,1);
    box-shadow:26px 0 70px rgba(2,10,25,.5);
}
.exdeck.is-open{transform:none}
.exdeck-head{display:flex;align-items:center;gap:12px;padding:17px 18px 14px;border-bottom:1px solid rgba(255,255,255,.09)}
.exdeck-head-logo{width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.95);display:grid;place-items:center;flex:none;box-shadow:0 6px 16px rgba(2,10,25,.35)}
.exdeck-head-logo img{width:32px;height:32px;object-fit:contain}
.exdeck-head strong{display:block;color:#fff;font-size:.9rem;letter-spacing:.01em}
.exdeck-head small{display:block;color:#8fb0d8;font-size:.63rem;text-transform:uppercase;letter-spacing:.13em;margin-top:2px}
.exdeck-close{
    margin-left:auto;width:32px;height:32px;border-radius:9px;border:0;cursor:pointer;
    background:rgba(255,255,255,.08);color:#c6d8ec;font-size:.85rem;transition:background .15s ease,color .15s ease;
}
.exdeck-close:hover{background:rgba(255,255,255,.17);color:#fff}
.exdeck-search{
    display:flex;align-items:center;gap:9px;margin:12px 14px 2px;padding:9px 12px;
    border-radius:11px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);
    transition:border-color .15s ease,background .15s ease;
}
.exdeck-search:focus-within{border-color:rgba(147,197,253,.55);background:rgba(255,255,255,.1)}
.exdeck-search>i{color:#8fb0d8;font-size:.8rem;flex:none}
.exdeck-search input{
    flex:1;min-width:0;background:transparent;border:0;outline:none;
    color:#fff;font-family:inherit;font-size:.83rem;font-weight:600;
}
.exdeck-search input::placeholder{color:#6f92bd}
.exdeck-search input::-webkit-search-cancel-button{display:none}
.exdeck-search-clear{
    flex:none;width:22px;height:22px;border:0;border-radius:7px;cursor:pointer;
    background:rgba(255,255,255,.1);color:#c6d8ec;font-size:.66rem;transition:background .15s ease,color .15s ease;
}
.exdeck-search-clear:hover{background:rgba(255,255,255,.2);color:#fff}
.exdeck-empty{padding:26px 14px;text-align:center;color:#8fb0d8;font-size:.8rem;display:none}
.exdeck-empty.is-visible{display:block}
.exdeck-scroll{flex:1;overflow-y:auto;padding:12px 14px 20px}
.exdeck-scroll::-webkit-scrollbar{width:6px}
.exdeck-scroll::-webkit-scrollbar-thumb{background:rgba(255,255,255,.16);border-radius:99px}
.exdeck-section{margin:8px 0;border:1px solid rgba(255,255,255,.1);border-radius:13px;background:rgba(255,255,255,.04);overflow:hidden;transition:border-color .2s ease,background .2s ease}
.exdeck-section summary{
    list-style:none;cursor:pointer;user-select:none;padding:12px 14px;
    display:flex;align-items:center;gap:11px;color:#e6eef8;font-weight:700;font-size:.85rem;
}
.exdeck-section summary::-webkit-details-marker{display:none}
.exdeck-section summary::after{content:"\f107";font-family:FontAwesome;color:#8fb0d8;font-size:.8rem;margin-left:6px;transition:transform .18s ease}
.exdeck-section[open] summary::after{transform:rotate(180deg)}
.exdeck-section[open]{border-color:rgba(147,197,253,.38);background:rgba(255,255,255,.06)}
.exdeck-chip{width:27px;height:27px;border-radius:8px;display:grid;place-items:center;color:#fff;font-size:.72rem;flex:none}
.exdeck-chip--setup{background:var(--ex-grad-blue)}
.exdeck-chip--records{background:var(--ex-grad-teal)}
.exdeck-chip--academics{background:var(--ex-grad-violet)}
.exdeck-chip--results{background:linear-gradient(135deg,#f87171,#ef4444)}
.exdeck-chip--finance{background:var(--ex-grad-gold)}
.exdeck-chip--campus{background:linear-gradient(135deg,#34d399,#059669)}
.exdeck-chip--admissions{background:linear-gradient(135deg,#f472b6,#db2777)}
.exdeck-count{margin-left:auto;font-size:.62rem;font-weight:800;background:rgba(255,255,255,.1);color:#c6d8ec;padding:2px 8px;border-radius:99px}
.exdeck-body{padding:4px 10px 12px;border-top:1px solid rgba(255,255,255,.08)}
.exdeck-body a{
    display:flex;align-items:center;gap:9px;margin:1px 0;padding:8px 8px;border-radius:8px;
    color:#b9cde6;text-decoration:none;font-size:.81rem;font-weight:600;transition:background .13s ease,color .13s ease;
}
.exdeck-body a i{width:16px;text-align:center;font-size:.74rem;color:#7fa3cf;transition:color .13s ease}
.exdeck-body a:hover{background:rgba(255,255,255,.08);color:#fff}
.exdeck-body a:hover i{color:#93c5fd}
.exdeck-foot{padding:12px 18px;border-top:1px solid rgba(255,255,255,.09);font-size:.66rem;color:#7fa3cf;text-align:center}
.exdash-sidebar-foot{padding:14px;border-top:1px solid rgba(255,255,255,.09);display:flex;flex-direction:column;gap:4px}
.exdash-sidebar-foot a{
    display:flex;align-items:center;gap:11px;padding:9px 12px;border-radius:11px;
    color:#9db6d6;text-decoration:none;font-size:.83rem;font-weight:600;
}
.exdash-sidebar-foot a i{width:18px;text-align:center}
.exdash-sidebar-foot a:hover{background:rgba(255,255,255,.07);color:#fff}

/* ---------- Main + topbar ---------- */
.exdash-main{flex:1;min-width:0;display:flex;flex-direction:column}
.exdash-topbar{
    position:sticky;top:0;z-index:50;display:flex;align-items:center;gap:14px;
    padding:13px 26px;border-bottom:1px solid var(--ex-line);
    background:rgba(255,255,255,.82);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
}
.exdash-burger{
    display:none;width:42px;height:42px;border-radius:12px;border:1px solid var(--ex-line);
    background:var(--ex-card);color:var(--ex-ink);font-size:1.02rem;cursor:pointer;place-items:center;flex:none;
}
.exdash-search{flex:1 1 320px;max-width:520px;position:relative}
.exdash-search i{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#93a5bd;font-size:.9rem}
.exdash-search input{
    width:100%;box-sizing:border-box;padding:11px 16px 11px 40px;font-family:inherit;
    border:1px solid var(--ex-line);border-radius:999px;background:#f6f9fd;
    font-size:.87rem;color:var(--ex-ink);outline:none;transition:border-color .15s ease,box-shadow .15s ease,background .15s ease;
}
.exdash-search input:focus{border-color:var(--ex-blue);box-shadow:0 0 0 4px rgba(59,130,246,.13);background:#fff}
.exdash-top-actions{margin-left:auto;display:flex;align-items:center;gap:13px;flex:none}
.exdash-bell{
    position:relative;width:42px;height:42px;border-radius:50%;cursor:pointer;
    border:1px solid var(--ex-line);background:var(--ex-card);color:var(--ex-ink);font-size:1rem;
    display:grid;place-items:center;transition:border-color .15s ease,box-shadow .15s ease;
}
.exdash-bell:hover{border-color:var(--ex-blue);box-shadow:0 4px 14px rgba(59,130,246,.15)}
.exdash-bell-badge{
    position:absolute;top:-4px;right:-4px;background:var(--ex-grad-gold);color:#fff;
    font-size:.6rem;font-weight:800;min-width:18px;height:18px;border-radius:999px;display:grid;place-items:center;padding:0 4px;
    box-shadow:0 3px 8px rgba(217,119,6,.4);
}
/* Live-users pill + document request link (classic admin topbar indicators) */
.exdash-live{
    display:inline-flex;align-items:center;gap:8px;height:42px;padding:0 15px;border-radius:999px;flex:none;
    border:1px solid var(--ex-line);background:var(--ex-card);color:var(--ex-ink);
    font-size:.78rem;font-weight:700;text-decoration:none;cursor:default;
    transition:border-color .15s ease,box-shadow .15s ease;
}
.exdash-live:hover{border-color:var(--ex-teal);box-shadow:0 4px 14px rgba(20,184,166,.15)}
.exdash-live i{color:var(--ex-teal);font-size:.85rem}
.exdash-live strong{font-size:.86rem}
.exdash-live-dot{
    width:8px;height:8px;border-radius:50%;background:#22c55e;flex:none;
    box-shadow:0 0 0 0 rgba(34,197,94,.5);animation:exdashPulse 2s infinite;
}
@keyframes exdashPulse{
    0%{box-shadow:0 0 0 0 rgba(34,197,94,.45)}
    70%{box-shadow:0 0 0 7px rgba(34,197,94,0)}
    100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}
}
.exdash-docs{
    position:relative;width:42px;height:42px;border-radius:50%;flex:none;
    border:1px solid var(--ex-line);background:var(--ex-card);color:var(--ex-ink);font-size:1rem;
    display:grid;place-items:center;text-decoration:none;transition:border-color .15s ease,box-shadow .15s ease;
}
.exdash-docs:hover{border-color:var(--ex-blue);box-shadow:0 4px 14px rgba(59,130,246,.15)}
.exdash-docs-badge{
    position:absolute;top:-4px;right:-4px;background:linear-gradient(135deg,#f87171,#ef4444);color:#fff;
    font-size:.6rem;font-weight:800;min-width:18px;height:18px;border-radius:999px;display:grid;place-items:center;padding:0 4px;
    box-shadow:0 3px 8px rgba(239,68,68,.4);
}
.exdash-bell-menu{
    position:absolute;right:0;top:calc(100% + 10px);width:330px;max-width:84vw;
    background:var(--ex-card);border:1px solid var(--ex-line);border-radius:16px;
    box-shadow:0 24px 50px rgba(15,36,64,.18);display:none;overflow:hidden;
}
.exdash-bell-menu.is-open{display:block}
.exdash-bell-menu header{
    padding:13px 18px;font-weight:800;font-size:.84rem;border-bottom:1px solid var(--ex-line);
    display:flex;align-items:center;justify-content:space-between;gap:10px;
}
.exdash-note-count{
    font-size:.62rem;font-weight:800;background:linear-gradient(135deg,#6366f1,#8b5cf6);
    color:#fff;padding:3px 9px;border-radius:999px;white-space:nowrap;
}
.exdash-note-list{max-height:380px;overflow-y:auto}
.exdash-note-list::-webkit-scrollbar{width:5px}
.exdash-note-list::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:99px}
.exdash-note-item{
    display:flex;gap:11px;padding:12px 18px;border-bottom:1px solid #f1f5f9;font-size:.82rem;
    position:relative;transition:background .13s ease;
}
.exdash-note-item:hover{background:rgba(99,102,241,.05)}
.exdash-note-item.is-unread{background:linear-gradient(90deg,rgba(99,102,241,.06),transparent 70%)}
.exdash-note-item.is-unread:hover{background:linear-gradient(90deg,rgba(99,102,241,.10),transparent 70%)}
.exdash-note-icon{
    width:34px;height:34px;border-radius:11px;flex:none;display:grid;place-items:center;
    background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;font-size:.8rem;
    box-shadow:0 6px 14px rgba(99,102,241,.3);
}
.exdash-note-icon.is-read{background:#eef2f7;color:#94a3b8;box-shadow:none}
.exdash-note-body{min-width:0;flex:1}
.exdash-note-top{display:flex;align-items:baseline;justify-content:space-between;gap:8px}
.exdash-note-top strong{font-size:.82rem;color:#0f172a}
.exdash-note-top time{color:var(--ex-muted);font-size:.66rem;white-space:nowrap;flex:none}
.exdash-note-action{
    display:inline-block;margin-top:3px;font-size:.66rem;font-weight:800;color:#6366f1;
    text-transform:uppercase;letter-spacing:.05em;
}
.exdash-note-body p{margin:4px 0 0;color:var(--ex-muted);font-size:.75rem;line-height:1.45;word-wrap:break-word}
.exdash-note-link{
    display:inline-flex;align-items:center;gap:5px;margin-top:7px;font-size:.72rem;font-weight:800;
    color:#4f46e5;text-decoration:none;border:1px solid rgba(99,102,241,.28);
    background:rgba(99,102,241,.07);padding:3px 11px;border-radius:999px;
    transition:background .12s ease,color .12s ease;
}
.exdash-note-link:hover{background:#6366f1;color:#fff;text-decoration:none}
.exdash-note-dot{
    position:absolute;top:14px;right:12px;width:8px;height:8px;border-radius:50%;flex:none;
    background:linear-gradient(135deg,#6366f1,#8b5cf6);
    box-shadow:0 0 0 3px rgba(99,102,241,.18);
}
.exdash-note-empty{padding:26px 18px;text-align:center;color:var(--ex-muted);font-size:.78rem;line-height:1.6}
.exdash-note-empty i{display:block;font-size:1.3rem;margin-bottom:8px;color:#cbd5e1}
.exdash-note-foot{
    display:flex;align-items:center;justify-content:space-between;gap:10px;
    padding:10px 14px;border-top:1px solid var(--ex-line);
}
.exdash-note-mark button{
    display:inline-flex;align-items:center;gap:6px;border:0;cursor:pointer;
    background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;
    font-family:inherit;font-size:.72rem;font-weight:800;padding:7px 13px;border-radius:9px;
    box-shadow:0 6px 14px rgba(99,102,241,.3);transition:transform .13s ease,filter .13s ease;
}
.exdash-note-mark button:hover{transform:translateY(-1px);filter:brightness(1.06)}

/* ---------- Notifications timeline panel ---------- */
.exnote-tools{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.exnote-stat{
    display:inline-flex;align-items:center;gap:7px;font-size:.72rem;font-weight:800;color:#4f46e5;
    background:linear-gradient(135deg,#eef2ff,#f5f3ff);border:1px solid #e0e7ff;
    padding:7px 13px;border-radius:999px;
}
.exnote-stat-dot{width:8px;height:8px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 0 0 3px rgba(99,102,241,.18)}
.exnote-list{display:flex;flex-direction:column;gap:8px;padding-top:4px}
.exnote-day{
    display:flex;align-items:center;gap:12px;margin:14px 2px 4px;
    font-size:.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#94a3b8;
}
.exnote-day::after{content:"";flex:1;height:1px;background:linear-gradient(90deg,#e8edf5,transparent)}
.exnote-item{
    position:relative;display:flex;gap:14px;padding:15px 17px;border-radius:15px;
    border:1px solid #eef2f7;background:#ffffff;
    transition:border-color .15s ease,box-shadow .15s ease,transform .15s ease;
}
.exnote-item:hover{border-color:#c7d2fe;transform:translateY(-1px);box-shadow:0 10px 24px rgba(99,102,241,.10)}
.exnote-item.is-unread{
    border-color:rgba(99,102,241,.35);
    background:linear-gradient(90deg,rgba(99,102,241,.05),#ffffff 45%);
    box-shadow:0 8px 20px rgba(99,102,241,.10);
}
.exnote-icon{
    width:42px;height:42px;border-radius:13px;flex:none;display:grid;place-items:center;
    color:#fff;font-size:.92rem;box-shadow:0 8px 16px rgba(15,23,42,.18);
}
.exnote-body{min-width:0;flex:1}
.exnote-top{display:flex;align-items:baseline;gap:9px;flex-wrap:wrap}
.exnote-top strong{font-size:.88rem;color:#0f172a;font-weight:800}
.exnote-role{
    font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;
    color:#6366f1;background:#eef2ff;padding:2px 8px;border-radius:999px;
}
.exnote-top time{margin-left:auto;color:var(--ex-muted);font-size:.68rem;white-space:nowrap}
.exnote-action{
    display:inline-block;margin-top:5px;font-size:.68rem;font-weight:800;color:#4f46e5;
    text-transform:uppercase;letter-spacing:.05em;
}
.exnote-body p{margin:5px 0 0;color:var(--ex-muted);font-size:.79rem;line-height:1.5;word-wrap:break-word}
.exnote-link{
    display:inline-flex;align-items:center;gap:6px;margin-top:8px;font-size:.74rem;font-weight:800;
    color:#4f46e5;text-decoration:none;background:rgba(99,102,241,.07);
    border:1px solid rgba(99,102,241,.28);padding:4px 12px;border-radius:999px;
    transition:background .15s ease,color .15s ease,border-color .15s ease;
}
.exnote-link:hover{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;text-decoration:none}
.exnote-empty{padding:44px 18px;text-align:center;color:var(--ex-muted)}
.exnote-empty i{display:block;font-size:2rem;margin-bottom:12px;color:#cbd5e1}
.exnote-empty h3{margin:0 0 6px;font-size:.95rem;color:#334155}
.exnote-empty p{margin:0;font-size:.78rem;line-height:1.6}
.exdash-bell-item{display:flex;gap:11px;padding:11px 18px;font-size:.82rem;border-bottom:1px solid #f1f5f9}
.exdash-bell-item:last-of-type{border-bottom:0}
.exdash-bell-item i{margin-top:2px}
.exdash-bell-all{
    display:flex;align-items:center;justify-content:center;gap:7px;padding:11px;
    font-size:.78rem;font-weight:700;color:var(--ex-blue);text-decoration:none;border-top:1px solid var(--ex-line);
}
.exdash-bell-all:hover{background:rgba(59,130,246,.06)}
.exdash-user{position:relative;display:flex;align-items:center;gap:11px}
.exdash-user-btn{
    display:flex;align-items:center;gap:11px;border:0;cursor:pointer;background:transparent;
    padding:6px 8px;border-radius:12px;font-family:inherit;transition:background .15s ease;
}
.exdash-user-btn:hover{background:rgba(100,116,139,.09)}
.exdash-user-caret{font-size:.68rem;color:var(--ex-muted);transition:transform .18s ease}
.exdash-user.is-open .exdash-user-caret{transform:rotate(180deg)}
.exdash-user-menu{
    position:absolute;top:calc(100% + 10px);right:0;width:264px;z-index:50;
    background:var(--ex-card,#fff);border:1px solid var(--ex-line,rgba(15,36,64,.09));
    border-radius:14px;box-shadow:0 18px 44px rgba(15,36,64,.16);
    opacity:0;transform:translateY(-6px);pointer-events:none;transition:opacity .18s ease,transform .18s ease;
    overflow:hidden;
}
.exdash-user.is-open .exdash-user-menu{opacity:1;transform:none;pointer-events:auto}
.exdash-user-menu-head{
    display:flex;align-items:center;gap:12px;padding:14px 16px;
    background:var(--ex-grad-brand);color:#fff;
}
.exdash-user-menu-head strong{display:block;font-size:.87rem}
.exdash-user-menu-head small{display:block;font-size:.7rem;opacity:.85;margin-top:2px}
.exdash-avatar--lg{width:40px;height:40px;font-size:.8rem;background:rgba(255,255,255,.22);box-shadow:none;flex:none}
.exdash-user-menu a{
    display:flex;align-items:center;gap:11px;padding:10px 16px;
    color:var(--ex-ink,#16233b);text-decoration:none;font-size:.83rem;font-weight:600;
    transition:background .13s ease,color .13s ease;
}
.exdash-user-menu a i{width:17px;text-align:center;font-size:.82rem;color:var(--ex-muted)}
.exdash-user-menu a:hover{background:rgba(100,116,139,.09);color:var(--ex-blue,#2563eb)}
.exdash-user-menu a.exdash-user-logout{color:#dc2626;border-top:1px solid var(--ex-line,rgba(15,36,64,.09));margin-top:4px}
.exdash-user-menu a.exdash-user-logout i{color:#dc2626}
.exdash-user-menu a.exdash-user-logout:hover{background:rgba(220,38,38,.08)}
.exdash-theme{padding:6px 8px 8px;border-bottom:1px solid var(--ex-line,rgba(15,36,64,.09))}
.exdash-theme-label{display:block;padding:2px 10px 7px;font-size:.6rem;text-transform:uppercase;letter-spacing:.14em;color:var(--ex-muted);font-weight:800}
.exdash-theme-row{
    display:flex;align-items:center;gap:11px;width:100%;margin:1px 0;padding:7px 10px;border:0;border-radius:9px;cursor:pointer;
    background:transparent;font-family:inherit;font-size:.82rem;font-weight:600;color:var(--ex-ink,#16233b);text-align:left;
    transition:background .13s ease,color .13s ease;
}
.exdash-theme-row .admin-theme-dot{width:17px;height:17px;flex:none;margin:0}
.exdash-theme-row:hover{background:rgba(100,116,139,.09)}
.exdash-theme-row.is-active{background:rgba(99,102,241,.13);color:var(--ex-blue,#2563eb);font-weight:800}
.exdash-theme-row.is-active::after{content:"\f00c";font-family:FontAwesome;margin-left:auto;font-size:.72rem}
.exdash-avatar{
    width:42px;height:42px;border-radius:50%;color:#fff;display:grid;place-items:center;
    font-weight:800;font-size:.85rem;background:var(--ex-grad-brand);box-shadow:0 6px 14px rgba(99,102,241,.35);
}
.exdash-user-meta{line-height:1.25;text-align:left}
.exdash-user-meta strong{display:block;font-size:.86rem}
.exdash-user-meta small{color:var(--ex-muted);font-size:.72rem}

/* ---------- Content ---------- */
.exdash-content{padding:26px 28px 70px;display:flex;flex-direction:column;gap:22px;max-width:1460px;width:100%;margin:0 auto;box-sizing:border-box}
.exdash-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
.exdash-heading h1{margin:0;font-size:clamp(1.4rem,2.4vw,1.8rem);letter-spacing:-.02em;font-weight:800}
.exdash-heading p{margin:5px 0 0;color:var(--ex-muted);font-size:.87rem;display:flex;align-items:center;gap:7px}
.exdash-heading p i{font-size:.8rem}
.exdash-heading > div:first-child{min-width:0}

/* Quick actions */
.exdash-quick{display:flex;flex-wrap:wrap;gap:8px}
.exdash-quick-btn{
    display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:999px;
    font-size:.78rem;font-weight:700;text-decoration:none;font-family:inherit;
    background:var(--ex-card);border:1px solid var(--ex-line);color:var(--ex-ink);
    transition:border-color .15s ease,box-shadow .15s ease;
}
.exdash-quick-btn i{color:var(--ex-blue)}
.exdash-quick-btn:hover{border-color:var(--ex-blue);box-shadow:0 4px 14px rgba(59,130,246,.14)}

/* Section tabs */
.exdash-tabs{
    display:flex;gap:5px;width:fit-content;max-width:100%;padding:5px;border-radius:14px;
    background:var(--ex-card);border:1px solid var(--ex-line);box-shadow:var(--ex-shadow);
}
.exdash-tab{
    display:inline-flex;align-items:center;gap:8px;border:0;background:transparent;cursor:pointer;
    padding:9px 20px;border-radius:10px;font-family:inherit;font-size:.85rem;font-weight:700;
    color:var(--ex-muted);white-space:nowrap;transition:background .18s ease,color .18s ease,box-shadow .18s ease;
}
.exdash-tab i{font-size:.82rem}
.exdash-tab:hover{color:var(--ex-ink)}
.exdash-tab.active{background:var(--ex-grad-brand);color:#fff;box-shadow:0 6px 16px rgba(99,102,241,.35)}
.exdash-tab-badge{
    display:inline-grid;place-items:center;min-width:18px;height:18px;padding:0 5px;margin-left:2px;
    border-radius:999px;background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#fff;
    font-size:.6rem;font-weight:800;box-shadow:0 3px 8px rgba(217,119,6,.4);
}
.exdash-tab.active .exdash-tab-badge{background:rgba(255,255,255,.25);box-shadow:none}
.exdash-pane[hidden]{display:none}

/* KPI cards */
.exdash-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}
.exdash-kpi{
    position:relative;overflow:hidden;border:0;color:#fff;border-radius:var(--ex-radius);
    padding:20px 22px;display:flex;align-items:center;gap:16px;min-width:0;
    box-shadow:0 14px 28px rgba(15,36,64,.16);transition:transform .16s ease,box-shadow .16s ease;
}
.exdash-kpi:hover{transform:translateY(-3px);box-shadow:0 20px 36px rgba(15,36,64,.22)}
.exdash-kpi::after{
    content:"";position:absolute;right:-38px;top:-38px;width:130px;height:130px;border-radius:50%;
    background:rgba(255,255,255,.14);
}
.exdash-kpi::before{
    content:"";position:absolute;right:14px;bottom:-52px;width:110px;height:110px;border-radius:50%;
    background:rgba(255,255,255,.08);
}
.exdash-kpi--blue{background:var(--ex-grad-blue)}
.exdash-kpi--teal{background:var(--ex-grad-teal)}
.exdash-kpi--gold{background:var(--ex-grad-gold)}
.exdash-kpi--violet{background:var(--ex-grad-violet)}
.exdash-kpi-icon{
    width:50px;height:50px;border-radius:14px;display:grid;place-items:center;font-size:1.15rem;flex:none;
    background:rgba(255,255,255,.22);border:1px solid rgba(255,255,255,.35);backdrop-filter:blur(4px);
}
.exdash-kpi-body{min-width:0;position:relative;z-index:1}
.exdash-kpi-body > span{font-size:.7rem;text-transform:uppercase;letter-spacing:.09em;font-weight:700;opacity:.92}
.exdash-kpi-body strong{display:block;font-size:1.7rem;font-weight:800;letter-spacing:-.01em;line-height:1.15;margin:2px 0 6px}
.exdash-kpi-trend{
    display:inline-flex;align-items:center;gap:5px;font-style:normal;font-size:.68rem;font-weight:700;
    background:rgba(255,255,255,.22);border:1px solid rgba(255,255,255,.28);
    color:#fff;padding:4px 10px;border-radius:999px;white-space:nowrap;
}
.exdash-kpi-trend--muted{opacity:.85}

/* Panels */
.exdash-panel{
    background:var(--ex-card);border:1px solid var(--ex-line);border-radius:var(--ex-radius);
    box-shadow:var(--ex-shadow);padding:20px 22px;min-width:0;
}
.exdash-panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}
.exdash-panel-head h2{margin:0;font-size:.98rem;font-weight:800;display:flex;align-items:center;gap:9px;letter-spacing:-.01em}
.exdash-panel-head h2 .exdash-head-icon{
    width:30px;height:30px;border-radius:9px;display:grid;place-items:center;font-size:.8rem;color:#fff;flex:none;
}
.exdash-head-icon--blue{background:var(--ex-grad-blue)}
.exdash-head-icon--teal{background:var(--ex-grad-teal)}
.exdash-head-icon--gold{background:var(--ex-grad-gold)}
.exdash-head-icon--violet{background:var(--ex-grad-violet)}
.exdash-head-icon--red{background:linear-gradient(135deg,#f87171,#ef4444)}
.exdash-panel-head small{display:block;color:var(--ex-muted);font-size:.76rem;margin-top:3px;font-weight:500}
.exdash-chip{
    align-self:flex-start;background:rgba(59,130,246,.1);color:var(--ex-blue);
    font-size:.64rem;font-weight:800;padding:5px 11px;border-radius:999px;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap;
}
.exdash-chart-box{position:relative;height:265px}
.exdash-empty-note{
    text-align:center;color:var(--ex-muted);font-size:.84rem;padding:34px 16px;
    background:rgba(59,130,246,.045);border:1px dashed var(--ex-line);border-radius:14px;
}
.exdash-donut-legend{display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:12px;font-size:.78rem;color:var(--ex-muted)}
.exdash-donut-legend b{color:var(--ex-ink);margin-left:3px}
.exdash-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:6px}

/* Scope picker */
.exdash-scope{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:14px}
.exdash-scope select{
    flex:1 1 118px;min-width:104px;padding:8px 11px;font-size:.78rem;font-family:inherit;
    border:1px solid var(--ex-line);border-radius:10px;background:var(--ex-card);color:var(--ex-ink);
    outline:none;cursor:pointer;transition:border-color .15s ease;
}
.exdash-scope select:focus{border-color:var(--ex-blue)}
.exdash-scope-btn{
    border:0;cursor:pointer;font-family:inherit;background:var(--ex-grad-brand);color:#fff;
    font-size:.78rem;font-weight:700;padding:9px 16px;border-radius:10px;white-space:nowrap;
    box-shadow:0 5px 14px rgba(99,102,241,.3);transition:filter .15s ease;
}
.exdash-scope-btn:hover{filter:brightness(1.08)}
.exdash-scope-reset{font-size:.76rem;color:var(--ex-muted);text-decoration:none;white-space:nowrap}
.exdash-scope-reset:hover{color:var(--ex-red)}
.exdash-perf-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:14px}
.exdash-perf-stats > div{background:rgba(20,184,166,.07);border:1px solid var(--ex-line);border-radius:12px;padding:10px 13px;min-width:0}
.exdash-perf-stats span{display:block;font-size:.62rem;text-transform:uppercase;letter-spacing:.06em;color:var(--ex-muted);margin-bottom:4px;font-weight:700}
.exdash-perf-stats strong{display:block;font-size:.94rem;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* Student breakdown cards (classic dashboard numbers) */
.exdash-breakdown{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}
.exdash-breakdown-card{
    position:relative;overflow:hidden;border-radius:14px;padding:15px 16px;color:#fff;
    background:var(--bd-grad,var(--ex-grad-blue));
    box-shadow:0 8px 20px var(--bd-glow,rgba(37,99,235,.28));
}
.exdash-breakdown-card::after{
    content:"";position:absolute;right:-22px;top:-22px;width:84px;height:84px;border-radius:50%;
    background:rgba(255,255,255,.14);
}
.exdash-breakdown-card h4{
    margin:0;font-size:.66rem;text-transform:uppercase;letter-spacing:.08em;font-weight:800;
    display:flex;align-items:center;gap:7px;opacity:.95;
}
.exdash-breakdown-card p{margin:7px 0 0;font-size:1.55rem;font-weight:800;line-height:1;letter-spacing:-.01em}
.exdash-breakdown-card--blue{--bd-grad:linear-gradient(135deg,#60a5fa 0%,#2563eb 100%);--bd-glow:rgba(37,99,235,.3)}
.exdash-breakdown-card--sky{--bd-grad:linear-gradient(135deg,#38bdf8 0%,#0284c7 100%);--bd-glow:rgba(2,132,199,.3)}
.exdash-breakdown-card--pink{--bd-grad:linear-gradient(135deg,#f472b6 0%,#db2777 100%);--bd-glow:rgba(219,39,119,.3)}
.exdash-breakdown-card--rose{--bd-grad:linear-gradient(135deg,#fb7185 0%,#e11d48 100%);--bd-glow:rgba(225,29,72,.3)}
@media (max-width:900px){.exdash-breakdown{grid-template-columns:repeat(2,minmax(0,1fr))}}
/* Batch breakdown toggles inside the coloured cards */
.exdash-breakdown-card .student-batch-toggle{margin-top:11px;position:relative;z-index:1}
.exdash-breakdown-card .student-batch-toggle summary{
    list-style:none;cursor:pointer;user-select:none;display:inline-flex;align-items:center;gap:6px;
    padding:4px 11px;border-radius:999px;background:rgba(255,255,255,.2);color:#fff;
    font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;
    transition:background .15s ease;
}
.exdash-breakdown-card .student-batch-toggle summary::-webkit-details-marker{display:none}
.exdash-breakdown-card .student-batch-toggle summary::after{content:"\f107";font-family:FontAwesome;font-size:.68rem;opacity:.85}
.exdash-breakdown-card .student-batch-toggle[open] summary{background:rgba(255,255,255,.32)}
.exdash-breakdown-card .student-batch-toggle[open] summary::after{content:"\f106"}
.exdash-breakdown-card .student-batch-toggle__list{
    margin-top:7px;padding:8px 11px;border-radius:10px;background:rgba(255,255,255,.16);
    backdrop-filter:blur(2px);
}
.exdash-breakdown-card .student-batch-toggle__list span{display:block;font-size:.7rem;font-weight:700;color:#fff;padding:2px 0;white-space:nowrap}
.exdash-breakdown-card .student-batch-toggle__empty{display:inline-block;margin-top:11px;font-size:.64rem;font-weight:700;color:rgba(255,255,255,.75)}

/* Grids */
.exdash-charts{display:grid;grid-template-columns:minmax(0,1.9fr) minmax(0,1fr);gap:16px}
.exdash-bottom{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.35fr);gap:16px;align-items:start}

/* Feed */
.exdash-feed-item{display:flex;align-items:flex-start;gap:12px;padding:11px 2px;border-bottom:1px solid #f1f5f9;font-size:.83rem}
.exdash-feed-item:last-of-type{border-bottom:0}
.exdash-feed-item strong{display:block;font-size:.84rem}
.exdash-feed-item p{margin:2px 0 0;color:var(--ex-muted);font-size:.78rem}
.exdash-feed-item time{margin-left:auto;color:var(--ex-muted);font-size:.7rem;white-space:nowrap;flex:none}
.exdash-feed-icon{width:37px;height:37px;border-radius:11px;display:grid;place-items:center;color:#fff;font-size:.85rem;flex:none}
.exdash-feed-icon--gold{background:var(--ex-grad-gold)}
.exdash-feed-icon--teal{background:var(--ex-grad-teal)}
.exdash-feed-icon--blue{background:var(--ex-grad-blue)}
.exdash-viewall{
    display:inline-flex;align-items:center;gap:7px;margin-top:10px;font-size:.79rem;font-weight:700;
    color:var(--ex-blue);text-decoration:none;
}
.exdash-viewall:hover{text-decoration:underline}

/* Table */
.exdash-table-wrap{overflow-x:auto;margin:0 -6px;padding:0 6px}
.exdash-table{width:100%;border-collapse:collapse;font-size:.82rem;min-width:520px}
.exdash-table th{
    text-align:left;font-size:.64rem;text-transform:uppercase;letter-spacing:.08em;color:var(--ex-muted);
    font-weight:800;padding:9px 10px;border-bottom:1px solid var(--ex-line);
}
.exdash-table td{padding:11px 10px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.exdash-table tbody tr{transition:background .12s ease}
.exdash-table tbody tr:hover{background:rgba(99,102,241,.05)}
.exdash-student{display:flex;align-items:center;gap:10px;min-width:0}
.exdash-student-avatar{
    width:34px;height:34px;border-radius:50%;flex:none;display:grid;place-items:center;
    background:rgba(59,130,246,.1);color:var(--ex-blue);font-size:.7rem;font-weight:800;
}
.exdash-student strong{display:block;font-size:.83rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.exdash-student small{display:block;color:var(--ex-muted);font-size:.7rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.exdash-pill{display:inline-block;font-size:.66rem;font-weight:800;padding:4px 11px;border-radius:999px;white-space:nowrap}
.exdash-pill--active{background:rgba(20,184,166,.13);color:#0d9488}
.exdash-pill--pending{background:rgba(245,158,11,.14);color:#b45309}
.exdash-pill--other{background:rgba(148,163,184,.16);color:#64748b}
.exdash-row-actions{
    width:32px;height:32px;border-radius:9px;border:1px solid var(--ex-line);background:var(--ex-card);
    color:var(--ex-muted);cursor:pointer;
}
.exdash-row-actions:hover{color:var(--ex-blue);border-color:var(--ex-blue)}

/* Management modules */
.exdash-modules-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(228px,1fr));gap:14px}
.exdash-module{
    background:rgba(255,255,255,.7);border:1px solid var(--ex-line);border-radius:15px;padding:15px 17px;min-width:0;
    transition:border-color .15s ease,box-shadow .15s ease;
}
.exdash-module:hover{border-color:rgba(59,130,246,.4);box-shadow:0 8px 22px rgba(15,36,64,.08)}
.exdash-module h3{
    margin:0 0 9px;font-size:.74rem;text-transform:uppercase;letter-spacing:.07em;font-weight:800;
    color:var(--ex-ink);display:flex;align-items:center;gap:9px;
}
.exdash-module h3 i{width:27px;height:27px;border-radius:8px;display:grid;place-items:center;color:#fff;font-size:.75rem;flex:none}
.exdash-module a{
    display:block;font-size:.79rem;color:var(--ex-muted);text-decoration:none;padding:4px 0 4px 2px;
    transition:color .12s ease,transform .12s ease;
}
.exdash-module a:hover{color:var(--ex-blue);transform:translateX(2px)}
.exdash-mod--setup h3 i{background:var(--ex-grad-blue)}
.exdash-mod--records h3 i{background:var(--ex-grad-teal)}
.exdash-mod--academics h3 i{background:var(--ex-grad-violet)}
.exdash-mod--results h3 i{background:linear-gradient(135deg,#f87171,#ef4444)}
.exdash-mod--finance h3 i{background:var(--ex-grad-gold)}
.exdash-mod--campus h3 i{background:linear-gradient(135deg,#34d399,#059669)}
.exdash-mod--admissions h3 i{background:linear-gradient(135deg,#f472b6,#db2777)}

/* Search dropdown */
.exdash-search-results{
    position:absolute;top:calc(100% + 8px);left:0;z-index:60;
    width:max(100%,560px);max-width:calc(100vw - 24px);
    background:var(--ex-card);border:1px solid var(--ex-line);border-radius:16px;
    box-shadow:0 24px 50px rgba(15,36,64,.18);max-height:min(460px,64vh);overflow:auto;
}
.exdash-search-results[hidden]{display:none}
.exdash-search-results .desktop-search-feedback{display:flex;gap:12px;align-items:flex-start;padding:16px;font-size:.84rem;color:var(--ex-muted)}
.exdash-search-results .desktop-search-feedback i{font-size:1.05rem;color:var(--ex-blue);margin-top:2px}
.exdash-search-results .desktop-search-feedback strong{display:block;color:var(--ex-ink);font-size:.86rem;margin-bottom:2px}
.exdash-search-results .desktop-search-summary{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:10px 16px;border-bottom:1px solid var(--ex-line);font-size:.8rem;color:var(--ex-muted)}
.exdash-search-results .desktop-search-summary>div{display:flex;flex-direction:column;gap:1px;min-width:0}
.exdash-search-results .desktop-search-summary strong{color:var(--ex-ink);font-size:.84rem}
.exdash-search-results .desktop-search-summary>span{flex:none;white-space:nowrap;font-size:.72rem}
.exdash-search-results .desktop-search-group{padding:6px 0}
.exdash-search-results .desktop-search-group-title{
    margin:0;padding:6px 16px;font-size:.68rem;text-transform:uppercase;letter-spacing:.07em;
    color:var(--ex-muted);display:flex;align-items:center;gap:7px;font-weight:800;
}
.exdash-search-results .desktop-search-grid{display:flex;flex-direction:column}
.exdash-search-results .desktop-search-card{
    display:flex;align-items:center;gap:14px;
    padding:10px 16px;border-bottom:1px solid #f1f5f9;transition:background .12s ease;
}
.exdash-search-results .desktop-search-card:hover{background:rgba(99,102,241,.05)}
.exdash-search-results .desktop-search-card:last-child{border-bottom:0}
.exdash-search-results .desktop-search-card__main{flex:1;min-width:0}
.exdash-search-results .desktop-search-card__eyebrow{font-size:.62rem;text-transform:uppercase;letter-spacing:.05em;color:var(--ex-muted);font-weight:700}
.exdash-search-results .desktop-search-card__title{display:block;font-weight:700;font-size:.9rem;color:var(--ex-ink);text-decoration:none;margin:1px 0 2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.exdash-search-results .desktop-search-card__title:hover{color:#4f46e5}
.exdash-search-results .desktop-search-card__meta{font-size:.76rem;color:var(--ex-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.exdash-search-results .desktop-search-card__desc{font-size:.76rem;color:var(--ex-muted);margin-top:1px}
.exdash-search-results .desktop-search-card__actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:6px;flex:none;max-width:46%}
.exdash-search-results .desktop-search-card__action{
    font-size:.72rem;color:#4f46e5;text-decoration:none;font-weight:700;
    border:1px solid rgba(99,102,241,.28);background:rgba(99,102,241,.07);
    padding:3px 11px;border-radius:999px;transition:background .12s ease,color .12s ease;
}
.exdash-search-results .desktop-search-card__action:hover{background:#6366f1;color:#fff;text-decoration:none}
@media (max-width:640px){
    .exdash-search-results .desktop-search-card{align-items:flex-start;flex-direction:column;gap:8px}
    .exdash-search-results .desktop-search-card__actions{justify-content:flex-start;max-width:none}
    .exdash-search-results .desktop-search-summary>span{display:none}
}

/* Scrim + responsive */
.exdash-scrim{
    position:fixed;inset:0;background:rgba(10,22,40,.5);backdrop-filter:blur(2px);
    opacity:0;pointer-events:none;transition:opacity .2s ease;z-index:80;
}
body.exdash-lock{overflow:hidden}
@media (max-width:1023px){
    .exdash-sidebar{
        position:fixed;left:0;top:0;bottom:0;z-index:90;height:100dvh;
        transform:translateX(-104%);transition:transform .24s ease;box-shadow:0 0 60px rgba(2,10,25,.45);
    }
    body.exdash-nav-open .exdash-sidebar{transform:none}
    body.exdash-nav-open .exdash-scrim{opacity:1;pointer-events:auto}
    .exdash-burger{display:grid;place-items:center}
}
@media (max-width:1100px){
    .exdash-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media (max-width:900px){
    .exdash-charts{grid-template-columns:1fr}
    .exdash-chart-box{height:240px}
}
@media (max-width:640px){
    .exdash-content{padding:18px 14px 56px;gap:18px}
    .exdash-topbar{padding:10px 14px;gap:10px;flex-wrap:wrap}
    .exdash-search{display:block;flex:1 1 100%;max-width:none;order:5}
    .exdash-user-meta{display:none}
    .exdash-live span{display:none}
    .exdash-live{padding:0 13px}
    .exdash-kpis{grid-template-columns:1fr}
    .exdash-panel{padding:16px 17px}
    .exdash-kpi{padding:17px 19px}
    .exdash-chart-box{height:215px}
    .exdash-bottom{grid-template-columns:1fr}
    .exdash-heading{flex-direction:column}
}
@media (max-width:520px){
    .exdash-tabs{width:100%}
    .exdash-tab{flex:1;justify-content:center;padding:9px 6px;font-size:.76rem}
    .exdash-perf-stats{grid-template-columns:repeat(3,minmax(0,1fr));gap:7px}
    .exdash-perf-stats > div{padding:8px 9px}
    .exdash-perf-stats strong{font-size:.8rem}
}
</style>
<?php include("colour-modes-head.php"); ?>
</head>
<body class="exdash-page">
<div class="exdash-scrim" id="exdashScrim"></div>
<div class="exdash-shell">

    <!-- ================= Sidebar ================= -->
    <aside class="exdash-sidebar" id="exdashSidebar" aria-label="Dashboard navigation">
        <div class="exdash-brand">
            <div class="exdash-brand-logo"><img src="logo/logo-transparent.png" alt="School crest"></div>
            <div class="exdash-brand-text">
                <strong><?php echo exd_e($_School); ?></strong>
                <small>School Management</small>
            </div>
        </div>
        <nav class="exdash-nav">
            <span class="exdash-nav-label">Menu</span>
            <a href="executive-dashboard.php" class="is-active"><i class="fa fa-th-large"></i> Dashboard</a>
            <a href="continuing-students.php"><i class="fa fa-graduation-cap"></i> Students</a>
            <a href="viewusers.php"><i class="fa fa-id-badge"></i> Staff</a>
            <a href="student-attendance.php"><i class="fa fa-check-square-o"></i> Attendance</a>
            <a href="payments.php"><i class="fa fa-money"></i> Fees</a>
            <a href="class-registry.php"><i class="fa fa-building-o"></i> Classes</a>
            <a href="lesson-timetable.php"><i class="fa fa-calendar"></i> Timetable</a>
            <a href="admin.php"><i class="fa fa-cogs"></i> Classic Admin Panel</a>
            <button type="button" class="exdash-nav-btn exdash-nav-btn--violet" id="exdashNavDeck">
                <i class="fa fa-th"></i> All Modules
                <i class="fa fa-chevron-down exdash-nav-btn-arrow"></i>
            </button>
            <span class="exdash-nav-label">Quick Tools</span>
            <a href="register-student.php"><i class="fa fa-user-plus"></i> Register Student</a>
            <a href="register-teacher.php"><i class="fa fa-user-plus"></i> Register Teacher</a>
            <a href="upload-register.php"><i class="fa fa-upload"></i> Upload Registers</a>
            <a href="viewstudents.php"><i class="fa fa-graduation-cap"></i> View Students</a>
            <a href="viewusers.php"><i class="fa fa-users"></i> View Teachers</a>
            <details class="exdash-nav-details">
                <summary><i class="fa fa-briefcase"></i> More Tools <span class="exdash-nav-count">16</span> <i class="fa fa-chevron-down exdash-nav-details-arrow"></i></summary>
                <div class="exdash-nav-sub">
                    <span class="exdash-nav-sub-label">People &amp; Accounts</span>
                    <a href="user-management.php"><i class="fa fa-users"></i> User Management</a>
                    <a href="user-visit-history.php"><i class="fa fa-clock-o"></i> User Visit History</a>
                    <span class="exdash-nav-sub-label">Academics</span>
                    <a href="academic-plan.php"><i class="fa fa-map-signs"></i> Academic Plan</a>
                    <a href="duty-roster.php"><i class="fa fa-calendar-check-o"></i> Duty Roster</a>
                    <a href="report-approval-board.php"><i class="fa fa-check-circle"></i> Report Approval</a>
                    <a href="alumni-hub.php"><i class="fa fa-graduation-cap"></i> Alumni Hub</a>
                    <span class="exdash-nav-sub-label">Departments</span>
                    <a href="department-management.php"><i class="fa fa-sitemap"></i> Departments &amp; HOD Setup</a>
                    <a href="department-team-management.php"><i class="fa fa-users"></i> Manage Department Teams</a>
                    <a href="department-bulk-assignment.php"><i class="fa fa-list-check"></i> Bulk Department Assignment</a>
                    <a href="department-result-approval.php"><i class="fa fa-check-square-o"></i> Department Result Approval</a>
                    <span class="exdash-nav-sub-label">Transport</span>
                    <a href="transport-management.php"><i class="fa fa-bus"></i> Transport Management</a>
                    <a href="transport-student-assignment.php"><i class="fa fa-map-marker"></i> Student Transport Assignment</a>
                    <span class="exdash-nav-sub-label">Communication</span>
                    <a href="online-voting-admin.php"><i class="fa fa-trophy"></i> Online Voting</a>
                    <a href="student-chat-monitor.php"><i class="fa fa-eye"></i> Student Chat Monitor</a>
                    <a href="student-chat-settings.php"><i class="fa fa-sliders"></i> Student Chat Control</a>
                    <a href="teacher-billing-assignment.php"><i class="fa fa-users"></i> Teacher Billing Assignment</a>
                </div>
            </details>
        </nav>
        <div class="exdash-sidebar-foot">
            <a href="change-password.php"><i class="fa fa-key"></i> Change Password</a>
            <a href="logout.php"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </aside>

    <!-- ================= Module Deck (classic dropdown menu) ================= -->
    <div class="exdeck-scrim" id="exdashDeckScrim"></div>
    <section class="exdeck" id="exdashDeck" aria-label="All modules menu">
        <header class="exdeck-head">
            <div class="exdeck-head-logo"><img src="logo/logo-transparent.png" alt="School crest"></div>
            <div>
                <strong><?php echo exd_e($_School); ?></strong>
                <small>Module Menu</small>
            </div>
            <button class="exdeck-close" id="exdashDeckClose" aria-label="Close menu"><i class="fa fa-times"></i></button>
        </header>
        <div class="exdeck-search">
            <i class="fa fa-search"></i>
            <input type="search" id="exdashDeckSearch" placeholder="Filter tools..." autocomplete="off" aria-label="Filter module tools">
            <button class="exdeck-search-clear" id="exdashDeckSearchClear" aria-label="Clear filter" hidden><i class="fa fa-times"></i></button>
        </div>
        <div class="exdeck-scroll">
            <details class="exdeck-section" data-key="setup">
                <summary><span class="exdeck-chip exdeck-chip--setup"><i class="fa fa-cogs"></i></span> School Setup <span class="exdeck-count"></span></summary>
                <div class="exdeck-body">
                    <a href="company-entry.php"><i class="fa fa-angle-right"></i> School Entry</a>
                    <a href="branch-entry.php"><i class="fa fa-angle-right"></i> Branch Entry</a>
                    <a href="batch-entry.php"><i class="fa fa-angle-right"></i> Batch Entry</a>
                    <a href="subject-entry.php"><i class="fa fa-angle-right"></i> Subject Entry</a>
                    <a href="class-entry.php"><i class="fa fa-angle-right"></i> Class Entry</a>
                    <a href="school-data-entry.php"><i class="fa fa-angle-right"></i> School Data Entry</a>
                </div>
            </details>
            <details class="exdeck-section" data-key="records">
                <summary><span class="exdeck-chip exdeck-chip--records"><i class="fa fa-folder-o"></i></span> Student Records <span class="exdeck-count"></span></summary>
                <div class="exdeck-body">
                    <a href="class-registry.php"><i class="fa fa-angle-right"></i> Class Registry</a>
                    <a href="upload-class-registry.php"><i class="fa fa-angle-right"></i> Upload Class Registry</a>
                    <a href="view-class-registry.php"><i class="fa fa-angle-right"></i> View Class Registry</a>
                    <a href="term-registry.php"><i class="fa fa-angle-right"></i> Semester Registry</a>
                    <a href="group-term-registry.php"><i class="fa fa-angle-right"></i> Group Semester Registry</a>
                    <a href="upload-semester-registry.php"><i class="fa fa-angle-right"></i> Upload Semester Registry</a>
                    <a href="promotion-center.php"><i class="fa fa-angle-right"></i> Promotion Center</a>
                    <a href="continuing-students.php"><i class="fa fa-angle-right"></i> Continuing Students</a>
                    <a href="student-history.php"><i class="fa fa-angle-right"></i> Student Transcript</a>
                </div>
            </details>
            <details class="exdeck-section" data-key="academics">
                <summary><span class="exdeck-chip exdeck-chip--academics"><i class="fa fa-book"></i></span> Academics &amp; Staff <span class="exdeck-count"></span></summary>
                <div class="exdeck-body">
                    <a href="subject-classification.php"><i class="fa fa-angle-right"></i> Subject Classification</a>
                    <a href="view-subject-classified.php"><i class="fa fa-angle-right"></i> View Subject Classified</a>
                    <a href="subject-assignment.php"><i class="fa fa-angle-right"></i> Subject Assignment</a>
                    <a href="view-all-subject-assigned.php"><i class="fa fa-angle-right"></i> View Subject(s) Assigned</a>
                    <a href="course-registration-admin.php"><i class="fa fa-angle-right"></i> Course Registration</a>
                    <a href="class-teacher-assignment.php"><i class="fa fa-angle-right"></i> Class Teacher Assignment</a>
                    <a href="student-attendance.php"><i class="fa fa-angle-right"></i> Student Attendance</a>
                    <a href="student-attendance-report.php"><i class="fa fa-angle-right"></i> Attendance Summary</a>
                    <a href="duty-roster.php"><i class="fa fa-angle-right"></i> Duty Roster</a>
                    <a href="lesson-timetable.php"><i class="fa fa-angle-right"></i> Lesson Timetable Entry</a>
                    <a href="lesson-timetable-report.php"><i class="fa fa-angle-right"></i> Lesson Timetable Report</a>
                </div>
            </details>
            <details class="exdeck-section" data-key="results">
                <summary><span class="exdeck-chip exdeck-chip--results"><i class="fa fa-graduation-cap"></i></span> Results &amp; Exams <span class="exdeck-count"></span></summary>
                <div class="exdeck-body">
                    <a href="student-terminal-data.php"><i class="fa fa-angle-right"></i> Student Remark Data</a>
                    <a href="upload-student-remark-data.php"><i class="fa fa-angle-right"></i> Upload Students Remark Data</a>
                    <a href="continuous-assessment.php"><i class="fa fa-angle-right"></i> Continuous Assessment</a>
                    <a href="terminal-report.php"><i class="fa fa-angle-right"></i> Examination Report</a>
                    <a href="report-approval-board.php"><i class="fa fa-angle-right"></i> Report Approval</a>
                    <a href="scores-report-all.php"><i class="fa fa-angle-right"></i> Scores Report</a>
                    <a href="waec-analysis.php"><i class="fa fa-angle-right"></i> WAEC Analysis</a>
                    <a href="internal-exam-analysis.php"><i class="fa fa-angle-right"></i> Internal Exams Analysis</a>
                    <a href="examanalysis-subject.php"><i class="fa fa-angle-right"></i> Exam Analysis : Subject</a>
                    <a href="examanalysis-rank.php"><i class="fa fa-angle-right"></i> Exam Analysis : Rank</a>
                    <a href="examinationtimetable.php"><i class="fa fa-angle-right"></i> Exam Time Table Entry</a>
                    <a href="examinationtimetablereport.php"><i class="fa fa-angle-right"></i> Exam Time Table Report</a>
                </div>
            </details>
            <details class="exdeck-section" data-key="finance">
                <summary><span class="exdeck-chip exdeck-chip--finance"><i class="fa fa-money"></i></span> Finance <span class="exdeck-count"></span></summary>
                <div class="exdeck-body">
                    <a href="payments.php"><i class="fa fa-angle-right"></i> Payments</a>
                    <a href="class-billing.php"><i class="fa fa-angle-right"></i> Billing Manager</a>
                    <a href="teacher-billing-assignment.php"><i class="fa fa-angle-right"></i> Teacher Billing Assignment</a>
                    <a href="student-billing.php"><i class="fa fa-angle-right"></i> Bill Student</a>
                    <a href="group-student-billing.php"><i class="fa fa-angle-right"></i> Bill Group Students</a>
                    <a href="print-student-bills.php"><i class="fa fa-angle-right"></i> Print Student Bills</a>
                    <a href="daily-report.php"><i class="fa fa-angle-right"></i> Daily Report</a>
                    <a href="payment-analysis.php"><i class="fa fa-angle-right"></i> Payment Report</a>
                    <a href="bills-report.php"><i class="fa fa-angle-right"></i> Bills Report</a>
                    <a href="item-bill-report.php"><i class="fa fa-angle-right"></i> Item Bill Report</a>
                </div>
            </details>
            <details class="exdeck-section" data-key="campus">
                <summary><span class="exdeck-chip exdeck-chip--campus"><i class="fa fa-home"></i></span> Campus &amp; Welfare <span class="exdeck-count"></span></summary>
                <div class="exdeck-body">
                    <a href="house-entry.php"><i class="fa fa-angle-right"></i> House Entry</a>
                    <a href="house-master-assignment.php"><i class="fa fa-angle-right"></i> House Master Assignment</a>
                    <a href="student-house-assignment.php"><i class="fa fa-angle-right"></i> Student House Assignment</a>
                    <a href="senior-house-assignment.php"><i class="fa fa-angle-right"></i> Senior House Assignment</a>
                    <a href="senior-house-dashboard.php"><i class="fa fa-angle-right"></i> Senior House Dashboard</a>
                    <a href="counsellor-assignment.php"><i class="fa fa-angle-right"></i> Counsellor Assignment</a>
                </div>
            </details>
            <details class="exdeck-section" data-key="admissions">
                <summary><span class="exdeck-chip exdeck-chip--admissions"><i class="fa fa-bullhorn"></i></span> Admissions &amp; Communication <span class="exdeck-count"></span></summary>
                <div class="exdeck-body">
                    <a href="online-admission-admin.php"><i class="fa fa-angle-right"></i> Online Admission</a>
                    <a href="online-voting-admin.php"><i class="fa fa-angle-right"></i> Online Voting</a>
                    <a href="website-editor.php"><i class="fa fa-angle-right"></i> Manage Website</a>
                    <a href="website-gallery-editor.php"><i class="fa fa-angle-right"></i> Manage Gallery</a>
                    <a href="website-announcement-editor.php"><i class="fa fa-angle-right"></i> Manage Announcements</a>
                    <a href="notification.php"><i class="fa fa-angle-right"></i> Send Notification</a>
                    <a href="student-chat-monitor.php"><i class="fa fa-angle-right"></i> Student Chat Monitor</a>
                    <a href="student-chat-settings.php"><i class="fa fa-angle-right"></i> Student Chat Control</a>
                    <a href="club-admin.php"><i class="fa fa-angle-right"></i> Manage Clubs &amp; Societies</a>
                    <a href="enablesmsalert.php"><i class="fa fa-angle-right"></i> Enable SMS Alert</a>
                    <a href="smsreport.php"><i class="fa fa-angle-right"></i> SMS Reporting</a>
                    <a href="smsreportdata.php"><i class="fa fa-angle-right"></i> SMS Data</a>
                </div>
            </details>
        </div>
        <div class="exdeck-empty" id="exdashDeckEmpty">No tools match your search</div>
        <footer class="exdeck-foot">Your classic menu &mdash; modernised for the Aurora dashboard</footer>
    </section>

    <!-- ================= Main ================= -->
    <div class="exdash-main">
        <header class="exdash-topbar">
            <button class="exdash-burger" id="exdashBurger" aria-label="Open navigation"><i class="fa fa-bars"></i></button>
            <div class="exdash-search">
                <i class="fa fa-search"></i>
                <input type="search" id="exdashSearch" placeholder="Search students, staff, classes, tools..." autocomplete="off">
                <div class="exdash-search-results" id="exdashSearchResults" hidden></div>
            </div>
            <div class="exdash-top-actions">
                <?php if($_ShowLiveUsersIndicator){ ?>
                <span class="exdash-live" id="exdashLiveUsers" title="Users active in the last 5 minutes">
                    <span class="exdash-live-dot"></span><i class="fa fa-signal"></i>
                    <span>Live</span> <strong id="exdashLiveUsersCount"><?php echo number_format((int)$_LiveUsersCount); ?></strong>
                </span>
                <?php } ?>
                <?php if($_PendingDocumentReviews > 0){ ?>
                <a class="exdash-docs" href="alumni-document-admin.php?status=submitted" title="Alumni document requests awaiting your review">
                    <i class="fa fa-bell" aria-hidden="true"></i>
                    <span class="exdash-docs-badge"><?php echo (int)$_PendingDocumentReviews; ?></span>
                </a>
                <?php } ?>
                <div style="position:relative">
                    <button class="exdash-bell" id="exdashBell" aria-label="Notifications">
                        <i class="fa fa-bell-o"></i>
                        <?php if($_UnreadChangeCount > 0){ ?><span class="exdash-bell-badge"><?php echo number_format($_UnreadChangeCount); ?></span><?php } ?>
                    </button>
                    <div class="exdash-bell-menu" id="exdashBellMenu">
                        <header>
                            <span>System Notifications</span>
                            <?php if($_UnreadChangeCount > 0){ ?><span class="exdash-note-count"><?php echo number_format($_UnreadChangeCount); ?> unread</span><?php } ?>
                        </header>
                        <div class="exdash-note-list">
                        <?php if(count($_ChangeNotifications) > 0){ foreach($_ChangeNotifications as $note){
                            $noteUnread = strtolower(trim((string)$note['status'])) === 'unread';
                            $noteAction = exd_change_action($note);
                            $actorType = trim((string)$note['actor_type']);
                            $noteIcon = $actorType === 'Student' ? 'fa-graduation-cap' : ($actorType === 'Teacher' ? 'fa-user' : ($actorType === 'Portal' ? 'fa-life-ring' : ($actorType === 'Alumni' ? 'fa-address-card' : 'fa-user-md')));
                        ?>
                        <div class="exdash-note-item<?php echo $noteUnread ? ' is-unread' : ''; ?>">
                            <div class="exdash-note-icon<?php echo $noteUnread ? '' : ' is-read'; ?>"><i class="fa <?php echo $noteIcon; ?>"></i></div>
                            <div class="exdash-note-body">
                                <div class="exdash-note-top">
                                    <strong><?php echo exd_e(trim((string)$note['actor_name']) !== '' ? $note['actor_name'] : $note['actor_userid']); ?></strong>
                                    <time><?php echo exd_e(exd_when($note['datetimeentry'])); ?></time>
                                </div>
                                <span class="exdash-note-action"><?php echo exd_e(ucwords(strtolower(str_replace('_', ' ', (string)$note['action_type'])))); ?></span>
                                <?php if(trim((string)$note['details']) !== ''){ ?><p><?php echo exd_e($note['details']); ?></p><?php } ?>
                                <?php if($noteAction){ ?><a class="exdash-note-link" href="<?php echo exd_e($noteAction[0]); ?>"><i class="fa fa-arrow-right"></i> <?php echo exd_e($noteAction[1]); ?></a><?php } ?>
                            </div>
                            <?php if($noteUnread){ ?><span class="exdash-note-dot" title="Unread"></span><?php } ?>
                        </div>
                        <?php }}else{ ?>
                        <div class="exdash-note-empty"><i class="fa fa-bell-slash-o"></i> No notifications yet.<br>Teacher, student, portal and alumni events will appear here.</div>
                        <?php } ?>
                        </div>
                        <div class="exdash-note-foot">
                            <?php if($_UnreadChangeCount > 0){ ?>
                            <form method="post" action="executive-dashboard.php" class="exdash-note-mark">
                                <input type="hidden" name="csrf" value="<?php echo exd_e($_SESSION['executive_csrf']); ?>">
                                <button type="submit" name="mark_changes_read"><i class="fa fa-check"></i> Mark all as read</button>
                            </form>
                            <?php } ?>
                            <a class="exdash-bell-all" href="executive-dashboard.php?tab=notifications"><i class="fa fa-bell"></i> View all</a>
                        </div>
                    </div>
                </div>
                <div class="exdash-user" id="exdashUser">
                    <button type="button" class="exdash-user-btn" id="exdashUserBtn" aria-haspopup="true" aria-expanded="false">
                        <div class="exdash-avatar"><?php echo exd_e($_Initials); ?></div>
                        <div class="exdash-user-meta">
                            <strong><?php echo exd_e($_FullName); ?></strong>
                            <small><?php echo exd_e($_RoleLabel); ?></small>
                        </div>
                        <i class="fa fa-chevron-down exdash-user-caret"></i>
                    </button>
                    <div class="exdash-user-menu" id="exdashUserMenu">
                        <div class="exdash-user-menu-head">
                            <div class="exdash-avatar exdash-avatar--lg"><?php echo exd_e($_Initials); ?></div>
                            <div>
                                <strong><?php echo exd_e($_FullName); ?></strong>
                                <small><?php echo exd_e($_RoleLabel); ?></small>
                            </div>
                        </div>
                        <div class="exdash-theme">
                            <span class="exdash-theme-label">Appearance</span>
                            <button type="button" class="exdash-theme-row" data-admin-theme-value="light"><i class="admin-theme-dot admin-theme-dot--light"></i> Light</button>
                            <button type="button" class="exdash-theme-row" data-admin-theme-value="dark"><i class="admin-theme-dot admin-theme-dot--dark"></i> Dark</button>
                            <button type="button" class="exdash-theme-row" data-admin-theme-value="classic"><i class="admin-theme-dot admin-theme-dot--classic"></i> Classic</button>
                            <button type="button" class="exdash-theme-row" data-admin-theme-value="ocean"><i class="admin-theme-dot admin-theme-dot--ocean"></i> Ocean Breeze</button>
                            <button type="button" class="exdash-theme-row" data-admin-theme-value="emerald"><i class="admin-theme-dot admin-theme-dot--emerald"></i> Emerald</button>
                            <button type="button" class="exdash-theme-row" data-admin-theme-value="royal"><i class="admin-theme-dot admin-theme-dot--royal"></i> Royal Velvet</button>
                        </div>
                        <a href="edit-account.php"><i class="fa fa-user"></i> Edit Profile</a>
                        <a href="uploaduser-image.php"><i class="fa fa-camera"></i> Upload Image</a>
                        <a href="user-management.php"><i class="fa fa-users"></i> User Management</a>
                        <a href="user-visit-history.php"><i class="fa fa-clock-o"></i> User Visit History</a>
                        <a href="messages.php"><i class="fa fa-envelope-o"></i> Messages</a>
                        <a href="admin-password-reset.php"><i class="fa fa-key"></i> Admin Password Reset</a>
                        <a href="change-password.php"><i class="fa fa-lock"></i> Change Password</a>
                        <a href="logout.php" class="exdash-user-logout"><i class="fa fa-power-off"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="exdash-content">
            <div class="exdash-heading">
                <div>
                    <h1>Administrator Dashboard</h1>
                    <p><i class="fa fa-calendar"></i> <?php echo date('l, d F Y'); ?></p>
                </div>
                <div class="exdash-quick" aria-label="Quick actions">
                    <a class="exdash-quick-btn" href="promotion-center.php"><i class="fa fa-level-up"></i> Promote</a>
                    <a class="exdash-quick-btn" href="student-history.php"><i class="fa fa-history"></i> Transcript</a>
                    <a class="exdash-quick-btn" href="result-access-admin.php"><i class="fa fa-lock"></i> Access</a>
                    <a class="exdash-quick-btn" href="result-access-payments.php"><i class="fa fa-money"></i> Payments</a>
                    <a class="exdash-quick-btn" href="alumni-hub.php"><i class="fa fa-graduation-cap"></i> Alumni</a>
                </div>
            </div>

            <!-- KPI cards -->
            <section class="exdash-kpis" aria-label="Key indicators">
                <article class="exdash-kpi exdash-kpi--blue">
                    <div class="exdash-kpi-icon"><i class="fa fa-users"></i></div>
                    <div class="exdash-kpi-body">
                        <span>Total Students</span>
                        <strong><?php echo number_format($totalStudents); ?></strong>
                        <em class="exdash-kpi-trend"><i class="fa fa-arrow-up"></i> Active enrolment</em>
                    </div>
                </article>
                <article class="exdash-kpi exdash-kpi--teal">
                    <div class="exdash-kpi-icon"><i class="fa fa-check-square-o"></i></div>
                    <div class="exdash-kpi-body">
                        <span>Attendance Today</span>
                        <strong><?php echo $attPercent === null ? '&mdash;' : $attPercent . '%'; ?></strong>
                        <em class="exdash-kpi-trend <?php echo $attPercent === null ? 'exdash-kpi-trend--muted' : ''; ?>">
                            <?php if($attPercent === null){ ?><i class="fa fa-clock-o"></i> Not marked yet<?php }else{ ?><i class="fa fa-arrow-up"></i> <?php echo number_format($attMarked); ?> marked<?php } ?>
                        </em>
                    </div>
                </article>
                <article class="exdash-kpi exdash-kpi--gold">
                    <div class="exdash-kpi-icon"><i class="fa fa-credit-card"></i></div>
                    <div class="exdash-kpi-body">
                        <span>Fees Collected</span>
                        <strong><?php echo $feesPercent === null ? '&mdash;' : $feesPercent . '%'; ?></strong>
                        <em class="exdash-kpi-trend <?php echo $feesPercent === null ? 'exdash-kpi-trend--muted' : ''; ?>">
                            <?php if($feesPercent === null){ ?><i class="fa fa-info-circle"></i> <?php echo $_Currency . number_format($feesCollected, 2); ?> collected<?php }else{ ?><i class="fa fa-arrow-up"></i> <?php echo $_Currency . number_format($feesCollected, 2); ?><?php } ?>
                        </em>
                    </div>
                </article>
                <article class="exdash-kpi exdash-kpi--violet">
                    <div class="exdash-kpi-icon"><i class="fa fa-id-badge"></i></div>
                    <div class="exdash-kpi-body">
                        <span>Teaching Staff</span>
                        <strong><?php echo number_format($teachingStaff); ?></strong>
                        <em class="exdash-kpi-trend"><i class="fa fa-arrow-up"></i> Active this term</em>
                    </div>
                </article>
            </section>

            <!-- Section tabs -->
            <nav class="exdash-tabs" role="tablist" aria-label="Dashboard sections">
                <button type="button" class="exdash-tab active" data-tab="overview" role="tab" aria-selected="true"><i class="fa fa-dashboard"></i> Overview</button>
                <button type="button" class="exdash-tab" data-tab="analytics" role="tab" aria-selected="false"><i class="fa fa-line-chart"></i> Analytics</button>
                <button type="button" class="exdash-tab" data-tab="notifications" role="tab" aria-selected="false"><i class="fa fa-bell-o"></i> Notifications <?php if($_UnreadChangeCount > 0){ ?><span class="exdash-tab-badge"><?php echo number_format($_UnreadChangeCount); ?></span><?php } ?></button>
            </nav>

            <div class="exdash-pane" data-pane="overview">
            <!-- Charts -->
            <section class="exdash-charts">
                <div class="exdash-panel">
                    <div class="exdash-panel-head">
                        <div>
                            <h2><span class="exdash-head-icon exdash-head-icon--blue"><i class="fa fa-line-chart"></i></span> Enrolment Trend</h2>
                            <small>New student registrations per month</small>
                        </div>
                    </div>
                    <div class="exdash-chart-box"><canvas id="exdashTrendChart" aria-label="Enrolment trend chart" role="img"></canvas></div>
                </div>
                <div class="exdash-panel">
                    <div class="exdash-panel-head">
                        <div>
                            <h2><span class="exdash-head-icon exdash-head-icon--teal"><i class="fa fa-pie-chart"></i></span> Attendance Overview</h2>
                            <small><?php echo $attMarked > 0 ? 'Today' : 'No session marked today'; ?></small>
                        </div>
                    </div>
                    <?php if($attMarked > 0){ ?>
                    <div class="exdash-chart-box" style="height:190px"><canvas id="exdashAttChart" aria-label="Attendance chart" role="img"></canvas></div>
                    <div class="exdash-donut-legend">
                        <div><span class="exdash-dot" style="background:#14b8a6"></span> Present <b><?php echo number_format($attPresent); ?></b></div>
                        <div><span class="exdash-dot" style="background:#f87171"></span> Absent <b><?php echo number_format($attAbsent); ?></b></div>
                        <div><span class="exdash-dot" style="background:#fbbf24"></span> Late <b><?php echo number_format($attLate); ?></b></div>
                        <?php if($attOther > 0){ ?><div><span class="exdash-dot" style="background:#94a3b8"></span> Other <b><?php echo number_format($attOther); ?></b></div><?php } ?>
                    </div>
                    <?php }else{ ?>
                    <div class="exdash-empty-note"><i class="fa fa-check-square-o" style="font-size:1.4rem;display:block;margin-bottom:8px;color:var(--ex-muted)"></i>Attendance has not been marked today.<br>Once a teacher submits a register, the breakdown appears here.</div>
                    <?php } ?>
                </div>
            </section>

            <!-- Feed + table -->
            <section class="exdash-bottom">
                <div class="exdash-panel exdash-feed">
                    <div class="exdash-panel-head">
                        <div>
                            <h2><span class="exdash-head-icon exdash-head-icon--violet"><i class="fa fa-bolt"></i></span> Recent Activity</h2>
                        </div>
                    </div>
                    <?php foreach($feed as $item){ ?>
                    <div class="exdash-feed-item">
                        <div class="exdash-feed-icon exdash-feed-icon--<?php echo exd_e($item['tint']); ?>"><i class="fa <?php echo exd_e($item['icon']); ?>"></i></div>
                        <div style="min-width:0">
                            <strong><?php echo exd_e($item['title']); ?></strong>
                            <p><?php echo exd_e($item['detail']); ?></p>
                        </div>
                        <time><?php echo exd_e($item['when']); ?></time>
                    </div>
                    <?php } ?>
                    <a class="exdash-viewall" href="daily-report.php">View all activity <i class="fa fa-arrow-right"></i></a>
                </div>

                <div class="exdash-panel">
                    <div class="exdash-panel-head">
                        <div>
                            <h2><span class="exdash-head-icon exdash-head-icon--blue"><i class="fa fa-user-plus"></i></span> Recent Students</h2>
                            <small>Latest admissions</small>
                        </div>
                    </div>
                    <div class="exdash-table-wrap">
                        <table class="exdash-table" id="exdashStudentTable">
                            <thead>
                                <tr><th>Student</th><th>ID</th><th>Admission Date</th><th>Status</th><th></th></tr>
                            </thead>
                            <tbody>
                            <?php if(count($recentStudents) > 0){ foreach($recentStudents as $stu){
                                $sName = trim($stu['firstname'] . ' ' . $stu['surname']);
                                if($sName === ''){ $sName = 'Student'; }
                                $sParts = preg_split('/\s+/', $sName);
                                $sInitials = strtoupper(substr($sParts[0], 0, 1) . substr(end($sParts), 0, 1));
                                $sStatus = strtolower(trim((string)$stu['status']));
                                $sStatusClass = $sStatus === 'active' ? 'exdash-pill--active' : ($sStatus === 'pending' ? 'exdash-pill--pending' : 'exdash-pill--other');
                            ?>
                                <tr>
                                    <td>
                                        <div class="exdash-student">
                                            <div class="exdash-student-avatar"><?php echo exd_e($sInitials); ?></div>
                                            <div style="min-width:0">
                                                <strong><?php echo exd_e($sName); ?></strong>
                                                <small><?php echo exd_e(trim($stu['firstname'] . ' ' . $stu['othernames'] . ' ' . $stu['surname'])); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo exd_e($stu['userid']); ?></td>
                                    <td><?php echo $stu['registereddatetime'] ? exd_e(date('d M Y', strtotime($stu['registereddatetime']))) : '&mdash;'; ?></td>
                                    <td><span class="exdash-pill <?php echo $sStatusClass; ?>"><?php echo exd_e(ucfirst($sStatus !== '' ? $sStatus : 'active')); ?></span></td>
                                    <td><a class="exdash-row-actions" title="Find student" aria-label="Find student" href="display-student.php?search-item=<?php echo rawurlencode($stu['userid']); ?>"><i class="fa fa-ellipsis-h"></i></a></td>
                                </tr>
                            <?php }}else{ ?>
                                <tr><td colspan="5"><div class="exdash-empty-note">No student records found.</div></td></tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            </div><!-- /overview pane -->

            <div class="exdash-pane" data-pane="analytics" hidden>
            <!-- Student breakdown cards (same numbers as the classic dashboard) -->
            <section class="exdash-breakdown" aria-label="Student breakdown by gender and residence">
                <div class="exdash-breakdown-card exdash-breakdown-card--blue">
                    <h4><i class="fa fa-male"></i> Boys &middot; Day</h4>
                    <p><?php echo number_format($popValues[0]); ?></p>
                    <?php echo dashboard_student_batch_breakdown_html($_ExdBatchSummary, 'day_boys', 'Batches', 'No batch yet.'); ?>
                </div>
                <div class="exdash-breakdown-card exdash-breakdown-card--sky">
                    <h4><i class="fa fa-male"></i> Boys &middot; Boarding</h4>
                    <p><?php echo number_format($popValues[1]); ?></p>
                    <?php echo dashboard_student_batch_breakdown_html($_ExdBatchSummary, 'boarding_boys', 'Batches', 'No batch yet.'); ?>
                </div>
                <div class="exdash-breakdown-card exdash-breakdown-card--pink">
                    <h4><i class="fa fa-female"></i> Girls &middot; Day</h4>
                    <p><?php echo number_format($popValues[2]); ?></p>
                    <?php echo dashboard_student_batch_breakdown_html($_ExdBatchSummary, 'day_girls', 'Batches', 'No batch yet.'); ?>
                </div>
                <div class="exdash-breakdown-card exdash-breakdown-card--rose">
                    <h4><i class="fa fa-female"></i> Girls &middot; Boarding</h4>
                    <p><?php echo number_format($popValues[3]); ?></p>
                    <?php echo dashboard_student_batch_breakdown_html($_ExdBatchSummary, 'boarding_girls', 'Batches', 'No batch yet.'); ?>
                </div>
            </section>

            <!-- Student population + subject performance -->
            <section class="exdash-charts">
                <div class="exdash-panel">
                    <div class="exdash-panel-head">
                        <div>
                            <h2><span class="exdash-head-icon exdash-head-icon--violet"><i class="fa fa-users"></i></span> Student Population</h2>
                            <small>Active students with an active class, by gender and residence</small>
                        </div>
                    </div>
                    <div class="exdash-chart-box"><canvas id="exdashPopChart" aria-label="Student population chart" role="img"></canvas></div>
                </div>
                <div class="exdash-panel">
                    <div class="exdash-panel-head">
                        <div>
                            <h2><span class="exdash-head-icon exdash-head-icon--gold"><i class="fa fa-graduation-cap"></i></span> Subject Performance</h2>
                            <small><?php echo exd_e($perfScopeLabel); ?></small>
                        </div>
                        <?php if($perfRowsCount > 0){ ?><span class="exdash-chip">Top <?php echo (int)$perfRowsCount; ?> subjects</span><?php } ?>
                    </div>
                    <form class="exdash-scope" method="get" action="executive-dashboard.php">
                        <select name="perf_class" aria-label="Class">
                            <option value="">All classes</option>
                            <?php foreach($perfClassOptions as $opt){ ?>
                            <option value="<?php echo exd_e($opt['class_entryid']); ?>" <?php echo $_PerfClassFilter === (string)$opt['class_entryid'] ? 'selected' : ''; ?>><?php echo exd_e($opt['class_name']); ?></option>
                            <?php } ?>
                        </select>
                        <select name="perf_batch" aria-label="Batch">
                            <option value="">All batches</option>
                            <?php foreach($perfBatchOptions as $opt){ ?>
                            <option value="<?php echo exd_e($opt['batchid']); ?>" <?php echo $_PerfBatchFilter === (string)$opt['batchid'] ? 'selected' : ''; ?>><?php echo exd_e($opt['batch']); ?></option>
                            <?php } ?>
                        </select>
                        <select name="perf_year" aria-label="Academic year">
                            <option value="">All years</option>
                            <?php foreach($perfYearOptions as $y){ ?>
                            <option value="<?php echo exd_e($y); ?>" <?php echo $_PerfYearFilter === $y ? 'selected' : ''; ?>>AY <?php echo exd_e($y); ?></option>
                            <?php } ?>
                        </select>
                        <select name="perf_term" aria-label="Semester">
                            <option value="">All semesters</option>
                            <?php foreach($perfTermOptions as $t){ ?>
                            <option value="<?php echo exd_e($t); ?>" <?php echo $_PerfTermFilter === $t ? 'selected' : ''; ?>>Semester <?php echo exd_e($t); ?></option>
                            <?php } ?>
                        </select>
                        <button type="submit" class="exdash-scope-btn"><i class="fa fa-filter"></i> Apply</button>
                        <?php if($perfHasFilter){ ?><a class="exdash-scope-reset" href="executive-dashboard.php"><i class="fa fa-undo"></i> Reset</a><?php } ?>
                    </form>
                    <?php if($perfRowsCount > 0){ ?>
                    <div class="exdash-perf-stats">
                        <div><span>Overall average</span><strong><?php echo number_format($overallAvg, 1); ?>%</strong></div>
                        <div><span>Overall pass rate</span><strong><?php echo number_format($overallPass, 1); ?>%</strong></div>
                        <div><span>Best subject</span><strong><?php echo exd_e($bestSubject); ?> &middot; <?php echo number_format($bestSubjectScore, 1); ?>%</strong></div>
                    </div>
                    <div class="exdash-chart-box"><canvas id="exdashPerfChart" aria-label="Subject performance chart" role="img"></canvas></div>
                    <?php }else{ ?>
                    <div class="exdash-empty-note"><i class="fa fa-bar-chart" style="font-size:1.4rem;display:block;margin-bottom:8px;color:var(--ex-muted)"></i><?php if($perfHasFilter){ ?>No scores found for the selected scope.<br>Try a different class, batch, year or semester &mdash; or <a href="executive-dashboard.php" style="color:var(--ex-blue)">reset the filters</a>.<?php }else{ ?>No scores recorded yet.<br>Once teachers submit class and exam scores, subject performance appears here.<?php } ?></div>
                    <?php } ?>
                </div>
            </section>
            </div><!-- /analytics pane -->

            <div class="exdash-pane" data-pane="notifications" hidden>
            <!-- Full system notifications timeline -->
            <section class="exdash-panel">
                <div class="exdash-panel-head">
                    <div>
                        <h2><span class="exdash-head-icon exdash-head-icon--violet"><i class="fa fa-bell"></i></span> System Notifications</h2>
                        <small>Teacher, student, portal, headmaster and alumni events &mdash; newest first</small>
                    </div>
                    <div class="exnote-tools">
                        <?php if($_UnreadChangeCount > 0){ ?>
                        <span class="exnote-stat"><span class="exnote-stat-dot"></span><?php echo number_format($_UnreadChangeCount); ?> unread</span>
                        <form method="post" action="executive-dashboard.php?tab=notifications" class="exdash-note-mark">
                            <input type="hidden" name="csrf" value="<?php echo exd_e($_SESSION['executive_csrf']); ?>">
                            <button type="submit" name="mark_changes_read"><i class="fa fa-check"></i> Mark all as read</button>
                        </form>
                        <?php } ?>
                    </div>
                </div>
                <div class="exnote-list">
                    <?php if(count($_AllChangeNotifications) > 0){
                        $_LastDayLabel = null;
                        foreach($_AllChangeNotifications as $note){
                            $actorType = trim((string)$note['actor_type']);
                            /* Highlight only what the unread badge counts (same rule as the classic panel) */
                            $noteUnread = strtolower(trim((string)$note['status'])) === 'unread'
                                && (in_array($actorType, array('Teacher','Student','Portal','Headmaster'), true)
                                    || ($actorType === 'Alumni' && in_array(strtoupper(trim((string)$note['action_type'])), array('ALUMNI_CONCERN_SUBMITTED','ALUMNI_DONATION_RECEIVED'), true)));
                            $noteAction = exd_change_action($note);
                            $actorType = trim((string)$note['actor_type']);
                            $noteIcon = $actorType === 'Student' ? 'fa-graduation-cap' : ($actorType === 'Teacher' ? 'fa-user' : ($actorType === 'Portal' ? 'fa-life-ring' : ($actorType === 'Alumni' ? 'fa-address-card' : 'fa-user-md')));
                            $noteTint = $actorType === 'Student' ? 'ex-grad-blue' : ($actorType === 'Teacher' ? 'ex-grad-teal' : ($actorType === 'Portal' ? 'ex-grad-gold' : 'ex-grad-violet'));
                            $dayLabel = exd_day_label($note['datetimeentry']);
                            if($dayLabel !== $_LastDayLabel){
                                $_LastDayLabel = $dayLabel;
                    ?>
                    <div class="exnote-day"><span><?php echo exd_e($dayLabel); ?></span></div>
                    <?php } ?>
                    <article class="exnote-item<?php echo $noteUnread ? ' is-unread' : ''; ?>">
                        <div class="exnote-icon" style="background:var(--<?php echo $noteTint; ?>)"><i class="fa <?php echo $noteIcon; ?>"></i></div>
                        <div class="exnote-body">
                            <div class="exnote-top">
                                <strong><?php echo exd_e(trim((string)$note['actor_name']) !== '' ? $note['actor_name'] : $note['actor_userid']); ?></strong>
                                <span class="exnote-role"><?php echo exd_e($actorType); ?></span>
                                <time><?php echo exd_e(exd_when($note['datetimeentry'])); ?></time>
                            </div>
                            <span class="exnote-action"><?php echo exd_e(ucwords(strtolower(str_replace('_', ' ', (string)$note['action_type'])))); ?></span>
                            <?php if(trim((string)$note['details']) !== ''){ ?><p><?php echo exd_e($note['details']); ?></p><?php } ?>
                            <?php if($noteAction){ ?><a class="exnote-link" href="<?php echo exd_e($noteAction[0]); ?>"><i class="fa fa-arrow-right"></i> <?php echo exd_e($noteAction[1]); ?></a><?php } ?>
                        </div>
                        <?php if($noteUnread){ ?><span class="exdash-note-dot" style="top:18px" title="Unread"></span><?php } ?>
                    </article>
                    <?php }}else{ ?>
                    <div class="exnote-empty"><i class="fa fa-bell-slash-o"></i><h3>All caught up</h3><p>Teacher, student, portal, headmaster and alumni events will appear here as they happen.</p></div>
                    <?php } ?>
                </div>
            </section>
            </div><!-- /notifications pane -->
        </main>
    </div>
</div>
<script src="js/vendor/chart.umd.min.js?v=4.5.1"></script>
<script>
(function () {
    'use strict';

    /* ---------- Sidebar drawer (mobile) ---------- */
    var burger = document.getElementById('exdashBurger');
    var scrim = document.getElementById('exdashScrim');
    var sidebar = document.getElementById('exdashSidebar');
    function closeNav(){ document.body.classList.remove('exdash-nav-open'); document.body.classList.toggle('exdash-lock', !!document.querySelector('#exdashDeck.is-open')); if(burger){ burger.setAttribute('aria-expanded','false'); } }
    if(burger){
        burger.addEventListener('click', function () {
            var open = document.body.classList.toggle('exdash-nav-open');
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('exdash-lock', open && window.innerWidth < 1024);
        });
    }
    if(scrim){ scrim.addEventListener('click', closeNav); }
    if(sidebar){
        sidebar.addEventListener('click', function (e) {
            if(e.target && e.target.closest('a') && window.innerWidth < 1024){ closeNav(); }
        });
    }

    /* ---------- Live users heartbeat (same endpoint as the classic admin menu) ---------- */
    var liveUsersNode = document.getElementById('exdashLiveUsersCount');
    var liveRunning = false;
    function exdLiveHeartbeat(){
        if(liveRunning || !liveUsersNode){ return; }
        liveRunning = true;
        fetch('user-activity-heartbeat.php', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            if(!response.ok){ throw new Error('heartbeat failed'); }
            return response.json();
        }).then(function (payload) {
            if(payload && typeof payload.live_users !== 'undefined'){
                liveUsersNode.textContent = Number(payload.live_users).toLocaleString();
            }
        }).catch(function () {
            /* Keep the topbar quiet if the heartbeat cannot complete. */
        }).finally(function () {
            liveRunning = false;
        });
    }
    exdLiveHeartbeat();
    window.setInterval(exdLiveHeartbeat, 60000);

    /* ---------- Notification dropdown ---------- */
    var bell = document.getElementById('exdashBell');
    var bellMenu = document.getElementById('exdashBellMenu');
    if(bell && bellMenu){
        bell.addEventListener('click', function (e) {
            e.stopPropagation();
            bellMenu.classList.toggle('is-open');
        });
        document.addEventListener('click', function (e) {
            if(!bellMenu.contains(e.target)){ bellMenu.classList.remove('is-open'); }
        });
    }

    /* ---------- User dropdown ---------- */
    var userWrap = document.getElementById('exdashUser');
    var userBtn = document.getElementById('exdashUserBtn');
    var userMenu = document.getElementById('exdashUserMenu');
    function exdCloseUser(){ if(userWrap){ userWrap.classList.remove('is-open'); } if(userBtn){ userBtn.setAttribute('aria-expanded','false'); } }
    if(userBtn && userWrap){
        userBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = userWrap.classList.toggle('is-open');
            userBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if(!userWrap.contains(e.target)){ exdCloseUser(); }
        });
        document.addEventListener('keydown', function (e) { if(e.key === 'Escape'){ exdCloseUser(); } });
    }

    /* ---------- Global search (same endpoint as the classic dashboard) ---------- */
    var search = document.getElementById('exdashSearch');
    var searchResults = document.getElementById('exdashSearchResults');
    var searchTimer = null, searchReq = 0;
    function exdCloseSearch(){ searchReq++; if(searchTimer){ clearTimeout(searchTimer); } if(searchResults){ searchResults.setAttribute('hidden',''); searchResults.innerHTML=''; } }
    function exdRunSearch(force){
        if(!search || !searchResults){ return; }
        var q = search.value.trim();
        if(!q){ exdCloseSearch(); return; }
        if(q.length < 2 && !force){
            searchReq++;
            searchResults.innerHTML = "<div class='desktop-search-feedback'><i class='fa fa-search'></i><div><strong>Keep typing</strong><span>Use at least 2 characters to search students, staff, classes and tools.</span></div></div>";
            searchResults.removeAttribute('hidden');
            return;
        }
        searchResults.innerHTML = "<div class='desktop-search-feedback'><i class='fa fa-spinner fa-spin'></i><div><strong>Searching</strong><span>Checking students, staff, classes, batches, tools and modules.</span></div></div>";
        searchResults.removeAttribute('hidden');
        var id = ++searchReq;
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function () {
            if(xhr.readyState !== 4 || id !== searchReq){ return; }
            if(xhr.status === 200){
                searchResults.innerHTML = xhr.responseText;
                searchResults.removeAttribute('hidden');
            }else if(xhr.status === 403){
                searchResults.innerHTML = "<div class='desktop-search-feedback'><i class='fa fa-lock'></i><div><strong>Access denied</strong><span>You do not have access to desktop search.</span></div></div>";
            }else{
                searchResults.innerHTML = "<div class='desktop-search-feedback'><i class='fa fa-exclamation-circle'></i><div><strong>Search failed</strong><span>Try again in a moment.</span></div></div>";
            }
        };
        xhr.open('GET', 'admin-global-search.php?q=' + encodeURIComponent(q), true);
        xhr.send();
    }
    if(search && searchResults){
        search.addEventListener('input', function () {
            if(searchTimer){ clearTimeout(searchTimer); }
            searchTimer = setTimeout(function () { exdRunSearch(false); }, 220);
        });
        search.addEventListener('focus', function () {
            if(search.value.trim() !== ''){ exdRunSearch(false); }
        });
        search.addEventListener('keydown', function (e) {
            if(e.key === 'Escape'){ exdCloseSearch(); search.blur(); }
        });
        document.addEventListener('click', function (e) {
            if(!e.target.closest || !e.target.closest('.exdash-search')){ exdCloseSearch(); }
        });
    }

    /* ---------- Section tabs ---------- */
    var exdTabs = document.querySelectorAll('.exdash-tab');
    var exdPanes = document.querySelectorAll('.exdash-pane');
    exdTabs.forEach(function(tab){
        tab.addEventListener('click', function(){
            var key = tab.getAttribute('data-tab');
            exdTabs.forEach(function(t){
                var on = (t === tab);
                t.classList.toggle('active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            exdPanes.forEach(function(p){
                var show = (p.getAttribute('data-pane') === key);
                if(show){ p.removeAttribute('hidden'); } else { p.setAttribute('hidden', ''); }
                if(show && typeof Chart === 'function'){
                    p.querySelectorAll('canvas').forEach(function(cv){
                        var inst = Chart.getChart(cv);
                        if(inst){
                            var box = cv.parentElement.getBoundingClientRect();
                            inst.stop();
                            inst.resize(Math.max(1, Math.round(box.width)), Math.max(1, Math.round(box.height)));
                            inst.update('none');
                        }
                    });
                }
            });
        });
    });

    /* ---------- Module Deck: the classic dropdown menu ---------- */
    var exdeck = document.getElementById('exdashDeck');
    var exdeckScrim = document.getElementById('exdashDeckScrim');
    var exdeckOpenBtn = document.getElementById('exdashNavDeck');
    var exdeckCloseBtn = document.getElementById('exdashDeckClose');
    var EXDECK_KEY = 'exdash-deck-open';
    var exdFiltering = false;
    function exdSaveDeck(){
        if(!exdeck || exdFiltering){ return; }
        var open = [];
        exdeck.querySelectorAll('.exdeck-section[open]').forEach(function(s){ open.push(s.getAttribute('data-key')); });
        try{ localStorage.setItem(EXDECK_KEY, JSON.stringify(open)); }catch(err){}
    }
    function exdRestoreDeck(){
        if(!exdeck){ return; }
        var open = [];
        try{ open = JSON.parse(localStorage.getItem(EXDECK_KEY) || '[]'); }catch(err){}
        if(!Array.isArray(open)){ open = []; }
        exdeck.querySelectorAll('.exdeck-section').forEach(function(s){
            s.toggleAttribute('open', open.indexOf(s.getAttribute('data-key')) !== -1);
        });
    }
    function exdOpenDeck(){
        if(!exdeck){ return; }
        exdeck.classList.add('is-open');
        if(exdeckScrim){ exdeckScrim.classList.add('is-open'); }
        document.body.classList.add('exdash-lock');
        if(typeof exdeckSearch !== 'undefined' && exdeckSearch && window.innerWidth >= 1024){
            setTimeout(function(){ exdeckSearch.focus(); }, 240);
        }
    }
    function exdCloseDeck(){
        if(!exdeck){ return; }
        exdeck.classList.remove('is-open');
        if(exdeckScrim){ exdeckScrim.classList.remove('is-open'); }
        document.body.classList.toggle('exdash-lock', document.body.classList.contains('exdash-nav-open') && window.innerWidth < 1024);
        exdSaveDeck();
    }
    if(exdeck){
        exdRestoreDeck();
        exdeck.querySelectorAll('.exdeck-section').forEach(function(s){
            var n = s.querySelectorAll('.exdeck-body a').length;
            var c = s.querySelector('.exdeck-count');
            if(c){ c.textContent = n; }
        });
        /* Save open-section state when leaving the page (toggle events are async, so we avoid relying on them) */
        window.addEventListener('pagehide', exdSaveDeck);
    }
    if(exdeckOpenBtn){ exdeckOpenBtn.addEventListener('click', exdOpenDeck); }
    if(exdeckCloseBtn){ exdeckCloseBtn.addEventListener('click', exdCloseDeck); }
    if(exdeckScrim){ exdeckScrim.addEventListener('click', exdCloseDeck); }
    document.addEventListener('keydown', function(e){ if(e.key === 'Escape'){ exdCloseDeck(); } });

    /* Module Deck filter: type to narrow the 66 tools live */
    var exdeckSearch = document.getElementById('exdashDeckSearch');
    var exdeckSearchClear = document.getElementById('exdashDeckSearchClear');
    var exdeckEmpty = document.getElementById('exdashDeckEmpty');
    function exdFilterDeck(){
        if(!exdeck || !exdeckSearch){ return; }
        var q = exdeckSearch.value.trim().toLowerCase();
        exdFiltering = true;
        var anyMatch = false;
        exdeck.querySelectorAll('.exdeck-section').forEach(function(s){
            var visible = 0;
            var summaryHit = q !== '' && s.querySelector('summary').textContent.toLowerCase().indexOf(q) !== -1;
            s.querySelectorAll('.exdeck-body a').forEach(function(a){
                var hit = q === '' || summaryHit || a.textContent.toLowerCase().indexOf(q) !== -1;
                a.style.display = hit ? '' : 'none';
                if(hit){ visible++; }
            });
            var show = q === '' || visible > 0 || summaryHit;
            s.style.display = show ? '' : 'none';
            var c = s.querySelector('.exdeck-count');
            if(c){ c.textContent = q === '' ? s.querySelectorAll('.exdeck-body a').length : visible; }
            if(q === ''){
                s.removeAttribute('open');
            } else if(show){
                s.setAttribute('open', '');
            }
            if(show && q !== ''){ anyMatch = true; }
        });
        if(q === ''){ exdRestoreDeck(); }
        if(exdeckEmpty){ exdeckEmpty.classList.toggle('is-visible', q !== '' && !anyMatch); }
        if(exdeckSearchClear){ exdeckSearchClear.hidden = (q === ''); }
        exdFiltering = false;
    }
    if(exdeckSearch){
        exdeckSearch.addEventListener('input', exdFilterDeck);
        exdeckSearch.addEventListener('keydown', function(e){ if(e.key === 'Escape'){ exdeckSearch.value=''; exdFilterDeck(); } });
    }
    if(exdeckSearchClear){
        exdeckSearchClear.addEventListener('click', function(){
            exdeckSearch.value = '';
            exdFilterDeck();
            exdeckSearch.focus();
        });
    }

    /* Deep-link: performance filter params open the Analytics tab after reload */
    if(/[?&]perf_(class|batch|year|term)=/.test(location.search)){
        var analyticsTab = document.querySelector('.exdash-tab[data-tab="analytics"]');
        if(analyticsTab){ analyticsTab.click(); }
    }
    /* Deep-link: ?tab=notifications opens the Notifications tab */
    if(/[?&]tab=notifications/.test(location.search)){
        var notesTab = document.querySelector('.exdash-tab[data-tab="notifications"]');
        if(notesTab){ notesTab.click(); }
    }

    /* ---------- Charts ---------- */
    if(typeof Chart === 'undefined'){
        document.querySelectorAll('.exdash-chart-box').forEach(function(box){
            box.textContent = 'Charts could not load. Refresh the page or contact the administrator.';
        });
        return;
    }
    Chart.defaults.font.family = "'Plus Jakarta Sans','Segoe UI',-apple-system,BlinkMacSystemFont,Roboto,Arial,sans-serif";

    var trendCanvas = document.getElementById('exdashTrendChart');
    var trendChart = null;
    if(trendCanvas){
        var ctx = trendCanvas.getContext('2d');
        var fill = ctx.createLinearGradient(0, 0, 0, 260);
        fill.addColorStop(0, 'rgba(59,130,246,0.30)');
        fill.addColorStop(0.6, 'rgba(139,92,246,0.10)');
        fill.addColorStop(1, 'rgba(139,92,246,0.01)');
        trendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trendLabels); ?>,
                datasets: [{
                    label: 'New students',
                    data: <?php echo json_encode($trendValues); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: fill,
                    borderWidth: 3,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4.5,
                    pointHoverRadius: 6,
                    tension: 0.42,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#7c8ca0', font: { size: 11 } } },
                    y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.14)' }, ticks: { color: '#7c8ca0', precision: 0, font: { size: 11 } } }
                }
            }
        });
    }

    var attCanvas = document.getElementById('exdashAttChart');
    var attChart = null;
    if(attCanvas){
        attChart = new Chart(attCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late'<?php echo $attOther > 0 ? ", 'Other'" : ''; ?>],
                datasets: [{
                    data: [<?php echo (int)$attPresent; ?>, <?php echo (int)$attAbsent; ?>, <?php echo (int)$attLate; ?><?php echo $attOther > 0 ? ', ' . (int)$attOther : ''; ?>],
                    backgroundColor: ['#14b8a6', '#f87171', '#fbbf24'<?php echo $attOther > 0 ? ", '#94a3b8'" : ''; ?>],
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: { legend: { display: false } }
            }
        });
    }

    var popCanvas = document.getElementById('exdashPopChart');
    var popChart = null;
    if(popCanvas){
        popChart = new Chart(popCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($popLabels); ?>,
                datasets: [{
                    label: 'Students',
                    data: <?php echo json_encode($popValues); ?>,
                    backgroundColor: ['#3b82f6', '#60a5fa', '#14b8a6', '#2dd4bf', '#f59e0b'],
                    borderRadius: 7,
                    barThickness: 21
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context){
                                var total = <?php echo (int)array_sum($popValues); ?>;
                                var pct = total > 0 ? ((context.parsed.x / total) * 100).toFixed(1) : 0;
                                return context.parsed.x.toLocaleString() + ' students (' + pct + '%)';
                            }
                        }
                    }
                },
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.14)' }, ticks: { color: '#7c8ca0', precision: 0, font: { size: 11 } } },
                    y: { grid: { display: false }, ticks: { color: '#5b6b80', font: { size: 11, weight: '700' } } }
                }
            }
        });
    }

    var perfCanvas = document.getElementById('exdashPerfChart');
    var perfChart = null;
    if(perfCanvas){
        perfChart = new Chart(perfCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($perfLabels); ?>,
                datasets: [
                    { label: 'Average %', data: <?php echo json_encode($perfAvg); ?>, backgroundColor: 'rgba(20,184,166,0.8)', borderColor: '#0d9488', borderWidth: 1, borderRadius: 5 },
                    { label: 'Pass %', data: <?php echo json_encode($perfPass); ?>, backgroundColor: 'rgba(245,158,11,0.72)', borderColor: '#d97706', borderWidth: 1, borderRadius: 5 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { color: '#7c8ca0', boxWidth: 12, font: { size: 11 } } }
                },
                scales: {
                    x: { ticks: { color: '#7c8ca0', font: { size: 10 } }, grid: { display: false } },
                    y: { beginAtZero: true, suggestedMax: 100, ticks: { color: '#7c8ca0', font: { size: 11 } }, grid: { color: 'rgba(148,163,184,0.14)' } }
                }
            }
        });
    }

    /* Re-tint charts when the colour mode changes */
    var root = document.documentElement;
    function chartColours(){
        var dark = root.getAttribute('data-admin-theme') === 'dark';
        return dark
            ? { tick: '#a5b9d0', grid: 'rgba(148,163,184,0.14)' }
            : { tick: '#7c8ca0', grid: 'rgba(148,163,184,0.14)' };
    }
    function restyleCharts(){
        var c = chartColours();
        if(trendChart){
            trendChart.options.scales.x.ticks.color = c.tick;
            trendChart.options.scales.y.ticks.color = c.tick;
            trendChart.options.scales.y.grid.color = c.grid;
            trendChart.update();
        }
        if(popChart){
            popChart.options.scales.x.ticks.color = c.tick;
            popChart.options.scales.x.grid.color = c.grid;
            popChart.options.scales.y.ticks.color = c.tick;
            popChart.update();
        }
        if(perfChart){
            perfChart.options.scales.x.ticks.color = c.tick;
            perfChart.options.scales.y.ticks.color = c.tick;
            perfChart.options.scales.y.grid.color = c.grid;
            if(perfChart.options.plugins.legend.labels){ perfChart.options.plugins.legend.labels.color = c.tick; }
            perfChart.update();
        }
    }
    var observer = new MutationObserver(restyleCharts);
    observer.observe(root, { attributes: true, attributeFilter: ['data-admin-theme'] });
    restyleCharts();
})();
</script>
<?php /* Theme switcher JS only — the floating switcher is replaced by the
         Appearance section inside the user menu (buttons carry data-admin-theme-value). */ ?>
<script>
(function () {
    'use strict';
    var KEY = 'admin-colour-mode';
    var root = document.documentElement;
    function sync(v) {
        var btns = document.querySelectorAll('[data-admin-theme-value]');
        for (var i = 0; i < btns.length; i++) {
            btns[i].classList.toggle('is-active', btns[i].getAttribute('data-admin-theme-value') === v);
        }
    }
    function apply(v) {
        root.setAttribute('data-admin-theme', v);
        sync(v);
    }

    var saved = null;
    try { saved = localStorage.getItem(KEY); } catch (e) {}
    var validModes = { light: 1, dark: 1, classic: 1, ocean: 1, emerald: 1, royal: 1 };
    if (!validModes[saved]) { saved = 'light'; }
    apply(saved);

    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-admin-theme-value]') : null;
        if (!btn) { return; }
        var v = btn.getAttribute('data-admin-theme-value');
        try { localStorage.setItem(KEY, v); } catch (err) {}
        apply(v);
    });

})();
</script>
</body>
</html>
