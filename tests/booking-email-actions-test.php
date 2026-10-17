<?php
// Isolated mail stubs: this test never contacts a mail service.
define('ABSPATH', __DIR__);
function add_action($hook,$callback,$priority=10) { $GLOBALS['hooks'][$hook][] = [$callback,$priority]; }
function current_user_can($cap) { return true; }
function absint($v) { return abs((int)$v); }
function admin_url($path) { return 'https://example.test/wp-admin/'.$path; }
function add_query_arg($key,$value=null,$url=null) {
    if (is_array($key)) { $url=$value; $args=$key; } else { $args=[$key=>$value]; }
    return $url.'&'.http_build_query($args);
}
function check_admin_referer($action) { if ($action!=='puzzlepath_resend_165') throw new Exception('Wrong nonce action'); }
function wp_safe_redirect($url) { echo json_encode(['url'=>$url,'sends'=>PuzzlePath_Stripe_Integration::$sends,'headers_sent'=>headers_sent()]); }
class PuzzlePath_Stripe_Integration {
    public static $result=true; public static $sends=0;
    static function get_instance() { return new self; }
    function send_confirmation_email($booking,$code) { self::$sends++; if (self::$result==='throw') throw new RuntimeException('Fixture failure'); return self::$result; }
}
class PuzzlePath_Audit_Logger { static $count=0; static function log_booking_action(...$args) { self::$count++; } }
$wpdb = new class {
    public $prefix='wp_'; public $booking;
    function prepare($sql,...$args) {return $sql;}
    function get_row($sql) {return $this->booking;}
};
$source=str_replace("\r\n", "\n", file_get_contents(__DIR__.'/../puzzlepath-booking.php'));
$start=strpos($source,'function puzzlepath_resend_confirmation_email(');
$end=strpos($source,'/**' . "\n" . ' * Export bookings to CSV',$start);
eval(substr($source,$start,$end-$start));
require __DIR__.'/../includes/booking-email-actions.php';
$wpdb->booking=(object)['id'=>165,'payment_status'=>'paid','booking_code'=>'FIXTURE'];
if (($argv[1]??'')==='route') {
    $_GET=['page'=>'puzzlepath-bookings','action'=>'resend_email','booking_id'=>165];
    puzzlepath_handle_booking_email_actions();
    throw new Exception('Expected exit after redirect');
}
function check($ok,$message) {if (!$ok) throw new Exception($message);}
$wpdb->booking=null;
check(!puzzlepath_resend_confirmation_email(165)['success'] && PuzzlePath_Stripe_Integration::$sends===0,'Missing booking sent');
$wpdb->booking=(object)['id'=>165,'payment_status'=>'unpaid','booking_code'=>'FIXTURE'];
check(!puzzlepath_resend_confirmation_email(165)['success'] && PuzzlePath_Stripe_Integration::$sends===0,'Unpaid booking sent');
$wpdb->booking->payment_status='paid';
PuzzlePath_Stripe_Integration::$result=false;
check(!puzzlepath_resend_confirmation_email(165)['success'] && PuzzlePath_Audit_Logger::$count===0,'Mail rejection reported success');
PuzzlePath_Stripe_Integration::$result=true;
check(puzzlepath_resend_confirmation_email(165)['success'] && PuzzlePath_Audit_Logger::$count===1,'Accepted email not logged');
check(in_array(['puzzlepath_handle_booking_email_actions',1],$GLOBALS['hooks']['admin_init'],true),'Not registered before admin output');
$process=proc_open([PHP_BINARY,__FILE__,'route'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
$output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
$result=json_decode($output,true);
check($exit===0 && $result['sends']===1 && !$result['headers_sent'] && strpos($result['url'],'message=email_sent')!==false,'Early redirect failed: '.$errors.$output);
echo "PASS: paid-only resend, accurate mail result, audit log, single send and redirect before output.\n";
