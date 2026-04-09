
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
  echo "<a href='admin-password-reset.php'><i class='fa fa-key'> Reset Teacher/Student Password </i></a><br/>";
}
?>
<?php
if(($_SESSION['ACCESSLEVEL']=="administrator" && $_SESSION['SYSTEMTYPE']=="normal_user") || ($_SESSION['ACCESSLEVEL']=="administrator" && $_SESSION['SYSTEMTYPE']=="super_user")){
  echo "<a href='admin.php#system-change-notifications'><i class='fa fa-bell'> Notifications <span style='display:inline-block;min-width:18px;padding:1px 6px;border-radius:999px;background:#b91c1c;color:#fff;font-size:10px;text-align:center;'>".$_UnreadChangeCount."</span></i></a><br/>";
}
?>

<hr>

<?php
//session_start();
if($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="Teacher"){
?>

<a class="active" href="teacher-page.php"><i class="fa fa-home"> Home </i></a><br/>
<br/>
<strong><i class="fa fa-check" > SUBJECT </i></strong><br/>
<a href="view-teacher-subject.php"><i class="fa fa-search" > View Subject(s) Assigned </i></a><br/>
<hr/>
<strong><i class="fa fa-check" > SCORES </i></strong><br/>
<a href="class-score-entry.php"><i class="fa fa-pencil" > Class Score Entry </i></a><br/>
<a href="exam-score-entry.php"><i class="fa fa-pencil" > Exam Score Entry </i></a><br/>
<a href="upload-class-score-entry.php"><i class="fa fa-arrow-circle-up" > Upload Class Score </i> </a><br/>
<a href="upload-exam-score-entry.php"><i class="fa fa-arrow-circle-up" > Upload Exam Score </i> </a><br/>

<a href="upload-classexam-score.php"><i class="fa fa-arrow-circle-up" > Upload Class & Exam Score </i> </a><br/>
<hr>
<strong><i class="fa fa-check" >DOWNLOADS </i></strong><br/>
<a href="download-classscore-template.php"><i class="fa fa-download" > Class Score Template </i> </a><br/>
<a href="download-examscore-template.php"><i class="fa fa-download" > Exam Score Template </i> </a><br/>
<a href="download-classexamscore-template.php"><i class="fa fa-download" > Class/Exam Score Template </i> </a><br/>
<hr>
<strong><i class="fa fa-check" > REPORT </i></strong><br/>
<a href="scores-report.php"><i class="fa fa-book" > Scores Report </i></a><br/>
<a href="student-terminal-data.php"><i class="fa fa-plus" > Student Remark Data </i></a><br/>
<a href="upload-student-remark-data.php"><i class="fa fa-arrow-circle-up" > Upload Students Remark Data </i></a><br/>
<a href="terminal-report.php"> <i class="fa fa-book" > Examination Report </i></a><br/>
<a href="examinationtimetablereport.php"><i class="fa fa-book" > Exam Time Table Report </i></a><br/>

<?php
}
else if($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="Student"){
?>
<a href="student-page.php"><i class="fa fa-home" > Home </i></a><br/>
<br/>
<strong> <i class="fa fa-check" > Accounts </i></strong><br/>
<a href="bills.php"><i class="fa fa-money" > Bills </i></a><br/>
<a href="account-statements.php"> <i class="fa fa-money" > Account Statements</i></a>

 <br/><br/>
<strong><i class="fa fa-check" > RECORDS </i></strong><br/>
<a href="registeredclass.php"> <i class="fa fa-folder-o" > Registered Class</i></a><br/>
<a href="registeredsubject.php"> <i class="fa fa-folder-o" > Registered Subject </i></a>

 <br/><br/>
<strong><i class="fa fa-check" > EXAMINATION </i></strong><br/>
<a href="examinationtimetablereport.php"><i class="fa fa-folder-o" > Exam Time Table Report</i></a><br/>
<a href="individual-terminal-report.php"><i class="fa fa-folder-o" > Examination Report</i></a><br/>
<?php
}
else if($_SESSION['ACCESSLEVEL']=="administrator" && $_SESSION['SYSTEMTYPE']=="super_user"){
 ?>
<a href="search.php" ><i  class="fa fa-search"> Search Student </i></a><br/>
<a  href="super.php"><i class="fa fa-home" > Home </i></a><br/>
<a href="company-entry.php"><i class="fa fa-plus" > School Entry </i></a><br/>
<a href="branch-entry.php"><i class="fa fa-plus" > Branch Entry </i></a><br/>
 <a href="batch-entry.php"><i class="fa fa-plus" > Batch Entry </i></a><br/>
<a href="subject-entry.php"><i class="fa fa-plus" > Subject Entry </i></a><br/>
<a href="class-entry.php"><i class="fa fa-plus" > Class Entry </i></a><br/>
<a href="school-data-entry.php"><i class="fa fa-plus" > School Data Entry </i></a><br/>
<br/>
<strong> <i class="fa fa-check" > ACCOUNTS </i></strong><br/>
<a href="item-entry.php"><i class="fa fa-plus"> Item Entry </i></a><br/>
<a href="item-pricing.php"><i class="fa fa-plus" > Item Pricing </i></a><br/>
<a href="class-billing.php"><i class="fa fa-plus" > Class Billing </i></a><br/>
<!--<a href="payments.php"><i class="fa fa-plus" ></i> Payments</a><br/>-->
<a href="daily-report.php"><i class="fa fa-book" > Daily Report </i></a><br/>
<a href="payment-analysis.php"><i class="fa fa-book" > Payment Report </i></a><br/>
<a href="bills-report.php"><i class="fa fa-book" > Bills Report </i></a><br/><br/>
 
<strong> <i class="fa fa-check"> BILLING </i></strong><br/>
 <a href="student-billing.php"><i class="fa fa-plus" > Bill Student </i></a><br/>
<!--<a href="rebill-student.php"><i class="fa fa-plus" ></i> Re-Bill Student</a><br/>-->
<a href="group-student-billing.php"><i class="fa fa-plus" > Bill Group Students </i></a><br/>
<a href="rebill-group-student.php"><i class="fa fa-plus" > Rebill Group Students </i></a><br/>
<a href="print-student-bills.php"><i class="fa fa-plus" > Print Student Bills </i></a><br/>
<br/>
<strong><i class="fa fa-check" > RECORDS </i></strong><br/>
<a href="class-registry.php"><i class="fa fa-plus" > Class Registry </i></a><br/>
<a href="upload-class-registry.php"><i class="fa fa-arrow-circle-up" >Upload Class Registry </i></a><br/>
<a href="view-class-registry.php"><i class="fa fa-arrow-circle-up" > View Class Registry </i></a><br/>

<hr>
<a href="term-registry.php"><i class="fa fa-plus" > Semester Registry </i></a><br/>
<a href="group-term-registry.php"><i class="fa fa-plus" > Group Semester Registry </i></a><br/>
<a href="upload-semester-registry.php"><i class="fa fa-arrow-circle-up" >Upload Semester Registry </i></a><br/>          
<a href="promotion-center.php"><i class="fa fa-level-up" > Promotion Center </i></a><br/>
<a href="student-history.php"><i class="fa fa-history" > Student History </i></a><br/>
<a href="continuing-students.php"><i class="fa fa-users" > Continuing Students </i></a><br/>
<br/>
<strong><i class="fa fa-check" > SUBJECT </i></strong><br/>
<div class="menu-inner">
<a href="subject-classification.php"><i class="fa fa-plus" > Subject Classification </i></a><br/>
<a href="view-subject-classified.php"><i class="fa fa-plus" > View Subject Classified </i></a><br/>
<a href="subject-assignment.php"><i class="fa fa-plus" > Subject Assignment </i></a><br/>
<a href="class-teacher-assignment.php"><i class="fa fa-plus" > Class Teacher Assignment </i></a><br/>
<a href="duty-roster.php"><i class="fa fa-calendar-check-o" > Duty Roster </i></a><br/>
<a href="online-admission-admin.php"><i class="fa fa-globe" > Online Admission </i></a><br/>
<a href="view-all-subject-assigned.php"><i class="fa fa-plus" > View Subject(s) Assigned </i></a><br/>
<br/>
<strong><i class="fa fa-check" > EXAMINATION </i></strong><br/>
<!--
<a href="all-class-score.php"><i class="fa fa-plus" ></i> Class Score</a><br/>
<a href="exam-score.php"><i class="fa fa-plus" ></i> Exam Score</a><br/>
<a href="student-terminal-data.php"><i class="fa fa-plus" ></i> Student Remark Data</a><br/>
<a href="upload-student-remark-data.php"><i class="fa fa-arrow-circle-up" ></i>Upload Students Remark Data</a><br/>
-->
<hr>
<a href="class-score-entry.php"><i class="fa fa-pencil" > Class Score Entry </i></a><br/>
<a href="exam-score-entry.php"><i class="fa fa-pencil" > Exam Score Entry </i></a><br/>
<a href="upload-class-score-entry.php"><i class="fa fa-arrow-circle-up" > Upload Class Score </i> </a><br/>
<a href="upload-exam-score-entry.php"><i class="fa fa-arrow-circle-up" > Upload Exam Score </i> </a><br/>
<a href="upload-classexam-score.php"><i class="fa fa-arrow-circle-up" > Upload Class & Exam Scores </i> </a><br/>

<hr>
<a href="scores-report.php"><i class="fa fa-book" > Scores Report </i></a><br/>
<a href="waec-analysis.php"><i class="fa fa-line-chart" > WAEC Analysis </i></a><br/>
<a href="terminal-report.php"><i class="fa fa-book" > Examination Report </i></a><br/>
<a href="internal-exam-analysis.php"><i class="fa fa-folder-o" > Internal Exams Analysis </i></a><br/>
<a href="examanalysis-subject.php"><i class="fa fa-folder-o" > Exam Analysis : Subject </i></a><br/>
<a href="examanalysis-rank.php"><i class="fa fa-folder-o" > Exam Analysis : Rank </i></a><br/>
<a href="enablesmsalert.php"><i class="fa fa-phone" > Enable SMS Alert </i></a><br/>
<a href="smsreport.php"><i class="fa fa-phone" > SMS Reporting </i></a><br/>
<a href="smsreportdata.php"><i class="fa fa-database" > SMS Data </i></a><br/>

<hr>
<a href="examinationtimetable.php"><i class="fa fa-plus" > Exam Time Table Entry </i></a><br/>
<a href="examinationtimetablereport.php"><i class="fa fa-book" > Exam Time Table Report </i></a><br/>
<br/>

<strong><i class="fa fa-check" > Notice </i></strong><br/>
<a href="notification.php"><i class="fa fa-plus" > Send Notification </i></a><br/>

<?php
}
else if($_SESSION['ACCESSLEVEL']=="administrator" && $_SESSION['SYSTEMTYPE']=="normal_user"){
 ?>
<a href="search.php" ><i  class="fa fa-search"> Search Student </i></a><br/>
<a href="admin.php"><i class="fa fa-home" > Home </i></a><br/>
<a href="company-entry.php"><i class="fa fa-plus" > School Entry </i></a><br/>
<a href="branch-entry.php"><i class="fa fa-plus" > Branch Entry </i></a><br/>
<a href="batch-entry.php"><i class="fa fa-plus" > Batch Entry </i></a><br/>
<a href="subject-entry.php"><i class="fa fa-plus" > Subject Entry </i></a><br/>
<a href="class-entry.php"><i class="fa fa-plus" > Class Entry</i></a><br/>
<a href="school-data-entry.php"><i class="fa fa-plus" > School Data Entry</i></a><br/>
<br/>
<strong> <i class="fa fa-check"> BILLING </i></strong><br/>
 <a href="student-billing.php"><i class="fa fa-plus" > Bill Student </i></a><br/>
<!--<a href="rebill-student.php"><i class="fa fa-plus" ></i> Re-Bill Student</a><br/>-->
<a href="group-student-billing.php"><i class="fa fa-plus" > Bill Group Students </i></a><br/>
<a href="rebill-group-student.php"><i class="fa fa-plus" > Rebill Group Students </i></a><br/>
<a href="print-student-bills.php"><i class="fa fa-plus" > Print Student Bills </i></a><br/>
<br/>
<strong> <i class="fa fa-check" > ACCOUNTS </i></strong><br/>
<a href="item-entry.php"><i class="fa fa-plus"> Item Entry </i></a><br/>
<a href="item-pricing.php"><i class="fa fa-plus" > Class Item Billing </i></a><br/>
<a href="class-billing.php"><i class="fa fa-plus" > Class Billing </i></a><br/>
<a href="class-Bills-report.php"><i class="fa fa-plus" > Class Bills Report </i></a><br/>
<!--<a href="payments.php"><i class="fa fa-plus" ></i> Payments</a><br/>-->
<a href="daily-report.php"><i class="fa fa-book" > Daily Report </i></a><br/>
<a href="payment-analysis.php"><i class="fa fa-book" > Payment Report </i></a><br/>
<a href="bills-report.php"><i class="fa fa-book" > Bills Report </i></a><br/>
<a href="item-bill-report.php"><i class="fa fa-book" > Item Bill Report </i></a><br/><br/>
 
<strong><i class="fa fa-check" > RECORDS </i></strong><br/>
<a href="class-registry.php"><i class="fa fa-plus" > Class Registry </i></a><br/>
<a href="upload-class-registry.php"><i class="fa fa-arrow-circle-up" > Upload Class Registry </i></a><br/>
<a href="view-class-registry.php"><i class="fa fa-arrow-circle-up" > View Class Registry </i></a><br/>

<hr>
<a href="term-registry.php"><i class="fa fa-plus" > Semester Registry </i></a><br/>
<a href="group-term-registry.php"><i class="fa fa-plus" > Group Semester Registry </i></a><br/>
<a href="upload-semester-registry.php"><i class="fa fa-arrow-circle-up" > Upload Semester Registry </i></a><br/>
<a href="promotion-center.php"><i class="fa fa-level-up" > Promotion Center </i></a><br/>
<a href="student-history.php"><i class="fa fa-history" > Student History </i></a><br/>
<a href="continuing-students.php"><i class="fa fa-users" > Continuing Students </i></a><br/>
<br/>
<strong><i class="fa fa-check" > SUBJECT </i></strong><br/>

<a href="subject-classification.php"><i class="fa fa-plus" > Subject Classification </i></a><br/>
<a href="view-subject-classified.php"><i class="fa fa-book" > View Subject Classified </i></a><br/>
<a href="subject-assignment.php"><i class="fa fa-plus" > Subject Assignment </i></a><br/>
<a href="class-teacher-assignment.php"><i class="fa fa-plus" > Class Teacher Assignment </i></a><br/>
<a href="duty-roster.php"><i class="fa fa-calendar-check-o" > Duty Roster </i></a><br/>
<a href="online-admission-admin.php"><i class="fa fa-globe" > Online Admission </i></a><br/>
<a href="view-all-subject-assigned.php"><i class="fa fa-book" > View Subject(s) Assigned </i></a><br/>
<br/>
<strong><i class="fa fa-check" > EXAMINATION </i></strong><br/>
<a href="student-terminal-data.php"><i class="fa fa-plus" > Student Remark Data </i></a><br/>
<a href="upload-student-remark-data.php"><i class="fa fa-arrow-circle-up" >Upload Students Remark Data </i></a><br/>
<a href="continuous-assessment.php"><i class="fa fa-folder-o" > Continuous Assessment </i></a><br/>
<a href="scores-report-all.php"><i class="fa fa-folder-o" > Scores Report </i></a><br/>
<a href="waec-analysis.php"><i class="fa fa-line-chart" > WAEC Analysis </i></a><br/>

<a href="terminal-report.php"><i class="fa fa-folder-o" > Examination Report </i></a><br/>
<a href="internal-exam-analysis.php"><i class="fa fa-folder-o" > Internal Exams Analysis </i></a><br/>
<a href="examanalysis-subject.php"><i class="fa fa-folder-o" > Exam Analysis : Subject </i></a><br/>
<a href="examanalysis-rank.php"><i class="fa fa-folder-o" > Exam Analysis : Rank </i></a><br/>

<hr>
<a href="enablesmsalert.php"><i class="fa fa-phone" > Enable SMS Alert </i></a><br/>

<a href="smsreport.php"><i class="fa fa-phone" > SMS Reporting </i></a><br/>
<a href="smsreportdata.php"><i class="fa fa-database" > SMS Data </i></a><br/>

<a href="examinationtimetable.php"><i class="fa fa-plus" > Exam Time Table Entry </i></a><br/>
<a href="examinationtimetablereport.php"><i class="fa fa-book" > Exam Time Table Report </i></a><br/>
<br/>
<strong><i class="fa fa-check" > Notice </i></strong><br/>
<a href="notification.php"><i class="fa fa-plus" > Send Notification </i></a><br/>
<?php

}
else if($_SESSION['ACCESSLEVEL']=="user" && $_SESSION['SYSTEMTYPE']=="User"){
 ?>
<a class="active" href="user.php"><i class="fa fa-home" > Home </i></a><br/>
<a href="batch-entry.php"><i class="fa fa-plus" > Batch Entry </i></a><br/>
<a href="subject-entry.php"><i class="fa fa-plus" > Subject Entry </i></a><br/>
<a href="class-entry.php"><i class="fa fa-plus" > Class Entry </i></a><br/>
<a href="school-data-entry.php"><i class="fa fa-plus" > School Data Entry </i></a><br/>
<br/>
<strong><i class="fa fa-check" > RECORDS </i></strong><br/>
<a href="class-registry.php"><i class="fa fa-plus" > Class Registry </i></a><br/>
<a href="upload-class-registry.php"><i class="fa fa-arrow-circle-up" > Upload Class Registry </i></a><br/>
<a href="view-class-registry.php"><i class="fa fa-arrow-circle-up" > View Class Registry </i></a><br/>

<hr>

<a href="term-registry.php"><i class="fa fa-plus" > Semester Registry </i></a><br/>
<a href="group-term-registry.php"><i class="fa fa-plus" > Group Semester Registry </i></a><br/>
<a href="upload-semester-registry.php"><i class="fa fa-arrow-circle-up" > Upload Semester Registry </i></a><br/>

<br/><strong><i class="fa fa-check" > TOOLS </i></strong><br/>
<a href="smsreport.php"><i class="fa fa-plus" > SMS Reporting </i></a><br/>
<a href="subject-classification.php"><i class="fa fa-plus" > Subject Classification </i></a><br/>
<a href="view-subject-classified.php"><i class="fa fa-plus" > View Subject Classified </i></a><br/>
<a href="subject-assignment.php"><i class="fa fa-plus" > Subject Assignment </i></a><br/>
<a href="view-all-subject-assigned.php"><i class="fa fa-plus" > View Subject(s) Assigned </i></a><br/>
<br/>
<strong><i class="fa fa-check" > EXAMINATION </i></strong><br/>
<a href="examinationtimetable.php"><i class="fa fa-plus" > Exam Time Table Entry </i></a><br/>
<a href="examinationtimetablereport.php"><i class="fa fa-book" > Exam Time Table Report </i></a><br/>
<a href="student-terminal-data.php"><i class="fa fa-plus" > Student Terminal Data </i></a><br/>
<a href="terminal-report.php"><i class="fa fa-book" > Examination Report </i></a><br/>
<a href="scores-report.php"><i class="fa fa-book" > Scores Report </i></a><br/>
<br/>
<strong><i class="fa fa-check" > Notice</strong><br/>
<a href="notification.php"><i class="fa fa-plus" > Send Notification </i></a><br/>
<?php
}
?>
</div>
