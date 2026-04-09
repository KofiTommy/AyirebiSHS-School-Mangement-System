<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
@$_ItemId=$_POST['itemid'];
@$_ItemName=strtoupper($_POST['item']);
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_item'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblitem(itemid,itemname,datetimeentry,recordedby,status,branchid)
	VALUES('$_ItemId','$_ItemName',NOW(),'$_Recordedby','active','$_SESSION[BRANCHID]')");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Item Successfully Saved</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Item failed to save,$_Error</div>";
	}
}
?>

<?php
include("dbstring.php");
@$_Update_ItemName=$_POST['update_item'];
@$_Update_Itemid=$_POST['update_itemid'];

if(isset($_POST['update_item_entry'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblitem SET itemname='$_Update_ItemName' WHERE itemid='$_Update_Itemid'");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Item Successfully Updated</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Item failed to update,$_Error</div>";
	}
}
?>




<?php
include("dbstring.php");

if(isset($_GET["delete_item"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblitem WHERE itemid='$_GET[delete_item]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Item Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Item failed to delete,Error:$_Error</div>";
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
		$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblitem WHERE itemid='$_GET[edit_item]'");
		$_Count=mysqli_num_rows($_SQL_EXECUTE);
		if($_Count>0)
		{
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
			{
			echo "<h3>Item Update</h3>";
			echo "<form method='post' id='formID' name='formID' action='item-entry.php'>";
			echo "<label>Item Id</label>";
			echo "<input type='text' id='update_itemid' name='update_itemid' value='$row[itemid]'><br/>";

			echo "<label>Item </label>";
			echo "<input type='text' id='update_item' name='update_item' value='$row[itemname]'><br/><br/>";
			echo "<div align='center'><button class='button-edit' id='update_item_entry' name='update_item_entry'><i class='fa fa-edit'></i> Update</button></div>";

			echo "</form>";
			}
		}
		}
		?>
			<h3>Item Entry 
				</h3>
			<br/>
		
			<form method="post" id="formID" name="formID" action="item-entry.php">

			<label>Item Id</label><br/>
			<input type="text" id="itemid" name="itemid" value="<?php include("shortcode.php");echo $shortcode;?>" class="validate[required]" readonly/><br/><br/>

			<fieldset><legend>ITEM</legend>
			<input type="text" id="item" name="item" value="" class="validate[required]"/><br/><br/>

			
			</fieldset><br/>

			
			<div align="center"><button class="button-save" id="register_item" name="register_item"><i class="fa fa-save"></i> SAVE ITEM</button></div>
		</form>

		</div>
			</td>
			<td width="70%">
				<div class="form-entry">
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblitem ORDER BY itemname ASC");

				//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>List Of Items</caption>";
				echo "<thead><th colspan=2>Task</th><th>Item Id</th><th>Item</th><th>Entry Date/Time</th><th>Status</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td align='center'><a title='Delete $row[itemname] ($row[itemid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='item-entry.php?delete_item=$row[itemid]'<i class='fa fa-trash-o' style='color:red'></i></a></td>";
				echo "<td align='center'><a title='Edit $row[itemname] ($row[itemid])'  href='item-entry.php?edit_item=$row[itemid]'<i class='fa fa-edit' style='color:olive'></i></a></td>";
				
				echo "<td align='center'>$row[itemid]</td>";
				echo "<td align='center'>$row[itemname]</td>";
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