# PuzzlePath Booking Plugin - Deployment Checklist
## Version 2.3.1 with Floating Logo

### 🎯 **What's New in This Version:**
- ✅ Floating, bouncing Puzzle Path logo above booking form
- ✅ Logo positioned 210px above form (150px on mobile)
- ✅ 168px logo size (40% bigger than original concept)
- ✅ Smooth floating animation with hover bounce effect
- ✅ Responsive design for mobile devices
- ✅ Drop shadow effects for floating appearance
- ✅ Rebuilt coupon system with modern validation
- ✅ Stripe payment integration maintained

---

## 🚀 **Pre-Deployment Steps:**

### 1. **Backup Current Live Site**
- [ ] Backup current plugin files from live site
- [ ] Backup WordPress database (in case of rollback needed)
- [ ] Note current plugin version on live site

### 2. **Verify Local Installation**
- [ ] Test booking form displays correctly in local XAMPP
- [ ] Confirm floating logo appears and animates
- [ ] Test on mobile browser/responsive view
- [ ] Verify coupon system works
- [ ] Test Stripe payment flow (if configured)

---

## 📦 **Deployment Options:**

### **Option A: Full Plugin Replacement (Recommended)**
1. [ ] Download the zip file: `puzzlepath-booking-v2.3.1-with-floating-logo.zip`
2. [ ] Access your live site via FTP/cPanel File Manager/SSH
3. [ ] Navigate to `/wp-content/plugins/`
4. [ ] **Deactivate** PuzzlePath Booking plugin in WordPress admin
5. [ ] Rename existing `puzzlepath-booking` folder to `puzzlepath-booking-backup`
6. [ ] Upload and extract the new zip file
7. [ ] **Reactivate** the plugin in WordPress admin
8. [ ] Test the booking form on live site

### **Option B: Individual File Updates**
1. [ ] **Deactivate** plugin in WordPress admin first
2. [ ] Upload these specific files to `/wp-content/plugins/puzzlepath-booking/`:
   - [ ] `puzzlepath-booking.php` (updated with logo HTML)
   - [ ] `css/booking-form.css` (updated with floating logo styles)
   - [ ] Create `images/` directory
   - [ ] Upload `images/puzzlepath-logo.png`
3. [ ] **Reactivate** plugin in WordPress admin
4. [ ] Test the booking form

---

## ✅ **Post-Deployment Testing:**

### **Essential Tests:**
- [ ] Visit page with `[puzzlepath_booking_form]` shortcode
- [ ] Confirm floating logo appears above form
- [ ] Test logo animation (should float up/down continuously)
- [ ] Hover over logo to confirm bouncing effect
- [ ] Test on mobile device/browser responsive mode
- [ ] Verify booking form functionality:
  - [ ] Event selection works
  - [ ] Coupon codes can be applied
  - [ ] Price calculations are correct
  - [ ] Stripe payment fields appear
  - [ ] Form submission works (test mode)

### **Browser Cache:**
- [ ] Hard refresh the booking page (Ctrl+F5 or Cmd+Shift+R)
- [ ] Test in incognito/private browser window
- [ ] Clear site cache if using caching plugins

### **Mobile Testing:**
- [ ] Logo appears at correct size (112px on mobile)
- [ ] Logo positioned properly above form
- [ ] Form remains functional on mobile
- [ ] Animations work smoothly on mobile

---

## 🔧 **Key Files Changed:**

| File | Changes Made |
|------|-------------|
| `puzzlepath-booking.php` | Added floating logo HTML, updated CSS version to 1.3.0 |
| `css/booking-form.css` | Added floating logo styles, animations, responsive design |
| `images/puzzlepath-logo.png` | New logo file (copied from your OneDrive) |

---

## 🐛 **Troubleshooting:**

### **Logo Not Appearing:**
- Check if `images/puzzlepath-logo.png` file uploaded correctly
- Verify file permissions (644 for files, 755 for directories)
- Hard refresh browser to clear CSS cache

### **Logo Not Animating:**
- Confirm CSS version updated to 1.3.0
- Check browser developer tools for CSS errors
- Clear any caching plugins

### **Plugin Won't Activate:**
- Check PHP error logs
- Verify all files uploaded correctly
- Ensure `puzzlepath-booking.php` is in the root plugin directory

### **Booking Form Broken:**
- Rollback to backup version immediately
- Check JavaScript console for errors
- Verify Stripe configuration if payment issues

---

## 📞 **Rollback Plan:**
If issues occur:
1. **Deactivate** the new plugin version
2. **Restore** the backup version you created
3. **Reactivate** the old version
4. **Report** specific error messages for troubleshooting

---

## 📋 **File Structure Reference:**
```
puzzlepath-booking/
├── puzzlepath-booking.php (✅ Updated)
├── css/
│   └── booking-form.css (✅ Updated)
├── js/
│   ├── booking-form.js
│   └── stripe-payment.js
├── includes/
│   ├── coupons.php
│   ├── events.php
│   ├── settings.php
│   └── stripe-integration.php
├── images/ (🆕 New directory)
│   └── puzzlepath-logo.png (🆕 New file)
├── vendor/ (Composer dependencies)
└── composer.json
```

---

**Deployment prepared by:** Agent Mode  
**Date:** July 20, 2025  
**Plugin Version:** 2.3.1 with Floating Logo  
**CSS Version:** 1.3.0
