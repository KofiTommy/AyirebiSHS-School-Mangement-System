<?php
session_start();
$_SESSION['Message']="";
include("dbstring.php");

function re_safe($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function re_normalize_birthday($value){
    $value = trim((string)$value);
    if($value === ""){ return ""; }
    if(preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:\s+\d{2}:\d{2}:\d{2})?$/', $value, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])){
        return sprintf("%04d-%02d-%02d", $m[1], $m[2], $m[3]);
    }
    if(preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $value, $m) && checkdate((int)$m[2], (int)$m[1], (int)$m[3])){
        return sprintf("%04d-%02d-%02d", $m[3], $m[2], $m[1]);
    }
    $timestamp = strtotime($value);
    if($timestamp !== false){
        return date("Y-m-d", $timestamp);
    }
    return false;
}

function re_birthday_input_value($value){
    $normalized = re_normalize_birthday($value);
    return $normalized === false ? "" : $normalized;
}

function re_value($value){
    return re_safe((string)$value);
}

function re_selected($current, $value){
    return strcasecmp(trim((string)$current), trim((string)$value)) === 0 ? " selected" : "";
}

function re_checked_attr($current, $value){
    return strcasecmp(trim((string)$current), trim((string)$value)) === 0 ? " checked" : "";
}

function re_row($row, $keys, $default = ""){
    if(!is_array($keys)){ $keys = array($keys); }
    foreach($keys as $key){
        if(is_array($row) && array_key_exists($key, $row) && $row[$key] !== null){
            return $row[$key];
        }
    }
    return $default;
}

function re_age($birthday){
    if(!$birthday){ return ""; }
    try{
        $dob = new DateTime($birthday);
        return (string)$dob->diff(new DateTime("today"))->y;
    }catch(Exception $e){
        return "";
    }
}

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

if(isset($_POST['register_update'])){
$_BirthdayNormalized = re_normalize_birthday($_Birthday);
if($_BirthdayNormalized === false){
    $_SESSION['Message']="<div style='color:red'>Please choose a valid date of birth.</div>";
}else{
$_Age = re_age($_BirthdayNormalized);
$_BirthdayEsc = mysqli_real_escape_string($con, $_BirthdayNormalized);
$_AgeEsc = mysqli_real_escape_string($con, $_Age);
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsystemuser SET firstname='$_Firstname',surname='$_Surname',
othernames='$_Othernames',gender='$_Gender',residencetype='$_ResidenceType',birthday='$_BirthdayEsc',age='$_AgeEsc',
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
			<input type="text" id="userid" name="userid" value="<?php echo re_value(re_row($row, 'userid'));?>" class="validate[required]" readonly/><br/><br/>

			<fieldset><legend>BIO-DATA</legend>
			<label>First Name</label><br/>
			<input type="text" id="firstname" name="firstname" value="<?php echo re_value(re_row($row, 'firstname'));?>" class="validate[required]"/><br/><br/>

			<label>Surname</label><br/>
			<input type="text" id="surname" name="surname" value="<?php echo re_value(re_row($row, 'surname'));?>" class="validate[required]"/><br/><br/>

			<label>Othernames</label><br/>
			<input type="text" id="othernames" name="othernames" value="<?php echo re_value(re_row($row, 'othernames'));?>" />
			</fieldset><br/><br/>

			<fieldset><legend>GENDER</legend>
			<input type="radio" id="gender_male" name="gender" value="male" class="validate[required]"<?php echo re_checked_attr(re_row($row, 'gender'), 'male'); ?>>
			<label for="gender_male">Male</label>
			<input type="radio" id="gender_female" name="gender" value="female" class="validate[required]"<?php echo re_checked_attr(re_row($row, 'gender'), 'female'); ?>>
			<label for="gender_female">Female</label>
			</fieldset><br/><br/>
			<fieldset><legend>Residence Type</legend>
                <label><input type="radio" name="residencetype" value="Day" required<?php echo re_checked_attr(re_row($row, 'residencetype'), 'Day'); ?>> Day</label>
                <label style="margin-left:1rem;"><input type="radio" name="residencetype" value="Boarding" required<?php echo re_checked_attr(re_row($row, 'residencetype'), 'Boarding'); ?>> Boarding</label>
            </fieldset>

			<label>Date of Birth</label><br/>
      <input type="date" id="birthday" name="birthday" value="<?php echo re_safe(re_birthday_input_value(re_row($row, array('birthday', 'Birthday'))));?>" class="validate[required]" max="<?php echo date("Y-m-d"); ?>" required />
      <br/><br/>
			<label>Age</label><br/>
			<input type="text" id="age" name="age" value="<?php echo re_safe(re_row($row, 'age'));?>" class="validate[required]" readonly/><br/><br/>

			<label>Postal Address</label>
			<textarea id="postaladdress" name="postaladdress" ><?php echo re_value(re_row($row, 'postaladdress'));?></textarea>
			<br/><br/>

			<label>Home Address</label>
			<textarea id="homeaddress" name="homeaddress" ><?php echo re_value(re_row($row, 'homeaddress'));?></textarea>
			<br/><br/>
			<label>Home Town</label>
			<input type="text" id="hometown" name="hometown" value="<?php echo re_value(re_row($row, 'hometown'));?>"/>
			<br/><br/>
			<label>Mobile</label>
			<input type="text" id="mobile" name="mobile" class="validate[required]" value="<?php echo re_value(re_row($row, 'mobile'));?>" /><br/><br/>
	
			<select id="religion" name="religion" class="validate[required]">
				<option value="Christian"<?php echo re_selected(re_row($row, 'religion'), 'Christian'); ?>>Christian</option>
				<option value="Muslim"<?php echo re_selected(re_row($row, 'religion'), 'Muslim'); ?>>Muslim</option>
				<option value="Tradition"<?php echo re_selected(re_row($row, 'religion'), 'Tradition'); ?>>Tradition</option>
				<option value="Others"<?php echo re_selected(re_row($row, 'religion'), 'Others'); ?>>Others</option>
			</select><br/><br/>

			<select id="relationship" name="relationship" class="validate[required]">
				<option value="Father"<?php echo re_selected(re_row($row, 'relationship'), 'Father'); ?>>Father</option>
				<option value="Mother"<?php echo re_selected(re_row($row, 'relationship'), 'Mother'); ?>>Mother</option>
				<option value="Uncle"<?php echo re_selected(re_row($row, 'relationship'), 'Uncle'); ?>>Uncle</option>
				<option value="Brother"<?php echo re_selected(re_row($row, 'relationship'), 'Brother'); ?>>Brother</option>
				<option value="Sister"<?php echo re_selected(re_row($row, 'relationship'), 'Sister'); ?>>Sister</option>
				<option value="Daughter"<?php echo re_selected(re_row($row, 'relationship'), 'Daughter'); ?>>Daughter</option>
				<option value="Others"<?php echo re_selected(re_row($row, 'relationship'), 'Others'); ?>>Others</option>
			</select><br/><br/>
			
			<label>BECE Index Number</label><br/>
			<input type="text" id="beceindexnumber" name="beceindexnumber" value="<?php echo re_value(re_row($row, array('beceindexnumber', 'BECEIndexNumber')));?>" />
			<br/><br/>
			<fieldset><legend>Next Of Kin</legend>
			<label>Full Name</label><br/>
			<input type="text" id="nextoffullname" name="nextoffullname" value="<?php echo re_value(re_row($row, 'nextofkin_fullname'));?>" class="validate[required]"/><br/><br/>
			<label>Contact</label>
			<input type="text" id="nextofkincontact" name="nextofkincontact" value="<?php echo re_value(re_row($row, 'nextofkin_contact'));?>" class="validate[required]" /><br/><br/>

			</fieldset><br/>

			<label>E-mail</label><br/>
			<input type="text" id="email" name="email" class="validate[required,custom[email]]" value="<?php echo re_value(re_row($row, 'email'));?>" /><br/><br/>
			<label>Branch:</label>
			<?php
			$_SQL=mysqli_query($con,"SELECT * FROM tblbranch");
			echo "<select id='branchid' name='branchid' class='validate[required]''>";
			while($rows=mysqli_fetch_array($_SQL,MYSQLI_ASSOC)){
				$_Selected=((string)$rows['branchid']===(string)re_row($row, 'branchid')) ? " selected" : "";
				echo "<option value='".re_value($rows['branchid'])."'".$_Selected.">".re_value($rows['location'])."</option>";
			}
			echo "</select>";
			?>
			<br/><br/>
<select id="recipient" name="recipient">
<option value="Teaching Staff"<?php echo re_selected(re_row($row, 'staffstatus'), 'Teaching Staff'); ?>>Teaching Staff</option>
<option value="Non-Teaching Staff"<?php echo re_selected(re_row($row, 'staffstatus'), 'Non-Teaching Staff'); ?>>Non-Teaching Staff</option>
<option value="Non Teaching Staff"<?php echo re_selected(re_row($row, 'staffstatus'), 'Non Teaching Staff'); ?>>Non Teaching Staff</option>
<option value="Student"<?php echo re_selected(re_row($row, 'staffstatus'), 'Student'); ?>>Student</option>
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
<script>
(function () {
  var birthday = document.getElementById("birthday");
  var age = document.getElementById("age");

  function syncAge() {
    if (!birthday || !age || !birthday.value) {
      if (age) { age.value = ""; }
      return;
    }

    var dob = new Date(birthday.value + "T00:00:00");
    if (isNaN(dob.getTime())) {
      age.value = "";
      return;
    }

    var today = new Date();
    var years = today.getFullYear() - dob.getFullYear();
    var monthDiff = today.getMonth() - dob.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
      years--;
    }
    age.value = years >= 0 ? years : "";
  }

  if (birthday) {
    birthday.addEventListener("change", syncAge);
    syncAge();
  }
}());
</script>
</div>
</body>
</html>
