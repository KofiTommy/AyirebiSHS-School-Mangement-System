<?php
$pageFile = "upload-class-score-entry.php";
$pageTitle = "Upload Class Scores";
$pageDescription = "Download the official class-score template, fill it offline, and upload it back through a cleaner mobile-friendly teacher workspace.";
$scoreType = "Class Score";
$scoreLabel = "Class Score";
$scoreLimit = 30;
$templatePage = "download-classscore-template.php";
$manualEntryPage = "class-score-entry.php";
$importPage = "import-class-scores-data.php";
$bodyModifierClass = "score-entry-page--class";
$heroTitle = "Upload Class Score Sheets";
$uploadGuidanceTitle = "Template rules";
$heroTips = array(
    "Open the exact subject card first so the upload stays attached to the correct semester and academic year.",
    "Download the official template before filling marks so the student IDs and subject code stay untouched.",
    "Save the completed workbook as `.xlsx` when possible before uploading it back here.",
    "Rows with students who already have saved class scores are skipped instead of being duplicated.",
    "Use Manual Entry when you only need to update a few students instead of the whole sheet."
);

include(__DIR__.DIRECTORY_SEPARATOR."score-upload-workspace.php");
