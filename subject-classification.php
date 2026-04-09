<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
@$_SubjectId=$_POST['subjectid'];
@$_ClassId=$_POST['classid'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_subject_classification']))
{
//Check if a subject is selected
@$_CountSubject=0;
foreach ($_SubjectId as $_Selected_Subject) {
$_CountSubject=$_CountSubject+1;
}
if($_CountSubject==0){
$_SESSION['Message']="<div style='background-color:#eff;color:red;border:1px solid #4dd;padding:5px;'>No subject selected</div>";
}
else{
	foreach ($_SubjectId as $_Selected_Subject) 
	{
		include("code.php");
	@$_ClassificationId=$code;
	@$_Subject="";
	//Check if subject already registered
	$_SQL_EXECUTE_SUBJECT=mysqli_query($con,"SELECT * FROM tblsubject WHERE subjectid='$_Selected_Subject'");
	if($row_s=mysqli_fetch_array($_SQL_EXECUTE_SUBJECT,MYSQLI_ASSOC)){
	$_Subject=$row_s['subject'];
	}

	$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tblsubjectclassification sc WHERE sc.classid='$_ClassId' AND sc.subjectid='$_Selected_Subject'");
	if(mysqli_num_rows($_SQL_EXECUTE_2)>0){
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;text-align:left;background-color:white'><i class='fa fa-check' style='color:red'></i> $_Subject Already Classified</div>";
		
	}else{

	$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblsubjectclassification(classificationid,classid,subjectid,status,datetimeentry,recordedby)
	VALUES('$_ClassificationId','$_ClassId','$_Selected_Subject','active',NOW(),'$_Recordedby')");
	if($_SQL_EXECUTE)
	{
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:left;background-color:white'><i class='fa fa-check' style='color:green'></i> $_Subject Successfully Classified</div>";
		}
		else{
			$_Error=mysqli_error($con);
			$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>$_Subject failed to classify,$_Error</div>";
		}
	}	
	}
  }
}
?>

<?php
include("dbstring.php");
@$_Update_subject=$_POST['update_item'];
@$_Update_subjectid=$_POST['update_subjectid'];

if(isset($_POST['update_item_entry'])){
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsubject SET subject='$_Update_subject' WHERE subjectid='$_Update_subjectid'");
if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Subject Successfully Updated</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>Subject failed to update,$_Error</div>";
	}
}
?>

<?php
include("dbstring.php");

if(isset($_GET["delete_class"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblsubjectclassification WHERE classificationid='$_GET[delete_class]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:maroon;text-align:center;background-color:white'>Class Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;text-align:center'>Class failed to delete,Error:$_Error</div>";
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
   var inputs = document.getElementsByName("subjectid[]");
    for(var i=0;i<inputs.length;i++){
     inputs[i].checked=false;
    }
     return false;
 }
  function checkBox(){
var inputs = document.getElementsByName("subjectid[]");
    for(var i=0;i<inputs.length;i++){
     inputs[i].checked=true;
    }
 return false;
 }
</script>

</head>
<body>
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");
	?>		
	<?php
	//include("side-menu.php");
	?>
	</div>
<div class="main-platform" style="">
	<table width="100%">
		<tr>
			<td valign="top" width="30%" align="center">
				<div class="form-entry" align="left">
			
		<?php
		include("dbstring.php");
		if(isset($_GET['edit_item']))
		{
		$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsubject WHERE subjectid='$_GET[edit_item]'");
		$_Count=mysqli_num_rows($_SQL_EXECUTE);
		if($_Count>0)
		{
			if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
			{
			echo "<h3>Item Update</h3>";
			echo "<form method='post' id='formID' name='formID' action='subject-classification.php'>";
			echo "<label>Item Id</label>";
			echo "<input type='text' id='update_subjectid' name='update_subjectid' value='$row[subjectid]'><br/>";

			echo "<label>Item </label>";
			echo "<input type='text' id='update_item' name='update_item' value='$row[subject]'><br/><br/>";
			echo "<div align='center'><button class='btn' id='update_item_entry' name='update_item_entry'><i class='fa fa-edit' style='color:white'></i> Update</button></div>";

			echo "</form>";
			}
		}
		}
		?>
			<h3>Subject Classification 
				</h3>
			<br/>
		
<form method="post" id="formID" name="formID" action="subject-classification.php">
		<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry ORDER BY class_name ASC");

			echo "<select id='classid' name='classid' class='validate[required]'>";
			echo "<option value=''>Select Class</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
				}				
			echo "</select><br/><br/>";
			?>
			<div align="center"><button class="button-save" id="register_subject_classification" name="register_subject_classification"><i class="fa fa-save"></i> SAVE CLASSIFICATION</button></div>
		
		</div>
			</td>
			<td width="70%">
			<div class="form-entry" align="left">
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsubject ORDER BY subject ASC");

				//Registered clients
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>List Of Items</caption>";
				echo "<thead><th colspan=1><input type='checkbox' id='all' name='name' onclick='selectAll()'/></th><th>Subject Id</th><th>Subject</th><th>Entry Date/Time</th><th>Status</th></thead>";
				echo "<tbody>";
				
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr  style='background-color:#ffffff;border-bottom:1px solid #cccccc'>";
				//echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-book' style='color:blue'></i></a></td>";
				echo "<td align='center'>";
				echo "<input type='checkbox' id='subjectid' name='subjectid[]' value='$row[subjectid]'>";
				echo "</td>";
				echo "<td align='center'>";
				echo $row['subjectid'];
				echo "</td>";
				echo "<td align='left'>$row[subject]</td>";
				echo "<td align='center'>$row[datetimeentry]</td>";
				//echo "<td>$row[recordedby]</td>";
				echo "<td align='center'>$row[status]</td>";
				echo "</tr>";
				$_SQL_CLASS=mysqli_query($con,"SELECT *,sc.datetimeentry FROM tblsubjectclassification sc INNER JOIN tblclassentry ce
				ON sc.classid=ce.class_entryid
				 WHERE sc.subjectid='$row[subjectid]' ORDER BY ce.datetimeentry ASC");
				@$serial=0;
				while($row_cl=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){
				echo "<tr style='background-color:#ffffff'>";
				
				echo "<td align='center'>";
				echo $serial=$serial+1 .".";
				echo "</td>";

				echo "<td align='center'><a onclick=\"javascript:return confirm('Do you want to remove?')\" title='Remove $row_cl[class_name] ($row_cl[classid])' href='subject-classification.php?delete_class=$row_cl[classificationid]'<i class='fa fa-trash-o' style='color:red'></i></a></td>";
				
		
				echo "<td colspan='1'>";
				echo $row_cl['class_name'];
				echo "</td>";
				echo "<td align='center'>$row_cl[datetimeentry]</td>";
				echo "<td colspan='1'>";
				
				echo "</td>";
				echo "</tr>";	
				}	
				}
				echo "</tbody>";
				echo "</table>";
?>
</div>
</td>
</tr>
</table>
</form>


</div>
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
</body>
</html>