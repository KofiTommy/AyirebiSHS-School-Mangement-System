
<?php
session_start();
	include("dbstring.php");
	@$_ClassID =trim($_GET['search-bill']);
  //@$_Batch=trim($_GET['batch']);
//echo $_Batch;
	$sql="SELECT * FROM tblitemprice ip INNER JOIN tblitem itm ON ip.itemid=itm.itemid INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid
  WHERE ip.class_entryid='$_ClassID' AND ip.status='active' ORDER BY ip.term,ip.batch ASC";
	$result =mysqli_query($con,$sql);

	  echo "<table border='1'>";
    echo "<thead>";
    echo "<th>Class</th><th>Term</th><th>Batch</th><th>Item</th><th>Amount</th><th>Date/Time</th><th>Status</th><th colspan='1'>Action</th>";
    echo "</thead>";
    echo "<tbody>";
    while($row=mysqli_fetch_array($result,MYSQLI_ASSOC))
    {
      echo "<tr>";

      echo "<td align='center'>";
      echo $row['class_name'];
      echo "</td>";

      echo "<td align='center'>";
      echo $row['term'];
      echo "</td>";

      echo "<td align='center'>";
      echo $row['batch'];
      echo "</td>";

      echo "<td align='center'>";
      echo $row['itemname'];
      echo "</td>";

      echo "<td align='center'>";
      echo $row['price'];
      echo "</td>";

      echo "<td align='center'>";
      echo $row['datetimeprice'];
      echo "</td>";

      echo "<td align='center'>";
      echo $row['status'];
      echo "</td>";

      echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";

	mysqli_close($con);
	?>
