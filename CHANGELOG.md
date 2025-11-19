# Changelog - PuzzlePath Booking Plugin

All notable changes to this project will be documented in this file.

---

## [2.9.0] - 2025-11-19

### 🎉 Major Feature: Tester Registration System

A complete, secure system allowing designated testers to receive free lifetime access (double passes) to all scavenger hunts.

#### Added
- **Tester Registration Page**: Auto-created `/tester-registration/` page with secure access key requirement
- **Database Schema**: New `is_tester` column in `pp_bookings` table for tester identification
- **Secure Access System**: 32-character random access key stored in WordPress options (`puzzlepath_tester_access_key`)
- **Registration Form**: Clean, styled form with Name and Email fields
- **Security Features**:
  - WordPress nonce verification
  - Honeypot bot prevention field
  - IP-based rate limiting (3 attempts per 15 minutes)
  - Duplicate registration prevention by email
  - URL access key requirement
- **Automated Booking System**:
  - Queries all open hunts (`display_on_site = 1` AND `seats >= 2`)
  - Creates 2 free tickets per hunt automatically
  - Transactional processing (all-or-nothing)
  - Automatic seat decrement with oversell protection
  - Gracefully skips hunts with insufficient seats
- **Email Notification System**:
  - Comprehensive HTML email with all booking details
  - Table of all quests with booking codes
  - Direct app links for each hunt
  - Testing guidelines and instructions
  - Support contact information
  - Notes about skipped hunts (if any)
- **Admin Features**:
  - Green "🧪 TESTER" badge on booking rows
  - Filter dropdown: "All Bookings", "🧪 Testers Only", "Regular Bookings"
  - Complete audit trail logging for all tester bookings
  - Testers appear in all booking lists and exports
- **Documentation**:
  - `TESTER-REGISTRATION.md` - Complete technical documentation
  - `QUICK-START.md` - Step-by-step activation guide
  - `CHANGELOG.md` - Version history and changes

#### Changed
- Plugin version updated from 2.8.4 to 2.9.0
- Database version updated to 2.9.0
- Tester registration page subtitle: "Register to receive free double passes to all scavenger hunts for life"

#### Fixed
- Syntax error: Removed duplicate closing brace on line 2270 (2025-11-19)

#### Technical Details
- **New Functions**:
  - `puzzlepath_tester_registration_shortcode()` - Main shortcode handler
  - `puzzlepath_process_tester_registration()` - Form processing and validation
  - `puzzlepath_create_tester_bookings()` - Transactional booking creation
  - `puzzlepath_generate_tester_booking_code()` - Unique code generation
  - `puzzlepath_send_tester_email()` - Aggregated email notification
  - `puzzlepath_get_client_ip()` - IP address detection for rate limiting

- **Database Changes**:
  ```sql
  ALTER TABLE wp_bookings 
  ADD COLUMN is_tester TINYINT(1) NOT NULL DEFAULT 0 
  AFTER booking_date;
  ```

- **New WordPress Options**:
  - `puzzlepath_tester_access_key` - 32-character secure access key

- **Code Locations**:
  - Lines 235-239: Database schema updates
  - Lines 241-260: Access key and page generation
  - Lines 2279-2349: Tester registration shortcode
  - Lines 2355-2473: Form processing
  - Lines 2479-2592: Booking creation
  - Lines 2623-2733: Email sending
  - Lines 4563, 4608-4612, 4768-4772: Admin filter
  - Lines 4916-4918: Admin badge

#### Migration Notes
- Plugin automatically creates `is_tester` column on activation
- Backward compatible - existing bookings default to `is_tester = 0`
- Access key auto-generated on first activation
- Tester page auto-created with proper slug and shortcode

---

## [2.8.4] - Previous Version

### Changed
- Various bug fixes and improvements
- Enhanced booking management features
- Updated audit logging system

---

## Version Numbering

We use [Semantic Versioning](https://semver.org/):
- **MAJOR** version for incompatible API changes
- **MINOR** version for backwards-compatible functionality additions
- **PATCH** version for backwards-compatible bug fixes

---

## Support

For questions or issues:
- **Email**: support@puzzlepath.com.au
- **Documentation**: See `TESTER-REGISTRATION.md` for feature details
- **Quick Start**: See `QUICK-START.md` for activation steps

---

## Git Repository

Branch: `feature/tester-registration`
Ready to merge to: `master`

Commits:
- `c8131d5` - Update tester registration text to emphasize lifetime access
- `97f0bc2` - Fix syntax error - remove duplicate closing brace on line 2270
- `fdd61aa` - Add quick start guide for tester registration system
- `4216b47` - Add comprehensive documentation for tester registration system
- `2e7070f` - Add tester registration system v2.9.0 - Complete feature implementation
