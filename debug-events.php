<?php
/**
 * DEBUGGING VERSION of the events management code
 * Add this to see what's happening with form submissions
 */

// Add this to your functions.php or plugin file temporarily
function puzzlepath_debug_events_page() {
    global $wpdb;
    $table_name = 'wp2s_pp_events';

    echo '<div class="wrap">';
    echo '<h1>🔧 Debug Events Management</h1>';
    
    // Debug: Show POST data when form is submitted
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        echo '<div class="notice notice-info">';
        echo '<h3>📋 POST Data Received:</h3>';
        echo '<pre>' . print_r($_POST, true) . '</pre>';
        echo '</div>';
    }

    // Handle form submissions for adding/editing events
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['puzzlepath_event_nonce'])) {
        
        echo '<div class="notice notice-warning">';
        echo '<h3>🔒 Security Check...</h3>';
        
        if (!wp_verify_nonce($_POST['puzzlepath_event_nonce'], 'puzzlepath_save_event')) {
            echo '<p style="color: red;">❌ Security check FAILED!</p>';
            echo '</div>';
            return;
        } else {
            echo '<p style="color: green;">✅ Security check passed</p>';
        }
        echo '</div>';

        $id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $display_on_adventures_page = isset($_POST['display_on_adventures_page']) ? 1 : 0;
        
        echo '<div class="notice notice-info">';
        echo '<h3>🎯 Processing Quest ID: ' . $id . '</h3>';
        echo '<p><strong>Adventures Page Status:</strong> ' . ($display_on_adventures_page ? 'ENABLED' : 'DISABLED') . '</p>';
        echo '</div>';

        $data = [
            'title' => sanitize_text_field($_POST['title']),
            'location' => sanitize_text_field($_POST['location']),
            'price' => floatval($_POST['price']),
            'seats' => intval($_POST['seats']),
            'display_on_adventures_page' => $display_on_adventures_page,
        ];

        echo '<div class="notice notice-info">';
        echo '<h3>💾 Data to Save:</h3>';
        echo '<pre>' . print_r($data, true) . '</pre>';
        echo '</div>';

        if ($id > 0) {
            echo '<div class="notice notice-warning">';
            echo '<h3>🔄 Updating Existing Quest...</h3>';
            
            $result = $wpdb->update($table_name, $data, ['id' => $id]);
            
            if ($result !== false) {
                echo '<p style="color: green;">✅ Update successful! Rows affected: ' . $result . '</p>';
            } else {
                echo '<p style="color: red;">❌ Update failed! Error: ' . $wpdb->last_error . '</p>';
            }
            echo '</div>';
            
            // Check what's actually in the database now
            $updated_event = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
            if ($updated_event) {
                echo '<div class="notice notice-success">';
                echo '<h3>📊 Current Database Values:</h3>';
                echo '<p><strong>Title:</strong> ' . $updated_event->title . '</p>';
                echo '<p><strong>Adventures Page:</strong> ' . ($updated_event->display_on_adventures_page ? 'ENABLED' : 'DISABLED') . '</p>';
                echo '</div>';
            }
        }
    }

    // Show all events and their current status
    echo '<h2>📋 All Events Status</h2>';
    $events = $wpdb->get_results("SELECT id, title, seats, display_on_adventures_page FROM $table_name ORDER BY id ASC");
    
    if ($events) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Title</th><th>Seats</th><th>Adventures Page</th><th>Action</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($events as $event) {
            $status = $event->display_on_adventures_page ? 
                '<span style="color: green; font-weight: bold;">✅ ENABLED</span>' : 
                '<span style="color: red; font-weight: bold;">❌ DISABLED</span>';
            
            echo '<tr>';
            echo '<td>' . $event->id . '</td>';
            echo '<td><strong>' . esc_html($event->title) . '</strong></td>';
            echo '<td>' . $event->seats . '</td>';
            echo '<td>' . $status . '</td>';
            echo '<td><a href="admin.php?page=puzzlepath-events&action=edit&event_id=' . $event->id . '">Edit</a></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    // Quick fix buttons
    echo '<h2>⚡ Quick Fixes</h2>';
    
    if (isset($_GET['quick_fix']) && $_GET['quick_fix'] === 'enable_all') {
        echo '<div class="notice notice-success">';
        $result = $wpdb->query("UPDATE $table_name SET display_on_adventures_page = 1 WHERE seats > 0");
        echo '<h3>✅ Quick Fix Applied!</h3>';
        echo '<p>Enabled ' . $result . ' quests for the adventures page.</p>';
        echo '</div>';
    }
    
    echo '<div style="background: #f0f0f1; padding: 20px; border-radius: 5px;">';
    echo '<h3>🛠 Emergency Actions</h3>';
    echo '<p><a href="admin.php?page=puzzlepath-debug-events&quick_fix=enable_all" class="button button-primary" onclick="return confirm(\'Enable all quests with seats for adventures page?\')">Enable All Quests with Seats</a></p>';
    echo '</div>';

    echo '</div>';
}

// Add to admin menu
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'Debug Events',
        'Debug Events', 
        'manage_options',
        'puzzlepath-debug-events',
        'puzzlepath_debug_events_page'
    );
});
?>