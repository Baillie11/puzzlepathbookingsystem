<?php
// Reuse the payment fixture that executes the production create_payment_intent method.
require __DIR__ . '/repeat-hunter-test.php';
class DiscountDatabase extends RepeatDatabase {
    public $available = true;
    public function get_row($query) {
        if (strpos($query[0], 'pp_coupons') !== false && !$this->available) return null;
        return parent::get_row($query);
    }
}
$wpdb = new DiscountDatabase;
$params = ['event_id'=>23, 'tickets'=>2, 'name'=>'Fixture', 'email'=>'fixture@example.test', 'coupon_code'=>'BROADBEACHCOLLECTIVE'];
$result = (new RepeatPaymentFixture)->create_payment_intent(new RepeatRequest($params));
rhCheck($result instanceof WP_REST_Response && $wpdb->booking['coupon_id'] === 9 && \Stripe\PaymentIntent::$amount === 2993, 'QR coupon not applied to actual payment amount');
$wpdb->available = false;
$wpdb->booking = null;
\Stripe\PaymentIntent::$amount = null;
$result = (new RepeatPaymentFixture)->create_payment_intent(new RepeatRequest($params));
rhCheck(is_wp_error($result) && $result->code === 'invalid_coupon', 'Unavailable voucher silently accepted');
rhCheck($wpdb->booking === null && \Stripe\PaymentIntent::$amount === null, 'Invalid voucher created a booking or payment');
echo "PASS: ordinary QR discount reaches payment; unavailable voucher cannot fall back to full price.\n";
