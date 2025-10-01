<?php
/**
 * Test script for adventure page admin fields functionality
 * Place this in your WordPress root and visit it to test the admin form updates
 */

require_once('wp-config.php');

echo "<h1>🧪 PuzzlePath Admin Fields Test</h1>";

global $wpdb;
$events_table = $wpdb->prefix . 'pp_events';

// Test 1: Check if all new columns exist in database
echo "<h2>📊 Database Schema Test</h2>";
$columns = $wpdb->get_results("SHOW COLUMNS FROM $events_table");
$expected_fields = ['quest_type', 'difficulty', 'quest_description', 'display_on_adventures_page', 'quest_image_url', 'display_on_site'];
$existing_fields = array_column($columns, 'Field');

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Status</th></tr>";
foreach ($expected_fields as $field) {
    $exists = in_array($field, $existing_fields);
    $status = $exists ? "<span style='color: green;'>✓ EXISTS</span>" : "<span style='color: red;'>✗ MISSING</span>";
    echo "<tr><td>{$field}</td><td>{$status}</td></tr>";
}
echo "</table>";

// Test 2: Check current quest data
echo "<h2>🎯 Current Quests Data</h2>";
$quests = $wpdb->get_results("SELECT id, title, quest_type, difficulty, display_on_site, display_on_adventures_page, quest_description FROM $events_table ORDER BY id DESC LIMIT 5");

if (empty($quests)) {
    echo "<p><em>No quests found in database.</em></p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Title</th><th>Quest Type</th><th>Difficulty</th><th>Booking Form</th><th>Adventures Page</th><th>Description</th></tr>";
    foreach ($quests as $quest) {
        $booking_form = $quest->display_on_site ? "✓ Visible" : "✗ Hidden";
        $adventures_page = $quest->display_on_adventures_page ? "✓ Visible" : "✗ Hidden";
        $description = $quest->quest_description ? substr($quest->quest_description, 0, 50) . '...' : '<em>None</em>';
        echo "<tr>";
        echo "<td>{$quest->id}</td>";
        echo "<td>{$quest->title}</td>";
        echo "<td>" . ucfirst($quest->quest_type ?: 'walking') . "</td>";
        echo "<td>" . ucfirst($quest->difficulty ?: 'easy') . "</td>";
        echo "<td>{$booking_form}</td>";
        echo "<td>{$adventures_page}</td>";
        echo "<td>{$description}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test 3: Check shortcode functionality
echo "<h2>🔧 Shortcode Test</h2>";
echo "<p><strong>Adventures Page Shortcode:</strong> <code>[puzzlepath_upcoming_adventures]</code></p>";

$adventures_quests = $wpdb->get_results("
    SELECT * FROM $events_table 
    WHERE display_on_adventures_page = 1 AND seats > 0 
    ORDER BY 
        CASE WHEN hosting_type = 'hosted' THEN 0 ELSE 1 END,
        event_date ASC,
        title ASC
");

echo "<p><strong>Quests that would appear on adventures page:</strong> " . count($adventures_quests) . "</p>";

if (!empty($adventures_quests)) {
    echo "<ul>";
    foreach ($adventures_quests as $quest) {
        $type_icon = ($quest->quest_type === 'driving') ? '🚗' : '🚶‍♂️';
        echo "<li>{$type_icon} <strong>{$quest->title}</strong> - {$quest->difficulty} - \${$quest->price}</li>";
    }
    echo "</ul>";
} else {
    echo "<p><em>No quests currently set to display on adventures page.</em></p>";
}

// Test 4: Admin interface instructions
echo "<h2>🛠 Admin Interface Test Instructions</h2>";
echo "<div style='background: #f0f0f0; padding: 15px; border-left: 4px solid #0073aa;'>";
echo "<ol>";
echo "<li>Go to your WordPress admin: <strong>PuzzlePath &gt; Events</strong></li>";
echo "<li>Edit any existing quest or create a new one</li>";
echo "<li>You should now see these new fields:</li>";
echo "<ul>";
echo "<li><strong>Display on Booking Form</strong> - Checkbox to control visibility in booking dropdown</li>";
echo "<li><strong>Quest Type</strong> - Dropdown: Walking Quest / Driving Quest</li>";
echo "<li><strong>Difficulty Level</strong> - Dropdown: Easy / Moderate / Hard</li>";
echo "<li><strong>Quest Description</strong> - Textarea for adventure description</li>";
echo "<li><strong>Display on Adventures Page</strong> - Checkbox for adventures page visibility</li>";
echo "<li><strong>Quest Image URL</strong> - Input for custom quest image</li>";
echo "</ul>";
echo "<li>Save a quest with these fields filled out</li>";
echo "<li>Refresh this test page to see the data saved</li>";
echo "<li>Use shortcode <code>[puzzlepath_upcoming_adventures]</code> on any page to display quest cards</li>";
echo "</ol>";
echo "</div>";

// Test 5: Sample admin form simulation
echo "<h2>📝 Sample Form Data (for reference)</h2>";
echo "<div style='background: #f9f9f9; padding: 15px; border: 1px solid #ddd;'>";
echo "<pre>";
echo "Sample quest data that would be saved:
- title: 'Brisbane City Adventure'
- quest_type: 'walking'
- difficulty: 'moderate'  
- quest_description: 'Explore Brisbane\'s hidden gems and solve puzzles in the heart of the city.'
- display_on_site: 1 (show in booking form)
- display_on_adventures_page: 1 (show on adventures page)
- quest_image_url: 'https://example.com/quest-image.jpg' (optional)
- price: 25.00
- location: 'Brisbane CBD'
- seats: 20
</pre>";
echo "</div>";

echo "<hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Test the admin interface with the new fields</li>";
echo "<li>Create or edit quests with adventure page data</li>";
echo "<li>Add <code>[puzzlepath_upcoming_adventures]</code> shortcode to your adventures page</li>";
echo "<li>Verify quests display correctly with proper styling and data</li>";
echo "</ol>";

?>