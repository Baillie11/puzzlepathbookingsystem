# Booking integration and Codex handoff
## Existing integration
Inspected sources: the public `Baillie11/puzzlepathbookingsystem` repository, the live `/book-today/` page and deployed `wp-content/plugins/puzzlepath-booking/js/booking-form.js` on 17 October 2026.
The public repository and deployed assets are not identical snapshots. Recheck both before changing the plugin.
The deployed script reads a positive numeric `event_id` using URLSearchParams, finds a matching enabled option and triggers the existing select change handler. The repository PHP `event_id` GET read observed in discovery is in confirmation handling, not sufficient proof of form preselection.
Normal links use `https://puzzlepath.com.au/book-today/?event_id=<verified-id>` with UTM campaign fields. The landing page sends no price, customer details or payment information.
The catalogue contains event IDs 9, 10, 11, 12, 15, 16, 19, 20, 21, 22 and 23. These were observed in both the booking selection and public catalogue; this is a snapshot, not a live availability guarantee.
The form's quantity represents groups/quests/carloads. One group of up to five people selects quantity 1, never five tickets.
## Current coupon fallback
The deployed script supports manual `coupon_code` entry followed by Apply Coupon. Its request posts `action=apply_coupon`, the current `coupon_nonce` and the coupon code to the plugin AJAX URL.
No URL-based coupon handler was found in the deployed script inspected. The select change handler resets coupon state and clears the input, so applying a URL coupon before quest selection would be incorrect.
The landing page therefore does not send a speculative coupon query parameter or claim automatic application.
BROCHURE25 and its first-booking eligibility have not been validated. The existing generic coupon schema alone does not demonstrate first-customer enforcement. Confirm configuration and eligibility before launch.
## Requested plugin changes — specification only
No plugin, database, app, payment settings or production files have been changed by this project.
### Proposed URL contract
After Codex implements and verifies it, support `event_id=<positive integer>&coupon=BROCHURE25` along with:
- `utm_source=brochure`
- `utm_medium=print`
- `utm_campaign=brochure_dl_2026`
- optional `utm_content=hotel|cafe|tourist_information|other`
The current landing page omits `coupon` until this contract is implemented and tested. Do not assume simply adding it will apply a discount.
### Initialization order
1. Parse parameters defensively. Accept only an enabled currently bookable event from the server-generated options. Unknown, hidden, sold-out or disabled IDs must leave a clear manual selection path.
2. Set the event and run the existing change handler exactly once.
3. After the reset/recalculation completes, populate the coupon field and call the same coupon-validation logic used by Apply Coupon, including nonce checks and explicit pending/success/error states.
4. Do not duplicate the validation/payment code or trust a percentage, price, quantity or total from the URL or landing page.
5. On event changes, invalidate any prior discount and revalidate only according to the existing coupon eligibility rules. Avoid repeated automatic requests or races.
6. A failed, expired, exhausted or ineligible coupon must leave the checkout usable and make the undiscounted total and error clear.
### Server authority and eligibility
Validate event status, group quantity, canonical group price, coupon dates/limits/eligibility and final amount again during payment creation. Display-only client arithmetic must not determine Stripe amounts.
The expected advertised display for one eligible group is AUD 19.95 less 25%, rounded to AUD 14.96. Confirm discount/total rounding is identical in validation, intent creation, confirmation and email. Confirm multiple-group behaviour rather than inferring it.
Define how “first booking” is enforced before customer identity is available, how returning customers are handled and what disclosure is shown. Do not collect or export additional personal information for campaign analytics.
### Attribution
Allowlist campaign fields server-side; do not store the whole referring URL or arbitrary query strings. Agree a privacy-reviewed aggregate reporting design before persisting campaign data or linking it to existing bookings.
No landing-page analytics collector or duplicate booking records are required. A future public catalogue API should expose only public quest metadata, with caching and no customer records or app access codes.
## Acceptance checks in authorised staging
- Valid, invalid, hidden, disabled and sold-out event IDs; missing parameters; duplicate and malicious values.
- Correct event selected before coupon validation; quantity 1 remains one group of up to five.
- Valid 25%, expired, exhausted, ineligible and unknown coupon; backend/network errors; repeated clicks; event changes during a request.
- Server rejects manipulated URL/client totals and percentages.
- AUD 14.96 for one eligible group; verified multiple-group rounding and quantity bounds.
- Booking remains usable if attribution or analytics is blocked; no PII in analytics.
- Desktop/mobile and fresh sessions; confirmation/email consistency in Stripe test mode only with separate authorisation.
No live payment transactions or booking submissions are part of the landing-page tests.
## Catalogue issues for owner review
The public catalogue labelled all entries Walking Quest, including Koala Trail Driving Quest, Bribie Island Drive Quest and Coomera Cruise & Tunes. Their travel-mode badges are deliberately omitted here until confirmed.
No source durations were verified, so none are invented.
