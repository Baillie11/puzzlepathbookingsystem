=== PuzzlePath Booking ===
Contributors: Andrew Baillie - Click eCommerce
Tags: booking, events, puzzlepath, stripe, discount, custom form
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 2.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A custom booking system built for PuzzlePath to manage events, take Stripe payments, issue discount codes, and send confirmations.

== Changelog ==

= 2.1.2 =
* Added hosting type support for events (Hosted vs Self Hosted App) with conditional date/time picker
* Enhanced AJAX coupon application with real-time validation and price updates
* Added edit functionality for events and coupons in admin panel
* Improved form processing and error handling
* Fixed database structure and booking reference generation
* Enhanced admin interface with better user experience

= 2.1.1 =
* Major refactor: removed all Stripe payment dependencies for simplified booking system
* Fixed "Page not found" error by separating form processing from display logic
* Added proper WordPress hooks and nonce verification
* Improved database structure and error handling

= 1.1.13 =
* Fixed database install issues: plugin now creates tables correctly on activation without SQL errors.

= 1.1.12 =
* Added redirect to the Payment page after successful booking, passing booking ID and name as URL parameters.

= 1.1.11 =
* Added AJAX-powered "Apply" button for discount codes on the booking form, allowing users to validate coupons before submitting the booking. 