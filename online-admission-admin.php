<?php
session_start();
include("dbstring.php");
include("check-login.php");
include_once("company.php");
include_once("online-admission-utils.php");
include_once("house-master-utils.php");
ensure_online_admission_tables($con);
ensure_house_tables($con);

if(!online_admission_is_admin()){
    header("location:".online_admission_landing_page());
    exit();
}

function aa_esc($value){ return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8"); }
function aa_alert($type, $message){
    $class = "rs-alert rs-alert--info";
    if($type === "success"){ $class = "rs-alert rs-alert--success"; }
    elseif($type === "error"){ $class = "rs-alert rs-alert--error"; }
    elseif($type === "warning"){ $class = "rs-alert rs-alert--warning"; }
    return "<div class=\"$class\">".aa_esc($message)."</div>";
}
function aa_date($value, $format){ $time = strtotime((string)$value); return $time ? date($format, $time) : ""; }
function aa_status_class($status){
    $status = strtolower(trim((string)$status));
    if($status === "reviewed"){ return "aa-status aa-status--success"; }
    if($status === "needs_attention"){ return "aa-status aa-status--warning"; }
    if($status === "submitted"){ return "aa-status aa-status--info"; }
    return "aa-status aa-status--neutral";
}
function aa_payment_status_class($status){
    $status = strtolower(trim((string)$status));
    if($status === "success"){ return "aa-status aa-status--success"; }
    if($status === "pending" || $status === "initialized"){ return "aa-status aa-status--info"; }
    if($status === "failed" || $status === "abandoned"){ return "aa-status aa-status--warning"; }
    return "aa-status aa-status--neutral";
}
function aa_help_status_class($status){
    $status = strtolower(trim((string)$status));
    if($status === "resolved"){ return "aa-status aa-status--success"; }
    if($status === "contacted"){ return "aa-status aa-status--info"; }
    return "aa-status aa-status--warning";
}
function aa_house_status_class($status){
    $status = strtolower(trim((string)$status));
    if($status === "active"){ return "aa-status aa-status--success"; }
    if($status === "inactive"){ return "aa-status aa-status--warning"; }
    return "aa-status aa-status--neutral";
}
function aa_money($amount, $currency){
    $currency = strtoupper(trim((string)$currency));
    if($currency === ""){
        $currency = "GHS";
    }
    return $currency." ".number_format((float)$amount, 2);
}
function aa_file_slug($value){
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string)$value, '-');
    return $value !== "" ? $value : "export";
}
function aa_output_excel_table($filename, $title, $headers, $rows){
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"".$filename."\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    echo "<html><head><meta charset=\"utf-8\"></head><body>";
    echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"6\">";
    echo "<tr><th colspan=\"".count($headers)."\" style=\"background:#17314b;color:#ffffff;font-size:16px;\">".aa_esc($title)."</th></tr>";
    echo "<tr>";
    foreach($headers as $header){
        echo "<th style=\"background:#edf4fa;color:#17314b;\">".aa_esc($header)."</th>";
    }
    echo "</tr>";
    if(count($rows) > 0){
        foreach($rows as $row){
            echo "<tr>";
            foreach($row as $value){
                echo "<td style=\"mso-number-format:'\\@';\">".aa_esc($value)."</td>";
            }
            echo "</tr>";
        }
    }else{
        echo "<tr><td colspan=\"".count($headers)."\">No records available.</td></tr>";
    }
    echo "</table></body></html>";
    exit();
}
function aa_output_print_table($title, $headers, $rows, $companyName, $branchName){
    echo "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>".aa_esc($title)."</title>";
    echo "<style>
        body{font-family:Arial,sans-serif;color:#17314b;margin:24px;}
        .print-wrap{max-width:1100px;margin:0 auto;}
        h1{margin:0 0 6px;font-size:24px;}
        p{margin:4px 0 0;color:#5f768f;}
        .print-actions{margin:18px 0 20px;}
        .print-actions button{padding:10px 16px;border:0;border-radius:10px;background:#17314b;color:#fff;font-weight:700;cursor:pointer;}
        table{width:100%;border-collapse:collapse;margin-top:16px;}
        th,td{border:1px solid #d9e3ed;padding:10px 12px;text-align:left;vertical-align:top;}
        th{background:#edf4fa;color:#17314b;font-size:12px;text-transform:uppercase;letter-spacing:.08em;}
        @media print {.print-actions{display:none;} body{margin:0.5in;}}
    </style>";
    echo "</head><body><div class=\"print-wrap\">";
    echo "<h1>".aa_esc($title)."</h1>";
    echo "<p>".aa_esc($companyName)." - ".aa_esc($branchName)."</p>";
    echo "<p>Printed on ".aa_esc(date("d M Y, g:i a"))."</p>";
    echo "<div class=\"print-actions\"><button type=\"button\" onclick=\"window.print()\">Print</button></div>";
    echo "<table><thead><tr>";
    foreach($headers as $header){
        echo "<th>".aa_esc($header)."</th>";
    }
    echo "</tr></thead><tbody>";
    if(count($rows) > 0){
        foreach($rows as $row){
            echo "<tr>";
            foreach($row as $value){
                echo "<td>".aa_esc($value)."</td>";
            }
            echo "</tr>";
        }
    }else{
        echo "<tr><td colspan=\"".count($headers)."\">No records available.</td></tr>";
    }
    echo "</tbody></table></div><script>window.onload=function(){window.print();};</script></body></html>";
    exit();
}
function aa_positive_page($value){
    $page = (int)$value;
    return $page > 0 ? $page : 1;
}
function aa_cycle_status($summary){
    if((int)$summary["posted_active"] > 0){
        return array("label" => "Active", "class" => "aa-status aa-status--success");
    }
    if((int)$summary["posted_total"] > 0){
        return array("label" => "Ready To Clear", "class" => "aa-status aa-status--warning");
    }
    return array("label" => "No Posted List", "class" => "aa-status aa-status--warning");
}
function aa_year_posted_students($con, $branchId, $admissionYear){
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $yearEsc = mysqli_real_escape_string($con, trim((string)$admissionYear));
    $rows = array();
    $res = mysqli_query($con, "SELECT *
        FROM tbladmissionpostedstudent
        WHERE branchid='$branchIdEsc'
          AND admissionyear='$yearEsc'
        ORDER BY datetimeentry DESC");
    if($res){ while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){ $rows[] = $row; } }
    return $rows;
}
function aa_year_applications($con, $branchId, $admissionYear){
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $yearEsc = mysqli_real_escape_string($con, trim((string)$admissionYear));
    $rows = array();
    $res = mysqli_query($con, "SELECT *
        FROM tblonlineadmissionapplication
        WHERE branchid='$branchIdEsc'
          AND admissionyear='$yearEsc'
        ORDER BY updatedat DESC");
    if($res){ while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){ $rows[] = $row; } }
    return $rows;
}
function aa_year_payments($con, $branchId, $admissionYear){
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $yearEsc = mysqli_real_escape_string($con, trim((string)$admissionYear));
    $rows = array();
    $res = mysqli_query($con, "SELECT pay.*, app.firstname, app.surname, app.othernames
        FROM tblonlineadmissionpayment pay
        LEFT JOIN tblonlineadmissionapplication app ON app.applicationid=pay.applicationid
        WHERE pay.branchid='$branchIdEsc'
          AND pay.admissionyear='$yearEsc'
        ORDER BY pay.createdat DESC");
    if($res){ while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){ $rows[] = $row; } }
    return $rows;
}
function aa_year_help_requests($con, $branchId, $admissionYear){
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $yearEsc = mysqli_real_escape_string($con, trim((string)$admissionYear));
    $rows = array();
    $res = mysqli_query($con, "SELECT *
        FROM tblonlineadmissionhelprequest
        WHERE branchid='$branchIdEsc'
          AND admissionyear='$yearEsc'
        ORDER BY requestedat DESC");
    if($res){ while($row = mysqli_fetch_array($res, MYSQLI_ASSOC)){ $rows[] = $row; } }
    return $rows;
}
function aa_csv_string($headers, $rows){
    $stream = fopen("php://temp", "r+");
    if(!$stream){
        return "";
    }
    fputcsv($stream, $headers);
    foreach($rows as $row){
        fputcsv($stream, $row);
    }
    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);
    return (string)$content;
}
function aa_output_year_images_zip($con, $branchId, $admissionYear, $branchName){
    if(!class_exists('ZipArchive')){
        header("Content-Type: text/plain; charset=UTF-8");
        echo "ZIP downloads are not available because the ZipArchive extension is not enabled on this server.";
        exit();
    }
    $yearImages = array();
    foreach(aa_year_applications($con, $branchId, $admissionYear) as $application){
        if(trim((string)(isset($application["filename"]) ? $application["filename"] : "")) !== ""){
            $yearImages[] = $application;
        }
    }

    $zipPath = tempnam(sys_get_temp_dir(), "admimg");
    if($zipPath === false){
        header("Content-Type: text/plain; charset=UTF-8");
        echo "A temporary ZIP file could not be created on this server.";
        exit();
    }

    $zip = new ZipArchive();
    if($zip->open($zipPath, ZipArchive::OVERWRITE) !== true){
        @unlink($zipPath);
        header("Content-Type: text/plain; charset=UTF-8");
        echo "The admission image ZIP file could not be prepared.";
        exit();
    }

    $manifestRows = array();
    $savedCount = 0;
    foreach($yearImages as $application){
        $filename = trim((string)$application["filename"]);
        $source = __DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR.$filename;
        if(!is_file($source)){
            continue;
        }
        $studentName = online_admission_backup_image_student_name($application);
        $savedFile = online_admission_backup_image_copy_name($application);
        if($zip->addFile($source, "images/".$savedFile)){
            $savedCount++;
            $manifestRows[] = array(
                (string)$application["applicationid"],
                (string)$application["beceindexnumber"],
                $studentName,
                $filename,
                $savedFile
            );
        }
    }

    $zip->addFromString("image-manifest.csv", aa_csv_string(
        array("applicationid", "beceindexnumber", "studentname", "originalfile", "savedfile"),
        $manifestRows
    ));
    $zip->addFromString("backup-summary.txt", "Admission Year: ".$admissionYear.PHP_EOL
        ."Downloaded At: ".date("Y-m-d H:i:s").PHP_EOL
        ."Saved Images: ".$savedCount.PHP_EOL);
    $zip->close();

    $downloadName = aa_file_slug($branchName)."-".$admissionYear."-admission-images.zip";
    if(function_exists("ob_get_level")){
        while(ob_get_level() > 0){
            ob_end_clean();
        }
    }
    header("Content-Type: application/zip");
    header("Content-Disposition: attachment; filename=\"".$downloadName."\"");
    header("Content-Length: ".filesize($zipPath));
    header("Pragma: no-cache");
    header("Expires: 0");
    readfile($zipPath);
    @unlink($zipPath);
    exit();
}
function aa_admin_url($overrides = array(), $anchor = ""){
    $params = $_GET;
    unset($params["export"], $params["print"]);
    foreach($overrides as $key => $value){
        if($value === null || $value === ""){
            unset($params[$key]);
        }else{
            $params[$key] = $value;
        }
    }
    $query = http_build_query($params);
    $url = "online-admission-admin.php";
    if($query !== ""){
        $url .= "?".$query;
    }
    if($anchor !== ""){
        $url .= $anchor;
    }
    return $url;
}
function aa_read_csv_rows($tmpName){
    $rows = array();
    if(($h = fopen($tmpName, "r")) !== false){
        $firstLine = fgets($h);
        $delimiter = ",";
        if($firstLine !== false){
            $lineSample = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
            $delims = array("," => substr_count($lineSample, ","), ";" => substr_count($lineSample, ";"), "\t" => substr_count($lineSample, "\t"), "|" => substr_count($lineSample, "|"));
            arsort($delims);
            $best = key($delims);
            if($best !== null && $delims[$best] > 0){
                $delimiter = $best;
            }
        }
        rewind($h);
        while(($data = fgetcsv($h, 0, $delimiter)) !== false){
            if(isset($data[0])){
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$data[0]);
            }
            $rows[] = $data;
        }
        fclose($h);
    }
    return $rows;
}
function aa_read_xlsx_rows($tmpName){
    require_once "simplexlsx.class.php";
    $oldLevel = error_reporting();
    error_reporting($oldLevel & ~E_DEPRECATED & ~E_NOTICE);
    $xlsx = new SimpleXLSX($tmpName);
    error_reporting($oldLevel);
    if(!$xlsx->success()){
        return array("error" => $xlsx->error(), "rows" => array());
    }
    $sheetNames = $xlsx->sheetNames();
    $firstKey = array_key_first($sheetNames);
    if($firstKey === null){
        return array("error" => "No worksheet was found in the uploaded Excel file.", "rows" => array());
    }
    $rows = $xlsx->rows($firstKey);
    if($rows === false){
        return array("error" => "The first worksheet in the uploaded Excel file could not be read.", "rows" => array());
    }
    return array("error" => "", "rows" => $rows);
}
function aa_normalize_header($value){
    $value = strtolower(trim((string)$value));
    return preg_replace('/[^a-z0-9]+/', '', $value);
}
function aa_posted_student_alias_map(){
    return array(
        "beceindexnumber" => array("beceindexnumber", "beceindex", "indexnumber", "indexno", "beceno", "bece"),
        "birthdate" => array("birthdate", "dateofbirth", "dob"),
        "fullname" => array("fullname", "studentname", "name", "candidatename"),
        "firstname" => array("firstname", "first", "givenname"),
        "surname" => array("surname", "lastname", "familyname"),
        "othernames" => array("othernames", "othername", "middlename", "middlenames"),
        "gender" => array("gender", "sex"),
        "admissionyear" => array("admissionyear", "year", "academicyear", "examyear"),
        "offeredprogram" => array("offeredprogram", "offeredprogramme", "program", "programme", "placedprogram"),
        "offeredclass" => array("offeredclass", "class", "assignedclass"),
        "residentialstatus" => array("residentialstatus", "residencestatus", "residence", "boardingstatus", "placedresidencetype"),
        "mobile" => array("mobile", "mobilenumber", "contactnumber", "phone", "telephone", "phonenumber")
    );
}
function aa_split_fullname($value){
    $value = trim(preg_replace('/\s+/', ' ', (string)$value));
    $parts = preg_split('/\s+/', $value);
    $parts = array_values(array_filter($parts, function($part){ return trim((string)$part) !== ""; }));
    if(empty($parts)){
        return array("surname" => "", "firstname" => "", "othernames" => "");
    }
    if(count($parts) === 1){
        return array("surname" => $parts[0], "firstname" => $parts[0], "othernames" => "");
    }
    if(count($parts) === 2){
        return array("surname" => $parts[0], "firstname" => $parts[1], "othernames" => "");
    }
    $surname = array_shift($parts);
    $firstname = array_shift($parts);
    return array(
        "surname" => $surname,
        "firstname" => $firstname,
        "othernames" => implode(" ", $parts)
    );
}
function aa_detect_header_map($row){
    $aliases = aa_posted_student_alias_map();
    $map = array();
    foreach((array)$row as $index => $value){
        $normalized = aa_normalize_header($value);
        if($normalized === ""){
            continue;
        }
        foreach($aliases as $field => $validHeaders){
            if(in_array($normalized, $validHeaders, true)){
                $map[$field] = $index;
                break;
            }
        }
    }
    if(count($map) < 3){
        return array();
    }
    return $map;
}
function aa_row_is_blank($row){
    foreach((array)$row as $value){
        if(trim((string)$value) !== ""){
            return false;
        }
    }
    return true;
}
function aa_extract_posted_student_row($row, $headerMap, $defaultYear){
    $fields = array("beceindexnumber", "birthdate", "fullname", "firstname", "surname", "othernames", "gender", "admissionyear", "offeredprogram", "offeredclass", "residentialstatus", "mobile");
    $data = array_fill_keys($fields, "");
    if(!empty($headerMap)){
        foreach($headerMap as $field => $index){
            $data[$field] = isset($row[$index]) ? trim((string)$row[$index]) : "";
        }
    }else{
        foreach($fields as $index => $field){
            $data[$field] = isset($row[$index]) ? trim((string)$row[$index]) : "";
        }
    }
    if($data["fullname"] !== "" && ($data["firstname"] === "" || $data["surname"] === "")){
        $nameParts = aa_split_fullname($data["fullname"]);
        if($data["surname"] === ""){
            $data["surname"] = $nameParts["surname"];
        }
        if($data["firstname"] === ""){
            $data["firstname"] = $nameParts["firstname"];
        }
        if($data["othernames"] === ""){
            $data["othernames"] = $nameParts["othernames"];
        }
    }
    $data["beceindexnumber"] = online_admission_normalize_bece($data["beceindexnumber"]);
    $data["birthdate"] = online_admission_normalize_date($data["birthdate"]);
    if($data["birthdate"] === false){
        $data["birthdate"] = "";
    }
    if($data["admissionyear"] === ""){
        $data["admissionyear"] = $defaultYear;
    }
    unset($data["fullname"]);
    return $data;
}
function aa_validate_posted_student_row($row){
    $errors = array();
    if($row["beceindexnumber"] === ""){ $errors[] = "missing BECE index"; }
    if($row["birthdate"] === ""){ $errors[] = "missing or invalid date of birth"; }
    if($row["firstname"] === ""){ $errors[] = "missing first name"; }
    if($row["surname"] === ""){ $errors[] = "missing surname"; }
    if($row["admissionyear"] === ""){ $errors[] = "missing admission year"; }
    return $errors;
}
function aa_fetch_application_bundle($con, $branchId, $applicationId){
    $branchIdEsc = mysqli_real_escape_string($con, (string)$branchId);
    $applicationIdEsc = mysqli_real_escape_string($con, (string)$applicationId);
    $res = mysqli_query($con, "SELECT app.*,
        post.offeredprogram,
        post.offeredclass,
        post.residentialstatus AS posted_residentialstatus
        FROM tblonlineadmissionapplication app
        LEFT JOIN tbladmissionpostedstudent post ON post.postingid=app.postingid
        WHERE app.applicationid='$applicationIdEsc' AND app.branchid='$branchIdEsc'
        LIMIT 1");
    if($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))){
        return $row;
    }
    return null;
}
function aa_application_form_defaults($application){
    return array(
        "firstname" => (string)$application["firstname"],
        "surname" => (string)$application["surname"],
        "othernames" => (string)$application["othernames"],
        "gender" => (string)$application["gender"],
        "birthdate" => (string)$application["birthdate"],
        "mobile" => (string)$application["mobile"],
        "email" => (string)$application["email"],
        "residencetype" => (string)$application["residencetype"],
        "religion" => (string)$application["religion"],
        "hometown" => (string)$application["hometown"],
        "postaladdress" => (string)$application["postaladdress"],
        "homeaddress" => (string)$application["homeaddress"],
        "guardianname" => (string)$application["guardianname"],
        "guardianrelationship" => (string)$application["guardianrelationship"],
        "guardiancontact" => (string)$application["guardiancontact"],
        "medicalnotes" => (string)$application["medicalnotes"],
        "studentnote" => (string)$application["studentnote"],
        "status" => (string)$application["status"],
        "reviewnote" => (string)$application["reviewnote"]
    );
}

$branchId = isset($_SESSION["BRANCHID"]) ? (string)$_SESSION["BRANCHID"] : "";
$branchIdEsc = mysqli_real_escape_string($con, $branchId);
$branchName = "Current Branch";
$branchRes = mysqli_query($con, "SELECT location FROM tblbranch WHERE branchid='$branchIdEsc' LIMIT 1");
if($branchRes && ($row = mysqli_fetch_array($branchRes, MYSQLI_ASSOC)) && trim((string)$row["location"]) !== ""){
    $branchName = trim((string)$row["location"]);
}
$companyName = isset($_CompanyName) && trim((string)$_CompanyName) !== "" ? trim((string)$_CompanyName) : "School Management System";

$flashMessage = isset($_SESSION["ONLINE_ADMISSION_ADMIN_MESSAGE"]) ? (string)$_SESSION["ONLINE_ADMISSION_ADMIN_MESSAGE"] : "";
unset($_SESSION["ONLINE_ADMISSION_ADMIN_MESSAGE"]);
$paymentSetting = online_admission_get_payment_setting($con, $branchId);
$paystackConfig = online_admission_paystack_config();
$paystackReady = online_admission_paystack_is_ready($paystackConfig);

$selectedApplicationId = trim((string)(isset($_POST["edit_application"]) ? $_POST["edit_application"] : (isset($_GET["edit_application"]) ? $_GET["edit_application"] : "")));
$editableApplication = null;
$editableApplicationForm = null;
$editablePayment = null;
$editableAssignedHouse = null;
if($selectedApplicationId !== ""){
    $editableApplication = aa_fetch_application_bundle($con, $branchId, $selectedApplicationId);
    if($editableApplication){
        $editableAssignedHouse = online_admission_assign_house_for_application($con, $editableApplication);
        $editableApplication = aa_fetch_application_bundle($con, $branchId, $selectedApplicationId);
        $editableApplicationForm = aa_application_form_defaults($editableApplication);
        $editablePayment = online_admission_get_latest_payment_by_application($con, $editableApplication["applicationid"]);
    }
}

$postedForm = array(
    "beceindexnumber" => "",
    "birthdate" => "",
    "firstname" => "",
    "surname" => "",
    "othernames" => "",
    "gender" => "",
    "admissionyear" => date("Y"),
    "offeredprogram" => "",
    "offeredclass" => "",
    "residentialstatus" => "",
    "mobile" => ""
);
$houseForm = array(
    "housename" => "",
    "description" => "",
    "housegender" => "Male",
    "houseresidencetype" => "Boarding",
    "autoassignenabled" => "1"
);

if(isset($_POST["save_admission_house"])){
    foreach($houseForm as $key => $value){
        if($key === "autoassignenabled"){
            $houseForm[$key] = isset($_POST[$key]) ? "1" : "0";
        }else{
            $houseForm[$key] = trim((string)(isset($_POST[$key]) ? $_POST[$key] : ""));
        }
    }
    $houseForm["housename"] = preg_replace('/\s+/', ' ', $houseForm["housename"]);
    $houseForm["description"] = preg_replace('/\s+/', ' ', $houseForm["description"]);
    $houseForm["housegender"] = house_master_normalize_gender_label($houseForm["housegender"]);
    $houseForm["houseresidencetype"] = house_master_normalize_residence_label($houseForm["houseresidencetype"]);

    $errors = array();
    if($houseForm["housename"] === ""){
        $errors[] = "House name is required.";
    }
    if($houseForm["housegender"] === ""){
        $errors[] = "Select the house gender.";
    }
    if($houseForm["houseresidencetype"] === ""){
        $errors[] = "Select the house residence type.";
    }

    if(empty($errors)){
        $duplicateStmt = mysqli_prepare($con, "SELECT houseid FROM tblhouse WHERE LOWER(TRIM(housename)) = LOWER(TRIM(?)) LIMIT 1");
        if($duplicateStmt){
            mysqli_stmt_bind_param($duplicateStmt, "s", $houseForm["housename"]);
            mysqli_stmt_execute($duplicateStmt);
            mysqli_stmt_store_result($duplicateStmt);
            if(mysqli_stmt_num_rows($duplicateStmt) > 0){
                $errors[] = "That house name already exists.";
            }
            mysqli_stmt_close($duplicateStmt);
        }
    }

    if(empty($errors)){
        $houseId = online_admission_generate_id("HOUSE_");
        $recordedBy = isset($_SESSION["USERID"]) ? (string)$_SESSION["USERID"] : "";
        $stmt = mysqli_prepare($con, "INSERT INTO tblhouse(
            houseid, housename, description, housegender, houseresidencetype, autoassignenabled, status, datetimeentry, recordedby
        ) VALUES(
            ?, ?, ?, ?, ?, ?, 'active', NOW(), ?
        )");
        if($stmt){
            $autoAssign = (int)$houseForm["autoassignenabled"];
            mysqli_stmt_bind_param($stmt, "sssssis", $houseId, $houseForm["housename"], $houseForm["description"], $houseForm["housegender"], $houseForm["houseresidencetype"], $autoAssign, $recordedBy);
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_close($stmt);
                $_SESSION["ONLINE_ADMISSION_ADMIN_MESSAGE"] = aa_alert("success", "\"".$houseForm["housename"]."\" created successfully.");
                header("location:online-admission-admin.php#admission-houses");
                exit();
            }
            $flashMessage = mysqli_stmt_errno($stmt) === 1062
                ? aa_alert("warning", "That house name already exists.")
                : aa_alert("error", "The house could not be created right now.");
            mysqli_stmt_close($stmt);
        }else{
            $flashMessage = aa_alert("error", "The house form could not be prepared right now.");
        }
    }else{
        $flashMessage = aa_alert("warning", implode(" ", $errors));
    }
}

if(isset($_POST["save_house_profile"])){
    $houseId = trim((string)(isset($_POST["houseid"]) ? $_POST["houseid"] : ""));
    $houseGender = house_master_normalize_gender_label(isset($_POST["housegender"]) ? $_POST["housegender"] : "");
    $houseResidence = house_master_normalize_residence_label(isset($_POST["houseresidencetype"]) ? $_POST["houseresidencetype"] : "");
    $autoAssign = isset($_POST["autoassignenabled"]) ? 1 : 0;
    if($houseId === ""){
        $flashMessage = aa_alert("warning", "Select a valid house first.");
    }elseif($houseGender === "" || $houseResidence === ""){
        $flashMessage = aa_alert("warning", "Choose the gender and residence type for this house.");
    }else{
        $houseIdEsc = mysqli_real_escape_string($con, $houseId);
        $genderEsc = mysqli_real_escape_string($con, $houseGender);
        $residenceEsc = mysqli_real_escape_string($con, $houseResidence);
        $updated = mysqli_query($con, "UPDATE tblhouse SET
            housegender='$genderEsc',
            houseresidencetype='$residenceEsc',
            autoassignenabled=".(int)$autoAssign."
            WHERE houseid='$houseIdEsc'
            LIMIT 1");
        $_SESSION["ONLINE_ADMISSION_ADMIN_MESSAGE"] = $updated
            ? aa_alert("success", "House auto-assignment profile updated successfully.")
            : aa_alert("error", "The house profile could not be updated right now.");
        header("location:online-admission-admin.php#admission-houses");
        exit();
    }
}

if(isset($_POST["add_posted_student"])){
    foreach($postedForm as $key => $value){
        $postedForm[$key] = trim((string)(isset($_POST[$key]) ? $_POST[$key] : ""));
    }
    $postedForm["beceindexnumber"] = online_admission_normalize_bece($postedForm["beceindexnumber"]);
    $birthdate = online_admission_normalize_date($postedForm["birthdate"]);
    $errors = array();
    if($postedForm["beceindexnumber"] === ""){ $errors[] = "BECE index number is required."; }
    if($birthdate === false || $birthdate === ""){ $errors[] = "A valid date of birth is required."; }
    else{ $postedForm["birthdate"] = $birthdate; }
    if($postedForm["firstname"] === ""){ $errors[] = "First name is required."; }
    if($postedForm["surname"] === ""){ $errors[] = "Surname is required."; }
    if($postedForm["admissionyear"] === ""){ $errors[] = "Admission year is required."; }

    if(empty($errors)){
        $postingId = online_admission_generate_id("POST_");
        $stmt = mysqli_prepare($con, "INSERT INTO tbladmissionpostedstudent(
            postingid, beceindexnumber, birthdate, firstname, surname, othernames, gender,
            admissionyear, offeredprogram, offeredclass, residentialstatus, mobile,
            status, datetimeentry, recordedby, branchid
        ) VALUES(
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            'active', NOW(), ?, ?
        )");
        if($stmt){
            $recordedBy = isset($_SESSION["USERID"]) ? (string)$_SESSION["USERID"] : "";
            mysqli_stmt_bind_param(
                $stmt,
                str_repeat("s", 14),
                $postingId, $postedForm["beceindexnumber"], $postedForm["birthdate"], $postedForm["firstname"], $postedForm["surname"], $postedForm["othernames"], $postedForm["gender"],
                $postedForm["admissionyear"], $postedForm["offeredprogram"], $postedForm["offeredclass"], $postedForm["residentialstatus"], $postedForm["mobile"],
                $recordedBy, $branchId
            );
            if(mysqli_stmt_execute($stmt)){
                $_SESSION["ONLINE_ADMISSION_ADMIN_MESSAGE"] = aa_alert("success", "Posted student added successfully. The student can now verify on the public online admission page.");
                mysqli_stmt_close($stmt);
                header("location:online-admission-admin.php");
                exit();
            }
            $flashMessage = mysqli_stmt_errno($stmt) == 1062
                ? aa_alert("warning", "That BECE index number is already on the posted student list for this admission year.")
                : aa_alert("error", "The posted student could not be saved right now.");
            mysqli_stmt_close($stmt);
        }else{
            $flashMessage = aa_alert("error", "The posted student form could not be prepared right now.");
        }
    }else{
        $flashMessage = aa_alert("warning", implode(" ", $errors));
    }
}

if(isset($_GET["download_posted_template"])){
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"online_admission_posted_students_template.csv\"");
    $out = fopen("php://output", "w");
    fputcsv($out, array("beceindexnumber", "birthdate", "firstname", "surname", "othernames", "gender", "admissionyear", "offeredprogram", "offeredclass", "residentialstatus", "mobile"));
    fputcsv($out, array("1234567890", "2010-01-15", "Akosua", "Mensah", "Serwaa", "Female", date("Y"), "General Arts", "SHS 1A", "Boarding", "0240000000"));
    fclose($out);
    exit();
}

if(isset($_POST["upload_posted_students"])){
    $defaultYear = trim((string)(isset($_POST["upload_admissionyear"]) ? $_POST["upload_admissionyear"] : date("Y")));
    if($defaultYear === ""){
        $defaultYear = date("Y");
    }
    if(!isset($_FILES["posted_student_file"]) || !isset($_FILES["posted_student_file"]["error"]) || (int)$_FILES["posted_student_file"]["error"] === UPLOAD_ERR_NO_FILE){
        $flashMessage = aa_alert("warning", "Choose an Excel or CSV file to upload.");
    }elseif((int)$_FILES["posted_student_file"]["error"] !== UPLOAD_ERR_OK){
        $flashMessage = aa_alert("error", "The upload could not be completed right now.");
    }else{
        $originalName = (string)$_FILES["posted_student_file"]["name"];
        $tmpName = (string)$_FILES["posted_student_file"]["tmp_name"];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $rows = array();
        $loadError = "";

        if($extension === "csv"){
            $rows = aa_read_csv_rows($tmpName);
        }elseif($extension === "xlsx"){
            $xlsxResult = aa_read_xlsx_rows($tmpName);
            $rows = $xlsxResult["rows"];
            $loadError = (string)$xlsxResult["error"];
        }else{
            $loadError = "Only .xlsx and .csv files are supported.";
        }

        if($loadError !== ""){
            $flashMessage = aa_alert("error", $loadError);
        }elseif(empty($rows)){
            $flashMessage = aa_alert("warning", "The uploaded file does not contain any rows.");
        }else{
            $headerMap = aa_detect_header_map($rows[0]);
            if(!empty($headerMap)){
                array_shift($rows);
            }

            $insertedCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;
            $errorSamples = array();
            $recordedBy = isset($_SESSION["USERID"]) ? (string)$_SESSION["USERID"] : "";

            $stmtUpsert = mysqli_prepare($con, "INSERT INTO tbladmissionpostedstudent(
                postingid, beceindexnumber, birthdate, firstname, surname, othernames, gender,
                admissionyear, offeredprogram, offeredclass, residentialstatus, mobile,
                status, datetimeentry, recordedby, branchid
            ) VALUES(
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                'active', NOW(), ?, ?
            ) ON DUPLICATE KEY UPDATE
                birthdate=VALUES(birthdate),
                firstname=VALUES(firstname),
                surname=VALUES(surname),
                othernames=VALUES(othernames),
                gender=VALUES(gender),
                offeredprogram=VALUES(offeredprogram),
                offeredclass=VALUES(offeredclass),
                residentialstatus=VALUES(residentialstatus),
                mobile=VALUES(mobile),
                status='active',
                recordedby=VALUES(recordedby),
                datetimeentry=NOW()");

            if(!$stmtUpsert){
                $flashMessage = aa_alert("error", "The bulk upload could not be prepared right now.");
            }else{
                foreach($rows as $rowIndex => $row){
                    if(aa_row_is_blank($row)){
                        continue;
                    }

                    $student = aa_extract_posted_student_row($row, $headerMap, $defaultYear);
                    $errors = aa_validate_posted_student_row($student);
                    if(!empty($errors)){
                        $skippedCount++;
                        if(count($errorSamples) < 5){
                            $errorSamples[] = "Row ".($rowIndex + (empty($headerMap) ? 1 : 2)).": ".implode(", ", $errors).".";
                        }
                        continue;
                    }

                    $postingId = online_admission_generate_id("POST_");
                    mysqli_stmt_bind_param(
                        $stmtUpsert,
                        str_repeat("s", 14),
                        $postingId, $student["beceindexnumber"], $student["birthdate"], $student["firstname"], $student["surname"], $student["othernames"], $student["gender"],
                        $student["admissionyear"], $student["offeredprogram"], $student["offeredclass"], $student["residentialstatus"], $student["mobile"],
                        $recordedBy, $branchId
                    );
                    if(mysqli_stmt_execute($stmtUpsert)){
                        $affected = mysqli_stmt_affected_rows($stmtUpsert);
                        if($affected === 1){
                            $insertedCount++;
                        }elseif($affected >= 2){
                            $updatedCount++;
                        }else{
                            $updatedCount++;
                        }
                    }else{
                        $skippedCount++;
                        if(count($errorSamples) < 5){
                            $errorSamples[] = "Row ".($rowIndex + (empty($headerMap) ? 1 : 2)).": could not be saved.";
                        }
                    }
                }
                mysqli_stmt_close($stmtUpsert);

                $message = "Bulk upload completed. Added ".number_format($insertedCount)." student(s), updated ".number_format($updatedCount)." existing record(s), and skipped ".number_format($skippedCount)." row(s).";
                if(!empty($errorSamples)){
                    $message .= " ".implode(" ", $errorSamples);
                }
                $_SESSION["ONLINE_ADMISSION_ADMIN_MESSAGE"] = aa_alert($skippedCount > 0 ? "warning" : "success", $message);
                header("location:online-admission-admin.php");
                exit();
            }
        }
    }
}

if(isset($_POST["save_payment_settings"])){
    $paymentData = array(
        "portalenabled" => isset($_POST["portal_enabled"]) ? 1 : 0,
        "enabled" => isset($_POST["payment_enabled"]) ? 1 : 0,
        "feeamount" => isset($_POST["feeamount"]) ? trim((string)$_POST["feeamount"]) : "0",
        "currency" => isset($_POST["currency"]) ? trim((string)$_POST["currency"]) : "GHS",
        "payablestatus" => isset($_POST["payablestatus"]) ? trim((string)$_POST["payablestatus"]) : "reviewed",
        "note" => isset($_POST["payment_note"]) ? trim((string)$_POST["payment_note"]) : ""
    );

    $feeAmount = (float)$paymentData["feeamount"];
    if($feeAmount < 0){
        $feeAmount = 0;
    }
    $paymentData["feeamount"] = number_format($feeAmount, 2, ".", "");
    if($paymentData["currency"] === ""){
        $paymentData["currency"] = "GHS";
    }
    if(!in_array($paymentData["payablestatus"], array("verified", "submitted", "reviewed"), true)){
        $paymentData["payablestatus"] = "verified";
    }

    $updatedBy = isset($_SESSION["USERID"]) ? (string)$_SESSION["USERID"] : "";
    $saved = online_admission_save_payment_setting($con, $branchId, $paymentData, $updatedBy);
    $_SESSION["ONLINE_ADMISSION_ADMIN_MESSAGE"] = $saved
        ? aa_alert("success", "Online admission payment settings updated successfully.")
        : aa_alert("error", "The online admission payment settings could not be saved.");
    header("location:online-admission-admin.php#payment-settings");
    exit();
}

if(isset($_POST["save_admission_documents"])){
    $documentYear = trim((string)(isset($_POST["document_year"]) ? $_POST["document_year"] : ""));
    $documentTitle = trim((string)(isset($_POST["document_title"]) ? $_POST["document_title"] : ""));
    $documentGroup = strtolower(trim((string)(isset($_POST["document_group"]) ? $_POST["document_group"] : "general")));
    $documentTargetGender = trim((string)(isset($_POST["document_target_gender"]) ? $_POST["document_target_gender"] : ""));
    $documentTargetResidence = trim((string)(isset($_POST["document_target_residencetype"]) ? $_POST["document_target_residencetype"] : ""));
    $uploadedBy = isset($_SESSION["USERID"]) ? (string)$_SESSION["USERID"] : "";
    $errors = array();

    if($documentYear === ""){
        $errors[] = "Enter the admission year for these downloadable documents.";
    }
    if($documentTitle === ""){
        $errors[] = "Enter the document title students should see.";
    }
    if(!isset($_FILES["document_file"]) || !isset($_FILES["document_file"]["error"]) || (int)$_FILES["document_file"]["error"] === UPLOAD_ERR_NO_FILE){
        $errors[] = "Choose the document file to upload.";
    }

    $savedDocument = false;
    if(empty($errors)){
        $errorMessage = "";
        $savedDocument = online_admission_save_document(
            $con,
            $branchId,
            $documentYear,
            $documentTitle,
            $_FILES["document_file"],
            $uploadedBy,
            $errorMessage,
            array(
                "documentgroup" => $documentGroup,
                "targetgender" => $documentTargetGender,
                "targetresidencetype" => $documentTargetResidence
            )
        );
        if(!$savedDocument){
            $errors[] = ($errorMessage !== "" ? $errorMessage : "The admission document could not be uploaded right now.");
        }
    }

    $_SESSION["ONLINE_ADMISSION_ADMIN_MESSAGE"] = empty($errors)
        ? aa_alert(
            "success",
            "\"".$documentTitle."\" uploaded successfully.".(
                $savedDocument && online_admission_document_group($savedDocument) === "prospectus"
                    ? " Assigned to ".strtolower(online_admission_document_target_summary($savedDocument))."."
                    : ""
            )
        )
        : aa_alert("error", implode(" ", $errors));
    header("location:".aa_admin_url(array("document_year" => $documentYear !== "" ? $documentYear : null), "#admission-documents"));
    exit();
}

if(isset($_POST["delete_admission_document"])){
    $documentYear = trim((string)(isset($_POST["document_year"]) ? $_POST["document_year"] : ""));
    $documentId = trim((string)(isset($_POST["document_id"]) ? $_POST["document_id"] : ""));
    $errorMessage = "";
    $deletedDocument = online_admission_delete_document($con, $branchId, $documentId, $errorMessage);

    $_SESSION["ONLINE_ADMISSION_ADMIN_MESSAGE"] = $deletedDocument
        ? aa_alert("success", "\"".online_admission_document_display_title($deletedDocument)."\" deleted successfully.")
        : aa_alert("error", $errorMessage !== "" ? $errorMessage : "The admission document could not be deleted right now.");
    header("location:".aa_admin_url(array("document_year" => $documentYear !== "" ? $documentYear : null), "#admission-documents"));
    exit();
}

if(isset($_POST["save_application_changes"])){
    if(!$editableApplication){
        $flashMessage = aa_alert("error", "The selected application could not be found.");
    }else{
        $editableApplicationForm = aa_application_form_defaults($editableApplication);
        foreach($editableApplicationForm as $key => $value){
            $editableApplicationForm[$key] = trim((string)(isset($_POST[$key]) ? $_POST[$key] : ""));
        }
        if($editableApplicationForm["status"] === ""){
            $editableApplicationForm["status"] = "submitted";
        }

        $errors = array();
        $validStatuses = array("draft", "submitted", "needs_attention", "reviewed");
        if($editableApplicationForm["firstname"] === ""){ $errors[] = "First name is required."; }
        if($editableApplicationForm["surname"] === ""){ $errors[] = "Surname is required."; }

        $birthdate = online_admission_normalize_date($editableApplicationForm["birthdate"]);
        if($birthdate === false || $birthdate === ""){
            $errors[] = "A valid date of birth is required.";
        }else{
            $editableApplicationForm["birthdate"] = $birthdate;
        }

        if($editableApplicationForm["email"] !== "" && !filter_var($editableApplicationForm["email"], FILTER_VALIDATE_EMAIL)){
            $errors[] = "Please enter a valid email address.";
        }
        if(!in_array($editableApplicationForm["status"], $validStatuses, true)){
            $errors[] = "Select a valid admission status.";
        }

        $imageName = trim((string)$editableApplication["filename"]);
        if(empty($errors) && isset($_FILES["admissionphoto"]) && isset($_FILES["admissionphoto"]["error"]) && (int)$_FILES["admissionphoto"]["error"] !== UPLOAD_ERR_NO_FILE){
            $imageError = "";
            $storedImage = online_admission_store_image($_FILES["admissionphoto"], $imageError);
            if($storedImage === false){
                $errors[] = $imageError;
            }elseif($storedImage !== ""){
                $imageName = $storedImage;
            }
        }

        if(empty($errors)){
            $reviewedByToStore = trim((string)$editableApplication["reviewedby"]);
            $reviewedAtToStore = trim((string)$editableApplication["revieweddatetime"]);
            if($editableApplicationForm["status"] === "needs_attention" || $editableApplicationForm["status"] === "reviewed" || $editableApplicationForm["reviewnote"] !== ""){
                $reviewedByToStore = (string)(isset($_SESSION["USERID"]) ? $_SESSION["USERID"] : "");
                $reviewedAtToStore = date("Y-m-d H:i:s");
            }

            $stmt = mysqli_prepare($con, "UPDATE tblonlineadmissionapplication SET
                firstname=?, surname=?, othernames=?, gender=?, birthdate=?,
                email=?, mobile=?, residencetype=?, hometown=?, postaladdress=?, homeaddress=?, religion=?,
                guardianname=?, guardianrelationship=?, guardiancontact=?, medicalnotes=?, studentnote=?,
                filename=?, status=?, reviewnote=?, reviewedby=?, revieweddatetime=NULLIF(?, ''), updatedat=NOW()
                WHERE applicationid=? AND branchid=?
                LIMIT 1");

            if($stmt){
                mysqli_stmt_bind_param(
                    $stmt,
                    str_repeat("s", 24),
                    $editableApplicationForm["firstname"], $editableApplicationForm["surname"], $editableApplicationForm["othernames"], $editableApplicationForm["gender"], $editableApplicationForm["birthdate"],
                    $editableApplicationForm["email"], $editableApplicationForm["mobile"], $editableApplicationForm["residencetype"], $editableApplicationForm["hometown"], $editableApplicationForm["postaladdress"], $editableApplicationForm["homeaddress"], $editableApplicationForm["religion"],
                    $editableApplicationForm["guardianname"], $editableApplicationForm["guardianrelationship"], $editableApplicationForm["guardiancontact"], $editableApplicationForm["medicalnotes"], $editableApplicationForm["studentnote"],
                    $imageName, $editableApplicationForm["status"], $editableApplicationForm["reviewnote"], $reviewedByToStore, $reviewedAtToStore,
                    $editableApplication["applicationid"], $branchId
                );
                if(mysqli_stmt_execute($stmt)){
                    mysqli_stmt_close($stmt);
                    $refreshedApplication = aa_fetch_application_bundle($con, $branchId, $editableApplication["applicationid"]);
                    if($refreshedApplication){
                        online_admission_assign_house_for_application($con, $refreshedApplication);
                    }
                    $_SESSION["ONLINE_ADMISSION_ADMIN_MESSAGE"] = aa_alert("success", "Admission form updated successfully.");
                    header("location:online-admission-admin.php?edit_application=".rawurlencode($editableApplication["applicationid"])."#edit-application");
                    exit();
                }
                $flashMessage = aa_alert("error", "The admission form could not be updated right now.");
                mysqli_stmt_close($stmt);
            }else{
                $flashMessage = aa_alert("error", "The admission form could not be prepared for saving right now.");
            }
        }else{
            $flashMessage = aa_alert("warning", implode(" ", $errors));
        }
    }
}

if(isset($_POST["update_application_status"])){
    $applicationId = trim((string)(isset($_POST["applicationid"]) ? $_POST["applicationid"] : ""));
    $status = trim((string)(isset($_POST["status"]) ? $_POST["status"] : ""));
    $reviewNote = trim((string)(isset($_POST["reviewnote"]) ? $_POST["reviewnote"] : ""));
    $returnAppPage = aa_positive_page(isset($_POST["app_page"]) ? $_POST["app_page"] : 1);
    $returnAppSearch = trim((string)(isset($_POST["app_search"]) ? $_POST["app_search"] : ""));
    if($applicationId !== "" && in_array($status, array("submitted", "needs_attention", "reviewed"), true)){
        $appEsc = mysqli_real_escape_string($con, $applicationId);
        $statusEsc = mysqli_real_escape_string($con, $status);
        $noteEsc = mysqli_real_escape_string($con, $reviewNote);
        $reviewedByEsc = mysqli_real_escape_string($con, isset($_SESSION["USERID"]) ? (string)$_SESSION["USERID"] : "");
        $updated = mysqli_query($con, "UPDATE tblonlineadmissionapplication SET
            status='$statusEsc',
            reviewnote='$noteEsc',
            reviewedby='$reviewedByEsc',
            revieweddatetime=NOW(),
            updatedat=NOW()
            WHERE applicationid='$appEsc' AND branchid='$branchIdEsc'
            LIMIT 1");
        if($updated){
            $refreshedApplication = aa_fetch_application_bundle($con, $branchId, $applicationId);
            if($refreshedApplication){
                online_admission_assign_house_for_application($con, $refreshedApplication);
            }
        }
        $_SESSION["ONLINE_ADMISSION_ADMIN_MESSAGE"] = $updated
            ? aa_alert("success", "Application status updated successfully.")
            : aa_alert("error", "The application status could not be updated.");
        header("location:".aa_admin_url(array(
            "app_page" => $returnAppPage > 1 ? $returnAppPage : null,
            "app_search" => $returnAppSearch !== "" ? $returnAppSearch : null
        ), "#applications"));
        exit();
    }
}

if(isset($_POST["save_help_request_status"])){
    $requestId = trim((string)(isset($_POST["requestid"]) ? $_POST["requestid"] : ""));
    $status = trim((string)(isset($_POST["help_status"]) ? $_POST["help_status"] : "open"));
    $adminNote = trim((string)(isset($_POST["adminnote"]) ? $_POST["adminnote"] : ""));
    if($requestId === ""){
        $flashMessage = aa_alert("warning", "Select a help request first.");
    }else{
        $updated = online_admission_update_help_request($con, $branchId, $requestId, $status, $adminNote);
        $_SESSION["ONLINE_ADMISSION_ADMIN_MESSAGE"] = $updated
            ? aa_alert("success", "Help request updated successfully.")
            : aa_alert("error", "The help request could not be updated.");
        header("location:online-admission-admin.php#help-requests");
        exit();
    }
}

if(isset($_POST["clear_admission_year"])){
    $clearYear = trim((string)(isset($_POST["clear_year"]) ? $_POST["clear_year"] : ""));
    $backupReference = trim((string)(isset($_POST["backup_reference"]) ? $_POST["backup_reference"] : ""));
    $confirmClear = !empty($_POST["confirm_clear"]);

    if($clearYear === ""){
        $flashMessage = aa_alert("warning", "Select an admission year to clear.");
    }elseif(!$confirmClear){
        $flashMessage = aa_alert("warning", "Confirm that you have taken a backup before clearing the admission year.");
    }else{
        $clearResult = online_admission_clear_year($con, $branchId, $clearYear, $backupReference, isset($_SESSION["USERID"]) ? $_SESSION["USERID"] : "");
        $flashMessage = aa_alert($clearResult["success"] ? "success" : "error", $clearResult["message"]);
    }
}

$stats = array("posted" => 0, "draft" => 0, "submitted" => 0, "reviewed" => 0);
$statsRes = mysqli_query($con, "SELECT
    (SELECT COUNT(*) FROM tbladmissionpostedstudent WHERE branchid='$branchIdEsc' AND status='active') AS posted_total,
    SUM(CASE WHEN status='draft' THEN 1 ELSE 0 END) AS draft_total,
    SUM(CASE WHEN status='submitted' THEN 1 ELSE 0 END) AS submitted_total,
    SUM(CASE WHEN status='reviewed' THEN 1 ELSE 0 END) AS reviewed_total
    FROM tblonlineadmissionapplication
    WHERE branchid='$branchIdEsc'");
if($statsRes && ($row = mysqli_fetch_array($statsRes, MYSQLI_ASSOC))){
    $stats["posted"] = (int)$row["posted_total"];
    $stats["draft"] = (int)$row["draft_total"];
    $stats["submitted"] = (int)$row["submitted_total"];
    $stats["reviewed"] = (int)$row["reviewed_total"];
}

$postedSearch = trim((string)(isset($_GET["posted_search"]) ? $_GET["posted_search"] : ""));
$appSearch = trim((string)(isset($_GET["app_search"]) ? $_GET["app_search"] : ""));
$postedPage = aa_positive_page(isset($_GET["posted_page"]) ? $_GET["posted_page"] : 1);
$appPage = aa_positive_page(isset($_GET["app_page"]) ? $_GET["app_page"] : 1);
$paymentPage = aa_positive_page(isset($_GET["payment_page"]) ? $_GET["payment_page"] : 1);
$postedPerPage = 25;
$applicationsPerPage = 5;
$paymentPerPage = 25;
$postedSearchSql = "";
if($postedSearch !== ""){
    $postedSearchEsc = mysqli_real_escape_string($con, $postedSearch);
    $postedLikeEsc = "%".$postedSearchEsc."%";
    $postedSearchSql = " AND (
        beceindexnumber LIKE '$postedLikeEsc'
        OR firstname LIKE '$postedLikeEsc'
        OR surname LIKE '$postedLikeEsc'
        OR othernames LIKE '$postedLikeEsc'
        OR CONCAT_WS(' ', firstname, othernames, surname) LIKE '$postedLikeEsc'
        OR CONCAT_WS(' ', surname, firstname, othernames) LIKE '$postedLikeEsc'
        OR gender LIKE '$postedLikeEsc'
        OR admissionyear LIKE '$postedLikeEsc'
        OR offeredprogram LIKE '$postedLikeEsc'
        OR offeredclass LIKE '$postedLikeEsc'
        OR residentialstatus LIKE '$postedLikeEsc'
        OR mobile LIKE '$postedLikeEsc'
    )";
}

$postedTotal = 0;
$postedCountRes = mysqli_query($con, "SELECT COUNT(*) AS total
    FROM tbladmissionpostedstudent
    WHERE branchid='$branchIdEsc'$postedSearchSql");
if($postedCountRes && ($row = mysqli_fetch_array($postedCountRes, MYSQLI_ASSOC))){
    $postedTotal = (int)$row["total"];
}
$postedTotalPages = max(1, (int)ceil($postedTotal / $postedPerPage));
if($postedPage > $postedTotalPages){
    $postedPage = $postedTotalPages;
}
$postedOffset = ($postedPage - 1) * $postedPerPage;

$postedStudents = array();
$postedRes = mysqli_query($con, "SELECT *
    FROM tbladmissionpostedstudent
    WHERE branchid='$branchIdEsc'$postedSearchSql
    ORDER BY datetimeentry DESC
    LIMIT $postedOffset, $postedPerPage");
if($postedRes){ while($row = mysqli_fetch_array($postedRes, MYSQLI_ASSOC)){ $postedStudents[] = $row; } }

$postedExportStudents = array();
$postedExportRes = mysqli_query($con, "SELECT *
    FROM tbladmissionpostedstudent
    WHERE branchid='$branchIdEsc'$postedSearchSql
    ORDER BY datetimeentry DESC");
if($postedExportRes){ while($row = mysqli_fetch_array($postedExportRes, MYSQLI_ASSOC)){ $postedExportStudents[] = $row; } }

$appSearchSql = "";
if($appSearch !== ""){
    $appSearchEsc = mysqli_real_escape_string($con, $appSearch);
    $appLikeEsc = "%".$appSearchEsc."%";
    $appSearchSql = " AND (
        beceindexnumber LIKE '$appLikeEsc'
        OR firstname LIKE '$appLikeEsc'
        OR surname LIKE '$appLikeEsc'
        OR othernames LIKE '$appLikeEsc'
        OR CONCAT_WS(' ', firstname, othernames, surname) LIKE '$appLikeEsc'
        OR CONCAT_WS(' ', surname, firstname, othernames) LIKE '$appLikeEsc'
        OR mobile LIKE '$appLikeEsc'
        OR guardianname LIKE '$appLikeEsc'
        OR guardiancontact LIKE '$appLikeEsc'
        OR residencetype LIKE '$appLikeEsc'
        OR admissionyear LIKE '$appLikeEsc'
        OR status LIKE '$appLikeEsc'
        OR verificationtoken LIKE '$appLikeEsc'
    )";
}

$applicationTotal = 0;
$applicationCountRes = mysqli_query($con, "SELECT COUNT(*) AS total
    FROM tblonlineadmissionapplication
    WHERE branchid='$branchIdEsc'$appSearchSql");
if($applicationCountRes && ($row = mysqli_fetch_array($applicationCountRes, MYSQLI_ASSOC))){
    $applicationTotal = (int)$row["total"];
}
$applicationTotalPages = max(1, (int)ceil($applicationTotal / $applicationsPerPage));
if($appPage > $applicationTotalPages){
    $appPage = $applicationTotalPages;
}
$applicationOffset = ($appPage - 1) * $applicationsPerPage;

$applications = array();
$appRes = mysqli_query($con, "SELECT *
    FROM tblonlineadmissionapplication
    WHERE branchid='$branchIdEsc'$appSearchSql
    ORDER BY updatedat DESC
    LIMIT $applicationOffset, $applicationsPerPage");
if($appRes){ while($row = mysqli_fetch_array($appRes, MYSQLI_ASSOC)){ $applications[] = $row; } }
$applicationAssignedHouseMap = array();
foreach($applications as $index => $app){
    $assignedHouse = online_admission_assign_house_for_application($con, $app);
    $refreshedApplication = online_admission_get_application_by_id($con, $app["applicationid"]);
    $applications[$index] = $refreshedApplication ? $refreshedApplication : $app;
    $applicationAssignedHouseMap[$app["applicationid"]] = $assignedHouse;
}
$applicationPaymentMap = array();
foreach($applications as $app){
    $applicationPaymentMap[$app["applicationid"]] = online_admission_get_latest_payment_by_application($con, $app["applicationid"]);
    if(!isset($applicationAssignedHouseMap[$app["applicationid"]])){
        $applicationAssignedHouseMap[$app["applicationid"]] = online_admission_application_assigned_house($con, $app);
    }
}
$paymentTotal = 0;
$paymentCountRes = mysqli_query($con, "SELECT COUNT(*) AS total
    FROM tblonlineadmissionpayment
    WHERE branchid='$branchIdEsc'");
if($paymentCountRes && ($row = mysqli_fetch_array($paymentCountRes, MYSQLI_ASSOC))){
    $paymentTotal = (int)$row["total"];
}
$paymentTotalPages = max(1, (int)ceil($paymentTotal / $paymentPerPage));
if($paymentPage > $paymentTotalPages){
    $paymentPage = $paymentTotalPages;
}
$paymentOffset = ($paymentPage - 1) * $paymentPerPage;

$recentPayments = array();
$paymentRes = mysqli_query($con, "SELECT pay.*, app.firstname, app.surname, app.othernames
    FROM tblonlineadmissionpayment pay
    LEFT JOIN tblonlineadmissionapplication app ON app.applicationid=pay.applicationid
    WHERE pay.branchid='$branchIdEsc'
    ORDER BY pay.createdat DESC
    LIMIT $paymentOffset, $paymentPerPage");
if($paymentRes){ while($row = mysqli_fetch_array($paymentRes, MYSQLI_ASSOC)){ $recentPayments[] = $row; } }

$paymentExportSource = array();
$paymentExportRes = mysqli_query($con, "SELECT pay.*, app.firstname, app.surname, app.othernames
    FROM tblonlineadmissionpayment pay
    LEFT JOIN tblonlineadmissionapplication app ON app.applicationid=pay.applicationid
    WHERE pay.branchid='$branchIdEsc'
    ORDER BY pay.createdat DESC");
if($paymentExportRes){ while($row = mysqli_fetch_array($paymentExportRes, MYSQLI_ASSOC)){ $paymentExportSource[] = $row; } }

$helpRequests = online_admission_get_recent_help_requests($con, $branchId, 20);
$cycleSummaries = online_admission_list_year_summaries($con, $branchId);
$activeCycle = null;
foreach($cycleSummaries as $cycleSummary){
    if((int)$cycleSummary["posted_active"] > 0){
        $activeCycle = $cycleSummary;
        break;
    }
}
if($activeCycle === null && count($cycleSummaries) > 0){
    $activeCycle = $cycleSummaries[0];
}
$documentYear = trim((string)(isset($_GET["document_year"]) ? $_GET["document_year"] : ($activeCycle ? $activeCycle["admissionyear"] : date("Y"))));
if($documentYear === ""){
    $documentYear = date("Y");
}
$documentLibrary = online_admission_list_documents($con, $branchId, $documentYear);
$studentHouses = array();
$houseActiveCount = 0;
$houseRes = mysqli_query($con, "SELECT h.houseid, h.housename, h.description, h.housegender, h.houseresidencetype, h.autoassignenabled, h.status, h.datetimeentry,
        COUNT(sh.assignmentid) AS studenttotal
    FROM tblhouse h
    LEFT JOIN tblstudenthouse sh ON sh.houseid=h.houseid AND sh.status='active'
    GROUP BY h.houseid, h.housename, h.description, h.housegender, h.houseresidencetype, h.autoassignenabled, h.status, h.datetimeentry
    ORDER BY CASE WHEN h.status='active' THEN 0 ELSE 1 END, h.housename ASC");
if($houseRes){
    while($houseRow = mysqli_fetch_array($houseRes, MYSQLI_ASSOC)){
        if(strtolower(trim((string)$houseRow["status"])) === "active"){
            $houseActiveCount++;
        }
        $studentHouses[] = $houseRow;
    }
}

$postedExportHeaders = array("BECE Index", "Student", "Gender", "Birth Date", "Programme", "Class", "Residence", "Year", "Mobile", "Added On");
$postedExportRows = array();
foreach($postedExportStudents as $student){
    $postedExportRows[] = array(
        (string)$student["beceindexnumber"],
        trim((string)$student["firstname"]." ".(string)$student["othernames"]." ".(string)$student["surname"]),
        (string)$student["gender"],
        aa_date($student["birthdate"], "d M Y"),
        (string)$student["offeredprogram"],
        (string)$student["offeredclass"],
        (string)$student["residentialstatus"],
        (string)$student["admissionyear"],
        (string)$student["mobile"],
        aa_date($student["datetimeentry"], "d M Y, g:i a")
    );
}

$paymentExportHeaders = array("Student", "Reference", "Internal Payment Code", "Amount", "Status", "Student Mobile", "Created", "Paid", "Action");
$paymentExportRows = array();
foreach($paymentExportSource as $payment){
    $paymentExportRows[] = array(
        trim((string)$payment["firstname"]." ".(string)$payment["othernames"]." ".(string)$payment["surname"]),
        (string)$payment["reference"],
        trim((string)$payment["admissioncode"]) !== "" ? (string)$payment["admissioncode"] : "Not issued",
        aa_money($payment["amount"], $payment["currency"]),
        online_admission_payment_status_label($payment["status"]),
        (string)$payment["mobile"],
        aa_date($payment["createdat"], "d M Y, g:i a"),
        trim((string)$payment["paidat"]) !== "" ? aa_date($payment["paidat"], "d M Y, g:i a") : "Not paid",
        trim((string)$payment["applicationid"]) !== "" ? "Open Form" : "Form not started"
    );
}

$exportAction = trim((string)(isset($_GET["export"]) ? $_GET["export"] : ""));
$printAction = trim((string)(isset($_GET["print"]) ? $_GET["print"] : ""));
$cycleYear = trim((string)(isset($_GET["cycle_year"]) ? $_GET["cycle_year"] : ""));
if($cycleYear !== "" && $exportAction === "year_posted_students"){
    $yearRows = array();
    foreach(aa_year_posted_students($con, $branchId, $cycleYear) as $student){
        $yearRows[] = array(
            (string)$student["beceindexnumber"],
            trim((string)$student["firstname"]." ".(string)$student["othernames"]." ".(string)$student["surname"]),
            (string)$student["gender"],
            aa_date($student["birthdate"], "d M Y"),
            (string)$student["offeredprogram"],
            (string)$student["offeredclass"],
            (string)$student["residentialstatus"],
            (string)$student["status"],
            (string)$student["mobile"],
            aa_date($student["datetimeentry"], "d M Y, g:i a")
        );
    }
    aa_output_excel_table(aa_file_slug($branchName)."-".$cycleYear."-posted-students.xls", "Posted Students - ".$cycleYear, array("BECE Index", "Student", "Gender", "Birth Date", "Programme", "Class", "Residence", "Record Status", "Mobile", "Added On"), $yearRows);
}
if($cycleYear !== "" && $exportAction === "year_applications"){
    $yearRows = array();
    foreach(aa_year_applications($con, $branchId, $cycleYear) as $application){
        $yearRows[] = array(
            trim((string)$application["firstname"]." ".(string)$application["othernames"]." ".(string)$application["surname"]),
            (string)$application["beceindexnumber"],
            (string)$application["mobile"],
            (string)$application["guardiancontact"],
            (string)$application["residencetype"],
            online_admission_status_label($application["status"]),
            trim((string)$application["verificationtoken"]) !== "" ? (string)$application["verificationtoken"] : "Not issued",
            aa_date($application["submittedat"], "d M Y, g:i a"),
            aa_date($application["updatedat"], "d M Y, g:i a")
        );
    }
    aa_output_excel_table(aa_file_slug($branchName)."-".$cycleYear."-admission-applications.xls", "Admission Applications - ".$cycleYear, array("Student", "BECE Index", "Student Mobile", "Guardian Contact", "Residence", "Status", "Token", "Submitted", "Last Updated"), $yearRows);
}
if($cycleYear !== "" && $exportAction === "year_payments"){
    $yearRows = array();
    foreach(aa_year_payments($con, $branchId, $cycleYear) as $payment){
        $yearRows[] = array(
            trim((string)$payment["firstname"]." ".(string)$payment["othernames"]." ".(string)$payment["surname"]),
            (string)$payment["reference"],
            trim((string)$payment["admissioncode"]) !== "" ? (string)$payment["admissioncode"] : "Not issued",
            aa_money($payment["amount"], $payment["currency"]),
            online_admission_payment_status_label($payment["status"]),
            (string)$payment["mobile"],
            aa_date($payment["createdat"], "d M Y, g:i a"),
            trim((string)$payment["paidat"]) !== "" ? aa_date($payment["paidat"], "d M Y, g:i a") : "Not paid"
        );
    }
    aa_output_excel_table(aa_file_slug($branchName)."-".$cycleYear."-admission-payments.xls", "Admission Payments - ".$cycleYear, array("Student", "Reference", "Internal Payment Code", "Amount", "Status", "Student Mobile", "Created", "Paid"), $yearRows);
}
if($cycleYear !== "" && $exportAction === "year_help_requests"){
    $yearRows = array();
    foreach(aa_year_help_requests($con, $branchId, $cycleYear) as $request){
        $yearRows[] = array(
            (string)$request["studentname"],
            (string)$request["beceindexnumber"],
            (string)$request["contactphone"],
            trim((string)$request["verificationtoken"]) !== "" ? (string)$request["verificationtoken"] : "",
            online_admission_help_status_label($request["status"]),
            trim((string)$request["adminnote"]),
            trim(preg_replace('/\s+/', ' ', (string)$request["helpmessage"])),
            aa_date($request["requestedat"], "d M Y, g:i a")
        );
    }
    aa_output_excel_table(aa_file_slug($branchName)."-".$cycleYear."-admission-help-requests.xls", "Admission Help Requests - ".$cycleYear, array("Student", "BECE Index", "Phone", "Token", "Status", "Admin Note", "Message", "Requested"), $yearRows);
}
if($cycleYear !== "" && $exportAction === "year_images"){
    aa_output_year_images_zip($con, $branchId, $cycleYear, $branchName);
}
if($exportAction === "posted_students"){
    $title = "Recent Posted Students";
    if($postedSearch !== ""){
        $title .= " - Search: ".$postedSearch;
    }
    aa_output_excel_table(aa_file_slug($branchName)."-recent-posted-students.xls", $title, $postedExportHeaders, $postedExportRows);
}
if($exportAction === "recent_payments"){
    aa_output_excel_table(aa_file_slug($branchName)."-recent-admission-payments.xls", "Recent Admission Payments", $paymentExportHeaders, $paymentExportRows);
}
if($printAction === "posted_students"){
    $title = "Recent Posted Students";
    if($postedSearch !== ""){
        $title .= " - Search: ".$postedSearch;
    }
    aa_output_print_table($title, $postedExportHeaders, $postedExportRows, $companyName, $branchName);
}
if($printAction === "recent_payments"){
    aa_output_print_table("Recent Admission Payments", $paymentExportHeaders, $paymentExportRows, $companyName, $branchName);
}
?>
<!DOCTYPE html>
<html>
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/register-student.css">
<link rel="stylesheet" type="text/css" href="css/online-admission-admin.css">
</head>
<body class="body-style student-register-page admission-admin-page">
<div class="header"><?php include("menu.php"); ?></div>
<main class="rs-shell">
    <?php if($flashMessage !== ""){ ?><div class="rs-flash"><?php echo $flashMessage; ?></div><?php } ?>

    <section class="rs-hero">
        <div>
            <span class="rs-kicker"><i class="fa fa-globe"></i> Online Admission Control</span>
            <h1>Online admission dashboard</h1>
            <p>Handle setup, downloads, intake, and application review from one clean, mobile-friendly control panel.</p>
            <div class="rs-pills">
                <span>Setup</span>
                <span>Intake</span>
                <span>Review</span>
                <span>Support</span>
            </div>
        </div>
        <aside class="rs-hero-card">
            <span class="rs-kicker">Current Branch</span>
            <h2><?php echo aa_esc($branchName); ?></h2>
            <p><?php echo aa_esc($companyName); ?></p>
            <div class="rs-metrics">
                <article><span>Posted</span><strong><?php echo number_format($stats["posted"]); ?></strong></article>
                <article><span>Drafts</span><strong><?php echo number_format($stats["draft"]); ?></strong></article>
                <article><span>Submitted</span><strong><?php echo number_format($stats["submitted"]); ?></strong></article>
                <article><span>Reviewed</span><strong><?php echo number_format($stats["reviewed"]); ?></strong></article>
            </div>
        </aside>
    </section>

    <section class="aa-overview-grid">
        <article class="aa-overview-card">
            <span class="aa-overview-card__label">Active Year</span>
            <strong><?php echo aa_esc($activeCycle ? $activeCycle["admissionyear"] : $documentYear); ?></strong>
            <small><?php echo $activeCycle ? number_format((int)$activeCycle["posted_total"])." posted record(s)" : "Current document year loaded"; ?></small>
        </article>
        <article class="aa-overview-card">
            <span class="aa-overview-card__label">Portal Status</span>
            <strong><?php echo online_admission_portal_is_open($paymentSetting) ? "Open" : "Closed"; ?></strong>
            <small><?php echo (int)$paymentSetting["enabled"] === 1 ? "Payment switch enabled" : "Payment switch off"; ?></small>
        </article>
        <article class="aa-overview-card">
            <span class="aa-overview-card__label">Submissions</span>
            <strong><?php echo number_format((int)$stats["submitted"] + (int)$stats["reviewed"]); ?></strong>
            <small><?php echo number_format((int)$stats["draft"]); ?> draft record(s)</small>
        </article>
        <article class="aa-overview-card">
            <span class="aa-overview-card__label">Downloads</span>
            <strong><?php echo number_format(count($documentLibrary)); ?></strong>
            <small><?php echo number_format(count($helpRequests)); ?> help request(s)</small>
        </article>
    </section>

    <nav class="aa-quick-links" aria-label="Admission admin quick links">
        <a href="#portal-entry" class="aa-quick-link"><i class="fa fa-globe"></i> Portal</a>
        <a href="#payment-settings" class="aa-quick-link"><i class="fa fa-credit-card"></i> Payment</a>
        <a href="#import-posted-students" class="aa-quick-link"><i class="fa fa-upload"></i> Import</a>
        <a href="#posted-student-setup" class="aa-quick-link"><i class="fa fa-user-plus"></i> Add Student</a>
        <a href="#admission-houses" class="aa-quick-link"><i class="fa fa-home"></i> Houses</a>
        <a href="#admission-documents" class="aa-quick-link"><i class="fa fa-folder-open"></i> Documents</a>
        <a href="#applications" class="aa-quick-link"><i class="fa fa-files-o"></i> Applications</a>
        <a href="#admission-payments" class="aa-quick-link"><i class="fa fa-money"></i> Payments</a>
        <a href="#help-requests" class="aa-quick-link"><i class="fa fa-life-ring"></i> Help</a>
    </nav>

    <div class="rs-layout">
        <div class="aa-main-stack">
        <div class="aa-block-label">Manual Operations</div>
        <section class="rs-panel rs-panel--form aa-accordion-section" id="posted-student-setup" data-accordion-group="operations">
            <div class="rs-panel-head">
                <div>
                    <span class="rs-kicker rs-kicker--dark">Posted Student Setup</span>
                    <h2>Add Posted Student</h2>
                    <p>Students can only verify on the public portal after they are on this list.</p>
                </div>
                <span class="aa-section-chip aa-section-chip--info"><?php echo aa_esc($activeCycle ? $activeCycle["admissionyear"] : date("Y")); ?></span>
            </div>

            <form method="post" action="online-admission-admin.php" class="rs-form">
                <section class="rs-section">
                    <div class="rs-grid rs-grid--3">
                        <div class="rs-field"><label for="beceindexnumber">BECE Index Number</label><input type="text" id="beceindexnumber" name="beceindexnumber" value="<?php echo aa_esc($postedForm["beceindexnumber"]); ?>" required></div>
                        <div class="rs-field"><label for="birthdate">Date of Birth</label><input type="date" id="birthdate" name="birthdate" value="<?php echo aa_esc($postedForm["birthdate"]); ?>" required></div>
                        <div class="rs-field"><label for="admissionyear">Admission Year</label><input type="text" id="admissionyear" name="admissionyear" value="<?php echo aa_esc($postedForm["admissionyear"]); ?>" required></div>
                        <div class="rs-field"><label for="firstname">First Name</label><input type="text" id="firstname" name="firstname" value="<?php echo aa_esc($postedForm["firstname"]); ?>" required></div>
                        <div class="rs-field"><label for="surname">Surname</label><input type="text" id="surname" name="surname" value="<?php echo aa_esc($postedForm["surname"]); ?>" required></div>
                        <div class="rs-field"><label for="othernames">Other Names</label><input type="text" id="othernames" name="othernames" value="<?php echo aa_esc($postedForm["othernames"]); ?>"></div>
                        <div class="rs-field"><label for="gender">Gender</label><select id="gender" name="gender"><option value="">Select gender</option><option value="Male"<?php echo $postedForm["gender"]==="Male" ? " selected" : ""; ?>>Male</option><option value="Female"<?php echo $postedForm["gender"]==="Female" ? " selected" : ""; ?>>Female</option></select></div>
                        <div class="rs-field"><label for="offeredprogram">Offered Programme</label><input type="text" id="offeredprogram" name="offeredprogram" value="<?php echo aa_esc($postedForm["offeredprogram"]); ?>"></div>
                        <div class="rs-field"><label for="offeredclass">Offered Class</label><input type="text" id="offeredclass" name="offeredclass" value="<?php echo aa_esc($postedForm["offeredclass"]); ?>"></div>
                        <div class="rs-field"><label for="residentialstatus">Residence Status</label><select id="residentialstatus" name="residentialstatus"><option value="">Select status</option><option value="Day"<?php echo $postedForm["residentialstatus"]==="Day" ? " selected" : ""; ?>>Day</option><option value="Boarding"<?php echo $postedForm["residentialstatus"]==="Boarding" ? " selected" : ""; ?>>Boarding</option></select></div>
                        <div class="rs-field"><label for="mobile">Contact Number</label><input type="text" id="mobile" name="mobile" value="<?php echo aa_esc($postedForm["mobile"]); ?>"></div>
                    </div>
                </section>

                <div class="rs-form-foot">
                    <p><i class="fa fa-info-circle"></i> After saving, the student can verify on the landing-page admission link using BECE index number and date of birth.</p>
                    <button type="submit" name="add_posted_student" class="rs-submit"><i class="fa fa-plus"></i> Add Posted Student</button>
                </div>
            </form>
        </section>

        <section class="rs-panel aa-section aa-accordion-section" id="admission-houses" data-accordion-group="operations">
            <div class="rs-panel-head">
                <div>
                    <span class="rs-kicker rs-kicker--dark">House Setup</span>
                    <h2>Student Houses</h2>
                    <p>Create the houses students will later be assigned to across registration, house management, and exeat workflows.</p>
                </div>
                <span class="aa-section-chip aa-section-chip--neutral"><?php echo number_format($houseActiveCount); ?> Active</span>
            </div>

            <form method="post" action="online-admission-admin.php#admission-houses" class="rs-form aa-house-form">
                <section class="rs-section">
                    <div class="rs-grid rs-grid--2">
                        <div class="rs-field">
                            <label for="admission_house_name">House Name</label>
                            <input type="text" id="admission_house_name" name="housename" value="<?php echo aa_esc($houseForm["housename"]); ?>" placeholder="Example: Red House" required>
                        </div>
                        <div class="rs-field">
                            <label for="admission_house_description">Short Description</label>
                            <textarea id="admission_house_description" name="description" placeholder="Optional note about the house, block, or intake group."><?php echo aa_esc($houseForm["description"]); ?></textarea>
                        </div>
                        <div class="rs-field">
                            <label for="admission_house_gender">Student Gender</label>
                            <select id="admission_house_gender" name="housegender" required>
                                <option value="Male"<?php echo $houseForm["housegender"] === "Male" ? " selected" : ""; ?>>Male</option>
                                <option value="Female"<?php echo $houseForm["housegender"] === "Female" ? " selected" : ""; ?>>Female</option>
                            </select>
                        </div>
                        <div class="rs-field">
                            <label for="admission_house_residence">Residence Type</label>
                            <select id="admission_house_residence" name="houseresidencetype" required>
                                <option value="Boarding"<?php echo $houseForm["houseresidencetype"] === "Boarding" ? " selected" : ""; ?>>Boarding</option>
                                <option value="Day"<?php echo $houseForm["houseresidencetype"] === "Day" ? " selected" : ""; ?>>Day</option>
                            </select>
                        </div>
                    </div>
                    <label class="aa-payment-toggle aa-payment-toggle--compact">
                        <input type="checkbox" name="autoassignenabled" value="1"<?php echo $houseForm["autoassignenabled"] === "1" ? " checked" : ""; ?>>
                        <span>Use this house in automatic online-admission house assignment.</span>
                    </label>
                </section>

                <div class="rs-form-foot">
                    <p><i class="fa fa-home"></i> Houses created here immediately become available in the rest of the student house tools, and can be used for fair auto-assignment during online admission.</p>
                    <button type="submit" name="save_admission_house" class="rs-submit"><i class="fa fa-plus"></i> Create House</button>
                </div>
            </form>

            <div class="aa-house-bar">
                <div class="aa-payment-config-meta">
                    <span class="aa-status aa-status--success"><?php echo number_format($houseActiveCount); ?> Active</span>
                    <span class="aa-status aa-status--neutral"><?php echo number_format(count($studentHouses)); ?> Total Houses</span>
                </div>
                <a href="house-entry.php" class="aa-link aa-link--ghost aa-link--inline"><i class="fa fa-external-link"></i> Open Full House Management</a>
            </div>

            <div class="aa-house-grid">
                <?php if(!empty($studentHouses)){ foreach($studentHouses as $houseRow){ ?>
                <article class="aa-house-card">
                    <div class="aa-house-card__head">
                        <div>
                            <h3><?php echo aa_esc($houseRow["housename"]); ?></h3>
                            <p><?php echo aa_esc(trim((string)$houseRow["description"]) !== "" ? $houseRow["description"] : "No description added yet."); ?></p>
                        </div>
                        <span class="<?php echo aa_house_status_class($houseRow["status"]); ?>"><?php echo aa_esc(ucfirst((string)$houseRow["status"])); ?></span>
                    </div>
                    <div class="aa-house-meta">
                        <span><strong>Students:</strong> <?php echo number_format((int)$houseRow["studenttotal"]); ?> assigned</span>
                        <span><strong>Gender:</strong> <?php echo aa_esc(house_master_normalize_gender_label($houseRow["housegender"]) !== "" ? house_master_normalize_gender_label($houseRow["housegender"]) : "Not set"); ?></span>
                        <span><strong>Residence:</strong> <?php echo aa_esc(house_master_normalize_residence_label($houseRow["houseresidencetype"]) !== "" ? house_master_normalize_residence_label($houseRow["houseresidencetype"]) : "Not set"); ?></span>
                        <span><strong>Auto Assign:</strong> <?php echo (int)$houseRow["autoassignenabled"] === 1 ? "Yes" : "No"; ?></span>
                        <span><strong>Created:</strong> <?php echo aa_esc(aa_date($houseRow["datetimeentry"], "d M Y")); ?></span>
                    </div>
                    <form method="post" action="online-admission-admin.php#admission-houses" class="aa-house-config">
                        <input type="hidden" name="houseid" value="<?php echo aa_esc($houseRow["houseid"]); ?>">
                        <div class="rs-grid rs-grid--3">
                            <div class="rs-field">
                                <label>Gender</label>
                                <select name="housegender">
                                    <option value="Male"<?php echo house_master_normalize_gender_label($houseRow["housegender"]) === "Male" ? " selected" : ""; ?>>Male</option>
                                    <option value="Female"<?php echo house_master_normalize_gender_label($houseRow["housegender"]) === "Female" ? " selected" : ""; ?>>Female</option>
                                </select>
                            </div>
                            <div class="rs-field">
                                <label>Residence</label>
                                <select name="houseresidencetype">
                                    <option value="Boarding"<?php echo house_master_normalize_residence_label($houseRow["houseresidencetype"]) === "Boarding" ? " selected" : ""; ?>>Boarding</option>
                                    <option value="Day"<?php echo house_master_normalize_residence_label($houseRow["houseresidencetype"]) === "Day" ? " selected" : ""; ?>>Day</option>
                                </select>
                            </div>
                            <label class="aa-payment-toggle aa-payment-toggle--compact aa-house-toggle">
                                <input type="checkbox" name="autoassignenabled" value="1"<?php echo (int)$houseRow["autoassignenabled"] === 1 ? " checked" : ""; ?>>
                                <span>Auto assign</span>
                            </label>
                        </div>
                        <button type="submit" name="save_house_profile" class="aa-button aa-button--wide"><i class="fa fa-save"></i> Save House Rules</button>
                    </form>
                </article>
                <?php } } else { ?>
                <div class="rs-empty"><h3>No houses created yet</h3><p>Add the first student house here and it will be available throughout the system.</p></div>
                <?php } ?>
            </div>
        </section>

        <section class="rs-panel aa-section aa-section--compact aa-accordion-section" id="cycle-rollover" data-accordion-group="operations">
            <div class="rs-side-head">
                <span class="rs-kicker rs-kicker--dark">Admission Reset</span>
                <h2>Clear Admission Year</h2>
                <span class="aa-section-chip aa-section-chip--warning"><?php echo aa_esc($activeCycle ? $activeCycle["admissionyear"] : "No Active Year"); ?></span>
            </div>
            <p class="aa-copy">Use this only when one admission cycle is fully finished and you want to prepare for a new year. Download the year records first, then clear that entire admission year from posted students, forms, payments, and help requests.</p>
            <div class="aa-rollover-note">
                <strong>What happens when you clear a year</strong>
                <span>The system saves that year's student admission photos into a backup folder, then removes that year's posted students, applications, payments, and help requests so the portal is ready for a fresh intake.</span>
            </div>

            <?php if($activeCycle){ $cycle = $activeCycle; $cycleState = aa_cycle_status($cycle); ?>
            <div class="aa-cycle-grid">
                <article class="aa-cycle-card">
                    <div class="aa-cycle-card__top">
                        <div>
                            <h3><?php echo aa_esc($cycle["admissionyear"]); ?> Active Admission Year</h3>
                            <p>Download the year backup files before using the clear button.</p>
                        </div>
                        <span class="<?php echo $cycleState["class"]; ?>"><?php echo aa_esc($cycleState["label"]); ?></span>
                    </div>

                    <div class="aa-cycle-metrics">
                        <article><span>Posted</span><strong><?php echo number_format((int)$cycle["posted_total"]); ?></strong></article>
                        <article><span>Applications</span><strong><?php echo number_format((int)$cycle["application_total"]); ?></strong></article>
                        <article><span>Drafts</span><strong><?php echo number_format((int)$cycle["draft_total"]); ?></strong></article>
                        <article><span>Submitted</span><strong><?php echo number_format((int)$cycle["submitted_total"]); ?></strong></article>
                        <article><span>Reviewed</span><strong><?php echo number_format((int)$cycle["reviewed_total"]); ?></strong></article>
                        <article><span>Paid</span><strong><?php echo number_format((int)$cycle["payment_success_total"]); ?></strong></article>
                    </div>

                    <div class="aa-cycle-actions">
                        <a href="<?php echo aa_esc(aa_admin_url(array("export" => "year_posted_students", "cycle_year" => $cycle["admissionyear"]), "#cycle-rollover")); ?>" class="aa-link aa-link--ghost aa-link--inline"><i class="fa fa-file-excel-o"></i> Posted List</a>
                        <a href="<?php echo aa_esc(aa_admin_url(array("export" => "year_applications", "cycle_year" => $cycle["admissionyear"]), "#cycle-rollover")); ?>" class="aa-link aa-link--ghost aa-link--inline"><i class="fa fa-file-excel-o"></i> Applications</a>
                        <a href="<?php echo aa_esc(aa_admin_url(array("export" => "year_payments", "cycle_year" => $cycle["admissionyear"]), "#cycle-rollover")); ?>" class="aa-link aa-link--ghost aa-link--inline"><i class="fa fa-file-excel-o"></i> Payments</a>
                        <a href="<?php echo aa_esc(aa_admin_url(array("export" => "year_help_requests", "cycle_year" => $cycle["admissionyear"]), "#cycle-rollover")); ?>" class="aa-link aa-link--ghost aa-link--inline"><i class="fa fa-file-excel-o"></i> Help Requests</a>
                        <a href="<?php echo aa_esc(aa_admin_url(array("export" => "year_images", "cycle_year" => $cycle["admissionyear"]), "#cycle-rollover")); ?>" class="aa-link aa-link--ghost aa-link--inline"><i class="fa fa-download"></i> Images (ZIP)</a>
                    </div>

                    <form method="post" action="online-admission-admin.php#cycle-rollover" class="aa-cycle-form">
                        <input type="hidden" name="clear_year" value="<?php echo aa_esc($cycle["admissionyear"]); ?>">
                        <div class="rs-field">
                            <label for="backup_reference_<?php echo aa_esc($cycle["admissionyear"]); ?>">Backup Reference</label>
                            <input type="text" id="backup_reference_<?php echo aa_esc($cycle["admissionyear"]); ?>" name="backup_reference" placeholder="Example: 2026 SQL backup + year Excel exports">
                        </div>
                        <label class="aa-payment-toggle aa-payment-toggle--compact">
                            <input type="checkbox" name="confirm_clear" value="1">
                            <span>I have backed up <?php echo aa_esc($cycle["admissionyear"]); ?> and I want to clear it.</span>
                        </label>
                        <button type="submit" name="clear_admission_year" class="aa-button aa-button--danger" <?php echo (int)$cycle["posted_total"] < 1 && (int)$cycle["application_total"] < 1 && (int)$cycle["payment_total"] < 1 && (int)$cycle["help_total"] < 1 ? "disabled" : ""; ?>><i class="fa fa-trash"></i> Clear Admission Year</button>
                        <?php if((int)$cycle["posted_total"] < 1 && (int)$cycle["application_total"] < 1 && (int)$cycle["payment_total"] < 1 && (int)$cycle["help_total"] < 1){ ?><p class="aa-cycle-footnote">There is nothing left to clear for this admission year.</p><?php } ?>
                    </form>
                </article>
            </div>
            <?php }else{ ?>
            <div class="rs-empty"><h3>No admission years found</h3><p>Add posted students first before using the reset tools.</p></div>
            <?php } ?>
        </section>
        </div>

        <aside class="rs-side">
            <div class="aa-block-label">Portal Setup</div>
            <section class="rs-panel aa-accordion-section is-open" id="portal-entry" data-accordion-group="setup">
                <div class="rs-side-head">
                    <span class="rs-kicker rs-kicker--dark">Public Entry</span>
                    <h2>Admission Portal</h2>
                    <span class="aa-section-chip <?php echo online_admission_portal_is_open($paymentSetting) ? "aa-section-chip--success" : "aa-section-chip--warning"; ?>"><?php echo online_admission_portal_is_open($paymentSetting) ? "Open" : "Closed"; ?></span>
                </div>
                <p class="aa-copy">Open or preview the public online admission page from here.</p>
                <div class="aa-payment-config-meta">
                    <span class="<?php echo online_admission_portal_is_open($paymentSetting) ? "aa-status aa-status--success" : "aa-status aa-status--warning"; ?>"><?php echo online_admission_portal_is_open($paymentSetting) ? "Portal Open" : "Portal Closed"; ?></span>
                    <span class="aa-status aa-status--neutral"><?php echo (int)$paymentSetting["enabled"] === 1 ? "Payment Configured" : "Payment Disabled"; ?></span>
                </div>
                <a href="online-admission.php" class="aa-link" target="_blank"><i class="fa fa-external-link"></i> Open Public Admission Portal</a>
            </section>

            <section class="rs-panel aa-accordion-section" id="payment-settings" data-accordion-group="setup">
                <div class="rs-side-head">
                    <span class="rs-kicker rs-kicker--dark">Online Payment</span>
                    <h2>Paystack Settings</h2>
                    <span class="aa-section-chip <?php echo (int)$paymentSetting["enabled"] === 1 ? "aa-section-chip--info" : "aa-section-chip--neutral"; ?>"><?php echo (int)$paymentSetting["enabled"] === 1 ? aa_esc(aa_money($paymentSetting["feeamount"], $paymentSetting["currency"])) : "Disabled"; ?></span>
                </div>
                <p class="aa-copy">Control whether students pay online and set the fee for the active cycle.</p>
                <div class="aa-payment-config-meta">
                    <span class="<?php echo $paystackReady ? "aa-status aa-status--success" : "aa-status aa-status--warning"; ?>"><?php echo $paystackReady ? "Paystack Ready" : "Keys Missing"; ?></span>
                    <span class="aa-status aa-status--neutral">Verified posting unlock</span>
                </div>
                <?php if(!$paystackReady){ ?>
                <div class="aa-payment-warning">Add your Paystack test keys in <code>online-admission-paystack-config.php</code> or server environment variables before enabling sandbox payment.</div>
                <?php } ?>
                <form method="post" action="online-admission-admin.php#payment-settings" class="aa-payment-form">
                    <input type="hidden" name="payablestatus" value="verified">
                    <label class="aa-payment-toggle">
                        <input type="checkbox" name="portal_enabled" value="1"<?php echo online_admission_portal_is_open($paymentSetting) ? " checked" : ""; ?>>
                        <span>Open public online admission portal</span>
                    </label>
                    <label class="aa-payment-toggle">
                        <input type="checkbox" name="payment_enabled" value="1"<?php echo (int)$paymentSetting["enabled"] === 1 ? " checked" : ""; ?>>
                        <span>Enable online admission payment</span>
                    </label>
                    <div class="rs-grid rs-grid--2 aa-payment-grid">
                        <div class="rs-field">
                            <label for="feeamount">Admission Fee Amount</label>
                            <input type="text" id="feeamount" name="feeamount" value="<?php echo aa_esc($paymentSetting["feeamount"]); ?>" placeholder="0.00">
                        </div>
                        <div class="rs-field">
                            <label for="currency">Currency</label>
                            <input type="text" id="currency" name="currency" value="<?php echo aa_esc($paymentSetting["currency"]); ?>" placeholder="GHS">
                        </div>
                        <div class="rs-field">
                            <label for="payment_note">Student Note</label>
                            <input type="text" id="payment_note" name="payment_note" value="<?php echo aa_esc($paymentSetting["note"]); ?>" placeholder="Optional message shown on the public portal">
                        </div>
                    </div>
                    <button type="submit" name="save_payment_settings" class="aa-button aa-button--wide"><i class="fa fa-credit-card"></i> Save Payment Settings</button>
                </form>
                <a href="online-admission-paystack-test.php" class="aa-link aa-link--ghost"><i class="fa fa-flask"></i> Open Paystack Sandbox Tester</a>
            </section>

            <section class="rs-panel aa-accordion-section" id="admission-documents" data-accordion-group="setup">
                <div class="rs-side-head">
                    <span class="rs-kicker rs-kicker--dark">Student Downloads</span>
                    <h2>Admission Documents</h2>
                    <span class="aa-section-chip aa-section-chip--neutral"><?php echo number_format(count($documentLibrary)); ?> Files</span>
                </div>
                <p class="aa-copy">Upload general documents for all applicants, or upload a prospectus targeted to <strong>Male Boarding</strong>, <strong>Female Boarding</strong>, <strong>Male Day</strong>, or <strong>Female Day</strong>. Students only see the downloads that match their record. If you upload one titled <strong>Admission Letter</strong>, that signed file becomes their main admission letter download.</p>
                <form method="get" action="online-admission-admin.php#admission-documents" class="aa-document-year-form">
                    <div class="rs-field">
                        <label for="document_year">Admission Year</label>
                        <input type="text" id="document_year" name="document_year" value="<?php echo aa_esc($documentYear); ?>">
                    </div>
                    <button type="submit" class="aa-button aa-search-button"><i class="fa fa-refresh"></i> Load Year</button>
                </form>
                <form method="post" action="<?php echo aa_esc(aa_admin_url(array("document_year" => $documentYear), "#admission-documents")); ?>" enctype="multipart/form-data" class="aa-document-form">
                    <input type="hidden" name="document_year" value="<?php echo aa_esc($documentYear); ?>">
                    <div class="aa-document-create">
                        <div class="rs-field">
                            <label for="document_title">Document Title</label>
                            <input type="text" id="document_title" name="document_title" placeholder="Example: Reporting Instructions" required>
                        </div>
                        <div class="rs-field">
                            <label for="document_group">Document Type</label>
                            <select id="document_group" name="document_group">
                                <option value="general">General Document</option>
                                <option value="prospectus">Prospectus</option>
                            </select>
                        </div>
                        <div class="rs-field">
                            <label for="document_target_gender">Student Gender</label>
                            <select id="document_target_gender" name="document_target_gender">
                                <option value="">All Students</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="rs-field">
                            <label for="document_target_residencetype">Residence Type</label>
                            <select id="document_target_residencetype" name="document_target_residencetype">
                                <option value="">All Students</option>
                                <option value="Boarding">Boarding</option>
                                <option value="Day">Day</option>
                            </select>
                        </div>
                        <div class="rs-field">
                            <label for="document_file">Document File</label>
                            <input type="file" id="document_file" name="document_file" accept=".pdf,.doc,.docx,application/pdf,.doc,.docx" required>
                        </div>
                    </div>
                    <p class="aa-copy">For a prospectus, choose both the gender and residence target. Uploading another prospectus for the same target replaces the current one for that year.</p>
                    <button type="submit" name="save_admission_documents" class="aa-button aa-button--wide"><i class="fa fa-upload"></i> Add Admission Document</button>
                </form>
                <div class="aa-document-grid">
                    <?php if(!empty($documentLibrary)){ foreach($documentLibrary as $documentRow){ ?>
                    <article class="aa-document-card">
                        <div class="aa-document-card__head">
                            <div>
                                <h3><?php echo aa_esc(online_admission_document_display_title($documentRow)); ?></h3>
                                <p><?php echo aa_esc(trim((string)$documentRow["originalfilename"]) !== "" ? $documentRow["originalfilename"] : $documentRow["filename"]); ?></p>
                            </div>
                            <span class="aa-status aa-status--success">Uploaded</span>
                        </div>
                        <div class="aa-document-meta">
                            <span><strong>Type:</strong> <?php echo aa_esc(online_admission_document_group_label($documentRow)); ?></span>
                            <span><strong>Audience:</strong> <?php echo aa_esc(online_admission_document_target_summary($documentRow)); ?></span>
                            <span><strong>Admission Year:</strong> <?php echo aa_esc($documentYear); ?></span>
                            <span><strong>Uploaded:</strong> <?php echo aa_esc(aa_date($documentRow["uploadedat"], "d M Y, g:i a")); ?></span>
                        </div>
                        <div class="aa-document-actions">
                            <a href="online-admission-document.php?documentid=<?php echo aa_esc($documentRow["documentid"]); ?>" class="aa-link aa-link--ghost aa-link--inline"><i class="fa fa-download"></i> Download</a>
                            <form method="post" action="<?php echo aa_esc(aa_admin_url(array("document_year" => $documentYear), "#admission-documents")); ?>" onsubmit="return confirm('Delete this admission document?');">
                                <input type="hidden" name="document_year" value="<?php echo aa_esc($documentYear); ?>">
                                <input type="hidden" name="document_id" value="<?php echo aa_esc($documentRow["documentid"]); ?>">
                                <button type="submit" name="delete_admission_document" class="aa-button aa-button--danger"><i class="fa fa-trash"></i> Delete</button>
                            </form>
                        </div>
                    </article>
                    <?php } } else { ?>
                    <div class="rs-empty"><h3>No admission documents yet</h3><p>Add the first downloadable document for <?php echo aa_esc($documentYear); ?> here.</p></div>
                    <?php } ?>
                </div>
            </section>

            <section class="rs-panel aa-accordion-section" id="import-posted-students" data-accordion-group="setup">
                <div class="rs-side-head">
                    <span class="rs-kicker rs-kicker--dark">Bulk Upload</span>
                    <h2>Import Posted Students</h2>
                    <span class="aa-section-chip aa-section-chip--info">CSSPS / CSV</span>
                </div>
                <p class="aa-copy">Upload the CSSPS or CSV list here to load many posted students at once.</p>
                <form method="post" action="online-admission-admin.php" enctype="multipart/form-data" class="aa-upload-form">
                    <div class="rs-field">
                        <label for="upload_admissionyear">Default Admission Year</label>
                        <input type="text" id="upload_admissionyear" name="upload_admissionyear" value="<?php echo date("Y"); ?>">
                    </div>
                    <div class="rs-field">
                        <label for="posted_student_file">Excel / CSV File</label>
                        <input type="file" id="posted_student_file" name="posted_student_file" accept=".xlsx,.csv" required>
                    </div>
                    <button type="submit" name="upload_posted_students" class="aa-button aa-button--wide"><i class="fa fa-upload"></i> Upload Posted Students</button>
                </form>
                <a href="online-admission-admin.php?download_posted_template=1" class="aa-link aa-link--ghost"><i class="fa fa-download"></i> Download CSV Template</a>
            </section>
        </aside>
    </div>
    <div class="aa-block-label aa-block-label--records">Records And Review</div>
<?php if(false){ ?>

    <section class="rs-panel aa-section" id="cycle-rollover">
        <div class="rs-side-head">
            <span class="rs-kicker rs-kicker--dark">Admission Reset</span>
            <h2>Clear Admission Year</h2>
        </div>
        <p class="aa-copy">Use this only when one admission cycle is fully finished and you want to prepare for a new year. Download the year records first, then clear that entire admission year from posted students, forms, payments, and help requests.</p>
        <div class="aa-rollover-note">
            <strong>What happens when you clear a year</strong>
            <span>The system saves that year’s student admission photos into a backup folder, then removes the year’s posted students, applications, payments, and help requests so the portal is ready for a fresh intake.</span>
        </div>

        <?php if($activeCycle){ $cycle = $activeCycle; $cycleState = aa_cycle_status($cycle); ?>
        <div class="aa-cycle-grid">
            <article class="aa-cycle-card">
                <div class="aa-cycle-card__top">
                    <div>
                        <h3><?php echo aa_esc($cycle["admissionyear"]); ?> Active Admission Year</h3>
                        <p>Download the year backup files before using the clear button.</p>
                    </div>
                    <span class="<?php echo $cycleState["class"]; ?>"><?php echo aa_esc($cycleState["label"]); ?></span>
                </div>

                <div class="aa-cycle-metrics">
                    <article><span>Posted</span><strong><?php echo number_format((int)$cycle["posted_total"]); ?></strong></article>
                    <article><span>Applications</span><strong><?php echo number_format((int)$cycle["application_total"]); ?></strong></article>
                    <article><span>Drafts</span><strong><?php echo number_format((int)$cycle["draft_total"]); ?></strong></article>
                    <article><span>Submitted</span><strong><?php echo number_format((int)$cycle["submitted_total"]); ?></strong></article>
                    <article><span>Reviewed</span><strong><?php echo number_format((int)$cycle["reviewed_total"]); ?></strong></article>
                    <article><span>Paid</span><strong><?php echo number_format((int)$cycle["payment_success_total"]); ?></strong></article>
                </div>

                <div class="aa-cycle-actions">
                    <a href="<?php echo aa_esc(aa_admin_url(array("export" => "year_posted_students", "cycle_year" => $cycle["admissionyear"]), "#cycle-rollover")); ?>" class="aa-link aa-link--ghost aa-link--inline"><i class="fa fa-file-excel-o"></i> Posted List</a>
                    <a href="<?php echo aa_esc(aa_admin_url(array("export" => "year_applications", "cycle_year" => $cycle["admissionyear"]), "#cycle-rollover")); ?>" class="aa-link aa-link--ghost aa-link--inline"><i class="fa fa-file-excel-o"></i> Applications</a>
                    <a href="<?php echo aa_esc(aa_admin_url(array("export" => "year_payments", "cycle_year" => $cycle["admissionyear"]), "#cycle-rollover")); ?>" class="aa-link aa-link--ghost aa-link--inline"><i class="fa fa-file-excel-o"></i> Payments</a>
                    <a href="<?php echo aa_esc(aa_admin_url(array("export" => "year_help_requests", "cycle_year" => $cycle["admissionyear"]), "#cycle-rollover")); ?>" class="aa-link aa-link--ghost aa-link--inline"><i class="fa fa-file-excel-o"></i> Help Requests</a>
                </div>

                <form method="post" action="online-admission-admin.php#cycle-rollover" class="aa-cycle-form">
                    <input type="hidden" name="clear_year" value="<?php echo aa_esc($cycle["admissionyear"]); ?>">
                    <div class="rs-field">
                        <label for="backup_reference_<?php echo aa_esc($cycle["admissionyear"]); ?>">Backup Reference</label>
                        <input type="text" id="backup_reference_<?php echo aa_esc($cycle["admissionyear"]); ?>" name="backup_reference" placeholder="Example: 2026 SQL backup + year Excel exports">
                    </div>
                    <label class="aa-payment-toggle aa-payment-toggle--compact">
                        <input type="checkbox" name="confirm_clear" value="1">
                        <span>I have backed up <?php echo aa_esc($cycle["admissionyear"]); ?> and I want to clear it.</span>
                    </label>
                    <button type="submit" name="clear_admission_year" class="aa-button aa-button--danger" <?php echo (int)$cycle["posted_total"] < 1 && (int)$cycle["application_total"] < 1 && (int)$cycle["payment_total"] < 1 && (int)$cycle["help_total"] < 1 ? "disabled" : ""; ?>><i class="fa fa-trash"></i> Clear Admission Year</button>
                    <?php if((int)$cycle["posted_total"] < 1 && (int)$cycle["application_total"] < 1 && (int)$cycle["payment_total"] < 1 && (int)$cycle["help_total"] < 1){ ?><p class="aa-cycle-footnote">There is nothing left to clear for this admission year.</p><?php } ?>
                </form>
            </article>
        </div>
        <?php }else{ ?>
        <div class="rs-empty"><h3>No admission years found</h3><p>Add posted students first before using the reset tools.</p></div>
        <?php } ?>
    </section>
<?php } ?>

    <section class="rs-panel aa-section aa-accordion-section" id="posted-students-panel" data-accordion-group="records">
        <div class="rs-side-head">
            <span class="rs-kicker rs-kicker--dark">Posted List</span>
            <h2>Recent Posted Students</h2>
            <span class="aa-section-chip aa-section-chip--neutral"><?php echo number_format($postedTotal); ?></span>
        </div>
        <div class="aa-search-bar">
            <form method="get" action="online-admission-admin.php#posted-students" class="aa-search-form">
                <?php if($paymentPage > 1){ ?><input type="hidden" name="payment_page" value="<?php echo aa_esc($paymentPage); ?>"><?php } ?>
                <div class="aa-search-input">
                    <label for="posted_search">Search Posted Students</label>
                    <input type="text" id="posted_search" name="posted_search" value="<?php echo aa_esc($postedSearch); ?>" placeholder="Search by BECE index, name, programme, year, residence, or phone">
                </div>
                <button type="submit" class="aa-button aa-search-button"><i class="fa fa-search"></i> Search</button>
                <?php if($postedSearch !== ""){ ?><a href="<?php echo aa_esc(aa_admin_url(array("posted_search" => null, "posted_page" => null), "#posted-students")); ?>" class="aa-link aa-link--ghost aa-search-clear"><i class="fa fa-times"></i> Clear</a><?php } ?>
            </form>
            <p class="aa-search-meta"><?php echo $postedSearch !== "" ? "Showing page ".number_format($postedPage)." of ".number_format($postedTotalPages)." for ".number_format($postedTotal)." match(es) for \"".aa_esc($postedSearch)."\"." : "Showing page ".number_format($postedPage)." of ".number_format($postedTotalPages)." from ".number_format($postedTotal)." posted student record(s)."; ?></p>
            <div class="aa-table-actions">
                <a href="<?php echo aa_esc(aa_admin_url(array("export" => "posted_students", "posted_page" => null), "")); ?>" class="aa-link"><i class="fa fa-file-excel-o"></i> Download Excel</a>
                <a href="<?php echo aa_esc(aa_admin_url(array("print" => "posted_students", "posted_page" => null), "")); ?>" class="aa-link aa-link--ghost aa-link--inline" target="_blank"><i class="fa fa-print"></i> Print</a>
            </div>
        </div>
        <div class="aa-table-wrap" id="posted-students">
            <table class="aa-table">
                <thead>
                    <tr>
                        <th>BECE Index</th>
                        <th>Student</th>
                        <th>Gender</th>
                        <th>Programme</th>
                        <th>Class</th>
                        <th>Residence</th>
                        <th>Year</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($postedStudents) > 0){ foreach($postedStudents as $student){ ?>
                    <tr>
                        <td><?php echo aa_esc($student["beceindexnumber"]); ?></td>
                        <td><?php echo aa_esc(trim($student["firstname"]." ".$student["othernames"]." ".$student["surname"])); ?></td>
                        <td><?php echo aa_esc($student["gender"]); ?></td>
                        <td><?php echo aa_esc($student["offeredprogram"]); ?></td>
                        <td><?php echo aa_esc($student["offeredclass"]); ?></td>
                        <td><?php echo aa_esc($student["residentialstatus"]); ?></td>
                        <td><?php echo aa_esc($student["admissionyear"]); ?></td>
                    </tr>
                    <?php } } else { ?>
                    <tr><td colspan="7"><?php echo $postedSearch !== "" ? "No posted students matched that search." : "No posted students have been added yet."; ?></td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php if($postedTotalPages > 1){ ?>
        <div class="aa-pagination">
            <span class="aa-pagination__meta">Page <?php echo number_format($postedPage); ?> of <?php echo number_format($postedTotalPages); ?></span>
            <div class="aa-pagination__links">
                <?php if($postedPage > 1){ ?><a href="<?php echo aa_esc(aa_admin_url(array("posted_page" => $postedPage - 1), "#posted-students")); ?>" class="aa-link aa-link--ghost aa-link--inline">Previous</a><?php } ?>
                <?php
                $postedStart = max(1, $postedPage - 2);
                $postedEnd = min($postedTotalPages, $postedPage + 2);
                for($page = $postedStart; $page <= $postedEnd; $page++){
                    $pageClass = $page === $postedPage ? "aa-link aa-link--inline" : "aa-link aa-link--ghost aa-link--inline";
                ?>
                <a href="<?php echo aa_esc(aa_admin_url(array("posted_page" => $page), "#posted-students")); ?>" class="<?php echo $pageClass; ?>"><?php echo aa_esc($page); ?></a>
                <?php } ?>
                <?php if($postedPage < $postedTotalPages){ ?><a href="<?php echo aa_esc(aa_admin_url(array("posted_page" => $postedPage + 1), "#posted-students")); ?>" class="aa-link aa-link--ghost aa-link--inline">Next</a><?php } ?>
            </div>
        </div>
        <?php } ?>
    </section>

    <?php if($editableApplication){ ?>
    <section class="rs-panel aa-section aa-accordion-section is-open" id="edit-application" data-accordion-group="editor">
        <div class="rs-side-head">
            <span class="rs-kicker rs-kicker--dark">Form Editor</span>
            <h2>View / Edit Submitted Form</h2>
            <span class="<?php echo aa_status_class($editableApplicationForm["status"]); ?>"><?php echo aa_esc(online_admission_status_label($editableApplicationForm["status"])); ?></span>
        </div>
        <div class="aa-editor-shell">
            <aside class="aa-editor-side">
                <div class="aa-editor-photo">
                    <img src="<?php echo aa_esc(online_admission_photo_src($editableApplication["filename"])); ?>" alt="Admission photo">
                </div>
                <div class="aa-editor-summary">
                    <article><span>BECE Index</span><strong><?php echo aa_esc($editableApplication["beceindexnumber"]); ?></strong></article>
                    <article><span>Admission Year</span><strong><?php echo aa_esc($editableApplication["admissionyear"]); ?></strong></article>
                    <article><span>Programme</span><strong><?php echo aa_esc($editableApplication["offeredprogram"] !== "" ? $editableApplication["offeredprogram"] : "Not set"); ?></strong></article>
                    <article><span>Class</span><strong><?php echo aa_esc($editableApplication["offeredclass"] !== "" ? $editableApplication["offeredclass"] : "Not set"); ?></strong></article>
                    <article><span>Placed Residence</span><strong><?php echo aa_esc($editableApplication["posted_residentialstatus"] !== "" ? $editableApplication["posted_residentialstatus"] : "Not set"); ?></strong></article>
                    <article><span>Assigned House</span><strong><?php echo aa_esc($editableAssignedHouse && trim((string)$editableAssignedHouse["housename"]) !== "" ? $editableAssignedHouse["housename"] : "Pending"); ?></strong></article>
                    <article><span>Payment</span><strong><?php echo aa_esc($editablePayment ? online_admission_payment_status_label($editablePayment["status"]) : "Not started"); ?></strong></article>
                    <article><span>Verification Token</span><strong><?php echo aa_esc(trim((string)$editableApplication["verificationtoken"]) !== "" ? $editableApplication["verificationtoken"] : "Not issued"); ?></strong></article>
                    <article><span>Internal Payment Code</span><strong><?php echo aa_esc(($editablePayment && trim((string)$editablePayment["admissioncode"]) !== "") ? $editablePayment["admissioncode"] : "Not issued"); ?></strong></article>
                    <article><span>Last Updated</span><strong><?php echo aa_esc(aa_date($editableApplication["updatedat"], "d M Y, g:i a")); ?></strong></article>
                </div>
                <a href="online-admission-admin.php#applications" class="aa-link aa-link--ghost aa-editor-close"><i class="fa fa-times"></i> Close Editor</a>
            </aside>

            <form method="post" action="online-admission-admin.php?edit_application=<?php echo aa_esc($editableApplication["applicationid"]); ?>#edit-application" enctype="multipart/form-data" class="rs-form aa-editor-form">
                <input type="hidden" name="edit_application" value="<?php echo aa_esc($editableApplication["applicationid"]); ?>">

                <section class="rs-section">
                    <div class="rs-panel-head aa-editor-head">
                        <div>
                            <span class="rs-kicker rs-kicker--dark">Student Details</span>
                            <h3><?php echo aa_esc(trim($editableApplication["firstname"]." ".$editableApplication["othernames"]." ".$editableApplication["surname"])); ?></h3>
                        </div>
                        <span class="<?php echo aa_status_class($editableApplicationForm["status"]); ?>"><?php echo aa_esc(online_admission_status_label($editableApplicationForm["status"])); ?></span>
                    </div>
                    <div class="rs-grid rs-grid--3 aa-editor-grid">
                        <div class="rs-field"><label for="edit_firstname">First Name</label><input type="text" id="edit_firstname" name="firstname" value="<?php echo aa_esc($editableApplicationForm["firstname"]); ?>" required></div>
                        <div class="rs-field"><label for="edit_surname">Surname</label><input type="text" id="edit_surname" name="surname" value="<?php echo aa_esc($editableApplicationForm["surname"]); ?>" required></div>
                        <div class="rs-field"><label for="edit_othernames">Other Names</label><input type="text" id="edit_othernames" name="othernames" value="<?php echo aa_esc($editableApplicationForm["othernames"]); ?>"></div>
                        <div class="rs-field"><label for="edit_gender">Gender</label><select id="edit_gender" name="gender"><option value="">Select gender</option><option value="Male"<?php echo $editableApplicationForm["gender"]==="Male" ? " selected" : ""; ?>>Male</option><option value="Female"<?php echo $editableApplicationForm["gender"]==="Female" ? " selected" : ""; ?>>Female</option><option value="male"<?php echo $editableApplicationForm["gender"]==="male" ? " selected" : ""; ?>>male</option><option value="female"<?php echo $editableApplicationForm["gender"]==="female" ? " selected" : ""; ?>>female</option></select></div>
                        <div class="rs-field"><label for="edit_birthdate">Date of Birth</label><input type="date" id="edit_birthdate" name="birthdate" value="<?php echo aa_esc($editableApplicationForm["birthdate"]); ?>" required></div>
                        <div class="rs-field"><label for="admissionphoto">Replace Photo</label><input type="file" id="admissionphoto" name="admissionphoto" accept=".jpg,.jpeg,.png,.gif,.webp,image/*"></div>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-panel-head aa-editor-head">
                        <div>
                            <span class="rs-kicker rs-kicker--dark">Contact Details</span>
                            <h3>Student Contact</h3>
                        </div>
                    </div>
                    <div class="rs-grid rs-grid--3 aa-editor-grid">
                        <div class="rs-field"><label for="edit_mobile">Student Mobile Number</label><input type="text" id="edit_mobile" name="mobile" value="<?php echo aa_esc($editableApplicationForm["mobile"]); ?>"></div>
                        <div class="rs-field"><label for="edit_email">Email Address</label><input type="email" id="edit_email" name="email" value="<?php echo aa_esc($editableApplicationForm["email"]); ?>"></div>
                        <div class="rs-field"><label for="edit_residencetype">Residence Type</label><select id="edit_residencetype" name="residencetype"><option value="">Select residence type</option><option value="Day"<?php echo $editableApplicationForm["residencetype"]==="Day" ? " selected" : ""; ?>>Day</option><option value="Boarding"<?php echo $editableApplicationForm["residencetype"]==="Boarding" ? " selected" : ""; ?>>Boarding</option></select></div>
                        <div class="rs-field"><label for="edit_religion">Religion</label><select id="edit_religion" name="religion"><option value="">Select religion</option><option value="Christian"<?php echo $editableApplicationForm["religion"]==="Christian" ? " selected" : ""; ?>>Christian</option><option value="Muslim"<?php echo $editableApplicationForm["religion"]==="Muslim" ? " selected" : ""; ?>>Muslim</option><option value="Tradition"<?php echo $editableApplicationForm["religion"]==="Tradition" ? " selected" : ""; ?>>Tradition</option><option value="Others"<?php echo $editableApplicationForm["religion"]==="Others" ? " selected" : ""; ?>>Others</option></select></div>
                        <div class="rs-field"><label for="edit_hometown">Hometown</label><input type="text" id="edit_hometown" name="hometown" value="<?php echo aa_esc($editableApplicationForm["hometown"]); ?>"></div>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-panel-head aa-editor-head">
                        <div>
                            <span class="rs-kicker rs-kicker--dark">Address</span>
                            <h3>Residential Details</h3>
                        </div>
                    </div>
                    <div class="rs-grid rs-grid--2 aa-editor-grid">
                        <div class="rs-field"><label for="edit_postaladdress">Postal Address</label><textarea id="edit_postaladdress" name="postaladdress" rows="3"><?php echo aa_esc($editableApplicationForm["postaladdress"]); ?></textarea></div>
                        <div class="rs-field"><label for="edit_homeaddress">Home Address</label><textarea id="edit_homeaddress" name="homeaddress" rows="3"><?php echo aa_esc($editableApplicationForm["homeaddress"]); ?></textarea></div>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-panel-head aa-editor-head">
                        <div>
                            <span class="rs-kicker rs-kicker--dark">Parent / Guardian</span>
                            <h3>Support Contact</h3>
                        </div>
                    </div>
                    <div class="rs-grid rs-grid--3 aa-editor-grid">
                        <div class="rs-field"><label for="edit_guardianname">Parent / Guardian Name</label><input type="text" id="edit_guardianname" name="guardianname" value="<?php echo aa_esc($editableApplicationForm["guardianname"]); ?>"></div>
                        <div class="rs-field"><label for="edit_guardianrelationship">Relationship</label><input type="text" id="edit_guardianrelationship" name="guardianrelationship" value="<?php echo aa_esc($editableApplicationForm["guardianrelationship"]); ?>"></div>
                        <div class="rs-field"><label for="edit_guardiancontact">Contact Number</label><input type="text" id="edit_guardiancontact" name="guardiancontact" value="<?php echo aa_esc($editableApplicationForm["guardiancontact"]); ?>"></div>
                    </div>
                </section>

                <section class="rs-section">
                    <div class="rs-panel-head aa-editor-head">
                        <div>
                            <span class="rs-kicker rs-kicker--dark">Extra Information</span>
                            <h3>Notes and Status</h3>
                        </div>
                    </div>
                    <div class="rs-grid rs-grid--2 aa-editor-grid">
                        <div class="rs-field"><label for="edit_medicalnotes">Medical Notes</label><textarea id="edit_medicalnotes" name="medicalnotes" rows="3"><?php echo aa_esc($editableApplicationForm["medicalnotes"]); ?></textarea></div>
                        <div class="rs-field"><label for="edit_studentnote">Student Note</label><textarea id="edit_studentnote" name="studentnote" rows="3"><?php echo aa_esc($editableApplicationForm["studentnote"]); ?></textarea></div>
                        <div class="rs-field"><label for="edit_status">Admission Status</label><select id="edit_status" name="status"><option value="draft"<?php echo $editableApplicationForm["status"]==="draft" ? " selected" : ""; ?>>Draft</option><option value="submitted"<?php echo $editableApplicationForm["status"]==="submitted" ? " selected" : ""; ?>>Submitted</option><option value="needs_attention"<?php echo $editableApplicationForm["status"]==="needs_attention" ? " selected" : ""; ?>>Needs Attention</option><option value="reviewed"<?php echo $editableApplicationForm["status"]==="reviewed" ? " selected" : ""; ?>>Reviewed</option></select></div>
                        <div class="rs-field"><label for="edit_reviewnote">Review Note</label><textarea id="edit_reviewnote" name="reviewnote" rows="3"><?php echo aa_esc($editableApplicationForm["reviewnote"]); ?></textarea></div>
                    </div>
                </section>

                <div class="rs-form-foot aa-editor-foot">
                    <p><i class="fa fa-info-circle"></i> Saving here updates the submitted admission form directly from the admin side.</p>
                    <button type="submit" name="save_application_changes" class="rs-submit"><i class="fa fa-save"></i> Save Form Changes</button>
                </div>
            </form>
        </div>
    </section>
    <?php } ?>

    <section class="rs-panel aa-section aa-accordion-section is-open" id="applications" data-accordion-group="records">
        <div class="rs-side-head">
            <span class="rs-kicker rs-kicker--dark">Applications</span>
            <h2>Admission Submissions</h2>
            <span class="aa-section-chip aa-section-chip--success"><?php echo number_format($applicationTotal); ?></span>
        </div>
        <div class="aa-search-bar">
            <form method="get" action="online-admission-admin.php#applications" class="aa-search-form">
                <?php if($postedPage > 1){ ?><input type="hidden" name="posted_page" value="<?php echo aa_esc($postedPage); ?>"><?php } ?>
                <?php if($paymentPage > 1){ ?><input type="hidden" name="payment_page" value="<?php echo aa_esc($paymentPage); ?>"><?php } ?>
                <div class="aa-search-input">
                    <label for="app_search">Search Applications</label>
                    <input type="text" id="app_search" name="app_search" value="<?php echo aa_esc($appSearch); ?>" placeholder="Search by student name, BECE index, token, mobile, guardian, year, or status">
                </div>
                <button type="submit" class="aa-button aa-search-button"><i class="fa fa-search"></i> Search</button>
                <?php if($appSearch !== ""){ ?><a href="<?php echo aa_esc(aa_admin_url(array("app_search" => null, "app_page" => null), "#applications")); ?>" class="aa-link aa-link--ghost aa-search-clear"><i class="fa fa-times"></i> Clear</a><?php } ?>
            </form>
            <p class="aa-search-meta"><?php echo $appSearch !== "" ? "Showing page ".number_format($appPage)." of ".number_format($applicationTotalPages)." for ".number_format($applicationTotal)." match(es) for \"".aa_esc($appSearch)."\"." : "Showing page ".number_format($appPage)." of ".number_format($applicationTotalPages)." from ".number_format($applicationTotal)." application record(s)."; ?></p>
        </div>
        <div class="aa-app-list">
            <?php if(count($applications) > 0){ foreach($applications as $app){ $appPayment = isset($applicationPaymentMap[$app["applicationid"]]) ? $applicationPaymentMap[$app["applicationid"]] : null; ?>
            <article class="aa-app-card">
                <div class="aa-app-card__top">
                    <div>
                        <h3><?php echo aa_esc(trim($app["firstname"]." ".$app["othernames"]." ".$app["surname"])); ?></h3>
                        <p><?php echo aa_esc($app["beceindexnumber"]); ?> · <?php echo aa_esc($app["admissionyear"]); ?></p>
                    </div>
                    <span class="<?php echo aa_status_class($app["status"]); ?>"><?php echo aa_esc(online_admission_status_label($app["status"])); ?></span>
                </div>
                <div class="aa-app-card__meta">
                    <span><?php echo aa_esc($app["residencetype"] !== "" ? $app["residencetype"] : "Residence pending"); ?></span>
                    <?php if(isset($applicationAssignedHouseMap[$app["applicationid"]]) && $applicationAssignedHouseMap[$app["applicationid"]] && trim((string)$applicationAssignedHouseMap[$app["applicationid"]]["housename"]) !== ""){ ?><span>House: <?php echo aa_esc($applicationAssignedHouseMap[$app["applicationid"]]["housename"]); ?></span><?php } ?>
                    <span><?php echo aa_esc($app["guardianname"] !== "" ? $app["guardianname"] : "Guardian pending"); ?></span>
                    <span><?php echo aa_esc($app["mobile"] !== "" ? $app["mobile"] : "Mobile pending"); ?></span>
                    <?php if(trim((string)$app["verificationtoken"]) !== ""){ ?><span>Token: <?php echo aa_esc($app["verificationtoken"]); ?></span><?php } ?>
                    <?php if($appPayment && trim((string)$appPayment["admissioncode"]) !== ""){ ?><span>Internal Code: <?php echo aa_esc($appPayment["admissioncode"]); ?></span><?php } ?>
                    <span class="<?php echo $appPayment ? aa_payment_status_class($appPayment["status"]) : "aa-status aa-status--neutral"; ?>"><?php echo aa_esc($appPayment ? online_admission_payment_status_label($appPayment["status"]) : "Payment not started"); ?></span>
                    <span><?php echo aa_esc(aa_date($app["updatedat"], "d M Y, g:i a")); ?></span>
                </div>
                <form method="post" action="online-admission-admin.php#applications" class="aa-review-form">
                    <input type="hidden" name="applicationid" value="<?php echo aa_esc($app["applicationid"]); ?>">
                    <input type="hidden" name="app_page" value="<?php echo aa_esc($appPage); ?>">
                    <input type="hidden" name="app_search" value="<?php echo aa_esc($appSearch); ?>">
                    <select name="status">
                        <option value="submitted"<?php echo $app["status"]==="submitted" ? " selected" : ""; ?>>Submitted</option>
                        <option value="needs_attention"<?php echo $app["status"]==="needs_attention" ? " selected" : ""; ?>>Needs Attention</option>
                        <option value="reviewed"<?php echo $app["status"]==="reviewed" ? " selected" : ""; ?>>Reviewed</option>
                    </select>
                    <input type="text" name="reviewnote" value="<?php echo aa_esc($app["reviewnote"]); ?>" placeholder="Optional review note">
                    <button type="submit" name="update_application_status" class="aa-button">Update</button>
                </form>
                <div class="aa-app-card__actions">
                    <a href="online-admission-admin.php?edit_application=<?php echo aa_esc($app["applicationid"]); ?>#edit-application" class="aa-link aa-link--ghost aa-app-link"><i class="fa fa-pencil"></i> View / Edit Form</a>
                </div>
            </article>
            <?php } } else { ?>
            <div class="rs-empty"><h3><?php echo $appSearch !== "" ? "No matching applications" : "No admission applications yet"; ?></h3><p><?php echo $appSearch !== "" ? "Try another student name, token, BECE index, or status." : "Applications submitted through the public portal will appear here."; ?></p></div>
            <?php } ?>
        </div>
        <?php if($applicationTotalPages > 1){ ?>
        <div class="aa-pagination">
            <span class="aa-pagination__meta">Page <?php echo number_format($appPage); ?> of <?php echo number_format($applicationTotalPages); ?></span>
            <div class="aa-pagination__links">
                <?php if($appPage > 1){ ?><a href="<?php echo aa_esc(aa_admin_url(array("app_page" => $appPage - 1), "#applications")); ?>" class="aa-link aa-link--ghost aa-link--inline">Previous</a><?php } ?>
                <?php
                $appStart = max(1, $appPage - 2);
                $appEnd = min($applicationTotalPages, $appPage + 2);
                for($page = $appStart; $page <= $appEnd; $page++){
                    $pageClass = $page === $appPage ? "aa-link aa-link--inline" : "aa-link aa-link--ghost aa-link--inline";
                ?>
                <a href="<?php echo aa_esc(aa_admin_url(array("app_page" => $page), "#applications")); ?>" class="<?php echo $pageClass; ?>"><?php echo aa_esc($page); ?></a>
                <?php } ?>
                <?php if($appPage < $applicationTotalPages){ ?><a href="<?php echo aa_esc(aa_admin_url(array("app_page" => $appPage + 1), "#applications")); ?>" class="aa-link aa-link--ghost aa-link--inline">Next</a><?php } ?>
            </div>
        </div>
        <?php } ?>
    </section>

    <section class="rs-panel aa-section aa-accordion-section" id="help-requests" data-accordion-group="records">
        <div class="rs-side-head">
            <span class="rs-kicker rs-kicker--dark">Support</span>
            <h2>Admission Help Requests</h2>
            <span class="aa-section-chip aa-section-chip--warning"><?php echo number_format(count($helpRequests)); ?></span>
        </div>
        <div class="aa-app-list">
            <?php if(count($helpRequests) > 0){ foreach($helpRequests as $request){ ?>
            <article class="aa-app-card">
                <div class="aa-app-card__top">
                    <div>
                        <h3><?php echo aa_esc($request["studentname"]); ?></h3>
                        <p><?php echo aa_esc(trim((string)$request["beceindexnumber"]) !== "" ? $request["beceindexnumber"]." - ".$request["admissionyear"] : "Public help request"); ?></p>
                    </div>
                    <span class="<?php echo aa_help_status_class($request["status"]); ?>"><?php echo aa_esc(online_admission_help_status_label($request["status"])); ?></span>
                </div>
                <div class="aa-app-card__meta">
                    <?php if(trim((string)$request["contactphone"]) !== ""){ ?><span><?php echo aa_esc($request["contactphone"]); ?></span><?php } ?>
                    <?php if(trim((string)$request["verificationtoken"]) !== ""){ ?><span>Token: <?php echo aa_esc($request["verificationtoken"]); ?></span><?php } ?>
                    <span><?php echo aa_esc(aa_date($request["requestedat"], "d M Y, g:i a")); ?></span>
                </div>
                <div class="aa-help-message"><?php echo nl2br(aa_esc($request["helpmessage"])); ?></div>
                <form method="post" action="online-admission-admin.php#help-requests" class="aa-help-form">
                    <input type="hidden" name="requestid" value="<?php echo aa_esc($request["requestid"]); ?>">
                    <select name="help_status">
                        <option value="open"<?php echo $request["status"]==="open" ? " selected" : ""; ?>>Open</option>
                        <option value="contacted"<?php echo $request["status"]==="contacted" ? " selected" : ""; ?>>Contacted</option>
                        <option value="resolved"<?php echo $request["status"]==="resolved" ? " selected" : ""; ?>>Resolved</option>
                    </select>
                    <input type="text" name="adminnote" value="<?php echo aa_esc($request["adminnote"]); ?>" placeholder="Optional admin note">
                    <button type="submit" name="save_help_request_status" class="aa-button">Update</button>
                </form>
                <?php if(trim((string)$request["applicationid"]) !== ""){ ?>
                <div class="aa-app-card__actions">
                    <a href="online-admission-admin.php?edit_application=<?php echo aa_esc($request["applicationid"]); ?>#edit-application" class="aa-link aa-link--ghost aa-app-link"><i class="fa fa-folder-open"></i> Open Form</a>
                </div>
                <?php } ?>
            </article>
            <?php } } else { ?>
            <div class="rs-empty"><h3>No help requests yet</h3><p>Student help requests from the public admission portal will appear here.</p></div>
            <?php } ?>
        </div>
    </section>

    <section class="rs-panel aa-section aa-accordion-section" id="admission-payments" data-accordion-group="records">
        <div class="rs-side-head">
            <span class="rs-kicker rs-kicker--dark">Payments</span>
            <h2>Recent Admission Payments</h2>
            <span class="aa-section-chip aa-section-chip--info"><?php echo number_format($paymentTotal); ?></span>
        </div>
        <p class="aa-search-meta">Showing page <?php echo number_format($paymentPage); ?> of <?php echo number_format($paymentTotalPages); ?> from <?php echo number_format($paymentTotal); ?> payment record(s).</p>
        <div class="aa-table-actions aa-table-actions--section">
            <a href="<?php echo aa_esc(aa_admin_url(array("export" => "recent_payments", "payment_page" => null), "")); ?>" class="aa-link"><i class="fa fa-file-excel-o"></i> Download Excel</a>
            <a href="<?php echo aa_esc(aa_admin_url(array("print" => "recent_payments", "payment_page" => null), "")); ?>" class="aa-link aa-link--ghost aa-link--inline" target="_blank"><i class="fa fa-print"></i> Print</a>
        </div>
        <div class="aa-table-wrap">
            <table class="aa-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Reference</th>
                        <th>Internal Payment Code</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Paid</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($recentPayments) > 0){ foreach($recentPayments as $payment){ ?>
                    <tr>
                        <td><?php echo aa_esc(trim($payment["firstname"]." ".$payment["othernames"]." ".$payment["surname"])); ?></td>
                        <td><?php echo aa_esc($payment["reference"]); ?></td>
                        <td><?php echo aa_esc(trim((string)$payment["admissioncode"]) !== "" ? $payment["admissioncode"] : "Not issued"); ?></td>
                        <td><?php echo aa_esc(aa_money($payment["amount"], $payment["currency"])); ?></td>
                        <td><span class="<?php echo aa_payment_status_class($payment["status"]); ?>"><?php echo aa_esc(online_admission_payment_status_label($payment["status"])); ?></span></td>
                        <td><?php echo aa_esc(aa_date($payment["createdat"], "d M Y, g:i a")); ?></td>
                        <td><?php echo aa_esc($payment["paidat"] !== "" ? aa_date($payment["paidat"], "d M Y, g:i a") : "Not paid"); ?></td>
                        <td><?php if(trim((string)$payment["applicationid"]) !== ""){ ?><a href="online-admission-admin.php?edit_application=<?php echo aa_esc($payment["applicationid"]); ?>#edit-application" class="aa-link aa-link--ghost aa-app-link">Open Form</a><?php }else{ ?>Form not started<?php } ?></td>
                    </tr>
                    <?php } } else { ?>
                    <tr><td colspan="8">No admission payment attempts have been recorded yet.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php if($paymentTotalPages > 1){ ?>
        <div class="aa-pagination">
            <span class="aa-pagination__meta">Page <?php echo number_format($paymentPage); ?> of <?php echo number_format($paymentTotalPages); ?></span>
            <div class="aa-pagination__links">
                <?php if($paymentPage > 1){ ?><a href="<?php echo aa_esc(aa_admin_url(array("payment_page" => $paymentPage - 1), "#admission-payments")); ?>" class="aa-link aa-link--ghost aa-link--inline">Previous</a><?php } ?>
                <?php
                $paymentStart = max(1, $paymentPage - 2);
                $paymentEnd = min($paymentTotalPages, $paymentPage + 2);
                for($page = $paymentStart; $page <= $paymentEnd; $page++){
                    $pageClass = $page === $paymentPage ? "aa-link aa-link--inline" : "aa-link aa-link--ghost aa-link--inline";
                ?>
                <a href="<?php echo aa_esc(aa_admin_url(array("payment_page" => $page), "#admission-payments")); ?>" class="<?php echo $pageClass; ?>"><?php echo aa_esc($page); ?></a>
                <?php } ?>
                <?php if($paymentPage < $paymentTotalPages){ ?><a href="<?php echo aa_esc(aa_admin_url(array("payment_page" => $paymentPage + 1), "#admission-payments")); ?>" class="aa-link aa-link--ghost aa-link--inline">Next</a><?php } ?>
            </div>
        </div>
        <?php } ?>
    </section>
</main>
<script>
(function () {
    function getHeader(section) {
        for (var index = 0; index < section.children.length; index += 1) {
            var child = section.children[index];
            if (child.classList.contains('rs-panel-head') || child.classList.contains('rs-side-head')) {
                return child;
            }
        }
        return null;
    }

    function ensureBody(section, header) {
        for (var index = 0; index < section.children.length; index += 1) {
            if (section.children[index].classList.contains('aa-accordion-body')) {
                return section.children[index];
            }
        }
        var body = document.createElement('div');
        body.className = 'aa-accordion-body';
        while (header.nextSibling) {
            body.appendChild(header.nextSibling);
        }
        section.appendChild(body);
        return body;
    }

    function setOpen(section, open) {
        section.classList.toggle('is-open', !!open);
        section.classList.toggle('is-collapsed', !open);
        var header = getHeader(section);
        if (header) {
            header.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    var sections = Array.prototype.slice.call(document.querySelectorAll('.aa-accordion-section'));
    sections.forEach(function (section) {
        var header = getHeader(section);
        if (!header) {
            return;
        }

        ensureBody(section, header);
        header.classList.add('aa-accordion-toggle');
        header.setAttribute('role', 'button');
        header.setAttribute('tabindex', '0');

        if (!section.classList.contains('is-open')) {
            section.classList.add('is-collapsed');
        }
        setOpen(section, section.classList.contains('is-open'));

        var toggleSection = function () {
            var shouldOpen = !section.classList.contains('is-open');
            if (shouldOpen) {
                var groupName = section.getAttribute('data-accordion-group');
                if (groupName) {
                    sections.forEach(function (otherSection) {
                        if (otherSection !== section && otherSection.getAttribute('data-accordion-group') === groupName) {
                            setOpen(otherSection, false);
                        }
                    });
                }
            }
            setOpen(section, shouldOpen);
        };

        header.addEventListener('click', toggleSection);
        header.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleSection();
            }
        });
    });

    function openFromHash() {
        var hash = window.location.hash;
        if (!hash) {
            return;
        }
        var target = document.getElementById(hash.substring(1));
        if (!target) {
            return;
        }
        var section = target.classList.contains('aa-accordion-section') ? target : target.closest('.aa-accordion-section');
        if (section) {
            var groupName = section.getAttribute('data-accordion-group');
            if (groupName) {
                sections.forEach(function (otherSection) {
                    if (otherSection !== section && otherSection.getAttribute('data-accordion-group') === groupName) {
                        setOpen(otherSection, false);
                    }
                });
            }
            setOpen(section, true);
        }
    }

    openFromHash();
    window.addEventListener('hashchange', openFromHash);
}());
</script>
</body>
</html>
