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
        'MGTINLIVING' => 'MANAGEMENT IN LIVING', 'MANAGEMENTINLIVING' => 'MANAGEMENT IN LIVING', 'PRINOFCOSTACCTS' => 'PRINCIPLES OF COST ACCOUNTING',
        'CHRISTIANRELSTUD' => 'CHRISTIAN RELIGIOUS STUDIES',
        'PRINCIPLEOFCOSTACCOUNTING' => 'PRINCIPLES OF COST ACCOUNTING',
        'INFOCOMMTECHNOLOGY' => 'INFORMATION & COMMUNICATION TECHNOLOGY',
        'TWIAKUAPEM' => 'TWI', 'TWIASANTE' => 'TWI'
    );
    return isset($map[$k]) ? $map[$k] : $s;
}

// Reassemble known subject labels before the PDF parser classifies individual lines.
// Match only a complete known label followed by an explicit outcome: never guess
// that standalone ART, STUDIES or ACCOUNTING means another subject.
function waec_join_pdf_subject_lines($text){
    // Page headers can interrupt a subject label or separate its dash from its grade.
    // Remove only recognised listing headers/footers before joining complete labels.
    $lines=preg_split('/\r\n|\r|\n/',(string)$text);
    $text=implode("\n",array_map(function($line){return waec_is_noise_line($line) ? 'WAEC Results Listing' : $line;},$lines));
    // Keep a recognised boundary outside matched labels, so school headings never
    // become part of the following candidate's name. Inside labels it is whitespace.
    $gap='(?:\\s|WAEC Results Listing)';
    foreach(waec_pdf_subject_alias_map() as $canonical=>$aliases){
        foreach(array_unique(array_merge(array($canonical),$aliases)) as $alias){
            $pattern=implode('\\s+',array_map(function($part){return preg_quote($part,'/');},preg_split('/\s+/',trim($alias))));
            $pattern=str_replace(array('\\(','\\)','\\-'),array('\\s*\\(\\s*','\\s*\\)','\\s*\\-\\s*'),$pattern);
            $pattern=str_replace('\\s',$gap,$pattern);
            $text=preg_replace('/(?<![A-Z0-9])'.$pattern.$gap.'*-'.$gap.'*(A1|B2|B3|C4|C5|C6|D7|E8|F9|X|W|P|\\*)(?![A-Z0-9])/i',$canonical.' - $1',(string)$text);
        }
    }
    return $text;
}
