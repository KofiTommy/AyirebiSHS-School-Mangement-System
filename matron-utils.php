<?php
include_once("user-management-utils.php");
include_once("house-master-utils.php");
include_once("storekeeper-utils.php");

if (!function_exists('matron_is_admin')) {
function matron_is_admin()
{
    return isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) &&
        $_SESSION['ACCESSLEVEL'] === "administrator" &&
        in_array($_SESSION['SYSTEMTYPE'], array("normal_user", "super_user"), true);
}
}

if (!function_exists('matron_landing_page')) {
function matron_landing_page()
{
    if (function_exists('storekeeper_landing_page')) {
        return storekeeper_landing_page();
    }
    return function_exists('um_home_link_for_session') ? um_home_link_for_session() : "index.php";
}
}

if (!function_exists('matron_can_manage_module')) {
function matron_can_manage_module($con = null, $moduleKey = 'matron_management')
{
    if (matron_is_admin()) {
        return true;
    }
    if (!$con || !function_exists('um_current_user_can_access_module')) {
        return false;
    }
    return um_current_user_can_access_module($con, trim((string)$moduleKey));
}
}

if (!function_exists('matron_esc')) {
function matron_esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
}

if (!function_exists('ensure_matron_tables')) {
function ensure_matron_tables($con)
{
    if (!$con) {
        return;
    }

    $useCache = function_exists('xschool_schema_cache_is_fresh') && function_exists('xschool_schema_cache_mark');
    if ($useCache && xschool_schema_cache_is_fresh('schema_matron_v1', 43200)) {
        return;
    }

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblmatronrequisition (
        requisitionid VARCHAR(40) NOT NULL PRIMARY KEY,
        storeitemid VARCHAR(40) NOT NULL,
        requestdate DATE NOT NULL,
        needbydate DATE NULL DEFAULT NULL,
        weekstartdate DATE NULL DEFAULT NULL,
        dayname VARCHAR(20) NOT NULL DEFAULT '',
        mealtime VARCHAR(20) NOT NULL DEFAULT '',
        quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
        purpose VARCHAR(120) NOT NULL DEFAULT '',
        notes VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        decisionnote VARCHAR(255) NULL,
        requestedby VARCHAR(30) NOT NULL,
        decisionby VARCHAR(30) NULL,
        decisiondatetime DATETIME NULL DEFAULT NULL,
        fulfilledissueid VARCHAR(40) NULL DEFAULT NULL,
        datetimeentry DATETIME NOT NULL,
        KEY idx_matronreq_item (storeitemid),
        KEY idx_matronreq_status (status),
        KEY idx_matronreq_requestdate (requestdate),
        KEY idx_matronreq_needby (needbydate),
        KEY idx_matronreq_weekslot (weekstartdate, dayname, mealtime),
        KEY idx_matronreq_requestedby (requestedby)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblmatronweeklymenu (
        menuid VARCHAR(40) NOT NULL PRIMARY KEY,
        weekstartdate DATE NOT NULL,
        dayname VARCHAR(20) NOT NULL,
        mealtime VARCHAR(20) NOT NULL,
        menutitle VARCHAR(120) NOT NULL DEFAULT '',
        menudetails VARCHAR(255) NOT NULL DEFAULT '',
        notes VARCHAR(255) NULL,
        audience VARCHAR(20) NOT NULL DEFAULT 'all',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        datetimeentry DATETIME NOT NULL,
        recordedby VARCHAR(30) NOT NULL,
        UNIQUE KEY uq_matronmenu_slot (weekstartdate, dayname, mealtime, audience),
        KEY idx_matronmenu_week (weekstartdate),
        KEY idx_matronmenu_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if ($useCache) {
        xschool_schema_cache_mark('schema_matron_v1');
    }
}
}

if (!function_exists('matron_can_manage_weekly_menu')) {
function matron_can_manage_weekly_menu($con = null)
{
    return matron_can_manage_module($con, 'matron_management');
}
}

if (!function_exists('matron_can_create_requisition')) {
function matron_can_create_requisition($con = null)
{
    return matron_can_manage_module($con, 'matron_management');
}
}

if (!function_exists('matron_can_review_requisition')) {
function matron_can_review_requisition($con = null)
{
    if (matron_is_admin()) {
        return true;
    }
    return function_exists('storekeeper_can_manage_module') && storekeeper_can_manage_module($con, 'stores_management');
}
}

if (!function_exists('matron_week_start_date')) {
function matron_week_start_date($dateValue = '')
{
    $dateValue = trim((string)$dateValue);
    $timestamp = $dateValue !== '' ? strtotime($dateValue) : strtotime(date('Y-m-d'));
    if (!$timestamp) {
        $timestamp = strtotime(date('Y-m-d'));
    }
    $dayIndex = (int)date('N', $timestamp);
    return date('Y-m-d', strtotime('-' . max(0, $dayIndex - 1) . ' day', $timestamp));
}
}

if (!function_exists('matron_week_end_date')) {
function matron_week_end_date($weekStartDate)
{
    $weekStartDate = matron_week_start_date($weekStartDate);
    return date('Y-m-d', strtotime($weekStartDate . ' +6 day'));
}
}

if (!function_exists('matron_week_label')) {
function matron_week_label($weekStartDate)
{
    $weekStartDate = matron_week_start_date($weekStartDate);
    $weekEndDate = matron_week_end_date($weekStartDate);
    return 'Week of ' . date('d M Y', strtotime($weekStartDate)) . ' to ' . date('d M Y', strtotime($weekEndDate));
}
}

if (!function_exists('matron_menu_day_options')) {
function matron_menu_day_options()
{
    return array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
}
}

if (!function_exists('matron_meal_options')) {
function matron_meal_options()
{
    return array('Breakfast', 'Lunch', 'Supper');
}
}

if (!function_exists('matron_normalize_option_value')) {
function matron_normalize_option_value($value, $options, $defaultValue = '')
{
    $value = preg_replace('/\s+/', ' ', trim((string)$value));
    if ($value === '') {
        return $defaultValue;
    }

    foreach ((array)$options as $option) {
        $option = trim((string)$option);
        if ($option !== '' && strcasecmp($option, $value) === 0) {
            return $option;
        }
    }

    return $value;
}
}

if (!function_exists('matron_normalize_day_name')) {
function matron_normalize_day_name($value, $defaultValue = 'Monday')
{
    return matron_normalize_option_value($value, matron_menu_day_options(), $defaultValue);
}
}

if (!function_exists('matron_normalize_meal_name')) {
function matron_normalize_meal_name($value, $defaultValue = 'Breakfast')
{
    return matron_normalize_option_value($value, matron_meal_options(), $defaultValue);
}
}

if (!function_exists('matron_menu_day_order')) {
function matron_menu_day_order($dayName)
{
    $dayName = trim((string)$dayName);
    $options = matron_menu_day_options();
    $index = array_search($dayName, $options, true);
    return ($index === false) ? 999 : (int)$index;
}
}

if (!function_exists('matron_meal_order')) {
function matron_meal_order($mealName)
{
    $mealName = trim((string)$mealName);
    $options = matron_meal_options();
    $index = array_search($mealName, $options, true);
    return ($index === false) ? 999 : (int)$index;
}
}

if (!function_exists('matron_menu_display_text')) {
function matron_menu_display_text($row)
{
    $title = trim((string)(isset($row['menutitle']) ? $row['menutitle'] : ''));
    $details = trim((string)(isset($row['menudetails']) ? $row['menudetails'] : ''));
    if ($title !== '' && $details !== '') {
        return $title . ': ' . $details;
    }
    return $title !== '' ? $title : $details;
}
}

if (!function_exists('matron_user_display_name')) {
function matron_user_display_name($row)
{
    $parts = array();
    foreach (array('firstname', 'othernames', 'surname') as $field) {
        if (isset($row[$field])) {
            $value = trim((string)$row[$field]);
            if ($value !== '') {
                $parts[] = $value;
            }
        }
    }
    $name = trim(implode(' ', $parts));
    if ($name === '') {
        $name = isset($row['userid']) ? trim((string)$row['userid']) : 'User';
    }
    return $name;
}
}

if (!function_exists('matron_store_catalog_context')) {
function matron_store_catalog_context($con, $limit = 500)
{
    ensure_storekeeper_tables($con);
    $limit = max(1, min(2000, (int)$limit));
    $foodRows = array();
    $activeRows = array();

    foreach (storekeeper_fetch_balance_rows($con) as $row) {
        if ((string)$row['status'] !== 'active') {
            continue;
        }

        $activeRows[] = $row;
        if (matron_is_food_category(isset($row['itemcategory']) ? $row['itemcategory'] : '')) {
            $foodRows[] = $row;
        }
    }

    if (!empty($foodRows)) {
        return array(
            'rows' => array_slice($foodRows, 0, $limit),
            'mode' => 'food_only',
            'uses_fallback' => false,
            'message' => ''
        );
    }

    if (!empty($activeRows)) {
        return array(
            'rows' => array_slice($activeRows, 0, $limit),
            'mode' => 'all_active_fallback',
            'uses_fallback' => true,
            'message' => 'Store items are still under general categories, so the matron form is showing all active store items for now.'
        );
    }

    return array(
        'rows' => array(),
        'mode' => 'empty',
        'uses_fallback' => false,
        'message' => 'There are no active store items available yet.'
    );
}
}

if (!function_exists('matron_store_catalog_uses_fallback')) {
function matron_store_catalog_uses_fallback($con)
{
    $context = matron_store_catalog_context($con, 1);
    return !empty($context['uses_fallback']);
}
}

if (!function_exists('matron_can_request_store_item')) {
function matron_can_request_store_item($con, $itemRow)
{
    if (!is_array($itemRow) || (string)$itemRow['status'] !== 'active') {
        return false;
    }

    if (matron_is_food_category(isset($itemRow['itemcategory']) ? $itemRow['itemcategory'] : '')) {
        return true;
    }

    return matron_store_catalog_uses_fallback($con);
}
}

if (!function_exists('matron_store_item_rows')) {
function matron_store_item_rows($con, $limit = 500)
{
    $context = matron_store_catalog_context($con, $limit);
    return isset($context['rows']) && is_array($context['rows']) ? $context['rows'] : array();
}
}

if (!function_exists('matron_requisition_badge_html')) {
function matron_requisition_badge_html($status)
{
    $status = strtolower(trim((string)$status));
    $className = 'neutral';
    $label = ucwords($status);
    if ($status === 'pending') {
        $className = 'warning';
        $label = 'Pending';
    } elseif ($status === 'approved') {
        $className = 'active';
        $label = 'Approved';
    } elseif ($status === 'issued') {
        $className = 'active';
        $label = 'Issued';
    } elseif ($status === 'rejected') {
        $className = 'danger';
        $label = 'Rejected';
    } elseif ($status === 'cancelled') {
        $className = 'inactive';
        $label = 'Cancelled';
    }
    return "<span class='sk-badge sk-badge--" . $className . "'>" . matron_esc($label) . "</span>";
}
}

if (!function_exists('matron_requisition_slot_label')) {
function matron_requisition_slot_label($dayName, $mealTime)
{
    $dayName = trim((string)$dayName);
    $mealTime = trim((string)$mealTime);
    if ($mealTime !== '' && $dayName !== '') {
        return $mealTime . ' on ' . $dayName;
    }
    if ($mealTime !== '') {
        return $mealTime;
    }
    return $dayName;
}
}

if (!function_exists('matron_fetch_weekly_menu_rows')) {
function matron_fetch_weekly_menu_rows($con, $filters = array())
{
    ensure_matron_tables($con);
    $filters = is_array($filters) ? $filters : array();
    $weekStartDate = isset($filters['weekstartdate']) ? matron_week_start_date($filters['weekstartdate']) : '';
    $status = isset($filters['status']) ? trim((string)$filters['status']) : 'active';
    $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
    $limit = max(7, min(400, $limit));

    $where = array("1=1");
    if ($weekStartDate !== '') {
        $weekStartEsc = mysqli_real_escape_string($con, $weekStartDate);
        $where[] = "m.weekstartdate='$weekStartEsc'";
    }
    if ($status !== '') {
        $statusEsc = mysqli_real_escape_string($con, $status);
        $where[] = "m.status='$statusEsc'";
    }

    $rows = array();
    $sql = "SELECT m.*
        FROM tblmatronweeklymenu m
        WHERE " . implode(" AND ", $where) . "
        ORDER BY
            FIELD(m.dayname,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
            FIELD(m.mealtime,'Breakfast','Lunch','Supper'),
            m.datetimeentry DESC
        LIMIT $limit";
    $res = @mysqli_query($con, $sql);
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $row['_display_text'] = matron_menu_display_text($row);
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if (!function_exists('matron_group_menu_rows')) {
function matron_group_menu_rows($rows)
{
    $grouped = array();
    foreach (matron_menu_day_options() as $dayName) {
        $grouped[$dayName] = array();
        foreach (matron_meal_options() as $mealName) {
            $grouped[$dayName][$mealName] = null;
        }
    }

    if (!is_array($rows)) {
        return $grouped;
    }

    foreach ($rows as $row) {
        $dayName = isset($row['dayname']) ? trim((string)$row['dayname']) : '';
        $mealName = isset($row['mealtime']) ? trim((string)$row['mealtime']) : '';
        if (isset($grouped[$dayName]) && array_key_exists($mealName, $grouped[$dayName])) {
            $grouped[$dayName][$mealName] = $row;
        }
    }

    return $grouped;
}
}

if (!function_exists('matron_current_week_menu_context')) {
function matron_current_week_menu_context($con, $referenceDate = '')
{
    $weekStartDate = matron_week_start_date($referenceDate);
    $rows = matron_fetch_weekly_menu_rows($con, array(
        'weekstartdate' => $weekStartDate,
        'status' => 'active',
        'limit' => 40
    ));

    return array(
        'week_start' => $weekStartDate,
        'week_end' => matron_week_end_date($weekStartDate),
        'week_label' => matron_week_label($weekStartDate),
        'rows' => $rows,
        'grouped' => matron_group_menu_rows($rows)
    );
}
}

if (!function_exists('matron_weekly_menu_summary')) {
function matron_weekly_menu_summary($con, $weekStartDate = '')
{
    $weekStartDate = matron_week_start_date($weekStartDate);
    $totalSlots = count(matron_menu_day_options()) * count(matron_meal_options());
    $usedSlots = 0;
    $res = @mysqli_query($con, "SELECT COUNT(*) AS total_slots
        FROM tblmatronweeklymenu
        WHERE weekstartdate='" . mysqli_real_escape_string($con, $weekStartDate) . "'
          AND status='active'");
    if ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
        $usedSlots = (int)$row['total_slots'];
    }
    return array(
        'week_start' => $weekStartDate,
        'slot_total' => $totalSlots,
        'slot_filled' => $usedSlots,
        'slot_open' => max(0, $totalSlots - $usedSlots)
    );
}
}

if (!function_exists('matron_fetch_requisition_rows')) {
function matron_fetch_requisition_rows($con, $filters = array())
{
    ensure_matron_tables($con);
    $filters = is_array($filters) ? $filters : array();
    $status = isset($filters['status']) ? trim((string)$filters['status']) : '';
    $search = isset($filters['search']) ? trim((string)$filters['search']) : '';
    $limit = isset($filters['limit']) ? (int)$filters['limit'] : 120;
    $limit = max(10, min(2000, $limit));

    $where = array("1=1");
    if ($status !== '') {
        $statusEsc = mysqli_real_escape_string($con, $status);
        $where[] = "mr.status='$statusEsc'";
    }
    if ($search !== '') {
        $searchEsc = mysqli_real_escape_string($con, $search);
        $searchLike = "%" . $searchEsc . "%";
        $where[] = "(mr.requisitionid LIKE '$searchLike'
            OR COALESCE(si.itemname,'') LIKE '$searchLike'
            OR COALESCE(mr.purpose,'') LIKE '$searchLike'
            OR COALESCE(mr.dayname,'') LIKE '$searchLike'
            OR COALESCE(mr.mealtime,'') LIKE '$searchLike'
            OR CONCAT_WS(' ', COALESCE(req.firstname,''), COALESCE(req.othernames,''), COALESCE(req.surname,'')) LIKE '$searchLike')";
    }

    $rows = array();
    $sql = "SELECT
            mr.*,
            COALESCE(si.itemname, mr.storeitemid) AS itemname,
            COALESCE(si.unitname, '') AS unitname,
            COALESCE(si.itemcategory, '') AS itemcategory,
            req.firstname AS requested_firstname,
            req.othernames AS requested_othernames,
            req.surname AS requested_surname,
            decision_user.firstname AS decision_firstname,
            decision_user.othernames AS decision_othernames,
            decision_user.surname AS decision_surname
        FROM tblmatronrequisition mr
        LEFT JOIN tblstoreitem si ON si.storeitemid=mr.storeitemid
        LEFT JOIN tblsystemuser req ON req.userid=mr.requestedby
        LEFT JOIN tblsystemuser decision_user ON decision_user.userid=mr.decisionby
        WHERE " . implode(" AND ", $where) . "
        ORDER BY
            CASE mr.status
                WHEN 'pending' THEN 0
                WHEN 'approved' THEN 1
                WHEN 'issued' THEN 2
                WHEN 'rejected' THEN 3
                WHEN 'cancelled' THEN 4
                ELSE 5
            END ASC,
            CASE WHEN mr.needbydate IS NULL OR mr.needbydate='0000-00-00' THEN 1 ELSE 0 END ASC,
            mr.needbydate ASC,
            mr.datetimeentry DESC
        LIMIT $limit";
    $res = @mysqli_query($con, $sql);
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $row['requested_by_name'] = matron_user_display_name(array(
                'firstname' => isset($row['requested_firstname']) ? $row['requested_firstname'] : '',
                'othernames' => isset($row['requested_othernames']) ? $row['requested_othernames'] : '',
                'surname' => isset($row['requested_surname']) ? $row['requested_surname'] : '',
                'userid' => isset($row['requestedby']) ? $row['requestedby'] : ''
            ));
            $row['decision_by_name'] = matron_user_display_name(array(
                'firstname' => isset($row['decision_firstname']) ? $row['decision_firstname'] : '',
                'othernames' => isset($row['decision_othernames']) ? $row['decision_othernames'] : '',
                'surname' => isset($row['decision_surname']) ? $row['decision_surname'] : '',
                'userid' => isset($row['decisionby']) ? $row['decisionby'] : ''
            ));
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if (!function_exists('matron_requisition_summary')) {
function matron_requisition_summary($con)
{
    ensure_matron_tables($con);
    $summary = array(
        'total' => 0,
        'pending' => 0,
        'approved' => 0,
        'issued' => 0,
        'rejected' => 0,
        'cancelled' => 0
    );
    $res = @mysqli_query($con, "SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved_count,
            SUM(CASE WHEN status='issued' THEN 1 ELSE 0 END) AS issued_count,
            SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) AS rejected_count,
            SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled_count
        FROM tblmatronrequisition");
    if ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
        $summary['total'] = (int)$row['total_count'];
        $summary['pending'] = (int)$row['pending_count'];
        $summary['approved'] = (int)$row['approved_count'];
        $summary['issued'] = (int)$row['issued_count'];
        $summary['rejected'] = (int)$row['rejected_count'];
        $summary['cancelled'] = (int)$row['cancelled_count'];
    }
    return $summary;
}
}

if (!function_exists('matron_food_categories')) {
function matron_food_categories()
{
    return array(
        'Food Item',
        'Dry Goods',
        'Protein',
        'Vegetable',
        'Beverage',
        'Condiment',
        'Kitchen Supply'
    );
}
}

if (!function_exists('matron_food_category_lookup')) {
function matron_food_category_lookup()
{
    static $lookup = null;
    if ($lookup !== null) {
        return $lookup;
    }

    $lookup = array();
    foreach (matron_food_categories() as $category) {
        $lookup[strtolower(trim((string)$category))] = true;
    }
    return $lookup;
}
}

if (!function_exists('matron_is_food_category')) {
function matron_is_food_category($category)
{
    $category = strtolower(trim((string)$category));
    if ($category === '') {
        return false;
    }
    $lookup = matron_food_category_lookup();
    return isset($lookup[$category]);
}
}

if (!function_exists('matron_food_category_sql_list')) {
function matron_food_category_sql_list($con)
{
    $parts = array();
    foreach (matron_food_categories() as $category) {
        $parts[] = "'" . mysqli_real_escape_string($con, $category) . "'";
    }
    return implode(',', $parts);
}
}

if (!function_exists('matron_boarders_without_house_count')) {
function matron_boarders_without_house_count($con)
{
    $total = 0;
    $res = @mysqli_query($con, "SELECT COUNT(*) AS total_count
        FROM tblsystemuser su
        WHERE su.systemtype='Student'
          AND su.status='active'
          AND UPPER(TRIM(COALESCE(su.residencetype,''))) IN ('BOARDING','BOARDER','B','HOSTEL','RESIDENT')
          AND NOT EXISTS (
              SELECT 1
              FROM tblstudenthouse sh
              WHERE sh.userid=su.userid
                AND sh.status='active'
          )");
    if ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
        $total = (int)$row['total_count'];
    }
    return $total;
}
}

if (!function_exists('matron_boarding_house_summary')) {
function matron_boarding_house_summary($con)
{
    $summary = array(
        'boarding_houses' => 0,
        'boarding_houses_with_supervisor' => 0,
        'boarding_houses_without_supervisor' => 0
    );

    $res = @mysqli_query($con, "SELECT
            COUNT(*) AS boarding_houses,
            SUM(CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM tblhousemaster hm
                    WHERE hm.houseid=h.houseid
                      AND hm.status='active'
                    LIMIT 1
                )
                THEN 1 ELSE 0
            END) AS houses_with_supervisor,
            SUM(CASE
                WHEN NOT EXISTS (
                    SELECT 1
                    FROM tblhousemaster hm
                    WHERE hm.houseid=h.houseid
                      AND hm.status='active'
                    LIMIT 1
                )
                THEN 1 ELSE 0
            END) AS houses_without_supervisor
        FROM tblhouse h
        WHERE h.status='active'
          AND UPPER(TRIM(COALESCE(h.houseresidencetype,''))) IN ('BOARDING','BOARDER','HOSTEL')");
    if ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
        $summary['boarding_houses'] = (int)$row['boarding_houses'];
        $summary['boarding_houses_with_supervisor'] = (int)$row['houses_with_supervisor'];
        $summary['boarding_houses_without_supervisor'] = (int)$row['houses_without_supervisor'];
    }

    return $summary;
}
}

if (!function_exists('matron_boarding_exeat_summary')) {
function matron_boarding_exeat_summary($con)
{
    $summary = array(
        'pending_exeat' => 0,
        'active_out' => 0,
        'overdue_returns' => 0,
        'returned_today' => 0
    );

    $overdueSql = function_exists('house_master_exeat_overdue_sql')
        ? house_master_exeat_overdue_sql('er')
        : "er.status='approved' AND er.actualreturndatetime IS NULL AND er.datereturn IS NOT NULL AND er.datereturn < CURDATE()";

    $res = @mysqli_query($con, "SELECT
            SUM(CASE WHEN er.status='pending' THEN 1 ELSE 0 END) AS pending_exeat,
            SUM(CASE WHEN er.status='approved' AND er.actualreturndatetime IS NULL THEN 1 ELSE 0 END) AS active_out,
            SUM(CASE WHEN $overdueSql THEN 1 ELSE 0 END) AS overdue_returns,
            SUM(CASE WHEN er.actualreturndatetime IS NOT NULL AND DATE(er.actualreturndatetime)=CURDATE() THEN 1 ELSE 0 END) AS returned_today
        FROM tblexeatrequest er
        INNER JOIN tblhouse h ON h.houseid=er.houseid
        WHERE h.status='active'
          AND UPPER(TRIM(COALESCE(h.houseresidencetype,''))) IN ('BOARDING','BOARDER','HOSTEL')");
    if ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
        $summary['pending_exeat'] = (int)$row['pending_exeat'];
        $summary['active_out'] = (int)$row['active_out'];
        $summary['overdue_returns'] = (int)$row['overdue_returns'];
        $summary['returned_today'] = (int)$row['returned_today'];
    }

    return $summary;
}
}

if (!function_exists('matron_boarding_student_item_summary')) {
function matron_boarding_student_item_summary($con)
{
    $summary = array(
        'boarding_student_items_out' => 0,
        'boarding_student_items_overdue' => 0
    );

    $res = @mysqli_query($con, "SELECT
            COUNT(*) AS total_out,
            SUM(CASE
                WHEN ssi.status='issued'
                 AND ssi.returnrequired=1
                 AND ssi.expectedreturndate IS NOT NULL
                 AND ssi.expectedreturndate <> '0000-00-00'
                 AND ssi.expectedreturndate < CURDATE()
                THEN 1 ELSE 0
            END) AS overdue_total
        FROM tblstorestudentissue ssi
        INNER JOIN tblsystemuser su ON su.userid=ssi.studentid
        WHERE ssi.status IN ('issued','lost')
          AND UPPER(TRIM(COALESCE(su.residencetype,''))) IN ('BOARDING','BOARDER','B','HOSTEL','RESIDENT')");
    if ($res && ($row = mysqli_fetch_array($res, MYSQLI_ASSOC))) {
        $summary['boarding_student_items_out'] = (int)$row['total_out'];
        $summary['boarding_student_items_overdue'] = (int)$row['overdue_total'];
    }

    return $summary;
}
}

if (!function_exists('matron_food_stock_summary')) {
function matron_food_stock_summary($con)
{
    $summary = array(
        'food_items_total' => 0,
        'food_low_stock' => 0,
        'food_out_of_stock' => 0
    );

    $rows = matron_store_item_rows($con, 2000);
    foreach ($rows as $row) {
        $summary['food_items_total']++;
        $balance = (float)$row['current_balance'];
        $reorderLevel = (float)$row['reorderlevel'];
        if ($balance <= 0) {
            $summary['food_out_of_stock']++;
            $summary['food_low_stock']++;
        } elseif ($reorderLevel > 0 && $balance <= $reorderLevel) {
            $summary['food_low_stock']++;
        }
    }

    return $summary;
}
}

if (!function_exists('matron_dashboard_summary')) {
function matron_dashboard_summary($con)
{
    ensure_house_tables($con);
    ensure_storekeeper_tables($con);
    ensure_matron_tables($con);

    $population = storekeeper_student_population_summary($con);
    $houseSummary = matron_boarding_house_summary($con);
    $exeatSummary = matron_boarding_exeat_summary($con);
    $foodSummary = matron_food_stock_summary($con);
    $studentItemSummary = matron_boarding_student_item_summary($con);
    $requisitionSummary = matron_requisition_summary($con);
    $menuSummary = matron_weekly_menu_summary($con, date('Y-m-d'));
    $boardersWithoutHouse = matron_boarders_without_house_count($con);

    return array(
        'student_total' => (int)$population['student_total'],
        'day_students_total' => (int)$population['day_students_total'],
        'boarding_students_total' => (int)$population['boarding_students_total'],
        'day_boys' => (int)$population['day_boys'],
        'day_girls' => (int)$population['day_girls'],
        'boarding_boys' => (int)$population['boarding_boys'],
        'boarding_girls' => (int)$population['boarding_girls'],
        'other_students_total' => (int)$population['other_students_total'],
        'students_no_status' => isset($population['students_no_status']) ? (int)$population['students_no_status'] : 0,
        'batch_breakdown' => (isset($population['batch_breakdown']) && is_array($population['batch_breakdown'])) ? $population['batch_breakdown'] : array(),
        'boarders_without_house' => $boardersWithoutHouse,
        'boarders_with_house' => max(0, (int)$population['boarding_students_total'] - $boardersWithoutHouse),
        'boarding_houses' => (int)$houseSummary['boarding_houses'],
        'boarding_houses_with_supervisor' => (int)$houseSummary['boarding_houses_with_supervisor'],
        'boarding_houses_without_supervisor' => (int)$houseSummary['boarding_houses_without_supervisor'],
        'pending_exeat' => (int)$exeatSummary['pending_exeat'],
        'active_out' => (int)$exeatSummary['active_out'],
        'overdue_returns' => (int)$exeatSummary['overdue_returns'],
        'returned_today' => (int)$exeatSummary['returned_today'],
        'food_items_total' => (int)$foodSummary['food_items_total'],
        'food_low_stock' => (int)$foodSummary['food_low_stock'],
        'food_out_of_stock' => (int)$foodSummary['food_out_of_stock'],
        'boarding_student_items_out' => (int)$studentItemSummary['boarding_student_items_out'],
        'boarding_student_items_overdue' => (int)$studentItemSummary['boarding_student_items_overdue'],
        'requisition_total' => (int)$requisitionSummary['total'],
        'requisition_pending' => (int)$requisitionSummary['pending'],
        'requisition_approved' => (int)$requisitionSummary['approved'],
        'requisition_issued' => (int)$requisitionSummary['issued'],
        'requisition_rejected' => (int)$requisitionSummary['rejected'],
        'requisition_cancelled' => (int)$requisitionSummary['cancelled'],
        'menu_week_start' => (string)$menuSummary['week_start'],
        'menu_slot_total' => (int)$menuSummary['slot_total'],
        'menu_slot_filled' => (int)$menuSummary['slot_filled'],
        'menu_slot_open' => (int)$menuSummary['slot_open']
    );
}
}

if (!function_exists('matron_food_watch_rows')) {
function matron_food_watch_rows($con, $limit = 8)
{
    $limit = max(1, min(24, (int)$limit));
    $rows = array();
    foreach (matron_store_item_rows($con, 2000) as $row) {
        $balance = (float)$row['current_balance'];
        $reorderLevel = (float)$row['reorderlevel'];
        if ($balance <= 0 || ($reorderLevel > 0 && $balance <= $reorderLevel)) {
            $row['_severity'] = ($balance <= 0) ? 0 : 1;
            $rows[] = $row;
        }
    }

    usort($rows, function ($left, $right) {
        $leftSeverity = isset($left['_severity']) ? (int)$left['_severity'] : 9;
        $rightSeverity = isset($right['_severity']) ? (int)$right['_severity'] : 9;
        if ($leftSeverity !== $rightSeverity) {
            return $leftSeverity - $rightSeverity;
        }
        $leftBalance = isset($left['current_balance']) ? (float)$left['current_balance'] : 0;
        $rightBalance = isset($right['current_balance']) ? (float)$right['current_balance'] : 0;
        if ($leftBalance === $rightBalance) {
            return strcmp((string)$left['itemname'], (string)$right['itemname']);
        }
        return ($leftBalance < $rightBalance) ? -1 : 1;
    });

    return array_slice($rows, 0, $limit);
}
}

if (!function_exists('matron_recent_food_issues')) {
function matron_recent_food_issues($con, $limit = 8)
{
    ensure_storekeeper_tables($con);
    $limit = max(1, min(30, (int)$limit));
    $rows = array();
    $where = array("COALESCE(siu.status, 'posted')='posted'");
    if (matron_store_catalog_uses_fallback($con)) {
        $where[] = "COALESCE(sti.status, 'active')='active'";
    } else {
        $categoryList = matron_food_category_sql_list($con);
        $where[] = "COALESCE(sti.itemcategory, '') IN ($categoryList)";
    }
    $res = @mysqli_query($con, "SELECT
            siu.*,
            COALESCE(sti.itemname, siu.storeitemid) AS itemname,
            COALESCE(sti.unitname, '') AS unitname,
            COALESCE(sti.itemcategory, '') AS itemcategory
        FROM tblstoreissue siu
        LEFT JOIN tblstoreitem sti ON sti.storeitemid=siu.storeitemid
        WHERE " . implode(" AND ", $where) . "
        ORDER BY siu.issuedate DESC, siu.datetimeentry DESC
        LIMIT $limit");
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if (!function_exists('matron_boarding_house_rows')) {
function matron_boarding_house_rows($con, $limit = 8)
{
    ensure_house_tables($con);
    $limit = max(1, min(30, (int)$limit));
    $rows = array();
    $overdueSql = function_exists('house_master_exeat_overdue_sql')
        ? house_master_exeat_overdue_sql('er')
        : "er.status='approved' AND er.actualreturndatetime IS NULL AND er.datereturn IS NOT NULL AND er.datereturn < CURDATE()";

    $res = @mysqli_query($con, "SELECT
            h.houseid,
            h.housename,
            h.housegender,
            h.houseresidencetype,
            h.status,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(su.firstname,''), ' ', COALESCE(su.othernames,''), ' ', COALESCE(su.surname,''))), ''), 'Not Assigned') AS supervisor_name,
            COALESCE(su.userid, '') AS supervisor_id,
            (SELECT COUNT(*)
                FROM tblstudenthouse sh
                INNER JOIN tblsystemuser stu ON stu.userid=sh.userid
                WHERE sh.houseid=h.houseid
                  AND sh.status='active'
                  AND stu.systemtype='Student'
                  AND stu.status='active'
            ) AS student_count,
            (SELECT COUNT(*) FROM tblexeatrequest er WHERE er.houseid=h.houseid AND er.status='pending') AS pending_exeat,
            (SELECT COUNT(*) FROM tblexeatrequest er WHERE er.houseid=h.houseid AND er.status='approved' AND er.actualreturndatetime IS NULL) AS active_out,
            (SELECT COUNT(*) FROM tblexeatrequest er WHERE er.houseid=h.houseid AND $overdueSql) AS overdue_returns
        FROM tblhouse h
        LEFT JOIN tblhousemaster hm ON hm.assignmentid = (
            SELECT hm2.assignmentid
            FROM tblhousemaster hm2
            WHERE hm2.houseid=h.houseid
              AND hm2.status='active'
            ORDER BY hm2.datetimeentry DESC
            LIMIT 1
        )
        LEFT JOIN tblsystemuser su ON su.userid=hm.userid
        WHERE h.status='active'
          AND UPPER(TRIM(COALESCE(h.houseresidencetype,''))) IN ('BOARDING','BOARDER','HOSTEL')
        ORDER BY
            CASE WHEN COALESCE(su.userid, '')='' THEN 0 ELSE 1 END ASC,
            overdue_returns DESC,
            student_count DESC,
            h.housename ASC
        LIMIT $limit");
    if ($res) {
        while ($row = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
            $rows[] = $row;
        }
    }
    return $rows;
}
}

if (!function_exists('matron_recent_boarding_student_issues')) {
function matron_recent_boarding_student_issues($con, $limit = 8)
{
    $limit = max(1, min(30, (int)$limit));
    $rows = array();
    foreach (storekeeper_recent_student_issues($con, max(10, $limit * 3)) as $row) {
        if (isset($row['_population_bucket']) && in_array((string)$row['_population_bucket'], array('boarding', 'boarding_boys', 'boarding_girls'), true)) {
            $rows[] = $row;
        }
        if (count($rows) >= $limit) {
            break;
        }
    }
    return $rows;
}
}

if (!function_exists('matron_recent_requisitions')) {
function matron_recent_requisitions($con, $limit = 8)
{
    return matron_fetch_requisition_rows($con, array('limit' => max(1, min(50, (int)$limit))));
}
}

if (!function_exists('matron_house_profile_label')) {
function matron_house_profile_label($row)
{
    $gender = function_exists('house_master_normalize_gender_label')
        ? house_master_normalize_gender_label(isset($row['housegender']) ? $row['housegender'] : '')
        : trim((string)(isset($row['housegender']) ? $row['housegender'] : ''));
    $residence = function_exists('house_master_normalize_residence_label')
        ? house_master_normalize_residence_label(isset($row['houseresidencetype']) ? $row['houseresidencetype'] : '')
        : trim((string)(isset($row['houseresidencetype']) ? $row['houseresidencetype'] : ''));

    $parts = array();
    if ($gender !== '') {
        $parts[] = $gender;
    }
    if ($residence !== '') {
        $parts[] = $residence;
    }
    return empty($parts) ? 'Boarding House' : implode(' ', $parts) . ' House';
}
}
