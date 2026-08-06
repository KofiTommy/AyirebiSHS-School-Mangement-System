<?php
return array(
    // Copy this to online-admission-paystack-config.local.php, then paste the
    // matching Paystack test or live key pair. Never commit live secret keys.
    "public_key" => "pk_test_REPLACE_WITH_YOUR_KEY",
    "secret_key" => "sk_test_REPLACE_WITH_YOUR_KEY",
    "callback_path" => "online-admission-paystack-callback.php"
);
