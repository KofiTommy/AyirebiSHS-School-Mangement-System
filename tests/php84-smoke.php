<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
error_reporting(E_ALL);
set_error_handler(function($severity,$message,$file,$line){ throw new ErrorException($message,0,$severity,$file,$line); });
require __DIR__.'/../fpdf181/fpdf.php';
require __DIR__.'/../third_party/fpdi/src/autoload.php';
require __DIR__.'/../vendor/autoload.php';
$pdf = new FPDF();
$pdf->SetTitle("R\xe9sults");
$pdf->SetAuthor('School'); $pdf->SetSubject('Compatibility');
$pdf->SetKeywords('report'); $pdf->SetCreator('School portal');
$pdf->AddPage(); $pdf->SetFont('Arial','',12); $pdf->Cell(80,10,'Synthetic compatibility report');
$bytes=$pdf->Output('S');
if (substr($bytes,0,5)!=='%PDF-') throw new RuntimeException('PDF generation failed');
$parser=new \Smalot\PdfParser\Parser();
$parsed=$parser->parseContent($bytes);
if (strpos($parsed->getText(),'Synthetic compatibility report')===false) throw new RuntimeException('PDF text extraction failed');
if (($parsed->getDetails()['Title']??'')!=="R\xc3\xa9sults") throw new RuntimeException('PDF metadata encoding changed');
$import=new \setasign\Fpdi\Fpdi();
$reader=\setasign\Fpdi\PdfParser\StreamReader::createByString($bytes);
if ($import->setSourceFile($reader)!==1) throw new RuntimeException('PDF import failed');
$page=$import->importPage(1); $import->AddPage(); $import->useTemplate($page);
if (substr($import->Output('S'),0,5)!=='%PDF-') throw new RuntimeException('Imported PDF output failed');
$csv=fopen('php://temp','w+');
$fields=array('00123','Comma, name','Quote "name"',"Line\nbreak",'C:\\school\\report');
fputcsv($csv,$fields,',','"','\\'); rewind($csv);
if(fgetcsv($csv,0,',','"','\\')!==$fields) throw new RuntimeException('CSV roundtrip failed');
fclose($csv);
require __DIR__.'/../sms/Hubtel/AbstractHttpClient.php';
foreach(array('HttpGet','HttpHead','HttpPost','HttpPut','HttpDelete') as $class) {
    $curl=curl_init();
    // Configure requests only: no curl_exec(), network or SMS operation.
    $request=new $class($curl,'/fixture','application/json',null);
    if($request->getPath()!=='/fixture') throw new RuntimeException('HTTP request construction failed');
    curl_close($curl);
}
echo 'PASS on PHP '.PHP_VERSION.': PDF generation, Latin-1 metadata, PDF extraction/import and CSV roundtrip with all errors enabled.'.PHP_EOL;
echo 'PASS: Hubtel request classes accept null parameters without deprecations; no network calls.'.PHP_EOL;
