# PuzzlePath Booking Plugin

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
2. Customize the email template for booking confirmations
3. Use placeholders: `{name}`, `{event_title}`, `{event_date}`, `{price}`

## Database Tables

The plugin creates three database tables:

- `wp_pp_events`: Stores event information
- `wp_pp_bookings`: Stores booking records
- `wp_pp_coupons`: Stores discount codes

## Version History

- **2.4.0** (July 20, 2025): **STABLE WORKING VERSION** - Floating animated logo, fixed PHP header warnings, fully functional booking system with Stripe integration
- **2.3.1**: Previous stable version
- **2.0.0**: Simplified version without payment processing
- **1.1.15**: Previous version with Stripe integration

### Recent Updates (v2.4.0)

- ✅ **Floating Logo Animation**: Added PuzzlePath logo with smooth floating animation above booking form
- ✅ **Mobile Responsive**: Logo and form work perfectly on all device sizes
- ✅ **PHP Header Fix**: Resolved "headers already sent" warnings during event creation/deletion
- ✅ **Output Buffering**: Implemented proper output buffering in event management
- ✅ **Cache Busting**: Updated CSS versioning for immediate style updates
- ✅ **Deployment Ready**: Includes deployment checklist and zip packaging

**Status**: Fully tested and working in local XAMPP environment. Ready for live deployment.

## Support

For support, contact Andrew Baillie at Click eCommerce. 