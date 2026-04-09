<div class="form-entry">
<?php
session_start();
include("dbstring.php");

function search_esc($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8");
}

$systemType = isset($_SESSION["SYSTEMTYPE"]) ? $_SESSION["SYSTEMTYPE"] : "";
if(!in_array($systemType, array("super_user", "normal_user"))){
    echo "<div style='color:#b91c1c;padding:8px;'>Access denied.</div>";
    exit;
}

$q = isset($_GET["search-item"]) ? trim((string)$_GET["search-item"]) : "";
if($q === ""){
    echo "<div style='color:#64748b;padding:8px;'>Type student name or index number to search.</div>";
    exit;
}
if(strlen($q) < 2){
    echo "<div style='color:#64748b;padding:8px;'>Type at least 2 characters.</div>";
    exit;
}

$like = "%".$q."%";
$qUpper = strtoupper($q);

$sql = "SELECT
    itm.userid,
    itm.firstname,
    itm.othernames,
    itm.surname,
    itm.homeaddress,
    itm.nextofkin_fullname,
    itm.nextofkin_contact
FROM tblsystemuser itm
WHERE itm.systemtype='Student'
  AND (
      itm.userid LIKE ?
      OR itm.firstname LIKE ?
      OR itm.othernames LIKE ?
      OR itm.surname LIKE ?
      OR CONCAT_WS(' ', itm.firstname, itm.othernames, itm.surname) LIKE ?
  )
ORDER BY
  CASE
    WHEN itm.userid = ? THEN 0
    WHEN UPPER(CONCAT_WS(' ', itm.firstname, itm.othernames, itm.surname)) = ? THEN 1
    WHEN itm.userid LIKE ? THEN 2
    ELSE 3
  END,
  itm.firstname ASC,
  itm.othernames ASC,
  itm.surname ASC
LIMIT 120";

$stmt = mysqli_prepare($con, $sql);
if(!$stmt){
    echo "<div style='color:#b91c1c;padding:8px;'>Search query failed.</div>";
    exit;
}

$idLikePrefix = $q."%";
mysqli_stmt_bind_param($stmt, "ssssssss", $like, $like, $like, $like, $like, $q, $qUpper, $idLikePrefix);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if(!$result){
    echo "<div style='color:#b91c1c;padding:8px;'>No result returned.</div>";
    mysqli_stmt_close($stmt);
    exit;
}

$count = mysqli_num_rows($result);
echo "<table border='1'>";
echo "<caption>".(int)$count." Students Found</caption>";
echo "<thead>";
echo "<tr><th colspan='3'>Action</th><th>Index Number</th><th>Full Name</th><th>Home Address</th><th>Next of Kin</th><th>Contact</th></tr>";
echo "</thead>";
echo "<tbody>";

while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
    $userid = (string)$row["userid"];
    $first = (string)$row["firstname"];
    $other = (string)$row["othernames"];
    $sur = (string)$row["surname"];
    $fullName = trim($first." ".$other." ".$sur);

    echo "<tr>";
    echo "<td align='center'><a href='payments.php?userid=".urlencode($userid)."' title='Click for Payment by ".search_esc($first)."'><i class='fa fa-money' style='color:green'></i></a></td>";
    echo "<td align='center'><a href='register_edit.php?edit_user=".urlencode($userid)."' title='Update Profile of ".search_esc($first)."'><i class='fa fa-edit' style='color:olive'></i></a></td>";
    echo "<td align='center'><a href='register.php?block_user=".urlencode($userid)."' title='Block ".search_esc($first)."'><i class='fa fa-user' style='color:red'></i></a></td>";
    echo "<td align='center'>".search_esc($userid)."</td>";
    echo "<td>".search_esc(strtoupper($fullName))."</td>";
    echo "<td>".search_esc($row["homeaddress"])."</td>";
    echo "<td>".search_esc($row["nextofkin_fullname"])."</td>";
    echo "<td align='center'>".search_esc($row["nextofkin_contact"])."</td>";
    echo "</tr>";

    $classSql = "SELECT bc.batch, ce.class_name, cl.datetimeentry
        FROM tblclass cl
        INNER JOIN tblclassentry ce ON cl.class_entryid=ce.class_entryid
        INNER JOIN tblbatch bc ON cl.batchid=bc.batchid
        WHERE cl.userid=?
        ORDER BY cl.datetimeentry DESC";
    $classStmt = mysqli_prepare($con, $classSql);
    echo "<tr><td colspan='8'>";
    if($classStmt){
        mysqli_stmt_bind_param($classStmt, "s", $userid);
        mysqli_stmt_execute($classStmt);
        $classRes = mysqli_stmt_get_result($classStmt);
        echo "<table>";
        echo "<thead><tr><th>Batch</th><th>Class</th><th>Date/Time</th></tr></thead>";
        if($classRes && mysqli_num_rows($classRes) > 0){
            while($rowc = mysqli_fetch_array($classRes, MYSQLI_ASSOC)){
                echo "<tr style='background-color:#efe'>";
                echo "<td>".search_esc($rowc["batch"])."</td>";
                echo "<td>".search_esc($rowc["class_name"])."</td>";
                echo "<td>".search_esc($rowc["datetimeentry"])."</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr style='background-color:#f8fafc'><td colspan='3'>No class history found.</td></tr>";
        }
        echo "</table>";
        mysqli_stmt_close($classStmt);
    } else {
        echo "<div style='color:#64748b;'>Class history unavailable.</div>";
    }
    echo "</td></tr>";
}

if($count === 0){
    echo "<tr><td colspan='8' style='text-align:center;color:#64748b;'>No student matched '".search_esc($q)."'.</td></tr>";
}

echo "</tbody>";
echo "</table>";

mysqli_stmt_close($stmt);
mysqli_close($con);
?>
</div><br/>
