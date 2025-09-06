# Changelog

All notable changes to the PuzzlePath Booking System will be documented in this file.

## [2.7.3] - 2025-09-06

### 🔧 FIXED
- **CRITICAL**: Fixed Total Revenue calculation not working
- **BREAKING**: Updated payment status system from 'succeeded' to 'paid'
- All existing bookings automatically migrated from 'succeeded' to 'paid' status
- Revenue calculations now use 'paid' status consistently
- Filter dropdown updated to show 'Paid' instead of 'Succeeded'
- Status colors and refund conditions updated for 'paid' status
- Booking details modal updated to use 'paid' status
- Unified view updated to use 'paid' status in WHERE clause

### ⚡ IMPROVED
- Added automatic payment status migration on plugin update
- Added admin notice showing successful migration count
- Enhanced error handling in Stripe integration
- Better consistency across payment status references

### 🛠 TECHNICAL
- Updated Stripe integration to set 'paid' status on successful payment
- Modified `puzzlepath_get_booking_stats()` function for correct revenue calculation
- Added `puzzlepath_update_payment_statuses()` migration function
- Updated database views and queries to use 'paid' status
- Version bump triggers automatic migration

### 📦 DEPENDENCIES
- Stripe PHP SDK v10.21.0 required (install via composer)
- Added composer.json for dependency management

---

## [2.7.2] - Previous Release
- Comprehensive booking management system
- Quest manager functionality  
- Full Stripe payment integration
- Coupon system with validation
- Hunt code generation for unified app integration
- REST API endpoints
- Advanced admin interfaces

---

## Deployment Notes

### Required for Production:
1. Upload all plugin files
2. Install Stripe dependencies: `composer install --no-dev`
3. Or manually upload the `vendor/` directory
4. Plugin will automatically migrate payment statuses on first load

### Breaking Changes in 2.7.3:
- Payment status 'succeeded' changed to 'paid' (automatic migration)
- Revenue calculations require 'paid' status bookings
