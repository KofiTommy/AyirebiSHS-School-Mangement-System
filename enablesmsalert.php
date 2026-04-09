<?php
session_start();
$_SESSION['Message']="";

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
@$_Nextofkin_fullname=$_POST['nextoffullname'];
@$_Nextofcontact=$_POST['nextofkincontact'];
@$_Username=$_POST['username'];
@$_Password=$_POST['password'];
@$_AccessLevel="user";
@$_SystemType=$_POST['systemtype'];
@$_Filename=$_POST['filename'];

if(isset($_POST['register_user'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblsystemuser(userid,firstname,surname,othernames,gender,birthday,age,postaladdress,homeaddress,hometown,religion,relationship,nextofkin_fullname,nextofkin_contact,registereddatetime,status,username,password,accesslevel,systemtype)
	VALUES('$_UserID','$_Firstname','$_Surname','$_Othernames','$_Gender',STR_TO_DATE('$_Birthday','%d-%m-%Y'),'$_Age','$_PostalAddress','$_HomeAddress','$_HomeTown','$_Religion','$_Relationship','$_Nextofkin_fullname','$_Nextofcontact',NOW(),'active','$_Username','$_Password','$_AccessLevel','$_SystemType')");
if($_SQL_EXECUTE){
$_SESSION['Message']="<div style='color:green;text-align:center'>User Information Successfully Saved</div>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red'>User Information Failed to save,Error:$_Error</div>";
}

}
?>

<?php
include("dbstring.php");

if(isset($_GET["block_user"]))
{
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsystemuser SET status='block' WHERE userid='$_GET[block_user]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>User is blocked</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>User failed to block</div>";
	}
}
?>

<?php
include("dbstring.php");

if(isset($_GET["unblock_user"]))
{
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsystemuser SET status='active' WHERE userid='$_GET[unblock_user]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>User is active</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>User failed to unblock</div>";
	}
}
?>
<?php
if(isset($_POST["activatesms"])){
	@$_UserId=$_POST["smscheck"];
foreach($_UserId as $selecteduserid){
	$_SQLES=mysqli_query($con,"UPDATE tblsystemuser SET smsalert=1 WHERE userid='$selecteduserid'");
	if($_SQLES){
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;border:1px solid #4a5;background-color:#efe;padding:5px;'>SMS Enabled for $selecteduserid</div>";	
	}
}
}
?>

<?php
include("dbstring.php");

if(isset($_GET["delete_user"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblsystemuser WHERE userid='$_GET[delete_user]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>User Record Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>User failed to delete</div>";
	}
}
?>


<html>
<head>
<?php
include("links.php");
?>
<script type="text/javascript">
function selectAll(){
  var selall = document.getElementById("all").checked;
  if(selall==true){
    checkBox();
  }
  else if(selall==false){
    uncheckBox();
  }  
 }
 function uncheckBox(){
 //	document.getElementById("activatesms").style.display="none";
   var inputs = document.getElementsByName("smscheck[]");
    for(var i=0;i<inputs.length;i++){
     inputs[i].checked=false;
    }
     return false;
 }
  function checkBox(){
 // 	document.getElementById("activatesms").style.display="block";
var inputs = document.getElementsByName("smscheck[]");
    for(var i=0;i<inputs.length;i++){
     inputs[i].checked=true;
    }
 return false;
 }
 function hideButton(){
 //	document.getElementById("activatesms").style.display="none";
  
 }
</script>

</head>
<body onload="hideButton()">
<div class="header">
<?php
include("menu.php");
?>		
</div>
<div class="main-platform" style="">
	<table width="200%">
		<tr>
			<td width="50%" align="center">
				<div class="form-entry" style="">
				<div  width="50%">
	<form method="post" action="enablesmsalert.php">
				<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry ORDER BY class_name");

			echo "<select id='class_entryid' name='class_entryid' class='validate[required]'>";
			echo "<option value=''>Select Class</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
				}
				
			echo "</select><br/>";
			?>		
		</div>
	</td>

	<td>
		<div width="50%">
				<button class="button-show" name="showstudent"><i class="fa fa-search"></i> SHOW STUDENTS</button>
			
		</div><br/>
</div>
</form>


				
			</td>
		</tr>
	</table>
<br/>
<?php
if(isset($_POST["showstudent"]))
{
@$_ClassentryID=$_POST["class_entryid"];
?>
<div class="form-entry" style="">
	<form method="post">
	<h3>Activate/Deactivate SMS Alert</h3>
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE2=mysqli_query($con,"SELECT * FROM tblsystemuser su INNER JOIN tblclass cl 
					ON su.userid=cl.userid WHERE cl.class_entryid='$_ClassentryID' AND su.systemtype='Student' ORDER BY su.firstname ASC");

				//Registered clients
				@$_ClassName="";
				$_SQLGC=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$_ClassentryID'");
				if($rowc=mysqli_fetch_array($_SQLGC,MYSQLI_ASSOC)){
				$_ClassName=$rowc["class_name"];
				}
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>STUDENT(S) FOUND:". mysqli_num_rows($_SQL_EXECUTE2)." ".$_ClassName ."</caption>";
				echo "<thead><th>*</th><th>User</th><th>Address</th><th>Email</th><th>Mobile</th><th>Username</th><th>SMS<input type='checkbox' id='all' name='all' onclick='selectAll()'/></th></thead>";
				echo "<tbody>";
				echo "<tr><td colspan='7' align='right'><button class='button-save' id='activatesms' name='activatesms' ><i class='fa fa-refresh'></i> ACTIVATE</button></td></tr>";
				@$serial=0;
				while($row=mysqli_fetch_array($_SQL_EXECUTE2,MYSQLI_ASSOC)){
					$serial=$serial+1;
				echo "<tr>";
				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='user-profile.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				//echo "<td align='center'><a title='Delete $row[firstname] ($row[userid])' onclick=\"javascript:return confirm('Do you want to delete?');\" href='enablesmsalert.php?delete_user=$row[userid]'<i class='fa fa-times' style='color:red'></i></a></td>";
				//echo "<td align='center'><a title='Edit $row[firstname] ($row[userid])' href='register_edit.php?edit_user=$row[userid]'<i class='fa fa-edit' style='color:green'></i></a></td>";
				/*echo "<td>";
				if($row['status']=="active"){
				echo"<a title='Block $row[firstname] ($row[userid])' href='enablesmsalert.php?block_user=$row[userid]'<i class='fa fa-user' style='color:orange'></i></a>";
					
			}else{
				echo"<a title='Unblock $row[firstname] ($row[userid])' href='enablesmsalert.php?unblock_user=$row[userid]'<i class='fa fa-user' style='color:red'></i></a>";
				
			}
				echo "</td>";
				*/


				echo "<td align='center'>$serial.</td>";
				echo "<td>$row[firstname] $row[surname] $row[othernames]($row[userid])</td>";
				echo "<td align='center'>$row[postaladdress]</td>";
				echo "<td align='center'>$row[email]</td>";
				echo "<td align='center'>$row[mobile]</td>";
				echo "<td align='center'>$row[username]</td>";
				
				//echo "<td align='center'>$row[systemtype]</td>";
				//echo "<td align='center'>$row[registereddatetime]</td>";
				echo "<td align='center'>";
				if($row['smsalert']==0){
					echo "<input type='checkbox' id='smscheck' name='smscheck[]' value='$row[userid]' unchecked>";
				}
				else{
				echo "<input type='checkbox' id='smscheck' name='smscheck[]' value='$row[userid]' checked>";
			
				}
				echo "</td>";
				echo "</tr>";
				}
				echo "</tbody>";
				echo "</table>";
				?>
			</form>
</div>
<?php
}
?>


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
</div>
</body>
</html>