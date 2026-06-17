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

$__faviconHref = "images/nexgen-logo.png";
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
<script>
(function () {
    var root = document.documentElement;
    root.classList.add('xschool-loading');
    var didFinish = false;

    var finishLoading = function () {
        if (didFinish) {
            return;
        }
        didFinish = true;
        root.classList.add('xschool-loading-done');
        window.setTimeout(function () {
            root.classList.remove('xschool-loading');
            root.classList.remove('xschool-loading-done');
        }, 180);
    };

    var quickFinish = function () {
        window.requestAnimationFrame(function () {
            window.setTimeout(finishLoading, 60);
        });
    };

    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        quickFinish();
    } else {
        document.addEventListener('DOMContentLoaded', quickFinish, { once: true });
    }

    window.addEventListener('load', finishLoading, { once: true });
    window.addEventListener('pageshow', finishLoading, { once: true });
    window.setTimeout(finishLoading, 900);
})();
</script>
<script type="text/javascript" src="scripts/xschool_script.js" defer></script>
<style>
:root{
    --xschool-watermark-image: url('<?php echo htmlspecialchars($__faviconHref, ENT_QUOTES, "UTF-8"); ?>');
}

html.xschool-loading,
html.xschool-loading body{
    overflow: hidden;
}

html.xschool-loading::before,
html.xschool-loading::after{
    content:"";
    position:fixed;
    left:50%;
    top:50%;
    pointer-events:none;
    z-index:99999;
    opacity:1;
}

html.xschool-loading::before{
    inset:0;
    left:0;
    top:0;
    transform:none;
    background:
        radial-gradient(circle at center, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.95) 18%, rgba(241, 245, 249, 0.94) 38%, rgba(255, 255, 255, 0.92) 100%),
        var(--xschool-watermark-image) center/ min(18vw, 132px) no-repeat;
    pointer-events:auto;
}

html.xschool-loading::after{
    width:min(28vw, 190px);
    height:min(28vw, 190px);
    transform:translate(-50%, -50%);
    border-radius:50%;
    border:4px solid rgba(15, 39, 66, 0.12);
    border-top-color:#0ea5e9;
    border-right-color:#d59b2d;
    border-bottom-color:#0f766e;
    box-shadow:
        0 0 0 10px rgba(255, 255, 255, 0.48),
        0 14px 34px rgba(15, 23, 42, 0.14);
    animation:xschoolLoaderSpin 1s linear infinite, xschoolLoaderPulse 1.8s ease-in-out infinite;
}

html.xschool-loading-done::before,
html.xschool-loading-done::after{
    opacity:0;
    transition:opacity 0.16s ease;
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
    html.xschool-loading::after{
        width:min(42vw, 170px);
        height:min(42vw, 170px);
        border-width:3px;
    }

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

@keyframes xschoolLoaderSpin{
    from{
        transform:translate(-50%, -50%) rotate(0deg);
    }
    to{
        transform:translate(-50%, -50%) rotate(360deg);
    }
}

@keyframes xschoolLoaderPulse{
    0%,
    100%{
        box-shadow:
            0 0 0 10px rgba(255, 255, 255, 0.48),
            0 14px 34px rgba(15, 23, 42, 0.14);
    }
    50%{
        box-shadow:
            0 0 0 16px rgba(255, 255, 255, 0.3),
            0 18px 42px rgba(15, 23, 42, 0.18);
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
