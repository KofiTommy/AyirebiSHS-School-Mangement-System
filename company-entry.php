<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
@$_companyid=$_POST['companyid'];
@$_fullname=$_POST['company'];
@$_Recordedby=$_SESSION['USERID'];
@$_FileName=$_FILES["logo"]["name"];

if(isset($_POST['register_company'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblcompany(companyid,fullname,logo,datetimeentry,status,recordedby)
	VALUES('$_companyid','$_fullname','$_FileName',NOW(),'active','$_Recordedby')");
if($_SQL_EXECUTE){
	move_uploaded_file($_FILES["logo"]["tmp_name"], "logo/".$_FileName);
	//move_uploaded_file($_FILES["file1"]["tmp_name"],"uploads/" . $_FILES["file1"]["name"]);
	
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>School Successfully Saved</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>School failed to save,$_Error</div>";
	}
}
?>

<?php
include("dbstring.php");
@$_Update_fullname=$_POST['update_item'];
@$_Update_companyid=$_POST['update_companyid'];
@$_FileName=$_FILES["logo"]["name"];

if(isset($_POST['update_item_entry'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblcompany SET fullname='$_Update_fullname',logo='$_FileName' WHERE companyid='$_Update_companyid'");
if($_SQL_EXECUTE){
	move_uploaded_file($_FILES["logo"]["tmp_name"], "logo/".$_FileName);
	
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>School Successfully Updated</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>School failed to update,$_Error</div>";
	}
}
?>




<?php
include("dbstring.php");

if(isset($_GET["delete_item"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblcompany WHERE companyid='$_GET[delete_item]'");
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
	</div>
<div class="main-platform" style="">
	<table width="100%">
		<tr>
			<td valign="top" width="30%" align="center">
				<div class="form-entry" align="left">
			
		<?php
		include("dbstring.php");
		if(isset($_GET['edit_item']))
		{
		$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblcompany WHERE companyid='$_GET[edit_item]'");
		$_Count=mysqli_num_rows($_SQL_EXECUTE);
		if($_Count>0)
		{
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
			{
			echo "<h3>School Information Update</h3>";
			echo "<form method='post' id='formID' name='formID' action='company-entry.php' enctype='multipart/form-data'>";
			echo "<label>School Id</label>";
			echo "<input type='text' id='update_companyid' name='update_companyid' value='$row[companyid]'><br/>";

			echo "<label>School </label>";
			echo "<input type='text' id='update_item' name='update_item' value='$row[fullname]'><br/><br/>";
			echo "<label>Logo</label>";
			echo "<input type='file' id='logo' name='logo' value='' /><br/><br/>";
			echo "<div align='center'><button class='button-edit' id='update_item_entry' name='update_item_entry'><i class='fa fa-edit' style='color:white'></i> UPDATE SCHOOL</button></div>";

			echo "</form>";
			}
		}
		}
		?>


			
			<h3>School Information Entry 
				</h3>
			<br/>
		
			<form method="post" id="formID" name="formID" action="company-entry.php" enctype="multipart/form-data">

			<label>School Id</label><br/>
			<input type="text" id="companyid" name="companyid" value="<?php include("shortcode.php"); echo $shortcode;?>" class="validate[required]" readonly/><br/><br/>

			<fieldset><legend>School</legend>
			<input type="text" id="company" name="company" value="" class="validate[required]"/><br/><br/>
			<label>Logo</label><br/>
			<input type="file" id="logo" name="logo" value="" class="validate[required]"/><br/>
			</fieldset><br/>

			
			<div align="center"><button class="button-save" id="register_company" name="register_company"><i class="fa fa-save"></i> SAVE SCHOOL</button></div>
		</form>

		</div>
			</td>
			<td width="70%">
				<div class="form-entry" align="left">
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblcompany ORDER BY fullname ASC");

				//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>School Information</caption>";
				echo "<thead><th colspan=2>TASK</th><th>SCHOOL ID</th><th>SCHOOL</th><th>LOGO</th><th>DATE/TIME</th><th>STATUS</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td align='center'><a title='Delete $row[fullname] ($row[companyid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='company-entry.php?delete_item=$row[companyid]'<i class='fa fa-trash-o' style='color:red'></i></a></td>";
				echo "<td align='center'><a title='Edit $row[fullname] ($row[companyid])'  href='company-entry.php?edit_item=$row[companyid]'<i class='fa fa-edit' style='color:olive'></i></a></td>";
				
				echo "<td align='center'>$row[companyid]</td>";
				echo "<td align='center'>$row[fullname]</td>";
				echo "<td align='center'>$row[logo]</td>";
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
</body>
</html>