<?php
/**
 * Temporary debug function - add this to your plugin file temporarily
 * Add this at the end of your main plugin file, just before the closing ?>
 */

// Add this to your WordPress admin menu temporarily
add_action('admin_menu', 'add_debug_adventures_menu');
function add_debug_adventures_menu() {
    add_submenu_page(
        'edit.php?post_type=events',
        'Debug Adventures',
        'Debug Adventures',
        'manage_options',
        'debug-adventures',
        'debug_adventures_page'
    );
}

function debug_adventures_page() {
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    
    echo '<div class="wrap">';
    echo '<h1>🔍 PuzzlePath Adventures Page Debug</h1>';
    
    // Test 1: Check if column exists
    echo '<h2>📊 Database Column Check</h2>';
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $events_table LIKE 'display_on_adventures_page'");
    
    if (empty($columns)) {
        echo '<div class="notice notice-error"><p><strong>❌ PROBLEM:</strong> The display_on_adventures_page column doesn\'t exist!</p></div>';
        echo '<p>Run this SQL: <code>ALTER TABLE ' . $events_table . ' ADD COLUMN display_on_adventures_page tinyint(1) DEFAULT 0 AFTER quest_description;</code></p>';
    } else {
        echo '<div class="notice notice-success"><p>✅ Column exists</p></div>';
    }
    
    // Test 2: Show all quests
    echo '<h2>🎯 All Quests Status</h2>';
    $quests = $wpdb->get_results("
        SELECT id, title, seats, display_on_adventures_page, quest_type, difficulty 
        FROM $events_table 
        ORDER BY id ASC
    ");
    
    if ($quests) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Title</th><th>Seats</th><th>Adventures Page</th><th>Type</th><th>Difficulty</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($quests as $quest) {
            $adventures_status = $quest->display_on_adventures_page ? 
                '<span style="color: green; font-weight: bold;">✅ VISIBLE</span>' : 
                '<span style="color: red; font-weight: bold;">❌ HIDDEN</span>';
            
            $seats_color = ($quest->seats > 0) ? 'green' : 'red';
            
            echo '<tr>';
            echo '<td>' . $quest->id . '</td>';
            echo '<td><strong>' . esc_html($quest->title) . '</strong></td>';
            echo '<td style="color: ' . $seats_color . ';">' . $quest->seats . '</td>';
            echo '<td>' . $adventures_status . '</td>';
            echo '<td>' . ucfirst($quest->quest_type ?: 'walking') . '</td>';
            echo '<td>' . ucfirst($quest->difficulty ?: 'easy') . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    // Test 3: Show what should appear
    echo '<h2>🚀 Quests Ready for Adventures Page</h2>';
    $visible_quests = $wpdb->get_results("
        SELECT * FROM $events_table 
        WHERE display_on_adventures_page = 1 AND seats > 0 
        ORDER BY title ASC
    ");
    
    echo '<p><strong>Quests that will show on adventures page:</strong> ' . count($visible_quests) . '</p>';
    
    if ($visible_quests) {
        echo '<div style="background: #d4edda; padding: 15px; margin: 10px 0;">';
        echo '<ul>';
        foreach ($visible_quests as $quest) {
            echo '<li>' . esc_html($quest->title) . ' - $' . $quest->price . ' (' . $quest->seats . ' seats)</li>';
        }
        echo '</ul>';
        echo '</div>';
    } else {
        echo '<div style="background: #f8d7da; padding: 15px; margin: 10px 0;">';
        echo '<p><strong>❌ No quests will appear because none have both:</strong></p>';
        echo '<ul><li>Display on Adventures Page = checked</li><li>Seats > 0</li></ul>';
        echo '</div>';
    }
    
    // Quick fix buttons
    echo '<h2>🛠 Quick Fix</h2>';
    echo '<p><strong>To enable all quests with seats:</strong></p>';
    echo '<form method="post">';
    echo '<input type="hidden" name="enable_all_adventures" value="1">';
    echo '<button type="submit" class="button button-primary" onclick="return confirm(\'Enable all quests with seats on adventures page?\')">Enable All Quests with Seats</button>';
    echo '</form>';
    
    // Handle quick fix
    if (isset($_POST['enable_all_adventures'])) {
        $result = $wpdb->query("UPDATE $events_table SET display_on_adventures_page = 1 WHERE seats > 0");
        echo '<div class="notice notice-success"><p>✅ Updated ' . $result . ' quests to show on adventures page!</p></div>';
        echo '<script>setTimeout(function(){ location.reload(); }, 1500);</script>';
    }
    
    echo '</div>';
}
?>