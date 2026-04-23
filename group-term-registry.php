<?php
session_start();
$_SESSION['Message']="";
?>

<?php
include("dbstring.php");
include_once("semester-registry-utils.php");
semester_registry_ensure_academic_year_column($con);
@$_ClassId=$_POST['classid'];
//@$_TermId=$_POST['termid'];
@$_UserId=$_POST['userid'];
@$_Term=$_POST['term'];
@$_BatchId=$_POST['batchid'];
@$_AcademicYear=semester_registry_normalize_year($_POST['academicyear'] ?? '');
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_term'])){
if(!$_UserId)
{
$_SESSION['Message']="<div style='color:red'>No user selected</div>";
}
elseif($_ClassId=="" || $_Term=="" || $_BatchId=="" || $_AcademicYear=="")
{
$_SESSION['Message']="<div style='color:red'>Select class, semester, batch, and academic year before saving.</div>";
}
else{
foreach($_UserId as $selecteduser){
include("code.php");
@$_TermId=$code;
//Get individual student id
$_UserId=$selecteduser;

$_AcademicYearSafe=mysqli_real_escape_string($con,$_AcademicYear);
$_ResolvedYearSql = semester_registry_resolved_year_sql("tbltermregistry");
$_SQL_CHECK=mysqli_query($con,"SELECT * FROM tbltermregistry WHERE userid='$_UserId' AND class_entryid='$_ClassId' AND termname='$_Term' AND batchid='$_BatchId' AND $_ResolvedYearSql='$_AcademicYearSafe'");
if(mysqli_num_rows($_SQL_CHECK)>0){
	@$_BatchName="";
	$_SQL_BATCH=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_BatchId'");
	if($row_ba=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC)){
		$_BatchName=$row_ba['batch'];
	}
$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>Student has already registered for Academic Year $_AcademicYear, Semester $_Term, Batch: $_BatchName.</div>";	
}
else{
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tbltermregistry(termid,userid,class_entryid,termname,batchid,academicyear,status,datetimeentry,recordedby)
	VALUES('$_TermId','$_UserId','$_ClassId','$_Term','$_BatchId','$_AcademicYearSafe','active',NOW(),'$_Recordedby')");
if($_SQL_EXECUTE){
@$_FullName="";
$_SQL_USER=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE userid='$_UserId'");
if($row_us=mysqli_fetch_array($_SQL_USER,MYSQLI_ASSOC)){
	$_FullName=$row_us['firstname']." ".$row_us['othernames']." ".$row_us['surname'];
}
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:center;background-color:white'>Semester Successfully Registered for $_FullName</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>Semester failed to register,$_Error</div>";
	}
}
}
}
}
?>

<?php
include("dbstring.php");
if(isset($_GET["delete_term"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tbltermregistry WHERE termid='$_GET[delete_term]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Semester Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Semester failed to delete,Error:$_Error</div>";
	}
}
?>

<html>
<head>
<?php
include("links.php");
?>

<script type="text/javascript">
function selectAll(){
  var selall = document.getElementById("all").checked;
  if(selall==true){
    checkBox();
  }
  else if(selall==false){
    uncheckBox();
  }  
 }
 

 function uncheckBox(){
   var inputs = document.getElementsByName("userid[]");
    for(var i=0;i<inputs.length;i++){
     inputs[i].checked=false;
    }
     return false;
 }

  function checkBox(){
var inputs = document.getElementsByName("userid[]");
   
    for(var i=0;i<inputs.length;i++){
     inputs[i].checked=true;
    }
 return false;
 }
</script>

</head>
<body>
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");
	?>		
	<?php
	//include("side-menu.php");
	?>
	</div>
<div class="main-platform" style="">
	<table width="100%">
		<tr>
			<td valign="top" width="40%" align="center">
				<?php
				echo $_SESSION['Message'];
				?>
<form method="post" id="formID" name="formID" action="group-term-registry.php">
<div class="form-entry" align="left">
	<fieldset><legend>SEARCH STUDENTS</legend>
	<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry");

			echo "<select id='classentryid' name='classentryid' class='validate[required]'>";
			echo "<option value=''>Select Class</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
				}
				
			echo "</select><br/><br/>";
			?>		
				<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");

			echo "<select id='batchid2' name='batchid2' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[batchid]'>$row[batch]</option>";
				}
				
			echo "</select>";
			?><br/><br/>
			<button class="button-show" name="searchstudent"><i class="fa fa-search"></i> SEARCH STUDENT</button>
</fieldset><br/><br/>
</form>
<form method="post" id="formID" name="formID" action="group-term-registry.php">

			<?php	
			if(isset($_POST["searchstudent"])){
				@$_ClassentryID=$_POST["classentryid"];
				@$_BatchID=$_POST["batchid2"];

				@$_Batch="";
				@$_ClassName="";
				$_SQL_CL=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$_ClassentryID'");
				if($rowcl=mysqli_fetch_array($_SQL_CL)){
					$_ClassName=$rowcl["class_name"];
				}
				$_SQL_BH=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_BatchID'");
				if($rowb=mysqli_fetch_array($_SQL_BH)){
					$_Batch=$rowb["batch"];
				}


			$_SQL_2=mysqli_query($con,"SELECT * FROM tblsystemuser su INNER JOIN tblclass cl
			ON su.userid=cl.userid
			 WHERE cl.class_entryid='$_ClassentryID' AND cl.batchid='$_BatchID' 
			 AND cl.status='active'
			 AND su.systemtype='Student' ORDER BY su.userid ASC");
			echo "<table>";
			echo "<caption>List of $_ClassName Students, Batch: $_Batch</th>";
			echo "<thead><th><input type='checkbox' id='all' name='all' Onclick='selectAll()' /></th><th>*</th><th>STUDENT</th></thead>";
			echo "<tbody>";
			@$serial=0;
			while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC))
			{
			echo "<tr class='light'>";
			echo "<td>";
			echo "<input type='checkbox' id='userid' name='userid[]' value='$row[userid]' />";
			echo "</td>";
			echo "<td>";
			echo $serial=$serial+1;
			echo "</td>";
			
			echo "<td>";
			echo $row['firstname']." ". $row['othernames']." ". $row['surname']."(".$row['userid'].")";
			echo "</td>";
			echo "</tr>";
			}	
			echo "</tbody>";
			echo "</table>";
		}
			?>
			</div>			
		</div>
			</td>
			<td width="20%">
			<div class="form-entry" align="left">
			<h3>Group Semester Registration 
				</h3>
				<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry");

			echo "<select id='classid' name='classid' class='validate[required]'>";
			echo "<option value=''>Select Class</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
				}
				
			echo "</select><br/><br/>";
			?>		
				<select id="term" name="term">
					<option value="" class="validate[required]">Select Semester</option>
					<option value="1" <?php echo (($_POST['term'] ?? '')==='1' ? 'selected' : ''); ?>>1</option>
					<option value="2" <?php echo (($_POST['term'] ?? '')==='2' ? 'selected' : ''); ?>>2</option>
				</select>
				<br/><br/>

				<select id="academicyear" name="academicyear" class="validate[required]">
					<option value="">Select Academic Year</option>
					<?php
					foreach(semester_registry_year_options() as $yearOption){
						$_Selected = ($_AcademicYear === $yearOption) ? "selected" : "";
						echo "<option value='$yearOption' $_Selected>$yearOption</option>";
					}
					?>
				</select>
				<br/><br/>

				<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");

			echo "<select id='batchid' name='batchid' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					$_SelectedBatch = (($_POST['batchid'] ?? '')==$row['batchid']) ? "selected" : "";
					echo "<option value='$row[batchid]' $_SelectedBatch>$row[batch]</option>";
				}
				
			echo "</select><br/><br/>";
			?>

			<div align="center"><button class="button-save" id="register_term" name="register_term"><i class="fa fa-save"></i> SAVE SEMESTER</button></div>
		</form>

		</div>
			</td>

<!--			<td width="40%">
				<?php
				
				include("dbstring.php");
				

	$_SQL_SU=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE systemtype='Student'");
	if(mysqli_num_rows($_SQL_SU)>0){


				//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<thead><th colspan=1>Task</th><th>Class</th><th>Semester</th><th>Batch</th><th>Entry Date/Time</th></thead>";
				echo "<tbody>";
	while($row_c=mysqli_fetch_array($_SQL_SU,MYSQLI_ASSOC)){
				echo "<tr style='background-color:#fef'>";
				//echo "<td align='center'><a title='View $row_c[firstname] ($row_c[userid])' href='group-term-registry.php?view_user=$row_c[userid]&class_id=$row_c[class_entryid]'><i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td colspan='4'>$row_c[firstname] $row_c[othernames] $row_c[surname] ($row_c[userid])</td>";
				echo "<td align='center'></td>";
				
				//echo "<td align='center'>$row[class_name]</td>";
				//echo "<td align='center'>$row[batch]</td>";
				//echo "<td align='center'>$row[datetimeentry]</td>";
				
				echo "</tr>";

				

				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblclass cl 
					INNER JOIN tblclassentry ce ON cl.class_entryid=ce.class_entryid 
					WHERE cl.userid='$row_c[userid]' ORDER BY ce.class_name ASC");

				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				echo "<td align='center'><a title='View ($row[class_name])' href='group-term-registry.php?view_user=$row[userid]&class_id=$row[class_entryid]'><i class='fa fa-book' style='color:blue'></i></a></td>";
				
				//echo "<td>$row[firstname] $row[othernames] $row[surname] ($row[userid])</td>";
				//echo "<td align='center'></td>";
				//echo "<td align='center'></td>";
				
				echo "<td align='center' colspan='3'>$row[class_name]</td>";
				echo "<td align='center'></td>";
				
				echo "</tr>";


				$_SQL_TERM=mysqli_query($con,"SELECT * FROM tbltermregistry tr 
				INNER JOIN tblbatch b ON tr.batchid=b.batchid
				WHERE tr.userid='$row[userid]' AND tr.class_entryid='$row[class_entryid]' ORDER BY tr.termname ASC");
				while($row_tr=mysqli_fetch_array($_SQL_TERM,MYSQLI_ASSOC)){
				echo "<tr style='background-color:#eee'>";
				echo "<td align='center'><a onclick=\"javascript:return confirm('Do you want to remove term?')\" title='Remove term $row_tr[termname]' href='group-term-registry.php?delete_term=$row_tr[termid]'<i class='fa fa-trash-o' style='color:red'></i></a></td>";
				

				echo "<td colspan='1' align='right'>";
				//echo "Term:";
				echo "</td>";
				echo "<td align='center'>";
				echo $row_tr['termname'];
				echo "</td>";
				echo "<td align='center'>$row_tr[batch]</td>";
				echo "<td align='center'>";
				echo $row_tr['datetimeentry'];
				echo "</td>";


				echo "</tr>";
				}	

				}
			}
				echo "</tbody>";
				echo "</table>";
}
				?>
			</td>
		</tr>

	</table>
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
</script>-->
</div>
</body>
</html>
