<?php
/**
 * Debug script to check quest data and shortcode functionality
 * Upload this to your WordPress site and run it to debug the shortcode issue
 */

// WordPress Bootstrap - try multiple common paths
$wp_paths = [
    './wp-load.php',           // If in WordPress root
    '../wp-load.php',          // If in subdirectory
    './wp-load.php',           // Current directory
    dirname(__FILE__) . '/wp-load.php'
];

$wp_loaded = false;
foreach ($wp_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('Could not find wp-load.php. Please place this file in your WordPress root directory (same folder as wp-config.php).');
}

echo "<h1>PuzzlePath Quest Debug</h1>\n";

// Check if plugin is active
if (!function_exists('puzzlepath_upcoming_adventures_shortcode')) {
    echo "<p style='color: red;'>❌ PuzzlePath plugin not loaded or shortcode function not found!</p>\n";
    exit;
}

echo "<p style='color: green;'>✅ Plugin loaded successfully</p>\n";

// Check database connection
global $wpdb;
echo "<h2>Database Connection Test</h2>\n";
$test_query = $wpdb->get_var("SELECT COUNT(*) FROM wp2s_pp_events");
if ($test_query !== null) {
    echo "<p style='color: green;'>✅ Database connection working. Found $test_query events in total</p>\n";
} else {
    echo "<p style='color: red;'>❌ Database connection failed: " . $wpdb->last_error . "</p>\n";
    exit;
}

// Check quest data
echo "<h2>Quest Data Analysis</h2>\n";
$all_quests = $wpdb->get_results("SELECT id, title, location, seats, display_on_site FROM wp2s_pp_events");

if (empty($all_quests)) {
    echo "<p style='color: red;'>❌ No quests found in database!</p>\n";
} else {
    echo "<p style='color: blue;'>📊 Total quests in database: " . count($all_quests) . "</p>\n";
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>ID</th><th>Title</th><th>Location</th><th>Seats</th><th>Status (display_on_site)</th></tr>\n";
    
    $display_on_adventures_count = 0;
    $seats_greater_than_zero = 0;
    $both_conditions = 0;
    
    foreach ($all_quests as $quest) {
        $display_site_text = $quest->display_on_site ? 'ACTIVE' : 'HIDDEN';
        $seats_text = $quest->seats > 0 ? $quest->seats : '0 (Hidden)';
        
        if ($quest->display_on_site) $display_on_adventures_count++;
        if ($quest->seats > 0) $seats_greater_than_zero++;
        if ($quest->display_on_site && $quest->seats > 0) $both_conditions++;
        
        $row_style = ($quest->display_on_site && $quest->seats > 0) ? 'background: #d4edda;' : 'background: #f8d7da;';
        
        echo "<tr style='$row_style'>";
        echo "<td>{$quest->id}</td>";
        echo "<td>" . esc_html($quest->title) . "</td>";
        echo "<td>" . esc_html($quest->location) . "</td>";
        echo "<td>$seats_text</td>";
        echo "<td style='font-weight: bold; color: " . ($quest->display_on_site ? 'green' : 'red') . ";'>$display_site_text</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    echo "<h3>Summary</h3>\n";
    echo "<ul>";
echo "<li>Quests with display_on_site = 1 (Status Active): <strong>$display_on_adventures_count</strong></li>";
    echo "<li>Quests with seats > 0: <strong>$seats_greater_than_zero</strong></li>";
    echo "<li>Quests meeting both conditions (what shortcode shows): <strong style='color: blue;'>$both_conditions</strong></li>";
    echo "</ul>";
}

// Test the shortcode directly
echo "<h2>Shortcode Output Test</h2>\n";
$shortcode_output = puzzlepath_upcoming_adventures_shortcode([]);

if (empty($shortcode_output) || trim($shortcode_output) === '') {
    echo "<p style='color: red;'>❌ Shortcode returned empty output!</p>\n";
} elseif (strpos($shortcode_output, 'No upcoming adventures') !== false) {
    echo "<p style='color: orange;'>⚠️ Shortcode returned 'no adventures' message</p>\n";
    echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>$shortcode_output</div>\n";
} else {
    echo "<p style='color: green;'>✅ Shortcode generated content successfully!</p>\n";
    echo "<h3>Shortcode HTML Output:</h3>\n";
    echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9; max-height: 300px; overflow-y: scroll;'>";
    echo "<pre>" . esc_html($shortcode_output) . "</pre>";
    echo "</div>\n";
}

// Quick fix suggestions
echo "<h2>Quick Fix Suggestions</h2>\n";

if ($both_conditions == 0 && count($all_quests) > 0) {
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px;'>";
    echo "<h3>🔧 Probable Fix Needed</h3>";
    echo "<p>Your quests exist but none have <code>display_on_site = 1</code>. To fix this:</p>";
    echo "<ol>";
    echo "<li>Go to your WordPress admin → PuzzlePath → Quests</li>";
    echo "<li>Use the Status toggle to make quests 'Active'</li>";
    echo "<li>Make sure 'Seats' is greater than 0</li>";
    echo "</ol>";
    echo "<p><strong>Or run this SQL to enable all quests:</strong></p>";
    echo "<code>UPDATE wp2s_pp_events SET display_on_site = 1 WHERE seats > 0;</code>";
    echo "<p><strong>Or use the migration script:</strong> <a href='migration_make_quests_visible.php'>migration_make_quests_visible.php</a></p>";
    echo "</div>";
} elseif (count($all_quests) == 0) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px;'>";
    echo "<h3>❌ No Quests Found</h3>";
    echo "<p>No quests exist in the database. You need to create some quests first in WordPress admin → PuzzlePath → Quests</p>";
    echo "</div>";
}

echo "<p><em>Debug completed at " . date('Y-m-d H:i:s') . "</em></p>";
?>