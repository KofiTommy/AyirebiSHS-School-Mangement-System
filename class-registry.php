<?php
session_start();
$_SESSION['Message']="";
?>
<?php
include("dbstring.php");
@$_ClassId=$_POST['classid'];
@$_BatchId=$_POST["batchid"];
@$_UserId=$_POST['userid'];
@$_Class=$_POST['class'];
@$_Recordedby=$_SESSION['USERID'];

if(isset($_POST['register_class'])){
	$_SelectedUsers = array();
	if(isset($_POST['userid'])){
		if(is_array($_POST['userid'])){
			$_SelectedUsers = $_POST['userid'];
		}else if(trim((string)$_POST['userid']) !== ''){
			$_SelectedUsers = array($_POST['userid']);
		}
	}

	if($_Class=="" || $_BatchId==""){
		$_SESSION['Message']="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>Please select class and batch</div>";
	}
	else if(count($_SelectedUsers)==0)
	{
		$_SESSION['Message']="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>No student selected</div>";	
	}
	else{
		$_Success=0;
		$_Duplicate=0;
		$_Failed=0;
		$_SESSION['Message']="";

		foreach($_SelectedUsers as $selectedUserId){
			$_UserId = mysqli_real_escape_string($con, trim($selectedUserId));
			if($_UserId==""){ continue; }

			$_SQL_CHECK=mysqli_query($con,"SELECT classid FROM tblclass WHERE userid='$_UserId' AND batchid='$_BatchId' AND status='active'");
			if(mysqli_num_rows($_SQL_CHECK)>0){
				$_Duplicate++;
				continue;
			}

			include("code.php");
			$_ClassId=$code;
			$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblclass(classid,userid,class_entryid,batchid,datetimeentry,recordedby,status)
				VALUES('$_ClassId','$_UserId','$_Class','$_BatchId',NOW(),'$_Recordedby','active')");
			if($_SQL_EXECUTE){
				$_Success++;
			}
			else{
				$_Failed++;
			}
		}

		if($_Success>0){
			$_SESSION['Message'].="<div style='color:green;padding:5px;text-align:center;border:1px solid #aea;background-color:#efe;'>Class Successfully Registered for $_Success student(s)</div>";
		}
		if($_Duplicate>0){
			$_SESSION['Message'].="<div style='color:#8a6d3b;padding:5px;text-align:center;border:1px solid #faebcc;background-color:#fcf8e3;'>$_Duplicate student(s) skipped (already has class in selected batch)</div>";
		}
		if($_Failed>0){
			$_SESSION['Message'].="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>$_Failed student(s) failed to register</div>";
		}
	}
}
?>

<?php
include("dbstring.php");

if(isset($_GET["delete_class"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblclass WHERE classid='$_GET[delete_class]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>Class Successfully Deleted</div>";	

	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>Class failed to delete,Error:$_Error</div>";	
	}
}
?>

<html>
<head>
<?php
include("links.php");
?>
<script type="text/javascript">
function toggleAllStudents(){
  var selectAll = document.getElementById("all_students");
  var inputs = document.getElementsByName("userid[]");
  for(var i=0;i<inputs.length;i++){
    inputs[i].checked = selectAll.checked;
  }
}

function filterStudents(){
  var q = document.getElementById("student_filter").value.toLowerCase();
  var rows = document.querySelectorAll(".student-row");
  for(var i=0;i<rows.length;i++){
    var txt = rows[i].getAttribute("data-student");
    rows[i].style.display = txt.indexOf(q) !== -1 ? "" : "none";
  }
}
</script>

</head>
<body>

	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");
	?>		
	</div>
<div class="main-platform" style="">
	<table width="100%">
		<tr>
			<td valign="top" width="30%" align="center">
			<div class="form-entry" align="left">
			<h3>Group Class Registration 
				</h3>
			<br/>
			<form method="post" id="formID" name="formID" action="class-registry.php">
			<input type="hidden" id="classid" name="classid" value="<?php include("shortcode.php");echo $shortcode;?>" />
			
			<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry ORDER BY datetimeentry ASC");

			echo "<select id='class' name='class' class='validate[required]'>";
			echo "<option value=''>Select Class</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
				}	
			echo "</select><br/><br/>";
			?>

			<?php	
			$_SQL_3=mysqli_query($con,"SELECT * FROM tblbatch ORDER BY datetimeentry DESC");

			echo "<select id='batchid' name='batchid' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_3,MYSQLI_ASSOC)){
					echo "<option value='$row[batchid]'>$row[batch]</option>";
				}	
			echo "</select><br/><br/>";
			?>
			
			<label>Find Student</label><br/>
			<input type="text" id="student_filter" onkeyup="filterStudents()" placeholder="Type name or user id" style="width:98%;padding:6px;" /><br/><br/>

			<div style="max-height:320px;overflow:auto;border:1px solid #ddd;padding:8px;background:#fff;">
				<table width="100%">
					<thead>
						<tr>
							<th width="10%"><input type="checkbox" id="all_students" onclick="toggleAllStudents()" /></th>
							<th align="left">Student</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$_SQL_STUDENTS=mysqli_query($con,"SELECT userid,firstname,surname,othernames FROM tblsystemuser WHERE systemtype='Student' AND status='active' ORDER BY firstname ASC,surname ASC");
						while($stu=mysqli_fetch_array($_SQL_STUDENTS,MYSQLI_ASSOC)){
							$_FullName=trim($stu['firstname']." ".$stu['othernames']." ".$stu['surname']." (".$stu['userid'].")");
							$_IndexText=htmlspecialchars(strtolower($_FullName), ENT_QUOTES, 'UTF-8');
							$_UserIdSafe=htmlspecialchars($stu['userid'], ENT_QUOTES, 'UTF-8');
							$_FullNameSafe=htmlspecialchars($_FullName, ENT_QUOTES, 'UTF-8');
							echo "<tr class='student-row' data-student='$_IndexText'>";
							echo "<td><input type='checkbox' name='userid[]' value='$_UserIdSafe' /></td>";
							echo "<td>$_FullNameSafe</td>";
							echo "</tr>";
						}
						?>
					</tbody>
				</table>
			</div><br/>

			<div align="center"><button class="button-save" id="register_class" name="register_class"><i class="fa fa-save"></i> SAVE CLASS REGISTRY</button></div>
		</form>

		</div>
			</td>
			<td width="70%">
				<div class="form-entry" align="left">
				<?php
				echo $_SESSION['Message'];

				include("dbstring.php");
				$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE systemtype='Student' ORDER BY firstname ASC");

				//Registered clients
				echo "<table width='100%'>";
				echo "<caption>Class Registry</caption>";
				echo "<thead><th>*</th><th colspan=1>TASK</th><th>STUENT</th><th>CLASS</th><th>BATCH</th><th>ENTRY DATE/TIME</th></thead>";
				echo "<tbody>";
		
				@$serial=0;
				while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
				echo "<tr>";
					echo "<td align='center'>";
				echo $serial=$serial+1 .".";
				echo "</td>";

				echo "<td align='center' ><a title='View $row[firstname] ($row[userid])' href='class-registry.php?view_user=$row[userid]'<i class='fa fa-plus' style='color:royalblue'></i></a></td>";
				
					echo "<td colspan='4'>$row[firstname] $row[othernames] $row[surname] ($row[userid])</td>";
			
				echo "</tr>";

				$_SQL_CLASS=mysqli_query($con,"SELECT *,cl.datetimeentry FROM tblclass cl 
				INNER JOIN tblclassentry ce ON cl.class_entryid=ce.class_entryid
				INNER JOIN tblbatch bh ON cl.batchid=bh.batchid
				 WHERE  cl.userid='$row[userid]' AND cl.status='active' ORDER BY ce.class_name ASC");
				
				while($row_cl=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){

				echo "<tr style='background-color:#ffffff;border-bottom:1px solid gray'>";
				echo "<td colspan='1'>";
				echo "</td>";
				echo "<td align='center'><a onclick=\"javascript:return confirm('Do you want to remove class?')\" title='Remove class $row_cl[class_name]' href='class-registry.php?delete_class=$row_cl[classid]'<i class='fa fa-trash-o' style='color:red'></i></a></td>";
				echo "<td colspan='1' align='right'>";
				echo "Class:";
				echo "</td>";
				echo "<td colspan='1'>";
				echo $row_cl['class_name'];
				echo "</td>";

				echo "<td colspan='1'>";
				echo $row_cl["batch"];
				echo "</td>";

				echo "<td colspan='1'>";
				echo $row_cl['datetimeentry'];
				echo "</td>";
				echo "</tr>";

		}
		}
				echo "</tbody>";
				echo "</table>";
				?>
			</td>
		</tr>
	</table>


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
