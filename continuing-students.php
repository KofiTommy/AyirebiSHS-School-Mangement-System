<?php
session_start();
include("dbstring.php");
include("check-login.php");

if (!isset($_SESSION['ACCESSLEVEL']) || $_SESSION['ACCESSLEVEL'] != "administrator") {
    header("location:index.php");
    exit();
}

@$_BatchId = $_GET["batchid"];
@$_ClassId = $_GET["classid"];
$_BatchIdSafe = mysqli_real_escape_string($con, $_BatchId);
$_ClassIdSafe = mysqli_real_escape_string($con, $_ClassId);
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
        <h3>Continuing Students By Semester</h3>
        <form method="get" action="continuing-students.php">
            <table>
                <tr>
                    <td>
                        <label>Batch / Semester</label><br/>
                        <?php
                        $_SQL_B = mysqli_query($con, "SELECT batchid,batch,status FROM tblbatch ORDER BY datetimeentry DESC");
                        echo "<select name='batchid' id='batchid'>";
                        echo "<option value=''>Select Batch</option>";
                        while($row = mysqli_fetch_array($_SQL_B, MYSQLI_ASSOC)){
                            $_Sel = ($_BatchId==$row["batchid"]) ? "selected" : "";
                            echo "<option value='$row[batchid]' $_Sel>$row[batch] ($row[status])</option>";
                        }
                        echo "</select>";
                        ?>
                    </td>
                    <td>
                        <label>Class (optional)</label><br/>
                        <?php
                        $_SQL_C = mysqli_query($con, "SELECT class_entryid,class_name FROM tblclassentry ORDER BY class_name ASC");
                        echo "<select name='classid' id='classid'>";
                        echo "<option value=''>All Classes</option>";
                        while($row = mysqli_fetch_array($_SQL_C, MYSQLI_ASSOC)){
                            $_Sel = ($_ClassId==$row["class_entryid"]) ? "selected" : "";
                            echo "<option value='$row[class_entryid]' $_Sel>$row[class_name]</option>";
                        }
                        echo "</select>";
                        ?>
                    </td>
                    <td valign="bottom" style="padding-left:8px;">
                        <button class="button-show"><i class="fa fa-search"></i> SHOW</button>
                    </td>
                </tr>
            </table>
        </form>
    </div>

    <div class="form-entry" align="left">
        <?php
        $sql = "SELECT * FROM vw_continuing_students WHERE 1=1";
        if($_BatchId!=""){
            $sql .= " AND batchid='$_BatchIdSafe'";
        }
        if($_ClassId!=""){
            $sql .= " AND class_entryid='$_ClassIdSafe'";
        }
        $sql .= " ORDER BY class_name ASC, firstname ASC";

        $_SQL_LIST = mysqli_query($con, $sql);
        echo "<table width='100%'>";
        echo "<caption>Continuing Students</caption>";
        echo "<thead><th>*</th><th>Student</th><th>Class</th><th>Batch</th><th>Gender</th><th>Residence</th><th>Reports</th><th>Date/Time</th></thead><tbody>";
        $serial = 0;
        while($row = mysqli_fetch_array($_SQL_LIST, MYSQLI_ASSOC)){
            echo "<tr>";
            echo "<td>".(++$serial).".</td>";
            echo "<td>$row[firstname] $row[othernames] $row[surname] ($row[userid])</td>";
            echo "<td>$row[class_name]</td>";
            echo "<td>$row[batch]</td>";
            echo "<td>$row[gender]</td>";
            echo "<td>$row[residencetype]</td>";
            echo "<td align='center'><a href='student-history.php?studentid=$row[userid]' title='All Reports'><i class='fa fa-history' style='color:#0b63ce'></i></a></td>";
            echo "<td>$row[datetimeentry]</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        ?>
    </div>
</div>
</body>
</html>


