<?php
require_once __DIR__.'/waec-subject-utils.php';

function wac_h($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function wac_grades(){ return array('A1','B2','B3','C4','C5','C6','D7','E8','F9'); }
function wac_core(){ return array('ENGLISH LANG','MATHEMATICS(CORE)','INTEGRATED SCIENCE','SOCIAL STUDIES'); }
function wac_percent($numerator, $denominator){ return $denominator > 0 ? $numerator * 100.0 / $denominator : null; }
function wac_rate($value){ return $value === null ? 'N/A' : number_format($value, 1).'%'; }
function wac_delta($before, $after){ return $before === null || $after === null ? null : $after - $before; }
function wac_delta_text($value){
    if($value === null) return 'Not comparable';
    if(abs($value) < 0.05) return 'No change';
    return ($value > 0 ? '+' : '').number_format($value, 1).' pp';
}
function wac_csv_cell($value){
    $value = (string)$value;
    return preg_match('/^[\s]*[=+@-]/', $value) ? "'".$value : $value;
}

// Additive metadata migration. Existing uploads remain unlabelled until confirmed.
function wac_ensure_metadata($con){
    $exists = mysqli_query($con, "SHOW TABLES LIKE 'tblwaecupload'");
    if(!$exists || mysqli_num_rows($exists) === 0) return false;
    $columns = mysqli_query($con, 'SHOW COLUMNS FROM tblwaecupload');
    $found = array();
    while($row = mysqli_fetch_assoc($columns)) $found[$row['Field']] = true;
    foreach(array('exam_year' => 'SMALLINT UNSIGNED NULL', 'exam_sitting' => "VARCHAR(80) NOT NULL DEFAULT ''", 'batch_label' => "VARCHAR(120) NOT NULL DEFAULT ''") as $name => $definition){
        if(!isset($found[$name])){
            try{
                if(!mysqli_query($con, "ALTER TABLE tblwaecupload ADD COLUMN $name $definition")) throw new RuntimeException('Could not add batch metadata.');
            }catch(mysqli_sql_exception $e){
                // Another request may finish the same additive migration first.
                if((int)$e->getCode() !== 1060) throw $e;
            }
        }
    }
    return true;
}

function wac_metadata($input){
    foreach(array('exam_year','exam_sitting','batch_label') as $key){
        if(!isset($input[$key]) || !is_scalar($input[$key])) throw new InvalidArgumentException('Enter the examination year, sitting and batch label.');
    }
    $year = trim((string)$input['exam_year']);
    $sitting = trim((string)$input['exam_sitting']);
    $label = trim((string)$input['batch_label']);
    if(!preg_match('/^\d{4}$/', $year) || (int)$year < 1950 || (int)$year > 2100) throw new InvalidArgumentException('Enter a four-digit examination year between 1950 and 2100.');
    if($sitting === '' || strlen($sitting) > 80 || $label === '' || strlen($label) > 120) throw new InvalidArgumentException('Enter a sitting (up to 80 bytes) and a batch label (up to 120 bytes).');
    return array('exam_year' => (int)$year, 'exam_sitting' => $sitting, 'batch_label' => $label);
}
function wac_ready($upload){
    try{ wac_metadata($upload); return true; }catch(InvalidArgumentException $e){ return false; }
}
function wac_accessible($upload, $session){
    if(($session['ACCESSLEVEL'] ?? '') === 'administrator' && ($session['SYSTEMTYPE'] ?? '') === 'super_user') return true;
    $branch = trim((string)($session['BRANCHID'] ?? ''));
    return $branch !== '' && $branch === trim((string)($upload['branchid'] ?? ''));
}
function wac_title($upload){ return $upload['exam_year'].' · '.$upload['batch_label'].' · '.$upload['exam_sitting']; }
function wac_selection($values, $uploads){
    if(!is_array($values)) throw new InvalidArgumentException('Select two or three batches.');
    $ids = array();
    foreach($values as $value){
        if(!is_string($value)) throw new InvalidArgumentException('Invalid batch selection.');
        if($value !== '') $ids[] = $value;
    }
    if(count($ids) < 2 || count($ids) > 3) throw new InvalidArgumentException('Select two or three batches.');
    if(count(array_unique($ids)) !== count($ids)) throw new InvalidArgumentException('Choose a different upload in each batch selector.');
    $selected = array();
    foreach($ids as $id){
        if(!isset($uploads[$id])) throw new InvalidArgumentException('A selected batch is unavailable or outside your branch.');
        if(!wac_ready($uploads[$id])) throw new InvalidArgumentException('Label each selected upload with its examination year, sitting and batch name first.');
        $selected[] = $uploads[$id];
    }
    usort($selected, function($a, $b){
        $year = (int)$a['exam_year'] <=> (int)$b['exam_year'];
        return $year !== 0 ? $year : strcmp(wac_title($a).$a['uploadid'], wac_title($b).$b['uploadid']);
    });
    return $selected;
}
function wac_empty_counts(){
    return array('graded'=>0, 'credits'=>0, 'points'=>0, 'X'=>0, 'W'=>0, '*'=>0, 'P'=>0,
        'conflicts'=>0, 'unknown'=>0, 'recorded'=>0, 'grades'=>array_fill_keys(wac_grades(), 0));
}
function wac_finalize($counts){
    $counts['credit_rate'] = wac_percent($counts['credits'], $counts['graded']);
    $counts['average'] = $counts['graded'] > 0 ? $counts['points'] / $counts['graded'] : null;
    return $counts;
}

function wac_achievement_labels(){
    return array(
        'english' => 'English credit',
        'maths' => 'Core Mathematics credit',
        'science' => 'Integrated Science credit',
        'social' => 'Social Studies credit',
        'english_maths' => 'Credits in both English and Core Mathematics',
        'all_core' => 'Credits in all four core subjects',
        'six_credits' => 'At least six credits across distinct subjects'
    );
}

// Input is already deduplicated by candidate and canonical subject. A null grade
// represents a conflicting outcome, never a credit. Return aggregates, not names.
function wac_student_achievement($candidateSubjects){
    $population = count($candidateSubjects);
    $counts = array_fill_keys(array_keys(wac_achievement_labels()), 0);
    $coverage = array('fully_graded_core'=>0, 'missing_core'=>0, 'ungraded_core'=>0);
    $buckets = array_fill_keys(array('0','1','2','3','4','5','6','7','8+'), 0);
    $coreKeys = array_combine(wac_core(), array('english','maths','science','social'));
    foreach($candidateSubjects as $subjects){
        $credits = array();
        foreach($subjects as $subject => $grade){
            $pointIndex = array_search($grade, wac_grades(), true);
            if($pointIndex !== false && $pointIndex <= 5) $credits[$subject] = true;
        }
        $coreCredits = 0; $coreGraded = 0; $missing = false; $ungraded = false;
        foreach($coreKeys as $subject => $key){
            if(isset($credits[$subject])){ $counts[$key]++; $coreCredits++; }
            if(!array_key_exists($subject, $subjects)){ $missing = true; }
            elseif(in_array($subjects[$subject], wac_grades(), true)){ $coreGraded++; }
            else{ $ungraded = true; }
        }
        if(isset($credits['ENGLISH LANG'], $credits['MATHEMATICS(CORE)'])) $counts['english_maths']++;
        if($coreCredits === 4) $counts['all_core']++;
        if(count($credits) >= 6) $counts['six_credits']++;
        if($coreGraded === 4) $coverage['fully_graded_core']++;
        if($missing) $coverage['missing_core']++;
        if($ungraded) $coverage['ungraded_core']++;
        $buckets[count($credits) >= 8 ? '8+' : (string)count($credits)]++;
    }
    $metrics = array();
    foreach($counts as $key => $count){
        $metrics[$key] = array('achieved'=>$count, 'not_confirmed'=>$population-$count,
            'population'=>$population, 'rate'=>wac_percent($count, $population));
    }
    return array('population'=>$population, 'metrics'=>$metrics, 'coverage'=>$coverage, 'credit_buckets'=>$buckets);
}

function wac_achievement_csv_rows($batches){
    $rows = array(array('Student achievement method', 'Confirmed students / all identifiable candidates in the upload x 100, including status-only candidates. Not confirmed does not mean failed. No registration roster or admission decision is implied.'));
    $rows[] = array('Achievement','Year','Sitting','Batch','Upload ID','Confirmed students','Candidates in upload','Confirmed %','Not confirmed','Change vs previous batch (pp)');
    foreach(wac_achievement_labels() as $key => $label){
        $previous = null;
        foreach($batches as $batch){
            $metric = $batch['analysis']['achievement']['metrics'][$key];
            $delta = wac_delta($previous, $metric['rate']);
            $rows[] = array($label,$batch['exam_year'],$batch['exam_sitting'],$batch['batch_label'],$batch['uploadid'],
                $metric['achieved'],$metric['population'],$metric['rate'] === null ? 'N/A' : round($metric['rate'],4),
                $metric['not_confirmed'],$delta === null ? 'N/A' : round($delta,4));
            $previous = $metric['rate'];
        }
    }
    $rows[] = array();
    $rows[] = array('Student-level coverage','Batch','Upload ID','Candidates','All four cores graded','Missing at least one core outcome','At least one ungraded/conflicting core outcome');
    foreach($batches as $batch){
        $a = $batch['analysis']['achievement']; $c = $a['coverage'];
        $rows[] = array('Counts of students; missing and ungraded groups may overlap',wac_title($batch),$batch['uploadid'],$a['population'],$c['fully_graded_core'],$c['missing_core'],$c['ungraded_core']);
    }
    $rows[] = array(); $rows[] = array('Observed credit count','Batch','Upload ID','Students','Candidates','% of candidates');
    foreach($batches as $batch){
        $a = $batch['analysis']['achievement'];
        foreach($a['credit_buckets'] as $bucket => $count){
            $rate = wac_percent($count,$a['population']);
            $rows[] = array((string)$bucket,wac_title($batch),$batch['uploadid'],$count,$a['population'],$rate === null ? 'N/A' : round($rate,4));
        }
    }
    return $rows;
}

// Pure calculation: one outcome per candidate and canonical subject. No writes.
function wac_analyze($rows, $includeStudents = false){
    $pairs = array(); $candidates = array(); $unidentified = 0; $duplicates = 0;
    $identities = array(); $studentOutcomes = array();
    foreach($rows as $row){
        $number = preg_replace('/\s+/', '', trim((string)($row['candidate_no'] ?? '')));
        $name = strtoupper(preg_replace('/\s+/', ' ', trim((string)($row['student_name'] ?? ''))));
        $subject = waec_normalize_subject($row['subject_name'] ?? '');
        if(($number === '' && $name === '') || $subject === ''){ $unidentified++; continue; }
        $candidate = $number !== '' ? 'CAND:'.$number : 'NAME:'.$name;
        $candidates[$candidate] = true;
        if($includeStudents){
            if(!isset($identities[$candidate])) $identities[$candidate] = array('number'=>$number, 'names'=>array());
            if($name !== '') $identities[$candidate]['names'][$name] = true;
        }
        $grade = strtoupper(preg_replace('/\s+/', '', (string)($row['grade'] ?? '')));
        $key = json_encode(array($candidate, $subject));
        if(!isset($pairs[$key])) $pairs[$key] = array('candidate'=>$candidate, 'subject'=>$subject, 'outcomes'=>array());
        if(isset($pairs[$key]['outcomes'][$grade])) $duplicates++;
        $pairs[$key]['outcomes'][$grade] = true;
    }
    $subjects = array(); $total = wac_empty_counts(); $signature = array();
    $candidateSubjects = array_fill_keys(array_keys($candidates), array());
    foreach($pairs as $key => $pair){
        $subject = $pair['subject'];
        if(!isset($subjects[$subject])) $subjects[$subject] = wac_empty_counts();
        $outcomes = array_keys($pair['outcomes']); sort($outcomes, SORT_STRING);
        $signature[$key] = $outcomes;
        $grade = count($outcomes) === 1 ? $outcomes[0] : null;
        $candidateSubjects[$pair['candidate']][$subject] = $grade;
        if($includeStudents) $studentOutcomes[$pair['candidate']][$subject] = $outcomes;
        $index = array_search($grade, wac_grades(), true);
        foreach(array(&$subjects[$subject], &$total) as &$counts){
            $counts['recorded']++;
            if(count($outcomes) > 1){ $counts['conflicts']++; }
            elseif($index !== false){
                $counts['graded']++; $counts['points'] += $index + 1; $counts['grades'][$grade]++;
                if($index <= 5) $counts['credits']++;
            }elseif(in_array($grade, array('X','W','*','P'), true)){ $counts[$grade]++; }
            else{ $counts['unknown']++; }
        }
        unset($counts);
    }
    foreach(wac_core() as $core){ if(!isset($subjects[$core])) $subjects[$core] = wac_empty_counts(); }
    foreach($subjects as $subject => &$counts){
        $counts = wac_finalize($counts);
        $counts['missing'] = in_array($subject, wac_core(), true) ? max(0, count($candidates) - $counts['recorded']) : null;
    }
    unset($counts);
    ksort($subjects); ksort($signature);
    $analysis = array('candidates'=>count($candidates), 'subjects'=>$subjects, 'overall'=>wac_finalize($total),
        'duplicates'=>$duplicates, 'unidentified'=>$unidentified,
        'fingerprint'=>hash('sha256', json_encode($signature)),
        'achievement'=>wac_student_achievement($candidateSubjects));
    if($includeStudents){
        $analysis['students'] = array();
        foreach($candidateSubjects as $key => $outcomes){
            ksort($outcomes);
            $names = array_keys($identities[$key]['names']); sort($names, SORT_STRING);
            $achievement = wac_student_achievement(array($outcomes));
            $credits = 0;
            foreach($outcomes as $grade){
                if(in_array($grade, array_slice(wac_grades(),0,6), true)) $credits++;
            }
            ksort($studentOutcomes[$key]);
            $analysis['students'][] = array('key'=>$key, 'number'=>$identities[$key]['number'],
                'name'=>implode(' / ', $names), 'name_conflict'=>count($names)>1,
                'subjects'=>$outcomes, 'outcomes'=>$studentOutcomes[$key], 'credits'=>$credits,
                'metrics'=>$achievement['metrics']);
        }
        usort($analysis['students'], function($a,$b){ return strcmp($a['number'],$b['number']) ?: strcmp($a['name'],$b['name']); });
    }
    return $analysis;
}

function wac_student_url($uploadId, $metric = 'six_credits', $group = 'confirmed'){
    return 'waec-achievement-students.php?'.http_build_query(array('upload'=>$uploadId,'metric'=>$metric,'group'=>$group));
}
function wac_flag_labels(){
    return array('below_credit'=>'Below credit (D7–F9)', 'absent'=>'Absent (X)',
        'withheld'=>'Withheld (W)', 'source_status'=>'Source marker (* / P)',
        'missing'=>'Missing core outcome', 'conflict'=>'Conflicting outcomes', 'unknown'=>'Unrecognised outcome');
}
// Flags describe recorded outcomes in the selected criterion, not failure or admission eligibility.
function wac_student_flags($student, $metric){
    $scopes = array('english'=>array(wac_core()[0]), 'maths'=>array(wac_core()[1]),
        'science'=>array(wac_core()[2]), 'social'=>array(wac_core()[3]),
        'english_maths'=>array_slice(wac_core(),0,2), 'all_core'=>wac_core());
    $scope = $scopes[$metric] ?? array_unique(array_merge(wac_core(),array_keys($student['subjects'])));
    $flags = array();
    foreach($scope as $subject){
        if(!array_key_exists($subject,$student['subjects'])){ $flags['missing'] = true; continue; }
        $grade = $student['subjects'][$subject];
        if($grade === null){ $flags['conflict'] = true; continue; }
        if(in_array($grade,array('D7','E8','F9'),true)) $flags['below_credit'] = true;
        elseif($grade === 'X') $flags['absent'] = true;
        elseif($grade === 'W') $flags['withheld'] = true;
        elseif($grade === '*' || $grade === 'P') $flags['source_status'] = true;
        elseif(!in_array($grade,wac_grades(),true)) $flags['unknown'] = true;
    }
    return $flags;
}
function wac_student_filters($input){
    $defaults = array('metric'=>'six_credits','group'=>'confirmed','flag'=>'','q'=>'','page'=>'1');
    $filters = array();
    foreach($defaults as $key=>$default){
        $value = $input[$key] ?? $default;
        if(!is_string($value)) throw new InvalidArgumentException('Invalid student filter.');
        $filters[$key] = trim($value);
    }
    if(!isset(wac_achievement_labels()[$filters['metric']]) || !in_array($filters['group'],array('confirmed','not_confirmed','all'),true)
        || ($filters['flag'] !== '' && !isset(wac_flag_labels()[$filters['flag']]))
        || strlen($filters['q']) > 120 || !preg_match('/^[1-9][0-9]{0,6}$/D',$filters['page'])){
        throw new InvalidArgumentException('Choose a valid achievement, group and outcome filter; search must be 120 bytes or fewer.');
    }
    $filters['page'] = (int)$filters['page'];
    return $filters;
}
function wac_filter_students($students, $filters){
    $filtered = array();
    foreach($students as $student){
        $confirmed = $student['metrics'][$filters['metric']]['achieved'] === 1;
        if($filters['group'] === 'confirmed' && !$confirmed) continue;
        if($filters['group'] === 'not_confirmed' && $confirmed) continue;
        $student['flags'] = wac_student_flags($student,$filters['metric']);
        if($filters['flag'] !== '' && !isset($student['flags'][$filters['flag']])) continue;
        if($filters['q'] !== '' && stripos($student['number'].' '.$student['name'],$filters['q']) === false) continue;
        $filtered[] = $student;
    }
    return $filtered;
}

function wac_load_rows($con, $uploadId){
    $stmt = mysqli_prepare($con, 'SELECT candidate_no,student_name,subject_name,grade FROM tblwaecresult WHERE uploadid=? UNION ALL SELECT candidate_no,student_name,subject_name,grade FROM tblwaecwithheld WHERE uploadid=?');
    mysqli_stmt_bind_param($stmt, 'ss', $uploadId, $uploadId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt); $rows = array();
    while($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function wac_summary($batches){
    $summary = array();
    for($i=1; $i<count($batches); $i++){
        $before = $batches[$i-1]; $after = $batches[$i];
        $delta = wac_delta($before['analysis']['overall']['credit_rate'], $after['analysis']['overall']['credit_rate']);
        $summary[] = wac_title($before).' → '.wac_title($after).': overall subject-entry credit rate '.wac_rate($before['analysis']['overall']['credit_rate']).' → '.wac_rate($after['analysis']['overall']['credit_rate']).' ('.wac_delta_text($delta).').';
    }
    $first = reset($batches); $last = end($batches); $changes = array();
    foreach(array('all_core','six_credits') as $metricKey){
        $before = $first['analysis']['achievement']['metrics'][$metricKey];
        $after = $last['analysis']['achievement']['metrics'][$metricKey];
        $labels = wac_achievement_labels();
        $summary[] = $labels[$metricKey].', first to last batch: '.$before['achieved'].'/'.$before['population'].' students ('.wac_rate($before['rate']).') to '.$after['achieved'].'/'.$after['population'].' ('.wac_rate($after['rate']).'); '.wac_delta_text(wac_delta($before['rate'],$after['rate'])).'. Confirmed achievements only; incomplete records can affect this comparison.';
    }
    foreach($first['analysis']['subjects'] as $subject => $counts){
        if(!isset($last['analysis']['subjects'][$subject])) continue;
        $delta = wac_delta($counts['credit_rate'], $last['analysis']['subjects'][$subject]['credit_rate']);
        if($delta !== null) $changes[$subject] = $delta;
    }
    if($changes){
        arsort($changes); $best = key($changes); $gain = current($changes);
        if($gain >= .05) $summary[] = 'Largest subject credit-rate increase, first to last batch: '.$best.' ('.wac_delta_text($gain).').';
        asort($changes); $worst = key($changes); $decline = current($changes);
        if($decline <= -.05) $summary[] = 'Largest subject credit-rate decline, first to last batch: '.$worst.' ('.wac_delta_text($decline).').';
        if(abs($gain) < .05 && abs($decline) < .05) $summary[] = 'Comparable subject credit rates are unchanged to one decimal place.';
    }else{ $summary[] = 'There are no subjects with graded results in both the first and last batches; subject trends cannot be calculated.'; }
    return $summary;
}
