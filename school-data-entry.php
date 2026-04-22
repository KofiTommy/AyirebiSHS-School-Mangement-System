<?php
session_start();
$_SESSION['Message'] = "";
include("dbstring.php");

if(!function_exists('school_data_column_exists')){
function school_data_column_exists($con, $table, $column){
    $tableEsc = mysqli_real_escape_string($con, trim((string)$table));
    $columnEsc = mysqli_real_escape_string($con, trim((string)$column));
    $result = @mysqli_query($con, "SHOW COLUMNS FROM `$tableEsc` LIKE '$columnEsc'");
    return ($result && mysqli_num_rows($result) > 0);
}
}

if(!function_exists('ensure_school_data_year_column')){
function ensure_school_data_year_column($con){
    static $done = false;
    if($done || !$con){
        return;
    }
    $done = true;
    if(!school_data_column_exists($con, 'tblschoolinfo', 'academicyear')){
        @mysqli_query($con, "ALTER TABLE tblschoolinfo ADD COLUMN academicyear VARCHAR(10) NOT NULL DEFAULT '' AFTER termname");
        @mysqli_query($con, "UPDATE tblschoolinfo SET academicyear=YEAR(datetimeentry) WHERE academicyear=''");
    }
}
}

ensure_school_data_year_column($con);

@$_ClassId = $_POST['classid'];
@$_Infoid = trim((string)$_POST['infoid']);
@$_SchoolClosesInput = trim((string)$_POST['schoolcloses-date']);
@$_SchoolResumesInput = trim((string)$_POST['schoolresumes']);
@$_SchoolCloses = ($_SchoolClosesInput !== "" && strtotime($_SchoolClosesInput)) ? date("Y-m-d", strtotime($_SchoolClosesInput)) : "";
@$_SchoolResumes = ($_SchoolResumesInput !== "" && strtotime($_SchoolResumesInput)) ? date("Y-m-d", strtotime($_SchoolResumesInput)) : "";
@$_AcademicYear = trim((string)$_POST['academicyear']);
@$_Term = trim((string)$_POST['term']);
@$_BatchId = trim((string)$_POST['batchid']);
@$_Recordedby = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";

include("shortcode.php");
$_FormInfoId = $shortcode;
$_FormAcademicYear = ($_AcademicYear !== "" ? $_AcademicYear : date("Y"));
$_FormTerm = $_Term;
$_FormBatchId = $_BatchId;
$_FormSchoolCloses = ($_SchoolCloses !== "" && strtotime($_SchoolCloses)) ? date("d/m/Y", strtotime($_SchoolCloses)) : "";
$_FormSchoolResumes = ($_SchoolResumes !== "" && strtotime($_SchoolResumes)) ? date("d/m/Y", strtotime($_SchoolResumes)) : "";
$_FormButtonText = "SAVE DATA";
$_IsEditMode = false;

if(isset($_POST['register_school_data'])){
    if($_Infoid === "" || $_SchoolCloses === "" || $_SchoolResumes === "" || $_AcademicYear === "" || $_Term === "" || $_BatchId === ""){
        $_SESSION['Message'] = "<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>Please select the academic year, semester, batch, school closes date, and next semester begins date.</div>";
    } else {
        $_InfoidEsc = mysqli_real_escape_string($con, $_Infoid);
        $_AcademicYearEsc = mysqli_real_escape_string($con, $_AcademicYear);
        $_TermEsc = mysqli_real_escape_string($con, $_Term);
        $_BatchIdEsc = mysqli_real_escape_string($con, $_BatchId);
        $_RecordedbyEsc = mysqli_real_escape_string($con, $_Recordedby);
        $_SchoolClosesEsc = mysqli_real_escape_string($con, $_SchoolCloses);
        $_SchoolResumesEsc = mysqli_real_escape_string($con, $_SchoolResumes);

        $_SQL_CHECK = mysqli_query($con, "SELECT * FROM tblschoolinfo WHERE batchid='$_BatchIdEsc' AND termname='$_TermEsc' AND academicyear='$_AcademicYearEsc' AND infoid<>'$_InfoidEsc' LIMIT 1");
        if($_SQL_CHECK && mysqli_num_rows($_SQL_CHECK) > 0){
            @$_BatchName = "";
            $_SQL_BATCH = mysqli_query($con, "SELECT * FROM tblbatch WHERE batchid='$_BatchIdEsc' LIMIT 1");
            if($row_ba = mysqli_fetch_array($_SQL_BATCH, MYSQLI_ASSOC)){
                $_BatchName = $row_ba['batch'];
            }
            $_SESSION['Message'] = "<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>School data has already been saved for ".$_AcademicYear." Semester ".$_Term." - Batch: ".$_BatchName."</div>";
        } else {
            $_SQL_EXISTS = mysqli_query($con, "SELECT infoid FROM tblschoolinfo WHERE infoid='$_InfoidEsc' LIMIT 1");
            if($_SQL_EXISTS && mysqli_num_rows($_SQL_EXISTS) > 0){
                $_SQL_EXECUTE = mysqli_query($con, "UPDATE tblschoolinfo SET
                    batchid='$_BatchIdEsc',
                    termname='$_TermEsc',
                    academicyear='$_AcademicYearEsc',
                    schoolcloses=STR_TO_DATE('$_SchoolClosesEsc','%Y-%m-%d'),
                    schoolresumes=STR_TO_DATE('$_SchoolResumesEsc','%Y-%m-%d'),
                    recordedby='$_RecordedbyEsc'
                    WHERE infoid='$_InfoidEsc' LIMIT 1");
            } else {
                $_SQL_EXECUTE = mysqli_query($con, "INSERT INTO tblschoolinfo(infoid,batchid,termname,academicyear,schoolcloses,schoolresumes,datetimeentry,status,recordedby)
                    VALUES('$_InfoidEsc','$_BatchIdEsc','$_TermEsc','$_AcademicYearEsc',STR_TO_DATE('$_SchoolClosesEsc','%Y-%m-%d'),STR_TO_DATE('$_SchoolResumesEsc','%Y-%m-%d'),NOW(),'active','$_RecordedbyEsc')");
            }

            if($_SQL_EXECUTE){
                $_SESSION['Message'] = "<div style='color:green;padding:5px;text-align:center;border:1px solid #aea;background-color:#efe;'>School information successfully saved for ".$_AcademicYear." Semester ".$_Term.".</div>";
                include("shortcode.php");
                $_FormInfoId = $shortcode;
                $_FormAcademicYear = date("Y");
                $_FormTerm = "";
                $_FormBatchId = "";
                $_FormSchoolCloses = "";
                $_FormSchoolResumes = "";
                $_FormButtonText = "SAVE DATA";
                $_IsEditMode = false;
            } else {
                $_SESSION['Message'] = "<div style='color:red;padding:5px;text-align:center;border:1px solid #eaa;background-color:#fee;'>School information failed to save.</div>";
            }
        }
    }
}

if(isset($_GET["delete_school"])){
    $_DeleteInfoId = mysqli_real_escape_string($con, trim((string)$_GET['delete_school']));
    $_SQL_EXECUTE = mysqli_query($con, "DELETE FROM tblschoolinfo WHERE infoid='$_DeleteInfoId' LIMIT 1");
    if($_SQL_EXECUTE){
        $_SESSION['Message'] = "<div style='color:maroon;text-align:center;background-color:white'>School information successfully deleted.</div>";
    } else {
        $_Error = mysqli_error($con);
        $_SESSION['Message'] = "<div style='color:red;text-align:center'>School information failed to delete, Error: $_Error</div>";
    }
}

if(isset($_GET["edit_school"])){
    $_EditInfoId = mysqli_real_escape_string($con, trim((string)$_GET['edit_school']));
    $_SQL_EDIT = mysqli_query($con, "SELECT * FROM tblschoolinfo WHERE infoid='$_EditInfoId' LIMIT 1");
    if($_SQL_EDIT && ($row_edit = mysqli_fetch_array($_SQL_EDIT, MYSQLI_ASSOC))){
        $_FormInfoId = $row_edit['infoid'];
        $_FormAcademicYear = trim((string)$row_edit['academicyear']) !== "" ? trim((string)$row_edit['academicyear']) : date("Y", strtotime((string)$row_edit['datetimeentry']));
        $_FormTerm = trim((string)$row_edit['termname']);
        $_FormBatchId = trim((string)$row_edit['batchid']);
        $_FormSchoolCloses = (trim((string)$row_edit['schoolcloses']) !== "") ? date("d/m/Y", strtotime($row_edit['schoolcloses'])) : "";
        $_FormSchoolResumes = (trim((string)$row_edit['schoolresumes']) !== "") ? date("d/m/Y", strtotime($row_edit['schoolresumes'])) : "";
        $_FormButtonText = "UPDATE DATA";
        $_IsEditMode = true;
    }
}
?>

<html>
<head>
<?php
include("links.php");
?>

</head>
<body>

	<div class="header">
	<?php
	include("menu.php");
	?>		
	</div>
<div class="main-platform" style="">
	<table width="100%">
		<tr>
			<td valign="top" width="30%" align="center">
				<div class="form-entry" align="left">
			
			<h3>School Data Entry</h3>
			<p style="margin-top:-4px;color:#555;">Save one record per batch and semester, so each semester can carry its own closing and reopening dates.</p>
		
			<form method="post" id="formID" name="formID" action="school-data-entry.php">

			<label>School Data Id</label><br/>
			<input type="text" id="infoid" name="infoid" value="<?php echo htmlspecialchars($_FormInfoId, ENT_QUOTES, 'UTF-8'); ?>" class="validate[required]" readonly/><br/><br/>

                <label>Academic Year</label><br/>
                <?php
                echo "<select id='academicyear' name='academicyear' class='validate[required]'>";
                echo "<option value=''>Select Year</option>";
                for($_YearOption = 2030; $_YearOption >= ((int)date("Y")) - 4; $_YearOption--){
                    $_SelectedYear = ((string)$_YearOption === (string)$_FormAcademicYear) ? "selected" : "";
                    echo "<option value='$_YearOption' $_SelectedYear>$_YearOption</option>";
                }
                echo "</select><br/><br/>";
                ?>

				<select id="term" name="term">
					<option value="" class="validate[required]">Select Semester</option>
					<option value="1" <?php echo ($_FormTerm === "1" ? "selected" : ""); ?>>1</option>
					<option value="2" <?php echo ($_FormTerm === "2" ? "selected" : ""); ?>>2</option>
				</select>
				<br/><br/>

<script type="text/javascript">
function show_alert(){
 alert("Please select Date Time Picker");
 }
</script>
<script src="scripts/datetimepicker_css.js"></script>

        <?php
         $tomorrow = mktime(0,0,0,date("m")+1,date("d"),date("Y"));
          $tdate= date("d/m/Y", $tomorrow);
         ?>
      <input type="hidden" name="todate" id="todate" value="<?php echo $tdate; ?>">


				<label>School Closes</label><br/>
				<input type="text" maxlength="25" size="25" onclick="javascript:NewCssCal ('schoolcloses-date','ddMMyyyy','','','','','')" id="schoolcloses-date" name="schoolcloses-date" value="<?php echo htmlspecialchars($_FormSchoolCloses, ENT_QUOTES, 'UTF-8'); ?>" readonly onchange="CheckSchoolCloses()"/>
				<br/><br/>
     
	<label>Next Semester Begins</label><br/>
				<input type="text" maxlength="25" size="25" onclick="javascript:NewCssCal ('schoolresumes','ddMMyyyy','','','','','')" id="schoolresumes" name="schoolresumes" value="<?php echo htmlspecialchars($_FormSchoolResumes, ENT_QUOTES, 'UTF-8'); ?>" readonly onchange="CheckSchoolResume()" />
				<br/><br/>
     		

				<?php	
			$_SQL_2 = mysqli_query($con, "SELECT * FROM tblbatch ORDER BY datetimeentry DESC");

			echo "<select id='batchid' name='batchid' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row = mysqli_fetch_array($_SQL_2, MYSQLI_ASSOC)){
                    $_SelectedBatch = ($_FormBatchId === $row['batchid']) ? "selected" : "";
					echo "<option value='$row[batchid]' $_SelectedBatch>$row[batch]</option>";
				}
				
			echo "</select><br/><br/>";
			?>

			<div align="center"><button class="button-save" id="register_school_data" name="register_school_data"><i class="fa fa-save"></i> <?php echo htmlspecialchars($_FormButtonText, ENT_QUOTES, 'UTF-8'); ?></button></div>
            <?php if($_IsEditMode){ ?>
            <div align="center" style="padding-top:10px;"><a href="school-data-entry.php">Cancel Edit</a></div>
            <?php } ?>
		</form>

		</div>
			</td>
<td width="70%">
<div class="form-entry" align="left">
<?php
echo $_SESSION['Message'];
include("dbstring.php");

$_SQL_SU = mysqli_query($con, "SELECT * FROM tblschoolinfo si 
INNER JOIN tblbatch bh ON si.batchid=bh.batchid
ORDER BY si.academicyear DESC, bh.datetimeentry DESC, si.termname ASC, si.datetimeentry DESC");
if($_SQL_SU && mysqli_num_rows($_SQL_SU) > 0){
echo "<table width='100%' style='background-color:white'>";
echo "<caption>School Data</caption>";
echo "<thead><th colspan='2'>Task</th><th>Batch</th><th>Year</th><th>Semester</th><th>Label</th><th>School Closes</th><th>Next Semester Begins</th><th>Date/Time</th></thead>";
echo "<tbody>";
while($row = mysqli_fetch_array($_SQL_SU, MYSQLI_ASSOC)){
$_RowYear = trim((string)$row['academicyear']) !== "" ? trim((string)$row['academicyear']) : date("Y", strtotime((string)$row['datetimeentry']));
echo "<tr>";
echo "<td align='center'><a title='Edit ($_RowYear Semester $row[termname] - $row[batch])' href='school-data-entry.php?edit_school=$row[infoid]'><i class='fa fa-edit' style='color:royalblue'></i></a></td>";
echo "<td align='center'><a title='Delete ($row[batch])' href='school-data-entry.php?delete_school=$row[infoid]'><i class='fa fa-trash-o' style='color:red'></i></a></td>";
echo "<td align='center'>$row[batch]</td>";
echo "<td align='center'>$_RowYear</td>";
echo "<td align='center'>$row[termname]</td>";
echo "<td align='center'>$_RowYear Semester $row[termname]</td>";				
echo "<td align='center'>$row[schoolcloses]</td>";
echo "<td align='center'>$row[schoolresumes]</td>";
echo "<td align='center'>$row[datetimeentry]</td>";
echo "</tr>";
}
echo "</tbody>";
echo "</table>";
}
?>
</div>
</td>
</tr>
</table>

<br/><br/>
<button onclick="topFunction()" id="myBtn" title="Go to top">Top</button> 

 <script>
var mybutton = document.getElementById("myBtn");

window.onscroll = function() {scrollFunction()};

function scrollFunction() {
  if (document.body.scrollTop > 50 || document.documentElement.scrollTop > 50) {
    mybutton.style.display = "block";
  } else {
    mybutton.style.display = "none";
  }
}

function topFunction() {
  document.body.scrollTop = 0;
  document.documentElement.scrollTop = 0;
}
</script>
</div>
</body>
</html>
