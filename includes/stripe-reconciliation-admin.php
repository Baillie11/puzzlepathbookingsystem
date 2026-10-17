<?php
defined('ABSPATH') || exit;

/** Authorized administrators can preview and repair existing Unpaid / Pending payments. */
function puzzlepath_stripe_reconciliation_page() {
    if (!current_user_can('manage_options')) { wp_die('Insufficient permissions.'); }
    global $wpdb;
    echo '<div class="wrap"><h1>Stripe Booking Reconciliation</h1><p>Verify Unpaid / Pending bookings directly against Stripe. Recovery sends no emails and creates no charges. Stripe states update Unpaid, Pending and Cancelled; only verified, unrefunded successful payments become Paid.</p>';
    echo '<p>Stripe mode: <strong>' . (get_option('puzzlepath_stripe_test_mode', true) ? 'TEST' : 'LIVE') . '</strong></p>';
    $after = isset($_POST['after_id']) ? absint($_POST['after_id']) : 0;
    $report = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('puzzlepath_reconcile_pending');
        if (($_POST['reconcile_action'] ?? '') === 'enable_events') {
            try { PuzzlePath_Stripe_Integration::get_instance()->enable_payment_status_events(); echo '<div class="notice notice-success"><p>Stripe payment-status events enabled on the existing webhook.</p></div>'; }
            catch (Exception $e) { echo '<div class="notice notice-error"><p>Could not enable Stripe payment-status events. Check the Stripe webhook configuration.</p></div>'; }
        }
        $apply = isset($_POST['reconcile_action']) && $_POST['reconcile_action'] === 'apply';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, stripe_payment_intent_id FROM {$wpdb->prefix}pp_bookings WHERE payment_status IN ('pending', 'unpaid') AND id > %d ORDER BY id LIMIT 20", $after));
        if ($wpdb->last_error) { echo '<div class="notice notice-error"><p>Could not read Unpaid / Pending bookings.</p></div>'; }
        else {
            echo '<h2>' . ($apply ? 'Apply results' : 'Preview results') . '</h2><table class="widefat striped"><thead><tr><th>Booking ID</th><th>Stripe status</th><th>Result</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $after = (int) $row->id;
                try {
                    $result = empty($row->stripe_payment_intent_id) ? ['result' => 'missing_payment_intent'] : PuzzlePath_Stripe_Integration::get_instance()->reconcile_payment_intent($row->stripe_payment_intent_id, $apply, false);
                } catch (Exception $e) { $result = ['result' => 'api_or_database_error']; }
                $status = $result['result'];
                $report[$status] = ($report[$status] ?? 0) + 1;
                echo '<tr><td>' . esc_html($row->id) . '</td><td>' . esc_html($result['stripe_status'] ?? '') . '</td><td>' . esc_html($status) . '</td></tr>';
            }
            echo '</tbody></table><p>Batch totals: <strong>' . esc_html(wp_json_encode($report)) . '</strong></p>';
            $remaining = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}pp_bookings WHERE payment_status IN ('pending', 'unpaid') AND id > %d", $after));
            echo '<p>Unpaid / Pending records after this batch: <strong>' . esc_html($remaining) . '</strong>. Last checked booking ID: <strong>' . esc_html($after) . '</strong>.</p>';
        }
    }
    echo '<p>Total Unpaid / Pending bookings: <strong>' . esc_html($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pp_bookings WHERE payment_status IN ('pending', 'unpaid')")) . '</strong></p>';
    echo '<form method="post">'; wp_nonce_field('puzzlepath_reconcile_pending');
    echo '<label>Start after booking ID <input type="number" min="0" name="after_id" value="0"></label><p><button class="button" name="reconcile_action" value="preview">Preview next 20</button> <button class="button button-primary" name="reconcile_action" value="apply">Sync verified statuses (next 20)</button></p><p><button class="button" name="reconcile_action" value="enable_events">Enable Stripe status events</button></p></form></div>';
}
add_action('admin_menu', function () {
    add_submenu_page('puzzlepath-booking', 'Stripe Reconciliation', 'Stripe Reconciliation', 'manage_options', 'puzzlepath-stripe-reconciliation', 'puzzlepath_stripe_reconciliation_page');
});
