<?php if(!isset($progressMode) || !function_exists('cra_safe')) { return; } ?>
<div class="cr-panel__header"><div><span class="cr-panel__eyebrow">Registration monitoring</span><h2>Class Progress</h2></div></div>
<form method="get" action="course-registration-admin.php" class="cr-progress-filters">
    <input type="hidden" name="classid" value="<?php echo cra_safe($selectedClassId); ?>">
    <label>Show<select name="progressview"><option value="recent" <?php echo $progressMode==='recent'?'selected':''; ?>>Recent windows</option><option value="all" <?php echo $progressMode==='all'?'selected':''; ?>>All classes</option></select></label>
    <label>Batch<select name="batchid"><option value="">Select batch</option><?php foreach($batchOptions as $b){ ?><option value="<?php echo cra_safe($b['batchid']); ?>" <?php echo $selectedBatchId===$b['batchid']?'selected':''; ?>><?php echo cra_safe($b['batch']); ?></option><?php } ?></select></label>
    <label>Academic year<select name="academicyear"><option value="">Select year</option><?php foreach(course_registration_year_options() as $y){ ?><option value="<?php echo cra_safe($y); ?>" <?php echo $selectedAcademicYear===$y?'selected':''; ?>><?php echo cra_safe($y); ?></option><?php } ?></select></label>
    <label>Semester<select name="termname"><option value="">Select semester</option><option value="1" <?php echo $selectedTermName==='1'?'selected':''; ?>>Semester 1</option><option value="2" <?php echo $selectedTermName==='2'?'selected':''; ?>>Semester 2</option></select></label>
    <button type="submit" class="button-show">Load progress</button>
</form>
<?php if($progressMode==='all'){ ?>
<p class="cr-progress-help">Only classes with active students enrolled in the selected batch, year and semester are shown. Classes without a registration window still appear as Not set up. Reload to see new submissions.</p>
<?php if(!$progressReady){ ?><p class="cra-alert cra-alert--info">Choose a batch, academic year and semester, then click Load progress to view all classes.</p>
<?php } elseif(!$allClassProgress){ ?><p>No classes have active student enrolments for this batch, academic year and semester. Check your filters and semester registrations.</p>
<?php } else {
    $progressTotal=array_sum(array_column($allClassProgress,'eligible_count'));
    $progressSubmitted=array_sum(array_column($allClassProgress,'submitted_count'));
    $progressPending=$progressTotal-$progressSubmitted;
    $progressPercent=$progressTotal ? round(100*$progressSubmitted/$progressTotal,1) : 0;
?>
<div class="cr-progress-summary" aria-label="Selected session registration totals">
    <article><span>Total students</span><strong><?php echo $progressTotal; ?></strong><small>Eligible class enrolments</small></article>
    <article data-tone="complete"><span>Registered</span><strong><?php echo $progressSubmitted; ?></strong><small>Registration submitted</small></article>
    <article data-tone="pending"><span>Pending</span><strong><?php echo $progressPending; ?></strong><small>Still to register</small></article>
    <article><span>Completion rate</span><strong><?php echo $progressPercent; ?>%</strong><small>Across the selected session</small></article>
</div>
<div class="cr-progress-tools" hidden>
    <label for="cr-progress-search">Find a class</label>
    <input type="search" id="cr-progress-search" placeholder="Search by class name" autocomplete="off" aria-controls="cr-progress-cards">
    <div class="cr-progress-tabs" role="group" aria-label="Filter classes by registration progress">
        <button type="button" data-progress-filter="all" aria-pressed="true">All</button>
        <button type="button" data-progress-filter="pending" aria-pressed="false">Pending</button>
        <button type="button" data-progress-filter="complete" aria-pressed="false">Completed</button>
    </div>
    <p id="cr-progress-matches" role="status" aria-live="polite"></p>
    <small>Summary totals remain for the full selected session.</small>
</div>
<p class="cr-progress-help"><?php echo count($allClassProgress); ?> classes · <?php echo array_sum(array_column($allClassProgress,'submitted_count')); ?> registered / <?php echo array_sum(array_column($allClassProgress,'eligible_count')); ?> eligible class enrolments</p>
<div class="cr-progress-list" id="cr-progress-cards" aria-label="All class registration progress">
<?php foreach($allClassProgress as $p){
    $progressTone=$p['eligible_count']>0 && $p['outstanding_count']===0 ? 'complete' : ($p['submitted_count']>0 ? 'pending' : 'not-started');
    $progressLabel=$progressTone==='complete' ? 'Complete' : ($progressTone==='pending' ? 'In progress' : 'Not started');
?>
<article class="cr-progress-card" data-tone="<?php echo $progressTone; ?>" data-class-name="<?php echo cra_safe($p['class_name']); ?>" data-progress-state="<?php echo $progressTone==='complete'?'complete':'pending'; ?>">
    <div class="cr-progress-card-heading">
        <h3><?php echo cra_safe($p['class_name']); ?></h3>
        <span class="cr-progress-state"><?php echo $progressLabel; ?></span>
    </div>
    <div class="cr-progress-card-details">
    <span class="cr-pill"><?php echo $p['windowid'] ? cra_safe(course_registration_window_status_label($p)) : 'Not set up'; ?></span>
    <p><strong><?php echo $p['submitted_count']; ?> / <?php echo $p['eligible_count']; ?></strong> registered · <?php echo $p['outstanding_count']; ?> pending</p>
    </div>
    <progress max="100" value="<?php echo $p['progress_percent']; ?>" aria-label="<?php echo cra_safe($p['class_name'].' registration progress'); ?>"><?php echo $p['progress_percent']; ?>%</progress>
    <div class="cr-progress-footer"><span><?php echo $p['eligible_count'] ? $p['progress_percent'].'% complete' : 'No eligible students'; ?></span><a class="cr-secondary-button" href="<?php echo cra_safe(cra_scope_link($p['classid'],$selectedBatchId,$selectedAcademicYear,$selectedTermName).'&progressview=all'); ?>">Load class</a></div>
</article>
<?php } ?></div><p class="cr-progress-empty" id="cr-progress-empty" hidden>No classes match these filters. Try another class name or choose All.</p><?php } } ?>
