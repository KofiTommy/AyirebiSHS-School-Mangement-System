<?php
if(!isset($_SESSION['USERID']) || $_SESSION['USERID']==""){
 header("location:index.php");
 exit();
}
include("dbstring.php");
include_once("user-management-utils.php");
include_once("engagement-utils.php");
ensure_user_management_columns($con);
//Read the key from a file
//$filename=fopen("api.txt", "r");

$_ValidKey=md5("hnWZab3Fjs9IwEcABz47-B2Hdp9OIluKLfbRhvPaC-UNrk7ESwZz8H01afbI4B-kZUbfhQJ1OtGrSYI7c0u01-01-2020");

$stmt=mysqli_prepare($con,"SELECT apikey FROM tblapi WHERE apikey=? AND status='inuse' LIMIT 1");
if($stmt){
    mysqli_stmt_bind_param($stmt,"s",$_ValidKey);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if(mysqli_stmt_num_rows($stmt)==0){
        header("location:app_authentication.php");
        exit();
    }
    mysqli_stmt_close($stmt);
}
else{
    header("location:app_authentication.php");
    exit();
}

$__CurrentScript = basename((string)(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : ''));

/*
 * A graduate may still have a valid portal session or a saved Student-dashboard
 * link.  Do not let that bypass the Alumni journey: their normal school login
 * must always take them through the Alumni activation page first.
 */
if($__CurrentScript !== 'alumni-activate.php' && $__CurrentScript !== 'logout.php'){
    $stmtAlumniStatus = @mysqli_prepare($con, "SELECT status FROM tblsystemuser WHERE userid=? LIMIT 1");
    if($stmtAlumniStatus){
        mysqli_stmt_bind_param($stmtAlumniStatus, 's', $_SESSION['USERID']);
        mysqli_stmt_execute($stmtAlumniStatus);
        $alumniStatusResult = mysqli_stmt_get_result($stmtAlumniStatus);
        $alumniStatusRow = $alumniStatusResult ? mysqli_fetch_array($alumniStatusResult, MYSQLI_ASSOC) : null;
        mysqli_stmt_close($stmtAlumniStatus);
        if($alumniStatusRow && strtolower(trim((string)$alumniStatusRow['status'])) === 'alumni'){
            header('location:alumni-activate.php');
            exit();
        }
    }
}

if($__CurrentScript !== "change-password.php" && $__CurrentScript !== "logout.php"){
    $stmtUserReset = @mysqli_prepare($con, "SELECT password_reset_required FROM tblsystemuser WHERE userid=? LIMIT 1");
    if($stmtUserReset){
        mysqli_stmt_bind_param($stmtUserReset, "s", $_SESSION['USERID']);
        mysqli_stmt_execute($stmtUserReset);
        $userResetResult = mysqli_stmt_get_result($stmtUserReset);
        if($userResetResult && ($userResetRow = mysqli_fetch_array($userResetResult, MYSQLI_ASSOC))){
            if((int)$userResetRow['password_reset_required'] === 1){
                mysqli_stmt_close($stmtUserReset);
                header("location:change-password.php?force=1");
                exit();
            }
        }
        mysqli_stmt_close($stmtUserReset);
    }
}
um_enforce_current_module_access($con);
engagement_track_current_script($con);
um_touch_current_user_activity($con, $__CurrentScript);
um_log_current_user_visit($con, $__CurrentScript);
?>
