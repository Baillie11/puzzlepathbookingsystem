# Puzzle Path brochure landing
A standalone, mobile-first static landing page for the brochure campaign. No WordPress code, customer database, checkout or server-side application is included.
## Local preview
Requires Python 3.11+ for the build. The build itself uses only Python's standard library; it makes no network requests.
1. Run `python scripts/build.py`.
2. Run `python -m http.server 4173 --bind 127.0.0.1 --directory dist`.
3. Open http://127.0.0.1:4173/.
The source `index.html` is generated and committed when version control is set up. Make HTML edits in `templates/index.html`, not the generated file.
## Current scope and limitations
- Eleven genuine quests and their first-party artwork are stored locally; there are no runtime catalogue requests.
- Normal navigation hands off to the existing HTTPS WordPress booking form.
- The indicative brochure price is $14.96 per group, compared with $19.95 standard pricing. WordPress remains authoritative.
- BROCHURE25 is shown with manual instructions. Its existence, first-booking eligibility and live validation still need confirmation. No automatic-discount claim is made.
- The approved final brochure remains the design reference, but neither brochure mock-up is rendered. Hero and Good Times images are responsive derivatives of two photographs already used on the Puzzle Path website. Their sources are recorded in `data/asset-provenance.json`; the owner should retain/confirm original rights records before public launch. They are not claimed to depict actual customers or a verified Gold Coast shoot.
- Conflicting travel-mode labels for event IDs 11, 22 and 23 are omitted. No unverified durations are shown.
- Analytics events are an optional local integration interface only; no collector or visitor tracking is enabled.
- This is a local review build, not a deployed or payment-verified production release.
## Maintenance
Edit `data/quests.json` to update verified quest titles, descriptions, locations and booking URLs. Add locally optimized cover variants in `assets/images/` and record their path and actual dimensions in the quest's `images` array.
Use the exact event ID and link from the live booking catalogue; do not invent IDs or availability. Set unknown durations and travel modes to `null`.
Prices are integer AUD cents. The generator calculates display-only promotional prices using decimal rounding, never payment amounts. Coordinate any price or coupon change with the booking-system owner.
Edit copy in `templates/index.html`, styles in `assets/css/styles.css`, and optional enhancements in `assets/js/main.js`. Rebuild and test after every change.
All static card text and booking links remain available with JavaScript disabled.
## Development tests
Node is development-only. Install the locked tools with `npm ci --ignore-scripts`.
- `npm test`: configuration, generation and JavaScript privacy/failure-path tests.
- `npm run test:browser`: responsive Chromium checks, axe accessibility, coupon copying, no-JavaScript behaviour and screenshots. Start the local server first.
- `npm run test:adventure`: trail/content clearance at all six widths, unchanged copy/base layout, all eleven clickable booking controls, opposite card tilts, one-shot spring return, keyboard/touch feedback, reduced motion, resize and zero layout shift.
- `npm run test:postcards`: randomized stagger/replay, substantial-distance rearming, slow-image skips, no late entrances, interaction cancellation, no replay image downloads and animation-failure/data-saver fallbacks.
- `npm run audit:mobile` and `npm run audit:desktop`: local Lighthouse reports. Start the server first.
Tests default to the locally installed Chrome on Windows; set `CHROME_PATH` if needed. No production payments or booking submissions are made.
## Assets
`data/asset-provenance.json` records first-party cover sources and hashes. Asset preparation is separate from the offline build and requires Pillow, PyMuPDF and BeautifulSoup.
Do not rerun preparation over manually edited catalogue information without reviewing the diff. The preparation command takes the approved final PDF, a previously inspected public catalogue HTML file, and its verification date.
## Deployment
Nothing is automatically deployed. See `docs/DEPLOYMENT.md` for verified hosting paths and approval gates, and `docs/BOOKING-INTEGRATION.md` for the separate Codex handoff.
## Adventure personality
`assets/css/adventure.css` and `assets/js/adventure.js` form an isolated decorative layer. The original booking/coupon/analytics script is unchanged.
The SVG trail uses coordinated section-level S-curves, small loops and selected grid gaps, ending at a small destination flag rather than tracing container perimeters. Trails render above the promotional banner and photo layers, clear of readable copy, prices, codes and controls. A solid SVG mask progressively reveals the dashed/footprint treatment without sliding the dashes. Scroll progress never retracts; step connections and arrival treatments run once. Responsive geometry is rebuilt on size changes, not on every scroll event. Complete SVG layers are constructed off-DOM with tightly bounded reveal masks to reduce startup work.
The supplied PDF is flattened and provides no separately reusable vector trail asset; these lightweight walking marks are a new decorative treatment in the existing palette, not a claimed extraction of an original footprint vector.
Cards alternate two-degree tilts with an eight-pixel lift, a brief spring return, keyboard-focus support and short touch feedback. Reduced-motion mode shows the full static trail and disables movement. All original content remains readable at all times, and booking links work if the enhancement script is unavailable.
## Replayable postcard entrances
`assets/js/postcards.js` owns outer `.quest-slot` entrance transforms; the inner `.quest-card` retains independent hover/focus/spring behaviour. No new dependencies are used, and `assets/js/main.js` is unchanged.
Responsive rows are inferred from stable layout offsets. Near-viewport IntersectionObserver entries select from six bounded styles, randomize arrival order, and use 720 ms entrances with 110 ms staggering. A separate observer rearms a played row only once it leaves an expanded viewport by at least 600 pixels or one viewport height. Minor scrolling does not reset the sequence. Replays reuse the same DOM and image sources.
A nearby-image observer warms responsive quest sources approximately 0.9 viewport ahead. Static image space is reserved immediately. Cards with images not yet decoded skip animation for that visit and remain readable; image completion never causes a late entrance. Default opacity is 1; even entrance keyframes keep cards at least 94% opaque.
Pointer/focus interaction, offscreen departure, resizing, backgrounding and reduced-motion changes cancel obsolete entrances and clear transient `will-change`. Unsupported/throwing animation engines and explicit slow-connection/data-saving signals keep the static page usable. There is no scroll listener in the entrance module.
## Regional settings and current visual review
Edit `data/site.json` for hero copy, regional metadata, canonical URL, photo sources/alt text/dimensions and an optional ordered subset of already-verified quest IDs. Leave `questIds` null to retain the complete existing catalogue. The shared animation modules contain no Gold Coast-specific content.
The top-left logo, regional hero eyebrow and orange sticker have been removed. Navigation remains in a compact header, and reduced hero spacing brings subsequent content higher. The offer background is 80% opaque (20% transparent), while text/buttons remain fully opaque with accessible contrast.
The pre-enhancement source/assets checkpoint is outside the project at `C:\Users\andre\AppData\Local\Temp\puzzlepath-checkpoints\before-fly-in-20261018-152951.zip`, with a companion hash manifest. This is a local review build, not an authorised production release.
