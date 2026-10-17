<?php
/** Run with: wp eval-file wp-content/plugins/puzzlepath-booking/tools/reconcile-pending.php [apply] */
if (!defined('WP_CLI') || !WP_CLI || !class_exists('PuzzlePath_Stripe_Integration')) {
    throw new RuntimeException('Run using WP-CLI with the PuzzlePath plugin active.');
}
global $wpdb;
$apply = isset($args[0]) && $args[0] === 'apply';
$service = PuzzlePath_Stripe_Integration::get_instance();
$after = 0;
$counts = [];
do {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, stripe_payment_intent_id FROM {$wpdb->prefix}pp_bookings WHERE payment_status IN ('pending', 'unpaid') AND id > %d ORDER BY id LIMIT 100", $after
    ));
    if ($wpdb->last_error) {
        WP_CLI::error('Could not read pending bookings.');
    }
    foreach ($rows as $row) {
        $after = (int) $row->id;
        try {
            $result = empty($row->stripe_payment_intent_id)
                ? ['result' => 'missing_payment_intent']
                : $service->reconcile_payment_intent($row->stripe_payment_intent_id, $apply, false);
        } catch (Exception $e) {
            // No credentials, client secrets, or customer information in the report.
            $result = ['result' => 'api_or_database_error'];
        }
        $status = $result['result'];
        $counts[$status] = ($counts[$status] ?? 0) + 1;
        WP_CLI::log(wp_json_encode(['booking_id' => $row->id] + $result));
    }
} while (count($rows) === 100);
WP_CLI::log(wp_json_encode(['mode' => $apply ? 'apply' : 'preview', 'totals' => $counts]));
