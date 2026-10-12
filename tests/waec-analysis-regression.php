<?php
if(PHP_SAPI !== 'cli'){
    http_response_code(403);
    exit;
}
// Run with PHP CLI. Load only pure helpers; no session, connection or upload writes.
require_once __DIR__.'/../waec-listing-utils.php';
require_once __DIR__.'/../waec-subject-utils.php';
$source = file_get_contents(__DIR__.'/../waec-analysis.php');
$tokens = token_get_all($source);
$wanted = array('waec_key','waec_grade','waec_grade_or_withheld','waec_normalize_gender',
    'waec_points','waec_normalize_subject','waec_identity_key','waec_candidate_col',
    'waec_name_col','waec_gender_col','waec_subject_col','waec_grade_col',
    'waec_build_header_map','waec_extract_rows','waec_deduplicate_records','waec_csv_cell',
    'waec_build_filter_where','waec_filtered_student_count','waec_is_index_number',
    'waec_extract_listing_rows_from_rows','waec_extract_listing_rows_from_xlsx','waec_read_csv_rows',
    'waec_extract_gender_hint','waec_command_exists','waec_extract_text_from_pdf',
    'waec_is_result_fragment_line','waec_is_noise_line','waec_clean_name_fragment','waec_is_name_fragment',
    'waec_resolve_subject_name','waec_pdf_subject_alias_map','waec_extract_listing_rows_from_text',
    'waec_extract_listing_rows_from_pdf','waec_parse_subject_grades_from_text');
for($i=0; $i<count($tokens); $i++){
    if(!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
    $nameIndex = $i + 1;
    while(is_array($tokens[$nameIndex]) && $tokens[$nameIndex][0] === T_WHITESPACE) $nameIndex++;
    if(!is_array($tokens[$nameIndex]) || !in_array($tokens[$nameIndex][1], $wanted, true)) continue;
    $code = ''; $depth = 0; $started = false;
    for(; $i<count($tokens); $i++){
        $token = $tokens[$i];
        $code .= is_array($token) ? ($token[0] === T_DIR ? var_export(dirname(__DIR__), true) : $token[1]) : $token;
        if($token === '{'){ $depth++; $started = true; }
        if($token === '}') $depth--;
        if($started && $depth === 0) break;
    }
    eval($code);
}
$checks = 0;
function expect_same($expected, $actual, $label){
    global $checks;
    $checks++;
    if($expected !== $actual){
        fwrite(STDERR, "FAIL: ".$label."\n");
        exit(1);
    }
}
expect_same('ENGLISH LANG', waec_normalize_subject('English Language'), 'English alias');
expect_same('MATHEMATICS(CORE)', waec_normalize_subject('Core Mathematics'), 'Core maths alias');
expect_same('MATHEMATICS(ELECT)', waec_normalize_subject('Mathematics (Elective)'), 'Elective maths stays distinct');
expect_same('B3', waec_grade(' b 3 '), 'Spaced grade');
expect_same('', waec_grade('W'), 'Withheld has no numerical grade');
expect_same(7, waec_points('D7'), 'D7 keeps seven points');
expect_same("'=SUM(A1:A2)", waec_csv_cell('=SUM(A1:A2)'), 'Spreadsheet formula guarded');
expect_same('0012345678', waec_csv_cell('0012345678'), 'Index text retained');
$long = waec_extract_rows(array(
    array('Candidate No','Candidate Name','Gender','Subject','Grade'),
    array('001','Example A','F','English Language','A1'),
    array('001','Example A','F','Core Mathematics','D7'),
    array('002','Example B','M','English Language','W'),
    array('003','Example C','M','Core Mathematics','X'),
    array('','','','English Language','A1')
));
expect_same(2, count($long['rows']), 'Long format excludes unidentified row');
expect_same(2, count($long['withheld_rows']), 'Long format retains absent and withheld');
expect_same('Example A', $long['rows'][0]['student_name'], 'Candidate Name header');
expect_same('ENGLISH LANG', $long['rows'][0]['subject_name'], 'Imported English canonical name');
$wide = waec_extract_rows(array(
    array('Index Number','Name','Sex','English Language','Core Mathematics','Social Studies'),
    array('001','Example A','Female','A1','D7','W'),
    array('002','Example B','Male','X','','F9')
));
expect_same(3, count($wide['rows']), 'Wide format graded rows');
expect_same(2, count($wide['withheld_rows']), 'Wide format statuses');
expect_same(7, $wide['rows'][1]['grade_point'], 'D7 not discarded');
$duplicates = $long['rows'];
$duplicate = $long['rows'][0]; $duplicate['subject_name'] = 'English'; $duplicates[] = $duplicate;
$dedup = waec_deduplicate_records($duplicates, $long['withheld_rows']);
expect_same(2, count($dedup['rows']), 'Subject aliases do not double count');
expect_same(2, count($dedup['withheld_rows']), 'Separate candidates retain status');
$wrapped = array(
    array('INDEX NUMBER','NAME','GENDER','DOB','RESULTS'),
    array('','','','','SOCIAL STUDIES - A1 ,'),
    array('','Example','','','ENGLISH LANG - B2 ,'),
    array('20703001','','','','MATHEMATICS(CORE) - * ,'),
    array('','One','Female','','INTEGRATED SCIENCE - B3'),
    array('','','','','SOCIAL STUDIES - F9 ,'),
    array('20703002','Example Two','Male','','ENGLISH LANG - X ,'),
    array('','','','','MATHEMATICS(CORE) - D7 ,'),
    array('https://example.test/page','','','',''),
    array('INDEX NUMBER','NAME','GENDER','DOB','RESULTS'),
    array('','','','','INTEGRATED SCIENCE - C6 , INFO. & COMM.'),
    array('','','','','TECHNOLOGY - P'),
    array('SUMMARY OF SUBJECT PASSES','','','',''),
    array('8 PASSES:','','','','2')
);
$parsed = waec_listing_blocks($wrapped);
expect_same('', $parsed['error'], 'Wrapped blocks and page headers');
expect_same(6, count($parsed['rows']), 'All numeric grades preserved');
expect_same(3, count($parsed['withheld_rows']), 'X, star and P retained');
expect_same('20703001', $parsed['rows'][0]['candidate_no'], 'Grades above index belong to next candidate');
expect_same('A1', $parsed['rows'][0]['grade'], 'First candidate grade unchanged');
expect_same('F9', $parsed['rows'][3]['grade'], 'Second candidate does not steal first grade');
expect_same('Example One', $parsed['rows'][0]['student_name'], 'Wrapped names preserved');
expect_same('*', waec_grade_or_withheld('*'), 'Structured star supported');
expect_same('P', waec_grade_or_withheld('P'), 'Structured P supported');
$conflict = $long['rows'][0]; $conflict['grade']='F9';
expect_same(true, !empty(waec_deduplicate_records(array($long['rows'][0],$conflict),array())['error']), 'Conflicting duplicates rejected');
$ambiguous=$wrapped; $ambiguous[2][0]='20703099';
expect_same(true, waec_listing_blocks($ambiguous)['error'] !== '', 'Ambiguous ownership rejected');
$invalid=$wrapped; $invalid[0][4]='ENGLISH LANG - A1'; $invalid[0][0]=''; $invalid[0][1]='';
expect_same(true, waec_listing_blocks($invalid)['error'] !== '', 'Leading unassigned grade rejected');
expect_same(0, waec_coverage_totals(349,343,3,0,3,true)['missing'], 'Source markers are outcomes');
expect_same(3, waec_coverage_totals(349,343,3,0,0,true)['missing'], 'Genuine missing rows remain visible');
if(in_array('--database', $argv, true)){
    require __DIR__.'/../dbstring.php';
    // Connection-local tables shadow production names, and disappear on disconnect.
    $created = mysqli_query($con, "CREATE TEMPORARY TABLE tblwaecresult (
        candidate_no VARCHAR(120), student_name VARCHAR(255), uploadid VARCHAR(80),
        subject_name VARCHAR(120), gender VARCHAR(10), grade VARCHAR(2)
    )");
    if(!$created) throw new RuntimeException('Cannot create isolated result fixture.');
    if(!mysqli_query($con, 'CREATE TEMPORARY TABLE tblwaecwithheld LIKE tblwaecresult')){
        throw new RuntimeException('Cannot create isolated status fixture.');
    }
    mysqli_query($con, "INSERT INTO tblwaecresult VALUES
        ('001','Example A','test','ENGLISH LANG','Female','A1'),
        ('001','Example A','test','MATHEMATICS(CORE)','Female','D7'),
        ('002','Example B','test','ENGLISH LANG','Male','F9'),
        ('003','Other upload','other','ENGLISH LANG','Male','A1')");
    mysqli_query($con, "INSERT INTO tblwaecwithheld VALUES
        ('002','Example B','test','MATHEMATICS(CORE)','Male','W'),
        ('003','Example C','test','ENGLISH LANG','Female','X'),
        ('004','Example D','test','ENGLISH LANG','','W')");
    expect_same(4, waec_filtered_student_count($con, 'test'), 'Counts graded and status-only candidates once');
    expect_same(2, waec_filtered_student_count($con, 'test', '', 'Female'), 'Gender count includes absent');
    expect_same(1, waec_filtered_student_count($con, 'test', '', '', 'A1'), 'Grade filter excludes X/W');
    expect_same(1, waec_filtered_student_count($con, 'test', '', '', 'X'), 'Absent-only filter');
    expect_same(2, waec_filtered_student_count($con, 'test', '', '', 'W'), 'Withheld-only filter');
    expect_same(1, waec_filtered_student_count($con, 'test', 'ENGLISH LANG', '', 'W'), 'Subject and withheld filter');
    expect_same(0, waec_filtered_student_count($con, 'test', '', 'Female', 'F9'), 'Combined filters produce zero');
    expect_same(0, waec_filtered_student_count($con, 'missing'), 'Unknown upload');
    mysqli_close($con);
}
echo $checks." WAEC regression checks passed.\n";
