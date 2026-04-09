<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
@$_BatchId=$_POST['batchid'];
@$_Batch=$_POST['batch_month']." ".$_POST['batch_year'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_batch'])){
$_SQL_CHECK_BATCH=mysqli_query($con,"SELECT * FROM tblbatch WHERE batch='$_Batch'");
if(mysqli_num_rows($_SQL_CHECK_BATCH)>0){
	$_SESSION['Message']="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>Batch already exists.</div>";
}
else{
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblbatch(batchid,batch,datetimeentry,recordedby,status,branchid)
	VALUES('$_BatchId','$_Batch',NOW(),'$_Recordedby','active','$_SESSION[BRANCHID]')");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Batch Successfully Saved</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Batch failed to save,$_Error</div>";
	}
}
}
?>

<?php
include("dbstring.php");
@$_Update_Batch=$_POST['update_batch_month']." ".$_POST['update_batch_year'];
@$_update_batch_entryid=$_POST['update_batch_entryid'];

if(isset($_POST['update_batch_entry'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblbatch SET batch='$_Update_Batch' WHERE batchid='$_update_batch_entryid'");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Batch Successfully Updated</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Batch failed to update,$_Error</div>";
	}
}
?>




<?php
include("dbstring.php");

if(isset($_GET["delete_batch"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblbatch WHERE batchid='$_GET[delete_batch]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Batch Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Batch failed to delete,Error:$_Error</div>";
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
			
		<?php
		include("dbstring.php");
		if(isset($_GET['edit_batch']))
		{
		$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_GET[edit_batch]'");
		$_Count=mysqli_num_rows($_SQL_EXECUTE);
		if($_Count>0)
		{
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
			{
			echo "<h3>Batch Update</h3>";
			echo "<form method='post' id='formID' name='formID' action='batch-entry.php'>";
			echo "<label>Batch Id</label>";
			echo "<input type='text' id='update_batch_entryid' name='update_batch_entryid' value='$row[batchid]'><br/>";

			echo "<input type='text' id='update_batch' name='update_batch' value='$row[batch]' readonly><br/><br/>";

			echo "<fieldset><legend>BATCH</legend>";
			echo "<table>";
			echo "<tr>";
			echo "<td>";
			echo "<select id='update_batch_month' name='update_batch_month' class='validate[required]'>";
			echo "<option value=''>Select Month</option>";
			echo "<option value='January'>January</option>";
			echo "<option value='February'>February</option>";
			echo "<option value='March'>March</option>";
			echo "<option value='April'>April</option>";
			echo "<option value='May'>May</option>";
			echo "<option value='June'>June</option>";
			echo "<option value='July'>July</option>";
			echo "<option value='August'>August</option>";
			echo "<option value='September'>September</option>";
			echo "<option value='October'>October</option>";
			echo "<option value='November'>November</option>";
			echo "<option value='December'>December</option>";
			echo "</select>";
			echo "</td>";

			echo "<td>";
			$k=date("Y");
			$k=$k-5;
			$L=$k+15;
			echo "<select id='update_batch_year' name='update_batch_year' class='validate[required]'>";
			echo "<option value=''>Select Year</option>";
			while($k<=$L){
			echo "<option value='$k'>$k</option>";
			$k++;
			}
			echo "</select>";
			echo "</td>";
			echo "</tr>";
			echo "</table>";
			echo "</fieldset>";
			echo "<br/>";

			echo "<div align='center'><button class='button-edit' id='update_batch_entry' name='update_batch_entry'><i class='fa fa-edit' style='color:white'></i> UPDATE BATCH</button></div>";
			echo "<br/>";

			echo "</form>";
			}
		}
		}
		?>
	</div>

			<div class="form-entry" align="left">
			<h3>Batch Entry 
				</h3>
			<br/>
		
			<form method="post" id="formID" name="formID" action="batch-entry.php">

			<label>Batch Id</label><br/>
			<input type="text" id="batchid" name="batchid" value="<?php include("shortcode.php");echo $shortcode;?>" class="validate[required]" readonly/><br/><br/>

			<?php
			echo "<fieldset><legend>BATCH</legend>";
			echo "<table>";
			echo "<tr>";
			echo "<td>";
			echo "<select id='batch_month' name='batch_month' class='validate[required]'>";
			echo "<option value=''>Select Month</option>";
			echo "<option value='January'>January</option>";
			echo "<option value='February'>February</option>";
			echo "<option value='March'>March</option>";
			echo "<option value='April'>April</option>";
			echo "<option value='May'>May</option>";
			echo "<option value='June'>June</option>";
			echo "<option value='July'>July</option>";
			echo "<option value='August'>August</option>";
			echo "<option value='September'>September</option>";
			echo "<option value='October'>October</option>";
			echo "<option value='November'>November</option>";
			echo "<option value='December'>December</option>";
			echo "</select>";
			echo "</td>";

			echo "<td>";
			$k=date("Y");
			$k=$k-5;
			$L=$k+15;
			echo "<select id='batch_year' name='batch_year' class='validate[required]'>";
			echo "<option value=''>Select Year</option>";
			while($k<=$L){
			echo "<option value='$k'>$k</option>";
			$k++;
			}
			echo "</select>";
			echo "</td>";
			echo "</tr>";
			echo "</table>";
			echo "</fieldset>";
			?>
			<br/><br/>
			<div align="center"><button class="button-save" id="register_batch" name="register_batch"><i class="fa fa-save"></i> SAVE BATCH</button></div>
		</form>

		</div>
			</td>
			<td width="70%">
				<div class="form-entry" align="left">
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblbatch ORDER BY batch ASC");

				//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>BATCH</caption>";
				echo "<thead><th colspan=2>TASK</th><th>BATCH ID</th><th>BATCH</th><th>DATE/TIME</th><th>STATUS</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td align='center'><a title='Delete $row[batch] ($row[batchid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='batch-entry.php?delete_batch=$row[batchid]'<i class='fa fa-trash-o' style='color:red'></i></a></td>";
				echo "<td align='center'><a title='Edit $row[batch] ($row[batchid])'  href='batch-entry.php?edit_batch=$row[batchid]'<i class='fa fa-edit' style='color:olive'></i></a></td>";
				
				echo "<td align='center'>$row[batchid]</td>";
				echo "<td align='center'>$row[batch]</td>";
				echo "<td align='center'>$row[datetimeentry]</td>";
				//echo "<td>$row[recordedby]</td>";
				echo "<td align='center'>$row[status]</td>";
				echo "</tr>";
				}
				echo "</tbody>";
				echo "</table>";
				?>

			</div>
			</td>
		</tr>

	</table>
		
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
</body>
</html>
