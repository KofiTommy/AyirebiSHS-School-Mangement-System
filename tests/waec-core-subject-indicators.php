<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
// Exercise the actual SQL expressions using synthetic derived rows, no table writes.
$source = file_get_contents(__DIR__.'/../waec-analysis.php');
$expressions = array();
foreach (array('science_credit', 'social_credit') as $key) {
    preg_match_all('/MAX\(CASE WHEN[^\r\n]+END\) AS '.$key.'\b/', $source, $matches);
    if (count($matches[0]) !== 4 || count(array_unique($matches[0])) !== 1) {
        throw new RuntimeException('Page/export expressions inconsistent: '.$key);
    }
    $expressions[] = $matches[0][0];
}
if (strpos($source, 'Science/Social Credit') !== false) { throw new RuntimeException('Combined subject label remains'); }
require __DIR__.'/../dbstring.php';
$cases = array(
    array(1,9,1,0), array(9,6,0,1), array(6,1,1,1), array(7,9,0,0), array(0,0,0,0)
);
foreach ($cases as $case) {
    $sql = 'SELECT '.implode(',', $expressions)." FROM (
        SELECT 'INTEGRATED SCIENCE' AS subject_name, ".$case[0]." AS grade_point
        UNION ALL SELECT 'SOCIAL STUDIES', ".$case[1]."
        UNION ALL SELECT 'BIOLOGY', 1
        UNION ALL SELECT 'INTEGRATED SCIENCE', ".$case[0]."
    ) fixture";
    $row = mysqli_fetch_assoc(mysqli_query($con, $sql));
    if ((int)$row['science_credit'] !== $case[2] || (int)$row['social_credit'] !== $case[3]) {
        throw new RuntimeException('Subjects not counted independently');
    }
}
echo "PASS: separate Science/Social indicators, A1/C6 boundaries, failures, invalid zero and duplicate rows; all four SQL paths consistent. No data changed.\n";
