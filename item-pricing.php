<?php
session_start();
$_SESSION['Message']="";
?>

<?php
include("dbstring.php");
include("code.php");

@$_ItemName="";
@$_ItemPriceId=$code;
@$_Price=$_POST['price'];
@$_Itemid=$_POST['itemid'];
@$_Recordedby=$_SESSION['USERID'];
@$_Class=$_POST['class'];
@$_Term=$_POST['term'];
@$_Batch=$_POST['batch'];

if(isset($_POST['price_item_entry'])){
$_SQL_Check=mysqli_query($con,"SELECT * FROM tblitemprice ip WHERE ip.itemid='$_Itemid' AND ip.class_entryid='$_Class' AND ip.batch='$_Batch' AND ip.term='$_Term'");
if(mysqli_num_rows($_SQL_Check)>0){
$_SESSION['Message']="<div style='color:red'>Batch's item already priced</div>";
}
else{
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblitemprice(itempriceid,class_entryid,term,batch,itemid,price,datetimeprice,status,recordedby,branchid)
VALUES('$_ItemPriceId','$_Class','$_Term','$_Batch','$_Itemid','$_Price',NOW(),'active','$_Recordedby','$_SESSION[BRANCHID]')");
if($_SQL_EXECUTE){
	$_SQL_ITM=mysqli_query($con,"SELECT * FROM tblitem WHERE itemid='$_Itemid'");
	if($row_itm=mysqli_fetch_array($_SQL_ITM,MYSQLI_ASSOC)){
		$_ItemName=$row_itm['itemname'];
	}
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>$_ItemName Successfully Priced</div>";
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
		
		<?php
		include("dbstring.php");
		if(isset($_GET['price_item']))
		{
		$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblitem WHERE itemid='$_GET[price_item]'");
		$_Count=mysqli_num_rows($_SQL_EXECUTE);
		if($_Count>0)
		{
			echo '<div class="form-entry" align="left">';
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
			{
			echo "<h3>Item Pricing</h3>";
			echo "<form method='post' id='formID' name='formID' action='item-pricing.php'>";
			echo "<label>Item Id</label>";
			echo "<input type='text' id='itemid' name='itemid' value='$row[itemid]'><br/>";

			echo "<label>Item </label>";
			echo "<input type='text' id='itemname' name='itemname' value='$row[itemname]' readonly><br/><br/>";
			echo "<label>Price </label>";
			echo "<input type='text' id='price' name='price' value='' class='validate[required,custom[number]]'><br/><br/>";

			$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry");

			echo "<select id='class' name='class' class='validate[required]'>";
			echo "<option value=''>Select Class</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
				}
				
			echo "</select><br/><br/>";

			echo "<select id='term' name='term' class='validate[required]'>";
			echo "<option value=''>Select Semester</option>";
			echo "<option value='1'>1</option>";
			echo "<option value='2'>2</option>";
			//echo "<option value='3'>3</option>";
			echo "</select>";
			echo "<br/><br/>";

			echo "<fieldset><legend>BATCH</legend>";
			

			$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");

			echo "<select id='batch' name='batch' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[batchid]'>$row[batch]</option>";
				}
			echo "</select>";
			
			echo "</fieldset>";
			echo "<br/><br/>";

			echo "<div align='center'><button class='button-save' id='price_item_entry' name='price_item_entry'><i class='fa fa-save'></i> PRICE ITEM</button></div>";

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
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblitem ORDER BY itemname ASC");

				//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>List Of Items</caption>";
				echo "<thead><th colspan=1>Task</th><th>Item Id</th><th>Item</th><th>Entry Date/Time</th><th>Status</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				//echo "<td align='center'><a title='Delete $row[itemname] ($row[itemid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='item-pricing.php?delete_item=$row[itemid]'<i class='fa fa-times' style='color:red'></i></a></td>";
				echo "<td align='center'><a title='Price $row[itemname] ($row[itemid])'  href='item-pricing.php?price_item=$row[itemid]'<i class='fa fa-plus' style='color:green'></i></a></td>";
				echo "<td align='center'>$row[itemid]</td>";
				echo "<td>$row[itemname]</td>";
				echo "<td align='center'>$row[datetimeentry]</td>";
				//echo "<td>$row[recordedby]</td>";
				echo "<td align='center'>$row[status]</td>";
				echo "</tr>";

				echo "<tr style='border:1px solid gray;'>";
				echo "<td colspan='1'></td>";
				echo "<td colspan='4'>";

				$_SQL_IP=mysqli_query($con,"SELECT * FROM tblitemprice ip 
				INNER JOIN tblbatch bch ON ip.batch=bch.batchid
				INNER JOIN tblclassentry ce ON ce.class_entryid=ip.class_entryid
				 WHERE itemid='$row[itemid]'");
				echo "<table>";
				echo "<thead><th>Class</th><th>Semester</th><th>Batch</th><th>Price</th><th>Date/Time</th></thead>";
				echo "<tbody>";
				while($row_ip=mysqli_fetch_array($_SQL_IP,MYSQLI_ASSOC))
				{
				echo "<tr>";
				echo "<td>$row_ip[class_name]</td>";
				echo "<td align='center'>$row_ip[term]</td>";
				echo "<td>$row_ip[batch]</td>";
				echo "<td align='center'>$row_ip[price]</td>";
				echo "<td align='center'>$row_ip[datetimeprice]</td>";
				
				echo "</tr>";
				}
				echo "</tbody>";
				echo "</table>";
				
				echo "</td>";
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