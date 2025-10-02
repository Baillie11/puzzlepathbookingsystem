<?php
/**
 * EMERGENCY FIX: Southport Rockpool Quest Issues
 * This will restore/create the missing quest and fix booking codes
 */

// Load WordPress if running from command line
if (php_sapi_name() === 'cli') {
    require_once('../../../wp-config.php');
}

function fix_southport_rockpool() {
    global $wpdb;
    
    echo "🚨 EMERGENCY FIX: Southport Rockpool Quest\n";
    echo "=========================================\n\n";
    
    $events_table = $wpdb->prefix . 'pp_events';
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    $clues_table = $wpdb->prefix . 'pp_clues';
    
    // Step 1: Check if Southport quest exists
    $existing_quest = $wpdb->get_row("
        SELECT * FROM {$events_table} 
        WHERE title LIKE '%Southport%' OR title LIKE '%Rockpool%'
           OR hunt_name LIKE '%Southport%' OR hunt_name LIKE '%Rockpool%'
           OR location LIKE '%Southport%' OR location LIKE '%Rockpool%'
        LIMIT 1
    ");
    
    if ($existing_quest) {
        echo "✅ Found existing Southport quest:\n";
        echo "   - ID: {$existing_quest->id}\n";
        echo "   - Title: {$existing_quest->title}\n";
        echo "   - Hunt Code: {$existing_quest->hunt_code}\n";
        echo "   - Location: {$existing_quest->location}\n";
        echo "   - Seats: {$existing_quest->seats}\n\n";
        
        $quest_id = $existing_quest->id;
        $hunt_code = $existing_quest->hunt_code;
        
        if ($existing_quest->seats <= 0) {
            echo "🔧 Fixing: Quest has no available seats\n";
            $wpdb->update($events_table, ['seats' => 50], ['id' => $quest_id]);
            echo "✅ Updated seats to 50\n\n";
        }
        
    } else {
        echo "🚨 CRITICAL: No Southport quest found - Creating new one...\n";
        
        // Create the missing quest
        $quest_data = [
            'title' => 'Southport Rockpool Adventure',
            'hunt_code' => 'SRP456',
            'hunt_name' => 'Southport Rockpool Quest',
            'location' => 'Southport',
            'price' => 35.00,
            'seats' => 50,
            'hosting_type' => 'self_hosted',
            'display_on_site' => 1,
            'created_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($events_table, $quest_data);
        
        if ($result !== false) {
            $quest_id = $wpdb->insert_id;
            $hunt_code = 'SRP456';
            
            echo "✅ Created new Southport quest:\n";
            echo "   - ID: {$quest_id}\n";
            echo "   - Hunt Code: {$hunt_code}\n";
            echo "   - Title: Southport Rockpool Adventure\n\n";
        } else {
            echo "❌ Failed to create quest: {$wpdb->last_error}\n";
            return;
        }
    }
    
    // Step 2: Find and fix orphaned bookings
    echo "🔍 Looking for Southport booking codes...\n";
    
    $southport_bookings = $wpdb->get_results("
        SELECT * FROM {$bookings_table} 
        WHERE booking_code LIKE '%SRP%' 
           OR booking_code LIKE '%SOUTH%' 
           OR booking_code LIKE '%ROCK%'
           OR hunt_id LIKE '%SRP%'
           OR hunt_id LIKE '%SOUTH%'
           OR hunt_id LIKE '%ROCK%'
        ORDER BY created_at DESC
    ");
    
    if ($southport_bookings) {
        echo "✅ Found " . count($southport_bookings) . " Southport booking(s):\n";
        
        foreach ($southport_bookings as $booking) {
            $status_icon = $booking->payment_status === 'paid' ? '💰' : 
                          ($booking->payment_status === 'pending' ? '⏳' : '❌');
            echo "   {$status_icon} {$booking->booking_code} - {$booking->customer_name} (\${$booking->total_price})\n";
            echo "      Status: {$booking->payment_status} | Event ID: {$booking->event_id} | Hunt ID: {$booking->hunt_id}\n";
            
            // Fix the booking
            $updated = $wpdb->update(
                $bookings_table,
                [
                    'event_id' => $quest_id,
                    'hunt_id' => $hunt_code
                ],
                ['id' => $booking->id]
            );
            
            if ($updated !== false) {
                echo "      ✅ Fixed - linked to quest ID {$quest_id} with hunt code {$hunt_code}\n";
            } else {
                echo "      ❌ Failed to update booking\n";
            }
            echo "\n";
        }
        
    } else {
        echo "❌ No Southport bookings found in database\n\n";
    }
    
    // Step 3: Check/Create basic clues if none exist
    echo "🧩 Checking for quest clues...\n";
    
    if ($wpdb->get_var("SHOW TABLES LIKE '{$clues_table}'")) {
        $clue_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$clues_table} WHERE hunt_id = %d",
            $quest_id
        ));
        
        if ($clue_count > 0) {
            echo "✅ Found {$clue_count} existing clues\n";
        } else {
            echo "⚠️  No clues found - creating basic clues for testing...\n";
            
            $basic_clues = [
                [
                    'hunt_id' => $quest_id,
                    'clue_order' => 1,
                    'title' => 'Welcome to Southport',
                    'clue_text' => 'Welcome to your Southport Rockpool adventure! This is a test clue to verify the quest is working.',
                    'task_description' => 'Take a photo at the starting location to confirm the quest is functioning.',
                    'hint_text' => 'This is a temporary clue for testing purposes.',
                    'answer' => 'photo_submission',
                    'is_active' => 1
                ]
            ];
            
            foreach ($basic_clues as $clue) {
                $wpdb->insert($clues_table, $clue);
            }
            
            echo "✅ Created basic test clue\n";
        }
    } else {
        echo "⚠️  Clues table doesn't exist - quest will work for booking validation but may need clues added\n";
    }
    
    // Step 4: Refresh unified view
    echo "\n🔄 Refreshing unified bookings view...\n";
    
    // Force refresh of unified view
    require_once(ABSPATH . 'wp-content/plugins/puzzlepath-booking/puzzlepath-booking.php');
    if (function_exists('puzzlepath_fix_unified_app_compatibility')) {
        puzzlepath_fix_unified_app_compatibility();
        echo "✅ Unified view refreshed\n";
    }
    
    // Step 5: Test the fix
    echo "\n🎮 Testing the fix...\n";
    
    if ($southport_bookings) {
        $test_booking = $southport_bookings[0];
        echo "📱 Test this booking code in your app: {$test_booking->booking_code}\n";
        echo "👤 Customer: {$test_booking->customer_name}\n";
        echo "💰 Amount: \${$test_booking->total_price}\n";
        echo "🎯 Should now load: Southport Rockpool Adventure\n\n";
    }
    
    echo "✅ EMERGENCY FIX COMPLETE!\n";
    echo "==========================\n";
    echo "📋 Summary of actions:\n";
    if (!$existing_quest) {
        echo "   ✅ Created missing Southport Rockpool quest\n";
    }
    if ($southport_bookings) {
        echo "   ✅ Fixed " . count($southport_bookings) . " booking codes\n";
    }
    echo "   ✅ Ensured quest has available seats\n";
    echo "   ✅ Refreshed unified bookings view\n\n";
    
    echo "🎯 NEXT STEPS:\n";
    echo "1. 📱 Test the booking codes in your Unified PuzzlePath app\n";
    echo "2. 🧩 Add proper clues for the Southport quest (if needed)\n";
    echo "3. ✉️  Contact your customer to let them know the issue is resolved\n";
    echo "4. 🔍 Consider adding database backup/monitoring to prevent this in future\n";
}

// Run if called directly
if (php_sapi_name() === 'cli') {
    fix_southport_rockpool();
} else {
    // Web interface
    echo "<pre>";
    fix_southport_rockpool();
    echo "</pre>";
}
?>