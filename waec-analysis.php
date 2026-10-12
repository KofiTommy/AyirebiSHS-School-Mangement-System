<?php
session_start();
include("check-login.php");
include("dbstring.php");
require_once __DIR__.'/waec-comparison-utils.php';

$isAllowed = (
    ($_SESSION['ACCESSLEVEL'] == "administrator" && $_SESSION['SYSTEMTYPE'] == "normal_user") ||
    ($_SESSION['ACCESSLEVEL'] == "administrator" && $_SESSION['SYSTEMTYPE'] == "super_user") ||
    (function_exists('um_is_academic_lead_user') && um_is_academic_lead_user())
);

if(!$isAllowed){
    header("location:".(function_exists('um_home_link_for_session') ? um_home_link_for_session() : "student-page.php"));
    exit();
}

function waec_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function waec_key($value){
    $k = strtolower(trim((string)$value));
    $k = preg_replace('/[^a-z0-9]+/', '', $k);
    return $k;
}

function waec_grade($value){
    $g = strtoupper(trim((string)$value));
    $g = str_replace(" ", "", $g);
    $allowed = array("A1","B2","B3","C4","C5","C6","D7","E8","F9");
    return in_array($g, $allowed) ? $g : "";
}

function waec_grade_or_withheld($value){
    $g = strtoupper(trim((string)$value));
    $g = str_replace(" ", "", $g);
    $allowed = array("A1","B2","B3","C4","C5","C6","D7","E8","F9","X","W","*","P");
    return in_array($g, $allowed) ? $g : "";
}

function waec_normalize_gender($value){
    $g = strtoupper(trim((string)$value));
    if(in_array($g, array("M","MALE","BOY","B"))){
        return "Male";
    }
    if(in_array($g, array("F","FEMALE","GIRL","G"))){
        return "Female";
    }
    return "";
}

function waec_display_gender($value){
    $g = waec_normalize_gender($value);
    return $g !== "" ? $g : "Not Set";
}

function waec_extract_gender_hint($value){
    $value = trim((string)$value);
    if($value === ""){
        return "";
    }
    // PDF text may join either side of the gender column, e.g.
    // "NAMEFemale23/11/2006" or "NAMEMale 23/11/2006".
    // Require the following DOB when accepting a missing word boundary;
    // do not infer gender from the student's name.
    if(preg_match('/(FEMALE|MALE)\s*(?=\d{1,2}\/\d{1,2}\/\d{2,4}(?!\d))/i', $value, $m)){
        return waec_normalize_gender($m[1]);
    }
    if(preg_match('/\b(MALE|FEMALE|BOY|GIRL|M|F)\b/i', $value, $m)){
        return waec_normalize_gender($m[1]);
    }
    return "";
}

function waec_points($grade){
    $map = array(
        "A1" => 1, "B2" => 2, "B3" => 3, "C4" => 4, "C5" => 5,
        "C6" => 6, "D7" => 7, "E8" => 8, "F9" => 9
    );
    return isset($map[$grade]) ? $map[$grade] : 0;
}

function waec_grade_scale(){
    return array("A1","B2","B3","C4","C5","C6","D7","E8","F9");
}

function waec_gender_pair_text($maleCount, $femaleCount){
    $male = (int)$maleCount;
    $female = (int)$femaleCount;
    if($male === 0 && $female === 0){
        return "-";
    }
    return $male."/".$female;
}

function waec_candidate_col($headerMap){
    $keys = array("candidateno","candidatenumber","candidateid","indexnumber","examnumber","candno","studentid","userid","id");
    foreach($keys as $k){
        if(isset($headerMap[$k])) return $headerMap[$k];
    }
    return 0;
}

function waec_name_col($headerMap){
    $keys = array("studentname","candidatename","fullname","name","candidate","student");
    foreach($keys as $k){
        if(isset($headerMap[$k])) return $headerMap[$k];
    }
    return 1;
}

function waec_gender_col($headerMap){
    $keys = array("gender","sex");
    foreach($keys as $k){
        if(isset($headerMap[$k])) return $headerMap[$k];
    }
    return -1;
}

function waec_subject_col($headerMap){
    $keys = array("subject","subjectname","course");
    foreach($keys as $k){
        if(isset($headerMap[$k])) return $headerMap[$k];
    }
    return -1;
}

function waec_grade_col($headerMap){
    $keys = array("grade","result","scoregrade");
    foreach($keys as $k){
        if(isset($headerMap[$k])) return $headerMap[$k];
    }
    return -1;
}

function waec_build_header_map($headerRow){
    $map = array();
    foreach($headerRow as $i => $cell){
        $key = waec_key($cell);
        if($key !== ""){
            $map[$key] = $i;
        }
    }
    return $map;
}

function waec_ensure_tables($con){
    $sqlUploads = "CREATE TABLE IF NOT EXISTS tblwaecupload (
        uploadid VARCHAR(80) NOT NULL PRIMARY KEY,
        originalname VARCHAR(255) NOT NULL,
        storedname VARCHAR(255) NOT NULL,
        fileext VARCHAR(10) NOT NULL,
        totalrows INT NOT NULL DEFAULT 0,
        totalstudents INT NOT NULL DEFAULT 0,
        notes TEXT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        uploadedby VARCHAR(60) NOT NULL,
        branchid VARCHAR(60) NULL,
        datetimeentry DATETIME NOT NULL
    )";
    mysqli_query($con, $sqlUploads);
    $colRes = mysqli_query($con, "SHOW COLUMNS FROM tblwaecupload LIKE 'withheldrows'");
    if(!$colRes || mysqli_num_rows($colRes) === 0){
        mysqli_query($con, "ALTER TABLE tblwaecupload ADD COLUMN withheldrows INT NOT NULL DEFAULT 0");
    }
    $colRes2 = mysqli_query($con, "SHOW COLUMNS FROM tblwaecupload LIKE 'withheldstudents'");
    if(!$colRes2 || mysqli_num_rows($colRes2) === 0){
        mysqli_query($con, "ALTER TABLE tblwaecupload ADD COLUMN withheldstudents INT NOT NULL DEFAULT 0");
    }

    $sqlResults = "CREATE TABLE IF NOT EXISTS tblwaecresult (
        resultid BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        uploadid VARCHAR(80) NOT NULL,
        candidate_no VARCHAR(120) NULL,
        student_name VARCHAR(255) NULL,
        gender VARCHAR(10) NULL DEFAULT '',
        subject_name VARCHAR(120) NOT NULL,
        grade VARCHAR(2) NOT NULL,
        grade_point INT NOT NULL,
        datetimeentry DATETIME NOT NULL,
        INDEX idx_uploadid (uploadid),
        INDEX idx_grade (grade),
        INDEX idx_candidate (candidate_no)
    )";
    mysqli_query($con, $sqlResults);

    $sqlWithheld = "CREATE TABLE IF NOT EXISTS tblwaecwithheld (
        withheldid BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        uploadid VARCHAR(80) NOT NULL,
        candidate_no VARCHAR(120) NULL,
        student_name VARCHAR(255) NULL,
        gender VARCHAR(10) NULL DEFAULT '',
        subject_name VARCHAR(120) NOT NULL,
        grade VARCHAR(2) NOT NULL DEFAULT 'W',
        datetimeentry DATETIME NOT NULL,
        INDEX idx_uploadid (uploadid),
        INDEX idx_subject (subject_name),
        INDEX idx_candidate (candidate_no)
    )";
    mysqli_query($con, $sqlWithheld);

    $genderColResult = mysqli_query($con, "SHOW COLUMNS FROM tblwaecresult LIKE 'gender'");
    if(!$genderColResult || mysqli_num_rows($genderColResult) === 0){
        mysqli_query($con, "ALTER TABLE tblwaecresult ADD COLUMN gender VARCHAR(10) NULL DEFAULT '' AFTER student_name");
    }
    $genderColWithheld = mysqli_query($con, "SHOW COLUMNS FROM tblwaecwithheld LIKE 'gender'");
    if(!$genderColWithheld || mysqli_num_rows($genderColWithheld) === 0){
        mysqli_query($con, "ALTER TABLE tblwaecwithheld ADD COLUMN gender VARCHAR(10) NULL DEFAULT '' AFTER student_name");
    }
}

function waec_identity_key($candidateNo, $studentName){
    $cand = preg_replace('/\s+/', '', trim((string)$candidateNo));
    $name = strtoupper(preg_replace('/\s+/', ' ', trim((string)$studentName)));
    return $cand !== "" ? "CAND:".$cand : "NAME:".$name;
}

function waec_apply_candidate_gender($records, $withheldRecords){
    $genderMap = array();

    foreach($records as $row){
        $gender = waec_normalize_gender(isset($row["gender"]) ? $row["gender"] : "");
        if($gender === ""){
            continue;
        }
        $genderMap[waec_identity_key($row["candidate_no"], $row["student_name"])] = $gender;
    }

    foreach($withheldRecords as $row){
        $gender = waec_normalize_gender(isset($row["gender"]) ? $row["gender"] : "");
        if($gender === ""){
            continue;
        }
        $genderMap[waec_identity_key($row["candidate_no"], $row["student_name"])] = $gender;
    }

    foreach($records as $idx => $row){
        $key = waec_identity_key($row["candidate_no"], $row["student_name"]);
        $records[$idx]["gender"] = isset($genderMap[$key]) ? $genderMap[$key] : waec_normalize_gender(isset($row["gender"]) ? $row["gender"] : "");
    }

    foreach($withheldRecords as $idx => $row){
        $key = waec_identity_key($row["candidate_no"], $row["student_name"]);
        $withheldRecords[$idx]["gender"] = isset($genderMap[$key]) ? $genderMap[$key] : waec_normalize_gender(isset($row["gender"]) ? $row["gender"] : "");
    }

    return array("rows" => $records, "withheld_rows" => $withheldRecords);
}

function waec_build_filter_where($con, $uploadId, $focusSubject = "", $focusGender = "", $focusGrade = "", $tableType = "result", $applyGrade = true){
    $uploadIdEsc = mysqli_real_escape_string($con, (string)$uploadId);
    $parts = array("uploadid='$uploadIdEsc'");

    if(trim((string)$focusSubject) !== ""){
        $subjectEsc = mysqli_real_escape_string($con, trim((string)$focusSubject));
        $parts[] = "subject_name='$subjectEsc'";
    }

    $genderFilter = trim((string)$focusGender);
    if($genderFilter === "Male" || $genderFilter === "Female"){
        $parts[] = "gender='$genderFilter'";
    }

    if($applyGrade){
        if($tableType === "result"){
            if(in_array($focusGrade, array("A1","B2","B3","C4","C5","C6","D7","E8","F9"))){
                $gradeEsc = mysqli_real_escape_string($con, (string)$focusGrade);
                $parts[] = "grade='$gradeEsc'";
            } elseif(in_array($focusGrade, array("X","W","*","P"))){
                $parts[] = "1=0";
            }
        } elseif($tableType === "withheld" && in_array($focusGrade, array("X","W","*","P"))){
            $gradeEsc = mysqli_real_escape_string($con, (string)$focusGrade);
            $parts[] = "grade='$gradeEsc'";
        } elseif($tableType === "withheld" && waec_grade($focusGrade) !== ""){
            $parts[] = "1=0";
        }
    }

    return implode(" AND ", $parts);
}

function waec_filtered_student_count($con, $uploadId, $subject = "", $gender = "", $grade = ""){
    $graded = waec_build_filter_where($con, $uploadId, $subject, $gender, $grade, "result", true);
    $statuses = waec_build_filter_where($con, $uploadId, $subject, $gender, $grade, "withheld", true);
    $identity = "CASE WHEN TRIM(COALESCE(candidate_no,''))<>'' THEN CONCAT('CAND:',TRIM(candidate_no)) ELSE CONCAT('NAME:',UPPER(TRIM(student_name))) END";
    $res = mysqli_query($con, "SELECT COUNT(*) AS total FROM (
        SELECT $identity AS candidate_key FROM tblwaecresult WHERE $graded
        UNION
        SELECT $identity AS candidate_key FROM tblwaecwithheld WHERE $statuses
    ) candidates");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row ? (int)$row['total'] : 0;
}

function waec_csv_cell($value){
    $value = (string)$value;
    // Keep imported text from executing as a spreadsheet formula.
    return preg_match('/^[\\s]*[=+@-]/u', $value) ? "'".$value : $value;
}

function waec_extract_saved_file_records_for_gender($filePath, $ext){
    $records = array();
    $withheldRecords = array();
    $ext = strtolower(trim((string)$ext));

    if(!is_file($filePath)){
        return array("rows" => $records, "withheld_rows" => $withheldRecords);
    }

    if($ext === "xlsx"){
        $xlsxData = waec_read_xlsx_rows($filePath);
        if(isset($xlsxData["error"]) && trim((string)$xlsxData["error"]) === ""){
            $extracted = waec_extract_rows($xlsxData["rows"]);
            $records = isset($extracted["rows"]) ? $extracted["rows"] : array();
            $withheldRecords = isset($extracted["withheld_rows"]) ? $extracted["withheld_rows"] : array();
            if(count($records) === 0 && count($withheldRecords) === 0){
                $listing = waec_extract_listing_rows_from_xlsx($filePath);
                $records = isset($listing["rows"]) ? $listing["rows"] : array();
                $withheldRecords = isset($listing["withheld_rows"]) ? $listing["withheld_rows"] : array();
            }
        }
    } elseif($ext === "csv"){
        $csvRows = waec_read_csv_rows($filePath);
        $extracted = waec_extract_rows($csvRows);
        $records = isset($extracted["rows"]) ? $extracted["rows"] : array();
        $withheldRecords = isset($extracted["withheld_rows"]) ? $extracted["withheld_rows"] : array();
        if(count($records) === 0 && count($withheldRecords) === 0){
            $listing = waec_extract_listing_rows_from_rows($csvRows);
                        $parseError = $listing["error"] ?? "";
            $records = isset($listing["rows"]) ? $listing["rows"] : array();
            $withheldRecords = isset($listing["withheld_rows"]) ? $listing["withheld_rows"] : array();
        }
    } elseif($ext === "pdf"){
        $pdfExtract = waec_extract_listing_rows_from_pdf($filePath);
        $records = isset($pdfExtract["rows"]) ? $pdfExtract["rows"] : array();
        $withheldRecords = isset($pdfExtract["withheld_rows"]) ? $pdfExtract["withheld_rows"] : array();
    }

    if(count($records) > 0 || count($withheldRecords) > 0){
        $ded = waec_deduplicate_records($records, $withheldRecords);
                    if(!empty($ded["error"])){
                        $insertUpload = false;
                        $message = "<div style='color:red'>".waec_esc($ded["error"])."</div>";
                    }
        $filled = waec_apply_candidate_gender($ded["rows"], $ded["withheld_rows"]);
        $records = $filled["rows"];
        $withheldRecords = $filled["withheld_rows"];
    }

    return array("rows" => $records, "withheld_rows" => $withheldRecords);
}

function waec_backfill_upload_gender($con, $uploadId, $storedName, $fileExt){
    $filePath = __DIR__.DIRECTORY_SEPARATOR."uploads".DIRECTORY_SEPARATOR.basename((string)$storedName);
    $parsed = waec_extract_saved_file_records_for_gender($filePath, $fileExt);
    $genderRows = array();

    foreach($parsed["rows"] as $row){
        $gender = waec_normalize_gender(isset($row["gender"]) ? $row["gender"] : "");
        if($gender === ""){
            continue;
        }
        $genderRows[waec_identity_key($row["candidate_no"], $row["student_name"])] = array(
            "candidate_no" => trim((string)$row["candidate_no"]),
            "student_name" => trim((string)$row["student_name"]),
            "gender" => $gender
        );
    }

    foreach($parsed["withheld_rows"] as $row){
        $gender = waec_normalize_gender(isset($row["gender"]) ? $row["gender"] : "");
        if($gender === ""){
            continue;
        }
        $genderRows[waec_identity_key($row["candidate_no"], $row["student_name"])] = array(
            "candidate_no" => trim((string)$row["candidate_no"]),
            "student_name" => trim((string)$row["student_name"]),
            "gender" => $gender
        );
    }

    if(count($genderRows) === 0){
        return false;
    }

    $uploadIdEsc = mysqli_real_escape_string($con, (string)$uploadId);
    foreach($genderRows as $row){
        $genderEsc = mysqli_real_escape_string($con, $row["gender"]);
        $cand = trim((string)$row["candidate_no"]);
        $name = trim((string)$row["student_name"]);

        if($cand !== ""){
            $candEsc = mysqli_real_escape_string($con, $cand);
            $matchSql = "candidate_no='$candEsc'";
        } else {
            $nameEsc = mysqli_real_escape_string($con, $name);
            $matchSql = "(candidate_no='' OR candidate_no IS NULL) AND student_name='$nameEsc'";
        }

        mysqli_query($con, "UPDATE tblwaecresult SET gender='$genderEsc'
            WHERE uploadid='$uploadIdEsc' AND $matchSql AND (gender='' OR gender IS NULL)");
        mysqli_query($con, "UPDATE tblwaecwithheld SET gender='$genderEsc'
            WHERE uploadid='$uploadIdEsc' AND $matchSql AND (gender='' OR gender IS NULL)");
    }

    return true;
}

function waec_read_csv_rows($tmpName){
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

function waec_read_xlsx_rows($tmpName){
    require_once "simplexlsx.class.php";
    $oldLevel = error_reporting();
    error_reporting($oldLevel & ~E_DEPRECATED & ~E_NOTICE);
    $xlsx = new SimpleXLSX($tmpName);
    error_reporting($oldLevel);
    if(!$xlsx->success()){
        return array("error" => $xlsx->error(), "rows" => array());
    }
    return array("error" => "", "rows" => $xlsx->rows());
}

function waec_command_exists($command){
    $command = trim((string)$command);
    if($command === ""){
        return false;
    }
    $isWin = strtoupper(substr(PHP_OS, 0, 3)) === "WIN";
    $checkCmd = $isWin ? "where ".$command : "command -v ".$command;
    @exec($checkCmd, $out, $code);
    return ($code === 0);
}

function waec_extract_text_from_pdf($pdfPath, &$error = ""){
    $error = "";
    $text = "";

    $autoload = __DIR__.DIRECTORY_SEPARATOR."vendor".DIRECTORY_SEPARATOR."autoload.php";
    if(is_file($autoload)){
        include_once $autoload;
        if(class_exists("\\Smalot\\PdfParser\\Parser")){
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($pdfPath);
                $text = (string)$pdf->getText();
                if(trim($text) !== ""){
                    return $text;
                }
            } catch(Exception $e){
                $error = "PDF parser library failed: ".$e->getMessage();
            }
        }
    }

    if(waec_command_exists("pdftotext")){
        $tmpTxt = tempnam(sys_get_temp_dir(), "waec_pdf_");
        if($tmpTxt !== false){
            $cmd = "pdftotext -layout ".escapeshellarg($pdfPath)." ".escapeshellarg($tmpTxt);
            @exec($cmd, $out, $code);
            if($code === 0 && is_file($tmpTxt)){
                $text = (string)@file_get_contents($tmpTxt);
                @unlink($tmpTxt);
                if(trim($text) !== ""){
                    return $text;
                }
            } else {
                @unlink($tmpTxt);
            }
        }
    }

    if($error === ""){
        $error = "No PDF text extractor available. Install pdftotext or a PHP PDF parser library.";
    }
    return "";
}

function waec_is_index_number($value){
    $v = preg_replace('/\s+/', '', trim((string)$value));
    return (bool)preg_match('/^\d{7,}$/', $v);
}

function waec_is_result_fragment_line($line){
    $l = strtoupper(trim((string)$line));
    if($l === ""){
        return false;
    }
    // Hyphenated surnames (including wrapped "LARYEA-" / "SOWAH") are not results.
    if(preg_match('/\s-|-\s|-(?:A1|B2|B3|C4|C5|C6|D7|E8|F9|X|W|P|\*)(?:\s|,|$)/', $l)){
        return true;
    }
    if(substr($l, -1) === '-'){
        foreach(waec_pdf_subject_alias_map() as $aliases){
            foreach($aliases as $alias) if(rtrim(substr($l,0,-1)) === strtoupper($alias)) return true;
        }
    }
    if(preg_match('/\b(A1|B2|B3|C4|C5|C6|D7|E8|F9|X|W)\b/', $l)){
        return true;
    }
    return false;
}

function waec_is_noise_line($line){
    $l = strtoupper(trim((string)$line));
    if($l === ""){
        return true;
    }
    if(stripos($l, "WAEC RESULTS LISTING") !== false) return true;
    if(stripos($l, "THE WEST AFRICAN") !== false) return true;
    if(stripos($l, "INDEX NUMBER") !== false) return true;
    if(preg_match('/^DATE\s*:/', $l)) return true;
    if(stripos($l, "TOTAL NUMBER OF CANDIDATES") !== false) return true;
    if(stripos($l, "COMMITTED TO EXCELLENCE") !== false) return true;
    if(stripos($l, "HTTP://") !== false || stripos($l, "HTTPS://") !== false) return true;
    if(preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4},?\s+\d{1,2}:\d{2}/', $l)) return true;
    if(preg_match('/^\d+\/\d+$/', $l)) return true;
    return false;
}

function waec_clean_name_fragment($line){
    $l = trim((string)$line);
    $l = preg_replace('/\b(MALE|FEMALE)\s*\d{1,2}\/\d{1,2}\/\d{2,4}/i', '', $l);
    $l = preg_replace('/\b(MALE|FEMALE)\b/i', '', $l);
    $l = preg_replace('/\d{1,2}\/\d{1,2}\/\d{2,4}/', '', $l);
    $l = preg_replace('/\s+/', ' ', $l);
    return trim($l, " ,.");
}

function waec_is_name_fragment($line){
    $l = waec_clean_name_fragment($line);
    if($l === "" || waec_is_noise_line($l)){
        return false;
    }
    if(waec_is_result_fragment_line($l)){
        return false;
    }
    if(preg_match('/^\d+$/', $l)){
        return false;
    }
    return (bool)preg_match('/[A-Za-z]/', $l);
}

function waec_resolve_subject_name($subject){
    $s = waec_normalize_subject($subject);
    $s = preg_replace('/\s+/', ' ', trim($s, " ,.-"));
    if($s === ""){
        return "";
    }

    // Common WAEC subjects. If noisy prefix/suffix appears, choose the canonical subject hit.
    $known = array(
        "ENGLISH LANG",
        "SOCIAL STUDIES",
        "MATHEMATICS(CORE)",
        "INTEGRATED SCIENCE",
        "ECONOMICS",
        "CERAMICS",
        "GENERAL KNOWLEDGE IN ART",
        "GRAPHIC DESIGN",
        "CHRISTIAN RELIGIOUS STUDIES",
        "GOVERNMENT",
        "HISTORY",
        "TWI",
        "BIOLOGY",
        "CLOTHING & TEXTILES",
        "MANAGEMENT IN LIVING",
        "GEOGRAPHY",
        "FRENCH",
        "LITERATURE IN ENGLISH",
        "FOODS & NUTRITION",
        "MATHEMATICS(ELECT)",
        "BUSINESS MANAGEMENT",
        "FINANCIAL ACCOUNTING",
        "CHEMISTRY",
        "GENERAL AGRICULTURE",
        "ANIMAL HUSBANDRY",
        "INFORMATION & COMMUNICATION TECHNOLOGY"
    );

    $best = "";
    foreach($known as $k){
        if($s === $k){
            return $k;
        }
        if(strpos($s, $k) !== false){
            if(strlen($k) > strlen($best)){
                $best = $k;
            }
        }
    }
    if($best !== ""){
        return $best;
    }

    // Reject obvious garbage fragments that should never be a subject.
    if(preg_match('/TOTAL NUMBER OF CANDIDATES|WAEC|COMMITTED TO EXCELLENCE/i', $s)){
        return "";
    }
    if(!preg_match('/[A-Z]/', $s)){
        return "";
    }
    return $s;
}

function waec_pdf_subject_alias_map(){
    return array(
        "ENGLISH LANG" => array("ENGLISH LANG"),
        "SOCIAL STUDIES" => array("SOCIAL STUDIES"),
        "MATHEMATICS(CORE)" => array("MATHEMATICS(CORE)"),
        "INTEGRATED SCIENCE" => array("INTEGRATED SCIENCE"),
        "ECONOMICS" => array("ECONOMICS"),
        "CERAMICS" => array("CERAMICS"),
        "GENERAL KNOWLEDGE IN ART" => array("GEN KNOW IN ART","GENERAL KNOWLEDGE IN ART"),
        "GRAPHIC DESIGN" => array("GRAPHIC DESIGN"),
        "CHRISTIAN RELIGIOUS STUDIES" => array("CHRISTIAN REL STUD","CHRISTIAN RELIGIOUS STUDIES"),
        "GOVERNMENT" => array("GOVERNMENT"),
        "HISTORY" => array("HISTORY"),
        "TWI" => array("TWI(AKUAPEM)","TWI(ASANTE)","TWI"),
        "BIOLOGY" => array("BIOLOGY"),
        "CLOTHING & TEXTILES" => array("CLOTHING & TEXTILES"),
        "MANAGEMENT IN LIVING" => array("MGT IN LIVING","MANAGEMENT IN LIVING"),
        "GEOGRAPHY" => array("GEOGRAPHY"),
        "FRENCH" => array("FRENCH"),
        "LITERATURE IN ENGLISH" => array("LIT IN ENGLISH","LITERATURE IN ENGLISH"),
        "FOODS & NUTRITION" => array("FOODS & NUTRITION"),
        "MATHEMATICS(ELECT)" => array("MATHEMATICS(ELECT)"),
        "BUSINESS MANAGEMENT" => array("BUSINESS MANAGEMENT"),
        "FINANCIAL ACCOUNTING" => array("FINANCIAL ACCOUNTING"),
        "CHEMISTRY" => array("CHEMISTRY"),
        "GENERAL AGRICULTURE" => array("GENERAL AGRICULTURE"),
        "ANIMAL HUSBANDRY" => array("ANIMAL HUSBANDRY"),
        "INFORMATION & COMMUNICATION TECHNOLOGY" => array("INFO COMM TECHNOLOGY","INFORMATION & COMMUNICATION TECHNOLOGY")
    );
}

function waec_extract_listing_rows_from_text($rawText){
    $all = array();
    $withheld = array();

    $text = str_replace("\r", "\n", (string)$rawText);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $lines = preg_split('/\n+/', $text);
    if(!is_array($lines) || count($lines) === 0){
        return array("error" => "", "rows" => $all, "withheld_rows" => $withheld);
    }

    $currentCandidate = "";
    $currentName = "";
    $currentGender = "";
    $nameParts = array();
    $resultsBuffer = "";
    $hasResultForCurrent = false;
    $prevLine = "";

    $flushCandidate = function() use (&$all, &$withheld, &$currentCandidate, &$currentName, &$currentGender, &$nameParts, &$resultsBuffer, &$hasResultForCurrent){
        if($currentName === ""){
            $nameBuilt = trim(implode(" ", $nameParts));
            $nameBuilt = preg_replace('/\s+/', ' ', $nameBuilt);
            $nameBuilt = preg_replace('/-\s+/', '-', $nameBuilt);
            $currentName = $nameBuilt;
        }
        if($currentCandidate === "" || trim($resultsBuffer) === ""){
            $currentCandidate = "";
            $currentName = "";
            $nameParts = array();
            $resultsBuffer = "";
            $hasResultForCurrent = false;
            return;
        }
        $candidateWithheld = array();
        $parsed = waec_parse_subject_grades_from_text($resultsBuffer, $candidateWithheld);
        $parsedSubjects = array();
        $nameOut = trim($currentName) === "" ? "Unknown" : trim($currentName);
        foreach($parsed as $p){
            $parsedSubjects[$p["subject_name"]] = true;
            $all[] = array(
                "candidate_no" => $currentCandidate,
                "student_name" => $nameOut,
                "gender" => $currentGender,
                "subject_name" => waec_normalize_subject($p["subject_name"]),
                "grade" => $p["grade"],
                "grade_point" => $p["grade_point"]
            );
        }
        foreach($candidateWithheld as $w){
            $parsedSubjects[$w["subject_name"]] = true;
            $withheld[] = array(
                "candidate_no" => $currentCandidate,
                "student_name" => $nameOut,
                "gender" => $currentGender,
                "subject_name" => waec_normalize_subject($w["subject_name"]),
                "grade" => $w["grade"]
            );
        }

        // PDF listings sometimes contain subject labels without trailing grade tokens.
        // Capture those as withheld (W) when no grade/status already exists for that subject.
        $upperBuffer = strtoupper($resultsBuffer);
        $aliasMap = waec_pdf_subject_alias_map();
        foreach($aliasMap as $canonical => $aliases){
            $foundMention = false;
            foreach($aliases as $alias){
                $aliasUp = strtoupper($alias);
                $aliasQ = preg_quote($aliasUp, '/');
                if(preg_match('/\b'.$aliasQ.'\b\s*-/', $upperBuffer)){
                    $foundMention = true;
                    break;
                }
            }
            if($foundMention && !isset($parsedSubjects[$canonical])){
                $withheld[] = array(
                    "candidate_no" => $currentCandidate,
                    "student_name" => $nameOut,
                    "gender" => $currentGender,
                    "subject_name" => $canonical,
                    "grade" => "W"
                );
                $parsedSubjects[$canonical] = true;
            }
        }

        // Extra safety for Core Math in PDF: if mentioned but still unresolved, mark as withheld.
        if(!isset($parsedSubjects["MATHEMATICS(CORE)"]) && strpos($upperBuffer, "MATHEMATICS(CORE)") !== false){
            $withheld[] = array(
                "candidate_no" => $currentCandidate,
                "student_name" => $nameOut,
                "gender" => $currentGender,
                "subject_name" => "MATHEMATICS(CORE)",
                "grade" => "W"
            );
            $parsedSubjects["MATHEMATICS(CORE)"] = true;
        }

        $currentCandidate = "";
        $currentName = "";
        $currentGender = "";
        $nameParts = array();
        $resultsBuffer = "";
        $hasResultForCurrent = false;
    };

    foreach($lines as $lineRaw){
        $line = trim((string)$lineRaw);
        if($line === "" || waec_is_noise_line($line)){
            // Do not carry a school/page heading forward as a candidate name.
            $prevLine = "";
            continue;
        }

        if(preg_match('/\b(\d{10})\b/', $line, $m)){
            $flushCandidate();
            $currentCandidate = trim($m[1]);
            $currentName = "";
            $currentGender = waec_extract_gender_hint($line);
            $nameParts = array();
            $resultsBuffer = "";
            $hasResultForCurrent = false;
            if($prevLine !== "" && waec_is_name_fragment($prevLine)){
                $nameParts[] = waec_clean_name_fragment($prevLine);
            }
            $rest = trim(str_replace($m[1], "", $line));
            if($rest !== ""){
                $restGender = waec_extract_gender_hint($rest);
                if($restGender !== ""){
                    $currentGender = $restGender;
                }
                if(waec_is_result_fragment_line($rest)){
                    $resultsBuffer .= " ".$rest;
                    $hasResultForCurrent = true;
                } elseif(waec_is_name_fragment($rest)){
                    $nameParts[] = waec_clean_name_fragment($rest);
                }
            }
            $prevLine = $line;
            continue;
        }

        if($currentCandidate !== ""){
            $lineGender = waec_extract_gender_hint($line);
            if($currentGender === "" && $lineGender !== ""){
                $currentGender = $lineGender;
            }
            if(waec_is_result_fragment_line($line)){
                $resultsBuffer .= " ".$line;
                $hasResultForCurrent = true;
            }
            // A wrapped given name can share a line with gender/date of birth.
            // Validate the cleaned fragment instead of dropping the whole line.
            if(!$hasResultForCurrent && waec_is_name_fragment($line)){
                $nameParts[] = waec_clean_name_fragment($line);
            }
        }
        $prevLine = $line;
    }
    $flushCandidate();

    return array("error" => "", "rows" => $all, "withheld_rows" => $withheld);
}

function waec_extract_listing_rows_from_pdf($pdfPath){
    $err = "";
    $txt = waec_extract_text_from_pdf($pdfPath, $err);
    if(trim($txt) === ""){
        return array("error" => $err, "rows" => array(), "withheld_rows" => array());
    }
    return waec_extract_listing_rows_from_text($txt);
}

function waec_parse_subject_grades_from_text($rawText, &$withheldRows = null){
    $records = array();
    if(!is_array($withheldRows)){
        $withheldRows = array();
    }
    $text = strtoupper((string)$rawText);
    $text = str_replace(array("\r", "\n", "\t"), " ", $text);
    $text = preg_replace('/\s+/', ' ', $text);

    if($text === ""){
        return $records;
    }

    preg_match_all('/([A-Z0-9()\/&\-\.\s]+?)\s*-\s*(A1|B2|B3|C4|C5|C6|D7|E8|F9|X|W)\b/', $text, $m, PREG_SET_ORDER);
    foreach($m as $row){
        $subject = trim($row[1], " ,.-");
        $grade = trim($row[2]);
        $subject = waec_resolve_subject_name($subject);
        if($subject === ""){
            continue;
        }
        if(in_array($grade, array("X","W","*","P"))){
            $withheldRows[] = array(
                "subject_name" => waec_normalize_subject($subject),
                "grade" => $grade
            );
            continue;
        }
        $records[] = array(
            "subject_name" => waec_normalize_subject($subject),
            "grade" => $grade,
            "grade_point" => waec_points($grade)
        );
    }
    return $records;
}

function waec_extract_listing_rows_from_rows($rows){
    return waec_listing_blocks($rows);
}

function waec_extract_listing_rows_from_xlsx($tmpName){
    require_once __DIR__."/simplexlsx.class.php";
    $xlsx = new SimpleXLSX($tmpName);
    if(!$xlsx->success()) return array("error"=>$xlsx->error(), "rows"=>array(), "withheld_rows"=>array());
    $rows = array();
    foreach($xlsx->sheetNames() as $sheetId=>$sheetName){
        foreach($xlsx->rows($sheetId) as $row) $rows[]=$row;
    }
    return waec_listing_blocks($rows);
}

function waec_extract_rows($rows){
    $records = array();
    $withheld = array();
    if(!is_array($rows) || count($rows) < 2){
        return array("rows" => $records, "withheld_rows" => $withheld);
    }

    $header = $rows[0];
    $headerMap = waec_build_header_map($header);
    $candidateCol = waec_candidate_col($headerMap);
    $nameCol = waec_name_col($headerMap);
    $genderCol = waec_gender_col($headerMap);
    $subjectCol = waec_subject_col($headerMap);
    $gradeCol = waec_grade_col($headerMap);

    $longFormat = ($subjectCol >= 0 && $gradeCol >= 0);

    if($longFormat){
        for($i = 1; $i < count($rows); $i++){
            $row = $rows[$i];
            $candidate = isset($row[$candidateCol]) ? trim((string)$row[$candidateCol]) : "";
            $name = isset($row[$nameCol]) ? trim((string)$row[$nameCol]) : "";
            if($candidate === "" && $name === "") continue;
            $gender = ($genderCol >= 0 && isset($row[$genderCol])) ? waec_normalize_gender($row[$genderCol]) : "";
            $subject = isset($row[$subjectCol]) ? trim((string)$row[$subjectCol]) : "";
            $gradeRaw = isset($row[$gradeCol]) ? waec_grade_or_withheld($row[$gradeCol]) : "";
            if($subject !== "" && in_array($gradeRaw, array("X","W","*","P"))){
                $withheld[] = array(
                    "candidate_no" => $candidate,
                    "student_name" => $name,
                    "gender" => $gender,
                    "subject_name" => waec_normalize_subject($subject),
                    "grade" => $gradeRaw
                );
                continue;
            }
            $grade = waec_grade($gradeRaw);
            if($subject !== "" && $grade !== ""){
                $records[] = array(
                    "candidate_no" => $candidate,
                    "student_name" => $name,
                    "gender" => $gender,
                    "subject_name" => waec_normalize_subject($subject),
                    "grade" => $grade,
                    "grade_point" => waec_points($grade)
                );
            }
        }
        return array("rows" => $records, "withheld_rows" => $withheld);
    }

    $skipCols = array($candidateCol => true, $nameCol => true);
    if($genderCol >= 0){
        $skipCols[$genderCol] = true;
    }
    for($i = 1; $i < count($rows); $i++){
        $row = $rows[$i];
        $candidate = isset($row[$candidateCol]) ? trim((string)$row[$candidateCol]) : "";
        $name = isset($row[$nameCol]) ? trim((string)$row[$nameCol]) : "";
        if($candidate === "" && $name === "") continue;
        $gender = ($genderCol >= 0 && isset($row[$genderCol])) ? waec_normalize_gender($row[$genderCol]) : "";

        for($c = 0; $c < count($header); $c++){
            if(isset($skipCols[$c])) continue;
            $subject = isset($header[$c]) ? trim((string)$header[$c]) : "";
            if($subject === "") continue;

            $subjectKey = waec_key($subject);
            if(in_array($subjectKey, array("position","remark","total","aggregate","gender","sex","age","class","form","index","number"))){
                continue;
            }

            $value = isset($row[$c]) ? $row[$c] : "";
            $gradeRaw = waec_grade_or_withheld($value);
            if(in_array($gradeRaw, array("X","W","*","P"))){
                $withheld[] = array(
                    "candidate_no" => $candidate,
                    "student_name" => $name,
                    "gender" => $gender,
                    "subject_name" => waec_normalize_subject($subject),
                    "grade" => $gradeRaw
                );
                continue;
            }
            $grade = waec_grade($gradeRaw);
            if($grade !== ""){
                $records[] = array(
                    "candidate_no" => $candidate,
                    "student_name" => $name,
                    "gender" => $gender,
                    "subject_name" => waec_normalize_subject($subject),
                    "grade" => $grade,
                    "grade_point" => waec_points($grade)
                );
            }
        }
    }

    return array("rows" => $records, "withheld_rows" => $withheld);
}

function waec_deduplicate_records($records, $withheldRecords){
    $conflict = waec_import_conflict($records, $withheldRecords);
    if($conflict !== "") return array("error"=>$conflict, "rows"=>array(), "withheld_rows"=>array());
    $dedupRecords = array();
    $dedupWithheld = array();
    $gradedKeySet = array();

    foreach($records as $r){
        $cand = trim((string)$r["candidate_no"]);
        $name = trim((string)$r["student_name"]);
        $sub = waec_normalize_subject($r["subject_name"]);
        $r["gender"] = waec_normalize_gender(isset($r["gender"]) ? $r["gender"] : "");
        $baseKey = waec_identity_key($cand, $name);
        $rowKey = $baseKey."|".$sub;
        if(isset($gradedKeySet[$rowKey])){
            continue;
        }
        $gradedKeySet[$rowKey] = true;
        $r["subject_name"] = $sub;
        $dedupRecords[] = $r;
    }

    $withheldSeen = array();
    foreach($withheldRecords as $w){
        $cand = trim((string)$w["candidate_no"]);
        $name = trim((string)$w["student_name"]);
        $sub = waec_normalize_subject($w["subject_name"]);
        $grade = strtoupper(trim((string)$w["grade"]));
        $w["gender"] = waec_normalize_gender(isset($w["gender"]) ? $w["gender"] : "");
        if(!in_array($grade, array("X","W","*","P"))){
            continue;
        }
        $baseKey = waec_identity_key($cand, $name);
        $subKey = $baseKey."|".$sub;
        if(isset($gradedKeySet[$subKey])){
            // If the student has an actual grade for the same subject, ignore status row.
            continue;
        }
        $rowKey = $subKey."|".$grade;
        if(isset($withheldSeen[$rowKey])){
            continue;
        }
        $withheldSeen[$rowKey] = true;
        $w["subject_name"] = $sub;
        $w["grade"] = $grade;
        $dedupWithheld[] = $w;
    }

    return array("rows" => $dedupRecords, "withheld_rows" => $dedupWithheld);
}

require_once __DIR__."/waec-listing-utils.php";

waec_ensure_tables($con);
wac_ensure_metadata($con);

$message = "";
$currentUploadId = isset($_GET["uploadid"]) ? trim($_GET["uploadid"]) : "";

if(isset($_POST["upload_waec"])){
    $batchMetadataError = '';
    try{ $batchMetadata = wac_metadata($_POST); }
    catch(InvalidArgumentException $e){ $batchMetadataError = $e->getMessage(); }
    if($batchMetadataError !== ''){
        $message = "<div style='color:red'>".waec_esc($batchMetadataError)."</div>";
    }elseif(!isset($_FILES["waec_file"]) || $_FILES["waec_file"]["error"] !== UPLOAD_ERR_OK){
        $message = "<div style='color:red'>Upload failed. Please choose a valid file.</div>";
    } else {
        $originalName = $_FILES["waec_file"]["name"];
        $tmpName = $_FILES["waec_file"]["tmp_name"];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = array("xlsx","csv","pdf");

        if(!in_array($ext, $allowed)){
            $message = "<div style='color:red'>Only .xlsx, .csv, and .pdf files are allowed.</div>";
        } else {
            $uploadId = "WAEC".date("YmdHis")."_".substr(md5(uniqid("", true)), 0, 8);
            $storedName = "waec_".date("YmdHis")."_".preg_replace('/[^a-zA-Z0-9._-]/', "_", $originalName);
            $targetPath = "uploads/".$storedName;
            $notes = "";
            $totalRows = 0;
            $totalStudents = 0;
            $withheldRowsCount = 0;
            $withheldStudentsCount = 0;
            $status = "active";

            if(!move_uploaded_file($tmpName, $targetPath)){
                $message = "<div style='color:red'>Unable to store uploaded file.</div>";
            } else {
                $records = array();
                $withheldRecords = array();
                $parseError = "";
                if($ext === "xlsx"){
                    $xlsxData = waec_read_xlsx_rows($targetPath);
                    if($xlsxData["error"] !== ""){
                        $parseError = $xlsxData["error"];
                        $message = "<div style='color:red'>Excel parse error: ".waec_esc($xlsxData["error"])."</div>";
                    } else {
                        $extracted = waec_extract_rows($xlsxData["rows"]);
                        $records = $extracted["rows"];
                        $withheldRecords = $extracted["withheld_rows"];
                        if(count($records) === 0 && count($withheldRecords) === 0){
                            $listing = waec_extract_listing_rows_from_xlsx($targetPath);
                            $parseError = $listing["error"] ?? "";
                            if($listing["error"] !== ""){
                                $message = "<div style='color:red'>WAEC listing parse error: ".waec_esc($listing["error"])."</div>";
                            } else {
                                $records = $listing["rows"];
                                $withheldRecords = isset($listing["withheld_rows"]) ? $listing["withheld_rows"] : array();
                            }
                        }
                    }
                } elseif($ext === "csv"){
                    $csvRows = waec_read_csv_rows($targetPath);
                    $extracted = waec_extract_rows($csvRows);
                    $records = $extracted["rows"];
                    $withheldRecords = $extracted["withheld_rows"];
                    if(count($records) === 0 && count($withheldRecords) === 0){
                        $listing = waec_extract_listing_rows_from_rows($csvRows);
                        $records = $listing["rows"];
                        $withheldRecords = isset($listing["withheld_rows"]) ? $listing["withheld_rows"] : array();
                    }
                } else {
                    $pdfExtract = waec_extract_listing_rows_from_pdf($targetPath);
                    if(isset($pdfExtract["error"]) && trim((string)$pdfExtract["error"]) !== ""){
                        $status = "pdf_uploaded";
                        $notes = "PDF saved but extraction failed. ".$pdfExtract["error"];
                    } else {
                        $records = isset($pdfExtract["rows"]) ? $pdfExtract["rows"] : array();
                        $withheldRecords = isset($pdfExtract["withheld_rows"]) ? $pdfExtract["withheld_rows"] : array();
                        if(count($records) === 0 && count($withheldRecords) === 0){
                            $status = "pdf_uploaded";
                            $notes = "PDF saved, but no valid WAEC subject-grade rows were extracted.";
                        }
                    }
                }

                $insertUpload = ($parseError === "");
                if(!$insertUpload) $message = "<div style='color:red'>".waec_esc($parseError)."</div>";
                if(count($records) > 0 || count($withheldRecords) > 0){
                    $ded = waec_deduplicate_records($records, $withheldRecords);
                    $records = $ded["rows"];
                    $withheldRecords = $ded["withheld_rows"];
                    $genderApplied = waec_apply_candidate_gender($records, $withheldRecords);
                    $records = $genderApplied["rows"];
                    $withheldRecords = $genderApplied["withheld_rows"];
                }
                if(count($records) === 0 && count($withheldRecords) === 0 && $status === "active"){
                    $status = "empty";
                    $notes = "No valid WAEC grades (A1-F9) were detected. Check headers and grade values.";
                }

                if(count($records) > 0 || count($withheldRecords) > 0){
                    $candidateSet = array();
                    foreach($records as $r){
                        $key = trim((string)$r["candidate_no"]);
                        if($key === "") $key = trim((string)$r["student_name"]);
                        if($key !== "") $candidateSet[$key] = true;
                    }
                    foreach($withheldRecords as $wr){
                        $key = trim((string)$wr["candidate_no"]);
                        if($key === "") $key = trim((string)$wr["student_name"]);
                        if($key !== "") $candidateSet[$key] = true;
                    }
                    $totalRows = count($records);
                    $totalStudents = count($candidateSet);
                }
                if(count($withheldRecords) > 0){
                    $withheldSet = array();
                    $absentRowsCount = 0;
                    $withheldOnlyRowsCount = 0;
                    foreach($withheldRecords as $wr){
                        $wKey = trim((string)$wr["candidate_no"]);
                        if($wKey === ""){
                            $wKey = trim((string)$wr["student_name"]);
                        }
                        if($wKey !== ""){
                            $withheldSet[$wKey] = true;
                        }
                        $g = isset($wr["grade"]) ? strtoupper(trim((string)$wr["grade"])) : "";
                        if($g === "X"){
                            $absentRowsCount++;
                        } elseif($g === "W"){
                            $withheldOnlyRowsCount++;
                        }
                    }
                    $withheldRowsCount = count($withheldRecords);
                    $withheldStudentsCount = count($withheldSet);
                    if($notes === ""){
                        $notes = "Special statuses detected: Absent (X): ".$absentRowsCount.", Withheld (W): ".$withheldOnlyRowsCount.".";
                    }
                }

                if(!$insertUpload && is_file($targetPath)) unlink($targetPath);
                if($insertUpload){
                    $uploadIdEsc = mysqli_real_escape_string($con, $uploadId);
                    $origEsc = mysqli_real_escape_string($con, $originalName);
                    $storedEsc = mysqli_real_escape_string($con, $storedName);
                    $extEsc = mysqli_real_escape_string($con, $ext);
                    $notesEsc = mysqli_real_escape_string($con, $notes);
                    $statusEsc = mysqli_real_escape_string($con, $status);
                    $userEsc = mysqli_real_escape_string($con, $_SESSION["USERID"]);
                    $branchEsc = mysqli_real_escape_string($con, $_SESSION["BRANCHID"]);

                    $examYear = $batchMetadata['exam_year'];
                    $examSitting = mysqli_real_escape_string($con, $batchMetadata['exam_sitting']);
                    $batchLabel = mysqli_real_escape_string($con, $batchMetadata['batch_label']);
                    $sqlUpload = "INSERT INTO tblwaecupload(uploadid,originalname,storedname,fileext,totalrows,totalstudents,withheldrows,withheldstudents,notes,status,uploadedby,branchid,datetimeentry,exam_year,exam_sitting,batch_label)
                        VALUES('$uploadIdEsc','$origEsc','$storedEsc','$extEsc',$totalRows,$totalStudents,$withheldRowsCount,$withheldStudentsCount,'$notesEsc','$statusEsc','$userEsc','$branchEsc',NOW(),$examYear,'$examSitting','$batchLabel')";
                    if(!mysqli_query($con, $sqlUpload)){
                        $message = "<div style='color:red'>Failed to save upload metadata: ".waec_esc(mysqli_error($con))."</div>";
                    } else {
                        if(count($records) > 0 || count($withheldRecords) > 0){
                            mysqli_begin_transaction($con);
                            $ok = true;
                            foreach($records as $rec){
                                $candEsc = mysqli_real_escape_string($con, $rec["candidate_no"]);
                                $nameEsc = mysqli_real_escape_string($con, $rec["student_name"]);
                                $genderEsc = mysqli_real_escape_string($con, waec_normalize_gender(isset($rec["gender"]) ? $rec["gender"] : ""));
                                $subEsc = mysqli_real_escape_string($con, $rec["subject_name"]);
                                $gradeEsc = mysqli_real_escape_string($con, $rec["grade"]);
                                $points = (int)$rec["grade_point"];
                                $sqlR = "INSERT INTO tblwaecresult(uploadid,candidate_no,student_name,gender,subject_name,grade,grade_point,datetimeentry)
                                    VALUES('$uploadIdEsc','$candEsc','$nameEsc','$genderEsc','$subEsc','$gradeEsc',$points,NOW())";
                                if(!mysqli_query($con, $sqlR)){
                                    $ok = false;
                                    break;
                                }
                            }
                            if($ok && count($withheldRecords) > 0){
                                foreach($withheldRecords as $wrec){
                                    $candEsc = mysqli_real_escape_string($con, $wrec["candidate_no"]);
                                    $nameEsc = mysqli_real_escape_string($con, $wrec["student_name"]);
                                    $genderEsc = mysqli_real_escape_string($con, waec_normalize_gender(isset($wrec["gender"]) ? $wrec["gender"] : ""));
                                    $subEsc = mysqli_real_escape_string($con, $wrec["subject_name"]);
                                    $gradeW = isset($wrec["grade"]) ? strtoupper(trim((string)$wrec["grade"])) : "W";
                                    if(!in_array($gradeW, array("W","X","*","P"))){
                                        $gradeW = "W";
                                    }
                                    $gradeEsc = mysqli_real_escape_string($con, $gradeW);
                                    $sqlW = "INSERT INTO tblwaecwithheld(uploadid,candidate_no,student_name,gender,subject_name,grade,datetimeentry)
                                        VALUES('$uploadIdEsc','$candEsc','$nameEsc','$genderEsc','$subEsc','$gradeEsc',NOW())";
                                    if(!mysqli_query($con, $sqlW)){
                                        $ok = false;
                                        break;
                                    }
                                }
                            }

                            if($ok){
                                mysqli_commit($con);
                                $absentRowsCount = 0;
                                $withheldOnlyRowsCount = 0;
                                foreach($withheldRecords as $wr){
                                    $g = isset($wr["grade"]) ? strtoupper(trim((string)$wr["grade"])) : "";
                                    if($g === "X"){
                                        $absentRowsCount++;
                                    } elseif($g === "W"){
                                        $withheldOnlyRowsCount++;
                                    }
                                }
                                $message = "<div style='color:green'>WAEC data uploaded and analyzed successfully. Records: ".$totalRows.", Students: ".$totalStudents.", Absent (X): ".$absentRowsCount.", Withheld (W): ".$withheldOnlyRowsCount.".</div>";
                            } else {
                                mysqli_rollback($con);
                                $message = "<div style='color:red'>Failed to save WAEC records: ".waec_esc(mysqli_error($con))."</div>";
                            }
                        } else {
                            $message = "<div style='color:#0f766e'>Upload saved. ".waec_esc($notes)."</div>";
                        }
                        $currentUploadId = $uploadId;
                    }
                }
            }
        }
    }
}

if(isset($_POST["delete_upload"]) && isset($_POST["delete_uploadid"])){
    $deleteUploadId = trim($_POST["delete_uploadid"]);
    if($deleteUploadId !== ""){
        $deleteUploadIdEsc = mysqli_real_escape_string($con, $deleteUploadId);
        $fileRes = mysqli_query($con, "SELECT storedname FROM tblwaecupload WHERE uploadid='$deleteUploadIdEsc' LIMIT 1");
        $storedName = "";
        if($fileRes && mysqli_num_rows($fileRes) > 0){
            $fileRow = mysqli_fetch_array($fileRes, MYSQLI_ASSOC);
            $storedName = isset($fileRow["storedname"]) ? $fileRow["storedname"] : "";
        }

        mysqli_begin_transaction($con);
        $okDelete = true;
        if(!mysqli_query($con, "DELETE FROM tblwaecresult WHERE uploadid='$deleteUploadIdEsc'")){
            $okDelete = false;
        }
        if($okDelete && !mysqli_query($con, "DELETE FROM tblwaecwithheld WHERE uploadid='$deleteUploadIdEsc'")){
            $okDelete = false;
        }
        if($okDelete && !mysqli_query($con, "DELETE FROM tblwaecupload WHERE uploadid='$deleteUploadIdEsc'")){
            $okDelete = false;
        }

        if($okDelete){
            mysqli_commit($con);
            if($storedName !== ""){
                $safeStored = basename($storedName);
                $filePath = "uploads/".$safeStored;
                if(is_file($filePath)){
                    @unlink($filePath);
                }
            }
            if($currentUploadId === $deleteUploadId){
                $currentUploadId = "";
            }
            $message = "<div style='color:green'>Upload deleted successfully.</div>";
        } else {
            mysqli_rollback($con);
            $message = "<div style='color:red'>Failed to delete upload: ".waec_esc(mysqli_error($con))."</div>";
        }
    }
}

if(isset($_GET["export"]) && isset($_GET["uploadid"])){
    $exportType = trim($_GET["export"]);
    $expUpload = mysqli_real_escape_string($con, trim($_GET["uploadid"]));
    $expFocusSubject = isset($_GET["focus_subject"]) ? trim($_GET["focus_subject"]) : "";
    $expFocusGrade = isset($_GET["focus_grade"]) ? trim($_GET["focus_grade"]) : "";
    $expDistributionSubject = isset($_GET["distribution_subject"]) ? trim($_GET["distribution_subject"]) : "";
    $expFocusGenderInput = isset($_GET["focus_gender"]) ? trim($_GET["focus_gender"]) : "";
    $expFocusGender = waec_normalize_gender($expFocusGenderInput);

    $uRes = mysqli_query($con, "SELECT * FROM tblwaecupload WHERE uploadid='$expUpload' LIMIT 1");
    if($uRes && mysqli_num_rows($uRes) > 0){
        $upload = mysqli_fetch_array($uRes, MYSQLI_ASSOC);

        if($exportType === "filtered_dashboard_pdf"){
            include("company.php");
            require("fpdf181/fpdf.php");

            $expWhere = waec_build_filter_where($con, $upload["uploadid"], $expFocusSubject, $expFocusGender, $expFocusGrade, "result", true);
            $expGradeDistributionSubject = ($expFocusSubject !== "") ? $expFocusSubject : $expDistributionSubject;
            $expGradeDistributionWhere = waec_build_filter_where($con, $upload["uploadid"], $expGradeDistributionSubject, $expFocusGender, $expFocusGrade, "result", true);
            $expGradeDistributionLabel = ($expGradeDistributionSubject !== "") ? $expGradeDistributionSubject : "All Subjects";

            $logoPath = "";
            if(isset($_Logo) && trim((string)$_Logo) !== ""){
                $candidateLogo = basename($_Logo);
                $paths = array("uploads/".$candidateLogo, "images/".$candidateLogo, "images/logo/".$candidateLogo);
                foreach($paths as $p){
                    if(is_file($p)){ $logoPath = $p; break; }
                }
            }

            $sumExp = mysqli_query($con, "SELECT
                COUNT(*) AS total_results,
                COUNT(DISTINCT CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END) AS total_students,
                COUNT(DISTINCT subject_name) AS total_subjects,
                SUM(CASE WHEN grade IN ('A1','B2','B3','C4','C5','C6') THEN 1 ELSE 0 END) AS pass_count,
                SUM(CASE WHEN grade IN ('D7','E8','F9') THEN 1 ELSE 0 END) AS fail_count,
                ROUND(AVG(grade_point),2) AS avg_point
                FROM tblwaecresult WHERE $expWhere");
            $sum = array("total_results"=>0,"total_students"=>0,"total_subjects"=>0,"pass_count"=>0,"fail_count"=>0,"avg_point"=>0);
            if($sumExp && $r=mysqli_fetch_array($sumExp, MYSQLI_ASSOC)){ $sum = $r; }
            $sum["total_students"] = waec_filtered_student_count($con, $upload["uploadid"], $expFocusSubject, $expFocusGender, $expFocusGrade);
            $overallPassExp = ((int)$sum["total_results"] > 0) ? round((((int)$sum["pass_count"]*100)/(int)$sum["total_results"]),2) : 0;

            $readExp = mysqli_query($con, "SELECT
                COUNT(*) AS students_total,
                SUM(CASE WHEN eng_credit=1 THEN 1 ELSE 0 END) AS english_credit_count,
                SUM(CASE WHEN math_credit=1 THEN 1 ELSE 0 END) AS math_credit_count,
                SUM(CASE WHEN sci_social_credit=1 THEN 1 ELSE 0 END) AS science_social_credit_count,
                SUM(CASE WHEN credits_total>=5 THEN 1 ELSE 0 END) AS credits5_count,
                SUM(CASE WHEN credits_total>=6 THEN 1 ELSE 0 END) AS credits6_count,
                SUM(CASE WHEN eng_credit=1 AND math_credit=1 AND sci_social_credit=1 THEN 1 ELSE 0 END) AS core_ready_count
                FROM (
                    SELECT
                        CASE WHEN candidate_no<>'' THEN candidate_no ELSE CONCAT('NAME:',student_name) END AS cand_key,
                        MAX(CASE WHEN subject_name IN ('ENGLISH LANG','ENGLISH LANGUAGE','ENGLISH') AND grade_point BETWEEN 1 AND 6 THEN 1 ELSE 0 END) AS eng_credit,
                        MAX(CASE WHEN subject_name IN ('MATHEMATICS(CORE)','MATHEMATICS (CORE)','CORE MATHEMATICS','CORE MATHS') AND grade_point BETWEEN 1 AND 6 THEN 1 ELSE 0 END) AS math_credit,
                        MAX(CASE WHEN subject_name IN ('INTEGRATED SCIENCE','SOCIAL STUDIES') AND grade_point<=6 THEN 1 ELSE 0 END) AS sci_social_credit,
                        SUM(CASE WHEN grade_point<=6 THEN 1 ELSE 0 END) AS credits_total
                    FROM tblwaecresult
                    WHERE $expWhere
                    GROUP BY CASE WHEN candidate_no<>'' THEN candidate_no ELSE CONCAT('NAME:',student_name) END
                ) student_rollup");
            $read = array("students_total"=>0,"english_credit_count"=>0,"math_credit_count"=>0,"science_social_credit_count"=>0,"credits5_count"=>0,"credits6_count"=>0,"core_ready_count"=>0);
            if($readExp && $re=mysqli_fetch_array($readExp, MYSQLI_ASSOC)){ $read = $re; }

            $gradeExp = mysqli_query($con, "SELECT grade, COUNT(*) AS total,
                ROUND((COUNT(*)*100.0)/NULLIF((SELECT COUNT(*) FROM tblwaecresult WHERE $expGradeDistributionWhere),0),2) AS pct
                FROM tblwaecresult WHERE $expGradeDistributionWhere
                GROUP BY grade
                ORDER BY FIELD(grade,'A1','B2','B3','C4','C5','C6','D7','E8','F9')");
            $overallGradeDistributionPassExp = 0;
            $gradeDistributionSummaryExp = mysqli_query($con, "SELECT
                COUNT(*) AS total_results,
                SUM(CASE WHEN grade IN ('A1','B2','B3','C4','C5','C6') THEN 1 ELSE 0 END) AS pass_count
                FROM tblwaecresult WHERE $expGradeDistributionWhere");
            if($gradeDistributionSummaryExp && $gdse = mysqli_fetch_array($gradeDistributionSummaryExp, MYSQLI_ASSOC)){
                $overallGradeDistributionPassExp = ((int)$gdse["total_results"] > 0) ? round((((int)$gdse["pass_count"] * 100) / (int)$gdse["total_results"]), 2) : 0;
            }

            $subjectExp = mysqli_query($con, "SELECT subject_name, COUNT(*) AS total, ROUND(AVG(grade_point),2) AS avg_point,
                ROUND((SUM(CASE WHEN grade_point<=6 THEN 1 ELSE 0 END)*100.0)/NULLIF(COUNT(*),0),2) AS pass_pct
                FROM tblwaecresult
                WHERE $expWhere
                GROUP BY subject_name
                ORDER BY pass_pct DESC, total DESC");

            $pdf = new FPDF();
            $pdf->AddPage();
            if($logoPath !== ""){ $pdf->Image($logoPath, 10, 8, 20); }
            $pdf->SetFont("Arial","B",14);
            $pdf->Cell(0,8,"Filtered WAEC Dashboard Report",0,1,"C");
            $pdf->SetFont("Arial","",10);
            $pdf->Cell(0,6,strtoupper($_CompanyName),0,1,"C");
            $pdf->Ln(2);
            $pdf->Cell(40,6,"Upload ID:",0,0); $pdf->Cell(0,6,$upload["uploadid"],0,1);
            $pdf->Cell(40,6,"Subject Filter:",0,0); $pdf->Cell(0,6,($expFocusSubject!==""?$expFocusSubject:"All Subjects"),0,1);
            $pdf->Cell(40,6,"Grade Filter:",0,0); $pdf->Cell(0,6,($expFocusGrade!==""?$expFocusGrade:"All Grades"),0,1);
            $pdf->Cell(40,6,"Gender Filter:",0,0); $pdf->Cell(0,6,($expFocusGender!==""?$expFocusGender:"All Genders"),0,1);
            $pdf->Cell(40,6,"Grade Dist. Subject:",0,0); $pdf->Cell(0,6,$expGradeDistributionLabel,0,1);
            $pdf->Ln(2);

            $pdf->SetFont("Arial","B",11);
            $pdf->Cell(0,7,"Dashboard Snapshot",0,1);
            $pdf->SetFont("Arial","",9);
            $pdf->Cell(70,6,"Total Results:",0,0); $pdf->Cell(25,6,$sum["total_results"],0,0);
            $pdf->Cell(55,6,"Total Students:",0,0); $pdf->Cell(0,6,$sum["total_students"],0,1);
            $pdf->Cell(70,6,"Credit (A1-C6):",0,0); $pdf->Cell(25,6,$sum["pass_count"],0,0);
            $pdf->Cell(55,6,"Below credit (D7-F9):",0,0); $pdf->Cell(0,6,$sum["fail_count"],0,1);
            $pdf->Cell(70,6,"Credit rate (A1-C6):",0,0); $pdf->Cell(25,6,$overallPassExp."%",0,0);
            $pdf->Cell(55,6,"Average Point:",0,0); $pdf->Cell(0,6,$sum["avg_point"],0,1);
            $pdf->Ln(2);

            $pdf->SetFont("Arial","B",11);
            $pdf->Cell(0,7,"Core and Credit Indicators",0,1);
            $pdf->SetFont("Arial","B",9);
            $pdf->Cell(95,7,"Metric",1,0,"C");
            $pdf->Cell(25,7,"Count",1,0,"C");
            $pdf->Cell(25,7,"%",1,1,"C");
            $pdf->SetFont("Arial","",9);
            $st = (int)$read["students_total"];
            $rm = array(
                array("English Credit (A1-C6)",(int)$read["english_credit_count"]),
                array("Core Math Credit (A1-C6)",(int)$read["math_credit_count"]),
                array("Science/Social Credit (A1-C6)",(int)$read["science_social_credit_count"]),
                array("At Least 5 Credits",(int)$read["credits5_count"]),
                array("At Least 6 Credits",(int)$read["credits6_count"]),
                array("Core credits (Eng+Math+Sci/Soc)",(int)$read["core_ready_count"])
            );
            foreach($rm as $m){
                $rp = $st>0 ? round(($m[1]*100)/$st,2) : 0;
                $pdf->Cell(95,7,$m[0],1,0);
                $pdf->Cell(25,7,$m[1],1,0,"C");
                $pdf->Cell(25,7,$rp."%",1,1,"C");
            }
            $pdf->Ln(2);

            $pdf->SetFont("Arial","B",11);
            $pdf->Cell(0,7,"Grade Distribution (".$expGradeDistributionLabel.")",0,1);
            $pdf->SetFont("Arial","B",9);
            $pdf->Cell(50,7,"Grade",1,0,"C");
            $pdf->Cell(35,7,"Count",1,0,"C");
            $pdf->Cell(35,7,"Percent",1,1,"C");
            $pdf->SetFont("Arial","",9);
            if($gradeExp && mysqli_num_rows($gradeExp)>0){
                while($g=mysqli_fetch_array($gradeExp, MYSQLI_ASSOC)){
                    $pdf->Cell(50,7,$g["grade"],1,0,"C");
                    $pdf->Cell(35,7,$g["total"],1,0,"C");
                    $pdf->Cell(35,7,$g["pct"]."%",1,1,"C");
                }
                $pdf->SetFont("Arial","B",9);
                $pdf->Cell(50,7,"Pass Rate (A1-C6)",1,0);
                $pdf->Cell(70,7,$overallGradeDistributionPassExp."%",1,1,"C");
                $pdf->SetFont("Arial","",9);
            } else {
                $pdf->Cell(120,7,"No grade data for selected filter.",1,1,"C");
            }
            $pdf->Ln(2);

            $pdf->SetFont("Arial","B",11);
            $pdf->Cell(0,7,"Subject Performance",0,1);
            $drawSubjectHeader = function() use ($pdf){
                $pdf->SetFont("Arial","B",9);
                $pdf->Cell(75,7,"Subject",1,0,"C");
                $pdf->Cell(20,7,"Entries",1,0,"C");
                $pdf->Cell(25,7,"Avg Point",1,0,"C");
                $pdf->Cell(20,7,"Pass %",1,1,"C");
                $pdf->SetFont("Arial","",8);
            };
            $drawSubjectHeader();
            $pdf->SetFont("Arial","",8);
            if($subjectExp && mysqli_num_rows($subjectExp)>0){
                while($s=mysqli_fetch_array($subjectExp, MYSQLI_ASSOC)){
                    if($pdf->GetY() > 265){
                        $pdf->AddPage();
                        if($logoPath !== ""){ $pdf->Image($logoPath, 10, 8, 20); }
                        $pdf->Ln(18);
                        $pdf->SetFont("Arial","B",11);
                        $pdf->Cell(0,7,"Subject Performance (cont.)",0,1);
                        $drawSubjectHeader();
                    }
                    $pdf->Cell(75,7,substr($s["subject_name"],0,35),1,0);
                    $pdf->Cell(20,7,$s["total"],1,0,"C");
                    $pdf->Cell(25,7,$s["avg_point"],1,0,"C");
                    $pdf->Cell(20,7,$s["pass_pct"]."%",1,1,"C");
                }
            } else {
                $pdf->Cell(140,7,"No subject data for selected filter.",1,1,"C");
            }

            $pdf->Output("D","waec_filtered_dashboard_".$upload["uploadid"].".pdf");
            exit();
        }

        if($exportType === "student_pdf" && isset($_GET["student_key"])){
            $studentKey = trim($_GET["student_key"]);
            $studentKeyEsc = mysqli_real_escape_string($con, $studentKey);
            $studentFilterExport = "CASE WHEN candidate_no<>'' THEN candidate_no ELSE CONCAT('NAME:',student_name) END='$studentKeyEsc'";

            $sInfoRes = mysqli_query($con, "SELECT
                CASE WHEN candidate_no<>'' THEN candidate_no ELSE 'N/A' END AS candidate_no,
                CASE WHEN student_name<>'' THEN student_name ELSE 'Unknown' END AS student_name,
                COUNT(*) AS total_subjects,
                SUM(CASE WHEN grade_point<=6 THEN 1 ELSE 0 END) AS total_credits,
                MAX(CASE WHEN subject_name IN ('ENGLISH LANG','ENGLISH LANGUAGE','ENGLISH') AND grade_point BETWEEN 1 AND 6 THEN 1 ELSE 0 END) AS english_credit,
                MAX(CASE WHEN subject_name IN ('MATHEMATICS(CORE)','MATHEMATICS (CORE)','CORE MATHEMATICS','CORE MATHS') AND grade_point BETWEEN 1 AND 6 THEN 1 ELSE 0 END) AS math_credit,
                ROUND(AVG(grade_point),2) AS avg_point
                FROM (
                    SELECT uploadid,candidate_no,student_name,subject_name,grade_point FROM tblwaecresult
                    UNION ALL SELECT uploadid,candidate_no,student_name,subject_name,NULL AS grade_point FROM tblwaecwithheld
                ) student_outcomes
                WHERE uploadid='$expUpload' AND $studentFilterExport
                GROUP BY CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END, student_name
                LIMIT 1");

            if($sInfoRes && mysqli_num_rows($sInfoRes) > 0){
                include("company.php");
                require("fpdf181/fpdf.php");

                $sInfo = mysqli_fetch_array($sInfoRes, MYSQLI_ASSOC);
                $logoPath = "";
                if(isset($_Logo) && trim((string)$_Logo) !== ""){
                    $candidateLogo = basename($_Logo);
                    $paths = array("uploads/".$candidateLogo, "images/".$candidateLogo, "images/logo/".$candidateLogo);
                    foreach($paths as $p){
                        if(is_file($p)){ $logoPath = $p; break; }
                    }
                }

                $pdf = new FPDF();
                $pdf->AddPage();
                if($logoPath !== ""){
                    $pdf->Image($logoPath, 10, 8, 20);
                }
                $pdf->SetFont("Arial", "B", 14);
                $pdf->Cell(0, 8, "Student WAEC Result Analysis", 0, 1, "C");
                $pdf->SetFont("Arial", "", 10);
                $pdf->Cell(0, 6, strtoupper($_CompanyName), 0, 1, "C");
                $pdf->Ln(3);
                $pdf->Cell(55, 6, "Upload ID:", 0, 0);
                $pdf->Cell(0, 6, $upload["uploadid"], 0, 1);
                $pdf->Cell(55, 6, "Student Name:", 0, 0);
                $pdf->Cell(0, 6, $sInfo["student_name"], 0, 1);
                $pdf->Cell(55, 6, "Candidate Number:", 0, 0);
                $pdf->Cell(0, 6, $sInfo["candidate_no"], 0, 1);
                $pdf->Cell(55, 6, "Total Subjects:", 0, 0);
                $pdf->Cell(0, 6, $sInfo["total_subjects"], 0, 1);
                $pdf->Cell(55, 6, "Total Credits (A1-C6):", 0, 0);
                $pdf->Cell(0, 6, $sInfo["total_credits"], 0, 1);
                $pdf->Cell(55, 6, "Average Grade Point:", 0, 0);
                $pdf->Cell(0, 6, $sInfo["avg_point"], 0, 1);
                $pdf->Cell(55, 6, "English Credit:", 0, 0);
                $pdf->Cell(0, 6, ((int)$sInfo["english_credit"]===1 ? "Yes" : "No"), 0, 1);
                $pdf->Cell(55, 6, "Core Math Credit:", 0, 0);
                $pdf->Cell(0, 6, ((int)$sInfo["math_credit"]===1 ? "Yes" : "No"), 0, 1);
                $pdf->Ln(4);

                $pdf->SetFont("Arial", "B", 10);
                $pdf->Cell(85, 7, "Subject", 1, 0, "C");
                $pdf->Cell(30, 7, "Grade", 1, 0, "C");
                $pdf->Cell(30, 7, "Point", 1, 1, "C");
                $pdf->SetFont("Arial", "", 9);

                $sRowsRes = mysqli_query($con, "SELECT subject_name,grade,grade_point
                    FROM tblwaecresult
                    WHERE uploadid='$expUpload' AND $studentFilterExport
                    UNION ALL SELECT subject_name,grade,NULL AS grade_point FROM tblwaecwithheld
                    WHERE uploadid='$expUpload' AND $studentFilterExport
                    ORDER BY subject_name ASC");
                if($sRowsRes){
                    while($row = mysqli_fetch_array($sRowsRes, MYSQLI_ASSOC)){
                        $pdf->Cell(85, 7, substr($row["subject_name"],0,40), 1, 0);
                        $pdf->Cell(30, 7, $row["grade"], 1, 0, "C");
                        $pdf->Cell(30, 7, $row["grade_point"], 1, 1, "C");
                    }
                }

                $safeCand = preg_replace('/[^A-Za-z0-9_-]+/', '_', $sInfo["candidate_no"]);
                if($safeCand === "" || $safeCand === "N_A"){ $safeCand = "student"; }
                $pdf->Output("D", "waec_student_analysis_".$safeCand.".pdf");
                exit();
            }
        }

        if($exportType === "excel"){
            header("Content-Type: text/csv; charset=UTF-8");
            header("Content-Disposition: attachment; filename=waec_analysis_".$upload["uploadid"].".csv");
            $out = fopen("php://output", "w");
            $overallPassCsvRes = mysqli_query($con, "SELECT
                COUNT(*) AS total_rows,
                SUM(CASE WHEN grade IN ('A1','B2','B3','C4','C5','C6') THEN 1 ELSE 0 END) AS pass_rows
                FROM tblwaecresult WHERE uploadid='$expUpload'");
            $overallPassCsv = 0;
            if($overallPassCsvRes && $ov = mysqli_fetch_array($overallPassCsvRes, MYSQLI_ASSOC)){
                $totalRowsCsv = (int)$ov["total_rows"];
                $passRowsCsv = (int)$ov["pass_rows"];
                $overallPassCsv = $totalRowsCsv > 0 ? round(($passRowsCsv * 100) / $totalRowsCsv, 2) : 0;
            }
            fputcsv($out, array("Upload ID", $upload["uploadid"]));
            fputcsv($out, array("Original File", waec_csv_cell($upload["originalname"])));
            fputcsv($out, array("Uploaded At", $upload["datetimeentry"]));
            fputcsv($out, array("Total Students", $upload["totalstudents"]));
            fputcsv($out, array("Total Subject Results", $upload["totalrows"]));
            fputcsv($out, array("Credit rate (A1-C6)", $overallPassCsv."%"));
            $readinessCsv = mysqli_query($con, "SELECT
                COUNT(*) AS students_total,
                SUM(CASE WHEN eng_credit=1 THEN 1 ELSE 0 END) AS english_credit_count,
                SUM(CASE WHEN math_credit=1 THEN 1 ELSE 0 END) AS math_credit_count,
                SUM(CASE WHEN sci_social_credit=1 THEN 1 ELSE 0 END) AS science_social_credit_count,
                SUM(CASE WHEN credits_total>=5 THEN 1 ELSE 0 END) AS credits5_count,
                SUM(CASE WHEN eng_credit=1 AND math_credit=1 AND sci_social_credit=1 THEN 1 ELSE 0 END) AS core_ready_count
                FROM (
                    SELECT
                        CASE WHEN candidate_no<>'' THEN candidate_no ELSE CONCAT('NAME:',student_name) END AS cand_key,
                        MAX(CASE WHEN subject_name IN ('ENGLISH LANG','ENGLISH LANGUAGE','ENGLISH') AND grade_point BETWEEN 1 AND 6 THEN 1 ELSE 0 END) AS eng_credit,
                        MAX(CASE WHEN subject_name IN ('MATHEMATICS(CORE)','MATHEMATICS (CORE)','CORE MATHEMATICS','CORE MATHS') AND grade_point BETWEEN 1 AND 6 THEN 1 ELSE 0 END) AS math_credit,
                        MAX(CASE WHEN subject_name IN ('INTEGRATED SCIENCE','SOCIAL STUDIES') AND grade_point<=6 THEN 1 ELSE 0 END) AS sci_social_credit,
                        SUM(CASE WHEN grade_point<=6 THEN 1 ELSE 0 END) AS credits_total
                    FROM tblwaecresult WHERE uploadid='$expUpload'
                    GROUP BY CASE WHEN candidate_no<>'' THEN candidate_no ELSE CONCAT('NAME:',student_name) END
                ) rollup");
            if($readinessCsv && $rw = mysqli_fetch_array($readinessCsv, MYSQLI_ASSOC)){
                fputcsv($out, array("Core credits (Eng+Math+Sci/Soc)", $rw["core_ready_count"]));
                fputcsv($out, array("English Credit Count", $rw["english_credit_count"]));
                fputcsv($out, array("Core Math Credit Count", $rw["math_credit_count"]));
                fputcsv($out, array("Science/Social Credit Count", $rw["science_social_credit_count"]));
                fputcsv($out, array("Students with 5+ Credits", $rw["credits5_count"]));
            }
            fputcsv($out, array(""));
            fputcsv($out, array("Candidate No", "Student Name", "Gender", "Subject", "Grade", "Point"));

            $rRes = mysqli_query($con, "SELECT candidate_no,student_name,gender,subject_name,grade,grade_point
                FROM tblwaecresult WHERE uploadid='$expUpload'
                UNION ALL
                SELECT candidate_no,student_name,gender,subject_name,grade,NULL AS grade_point
                FROM tblwaecwithheld WHERE uploadid='$expUpload'
                ORDER BY student_name ASC, subject_name ASC");
            if($rRes){
                while($r = mysqli_fetch_array($rRes, MYSQLI_ASSOC)){
                    fputcsv($out, array_map("waec_csv_cell", array($r["candidate_no"], $r["student_name"], waec_display_gender($r["gender"]), $r["subject_name"], $r["grade"], $r["grade_point"])));
                }
            }
            fclose($out);
            exit();
        }

        if($exportType === "pdf"){
            include("company.php");
            require("fpdf181/fpdf.php");

            $pdf = new FPDF();
            $pdf->AddPage();
            $logoPath = "";
            if(isset($_Logo) && trim((string)$_Logo) !== ""){
                $candidateLogo = basename($_Logo);
                $paths = array(
                    "uploads/".$candidateLogo,
                    "images/".$candidateLogo,
                    "images/logo/".$candidateLogo
                );
                foreach($paths as $p){
                    if(is_file($p)){
                        $logoPath = $p;
                        break;
                    }
                }
            }
            if($logoPath !== ""){
                $pdf->Image($logoPath, 10, 8, 20);
            }
            $pdf->SetFont("Arial", "B", 14);
            $pdf->Cell(0, 8, "WAEC Results Analysis Report", 0, 1, "C");
            $pdf->SetFont("Arial", "", 10);
            $pdf->Cell(0, 6, strtoupper($_CompanyName), 0, 1, "C");
            $pdf->Ln(3);
            $pdf->Cell(50, 6, "Upload ID:", 0, 0);
            $pdf->Cell(0, 6, $upload["uploadid"], 0, 1);
            $pdf->Cell(50, 6, "Source File:", 0, 0);
            $pdf->Cell(0, 6, $upload["originalname"], 0, 1);
            $pdf->Cell(50, 6, "Uploaded At:", 0, 0);
            $pdf->Cell(0, 6, $upload["datetimeentry"], 0, 1);
            $pdf->Cell(50, 6, "Total Students:", 0, 0);
            $pdf->Cell(0, 6, $upload["totalstudents"], 0, 1);
            $pdf->Cell(50, 6, "Total Results:", 0, 0);
            $pdf->Cell(0, 6, $upload["totalrows"], 0, 1);
            $overallPassPdfRes = mysqli_query($con, "SELECT
                COUNT(*) AS total_rows,
                SUM(CASE WHEN grade IN ('A1','B2','B3','C4','C5','C6') THEN 1 ELSE 0 END) AS pass_rows
                FROM tblwaecresult WHERE uploadid='$expUpload'");
            $overallPassPdf = 0;
            $totalRowsPdf = 0;
            if($overallPassPdfRes && $ovp = mysqli_fetch_array($overallPassPdfRes, MYSQLI_ASSOC)){
                $totalRowsPdf = (int)$ovp["total_rows"];
                $passRowsPdf = (int)$ovp["pass_rows"];
                $overallPassPdf = $totalRowsPdf > 0 ? round(($passRowsPdf * 100) / $totalRowsPdf, 2) : 0;
            }
            $pdf->Cell(50, 6, "Credit rate (A1-C6):", 0, 0);
            $pdf->Cell(0, 6, $overallPassPdf."%", 0, 1);
            $pdf->Ln(4);
            $studentsTotalPdf = 0;
            $coreReadyPdf = 0;
            $credits5Pdf = 0;
            $readinessPdf = mysqli_query($con, "SELECT
                COUNT(*) AS students_total,
                SUM(CASE WHEN eng_credit=1 THEN 1 ELSE 0 END) AS english_credit_count,
                SUM(CASE WHEN math_credit=1 THEN 1 ELSE 0 END) AS math_credit_count,
                SUM(CASE WHEN sci_social_credit=1 THEN 1 ELSE 0 END) AS science_social_credit_count,
                SUM(CASE WHEN credits_total>=5 THEN 1 ELSE 0 END) AS credits5_count,
                SUM(CASE WHEN eng_credit=1 AND math_credit=1 AND sci_social_credit=1 THEN 1 ELSE 0 END) AS core_ready_count
                FROM (
                    SELECT
                        CASE WHEN candidate_no<>'' THEN candidate_no ELSE CONCAT('NAME:',student_name) END AS cand_key,
                        MAX(CASE WHEN subject_name IN ('ENGLISH LANG','ENGLISH LANGUAGE','ENGLISH') AND grade_point BETWEEN 1 AND 6 THEN 1 ELSE 0 END) AS eng_credit,
                        MAX(CASE WHEN subject_name IN ('MATHEMATICS(CORE)','MATHEMATICS (CORE)','CORE MATHEMATICS','CORE MATHS') AND grade_point BETWEEN 1 AND 6 THEN 1 ELSE 0 END) AS math_credit,
                        MAX(CASE WHEN subject_name IN ('INTEGRATED SCIENCE','SOCIAL STUDIES') AND grade_point<=6 THEN 1 ELSE 0 END) AS sci_social_credit,
                        SUM(CASE WHEN grade_point<=6 THEN 1 ELSE 0 END) AS credits_total
                    FROM tblwaecresult WHERE uploadid='$expUpload'
                    GROUP BY CASE WHEN candidate_no<>'' THEN candidate_no ELSE CONCAT('NAME:',student_name) END
                ) rollup");
            if($readinessPdf && $rr = mysqli_fetch_array($readinessPdf, MYSQLI_ASSOC)){
                $studentsTotalPdf = (int)$rr["students_total"];
                $coreReadyPdf = (int)$rr["core_ready_count"];
                $credits5Pdf = (int)$rr["credits5_count"];
                $pdf->SetFont("Arial", "B", 11);
                $pdf->Cell(0, 7, "Core and Credit Indicators", 0, 1);
                $pdf->SetFont("Arial", "", 9);
                $pdf->Cell(70, 6, "Core credits (Eng+Math+Sci/Soc):", 0, 0);
                $pdf->Cell(0, 6, $rr["core_ready_count"]." / ".$rr["students_total"], 0, 1);
                $pdf->Cell(70, 6, "Students with 5+ Credits:", 0, 0);
                $pdf->Cell(0, 6, $rr["credits5_count"], 0, 1);
                $pdf->Ln(2);
            }

            $highlightsRes = mysqli_query($con, "SELECT
                ROUND(AVG(grade_point),2) AS avg_point,
                SUM(CASE WHEN grade_point<=3 THEN 1 ELSE 0 END) AS strong_rows,
                SUM(CASE WHEN grade_point>=7 THEN 1 ELSE 0 END) AS weak_rows,
                COUNT(*) AS total_rows
                FROM tblwaecresult WHERE uploadid='$expUpload'");
            $avgPoint = 0;
            $strongPct = 0;
            $weakPct = 0;
            if($highlightsRes && $hh = mysqli_fetch_array($highlightsRes, MYSQLI_ASSOC)){
                $avgPoint = (float)$hh["avg_point"];
                $totalRowsH = (int)$hh["total_rows"];
                $strongRows = (int)$hh["strong_rows"];
                $weakRows = (int)$hh["weak_rows"];
                $strongPct = $totalRowsH > 0 ? round(($strongRows * 100) / $totalRowsH, 2) : 0;
                $weakPct = $totalRowsH > 0 ? round(($weakRows * 100) / $totalRowsH, 2) : 0;
            }

            $pdf->SetFont("Arial", "B", 11);
            $pdf->Cell(0, 7, "Grade Breakdown (A1-F9)", 0, 1);
            $pdf->SetFont("Arial", "B", 9);
            $pdf->Cell(40, 7, "Grade", 1, 0, "C");
            $pdf->Cell(40, 7, "Count", 1, 0, "C");
            $pdf->Cell(40, 7, "Percent", 1, 1, "C");
            $pdf->SetFont("Arial", "", 9);
            $gradeMap = array("A1"=>0,"B2"=>0,"B3"=>0,"C4"=>0,"C5"=>0,"C6"=>0,"D7"=>0,"E8"=>0,"F9"=>0);
            $gradeDist = mysqli_query($con, "SELECT grade, COUNT(*) AS total
                FROM tblwaecresult
                WHERE uploadid='$expUpload'
                GROUP BY grade");
            if($gradeDist){
                while($gd = mysqli_fetch_array($gradeDist, MYSQLI_ASSOC)){
                    if(isset($gradeMap[$gd["grade"]])){
                        $gradeMap[$gd["grade"]] = (int)$gd["total"];
                    }
                }
            }
            $totalGradeRows = (int)$upload["totalrows"];
            foreach($gradeMap as $gk => $gv){
                $gpct = $totalGradeRows > 0 ? round(($gv * 100) / $totalGradeRows, 2) : 0;
                $pdf->Cell(40, 7, $gk, 1, 0, "C");
                $pdf->Cell(40, 7, $gv, 1, 0, "C");
                $pdf->Cell(40, 7, $gpct."%", 1, 1, "C");
            }
            $pdf->Ln(2);

            $pdf->SetFont("Arial", "B", 11);
            $pdf->Cell(0, 7, "Important Facts", 0, 1);
            $pdf->SetFont("Arial", "B", 9);
            $pdf->Cell(90, 7, "Metric", 1, 0, "C");
            $pdf->Cell(40, 7, "Value", 1, 1, "C");
            $pdf->SetFont("Arial", "", 9);
            $overallFailPdf = $totalRowsPdf > 0 ? round(100 - (float)$overallPassPdf, 2) : 0;
            $coreReadyPctPdf = $studentsTotalPdf > 0 ? round(($coreReadyPdf * 100) / $studentsTotalPdf, 2) : 0;
            $credits5PctPdf = $studentsTotalPdf > 0 ? round(($credits5Pdf * 100) / $studentsTotalPdf, 2) : 0;
            $facts = array(
                array("Credit rate (A1-C6)", $overallPassPdf."%"),
                array("Below credit % (D7-F9)", $overallFailPdf."%"),
                array("Average Grade Point", $avgPoint),
                array("Strong Grades Share (A1-B3)", $strongPct."%"),
                array("Weak Grades Share (D7-F9)", $weakPct."%"),
                array("Core credits (Eng+Math+Sci/Soc)", $coreReadyPdf." / ".$studentsTotalPdf." (".$coreReadyPctPdf."%)"),
                array("Students with 5+ Credits", $credits5Pdf." / ".$studentsTotalPdf." (".$credits5PctPdf."%)")
            );
            foreach($facts as $f){
                $pdf->Cell(90, 7, $f[0], 1, 0);
                $pdf->Cell(40, 7, $f[1], 1, 1, "C");
            }
            $pdf->Ln(3);

            $topSubjectRes = mysqli_query($con, "SELECT subject_name,
                COUNT(*) AS total,
                ROUND((SUM(CASE WHEN grade_point<=6 THEN 1 ELSE 0 END) * 100.0) / NULLIF(COUNT(*),0),2) AS pass_pct
                FROM tblwaecresult
                WHERE uploadid='$expUpload'
                GROUP BY subject_name
                HAVING total >= 10
                ORDER BY pass_pct DESC, total DESC
                LIMIT 5");

            $bottomSubjectRes = mysqli_query($con, "SELECT subject_name,
                COUNT(*) AS total,
                ROUND((SUM(CASE WHEN grade_point<=6 THEN 1 ELSE 0 END) * 100.0) / NULLIF(COUNT(*),0),2) AS pass_pct
                FROM tblwaecresult
                WHERE uploadid='$expUpload'
                GROUP BY subject_name
                HAVING total >= 10
                ORDER BY pass_pct ASC, total DESC
                LIMIT 5");

            $pdf->SetFont("Arial", "B", 10);
            $pdf->Cell(95, 7, "Top 5 Subjects by Pass Rate", 1, 0, "C");
            $pdf->Cell(95, 7, "Bottom 5 Subjects by Pass Rate", 1, 1, "C");
            $pdf->SetFont("Arial", "B", 8);
            $pdf->Cell(70, 6, "Subject", 1, 0, "C");
            $pdf->Cell(25, 6, "Pass %", 1, 0, "C");
            $pdf->Cell(70, 6, "Subject", 1, 0, "C");
            $pdf->Cell(25, 6, "Pass %", 1, 1, "C");
            $pdf->SetFont("Arial", "", 8);

            $tops = array();
            $bots = array();
            if($topSubjectRes){
                while($tr = mysqli_fetch_array($topSubjectRes, MYSQLI_ASSOC)){ $tops[] = $tr; }
            }
            if($bottomSubjectRes){
                while($br = mysqli_fetch_array($bottomSubjectRes, MYSQLI_ASSOC)){ $bots[] = $br; }
            }
            $maxRows = max(count($tops), count($bots));
            if($maxRows === 0){ $maxRows = 1; }
            for($i=0; $i<$maxRows; $i++){
                $lt = isset($tops[$i]) ? $tops[$i] : array("subject_name"=>"","pass_pct"=>"");
                $rb = isset($bots[$i]) ? $bots[$i] : array("subject_name"=>"","pass_pct"=>"");
                $pdf->Cell(70, 6, substr($lt["subject_name"],0,35), 1, 0);
                $pdf->Cell(25, 6, ($lt["pass_pct"]!=="" ? $lt["pass_pct"]."%" : ""), 1, 0, "C");
                $pdf->Cell(70, 6, substr($rb["subject_name"],0,35), 1, 0);
                $pdf->Cell(25, 6, ($rb["pass_pct"]!=="" ? $rb["pass_pct"]."%" : ""), 1, 1, "C");
            }

            $pdf->Output("D", "waec_analysis_".$upload["uploadid"].".pdf");
            exit();
        }
    }
}

$uploads = mysqli_query($con, "SELECT * FROM tblwaecupload ORDER BY datetimeentry DESC LIMIT 20");

$summary = null;
$readiness = null;
$gradeRows = null;
$subjectRows = null;
$topSubjects = null;
$bottomSubjects = null;
$topStudents = null;
$subjectOptions = null;
$subjectCoverageRows = array();
$subjectAbsentMap = array();
$subjectWithheldMap = array();
$subjectSourceStatusMap = array();
$allStudentsCount = 0;
$absentSummary = array("absent_rows" => 0, "absent_students" => 0);
$withheldSummary = array("withheld_rows" => 0, "withheld_students" => 0);
$focusSubjectAbsentRows = 0;
$focusSubjectAbsentStudents = 0;
$focusSubjectWithheldRows = 0;
$focusSubjectWithheldStudents = 0;
$focusSubjectRegisteredStudents = 0;
$uploadSheetTotalStudents = 0;
$fullAbsenteeStudents = 0;
$integratedScienceWroteStudents = 0;
$focusSubject = isset($_GET["focus_subject"]) ? trim($_GET["focus_subject"]) : "";
$focusGrade = isset($_GET["focus_grade"]) ? trim($_GET["focus_grade"]) : "";
$distributionSubject = isset($_GET["distribution_subject"]) ? trim($_GET["distribution_subject"]) : "";
$focusGenderInput = isset($_GET["focus_gender"]) ? trim($_GET["focus_gender"]) : "";
$focusGender = waec_normalize_gender($focusGenderInput);
$studentQuery = isset($_GET["student_query"]) ? trim($_GET["student_query"]) : "";
$studentRows = null;
$studentSummary = null;
$studentMatches = array();
$absentStudentBreakdownRows = array();
$studentKeyInput = isset($_GET["student_key"]) ? trim($_GET["student_key"]) : "";
$selectedStudentKey = "";
$selectedStudentName = "";
$selectedStudentCandidate = "";
$genderCandidateBreakdown = array();
$genderPerformanceRows = array();
$genderSubjectComparison = array();
$genderStatusBreakdown = array();
$genderCandidateCounts = array("Male" => 0, "Female" => 0);
$genderPerformanceMap = array();
$subjectOptionRows = array();
$gradeDistributionPassPct = 0;
$uploadMeta = array("totalstudents" => 0, "storedname" => "", "fileext" => "");
$statementCandidateSummaryRows = array();
$statementPassBucketRows = array();
$statementGradeMatrixRows = array();
$statementCandidateTotal = 0;
$statementUnknownGenderCandidates = 0;
$statementUnknownGenderGradeRows = 0;

if($currentUploadId !== ""){
    $uploadIdEsc = mysqli_real_escape_string($con, $currentUploadId);
    $uploadMetaRes = mysqli_query($con, "SELECT totalstudents,storedname,fileext FROM tblwaecupload WHERE uploadid='$uploadIdEsc' LIMIT 1");
    if($uploadMetaRes && $um = mysqli_fetch_array($uploadMetaRes, MYSQLI_ASSOC)){
        $uploadMeta = $um;
        $uploadSheetTotalStudents = (int)$um["totalstudents"];
    }
    $genderPresenceRes = mysqli_query($con, "SELECT (
        (SELECT COUNT(*) FROM tblwaecresult WHERE uploadid='$uploadIdEsc' AND gender<>'' AND gender IS NOT NULL) +
        (SELECT COUNT(*) FROM tblwaecwithheld WHERE uploadid='$uploadIdEsc' AND gender<>'' AND gender IS NOT NULL)
    ) AS gender_rows");
    if($genderPresenceRes && $gp = mysqli_fetch_array($genderPresenceRes, MYSQLI_ASSOC)){
        if((int)$gp["gender_rows"] === 0 && !empty($uploadMeta["storedname"]) && !empty($uploadMeta["fileext"])){
            waec_backfill_upload_gender($con, $currentUploadId, $uploadMeta["storedname"], $uploadMeta["fileext"]);
        }
    }
    $analysisWhere = waec_build_filter_where($con, $currentUploadId, $focusSubject, $focusGender, $focusGrade, "result", true);
    $resultBaseWhere = waec_build_filter_where($con, $currentUploadId, $focusSubject, $focusGender, "", "result", false);
    $withheldBaseWhere = waec_build_filter_where($con, $currentUploadId, $focusSubject, $focusGender, "", "withheld", false);
    $withheldAnalysisWhere = waec_build_filter_where($con, $currentUploadId, $focusSubject, $focusGender, $focusGrade, "withheld", true);
    $gradeDistributionSubject = ($focusSubject !== "") ? $focusSubject : $distributionSubject;
    $gradeDistributionWhere = waec_build_filter_where($con, $currentUploadId, $gradeDistributionSubject, $focusGender, $focusGrade, "result", true);
    $gradeDistributionLabel = ($gradeDistributionSubject !== "") ? $gradeDistributionSubject : "All Subjects";
    $genderExpr = "CASE WHEN gender='Male' THEN 'Male' WHEN gender='Female' THEN 'Female' ELSE '' END";
    $candidateExpr = "CASE WHEN candidate_no<>'' THEN candidate_no ELSE CONCAT('NAME:',student_name) END";

    $sumRes = mysqli_query($con, "SELECT
        COUNT(*) AS total_results,
        COUNT(DISTINCT CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END) AS total_students,
        COUNT(DISTINCT subject_name) AS total_subjects,
        SUM(CASE WHEN grade IN ('A1','B2','B3','C4','C5','C6') THEN 1 ELSE 0 END) AS pass_count,
        SUM(CASE WHEN grade IN ('D7','E8','F9') THEN 1 ELSE 0 END) AS fail_count,
        ROUND(AVG(grade_point),2) AS avg_point
        FROM tblwaecresult WHERE $analysisWhere");
    if($sumRes){
        $summary = mysqli_fetch_array($sumRes, MYSQLI_ASSOC);
    }

    $allStudentsRes = mysqli_query($con, "SELECT COUNT(*) AS total_students FROM (
        SELECT DISTINCT CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END AS cand_key
        FROM tblwaecresult
        WHERE uploadid='$uploadIdEsc'
        UNION
        SELECT DISTINCT CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END AS cand_key
        FROM tblwaecwithheld
        WHERE uploadid='$uploadIdEsc'
    ) all_cands");
    if($allStudentsRes && $asr = mysqli_fetch_array($allStudentsRes, MYSQLI_ASSOC)){
        $allStudentsCount = (int)$asr["total_students"];
    }
    $absentRes = mysqli_query($con, "SELECT
        COUNT(*) AS absent_rows,
        COUNT(DISTINCT CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END) AS absent_students
        FROM tblwaecwithheld
        WHERE ".waec_build_filter_where($con, $currentUploadId, "", $focusGender, "X", "withheld", true));
    if($absentRes && $ar = mysqli_fetch_array($absentRes, MYSQLI_ASSOC)){
        $absentSummary = $ar;
    }
    $withheldRes = mysqli_query($con, "SELECT
        COUNT(*) AS withheld_rows,
        COUNT(DISTINCT CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END) AS withheld_students
        FROM tblwaecwithheld
        WHERE ".waec_build_filter_where($con, $currentUploadId, "", $focusGender, "W", "withheld", true));
    if($withheldRes && $wr = mysqli_fetch_array($withheldRes, MYSQLI_ASSOC)){
        $withheldSummary = $wr;
    }
    if($focusSubject !== ""){
        $focusAbsentRes = mysqli_query($con, "SELECT
            COUNT(*) AS absent_rows,
            COUNT(DISTINCT CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END) AS absent_students
            FROM tblwaecwithheld
            WHERE ".waec_build_filter_where($con, $currentUploadId, $focusSubject, $focusGender, "X", "withheld", true));
        if($focusAbsentRes && $fr = mysqli_fetch_array($focusAbsentRes, MYSQLI_ASSOC)){
            $focusSubjectAbsentRows = (int)$fr["absent_rows"];
            $focusSubjectAbsentStudents = (int)$fr["absent_students"];
        }
        $focusWithheldRes = mysqli_query($con, "SELECT
            COUNT(*) AS withheld_rows,
            COUNT(DISTINCT CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END) AS withheld_students
            FROM tblwaecwithheld
            WHERE ".waec_build_filter_where($con, $currentUploadId, $focusSubject, $focusGender, "W", "withheld", true));
        if($focusWithheldRes && $fw = mysqli_fetch_array($focusWithheldRes, MYSQLI_ASSOC)){
            $focusSubjectWithheldRows = (int)$fw["withheld_rows"];
            $focusSubjectWithheldStudents = (int)$fw["withheld_students"];
        }
        $focusSubjectRegisteredStudents = $focusSubjectAbsentStudents + $focusSubjectWithheldStudents;
        $focusSubjectWroteRes = mysqli_query($con, "SELECT
            COUNT(DISTINCT CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END) AS wrote_students
            FROM tblwaecresult
            WHERE ".waec_build_filter_where($con, $currentUploadId, $focusSubject, $focusGender, "", "result", false));
        if($focusSubjectWroteRes && $frw = mysqli_fetch_array($focusSubjectWroteRes, MYSQLI_ASSOC)){
            $focusSubjectRegisteredStudents += (int)$frw["wrote_students"];
        }
    } else {
        $focusSubjectAbsentRows = (int)$absentSummary["absent_rows"];
        $focusSubjectAbsentStudents = (int)$absentSummary["absent_students"];
        $focusSubjectWithheldRows = (int)$withheldSummary["withheld_rows"];
        $focusSubjectWithheldStudents = (int)$withheldSummary["withheld_students"];
    }

    $readinessRes = mysqli_query($con, "SELECT
        COUNT(*) AS students_total,
        SUM(CASE WHEN eng_credit=1 THEN 1 ELSE 0 END) AS english_credit_count,
        SUM(CASE WHEN math_credit=1 THEN 1 ELSE 0 END) AS math_credit_count,
        SUM(CASE WHEN sci_social_credit=1 THEN 1 ELSE 0 END) AS science_social_credit_count,
        SUM(CASE WHEN credits_total>=5 THEN 1 ELSE 0 END) AS credits5_count,
        SUM(CASE WHEN credits_total>=6 THEN 1 ELSE 0 END) AS credits6_count,
        SUM(CASE WHEN eng_credit=1 AND math_credit=1 AND sci_social_credit=1 THEN 1 ELSE 0 END) AS core_ready_count
        FROM (
            SELECT
                CASE WHEN candidate_no<>'' THEN candidate_no ELSE CONCAT('NAME:',student_name) END AS cand_key,
                MAX(CASE WHEN subject_name IN ('ENGLISH LANG','ENGLISH LANGUAGE','ENGLISH') AND grade_point BETWEEN 1 AND 6 THEN 1 ELSE 0 END) AS eng_credit,
                MAX(CASE WHEN subject_name IN ('MATHEMATICS(CORE)','MATHEMATICS (CORE)','CORE MATHEMATICS','CORE MATHS') AND grade_point BETWEEN 1 AND 6 THEN 1 ELSE 0 END) AS math_credit,
                MAX(CASE WHEN subject_name IN ('INTEGRATED SCIENCE','SOCIAL STUDIES') AND grade_point<=6 THEN 1 ELSE 0 END) AS sci_social_credit,
                SUM(CASE WHEN grade_point<=6 THEN 1 ELSE 0 END) AS credits_total
            FROM tblwaecresult
            WHERE ".waec_build_filter_where($con, $currentUploadId, "", $focusGender, "", "result", false)."
            GROUP BY CASE WHEN candidate_no<>'' THEN candidate_no ELSE CONCAT('NAME:',student_name) END
        ) student_rollup");
    if($readinessRes){
        $readiness = mysqli_fetch_array($readinessRes, MYSQLI_ASSOC);
    }

    $gradeRows = mysqli_query($con, "SELECT grade, COUNT(*) AS total,
        ROUND((COUNT(*) * 100.0) / NULLIF((SELECT COUNT(*) FROM tblwaecresult WHERE $gradeDistributionWhere),0),2) AS pct
        FROM tblwaecresult WHERE $gradeDistributionWhere
        GROUP BY grade
        ORDER BY FIELD(grade,'A1','B2','B3','C4','C5','C6','D7','E8','F9')");
    $gradeSummaryRes = mysqli_query($con, "SELECT
        COUNT(*) AS total_results,
        SUM(CASE WHEN grade IN ('A1','B2','B3','C4','C5','C6') THEN 1 ELSE 0 END) AS pass_count
        FROM tblwaecresult WHERE $gradeDistributionWhere");
    if($gradeSummaryRes && $gsr = mysqli_fetch_array($gradeSummaryRes, MYSQLI_ASSOC)){
        $gradeDistributionPassPct = ((int)$gsr["total_results"] > 0) ? round((((int)$gsr["pass_count"] * 100) / (int)$gsr["total_results"]), 2) : 0;
    }

    $subjectRows = mysqli_query($con, "SELECT subject_name,
        COUNT(*) AS total,
        ROUND(AVG(grade_point),2) AS avg_point,
        ROUND((SUM(CASE WHEN grade_point <= 6 THEN 1 ELSE 0 END) * 100.0) / NULLIF(COUNT(*),0),2) AS pass_pct
        FROM tblwaecresult
        WHERE $analysisWhere
        GROUP BY subject_name
        ORDER BY avg_point ASC, subject_name ASC");

    $subjectCoverageRes = mysqli_query($con, "SELECT
        subject_name,
        COUNT(*) AS total_rows,
        COUNT(DISTINCT CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END) AS students_with_subject
        FROM tblwaecresult
        WHERE uploadid='$uploadIdEsc'
        GROUP BY subject_name
        ORDER BY subject_name ASC");
    if($subjectCoverageRes){
        while($sc = mysqli_fetch_array($subjectCoverageRes, MYSQLI_ASSOC)){
            $subjectCoverageRows[] = $sc;
        }
    }
    $subjectAbsentRes = mysqli_query($con, "SELECT
        subject_name,
        COUNT(DISTINCT CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END) AS absent_students
        FROM tblwaecwithheld
        WHERE uploadid='$uploadIdEsc' AND grade='X'
        GROUP BY subject_name");
    if($subjectAbsentRes){
        while($sa = mysqli_fetch_array($subjectAbsentRes, MYSQLI_ASSOC)){
            $subjectAbsentMap[$sa["subject_name"]] = (int)$sa["absent_students"];
        }
    }
    $subjectWithheldRes = mysqli_query($con, "SELECT
        subject_name,
        COUNT(DISTINCT CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END) AS withheld_students
        FROM tblwaecwithheld
        WHERE uploadid='$uploadIdEsc' AND grade='W'
        GROUP BY subject_name");
    if($subjectWithheldRes){
        while($sw = mysqli_fetch_array($subjectWithheldRes, MYSQLI_ASSOC)){
            $subjectWithheldMap[$sw["subject_name"]] = (int)$sw["withheld_students"];
        }
    }

    $sourceStatusRes = mysqli_query($con, "SELECT subject_name,
        COUNT(DISTINCT CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END) AS status_students
        FROM tblwaecwithheld WHERE uploadid='$uploadIdEsc' AND grade IN ('*','P') GROUP BY subject_name");
    if($sourceStatusRes){
        while($row = mysqli_fetch_assoc($sourceStatusRes)) $subjectSourceStatusMap[$row["subject_name"]] = (int)$row["status_students"];
    }

    $fullAbsentRes = mysqli_query($con, "SELECT COUNT(*) AS total_full_absent FROM (
        SELECT
            cand_key
        FROM (
            SELECT
                CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END AS cand_key,
                1 AS graded_rows,
                0 AS absent_rows
            FROM tblwaecresult
            WHERE ".waec_build_filter_where($con, $currentUploadId, "", $focusGender, "", "result", false)."
            UNION ALL
            SELECT
                CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END AS cand_key,
                0 AS graded_rows,
                CASE WHEN grade='X' THEN 1 ELSE 0 END AS absent_rows
            FROM tblwaecwithheld
            WHERE ".waec_build_filter_where($con, $currentUploadId, "", $focusGender, "", "withheld", false)."
        ) r
        GROUP BY cand_key
        HAVING SUM(graded_rows)=0 AND SUM(absent_rows)>=8
    ) z");
    if($fullAbsentRes && $fa = mysqli_fetch_array($fullAbsentRes, MYSQLI_ASSOC)){
        $fullAbsenteeStudents = (int)$fa["total_full_absent"];
    }

    $intSciRes = mysqli_query($con, "SELECT
        COUNT(DISTINCT CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END) AS wrote_count
        FROM tblwaecresult
        WHERE ".waec_build_filter_where($con, $currentUploadId, "INTEGRATED SCIENCE", $focusGender, "", "result", false));
    if($intSciRes && $isr = mysqli_fetch_array($intSciRes, MYSQLI_ASSOC)){
        $integratedScienceWroteStudents = (int)$isr["wrote_count"];
    }

    $absentWhere = waec_build_filter_where($con, $currentUploadId, $focusSubject, $focusGender, "X", "withheld", true);
    $absentBreakdownRes = mysqli_query($con, "SELECT
        CASE WHEN candidate_no<>'' THEN candidate_no ELSE 'N/A' END AS candidate_no,
        CASE WHEN student_name<>'' THEN student_name ELSE 'Unknown' END AS student_name,
        COUNT(*) AS absent_subject_count,
        GROUP_CONCAT(DISTINCT subject_name ORDER BY subject_name SEPARATOR ', ') AS absent_subjects
        FROM tblwaecwithheld
        WHERE $absentWhere
        GROUP BY CASE WHEN candidate_no<>'' THEN candidate_no ELSE CONCAT('NAME:',student_name) END, candidate_no, student_name
        ORDER BY absent_subject_count DESC, student_name ASC
        LIMIT 200");
    if($absentBreakdownRes){
        while($ab = mysqli_fetch_array($absentBreakdownRes, MYSQLI_ASSOC)){
            $absentStudentBreakdownRows[] = $ab;
        }
    }

    $topSubjects = mysqli_query($con, "SELECT subject_name,
        COUNT(*) AS total,
        ROUND((SUM(CASE WHEN grade_point <= 6 THEN 1 ELSE 0 END) * 100.0) / NULLIF(COUNT(*),0),2) AS pass_pct
        FROM tblwaecresult
        WHERE ".waec_build_filter_where($con, $currentUploadId, "", $focusGender, "", "result", false)."
        GROUP BY subject_name
        HAVING total >= 10
        ORDER BY pass_pct DESC, total DESC
        LIMIT 5");

    $bottomSubjects = mysqli_query($con, "SELECT subject_name,
        COUNT(*) AS total,
        ROUND((SUM(CASE WHEN grade_point <= 6 THEN 1 ELSE 0 END) * 100.0) / NULLIF(COUNT(*),0),2) AS pass_pct
        FROM tblwaecresult
        WHERE ".waec_build_filter_where($con, $currentUploadId, "", $focusGender, "", "result", false)."
        GROUP BY subject_name
        HAVING total >= 10
        ORDER BY pass_pct ASC, total DESC
        LIMIT 5");

    $topStudents = mysqli_query($con, "SELECT
        CASE WHEN candidate_no<>'' THEN candidate_no ELSE 'N/A' END AS candidate_no,
        CASE WHEN student_name<>'' THEN student_name ELSE 'Unknown' END AS student_name,
        ROUND(AVG(grade_point),2) AS avg_point,
        COUNT(*) AS subjects
        FROM tblwaecresult
        WHERE $analysisWhere
        GROUP BY CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END, student_name
        HAVING subjects > 0
        ORDER BY avg_point ASC, student_name ASC
        LIMIT 10");

    $subjectOptions = mysqli_query($con, "SELECT subject_name FROM (
        SELECT DISTINCT subject_name FROM tblwaecresult WHERE uploadid='$uploadIdEsc'
        UNION
        SELECT DISTINCT subject_name FROM tblwaecwithheld WHERE uploadid='$uploadIdEsc'
    ) s
    ORDER BY subject_name ASC");
    if($subjectOptions){
        while($opt = mysqli_fetch_array($subjectOptions, MYSQLI_ASSOC)){
            $subjectOptionRows[] = $opt;
        }
    }

    $genderCandidateRes = mysqli_query($con, "SELECT
        CASE gender_rank WHEN 2 THEN 'Male' WHEN 1 THEN 'Female' END AS gender_group,
        COUNT(*) AS total_students
        FROM (
            SELECT cand_key, MAX(gender_rank) AS gender_rank
            FROM (
                SELECT $candidateExpr AS cand_key,
                    CASE WHEN gender='Male' THEN 2 WHEN gender='Female' THEN 1 ELSE 0 END AS gender_rank
                FROM tblwaecresult
                WHERE $resultBaseWhere
                UNION ALL
                SELECT $candidateExpr AS cand_key,
                    CASE WHEN gender='Male' THEN 2 WHEN gender='Female' THEN 1 ELSE 0 END AS gender_rank
                FROM tblwaecwithheld
                WHERE $withheldBaseWhere
            ) gender_union
            GROUP BY cand_key
        ) gender_rollup
        WHERE gender_rank IN (1,2)
        GROUP BY gender_group
        ORDER BY FIELD(gender_group,'Male','Female')");
    if($genderCandidateRes){
        while($gc = mysqli_fetch_array($genderCandidateRes, MYSQLI_ASSOC)){
            $genderCandidateBreakdown[] = $gc;
            $genderCandidateCounts[$gc["gender_group"]] = (int)$gc["total_students"];
        }
    }

    $genderPerformanceRes = mysqli_query($con, "SELECT
        $genderExpr AS gender_group,
        COUNT(*) AS total_results,
        SUM(CASE WHEN grade_point<=6 THEN 1 ELSE 0 END) AS pass_count,
        ROUND((SUM(CASE WHEN grade_point<=6 THEN 1 ELSE 0 END) * 100.0) / NULLIF(COUNT(*),0),2) AS pass_pct,
        ROUND(AVG(grade_point),2) AS avg_point
        FROM tblwaecresult
        WHERE $resultBaseWhere AND gender IN ('Male','Female')
        GROUP BY gender_group
        ORDER BY FIELD(gender_group,'Male','Female')");
    if($genderPerformanceRes){
        while($gpRow = mysqli_fetch_array($genderPerformanceRes, MYSQLI_ASSOC)){
            $genderPerformanceRows[] = $gpRow;
            $genderPerformanceMap[$gpRow["gender_group"]] = $gpRow;
        }
    }

    $genderSubjectRes = mysqli_query($con, "SELECT
        subject_name,
        $genderExpr AS gender_group,
        COUNT(*) AS total_entries,
        ROUND((SUM(CASE WHEN grade_point<=6 THEN 1 ELSE 0 END) * 100.0) / NULLIF(COUNT(*),0),2) AS pass_pct
        FROM tblwaecresult
        WHERE $resultBaseWhere AND gender IN ('Male','Female')
        GROUP BY subject_name, gender_group
        ORDER BY subject_name ASC, FIELD(gender_group,'Male','Female')");
    if($genderSubjectRes){
        while($gs = mysqli_fetch_array($genderSubjectRes, MYSQLI_ASSOC)){
            $subjectName = $gs["subject_name"];
            if(!isset($genderSubjectComparison[$subjectName])){
                $genderSubjectComparison[$subjectName] = array(
                    "subject_name" => $subjectName,
                    "Male_pct" => "",
                    "Male_entries" => 0,
                    "Female_pct" => "",
                    "Female_entries" => 0,
                    "gap" => ""
                );
            }
            $group = $gs["gender_group"];
            $genderSubjectComparison[$subjectName][$group."_pct"] = $gs["pass_pct"];
            $genderSubjectComparison[$subjectName][$group."_entries"] = (int)$gs["total_entries"];
        }
        foreach($genderSubjectComparison as $subjectName => $row){
            if($row["Male_pct"] !== "" && $row["Female_pct"] !== ""){
                $genderSubjectComparison[$subjectName]["gap"] = round((float)$row["Male_pct"] - (float)$row["Female_pct"], 2);
            }
        }
    }

    $genderStatusRes = mysqli_query($con, "SELECT
        $genderExpr AS gender_group,
        SUM(CASE WHEN grade='X' THEN 1 ELSE 0 END) AS absent_rows,
        COUNT(DISTINCT CASE WHEN grade='X' THEN $candidateExpr ELSE NULL END) AS absent_students,
        SUM(CASE WHEN grade='W' THEN 1 ELSE 0 END) AS withheld_rows,
        COUNT(DISTINCT CASE WHEN grade='W' THEN $candidateExpr ELSE NULL END) AS withheld_students
        FROM tblwaecwithheld
        WHERE $withheldBaseWhere AND gender IN ('Male','Female')
        GROUP BY gender_group
        ORDER BY FIELD(gender_group,'Male','Female')");
    if($genderStatusRes){
        while($gsr = mysqli_fetch_array($genderStatusRes, MYSQLI_ASSOC)){
            $genderStatusBreakdown[] = $gsr;
        }
    }

    $statementMetricRows = array(
        "registered" => array("label" => "Registered Candidates", "Male" => 0, "Female" => 0, "Unknown" => 0, "total" => 0),
        "with_grade" => array("label" => "Candidates With Grade Rows", "Male" => 0, "Female" => 0, "Unknown" => 0, "total" => 0),
        "absent" => array("label" => "Candidates With Any X", "Male" => 0, "Female" => 0, "Unknown" => 0, "total" => 0),
        "withheld" => array("label" => "Candidates With Any W", "Male" => 0, "Female" => 0, "Unknown" => 0, "total" => 0),
        "zero_credit" => array("label" => "0 Credits (A1-C6)", "Male" => 0, "Female" => 0, "Unknown" => 0, "total" => 0),
        "five_plus" => array("label" => "5+ Credits (A1-C6)", "Male" => 0, "Female" => 0, "Unknown" => 0, "total" => 0),
        "eight_plus" => array("label" => "8+ Credits (A1-C6)", "Male" => 0, "Female" => 0, "Unknown" => 0, "total" => 0)
    );
    $statementPassBucketMap = array();
    for($bucket = 8; $bucket >= 0; $bucket--){
        $statementPassBucketMap[$bucket] = array(
            "label" => ($bucket === 8 ? "8+" : (string)$bucket),
            "Male" => 0,
            "Female" => 0,
            "Unknown" => 0,
            "total" => 0,
            "pct" => 0
        );
    }
    $statementBump = function(&$row, $genderKey){
        if(!isset($row[$genderKey])){
            $genderKey = "Unknown";
        }
        $row[$genderKey]++;
        $row["total"]++;
    };

    $statementCandidateRollupRes = mysqli_query($con, "SELECT
        candidate_rollup.cand_key,
        CASE candidate_rollup.gender_rank WHEN 2 THEN 'Male' WHEN 1 THEN 'Female' ELSE '' END AS gender_group,
        COALESCE(result_rollup.total_results, 0) AS total_results,
        COALESCE(result_rollup.credits_total, 0) AS credits_total,
        COALESCE(withheld_rollup.absent_rows, 0) AS absent_rows,
        COALESCE(withheld_rollup.withheld_rows, 0) AS withheld_rows
        FROM (
            SELECT cand_key, MAX(gender_rank) AS gender_rank
            FROM (
                SELECT $candidateExpr AS cand_key,
                    CASE WHEN gender='Male' THEN 2 WHEN gender='Female' THEN 1 ELSE 0 END AS gender_rank
                FROM tblwaecresult
                WHERE uploadid='$uploadIdEsc'
                UNION ALL
                SELECT $candidateExpr AS cand_key,
                    CASE WHEN gender='Male' THEN 2 WHEN gender='Female' THEN 1 ELSE 0 END AS gender_rank
                FROM tblwaecwithheld
                WHERE uploadid='$uploadIdEsc'
            ) statement_candidates
            GROUP BY cand_key
        ) candidate_rollup
        LEFT JOIN (
            SELECT $candidateExpr AS cand_key,
                COUNT(*) AS total_results,
                SUM(CASE WHEN grade_point<=6 THEN 1 ELSE 0 END) AS credits_total
            FROM tblwaecresult
            WHERE uploadid='$uploadIdEsc'
            GROUP BY $candidateExpr
        ) result_rollup ON result_rollup.cand_key = candidate_rollup.cand_key
        LEFT JOIN (
            SELECT $candidateExpr AS cand_key,
                SUM(CASE WHEN grade='X' THEN 1 ELSE 0 END) AS absent_rows,
                SUM(CASE WHEN grade='W' THEN 1 ELSE 0 END) AS withheld_rows
            FROM tblwaecwithheld
            WHERE uploadid='$uploadIdEsc'
            GROUP BY $candidateExpr
        ) withheld_rollup ON withheld_rollup.cand_key = candidate_rollup.cand_key
        ORDER BY candidate_rollup.cand_key ASC");
    if($statementCandidateRollupRes){
        while($cr = mysqli_fetch_array($statementCandidateRollupRes, MYSQLI_ASSOC)){
            $genderKey = ($cr["gender_group"] === "Male" || $cr["gender_group"] === "Female") ? $cr["gender_group"] : "Unknown";
            if($genderKey === "Unknown"){
                $statementUnknownGenderCandidates++;
            }

            $creditsTotal = (int)$cr["credits_total"];
            if($creditsTotal < 0){
                $creditsTotal = 0;
            }

            $statementBump($statementMetricRows["registered"], $genderKey);
            if((int)$cr["total_results"] > 0){
                $statementBump($statementMetricRows["with_grade"], $genderKey);
            }
            if((int)$cr["absent_rows"] > 0){
                $statementBump($statementMetricRows["absent"], $genderKey);
            }
            if((int)$cr["withheld_rows"] > 0){
                $statementBump($statementMetricRows["withheld"], $genderKey);
            }
            if($creditsTotal === 0){
                $statementBump($statementMetricRows["zero_credit"], $genderKey);
            }
            if($creditsTotal >= 5){
                $statementBump($statementMetricRows["five_plus"], $genderKey);
            }
            if($creditsTotal >= 8){
                $statementBump($statementMetricRows["eight_plus"], $genderKey);
            }

            $bucketKey = ($creditsTotal >= 8) ? 8 : $creditsTotal;
            if($bucketKey < 0 || $bucketKey > 8){
                $bucketKey = 0;
            }
            $statementBump($statementPassBucketMap[$bucketKey], $genderKey);
            $statementCandidateTotal++;
        }
    }
    if($statementCandidateTotal > 0){
        foreach($statementPassBucketMap as $bucketKey => $bucketRow){
            $statementPassBucketMap[$bucketKey]["pct"] = round(($bucketRow["total"] * 100) / $statementCandidateTotal, 2);
        }
    }
    $statementCandidateSummaryRows = array_values($statementMetricRows);
    $statementPassBucketRows = array_values($statementPassBucketMap);

    $gradeMatrixSelectParts = array();
    foreach(waec_grade_scale() as $gradeCode){
        $gradeKey = strtolower($gradeCode);
        $gradeEsc = mysqli_real_escape_string($con, $gradeCode);
        $gradeMatrixSelectParts[] = "SUM(CASE WHEN grade='$gradeEsc' AND gender='Male' THEN 1 ELSE 0 END) AS g_".$gradeKey."_male";
        $gradeMatrixSelectParts[] = "SUM(CASE WHEN grade='$gradeEsc' AND gender='Female' THEN 1 ELSE 0 END) AS g_".$gradeKey."_female";
    }
    $gradeMatrixSelectParts[] = "SUM(CASE WHEN gender='Male' THEN 1 ELSE 0 END) AS male_total";
    $gradeMatrixSelectParts[] = "SUM(CASE WHEN gender='Female' THEN 1 ELSE 0 END) AS female_total";
    $gradeMatrixSelectParts[] = "SUM(CASE WHEN gender NOT IN ('Male','Female') OR gender='' OR gender IS NULL THEN 1 ELSE 0 END) AS unknown_total";
    $gradeMatrixSelectParts[] = "COUNT(*) AS total_entries";
    $statementGradeMatrixRes = mysqli_query($con, "SELECT
        subject_name,
        ".implode(",\n        ", $gradeMatrixSelectParts)."
        FROM tblwaecresult
        WHERE uploadid='$uploadIdEsc'
        GROUP BY subject_name
        ORDER BY subject_name ASC");
    if($statementGradeMatrixRes){
        while($gmr = mysqli_fetch_array($statementGradeMatrixRes, MYSQLI_ASSOC)){
            $statementUnknownGenderGradeRows += (int)$gmr["unknown_total"];
            $statementGradeMatrixRows[] = $gmr;
        }
    }

    if($studentQuery !== "" || $studentKeyInput !== ""){
        $queryName = preg_replace('/\s+/', ' ', trim($studentQuery));
        $queryNo = preg_replace('/\s+/', '', trim($studentQuery));
        $queryNameEsc = mysqli_real_escape_string($con, $queryName);
        $queryNoEsc = mysqli_real_escape_string($con, $queryNo);
        /* Search both normal grades and X/W status records. */
        $studentDirectoryFrom = "(SELECT candidate_no,student_name FROM tblwaecresult WHERE uploadid='$uploadIdEsc'
            UNION
            SELECT candidate_no,student_name FROM tblwaecwithheld WHERE uploadid='$uploadIdEsc') waec_student_directory";

        if($studentKeyInput !== ""){
            $selectedStudentKey = $studentKeyInput;
            $selectedStudentKeyEsc = mysqli_real_escape_string($con, $selectedStudentKey);
            $studentInfoByKey = mysqli_query($con, "SELECT
                CASE WHEN candidate_no<>'' THEN candidate_no ELSE 'N/A' END AS candidate_no,
                CASE WHEN student_name<>'' THEN student_name ELSE 'Unknown' END AS student_name
                FROM $studentDirectoryFrom
                WHERE CASE WHEN candidate_no<>'' THEN candidate_no ELSE CONCAT('NAME:',student_name) END='$selectedStudentKeyEsc'
                LIMIT 1");
            if($studentInfoByKey && mysqli_num_rows($studentInfoByKey) > 0){
                $infoKey = mysqli_fetch_array($studentInfoByKey, MYSQLI_ASSOC);
                $selectedStudentName = $infoKey["student_name"];
                $selectedStudentCandidate = $infoKey["candidate_no"];
            }
        } else {
            $studentPick = mysqli_query($con, "SELECT
                CASE WHEN candidate_no<>'' THEN candidate_no ELSE CONCAT('NAME:',student_name) END AS student_key,
                CASE WHEN candidate_no<>'' THEN candidate_no ELSE 'N/A' END AS candidate_no,
                CASE WHEN student_name<>'' THEN student_name ELSE 'Unknown' END AS student_name
                FROM $studentDirectoryFrom
                WHERE candidate_no='$queryNoEsc' OR UPPER(TRIM(student_name))=UPPER('$queryNameEsc')
                GROUP BY student_key, candidate_no, student_name
                ORDER BY CASE WHEN candidate_no='$queryNoEsc' THEN 0 ELSE 1 END, student_name ASC
                LIMIT 1");

            if($studentPick && mysqli_num_rows($studentPick) > 0){
                $picked = mysqli_fetch_array($studentPick, MYSQLI_ASSOC);
                $selectedStudentKey = $picked["student_key"];
                $selectedStudentName = $picked["student_name"];
                $selectedStudentCandidate = $picked["candidate_no"];
            } else {
                $likeNameEsc = mysqli_real_escape_string($con, $queryName);
                $likeNoEsc = mysqli_real_escape_string($con, $queryNo);
                $matchesRes = mysqli_query($con, "SELECT
                    CASE WHEN candidate_no<>'' THEN candidate_no ELSE CONCAT('NAME:',student_name) END AS student_key,
                    CASE WHEN candidate_no<>'' THEN candidate_no ELSE 'N/A' END AS candidate_no,
                    CASE WHEN student_name<>'' THEN student_name ELSE 'Unknown' END AS student_name
                    FROM $studentDirectoryFrom
                    WHERE candidate_no LIKE '%$likeNoEsc%' OR UPPER(student_name) LIKE UPPER('%$likeNameEsc%')
                    GROUP BY student_key, candidate_no, student_name
                    ORDER BY student_name ASC
                    LIMIT 20");
                if($matchesRes){
                    while($m = mysqli_fetch_array($matchesRes, MYSQLI_ASSOC)){
                        $studentMatches[] = $m;
                    }
                    if(count($studentMatches) === 1){
                        $selectedStudentKey = $studentMatches[0]["student_key"];
                        $selectedStudentName = $studentMatches[0]["student_name"];
                        $selectedStudentCandidate = $studentMatches[0]["candidate_no"];
                    }
                }
            }
        }

        if($selectedStudentKey !== ""){
            $selectedStudentKeyEsc = mysqli_real_escape_string($con, $selectedStudentKey);
            $studentKeyFilter = "CASE WHEN candidate_no<>'' THEN candidate_no ELSE CONCAT('NAME:',student_name) END='$selectedStudentKeyEsc'";

            $studentRows = mysqli_query($con, "SELECT candidate_no,student_name,subject_name,grade,grade_point
                FROM tblwaecresult
                WHERE uploadid='$uploadIdEsc' AND $studentKeyFilter
                UNION ALL
                SELECT candidate_no,student_name,subject_name,grade,NULL AS grade_point
                FROM tblwaecwithheld
                WHERE uploadid='$uploadIdEsc' AND $studentKeyFilter
                ORDER BY subject_name ASC");

            $studentSummary = mysqli_query($con, "SELECT
                CASE WHEN candidate_no<>'' THEN candidate_no ELSE 'N/A' END AS candidate_no,
                CASE WHEN student_name<>'' THEN student_name ELSE 'Unknown' END AS student_name,
                MAX(CASE WHEN gender<>'' THEN gender ELSE '' END) AS gender,
                COUNT(*) AS total_subjects,
                SUM(CASE WHEN grade_point<=6 THEN 1 ELSE 0 END) AS total_credits,
                MAX(CASE WHEN subject_name IN ('ENGLISH LANG','ENGLISH LANGUAGE','ENGLISH') AND grade_point BETWEEN 1 AND 6 THEN 1 ELSE 0 END) AS english_credit,
                MAX(CASE WHEN subject_name IN ('MATHEMATICS(CORE)','MATHEMATICS (CORE)','CORE MATHEMATICS','CORE MATHS') AND grade_point BETWEEN 1 AND 6 THEN 1 ELSE 0 END) AS math_credit,
                ROUND(AVG(grade_point),2) AS avg_point
                FROM tblwaecresult
                WHERE uploadid='$uploadIdEsc' AND $studentKeyFilter
                GROUP BY CASE WHEN candidate_no<>'' THEN candidate_no ELSE student_name END, student_name
                LIMIT 1");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>WAEC Results Analysis</title>
<?php
include("links.php");
?>
<style>
:root {
    --bg-page: #f3f7fb;
    --card-bg: #ffffff;
    --card-border: #dbe5f0;
    --text-main: #0f172a;
    --text-muted: #475569;
    --brand: #0f766e;
    --brand-dark: #115e59;
    --accent: #0b66c3;
    --warn: #b45309;
    --danger: #b91c1c;
}
.waec-wrap {
    max-width: 1280px;
    margin: 14px auto;
    padding: 10px 14px;
}
.waec-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 12px;
    padding: 14px 16px;
    margin-bottom: 14px;
    position: relative;
    z-index: 1;
    overflow: visible;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
}
.waec-card h2,
.waec-card h3 {
    line-height: 1.2;
}
.waec-card h3 {
    margin: 0 0 10px 0;
    padding-bottom: 7px;
    border-bottom: 1px solid #e5edf6;
    color: #0f172a;
    letter-spacing: 0.15px;
}
.waec-title {
    color: var(--brand);
    margin: 0 0 6px 0;
    letter-spacing: 0.2px;
}
.waec-note {
    color: var(--text-muted);
    margin: 0 0 14px 0;
}
.waec-grid {
    display: grid;
    grid-template-columns: minmax(430px, 470px) minmax(0, 1fr);
    gap: 12px;
    align-items: start;
}
.waec-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
}
.waec-stat {
    border: 1px solid #dbe5f0;
    background: linear-gradient(180deg, #ffffff 0%, #f7fafc 100%);
    border-radius: 10px;
    padding: 10px;
}
.waec-stat h4 {
    margin: 0 0 6px;
    font-size: 12px;
    color: var(--text-muted);
}
.waec-stat strong {
    font-size: 22px;
    color: var(--text-main);
}
.waec-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.waec-table th, .waec-table td {
    border: 1px solid #e3ebf4;
    padding: 8px;
    vertical-align: top;
}
.waec-table th {
    background: #edf3fa;
    text-align: left;
    color: #1e293b;
    letter-spacing: 0.2px;
}
.waec-table tbody tr:nth-child(even) {
    background: #fafcff;
}
.waec-table tbody tr:hover {
    background: #f0f7ff;
}
.waec-table-wrap {
    overflow-x: auto;
}
.waec-actions a, .waec-actions button {
    display: inline-block;
    margin-right: 6px;
    margin-top: 6px;
}
.waec-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.waec-actions a, .waec-actions button {
    margin: 0;
}
.waec-btn {
    border: 0;
    background: linear-gradient(180deg, var(--brand) 0%, var(--brand-dark) 100%);
    color: white;
    padding: 9px 13px;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    font-weight: 600;
    transition: transform .12s ease, box-shadow .18s ease, background .18s ease;
}
.waec-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 18px rgba(15, 118, 110, 0.28);
}
.waec-btn-muted {
    border: 1px solid #c9d7e7;
    background: #fff;
    color: #1e293b;
    padding: 8px 11px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all .15s ease;
}
.waec-btn-muted:hover {
    border-color: #9fb6cf;
    background: #f8fbff;
}
.waec-btn:focus,
.waec-btn-muted:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(11, 102, 195, 0.15);
}
.waec-btn-filter {
    border-color: #0ea5a7;
    color: #0f766e;
    background: #f0fdfa;
}
.waec-btn-search {
    border-color: #60a5fa;
    color: #0b66c3;
    background: #eff6ff;
}
.waec-btn-reset {
    border-color: #f59e0b;
    color: #9a3412;
    background: #fffbeb;
}
.waec-btn-clear {
    border-color: #f87171;
    color: #b91c1c;
    background: #fef2f2;
}
.waec-btn-excel {
    border-color: #34d399;
    color: #065f46;
    background: #ecfdf5;
}
.waec-btn-pdf {
    border-color: #fca5a5;
    color: #991b1b;
    background: #fef2f2;
}
.waec-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    margin-bottom: 8px;
    padding: 10px;
    border: 1px solid #e2ebf5;
    border-radius: 10px;
    background: #f8fbff;
}
.waec-toolbar select,
.waec-toolbar input[type="text"] {
    border: 1px solid #bfd0e3;
    border-radius: 8px;
    padding: 7px 10px;
    min-height: 36px;
    background: #ffffff;
    color: #0f172a;
}
.waec-toolbar select:focus,
.waec-toolbar input[type="text"]:focus {
    outline: none;
    border-color: #0ea5a7;
    box-shadow: 0 0 0 3px rgba(14, 165, 167, 0.12);
}
.waec-btn-small {
    padding: 6px 9px;
    font-size: 11px;
    border-radius: 5px;
    min-height: 30px;
}
.waec-small {
    font-size: 12px;
    color: #64748b;
}
.waec-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 999px;
    background: #e0f2fe;
    color: #075985;
    border: 1px solid #bae6fd;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.2px;
}
.waec-action-col {
    width: 150px;
    min-width: 150px;
}
.waec-action-wrap {
    display: flex;
    gap: 6px;
    flex-wrap: nowrap;
}
.waec-action-wrap form {
    margin: 0;
}
.waec-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 62px;
    padding: 6px 8px;
    font-size: 11px;
    white-space: nowrap;
}
.waec-recent-card {
    z-index: 4;
}
.waec-analysis-card {
    z-index: 2;
}
.waec-highlight-row td {
    background: #ecfdf5;
    border-top: 2px solid #34d399;
    font-weight: 700;
}
.waec-core-tag {
    display: inline-block;
    margin-left: 6px;
    padding: 2px 6px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    color: #1d4ed8;
    background: #dbeafe;
    border: 1px solid #bfdbfe;
}
.waec-kpi-alert {
    border-left: 4px solid var(--warn);
    background: #fff7ed;
    color: #7c2d12;
    padding: 9px 10px;
    border-radius: 8px;
    margin: 8px 0;
    font-size: 12px;
}
.waec-footer-note {
    border-left-color: #93c5fd;
    background: #eff6ff;
    color: #1e3a8a;
}
.waec-bars {
    display: grid;
    gap: 8px;
}
.waec-bar-row {
    display: grid;
    grid-template-columns: 1fr 90px;
    gap: 8px;
    align-items: center;
}
.waec-track {
    width: 100%;
    height: 14px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}
.waec-fill {
    height: 100%;
    background: linear-gradient(90deg, #0f766e, #0b66c3);
}
@media (max-width: 980px){
    .waec-grid { grid-template-columns: 1fr; }
    .waec-stats { grid-template-columns: 1fr 1fr; }
    .waec-action-wrap { flex-wrap: wrap; }
    .waec-toolbar { padding: 8px; }
    .waec-toolbar select, .waec-toolbar input[type="text"] { width: 100%; }
    .waec-card h3 { margin-bottom: 8px; }
}
</style>
<link rel="stylesheet" href="css/waec-analysis.css?v=20261011-layout2">
</head>
<body class="waec-page">
<div class="header">
<?php
include("menu.php");
?>
</div>
<?php
// Presentation-only batch list; leave the saved result sets and calculations unchanged.
$workspaceUploads = array(); $workspaceSelected = null;
if($uploads){
    while($workspaceUpload = mysqli_fetch_array($uploads, MYSQLI_ASSOC)){
        $workspaceUploads[] = $workspaceUpload;
        if($workspaceUpload['uploadid'] === $currentUploadId) $workspaceSelected = $workspaceUpload;
    }
    if(count($workspaceUploads)) mysqli_data_seek($uploads, 0);
}
?>
<main class="waec-wrap">
    <header class="waec-hero">
        <div><span class="waec-eyebrow">Academic intelligence / WAEC</span>
        <h1>Your results, clearly understood.</h1>
        <p>Start with the big picture. Explore subjects, support students and check every outcome.</p></div>
        <a class="waec-compare-link" href="waec-comparison.php"><i class="fa fa-bar-chart" aria-hidden="true"></i><span>Compare batches<small>See progress across examination years</small></span><i class="fa fa-arrow-right" aria-hidden="true"></i></a>
    </header>
    <?php if($message !== ''){ ?><div class="waec-feedback" role="status"><?php echo $message; ?></div><?php } ?>
    <section class="waec-context" aria-label="Selected results batch">
        <form method="get" action="waec-analysis.php#waec-overview" class="waec-batch-switch">
            <label for="waec-current-batch">Results workspace <small>Choose the upload you want to analyse</small></label>
            <select id="waec-current-batch" name="uploadid" required>
                <option value="">Select a saved results batch</option>
                <?php if($currentUploadId !== '' && !$workspaceSelected){ ?><option value="<?php echo waec_esc($currentUploadId); ?>" selected><?php echo waec_esc($currentUploadId); ?></option><?php } ?>
                <?php foreach($workspaceUploads as $workspaceUpload){ ?>
                <option value="<?php echo waec_esc($workspaceUpload['uploadid']); ?>" <?php echo $workspaceUpload['uploadid'] === $currentUploadId ? 'selected' : ''; ?>><?php echo waec_esc((wac_ready($workspaceUpload) ? wac_title($workspaceUpload) : $workspaceUpload['originalname']).' · '.substr($workspaceUpload['uploadid'], -8)); ?></option>
                <?php } ?>
            </select>
            <button type="submit" class="waec-btn">Open batch</button>
        </form>
        <?php if($workspaceSelected){ ?><p class="waec-context-note"><i class="fa fa-file-text-o" aria-hidden="true"></i> <?php echo waec_esc($workspaceSelected['originalname']); ?> <span>Uploaded <?php echo waec_esc($workspaceSelected['datetimeentry']); ?></span></p><?php } ?>
    </section>
    <div class="waec-workspace" data-waec-workspace data-default-section="<?php echo $currentUploadId !== '' ? 'waec-overview' : 'waec-upload'; ?>">
        <nav class="waec-workspace-nav" aria-label="WAEC workspace sections">
            <span class="waec-nav-label">Explore results</span>
            <a href="#waec-overview" data-waec-nav><i class="fa fa-th-large" aria-hidden="true"></i><span>Overview<small>Key figures &amp; indicators</small></span></a>
            <a href="#waec-subjects" data-waec-nav><i class="fa fa-bar-chart" aria-hidden="true"></i><span>Subjects<small>Grades &amp; performance</small></span></a>
            <a href="#student-result-analysis" data-waec-nav><i class="fa fa-users" aria-hidden="true"></i><span>Students<small>Search &amp; breakdowns</small></span></a>
            <a href="#waec-coverage" data-waec-nav><i class="fa fa-check-square-o" aria-hidden="true"></i><span>Coverage<small>Missing &amp; special outcomes</small></span></a>
            <a href="#waec-upload" data-waec-nav><i class="fa fa-folder-open-o" aria-hidden="true"></i><span>Uploads<small>Import &amp; manage files</small></span></a>
            <p class="waec-nav-note"><i class="fa fa-info-circle" aria-hidden="true"></i> Rates use graded entries. A missing outcome is not a failure.</p>
        </nav>
        <div class="waec-workspace-content">
        <?php if($currentUploadId !== ''){ ?>
        <details class="waec-filter-drawer" <?php echo $focusSubject !== '' || $focusGrade !== '' || $focusGender !== '' ? 'open' : ''; ?>>
            <summary><span><i class="fa fa-sliders" aria-hidden="true"></i> Refine this view</span><span class="waec-filter-summary"><?php echo waec_esc($focusSubject !== '' ? $focusSubject : 'All subjects'); ?> · <?php echo waec_esc($focusGrade !== '' ? $focusGrade : 'All grades'); ?> · <?php echo waec_esc($focusGender !== '' ? $focusGender : 'All genders'); ?></span></summary>
            <p class="waec-small">Overview and subject results follow these filters. Other sections explain their own scope.</p>
                <form method="get" action="waec-analysis.php#waec-overview" class="waec-toolbar">
                    <input type="hidden" name="uploadid" value="<?php echo waec_esc($currentUploadId); ?>">
                    <input type="hidden" name="distribution_subject" value="<?php echo waec_esc($distributionSubject); ?>">
                    <select name="focus_subject" aria-label="Filter by subject">
                        <option value="">All Subjects</option>
                        <?php
                        foreach($subjectOptionRows as $opt){
                            $sel = ($focusSubject === $opt["subject_name"]) ? "selected" : "";
                            echo "<option value='".waec_esc($opt["subject_name"])."' $sel>".waec_esc($opt["subject_name"])."</option>";
                        }
                        ?>
                    </select>
                    <select name="focus_grade" aria-label="Filter by grade or result status">
                        <option value="">All Grades / Statuses</option>
                        <option value="*"<?php echo $focusGrade === "*" ? " selected" : ""; ?>>* (Source status)</option>
                        <option value="P"<?php echo $focusGrade === "P" ? " selected" : ""; ?>>P (Source status)</option>
                        <?php
                        foreach(array("A1","B2","B3","C4","C5","C6","D7","E8","F9") as $gr){
                            $sel = ($focusGrade === $gr) ? "selected" : "";
                            echo "<option value='$gr' $sel>$gr</option>";
                        }
                        if($focusSubjectAbsentStudents > 0 || $focusGrade === "X"){
                            $selX = ($focusGrade === "X") ? "selected" : "";
                            echo "<option value='X' $selX>X (Students Absent)</option>";
                        }
                        if($focusSubjectWithheldStudents > 0 || $focusGrade === "W"){
                            $selW = ($focusGrade === "W") ? "selected" : "";
                            echo "<option value='W' $selW>W (Subject Withheld)</option>";
                        }
                        ?>
                    </select>
                    <select name="focus_gender" aria-label="Filter by gender">
                        <option value="">All Genders</option>
                        <?php
                        foreach(array("Male","Female") as $genderOpt){
                            $sel = ($focusGender === $genderOpt) ? "selected" : "";
                            echo "<option value='".waec_esc($genderOpt)."' $sel>".waec_esc($genderOpt)."</option>";
                        }
                        ?>
                    </select>
                    <button class="waec-btn-muted waec-btn-filter" type="submit"><i class="fa fa-filter"></i> Apply</button>
                    <a class="waec-btn-muted waec-btn-small waec-btn-reset" href="waec-analysis.php?uploadid=<?php echo urlencode($currentUploadId); ?>&student_query=<?php echo urlencode($studentQuery); ?>">Reset</a>
                </form>
        </details>
        <details class="waec-export-drawer"><summary><i class="fa fa-download" aria-hidden="true"></i> Download &amp; print reports</summary>                <div class="waec-actions">
                    <a class="waec-btn-muted waec-btn-excel" href="waec-analysis.php?uploadid=<?php echo urlencode($currentUploadId); ?>&export=excel"><i class="fa fa-file-excel-o"></i> Full report (CSV)</a>
                    <a class="waec-btn-muted waec-btn-pdf" href="waec-analysis.php?uploadid=<?php echo urlencode($currentUploadId); ?>&export=pdf"><i class="fa fa-file-pdf-o"></i> Full report (PDF)</a>
                    <a class="waec-btn-muted waec-btn-pdf" href="waec-analysis.php?uploadid=<?php echo urlencode($currentUploadId); ?>&export=filtered_dashboard_pdf&focus_subject=<?php echo urlencode($focusSubject); ?>&focus_grade=<?php echo urlencode($focusGrade); ?>&focus_gender=<?php echo urlencode($focusGender); ?>&distribution_subject=<?php echo urlencode($distributionSubject); ?>"><i class="fa fa-print"></i> Print Filtered Dashboard</a>
                </div></details>
        <?php } ?>
<section class="waec-panel" id="waec-overview" aria-label="Performance overview">
        <div class="waec-card waec-analysis-card">
            <div class="waec-section-heading"><div><span class="waec-section-kicker">The big picture</span><h2>Performance overview</h2></div><span class="waec-scope-badge">Current filters</span></div>
            <?php if($currentUploadId === ""){ ?>
                <p>Choose a saved batch above, or open Uploads to import your first results file.</p>
            <?php } else { ?>



                <?php
                $overallPassPct = 0;
                $summaryTotalResults = (int)@$summary["total_results"];
                $summaryPassCount = (int)@$summary["pass_count"];
                $summaryAbsentRows = (int)$focusSubjectAbsentRows;
                $summaryAbsentStudents = (int)$focusSubjectAbsentStudents;
                $summaryWithheldRows = (int)$focusSubjectWithheldRows;
                $summaryWithheldStudents = (int)$focusSubjectWithheldStudents;
                $hasDashboardFilter = ($focusSubject !== "" || $focusGrade !== "" || $focusGender !== "");
                $dashboardTotalStudents = waec_filtered_student_count($con, $currentUploadId, $focusSubject, $focusGender, $focusGrade);
                if($focusSubject !== ""){
                    $displayAbsentStudents = (int)$focusSubjectAbsentStudents;
                    $displayAbsentRows = (int)$focusSubjectAbsentRows;
                } elseif($focusGrade === "X"){
                    $displayAbsentStudents = (int)$summaryAbsentStudents;
                    $displayAbsentRows = (int)$summaryAbsentRows;
                } elseif($hasDashboardFilter){
                    $displayAbsentStudents = 0;
                    $displayAbsentRows = 0;
                } else {
                    $displayAbsentStudents = (int)$fullAbsenteeStudents;
                    $displayAbsentRows = (int)$fullAbsenteeStudents;
                }
                $integratedSciencePlusAbsent = $integratedScienceWroteStudents + $fullAbsenteeStudents;
                if($summaryTotalResults > 0){
                    $overallPassPct = round(($summaryPassCount * 100) / $summaryTotalResults, 2);
                }
                ?>
                <div class="waec-stats waec-primary-stats">
                    <div class="waec-stat waec-stat--featured"><span class="waec-stat-icon"><i class="fa fa-line-chart" aria-hidden="true"></i></span><h4>Credit rate</h4><strong><?php echo $summaryTotalResults > 0 ? waec_esc($overallPassPct).'%' : 'N/A'; ?></strong><small>A1–C6 / graded entries</small></div>
                    <div class="waec-stat"><span class="waec-stat-icon"><i class="fa fa-users" aria-hidden="true"></i></span><h4>Students</h4><strong><?php echo number_format($dashboardTotalStudents); ?></strong><small>Within the selected filters</small></div>
                    <div class="waec-stat"><span class="waec-stat-icon"><i class="fa fa-file-text-o" aria-hidden="true"></i></span><h4>Graded entries</h4><strong><?php echo number_format($summaryTotalResults); ?></strong><small>Subject results with A1–F9</small></div>
                    <div class="waec-stat"><span class="waec-stat-icon"><i class="fa fa-balance-scale" aria-hidden="true"></i></span><h4>Average point</h4><strong><?php echo $summaryTotalResults > 0 ? waec_esc(@$summary["avg_point"]) : 'N/A'; ?></strong><small>Lower is better · A1 = 1</small></div>
                </div>
                <div class="waec-secondary-stats">
                    <div><span>Subjects</span><strong><?php echo (int)@$summary["total_subjects"]; ?></strong></div>
                    <div><span>Credits · A1–C6</span><strong><?php echo number_format((int)@$summary["pass_count"]); ?></strong></div>
                    <div><span>Below credit · D7–F9</span><strong><?php echo number_format((int)@$summary["fail_count"]); ?></strong></div>
                    <div><span>Students absent</span><strong><?php echo $displayAbsentStudents; ?></strong></div>
                    <div><span>Withheld entries</span><strong><?php echo $summaryWithheldRows; ?></strong></div>
                    <?php if($focusSubject !== ""){ ?><div><span>Registered for subject</span><strong><?php echo (int)$focusSubjectRegisteredStudents; ?></strong></div><?php } ?>
                </div>
                <p class="waec-small">Credit rate = A1-C6 results divided by graded results (A1-F9). Absent (X) and withheld (W) outcomes are excluded. D7/E8 are below credit; F9 is a fail. A lower average grade point is better.</p>
                <?php if($displayAbsentRows > 0){ ?>
                <div class="waec-kpi-alert" style="margin-top:8px;">
                    Students absent<?php echo ($focusSubject !== "" ? " for ".waec_esc($focusSubject) : ""); ?>:
                    <strong><?php echo $displayAbsentStudents; ?></strong>.
                </div>
                <?php } ?>
                <?php if($summaryWithheldRows > 0){ ?>
                <div class="waec-kpi-alert" style="margin-top:8px;">
                    Withheld subjects detected<?php echo ($focusSubject !== "" ? " for ".waec_esc($focusSubject) : ""); ?>:
                    <strong><?php echo $summaryWithheldRows; ?></strong> result rows across
                    <strong><?php echo $summaryWithheldStudents; ?></strong> students.
                </div>
                <?php } ?>

            <?php } ?>
        </div>

<?php if($currentUploadId !== ''){ ?>
<div class="waec-quick-links"><a href="#waec-subjects"><i class="fa fa-bar-chart" aria-hidden="true"></i><span>Explore subject results<small>Find strengths and areas for attention</small></span><span aria-hidden="true">→</span></a><a href="#waec-coverage"><i class="fa fa-check-square-o" aria-hidden="true"></i><span>Check result coverage<small>Review missing and ungraded outcomes</small></span><span aria-hidden="true">→</span></a></div>        <div class="waec-card">
            <h3 style="margin-top:0">Core &amp; Credit Indicators</h3>
            <p class="waec-small">Uses all subjects for the selected gender and candidates with at least one graded result. Subject and grade filters do not apply here. These indicators do not determine admission: institutions require specific core subjects, relevant electives and programme aggregates.</p>
            <table class="waec-table">
                <thead><tr><th>Metric</th><th>Count</th><th>% of Students</th></tr></thead>
                <tbody>
                    <?php
                    $sTotal = (int)@$readiness["students_total"];
                    $rMetrics = array(
                        "English Credit (A1-C6)" => (int)@$readiness["english_credit_count"],
                        "Core Math Credit (A1-C6)" => (int)@$readiness["math_credit_count"],
                        "Science/Social Credit (A1-C6)" => (int)@$readiness["science_social_credit_count"],
                        "At Least 5 Credits" => (int)@$readiness["credits5_count"],
                        "At Least 6 Credits" => (int)@$readiness["credits6_count"],
                        "Core credits (Eng+Math+Sci/Soc)" => (int)@$readiness["core_ready_count"]
                    );
                    foreach($rMetrics as $label => $countVal){
                        $pct = $sTotal > 0 ? round(($countVal * 100) / $sTotal, 2) : 0;
                        echo "<tr><td>".waec_esc($label)."</td><td>".$countVal."</td><td>".$pct."%</td></tr>";
                    }
                    ?>
                </tbody>
            </table></div>
<?php } ?>
</section>
<section class="waec-panel" id="waec-subjects" aria-label="Subject analysis">
<div class="waec-panel-heading"><span class="waec-section-kicker">Subject insights</span><h2>Where are students doing well?</h2><p>Explore credit rates first, then inspect grade distributions and the highest- and lowest-performing subjects.</p></div>
<?php if($currentUploadId !== ''){ ?>
<div class="waec-card">            <h3 style="margin-top:0">Subject Performance</h3>
            <?php
            echo "<div class='waec-bars'>";
            if($subjectRows && mysqli_num_rows($subjectRows) > 0){
                while($s = mysqli_fetch_array($subjectRows, MYSQLI_ASSOC)){
                    $pct = (float)$s["pass_pct"];
                    echo "<div class='waec-bar-row'>";
                    echo "<div><strong>".waec_esc($s["subject_name"])."</strong> <span class='waec-small'>(".waec_esc($s["total"])." entries, avg ".waec_esc($s["avg_point"]).")</span>";
                    echo "<div class='waec-track'><div class='waec-fill' style='width:".$pct."%'></div></div></div>";
                    echo "<div style='text-align:right'>".$pct."%</div>";
                    echo "</div>";
                }
            } else {
                echo "<p>No subject data.</p>";
            }
            echo "</div>";
            ?>
        </div><div class="waec-grid waec-balanced-grid"><div class="waec-card">            <div class="waec-actions" style="margin-bottom:8px;">
                <button type="button" class="waec-btn-muted waec-btn-pdf" onclick="waecPrintSection('waec-grade-distribution-print', 'Grade Distribution')"><i class="fa fa-print"></i> Print Grade Distribution</button>
            </div>
            <h3 style="margin-top:0">Grade Distribution</h3>
            <?php if($focusSubject === ""){ ?>
                <form method="get" action="waec-analysis.php#waec-subjects" class="waec-toolbar" style="margin-bottom:8px;">
                    <input type="hidden" name="uploadid" value="<?php echo waec_esc($currentUploadId); ?>">
                    <input type="hidden" name="focus_subject" value="<?php echo waec_esc($focusSubject); ?>">
                    <input type="hidden" name="focus_grade" value="<?php echo waec_esc($focusGrade); ?>">
                    <input type="hidden" name="focus_gender" value="<?php echo waec_esc($focusGender); ?>">
                    <input type="hidden" name="student_query" value="<?php echo waec_esc($studentQuery); ?>">
                    <?php if($selectedStudentKey !== ""){ ?>
                        <input type="hidden" name="student_key" value="<?php echo waec_esc($selectedStudentKey); ?>">
                    <?php } ?>
                    <select name="distribution_subject" aria-label="Grade distribution subject">
                        <option value="">All Subjects In Grade Distribution</option>
                        <?php
                        foreach($subjectOptionRows as $opt){
                            $sel = ($distributionSubject === $opt["subject_name"]) ? "selected" : "";
                            echo "<option value='".waec_esc($opt["subject_name"])."' $sel>".waec_esc($opt["subject_name"])."</option>";
                        }
                        ?>
                    </select>
                    <button class="waec-btn-muted waec-btn-filter" type="submit"><i class="fa fa-filter"></i> Apply</button>
                    <a class="waec-btn-muted waec-btn-small waec-btn-reset" href="waec-analysis.php?uploadid=<?php echo urlencode($currentUploadId); ?>&focus_subject=<?php echo urlencode($focusSubject); ?>&focus_grade=<?php echo urlencode($focusGrade); ?>&focus_gender=<?php echo urlencode($focusGender); ?>&student_query=<?php echo urlencode($studentQuery); ?><?php echo ($selectedStudentKey !== "") ? "&student_key=".urlencode($selectedStudentKey) : ""; ?>#waec-subjects">Reset Grade Distribution</a>
                </form>
            <?php } ?>
            <div id="waec-grade-distribution-print">
            <p class="waec-small">Distribution scope: <span class="waec-badge"><?php echo waec_esc($gradeDistributionLabel); ?></span></p>
            <?php if($focusSubject !== ""){ ?>
            <p class="waec-small">Uses the dashboard subject filter. Clear that filter to choose a different distribution subject.</p>
            <?php } ?>
            <table class="waec-table">
                <thead><tr><th>Grade</th><th>Count</th><th>%</th></tr></thead>
                <tbody>
                <?php
                if($gradeRows && mysqli_num_rows($gradeRows) > 0){
                    while($g = mysqli_fetch_array($gradeRows, MYSQLI_ASSOC)){
                        echo "<tr><td>".waec_esc($g["grade"])."</td><td>".waec_esc($g["total"])."</td><td>".waec_esc($g["pct"])."%</td></tr>";
                    }
                    echo "<tr class='waec-highlight-row'><td>Credit rate (A1-C6)</td><td colspan='2'>".waec_esc($gradeDistributionPassPct)."%</td></tr>";
                } else {
                    echo "<tr><td colspan='3'>No grade data.</td></tr>";
                }
                ?>
                </tbody>
            </table>
            </div></div><div class="waec-card">            <h3 style="margin-top:0">Subject credit-rate leaders &amp; gaps</h3>
            <p class="waec-small">Subjects with at least 10 graded entries, within the selected gender scope. Subject and grade filters do not apply to this ranking.</p>
            <table class="waec-table">
                <thead><tr><th>Category</th><th>Subject</th><th>Credit %</th></tr></thead>
                <tbody>
                <?php
                $topLeague = array();
                $bottomLeague = array();
                if($topSubjects && mysqli_num_rows($topSubjects) > 0){
                    while($s = mysqli_fetch_array($topSubjects, MYSQLI_ASSOC)){
                        $topLeague[] = $s;
                        echo "<tr><td>Top</td><td>".waec_esc($s["subject_name"])."</td><td>".waec_esc($s["pass_pct"])."%</td></tr>";
                    }
                }
                if($bottomSubjects && mysqli_num_rows($bottomSubjects) > 0){
                    while($s2 = mysqli_fetch_array($bottomSubjects, MYSQLI_ASSOC)){
                        $bottomLeague[] = $s2;
                        echo "<tr><td>Bottom</td><td>".waec_esc($s2["subject_name"])."</td><td>".waec_esc($s2["pass_pct"])."%</td></tr>";
                    }
                }
                if(!$topLeague && !$bottomLeague){
                    echo "<tr><td colspan='3'>No subjects have at least 10 graded entries in this scope.</td></tr>";
                }
                ?>
                </tbody>
            </table>
            <?php
            if(count($topLeague) > 0 && count($bottomLeague) > 0){
                $best = $topLeague[0];
                $worst = $bottomLeague[0];
                $spread = round(((float)$best["pass_pct"] - (float)$worst["pass_pct"]), 2);
                echo "<div class='waec-kpi-alert' style='margin-top:10px;'>";
                echo "Insight: Best subject is <strong>".waec_esc($best["subject_name"])."</strong> (".waec_esc($best["pass_pct"])."%) while weakest is <strong>".waec_esc($worst["subject_name"])."</strong> (".waec_esc($worst["pass_pct"])."%). Spread: <strong>".$spread."%</strong>.";
                echo "</div>";
            }
            ?></div></div>
<?php }else{ ?><div class="waec-card waec-empty-state"><i class="fa fa-file-text-o" aria-hidden="true"></i><h3>Choose a batch to begin</h3><p>Open a saved batch above, or import a results file in Uploads.</p><a href="#waec-upload" class="waec-btn-muted">Go to uploads</a></div><?php } ?>

</section>
<section class="waec-panel" id="student-result-analysis" aria-label="Student analysis">
<div class="waec-panel-heading"><span class="waec-section-kicker">Student insights</span><h2>From the batch to the individual</h2><p>Find a candidate, review their results, or open the detailed candidate and gender breakdowns.</p></div>
<?php if($currentUploadId !== ''){ ?>
<div class="waec-card waec-student-search"><h3>Find a student</h3><p class="waec-small">Search by name or index number. Individual results cover the selected student’s full upload, not just the dashboard filters.</p>                <form method="get" action="waec-analysis.php#student-result-analysis" class="waec-toolbar">
                    <input type="hidden" name="uploadid" value="<?php echo waec_esc($currentUploadId); ?>">
                    <input type="hidden" name="focus_subject" value="<?php echo waec_esc($focusSubject); ?>">
                    <input type="hidden" name="focus_grade" value="<?php echo waec_esc($focusGrade); ?>">
                    <input type="hidden" name="focus_gender" value="<?php echo waec_esc($focusGender); ?>">
                    <input type="hidden" name="distribution_subject" value="<?php echo waec_esc($distributionSubject); ?>">
                    <input type="text" aria-label="Student name or index number" name="student_query" value="<?php echo waec_esc($studentQuery); ?>" placeholder="Type few letters of name or index number">
                    <button class="waec-btn-muted waec-btn-search" type="submit"><i class="fa fa-search"></i> Find Student</button>
                    <a class="waec-btn-muted waec-btn-small waec-btn-clear" href="waec-analysis.php?uploadid=<?php echo urlencode($currentUploadId); ?>&focus_subject=<?php echo urlencode($focusSubject); ?>&focus_grade=<?php echo urlencode($focusGrade); ?>&focus_gender=<?php echo urlencode($focusGender); ?>&distribution_subject=<?php echo urlencode($distributionSubject); ?>#student-result-analysis">Clear Search</a>
                </form></div>    <?php if($studentQuery !== "" || $selectedStudentKey !== "" || $studentKeyInput !== ""){ ?>
    <div class="waec-card">
        <h3 style="margin-top:0">Student Result Analysis</h3>
        <?php
        if(count($studentMatches) > 1 && $selectedStudentKey === ""){
            echo "<div class='waec-kpi-alert'>";
            echo "Multiple matches found. Select the exact student below.";
            echo "</div>";
            echo "<table class='waec-table' style='margin-bottom:10px;'>";
            echo "<thead><tr><th>Candidate No</th><th>Student Name</th><th>Action</th></tr></thead><tbody>";
            foreach($studentMatches as $m){
                echo "<tr>";
                echo "<td>".waec_esc($m["candidate_no"])."</td>";
                echo "<td>".waec_esc($m["student_name"])."</td>";
                echo "<td><a class='waec-btn-muted waec-btn-small waec-btn-search' href='waec-analysis.php?uploadid=".urlencode($currentUploadId)."&focus_subject=".urlencode($focusSubject)."&focus_grade=".urlencode($focusGrade)."&focus_gender=".urlencode($focusGender)."&distribution_subject=".urlencode($distributionSubject)."&student_query=".urlencode($studentQuery)."&student_key=".urlencode($m["student_key"])."#student-result-analysis'>Select</a></td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        }
        ?>
        <?php
        $hasStudent = false;
        if($studentSummary && mysqli_num_rows($studentSummary) > 0){
            $ss = mysqli_fetch_array($studentSummary, MYSQLI_ASSOC);
            $hasStudent = true;
            $engOk = ((int)$ss["english_credit"] === 1) ? "Yes" : "No";
            $mathOk = ((int)$ss["math_credit"] === 1) ? "Yes" : "No";
            echo "<div class='waec-stats'>";
            echo "<div class='waec-stat'><h4>Student</h4><strong style='font-size:14px'>".waec_esc($ss["student_name"])."</strong><div class='waec-small'>".waec_esc($ss["candidate_no"])."</div></div>";
            echo "<div class='waec-stat'><h4>Gender</h4><strong style='font-size:18px'>".waec_esc(waec_display_gender($ss["gender"]))."</strong></div>";
            echo "<div class='waec-stat'><h4>Total Subjects</h4><strong>".waec_esc($ss["total_subjects"])."</strong></div>";
            echo "<div class='waec-stat'><h4>Total Credits</h4><strong>".waec_esc($ss["total_credits"])."</strong></div>";
            echo "<div class='waec-stat'><h4>Average Point</h4><strong>".waec_esc($ss["avg_point"])."</strong></div>";
            echo "<div class='waec-stat'><h4>English Credit</h4><strong style='font-size:18px'>".$engOk."</strong></div>";
            echo "<div class='waec-stat'><h4>Core Math Credit</h4><strong style='font-size:18px'>".$mathOk."</strong></div>";
            echo "</div><br>";
        }
        ?>
        <table class="waec-table">
            <thead><tr><th>Candidate No</th><th>Student Name</th><th>Subject</th><th>Grade</th><th>Point</th></tr></thead>
            <tbody>
            <?php
            if($studentRows && mysqli_num_rows($studentRows) > 0){
                while($sr = mysqli_fetch_array($studentRows, MYSQLI_ASSOC)){
                    echo "<tr><td>".waec_esc($sr["candidate_no"])."</td><td>".waec_esc($sr["student_name"])."</td><td>".waec_esc($sr["subject_name"])."</td><td>".waec_esc($sr["grade"])."</td><td>".waec_esc($sr["grade_point"])."</td></tr>";
                }
            } else {
                echo "<tr><td colspan='5'>No exact student selected yet for: ".waec_esc($studentQuery)."</td></tr>";
            }
            ?>
            </tbody>
        </table>
        <?php
        if($selectedStudentKey !== "" && $studentRows && mysqli_num_rows($studentRows) > 0){
            echo "<div class='waec-actions' style='margin-top:10px;'>";
            echo "<a class='waec-btn-muted waec-btn-pdf' href='waec-analysis.php?uploadid=".urlencode($currentUploadId)."&export=student_pdf&student_key=".urlencode($selectedStudentKey)."'><i class='fa fa-print'></i> Print Student Analysis</a>";
            echo "</div>";
        }
        ?>
    </div>
    <?php } ?>

<details class="waec-detail"><summary><span>Candidate summary & credit totals<small>Full upload · candidate counts and number of credits achieved</small></span><span class="waec-detail-toggle" aria-hidden="true">+</span></summary><div class="waec-detail-body">    <div class="waec-card">
        <div class="waec-actions" style="margin-bottom:8px;">
            <button type="button" class="waec-btn-muted waec-btn-pdf" onclick="waecPrintSection('waec-statement-overview-print', 'Statement-Style Overview')"><i class="fa fa-print"></i> Print Statement Overview</button>
        </div>
        <div id="waec-statement-overview-print">
        <h3 style="margin-top:0">Statement-Style Overview</h3>
        <p class="waec-small">These tables mirror the PDF full statement structure. They always use the full upload so candidate totals do not collapse when subject, grade, or gender filters are active above.</p>
        <div class="waec-grid" style="margin-top:10px;">
            <div class="waec-card" style="margin-bottom:0;">
                <h3 style="margin-top:0">Candidate Summary (M / F / T)</h3>
                <p class="waec-small">Status rows can overlap. For example, one candidate can have grade rows and also have an absent or withheld subject.</p>
                <table class="waec-table">
                    <thead><tr><th>Metric</th><th>Male</th><th>Female</th><th>Total</th></tr></thead>
                    <tbody>
                    <?php
                    if(count($statementCandidateSummaryRows) > 0){
                        foreach($statementCandidateSummaryRows as $row){
                            echo "<tr>";
                            echo "<td>".waec_esc($row["label"])."</td>";
                            echo "<td>".(int)$row["Male"]."</td>";
                            echo "<td>".(int)$row["Female"]."</td>";
                            echo "<td>".(int)$row["total"]."</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>No candidate rollup available yet.</td></tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
            <div class="waec-card" style="margin-bottom:0;">
                <h3 style="margin-top:0">Subjects Passed Per Candidate (A1-C6)</h3>
                <p class="waec-small">This is the same 8 to 0-style view shown in the official statement. The <strong>8+</strong> bucket includes every candidate with eight or more credits.</p>
                <table class="waec-table">
                    <thead><tr><th>Credits Passed</th><th>Male</th><th>Female</th><th>Total</th><th>% of Candidates</th></tr></thead>
                    <tbody>
                    <?php
                    if(count($statementPassBucketRows) > 0){
                        foreach($statementPassBucketRows as $row){
                            echo "<tr>";
                            echo "<td>".waec_esc($row["label"])."</td>";
                            echo "<td>".(int)$row["Male"]."</td>";
                            echo "<td>".(int)$row["Female"]."</td>";
                            echo "<td>".(int)$row["total"]."</td>";
                            echo "<td>".waec_esc($row["pct"])."%</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5'>No credit-bucket analysis available yet.</td></tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </div>

</div></details><details class="waec-detail"><summary><span>Gender performance<small>Subject/gender scope · grade filter excluded</small></span><span class="waec-detail-toggle" aria-hidden="true">+</span></summary><div class="waec-detail-body">    <div class="waec-card">
        <div class="waec-actions" style="margin-bottom:8px;">
            <button type="button" class="waec-btn-muted waec-btn-pdf" onclick="waecPrintSection('waec-gender-analysis-print', 'Gender Analysis')"><i class="fa fa-print"></i> Print Gender Analysis</button>
        </div>
        <div id="waec-gender-analysis-print">
        <h3 style="margin-top:0">Gender Analysis</h3>
        <p class="waec-small">Comparison below uses only Male and Female records from the current upload plus the current subject/gender scope. The grade filter is not applied here so pass-rate comparisons remain meaningful.</p>
        <?php
        $malePerf = isset($genderPerformanceMap["Male"]) ? $genderPerformanceMap["Male"] : array("pass_pct" => "", "avg_point" => "", "total_results" => 0, "pass_count" => 0);
        $femalePerf = isset($genderPerformanceMap["Female"]) ? $genderPerformanceMap["Female"] : array("pass_pct" => "", "avg_point" => "", "total_results" => 0, "pass_count" => 0);
        $genderPassGap = ($malePerf["pass_pct"] !== "" && $femalePerf["pass_pct"] !== "")
            ? round((float)$malePerf["pass_pct"] - (float)$femalePerf["pass_pct"], 2)
            : "";
        ?>
        <div class="waec-stats">
            <div class="waec-stat">
                <h4>Male Candidates</h4>
                <strong><?php echo (int)$genderCandidateCounts["Male"]; ?></strong>
            </div>
            <div class="waec-stat">
                <h4>Female Candidates</h4>
                <strong><?php echo (int)$genderCandidateCounts["Female"]; ?></strong>
            </div>
            <div class="waec-stat">
                <h4>Male credit %</h4>
                <strong><?php echo ($malePerf["pass_pct"] !== "" ? waec_esc($malePerf["pass_pct"])."%" : "N/A"); ?></strong>
            </div>
            <div class="waec-stat">
                <h4>Female credit %</h4>
                <strong><?php echo ($femalePerf["pass_pct"] !== "" ? waec_esc($femalePerf["pass_pct"])."%" : "N/A"); ?></strong>
            </div>
            <div class="waec-stat">
                <h4>Male Avg Point</h4>
                <strong><?php echo ($malePerf["avg_point"] !== "" ? waec_esc($malePerf["avg_point"]) : "N/A"); ?></strong>
            </div>
            <div class="waec-stat">
                <h4>Female Avg Point</h4>
                <strong><?php echo ($femalePerf["avg_point"] !== "" ? waec_esc($femalePerf["avg_point"]) : "N/A"); ?></strong>
            </div>
            <div class="waec-stat">
                <h4>Credit-rate gap (M–F)</h4>
                <strong><?php echo ($genderPassGap !== "" ? waec_esc($genderPassGap)." pp" : "N/A"); ?></strong>
            </div>
        </div>
        <div class="waec-grid" style="margin-top:10px;">
            <div class="waec-card" style="margin-bottom:0;">
                <h3 style="margin-top:0">Subject credit rates by gender</h3>
                <table class="waec-table">
                    <thead><tr><th>Subject</th><th>Male %</th><th>Female %</th><th>Gap (M-F)</th></tr></thead>
                    <tbody>
                    <?php
                    if(count($genderSubjectComparison) > 0){
                        foreach($genderSubjectComparison as $row){
                            echo "<tr>";
                            echo "<td>".waec_esc($row["subject_name"])."</td>";
                            echo "<td>".($row["Male_pct"] !== "" ? waec_esc($row["Male_pct"])."% <span class='waec-small'>(".$row["Male_entries"].")</span>" : "N/A")."</td>";
                            echo "<td>".($row["Female_pct"] !== "" ? waec_esc($row["Female_pct"])."% <span class='waec-small'>(".$row["Female_entries"].")</span>" : "N/A")."</td>";
                            echo "<td>".($row["gap"] !== "" ? waec_esc($row["gap"])." pp" : "N/A")."</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>No subject gender comparison available yet.</td></tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
            <div class="waec-card" style="margin-bottom:0;">
                <h3 style="margin-top:0">Absent / Withheld By Gender</h3>
                <table class="waec-table">
                    <thead><tr><th>Gender</th><th>Absent Students</th><th>Absent Rows</th><th>Withheld Students</th><th>Withheld Rows</th></tr></thead>
                    <tbody>
                    <?php
                    if(count($genderStatusBreakdown) > 0){
                        foreach($genderStatusBreakdown as $row){
                            echo "<tr>";
                            echo "<td>".waec_esc($row["gender_group"])."</td>";
                            echo "<td>".(int)$row["absent_students"]."</td>";
                            echo "<td>".(int)$row["absent_rows"]."</td>";
                            echo "<td>".(int)$row["withheld_students"]."</td>";
                            echo "<td>".(int)$row["withheld_rows"]."</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5'>No absent/withheld gender data available yet.</td></tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </div>

</div></details><details class="waec-detail"><summary><span>Subject grade matrix<small>Full upload · male/female counts for every grade</small></span><span class="waec-detail-toggle" aria-hidden="true">+</span></summary><div class="waec-detail-body">    <div class="waec-card">
        <div class="waec-actions" style="margin-bottom:8px;">
            <button type="button" class="waec-btn-muted waec-btn-pdf" onclick="waecPrintSection('waec-grade-matrix-print', 'Subject Grade Counts By Gender')"><i class="fa fa-print"></i> Print Grade Matrix</button>
        </div>
        <div id="waec-grade-matrix-print">
        <h3 style="margin-top:0">Subject Grade Counts By Gender</h3>
        <p class="waec-small">Each grade cell shows <strong>Male/Female</strong> counts from graded rows only.</p>
        <div class="waec-table-wrap">
            <table class="waec-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <?php
                        foreach(waec_grade_scale() as $gradeCode){
                            echo "<th>".waec_esc($gradeCode)."<br><span class='waec-small'>M/F</span></th>";
                        }
                        ?>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if(count($statementGradeMatrixRows) > 0){
                    foreach($statementGradeMatrixRows as $row){
                        echo "<tr>";
                        echo "<td>".waec_esc($row["subject_name"])."</td>";
                        foreach(waec_grade_scale() as $gradeCode){
                            $gradeKey = strtolower($gradeCode);
                            $maleKey = "g_".$gradeKey."_male";
                            $femaleKey = "g_".$gradeKey."_female";
                            echo "<td>".waec_esc(waec_gender_pair_text($row[$maleKey], $row[$femaleKey]))."</td>";
                        }
                        echo "<td>".(int)$row["total_entries"]."</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='11'>No subject grade matrix available yet.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
        </div>
    </div>

</div></details><details class="waec-detail"><summary><span>Lowest average grade points<small>Current filters · not a best-six aggregate or admission ranking</small></span><span class="waec-detail-toggle" aria-hidden="true">+</span></summary><div class="waec-detail-body"><div class="waec-card">            <h3 style="margin-top:0">Lowest Average Grade Points</h3>
            <p class="waec-small">Based on the selected result filters. Candidates may have different subject counts; this is not a best-six aggregate or an admission ranking.</p>
            <table class="waec-table">
                <thead><tr><th>Candidate No</th><th>Name</th><th>Avg Point</th><th>Subjects</th></tr></thead>
                <tbody>
                <?php
                if($topStudents && mysqli_num_rows($topStudents) > 0){
                    while($t = mysqli_fetch_array($topStudents, MYSQLI_ASSOC)){
                        echo "<tr><td>".waec_esc($t["candidate_no"])."</td><td>".waec_esc($t["student_name"])."</td><td>".waec_esc($t["avg_point"])."</td><td>".waec_esc($t["subjects"])."</td></tr>";
                    }
                } else {
                    echo "<tr><td colspan='4'>No candidate data.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
</div></details>
<?php }else{ ?><div class="waec-card waec-empty-state"><i class="fa fa-file-text-o" aria-hidden="true"></i><h3>Choose a batch to begin</h3><p>Open a saved batch above, or import a results file in Uploads.</p><a href="#waec-upload" class="waec-btn-muted">Go to uploads</a></div><?php } ?>

</section>
<section class="waec-panel" id="waec-coverage" aria-label="Coverage audit">
<div class="waec-panel-heading"><span class="waec-section-kicker">Data quality</span><h2>Account for every outcome</h2><p>Keep grades, absence, withheld results and source markers separate. Use the full-upload audit to investigate gaps.</p></div>
<?php if($currentUploadId !== ''){ ?>
    <div class="waec-card">
        <h3 style="margin-top:0">Subject Coverage Audit (All Students)</h3>
        <div class="waec-actions" style="margin-bottom:8px;">
            <button type="button" class="waec-btn-muted waec-btn-pdf" onclick="waecPrintCoverageAudit()"><i class="fa fa-print"></i> Print Coverage Audit</button>
        </div>
        <p class="waec-small">Core subjects use the full uploaded candidate population. <strong>No outcome recorded</strong> means no imported entry for that subject. Source markers <strong>* / P</strong> are preserved separately with no grade points; they are not treated as missing, failed or absent. Known-sat counts include graded and W entries only.</p>
        <div id="waec-coverage-audit-print">
        <table class="waec-table">
            <thead><tr><th>Subject</th><th>Students With Grade</th><th>Students Absent (X)</th><th>Student Results Withheld (W)</th><th>Source Status (* / P)</th><th>No Outcome Recorded</th><th>Total Registered</th><th>Known Sat (graded / W)</th><th>% Sat</th><th>% With Grade</th></tr></thead>
            <tbody>
            <?php
            $coverageGrades = array();
            foreach($subjectCoverageRows as $sc) $coverageGrades[$sc["subject_name"]] = (int)$sc["students_with_subject"];
            $coverageSubjects = array_unique(array_merge(array_keys($coverageGrades), array_keys($subjectAbsentMap), array_keys($subjectWithheldMap), array_keys($subjectSourceStatusMap)));
            sort($coverageSubjects);
            foreach($coverageSubjects as $subject){
                $graded = $coverageGrades[$subject] ?? 0;
                $absent = $subjectAbsentMap[$subject] ?? 0;
                $withheld = $subjectWithheldMap[$subject] ?? 0;
                $sourceStatus = $subjectSourceStatusMap[$subject] ?? 0;
                $isCore = in_array($subject, array("ENGLISH LANG","MATHEMATICS(CORE)","INTEGRATED SCIENCE","SOCIAL STUDIES"), true);
                $counts = waec_coverage_totals($allStudentsCount, $graded, $absent, $withheld, $sourceStatus, $isCore);
                echo "<tr><td>".waec_esc($subject).($isCore ? " <span class='waec-core-tag'>CORE</span>" : "")."</td>";
                foreach(array($graded,$absent,$withheld,$sourceStatus,$counts["missing"],$counts["registered"],$counts["sat"]) as $count) echo "<td>".(int)$count."</td>";
                echo "<td>".$counts["sat_pct"]."%</td><td>".$counts["graded_pct"]."%</td></tr>";
            }
            if(!$coverageSubjects) echo "<tr><td colspan='10'>No coverage data available yet.</td></tr>";
            ?>
            </tbody>
        </table>
        </div>
    </div>

                <?php if(count($absentStudentBreakdownRows) > 0){ ?>
                <div class="waec-card" style="margin-top:10px;padding:10px;">
                    <h3 style="margin-top:0">Absent Students Breakdown (X)</h3>
                    <table class="waec-table">
                        <thead><tr><th>Candidate No</th><th>Student Name</th><th>Absent Subjects Count</th><th>Subjects</th></tr></thead>
                        <tbody>
                        <?php
                        foreach($absentStudentBreakdownRows as $ab){
                            echo "<tr>";
                            echo "<td>".waec_esc($ab["candidate_no"])."</td>";
                            echo "<td>".waec_esc($ab["student_name"])."</td>";
                            echo "<td>".(int)$ab["absent_subject_count"]."</td>";
                            echo "<td>".waec_esc($ab["absent_subjects"])."</td>";
                            echo "</tr>";
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
<?php }else{ ?><div class="waec-card waec-empty-state"><i class="fa fa-file-text-o" aria-hidden="true"></i><h3>Choose a batch to begin</h3><p>Open a saved batch above, or import a results file in Uploads.</p><a href="#waec-upload" class="waec-btn-muted">Go to uploads</a></div><?php } ?>

</section>
<section class="waec-panel" id="waec-upload" aria-label="Upload management">
<div class="waec-panel-heading"><span class="waec-section-kicker">Results library</span><h2>Bring your results into focus</h2><p>Import a new batch or open one of your recent uploads. Existing grades are not changed when you switch views.</p></div>
    <div class="waec-card">
        <h2 class="waec-title">Import a results file</h2>
        <p class="waec-note">Upload Excel, CSV or a text-based PDF results listing.</p>
        <form method="post" enctype="multipart/form-data" action="waec-analysis.php">
            <div class="waec-batch-fields">
                <label>Examination year<input type="number" name="exam_year" min="1950" max="2100" placeholder="e.g. 2025" value="<?php echo waec_esc(isset($_POST['exam_year']) && is_scalar($_POST['exam_year']) ? $_POST['exam_year'] : ''); ?>" required></label>
                <label>Examination sitting<input type="text" name="exam_sitting" maxlength="80" placeholder="e.g. School candidates" value="<?php echo waec_esc(isset($_POST['exam_sitting']) && is_scalar($_POST['exam_sitting']) ? $_POST['exam_sitting'] : ''); ?>" required></label>
                <label>Batch label<input type="text" name="batch_label" maxlength="120" placeholder="e.g. 2025 graduating class" value="<?php echo waec_esc(isset($_POST['batch_label']) && is_scalar($_POST['batch_label']) ? $_POST['batch_label'] : ''); ?>" required></label>
            </div>
            <p class="waec-small">Use the examination year, not the upload date. You can label older uploads on the <a href="waec-comparison.php#batch-library">Compare batches</a> page.</p>
            <label for="waec-results-file" class="waec-small">Results file (Excel, CSV or PDF)</label>
            <input type="file" id="waec-results-file" name="waec_file" accept=".xlsx,.csv,.pdf" required />
            <button type="submit" name="upload_waec" class="waec-btn"><i class="fa fa-upload"></i> Upload & Analyze</button>
            <span class="waec-small">Recommended: Excel/CSV with WAEC grades A1-F9 and a Gender/Sex column.</span>
        </form>
        <details class="waec-method">
            <summary>File format and how to read the figures</summary>
            <p>For a simple CSV, use Candidate No, Student Name, Gender, Subject and Grade columns (one subject per row), or put each subject in its own column. Keep index numbers as text to retain leading zeros. Supported outcomes: A1-F9, X (absent), W (withheld) and source markers * / P. Blank cells are not failures.</p>
            <p>Credit rates on this page use graded results only. They may differ from published statistics that use all candidates who sat. Full CSV and PDF reports cover the whole upload; the filtered PDF follows the overview filters. Core-credit indicators are not admission decisions.</p>
            <p>References: <a href="https://waecgh.org/wp-content/uploads/2024/12/Release-of-Provisional-Results.pdf">WAEC's subject-performance reporting</a> and <a href="https://admissions.ug.edu.gh/undergraduate/entry-requirements">University of Ghana entry requirements</a>.</p>
        </details>
    </div>

        <div class="waec-card waec-recent-card">
            <h3 style="margin-top:0">Recent Uploads</h3>
            <table class="waec-table">
                <thead>
                    <tr>
                        <th>Upload</th>
                        <th>Type</th>
                        <th>Rows</th>
                        <th class="waec-action-col">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if($uploads && mysqli_num_rows($uploads) > 0){
                    while($u = mysqli_fetch_array($uploads, MYSQLI_ASSOC)){
                        echo "<tr>";
                        echo "<td>".waec_esc($u["originalname"])."<br><span class='waec-small'>".waec_esc(wac_ready($u) ? wac_title($u) : 'Batch details not yet set')."<br>".waec_esc($u["datetimeentry"])."</span></td>";
                        echo "<td>".waec_esc(strtoupper($u["fileext"]))."</td>";
                        echo "<td>".waec_esc($u["totalrows"])."</td>";
                        echo "<td class='waec-action-col'><div class='waec-action-wrap'>";
                        echo "<a class='waec-btn-muted waec-action-btn' href='waec-analysis.php?uploadid=".urlencode($u["uploadid"])."'>View</a>";
                        echo "<form method='post' action='waec-analysis.php' onsubmit=\"return confirm('Delete this upload and all analyzed WAEC rows?');\">";
                        echo "<input type='hidden' name='delete_uploadid' value='".waec_esc($u["uploadid"])."'>";
                        echo "<button type='submit' name='delete_upload' class='waec-btn-muted waec-action-btn' style='border-color:#ef4444;color:#b91c1c;'><i class='fa fa-trash'></i>&nbsp;Delete</button>";
                        echo "</form>";
                        echo "</div></td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='4'>No WAEC upload yet.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>


</section>
        </div>
    </div>
</main>
<script src="js/waec-analysis-workspace.js?v=20261011"></script>
<script>
function waecPrintSection(elementId, title){
    var el = document.getElementById(elementId);
    if(!el){ return; }
    var w = window.open("", "_blank", "width=1100,height=800");
    if(!w){ return; }
    var html = ""
        + "<!DOCTYPE html><html><head><title>" + (title || "Print") + "</title>"
        + "<style>"
        + "body{font-family:Arial,sans-serif;padding:16px;color:#111827;}"
        + "h2{margin:0 0 10px 0;font-size:18px;}"
        + "h3{margin:0 0 10px 0;font-size:16px;}"
        + ".waec-small{font-size:12px;color:#475569;}"
        + ".waec-badge{display:inline-block;background:#e2e8f0;color:#0f172a;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;}"
        + ".waec-stats{display:flex;flex-wrap:wrap;gap:10px;margin:12px 0;}"
        + ".waec-stat{border:1px solid #cbd5e1;border-radius:10px;padding:10px;min-width:150px;flex:1 1 150px;background:#f8fafc;}"
        + ".waec-stat h4{margin:0 0 6px 0;font-size:12px;color:#475569;font-weight:700;}"
        + ".waec-stat strong{font-size:18px;color:#0f172a;}"
        + ".waec-grid{display:block;}"
        + ".waec-card{margin:12px 0 0 0;padding:0;border:0;background:none;box-shadow:none;}"
        + ".waec-kpi-alert{margin:10px 0;padding:10px;border:1px solid #cbd5e1;border-radius:10px;background:#f8fafc;font-size:12px;}"
        + "table{width:100%;border-collapse:collapse;font-size:12px;}"
        + "th,td{border:1px solid #cbd5e1;padding:6px;text-align:left;}"
        + "th{background:#f1f5f9;}"
        + "@media print{body{padding:0;}}"
        + "</style></head><body>"
        + "<h2>" + (title || "Print") + "</h2>"
        + el.innerHTML
        + "</body></html>";
    w.document.open();
    w.document.write(html);
    w.document.close();
    w.focus();
    w.print();
}

function waecPrintCoverageAudit(){
    waecPrintSection("waec-coverage-audit-print", "Subject Coverage Audit");
}
</script>
</body>
</html>
