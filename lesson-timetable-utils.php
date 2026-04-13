<?php
if(!function_exists('xschool_schema_cache_is_fresh')){
function xschool_schema_cache_is_fresh($key, $ttlSeconds = 900){
    static $memoryCache = array();
    $key = trim((string)$key);
    if($key === ""){
        return false;
    }
    if(isset($memoryCache[$key])){
        return $memoryCache[$key];
    }
    if(PHP_SAPI === 'cli' || !function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE){
        $memoryCache[$key] = false;
        return false;
    }
    $cacheBag = isset($_SESSION['_xschool_schema_cache']) && is_array($_SESSION['_xschool_schema_cache'])
        ? $_SESSION['_xschool_schema_cache']
        : array();
    $isFresh = isset($cacheBag[$key]) && ((int)$cacheBag[$key] + (int)$ttlSeconds) > time();
    $memoryCache[$key] = $isFresh;
    return $isFresh;
}
}

if(!function_exists('xschool_schema_cache_mark')){
function xschool_schema_cache_mark($key){
    static $memoryCache = array();
    $key = trim((string)$key);
    if($key === ""){
        return;
    }
    $memoryCache[$key] = true;
    if(PHP_SAPI === 'cli' || !function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE){
        return;
    }
    if(!isset($_SESSION['_xschool_schema_cache']) || !is_array($_SESSION['_xschool_schema_cache'])){
        $_SESSION['_xschool_schema_cache'] = array();
    }
    $_SESSION['_xschool_schema_cache'][$key] = time();
}
}

if(!function_exists('lesson_timetable_is_admin')){
function lesson_timetable_is_admin(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "administrator" &&
        ($_SESSION['SYSTEMTYPE'] === "normal_user" || $_SESSION['SYSTEMTYPE'] === "super_user");
}
}

if(!function_exists('lesson_timetable_is_teacher')){
function lesson_timetable_is_teacher(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "user" &&
        $_SESSION['SYSTEMTYPE'] === "Teacher";
}
}

if(!function_exists('lesson_timetable_landing_page')){
function lesson_timetable_landing_page(){
    if(lesson_timetable_is_admin()){
        return ($_SESSION['SYSTEMTYPE'] === "super_user") ? "super.php" : "admin.php";
    }
    if(lesson_timetable_is_teacher()){
        return "teacher-page.php";
    }
    if(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL'] === "user" && $_SESSION['SYSTEMTYPE'] === "User"){
        return "user.php";
    }
    if(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL'] === "user" && $_SESSION['SYSTEMTYPE'] === "Student"){
        return "student-page.php";
    }
    return "index.php";
}
}

if(!function_exists('lesson_timetable_can_manage')){
function lesson_timetable_can_manage($con = null){
    if(lesson_timetable_is_admin()){
        return true;
    }
    if(!$con || !function_exists('um_current_user_can_access_module')){
        return false;
    }
    return um_current_user_can_access_module($con, 'lesson_timetable');
}
}

if(!function_exists('lesson_timetable_can_view')){
function lesson_timetable_can_view($con = null){
    if(lesson_timetable_is_admin() || lesson_timetable_is_teacher()){
        return true;
    }
    if(!$con || !function_exists('um_current_user_can_access_module')){
        return false;
    }
    return um_current_user_can_access_module($con, 'lesson_timetable');
}
}

if(!function_exists('lesson_timetable_escape')){
function lesson_timetable_escape($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
}

if(!function_exists('lesson_timetable_make_id')){
function lesson_timetable_make_id($prefix = 'LESSON'){
    $prefix = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string)$prefix));
    if($prefix === ''){
        $prefix = 'LESSON';
    }
    return $prefix.'_'.strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 24));
}
}

if(!function_exists('lesson_timetable_weekdays')){
function lesson_timetable_weekdays(){
    return array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
}
}

if(!function_exists('lesson_timetable_day_sort_sql')){
function lesson_timetable_day_sort_sql($column = 'lt.weekday'){
    $column = trim((string)$column);
    if($column === ''){
        $column = 'lt.weekday';
    }
    return "CASE ".$column."
        WHEN 'Monday' THEN 1
        WHEN 'Tuesday' THEN 2
        WHEN 'Wednesday' THEN 3
        WHEN 'Thursday' THEN 4
        WHEN 'Friday' THEN 5
        WHEN 'Saturday' THEN 6
        ELSE 7
    END";
}
}

if(!function_exists('lesson_timetable_normalize_weekday')){
function lesson_timetable_normalize_weekday($value){
    $value = strtolower(trim((string)$value));
    foreach(lesson_timetable_weekdays() as $day){
        if(strtolower($day) === $value){
            return $day;
        }
    }
    return '';
}
}

if(!function_exists('lesson_timetable_valid_time')){
function lesson_timetable_valid_time($value){
    $value = trim((string)$value);
    return (bool)preg_match('/^\d{2}:\d{2}$/', $value);
}
}

if(!function_exists('lesson_timetable_format_time')){
function lesson_timetable_format_time($value){
    $time = strtotime((string)$value);
    return $time ? date('g:i A', $time) : (string)$value;
}
}

if(!function_exists('lesson_timetable_duration_minutes')){
function lesson_timetable_duration_minutes($startTime, $endTime){
    $start = strtotime((string)$startTime);
    $end = strtotime((string)$endTime);
    if(!$start || !$end || $end <= $start){
        return 0;
    }
    return (int)round(($end - $start) / 60);
}
}

if(!function_exists('lesson_timetable_slot_key')){
function lesson_timetable_slot_key($startTime, $endTime){
    return trim((string)$startTime).'__'.trim((string)$endTime);
}
}

if(!function_exists('lesson_timetable_extract_time_slots')){
function lesson_timetable_extract_time_slots($rows){
    $slots = array();
    if(!is_array($rows)){
        return $slots;
    }
    foreach($rows as $row){
        $startTime = isset($row['starttime']) ? trim((string)$row['starttime']) : '';
        $endTime = isset($row['endtime']) ? trim((string)$row['endtime']) : '';
        if($startTime === '' || $endTime === ''){
            continue;
        }
        $slotKey = lesson_timetable_slot_key($startTime, $endTime);
        if(!isset($slots[$slotKey])){
            $slots[$slotKey] = array(
                'key' => $slotKey,
                'starttime' => $startTime,
                'endtime' => $endTime,
                'label' => lesson_timetable_format_time($startTime).' - '.lesson_timetable_format_time($endTime),
                'duration' => lesson_timetable_duration_minutes($startTime, $endTime)
            );
        }
    }
    uasort($slots, function($left, $right){
        $leftStart = isset($left['starttime']) ? (string)$left['starttime'] : '';
        $rightStart = isset($right['starttime']) ? (string)$right['starttime'] : '';
        if($leftStart === $rightStart){
            $leftEnd = isset($left['endtime']) ? (string)$left['endtime'] : '';
            $rightEnd = isset($right['endtime']) ? (string)$right['endtime'] : '';
            return strcmp($leftEnd, $rightEnd);
        }
        return strcmp($leftStart, $rightStart);
    });
    return array_values($slots);
}
}

if(!function_exists('lesson_timetable_group_rows_by_day_and_slot')){
function lesson_timetable_group_rows_by_day_and_slot($rows){
    $matrix = array();
    foreach(lesson_timetable_weekdays() as $day){
        $matrix[$day] = array();
    }
    if(!is_array($rows)){
        return $matrix;
    }
    foreach($rows as $row){
        $day = isset($row['weekday']) ? lesson_timetable_normalize_weekday($row['weekday']) : '';
        if($day === ''){
            $day = 'Monday';
        }
        if(!isset($matrix[$day])){
            $matrix[$day] = array();
        }
        $slotKey = lesson_timetable_slot_key(
            isset($row['starttime']) ? $row['starttime'] : '',
            isset($row['endtime']) ? $row['endtime'] : ''
        );
        if(!isset($matrix[$day][$slotKey])){
            $matrix[$day][$slotKey] = array();
        }
        $matrix[$day][$slotKey][] = $row;
    }
    return $matrix;
}
}

if(!function_exists('lesson_timetable_visual_tokens')){
function lesson_timetable_visual_tokens($seed){
    $palettes = array(
        array('accent' => '#2563eb', 'soft' => '#dbeafe', 'surface' => '#eff6ff', 'ink' => '#1d4ed8'),
        array('accent' => '#0891b2', 'soft' => '#cffafe', 'surface' => '#ecfeff', 'ink' => '#0e7490'),
        array('accent' => '#0f766e', 'soft' => '#ccfbf1', 'surface' => '#f0fdfa', 'ink' => '#0f766e'),
        array('accent' => '#16a34a', 'soft' => '#dcfce7', 'surface' => '#f0fdf4', 'ink' => '#15803d'),
        array('accent' => '#9333ea', 'soft' => '#f3e8ff', 'surface' => '#faf5ff', 'ink' => '#7e22ce'),
        array('accent' => '#c026d3', 'soft' => '#fae8ff', 'surface' => '#fdf4ff', 'ink' => '#a21caf'),
        array('accent' => '#ea580c', 'soft' => '#ffedd5', 'surface' => '#fff7ed', 'ink' => '#c2410c'),
        array('accent' => '#dc2626', 'soft' => '#fee2e2', 'surface' => '#fef2f2', 'ink' => '#b91c1c'),
        array('accent' => '#4f46e5', 'soft' => '#e0e7ff', 'surface' => '#eef2ff', 'ink' => '#4338ca'),
        array('accent' => '#b45309', 'soft' => '#fef3c7', 'surface' => '#fffbeb', 'ink' => '#92400e')
    );
    $seed = strtolower(trim((string)$seed));
    if($seed === ''){
        $seed = 'lesson';
    }
    $index = hexdec(substr(md5($seed), 0, 8)) % count($palettes);
    return $palettes[$index];
}
}

if(!function_exists('lesson_timetable_today_name')){
function lesson_timetable_today_name(){
    return date('l');
}
}

if(!function_exists('ensure_lesson_timetable_table')){
function ensure_lesson_timetable_table($con){
    if(xschool_schema_cache_is_fresh('schema_lesson_timetable_v1')){
        return;
    }
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tbllessontimetable (
        lessonid VARCHAR(40) NOT NULL PRIMARY KEY,
        classid VARCHAR(30) NOT NULL,
        batchid VARCHAR(30) NOT NULL,
        termname INT NOT NULL,
        weekday VARCHAR(20) NOT NULL,
        subjectid VARCHAR(30) NOT NULL,
        teacherid VARCHAR(30) NOT NULL,
        starttime TIME NOT NULL,
        endtime TIME NOT NULL,
        location VARCHAR(120) DEFAULT '',
        note VARCHAR(255) DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        updatedat DATETIME NULL,
        recordedby VARCHAR(30) NOT NULL,
        INDEX idx_lessontimetable_class (classid,batchid,termname),
        INDEX idx_lessontimetable_teacher (teacherid,weekday),
        INDEX idx_lessontimetable_day (weekday,starttime),
        INDEX idx_lessontimetable_status (status)
    )");
    xschool_schema_cache_mark('schema_lesson_timetable_v1');
}
}

if(!function_exists('lesson_timetable_default_batch_id')){
function lesson_timetable_default_batch_id($con){
    $result = mysqli_query($con, "SELECT batchid FROM tblbatch WHERE status='active' ORDER BY datetimeentry DESC LIMIT 1");
    if($result && ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))){
        return (string)$row['batchid'];
    }
    $result = mysqli_query($con, "SELECT batchid FROM tblbatch ORDER BY datetimeentry DESC LIMIT 1");
    if($result && ($row = mysqli_fetch_array($result, MYSQLI_ASSOC))){
        return (string)$row['batchid'];
    }
    return '';
}
}

if(!function_exists('lesson_timetable_fetch_assignment_options')){
function lesson_timetable_fetch_assignment_options($con, $batchId = '', $termName = '', $classId = ''){
    $where = " WHERE sa.status='active' AND su.status='active' AND su.systemtype='Teacher' ";
    if(trim((string)$batchId) !== ''){
        $where .= " AND sa.batchid='".mysqli_real_escape_string($con, trim((string)$batchId))."'";
    }
    if(trim((string)$classId) !== ''){
        $where .= " AND sa.classid='".mysqli_real_escape_string($con, trim((string)$classId))."'";
    }
    if(trim((string)$termName) !== ''){
        $where .= " AND sa.termname='".((int)$termName)."'";
    }

    $sql = "SELECT DISTINCT
                sa.userid AS teacherid,
                sa.classid,
                sa.batchid,
                sa.termname,
                sc.subjectid,
                sub.subject,
                ce.class_name,
                bh.batch,
                su.firstname,
                su.othernames,
                su.surname
            FROM tblsubjectassignment sa
            INNER JOIN tblsystemuser su ON su.userid=sa.userid
            INNER JOIN tblsubjectclassification sc ON sc.classificationid=sa.classificationid
            INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
            INNER JOIN tblclassentry ce ON ce.class_entryid=sa.classid
            INNER JOIN tblbatch bh ON bh.batchid=sa.batchid
            ".$where."
            ORDER BY ce.class_name ASC, sub.subject ASC, su.firstname ASC, su.surname ASC";

    $rows = array();
    $result = mysqli_query($con, $sql);
    if($result){
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $row['teacher_name'] = trim($row['firstname'].' '.$row['othernames'].' '.$row['surname']);
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('lesson_timetable_teacher_subject_is_valid')){
function lesson_timetable_teacher_subject_is_valid($con, $teacherId, $subjectId, $classId, $batchId, $termName){
    $teacherId = mysqli_real_escape_string($con, trim((string)$teacherId));
    $subjectId = mysqli_real_escape_string($con, trim((string)$subjectId));
    $classId = mysqli_real_escape_string($con, trim((string)$classId));
    $batchId = mysqli_real_escape_string($con, trim((string)$batchId));
    $termName = (int)$termName;
    if($teacherId === '' || $subjectId === '' || $classId === '' || $batchId === '' || $termName <= 0){
        return false;
    }
    $sql = "SELECT sa.assignmentid
        FROM tblsubjectassignment sa
        INNER JOIN tblsubjectclassification sc ON sc.classificationid=sa.classificationid
        WHERE sa.userid='$teacherId'
          AND sa.classid='$classId'
          AND sa.batchid='$batchId'
          AND sa.termname='$termName'
          AND sa.status='active'
          AND sc.subjectid='$subjectId'
        LIMIT 1";
    $result = mysqli_query($con, $sql);
    return ($result && mysqli_num_rows($result) > 0);
}
}

if(!function_exists('lesson_timetable_has_class_overlap')){
function lesson_timetable_has_class_overlap($con, $classId, $batchId, $termName, $weekday, $startTime, $endTime, $excludeLessonId = ''){
    $classId = mysqli_real_escape_string($con, trim((string)$classId));
    $batchId = mysqli_real_escape_string($con, trim((string)$batchId));
    $weekday = mysqli_real_escape_string($con, lesson_timetable_normalize_weekday($weekday));
    $startTime = mysqli_real_escape_string($con, trim((string)$startTime));
    $endTime = mysqli_real_escape_string($con, trim((string)$endTime));
    $excludeSql = '';
    if(trim((string)$excludeLessonId) !== ''){
        $excludeSql = " AND lessonid!='".mysqli_real_escape_string($con, trim((string)$excludeLessonId))."'";
    }
    $sql = "SELECT lessonid
        FROM tbllessontimetable
        WHERE status='active'
          AND classid='$classId'
          AND batchid='$batchId'
          AND termname='".((int)$termName)."'
          AND weekday='$weekday'
          AND starttime<'$endTime'
          AND endtime>'$startTime'
          $excludeSql
        LIMIT 1";
    $result = mysqli_query($con, $sql);
    return ($result && mysqli_num_rows($result) > 0);
}
}

if(!function_exists('lesson_timetable_has_teacher_overlap')){
function lesson_timetable_has_teacher_overlap($con, $teacherId, $weekday, $startTime, $endTime, $excludeLessonId = ''){
    $teacherId = mysqli_real_escape_string($con, trim((string)$teacherId));
    $weekday = mysqli_real_escape_string($con, lesson_timetable_normalize_weekday($weekday));
    $startTime = mysqli_real_escape_string($con, trim((string)$startTime));
    $endTime = mysqli_real_escape_string($con, trim((string)$endTime));
    $excludeSql = '';
    if(trim((string)$excludeLessonId) !== ''){
        $excludeSql = " AND lessonid!='".mysqli_real_escape_string($con, trim((string)$excludeLessonId))."'";
    }
    $sql = "SELECT lessonid
        FROM tbllessontimetable
        WHERE status='active'
          AND teacherid='$teacherId'
          AND weekday='$weekday'
          AND starttime<'$endTime'
          AND endtime>'$startTime'
          $excludeSql
        LIMIT 1";
    $result = mysqli_query($con, $sql);
    return ($result && mysqli_num_rows($result) > 0);
}
}

if(!function_exists('lesson_timetable_fetch_rows')){
function lesson_timetable_fetch_rows($con, $filters = array()){
    $where = " WHERE lt.status='active' ";
    if(isset($filters['batchid']) && trim((string)$filters['batchid']) !== ''){
        $where .= " AND lt.batchid='".mysqli_real_escape_string($con, trim((string)$filters['batchid']))."'";
    }
    if(isset($filters['classid']) && trim((string)$filters['classid']) !== ''){
        $where .= " AND lt.classid='".mysqli_real_escape_string($con, trim((string)$filters['classid']))."'";
    }
    if(isset($filters['teacherid']) && trim((string)$filters['teacherid']) !== ''){
        $where .= " AND lt.teacherid='".mysqli_real_escape_string($con, trim((string)$filters['teacherid']))."'";
    }
    if(isset($filters['subjectid']) && trim((string)$filters['subjectid']) !== ''){
        $where .= " AND lt.subjectid='".mysqli_real_escape_string($con, trim((string)$filters['subjectid']))."'";
    }
    if(isset($filters['termname']) && trim((string)$filters['termname']) !== ''){
        $where .= " AND lt.termname='".((int)$filters['termname'])."'";
    }
    if(isset($filters['weekday']) && trim((string)$filters['weekday']) !== ''){
        $weekday = lesson_timetable_normalize_weekday($filters['weekday']);
        if($weekday !== ''){
            $where .= " AND lt.weekday='".mysqli_real_escape_string($con, $weekday)."'";
        }
    }
    $limitSql = '';
    if(isset($filters['limit']) && (int)$filters['limit'] > 0){
        $limitSql = " LIMIT ".((int)$filters['limit']);
    }
    $sql = "SELECT
                lt.*,
                ce.class_name,
                bh.batch,
                sub.subject,
                su.firstname,
                su.othernames,
                su.surname
            FROM tbllessontimetable lt
            INNER JOIN tblclassentry ce ON ce.class_entryid=lt.classid
            INNER JOIN tblbatch bh ON bh.batchid=lt.batchid
            INNER JOIN tblsubject sub ON sub.subjectid=lt.subjectid
            INNER JOIN tblsystemuser su ON su.userid=lt.teacherid
            ".$where."
            ORDER BY bh.datetimeentry DESC, ce.class_name ASC, lt.termname ASC, ".lesson_timetable_day_sort_sql('lt.weekday').", lt.starttime ASC".$limitSql;
    $rows = array();
    $result = mysqli_query($con, $sql);
    if($result){
        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
            $row['teacher_name'] = trim($row['firstname'].' '.$row['othernames'].' '.$row['surname']);
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if(!function_exists('lesson_timetable_group_rows_by_day')){
function lesson_timetable_group_rows_by_day($rows){
    $grouped = array();
    foreach(lesson_timetable_weekdays() as $day){
        $grouped[$day] = array();
    }
    if(!is_array($rows)){
        return $grouped;
    }
    foreach($rows as $row){
        $day = isset($row['weekday']) ? lesson_timetable_normalize_weekday($row['weekday']) : '';
        if($day === ''){
            $day = 'Monday';
        }
        if(!isset($grouped[$day])){
            $grouped[$day] = array();
        }
        $grouped[$day][] = $row;
    }
    return $grouped;
}
}

if(!function_exists('lesson_timetable_fetch_teacher_today_rows')){
function lesson_timetable_fetch_teacher_today_rows($con, $teacherId, $batchId = ''){
    $filters = array(
        'teacherid' => $teacherId,
        'weekday' => lesson_timetable_today_name(),
    );
    if(trim((string)$batchId) !== ''){
        $filters['batchid'] = $batchId;
    }
    return lesson_timetable_fetch_rows($con, $filters);
}
}
?>
