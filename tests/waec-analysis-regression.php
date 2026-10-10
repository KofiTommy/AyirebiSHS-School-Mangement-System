<?php
if(PHP_SAPI !== 'cli'){
    http_response_code(403);
    exit;
}
// Run with PHP CLI. Load only pure helpers; no session, connection or upload writes.
require_once __DIR__.'/../waec-listing-utils.php';
$source = file_get_contents(__DIR__.'/../waec-analysis.php');
$tokens = token_get_all($source);
$wanted = array('waec_key','waec_grade','waec_grade_or_withheld','waec_normalize_gender',
    'waec_points','waec_normalize_subject','waec_identity_key','waec_candidate_col',
    'waec_name_col','waec_gender_col','waec_subject_col','waec_grade_col',
    'waec_build_header_map','waec_extract_rows','waec_deduplicate_records','waec_csv_cell',
    'waec_build_filter_where','waec_filtered_student_count','waec_is_index_number',
    'waec_extract_listing_rows_from_rows','waec_extract_listing_rows_from_xlsx','waec_read_csv_rows');
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
