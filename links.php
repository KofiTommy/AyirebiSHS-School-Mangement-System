<link rel="stylesheet" type="text/css" href="css/font-awesome-4.7.0/css/font-awesome.min.css">

<!-- External Style Sheets  -->
<link rel="stylesheet" type="text/css" href="css/styles.css">
<link rel="stylesheet" type="text/css" href="css/table.css"/>
<link rel="stylesheet" type="text/css" href="css/menu.css"/>
<link rel="stylesheet" type="text/css" href="css/formstyle.css"/>
<link rel="stylesheet" type="text/css" href="css/buttonstyle.css">
<!-- External Scripts -->

<?php
include("validation/header.php");
include_once("company.php");

$__faviconHref = "images/xschool-logo.png";
if(isset($_Logo) && trim((string)$_Logo) !== ""){
    $__logoFile = trim((string)$_Logo);
    $__candidates = array(
        "images/logo/".$__logoFile,
        "logo/".$__logoFile,
        $__logoFile,
    );
    foreach($__candidates as $__candidate){
        if($__candidate !== "" && file_exists(__DIR__.DIRECTORY_SEPARATOR.str_replace(array("/", "\\"), DIRECTORY_SEPARATOR, $__candidate))){
            $__faviconHref = str_replace("\\", "/", $__candidate);
            break;
        }
    }
}
?>
<script type="text/javascript" src="scripts/xschool_script.js" defer></script>
<style>
:root{
    --xschool-watermark-image: url('<?php echo htmlspecialchars($__faviconHref, ENT_QUOTES, "UTF-8"); ?>');
}

body:not(.landing-page)::before{
    content:"";
    position:fixed;
    left:50%;
    top:50%;
    width:min(28vw, 240px);
    height:min(28vw, 240px);
    transform:translate(-50%, -50%);
    background:var(--xschool-watermark-image) center/contain no-repeat;
    opacity:0.055;
    filter:grayscale(1) contrast(1.05);
    pointer-events:none;
    z-index:-1;
}

body:not(.landing-page)::after{
    content:"";
    position:fixed;
    left:50%;
    top:50%;
    width:min(36vw, 320px);
    height:min(36vw, 320px);
    transform:translate(-50%, -50%);
    border-radius:50%;
    background:radial-gradient(circle, rgba(16, 37, 60, 0.04), transparent 68%);
    pointer-events:none;
    z-index:-2;
}

@media (max-width: 820px){
    body:not(.landing-page)::before{
        width:min(46vw, 220px);
        height:min(46vw, 220px);
        opacity:0.048;
        filter:none;
    }

    body:not(.landing-page)::after{
        display:none;
    }
}
</style>

<title>XSCHOOL V<?php echo date("Y");?></title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<meta name="theme-color" content="#0b63ce">
<link rel="icon" type="image/png" href="<?php echo htmlspecialchars($__faviconHref, ENT_QUOTES, "UTF-8"); ?>">
<link rel="shortcut icon" href="<?php echo htmlspecialchars($__faviconHref, ENT_QUOTES, "UTF-8"); ?>">
<link rel="apple-touch-icon" href="<?php echo htmlspecialchars($__faviconHref, ENT_QUOTES, "UTF-8"); ?>">
