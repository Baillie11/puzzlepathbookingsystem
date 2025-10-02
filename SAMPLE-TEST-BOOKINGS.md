# PuzzlePath Test Booking Codes - Sample

**Generated on:** 2025-01-10

This document shows what test booking codes will be generated for your active quests.

## Sample Quest 1: Broadbeach Quest
**Location:** Broadbeach  
**Hunt Code:** BB  
**Price per Person:** $35

### BB-20250108-9001
- **Customer:** Alice Johnson
- **Email:** alice.test@puzzlepath.com.au
- **Participants:** Alice Johnson, Bob Smith
- **Party Size:** 2 people
- **Booking Date:** 2025-01-08
- **Total Price:** $70
- **Status:** PAID ✅

### BB-20250115-9002
- **Customer:** Charlie Brown
- **Email:** charlie.test@puzzlepath.com.au
- **Participants:** Charlie Brown, Diana White, Emma Davis
- **Party Size:** 3 people
- **Booking Date:** 2025-01-15
- **Total Price:** $105
- **Status:** PAID ✅

### BB-20250122-9003
- **Customer:** Frank Wilson
- **Email:** frank.test@puzzlepath.com.au
- **Participants:** Frank Wilson, Grace Lee
- **Party Size:** 2 people
- **Booking Date:** 2025-01-22
- **Total Price:** $70
- **Status:** PAID ✅

## Sample Quest 2: Emerald Lakes Explorer's Quest
**Location:** Emerald Park  
**Hunt Code:** EP  
**Price per Person:** $30

### EP-20250108-9004
- **Customer:** Alice Johnson
- **Email:** alice.test@puzzlepath.com.au
- **Participants:** Alice Johnson, Bob Smith
- **Party Size:** 2 people
- **Booking Date:** 2025-01-08
- **Total Price:** $60
- **Status:** PAID ✅

### EP-20250115-9005
- **Customer:** Charlie Brown
- **Email:** charlie.test@puzzlepath.com.au
- **Participants:** Charlie Brown, Diana White, Emma Davis
- **Party Size:** 3 people
- **Booking Date:** 2025-01-15
- **Total Price:** $90
- **Status:** PAID ✅

### EP-20250122-9006
- **Customer:** Frank Wilson
- **Email:** frank.test@puzzlepath.com.au
- **Participants:** Frank Wilson, Grace Lee
- **Party Size:** 2 people
- **Booking Date:** 2025-01-22
- **Total Price:** $60
- **Status:** PAID ✅

## Testing Instructions

1. **Open the Unified PuzzlePath App:** https://app.puzzlepath.com.au
2. **Enter any booking code** from the list above
3. **Verify the quest loads** with correct details
4. **Test the complete quest flow** including:
   - Booking verification ✅
   - Quest loading ✅
   - Customer name recognition ✅
   - Clue progression ✅
   - Answer validation ✅
   - Hint system ✅
   - Completion ceremony ✅

## Key Features of Test Bookings

- **✅ Realistic Data:** Proper customer names, emails, and party compositions
- **✅ PAID Status:** All bookings have 'paid' payment status for immediate testing
- **✅ Future Dates:** Booking dates are set 7, 14, and 21 days in the future
- **✅ Proper Hunt Codes:** Each booking code matches the quest's hunt code
- **✅ Easy Identification:** Test booking codes use '9XXX' sequence numbers
- **✅ Easy Cleanup:** All test data can be removed with a single cleanup script

## Booking Code Format

Format: `HUNTCODE-YYYYMMDD-9XXX`

Where:
- **HUNTCODE** = The quest's hunt code (BB, EP, etc.)
- **YYYYMMDD** = Booking date in year-month-day format
- **9XXX** = Sequential number starting with 9 for easy identification

## Cleanup

When testing is complete, run the cleanup script:
```bash
php cleanup-test-bookings.php
```

Or use the **Test Bookings** admin page in your WordPress dashboard:
**PuzzlePath → Test Bookings → Cleanup Test Bookings**

---

**Note:** This is a sample document. The actual test bookings will be generated based on your current active quests and will include current dates plus 7/14/21 days.