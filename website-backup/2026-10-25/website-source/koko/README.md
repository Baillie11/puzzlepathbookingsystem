# Koko’s Island Adventure — local review release

Run `./start-local.ps1` from PowerShell, then open http://127.0.0.1:4180/?campaign=dl-brochure. Direct visits at http://127.0.0.1:4180/ show the standard price. This is the new PHP implementation; the older static project is retained untouched.

The original approved prototype was copied from `../WordPress/Puzzle Path/unified-puzzle-path/puzzlepath-koko-landing`. Its original source remains unchanged. PHP 8.2+, HTML, CSS, SVG and vanilla JavaScript are the only application requirements. Node/Playwright are existing development tools. No new booking engine or payment system was created.

## Verification

From the parent project:

```
php koko/tests/core-test.php
php koko/tests/cache-test.php
node koko/tests/browser.cjs
```

The browser test requires the local server and existing project dependencies. It uses installed Chrome. Reports and screenshots go to `koko/reports/`.

## Configuration

Edit `config/campaigns.php` on the server to add or deactivate a campaign. Public identifiers are the array keys. Set internal ID, name, source, trusted partner, policy, percentage, provider and coupon there. Dates must use ISO 8601 with an explicit timezone, such as `2026-12-31T23:59:59+10:00`. Never put secrets in this file if it will be committed. Only the existing Puzzle Path provider is implemented; Rezdy remains disabled.

Campaign attribution travels in every quest link. Refresh preserves it; a new direct visit does not inherit the offer. Unrecognised, future, disabled or expired campaigns cannot grant discounts. `source`, `partner`, `percent`, `price`, `discount` and return URLs supplied by visitors are ignored. Campaign values come from trusted configuration.

`config/environment.example` documents server environment settings; it is not automatically loaded. Default mode is production and both booking verification gates default to closed. `PP_ENV=local` alone enables the fixture catalogue and simulated quote screen; that screen never accepts details or creates bookings. Never set local mode on a public host.

## Catalogue and prices

Local preview uses the genuine public API response captured 22 October 2026 in `tests/catalogue-snapshot.json`. It is a test fixture, not an independently maintained production database. All 11 IDs matched the current booking form, and all were $19.95 per group (up to five people). Descriptions use a location-based fallback because the existing API does not expose descriptions.

Production reads the existing public `/wp-json/puzzlepath/v1/hunts` API by default. Its existing source excludes quests without a hunt code. Although it matched all 11 current booking options, use the included read-only companion plugin and configure its `/wp-json/puzzlepath-landing/v1/catalogue` endpoint before launch to guarantee future coverage of **all** bookable events. This new endpoint is implemented here, not presumed to exist remotely. It queries the existing table with the booking form’s predicate and includes existing descriptions. No new quest records or tables are created.

Responses cache for five minutes; last-known data can display for up to 24 hours during an outage. A booking click always fetches fresh availability and blocks on failure. Successful empty responses remove old quests. Descriptions have an escaped, bounded fallback. Prices are integer cents derived from source prices; displayed discount is not proof of checkout entitlement.

## Booking and attribution

All buttons pass through `public/index.php?action=book`. The existing provider uses the inspected `event_id` and `discount` URL contract on the fixed HTTPS Puzzle Path booking route. The existing plugin revalidates coupon and price during payment creation. No landing-page price is sent to checkout. Direct links clear the saved coupon through the same verified helper contract. Both live handoff and live promotional handoff require explicit server verification flags.

Optional `PP_ANALYTICS=1` writes aggregate landing views, quest selections and successful redirect handoffs to private daily JSONL files, respecting GPC and DNT. These are request counts, not unique people. No persistent identifier, IP, user agent, customer details or whole URL is recorded. Delete these logs after 30 days and use host log rotation. Analytics is off in local preview. Mock checkout visits never emit completed bookings or live handoffs.

`src/conversions.php` provides a verifier interface and idempotent ledger; its HMAC verifier is test-only. There is **no production conversion receiver** until a provider-specific authenticated confirmation adapter and campaign-to-booking reference mapping are supplied. The public webhook action returns 501 without writing anything. Existing Stripe signature validation must remain in the existing booking service.

## Rezdy

`config/rezdy.example.php`, provider interfaces and disabled `RezdyProvider` reserve referral, booking-link, API and webhook integration points. No guessed API endpoints or parameters are used. Enabling a referral does not confer a discount. Confirm account access, mappings and official contracts before implementing a live adapter.

See [readiness](docs/READINESS.md), [discovery](docs/DISCOVERY.md) and [deployment and rollback](docs/DEPLOYMENT.md). Nothing is deployed.
