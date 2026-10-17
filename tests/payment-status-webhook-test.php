<?php
namespace Stripe {
    class Webhook {
        public static $event;
        public static function constructEvent($body, $signature, $secret) {
            if ($signature !== 'valid') { throw new \UnexpectedValueException('Bad payload'); }
            return self::$event;
        }
    }
    class WebhookEndpoint {
        public static $updated = [];
        public static function all($args) {
            return new class {
                public function autoPagingIterator() {
                    return [(object)['id'=>'we_fixture','url'=>'https://example.test/wp-json/puzzlepath/v1/stripe-webhook','status'=>'enabled','enabled_events'=>['charge.succeeded']]];
                }
            };
        }
        public static function update($id,$args) { self::$updated = $args['enabled_events']; }
    }
}
namespace {
    require __DIR__ . '/stripe-reconciliation-test.php';
    function rest_url($path) { return 'https://example.test/wp-json/'.$path; }
    function error_log_for_test($message) {}
    class WP_Error { public $code; public function __construct($code,...$args) { $this->code=$code; } }
    class WP_REST_Response { public $data; public function __construct($data,...$args) { $this->data=$data; } }
    $source=file_get_contents(__DIR__.'/../puzzlepath-booking.php');
    $start=strpos($source,'        public function handle_webhook(');
    $end=strpos($source,'        public function send_confirmation_email',$start);
    eval('class WebhookService extends Service {'.substr($source,$start,$end-$start).'}');
    class Request {
        public $signature='valid';
        function get_body(){return '{}';}
        function get_header($name){return $this->signature;}
    }
    foreach (['payment_intent.succeeded','charge.succeeded','payment_intent.processing','payment_intent.amount_capturable_updated','payment_intent.requires_action','payment_intent.payment_failed','payment_intent.canceled'] as $type) {
        fixture(); $service=new WebhookService;
        $state=['payment_intent.processing'=>'processing','payment_intent.amount_capturable_updated'=>'requires_capture','payment_intent.requires_action'=>'requires_action','payment_intent.payment_failed'=>'requires_payment_method','payment_intent.canceled'=>'canceled'][$type] ?? 'succeeded';
        \Stripe\PaymentIntent::$record->status=$state;
        \Stripe\Webhook::$event=(object)['type'=>$type,'data'=>(object)['object'=>(object)['id'=>'pi_fixture','payment_intent'=>'pi_fixture']]];
        $response=$service->handle_webhook(new Request);
        check($response instanceof WP_REST_Response,'Webhook failed: '.$type);
        $expected=$state==='succeeded'?'paid':Service::booking_status_for_intent($state);
        check($wpdb->booking->payment_status===$expected,'Wrong webhook state: '.$type);
        check($service->emails===($expected==='paid'?1:0),'Unexpected email');
    }
    fixture(); $service=new WebhookService; $request=new Request; $request->signature='invalid';
    check($service->handle_webhook($request)->code==='invalid_payload','Bad payload accepted');
    $service->enable_payment_status_events();
    check(in_array('charge.succeeded',\Stripe\WebhookEndpoint::$updated,true),'Existing webhook event removed');
    foreach (['payment_intent.succeeded','payment_intent.processing','payment_intent.amount_capturable_updated','payment_intent.requires_action','payment_intent.payment_failed','payment_intent.canceled'] as $type) {
        check(in_array($type,\Stripe\WebhookEndpoint::$updated,true),'Missing subscription: '.$type);
    }
    echo "PASS: Stripe event handling, signature rejection, payment notifications, preserving existing webhook subscriptions.\n";
}
