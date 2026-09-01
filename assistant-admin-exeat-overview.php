<?php
session_start();
include('dbstring.php');
include('check-login.php');
include_once('house-master-utils.php');
ensure_house_tables($con);

if(($_SESSION['ACCESSLEVEL'] ?? '') !== 'user' || ($_SESSION['SYSTEMTYPE'] ?? '') !== 'AssistantHeadAdministration'){
    header('location:index.php');
    exit();
}

function aeo_esc($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$branchId = trim((string)($_SESSION['BRANCHID'] ?? ''));
$branchWhere = '';
if($branchId !== ''){
    $branchWhere = " AND su.branchid='".mysqli_real_escape_string($con, $branchId)."'";
}
$expectedReturnSql = house_master_exeat_expected_return_sql('er');
$overdueSql = house_master_exeat_overdue_sql('er');
$summarySql = "SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN er.status='pending' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN er.status='approved' AND er.actualreturndatetime IS NULL THEN 1 ELSE 0 END) AS active_out,
    SUM(CASE WHEN $overdueSql THEN 1 ELSE 0 END) AS overdue,
    SUM(CASE WHEN er.actualreturndatetime >= CURDATE() AND er.actualreturndatetime < CURDATE() + INTERVAL 1 DAY THEN 1 ELSE 0 END) AS returned_today
    FROM tblexeatrequest er
    INNER JOIN tblsystemuser su ON su.userid=er.userid
    WHERE 1=1$branchWhere";
$summary = array('total'=>0,'pending'=>0,'active_out'=>0,'overdue'=>0,'returned_today'=>0);
$summaryResult = mysqli_query($con, $summarySql);
if($summaryResult && ($row = mysqli_fetch_assoc($summaryResult))){
    foreach($summary as $key => $value){ $summary[$key] = (int)($row[$key] ?? 0); }
}
$status = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : 'active';
if(!in_array($status, array('active','pending','overdue','all'), true)){ $status = 'active'; }
$statusWhere = '';
if($status === 'active'){
    $statusWhere = " AND er.status='approved' AND er.actualreturndatetime IS NULL";
}elseif($status === 'pending'){
    $statusWhere = " AND er.status='pending'";
}elseif($status === 'overdue'){
    $statusWhere = " AND $overdueSql";
}
$rows = mysqli_query($con, "SELECT er.*, h.housename, su.firstname, su.othernames, su.surname, $expectedReturnSql AS expected_return, CASE WHEN $overdueSql THEN 1 ELSE 0 END AS is_overdue
    FROM tblexeatrequest er
    INNER JOIN tblsystemuser su ON su.userid=er.userid
    LEFT JOIN tblhouse h ON h.houseid=er.houseid
    WHERE 1=1$branchWhere$statusWhere
    ORDER BY CASE WHEN $overdueSql THEN 0 WHEN er.status='pending' THEN 1 WHEN er.status='approved' AND er.actualreturndatetime IS NULL THEN 2 ELSE 3 END, er.requestedatetime DESC
    LIMIT 100");
?>
<!doctype html>
<html lang="en">
<head>
<?php include('title.php'); include('links.php'); ?>
<link rel="stylesheet" href="css/headmaster-dashboard.css">
<link rel="stylesheet" href="css/assistant-admin-exeat-overview.css">
</head>
<body class="hm-page aeo-page">
<div class="header"><?php include('menu.php'); ?></div>
<main class="hm-shell">
    <aside class="hm-sidebar"><div class="hm-sidebar__inner"><?php include('welcome.php'); include('menuboard.php'); ?></div></aside>
    <section class="hm-main">
        <section class="hm-hero hm-hero--single aeo-hero">
            <div class="hm-hero__copy">
                <span class="hm-kicker">Student welfare overview</span>
                <h1>Student exeat overview</h1>
                <p>Monitor approved movements, requests awaiting house review, overdue returns, and arrivals for the school. House staff remain responsible for approving and recording individual exeats.</p>
                <div class="hm-hero__footer"><div class="hm-context"><span><i class="fa fa-eye"></i> Administration oversight</span><span><i class="fa fa-calendar"></i> <?php echo date('l, d M Y'); ?></span></div><a class="aeo-back" href="assistant-head-administration-page.php"><i class="fa fa-arrow-left"></i> Administration dashboard</a></div>
            </div>
        </section>
        <section class="hm-section"><div class="aeo-stats">
            <a href="assistant-admin-exeat-overview.php?status=active" class="aeo-stat"><i class="fa fa-sign-out"></i><span>Currently out<strong><?php echo number_format($summary['active_out']); ?></strong></span></a>
            <a href="assistant-admin-exeat-overview.php?status=pending" class="aeo-stat"><i class="fa fa-clock-o"></i><span>Awaiting house review<strong><?php echo number_format($summary['pending']); ?></strong></span></a>
            <a href="assistant-admin-exeat-overview.php?status=overdue" class="aeo-stat aeo-stat--alert"><i class="fa fa-exclamation-triangle"></i><span>Overdue returns<strong><?php echo number_format($summary['overdue']); ?></strong></span></a>
            <a href="assistant-admin-exeat-overview.php?status=all" class="aeo-stat"><i class="fa fa-check-circle"></i><span>Returned today<strong><?php echo number_format($summary['returned_today']); ?></strong></span></a>
        </div></section>
        <section class="hm-panel aeo-panel">
            <div class="hm-panel__head"><div><span class="hm-section__eyebrow">Movement register</span><h2><?php echo ucfirst($status); ?> exeat records</h2><p>Showing up to 100 records. This page is an oversight register and does not change Housemaster decisions.</p></div><div class="aeo-filter"><a href="assistant-admin-exeat-overview.php?status=active" class="<?php echo $status==='active'?'is-active':''; ?>">Currently out</a><a href="assistant-admin-exeat-overview.php?status=pending" class="<?php echo $status==='pending'?'is-active':''; ?>">Pending</a><a href="assistant-admin-exeat-overview.php?status=overdue" class="<?php echo $status==='overdue'?'is-active':''; ?>">Overdue</a><a href="assistant-admin-exeat-overview.php?status=all" class="<?php echo $status==='all'?'is-active':''; ?>">All</a></div></div>
            <div class="aeo-table-wrap"><table><thead><tr><th>Student</th><th>House</th><th>Type</th><th>Out</th><th>Expected return</th><th>Status</th></tr></thead><tbody>
            <?php if($rows && mysqli_num_rows($rows)>0){ while($row=mysqli_fetch_assoc($rows)){ $name=trim(($row['firstname'] ?? '').' '.($row['othernames'] ?? '').' '.($row['surname'] ?? '')); $isOverdue=(int)$row['is_overdue']===1; $label=$isOverdue?'Overdue':ucfirst((string)$row['status']); if(trim((string)$row['actualreturndatetime'])!==''){$label='Returned';} ?>
                <tr><td><strong><?php echo aeo_esc($name !== '' ? $name : $row['userid']); ?></strong><small><?php echo aeo_esc($row['reason']); ?></small></td><td><?php echo aeo_esc($row['housename'] ?? '—'); ?></td><td><?php echo aeo_esc(ucfirst((string)($row['exeattype'] ?? 'External'))); ?></td><td><?php echo date('d M Y',strtotime($row['dateout'])).(trim((string)$row['timeout'])!==''?' · '.date('H:i',strtotime($row['timeout'])):''); ?></td><td><?php echo !empty($row['expected_return']) ? date('d M Y · H:i',strtotime($row['expected_return'])) : 'Not stated'; ?></td><td><span class="aeo-status aeo-status--<?php echo $isOverdue?'overdue':strtolower((string)$row['status']); ?>"><?php echo aeo_esc($label); ?></span></td></tr>
            <?php }}else{ ?><tr><td colspan="6" class="aeo-empty">No exeat records match this view.</td></tr><?php } ?>
            </tbody></table></div>
        </section>
    </section>
</main>
</body>
</html>