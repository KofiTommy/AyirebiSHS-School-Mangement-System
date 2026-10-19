<?php
namespace LoginFastPathTest;
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
// Execute the real login handler with synthetic database/password functions only.
class RedirectComplete extends \Exception {}
function mysqli_prepare($con, $sql) { return true; }
function mysqli_stmt_bind_param(...$args) {}
function mysqli_stmt_execute($stmt) {}
function mysqli_stmt_close($stmt) {}
function mysqli_stmt_get_result($stmt) { return (object)['rows' => $GLOBALS['fixtureRows']]; }
function mysqli_fetch_array($result, $mode) { return array_shift($result->rows); }
function portal_verify_password($con, $row, $password) { return $password === $row['fixturePassword']; }
function header($value) { $GLOBALS['destination'] = $value; }
function mysqli_query($con, $sql) {
    $GLOBALS['queries'][] = $sql;
    if (strpos($sql, 'SELECT ad.auditdate') === 0) {
        return (object)['rows' => $GLOBALS['auditExists'] ? [['auditdate' => '2026-10-20']] : []];
    }
    if (strpos($sql, 'INSERT INTO tblauditdate') === 0) { $GLOBALS['auditExists'] = true; return true; }
    if (strpos($sql, 'UPDATE tblauditdate') === 0) { return true; }
    throw new \RuntimeException('Unexpected query in login path');
}
$source = file_get_contents(__DIR__.'/../index.php');
$start = strpos($source, 'if(isset($_POST["login"])){');
$end = strpos($source, '// Only prepare the public landing page');
if ($start === false || $end === false || $end <= $start) { throw new \RuntimeException('Fast path missing'); }
$handler = substr($source, $start, $end-$start);
if (preg_match('/\binclude(?:_once)?\s*\(/', $handler) || strpos($handler, 'SET GLOBAL') !== false) {
    throw new \RuntimeException('Login must not reconnect or run server maintenance');
}
preg_match_all('/^\s*header\("location:[^"]+"\);\s*exit\(\);/m', $handler, $redirects);
if (count($redirects[0]) !== 12) { throw new \RuntimeException('Every successful redirect must terminate'); }
foreach (['ensure_online_admission_tables($con)', 'ensure_user_management_columns($con)', 'ensure_portal_help_request_table($con)', '$_SQL_Stats='] as $work) {
    if (strpos($source, $work) < $end) { throw new \RuntimeException('Landing work still runs before login'); }
}
$handler = str_replace('exit();', 'throw new \\LoginFastPathTest\\RedirectComplete();', $handler);
$routes = [
    'super_user'=>'super.php', 'normal_user'=>'select-branch.php', 'Student'=>'student-page.php',
    'Teacher'=>'teacher-page.php', 'Headmaster'=>'headmaster-page.php',
    'AssistantHeadAcademic'=>'assistant-head-academics-page.php',
    'AssistantHeadAdministration'=>'assistant-head-administration-page.php',
    'Bursar'=>'bursar-dashboard.php', 'SafeguardingLead'=>'safeguarding-dashboard.php', 'User'=>'user.php'
];
$checks = 0;
foreach ($routes as $role=>$page) {
    foreach (['existing','new-day','wrong-password','blocked','reset','alumni','duplicate'] as $scenario) {
        $_POST = ['login'=>'1','username'=>'fixture','password'=>'correct','user'=>''];
        $_SESSION = ['Message'=>''];
        $row = ['userid'=>'fixture','username'=>'fixture','firstname'=>'Test','othernames'=>'','surname'=>'User',
            'accesslevel'=>in_array($role,['super_user','normal_user'],true)?'administrator':'user',
            'systemtype'=>$role,'branchid'=>'test','companyid'=>'test','status'=>'active',
            'audit_branch_initials'=>'TEST','password_reset_required'=>0,'fixturePassword'=>'correct'];
        if ($scenario === 'wrong-password') { $_POST['password']='wrong'; }
        if ($scenario === 'blocked') { $row['status']='block'; }
        if ($scenario === 'reset') { $row['password_reset_required']=1; }
        if ($scenario === 'alumni') { $row['status']='alumni'; }
        $GLOBALS['fixtureRows'] = [$row];
        if ($scenario === 'duplicate') { $wrong=$row; $wrong['fixturePassword']='different'; array_unshift($GLOBALS['fixtureRows'],$wrong); }
        $GLOBALS['auditExists'] = $scenario !== 'new-day';
        $GLOBALS['destination'] = null;
        $GLOBALS['queries'] = [];
        $con = true; $_DeviceInfo = 'synthetic'; $terminated = false;
        try { eval('namespace LoginFastPathTest; '.$handler); }
        catch (RedirectComplete $e) { $terminated = true; }
        $expected = in_array($scenario,['wrong-password','blocked'],true) ? null : 'location:'.($scenario==='reset'?'change-password.php?force=1':($scenario==='alumni'?'alumni-activate.php':$page));
        if ($GLOBALS['destination'] !== $expected || $terminated !== ($expected !== null)) {
            throw new \RuntimeException('Redirect mismatch: '.$role.' / '.$scenario);
        }
        if ($expected === null && $_SESSION['Message'] === '') { throw new \RuntimeException('Missing failure message'); }
        if ($expected !== null && ($_SESSION['USERID'] !== 'fixture' || $_SESSION['AUDITDATE'] === '')) { throw new \RuntimeException('Session data missing'); }
        $checks++;
    }
}
echo "PASS: $checks synthetic login scenarios; landing work deferred, all redirects terminate. No real accounts or database used.\n";
