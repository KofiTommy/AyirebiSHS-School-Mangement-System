<?php
session_start();
$_SESSION['Message']="";
?>


<?php
//Declare the variables
@$_TransactionId_Bi="";
//@$_Receivedby=$_SESSION['USERID'];
//@$_PaymentDate=$_POST['batch_month']." ".$_POST['batch_year'];
if(isset($_POST["print_all_bill"]))
{
      include("dbstring.php");
      include("config.php");
      include("company.php");
      //Get all the ordered items

require('fpdf181/fpdf.php');
//ob_start();

$pdf = new FPDF();
$pdf->AddPage();
$_GETUserID=$_POST["getuserid"];
$_SQL_SU="SELECT * FROM tblsystemuser WHERE userid='$_GETUserID'";

$width_cell=array(10,80,40,50,30);
$pdf->SetFont('Arial','B',18);

//Background color of header//
//Heading of the pdf
// Logo
     $pdf->Image("logo/".$_Logo,$width_cell[0]+$width_cell[1],7,22);
     $pdf->Ln(24);

$p=10;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,$_CompanyName,0,0,'C',true);
$pdf->Ln($p);
//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,"C.D.C",0,0,'C',true);
//$pdf->Ln($p);

$pdf->SetFont('Arial','B',12);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,$_Address." ".$_Location,0,0,'C',true);
$pdf->Ln($p);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'LOCATION: OYOKO ROUNABOUT, KOFORIDUA',0,0,'C',true);
//$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,'Tel:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',14);

  $text_height = 5;
  $text_length = 70;
  $n=9;

      $pdf->Cell($text_length,$text_height,'PAYMENT DETAILS',0,0,'L',true);
      //$pdf->SetTextColor(0);
     $pdf->SetFont('Arial','B',12);

      $pdf->Ln($n);
 	$_Result=mysqli_query($con,$_SQL_SU);

      if($row_ps=mysqli_fetch_array($_Result,MYSQLI_ASSOC)){
      	@$_StaffName=$row_ps['firstname']." ".$row_ps['othernames']." ".$row_ps['surname']." (".$row_ps['userid'].")";
      	    $pdf->Cell($text_length,$text_height,'NAME:'.$_StaffName,0,0,'L',true);
      $pdf->Ln(10);
      }
  

 @$_Class_ID=$_POST['getclass'];
@$_Term_ID=$_POST['gettermid'];

$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce WHERE ce.class_entryid='$_Class_ID' ORDER BY ce.class_name ASC");
while($row_class=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC))
{
@$_Total_Amount=0;
@$_Total_Balance=0;
@$_Total_Amount_Paid=0;

$pdf->SetFont('Arial','B',12);
$pdf->Cell($text_length,$text_height,strtoupper($row_class['class_name']),0,0,'L',true);
$pdf->Ln($n);

$_SQL_TERM=mysqli_query($con,"SELECT * FROM tbltermregistry tr WHERE tr.class_entryid='$_Class_ID' AND tr.termname='$_Term_ID' 
	AND tr.userid='$_GETUserID' ORDER BY tr.termname ASC");


$pdf->SetFillColor(255,255,255);

$pdf->SetFont('Arial','B',12);
//Header starts //
$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);

//First header column //
$pdf->Cell($width_cell[1],10,'ITEM',1,0,'C',true);

//$pdf->Cell($width_cell[2],10,'AMOUNT',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'PAYMENT',1,0,'C',true);

$pdf->Cell($width_cell[3],10,'PAYMENT DATE/TIME',1,0,'C',true);
$pdf->Ln($n);

@$_Total_Amount_Paid=0;
@$_Total_amount_To_Pay=0;

while($row_tr=mysqli_fetch_array($_SQL_TERM,MYSQLI_ASSOC))
{
//$pdf->Cell($text_length,$text_height,"Term: ".$row_tr['termname'],0,0,'L',true);
$pdf->Cell($width_cell[0]+$width_cell[1],10,"Semester: ".$row_tr['termname'],1,0,'L',true);
//$pdf->Ln(10);

$_SQL_BIL=mysqli_query($con,"SELECT SUM(ip.price) AS Total_Amount,transactionid FROM tblbilling bi INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid WHERE bi.userid='$_GETUserID' AND ip.class_entryid='$row_tr[class_entryid]' AND ip.term='$row_tr[termname]'");
if($row_b=mysqli_fetch_array($_SQL_BIL,MYSQLI_ASSOC)){
$pdf->Cell($width_cell[2]+$width_cell[3],10,"AMOUNT TO PAY: $_SESSION[SYMBOL] ".$row_b['Total_Amount'],1,0,'R',true);
$_Total_amount_To_Pay=$row_b['Total_Amount'];
$_TransactionId_Bi=$row_b['transactionid'];
}

      //$pdf->SetTextColor(0);
$pdf->SetFont('Arial','B',12);
$pdf->Ln(10);

$_SQL_EXECUTE_3="SELECT * FROM tblbilling bi 
INNER JOIN tblsystemuser su ON bi.userid=su.userid 
INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
INNER JOIN tblitem itm ON ip.itemid=itm.itemid
INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid
INNER JOIN tblbatch b ON ip.batch=b.batchid
INNER JOIN tblpayment pm ON pm.billid=bi.billid
WHERE bi.userid='$_GETUserID' AND ip.class_entryid='$row_class[class_entryid]' AND ip.term='$row_tr[termname]' ORDER BY pm.datetimepayment DESC";
//$_SQL_EXECUTE_3_r=$_SQL_EXECUTE_3;


@$serial=0;
@$_Total_Amount_single=0;
@$_Total_Amount_Paid_Single=0;
@$_Total_Balance_Single=0;

//while($row_p=mysqli_fetch_array($_SQL_EXECUTE_3,MYSQLI_ASSOC)){


///header ends///
$pdf->SetFont('Arial','',10);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;

@$_AdditionalPrice=0;

@$serial=0;
//each record is one row //
foreach ($dbo->query($_SQL_EXECUTE_3) as $row_p) 
{

$pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill);
//$pdf->Ln(10);
  //$pdf->Cell($width_cell[0],10,"Gross Salary",1,0,'C',$fill);
$pdf->Cell($width_cell[1],10,$row_p['itemname'],1,0,'L',$fill);
//$pdf->Ln(10);

//$_Total_Amount=$_Total_Amount+$row_p['price'];
//$_Total_Amount_single=$_Total_Amount_single+$row_p['price'];
//$pdf->Cell($width_cell[2],10,$row_p['price'],1,0,'C',$fill);
//$pdf->Ln(10);
				
$pdf->Cell($width_cell[2],10,$row_p['payment'],1,0,'C',$fill);
$_Total_Amount_Paid_Single=@$_Total_Amount_Paid_Single + $row_p['payment'];
//$pdf->Ln(10);
$pdf->Cell($width_cell[3],10,$row_p['datetimepayment'],1,0,'C',$fill);

/*$_Balance=$row_p['price']-$row_p['payment'];
$_Total_Balance=$_Total_Balance+$_Balance;
$_Total_Balance_Single=$_Total_Balance_Single+$_Balance;
$pdf->Cell($width_cell[4],10,$_Balance,1,0,'C',$fill);
*/
$fill = !$fill;
$pdf->Ln(10);
}

//Footer of the table
 //$pdf->Cell($width_cell[0],10,'',1,0,'C',true);
// $pdf->Cell($width_cell[0],10,'',1,0,'C',true);
$pdf->Cell($width_cell[0]+$width_cell[1],10,'Sub Total:',1,0,'R',true);
//$pdf->Cell($width_cell[2],10,$_Total_Amount_single,1,0,'C',true);
$pdf->Cell($width_cell[2],10,$_Total_Amount_Paid_Single,1,0,'C',true);
$_Total_Amount_Paid=$_Total_Amount_Paid+$_Total_Amount_Paid_Single;

$pdf->Cell($width_cell[3],10,"",1,0,'C',true);
$pdf->Ln(10); 
}
$pdf->SetFont('Arial','B',10);
$pdf->Cell($width_cell[0]+$width_cell[1],10,'GRAND TOTAL:',1,0,'R',true);
//$pdf->Cell($width_cell[2],10,$_SESSION['SYMBOL']." ".$_Total_Amount,1,0,'C',true);
$pdf->Cell($width_cell[2],10,$_SESSION['SYMBOL']." ".$_Total_Amount_Paid,1,0,'C',true);
$pdf->Cell($width_cell[3],10,"BALANCE: ".$_SESSION['SYMBOL']." ".($_Total_amount_To_Pay-$_Total_Amount_Paid),1,0,'C',true);
$pdf->Ln(15); 
}
/*$pdf->Cell($width_cell[2]+$width_cell[3],10,'Grand Total: Ghc',1,0,'C',true);
$pdf->Cell($width_cell[4],10,$_GrandTotal,1,0,'C',true);
*/
$pdf->SetFont('Arial','',10);

$tomorrow = mktime(0,0,0,date("m"),date("d"),date("Y"));
$tdate= date("d/m/Y", $tomorrow);
$pdf->SetFillColor(0,0,0);
//$pdf->PutLink("http://www.braintechconsult.com","BTC");
//$pdf->Ln(10);                    //$pdf->SetStyle('I',true);       
//$pdf->Cell(0,10,'Print Date/Time: '.$tdate .','.$todayTime,0);
$pdf->Cell(0,10,'Print Date/Time: '.$tdate,0);

//$pdf->Ln(10);                    //$pdf->SetStyle('I',true);       
//$pdf->Cell(0,10,'Paid By: '.$_Receivedby,0);
 $pdf->Ln(10); 

 $_SQL_AC=mysqli_query($con,"SELECT * FROM tbltransaction tr 
  INNER JOIN tblsystemuser su ON tr.recordedby=su.userid 
  WHERE tr.transactionid='$_TransactionId_Bi'");
 if($row_bi=mysqli_fetch_array($_SQL_AC,MYSQLI_ASSOC)){
 $pdf->Cell(0,10,'ADMINISTRATOR:'. strtoupper($row_bi['firstname']." ".$row_bi['othernames']." ".$row_bi['surname']),0);
 
 $pdf->Ln(10); 
 //$pdf->Cell(0,10,'SIGNATURE:'.strtoupper($row_bi['firstname']." ".$row_bi['othernames']." ".$row_bi['surname']),0);
 $pdf->Cell(0,10,'SIGNATURE:..........................................',0);
 
 //$pdf->Ln(5); 
 
//$pdf->Cell(0,3,$pdf->Image('accountants/'.$row_bi['signature']),0,0,'L',false);
 

}
 
 //$pdf->Ln(8); 

//$pdf->SetFont('Arial','B',8);

 /*$pdf->Ln(14); 
 $pdf->Cell(0,10,'Developed by: Brainstorm Technologies Consult',0);
 $pdf->Ln(8); 
 $pdf->Cell(0,10,'Accra,Takoradi,Koforidua - 0342-292-121',0);
/// end of records ///
 $pdf->Ln(50);
*/
//}
$pdf->Output();
 //ob_end_flush(); 
 //}
}
?>


<?php
//Declare the variables
@$_UserID=$_POST['userid'];
@$_TransactionId_Bi="";

@$_Receivedby=$_SESSION['USERID'];
//@$_PaymentDate=$_POST['batch_month']." ".$_POST['batch_year'];
if(isset($_POST["print_bill"]))
{
      include("dbstring.php");
      include("config.php");
      include("company.php");
      //Get all the ordered items

require('fpdf181/fpdf.php');
//ob_start();

$pdf = new FPDF();
$pdf->AddPage();

$_SQL_SU="SELECT * FROM tblsystemuser WHERE userid='$_UserID'";

$width_cell=array(10,80,40,50,30);
$pdf->SetFont('Arial','B',18);
//if(mysqli_num_rows(mysqli_query($con,$_SQL_EXECUTE_SP))>0)
//{	

//Background color of header//
//Heading of the pdf
// Logo
 $pdf->Image("logo/".$_Logo,$width_cell[0]+$width_cell[1],3,22);
  $pdf->Ln(20);

$p=10;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,$_CompanyName,0,0,'C',true);
$pdf->Ln($p);
//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,"C.D.C",0,0,'C',true);
//$pdf->Ln($p);

$pdf->SetFont('Arial','B',12);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,$_Address." ".$_Location,0,0,'C',true);
$pdf->Ln($p);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'LOCATION: OYOKO ROUNABOUT, KOFORIDUA',0,0,'C',true);
//$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,'Tel:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',14);

  $text_height = 5;
  $text_length = 70;
  $n=9;

      $pdf->Cell($text_length,$text_height,'PAYMENT DETAILS',0,0,'L',true);
      //$pdf->SetTextColor(0);
     $pdf->SetFont('Arial','B',12);

      $pdf->Ln($n);
 	$_Result=mysqli_query($con,$_SQL_SU);

      if($row_ps=mysqli_fetch_array($_Result,MYSQLI_ASSOC)){
      	@$_StaffName=$row_ps['firstname']." ".$row_ps['othernames']." ".$row_ps['surname']." (".$row_ps['userid'].")";
      	    $pdf->Cell($text_length,$text_height,'NAME:'.$_StaffName,0,0,'L',true);
      $pdf->Ln(10);
      }
  

@$_Class_ID=$_POST['class_id'];
@$_Term_ID=$_POST['term_id'];
@$_Bill_Term=$_POST['bill_id'];

$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce WHERE ce.class_entryid='$_Class_ID' ORDER BY ce.class_name ASC");
while($row_class=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC))
{
@$_Total_Amount=0;
@$_Total_Balance=0;
@$_Total_Amount_Paid=0;

$pdf->SetFont('Arial','B',12);
$pdf->Cell($text_length,$text_height,strtoupper($row_class['class_name']),0,0,'L',true);
$pdf->Ln($n);

$_SQL_TERM=mysqli_query($con,"SELECT * FROM tbltermregistry tr WHERE tr.class_entryid='$_Class_ID' AND tr.termname='$_Term_ID' 
AND tr.userid='$_UserID' ORDER BY tr.termname ASC");


$pdf->SetFillColor(255,255,255);
$pdf->SetFont('Arial','B',12);
//Header starts //
$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);

//First header column //
$pdf->Cell($width_cell[1],10,'ITEM',1,0,'C',true);

//$pdf->Cell($width_cell[2],10,'AMOUNT',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'PAYMENT',1,0,'C',true);

$pdf->Cell($width_cell[3],10,'PAYMENT DATE/TIME',1,0,'C',true);
$pdf->Ln($n);

@$_Total_Amount_Paid=0;
@$_Total_amount_To_Pay=0;

while($row_tr=mysqli_fetch_array($_SQL_TERM,MYSQLI_ASSOC))
{
//$pdf->Cell($text_length,$text_height,"Term: ".$row_tr['termname'],0,0,'L',true);
$pdf->Cell($width_cell[0]+$width_cell[1],10,"Semester: ".$row_tr['termname'],1,0,'L',true);
//$pdf->Ln(10);

$_SQL_BIL=mysqli_query($con,"SELECT SUM(ip.price) AS Total_Amount,transactionid FROM tblbilling bi INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid WHERE bi.userid='$_UserID' AND ip.class_entryid='$row_tr[class_entryid]' AND ip.term='$row_tr[termname]' AND bi.billid='$_Bill_Term'");
if($row_b=mysqli_fetch_array($_SQL_BIL,MYSQLI_ASSOC)){
$pdf->Cell($width_cell[2]+$width_cell[3],10,"AMOUNT TO PAY: $_SESSION[SYMBOL] ".$row_b['Total_Amount'],1,0,'R',true);
$_Total_amount_To_Pay=$row_b['Total_Amount'];
$_TransactionId_Bi=$row_b['transactionid'];
}

      //$pdf->SetTextColor(0);
$pdf->SetFont('Arial','B',12);
$pdf->Ln(10);

$_SQL_EXECUTE_3="SELECT * FROM tblbilling bi 
INNER JOIN tblsystemuser su ON bi.userid=su.userid 
INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
INNER JOIN tblitem itm ON ip.itemid=itm.itemid
INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid
INNER JOIN tblbatch b ON ip.batch=b.batchid
INNER JOIN tblpayment pm ON pm.billid=bi.billid
WHERE bi.userid='$_UserID' AND ip.class_entryid='$row_class[class_entryid]' AND bi.billid='$_Bill_Term'
AND ip.term='$row_tr[termname]' ORDER BY pm.datetimepayment DESC";
//$_SQL_EXECUTE_3_r=$_SQL_EXECUTE_3;


@$serial=0;
@$_Total_Amount_single=0;
@$_Total_Amount_Paid_Single=0;
@$_Total_Balance_Single=0;

//while($row_p=mysqli_fetch_array($_SQL_EXECUTE_3,MYSQLI_ASSOC)){


///header ends///
$pdf->SetFont('Arial','',10);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;

@$_AdditionalPrice=0;

@$serial=0;
//each record is one row //
foreach ($dbo->query($_SQL_EXECUTE_3) as $row_p) 
{

$pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill);
//$pdf->Ln(10);
  //$pdf->Cell($width_cell[0],10,"Gross Salary",1,0,'C',$fill);
$pdf->Cell($width_cell[1],10,$row_p['itemname'],1,0,'L',$fill);
//$pdf->Ln(10);

//$_Total_Amount=$_Total_Amount+$row_p['price'];
//$_Total_Amount_single=$_Total_Amount_single+$row_p['price'];
//$pdf->Cell($width_cell[2],10,$row_p['price'],1,0,'C',$fill);
//$pdf->Ln(10);
				
$pdf->Cell($width_cell[2],10,$row_p['payment'],1,0,'C',$fill);
$_Total_Amount_Paid_Single=@$_Total_Amount_Paid_Single + $row_p['payment'];
//$pdf->Ln(10);
$pdf->Cell($width_cell[3],10,$row_p['datetimepayment'],1,0,'C',$fill);

/*$_Balance=$row_p['price']-$row_p['payment'];
$_Total_Balance=$_Total_Balance+$_Balance;
$_Total_Balance_Single=$_Total_Balance_Single+$_Balance;
$pdf->Cell($width_cell[4],10,$_Balance,1,0,'C',$fill);
*/
$fill = !$fill;
$pdf->Ln(10);
}

//Footer of the table
 //$pdf->Cell($width_cell[0],10,'',1,0,'C',true);
// $pdf->Cell($width_cell[0],10,'',1,0,'C',true);
$pdf->Cell($width_cell[0]+$width_cell[1],10,'Sub Total:',1,0,'R',true);
//$pdf->Cell($width_cell[2],10,$_Total_Amount_single,1,0,'C',true);
$pdf->Cell($width_cell[2],10,$_Total_Amount_Paid_Single,1,0,'C',true);
$_Total_Amount_Paid=$_Total_Amount_Paid+$_Total_Amount_Paid_Single;

$pdf->Cell($width_cell[3],10,"",1,0,'C',true);
$pdf->Ln(10); 
}
$pdf->SetFont('Arial','B',10);
$pdf->Cell($width_cell[0]+$width_cell[1],10,'GRAND TOTAL:',1,0,'R',true);
//$pdf->Cell($width_cell[2],10,$_SESSION['SYMBOL']." ".$_Total_Amount,1,0,'C',true);
$pdf->Cell($width_cell[2],10,$_SESSION['SYMBOL']." ".$_Total_Amount_Paid,1,0,'C',true);
$pdf->Cell($width_cell[3],10,"BALANCE: ".$_SESSION['SYMBOL']." ".($_Total_amount_To_Pay-$_Total_Amount_Paid),1,0,'C',true);
$pdf->Ln(15); 
}
/*$pdf->Cell($width_cell[2]+$width_cell[3],10,'Grand Total: Ghc',1,0,'C',true);
$pdf->Cell($width_cell[4],10,$_GrandTotal,1,0,'C',true);
*/
$pdf->SetFont('Arial','',8);

$tomorrow = mktime(0,0,0,date("m"),date("d"),date("Y"));
$tdate= date("d/m/Y", $tomorrow);
$pdf->SetFillColor(0,0,0);
//$pdf->PutLink("http://www.braintechconsult.com","BTC");
//$pdf->Ln(10);                    //$pdf->SetStyle('I',true);       
//$pdf->Cell(0,10,'Print Date/Time: '.$tdate .','.$todayTime,0);
$pdf->Cell(0,10,'Print Date/Time: '.$tdate,0);

//$pdf->Ln(10);                    //$pdf->SetStyle('I',true);       
//$pdf->Cell(0,10,'Paid By: '.$_Receivedby,0);
 $pdf->Ln(10); 
$_SQL_AC=mysqli_query($con,"SELECT * FROM tbltransaction tr 
  INNER JOIN tblsystemuser su ON tr.recordedby=su.userid 
  WHERE tr.transactionid='$_TransactionId_Bi'");
 if($row_bi=mysqli_fetch_array($_SQL_AC,MYSQLI_ASSOC)){
 $pdf->Cell(0,10,'ADMINISTRATOR:'. strtoupper($row_bi['firstname']." ".$row_bi['othernames']." ".$row_bi['surname']),0);
 }

 $pdf->Ln(10); 
 $pdf->Cell(0,10,'SIGNATURE:.........................................................................',0);

/* $pdf->Ln(8); 

$pdf->SetFont('Arial','B',8);

 $pdf->Ln(14); 
 $pdf->Cell(0,10,'Developed by: Brainstorm Technologies Consult',0);
 $pdf->Ln(8); 
 $pdf->Cell(0,10,'Accra,Takoradi,Koforidua - 0342-292-121',0);
/// end of records ///
 $pdf->Ln(50);
 */

//}
$pdf->Output();
 //ob_end_flush(); 
 //}
}
?>


<?php
include("dbstring.php");
if(isset($_POST['pay_bill']))
{
	$_Amount_Paid=$_POST['payment'];
	$_User_ID=$_POST['userid'];
	$_ItemPriceId=$_POST['item-price-id'];
	$_Transaction_Id=$_POST['transactioncode'];
	$_Bill_Id=$_POST['bill_id'];

@$_Bill_Payment=0;
if($_Amount_Paid<=0){
$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> No amount has been entered</div>";
}
else{
//Check if balance is zero
$_SQL_CHECK_BALANCE_1=mysqli_query($con,"SELECT sum(payment) AS payment FROM tblbilling bi INNER JOIN tblpayment pm 
ON bi.billid=pm.billid
WHERE bi.userid='$_User_ID' AND bi.itempriceid='$_ItemPriceId'");	
if($row_1=mysqli_fetch_array($_SQL_CHECK_BALANCE_1,MYSQLI_ASSOC)){
$_Bill_Payment=$row_1['payment']+$_Amount_Paid;
//$_Bill_Id=$row_1['billid'];
//echo "Bill P".$_Bill_Payment ."<br/>";
}
$_SQL_CHECK_BALANCE_2=mysqli_query($con,"SELECT * FROM tblitemprice 
WHERE itempriceid='$_ItemPriceId'");	
if($row_2=mysqli_fetch_array($_SQL_CHECK_BALANCE_2,MYSQLI_ASSOC)){
@$_Actual_Payment=$row_2['price'];
@$_ItemId=$row_2['itemid'];
//echo "Actual".$_Actual_Payment;
}
if($_Bill_Payment>$_Actual_Payment){
$_SQL_Item=mysqli_query($con,"SELECT * FROM tblitem WHERE itemid='$_ItemId'");
if($row_item=mysqli_fetch_array($_SQL_Item,MYSQLI_ASSOC)){
@$_ItemName=$row_item['itemname'];
}
$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> Student has finished paying for the $_ItemName or Amount entered is more than the balance</div>";
}
else
{
$_SQL_Item_2=mysqli_query($con,"SELECT * FROM tblitem WHERE itemid='$_ItemId'");
if($row_item_2=mysqli_fetch_array($_SQL_Item_2,MYSQLI_ASSOC)){
@$_ItemName_2=$row_item_2['itemname'];
}
include("code.php");
@$_Payment_Id=$code;

$_SQL_Bill_Pay=mysqli_query($con,"INSERT INTO tblpayment(paymentid,userid,billid,transactionid,payment,datetimepayment,recordedby,status,referenceid)
		VALUES('$_Payment_Id','$_User_ID','$_Bill_Id','$_Transaction_Id','$_Amount_Paid',NOW(),'$_SESSION[USERID]','active','$_SESSION[PAYMENTACCOUNT]')");
	if($_SQL_Bill_Pay){

$_SQL_B=mysqli_query($con,"INSERT INTO accountingbookentries(accountId,cr,created,createdBy,dr,modifiedBy,narration,particulars,refAccountId,transactionId)
VALUES('$_SESSION[PAYMENTACCOUNT]',0,NOW(),'$_SESSION[USERID]','$_Amount_Paid','','Payment','Payment','$_User_ID','$_Transaction_Id')");
if($_SQL_B){}


	//$_SQL_Payment=mysqli_query($con,"UPDATE tblpayment SET payment=payment+'$_Amount_Paid' 
	//	WHERE userid='$_User_ID' AND transactionid='$_Transaction_Id'");	
	//if($_SQL_Payment){

   //SEND SMS ALERT: SHORT MESSAGE OF THE CUSTOMER'S MESSAGE
                    $_SQLCl=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE userid='$_User_ID'");
                    @$_StudentName="";
                    @$SMSIsEnable=0;
                
                    if($rowcl=mysqli_fetch_array($_SQLCl,MYSQLI_ASSOC)){
                      $_StudentName=$rowcl['firstname']." ".$rowcl['othernames']." ".$rowcl['surname']."(".$rowcl['userid'].")";
                      //$_ClientName=substr($_, start)
                      $Receiver_Mobile=$rowcl['nextofkin_contact'];
                      $SMSIsEnable=$rowcl['smsalert'];
                    }
                    //GET TOTAL SALES FOR TODAY
                    @$_TotalDailySales=0;
                    $_SQLTOTAL=mysqli_query($con,"SELECT SUM(op.payment) AS DailyTotalSales FROM tblpayment op WHERE date_format(op.datetimepayment,'%d-%m-%Y')=date_format(NOW(),'%d-%m-%Y')");
                    if($rowdts=mysqli_fetch_array($_SQLTOTAL,MYSQLI_ASSOC)){
                      $_TotalDailySales=$rowdts["DailyTotalSales"];
                    } 
                    else{
                     $_Error=mysqli_error($con);
                    echo "<div style='color:red'>Failed to get Total Sales</div>";
                    }
                    $_today=date("d")."-".date("m")."-".date("Y")." ".date("H:i:s A",time());
                    //SMS to manager
                    //$ShortTransactionMsg="$_StudentName paid GHC$_Amount_Paid at $_today.\nCurrent Sales is GHC$_TotalDailySales\n Received by $_SESSION[USERID]";
                    
                    //SMS to student
                    $ShortTransactionMsg="$_StudentName paid GHC$_Amount_Paid at $_today";
                    if($SMSIsEnable==1){
                   
                      $phone=$Receiver_Mobile;
                      $message=$ShortTransactionMsg;
                      include("bulksms/bulksms.php");
                    }

	         $_SESSION['Message']="<div style='color:green;text-align:left;background-color:white;padding:8px;'><i class='fa fa-check' style='color:green'></i> Amount of $_Amount_Paid $_SESSION[CURRENCY] Successfully Paid for $_ItemName_2</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>Failed to pay,$_Error</div>";
	
	}
	}
}
}
?>
<?php
include("dbstring.php");
//@$_ClassId=$_POST['classid'];
@$_UserId=$_POST['userid'];
@$_Class=$_POST['class'];
@$_Batch=$_POST['batch'];
@$_Term=$_POST['term'];
@$_Recordedby=$_SESSION['USERID'];
//echo $_SESSION['USERID'];

if(isset($_POST['bill_student'])){
//Create payment container
include("code.php");
@$_Payment_Id=$code;
@$_Transaction_Code=$transaction_id;

$_SQL_Payment=mysqli_query($con,"INSERT INTO tblpayment(paymentid,userid,transactionid,payment,datetimepayment,recordedby,status,paymentdatetime)
	VALUES('$_Payment_Id','$_UserId','$_Transaction_Code',0,NOW(),'$_SESSION[USERID]','active',NOW())");
if($_SQL_Payment){
@$_TransId=$_Transaction_Code;
}

$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tbltermregistry tr 
				INNER JOIN tblitemprice ip ON tr.class_entryid=ip.class_entryid AND tr.termname=ip.term
				INNER JOIN tblitem itm ON ip.itemid=itm.itemid
				INNER JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
				WHERE tr.userid='$_UserId' AND ip.class_entryid='$_Class' AND ip.term='$_Term' AND ip.batch='$_Batch'");

while($row_b=mysqli_fetch_array($_SQL_EXECUTE_2,MYSQLI_ASSOC))
{
include("code.php");
@$_BillId=$code;

$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblbilling(billid,userid,itempriceid,transactionid,datetimebilled,recordedby,status)
	VALUES('$_BillId','$_UserId','$row_b[itempriceid]','$_TransId',NOW(),'$_Recordedby','active')");
if($_SQL_EXECUTE){
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:center;background-color:white'>$_BillId Successfully Created</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>$_BillId failed to create,$_Error</div>";
	}
}
}
?>

<html>
<head>
<?php
include("links.php");
?>

<script type="text/javascript">
function SearchItem(str)
{
  if(str=="")
  {
  document.getElementById("search-result").innerHTML="";
  return;
  }
  else
  {
    if(window.XMLHttpRequest)
    {
      xmlhttp = new XMLHttpRequest();
    }
    else
    {
      xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange = function()
    {
      if(this.readyState==4 && this.status==200)
      {
        document.getElementById("search-result").innerHTML = this.responseText;
      }
    };
    xmlhttp.open("GET","display-student.php?search-item="+str,true);
    xmlhttp.send();
  }
}
</script>


<script>
  var rnd;
function getItemID()
{
rnd=Math.floor( Math.random()*100000000);
document.getElementById("item-id").value=rnd;
}
</script>

<script type="text/javascript">
var gbatch;
function getBatch()
{
gbatch=getElementById("batch").value;
 //return _batch;  
}
function getStudentBill(str)
{
	if(str=="")
  {
  
  document.getElementById("search-result").innerHTML="";
  return;
  }
  else
  {
    if(window.XMLHttpRequest)
    {
      xmlhttp = new XMLHttpRequest();
    }
    else
    {
      xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange = function()
    {
      if(this.readyState==4 && this.status==200)
      {
        document.getElementById("search-result").innerHTML = this.responseText;
      }
    };
    xmlhttp.open("GET","display-class-bill.php?search-bill="+str+"&batch="+gbatch,true);
    xmlhttp.send();
  }
}
</script>
</head>
<body>
	<div class="header">
		<!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
	<?php
	include("menu.php");
	?>		

	</div><br/>
<div class="form-entry" style="background-color:transparent">
	<br/><br/>
	<table width="100%" style="background-color:white">
		<tr>

			<td width="100%">
				<?php
				echo "<div style='padding:8px;color:green'>$_SESSION[Message]</div>";
				?>
				<?php
				//echo "<form id='form' method='post' action='payments.php'>"; 
				//echo "<div class='form-entry'>";
			//	echo "<input type='text' id='search-item' name='search-item' placeholder='Search Student by Index Number or First Name or Surname or Othernames' onkeyup='SearchItem(this.value)'/>";
				//echo " </div><br/>";   
				//echo "</form>";
				//echo "<form id='formID' method='post' action='pointofsale.php'>"; 
			//	echo "<div id='search-result' name='search-result'></div>";
				//echo "</form>";
				?>

	<?php

if(isset($_GET['userid']))
{
	@$_Get_UserID="";
	@$_Get_Class="";
	@$_Get_TermId="";

	@$_FullName="";
	$_SQL_SU=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE userid='$_GET[userid]'");
		if($row_su=mysqli_fetch_array($_SQL_SU,MYSQLI_ASSOC)){
		$_FullName=$row_su['firstname']." ".$row_su['othernames']." ".$row_su['surname']."(".$row_su['userid'].")";
			}

include("dbstring.php");
echo "<table style='background-color:white'>";
echo "<caption>Hi, $_FullName do you want to make payment?</caption>";
echo "<thead><th>*</th><th>ITEM</th><th>AMOUNT</th><th>PAID</th><th>BALANCE</th><th>PAYMENT</th><th colspan='2'>ACTION</th></thead>";
echo "<tbody>";

$_SQL_CR=mysqli_query($con,"SELECT * FROM tblclass WHERE userid='$_GET[userid]'");
while($row_cr=mysqli_fetch_array($_SQL_CR,MYSQLI_ASSOC))
{
//Get all the classes regisetred for the studenet
$_Class_Reg_ID=$row_cr['class_entryid'];
$_Batch_Id=$row_cr['batchid'];
				
$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce WHERE ce.class_entryid='$_Class_Reg_ID' ORDER BY ce.class_name ASC");
while($row_class=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC))
{

echo "<tr>";
echo "<td colspan='12' align='left' style='font-weight:bold'>";
echo strtoupper($row_class['class_name']);
echo "</td>";
echo "</tr>";

$_SQL_TERM=mysqli_query($con,"SELECT * FROM tbltermregistry tr WHERE tr.class_entryid='$row_class[class_entryid]' AND tr.batchid='$_Batch_Id' AND tr.userid='$_GET[userid]' ORDER BY tr.termname ASC");
while($row_tr=mysqli_fetch_array($_SQL_TERM,MYSQLI_ASSOC))
{
echo "<tr>";
echo "<td colspan='12' align='left' style='background-color:#ede;font-weight:bold'>";
echo "SEMESTER: ". $row_tr['termname'];
//echo "<input type='hidden' id='class_id' name='class_id' value='$row_class[class_entryid]' />";
//echo "<input type='hidden' id='term_id' name='term_id' value='$row_tr[termname]' />";
echo "</td>";
echo "</tr>";

				$_SQL_EXECUTE_3=mysqli_query($con,"SELECT * FROM tblbilling bi INNER JOIN tblsystemuser su 
					ON bi.userid=su.userid INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
					INNER JOIN tblitem itm ON ip.itemid=itm.itemid
					INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid
					INNER JOIN tblbatch b ON ip.batch=b.batchid
					
				 WHERE bi.userid='$_GET[userid]' AND ip.class_entryid='$row_tr[class_entryid]' AND ip.term='$row_tr[termname]' ORDER BY ip.term ASC");
				//$_SQL_EXECUTE_3_r=$_SQL_EXECUTE_3;


	/*	if(!mysqli_num_rows($_SQL_EXECUTE_3_r)>0){
			@$_FullName="";
			$_SQL_SU=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE userid='$_GET[userid]'");
			if($row_su=mysqli_fetch_array($_SQL_SU,MYSQLI_ASSOC)){
			$_FullName=$row_su['firstname']." ".$row_su['othernames']." ".$row_su['surname']."(".$row_su['userid'].")";
			}
		echo "<div style='color:red;text-align:center'>There is no bill available for $_FullName</div>";
		}
		else{
			*/
				@$serial=0;

				/*if($row_p_r=mysqli_fetch_array($_SQL_EXECUTE_3_r,MYSQLI_ASSOC)){
				//echo "<tr>";
				//echo "<td colspan='12' style='background-color:#eed;border-bottom:1px solid orange;color:blue;font-weight:bold'>";
				//echo $row_p_r['firstname']." ".$row_p_r['othernames']." ".$row_p_r['surname']." (". $row_p_r['userid'].")";
				//echo "</td>";
				//echo "</tr>";
				}
				*/
				@$_Total_Amount_Paid=0;
				@$_Total_Balance=0;
				@$_Balance=0;
				@$_Total_Amount=0;
				
				while($row_p=mysqli_fetch_array($_SQL_EXECUTE_3,MYSQLI_ASSOC)){
				echo "<form id='formID' name='formID' method='post'>";
				
				echo "<tr>";
				$_Get_UserID=$row_p['userid'];
				$_Get_Class=$row_class['class_entryid'];
				$_Get_TermId=$row_tr['termname'];

				echo "<input type='hidden' id='userid' name='userid' value='$row_p[userid]' />";
				
				echo "<td align='center'>";
				echo $serial=$serial+1;
				echo "<input type='hidden' id='class_id' name='class_id' value='$row_class[class_entryid]' />";
				echo "<input type='hidden' id='term_id' name='term_id' value='$row_tr[termname]' />";
				echo "</td>";

				echo "<td>";
				echo "<input type='hidden' id='item-price-id' name='item-price-id' value='$row_p[itempriceid]' />";
				echo "<input type='hidden' id='transactioncode' name='transactioncode' value='$row_p[transactionid]' />";
				echo "<input type='hidden' id='bill_id' name='bill_id' value='$row_p[billid]' />";
				
				echo $row_p['itemname'];
				echo "</td>";

				echo "<td align='center'>";
				echo $row_p['cost'];
				$_Total_Amount=$_Total_Amount+$row_p['cost'];
				echo "</td>";

				echo "<td align='center'>";
				$_SQL_SUBPAYMENT=mysqli_query($con,"SELECT sum(pm.payment) AS payment FROM tblpayment pm WHERE pm.transactionid='$row_p[transactionid]' AND pm.billid='$row_p[billid]'");
				if($row_subpy=mysqli_fetch_array($_SQL_SUBPAYMENT)){
				echo $row_subpy['payment'];
				@$_Total_Payment=$row_subpy['payment'];
				$_Total_Amount_Paid=$_Total_Amount_Paid+$row_subpy['payment'];
				}
				 echo "</td>";

				echo "<td align='center'>";
				//echo $row_p['payment'];
				$_Balance=$row_p['cost']-$_Total_Payment;
				$_Total_Balance=$_Total_Balance+$_Balance;
				echo $_Balance;
				echo "</td>";

				echo "<td align='center'>";
				echo "<input type='text' style='text-align:center' id='payment' name='payment' class='validate[required,custom[number]]' placeholder='Enter Amount'/>";
				echo "</td>";

				echo "<td align='center'>";
				echo "<button class='button-pay'  id='pay_bill' name='pay_bill'><i class='fa fa-plus' style='color:white'></i> Pay</button>";
				echo "</td>";

				echo "<td align='center'>";
				echo "<button class='button-pay'  id='print_bill' name='print_bill'><i class='fa fa-print' style='color:white'></i> Print</button>";
				echo "</td>";

				echo "</tr>";
				echo "</form>";
				}
				echo "<tr style='background-color:#eeb;color:blue;font-weight:bold'>";
				echo "<td colspan='2' align='right'>";
				echo "TOTAL:";
				echo "</td>";
				echo "<td colspan='1' align='center'>";
				echo $_SESSION['SYMBOL']." ". $_Total_Amount;
				echo "</td>";

				echo "<td colspan='1' align='center'>";
				echo $_SESSION['SYMBOL']." ". $_Total_Amount_Paid;
				echo "</td>";

				echo "<td colspan='1' align='center'>";
				echo $_SESSION['SYMBOL']." ". $_Total_Balance;
				echo "</td>";

				echo "<td colspan='2'>";
				echo "</td>";

				echo "<td align='center'>";
				echo "<form id='formID2' name='formID2' method='post'>";
				echo "<input type='hidden' id='getuserid' name='getuserid' value='$_Get_UserID' />";
				echo "<input type='hidden' id='getclass' name='getclass' value='$_Get_Class' />";
				echo "<input type='hidden' id='gettermid' name='gettermid' value='$_Get_TermId' />";
				
				echo "<button id='print_all_bill' name='print_all_bill' class='button-pay'><i class='fa fa-print' style='color:yellow'></i> Print All</button>";
				echo "</form>";
				echo "</td>";
				
				echo "</tr>";
			}
		}
	}
echo "</tbody>";
echo "</table>";
}
?>
</div>
			</td>
		</tr>
	</table>

 
<button onclick="topFunction()" id="myBtn" title="Go to top">Top</button> 

 <script>
//Get the button
var mybutton = document.getElementById("myBtn");

// When the user scrolls down 20px from the top of the document, show the button
window.onscroll = function() {scrollFunction()};

function scrollFunction() {
  if (document.body.scrollTop > 50 || document.documentElement.scrollTop > 50) {
    mybutton.style.display = "block";
  } else {
    mybutton.style.display = "none";
  }
}

// When the user clicks on the button, scroll to the top of the document
function topFunction() {
  document.body.scrollTop = 0;
  document.documentElement.scrollTop = 0;
}
</script>
<br/>
</div>
<br/><br/>
</body>
</html>