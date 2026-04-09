<?php
session_start();
include("dbstring.php");
include_once("online-admission-utils.php");
ensure_online_admission_tables($con);

function oa_letter_redirect($message){
    $_SESSION["ONLINE_ADMISSION_MESSAGE"] = "<div class=\"oa-alert oa-alert--warning\">".htmlspecialchars((string)$message, ENT_QUOTES, "UTF-8")."</div>";
    header("location:online-admission.php");
    exit();
}

if(!isset($_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"], $_SESSION["ONLINE_ADMISSION_APPLICATION_ID"]) ||
    (string)$_SESSION["ONLINE_ADMISSION_TOKEN_AUTH"] !== "1" ||
    trim((string)$_SESSION["ONLINE_ADMISSION_APPLICATION_ID"]) === ""){
    oa_letter_redirect("Log in with your token first before downloading your admission letter.");
}

$application = online_admission_get_application_by_id($con, (string)$_SESSION["ONLINE_ADMISSION_APPLICATION_ID"]);
if(!$application){
    oa_letter_redirect("We could not find that admission record anymore. Please log in again.");
}

$postedStudent = online_admission_get_posted_student_by_id($con, (string)$application["branchid"], (string)$application["postingid"]);
if(!$postedStudent){
    oa_letter_redirect("The posted student record linked to this admission form is no longer available.");
}

$paymentSetting = online_admission_get_payment_setting($con, (string)$application["branchid"]);
$paymentEnabled = (int)$paymentSetting["enabled"] === 1 && (float)$paymentSetting["feeamount"] > 0;
$successfulPayment = online_admission_get_successful_payment_by_application($con, (string)$application["applicationid"]);
if(!online_admission_documents_unlocked($application, $successfulPayment, $paymentEnabled ? 1 : 0)){
    oa_letter_redirect("This admission letter is only available after you complete the required admission steps.");
}

$school = array(
    "name" => "School Management System",
    "address" => "",
    "location" => "",
    "telephone1" => "",
    "telephone2" => "",
    "logo" => ""
);
$branchIdEsc = mysqli_real_escape_string($con, (string)$application["branchid"]);
$schoolRes = mysqli_query($con, "SELECT cm.fullname, br.address, br.location, br.telephone1, br.telephone2, cm.logo AS company_logo, br.logo AS branch_logo
    FROM tblbranch br
    INNER JOIN tblcompany cm ON br.companyid=cm.companyid
    WHERE br.branchid='$branchIdEsc'
    LIMIT 1");
if($schoolRes && $schoolRow = mysqli_fetch_array($schoolRes, MYSQLI_ASSOC)){
    $school["name"] = trim((string)$schoolRow["fullname"]) !== "" ? trim((string)$schoolRow["fullname"]) : $school["name"];
    $school["address"] = trim((string)$schoolRow["address"]);
    $school["location"] = trim((string)$schoolRow["location"]);
    $school["telephone1"] = trim((string)$schoolRow["telephone1"]);
    $school["telephone2"] = trim((string)$schoolRow["telephone2"]);
    $logoFile = trim((string)$schoolRow["company_logo"]);
    if($logoFile === ""){
        $logoFile = trim((string)$schoolRow["branch_logo"]);
    }
    if($logoFile !== ""){
        foreach(array(
            "images/logo/".$logoFile,
            "logo/".$logoFile,
            $logoFile
        ) as $candidate){
            $fullPath = __DIR__.DIRECTORY_SEPARATOR.str_replace(array("/", "\\"), DIRECTORY_SEPARATOR, $candidate);
            if(file_exists($fullPath)){
                $school["logo"] = $fullPath;
                break;
            }
        }
    }
}

$candidateName = trim(online_admission_candidate_name($application));
if($candidateName === ""){
    $candidateName = trim((string)$postedStudent["firstname"]." ".(string)$postedStudent["othernames"]." ".(string)$postedStudent["surname"]);
}
$programme = trim((string)$postedStudent["offeredprogram"]) !== "" ? trim((string)$postedStudent["offeredprogram"]) : "To be confirmed";
$className = trim((string)$postedStudent["offeredclass"]) !== "" ? trim((string)$postedStudent["offeredclass"]) : "To be assigned";
$residence = trim((string)$application["residencetype"]) !== "" ? trim((string)$application["residencetype"]) : trim((string)$postedStudent["residentialstatus"]);
if($residence === ""){
    $residence = "To be confirmed";
}
$assignedHouse = online_admission_application_assigned_house($con, $application);
$assignedHouseName = ($assignedHouse && trim((string)$assignedHouse["housename"]) !== "") ? trim((string)$assignedHouse["housename"]) : "To be announced";
$admissionYear = trim((string)$postedStudent["admissionyear"]);
$token = trim((string)$application["verificationtoken"]);
$submittedAt = trim((string)$application["submittedat"]);
$submittedText = $submittedAt !== "" ? date("d M Y", strtotime($submittedAt)) : date("d M Y");

require_once("fpdf181/fpdf.php");

class OnlineAdmissionLetterPdf extends FPDF {
    function bodyText($text){
        $this->SetFont('Arial', '', 11);
        $this->SetTextColor(45, 58, 74);
        $this->MultiCell(0, 7, $text, 0, 'L');
        $this->Ln(1);
    }
}

$pdf = new OnlineAdmissionLetterPdf("P", "mm", "A4");
$pdf->SetMargins(18, 14, 18);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 16);

if($school["logo"] !== ""){
    $ext = strtolower(pathinfo($school["logo"], PATHINFO_EXTENSION));
    if(in_array($ext, array("jpg", "jpeg", "png"), true)){
        $pdf->Image($school["logo"], 18, 14, 22);
    }
}

$pdf->SetFont('Arial', 'B', 17);
$pdf->SetTextColor(17, 58, 72);
$pdf->Cell(0, 8, $school["name"], 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(92, 108, 124);
$addressLine = trim($school["address"].($school["location"] !== "" ? ", ".$school["location"] : ""));
if($addressLine !== ""){
    $pdf->Cell(0, 6, $addressLine, 0, 1, 'C');
}
$phoneLine = trim($school["telephone1"].($school["telephone2"] !== "" ? "  ".$school["telephone2"] : ""));
if($phoneLine !== ""){
    $pdf->Cell(0, 6, "Tel: ".$phoneLine, 0, 1, 'C');
}

$pdf->Ln(6);
$pdf->SetDrawColor(212, 223, 233);
$pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
$pdf->Ln(6);

$pdf->SetFont('Arial', 'B', 15);
$pdf->SetTextColor(18, 48, 70);
$pdf->Cell(0, 8, "Admission Letter", 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(90, 107, 123);
$pdf->Cell(0, 6, "Generated on ".date("d M Y, g:i a"), 0, 1, 'C');

$pdf->Ln(8);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(45, 58, 74);
$pdf->Cell(0, 7, "Date: ".$submittedText, 0, 1, 'L');
$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 7, $candidateName, 0, 1, 'L');
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, "BECE Index Number: ".trim((string)$postedStudent["beceindexnumber"]), 0, 1, 'L');
$pdf->Cell(0, 7, "Admission Year: ".($admissionYear !== "" ? $admissionYear : "Current Year"), 0, 1, 'L');

$pdf->Ln(6);
$pdf->bodyText("Dear ".$candidateName.",");
$pdf->bodyText("Congratulations. This letter confirms your admission processing with ".$school["name"]." for the ".$admissionYear." academic year through the online admission portal.");
$pdf->bodyText("You have been considered for ".$programme." with ".$residence." status. Your reporting class is currently recorded as ".$className.".");
$pdf->bodyText("Please keep this letter together with your online admission summary and any other school documents made available on your portal. You may be asked to present them during reporting or further admission checks.");

$pdf->Ln(3);
$pdf->SetFillColor(240, 246, 251);
$pdf->SetDrawColor(213, 224, 234);
$pdf->SetTextColor(23, 49, 75);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 9, "Admission Details", 1, 1, 'L', true);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(55, 8, "Programme", 1, 0, 'L');
$pdf->Cell(0, 8, $programme, 1, 1, 'L');
$pdf->Cell(55, 8, "Class", 1, 0, 'L');
$pdf->Cell(0, 8, $className, 1, 1, 'L');
$pdf->Cell(55, 8, "Residence", 1, 0, 'L');
$pdf->Cell(0, 8, $residence, 1, 1, 'L');
$pdf->Cell(55, 8, "Assigned House", 1, 0, 'L');
$pdf->Cell(0, 8, $assignedHouseName, 1, 1, 'L');
$pdf->Cell(55, 8, "Application Status", 1, 0, 'L');
$pdf->Cell(0, 8, online_admission_status_label($application["status"]), 1, 1, 'L');
$pdf->Cell(55, 8, "Verification Token", 1, 0, 'L');
$pdf->Cell(0, 8, ($token !== "" ? $token : "Not available"), 1, 1, 'L');

$pdf->Ln(7);
$pdf->bodyText("We look forward to welcoming you. For support, contact the school using the details on the portal or the help options provided on the admission page.");

$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, "Admissions Office", 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, $school["name"], 0, 1, 'L');

$filenameSlug = preg_replace('/[^A-Za-z0-9]+/', '-', $candidateName);
$filenameSlug = trim((string)$filenameSlug, '-');
if($filenameSlug === ""){
    $filenameSlug = "admission-letter";
}
$pdf->Output("D", strtolower($filenameSlug)."-admission-letter.pdf");
exit();
?>
