<?php
session_start();
if (!isset($_SESSION['SESSION_INIT'])) {
    session_regenerate_id(true);
    $_SESSION['SESSION_INIT'] = 1;
}
?>
<?php
$_SESSION['Message']="";
$_SESSION['USERID']="";
$_SESSION['USERNAME']="";
$_SESSION['CURRENCY']="";
$_SESSION['SYMBOL']="";
$_SESSION['ACCESSLEVEL']="";
$_SESSION['SYSTEMTYPE']="";
$_SESSION['BRANCHID']="";
$_SESSION["AUDITDATE"]="";
$_SESSION["SCHOOLACCOUNT"]="12311";
$_SESSION["PAYMENTACCOUNT"]="12314";



include("deviceinformation.php");
@$obj_device=new DeviceInformation();
$obj_device->setIPaddr(1);
@$IPAddress=$obj_device->getIPaddr();
@$obj_os=new DeviceInformation();
$obj_os->setOS(1);
@$OS=$obj_os->getOS();
@$obj_browser=new DeviceInformation();
$obj_browser->setBrowser(1);
@$_Browser=$obj_browser->getBrowser();
@$_DeviceInfo="IP:".$IPAddress.", OS:".$OS.", Browser:".$_Browser;
?>

<?php
include("dbstring.php");
include_once("online-admission-utils.php");
include_once("user-management-utils.php");
include_once("portal-help-utils.php");
ensure_online_admission_tables($con);
ensure_user_management_columns($con);
ensure_portal_help_request_table($con);

$_LandingHelpRequestMessageHtml = "";
$_LandingHelpRequestWasSent = false;
$_LandingHelpForm = array(
    "requestername" => "",
    "requesterrole" => "visitor",
    "contactphone" => "",
    "contactemail" => "",
    "helptopic" => "general",
    "helpmessage" => ""
);

$_PublicAdmissionOpen=false;
$_PublicAdmissionPaymentEnabled=false;
$__AdmissionBranchContext=online_admission_default_branch_context($con);
if(trim((string)$__AdmissionBranchContext["branchid"]) !== ""){
    $__AdmissionSetting=online_admission_get_payment_setting($con, $__AdmissionBranchContext["branchid"]);
    $_PublicAdmissionOpen=online_admission_portal_is_open($__AdmissionSetting);
    $_PublicAdmissionPaymentEnabled=((int)$__AdmissionSetting["enabled"] === 1 && (float)$__AdmissionSetting["feeamount"] > 0);
}

$_SQL_Item_2=mysqli_query($con,"SELECT * FROM tblcurrency");
if($row_item_2=mysqli_fetch_array($_SQL_Item_2,MYSQLI_ASSOC)){
$_SESSION['CURRENCY']=$row_item_2['currencyname'];
$_SESSION['SYMBOL']=$row_item_2['symbol'];
}

if(isset($_POST["send_help_request"])){
$_LandingHelpForm["requestername"]=trim((string)(isset($_POST["requestername"]) ? $_POST["requestername"] : ""));
$_LandingHelpForm["requesterrole"]=portal_help_normalize_role(isset($_POST["requesterrole"]) ? $_POST["requesterrole"] : "visitor");
$_LandingHelpForm["contactphone"]=trim((string)(isset($_POST["contactphone"]) ? $_POST["contactphone"] : ""));
$_LandingHelpForm["contactemail"]=trim((string)(isset($_POST["contactemail"]) ? $_POST["contactemail"] : ""));
$_LandingHelpForm["helptopic"]=portal_help_normalize_topic(isset($_POST["helptopic"]) ? $_POST["helptopic"] : "general");
$_LandingHelpForm["helpmessage"]=trim((string)(isset($_POST["helpmessage"]) ? $_POST["helpmessage"] : ""));

if($_LandingHelpForm["requestername"] === ""){
    $_LandingHelpRequestMessageHtml = "<div class='landing-help-flash landing-help-flash--error'>Please enter your name before sending the help message.</div>";
}elseif($_LandingHelpForm["contactphone"] === "" && $_LandingHelpForm["contactemail"] === ""){
    $_LandingHelpRequestMessageHtml = "<div class='landing-help-flash landing-help-flash--error'>Add a phone number or email so the admin can get back to you.</div>";
}elseif($_LandingHelpForm["contactemail"] !== "" && !filter_var($_LandingHelpForm["contactemail"], FILTER_VALIDATE_EMAIL)){
    $_LandingHelpRequestMessageHtml = "<div class='landing-help-flash landing-help-flash--error'>Please enter a valid email address.</div>";
}elseif($_LandingHelpForm["helpmessage"] === ""){
    $_LandingHelpRequestMessageHtml = "<div class='landing-help-flash landing-help-flash--error'>Tell the admin what you need help with.</div>";
}else{
    $_SavedHelpRequestId = portal_help_create_request($con, array(
        "requestername" => $_LandingHelpForm["requestername"],
        "requesterrole" => $_LandingHelpForm["requesterrole"],
        "contactphone" => $_LandingHelpForm["contactphone"],
        "contactemail" => $_LandingHelpForm["contactemail"],
        "helptopic" => $_LandingHelpForm["helptopic"],
        "helpmessage" => $_LandingHelpForm["helpmessage"],
        "sourcepage" => "index.php",
        "ipaddress" => isset($_SERVER["REMOTE_ADDR"]) ? (string)$_SERVER["REMOTE_ADDR"] : "",
        "useragent" => isset($_SERVER["HTTP_USER_AGENT"]) ? (string)$_SERVER["HTTP_USER_AGENT"] : "",
        "branchid" => trim((string)(isset($__AdmissionBranchContext["branchid"]) ? $__AdmissionBranchContext["branchid"] : ""))
    ));
    if($_SavedHelpRequestId){
        $_LandingHelpRequestWasSent = true;
        $_LandingHelpRequestMessageHtml = "<div class='landing-help-flash landing-help-flash--success'>Your help message has been sent. The admin will follow up soon.</div>";
        $_LandingHelpForm = array(
            "requestername" => "",
            "requesterrole" => "visitor",
            "contactphone" => "",
            "contactemail" => "",
            "helptopic" => "general",
            "helpmessage" => ""
        );
    }else{
        $_LandingHelpRequestMessageHtml = "<div class='landing-help-flash landing-help-flash--error'>The help message could not be sent right now. Please try again.</div>";
    }
}
}

if(isset($_POST["login"])){
@$_Username=$_POST["username"];
@$_Password=md5($_POST["password"]);
@$_User =strtolower($_POST["user"]);

$_SQL_EXECUTE=false;
$stmt_login=mysqli_prepare($con,"SELECT *
FROM tblsystemuser su
INNER JOIN tblbranch br ON su.branchid=br.branchid
WHERE (su.userid=? OR su.username=?)
  AND su.password=?
ORDER BY CASE
    WHEN su.userid=? THEN 0
    WHEN su.username=? THEN 1
    ELSE 2
END,
su.registereddatetime DESC
LIMIT 1");
if($stmt_login){
mysqli_stmt_bind_param($stmt_login,"sssss",$_Username,$_Username,$_Password,$_Username,$_Username);
mysqli_stmt_execute($stmt_login);
$_SQL_EXECUTE=mysqli_stmt_get_result($stmt_login);
}

//$_SQL_EXECUTE=mysqli_query($con,"SELECT * FROM tblsystemuser su  WHERE su.username='$_Username' AND su.password='$_Password'");
	if($_SQL_EXECUTE && mysqli_num_rows($_SQL_EXECUTE)>0){
		if($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC)){
			@$_AccessLevel=$row['accesslevel'];
			@$_SystemType=$row['systemtype'];
			$_SESSION['USERID']=$row['userid'];
			$_SESSION['USERNAME']=$row['username'];
			@$_Userfullname=$row['firstname']." ".$row['othernames']." ".$row['surname'];
			$_SESSION['FULLNAME']=$_Userfullname;
			$_SESSION['ACCESSLEVEL']=$row['accesslevel'];
			$_SESSION['SYSTEMTYPE']=$row['systemtype'];
			$_SESSION['BRANCHID']=$row['branchid'];
			$_SESSION['COMPANY']=$row['companyid'];


//Get the Audit Date
$_SESSION["AUDITDATE"]="";

$_SQLAD=mysqli_query($con,"SELECT ad.auditdate
FROM tblauditdate ad
WHERE ad.auditdate >= CURDATE()
AND ad.auditdate < (CURDATE() + INTERVAL 1 DAY)
AND ad.status='active'
LIMIT 1");
if($_SQLAD && ($rowad=mysqli_fetch_array($_SQLAD,MYSQLI_ASSOC))){
		  $_SESSION["AUDITDATE"]=$rowad["auditdate"];
}
else{
		   //CREATE AUDIT DATE
		  include("code.php");
		  @$_AuditId=$code;
		  $_SQLAD=mysqli_query($con,"INSERT INTO tblauditdate(auditid,auditdate,status,deviceinformation,recordedby,branchid)
		  VALUES('$_AuditId',NOW(),'active','$_DeviceInfo','$_SESSION[USERID]','$_SESSION[BRANCHID]')");
		  if($_SQLAD)
			  {
			    $_SQLU=mysqli_query($con,"UPDATE tblauditdate ad SET ad.status='sealed' WHERE ad.status='active' AND date_format(ad.auditdate,'%d-%m-%Y')<>date_format(NOW(),'%d-%m-%Y')");
				    if($_SQLU){
				    	//SET GLOBAL event_scheduler="ON" 
				    	$_SET_Global=mysqli_query($con,"SET GLOBAL event_scheduler='ON'");
				    	if($_SET_Global){/*Global event put on*/}

				    }
		  }

		  $_SQLAD=mysqli_query($con,"SELECT ad.auditdate
		  FROM tblauditdate ad
		  WHERE ad.auditdate >= CURDATE()
		  AND ad.auditdate < (CURDATE() + INTERVAL 1 DAY)
		  AND ad.status='active'
		  LIMIT 1");
		  if($rowad=mysqli_fetch_array($_SQLAD,MYSQLI_ASSOC)){
		  $_SESSION["AUDITDATE"]=$rowad["auditdate"];
		  }
		 
	}
			if($row['status']=="block"){
			$_SESSION['Message']="<div style='color:red;text-align:center;padding:8px;text-transform:blink'>Account is blocked!! Please contact administrator</div>";
			}else{
				if(isset($row['password_reset_required']) && (int)$row['password_reset_required'] === 1){
					header("location:change-password.php?force=1");
					exit();
				}
				if($_AccessLevel=="administrator" && $_SystemType=="super_user"){
					header("location:super.php");
				}
				elseif($_AccessLevel=="administrator" && $_SystemType=="normal_user"){
					//header("location:admin.php");
					header("location:select-branch.php");
				}
				elseif($_AccessLevel=="user" && $_SystemType=="Student"){
					header("location:student-page.php");
				}
				elseif($_AccessLevel=="user" && $_SystemType=="Teacher"){
					header("location:teacher-page.php");
				}
				elseif($_AccessLevel=="user" && $_SystemType=="Headmaster"){
					header("location:headmaster-page.php");
				}
				elseif($_AccessLevel=="user" && $_SystemType=="AssistantHeadAcademic"){
					header("location:assistant-head-academics-page.php");
				}
				elseif($_AccessLevel=="user" && $_SystemType=="User"){
					header("location:user.php");
				}	
			}
		}
	}
	else
	{
		$_SESSION['Message']="<div style='color:red;text-align:center;padding:8px;'>Failed to login. Try again!!</div>";
	}
if(isset($stmt_login) && $stmt_login){
mysqli_stmt_close($stmt_login);
}
}
?>

<html>
<head>

<?php
include("title.php");
include("links.php");
?>
<link rel="stylesheet" type="text/css" href="css/index-landing.css">
<link rel="stylesheet" type="text/css" href="css/ayisec-shared-palette.css">
<?php
include("validation/header.php");
?>
</head>
<?php
include("backgroundphoto.php");
$_LandingBackgroundPhoto = htmlspecialchars((string)$_BackgroundPhoto, ENT_QUOTES, "UTF-8");
$_LandingHelpLine = trim((string)(isset($_Telephone1) ? $_Telephone1 : ""));
if($_LandingHelpLine === ""){
    $_LandingHelpLine = "+233(0)245067195";
}
$_LandingSchoolName = trim((string)(isset($_CompanyName) ? $_CompanyName : ""));
if($_LandingSchoolName === ""){
    $_LandingSchoolName = "Live Campus";
}
$_LandingSchoolNameSafe = htmlspecialchars($_LandingSchoolName, ENT_QUOTES, "UTF-8");
$_LandingFacebookUrl = "https://www.facebook.com/Ayirebiseniorhighschool/";
$_LandingTiktokLabel = "Official Ayisec Tv";
$_LandingTiktokUrl = "https://www.tiktok.com/search?q=".rawurlencode($_LandingTiktokLabel);
$_LandingWhatsappNumber = "+233245065954";
$_LandingWhatsappUrl = "https://wa.me/233245065954?text=".rawurlencode("Hello, I need help with admission.");
$_LandingPhoneHref = preg_replace('/[^0-9+]/', '', $_LandingHelpLine);
if($_LandingPhoneHref !== ""){
    $_LandingPhoneHref = "tel:".$_LandingPhoneHref;
}
$_LandingQuickActionHref = $_PublicAdmissionOpen ? "online-admission.php" : "#portal-login";
$_LandingQuickActionLabel = $_PublicAdmissionOpen ? "Admission" : "Login";
$_LandingQuickActionIcon = $_PublicAdmissionOpen ? "fa-arrow-right" : "fa-sign-in";
$_LandingHelpModalOpen = isset($_POST["send_help_request"]) || $_LandingHelpRequestWasSent;
$_LandingLogoHref = "images/nexgen-logo.png";
if(isset($_Logo) && trim((string)$_Logo) !== ""){
    $__LandingLogoFile = trim((string)$_Logo);
    $__LandingLogoCandidates = array(
        "images/logo/".$__LandingLogoFile,
        "logo/".$__LandingLogoFile,
        $__LandingLogoFile,
    );
    foreach($__LandingLogoCandidates as $__LandingLogoCandidate){
        if($__LandingLogoCandidate !== "" && file_exists(__DIR__.DIRECTORY_SEPARATOR.str_replace(array("/", "\\"), DIRECTORY_SEPARATOR, $__LandingLogoCandidate))){
            $_LandingLogoHref = str_replace("\\", "/", $__LandingLogoCandidate);
            break;
        }
    }
}
?>
<body class="landing-page" style="--landing-photo:url('images/logo/<?php echo $_LandingBackgroundPhoto; ?>');">
    <style>.school-website-shortcut{position:fixed;z-index:9999;right:20px;bottom:20px;display:inline-flex;gap:10px;align-items:center;padding:12px 17px;border-radius:3px;background:#164239;color:#fff!important;font:700 13px Arial,sans-serif;text-decoration:none;box-shadow:0 8px 22px rgba(0,0,0,.22)}.school-website-shortcut span{color:#e3b650;font-size:18px;line-height:1}.school-website-shortcut:hover{background:#0e3029;color:#fff!important}@media(max-width:600px){.school-website-shortcut{right:12px;bottom:12px;font-size:12px;padding:10px 13px}}</style>
    <a href="website.php" class="school-website-shortcut" aria-label="Open Ayirebi Senior High School public website">Visit school website <span>↗</span></a>
    <style>.school-website-shortcut{bottom:86px}@media(max-width:600px){.school-website-shortcut{bottom:74px}}</style>
    <style>.school-website-shortcut{bottom:100px;z-index:35}@media(max-width:760px){.school-website-shortcut{right:12px;bottom:160px}}</style>
<div class="landing-shell">
    <div class="landing-currents" aria-hidden="true">
        <span class="landing-current landing-current--one"></span>
        <span class="landing-current landing-current--two"></span>
        <span class="landing-current landing-current--three"></span>
        <span class="landing-current landing-current--four"></span>
        <span class="landing-beam landing-beam--one"></span>
        <span class="landing-beam landing-beam--two"></span>
    </div>
    <div class="landing-orb landing-orb-a"></div>
    <div class="landing-orb landing-orb-b"></div>
    <div class="landing-orb landing-orb-c"></div>

    <header class="landing-topbar">
        <div class="landing-brand">
            <div class="landing-brand__mark">
                <img src="<?php echo htmlspecialchars($_LandingLogoHref, ENT_QUOTES, "UTF-8"); ?>" alt="<?php echo $_LandingSchoolNameSafe; ?>">
            </div>
            <div class="landing-brand__text">
                <span class="landing-kicker">School Portal</span>
                <h2><?php echo $_LandingSchoolNameSafe; ?></h2>
            </div>
        </div>
        <div class="landing-product-mark" aria-label="LiveCampus">
            <img src="images/LiveCampus-white.png" alt="LiveCampus">
            <span>LiveCampus</span>
        </div>
        <div class="landing-topbar__meta">
            <?php if($_PublicAdmissionOpen){ ?>
            <div class="landing-chip landing-chip--accent"><i class="fa fa-check-circle"></i> Online Admission Open</div>
            <?php } ?>
            <div class="landing-chip"><i class="fa fa-phone"></i> <?php echo htmlspecialchars($_LandingHelpLine, ENT_QUOTES, "UTF-8"); ?></div>
        </div>
    </header>

    <main class="landing-grid">
        <section class="landing-hero">
            <div class="landing-hero__watermark" aria-hidden="true">
                <img src="<?php echo htmlspecialchars($_LandingLogoHref, ENT_QUOTES, "UTF-8"); ?>" alt="">
            </div>
            <div class="landing-copy">
                <span class="landing-eyebrow">Mobile-First Access</span>
                <h1>Welcome to <?php echo $_LandingSchoolNameSafe ; ?> Student Management System.</h1>
                <?php if($_PublicAdmissionOpen){ ?>
                <div class="landing-admission-notice" role="status">
                    <div class="landing-admission-notice__icon"><i class="fa fa-graduation-cap"></i></div>
                    <div>
                        <strong>Attention: New Students &amp; Parents</strong>
                        <h2>Start your admission as a fresher</h2>
                        <p>Click <b>Start Online Admission</b> below and follow the steps: verify posting, pay if required, use your token, then fill and submit your form.</p>
                    </div>
                </div>
                <?php } ?>
                <p><?php echo $_PublicAdmissionOpen ? "New students should use start admission. Existing users should use portal login." : "Sign in to continue."; ?></p>
                <a class="landing-report-guide" href="images/websiteimages/How%20to%20Print%20your%20Report%20Online.jpeg" target="_blank" rel="noopener noreferrer" aria-label="Open the student terminal report printing guide">
                    <img src="images/websiteimages/How%20to%20Print%20your%20Report%20Online.jpeg" alt="Preview of the AYISEC student report printing guide">
                    <span><strong>Student report printing guide</strong><small>Learn how to check and print your terminal report online.</small><b>View the five steps <i class="fa fa-external-link"></i></b></span>
                </a>
            </div>

            <div class="landing-route-grid">
                <?php if($_PublicAdmissionOpen){ ?>
                <article class="landing-route-card landing-route-card--admission">
                    <div class="landing-route-card__head">
                        <span class="landing-route-card__badge">New Student Admission</span>
                        <h3>Start admission</h3>
                    </div>
                    <div class="landing-step-grid">
                        <article class="landing-step">
                            <span class="landing-step__icon"><i class="fa fa-check-circle"></i></span>
                            <span class="landing-step__copy">
                                <strong>Verify Posting</strong>
                                <small>Step 1</small>
                            </span>
                        </article>
                        <article class="landing-step">
                            <span class="landing-step__icon"><i class="fa <?php echo $_PublicAdmissionPaymentEnabled ? "fa-credit-card" : "fa-file-text-o"; ?>"></i></span>
                            <span class="landing-step__copy">
                                <strong><?php echo $_PublicAdmissionPaymentEnabled ? "Pay" : "Open Form"; ?></strong>
                                <small>Step 2</small>
                            </span>
                        </article>
                        <article class="landing-step">
                            <span class="landing-step__icon"><i class="fa <?php echo $_PublicAdmissionPaymentEnabled ? "fa-key" : "fa-folder-open-o"; ?>"></i></span>
                            <span class="landing-step__copy">
                                <strong><?php echo $_PublicAdmissionPaymentEnabled ? "Log In With Token" : "Resume Form"; ?></strong>
                                <small>Step 3</small>
                            </span>
                        </article>
                        <article class="landing-step">
                            <span class="landing-step__icon"><i class="fa fa-paper-plane"></i></span>
                            <span class="landing-step__copy">
                                <strong>Fill And Submit Form</strong>
                                <small>Step 4</small>
                            </span>
                        </article>
                    </div>
                    <div class="landing-admission-actions">
                        <a class="landing-admission-link" href="online-admission.php">
                            <i class="fa fa-arrow-right"></i> Start Online Admission
                        </a>
                        <a class="landing-admission-link landing-admission-link--ghost" href="online-admission.php?resume_admission=1">
                            <i class="fa fa-unlock-alt"></i> Continue With Token
                        </a>
                    </div>
                </article>
                <?php } ?>

                <article class="landing-route-card landing-route-card--login">
                    <div class="landing-route-card__head">
                        <span class="landing-route-card__badge landing-route-card__badge--soft">Existing Users</span>
                        <h3>Portal login</h3>
                    </div>
                    <div class="landing-role-row">
                        <span>Admin</span>
                        <span>Teacher</span>
                        <span>Student</span>
                        <span>Staff</span>
                    </div>
                    <a class="landing-admission-link landing-admission-link--ghost" href="#portal-login">
                        <i class="fa fa-sign-in"></i> Go to Login
                    </a>
                </article>
            </div>
        </section>

        <aside class="landing-auth" id="portal-login">
            <div class="landing-auth__card">
                <div class="landing-auth__header">
                    <img src="<?php echo htmlspecialchars($_LandingLogoHref, ENT_QUOTES, "UTF-8"); ?>" alt="<?php echo $_LandingSchoolNameSafe; ?>" class="landing-auth__logo">
                    <div>
                        <span class="landing-auth__eyebrow">System Access</span>
                        <h3>Sign in to continue</h3>
                    </div>
                </div>

                <div class="landing-auth__message">
                    <?php echo @$_SESSION['Message']; ?>
                </div>

                <form id="formID" name="formID" method="post" action="index.php" class="landing-form">
                    <div class="landing-form__group">
                        <label for="username">User Name</label>
                        <div class="landing-field">
                            <span class="landing-field__icon"><i class="fa fa-user"></i></span>
                            <input type="text" id="username" name="username" placeholder="Type Username" class="validate[required]" autocomplete="username" style="text-align:left !important; direction:ltr; padding-left:16px;">
                        </div>
                    </div>

                    <div class="landing-form__group">
                        <label for="password">Password</label>
                        <div class="landing-field">
                            <span class="landing-field__icon"><i class="fa fa-lock"></i></span>
                            <input type="password" id="password" name="password" placeholder="Type Password" class="validate[required]" autocomplete="current-password" style="text-align:left !important; direction:ltr; padding-left:16px;">
                        </div>
                    </div>

                    <button class="landing-submit" id="login" name="login" type="submit">
                        <i class="fa fa-sign-in"></i> Login
                    </button>
                </form>

                <div class="landing-support">
                    <span><i class="fa fa-phone"></i> Help line: <?php echo htmlspecialchars($_LandingHelpLine, ENT_QUOTES, "UTF-8"); ?></span>
                    <span><i class="fa fa-lock"></i> Session protected</span>
                </div>

                <?php if($_LandingWhatsappUrl !== ""){ ?>
                <a href="<?php echo htmlspecialchars($_LandingWhatsappUrl, ENT_QUOTES, "UTF-8"); ?>" class="landing-social-link landing-social-link--whatsapp" target="_blank" rel="noopener noreferrer">
                    <i class="fa fa-whatsapp"></i> Chat On WhatsApp
                </a>
                <?php } ?>

                <a href="<?php echo htmlspecialchars($_LandingFacebookUrl, ENT_QUOTES, "UTF-8"); ?>" class="landing-social-link landing-social-link--facebook" target="_blank" rel="noopener noreferrer">
                    <i class="fa fa-facebook-square"></i> Follow Us On Facebook
                </a>

                <a href="<?php echo htmlspecialchars($_LandingTiktokUrl, ENT_QUOTES, "UTF-8"); ?>" class="landing-social-link landing-social-link--tiktok" target="_blank" rel="noopener noreferrer">
                    <span class="landing-social-mark">TT</span> <?php echo htmlspecialchars($_LandingTiktokLabel, ENT_QUOTES, "UTF-8"); ?> On TikTok
                </a>
            </div>
        </aside>
    </main>

    <footer class="landing-footer">
        <p class="landing-footer__product">
            <img src="images/LiveCampus-white.png" alt="LiveCampus" class="landing-footer__product-logo">
            <span>&copy; 2026 LiveCampus V2.20.2.2</span>
        </p>
        <a class="landing-footer__developer" href="https://tokaatechconsult.com/" target="_blank" rel="noopener noreferrer" aria-label="Visit Tokaa Tech Consult">
            <img src="images/Tokaa Logo.png" alt="Tokaa Tech Consult" class="landing-footer__developer-logo">
            <span>Developed by <strong>Tokaa Tech Consult</strong></span>
        </a>
        <p>
            <?php if($_LandingWhatsappUrl !== ""){ ?><a href="<?php echo htmlspecialchars($_LandingWhatsappUrl, ENT_QUOTES, "UTF-8"); ?>" class="landing-footer__link" target="_blank" rel="noopener noreferrer">WhatsApp</a> | <?php } ?>
            <a href="<?php echo htmlspecialchars($_LandingFacebookUrl, ENT_QUOTES, "UTF-8"); ?>" class="landing-footer__link" target="_blank" rel="noopener noreferrer">Facebook Page</a> |
            <a href="<?php echo htmlspecialchars($_LandingTiktokUrl, ENT_QUOTES, "UTF-8"); ?>" class="landing-footer__link" target="_blank" rel="noopener noreferrer">TikTok: <?php echo htmlspecialchars($_LandingTiktokLabel, ENT_QUOTES, "UTF-8"); ?></a>
        </p>
    </footer>

    <div class="landing-mobile-help" aria-label="Quick help">
        <?php if($_LandingWhatsappUrl !== ""){ ?>
        <a href="<?php echo htmlspecialchars($_LandingWhatsappUrl, ENT_QUOTES, "UTF-8"); ?>" class="landing-mobile-help__link landing-mobile-help__link--whatsapp" target="_blank" rel="noopener noreferrer">
            <i class="fa fa-whatsapp"></i>
            <span>WhatsApp</span>
        </a>
        <?php } ?>
        <?php if($_LandingPhoneHref !== ""){ ?>
        <a href="<?php echo htmlspecialchars($_LandingPhoneHref, ENT_QUOTES, "UTF-8"); ?>" class="landing-mobile-help__link">
            <i class="fa fa-phone"></i>
            <span>Call</span>
        </a>
        <?php } ?>
        <a href="<?php echo htmlspecialchars($_LandingQuickActionHref, ENT_QUOTES, "UTF-8"); ?>" class="landing-mobile-help__link landing-mobile-help__link--accent">
            <i class="fa <?php echo htmlspecialchars($_LandingQuickActionIcon, ENT_QUOTES, "UTF-8"); ?>"></i>
            <span><?php echo htmlspecialchars($_LandingQuickActionLabel, ENT_QUOTES, "UTF-8"); ?></span>
        </a>
    </div>

    <button type="button" class="landing-help-fab" data-help-open aria-haspopup="dialog" aria-controls="landing-help-modal">
        <span class="landing-help-fab__pulse" aria-hidden="true"></span>
        <i class="fa fa-commenting-o"></i>
        <span>Need Help?</span>
    </button>

    <div class="landing-help-modal<?php echo $_LandingHelpModalOpen ? " is-open" : ""; ?>" id="landing-help-modal"<?php echo $_LandingHelpModalOpen ? "" : " hidden"; ?>>
        <div class="landing-help-modal__backdrop" data-help-close></div>
        <section class="landing-help-window" role="dialog" aria-modal="true" aria-labelledby="landing-help-title">
            <div class="landing-help-window__header">
                <div>
                    <span class="landing-help-window__eyebrow">LiveCampus Help Desk</span>
                    <h3 id="landing-help-title">Send a help message to the admin</h3>
                    <p>Leave a short note here and the admin can follow up by phone or email.</p>
                </div>
                <button type="button" class="landing-help-window__close" data-help-close aria-label="Close help form">
                    <i class="fa fa-times"></i>
                </button>
            </div>

            <div class="landing-help-chat">
                <div class="landing-help-bubble landing-help-bubble--admin">
                    <strong>Need a hand?</strong>
                    <span>Tell us if it is about login, admission, results, fees, or anything else on the portal.</span>
                </div>
                <div class="landing-help-bubble landing-help-bubble--user">
                    <span>I want the admin to get my message from this page.</span>
                </div>
            </div>

            <?php if($_LandingHelpRequestMessageHtml !== ""){ ?>
            <div class="landing-help-message"><?php echo $_LandingHelpRequestMessageHtml; ?></div>
            <?php } ?>

            <div class="landing-help-chip-row">
                <button type="button" class="landing-help-chip" data-help-topic="login">Login</button>
                <button type="button" class="landing-help-chip" data-help-topic="admission">Admission</button>
                <button type="button" class="landing-help-chip" data-help-topic="results">Results</button>
                <button type="button" class="landing-help-chip" data-help-topic="technical">Technical</button>
            </div>

            <form method="post" action="index.php#landing-help-modal" class="landing-help-form" id="landing-help-form">
                <div class="landing-help-form__grid">
                    <label class="landing-help-field">
                        <span>Your Name</span>
                        <input type="text" name="requestername" value="<?php echo htmlspecialchars($_LandingHelpForm["requestername"], ENT_QUOTES, "UTF-8"); ?>" placeholder="Type your full name" required>
                    </label>
                    <label class="landing-help-field">
                        <span>You Are</span>
                        <select name="requesterrole" id="landing-help-role">
                            <option value="visitor" <?php echo $_LandingHelpForm["requesterrole"] === "visitor" ? "selected" : ""; ?>>Visitor</option>
                            <option value="parent" <?php echo $_LandingHelpForm["requesterrole"] === "parent" ? "selected" : ""; ?>>Parent</option>
                            <option value="student" <?php echo $_LandingHelpForm["requesterrole"] === "student" ? "selected" : ""; ?>>Student</option>
                            <option value="teacher" <?php echo $_LandingHelpForm["requesterrole"] === "teacher" ? "selected" : ""; ?>>Teacher</option>
                            <option value="staff" <?php echo $_LandingHelpForm["requesterrole"] === "staff" ? "selected" : ""; ?>>Staff</option>
                            <option value="applicant" <?php echo $_LandingHelpForm["requesterrole"] === "applicant" ? "selected" : ""; ?>>Applicant</option>
                            <option value="other" <?php echo $_LandingHelpForm["requesterrole"] === "other" ? "selected" : ""; ?>>Other</option>
                        </select>
                    </label>
                    <label class="landing-help-field">
                        <span>Phone Number</span>
                        <input type="text" name="contactphone" value="<?php echo htmlspecialchars($_LandingHelpForm["contactphone"], ENT_QUOTES, "UTF-8"); ?>" placeholder="024 xxx xxxx">
                    </label>
                    <label class="landing-help-field">
                        <span>Email</span>
                        <input type="email" name="contactemail" value="<?php echo htmlspecialchars($_LandingHelpForm["contactemail"], ENT_QUOTES, "UTF-8"); ?>" placeholder="Optional email address">
                    </label>
                    <label class="landing-help-field landing-help-field--full">
                        <span>Help Topic</span>
                        <select name="helptopic" id="landing-help-topic">
                            <option value="general" <?php echo $_LandingHelpForm["helptopic"] === "general" ? "selected" : ""; ?>>General Help</option>
                            <option value="login" <?php echo $_LandingHelpForm["helptopic"] === "login" ? "selected" : ""; ?>>Login Problem</option>
                            <option value="admission" <?php echo $_LandingHelpForm["helptopic"] === "admission" ? "selected" : ""; ?>>Admission</option>
                            <option value="results" <?php echo $_LandingHelpForm["helptopic"] === "results" ? "selected" : ""; ?>>Results</option>
                            <option value="fees" <?php echo $_LandingHelpForm["helptopic"] === "fees" ? "selected" : ""; ?>>Fees / Payments</option>
                            <option value="technical" <?php echo $_LandingHelpForm["helptopic"] === "technical" ? "selected" : ""; ?>>Technical Issue</option>
                            <option value="other" <?php echo $_LandingHelpForm["helptopic"] === "other" ? "selected" : ""; ?>>Other</option>
                        </select>
                    </label>
                    <label class="landing-help-field landing-help-field--full">
                        <span>Your Message</span>
                        <textarea name="helpmessage" id="landing-help-message-box" placeholder="Briefly explain the help you need." required><?php echo htmlspecialchars($_LandingHelpForm["helpmessage"], ENT_QUOTES, "UTF-8"); ?></textarea>
                    </label>
                </div>
                <div class="landing-help-form__footer">
                    <small>The admin will receive this message on the dashboard.</small>
                    <button type="submit" name="send_help_request" class="landing-help-submit">
                        <i class="fa fa-paper-plane"></i> Send Help Message
                    </button>
                </div>
            </form>
        </section>
    </div>
</div>
<script>
(function () {
    var body = document.body;
    var modal = document.getElementById('landing-help-modal');
    if (!body || !modal) {
        return;
    }

    function setModalState(open) {
        if (open) {
            modal.hidden = false;
            modal.classList.add('is-open');
            body.classList.add('landing-help-open');
            return;
        }
        modal.classList.remove('is-open');
        modal.hidden = true;
        body.classList.remove('landing-help-open');
    }

    var openButtons = document.querySelectorAll('[data-help-open]');
    for (var openIndex = 0; openIndex < openButtons.length; openIndex++) {
        openButtons[openIndex].addEventListener('click', function () {
            setModalState(true);
        });
    }

    var closeButtons = modal.querySelectorAll('[data-help-close]');
    for (var closeIndex = 0; closeIndex < closeButtons.length; closeIndex++) {
        closeButtons[closeIndex].addEventListener('click', function () {
            setModalState(false);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            setModalState(false);
        }
    });

    var topicField = document.getElementById('landing-help-topic');
    var messageField = document.getElementById('landing-help-message-box');
    var topicButtons = modal.querySelectorAll('[data-help-topic]');
    for (var topicIndex = 0; topicIndex < topicButtons.length; topicIndex++) {
        topicButtons[topicIndex].addEventListener('click', function () {
            var topicValue = this.getAttribute('data-help-topic') || 'general';
            if (topicField) {
                topicField.value = topicValue;
            }
            if (messageField && messageField.value.replace(/^\s+|\s+$/g, '') === '') {
                messageField.focus();
            }
        });
    }

    if (modal.classList.contains('is-open')) {
        setModalState(true);
    }
})();
</script>
</body>
</html>
