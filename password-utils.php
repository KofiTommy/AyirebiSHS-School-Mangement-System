<?php
if(!function_exists('portal_password_hash')){function portal_password_hash($plain){return password_hash((string)$plain,PASSWORD_DEFAULT);}}
if(!function_exists('portal_verify_password')){function portal_verify_password($con,$userRow,$plain){$stored=isset($userRow['password'])?(string)$userRow['password']:'';$ok=strpos($stored,'$')===0?password_verify((string)$plain,$stored):hash_equals($stored,md5((string)$plain));if($ok&&strpos($stored,'$')!==0&&$con&&isset($userRow['userid'])){$hash=portal_password_hash($plain);$stmt=@mysqli_prepare($con,'UPDATE tblsystemuser SET password=? WHERE userid=? LIMIT 1');if($stmt){mysqli_stmt_bind_param($stmt,'ss',$hash,$userRow['userid']);mysqli_stmt_execute($stmt);mysqli_stmt_close($stmt);}}return $ok;}}
?>
