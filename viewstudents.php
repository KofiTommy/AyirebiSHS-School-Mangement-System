<?php
session_start();
$_SESSION['Message']="";

if(!function_exists('normalize_gender_label')){
function normalize_gender_label($gender){
    $g = strtoupper(trim((string)$gender));
    if(in_array($g, array('M','MALE','BOY','B'))){ return 'Boy'; }
    if(in_array($g, array('F','FEMALE','GIRL','G'))){ return 'Girl'; }
    return '';
}
}

if(!function_exists('normalize_residence_label')){
function normalize_residence_label($residence){
    $r = strtoupper(trim((string)$residence));
    if($r==='DAY' || $r==='D'){ return 'Day'; }
    if($r==='BOARDING' || $r==='BOARDER' || $r==='B'){ return 'Boarding'; }
    return '';
}
}

if(!function_exists('extract_programme_label')){
function extract_programme_label($className){
    $cn = strtoupper(trim((string)$className));
    if($cn===''){ return 'Others'; }

    // Normalize punctuation/spacing so variants like "3 Gen. Art1" collapse correctly.
    $norm = preg_replace('/[^A-Z0-9]+/', ' ', $cn);
    $norm = preg_replace('/([A-Z])([0-9])/', '$1 $2', $norm);
    $norm = preg_replace('/\s+/', ' ', trim($norm));

    if(preg_match('/\bGEN(?:ERAL)?\s*ARTS?\b/', $norm)){ return 'General Arts'; }
    if(preg_match('/\bBUS(?:INESS)?\b|\bBIZ\b/', $norm)){ return 'Business'; }
    if(preg_match('/\bVIS(?:UAL)?\s*ARTS?\b|\bV\s*ARTS?\b/', $norm)){ return 'Visual Arts'; }
    if(preg_match('/\bGEN(?:ERAL)?\s*SCI(?:ENCE)?\b|\bSCI(?:ENCE)?\b/', $norm)){ return 'General Science'; }
    if(preg_match('/\bTECH(?:NICAL)?\b/', $norm)){ return 'Technical'; }
    if(preg_match('/\bHOME\s*ECON(?:S|OMICS)?\b|\bH\s*E\b/', $norm)){ return 'Home Economics'; }
    if(preg_match('/\bAGRIC(?:ULTURE)?\b|\bAGRI\b/', $norm)){ return 'Agric Science'; }
    if(preg_match('/\bSTEM\b/', $norm)){ return 'STEM'; }
    if(preg_match('/\bLANG(?:UAGE)?\b/', $norm)){ return 'LANG'; }

    $clean = preg_replace('/\b(SHS|FORM)\s*[123]\b/i', '', (string)$className);
    $clean = preg_replace('/\s+[0-9]+\s*$/', '', $clean);
    $clean = trim(preg_replace('/\s+/', ' ', (string)$clean));
    if($clean===''){ return 'Others'; }
    return ucwords(strtolower($clean));
}
}

if(isset($_POST["print_batch_programme_summary"]))
{
ini_set('log_errors', '1');
ini_set('error_log', __DIR__.'/print-error.log');
error_reporting(E_ALL);
include("dbstring.php");
include("company.php");
if(!file_exists(__DIR__.'/fpdf181/fpdf.php')){
    http_response_code(500);
    exit("Print setup error: PDF library file not found.");
}
require(__DIR__.'/fpdf181/fpdf.php');

@$_BatchID=$_POST["print_batchid"];
if($_BatchID==""){
    http_response_code(400);
    exit("Print request error: missing batch.");
}

$_BatchName="";
$_SQL_BATCH=mysqli_query($con,"SELECT batch FROM tblbatch WHERE batchid='$_BatchID' LIMIT 1");
if($_SQL_BATCH && $row_ba=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC)){
    $_BatchName=$row_ba['batch'];
}

$_Counts = array();
$_Seen = array();
$_Unclassified = 0;

$_SQL_STUDENTS=mysqli_query($con,"
    SELECT DISTINCT su.userid,su.gender,su.residencetype,ce.class_name
    FROM tblsystemuser su
    INNER JOIN tblclass cl ON cl.userid=su.userid
    INNER JOIN tblclassentry ce ON ce.class_entryid=cl.class_entryid
    WHERE su.systemtype='Student'
      AND su.status='active'
      AND cl.status='active'
      AND cl.batchid='".mysqli_real_escape_string($con, $_BatchID)."'
");
if($_SQL_STUDENTS){
    while($row=mysqli_fetch_array($_SQL_STUDENTS,MYSQLI_ASSOC)){
        $_key = $row['userid']."|".$row['class_name'];
        if(isset($_Seen[$_key])){ continue; }
        $_Seen[$_key] = 1;

        $_programme = extract_programme_label($row['class_name']);
        $_gender = normalize_gender_label($row['gender']);
        $_residence = normalize_residence_label($row['residencetype']);

        if(!isset($_Counts[$_programme])){
            $_Counts[$_programme] = array(
                "DayBoy" => 0,
                "DayGirl" => 0,
                "BoardingBoy" => 0,
                "BoardingGirl" => 0
            );
        }

        if($_gender==='' || $_residence===''){
            $_Unclassified++;
            continue;
        }

        if($_residence==="Day" && $_gender==="Boy"){ $_Counts[$_programme]["DayBoy"]++; }
        if($_residence==="Day" && $_gender==="Girl"){ $_Counts[$_programme]["DayGirl"]++; }
        if($_residence==="Boarding" && $_gender==="Boy"){ $_Counts[$_programme]["BoardingBoy"]++; }
        if($_residence==="Boarding" && $_gender==="Girl"){ $_Counts[$_programme]["BoardingGirl"]++; }
    }
}

if(count($_Counts)===0){
    $_Counts["Others"] = array("DayBoy"=>0,"DayGirl"=>0,"BoardingBoy"=>0,"BoardingGirl"=>0);
}

$_PreferredOrder = array("General Arts","Business","Visual Arts","General Science","Technical","Home Economics","Agric Science","STEM","LANG","Others");
$_Programmes = array();
foreach($_PreferredOrder as $_p){
    if(isset($_Counts[$_p])){ $_Programmes[] = $_p; }
}
foreach(array_keys($_Counts) as $_p){
    if(!in_array($_p, $_Programmes, true)){ $_Programmes[] = $_p; }
}

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true,15);
$pdf->SetFont('Arial','B',10);

$logoPath = "";
if(!empty($_Logo)){
    $candidate = "logo/".$_Logo;
    if(file_exists($candidate)){
        $logoPath = $candidate;
    }
}
if($logoPath=="" && file_exists("logo/logo.png")){
    $logoPath = "logo/logo.png";
}
if($logoPath=="" && file_exists("logo/logo.jpeg")){
    $logoPath = "logo/logo.jpeg";
}
if($logoPath!=""){
    $pdf->Image($logoPath,95,4,20);
}
$pdf->Ln(14);

$tableTotalWidth = 48+14+14+14+14+14+14+22;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($tableTotalWidth,7,strtoupper($_CompanyName),0,1,'C',true);
$pdf->SetFont('Arial','',9);
$pdf->Cell($tableTotalWidth,6,$_Address.", ".$_Location,0,1,'C',true);
$pdf->Cell($tableTotalWidth,6,'TEL:'. $_Telephone1. " ". $_Telephone2,0,1,'C',true);
$pdf->Ln(2);

$caption = "Total number of students in the school by programme";
$pdf->SetFont('Arial','B',9);
$pdf->Cell($tableTotalWidth,7,$caption,0,1,'L',true);
$pdf->SetFont('Arial','',8);
$pdf->Cell($tableTotalWidth,5,"Batch: ".$_BatchName,0,1,'L',true);
$pdf->Ln(2);

$wProg = 48;
$w = 14;
$wGrand = 22;
$hTop = 7;
$hSub = 7;

$x = $pdf->GetX();
$pdf->SetFont('Arial','B',8);
$pdf->Cell($wProg,$hTop+$hSub,'Programme',1,0,'L');
$pdf->Cell($w*2,$hTop,'Day',1,0,'C');
$pdf->Cell($w*2,$hTop,'Boarding',1,0,'C');
$pdf->Cell($w*2,$hTop,'Total',1,0,'C');
$pdf->Cell($wGrand,$hTop+$hSub,'Grand Total',1,1,'C');

$pdf->SetX($x+$wProg);
$pdf->Cell($w,$hSub,'Boy',1,0,'C');
$pdf->Cell($w,$hSub,'Girl',1,0,'C');
$pdf->Cell($w,$hSub,'Boy',1,0,'C');
$pdf->Cell($w,$hSub,'Girl',1,0,'C');
$pdf->Cell($w,$hSub,'Boy',1,0,'C');
$pdf->Cell($w,$hSub,'Girl',1,1,'C');

$pdf->SetFont('Arial','',8);
$_GrandAll = 0;
foreach($_Programmes as $_P){
    $_DayBoy = $_Counts[$_P]["DayBoy"];
    $_DayGirl = $_Counts[$_P]["DayGirl"];
    $_BoardBoy = $_Counts[$_P]["BoardingBoy"];
    $_BoardGirl = $_Counts[$_P]["BoardingGirl"];
    $_TotBoy = $_DayBoy + $_BoardBoy;
    $_TotGirl = $_DayGirl + $_BoardGirl;
    $_RowGrand = $_TotBoy + $_TotGirl;
    $_GrandAll += $_RowGrand;

    $pdf->Cell($wProg,7,$_P,1,0,'L');
    $pdf->Cell($w,7,($_DayBoy>0 ? $_DayBoy : ''),1,0,'C');
    $pdf->Cell($w,7,($_DayGirl>0 ? $_DayGirl : ''),1,0,'C');
    $pdf->Cell($w,7,($_BoardBoy>0 ? $_BoardBoy : ''),1,0,'C');
    $pdf->Cell($w,7,($_BoardGirl>0 ? $_BoardGirl : ''),1,0,'C');
    $pdf->Cell($w,7,($_TotBoy>0 ? $_TotBoy : ''),1,0,'C');
    $pdf->Cell($w,7,($_TotGirl>0 ? $_TotGirl : ''),1,0,'C');
    $pdf->Cell($wGrand,7,($_RowGrand>0 ? $_RowGrand : ''),1,1,'C');
}

$pdf->SetFont('Arial','B',8);
$pdf->Cell($wProg+(6*$w),7,'Grand Total',1,0,'R');
$pdf->Cell($wGrand,7,$_GrandAll,1,1,'C');

$pdf->Ln(8);
$pdf->SetFont('Arial','',8);
$pdf->Cell(0,6,'Print Date/Time: '.date("d/m/Y H:i:s"),0,1,'L');
if($_Unclassified>0){
    $pdf->Cell(0,6,'Unclassified (missing gender/residence): '.$_Unclassified,0,1,'L');
}

$__pdfName = 'students-programme-summary-batch.pdf';
if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$__pdfName.'"');
$pdf->Output('I', $__pdfName);
exit();
}

if(isset($_POST["print_programme_summary"]))
{
ini_set('log_errors', '1');
ini_set('error_log', __DIR__.'/print-error.log');
error_reporting(E_ALL);
include("dbstring.php");
include("company.php");
if(!file_exists(__DIR__.'/fpdf181/fpdf.php')){
    http_response_code(500);
    exit("Print setup error: PDF library file not found.");
}
require(__DIR__.'/fpdf181/fpdf.php');

@$_ClassentryID=$_POST["print_class_id"];
@$_BatchID=$_POST["print_batchid"];
if($_ClassentryID=="" || $_BatchID==""){
    http_response_code(400);
    exit("Print request error: missing class or batch.");
}

$_ClassName = "";
$_SQLGC=mysqli_query($con,"SELECT class_name FROM tblclassentry WHERE class_entryid='$_ClassentryID' LIMIT 1");
if($_SQLGC && $rowc=mysqli_fetch_array($_SQLGC,MYSQLI_ASSOC)){
    $_ClassName=$rowc["class_name"];
}

$_BatchName="";
$_SQL_BATCH=mysqli_query($con,"SELECT batch FROM tblbatch WHERE batchid='$_BatchID' LIMIT 1");
if($_SQL_BATCH && $row_ba=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC)){
    $_BatchName=$row_ba['batch'];
}

$_FormPrefix = "";
$_FormLabel = "Selected Form";
if(preg_match('/\b(SHS\s*[123])\b/i', $_ClassName, $mForm)){
    $_FormPrefix = strtoupper(trim($mForm[1]));
    if($_FormPrefix=="SHS 1"){ $_FormLabel = "Form One (1)"; }
    elseif($_FormPrefix=="SHS 2"){ $_FormLabel = "Form Two (2)"; }
    elseif($_FormPrefix=="SHS 3"){ $_FormLabel = "Form Three (3)"; }
    else{ $_FormLabel = $_FormPrefix; }
}

$_ClassFilter = "";
if($_FormPrefix!=""){
    $_ClassFilter = " AND UPPER(ce.class_name) LIKE '".mysqli_real_escape_string($con, $_FormPrefix)."%' ";
}else{
    $_ClassFilter = " AND ce.class_entryid='".mysqli_real_escape_string($con, $_ClassentryID)."' ";
}

$_Counts = array();
$_Seen = array();
$_Unclassified = 0;

$_SQL_STUDENTS=mysqli_query($con,"
    SELECT DISTINCT su.userid,su.gender,su.residencetype,ce.class_name
    FROM tblsystemuser su
    INNER JOIN tblclass cl ON cl.userid=su.userid
    INNER JOIN tblclassentry ce ON ce.class_entryid=cl.class_entryid
    WHERE su.systemtype='Student'
      AND su.status='active'
      AND cl.status='active'
      AND cl.batchid='".mysqli_real_escape_string($con, $_BatchID)."'
      $_ClassFilter
");
if($_SQL_STUDENTS){
    while($row=mysqli_fetch_array($_SQL_STUDENTS,MYSQLI_ASSOC)){
        $_key = $row['userid']."|".$row['class_name'];
        if(isset($_Seen[$_key])){ continue; }
        $_Seen[$_key] = 1;

        $_programme = extract_programme_label($row['class_name']);
        $_gender = normalize_gender_label($row['gender']);
        $_residence = normalize_residence_label($row['residencetype']);

        if(!isset($_Counts[$_programme])){
            $_Counts[$_programme] = array(
                "DayBoy" => 0,
                "DayGirl" => 0,
                "BoardingBoy" => 0,
                "BoardingGirl" => 0
            );
        }

        if($_gender==='' || $_residence===''){
            $_Unclassified++;
            continue;
        }

        if($_residence==="Day" && $_gender==="Boy"){ $_Counts[$_programme]["DayBoy"]++; }
        if($_residence==="Day" && $_gender==="Girl"){ $_Counts[$_programme]["DayGirl"]++; }
        if($_residence==="Boarding" && $_gender==="Boy"){ $_Counts[$_programme]["BoardingBoy"]++; }
        if($_residence==="Boarding" && $_gender==="Girl"){ $_Counts[$_programme]["BoardingGirl"]++; }
    }
}

if(count($_Counts)===0){
    $_Counts["Others"] = array("DayBoy"=>0,"DayGirl"=>0,"BoardingBoy"=>0,"BoardingGirl"=>0);
}

$_PreferredOrder = array("General Arts","Business","Visual Arts","General Science","Technical","Home Economics","Agric Science","STEM","LANG","Others");
$_Programmes = array();
foreach($_PreferredOrder as $_p){
    if(isset($_Counts[$_p])){ $_Programmes[] = $_p; }
}
foreach(array_keys($_Counts) as $_p){
    if(!in_array($_p, $_Programmes, true)){ $_Programmes[] = $_p; }
}

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(true,15);
$pdf->SetFont('Arial','B',10);

$logoPath = "";
if(!empty($_Logo)){
    $candidate = "logo/".$_Logo;
    if(file_exists($candidate)){
        $logoPath = $candidate;
    }
}
if($logoPath=="" && file_exists("logo/logo.png")){
    $logoPath = "logo/logo.png";
}
if($logoPath=="" && file_exists("logo/logo.jpeg")){
    $logoPath = "logo/logo.jpeg";
}
if($logoPath!=""){
    $pdf->Image($logoPath,95,4,20);
}
$pdf->Ln(14);

$tableTotalWidth = 48+14+14+14+14+14+14+22;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($tableTotalWidth,7,strtoupper($_CompanyName),0,1,'C',true);
$pdf->SetFont('Arial','',9);
$pdf->Cell($tableTotalWidth,6,$_Address.", ".$_Location,0,1,'C',true);
$pdf->Cell($tableTotalWidth,6,'TEL:'. $_Telephone1. " ". $_Telephone2,0,1,'C',true);
$pdf->Ln(2);

$caption = "3. d. Total number of ".$_FormLabel." students in the school by programme";
$pdf->SetFont('Arial','B',9);
$pdf->Cell($tableTotalWidth,7,$caption,0,1,'L',true);
$pdf->SetFont('Arial','',8);
$pdf->Cell($tableTotalWidth,5,"Batch: ".$_BatchName,0,1,'L',true);
$pdf->Ln(2);

$wProg = 48;
$w = 14;
$wGrand = 22;
$hTop = 7;
$hSub = 7;

$x = $pdf->GetX();
$y = $pdf->GetY();
$pdf->SetFont('Arial','B',8);
$pdf->Cell($wProg,$hTop+$hSub,'Programme',1,0,'L');
$pdf->Cell($w*2,$hTop,'Day',1,0,'C');
$pdf->Cell($w*2,$hTop,'Boarding',1,0,'C');
$pdf->Cell($w*2,$hTop,'Total',1,0,'C');
$pdf->Cell($wGrand,$hTop+$hSub,'Grand Total',1,1,'C');

$pdf->SetX($x+$wProg);
$pdf->Cell($w,$hSub,'Boy',1,0,'C');
$pdf->Cell($w,$hSub,'Girl',1,0,'C');
$pdf->Cell($w,$hSub,'Boy',1,0,'C');
$pdf->Cell($w,$hSub,'Girl',1,0,'C');
$pdf->Cell($w,$hSub,'Boy',1,0,'C');
$pdf->Cell($w,$hSub,'Girl',1,1,'C');

$pdf->SetFont('Arial','',8);
$_GrandAll = 0;
foreach($_Programmes as $_P){
    $_DayBoy = $_Counts[$_P]["DayBoy"];
    $_DayGirl = $_Counts[$_P]["DayGirl"];
    $_BoardBoy = $_Counts[$_P]["BoardingBoy"];
    $_BoardGirl = $_Counts[$_P]["BoardingGirl"];
    $_TotBoy = $_DayBoy + $_BoardBoy;
    $_TotGirl = $_DayGirl + $_BoardGirl;
    $_RowGrand = $_TotBoy + $_TotGirl;
    $_GrandAll += $_RowGrand;

    $pdf->Cell($wProg,7,$_P,1,0,'L');
    $pdf->Cell($w,7,($_DayBoy>0 ? $_DayBoy : ''),1,0,'C');
    $pdf->Cell($w,7,($_DayGirl>0 ? $_DayGirl : ''),1,0,'C');
    $pdf->Cell($w,7,($_BoardBoy>0 ? $_BoardBoy : ''),1,0,'C');
    $pdf->Cell($w,7,($_BoardGirl>0 ? $_BoardGirl : ''),1,0,'C');
    $pdf->Cell($w,7,($_TotBoy>0 ? $_TotBoy : ''),1,0,'C');
    $pdf->Cell($w,7,($_TotGirl>0 ? $_TotGirl : ''),1,0,'C');
    $pdf->Cell($wGrand,7,($_RowGrand>0 ? $_RowGrand : ''),1,1,'C');
}

$pdf->SetFont('Arial','B',8);
$pdf->Cell($wProg+(6*$w),7,'Grand Total',1,0,'R');
$pdf->Cell($wGrand,7,$_GrandAll,1,1,'C');

$pdf->Ln(8);
$pdf->SetFont('Arial','',8);
$pdf->Cell(0,6,'Print Date/Time: '.date("d/m/Y H:i:s"),0,1,'L');
if($_Unclassified>0){
    $pdf->Cell(0,6,'Unclassified (missing gender/residence): '.$_Unclassified,0,1,'L');
}

$__pdfName = 'students-programme-summary.pdf';
if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$__pdfName.'"');
$pdf->Output('I', $__pdfName);
exit();
}

if(isset($_POST["print_student"]))
{
ini_set('log_errors', '1');
ini_set('error_log', __DIR__.'/print-error.log');
error_reporting(E_ALL);
include("dbstring.php");
include("company.php");
//Get all the ordered items
if(!file_exists(__DIR__.'/fpdf181/fpdf.php')){
    http_response_code(500);
    exit("Print setup error: PDF library file not found.");
}
require(__DIR__.'/fpdf181/fpdf.php');
$pdf = new FPDF();
$pdf->AddPage();

$width_cell=array(7,55,20,20,20,20,10,25,15);
$pdf->SetFont('Arial','B',10);
//Background color of header//
//Heading of the pdf
// Logo (safe fallback to avoid hard failure when DB logo path is missing on server)
$logoPath = "";
if(!empty($_Logo)){
    $candidate = "logo/".$_Logo;
    if(file_exists($candidate)){
        $logoPath = $candidate;
    }
}
if($logoPath=="" && file_exists("logo/logo.png")){
    $logoPath = "logo/logo.png";
}
if($logoPath=="" && file_exists("logo/logo.jpeg")){
    $logoPath = "logo/logo.jpeg";
}
if($logoPath!=""){
    $pdf->Image($logoPath,100,3,20);
}
$pdf->Ln(15);

$p=10;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5]+$width_cell[6]+$width_cell[7]+$width_cell[8],10,strtoupper($_CompanyName),0,0,'C',true);
$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5]+$width_cell[6]+$width_cell[7]+$width_cell[8],10,$_Address.", ".$_Location,0,0,'C',true);
$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5]+$width_cell[6]+$width_cell[7]+$width_cell[8],10,'TEL:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);

$text_height = 5;
$text_length = 70;
$n=7;
$pdf->SetFillColor(255,255,255);

@$_ClassentryID=$_POST["print_class_id"];
@$_BatchID=$_POST["print_batchid"];
if($_ClassentryID=="" || $_BatchID==""){
    http_response_code(400);
    exit("Print request error: missing class or batch.");
}

include("dbstring.php");
$_SQL_EXECUTE2=mysqli_query($con,"SELECT * FROM tblsystemuser su INNER JOIN tblclass cl 
ON su.userid=cl.userid WHERE cl.class_entryid='$_ClassentryID'AND cl.batchid='$_BatchID' AND su.systemtype='Student' ORDER BY su.firstname ASC");

//Registered clients
@$_ClassName="";
$_SQLGC=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$_ClassentryID'");
if($rowc=mysqli_fetch_array($_SQLGC,MYSQLI_ASSOC)){
$_ClassName=$rowc["class_name"];
}

@$_BatchName="";
$_SQL_BATCH=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_BatchID'");
if($row_ba=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC)){
		$_BatchName=$row_ba['batch'];
}

if(mysqli_num_rows($_SQL_EXECUTE2)>0){
	$pdf->SetFont('Arial','',9);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4]+$width_cell[5]+$width_cell[6],10,strtoupper(mysqli_num_rows($_SQL_EXECUTE2)." ".$_ClassName ." STUDENT(S) FOUND FOR ". $_BatchName." Batch"),0,0,'L',true);
$pdf->Ln(10);
$pdf->SetFont('Arial','B',7);
//Header starts //
//First header column //
$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);
$pdf->Cell($width_cell[1],10,'STUDENTS',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'BECE INDEX',1,0,'C',true);
$pdf->Cell($width_cell[3],10,'EMAIL',1,0,'C',true);
$pdf->Cell($width_cell[4],10,'MOBILE',1,0,'C',true);
$pdf->Cell($width_cell[5],10,'USERNAME',1,0,'C',true);
$pdf->Cell($width_cell[6],10,'TYPE',1,0,'C',true);
$pdf->Cell($width_cell[7],10,'DATE/TIME',1,0,'C',true);
$pdf->Cell($width_cell[8],10,'STATUS',1,0,'C',true);

///header ends///
$pdf->SetFont('Arial','',6);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;

@$serial=0;
$pdf->Ln(10);
	while($row=mysqli_fetch_array($_SQL_EXECUTE2,MYSQLI_ASSOC)){
	$_FullName=$row["firstname"]." ".$row["surname"]." ". $row["othernames"]."(".$row["userid"].")";
	$bece_index = isset($row["BECEIndexNumber"]) && !empty($row["BECEIndexNumber"])
		? $row["BECEIndexNumber"]
		: (isset($row["BECEIndex"]) && !empty($row["BECEIndex"]) ? $row["BECEIndex"] : "N/A");
	$pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill);
	$pdf->Cell($width_cell[1],10,$_FullName,1,0,'L',$fill);
	$pdf->Cell($width_cell[2],10,$bece_index,1,0,'L',$fill);
	$pdf->Cell($width_cell[3],10,$row["email"],1,0,'L',$fill);
	$pdf->Cell($width_cell[4],10,$row["mobile"],1,0,'C',$fill);
	$pdf->Cell($width_cell[5],10,$row["username"],1,0,'L',$fill);
	$pdf->Cell($width_cell[6],10,$row["systemtype"],1,0,'L',$fill);
	$pdf->Cell($width_cell[7],10,$row["registereddatetime"],1,0,'L',$fill);
	$pdf->Cell($width_cell[8],10,$row["status"],1,0,'C',$fill);
	$fill = !$fill;
	$pdf->Ln(10);
}

$tomorrow = mktime(0,0,0,date("m"),date("d"),date("Y"));
$tdate= date("d/m/Y", $tomorrow);
$pdf->SetFillColor(0,0,0);

$pdf->SetFont('Arial','',8);
$pdf->Cell(0,10,'Print Date/Time: '.$tdate,0);

$pdf->Ln(10); 
$pdf->Cell(0,10,'ADMINISTRATOR:',0);
 
$pdf->Ln(10); 
$pdf->Cell(0,10,'SIGNATURE:.......................................................',0);

$pdf->Ln(85); 
$pdf->Cell(0,10,' ',0);

 //}
//}
}
$__pdfName = 'students-list.pdf';
if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$__pdfName.'"');
$pdf->Output('I', $__pdfName);
exit();
}
?>

<?php
include("dbstring.php");

@$_UserID=$_POST['userid'];
@$_Firstname=$_POST['firstname'];
@$_Surname=$_POST['surname'];
@$_Othernames=$_POST['othernames'];
@$_Gender=$_POST['gender'];
@$_ResidenceType = $_POST['residencetype'];
@$_Birthday=$_POST['birthday'];
@$_Age=$_POST['age'];
@$_PostalAddress=$_POST['postaladdress'];
@$_HomeAddress=$_POST['homeaddress'];
@$_HomeTown=$_POST['hometown'];
@$_Religion=$_POST['religion'];
@$_Relationship=$_POST['relationship'];
@$_BECEIndexNumber = $_POST['beceindexnumber'];
@$_Nextofkin_fullname=$_POST['nextoffullname'];
@$_Nextofcontact=$_POST['nextofkincontact'];
@$_Username=$_POST['username'];
@$_Password=$_POST['password'];
@$_AccessLevel="user";
@$_SystemType=$_POST['systemtype'];
@$_Filename=$_POST['filename'];

if(isset($_POST['register_user'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblsystemuser(userid,firstname,surname,othernames,gender,residencetype,birthday,age,postaladdress,homeaddress,hometown,religion,relationship,beceindexnumber,nextofkin_fullname,nextofkin_contact,registereddatetime,status,username,password,accesslevel,systemtype)
	VALUES('$_UserID','$_Firstname','$_Surname','$_Othernames','$_Gender','$_ResidenceType',STR_TO_DATE('$_Birthday','%d-%m-%Y'),'$_Age','$_PostalAddress','$_HomeAddress','$_HomeTown','$_Religion','$_Relationship','$_BECEIndexNumber,'$_Nextofkin_fullname','$_Nextofcontact',NOW(),'active','$_Username','$_Password','$_AccessLevel','$_SystemType')");
if($_SQL_EXECUTE){
$_SESSION['Message']="<div style='color:green;text-align:center'>User Information Successfully Saved</div>";
}
else{
	$_Error=mysqli_error($con);
	$_SESSION['Message']="<div style='color:red'>User Information Failed to save,Error:$_Error</div>";
}
}
?>

<?php
include("dbstring.php");

if(isset($_GET["block_user"]))
{
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsystemuser SET status='block' WHERE userid='$_GET[block_user]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>User is blocked</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>User failed to block</div>";
	}
}
?>

<?php
include("dbstring.php");

if(isset($_GET["unblock_user"]))
{
$_SQL_EXECUTE=mysqli_query($con,"UPDATE tblsystemuser SET status='active' WHERE userid='$_GET[unblock_user]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>User is active</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>User failed to unblock</div>";
	}
}
?>

<?php
include("dbstring.php");

if(isset($_GET["delete_user"]))
{
$_SQL_EXECUTE=mysqli_query($con,"DELETE FROM tblsystemuser WHERE userid='$_GET[delete_user]'");
	if($_SQL_EXECUTE){
	$_SESSION['Message']="<div style='color:red;text-align:center;background-color:white'>User Record Successfully Deleted</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']="<div style='color:red'>User failed to delete</div>";
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
	<table width="200%">
		<tr>
			<td width="50%" align="center">
				<div class="form-entry" style="">
				<div width="50%">
	<form method="post" action="viewstudents.php">
				<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblclassentry ORDER BY class_name");

			echo "<select id='class_entryid' name='class_entryid' class='validate[required]'>";
			echo "<option value=''>Select Class</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[class_entryid]'>$row[class_name]</option>";
				}
				
			echo "</select><br/><br/>";
			?>	

			<?php	
			$_SQL_2=mysqli_query($con,"SELECT * FROM tblbatch");

			echo "<select id='batchid' name='batchid' class='validate[required]'>";
			echo "<option value=''>Select Batch</option>";
				while($row=mysqli_fetch_array($_SQL_2,MYSQLI_ASSOC)){
					echo "<option value='$row[batchid]'>$row[batch]</option>";
				}
				
			echo "</select><br/><br/>";
			?>	
		</div>
	</td>
<td>
<div width="50%">
<button class="button-show" name="showstudent"><i class="fa fa-search"></i> SHOW STUDENTS</button>	
</div><br/>
</div>
</form>
</td>
</tr>
	</table>
<br/>
<?php
if(isset($_POST["showstudent"]))
{
@$_ClassentryID=$_POST["class_entryid"];
@$_BatchID=$_POST["batchid"];
?>
<div class="form-entry" style="">
				<?php
				echo $_SESSION['Message'];

include("dbstring.php");
$_SQL_EXECUTE2=mysqli_query($con,"SELECT * FROM tblsystemuser su INNER JOIN tblclass cl 
ON su.userid=cl.userid WHERE cl.class_entryid='$_ClassentryID'AND cl.batchid='$_BatchID' AND su.systemtype='Student' ORDER BY su.firstname ASC");

				//Registered clients
				@$_ClassName="";
				$_SQLGC=mysqli_query($con,"SELECT * FROM tblclassentry WHERE class_entryid='$_ClassentryID'");
				if($rowc=mysqli_fetch_array($_SQLGC,MYSQLI_ASSOC)){
				$_ClassName=$rowc["class_name"];
				}
	@$_BatchName="";
	$_SQL_BATCH=mysqli_query($con,"SELECT * FROM tblbatch WHERE batchid='$_BatchID'");
	if($row_ba=mysqli_fetch_array($_SQL_BATCH,MYSQLI_ASSOC)){
		$_BatchName=$row_ba['batch'];
	}

	echo "<form method='post'>";
	echo "<input type='hidden' id='print_batchid_only' name='print_batchid' value='$_BatchID' />";
	echo "<button class='button-print' id='print_batch_programme_summary_top' name='print_batch_programme_summary'><i class='fa fa-print'></i> Print Batch Course Summary</button><br/><br/>";
	echo "</form>";
if(mysqli_num_rows($_SQL_EXECUTE2)>0){
	echo "<form method='post'>";
	echo "<input type='hidden' id='print_class_id' name='print_class_id' value='$_ClassentryID'/>";
	echo "<input type='hidden' id='print_batchid' name='print_batchid' value='$_BatchID' />";
	echo "<button class='button-print' id='print_student' name='print_student'><i class='fa fa-print'></i> Print Student</button><br/><br/>";
	echo "<button class='button-print' id='print_programme_summary' name='print_programme_summary' style='margin-top:4px;'><i class='fa fa-print'></i> Print Programme Summary</button><br/><br/>";
	echo "</form>";
				echo "<table width='100%' style='background-color:white'>";
				echo "<caption>". mysqli_num_rows($_SQL_EXECUTE2)." ".$_ClassName ." STUDENT(S) FOUND FOR ". $_BatchName." Batch</caption>";
				echo "<thead><th colspan=2>TASK</th><th>*</th><th>INDEX NUMBER</th><th>STUDENTS</th><th>RESIDENCE STATUS</th><th>BECE INDEX NO</th><th>MOBILE</th><th>USERNAME</th><th>TYPE</th><th>DATE/TIME</th><th>STATUS</th></thead>";
				echo "<tbody>";
				@$serial=0;
				while($row=mysqli_fetch_array($_SQL_EXECUTE2,MYSQLI_ASSOC)){
					$serial=$serial+1;
					$bece_index = isset($row["BECEIndexNumber"]) && !empty($row["BECEIndexNumber"])
						? $row["BECEIndexNumber"]
						: (isset($row["BECEIndex"]) && !empty($row["BECEIndex"]) ? $row["BECEIndex"] : "N/A");
					echo "<tr>";
					echo "<td align='center'><a title='View $row[firstname] ($row[userid])' href='user-profile.php?view_user=$row[userid]'><i class='fa fa-book' style='color:blue'></i></a></td>";
					echo "<td align='center'><a title='Edit $row[firstname] ($row[userid])' href='register_edit.php?edit_user=$row[userid]'><i class='fa fa-edit' style='color:green'></i></a></td>";
					/*echo "<td>";
					if($row['status']=="active"){
					echo"<a title='Block $row[firstname] ($row[userid])' href='viewstudents.php?block_user=$row[userid]'><i class='fa fa-user' style='color:orange'></i></a>";
					}else{
					echo"<a title='Unblock $row[firstname] ($row[userid])' href='viewstudents.php?unblock_user=$row[userid]'><i class='fa fa-user' style='color:red'></i></a>";
					}
					echo "</td>";
					*/
					echo "<td align='center'>$serial.</td>";
					echo "<td>$row[userid]</td>";
					echo "<td>$row[firstname] $row[surname] $row[othernames]</td>";
					echo "<td align='center'>$row[residencetype]</td>";
					echo "<td align='center'>" . (isset($row['BECEIndexNumber']) ? $row['BECEIndexNumber'] : "N/A") . "</td>";
					echo "<td align='center'>$row[mobile]</td>";
					echo "<td align='center'>$row[username]</td>";
					echo "<td align='center'>$row[systemtype]</td>";
					echo "<td align='center'>$row[registereddatetime]</td>";
					echo "<td align='center'>";
					if($row['status']=="active"){
						echo "<strong style='color:green'><i class='fa fa-check-circle' style='color:green;margin-right:4px'></i>Active</strong>";
					}
					else{
						echo "<strong style='color:red'><i class='fa fa-ban' style='color:red;margin-right:4px'></i>Blocked</strong>";
					}
					echo "</td>";
					echo "</tr>";
				}
				echo "</tbody>";
				echo "</table>";
			}
	?>
</div>
<?php
}
?>

	<br/><br/>
<button onclick="topFunction()" id="myBtn" title="Go to top">Top</button> 

 <script>
//Get the button
var mybutton = document.getElementById("myBtn");

// When the user scrolls down 20px from the top of the document, show the button
window.onscroll = function() {scrollFunction()};

function scrollFunction() {
  if (document.body.scrollTop > 50 || document.documentElement.scrollTop > 50) {
    mybutton.style.display = "block";
  } else {
    mybutton.style.display = "none";
  }
}

// When the user clicks on the button, scroll to the top of the document
function topFunction() {
  document.body.scrollTop = 0;
  document.documentElement.scrollTop = 0;
}
</script>
</div>
</body>
</html>
