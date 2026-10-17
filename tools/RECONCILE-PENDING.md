# Recover Pending Stripe bookings

Deploy `puzzlepath-booking.php` and `includes/stripe-reconciliation.php` together. The main file also contains the Quest Name / Travel Mode editor improvements.

In Stripe, subscribe the existing PuzzlePath webhook endpoint to `payment_intent.succeeded`. Existing `charge.succeeded` deliveries remain supported using the charge's `payment_intent` field. Keep signature verification enabled and confirm the endpoint signing secret matches the WordPress Stripe settings.

On the WordPress server, preview with the site's configured Stripe account and test/live mode:

```sh
wp eval-file wp-content/plugins/puzzlepath-booking/tools/reconcile-pending.php
```

Apply verified matches:

```sh
wp eval-file wp-content/plugins/puzzlepath-booking/tools/reconcile-pending.php apply
```

Run from the WordPress root; adjust the plugin folder if needed. The tool reads Pending rows in batches of 100 and retrieves each saved PaymentIntent directly from Stripe. It makes no charges and sends no recovery emails. Only succeeded payments matching live/test mode, AUD amount, booking metadata, and event metadata are promoted. Refunded/disputed payments require review. Unpaid, missing-ID, mismatched and API-error bookings remain unchanged. Reports include booking IDs, results and Stripe payment status, without customer details or keys.

Apply updates booking status, quest seats and coupon usage in one transaction; the three tables must use InnoDB. Conditional status updates prevent duplicate fulfillment by concurrent webhooks or repeated runs. Inspect `review`, `missing_payment_intent`, `unmatched`, and `api_or_database_error` results individually. An absent saved PaymentIntent cannot be safely recovered by guessing from a customer's name/email.

Check the totals, rerun preview, and verify a new test payment through the webhook. Existing paid bookings are skipped. Reconciliation doesn't mark declines or abandoned checkout attempts as Failed because a PaymentIntent may still be retried.

Local regression test:

```sh
php tests/stripe-reconciliation-test.php
```


## Unpaid and Pending statuses (17 October 2026)

New bookings start as Unpaid. Stripe `requires_payment_method` and `requires_confirmation` map to Unpaid; `processing`, `requires_action`, and `requires_capture` map to Pending; `canceled` maps to Cancelled. Verified successful payments follow the existing Paid fulfillment flow. Failed remains available for historical/manual records; a retryable declined attempt is Unpaid.

The reconciliation tool now scans both Unpaid and Pending bookings. Apply mode synchronizes these states without decrementing seats or consuming coupons for non-paid changes. Use PuzzlePath > Stripe Reconciliation > Enable Stripe status events after deployment to add the PaymentIntent lifecycle events to the existing enabled endpoint; existing subscriptions are preserved. Hover over or focus a payment badge in the admin screen for its explanation. An expandable status guide is available on the bookings and edit-booking screens.

Live result: bookings 158, 159, 166, 167 and 168 were verified against Stripe and reclassified as Unpaid. Admin totals: 5 Unpaid, 0 Pending. No charges or recovery emails were sent.
