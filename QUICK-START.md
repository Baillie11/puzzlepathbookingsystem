# 🚀 Tester Registration System - Quick Start Guide

## Step 1: Activate the Plugin

1. Navigate to WordPress Admin → Plugins
2. Find **PuzzlePath Booking**
3. Click **Deactivate** (if currently active)
4. Click **Activate**

This will:
- ✅ Add the `is_tester` column to your database
- ✅ Generate a secure access key
- ✅ Create the tester registration page

---

## Step 2: Get Your Access Key

### Option A: Using PHP (Quick)
Add this temporarily to your theme's `functions.php`:

```php
add_action('admin_notices', function() {
    $key = get_option('puzzlepath_tester_access_key');
    echo '<div class="notice notice-info"><p><strong>Tester Access Key:</strong> <code>' . $key . '</code></p></div>';
});
```

Visit any admin page and copy the key, then remove the code.

### Option B: Using Database Query
```sql
SELECT option_value FROM wp_options 
WHERE option_name = 'puzzlepath_tester_access_key';
```

### Option C: Using WordPress CLI (if available)
```bash
wp option get puzzlepath_tester_access_key
```

---

## Step 3: Share the URL with Testers

Send your testers this URL:

```
https://yourdomain.com/tester-registration/?key=YOUR_ACCESS_KEY_HERE
```

**Example:**
```
https://puzzlepath.com.au/tester-registration/?key=abc123xyz789def456ghi...
```

⚠️ **Important:** Keep this URL secure! Anyone with the key can register as a tester.

---

## Step 4: Monitor Tester Bookings

1. Go to **Admin → Bookings**
2. Look for the green **🧪 TESTER** badge
3. Use the filter: **"🧪 Testers Only"** to see all tester bookings

---

## What Happens When a Tester Registers?

1. **Form Submission** → Tester enters name and email
2. **Validation** → System checks for duplicates and validates inputs
3. **Booking Creation** → Creates 2 free tickets for each open hunt
4. **Email Sent** → Comprehensive email with all codes and links
5. **Admin Badge** → Bookings appear with 🧪 TESTER badge

---

## Troubleshooting

### "Access Denied" Error
- Check that you're using the correct access key
- Verify the key parameter is in the URL: `?key=...`

### No Hunts Available
- Check that hunts have `display_on_site = 1`
- Verify hunts have at least 2 available seats
- Go to Admin → Events and update settings

### Email Not Received
- Check spam/junk folder
- Verify WordPress mail is working: Install WP Mail SMTP plugin
- Check error logs in `wp-content/debug.log`

### Tester Can't Register Twice
- This is intentional (duplicate prevention)
- If needed, delete their existing tester bookings from admin
- Or use a different email address

---

## Testing Checklist

Before giving the URL to real testers:

- [ ] Plugin activated successfully
- [ ] Access key retrieved
- [ ] Test page without key → See "Access Denied"
- [ ] Test page with key → See registration form
- [ ] Submit test registration
- [ ] Verify email received
- [ ] Check bookings appear in admin
- [ ] Confirm TESTER badge shows
- [ ] Test tester filter works

---

## Need Help?

- 📧 Email: support@puzzlepath.com.au
- 📖 Full Documentation: See `TESTER-REGISTRATION.md`
- 🐛 Issues: Check WordPress error logs

---

**Ready to go!** 🎉

Send the registration URL to your testers and they'll receive instant access to all open hunts with double passes.
