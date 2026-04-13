<?php
session_start();
$_SESSION['Message']="";
include("check-login.php");
include("dbstring.php");
include("house-master-utils.php");
ensure_house_tables($con);

if(!house_master_can_manage_module($con, 'house_management')){
    header("location:".house_master_landing_page());
    exit();
}

if(isset($_POST['save_student_house'])){
    @$_UserId = $_POST['userid'];
    @$_HouseId = $_POST['houseid'];
    if(!$_UserId || !$_HouseId){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Please select student and house.</div>";
    }else{
        if(assign_student_to_house($con, $_UserId, $_HouseId, $_SESSION['USERID'])){
            $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white'>Student assigned to house successfully.</div>";
        }else{
            $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Failed to assign student: ".mysqli_error($con)."</div>";
        }
    }
}

if(isset($_POST['bulk_assign_students'])){
    @$_BulkHouseId = $_POST['bulk_houseid'];
    @$_StudentIds = $_POST['studentids'];
    if(!$_BulkHouseId){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Please select a house for bulk assignment.</div>";
    }elseif(!is_array($_StudentIds) || count($_StudentIds) === 0){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Select at least one student for bulk assignment.</div>";
    }else{
        $_Success = 0;
        $_Failed = 0;
        $_Processed = array();
        foreach($_StudentIds as $_Sid){
            $_Sid = trim((string)$_Sid);
            if($_Sid === "" || isset($_Processed[$_Sid])){
                continue;
            }
            $_Processed[$_Sid] = 1;
            if(assign_student_to_house($con, $_Sid, $_BulkHouseId, $_SESSION['USERID'])){
                $_Success++;
            }else{
                $_Failed++;
            }
        }
        $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white'>Bulk assignment complete. Success: $_Success, Failed: $_Failed.</div>";
    }
}

if(isset($_GET['remove_student_house'])){
    $_AssignmentId = mysqli_real_escape_string($con, $_GET['remove_student_house']);
    $_SQL_R = mysqli_query($con, "UPDATE tblstudenthouse SET status='inactive' WHERE assignmentid='$_AssignmentId' AND status='active'");
    if($_SQL_R){
        if(mysqli_affected_rows($con) > 0){
            $_SESSION['Message'] = "<div style='color:maroon;text-align:center;background-color:white'>Student removed from house successfully.</div>";
        }else{
            $_SESSION['Message'] = "<div style='color:#b45309;text-align:center;background-color:white'>No active student-house assignment found to remove.</div>";
        }
    }else{
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Failed to remove student from house: ".mysqli_error($con)."</div>";
    }
}

@$_FilterClassId = trim($_GET['filter_classid'] ?? '');
@$_FilterBatchId = trim($_GET['filter_batchid'] ?? '');
@$_FilterSearch = trim($_GET['filter_search'] ?? '');

$_Where = " WHERE su.systemtype='Student' AND su.status='active' ";
if($_FilterClassId !== ''){
    $_FilterClassIdEsc = mysqli_real_escape_string($con, $_FilterClassId);
    $_Where .= " AND EXISTS (SELECT 1 FROM tblclass cl WHERE cl.userid=su.userid AND cl.class_entryid='$_FilterClassIdEsc' AND cl.status='active') ";
}
if($_FilterBatchId !== ''){
    $_FilterBatchIdEsc = mysqli_real_escape_string($con, $_FilterBatchId);
    $_Where .= " AND EXISTS (SELECT 1 FROM tbltermregistry tr WHERE tr.userid=su.userid AND tr.batchid='$_FilterBatchIdEsc') ";
}
if($_FilterSearch !== ''){
    $_FilterSearchEsc = mysqli_real_escape_string($con, $_FilterSearch);
    $_Where .= " AND (su.userid LIKE '%$_FilterSearchEsc%' OR su.firstname LIKE '%$_FilterSearchEsc%' OR su.surname LIKE '%$_FilterSearchEsc%' OR su.othernames LIKE '%$_FilterSearchEsc%') ";
}
?>
<html>
<head>
<?php include("links.php"); ?>
<style>
@media print {
    .header, .print-hide, h4, form { display: none !important; }
    .print-area { display: block !important; }
    .main-platform, .form-entry { margin: 0 !important; padding: 0 !important; border: 0 !important; }
    table { width: 100% !important; }
    .selection-col { display: none !important; }
}
</style>
<script>
function toggleAllStudents(source){
    var checkboxes = document.querySelectorAll("input[name='studentids[]']");
    for(var i=0;i<checkboxes.length;i++){
        checkboxes[i].checked = source.checked;
    }
}
</script>
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
<h3>Student House Assignment</h3>
<?php echo $_SESSION['Message']; ?>

<h4 style="margin-bottom:8px;">Single Assignment</h4>
<form method="post" action="student-house-assignment.php" id="formID" name="formID">
<?php
$_SQL_S = mysqli_query($con,"SELECT userid,firstname,surname,othernames FROM tblsystemuser WHERE systemtype='Student' AND status='active' ORDER BY firstname ASC");
echo "<label>Student</label><br/>";
echo "<select id='userid' name='userid' class='validate[required]'>";
echo "<option value=''>Select Student</option>";
while($row_s=mysqli_fetch_array($_SQL_S,MYSQLI_ASSOC)){
    echo "<option value='$row_s[userid]'>$row_s[firstname] $row_s[othernames] $row_s[surname] ($row_s[userid])</option>";
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
<div align="center"><button class="button-save" id="save_student_house" name="save_student_house"><i class="fa fa-save"></i> Save Assignment</button></div>
</form>
</div>
</td>
<td width="70%" valign="top">
<div class="form-entry">
<h4 style="margin-top:0;">Bulk Assignment</h4>

<form method="get" action="student-house-assignment.php" style="margin-bottom:12px;">
<?php
$_SQL_CLS = mysqli_query($con,"SELECT class_entryid,class_name FROM tblclassentry ORDER BY class_name ASC");
echo "<label>Filter by Class</label><br/>";
echo "<select name='filter_classid'>";
echo "<option value=''>All Classes</option>";
while($row_cls=mysqli_fetch_array($_SQL_CLS,MYSQLI_ASSOC)){
    $_Selected = ($_FilterClassId === $row_cls['class_entryid']) ? "selected" : "";
    echo "<option value='$row_cls[class_entryid]' $_Selected>$row_cls[class_name]</option>";
}
echo "</select><br/><br/>";

$_SQL_BH = mysqli_query($con,"SELECT batchid,batch FROM tblbatch ORDER BY datetimeentry DESC");
echo "<label>Filter by Batch</label><br/>";
echo "<select name='filter_batchid'>";
echo "<option value=''>All Batches</option>";
while($row_bh=mysqli_fetch_array($_SQL_BH,MYSQLI_ASSOC)){
    $_Selected = ($_FilterBatchId === $row_bh['batchid']) ? "selected" : "";
    echo "<option value='$row_bh[batchid]' $_Selected>$row_bh[batch]</option>";
}
echo "</select><br/><br/>";
?>
<label>Search (Name or ID)</label><br/>
<input type="text" name="filter_search" value="<?php echo htmlspecialchars($_FilterSearch); ?>" />
<div class="print-hide" style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;">
<button type="submit" name="load_mode" value="all" title="Load using current filters"><i class="fa fa-filter"></i> Load Students</button>
<button type="submit" name="load_mode" value="batch" title="Load all students in selected batch" onclick="return loadByBatchOnly();"><i class="fa fa-users"></i> Load By Batch</button>
<button type="button" title="Clear filters" onclick="clearFilters();"><i class="fa fa-eraser"></i> Clear Filters</button>
</div>
</form>

<form method="post" action="student-house-assignment.php">
<?php
$_SQL_H2 = mysqli_query($con,"SELECT houseid,housename FROM tblhouse WHERE status='active' ORDER BY housename ASC");
echo "<label>Assign Selected To House</label><br/>";
echo "<select id='bulk_houseid' name='bulk_houseid' required>";
echo "<option value=''>Select House</option>";
$_HousePrintOptions = array();
while($row_h2=mysqli_fetch_array($_SQL_H2,MYSQLI_ASSOC)){
    $_HousePrintOptions[$row_h2['houseid']] = $row_h2['housename'];
    echo "<option value='$row_h2[houseid]'>$row_h2[housename]</option>";
}
echo "</select><br/><br/>";

$_SQL_BULK = mysqli_query($con,"SELECT su.userid,su.firstname,su.surname,su.othernames,COALESCE(h.housename,'Not Assigned') AS currenthouse, COALESCE(h.houseid,'') AS currenthouseid
FROM tblsystemuser su
LEFT JOIN (
    SELECT sh1.userid,sh1.houseid
    FROM tblstudenthouse sh1
    INNER JOIN (
        SELECT userid,MAX(datetimeentry) AS latestdt
        FROM tblstudenthouse
        WHERE status='active'
        GROUP BY userid
    ) sh2 ON sh2.userid=sh1.userid AND sh2.latestdt=sh1.datetimeentry
    WHERE sh1.status='active'
) sh ON sh.userid=su.userid
LEFT JOIN tblhouse h ON h.houseid=sh.houseid
$_Where
ORDER BY su.firstname ASC,su.surname ASC");

echo "<div class='print-hide' style='margin-bottom:8px;display:flex;justify-content:flex-end;gap:8px;align-items:center;flex-wrap:wrap;'>";
echo "<label for='print_houseid' style='margin:0;'>Print House</label>";
echo "<select id='print_houseid'>";
echo "<option value=''>All Loaded Students</option>";
foreach($_HousePrintOptions as $_PHouseId => $_PHouseName){
    echo "<option value='".htmlspecialchars($_PHouseId)."'>".htmlspecialchars($_PHouseName)."</option>";
}
echo "<option value='__unassigned__'>Not Assigned</option>";
echo "</select>";
echo "<button type='button' onclick='printStudentListByHouse();'><i class='fa fa-print'></i> Print Student List</button>";
echo "</div>";

echo "<div class='print-area'>";
echo "<h4 style='margin:4px 0;'>Students List</h4>";
echo "<div style='margin-bottom:8px;'><input type='checkbox' onclick='toggleAllStudents(this)' /> Select All Loaded Students</div>";
echo "<table id='loaded-students-table' width='100%' style='background-color:white'>";
echo "<thead><th class='selection-col'></th><th>Student</th><th>Current House</th></thead>";
echo "<tbody>";
$_CountLoaded = 0;
while($row_bulk=mysqli_fetch_array($_SQL_BULK,MYSQLI_ASSOC)){
    $_CountLoaded++;
    $_RowHouseId = ($row_bulk['currenthouseid'] !== '') ? $row_bulk['currenthouseid'] : '__unassigned__';
    echo "<tr data-houseid='".htmlspecialchars($_RowHouseId)."'>";
    echo "<td class='selection-col' align='center'><input type='checkbox' name='studentids[]' value='".htmlspecialchars($row_bulk['userid'])."' /></td>";
    echo "<td>".htmlspecialchars($row_bulk['firstname']." ".$row_bulk['othernames']." ".$row_bulk['surname'])." (".htmlspecialchars($row_bulk['userid']).")</td>";
    echo "<td align='center'>".htmlspecialchars($row_bulk['currenthouse'])."</td>";
    echo "</tr>";
}
if($_CountLoaded === 0){
    echo "<tr><td colspan='3' align='center'>No students matched your filters.</td></tr>";
}
echo "</tbody>";
echo "</table>";
echo "<div style='margin-top:8px;'>Loaded: ".(int)$_CountLoaded." student(s)</div>";
echo "</div>";
?>
<div style="margin-top:10px;" align="right">
<button class="button-save" type="submit" name="bulk_assign_students"><i class="fa fa-save"></i> Assign Selected Students</button>
</div>
</form>
</div>

<div class="form-entry" style="margin-top:10px;">
<?php
$_SQL_A = mysqli_query($con,"SELECT sh.*,h.housename,su.firstname,su.surname,su.othernames
FROM tblstudenthouse sh
INNER JOIN tblhouse h ON h.houseid=sh.houseid
INNER JOIN tblsystemuser su ON su.userid=sh.userid
WHERE sh.status='active'
ORDER BY sh.datetimeentry DESC");
echo "<table width='100%' style='background-color:white'>";
echo "<caption>Active Student House Assignment</caption>";
echo "<thead><th>Task</th><th>Student</th><th>House</th><th>Date/Time</th></thead>";
echo "<tbody>";
while($row=mysqli_fetch_array($_SQL_A,MYSQLI_ASSOC)){
    echo "<tr>";
    echo "<td align='center'>";
    echo "<a title='Remove student from house' onclick=\"javascript:return confirm('Remove this student from house assignment?');\" href='student-house-assignment.php?remove_student_house=$row[assignmentid]'><i class='fa fa-trash' style='color:#b91c1c'></i></a>";
    echo "</td>";
    echo "<td>".htmlspecialchars($row['firstname']." ".$row['othernames']." ".$row['surname'])." (".htmlspecialchars($row['userid']).")</td>";
    echo "<td align='center'>".htmlspecialchars($row['housename'])."</td>";
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
<script>
function loadByBatchOnly(){
    var batch = document.querySelector("select[name='filter_batchid']");
    if(!batch || !batch.value){
        alert("Select a batch first.");
        return false;
    }
    var classSel = document.querySelector("select[name='filter_classid']");
    var search = document.querySelector("input[name='filter_search']");
    if(classSel){ classSel.value = ""; }
    if(search){ search.value = ""; }
    return true;
}

function clearFilters(){
    var classSel = document.querySelector("select[name='filter_classid']");
    var batch = document.querySelector("select[name='filter_batchid']");
    var search = document.querySelector("input[name='filter_search']");
    if(classSel){ classSel.value = ""; }
    if(batch){ batch.value = ""; }
    if(search){ search.value = ""; }
}

function printStudentListByHouse(){
    var table = document.getElementById("loaded-students-table");
    if(!table){
        window.print();
        return;
    }
    var houseSel = document.getElementById("print_houseid");
    var selectedHouse = houseSel ? houseSel.value : "";
    var rows = table.querySelectorAll("tbody tr");
    for(var i=0;i<rows.length;i++){
        var houseId = rows[i].getAttribute("data-houseid") || "";
        if(selectedHouse === "" || houseId === selectedHouse){
            rows[i].style.display = "";
        }else{
            rows[i].style.display = "none";
        }
    }
    window.print();
    for(var j=0;j<rows.length;j++){
        rows[j].style.display = "";
    }
}
</script>
</body>
</html>
