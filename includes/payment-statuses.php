<?php
defined('ABSPATH') || exit;

function puzzlepath_payment_status_help($status) {
    $help = [
        'unpaid' => 'Unpaid: Payment has not completed and is not currently underway. Checkout may be unfinished, or a declined payment needs another attempt.',
        'pending' => 'Pending: Payment is underway. Stripe is processing it, awaiting customer or bank authentication, or awaiting capture. Payment is not yet confirmed.',
        'paid' => 'Paid: Payment has been confirmed, or the booking was completed free of charge.',
        'failed' => 'Failed: A payment or booking was marked as failed. A declined Stripe attempt that can be retried is shown as Unpaid.',
        'cancelled' => 'Cancelled: The payment was explicitly cancelled and cannot complete using the same payment attempt.',
        'refunded' => 'Refunded: The booking has been marked as refunded. Check Stripe for refund details.',
    ];
    return $help[$status] ?? 'Payment status has not been recognised. Review the booking.';
}
function puzzlepath_payment_status_color($status) {
    return ['unpaid'=>'#646970','pending'=>'#996800','paid'=>'#00802b','failed'=>'#b32d2e','cancelled'=>'#646970','refunded'=>'#646970'][$status] ?? '#646970';
}
function puzzlepath_payment_status_badge($status) {
    $help = puzzlepath_payment_status_help($status);
    return '<span class="pp-payment-status" tabindex="0" aria-label="' . esc_attr($help) . '" style="background:' . esc_attr(puzzlepath_payment_status_color($status)) . '">' . esc_html(ucfirst($status)) . '<span class="pp-payment-status-tip" role="tooltip">' . esc_html($help) . '</span></span>';
}
function puzzlepath_payment_status_guide() {
    $html = '<details class="pp-payment-status-guide"><summary>What do the payment statuses mean?</summary><ul>';
    foreach (['unpaid','pending','paid','failed','cancelled','refunded'] as $status) { $html .= '<li>' . esc_html(puzzlepath_payment_status_help($status)) . '</li>'; }
    return $html . '</ul></details>';
}
add_action('admin_head', function () {
    echo '<style>.pp-payment-status{position:relative;display:inline-block;padding:3px 8px;border-radius:3px;color:#fff;font-size:11px;text-transform:uppercase;cursor:help}.pp-payment-status-tip{display:none;position:absolute;z-index:100001;right:0;bottom:calc(100% + 6px);width:280px;padding:10px;border-radius:4px;background:#1d2327;color:#fff;box-shadow:0 2px 6px #0003;text-transform:none;font-size:13px;font-weight:normal;line-height:1.5;white-space:normal}.pp-payment-status:hover>.pp-payment-status-tip,.pp-payment-status:focus>.pp-payment-status-tip{display:block}.pp-payment-status:focus{outline:2px solid #2271b1;outline-offset:2px}.pp-payment-status-guide{margin:12px 0}.pp-payment-status-guide summary{cursor:pointer;font-weight:600}.pp-payment-status-guide li{margin:6px 0}</style>';
});
/** New unpaid bookings should never default to Pending. Existing rows are verified separately. */
add_action('admin_init', function () {
    if (!current_user_can('manage_options') || get_option('puzzlepath_unpaid_default_v1')) { return; }
    global $wpdb;
    if ($wpdb->query("ALTER TABLE {$wpdb->prefix}pp_bookings ALTER COLUMN payment_status SET DEFAULT 'unpaid'") !== false) {
        update_option('puzzlepath_unpaid_default_v1', 1);
    }
});
