<?php
/**
 * Quick fix script to enable display_on_adventures_page for existing quests
 * This will make your quests visible in the shortcode output
 * 
 * Upload this to your WordPress site and run it once to fix the issue
 */

// WordPress Bootstrap - adjust path as needed
$wp_load_path = '../../../wp-load.php';
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    die('Could not find wp-load.php. Please adjust the path in line 8.');
}

// Security check - only allow admin users to run this
if (!current_user_can('manage_options')) {
    die('Access denied. Only admin users can run this script.');
}

echo "<h1>PuzzlePath Quest Display Fix</h1>\n";

global $wpdb;

// Check current status
$total_quests = $wpdb->get_var("SELECT COUNT(*) FROM wp2s_pp_events");
$quests_with_seats = $wpdb->get_var("SELECT COUNT(*) FROM wp2s_pp_events WHERE seats > 0");
$quests_display_adventures = $wpdb->get_var("SELECT COUNT(*) FROM wp2s_pp_events WHERE display_on_site = 1");
$quests_both_conditions = $wpdb->get_var("SELECT COUNT(*) FROM wp2s_pp_events WHERE display_on_site = 1 AND seats > 0");

echo "<h2>Current Status</h2>\n";
echo "<ul>";
echo "<li>Total quests: <strong>$total_quests</strong></li>";
echo "<li>Quests with seats > 0: <strong>$quests_with_seats</strong></li>";
echo "<li>Quests with display_on_site = 1 (Status Active): <strong>$quests_display_adventures</strong></li>";
echo "<li>Quests visible on website (both conditions): <strong style='color: blue;'>$quests_both_conditions</strong></li>";
echo "</ul>";

if ($quests_both_conditions > 0) {
    echo "<p style='color: green;'>✅ Your quests are already configured correctly! The shortcode should be working.</p>";
    echo "<p>If you're still seeing a blank page, the issue might be elsewhere. Try:</p>";
    echo "<ul>";
    echo "<li>Check if the page contains the correct shortcode: <code>[puzzlepath_upcoming_adventures]</code></li>";
    echo "<li>Clear any caching plugins</li>";
    echo "<li>Check for PHP errors in your error logs</li>";
    echo "</ul>";
    exit;
}

// Apply the fix
if (isset($_GET['apply_fix']) && $_GET['apply_fix'] === 'yes') {
    echo "<h2>Applying Fix...</h2>\n";
    
    // Update all quests with seats > 0 to be visible on site
    $result = $wpdb->query("UPDATE wp2s_pp_events SET display_on_site = 1 WHERE seats > 0");
    
    if ($result !== false) {
        echo "<p style='color: green;'>✅ Successfully updated <strong>$result</strong> quest(s) to be visible on site!</p>";
        
        // Show updated status
        $new_visible_quests = $wpdb->get_var("SELECT COUNT(*) FROM wp2s_pp_events WHERE display_on_site = 1 AND seats > 0");
        echo "<p style='color: blue;'>🎉 Your website should now show <strong>$new_visible_quests</strong> quest(s)!</p>";
        
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>✅ Fix Applied Successfully!</h3>";
        echo "<p>Go check your website - the quests should now be visible where you have the <code>[puzzlepath_upcoming_adventures]</code> shortcode.</p>";
        echo "<p><strong>You can now delete both debug files:</strong></p>";
        echo "<ul><li>debug_quests.php</li><li>fix_quest_display.php</li></ul>";
        echo "</div>";
        
    } else {
        echo "<p style='color: red;'>❌ Error updating quests: " . $wpdb->last_error . "</p>";
    }
} else {
    // Show the fix button
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>🔧 Ready to Apply Fix</h3>";
    echo "<p>This will update all quests that have <strong>seats > 0</strong> to be visible on your website (Status = Active).</p>";
    echo "<p><strong>Quests that will be updated:</strong> $quests_with_seats</p>";
    echo "<a href='?apply_fix=yes' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>🚀 Apply Fix Now</a>";
    echo "</div>";
    
    echo "<h3>What this fix does:</h3>";
    echo "<p>It runs this SQL command: <code>UPDATE wp2s_pp_events SET display_on_site = 1 WHERE seats > 0;</code></p>";
    
    echo "<h3>Alternative Manual Fix:</h3>";
    echo "<p>Instead of using this script, you can:</p>";
    echo "<ol>";
    echo "<li>Go to WordPress Admin → PuzzlePath → Quests</li>";
    echo "<li>Edit each quest you want to display</li>";
    echo "<li>Toggle the Status to 'Active'</li>";
    echo "<li>Save the quest</li>";
    echo "</ol>";
}

echo "<p><em>Generated at " . date('Y-m-d H:i:s') . "</em></p>";
?>