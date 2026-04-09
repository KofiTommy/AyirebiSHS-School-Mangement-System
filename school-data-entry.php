<?php
session_start();
$_SESSION['Message']="";
?>

<?php
include("dbstring.php");
@$_ClassId=$_POST['classid'];
@$_Infoid=$_POST['infoid'];
@$_SchoolCloses=date("Y-m-d",strtotime($_POST['schoolcloses-date']));
@$_SchoolResumes=date("Y-m-d",strtotime($_POST['schoolresumes']));

//echo $_SchoolCloses ."<br/>";
//echo $_SchoolResumes;

@$_Term=$_POST['term'];
@$_BatchId=$_POST['batchid'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_school_data'])){
$_SQL_CHECK=mysqli_query($con,"SELECT * FROM tblschoolinfo WHERE batchid='$_BatchId'");
if(mysqli_num_rows($_SQL_CHECK)>0){
	@$_BatchName="";
	$_SQL_BATCH=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_BatchId'");
	if($row_ba=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC)){
		$_BatchName=$row_ba['batch'];
	}
$_SESSION['Message']="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>School has already saved for semester $_Term - Batch: $_BatchName</div>";	
}
else{
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblschoolinfo(infoid,batchid,termname,schoolcloses,schoolresumes,datetimeentry,status,recordedby)
	VALUES('$_Infoid','$_BatchId','$_Term',STR_TO_DATE('$_SchoolCloses','%Y-%m-%d'),STR_TO_DATE('$_SchoolResumes','%Y-%m-%d'),NOW(),'active','$_Recordedby')");
if($_SQL_EXECUTE){
	//$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>School Information Successfully Saved</div>";
	$_SESSION['Message']="<div style='color:green;padding:5px;text-align:center;border:1px solid #aea;background-color:#efe;'>School Information Successfully Saved</div>";	

	}
	else{
		$_Error=mysqli_error($con);
		//$_SESSION['Message']="<div style='color:red'>School Information failed to saved,$_Error</div>";
		$_SESSION['Message']="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>School Information failed to save</div>";	

	}
}
}
?>

<?php
include("dbstring.php");
if(isset($_GET["delete_school"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblschoolinfo WHERE infoid='$_GET[delete_school]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>School Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>School failed to delete,Error:$_Error</div>";
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
			<td valign="top" width="30%" align="center">
				<div class="form-entry" align="left">
			
			<h3>School Data Entry 
				</h3>
		
			<form method="post" id="formID" name="formID" action="school-data-entry.php">

			<label>School Data Id</label><br/>
			<input type="text" id="infoid" name="infoid" value="<?php include("shortcode.php");echo $shortcode;?>" class="validate[required]" readonly/><br/><br/>


				<select id="term" name="term">
					<option value="" class="validate[required]">Select Semester</option>
					<option value="1">1</option>
					<option value="2">2</option>
					<!--<option value="3">3</option>-->
				</select>
				<br/><br/>

<script type="text/javascript">
function show_alert(){
 alert("Please select Date Time Picker");
 }
</script>
<script src="scripts/datetimepicker_css.js"></script>

        <?php
         $tomorrow = mktime(0,0,0,date("m")+1,date("d"),date("Y"));
          $tdate= date("d/m/Y", $tomorrow);
         ?>
      <input type="hidden" name="todate" id="todate" value="<?php echo $tdate; ?>">


				<label>School Closes</label><br/>
				<input type="text" maxlength="25" size="25" onclick="javascript:NewCssCal ('schoolcloses-date','ddMMyyyy','','','','','')" id="schoolcloses-date" name="schoolcloses-date" value="" readonly   onchange="CheckSchoolCloses()"/>
				<br/><br/>
     
	<label>Next Semester Begins</label><br/>
				<input type="text" maxlength="25" size="25" onclick="javascript:NewCssCal ('schoolresumes','ddMMyyyy','','','','','')" id="schoolresumes" name="schoolresumes" value="" readonly   onchange="CheckSchoolResume()" />
				<br/><br/>
     		

				<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");

			echo "<select id='batchid' name='batchid' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[batchid]'>$row[batch]</option>";
				}
				
			echo "</select><br/><br/>";
			?>

			<div align="center"><button class="button-save" id="register_school_data" name="register_school_data"><i class="fa fa-save"></i> SAVE DATA</button></div>
		</form>

		</div>
			</td>
<td width="70%">
<div class="form-entry" align="left">
<?php
echo $_SESSION['Message'];
include("dbstring.php");

$_SQL_SU=mysqli_query($con,"SELECT * FROM tblschoolinfo si 
INNER JOIN tblbatch bh ON si.batchid=bh.batchid");
if(mysqli_num_rows($_SQL_SU)>0){
				//Registered clients
echo "<table width='100%' style='background-color:white'>";
echo "<caption>School Data</caption>";
echo "<thead><th colspan=1>Task</th><th>Batch</th><th>Semester</th><th>School Closes</th><th>Next Semester Begins</th><th>Date/Time</th></thead>";
echo "<tbody>";
while($row=mysqli_fetch_array($_SQL_SU,MYSQLI_ASSOC)){
echo "<tr>";
echo "<td align='center'><a title='Delete ($row[batch])' href='school-data-entry.php?delete_school=$row[infoid]'><i class='fa fa-trash-o' style='color:red'></i></a></td>";
echo "<td align='center'>$row[batch]</td>";
echo "<td align='center'>$row[termname]</td>";				
echo "<td align='center'>";
echo $row['schoolcloses'];
echo "</td>";
echo "<td align='center'>$row[schoolresumes]</td>";
echo "<td align='center'>";
echo $row['datetimeentry'];
echo "</td>";
echo "</tr>";
}
echo "</tbody>";
echo "</table>";
}
?>
</div>
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
</script>
</div>
</body>
</html>