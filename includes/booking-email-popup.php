<?php
defined('ABSPATH') || exit;

/** Show confirmation only on the successful single-email redirect. */
add_action('admin_footer', function () {
    if (($_GET['page'] ?? '') !== 'puzzlepath-bookings' || ($_GET['message'] ?? '') !== 'email_sent' || !current_user_can('manage_options')) { return; }
    ?>
    <dialog id="pp-email-sent-dialog" aria-labelledby="pp-email-sent-title" aria-describedby="pp-email-sent-description" style="border:0;border-radius:10px;padding:28px;max-width:420px;width:calc(100% - 80px);box-shadow:0 12px 45px #0005;">
        <h2 id="pp-email-sent-title" style="margin:0 0 14px;color:#00802b;">Email sent</h2>
        <p id="pp-email-sent-description" style="font-size:15px;line-height:1.6;margin:0 0 22px;">Booking Confirmation email has been sent.</p>
        <form method="dialog" style="text-align:right;margin:0;"><button class="button button-primary" autofocus>OK</button></form>
    </dialog>
    <style>#pp-email-sent-dialog::backdrop{background:rgba(0,0,0,.4)}</style>
    <script>
    (function () {
        var dialog = document.getElementById('pp-email-sent-dialog');
        if (!dialog) return;
        dialog.showModal();
        // Refreshing or returning to this page should not repeat the confirmation.
        var url = new URL(window.location.href);
        if (url.searchParams.get('message') === 'email_sent') {
            url.searchParams.delete('message');
            window.history.replaceState(null, '', url.toString());
        }
    }());
    </script>
    <?php
});
