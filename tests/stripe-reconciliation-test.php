<?php
namespace Stripe {
    class Stripe { public static function setApiKey($key) {} }
    class PaymentIntent { public static $record; public static function retrieve($args) { return self::$record; } }
}
namespace {
    define('ABSPATH', __DIR__);
    require __DIR__ . '/../includes/stripe-reconciliation.php';
    function get_option($name, $default = null) { return false; }
    class PuzzlePath_Audit_Logger { public static $calls = 0; public static function log_payment_status_changed(...$args) { self::$calls++; } }
    class Service {
        use \PuzzlePath_Stripe_Reconciliation;
        public $emails = 0;
        private function get_stripe_keys() { return ['secret' => 'test-fixture']; }
        private function send_confirmation_email(...$args) { $this->emails++; }
    }
    class Database {
        public $prefix = 'wp_'; public $booking; public $queries = []; public $updates = 0; public $fail = false;
        function prepare($sql, ...$args) { return $sql; }
        function get_row($sql) { return $this->booking; }
        function get_var($sql) { return 'InnoDB'; }
        function update($table, $data, $where) {
            $this->updates++;
            if ($this->booking->payment_status !== $where['payment_status']) return 0;
            $this->booking->payment_status = $data['payment_status']; return 1;
        }
        function query($sql) {
            $this->queries[] = $sql;
            if ($this->fail && strpos($sql, 'UPDATE') === 0) return false;
            return 1;
        }
    }
    function check($value, $message) { if (!$value) throw new \Exception($message); }
    function fixture() {
        global $wpdb;
        $wpdb = new Database;
        $wpdb->booking = (object) ['id'=>7,'event_id'=>9,'payment_status'=>'pending','total_price'=>19.95,'tickets'=>2,'coupon_id'=>3,'booking_code'=>'ABC'];
        \Stripe\PaymentIntent::$record = (object) ['status'=>'succeeded','livemode'=>true,'currency'=>'aud','amount'=>1995,'amount_received'=>1995,'metadata'=>(object)['booking_id'=>'7','event_id'=>'9'],'latest_charge'=>(object)['amount_refunded'=>0,'disputed'=>false]];
        return new Service;
    }
    $svc = fixture();
    check($svc->reconcile_payment_intent('pi_fixture')['result']==='would_mark_paid', 'Preview mismatch');
    check($wpdb->updates===0, 'Preview changed data');
    check($svc->reconcile_payment_intent('pi_fixture',true)['result']==='marked_paid', 'Apply failed');
    check($svc->emails===0, 'Recovery sent email');
    check($svc->reconcile_payment_intent('pi_fixture',true)['result']==='unchanged', 'Duplicate not ignored');
    check($wpdb->updates===1, 'Duplicate fulfillment');
    foreach (['processing','requires_action','requires_payment_method','canceled'] as $status) {
        $svc=fixture(); \Stripe\PaymentIntent::$record->status=$status;
        $target = $status === 'requires_payment_method' ? 'unpaid' : ($status === 'canceled' ? 'cancelled' : 'pending');
        $result=$svc->reconcile_payment_intent('pi_fixture',true);
        check($wpdb->booking->payment_status===$target, 'Wrong mapped status: '.$status);
        check(count($wpdb->queries)===0, 'Non-paid state changed inventory');
    }
    foreach (['amount','currency','mode','metadata','refund','dispute'] as $case) {
        $svc=fixture(); $pi=\Stripe\PaymentIntent::$record;
        if ($case==='amount') $pi->amount_received=1;
        if ($case==='currency') $pi->currency='usd';
        if ($case==='mode') $pi->livemode=false;
        if ($case==='metadata') $pi->metadata->booking_id='8';
        if ($case==='refund') $pi->latest_charge->amount_refunded=100;
        if ($case==='dispute') $pi->latest_charge->disputed=true;
        check($svc->reconcile_payment_intent('pi_fixture',true)['result']==='review' && $wpdb->updates===0, 'Unsafe payment applied: '.$case);
    }
    $svc=fixture(); $wpdb->fail=true;
    try { $svc->reconcile_payment_intent('pi_fixture',true); throw new \Exception('Failure not detected'); }
    catch (\RuntimeException $e) { check(in_array('ROLLBACK',$wpdb->queries,true), 'Missing rollback'); }
    $svc=fixture(); $wpdb->booking=null;
    check($svc->reconcile_payment_intent('pi_fixture',true)['result']==='unmatched', 'Unmatched result missing');
    $svc=fixture(); $wpdb->booking->payment_status='unpaid';
    check($svc->reconcile_payment_intent('pi_fixture',true)['result']==='marked_paid', 'Unpaid cannot become paid');
    $svc=fixture(); $wpdb->booking->payment_status='unpaid'; \Stripe\PaymentIntent::$record->status='processing';
    check($svc->reconcile_payment_intent('pi_fixture',false)['result']==='would_set_pending' && $wpdb->booking->payment_status==='unpaid', 'Preview mutated status');
    check($svc->reconcile_payment_intent('pi_fixture',true)['result']==='set_pending', 'Unpaid did not become pending');
    \Stripe\PaymentIntent::$record->status='requires_payment_method';
    check($svc->reconcile_payment_intent('pi_fixture',true)['result']==='set_unpaid', 'Decline did not restore Unpaid');
    foreach (['requires_payment_method'=>'unpaid','requires_confirmation'=>'unpaid','requires_action'=>'pending','requires_capture'=>'pending','processing'=>'pending','canceled'=>'cancelled'] as $state=>$expected) {
        check(Service::booking_status_for_intent($state)===$expected,'Mapping failed');
    }
    echo "PASS: preview, payment verification, duplicate handling, unpaid states, review safeguards, rollback and missing booking.\n";
}
