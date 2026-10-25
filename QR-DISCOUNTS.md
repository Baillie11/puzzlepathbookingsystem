# QR voucher links

A QR code containing `https://puzzlepath.com.au/?discount=BROADBEACHCOLLECTIVE`
continues to open the home page. The plugin saves the code in that browser's
local storage for seven days. Selecting a quest at checkout automatically runs
the existing coupon validation and updates the displayed total. Changing quests
rechecks the code. Scanning a new voucher replaces the saved code.

The code must already exist in PuzzlePath > Coupons. The URL does not define a
discount amount or create a coupon. Expiry and usage checks still run on the
server. Invalid or exhausted codes stop checkout instead of silently charging
full price. Visitors can remove a saved voucher or type another code. An explicit
Repeat Hunter offer takes priority and keeps its existing validation.

Deploy these three files together to the existing PuzzlePath Booking plugin:

- `puzzlepath-booking.php`
- `js/booking-form.js`
- `js/discount-link.js`

Purge the WordPress/CDN page cache and any combined JavaScript cache after
deployment, so the home page includes the capture script. Keep that script
enabled on all public pages. Use one canonical site hostname throughout the
journey: local storage is specific to the browser and origin. If storage is
blocked, a discount works on its landing page but cannot survive navigation.
Saved vouchers expire after seven days even if the coupon itself lasts longer.
They remain saved after booking until removed or expired; server usage limits
determine whether they can be used again.

Verification: `npm install --no-save --package-lock=false jsdom jquery@3`, then
`node tests/discount-link-test.js`; run PHP lint and the existing PHP tests.
On staging, open the example QR URL, browse to a quest, confirm its discounted
total, switch quests, remove the voucher, and check an expired voucher. Check
both a paid discount and a 100% discount without submitting a real payment.
