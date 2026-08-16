<?php
/* Run from a server cron every 15 minutes. It is deliberately not web-accessible. */
if(PHP_SAPI !== 'cli'){
    http_response_code(403);
    exit('This task can only run from the server scheduler.');
}

require_once __DIR__.DIRECTORY_SEPARATOR.'dbstring.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'lesson-timetable-utils.php';

ensure_lesson_timetable_table($con);
@mysqli_query($con, "CREATE TABLE IF NOT EXISTS tbllessonnotificationlog (
    lessonid VARCHAR(40) NOT NULL, reminderdate DATE NOT NULL, userid VARCHAR(30) NOT NULL,
    createdat DATETIME NOT NULL, PRIMARY KEY(lessonid,reminderdate,userid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$now = new DateTimeImmutable('now');
$weekday = $now->format('l');
$today = $now->format('Y-m-d');
$windowStart = $now->format('H:i:s');
$windowEnd = $now->modify('+15 minutes')->format('H:i:s');
$weekdayEsc = mysqli_real_escape_string($con, $weekday);
$startEsc = mysqli_real_escape_string($con, $windowStart);
$endEsc = mysqli_real_escape_string($con, $windowEnd);
$todayEsc = mysqli_real_escape_string($con, $today);

$sql = "SELECT lt.lessonid,lt.teacherid,lt.starttime,lt.endtime,lt.location,lt.note,
    sub.subject,ce.class_name,b.batch
    FROM tbllessontimetable lt
    INNER JOIN tblsubject sub ON sub.subjectid=lt.subjectid
    INNER JOIN tblclassentry ce ON ce.class_entryid=lt.classid
    INNER JOIN tblbatch b ON b.batchid=lt.batchid
    WHERE lt.status='active' AND lt.weekday='$weekdayEsc'
      AND lt.starttime >= '$startEsc' AND lt.starttime <= '$endEsc'";
$result = mysqli_query($con, $sql);
$sent = 0;
if($result){
    while($lesson = mysqli_fetch_assoc($result)){
        $lessonIdEsc = mysqli_real_escape_string($con, $lesson['lessonid']);
        $teacherEsc = mysqli_real_escape_string($con, $lesson['teacherid']);
        $already = mysqli_query($con, "SELECT 1 FROM tbllessonnotificationlog WHERE lessonid='$lessonIdEsc' AND reminderdate='$todayEsc' AND userid='$teacherEsc' LIMIT 1");
        if($already && mysqli_num_rows($already) > 0){ continue; }
        $message = 'Lesson reminder: '.$lesson['subject'].' for '.$lesson['class_name'].' begins at '.substr($lesson['starttime'],0,5).'.';
        if(trim((string)$lesson['location']) !== ''){ $message .= ' Location: '.$lesson['location'].'.'; }
        $messageIdEsc = mysqli_real_escape_string($con, 'MSG_'.strtoupper(substr(sha1(uniqid('', true)), 0, 18)));
        $messageEsc = mysqli_real_escape_string($con, substr($message, 0, 4900));
        $created = @mysqli_query($con, "INSERT IGNORE INTO tbllessonnotificationlog(lessonid,reminderdate,userid,createdat) VALUES('$lessonIdEsc','$todayEsc','$teacherEsc',NOW())");
        if($created && mysqli_affected_rows($con) > 0){
            @mysqli_query($con, "INSERT INTO tblmessages(messageid,messages,datetimeentry,status,sentby,recipient_group,recipient_type,recipient_value,recipient_label) VALUES('$messageIdEsc','$messageEsc',NOW(),'active','SYSTEM','teachers','user','$teacherEsc','Lesson reminder')");
            $sent++;
        }
    }
}
echo 'Lesson notifications sent: '.$sent.PHP_EOL;
?>
