<?php
// CLI-only, zero real database/API calls. Executes the deployed payment method against fakes.
if (PHP_SAPI !== 'cli') exit(1);
$plugin=$argv[1] ?? '';
if (!is_file($plugin)) throw new RuntimeException('Pass the deployed plugin PHP file.');
class WP_Error { public function __construct(public $code,public $message,public $data=[]) {} }
class WP_REST_Response { public function __construct(public $data,public $status=200) {} }
function sanitize_text_field($v){return $v;}
function sanitize_email($v){return $v;}
function current_time($v){return gmdate('Y-m-d H:i:s');}
function is_wp_error($v){return $v instanceof WP_Error;}
class PuzzlePath_Audit_Logger {public static function log_booking_created(...$args){}}
eval('namespace Stripe; class Stripe {static function setApiKey($key){if($key!=="mock-key")throw new \\RuntimeException("Unexpected key");}} class PaymentIntent {static $amount=null; static function create($data){self::$amount=$data["amount"];return (object)["id"=>"pi_mock","client_secret"=>"mock"];}}');
class FakeBookingDatabase {
 public $prefix='wp2s_'; public $insert_id=1; public $booking=null;
 public function prepare($query,...$args){return [$query,$args];}
 public function get_row($query){
  [$sql,$args]=$query;
  if(str_contains($sql,'pp_events'))return (object)['id'=>23,'title'=>'Mock quest','price'=>19.95,'seats'=>99,'display_on_site'=>1,'hunt_code'=>'MOCK'];
  if(str_contains($sql,'pp_coupons'))return $args[0]==='BROCHURE25'?(object)['id'=>123,'code'=>'BROCHURE25','discount_percent'=>25,'max_uses'=>0,'times_used'=>0,'expires_at'=>null]:null;
  throw new RuntimeException('Unexpected query');
 }
 public function insert($table,$row){$this->booking=$row;return 1;}
 public function update(...$args){return 1;}
}
class FakeRequest { public function __construct(private array $params){} public function get_json_params(){return $this->params;} }
$source=file_get_contents($plugin);
$start=strpos($source,'        public function create_payment_intent(');
$end=strpos($source,'        public function handle_webhook(',$start);
if($start===false||$end===false)throw new RuntimeException('Payment method boundaries changed; review before testing.');
$method=substr($source,$start,$end-$start);
eval('class DeployedPaymentCheck {'.$method.' private function get_stripe_keys(){return ["secret"=>"mock-key"]; } private function generate_unique_booking_code($event){return "MOCK";} private function process_free_booking(...$args){throw new RuntimeException("Unexpected free booking");}}');
$wpdb=new FakeBookingDatabase;
foreach([[1,'BROCHURE25',1496],[2,'BROCHURE25',2993],[1,'',1995],[1,'INVALID',null]] as [$quantity,$coupon,$expected]){
 $wpdb->booking=null;\Stripe\PaymentIntent::$amount=null;
 $params=['event_id'=>23,'tickets'=>$quantity,'name'=>'Mock','email'=>'mock@example.test','coupon_code'=>$coupon,'total'=>0,'discount_percent'=>100];
 $response=(new DeployedPaymentCheck)->create_payment_intent(new FakeRequest($params));
 if($expected===null){if(!is_wp_error($response)||$wpdb->booking!==null||\Stripe\PaymentIntent::$amount!==null)throw new RuntimeException('Invalid coupon did not fail closed');}
 elseif(!($response instanceof WP_REST_Response)||\Stripe\PaymentIntent::$amount!==$expected)throw new RuntimeException('Incorrect server amount');
 echo 'PASS quantity='.$quantity.' coupon='.($coupon?:'none').' amount='.($expected??'rejected')."\n";
}
echo 'Method SHA256 '.hash('sha256',$method)."\nNo live booking, database write or Stripe request performed.\n";
