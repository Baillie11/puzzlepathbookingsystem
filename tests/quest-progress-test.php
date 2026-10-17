<?php
define('ABSPATH',__DIR__);define('OBJECT_K','OBJECT_K');
function add_action(...$a){}
function get_option($name){return false;}
function current_user_can($cap){return true;}
function wp_date($format,$stamp){return gmdate($format,$stamp);}
class PuzzlePath_Audit_Logger {static $events=[];static function log_booking_action(...$args){self::$events[]=$args;}}
require __DIR__.'/../includes/quest-progress.php';
class DB {
 public $prefix='wp_';public $last_error='';public $rows=[];public $queries=[];public $inserted=[];public $fail=false;
 function prepare($sql,...$args){return $sql;}
 function get_var($sql){return strpos($sql,'ENGINE')!==false?'InnoDB':1;}
 function get_results($sql){if(strpos($sql,'t.hunt_id=b.event_id')===false||strpos($sql,"b.payment_status='paid'")===false)throw new RuntimeException('Historical match not constrained');return $this->rows;}
 function query($sql){$this->queries[]=$sql;if(strpos($sql,'INSERT IGNORE INTO wp_pp_booking_progress')===0){if($this->fail)return false;return $this->inserted++?0:1;}return 1;}
}
function check($ok,$msg){if(!$ok)throw new Exception($msg);}
$wpdb=new DB;
$wpdb->rows=[(object)['booking_id'=>7,'booking_code'=>'TEST','tracking_id'=>10,'started'=>time()-3600,'finished'=>time(),'total_time_ms'=>3600000]];
// Only the first matching record for a booking should import.
$wpdb->inserted=0;
$r=puzzlepath_import_legacy_progress();check($r['imported']===1&&in_array('COMMIT',$wpdb->queries,true),'Completion import failed');
check(count(array_filter($wpdb->queries,fn($q)=>strpos($q,'INSERT IGNORE INTO wp_pp_redemptions')===0))===1,'Used flag not saved atomically');
$wpdb->queries=[];$wpdb->rows[0]->finished=0;$wpdb->inserted=0;
$r=puzzlepath_import_legacy_progress();check($r['imported']===1&&count(array_filter($wpdb->queries,fn($q)=>strpos($q,'INSERT IGNORE INTO wp_pp_redemptions')===0))===0,'Start marked booking used');
$wpdb->queries=[];$wpdb->rows[0]->finished=time();$wpdb->rows[0]->total_time_ms=1;
$r=puzzlepath_import_legacy_progress();check($r['skipped']===1&&$r['imported']===0,'Invalid completion evidence imported');
$wpdb->rows[0]->total_time_ms=3600000;$wpdb->fail=true;$wpdb->queries=[];
try{puzzlepath_import_legacy_progress();throw new Exception('Failed import accepted');}catch(RuntimeException $e){check(in_array('ROLLBACK',$wpdb->queries,true),'Failed import did not roll back');}
check(puzzlepath_progress_duration(3661000)==='1:01:01','Duration format incorrect');
check(puzzlepath_progress_duration(null)==='Not recorded','Unknown duration invented');
echo "PASS: historical matching, atomic completion/redemption, start-only evidence, invalid evidence, rollback and duration display.\n";
