<?php
session_start();
include("check-login.php");
include("dbstring.php");
include("house-master-utils.php");
ensure_house_tables($con);

if(!house_master_is_admin()){
    header("location:".house_master_landing_page());
    exit();
}

$_Message = isset($_SESSION['Message']) ? (string)$_SESSION['Message'] : "";
unset($_SESSION['Message']);

$_Form = array(
    "houseid" => "",
    "housename" => "",
    "description" => ""
);
$_EditingHouse = false;

if(isset($_GET['edit_house'])){
    $_EditHouseId = mysqli_real_escape_string($con, trim((string)$_GET['edit_house']));
    if($_EditHouseId !== ""){
        $_EDIT_RES = mysqli_query($con, "SELECT houseid,housename,description FROM tblhouse WHERE houseid='$_EditHouseId' LIMIT 1");
        if($_EDIT_RES && ($_EDIT_ROW = mysqli_fetch_array($_EDIT_RES, MYSQLI_ASSOC))){
            $_Form["houseid"] = (string)$_EDIT_ROW["houseid"];
            $_Form["housename"] = (string)$_EDIT_ROW["housename"];
            $_Form["description"] = (string)$_EDIT_ROW["description"];
            $_EditingHouse = true;
        }else{
            $_Message = "<div style='color:red;text-align:center;background-color:white'>The selected house could not be found.</div>";
        }
    }
}

if(isset($_POST['save_house'])){
    $_Form["houseid"] = trim((string)(isset($_POST['houseid']) ? $_POST['houseid'] : ""));
    $_Form["housename"] = trim((string)(isset($_POST['housename']) ? $_POST['housename'] : ""));
    $_Form["description"] = trim((string)(isset($_POST['description']) ? $_POST['description'] : ""));
    $_RecordedBy = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";
    $_EditingHouse = $_Form["houseid"] !== "";

    if($_Form["housename"] === ""){
        $_Message = "<div style='color:red;text-align:center;background-color:white'>House name is required.</div>";
    }else{
        $_HouseIdEsc = mysqli_real_escape_string($con, $_Form["houseid"]);
        $_HouseNameEsc = mysqli_real_escape_string($con, $_Form["housename"]);
        $_DescriptionEsc = mysqli_real_escape_string($con, $_Form["description"]);
        $_RecordedByEsc = mysqli_real_escape_string($con, $_RecordedBy);

        $_CHK_SQL = "SELECT houseid FROM tblhouse WHERE housename='$_HouseNameEsc'";
        if($_EditingHouse){
            $_CHK_SQL .= " AND houseid<>'$_HouseIdEsc'";
        }
        $_CHK_SQL .= " LIMIT 1";
        $_CHK = mysqli_query($con, $_CHK_SQL);
        if($_CHK && mysqli_num_rows($_CHK) > 0){
            $_Message = "<div style='color:red;text-align:center;background-color:white'>House already exists.</div>";
        }else{
            if($_EditingHouse){
                $_SQL = mysqli_query($con, "UPDATE tblhouse
                    SET housename='$_HouseNameEsc', description='$_DescriptionEsc'
                    WHERE houseid='$_HouseIdEsc'
                    LIMIT 1");
                if($_SQL){
                    $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white'>House updated successfully. Existing assignments remain intact.</div>";
                    header("location:house-entry.php");
                    exit();
                }
                $_Message = "<div style='color:red;text-align:center;background-color:white'>Failed to update house: ".mysqli_error($con)."</div>";
            }else{
                include("code.php");
                $_HouseId = $code;
                $_SQL = mysqli_query($con, "INSERT INTO tblhouse(houseid,housename,description,status,datetimeentry,recordedby)
                    VALUES('$_HouseId','$_HouseNameEsc','$_DescriptionEsc','active',NOW(),'$_RecordedByEsc')");
                if($_SQL){
                    $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white'>House saved successfully.</div>";
                    header("location:house-entry.php");
                    exit();
                }
                $_Message = "<div style='color:red;text-align:center;background-color:white'>Failed to save house: ".mysqli_error($con)."</div>";
            }
        }
    }
}

if(isset($_GET['deactivate_house'])){
    $_HouseId = mysqli_real_escape_string($con, trim((string)$_GET['deactivate_house']));
    if($_HouseId !== ""){
        $_SQL = mysqli_query($con, "UPDATE tblhouse SET status='inactive' WHERE houseid='$_HouseId'");
        if($_SQL){
            $_SESSION['Message'] = "<div style='color:maroon;text-align:center;background-color:white'>House deactivated.</div>";
        }else{
            $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Failed to deactivate house: ".mysqli_error($con)."</div>";
        }
    }
    header("location:house-entry.php");
    exit();
}

if(isset($_GET['delete_house'])){
    $_HouseId = mysqli_real_escape_string($con, trim((string)$_GET['delete_house']));

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
    header("location:house-entry.php");
    exit();
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
<?php echo $_Message; ?>
<form method="post" action="house-entry.php" id="formID" name="formID">
<input type="hidden" name="houseid" value="<?php echo htmlspecialchars($_Form['houseid'], ENT_QUOTES, 'UTF-8'); ?>" />
<label>House Name</label><br/>
<input type="text" id="housename" name="housename" class="validate[required]" value="<?php echo htmlspecialchars($_Form['housename'], ENT_QUOTES, 'UTF-8'); ?>" /><br/><br/>
<label>Description</label><br/>
<textarea id="description" name="description"><?php echo htmlspecialchars($_Form['description'], ENT_QUOTES, 'UTF-8'); ?></textarea><br/><br/>
<?php if($_EditingHouse){ ?>
<div style="margin-bottom:10px;color:#1f2937;background:#eff6ff;border:1px solid #bfdbfe;padding:10px;border-radius:6px;">
You are editing this house name. Existing house assignments will continue to use the same house record.
</div>
<?php } ?>
<div align="center">
    <button class="button-save" id="save_house" name="save_house"><i class="fa fa-save"></i> <?php echo $_EditingHouse ? "Update House" : "Save House"; ?></button>
    <?php if($_EditingHouse){ ?>
    <a href="house-entry.php" style="margin-left:10px;display:inline-block;padding:10px 14px;background:#e5e7eb;color:#111827;text-decoration:none;border-radius:6px;"><i class="fa fa-times"></i> Cancel</a>
    <?php } ?>
</div>
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
    echo "<a title='Edit house' href='house-entry.php?edit_house=".urlencode($row['houseid'])."'><i class='fa fa-pencil' style='color:#1d4ed8'></i></a> ";
    if($row['status'] === 'active'){
        echo "<a title='Deactivate house' onclick=\"javascript:return confirm('Deactivate this house?');\" href='house-entry.php?deactivate_house=".urlencode($row['houseid'])."'><i class='fa fa-ban' style='color:#b45309'></i></a> ";
    }
    echo "<a title='Delete house' onclick=\"javascript:return confirm('Delete this house permanently?');\" href='house-entry.php?delete_house=".urlencode($row['houseid'])."'><i class='fa fa-trash' style='color:#b91c1c'></i></a>";
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
