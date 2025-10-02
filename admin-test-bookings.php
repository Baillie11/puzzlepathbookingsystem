<?php
/**
 * Test Bookings Admin Page for PuzzlePath
 * Add this to your admin menu system in puzzlepath-booking.php
 */

/**
 * Display the Test Bookings admin page
 */
function puzzlepath_test_bookings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    $message = '';
    $message_type = '';
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_test_bookings'])) {
        if (!wp_verify_nonce($_POST['test_bookings_nonce'], 'generate_test_bookings')) {
            wp_die('Security check failed.');
        }
        
        $create_database_entries = isset($_POST['create_database_entries']);
        $test_bookings = generate_test_bookings($create_database_entries);
        
        if (!empty($test_bookings)) {
            $message = 'Successfully generated ' . count($test_bookings) . ' test booking codes!';
            $message_type = 'success';
        } else {
            $message = 'No test bookings were generated. Check if you have active quests with seats available.';
            $message_type = 'error';
        }
    }
    
    // Handle cleanup
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup_test_bookings'])) {
        if (!wp_verify_nonce($_POST['cleanup_nonce'], 'cleanup_test_bookings')) {
            wp_die('Security check failed.');
        }
        
        $deleted_count = cleanup_existing_test_bookings();
        $message = "Cleaned up {$deleted_count} test booking records.";
        $message_type = 'success';
    }
    
    // Get current quests
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    $quests = $wpdb->get_results("
        SELECT id, title, hunt_code, hunt_name, location, price, seats 
        FROM {$events_table} 
        WHERE seats > 0 
        ORDER BY id ASC
    ");
    
    // Check for existing test bookings with hunt information
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    $existing_test_bookings = $wpdb->get_results("
        SELECT b.booking_code, b.customer_name, b.payment_status, b.hunt_id,
               e.hunt_name, e.title as quest_title
        FROM {$bookings_table} b
        LEFT JOIN {$events_table} e ON b.event_id = e.id
        WHERE b.customer_email LIKE '%@puzzlepath.com.au' 
           OR b.booking_code LIKE '%-9%'
        ORDER BY b.created_at DESC
    ");
    
    ?>
    <div class="wrap">
        <h1>🧪 Test Booking Generator</h1>
        <p>Generate test booking codes for all active quests to test the Unified PuzzlePath app.</p>
        
        <?php if ($message): ?>
            <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="postbox-container" style="width: 100%;">
            <div class="meta-box-sortables">
                
                <!-- Current Quests -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">🎯 Active Quests</h2>
                    </div>
                    <div class="inside">
                        <?php if (empty($quests)): ?>
                            <div class="notice notice-warning inline">
                                <p><strong>⚠️ No active quests found!</strong></p>
                                <p>Make sure you have quests with available seats (seats > 0) in your events table.</p>
                                <p><a href="<?php echo admin_url('admin.php?page=puzzlepath-events'); ?>">Manage Events</a></p>
                            </div>
                        <?php else: ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th>Quest Title</th>
                                        <th>Hunt Name</th>
                                        <th>Hunt Code</th>
                                        <th>Location</th>
                                        <th>Price</th>
                                        <th>Seats Available</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($quests as $quest): ?>
                                        <tr>
                                            <td><strong><?php echo esc_html($quest->title); ?></strong></td>
                                            <td>
                                                <?php if ($quest->hunt_name): ?>
                                                    <em><?php echo esc_html($quest->hunt_name); ?></em>
                                                <?php else: ?>
                                                    <em style="color: #999;">Same as title</em>
                                                <?php endif; ?>
                                            </td>
                                            <td><code><?php echo esc_html($quest->hunt_code); ?></code></td>
                                            <td><?php echo esc_html($quest->location); ?></td>
                                            <td>$<?php echo number_format($quest->price, 2); ?></td>
                                            <td><?php echo esc_html($quest->seats); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Generation Form -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">🚀 Generate Test Bookings</h2>
                    </div>
                    <div class="inside">
                        <?php if (!empty($quests)): ?>
                            <form method="post" action="">
                                <?php wp_nonce_field('generate_test_bookings', 'test_bookings_nonce'); ?>
                                
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Generation Options</th>
                                        <td>
                                            <p>This will create <strong><?php echo count($quests) * 3; ?> test bookings</strong> (3 per quest) with the following:</p>
                                            <ul>
                                                <li>✅ Realistic customer names and emails</li>
                                                <li>✅ Different party sizes (2-3 people)</li>
                                                <li>✅ Future booking dates (7, 14, 21 days ahead)</li>
                                                <li>✅ PAID status for immediate testing</li>
                                                <li>✅ Proper hunt code mapping</li>
                                            </ul>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Database Creation</th>
                                        <td>
                                            <label>
                                                <input type="checkbox" name="create_database_entries" value="1" checked>
                                                Create database entries (recommended for testing)
                                            </label>
                                            <p class="description">
                                                When checked, booking records will be added to your database with PAID status. 
                                                Uncheck to only generate booking codes without database entries.
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                                
                                <p class="submit">
                                    <input type="submit" name="generate_test_bookings" class="button-primary" 
                                           value="🎮 Generate Test Bookings">
                                </p>
                            </form>
                        <?php else: ?>
                            <div class="notice notice-info inline">
                                <p>Add some quests with available seats to generate test bookings.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Existing Test Bookings -->
                <?php if (!empty($existing_test_bookings)): ?>
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">🔍 Existing Test Bookings</h2>
                    </div>
                    <div class="inside">
                        <p>Found <strong><?php echo count($existing_test_bookings); ?></strong> existing test booking(s):</p>
                        
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Booking Code</th>
                                    <th>Quest / Hunt Name</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($existing_test_bookings as $booking): ?>
                                    <tr>
                                        <td><code><?php echo esc_html($booking->booking_code); ?></code></td>
                                        <td>
                                            <?php if ($booking->hunt_name): ?>
                                                <strong><?php echo esc_html($booking->hunt_name); ?></strong>
                                            <?php elseif ($booking->quest_title): ?>
                                                <strong><?php echo esc_html($booking->quest_title); ?></strong>
                                            <?php else: ?>
                                                <em>Hunt: <?php echo esc_html($booking->hunt_id ?: 'Unknown'); ?></em>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($booking->customer_name); ?></td>
                                        <td>
                                            <?php if ($booking->payment_status === 'paid'): ?>
                                                <span style="color: green;">✅ PAID</span>
                                            <?php else: ?>
                                                <span style="color: orange;">⏳ <?php echo strtoupper($booking->payment_status); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <form method="post" action="" style="margin-top: 15px;">
                            <?php wp_nonce_field('cleanup_test_bookings', 'cleanup_nonce'); ?>
                            <p>
                                <input type="submit" name="cleanup_test_bookings" class="button button-secondary" 
                                       value="🧹 Cleanup Test Bookings"
                                       onclick="return confirm('Are you sure you want to delete all test booking records?');">
                                <span class="description">This will permanently delete all test booking records.</span>
                            </p>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Usage Instructions -->
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">📖 Testing Instructions</h2>
                    </div>
                    <div class="inside">
                        <ol>
                            <li><strong>Generate test bookings</strong> using the form above</li>
                            <li><strong>Open your Unified PuzzlePath app:</strong> 
                                <a href="https://app.puzzlepath.com.au" target="_blank">app.puzzlepath.com.au</a>
                            </li>
                            <li><strong>Enter any generated booking code</strong> to start testing</li>
                            <li><strong>Test the complete quest flow:</strong>
                                <ul>
                                    <li>Booking verification</li>
                                    <li>Quest loading</li>
                                    <li>Clue progression</li>
                                    <li>Answer validation</li>
                                    <li>Hint system</li>
                                    <li>Completion ceremony</li>
                                </ul>
                            </li>
                            <li><strong>Clean up</strong> when testing is complete</li>
                        </ol>
                        
                        <div class="notice notice-info inline">
                            <p><strong>💡 Pro Tip:</strong> Test bookings use the format <code>HUNTCODE-YYYYMMDD-9XXX</code> 
                               where the "9" in the sequence number makes them easy to identify and clean up.</p>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <style>
    .postbox-container {
        max-width: none;
    }
    .postbox {
        margin-bottom: 20px;
    }
    .wp-list-table th, .wp-list-table td {
        padding: 8px 10px;
    }
    code {
        background: #f1f1f1;
        padding: 2px 4px;
        border-radius: 3px;
        font-family: 'Courier New', monospace;
    }
    </style>
    <?php
}

/**
 * Cleanup existing test bookings
 */
function cleanup_existing_test_bookings() {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    
    // Delete test bookings (identified by test email domain or booking code pattern)
    $result = $wpdb->query("
        DELETE FROM {$bookings_table} 
        WHERE customer_email LIKE '%@puzzlepath.com.au' 
           OR booking_code LIKE '%-9%'
    ");
    
    return $result !== false ? $result : 0;
}

/**
 * Load the test booking generator functions
 * Include this in your main plugin file
 */
if (!function_exists('generate_test_bookings')) {
    require_once(dirname(__FILE__) . '/generate-test-bookings.php');
}
?>