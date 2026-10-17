<?php
defined('ABSPATH') || exit;

/** Send and redirect before WordPress prints the admin header or notices. */
function puzzlepath_handle_booking_email_actions() {
    if (($_GET['page'] ?? '') !== 'puzzlepath-bookings') { return; }
    $single = ($_GET['action'] ?? '') === 'resend_email';
    $bulk = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'bulk_email';
    if (!$single && !$bulk) { return; }
    if (!current_user_can('manage_options')) { wp_die('You do not have permission to resend booking emails.'); }
    $base = admin_url('admin.php?page=puzzlepath-bookings');
    if ($single) {
        $booking_id = absint($_GET['booking_id'] ?? 0);
        check_admin_referer('puzzlepath_resend_' . $booking_id);
        $result = puzzlepath_resend_confirmation_email($booking_id);
        $url = $result['success']
            ? add_query_arg('message', 'email_sent', $base)
            : add_query_arg('error', $result['error'], $base);
    } else {
        check_admin_referer('bulk-bookings');
        $ids = array_unique(array_filter(array_map('absint', (array) ($_POST['booking_ids'] ?? []))));
        $sent = 0; $failed = 0;
        foreach ($ids as $id) {
            $result = puzzlepath_resend_confirmation_email($id);
            if ($result['success']) { $sent++; } else { $failed++; }
        }
        $url = add_query_arg(['message'=>'bulk_emails_sent', 'count'=>$sent, 'email_failed'=>$failed], $base);
    }
    wp_safe_redirect($url);
    exit;
}
add_action('admin_init', 'puzzlepath_handle_booking_email_actions', 1);
add_action('admin_notices', function () {
    if (($_GET['page'] ?? '') !== 'puzzlepath-bookings' || !current_user_can('manage_options')) { return; }
    $failed = absint($_GET['email_failed'] ?? 0);
    if ($failed) { echo '<div class="notice notice-error"><p>' . esc_html($failed . ' confirmation email(s) were not sent. Only paid bookings are eligible; check your mail service if sending failed.') . '</p></div>'; }
});
