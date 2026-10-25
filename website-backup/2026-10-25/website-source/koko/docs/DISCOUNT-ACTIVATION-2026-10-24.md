# Live discount activation — 24 October 2026

Booking and automatic brochure discount are enabled in production.

Brochure URL: https://www.puzzlepath.com.au/landingpage/?campaign=dl-brochure
Direct visits without the campaign retain standard pricing, as specified in the brief.

## Changes
- Created BROCHURE25 in the existing booking coupon table, ID 61: 25%, no expiry, unlimited uses, matching campaign configuration.
- Existing voucher capture helper is embedded inline in WordPress. A separate discount-link.js file is not required.
- Enabled PP_BOOKING_VERIFIED and PP_PROMOTION_VERIFIED in private hosting configuration. Production index loads config/hosting-enabled.php; hosting.php also contains enabled flags. The distinct filename refreshed the host's cached configuration.
- Corrected booking-form.js display calculations to use integer cents, matching authoritative server rounding. Two groups now show $29.93; one group shows $14.96.
- Updated the adjacent local WordPress plugin source with the same JavaScript correction.

## Verification
- All 11 landing Book Now routes return 303 with the correct event ID, campaign attribution and BROCHURE25.
- Browser verified automatic 25% application across all 11 checkout events, refresh persistence, and clearing the voucher through discount= for standard $19.95 checkout.
- Browser verified complete landing-to-checkout handoff and $14.96 total.
- Anonymous coupon AJAX accepts BROCHURE25 and rejects an invalid code.
- Actual deployed PHP payment method tested in isolation with mocked database and Stripe calls: correct 1496/2993 cent amounts, standard 1995 cents, rejection of invalid coupon and disregard of tampered client total/percentage.
- No real booking, payment, settlement or confirmation email was created/tested.
- Screenshot: ../reports/discount-live-20261024.png

## Rollback and future releases
Private backups: /home/ozbizfin/koko-discount-20261024 (hosting.before.php, index.before.php, booking-form.before.js, coupon-before.json).
Restore hosting.before.php and index.before.php to disable handoff; restore booking-form.before.js only if the rounding patch must be reverted. Preserve actual coupon usage records if withdrawing the promotion; do not delete customer data.
The package script deliberately retains disabled verification flags for fresh installations. A future production release must preserve the verified live configuration instead of replacing it with the package defaults.
