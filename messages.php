<?php
session_start();
include("dbstring.php");
include("check-login.php");
include("code.php");

if(!function_exists('msg_esc')){
function msg_esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
}

if(!function_exists('msg_time')){
function msg_time($value){
    $time = strtotime((string)$value);
    return $time ? date("d M Y, H:i", $time) : (string)$value;
}
}

if(!function_exists('msg_audience_badge_class')){
function msg_audience_badge_class($audience){
    $audience = um_message_normalize_audience($audience);
    if($audience === 'students'){
        return 'messages-audience messages-audience--students';
    }
    if($audience === 'teachers'){
        return 'messages-audience messages-audience--teachers';
    }
    if($audience === 'admins'){
        return 'messages-audience messages-audience--admins';
    }
    return 'messages-audience messages-audience--all';
}
}

$__CurrentUserId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : "";
$__CurrentUserIdEsc = mysqli_real_escape_string($con, $__CurrentUserId);
$__AudienceOptions = um_message_audience_options_for_current_user();
$__DefaultAudience = um_message_default_audience_for_current_user();
$__VisibilitySql = um_message_visibility_sql('mg.recipient_group');
$__CanManageAllMessages = um_is_admin_manager();
$__SystemType = isset($_SESSION['SYSTEMTYPE']) ? trim((string)$_SESSION['SYSTEMTYPE']) : '';
$__UserFullName = isset($_SESSION['FULLNAME']) ? trim((string)$_SESSION['FULLNAME']) : '';
$__UserDisplayName = $__UserFullName !== '' ? $__UserFullName : (isset($_SESSION['USERNAME']) ? trim((string)$_SESSION['USERNAME']) : $__CurrentUserId);
$__RoleLabel = 'School Communication';
if($__SystemType === 'Student'){
    $__RoleLabel = 'Student Message Box';
} elseif($__SystemType === 'Teacher'){
    $__RoleLabel = 'Teacher Message Box';
} elseif($__SystemType === 'User'){
    $__RoleLabel = 'Office Message Box';
} elseif($__SystemType === 'normal_user' || $__SystemType === 'super_user'){
    $__RoleLabel = 'Admin Message Box';
}

if(isset($_POST["send_message"])){
    $_Message = trim((string)(isset($_POST['message']) ? $_POST['message'] : ''));
    if($_Message === ''){
        $_SESSION['Message'] = "<div style='color:#991b1b;padding:10px;'>Please type a message before sending.</div>";
    } else {
        $_ChosenAudience = isset($_POST['message_audience']) ? um_message_normalize_audience($_POST['message_audience']) : $__DefaultAudience;
        if($__SystemType === 'Student'){
            $_ChosenAudience = 'admins';
        } elseif($__SystemType === 'Teacher'){
            $_ChosenAudience = 'admins';
        }
        $_MessageId = mysqli_real_escape_string($con, (string)$code);
        $_ChosenAudienceEsc = mysqli_real_escape_string($con, $_ChosenAudience);
        $_MessageEsc = mysqli_real_escape_string($con, $_Message);
        $_SQL = mysqli_query($con, "INSERT INTO tblmessages(messageid,messages,datetimeentry,status,sentby,recipient_group)
            VALUES('$_MessageId','$_MessageEsc',NOW(),'active','$__CurrentUserIdEsc','$_ChosenAudienceEsc')");
        if($_SQL){
            if($__SystemType === 'Teacher'){
                engagement_track_daily_action($con, 'teacher_message_sent_daily', $__CurrentUserId);
            } elseif($__SystemType === 'Student'){
                engagement_track_daily_action($con, 'student_message_sent_daily', $__CurrentUserId);
            }
            $_SESSION['Message'] = "<div style='color:#166534;padding:10px;'>Message successfully sent.</div>";
        } else {
            $_SESSION['Message'] = "<div style='color:#991b1b;padding:10px;'>Message failed to send.</div>";
        }
    }
    header("location:messages.php");
    exit();
}

$__DeleteMessageId = "";
if(isset($_POST['delete_message'])){
    $__DeleteMessageId = trim((string)(isset($_POST['messageid']) ? $_POST['messageid'] : ""));
} elseif(isset($_GET['delete_message'])){
    $__DeleteMessageId = trim((string)$_GET['delete_message']);
}
if($__DeleteMessageId !== ""){
    $_MessageIdEsc = mysqli_real_escape_string($con, $__DeleteMessageId);
    $_DeleteWhere = $__CanManageAllMessages ? "messageid='$_MessageIdEsc'" : "messageid='$_MessageIdEsc' AND sentby='$__CurrentUserIdEsc'";
    $_SQL_D = mysqli_query($con, "DELETE FROM tblmessages WHERE $_DeleteWhere LIMIT 1");
    if($_SQL_D && mysqli_affected_rows($con) > 0){
        $_SESSION['Message'] = "<div style='color:#991b1b;padding:10px;'>Message deleted.</div>";
    } else {
        $_SESSION['Message'] = "<div style='color:#991b1b;padding:10px;'>Message could not be deleted.</div>";
    }
    header("location:messages.php");
    exit();
}

$flashMessage = isset($_SESSION['Message']) ? $_SESSION['Message'] : "";
$_SESSION['Message'] = "";

$myMessageCount = 0;
$boardMessageCount = 0;
$myMessages = array();
$boardMessages = array();

$_SQL_MY_COUNT = mysqli_query($con, "SELECT COUNT(*) AS total_messages FROM tblmessages WHERE sentby='$__CurrentUserIdEsc' AND status='active'");
if($_SQL_MY_COUNT && ($row_count = mysqli_fetch_array($_SQL_MY_COUNT, MYSQLI_ASSOC))){
    $myMessageCount = (int)$row_count['total_messages'];
}

$_SQL_BOARD_COUNT = mysqli_query($con, "SELECT COUNT(*) AS total_messages
    FROM tblmessages mg
    WHERE mg.status='active' AND $__VisibilitySql");
if($_SQL_BOARD_COUNT && ($row_board_count = mysqli_fetch_array($_SQL_BOARD_COUNT, MYSQLI_ASSOC))){
    $boardMessageCount = (int)$row_board_count['total_messages'];
}

$_SQL_MY_MESSAGES = mysqli_query($con, "SELECT messageid,messages,datetimeentry,recipient_group
    FROM tblmessages
    WHERE sentby='$__CurrentUserIdEsc' AND status='active'
    ORDER BY datetimeentry DESC
    LIMIT 40");
if($_SQL_MY_MESSAGES){
    while($row = mysqli_fetch_array($_SQL_MY_MESSAGES, MYSQLI_ASSOC)){
        $myMessages[] = $row;
    }
}

$_SQL_BOARD_MESSAGES = mysqli_query($con, "SELECT
        mg.messageid,
        mg.messages,
        mg.datetimeentry,
        mg.recipient_group,
        mg.sentby,
        su.firstname,
        su.othernames,
        su.surname,
        su.systemtype
    FROM tblmessages mg
    INNER JOIN tblsystemuser su ON mg.sentby=su.userid
    WHERE mg.status='active' AND $__VisibilitySql
    ORDER BY mg.datetimeentry DESC
    LIMIT 60");
if($_SQL_BOARD_MESSAGES){
    while($row = mysqli_fetch_array($_SQL_BOARD_MESSAGES, MYSQLI_ASSOC)){
        $boardMessages[] = $row;
    }
}

$visibilityHint = "You can see all active message groups here.";
if($__SystemType === 'Student'){
    $visibilityHint = "You will only see general notices and messages directed to students. Any message you send from here goes to admin only.";
} elseif($__SystemType === 'Teacher'){
    $visibilityHint = "You will only see general notices and messages directed to teachers. Any message you send from here goes to admin only.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/messages.css">
</head>
<body class="body-style messages-page">
    <div class="header">
    <?php include("menu.php"); ?>
    </div>

    <main class="messages-shell">
        <aside class="messages-sidebar">
            <?php include("welcome.php"); ?>
        </aside>

        <section class="messages-main">
            <?php if($flashMessage !== ""){ ?>
            <div class="messages-flash"><?php echo $flashMessage; ?></div>
            <?php } ?>

            <section class="messages-hero">
                <div class="messages-hero__copy">
                    <span class="messages-kicker"><?php echo msg_esc($__RoleLabel); ?></span>
                    <h1>School Messages</h1>
                    <p>Send updates, review your own posts, and follow the shared message board from one cleaner, mobile-friendly workspace.</p>
                </div>
                <div class="messages-stats">
                    <article class="messages-stat">
                        <span>My Messages</span>
                        <strong><?php echo (int)$myMessageCount; ?></strong>
                    </article>
                    <article class="messages-stat">
                        <span>Visible Board Posts</span>
                        <strong><?php echo (int)$boardMessageCount; ?></strong>
                    </article>
                    <article class="messages-stat">
                        <span>Audience View</span>
                        <strong><?php echo msg_esc($__SystemType === '' ? 'General' : $__SystemType); ?></strong>
                    </article>
                </div>
            </section>

            <div class="messages-note">
                <i class="fa fa-info-circle"></i>
                <span><?php echo msg_esc($visibilityHint); ?></span>
            </div>

            <div class="messages-grid">
                <section class="messages-card messages-card--composer">
                    <div class="messages-card__header">
                        <div>
                            <span class="messages-card__eyebrow">Write Message</span>
                            <h2>Send a new message</h2>
                        </div>
                    </div>

                    <form method="post" class="messages-form">
                        <input type="hidden" id="userid" name="userid" value="<?php echo msg_esc($__CurrentUserId); ?>" readonly>

                        <label for="message">Message</label>
                        <textarea id="message" name="message" placeholder="Type your message here..." required></textarea>

                        <?php if(count($__AudienceOptions) > 1){ ?>
                        <label for="message_audience">Send To</label>
                        <select id="message_audience" name="message_audience">
                            <?php foreach($__AudienceOptions as $__AudienceValue => $__AudienceLabel){ ?>
                            <option value="<?php echo msg_esc($__AudienceValue); ?>"<?php echo ($__AudienceValue === $__DefaultAudience ? " selected" : ""); ?>><?php echo msg_esc($__AudienceLabel); ?></option>
                            <?php } ?>
                        </select>
                        <?php } else { ?>
                        <div class="messages-helper">
                            Your message will go to teachers and school office only.
                        </div>
                        <?php } ?>

                        <div class="messages-form__actions">
                            <span>Signed in as <?php echo msg_esc($__UserDisplayName); ?></span>
                            <button class="messages-primary-btn" type="submit" name="send_message"><i class="fa fa-send"></i> Send Message</button>
                        </div>
                    </form>
                </section>

                <section class="messages-card messages-card--mine">
                    <div class="messages-card__header">
                        <div>
                            <span class="messages-card__eyebrow">My Posts</span>
                            <h2>Your recent messages</h2>
                        </div>
                        <span class="messages-count"><?php echo (int)$myMessageCount; ?></span>
                    </div>

                    <div class="messages-feed">
                        <?php if(count($myMessages) > 0){ ?>
                            <?php foreach($myMessages as $message){ ?>
                            <article class="messages-item messages-item--mine">
                                <div class="messages-item__meta">
                                    <span class="<?php echo msg_esc(msg_audience_badge_class(isset($message['recipient_group']) ? $message['recipient_group'] : 'all')); ?>">
                                        <?php echo msg_esc(um_message_audience_label(isset($message['recipient_group']) ? $message['recipient_group'] : 'all')); ?>
                                    </span>
                                    <span class="messages-time"><?php echo msg_esc(msg_time($message['datetimeentry'])); ?></span>
                                </div>
                                <p><?php echo nl2br(msg_esc($message['messages'])); ?></p>
                                <form method="post" class="messages-delete-form">
                                    <input type="hidden" name="messageid" value="<?php echo msg_esc($message['messageid']); ?>">
                                    <button type="submit" name="delete_message" class="messages-delete-btn" onclick="return confirm('Delete this message?');"><i class="fa fa-trash"></i> Delete</button>
                                </form>
                            </article>
                            <?php } ?>
                        <?php } else { ?>
                        <div class="messages-empty">
                            <h3>No messages yet</h3>
                            <p>Your sent messages will appear here after you post the first one.</p>
                        </div>
                        <?php } ?>
                    </div>
                </section>
            </div>

            <section class="messages-card messages-card--board">
                <div class="messages-card__header">
                    <div>
                        <span class="messages-card__eyebrow">Shared Board</span>
                        <h2>Latest visible messages</h2>
                    </div>
                    <span class="messages-count"><?php echo (int)$boardMessageCount; ?></span>
                </div>

                <div class="messages-feed messages-feed--board">
                    <?php if(count($boardMessages) > 0){ ?>
                        <?php foreach($boardMessages as $message){ ?>
                        <?php
                        $__SenderName = trim($message['firstname']." ".$message['othernames']." ".$message['surname']);
                        $__IsMine = ((string)$message['sentby'] === $__CurrentUserId);
                        ?>
                        <article class="messages-item">
                            <div class="messages-item__meta">
                                <div class="messages-item__who">
                                    <span class="<?php echo msg_esc(msg_audience_badge_class(isset($message['recipient_group']) ? $message['recipient_group'] : 'all')); ?>">
                                        <?php echo msg_esc(um_message_audience_label(isset($message['recipient_group']) ? $message['recipient_group'] : 'all')); ?>
                                    </span>
                                    <strong><?php echo msg_esc($__SenderName !== '' ? $__SenderName : $message['sentby']); ?></strong>
                                    <span class="messages-role"><?php echo msg_esc($message['systemtype']); ?></span>
                                    <?php if($__IsMine){ ?><span class="messages-you">You</span><?php } ?>
                                </div>
                                <span class="messages-time"><?php echo msg_esc(msg_time($message['datetimeentry'])); ?></span>
                            </div>
                            <p><?php echo nl2br(msg_esc($message['messages'])); ?></p>
                            <?php if($__IsMine || $__CanManageAllMessages){ ?>
                            <form method="post" class="messages-delete-form">
                                <input type="hidden" name="messageid" value="<?php echo msg_esc($message['messageid']); ?>">
                                <button type="submit" name="delete_message" class="messages-delete-btn" onclick="return confirm('Delete this message?');"><i class="fa fa-trash"></i> Delete</button>
                            </form>
                            <?php } ?>
                        </article>
                        <?php } ?>
                    <?php } else { ?>
                    <div class="messages-empty">
                        <h3>No board messages yet</h3>
                        <p>When new messages are posted to your visible audience, they will show here.</p>
                    </div>
                    <?php } ?>
                </div>
            </section>
        </section>
    </main>
</body>
</html>
