<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/waec-analysis-regression.php';
$fixture="0020703001\nEXAMPLE STUDENT Female 01/01/2007\nSOCIAL STUDIES - A1 ,\nMATHEMATICS\n(ELECTIVE) - C6 ,\nGENERAL KNOWLEDGE IN\nART - B3 ,\nCHRISTIAN RELIGIOUS\nSTUDIES - P ,\nMATHEMATICS (CORE)\n- * ,\nFINANCIAL\nACCOUNTING - B2 ,\nPRINCIPLE OF COST\nACCOUNTING - C5 ,\nLITERATURE-IN-ENGLISH\n- E8";
$r=waec_extract_listing_rows_from_text($fixture);
$map=array_column(array_merge($r['rows'],$r['withheld_rows']),'grade','subject_name');
expect_same(8,count($map),'All wrapped subjects retained');
foreach(array('MATHEMATICS(ELECT)'=>'C6','GENERAL KNOWLEDGE IN ART'=>'B3','CHRISTIAN RELIGIOUS STUDIES'=>'P','MATHEMATICS(CORE)'=>'*','FINANCIAL ACCOUNTING'=>'B2','PRINCIPLES OF COST ACCOUNTING'=>'C5','LITERATURE IN ENGLISH'=>'E8') as $s=>$g) expect_same($g,$map[$s],'Exact wrapped outcome '.$s);
$pageBreak="MATHEMATICS\n10/15/26, 8:45 AM WAEC Results Listing\nhttps://resultslisting.waecgh.org/search 2/162\nINDEX NUMBER NAME GENDER DOB RESULTS\n(ELECTIVE) - E8";
expect_same('MATHEMATICS(ELECT) - E8',waec_join_pdf_subject_lines($pageBreak),'Subject spanning page headers recovered');
expect_same('ART - B3',waec_join_pdf_subject_lines('ART - B3'),'Never blindly relabel standalone ART');
expect_same('ACCOUNTING - B3',waec_join_pdf_subject_lines('ACCOUNTING - B3'),'Never blindly relabel standalone ACCOUNTING');
expect_same(false,strpos(waec_join_pdf_subject_lines("MATHEMATICS\n0020703002\n(ELECTIVE) - A1"),'MATHEMATICS(ELECT)')!==false,'No joining across candidate boundary');

// Independent source ledger: split complete candidate blocks on commas, instead
// of using the production line classifier or subject/grade regex scanner.
function waec_pdf_source_ledger($raw){
    $clean=implode("\n",array_filter(preg_split('/\r\n|\r|\n/',$raw),function($line){return !waec_is_noise_line($line);}));
    $clean=preg_split('/(?:^|\n)\s*8\s+PASSES\s*:/i',$clean)[0];
    $parts=preg_split('/\b(\d{10})\b/',$clean,-1,PREG_SPLIT_DELIM_CAPTURE);
    $aliases=array();foreach(waec_pdf_subject_alias_map() as $canonical=>$names){foreach(array_merge(array($canonical),$names) as $name)$aliases[preg_replace('/[^A-Z0-9]/','',strtoupper($name))]=$canonical;}
    $ledger=array();
    for($i=1;$i<count($parts);$i+=2){
        $body=$parts[$i+1];$start=stripos($body,'SOCIAL');
        if($start===false)throw new RuntimeException('Candidate source block has no start subject.');
        $items=explode(',',substr($body,$start)); $n=0;
        foreach($items as $item){
            $item=trim(preg_replace('/\s+/',' ',$item));
            if($item==='')continue;
            if(!preg_match('/^(.+)\s*-\s*(A1|B2|B3|C4|C5|C6|D7|E8|F9|X|W|P|\*)$/',$item,$m))throw new RuntimeException('Incomplete source outcome; repair stopped.');
            $subject=$aliases[preg_replace('/[^A-Z0-9]/','',strtoupper($m[1]))]??'';
            if($subject==='')throw new RuntimeException('Unknown source subject; repair stopped.');
            $key=$parts[$i].'|'.$subject;if(isset($ledger[$key]))throw new RuntimeException('Duplicate source outcome.');
            $ledger[$key]=$m[2];$n++;
        }
        if($n!==8)throw new RuntimeException('Expected eight explicit outcomes per candidate in this listing.');
    }
    ksort($ledger);return $ledger;
}
$options=getopt('',array('pdf:','upload:','source:','apply'));
if(!empty($options['pdf'])){
    $error='';$raw=waec_extract_text_from_pdf($options['pdf'],$error);if(!$raw)throw new RuntimeException('Cannot extract supplied PDF.');
    $parsed=waec_extract_listing_rows_from_text($raw);$actual=array();$counts=array();$people=array();
    foreach(array_merge($parsed['rows'],$parsed['withheld_rows']) as $row){
        $key=$row['candidate_no'].'|'.$row['subject_name'];if(isset($actual[$key]))throw new RuntimeException('Duplicate parsed outcome.');
        $actual[$key]=$row['grade'];$counts[$row['subject_name']]=($counts[$row['subject_name']]??0)+1;$people[$row['candidate_no']]=true;
    }
    ksort($actual);expect_same(waec_pdf_source_ledger($raw),$actual,'Every candidate/subject/outcome agrees with independent source ledger');
    echo json_encode(array('candidates'=>count($people),'outcomes'=>count($actual),'subjects'=>$counts),JSON_PRETTY_PRINT).PHP_EOL;
}
echo "Wrapped-subject regression checks passed.\n";
