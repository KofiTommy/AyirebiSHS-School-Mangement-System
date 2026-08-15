<?php
if(isset($_POST["send"])){
    include_once(dirname(__DIR__).DIRECTORY_SEPARATOR.'online-admission-utils.php');
    $resultCode = '';
    $sent = online_admission_sms_gateway_send(
        online_admission_normalize_sms_phone(isset($_POST['to']) ? $_POST['to'] : ''),
        isset($_POST['message']) ? trim($_POST['message']) : '',
        $resultCode
    );

    echo $sent ? 'Message sent.' : 'Message was not sent. Check the phone number, SMS configuration, or provider balance.';
}
?>

<html>
<head>

</head>
<body>
	<form method="post" action="bulksmstry.php">
<label>To:</label>
<input type="text" id="to" name="to" placeholder="To:"/>
<br/>
<label>Message</label>
<textarea id="message" name="message"></textarea>
<br/>
<button id="send" name="send">SEND</button>
</form>
</body>
</html>
