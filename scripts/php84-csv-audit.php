<?php
// CLI-only, read-only audit. --patch-json emits proposed line edits for review.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$changes = array(); $calls = 0;
foreach (glob(dirname(__DIR__).'/*.php') as $file) {
    $source = file_get_contents($file);
    $tokens = token_get_all($source); $offset = 0; $edits = array();
    foreach ($tokens as $i=>$token) {
        $text = is_array($token) ? $token[1] : $token;
        if (is_array($token) && $token[0] === T_STRING && in_array(strtolower($text), array('fgetcsv','fputcsv','str_getcsv'), true)) {
            $name = strtolower($text); $j = $i+1; $pos = $offset+strlen($text);
            while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0]===T_WHITESPACE) { $pos+=strlen($tokens[$j][1]); $j++; }
            if (($tokens[$j]??null) !== '(') { throw new RuntimeException('Unexpected CSV syntax'); }
            $depth = 0; $args = 1;
            for (; $j<count($tokens); $j++) {
                $part=$tokens[$j]; $value=is_array($part)?$part[1]:$part;
                if (!is_array($part)) {
                    if (in_array($part,array('(','[','{'),true)) $depth++;
                    if (in_array($part,array(')',']','}'),true)) $depth--;
                    if ($part===',' && $depth===1) $args++;
                    if ($depth===0) break;
                }
                $pos+=strlen($value);
            }
            $defaults = $name==='fgetcsv' ? array('null', 'null', '0', "','", "'\"'", "'\\\\'")
                : ($name==='fputcsv' ? array('null','null','null',"','","'\"'","'\\\\'") : array('null','null',"','","'\"'","'\\\\'"));
            $required = $name==='str_getcsv' ? 4 : 5;
            if ($args < $required) {
                $extra=array(); for($a=$args+1;$a<=$required;$a++) $extra[]=$defaults[$a];
                $edits[]=array($pos, ', '.implode(', ', $extra)); $calls++;
            }
        }
        $offset+=strlen($text);
    }
    $updated=$source;
    foreach(array_reverse($edits) as $edit) $updated=substr_replace($updated,$edit[1],$edit[0],0);
    $before=explode("\n",str_replace("\r\n","\n",$source)); $after=explode("\n",str_replace("\r\n","\n",$updated));
    foreach($before as $i=>$line) if($line!==$after[$i]) $changes[]=array('file'=>basename($file),'old'=>$line,'new'=>$after[$i]);
}
if(in_array('--patch-json',$argv,true)) echo json_encode($changes,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
else { echo "$calls CSV calls rely on implicit escape defaults.\n"; exit($calls?1:0); }
