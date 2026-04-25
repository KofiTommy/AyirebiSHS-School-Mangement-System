<?php
session_start();
include("dbstring.php");
include("check-login.php");
include("class-teacher-utils.php");
include("duty-roster-utils.php");
include("student-attendance-utils.php");
ensure_class_teacher_table($con);
ensure_duty_roster_tables($con);
ensure_student_attendance_tables($con);
if(!(isset($_SESSION['ACCESSLEVEL'],$_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL']==="user" && $_SESSION['SYSTEMTYPE']==="Teacher")){
    header("location:".class_teacher_landing_page());
    exit();
}
function td_esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
function td_alert($type,$message){
    $class="teacher-inline-alert teacher-inline-alert--info";
    if($type==="success"){$class="teacher-inline-alert teacher-inline-alert--success";}
    elseif($type==="error"){$class="teacher-inline-alert teacher-inline-alert--error";}
    elseif($type==="warning"){$class="teacher-inline-alert teacher-inline-alert--warning";}
    return "<div class=\"$class\">".td_esc($message)."</div>";
}
function td_term($term){ $term=trim((string)$term); return $term==="" ? "Semester" : "Semester ".$term; }
function td_session_label($dateTimeValue,$batchLabel,$termValue){
    $yearValue = "";
    if(trim((string)$dateTimeValue) !== ""){
        $time = strtotime((string)$dateTimeValue);
        if($time){ $yearValue = date("Y",$time); }
    }
    if($yearValue === ""){ $yearValue = date("Y"); }
    return trim($yearValue." Batch ".trim((string)$batchLabel)." Semester ".trim((string)$termValue));
}
function td_date($value){ $time=strtotime((string)$value); return $time ? date("d M Y, H:i",$time) : (string)$value; }
$teacherId = isset($_SESSION['USERID']) ? (string)$_SESSION['USERID'] : "";
$teacherIdEsc = mysqli_real_escape_string($con, $teacherId);

if(isset($_POST['send_message'])){
    $message = trim((string)(isset($_POST['message']) ? $_POST['message'] : ""));
    if($message === ""){
        $_SESSION['Message'] = td_alert("warning","Please type a message before sending.");
    } else {
        include("code.php");
        $messageId = mysqli_real_escape_string($con, (string)$code);
        $messageEsc = mysqli_real_escape_string($con, $message);
        $messageAudienceEsc = mysqli_real_escape_string($con, um_message_default_audience_for_current_user());
        $_SQL = mysqli_query($con,"INSERT INTO tblmessages(messageid,messages,datetimeentry,status,sentby,recipient_group)
            VALUES('$messageId','$messageEsc',NOW(),'active','$teacherIdEsc','$messageAudienceEsc')");
        if($_SQL){
            engagement_track_daily_action($con, 'teacher_message_sent_daily', $teacherId);
        }
        $_SESSION['Message'] = $_SQL ? td_alert("success","Message successfully submitted.") : td_alert("error","Message failed to submit.");
    }
    header("location:teacher-page.php#teacher-messages");
    exit();
}
if(isset($_POST["delete_message"])){
    $messageId = trim((string)(isset($_POST["messageid"]) ? $_POST["messageid"] : ""));
    if($messageId !== ""){
        $messageIdEsc = mysqli_real_escape_string($con, $messageId);
        $_SQL_D = mysqli_query($con,"DELETE FROM tblmessages WHERE messageid='$messageIdEsc' AND sentby='$teacherIdEsc' LIMIT 1");
        $_SESSION['Message'] = ($_SQL_D && mysqli_affected_rows($con)>0) ? td_alert("success","Message successfully deleted.") : td_alert("error","Message could not be deleted.");
    }
    header("location:teacher-page.php#teacher-messages");
    exit();
}

$flashMessage = isset($_SESSION['Message']) ? $_SESSION['Message'] : "";
$_SESSION['Message'] = "";
$teacherName = isset($_SESSION['FULLNAME']) ? trim((string)$_SESSION['FULLNAME']) : "";
$teacherShortName = $teacherName !== "" ? explode(" ", $teacherName)[0] : "Teacher";
$teacherBranch = "";
$teacherFilename = "";
$teacherProfileRes = mysqli_query($con,"SELECT su.firstname,su.surname,su.othernames,su.filename,br.location
    FROM tblsystemuser su LEFT JOIN tblbranch br ON su.branchid=br.branchid
    WHERE su.userid='$teacherIdEsc' LIMIT 1");
if($teacherProfileRes && $row=mysqli_fetch_array($teacherProfileRes,MYSQLI_ASSOC)){
    $full = trim($row['firstname']." ".$row['othernames']." ".$row['surname']);
    if($full !== ""){ $teacherName = $full; $teacherShortName = explode(" ", $full)[0]; }
    $teacherBranch = trim((string)$row['location']);
    $teacherFilename = trim((string)$row['filename']);
}
$teacherImage = "uploads/comm.gif";
if($teacherFilename !== "" && file_exists(__DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR.$teacherFilename)){
    $teacherImage = "uploads/".rawurlencode($teacherFilename);
}
$dutyDashboard = duty_roster_get_teacher_dashboard_context($con, $teacherId);
$attendanceSummary = student_attendance_teacher_dashboard_summary($con, $teacherId);

$classTeacherRoles = array();
$classTeacherLookup = array();
$classTeacherRes = mysqli_query($con,"SELECT ct.classid,ct.batchid,ct.termname,ct.datetimeentry AS role_datetimeentry,ce.class_name,bh.batch
    FROM tblclassteacher ct
    INNER JOIN tblclassentry ce ON ce.class_entryid=ct.classid
    INNER JOIN tblbatch bh ON bh.batchid=ct.batchid
    WHERE ct.userid='$teacherIdEsc' AND ct.status='active'
    ORDER BY ct.datetimeentry DESC,ct.termname DESC,ce.class_name ASC");
if($classTeacherRes){
    while($row=mysqli_fetch_array($classTeacherRes,MYSQLI_ASSOC)){
        $key = $row["batchid"]."|".$row["classid"]."|".$row["termname"];
        $classTeacherLookup[$key] = true;
        $row["session_label"] = td_session_label($row["role_datetimeentry"], $row["batch"], $row["termname"]);
        $classTeacherRoles[] = $row;
    }
}

$assignmentGroups = array();
$assignedSubjectCount = 0;
$activeBatchIds = array();
$recentTeachingGroupLimit = 6;
$assignmentRes = mysqli_query($con,"SELECT sa.classid,sa.batchid,sa.termname,sa.datetimeentry AS assignment_datetimeentry,ce.class_name,bh.batch,sub.subject
    FROM tblsubjectassignment sa
    INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
    INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
    INNER JOIN tblclassentry ce ON ce.class_entryid=sa.classid
    INNER JOIN tblbatch bh ON bh.batchid=sa.batchid
    WHERE sa.userid='$teacherIdEsc' AND sa.status='active' AND bh.status='active'
    ORDER BY bh.datetimeentry DESC,sa.termname DESC,ce.class_name ASC,sub.subject ASC");
if($assignmentRes){
    while($row=mysqli_fetch_array($assignmentRes,MYSQLI_ASSOC)){
        $assignedSubjectCount++;
        $activeBatchIds[$row["batchid"]] = true;
        $key = $row["batchid"]."|".$row["classid"]."|".$row["termname"];
        if(!isset($assignmentGroups[$key])){
            $assignmentGroups[$key] = array(
                "class_name"=>$row["class_name"],
                "batch"=>$row["batch"],
                "termname"=>$row["termname"],
                "session_label"=>td_session_label($row["assignment_datetimeentry"], $row["batch"], $row["termname"]),
                "sort_timestamp"=>(strtotime((string)$row["assignment_datetimeentry"]) ?: 0),
                "subjects"=>array(),
                "is_class_teacher"=>isset($classTeacherLookup[$key])
            );
        } else {
            $currentSortTime = (strtotime((string)$row["assignment_datetimeentry"]) ?: 0);
            if($currentSortTime > (int)$assignmentGroups[$key]["sort_timestamp"]){
                $assignmentGroups[$key]["sort_timestamp"] = $currentSortTime;
                $assignmentGroups[$key]["session_label"] = td_session_label($row["assignment_datetimeentry"], $row["batch"], $row["termname"]);
            }
        }
        $assignmentGroups[$key]["subjects"][] = $row["subject"];
    }
}
$teachingGroups = array_values($assignmentGroups);
usort($teachingGroups, function($left, $right){
    $leftSort = isset($left["sort_timestamp"]) ? (int)$left["sort_timestamp"] : 0;
    $rightSort = isset($right["sort_timestamp"]) ? (int)$right["sort_timestamp"] : 0;
    if($leftSort === $rightSort){
        return strcmp((string)$left["class_name"], (string)$right["class_name"]);
    }
    return ($rightSort <=> $leftSort);
});
$recentTeachingGroups = array_slice($teachingGroups, 0, $recentTeachingGroupLimit);
$teachingGroupCount = count($teachingGroups);
$recentTeachingGroupCount = count($recentTeachingGroups);
$classTeacherRoleCount = count($classTeacherRoles);
$teacherCanTakeAttendance = ($classTeacherRoleCount > 0);
$activeBatchCount = count($activeBatchIds);
$myMessageCount = 0;
$countRes = mysqli_query($con,"SELECT COUNT(*) AS total_messages FROM tblmessages WHERE sentby='$teacherIdEsc' AND status='active'");
if($countRes && $countRow=mysqli_fetch_array($countRes,MYSQLI_ASSOC)){ $myMessageCount = (int)$countRow["total_messages"]; }
$myMessages = array();
$myMessagesRes = mysqli_query($con,"SELECT messageid,messages,datetimeentry FROM tblmessages
    WHERE sentby='$teacherIdEsc' AND status='active' ORDER BY datetimeentry DESC LIMIT 6");
if($myMessagesRes){ while($row=mysqli_fetch_array($myMessagesRes,MYSQLI_ASSOC)){ $myMessages[] = $row; } }
$engagementSummary = engagement_get_summary($con, $teacherId);
$engagementRecent = engagement_get_recent_activity($con, $teacherId, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/teacher-dashboard.css">
</head>
<body class="teacher-dashboard-page">
<div class="header"><?php include("menu.php"); ?></div>
<main class="teacher-shell">
<?php if($flashMessage !== ""){ ?><div class="teacher-flash"><?php echo $flashMessage; ?></div><?php } ?>

<section class="teacher-hero">
    <div class="teacher-hero__copy">
        <span class="teacher-kicker">Teacher Workspace</span>
        <h1>Welcome back, <?php echo td_esc($teacherShortName); ?>.</h1>
        <div class="teacher-stat-grid">
            <article class="teacher-stat-card"><span>Assigned Subjects</span><strong><?php echo (int)$assignedSubjectCount; ?></strong></article>
            <article class="teacher-stat-card"><span>Recent Groups</span><strong><?php echo (int)$recentTeachingGroupCount; ?></strong></article>
            <article class="teacher-stat-card"><span>Class Teacher Roles</span><strong><?php echo (int)$classTeacherRoleCount; ?></strong></article>
            <article class="teacher-stat-card"><span>My Messages</span><strong><?php echo (int)$myMessageCount; ?></strong></article>
            <?php if($teacherCanTakeAttendance){ ?>
            <article class="teacher-stat-card"><span>Attendance Today</span><strong><?php echo (int)$attendanceSummary["today_session_count"]; ?></strong></article>
            <?php } ?>
        </div>
    </div>
    <aside class="teacher-profile-card">
        <div class="teacher-profile-card__top">
            <img src="<?php echo td_esc($teacherImage); ?>" alt="<?php echo td_esc($teacherName !== "" ? $teacherName : $_SESSION['USERNAME']); ?>">
            <div>
                <span class="teacher-profile-card__eyebrow">Profile</span>
                <h2><?php echo td_esc($teacherName !== "" ? $teacherName : $_SESSION['USERNAME']); ?></h2>
                <p>Teacher<?php echo ($teacherBranch !== "" ? " | ".td_esc($teacherBranch) : ""); ?></p>
            </div>
        </div>
        <div class="teacher-profile-meta">
            <div class="teacher-profile-meta__item"><span>Branch</span><strong><?php echo td_esc($teacherBranch !== "" ? $teacherBranch : "Not Set"); ?></strong></div>
            <div class="teacher-profile-meta__item"><span>Active Batches</span><strong><?php echo (int)$activeBatchCount; ?></strong></div>
            <div class="teacher-profile-meta__item"><span>Image Status</span><strong><?php echo td_esc($teacherFilename !== "" ? "Uploaded" : "Not Uploaded"); ?></strong></div>
        </div>
        <div class="teacher-profile-actions">
            <a class="teacher-secondary-link" href="uploaduser-image.php"><i class="fa fa-arrow-circle-up"></i> Upload Image</a>
            <a class="teacher-secondary-link" href="edit-account.php"><i class="fa fa-user"></i> Edit Profile</a>
            <a class="teacher-secondary-link" href="change-password.php"><i class="fa fa-key"></i> Change Password</a>
        </div>
    </aside>
</section>

<section class="teacher-section">
    <div class="teacher-section__heading">
        <div><span class="teacher-section__eyebrow">Quick Actions</span><h2>Move straight into today's work</h2></div>
    </div>
    <div class="teacher-quick-grid">
        <a class="teacher-action-card" href="view-teacher-subject.php"><span class="teacher-action-card__icon"><i class="fa fa-search"></i></span><h3>Assigned Subjects</h3></a>
        <?php if($teacherCanTakeAttendance){ ?>
        <a class="teacher-action-card" href="student-attendance.php"><span class="teacher-action-card__icon"><i class="fa fa-check-square-o"></i></span><h3>Student Attendance</h3></a>
        <a class="teacher-action-card" href="student-attendance-report.php"><span class="teacher-action-card__icon"><i class="fa fa-bar-chart"></i></span><h3>Attendance Summary</h3></a>
        <?php } ?>
        <a class="teacher-action-card" href="class-score-entry.php"><span class="teacher-action-card__icon"><i class="fa fa-pencil"></i></span><h3>Class Score Entry</h3></a>
        <a class="teacher-action-card" href="exam-score-entry.php"><span class="teacher-action-card__icon"><i class="fa fa-edit"></i></span><h3>Exam Score Entry</h3></a>
        <a class="teacher-action-card" href="upload-classexam-score.php"><span class="teacher-action-card__icon"><i class="fa fa-upload"></i></span><h3>Upload Scores</h3></a>
        <?php if($teacherCanTakeAttendance){ ?>
        <a class="teacher-action-card" href="student-terminal-data.php"><span class="teacher-action-card__icon"><i class="fa fa-commenting"></i></span><h3>Student Remarks</h3></a>
        <?php } ?>
        <a class="teacher-action-card" href="terminal-report.php"><span class="teacher-action-card__icon"><i class="fa fa-book"></i></span><h3>Terminal Reports</h3></a>
        <a class="teacher-action-card" href="lesson-timetable-report.php"><span class="teacher-action-card__icon"><i class="fa fa-calendar"></i></span><h3>Lesson Timetable</h3><p>Open your weekly lesson schedule and check today’s teaching periods quickly.</p></a>
        <a class="teacher-action-card" href="scores-report.php"><span class="teacher-action-card__icon"><i class="fa fa-line-chart"></i></span><h3>Scores Report</h3><p>Check reporting summaries and score outputs for your classes.</p></a>
        <a class="teacher-action-card" href="messages.php"><span class="teacher-action-card__icon"><i class="fa fa-comments"></i></span><h3>Message Board</h3><p>Open the wider message board when you need more than the dashboard preview.</p></a>
    </div>
</section>

<div class="teacher-layout">
    <section class="teacher-panel teacher-panel--wide">
        <div class="teacher-panel__header">
            <div><span class="teacher-panel__eyebrow">Teaching Load</span><h2>Recent assigned subjects and classes</h2></div>
            <a class="teacher-panel__link" href="view-teacher-subject.php">View Full Subject List</a>
        </div>
        <?php if(count($recentTeachingGroups) > 0){ ?>
        <div class="teacher-load-grid">
            <?php foreach($recentTeachingGroups as $group){ ?>
            <article class="teacher-load-card">
                <div class="teacher-load-card__head">
                    <div>
                        <h3><?php echo td_esc($group["class_name"]); ?></h3>
                        <p><?php echo td_esc($group["session_label"]); ?></p>
                    </div>
                    <div class="teacher-load-card__badges">
                        <span class="teacher-pill"><?php echo count($group["subjects"]); ?> Subject<?php echo (count($group["subjects"])===1?"":"s"); ?></span>
                        <?php if($group["is_class_teacher"]){ ?><span class="teacher-pill teacher-pill--accent">Class Teacher</span><?php } ?>
                    </div>
                </div>
                <div class="teacher-chip-row"><?php foreach($group["subjects"] as $subject){ ?><span class="teacher-chip"><?php echo td_esc($subject); ?></span><?php } ?></div>
            </article>
            <?php } ?>
        </div>
        <?php if($teachingGroupCount > $recentTeachingGroupLimit){ ?>
        <div class="teacher-empty-state teacher-empty-state--compact">
            <p>Showing latest <?php echo (int)$recentTeachingGroupLimit; ?> of <?php echo (int)$teachingGroupCount; ?>.</p>
        </div>
        <?php } ?>
        <?php } else { ?>
        <div class="teacher-empty-state"><h3>No subject assignments yet</h3></div>
        <?php } ?>
    </section>

    <div class="teacher-panel-stack">
        <section class="teacher-panel">
            <div class="teacher-panel__header">
                <div><span class="teacher-panel__eyebrow">Engagement</span><h2>Keep your teaching flow active</h2></div>
            </div>
            <div class="teacher-engagement-hero teacher-engagement-hero--<?php echo td_esc($engagementSummary["badge"]["tone"]); ?>">
                <div class="teacher-engagement-hero__copy">
                    <span class="teacher-engagement-hero__eyebrow">Current Level</span>
                    <h3><?php echo td_esc($engagementSummary["badge"]["label"]); ?></h3>
                    <div class="teacher-engagement-stars" aria-label="<?php echo (int)$engagementSummary["stars"]; ?> stars">
                        <?php for($starIndex = 1; $starIndex <= 5; $starIndex++){ ?>
                        <i class="fa fa-star<?php echo ($starIndex <= (int)$engagementSummary["stars"]) ? " is-active" : ""; ?>"></i>
                        <?php } ?>
                    </div>
                    <div class="teacher-engagement-total"><?php echo number_format((int)$engagementSummary["total_points"]); ?> points</div>
                    <div class="teacher-engagement-meter" aria-hidden="true">
                        <span class="teacher-engagement-meter__fill" style="width: <?php echo (int)$engagementSummary["progress_percent"]; ?>%;"></span>
                    </div>
                    <p class="teacher-engagement-progress-copy">
                        <?php if(!empty($engagementSummary["next_badge"])){ ?>
                            <?php echo number_format((int)$engagementSummary["points_to_next"]); ?> more point<?php echo ((int)$engagementSummary["points_to_next"] === 1 ? "" : "s"); ?> to reach <?php echo td_esc((string)$engagementSummary["next_badge"]["label"]); ?>.
                        <?php } else { ?>
                            Top level reached. Keep the momentum going.
                        <?php } ?>
                    </p>
                </div>
                <div class="teacher-engagement-side">
                    <article class="teacher-engagement-stat">
                        <span>This Week</span>
                        <strong><?php echo number_format((int)$engagementSummary["week_points"]); ?></strong>
                    </article>
                    <article class="teacher-engagement-stat">
                        <span>Active Streak</span>
                        <strong><?php echo number_format((int)$engagementSummary["streak_days"]); ?> Day<?php echo ((int)$engagementSummary["streak_days"] === 1 ? "" : "s"); ?></strong>
                    </article>
                    <article class="teacher-engagement-stat">
                        <span>Progress</span>
                        <strong><?php echo (int)$engagementSummary["progress_percent"]; ?>%</strong>
                    </article>
                </div>
            </div>
            <div class="teacher-engagement-list">
                <?php if(count($engagementRecent) > 0){ ?>
                    <?php foreach($engagementRecent as $activity){ ?>
                    <article class="teacher-engagement-item">
                        <div class="teacher-engagement-item__meta">
                            <strong><?php echo td_esc((string)$activity["actionlabel"]); ?></strong>
                            <span><?php echo td_esc(td_date((string)$activity["datetimeentry"])); ?></span>
                        </div>
                        <div class="teacher-engagement-points">+<?php echo number_format((int)$activity["pointvalue"]); ?></div>
                    </article>
                    <?php } ?>
                <?php } else { ?>
                <div class="teacher-empty-state teacher-empty-state--compact"><p>No activity yet.</p></div>
                <?php } ?>
            </div>
        </section>

        <section class="teacher-panel">
            <div class="teacher-panel__header">
                <div><span class="teacher-panel__eyebrow">Duty Roster</span><h2>Duty reminders on your dashboard</h2></div>
            </div>
            <?php if(count($dutyDashboard["cards"]) > 0){ ?>
            <div class="teacher-duty-grid">
                <?php foreach($dutyDashboard["cards"] as $card){ ?>
                <article class="teacher-duty-card teacher-duty-card--<?php echo td_esc($card["tone"]); ?>">
                    <div class="teacher-duty-card__top">
                        <span class="teacher-duty-label"><?php echo td_esc($card["label"]); ?></span>
                        <span class="teacher-duty-period"><?php echo td_esc($card["period"]); ?></span>
                    </div>
                    <h3><?php echo td_esc($card["title"]); ?></h3>
                    <p><?php echo td_esc($card["location"] !== "" ? $card["location"] : "Pending"); ?></p>
                    <?php if($card["note"] !== ""){ ?><small><?php echo td_esc($card["note"]); ?></small><?php } ?>
                </article>
                <?php } ?>
            </div>
            <?php } else { ?>
            <div class="teacher-empty-state teacher-empty-state--compact"><p>No duty roster yet.</p></div>
            <?php } ?>
        </section>

        <section class="teacher-panel">
            <div class="teacher-panel__header">
                <div><span class="teacher-panel__eyebrow">Class Teacher</span><h2>My class-teacher duties</h2></div>
            </div>
            <?php if($classTeacherRoleCount > 0){ ?>
            <div class="teacher-role-list">
                <?php foreach($classTeacherRoles as $role){ ?>
                <article class="teacher-role-card">
                    <h3><?php echo td_esc($role["class_name"]); ?></h3>
                    <p><?php echo td_esc($role["session_label"]); ?></p>
                    <span>Class teacher assignment</span>
                </article>
                <?php } ?>
            </div>
            <?php } else { ?>
            <div class="teacher-empty-state teacher-empty-state--compact"><p>No class-teacher role yet.</p></div>
            <?php } ?>
        </section>

        <section class="teacher-panel">
            <div class="teacher-panel__header">
                <div><span class="teacher-panel__eyebrow">Resources</span><h2>Downloads and links</h2></div>
            </div>
            <div class="teacher-resource-list">
                <a class="teacher-resource-link" href="download-classscore-template.php"><span class="teacher-resource-link__icon"><i class="fa fa-download"></i></span><span class="teacher-resource-link__body"><strong>Class Score Template</strong></span></a>
                <a class="teacher-resource-link" href="download-examscore-template.php"><span class="teacher-resource-link__icon"><i class="fa fa-download"></i></span><span class="teacher-resource-link__body"><strong>Exam Score Template</strong></span></a>
                <a class="teacher-resource-link" href="download-classexamscore-template.php"><span class="teacher-resource-link__icon"><i class="fa fa-download"></i></span><span class="teacher-resource-link__body"><strong>Class & Exam Template</strong></span></a>
                <a class="teacher-resource-link" href="lesson-timetable-report.php"><span class="teacher-resource-link__icon"><i class="fa fa-calendar"></i></span><span class="teacher-resource-link__body"><strong>Lesson Timetable</strong></span></a>
                <a class="teacher-resource-link" href="examinationtimetablereport.php"><span class="teacher-resource-link__icon"><i class="fa fa-calendar"></i></span><span class="teacher-resource-link__body"><strong>Exam Timetable Report</strong></span></a>
            </div>
        </section>
    </div>
</div>

<div class="teacher-layout teacher-layout--messages">
    <section class="teacher-panel" id="teacher-messages">
        <div class="teacher-panel__header">
            <div><span class="teacher-panel__eyebrow">Message Center</span><h2>Send and manage your messages</h2></div>
            <a class="teacher-panel__link" href="messages.php">Open Full Message Board</a>
        </div>
        <form method="post" action="teacher-page.php#teacher-messages" class="teacher-message-form">
            <label for="message">Write a message</label>
            <textarea id="message" name="message" placeholder="Share an update, request support, or leave a note for the school team." required></textarea>
            <div class="teacher-message-form__actions">
                <button class="teacher-primary-btn" type="submit" name="send_message"><i class="fa fa-send"></i> Send Message</button>
            </div>
        </form>

        <div class="teacher-message-list">
            <?php if(count($myMessages) > 0){ ?>
                <?php foreach($myMessages as $message){ ?>
                <article class="teacher-message-card">
                    <div class="teacher-message-card__meta">
                        <span><?php echo td_esc(td_date($message["datetimeentry"])); ?></span>
                        <form method="post" action="teacher-page.php#teacher-messages">
                            <input type="hidden" name="messageid" value="<?php echo td_esc((string)$message["messageid"]); ?>">
                            <button type="submit" name="delete_message" class="teacher-message-delete" onclick="return confirm('Delete this message?');"><i class="fa fa-trash"></i> Delete</button>
                        </form>
                    </div>
                    <p><?php echo nl2br(td_esc($message["messages"])); ?></p>
                </article>
                <?php } ?>
            <?php } else { ?>
            <div class="teacher-empty-state teacher-empty-state--compact"><p>No messages yet.</p></div>
            <?php } ?>
        </div>
    </section>

</div>
</main>
</body>
</html>
