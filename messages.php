<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
include("check-login.php");
include("code.php");
@$_MessageId=$code;
@$_Message=$_POST['message'];

$__CurrentUserId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";
$__CurrentUserIdEsc = mysqli_real_escape_string($con, $__CurrentUserId);
$__AudienceOptions = um_message_audience_options_for_current_user();
$__DefaultAudience = um_message_default_audience_for_current_user();
$__VisibilitySql = um_message_visibility_sql('mg.recipient_group');
$__CanManageAllMessages = um_is_admin_manager();

if(isset($_POST["send_message"])){
$_ChosenAudience = isset($_POST['message_audience']) ? um_message_normalize_audience($_POST['message_audience']) : $__DefaultAudience;
if(isset($_SESSION['SYSTEMTYPE']) && $_SESSION['SYSTEMTYPE'] === 'Student'){
	$_ChosenAudience = 'teachers';
}
$_ChosenAudienceEsc = mysqli_real_escape_string($con, $_ChosenAudience);
$_MessageEsc = mysqli_real_escape_string($con, (string)$_Message);
$_SQL=mysqli_query($con,"INSERT INTO tblmessages(messageid,messages,datetimeentry,status,sentby,recipient_group)
VALUES('$_MessageId','$_MessageEsc',NOW(),'active','$_SESSION[USERID]','$_ChosenAudienceEsc')");
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
$_MessageIdEsc = mysqli_real_escape_string($con, (string)$_GET['delete_message']);
$_DeleteWhere = $__CanManageAllMessages ? "messageid='$_MessageIdEsc'" : "messageid='$_MessageIdEsc' AND sentby='$__CurrentUserIdEsc'";
$_SQL_D=mysqli_query($con,"DELETE FROM tblmessages WHERE $_DeleteWhere LIMIT 1");
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
	<!--Header-->
	
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");

	?>		
	</div>
<div class="main-platform" align="center" >
	<br/><br/>
	<table border="0" width="100%">
		<tr>
			<td width="25%" valign="top">
	
			<?php
			include("welcome.php");
			?>	
			</td>

			<td width="50%" valign="top" align="center">
				
				<h4 align="left">MESSAGES</h4>
				
				<?php
				echo $_SESSION['Message'];
				?>
<div class="form-entry" align="center">
	<?php
	include("dbstring.php");
	$_SQL_Msg=mysqli_query($con,"SELECT * FROM tblmessages WHERE sentby='$_SESSION[USERID]' ORDER BY datetimeentry DESC");
	while($row=mysqli_fetch_array($_SQL_Msg,MYSQLI_ASSOC)){
		echo "<div style='padding:10px;border-bottom:1px solid #ddd;text-align:justify'>";
		echo $row['messages'];
		echo "<br/><strong style='color:#0f766e;font-size:11px;'>".htmlspecialchars(um_message_audience_label(isset($row['recipient_group']) ? $row['recipient_group'] : 'all'), ENT_QUOTES, 'UTF-8')."</strong>";
		echo "<br/><strong style='color:darkblue;font-size:10px;;text-align:right'> $row[datetimeentry] </strong>";

		echo "<div style='color:red;text-align:right'><a href='messages.php?delete_message=$row[messageid]'><i class='fa fa-times' style='color:red'></i></a></div>";
		echo "</div><br/><br/>";
	}


	?>
			
<h3>SEND MESSAGE 
</h3>
	
			<form method="post" id="formID" name="formID">

			<input type="hidden" id="userid" name="userid" value="<?php echo $_SESSION['USERID'];?>" class="validate[required]" readonly/>

			<label>Message</label><br/>
			<textarea id="message" name="message" style="background-color:white;"></textarea><br/><br/>
			<?php if(count($__AudienceOptions) > 1){ ?>
			<label>Send To</label><br/>
			<select id="message_audience" name="message_audience" style="background-color:white;">
				<?php foreach($__AudienceOptions as $__AudienceValue => $__AudienceLabel){ ?>
				<option value="<?php echo htmlspecialchars($__AudienceValue, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($__AudienceValue === $__DefaultAudience ? " selected" : ""); ?>><?php echo htmlspecialchars($__AudienceLabel, ENT_QUOTES, 'UTF-8'); ?></option>
				<?php } ?>
			</select><br/><br/>
			<?php }else{ ?>
			<div style="text-align:left;color:#0f766e;padding:8px 0;">Your message will go to teachers and school office only.</div>
			<?php } ?>
			
			<div align="right"><button class="button-pay" id="send_message" name="send_message"><i class="fa fa-send"></i> SEND</button></div>
		</form>

		</div>
</td>
<td width="25%" valign="top" align="center">
<?php
	include("dbstring.php");
	$_SQL_Msg=mysqli_query($con,"SELECT * FROM tblmessages mg INNER JOIN tblsystemuser su 
		ON mg.sentby=su.userid WHERE mg.status='active' AND $__VisibilitySql ORDER BY mg.datetimeentry DESC");
	while($row=mysqli_fetch_array($_SQL_Msg,MYSQLI_ASSOC)){
		echo "<div style='padding:10px;border-bottom:1px solid #ddd;text-align:justify'>";
		echo "<p>". $row['messages'] ."</p>";
		echo "<div style='color:#0f766e;font-size:11px;font-weight:bold;'>".htmlspecialchars(um_message_audience_label(isset($row['recipient_group']) ? $row['recipient_group'] : 'all'), ENT_QUOTES, 'UTF-8')."</div>";
		echo "<br/><br/><strong style='color:darkblue;font-size:12px;;text-align:right'> Posted by $row[firstname] $row[othernames] $row[surname], $row[datetimeentry] </strong>";

		if($row['sentby']==$_SESSION['USERID'] || $__CanManageAllMessages){
		echo "<div style='color:red;text-align:right'><a href='messages.php?delete_message=$row[messageid]'><i class='fa fa-times' style='color:red'></i></a></div>";
		}
		echo "</div><br/><br/>";
	}
?>
</td>
</tr>
</table>
</div>
</body>
</html>
