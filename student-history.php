<?php
session_start();
include("dbstring.php");
include("check-login.php");
if (!isset($_SESSION['ACCESSLEVEL']) || $_SESSION['ACCESSLEVEL'] != "administrator") {
    header("location:index.php");
    exit();
}
if (!isset($_SESSION['SYSTEMTYPE']) || ($_SESSION['SYSTEMTYPE'] != "normal_user" && $_SESSION['SYSTEMTYPE'] != "super_user")) {
    header("location:index.php");
    exit();
}
@$_StudentId = trim($_GET["studentid"]);
if($_StudentId=="" && isset($_GET["userid"])){
    $_StudentId = trim($_GET["userid"]);
}
@$_StudentIdSafe = mysqli_real_escape_string($con, $_StudentId);
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
    <div class="form-entry" align="left">
        <h3>Student Multi-Year History</h3>
        <form method="get" action="student-history.php">
            <label>Select Student</label><br/>
            <?php
            $_SQL_ST = mysqli_query($con, "SELECT userid,firstname,surname,othernames FROM tblsystemuser WHERE systemtype='Student' ORDER BY firstname ASC");
            echo "<select name='studentid' id='studentid'>";
            echo "<option value=''>Select Student</option>";
            while($row = mysqli_fetch_array($_SQL_ST, MYSQLI_ASSOC)){
                $_Sel = ($_StudentId==$row["userid"]) ? "selected" : "";
                echo "<option value='$row[userid]' $_Sel>$row[firstname] $row[othernames] $row[surname] ($row[userid])</option>";
            }
            echo "</select><br/><br/>";
            ?>
            <label>Or enter Student ID manually</label><br/>
            <input type="text" name="userid" id="userid" value="" placeholder="Optional manual Student ID"/><br/><br/>
            <button class="button-show"><i class="fa fa-search"></i> SHOW HISTORY</button>
        </form>
    </div>

    <?php
    if($_StudentId!=""){
        echo "<div class='form-entry' align='left'>";
        echo "<h3>Semester History</h3>";
        $_SQL_H1 = mysqli_query($con, "SELECT * FROM vw_student_semester_history WHERE userid='$_StudentIdSafe' ORDER BY batch, semester");
        if($_SQL_H1){
            echo "<table width='100%'>";
            echo "<thead><th>Student</th><th>Class</th><th>Batch</th><th>Semester</th><th>Status</th><th>Registered</th></thead><tbody>";
            while($row = mysqli_fetch_array($_SQL_H1, MYSQLI_ASSOC)){
                echo "<tr>";
                echo "<td>$row[firstname] $row[othernames] $row[surname] ($row[userid])</td>";
                echo "<td>$row[class_name]</td>";
                echo "<td>$row[batch]</td>";
                echo "<td>$row[semester]</td>";
                echo "<td>$row[semester_status]</td>";
                echo "<td>$row[semester_registered_on]</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<div style='color:red'>History view not found. Run Phase 2 SQL in database.sql.</div>";
        }
        echo "</div>";

        echo "<div class='form-entry' align='left'>";
        echo "<h3>Terminal / Remark History</h3>";
        $_SQL_H3 = mysqli_query($con, "SELECT str.*, b.batch, ce.class_name
                                       FROM tblstudentterminalreport str
                                       LEFT JOIN tblbatch b ON b.batchid=str.batchid
                                       LEFT JOIN tblclass cl ON cl.userid=str.userid AND cl.batchid=str.batchid
                                       LEFT JOIN tblclassentry ce ON ce.class_entryid=cl.class_entryid
                                       WHERE str.userid='$_StudentIdSafe'
                                       ORDER BY str.datetimeentry DESC");
        if($_SQL_H3){
            echo "<table width='100%'>";
            echo "<thead><th>Batch</th><th>Class</th><th>Roll</th><th>Attendance</th><th>Total Attendance</th><th>Promoted To</th><th>Conduct</th><th>Teacher Remark</th><th>Head Remark</th><th>Date/Time</th></thead><tbody>";
            while($row = mysqli_fetch_array($_SQL_H3, MYSQLI_ASSOC)){
                echo "<tr>";
                echo "<td>$row[batch]</td>";
                echo "<td>$row[class_name]</td>";
                echo "<td>$row[roll]</td>";
                echo "<td>$row[attendance]</td>";
                echo "<td>$row[totalattendance]</td>";
                echo "<td>$row[promotedto]</td>";
                echo "<td>$row[conduct]</td>";
                echo "<td>$row[class_teacher_remark]</td>";
                echo "<td>$row[head_teacher_remark]</td>";
                echo "<td>$row[datetimeentry]</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<div style='color:red'>Terminal history query failed.</div>";
        }
        echo "</div>";

        echo "<div class='form-entry' align='left'>";
        echo "<h3>Results History</h3>";
        $_SQL_H2 = mysqli_query($con, "SELECT * FROM vw_student_results_history WHERE userid='$_StudentIdSafe' ORDER BY batch, semester, subject, testtype");
        if($_SQL_H2){
            echo "<table width='100%'>";
            echo "<thead><th>Class</th><th>Batch</th><th>Semester</th><th>Subject</th><th>Test Type</th><th>Score</th><th>Total</th><th>Date/Time</th></thead><tbody>";
            while($row = mysqli_fetch_array($_SQL_H2, MYSQLI_ASSOC)){
                echo "<tr>";
                echo "<td>$row[class_name]</td>";
                echo "<td>$row[batch]</td>";
                echo "<td>$row[semester]</td>";
                echo "<td>$row[subject]</td>";
                echo "<td>$row[testtype]</td>";
                echo "<td>$row[mark]</td>";
                echo "<td>$row[totalmark]</td>";
                echo "<td>$row[datetimeentry]</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<div style='color:red'>Results view not found. Run Phase 2 SQL in database.sql.</div>";
        }
        echo "</div>";
    }
    ?>
</div>
</body>
</html>
