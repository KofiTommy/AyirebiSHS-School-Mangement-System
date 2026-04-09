<?php
session_start();
$_SESSION['Message']="";
include("dbstring.php");
@$_UserID=$_POST['userid'];
@$_Firstname=$_POST['firstname'];
@$_Surname=$_POST['surname'];
@$_Othernames=$_POST['othernames'];
@$_Gender=$_POST['gender'];
@$_ResidenceType = $_POST['residencetype'];
@$_Birthday=$_POST['birthday'];
@$_Age=$_POST['age'];
@$_PostalAddress=$_POST['postaladdress'];
@$_HomeAddress=$_POST['homeaddress'];
@$_HomeTown=$_POST['hometown'];
@$_Email=$_POST['email'];
@$_Mobile=$_POST['mobile'];
@$_Religion=$_POST['religion'];
@$_Relationship=$_POST['relationship'];
@$_BECEIndexNumber = $_POST['beceindexnumber'];
@$_Nextofkin_fullname=$_POST['nextoffullname'];
@$_Nextofcontact=$_POST['nextofkincontact'];
@$_Username=$_POST['username'];
@$_Password=$_POST['password'];
@$_AccessLevel="user";
@$_SystemType=$_POST['systemtype'];
@$_Recipient=$_POST['recipient'];
@$_Filename=$_POST['filename'];
@$_Branch=$_POST['branchid'];

//birthday=STR_TO_DATE('$_Birthday','%d-%m-%Y'),

if(isset($_POST['register_update'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsystemuser SET firstname='$_Firstname',surname='$_Surname',
othernames='$_Othernames',gender='$_Gender',residencetype='$_ResidenceType',age='$_Age',
postaladdress='$_PostalAddress',homeaddress='$_HomeAddress',hometown='$_HomeTown',email='$_Email',mobile='$_Mobile',religion='$_Religion',
relationship='$_Relationship',beceindexnumber='$_BECEIndexNumber',nextofkin_fullname='$_Nextofkin_fullname',nextofkin_contact='$_Nextofcontact',staffstatus='$_Recipient',branchid='$_Branch' 
WHERE userid='$_UserID'");
if($_SQL_EXECUTE){
$_SESSION['Message']="<div style='color:green;text-align:center'>User Information Successfully Updated</div>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red'>User Information Failed to update,Error:$_Error</div>";
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
	<?php
	include("menu.php");
	?>		
	</div>
<div class="main-platform" style="">
<br/>
<table width="200%">
<tr>
<td width="25%">			
</td>
<td valign="top" width="50%" align="center">
<?php
echo $_SESSION['Message'];
?>
<div class="form-entry" align="left">
<?php
			include("dbstring.php");
			if(isset($_GET["edit_user"])){
			$SQL=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE userid='$_GET[edit_user]'");
			if($row=mysqli_fetch_array($SQL,MYSQLI_ASSOC)){
			?>
			<form method="post" id="formID" name="formID" action="register_edit.php">
			<h3>EDIT REGISTERED USER 
				</h3>
			<label>User Id</label><br/>
			<input type="text" id="userid" name="userid" value="<?php echo $row['userid'];?>" class="validate[required]" readonly/><br/><br/>

			<fieldset><legend>BIO-DATA</legend>
			<label>First Name</label><br/>
			<input type="text" id="firstname" name="firstname" value="<?php echo $row['firstname'];?>" class="validate[required]"/><br/><br/>

			<label>Surname</label><br/>
			<input type="text" id="surname" name="surname" value="<?php echo $row['surname'];?>" class="validate[required]"/><br/><br/>

			<label>Othernames</label><br/>
			<input type="text" id="othernames" name="othernames" value="<?php echo $row['othernames'];?>" />
			</fieldset><br/><br/>

			<fieldset><legend>GENDER</legend>
			<?php 
			if($row['gender']=="male")
			{
			?>
			<input type="radio" id="gender" name="gender" value="male" class="validate[required]" checked>
			<label>Male</label>
			<input type="radio" id="gender" name="gender" value="female" class="validate[required]">
			<label>Female</label>
			</fieldset><br/><br/>
			<?php
			}
			else
			{
			?>
			<input type="radio" id="gender" name="gender" value="male" class="validate[required]" >
			<label>Male</label>
			<input type="radio" id="gender" name="gender" value="female" class="validate[required]" checked>
			<label>Female</label>
			</fieldset><br/><br/>
			<?php
			}
			?>
			<fieldset><legend>Residence Type</legend>
                <label><input type="radio" name="residencetype" value="Day" required> Day</label>
                <label style="margin-left:1rem;"><input type="radio" name="residencetype" value="Boarding" required> Boarding</label>
            </fieldset>

			<label>Birthday</label><br/>

			<script type="text/javascript">
            function show_alert()
            {
               alert("Please select Date Time Picker");
            }
            </script>
            <script src="scripts/datetimepicker_css.js"></script>

        <?php
         $tomorrow = mktime(0,0,0,date("m")+1,date("d"),date("Y"));
          $tdate= date("d/m/Y", $tomorrow);
         ?>
      <input type="hidden" name="todate" id="todate" value="<?php echo $tdate; ?>">
      <input type="text" maxlength="25" size="25" onclick="javascript:NewCssCal ('birthday','ddMMyyyy','','','','','')" id="birthday" name="birthday" value="<?php echo $row['birthday'];?>" class="validate[required]" readonly   onchange="CheckDateOfBirth()"/>
      <br/><br/>
			<label>Age</label><br/>
			<input type="text" id="age" name="age" value="<?php echo $row['age'];?>" class="validate[required]" readonly/><br/><br/>

			<label>Postal Address</label>
			<textarea id="postaladdress" name="postaladdress" ><?php echo $row['postaladdress'];?></textarea>
			<br/><br/>

			<label>Home Address</label>
			<textarea id="homeaddress" name="homeaddress" ><?php echo $row['homeaddress'];?></textarea>
			<br/><br/>
			<label>Home Town</label>
			<input type="text" id="hometown" name="hometown" value="<?php echo $row['hometown'];?>"/>
			<br/><br/>
			<label>Mobile</label>
			<input type="text" id="mobile" name="mobile" class="validate[required]" value="<?php echo $row['mobile'];?>" /><br/><br/>
	
			<select id="religion" name="religion" class="validate[required]">
				<option value="<?php echo $row['religion'];?>"><?php echo $row['religion'];?></option>
				<option value="Christian">Christian</option>
				<option value="Muslim">Muslim</option>
				<option value="Tradition">Tradition</option>
				<option value="Others">Others</option>
			</select><br/><br/>

			<select id="relationship" name="relationship" class="validate[required]">
				<option value="<?php echo $row['relationship'];?>"><?php echo $row['relationship'];?></option>
				<option value="Father">Father</option>
				<option value="Mother">Mother</option>
				<option value="Uncle">Uncle</option>
				<option value="Brother">Brother</option>
				<option value="Sister">Sister</option>
				<option value="Daughter">Daughter</option>
				<option value="Others">Others</option>
			</select><br/><br/>
			
			<label>BECE Index Number</label><br/>
			<input type="text" id="beceindexnumber" name="beceindexnumber" />
			<br/><br/>
			<fieldset><legend>Next Of Kin</legend>
			<label>Full Name</label><br/>
			<input type="text" id="nextoffullname" name="nextoffullname" value="<?php echo $row['nextofkin_fullname'];?>" class="validate[required]"/><br/><br/>
			<label>Contact</label>
			<input type="text" id="nextofkincontact" name="nextofkincontact" value="<?php echo $row['nextofkin_contact'];?>" class="validate[required]" /><br/><br/>

			</fieldset><br/>

			<label>E-mail</label><br/>
			<input type="text" id="email" name="email" class="validate[required,custom[email]]" value="<?php echo $row['email'];?>" /><br/><br/>
			<label>Branch:</label>
			<?php
			$_SQL_B=mysqli_query($con,"SELECT * FROM tblbranch WHERE branchid='$row[branchid]'");
			if($row_b=mysqli_fetch_array($_SQL_B,MYSQLI_ASSOC)){
			echo $row_b['location'];
			}
			$_SQL=mysqli_query($con,"SELECT * FROM tblbranch");
			echo "<select id='branchid' name='branchid' class='validate[required]''>";
			echo "<option value='$row_b[branchid]'>$row_b[location]</option>";
			while($rows=mysqli_fetch_array($_SQL,MYSQLI_ASSOC)){
				echo "<option value='$rows[branchid]'>$rows[location]</option>";
			}
			echo "</select>";
			?>
			<br/><br/>
<select id="recipient" name="recipient">
<option value="<?php echo $row['staffstatus'];?>"><?php echo $row['staffstatus'];?></option>
<option value="Teaching Staff">Teaching Staff</option>
<option value="Non-Teaching Staff">Non Teaching Staff</option>
<option value="Student">Student</option>
</select><br/><br/>			
<div align="center"><button class="button-edit" id="register_update" name="register_update"><i class="fa fa-edit"></i> UPDATE REGISTER</button></div>
</form>
<?php
}
}
?>
</div>
</td>
<td width="25%">			
</td>
</tr>
</table>

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