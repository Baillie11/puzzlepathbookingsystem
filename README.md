# PuzzlePath Booking Plugin

**Version:** 2.9.0  
**WordPress Plugin for:** PuzzlePath Scavenger Hunts  
**Author:** Andrew Baillie  
**Last Updated:** 2025-11-19

---

## 📋 Overview

A comprehensive WordPress booking plugin for managing PuzzlePath scavenger hunt bookings, including Stripe payment integration, coupon management, quest management, and a tester registration system.

---

## 🎯 Core Features

### 1. **Booking Management**
- Full booking lifecycle management
- Stripe payment integration (test & live modes)
- Coupon/discount system with usage limits
- Comprehensive admin dashboard
- CSV export functionality
- Advanced filtering and search
- Audit trail logging for all changes

### 2. **Quest/Event Management**
- Create and manage scavenger hunts
- Hunt codes and naming
- Hosted vs self-hosted events
- Seat management with oversell protection
- Quest difficulty and type classification
- Display controls for website visibility
- JSON import system for quests

### 3. **Tester Registration System** ⭐ NEW in v2.9.0
- Secure registration page for designated testers
- Automatic booking creation for all open hunts
- Free lifetime double passes (2 tickets per hunt)
- Comprehensive email notifications
- Admin visibility with tester badges
- Full audit logging

### 4. **Email System**
- HTML email templates
- Booking confirmation emails
- Tester welcome emails with all details
- Customizable email templates
- Logo and branding support

### 5. **App Integration**
- Unified app API endpoints
- Booking code validation
- Quest data synchronization
- Real-time booking status

---

## 🗂️ Project Structure

```
puzzlepath-booking/
├── puzzlepath-booking.php          # Main plugin file (core logic)
├── includes/
│   └── stripe-integration.php      # Stripe payment processing
├── vendor/                          # Composer dependencies (Stripe SDK)
├── CHANGELOG.md                     # Version history
├── README.md                        # This file
├── TESTER-REGISTRATION.md           # Tester system documentation
└── QUICK-START.md                   # Quick start guide
```

---

## 🗄️ Database Schema

### Tables

#### `pp_events`
Stores scavenger hunt events/quests
- Primary fields: `id`, `title`, `hunt_code`, `hunt_name`, `location`, `price`, `seats`
- Display: `display_on_site`, `display_on_adventures_page`
- Classification: `quest_type`, `difficulty`, `hosting_type`
- Metadata: `event_date`, `duration_minutes`, `medal_image_url`, `quest_image_url`

#### `pp_bookings`
Stores all bookings (regular + tester)
- Core: `id`, `event_id`, `hunt_id`, `booking_code`
- Customer: `customer_name`, `customer_email`, `tickets`
- Payment: `total_price`, `payment_status`, `stripe_payment_intent_id`
- **Tester**: `is_tester` (TINYINT, 0/1) - NEW in v2.9.0
- Tracking: `created_at`, `booking_date`

#### `pp_coupons`
Discount codes and usage tracking
- Fields: `code`, `discount_percent`, `max_uses`, `times_used`, `expires_at`

#### `pp_clues`
Quest clue data for the mobile app
- Fields: `hunt_id`, `clue_order`, `clue_text`, `answer`, `latitude`, `longitude`

#### `pp_booking_audit`
Complete audit trail of all booking changes
- Tracks: user, action, old/new data, IP address, timestamp

### WordPress Options
- `puzzlepath_tester_access_key` - 32-char secure key for tester page
- `puzzlepath_email_template` - HTML email template
- `puzzlepath_stripe_*` - Stripe configuration keys
- `puzzlepath_booking_version` - Current database version

---

## 🔧 Key Functions Reference

### Tester System (v2.9.0)
```php
puzzlepath_tester_registration_shortcode()     // Main form handler
puzzlepath_process_tester_registration()       // Process submissions
puzzlepath_create_tester_bookings()            // Create bookings
puzzlepath_generate_tester_booking_code()      // Generate unique codes
puzzlepath_send_tester_email()                 // Send notification email
puzzlepath_get_client_ip()                     // IP detection for rate limiting
```

### Booking System
```php
puzzlepath_bookings_page()                     // Admin bookings dashboard
puzzlepath_process_refund()                    // Process booking refunds
puzzlepath_export_bookings_csv()               // Export to CSV
PuzzlePath_Audit_Logger::log_booking_*()       // Audit logging
```

### Payment Integration
```php
PuzzlePath_Stripe_Integration                  // Main Stripe class
  ->create_payment_intent()                    // Create Stripe payment
  ->handle_webhook()                           // Process Stripe webhooks
  ->process_free_booking()                     // Handle 100% discounts
```

---

## 🚀 Installation & Setup

### Requirements
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+
- Stripe PHP SDK (included via Composer)

### Installation Steps
1. Upload `puzzlepath-booking/` folder to `/wp-content/plugins/`
2. Activate plugin in WordPress Admin → Plugins
3. Configure Stripe keys in Settings → Stripe Settings
4. Plugin automatically creates required database tables

### Tester System Setup
1. Activate/reactivate plugin to generate access key
2. Retrieve key from dashboard or database
3. Share URL: `https://yoursite.com/tester-registration/?key=YOUR_KEY`
4. See `QUICK-START.md` for detailed instructions

---

## 🔐 Security Features

- WordPress nonce verification on all forms
- Prepared SQL statements (injection prevention)
- Sanitized user inputs
- CSRF protection
- Rate limiting (IP-based)
- Honeypot bot prevention
- Secure access key for tester page
- Audit logging for accountability

---

## 📧 Email System

### Templates
- Booking confirmation (regular customers)
- Tester welcome email (with all quest details)
- Customizable HTML templates via admin
- Logo and branding support

### Placeholders
- `{name}` - Customer name
- `{event_title}` - Quest title
- `{booking_code}` - Unique booking code
- `{price}` - Total price
- `{logo_url}` - Logo image URL
- `{app_url}` - App link with booking code

---

## 🧪 Testing

### Test Mode
- Stripe test mode toggle in admin
- Test card: 4242 4242 4242 4242
- Use any future expiry date and 3-digit CVC

### Tester Registration Test
1. Get access key from admin dashboard
2. Visit: `https://yoursite.com/tester-registration/?key=YOUR_KEY`
3. Register with test email
4. Verify bookings created with `is_tester = 1`
5. Check email for comprehensive details
6. Confirm 🧪 TESTER badge in admin

---

## 🐛 Troubleshooting

### Common Issues

**"Access Denied" on tester page**
- Verify access key in URL parameter
- Check key matches database value

**No hunts available for testers**
- Check events have `display_on_site = 1`
- Verify events have `seats >= 2`

**Email not received**
- Check WordPress mail settings
- Install WP Mail SMTP plugin
- Check spam folder
- Verify email logs

**Database errors**
- Deactivate and reactivate plugin
- Check database permissions
- Verify table prefixes match

---

## 📊 Version History

See `CHANGELOG.md` for detailed version history.

### Current: v2.9.0 (2025-11-19)
- ✅ Tester registration system
- ✅ Admin visibility enhancements
- ✅ Security improvements
- ✅ Comprehensive documentation

### Previous: v2.8.4
- Booking management improvements
- Enhanced audit logging
- Bug fixes

---

## 🔄 Git Workflow

### Current Branch Structure
- `master` - Production-ready code
- `feature/tester-registration` - v2.9.0 development (ready to merge)
- `clue-management-and-admin-notifications` - Previous feature branch

### Deployment Process
1. Test changes locally
2. Commit to feature branch
3. Test on staging environment
4. Merge to master
5. Deploy to production
6. Monitor logs and bookings

---

## 📞 Support & Contact

- **Email**: support@puzzlepath.com.au
- **Website**: https://puzzlepath.com.au
- **Documentation**: See individual MD files in this directory

---

## 📝 Developer Notes

### Important Files to Understand
1. `puzzlepath-booking.php` (lines 1-500) - Database setup and activation
2. `puzzlepath-booking.php` (lines 2273-2755) - Tester registration system
3. `puzzlepath-booking.php` (lines 4470-5000) - Admin booking management
4. `includes/stripe-integration.php` - Payment processing

### Database Prefix
- Default: `wp_`
- Production: `wp2s_` (used on puzzlepath.com.au)
- Dynamically retrieved via `$wpdb->prefix`

### Code Style
- WordPress coding standards
- Prepared statements for all queries
- Consistent naming: `puzzlepath_*` for functions
- Comments for complex logic
- Error logging via `error_log()`

### Testing Hooks
- Use `WP_DEBUG` constant for verbose logging
- Audit logs in `pp_booking_audit` table
- Email debug in error logs

---

## 🎯 Future Enhancements

Potential improvements for future versions:
- [ ] Admin UI to rotate access key
- [ ] Tester-specific analytics dashboard
- [ ] Tester feedback form integration
- [ ] Expiring tester access (time-limited)
- [ ] Selective hunt assignment (not all hunts)
- [ ] Bulk tester management
- [ ] Tester activity tracking

---

## 📄 License

Proprietary - PuzzlePath Scavenger Hunts  
© 2025 Andrew Baillie

---

**Built with ❤️ for PuzzlePath Scavenger Hunts**
