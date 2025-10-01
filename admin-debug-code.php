<?php
/**
 * TEMPORARY DEBUG CODE - Add this to the end of your puzzlepath-booking.php file
 * This will create a debug page in your WordPress admin
 */

// Add debug menu to WordPress admin
add_action('admin_menu', 'puzzlepath_add_debug_menu');
function puzzlepath_add_debug_menu() {
    add_submenu_page(
        'tools.php',
        'PuzzlePath Debug',
        'PuzzlePath Debug',
        'manage_options',
        'puzzlepath-debug',
        'puzzlepath_debug_page'
    );
}

function puzzlepath_debug_page() {
    global $wpdb;
    
    // Handle quick fix submission
    if (isset($_POST['enable_all_quests']) && wp_verify_nonce($_POST['debug_nonce'], 'puzzlepath_debug_action')) {
        $result = $wpdb->query("UPDATE wp2s_pp_events SET display_on_adventures_page = 1 WHERE seats > 0");
        echo '<div class="notice notice-success"><p>✅ Enabled ' . $result . ' quests for the adventures page!</p></div>';
    }
    
    echo '<div class="wrap">';
    echo '<h1>🔍 PuzzlePath Adventures Debug</h1>';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE 'wp2s_pp_events'");
    if (!$table_exists) {
        echo '<div class="notice notice-error"><p><strong>❌ ERROR:</strong> Table wp2s_pp_events does not exist!</p></div>';
        echo '</div>';
        return;
    }
    
    echo '<div class="notice notice-success"><p>✅ Table wp2s_pp_events exists</p></div>';
    
    // Check if column exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM wp2s_pp_events LIKE 'display_on_adventures_page'");
    if (empty($column_exists)) {
        echo '<div class="notice notice-error"><p><strong>❌ ERROR:</strong> Column display_on_adventures_page does not exist!</p></div>';
        echo '<p>You need to run: <code>ALTER TABLE wp2s_pp_events ADD COLUMN display_on_adventures_page tinyint(1) DEFAULT 0;</code></p>';
    } else {
        echo '<div class="notice notice-success"><p>✅ Column display_on_adventures_page exists</p></div>';
    }
    
    // Show all quests
    echo '<h2>🎯 All Quests Status</h2>';
    $quests = $wpdb->get_results("SELECT id, title, seats, display_on_adventures_page FROM wp2s_pp_events ORDER BY id ASC");
    
    if ($quests) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Title</th><th>Seats</th><th>Adventures Page</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($quests as $quest) {
            $status = $quest->display_on_adventures_page ? 
                '<span style="color: green; font-weight: bold;">✅ ENABLED</span>' : 
                '<span style="color: red; font-weight: bold;">❌ DISABLED</span>';
            
            $seats_color = ($quest->seats > 0) ? 'green' : 'red';
            
            echo '<tr>';
            echo '<td>' . $quest->id . '</td>';
            echo '<td><strong>' . esc_html($quest->title) . '</strong></td>';
            echo '<td style="color: ' . $seats_color . ';">' . $quest->seats . '</td>';
            echo '<td>' . $status . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        // Show count of enabled quests
        $enabled_count = $wpdb->get_var("SELECT COUNT(*) FROM wp2s_pp_events WHERE display_on_adventures_page = 1 AND seats > 0");
        echo '<p><strong>Quests that will show on adventures page:</strong> ' . $enabled_count . '</p>';
        
        if ($enabled_count == 0) {
            echo '<div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;">';
            echo '<h3>⚠️ No quests are enabled for the adventures page!</h3>';
            echo '<p>This is why you\'re seeing "No upcoming adventures available at this time."</p>';
            echo '</div>';
        }
    }
    
    // Quick fix button
    echo '<h2>🛠 Quick Fix</h2>';
    echo '<form method="post" style="background: #f0f0f1; padding: 20px; border-radius: 5px;">';
    echo '<p><strong>Enable all quests with seats for the adventures page:</strong></p>';
    wp_nonce_field('puzzlepath_debug_action', 'debug_nonce');
    echo '<button type="submit" name="enable_all_quests" class="button button-primary button-large" onclick="return confirm(\'Enable all quests with available seats?\')">Enable All Quests with Seats</button>';
    echo '</form>';
    
    echo '</div>';
}
?>