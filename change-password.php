<?php
session_start();
$_SESSION['Message']="";
?>

<?php
include("dbstring.php");
include("audit_notifications.php");
@$_Oldpassword=md5($_POST['oldpassword']);
@$_NewUsername=$_POST['username'];
@$_Newpassword=md5($_POST['newpassword']);

if(isset($_POST['update_account'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsystemuser 
	SET username='$_NewUsername',password='$_Newpassword' 
	WHERE userid='$_SESSION[USERID]' AND password='$_Oldpassword'");
if($_SQL_EXECUTE){
    if (mysqli_affected_rows($con) > 0) {
        logSystemChange(
            $con,
            "PASSWORD_CHANGE",
            "Password was changed by ".$_SESSION['SYSTEMTYPE']." user."
        );
    }
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Account Successfully Updated<br/><br/><a href='index.php' style='color:blue'>Login</a><br/><br/></div>";

	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Account failed to update,$_Error</div>";
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
<td valign="top" width="25%" style="border:1px solid #fff">
</td>
<td valign="top" width="50%" align="center" style="border:1px solid #fff">
	<?php
	echo $_SESSION['Message'];
	?>
<div class="form-entry" align="left">
			
<h3>Change Password 
</h3>
<br/>
	
			<form method="post" id="formID" name="formID" action="change-password.php">

			<label>User Id</label><br/>
			<input type="text" id="userid" name="userid" value="<?php echo $_SESSION['USERID'];?>" class="validate[required]" readonly/><br/><br/>

			<label>Old Password</label><br/>
			<input type="password" id="oldpassword" name="oldpassword" value="" class="validate[required]" placeholder="Type Old Password"/><br/><br/>

			<label>New Username</label><br/>
			<input type="text" id="username" name="username" value="" placeholder="Type New Username" /><br/><br/>

			<label>New Password</label><br/>
			<input type="password" id="newpassword" name="newpassword" value="" class="validate[required]" placeholder="Type New Password"/><br/><br/>

			<label>Repeat Password</label><br/>
			<input type="password" id="repeatpassword" name="repeatpassword" value="" class="validate[required,equals[newpassword]]" placeholder="Repeat Password"/><br/><br/>
			<div align="center"><button class="button-edit" id="update_account" name="update_account"><i class="fa fa-edit"></i> UPDATE ACCOUNT</button></div>
		</form>
</div>
</td>
<td valign="top" width="25%" style="border:1px solid #fff">
</td>
</tr>
</table>	
</div>
</body>
</html>
