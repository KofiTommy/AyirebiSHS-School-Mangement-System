<?php
session_start();
require_once __DIR__.'/check-login.php';
require_once __DIR__.'/waec-comparison-utils.php';
$allowed = (($_SESSION['ACCESSLEVEL'] ?? '') === 'administrator' && in_array($_SESSION['SYSTEMTYPE'] ?? '', array('normal_user','super_user'), true))
    || (function_exists('um_is_academic_lead_user') && um_is_academic_lead_user());
if(!$allowed){
    http_response_code(403);
    exit('You do not have permission to compare WAEC results.');
}
header('Cache-Control: private, no-store');
if(empty($_SESSION['waec_batch_csrf'])) $_SESSION['waec_batch_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['waec_batch_csrf'];
$uploads = array(); $batches = array(); $warnings = array(); $errors = array(); $summary = array();
$notice = $_SESSION['waec_batch_notice'] ?? '';
unset($_SESSION['waec_batch_notice']);
$selectedValues = isset($_GET['batches']) && is_array($_GET['batches']) ? array_values($_GET['batches']) : array();
$selectedValues = array_map(function($value){ return is_string($value) ? $value : ''; }, array_slice($selectedValues, 0, 3));
$editingId = ''; $editingValues = array();
try{
    if(wac_ensure_metadata($con)){
        if(um_is_super_admin_manager()){
            $uploadRows = mysqli_query($con, 'SELECT * FROM tblwaecupload ORDER BY exam_year DESC,datetimeentry DESC');
        }else{
            $branch = trim((string)($_SESSION['BRANCHID'] ?? ''));
            $stmt = mysqli_prepare($con, 'SELECT * FROM tblwaecupload WHERE branchid=? ORDER BY exam_year DESC,datetimeentry DESC');
            mysqli_stmt_bind_param($stmt, 's', $branch);
            mysqli_stmt_execute($stmt); $uploadRows = mysqli_stmt_get_result($stmt);
        }
        while($upload = mysqli_fetch_assoc($uploadRows)){
            if(wac_accessible($upload, $_SESSION)) $uploads[$upload['uploadid']] = $upload;
        }
        if(isset($stmt)) mysqli_stmt_close($stmt);
    }
    if($_SERVER['REQUEST_METHOD'] === 'POST'){
        if(!isset($_POST['csrf']) || !is_string($_POST['csrf']) || !hash_equals($csrf, $_POST['csrf'])) throw new InvalidArgumentException('Your form expired. Refresh this page and try again.');
        $editingId = isset($_POST['uploadid']) && is_string($_POST['uploadid']) ? $_POST['uploadid'] : '';
        if(!isset($uploads[$editingId])) throw new InvalidArgumentException('This upload is unavailable or outside your branch.');
        $editingValues = array_intersect_key($_POST, array_flip(array('exam_year','exam_sitting','batch_label')));
        $metadata = wac_metadata($_POST);
        $stmt = mysqli_prepare($con, 'UPDATE tblwaecupload SET exam_year=?,exam_sitting=?,batch_label=? WHERE uploadid=? AND branchid <=> ?');
        $branch = $uploads[$editingId]['branchid'];
        mysqli_stmt_bind_param($stmt, 'issss', $metadata['exam_year'], $metadata['exam_sitting'], $metadata['batch_label'], $editingId, $branch);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        $_SESSION['waec_batch_notice'] = 'Batch details saved. The original grades and result files have not changed.';
        header('Location: waec-comparison.php#batch-library', true, 303);
        exit;
    }
    if(isset($_GET['batches']) || isset($_GET['compare']) || isset($_GET['export'])){
        $batches = wac_selection($_GET['batches'] ?? array(), $uploads);
        $fingerprints = array();
        foreach($batches as &$batch){
            $batch['analysis'] = wac_analyze(wac_load_rows($con, $batch['uploadid']));
            $analysis = $batch['analysis'];
            if($analysis['candidates'] === 0) throw new InvalidArgumentException('One selected upload contains no identifiable subject outcomes. Check the original analysis before comparing.');
            if(isset($fingerprints[$analysis['fingerprint']])) throw new InvalidArgumentException('Two selected uploads contain the same candidate and subject outcomes. Choose distinct batches, not duplicate uploads of the same results.');
            $fingerprints[$analysis['fingerprint']] = true;
            $prefix = wac_title($batch).': ';
            if($analysis['duplicates']) $warnings[] = $prefix.$analysis['duplicates'].' repeated identical rows were counted only once.';
            if($analysis['overall']['conflicts']) $warnings[] = $prefix.$analysis['overall']['conflicts'].' conflicting candidate/subject outcomes were excluded from grade calculations. Review the source results.';
            if($analysis['overall']['unknown'] || $analysis['unidentified']) $warnings[] = $prefix.'Unrecognised or unidentified records were excluded from grade calculations ('.$analysis['overall']['unknown'].' unknown outcomes; '.$analysis['unidentified'].' unidentified rows).';
            $missing = array_sum(array_map(function($subject) use ($analysis){ return $analysis['subjects'][$subject]['missing']; }, wac_core()));
            if($missing) $warnings[] = $prefix.$missing.' core-subject outcome gaps across candidates found in this upload. Missing results are not failures.';
        }
        unset($batch);
        if(count(array_unique(array_column($batches, 'branchid'))) > 1) $warnings[] = 'These batches belong to different branches. This is a cross-branch comparison, not a single-school year-on-year trend.';
        if(count(array_unique(array_map(function($b){ return strtolower(trim($b['exam_sitting'])); }, $batches))) > 1) $warnings[] = 'Examination sittings differ. Consider the different candidate groups when interpreting changes.';
        if(count(array_unique(array_column($batches, 'exam_year'))) < count($batches)) $warnings[] = 'Some batches share an examination year. Same-year batches are ordered by label; their differences are not year-on-year changes.';
        $summary = wac_summary($batches);
    }
}catch(InvalidArgumentException $e){
    $errors[] = $e->getMessage(); $batches = array();
}catch(Throwable $e){
    error_log('WAEC comparison: '.$e->getMessage());
    $errors[] = 'Batch data could not be loaded or saved. Please try again. If this continues, ask your administrator to check the database connection and schema permissions.';
    $batches = array();
}
$ready = array_filter($uploads, 'wac_ready');
$subjects = wac_core();
foreach($batches as $batch) $subjects = array_merge($subjects, array_keys($batch['analysis']['subjects']));
$subjects = array_values(array_unique($subjects));
$coreSubjects = wac_core();
usort($subjects, function($a, $b) use ($coreSubjects){
    $aCore = array_search($a, $coreSubjects, true); $bCore = array_search($b, $coreSubjects, true);
    if($aCore !== false || $bCore !== false) return ($aCore === false ? 99 : $aCore) <=> ($bCore === false ? 99 : $bCore);
    return strcmp($a, $b);
});
if(isset($_GET['export']) && $_GET['export'] === 'csv' && $batches && !$errors){
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="waec-batch-comparison.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $write = function($row) use ($out){ fputcsv($out, array_map('wac_csv_cell', $row)); };
    $write(array('WAEC batch comparison'));
    $write(array('Method', 'Credit rate = A1-C6 entries / A1-F9 graded entries x 100. Not the percentage of students passing. X, W, *, P, conflicts and unknown outcomes are excluded.'));
    $write(array('Coverage', 'Core gaps are measured against identifiable candidates in each upload, not an external registration roster. Elective missing counts are unknown. Programme breakdown is unavailable.'));
    foreach($warnings as $warning) $write(array('Warning', $warning));
    foreach($summary as $line) $write(array('Summary', $line));
    $write(array());
    $write(array('Year','Sitting','Batch','Branch ID','Upload ID','Original file','Candidates','Graded entries','Credits A1-C6','Credit rate %','Average point (lower is better)','Absent X','Withheld W','Marker *','Marker P','Conflicting outcomes','Unknown outcomes','Unidentified rows','Identical duplicate rows removed'));
    foreach($batches as $b){
        $a = $b['analysis']; $t = $a['overall'];
        $write(array($b['exam_year'],$b['exam_sitting'],$b['batch_label'],$b['branchid'],$b['uploadid'],$b['originalname'],$a['candidates'],$t['graded'],$t['credits'],$t['credit_rate'] === null ? 'N/A' : round($t['credit_rate'],4),$t['average'] === null ? 'N/A' : round($t['average'],4),$t['X'],$t['W'],$t['*'],$t['P'],$t['conflicts'],$t['unknown'],$a['unidentified'],$a['duplicates']));
    }
    $write(array());
    foreach(wac_achievement_csv_rows($batches) as $row) $write($row);
    $write(array());
    $write(array('Subject','Year','Sitting','Batch','Upload ID','Recorded candidates','Graded entries','Credits A1-C6','Credit rate %','Change vs previous batch (pp)','Absent X','Withheld W','Marker *','Marker P','Core gaps','Conflicts','Unknown outcomes'));
    foreach($subjects as $subject){
        $previous = null;
        foreach($batches as $b){
            $s = $b['analysis']['subjects'][$subject] ?? null;
            $rate = $s ? $s['credit_rate'] : null; $delta = wac_delta($previous, $rate);
            $write(array($subject,$b['exam_year'],$b['exam_sitting'],$b['batch_label'],$b['uploadid'],$s ? $s['recorded'] : 'N/A',$s ? $s['graded'] : 'N/A',$s ? $s['credits'] : 'N/A',$rate === null ? 'N/A' : round($rate,4),$delta === null ? 'N/A' : round($delta,4),$s ? $s['X'] : 'N/A',$s ? $s['W'] : 'N/A',$s ? $s['*'] : 'N/A',$s ? $s['P'] : 'N/A',$s && $s['missing'] !== null ? $s['missing'] : 'Unknown',$s ? $s['conflicts'] : 'N/A',$s ? $s['unknown'] : 'N/A'));
            $previous = $rate;
        }
    }
    $write(array()); $write(array('Grade','Batch','Count','Percentage of graded entries'));
    foreach(wac_grades() as $grade){ foreach($batches as $b){
        $t = $b['analysis']['overall'];
        $write(array($grade,wac_title($b),$t['grades'][$grade],$t['graded'] ? round(wac_percent($t['grades'][$grade],$t['graded']),4) : 'N/A'));
    } }
    fclose($out); exit;
}
$exportUrl = 'waec-comparison.php?'.http_build_query(array('batches'=>array_column($batches, 'uploadid'), 'export'=>'csv'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Compare WAEC Batches | LiveCampus</title>
<?php include('links.php'); ?>
<link rel="stylesheet" href="css/waec-comparison.css?v=20261012">
</head>
<body class="wac-page">
<div class="header wac-no-print"><?php include('menu.php'); ?></div>
<main class="wac-wrap">
    <header class="wac-hero">
        <span class="wac-eyebrow">Academic performance / Batch comparison</span>
        <h1>See the progress. Understand the gaps.</h1>
        <p>Compare two or three WAEC batches with consistent subject names, clear percentages and honest coverage checks.</p>
        <nav class="wac-actions wac-no-print" aria-label="WAEC navigation">
            <a href="waec-analysis.php"><i class="fa fa-arrow-left" aria-hidden="true"></i> Results &amp; uploads</a>
            <a href="#batch-library">Label existing uploads</a>
            <?php if($batches){ ?><a href="#student-achievement">Student achievement</a><?php } ?>
        </nav>
    </header>
    <?php if($notice){ ?><div class="wac-notice wac-no-print" role="status"><?php echo wac_h($notice); ?></div><?php } ?>
    <?php foreach($errors as $error){ ?><div class="wac-warning" role="alert"><?php echo wac_h($error); ?></div><?php } ?>

    <section class="wac-card wac-no-print" aria-labelledby="choose-heading">
        <div class="wac-heading"><div><span class="wac-eyebrow">01 / Choose your batches</span><h2 id="choose-heading">Compare examination results</h2></div><span class="wac-badge"><?php echo count($ready); ?> labelled uploads</span></div>
        <p class="wac-muted">Results are ordered by examination year, oldest first. The third batch is optional.</p>
        <form method="get" action="waec-comparison.php#comparison" class="wac-select-form">
            <?php for($i=0; $i<3; $i++){ ?>
            <label>Batch <?php echo $i+1; ?><?php echo $i === 2 ? ' (optional)' : ''; ?>
                <select name="batches[]" <?php echo $i < 2 ? 'required' : ''; ?>>
                    <option value=""><?php echo $i === 2 ? 'No third batch' : 'Select a labelled upload'; ?></option>
                    <?php foreach($ready as $upload){ ?>
                    <option value="<?php echo wac_h($upload['uploadid']); ?>" <?php echo ($selectedValues[$i] ?? '') === $upload['uploadid'] ? 'selected' : ''; ?>><?php echo wac_h(wac_title($upload).' · '.substr($upload['uploadid'], -8)); ?></option>
                    <?php } ?>
                </select>
            </label>
            <?php } ?>
            <div class="wac-actions"><button type="submit" name="compare" value="1" <?php echo count($ready) < 2 ? 'disabled' : ''; ?>><i class="fa fa-bar-chart" aria-hidden="true"></i> Compare batches</button><a class="wac-secondary" href="waec-comparison.php">Clear selection</a></div>
        </form>
        <?php if(count($ready) < 2){ ?>
        <div class="wac-empty"><i class="fa fa-clone" aria-hidden="true"></i><div><h3>Your comparison workspace is ready</h3><p>Upload results for at least two distinct batches, then confirm their year, sitting and label. Existing uploads can be labelled below. No sample results are mixed into your school data.</p><a href="waec-analysis.php#waec-upload">Upload another results file &rarr;</a></div></div>
        <?php } ?>
    </section>

    <?php if($batches){ ?>
    <div id="comparison">
        <section class="wac-card">
            <div class="wac-heading"><div><span class="wac-eyebrow">02 / Performance snapshot</span><h2>Batch comparison report</h2></div><div class="wac-actions wac-no-print"><a class="wac-secondary" href="<?php echo wac_h($exportUrl); ?>">Download CSV</a><button type="button" id="wac-print"><i class="fa fa-print" aria-hidden="true"></i> Print / Save PDF</button></div></div>
            <p class="wac-muted">Overall credit rate measures subject entries, not students passing. Subject mix and the proportion of ungraded outcomes can differ between batches; use the subject-level comparison alongside it.</p>
            <div class="wac-batch-grid">
                <?php foreach($batches as $i => $b){ $a=$b['analysis']; $t=$a['overall']; ?>
                <article class="wac-batch wac-series-<?php echo $i; ?>">
                    <span class="wac-badge"><?php echo wac_h($b['exam_year']); ?></span><h3><?php echo wac_h($b['batch_label']); ?></h3><p><?php echo wac_h($b['exam_sitting']); ?></p>
                    <strong class="wac-big-rate"><?php echo wac_rate($t['credit_rate']); ?></strong><span class="wac-muted">A1–C6 credit rate</span>
                    <dl><div><dt>Candidates in upload</dt><dd><?php echo number_format($a['candidates']); ?></dd></div><div><dt>Credit / graded entries</dt><dd><?php echo number_format($t['credits']).' / '.number_format($t['graded']); ?></dd></div><div><dt>Average grade point</dt><dd><?php echo $t['average'] === null ? 'N/A' : number_format($t['average'], 2); ?></dd></div></dl>
                    <small>Average points: lower is better (A1 = 1, F9 = 9).<br>Source: <?php echo wac_h($b['originalname']); ?><br>Branch: <?php echo wac_h($b['branchid'] ?: 'Unassigned'); ?> · Upload: <?php echo wac_h($b['uploadid']); ?></small>
                </article>
                <?php } ?>
            </div>
        </section>
        <section class="wac-card">
            <span class="wac-eyebrow">What changed</span><h2>Performance summary</h2>
            <ul class="wac-insights"><?php foreach($summary as $line){ ?><li><?php echo wac_h($line); ?></li><?php } ?></ul>
            <p class="wac-muted">Changes are percentage points (pp), not relative percentage growth. These are descriptive calculations, not explanations of why results changed or admission decisions.</p>
            <?php if($warnings){ ?><div class="wac-warning"><h3>Read these comparisons with care</h3><ul><?php foreach($warnings as $warning){ ?><li><?php echo wac_h($warning); ?></li><?php } ?></ul></div><?php } ?>
        </section>
        <section class="wac-card" id="student-achievement" aria-labelledby="achievement-heading">
            <span class="wac-eyebrow">03 / Student achievement</span><h2 id="achievement-heading">How many students achieved the milestones?</h2>
            <p class="wac-muted">These percentages count <strong>students, not subject entries</strong>: confirmed students / all identifiable candidates in each upload, including candidates with only status records. Each student counts once per measure. This is not an admission assessment.</p>
            <div class="wac-batch-grid">
            <?php foreach($batches as $i => $b){ $achievement=$b['analysis']['achievement']; $six=$achievement['metrics']['six_credits']; $coverage=$achievement['coverage']; ?>
                <article class="wac-batch wac-series-<?php echo $i; ?>">
                    <span class="wac-badge"><?php echo wac_h($b['exam_year']); ?></span><h3><?php echo wac_h($b['batch_label']); ?></h3><p><?php echo wac_h($b['exam_sitting']); ?></p>
                    <strong class="wac-big-rate"><?php echo wac_rate($six['rate']); ?></strong><span class="wac-muted">Students with at least six credits</span>
                    <p class="wac-achievement-count"><a href="<?php echo wac_h(wac_student_url($b['uploadid'])); ?>"><?php echo number_format($six['achieved']).' / '.number_format($six['population']); ?> students confirmed</a></p>
                    <dl><div><dt>All four core outcomes graded</dt><dd><?php echo $coverage['fully_graded_core']; ?></dd></div><div><dt>Missing at least one core outcome</dt><dd><?php echo $coverage['missing_core']; ?></dd></div><div><dt>At least one ungraded/conflicting core</dt><dd><?php echo $coverage['ungraded_core']; ?></dd></div></dl>
                    <small>Coverage figures count students, not result rows. Missing and ungraded groups may overlap. Ungraded includes X, W, *, P, unknown codes and conflicts.</small>
                </article>
            <?php } ?>
            </div>
            <p class="wac-muted wac-no-print">Select a confirmed or not-confirmed count below to view, search and export the students behind that figure.</p>
            <div class="wac-table-scroll" tabindex="0" role="region" aria-label="Student achievement comparison table">
                <table><caption>Confirmed student achievements · credits are A1–C6</caption><thead><tr><th scope="col">Milestone</th><?php foreach($batches as $b){ ?><th scope="col"><?php echo wac_h(wac_title($b)); ?></th><?php } ?><th scope="col">First → last</th></tr></thead><tbody>
                <?php foreach(wac_achievement_labels() as $metricKey => $label){ $previous=null; $firstRate=null; $lastRate=null; ?>
                    <tr><th scope="row"><?php echo wac_h($label); ?></th>
                    <?php foreach($batches as $i => $b){ $metric=$b['analysis']['achievement']['metrics'][$metricKey]; if($i===0) $firstRate=$metric['rate']; $lastRate=$metric['rate']; ?>
                    <td><strong><?php echo wac_rate($metric['rate']); ?></strong><small><a href="<?php echo wac_h(wac_student_url($b['uploadid'],$metricKey,'confirmed')); ?>"><?php echo $metric['achieved'].' / '.$metric['population']; ?> students confirmed</a></small><small><a href="<?php echo wac_h(wac_student_url($b['uploadid'],$metricKey,'not_confirmed')); ?>"><?php echo $metric['not_confirmed']; ?> not confirmed</a></small><?php if($i>0){ ?><small><?php echo wac_delta_text(wac_delta($previous,$metric['rate'])); ?> vs previous</small><?php } ?></td>
                    <?php $previous=$metric['rate']; } ?>
                    <td><?php echo wac_delta_text(wac_delta($firstRate,$lastRate)); ?></td></tr>
                <?php } ?>
                </tbody></table>
            </div>
            <p class="wac-muted wac-achievement-note"><strong>Not confirmed does not mean failed.</strong> It includes below-credit grades, absence and missing, withheld, unknown or conflicting outcomes. Six credits means six distinct subjects with confirmed A1–C6 grades; it does not require a particular core/elective combination. All four cores means English, Core Mathematics, Integrated Science and Social Studies. A student may appear in several milestone rows; do not add them together.</p>
            <details class="wac-achievement-detail">
                <summary>View the number of credits recorded per student</summary>
                <p class="wac-muted">Each student appears in one bucket. Zero means no confirmed credits in the imported records, not necessarily that the student failed every subject. Missing electives cannot be identified without a registration roster.</p>
                <div class="wac-table-scroll" tabindex="0" role="region" aria-label="Credits per student comparison table"><table><caption>Observed credit counts per student</caption><thead><tr><th scope="col">Confirmed credits</th><?php foreach($batches as $b){ ?><th scope="col"><?php echo wac_h(wac_title($b)); ?></th><?php } ?></tr></thead><tbody>
                <?php foreach(array('0','1','2','3','4','5','6','7','8+') as $bucket){ ?><tr><th scope="row"><?php echo $bucket; ?></th><?php foreach($batches as $b){ $a=$b['analysis']['achievement']; $count=$a['credit_buckets'][$bucket]; ?><td><?php echo number_format($count); ?> students<small><?php echo wac_rate(wac_percent($count,$a['population'])); ?> of <?php echo $a['population']; ?></small></td><?php } ?></tr><?php } ?>
                </tbody></table></div>
            </details>
        </section>
        <section class="wac-card">
            <span class="wac-eyebrow">04 / Core subjects</span><h2>Core credit rates at a glance</h2><p class="wac-muted">These bars return to <strong>subject-entry</strong> credit rates (credits / graded entries), unlike the student percentages above. Every bar uses the same 0–100% scale. N/A means no usable grades, not a zero score.</p>
            <div class="wac-charts">
            <?php foreach(wac_core() as $subject){ ?>
                <article class="wac-chart"><h3><?php echo wac_h($subject); ?></h3>
                <?php foreach($batches as $i => $b){ $s=$b['analysis']['subjects'][$subject]; ?>
                    <div class="wac-chart-row wac-series-<?php echo $i; ?>"><div class="wac-chart-label"><span><?php echo wac_h($b['exam_year'].' · '.$b['batch_label']); ?></span><strong><?php echo wac_rate($s['credit_rate']); ?></strong></div><div class="wac-track" aria-hidden="true"><span style="width:<?php echo $s['credit_rate'] === null ? 0 : round($s['credit_rate'], 3); ?>%"></span></div><small><?php echo $s['credits'].' credits / '.$s['graded'].' graded'; ?></small></div>
                <?php } ?>
                </article>
            <?php } ?>
            </div>
        </section>
        <section class="wac-card">
            <span class="wac-eyebrow">05 / Subject detail</span><h2>Compare every subject</h2>
            <p class="wac-muted">Each cell shows credit rate, credits / graded entries, and change from the previous displayed batch. The final column compares the first and last displayed batches.</p>
            <div class="wac-table-scroll" tabindex="0" role="region" aria-label="Subject comparison table">
            <table><caption>Subject credit rates · A1–C6 / A1–F9</caption><thead><tr><th scope="col">Subject</th><?php foreach($batches as $b){ ?><th scope="col"><?php echo wac_h(wac_title($b)); ?></th><?php } ?><th scope="col">First → last</th></tr></thead><tbody>
            <?php foreach($subjects as $subject){ $previous=null; $first=null; $last=null; ?>
                <tr><th scope="row"><?php echo wac_h($subject); ?><?php if(in_array($subject,wac_core(),true)){ ?><small>Core subject</small><?php } ?></th>
                <?php foreach($batches as $i => $b){ $s=$b['analysis']['subjects'][$subject] ?? null; $rate=$s ? $s['credit_rate'] : null; if($i===0) $first=$rate; $last=$rate; ?>
                <td><strong><?php echo wac_rate($rate); ?></strong><small><?php echo $s && $s['recorded'] ? $s['credits'].' / '.$s['graded'].' graded entries' : 'No outcome recorded'; ?></small><?php if($i>0){ ?><small><?php echo wac_delta_text(wac_delta($previous,$rate)); ?> vs previous</small><?php } ?></td>
                <?php $previous=$rate; } ?>
                <td><?php echo wac_delta_text(wac_delta($first,$last)); ?></td></tr>
            <?php } ?>
            </tbody></table></div>
        </section>
        <section class="wac-card">
            <h2>Grade distribution</h2><p class="wac-muted">Counts and percentages use graded subject entries only, not candidate counts. D7–F9 are below credit.</p>
            <div class="wac-table-scroll" tabindex="0" role="region" aria-label="Grade distribution table"><table><caption>A1–F9 distributions</caption><thead><tr><th scope="col">Grade</th><?php foreach($batches as $b){ ?><th scope="col"><?php echo wac_h(wac_title($b)); ?></th><?php } ?></tr></thead><tbody>
            <?php foreach(wac_grades() as $grade){ ?><tr><th scope="row"><?php echo $grade; ?></th><?php foreach($batches as $b){ $t=$b['analysis']['overall']; ?><td><?php echo number_format($t['grades'][$grade]); ?><small><?php echo wac_rate(wac_percent($t['grades'][$grade],$t['graded'])); ?></small></td><?php } ?></tr><?php } ?>
            </tbody></table></div>
        </section>
        <section class="wac-card">
            <h2>Coverage &amp; ungraded outcomes</h2><p class="wac-muted">X = absent; W = withheld. * and P are retained exactly as source markers, without assigning a grade. Conflicts and unknown outcomes are excluded from rates.</p>
            <div class="wac-table-scroll" tabindex="0" role="region" aria-label="Coverage comparison table"><table><caption>Subject coverage by batch</caption><thead><tr><th scope="col">Subject</th><?php foreach($batches as $b){ ?><th scope="col"><?php echo wac_h(wac_title($b)); ?></th><?php } ?></tr></thead><tbody>
            <?php foreach($subjects as $subject){ ?><tr><th scope="row"><?php echo wac_h($subject); ?></th><?php foreach($batches as $b){ $s=$b['analysis']['subjects'][$subject] ?? null; ?><td><?php if($s){ ?>
                <strong><?php echo $s['recorded']; ?> recorded</strong><small>Graded: <?php echo $s['graded']; ?> · X: <?php echo $s['X']; ?> · W: <?php echo $s['W']; ?></small><small>*: <?php echo $s['*']; ?> · P: <?php echo $s['P']; ?> · Conflicts: <?php echo $s['conflicts']; ?> · Unknown: <?php echo $s['unknown']; ?></small><small><?php echo $s['missing'] === null ? 'Elective gaps: unknown' : 'Core outcome gaps: '.$s['missing']; ?></small>
            <?php }else{ ?>No outcome recorded<small>Elective registration unknown</small><?php } ?></td><?php } ?></tr><?php } ?>
            </tbody></table></div>
        </section>
        <section class="wac-card wac-method"><h2>How to interpret this report</h2><ul>
            <li>Credit rate = number of A1–C6 outcomes divided by A1–F9 graded outcomes × 100. Absence, withheld results, source markers and missing records never become F9.</li>
            <li>Candidate totals include identifiable candidates with grades or statuses. Core gaps use those candidates as the expected population. Students entirely absent from the upload cannot be detected without a registration roster.</li>
            <li>Student achievement percentages use all identifiable candidates as the denominator, not just those with grades. Only unambiguous A1–C6 outcomes count as confirmed credits. Incomplete records can understate achievements. The remaining students are not automatically failures.</li>
            <li>Elective registration is not available, so an unrecorded elective is not assumed to be a missing result. Programme and gender comparisons are not included in this first version.</li>
            <li>Equivalent subject names use the same alias rules as the importer. Exact duplicate outcomes count once; conflicting outcomes are excluded and flagged.</li>
            <li>A small batch or a batch with many ungraded results can have a misleadingly high rate. Compare denominators, coverage, sitting and subject mix before drawing conclusions.</li>
        </ul></section>
    </div>
    <?php } ?>

    <section class="wac-card wac-no-print" id="batch-library">
        <div class="wac-heading"><div><span class="wac-eyebrow">Batch library</span><h2>Give every upload the right context</h2></div><a class="wac-secondary" href="waec-analysis.php#waec-upload">Upload results</a></div>
        <p class="wac-muted">Confirm the examination year, sitting and batch label from your original results. Upload dates are not examination years. Editing these labels does not change grades.</p>
        <?php if(!$uploads){ ?><p>No WAEC uploads are available in your branch yet.</p><?php } ?>
        <div class="wac-library">
        <?php foreach($uploads as $upload){ $isEditing=$editingId===$upload['uploadid']; $values=$isEditing ? array_merge($upload,$editingValues) : $upload; ?>
        <details <?php echo !wac_ready($upload) || $isEditing ? 'open' : ''; ?>>
            <summary><span><?php echo wac_h($upload['originalname']); ?><small><?php echo wac_h(wac_ready($upload) ? wac_title($upload) : 'Needs year, sitting and batch label'); ?> · <?php echo wac_h(substr($upload['uploadid'],-8)); ?></small></span><span class="wac-badge"><?php echo wac_ready($upload) ? 'Labelled' : 'Set details'; ?></span></summary>
            <form method="post" action="waec-comparison.php#batch-library" class="wac-select-form">
                <input type="hidden" name="csrf" value="<?php echo wac_h($csrf); ?>"><input type="hidden" name="uploadid" value="<?php echo wac_h($upload['uploadid']); ?>">
                <label>Examination year<input type="number" name="exam_year" min="1950" max="2100" placeholder="e.g. 2025" value="<?php echo wac_h(is_scalar($values['exam_year'] ?? null) ? $values['exam_year'] : ''); ?>" required></label>
                <label>Examination sitting<input type="text" name="exam_sitting" maxlength="80" placeholder="e.g. School candidates" value="<?php echo wac_h(is_scalar($values['exam_sitting'] ?? null) ? $values['exam_sitting'] : ''); ?>" required></label>
                <label>Batch label<input type="text" name="batch_label" maxlength="120" placeholder="e.g. 2025 graduating class" value="<?php echo wac_h(is_scalar($values['batch_label'] ?? null) ? $values['batch_label'] : ''); ?>" required></label>
                <div class="wac-actions"><button type="submit">Save batch details</button><small>Branch: <?php echo wac_h($upload['branchid'] ?: 'Unassigned'); ?> · Uploaded: <?php echo wac_h($upload['datetimeentry']); ?></small></div>
            </form>
        </details>
        <?php } ?>
        </div>
    </section>
</main>
<script>
(function(){
    var printButton = document.getElementById('wac-print');
    if(printButton){ printButton.addEventListener('click', function(){ window.print(); }); }
    var folded = [];
    window.addEventListener('beforeprint', function(){
        folded = Array.prototype.slice.call(document.querySelectorAll('#student-achievement details:not([open])'));
        folded.forEach(function(detail){ detail.open = true; });
    });
    window.addEventListener('afterprint', function(){
        folded.forEach(function(detail){ detail.open = false; });
        folded = [];
    });
})();
</script>
</body>
</html>
