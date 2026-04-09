<?php
@$_SESSION["Message"]="";

/*$tables=array("tblmessages","tbladministration","tbltimetable",
"tbltermregistry","tblmark","tblsubjectassignment","tblsubjectclassification","tblsubject","tblstudentterminalreport",
"tblsmsalert","tblschoolinfo","tblsalarypayment","tblsalarydetails","tblpayment",
"tblbilling","tbltransaction","tblitemprice","tbldeductions","tblclass","tblclassentry",
"tblbatch","tblitem","tblbranch","tblcompany");
*/
$tables=array("tblmessages","tbladministration","tbltimetable",
"tbltermregistry","tblmark","tblsubjectassignment","tblsubjectclassification","tblsubject","tblstudentterminalreport",
"tblsmsalert","tblschoolinfo","tblsalarypayment","tblsalarydetails","tblpayment",
"tblbilling","tbltransaction","tblitemprice","tbldeductions","tblclass","tblclassentry",
"tblbatch","tblitem","tblsmsexamresults");

for($i=0;$i<count($tables);$i++){
	deleteFromGlobalTables($tables[$i]);
}

function deleteFromGlobalTables($table_name){
include("dbstring.php");

$sql="DELETE FROM $table_name";
$result=mysqli_query($con,$sql);

if($result){
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;padding:5px;background-color:#fee;border:1px solid #fbb;'>Table, $table_name successfully deleted</div>";
}
else{
$_Error=mysqli_error($con);
$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;padding:5px;background-color:#fee;border:1px solid #fbb;'>Table, $table_name failed to delete</div>";
}

}

echo $_SESSION["Message"];
?>