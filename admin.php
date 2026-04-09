<?php
session_start();
?>

<html>
<head>
<?php
include("links.php");
?>

<style>
:root {
    --bg-1: #f3f4f6;
    --bg-2: #fffaf0;
    --ink: #1f2937;
    --muted: #64748b;
    --panel: #ffffff;
    --line: #e5e7eb;
    --brand: #0f766e;
    --accent: #b45309;
}

.body-style {
    margin: 0;
    color: var(--ink);
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    background: radial-gradient(circle at 0% 0%, #fef3c7 0%, transparent 24%),
                radial-gradient(circle at 100% 0%, #dbeafe 0%, transparent 28%),
                linear-gradient(180deg, var(--bg-2), var(--bg-1));
    overflow-x: hidden;
}

.header {
    background: rgba(255, 255, 255, 0.95);
    border-bottom: 1px solid var(--line);
    backdrop-filter: blur(8px);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    padding: 12px 20px;
}

.main-platform {
    max-width: 1500px;
    margin: 0 auto;
    padding: 6px 18px 8px;
    box-sizing: border-box;
    overflow-x: clip;
}

.main-platform > h2 {
    margin: 0 0 14px;
    padding: 16px 18px;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: linear-gradient(135deg, #0f766e 0%, #155e75 100%);
    color: #ecfeff;
    box-shadow: 0 14px 36px rgba(8, 47, 73, 0.22);
    text-align: left;
}

.admin-layout {
    width: 100%;
    border-collapse: separate;
    border-spacing: 12px 0;
    table-layout: fixed;
}

.admin-layout td {
    vertical-align: top;
    min-width: 0;
}

.admin-sidebar-col {
    width: 320px;
}

.admin-sidebar-scroll {
    position: sticky;
    top: 12px;
    max-height: calc(100vh - 118px);
    overflow-y: auto;
    padding: 10px;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    box-shadow: 0 12px 26px rgba(15, 23, 42, 0.08);
}

.admin-sidebar-scroll::-webkit-scrollbar {
    width: 8px;
}

.admin-sidebar-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 8px;
}

.admin-dashboard-col {
    width: auto;
}

.admin-dashboard-panel {
    min-height: calc(100vh - 190px);
    border-radius: 16px;
    border: 1px solid var(--line);
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
    min-width: 0;
}

.form-entry {
    border-radius: 14px;
    border: 1px solid var(--line);
    background: var(--panel);
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.05);
    padding: 14px;
    min-width: 0;
}

.dashboard-flex {
    display: grid;
    grid-template-columns: 340px minmax(0, 1fr);
    gap: 14px;
    margin: 0 0 16px;
    min-width: 0;
}

.chart-container {
    border: 1px solid var(--line);
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    padding: 14px;
    min-height: 320px;
    min-width: 0;
}

.cards-side {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.card {
    border: 1px solid var(--line);
    border-radius: 11px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    padding: 12px;
    min-height: 84px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.1);
}

.card h4 {
    margin: 0 0 7px;
    font-size: 0.82rem;
    font-weight: 700;
    color: #475569;
}

.card p {
    margin: 0;
    font-size: 1.45rem;
    font-weight: 800;
}

.card.total {
    background: linear-gradient(135deg, var(--brand), #0e7490);
    border-color: transparent;
}

.card.total h4,
.card.total p {
    color: #ecfeff;
}

.readiness-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
}

.readiness-copy {
    flex: 1 1 320px;
    min-width: 0;
}

.readiness-card p {
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.45;
    color: #334155;
}

.readiness-meta {
    margin-top: 8px;
    color: var(--muted);
    font-size: 0.82rem;
    font-weight: 600;
}

.readiness-side {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.readiness-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 120px;
    padding: 8px 12px;
    border-radius: 999px;
    border: 1px solid transparent;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.readiness-pill-ready {
    background: #dcfce7;
    border-color: #86efac;
    color: #166534;
}

.readiness-pill-not-ready {
    background: #fee2e2;
    border-color: #fca5a5;
    color: #991b1b;
}

.readiness-pill-neutral {
    background: #e2e8f0;
    border-color: #cbd5e1;
    color: #334155;
}

.readiness-pill-warning {
    background: #fef3c7;
    border-color: #fcd34d;
    color: #92400e;
}

.readiness-score {
    color: #0f172a;
    font-size: 0.88rem;
    font-weight: 700;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin: 10px 0 16px;
}

.quick-action-btn {
    text-decoration: none;
    border: 1px solid var(--line);
    background: #fff;
    color: var(--ink);
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 0.84rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s ease;
}

.quick-action-btn:hover {
    border-color: #86efac;
    background: #f0fdf4;
    transform: translateY(-1px);
}

.dashboard-shell {
    display: grid;
    grid-template-columns: 210px minmax(0, 1fr);
    gap: 12px;
    margin-bottom: 14px;
    min-width: 0;
}

.dashboard-side-menu {
    border: 1px solid var(--line);
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    padding: 10px;
    height: fit-content;
    min-width: 0;
}

.dash-side-btn {
    width: 100%;
    margin-bottom: 8px;
    text-align: left;
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 10px 11px;
    background: #fff;
    color: #0f172a;
    cursor: pointer;
    font-weight: 600;
}

.dash-side-btn.active {
    background: #ecfeff;
    border-color: #67e8f9;
}

.dashboard-main {
    min-width: 0;
}

.dashboard-top-menu {
    border: 1px solid var(--line);
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    padding: 10px;
    margin-bottom: 10px;
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 10px;
    min-width: 0;
}

.dash-top-btn {
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 9px 12px;
    background: #fff;
    color: #0f172a;
    cursor: pointer;
    font-weight: 700;
}

.dash-top-btn.active {
    background: #fef3c7;
    border-color: #f59e0b;
}

.dashboard-section {
    display: none;
}

.dashboard-section.active {
    display: block;
}

.perf-panel {
    border: 1px solid var(--line);
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    padding: 12px;
    margin-bottom: 14px;
    min-width: 0;
}

.perf-toolbar {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
    align-items: end;
    margin-bottom: 12px;
}

.perf-toolbar label {
    display: block;
    margin-bottom: 5px;
    color: #334155;
    font-weight: 600;
    font-size: 0.82rem;
}

.perf-toolbar select {
    width: 100%;
    padding: 9px 10px;
    border: 1px solid var(--line);
    border-radius: 9px;
    background: #fff;
}

.perf-grid {
    display: grid;
    grid-template-columns: minmax(320px, 1fr) minmax(0, 1.25fr);
    gap: 12px;
    min-width: 0;
}

.perf-chart-wrap {
    border: 1px solid var(--line);
    border-radius: 10px;
    background: #fff;
    padding: 10px;
    min-height: 290px;
    min-width: 0;
}

.perf-table-wrap {
    border: 1px solid var(--line);
    border-radius: 10px;
    background: #fff;
    padding: 10px;
    min-width: 0;
    overflow-x: visible;
}

.pending-list-wrap {
    border: 1px solid var(--line);
    border-radius: 10px;
    background: #fff;
    padding: 10px;
    margin-bottom: 12px;
}

.pending-list {
    margin: 0;
    padding-left: 18px;
    max-height: 170px;
    overflow-y: auto;
}

.pending-list li {
    margin: 0 0 7px;
    color: #334155;
    font-size: 0.85rem;
}

.table-wrap {
    min-width: 0;
    overflow-x: visible;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    padding: 12px;
}

.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
    table-layout: fixed;
}

.table caption {
    text-align: left;
    font-weight: 700;
    margin-bottom: 9px;
    color: #0f172a;
}

.table thead th {
    text-align: left;
    font-weight: 700;
    color: #334155;
    border-bottom: 1px solid var(--line);
    padding: 9px 10px;
    background: #f8fafc;
}

.table td {
    border-bottom: 1px solid #f1f5f9;
    padding: 10px;
    vertical-align: top;
    white-space: normal;
    overflow-wrap: anywhere;
    word-break: break-word;
}

.table tbody tr:hover {
    background: #f8fafc;
}

.notification-scroll {
    max-height: 340px;
    overflow-y: auto;
    overflow-x: visible;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    min-width: 0;
}

.notification-scroll thead th {
    position: sticky;
    top: 0;
    z-index: 2;
}

#myBtn {
    display: none;
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 44px;
    height: 44px;
    border-radius: 999px;
    border: 0;
    background: var(--accent);
    color: #fff;
    cursor: pointer;
    box-shadow: 0 8px 20px rgba(120, 53, 15, 0.35);
}

@media (max-width: 1200px) {
    .main-platform {
        padding-left: 14px;
        padding-right: 14px;
    }

    .quick-actions {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .cards-side {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .perf-grid {
        grid-template-columns: 1fr;
    }
    .perf-toolbar {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .dashboard-shell {
        grid-template-columns: 1fr;
    }
    .dashboard-top-menu {
        justify-content: flex-start;
    }

    .table thead th,
    .table td {
        padding: 9px 8px;
    }
}

@media (max-width: 980px) {
    .admin-layout,
    .admin-layout tbody,
    .admin-layout tr,
    .admin-layout td {
        display: block;
        width: 100%;
    }
    .admin-layout {
        border-spacing: 0;
    }
    .admin-sidebar-col {
        width: 100%;
    }
    .admin-sidebar-scroll {
        position: static;
        max-height: none;
        margin-bottom: 14px;
    }
    .admin-dashboard-panel {
        min-height: 0;
    }
    .dashboard-flex {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 620px) {
    .main-platform {
        padding-left: 10px;
        padding-right: 10px;
    }

    .quick-actions,
    .cards-side {
        grid-template-columns: 1fr;
    }
    .perf-toolbar {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body class="body-style">
    <div class="header">
        <?php
        include("menu.php");
        ?>
    </div>

    <div class="main-platform" align="center">
        <h2>Administrator Dashboard</h2>
        <table border="0" width="100%" class="admin-layout">
            <tr>
                <td width="25%" valign="top" class="admin-sidebar-col">
                    <div class="admin-sidebar-scroll">
                        <?php
                        include("welcome.php");
                        include("menuboard.php");
                        ?>
                    </div>
                </td>
                <td width="75%" valign="top" class="admin-dashboard-col">
                    <div class="form-entry admin-dashboard-panel">
                        <?php
                        include("dbstring.php");
                        include("audit_notifications.php");
                        ensureSystemChangeLogTable($con);
                        mysqli_query($con, "DELETE FROM tblsystemchangelog WHERE status='read' AND datetimeentry < (NOW() - INTERVAL 48 HOUR)");

                        if(isset($_POST['mark_changes_read'])){
                            mysqli_query($con, "UPDATE tblsystemchangelog SET status='read' WHERE status='unread' AND actor_type IN ('Teacher','Student')");
                            header("Location: admin.php#system-change-notifications");
                            exit();
                        }

                        /* 1) Query: counts by (gender x residence) */
                        $sql = "
                          SELECT
                            CASE
                              WHEN UPPER(su.gender) IN ('M','MALE','BOY','B') THEN 'Male'
                              WHEN UPPER(su.gender) IN ('F','FEMALE','GIRL','G') THEN 'Female'
                              ELSE 'Other'
                            END AS gnorm,
                            su.residencetype,
                            COUNT(DISTINCT su.userid) AS cnt
                          FROM tblsystemuser su
                          INNER JOIN tblclass cl ON cl.userid=su.userid
                          WHERE su.systemtype='Student'
                            AND su.status='active'
                            AND cl.status='active'
                            AND su.residencetype IN ('Day','Boarding')
                          GROUP BY gnorm, su.residencetype
                        ";

                        $res = mysqli_query($con, $sql);

                        /* 2) Seed defaults so missing combos show 0 */
                        $counts = [
                          'Male'   => ['Day' => 0, 'Boarding' => 0],
                          'Female' => ['Day' => 0, 'Boarding' => 0],
                        ];

                        while ($row = mysqli_fetch_assoc($res)) {
                          $g = $row['gnorm'];
                          $r = $row['residencetype'];
                          if (isset($counts[$g][$r])) {
                            $counts[$g][$r] = (int)$row['cnt'];
                          }
                        }

                        /* 3) Convenient vars + totals */
                        $boys_day        = $counts['Male']['Day'];
                        $boys_boarding   = $counts['Male']['Boarding'];
                        $girls_day       = $counts['Female']['Day'];
                        $girls_boarding  = $counts['Female']['Boarding'];

                        $boys_total      = $boys_day + $boys_boarding;
                        $girls_total     = $girls_day + $girls_boarding;
                        $day_total       = $boys_day + $girls_day;
                        $boarding_total  = $boys_boarding + $girls_boarding;
                        $grand_total     = $boys_total + $girls_total;

                        $activeBatchNames = array();
                        $_SQL_ACTIVE_BATCH = mysqli_query($con, "SELECT batch FROM tblbatch WHERE status='active' ORDER BY datetimeentry DESC");
                        if ($_SQL_ACTIVE_BATCH && mysqli_num_rows($_SQL_ACTIVE_BATCH) > 0) {
                            while ($row_ab = mysqli_fetch_array($_SQL_ACTIVE_BATCH, MYSQLI_ASSOC)) {
                                $activeBatchNames[] = $row_ab['batch'];
                            }
                        }
                        $activeBatchLabel = count($activeBatchNames) > 0 ? implode(", ", $activeBatchNames) : "No active semester";

                        $_ClassFilter = isset($_GET['perf_class']) ? trim($_GET['perf_class']) : '';
                        $_BatchFilter = isset($_GET['perf_batch']) ? trim($_GET['perf_batch']) : '';
                        $_TermFilter = isset($_GET['perf_term']) ? trim($_GET['perf_term']) : '';
                        $_ClassFilterSafe = mysqli_real_escape_string($con, $_ClassFilter);
                        $_BatchFilterSafe = mysqli_real_escape_string($con, $_BatchFilter);
                        $_TermFilterSafe = mysqli_real_escape_string($con, $_TermFilter);

                        $_ClassOptions = mysqli_query($con, "SELECT class_entryid, class_name FROM tblclassentry ORDER BY class_name ASC");
                        $_BatchOptions = mysqli_query($con, "SELECT batchid, batch FROM tblbatch ORDER BY datetimeentry DESC");
                        $_TermOptions = mysqli_query($con, "SELECT DISTINCT termname FROM tblsubjectassignment ORDER BY termname ASC");

                        $_PerfWhere = " WHERE mk.status='active' ";
                        if($_ClassFilterSafe !== ''){
                            $_PerfWhere .= " AND sa.classid='$_ClassFilterSafe' ";
                        }
                        if($_BatchFilterSafe !== ''){
                            $_PerfWhere .= " AND sa.batchid='$_BatchFilterSafe' ";
                        }
                        if($_TermFilterSafe !== ''){
                            $_PerfWhere .= " AND sa.termname='$_TermFilterSafe' ";
                        }

                        $_AssignWhere = " WHERE 1=1 ";
                        if($_ClassFilterSafe !== ''){
                            $_AssignWhere .= " AND sa.classid='$_ClassFilterSafe' ";
                        }
                        if($_BatchFilterSafe !== ''){
                            $_AssignWhere .= " AND sa.batchid='$_BatchFilterSafe' ";
                        }
                        if($_TermFilterSafe !== ''){
                            $_AssignWhere .= " AND sa.termname='$_TermFilterSafe' ";
                        }

                        $_TotalAssignedSubjects = 0;
                        $_SubmittedSubjects = 0;
                        $_PendingSubjects = 0;
                        $_SQL_TOTAL_ASSIGNED = mysqli_query($con, "SELECT COUNT(DISTINCT sa.assignmentid) AS total_assigned
                            FROM tblsubjectassignment sa
                            $_AssignWhere");
                        if($_SQL_TOTAL_ASSIGNED && $row_total_assigned = mysqli_fetch_array($_SQL_TOTAL_ASSIGNED, MYSQLI_ASSOC)){
                            $_TotalAssignedSubjects = (int)$row_total_assigned['total_assigned'];
                        }

                        $_SQL_SUBMITTED = mysqli_query($con, "SELECT COUNT(DISTINCT sa.assignmentid) AS submitted_total
                            FROM tblsubjectassignment sa
                            $_AssignWhere
                            AND EXISTS (
                                SELECT 1 FROM tblmark mk
                                WHERE mk.assignmentid=sa.assignmentid
                                  AND mk.status='active'
                            )");
                        if($_SQL_SUBMITTED && $row_submitted = mysqli_fetch_array($_SQL_SUBMITTED, MYSQLI_ASSOC)){
                            $_SubmittedSubjects = (int)$row_submitted['submitted_total'];
                        }
                        $_PendingSubjects = max(0, $_TotalAssignedSubjects - $_SubmittedSubjects);

                        $_PendingRows = array();
                        $_SQL_PENDING_LIST = mysqli_query($con, "SELECT
                                sa.assignmentid,
                                sa.termname,
                                ce.class_name,
                                bch.batch,
                                sub.subject,
                                CONCAT(su.firstname,' ',su.othernames,' ',su.surname,' (',su.userid,')') AS teacher_name
                            FROM tblsubjectassignment sa
                            INNER JOIN tblsubjectclassification sc ON sa.classificationid = sc.classificationid
                            INNER JOIN tblsubject sub ON sc.subjectid = sub.subjectid
                            INNER JOIN tblclassentry ce ON sa.classid = ce.class_entryid
                            INNER JOIN tblbatch bch ON sa.batchid = bch.batchid
                            INNER JOIN tblsystemuser su ON sa.userid = su.userid
                            $_AssignWhere
                            AND NOT EXISTS (
                                SELECT 1 FROM tblmark mk
                                WHERE mk.assignmentid=sa.assignmentid
                                  AND mk.status='active'
                            )
                            ORDER BY ce.class_name ASC, sa.termname ASC, sub.subject ASC
                            LIMIT 100");
                        if($_SQL_PENDING_LIST && mysqli_num_rows($_SQL_PENDING_LIST)>0){
                            while($row_pending = mysqli_fetch_array($_SQL_PENDING_LIST, MYSQLI_ASSOC)){
                                $_PendingRows[] = $row_pending;
                            }
                        }

                        $_HasReadinessScope = ($_ClassFilterSafe !== '' && $_BatchFilterSafe !== '' && $_TermFilterSafe !== '');
                        $_ReadinessStatusLabel = "Select Scope";
                        $_ReadinessPillClass = "readiness-pill-neutral";
                        $_ReadinessDetail = "Select class, batch, and semester to evaluate whether the full class result set is ready.";
                        $_ReadinessMeta = "This badge uses complete student-subject coverage, not just whether a subject has started receiving scores.";
                        $_ReadinessScore = "";
                        $_ReadinessCounts = array(
                            'expected_rows' => 0,
                            'complete_rows' => 0,
                            'missing_class_rows' => 0,
                            'missing_exam_rows' => 0,
                            'missing_both_rows' => 0,
                            'duplicate_rows' => 0
                        );

                        if($_HasReadinessScope){
                            $_ReadyClassName = $_ClassFilter;
                            $_ReadyBatchName = $_BatchFilter;

                            $_SQL_READY_CLASS = mysqli_query($con, "SELECT class_name FROM tblclassentry WHERE class_entryid='$_ClassFilterSafe' LIMIT 1");
                            if($_SQL_READY_CLASS && $row_ready_class = mysqli_fetch_array($_SQL_READY_CLASS, MYSQLI_ASSOC)){
                                $_ReadyClassName = $row_ready_class['class_name'];
                            }

                            $_SQL_READY_BATCH = mysqli_query($con, "SELECT batch FROM tblbatch WHERE batchid='$_BatchFilterSafe' LIMIT 1");
                            if($_SQL_READY_BATCH && $row_ready_batch = mysqli_fetch_array($_SQL_READY_BATCH, MYSQLI_ASSOC)){
                                $_ReadyBatchName = $row_ready_batch['batch'];
                            }

                            $_ReadinessMeta = trim($_ReadyClassName." / ".$_ReadyBatchName." / Semester ".$_TermFilter, " /");

                            $_SQL_CLASS_READY = mysqli_query($con, "SELECT
                                    COUNT(*) AS expected_rows,
                                    SUM(CASE WHEN ready.class_score_rows = 1 AND ready.exam_score_rows = 1 THEN 1 ELSE 0 END) AS complete_rows,
                                    SUM(CASE WHEN ready.class_score_rows = 0 AND ready.exam_score_rows > 0 THEN 1 ELSE 0 END) AS missing_class_rows,
                                    SUM(CASE WHEN ready.class_score_rows > 0 AND ready.exam_score_rows = 0 THEN 1 ELSE 0 END) AS missing_exam_rows,
                                    SUM(CASE WHEN ready.class_score_rows = 0 AND ready.exam_score_rows = 0 THEN 1 ELSE 0 END) AS missing_both_rows,
                                    SUM(CASE WHEN ready.class_score_rows > 1 OR ready.exam_score_rows > 1 THEN 1 ELSE 0 END) AS duplicate_rows
                                FROM (
                                    SELECT
                                        tr.userid,
                                        sc.subjectid,
                                        SUM(CASE WHEN mk.status='active' AND mk.testtype='Class Score' THEN 1 ELSE 0 END) AS class_score_rows,
                                        SUM(CASE WHEN mk.status='active' AND mk.testtype='Exam Score' THEN 1 ELSE 0 END) AS exam_score_rows
                                    FROM tbltermregistry tr
                                    INNER JOIN tblsystemuser stu ON stu.userid = tr.userid
                                    INNER JOIN tblsubjectassignment sa ON sa.classid = tr.class_entryid AND sa.batchid = tr.batchid AND sa.termname = tr.termname
                                    INNER JOIN tblsubjectclassification sc ON sa.classificationid = sc.classificationid
                                    LEFT JOIN tblmark mk ON mk.assignmentid = sa.assignmentid AND mk.userid = tr.userid
                                    WHERE tr.class_entryid='$_ClassFilterSafe'
                                      AND tr.batchid='$_BatchFilterSafe'
                                      AND tr.termname='$_TermFilterSafe'
                                      AND stu.systemtype='Student'
                                    GROUP BY tr.userid, sc.subjectid
                                ) ready");

                            if($_SQL_CLASS_READY && $row_ready = mysqli_fetch_array($_SQL_CLASS_READY, MYSQLI_ASSOC)){
                                $_ReadinessCounts['expected_rows'] = (int)$row_ready['expected_rows'];
                                $_ReadinessCounts['complete_rows'] = (int)$row_ready['complete_rows'];
                                $_ReadinessCounts['missing_class_rows'] = (int)$row_ready['missing_class_rows'];
                                $_ReadinessCounts['missing_exam_rows'] = (int)$row_ready['missing_exam_rows'];
                                $_ReadinessCounts['missing_both_rows'] = (int)$row_ready['missing_both_rows'];
                                $_ReadinessCounts['duplicate_rows'] = (int)$row_ready['duplicate_rows'];
                            }

                            $_ExpectedReadinessRows = $_ReadinessCounts['expected_rows'];
                            $_CompleteReadinessRows = $_ReadinessCounts['complete_rows'];
                            $_MissingClassRows = $_ReadinessCounts['missing_class_rows'];
                            $_MissingExamRows = $_ReadinessCounts['missing_exam_rows'];
                            $_MissingBothRows = $_ReadinessCounts['missing_both_rows'];
                            $_DuplicateReadinessRows = $_ReadinessCounts['duplicate_rows'];

                            if($_ExpectedReadinessRows <= 0){
                                $_ReadinessStatusLabel = "No Data";
                                $_ReadinessPillClass = "readiness-pill-warning";
                                $_ReadinessDetail = "No registered student-subject rows were found for this class scope yet, so readiness cannot be confirmed.";
                            } else {
                                $_ReadinessCompletionPct = round(($_CompleteReadinessRows * 100) / $_ExpectedReadinessRows, 2);
                                $_ReadinessScore = number_format($_CompleteReadinessRows)."/".number_format($_ExpectedReadinessRows)." Complete (".number_format($_ReadinessCompletionPct, 2)."%)";

                                if($_CompleteReadinessRows === $_ExpectedReadinessRows &&
                                   $_MissingClassRows === 0 &&
                                   $_MissingExamRows === 0 &&
                                   $_MissingBothRows === 0 &&
                                   $_DuplicateReadinessRows === 0){
                                    $_ReadinessStatusLabel = "Ready";
                                    $_ReadinessPillClass = "readiness-pill-ready";
                                    $_ReadinessDetail = "All expected entries for this class currently have one class score and one exam score.";
                                } else {
                                    $_ReadinessStatusLabel = "Not Ready";
                                    $_ReadinessPillClass = "readiness-pill-not-ready";
                                    $_ReadinessDetail = number_format($_CompleteReadinessRows)." of ".number_format($_ExpectedReadinessRows)." expected entries are complete. Missing Class: ".number_format($_MissingClassRows)." | Missing Exam: ".number_format($_MissingExamRows)." | Missing Both: ".number_format($_MissingBothRows)." | Duplicates: ".number_format($_DuplicateReadinessRows).".";
                                }
                            }
                        }

                        $_SQL_SUBJECT_PERF = mysqli_query($con, "SELECT
                                sub.subjectid,
                                sub.subject,
                                COUNT(*) AS entries_count,
                                ROUND(AVG(CASE WHEN mk.totalmark > 0 THEN (mk.mark / mk.totalmark) * 100 ELSE 0 END),2) AS avg_percent,
                                ROUND(AVG(CASE WHEN mk.totalmark > 0 AND ((mk.mark / mk.totalmark) * 100) >= 50 THEN 100 ELSE 0 END),2) AS pass_rate,
                                ROUND(AVG(CASE WHEN mk.totalmark > 0 AND ((mk.mark / mk.totalmark) * 100) >= 80 THEN 100 ELSE 0 END),2) AS excellence_rate
                            FROM tblmark mk
                            INNER JOIN tblsubjectassignment sa ON sa.assignmentid = mk.assignmentid
                            INNER JOIN tblsubjectclassification sc ON sa.classificationid = sc.classificationid
                            INNER JOIN tblsubject sub ON sc.subjectid = sub.subjectid
                            $_PerfWhere
                            GROUP BY sub.subjectid, sub.subject
                            ORDER BY avg_percent DESC, sub.subject ASC");

                        $_PerfLabels = array();
                        $_PerfAvg = array();
                        $_PerfPass = array();
                        $_PerfRows = array();
                        $_OverallAvg = 0;
                        $_OverallPass = 0;
                        $_TotalSubjects = 0;
                        $_BestSubject = "N/A";
                        $_BestSubjectScore = 0;

                        if($_SQL_SUBJECT_PERF && mysqli_num_rows($_SQL_SUBJECT_PERF)>0){
                            while($row_perf = mysqli_fetch_array($_SQL_SUBJECT_PERF, MYSQLI_ASSOC)){
                                $_PerfRows[] = $row_perf;
                                $_PerfLabels[] = $row_perf['subject'];
                                $_PerfAvg[] = (float)$row_perf['avg_percent'];
                                $_PerfPass[] = (float)$row_perf['pass_rate'];
                                $_OverallAvg += (float)$row_perf['avg_percent'];
                                $_OverallPass += (float)$row_perf['pass_rate'];
                            }
                            $_TotalSubjects = count($_PerfRows);
                            if($_TotalSubjects>0){
                                $_OverallAvg = round($_OverallAvg / $_TotalSubjects, 2);
                                $_OverallPass = round($_OverallPass / $_TotalSubjects, 2);
                                $_BestSubject = $_PerfRows[0]['subject'];
                                $_BestSubjectScore = (float)$_PerfRows[0]['avg_percent'];
                            }
                        }

                        $_UnreadChangeCount = 0;
                        $_SQL_UNREAD = mysqli_query($con, "SELECT COUNT(*) AS total_unread
                            FROM tblsystemchangelog
                            WHERE status='unread'
                              AND actor_type IN ('Teacher','Student')");
                        if($_SQL_UNREAD && $row_unread = mysqli_fetch_array($_SQL_UNREAD, MYSQLI_ASSOC)){
                            $_UnreadChangeCount = (int)$row_unread['total_unread'];
                        }
                        ?>

                        <div style="text-align:left;margin-bottom:8px;color:#374151;font-size:0.92rem;">
                            Active Semesters: <strong><?php echo $activeBatchLabel; ?></strong>
                        </div>
                        <div class="quick-actions" role="region" aria-label="Semester actions">
                            <a class="quick-action-btn" href="batch-entry.php"><i class="fa fa-plus"></i> Start New Semester</a>
                            <a class="quick-action-btn" href="term-registry.php"><i class="fa fa-plus"></i> Semester Registry</a>
                            <a class="quick-action-btn" href="promotion-center.php"><i class="fa fa-level-up"></i> Promote Students</a>
                            <a class="quick-action-btn" href="student-history.php"><i class="fa fa-history"></i> Student History</a>
                        </div>

                        <div class="dashboard-shell">
                            <div class="dashboard-side-menu" aria-label="Dashboard Sections">
                                <button type="button" class="dash-side-btn active" data-target="section-overview"><i class="fa fa-dashboard"></i> Overview</button>
                                <button type="button" class="dash-side-btn" data-target="section-notifications"><i class="fa fa-bell"></i> Notifications</button>
                            </div>
                            <div class="dashboard-main">
                                <div class="dashboard-top-menu" aria-label="Performance Menu">
                                    <button type="button" class="dash-top-btn" data-target="section-performance"><i class="fa fa-line-chart"></i> Subject Performance</button>
                                </div>

                        <?php
                        $_SQL_CHANGE_LOG = mysqli_query($con, "SELECT *
                            FROM tblsystemchangelog
                            WHERE actor_type IN ('Teacher','Student')
                            ORDER BY (CASE WHEN status='unread' THEN 0 ELSE 1 END), datetimeentry DESC
                            LIMIT 120");
                        ?>

                        <div class="dashboard-section" id="section-notifications">
                        <div class="table-wrap" role="region" aria-label="System Change Notifications" style="margin-bottom:14px;" id="system-change-notifications">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                                <div style="font-weight:600;color:#0f172a;">
                                    Unread Changes:
                                    <span style="display:inline-block;min-width:22px;padding:2px 8px;border-radius:999px;background:#b91c1c;color:#fff;font-size:12px;text-align:center;">
                                        <?php echo (int)$_UnreadChangeCount; ?>
                                    </span>
                                </div>
                                <form method="post" action="admin.php#system-change-notifications" style="margin:0;">
                                    <button type="submit" name="mark_changes_read" class="quick-action-btn" style="padding:8px 12px;">
                                        <i class="fa fa-check"></i> Mark All As Read
                                    </button>
                                </form>
                            </div>
                            <div class="notification-scroll">
                                <table class="table" style="margin:0;">
                                    <caption>System Change Notifications (Teachers and Students)</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Date/Time</th>
                                            <th scope="col">Actor</th>
                                            <th scope="col">Role</th>
                                            <th scope="col">Action</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($_SQL_CHANGE_LOG && mysqli_num_rows($_SQL_CHANGE_LOG) > 0) {
                                            while ($row_log = mysqli_fetch_array($_SQL_CHANGE_LOG, MYSQLI_ASSOC)) {
                                                echo "<tr>";
                                                echo "<td>".htmlspecialchars($row_log['datetimeentry'])."</td>";
                                                echo "<td>".htmlspecialchars($row_log['actor_name'])." (".htmlspecialchars($row_log['actor_userid']).")</td>";
                                                echo "<td>".htmlspecialchars($row_log['actor_type'])."</td>";
                                                echo "<td>".htmlspecialchars($row_log['action_type'])."</td>";
                                                echo "<td>".htmlspecialchars($row_log['status'])."</td>";
                                                echo "<td>".htmlspecialchars($row_log['details'])."</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='6' style='text-align:center;color:#64748b'>No change notifications yet.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        </div>

                        <!-- Chart.js CDN -->
                        <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>

                        <!-- Dashboard Section -->
                        <div class="dashboard-section active" id="section-overview">
                        <div class="dashboard-flex" role="region" aria-label="Student Distribution Dashboard">
                            <div class="chart-side">
                                <div class="chart-container">
                                    <canvas id="studentChart" width="280" height="280" aria-label="Student distribution by gender and residence"></canvas>
                                </div>
                            </div>
                            <div class="cards-side">
                                <div class="card" role="article" aria-label="Boys Day Students">
                                    <h4><i class="fa fa-male" style="color:#2563eb; margin-right:4px;"></i>Boys - Day</h4>
                                    <p><?php echo number_format($boys_day); ?></p>
                                </div>
                                <div class="card" role="article" aria-label="Boys Boarding Students">
                                    <h4><i class="fa fa-male" style="color:#38bdf8; margin-right:4px;"></i>Boys - Boarding</h4>
                                    <p><?php echo number_format($boys_boarding); ?></p>
                                </div>
                                <div class="card" role="article" aria-label="Girls Day Students">
                                    <h4><i class="fa fa-female" style="color:#db2777; margin-right:4px;"></i>Girls - Day</h4>
                                    <p><?php echo number_format($girls_day); ?></p>
                                </div>
                                <div class="card" role="article" aria-label="Girls Boarding Students">
                                    <h4><i class="fa fa-female" style="color:#f472b6; margin-right:4px;"></i>Girls - Boarding</h4>
                                    <p><?php echo number_format($girls_boarding); ?></p>
                                </div>
                                <div class="card total" role="article" aria-label="Total Students">
                                    <h4><i class="fa fa-users" style="color:#fff; margin-right:4px;"></i>Total Students</h4>
                                    <p><?php echo number_format($grand_total); ?></p>
                                </div>
                            </div>
                        </div>
                        </div>

                        <div class="dashboard-section" id="section-performance">
                        <div class="perf-panel" role="region" aria-label="Subject Performance" id="subject-performance-section">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                                <h3 style="margin:0;color:#0f172a;font-size:1rem;">Subject Performance Metrics</h3>
                                <div style="color:#475569;font-size:0.85rem;">Filter by class and batch</div>
                            </div>

                            <form method="get" action="admin.php" class="perf-toolbar">
                                <div>
                                    <label for="perf_class">Class</label>
                                    <select id="perf_class" name="perf_class">
                                        <option value="">All Classes</option>
                                        <?php
                                        if($_ClassOptions){
                                            while($row_c = mysqli_fetch_array($_ClassOptions, MYSQLI_ASSOC)){
                                                $selected = ($_ClassFilter === $row_c['class_entryid']) ? "selected" : "";
                                                echo "<option value='".htmlspecialchars($row_c['class_entryid'])."' $selected>".htmlspecialchars($row_c['class_name'])."</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="perf_batch">Batch</label>
                                    <select id="perf_batch" name="perf_batch">
                                        <option value="">All Batches</option>
                                        <?php
                                        if($_BatchOptions){
                                            while($row_b = mysqli_fetch_array($_BatchOptions, MYSQLI_ASSOC)){
                                                $selected = ($_BatchFilter === $row_b['batchid']) ? "selected" : "";
                                                echo "<option value='".htmlspecialchars($row_b['batchid'])."' $selected>".htmlspecialchars($row_b['batch'])."</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="perf_term">Semester</label>
                                    <select id="perf_term" name="perf_term">
                                        <option value="">All Semesters</option>
                                        <?php
                                        if($_TermOptions){
                                            while($row_t = mysqli_fetch_array($_TermOptions, MYSQLI_ASSOC)){
                                                $selected = ($_TermFilter === $row_t['termname']) ? "selected" : "";
                                                echo "<option value='".htmlspecialchars($row_t['termname'])."' $selected>".htmlspecialchars($row_t['termname'])."</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div>
                                    <label>&nbsp;</label>
                                    <button type="submit" class="quick-action-btn" style="width:100%;"><i class="fa fa-filter"></i> Apply Filter</button>
                                </div>
                                <div>
                                    <label>&nbsp;</label>
                                    <a href="admin.php" class="quick-action-btn" style="width:100%;"><i class="fa fa-undo"></i> Reset</a>
                                </div>
                            </form>

                            <div class="cards-side" style="margin-bottom:12px;">
                                <div class="card">
                                    <h4>Assigned Subjects</h4>
                                    <p><?php echo number_format($_TotalAssignedSubjects); ?></p>
                                </div>
                                <div class="card">
                                    <h4>Submitted Entries</h4>
                                    <p><?php echo number_format($_SubmittedSubjects); ?></p>
                                </div>
                                <div class="card">
                                    <h4>Pending Entries</h4>
                                    <p><?php echo number_format($_PendingSubjects); ?></p>
                                </div>
                                <div class="card">
                                    <h4>Average Score (%)</h4>
                                    <p><?php echo number_format($_OverallAvg, 2); ?></p>
                                </div>
                                <div class="card">
                                    <h4>Average Pass Rate (%)</h4>
                                    <p><?php echo number_format($_OverallPass, 2); ?></p>
                                </div>
                                <div class="card readiness-card" style="grid-column: span 3;">
                                    <div class="readiness-copy">
                                        <h4>Class Ready</h4>
                                        <p><?php echo htmlspecialchars($_ReadinessDetail); ?></p>
                                        <div class="readiness-meta"><?php echo htmlspecialchars($_ReadinessMeta); ?></div>
                                    </div>
                                    <div class="readiness-side">
                                        <span class="readiness-pill <?php echo $_ReadinessPillClass; ?>"><?php echo htmlspecialchars($_ReadinessStatusLabel); ?></span>
                                        <?php if($_ReadinessScore !== ''){ ?>
                                            <div class="readiness-score"><?php echo htmlspecialchars($_ReadinessScore); ?></div>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="card total" style="grid-column: span 3;">
                                    <h4>Top Subject</h4>
                                    <p style="font-size:1.1rem;"><?php echo htmlspecialchars($_BestSubject); ?> (<?php echo number_format($_BestSubjectScore,2); ?>%)</p>
                                </div>
                            </div>

                            <div class="pending-list-wrap">
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                                    <strong style="color:#0f172a;">Pending Subject List (No Score Entry Yet)</strong>
                                    <span style="font-size:0.82rem;color:#64748b;">Showing up to 100 items</span>
                                </div>
                                <?php if(count($_PendingRows)>0){ ?>
                                    <ol class="pending-list">
                                        <?php
                                        foreach($_PendingRows as $pendingRow){
                                            echo "<li>";
                                            echo "<strong>".htmlspecialchars($pendingRow['subject'])."</strong>";
                                            echo " | Class: ".htmlspecialchars($pendingRow['class_name']);
                                            echo " | Semester: ".htmlspecialchars($pendingRow['termname']);
                                            echo " | Batch: ".htmlspecialchars($pendingRow['batch']);
                                            echo " | Teacher: ".htmlspecialchars($pendingRow['teacher_name']);
                                            echo "</li>";
                                        }
                                        ?>
                                    </ol>
                                <?php } else { ?>
                                    <div style="color:#0f766e;font-size:0.9rem;">All assigned subjects have submitted score entries for this filter.</div>
                                <?php } ?>
                            </div>

                            <div class="perf-grid">
                                <div class="perf-chart-wrap">
                                    <canvas id="subjectPerformanceChart" height="260" aria-label="Subject performance chart"></canvas>
                                </div>
                                <div class="perf-table-wrap">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Entries</th>
                                                <th>Avg %</th>
                                                <th>Pass %</th>
                                                <th>Excellent %</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if($_TotalSubjects>0){
                                                foreach($_PerfRows as $prow){
                                                    echo "<tr>";
                                                    echo "<td>".htmlspecialchars($prow['subject'])."</td>";
                                                    echo "<td align='center'>".number_format($prow['entries_count'])."</td>";
                                                    echo "<td align='center'>".number_format((float)$prow['avg_percent'],2)."</td>";
                                                    echo "<td align='center'>".number_format((float)$prow['pass_rate'],2)."</td>";
                                                    echo "<td align='center'>".number_format((float)$prow['excellence_rate'],2)."</td>";
                                                    echo "</tr>";
                                                }
                                            } else {
                                                echo "<tr><td colspan='5' style='text-align:center;color:#64748b'>No score data found for selected filter.</td></tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        </div>
                            </div>
                        </div>

                        <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            function showDashboardSection(sectionId) {
                                const sections = document.querySelectorAll('.dashboard-section');
                                const sideBtns = document.querySelectorAll('.dash-side-btn');
                                const topBtns = document.querySelectorAll('.dash-top-btn');

                                sections.forEach(sec => sec.classList.remove('active'));
                                sideBtns.forEach(btn => btn.classList.remove('active'));
                                topBtns.forEach(btn => btn.classList.remove('active'));

                                const selectedSection = document.getElementById(sectionId);
                                if (selectedSection) {
                                    selectedSection.classList.add('active');
                                }

                                const selectedSide = document.querySelector('.dash-side-btn[data-target="' + sectionId + '"]');
                                const selectedTop = document.querySelector('.dash-top-btn[data-target="' + sectionId + '"]');
                                if (selectedSide) selectedSide.classList.add('active');
                                if (selectedTop) selectedTop.classList.add('active');
                            }

                            document.querySelectorAll('.dash-side-btn, .dash-top-btn').forEach(btn => {
                                btn.addEventListener('click', function() {
                                    const target = this.getAttribute('data-target');
                                    showDashboardSection(target);
                                });
                            });

                            const urlParams = new URLSearchParams(window.location.search);
                            if (window.location.hash === '#system-change-notifications') {
                                showDashboardSection('section-notifications');
                            } else if (
                                urlParams.get('perf_class') ||
                                urlParams.get('perf_batch') ||
                                urlParams.get('perf_term')
                            ) {
                                showDashboardSection('section-performance');
                            } else {
                                showDashboardSection('section-overview');
                            }

                            if (typeof Chart !== 'function') {
                                return;
                            }

                            const studentCanvas = document.getElementById('studentChart');
                            if (studentCanvas) {
                                const ctx = studentCanvas.getContext('2d');
                                new Chart(ctx, {
                                    type: 'doughnut',
                                    data: {
                                        labels: ['Boys Day', 'Boys Boarding', 'Girls Day', 'Girls Boarding'],
                                        datasets: [{
                                            label: 'Student Count',
                                            data: [<?php echo $boys_day; ?>, <?php echo $boys_boarding; ?>, <?php echo $girls_day; ?>, <?php echo $girls_boarding; ?>],
                                            backgroundColor: ['#2563eb', '#38bdf8', '#db2777', '#f472b6'],
                                            borderColor: '#fff',
                                            borderWidth: 2,
                                            hoverOffset: 16
                                        }]
                                    },
                                    options: {
                                        responsive: true, maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                position: 'bottom',
                                                labels: {
                                                    font: { size: 14 },
                                                    color: '#374151',
                                                    padding: 20,
                                                    boxWidth: 20
                                                }
                                            },
                                            title: {
                                                display: true,
                                                text: 'Student Distribution by Gender & Residence',
                                                font: { size: 16, weight: '600' },
                                                color: '#111827',
                                                padding: { top: 10, bottom: 20 }
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        let label = context.label || '';
                                                        let value = context.parsed || 0;
                                                        let total = <?php echo $grand_total; ?>;
                                                        let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                                        return `${label}: ${value.toLocaleString()} (${percentage}%)`;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                });
                            }

                            const perfCanvas = document.getElementById('subjectPerformanceChart');
                            if (perfCanvas) {
                                const perfLabels = <?php echo json_encode($_PerfLabels); ?>;
                                const perfAvg = <?php echo json_encode($_PerfAvg); ?>;
                                const perfPass = <?php echo json_encode($_PerfPass); ?>;
                                const perfCtx = perfCanvas.getContext('2d');

                                if (perfLabels.length > 0) {
                                    new Chart(perfCtx, {
                                        type: 'bar',
                                        data: {
                                            labels: perfLabels,
                                            datasets: [
                                                {
                                                    label: 'Average %',
                                                    data: perfAvg,
                                                    backgroundColor: 'rgba(14, 116, 144, 0.75)',
                                                    borderColor: '#0e7490',
                                                    borderWidth: 1
                                                },
                                                {
                                                    label: 'Pass %',
                                                    data: perfPass,
                                                    backgroundColor: 'rgba(180, 83, 9, 0.65)',
                                                    borderColor: '#b45309',
                                                    borderWidth: 1
                                                }
                                            ]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            plugins: {
                                                legend: { position: 'top' },
                                                title: {
                                                    display: true,
                                                    text: 'Average vs Pass Rate by Subject'
                                                }
                                            },
                                            scales: {
                                                y: {
                                                    beginAtZero: true,
                                                    suggestedMax: 100
                                                }
                                            }
                                        }
                                    });
                                } else {
                                    perfCtx.font = '14px Segoe UI';
                                    perfCtx.fillStyle = '#64748b';
                                    perfCtx.fillText('No performance data for selected filter.', 16, 40);
                                }
                            }
                        });
                        </script>

                    </div>
                </td>
            </tr>
        </table>

        <button onclick="topFunction()" id="myBtn" title="Go to top" aria-label="Scroll to top"><i class="fa fa-arrow-up"></i></button>
    </div>

    <script>
    // Go to Top Button
    const mybutton = document.getElementById('myBtn');
    window.onscroll = function() {
        if (document.body.scrollTop > 50 || document.documentElement.scrollTop > 50) {
            mybutton.style.display = 'block';
        } else {
            mybutton.style.display = 'none';
        }
    };

    function topFunction() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    </script>
</body>
</html>





