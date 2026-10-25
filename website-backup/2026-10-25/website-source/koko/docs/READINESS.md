> Update 24 October 2026: live booking and the automatic 25% brochure discount are now enabled. See [activation report](DISCOUNT-ACTIVATION-2026-10-24.md). Earlier readiness notes below describe the pre-activation state.

# Local readiness report

Update, 24 October 2026: the owner approved publication and the page is deployed at https://www.puzzlepath.com.au/landingpage/. Booking remains disabled pending verification. See DEPLOYED-2026-10-24.md. The following records the original local review results and remaining integration requirements.

## Implemented and tested

- Approved hero, Koko art, island silhouettes, green quest islands, sandy information islands, Book Now styling and final CTA retained.
- All 11 currently observed publicly bookable quests, without quest photographs or individual prices; layout expands dynamically in alternating groups.
- Server-resolved campaign policy and attribution; brochure, direct, Facebook and partner behaviour; expiry and invalid-campaign handling.
- Central trusted booking handler, fresh catalogue validation, cache and outage handling, output escaping and security headers.
- Scroll-revealed original paw geometry; footprints remain revealed, respect reduced motion, and have a static no-JavaScript fallback. At the owner's request, footprints render above islands and text; pointer events pass through to the buttons. Islands remain stationary.
- Responsive Koko images: approximately 25 KB, 75 KB and 144 KB versus the original 2 MB PNG.
- 24 PHP core checks and 5 catalogue-cache checks passed.
- Existing booking-plugin discount/payment fixture passed with mocked Stripe calls, including rejection of unavailable coupons.
- Chromium at 320, 375, 390, 430, 768, 1024 and 1440 px: 11 quests each, no horizontal overflow or out-of-island content, zero axe violations and zero JavaScript runtime errors. All 11 local handoffs, tampered price/discount inputs, missing quests, invalid campaigns, disabled webhook, no-JavaScript booking and monotonic/reduced-motion trail checks passed.
- Desktop and mobile screenshots visually reviewed. These are responsive Chromium checks, not physical iPhone/Safari or Android hardware testing.

## Implemented using mocks

- Local brochure quote and handoff show $14.96 for one group, based on a captured real catalogue and a mock 25% campaign policy. No payment or real booking controls are present.
- Conversion ledger tests authenticate mock notifications and deduplicate confirmed payment events.
- Future Rezdy referral eligibility test; no live Rezdy service calls.

## Scaffolded

- Provider-neutral Rezdy/referral configuration, future notification verifier and conversion ledger.
- Campaign-to-booking conversion and confirmed revenue reporting require an authenticated booking adapter and reference mapping. They are not enabled or claimed as working.
- Read-only WordPress catalogue companion plugin is implemented and syntax-checked locally but has not been run in a WordPress staging database. It needs installation approval and staging validation.

## Required before production

1. Repair/reconcile the deployed voucher helper and verify automatic discount initialization, event changes and failure behaviour.
2. Confirm or create the approved brochure voucher in the authoritative booking administration: 25%, all eligible quests, dates and limits. Confirm one-group and multiple-group charge totals and no silent full-price fallback.
3. Install/validate the read-only complete catalogue bridge in approved staging; check unpublished, sold-out and missing-code records against the booking form.
4. Test an actual end-to-end booking in approved Stripe test mode, including final total, confirmation, email and callback behaviour. No such booking was made in this task.
5. Confirm OrangeHost route, PHP extensions, environment settings, TLS and access method. Review privacy/retention settings before enabling optional analytics.
6. Obtain explicit deployment approval. Then, after successful staged verification, deliberately enable the booking verification flags. Approval alone is not evidence that checkout works.

**Local review ready. Not production-ready for the advertised discount. No deployment or final QR code has been produced.**

