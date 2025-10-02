<?php
/**
 * Quick fix for SRP456 hunt code issue
 * This will help resolve the "Hunt not available" error
 */

// Load WordPress if running from command line
if (php_sapi_name() === 'cli') {
    require_once('../../../wp-config.php');
}

function fix_spr456_hunt_code() {
    global $wpdb;
    
    echo "🔧 PuzzlePath SRP456 Hunt Code Fix\n";
    echo "==================================\n\n";
    
    $events_table = $wpdb->prefix . 'pp_events';
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    
    // Check what quests exist
    $existing_quests = $wpdb->get_results("SELECT id, title, hunt_code, location, seats FROM {$events_table} ORDER BY id");
    
    if (empty($existing_quests)) {
        echo "❌ No quests found in database. Please create quests first.\n";
        return;
    }
    
    echo "📋 Available Quests:\n";
    foreach ($existing_quests as $i => $quest) {
        echo "   " . ($i + 1) . ". ID {$quest->id}: '{$quest->hunt_code}' - {$quest->title} ({$quest->location})\n";
    }
    
    // Option 1: Update an existing quest to use SRP456
    echo "\n🎯 Fix Option 1: Update Existing Quest\n";
    echo "=====================================\n";
    
    if (php_sapi_name() === 'cli') {
        echo "Which quest should use hunt code 'SRP456'? Enter the number (1-" . count($existing_quests) . "): ";
        $handle = fopen("php://stdin", "r");
        $choice = (int)trim(fgets($handle));
        fclose($handle);
        
        if ($choice >= 1 && $choice <= count($existing_quests)) {
            $selected_quest = $existing_quests[$choice - 1];
            
            echo "\n🔄 Updating quest '{$selected_quest->title}' to use hunt code 'SRP456'...\n";
            
            // Update the quest
            $result1 = $wpdb->update(
                $events_table,
                ['hunt_code' => 'SRP456'],
                ['id' => $selected_quest->id]
            );
            
            // Update any existing bookings
            $result2 = $wpdb->query($wpdb->prepare("
                UPDATE {$bookings_table} 
                SET hunt_id = 'SRP456' 
                WHERE event_id = %d
            ", $selected_quest->id));
            
            if ($result1 !== false) {
                echo "✅ Updated quest hunt code to 'SRP456'\n";
                echo "✅ Updated {$result2} booking records\n";
                
                echo "\n🎮 Test these booking codes now:\n";
                echo "   - SRP456-20251023-9033\n";
                echo "   - SRP456-20251016-9032\n";
                echo "   - SRP456-20251009-9031\n";
                
            } else {
                echo "❌ Failed to update quest\n";
            }
        } else {
            echo "❌ Invalid choice\n";
        }
    } else {
        // Web interface - just show SQL commands
        echo "Run one of these SQL commands to fix the issue:\n\n";
        
        foreach ($existing_quests as $i => $quest) {
            echo "Option " . ($i + 1) . " - Update '{$quest->title}':\n";
            echo "UPDATE {$events_table} SET hunt_code = 'SRP456' WHERE id = {$quest->id};\n";
            echo "UPDATE {$bookings_table} SET hunt_id = 'SRP456' WHERE event_id = {$quest->id};\n\n";
        }
    }
    
    // Option 2: Create new quest
    echo "\n🆕 Fix Option 2: Create New Quest with SRP456\n";
    echo "=============================================\n";
    echo "Run this SQL to create a new quest:\n\n";
    
    echo "INSERT INTO {$events_table} (\n";
    echo "    title, hunt_code, hunt_name, location, price, seats, \n";
    echo "    hosting_type, display_on_site, created_at\n";
    echo ") VALUES (\n";
    echo "    'SRP Test Quest',\n";
    echo "    'SRP456',\n";
    echo "    'SRP Test Quest - Test Location',\n";
    echo "    'Test Location',\n";
    echo "    35.00,\n";
    echo "    50,\n";
    echo "    'self_hosted',\n";
    echo "    1,\n";
    echo "    NOW()\n";
    echo ");\n\n";
    
    // Option 3: Regenerate test bookings
    echo "🔄 Fix Option 3: Regenerate Test Bookings\n";
    echo "=========================================\n";
    echo "Use the Test Booking Generator with your actual quest hunt codes:\n";
    echo "1. Go to: PuzzlePath → Test Bookings\n";
    echo "2. Click 'Generate Test Bookings' to create codes with correct hunt codes\n";
    echo "3. Use the newly generated codes instead\n";
    
    echo "\n💡 Recommendation:\n";
    echo "==================\n";
    echo "The easiest fix is Option 1 - update an existing quest to use 'SRP456'\n";
    echo "This will make your test booking codes work immediately.\n";
}

// Run if called directly
if (php_sapi_name() === 'cli') {
    fix_spr456_hunt_code();
} else {
    // Web interface
    echo "<pre>";
    fix_spr456_hunt_code();
    echo "</pre>";
}
?>