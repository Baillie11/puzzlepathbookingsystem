<?php
define('ABSPATH',__DIR__);$filters=[];
function add_action($name,$callback,...$rest){global $actions;$actions[$name][]=$callback;}function add_filter($name,$callback,...$rest){global $filters;$filters[$name]=$callback;}
function wp_script_is(...$args){return true;}function wp_unslash($v){return $v;}
function nocache_headers(){}function wp_date($format,$time){return gmdate($format,$time);}function wp_json_encode($v){return json_encode($v);}
function wp_add_inline_script(...$args){global $inlineScripts;$inlineScripts[]=$args;}
class WP_Error {public $code;public $message;function __construct($c,$m,$d=[]){$this->code=$c;$this->message=$m;}function get_error_message(){return $this->message;}}
class WP_REST_Response {public $data;function __construct($d,$s=200){$this->data=$d;}}
function is_wp_error($v){return $v instanceof WP_Error;}function sanitize_text_field($v){return $v;}function sanitize_email($v){return $v;}function current_time($f){return gmdate('Y-m-d H:i:s');}
class PuzzlePath_Audit_Logger {static function log_booking_created(...$args){}}
eval('namespace Stripe; class Stripe {static function setApiKey($key){}} class PaymentIntent {public static $amount;static function create($data){self::$amount=$data["amount"];return (object)["id"=>"pi_fixture","client_secret"=>"fixture_secret"];}}');
class RepeatDatabase {
 public $prefix='wp2s_';public $insert_id=101;public $booking;public $offer;public $event;
 function __construct(){$this->offer=(object)['email_hash'=>hash('sha256','fixture@example.test'),'expires_at'=>gmdate('Y-m-d H:i:s',time()+3600),'payment_status'=>'paid','quest_status'=>'completed'];$this->event=(object)['id'=>23,'title'=>'Fixture','hunt_code'=>'FIXTURE','price'=>19.95,'seats'=>49,'display_on_site'=>1];}
 function prepare($sql,...$args){return [$sql,$args];}
 function get_row($q){[$sql,$args]=$q;if(strpos($sql,'pp_repeat_hunter_offers')!==false)return $this->offer;if(strpos($sql,'pp_events')!==false)return $this->event;if(strpos($sql,'pp_coupons')!==false)return(object)['id'=>9,'discount_percent'=>25];throw new Exception('Unexpected query');}
 function insert($table,$row){$this->booking=$row;return 1;}function update(...$args){return 1;}
}
require __DIR__.'/../includes/repeat-hunter.php';
function rhCheck($ok,$message){if(!$ok)throw new Exception($message);}
$wpdb=new RepeatDatabase;
rhCheck(pp_rh_cents(19.95,1)===['subtotal_cents'=>1995,'discount_cents'=>499,'total_cents'=>1496],'One carload cents mismatch');
rhCheck(pp_rh_cents(19.95,2)===['subtotal_cents'=>3990,'discount_cents'=>997,'total_cents'=>2993],'Multiple carload rounding mismatch');
rhCheck(pp_rh_validate($wpdb->offer,' FIXTURE@example.test ')===true,'Normalised email failed');
rhCheck(is_wp_error(pp_rh_validate($wpdb->offer,'other@example.test')),'Different customer accepted');
$boundary=strtotime($wpdb->offer->expires_at.' UTC');rhCheck(is_wp_error(pp_rh_validate($wpdb->offer,null,$boundary)),'Expiry boundary accepted');
class RepeatRequest {public $params;function __construct($p){$this->params=$p;}function get_route(){return '/puzzlepath/v1/payment/create-intent';}function get_json_params(){return $this->params;}function set_param($key,$value){$this->params[$key]=$value;}}
$code='RH-'.str_repeat('a',32);$_COOKIE['PPRepeatHunter']=$code;
$wpdb->offer->coupon_code=$code;$wpdb->offer->customer_name='Fixture';$wpdb->offer->customer_email='fixture@example.test';
$inlineScripts=[];$_GET=[];
$actions['wp_enqueue_scripts'][0]();rhCheck(count($inlineScripts)===0,'Ordinary booking activated stale cookie offer');
$_GET=['repeat_offer'=>$code];$actions['wp_enqueue_scripts'][0]();rhCheck(count($inlineScripts)===1,'Dashboard/leaderboard checkout did not activate offer');
$_GET=['repeat_offer'=>'RH-'.str_repeat('b',32)];$actions['wp_enqueue_scripts'][0]();rhCheck(count($inlineScripts)===1,'Mismatched handoff activated offer');$_GET=[];
$ordinary=new RepeatRequest(['event_id'=>23,'tickets'=>1,'email'=>'fixture@example.test','coupon_code'=>'']);
rhCheck($filters['rest_request_before_callbacks'](null,null,$ordinary)===null&&$ordinary->params['coupon_code']==='','Stale cookie silently applied discount to ordinary payment');
$params=['event_id'=>23,'tickets'=>1,'name'=>'Fixture','email'=>'fixture@example.test','coupon_code'=>$code];$request=new RepeatRequest($params);
rhCheck($filters['rest_request_before_callbacks'](null,null,$request)===null,'Valid request rejected');
$bad=$params;$bad['email']='other@example.test';rhCheck(is_wp_error($filters['rest_request_before_callbacks'](null,null,new RepeatRequest($bad))),'Payment accepted another email');
$bad=$params;$bad['tickets']=0;rhCheck(is_wp_error($filters['rest_request_before_callbacks'](null,null,new RepeatRequest($bad))),'Zero quantity accepted');
unset($_COOKIE['PPRepeatHunter']);rhCheck(is_wp_error($filters['rest_request_before_callbacks'](null,null,$request)),'Missing dashboard handoff accepted');$_COOKIE['PPRepeatHunter']=$code;
$source=file_get_contents(__DIR__.'/../puzzlepath-booking.php');$start=strpos($source,'        public function create_payment_intent(');$end=strpos($source,'        public function handle_webhook(',$start);
eval('class RepeatPaymentFixture {'.substr($source,$start,$end-$start).' function get_stripe_keys(){return ["secret"=>"fixture"]; } function generate_unique_booking_code($event){return "FIXTURE-BOOKING";} function process_free_booking(...$args){throw new Exception("Unexpected free booking");}}');
$response=(new RepeatPaymentFixture)->create_payment_intent($request);rhCheck($response instanceof WP_REST_Response&&\Stripe\PaymentIntent::$amount===1496&&$wpdb->booking['total_price']===14.96&&$wpdb->booking['coupon_id']===9,'Stripe amount or persisted discount mismatch');
$params['tickets']=2;(new RepeatPaymentFixture)->create_payment_intent(new RepeatRequest($params));rhCheck(\Stripe\PaymentIntent::$amount===2993&&$wpdb->booking['total_price']===29.93,'Quantity Stripe amount mismatch');
echo "PASS: expiry, customer binding, handoff, quantity, cent rounding, stored coupon and actual Stripe amount construction (mock API).\n";
