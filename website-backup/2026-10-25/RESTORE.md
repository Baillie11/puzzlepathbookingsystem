# Puzzle Path website backup — 25 October 2026 (Brisbane)

This snapshot preserves the website work through the Contact page refresh. It is stored on `backup/website-2026-10-25` in the existing booking-system repository. It does not deploy anything.

## Contents

- Repository root: booking plugin source including the local discount-link and integer-cent rounding updates, plus their existing tests and documentation.
- `website-source/koko/`: deployed landing-page application source, artwork, catalogue integration and deployment/discount activation records. The original brochure build is also retained under `website-source/`.
- `website-source/about-refresh/puzzlepath-booking-look/`: current design plugin, version **1.2.2**, for Booking, About and Contact. Includes transparent Koko, island SVGs and social icon styling. Use this version, not the historical `booking-refresh` copy.
- `wordpress/pages.xml`: all 12 WordPress pages and their associated attachment records, including editable Elementor metadata.
- `wordpress/site-content.xml`: pages plus navigation, Elementor templates, global styles, Additional CSS and media records. This is the preferred WordPress import; do not import both XML files into the same target.
- `wordpress/pages/`: individual page HTML and metadata for inspection and selective recovery.
- `wordpress/additional.css`: existing Astra Additional CSS, required alongside the design plugin.
- `wordpress/contact-form.json`: importable WPForms contact-form definition, including notification configuration. No submitted messages are included.
- `media/` and `wordpress/media-manifest.json`: downloaded WordPress media originals and their recorded source URLs/checksums or download errors.
- `screenshots/`: desktop/mobile visual references from the page work.
- `SHA256SUMS.json`: snapshot file checksums.

## Restore procedure

1. Take a separate private hosting/database backup before restoration. Use a staging copy first.
2. Restore the booking plugin from the repository source. Preserve the existing database tables, live Stripe credentials, coupons, booking records and other server settings.
3. Install/activate Astra, Elementor and WPForms as required by the existing site. Import `wordpress/site-content.xml` using the WordPress importer and map authors to the appropriate administrator. Author email/name metadata and comments have been omitted from this public GitHub snapshot. Regenerable Elementor cache and obsolete backup metadata were also removed.
4. Restore the media files to their recorded `wp-content/uploads/...` locations and verify attachment IDs/URLs. Review any errors in the media manifest. Regenerate thumbnails as needed.
5. Restore `additional.css` in Astra/WordPress Additional CSS. Install the current `puzzlepath-booking-look` directory as a plugin. The ready-to-upload ZIP is included in the same `about-refresh` folder.
6. Import the WPForms JSON. If WordPress allocates a new form ID, update the Contact page's `[wpforms id="10"]` shortcode accordingly. Confirm notification delivery with an approved test message.
7. Restore the standalone landing application following `website-source/koko/docs/DEPLOYED-2026-10-24.md` and `DISCOUNT-ACTIVATION-2026-10-24.md`. Public files belong under `/landingpage/`; private source/config/storage remain outside the web root. The packaged defaults intentionally disable booking/promotion: preserve the verified production `hosting-enabled.php` configuration and BROCHURE25 coupon described in the activation report.
8. Set the existing home page and menu locations, regenerate Elementor CSS/data, and clear caches. Check Landing, Booking, About and Contact on desktop/mobile, booking handoff/prices, Contact required-field validation and social links.

## Scope and limits

This is a source/content backup of our website changes, not a complete server or database image. It excludes customer bookings, payment data, credentials, account email reports, runtime caches and unrelated local projects. WordPress options, live coupon/quest tables and server configuration require the separate private hosting/database backup. Local source and deployment records are preserved; no claim is made that a full disaster-recovery restore has been tested.

Verified in the export: all 12 published pages, Contact's Facebook and Instagram destinations, About's current Elementor content and Upcoming Adventures button destination, and the existing contact form. Version 1.2.2 is the latest deployed styling package.
