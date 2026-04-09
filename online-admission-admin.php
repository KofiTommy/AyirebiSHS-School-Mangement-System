<?php
session_start();
include("dbstring.php");
include("check-login.php");
include_once("company.php");
include_once("online-admission-utils.php");
ensure_online_admission_tables($con);

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
if($selectedApplicationId !== ""){
    $editableApplication = aa_fetch_application_bundle($con, $branchId, $selectedApplicationId);
    if($editableApplication){
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
        $_SESSION["ONLINE_ADMISSION_ADMIN_MESSAGE"] = $updated
            ? aa_alert("success", "Application status updated successfully.")
            : aa_alert("error", "The application status could not be updated.");
        header("location:online-admission-admin.php#applications");
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
$postedPage = aa_positive_page(isset($_GET["posted_page"]) ? $_GET["posted_page"] : 1);
$paymentPage = aa_positive_page(isset($_GET["payment_page"]) ? $_GET["payment_page"] : 1);
$postedPerPage = 25;
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

$applications = array();
$appRes = mysqli_query($con, "SELECT *
    FROM tblonlineadmissionapplication
    WHERE branchid='$branchIdEsc'
    ORDER BY updatedat DESC
    LIMIT 40");
if($appRes){ while($row = mysqli_fetch_array($appRes, MYSQLI_ASSOC)){ $applications[] = $row; } }
$applicationPaymentMap = array();
foreach($applications as $app){
    $applicationPaymentMap[$app["applicationid"]] = online_admission_get_latest_payment_by_application($con, $app["applicationid"]);
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
            <h1>Manage posted students and review admission submissions.</h1>
            <p>This is the admin side of the public online admission portal. Add the posted student list here, then monitor drafts, submissions, and reviewed applications from one place.</p>
            <div class="rs-pills">
                <span>Public portal linked</span>
                <span>Mobile responsive</span>
                <span>Simple review flow</span>
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

    <div class="rs-layout">
        <section class="rs-panel rs-panel--form">
            <div class="rs-panel-head">
                <div>
                    <span class="rs-kicker rs-kicker--dark">Posted Student Setup</span>
                    <h2>Add Posted Student</h2>
                    <p>Students can only verify on the public portal after they are on this list.</p>
                </div>
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

        <aside class="rs-side">
            <section class="rs-panel">
                <div class="rs-side-head">
                    <span class="rs-kicker rs-kicker--dark">Public Entry</span>
                    <h2>Admission Portal</h2>
                </div>
                <p class="aa-copy">Students use the public page below to verify their posting and complete admission online.</p>
                <div class="aa-payment-config-meta">
                    <span class="<?php echo online_admission_portal_is_open($paymentSetting) ? "aa-status aa-status--success" : "aa-status aa-status--warning"; ?>"><?php echo online_admission_portal_is_open($paymentSetting) ? "Portal Open" : "Portal Closed"; ?></span>
                    <span class="aa-status aa-status--neutral"><?php echo (int)$paymentSetting["enabled"] === 1 ? "Payment Configured" : "Payment Disabled"; ?></span>
                </div>
                <a href="online-admission.php" class="aa-link" target="_blank"><i class="fa fa-external-link"></i> Open Public Admission Portal</a>
            </section>

            <section class="rs-panel" id="payment-settings">
                <div class="rs-side-head">
                    <span class="rs-kicker rs-kicker--dark">Online Payment</span>
                    <h2>Paystack Settings</h2>
                </div>
                <p class="aa-copy">Switch online admission payment on here and choose the fee amount. Students will verify posting, receive a token, pay through Paystack, and then reopen the form with that token.</p>
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

            <section class="rs-panel">
                <div class="rs-side-head">
                    <span class="rs-kicker rs-kicker--dark">Bulk Upload</span>
                    <h2>Import Posted Students</h2>
                </div>
                <p class="aa-copy">Upload an Excel or CSV file to add many posted students at once. Native CSSPS portal exports are supported directly, and existing BECE index and year matches will be updated instead of duplicated.</p>
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

    <section class="rs-panel aa-section">
        <div class="rs-side-head">
            <span class="rs-kicker rs-kicker--dark">Posted List</span>
            <h2>Recent Posted Students</h2>
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
    <section class="rs-panel aa-section" id="edit-application">
        <div class="rs-side-head">
            <span class="rs-kicker rs-kicker--dark">Form Editor</span>
            <h2>View / Edit Submitted Form</h2>
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

    <section class="rs-panel aa-section" id="applications">
        <div class="rs-side-head">
            <span class="rs-kicker rs-kicker--dark">Applications</span>
            <h2>Admission Submissions</h2>
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
                    <span><?php echo aa_esc($app["guardianname"] !== "" ? $app["guardianname"] : "Guardian pending"); ?></span>
                    <span><?php echo aa_esc($app["mobile"] !== "" ? $app["mobile"] : "Mobile pending"); ?></span>
                    <?php if(trim((string)$app["verificationtoken"]) !== ""){ ?><span>Token: <?php echo aa_esc($app["verificationtoken"]); ?></span><?php } ?>
                    <?php if($appPayment && trim((string)$appPayment["admissioncode"]) !== ""){ ?><span>Internal Code: <?php echo aa_esc($appPayment["admissioncode"]); ?></span><?php } ?>
                    <span class="<?php echo $appPayment ? aa_payment_status_class($appPayment["status"]) : "aa-status aa-status--neutral"; ?>"><?php echo aa_esc($appPayment ? online_admission_payment_status_label($appPayment["status"]) : "Payment not started"); ?></span>
                    <span><?php echo aa_esc(aa_date($app["updatedat"], "d M Y, g:i a")); ?></span>
                </div>
                <form method="post" action="online-admission-admin.php#applications" class="aa-review-form">
                    <input type="hidden" name="applicationid" value="<?php echo aa_esc($app["applicationid"]); ?>">
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
            <div class="rs-empty"><h3>No admission applications yet</h3><p>Applications submitted through the public portal will appear here.</p></div>
            <?php } ?>
        </div>
    </section>

    <section class="rs-panel aa-section" id="help-requests">
        <div class="rs-side-head">
            <span class="rs-kicker rs-kicker--dark">Support</span>
            <h2>Admission Help Requests</h2>
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

    <section class="rs-panel aa-section" id="admission-payments">
        <div class="rs-side-head">
            <span class="rs-kicker rs-kicker--dark">Payments</span>
            <h2>Recent Admission Payments</h2>
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
</body>
</html>
