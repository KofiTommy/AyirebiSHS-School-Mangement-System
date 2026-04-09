<?php
if(!isset($_SESSION['USERID']) || $_SESSION['USERID']==""){
 header("location:index.php");
 exit();
}
include("dbstring.php");
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
?>
