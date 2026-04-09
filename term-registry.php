<?php
session_start();
$_SESSION['Message']="";
?>

<?php
include("dbstring.php");
@$_ClassId=$_POST['classid'];
@$_TermId=$_POST['termid'];
@$_UserId=$_POST['userid'];
@$_Term=$_POST['term'];
@$_BatchId=$_POST['batchid'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_term'])){
if($_UserId=="" || $_ClassId=="" || $_Term=="" || $_BatchId==""){
	$_SESSION['Message']="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>Missing required values. Select student, class, semester and batch again.</div>";
}
else{
$_SQL_CLASS_EXISTS=mysqli_query($con,"SELECT class_entryid FROM tblclassentry WHERE class_entryid='$_ClassId' LIMIT 1");
if(mysqli_num_rows($_SQL_CLASS_EXISTS)==0){
	$_SESSION['Message']="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>Invalid class selected. Reload and choose the student from the list again.</div>";
}
else{
$_SQL_CHECK_CLASS=mysqli_query($con,"SELECT classid FROM tblclass WHERE userid='$_UserId' AND class_entryid='$_ClassId' AND batchid='$_BatchId' AND status='active' LIMIT 1");
if(mysqli_num_rows($_SQL_CHECK_CLASS)==0){
	$_SESSION['Message']="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>Student is not in an active class for the selected batch. Open the student from this page list and retry.</div>";
}
else{
$_SQL_CHECK=mysqli_query($con,"SELECT * FROM tbltermregistry WHERE userid='$_UserId' AND class_entryid='$_ClassId' AND termname='$_Term' AND batchid='$_BatchId'");
if(mysqli_num_rows($_SQL_CHECK)>0){
	@$_BatchName="";
	$_SQL_BATCH=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_BatchId'");
	if($row_ba=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC)){
		$_BatchName=$row_ba['batch'];
	}
$_SESSION['Message']="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>Student has already registered for Semester $_Term or Batch: $_BatchName already created</div>";
}
else{
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tbltermregistry(termid,userid,class_entryid,termname,batchid,status,datetimeentry,recordedby)
	VALUES('$_TermId','$_UserId','$_ClassId','$_Term','$_BatchId','active',NOW(),'$_Recordedby')");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;padding:5px;text-align:center;border:1px solid #aea;background-color:#efe;'>Semester Successfully Registered</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>Semester failed to save: $_Error</div>";
	}
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
		$_SESSION['Message']="<div style='color:red;text-align:center'>Term failed to delete,Error:$_Error</div>";
	}
}
?>

<html>
<head>
<?php
include("links.php");
?>
</head>
<body>
	<div class="header">
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
			<td valign="top" width="30%" align="center">
				<div class="form-entry" align="left">
			<h3>Semester Registration 
				</h3>
		
			<form method="post" id="formID" name="formID" action="term-registry.php">

			<label>Semester Id</label><br/>
			<input type="text" id="termid" name="termid" value="<?php include("shortcode.php");echo $shortcode;?>" class="validate[required]" readonly/><br/><br/>

			<fieldset><legend>STUDENT NAME</legend>
			<?php
			if(isset($_GET['view_user'])){
				@$Class_ID=$_GET['class_id'];
				//echo $Class_ID;
			@$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE userid='$_GET[view_user]'");
			
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
			$_FullName=$row['firstname']." ".$row['surname']." ".$row['othernames']."(".$row['userid'].")";
			echo "<input type='text' id='firstname' name='firstname' value='$_FullName' class='validate[required]' readonly/><br/><br/>";
			echo "<input type='hidden' id='userid' name='userid' value='$row[userid]' class='validate[required]' readonly/>";
			echo "<input type='hidden' id='classid' name='classid' value='$Class_ID' class='validate[required]' readonly/>";
			
			$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$Class_ID'");
			if($row_cl=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){
			//echo "Class: ". $row_cl['class_name'];
			echo "<input type='text' id='class_1' name='class_1' value='$row_cl[class_name]' readonly/>";
			
			}


			}
			}
			?>
			
			</fieldset><br/>

				<select id="term" name="term">
					<option value="" class="validate[required]">Select Semester</option>
					<option value="1">1</option>
					<option value="2">2</option>
				</select>
				<br/><br/>

				<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch ORDER BY datetimeentry DESC");

			echo "<select id='batchid' name='batchid' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[batchid]'>$row[batch]</option>";
				}
				
			echo "</select><br/><br/>";
			?>
<div align="center"><button class="button-save" id="register_term" name="register_term"><i class="fa fa-save"></i> SAVE TERM</button></div>
</form>
</div>
</td>
<td width="70%">
<div class="form-entry" align="left">
	<form id="formID" name="formID" method="post">
<?php	
include("dbstring.php");
echo "<fieldset><legend>FIND STUDENTS</legend>";		
echo "<table>";
echo "<tr>";
echo "<td>";
$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry");

echo "<select id='class_entryid' name='class_entryid' class='validate[required]'>";
echo "<option value=''>Select Class</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
}
echo "</select>";
echo "</td>";
echo "<td>";
$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch ORDER BY datetimeentry DESC");

echo "<select id='batchid' name='batchid' class='validate[required]'>";
echo "<option value=''>Select Batch</option>";
while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
echo "<option value='$row[batchid]'>$row[batch]</option>";
}
echo "</select>";
echo "</td>";
echo "<td>";
echo "<button class='button-show' id='show_semester' name='show_semester'><i class='fa fa-search' style='color:white'></i> SHOW</button> ";
echo "</td>";
echo "</tr>";
echo "</table>";
echo "</fieldset>";
?>
</form>


<?php
echo $_SESSION['Message'];
include("dbstring.php");
@$_Class_EntryId=$_POST["class_entryid"];
@$_Batch_Id=$_POST["batchid"];

if(isset($_POST["show_semester"]))
{
//$_SQL_SU=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE systemtype='Student'");
$_SQL_SU=mysqli_query($con,"SELECT * FROM tblsystemuser su INNER JOIN tblclass cl ON su.userid=cl.userid 
				WHERE cl.batchid='$_Batch_Id' AND cl.class_entryid='$_Class_EntryId' AND cl.status='active' AND su.systemtype='Student' ORDER BY su.firstname ASC");

if(mysqli_num_rows($_SQL_SU)>0){
//Registered clients
echo "<table width='100%' style='background-color:white'>";
echo "<caption>Semester Registration </caption>";
echo "<thead><th>*</th><th colspan=1>TASK</th><th>CLASS</th><th>SEM.</th><th>BATCH</th><th>ENTRY DATE/TIME</th></thead>";
echo "<tbody>";
@$serial=0;
	while($row_c=mysqli_fetch_array($_SQL_SU,MYSQLI_ASSOC)){
				echo "<tr style='background-color:#ffffff'>";

				echo "<td align='center'>";
				echo $serial=$serial+1 .".";
				echo "</td>";

				//echo "<td align='center'><a title='View $row_c[firstname] ($row_c[userid])' href='term-registry.php?view_user=$row_c[userid]&class_id=$row_c[class_entryid]'><i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td colspan='4'>$row_c[firstname] $row_c[othernames] $row_c[surname] ($row_c[userid])</td>";
				echo "<td align='center'></td>";
				
				//echo "<td align='center'>$row[class_name]</td>";
				//echo "<td align='center'>$row[batch]</td>";
				//echo "<td align='center'>$row[datetimeentry]</td>";
				
				echo "</tr>";

				

				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblclass cl 
					INNER JOIN tblclassentry ce ON cl.class_entryid=ce.class_entryid 
					WHERE cl.userid='$row_c[userid]' AND cl.class_entryid='$_Class_EntryId' AND cl.batchid='$_Batch_Id' AND cl.status='active' ORDER BY ce.class_name ASC");

				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				echo "<td></td>";
				echo "<td align='center'><a title='View ($row[class_name])' href='term-registry.php?view_user=$row[userid]&class_id=$row[class_entryid]'><i class='fa fa-plus' style='color:royalblue'></i></a></td>";
				
				//echo "<td>$row[firstname] $row[othernames] $row[surname] ($row[userid])</td>";
				//echo "<td align='center'></td>";
				//echo "<td align='center'></td>";
				
				echo "<td align='center'>$row[class_name]</td>";
				echo "<td align='center'></td>";
				
				echo "</tr>";


				$_SQL_TERM=mysqli_query($con,"SELECT *,tr.datetimeentry FROM tbltermregistry tr 
				INNER JOIN tblbatch b ON tr.batchid=b.batchid
				WHERE tr.userid='$row[userid]' AND tr.class_entryid='$row[class_entryid]'AND tr.batchid='$_Batch_Id' ORDER BY tr.termname ASC");
				while($row_tr=mysqli_fetch_array($_SQL_TERM,MYSQLI_ASSOC)){
				echo "<tr style='background-color:#ffffff;border-bottom:1px solid gray'>";
				echo "<td></td>";
				echo "<td align='center'><a onclick=\"javascript:return confirm('Do you want to remove term?')\" title='Remove term $row_tr[termname]' href='term-registry.php?delete_term=$row_tr[termid]'<i class='fa fa-trash-o' style='color:red'></i></a></td>";
				

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
}
?>
</td>
</tr>
</table>
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

</div>

<!--Footer-->

</body>
</html>
