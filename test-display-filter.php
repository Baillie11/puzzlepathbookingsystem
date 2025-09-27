<?php
/**
 * Test script to verify the display_on_site filter
 * This script simulates the booking form query to show what events would be displayed
 */

// This would normally be loaded by WordPress
// For testing purposes, you can connect to your database directly
// Uncomment and modify the following if you want to test database directly:

/*
$wpdb = new wpdb('username', 'password', 'database', 'localhost');
$events_table = $wpdb->prefix . 'pp_events';

echo "<h2>Events Query Test</h2>";

echo "<h3>ALL Events (regardless of display_on_site):</h3>";
$all_events = $wpdb->get_results("SELECT id, title, seats, display_on_site FROM $events_table WHERE seats > 0 ORDER BY event_date ASC");
foreach($all_events as $event) {
    echo "- {$event->title} (ID: {$event->id}, Seats: {$event->seats}, Display: " . ($event->display_on_site ? 'YES' : 'NO') . ")<br>";
}

echo "<h3>FILTERED Events (display_on_site = 1 only):</h3>";
$filtered_events = $wpdb->get_results("SELECT id, title, seats, display_on_site FROM $events_table WHERE seats > 0 AND display_on_site = 1 ORDER BY event_date ASC");
foreach($filtered_events as $event) {
    echo "- {$event->title} (ID: {$event->id}, Seats: {$event->seats}, Display: " . ($event->display_on_site ? 'YES' : 'NO') . ")<br>";
}

echo "<h3>HIDDEN Events (display_on_site = 0):</h3>";
$hidden_events = $wpdb->get_results("SELECT id, title, seats, display_on_site FROM $events_table WHERE seats > 0 AND display_on_site = 0 ORDER BY event_date ASC");
foreach($hidden_events as $event) {
    echo "- {$event->title} (ID: {$event->id}, Seats: {$event->seats}, Display: " . ($event->display_on_site ? 'YES' : 'NO') . ")<br>";
}
*/

echo "<h2>Fix Summary</h2>";
echo "<p><strong>What was fixed:</strong></p>";
echo "<ul>";
echo "<li>✅ Updated booking form query to filter by display_on_site = 1</li>";
echo "<li>✅ Updated REST API hunts endpoint to filter by display_on_site = 1</li>";
echo "<li>✅ Added display_on_site checkbox to events form</li>";
echo "<li>✅ Added display_on_site column to events table listing</li>";
echo "</ul>";

echo "<p><strong>Files Modified:</strong></p>";
echo "<ul>";
echo "<li>📁 puzzlepath-booking.php - Line 512: Added display_on_site = 1 filter to booking form query</li>";
echo "<li>📁 puzzlepath-booking.php - Lines 2294-2296: Added display_on_site = 1 filter to REST API</li>";
echo "<li>📁 includes/events.php - Added display_on_site checkbox to admin form</li>";
echo "<li>📁 includes/events.php - Added display_on_site column to admin table</li>";
echo "</ul>";

echo "<p><strong>How to test:</strong></p>";
echo "<ol>";
echo "<li>Go to PuzzlePath → Events in WordPress admin</li>";
echo "<li>Edit any events you want to hide and uncheck 'Display on Site'</li>";
echo "<li>Save the event</li>";
echo "<li>Check your booking form - those events should no longer appear in the dropdown</li>";
echo "<li>Events with 'Display on Site' checked should still appear</li>";
echo "</ol>";
?>