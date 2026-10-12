<?php
// Shared subject aliases for imports and cross-batch comparisons.
function waec_normalize_subject($subject){
    $s = strtoupper(trim((string)$subject));
    $s = preg_replace('/\s+/', ' ', $s);
    $k = preg_replace('/[^A-Z0-9]+/', '', $s);
    $map = array(
        'ENGLISH' => 'ENGLISH LANG', 'ENGLISHLANG' => 'ENGLISH LANG',
        'ENGLISHLANGUAGE' => 'ENGLISH LANG',
        'COREMATHEMATICS' => 'MATHEMATICS(CORE)', 'COREMATHS' => 'MATHEMATICS(CORE)',
        'MATHEMATICSCORE' => 'MATHEMATICS(CORE)',
        'ELECTIVEMATHEMATICS' => 'MATHEMATICS(ELECT)', 'MATHEMATICSELECTIVE' => 'MATHEMATICS(ELECT)',
        'MATHEMATICSELECT' => 'MATHEMATICS(ELECT)',
        'INTEGRATEDSCIENCE' => 'INTEGRATED SCIENCE', 'SOCIALSTUDIES' => 'SOCIAL STUDIES',
        'LITINENGLISH' => 'LITERATURE IN ENGLISH', 'GENKNOWINART' => 'GENERAL KNOWLEDGE IN ART',
        'MGTINLIVING' => 'MANAGEMENT IN LIVING', 'PRINOFCOSTACCTS' => 'PRINCIPLES OF COST ACCOUNTING',
        'CHRISTIANRELSTUD' => 'CHRISTIAN RELIGIOUS STUDIES',
        'INFOCOMMTECHNOLOGY' => 'INFORMATION & COMMUNICATION TECHNOLOGY',
        'TWIAKUAPEM' => 'TWI', 'TWIASANTE' => 'TWI'
    );
    return isset($map[$k]) ? $map[$k] : $s;
}
