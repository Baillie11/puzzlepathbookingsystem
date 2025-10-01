<?php
/**
 * Debug script for adventures page quest visibility
 * Place this in your WordPress root and visit it to debug the issue
 * UPDATED with correct table name for your environment
 */

require_once('wp-config.php');

echo "<h1>🔍 PuzzlePath Adventures Page Debug</h1>";

global $wpdb;

// HARDCODED table name instead of using prefix
$events_table = 'wp2s_pp_events';

echo "<p><strong>Using table:</strong> {$events_table}</p>";

// Test 1: Check if display_on_adventures_page column exists and has data
echo "<h2>📊 Database Column Check</h2>";
$columns = $wpdb->get_results("SHOW COLUMNS FROM $events_table LIKE 'display_on_adventures_page'");

if (empty($columns)) {
    echo "<p style='color: red;'><strong>❌ PROBLEM FOUND:</strong> The 'display_on_adventures_page' column doesn't exist in your database!</p>";
    echo "<p>Run this SQL command in your database:</p>";
    echo "<code>ALTER TABLE {$events_table} ADD COLUMN display_on_adventures_page tinyint(1) DEFAULT 0 AFTER quest_description;</code>";
} else {
    echo "<p style='color: green;'>✅ Column 'display_on_adventures_page' exists.</p>";
}

// Test 2: Show current quest data with all relevant fields
echo "<h2>🎯 All Quests Data</h2>";
$quests = $wpdb->get_results("
    SELECT id, title, seats, display_on_adventures_page, quest_type, difficulty, quest_description 
    FROM $events_table 
    ORDER BY id ASC
");

if (empty($quests)) {
    echo "<p><em>No quests found in database.</em></p>";
} else {
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>ID</th><th>Title</th><th>Seats</th><th>Adventures Page</th><th>Quest Type</th><th>Difficulty</th><th>Description</th>";
    echo "</tr>";
    
    foreach ($quests as $quest) {
        $adventures_status = $quest->display_on_adventures_page ? 
            "<span style='color: green; font-weight: bold;'>✅ VISIBLE</span>" : 
            "<span style='color: red; font-weight: bold;'>❌ HIDDEN</span>";
        
        $seats_status = ($quest->seats > 0) ? 
            "<span style='color: green;'>{$quest->seats} seats</span>" : 
            "<span style='color: red;'>0 seats</span>";
            
        $description = $quest->quest_description ? 
            substr($quest->quest_description, 0, 30) . '...' : 
            '<em style="color: #999;">None</em>';
            
        echo "<tr>";
        echo "<td>{$quest->id}</td>";
        echo "<td><strong>{$quest->title}</strong></td>";
        echo "<td>{$seats_status}</td>";
        echo "<td>{$adventures_status}</td>";
        echo "<td>" . ucfirst($quest->quest_type ?: 'walking') . "</td>";
        echo "<td>" . ucfirst($quest->difficulty ?: 'easy') . "</td>";
        echo "<td>{$description}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 3: Show quests that SHOULD appear on adventures page
echo "<h2>🚀 Quests Ready for Adventures Page</h2>";
$adventures_quests = $wpdb->get_results("
    SELECT * FROM $events_table 
    WHERE display_on_adventures_page = 1 AND seats > 0 
    ORDER BY 
        CASE WHEN hosting_type = 'hosted' THEN 0 ELSE 1 END,
        event_date ASC,
        title ASC
");

echo "<p><strong>Quests that meet adventures page criteria:</strong> " . count($adventures_quests) . "</p>";

if (!empty($adventures_quests)) {
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<h3>✅ These quests will appear:</h3>";
    echo "<ul>";
    foreach ($adventures_quests as $quest) {
        $type_icon = ($quest->quest_type === 'driving') ? '🚗' : '🚶‍♂️';
        $date_info = $quest->event_date ? date('M j, Y', strtotime($quest->event_date)) : 'Anytime';
        echo "<li>{$type_icon} <strong>{$quest->title}</strong> - {$quest->difficulty} - \${$quest->price} - {$date_info}</li>";
    }
    echo "</ul>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3>❌ No quests will appear because:</h3>";
    echo "<ul>";
    echo "<li>No quests have 'Display on Adventures Page' checked AND have available seats</li>";
    echo "</ul>";
    echo "</div>";
}

// Test 4: Test the shortcode output
echo "<h2>🔧 Shortcode Test</h2>";
echo "<p><strong>Shortcode:</strong> <code>[puzzlepath_upcoming_adventures]</code></p>";

// Manually call the shortcode function
if (function_exists('puzzlepath_upcoming_adventures_shortcode')) {
    echo "<div style='border: 2px dashed #007cba; padding: 15px; background: #f7f7f7;'>";
    echo "<h4>Shortcode Output:</h4>";
    echo puzzlepath_upcoming_adventures_shortcode([]);
    echo "</div>";
} else {
    echo "<p style='color: red;'><strong>❌ PROBLEM:</strong> The shortcode function 'puzzlepath_upcoming_adventures_shortcode' is not loaded!</p>";
}

// Test 5: Quick Fix Instructions
echo "<h2>🛠 Quick Fix Steps</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
echo "<h3>To fix the 'No upcoming adventures' issue:</h3>";
echo "<ol>";
echo "<li><strong>Make sure quests have seats available</strong> (seats > 0)</li>";
echo "<li><strong>Edit each quest you want to show:</strong>";
echo "<ul>";
echo "<li>Go to PuzzlePath → Events</li>";
echo "<li>Click 'Edit' on a quest</li>";
echo "<li>Check ✅ 'Display on Adventures Page'</li>";
echo "<li>Fill in Quest Description (optional but recommended)</li>";
echo "<li>Set Quest Type (Walking/Driving)</li>";
echo "<li>Set Difficulty Level</li>";
echo "<li>Click 'Update Event'</li>";
echo "</ul></li>";
echo "<li><strong>Refresh your adventures page</strong></li>";
echo "</ol>";
echo "</div>";

// Test 6: Manual SQL update (if needed)
echo "<h2>⚡ Emergency SQL Fix</h2>";
echo "<div style='background: #e7f3ff; padding: 15px; border: 1px solid #b8daff; border-radius: 5px;'>";
echo "<p><strong>If you want to quickly enable ALL quests with seats on the adventures page, run this SQL:</strong></p>";
echo "<code style='background: #f1f1f1; padding: 10px; display: block; font-family: monospace;'>";
echo "UPDATE {$events_table} SET display_on_adventures_page = 1 WHERE seats > 0;";
echo "</code>";
echo "<p><em>⚠️ This will make ALL quests with available seats visible on the adventures page.</em></p>";
echo "</div>";

echo "<hr>";
echo "<p><strong>🔄 After making changes:</strong></p>";
echo "<ol>";
echo "<li>Refresh this debug page to see updated data</li>";
echo "<li>Check your adventures page to see if quests now appear</li>";
echo "<li>If still not working, check that your page actually contains the shortcode</li>";
echo "</ol>";

?>