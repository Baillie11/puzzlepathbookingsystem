<?php
/**
 * Emergency Diagnostic Script for Southport Rockpool Quest Issues
 * This will help identify why booking codes aren't working
 */

// Load WordPress if running from command line
if (php_sapi_name() === 'cli') {
    require_once('../../../wp-config.php');
}

function debug_southport_rockpool() {
    global $wpdb;
    
    echo "🚨 EMERGENCY: Southport Rockpool Quest Debug\n";
    echo "==========================================\n\n";
    
    $events_table = $wpdb->prefix . 'pp_events';
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    $clues_table = $wpdb->prefix . 'pp_clues';
    $unified_view = $wpdb->prefix . 'pp_bookings_unified';
    
    // 1. Search for Southport Rockpool in all relevant ways
    echo "1️⃣ Searching for Southport Rockpool Quests:\n";
    echo "============================================\n";
    
    // Search in titles
    $title_search = $wpdb->get_results("
        SELECT id, title, hunt_code, hunt_name, location, price, seats, hosting_type 
        FROM {$events_table} 
        WHERE title LIKE '%Southport%' OR title LIKE '%Rockpool%'
        ORDER BY id
    ");
    
    if ($title_search) {
        echo "✅ Found by TITLE search:\n";
        foreach ($title_search as $quest) {
            $status = $quest->seats > 0 ? '✅ Active' : '❌ No seats';
            echo "   - ID {$quest->id}: '{$quest->title}'\n";
            echo "     Hunt Code: '{$quest->hunt_code}' | Hunt Name: '{$quest->hunt_name}'\n";
            echo "     Location: '{$quest->location}' | Price: \${$quest->price} | {$status}\n\n";
        }
    } else {
        echo "❌ No quests found with 'Southport' or 'Rockpool' in TITLE\n\n";
    }
    
    // Search in hunt_name
    $hunt_name_search = $wpdb->get_results("
        SELECT id, title, hunt_code, hunt_name, location, price, seats, hosting_type 
        FROM {$events_table} 
        WHERE hunt_name LIKE '%Southport%' OR hunt_name LIKE '%Rockpool%'
        ORDER BY id
    ");
    
    if ($hunt_name_search) {
        echo "✅ Found by HUNT NAME search:\n";
        foreach ($hunt_name_search as $quest) {
            $status = $quest->seats > 0 ? '✅ Active' : '❌ No seats';
            echo "   - ID {$quest->id}: '{$quest->title}'\n";
            echo "     Hunt Code: '{$quest->hunt_code}' | Hunt Name: '{$quest->hunt_name}'\n";
            echo "     Location: '{$quest->location}' | Price: \${$quest->price} | {$status}\n\n";
        }
    } else {
        echo "❌ No quests found with 'Southport' or 'Rockpool' in HUNT NAME\n\n";
    }
    
    // Search in location
    $location_search = $wpdb->get_results("
        SELECT id, title, hunt_code, hunt_name, location, price, seats, hosting_type 
        FROM {$events_table} 
        WHERE location LIKE '%Southport%' OR location LIKE '%Rockpool%'
        ORDER BY id
    ");
    
    if ($location_search) {
        echo "✅ Found by LOCATION search:\n";
        foreach ($location_search as $quest) {
            $status = $quest->seats > 0 ? '✅ Active' : '❌ No seats';
            echo "   - ID {$quest->id}: '{$quest->title}'\n";
            echo "     Hunt Code: '{$quest->hunt_code}' | Hunt Name: '{$quest->hunt_name}'\n";
            echo "     Location: '{$quest->location}' | Price: \${$quest->price} | {$status}\n\n";
        }
    } else {
        echo "❌ No quests found with 'Southport' or 'Rockpool' in LOCATION\n\n";
    }
    
    // 2. Search for booking codes that might be Southport Rockpool related
    echo "2️⃣ Searching for Southport Rockpool Booking Codes:\n";
    echo "===================================================\n";
    
    // Common patterns for Southport Rockpool
    $code_patterns = ['%SRP%', '%SOUTH%', '%ROCK%', '%SR%', '%SP%'];
    
    foreach ($code_patterns as $pattern) {
        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT booking_code, hunt_id, customer_name, customer_email, payment_status, total_price, created_at
            FROM {$bookings_table} 
            WHERE booking_code LIKE %s 
            ORDER BY created_at DESC
            LIMIT 10
        ", $pattern));
        
        if ($bookings) {
            echo "✅ Found bookings matching pattern '{$pattern}':\n";
            foreach ($bookings as $booking) {
                $status_icon = $booking->payment_status === 'paid' ? '✅' : 
                              ($booking->payment_status === 'pending' ? '⏳' : '❌');
                echo "   {$status_icon} {$booking->booking_code} - {$booking->customer_name}\n";
                echo "      Email: {$booking->customer_email} | Status: {$booking->payment_status} | \${$booking->total_price}\n";
                echo "      Hunt ID: '{$booking->hunt_id}' | Created: {$booking->created_at}\n\n";
            }
        }
    }
    
    // 3. Check for orphaned bookings (bookings without matching events)
    echo "3️⃣ Checking for Orphaned Bookings:\n";
    echo "===================================\n";
    
    $orphaned = $wpdb->get_results("
        SELECT b.booking_code, b.hunt_id, b.event_id, b.customer_name, b.payment_status, b.total_price
        FROM {$bookings_table} b
        LEFT JOIN {$events_table} e ON b.event_id = e.id
        WHERE e.id IS NULL AND b.payment_status = 'paid'
        ORDER BY b.created_at DESC
    ");
    
    if ($orphaned) {
        echo "🚨 CRITICAL: Found PAID bookings without matching quests:\n";
        foreach ($orphaned as $booking) {
            echo "   🚨 {$booking->booking_code} - {$booking->customer_name} (\${$booking->total_price})\n";
            echo "      Hunt ID: '{$booking->hunt_id}' | Event ID: {$booking->event_id} (MISSING)\n\n";
        }
    } else {
        echo "✅ No orphaned paid bookings found\n\n";
    }
    
    // 4. Check unified view
    echo "4️⃣ Checking Unified Bookings View:\n";
    echo "==================================\n";
    
    $view_exists = $wpdb->get_var("SHOW TABLES LIKE '{$unified_view}'");
    if ($view_exists) {
        echo "✅ Unified view exists: {$unified_view}\n";
        
        // Check for any Southport-related bookings in unified view
        $unified_data = $wpdb->get_results("
            SELECT booking_code, hunt_id, hunt_code, event_title, customer_name, payment_status
            FROM {$unified_view} 
            WHERE event_title LIKE '%Southport%' OR event_title LIKE '%Rockpool%'
               OR hunt_id LIKE '%SRP%' OR hunt_id LIKE '%SOUTH%' OR hunt_id LIKE '%ROCK%'
            ORDER BY created_at DESC
        ");
        
        if ($unified_data) {
            echo "✅ Found Southport bookings in unified view:\n";
            foreach ($unified_data as $row) {
                echo "   - {$row->booking_code}: {$row->event_title}\n";
                echo "     Hunt ID: '{$row->hunt_id}' | Hunt Code: '{$row->hunt_code}' | Status: {$row->payment_status}\n\n";
            }
        } else {
            echo "❌ No Southport bookings found in unified view\n";
        }
    } else {
        echo "❌ Unified view does not exist: {$unified_view}\n";
    }
    
    // 5. Show all quests for reference
    echo "5️⃣ All Available Quests (for reference):\n";
    echo "========================================\n";
    
    $all_quests = $wpdb->get_results("SELECT id, title, hunt_code, hunt_name, location, seats FROM {$events_table} ORDER BY id");
    
    if ($all_quests) {
        foreach ($all_quests as $quest) {
            $status = ($quest->seats > 0) ? '✅' : '❌';
            echo "   {$status} ID {$quest->id}: '{$quest->hunt_code}' - {$quest->title}\n";
            echo "      Hunt Name: '{$quest->hunt_name}' | Location: '{$quest->location}'\n\n";
        }
    } else {
        echo "❌ No quests found in database!\n";
    }
    
    // 6. Check for clues
    echo "6️⃣ Checking for Southport Clues:\n";
    echo "================================\n";
    
    if ($wpdb->get_var("SHOW TABLES LIKE '{$clues_table}'")) {
        $clue_hunts = $wpdb->get_results("
            SELECT DISTINCT hunt_id, COUNT(*) as clue_count
            FROM {$clues_table} 
            WHERE hunt_id IN (SELECT id FROM {$events_table} WHERE 
                title LIKE '%Southport%' OR title LIKE '%Rockpool%' OR
                hunt_name LIKE '%Southport%' OR hunt_name LIKE '%Rockpool%' OR
                location LIKE '%Southport%' OR location LIKE '%Rockpool%')
            GROUP BY hunt_id
        ");
        
        if ($clue_hunts) {
            echo "✅ Found clues for Southport quests:\n";
            foreach ($clue_hunts as $hunt) {
                echo "   - Hunt ID {$hunt->hunt_id}: {$hunt->clue_count} clues\n";
            }
        } else {
            echo "❌ No clues found for Southport quests\n";
        }
    } else {
        echo "❌ Clues table does not exist: {$clues_table}\n";
    }
    
    echo "\n💡 RECOMMENDATIONS:\n";
    echo "==================\n";
    
    if (empty($title_search) && empty($hunt_name_search) && empty($location_search)) {
        echo "🚨 CRITICAL ISSUE: No Southport Rockpool quest found in database!\n\n";
        echo "IMMEDIATE ACTIONS NEEDED:\n";
        echo "1. 🔍 Check if quest was accidentally deleted\n";
        echo "2. 🔄 Restore quest from backup if deleted\n";
        echo "3. 🆕 Create new Southport Rockpool quest if needed\n";
        echo "4. 💰 Process refund for customer if quest cannot be restored\n\n";
        
        echo "🎯 QUICK FIX - Create Missing Quest:\n";
        echo "INSERT INTO {$events_table} (title, hunt_code, hunt_name, location, price, seats, hosting_type, display_on_site, created_at) VALUES\n";
        echo "('Southport Rockpool Adventure', 'SRP456', 'Southport Rockpool Quest', 'Southport', 35.00, 50, 'self_hosted', 1, NOW());\n\n";
        
        echo "Then update existing bookings:\n";
        echo "UPDATE {$bookings_table} SET event_id = LAST_INSERT_ID(), hunt_id = 'SRP456' WHERE hunt_id LIKE '%SRP%';\n";
    } else {
        echo "✅ Quest exists - check Unified PuzzlePath app configuration\n";
        echo "🔍 Verify app can connect to your WordPress database\n";
        echo "🔍 Check hunt code matching logic in the app\n";
    }
}

// Run if called directly
if (php_sapi_name() === 'cli') {
    debug_southport_rockpool();
} else {
    // Web interface
    echo "<pre>";
    debug_southport_rockpool();
    echo "</pre>";
}
?>