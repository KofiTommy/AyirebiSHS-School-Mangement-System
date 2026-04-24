<?php
if(!function_exists('semester_registry_column_exists')){
function semester_registry_column_exists($con, $tableName, $columnName){
    $tableSafe = mysqli_real_escape_string($con, (string)$tableName);
    $columnSafe = mysqli_real_escape_string($con, (string)$columnName);
    $sql = "SHOW COLUMNS FROM `".$tableSafe."` LIKE '".$columnSafe."'";
    $result = mysqli_query($con, $sql);
    return ($result && mysqli_num_rows($result) > 0);
}
}

if(!function_exists('semester_registry_ensure_academic_year_column')){
function semester_registry_ensure_academic_year_column($con){
    if(!semester_registry_column_exists($con, 'tbltermregistry', 'academicyear')){
        @mysqli_query($con, "ALTER TABLE tbltermregistry ADD COLUMN academicyear VARCHAR(10) NOT NULL DEFAULT '' AFTER batchid");
        @mysqli_query($con, "UPDATE tbltermregistry SET academicyear=CAST(YEAR(datetimeentry) AS CHAR) WHERE TRIM(COALESCE(academicyear,''))=''");
    }
}
}

if(!function_exists('semester_registry_normalize_year')){
function semester_registry_normalize_year($value){
    $value = trim((string)$value);
    if($value === ''){
        return '';
    }
    if(preg_match('/^\d{4}$/', $value) !== 1){
        return '';
    }
    $yearValue = (int)$value;
    if($yearValue < 2000 || $yearValue > 2100){
        return '';
    }
    return (string)$yearValue;
}
}

if(!function_exists('semester_registry_resolved_year_sql')){
function semester_registry_resolved_year_sql($alias = 'tr'){
    $alias = trim((string)$alias);
    if($alias === ''){
        $alias = 'tr';
    }
    return "COALESCE(NULLIF(TRIM(".$alias.".academicyear),''), CAST(YEAR(".$alias.".datetimeentry) AS CHAR))";
}
}

if(!function_exists('semester_registry_assignment_year_sql')){
function semester_registry_assignment_year_sql($alias = 'sa'){
    $alias = trim((string)$alias);
    if($alias === ''){
        $alias = 'sa';
    }
    return "CAST(YEAR(".$alias.".datetimeentry) AS CHAR)";
}
}

if(!function_exists('score_entry_esc')){
function score_entry_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
}

if(!function_exists('score_entry_alert')){
function score_entry_alert($type, $message){
    $class = "score-entry-alert score-entry-alert--info";
    if($type === "success"){
        $class = "score-entry-alert score-entry-alert--success";
    }elseif($type === "error"){
        $class = "score-entry-alert score-entry-alert--error";
    }elseif($type === "warning"){
        $class = "score-entry-alert score-entry-alert--warning";
    }
    return "<div class=\"$class\">".score_entry_esc($message)."</div>";
}
}

if(!function_exists('score_entry_build_url')){
function score_entry_build_url($page, $classId, $termId, $batchId, $subjectId, $prefillTotal = "", $yearId = ""){
    $params = array();
    if(trim((string)$classId) !== ""){
        $params['class_ID'] = trim((string)$classId);
    }
    if(trim((string)$termId) !== ""){
        $params['term_ID'] = trim((string)$termId);
    }
    if(trim((string)$batchId) !== ""){
        $params['batch_ID'] = trim((string)$batchId);
    }
    if(trim((string)$subjectId) !== ""){
        $params['subject_ID'] = trim((string)$subjectId);
    }
    if(trim((string)$yearId) !== ""){
        $params['year_ID'] = trim((string)$yearId);
    }
    if(trim((string)$prefillTotal) !== ""){
        $params['prefill_total'] = trim((string)$prefillTotal);
    }
    if(empty($params)){
        return $page;
    }
    return $page."?".http_build_query($params);
}
}

if(!function_exists('score_entry_status_meta')){
function score_entry_status_meta($totalStudents, $savedStudents){
    $totalStudents = (int)$totalStudents;
    $savedStudents = (int)$savedStudents;
    $pendingStudents = max($totalStudents - $savedStudents, 0);

    if($totalStudents === 0){
        return array(
            "label" => "No Students",
            "class" => "score-entry-status score-entry-status--empty",
            "pending" => 0
        );
    }

    if($pendingStudents === 0){
        return array(
            "label" => "Completed",
            "class" => "score-entry-status score-entry-status--done",
            "pending" => 0
        );
    }

    if($savedStudents > 0){
        return array(
            "label" => "Continue",
            "class" => "score-entry-status score-entry-status--progress",
            "pending" => $pendingStudents
        );
    }

    return array(
        "label" => "Start",
        "class" => "score-entry-status score-entry-status--start",
        "pending" => $pendingStudents
    );
}
}

if(!function_exists('score_entry_session_label')){
function score_entry_session_label($dateTimeValue, $batchLabel, $termValue, $yearOverride = ""){
    $yearValue = trim((string)$yearOverride);
    if($yearValue === ""){
        if(trim((string)$dateTimeValue) !== ""){
            $time = strtotime((string)$dateTimeValue);
            if($time){
                $yearValue = date("Y", $time);
            }
        }
    }
    if($yearValue === ""){
        $yearValue = date("Y");
    }

    $batchText = trim((string)$batchLabel);
    if($batchText === ""){
        $batchText = "Not Set";
    }

    $termText = trim((string)$termValue);
    if($termText === ""){
        $termText = "Not Set";
    }

    return trim($yearValue." Batch ".$batchText." Semester ".$termText);
}
}
