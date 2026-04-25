<?php
if(!function_exists('menuboard_section_start')){
function menuboard_section_start($title, $icon = 'fa-folder-open', $open = false){
    echo "<details class='menuboard-section'".($open ? " open" : "").">";
    echo "<summary><span class='menuboard-section__title'><i class='fa ".$icon."'></i> ".$title."</span></summary>";
    echo "<div class='menuboard-section__body'>";
}
}

if(!function_exists('menuboard_section_end')){
function menuboard_section_end(){
    echo "</div></details>";
}
}
?>
<?php
if(!isset($con)){
    include_once("dbstring.php");
}
include_once("user-management-utils.php");
$_TeacherExtraAccessLinks = array();
$_ShowTeacherAttendanceLinks = false;
if(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
   $_SESSION['ACCESSLEVEL'] === "user" &&
   $_SESSION['SYSTEMTYPE'] === "Teacher"){
    include_once("class-teacher-utils.php");
    ensure_class_teacher_table($con);
    $_TeacherExtraAccessLinks = um_teacher_extra_nav_links($con, $_SESSION['USERID']);
    $_ShowTeacherAttendanceLinks = class_teacher_has_any_assignment($con, $_SESSION['USERID']);
}
?>
<style>
.menu-inner .menuboard-section{
    margin:12px 0;
    border:1px solid rgba(255,255,255,0.12);
    border-radius:14px;
    background:rgba(255,255,255,0.04);
    overflow:hidden;
}

.menu-inner .menuboard-section summary{
    list-style:none;
    cursor:pointer;
    padding:12px 14px;
    color:#fff;
    font-weight:700;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
}

.menu-inner .menuboard-section__title{
    display:inline-flex;
    align-items:center;
    gap:10px;
}

.menu-inner .menuboard-section summary::-webkit-details-marker{
    display:none;
}

.menu-inner .menuboard-section summary::after{
    content:"\f107";
    font-family:FontAwesome;
    color:#d8e6f3;
    transition:transform 0.18s ease;
}

.menu-inner .menuboard-section[open] summary::after{
    transform:rotate(180deg);
}

.menu-inner .menuboard-section__body{
    padding:4px 14px 12px;
    border-top:1px solid rgba(255,255,255,0.08);
}

.menu-inner .menuboard-section__body a{
    display:block;
    margin:0;
    padding:9px 0;
    line-height:1.45;
    font-family: Arial, Helvetica, sans-serif !important;
}

.menu-inner .menuboard-section__body a i{
    width:18px;
    text-align:center;
    font-family: FontAwesome !important;
}

.menu-inner .menuboard-home-link{
    display:block;
    margin:6px 0 10px;
}

.menu-inner .menuboard-quick-links{
    display:grid;
    gap:10px;
    margin:8px 0 14px;
}

.menu-inner .menuboard-quick-links a{
    display:flex;
    align-items:center;
    gap:10px;
    padding:10px 12px;
    border:1px solid rgba(255,255,255,0.1);
    border-radius:12px;
    background:rgba(255,255,255,0.05);
    font-family: Arial, Helvetica, sans-serif !important;
}

.menu-inner .menuboard-quick-links a i{
    width:18px;
    text-align:center;
    font-family: FontAwesome !important;
}

.menu-inner .menuboard-quick-divider{
    margin:14px 0;
    border:0;
    border-top:1px solid rgba(255,255,255,0.12);
}

.menu-inner .menuboard-inline-link{
    display:inline-flex;
    align-items:center;
    gap:8px;
    position:relative;
    z-index:2;
    pointer-events:auto;
}

.menu-inner .menuboard-inline-link i{
    width:auto;
    font-family:FontAwesome !important;
}

.menu-inner .menuboard-badge{
    display:inline-block;
    min-width:18px;
    padding:1px 6px;
    border-radius:999px;
    background:#b91c1c;
    color:#fff;
    font-size:10px;
    text-align:center;
    line-height:1.2;
}
</style>
<div class="menu-inner">
<div style="text-align:center">
    <?php
    @$FileName="";
    @$_Branch="";

    include("dbstring.php");
    $sql ="SELECT * FROM tblsystemuser su INNER JOIN tblbranch br ON su.branchid=br.branchid
     WHERE userid='$_SESSION[USERID]'";
    $result = mysqli_query($con,$sql);
    if($row=mysqli_fetch_array($result,MYSQLI_ASSOC)){
    $FileName = $row['filename'];
    $_Branch=$row['location'];

    if($FileName){
      echo "<img src='uploads/$FileName' width='80px' height='80px' style='border-radius:100%'/><br/><br/>";
      echo "<div style='padding:10px;background-color:transparent;margin-top:-5px;margin-left:-5px;margin-right:-5px;margin-bottom:-5px;border-bottom:0px solid #ccc;color:white;font-size:1em;font-weight:bold'>$_SESSION[FULLNAME]</div>"; 
      echo "<b style='color:lightblue;font-size:10px;padding:10px;'>Uploaded Date/Time:$row[uploadeddatetime]</b>";
    }
    else{
      echo "<img src='uploads/comm.gif' width='80px' height='80px' style='border-radius:100%'/><br/>";
      echo "<div style='padding:10px;background-color:transparent;margin-top:-5px;margin-left:-5px;margin-right:-5px;margin-bottom:-5px;border-bottom:0px solid #ccc;color:white;font-size:1em;font-weight:bold'>$_SESSION[FULLNAME]</div>"; 
      echo "<b style='color:lightblue;font-size:10px'>Image Not Uploaded</b>";
    }
  }
?>

<br/><br/>
<a href="uploaduser-image.php" class="button-pay" title="Open and Upload Your Image"><i  class="fa fa-arrow-circle-up"> Upload Image </i></a>
<br/>

<?php
@$_UnreadChangeCount = 0;
if(($_SESSION['ACCESSLEVEL']=="administrator" && $_SESSION['SYSTEMTYPE']=="normal_user") || ($_SESSION['ACCESSLEVEL']=="administrator" && $_SESSION['SYSTEMTYPE']=="super_user")){
  include("audit_notifications.php");
  ensureSystemChangeLogTable($con);
  $_SQL_UNREAD_CHG = mysqli_query($con,"SELECT COUNT(*) AS total_unread
    FROM tblsystemchangelog
    WHERE status='unread'
      AND actor_type IN ('Teacher','Student')");
  if($_SQL_UNREAD_CHG && $row_uc=mysqli_fetch_array($_SQL_UNREAD_CHG,MYSQLI_ASSOC)){
    $_UnreadChangeCount = (int)$row_uc['total_unread'];
  }
}

if( $_SESSION['SYSTEMTYPE']=="normal_user"){
echo "<br/><b style='margin-bottom:10px;font-size:12px;'> Level: Administrator</b><br/><br/>";  
}
elseif( $_SESSION['SYSTEMTYPE']=="super_user"){
echo "<br/><b> Level: Super User</b><br/><br/>"; 
}
else{
echo "<br/><b> Level:". $_SESSION['SYSTEMTYPE'] ."</b><br/><br/>";   
}
echo "<b style='margin-bottom:10px;font-size:12px;'> Branch:". $_Branch ."</b><br/>";
?>
</div>
<hr>
<a href="edit-account.php" ><i   class="fa fa-edit"> Edit Profile </i> </a><br/>
<a href="change-password.php" ><i  class="fa fa-edit"> Change Password </i> </a><br/>
<a href="messages.php" ><i  class="fa fa-book"> Messages </i></a><br/>
<?php
if(($_SESSION['ACCESSLEVEL']=="administrator" && $_SESSION['SYSTEMTYPE']=="normal_user") || ($_SESSION['ACCESSLEVEL']=="administrator" && $_SESSION['SYSTEMTYPE']=="super_user")){
  echo "<a href='user-management.php'><i class='fa fa-users'> User Management </i></a><br/>";
}
?>
<?php
if(($_SESSION['ACCESSLEVEL']=="administrator" && $_SESSION['SYSTEMTYPE']=="normal_user") || ($_SESSION['ACCESSLEVEL']=="administrator" && $_SESSION['SYSTEMTYPE']=="super_user")){
  echo "<a class='menuboard-inline-link' href='admin.php#system-change-notifications'><i class='fa fa-bell'></i><span>Notifications</span><span class='menuboard-badge'>".$_UnreadChangeCount."</span></a><br/>";
}
?>

<hr>

<?php
//session_start();
if($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="Teacher"){
?>
<div class="menuboard-quick-links">
<a class="active menuboard-home-link" href="teacher-page.php"><i class="fa fa-home"></i> Home</a>
</div>
<?php menuboard_section_start('Subject', 'fa-book', true); ?>
<a href="view-teacher-subject.php"><i class="fa fa-search"></i> View Subject(s) Assigned</a>
<?php if($_ShowTeacherAttendanceLinks){ ?>
<a href="student-attendance.php"><i class="fa fa-check-square-o"></i> Student Attendance</a>
<a href="student-attendance-report.php"><i class="fa fa-bar-chart"></i> Attendance Summary</a>
<?php } ?>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Scores', 'fa-pencil'); ?>
<a href="class-score-entry.php"><i class="fa fa-pencil"></i> Class Score Entry</a>
<a href="exam-score-entry.php"><i class="fa fa-pencil"></i> Exam Score Entry</a>
<a href="upload-class-score-entry.php"><i class="fa fa-arrow-circle-up"></i> Upload Class Score</a>
<a href="upload-exam-score-entry.php"><i class="fa fa-arrow-circle-up"></i> Upload Exam Score</a>
<a href="upload-classexam-score.php"><i class="fa fa-arrow-circle-up"></i> Upload Class & Exam Score</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Downloads', 'fa-download'); ?>
<a href="download-classscore-template.php"><i class="fa fa-download"></i> Class Score Template</a>
<a href="download-examscore-template.php"><i class="fa fa-download"></i> Exam Score Template</a>
<a href="download-classexamscore-template.php"><i class="fa fa-download"></i> Class/Exam Score Template</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Report', 'fa-file-text-o'); ?>
<a href="scores-report.php"><i class="fa fa-book"></i> Scores Report</a>
<a href="student-terminal-data.php"><i class="fa fa-plus"></i> Student Remark Data</a>
<a href="upload-student-remark-data.php"><i class="fa fa-arrow-circle-up"></i> Upload Students Remark Data</a>
<a href="terminal-report.php"><i class="fa fa-book"></i> Examination Report</a>
<a href="lesson-timetable-report.php"><i class="fa fa-calendar"></i> Lesson Timetable</a>
<a href="examinationtimetablereport.php"><i class="fa fa-book"></i> Exam Time Table Report</a>
<?php menuboard_section_end(); ?>

<?php if(!empty($_TeacherExtraAccessLinks)){ ?>
<?php menuboard_section_start('Extended Access', 'fa-unlock'); ?>
<?php foreach($_TeacherExtraAccessLinks as $_ExtraLink){ ?>
<a href="<?php echo htmlspecialchars($_ExtraLink['href'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fa <?php echo htmlspecialchars($_ExtraLink['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i> <?php echo htmlspecialchars($_ExtraLink['label'], ENT_QUOTES, 'UTF-8'); ?></a>
<?php } ?>
<?php menuboard_section_end(); ?>
<?php } ?>

<?php
}
else if($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="Student"){
?>
<div class="menuboard-quick-links">
<a class="menuboard-home-link" href="student-page.php"><i class="fa fa-home"></i> Home</a>
</div>
<?php menuboard_section_start('Accounts', 'fa-money', true); ?>
<a href="bills.php"><i class="fa fa-money"></i> Bills</a>
<a href="account-statements.php"><i class="fa fa-money"></i> Account Statements</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Communication', 'fa-comments'); ?>
<a href="messages.php"><i class="fa fa-comments"></i> Message Box</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Records', 'fa-folder-o'); ?>
<a href="registeredclass.php"><i class="fa fa-folder-o"></i> Registered Class</a>
<a href="registeredsubject.php"><i class="fa fa-folder-o"></i> Registered Subject</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Examination', 'fa-graduation-cap'); ?>
<a href="examinationtimetablereport.php"><i class="fa fa-folder-o"></i> Exam Time Table Report</a>
<a href="lesson-timetable-report.php"><i class="fa fa-clock-o"></i> Lesson Timetable</a>
<a href="individual-terminal-report.php"><i class="fa fa-folder-o"></i> Examination Report</a>
<a href="student-attendance-report.php"><i class="fa fa-bar-chart"></i> My Attendance</a>
<?php menuboard_section_end(); ?>
<?php
}
else if($_SESSION['ACCESSLEVEL']=="administrator" && $_SESSION['SYSTEMTYPE']=="super_user"){
 ?>
<div class="menuboard-quick-links">
<a href="search.php"><i class="fa fa-search"></i> Search Student</a>
<a class="menuboard-home-link" href="super.php"><i class="fa fa-home"></i> Home</a>
</div>

<?php menuboard_section_start('School Setup', 'fa-cogs'); ?>
<a href="company-entry.php"><i class="fa fa-plus"></i> School Entry</a>
<a href="branch-entry.php"><i class="fa fa-plus"></i> Branch Entry</a>
<a href="batch-entry.php"><i class="fa fa-plus"></i> Batch Entry</a>
<a href="subject-entry.php"><i class="fa fa-plus"></i> Subject Entry</a>
<a href="class-entry.php"><i class="fa fa-plus"></i> Class Entry</a>
<a href="school-data-entry.php"><i class="fa fa-plus"></i> School Data Entry</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Accounts', 'fa-money'); ?>
<a href="item-entry.php"><i class="fa fa-plus"></i> Item Entry</a>
<a href="item-pricing.php"><i class="fa fa-plus"></i> Item Pricing</a>
<a href="class-billing.php"><i class="fa fa-plus"></i> Class Billing</a>
<a href="daily-report.php"><i class="fa fa-book"></i> Daily Report</a>
<a href="payment-analysis.php"><i class="fa fa-book"></i> Payment Report</a>
<a href="bills-report.php"><i class="fa fa-book"></i> Bills Report</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Billing', 'fa-money'); ?>
<a href="student-billing.php"><i class="fa fa-plus"></i> Bill Student</a>
<a href="group-student-billing.php"><i class="fa fa-plus"></i> Bill Group Students</a>
<a href="rebill-group-student.php"><i class="fa fa-plus"></i> Rebill Group Students</a>
<a href="print-student-bills.php"><i class="fa fa-plus"></i> Print Student Bills</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Records', 'fa-folder-o'); ?>
<a href="class-registry.php"><i class="fa fa-plus"></i> Class Registry</a>
<a href="upload-class-registry.php"><i class="fa fa-arrow-circle-up"></i> Upload Class Registry</a>
<a href="view-class-registry.php"><i class="fa fa-arrow-circle-up"></i> View Class Registry</a>
<a href="term-registry.php"><i class="fa fa-plus"></i> Semester Registry</a>
<a href="group-term-registry.php"><i class="fa fa-plus"></i> Group Semester Registry</a>
<a href="upload-semester-registry.php"><i class="fa fa-arrow-circle-up"></i> Upload Semester Registry</a>
<a href="promotion-center.php"><i class="fa fa-level-up"></i> Promotion Center</a>
<a href="student-history.php"><i class="fa fa-history"></i> Student History</a>
<a href="continuing-students.php"><i class="fa fa-users"></i> Continuing Students</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Subject', 'fa-book'); ?>
<a href="subject-classification.php"><i class="fa fa-plus"></i> Subject Classification</a>
<a href="view-subject-classified.php"><i class="fa fa-plus"></i> View Subject Classified</a>
<a href="subject-assignment.php"><i class="fa fa-plus"></i> Subject Assignment</a>
<a href="class-teacher-assignment.php"><i class="fa fa-plus"></i> Class Teacher Assignment</a>
<a href="student-attendance.php"><i class="fa fa-check-square-o"></i> Student Attendance</a>
<a href="student-attendance-report.php"><i class="fa fa-bar-chart"></i> Attendance Summary</a>
<a href="duty-roster.php"><i class="fa fa-calendar-check-o"></i> Duty Roster</a>
<a href="lesson-timetable.php"><i class="fa fa-calendar"></i> Lesson Timetable Entry</a>
<a href="lesson-timetable-report.php"><i class="fa fa-book"></i> Lesson Timetable Report</a>
<a href="online-admission-admin.php"><i class="fa fa-globe"></i> Online Admission</a>
<a href="view-all-subject-assigned.php"><i class="fa fa-plus"></i> View Subject(s) Assigned</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Examination', 'fa-graduation-cap'); ?>
<a href="class-score-entry.php"><i class="fa fa-pencil"></i> Class Score Entry</a>
<a href="exam-score-entry.php"><i class="fa fa-pencil"></i> Exam Score Entry</a>
<a href="upload-class-score-entry.php"><i class="fa fa-arrow-circle-up"></i> Upload Class Score</a>
<a href="upload-exam-score-entry.php"><i class="fa fa-arrow-circle-up"></i> Upload Exam Score</a>
<a href="upload-classexam-score.php"><i class="fa fa-arrow-circle-up"></i> Upload Class & Exam Scores</a>
<a href="scores-report.php"><i class="fa fa-book"></i> Scores Report</a>
<a href="waec-analysis.php"><i class="fa fa-line-chart"></i> WAEC Analysis</a>
<a href="terminal-report.php"><i class="fa fa-book"></i> Examination Report</a>
<a href="internal-exam-analysis.php"><i class="fa fa-folder-o"></i> Internal Exams Analysis</a>
<a href="examanalysis-subject.php"><i class="fa fa-folder-o"></i> Exam Analysis : Subject</a>
<a href="examanalysis-rank.php"><i class="fa fa-folder-o"></i> Exam Analysis : Rank</a>
<a href="lesson-timetable.php"><i class="fa fa-calendar"></i> Lesson Timetable Entry</a>
<a href="lesson-timetable-report.php"><i class="fa fa-book"></i> Lesson Timetable Report</a>
<a href="examinationtimetable.php"><i class="fa fa-plus"></i> Exam Time Table Entry</a>
<a href="examinationtimetablereport.php"><i class="fa fa-book"></i> Exam Time Table Report</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Notice', 'fa-comments-o'); ?>
<a href="notification.php"><i class="fa fa-plus"></i> Send Notification</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('SMS', 'fa-phone'); ?>
<a href="enablesmsalert.php"><i class="fa fa-phone"></i> Enable SMS Alert</a>
<a href="smsreport.php"><i class="fa fa-phone"></i> SMS Reporting</a>
<a href="smsreportdata.php"><i class="fa fa-database"></i> SMS Data</a>
<?php menuboard_section_end(); ?>

<?php
}
else if($_SESSION['ACCESSLEVEL']=="administrator" && $_SESSION['SYSTEMTYPE']=="normal_user"){
 ?>
<div class="menuboard-quick-links">
<a href="search.php"><i class="fa fa-search"></i> Search Student</a>
<a class="menuboard-home-link" href="admin.php"><i class="fa fa-home"></i> Home</a>
</div>

<?php menuboard_section_start('School Setup', 'fa-cogs'); ?>
<a href="company-entry.php"><i class="fa fa-plus"></i> School Entry</a>
<a href="branch-entry.php"><i class="fa fa-plus"></i> Branch Entry</a>
<a href="batch-entry.php"><i class="fa fa-plus"></i> Batch Entry</a>
<a href="subject-entry.php"><i class="fa fa-plus"></i> Subject Entry</a>
<a href="class-entry.php"><i class="fa fa-plus"></i> Class Entry</a>
<a href="school-data-entry.php"><i class="fa fa-plus"></i> School Data Entry</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Billing', 'fa-money'); ?>
<a href="student-billing.php"><i class="fa fa-plus"></i> Bill Student</a>
<a href="group-student-billing.php"><i class="fa fa-plus"></i> Bill Group Students</a>
<a href="rebill-group-student.php"><i class="fa fa-plus"></i> Rebill Group Students</a>
<a href="print-student-bills.php"><i class="fa fa-plus"></i> Print Student Bills</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Accounts', 'fa-money'); ?>
<a href="item-entry.php"><i class="fa fa-plus"></i> Item Entry</a>
<a href="item-pricing.php"><i class="fa fa-plus"></i> Class Item Billing</a>
<a href="class-billing.php"><i class="fa fa-plus"></i> Class Billing</a>
<a href="class-Bills-report.php"><i class="fa fa-plus"></i> Class Bills Report</a>
<a href="daily-report.php"><i class="fa fa-book"></i> Daily Report</a>
<a href="payment-analysis.php"><i class="fa fa-book"></i> Payment Report</a>
<a href="bills-report.php"><i class="fa fa-book"></i> Bills Report</a>
<a href="item-bill-report.php"><i class="fa fa-book"></i> Item Bill Report</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Records', 'fa-folder-o'); ?>
<a href="class-registry.php"><i class="fa fa-plus"></i> Class Registry</a>
<a href="upload-class-registry.php"><i class="fa fa-arrow-circle-up"></i> Upload Class Registry</a>
<a href="view-class-registry.php"><i class="fa fa-arrow-circle-up"></i> View Class Registry</a>
<a href="term-registry.php"><i class="fa fa-plus"></i> Semester Registry</a>
<a href="group-term-registry.php"><i class="fa fa-plus"></i> Group Semester Registry</a>
<a href="upload-semester-registry.php"><i class="fa fa-arrow-circle-up"></i> Upload Semester Registry</a>
<a href="promotion-center.php"><i class="fa fa-level-up"></i> Promotion Center</a>
<a href="student-history.php"><i class="fa fa-history"></i> Student History</a>
<a href="continuing-students.php"><i class="fa fa-users"></i> Continuing Students</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Subject', 'fa-book'); ?>
<a href="subject-classification.php"><i class="fa fa-plus"></i> Subject Classification</a>
<a href="view-subject-classified.php"><i class="fa fa-book"></i> View Subject Classified</a>
<a href="subject-assignment.php"><i class="fa fa-plus"></i> Subject Assignment</a>
<a href="class-teacher-assignment.php"><i class="fa fa-plus"></i> Class Teacher Assignment</a>
<a href="student-attendance.php"><i class="fa fa-check-square-o"></i> Student Attendance</a>
<a href="student-attendance-report.php"><i class="fa fa-bar-chart"></i> Attendance Summary</a>
<a href="duty-roster.php"><i class="fa fa-calendar-check-o"></i> Duty Roster</a>
<a href="lesson-timetable.php"><i class="fa fa-calendar"></i> Lesson Timetable Entry</a>
<a href="lesson-timetable-report.php"><i class="fa fa-book"></i> Lesson Timetable Report</a>
<a href="online-admission-admin.php"><i class="fa fa-globe"></i> Online Admission</a>
<a href="view-all-subject-assigned.php"><i class="fa fa-book"></i> View Subject(s) Assigned</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Examination', 'fa-graduation-cap'); ?>
<a href="student-terminal-data.php"><i class="fa fa-plus"></i> Student Remark Data</a>
<a href="upload-student-remark-data.php"><i class="fa fa-arrow-circle-up"></i> Upload Students Remark Data</a>
<a href="continuous-assessment.php"><i class="fa fa-folder-o"></i> Continuous Assessment</a>
<a href="scores-report-all.php"><i class="fa fa-folder-o"></i> Scores Report</a>
<a href="waec-analysis.php"><i class="fa fa-line-chart"></i> WAEC Analysis</a>
<a href="terminal-report.php"><i class="fa fa-folder-o"></i> Examination Report</a>
<a href="internal-exam-analysis.php"><i class="fa fa-folder-o"></i> Internal Exams Analysis</a>
<a href="examanalysis-subject.php"><i class="fa fa-folder-o"></i> Exam Analysis : Subject</a>
<a href="examanalysis-rank.php"><i class="fa fa-folder-o"></i> Exam Analysis : Rank</a>
<a href="lesson-timetable.php"><i class="fa fa-calendar"></i> Lesson Timetable Entry</a>
<a href="lesson-timetable-report.php"><i class="fa fa-book"></i> Lesson Timetable Report</a>
<a href="examinationtimetable.php"><i class="fa fa-plus"></i> Exam Time Table Entry</a>
<a href="examinationtimetablereport.php"><i class="fa fa-book"></i> Exam Time Table Report</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Notice', 'fa-comments-o'); ?>
<a href="notification.php"><i class="fa fa-plus"></i> Send Notification</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('SMS', 'fa-phone'); ?>
<a href="enablesmsalert.php"><i class="fa fa-phone"></i> Enable SMS Alert</a>
<a href="smsreport.php"><i class="fa fa-phone"></i> SMS Reporting</a>
<a href="smsreportdata.php"><i class="fa fa-database"></i> SMS Data</a>
<?php menuboard_section_end(); ?>
<?php

}
else if($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="User"){
 ?>
<div class="menuboard-quick-links">
<a class="active menuboard-home-link" href="user.php"><i class="fa fa-home"></i> Home</a>
</div>

<?php menuboard_section_start('School Setup', 'fa-cogs'); ?>
<a href="batch-entry.php"><i class="fa fa-plus"></i> Batch Entry</a>
<a href="subject-entry.php"><i class="fa fa-plus"></i> Subject Entry</a>
<a href="class-entry.php"><i class="fa fa-plus"></i> Class Entry</a>
<a href="school-data-entry.php"><i class="fa fa-plus"></i> School Data Entry</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Records', 'fa-folder-o'); ?>
<a href="class-registry.php"><i class="fa fa-plus"></i> Class Registry</a>
<a href="upload-class-registry.php"><i class="fa fa-arrow-circle-up"></i> Upload Class Registry</a>
<a href="view-class-registry.php"><i class="fa fa-arrow-circle-up"></i> View Class Registry</a>
<a href="term-registry.php"><i class="fa fa-plus"></i> Semester Registry</a>
<a href="group-term-registry.php"><i class="fa fa-plus"></i> Group Semester Registry</a>
<a href="upload-semester-registry.php"><i class="fa fa-arrow-circle-up"></i> Upload Semester Registry</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Tools', 'fa-wrench'); ?>
<a href="subject-classification.php"><i class="fa fa-plus"></i> Subject Classification</a>
<a href="view-subject-classified.php"><i class="fa fa-plus"></i> View Subject Classified</a>
<a href="subject-assignment.php"><i class="fa fa-plus"></i> Subject Assignment</a>
<a href="view-all-subject-assigned.php"><i class="fa fa-plus"></i> View Subject(s) Assigned</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Examination', 'fa-graduation-cap'); ?>
<a href="examinationtimetable.php"><i class="fa fa-plus"></i> Exam Time Table Entry</a>
<a href="examinationtimetablereport.php"><i class="fa fa-book"></i> Exam Time Table Report</a>
<a href="student-terminal-data.php"><i class="fa fa-plus"></i> Student Terminal Data</a>
<a href="terminal-report.php"><i class="fa fa-book"></i> Examination Report</a>
<a href="scores-report.php"><i class="fa fa-book"></i> Scores Report</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('Notice', 'fa-comments-o'); ?>
<a href="notification.php"><i class="fa fa-plus"></i> Send Notification</a>
<?php menuboard_section_end(); ?>

<?php menuboard_section_start('SMS', 'fa-phone'); ?>
<a href="smsreport.php"><i class="fa fa-plus"></i> SMS Reporting</a>
<?php menuboard_section_end(); ?>
<?php
}
?>
</div>
<script>
(function () {
    var sections = document.querySelectorAll('.menu-inner .menuboard-section');
    sections.forEach(function (section) {
        section.addEventListener('toggle', function () {
            if (!section.open) {
                return;
            }

            sections.forEach(function (otherSection) {
                if (otherSection !== section) {
                    otherSection.open = false;
                }
            });
        });
    });
})();
</script>
