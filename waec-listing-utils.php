<?php
/* Read the printed WAEC listing by result blocks, not by the vertical position
 * of its index numbers. A block may continue on another worksheet/page. */
function waec_listing_blocks($rows){
    $blocks = array();
    $block = null;
    foreach($rows as $row){
        $index = trim((string)($row[0] ?? ''));
        $name = trim((string)($row[1] ?? ''));
        $result = trim((string)($row[4] ?? ''), " \t\r\n\"'");
        if(stripos($index, 'SUMMARY OF SUBJECT PASSES') !== false) break;
        if(preg_match('/INDEX NUMBER|TOTAL NUMBER OF CANDIDATES|https?:\/\/|WAEC Results Listing/i', $index.' '.$name) || strtoupper($result) === 'RESULTS') continue;
        if(preg_match('/^SOCIAL\s+STUDIES\s*-/i', $result)){
            if($block !== null) $blocks[] = $block;
            $block = array('indices'=>array(), 'names'=>array(), 'genders'=>array(), 'text'=>'');
        }
        if($block === null){
            if(waec_is_index_number($index) || preg_match('/-\s*(A1|B2|B3|C4|C5|C6|D7|E8|F9|X|W|\*)\s*[,\s]*$/i', $result)){
                return array('error'=>'Unrecognized listing layout before the first result block. Use a structured CSV instead.', 'rows'=>array(), 'withheld_rows'=>array());
            }
            continue;
        }
        if(waec_is_index_number($index)) $block['indices'][] = preg_replace('/\s+/', '', $index);
        if($name !== '') $block['names'][] = $name;
        foreach(array(2,3) as $col){
            $gender = waec_normalize_gender($row[$col] ?? '');
            if($gender !== '') $block['genders'][$gender] = true;
        }
        if($result !== '') $block['text'] .= ' '.$result;
    }
    if($block !== null) $blocks[] = $block;
    $records=array(); $statuses=array(); $seen=array();
    foreach($blocks as $i=>$block){
        $error = '';
        if(count($block['indices']) !== 1 || count($block['genders']) > 1){
            $error = 'Each result block must identify exactly one candidate and one consistent gender.';
        } else {
            $candidate = $block['indices'][0];
            if(isset($seen[$candidate])) $error = 'A candidate appears in more than one result block.';
            $seen[$candidate] = true;
        }
        if($error !== '') return array('error'=>'Listing block '.($i+1).': '.$error, 'rows'=>array(), 'withheld_rows'=>array());
        $subjects=array();
        foreach(explode(',', $block['text']) as $item){
            $item = trim(preg_replace('/\s+/', ' ', $item), " \t\r\n\"'");
            if($item === '') continue;
            if(!preg_match('/^(.+?)\s*-\s*(A1|B2|B3|C4|C5|C6|D7|E8|F9|X|W|P|\*)$/i', $item, $match)){
                return array('error'=>'Listing block '.($i+1).' has an incomplete or unsupported subject outcome. Import stopped for review.', 'rows'=>array(), 'withheld_rows'=>array());
            }
            $subject = waec_normalize_subject($match[1]);
            if(isset($subjects[$subject])) return array('error'=>'Listing block '.($i+1).' repeats a subject. Import stopped for review.', 'rows'=>array(), 'withheld_rows'=>array());
            $subjects[$subject]=true;
            $grade = strtoupper($match[2]);
            $record=array('candidate_no'=>$candidate, 'student_name'=>trim(implode(' ', $block['names'])),
                'gender'=>key($block['genders']) ?: '', 'subject_name'=>$subject, 'grade'=>$grade);
            if(in_array($grade, array('X','W','*','P'), true)) $statuses[]=$record;
            else { $record['grade_point']=waec_points($grade); $records[]=$record; }
        }
    }
    return array('error'=>'', 'rows'=>$records, 'withheld_rows'=>$statuses);
}

function waec_import_conflict($records, $statuses){
    $seen=array();
    foreach(array_merge($records, $statuses) as $r){
        $key=waec_identity_key($r['candidate_no'],$r['student_name']).'|'.waec_normalize_subject($r['subject_name']);
        $grade=strtoupper(trim($r['grade']));
        if(isset($seen[$key]) && $seen[$key] !== $grade){
            return 'Conflicting outcomes for the same candidate and subject. Correct the source file before importing.';
        }
        $seen[$key]=$grade;
    }
    return '';
}

function waec_coverage_totals($population, $graded, $absent, $withheld, $sourceStatus, $core){
    $recorded = $graded + $absent + $withheld + $sourceStatus;
    $registered = $core ? max($population, $recorded) : $recorded;
    $sat = $graded + $withheld;
    return array('registered'=>$registered, 'missing'=>$registered-$recorded, 'sat'=>$sat,
        'sat_pct'=>$registered ? round(100*$sat/$registered,2) : 0,
        'graded_pct'=>$registered ? round(100*$graded/$registered,2) : 0);
}
