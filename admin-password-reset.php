<?php
session_start();
$_SESSION['Message'] = "";

include("dbstring.php");
include("audit_notifications.php");
include_once("house-master-utils.php");
include_once("user-management-utils.php");
ensure_user_management_columns($con);

if (!function_exists('ensureAdminPasswordResetSmsLogTable')) {
    function ensureAdminPasswordResetSmsLogTable($con)
    {
        static $done = false;
        if ($done || !$con) {
            return;
        }
        $done = true;
        @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tbladminpasswordresetsmslog (
            logid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            target_userid VARCHAR(60) NOT NULL,
            target_type VARCHAR(30) NOT NULL,
            mobile VARCHAR(40) DEFAULT '',
            sms_message VARCHAR(255) DEFAULT '',
            sms_status VARCHAR(30) NOT NULL,
            sms_code VARCHAR(80) DEFAULT '',
            admin_userid VARCHAR(60) DEFAULT '',
            datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (logid),
            KEY idx_target_userid (target_userid),
            KEY idx_sms_status (sms_status),
            KEY idx_datetimeentry (datetimeentry)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('logAdminPasswordResetSmsOutcome')) {
    function logAdminPasswordResetSmsOutcome($con, $targetUserId, $targetType, $mobile, $smsMessage, $smsStatus, $smsCode = '')
    {
        if (!$con) {
            return false;
        }

        ensureAdminPasswordResetSmsLogTable($con);

        $adminUserId = isset($_SESSION['USERID']) ? (string)$_SESSION['USERID'] : '';
        $targetUserId = (string)$targetUserId;
        $targetType = (string)$targetType;
        $mobile = (string)$mobile;
        $smsMessage = (string)$smsMessage;
        $smsStatus = (string)$smsStatus;
        $smsCode = (string)$smsCode;

        $stmt = @mysqli_prepare($con, "INSERT INTO tbladminpasswordresetsmslog
            (target_userid,target_type,mobile,sms_message,sms_status,sms_code,admin_userid,datetimeentry)
            VALUES (?,?,?,?,?,?,?,NOW())");
        if (!$stmt) {
            return false;
        }

        @mysqli_stmt_bind_param($stmt, "sssssss", $targetUserId, $targetType, $mobile, $smsMessage, $smsStatus, $smsCode, $adminUserId);
        $ok = @mysqli_stmt_execute($stmt);
        @mysqli_stmt_close($stmt);
        return $ok ? true : false;
    }
}

$isAdmin = isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
    $_SESSION['ACCESSLEVEL'] === "administrator" &&
    in_array($_SESSION['SYSTEMTYPE'], array("normal_user", "super_user"), true);

if (!$isAdmin) {
    header("location:index.php");
    exit();
}

$targetType = isset($_GET['type']) ? trim($_GET['type']) : "Student";
if ($targetType !== "Teacher" && $targetType !== "Student") {
    $targetType = "Student";
}

$keyword = isset($_GET['q']) ? trim($_GET['q']) : "";

if (isset($_POST['admin_reset_password'])) {
    $targetUserId = isset($_POST['target_userid']) ? trim($_POST['target_userid']) : "";
    $newUsername = isset($_POST['new_username']) ? trim($_POST['new_username']) : "";
    $newPasswordRaw = isset($_POST['new_password']) ? trim($_POST['new_password']) : "";
    $resetType = isset($_POST['target_type']) ? trim($_POST['target_type']) : "Student";

    if ($resetType !== "Teacher" && $resetType !== "Student") {
        $resetType = "Student";
    }

    if ($targetUserId === "" || $newUsername === "" || $newPasswordRaw === "") {
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background:#fff;border:1px solid #f5c2c7;padding:10px'>User, username and password are required.</div>";
    } elseif (strlen($newPasswordRaw) < 6) {
        $_SESSION['Message'] = "<div style='color:red;text-align:center;background:#fff;border:1px solid #f5c2c7;padding:10px'>New password must be at least 6 characters.</div>";
    } else {
        $stmtCheck = mysqli_prepare($con, "SELECT userid,firstname,othernames,surname,mobile FROM tblsystemuser WHERE userid=? AND systemtype=? LIMIT 1");
        if ($stmtCheck) {
            mysqli_stmt_bind_param($stmtCheck, "ss", $targetUserId, $resetType);
            mysqli_stmt_execute($stmtCheck);
            $resCheck = mysqli_stmt_get_result($stmtCheck);

            if ($resCheck && $userRow = mysqli_fetch_array($resCheck, MYSQLI_ASSOC)) {
                $newPassword = md5($newPasswordRaw);
                $stmtUpdate = mysqli_prepare($con, "UPDATE tblsystemuser SET username=?, password=?, password_reset_required=1, password_last_reset_at=NOW() WHERE userid=? AND systemtype=? LIMIT 1");
                if ($stmtUpdate) {
                    mysqli_stmt_bind_param($stmtUpdate, "ssss", $newUsername, $newPassword, $targetUserId, $resetType);
                    $okUpdate = mysqli_stmt_execute($stmtUpdate);
                    mysqli_stmt_close($stmtUpdate);

                    if ($okUpdate && mysqli_affected_rows($con) > 0) {
                        $fullName = trim($userRow['firstname'] . " " . $userRow['othernames'] . " " . $userRow['surname']);
                        logSystemChange(
                            $con,
                            "ADMIN_PASSWORD_RESET",
                            "Admin reset password for " . $resetType . " user " . $fullName . " (" . $targetUserId . ").",
                            $targetUserId
                        );
                        $smsNote = "";
                        if ($resetType === "Teacher") {
                            $teacherPhone = trim((string)$userRow['mobile']);
                            $teacherName = trim($userRow['firstname'] . " " . $userRow['othernames'] . " " . $userRow['surname']);
                            $smsMessage = "Hello " . $teacherName . ", your account password was reset by school admin. Username: " . $newUsername . ". Please login and change password immediately.";
                            if ($teacherPhone !== "") {
                                $smsCode = "";
                                $smsSent = send_bulk_sms_message($teacherPhone, $smsMessage, $smsCode);
                                if ($smsSent) {
                                    logAdminPasswordResetSmsOutcome($con, $targetUserId, $resetType, $teacherPhone, $smsMessage, "SENT", (string)$smsCode);
                                    $smsNote = "<br/><span style='color:#065f46;'>Teacher SMS notification sent.</span>";
                                } else {
                                    logAdminPasswordResetSmsOutcome($con, $targetUserId, $resetType, $teacherPhone, $smsMessage, "FAILED", (string)$smsCode);
                                    $smsNote = "<br/><span style='color:#92400e;'>Password reset completed, but teacher SMS failed (code: " . htmlspecialchars((string)$smsCode) . ").</span>";
                                }
                            } else {
                                logAdminPasswordResetSmsOutcome($con, $targetUserId, $resetType, "", $smsMessage, "NO_PHONE", "NO_PHONE");
                                $smsNote = "<br/><span style='color:#92400e;'>Password reset completed, but no teacher phone number is available.</span>";
                            }
                        }
                        $_SESSION['Message'] = "<div style='color:green;text-align:center;background:#fff;border:1px solid #b7eb8f;padding:10px'>Password reset successful for <strong>" . htmlspecialchars($targetUserId) . "</strong>." . $smsNote . "</div>";
                    } else {
                        $_SESSION['Message'] = "<div style='color:red;text-align:center;background:#fff;border:1px solid #f5c2c7;padding:10px'>Reset failed. Please try again.</div>";
                    }
                } else {
                    $_SESSION['Message'] = "<div style='color:red;text-align:center;background:#fff;border:1px solid #f5c2c7;padding:10px'>Reset failed. Could not prepare update query.</div>";
                }
            } else {
                $_SESSION['Message'] = "<div style='color:red;text-align:center;background:#fff;border:1px solid #f5c2c7;padding:10px'>Selected user was not found.</div>";
            }
            mysqli_stmt_close($stmtCheck);
        } else {
            $_SESSION['Message'] = "<div style='color:red;text-align:center;background:#fff;border:1px solid #f5c2c7;padding:10px'>Reset failed. Could not prepare lookup query.</div>";
        }
    }

    $encodedQ = urlencode($keyword);
    header("location:admin-password-reset.php?type=" . urlencode($resetType) . "&q=" . $encodedQ);
    exit();
}
?>
<html>
<head>
<?php include("links.php"); ?>
<style>
.reset-wrap { max-width: 1200px; margin: 0 auto; padding: 10px 14px 20px; }
.reset-grid { display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 14px; }
.reset-card { border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; padding: 12px; }
.filter-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 10px; }
.filter-row input, .filter-row select { padding: 7px 8px; border: 1px solid #d1d5db; border-radius: 8px; }
.btn-sm { border: 1px solid #1d4ed8; background: #2563eb; color: #fff; padding: 7px 10px; border-radius: 8px; cursor: pointer; }
.btn-sm.secondary { border-color: #6b7280; background: #6b7280; }
.table-scroll { overflow-x: auto; }
table.reset-table { width: 100%; border-collapse: collapse; background: #fff; }
table.reset-table th, table.reset-table td { border: 1px solid #e5e7eb; padding: 7px; font-size: 13px; }
table.reset-table th { background: #f8fafc; text-align: left; }
.badge-stu { color: #0f5132; background: #d1e7dd; border-radius: 999px; padding: 2px 8px; font-size: 11px; }
.badge-tea { color: #084298; background: #cfe2ff; border-radius: 999px; padding: 2px 8px; font-size: 11px; }
@media (max-width: 960px) { .reset-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<div class="header">
<?php include("menu.php"); ?>
</div>

<div class="reset-wrap">
    <h2 style="margin:8px 0 12px;">Administrator Password Reset</h2>
    <div class="reset-grid">
        <div class="reset-card">
            <?php include("menuboard.php"); ?>
        </div>
        <div class="reset-card">
            <?php
            if (isset($_SESSION['Message']) && $_SESSION['Message'] !== "") {
                echo $_SESSION['Message'];
                $_SESSION['Message'] = "";
            }
            ?>

            <form method="get" action="admin-password-reset.php" class="filter-row">
                <label><strong>User Type</strong></label>
                <select id="type" name="type">
                    <option value="Student" <?php echo ($targetType === "Student" ? "selected" : ""); ?>>Student</option>
                    <option value="Teacher" <?php echo ($targetType === "Teacher" ? "selected" : ""); ?>>Teacher</option>
                </select>
                <input type="text" id="q" name="q" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="Search by user ID or name">
                <button class="btn-sm" type="submit"><i class="fa fa-search"></i> Search</button>
                <a class="btn-sm secondary" href="admin-password-reset.php?type=<?php echo urlencode($targetType); ?>" style="text-decoration:none;"><i class="fa fa-undo"></i> Reset</a>
            </form>

            <div class="table-scroll">
                <table class="reset-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Current Username</th>
                            <th style="min-width:280px;">Reset Credentials</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if ($keyword !== "") {
                        $sql = "SELECT userid,firstname,othernames,surname,username,systemtype
                                FROM tblsystemuser
                                WHERE systemtype=?
                                  AND (userid LIKE ? OR firstname LIKE ? OR othernames LIKE ? OR surname LIKE ?)
                                ORDER BY firstname ASC,surname ASC
                                LIMIT 120";
                    } else {
                        $sql = "SELECT userid,firstname,othernames,surname,username,systemtype
                                FROM tblsystemuser
                                WHERE systemtype=?
                                ORDER BY firstname ASC,surname ASC
                                LIMIT 120";
                    }

                    $stmtList = mysqli_prepare($con, $sql);
                    $rowsFound = 0;
                    if ($stmtList) {
                        if ($keyword !== "") {
                            $like = "%" . $keyword . "%";
                            mysqli_stmt_bind_param($stmtList, "sssss", $targetType, $like, $like, $like, $like);
                        } else {
                            mysqli_stmt_bind_param($stmtList, "s", $targetType);
                        }
                        mysqli_stmt_execute($stmtList);
                        $resList = mysqli_stmt_get_result($stmtList);
                        if ($resList) {
                            while ($row = mysqli_fetch_array($resList, MYSQLI_ASSOC)) {
                                $rowsFound++;
                                $fullName = trim($row['firstname'] . " " . $row['othernames'] . " " . $row['surname']);
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['userid']) . "</td>";
                                echo "<td>" . htmlspecialchars($fullName) . "</td>";
                                if ($row['systemtype'] === "Teacher") {
                                    echo "<td><span class='badge-tea'>Teacher</span></td>";
                                } else {
                                    echo "<td><span class='badge-stu'>Student</span></td>";
                                }
                                echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                                echo "<td>";
                                echo "<form method='post' action='admin-password-reset.php?type=" . urlencode($targetType) . "&q=" . urlencode($keyword) . "' class='filter-row' style='margin:0;'>";
                                echo "<input type='hidden' name='target_userid' value='" . htmlspecialchars($row['userid']) . "'>";
                                echo "<input type='hidden' name='target_type' value='" . htmlspecialchars($row['systemtype']) . "'>";
                                echo "<input type='text' name='new_username' value='" . htmlspecialchars($row['username']) . "' placeholder='New username' required>";
                                echo "<input type='password' name='new_password' placeholder='New password (min 6 chars)' required minlength='6'>";
                                echo "<button class='btn-sm' type='submit' name='admin_reset_password' onclick=\"return confirm('Reset password for this user?');\"><i class='fa fa-key'></i> Reset</button>";
                                echo "</form>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        }
                        mysqli_stmt_close($stmtList);
                    }

                    if ($rowsFound === 0) {
                        echo "<tr><td colspan='5' style='text-align:center;color:#6b7280;'>No " . htmlspecialchars(strtolower($targetType)) . " records found.</td></tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
            <p style="margin:10px 0 0;color:#6b7280;font-size:12px;">Only administrator accounts can access this page. Password resets are logged in system change log.</p>
        </div>
    </div>
</div>
</body>
</html>
