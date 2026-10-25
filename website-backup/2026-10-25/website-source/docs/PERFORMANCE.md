# Performance and local verification
## Latest review — replayable postcards, lifestyle photos and softened banner
Latest local measurements on 18 October 2026, using the same tools/configuration below:
- Mobile: performance 94, accessibility 100, best practices 100; FCP 1.8 s, LCP 2.3 s, CLS 0, TBT 210 ms; measured transfer 376 KiB.
- Desktop: performance 100, accessibility 100, best practices 100; FCP 0.5 s, LCP 0.6 s, CLS 0, TBT 20 ms; measured transfer 536 KiB.
- SEO remains 66 due to deliberate noindex.
The 95+ mobile performance target is not yet met. Intermediate runs varied substantially (including one 33-point run with a 6.4-second unattributable task); do not present these as stable field results or assume the cause is solely application code. Startup trail construction was batched off-DOM, sampling reduced, and masks bounded to their actual paths. Further controlled profiling and an authorised HTTPS preview audit remain needed.
Current checks: 15 Python unit tests and 6 JavaScript campaign tests pass. The six-width browser/axe suite and adventure suite pass for responsive layout, contrast, text/control clearance, all eleven booking controls, hover/focus/touch/reduced motion and CLS 0.
The postcard acceptance suite passes for varied stagger, replay after substantial separation, minor-scroll hysteresis, exact resting positions, no replay asset reload, slow-image/no-late-animation behaviour, rapid scroll, interaction priority and unsupported/throwing/data-saver/script-failure fallbacks. One run timed out waiting for local images; independent loading inspection and rerun passed. Treat that intermittent test timing as a limitation rather than silently claiming every invocation passed.
Booking/coupon/analytics script and quest catalogue SHA-256 hashes match the pre-enhancement checkpoint. These checks do not validate a production coupon or submit payments.
Hero is the only high-priority image; other responsive images remain native-lazy with bounded ahead-of-viewport warming. Both brochure images are absent from rendered HTML.
No field INP, real mid-range physical-device smoothness or Safari/Firefox certification has been measured. Lighthouse TBT is a lab responsiveness indicator, not an INP result.
## Latest results — adventure enhancement, 18 October 2026
Measured against the same local URL and Lighthouse configuration described below, after adding the isolated SVG/CSS adventure layer:
- Mobile: performance 98, accessibility 100, best practices 100; FCP 1.1 s, LCP 2.2 s, CLS 0, TBT 80 ms; measured transfer 378 KiB.
- Desktop: performance 100, accessibility 100, best practices 100; FCP 0.3 s, LCP 0.4 s, CLS 0, TBT 0 ms; measured transfer 501 KiB.
- SEO remains 66 because the deliberate `noindex` policy is unchanged.
Motion-specific tests pass at 320, 375, 390, 430, 768 and 1440 pixels: sampled trail paths stay clear of protected content, all eleven booking buttons remain actionable, and there is no horizontal overflow.
Additional checks pass for unchanged copy/links/base layout at desktop/mobile; persistent reveal when scrolling back; sequential steps; alternating tilts; return to rest; no overlap with neighbouring quest titles/buttons; keyboard focus; touch feedback; live resizing; static reduced motion; and measured CLS 0 during the motion journey.
The existing six-width browser/axe, coupon, navigation and no-JavaScript suite also passed after adding the enhancement. The 16 unit tests remain passing. These remain local Chromium checks, not claims of production deployment or all-browser certification.
Screenshots and machine-readable results are generated in `reports/`; run `npm run test:adventure` with the local server running to reproduce.
## Initial pre-enhancement local results — 18 October 2026
Target: `http://127.0.0.1:4173/`, generated `dist/` files served by Python's development HTTP server.
Tool: Lighthouse 12.8.2 with locally installed Chrome. Mobile uses Lighthouse's simulated mobile defaults; desktop uses 1440×900, 40 ms RTT, 10,240 Kbps throughput and 1× CPU slowdown. These are individual local lab runs, not field data or Orange Host results.
### Mobile
- Performance: 99/100.
- Accessibility: 100/100.
- Best practices: 100/100.
- First Contentful Paint: 1.1 seconds.
- Largest Contentful Paint: 2.1 seconds.
- Cumulative Layout Shift: 0.
- Total Blocking Time: 10 ms.
- Lighthouse measured transfer: 360 KiB.
### Desktop
- Performance: 100/100.
- Accessibility: 100/100.
- Best practices: 100/100.
- First Contentful Paint: 0.3 seconds.
- Largest Contentful Paint: 0.4 seconds.
- Cumulative Layout Shift: 0.
- Total Blocking Time: 0 ms.
- Lighthouse measured transfer: 482 KiB.
SEO was 66/100 in both runs because indexing is intentionally blocked with `noindex, follow` for this campaign review build. The canonical URL points to the eventual `/brochure/` location. Decide production indexing deliberately; do not remove preview `noindex` simply to improve a lab score.
Raw HTML/JSON reports are local generated artifacts in `reports/lighthouse-mobile.*` and `reports/lighthouse-desktop.*`; they are excluded from Git.
## Other completed local checks
- 10 Python tests: catalogue validation, static cards, display-price rounding, deterministic generation, escaping, booking URL/parameter validation, duplicate IDs, safe assets and prevention of unimplemented automatic-discount claims.
- 6 JavaScript tests: bounded campaign fields, discarded unknown placement values, DNT/GPC handling and analytics-failure isolation.
- Chrome browser checks at 320, 375, 390, 430, 768 and 1440 pixels: no horizontal page overflow, 11 quest cards, every image decoded, zero page script errors or failed resource responses.
- Axe WCAG 2 A/AA and WCAG 2.1 AA automated checks: zero violations at all six widths. This is not a certification or substitute for manual accessibility testing.
- Coupon clipboard copy verified.
- Allowlisted hotel attribution verified; arbitrary email/query content not forwarded.
- Booking-link navigation verified using an intercepted response. No production booking request, payment transaction or customer-data submission was sent by this navigation test.
- JavaScript-disabled page still exposes all 11 real booking links and manual coupon instructions.
- Privacy information page loads correctly.
## Not yet verified
- Actual production event preselection and coupon eligibility/validation in a browser session. Discovery established deployed source support for event preselection, not an end-to-end checkout pass.
- Safari/WebKit, Firefox and Edge-specific rendering; screen-reader and real-device usability.
- Orange Host HTTPS preview, production security headers, compression/caching and deployed Lighthouse results.
- Aggregate analytics delivery: no collector is enabled.
- Clean standalone hero photography: the current build uses the supplied approved brochure itself, not replacement stock or an older brochure.
## Repeat locally
Run `python scripts/build.py`, start the loopback server documented in the README, then run `npm test`, `npm run test:browser`, `npm run audit:mobile` and `npm run audit:desktop`.
After authorised hosting deployment, use `AUDIT_URL` for the actual HTTPS preview and label those results separately.
