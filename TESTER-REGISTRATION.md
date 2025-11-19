# PuzzlePath Tester Registration System

**Version:** 2.9.0  
**Status:** ✅ Complete and Ready for Testing

---

## 🎯 Overview

The Tester Registration System allows designated testers to register for **free double passes (2 tickets)** to all currently available scavenger hunts through a secure, dedicated registration page.

---

## 📋 Features Implemented

### 1. **Database Schema**
- ✅ Added `is_tester` column to `pp_bookings` table
- ✅ Default value: `0` (backward compatible)
- ✅ Automatically created on plugin activation

### 2. **Secure Access System**
- ✅ Auto-generated 32-character access key
- ✅ Stored in WordPress options: `puzzlepath_tester_access_key`
- ✅ Access-controlled page (requires `?key=...` parameter)
- ✅ Friendly "Access Denied" message without valid key

### 3. **Auto-Created Page**
- ✅ **Page Title:** PuzzlePath Tester Registration
- ✅ **Slug:** `tester-registration`
- ✅ **Status:** Published (not in menus)
- ✅ **Shortcode:** `[puzzlepath_tester_registration]`

### 4. **Registration Form**
- ✅ Clean, styled interface
- ✅ Fields: Name (required), Email (required)
- ✅ WordPress nonce security
- ✅ Honeypot field for bot prevention
- ✅ Rate limiting (3 attempts per 15 minutes per IP)
- ✅ Duplicate registration prevention

### 5. **Booking Generation**
- ✅ Fetches all open hunts (`display_on_site = 1` AND `seats >= 2`)
- ✅ Creates 2 tickets per hunt (double pass)
- ✅ All bookings are FREE (`total_price = 0.00`)
- ✅ Payment status set to `paid` (completed)
- ✅ Unique booking codes generated per hunt
- ✅ Transactional processing (all-or-nothing)
- ✅ Automatic seat decrement with oversell protection
- ✅ Skips hunts with insufficient seats gracefully

### 6. **Email Notifications**
- ✅ Comprehensive HTML email sent to tester
- ✅ Subject: "Your PuzzlePath Tester Access - All Quest Details"
- ✅ Includes:
  - Welcome message
  - Table of all quests with booking codes
  - Direct "Start Quest" links for each hunt
  - Testing guidelines and instructions
  - Support contact information
  - Note about any skipped hunts

### 7. **Admin Visibility**
- ✅ "🧪 TESTER" badge on booking rows
- ✅ Filter dropdown: "All Bookings", "🧪 Testers Only", "Regular Bookings"
- ✅ Testers appear in all booking lists and exports
- ✅ Audit trail logging for all tester bookings

### 8. **Security Features**
- ✅ URL access key requirement
- ✅ WordPress nonce verification
- ✅ Honeypot bot prevention
- ✅ IP-based rate limiting
- ✅ Email validation
- ✅ Sanitized inputs

---

## 🚀 How to Use

### For Admins

#### Step 1: Get the Access Key
After plugin activation, retrieve the access key:

```php
$access_key = get_option('puzzlepath_tester_access_key');
echo $access_key; // Copy this key
```

Or check in WordPress Admin → Settings → Options (search for `puzzlepath_tester_access_key`)

#### Step 2: Share the Registration URL
Give testers this URL format:
```
https://yoursite.com/tester-registration/?key=YOUR_ACCESS_KEY_HERE
```

**Example:**
```
https://puzzlepath.com.au/tester-registration/?key=abc123xyz789...
```

#### Step 3: View Tester Bookings
1. Go to **Admin → Bookings**
2. Use the filter dropdown: **"🧪 Testers Only"**
3. Look for the green **🧪 TESTER** badge on booking rows

---

### For Testers

1. **Access** the registration URL (provided by admin)
2. **Fill out** the form with your name and email
3. **Submit** the registration
4. **Check email** for booking codes and quest links
5. **Start testing** using the app links provided

---

## 📊 Database Structure

### Updated `pp_bookings` Table
```sql
ALTER TABLE pp_bookings 
ADD COLUMN is_tester TINYINT(1) NOT NULL DEFAULT 0 
AFTER booking_date;
```

### Tester Booking Example
```
event_id: 5
hunt_id: "SOUTH01"
customer_name: "John Tester"
customer_email: "john@example.com"
tickets: 2
total_price: 0.00
payment_status: "paid"
is_tester: 1
booking_code: "SOUTH01-20231119-1234"
```

---

## 🔒 Security Measures

| Feature | Implementation |
|---------|----------------|
| **Access Control** | 32-character random key required in URL |
| **CSRF Protection** | WordPress nonce verification |
| **Bot Prevention** | Hidden honeypot field |
| **Rate Limiting** | 3 attempts per 15 min per IP |
| **Duplicate Check** | Email-based duplicate prevention |
| **Input Sanitization** | WordPress sanitize functions |
| **Email Validation** | Built-in WordPress `is_email()` |

---

## 📧 Email Template

The tester email includes:

1. **Header**: Welcome message and explanation
2. **Quest Table**: All bookings with:
   - Quest title and date
   - Location
   - Booking code
   - "Start Quest" button (app link)
3. **Testing Guidelines**: What to test and how to report issues
4. **Support Contact**: Email address for help
5. **Skipped Hunts Note**: If any hunts couldn't be booked

---

## 🧪 Testing Checklist

### Before Deployment
- [ ] Activate plugin on staging
- [ ] Verify `is_tester` column exists
- [ ] Retrieve access key
- [ ] Access page without key → Should deny
- [ ] Access page with key → Should show form
- [ ] Submit empty form → Should validate
- [ ] Submit with valid email → Should create bookings
- [ ] Check email inbox → Should receive email
- [ ] Verify bookings in admin → Should see TESTER badge
- [ ] Try duplicate registration → Should reject
- [ ] Use tester filter → Should show only testers

### After Deployment
- [ ] Test on production with real tester email
- [ ] Verify seats are decremented correctly
- [ ] Check audit logs
- [ ] Confirm email delivery
- [ ] Test all app links in email

---

## 🛠️ Troubleshooting

### Issue: Access Key Not Found
**Solution:** Deactivate and reactivate the plugin to generate a new key.

### Issue: No Hunts Available
**Cause:** No events have `display_on_site = 1` AND `seats >= 2`  
**Solution:** Update events in admin or wait for more seats.

### Issue: Email Not Received
**Check:**
1. WordPress email settings
2. SMTP configuration
3. Spam folder
4. Email logs in `wp_mail` debug

### Issue: Tester Badge Not Showing
**Cause:** Old bookings don't have `is_tester` column  
**Solution:** Run migration or manually update schema.

---

## 📝 Code Locations

| Feature | File | Lines |
|---------|------|-------|
| **Database Schema** | `puzzlepath-booking.php` | 235-239 |
| **Access Key Generation** | `puzzlepath-booking.php` | 241-245 |
| **Page Auto-Creation** | `puzzlepath-booking.php` | 247-260 |
| **Shortcode** | `puzzlepath-booking.php` | 2279-2349 |
| **Form Processing** | `puzzlepath-booking.php` | 2355-2473 |
| **Booking Creation** | `puzzlepath-booking.php` | 2479-2592 |
| **Email Sending** | `puzzlepath-booking.php` | 2623-2733 |
| **Admin Filter** | `puzzlepath-booking.php` | 4563, 4608-4612, 4768-4772 |
| **Admin Badge** | `puzzlepath-booking.php` | 4916-4918 |

---

## 🔄 Future Enhancements

- [ ] Admin UI to view/rotate access key
- [ ] Ability to grant testers to specific hunts (not all)
- [ ] Tester expiry dates (revoke access after X days)
- [ ] Tester feedback form integration
- [ ] Export tester-specific analytics

---

## 📞 Support

For questions or issues with the tester registration system:
- **Email:** support@puzzlepath.com.au
- **Documentation:** This file
- **Audit Logs:** Check `pp_booking_audit` table

---

## ✅ Version History

### Version 2.9.0 (Current)
- ✅ Initial tester registration system
- ✅ Database schema updates
- ✅ Secure access key system
- ✅ Complete booking automation
- ✅ HTML email notifications
- ✅ Admin visibility features

---

**Built with ❤️ for PuzzlePath Scavenger Hunts**
