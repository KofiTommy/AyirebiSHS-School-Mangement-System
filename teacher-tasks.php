<?php
session_start();
require_once('check-login.php');
require_once('task-scheduler-utils.php');
task_scheduler_ensure_tables($con);
if(!(isset($_SESSION['ACCESSLEVEL'], $_SESSION['SYSTEMTYPE']) && $_SESSION['ACCESSLEVEL']==='user' && $_SESSION['SYSTEMTYPE']==='Teacher')){ header('location:index.php'); exit(); }
function tsk_redirect(){ header('location:teacher-tasks.php'); exit(); }
$teacherId = (string)$_SESSION['USERID'];
$teacherEsc = mysqli_real_escape_string($con, $teacherId);
if(empty($_SESSION['teacher_task_csrf'])){ $_SESSION['teacher_task_csrf'] = bin2hex(random_bytes(24)); }
$flash = isset($_SESSION['teacher_task_flash']) ? $_SESSION['teacher_task_flash'] : '';
unset($_SESSION['teacher_task_flash']);

$assignments = array();
$assignmentLookup = array();
$assignmentSql = "SELECT sa.assignmentid,sa.classid,sa.classificationid,sa.batchid,sa.termname,ce.class_name,bh.batch,sub.subject
    FROM tblsubjectassignment sa
    INNER JOIN tblclassentry ce ON ce.class_entryid=sa.classid
    INNER JOIN tblbatch bh ON bh.batchid=sa.batchid
    INNER JOIN tblsubjectclassification sc ON sc.classificationid=sa.classificationid
    INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
    WHERE sa.userid='$teacherEsc' AND sa.status='active' AND bh.status='active'
    ORDER BY bh.datetimeentry DESC,sa.termname DESC,ce.class_name ASC,sub.subject ASC";
$assignmentRes = mysqli_query($con, $assignmentSql);
if($assignmentRes){ while($assignment = mysqli_fetch_array($assignmentRes, MYSQLI_ASSOC)){ $assignments[]=$assignment; $assignmentLookup[$assignment['assignmentid']]=$assignment; } }

if($_SERVER['REQUEST_METHOD']==='POST' && hash_equals($_SESSION['teacher_task_csrf'], $_POST['csrf'] ?? '')){
    $action = $_POST['action'] ?? '';
    if($action==='create'){
        $assignmentId = trim((string)($_POST['assignmentid'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $instructions = trim((string)($_POST['instructions'] ?? ''));
        $taskType = trim((string)($_POST['tasktype'] ?? 'Homework'));
        $dueRaw = trim((string)($_POST['dueat'] ?? ''));
        $publishRaw = trim((string)($_POST['publishat'] ?? ''));
        $allowedTypes = array('Homework','Classwork','Project','Research','Revision','Other');
        if(!isset($assignmentLookup[$assignmentId]) || $title==='' || strtotime($dueRaw)===false){
            $_SESSION['teacher_task_flash'] = array('error','Please choose one of your assigned subjects, give the task a title, and set a valid deadline.');
        }elseif(strlen($title)>160 || !in_array($taskType,$allowedTypes,true)){
            $_SESSION['teacher_task_flash'] = array('error','Please use a shorter title and a valid task type.');
        }else{
            $dueAt = date('Y-m-d H:i:s', strtotime($dueRaw));
            $publishAt = strtotime($publishRaw) ? date('Y-m-d H:i:s', strtotime($publishRaw)) : date('Y-m-d H:i:s');
            if(strtotime($dueAt) <= strtotime($publishAt)){
                $_SESSION['teacher_task_flash'] = array('error','The deadline must be later than the publish time.');
            }else{
                $a=$assignmentLookup[$assignmentId];
                $stmt=mysqli_prepare($con,'INSERT INTO tblteachertask(teacherid,assignmentid,classid,classificationid,batchid,termname,title,instructions,tasktype,dueat,publishat,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,\'published\')');
                mysqli_stmt_bind_param($stmt,'sssssisssss',$teacherId,$a['assignmentid'],$a['classid'],$a['classificationid'],$a['batchid'],$a['termname'],$title,$instructions,$taskType,$dueAt,$publishAt);
                $ok=mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
                $_SESSION['teacher_task_flash']=$ok ? array('success','Task scheduled. Students will see it at the chosen publish time.') : array('error','The task could not be scheduled. Please try again.');
            }
        }
    }elseif($action==='close'){
        $taskId=(int)($_POST['taskid'] ?? 0);
        $stmt=mysqli_prepare($con,"UPDATE tblteachertask SET status='closed' WHERE taskid=? AND teacherid=? AND status='published'"); mysqli_stmt_bind_param($stmt,'is',$taskId,$teacherId); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        $_SESSION['teacher_task_flash']=array('success','Task closed. Students can still see their submission history.');
    }elseif($action==='reopen'){
        $taskId=(int)($_POST['taskid'] ?? 0);
        $stmt=mysqli_prepare($con,"UPDATE tblteachertask SET status='published' WHERE taskid=? AND teacherid=? AND status='closed'"); mysqli_stmt_bind_param($stmt,'is',$taskId,$teacherId); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        $_SESSION['teacher_task_flash']=array('success','Task reopened for students.');
    }
    tsk_redirect();
}
$tasks=array();
$taskRes=mysqli_query($con,"SELECT t.*,ce.class_name,bh.batch,sub.subject,COUNT(s.submissionid) AS submission_count,SUM(CASE WHEN s.status='late' THEN 1 ELSE 0 END) AS late_count
    FROM tblteachertask t INNER JOIN tblclassentry ce ON ce.class_entryid=t.classid LEFT JOIN tblbatch bh ON bh.batchid=t.batchid
    INNER JOIN tblsubjectclassification sc ON sc.classificationid=t.classificationid INNER JOIN tblsubject sub ON sub.subjectid=sc.subjectid
    LEFT JOIN tblteachertasksubmission s ON s.taskid=t.taskid WHERE t.teacherid='$teacherEsc'
    GROUP BY t.taskid ORDER BY (t.status='published') DESC,t.dueat ASC,t.createdat DESC LIMIT 80");
if($taskRes){while($row=mysqli_fetch_array($taskRes,MYSQLI_ASSOC)){$tasks[]=$row;}}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Task Scheduler</title><?php include('links.php'); ?><link rel="stylesheet" href="css/task-scheduler.css"></head><body class="task-page"><main class="task-shell">
<a class="task-back" href="teacher-page.php"><i class="fa fa-arrow-left"></i> Teacher dashboard</a>
<section class="task-hero"><div><span>Teaching planner</span><h1>Task Scheduler</h1><p>Set meaningful work, choose exactly when it is due, and keep an eye on every response.</p></div><i class="fa fa-calendar-check-o"></i></section>
<?php if($flash){ ?><div class="task-flash task-flash--<?php echo task_scheduler_escape($flash[0]); ?>"><?php echo task_scheduler_escape($flash[1]); ?></div><?php } ?>
<section class="task-create"><div class="task-section-heading"><div><span>New task</span><h2>Give your class a clear deadline</h2></div><p>Only students in the selected class can see this task.</p></div>
<?php if(count($assignments)>0){ ?><form method="post" class="task-form"><input type="hidden" name="csrf" value="<?php echo task_scheduler_escape($_SESSION['teacher_task_csrf']); ?>"><input type="hidden" name="action" value="create"><label>Class and subject<select name="assignmentid" required><option value="">Choose class and subject</option><?php foreach($assignments as $a){ ?><option value="<?php echo task_scheduler_escape($a['assignmentid']); ?>"><?php echo task_scheduler_escape($a['class_name'].' · '.$a['subject'].' · '.$a['batch'].' · Semester '.$a['termname']); ?></option><?php } ?></select></label><label>Task title<input name="title" maxlength="160" required placeholder="e.g. Food preservation research"></label><label>Task type<select name="tasktype"><option>Homework</option><option>Classwork</option><option>Project</option><option>Research</option><option>Revision</option><option>Other</option></select></label><label class="task-form__wide">Instructions<textarea name="instructions" placeholder="What should students complete? Include any useful links, textbook pages, or success criteria."></textarea></label><label>Publish to students<input type="datetime-local" name="publishat" value="<?php echo date('Y-m-d\TH:i'); ?>"></label><label>Due date and time<input type="datetime-local" name="dueat" min="<?php echo date('Y-m-d\TH:i'); ?>" required></label><button class="task-primary" type="submit"><i class="fa fa-paper-plane"></i> Schedule task</button></form><?php }else{ ?><div class="task-empty">You need an active subject assignment before you can schedule work. Ask the administrator to assign your subject and class.</div><?php } ?></section>
<section class="task-list"><div class="task-section-heading"><div><span>Task board</span><h2>Your scheduled work</h2></div><p><?php echo count($tasks); ?> task<?php echo count($tasks)===1?'':'s'; ?> in your board</p></div><?php if(count($tasks)>0){ ?><div class="task-grid"><?php foreach($tasks as $task){ $isOverdue=strtotime($task['dueat'])<time() && $task['status']==='published'; ?><article class="task-card <?php echo $isOverdue?'task-card--overdue':''; ?>"><div class="task-card__top"><span class="task-pill"><?php echo task_scheduler_escape($task['tasktype']); ?></span><span class="task-state task-state--<?php echo task_scheduler_escape($task['status']); ?>"><?php echo task_scheduler_escape(ucfirst($task['status'])); ?></span></div><h3><?php echo task_scheduler_escape($task['title']); ?></h3><p class="task-card__context"><?php echo task_scheduler_escape($task['subject'].' · '.$task['class_name']); ?></p><?php if(trim($task['instructions'])!==''){ ?><p class="task-card__instructions"><?php echo nl2br(task_scheduler_escape($task['instructions'])); ?></p><?php } ?><div class="task-deadline"><i class="fa fa-clock-o"></i><div><span>Due</span><strong><?php echo task_scheduler_escape(task_scheduler_due_label($task['dueat'])); ?></strong></div></div><div class="task-card__stats"><span><b><?php echo (int)$task['submission_count']; ?></b> submitted</span><span><b><?php echo (int)$task['late_count']; ?></b> late</span></div><form method="post"><input type="hidden" name="csrf" value="<?php echo task_scheduler_escape($_SESSION['teacher_task_csrf']); ?>"><input type="hidden" name="taskid" value="<?php echo (int)$task['taskid']; ?>"><?php if($task['status']==='published'){ ?><button class="task-secondary" name="action" value="close">Close task</button><?php }elseif($task['status']==='closed'){ ?><button class="task-secondary" name="action" value="reopen">Reopen task</button><?php } ?></form></article><?php } ?></div><?php }else{ ?><div class="task-empty">Your task board is ready. Schedule your first task above.</div><?php } ?></section></main></body></html>
