# PuzzlePath Booking System

[![Version](https://img.shields.io/badge/version-2.7.3-blue.svg)](https://github.com/Baillie11/puzzlepathbookingsystem/releases)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-green.svg)](LICENSE)

A comprehensive WordPress booking plugin for PuzzlePath experiences with advanced management features, Stripe payment integration, and unified app compatibility.

## ✨ Features

### 📊 **Advanced Admin Management**
- **Comprehensive Bookings Management** - Filter, sort, search, and manage all bookings
- **Event Management** - Create hosted and self-hosted events with hunt code generation
- **Quest Manager** - Statistics dashboard with completion tracking and revenue analytics
- **Coupon System** - Create discount codes with usage limits and expiry dates
- **Bulk Operations** - Refund multiple bookings, resend confirmation emails

### 💳 **Payment Processing**
- **Stripe Integration** - Complete payment processing with test/live mode
- **Webhook Support** - Automatic booking confirmation via Stripe webhooks
- **Refund Management** - Process refunds directly through Stripe
- **Free Booking Support** - Handle 100% discount bookings
- **Australian Payment Support** - Optimized for Australian customers

### 🔗 **Unified App Integration**
- **Hunt Code System** - Automatic generation for unified app compatibility
- **REST API Endpoints** - Full API for external app integration
- **Database Views** - Unified booking data structure
- **Booking Search** - Search by code, status, date ranges

### 🎨 **Frontend Experience**
- **Responsive Booking Form** - Mobile-friendly booking interface
- **AJAX Coupon Validation** - Real-time discount code checking
- **Progress Indicators** - Visual feedback during booking process
- **Professional Email Confirmations** - Branded HTML emails with quest app link
- **Mobile-Optimized Templates** - Email templates that look great on all devices

### 📈 **Analytics & Reporting**
- **Revenue Tracking** - Accurate revenue calculations and reporting
- **Booking Statistics** - Comprehensive statistics dashboard
- **CSV Export** - Export booking data with all filters applied
- **Quest Performance** - Track completion rates and participant numbers

## 🚀 Installation

### Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Stripe account (for payment processing)

### Quick Install

1. **Download the plugin**:
   ```bash
   git clone https://github.com/Baillie11/puzzlepathbookingsystem.git
   ```

2. **Upload to WordPress**:
   - Upload the entire folder to `/wp-content/plugins/puzzlepath-booking/`

3. **Install Stripe Dependencies** (REQUIRED):
   ```bash
   cd wp-content/plugins/puzzlepath-booking
   composer install --no-dev
   ```
   
   **OR** manually upload the `vendor/` directory with Stripe PHP library

4. **Activate the plugin** in WordPress admin

5. **Configure Stripe settings** in PuzzlePath → Stripe Settings

## ⚙️ Configuration

### Stripe Setup
1. Go to **PuzzlePath → Stripe Settings**
2. Configure your API keys (test/live)
3. Set up webhook endpoint: `https://yoursite.com/wp-json/puzzlepath/v1/stripe-webhook`
4. Enable `charge.succeeded` event in Stripe dashboard

### Basic Usage
1. Create events in **PuzzlePath → Events**
2. Add booking form to any page: `[puzzlepath_booking_form]`
3. Manage bookings in **PuzzlePath → Bookings**
4. Track performance in **PuzzlePath → Quest Manager**

## 🎯 Usage

### Shortcodes

**Booking Form**:
```php
[puzzlepath_booking_form]
```

### Admin Pages
- **Events** - Create and manage treasure hunt events
- **Bookings** - View, filter, and manage all bookings
- **Quest Manager** - Analytics and performance tracking
- **Coupons** - Create and manage discount codes
- **Stripe Settings** - Configure payment processing
- **Settings** - General plugin configuration

### REST API Endpoints

```
GET  /wp-json/puzzlepath/v1/bookings
GET  /wp-json/puzzlepath/v1/booking/{code}
GET  /wp-json/puzzlepath/v1/hunts
POST /wp-json/puzzlepath/v1/payment/create-intent
POST /wp-json/puzzlepath/v1/stripe-webhook
```

## 🔧 Development

### File Structure
```
puzzlepath-booking/
├── puzzlepath-booking.php          # Main plugin file
├── css/
│   └── booking-form.css            # Frontend styles
├── js/
│   ├── booking-form.js             # Form interactions
│   └── stripe-payment.js           # Payment processing
├── includes/
│   ├── stripe-integration.php      # Stripe API integration
│   ├── events.php                 # Event management
│   ├── coupons.php                # Coupon system
│   └── settings.php               # Settings pages
├── images/
│   └── puzzlepath-logo.png        # Plugin assets
└── vendor/                        # Composer dependencies
    └── stripe/stripe-php/         # Stripe PHP SDK
```

### Database Tables
- `wp_pp_events` - Event/hunt data
- `wp_pp_bookings` - Booking records
- `wp_pp_coupons` - Discount codes
- `wp_pp_bookings_unified` - Unified view for app integration

## 🚀 Deployment Instructions

### Production Deployment

**Critical**: The plugin requires Stripe PHP library dependencies:

1. **Upload all plugin files** to your server
2. **Install dependencies**:
   ```bash
   composer install --no-dev
   ```
   **OR** manually upload the complete `vendor/` directory
3. **Activate the plugin** - automatic database migration will occur
4. **Configure Stripe settings** for live mode
5. **Set up webhook** in your Stripe dashboard

### Migration Notes (v2.7.3)
- Plugin automatically migrates payment statuses from 'succeeded' to 'paid'
- Revenue calculations are updated for consistency
- Admin notice confirms migration completion

**Note**: The `vendor/` directory is excluded from git but **required for production**.

## 📋 Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

### Latest Release (v2.7.4)
- ✅ Professional HTML email confirmations with PuzzlePath branding
- ✅ Direct link to quest app (https://app.puzzlepath.com.au) in emails
- ✅ Responsive email template that looks great on all devices
- ✅ Enhanced settings page with template reset functionality
- ✅ Automatic plain-text fallback for better email deliverability
- ✅ Improved email template editor with full HTML support

## 🐛 Troubleshooting

### Common Issues

**"Stripe PHP library is not installed"**
- Solution: Run `composer install --no-dev` or upload `vendor/` directory

**Revenue showing $0.00**
- Solution: Update to v2.7.3+ for automatic payment status migration

**Webhook not working**
- Check webhook URL in Stripe dashboard
- Verify webhook secret in plugin settings
- Ensure `charge.succeeded` event is enabled

## 🤝 Support

- **Issues**: [GitHub Issues](https://github.com/Baillie11/puzzlepathbookingsystem/issues)
- **Documentation**: See README and CHANGELOG
- **Updates**: [GitHub Releases](https://github.com/Baillie11/puzzlepathbookingsystem/releases)

## 📄 License

This plugin is licensed under the GPL v2 or later.

---

**Developed by Andrew Baillie** | **Version 2.7.3** | **WordPress Plugin**

A simple WordPress booking plugin for PuzzlePath events with discount codes and email confirmation.

## Features

- **Event Management**: Create and manage events with dates, locations, prices, and seat availability
- **Booking System**: Simple booking form with name, email, and optional coupon codes
- **Coupon System**: Create discount codes with percentage discounts, usage limits, and expiry dates
- **Email Confirmation**: Automatic email confirmations sent to customers
- **Seat Management**: Automatic seat reduction when bookings are made
- **Admin Interface**: Clean WordPress admin interface for managing events and coupons

## Installation

1. Upload the plugin files to `/wp-content/plugins/puzzlepath-booking/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'PuzzlePath' in the admin menu to configure settings

## Usage

### Adding the Booking Form

Use the shortcode `[puzzlepath_booking_form]` on any page or post to display the booking form.

### Managing Events

1. Go to **PuzzlePath > Events** in the admin menu
2. Add new events with title, date/time, location, price, and available seats
3. View and delete existing events

### Managing Coupons

1. Go to **PuzzlePath > Coupons** in the admin menu
2. Create discount codes with percentage discounts
3. Set usage limits and expiry dates
4. View usage statistics

### Settings

1. Go to **PuzzlePath > Settings** in the admin menu
2. Customize the HTML email template for booking confirmations
3. Use placeholders: `{name}`, `{event_title}`, `{event_date}`, `{price}`, `{booking_code}`
4. Reset to professional default template anytime
5. Full HTML editor support with media upload capabilities

## Database Tables

The plugin creates three database tables:

- `wp_pp_events`: Stores event information
- `wp_pp_bookings`: Stores booking records
- `wp_pp_coupons`: Stores discount codes

## Version History

- **2.5.0** (July 20, 2025): **STABLE WORKING VERSION** - Floating animated logo, fixed PHP header warnings, merged remote improvements, fully functional booking system with Stripe integration
- **2.4.0**: Development version (skipped)
- **2.3.5**: Previous stable version
- **2.0.0**: Simplified version without payment processing
- **1.1.15**: Previous version with Stripe integration

### Recent Updates (v2.5.0)

- ✅ **Floating Logo Animation**: Added PuzzlePath logo with smooth floating animation above booking form
- ✅ **Mobile Responsive**: Logo and form work perfectly on all device sizes
- ✅ **PHP Header Fix**: Resolved "headers already sent" warnings during event creation/deletion
- ✅ **Output Buffering**: Implemented proper output buffering in event management
- ✅ **Cache Busting**: Updated CSS versioning for immediate style updates
- ✅ **Deployment Ready**: Includes deployment checklist and zip packaging

**Status**: Fully tested and working in local XAMPP environment. Ready for live deployment.

## Support

For support, contact Andrew Baillie at Click eCommerce. 