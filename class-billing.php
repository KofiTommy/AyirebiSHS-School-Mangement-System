<?php
session_start();
$_SESSION['Message']="";
?>

<?php
include("dbstring.php");
@$_ItemName="";
@$_Price=$_POST['price'];
@$_Item_Id=$_POST['item_id'];
@$_Recordedby=$_SESSION['USERID'];
@$_Class=$_POST['class'];
@$_Term=$_POST['term'];
@$_Batch=$_POST['batch'];

if(isset($_POST['save_items'])){
$_K=0;
foreach($_Item_Id as $_Selected_Item_Id){
include("code.php");
@$_ItemPriceId=$code;
@$_Selected_Price=$_Price[$_K];

$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblitemprice(itempriceid,class_entryid,term,batch,itemid,price,datetimeprice,status,recordedby,branchid)
VALUES('$_ItemPriceId','$_Class','$_Term','$_Batch','$_Selected_Item_Id','$_Selected_Price',NOW(),'active','$_Recordedby','$_SESSION[BRANCHID]')");
if($_SQL_EXECUTE){
	$_SQL_ITM=mysqli_query($con,"SELECT * FROM tblitem WHERE itemid='$_Itemid'");
	if($row_itm=mysqli_fetch_array($_SQL_ITM,MYSQLI_ASSOC)){
	//	$_ItemName=$row_itm['itemname'];
	}
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>$_ItemName failed to price,$_Error</div>";
	}
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
	<br/>
	<table width="100%">
	<tr>
		<td valign="top" width="30%" align="center">
		

		<form form="formID2" method="post" action="class-billing.php">
<fieldset><legend>CLASS BILLING</legend>
			<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry");

			echo "<select id='class' name='class' class='validate[required]'>";
			echo "<option value=''>Select Class</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
				}
				
			echo "</select><br/><br/>";
			?>
			<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");

			echo "<select id='batch' name='batch' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[batchid]'>$row[batch]</option>";
				}
				
			echo "</select><br/><br/>";

			$_SQL_1=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.systemtype='Student' ORDER BY su.userid ASC");
				
			while($row_s=mysqli_fetch_array($_SQL_1,MYSQLI_ASSOC)){
				echo "<input type='hidden' id='user_id' name='user_id[]' value='$row_s[userid]' />";
			}

			?>


			<select id="term" name="term">
				<option value="" class="validate[required]">Select Semester</option>
				<option value="1">1</option>
				<option value="2">2</option>
			</select><br/><br/>
			
			</fieldset><br/><br/>
			

		</div>

			</td>
<td width="70%">
	<div class="form-entry">
<?php
echo $_SESSION['Message'];
include("dbstring.php");
$_SQL_EXECUTE_1=mysqli_query($con,"SELECT * FROM tblitem");
			
echo "<table width='100%' style='background-color:white'>";
echo "<caption>List Of Items</caption>";
echo "<thead><th>Item</th><th>Price</th></thead>";
echo "<tbody>";
				
while($row=mysqli_fetch_array($_SQL_EXECUTE_1,MYSQLI_ASSOC))
{

//Registered clients
echo "<tr style='background-color:#fff'>";
echo "<td>";
echo $row["itemname"];
echo "<input type='hidden' id='item_id' name='item_id[]' value='$row[itemid]' />";
echo "</td>";

echo "<td>";
echo "<input type='text' name='price[]' value='' />";
echo "</td>";

echo "</tr>";


}
echo "</tbody>";
echo "</table>";		
?>
</div><br/>
<div align="right"><button class="button-print" id="save_itemsC" name="save_itemsC"><i class="fa fa-save"></i> SAVE ITEM</button></div>
</form>
</td>
</tr>
</table>
</div>
</body>
</html>