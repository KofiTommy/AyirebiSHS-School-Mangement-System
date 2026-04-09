<?php
session_start();
$_SESSION['Message']="";
?>

<?php
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
@$_Email=$_POST['email'];
@$_Mobile=$_POST['mobile'];

@$_Religion=$_POST['religion'];
@$_Relationship=$_POST['relationship'];
@$_Nextofkin_fullname=$_POST['nextoffullname'];
@$_Nextofcontact=$_POST['nextofkincontact'];
@$_Username=$_POST['username'];
@$_Password=md5($_POST['password']);
@$_AccessLevel="user";
@$_SystemType="Student";
@$_Filename=$_POST['filename'];

if(isset($_POST['register_user'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblsystemuser(userid,firstname,surname,othernames,gender,birthday,age,postaladdress,homeaddress,hometown,religion,relationship,nextofkin_fullname,nextofkin_contact,email,mobile,registereddatetime,status,username,password,accesslevel,systemtype,branchid)
	VALUES('$_UserID','$_Firstname','$_Surname','$_Othernames','$_Gender',STR_TO_DATE('$_Birthday','%d-%m-%Y'),'$_Age','$_PostalAddress','$_HomeAddress','$_HomeTown','$_Religion','$_Relationship','$_Nextofkin_fullname','$_Nextofcontact','$_Email','$_Mobile',NOW(),'active','$_Username','$_Password','$_AccessLevel','$_SystemType','$_SESSION[BRANCHID]')");
if($_SQL_EXECUTE){
$_SESSION['Message']="<div style='color:green;text-align:center'>Teacher/Student Information Successfully Saved</div>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red'>Teacher/Student Information Failed to save,Error:$_Error</div>";
}

}
?>


<?php
include("dbstring.php");
include("code.php");
@$_MessageId=$code;
@$_Message=$_POST['message'];

if(isset($_POST["send_message"])){
$_SQL=mysqli_query($con,"INSERT INTO tblmessages(messageid,messages,datetimeentry,status,sentby)
VALUES('$_MessageId','$_Message',NOW(),'active','$_SESSION[USERID]')");
if($_SQL){
$_SESSION['Message']="<div style='color:green;padding:10px;'>Message Successfully Submitted</di>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red;padding:10px;'>Message failed to submit</div>";
}
}
?>

<?php

if(isset($_GET["delete_message"])){
$_SQL_D=mysqli_query($con,"DELETE FROM tblmessages WHERE messageid='$_GET[delete_message]'");
if($_SQL_D){
	$_SESSION['Message']="<div style='color:red;padding:10px;'>Message Successfully Deleted</di>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red;padding:10px;'>Message failed to delete</div>";
}

}
?>
<html>
<head>
<?php
include("links.php");
?>
</head>
<body class="body-style">
<div class="header">
<?php
include("menu.php");
?>		
<div class="main-platform" align="center" >
	<?php
	echo $_SESSION["Message"];
	?>

	<table border="0" width="100%">
		<tr>
			<td width="30%" valign="top" align="center">
				<h4>Group Student Registration</h4>	
				
	<div class="form-entry" align="left">
		<form method="post" action="import-data.php" id="form1" enctype="multipart/form-data">
		<div align="center">

		<div id="subscription-style1" align="left">
			<!--Employee Details -->
			
			<label>Upload Excel File*</label><br>
			<input type="file" id="file1" name="file1" /><br><br>				
			
			<!--Submit form's data -->
			<button class="button-pay" id="submit_group_data" name="submit_group_data"><i class="fa fa-upload" ></i> Upload Data</button>
			
		</div>
	</div>
	</form>
		</div>
			</td>

			<td width="70%" valign="top">
				<div class="form-entry" align="left">
				<?php
				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE date_format(registereddatetime,'%d-%m-%Y')=date_format(NOW(),'%d-%m-%Y') AND (systemtype='Student' OR systemtype='Teacher') ORDER BY registereddatetime DESC");

				//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>STUDENTS</caption>";
				echo "<thead><th colspan=2>TASK</th><th>STUDENTS</th><th>GENDER</th><th>BIRTHDAY</th><th>USERNAME</th><th>TYPE</th><th>DATE/TIME</th><th>STATUS</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
				echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='user-profile.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				//echo "<td align='center'><a title='Delete $row[firstname] ($row[userid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='viewstudents.php?delete_user=$row[userid]'<i class='fa fa-times' style='color:red'></i></a></td>";
				echo "<td align='center'><a title='Edit $row[firstname] ($row[userid])' href='register_edit.php?edit_user=$row[userid]'<i class='fa fa-edit' style='color:green'></i></a></td>";
				//echo "<td>";
				if($row['status']=="active"){
				//echo"<a title='Block $row[firstname] ($row[userid])' href='viewstudents.php?block_user=$row[userid]'<i class='fa fa-user' style='color:orange'></i></a>";
					
			}else{
				//echo"<a title='Unblock $row[firstname] ($row[userid])' href='viewstudents.php?unblock_user=$row[userid]'<i class='fa fa-user' style='color:red'></i></a>";
				
			}
			//	echo "</td>";


				echo "<td>$row[firstname] $row[surname] $row[othernames]($row[userid])</td>";
				echo "<td align='center'>$row[gender]</td>";
				echo "<td align='center'>$row[birthday]</td>";
				echo "<td align='center'>$row[username]</td>";
				
				echo "<td align='center'>$row[systemtype]</td>";
				echo "<td align='center'>$row[registereddatetime]</td>";
				echo "<td align='center'>";
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
				?>
			</div>
			</td>
		</tr>
	</table>
</div>
</body>
</html>