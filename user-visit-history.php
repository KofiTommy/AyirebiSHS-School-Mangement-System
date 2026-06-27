<?php
session_start();
include("check-login.php");

if(!function_exists('um_is_admin_manager') || !um_is_admin_manager()){
    header("location:".(function_exists('um_home_link_for_session') ? um_home_link_for_session() : "index.php"));
    exit();
}

ensure_user_visit_log_table($con);

function uvh_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function uvh_relative_time($value){
    $timestamp = strtotime((string)$value);
    if($timestamp === false){
        return trim((string)$value);
    }
    $diff = time() - $timestamp;
    if($diff < 0){
        $diff = 0;
    }
    if($diff < 60){
        return "Just now";
    }
    if($diff < 3600){
        return floor($diff / 60)." min ago";
    }
    if($diff < 86400){
        return floor($diff / 3600)." hr ago";
    }
    return date("d M Y, g:i a", $timestamp);
}

function uvh_role_label($accessLevel, $systemType){
    $accessLevel = trim((string)$accessLevel);
    $systemType = trim((string)$systemType);
    if($accessLevel === "administrator"){
        return $systemType === "super_user" ? "Super User" : "Administrator";
    }
    if($systemType === "AssistantHeadAcademic"){
        return "Assistant Head Academics";
    }
    return $systemType !== "" ? $systemType : "User";
}

$_Role = isset($_GET['role']) ? trim((string)$_GET['role']) : "";
$_Search = isset($_GET['search']) ? trim((string)$_GET['search']) : "";
$_AllowedRoles = array("", "administrator", "normal_user", "super_user", "Teacher", "Student", "Headmaster", "AssistantHeadAcademic", "User");
if(!in_array($_Role, $_AllowedRoles, true)){
    $_Role = "";
}

$_Where = "v.visitedat >= (NOW() - INTERVAL 24 HOUR)";
if($_Role !== ""){
    $_RoleEsc = mysqli_real_escape_string($con, $_Role);
    if($_Role === "administrator"){
        $_Where .= " AND v.accesslevel='administrator'";
    }else{
        $_Where .= " AND v.systemtype='$_RoleEsc'";
    }
}
if($_Search !== ""){
    $_SearchEsc = mysqli_real_escape_string($con, $_Search);
    $_SearchLike = "%".$_SearchEsc."%";
    $_Where .= " AND (
        v.userid LIKE '$_SearchLike'
        OR v.fullname LIKE '$_SearchLike'
        OR v.systemtype LIKE '$_SearchLike'
        OR v.scriptname LIKE '$_SearchLike'
        OR v.requestpath LIKE '$_SearchLike'
        OR v.ipaddress LIKE '$_SearchLike'
    )";
}

$_Summary = array(
    "total_visits" => 0,
    "unique_users" => 0,
    "latest_visit" => ""
);
$_SummaryRes = mysqli_query($con, "SELECT
        COUNT(*) AS total_visits,
        COUNT(DISTINCT v.userid) AS unique_users,
        MAX(v.visitedat) AS latest_visit
    FROM tbluservisitlog v
    WHERE $_Where");
if($_SummaryRes && ($_SummaryRow = mysqli_fetch_array($_SummaryRes, MYSQLI_ASSOC))){
    $_Summary = array_merge($_Summary, $_SummaryRow);
}

$_RoleRows = array();
$_RoleRes = mysqli_query($con, "SELECT
        v.accesslevel,
        v.systemtype,
        COUNT(DISTINCT v.userid) AS user_total,
        COUNT(*) AS visit_total
    FROM tbluservisitlog v
    WHERE $_Where
    GROUP BY v.accesslevel, v.systemtype
    ORDER BY user_total DESC, visit_total DESC");
if($_RoleRes){
    while($_Row = mysqli_fetch_array($_RoleRes, MYSQLI_ASSOC)){
        $_RoleRows[] = $_Row;
    }
}

$_TopPages = array();
$_TopPagesRes = mysqli_query($con, "SELECT
        v.scriptname,
        COUNT(*) AS visit_total,
        COUNT(DISTINCT v.userid) AS user_total
    FROM tbluservisitlog v
    WHERE $_Where
    GROUP BY v.scriptname
    ORDER BY visit_total DESC, user_total DESC
    LIMIT 8");
if($_TopPagesRes){
    while($_Row = mysqli_fetch_array($_TopPagesRes, MYSQLI_ASSOC)){
        $_TopPages[] = $_Row;
    }
}

$_VisitorRows = array();
$_VisitorRes = mysqli_query($con, "SELECT
        v.userid,
        MAX(v.fullname) AS fullname,
        MAX(v.accesslevel) AS accesslevel,
        MAX(v.systemtype) AS systemtype,
        COUNT(*) AS page_views,
        COUNT(DISTINCT v.scriptname) AS unique_pages,
        MIN(v.visitedat) AS first_visit,
        MAX(v.visitedat) AS last_visit,
        SUBSTRING_INDEX(GROUP_CONCAT(v.scriptname ORDER BY v.visitedat DESC SEPARATOR '||'), '||', 1) AS last_page,
        SUBSTRING_INDEX(GROUP_CONCAT(v.ipaddress ORDER BY v.visitedat DESC SEPARATOR '||'), '||', 1) AS last_ip,
        SUBSTRING_INDEX(GROUP_CONCAT(v.useragent ORDER BY v.visitedat DESC SEPARATOR '||'), '||', 1) AS last_agent
    FROM tbluservisitlog v
    WHERE $_Where
    GROUP BY v.userid
    ORDER BY last_visit DESC
    LIMIT 300");
if($_VisitorRes){
    while($_Row = mysqli_fetch_array($_VisitorRes, MYSQLI_ASSOC)){
        $_VisitorRows[] = $_Row;
    }
}

$_RecentRows = array();
$_RecentRes = mysqli_query($con, "SELECT
        v.userid,
        v.fullname,
        v.accesslevel,
        v.systemtype,
        v.scriptname,
        v.requestpath,
        v.ipaddress,
        v.visitedat
    FROM tbluservisitlog v
    WHERE $_Where
    ORDER BY v.visitedat DESC
    LIMIT 160");
if($_RecentRes){
    while($_Row = mysqli_fetch_array($_RecentRes, MYSQLI_ASSOC)){
        $_RecentRows[] = $_Row;
    }
}

$_RoleLabels = array(
    "" => "All Roles",
    "administrator" => "All Administrators",
    "normal_user" => "Administrators",
    "super_user" => "Super Users",
    "Teacher" => "Teachers",
    "Student" => "Students",
    "Headmaster" => "Headmaster",
    "AssistantHeadAcademic" => "Assistant Head Academics",
    "User" => "Office Users"
);
?>
<!DOCTYPE html>
<html>
<head>
<title>User Visit History</title>
<?php include("links.php"); ?>
<link rel="stylesheet" href="css/user-visit-history.css">
</head>
<body class="visit-history-page">
<div class="header">
<?php include("menu.php"); ?>
</div>
<main class="visit-shell">
    <section class="visit-hero">
        <div>
            <span class="visit-eyebrow"><i class="fa fa-clock-o"></i> Last 24 Hours</span>
            <h1>User Visit History</h1>
            <p>Review logged-in users who have opened the app recently, including their latest page and visit count.</p>
        </div>
        <a class="visit-btn" href="admin.php"><i class="fa fa-home"></i> Admin Dashboard</a>
    </section>

    <section class="visit-stats">
        <article class="visit-stat">
            <span>Total Visits</span>
            <strong><?php echo number_format((int)$_Summary["total_visits"]); ?></strong>
            <small>Page visits recorded in the selected 24-hour window.</small>
        </article>
        <article class="visit-stat">
            <span>Unique Users</span>
            <strong><?php echo number_format((int)$_Summary["unique_users"]); ?></strong>
            <small>Logged-in accounts seen in the app.</small>
        </article>
        <article class="visit-stat">
            <span>Latest Visit</span>
            <strong><?php echo $_Summary["latest_visit"] !== "" ? uvh_esc(uvh_relative_time($_Summary["latest_visit"])) : "-"; ?></strong>
            <small><?php echo $_Summary["latest_visit"] !== "" ? uvh_esc($_Summary["latest_visit"]) : "No visit recorded yet."; ?></small>
        </article>
    </section>

    <section class="visit-panel">
        <form method="get" action="user-visit-history.php" class="visit-filter">
            <label>
                <span>Search</span>
                <input type="text" name="search" value="<?php echo uvh_esc($_Search); ?>" placeholder="Name, user ID, page or IP address">
            </label>
            <label>
                <span>Role</span>
                <select name="role">
                    <?php foreach($_RoleLabels as $_Value => $_Label){ ?>
                    <option value="<?php echo uvh_esc($_Value); ?>" <?php echo $_Role === $_Value ? "selected" : ""; ?>><?php echo uvh_esc($_Label); ?></option>
                    <?php } ?>
                </select>
            </label>
            <div class="visit-filter-actions">
                <button type="submit" class="visit-btn visit-btn-primary"><i class="fa fa-search"></i> Filter</button>
                <a class="visit-btn" href="user-visit-history.php"><i class="fa fa-times"></i> Clear</a>
            </div>
        </form>
    </section>

    <section class="visit-grid">
        <div class="visit-panel">
            <div class="visit-panel-head">
                <h2>Role Breakdown</h2>
                <span><?php echo count($_RoleRows); ?> role group(s)</span>
            </div>
            <div class="visit-mini-list">
                <?php if(count($_RoleRows) > 0){ ?>
                    <?php foreach($_RoleRows as $_RoleRow){ ?>
                    <div class="visit-mini-item">
                        <strong><?php echo uvh_esc(uvh_role_label($_RoleRow["accesslevel"], $_RoleRow["systemtype"])); ?></strong>
                        <span><?php echo number_format((int)$_RoleRow["user_total"]); ?> user(s), <?php echo number_format((int)$_RoleRow["visit_total"]); ?> visit(s)</span>
                    </div>
                    <?php } ?>
                <?php }else{ ?>
                <p class="visit-empty">No role activity found for the selected filter.</p>
                <?php } ?>
            </div>
        </div>

        <div class="visit-panel">
            <div class="visit-panel-head">
                <h2>Most Visited Pages</h2>
                <span>Top <?php echo count($_TopPages); ?></span>
            </div>
            <div class="visit-mini-list">
                <?php if(count($_TopPages) > 0){ ?>
                    <?php foreach($_TopPages as $_PageRow){ ?>
                    <div class="visit-mini-item">
                        <strong><?php echo uvh_esc($_PageRow["scriptname"]); ?></strong>
                        <span><?php echo number_format((int)$_PageRow["visit_total"]); ?> visit(s) by <?php echo number_format((int)$_PageRow["user_total"]); ?> user(s)</span>
                    </div>
                    <?php } ?>
                <?php }else{ ?>
                <p class="visit-empty">No page visits found for the selected filter.</p>
                <?php } ?>
            </div>
        </div>
    </section>

    <section class="visit-panel">
        <div class="visit-panel-head">
            <h2>Users Seen In The Last 24 Hours</h2>
            <span><?php echo count($_VisitorRows); ?> account(s)</span>
        </div>
        <div class="visit-table-wrap">
            <table class="visit-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Page Views</th>
                        <th>First Visit</th>
                        <th>Last Visit</th>
                        <th>Last Page</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($_VisitorRows) > 0){ ?>
                        <?php foreach($_VisitorRows as $_Visitor){ ?>
                        <tr>
                            <td>
                                <strong><?php echo uvh_esc($_Visitor["fullname"] !== "" ? $_Visitor["fullname"] : $_Visitor["userid"]); ?></strong>
                                <span><?php echo uvh_esc($_Visitor["userid"]); ?></span>
                            </td>
                            <td><?php echo uvh_esc(uvh_role_label($_Visitor["accesslevel"], $_Visitor["systemtype"])); ?></td>
                            <td><?php echo number_format((int)$_Visitor["page_views"]); ?> <span class="visit-muted"><?php echo number_format((int)$_Visitor["unique_pages"]); ?> page(s)</span></td>
                            <td><?php echo uvh_esc(uvh_relative_time($_Visitor["first_visit"])); ?></td>
                            <td><?php echo uvh_esc(uvh_relative_time($_Visitor["last_visit"])); ?></td>
                            <td><?php echo uvh_esc($_Visitor["last_page"]); ?></td>
                            <td><?php echo uvh_esc($_Visitor["last_ip"] !== "" ? $_Visitor["last_ip"] : "-"); ?></td>
                        </tr>
                        <?php } ?>
                    <?php }else{ ?>
                    <tr><td colspan="7" class="visit-empty">No users matched this 24-hour view.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="visit-panel">
        <div class="visit-panel-head">
            <h2>Recent Page Visits</h2>
            <span>Latest <?php echo count($_RecentRows); ?></span>
        </div>
        <div class="visit-table-wrap">
            <table class="visit-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Page</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($_RecentRows) > 0){ ?>
                        <?php foreach($_RecentRows as $_Visit){ ?>
                        <tr>
                            <td><?php echo uvh_esc(uvh_relative_time($_Visit["visitedat"])); ?><span><?php echo uvh_esc($_Visit["visitedat"]); ?></span></td>
                            <td>
                                <strong><?php echo uvh_esc($_Visit["fullname"] !== "" ? $_Visit["fullname"] : $_Visit["userid"]); ?></strong>
                                <span><?php echo uvh_esc($_Visit["userid"]); ?></span>
                            </td>
                            <td><?php echo uvh_esc(uvh_role_label($_Visit["accesslevel"], $_Visit["systemtype"])); ?></td>
                            <td><?php echo uvh_esc($_Visit["scriptname"]); ?><span><?php echo uvh_esc($_Visit["requestpath"]); ?></span></td>
                            <td><?php echo uvh_esc($_Visit["ipaddress"] !== "" ? $_Visit["ipaddress"] : "-"); ?></td>
                        </tr>
                        <?php } ?>
                    <?php }else{ ?>
                    <tr><td colspan="5" class="visit-empty">No page visits found for this 24-hour view.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
