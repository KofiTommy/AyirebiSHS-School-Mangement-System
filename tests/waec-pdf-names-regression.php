<?php
if(PHP_SAPI !== 'cli'){ http_response_code(403); exit; }
require __DIR__.'/waec-analysis-regression.php';
$fixture="0020703043\nAGBO\nFRANCIS Male 15/06/2000\nSOCIAL STUDIES - X ,\nENGLISH LANG - X\n0020703044\nEXAMPLE\nGIVEN MIDDLE Female 23/11/2006\nSOCIAL STUDIES - A1 ,\nENGLISH LANG - W\n0020703045 SAME LINE Male 15/06/2000\nSOCIAL STUDIES - B2\n0020703046\nFULL NAME\nMale 15/06/2000\nSOCIAL STUDIES - C6";
$parsed=waec_extract_listing_rows_from_text($fixture);
$map=array(); foreach(array_merge($parsed['rows'],$parsed['withheld_rows']) as $row) $map[$row['candidate_no']]=$row['student_name'];
expect_same('AGBO FRANCIS',$map['0020703043'],'Given name sharing gender/DOB line preserved');
expect_same('EXAMPLE GIVEN MIDDLE',$map['0020703044'],'Multiple wrapped given names preserved');
expect_same('SAME LINE',$map['0020703045'],'Name sharing candidate/gender line preserved');
expect_same('FULL NAME',$map['0020703046'],'Standalone gender/DOB not included in name');
expect_same(3,count($parsed['rows']),'Graded outcomes unchanged');
expect_same(3,count($parsed['withheld_rows']),'Status outcomes unchanged');
expect_same('Male',$parsed['withheld_rows'][0]['gender'],'Gender remains available');
echo "7 PDF name regression checks passed.\n";
$hyphens=waec_extract_listing_rows_from_text("Date : 29-10-2025 08:24:33\n0020703032\nADU-GYAMFI\nAUGUSTUS Male 15/11/2006\nSOCIAL STUDIES - A1\n0020703332\nLARYEA-\nSOWAH\nSANDRA\nAYORKOR\nFemale23/11/2006\nSOCIAL STUDIES - B2");
expect_same('ADU-GYAMFI AUGUSTUS',$hyphens['rows'][0]['student_name'],'Hyphenated surname is not a result and date header is not a name');
expect_same('LARYEA-SOWAH SANDRA AYORKOR',$hyphens['rows'][1]['student_name'],'Wrapped hyphen joined and adjacent gender/DOB excluded');
expect_same(true,waec_is_result_fragment_line('MATHEMATICS(CORE)-'),'Compact wrapped subject still a result');
echo "3 hyphenated-name regression checks passed.\n";
$header=waec_extract_listing_rows_from_text("EXAMPLE SCHOOL\nSchool # : 0020703\nDate : 29-10-2025 08:24:33\nINDEX NUMBER NAME GENDER DOB RESULTS\n0020703001\nEXAMPLE NAME Female23/11/2006\nSOCIAL STUDIES - B2");
expect_same('EXAMPLE NAME',$header['rows'][0]['student_name'],'Header text cannot leak into first name');
echo "1 PDF header regression check passed.\n";
