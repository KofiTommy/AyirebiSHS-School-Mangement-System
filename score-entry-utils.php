<?php
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
function score_entry_build_url($page, $classId, $termId, $batchId, $subjectId, $prefillTotal = ""){
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
function score_entry_session_label($dateTimeValue, $batchLabel, $termValue){
    $yearValue = "";
    if(trim((string)$dateTimeValue) !== ""){
        $time = strtotime((string)$dateTimeValue);
        if($time){
            $yearValue = date("Y", $time);
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
