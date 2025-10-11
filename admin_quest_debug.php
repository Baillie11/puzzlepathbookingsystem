<?php
/**
 * WordPress Admin Debug Page for Quest Visibility
 * Add this as a temporary admin page to debug quest issues
 */

// Add this code to your functions.php file temporarily, or create a simple plugin

function puzzlepath_debug_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Access denied.');
    }
    
    global $wpdb;
    
    echo '<div class="wrap">';
    echo '<h1>PuzzlePath Quest Debug</h1>';
    
    // Get quest data
    $quests = $wpdb->get_results("SELECT id, title, location, seats, display_on_site, hosting_type FROM wp2s_pp_events ORDER BY id DESC");
    
    echo '<h2>All Quests in Database</h2>';
    if (empty($quests)) {
        echo '<p style="color: red;">❌ No quests found in database!</p>';
    } else {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Title</th><th>Location</th><th>Seats</th><th>Status</th><th>Hosting Type</th><th>Will Show on Site?</th></tr></thead>';
        echo '<tbody>';
        
        $visible_count = 0;
        foreach ($quests as $quest) {
            $status_text = $quest->display_on_site ? '✅ ACTIVE' : '❌ HIDDEN';
            $status_color = $quest->display_on_site ? 'green' : 'red';
            $will_show = ($quest->display_on_site && $quest->seats > 0) ? '✅ YES' : '❌ NO';
            $will_show_color = ($quest->display_on_site && $quest->seats > 0) ? 'green' : 'red';
            
            if ($quest->display_on_site && $quest->seats > 0) $visible_count++;
            
            echo '<tr>';
            echo '<td>' . $quest->id . '</td>';
            echo '<td><strong>' . esc_html($quest->title) . '</strong></td>';
            echo '<td>' . esc_html($quest->location) . '</td>';
            echo '<td>' . $quest->seats . '</td>';
            echo '<td style="color: ' . $status_color . '; font-weight: bold;">' . $status_text . '</td>';
            echo '<td>' . esc_html($quest->hosting_type) . '</td>';
            echo '<td style="color: ' . $will_show_color . '; font-weight: bold;">' . $will_show . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        echo '<h3>Summary</h3>';
        echo '<ul>';
        echo '<li>Total quests: <strong>' . count($quests) . '</strong></li>';
        echo '<li>Quests that will show on website: <strong style="color: blue;">' . $visible_count . '</strong></li>';
        echo '</ul>';
        
        if ($visible_count == 0) {
            echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;">';
            echo '<h3>🔧 Issue Found!</h3>';
            echo '<p>No quests meet both conditions (Status = Active AND Seats > 0).</p>';
            echo '<p><strong>Quick Fix SQL:</strong></p>';
            echo '<code>UPDATE wp2s_pp_events SET display_on_site = 1 WHERE seats > 0;</code>';
            echo '</div>';
        }
    }
    
    // Test the shortcode directly
    echo '<h2>Shortcode Test</h2>';
    $shortcode_output = do_shortcode('[puzzlepath_upcoming_adventures]');
    
    if (empty(trim(strip_tags($shortcode_output)))) {
        echo '<p style="color: red;">❌ Shortcode returned empty content!</p>';
    } else if (strpos($shortcode_output, 'No upcoming adventures') !== false) {
        echo '<p style="color: orange;">⚠️ Shortcode shows "no adventures" message</p>';
    } else {
        echo '<p style="color: green;">✅ Shortcode generated content!</p>';
    }
    
    echo '<h3>Raw Shortcode Output:</h3>';
    echo '<div style="border: 1px solid #ccc; padding: 10px; background: #f9f9f9; max-height: 200px; overflow-y: scroll;">';
    echo '<pre>' . esc_html($shortcode_output) . '</pre>';
    echo '</div>';
    
    echo '</div>';
}

// Add admin menu item (temporary)
function puzzlepath_add_debug_menu() {
    add_management_page(
        'PuzzlePath Quest Debug',
        'Quest Debug',
        'manage_options',
        'puzzlepath-quest-debug',
        'puzzlepath_debug_admin_page'
    );
}
add_action('admin_menu', 'puzzlepath_add_debug_menu');
?>