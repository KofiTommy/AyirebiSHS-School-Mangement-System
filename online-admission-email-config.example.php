<?php
// Copy this file to online-admission-email-config.local.php and fill in the
// mailbox details in Hostinger. Never commit the local file or its password.
return array(
    "enabled" => false,
    "host" => "smtp.hostinger.com",
    "port" => 465,
    "encryption" => "ssl", // ssl (465) or tls (587)
    "username" => "admissions@your-school-domain.com",
    "password" => "",
    "from_email" => "admissions@your-school-domain.com",
    "from_name" => "School Admissions",
    "timeout" => 15
);
