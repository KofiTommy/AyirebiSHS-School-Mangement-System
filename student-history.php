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

function sh_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function sh_format_datetime($value){
    $value = trim((string)$value);
    if($value === ""){
        return "Not available";
    }
    $timestamp = strtotime($value);
    if($timestamp === false){
        return $value;
    }
    return date("d M Y, g:i a", $timestamp);
}

function sh_format_date($value){
    $value = trim((string)$value);
    if($value === ""){
        return "Not available";
    }
    $timestamp = strtotime($value);
    if($timestamp === false){
        return $value;
    }
    return date("d M Y", $timestamp);
}

$_StudentId = trim((string)(isset($_GET["studentid"]) ? $_GET["studentid"] : ""));
if($_StudentId=="" && isset($_GET["userid"])){
    $_StudentId = trim((string)$_GET["userid"]);
}
$_StudentIdSafe = mysqli_real_escape_string($con, $_StudentId);

$selectedStudent = null;
$semesterHistory = array();
$terminalHistory = array();
$resultsHistory = array();
$semesterQueryError = "";
$terminalQueryError = "";
$resultsQueryError = "";

if($_StudentId !== ""){
    $studentRes = mysqli_query($con, "SELECT userid, firstname, surname, othernames FROM tblsystemuser WHERE userid='$_StudentIdSafe' LIMIT 1");
    if($studentRes && ($studentRow = mysqli_fetch_array($studentRes, MYSQLI_ASSOC))){
        $selectedStudent = $studentRow;
    }

    $semesterRes = mysqli_query($con, "SELECT * FROM vw_student_semester_history WHERE userid='$_StudentIdSafe' ORDER BY batch, semester");
    if($semesterRes){
        while($row = mysqli_fetch_array($semesterRes, MYSQLI_ASSOC)){
            $semesterHistory[] = $row;
        }
    }else{
        $semesterQueryError = "History view not found. Run Phase 2 SQL in database.sql.";
    }

    $terminalRes = mysqli_query($con, "SELECT str.*, b.batch, ce.class_name
                                       FROM tblstudentterminalreport str
                                       LEFT JOIN tblbatch b ON b.batchid=str.batchid
                                       LEFT JOIN tblclass cl ON cl.userid=str.userid AND cl.batchid=str.batchid
                                       LEFT JOIN tblclassentry ce ON ce.class_entryid=cl.class_entryid
                                       WHERE str.userid='$_StudentIdSafe'
                                       ORDER BY str.datetimeentry DESC");
    if($terminalRes){
        while($row = mysqli_fetch_array($terminalRes, MYSQLI_ASSOC)){
            $terminalHistory[] = $row;
        }
    }else{
        $terminalQueryError = "Terminal history query failed.";
    }

    $resultsRes = mysqli_query($con, "SELECT * FROM vw_student_results_history WHERE userid='$_StudentIdSafe' ORDER BY batch, semester, subject, testtype");
    if($resultsRes){
        while($row = mysqli_fetch_array($resultsRes, MYSQLI_ASSOC)){
            $resultsHistory[] = $row;
        }
    }else{
        $resultsQueryError = "Results view not found. Run Phase 2 SQL in database.sql.";
    }
}
?>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/student-history.css">
</head>
<body class="student-history-page">
<div class="header print-hide">
<?php include("menu.php"); ?>
</div>

<div class="main-platform student-history-shell">
    <section class="student-history-search form-entry print-hide" align="left">
        <div class="student-history-search__head">
            <div>
                <span class="student-history-kicker">Academic Report</span>
                <h2>Student Multi-Year History</h2>
                <p>Search a student once and get one clean report showing semester history, remarks, and score records.</p>
            </div>
        </div>

        <form method="get" action="student-history.php" class="student-history-search__form">
            <div class="student-history-field">
                <label for="studentid">Select Student</label>
                <?php
                $_SQL_ST = mysqli_query($con, "SELECT userid,firstname,surname,othernames FROM tblsystemuser WHERE systemtype='Student' ORDER BY firstname ASC");
                echo "<select name='studentid' id='studentid'>";
                echo "<option value=''>Select Student</option>";
                while($row = mysqli_fetch_array($_SQL_ST, MYSQLI_ASSOC)){
                    $_Sel = ($_StudentId==$row["userid"]) ? "selected" : "";
                    echo "<option value='".sh_esc($row["userid"])."' $_Sel>".sh_esc(trim($row["firstname"]." ".$row["othernames"]." ".$row["surname"]))." (".sh_esc($row["userid"]).")</option>";
                }
                echo "</select>";
                ?>
            </div>

            <div class="student-history-field">
                <label for="userid">Or Enter Student ID Manually</label>
                <input type="text" name="userid" id="userid" value="<?php echo sh_esc($_StudentId); ?>" placeholder="Optional manual Student ID">
            </div>

            <div class="student-history-search__actions">
                <button class="button-show" type="submit"><i class="fa fa-search"></i> Show History</button>
                <a href="student-history.php" class="button-print student-history-reset"><i class="fa fa-refresh"></i> Reset</a>
                <?php if($_StudentId !== ""){ ?>
                <button type="button" class="button-print" onclick="window.print();"><i class="fa fa-print"></i> Print Report</button>
                <?php } ?>
            </div>
        </form>
    </section>

    <?php if($_StudentId !== ""){ ?>
    <section class="student-history-report" align="left">
        <div class="student-history-report__hero">
            <div>
                <span class="student-history-kicker">Student Report</span>
                <h2><?php echo $selectedStudent ? sh_esc(trim($selectedStudent["firstname"]." ".$selectedStudent["othernames"]." ".$selectedStudent["surname"])) : "Student Record"; ?></h2>
                <p><?php echo sh_esc($_StudentId); ?><?php if(!$selectedStudent){ ?> · Student record loaded from history tables<?php } ?></p>
            </div>
            <div class="student-history-report__actions print-hide">
                <button type="button" class="button-print" onclick="window.print();"><i class="fa fa-print"></i> Print Report</button>
            </div>
        </div>

        <div class="student-history-metrics">
            <article>
                <span>Semester Records</span>
                <strong><?php echo number_format(count($semesterHistory)); ?></strong>
            </article>
            <article>
                <span>Terminal Reports</span>
                <strong><?php echo number_format(count($terminalHistory)); ?></strong>
            </article>
            <article>
                <span>Score Records</span>
                <strong><?php echo number_format(count($resultsHistory)); ?></strong>
            </article>
            <article>
                <span>Student ID</span>
                <strong><?php echo sh_esc($_StudentId); ?></strong>
            </article>
        </div>

        <section class="student-history-card">
            <div class="student-history-card__head">
                <div>
                    <span class="student-history-kicker">Semester History</span>
                    <h3>Class And Semester Progress</h3>
                </div>
                <span class="student-history-chip"><?php echo number_format(count($semesterHistory)); ?> record(s)</span>
            </div>

            <?php if($semesterQueryError !== ""){ ?>
            <div class="student-history-alert student-history-alert--error"><?php echo sh_esc($semesterQueryError); ?></div>
            <?php }elseif(empty($semesterHistory)){ ?>
            <div class="student-history-empty">No semester history was found for this student.</div>
            <?php }else{ ?>
            <div class="student-history-table-wrap">
                <table class="student-history-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Batch</th>
                            <th>Semester</th>
                            <th>Status</th>
                            <th>Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($semesterHistory as $row){ ?>
                        <tr>
                            <td data-label="Student"><?php echo sh_esc(trim($row["firstname"]." ".$row["othernames"]." ".$row["surname"])); ?><br><small><?php echo sh_esc($row["userid"]); ?></small></td>
                            <td data-label="Class"><?php echo sh_esc($row["class_name"]); ?></td>
                            <td data-label="Batch"><?php echo sh_esc($row["batch"]); ?></td>
                            <td data-label="Semester"><?php echo sh_esc($row["semester"]); ?></td>
                            <td data-label="Status"><span class="student-history-pill"><?php echo sh_esc($row["semester_status"]); ?></span></td>
                            <td data-label="Registered"><?php echo sh_esc(sh_format_datetime($row["semester_registered_on"])); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </section>

        <section class="student-history-card">
            <div class="student-history-card__head">
                <div>
                    <span class="student-history-kicker">Terminal Reports</span>
                    <h3>Remarks And Promotion History</h3>
                </div>
                <span class="student-history-chip"><?php echo number_format(count($terminalHistory)); ?> record(s)</span>
            </div>

            <?php if($terminalQueryError !== ""){ ?>
            <div class="student-history-alert student-history-alert--error"><?php echo sh_esc($terminalQueryError); ?></div>
            <?php }elseif(empty($terminalHistory)){ ?>
            <div class="student-history-empty">No terminal or remark history was found for this student.</div>
            <?php }else{ ?>
            <div class="student-history-table-wrap">
                <table class="student-history-table">
                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>Class</th>
                            <th>Roll</th>
                            <th>Attendance</th>
                            <th>Total Attendance</th>
                            <th>Promoted To</th>
                            <th>Conduct</th>
                            <th>Teacher Remark</th>
                            <th>Head Remark</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($terminalHistory as $row){ ?>
                        <tr>
                            <td data-label="Batch"><?php echo sh_esc($row["batch"]); ?></td>
                            <td data-label="Class"><?php echo sh_esc($row["class_name"]); ?></td>
                            <td data-label="Roll"><?php echo sh_esc($row["roll"]); ?></td>
                            <td data-label="Attendance"><?php echo sh_esc($row["attendance"]); ?></td>
                            <td data-label="Total Attendance"><?php echo sh_esc($row["totalattendance"]); ?></td>
                            <td data-label="Promoted To"><?php echo sh_esc($row["promotedto"]); ?></td>
                            <td data-label="Conduct"><?php echo sh_esc($row["conduct"]); ?></td>
                            <td data-label="Teacher Remark"><?php echo sh_esc($row["class_teacher_remark"]); ?></td>
                            <td data-label="Head Remark"><?php echo sh_esc($row["head_teacher_remark"]); ?></td>
                            <td data-label="Date"><?php echo sh_esc(sh_format_datetime($row["datetimeentry"])); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </section>

        <section class="student-history-card">
            <div class="student-history-card__head">
                <div>
                    <span class="student-history-kicker">Score History</span>
                    <h3>Assessment Records</h3>
                </div>
                <span class="student-history-chip"><?php echo number_format(count($resultsHistory)); ?> record(s)</span>
            </div>

            <?php if($resultsQueryError !== ""){ ?>
            <div class="student-history-alert student-history-alert--error"><?php echo sh_esc($resultsQueryError); ?></div>
            <?php }elseif(empty($resultsHistory)){ ?>
            <div class="student-history-empty">No score history was found for this student.</div>
            <?php }else{ ?>
            <div class="student-history-table-wrap">
                <table class="student-history-table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Batch</th>
                            <th>Semester</th>
                            <th>Subject</th>
                            <th>Test Type</th>
                            <th>Score</th>
                            <th>Total</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($resultsHistory as $row){ ?>
                        <tr>
                            <td data-label="Class"><?php echo sh_esc($row["class_name"]); ?></td>
                            <td data-label="Batch"><?php echo sh_esc($row["batch"]); ?></td>
                            <td data-label="Semester"><?php echo sh_esc($row["semester"]); ?></td>
                            <td data-label="Subject"><?php echo sh_esc($row["subject"]); ?></td>
                            <td data-label="Test Type"><?php echo sh_esc($row["testtype"]); ?></td>
                            <td data-label="Score"><?php echo sh_esc($row["mark"]); ?></td>
                            <td data-label="Total"><?php echo sh_esc($row["totalmark"]); ?></td>
                            <td data-label="Date"><?php echo sh_esc(sh_format_datetime($row["datetimeentry"])); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </section>
    </section>
    <?php } ?>
</div>
</body>
</html>
