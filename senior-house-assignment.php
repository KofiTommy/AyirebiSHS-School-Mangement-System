<?php
session_start();
$_SESSION['Message'] = "";
include("check-login.php");
include("dbstring.php");
include("house-master-utils.php");
ensure_house_tables($con);

if(!house_master_is_admin()){
    header("location:".house_master_landing_page());
    exit();
}

if(isset($_POST['save_senior_house'])){
    @$_TeacherId = $_POST['userid'];
    @$_Designation = $_POST['designation'];
    @$_RecordedBy = $_SESSION['USERID'];

    $_Designation = house_master_normalize_senior_designation($_Designation);

    if(!$_TeacherId || !$_Designation){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Please select teacher and designation.</div>";
    }else{
        $_TeacherId = mysqli_real_escape_string($con, $_TeacherId);
        $_DesignationEsc = mysqli_real_escape_string($con, $_Designation);
        $_RecordedBy = mysqli_real_escape_string($con, $_RecordedBy);

        $_TeacherExists = mysqli_query($con, "SELECT userid FROM tblsystemuser WHERE userid='$_TeacherId' AND systemtype='Teacher' AND status='active' LIMIT 1");
        if(!$_TeacherExists || mysqli_num_rows($_TeacherExists) === 0){
            $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Selected teacher is not active.</div>";
        }else{
            $_EXIST = mysqli_query($con, "SELECT assignmentid,userid FROM tblseniorhouseauthority WHERE designation='$_DesignationEsc' AND status='active' LIMIT 1");
            if($_EXIST && $row_exist=mysqli_fetch_array($_EXIST, MYSQLI_ASSOC)){
                $_AssignmentId = $row_exist['assignmentid'];
                $_UPD = mysqli_query($con, "UPDATE tblseniorhouseauthority
                    SET userid='$_TeacherId', recordedby='$_RecordedBy', datetimeentry=NOW(), status='active'
                    WHERE assignmentid='$_AssignmentId'");
                if($_UPD){
                    notify_senior_house_assignment($con, $_TeacherId, $_Designation, $_RecordedBy, "updated");
                    $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white'>Senior house role updated successfully.</div>";
                }else{
                    $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Update failed: ".mysqli_error($con)."</div>";
                }
            }else{
                include("code.php");
                $_AssignmentId = $code;
                $_INS = mysqli_query($con, "INSERT INTO tblseniorhouseauthority(assignmentid,userid,designation,status,datetimeentry,recordedby)
                    VALUES('$_AssignmentId','$_TeacherId','$_DesignationEsc','active',NOW(),'$_RecordedBy')");
                if($_INS){
                    notify_senior_house_assignment($con, $_TeacherId, $_Designation, $_RecordedBy, "assigned");
                    $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white'>Senior house role assigned successfully.</div>";
                }else{
                    $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Assignment failed: ".mysqli_error($con)."</div>";
                }
            }
        }
    }
}

if(isset($_GET['deactivate_assignment'])){
    $_AssignmentId = mysqli_real_escape_string($con, $_GET['deactivate_assignment']);
    $_SQL = mysqli_query($con, "UPDATE tblseniorhouseauthority SET status='inactive' WHERE assignmentid='$_AssignmentId'");
    if($_SQL){
        $_SESSION['Message'] = "<div style='color:maroon;text-align:center;background-color:white'>Senior house assignment deactivated.</div>";
    }else{
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Failed to deactivate assignment: ".mysqli_error($con)."</div>";
    }
}

if(isset($_GET['delete_assignment'])){
    $_AssignmentId = mysqli_real_escape_string($con, $_GET['delete_assignment']);
    $_SQL_D = mysqli_query($con, "DELETE FROM tblseniorhouseauthority WHERE assignmentid='$_AssignmentId'");
    if($_SQL_D){
        $_SESSION['Message'] = "<div style='color:maroon;text-align:center;background-color:white'>Senior house assignment deleted successfully.</div>";
    }else{
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Failed to delete assignment: ".mysqli_error($con)."</div>";
    }
}
?>
<html>
<head>
<?php include("links.php"); ?>
</head>
<body>
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform">
<table width="100%">
<tr>
<td width="30%" valign="top">
<div class="form-entry" align="left">
<h3>Senior House Assignment</h3>
<?php echo $_SESSION['Message']; ?>
<form method="post" action="senior-house-assignment.php" id="formID" name="formID">
<?php
$_SQL_T = mysqli_query($con, "SELECT userid,firstname,surname,othernames FROM tblsystemuser WHERE systemtype='Teacher' AND status='active' ORDER BY firstname ASC");
echo "<label>Teacher</label><br/>";
echo "<select id='userid' name='userid' class='validate[required]'>";
echo "<option value=''>Select Teacher</option>";
while($row_t=mysqli_fetch_array($_SQL_T, MYSQLI_ASSOC)){
    echo "<option value='$row_t[userid]'>$row_t[firstname] $row_t[othernames] $row_t[surname] ($row_t[userid])</option>";
}
echo "</select><br/><br/>";
?>
<label>Designation</label><br/>
<select id="designation" name="designation" class="validate[required]">
    <option value="">Select Designation</option>
    <option value="Senior House Master">Senior House Master</option>
    <option value="Senior House Mistress">Senior House Mistress</option>
</select><br/><br/>
<div align="center"><button class="button-save" id="save_senior_house" name="save_senior_house"><i class="fa fa-save"></i> Save Assignment</button></div>
</form>
</div>
</td>
<td width="70%" valign="top">
<div class="form-entry">
<?php
$_SQL_A = mysqli_query($con, "SELECT sha.*,su.firstname,su.surname,su.othernames
FROM tblseniorhouseauthority sha
INNER JOIN tblsystemuser su ON su.userid=sha.userid
ORDER BY
    CASE WHEN sha.designation='Senior House Master' THEN 0 ELSE 1 END ASC,
    sha.datetimeentry DESC");
echo "<table width='100%' style='background-color:white'>";
echo "<caption>Assigned Senior House Officials</caption>";
echo "<thead><th>Task</th><th>Designation</th><th>Teacher</th><th>Status</th><th>Date/Time</th></thead>";
echo "<tbody>";
while($row=mysqli_fetch_array($_SQL_A, MYSQLI_ASSOC)){
    echo "<tr>";
    echo "<td align='center'>";
    if($row['status'] === 'active'){
        echo "<a title='Deactivate assignment' onclick=\"javascript:return confirm('Deactivate this senior house assignment?');\" href='senior-house-assignment.php?deactivate_assignment=$row[assignmentid]'><i class='fa fa-ban' style='color:#b45309'></i></a> ";
    }
    echo "<a title='Delete assignment' onclick=\"javascript:return confirm('Delete this senior house assignment permanently?');\" href='senior-house-assignment.php?delete_assignment=$row[assignmentid]'><i class='fa fa-trash' style='color:#b91c1c'></i></a>";
    echo "</td>";
    echo "<td>".htmlspecialchars($row['designation'])."</td>";
    echo "<td>".htmlspecialchars($row['firstname']." ".$row['othernames']." ".$row['surname'])." (".htmlspecialchars($row['userid']).")</td>";
    echo "<td align='center'>".htmlspecialchars($row['status'])."</td>";
    echo "<td align='center'>".htmlspecialchars($row['datetimeentry'])."</td>";
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
