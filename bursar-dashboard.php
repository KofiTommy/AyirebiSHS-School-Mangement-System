<?php
session_start();
include('dbstring.php');
include('check-login.php');
include('company.php');
include_once('user-management-utils.php');

if(!function_exists('um_is_bursar_user') || !um_is_bursar_user()){
    header('location:index.php');
    exit();
}

function bursar_safe($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function bursar_total($con, $sql, $field){
    $result = mysqli_query($con, $sql);
    $row = $result ? mysqli_fetch_array($result, MYSQLI_ASSOC) : null;
    return $row && isset($row[$field]) ? (float)$row[$field] : 0;
}

$currency = trim((string)($_SESSION['SYMBOL'] ?? 'GH₵'));
$studentCount = (int)bursar_total($con, "SELECT COUNT(*) AS total FROM tblsystemuser WHERE systemtype='Student' AND status='active'", 'total');
$billed = bursar_total($con, "SELECT COALESCE(SUM(cost),0) AS total FROM tblbilling", 'total');
$paid = bursar_total($con, "SELECT COALESCE(SUM(payment),0) AS total FROM tblpayment WHERE status='active'", 'total');
$outstanding = max(0, $billed - $paid);
$recent = mysqli_query($con, "SELECT pm.*, su.firstname, su.othernames, su.surname FROM tblpayment pm LEFT JOIN tblbilling bi ON bi.billid=pm.billid LEFT JOIN tblsystemuser su ON su.userid=bi.userid WHERE pm.status='active' ORDER BY pm.paymentdate DESC, pm.paymentid DESC LIMIT 8");
$name = trim((string)($_SESSION['FULLNAME'] ?? 'Bursar'));
?>
<!doctype html>
<html>
<head>
    <?php include('links.php'); ?>
    <link rel="stylesheet" href="css/bursar-dashboard.css?v=20260902">
    <title>Bursar Dashboard</title>
</head>
<body class="bursar-page">
    <div class="header"><?php include('menu.php'); ?></div>
    <main class="bursar-shell">
        <section class="bursar-hero">
            <div><span>Finance office</span><h1>Welcome, <?php echo bursar_safe($name); ?></h1><p>Record payments, issue accurate receipts, and keep school fee records clear.</p></div>
            <a href="payments.php" class="bursar-primary"><i class="fa fa-credit-card"></i> Record a payment</a>
        </section>
        <section class="bursar-stats">
            <article><i class="fa fa-users"></i><span>Active students</span><strong><?php echo number_format($studentCount); ?></strong></article>
            <article><i class="fa fa-file-text-o"></i><span>Total billed</span><strong><?php echo bursar_safe($currency); ?><?php echo number_format($billed, 2); ?></strong></article>
            <article><i class="fa fa-check-circle"></i><span>Payments received</span><strong><?php echo bursar_safe($currency); ?><?php echo number_format($paid, 2); ?></strong></article>
            <article><i class="fa fa-exclamation-circle"></i><span>Outstanding balance</span><strong><?php echo bursar_safe($currency); ?><?php echo number_format($outstanding, 2); ?></strong></article>
        </section>
        <section class="bursar-section"><div class="bursar-section-head"><div><span>Daily work</span><h2>Finance tools</h2></div><p>Fee setup remains under administrator control; payments and reporting are managed here.</p></div>
            <div class="bursar-actions">
                <a href="payments.php"><i class="fa fa-credit-card"></i><strong>Class payments</strong><small>Find a student, receive payment, and print a receipt.</small></a>
                <a href="account-statements.php"><i class="fa fa-list-alt"></i><strong>Student statements</strong><small>Review bills, payments, and balances per student.</small></a>
                <a href="payment-analysis.php"><i class="fa fa-line-chart"></i><strong>Payment analysis</strong><small>Print collections for a selected date range.</small></a>
                <a href="bills-report.php"><i class="fa fa-file-text-o"></i><strong>Billing report</strong><small>Review bills created across the school.</small></a>
            </div>
        </section>
        <section class="bursar-section bursar-table-panel"><div class="bursar-section-head"><div><span>Latest activity</span><h2>Recent payments</h2></div><a href="payments.php">Open payments <i class="fa fa-arrow-right"></i></a></div>
            <div class="bursar-table-wrap"><table><thead><tr><th>Student</th><th>Amount</th><th>Payment date</th></tr></thead><tbody>
            <?php if($recent && mysqli_num_rows($recent)>0){ while($row=mysqli_fetch_array($recent, MYSQLI_ASSOC)){ $student=trim(($row['firstname'] ?? '').' '.($row['othernames'] ?? '').' '.($row['surname'] ?? '')); ?>
                <tr><td><?php echo bursar_safe($student !== '' ? $student : 'Student record'); ?></td><td><?php echo bursar_safe($currency); ?><?php echo number_format((float)($row['payment'] ?? 0), 2); ?></td><td><?php echo bursar_safe(date('d M Y', strtotime((string)($row['paymentdate'] ?? 'now')))); ?></td></tr>
            <?php } }else{ ?><tr><td colspan="3" class="bursar-empty">No payment has been recorded yet.</td></tr><?php } ?>
            </tbody></table></div>
        </section>
    </main>
</body>
</html>
