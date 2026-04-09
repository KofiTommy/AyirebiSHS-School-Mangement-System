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

if(isset($_POST['save_house'])){
    @$_HouseName = trim($_POST['housename']);
    @$_Description = trim($_POST['description']);
    @$_RecordedBy = $_SESSION['USERID'];

    if($_HouseName === ""){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>House name is required.</div>";
    }else{
        $_HouseName = mysqli_real_escape_string($con, $_HouseName);
        $_Description = mysqli_real_escape_string($con, $_Description);
        $_RecordedBy = mysqli_real_escape_string($con, $_RecordedBy);

        $_CHK = mysqli_query($con, "SELECT houseid FROM tblhouse WHERE housename='$_HouseName' LIMIT 1");
        if($_CHK && mysqli_num_rows($_CHK) > 0){
            $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>House already exists.</div>";
        }else{
            include("code.php");
            $_HouseId = $code;
            $_SQL = mysqli_query($con, "INSERT INTO tblhouse(houseid,housename,description,status,datetimeentry,recordedby)
                VALUES('$_HouseId','$_HouseName','$_Description','active',NOW(),'$_RecordedBy')");
            if($_SQL){
                $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white'>House saved successfully.</div>";
            }else{
                $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Failed to save house: ".mysqli_error($con)."</div>";
            }
        }
    }
}

if(isset($_GET['deactivate_house'])){
    $_HouseId = mysqli_real_escape_string($con, $_GET['deactivate_house']);
    $_SQL = mysqli_query($con, "UPDATE tblhouse SET status='inactive' WHERE houseid='$_HouseId'");
    if($_SQL){
        $_SESSION['Message'] = "<div style='color:maroon;text-align:center;background-color:white'>House deactivated.</div>";
    }else{
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Failed to deactivate house: ".mysqli_error($con)."</div>";
    }
}

if(isset($_GET['delete_house'])){
    $_HouseId = mysqli_real_escape_string($con, $_GET['delete_house']);

    $_CHK_STUDENT = mysqli_query($con, "SELECT assignmentid FROM tblstudenthouse WHERE houseid='$_HouseId' AND status='active' LIMIT 1");
    $_CHK_MASTER = mysqli_query($con, "SELECT assignmentid FROM tblhousemaster WHERE houseid='$_HouseId' AND status='active' LIMIT 1");
    $_CHK_EXEAT = mysqli_query($con, "SELECT exeatid FROM tblexeatrequest WHERE houseid='$_HouseId' LIMIT 1");

    if(($_CHK_STUDENT && mysqli_num_rows($_CHK_STUDENT) > 0) || ($_CHK_MASTER && mysqli_num_rows($_CHK_MASTER) > 0)){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Cannot delete house: remove active student and house-master assignments first.</div>";
    }elseif($_CHK_EXEAT && mysqli_num_rows($_CHK_EXEAT) > 0){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Cannot delete house: exeat history exists for this house.</div>";
    }else{
        $_SQL_D = mysqli_query($con, "DELETE FROM tblhouse WHERE houseid='$_HouseId'");
        if($_SQL_D){
            $_SESSION['Message'] = "<div style='color:maroon;text-align:center;background-color:white'>House deleted successfully.</div>";
        }else{
            $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Failed to delete house: ".mysqli_error($con)."</div>";
        }
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
<h3>House Entry</h3>
<?php echo $_SESSION['Message']; ?>
<form method="post" action="house-entry.php" id="formID" name="formID">
<label>House Name</label><br/>
<input type="text" id="housename" name="housename" class="validate[required]" /><br/><br/>
<label>Description</label><br/>
<textarea id="description" name="description"></textarea><br/><br/>
<div align="center"><button class="button-save" id="save_house" name="save_house"><i class="fa fa-save"></i> Save House</button></div>
</form>
</div>
</td>
<td width="70%" valign="top">
<div class="form-entry">
<?php
$_SQL_H = mysqli_query($con,"SELECT * FROM tblhouse ORDER BY datetimeentry DESC");
echo "<table width='100%' style='background-color:white'>";
echo "<caption>Houses</caption>";
echo "<thead><th>Task</th><th>House</th><th>Description</th><th>Status</th><th>Date/Time</th></thead>";
echo "<tbody>";
while($row=mysqli_fetch_array($_SQL_H,MYSQLI_ASSOC)){
    echo "<tr>";
    echo "<td align='center'>";
    if($row['status'] === 'active'){
        echo "<a title='Deactivate house' onclick=\"javascript:return confirm('Deactivate this house?');\" href='house-entry.php?deactivate_house=$row[houseid]'><i class='fa fa-ban' style='color:#b45309'></i></a> ";
    }
    echo "<a title='Delete house' onclick=\"javascript:return confirm('Delete this house permanently?');\" href='house-entry.php?delete_house=$row[houseid]'><i class='fa fa-trash' style='color:#b91c1c'></i></a>";
    echo "</td>";
    echo "<td>".htmlspecialchars($row['housename'])."</td>";
    echo "<td>".htmlspecialchars($row['description'])."</td>";
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
