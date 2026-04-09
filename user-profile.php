<?php
session_start();

include("dbstring.php");

@$_UserID=$_POST['userid'];
@$_Firstname=$_POST['firstname'];
@$_Surname=$_POST['surname'];
@$_Othernames=$_POST['othernames'];
@$_Gender=$_POST['gender'];
@$_Birthday=$_POST['birthday'];
@$_Age=$_POST['age'];
@$_PostalAddress=$_POST['postaladdress'];
@$_HomeAddress=$_POST['homeaddress'];
@$_HomeTown=$_POST['hometown'];
@$_Religion=$_POST['religion'];
@$_Relationship=$_POST['relationship'];
@$_Nextofkin=$_POST['nextofkin'];

if(isset($_POST['register_user'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblsystemuser(userid,firstname,surname,othernames,gender,birthday,age,postaladdress,homeaddress,hometown,religion,nextofkin_fullname,nextofkin_contact,registereddatetime,recordedby,status,username,password,accesslevel,finame)
	VALUES('$_UserID','$_Firstname','$_Surname','$_Othernames','$_Gender','$_Birthday','$_Age','$_PostalAddress','$_HomeAddress','$_HomeTown','$_Religion','$_Relationship','$_Nextofkin','')");
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
	</div>

<div class="main-platform" style="background-color:white">
	<br/>
	<table width="200%">
		<tr>
			<td width="100%">
				<div class="form-entry2">
				<?php
				include("dbstring.php");
				@$_Get_UserID=$_GET['view_user'];

				if(isset($_Get_UserID)){
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE userid='$_Get_UserID'");

				//Registered clients
				echo "<table width='100%'>";
				echo "<caption>Profile</caption>";
				echo "<thead><th>User Id</th><th>First Name</th><th>Surname</th><th>Othernames</th><th>Gender</th><th>Birthday</th><th>Age</th><th>Postal Address</th><th>Home Address</th><th>Home Town</th><th>Religion</th><th>Relationship</th><th>Next Of Kin Fullname</th><th>Next Of Kin Contact</th><th>Entry Date/Time</th><th>Status</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				
				echo "<td>$row[userid]</td>";
				echo "<td>$row[firstname]</td>";
				echo "<td>$row[surname]</td>";
				echo "<td>$row[othernames]</td>";
				echo "<td>$row[gender]</td>";
				echo "<td>$row[birthday]</td>";
				echo "<td>$row[age]</td>";
				echo "<td>$row[postaladdress]</td>";
				echo "<td>$row[homeaddress]</td>";
				echo "<td>$row[hometown]</td>";
				echo "<td>$row[religion]</td>";
				echo "<td>$row[relationship]</td>";
				echo "<td>$row[nextofkin_fullname]</td>";
				echo "<td>$row[nextofkin_contact]</td>";
				echo "<td>$row[registereddatetime]</td>";
				echo "<td>";
				if($row['status']=="active"){
					echo "<strong style='color:green'>Active</strong>";
				}
				else{
					echo "<strong style='color:red'>Blocked</strong>";
				}
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
</div>
</body>
</html>