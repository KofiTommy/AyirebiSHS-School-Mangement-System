<?php
session_start();
$_SESSION['Message']="";
include("check-login.php");
include("dbstring.php");
include("house-master-utils.php");
ensure_house_tables($con);

if(!house_master_is_admin()){
    header("location:".house_master_landing_page());
    exit();
}

if(isset($_POST['save_house_master'])){
    @$_TeacherId = $_POST['userid'];
    @$_HouseId = $_POST['houseid'];
    @$_RecordedBy = $_SESSION['USERID'];

    if(!$_TeacherId || !$_HouseId){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Please select teacher and house.</div>";
    }else{
        $_TeacherId = mysqli_real_escape_string($con, $_TeacherId);
        $_HouseId = mysqli_real_escape_string($con, $_HouseId);
        $_RecordedBy = mysqli_real_escape_string($con, $_RecordedBy);

        $_EXIST = mysqli_query($con,"SELECT assignmentid FROM tblhousemaster WHERE houseid='$_HouseId' AND status='active' LIMIT 1");
        $_HouseName = "";
        $_SQL_HN = mysqli_query($con,"SELECT housename FROM tblhouse WHERE houseid='$_HouseId' LIMIT 1");
        if($_SQL_HN && $row_hn=mysqli_fetch_array($_SQL_HN,MYSQLI_ASSOC)){
            $_HouseName = $row_hn['housename'];
        }
        if($_EXIST && $row_exist=mysqli_fetch_array($_EXIST,MYSQLI_ASSOC)){
            $_AssignmentId = $row_exist['assignmentid'];
            $_UPD = mysqli_query($con,"UPDATE tblhousemaster SET userid='$_TeacherId', recordedby='$_RecordedBy', datetimeentry=NOW(), status='active' WHERE assignmentid='$_AssignmentId'");
            if($_UPD){
                if($_HouseName !== ""){
                    notify_house_master_assignment($con, $_TeacherId, $_HouseName, $_RecordedBy, "updated");
                }
                $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white'>House master assignment updated.</div>";
            }else{
                $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Update failed: ".mysqli_error($con)."</div>";
            }
        }else{
            include("code.php");
            $_AssignmentId = $code;
            $_INS = mysqli_query($con,"INSERT INTO tblhousemaster(assignmentid,houseid,userid,status,datetimeentry,recordedby)
                VALUES('$_AssignmentId','$_HouseId','$_TeacherId','active',NOW(),'$_RecordedBy')");
            if($_INS){
                if($_HouseName !== ""){
                    notify_house_master_assignment($con, $_TeacherId, $_HouseName, $_RecordedBy, "assigned");
                }
                $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white'>House master assigned successfully.</div>";
            }else{
                $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Assignment failed: ".mysqli_error($con)."</div>";
            }
        }
    }
}

if(isset($_GET['deactivate_assignment'])){
    $_AssignmentId = mysqli_real_escape_string($con, $_GET['deactivate_assignment']);
    $_SQL = mysqli_query($con, "UPDATE tblhousemaster SET status='inactive' WHERE assignmentid='$_AssignmentId'");
    if($_SQL){
        $_SESSION['Message'] = "<div style='color:maroon;text-align:center;background-color:white'>Assignment deactivated.</div>";
    }else{
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Failed to deactivate assignment: ".mysqli_error($con)."</div>";
    }
}

if(isset($_GET['delete_assignment'])){
    $_AssignmentId = mysqli_real_escape_string($con, $_GET['delete_assignment']);
    $_SQL_D = mysqli_query($con, "DELETE FROM tblhousemaster WHERE assignmentid='$_AssignmentId'");
    if($_SQL_D){
        $_SESSION['Message'] = "<div style='color:maroon;text-align:center;background-color:white'>House master assignment deleted successfully.</div>";
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
<h3>House Master Assignment</h3>
<?php echo $_SESSION['Message']; ?>
<form method="post" action="house-master-assignment.php" id="formID" name="formID">
<?php
$_SQL_T = mysqli_query($con,"SELECT userid,firstname,surname,othernames FROM tblsystemuser WHERE systemtype='Teacher' AND status='active' ORDER BY firstname ASC");
echo "<label>Teacher</label><br/>";
echo "<select id='userid' name='userid' class='validate[required]'>";
echo "<option value=''>Select Teacher</option>";
while($row_t=mysqli_fetch_array($_SQL_T,MYSQLI_ASSOC)){
    echo "<option value='$row_t[userid]'>$row_t[firstname] $row_t[othernames] $row_t[surname] ($row_t[userid])</option>";
}
echo "</select><br/><br/>";

$_SQL_H = mysqli_query($con,"SELECT houseid,housename FROM tblhouse WHERE status='active' ORDER BY housename ASC");
echo "<label>House</label><br/>";
echo "<select id='houseid' name='houseid' class='validate[required]'>";
echo "<option value=''>Select House</option>";
while($row_h=mysqli_fetch_array($_SQL_H,MYSQLI_ASSOC)){
    echo "<option value='$row_h[houseid]'>$row_h[housename]</option>";
}
echo "</select><br/><br/>";
?>
<div align="center"><button class="button-save" id="save_house_master" name="save_house_master"><i class="fa fa-save"></i> Save Assignment</button></div>
</form>
</div>
</td>
<td width="70%" valign="top">
<div class="form-entry">
<?php
$_SQL_A = mysqli_query($con,"SELECT hm.*,h.housename,su.firstname,su.surname,su.othernames
FROM tblhousemaster hm
INNER JOIN tblhouse h ON h.houseid=hm.houseid
INNER JOIN tblsystemuser su ON su.userid=hm.userid
ORDER BY hm.datetimeentry DESC");
echo "<table width='100%' style='background-color:white'>";
echo "<caption>Assigned House Masters</caption>";
echo "<thead><th>Task</th><th>House</th><th>House Master</th><th>Status</th><th>Date/Time</th></thead>";
echo "<tbody>";
while($row=mysqli_fetch_array($_SQL_A,MYSQLI_ASSOC)){
    echo "<tr>";
    echo "<td align='center'>";
    if($row['status']==='active'){
        echo "<a title='Deactivate assignment' onclick=\"javascript:return confirm('Deactivate this assignment?');\" href='house-master-assignment.php?deactivate_assignment=$row[assignmentid]'><i class='fa fa-ban' style='color:#b45309'></i></a> ";
    }
    echo "<a title='Delete assignment' onclick=\"javascript:return confirm('Delete this house-master assignment permanently?');\" href='house-master-assignment.php?delete_assignment=$row[assignmentid]'><i class='fa fa-trash' style='color:#b91c1c'></i></a>";
    echo "</td>";
    echo "<td>".htmlspecialchars($row['housename'])."</td>";
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
