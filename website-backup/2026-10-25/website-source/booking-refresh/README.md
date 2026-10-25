# Booking page visual refresh

This standalone WordPress plugin styles `/book-today/` with the Koko landing page's existing artwork, Trebuchet font, cream, green, sage and sand palette.

## Install

Upload `puzzlepath-booking-look.zip` using WordPress → Plugins → Add New → Upload Plugin, then activate **PuzzlePath Booking Look**. Clear any page/CDN cache and check Book Today in a private browser window.

The plugin adds a body class, a stylesheet and the requested group wording only on the page with the slug `book-today`. It does not replace the existing booking plugin, page content, shortcode definitions, form controls, JavaScript, Stripe configuration, prices, coupons, event data or confirmation logic. All artwork is bundled locally. Stripe's internal iframe typography remains controlled by the existing integration.

To undo the visual change, deactivate this plugin and clear the cache.

## Review

`preview.html` is a static design preview, not a checkout. The live form is used for browser checks with this stylesheet injected locally, without changing the server. Non-GET requests are blocked during those checks. No real payment or booking is submitted.

Screenshots and measured layout/interaction results are in `reports/`. `check.cjs` checks the original form markup is unchanged, desktop/mobile overflow, Stripe iframe presence, hidden success state, quantity recalculation and keyboard focus styling.

The source booking plugin already contains unrelated local changes and differs from production. It has deliberately not been modified or included in the deployment ZIP.
