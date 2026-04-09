<?php
session_start();
$_SESSION['Message']="";
include("check-login.php");
include("dbstring.php");
include("house-master-utils.php");
ensure_house_tables($con);

if(!house_master_is_student()){
    header("location:".house_master_landing_page());
    exit();
}

$_StudentId = $_SESSION['USERID'];
$_HouseInfo = get_student_active_house($con, $_StudentId);

if(isset($_POST['request_exeat'])){
    @$_ExeatType = strtolower(trim((string)($_POST['exeattype'] ?? '')));
    @$_Reason = trim($_POST['reason']);
    @$_DateOut = $_POST['dateout'];
    @$_TimeOut = $_POST['timeout'];
    @$_DateReturn = $_POST['datereturn'];
    @$_TimeReturn = $_POST['timereturn'];

    if(!$_HouseInfo){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>You are not assigned to any house yet. Contact administration.</div>";
    }elseif(($_ExeatType !== "internal" && $_ExeatType !== "external") || $_Reason === "" || $_DateOut === "" || $_TimeOut === "" || $_DateReturn === "" || $_TimeReturn === ""){
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Exeat type, reason, departure date/time and return date/time are required.</div>";
    }else{
        $_OutDateTime = strtotime($_DateOut." ".$_TimeOut);
        $_ReturnDateTime = strtotime($_DateReturn." ".$_TimeReturn);
        if($_OutDateTime === false || $_ReturnDateTime === false){
            $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Invalid date/time provided.</div>";
        }elseif($_ReturnDateTime <= $_OutDateTime){
            $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Return date/time must be later than departure date/time.</div>";
        }else{
        $_StudentIdE = mysqli_real_escape_string($con, $_StudentId);
        $_HouseIdE = mysqli_real_escape_string($con, $_HouseInfo['houseid']);
        $_ExeatTypeE = mysqli_real_escape_string($con, $_ExeatType);
        $_ReasonE = mysqli_real_escape_string($con, $_Reason);
        $_DateOutE = mysqli_real_escape_string($con, $_DateOut);
        $_TimeOutE = mysqli_real_escape_string($con, $_TimeOut);
        $_DateReturnE = mysqli_real_escape_string($con, $_DateReturn);
        $_TimeReturnE = mysqli_real_escape_string($con, $_TimeReturn);

        include("code.php");
        $_ExeatId = $code;
        $_SQL = mysqli_query($con, "INSERT INTO tblexeatrequest(exeatid,userid,houseid,exeattype,reason,dateout,timeout,datereturn,timereturn,requestedatetime,status,recordedby)
            VALUES('$_ExeatId','$_StudentIdE','$_HouseIdE','$_ExeatTypeE','$_ReasonE','$_DateOutE','$_TimeOutE','$_DateReturnE','$_TimeReturnE',NOW(),'pending','$_StudentIdE')");
        if($_SQL){
            $_SESSION['Message'] = "<div style='color:green;text-align:center;background-color:white'>Exeat request submitted successfully.</div>";
            $_SQLStudent = mysqli_query($con, "SELECT firstname,surname,othernames FROM tblsystemuser WHERE userid='$_StudentIdE' LIMIT 1");
            $_StudentName = $_StudentIdE;
            if($_SQLStudent && $row_st=mysqli_fetch_array($_SQLStudent, MYSQLI_ASSOC)){
                $_StudentName = trim($row_st['firstname']." ".$row_st['othernames']." ".$row_st['surname']);
            }
            $_NotifySummary = array();
            notify_house_masters_new_exeat(
                $con,
                $_HouseIdE,
                $_StudentName,
                $_ExeatTypeE,
                $_DateOutE." ".$_TimeOutE,
                $_DateReturnE." ".$_TimeReturnE,
                $_NotifySummary
            );
            if(isset($_NotifySummary['total']) && (int)$_NotifySummary['total'] > 0){
                $_SESSION['Message'] .= "<div style='color:#1d4ed8;text-align:center;background-color:white'>House master alert sent: ".(int)$_NotifySummary['sent']." success, ".(int)$_NotifySummary['failed']." failed, ".(int)$_NotifySummary['no_phone']." no phone.</div>";
            }else{
                $_SESSION['Message'] .= "<div style='color:#b45309;text-align:center;background-color:white'>No active house master found to notify.</div>";
            }
        }else{
            $_SESSION['Message'] = "<div style='color:red;text-align:center;background-color:white'>Failed to submit request: ".mysqli_error($con)."</div>";
        }
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
<h3>Request Exeat</h3>
<?php echo $_SESSION['Message']; ?>
<?php
if($_HouseInfo){
    echo "<div style='margin-bottom:8px;'><strong>House:</strong> ".htmlspecialchars($_HouseInfo['housename'])."</div>";
}else{
    echo "<div style='margin-bottom:8px;color:red;'>House: Not assigned</div>";
}
?>
<form method="post" action="student-exeat-request.php" id="formID" name="formID">
<label>Exeat Type</label><br/>
<select id="exeattype" name="exeattype" class="validate[required]">
<option value="">Select Type</option>
<option value="internal">Internal</option>
<option value="external">External</option>
</select><br/><br/>
<label>Reason</label><br/>
<textarea id="reason" name="reason" class="validate[required]"></textarea><br/><br/>
<label>Date Out</label><br/>
<input type="date" id="dateout" name="dateout" class="validate[required]" /><br/><br/>
<label>Time Out</label><br/>
<input type="time" id="timeout" name="timeout" class="validate[required]" /><br/><br/>
<label>Return Date</label><br/>
<input type="date" id="datereturn" name="datereturn" class="validate[required]" /><br/><br/>
<label>Expected Time Return</label><br/>
<input type="time" id="timereturn" name="timereturn" class="validate[required]" /><br/><br/>
<div align="center"><button class="button-save" id="request_exeat" name="request_exeat"><i class="fa fa-save"></i> Submit Request</button></div>
</form>
</div>
</td>
<td width="70%" valign="top">
<div class="form-entry">
<?php
$_StudentIdE = mysqli_real_escape_string($con, $_StudentId);
$_SQL = mysqli_query($con, "SELECT er.*,h.housename
FROM tblexeatrequest er
INNER JOIN tblhouse h ON h.houseid=er.houseid
WHERE er.userid='$_StudentIdE'
ORDER BY er.requestedatetime DESC");
echo "<table width='100%' style='background-color:white'>";
echo "<caption>My Exeat Requests</caption>";
echo "<thead><th>Type</th><th>Status</th><th>House</th><th>Reason</th><th>Departure</th><th>Return</th><th>Decision Note</th><th>Requested</th></thead>";
echo "<tbody>";
while($row=mysqli_fetch_array($_SQL,MYSQLI_ASSOC)){
    $_Type = strtolower(trim((string)($row['exeattype'] ?? 'external')));
    if($_Type !== 'internal'){ $_Type = 'external'; }
    $_Departure = trim((string)$row['dateout'])." ".trim((string)($row['timeout'] ?? ''));
    $_Return = trim((string)($row['datereturn'] ?? ''))." ".trim((string)($row['timereturn'] ?? ''));
    echo "<tr>";
    echo "<td align='center'>".htmlspecialchars(ucfirst($_Type))."</td>";
    echo "<td align='center'>".htmlspecialchars($row['status'])."</td>";
    echo "<td align='center'>".htmlspecialchars($row['housename'])."</td>";
    echo "<td>".htmlspecialchars($row['reason'])."</td>";
    echo "<td align='center'>".htmlspecialchars(trim($_Departure))."</td>";
    echo "<td align='center'>".htmlspecialchars(trim($_Return))."</td>";
    echo "<td>".htmlspecialchars($row['decisionnote'])."</td>";
    echo "<td align='center'>".htmlspecialchars($row['requestedatetime'])."</td>";
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
