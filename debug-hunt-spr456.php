<?php
/**
 * Debug script to check SRP456 hunt code issue
 * Run this to diagnose why booking codes aren't working
 */

// Load WordPress if running from command line
if (php_sapi_name() === 'cli') {
    require_once('../../../wp-config.php');
}

function debug_hunt_spr456() {
    global $wpdb;
    
    echo "🔍 PuzzlePath Hunt Code Debug: SRP456\n";
    echo "=====================================\n\n";
    
    $events_table = $wpdb->prefix . 'pp_events';
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    
    // 1. Check if events table exists
    echo "1️⃣ Checking Events Table:\n";
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$events_table}'");
    if ($table_exists) {
        echo "   ✅ Table {$events_table} exists\n";
    } else {
        echo "   ❌ Table {$events_table} does not exist!\n";
        return;
    }
    
    // 2. Look for SRP456 hunt code
    echo "\n2️⃣ Searching for SRP456 Hunt Code:\n";
    $spr_quest = $wpdb->get_row("SELECT * FROM {$events_table} WHERE hunt_code = 'SRP456'");
    
    if ($spr_quest) {
        echo "   ✅ Found quest with hunt code 'SRP456':\n";
        echo "   - ID: {$spr_quest->id}\n";
        echo "   - Title: {$spr_quest->title}\n";
        echo "   - Hunt Name: " . ($spr_quest->hunt_name ?: 'Not set') . "\n";
        echo "   - Location: {$spr_quest->location}\n";
        echo "   - Seats: {$spr_quest->seats}\n";
        echo "   - Price: \${$spr_quest->price}\n";
    } else {
        echo "   ❌ No quest found with hunt code 'SRP456'\n";
        
        // Check for similar hunt codes
        echo "\n   🔍 Checking for similar hunt codes:\n";
        $similar_codes = $wpdb->get_results("SELECT id, title, hunt_code, location FROM {$events_table} WHERE hunt_code LIKE '%SRP%' OR hunt_code LIKE '%456%'");
        
        if ($similar_codes) {
            foreach ($similar_codes as $quest) {
                echo "   - ID {$quest->id}: '{$quest->hunt_code}' - {$quest->title} ({$quest->location})\n";
            }
        } else {
            echo "   - No similar hunt codes found\n";
        }
    }
    
    // 3. Show all available hunt codes
    echo "\n3️⃣ All Available Hunt Codes:\n";
    $all_quests = $wpdb->get_results("SELECT id, title, hunt_code, hunt_name, location, seats FROM {$events_table} ORDER BY id");
    
    if ($all_quests) {
        foreach ($all_quests as $quest) {
            $status = ($quest->seats > 0) ? '✅' : '❌';
            echo "   {$status} '{$quest->hunt_code}' - {$quest->title} ({$quest->location}) - {$quest->seats} seats\n";
        }
    } else {
        echo "   ❌ No quests found in database!\n";
    }
    
    // 4. Check for the test bookings
    echo "\n4️⃣ Checking Test Bookings:\n";
    $test_bookings = $wpdb->get_results("
        SELECT booking_code, hunt_id, customer_name, payment_status 
        FROM {$bookings_table} 
        WHERE booking_code IN ('SRP456-20251023-9033', 'SRP456-20251016-9032', 'SRP456-20251009-9031')
    ");
    
    if ($test_bookings) {
        foreach ($test_bookings as $booking) {
            $status = ($booking->payment_status === 'paid') ? '✅ PAID' : '❌ ' . strtoupper($booking->payment_status);
            echo "   {$status} {$booking->booking_code} - {$booking->customer_name} (hunt_id: {$booking->hunt_id})\n";
        }
    } else {
        echo "   ❌ Test booking codes not found in database\n";
    }
    
    // 5. Check unified view
    echo "\n5️⃣ Checking Unified View:\n";
    $unified_view = $wpdb->prefix . 'pp_bookings_unified';
    $view_exists = $wpdb->get_var("SHOW TABLES LIKE '{$unified_view}'");
    
    if ($view_exists) {
        echo "   ✅ Unified view exists: {$unified_view}\n";
        
        $unified_data = $wpdb->get_results("
            SELECT booking_code, hunt_id, hunt_code, event_title, payment_status 
            FROM {$unified_view} 
            WHERE booking_code LIKE 'SRP456%'
        ");
        
        if ($unified_data) {
            foreach ($unified_data as $row) {
                echo "   - {$row->booking_code}: hunt_id='{$row->hunt_id}', hunt_code='{$row->hunt_code}', title='{$row->event_title}'\n";
            }
        } else {
            echo "   - No SRP456 bookings found in unified view\n";
        }
    } else {
        echo "   ❌ Unified view does not exist: {$unified_view}\n";
    }
    
    echo "\n💡 Recommendations:\n";
    echo "================\n";
    
    if (!$spr_quest) {
        echo "🔧 ISSUE: No quest exists with hunt code 'SRP456'\n";
        echo "   Solutions:\n";
        echo "   1. Create a quest with hunt code 'SRP456'\n";
        echo "   2. Or update existing quest to use 'SRP456' as hunt code\n";
        echo "   3. Or regenerate test bookings with correct hunt codes\n\n";
        
        echo "🎯 Quick Fix - Update Existing Quest:\n";
        if ($all_quests) {
            $first_quest = $all_quests[0];
            echo "   UPDATE {$events_table} SET hunt_code = 'SRP456' WHERE id = {$first_quest->id};\n";
            echo "   UPDATE {$bookings_table} SET hunt_id = 'SRP456' WHERE booking_code LIKE 'SRP456%';\n";
        }
    } else {
        echo "✅ Quest exists, check Unified App configuration\n";
    }
}

// Run if called directly
if (php_sapi_name() === 'cli') {
    debug_hunt_spr456();
} else {
    // Web interface
    echo "<pre>";
    debug_hunt_spr456();
    echo "</pre>";
}
?>