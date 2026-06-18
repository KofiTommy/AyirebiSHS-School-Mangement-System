<?php
session_start();
include("dbstring.php");
include_once("user-management-utils.php");

header("Content-Type: text/html; charset=UTF-8");

function ags_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function ags_trim_name($row){
    return trim((string)($row['firstname'] ?? '')." ".(string)($row['othernames'] ?? '')." ".(string)($row['surname'] ?? ''));
}

function ags_feedback($title, $message, $icon = "fa-search"){
    return "<div class='desktop-search-feedback'><i class='fa ".$icon."'></i><div><strong>".ags_esc($title)."</strong><span>".ags_esc($message)."</span></div></div>";
}

if(!function_exists('um_is_admin_manager') || !um_is_admin_manager()){
    http_response_code(403);
    echo ags_feedback("Access denied", "Desktop search is available only to administrators.", "fa-lock");
    exit();
}

$query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if($query === ''){
    echo ags_feedback("Start typing", "Search students, staff, classes, batches, and tools from here.");
    exit();
}
if(strlen($query) < 2){
    echo ags_feedback("Keep typing", "Use at least 2 characters to search the desktop.");
    exit();
}

$like = "%".$query."%";
$prefix = $query."%";
$upper = strtoupper($query);
$queryLower = strtolower($query);
$totalShown = 0;

$studentRows = array();
$studentSql = "SELECT
        su.userid,
        su.firstname,
        su.othernames,
        su.surname,
        su.mobile,
        ce.class_name,
        bh.batch
    FROM tblsystemuser su
    LEFT JOIN (
        SELECT cl.userid, cl.class_entryid, cl.batchid
        FROM tblclass cl
        INNER JOIN (
            SELECT userid, MAX(datetimeentry) AS max_datetime
            FROM tblclass
            WHERE status='active'
            GROUP BY userid
        ) latest
            ON latest.userid = cl.userid
           AND latest.max_datetime = cl.datetimeentry
        WHERE cl.status='active'
    ) current_class ON current_class.userid = su.userid
    LEFT JOIN tblclassentry ce ON ce.class_entryid = current_class.class_entryid
    LEFT JOIN tblbatch bh ON bh.batchid = current_class.batchid
    WHERE su.systemtype='Student'
      AND su.status='active'
      AND (
          su.userid LIKE ?
          OR su.firstname LIKE ?
          OR su.othernames LIKE ?
          OR su.surname LIKE ?
          OR CONCAT_WS(' ', su.firstname, su.othernames, su.surname) LIKE ?
      )
    ORDER BY
      CASE
        WHEN su.userid = ? THEN 0
        WHEN UPPER(CONCAT_WS(' ', su.firstname, su.othernames, su.surname)) = ? THEN 1
        WHEN su.userid LIKE ? THEN 2
        ELSE 3
      END,
      su.firstname ASC,
      su.othernames ASC,
      su.surname ASC
    LIMIT 6";
$studentStmt = mysqli_prepare($con, $studentSql);
if($studentStmt){
    mysqli_stmt_bind_param($studentStmt, "ssssssss", $like, $like, $like, $like, $like, $query, $upper, $prefix);
    mysqli_stmt_execute($studentStmt);
    $studentRes = mysqli_stmt_get_result($studentStmt);
    if($studentRes){
        while($row = mysqli_fetch_array($studentRes, MYSQLI_ASSOC)){
            $studentRows[] = $row;
        }
    }
    mysqli_stmt_close($studentStmt);
}

$staffRows = array();
$staffSql = "SELECT
        su.userid,
        su.firstname,
        su.othernames,
        su.surname,
        su.mobile,
        su.systemtype,
        su.accesslevel
    FROM tblsystemuser su
    WHERE su.systemtype <> 'Student'
      AND su.status='active'
      AND (
          su.userid LIKE ?
          OR su.firstname LIKE ?
          OR su.othernames LIKE ?
          OR su.surname LIKE ?
          OR CONCAT_WS(' ', su.firstname, su.othernames, su.surname) LIKE ?
          OR su.systemtype LIKE ?
      )
    ORDER BY
      CASE
        WHEN su.userid = ? THEN 0
        WHEN UPPER(CONCAT_WS(' ', su.firstname, su.othernames, su.surname)) = ? THEN 1
        WHEN su.userid LIKE ? THEN 2
        ELSE 3
      END,
      su.firstname ASC,
      su.othernames ASC,
      su.surname ASC
    LIMIT 6";
$staffStmt = mysqli_prepare($con, $staffSql);
if($staffStmt){
    mysqli_stmt_bind_param($staffStmt, "sssssssss", $like, $like, $like, $like, $like, $like, $query, $upper, $prefix);
    mysqli_stmt_execute($staffStmt);
    $staffRes = mysqli_stmt_get_result($staffStmt);
    if($staffRes){
        while($row = mysqli_fetch_array($staffRes, MYSQLI_ASSOC)){
            $staffRows[] = $row;
        }
    }
    mysqli_stmt_close($staffStmt);
}

$classRows = array();
$classSql = "SELECT
        ce.class_entryid,
        ce.class_name,
        COUNT(DISTINCT cl.userid) AS active_students
    FROM tblclassentry ce
    LEFT JOIN tblclass cl
        ON cl.class_entryid = ce.class_entryid
       AND cl.status='active'
    WHERE ce.class_name LIKE ?
    GROUP BY ce.class_entryid, ce.class_name
    ORDER BY
      CASE
        WHEN ce.class_name = ? THEN 0
        WHEN ce.class_name LIKE ? THEN 1
        ELSE 2
      END,
      ce.class_name ASC
    LIMIT 6";
$classStmt = mysqli_prepare($con, $classSql);
if($classStmt){
    mysqli_stmt_bind_param($classStmt, "sss", $like, $query, $prefix);
    mysqli_stmt_execute($classStmt);
    $classRes = mysqli_stmt_get_result($classStmt);
    if($classRes){
        while($row = mysqli_fetch_array($classRes, MYSQLI_ASSOC)){
            $classRows[] = $row;
        }
    }
    mysqli_stmt_close($classStmt);
}

$batchRows = array();
$batchSql = "SELECT batchid, batch
    FROM tblbatch
    WHERE batch LIKE ?
    ORDER BY
      CASE
        WHEN batch = ? THEN 0
        WHEN batch LIKE ? THEN 1
        ELSE 2
      END,
      datetimeentry DESC
    LIMIT 6";
$batchStmt = mysqli_prepare($con, $batchSql);
if($batchStmt){
    mysqli_stmt_bind_param($batchStmt, "sss", $like, $query, $prefix);
    mysqli_stmt_execute($batchStmt);
    $batchRes = mysqli_stmt_get_result($batchStmt);
    if($batchRes){
        while($row = mysqli_fetch_array($batchRes, MYSQLI_ASSOC)){
            $batchRows[] = $row;
        }
    }
    mysqli_stmt_close($batchStmt);
}

$toolCatalog = array(
    array("href" => "search.php", "label" => "Search Students", "icon" => "fa-search", "description" => "Open the full student search page.", "keywords" => "student search finder records"),
    array("href" => "student-history.php", "label" => "Student Transcript", "icon" => "fa-history", "description" => "View a student's full transcript.", "keywords" => "transcript history results"),
    array("href" => "promotion-center.php", "label" => "Promote Students", "icon" => "fa-level-up", "description" => "Run student promotion for the next class.", "keywords" => "promotion continuing students"),
    array("href" => "register-student.php", "label" => "Register Student", "icon" => "fa-user", "description" => "Add a new student record.", "keywords" => "student admission register"),
    array("href" => "register-teacher.php", "label" => "Register Teacher", "icon" => "fa-user-plus", "description" => "Add a new teacher record.", "keywords" => "teacher staff register"),
    array("href" => "viewstudents.php", "label" => "View Students", "icon" => "fa-graduation-cap", "description" => "Review student records in bulk.", "keywords" => "students list"),
    array("href" => "viewusers.php", "label" => "View Teachers", "icon" => "fa-users", "description" => "Review teacher and staff records.", "keywords" => "teachers staff list"),
    array("href" => "class-registry.php", "label" => "Class Registry", "icon" => "fa-folder-open", "description" => "Assign students to classes.", "keywords" => "class registry assign"),
    array("href" => "group-term-registry.php", "label" => "Group Semester Registry", "icon" => "fa-calendar", "description" => "Register a class into a semester in bulk.", "keywords" => "group term semester registry"),
    array("href" => "term-registry.php", "label" => "Semester Registry", "icon" => "fa-calendar-o", "description" => "Register one student into a semester.", "keywords" => "term semester registry"),
    array("href" => "view-class-registry.php", "label" => "View Class Registry", "icon" => "fa-list-alt", "description" => "Review class registry records.", "keywords" => "view class registry"),
    array("href" => "student-billing.php", "label" => "Student Billing", "icon" => "fa-credit-card", "description" => "Bill students by class and batch.", "keywords" => "billing fees finance"),
    array("href" => "print-student-bills.php", "label" => "Print Student Bills", "icon" => "fa-print", "description" => "Print student bill sheets.", "keywords" => "print bills statements"),
    array("href" => "online-admission-admin.php", "label" => "Online Admission", "icon" => "fa-globe", "description" => "Manage online admission applications.", "keywords" => "online admission applications"),
    array("href" => "guidance-counselling.php", "label" => "Guidance And Counselling", "icon" => "fa-comments-o", "description" => "Open the counselling session board.", "keywords" => "counselling counselor welfare"),
    array("href" => "messages.php", "label" => "Messages", "icon" => "fa-envelope-o", "description" => "Open the school message board.", "keywords" => "messages communication inbox"),
    array("href" => "admin-password-reset.php", "label" => "Admin Password Reset", "icon" => "fa-key", "description" => "Reset and send user login details.", "keywords" => "password reset sms credentials"),
    array("href" => "smsreport.php", "label" => "Results SMS", "icon" => "fa-commenting", "description" => "Send result summaries by SMS.", "keywords" => "sms results report"),
    array("href" => "enablesmsalert.php", "label" => "SMS Alerts", "icon" => "fa-bell", "description" => "Manage student SMS alert subscriptions.", "keywords" => "sms alerts notification"),
    array("href" => "scores-report.php", "label" => "Scores Report", "icon" => "fa-bar-chart", "description" => "Review entered scores and reports.", "keywords" => "scores report results"),
    array("href" => "terminal-report.php", "label" => "Terminal Reports", "icon" => "fa-file-text-o", "description" => "Prepare and print terminal reports.", "keywords" => "terminal report semester results")
);

$toolRows = array();
foreach($toolCatalog as $tool){
    $haystack = strtolower($tool['label']." ".$tool['description']." ".$tool['keywords']." ".$tool['href']);
    if(strpos($haystack, $queryLower) !== false){
        $toolRows[] = $tool;
    }
}
$toolRows = array_slice($toolRows, 0, 8);

$totalShown = count($studentRows) + count($staffRows) + count($classRows) + count($batchRows) + count($toolRows);
if($totalShown === 0){
    echo ags_feedback("No matches found", "Try a student ID, staff name, class name, batch name, or tool title.", "fa-search-minus");
    exit();
}

echo "<div class='desktop-search-summary'>";
echo "<div><strong>".number_format($totalShown)." matches shown</strong><span>Search results for &ldquo;".ags_esc($query)."&rdquo;</span></div>";
echo "<span>Students, staff, classes, batches, and tools</span>";
echo "</div>";

if(!empty($studentRows)){
    echo "<section class='desktop-search-group'>";
    echo "<h3 class='desktop-search-group-title'><i class='fa fa-graduation-cap'></i> Students</h3>";
    echo "<div class='desktop-search-grid'>";
    foreach($studentRows as $row){
        $fullName = ags_trim_name($row);
        $classBits = array();
        if(trim((string)($row['class_name'] ?? '')) !== ''){
            $classBits[] = trim((string)$row['class_name']);
        }
        if(trim((string)($row['batch'] ?? '')) !== ''){
            $classBits[] = trim((string)$row['batch']);
        }
        $meta = trim((string)$row['userid']);
        if(!empty($classBits)){
            $meta .= " | ".implode(" | ", $classBits);
        }
        echo "<article class='desktop-search-card'>";
        echo "<div class='desktop-search-card__eyebrow'><i class='fa fa-user-circle-o'></i> Student</div>";
        echo "<a class='desktop-search-card__title' href='register_edit.php?edit_user=".urlencode((string)$row['userid'])."'>".ags_esc($fullName !== '' ? $fullName : (string)$row['userid'])."</a>";
        echo "<div class='desktop-search-card__meta'>".ags_esc($meta)."</div>";
        echo "<div class='desktop-search-card__actions'>";
        echo "<a class='desktop-search-card__action' href='register_edit.php?edit_user=".urlencode((string)$row['userid'])."'><i class='fa fa-edit'></i> Profile</a>";
        echo "<a class='desktop-search-card__action' href='student-history.php?userid=".urlencode((string)$row['userid'])."'><i class='fa fa-history'></i> Transcript</a>";
        echo "<a class='desktop-search-card__action' href='payments.php?userid=".urlencode((string)$row['userid'])."'><i class='fa fa-money'></i> Payments</a>";
        echo "</div>";
        echo "</article>";
    }
    echo "</div>";
    echo "</section>";
}

if(!empty($staffRows)){
    echo "<section class='desktop-search-group'>";
    echo "<h3 class='desktop-search-group-title'><i class='fa fa-users'></i> Teachers And Staff</h3>";
    echo "<div class='desktop-search-grid'>";
    foreach($staffRows as $row){
        $fullName = ags_trim_name($row);
        $role = function_exists('um_role_label_from_user') ? um_role_label_from_user($row) : trim((string)$row['systemtype']);
        $meta = trim((string)$row['userid'])." | ".trim((string)$role);
        echo "<article class='desktop-search-card'>";
        echo "<div class='desktop-search-card__eyebrow'><i class='fa fa-id-badge'></i> ".ags_esc($role)."</div>";
        echo "<a class='desktop-search-card__title' href='register_edit.php?edit_user=".urlencode((string)$row['userid'])."'>".ags_esc($fullName !== '' ? $fullName : (string)$row['userid'])."</a>";
        echo "<div class='desktop-search-card__meta'>".ags_esc($meta)."</div>";
        echo "<div class='desktop-search-card__actions'>";
        echo "<a class='desktop-search-card__action' href='register_edit.php?edit_user=".urlencode((string)$row['userid'])."'><i class='fa fa-edit'></i> Profile</a>";
        echo "</div>";
        echo "</article>";
    }
    echo "</div>";
    echo "</section>";
}

if(!empty($classRows)){
    echo "<section class='desktop-search-group'>";
    echo "<h3 class='desktop-search-group-title'><i class='fa fa-building-o'></i> Classes</h3>";
    echo "<div class='desktop-search-grid'>";
    foreach($classRows as $row){
        echo "<article class='desktop-search-card'>";
        echo "<div class='desktop-search-card__eyebrow'><i class='fa fa-building'></i> Class</div>";
        echo "<a class='desktop-search-card__title' href='view-class-registry.php'>".ags_esc((string)$row['class_name'])."</a>";
        echo "<div class='desktop-search-card__meta'>Active students: ".number_format((int)$row['active_students'])."</div>";
        echo "<div class='desktop-search-card__actions'>";
        echo "<a class='desktop-search-card__action' href='class-registry.php'><i class='fa fa-folder-open'></i> Registry</a>";
        echo "<a class='desktop-search-card__action' href='group-term-registry.php'><i class='fa fa-calendar'></i> Semester</a>";
        echo "</div>";
        echo "</article>";
    }
    echo "</div>";
    echo "</section>";
}

if(!empty($batchRows)){
    echo "<section class='desktop-search-group'>";
    echo "<h3 class='desktop-search-group-title'><i class='fa fa-calendar-check-o'></i> Batches</h3>";
    echo "<div class='desktop-search-grid'>";
    foreach($batchRows as $row){
        echo "<article class='desktop-search-card'>";
        echo "<div class='desktop-search-card__eyebrow'><i class='fa fa-calendar'></i> Batch</div>";
        echo "<a class='desktop-search-card__title' href='view-class-registry.php'>".ags_esc((string)$row['batch'])."</a>";
        echo "<div class='desktop-search-card__meta'>Batch ID: ".ags_esc((string)$row['batchid'])."</div>";
        echo "<div class='desktop-search-card__actions'>";
        echo "<a class='desktop-search-card__action' href='view-class-registry.php'><i class='fa fa-list-alt'></i> View Registry</a>";
        echo "<a class='desktop-search-card__action' href='group-term-registry.php'><i class='fa fa-users'></i> Group Registry</a>";
        echo "</div>";
        echo "</article>";
    }
    echo "</div>";
    echo "</section>";
}

if(!empty($toolRows)){
    echo "<section class='desktop-search-group'>";
    echo "<h3 class='desktop-search-group-title'><i class='fa fa-compass'></i> Tools</h3>";
    echo "<div class='desktop-search-grid'>";
    foreach($toolRows as $tool){
        echo "<article class='desktop-search-card'>";
        echo "<div class='desktop-search-card__eyebrow'><i class='fa ".ags_esc((string)$tool['icon'])."'></i> Tool</div>";
        echo "<a class='desktop-search-card__title' href='".ags_esc((string)$tool['href'])."'>".ags_esc((string)$tool['label'])."</a>";
        echo "<div class='desktop-search-card__desc'>".ags_esc((string)$tool['description'])."</div>";
        echo "<div class='desktop-search-card__actions'>";
        echo "<a class='desktop-search-card__action' href='".ags_esc((string)$tool['href'])."'><i class='fa ".ags_esc((string)$tool['icon'])."'></i> Open</a>";
        echo "</div>";
        echo "</article>";
    }
    echo "</div>";
    echo "</section>";
}
