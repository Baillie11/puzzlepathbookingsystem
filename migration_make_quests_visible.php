<?php
/**
 * Migration script to make existing quests visible by default
 * Run this once to update all existing quests to use the new simplified visibility system
 * 
 * Upload this to your WordPress site and run it once
 */

// WordPress Bootstrap
$wp_load_path = '../../../wp-load.php';
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    die('Could not find wp-load.php. Please adjust the path.');
}

// Security check - only allow admin users
if (!current_user_can('manage_options')) {
    die('Access denied. Only admin users can run this script.');
}

echo "<h1>PuzzlePath Quest Visibility Migration</h1>\n";

global $wpdb;

// Check current status
$total_quests = $wpdb->get_var("SELECT COUNT(*) FROM wp2s_pp_events");
$visible_quests = $wpdb->get_var("SELECT COUNT(*) FROM wp2s_pp_events WHERE display_on_site = 1");
$hidden_quests = $wpdb->get_var("SELECT COUNT(*) FROM wp2s_pp_events WHERE display_on_site = 0");
$null_quests = $wpdb->get_var("SELECT COUNT(*) FROM wp2s_pp_events WHERE display_on_site IS NULL");

echo "<h2>Current Status</h2>\n";
echo "<ul>";
echo "<li>Total quests: <strong>$total_quests</strong></li>";
echo "<li>Visible quests (display_on_site = 1): <strong>$visible_quests</strong></li>";
echo "<li>Hidden quests (display_on_site = 0): <strong>$hidden_quests</strong></li>";
echo "<li>NULL status quests: <strong>$null_quests</strong></li>";
echo "</ul>";

// Apply migration if requested
if (isset($_GET['run_migration']) && $_GET['run_migration'] === 'yes') {
    echo "<h2>Running Migration...</h2>\n";
    
    // Update quests with seats > 0 to be visible
    $result = $wpdb->query("UPDATE wp2s_pp_events SET display_on_site = 1 WHERE seats > 0 AND (display_on_site = 0 OR display_on_site IS NULL)");
    
    if ($result !== false) {
        echo "<p style='color: green;'>✅ Successfully updated <strong>$result</strong> quest(s) to be visible!</p>";
        
        // Show updated status
        $new_visible_quests = $wpdb->get_var("SELECT COUNT(*) FROM wp2s_pp_events WHERE display_on_site = 1");
        echo "<p style='color: blue;'>🎉 Total visible quests: <strong>$new_visible_quests</strong></p>";
        
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>✅ Migration Completed!</h3>";
        echo "<p>Your quests should now be visible on your website. The shortcode <code>[puzzlepath_upcoming_adventures]</code> will show all quests where:</p>";
        echo "<ul><li><code>display_on_site = 1</code> (controlled by the Status toggle in admin)</li><li><code>seats > 0</code></li></ul>";
        echo "<p><strong>You can now delete this migration file: migration_make_quests_visible.php</strong></p>";
        echo "</div>";
        
    } else {
        echo "<p style='color: red;'>❌ Error during migration: " . $wpdb->last_error . "</p>";
    }
} else {
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>🔧 Ready to Run Migration</h3>";
    echo "<p>This migration will:</p>";
    echo "<ul>";
    echo "<li>Update all quests with <strong>seats > 0</strong> to be visible (<code>display_on_site = 1</code>)</li>";
    echo "<li>Leave quests with 0 seats as they are (typically hidden)</li>";
    echo "</ul>";
    echo "<p>This change makes the shortcode use the unified \"Status\" toggle in your admin instead of a separate adventures page setting.</p>";
    
    $quests_to_update = $wpdb->get_var("SELECT COUNT(*) FROM wp2s_pp_events WHERE seats > 0 AND (display_on_site = 0 OR display_on_site IS NULL)");
    echo "<p><strong>Quests that will be updated:</strong> $quests_to_update</p>";
    
    if ($quests_to_update > 0) {
        echo "<a href='?run_migration=yes' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;'>🚀 Run Migration</a>";
    } else {
        echo "<p style='color: green;'>✅ No migration needed - all quests are already properly configured!</p>";
    }
    echo "</div>";
}

echo "<h3>How the new system works:</h3>";
echo "<ul>";
echo "<li>Go to WordPress Admin → PuzzlePath → Quests</li>";
echo "<li>Use the <strong>Status</strong> toggle to show/hide quests on your website</li>";
echo "<li>The shortcode <code>[puzzlepath_upcoming_adventures]</code> shows quests where Status = Active AND Seats > 0</li>";
echo "</ul>";

echo "<p><em>Generated at " . date('Y-m-d H:i:s') . "</em></p>";
?>