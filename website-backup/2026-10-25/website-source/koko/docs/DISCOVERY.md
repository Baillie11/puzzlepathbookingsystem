# Discovery — 22 October 2026

1. **Approved prototype:** `WordPress/Puzzle Path/unified-puzzle-path/puzzlepath-koko-landing/index.php`, its `includes/island.php`, `includes/trail.php`, CSS and SVG artwork. The original is preserved. The new implementation extends that composition without replacing its artwork.
2. **Website and booking:** WordPress public website with the custom `puzzlepath-booking` plugin. The separate `unified-puzzle-path/unified-puzzle-path` PHP app handles playing quests and progress. It is not the checkout.
3. **Authoritative catalogue:** existing `pp_events` table (WordPress prefix varies; some older payment code hardcodes `wp2s_`). Form query: `seats > 0 AND display_on_site = 1`. The existing `/puzzlepath/v1/hunts` REST API additionally excludes missing hunt codes. Local companion plugin removes that completeness limitation using the existing data.
4. **IDs and URLs:** public booking form uses `/book-today/?event_id=<id>`. Read-only direct HTTPS fetches on 22 October returned IDs 9, 10, 11, 12, 15, 16, 19, 20, 21, 22, 23 from both the API and form. Search-engine cached content was months old, so it was not used as current pricing evidence.
5. **Prices:** every current booking option and API row was AUD 19.95 per group. One group is up to five participants. Integer-cent calculation gives AUD 14.96 at 25%. That confirms display arithmetic, not brochure coupon existence.
6. **Discounts:** existing `pp_coupons` table; AJAX `apply_coupon`; payment creation independently looks up dates and usage limits and rejects an invalid coupon. Local `js/discount-link.js` supports the `discount` parameter and saves a voucher; `booking-form.js` selects the event before applying it and guards pending/rejected coupon state. Only one coupon is supplied, preventing landing-page stacking.
7. **Payment:** existing `puzzlepath/v1/payment/create-intent`, booking row, Stripe PaymentIntent with server-derived price and booking metadata. The local production-method test ran with a mocked Stripe API, not a real payment or database.
8. **Confirmation:** existing signed Stripe webhook and booking-status route; a separate landing campaign association does not yet exist. The new ledger interface is tested with mock verification only, and its public receiver stays disabled.
9. **Analytics:** older landing project had an optional browser event interface, no live collector. New aggregate logger is off by default. Existing plugin audit logs are for booking administration, not reused to store browsing details.
10. **Artwork:** approved Koko PNG, nine existing island SVG variants and original footprint geometry copied unchanged. Responsive JPEG derivatives reduce mobile image transfer while retaining the supplied artwork; original remains locally for provenance. No quest photos are displayed.

## Live mismatch and blockers

The deployed booking-form.js includes calls to `ppDiscountLink`, but a direct fetch of `/wp-content/plugins/puzzlepath-booking/js/discount-link.js` returned **404**, and the current booking page did not enqueue that helper. This prevents a reliable automatic brochure discount and clearing old voucher state. The existing local helper and enqueue implementation must be reconciled with the deployed plugin in approved staging.

`BROCHURE25` is a proposed trusted configuration value; it has **not** been validated against the live coupon table or AJAX. Campaign dates, usage limits and all-quest eligibility need confirmation. No coupon was created or modified. Checkout discount and final charge still require end-to-end testing in approved staging/test mode.

Production PHP/cURL, cPanel environment configuration and destination route need rechecking. No live filesystem, plugin, database, payment, host or DNS modifications were made.
