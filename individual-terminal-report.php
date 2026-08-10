<?php
session_start();
$_SESSION['Message']="";
include("positions.php");
include("class-position.php");
include_once("dbstring.php");
include_once("semester-registry-utils.php");
include_once("report-approval-utils.php");
include_once("result-access-utils.php");
include_once("school-data-utils.php");
include_once("terminal-report-pdf-utils.php");
semester_registry_ensure_academic_year_column($con);

@$_position_obj=new Position;
@$_position_obj_1=new Position;
@$_class_position_obj=new ClassPosition;

if(!function_exists('itr_esc')){
    function itr_esc($value){
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
?>

<?php
//Declare the variables
@$_UserID=$_SESSION['USERID'];

//@$todayTime =$_POST['today_time2'];
@$_BatchId=$_POST['batchid'];
@$_AcademicYear=trim((string)$_POST['academicyear']);
@$_TermId=$_POST['termid'];
@$_ClassId=$_POST['classid'];
@$_ReportApprovalMessage="";

if(isset($_POST["print_terminal_report"]))
{
      $_ReportApprovalMeta = report_approval_scope_meta($con, $_BatchId, $_AcademicYear, $_TermId, $_ClassId);
      $_ResultAccessMeta = result_access_student_allowed($con, $_UserID, $_BatchId, $_AcademicYear, $_TermId, $_ClassId);
      if(report_approval_is_student_user() && !$_ResultAccessMeta['allowed']){
          $_scope=$_ResultAccessMeta['scope']; $_note=trim((string)(isset($_scope['note'])?$_scope['note']:''));
          $_ReportApprovalMessage = "<div style='margin:12px 0;padding:12px 14px;border-radius:14px;background:#fff7ed;border:1px solid rgba(194,65,12,0.14);color:#c2410c;font-weight:600;'>Result viewing for this semester is controlled by the school.".($_note!==''?' '.itr_esc($_note):'')."</div>";
          if(isset($_scope['mode']) && $_scope['mode']==='payment' && (float)$_scope['amount']>0){
              $_ReportApprovalMessage.="<form method='post' action='result-access-paystack-init.php' style='margin:12px 0'><input type='hidden' name='batchid' value='".itr_esc($_BatchId)."'><input type='hidden' name='academicyear' value='".itr_esc($_AcademicYear)."'><input type='hidden' name='termid' value='".itr_esc($_TermId)."'><input type='hidden' name='classid' value='".itr_esc($_ClassId)."'><button type='submit' style='padding:11px 16px;border:0;border-radius:9px;background:#087443;color:#fff;font-weight:700;cursor:pointer'>Pay GHS ".number_format((float)$_scope['amount'],2)." to view this result</button></form>";
          }
      }elseif(report_approval_is_student_user() && $_ReportApprovalMeta['required'] && !$_ReportApprovalMeta['allowed']){
          $_ReportApprovalMessage = "<div style='margin:12px 0;padding:12px 14px;border-radius:14px;background:#fff7ed;border:1px solid rgba(194,65,12,0.14);color:#c2410c;font-weight:600;'>This report is not yet available. Please wait for approval.</div>";
      }else{
          $_PrintResult = tr_terminal_report_print_single_pdf($con, $_UserID, $_BatchId, $_AcademicYear, $_TermId, $_ClassId);
          if(empty($_PrintResult['success'])){
              $_ReportApprovalMessage = "<div style='margin:12px 0;padding:12px 14px;border-radius:14px;background:#fff7ed;border:1px solid rgba(194,65,12,0.14);color:#c2410c;font-weight:600;'>".itr_esc((string)$_PrintResult['message'])."</div>";
          }
      }
}
?>




<?php
include("dbstring.php");
@$_Mark=$_POST['marks'];
@$_AssignmentId=$_POST['assignmentid'];
@$_UserId=$_POST['userid'];
@$_TotalMark=$_POST['totalscore'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['save_all_mark']))
{
	@$_CheckMark=0;
	foreach ($_Mark as $_Selected_Mark) 
	{
		if($_Selected_Mark>$_TotalMark){
			$_CheckMark=1;
		}
	}
//Check if mark entered is more than the total mark
if($_CheckMark==1){
$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;padding:10px;background-color:white;'>Total Mark is less than the mark entered</div>";
}else/*No mark is greater than the total mark*/
{

$_TotalUsers =count($_UserId);

for($k=0;$k<$_TotalUsers;$k++)
{
$_Selected_User=$_UserId[$k];
$_Selected_Mark=$_Mark[$k];

		include("code.php");
	@$_MarkId=$code;
	@$_UserFullname="";

	$_SQL_EXECUTE_USER_2=mysqli_query($con,"SELECT * FROM tblsystemuser su  WHERE su.userid='$_Selected_User'");
		
		if($row_u_2=mysqli_fetch_array($_SQL_EXECUTE_USER_2,MYSQLI_ASSOC)){
		$_UserFullname=$row_u_2['firstname']." ".$row_u_2['othernames']." ".$row_u_2['surname']." (".$row_u_2['userid'].")";
		}

	//@$_Subject="";
	//Check if subject already registered
	/*$_SQL_EXECUTE_SUBJECT=mysqli_query($con,"SELECT * FROM tblsubject sub INNER JOIN tblsubjectclassification sc ON sub.subjectid=sc.subjectid WHERE sc.classificationid='$_Selected_ClassId'");
	if($row_s=mysqli_fetch_array($_SQL_EXECUTE_SUBJECT,MYSQLI_ASSOC)){
	$_Subject=$row_s['subject'];
	$_ClassId=$row_s['classid'];
	//@$_getUser_ID=$row_s['userid'];

	}
	*/

	/*$_SQL_EXECUTE_USER=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa INNER JOIN tblsystemuser su ON sa.userid=su.userid WHERE sa.classificationid='$_Selected_ClassId'");
	if(!mysqli_num_rows($_SQL_EXECUTE_USER)>0){
		$_SQL_EXECUTE_USER_2=mysqli_query($con,"SELECT * FROM tblsystemuser su  WHERE su.userid='$_UserId'");
		
		if($row_u_2=mysqli_fetch_array($_SQL_EXECUTE_USER_2,MYSQLI_ASSOC)){
		$_UserFullname=$row_u_2['firstname']." ".$row_u_2['othernames']." ".$row_u_2['surname']." (".$row_u_2['userid'].")";
		}

	}else{
		if($row_u=mysqli_fetch_array($_SQL_EXECUTE_USER,MYSQLI_ASSOC)){
		$_UserFullname=$row_u['firstname']." ".$row_u['othernames']." ".$row_u['surname']." (".$row_u['userid'].")";
		}
	}
	*/

	//$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa WHERE sa.classificationid='$_Selected_ClassId' AND sa.userid='$_UserId' AND sa.classid='$_ClassId'");
	/*$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tblsubjectassignment sa WHERE sa.classificationid='$_Selected_ClassId'");
	
	if(mysqli_num_rows($_SQL_EXECUTE_2)>0){
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;text-align:left;background-color:white'><i class='fa fa-check' style='color:red'></i> $_Subject Already Assigned To $_UserFullname</div>";
		
	}else{
		*/

		$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblmark(markid,assignmentid,userid,testtype,mark,totalmark,datetimeentry,status,recordedby)
		VALUES('$_MarkId','$_AssignmentId','$_Selected_User','Class Score','$_Selected_Mark','$_TotalMark',NOW(),'active','$_Recordedby')");
			if($_SQL_EXECUTE)
			{
		
			$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:white'><i class='fa fa-check' style='color:green'></i> $_Selected_Mark Successfully Stored for $_UserFullname</div>";
			}
			else{
				$_Error=mysqli_error($con);
				$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>Mark failed to save,$_Error</div>";
			}
	}
	}	
	
}
?>

<?php
include("dbstring.php");
@$_Update_subject=$_POST['update_item'];
@$_Update_subjectid=$_POST['update_subjectid'];

if(isset($_POST['update_item_entry'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsubject SET subject='$_Update_subject' WHERE subjectid='$_Update_subjectid'");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Subject Successfully Updated</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Subject failed to update,$_Error</div>";
	}
}
?>


<?php
include("dbstring.php");

if(isset($_GET["delete_mark"]))
{
    $_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblmark WHERE markid='$_GET[delete_mark]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Mark Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Mark failed to delete,Error:$_Error</div>";
	}
}
?>



<?php
$_SelectedBatchId = isset($_POST['batchid']) ? trim((string)$_POST['batchid']) : '';
$_SelectedAcademicYear = isset($_POST['academicyear']) ? trim((string)$_POST['academicyear']) : '';
$_SelectedTermId = isset($_POST['termid']) ? trim((string)$_POST['termid']) : '';
$_SelectedClassId = isset($_POST['classid']) ? trim((string)$_POST['classid']) : '';
$_SelectedTermLabel = '';
$_SelectedClassLabel = '';
$_SelectedBatchLabel = '';
$_BatchOptions = array();
$_AcademicYearOptions = array();
$_ClassOptions = array();
$_SelectedScopeApprovalMeta = report_approval_scope_meta($con, $_SelectedBatchId, $_SelectedAcademicYear, $_SelectedTermId, $_SelectedClassId);
$_DisableStudentPrint = report_approval_is_student_user() && $_SelectedScopeApprovalMeta['required'] && !$_SelectedScopeApprovalMeta['allowed'];

$_SQL_BATCH_OPTIONS = mysqli_query($con, "SELECT batchid,batch FROM tblbatch ORDER BY datetimeentry DESC");
if($_SQL_BATCH_OPTIONS){
    while($rowBatch = mysqli_fetch_array($_SQL_BATCH_OPTIONS, MYSQLI_ASSOC)){
        $_BatchOptions[] = $rowBatch;
        if($_SelectedBatchId !== '' && $_SelectedBatchId === (string)$rowBatch['batchid']){
            $_SelectedBatchLabel = $rowBatch['batch'];
        }
    }
}

$_YearWhereSql = "";
if($_SelectedBatchId !== ''){
    $_SelectedBatchIdEsc = mysqli_real_escape_string($con, $_SelectedBatchId);
    $_YearWhereSql = " WHERE batchid='$_SelectedBatchIdEsc' ";
}
$_SQL_YEAR_OPT = mysqli_query($con, "
    SELECT DISTINCT academic_year FROM (
        SELECT CASE
            WHEN TRIM(COALESCE(academicyear,''))<>'' THEN academicyear
            ELSE YEAR(datetimeentry)
        END AS academic_year
        FROM tblschoolinfo
        $_YearWhereSql
        UNION
        SELECT YEAR(datetimeentry) AS academic_year
        FROM tblsubjectassignment
        $_YearWhereSql
    ) year_options
    WHERE academic_year IS NOT NULL AND academic_year<>''
    ORDER BY academic_year DESC
");
if($_SQL_YEAR_OPT){
    while($rowYear = mysqli_fetch_array($_SQL_YEAR_OPT, MYSQLI_ASSOC)){
        $_AcademicYearOptions[] = (string)$rowYear['academic_year'];
    }
}

if($_SelectedBatchId !== ''){
    $_SQL_CLASS_OPT = mysqli_query($con, "SELECT DISTINCT ce.class_entryid,ce.class_name
        FROM tbltermregistry tr
        INNER JOIN tblclassentry ce ON tr.class_entryid=ce.class_entryid
        WHERE tr.userid='$_SESSION[USERID]'
          AND tr.batchid='".mysqli_real_escape_string($con, $_SelectedBatchId)."'
          ".($_SelectedAcademicYear !== "" ? " AND ".semester_registry_resolved_year_sql("tr")."='".mysqli_real_escape_string($con, $_SelectedAcademicYear)."'" : "")."
        ORDER BY ce.class_name ASC");
}else{
    $_SQL_CLASS_OPT = mysqli_query($con, "SELECT class_entryid,class_name FROM tblclassentry ORDER BY class_name ASC");
}
if($_SQL_CLASS_OPT){
    while($rowClass = mysqli_fetch_array($_SQL_CLASS_OPT, MYSQLI_ASSOC)){
        $_ClassOptions[] = $rowClass;
        if($_SelectedClassId !== '' && $_SelectedClassId === (string)$rowClass['class_entryid']){
            $_SelectedClassLabel = $rowClass['class_name'];
        }
    }
}

if($_SelectedTermId !== ""){
    $_SelectedTermLabel = ($_SelectedAcademicYear !== "" ? $_SelectedAcademicYear." | " : "")."Semester ".$_SelectedTermId;
}

$_ItrFlashMessage = trim((string)$_SESSION['Message']);
$_ItrScopeSelected = ($_SelectedBatchId !== '' || $_SelectedAcademicYear !== '' || $_SelectedTermId !== '' || $_SelectedClassId !== '');
$_ItrStatusTone = 'neutral';
$_ItrStatusLabel = 'Choose a report scope to continue.';
if($_SelectedClassId !== '' && $_SelectedTermId !== '' && $_SelectedAcademicYear !== ''){
    if($_SelectedScopeApprovalMeta['required']){
        $_ItrStatusTone = $_SelectedScopeApprovalMeta['allowed'] ? 'approved' : 'pending';
        $_ItrStatusLabel = $_SelectedScopeApprovalMeta['status_label'];
    }else{
        $_ItrStatusTone = 'info';
        $_ItrStatusLabel = 'This report can be printed once the marks and remarks are ready.';
    }
}elseif($_ItrScopeSelected){
    $_ItrStatusTone = 'info';
    $_ItrStatusLabel = 'Finish selecting your academic year, semester, and class to print the report.';
}
?>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/individual-terminal-report.css">
</head>
<body class="itr-page">
	<div class="header">
	<?php include("menu.php"); ?>
	</div>

    <main class="itr-shell">
        <?php if($_ItrFlashMessage !== ''){ ?>
        <div class="itr-flash"><?php echo $_SESSION['Message']; ?></div>
        <?php } ?>

        <section class="itr-hero">
            <div class="itr-hero__copy">
                <span class="itr-kicker">Student Results</span>
                <h1>Individual Terminal Report</h1>
                <p>Select the academic year, semester, and class to print your terminal report.</p>
            </div>
            <aside class="itr-hero-card">
                <span class="itr-hero-card__label">Status</span>
                <strong><?php echo itr_esc($_ItrStatusLabel); ?></strong>
                <small><?php echo $_DisableStudentPrint ? 'Printing is locked until approval is complete.' : 'Print becomes available as soon as the selected scope is ready.'; ?></small>
            </aside>
        </section>

        <div class="itr-layout">
            <section class="itr-panel itr-panel--form">
                <div class="itr-panel__heading">
                    <div class="itr-panel__icon"><i class="fa fa-filter"></i></div>
                    <div>
                        <h2>Select Report</h2>
                        <p>Select the class report you want to print.</p>
                    </div>
                </div>

                <form id="formID" name="formID" method="post" action="individual-terminal-report.php" class="itr-form">
                    <div class="itr-fieldset">
                        <div class="itr-field">
                            <label for="batchid">Batch</label>
                            <select id="batchid" name="batchid" class="validate[required]">
                                <option value="">Select Academic Year (Batch)</option>
                                <?php foreach($_BatchOptions as $rowBatch){ ?>
                                <option value="<?php echo itr_esc($rowBatch['batchid']); ?>"<?php echo ($_SelectedBatchId === (string)$rowBatch['batchid'] ? ' selected' : ''); ?>><?php echo itr_esc($rowBatch['batch']); ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="itr-field">
                            <label for="academicyear">Academic Year</label>
                            <select id="academicyear" name="academicyear" class="validate[required]">
                                <option value="">Select Academic Year</option>
                                <?php foreach($_AcademicYearOptions as $academicYearOption){ ?>
                                <option value="<?php echo itr_esc($academicYearOption); ?>"<?php echo ($_SelectedAcademicYear === (string)$academicYearOption ? ' selected' : ''); ?>><?php echo itr_esc($academicYearOption); ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="itr-field">
                            <label for="termid">Semester</label>
                            <select id="termid" name="termid" class="validate[required]">
                                <option value="">Select Semester</option>
                                <option value="1"<?php echo ($_SelectedTermId === '1' ? ' selected' : ''); ?>>1</option>
                                <option value="2"<?php echo ($_SelectedTermId === '2' ? ' selected' : ''); ?>>2</option>
                                <option value="3"<?php echo ($_SelectedTermId === '3' ? ' selected' : ''); ?>>3</option>
                            </select>
                        </div>

                        <div class="itr-field">
                            <label for="classid">Class</label>
                            <select id="classid" name="classid" class="validate[required]">
                                <option value="">Select Class</option>
                                <?php foreach($_ClassOptions as $rowClass){ ?>
                                <option value="<?php echo itr_esc($rowClass['class_entryid']); ?>"<?php echo ($_SelectedClassId === (string)$rowClass['class_entryid'] ? ' selected' : ''); ?>><?php echo itr_esc($rowClass['class_name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="itr-status-card itr-status-card--<?php echo itr_esc($_ItrStatusTone); ?>">
                        <i class="fa fa-info-circle"></i>
                        <span><?php echo itr_esc($_ItrStatusLabel); ?></span>
                    </div>

                    <?php if($_SelectedTermLabel !== ''){ ?>
                    <div class="itr-selected-scope">
                        <i class="fa fa-check-circle"></i>
                        <span>Selected: <?php echo itr_esc($_SelectedTermLabel); ?><?php echo ($_SelectedClassLabel !== '' ? ' | Class: '.itr_esc($_SelectedClassLabel) : ''); ?></span>
                    </div>
                    <?php } ?>

                    <?php if($_ReportApprovalMessage !== ''){ ?>
                    <div class="itr-approval-note"><?php echo $_ReportApprovalMessage; ?></div>
                    <?php } ?>

                    <div class="itr-actions">
                        <button class="itr-btn itr-btn--primary" id="print_terminal_report" name="print_terminal_report" <?php echo $_DisableStudentPrint ? 'disabled' : ''; ?>>
                            <i class="fa fa-print"></i>
                            <?php echo $_DisableStudentPrint ? 'Awaiting Approval' : 'Print Report'; ?>
                        </button>
                    </div>
                </form>
            </section>

            <section class="itr-panel itr-panel--info">
                <div class="itr-panel__heading">
                    <div class="itr-panel__icon itr-panel__icon--alt"><i class="fa fa-file-text-o"></i></div>
                    <div>
                        <h2>Report Summary</h2>
                        <p>Check the selected report before printing.</p>
                    </div>
                </div>

                <div class="itr-summary-grid">
                    <article class="itr-summary-card">
                        <span>Batch</span>
                        <strong><?php echo itr_esc($_SelectedBatchLabel !== '' ? $_SelectedBatchLabel : 'Not selected'); ?></strong>
                    </article>
                    <article class="itr-summary-card">
                        <span>Academic Year</span>
                        <strong><?php echo itr_esc($_SelectedAcademicYear !== '' ? $_SelectedAcademicYear : 'Not selected'); ?></strong>
                    </article>
                    <article class="itr-summary-card">
                        <span>Semester</span>
                        <strong><?php echo itr_esc($_SelectedTermId !== '' ? 'Semester '.$_SelectedTermId : 'Not selected'); ?></strong>
                    </article>
                    <article class="itr-summary-card">
                        <span>Class</span>
                        <strong><?php echo itr_esc($_SelectedClassLabel !== '' ? $_SelectedClassLabel : 'Not selected'); ?></strong>
                    </article>
                </div>

                <div class="itr-guide-card">
                    <h3>Report Details</h3>
                    <p>The report includes subject scores, class position, attendance, house details, conduct, promotion remarks, and the school sign-off section.</p>
                </div>

                <div class="itr-guide-card">
                    <h3>Before Printing</h3>
                    <ul class="itr-checklist">
                        <li>Make sure you selected the correct academic year and semester.</li>
                        <li>Confirm the class matches the period you want to print.</li>
                        <li>If approval is required, wait until the report status changes to approved.</li>
                    </ul>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
