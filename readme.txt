=== PuzzlePath Booking ===
Contributors: Andrew Baillie
Tags: booking, events, stripe, payments, treasure hunt, escape room, quest, management
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 2.7.5
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Comprehensive booking management plugin for PuzzlePath treasure hunts with advanced admin features, Stripe payments, and unified app integration.

== Description ==

PuzzlePath Booking is a powerful, feature-rich booking management plugin designed specifically for treasure hunt, escape room, and quest-based businesses. It provides a complete solution for managing events, processing payments, tracking revenue, and integrating with mobile applications.

**🎯 Perfect for:**
* Treasure hunt companies
* Escape room businesses  
* Quest and adventure experiences
* Event management companies
* Tour operators

**✨ Key Features:**

= 📊 Advanced Admin Management =
* Comprehensive booking dashboard with filtering, sorting, and search
* Event management with hosted and self-hosted options
* Quest manager with performance analytics and revenue tracking
* Coupon system with usage limits and expiry dates
* Bulk operations (refunds, email resends)

= 💳 Payment Processing =
* Complete Stripe integration with test/live mode switching
* Automatic webhook handling for booking confirmations
* Refund management directly through Stripe
* Support for 100% discount (free) bookings
* Optimized for Australian payments

= 🔗 Unified App Integration =
* Automatic hunt code generation
* REST API endpoints for mobile app integration
* Unified database views for external systems
* Booking search by code, status, and date ranges

= 📈 Analytics & Reporting =
* Accurate revenue tracking and calculations
* Booking statistics dashboard
* CSV export with full filtering support
* Quest performance metrics

= 🎨 User Experience =
* Responsive, mobile-friendly booking forms
* Real-time AJAX coupon validation
* Progress indicators and visual feedback
* Automated email confirmations with templates

**🆕 Latest Updates (v2.7.5):**
* NEW: Complete booking edit functionality
* Edit customer details, tickets, and payment status
* AJAX-powered modal interface for seamless editing
* Enhanced admin interface with edit buttons
* Real-time validation and error handling

= Shortcodes =

* `[puzzlepath_booking_form]` - Display the responsive booking form

= REST API Endpoints =

* `GET /wp-json/puzzlepath/v1/bookings` - Retrieve bookings
* `GET /wp-json/puzzlepath/v1/booking/{code}` - Get specific booking
* `GET /wp-json/puzzlepath/v1/hunts` - List available quests
* `POST /wp-json/puzzlepath/v1/payment/create-intent` - Create payment

== Installation ==

**⚠️ Important: This plugin requires Stripe PHP library dependencies**

= Quick Install =

1. Upload the plugin files to `/wp-content/plugins/puzzlepath-booking/`
2. **Install Stripe dependencies** (CRITICAL):
   * Run `composer install --no-dev` in the plugin directory, OR
   * Manually upload the complete `vendor/` directory
3. Activate the plugin through WordPress admin
4. Configure Stripe settings in PuzzlePath → Stripe Settings
5. Set up webhook in your Stripe dashboard

= Stripe Configuration =

1. Get API keys from your Stripe dashboard
2. Go to PuzzlePath → Stripe Settings
3. Enter your test/live API keys
4. Set up webhook endpoint: `https://yoursite.com/wp-json/puzzlepath/v1/stripe-webhook`
5. Enable `charge.succeeded` event in Stripe

== Frequently Asked Questions ==

= Do I need a Stripe account? =

Yes, a Stripe account is required for payment processing. The plugin supports both test and live modes for development and production.

= Why do I see "Stripe PHP library is not installed"? =

The plugin requires Stripe's PHP SDK. Run `composer install --no-dev` in the plugin directory, or manually upload the `vendor/` folder with dependencies.

= I see mixed payment statuses ('succeeded' and 'paid') - how do I fix this? =

Version 2.7.4+ includes automatic and manual migration options:
- **Automatic**: Plugin update triggers migration automatically
- **Manual**: Go to PuzzlePath → Settings → Database Maintenance → "Migrate Payment Statuses"

= Can I create different types of events? =

Yes! Create hosted events with specific dates/times, or self-hosted events for app-based experiences. Hunt codes are automatically generated for app integration.

= How do I track revenue? =

The Quest Manager provides comprehensive revenue tracking. Version 2.7.4+ includes enhanced revenue calculation fixes.

= Can I offer discount codes? =

Yes, create percentage-based coupons with usage limits, expiry dates, and real-time validation.

= Is there an API for mobile apps? =

Yes, comprehensive REST API endpoints are available for mobile app integration, including booking management and quest data.

= Can I export booking data? =

Yes, export bookings to CSV with all applied filters. Perfect for accounting and reporting.

== Screenshots ==

1. Comprehensive booking management dashboard with filters and stats
2. Event creation form with hosting types and hunt code generation  
3. Quest Manager analytics and revenue tracking
4. Stripe settings with test/live mode toggle
5. Responsive frontend booking form with progress indicators
6. Coupon management with usage tracking
7. Booking details modal with customer information
8. CSV export functionality
9. Settings page with database maintenance tools

== Changelog ==

= 2.7.5 - 2025-01-06 =
**✨ NEW FEATURES:**
* Added complete booking edit functionality
* Edit customer name, email, ticket count, and payment status
* Edit participant names when available
* Real-time form validation and error handling
* AJAX-powered modal interface for seamless editing

**🎨 UI ENHANCEMENTS:**
* Added edit (✏️) button to booking actions column
* New edit booking modal with proper form layout
* "Edit Booking" button in booking details modal for quick access
* Enhanced modal system with validation feedback

**🛠 TECHNICAL IMPROVEMENTS:**
* Added secure AJAX handlers for edit functionality
* Comprehensive input validation and sanitization
* Database updates with proper error handling
* Enhanced security with nonce verification

= 2.7.4 - 2025-09-06 =
**🔧 CRITICAL MIGRATION FIXES:**
* Added manual migration button in Settings → Database Maintenance
* Enhanced automatic migration system with version trigger
* Fixed mixed payment status issues ('succeeded' vs 'paid')
* Added admin confirmation dialogs for migration safety
* Improved migration success messaging

**⚡ ENHANCEMENTS:**
* Enhanced README with comprehensive plugin documentation
* Better admin interface for database maintenance
* Improved error handling in migration process
* Updated version system to force migration re-run

**🛠 TECHNICAL:**
* Added manual migration function with nonce security
* Enhanced admin notice system for migration feedback
* Better version comparison for automatic updates
* Improved database maintenance tools

= 2.7.3 - 2025-09-06 =
**🔧 CRITICAL FIXES:**
* Fixed Total Revenue calculation not working properly
* Updated payment status system from 'succeeded' to 'paid' for consistency
* Automatic migration of existing booking statuses
* Enhanced revenue tracking accuracy

**⚡ IMPROVEMENTS:**
* Added admin notice for successful payment migration
* Better Stripe integration error handling
* Updated UI filters and status displays
* Enhanced booking details modal

= 2.7.2 =
* Comprehensive booking management system
* Quest manager functionality
* Enhanced Stripe integration
* Hunt code generation
* REST API endpoints

= 2.4.0 =
* Major update: Unified app integration with hunt code support
* Enhanced database schema with hunt_code, hunt_name fields
* Smart booking code generation with hunt-specific formats
* Created database view for seamless unified app integration

== Upgrade Notice ==

= 2.7.5 =
**NEW FEATURE**: Complete booking edit functionality added. Edit customer details, tickets, and payment status directly from admin. Enhanced UI with AJAX-powered modals.

= 2.7.4 =
**CRITICAL UPDATE**: Enhanced migration system for payment status fixes. Includes manual migration tools and automatic migration improvements. Required if you see mixed payment statuses.

= 2.7.3 =
**CRITICAL UPDATE**: Fixes revenue calculation issues and updates payment status system. Automatic migration included - no data loss. Stripe dependencies required.

= 2.7.2 =
Major feature update with advanced admin management, quest analytics, and enhanced Stripe integration.

== Support ==

* **GitHub**: https://github.com/Baillie11/puzzlepathbookingsystem
* **Issues**: Report bugs and request features on GitHub
* **Documentation**: Comprehensive README and changelog available

== Requirements ==

* WordPress 5.0+
* PHP 7.4+
* MySQL 5.7+
* Stripe account
* Composer (for dependency installation)

== Migration Guide ==

**If you have mixed payment statuses:**

1. **Automatic Migration**: Upload plugin v2.7.4+ - migration runs automatically
2. **Manual Migration**: Go to PuzzlePath → Settings → Database Maintenance → Click "Migrate Payment Statuses"
3. **Verification**: Check PuzzlePath → Bookings to confirm all statuses show "paid"
4. **Revenue Fix**: Quest Manager should now show accurate revenue calculations
