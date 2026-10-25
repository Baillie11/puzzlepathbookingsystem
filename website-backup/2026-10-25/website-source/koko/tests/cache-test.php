<?php
require __DIR__.'/../src/core.php';
$storage=sys_get_temp_dir().'/pp-cache-test-'.bin2hex(random_bytes(6));mkdir($storage);
$data=json_decode(file_get_contents(__DIR__.'/catalogue-snapshot.json'),true);
function verify(bool $test,string $name):void {if(!$test)throw new RuntimeException($name);echo "PASS $name\n";}
$fail=function(){throw new RuntimeException('Simulated timeout');};
try {
 $a=catalogue('production',$storage,false,fn()=>$data);verify(count($a['quests'])===11&&!$a['stale'],'initial remote fetch');
 $a=catalogue('production',$storage,false,$fail);verify(!$a['stale'],'five minute cache avoids request');
 $a=catalogue('production',$storage,true,$fail);verify($a['stale']&&count($a['quests'])===11,'handoff fresh validation detects outage and keeps stale display');
 touch($storage.'/catalogue.json',time()-90000);clearstatcache();
 $a=catalogue('production',$storage,false,$fail);verify($a['stale']&&count($a['quests'])===0,'expired cache is not displayed');
 $a=catalogue('production',$storage,true,fn()=>['hunts'=>[]]);verify(!$a['stale']&&count($a['quests'])===0,'authoritative empty catalogue removes old quests');
}finally{unlink($storage.'/catalogue.json');rmdir($storage);}
