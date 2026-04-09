<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
@$_class_entryid=$_POST['class_entryid'];
@$_className=$_POST['class'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_class'])){
//Check duplicate entry
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblclassentry(class_entryid,class_name,datetimeentry,recordedby,status,branchid)
	VALUES('$_class_entryid','$_className',NOW(),'$_Recordedby','active','$_SESSION[BRANCHID]')");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>$_className Successfully Added</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Class failed to save,$_Error</div>";
	}
}
?>

<?php
include("dbstring.php");
@$_Update_className=$_POST['update_class'];
@$_Update_class_entryid=$_POST['update_class_entryid'];

if(isset($_POST['update_class_entry'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblclassentry SET class_name='$_Update_className' WHERE class_entryid='$_Update_class_entryid'");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Class Successfully Updated</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>class failed to update,$_Error</div>";
	}
}
?>
<?php
include("dbstring.php");

if(isset($_GET["delete_class"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblclassentry WHERE class_entryid='$_GET[delete_class]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Class Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>class failed to delete,Error:$_Error</div>";
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
		if(isset($_GET['edit_class']))
		{
		$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$_GET[edit_class]'");
		$_Count=mysqli_num_rows($_SQL_EXECUTE);
		if($_Count>0)
		{
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
			{
			echo "<h3>class Update</h3>";
			echo "<form method='post' id='formID' name='formID' action='class-entry.php'>";
			echo "<label>class Id</label>";
			echo "<input type='text' id='update_class_entryid' name='update_class_entryid' value='$row[class_entryid]' readonly><br/>";

			echo "<label>class </label>";
			echo "<input type='text' id='update_class' name='update_class' value='$row[class_name]'><br/><br/>";
			echo "<div align='center'><button class='button-edit' id='update_class_entry' name='update_class_entry'><i class='fa fa-edit'></i> UPDATE CLASS</button></div>";

			echo "</form>";
			}
		}
		}
		?>
			<h3>Class Entry 
				</h3>
			<br/>
		
			<form method="post" id="formID" name="formID" action="class-entry.php">
			<label>Class Id</label><br/>
			<input type="text" id="class_entryid" name="class_entryid" value="<?php include("shortcode.php");echo $shortcode;?>" class="validate[required]" readonly/><br/><br/>
			<fieldset><legend>Class</legend>
			<input type="text" id="class" name="class" value="" class="validate[required]"/><br/><br/>
			</fieldset><br/>
			<div align="center"><button class="button-save" id="register_class" name="register_class"><i class="fa fa-save"></i> SAVE CLASS</button></div>
		</form>

		</div>
			</td>
			<td width="70%">
				<div class="form-entry" align="left">
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblclassentry ORDER BY class_name ASC");

				//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>List Of Classes</caption>";
				echo "<thead><th colspan=2>TASK</th><th>CLASS ID</th><th>CLASS</th><th>ENTRY DATE/TIME</th><th>STATUS</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td align='center'><a title='Delete $row[class_name] ($row[class_entryid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='class-entry.php?delete_class=$row[class_entryid]'<i class='fa fa-trash-o' style='color:red'></i></a></td>";
				echo "<td align='center'><a title='Edit $row[class_name] ($row[class_entryid])'  href='class-entry.php?edit_class=$row[class_entryid]'<i class='fa fa-edit' style='color:olive'></i></a></td>";
				
				echo "<td>$row[class_entryid]</td>";
				echo "<td>$row[class_name]</td>";
				echo "<td align='center'>$row[datetimeentry]</td>";
				//echo "<td>$row[recordedby]</td>";
				echo "<td align='center'>$row[status]</td>";
				echo "</tr>";
				}
				echo "</tbody>";
				echo "</table>";
				?>
			</td>
		</tr>
	</table>
</div>
</body>
</html>