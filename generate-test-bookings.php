<?php
/**
 * PuzzlePath Test Booking Generator
 * Creates test booking codes for all active quests in the system
 * 
 * Usage: Run this script from your WordPress root directory
 * php wp-content/plugins/puzzlepath-booking/generate-test-bookings.php
 * 
 * OR include in a WordPress admin page and call generate_test_bookings()
 */

// If running from command line, load WordPress
if (php_sapi_name() === 'cli') {
    // Adjust path as needed - this assumes script is in plugin folder
    require_once('../../../wp-config.php');
}

function generate_test_bookings($create_database_entries = true) {
    global $wpdb;
    
    // Get all active events/quests
    $events_table = $wpdb->prefix . 'pp_events';
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    
    $quests = $wpdb->get_results("
        SELECT id, title, hunt_code, hunt_name, location, price, hosting_type, event_date 
        FROM {$events_table} 
        WHERE seats > 0 
        ORDER BY id ASC
    ");
    
    if (empty($quests)) {
        echo "❌ No active quests found in the database.\n";
        return;
    }
    
    echo "🎯 Found " . count($quests) . " active quest(s):\n";
    foreach ($quests as $quest) {
        echo "   - {$quest->title} (Code: {$quest->hunt_code})\n";
    }
    echo "\n";
    
    $test_bookings = [];
    $today = new DateTime();
    $booking_counter = 9001;
    
    // Test customer profiles
    $test_customers = [
        [
            'name' => 'Alice Johnson',
            'email' => 'alice.test@puzzlepath.com.au',
            'participants' => 'Alice Johnson, Bob Smith'
        ],
        [
            'name' => 'Charlie Brown',
            'email' => 'charlie.test@puzzlepath.com.au', 
            'participants' => 'Charlie Brown, Diana White, Emma Davis'
        ],
        [
            'name' => 'Frank Wilson',
            'email' => 'frank.test@puzzlepath.com.au',
            'participants' => 'Frank Wilson, Grace Lee'
        ]
    ];
    
    foreach ($quests as $quest) {
        $hunt_display = $quest->hunt_name ?: $quest->title;
        echo "🎮 Generating test bookings for: {$quest->title}\n";
        echo "   📍 Location: {$quest->location} | Hunt Code: {$quest->hunt_code} | Hunt Name: {$hunt_display}\n\n";
        
        for ($i = 0; $i < 3; $i++) {
            $customer = $test_customers[$i];
            $participant_count = count(explode(', ', $customer['participants']));
            
            // Generate booking date (7, 14, 21 days from today)
            $booking_date = clone $today;
            $booking_date->add(new DateInterval('P' . (7 * ($i + 1)) . 'D'));
            
            // Generate booking code: HUNTCODE-YYYYMMDD-####
            $booking_code = strtoupper($quest->hunt_code) . '-' . 
                          $booking_date->format('Ymd') . '-' . 
                          str_pad($booking_counter++, 4, '0', STR_PAD_LEFT);
            
            $test_booking = [
                'quest_id' => $quest->id,
                'quest_title' => $quest->title,
                'hunt_code' => $quest->hunt_code,
                'hunt_name' => $hunt_display,
                'booking_code' => $booking_code,
                'customer_name' => $customer['name'],
                'customer_email' => $customer['email'],
                'participant_names' => $customer['participants'],
                'participant_count' => $participant_count,
                'booking_date' => $booking_date->format('Y-m-d'),
                'event_date' => $quest->event_date,
                'total_price' => $quest->price * $participant_count,
                'price_per_person' => $quest->price,
                'location' => $quest->location,
                'hosting_type' => $quest->hosting_type
            ];
            
            $test_bookings[] = $test_booking;
            
            echo "   ✅ {$booking_code} - {$customer['name']} ({$participant_count} people) → {$hunt_display}\n";
        }
        echo "\n";
    }
    
    // Create database entries if requested
    if ($create_database_entries) {
        echo "💾 Creating database entries...\n";
        
        $inserted_count = 0;
        $failed_count = 0;
        
        foreach ($test_bookings as $booking) {
            $db_data = [
                'event_id' => $booking['quest_id'],
                'hunt_id' => $booking['hunt_code'],
                'customer_name' => $booking['customer_name'],
                'customer_email' => $booking['customer_email'],
                'participant_names' => $booking['participant_names'],
                'tickets' => $booking['participant_count'],
                'total_price' => $booking['total_price'],
                'payment_status' => 'paid',
                'booking_code' => $booking['booking_code'],
                'booking_date' => $booking['booking_date'],
                'stripe_payment_intent_id' => 'pi_test_' . uniqid(),
                'created_at' => current_time('mysql')
            ];
            
            // Check if booking code already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$bookings_table} WHERE booking_code = %s",
                $booking['booking_code']
            ));
            
            if ($exists) {
                echo "   ⚠️  {$booking['booking_code']} already exists, skipping\n";
                continue;
            }
            
            $result = $wpdb->insert($bookings_table, $db_data);
            
            if ($result !== false) {
                $inserted_count++;
                echo "   ✅ {$booking['booking_code']} created in database\n";
            } else {
                $failed_count++;
                echo "   ❌ Failed to create {$booking['booking_code']}: {$wpdb->last_error}\n";
            }
        }
        
        echo "\n📊 Summary:\n";
        echo "   - Generated: " . count($test_bookings) . " test bookings\n";
        echo "   - Inserted: {$inserted_count} new database records\n";
        echo "   - Failed: {$failed_count} database insertions\n";
    }
    
    // Generate summary files
    generate_booking_summary_files($test_bookings);
    generate_cleanup_script($test_bookings);
    
    return $test_bookings;
}

function generate_booking_summary_files($test_bookings) {
    $plugin_dir = dirname(__FILE__);
    
    // Generate CSV file
    $csv_content = "Booking Code,Quest Title,Hunt Code,Hunt Name,Customer Name,Email,Participants,Count,Booking Date,Price,Location\n";
    foreach ($test_bookings as $booking) {
        $csv_content .= sprintf(
            "%s,%s,%s,\"%s\",%s,%s,\"%s\",%d,%s,%.2f,%s\n",
            $booking['booking_code'],
            $booking['quest_title'], 
            $booking['hunt_code'],
            $booking['hunt_name'],
            $booking['customer_name'],
            $booking['customer_email'],
            $booking['participant_names'],
            $booking['participant_count'],
            $booking['booking_date'],
            $booking['total_price'],
            $booking['location']
        );
    }
    
    file_put_contents($plugin_dir . '/test-bookings.csv', $csv_content);
    echo "📄 CSV file created: test-bookings.csv\n";
    
    // Generate JSON file
    $json_content = json_encode($test_bookings, JSON_PRETTY_PRINT);
    file_put_contents($plugin_dir . '/test-bookings.json', $json_content);
    echo "📄 JSON file created: test-bookings.json\n";
    
    // Generate markdown documentation
    $md_content = "# PuzzlePath Test Booking Codes\n\n";
    $md_content .= "Generated on: " . date('Y-m-d H:i:s') . "\n\n";
    
    $current_quest = '';
    foreach ($test_bookings as $booking) {
        if ($current_quest !== $booking['quest_title']) {
            $current_quest = $booking['quest_title'];
            $md_content .= "## {$current_quest}\n";
            $md_content .= "**Hunt Name:** {$booking['hunt_name']}  \n";
            $md_content .= "**Location:** {$booking['location']}  \n";
            $md_content .= "**Hunt Code:** {$booking['hunt_code']}  \n";
            $md_content .= "**Price per Person:** \${$booking['price_per_person']}\n\n";
        }
        
        $md_content .= "### {$booking['booking_code']} → {$booking['hunt_name']}\n";
        $md_content .= "- **Customer:** {$booking['customer_name']}\n";
        $md_content .= "- **Email:** {$booking['customer_email']}\n"; 
        $md_content .= "- **Participants:** {$booking['participant_names']}\n";
        $md_content .= "- **Party Size:** {$booking['participant_count']} people\n";
        $md_content .= "- **Booking Date:** {$booking['booking_date']}\n";
        $md_content .= "- **Total Price:** \${$booking['total_price']}\n";
        $md_content .= "- **Status:** PAID ✅\n\n";
    }
    
    $md_content .= "## Testing Instructions\n\n";
    $md_content .= "1. **Open the Unified PuzzlePath App:** https://app.puzzlepath.com.au\n";
    $md_content .= "2. **Enter any booking code** from the list above\n";
    $md_content .= "3. **Verify the quest loads** with correct details\n";
    $md_content .= "4. **Test the complete quest flow** including:\n";
    $md_content .= "   - Clue progression\n";
    $md_content .= "   - Answer validation\n";
    $md_content .= "   - Hint system\n";
    $md_content .= "   - Completion ceremony\n\n";
    $md_content .= "## Cleanup\n\n";
    $md_content .= "When testing is complete, run the cleanup script:\n";
    $md_content .= "```bash\n";
    $md_content .= "php cleanup-test-bookings.php\n";
    $md_content .= "```\n";
    
    file_put_contents($plugin_dir . '/TEST-BOOKINGS.md', $md_content);
    echo "📄 Documentation created: TEST-BOOKINGS.md\n";
}

function generate_cleanup_script($test_bookings) {
    $plugin_dir = dirname(__FILE__);
    
    $cleanup_content = "<?php\n";
    $cleanup_content .= "/**\n";
    $cleanup_content .= " * Cleanup script for PuzzlePath test bookings\n";
    $cleanup_content .= " * Generated on: " . date('Y-m-d H:i:s') . "\n";
    $cleanup_content .= " */\n\n";
    $cleanup_content .= "// Load WordPress if running from command line\n";
    $cleanup_content .= "if (php_sapi_name() === 'cli') {\n";
    $cleanup_content .= "    require_once('../../../wp-config.php');\n";
    $cleanup_content .= "}\n\n";
    $cleanup_content .= "function cleanup_test_bookings() {\n";
    $cleanup_content .= "    global \$wpdb;\n";
    $cleanup_content .= "    \$bookings_table = \$wpdb->prefix . 'pp_bookings';\n";
    $cleanup_content .= "    \n";
    $cleanup_content .= "    \$test_booking_codes = [\n";
    
    foreach ($test_bookings as $booking) {
        $cleanup_content .= "        '{$booking['booking_code']}',\n";
    }
    
    $cleanup_content .= "    ];\n";
    $cleanup_content .= "    \n";
    $cleanup_content .= "    echo \"🧹 Cleaning up test bookings...\\n\";\n";
    $cleanup_content .= "    \n";
    $cleanup_content .= "    \$deleted_count = 0;\n";
    $cleanup_content .= "    foreach (\$test_booking_codes as \$code) {\n";
    $cleanup_content .= "        \$result = \$wpdb->delete(\$bookings_table, ['booking_code' => \$code]);\n";
    $cleanup_content .= "        if (\$result !== false) {\n";
    $cleanup_content .= "            \$deleted_count++;\n";
    $cleanup_content .= "            echo \"   ✅ Deleted: \$code\\n\";\n";
    $cleanup_content .= "        }\n";
    $cleanup_content .= "    }\n";
    $cleanup_content .= "    \n";
    $cleanup_content .= "    echo \"\\n📊 Cleanup Summary:\\n\";\n";
    $cleanup_content .= "    echo \"   - Processed: \" . count(\$test_booking_codes) . \" booking codes\\n\";\n";
    $cleanup_content .= "    echo \"   - Deleted: \$deleted_count database records\\n\";\n";
    $cleanup_content .= "}\n\n";
    $cleanup_content .= "// Run cleanup if called directly\n";
    $cleanup_content .= "if (php_sapi_name() === 'cli') {\n";
    $cleanup_content .= "    cleanup_test_bookings();\n";
    $cleanup_content .= "}\n";
    
    file_put_contents($plugin_dir . '/cleanup-test-bookings.php', $cleanup_content);
    echo "🧹 Cleanup script created: cleanup-test-bookings.php\n";
}

// If running from command line, execute the function
if (php_sapi_name() === 'cli') {
    echo "🚀 PuzzlePath Test Booking Generator\n";
    echo "=====================================\n\n";
    
    generate_test_bookings(true);
    
    echo "\n✨ Test booking generation complete!\n";
    echo "\nNext steps:\n";
    echo "1. Check the generated files:\n";
    echo "   - test-bookings.csv (spreadsheet import)\n";
    echo "   - test-bookings.json (API/development)\n";
    echo "   - TEST-BOOKINGS.md (documentation)\n";
    echo "2. Test the booking codes in the Unified PuzzlePath app\n";
    echo "3. When done testing, run: php cleanup-test-bookings.php\n";
}
?>