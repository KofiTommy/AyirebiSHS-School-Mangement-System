<?php
// Copy this file to online-admission-sms-config.local.php on the server.
// Keep the local file outside Git and rotate the API key if it was exposed.
return array(
    "api_key" => "REPLACE_WITH_YOUR_BULKSMS_GH_API_KEY",
    "sender_id" => "AYISEC",
    // Use the HTTPS endpoint here only after BulkSMS Ghana confirms it works
    // for your account. Their published legacy integration uses this URL.
    "api_url" => "http://clientlogin.bulksmsgh.com/smsapi"
);
