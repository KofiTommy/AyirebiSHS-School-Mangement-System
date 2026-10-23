<?php
// Shared, bounded server-side paging for student directories.
function student_list_input($key, $default = '') {
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_scalar($value) ? trim((string)$value) : (string)$default;
}
function student_list_page($total) {
    $size = (int)student_list_input('per_page', '25');
    if (!in_array($size, array(25, 50, 100), true)) { $size = 25; }
    $pages = max(1, (int)ceil($total / $size));
    $page = min($pages, max(1, (int)student_list_input('page', '1')));
    return array('total' => (int)$total, 'size' => $size, 'pages' => $pages, 'page' => $page, 'offset' => ($page - 1) * $size);
}
function student_list_search_sql($con, $fields) {
    $query = student_list_input('q');
    if ($query === '') { return ''; }
    // LOCATE treats % and _ literally, unlike LIKE wildcards.
    $parts = array();
    foreach ($fields as $field) { $parts[] = "COALESCE(".$field.", '')"; }
    return " AND LOCATE(LOWER('".mysqli_real_escape_string($con, $query)."'), LOWER(CONCAT_WS(' ', ".implode(', ', $parts)."))) > 0";
}
function student_list_controls() {
    $size = student_list_page(0)['size'];
    echo '<label class="student-directory-field"><span>Search all matching students</span><input style="width:100%;min-height:48px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px" type="search" name="q" value="'.htmlspecialchars(student_list_input('q'), ENT_QUOTES, 'UTF-8').'" placeholder="Name, student ID or other details"></label>';
    echo '<label class="student-directory-field"><span>Students per page</span><select name="per_page">';
    foreach (array(25, 50, 100) as $option) {
        echo '<option value="'.$option.'"'.($size === $option ? ' selected' : '').'>'.$option.'</option>';
    }
    echo '</select></label>';
}
function student_list_navigation($paging, $path, $filters) {
    $filters['q'] = student_list_input('q');
    $filters['per_page'] = $paging['size'];
    $first = $paging['total'] ? $paging['offset'] + 1 : 0;
    $last = min($paging['total'], $paging['offset'] + $paging['size']);
    echo '<nav aria-label="Student list pagination" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;padding:16px 0">';
    echo '<span>Showing '.$first.'–'.$last.' of '.number_format($paging['total']).' students · Page '.$paging['page'].' of '.$paging['pages'].'</span>';
    foreach (array('First' => 1, 'Previous' => $paging['page'] - 1, 'Next' => $paging['page'] + 1, 'Last' => $paging['pages']) as $label => $page) {
        if ($page < 1 || $page > $paging['pages'] || $page === $paging['page']) {
            echo '<span aria-disabled="true">'.$label.'</span>';
        } else {
            $filters['page'] = $page;
            echo '<a style="padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px" href="'.htmlspecialchars($path.'?'.http_build_query($filters), ENT_QUOTES, 'UTF-8').'">'.$label.'</a>';
        }
    }
    echo '</nav>';
}
