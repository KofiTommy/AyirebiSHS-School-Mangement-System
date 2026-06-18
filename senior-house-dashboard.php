<?php
session_start();
include("check-login.php");
include("dbstring.php");
include("house-master-utils.php");
ensure_house_tables($con);

if(!house_master_can_view_senior_dashboard($con)){
    header("location:".house_master_landing_page());
    exit();
}

function senior_house_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$_Overview = array(
    "active_houses" => 0,
    "inactive_houses" => 0,
    "active_supervisors" => 0,
    "houses_without_supervisor" => 0,
    "assigned_students" => 0,
    "unassigned_students" => 0,
    "pending_exeat" => 0,
    "active_out" => 0,
    "overdue_returns" => 0,
    "returned_today" => 0,
    "external_pending" => 0,
    "internal_pending" => 0,
    "approved_today" => 0,
    "rejected_today" => 0
);
$_HouseRows = array();
$_UnassignedStudents = array();
$_RecentAssignments = array();
$_RecentExeat = array();
$_OverdueExeatRows = array();
$_ReturnedTodayRows = array();
$_Alerts = array();
$_ViewerId = isset($_SESSION['USERID']) ? (string)$_SESSION['USERID'] : "";
$_CanManageSeniorHouse = house_master_is_admin();
$_IsHeadmasterView = isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
    $_SESSION['ACCESSLEVEL'] === "user" &&
    $_SESSION['SYSTEMTYPE'] === "Headmaster";
$_StudentSearch = isset($_GET['student_search']) ? trim((string)$_GET['student_search']) : "";
$_StudentHouseId = isset($_GET['student_houseid']) ? trim((string)$_GET['student_houseid']) : "";
$_AssignedHouseOptions = array();
$_AssignedHouseMap = array();
$_StudentSearchSql = "";
$_StudentHouseSql = "";
$_MySeniorAssignment = null;
$_IsSeniorTeacherView = false;
$_HouseScopeSql = "1=1";
$_HouseMasterScopeSql = "1=1";
$_StudentHouseScopeSql = "1=1";
$_ExeatScopeSql = "1=1";
$_DashboardExpectedReturnSql = house_master_exeat_expected_return_sql('er');
$_DashboardOverdueSql = house_master_exeat_overdue_sql('er');
$_StudentHouseFilterLabel = "All Assigned Houses";
$_PageSubtitle = "Supervise all houses, monitor house masters and mistresses, track student placement, and keep an eye on exeat activity from one place.";
$_HouseOverviewDescription = "Each house with its current staff coverage, student load, and exeat activity.";
$_AssignmentPanelTitle = "Recent House Movements";
$_AssignmentPanelDescription = "Latest house assignment activity, including current active and older inactive movements.";
$_AssignmentPanelEmpty = "No student house movement history found yet.";
$_ExeatPanelDescription = "Latest exeat requests across the houses, with pending requests shown first for quick supervision.";
if(house_master_is_teacher()){
    $_MySeniorAssignment = get_senior_house_assignment($con, $_ViewerId);
    if(!$_CanManageSeniorHouse && $_MySeniorAssignment){
        $_IsSeniorTeacherView = true;
        $_AssignedHouseRes = mysqli_query($con, "SELECT h.houseid,h.housename
            FROM tblhouse h
            ORDER BY CASE WHEN h.status='active' THEN 0 ELSE 1 END ASC, h.housename ASC");
        if($_AssignedHouseRes){
            while($_AssignedHouse = mysqli_fetch_array($_AssignedHouseRes, MYSQLI_ASSOC)){
                $_AssignedHouseOptions[] = $_AssignedHouse;
                $_AssignedHouseMap[$_AssignedHouse['houseid']] = $_AssignedHouse['housename'];
            }
        }
        if($_StudentHouseId !== "" && !isset($_AssignedHouseMap[$_StudentHouseId])){
            $_StudentHouseId = "";
        }
        if($_StudentSearch !== ""){
            $_StudentSearchEsc = mysqli_real_escape_string($con, $_StudentSearch);
            $_StudentSearchLike = "%".$_StudentSearchEsc."%";
            $_StudentSearchSql = " AND (
                su.userid LIKE '$_StudentSearchLike'
                OR CONCAT_WS(' ', COALESCE(su.firstname,''), COALESCE(su.othernames,''), COALESCE(su.surname,'')) LIKE '$_StudentSearchLike'
                OR h.housename LIKE '$_StudentSearchLike'
                OR COALESCE(su.mobile,'') LIKE '$_StudentSearchLike'
                OR COALESCE(su.nextofkin_contact,'') LIKE '$_StudentSearchLike'
            )";
        }
        if($_StudentHouseId !== ""){
            $_StudentHouseEsc = mysqli_real_escape_string($con, $_StudentHouseId);
            $_StudentHouseSql = " AND sh.houseid='$_StudentHouseEsc'";
        }
        $_StudentHouseFilterLabel = "All Houses";
        $_PageSubtitle = "This senior role view covers all houses, students, and exeat activity, while setup controls remain limited to admin.";
        $_HouseOverviewDescription = "All houses with their current staff coverage, student load, and exeat return activity.";
        $_AssignmentPanelTitle = "Assigned Students By House";
        $_AssignmentPanelDescription = "Search current active student house assignments across all houses.";
        $_AssignmentPanelEmpty = "No active student house assignments were found.";
        $_ExeatPanelDescription = "Latest exeat requests and return activity across all houses.";
    }
}
if($_IsHeadmasterView){
    $_PageSubtitle = "Review senior house leadership, student placement, and exeat activity across the school from one leadership view.";
    $_HouseOverviewDescription = "All houses with current supervision, student load, and exeat return activity.";
    $_AssignmentPanelTitle = "Student Assignments By House";
    $_AssignmentPanelDescription = "Current student house placement across the school.";
    $_AssignmentPanelEmpty = "No active student house assignments were found.";
    $_ExeatPanelDescription = "Latest exeat requests and return activity across all houses.";
}
$_DashboardRoleLabel = $_CanManageSeniorHouse ? "Administrator" : ($_IsHeadmasterView ? "Headmaster" : (($_MySeniorAssignment && trim((string)$_MySeniorAssignment['designation']) !== "") ? (string)$_MySeniorAssignment['designation'] : "House Staff"));
$_DashboardScopeLabel = ($_CanManageSeniorHouse || $_IsSeniorTeacherView || $_IsHeadmasterView) ? "All Houses" : "Assigned House Scope";
$_DashboardModeLabel = $_CanManageSeniorHouse ? "Setup Controls Enabled" : ($_IsHeadmasterView ? "Read Only Overview" : "Operations View");
$_DashboardFilterParts = array();
if($_StudentHouseId !== "" && isset($_AssignedHouseMap[$_StudentHouseId])){
    $_DashboardFilterParts[] = "House: ".$_AssignedHouseMap[$_StudentHouseId];
}
if($_StudentSearch !== ""){
    $_DashboardFilterParts[] = "Search: ".$_StudentSearch;
}
$_DashboardFilterSummary = implode(" | ", $_DashboardFilterParts);

if($_CanManageSeniorHouse){
    $_OverviewSql = "SELECT
        (SELECT COUNT(*) FROM tblhouse WHERE status='active') AS active_houses,
        (SELECT COUNT(*) FROM tblhouse WHERE status='inactive') AS inactive_houses,
        (SELECT COUNT(*) FROM tblhousemaster WHERE status='active') AS active_supervisors,
        (SELECT COUNT(*)
            FROM tblhouse h
            WHERE h.status='active'
              AND NOT EXISTS (
                  SELECT 1 FROM tblhousemaster hm
                  WHERE hm.houseid=h.houseid AND hm.status='active'
              )
        ) AS houses_without_supervisor,
        (SELECT COUNT(*)
            FROM tblstudenthouse sh
            INNER JOIN tblsystemuser su ON su.userid=sh.userid
            WHERE sh.status='active'
              AND su.systemtype='Student'
              AND su.status='active'
        ) AS assigned_students,
        (SELECT COUNT(*)
            FROM tblsystemuser su
            WHERE su.systemtype='Student'
              AND su.status='active'
              AND NOT EXISTS (
                  SELECT 1 FROM tblstudenthouse sh
                  WHERE sh.userid=su.userid AND sh.status='active'
              )
        ) AS unassigned_students,
        (SELECT COUNT(*) FROM tblexeatrequest WHERE status='pending') AS pending_exeat,
        (SELECT COUNT(*) FROM tblexeatrequest er WHERE er.status='approved' AND er.actualreturndatetime IS NULL) AS active_out,
        (SELECT COUNT(*) FROM tblexeatrequest er WHERE ".$_DashboardOverdueSql.") AS overdue_returns,
        (SELECT COUNT(*) FROM tblexeatrequest er WHERE er.actualreturndatetime IS NOT NULL AND DATE(er.actualreturndatetime)=CURDATE()) AS returned_today,
        (SELECT COUNT(*) FROM tblexeatrequest WHERE status='pending' AND LOWER(COALESCE(exeattype,'external'))='external') AS external_pending,
        (SELECT COUNT(*) FROM tblexeatrequest WHERE status='pending' AND LOWER(COALESCE(exeattype,'external'))='internal') AS internal_pending,
        (SELECT COUNT(*) FROM tblexeatrequest WHERE status='approved' AND DATE(decisiondatetime)=CURDATE()) AS approved_today,
        (SELECT COUNT(*) FROM tblexeatrequest WHERE status='rejected' AND DATE(decisiondatetime)=CURDATE()) AS rejected_today";
}else{
    $_OverviewSql = "SELECT
        (SELECT COUNT(*) FROM tblhouse h WHERE ".$_HouseScopeSql." AND h.status='active') AS active_houses,
        (SELECT COUNT(*) FROM tblhouse h WHERE ".$_HouseScopeSql." AND h.status='inactive') AS inactive_houses,
        (SELECT COUNT(*) FROM tblhousemaster hm WHERE ".$_HouseMasterScopeSql." AND hm.status='active') AS active_supervisors,
        0 AS houses_without_supervisor,
        (SELECT COUNT(*)
            FROM tblstudenthouse sh
            INNER JOIN tblsystemuser su ON su.userid=sh.userid
            WHERE ".$_StudentHouseScopeSql."
              AND sh.status='active'
              AND su.systemtype='Student'
              AND su.status='active'
        ) AS assigned_students,
        0 AS unassigned_students,
        (SELECT COUNT(*) FROM tblexeatrequest er WHERE ".$_ExeatScopeSql." AND er.status='pending') AS pending_exeat,
        (SELECT COUNT(*) FROM tblexeatrequest er WHERE ".$_ExeatScopeSql." AND er.status='approved' AND er.actualreturndatetime IS NULL) AS active_out,
        (SELECT COUNT(*) FROM tblexeatrequest er WHERE ".$_ExeatScopeSql." AND ".$_DashboardOverdueSql.") AS overdue_returns,
        (SELECT COUNT(*) FROM tblexeatrequest er WHERE ".$_ExeatScopeSql." AND er.actualreturndatetime IS NOT NULL AND DATE(er.actualreturndatetime)=CURDATE()) AS returned_today,
        (SELECT COUNT(*) FROM tblexeatrequest er WHERE ".$_ExeatScopeSql." AND er.status='pending' AND LOWER(COALESCE(er.exeattype,'external'))='external') AS external_pending,
        (SELECT COUNT(*) FROM tblexeatrequest er WHERE ".$_ExeatScopeSql." AND er.status='pending' AND LOWER(COALESCE(er.exeattype,'external'))='internal') AS internal_pending,
        (SELECT COUNT(*) FROM tblexeatrequest er WHERE ".$_ExeatScopeSql." AND er.status='approved' AND DATE(er.decisiondatetime)=CURDATE()) AS approved_today,
        (SELECT COUNT(*) FROM tblexeatrequest er WHERE ".$_ExeatScopeSql." AND er.status='rejected' AND DATE(er.decisiondatetime)=CURDATE()) AS rejected_today";
}
$_OverviewRes = mysqli_query($con, $_OverviewSql);
if($_OverviewRes && $_Row = mysqli_fetch_array($_OverviewRes, MYSQLI_ASSOC)){
    $_Overview = array_merge($_Overview, $_Row);
}

$_HouseRes = mysqli_query($con, "SELECT
    h.houseid,
    h.housename,
    h.description,
    h.status,
    h.datetimeentry,
    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(su.firstname,''), ' ', COALESCE(su.othernames,''), ' ', COALESCE(su.surname,''))), ''), 'Not Assigned') AS supervisor_name,
    COALESCE(su.userid, '') AS supervisor_id,
    COALESCE(su.mobile, '') AS supervisor_mobile,
    COALESCE(hm.datetimeentry, '') AS supervisor_assigned_on,
    (SELECT COUNT(*)
        FROM tblstudenthouse sh
        INNER JOIN tblsystemuser stu ON stu.userid=sh.userid
        WHERE sh.houseid=h.houseid
          AND sh.status='active'
          AND stu.systemtype='Student'
          AND stu.status='active'
    ) AS student_count,
    (SELECT COUNT(*) FROM tblexeatrequest er WHERE er.houseid=h.houseid AND er.status='pending') AS pending_exeat,
    (SELECT COUNT(*) FROM tblexeatrequest er WHERE er.houseid=h.houseid AND er.status='approved' AND er.actualreturndatetime IS NULL) AS active_out,
    (SELECT COUNT(*) FROM tblexeatrequest er WHERE er.houseid=h.houseid AND ".house_master_exeat_overdue_sql('er').") AS overdue_returns,
    (SELECT COUNT(*) FROM tblexeatrequest er WHERE er.houseid=h.houseid AND er.actualreturndatetime IS NOT NULL AND DATE(er.actualreturndatetime)=CURDATE()) AS returned_today,
    (SELECT COUNT(*) FROM tblexeatrequest er WHERE er.houseid=h.houseid AND er.status='approved') AS approved_exeat,
    (SELECT COUNT(*) FROM tblexeatrequest er WHERE er.houseid=h.houseid AND er.status='rejected') AS rejected_exeat,
    (SELECT MAX(sh.datetimeentry) FROM tblstudenthouse sh WHERE sh.houseid=h.houseid) AS last_student_move,
    (SELECT MAX(COALESCE(er.actualreturndatetime, er.requestedatetime)) FROM tblexeatrequest er WHERE er.houseid=h.houseid) AS last_exeat_request
    FROM tblhouse h
    LEFT JOIN tblhousemaster hm ON hm.assignmentid = (
        SELECT hm2.assignmentid
        FROM tblhousemaster hm2
        WHERE hm2.houseid=h.houseid AND hm2.status='active'
        ORDER BY hm2.datetimeentry DESC
        LIMIT 1
    )
    LEFT JOIN tblsystemuser su ON su.userid=hm.userid
    WHERE ".$_HouseScopeSql."
    ORDER BY
        CASE WHEN h.status='active' THEN 0 ELSE 1 END ASC,
        overdue_returns DESC,
        pending_exeat DESC,
        h.housename ASC");
if($_HouseRes){
    while($_Row = mysqli_fetch_array($_HouseRes, MYSQLI_ASSOC)){
        $_HouseRows[] = $_Row;
    }
}

if($_CanManageSeniorHouse){
    $_UnassignedRes = mysqli_query($con, "SELECT
        su.userid,
        COALESCE(NULLIF(TRIM(CONCAT(COALESCE(su.firstname,''), ' ', COALESCE(su.othernames,''), ' ', COALESCE(su.surname,''))), ''), su.userid) AS student_name,
        COALESCE(su.mobile, '') AS mobile
        FROM tblsystemuser su
        LEFT JOIN tblstudenthouse sh ON sh.userid=su.userid AND sh.status='active'
        WHERE su.systemtype='Student'
          AND su.status='active'
          AND sh.assignmentid IS NULL
        ORDER BY student_name ASC
        LIMIT 25");
    if($_UnassignedRes){
        while($_Row = mysqli_fetch_array($_UnassignedRes, MYSQLI_ASSOC)){
            $_UnassignedStudents[] = $_Row;
        }
    }
}

if($_CanManageSeniorHouse){
    $_AssignmentSql = "SELECT
        sh.assignmentid,
        sh.datetimeentry,
        sh.status,
        sh.recordedby,
        h.housename,
        su.userid,
        COALESCE(su.mobile, '') AS mobile,
        COALESCE(su.nextofkin_contact, '') AS nextofkin_contact,
        COALESCE(NULLIF(TRIM(CONCAT(COALESCE(su.firstname,''), ' ', COALESCE(su.othernames,''), ' ', COALESCE(su.surname,''))), ''), su.userid) AS student_name
        FROM tblstudenthouse sh
        INNER JOIN tblhouse h ON h.houseid=sh.houseid
        INNER JOIN tblsystemuser su ON su.userid=sh.userid
        WHERE su.systemtype='Student'
        ORDER BY sh.datetimeentry DESC
        LIMIT 15";
}else{
    $_AssignmentSql = "SELECT
        sh.assignmentid,
        sh.datetimeentry,
        sh.status,
        sh.recordedby,
        h.housename,
        su.userid,
        COALESCE(su.mobile, '') AS mobile,
        COALESCE(su.nextofkin_contact, '') AS nextofkin_contact,
        COALESCE(NULLIF(TRIM(CONCAT(COALESCE(su.firstname,''), ' ', COALESCE(su.othernames,''), ' ', COALESCE(su.surname,''))), ''), su.userid) AS student_name
        FROM tblstudenthouse sh
        INNER JOIN tblhouse h ON h.houseid=sh.houseid
        INNER JOIN tblsystemuser su ON su.userid=sh.userid
        WHERE ".$_StudentHouseScopeSql."
          AND sh.status='active'
          AND su.systemtype='Student'
          AND su.status='active'
          $_StudentHouseSql
          $_StudentSearchSql
        ORDER BY h.housename ASC, student_name ASC
        LIMIT 50";
}
$_AssignmentRes = mysqli_query($con, $_AssignmentSql);
if($_AssignmentRes){
    while($_Row = mysqli_fetch_array($_AssignmentRes, MYSQLI_ASSOC)){
        $_RecentAssignments[] = $_Row;
    }
}

$_ExeatRes = mysqli_query($con, "SELECT
    er.exeatid,
    er.status,
    LOWER(COALESCE(er.exeattype,'external')) AS exeattype,
    er.requestedatetime,
    er.decisiondatetime,
    er.decisionby,
    er.dateout,
    er.timeout,
    er.datereturn,
    er.timereturn,
    er.actualreturndatetime,
    er.returnedby,
    er.returnnote,
    ".$_DashboardExpectedReturnSql." AS expected_returndatetime,
    CASE WHEN ".$_DashboardOverdueSql." THEN 1 ELSE 0 END AS is_overdue,
    h.housename,
    su.userid,
    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(su.firstname,''), ' ', COALESCE(su.othernames,''), ' ', COALESCE(su.surname,''))), ''), su.userid) AS student_name
    FROM tblexeatrequest er
    INNER JOIN tblhouse h ON h.houseid=er.houseid
    INNER JOIN tblsystemuser su ON su.userid=er.userid
    WHERE ".$_ExeatScopeSql."
    ORDER BY
        CASE
            WHEN er.status='pending' THEN 0
            WHEN ".$_DashboardOverdueSql." THEN 1
            WHEN er.status='approved' AND er.actualreturndatetime IS NULL THEN 2
            WHEN er.actualreturndatetime IS NOT NULL THEN 3
            ELSE 4
        END ASC,
        er.requestedatetime DESC
    LIMIT 15");
if($_ExeatRes){
    while($_Row = mysqli_fetch_array($_ExeatRes, MYSQLI_ASSOC)){
        $_RecentExeat[] = $_Row;
    }
}

$_OverdueRes = mysqli_query($con, "SELECT
    er.exeatid,
    er.datereturn,
    er.timereturn,
    ".$_DashboardExpectedReturnSql." AS expected_returndatetime,
    TIMESTAMPDIFF(HOUR, ".$_DashboardExpectedReturnSql.", NOW()) AS hours_overdue,
    h.housename,
    su.userid,
    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(su.firstname,''), ' ', COALESCE(su.othernames,''), ' ', COALESCE(su.surname,''))), ''), su.userid) AS student_name
    FROM tblexeatrequest er
    INNER JOIN tblhouse h ON h.houseid=er.houseid
    INNER JOIN tblsystemuser su ON su.userid=er.userid
    WHERE ".$_ExeatScopeSql."
      AND ".$_DashboardOverdueSql."
    ORDER BY ".$_DashboardExpectedReturnSql." ASC
    LIMIT 12");
if($_OverdueRes){
    while($_Row = mysqli_fetch_array($_OverdueRes, MYSQLI_ASSOC)){
        $_OverdueExeatRows[] = $_Row;
    }
}

$_ReturnedTodayRes = mysqli_query($con, "SELECT
    er.exeatid,
    er.actualreturndatetime,
    er.returnedby,
    er.returnnote,
    h.housename,
    su.userid,
    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(su.firstname,''), ' ', COALESCE(su.othernames,''), ' ', COALESCE(su.surname,''))), ''), su.userid) AS student_name
    FROM tblexeatrequest er
    INNER JOIN tblhouse h ON h.houseid=er.houseid
    INNER JOIN tblsystemuser su ON su.userid=er.userid
    WHERE ".$_ExeatScopeSql."
      AND er.actualreturndatetime IS NOT NULL
      AND DATE(er.actualreturndatetime)=CURDATE()
    ORDER BY er.actualreturndatetime DESC
    LIMIT 12");
if($_ReturnedTodayRes){
    while($_Row = mysqli_fetch_array($_ReturnedTodayRes, MYSQLI_ASSOC)){
        $_ReturnedTodayRows[] = $_Row;
    }
}

if($_CanManageSeniorHouse){
    if((int)$_Overview["houses_without_supervisor"] > 0){
        $_Alerts[] = $_Overview["houses_without_supervisor"]." active house(s) still do not have an assigned house master or mistress.";
    }
    if((int)$_Overview["unassigned_students"] > 0){
        $_Alerts[] = $_Overview["unassigned_students"]." active student(s) are not assigned to any house yet.";
    }
}
if((int)$_Overview["pending_exeat"] > 0){
    $_Alerts[] = ($_CanManageSeniorHouse || $_IsSeniorTeacherView)
        ? $_Overview["pending_exeat"]." exeat request(s) are still pending across the houses."
        : $_Overview["pending_exeat"]." exeat request(s) are still pending in your current house scope.";
}
if((int)$_Overview["overdue_returns"] > 0){
    $_Alerts[] = ($_CanManageSeniorHouse || $_IsSeniorTeacherView)
        ? $_Overview["overdue_returns"]." student(s) are overdue from exeat return across the houses."
        : $_Overview["overdue_returns"]." student(s) are overdue from exeat return in your current house scope.";
}
?>
<html>
<head>
<?php include("links.php"); ?>
<style>
:root{
    --senior-ink:#122033;
    --senior-muted:#5f6f84;
    --senior-border:#d8e2ec;
    --senior-panel:#ffffff;
    --senior-bg:#f4f7fb;
    --senior-brand:#0f766e;
    --senior-brand-deep:#155e75;
    --senior-info-bg:#ecfeff;
    --senior-warn-bg:#fffbeb;
    --senior-danger-bg:#fff1f2;
    --senior-success-bg:#f0fdf4;
}
body{
    background:
        radial-gradient(circle at top right, rgba(15,118,110,0.12), transparent 30%),
        linear-gradient(180deg, #f9fbff 0%, var(--senior-bg) 100%);
}
.senior-wrap{
    max-width:1280px;
    margin:0 auto;
}
.senior-shell{
    background:rgba(255,255,255,0.86);
    border:1px solid rgba(216,226,236,0.95);
    border-radius:24px;
    padding:20px;
    box-shadow:0 22px 48px rgba(15,23,42,0.08);
}
.senior-top{
    display:flex;
    justify-content:space-between;
    gap:22px;
    align-items:flex-start;
    margin-bottom:12px;
}
.senior-top-copy{
    flex:1 1 auto;
}
.senior-kicker{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 12px;
    border-radius:999px;
    background:rgba(15,118,110,0.1);
    color:var(--senior-brand);
    font-size:11px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0.08em;
    margin-bottom:12px;
}
.senior-title{
    margin:0;
    color:var(--senior-ink);
    font-size:32px;
    line-height:1.1;
}
.senior-subtitle{
    margin:10px 0 0 0;
    color:var(--senior-muted);
    max-width:780px;
    line-height:1.5;
    font-size:14px;
}
.senior-chip-row{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:14px;
}
.senior-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:9px 12px;
    border-radius:999px;
    border:1px solid transparent;
    font-size:12px;
    font-weight:700;
    line-height:1;
}
.senior-chip-neutral{
    background:#f8fbfd;
    border-color:#d7e4ef;
    color:var(--senior-ink);
}
.senior-chip-info{
    background:var(--senior-info-bg);
    border-color:#bae6fd;
    color:#0f4c81;
}
.senior-chip-success{
    background:var(--senior-success-bg);
    border-color:#bbf7d0;
    color:#166534;
}
.senior-chip-warning{
    background:var(--senior-warn-bg);
    border-color:#fde68a;
    color:#92400e;
}
.senior-chip-danger{
    background:var(--senior-danger-bg);
    border-color:#fecdd3;
    color:#b91c1c;
}
.senior-actions{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    justify-content:flex-end;
}
.senior-btn{
    display:inline-flex;
    align-items:center;
    gap:8px;
    border:1px solid var(--senior-border);
    background:#ffffff;
    color:var(--senior-ink);
    text-decoration:none;
    padding:10px 15px;
    border-radius:999px;
    font-weight:600;
    cursor:pointer;
    font:inherit;
    box-shadow:0 8px 18px rgba(15,23,42,0.04);
    transition:transform 0.16s ease, box-shadow 0.16s ease, border-color 0.16s ease;
}
.senior-btn-primary{
    background:linear-gradient(135deg, #0f766e 0%, #155e75 100%);
    border-color:#0f766e;
    color:#ffffff;
}
.senior-btn:hover{
    transform:translateY(-1px);
    box-shadow:0 12px 20px rgba(15,23,42,0.08);
    border-color:#bfd0df;
}
.senior-nav{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin:14px 0 18px 0;
}
.senior-nav-link{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 14px;
    border-radius:14px;
    text-decoration:none;
    background:#ffffff;
    border:1px solid var(--senior-border);
    color:var(--senior-ink);
    font-weight:700;
    font-size:13px;
    transition:transform 0.16s ease, border-color 0.16s ease, background 0.16s ease;
}
.senior-nav-link:hover{
    transform:translateY(-1px);
    border-color:#b9ccdb;
    background:#f8fbfd;
}
.senior-stats{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(170px,1fr));
    gap:14px;
    margin-bottom:18px;
}
.senior-stat{
    background:var(--senior-panel);
    border:1px solid var(--senior-border);
    border-radius:18px;
    padding:16px;
    position:relative;
    overflow:hidden;
}
.senior-stat::before{
    content:"";
    position:absolute;
    inset:0 auto 0 0;
    width:5px;
    background:#cbd5e1;
}
.senior-stat-neutral::before{
    background:#94a3b8;
}
.senior-stat-warning::before{
    background:#d97706;
}
.senior-stat-info::before{
    background:#0891b2;
}
.senior-stat-danger::before{
    background:#dc2626;
}
.senior-stat-success::before{
    background:#16a34a;
}
.senior-stat h4{
    margin:0 0 8px 0;
    font-size:12px;
    color:var(--senior-muted);
    text-transform:uppercase;
    letter-spacing:0.08em;
}
.senior-stat strong{
    display:block;
    color:var(--senior-ink);
    font-size:30px;
    line-height:1;
}
.senior-stat span{
    display:block;
    margin-top:8px;
    color:var(--senior-muted);
    font-size:12px;
}
.senior-alerts{
    display:grid;
    gap:10px;
    margin-bottom:18px;
}
.senior-alert{
    border-radius:14px;
    padding:12px 14px;
    border:1px solid #fed7aa;
    background:#fff7ed;
    color:#9a3412;
    box-shadow:0 8px 16px rgba(15,23,42,0.04);
}
.senior-alert-ok{
    border-color:#bbf7d0;
    background:#f0fdf4;
    color:#166534;
}
.senior-grid{
    display:grid;
    grid-template-columns:repeat(12,1fr);
    gap:14px;
    margin-bottom:14px;
}
.senior-panel{
    background:var(--senior-panel);
    border:1px solid var(--senior-border);
    border-radius:20px;
    padding:16px;
    box-shadow:0 14px 28px rgba(15,23,42,0.04);
}
.senior-panel-wide{
    grid-column:span 12;
}
.senior-panel-half{
    grid-column:span 6;
}
.senior-panel h3{
    margin:0 0 8px 0;
    color:var(--senior-ink);
    font-size:19px;
    display:flex;
    align-items:center;
    gap:10px;
}
.senior-panel p{
    margin:0 0 12px 0;
    color:var(--senior-muted);
    font-size:13px;
    line-height:1.5;
}
.senior-heading-icon{
    color:var(--senior-brand);
}
.senior-table-wrap{
    overflow:auto;
    border-radius:16px;
    border:1px solid #e7edf4;
    background:#ffffff;
    box-shadow:inset 0 1px 0 rgba(255,255,255,0.7);
}
.senior-table{
    width:100%;
    border-collapse:collapse;
    background:#ffffff;
}
.senior-table th,
.senior-table td{
    border-bottom:1px solid #e9eef5;
    padding:10px 8px;
    vertical-align:top;
    text-align:left;
    font-size:13px;
}
.senior-table th{
    color:var(--senior-muted);
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:0.04em;
    background:#f8fbfd;
    position:sticky;
    top:0;
    z-index:1;
}
.senior-table tbody tr:nth-child(even){
    background:#fbfdff;
}
.senior-table tbody tr:hover{
    background:#f2f8fc;
}
.senior-row-watch{
    background:linear-gradient(90deg, rgba(245,158,11,0.08), transparent 45%);
}
.senior-row-critical{
    background:linear-gradient(90deg, rgba(220,38,38,0.08), transparent 45%);
}
.senior-row-calm{
    background:linear-gradient(90deg, rgba(22,163,74,0.06), transparent 45%);
}
.senior-table tr:last-child td{
    border-bottom:none;
}
.senior-badge{
    display:inline-block;
    padding:4px 10px;
    border-radius:999px;
    font-size:11px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0.05em;
}
.senior-badge-active,
.senior-badge-approved{
    background:#dcfce7;
    color:#166534;
}
.senior-badge-pending{
    background:#fef3c7;
    color:#92400e;
}
.senior-badge-rejected,
.senior-badge-inactive{
    background:#fee2e2;
    color:#991b1b;
}
.senior-badge-external{
    background:#dbeafe;
    color:#1d4ed8;
}
.senior-badge-internal{
    background:#ede9fe;
    color:#6d28d9;
}
.senior-muted{
    color:var(--senior-muted);
    font-size:12px;
}
.senior-name{
    font-weight:700;
    color:var(--senior-ink);
}
.senior-empty{
    padding:18px 8px;
    color:var(--senior-muted);
    text-align:center;
}
.senior-filter-form{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    align-items:flex-end;
    margin-bottom:12px;
    padding:12px;
    border:1px solid var(--senior-border);
    border-radius:14px;
    background:#f8fbfd;
}
.senior-filter-field{
    display:flex;
    flex-direction:column;
    gap:6px;
    min-width:220px;
    flex:1 1 240px;
}
.senior-filter-field-house{
    min-width:360px;
    flex:1 1 360px;
}
.senior-filter-field label{
    font-size:12px;
    font-weight:700;
    color:var(--senior-muted);
    text-transform:uppercase;
    letter-spacing:0.04em;
}
.senior-filter-field input,
.senior-filter-field select{
    width:100%;
    border:1px solid #cbd5e1;
    border-radius:10px;
    padding:10px 12px;
    font:inherit;
    min-height:44px;
    color:var(--senior-ink);
    background:#ffffff;
}
.senior-filter-actions{
    display:flex;
    gap:8px;
    align-items:center;
    flex-wrap:wrap;
}
.senior-filter-summary{
    margin:0 0 10px 0;
    color:var(--senior-muted);
    font-size:13px;
}
@media (max-width: 980px){
    .senior-top{
        flex-direction:column;
    }
    .senior-actions{
        justify-content:flex-start;
    }
    .senior-nav{
        margin-top:10px;
    }
    .senior-panel-half{
        grid-column:span 12;
    }
}
@media print{
    .header,
    .print-hide{
        display:none !important;
    }
    body{
        background:#ffffff !important;
    }
    .main-platform,
    .senior-wrap,
    .senior-shell{
        margin:0 !important;
        padding:0 !important;
        border:none !important;
        box-shadow:none !important;
        background:#ffffff !important;
        max-width:none !important;
    }
    .senior-panel,
    .senior-stat{
        break-inside:avoid;
        box-shadow:none !important;
    }
}
</style>
</head>
<body>
<div class="header">
<?php include("menu.php"); ?>
</div>
<div class="main-platform">
    <div class="senior-wrap">
        <div class="senior-shell">
            <div class="senior-top">
                <div class="senior-top-copy">
                    <div class="senior-kicker"><i class="fa fa-shield"></i> Senior House Operations</div>
                    <h2 class="senior-title">Senior House Dashboard</h2>
                    <p class="senior-subtitle"><?php echo senior_house_esc($_PageSubtitle); ?></p>
                    <div class="senior-chip-row">
                        <span class="senior-chip senior-chip-neutral"><i class="fa fa-user-circle"></i> <?php echo senior_house_esc($_DashboardRoleLabel); ?></span>
                        <span class="senior-chip senior-chip-info"><i class="fa fa-sitemap"></i> <?php echo senior_house_esc($_DashboardScopeLabel); ?></span>
                        <span class="senior-chip senior-chip-neutral"><i class="fa fa-sliders"></i> <?php echo senior_house_esc($_DashboardModeLabel); ?></span>
                        <?php if($_DashboardFilterSummary !== ""){ ?>
                        <span class="senior-chip senior-chip-warning"><i class="fa fa-filter"></i> <?php echo senior_house_esc($_DashboardFilterSummary); ?></span>
                        <?php } ?>
                        <?php if((int)$_Overview["overdue_returns"] > 0){ ?>
                        <span class="senior-chip senior-chip-danger"><i class="fa fa-warning"></i> <?php echo (int)$_Overview["overdue_returns"]; ?> Overdue Return(s)</span>
                        <?php }elseif((int)$_Overview["returned_today"] > 0){ ?>
                        <span class="senior-chip senior-chip-success"><i class="fa fa-check-circle"></i> <?php echo (int)$_Overview["returned_today"]; ?> Checked In Today</span>
                        <?php } ?>
                    </div>
                </div>
                <div class="senior-actions print-hide">
                    <?php if($_CanManageSeniorHouse){ ?>
                    <a class="senior-btn senior-btn-primary" href="student-house-assignment.php"><i class="fa fa-users"></i> Assign Students</a>
                    <a class="senior-btn" href="house-master-assignment.php"><i class="fa fa-plus"></i> Manage House Staff</a>
                    <a class="senior-btn" href="house-entry.php"><i class="fa fa-home"></i> Manage Houses</a>
                    <a class="senior-btn" href="senior-house-assignment.php"><i class="fa fa-star"></i> Senior Role Assignment</a>
                    <?php } ?>
                    <?php if(house_master_is_teacher()){ ?>
                    <a class="senior-btn" href="house-master-exeat.php"><i class="fa fa-check-square-o"></i> Exeat Desk</a>
                    <?php } ?>
                    <button type="button" class="senior-btn" onclick="window.print();"><i class="fa fa-print"></i> Print Dashboard</button>
                </div>
            </div>

            <div class="senior-alerts">
                <?php
                if(count($_Alerts) > 0){
                    foreach($_Alerts as $_Alert){
                        echo "<div class='senior-alert'><i class='fa fa-warning'></i> ".senior_house_esc($_Alert)."</div>";
                    }
                }else{
                    if($_CanManageSeniorHouse || $_IsSeniorTeacherView){
                        $_OkMessage = "All active houses currently have supervisors, students are assigned, and there are no pending or overdue exeat backlogs.";
                    }elseif($_IsHeadmasterView){
                        $_OkMessage = "Senior house activity is stable right now, with no pending or overdue exeat backlog requiring immediate attention.";
                    }else{
                        $_OkMessage = "Your assigned house students are up to date and there are no pending or overdue exeat backlogs in your current scope.";
                    }
                    echo "<div class='senior-alert senior-alert-ok'><i class='fa fa-check-circle'></i> ".senior_house_esc($_OkMessage)."</div>";
                }
                ?>
            </div>

            <div class="senior-nav print-hide">
                <a class="senior-nav-link" href="#house-overview"><i class="fa fa-home"></i> House Overview</a>
                <a class="senior-nav-link" href="#student-assignments"><i class="fa fa-address-book-o"></i> Student Assignments</a>
                <a class="senior-nav-link" href="#return-monitoring"><i class="fa fa-clock-o"></i> Return Monitoring</a>
                <a class="senior-nav-link" href="#exeat-activity"><i class="fa fa-exchange"></i> Exeat Activity</a>
            </div>

            <div class="senior-stats">
                <div class="senior-stat senior-stat-neutral">
                    <h4>Active Houses</h4>
                    <strong><?php echo (int)$_Overview["active_houses"]; ?></strong>
                    <span><?php echo ($_CanManageSeniorHouse || $_IsSeniorTeacherView) ? (int)$_Overview["inactive_houses"]." inactive house records" : "Houses linked to your active assignment"; ?></span>
                </div>
                <div class="senior-stat senior-stat-neutral">
                    <h4>House Staff</h4>
                    <strong><?php echo (int)$_Overview["active_supervisors"]; ?></strong>
                    <span><?php echo ($_CanManageSeniorHouse || $_IsSeniorTeacherView) ? (int)$_Overview["houses_without_supervisor"]." active houses without staff" : "Active staff record(s) inside your current scope"; ?></span>
                </div>
                <div class="senior-stat senior-stat-neutral">
                    <h4>Assigned Students</h4>
                    <strong><?php echo (int)$_Overview["assigned_students"]; ?></strong>
                    <span><?php echo ($_CanManageSeniorHouse || $_IsSeniorTeacherView) ? (int)$_Overview["unassigned_students"]." students still unassigned" : "Students currently attached to your house"; ?></span>
                </div>
                <div class="senior-stat senior-stat-warning">
                    <h4>Pending Exeat</h4>
                    <strong><?php echo (int)$_Overview["pending_exeat"]; ?></strong>
                    <span><?php echo (int)$_Overview["external_pending"]; ?> external, <?php echo (int)$_Overview["internal_pending"]; ?> internal</span>
                </div>
                <div class="senior-stat senior-stat-info">
                    <h4>Out On Exeat</h4>
                    <strong><?php echo (int)$_Overview["active_out"]; ?></strong>
                    <span>Approved exeat not yet checked back in</span>
                </div>
                <div class="senior-stat senior-stat-danger">
                    <h4>Overdue Returns</h4>
                    <strong><?php echo (int)$_Overview["overdue_returns"]; ?></strong>
                    <span>Students still out past expected return time</span>
                </div>
                <div class="senior-stat senior-stat-success">
                    <h4>Returned Today</h4>
                    <strong><?php echo (int)$_Overview["returned_today"]; ?></strong>
                    <span>Students checked back in today</span>
                </div>
                <div class="senior-stat senior-stat-success">
                    <h4>Approved Today</h4>
                    <strong><?php echo (int)$_Overview["approved_today"]; ?></strong>
                    <span>Today&apos;s approved exeat decisions</span>
                </div>
                <div class="senior-stat senior-stat-warning">
                    <h4>Rejected Today</h4>
                    <strong><?php echo (int)$_Overview["rejected_today"]; ?></strong>
                    <span>Today&apos;s rejected exeat decisions</span>
                </div>
            </div>

            <div class="senior-grid">
                <div class="senior-panel senior-panel-wide" id="house-overview">
                    <h3><i class="fa fa-building-o senior-heading-icon"></i> House Overview</h3>
                    <p><?php echo senior_house_esc($_HouseOverviewDescription); ?></p>
                    <div class="senior-table-wrap">
                    <table class="senior-table">
                        <thead>
                            <tr>
                                <th>House</th>
                                <th>Status</th>
                                <th>Supervisor</th>
                                <th>Students</th>
                                <th>Pending</th>
                                <th>Out Now</th>
                                <th>Overdue</th>
                                <th>Returned Today</th>
                                <th>Last Activity</th>
                                <?php if($_CanManageSeniorHouse){ ?>
                                <th class="print-hide">Action</th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if(count($_HouseRows) > 0){
                                foreach($_HouseRows as $_House){
                                    $_StatusClass = ($_House["status"] === "active") ? "senior-badge-active" : "senior-badge-inactive";
                                    $_LastActivity = trim((string)$_House["last_student_move"]) !== "" ? $_House["last_student_move"] : $_House["last_exeat_request"];
                                    $_HouseRowClass = "senior-row-calm";
                                    if(trim((string)$_House["supervisor_id"]) === "" || (int)$_House["overdue_returns"] > 0){
                                        $_HouseRowClass = "senior-row-critical";
                                    }elseif((int)$_House["pending_exeat"] > 0 || (int)$_House["active_out"] > 0){
                                        $_HouseRowClass = "senior-row-watch";
                                    }
                                    if($_LastActivity === ""){
                                        $_LastActivity = $_House["datetimeentry"];
                                    }
                                    echo "<tr class='".$_HouseRowClass."'>";
                                    echo "<td><span class='senior-name'>".senior_house_esc($_House["housename"])."</span>";
                                    if(trim((string)$_House["description"]) !== ""){
                                        echo "<div class='senior-muted'>".senior_house_esc($_House["description"])."</div>";
                                    }
                                    echo "</td>";
                                    echo "<td><span class='senior-badge ".$_StatusClass."'>".senior_house_esc(ucfirst((string)$_House["status"]))."</span></td>";
                                    echo "<td><span class='senior-name'>".senior_house_esc($_House["supervisor_name"])."</span>";
                                    if(trim((string)$_House["supervisor_id"]) !== ""){
                                        echo "<div class='senior-muted'>".senior_house_esc($_House["supervisor_id"]);
                                        if(trim((string)$_House["supervisor_mobile"]) !== ""){
                                            echo " | ".senior_house_esc($_House["supervisor_mobile"]);
                                        }
                                        echo "</div>";
                                    }else{
                                        echo "<div class='senior-muted'>No active house master or mistress assigned</div>";
                                    }
                                    echo "</td>";
                                    echo "<td>".(int)$_House["student_count"]."</td>";
                                    echo "<td>".(int)$_House["pending_exeat"]."</td>";
                                    echo "<td>".(int)$_House["active_out"]."</td>";
                                    echo "<td>".(int)$_House["overdue_returns"]."</td>";
                                    echo "<td>".(int)$_House["returned_today"]."</td>";
                                    echo "<td>".senior_house_esc($_LastActivity)."</td>";
                                    if($_CanManageSeniorHouse){
                                        echo "<td class='print-hide'><a class='senior-btn' href='house-master-assignment.php'>Staff</a> <a class='senior-btn' href='student-house-assignment.php'>Students</a></td>";
                                    }
                                    echo "</tr>";
                                }
                            }else{
                                echo "<tr><td colspan='".($_CanManageSeniorHouse ? "10" : "9")."' class='senior-empty'>".senior_house_esc(($_CanManageSeniorHouse || $_IsSeniorTeacherView) ? "No house records found yet." : "No house records were found in your current scope.")."</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>

            <div class="senior-grid">
                <?php if($_CanManageSeniorHouse){ ?>
                <div class="senior-panel senior-panel-half">
                    <h3><i class="fa fa-user-times senior-heading-icon"></i> Unassigned Students</h3>
                    <p>These active students still need to be attached to a house.</p>
                    <div class="senior-table-wrap">
                    <table class="senior-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Phone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if(count($_UnassignedStudents) > 0){
                                foreach($_UnassignedStudents as $_Student){
                                    echo "<tr>";
                                    echo "<td>".senior_house_esc($_Student["userid"])."</td>";
                                    echo "<td>".senior_house_esc($_Student["student_name"])."</td>";
                                    echo "<td>".senior_house_esc($_Student["mobile"] !== "" ? $_Student["mobile"] : "-")."</td>";
                                    echo "</tr>";
                                }
                            }else{
                                echo "<tr><td colspan='3' class='senior-empty'>Every active student currently has a house assignment.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    </div>
                    <div class="print-hide" style="margin-top:12px;">
                        <a class="senior-btn senior-btn-primary" href="student-house-assignment.php"><i class="fa fa-users"></i> Open Student House Assignment</a>
                    </div>
                </div>
                <?php } ?>

                <div class="senior-panel <?php echo $_CanManageSeniorHouse ? "senior-panel-half" : "senior-panel-wide"; ?>" id="student-assignments">
                    <h3><i class="fa fa-address-book-o senior-heading-icon"></i> <?php echo senior_house_esc($_AssignmentPanelTitle); ?></h3>
                    <p><?php echo senior_house_esc($_AssignmentPanelDescription); ?></p>
                    <?php if(!$_CanManageSeniorHouse){ ?>
                    <form method="get" class="senior-filter-form print-hide">
                        <div class="senior-filter-field">
                            <label for="student_search">Search Student</label>
                            <input type="text" id="student_search" name="student_search" value="<?php echo senior_house_esc($_StudentSearch); ?>" placeholder="Name, ID, phone or guardian contact">
                        </div>
                        <div class="senior-filter-field senior-filter-field-house">
                            <label for="student_houseid">House</label>
                            <select id="student_houseid" name="student_houseid">
                                <option value=""><?php echo senior_house_esc($_StudentHouseFilterLabel); ?></option>
                                <?php
                                foreach($_AssignedHouseOptions as $_AssignedHouse){
                                    $_Selected = ($_StudentHouseId === $_AssignedHouse['houseid']) ? " selected" : "";
                                    echo "<option value='".senior_house_esc($_AssignedHouse['houseid'])."'".$_Selected.">".senior_house_esc($_AssignedHouse['housename'])."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="senior-filter-actions">
                            <button type="submit" class="senior-btn senior-btn-primary"><i class="fa fa-search"></i> Filter</button>
                            <a class="senior-btn" href="senior-house-dashboard.php">Clear</a>
                        </div>
                    </form>
                    <?php
                    if($_StudentSearch !== "" || $_StudentHouseId !== ""){
                        echo "<div class='senior-filter-summary'><strong>Filtered results:</strong> ";
                        if($_StudentHouseId !== "" && isset($_AssignedHouseMap[$_StudentHouseId])){
                            echo "House ".senior_house_esc($_AssignedHouseMap[$_StudentHouseId]);
                            if($_StudentSearch !== ""){
                                echo " | ";
                            }
                        }
                        if($_StudentSearch !== ""){
                            echo "Search '".senior_house_esc($_StudentSearch)."'";
                        }
                        echo "</div>";
                    }
                    ?>
                    <?php } ?>
                    <div class="senior-table-wrap">
                    <table class="senior-table">
                        <thead>
                            <?php if($_CanManageSeniorHouse){ ?>
                            <tr>
                                <th>Date/Time</th>
                                <th>Student</th>
                                <th>House</th>
                                <th>Status</th>
                                <th>By</th>
                            </tr>
                            <?php }else{ ?>
                            <tr>
                                <th>Student</th>
                                <th>House</th>
                                <th>Student Contact</th>
                                <th>Parent / Guardian Contact</th>
                                <th>Assigned On</th>
                            </tr>
                            <?php } ?>
                        </thead>
                        <tbody>
                            <?php
                            if(count($_RecentAssignments) > 0){
                                foreach($_RecentAssignments as $_Move){
                                    echo "<tr class='".($_CanManageSeniorHouse ? "senior-row-watch" : "senior-row-calm")."'>";
                                    if($_CanManageSeniorHouse){
                                        $_MoveClass = ($_Move["status"] === "active") ? "senior-badge-active" : "senior-badge-inactive";
                                        echo "<td>".senior_house_esc($_Move["datetimeentry"])."</td>";
                                        echo "<td><span class='senior-name'>".senior_house_esc($_Move["student_name"])."</span><div class='senior-muted'>".senior_house_esc($_Move["userid"])."</div></td>";
                                        echo "<td>".senior_house_esc($_Move["housename"])."</td>";
                                        echo "<td><span class='senior-badge ".$_MoveClass."'>".senior_house_esc(ucfirst((string)$_Move["status"]))."</span></td>";
                                        echo "<td>".senior_house_esc($_Move["recordedby"])."</td>";
                                    }else{
                                        echo "<td><span class='senior-name'>".senior_house_esc($_Move["student_name"])."</span><div class='senior-muted'>".senior_house_esc($_Move["userid"])."</div></td>";
                                        echo "<td>".senior_house_esc($_Move["housename"])."</td>";
                                        echo "<td>".senior_house_esc(trim((string)$_Move["mobile"]) !== "" ? $_Move["mobile"] : "-")."</td>";
                                        echo "<td>".senior_house_esc(trim((string)$_Move["nextofkin_contact"]) !== "" ? $_Move["nextofkin_contact"] : "-")."</td>";
                                        echo "<td>".senior_house_esc($_Move["datetimeentry"])."</td>";
                                    }
                                    echo "</tr>";
                                }
                            }else{
                                echo "<tr><td colspan='5' class='senior-empty'>".senior_house_esc($_AssignmentPanelEmpty)."</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>

            <div class="senior-grid" id="return-monitoring">
                <div class="senior-panel senior-panel-half">
                    <h3><i class="fa fa-exclamation-triangle senior-heading-icon"></i> Overdue Exeat Returns</h3>
                    <p>Students whose approved exeat return time has already passed and who have not yet been checked back in.</p>
                    <div class="senior-table-wrap">
                    <table class="senior-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>House</th>
                                <th>Expected Return</th>
                                <th>Overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if(count($_OverdueExeatRows) > 0){
                                foreach($_OverdueExeatRows as $_Overdue){
                                    $_HoursOverdue = max(0, (int)($_Overdue["hours_overdue"] ?? 0));
                                    echo "<tr class='senior-row-critical'>";
                                    echo "<td><span class='senior-name'>".senior_house_esc($_Overdue["student_name"])."</span><div class='senior-muted'>".senior_house_esc($_Overdue["userid"])."</div></td>";
                                    echo "<td>".senior_house_esc($_Overdue["housename"])."</td>";
                                    echo "<td>".senior_house_esc($_Overdue["expected_returndatetime"])."</td>";
                                    echo "<td>".senior_house_esc($_HoursOverdue." hr(s)")."</td>";
                                    echo "</tr>";
                                }
                            }else{
                                echo "<tr><td colspan='4' class='senior-empty'>No overdue exeat returns in the current scope.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    </div>
                </div>

                <div class="senior-panel senior-panel-half">
                    <h3><i class="fa fa-check-circle senior-heading-icon"></i> Checked In Today</h3>
                    <p>Students whose return from exeat has been confirmed today, including who checked them back in.</p>
                    <div class="senior-table-wrap">
                    <table class="senior-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>House</th>
                                <th>Returned</th>
                                <th>Checked In By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if(count($_ReturnedTodayRows) > 0){
                                foreach($_ReturnedTodayRows as $_Returned){
                                    echo "<tr class='senior-row-calm'>";
                                    echo "<td><span class='senior-name'>".senior_house_esc($_Returned["student_name"])."</span><div class='senior-muted'>".senior_house_esc($_Returned["userid"])."</div></td>";
                                    echo "<td>".senior_house_esc($_Returned["housename"])."</td>";
                                    echo "<td>".senior_house_esc($_Returned["actualreturndatetime"])."</td>";
                                    echo "<td>".senior_house_esc(trim((string)$_Returned["returnedby"]) !== "" ? $_Returned["returnedby"] : "-");
                                    if(trim((string)$_Returned["returnnote"]) !== ""){
                                        echo "<div class='senior-muted'>".senior_house_esc($_Returned["returnnote"])."</div>";
                                    }
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            }else{
                                echo "<tr><td colspan='4' class='senior-empty'>No students have been checked back in today in the current scope.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>

            <div class="senior-grid" id="exeat-activity">
                <div class="senior-panel senior-panel-wide">
                    <h3><i class="fa fa-random senior-heading-icon"></i> Recent Exeat Activity</h3>
                    <p><?php echo senior_house_esc($_ExeatPanelDescription); ?></p>
                    <div class="senior-table-wrap">
                    <table class="senior-table">
                        <thead>
                            <tr>
                                <th>Requested</th>
                                <th>Student</th>
                                <th>House</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Return</th>
                                <th>Decision / Return</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if(count($_RecentExeat) > 0){
                                foreach($_RecentExeat as $_Exeat){
                                    $_Type = ($_Exeat["exeattype"] === "internal") ? "Internal" : "External";
                                    $_TypeClass = ($_Exeat["exeattype"] === "internal") ? "senior-badge-internal" : "senior-badge-external";
                                    $_Status = strtolower((string)$_Exeat["status"]);
                                    $_StatusClass = "senior-badge-pending";
                                    $_StatusLabel = ucfirst($_Status);
                                    if(trim((string)($_Exeat["actualreturndatetime"] ?? "")) !== ""){
                                        $_StatusClass = "senior-badge-active";
                                        $_StatusLabel = "Returned";
                                    }elseif($_Status === "approved" && (int)($_Exeat["is_overdue"] ?? 0) === 1){
                                        $_StatusClass = "senior-badge-rejected";
                                        $_StatusLabel = "Overdue Return";
                                    }elseif($_Status === "approved"){
                                        $_StatusClass = "senior-badge-approved";
                                        $_StatusLabel = "Out On Exeat";
                                    }elseif($_Status === "rejected"){
                                        $_StatusClass = "senior-badge-rejected";
                                    }
                                    $_ReturnText = trim((string)($_Exeat["datereturn"] ?? ""));
                                    if(trim((string)($_Exeat["timereturn"] ?? "")) !== ""){
                                        $_ReturnText .= " ".trim((string)$_Exeat["timereturn"]);
                                    }
                                    if(trim($_ReturnText) === ""){
                                        $_ReturnText = "-";
                                    }
                                    if(trim((string)($_Exeat["actualreturndatetime"] ?? "")) !== ""){
                                        $_DecisionText = trim((string)$_Exeat["actualreturndatetime"]);
                                    }else{
                                        $_DecisionText = ($_Status === "pending") ? "Awaiting decision" : trim((string)$_Exeat["decisiondatetime"]);
                                    }
                                    $_ExeatRowClass = "senior-row-calm";
                                    if(trim((string)($_Exeat["actualreturndatetime"] ?? "")) !== ""){
                                        $_ExeatRowClass = "senior-row-calm";
                                    }elseif($_Status === "approved" && (int)($_Exeat["is_overdue"] ?? 0) === 1){
                                        $_ExeatRowClass = "senior-row-critical";
                                    }elseif($_Status === "pending"){
                                        $_ExeatRowClass = "senior-row-watch";
                                    }
                                    echo "<tr class='".$_ExeatRowClass."'>";
                                    echo "<td>".senior_house_esc($_Exeat["requestedatetime"])."</td>";
                                    echo "<td><span class='senior-name'>".senior_house_esc($_Exeat["student_name"])."</span><div class='senior-muted'>".senior_house_esc($_Exeat["userid"])."</div></td>";
                                    echo "<td>".senior_house_esc($_Exeat["housename"])."</td>";
                                    echo "<td><span class='senior-badge ".$_TypeClass."'>".senior_house_esc($_Type)."</span></td>";
                                    echo "<td><span class='senior-badge ".$_StatusClass."'>".senior_house_esc($_StatusLabel)."</span></td>";
                                    echo "<td>".senior_house_esc($_ReturnText)."</td>";
                                    echo "<td>".senior_house_esc($_DecisionText);
                                    if(trim((string)($_Exeat["actualreturndatetime"] ?? "")) !== ""){
                                        if(trim((string)$_Exeat["returnedby"]) !== ""){
                                            echo "<div class='senior-muted'>Checked in by ".senior_house_esc($_Exeat["returnedby"])."</div>";
                                        }
                                        if(trim((string)$_Exeat["returnnote"]) !== ""){
                                            echo "<div class='senior-muted'>".senior_house_esc($_Exeat["returnnote"])."</div>";
                                        }
                                    }elseif(trim((string)$_Exeat["decisionby"]) !== ""){
                                        echo "<div class='senior-muted'>By ".senior_house_esc($_Exeat["decisionby"])."</div>";
                                        if((int)($_Exeat["is_overdue"] ?? 0) === 1 && trim((string)($_Exeat["expected_returndatetime"] ?? "")) !== ""){
                                            echo "<div class='senior-muted'>Expected back ".senior_house_esc($_Exeat["expected_returndatetime"])."</div>";
                                        }
                                    }
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            }else{
                                echo "<tr><td colspan='7' class='senior-empty'>".senior_house_esc($_CanManageSeniorHouse ? "No exeat requests recorded yet." : "No exeat requests were found in your current scope.")."</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
