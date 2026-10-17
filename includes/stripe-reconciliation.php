<?php
/** Verified Stripe reconciliation shared by webhooks and the WP-CLI recovery tool. */
defined('ABSPATH') || exit;

trait PuzzlePath_Stripe_Reconciliation {
    public static function booking_status_for_intent($status) {
        if (in_array($status, ['requires_payment_method', 'requires_confirmation'], true)) { return 'unpaid'; }
        if (in_array($status, ['processing', 'requires_action', 'requires_capture'], true)) { return 'pending'; }
        if ($status === 'canceled') { return 'cancelled'; }
        return null;
    }
    public function enable_payment_status_events() {
        $keys = $this->get_stripe_keys();
        if (empty($keys['secret'])) { throw new RuntimeException('Stripe key not configured.'); }
        \Stripe\Stripe::setApiKey($keys['secret']);
        $url = rtrim(rest_url('puzzlepath/v1/stripe-webhook'), '/');
        $required = ['payment_intent.succeeded', 'payment_intent.processing', 'payment_intent.amount_capturable_updated', 'payment_intent.requires_action', 'payment_intent.payment_failed', 'payment_intent.canceled'];
        $found = false;
        foreach (\Stripe\WebhookEndpoint::all(['limit' => 100])->autoPagingIterator() as $endpoint) {
            if (rtrim($endpoint->url, '/') !== $url || $endpoint->status !== 'enabled') { continue; }
            $found = true;
            $events = $endpoint->enabled_events;
            if (in_array('*', $events, true)) { continue; }
            $merged = array_values(array_unique(array_merge($events, $required)));
            if (count($merged) !== count($events)) { \Stripe\WebhookEndpoint::update($endpoint->id, ['enabled_events' => $merged]); }
        }
        if (!$found) { throw new RuntimeException('No enabled webhook matches the configured site URL.'); }
    }
    public function reconcile_payment_intent($intent_id, $apply = false, $send_email = false) {
        global $wpdb;
        $table = $wpdb->prefix . 'pp_bookings';
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE stripe_payment_intent_id = %s", $intent_id));
        if (!$booking) {
            return ['result' => 'unmatched'];
        }
        if (!in_array($booking->payment_status, ['pending', 'unpaid'], true)) {
            return ['result' => 'unchanged', 'booking_id' => $booking->id];
        }
        $keys = $this->get_stripe_keys();
        if (empty($keys['secret'])) {
            throw new RuntimeException('Stripe key not configured.');
        }
        \Stripe\Stripe::setApiKey($keys['secret']);
        // Fetch current Stripe state, including refunds/disputes that may postdate success.
        $intent = \Stripe\PaymentIntent::retrieve(['id' => $intent_id, 'expand' => ['latest_charge']]);
        $result = ['booking_id' => $booking->id, 'stripe_status' => $intent->status, 'result' => 'unpaid'];
        $expected_live = !get_option('puzzlepath_stripe_test_mode', true);
        if ((bool) $intent->livemode !== $expected_live || $intent->currency !== 'aud'
            || (int) $intent->amount !== (int) round($booking->total_price * 100)
            || (string) ($intent->metadata->booking_id ?? '') !== (string) $booking->id
            || (string) ($intent->metadata->event_id ?? '') !== (string) $booking->event_id) {
            $result['result'] = 'review'; return $result;
        }
        if ($intent->status !== 'succeeded') {
            $target = self::booking_status_for_intent($intent->status);
            if ($target === null) { $result['result'] = 'review'; return $result; }
            $result['booking_status'] = $target;
            if (!$apply || $booking->payment_status === $target) { $result['result'] = $apply ? 'unchanged' : 'would_set_' . $target; return $result; }
            $changed = $wpdb->update($table, ['payment_status' => $target], ['id' => $booking->id, 'payment_status' => $booking->payment_status, 'stripe_payment_intent_id' => $intent_id]);
            if ($changed === false) { throw new RuntimeException('Could not update payment status.'); }
            if ($changed) { PuzzlePath_Audit_Logger::log_payment_status_changed($booking->id, $booking->payment_status, $target, 'Stripe state verified: ' . $intent->status); }
            $result['result'] = $changed ? 'set_' . $target : 'unchanged';
            return $result;
        }
        $charge = $intent->latest_charge;
        $expected_live = !get_option('puzzlepath_stripe_test_mode', true);
        if ((bool) $intent->livemode !== $expected_live || $intent->currency !== 'aud'
            || (int) $intent->amount_received !== (int) round($booking->total_price * 100)
            || (string) ($intent->metadata->booking_id ?? '') !== (string) $booking->id
            || (string) ($intent->metadata->event_id ?? '') !== (string) $booking->event_id
            || !is_object($charge) || !empty($charge->amount_refunded) || !empty($charge->disputed)) {
            $result['result'] = 'review';
            return $result;
        }
        $result['result'] = 'would_mark_paid';
        if (!$apply) {
            return $result;
        }
        // Require transactional tables so failures cannot leave partially fulfilled bookings.
        foreach (['pp_bookings', 'pp_events', 'pp_coupons'] as $suffix) {
            $engine = $wpdb->get_var($wpdb->prepare(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                $wpdb->prefix . $suffix
            ));
            if (strtoupper((string) $engine) !== 'INNODB') {
                throw new RuntimeException('Reconciliation requires InnoDB tables.');
            }
        }
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Could not start payment transaction.');
        }
        try {
            // Conditional update serializes concurrent deliveries and reconciliation runs.
            $updated = $wpdb->update($table, ['payment_status' => 'paid'],
                ['id' => $booking->id, 'payment_status' => $booking->payment_status, 'stripe_payment_intent_id' => $intent_id]);
            if ($updated === false) {
                throw new RuntimeException('Could not update booking.');
            }
            if ($updated === 0) {
                $wpdb->query('ROLLBACK');
                $result['result'] = 'unchanged';
                return $result;
            }
            $events = $wpdb->prefix . 'pp_events';
            if ($wpdb->query($wpdb->prepare("UPDATE $events SET seats = seats - %d WHERE id = %d", $booking->tickets, $booking->event_id)) !== 1) {
                throw new RuntimeException('Could not update quest seats.');
            }
            if ($booking->coupon_id) {
                $coupons = $wpdb->prefix . 'pp_coupons';
                if ($wpdb->query($wpdb->prepare("UPDATE $coupons SET times_used = times_used + 1 WHERE id = %d", $booking->coupon_id)) !== 1) {
                    throw new RuntimeException('Could not update coupon usage.');
                }
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Could not commit payment update.');
            }
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
        PuzzlePath_Audit_Logger::log_payment_status_changed($booking->id, $booking->payment_status, 'paid', 'Payment verified against Stripe PaymentIntent ' . $intent_id);
        if ($send_email) {
            $this->send_confirmation_email($booking, $booking->booking_code);
        }
        $result['result'] = 'marked_paid';
        return $result;
    }
}
