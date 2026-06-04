<?php
$pageFile = "upload-exam-score-entry.php";
$pageTitle = "Upload Exam Scores";
$pageDescription = "Work through exam-score uploads in the same polished teacher workspace, with clearer guidance and a layout that stays usable on smaller screens.";
$scoreType = "Exam Score";
$scoreLabel = "Exam Score";
$scoreLimit = 70;
$templatePage = "download-examscore-template.php";
$manualEntryPage = "exam-score-entry.php";
$importPage = "import-exam-scores-data.php";
$bodyModifierClass = "score-entry-page--exam";
$heroTitle = "Upload Exam Score Sheets";
$uploadGuidanceTitle = "Workbook checklist";
$heroTips = array(
    "Choose the correct exam sheet first so the upload cannot drift into the wrong class or semester.",
    "Keep the downloaded template headings unchanged and enter each student's exam mark in the last score column only.",
    "Save the finished workbook as `.xlsx` before uploading whenever you can for the smoothest import.",
    "Students who already have saved exam scores in this sheet are skipped instead of being uploaded twice.",
    "Use Scores Report after uploading if you want to review the saved results immediately."
);

include(__DIR__.DIRECTORY_SEPARATOR."score-upload-workspace.php");
