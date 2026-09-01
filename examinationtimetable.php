<?php
session_start();
$_SESSION['Message']="";
include("check-login.php");
?>


<?php
include("dbstring.php");
include_once("exam-timetable-utils.php");
if(!function_exists('exam_timetable_ensure_invigilator_column')){
function exam_timetable_ensure_invigilator_column($con)
{
    $column = @mysqli_query($con,"SHOW COLUMNS FROM tbltimetable LIKE 'invigilators'");
    if(!$column || mysqli_num_rows($column) === 0){
        @mysqli_query($con,"ALTER TABLE tbltimetable ADD COLUMN invigilators VARCHAR(255) NOT NULL DEFAULT '' AFTER subjectid");
    }
}
}
exam_timetable_ensure_invigilator_column($con);
exam_timetable_ensure_tables($con);
//@$_ClassId=$_POST['classid'];
@$_SubjectId=$_POST['subjectid'];
@$_ClassId=$_POST['class'];
@$_Batch=$_POST['batch'];
@$_Term=$_POST['term'];
$_ExamType=exam_timetable_normalize_exam_type(isset($_POST['examtype']) ? $_POST['examtype'] : '');
$_ExamTypeSafe=mysqli_real_escape_string($con,$_ExamType);
@$_StartTime=$_POST['starttime'];
@$_EndTime=$_POST['endtime'];
@$_Tabledate=$_POST['timetabledate'];
$_InvigilatorsSafe=mysqli_real_escape_string($con,trim((string)($_POST['invigilators'] ?? '')));
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['save_timetable']))
{
//Create payment container
include("shortcode.php");
@$_TimeId=$shortcode;
@$_Transaction_Code=$transaction_id;
@$_TransId=0;

$_SQL_Time=mysqli_query($con,"INSERT INTO tbltimetable(timeid,subjectid,examtype,invigilators,tablestarttime,tableendtime,tabledate,class_entryid,termname,batchid,recordedby,status)
	VALUES('$_TimeId','$_SubjectId','$_ExamTypeSafe','$_InvigilatorsSafe','$_StartTime','$_EndTime',STR_TO_DATE('$_Tabledate','%d-%m-%Y'),'$_ClassId','$_Term','$_Batch','$_SESSION[USERID]','active')");
if($_SQL_Time){
exam_timetable_replace_invigilators($con,$_TimeId,isset($_POST['invigilatorids']) ? $_POST['invigilatorids'] : array());
$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:white;padding:5px;'><i class='fa fa-check' style='color:green'></i> Time Table Successfully Saved</div>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>No Time Table saved,$_Error</div>";
}
}
?>

<?php
include("dbstring.php");
//@$_ClassId=$_POST['classid'];
@$_SubjectId=$_POST['subjectid'];
@$_ClassId=$_POST['class'];
@$_Batch=$_POST['batch'];
@$_Term=$_POST['term'];
$_ExamType=exam_timetable_normalize_exam_type(isset($_POST['examtype']) ? $_POST['examtype'] : '');
$_ExamTypeSafe=mysqli_real_escape_string($con,$_ExamType);
@$_StartTime=$_POST['starttime'];
@$_EndTime=$_POST['endtime'];
@$_Tabledate=$_POST['timetabledate'];
$_InvigilatorsSafe=mysqli_real_escape_string($con,trim((string)($_POST['invigilators'] ?? '')));
@$_Recordedby=$_SESSION['USERID'];
@$_TimeId=$_POST["timeid"];

if(isset($_POST['update_timetable']))
{
$_TimeId=mysqli_real_escape_string($con,trim((string)$_TimeId));

$_SQL_Time=mysqli_query($con,"UPDATE tbltimetable SET subjectid='$_SubjectId',tablestarttime='$_StartTime',
	tableendtime='$_EndTime',tabledate=STR_TO_DATE('$_Tabledate','%d-%m-%Y'),class_entryid='$_ClassId'
	,termname='$_Term',batchid='$_Batch',examtype='$_ExamTypeSafe',invigilators='$_InvigilatorsSafe' WHERE timeid='$_TimeId'");
if($_SQL_Time){
exam_timetable_replace_invigilators($con,$_TimeId,isset($_POST['invigilatorids']) ? $_POST['invigilatorids'] : array());
$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:#efe;padding:5px;border:1px solid #4a4;'><i class='fa fa-check' style='color:green'></i> Time Table Successfully Updated</div>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>No Time Table update,$_Error</div>";
}
}
?>

<?php
include("dbstring.php");
if(isset($_GET["delete_timetable"]))
{
$_SQLDelete=mysqli_query($con,"DELETE FROM tbltimetable WHERE timeid='$_GET[delete_timetable]'");
if($_SQLDelete){

	}
}
?>

<html>
<head>
<?php
include("links.php");
?>
<link rel="stylesheet" href="css/examinationtimetable.css">
<link rel="stylesheet" href="css/invigilator-picker.css">
<link rel="stylesheet" href="css/exam-timetable-page-modern.css">
<link rel="stylesheet" href="css/examinationtimetablereport.css">
<link rel="stylesheet" href="css/exam-timetable-manager.css">

<script>
  var rnd;
function getItemID()
{
rnd=Math.floor( Math.random()*100000000);
document.getElementById("item-id").value=rnd;
}
</script>

<script type="text/javascript">
var gbatch;
function getBatch()
{
gbatch=getElementById("batch").value;
 //return _batch;  
}
function getStudentBill(str)
{
	if(str=="")
  {
  
  document.getElementById("search-result").innerHTML="";
  return;
  }
  else
  {
    if(window.XMLHttpRequest)
    {
      xmlhttp = new XMLHttpRequest();
    }
    else
    {
      xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange = function()
    {
      if(this.readyState==4 && this.status==200)
      {
        document.getElementById("search-result").innerHTML = this.responseText;
      }
    };
    xmlhttp.open("GET","display-class-bill.php?search-bill="+str+"&batch="+gbatch,true);
    xmlhttp.send();
  }
}
</script>
<script>
function filterInvigilatorChecklist(searchInput, checklistId) {
    var term = (searchInput.value || '').toLowerCase().trim();
    var options = document.querySelectorAll('#' + checklistId + ' [data-invigilator-option]');
    for (var i = 0; i < options.length; i++) {
        options[i].style.display = options[i].getAttribute('data-invigilator-name').indexOf(term) !== -1 ? '' : 'none';
    }
}
</script>
</head>

<body class="exam-timetable-page">
	<div class="header">
<?php
include("menu.php");
?>		
</div>
<main class="main-platform exam-timetable-report-page exam-timetable-manager-page">
<section class="exam-timetable-report-hero">
    <div><span class="exam-timetable-report-kicker">Examination timetable</span><h1>Examination Timetable</h1><p>Create and manage examination schedules, invigilators, dates, and sessions from one place.</p><div class="exam-timetable-report-page-actions"><button type="button" class="exam-timetable-report-print-page" onclick="window.print()"><i class="fa fa-print"></i> Print This Page</button></div></div>
    <div class="exam-timetable-report-hero-card"><i class="fa fa-calendar"></i><span>Timetable Planner</span><small>Keep every examination day clear and organised.</small></div>
</section>
<div class="exam-timetable-report-layout exam-timetable-manager-layout">
<aside class="exam-timetable-report-panel exam-timetable-report-filter-panel exam-timetable-manager-entry">
	<!--UPDATE EXAMINATINO TIME TABLE-->
<?php
if(isset($_GET["edit_timeid"])){
$_EditTimeId=mysqli_real_escape_string($con,$_GET["edit_timeid"]);
$_EditTimetable=mysqli_fetch_array(mysqli_query($con,"SELECT * FROM tbltimetable WHERE timeid='$_EditTimeId' LIMIT 1"),MYSQLI_ASSOC);
$_EditInvigilatorIds=exam_timetable_selected_invigilator_ids($con,$_EditTimeId);
?>
	<div class="form-entry" align="left">
<h3>EXAMINATION TIME TABLE UPDATE
</h3>
<br/>
<form method="post" id="formID2" name="formID2" action="examinationtimetable.php">
<input type="hidden" id="timeid" name="timeid" value="<?php echo $_GET["edit_timeid"];?>" />
<?php	
$_SQL_2=mysqli_query($con,"SELECT * FROM tblsubject");
echo "<select id='subjectid' name='subjectid' class='validate[required]'>";
echo "<option value=''>Select Subject</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
echo "<option value='$row[subjectid]'>$row[subject]</option>";
}
echo "</select><br/><br/>";
?>
<?php	
$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry");
echo "<select id='class' name='class' class='validate[required]'>";
echo "<option value=''>Select Class</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
}
echo "</select><br/><br/>";
?>
<select id="term" name="term" class="validate[required]">
<option value="" >Select Semester</option>
<option value="1">1</option>
<option value="2">2</option>
</select><br/><br/>

<label for="examtype">Examination Type</label>
<select id="examtype" name="examtype" class="validate[required]"><?php foreach(exam_timetable_exam_types() as $_ExamTypeOption){ ?><option value="<?php echo htmlspecialchars($_ExamTypeOption,ENT_QUOTES,'UTF-8'); ?>"<?php echo (($_EditTimetable['examtype'] ?? 'End of Semester Examination') === $_ExamTypeOption) ? ' selected' : ''; ?>><?php echo htmlspecialchars($_ExamTypeOption,ENT_QUOTES,'UTF-8'); ?></option><?php } ?></select><br/><br/>

<?php	
$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");
echo "<select id='batch' name='batch' class='validate[required]'>";
echo "<option value=''>Select Batch</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
echo "<option value='$row[batchid]'>$row[batch]</option>";
}
echo "</select><br/><br/>";
?>
<label>Start Time</label><br/>
<input type="time" id="starttime" name="starttime" value=""/>
<br/><br/>
<label>End Time</label><br/>
<input type="time" id="endtime" name="endtime" value=""/>
			<br/><br/>
			<label for="invigilator_search_edit">Invigilator(s)</label><br/>
			<input type="text" id="invigilator_search_edit" class="invigilator-search" placeholder="Search a teacher by name" oninput="filterInvigilatorChecklist(this,'invigilator_checklist_edit')" autocomplete="off" />
			<div class="invigilator-checklist" id="invigilator_checklist_edit">
			<?php foreach(exam_timetable_teacher_list($con) as $_Teacher){ $_TeacherName=htmlspecialchars($_Teacher['teacher_name'],ENT_QUOTES,'UTF-8'); ?><label data-invigilator-option data-invigilator-name="<?php echo strtolower($_TeacherName); ?>"><input type="checkbox" name="invigilatorids[]" value="<?php echo htmlspecialchars($_Teacher['userid'],ENT_QUOTES,'UTF-8'); ?>"<?php echo isset($_EditInvigilatorIds[(string)$_Teacher['userid']]) ? ' checked' : ''; ?>><span><?php echo $_TeacherName; ?></span></label><?php } ?>
			</div><small>Tick every teacher assigned to supervise this paper.</small>
			<br/><br/>


			<label>Date</label><br/>

			<script type="text/javascript">
            function show_alert()
            {
               alert("Please select Date Time Picker");
            }
            </script>
            <script src="scripts/datetimepicker_css.js"></script>

        <?php
         $tomorrow = mktime(0,0,0,date("m")+1,date("d"),date("Y"));
          $tdate= date("d/m/Y", $tomorrow);
         ?>
      <input type="hidden" name="todate" id="todate" value="<?php echo $tdate; ?>">
      <input type="text" maxlength="25" size="25" onclick="javascript:NewCssCal ('timetabledate','ddMMyyyy','','','','','')" id="timetabledate" name="timetabledate" value="" class="validate[required]" readonly   onchange="CheckDateOfBirth()"/>
      <br/><br/>
			
<div align="center"><button class="button-edit" id="update_timetable" name="update_timetable"><i class="fa fa-edit"></i> UPDATE TIME TABLE</button></div>
		</form>

		</div>
<?php
}
?>
<!--ADD NEW EXAMINATION TIME TABLE-->
<div class="form-entry" align="left">
<h3>EXAMINATION TIME TABLE ENTRY
</h3>
<br/>
<form method="post" id="formID" name="formID" action="examinationtimetable.php">
<?php	
$_SQL_2=mysqli_query($con,"SELECT * FROM tblsubject");
echo "<select id='subjectid' name='subjectid' class='validate[required]'>";
echo "<option value=''>Select Subject</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
echo "<option value='$row[subjectid]'>$row[subject]</option>";
}
echo "</select><br/><br/>";
?>
<?php	
$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry");
echo "<select id='class' name='class' class='validate[required]'>";
echo "<option value=''>Select Class</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
}
echo "</select><br/><br/>";
?>
<select id="term" name="term" class="validate[required]">
<option value="" >Select Semester</option>
<option value="1">1</option>
<option value="2">2</option>
</select><br/><br/>

<label for="examtype_new">Examination Type</label>
<select id="examtype_new" name="examtype" class="validate[required]"><?php foreach(exam_timetable_exam_types() as $_ExamTypeOption){ ?><option value="<?php echo htmlspecialchars($_ExamTypeOption,ENT_QUOTES,'UTF-8'); ?>"><?php echo htmlspecialchars($_ExamTypeOption,ENT_QUOTES,'UTF-8'); ?></option><?php } ?></select><br/><br/>

<?php	
$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");
echo "<select id='batch' name='batch' class='validate[required]'>";
echo "<option value=''>Select Batch</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
echo "<option value='$row[batchid]'>$row[batch]</option>";
}
echo "</select><br/><br/>";
?>
<label>Start Time</label><br/>
<input type="time" id="starttime" name="starttime" value=""/>
<br/><br/>
<label>End Time</label><br/>
<input type="time" id="endtime" name="endtime" value=""/>
			<br/><br/>
			<label for="invigilator_search_new">Invigilator(s)</label><br/>
			<input type="text" id="invigilator_search_new" class="invigilator-search" placeholder="Search a teacher by name" oninput="filterInvigilatorChecklist(this,'invigilator_checklist_new')" autocomplete="off" />
			<div class="invigilator-checklist" id="invigilator_checklist_new">
			<?php foreach(exam_timetable_teacher_list($con) as $_Teacher){ $_TeacherName=htmlspecialchars($_Teacher['teacher_name'],ENT_QUOTES,'UTF-8'); ?><label data-invigilator-option data-invigilator-name="<?php echo strtolower($_TeacherName); ?>"><input type="checkbox" name="invigilatorids[]" value="<?php echo htmlspecialchars($_Teacher['userid'],ENT_QUOTES,'UTF-8'); ?>"><span><?php echo $_TeacherName; ?></span></label><?php } ?>
			</div><small>Tick every teacher assigned to supervise this paper.</small>
			<br/><br/>


			<label>Date</label><br/>

			<script type="text/javascript">
            function show_alert()
            {
               alert("Please select Date Time Picker");
            }
            </script>
            <script src="scripts/datetimepicker_css.js"></script>

        <?php
         $tomorrow = mktime(0,0,0,date("m")+1,date("d"),date("Y"));
          $tdate= date("d/m/Y", $tomorrow);
         ?>
      <input type="hidden" name="todate" id="todate" value="<?php echo $tdate; ?>">
      <input type="text" maxlength="25" size="25" onclick="javascript:NewCssCal ('timetabledate','ddMMyyyy','','','','','')" id="timetabledate" name="timetabledate" value="" class="validate[required]" readonly   onchange="CheckDateOfBirth()"/>
      <br/><br/>
			
<div align="center"><button class="button-save" id="save_timetable" name="save_timetable"><i class="fa fa-save"></i> SAVE TIME TABLE</button></div>
		</form>

		</div>
</aside>
<section class="exam-timetable-report-panel exam-timetable-report-list-panel exam-timetable-manager-list">
<div class="form-entry exam-timetable-list-card">
<?php
echo $_SESSION["Message"];
?>
	<?php
	include("dbstring.php");
	$_SQL=mysqli_query($con,"SELECT tt.*,ce.class_name,bch.batch,sub.subject,exam_invigilators.selected_invigilators FROM tbltimetable tt INNER JOIN tblclassentry ce ON tt.class_entryid=ce.class_entryid
	INNER JOIN tblbatch bch ON tt.batchid=bch.batchid INNER JOIN tblsubject sub ON tt.subjectid=sub.subjectid ".exam_timetable_invigilator_subquery());
	echo "<table style='background-color:white'>";
	echo "<caption>EXAMINATION TIME TABLE</caption>";
	echo "<thead><th colspan='2'>TASK</th><th>*</th><th>TIME ID</th><th>EXAM TYPE</th><th>SUBJECT</th><th>INVIGILATOR(S)</th><th>CLASS</th><th>SEM.</th><th>BATCH</th><th>START TIME</th><th>END START</th><th>DATE</th></thead>";
	echo "<tbody>";
	@$serial=0;
	while($row=mysqli_fetch_array($_SQL,MYSQLI_ASSOC)){
	echo "<tr>";
	//echo "<td align='center'><a title='View $row[subject]' href='examinationtimetable.php?view_user=$row[timeid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
	echo "<td align='center'><a title='Delete $row[subject]' onclick=\"javascript:return confirm('Do you want to delete?');\" href='examinationtimetable.php?delete_timetable=$row[timeid]'<i class='fa fa-trash-o' style='color:red'></i></a></td>";
	echo "<td align='center'><a title='Edit Exam Time for $row[subject]' href='examinationtimetable.php?edit_timeid=$row[timeid]'<i class='fa fa-edit' style='color:green'></i></a></td>";
				


	echo "<td align='center'>";
	echo $serial=$serial+1;
	echo "</td>";
	
	echo "<td>";
	echo $row['timeid'];
	echo "</td>";
	echo "<td>";
	echo htmlspecialchars(exam_timetable_normalize_exam_type($row['examtype'] ?? ''),ENT_QUOTES,'UTF-8');
	echo "</td>";

	echo "<td>";
	echo $row['subject'];
	echo "</td>";
	echo "<td>";
	echo htmlspecialchars(exam_timetable_display_invigilators($row),ENT_QUOTES,'UTF-8');
	echo "</td>";
	echo "<td align='center'>";
	echo $row['class_name'];
	echo "</td>";
	
	echo "<td align='center'>";
	echo $row['termname'];
	echo "</td>";

	echo "<td align='center'>";
	echo $row['batch'];
	echo "</td>";

	echo "<td align='center'>";
	echo $row['tablestarttime'];
	echo "</td>";
	
	echo "<td align='center'>";
	echo $row['tableendtime'];
	echo "</td>";
	
	echo "<td align='center'>";
	echo $row['tabledate'];
	echo "</td>";
	echo "</tr>";
	}
	?>
</div>
</section>
</div>

<br/><br/>
<button onclick="topFunction()" id="myBtn" title="Go to top">Top</button> 

 <script>
//Get the button
var mybutton = document.getElementById("myBtn");

// When the user scrolls down 20px from the top of the document, show the button
window.onscroll = function() {scrollFunction()};

function scrollFunction() {
  if (document.body.scrollTop > 50 || document.documentElement.scrollTop > 50) {
    mybutton.style.display = "block";
  } else {
    mybutton.style.display = "none";
  }
}

// When the user clicks on the button, scroll to the top of the document
function topFunction() {
  document.body.scrollTop = 0;
  document.documentElement.scrollTop = 0;
}
</script>
</main>
</body>
</html>
