<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../student-list-pagination.php';
function paging_check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
$_POST = $_GET = array();
paging_check(student_list_page(61)['size'] === 25, 'Default size');
$_GET = array('page' => '3');
paging_check(student_list_page(61)['offset'] === 50, 'Third page');
$_GET = array('page' => '99999', 'per_page' => '50000');
paging_check(student_list_page(61)['page'] === 3, 'Clamp page');
paging_check(student_list_page(61)['size'] === 25, 'Bound size');
$_GET = array('page' => '-4', 'per_page' => '50');
paging_check(student_list_page(0)['offset'] === 0, 'Empty result');
$_GET = array('page' => array('bad'), 'q' => array('bad'));
paging_check(student_list_page(61)['page'] === 1 && student_list_input('q') === '', 'Reject array input');
$_GET = array('q' => '<script>& name', 'page' => '2');
ob_start();
student_list_navigation(student_list_page(61), 'viewstudents.php', array('class_entryid' => 'C1', 'batchid' => 'B1', 'showstudent' => '1'));
$html = ob_get_clean();
paging_check(strpos($html, 'class_entryid=C1&amp;batchid=B1') !== false, 'Preserve filters');
paging_check(strpos($html, '<script>') === false && strpos($html, 'q=%3Cscript%3E') !== false, 'Encode search');
echo "Pagination boundary and navigation tests passed.\n";

if (in_array('--database', $argv, true)) {
    // Read-only: exercise the actual listing queries without running page controllers.
    require __DIR__.'/../dbstring.php';
    $_POST = $_GET = array('page' => '2', 'q' => "O'Brien_%");
    $selectedBatchId = $selectedClassId = $selectedBatchIdSafe = $selectedClassIdSafe = '';
    $source = file_get_contents(__DIR__.'/../continuing-students.php');
    $start = strpos($source, '$sql = "SELECT * FROM vw_continuing_students');
    $end = strpos($source, '$studentRows = array();', $start);
    eval(substr($source, $start, $end - $start));
    $result = mysqli_query($con, $sql);
    paging_check(mysqli_num_rows($result) <= $paging['size'], 'Continuing list bounded');
    $_SelectedClassentryID = $_SelectedBatchID = $_BranchStudentFilter = '';
    $source = file_get_contents(__DIR__.'/../viewstudents.php');
    $start = strpos($source, '$_StudentFrom =');
    $end = strpos($source, 'if($_SQL_EXECUTE2)', $start);
    eval(substr($source, $start, $end - $start));
    paging_check(mysqli_num_rows($_SQL_EXECUTE2) <= $_Paging['size'], 'Class list bounded');
    echo "Both actual listing and summary SQL queries passed (read-only).\n";
}
