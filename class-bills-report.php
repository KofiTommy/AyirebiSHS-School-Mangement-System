<?php
session_start();
$_SESSION['Message']="";
?>

<?php
include("dbstring.php");
@$_ItemPriceId=$_POST['itempriceid'];
@$_Price=$_POST['price'];
//@$_Itemid=$_POST['itemid'];
//@$_Recordedby=$_SESSION['USERID'];
//@$_Class=$_POST['class'];
//@$_Term=$_POST['term'];
//@$_Batch=$_POST['batch_month']."-".$_POST['batch_year'];

if(isset($_POST['price_item_update'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblitemprice SET price='$_Price' WHERE itempriceid='$_ItemPriceId'");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Item Priced Successfully Updated </div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Item Priced failed to update,$_Error</div>";
	}
}
?>
<?php
include("dbstring.php");
if(isset($_GET['delete_price_item'])){
$_DeleteItemPriceId=$_GET['delete_price_item'];
$_SQL_Re=mysqli_query($con,"DELETE FROM tblitemprice WHERE itempriceid='$_DeleteItemPriceId'");
if($_SQL_Re){
$_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>Item Priced Successfully Deleted</div>";
}
else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Item priced failed to delete,$_Error</div>";
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
		

		<?php
		include("dbstring.php");
		if(isset($_GET['price_item']))
		{
		$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblitemprice ip 
		INNER JOIN tblitem itm ON ip.itemid=itm.itemid 
		INNER JOIN tblbatch bch ON ip.batch=bch.batchid
		WHERE ip.itempriceid='$_GET[price_item]'");
		$_Count=mysqli_num_rows($_SQL_EXECUTE);
		if($_Count>0)
		{
			echo '<div class="form-entry" align="left">';
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
			{
			echo "<h3>Update Class Billing</h3>";
			echo "<form method='post' id='formID' name='formID' action='class-billing.php'>";
			echo "<input type='hidden' id='itempriceid' name='itempriceid' value='$row[itempriceid]' readonly><br/>";

			echo "<label>Batch</label>";
			echo "<input type='text' id='batchid' name='batchid' value='$row[batch]' readonly><br/>";


			echo "<label>Item Id</label>";
			echo "<input type='text' id='itemid' name='itemid' value='$row[itemid]' readonly><br/>";

			echo "<label>Item </label>";
			echo "<input type='text' id='itemname' name='itemname' value='$row[itemname]' readonly><br/>";
			echo "<label>Price </label>";
			echo "<input type='text' id='price' name='price' value='$row[price]' class='validate[required,custom[number]]'><br/><br/>";

			echo "<div align='center'><button class='button-edit' id='price_item_update' name='price_item_update'><i class='fa fa-edit'></i> UPDATE CLASS</button></div>";

			echo "</form>";
			}
			echo '</div>';
		}
		}
		?>

			</td>
<td width="70%">
	<div class="form-entry">
<?php
echo $_SESSION['Message'];
include("dbstring.php");
$_SQL_EXECUTE_1=mysqli_query($con,"SELECT * FROM tblclassentry ORDER BY class_name ASC");
			
echo "<table width='100%' style='background-color:white'>";
echo "<caption>Class Billing: List Of Items</caption>";
echo "<thead><th colspan=2>Task</th><th>Item</th><th>Price</th><th>Entry Date/Time</th><th>Status</th></thead>";
echo "<tbody>";
				
while($row_c=mysqli_fetch_array($_SQL_EXECUTE_1,MYSQLI_ASSOC))
{
@$_Class_Id=$row_c['class_entryid'];
@$_Class=$row_c['class_name'];

//Registered clients
echo "<tr style='background-color:#fff'>";
echo "<td colspan='8'>";
echo $_Class;
echo "</td>";
echo "</tr>";

$k=1;
while($k<=3)
{
	
//$_SQL_BATCH=mysqli_query($con,"SELECT * FROM tblbatch");
$_SQL_BATCH=mysqli_query($con,"SELECT * FROM tblitemprice ip INNER JOIN tblitem itm ON ip.itemid=itm.itemid 
	INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid 
	INNER JOIN tblbatch b ON ip.batch=b.batchid WHERE  ip.term='$k' AND ip.class_entryid='$_Class_Id' GROUP BY ip.batch");
if(mysqli_num_rows($_SQL_BATCH)>0)//Batch exists in the item price
{
	echo "<tr style='background-color:#fff;font-weight:bold'>";
	echo "<td colspan='6'>";
	echo "Semester: ". $k;
	echo "</td>";
	echo "</tr>";

while($row_b=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC))
{
echo "<tr style='background-color:#eef;'>";
echo "<td colspan='6'>";
echo "Batch: ". $row_b['batch'];
echo "</td>";
echo "</tr>";
		
	@$_Total_Amount=0;
	$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblitemprice ip INNER JOIN tblitem itm ON ip.itemid=itm.itemid 
	INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid 
	INNER JOIN tblbatch b ON ip.batch=b.batchid
	WHERE ce.class_entryid='$_Class_Id' AND ip.term='$k' AND ip.batch='$row_b[batchid]' ORDER BY ip.term");
				
			while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
			{
				echo "<tr>";
				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				//echo "<td align='center'><a title='Delete $row[itemname] ($row[itemid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='item-pricing.php?delete_item=$row[itemid]'<i class='fa fa-times' style='color:red'></i></a></td>";
				echo "<td align='center'><a title='Edit $row[itemname] ($row[itemid])'  href='class-billing.php?price_item=$row[itempriceid]'<i class='fa fa-edit' style='color:olive'></i></a></td>";
				
				echo "<td align='center'><a onclick=\"javascript:return confirm('Do you want to delete?')\" title='Delete $row[itemname] ($row[itemid])'  href='class-billing.php?delete_price_item=$row[itempriceid]'<i class='fa fa-trash-o' style='color:red'></i></a></td>";
				//echo "<td align='center'>$row[term]</td>";
				//echo "<td align='center'>$row[batch]</td>";
				
			//	echo "<td align='center'>$row[itemid]</td>";
				echo "<td>$row[itemname]</td>";
				echo "<td align='center'>$row[price]</td>";
				
				echo "<td align='center'>$row[datetimeprice]</td>";
				//echo "<td>$row[recordedby]</td>";
				echo "<td align='center'>$row[status]</td>";
				$_Total_Amount=$_Total_Amount+$row['price'];
				
				echo "</tr>";
			}
				echo "<tr style='background-color:#dee;color:blue;font-size:18px;border-top:2px solid orange'>";
				echo "<td colspan='3' align='right' style=''>";
				echo "TOTAL:";
				echo "</td>";
				echo "<td colspan='1' align='center'>";
				echo $_Total_Amount;
				echo "</td>";

				echo "<td colspan='2' align='left'>";
				//echo $_Total_Amount;
				echo "</td>";
				
				echo "</tr>";
 }
}
$k++;
}
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