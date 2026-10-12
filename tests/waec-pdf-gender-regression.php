<?php
if(PHP_SAPI !== 'cli'){ http_response_code(403); exit; }
require __DIR__.'/waec-analysis-regression.php';
$start=$checks;
foreach(array(
    'Female23/11/2006'=>'Female', 'EXAMPLEFemale23/11/2006'=>'Female',
    'EXAMPLEMale 15/06/2000'=>'Male', 'Female 23/11/2006'=>'Female',
    'Male15/06/2000'=>'Male', 'EXAMPLEfemale01/01/06'=>'Female',
    'Female'=>'Female', 'Male'=>'Male', 'M'=>'Male', 'F'=>'Female',
    'MALEVOLENT'=>'', 'FEMALEEXAMPLE'=>'', 'EXAMPLEMale'=>'', 'EXAMPLEFemale'=>'',
    'EXAMPLEFemale123/11/2006'=>'', 'EXAMPLEFemale23/11/200678'=>'',
    'EXAMPLE NAME'=>'', ''=>''
) as $input=>$expected) expect_same($expected,waec_extract_gender_hint($input),'Explicit gender extraction: '.$input);
$fixture="0020703001\nEXAMPLEFemale23/11/2006\nSOCIAL STUDIES - A1 ,\nENGLISH LANG - W\n0020703002\nEXAMPLEMale 15/06/2000\nSOCIAL STUDIES - X ,\nENGLISH LANG - B2\n0020703003\nEXAMPLE NAME\nSOCIAL STUDIES - C6";
$parsed=waec_extract_listing_rows_from_text($fixture);
$gender=array(); foreach(array_merge($parsed['rows'],$parsed['withheld_rows']) as $row) $gender[$row['candidate_no']]=$row['gender'];
expect_same('Female',$gender['0020703001'],'Female attached to name and date');
expect_same('Male',$gender['0020703002'],'Male attached to name');
expect_same('',$gender['0020703003'],'Missing gender not inherited from previous candidate');
expect_same(3,count($parsed['rows']),'Graded rows preserved');
expect_same(2,count($parsed['withheld_rows']),'Statuses preserved');
echo ($checks-$start)." PDF gender regression checks passed.\n";
