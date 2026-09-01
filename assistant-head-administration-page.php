<?php
session_start();
include('dbstring.php');
include('check-login.php');
include('company.php');
include_once('administration-ops-utils.php');
include_once('academic-plan-utils.php');
include_once('staff-permission-utils.php');

administration_ops_ensure_tables($con);
academic_plan_ensure_table($con);
staff_permission_ensure_table($con);

if(($_SESSION['ACCESSLEVEL'] ?? '') !== 'user' || ($_SESSION['SYSTEMTYPE'] ?? '') !== 'AssistantHeadAdministration'){
    header('location:index.php');
    exit();
}

function aha_admin_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if(isset($_POST['log_issue'])){
    $id = administration_ops_id();
    $title = trim((string)$_POST['title']);
    $location = trim((string)$_POST['location']);
    $priority = isset($_POST['priority']) && in_array($_POST['priority'], array('Low','Normal','High','Urgent'), true) ? $_POST['priority'] : 'Normal';
    $description = trim((string)$_POST['description']);

    if($title !== '' && $location !== ''){
        $stmt = mysqli_prepare($con, 'INSERT INTO tblmaintenanceissue(issueid,title,location,priority,description,status,reportedby) VALUES(?,?,?,?,?,?,?)');
        if($stmt){
            $status = 'open';
            $by = $_SESSION['USERID'];
            mysqli_stmt_bind_param($stmt, 'sssssss', $id, $title, $location, $priority, $description, $status, $by);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['aha_admin_message'] = 'The maintenance issue has been logged and added to the open-work register.';
        }
    }
    header('location:assistant-head-administration-page.php');
    exit();
}

$teachers = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) c FROM tblsystemuser WHERE systemtype='Teacher' AND status='active'"))['c'] ?? 0);
$students = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) c FROM tblsystemuser WHERE systemtype='Student' AND status='active'"))['c'] ?? 0);
$pendingStaff = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) c FROM tblstaffpermissionrequest WHERE status='pending'"))['c'] ?? 0);
$openIssues = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) c FROM tblmaintenanceissue WHERE status='open'"))['c'] ?? 0);
$issues = mysqli_query($con, "SELECT * FROM tblmaintenanceissue WHERE status='open' ORDER BY FIELD(priority,'Urgent','High','Normal','Low'),datetimeentry DESC LIMIT 6");
$calendar = academic_plan_rows($con, '', false, 'All School');
$studentDistribution = array('boys_day'=>0,'boys_boarding'=>0,'girls_day'=>0,'girls_boarding'=>0,'other'=>0);
$distributionSql = "SELECT
    CASE WHEN UPPER(TRIM(COALESCE(su.gender,''))) IN ('M','MALE','BOY','B') THEN 'boys'
         WHEN UPPER(TRIM(COALESCE(su.gender,''))) IN ('F','FEMALE','GIRL','G') THEN 'girls'
         ELSE 'other' END AS gender_group,
    CASE WHEN UPPER(TRIM(COALESCE(su.residencetype,''))) IN ('DAY','D') THEN 'day'
         WHEN UPPER(TRIM(COALESCE(su.residencetype,''))) IN ('BOARDING','BOARDER','B') THEN 'boarding'
         ELSE 'other' END AS residence_group,
    COUNT(DISTINCT su.userid) AS total
    FROM tblsystemuser su
    WHERE su.systemtype='Student' AND su.status='active'
    GROUP BY gender_group,residence_group";
$distributionResult = mysqli_query($con, $distributionSql);
if($distributionResult){
    while($distributionRow = mysqli_fetch_assoc($distributionResult)){
        $key = ($distributionRow['gender_group'] ?? 'other').'_'.($distributionRow['residence_group'] ?? 'other');
        if(isset($studentDistribution[$key])){ $studentDistribution[$key] = (int)$distributionRow['total']; }
        elseif(($distributionRow['gender_group'] ?? '') === 'other'){ $studentDistribution['other'] += (int)$distributionRow['total']; }
    }
}
$adminName = trim((string)($_SESSION['FULLNAME'] ?? 'Assistant Headmaster'));
$adminFirstName = trim((string)strtok($adminName, ' '));
if($adminFirstName === ''){ $adminFirstName = 'Assistant Headmaster'; }
$message = isset($_SESSION['aha_admin_message']) ? $_SESSION['aha_admin_message'] : '';
unset($_SESSION['aha_admin_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include('title.php'); include('links.php'); ?>
<link rel="stylesheet" href="css/headmaster-dashboard.css">
<link rel="stylesheet" href="css/assistant-head-administration.css">
</head>
<body class="hm-page aha-admin-page">
<div class="header"><?php include('menu.php'); ?></div>
<main class="hm-shell">
    <aside class="hm-sidebar">
        <div class="hm-sidebar__inner">
            <?php include('welcome.php'); include('menuboard.php'); ?>
        </div>
    </aside>

    <section class="hm-main">
        <section class="hm-hero hm-hero--single aha-admin-hero">
            <div class="hm-hero__copy">
                <span class="hm-kicker">Assistant Headmaster Administration</span>
                <h1>Administration command centre</h1>
                <p>Welcome, <?php echo aha_admin_esc($adminFirstName); ?>. Keep staff, facilities, welfare, duties, and school operations ready for every learning day.</p>
                <div class="hm-hero__footer">
                    <div class="hm-context">
                        <span><i class="fa fa-shield"></i> Operations oversight</span>
                        <span><i class="fa fa-calendar"></i> <?php echo date('l, d M Y'); ?></span>
                        <span><i class="fa fa-wrench"></i> <?php echo number_format($openIssues); ?> open issue<?php echo $openIssues === 1 ? '' : 's'; ?></span>
                    </div>
                    <div class="hm-live-clock-wrap">
                        <div class="xschool-live-clock hm-live-clock" data-live-clock>
                            <div class="xschool-live-clock__top">
                                <span class="xschool-live-clock__eyebrow">Live Date &amp; Time</span>
                                <span class="xschool-live-clock__status"><i class="fa fa-circle"></i> Live</span>
                            </div>
                            <div class="xschool-live-clock__time" data-live-clock-time>--:--:--</div>
                            <div class="xschool-live-clock__date" data-live-clock-date>Loading current date</div>
                            <div class="xschool-live-clock__zone" data-live-clock-zone>Local time</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php if($message !== ''){ ?>
        <div class="hm-inline-flash hm-inline-flash--success"><i class="fa fa-check-circle"></i> <?php echo aha_admin_esc($message); ?></div>
        <?php } ?>

        <section class="hm-section aha-admin-summary">
            <div class="hm-section__head">
                <div>
                    <span class="hm-section__eyebrow">Dashboard Summary</span>
                    <h2>School operations at a glance</h2>
                </div>
            </div>
            <div class="aha-admin-kpis" role="region" aria-label="Administration dashboard summary">
                <article class="aha-admin-kpi aha-admin-kpi--teal"><i class="fa fa-users"></i><div><span>Active teachers</span><strong><?php echo number_format($teachers); ?></strong><small>Staff available in the system</small></div></article>
                <article class="aha-admin-kpi aha-admin-kpi--blue"><i class="fa fa-graduation-cap"></i><div><span>Active students</span><strong><?php echo number_format($students); ?></strong><small>Students currently enrolled</small></div></article>
                <article class="aha-admin-kpi aha-admin-kpi--gold"><i class="fa fa-clock-o"></i><div><span>Staff permissions</span><strong><?php echo number_format($pendingStaff); ?></strong><small>Request<?php echo $pendingStaff === 1 ? '' : 's'; ?> awaiting review</small></div></article>
                <article class="aha-admin-kpi aha-admin-kpi--rose"><i class="fa fa-wrench"></i><div><span>Maintenance issues</span><strong><?php echo number_format($openIssues); ?></strong><small>Open item<?php echo $openIssues === 1 ? '' : 's'; ?> needing attention</small></div></article>
            </div>
        </section>

        <section class="hm-section aha-distribution-section">
            <div class="hm-section__head">
                <div>
                    <span class="hm-section__eyebrow">Student Distribution</span>
                    <h2>Student population across the school</h2>
                </div>
                <span class="aha-distribution-total"><?php echo number_format($students); ?> active students</span>
            </div>
            <div class="aha-distribution-grid">
                <article><i class="fa fa-male"></i><span>Boys · Day</span><strong><?php echo number_format($studentDistribution['boys_day']); ?></strong></article>
                <article><i class="fa fa-male"></i><span>Boys · Boarding</span><strong><?php echo number_format($studentDistribution['boys_boarding']); ?></strong></article>
                <article><i class="fa fa-female"></i><span>Girls · Day</span><strong><?php echo number_format($studentDistribution['girls_day']); ?></strong></article>
                <article><i class="fa fa-female"></i><span>Girls · Boarding</span><strong><?php echo number_format($studentDistribution['girls_boarding']); ?></strong></article>
            </div>
        </section>

        <section class="hm-section">
            <div class="hm-section__head">
                <div>
                    <span class="hm-section__eyebrow">Operations Desk</span>
                    <h2>Start with the work that needs you</h2>
                </div>
            </div>
            <div class="aha-admin-actions">
                <a href="staff-permission-review.php"><span class="aha-admin-action__icon"><i class="fa fa-user-plus"></i></span><span><strong>Staff permissions</strong><small>Review absence and permission requests.</small></span><i class="fa fa-arrow-right"></i></a>
                <a href="duty-roster.php"><span class="aha-admin-action__icon"><i class="fa fa-calendar-check-o"></i></span><span><strong>Duty roster</strong><small>Check school duty coverage and assignments.</small></span><i class="fa fa-arrow-right"></i></a>
                <a href="assistant-admin-exeat-overview.php"><span class="aha-admin-action__icon"><i class="fa fa-sign-out"></i></span><span><strong>Student exeats</strong><small>Monitor movement and welfare requests.</small></span><i class="fa fa-arrow-right"></i></a>
                <a href="academic-plan.php"><span class="aha-admin-action__icon"><i class="fa fa-map-signs"></i></span><span><strong>Plan academic calendar</strong><small>Create, update, and publish term activities.</small></span><i class="fa fa-arrow-right"></i></a>
                <a href="student-attendance-report.php"><span class="aha-admin-action__icon"><i class="fa fa-check-square-o"></i></span><span><strong>Attendance overview</strong><small>Monitor daily student attendance patterns.</small></span><i class="fa fa-arrow-right"></i></a>
                <a href="transport-management.php"><span class="aha-admin-action__icon"><i class="fa fa-bus"></i></span><span><strong>Transport operations</strong><small>Manage vehicles, routes, stops, and riders.</small></span><i class="fa fa-arrow-right"></i></a>
                <a href="messages.php"><span class="aha-admin-action__icon"><i class="fa fa-comments"></i></span><span><strong>School messages</strong><small>Keep communication with staff and students clear.</small></span><i class="fa fa-arrow-right"></i></a>                <a href="internal-exam-analysis.php"><span class="aha-admin-action__icon"><i class="fa fa-line-chart"></i></span><span><strong>Student performance</strong><small>Analyse marks, grades, trends, and subject results.</small></span><i class="fa fa-arrow-right"></i></a>                <a href="assistant-admin-welfare.php"><span class="aha-admin-action__icon"><i class="fa fa-heart"></i></span><span><strong>Student welfare</strong><small>Record concerns, support actions, and follow-ups.</small></span><i class="fa fa-arrow-right"></i></a>
                <a href="assistant-admin-attendance-watch.php"><span class="aha-admin-action__icon"><i class="fa fa-bell"></i></span><span><strong>Attendance watch</strong><small>Spot repeated absence and late-coming early.</small></span><i class="fa fa-arrow-right"></i></a>
                <a href="assistant-admin-maintenance.php"><span class="aha-admin-action__icon"><i class="fa fa-wrench"></i></span><span><strong>Maintenance work orders</strong><small>Assign issues, due dates, and completion updates.</small></span><i class="fa fa-arrow-right"></i></a>
                <a href="assistant-admin-staff-availability.php"><span class="aha-admin-action__icon"><i class="fa fa-user-check"></i></span><span><strong>Staff availability</strong><small>See daily duty coverage and absence alerts.</small></span><i class="fa fa-arrow-right"></i></a>
                <a href="viewstudents.php"><span class="aha-admin-action__icon"><i class="fa fa-graduation-cap"></i></span><span><strong>Student directory</strong><small>Search student records and print class lists.</small></span><i class="fa fa-arrow-right"></i></a>
                <a href="viewusers.php"><span class="aha-admin-action__icon"><i class="fa fa-users"></i></span><span><strong>Teacher directory</strong><small>Search staff records and print the teachers list.</small></span><i class="fa fa-arrow-right"></i></a>
            </div>
        </section>

        <section class="aha-admin-workspace">
            <section class="hm-panel aha-admin-panel">
                <div class="hm-panel__head"><div><span class="hm-section__eyebrow">Facilities</span><h2>Log a maintenance issue</h2><p>Record issues early so the school environment stays safe and ready.</p></div></div>
                <form method="post" class="aha-admin-form">
                    <label>Issue title<input name="title" required placeholder="e.g. Broken classroom fan"></label>
                    <label>Location / block<input name="location" required placeholder="e.g. Form 2 Block, Room 4"></label>
                    <label>Priority<select name="priority"><option>Normal</option><option>Low</option><option>High</option><option>Urgent</option></select></label>
                    <label>What needs attention?<textarea name="description" placeholder="Describe the issue and the required action"></textarea></label>
                    <button type="submit" name="log_issue" class="aha-admin-submit"><i class="fa fa-plus-circle"></i> Add to maintenance register</button>
                </form>
            </section>

            <section class="hm-panel aha-admin-panel">
                <div class="hm-panel__head"><div><span class="hm-section__eyebrow">Facilities Watch</span><h2>Open maintenance issues</h2><p>Prioritised items currently requiring operational attention.</p></div><span class="aha-admin-count"><?php echo number_format($openIssues); ?> open</span></div>
                <div class="aha-admin-list">
                <?php if($issues && mysqli_num_rows($issues) > 0){ while($issue = mysqli_fetch_assoc($issues)){ ?>
                    <article class="aha-admin-issue">
                        <span class="aha-admin-priority aha-admin-priority--<?php echo strtolower(aha_admin_esc($issue['priority'])); ?>"><?php echo aha_admin_esc($issue['priority']); ?></span>
                        <div><strong><?php echo aha_admin_esc($issue['title']); ?></strong><small><i class="fa fa-map-marker"></i> <?php echo aha_admin_esc($issue['location']); ?></small></div>
                    </article>
                <?php }}else{ ?>
                    <p class="aha-admin-empty"><i class="fa fa-check-circle"></i> No open maintenance issues. The register is clear.</p>
                <?php } ?>
                </div>
            </section>
        </section>

        <section class="hm-section">
            <div class="hm-panel aha-admin-panel aha-admin-calendar-panel">
                <div class="hm-panel__head"><div><span class="hm-section__eyebrow">Forward Plan</span><h2>This week and coming up</h2><p>Published school activities that can affect staffing, facilities, and daily operations.</p></div><a href="academic-plan-view.php">Open calendar <i class="fa fa-arrow-right"></i></a></div>
                <div class="aha-admin-calendar-list">
                <?php if(!empty($calendar)){ foreach(array_slice($calendar, 0, 6) as $event){ ?>
                    <article class="aha-admin-calendar"><time datetime="<?php echo aha_admin_esc($event['startdate']); ?>"><strong><?php echo date('d', strtotime($event['startdate'])); ?></strong><span><?php echo date('M', strtotime($event['startdate'])); ?></span></time><div><strong><?php echo aha_admin_esc($event['title']); ?></strong><small><?php echo (int)$event['weeknumber'] > 0 ? 'Week '.(int)$event['weeknumber'].' · ' : ''; ?><?php echo aha_admin_esc($event['eventtype']); ?></small></div><span class="aha-admin-calendar__date"><?php echo date('D', strtotime($event['startdate'])); ?></span></article>
                <?php }}else{ ?>
                    <p class="aha-admin-empty"><i class="fa fa-calendar-o"></i> No published activities are available yet.</p>
                <?php } ?>
                </div>
            </div>
        </section>
    </section>
</main>
</body>
</html>
