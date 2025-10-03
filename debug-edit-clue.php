<?php
/**
 * DIAGNOSTIC SCRIPT: Edit Clue Functionality
 * Upload this to your WordPress root directory and access via browser
 * URL: https://yoursite.com/debug-edit-clue.php
 */

// Load WordPress
require_once('wp-config.php');

if (!current_user_can('manage_options')) {
    die('You need admin access to run this diagnostic.');
}

echo "<h1>🔍 Edit Clue Functionality Diagnostic</h1>";
echo "<hr>";

// Test 1: Check if plugin file contains our changes
echo "<h2>1. Plugin File Check</h2>";
$plugin_file = WP_PLUGIN_DIR . '/puzzlepath-booking/puzzlepath-booking.php';
if (file_exists($plugin_file)) {
    $file_content = file_get_contents($plugin_file);
    
    // Check for our specific functions
    $checks = [
        'puzzlepath_get_clue_ajax' => strpos($file_content, 'function puzzlepath_get_clue_ajax()') !== false,
        'puzzlepath_save_clue_ajax' => strpos($file_content, 'function puzzlepath_save_clue_ajax()') !== false,
        'editClue function' => strpos($file_content, 'function editClue(clueId)') !== false,
        'edit-clue-modal' => strpos($file_content, 'edit-clue-modal') !== false,
        'pp_clues table creation' => strpos($file_content, 'pp_clues') !== false,
    ];
    
    foreach ($checks as $check => $found) {
        echo ($found ? "✅" : "❌") . " {$check}: " . ($found ? "FOUND" : "NOT FOUND") . "<br>";
    }
    
    // Get file modification time
    $mod_time = filemtime($plugin_file);
    echo "<br><strong>File last modified:</strong> " . date('Y-m-d H:i:s', $mod_time) . "<br>";
    
} else {
    echo "❌ Plugin file not found at: {$plugin_file}<br>";
}

// Test 2: Check database table existence
echo "<br><h2>2. Database Table Check</h2>";
global $wpdb;

$tables = [
    'pp_events' => $wpdb->prefix . 'pp_events',
    'pp_clues' => $wpdb->prefix . 'pp_clues'
];

foreach ($tables as $name => $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
    echo ($exists ? "✅" : "❌") . " Table {$name}: " . ($exists ? "EXISTS" : "NOT FOUND") . "<br>";
    
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        echo "   └─ Records: {$count}<br>";
        
        if ($name === 'pp_clues') {
            // Show table structure
            $columns = $wpdb->get_results("DESCRIBE {$table}");
            echo "   └─ Columns: ";
            foreach ($columns as $col) {
                echo $col->Field . " ";
            }
            echo "<br>";
        }
    }
}

// Test 3: Check AJAX actions registration
echo "<br><h2>3. AJAX Actions Check</h2>";
$ajax_actions = [
    'wp_ajax_get_clue',
    'wp_ajax_save_clue',
    'wp_ajax_get_quest_clues'
];

foreach ($ajax_actions as $action) {
    $has_action = has_action($action);
    echo ($has_action ? "✅" : "❌") . " Action {$action}: " . ($has_action ? "REGISTERED" : "NOT REGISTERED") . "<br>";
}

// Test 4: Test basic quest data
echo "<br><h2>4. Quest Data Check</h2>";
$quests = $wpdb->get_results("SELECT id, title, hunt_code FROM {$wpdb->prefix}pp_events WHERE hunt_code IS NOT NULL LIMIT 5");

if ($quests) {
    echo "✅ Found " . count($quests) . " quests:<br>";
    foreach ($quests as $quest) {
        echo "   └─ #{$quest->id}: {$quest->title} ({$quest->hunt_code})<br>";
        
        // Check for clues
        $clue_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}pp_clues WHERE hunt_id = %d", $quest->id));
        echo "      └─ Clues: {$clue_count}<br>";
    }
} else {
    echo "❌ No quests found<br>";
}

// Test 5: JavaScript check
echo "<br><h2>5. JavaScript Function Test</h2>";
echo "Copy and paste this into your browser console on the Quests page:<br>";
echo "<textarea style='width: 100%; height: 100px;'>
// Test if our functions exist
console.log('editClue function:', typeof editClue);
console.log('showEditClueForm function:', typeof showEditClueForm);
console.log('saveClueChanges function:', typeof saveClueChanges);
console.log('jQuery loaded:', typeof jQuery);

// Test modal exists
console.log('Edit clue modal exists:', document.getElementById('edit-clue-modal') !== null);
</textarea>";

// Test 6: WordPress version and compatibility
echo "<br><h2>6. WordPress Environment</h2>";
echo "✅ WordPress Version: " . get_bloginfo('version') . "<br>";
echo "✅ PHP Version: " . PHP_VERSION . "<br>";
echo "✅ Plugin Active: " . (is_plugin_active('puzzlepath-booking/puzzlepath-booking.php') ? "YES" : "NO") . "<br>";

// Test 7: Create a test AJAX call
echo "<br><h2>7. Manual AJAX Test</h2>";
echo "Try this URL in a new tab (replace QUEST_ID and CLUE_ID with real values):<br>";
echo "<strong>Get Quest Clues:</strong><br>";
echo admin_url('admin-ajax.php?action=get_quest_clues&quest_id=1&nonce=' . wp_create_nonce('quest_clues_nonce')) . "<br><br>";

if (!empty($quests)) {
    $sample_quest = $quests[0];
    $sample_clue = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pp_clues WHERE hunt_id = %d LIMIT 1", $sample_quest->id));
    
    if ($sample_clue) {
        echo "<strong>Get Single Clue:</strong><br>";
        echo "POST to: " . admin_url('admin-ajax.php') . "<br>";
        echo "Data: action=get_clue&clue_id={$sample_clue->id}&nonce=" . wp_create_nonce('edit_clue_nonce') . "<br>";
    }
}

echo "<br><h2>🎯 Next Steps</h2>";
echo "1. Check all the ❌ items above<br>";
echo "2. If plugin file is missing our changes, re-upload it<br>";
echo "3. If pp_clues table doesn't exist, deactivate and reactivate the plugin<br>";
echo "4. Test the JavaScript console commands on your Quests admin page<br>";
echo "5. Check your browser's Network tab when clicking 'Edit Clue'<br>";

echo "<br><hr><small>Generated on: " . date('Y-m-d H:i:s') . "</small>";
?>