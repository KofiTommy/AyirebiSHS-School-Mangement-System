<?php
session_start();
require_once __DIR__.'/check-login.php';
require_once __DIR__.'/waec-comparison-utils.php';
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
$allowed = (($_SESSION['ACCESSLEVEL'] ?? '') === 'administrator' && in_array($_SESSION['SYSTEMTYPE'] ?? '',array('normal_user','super_user'),true))
    || (function_exists('um_is_academic_lead_user') && um_is_academic_lead_user());
if(!$allowed){ http_response_code(403); exit('You do not have permission to view WAEC student results.'); }
$error = ''; $upload = null; $students = array();
$labels = wac_achievement_labels(); $flagLabels = wac_flag_labels();
$groups = array('confirmed'=>'Confirmed','not_confirmed'=>'Not confirmed','all'=>'All candidates');
try{
    $uploadId = $_GET['upload'] ?? '';
    if(!is_string($uploadId) || $uploadId === '' || strlen($uploadId)>120) throw new InvalidArgumentException('Select an upload from the batch comparison.');
    $filters = wac_student_filters($_GET);
    $stmt = mysqli_prepare($con,'SELECT * FROM tblwaecupload WHERE uploadid=? LIMIT 1');
    mysqli_stmt_bind_param($stmt,'s',$uploadId); mysqli_stmt_execute($stmt);
    $upload = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
    if(!$upload || !wac_accessible($upload,$_SESSION)){
        http_response_code(404); $upload = null;
        $error = 'This upload is unavailable or outside your branch.';
    }else{
        $analysis = wac_analyze(wac_load_rows($con,$uploadId),true);
        $students = wac_filter_students($analysis['students'],$filters);
    }
}catch(InvalidArgumentException $e){ http_response_code(400); $error = $e->getMessage(); }
catch(Throwable $e){
    http_response_code(500); error_log('WAEC student drilldown: '.$e->getMessage());
    $error = 'Student results could not be loaded. Please try again or contact your administrator.';
}
if(!$error){
    $title = wac_ready($upload) ? wac_title($upload) : 'Upload '.$uploadId;
    $metric = $analysis['achievement']['metrics'][$filters['metric']];
    $total = count($students); $pages = max(1,(int)ceil($total/50));
    $filters['page'] = min($filters['page'],$pages);
    $pageStudents = array_slice($students,($filters['page']-1)*50,50);
    $baseQuery = array_merge(array('upload'=>$uploadId),$filters);
    $exportUrl = 'waec-achievement-students.php?'.http_build_query(array_merge($baseQuery,array('export'=>'csv')));
    if(($_GET['export'] ?? '') === 'csv'){
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="waec-achievement-students.csv"');
        header('X-Content-Type-Options: nosniff');
        $out = fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
        $write = function($row) use ($out){ fputcsv($out,array_map('wac_csv_cell',$row)); };
        $write(array('Batch',$title,'Upload ID',$uploadId));
        $write(array('Criterion',$labels[$filters['metric']],'Group',$groups[$filters['group']]));
        $write(array('Outcome filter',$flagLabels[$filters['flag']] ?? 'All outcomes','Search',$filters['q'],'Matching students',$total));
        $write(array('Method','Confirmed A1-C6 credits only. Not confirmed does not mean failed. Missing electives cannot be determined without a registration roster. Conflicting results count as no confirmed credit. Protect this file: it contains student information.'));
        $write(array('Candidate number (text)','Student name','Criterion confirmed','Distinct credits','Outcome flags for criterion','Subject outcomes'));
        foreach($students as $student){
            $outcomes = array();
            foreach($student['outcomes'] as $subject=>$grades) $outcomes[] = $subject.': '.implode(' / ',array_map(function($grade){ return $grade === '' ? '(blank)' : $grade; },$grades));
            $flags = array(); foreach($student['flags'] as $key=>$unused) $flags[] = $flagLabels[$key];
            $write(array($student['number'],$student['name'],$student['metrics'][$filters['metric']]['achieved'] ? 'Yes':'Not confirmed',
                $student['credits'],implode('; ',$flags),implode('; ',$outcomes)));
        }
        fclose($out); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>WAEC Student Achievement | LiveCampus</title>
<?php include('links.php'); ?>
<link rel="stylesheet" href="css/waec-comparison.css?v=20261012-students">
</head>
<body class="wac-page">
<div class="header wac-no-print"><?php include('menu.php'); ?></div>
<main class="wac-wrap">
    <header class="wac-hero">
        <span class="wac-eyebrow">WAEC / Student results</span>
        <h1>Student Achievement</h1>
        <nav class="wac-actions wac-no-print" aria-label="WAEC navigation"><a href="waec-comparison.php">Batch comparison</a><a href="waec-analysis.php">Results &amp; uploads</a></nav>
    </header>
    <?php if($error){ ?><div class="wac-warning" role="alert"><?php echo wac_h($error); ?></div><?php }else{ ?>
    <section class="wac-card" aria-labelledby="student-list-heading">
        <span class="wac-eyebrow"><?php echo wac_h($title); ?></span>
        <h2 id="student-list-heading"><?php echo wac_h($labels[$filters['metric']]); ?></h2>
        <p class="wac-muted">Across this upload: <strong><?php echo $metric['achieved'].' / '.$metric['population']; ?> confirmed (<?php echo wac_rate($metric['rate']); ?>)</strong> · <?php echo $metric['not_confirmed']; ?> not confirmed.</p>
        <form method="get" class="wac-select-form wac-no-print" action="waec-achievement-students.php">
            <input type="hidden" name="upload" value="<?php echo wac_h($uploadId); ?>">
            <label>Achievement<select name="metric"><?php foreach($labels as $key=>$label){ ?><option value="<?php echo $key; ?>" <?php echo $filters['metric']===$key ? 'selected':''; ?>><?php echo wac_h($label); ?></option><?php } ?></select></label>
            <label>Student group<select name="group"><?php foreach($groups as $key=>$label){ ?><option value="<?php echo $key; ?>" <?php echo $filters['group']===$key ? 'selected':''; ?>><?php echo wac_h($label); ?></option><?php } ?></select></label>
            <label>Recorded outcome flag<select name="flag"><option value="">All outcomes</option><?php foreach($flagLabels as $key=>$label){ ?><option value="<?php echo $key; ?>" <?php echo $filters['flag']===$key ? 'selected':''; ?>><?php echo wac_h($label); ?></option><?php } ?></select></label>
            <label>Candidate number or name<input type="search" name="q" maxlength="120" value="<?php echo wac_h($filters['q']); ?>" placeholder="Search within this upload"></label>
            <div class="wac-actions"><button type="submit">Apply filters</button><a class="wac-secondary" href="<?php echo wac_h(wac_student_url($uploadId,$filters['metric'],'all')); ?>">Reset filters</a></div>
        </form>
    </section>
    <div class="wac-warning"><strong>Not confirmed does not mean failed.</strong> Flags apply to the selected criterion and can overlap. For six credits, flags cover all recorded subjects plus missing core outcomes; missing electives are unknown. A conflicting subject is not counted as a credit. * and P retain the source markers without assuming their meaning.</div>
    <section class="wac-card" aria-labelledby="matches-heading">
        <div class="wac-heading"><h2 id="matches-heading"><?php echo number_format($total); ?> matching students</h2><div class="wac-actions wac-no-print"><a class="wac-secondary" href="<?php echo wac_h($exportUrl); ?>">Export all matches (CSV)</a><button type="button" id="wac-print">Print this page</button></div></div>
        <p class="wac-muted">Group: <?php echo wac_h($groups[$filters['group']]); ?> · Flag: <?php echo wac_h($flagLabels[$filters['flag']] ?? 'All outcomes'); ?><?php if($filters['q'] !== ''){ ?> · Search: <?php echo wac_h($filters['q']); ?><?php } ?>. Page <?php echo $filters['page'].' of '.$pages; ?> · Up to 50 students per page.</p>
        <p class="wac-muted">Confidential student information. CSV includes every matching student; import the candidate-number column as text to preserve leading zeros.</p>
        <?php if(!$students){ ?><div class="wac-empty">No students match these filters. Try another group or clear the outcome flag and search.</div><?php }else{ ?>
        <div class="wac-table-scroll" tabindex="0" role="region" aria-label="Student results, scroll horizontally on small screens">
        <table class="wac-student-table"><thead><tr><th scope="col">Candidate</th><th scope="col">Achievement</th><th scope="col">Recorded outcome flags</th><th scope="col">Subject results</th></tr></thead><tbody>
        <?php foreach($pageStudents as $student){ ?>
            <tr><th scope="row"><strong><?php echo wac_h($student['number'] ?: 'No candidate number'); ?></strong><small><?php echo wac_h($student['name'] ?: 'Name not supplied'); ?></small><?php if($student['number']===''){ ?><small>Matched by name only; verify identity.</small><?php } ?><?php if($student['name_conflict']){ ?><small>Multiple names in source; verify identity.</small><?php } ?></th>
                <td><span class="wac-badge <?php echo $student['metrics'][$filters['metric']]['achieved'] ? '':'wac-badge-review'; ?>"><?php echo $student['metrics'][$filters['metric']]['achieved'] ? 'Confirmed':'Not confirmed'; ?></span><small><?php echo $student['credits']; ?> distinct A1–C6 credits overall</small></td>
                <td><?php if(!$student['flags']){ ?><small>No flags for this criterion</small><?php } foreach($student['flags'] as $key=>$unused){ ?><small><?php echo wac_h($flagLabels[$key]); ?></small><?php } ?></td>
                <td><details class="wac-evidence"><summary>View subject outcomes</summary><dl><?php foreach($student['outcomes'] as $subject=>$grades){ ?><div><dt><?php echo wac_h($subject); ?></dt><dd><?php echo wac_h(implode(' / ',array_map(function($grade){ return $grade === '' ? '(blank)' : $grade; },$grades))); ?></dd></div><?php } ?></dl></details></td></tr>
        <?php } ?>
        </tbody></table></div>
        <nav class="wac-actions wac-no-print wac-pagination" aria-label="Student pages">
        <?php if($filters['page']>1){ ?><a class="wac-secondary" href="<?php echo wac_h('waec-achievement-students.php?'.http_build_query(array_merge($baseQuery,array('page'=>$filters['page']-1)))); ?>">Previous 50</a><?php } ?>
        <?php if($filters['page']<$pages){ ?><a class="wac-secondary" href="<?php echo wac_h('waec-achievement-students.php?'.http_build_query(array_merge($baseQuery,array('page'=>$filters['page']+1)))); ?>">Next 50</a><?php } ?>
        </nav><?php } ?>
    </section>
    <?php } ?>
</main>
<script>
(function(){
    var button=document.getElementById('wac-print'), folded=[];
    if(button) button.addEventListener('click',function(){ window.print(); });
    window.addEventListener('beforeprint',function(){ folded=Array.prototype.slice.call(document.querySelectorAll('.wac-evidence:not([open])')); folded.forEach(function(d){ d.open=true; }); });
    window.addEventListener('afterprint',function(){ folded.forEach(function(d){ d.open=false; }); folded=[]; });
})();
</script>
</body>
</html>
