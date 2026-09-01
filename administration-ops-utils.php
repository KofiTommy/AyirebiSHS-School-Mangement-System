<?php
if(!function_exists('administration_ops_ensure_tables')){
function administration_ops_ensure_tables($con){
    @mysqli_query($con,"CREATE TABLE IF NOT EXISTS tblmaintenanceissue(issueid VARCHAR(60) NOT NULL, title VARCHAR(180) NOT NULL, location VARCHAR(180) NOT NULL, priority VARCHAR(20) NOT NULL DEFAULT 'Normal', description TEXT, status VARCHAR(20) NOT NULL DEFAULT 'open', reportedby VARCHAR(60) NOT NULL, datetimeentry DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(issueid), KEY idx_maintenance_status(status,priority)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $columns = array(
        'assignedto' => "ALTER TABLE tblmaintenanceissue ADD COLUMN assignedto VARCHAR(60) NOT NULL DEFAULT '' AFTER reportedby",
        'duedate' => "ALTER TABLE tblmaintenanceissue ADD COLUMN duedate DATE NULL AFTER assignedto",
        'resolvedat' => "ALTER TABLE tblmaintenanceissue ADD COLUMN resolvedat DATETIME NULL AFTER duedate",
        'resolutionnote' => "ALTER TABLE tblmaintenanceissue ADD COLUMN resolutionnote VARCHAR(500) NULL AFTER resolvedat",
        'updatedat' => "ALTER TABLE tblmaintenanceissue ADD COLUMN updatedat DATETIME NULL AFTER resolutionnote"
    );
    foreach($columns as $column => $sql){
        $check = @mysqli_query($con, "SHOW COLUMNS FROM tblmaintenanceissue LIKE '".mysqli_real_escape_string($con, $column)."'");
        if(!$check || mysqli_num_rows($check) === 0){ @mysqli_query($con, $sql); }
    }
    @mysqli_query($con,"CREATE TABLE IF NOT EXISTS tblstudentwelfarecase (
        caseid VARCHAR(60) NOT NULL PRIMARY KEY,
        studentid VARCHAR(60) NOT NULL,
        category VARCHAR(60) NOT NULL,
        incidentdate DATE NOT NULL,
        summary TEXT NOT NULL,
        actiontaken TEXT NULL,
        parentcontacted TINYINT(1) NOT NULL DEFAULT 0,
        followupdate DATE NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        recordedby VARCHAR(60) NOT NULL,
        createdat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updatedat DATETIME NULL,
        KEY idx_welfare_student(studentid), KEY idx_welfare_status(status,followupdate), KEY idx_welfare_date(incidentdate)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
}
if(!function_exists('administration_ops_id')){
function administration_ops_id($prefix = 'MT'){ return strtoupper($prefix).date('YmdHis').mt_rand(10,99); }
}
if(!function_exists('administration_ops_is_lead')){
function administration_ops_is_lead(){
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL'] === 'user' && $_SESSION['SYSTEMTYPE'] === 'AssistantHeadAdministration';
}
}
if(!function_exists('administration_ops_escape')){
function administration_ops_escape($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}
?>