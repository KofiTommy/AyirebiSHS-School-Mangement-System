<?php
session_start();
$_SESSION['Message'] = isset($_SESSION['Message']) ? $_SESSION['Message'] : "";
include("check-login.php");
include("dbstring.php");
include("teacher-billing-utils.php");
ensure_teacher_billing_table($con);
ensure_teacher_billing_item_table($con);

if(!teacher_billing_can_manage_assignments()){
    header("location:".teacher_billing_landing_page());
    exit();
}

function tba_redirect(){
    header("location:teacher-billing-assignment.php");
    exit();
}

if(isset($_POST['save_teacher_billing_assignment'])){
    $_TeacherId = isset($_POST['userid']) ? trim((string)$_POST['userid']) : "";
    $_ClassId = isset($_POST['classid']) ? trim((string)$_POST['classid']) : "";
    $_BatchId = isset($_POST['batchid']) ? trim((string)$_POST['batchid']) : "";
    $_TermName = isset($_POST['termname']) ? (int)$_POST['termname'] : 0;
    $_RecordedBy = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";

    if($_TeacherId === '' || $_ClassId === '' || $_BatchId === '' || $_TermName <= 0){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white;padding:8px;'>Please select teacher, class, batch, and semester.</div>";
        tba_redirect();
    }

    $_TeacherIdEsc = mysqli_real_escape_string($con, $_TeacherId);
    $_ClassIdEsc = mysqli_real_escape_string($con, $_ClassId);
    $_BatchIdEsc = mysqli_real_escape_string($con, $_BatchId);
    $_RecordedByEsc = mysqli_real_escape_string($con, $_RecordedBy);

    $_SQL_EXIST = mysqli_query($con, "SELECT assignmentid
        FROM tblteacherbillingassignment
        WHERE userid='$_TeacherIdEsc'
          AND classid='$_ClassIdEsc'
          AND batchid='$_BatchIdEsc'
          AND termname='$_TermName'
        LIMIT 1");

    if($_SQL_EXIST && ($row_exist = mysqli_fetch_array($_SQL_EXIST, MYSQLI_ASSOC))){
        $_AssignmentIdEsc = mysqli_real_escape_string($con, $row_exist['assignmentid']);
        $_SQL_UPDATE = mysqli_query($con, "UPDATE tblteacherbillingassignment
            SET status='active', datetimeentry=NOW(), recordedby='$_RecordedByEsc'
            WHERE assignmentid='$_AssignmentIdEsc'");
        if($_SQL_UPDATE){
            $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white;padding:8px;'>Teacher billing assignment updated successfully.</div>";
        }else{
            $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white;padding:8px;'>Failed to update assignment: ".mysqli_error($con)."</div>";
        }
        tba_redirect();
    }

    include("code.php");
    $_AssignmentIdEsc = mysqli_real_escape_string($con, trim((string)$code));
    $_SQL_INSERT = mysqli_query($con, "INSERT INTO tblteacherbillingassignment(assignmentid,userid,classid,batchid,termname,status,datetimeentry,recordedby)
        VALUES('$_AssignmentIdEsc','$_TeacherIdEsc','$_ClassIdEsc','$_BatchIdEsc','$_TermName','active',NOW(),'$_RecordedByEsc')");
    if($_SQL_INSERT){
        $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white;padding:8px;'>Teacher billing assignment saved successfully.</div>";
    }else{
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white;padding:8px;'>Failed to save assignment: ".mysqli_error($con)."</div>";
    }
    tba_redirect();
}

if(isset($_GET['deactivate_assignment'])){
    $_AssignmentId = mysqli_real_escape_string($con, trim((string)$_GET['deactivate_assignment']));
    if($_AssignmentId !== ''){
        $_SQL_D = mysqli_query($con, "UPDATE tblteacherbillingassignment SET status='inactive' WHERE assignmentid='$_AssignmentId'");
        $_SESSION['Message'] = $_SQL_D
            ? "<div style='color:#8b4513;text-align:center;background-color:white;padding:8px;'>Assignment deactivated.</div>"
            : "<div style='color:red;text-align:center;background-color:white;padding:8px;'>Failed to deactivate assignment: ".mysqli_error($con)."</div>";
    }
    tba_redirect();
}

if(isset($_GET['delete_assignment'])){
    $_AssignmentId = mysqli_real_escape_string($con, trim((string)$_GET['delete_assignment']));
    if($_AssignmentId !== ''){
        @mysqli_query($con, "DELETE FROM tblteacherbillingassignmentitem WHERE assignmentid='$_AssignmentId'");
        $_SQL_DEL = mysqli_query($con, "DELETE FROM tblteacherbillingassignment WHERE assignmentid='$_AssignmentId'");
        $_SESSION['Message'] = $_SQL_DEL
            ? "<div style='color:#8b4513;text-align:center;background-color:white;padding:8px;'>Assignment deleted.</div>"
            : "<div style='color:red;text-align:center;background-color:white;padding:8px;'>Failed to delete assignment: ".mysqli_error($con)."</div>";
    }
    tba_redirect();
}

if(isset($_POST['save_assignment_items'])){
    $_AssignmentId = isset($_POST['assignmentid']) ? trim((string)$_POST['assignmentid']) : "";
    $_RecordedBy = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";
    $_AssignmentRow = teacher_billing_assignment_row($con, $_AssignmentId);
    if(!$_AssignmentRow){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white;padding:8px;'>The selected billing assignment could not be found.</div>";
        tba_redirect();
    }
    $_AllowedRows = teacher_billing_scope_itemprice_rows($con, $_AssignmentRow['classid'], $_AssignmentRow['batchid'], $_AssignmentRow['termname']);
    $_AllowedMap = array();
    foreach($_AllowedRows as $_allowedRow){
        $_AllowedMap[(string)$_allowedRow['itempriceid']] = true;
    }
    $_SubmittedItems = isset($_POST['itempriceids']) && is_array($_POST['itempriceids']) ? $_POST['itempriceids'] : array();
    $_ValidItems = array();
    foreach($_SubmittedItems as $_ItemPriceId){
        $_ItemPriceId = trim((string)$_ItemPriceId);
        if($_ItemPriceId !== '' && isset($_AllowedMap[$_ItemPriceId])){
            $_ValidItems[] = $_ItemPriceId;
        }
    }
    $_Saved = teacher_billing_assignment_replace_items($con, $_AssignmentId, $_ValidItems, $_RecordedBy);
    if($_Saved){
        if(count($_ValidItems) > 0){
            $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white;padding:8px;'>Teacher billing items updated successfully.</div>";
        }else{
            $_SESSION['Message'] = "<div style='color:#8b4513;text-align:center;background-color:white;padding:8px;'>No specific items were selected. The teacher will be able to collect all billed items in that assigned scope.</div>";
        }
    }else{
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white;padding:8px;'>Some billing items could not be saved. Please try again.</div>";
    }
    header("location:teacher-billing-assignment.php?set_items=".urlencode($_AssignmentId));
    exit();
}
?>
<html>
<head>
<?php include("links.php"); ?>
<style>
@media print {
    .header, .print-hide { display:none !important; }
    .main-platform { margin:0; padding:0; }
}
</style>
</head>
<body>
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform">
<table width="100%">
<tr>
<td width="32%" valign="top">
<div class="form-entry" align="left">
<h3>Teacher Billing Assignment</h3>
<?php
echo $_SESSION['Message'];
$_SESSION['Message'] = "";
?>
<form method="post" action="teacher-billing-assignment.php" id="formID" name="formID">
<?php
$_SQL_T = mysqli_query($con, "SELECT userid,firstname,surname,othernames FROM tblsystemuser WHERE systemtype='Teacher' AND status='active' ORDER BY firstname ASC, surname ASC");
echo "<label>Teacher</label><br/>";
echo "<select id='userid' name='userid' class='validate[required]'>";
echo "<option value=''>Select Teacher</option>";
while($_row_t = mysqli_fetch_array($_SQL_T, MYSQLI_ASSOC)){
    echo "<option value='".$_row_t['userid']."'>".$_row_t['firstname']." ".$_row_t['othernames']." ".$_row_t['surname']." (".$_row_t['userid'].")</option>";
}
echo "</select><br/><br/>";

$_ClassOptions = teacher_billing_class_options($con);
echo "<label>Class</label><br/>";
echo "<select id='classid' name='classid' class='validate[required]'>";
echo "<option value=''>Select Class</option>";
foreach($_ClassOptions as $_class_row){
    echo "<option value='".$_class_row['class_entryid']."'>".$_class_row['class_name']."</option>";
}
echo "</select><br/><br/>";

$_BatchOptions = teacher_billing_batch_options($con);
echo "<label>Batch</label><br/>";
echo "<select id='batchid' name='batchid' class='validate[required]'>";
echo "<option value=''>Select Batch</option>";
foreach($_BatchOptions as $_batch_row){
    echo "<option value='".$_batch_row['batchid']."'>".$_batch_row['batch']."</option>";
}
echo "</select><br/><br/>";
?>
<label>Semester</label><br/>
<select id="termname" name="termname" class="validate[required]">
<option value="">Select Semester</option>
<option value="1">1</option>
<option value="2">2</option>
</select><br/><br/>
<div align="center">
    <button class="button-save" id="save_teacher_billing_assignment" name="save_teacher_billing_assignment"><i class="fa fa-save"></i> Save Assignment</button>
</div>
</form>
</div>
</td>
<td width="68%" valign="top">
<?php
$_ManageAssignmentId = isset($_GET['set_items']) ? trim((string)$_GET['set_items']) : "";
$_ManageAssignmentRow = $_ManageAssignmentId !== "" ? teacher_billing_assignment_row($con, $_ManageAssignmentId) : null;
if($_ManageAssignmentRow){
    $_ManageItemRows = teacher_billing_scope_itemprice_rows($con, $_ManageAssignmentRow['classid'], $_ManageAssignmentRow['batchid'], $_ManageAssignmentRow['termname']);
    $_SelectedItems = array();
    foreach(teacher_billing_assignment_item_rows($con, $_ManageAssignmentId) as $_SelectedRow){
        $_SelectedItems[(string)$_SelectedRow['itempriceid']] = true;
    }
    echo "<div class='form-entry print-hide' align='left' style='margin-bottom:16px;'>";
    echo "<h3>Billing Items For Assigned Teacher</h3>";
    echo "<div style='margin-bottom:10px;color:#475569;'>";
    echo "<strong>Teacher:</strong> ".$_ManageAssignmentRow['firstname']." ".$_ManageAssignmentRow['othernames']." ".$_ManageAssignmentRow['surname']." (".$_ManageAssignmentRow['userid'].")";
    echo " | <strong>Class:</strong> ".$_ManageAssignmentRow['class_name'];
    echo " | <strong>Batch:</strong> ".$_ManageAssignmentRow['batch'];
    echo " | <strong>Semester:</strong> ".$_ManageAssignmentRow['termname'];
    echo "</div>";
    echo "<div style='margin-bottom:10px;color:#64748b;'>Select the class billing items this teacher can collect for this scope. If you leave everything unchecked, the teacher will be able to collect all billed items in this scope.</div>";
    if(empty($_ManageItemRows)){
        echo "<div style='color:#b45309;background-color:#fff7ed;padding:10px;border-radius:8px;'>No active class billing items exist yet for this class, batch, and semester. Create them first in <a href='class-billing.php'>Billing Manager</a>.</div>";
    }else{
        echo "<form method='post' action='teacher-billing-assignment.php?set_items=".urlencode($_ManageAssignmentId)."'>";
        echo "<input type='hidden' name='assignmentid' value='".htmlspecialchars($_ManageAssignmentId, ENT_QUOTES, 'UTF-8')."'>";
        echo "<table width='100%' style='background-color:white'>";
        echo "<thead><tr><th align='center'>*</th><th align='left'>Billing Item</th><th align='center'>Price</th><th align='center'>Item Price ID</th></tr></thead><tbody>";
        foreach($_ManageItemRows as $_ItemRow){
            $_ItemPriceId = (string)$_ItemRow['itempriceid'];
            echo "<tr>";
            echo "<td align='center'><input type='checkbox' name='itempriceids[]' value='".htmlspecialchars($_ItemPriceId, ENT_QUOTES, 'UTF-8')."'".(isset($_SelectedItems[$_ItemPriceId]) ? " checked" : "")."></td>";
            echo "<td>".htmlspecialchars((string)$_ItemRow['itemname'], ENT_QUOTES, 'UTF-8')."</td>";
            echo "<td align='center'>".htmlspecialchars((string)$_SESSION['SYMBOL'], ENT_QUOTES, 'UTF-8')." ".number_format((float)$_ItemRow['price'], 2)."</td>";
            echo "<td align='center'>".htmlspecialchars($_ItemPriceId, ENT_QUOTES, 'UTF-8')."</td>";
            echo "</tr>";
        }
        echo "</tbody></table><br/>";
        echo "<button class='button-save' type='submit' name='save_assignment_items'><i class='fa fa-save'></i> Save Billing Items</button> ";
        echo "<a class='button-save' style='text-decoration:none;padding:8px 12px;display:inline-block;background:#64748b;margin-left:6px;' href='teacher-billing-assignment.php'>Close</a>";
        echo "</form>";
    }
    echo "</div>";
}
?>
<div class="form-entry">
<div class="print-hide" style="margin-bottom:10px;text-align:right;">
    <button class="button-save" type="button" onclick="window.print()"><i class="fa fa-print"></i> Print Billing Assignments</button>
</div>
<?php
$_SQL_A = mysqli_query($con, "SELECT tba.*,su.firstname,su.surname,su.othernames,ce.class_name,bh.batch
    FROM tblteacherbillingassignment tba
    INNER JOIN tblsystemuser su ON su.userid=tba.userid
    INNER JOIN tblclassentry ce ON ce.class_entryid=tba.classid
    INNER JOIN tblbatch bh ON bh.batchid=tba.batchid
    ORDER BY tba.datetimeentry DESC");
echo "<table width='100%' style='background-color:white'>";
echo "<caption>Assigned Teacher Billing Scopes</caption>";
echo "<thead><th>Task</th><th>Teacher</th><th>Class</th><th>Semester</th><th>Batch</th><th>Billing Items</th><th>Status</th><th>Date/Time</th></thead>";
echo "<tbody>";
while($_row_a = mysqli_fetch_array($_SQL_A, MYSQLI_ASSOC)){
    $_SelectedItemCount = count(teacher_billing_assignment_item_rows($con, $_row_a['assignmentid']));
    echo "<tr>";
    echo "<td align='center'>";
    if($_row_a['status'] === 'active'){
        echo "<span class='print-hide'><a title='Deactivate assignment' onclick=\"javascript:return confirm('Deactivate this assignment?');\" href='teacher-billing-assignment.php?deactivate_assignment=".$_row_a['assignmentid']."'><i class='fa fa-ban' style='color:#b45309'></i></a></span> ";
    }
    echo "<span class='print-hide'><a title='Set billing items' href='teacher-billing-assignment.php?set_items=".$_row_a['assignmentid']."'><i class='fa fa-list' style='color:#0f766e'></i></a></span> ";
    echo "<span class='print-hide'><a title='Delete assignment' onclick=\"javascript:return confirm('Delete this assignment permanently?');\" href='teacher-billing-assignment.php?delete_assignment=".$_row_a['assignmentid']."'><i class='fa fa-trash' style='color:#b91c1c'></i></a></span>";
    echo "</td>";
    echo "<td>".$_row_a['firstname']." ".$_row_a['othernames']." ".$_row_a['surname']." (".$_row_a['userid'].")</td>";
    echo "<td align='center'>".$_row_a['class_name']."</td>";
    echo "<td align='center'>".$_row_a['termname']."</td>";
    echo "<td align='center'>".$_row_a['batch']."</td>";
    echo "<td align='center'>".($_SelectedItemCount > 0 ? $_SelectedItemCount." selected" : "All in scope")."</td>";
    echo "<td align='center'>".$_row_a['status']."</td>";
    echo "<td align='center'>".$_row_a['datetimeentry']."</td>";
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
