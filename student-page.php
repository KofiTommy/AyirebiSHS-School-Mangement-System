<?php
session_start();
include("dbstring.php");
include("check-login.php");
include("house-master-utils.php");
include("company.php");
ensure_house_tables($con);

if(!house_master_is_student()){
    header("location:".house_master_landing_page());
    exit();
}

function sd_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function sd_alert($type, $message){
    $class = "student-inline-alert student-inline-alert--info";
    if($type === "success"){
        $class = "student-inline-alert student-inline-alert--success";
    }elseif($type === "error"){
        $class = "student-inline-alert student-inline-alert--error";
    }elseif($type === "warning"){
        $class = "student-inline-alert student-inline-alert--warning";
    }
    return "<div class=\"$class\">".sd_esc($message)."</div>";
}

function sd_term($term){
    $term = trim((string)$term);
    return $term === "" ? "Semester" : "Semester ".$term;
}

function sd_date($value){
    $time = strtotime((string)$value);
    return $time ? date("d M Y, H:i", $time) : (string)$value;
}

function sd_money($amount){
    $symbol = (isset($_SESSION['SYMBOL']) && trim((string)$_SESSION['SYMBOL']) !== "") ? trim((string)$_SESSION['SYMBOL']) : "GHC";
    return $symbol." ".number_format((float)$amount, 2);
}

function sd_status_class($status){
    $status = strtolower(trim((string)$status));
    if($status === "approved" || $status === "returned"){
        return "student-status-pill student-status-pill--success";
    }
    if($status === "pending"){
        return "student-status-pill student-status-pill--warning";
    }
    if($status === "rejected" || $status === "declined" || $status === "denied"){
        return "student-status-pill student-status-pill--danger";
    }
    return "student-status-pill student-status-pill--neutral";
}

$studentId = isset($_SESSION['USERID']) ? (string)$_SESSION['USERID'] : "";
$studentIdEsc = mysqli_real_escape_string($con, $studentId);

if(isset($_POST['send_message'])){
    $message = trim((string)(isset($_POST['message']) ? $_POST['message'] : ""));
    if($message === ""){
        $_SESSION['Message'] = sd_alert("warning", "Please type a message before sending.");
    }else{
        include("code.php");
        $messageId = mysqli_real_escape_string($con, (string)$code);
        $messageEsc = mysqli_real_escape_string($con, $message);
        $messageAudienceEsc = mysqli_real_escape_string($con, um_message_default_audience_for_current_user());
        $_SQL = mysqli_query($con, "INSERT INTO tblmessages(messageid,messages,datetimeentry,status,sentby,recipient_group)
            VALUES('$messageId','$messageEsc',NOW(),'active','$studentIdEsc','$messageAudienceEsc')");
        $_SESSION['Message'] = $_SQL ? sd_alert("success", "Message successfully submitted.") : sd_alert("error", "Message failed to submit.");
    }
    header("location:student-page.php#student-messages");
    exit();
}

if(isset($_POST['delete_message'])){
    $messageId = trim((string)(isset($_POST['messageid']) ? $_POST['messageid'] : ""));
    if($messageId !== ""){
        $messageIdEsc = mysqli_real_escape_string($con, $messageId);
        $_SQL_D = mysqli_query($con, "DELETE FROM tblmessages WHERE messageid='$messageIdEsc' AND sentby='$studentIdEsc' LIMIT 1");
        $_SESSION['Message'] = ($_SQL_D && mysqli_affected_rows($con) > 0) ? sd_alert("success", "Message successfully deleted.") : sd_alert("error", "Message could not be deleted.");
    }
    header("location:student-page.php#student-messages");
    exit();
}

$flashMessage = isset($_SESSION['Message']) ? $_SESSION['Message'] : "";
$_SESSION['Message'] = "";
$portalTitle = trim((string)$_CompanyName) !== "" ? trim((string)$_CompanyName)." Student Portal" : "Student Portal";
$studentName = isset($_SESSION['FULLNAME']) ? trim((string)$_SESSION['FULLNAME']) : "";
$studentShortName = $studentName !== "" ? explode(" ", $studentName)[0] : "Student";
$studentBranch = "";
$studentFilename = "";

$profileRes = mysqli_query($con, "SELECT su.firstname,su.surname,su.othernames,su.filename,br.location
    FROM tblsystemuser su
    LEFT JOIN tblbranch br ON su.branchid=br.branchid
    WHERE su.userid='$studentIdEsc' LIMIT 1");
if($profileRes && $row = mysqli_fetch_array($profileRes, MYSQLI_ASSOC)){
    $full = trim($row['firstname']." ".$row['othernames']." ".$row['surname']);
    if($full !== ""){
        $studentName = $full;
        $studentShortName = explode(" ", $full)[0];
    }
    $studentBranch = trim((string)$row['location']);
    $studentFilename = trim((string)$row['filename']);
}

$studentImage = "uploads/comm.gif";
if($studentFilename !== "" && file_exists(__DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR.$studentFilename)){
    $studentImage = "uploads/".rawurlencode($studentFilename);
}

$houseInfo = get_student_active_house($con, $studentId);
$houseName = ($houseInfo && !empty($houseInfo['housename'])) ? trim((string)$houseInfo['housename']) : "Not Assigned";

$classLookup = array();
$classGroups = array();
$classRes = mysqli_query($con, "SELECT cl.class_entryid,cl.batchid,ce.class_name,bh.batch
    FROM tblclass cl
    INNER JOIN tblclassentry ce ON ce.class_entryid=cl.class_entryid
    LEFT JOIN tblbatch bh ON bh.batchid=cl.batchid
    WHERE cl.userid='$studentIdEsc'
    ORDER BY bh.datetimeentry DESC, ce.class_name ASC");
if($classRes){
    while($row = mysqli_fetch_array($classRes, MYSQLI_ASSOC)){
        $key = $row['batchid']."|".$row['class_entryid'];
        if(isset($classLookup[$key])){
            continue;
        }
        $classLookup[$key] = true;
        $classGroups[] = $row;
    }
}
$classCount = count($classGroups);

$reportOptionLookup = array();
$semesterLookup = array();
$reportOptions = array();
$termRes = mysqli_query($con, "SELECT tr.class_entryid,tr.batchid,tr.termname,ce.class_name,bh.batch
    FROM tbltermregistry tr
    INNER JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
    LEFT JOIN tblbatch bh ON bh.batchid=tr.batchid
    WHERE tr.userid='$studentIdEsc'
    ORDER BY bh.datetimeentry DESC, tr.termname DESC, ce.class_name ASC");
if($termRes){
    while($row = mysqli_fetch_array($termRes, MYSQLI_ASSOC)){
        $key = $row['batchid']."|".$row['class_entryid']."|".$row['termname'];
        if(isset($reportOptionLookup[$key])){
            continue;
        }
        $reportOptionLookup[$key] = true;
        $semesterLookup[$row['batchid']."|".$row['termname']] = true;
        $reportOptions[] = $row;
    }
}
$availableReportCount = count($reportOptions);
$semesterCount = count($semesterLookup);
$latestReportOption = $availableReportCount > 0 ? $reportOptions[0] : null;
$currentClassLabel = $classCount > 0 ? trim((string)$classGroups[0]['class_name']) : "";
$currentBatchLabel = $classCount > 0 ? trim((string)$classGroups[0]['batch']) : "";
if(($currentClassLabel === "" || $currentBatchLabel === "") && $latestReportOption){
    if($currentClassLabel === ""){
        $currentClassLabel = trim((string)$latestReportOption['class_name']);
    }
    if($currentBatchLabel === ""){
        $currentBatchLabel = trim((string)$latestReportOption['batch']);
    }
}
$currentSemesterLabel = $latestReportOption ? sd_term($latestReportOption['termname']) : "No semester selected";

$financeBilled = 0.0;
$financePaid = 0.0;
$latestPaymentDate = "";
$billedRes = mysqli_query($con, "SELECT COALESCE(SUM(ip.price),0) AS total_billed
    FROM tblbilling bi
    INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
    WHERE bi.userid='$studentIdEsc'");
if($billedRes && $row = mysqli_fetch_array($billedRes, MYSQLI_ASSOC)){
    $financeBilled = (float)$row['total_billed'];
}
$paidRes = mysqli_query($con, "SELECT COALESCE(SUM(pm.payment),0) AS total_paid, MAX(pm.datetimepayment) AS latest_payment
    FROM tblpayment pm
    INNER JOIN tblbilling bi ON bi.billid=pm.billid
    WHERE bi.userid='$studentIdEsc'");
if($paidRes && $row = mysqli_fetch_array($paidRes, MYSQLI_ASSOC)){
    $financePaid = (float)$row['total_paid'];
    $latestPaymentDate = trim((string)$row['latest_payment']);
}
$financeBalance = $financeBilled - $financePaid;

$exeatTotal = 0;
$exeatPending = 0;
$exeatApproved = 0;
$recentExeat = array();
$exeatStatsRes = mysqli_query($con, "SELECT COUNT(*) AS total_requests,
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_requests,
    SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved_requests
    FROM tblexeatrequest
    WHERE userid='$studentIdEsc'");
if($exeatStatsRes && $row = mysqli_fetch_array($exeatStatsRes, MYSQLI_ASSOC)){
    $exeatTotal = (int)$row['total_requests'];
    $exeatPending = (int)$row['pending_requests'];
    $exeatApproved = (int)$row['approved_requests'];
}
$exeatRes = mysqli_query($con, "SELECT er.exeattype,er.status,er.reason,er.dateout,er.timeout,er.datereturn,er.timereturn,er.decisionnote,er.requestedatetime,h.housename
    FROM tblexeatrequest er
    LEFT JOIN tblhouse h ON h.houseid=er.houseid
    WHERE er.userid='$studentIdEsc'
    ORDER BY er.requestedatetime DESC
    LIMIT 4");
if($exeatRes){
    while($row = mysqli_fetch_array($exeatRes, MYSQLI_ASSOC)){
        $recentExeat[] = $row;
    }
}

$myMessages = array();
$messageRes = mysqli_query($con, "SELECT messageid,messages,datetimeentry
    FROM tblmessages
    WHERE sentby='$studentIdEsc' AND status='active'
    ORDER BY datetimeentry DESC
    LIMIT 6");
if($messageRes){
    while($row = mysqli_fetch_array($messageRes, MYSQLI_ASSOC)){
        $myMessages[] = $row;
    }
}

$reportPreview = array_slice($reportOptions, 0, 6);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/student-dashboard.css">
</head>
<body class="student-dashboard-page">
<div class="header"><?php include("menu.php"); ?></div>
<main class="student-shell">
<?php if($flashMessage !== ""){ ?><div class="student-flash"><?php echo $flashMessage; ?></div><?php } ?>

<section class="student-hero">
    <div class="student-hero__copy">
        <span class="student-kicker"><?php echo sd_esc($portalTitle); ?></span>
        <h1>Welcome back, <?php echo sd_esc($studentShortName); ?>.</h1>
        <p>Track your classes, print reports faster, follow house and exeat updates, and keep up with your account and school messages in one cleaner workspace.</p>
        <div class="student-stat-grid">
            <article class="student-stat-card"><span>Registered Classes</span><strong><?php echo (int)$classCount; ?></strong></article>
            <article class="student-stat-card"><span>Report Options</span><strong><?php echo (int)$availableReportCount; ?></strong></article>
            <article class="student-stat-card"><span>Pending Exeat</span><strong><?php echo (int)$exeatPending; ?></strong></article>
            <article class="student-stat-card"><span>Balance</span><strong><?php echo sd_esc(sd_money($financeBalance)); ?></strong></article>
        </div>
    </div>
    <aside class="student-profile-card">
        <div class="student-profile-card__top">
            <img src="<?php echo sd_esc($studentImage); ?>" alt="<?php echo sd_esc($studentName !== "" ? $studentName : $studentId); ?>">
            <div>
                <span class="student-profile-card__eyebrow">Student Profile</span>
                <h2><?php echo sd_esc($studentName !== "" ? $studentName : $studentId); ?></h2>
                <p><?php echo sd_esc($studentBranch !== "" ? $studentBranch : "Branch not set"); ?></p>
            </div>
        </div>
        <div class="student-profile-meta">
            <div class="student-profile-meta__item"><span>Current Class</span><strong><?php echo sd_esc($currentClassLabel !== "" ? $currentClassLabel : "Not Yet Registered"); ?></strong></div>
            <div class="student-profile-meta__item"><span>Academic Year</span><strong><?php echo sd_esc($currentBatchLabel !== "" ? $currentBatchLabel : "Not Yet Registered"); ?></strong></div>
            <div class="student-profile-meta__item"><span>House</span><strong><?php echo sd_esc($houseName); ?></strong></div>
        </div>
        <div class="student-profile-actions">
            <a class="student-secondary-link" href="edit-account.php"><i class="fa fa-user"></i> Edit Profile</a>
            <a class="student-secondary-link" href="uploaduser-image.php"><i class="fa fa-arrow-circle-up"></i> Upload Image</a>
            <a class="student-secondary-link" href="change-password.php"><i class="fa fa-key"></i> Change Password</a>
        </div>
    </aside>
</section>

<section class="student-section">
    <div class="student-section__heading">
        <div><span class="student-section__eyebrow">Quick Actions</span><h2>Jump straight into your student tools</h2></div>
    </div>
    <div class="student-quick-grid">
        <a class="student-action-card" href="individual-terminal-report.php"><span class="student-action-card__icon"><i class="fa fa-book"></i></span><h3>Terminal Report</h3><p>Open the report page to review or print your semester report.</p></a>
        <a class="student-action-card" href="account-statements.php"><span class="student-action-card__icon"><i class="fa fa-money"></i></span><h3>Account Statement</h3><p>Check payments, balances, and your account history for each registered semester.</p></a>
        <a class="student-action-card" href="student-exeat-request.php"><span class="student-action-card__icon"><i class="fa fa-file"></i></span><h3>Request Exeat</h3><p>Submit a house exeat request and review past approvals or pending decisions.</p></a>
        <a class="student-action-card" href="examinationtimetablereport.php"><span class="student-action-card__icon"><i class="fa fa-calendar"></i></span><h3>Exam Timetable</h3><p>Check the latest examination schedule without browsing through old menus.</p></a>
        <a class="student-action-card" href="lesson-timetable-report.php"><span class="student-action-card__icon"><i class="fa fa-clock-o"></i></span><h3>Lesson Timetable</h3><p>Check your current lesson, what comes next, and your full class timetable for the week.</p></a>
        <a class="student-action-card" href="student-attendance-report.php"><span class="student-action-card__icon"><i class="fa fa-bar-chart"></i></span><h3>My Attendance</h3><p>See your attendance between any two dates, follow the graph, and print your summary when needed.</p></a>
        <a class="student-action-card" href="messages.php"><span class="student-action-card__icon"><i class="fa fa-comments"></i></span><h3>Message Board</h3><p>Open the full school message board when you want the complete conversation view.</p></a>
        <a class="student-action-card" href="edit-account.php"><span class="student-action-card__icon"><i class="fa fa-id-card"></i></span><h3>Profile Settings</h3><p>Update your account details so your school records stay current and accurate.</p></a>
        <a class="student-action-card" href="uploaduser-image.php"><span class="student-action-card__icon"><i class="fa fa-image"></i></span><h3>Profile Image</h3><p>Add or replace your student image so your portal profile looks complete.</p></a>
        <a class="student-action-card" href="logout.php"><span class="student-action-card__icon"><i class="fa fa-power-off"></i></span><h3>Sign Out</h3><p>Log out quickly after checking reports, payments, or other student activities.</p></a>
    </div>
</section>

<div class="student-layout">
    <section class="student-panel student-panel--wide">
        <div class="student-panel__header">
            <div><span class="student-panel__eyebrow">Academic Snapshot</span><h2>Report-ready semesters and current learning context</h2></div>
            <a class="student-panel__link" href="individual-terminal-report.php">Open Full Report Tool</a>
        </div>
        <div class="student-summary-grid">
            <article class="student-summary-card"><span>Current Class</span><strong><?php echo sd_esc($currentClassLabel !== "" ? $currentClassLabel : "Not Yet Registered"); ?></strong></article>
            <article class="student-summary-card"><span>Current Year</span><strong><?php echo sd_esc($currentBatchLabel !== "" ? $currentBatchLabel : "Not Yet Registered"); ?></strong></article>
            <article class="student-summary-card"><span>Latest Semester</span><strong><?php echo sd_esc($currentSemesterLabel); ?></strong></article>
            <article class="student-summary-card"><span>Available Semesters</span><strong><?php echo (int)$semesterCount; ?></strong></article>
        </div>

        <?php if(count($reportPreview) > 0){ ?>
        <div class="student-report-grid">
            <?php foreach($reportPreview as $report){ ?>
            <article class="student-report-card">
                <div class="student-report-card__meta">
                    <span class="student-status-pill student-status-pill--info"><?php echo sd_esc(sd_term($report['termname'])); ?></span>
                    <span class="student-report-card__year"><?php echo sd_esc($report['batch']); ?></span>
                </div>
                <h3><?php echo sd_esc($report['class_name']); ?></h3>
                <p>Use this saved registration to print your semester report directly from the dashboard.</p>
                <form method="post" action="individual-terminal-report.php" class="student-inline-form">
                    <input type="hidden" name="batchid" value="<?php echo sd_esc((string)$report['batchid']); ?>">
                    <input type="hidden" name="termid" value="<?php echo sd_esc((string)$report['termname']); ?>">
                    <input type="hidden" name="classid" value="<?php echo sd_esc((string)$report['class_entryid']); ?>">
                    <button class="student-inline-btn" type="submit" name="print_terminal_report"><i class="fa fa-print"></i> Print Report</button>
                </form>
            </article>
            <?php } ?>
        </div>
        <?php } else { ?>
        <div class="student-empty-state"><h3>No report options yet</h3><p>Your report-ready semester registrations will appear here as soon as your class and semester records are available.</p></div>
        <?php } ?>
    </section>

    <div class="student-panel-stack">
        <section class="student-panel">
            <div class="student-panel__header">
                <div><span class="student-panel__eyebrow">Registrations</span><h2>My classes and years</h2></div>
            </div>
            <?php if($classCount > 0){ ?>
            <div class="student-list-grid">
                <?php foreach($classGroups as $classRow){ ?>
                <article class="student-list-card">
                    <h3><?php echo sd_esc($classRow['class_name']); ?></h3>
                    <p><?php echo sd_esc($classRow['batch']); ?></p>
                </article>
                <?php } ?>
            </div>
            <?php } else { ?>
            <div class="student-empty-state student-empty-state--compact"><p>Your registered classes will show here after your school registration is completed.</p></div>
            <?php } ?>
        </section>

        <section class="student-panel">
            <div class="student-panel__header">
                <div><span class="student-panel__eyebrow">House And Exeat</span><h2>Current house and latest requests</h2></div>
                <a class="student-panel__link" href="student-exeat-request.php">Open Exeat Page</a>
            </div>
            <div class="student-house-card">
                <div class="student-house-card__row"><span>House</span><strong><?php echo sd_esc($houseName); ?></strong></div>
                <div class="student-house-card__row"><span>Total Requests</span><strong><?php echo (int)$exeatTotal; ?></strong></div>
                <div class="student-house-card__row"><span>Approved</span><strong><?php echo (int)$exeatApproved; ?></strong></div>
                <div class="student-house-card__row"><span>Pending</span><strong><?php echo (int)$exeatPending; ?></strong></div>
            </div>
            <?php if(count($recentExeat) > 0){ ?>
            <div class="student-list-grid student-list-grid--tight">
                <?php foreach($recentExeat as $exeat){ ?>
                <article class="student-list-card">
                    <div class="student-report-card__meta">
                        <span class="<?php echo sd_esc(sd_status_class($exeat['status'])); ?>"><?php echo sd_esc(ucfirst((string)$exeat['status'])); ?></span>
                        <span class="student-report-card__year"><?php echo sd_esc(ucfirst((string)$exeat['exeattype'])); ?></span>
                    </div>
                    <h3><?php echo sd_esc($exeat['housename'] !== "" ? $exeat['housename'] : $houseName); ?></h3>
                    <p><?php echo sd_esc($exeat['reason']); ?></p>
                    <small><?php echo sd_esc(trim((string)$exeat['dateout']." ".(string)$exeat['timeout'])); ?> to <?php echo sd_esc(trim((string)$exeat['datereturn']." ".(string)$exeat['timereturn'])); ?></small>
                </article>
                <?php } ?>
            </div>
            <?php } else { ?>
            <div class="student-empty-state student-empty-state--compact"><p>You have not submitted any exeat request yet.</p></div>
            <?php } ?>
        </section>

        <section class="student-panel">
            <div class="student-panel__header">
                <div><span class="student-panel__eyebrow">Finance Snapshot</span><h2>Account balance at a glance</h2></div>
                <a class="student-panel__link" href="account-statements.php">Open Account Statements</a>
            </div>
            <div class="student-finance-grid">
                <div class="student-finance-card"><span>Total Billed</span><strong><?php echo sd_esc(sd_money($financeBilled)); ?></strong></div>
                <div class="student-finance-card"><span>Total Paid</span><strong><?php echo sd_esc(sd_money($financePaid)); ?></strong></div>
                <div class="student-finance-card"><span>Outstanding</span><strong><?php echo sd_esc(sd_money($financeBalance)); ?></strong></div>
            </div>
            <div class="student-finance-note">
                <span>Latest Payment</span>
                <strong><?php echo sd_esc($latestPaymentDate !== "" ? sd_date($latestPaymentDate) : "No payment record yet"); ?></strong>
            </div>
        </section>
    </div>
</div>

<div class="student-layout student-layout--messages">
    <section class="student-panel" id="student-messages">
        <div class="student-panel__header">
            <div><span class="student-panel__eyebrow">Message Center</span><h2>Send and manage your messages</h2></div>
            <a class="student-panel__link" href="messages.php">Open Full Message Board</a>
        </div>
        <form method="post" action="student-page.php#student-messages" class="student-message-form">
            <label for="message">Write a message</label>
            <textarea id="message" name="message" placeholder="Share a concern, ask for support, or leave an update for the school team." required></textarea>
            <div class="student-message-form__actions">
                <span>Your messages here are sent into the wider school message feed.</span>
                <button class="student-primary-btn" type="submit" name="send_message"><i class="fa fa-send"></i> Send Message</button>
            </div>
        </form>

        <div class="student-message-list">
            <?php if(count($myMessages) > 0){ ?>
                <?php foreach($myMessages as $message){ ?>
                <article class="student-message-card">
                    <div class="student-message-card__meta">
                        <span><?php echo sd_esc(sd_date($message['datetimeentry'])); ?></span>
                        <form method="post" action="student-page.php#student-messages">
                            <input type="hidden" name="messageid" value="<?php echo sd_esc((string)$message['messageid']); ?>">
                            <button type="submit" name="delete_message" class="student-message-delete" onclick="return confirm('Delete this message?');"><i class="fa fa-trash"></i> Delete</button>
                        </form>
                    </div>
                    <p><?php echo nl2br(sd_esc($message['messages'])); ?></p>
                </article>
                <?php } ?>
            <?php } else { ?>
            <div class="student-empty-state student-empty-state--compact"><p>You have not posted any message yet.</p></div>
            <?php } ?>
        </div>
    </section>

    <section class="student-panel">
        <div class="student-panel__header">
            <div><span class="student-panel__eyebrow">Student Essentials</span><h2>What this page now does better</h2></div>
        </div>
        <div class="student-list-grid">
            <article class="student-list-card"><h3>Faster Report Access</h3><p>Your saved semester registrations can now be used to print reports directly from the dashboard.</p><small>Better academic UX</small></article>
            <article class="student-list-card"><h3>Stronger Visibility</h3><p>Your class, year, house, exeat counts, and finance snapshot are now visible at a glance.</p><small>More useful home page</small></article>
            <article class="student-list-card"><h3>Safer Message Actions</h3><p>Message deletion now works through your own session and only affects messages created by you.</p><small>Cleaner handling</small></article>
            <article class="student-list-card"><h3>Mobile Friendly Layout</h3><p>The page collapses into stacked cards and full-width actions so it stays easy to use on phones.</p><small>Responsive design</small></article>
        </div>
    </section>
</div>
</main>
</body>
</html>
