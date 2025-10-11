<?php
/**
 * Plugin Name: PuzzlePath Booking
 * Description: A custom booking plugin for PuzzlePath with unified app integration.
 * Version: 2.8.4
 * Author: Andrew Baillie
 */

defined('ABSPATH') or die('No script kiddies please!');

/**
 * Activation hook to create/update database tables.
 */
function puzzlepath_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Events Table - Enhanced with hunt codes
    $table_name = $wpdb->prefix . 'pp_events';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        title varchar(255) NOT NULL,
        hunt_code varchar(10) DEFAULT NULL,
        hunt_name varchar(255) DEFAULT NULL,
        hosting_type varchar(20) DEFAULT 'hosted' NOT NULL,
        event_date datetime,
        location varchar(255) NOT NULL,
        price float NOT NULL,
        seats int(11) NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY hunt_code (hunt_code)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Coupons Table
    $table_name = $wpdb->prefix . 'pp_coupons';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        code varchar(50) NOT NULL,
        discount_percent int(3) NOT NULL,
        max_uses int(11) DEFAULT 0 NOT NULL,
        times_used int(11) DEFAULT 0 NOT NULL,
        expires_at datetime,
        PRIMARY KEY  (id),
        UNIQUE KEY code (code)
    ) $charset_collate;";
    dbDelta($sql);

    // Bookings Table - Enhanced for unified app compatibility
    $table_name = $wpdb->prefix . 'pp_bookings';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        event_id mediumint(9) NOT NULL,
        hunt_id varchar(10) DEFAULT NULL,
        customer_name varchar(255) NOT NULL,
        customer_email varchar(255) NOT NULL,
        participant_names text DEFAULT NULL,
        tickets int(11) NOT NULL,
        total_price float NOT NULL,
        coupon_id mediumint(9),
        payment_status varchar(50) DEFAULT 'pending' NOT NULL,
        stripe_payment_intent_id varchar(255),
        booking_code varchar(25),
        booking_date date DEFAULT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);
    
    // Clues Table - For quest clue data
    $table_name = $wpdb->prefix . 'pp_clues';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        hunt_id mediumint(9) NOT NULL,
        clue_order int(11) NOT NULL,
        title varchar(255) DEFAULT NULL,
        clue_text text NOT NULL,
        task_description text DEFAULT NULL,
        hint_text text DEFAULT NULL,
        answer varchar(255) NOT NULL,
        latitude decimal(10,7) DEFAULT NULL,
        longitude decimal(10,7) DEFAULT NULL,
        geofence_radius int(11) DEFAULT NULL,
        image_url varchar(500) DEFAULT NULL,
        is_active tinyint(1) DEFAULT 1 NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        INDEX hunt_id (hunt_id),
        INDEX is_active (is_active),
        INDEX clue_order (clue_order)
    ) $charset_collate;";
    dbDelta($sql);
    
    // Audit Log Table - Comprehensive tracking of all booking changes
    $table_name = $wpdb->prefix . 'pp_booking_audit';
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        booking_id mediumint(9) NOT NULL,
        booking_code varchar(25) DEFAULT NULL,
        action varchar(50) NOT NULL,
        user_id bigint(20) DEFAULT NULL,
        user_login varchar(60) DEFAULT NULL,
        user_email varchar(100) DEFAULT NULL,
        ip_address varchar(45) DEFAULT NULL,
        user_agent text DEFAULT NULL,
        old_data longtext DEFAULT NULL,
        new_data longtext DEFAULT NULL,
        changed_fields text DEFAULT NULL,
        notes text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        INDEX booking_id (booking_id),
        INDEX action (action),
        INDEX user_id (user_id),
        INDEX created_at (created_at),
        INDEX booking_code (booking_code)
    ) $charset_collate;";
    dbDelta($sql);
    
    // Create compatibility view for unified app
    $view_name = $wpdb->prefix . 'pp_bookings_unified';
    $wpdb->query("DROP VIEW IF EXISTS $view_name");
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    $events_table = $wpdb->prefix . 'pp_events';
    $wpdb->query("CREATE VIEW $view_name AS 
        SELECT 
            b.id,
            b.booking_code,
            b.hunt_id,
            e.hunt_code,
            e.hunt_name,
            e.title as event_title,
            e.location,
            e.event_date,
            b.customer_name,
            b.customer_email,
            b.participant_names,
            b.tickets as participant_count,
            b.total_price,
            b.booking_date,
            b.created_at,
            b.payment_status
        FROM $bookings_table b
        LEFT JOIN $events_table e ON b.event_id = e.id");
    
    // Force database schema update for event_date column
    $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events MODIFY COLUMN event_date datetime DEFAULT NULL");
    
    // Fix created_at column for events table
    $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events MODIFY COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL");
    
    // Add duration_minutes column if it doesn't exist
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}pp_events LIKE 'duration_minutes'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events ADD COLUMN duration_minutes int(11) DEFAULT NULL AFTER price");
    }
    
    // Add medal_image_url column if it doesn't exist
    $medal_column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}pp_events LIKE 'medal_image_url'");
    if (empty($medal_column_exists)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events ADD COLUMN medal_image_url varchar(500) DEFAULT NULL AFTER duration_minutes");
    }
    
    // Add display_on_site column if it doesn't exist (default 1 = visible)
    $display_column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}pp_events LIKE 'display_on_site'");
    if (empty($display_column_exists)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events ADD COLUMN display_on_site tinyint(1) DEFAULT 1 AFTER medal_image_url");
        // Update existing quests to be visible by default
        $wpdb->query("UPDATE {$wpdb->prefix}pp_events SET display_on_site = 1 WHERE display_on_site IS NULL OR display_on_site = 0");
    }
    
    // Add quest_type column for Walking/Driving classification
    $quest_type_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}pp_events LIKE 'quest_type'");
    if (empty($quest_type_exists)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events ADD COLUMN quest_type varchar(20) DEFAULT 'walking' AFTER display_on_site");
    }
    
    // Add difficulty column for Easy/Moderate/Hard
    $difficulty_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}pp_events LIKE 'difficulty'");
    if (empty($difficulty_exists)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events ADD COLUMN difficulty varchar(20) DEFAULT 'easy' AFTER quest_type");
    }
    
    // Add quest_description column for adventure details
    $quest_desc_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}pp_events LIKE 'quest_description'");
    if (empty($quest_desc_exists)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events ADD COLUMN quest_description text DEFAULT NULL AFTER difficulty");
    }
    
    // Add display_on_adventures_page column for adventures page visibility
    $display_adventures_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}pp_events LIKE 'display_on_adventures_page'");
    if (empty($display_adventures_exists)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events ADD COLUMN display_on_adventures_page tinyint(1) DEFAULT 0 AFTER quest_description");
    }
    
    // Add quest_image_url column for future custom quest images
    $quest_image_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}pp_events LIKE 'quest_image_url'");
    if (empty($quest_image_exists)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events ADD COLUMN quest_image_url varchar(500) DEFAULT NULL AFTER display_on_adventures_page");
    }
    
    // Add sorting and priority columns
    $sort_order_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}pp_events LIKE 'sort_order'");
    if (empty($sort_order_exists)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events ADD COLUMN sort_order int(11) DEFAULT 0 AFTER quest_image_url");
    }
    
    $is_featured_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}pp_events LIKE 'is_featured'");
    if (empty($is_featured_exists)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events ADD COLUMN is_featured tinyint(1) DEFAULT 0 AFTER sort_order");
    }
    
    // Ensure unified app compatibility by updating existing bookings
    puzzlepath_fix_unified_app_compatibility();
    
    update_option('puzzlepath_booking_version', '2.8.5');
}

register_activation_hook(__FILE__, 'puzzlepath_activate');

/**
 * Comprehensive audit logging system for booking changes
 */
class PuzzlePath_Audit_Logger {
    
    /**
     * Log a booking action to the audit trail
     * 
     * @param int $booking_id Booking ID
     * @param string $action Action performed (created, updated, status_changed, deleted, etc.)
     * @param array|null $old_data Previous booking data (null for new bookings)
     * @param array|null $new_data New booking data (null for deletions)
     * @param string $notes Additional notes about the action
     * @param int|null $user_id User who performed the action (null for system actions)
     */
    public static function log_booking_action($booking_id, $action, $old_data = null, $new_data = null, $notes = '', $user_id = null) {
        global $wpdb;
        
        // Get current user info if not provided
        if ($user_id === null) {
            $current_user = wp_get_current_user();
            $user_id = $current_user->ID ?: null;
            $user_login = $current_user->user_login ?: 'system';
            $user_email = $current_user->user_email ?: null;
        } else {
            $user = get_userdata($user_id);
            $user_login = $user ? $user->user_login : 'unknown';
            $user_email = $user ? $user->user_email : null;
        }
        
        // Get booking code if we have booking data
        $booking_code = null;
        if ($new_data && isset($new_data['booking_code'])) {
            $booking_code = $new_data['booking_code'];
        } elseif ($old_data && isset($old_data['booking_code'])) {
            $booking_code = $old_data['booking_code'];
        } else {
            // Try to get from database
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT booking_code FROM {$wpdb->prefix}pp_bookings WHERE id = %d", 
                $booking_id
            ));
            $booking_code = $booking ? $booking->booking_code : null;
        }
        
        // Determine what fields changed
        $changed_fields = [];
        if ($old_data && $new_data) {
            foreach ($new_data as $field => $new_value) {
                $old_value = isset($old_data[$field]) ? $old_data[$field] : null;
                if ($old_value != $new_value) {
                    $changed_fields[] = $field;
                }
            }
        }
        
        // Get request info
        $ip_address = self::get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
        
        // Prepare audit data
        $audit_data = [
            'booking_id' => $booking_id,
            'booking_code' => $booking_code,
            'action' => $action,
            'user_id' => $user_id,
            'user_login' => $user_login,
            'user_email' => $user_email,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'old_data' => $old_data ? json_encode($old_data) : null,
            'new_data' => $new_data ? json_encode($new_data) : null,
            'changed_fields' => !empty($changed_fields) ? implode(',', $changed_fields) : null,
            'notes' => $notes,
            'created_at' => current_time('mysql')
        ];
        
        // Insert audit record
        $result = $wpdb->insert(
            $wpdb->prefix . 'pp_booking_audit',
            $audit_data
        );
        
        // Log errors if insert failed
        if ($result === false) {
            error_log('PuzzlePath Audit Log Error: ' . $wpdb->last_error);
        }
        
        return $result;
    }
    
    /**
     * Log booking creation
     */
    public static function log_booking_created($booking_id, $booking_data, $notes = 'Booking created via website') {
        return self::log_booking_action($booking_id, 'created', null, $booking_data, $notes);
    }
    
    /**
     * Log booking update
     */
    public static function log_booking_updated($booking_id, $old_data, $new_data, $notes = 'Booking updated') {
        return self::log_booking_action($booking_id, 'updated', $old_data, $new_data, $notes);
    }
    
    /**
     * Log payment status change
     */
    public static function log_payment_status_changed($booking_id, $old_status, $new_status, $notes = 'Payment status changed') {
        $old_data = ['payment_status' => $old_status];
        $new_data = ['payment_status' => $new_status];
        return self::log_booking_action($booking_id, 'payment_status_changed', $old_data, $new_data, $notes);
    }
    
    /**
     * Log booking deletion
     */
    public static function log_booking_deleted($booking_id, $booking_data, $notes = 'Booking deleted') {
        return self::log_booking_action($booking_id, 'deleted', $booking_data, null, $notes);
    }
    
    /**
     * Log bulk deletion
     */
    public static function log_bulk_deletion($booking_ids, $notes = 'Bulk deletion performed') {
        global $wpdb;
        
        // Get all booking data before deletion
        $placeholders = implode(',', array_fill(0, count($booking_ids), '%d'));
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pp_bookings WHERE id IN ($placeholders)",
            $booking_ids
        ), ARRAY_A);
        
        // Log each deletion
        foreach ($bookings as $booking) {
            self::log_booking_deleted($booking['id'], $booking, $notes);
        }
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    }
    
    /**
     * Get audit log entries for a booking
     */
    public static function get_booking_audit_log($booking_id, $limit = 50) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pp_booking_audit 
             WHERE booking_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d",
            $booking_id,
            $limit
        ), ARRAY_A);
    }
    
    /**
     * Get all audit log entries with filtering
     */
    public static function get_audit_log($filters = [], $limit = 100, $offset = 0) {
        global $wpdb;
        
        $where_clauses = [];
        $where_values = [];
        
        // Build WHERE clauses based on filters
        if (!empty($filters['booking_id'])) {
            $where_clauses[] = 'booking_id = %d';
            $where_values[] = $filters['booking_id'];
        }
        
        if (!empty($filters['booking_code'])) {
            $where_clauses[] = 'booking_code LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like($filters['booking_code']) . '%';
        }
        
        if (!empty($filters['action'])) {
            $where_clauses[] = 'action = %s';
            $where_values[] = $filters['action'];
        }
        
        if (!empty($filters['user_id'])) {
            $where_clauses[] = 'user_id = %d';
            $where_values[] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = 'DATE(created_at) >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = 'DATE(created_at) <= %s';
            $where_values[] = $filters['date_to'];
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $query = "SELECT * FROM {$wpdb->prefix}pp_booking_audit $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, [$limit, $offset]);
        
        return $wpdb->get_results($wpdb->prepare($query, $query_values), ARRAY_A);
    }
}

/**
 * Fix unified app compatibility for existing bookings
 */
function puzzlepath_fix_unified_app_compatibility() {
    global $wpdb;
    
    // Update existing bookings to have correct hunt_id from events
    $wpdb->query("
        UPDATE {$wpdb->prefix}pp_bookings b 
        LEFT JOIN {$wpdb->prefix}pp_events e ON b.event_id = e.id 
        SET b.hunt_id = e.hunt_code 
        WHERE b.hunt_id IS NULL OR b.hunt_id = '' OR b.hunt_id != e.hunt_code
    ");
    
    // Recreate the unified view with better field mapping
    $view_name = $wpdb->prefix . 'pp_bookings_unified';
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    $events_table = $wpdb->prefix . 'pp_events';
    
    $wpdb->query("DROP VIEW IF EXISTS $view_name");
    $wpdb->query("CREATE VIEW $view_name AS 
        SELECT 
            b.id,
            b.booking_code,
            COALESCE(b.hunt_id, e.hunt_code) as hunt_id,
            e.hunt_code,
            e.hunt_name,
            e.title as event_title,
            e.location,
            e.event_date,
            b.customer_name,
            b.customer_email,
            b.participant_names,
            b.tickets as participant_count,
            b.total_price,
            b.booking_date,
            b.created_at,
            b.payment_status,
            CASE 
                WHEN b.payment_status = 'paid' THEN 'confirmed'
                WHEN b.payment_status = 'pending' THEN 'pending'
                ELSE 'cancelled'
            END as status
        FROM $bookings_table b
        LEFT JOIN $events_table e ON b.event_id = e.id
        WHERE b.payment_status IN ('paid', 'pending')");
}

/**
 * Check if database needs updating on plugin load.
 */
function puzzlepath_update_db_check() {
    $current_version = get_option('puzzlepath_booking_version', '1.0');
    if (version_compare($current_version, '2.8.5', '<')) {
        puzzlepath_activate();
        // Generate hunt codes for existing events that don't have them
        puzzlepath_generate_missing_hunt_codes();
        // Update payment statuses for existing bookings
        puzzlepath_update_payment_statuses();
    }
}
add_action('plugins_loaded', 'puzzlepath_update_db_check');

/**
 * Manual database update function - can be called via URL
 */
function puzzlepath_manual_db_update() {
    if (isset($_GET['puzzlepath_update_db']) && current_user_can('manage_options')) {
        if (wp_verify_nonce($_GET['nonce'], 'puzzlepath_manual_update')) {
            puzzlepath_activate();
            wp_redirect(admin_url('admin.php?page=puzzlepath-quests&message=db_updated'));
            exit;
        }
    }
}
add_action('admin_init', 'puzzlepath_manual_db_update');

/**
 * Force payment status migration on next admin page load (run once)
 */
function puzzlepath_force_payment_migration() {
    if (is_admin() && current_user_can('manage_options')) {
        $migration_done = get_option('puzzlepath_payment_migration_2025', false);
        if (!$migration_done) {
            puzzlepath_update_payment_statuses();
            update_option('puzzlepath_payment_migration_2025', true);
        }
    }
}
add_action('admin_init', 'puzzlepath_force_payment_migration');

/**
 * Display admin notice after payment status migration
 */
function puzzlepath_payment_migration_admin_notice() {
    $migrated_count = get_transient('puzzlepath_payment_migration_notice');
    if ($migrated_count) {
        delete_transient('puzzlepath_payment_migration_notice');
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>PuzzlePath Booking:</strong> Successfully updated ' . $migrated_count . ' booking(s) from "succeeded" to "paid" status. Your revenue calculations should now work correctly.</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'puzzlepath_payment_migration_admin_notice');

/**
 * Manual migration function - can be called via URL parameter
 */
function puzzlepath_manual_payment_migration() {
    if (isset($_GET['puzzlepath_migrate']) && current_user_can('manage_options')) {
        if (wp_verify_nonce($_GET['nonce'], 'puzzlepath_migrate_payments')) {
            puzzlepath_update_payment_statuses();
            wp_redirect(admin_url('admin.php?page=puzzlepath-bookings&message=migration_complete'));
            exit;
        }
    }
}
add_action('admin_init', 'puzzlepath_manual_payment_migration');

/**
 * Generate unique hunt code based on event details
 * Format: First letter of each word in title + location abbreviation + sequential number
 * Example: "Escape Room Adventure" in "Brisbane" -> "ERAB1", "ERAB2", etc.
 */
function puzzlepath_generate_hunt_code($event_data) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    
    // Get first letter of each word in title (max 3 letters)
    $title_words = explode(' ', $event_data['title']);
    $title_prefix = '';
    foreach ($title_words as $word) {
        if (strlen($title_prefix) < 3 && !empty($word)) {
            $title_prefix .= strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $word), 0, 1));
        }
    }
    
    // Ensure we have at least 1 character from title, pad if needed
    if (empty($title_prefix)) {
        $title_prefix = 'E'; // Default to 'E' for Event
    }
    
    // Get location abbreviation (first 2 letters, uppercase)
    $location_prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $event_data['location']), 0, 2));
    if (empty($location_prefix)) {
        $location_prefix = 'XX';
    } elseif (strlen($location_prefix) == 1) {
        $location_prefix .= 'X';
    }
    
    // Generate base pattern (e.g., "ERABR")
    $base_pattern = $title_prefix . $location_prefix;
    
    // Find the next sequential number for this pattern
    $existing_codes = $wpdb->get_col($wpdb->prepare(
        "SELECT hunt_code FROM $events_table WHERE hunt_code LIKE %s ORDER BY hunt_code DESC",
        $base_pattern . '%'
    ));
    
    $next_number = 1;
    if (!empty($existing_codes)) {
        foreach ($existing_codes as $code) {
            // Extract number from end of code
            $number = intval(preg_replace('/[^0-9]/', '', substr($code, strlen($base_pattern))));
            if ($number >= $next_number) {
                $next_number = $number + 1;
            }
        }
    }
    
    // Format as base + number (e.g., "ERABR1", "ERABR2")
    // Keep within 10 character limit
    $full_code = $base_pattern . $next_number;
    if (strlen($full_code) > 10) {
        // Truncate base pattern if needed
        $available_chars = 10 - strlen($next_number);
        $base_pattern = substr($base_pattern, 0, $available_chars);
        $full_code = $base_pattern . $next_number;
    }
    
    return $full_code;
}

/**
 * Update payment statuses from 'succeeded' to 'paid' for consistency
 */
function puzzlepath_update_payment_statuses() {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    
    // Update all 'succeeded' statuses to 'paid'
    $wpdb->query("
        UPDATE {$bookings_table}
        SET payment_status = 'paid'
        WHERE payment_status = 'succeeded'
    ");
    
    // Log the status update
    $rows_affected = $wpdb->rows_affected;
    error_log("PuzzlePath payment status migration: Updated {$rows_affected} bookings from 'succeeded' to 'paid'");
    
    // Show admin notice if any updates were made
    if ($rows_affected > 0) {
        set_transient('puzzlepath_payment_migration_notice', $rows_affected, 30);
    }
}

/**
 * Generate hunt codes for existing events that don't have them
 */
function puzzlepath_generate_missing_hunt_codes() {
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    
    // Get all events without hunt codes
    $events = $wpdb->get_results(
        "SELECT * FROM $events_table WHERE hunt_code IS NULL OR hunt_code = ''"
    );
    
    foreach ($events as $event) {
        $event_data = [
            'title' => $event->title,
            'location' => $event->location
        ];
        
        $hunt_code = puzzlepath_generate_hunt_code($event_data);
        
        // Also generate a hunt name if it doesn't exist
        $hunt_name = !empty($event->hunt_name) ? $event->hunt_name : $event->title . ' - ' . $event->location;
        
        $wpdb->update(
            $events_table,
            [
                'hunt_code' => $hunt_code,
                'hunt_name' => $hunt_name
            ],
            ['id' => $event->id]
        );
    }
}

/**
 * Centralized function to create all admin menus.
 */
function puzzlepath_register_admin_menus() {
    // Custom jigsaw puzzle SVG icon - simple and clean design
    $puzzle_svg = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M2 2h6v2.5a1.5 1.5 0 003 0V2h6a1 1 0 011 1v6h-2.5a1.5 1.5 0 000 3H18v6a1 1 0 01-1 1h-6v-2.5a1.5 1.5 0 00-3 0V19H2a1 1 0 01-1-1v-6h2.5a1.5 1.5 0 000-3H1V3a1 1 0 011-1z"/></svg>');
    
    add_menu_page('PuzzlePath Bookings', 'PuzzlePath', 'manage_options', 'puzzlepath-booking', 'puzzlepath_quests_page', $puzzle_svg, 20);
    add_submenu_page('puzzlepath-booking', 'Bookings', 'Bookings', 'manage_options', 'puzzlepath-bookings', 'puzzlepath_bookings_page');
    add_submenu_page('puzzlepath-booking', 'Quests', 'Quests', 'manage_options', 'puzzlepath-quests', 'puzzlepath_quests_page');
    add_submenu_page('puzzlepath-booking', 'Coupons', 'Coupons', 'manage_options', 'puzzlepath-coupons', 'puzzlepath_coupons_page');
    add_submenu_page('puzzlepath-booking', 'Quest Import', 'Quest Import', 'edit_posts', 'puzzlepath-quest-import', 'puzzlepath_quest_import_page');
    add_submenu_page('puzzlepath-booking', 'Test Bookings', 'Test Bookings', 'manage_options', 'puzzlepath-test-bookings', 'puzzlepath_test_bookings_page');
    add_submenu_page('puzzlepath-booking', 'Audit Log', 'Audit Log', 'manage_options', 'puzzlepath-audit-log', 'puzzlepath_audit_log_page');
    add_submenu_page('puzzlepath-booking', 'Email Settings', 'Email Settings', 'manage_options', 'puzzlepath-email-settings', 'puzzlepath_email_settings_page');
    if (class_exists('PuzzlePath_Stripe_Integration')) {
        $stripe_instance = PuzzlePath_Stripe_Integration::get_instance();
        add_submenu_page('puzzlepath-booking', 'Stripe Settings', 'Stripe Settings', 'manage_options', 'puzzlepath-stripe-settings', array($stripe_instance, 'stripe_settings_page_content'));
    }
    remove_submenu_page('puzzlepath-booking', 'puzzlepath-booking');
}
add_action('admin_menu', 'puzzlepath_register_admin_menus');

/**
 * Display the Audit Log admin page
 */
function puzzlepath_audit_log_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    // Handle filtering
    $filters = [];
    if (isset($_GET['booking_code']) && !empty($_GET['booking_code'])) {
        $filters['booking_code'] = sanitize_text_field($_GET['booking_code']);
    }
    if (isset($_GET['action_filter']) && !empty($_GET['action_filter'])) {
        $filters['action'] = sanitize_text_field($_GET['action_filter']);
    }
    if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
        $filters['date_from'] = sanitize_text_field($_GET['date_from']);
    }
    if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
        $filters['date_to'] = sanitize_text_field($_GET['date_to']);
    }
    
    $per_page = 50;
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($page - 1) * $per_page;
    
    // Get audit log entries
    $audit_entries = PuzzlePath_Audit_Logger::get_audit_log($filters, $per_page, $offset);
    
    // Get total count for pagination
    global $wpdb;
    $where_clauses = [];
    $where_values = [];
    
    if (!empty($filters['booking_code'])) {
        $where_clauses[] = 'booking_code LIKE %s';
        $where_values[] = '%' . $wpdb->esc_like($filters['booking_code']) . '%';
    }
    if (!empty($filters['action'])) {
        $where_clauses[] = 'action = %s';
        $where_values[] = $filters['action'];
    }
    if (!empty($filters['date_from'])) {
        $where_clauses[] = 'DATE(created_at) >= %s';
        $where_values[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $where_clauses[] = 'DATE(created_at) <= %s';
        $where_values[] = $filters['date_to'];
    }
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}pp_booking_audit $where_sql";
    $total_items = empty($where_values) ? 
        $wpdb->get_var($count_query) : 
        $wpdb->get_var($wpdb->prepare($count_query, $where_values));
    
    $total_pages = ceil($total_items / $per_page);
    
    ?>
    <div class="wrap">
        <h1>📋 Booking Audit Log</h1>
        <p>Comprehensive tracking of all booking changes and activities.</p>
        
        <!-- Filters -->
        <div class="audit-filters" style="background: #fff; padding: 15px; margin-bottom: 20px; border: 1px solid #c3c4c7; border-radius: 4px;">
            <form method="get" action="">
                <input type="hidden" name="page" value="puzzlepath-audit-log" />
                
                <div style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                    <div>
                        <label for="booking_code"><strong>Booking Code:</strong></label><br>
                        <input type="text" name="booking_code" id="booking_code" value="<?php echo esc_attr($filters['booking_code'] ?? ''); ?>" placeholder="Search by booking code" class="regular-text" />
                    </div>
                    
                    <div>
                        <label for="action_filter"><strong>Action:</strong></label><br>
                        <select name="action_filter" id="action_filter">
                            <option value="">All Actions</option>
                            <option value="created" <?php selected($filters['action'] ?? '', 'created'); ?>>Created</option>
                            <option value="updated" <?php selected($filters['action'] ?? '', 'updated'); ?>>Updated</option>
                            <option value="payment_status_changed" <?php selected($filters['action'] ?? '', 'payment_status_changed'); ?>>Payment Status Changed</option>
                            <option value="deleted" <?php selected($filters['action'] ?? '', 'deleted'); ?>>Deleted</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="date_from"><strong>From Date:</strong></label><br>
                        <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($filters['date_from'] ?? ''); ?>" />
                    </div>
                    
                    <div>
                        <label for="date_to"><strong>To Date:</strong></label><br>
                        <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($filters['date_to'] ?? ''); ?>" />
                    </div>
                    
                    <div>
                        <input type="submit" class="button" value="Filter" />
                        <a href="<?php echo admin_url('admin.php?page=puzzlepath-audit-log'); ?>" class="button">Clear</a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Statistics -->
        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
            <div style="background: #fff; padding: 15px; border-left: 4px solid #2271b1; box-shadow: 0 1px 1px rgba(0,0,0,.04); flex: 1;">
                <h3 style="margin: 0; color: #2271b1;">Total Entries</h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;"><?php echo number_format($total_items); ?></p>
            </div>
            
            <?php 
            $action_counts = $wpdb->get_results(
                "SELECT action, COUNT(*) as count FROM {$wpdb->prefix}pp_booking_audit GROUP BY action ORDER BY count DESC", 
                ARRAY_A
            );
            foreach (array_slice($action_counts, 0, 3) as $action_count): ?>
            <div style="background: #fff; padding: 15px; border-left: 4px solid #00a32a; box-shadow: 0 1px 1px rgba(0,0,0,.04); flex: 1;">
                <h3 style="margin: 0; color: #00a32a;"><?php echo ucwords(str_replace('_', ' ', $action_count['action'])); ?></h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;"><?php echo number_format($action_count['count']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Audit Log Table -->
        <?php if (empty($audit_entries)): ?>
            <div class="notice notice-info">
                <p><strong>No audit entries found.</strong> <?php echo empty($filters) ? 'No booking activities have been logged yet.' : 'Try adjusting your filters.'; ?></p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 140px;">Date/Time</th>
                        <th style="width: 120px;">Booking Code</th>
                        <th style="width: 80px;">Action</th>
                        <th style="width: 100px;">User</th>
                        <th style="width: 120px;">Changes</th>
                        <th>Notes</th>
                        <th style="width: 60px;">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($audit_entries as $entry): ?>
                        <tr>
                            <td>
                                <strong><?php echo date('M j, Y', strtotime($entry['created_at'])); ?></strong><br>
                                <small style="color: #666;"><?php echo date('g:i A', strtotime($entry['created_at'])); ?></small>
                            </td>
                            <td>
                                <?php if ($entry['booking_code']): ?>
                                    <code style="background: #f1f1f1; padding: 2px 4px; border-radius: 3px;"><?php echo esc_html($entry['booking_code']); ?></code>
                                <?php else: ?>
                                    <em style="color: #666;">N/A</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $action_colors = [
                                    'created' => '#00a32a',
                                    'updated' => '#2271b1', 
                                    'payment_status_changed' => '#dba617',
                                    'deleted' => '#d63638'
                                ];
                                $color = $action_colors[$entry['action']] ?? '#666';
                                ?>
                                <span style="padding: 2px 6px; border-radius: 3px; font-size: 11px; text-transform: uppercase; color: white; background: <?php echo $color; ?>; font-weight: bold;">
                                    <?php echo esc_html(str_replace('_', ' ', $entry['action'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($entry['user_login'] && $entry['user_login'] !== 'system'): ?>
                                    <strong><?php echo esc_html($entry['user_login']); ?></strong>
                                    <?php if ($entry['user_email']): ?>
                                        <br><small style="color: #666;"><?php echo esc_html($entry['user_email']); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <em style="color: #666;">System</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($entry['changed_fields']): ?>
                                    <small><?php echo esc_html($entry['changed_fields']); ?></small>
                                <?php else: ?>
                                    <em style="color: #666;">—</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($entry['notes'] ?: '—'); ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small" onclick="showAuditDetails(<?php echo $entry['id']; ?>)">View</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav" style="margin-top: 20px;">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo number_format($total_items); ?> items</span>
                        
                        <?php 
                        $base_url = admin_url('admin.php?page=puzzlepath-audit-log');
                        if (!empty($filters)) {
                            $base_url .= '&' . http_build_query(array_filter($filters));
                        }
                        ?>
                        
                        <span class="pagination-links">
                            <?php if ($page > 1): ?>
                                <a class="prev-page button" href="<?php echo $base_url . '&paged=' . ($page - 1); ?>">‹</a>
                            <?php endif; ?>
                            
                            <span class="paging-input">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                            </span>
                            
                            <?php if ($page < $total_pages): ?>
                                <a class="next-page button" href="<?php echo $base_url . '&paged=' . ($page + 1); ?>">›</a>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Audit Details Modal -->
    <div id="audit-details-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6);">
        <div style="background-color: #fefefe; margin: 20px auto; padding: 0; border: 1px solid #888; width: 90%; max-width: 1000px; border-radius: 8px; max-height: 90vh; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            <div style="background: #f8f9ff; padding: 20px; border-bottom: 1px solid #e0e3ff; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; color: #3F51B5;">Audit Entry Details</h2>
                <span style="color: #666; font-size: 24px; font-weight: bold; cursor: pointer; padding: 5px 10px; border-radius: 4px; transition: background 0.2s;" onclick="closeAuditDetails()" onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background='transparent'">&times;</span>
            </div>
            <div id="audit-details-content" style="padding: 20px; max-height: calc(90vh - 80px); overflow-y: auto;">
                Loading...
            </div>
        </div>
    </div>
    
    <script>
    function showAuditDetails(entryId) {
        document.getElementById('audit-details-modal').style.display = 'block';
        document.getElementById('audit-details-content').innerHTML = '<div style="text-align: center; padding: 40px;"><div class="spinner is-active" style="float: none; margin: 0 auto;"></div><p style="margin-top: 15px;">Loading audit details...</p></div>';
        
        // Make AJAX call to get detailed audit information
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_audit_details',
                entry_id: entryId,
                nonce: '<?php echo wp_create_nonce('audit_details_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    document.getElementById('audit-details-content').innerHTML = response.data;
                } else {
                    document.getElementById('audit-details-content').innerHTML = 
                        '<div style="padding: 20px; text-align: center; color: #d63638;">' +
                        '<h3>Error Loading Details</h3>' +
                        '<p>' + (response.data || 'Unknown error occurred') + '</p>' +
                        '</div>';
                }
            },
            error: function(xhr, status, error) {
                document.getElementById('audit-details-content').innerHTML = 
                    '<div style="padding: 20px; text-align: center; color: #d63638;">' +
                    '<h3>Connection Error</h3>' +
                    '<p>Failed to load audit details. Please try again.</p>' +
                    '<small>Error: ' + error + '</small>' +
                    '</div>';
            }
        });
    }
    
    function closeAuditDetails() {
        document.getElementById('audit-details-modal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        var modal = document.getElementById('audit-details-modal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
    </script>
    <?php
}

/**
 * AJAX handler to get detailed audit entry information
 */
function puzzlepath_get_audit_details_ajax() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions.');
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'audit_details_nonce')) {
        wp_send_json_error('Invalid nonce.');
    }
    
    $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    if ($entry_id <= 0) {
        wp_send_json_error('Invalid entry ID.');
    }
    
    global $wpdb;
    $audit_entry = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pp_booking_audit WHERE id = %d",
        $entry_id
    ), ARRAY_A);
    
    if (!$audit_entry) {
        wp_send_json_error('Audit entry not found.');
    }
    
    // Format the detailed view
    $html = puzzlepath_format_audit_details($audit_entry);
    
    wp_send_json_success($html);
}
add_action('wp_ajax_get_audit_details', 'puzzlepath_get_audit_details_ajax');

/**
 * Format audit entry details for display
 */
function puzzlepath_format_audit_details($entry) {
    $html = '<div class="audit-details">';
    
    // Header information
    $html .= '<div class="audit-header" style="margin-bottom: 20px; padding: 15px; background: #f8f9ff; border-radius: 5px; border: 1px solid #e0e3ff;">';
    $html .= '<h3 style="margin: 0 0 10px 0; color: #3F51B5;">Audit Entry #' . $entry['id'] . '</h3>';
    $html .= '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';
    
    // Left column
    $html .= '<div>';
    $html .= '<p><strong>Action:</strong> <span class="action-badge" style="background: #2271b1; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; text-transform: uppercase;">' . esc_html($entry['action']) . '</span></p>';
    $html .= '<p><strong>Date/Time:</strong> ' . date('F j, Y \a\t g:i:s A', strtotime($entry['created_at'])) . '</p>';
    if ($entry['booking_code']) {
        $html .= '<p><strong>Booking Code:</strong> <code style="background: #f1f1f1; padding: 2px 6px; border-radius: 3px;">' . esc_html($entry['booking_code']) . '</code></p>';
    }
    $html .= '</div>';
    
    // Right column
    $html .= '<div>';
    $html .= '<p><strong>User:</strong> ' . ($entry['user_login'] && $entry['user_login'] !== 'system' ? esc_html($entry['user_login']) : 'System') . '</p>';
    if ($entry['user_email'] && $entry['user_email'] !== 'system') {
        $html .= '<p><strong>Email:</strong> ' . esc_html($entry['user_email']) . '</p>';
    }
    $html .= '<p><strong>IP Address:</strong> ' . esc_html($entry['ip_address'] ?: 'N/A') . '</p>';
    $html .= '</div>';
    
    $html .= '</div>';
    $html .= '</div>';
    
    // Changed fields
    if ($entry['changed_fields']) {
        $html .= '<div class="changed-fields" style="margin-bottom: 20px;">';
        $html .= '<h4 style="margin: 0 0 10px 0; color: #d63638;">Changed Fields:</h4>';
        $html .= '<p style="background: #fff2f2; padding: 10px; border-radius: 4px; border: 1px solid #f0c2c2; margin: 0;">' . esc_html($entry['changed_fields']) . '</p>';
        $html .= '</div>';
    }
    
    // Notes
    if ($entry['notes']) {
        $html .= '<div class="notes" style="margin-bottom: 20px;">';
        $html .= '<h4 style="margin: 0 0 10px 0; color: #2271b1;">Notes:</h4>';
        $html .= '<p style="background: #f8f9ff; padding: 10px; border-radius: 4px; border: 1px solid #e0e3ff; margin: 0;">' . esc_html($entry['notes']) . '</p>';
        $html .= '</div>';
    }
    
    // Data comparison (Before/After)
    $old_data = $entry['old_data'] ? json_decode($entry['old_data'], true) : null;
    $new_data = $entry['new_data'] ? json_decode($entry['new_data'], true) : null;
    
    if ($old_data || $new_data) {
        $html .= '<div class="data-comparison" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">';
        
        // Old data column
        $html .= '<div class="old-data">';
        $html .= '<h4 style="margin: 0 0 10px 0; color: #d63638;">Before (Old Data):</h4>';
        if ($old_data) {
            $html .= '<div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; max-height: 300px; overflow-y: auto;">';
            $html .= puzzlepath_format_data_table($old_data);
            $html .= '</div>';
        } else {
            $html .= '<p style="color: #666; font-style: italic;">No previous data (new record)</p>';
        }
        $html .= '</div>';
        
        // New data column
        $html .= '<div class="new-data">';
        $html .= '<h4 style="margin: 0 0 10px 0; color: #00a32a;">After (New Data):</h4>';
        if ($new_data) {
            $html .= '<div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; max-height: 300px; overflow-y: auto;">';
            $html .= puzzlepath_format_data_table($new_data);
            $html .= '</div>';
        } else {
            $html .= '<p style="color: #666; font-style: italic;">No new data (deleted record)</p>';
        }
        $html .= '</div>';
        
        $html .= '</div>';
    }
    
    // Technical details (collapsible)
    $html .= '<details style="margin-top: 20px; border: 1px solid #ddd; border-radius: 4px; padding: 10px;">';
    $html .= '<summary style="cursor: pointer; font-weight: bold; padding: 5px 0;">Technical Details</summary>';
    $html .= '<div style="margin-top: 10px; font-family: monospace; font-size: 12px;">';
    $html .= '<p><strong>Entry ID:</strong> ' . $entry['id'] . '</p>';
    $html .= '<p><strong>Booking ID:</strong> ' . $entry['booking_id'] . '</p>';
    $html .= '<p><strong>User ID:</strong> ' . ($entry['user_id'] ?: 'N/A') . '</p>';
    if ($entry['user_agent']) {
        $html .= '<p><strong>User Agent:</strong> <br><code style="word-break: break-all;">' . esc_html($entry['user_agent']) . '</code></p>';
    }
    $html .= '<p><strong>Created At:</strong> ' . $entry['created_at'] . ' (UTC)</p>';
    $html .= '</div>';
    $html .= '</details>';
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Format data array as a readable table
 */
function puzzlepath_format_data_table($data) {
    if (empty($data) || !is_array($data)) {
        return '<p style="padding: 10px; color: #666; font-style: italic;">No data available</p>';
    }
    
    $html = '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
    
    foreach ($data as $key => $value) {
        $html .= '<tr style="border-bottom: 1px solid #eee;">';
        $html .= '<td style="padding: 8px; font-weight: bold; width: 35%; background: #f9f9f9; vertical-align: top;">' . esc_html(ucwords(str_replace('_', ' ', $key))) . '</td>';
        
        $html .= '<td style="padding: 8px; vertical-align: top;">';
        if (is_null($value)) {
            $html .= '<em style="color: #999;">NULL</em>';
        } elseif (is_bool($value)) {
            $html .= '<span style="color: ' . ($value ? '#00a32a' : '#d63638') . ';">' . ($value ? 'TRUE' : 'FALSE') . '</span>';
        } elseif (is_numeric($value)) {
            $html .= '<code>' . $value . '</code>';
        } elseif (in_array($key, ['created_at', 'updated_at', 'booking_date', 'event_date'])) {
            // Format dates nicely
            if ($value) {
                $formatted_date = date('F j, Y g:i A', strtotime($value));
                $html .= $formatted_date . ' <small style="color: #666;">(' . esc_html($value) . ')</small>';
            } else {
                $html .= '<em style="color: #999;">Not set</em>';
            }
        } elseif ($key === 'total_price' && is_numeric($value)) {
            $html .= '<strong>$' . number_format($value, 2) . '</strong>';
        } elseif ($key === 'payment_status') {
            $status_colors = ['pending' => '#dba617', 'paid' => '#00a32a', 'failed' => '#d63638', 'refunded' => '#8c8f94'];
            $color = $status_colors[$value] ?? '#666';
            $html .= '<span style="background: ' . $color . '; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; text-transform: uppercase;">' . esc_html($value) . '</span>';
        } elseif (strlen($value) > 50) {
            // Long text - truncate with expansion option
            $html .= '<div class="long-text">';
            $html .= '<div class="truncated">' . esc_html(substr($value, 0, 50)) . '... <a href="#" onclick="this.parentNode.style.display=\'none\'; this.parentNode.nextElementSibling.style.display=\'block\'; return false;" style="color: #2271b1;">[Show More]</a></div>';
            $html .= '<div class="full" style="display: none;">' . esc_html($value) . ' <a href="#" onclick="this.parentNode.style.display=\'none\'; this.parentNode.previousElementSibling.style.display=\'block\'; return false;" style="color: #2271b1;">[Show Less]</a></div>';
            $html .= '</div>';
        } else {
            $html .= esc_html($value ?: '');
        }
        $html .= '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    
    return $html;
}


/**
 * Handle non-payment form submission (deprecated, but kept for safety).
 */
function puzzlepath_handle_booking_submission() {
    // This is now handled by the Stripe payment flow.
}
add_action('init', 'puzzlepath_handle_booking_submission');

/**
 * Enqueue scripts and styles.
 */
function puzzlepath_enqueue_scripts() {
    if (is_admin()) {
        wp_enqueue_script('jquery');
        return;
    }

    global $post;
    
    // Check multiple conditions for when to load scripts
    $should_load_scripts = false;
    
    // Condition 1: Post content contains booking shortcodes
    if ($post && (has_shortcode($post->post_content, 'puzzlepath_booking_form') || 
                  has_shortcode($post->post_content, 'puzzlepath_booking_confirmation'))) {
        $should_load_scripts = true;
    }
    
    // Condition 2: Current page URL suggests it's a booking related page
    if (strpos($_SERVER['REQUEST_URI'], 'booking') !== false || 
        strpos($_SERVER['REQUEST_URI'], 'simple-booking-test') !== false ||
        strpos($_SERVER['REQUEST_URI'], 'confirmation') !== false) {
        $should_load_scripts = true;
    }
    
    // Condition 3: Query parameter indicates shortcode will be used
    if (isset($_GET['show_booking_form']) || 
        isset($_GET['booking_code']) || // Confirmation page with booking code
        (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'puzzlepath') !== false)) {
        $should_load_scripts = true;
    }
    
    if ($should_load_scripts) {
        wp_enqueue_style(
            'puzzlepath-booking-form-style',
            plugin_dir_url(__FILE__) . 'css/booking-form.css',
            array(),
            '2.7.5'
        );
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
        
        wp_enqueue_script(
            'puzzlepath-booking-form',
            plugin_dir_url(__FILE__) . 'js/booking-form.js',
            array('jquery'),
            '3.0.0', // Added confirmation page redirect
            true
        );
        
        wp_enqueue_script(
            'puzzlepath-stripe-payment',
            plugin_dir_url(__FILE__) . 'js/stripe-payment.js',
            array('jquery', 'stripe-js'),
            '3.0.0', // Added confirmation page redirect with debugging
            true
        );

        $test_mode = get_option('puzzlepath_stripe_test_mode', true);
        $publishable_key = $test_mode ? get_option('puzzlepath_stripe_publishable_key') : get_option('puzzlepath_stripe_live_publishable_key');

        // Localize script for both stripe payment AND booking form
        $localize_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'coupon_nonce' => wp_create_nonce('puzzlepath_coupon_nonce'),
            'publishable_key' => $publishable_key,
            'rest_url' => rest_url('puzzlepath/v1/'),
            'rest_nonce' => wp_create_nonce('wp_rest')
        );
        
        wp_localize_script('puzzlepath-booking-form', 'puzzlepath_data', $localize_data);
        wp_localize_script('puzzlepath-stripe-payment', 'puzzlepath_data', $localize_data);
    }
}
add_action('wp_enqueue_scripts', 'puzzlepath_enqueue_scripts');

/**
 * AJAX handler for applying a coupon.
 */
function puzzlepath_apply_coupon_callback() {
    // Debug: Log the request
    error_log('PuzzlePath Coupon AJAX called. POST data: ' . print_r($_POST, true));
    
    try {
        check_ajax_referer('puzzlepath_coupon_nonce', 'nonce');
    } catch (Exception $e) {
        error_log('PuzzlePath Coupon: Nonce verification failed: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Security verification failed.']);
        return;
    }
    
    global $wpdb;
    $coupons_table = 'wp2s_pp_coupons';
    $code = sanitize_text_field($_POST['coupon_code']);
    
    error_log('PuzzlePath Coupon: Looking for coupon code: ' . $code);
    
    $coupon = $wpdb->get_row($wpdb->prepare("SELECT * FROM $coupons_table WHERE code = %s", $code));

    if (!$coupon) {
        error_log('PuzzlePath Coupon: Coupon not found');
        wp_send_json_error(['message' => 'Invalid coupon code.']);
        return;
    }
    
    error_log('PuzzlePath Coupon: Found coupon: ' . print_r($coupon, true));
    
    if ($coupon->expires_at && strtotime($coupon->expires_at) < time()) {
        error_log('PuzzlePath Coupon: Coupon expired');
        wp_send_json_error(['message' => 'This coupon has expired.']);
        return;
    }
    if ($coupon->max_uses > 0 && $coupon->times_used >= $coupon->max_uses) {
        error_log('PuzzlePath Coupon: Coupon usage limit reached');
        wp_send_json_error(['message' => 'This coupon has reached its usage limit.']);
        return;
    }
    
    $response = [
        'discount_percent' => $coupon->discount_percent,
        'code' => $coupon->code
    ];
    
    error_log('PuzzlePath Coupon: Success response: ' . print_r($response, true));
    wp_send_json_success($response);
}
add_action('wp_ajax_apply_coupon', 'puzzlepath_apply_coupon_callback');
add_action('wp_ajax_nopriv_apply_coupon', 'puzzlepath_apply_coupon_callback');

/**
 * The main shortcode for displaying the booking form.
 */
function puzzlepath_booking_form_shortcode($atts) {
    global $wpdb;
    $events_table = 'wp2s_pp_events';
    
    $events = $wpdb->get_results("SELECT * FROM $events_table WHERE seats > 0 AND display_on_site = 1 ORDER BY event_date ASC");
    
    if (empty($events)) {
        return '<p>No events available for booking at this time.</p>';
    }
    
    ob_start();
    ?>
    <div id="puzzlepath-booking-form">
        <h3>Book Your PuzzlePath Experience</h3>
        <form id="booking-form" action="" onsubmit="return false;">
            <div class="form-group">
                <label for="event_id">Select Event:</label>
                <select name="event_id" id="event_id" required>
                    <option value="">-- Select an Event --</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo esc_attr($event->id); ?>" 
                                data-price="<?php echo esc_attr($event->price); ?>"
                                data-seats="<?php echo esc_attr($event->seats); ?>"
                                data-hunt-code="<?php echo esc_attr($event->hunt_code); ?>"
                                data-hunt-name="<?php echo esc_attr($event->hunt_name); ?>">
                            <?php 
                            // Display only: Event title, date (if available), and price
                            echo esc_html($event->title);
                            if ($event->event_date) {
                                echo ' - ' . date('F j, Y, g:i a', strtotime($event->event_date));
                            }
                            echo ' - $' . number_format($event->price, 2);
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" name="name" id="name" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" name="email" id="email" required>
            </div>
            
            <div class="form-group">
                <label for="tickets">Number of Tickets:</label>
                <input type="number" name="tickets" id="tickets" min="1" max="10" value="1" required>
            </div>
            
            <div class="form-group">
                <label for="coupon_code">Coupon Code (optional):</label>
                <input type="text" name="coupon_code" id="coupon_code">
                <button type="button" id="apply-coupon">Apply Coupon</button>
            </div>
            
            <div id="coupon-message"></div>
            
            <div class="price-summary">
                <p>Subtotal: $<span id="subtotal">0.00</span></p>
                <p id="discount-line" style="display: none;">Discount: -$<span id="discount">0.00</span></p>
                <p><strong>Total: $<span id="total">0.00</span></strong></p>
            </div>
            
            <div id="card-element">
                <!-- Stripe Elements will create form elements here -->
            </div>
            <div id="card-errors" role="alert"></div>
            
            <button type="submit" id="submit-payment">Book Now</button>
        </form>
        
        <div id="payment-success" style="display: none;">
            <h3>Booking Confirmed!</h3>
            <p>Thank you for your booking. Your booking code is: <strong id="booking-code"></strong></p>
            <p>A confirmation email has been sent to your email address.</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('puzzlepath_booking_form', 'puzzlepath_booking_form_shortcode');

/**
 * The confirmation page shortcode for displaying booking confirmation details.
 */
function puzzlepath_booking_confirmation_shortcode($atts) {
    error_log('PuzzlePath Debug: Confirmation shortcode called');
    
    try {
        // Get booking code from URL parameters
        $booking_code = isset($_GET['booking_code']) ? sanitize_text_field($_GET['booking_code']) : '';
        $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
        
        error_log('PuzzlePath Debug: Booking code: ' . $booking_code . ', Event ID: ' . $event_id);
        
        if (empty($booking_code)) {
            error_log('PuzzlePath Debug: No booking code provided');
            return '<div class="puzzlepath-error"><p>No booking code provided. Please check your confirmation email for the correct link.</p></div>';
        }
    
    global $wpdb;
    
    // Get booking details
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, e.title as event_title, e.location, e.event_date 
         FROM wp2s_pp_bookings b 
         LEFT JOIN wp2s_pp_events e ON b.event_id = e.id 
         WHERE b.booking_code = %s",
        $booking_code
    ));
    
    if (!$booking) {
        return '<div class="puzzlepath-error"><p>Booking not found. Please check your booking code and try again.</p></div>';
    }
    
    // Create app URL with booking code pre-filled
    $app_url_with_booking = 'https://app.puzzlepath.com.au?booking=' . urlencode($booking_code);
    
    // Format event date
    $formatted_date = $booking->event_date ? date('F j, Y \a\t g:i A', strtotime($booking->event_date)) : 'TBD';
    
    ob_start();
    ?>
    <div id="puzzlepath-confirmation">
        <div class="confirmation-header">
            <h1>🎉 Booking Confirmed!</h1>
            <p class="subtitle">Thank you for your booking! Your adventure awaits.</p>
        </div>
        
        <div class="booking-details-card">
            <h2>📋 Booking Details</h2>
            <div class="details-grid">
                <div class="detail-row">
                    <span class="label">Event:</span>
                    <span class="value"><?php echo esc_html($booking->event_title); ?></span>
                </div>
                <?php if ($booking->location): ?>
                <div class="detail-row">
                    <span class="label">Location:</span>
                    <span class="value"><?php echo esc_html($booking->location); ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="label">Date & Time:</span>
                    <span class="value"><?php echo esc_html($formatted_date); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Tickets:</span>
                    <span class="value"><?php echo esc_html($booking->tickets); ?></span>
                </div>
                <div class="detail-row">
                    <span class="label">Total Paid:</span>
                    <span class="value">$<?php echo number_format($booking->total_price, 2); ?></span>
                </div>
                <div class="detail-row highlight">
                    <span class="label">Booking Code:</span>
                    <span class="value booking-code"><?php echo esc_html($booking->booking_code); ?></span>
                </div>
            </div>
        </div>
        
        <div class="action-section">
            <h3>Ready to start your quest?</h3>
            <a href="<?php echo esc_url($app_url_with_booking); ?>" class="start-quest-btn" target="_blank">
                🚀 Open Your Quest
            </a>
            <p class="app-info">Click the button above or visit: <a href="<?php echo esc_url($app_url_with_booking); ?>" target="_blank">app.puzzlepath.com.au</a></p>
        </div>
        
        <div class="important-note">
            <p>💡 <strong>Important:</strong> Please save your booking code <strong><?php echo esc_html($booking->booking_code); ?></strong> - you'll need it to access your quest!</p>
        </div>
        
        <div class="email-note">
            <p>A confirmation email has been sent to <strong><?php echo esc_html($booking->customer_email); ?></strong></p>
        </div>
    </div>
    
    <style>
    #puzzlepath-confirmation {
        max-width: 600px;
        margin: 40px auto;
        padding: 20px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
    }
    
    .confirmation-header {
        text-align: center;
        margin-bottom: 40px;
    }
    
    .confirmation-header h1 {
        color: #3F51B5;
        font-size: 2.5em;
        margin-bottom: 10px;
    }
    
    .confirmation-header .subtitle {
        font-size: 1.2em;
        color: #666;
        margin: 0;
    }
    
    .booking-details-card {
        background: #f8f9ff;
        border: 1px solid #e0e3ff;
        border-radius: 12px;
        padding: 30px;
        margin-bottom: 30px;
    }
    
    .booking-details-card h2 {
        color: #3F51B5;
        font-size: 1.5em;
        margin-top: 0;
        margin-bottom: 20px;
    }
    
    .details-grid {
        display: grid;
        gap: 15px;
    }
    
    .detail-row {
        display: grid;
        grid-template-columns: 1fr 2fr;
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }
    
    .detail-row:last-child {
        border-bottom: none;
    }
    
    .detail-row.highlight {
        background: rgba(63, 81, 181, 0.1);
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #3F51B5;
        margin-top: 10px;
    }
    
    .detail-row .label {
        font-weight: 600;
        color: #666;
    }
    
    .detail-row .value {
        color: #333;
    }
    
    .booking-code {
        font-family: 'Courier New', monospace;
        font-weight: bold;
        font-size: 1.1em;
        color: #3F51B5 !important;
    }
    
    .action-section {
        text-align: center;
        margin: 40px 0;
    }
    
    .action-section h3 {
        color: #333;
        font-size: 1.3em;
        margin-bottom: 20px;
    }
    
    .start-quest-btn {
        display: inline-block;
        background: linear-gradient(135deg, #3F51B5 0%, #5C6BC0 100%);
        color: white !important;
        text-decoration: none;
        padding: 16px 32px;
        border-radius: 50px;
        font-size: 1.1em;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(63, 81, 181, 0.3);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .start-quest-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(63, 81, 181, 0.4);
        text-decoration: none;
        color: white !important;
    }
    
    .app-info {
        margin-top: 15px;
        color: #666;
        font-size: 0.9em;
    }
    
    .app-info a {
        color: #3F51B5;
        text-decoration: none;
    }
    
    .important-note {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 8px;
        padding: 20px;
        margin: 30px 0;
    }
    
    .important-note p {
        margin: 0;
        color: #856404;
        line-height: 1.5;
    }
    
    .email-note {
        text-align: center;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-top: 20px;
    }
    
    .email-note p {
        margin: 0;
        color: #6c757d;
    }
    
    .puzzlepath-error {
        max-width: 600px;
        margin: 40px auto;
        padding: 20px;
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        border-radius: 8px;
        color: #721c24;
        text-align: center;
    }
    
    @media (max-width: 768px) {
        #puzzlepath-confirmation {
            margin: 20px auto;
            padding: 15px;
        }
        
        .confirmation-header h1 {
            font-size: 2em;
        }
        
        .booking-details-card {
            padding: 20px;
        }
        
        .detail-row {
            grid-template-columns: 1fr;
            gap: 5px;
        }
        
        .detail-row .label {
            font-size: 0.9em;
        }
    }
    </style>
    <?php
    return ob_get_clean();
    
    } catch (Exception $e) {
        error_log('PuzzlePath Debug: Confirmation shortcode error: ' . $e->getMessage());
        return '<div class="puzzlepath-error"><p>Error loading confirmation page. Please try again or contact support.</p></div>';
    }
}
add_shortcode('puzzlepath_booking_confirmation', 'puzzlepath_booking_confirmation_shortcode');

/**
 * Simple test version of the confirmation shortcode to debug
 */
function puzzlepath_booking_confirmation_test($atts) {
    return '<div style="background: red; color: white; padding: 20px;">TEST: Shortcode is working! Booking code from URL: ' . (isset($_GET['booking_code']) ? sanitize_text_field($_GET['booking_code']) : 'Not found') . '</div>';
}
add_shortcode('puzzlepath_confirmation_test', 'puzzlepath_booking_confirmation_test');

/**
 * Shortcode to display upcoming adventures from database
 * Shows quests where display_on_site = 1 (Status toggle = Active) and seats > 0
 * Usage: [puzzlepath_upcoming_adventures sort="featured"]
 * 
 * Available sort options:
 * - featured (default): Featured first, then hosted events, then by date
 * - alphabetical: A-Z by title
 * - alphabetical_desc: Z-A by title
 * - price_low: Lowest price first
 * - price_high: Highest price first
 * - newest: Most recently created first
 * - oldest: Oldest quests first
 * - popular: Most bookings first
 * - difficulty: Easy to Hard
 * - location: Grouped by location
 * - quest_type: Walking first, then driving
 * - random: Random order
 * - manual: Custom admin-defined order
 */
function puzzlepath_upcoming_adventures_shortcode($atts) {
    global $wpdb;
    $events_table = 'wp2s_pp_events';
    $bookings_table = 'wp2s_pp_bookings';
    
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'sort' => 'featured', // Default sorting
        'limit' => 50, // Maximum quests to show
        'show_sort_dropdown' => 'false' // Whether to show user sorting dropdown
    ), $atts, 'puzzlepath_upcoming_adventures');
    
    // Check for URL parameter to override sort (for frontend dropdown)
    if (isset($_GET['quest_sort']) && !empty($_GET['quest_sort'])) {
        $atts['sort'] = sanitize_text_field($_GET['quest_sort']);
    }
    
    // Check if new columns exist (safety check)
    global $wpdb;
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $events_table");
    $has_sort_order = in_array('sort_order', $columns);
    $has_is_featured = in_array('is_featured', $columns);
    
    // Build query with optional columns
    $select_fields = "e.*";
    if ($has_sort_order) {
        $select_fields .= ", COALESCE(e.sort_order, 0) as sort_order";
    } else {
        $select_fields .= ", 0 as sort_order";
    }
    if ($has_is_featured) {
        $select_fields .= ", COALESCE(e.is_featured, 0) as is_featured";
    } else {
        $select_fields .= ", 0 as is_featured";
    }
    
    // Get all quests with booking statistics
    $base_query = "
        SELECT $select_fields, 
               COALESCE(booking_stats.total_bookings, 0) as total_bookings,
               COALESCE(booking_stats.recent_bookings, 0) as recent_bookings
        FROM $events_table e
        LEFT JOIN (
            SELECT 
                event_id,
                COUNT(*) as total_bookings,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent_bookings
            FROM $bookings_table
            WHERE payment_status = 'paid'
            GROUP BY event_id
        ) booking_stats ON e.id = booking_stats.event_id
        WHERE e.display_on_site = 1 AND e.seats > 0
    ";
    
    // Add sorting based on the sort parameter
    $order_clause = puzzlepath_get_sort_order($atts['sort']);
    $final_query = $base_query . $order_clause;
    
    // Apply limit if specified
    if ($atts['limit'] && is_numeric($atts['limit'])) {
        $final_query .= " LIMIT " . intval($atts['limit']);
    }
    
    $quests = $wpdb->get_results($final_query);
    
    if (empty($quests)) {
        return '<p>No upcoming adventures available at this time.</p>';
    }

    // Determine the correct booking page URL dynamically by locating the page
    // that contains the booking form shortcode. Falls back to /booking/ if not found.
    if (!function_exists('puzzlepath_get_booking_page_url')) {
        function puzzlepath_get_booking_page_url() {
            // Attempt to find a published page with the booking form shortcode
            $pages = get_pages(array('post_status' => 'publish'));
            if (!empty($pages)) {
                foreach ($pages as $p) {
                    if (isset($p->post_content) && has_shortcode($p->post_content, 'puzzlepath_booking_form')) {
                        return get_permalink($p->ID);
                    }
                }
            }
            // Fallback to a conventional slug if none found
            return site_url('/booking/');
        }
    }
    $booking_page_url = puzzlepath_get_booking_page_url();
    
    ob_start();
    ?>
    <!-- PuzzlePath Adventures v2.8.4 Updated: <?php echo date('Y-m-d H:i:s'); ?> -->
    <div class="puzzle-adventures-container">
        <?php if ($atts['show_sort_dropdown'] === 'true'): ?>
        <div class="quest-sort-controls" style="margin-bottom: 20px; text-align: center;">
            <label for="quest-sort" style="margin-right: 10px; font-weight: bold;">Sort by:</label>
            <select id="quest-sort" onchange="puzzlepathSortQuests(this.value)" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; background: white;">
                <option value="featured" <?php selected($atts['sort'], 'featured'); ?>>⭐ Featured</option>
                <option value="alphabetical" <?php selected($atts['sort'], 'alphabetical'); ?>>🔤 A-Z</option>
                <option value="alphabetical_desc" <?php selected($atts['sort'], 'alphabetical_desc'); ?>>🔤 Z-A</option>
                <option value="price_low" <?php selected($atts['sort'], 'price_low'); ?>>💰 Price: Low to High</option>
                <option value="price_high" <?php selected($atts['sort'], 'price_high'); ?>>💰 Price: High to Low</option>
                <option value="popular" <?php selected($atts['sort'], 'popular'); ?>>📈 Most Popular</option>
                <option value="newest" <?php selected($atts['sort'], 'newest'); ?>>🆕 Newest First</option>
                <option value="difficulty" <?php selected($atts['sort'], 'difficulty'); ?>>⭐ By Difficulty</option>
                <option value="location" <?php selected($atts['sort'], 'location'); ?>>📍 By Location</option>
                <option value="quest_type" <?php selected($atts['sort'], 'quest_type'); ?>>🚶‍♂️ By Quest Type</option>
            </select>
            <small style="display: block; margin-top: 5px; color: #666;">Showing <?php echo count($quests); ?> quest(s)</small>
        </div>
        <?php endif; ?>
        <div class="adventures-grid-wrap">
            <div class="adventures-grid">
            <?php foreach ($quests as $quest): ?>
                <div class="adventure-card">
                    <div class="adventure-content">
                        <h3 class="adventure-title"><?php echo esc_html($quest->title); ?></h3>
                        
                        <!-- Quest Image -->
                        <div class="adventure-image">
                            <?php 
                            $image_url = !empty($quest->quest_image_url) ? 
                                esc_url($quest->quest_image_url) : 
                                plugin_dir_url(__FILE__) . 'images/puzzlepath-logo.png';
                            ?>
                            <img src="<?php echo $image_url; ?>" alt="<?php echo esc_attr($quest->title); ?>" loading="lazy">
                        </div>
                        
                        <!-- Date/Time Info -->
                        <div class="adventure-info">
                            <div class="info-line">
                                <span class="info-icon">📅</span>
                                <?php if ($quest->hosting_type === 'hosted' && $quest->event_date): ?>
                                    <span><?php echo date('F j, Y \a\t g:i A', strtotime($quest->event_date)); ?></span>
                                <?php else: ?>
                                    <span>Anytime Quest</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Starting Location -->
                            <div class="info-line">
                                <span class="info-icon">📍</span>
                                <span><strong>Starts:</strong> <?php echo esc_html($quest->location); ?></span>
                            </div>
                            
                            <!-- Quest Type & Difficulty -->
                            <div class="info-line">
                                <?php if ($quest->is_featured): ?>
                                    <span class="featured-badge" style="background: #FFD700; color: #000; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold; margin-right: 8px;">⭐ FEATURED</span>
                                <?php endif; ?>
                                <?php if ($quest->quest_type === 'driving'): ?>
                                    <span class="info-icon">🚗</span>
                                    <span>Driving Quest</span>
                                <?php else: ?>
                                    <span class="info-icon">🚶‍♂️</span>
                                    <span>Walking Quest</span>
                                <?php endif; ?>
                                <span class="difficulty-separator"> · </span>
                                <span class="difficulty"><?php echo ucfirst(esc_html($quest->difficulty)); ?></span>
                                <?php if ($quest->difficulty === 'easy'): ?>
                                    <span class="difficulty-note"> · Family-Friendly</span>
                                <?php elseif ($quest->difficulty === 'moderate'): ?>
                                    <span class="difficulty-note"> · Fun for Adults & Families</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Quest Description -->
                            <?php if (!empty($quest->quest_description)): ?>
                            <div class="info-line">
                                <span class="info-icon">🕵️‍♀️</span>
                                <span><?php echo esc_html($quest->quest_description); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Price -->
                            <div class="info-line price-line">
                                <span class="info-icon">🎟️</span>
                                <span><strong>$<?php echo number_format($quest->price, 0); ?> person</strong></span>
                            </div>
                            
                            <!-- Book Now Button -->
                            <div class="book-now-section">
                                <a href="https://puzzlepath.com.au/book-today/?event_id=<?php echo $quest->id; ?>" class="book-now-btn">👉 Book Now</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <style>
    /* Cache bust: <?php echo time(); ?> */
    /* Force centering in Astra + Elementor */
    .elementor .elementor-widget-shortcode .elementor-widget-container{
      text-align:center !important;
    }
    .elementor .elementor-widget-shortcode .puzzle-adventures-container .adventures-grid{
      display:inline-grid !important;
      grid-template-columns:repeat(2, 500px);
      gap:24px;
      place-content:center;
    }
    .puzzle-adventures-container {
        box-sizing: border-box;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 24px;
        width: 100%;
    }

    /* Desktop-only: increase left padding to nudge grid toward center */
    @media (min-width: 1025px) {
        .puzzle-adventures-container {
            padding-left: 24px;
            padding-right: 24px;
        }
    }
    
    /* Ensure the two-card grid is centered regardless of theme/Elementor flex */
    .adventures-grid-wrap{
        display:flex !important;
        justify-content:center !important;
        align-items:flex-start;
        width:100%;
    }
    .puzzle-adventures-container .adventures-grid {
        display: grid;
        grid-template-columns: repeat(2, 500px);
        gap: 24px;
        align-items: stretch;
        justify-content: center !important; /* center grid tracks */
        justify-items: stretch;
        margin-left: auto !important;
        margin-right: auto !important;
        width: fit-content;
        max-width: 100%;
    }

    /* Fallback: if a parent imposes layout, use a flex wrapper behavior */
    .puzzle-adventures-container {
        display: block;
    }
    @supports (display: flex) {
        .puzzle-adventures-container:where(.force-center) { /* opt-in hook if ever needed */
            display: flex;
            justify-content: center;
        }
    }
    
    .adventure-card {
        background: #ffffff;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: 2px solid #ddd;
        margin-bottom: 20px;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    .adventure-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }
    
    .adventure-content {
        padding: 25px;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    .adventure-title {
        font-size: 24px;
        font-weight: bold;
        color: #2c3e50;
        margin: 0 0 20px 0;
        text-align: center;
    }
    
    .adventure-image {
        width: 100%;
        margin-bottom: 20px;
        overflow: hidden;
        border-radius: 10px;
        height: 300px;
    }
    
    .adventure-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        border-radius: 10px;
    }
    
    .adventure-info {
        line-height: 1.6;
        display: flex;
        flex-direction: column;
        flex: 1;
    }
    
    .info-line {
        display: flex;
        align-items: flex-start;
        margin-bottom: 12px;
        font-size: 16px;
    }
    
    .info-icon {
        margin-right: 10px;
        font-size: 18px;
        min-width: 25px;
    }
    
    .difficulty-separator {
        margin: 0 5px;
    }
    
    .difficulty {
        font-weight: 600;
        color: #27ae60;
    }
    
    .difficulty-note {
        color: #7f8c8d;
        font-style: italic;
    }
    
    .price-line {
        margin-top: 15px;
        font-size: 18px;
        font-weight: bold;
        color: #e74c3c;
    }
    
    .book-now-section {
        text-align: center;
        margin-top: auto;
        padding-top: 15px;
        border-top: 1px solid #ecf0f1;
    }
    
    .book-now-btn {
        display: inline-block;
        background: linear-gradient(45deg, #3498db, #2980b9);
        color: white;
        padding: 12px 25px;
        text-decoration: none;
        border-radius: 25px;
        font-weight: bold;
        font-size: 16px;
        transition: all 0.3s ease;
    }
    
    .book-now-btn:hover {
        background: linear-gradient(45deg, #2980b9, #3498db);
        transform: scale(1.05);
        text-decoration: none;
        color: white;
    }
    
    /* Responsive Design */
    
    /* Tablet view (<= 1024px) - 1 column */
    @media (max-width: 1024px) {
        .elementor .elementor-widget-shortcode .puzzle-adventures-container .adventures-grid{
          display:grid !important;
          grid-template-columns:1fr !important;
          width:100%;
          gap:20px;
        }
        .adventures-grid {
            grid-template-columns: 1fr;
            gap: 24px;
            width: 100%;
        }
        .puzzle-adventures-container {
            padding: 0 12px;
        }
    }
    
    /* Mobile view (<= 768px) - 1 column with tighter spacing */
    @media (max-width: 768px) {
        .adventures-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        .adventure-card {
            margin-bottom: 16px;
        }
        .adventure-content {
            padding: 16px;
        }
        .adventure-title {
            font-size: 20px;
        }
        .puzzle-adventures-container {
            padding: 0 8px;
        }
    }
    </style>
    
    <?php if ($atts['show_sort_dropdown'] === 'true'): ?>
    <script>
    function puzzlepathSortQuests(sortBy) {
        // Get current URL and update the sort parameter
        var url = new URL(window.location);
        url.searchParams.set('quest_sort', sortBy);
        
        // Reload the page with new sort parameter
        window.location.href = url.toString();
    }
    
    // Check for sort parameter in URL on page load
    document.addEventListener('DOMContentLoaded', function() {
        var urlParams = new URLSearchParams(window.location.search);
        var sortParam = urlParams.get('quest_sort');
        if (sortParam) {
            var sortSelect = document.getElementById('quest-sort');
            if (sortSelect) {
                sortSelect.value = sortParam;
            }
        }
    });
    </script>
    <?php endif; ?>
    
    <?php
    return ob_get_clean();
}
add_shortcode('puzzlepath_upcoming_adventures', 'puzzlepath_upcoming_adventures_shortcode');

/**
 * Force shortcode processing in content - ensures shortcodes work even if theme doesn't support them
 */
function puzzlepath_force_shortcode_processing($content) {
    // Only process on pages that might contain our shortcodes
    if (is_page() && (strpos($content, 'puzzlepath_booking_confirmation') !== false || 
                     strpos($content, 'puzzlepath_confirmation_test') !== false || 
                     strpos($content, '[puzzlepath_booking_confirmation]') !== false || 
                     strpos($content, '[puzzlepath_confirmation_test]') !== false)) {
        error_log('PuzzlePath Debug: Forcing shortcode processing on page content');
        $content = do_shortcode($content);
    }
    return $content;
}
add_filter('the_content', 'puzzlepath_force_shortcode_processing', 20);

/**
 * Get ORDER BY clause for quest sorting
 */
function puzzlepath_get_sort_order($sort_type) {
    switch ($sort_type) {
        case 'featured':
            return " ORDER BY 
                e.is_featured DESC,
                CASE WHEN e.hosting_type = 'hosted' THEN 0 ELSE 1 END,
                e.event_date ASC,
                e.sort_order ASC,
                e.title ASC";
            
        case 'alphabetical':
            return " ORDER BY e.title ASC";
            
        case 'alphabetical_desc':
            return " ORDER BY e.title DESC";
            
        case 'price_low':
            return " ORDER BY e.price ASC, e.title ASC";
            
        case 'price_high':
            return " ORDER BY e.price DESC, e.title ASC";
            
        case 'newest':
            return " ORDER BY e.created_at DESC, e.title ASC";
            
        case 'oldest':
            return " ORDER BY e.created_at ASC, e.title ASC";
            
        case 'popular':
            return " ORDER BY booking_stats.total_bookings DESC, booking_stats.recent_bookings DESC, e.title ASC";
            
        case 'difficulty':
            return " ORDER BY 
                CASE e.difficulty 
                    WHEN 'easy' THEN 1 
                    WHEN 'moderate' THEN 2 
                    WHEN 'hard' THEN 3 
                    ELSE 4 
                END ASC, e.title ASC";
            
        case 'location':
            return " ORDER BY e.location ASC, e.title ASC";
            
        case 'quest_type':
            return " ORDER BY 
                CASE e.quest_type 
                    WHEN 'walking' THEN 1 
                    WHEN 'driving' THEN 2 
                    ELSE 3 
                END ASC, e.title ASC";
            
        case 'random':
            return " ORDER BY RAND()";
            
        case 'manual':
            return " ORDER BY e.sort_order ASC, e.title ASC";
            
        case 'duration':
            return " ORDER BY 
                CASE WHEN e.duration_minutes IS NULL THEN 1 ELSE 0 END,
                e.duration_minutes ASC, 
                e.title ASC";
                
        case 'event_date':
            return " ORDER BY 
                CASE WHEN e.event_date IS NULL THEN 1 ELSE 0 END,
                e.event_date ASC, 
                e.title ASC";
            
        default: // Default to featured
            return " ORDER BY 
                e.is_featured DESC,
                CASE WHEN e.hosting_type = 'hosted' THEN 0 ELSE 1 END,
                e.event_date ASC,
                e.sort_order ASC,
                e.title ASC";
    }
}

/**
 * Debug function to check if our shortcodes are registered
 */
function puzzlepath_debug_shortcodes() {
    global $shortcode_tags;
    if (isset($_GET['booking_code']) && is_page()) {
        error_log('PuzzlePath Debug: Plugin loaded, checking shortcodes...');
        error_log('PuzzlePath Debug: Confirmation shortcode registered: ' . (isset($shortcode_tags['puzzlepath_booking_confirmation']) ? 'YES' : 'NO'));
        error_log('PuzzlePath Debug: Test shortcode registered: ' . (isset($shortcode_tags['puzzlepath_confirmation_test']) ? 'YES' : 'NO'));
        error_log('PuzzlePath Debug: Current page ID: ' . get_the_ID());
        error_log('PuzzlePath Debug: Is page: ' . (is_page() ? 'YES' : 'NO'));
    }
}
add_action('wp', 'puzzlepath_debug_shortcodes');

// ========================= EVENTS MANAGEMENT =========================

/**
 * Ensure required columns exist on the events table.
 */
function puzzlepath_ensure_events_columns($table_name) {
    global $wpdb;
    $cols = $wpdb->get_results("SHOW COLUMNS FROM $table_name", OBJECT_K);
    if (!isset($cols['quest_type'])) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN quest_type varchar(20) DEFAULT 'walking' AFTER display_on_site");
    }
    if (!isset($cols['difficulty'])) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN difficulty varchar(20) DEFAULT 'easy' AFTER quest_type");
    }
    if (!isset($cols['quest_description'])) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN quest_description text DEFAULT NULL AFTER difficulty");
    }
    if (!isset($cols['display_on_adventures_page'])) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN display_on_adventures_page tinyint(1) DEFAULT 0 AFTER quest_description");
    }
    if (!isset($cols['quest_image_url'])) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN quest_image_url varchar(500) DEFAULT NULL AFTER display_on_adventures_page");
    }
}

/**
 * Admin-post handler to save events reliably
 */
function puzzlepath_handle_event_save() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions.');
    }

    try {
        check_admin_referer('puzzlepath_save_event', 'puzzlepath_event_nonce');
    } catch (Exception $e) {
        set_transient('puzzlepath_event_save_error', 'Security check failed', 30);
        wp_safe_redirect(admin_url('admin.php?page=puzzlepath-quests&message=error'));
        exit;
    }

    global $wpdb;
    $table_name = 'wp2s_pp_events';
    // Ensure DB schema is up to date before saving
    puzzlepath_ensure_events_columns($table_name);

    $id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $title = sanitize_text_field($_POST['title'] ?? '');
    $hunt_code = !empty($_POST['hunt_code']) ? sanitize_text_field($_POST['hunt_code']) : null;
    $hunt_name = !empty($_POST['hunt_name']) ? sanitize_text_field($_POST['hunt_name']) : null;
    $location = sanitize_text_field($_POST['location'] ?? '');
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $seats = isset($_POST['seats']) ? intval($_POST['seats']) : 0;
    $hosting_type = isset($_POST['hosting_type']) && in_array($_POST['hosting_type'], ['hosted', 'self_hosted']) ? $_POST['hosting_type'] : 'hosted';
    $event_date = ($hosting_type === 'hosted' && !empty($_POST['event_date'])) ? sanitize_text_field($_POST['event_date']) : null;

    $display_on_site = isset($_POST['display_on_site']) ? 1 : 0;
    $quest_type = isset($_POST['quest_type']) && in_array($_POST['quest_type'], ['walking', 'driving']) ? $_POST['quest_type'] : 'walking';
    $difficulty = isset($_POST['difficulty']) && in_array($_POST['difficulty'], ['easy', 'moderate', 'hard']) ? $_POST['difficulty'] : 'easy';
    $quest_description = !empty($_POST['quest_description']) ? sanitize_textarea_field($_POST['quest_description']) : null;
    $display_on_adventures_page = isset($_POST['display_on_adventures_page']) ? 1 : 0;
    $quest_image_url = !empty($_POST['quest_image_url']) ? esc_url_raw($_POST['quest_image_url']) : null;

    // Auto values
    if (empty($hunt_code)) {
        $hunt_code = puzzlepath_generate_hunt_code(['title' => $title, 'location' => $location]);
    }
    if (empty($hunt_name)) {
        $hunt_name = $title . ' - ' . $location;
    }

    $data = [
        'title' => $title,
        'location' => $location,
        'price' => $price,
        'seats' => $seats,
        'hosting_type' => $hosting_type,
        'event_date' => $event_date,
        'display_on_site' => $display_on_site,
        'quest_type' => $quest_type,
        'difficulty' => $difficulty,
        'quest_description' => $quest_description,
        'display_on_adventures_page' => $display_on_adventures_page,
        'quest_image_url' => $quest_image_url,
        'hunt_code' => $hunt_code,
        'hunt_name' => $hunt_name,
        'created_at' => current_time('mysql'),
    ];

    if ($id > 0) {
        $result = $wpdb->update($table_name, $data, ['id' => $id]);
    } else {
        $result = $wpdb->insert($table_name, $data);
    }

    error_log('PuzzlePath Events (admin-post): save result=' . var_export($result, true) . ' last_error=' . $wpdb->last_error . ' data.quest_image_url=' . ($data['quest_image_url'] ?? 'NULL'));

    if ($result === false) {
        set_transient('puzzlepath_event_save_error', $wpdb->last_error ?: 'Unknown database error', 30);
        wp_safe_redirect(admin_url('admin.php?page=puzzlepath-quests&message=error'));
        exit;
    }

    wp_safe_redirect(admin_url('admin.php?page=puzzlepath-quests&message=1'));
    exit;
}
add_action('admin_post_puzzlepath_save_event', 'puzzlepath_handle_event_save');


// Include test bookings admin functionality
require_once plugin_dir_path(__FILE__) . 'admin-test-bookings.php';

// ========================= QUEST IMPORT SYSTEM =========================

/**
 * Display the Quest Import page
 */
function puzzlepath_quest_import_page() {
    
    if (!current_user_can('edit_posts')) {
        wp_die(__('You do not have sufficient permissions to access this page. Current user roles: ' . implode(', ', $user_roles)));
    }
    
    $import_result = null;
    
    // Handle JSON import submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['puzzlepath_import_nonce'])) {
        if (!wp_verify_nonce($_POST['puzzlepath_import_nonce'], 'puzzlepath_import_quest')) {
            wp_die('Security check failed.');
        }
        
        $json_data = stripslashes($_POST['quest_json']); // Don't sanitize JSON - it breaks the format
        $import_result = puzzlepath_process_quest_import($json_data);
    }
    ?>
    <div class="wrap">
        <h1>🧩 Quest Import</h1>
        <p>Import quest data from ChatGPT in JSON format. Paste the complete JSON output from the ChatGPT Quest Builder.</p>
        
        <?php if ($import_result): ?>
            <?php if ($import_result['success']): ?>
                <div class="notice notice-success">
                    <h3>✅ Import Successful!</h3>
                    <p><strong>Quest:</strong> <?php echo esc_html($import_result['quest_title']); ?> (<?php echo esc_html($import_result['hunt_code']); ?>)</p>
                    <p><strong>Clues Imported:</strong> <?php echo intval($import_result['clues_count']); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=puzzlepath-quests'); ?>">View in Quest Management</a></p>
                </div>
            <?php else: ?>
                <div class="notice notice-error">
                    <h3>❌ Import Failed</h3>
                    <p><strong>Error:</strong> <?php echo esc_html($import_result['error']); ?></p>
                    <?php if (isset($import_result['details'])): ?>
                        <details>
                            <summary>Technical Details</summary>
                            <pre><?php echo esc_html($import_result['details']); ?></pre>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="quest-import-form">
            <h2>Import Quest JSON</h2>
            <form method="post" action="">
                <?php wp_nonce_field('puzzlepath_import_quest', 'puzzlepath_import_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="quest_json">Quest JSON Data</label></th>
                        <td>
                            <textarea name="quest_json" id="quest_json" rows="20" cols="100" class="large-text code" placeholder='Paste your ChatGPT quest JSON here...

Example:
{
  "quest": {
    "title": "Sample Quest",
    "hunt_code": "SQ001",
    ...
  },
  "clues": [
    {
      "clue_order": 1,
      "title": "First Clue",
      ...
    }
  ]
}'><?php echo (isset($_POST['quest_json']) && (!$import_result || !$import_result['success'])) ? esc_textarea($_POST['quest_json']) : ''; ?></textarea>
                            <p class="description">Paste the complete JSON output from ChatGPT Quest Builder. Make sure it includes both "quest" and "clues" sections.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('🚀 Import Quest', 'primary', 'submit', false, ['style' => 'font-size: 16px; padding: 10px 20px;']); ?>
            </form>
        </div>
        
        <div class="quest-import-help" style="margin-top: 40px; padding: 20px; background: #f9f9f9; border-left: 4px solid #2271b1;">
            <h3>📋 Import Format Requirements</h3>
            <p>Your JSON must include:</p>
            <ul>
                <li><strong>quest</strong> object with: title, hunt_code, location, price, hosting_type</li>
                <li><strong>clues</strong> array with: clue_order, title, clue_text, answer_text</li>
            </ul>
            
            <h4>🎯 Supported Quest Types:</h4>
            <ul>
                <li><strong>hosted</strong> - Live events with specific dates</li>
                <li><strong>self-hosted</strong> - Customer-scheduled experiences</li>
                <li><strong>anytime</strong> - Digital/remote quests</li>
            </ul>
            
            <h4>🗺️ Clue Features:</h4>
            <ul>
                <li>GPS coordinates (latitude/longitude)</li>
                <li>Multiple answer types (exact, partial, numeric)</li>
                <li>Hints and penalty hints</li>
                <li>Image and audio URLs</li>
                <li>Geofencing radius</li>
                <li>Point values and time limits</li>
            </ul>
        </div>
    </div>
    
    <style>
    .quest-import-form textarea {
        font-family: 'Courier New', monospace;
        background: #f8f9fa;
        border: 2px dashed #ddd;
    }
    .quest-import-form textarea:focus {
        border-color: #2271b1;
        background: #fff;
    }
    .quest-import-help {
        border-radius: 6px;
    }
    .quest-import-help h3 {
        margin-top: 0;
        color: #2271b1;
    }
    .quest-import-help ul {
        margin-left: 20px;
    }
    .quest-import-help li {
        margin-bottom: 8px;
    }
    </style>
    <?php
}

/**
 * Process quest import from JSON data
 */
function puzzlepath_process_quest_import($json_data) {
    global $wpdb;
    
    try {
        // Clean up JSON data
        $json_data = trim($json_data);
        
        // Validate JSON
        $data = json_decode($json_data, true);
        $json_error = json_last_error();
        
        if ($json_error !== JSON_ERROR_NONE) {
            $error_msg = 'Invalid JSON format: ' . json_last_error_msg();
            if ($json_error === JSON_ERROR_SYNTAX) {
                $error_msg .= '. Check for missing commas, quotes, or brackets.';
            }
            return [
                'success' => false,
                'error' => $error_msg,
                'details' => 'JSON Error Code: ' . $json_error . "\nFirst 500 chars: " . substr($json_data, 0, 500)
            ];
        }
        
        // Validate required structure
        $validation_result = puzzlepath_validate_quest_json($data);
        if (!$validation_result['valid']) {
            return [
                'success' => false,
                'error' => $validation_result['error'],
                'details' => implode("\n", $validation_result['details'])
            ];
        }
        
        // Start database transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Import quest
            $quest_result = puzzlepath_import_quest_data($data['quest']);
            if (!$quest_result['success']) {
                throw new Exception('Quest import failed: ' . $quest_result['error']);
            }
            
            $event_id = $quest_result['event_id'];
            $hunt_code = $data['quest']['hunt_code'];
            
            // Import clues
            $clues_result = puzzlepath_import_clues_data($data['clues'], $event_id);
            if (!$clues_result['success']) {
                throw new Exception('Clues import failed: ' . $clues_result['error']);
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            return [
                'success' => true,
                'quest_title' => $data['quest']['title'],
                'hunt_code' => $hunt_code,
                'event_id' => $event_id,
                'clues_count' => $clues_result['clues_count']
            ];
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            throw $e;
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'details' => error_get_last() ? print_r(error_get_last(), true) : 'No additional error details'
        ];
    }
}

/**
 * Validate quest JSON structure
 */
function puzzlepath_validate_quest_json($data) {
    $errors = [];
    
    // Check main structure
    if (!isset($data['quest']) || !isset($data['clues'])) {
        return [
            'valid' => false,
            'error' => 'JSON must contain both "quest" and "clues" sections',
            'details' => ['Missing required top-level keys: quest, clues']
        ];
    }
    
    // Validate quest data
    $quest = $data['quest'];
    $required_quest_fields = ['title', 'hunt_code', 'location', 'price'];
    
    foreach ($required_quest_fields as $field) {
        if (empty($quest[$field])) {
            $errors[] = "Quest missing required field: {$field}";
        }
    }
    
    // Validate hunt_code format
    if (isset($quest['hunt_code']) && strlen($quest['hunt_code']) > 10) {
        $errors[] = 'Hunt code must be 10 characters or less';
    }
    
    // Validate hosting_type
    if (isset($quest['hosting_type']) && !in_array($quest['hosting_type'], ['hosted', 'self-hosted', 'anytime'])) {
        $errors[] = 'Invalid hosting_type. Must be: hosted, self-hosted, or anytime';
    }
    
    // Validate clues
    if (!is_array($data['clues']) || empty($data['clues'])) {
        $errors[] = 'Clues must be a non-empty array';
    } else {
        foreach ($data['clues'] as $i => $clue) {
            $clue_errors = puzzlepath_validate_clue_data($clue, $i + 1);
            $errors = array_merge($errors, $clue_errors);
        }
        
        // Check clue order sequence
        $orders = array_column($data['clues'], 'clue_order');
        $expected_orders = range(1, count($data['clues']));
        if (array_diff($expected_orders, $orders)) {
            $errors[] = 'Clue orders must be sequential starting from 1';
        }
    }
    
    return [
        'valid' => empty($errors),
        'error' => empty($errors) ? '' : 'Validation failed',
        'details' => $errors
    ];
}

/**
 * Validate individual clue data
 */
function puzzlepath_validate_clue_data($clue, $clue_number) {
    $errors = [];
    $required_fields = ['clue_order', 'clue_text', 'answer_text'];
    
    foreach ($required_fields as $field) {
        if (empty($clue[$field])) {
            $errors[] = "Clue #{$clue_number} missing required field: {$field}";
        }
    }
    
    // Validate answer_type
    if (isset($clue['answer_type']) && !in_array($clue['answer_type'], ['exact', 'partial', 'numeric', 'multiple_choice'])) {
        $errors[] = "Clue #{$clue_number} has invalid answer_type. Must be: exact, partial, numeric, or multiple_choice";
    }
    
    // Validate coordinates if provided
    if (isset($clue['latitude']) && (abs($clue['latitude']) > 90)) {
        $errors[] = "Clue #{$clue_number} has invalid latitude (must be between -90 and 90)";
    }
    
    if (isset($clue['longitude']) && (abs($clue['longitude']) > 180)) {
        $errors[] = "Clue #{$clue_number} has invalid longitude (must be between -180 and 180)";
    }
    
    return $errors;
}

/**
 * Import quest data into pp_events table only
 */
function puzzlepath_import_quest_data($quest_data) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    
    try {
        // Check if hunt_code already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$events_table} WHERE hunt_code = %s",
            $quest_data['hunt_code']
        ));
        
        if ($existing) {
            return [
                'success' => false,
                'error' => 'Hunt code "' . $quest_data['hunt_code'] . '" already exists. Please use a different hunt code.'
            ];
        }
        
        // Map JSON data to pp_events table structure
        $db_data = [
            'title' => sanitize_text_field($quest_data['title']),
            'hunt_code' => sanitize_text_field($quest_data['hunt_code']),
            'hunt_name' => isset($quest_data['hunt_name']) ? sanitize_text_field($quest_data['hunt_name']) : sanitize_text_field($quest_data['title']),
            'hosting_type' => isset($quest_data['hosting_type']) ? sanitize_text_field($quest_data['hosting_type']) : 'self-hosted',
            'event_date' => !empty($quest_data['event_date']) ? $quest_data['event_date'] : null,
            'location' => sanitize_text_field($quest_data['location']),
            'price' => floatval($quest_data['price']),
            'seats' => isset($quest_data['seats']) ? intval($quest_data['seats']) : 50,
            'duration_minutes' => isset($quest_data['duration_minutes']) ? intval($quest_data['duration_minutes']) : null,
            'medal_image_url' => isset($quest_data['medal_image_url']) ? esc_url_raw($quest_data['medal_image_url']) : null,
            'display_on_site' => isset($quest_data['display_on_site']) ? intval($quest_data['display_on_site']) : 0
        ];
        
        $result = $wpdb->insert($events_table, $db_data);
        
        if ($result === false) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $wpdb->last_error
            ];
        }
        
        return [
            'success' => true,
            'event_id' => $wpdb->insert_id
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Exception during quest import: ' . $e->getMessage()
        ];
    }
}

/**
 * Import clues data into pp_clues table
 */
function puzzlepath_import_clues_data($clues_data, $event_id) {
    global $wpdb;
    $clues_table = $wpdb->prefix . 'pp_clues';
    
    try {
        $imported_count = 0;
        
        foreach ($clues_data as $clue) {
            // Map JSON data to pp_clues table structure
            $db_data = [
                'hunt_id' => intval($event_id), // This is the event ID from pp_events
                'clue_order' => intval($clue['clue_order']),
                'title' => isset($clue['title']) ? sanitize_text_field($clue['title']) : '',
                'clue_text' => sanitize_textarea_field($clue['clue_text']),
                'task_description' => isset($clue['task_description']) ? sanitize_textarea_field($clue['task_description']) : null,
                'hint_text' => isset($clue['hint_text']) ? sanitize_textarea_field($clue['hint_text']) : null,
                'answer' => sanitize_text_field($clue['answer_text']), // Your table uses 'answer' not 'answer_text'
                'latitude' => isset($clue['latitude']) ? floatval($clue['latitude']) : null,
                'longitude' => isset($clue['longitude']) ? floatval($clue['longitude']) : null,
                'geofence_radius' => isset($clue['geofence_radius']) ? intval($clue['geofence_radius']) : null,
                'image_url' => isset($clue['image_url']) ? esc_url_raw($clue['image_url']) : null,
                'is_active' => isset($clue['is_active']) ? intval($clue['is_active']) : 1
            ];
            
            // Handle alternative answers - store as hint_text if not already used
            if (isset($clue['alternative_answers']) && !empty($clue['alternative_answers']) && empty($db_data['hint_text'])) {
                $db_data['hint_text'] = 'Alternative answers: ' . implode(', ', $clue['alternative_answers']);
            } elseif (isset($clue['alternative_answers']) && !empty($clue['alternative_answers'])) {
                // Append to existing hint_text
                $db_data['hint_text'] .= ' | Alternative answers: ' . implode(', ', $clue['alternative_answers']);
            }
            
            // Handle penalty hint - append to hint_text if exists
            if (isset($clue['penalty_hint']) && !empty($clue['penalty_hint'])) {
                if (empty($db_data['hint_text'])) {
                    $db_data['hint_text'] = 'Penalty hint: ' . $clue['penalty_hint'];
                } else {
                    $db_data['hint_text'] .= ' | Penalty hint: ' . $clue['penalty_hint'];
                }
            }
            
            $result = $wpdb->insert($clues_table, $db_data);
            
            if ($result === false) {
                throw new Exception('Failed to insert clue #' . $clue['clue_order'] . ': ' . $wpdb->last_error);
            }
            
            $imported_count++;
        }
        
        return [
            'success' => true,
            'clues_count' => $imported_count
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// ========================= COUPONS MANAGEMENT =========================

/**
 * Display the main page for managing coupons.
 */
function puzzlepath_coupons_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pp_coupons';

    // Handle form submissions for adding/editing coupons
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['puzzlepath_coupon_nonce'])) {
        if (!wp_verify_nonce($_POST['puzzlepath_coupon_nonce'], 'puzzlepath_save_coupon')) {
            wp_die('Security check failed.');
        }

        $id = isset($_POST['coupon_id']) ? intval($_POST['coupon_id']) : 0;
        $code = sanitize_text_field($_POST['code']);
        $discount_percent = intval($_POST['discount_percent']);
        $max_uses = intval($_POST['max_uses']);
        $expires_at = !empty($_POST['expires_at']) ? sanitize_text_field($_POST['expires_at']) : null;

        $data = [
            'code' => $code,
            'discount_percent' => $discount_percent,
            'max_uses' => $max_uses,
            'expires_at' => $expires_at,
        ];

        if ($id > 0) {
            $wpdb->update($table_name, $data, ['id' => $id]);
        } else {
            $wpdb->insert($table_name, $data);
        }

        wp_redirect(admin_url('admin.php?page=puzzlepath-coupons&message=1'));
        exit;
    }

    // Handle coupon deletion
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['coupon_id'])) {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'puzzlepath_delete_coupon_' . $_GET['coupon_id'])) {
            wp_die('Security check failed.');
        }
        $id = intval($_GET['coupon_id']);
        $wpdb->delete($table_name, ['id' => $id]);
        wp_redirect(admin_url('admin.php?page=puzzlepath-coupons&message=2'));
        exit;
    }

    $edit_coupon = null;
    if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['coupon_id'])) {
        $edit_coupon = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['coupon_id'])));
    }
    ?>
    <div class="wrap">
        <h1>Coupons</h1>

        <?php if (isset($_GET['message'])): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo $_GET['message'] == 1 ? 'Coupon saved successfully.' : 'Coupon deleted successfully.'; ?></p>
            </div>
        <?php endif; ?>

        <h2><?php echo $edit_coupon ? 'Edit Coupon' : 'Add New Coupon'; ?></h2>
        <form method="post" action="">
            <input type="hidden" name="coupon_id" value="<?php echo $edit_coupon ? esc_attr($edit_coupon->id) : ''; ?>">
            <?php wp_nonce_field('puzzlepath_save_coupon', 'puzzlepath_coupon_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="code">Coupon Code</label></th>
                    <td><input type="text" name="code" id="code" value="<?php echo $edit_coupon ? esc_attr($edit_coupon->code) : ''; ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="discount_percent">Discount (%)</label></th>
                    <td><input type="number" name="discount_percent" id="discount_percent" value="<?php echo $edit_coupon ? esc_attr($edit_coupon->discount_percent) : ''; ?>" class="small-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="max_uses">Max Uses</label></th>
                    <td><input type="number" name="max_uses" id="max_uses" value="<?php echo $edit_coupon ? esc_attr($edit_coupon->max_uses) : '0'; ?>" class="small-text">
                    <p class="description">Set to 0 for unlimited uses.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="expires_at">Expires At</label></th>
                    <td><input type="datetime-local" name="expires_at" id="expires_at" value="<?php echo $edit_coupon && $edit_coupon->expires_at ? date('Y-m-d\TH:i', strtotime($edit_coupon->expires_at)) : ''; ?>"></td>
                </tr>
            </table>
            <?php submit_button($edit_coupon ? 'Update Coupon' : 'Add Coupon'); ?>
        </form>

        <hr/>
        
        <h2>All Coupons</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Discount</th>
                    <th>Usage</th>
                    <th>Expires At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $coupons = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
                foreach ($coupons as $coupon) {
                    echo '<tr>';
                    echo '<td>' . esc_html($coupon->code) . '</td>';
                    echo '<td>' . esc_html($coupon->discount_percent) . '%</td>';
                    echo '<td>' . esc_html($coupon->times_used) . ' / ' . ($coupon->max_uses > 0 ? esc_html($coupon->max_uses) : '∞') . '</td>';
                    echo '<td>' . ($coupon->expires_at ? date('F j, Y, g:i a', strtotime($coupon->expires_at)) : 'Never') . '</td>';
                    echo '<td>';
                    echo '<a href="' . admin_url('admin.php?page=puzzlepath-coupons&action=edit&coupon_id=' . $coupon->id) . '">Edit</a> | ';
                    $delete_nonce = wp_create_nonce('puzzlepath_delete_coupon_' . $coupon->id);
                    echo '<a href="' . admin_url('admin.php?page=puzzlepath-coupons&action=delete&coupon_id=' . $coupon->id . '&_wpnonce=' . $delete_nonce) . '" onclick="return confirm(\'Are you sure you want to delete this coupon?\')">Delete</a>';
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}


// ========================= INCLUDES =========================

// Load settings functions
// Settings functionality has been merged into this main file
// require_once plugin_dir_path(__FILE__) . 'includes/settings.php';

// ========================= STRIPE INTEGRATION =========================

// Check if Stripe library is available (only load if composer installed)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';

    class PuzzlePath_Stripe_Integration {
        private static $instance;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            add_action('admin_init', array($this, 'register_stripe_settings'));
            add_action('rest_api_init', array($this, 'register_rest_endpoints'));
        }

        public function register_stripe_settings() {
            register_setting('puzzlepath_stripe_settings', 'puzzlepath_stripe_test_mode');
            register_setting('puzzlepath_stripe_settings', 'puzzlepath_stripe_publishable_key');
            register_setting('puzzlepath_stripe_settings', 'puzzlepath_stripe_secret_key');
            register_setting('puzzlepath_stripe_settings', 'puzzlepath_stripe_live_publishable_key');
            register_setting('puzzlepath_stripe_settings', 'puzzlepath_stripe_live_secret_key');
            register_setting('puzzlepath_stripe_settings', 'puzzlepath_stripe_webhook_secret');
        }

        public function register_rest_endpoints() {
            register_rest_route('puzzlepath/v1', '/payment/create-intent', array(
                'methods' => 'POST',
                'callback' => array($this, 'create_payment_intent'),
                'permission_callback' => '__return_true'
            ));

            register_rest_route('puzzlepath/v1', '/stripe-webhook', array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_webhook'),
                'permission_callback' => '__return_true'
            ));

            register_rest_route('puzzlepath/v1', '/booking-status', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_booking_status'),
                'permission_callback' => '__return_true'
            ));
            
            // Unified App endpoints
            register_rest_route('puzzlepath/v1', '/bookings', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_unified_bookings'),
                'permission_callback' => '__return_true'
            ));
            
            register_rest_route('puzzlepath/v1', '/booking/(?P<code>[a-zA-Z0-9\-]+)', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_booking_by_code'),
                'permission_callback' => '__return_true'
            ));
            
            register_rest_route('puzzlepath/v1', '/hunts', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_hunts_list'),
                'permission_callback' => '__return_true'
            ));
        }

        private function get_stripe_keys() {
            $test_mode = get_option('puzzlepath_stripe_test_mode', true);
            if ($test_mode) {
                return [
                    'publishable' => get_option('puzzlepath_stripe_publishable_key'),
                    'secret' => get_option('puzzlepath_stripe_secret_key'),
                ];
            } else {
                return [
                    'publishable' => get_option('puzzlepath_stripe_live_publishable_key'),
                    'secret' => get_option('puzzlepath_stripe_live_secret_key'),
                ];
            }
        }

        public function create_payment_intent($request) {
            global $wpdb;
            $params = $request->get_json_params();

            if (empty($params['event_id']) || empty($params['tickets'])) {
                return new WP_Error('missing_params', 'Missing event_id or tickets', array('status' => 400));
            }

            $event_id = intval($params['event_id']);
            $tickets = intval($params['tickets']);
            $coupon_code = isset($params['coupon_code']) ? sanitize_text_field($params['coupon_code']) : null;

            $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp2s_pp_events WHERE id = %d", $event_id));

            if (!$event || $event->seats < $tickets) {
                return new WP_Error('invalid_event', 'Event not found or not enough seats.', array('status' => 400));
            }

            $total_price = $event->price * $tickets;
            $coupon_id = null;

            if ($coupon_code) {
                $coupon = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp2s_pp_coupons WHERE code = %s AND (expires_at IS NULL OR expires_at > NOW()) AND (max_uses = 0 OR times_used < max_uses)", $coupon_code));
                if ($coupon) {
                    $total_price = $total_price - ($total_price * ($coupon->discount_percent / 100));
                    $coupon_id = $coupon->id;
                }
            }

            // Handle free bookings (100% discount or $0 total)
            if ($total_price <= 0) {
                return $this->process_free_booking($event_id, $tickets, $params, $event, $coupon_id);
            }

            // Get Stripe keys and validate them before proceeding
            $stripe_keys = $this->get_stripe_keys();
            if (empty($stripe_keys['secret'])) {
                return new WP_Error('stripe_config_error', 'Stripe secret key not configured. Please check your Stripe settings.', array('status' => 500));
            }
            
            // Set the API key for Stripe
            \Stripe\Stripe::setApiKey($stripe_keys['secret']);

            try {
                // Generate unique booking code with hunt integration
                $booking_code = $this->generate_unique_booking_code($event);
                
                // Create pending booking
                $booking_data = [
                    'event_id' => $event_id,
                    'hunt_id' => $event->hunt_code,
                    'customer_name' => sanitize_text_field($params['name']),
                    'customer_email' => sanitize_email($params['email']),
                    'tickets' => $tickets,
                    'total_price' => $total_price,
                    'coupon_id' => $coupon_id,
                    'payment_status' => 'pending',
                    'booking_code' => $booking_code,
                    'booking_date' => current_time('mysql')
                ];
                
                $wpdb->insert("wp2s_pp_bookings", $booking_data);
                $booking_id = $wpdb->insert_id;
                
                // Log booking creation
                PuzzlePath_Audit_Logger::log_booking_created(
                    $booking_id, 
                    $booking_data, 
                    'Booking created via Stripe payment intent'
                );

                $payment_intent = \Stripe\PaymentIntent::create([
                    'amount' => $total_price * 100,
                    'currency' => 'aud',
                    'metadata' => [
                        'booking_id' => $booking_id,
                        'event_id' => $event_id,
                        'tickets' => $tickets,
                    ],
                ]);

                $wpdb->update("wp2s_pp_bookings", 
                    ['stripe_payment_intent_id' => $payment_intent->id],
                    ['id' => $booking_id]
                );

                return new WP_REST_Response([
                    'clientSecret' => $payment_intent->client_secret,
                    'bookingId' => $booking_id,
                    'bookingCode' => $booking_code
                ], 200);

            } catch (Exception $e) {
                return new WP_Error('stripe_error', $e->getMessage(), array('status' => 500));
            }
        }

        public function handle_webhook($request) {
            $payload = $request->get_body();
            $sig_header = $request->get_header('stripe_signature');
            $endpoint_secret = get_option('puzzlepath_stripe_webhook_secret');
            $event = null;

            try {
                $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
            } catch(\UnexpectedValueException $e) {
                return new WP_Error('invalid_payload', 'Invalid payload', array('status' => 400));
            } catch(\Stripe\Exception\SignatureVerificationException $e) {
                return new WP_Error('invalid_signature', 'Invalid signature', array('status' => 400));
            }

            if ($event->type == 'charge.succeeded') {
                $payment_intent = $event->data->object;
                $booking_code = $this->fulfill_booking($payment_intent->id);
                return new WP_REST_Response(array('status' => 'success', 'booking_code' => $booking_code), 200);
            }

            return new WP_REST_Response(array('status' => 'success'), 200);
        }
        
        private function fulfill_booking($payment_intent_id) {
            global $wpdb;
            $bookings_table = 'wp2s_pp_bookings';
            $events_table = 'wp2s_pp_events';
            $coupons_table = 'wp2s_pp_coupons';

            $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bookings_table WHERE stripe_payment_intent_id = %s", $payment_intent_id));

            if ($booking && $booking->payment_status === 'pending') {
                // Log payment status change
                PuzzlePath_Audit_Logger::log_payment_status_changed(
                    $booking->id,
                    'pending',
                    'paid',
                    'Payment successful via Stripe webhook'
                );
                
                $wpdb->update($bookings_table, 
                    ['payment_status' => 'paid'], 
                    ['id' => $booking->id]
                );

                $wpdb->query($wpdb->prepare("UPDATE $events_table SET seats = seats - %d WHERE id = %d", $booking->tickets, $booking->event_id));

                if ($booking->coupon_id) {
                    $wpdb->query($wpdb->prepare("UPDATE $coupons_table SET times_used = times_used + 1 WHERE id = %d", $booking->coupon_id));
                }

                $this->send_confirmation_email($booking, $booking->booking_code);

                return $booking->booking_code;
            }
            return null;
        }

        private function send_confirmation_email($booking, $booking_code) {
            $to = $booking->customer_email;
            $subject = 'Your PuzzlePath Booking Confirmation';
            global $wpdb;
            $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pp_events WHERE id = %d", $booking->event_id));
            
            // Get HTML template (fallback to default if not set) 
            $template = get_option('puzzlepath_email_template', puzzlepath_get_default_html_template());
            
            // Get plugin URL for logo with fallback
            $plugin_url = plugin_dir_url(__FILE__);
            $logo_url = $plugin_url . 'images/puzzlepath-logo.png';
            
            // Check if logo exists, use fallback if not
            $logo_path = plugin_dir_path(__FILE__) . 'images/puzzlepath-logo.png';
            if (!file_exists($logo_path)) {
                $logo_url = 'https://via.placeholder.com/150x60/3F51B5/ffffff?text=PuzzlePath';
                error_log('PuzzlePath Email: Logo not found, using placeholder: ' . $logo_path);
            }
            
            // Format event date
            $formatted_date = $event && $event->event_date ? date('F j, Y \a\t g:i A', strtotime($event->event_date)) : 'TBD';
            
            // Create app URL with booking code pre-filled
            $app_url_with_booking = 'https://app.puzzlepath.com.au?booking=' . urlencode($booking_code);
            
            // Replace placeholders
            $html_message = str_replace(
                ['{name}', '{event_title}', '{event_date}', '{price}', '{booking_code}', '{logo_url}', '{app_url}'],
                [
                    $booking->customer_name, 
                    $event ? $event->title : 'Your Event', 
                    $formatted_date,
                    '$' . number_format($booking->total_price, 2), 
                    $booking_code,
                    $logo_url,
                    $app_url_with_booking
                ],
                $template
            );
            
            // Create a unique filter name to avoid conflicts
            $filter_name = 'puzzlepath_mail_content_type_' . uniqid();
            
            // Set content type to HTML with a unique filter
            add_filter('wp_mail_content_type', function() { return 'text/html'; }, 10, 0);
            
            // Set headers for HTML email (remove Content-Type since we're using the filter)
            $headers = [];
            $headers[] = 'From: PuzzlePath <bookings@puzzlepath.com.au>';
            
            // Debug logging (only if WP_DEBUG is enabled)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PuzzlePath Email Debug: Attempting to send HTML email to ' . $to);
                error_log('PuzzlePath Email Debug: Subject: ' . $subject);
                error_log('PuzzlePath Email Debug: Logo URL: ' . $logo_url);
                error_log('PuzzlePath Email Debug: App URL: https://app.puzzlepath.com.au');
                
                add_action('wp_mail_failed', function($wp_error) {
                    error_log('PuzzlePath Email Debug: wp_mail failed: ' . $wp_error->get_error_message());
                });
            }
            
            // Add PHPMailer action to set plain-text alternative and ensure HTML content type
            $phpmailer_callback = function($phpmailer) use ($html_message) {
                // Ensure we're sending HTML
                $phpmailer->isHTML(true);
                $phpmailer->CharSet = 'UTF-8';
                
                // Generate plain-text version for AltBody
                $plain_text = wp_strip_all_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html_message));
                $plain_text = html_entity_decode($plain_text, ENT_QUOTES, 'UTF-8');
                $phpmailer->AltBody = $plain_text;
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('PuzzlePath Email Debug: PHPMailer ContentType set to: ' . $phpmailer->ContentType);
                }
            };
            
            add_action('phpmailer_init', $phpmailer_callback);
            
            $mail_result = wp_mail($to, $subject, $html_message, $headers);
            
            // Clean up filters and actions
            remove_filter('wp_mail_content_type', function() { return 'text/html'; }, 10);
            remove_action('phpmailer_init', $phpmailer_callback);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if ($mail_result) {
                    error_log('PuzzlePath Email Debug: HTML email sent successfully');
                } else {
                    error_log('PuzzlePath Email Debug: HTML email failed to send');
                }
            }
            
            return $mail_result;
        }
        

        /**
         * Process free booking (100% discount or $0 total)
         */
        private function process_free_booking($event_id, $tickets, $params, $event, $coupon_id) {
            global $wpdb;
            
            try {
                // Generate unique booking code
                $booking_code = $this->generate_unique_booking_code($event);
                
                // Create completed booking (no payment required)
                $booking_data = [
                    'event_id' => $event_id,
                    'hunt_id' => $event->hunt_code,
                    'customer_name' => sanitize_text_field($params['name']),
                    'customer_email' => sanitize_email($params['email']),
                    'tickets' => $tickets,
                    'total_price' => 0.00, // Free booking
                    'coupon_id' => $coupon_id,
                    'payment_status' => 'paid', // Mark as paid since booking is complete (free)
                    'booking_code' => $booking_code,
                    'booking_date' => current_time('mysql')
                ];
                
                $wpdb->insert("{$wpdb->prefix}pp_bookings", $booking_data);
                $booking_id = $wpdb->insert_id;
                
                // Log free booking creation
                PuzzlePath_Audit_Logger::log_booking_created(
                    $booking_id, 
                    $booking_data, 
                    'Free booking created (100% discount or $0 total)'
                );

                // Update event seats immediately (no payment processing delay)
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}pp_events SET seats = seats - %d WHERE id = %d",
                    $tickets,
                    $event_id
                ));

                // Update coupon usage if applicable
                if ($coupon_id) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->prefix}pp_coupons SET times_used = times_used + 1 WHERE id = %d",
                        $coupon_id
                    ));
                }

                // Send confirmation email
                $booking = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}pp_bookings WHERE id = %d",
                    $booking_id
                ));
                
                // Always log email attempts for free bookings (debugging)
                error_log('PuzzlePath Free Booking: Attempting to send confirmation email to ' . $booking->customer_email);
                error_log('PuzzlePath Free Booking: Booking code: ' . $booking_code);
                
                $email_result = $this->send_confirmation_email($booking, $booking_code);
                
                // Log email result
                if ($email_result) {
                    error_log('PuzzlePath Free Booking: Confirmation email sent successfully');
                } else {
                    error_log('PuzzlePath Free Booking: Confirmation email FAILED to send');
                }

                // Return success response with special flag for free booking
                return new WP_REST_Response([
                    'success' => true,
                    'free_booking' => true,
                    'bookingId' => $booking_id,
                    'bookingCode' => $booking_code,
                    'message' => 'Booking confirmed successfully!'
                ], 200);

            } catch (Exception $e) {
                return new WP_Error('free_booking_error', $e->getMessage(), array('status' => 500));
            }
        }

        /**
         * Generate unique booking code with hunt code integration
         */
        private function generate_unique_booking_code($event = null) {
            global $wpdb;
            $bookings_table = $wpdb->prefix . 'pp_bookings';
            
            do {
                if ($event && !empty($event->hunt_code)) {
                    // Hunt-specific format: HuntCode-YYYYMMDD-XXXX
                    $date_part = date('Ymd');
                    $random_part = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $code = strtoupper($event->hunt_code) . '-' . $date_part . '-' . $random_part;
                } else {
                    // Default PP format
                    $code = 'PP-' . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
                }
                $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $bookings_table WHERE booking_code = %s", $code));
            } while ($exists > 0);
            
            return $code;
        }

        public function get_booking_status($request) {
            global $wpdb;
            $payment_intent_id = $request->get_param('payment_intent');
            if (!$payment_intent_id) {
                return new WP_Error('missing_param', 'Missing payment_intent parameter', array('status' => 400));
            }
            $booking = $wpdb->get_row($wpdb->prepare("SELECT booking_code, payment_status FROM {$wpdb->prefix}pp_bookings WHERE stripe_payment_intent_id = %s", $payment_intent_id));
            if (!$booking) {
                return new WP_REST_Response(['status' => 'pending'], 200);
            }
            if ($booking->payment_status === 'paid' && $booking->booking_code) {
                return new WP_REST_Response(['status' => 'paid', 'booking_code' => $booking->booking_code], 200);
            }
            return new WP_REST_Response(['status' => $booking->payment_status], 200);
        }

        /**
         * Get unified bookings data for the unified app
         */
        public function get_unified_bookings($request) {
            global $wpdb;
            $unified_view = $wpdb->prefix . 'pp_bookings_unified';
            
            // Get query parameters
            $hunt_id = $request->get_param('hunt_id');
            $status = $request->get_param('status'); 
            $date_from = $request->get_param('date_from');
            $date_to = $request->get_param('date_to');
            $limit = intval($request->get_param('limit')) ?: 50;
            $offset = intval($request->get_param('offset')) ?: 0;
            $search = $request->get_param('search');
            
            // Build WHERE clauses
            $where_clauses = [];
            $where_values = [];
            
            if ($hunt_id) {
                $where_clauses[] = 'hunt_id = %s';
                $where_values[] = $hunt_id;
            }
            
            if ($status) {
                $where_clauses[] = 'status = %s';
                $where_values[] = $status;
            }
            
            if ($date_from) {
                $where_clauses[] = 'DATE(created_at) >= %s';
                $where_values[] = $date_from;
            }
            
            if ($date_to) {
                $where_clauses[] = 'DATE(created_at) <= %s';
                $where_values[] = $date_to;
            }
            
            if ($search) {
                $where_clauses[] = '(customer_name LIKE %s OR customer_email LIKE %s OR booking_code LIKE %s)';
                $search_term = '%' . $wpdb->esc_like($search) . '%';
                $where_values[] = $search_term;
                $where_values[] = $search_term;
                $where_values[] = $search_term;
            }
            
            $where_sql = '';
            if (!empty($where_clauses)) {
                $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
            }
            
            // Get total count
            $count_query = "SELECT COUNT(*) FROM $unified_view $where_sql";
            $total_count = empty($where_values) ? 
                $wpdb->get_var($count_query) : 
                $wpdb->get_var($wpdb->prepare($count_query, $where_values));
            
            // Get bookings
            $query = "SELECT * FROM $unified_view $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
            $query_values = array_merge($where_values, [$limit, $offset]);
            $bookings = $wpdb->get_results($wpdb->prepare($query, $query_values));
            
            // Format response
            $formatted_bookings = [];
            foreach ($bookings as $booking) {
                $formatted_bookings[] = [
                    'id' => intval($booking->id),
                    'booking_code' => $booking->booking_code,
                    'hunt_id' => $booking->hunt_id,
                    'hunt_code' => $booking->hunt_code,
                    'hunt_name' => $booking->hunt_name,
                    'event_title' => $booking->event_title,
                    'location' => $booking->location,
                    'event_date' => $booking->event_date,
                    'customer_name' => $booking->customer_name,
                    'customer_email' => $booking->customer_email,
                    'participant_names' => $booking->participant_names,
                    'participant_count' => intval($booking->participant_count),
                    'total_price' => floatval($booking->total_price),
                    'booking_date' => $booking->booking_date,
                    'created_at' => $booking->created_at,
                    'payment_status' => $booking->payment_status,
                    'status' => $booking->status
                ];
            }
            
            return new WP_REST_Response([
                'bookings' => $formatted_bookings,
                'total_count' => intval($total_count),
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total_count
            ], 200);
        }
        
        /**
         * Get a specific booking by booking code
         */
        public function get_booking_by_code($request) {
            global $wpdb;
            $unified_view = $wpdb->prefix . 'pp_bookings_unified';
            $booking_code = $request->get_param('code');
            
            if (!$booking_code) {
                return new WP_Error('missing_code', 'Missing booking code parameter', array('status' => 400));
            }
            
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $unified_view WHERE booking_code = %s",
                $booking_code
            ));
            
            if (!$booking) {
                return new WP_Error('booking_not_found', 'Booking not found', array('status' => 404));
            }
            
            $formatted_booking = [
                'id' => intval($booking->id),
                'booking_code' => $booking->booking_code,
                'hunt_id' => $booking->hunt_id,
                'hunt_code' => $booking->hunt_code,
                'hunt_name' => $booking->hunt_name,
                'event_title' => $booking->event_title,
                'location' => $booking->location,
                'event_date' => $booking->event_date,
                'customer_name' => $booking->customer_name,
                'customer_email' => $booking->customer_email,
                'participant_names' => $booking->participant_names,
                'participant_count' => intval($booking->participant_count),
                'total_price' => floatval($booking->total_price),
                'booking_date' => $booking->booking_date,
                'created_at' => $booking->created_at,
                'payment_status' => $booking->payment_status,
                'status' => $booking->status
            ];
            
            return new WP_REST_Response(['booking' => $formatted_booking], 200);
        }
        
        /**
         * Get list of available hunts/events for the unified app
         */
        public function get_hunts_list($request) {
            global $wpdb;
            $events_table = 'wp2s_pp_events';
            
            // Get query parameters
            $active_only = $request->get_param('active_only') !== 'false'; // Default to true
            $hosting_type = $request->get_param('hosting_type');
            
            // Build WHERE clauses
            $where_clauses = [];
            $where_values = [];
            
            if ($active_only) {
                $where_clauses[] = 'seats > 0';
            }
            
            if ($hosting_type) {
                $where_clauses[] = 'hosting_type = %s';
                $where_values[] = $hosting_type;
            }
            
            // Only include events with hunt codes for unified app
            $where_clauses[] = 'hunt_code IS NOT NULL AND hunt_code != \'\''; 
            
            // Only include events that should be displayed on the site
            $where_clauses[] = 'display_on_site = 1';
            
            $where_sql = '';
            if (!empty($where_clauses)) {
                $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
            }
            
            $query = "SELECT id, title, hunt_code, hunt_name, hosting_type, event_date, location, price, seats, created_at FROM $events_table $where_sql ORDER BY event_date ASC, created_at DESC";
            
            $events = empty($where_values) ? 
                $wpdb->get_results($query) : 
                $wpdb->get_results($wpdb->prepare($query, $where_values));
            
            $formatted_hunts = [];
            foreach ($events as $event) {
                $formatted_hunts[] = [
                    'id' => intval($event->id),
                    'title' => $event->title,
                    'hunt_code' => $event->hunt_code,
                    'hunt_name' => $event->hunt_name,
                    'hosting_type' => $event->hosting_type,
                    'event_date' => $event->event_date,
                    'location' => $event->location,
                    'price' => floatval($event->price),
                    'seats_available' => intval($event->seats),
                    'created_at' => $event->created_at
                ];
            }
            
            return new WP_REST_Response([
                'hunts' => $formatted_hunts,
                'total_count' => count($formatted_hunts)
            ], 200);
        }

        public function stripe_settings_page_content() {
            // Check for save message
            $message = '';
            if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
                $message = '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
            }
            
            $test_mode = get_option('puzzlepath_stripe_test_mode', true);
            $test_pub_key = get_option('puzzlepath_stripe_publishable_key', '');
            $test_secret_key = get_option('puzzlepath_stripe_secret_key', '');
            $live_pub_key = get_option('puzzlepath_stripe_live_publishable_key', '');
            $live_secret_key = get_option('puzzlepath_stripe_live_secret_key', '');
            
            ?>
            <div class="wrap">
                <h1>🔒 Stripe Payment Settings</h1>
                
                <?php echo $message; ?>
                
                <!-- Current Status -->
                <div class="notice notice-info">
                    <p><strong>Current Mode:</strong> 
                        <?php if ($test_mode): ?>
                            🧪 <span style="color: #d63638;">TEST MODE</span> - No real money will be processed
                        <?php else: ?>
                            💰 <span style="color: #00a32a;">LIVE MODE</span> - Real payments will be processed!
                        <?php endif; ?>
                    </p>
                </div>
                
                <form method="post" action="options.php">
                    <?php settings_fields('puzzlepath_stripe_settings'); ?>
                    
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Mode Toggle</th>
                            <td>
                                <div class="stripe-mode-toggle">
                                    <input type="checkbox" id="stripe-mode-toggle" name="puzzlepath_stripe_test_mode" value="1" 
                                           <?php checked($test_mode); ?> style="display: none;">
                                    <label for="stripe-mode-toggle" class="toggle-switch">
                                        <span class="toggle-slider"></span>
                                        <span class="toggle-label-left">LIVE</span>
                                        <span class="toggle-label-right">TEST</span>
                                    </label>
                                </div>
                                <p class="description">Toggle between test mode (safe for development) and live mode (real payments)</p>
                            </td>
                        </tr>
                    </table>
                    
                    <h2>🔑 API Keys</h2>
                    <p>Get your API keys from your <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard</a></p>
                    
                    <table class="form-table">
                        <!-- Test Keys Section -->
                        <tr valign="top" class="test-keys-section">
                            <th scope="row" colspan="2"><h3 style="margin: 0; color: #d63638;">🧪 Test Keys (for development)</h3></th>
                        </tr>
                        <tr valign="top" class="test-keys-section">
                            <th scope="row">Test Publishable Key</th>
                            <td>
                                <input type="text" name="puzzlepath_stripe_publishable_key" 
                                       value="<?php echo esc_attr($test_pub_key); ?>" 
                                       class="regular-text" 
                                       placeholder="pk_test_..." 
                                       style="font-family: monospace;"/>
                                <p class="description">Starts with <code>pk_test_</code> - Safe to use in frontend code</p>
                            </td>
                        </tr>
                        
                        <tr valign="top" class="test-keys-section">
                            <th scope="row">Test Secret Key</th>
                            <td>
                                <input type="password" name="puzzlepath_stripe_secret_key" 
                                       value="<?php echo esc_attr($test_secret_key); ?>" 
                                       class="regular-text" 
                                       placeholder="sk_test_..." 
                                       style="font-family: monospace;"/>
                                <p class="description">Starts with <code>sk_test_</code> - Keep this secure! Used on the server only.</p>
                            </td>
                        </tr>
                        
                        <!-- Live Keys Section -->
                        <tr valign="top" class="live-keys-section" style="border-top: 2px solid #ddd;">
                            <th scope="row" colspan="2"><h3 style="margin: 20px 0 10px 0; color: #00a32a;">💰 Live Keys (for real payments)</h3></th>
                        </tr>
                        <tr valign="top" class="live-keys-section">
                            <th scope="row">Live Publishable Key</th>
                            <td>
                                <input type="text" name="puzzlepath_stripe_live_publishable_key" 
                                       value="<?php echo esc_attr($live_pub_key); ?>" 
                                       class="regular-text" 
                                       placeholder="pk_live_..." 
                                       style="font-family: monospace;"/>
                                <p class="description">Starts with <code>pk_live_</code> - Used for live payments</p>
                            </td>
                        </tr>
                        
                        <tr valign="top" class="live-keys-section">
                            <th scope="row">Live Secret Key</th>
                            <td>
                                <input type="password" name="puzzlepath_stripe_live_secret_key" 
                                       value="<?php echo esc_attr($live_secret_key); ?>" 
                                       class="regular-text" 
                                       placeholder="sk_live_..." 
                                       style="font-family: monospace;"/>
                                <p class="description">Starts with <code>sk_live_</code> - EXTREMELY SENSITIVE! Keep secure!</p>
                            </td>
                        </tr>
                    </table>
                    
                    <h2>🎯 Key Status Check</h2>
                    <table class="form-table">
                        <tr>
                            <th>Current Configuration:</th>
                            <td>
                                <?php 
                                $current_pub = $test_mode ? $test_pub_key : $live_pub_key;
                                $current_secret = $test_mode ? $test_secret_key : $live_secret_key;
                                ?>
                                <p><strong>Publishable Key:</strong> 
                                    <?php if ($current_pub): ?>
                                        ✅ Configured (<?php echo substr($current_pub, 0, 12); ?>...)
                                    <?php else: ?>
                                        ❌ Not configured
                                    <?php endif; ?>
                                </p>
                                <p><strong>Secret Key:</strong> 
                                    <?php if ($current_secret): ?>
                                        ✅ Configured (<?php echo substr($current_secret, 0, 12); ?>...)
                                    <?php else: ?>
                                        ❌ Not configured
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('💾 Save Stripe Settings', 'primary', 'submit', false); ?>
                </form>
                
                <hr>
                
                <h2>🇦🇺 Australian Test Credit Cards</h2>
                <table class="widefat striped">
                    <thead>
                        <tr><th>Purpose</th><th>Card Number</th><th>Card Type</th><th>Result</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Successful Payment</td><td><code>4000000560000004</code></td><td>Visa (AU)</td><td>✅ Approved</td></tr>
                        <tr><td>Successful Payment</td><td><code>5200828282828210</code></td><td>Mastercard (AU)</td><td>✅ Approved</td></tr>
                        <tr><td>Authentication Required</td><td><code>4000002500003155</code></td><td>Visa (AU)</td><td>🔐 3D Secure</td></tr>
                        <tr><td>Declined Payment</td><td><code>4000000000000002</code></td><td>Visa</td><td>❌ Generic Decline</td></tr>
                        <tr><td>Insufficient Funds</td><td><code>4000000000009995</code></td><td>Visa</td><td>💳 Insufficient Funds</td></tr>
                        <tr><td>Processing Error</td><td><code>4000000000000119</code></td><td>Visa</td><td>⚠️ Processing Error</td></tr>
                    </tbody>
                </table>
                <p><em>Use any future expiry date (like 12/34) and any 3-digit CVC for testing. Australian cards use AUD currency by default.</em></p>
            </div>
            
            <style>
            .form-table th { width: 200px; }
            .regular-text { width: 400px; }
            .notice h3 { margin-top: 0; }
            
            /* Toggle Switch Styles */
            .stripe-mode-toggle {
                margin-bottom: 10px;
            }
            
            .toggle-switch {
                position: relative;
                display: inline-block;
                width: 120px;
                height: 34px;
                cursor: pointer;
                background-color: #00a32a;
                border-radius: 34px;
                transition: background-color 0.3s;
            }
            
            .toggle-switch:hover {
                opacity: 0.8;
            }
            
            .toggle-slider {
                position: absolute;
                top: 2px;
                left: 2px;
                width: 30px;
                height: 30px;
                background-color: white;
                border-radius: 50%;
                transition: transform 0.3s;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            
            .toggle-label-left,
            .toggle-label-right {
                position: absolute;
                top: 50%;
                transform: translateY(-50%);
                font-size: 12px;
                font-weight: bold;
                color: white;
                pointer-events: none;
            }
            
            .toggle-label-left {
                left: 10px;
            }
            
            .toggle-label-right {
                right: 10px;
            }
            
            /* When checkbox is checked (test mode) */
            #stripe-mode-toggle:checked + .toggle-switch {
                background-color: #d63638;
            }
            
            #stripe-mode-toggle:checked + .toggle-switch .toggle-slider {
                transform: translateX(86px);
            }
            </style>
            
            <script>
            jQuery(document).ready(function($) {
                // Update mode display when toggle is clicked
                $('#stripe-mode-toggle').on('change', function() {
                    var isTestMode = $(this).is(':checked');
                    var modeText = isTestMode ? 
                        '🧪 <span style="color: #d63638;">TEST MODE</span> - No real money will be processed' : 
                        '💰 <span style="color: #00a32a;">LIVE MODE</span> - Real payments will be processed!';
                    
                    $('.notice-info p').html('<strong>Current Mode:</strong> ' + modeText);
                });
            });
            </script>
            <?php
        }
    }

    // Initialize the Stripe integration
    PuzzlePath_Stripe_Integration::get_instance();
} else {
    // Show admin notice if Stripe library not installed
    add_action('admin_notices', function() {
        echo '<div class="error"><p>PuzzlePath Booking: The Stripe PHP library is not installed. Please run "composer install" in the plugin directory.</p></div>';
    });
}

// ========================= BOOKINGS MANAGEMENT =========================

/**
 * Display the comprehensive bookings management page.
 */
function puzzlepath_bookings_page() {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    $events_table = $wpdb->prefix . 'pp_events';
    $coupons_table = $wpdb->prefix . 'pp_coupons';
    
    // Handle actions
    if (isset($_GET['action']) && isset($_GET['booking_id'])) {
        $booking_id = intval($_GET['booking_id']);
        
        switch ($_GET['action']) {
            case 'refund':
                if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'puzzlepath_refund_' . $booking_id)) {
                    $result = puzzlepath_process_refund($booking_id);
                    if ($result['success']) {
                        wp_redirect(admin_url('admin.php?page=puzzlepath-bookings&message=refunded'));
                    } else {
                        wp_redirect(admin_url('admin.php?page=puzzlepath-bookings&error=' . urlencode($result['error'])));
                    }
                    exit;
                }
                break;
                
            case 'resend_email':
                if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'puzzlepath_resend_' . $booking_id)) {
                    puzzlepath_resend_confirmation_email($booking_id);
                    wp_redirect(admin_url('admin.php?page=puzzlepath-bookings&message=email_sent'));
                    exit;
                }
                break;
        }
    }
    
    // Handle bulk actions
    if (isset($_POST['action']) && $_POST['action'] !== '-1' && isset($_POST['booking_ids'])) {
        check_admin_referer('bulk-bookings');
        $action = sanitize_text_field($_POST['action']);
        $booking_ids = array_map('intval', $_POST['booking_ids']);
        
        switch ($action) {
            case 'bulk_refund':
                $refunded_count = 0;
                foreach ($booking_ids as $booking_id) {
                    $result = puzzlepath_process_refund($booking_id);
                    if ($result['success']) $refunded_count++;
                }
                wp_redirect(admin_url('admin.php?page=puzzlepath-bookings&message=bulk_refunded&count=' . $refunded_count));
                exit;
                break;
                
            case 'bulk_email':
                foreach ($booking_ids as $booking_id) {
                    puzzlepath_resend_confirmation_email($booking_id);
                }
                wp_redirect(admin_url('admin.php?page=puzzlepath-bookings&message=bulk_emails_sent&count=' . count($booking_ids)));
                exit;
                break;
                
            case 'bulk_delete':
                // Comprehensive audit logging before deletion
                PuzzlePath_Audit_Logger::log_bulk_deletion(
                    $booking_ids, 
                    'Bulk deletion performed by admin user - ' . count($booking_ids) . ' bookings deleted'
                );
                
                // Perform the actual deletion
                $placeholders = implode(',', array_fill(0, count($booking_ids), '%d'));
                $deleted_count = $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$bookings_table} WHERE id IN ($placeholders)",
                    $booking_ids
                ));
                
                wp_redirect(admin_url('admin.php?page=puzzlepath-bookings&message=bulk_deleted&count=' . $deleted_count));
                exit;
                break;
        }
    }
    
    // Handle CSV export
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        puzzlepath_export_bookings_csv();
        exit;
    }
    
    // Get filter parameters
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $event_filter = isset($_GET['event_id']) ? intval($_GET['event_id']) : '';
    $hunt_filter = isset($_GET['hunt_code']) ? sanitize_text_field($_GET['hunt_code']) : '';
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    // Get sorting parameters
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
    $order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';
    
    // Validate orderby parameter
    $allowed_columns = [
        'id' => 'b.id',
        'booking_code' => 'b.booking_code',
        'customer_name' => 'b.customer_name',
        'customer_email' => 'b.customer_email',
        'event_title' => 'e.title',
        'hunt_name' => 'e.hunt_name',
        'tickets' => 'b.tickets',
        'total_price' => 'b.total_price',
        'payment_status' => 'b.payment_status',
        'created_at' => 'b.created_at',
        'event_date' => 'e.event_date'
    ];
    
    $order_column = isset($allowed_columns[$orderby]) ? $allowed_columns[$orderby] : 'b.created_at';
    
    // Build query
    $where_clauses = [];
    $where_values = [];
    
    if ($status_filter) {
        $where_clauses[] = 'b.payment_status = %s';
        $where_values[] = $status_filter;
    }
    
    if ($event_filter) {
        $where_clauses[] = 'b.event_id = %d';
        $where_values[] = $event_filter;
    }
    
    if ($hunt_filter) {
        $where_clauses[] = 'e.hunt_code = %s';
        $where_values[] = $hunt_filter;
    }
    
    if ($date_from) {
        $where_clauses[] = 'DATE(b.created_at) >= %s';
        $where_values[] = $date_from;
    }
    
    if ($date_to) {
        $where_clauses[] = 'DATE(b.created_at) <= %s';
        $where_values[] = $date_to;
    }
    
    if ($search) {
        $where_clauses[] = '(b.customer_name LIKE %s OR b.customer_email LIKE %s OR b.booking_code LIKE %s)';
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $where_values[] = $search_term;
        $where_values[] = $search_term;
        $where_values[] = $search_term;
    }
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    // Pagination
    $items_per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM $bookings_table b 
                   LEFT JOIN $events_table e ON b.event_id = e.id 
                   LEFT JOIN $coupons_table c ON b.coupon_id = c.id 
                   $where_sql";
    
    if (!empty($where_values)) {
        $total_items = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
    } else {
        $total_items = $wpdb->get_var($count_query);
    }
    
    $total_pages = ceil($total_items / $items_per_page);
    
    // Get bookings
    $query = "SELECT b.*, e.title as event_title, e.hunt_code, e.hunt_name, e.event_date, c.code as coupon_code
             FROM $bookings_table b 
             LEFT JOIN $events_table e ON b.event_id = e.id
             LEFT JOIN $coupons_table c ON b.coupon_id = c.id
             $where_sql
             ORDER BY $order_column $order
             LIMIT %d OFFSET %d";
    
    $query_values = array_merge($where_values, [$items_per_page, $offset]);
    $bookings = $wpdb->get_results($wpdb->prepare($query, $query_values));
    
    // Get summary statistics
    $stats = puzzlepath_get_booking_stats($where_sql, $where_values);
    
    ?>
    <div class="wrap">
        <h1>Bookings Management</h1>
        
        <?php if (isset($_GET['message'])): ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php 
                    switch ($_GET['message']) {
                        case 'refunded':
                            echo 'Booking refunded successfully.';
                            break;
                        case 'email_sent':
                            echo 'Confirmation email sent successfully.';
                            break;
                        case 'bulk_refunded':
                            $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
                            echo sprintf('%d booking(s) refunded successfully.', $count);
                            break;
                        case 'bulk_emails_sent':
                            $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
                            echo sprintf('Confirmation emails sent for %d booking(s).', $count);
                            break;
                        case 'bulk_deleted':
                            $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
                            echo sprintf('%d booking(s) permanently deleted. All changes have been logged in the audit trail.', $count);
                            break;
                        case 'migration_complete':
                            echo 'Payment status migration completed successfully!';
                            break;
                    }
                    ?>
                </p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html(urldecode($_GET['error'])); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Summary Statistics -->
        <div class="booking-stats" style="display: flex; gap: 20px; margin-bottom: 20px;">
            <div class="stat-box" style="background: #fff; padding: 15px; border-left: 4px solid #2271b1; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0; color: #2271b1;">Total Bookings</h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;"><?php echo $stats['total_bookings']; ?></p>
            </div>
            <div class="stat-box" style="background: #fff; padding: 15px; border-left: 4px solid #00a32a; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0; color: #00a32a;">Total Revenue</h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;">$<?php echo number_format($stats['total_revenue'], 2); ?></p>
            </div>
            <div class="stat-box" style="background: #fff; padding: 15px; border-left: 4px solid #dba617; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0; color: #dba617;">Pending Payments</h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;"><?php echo $stats['pending_payments']; ?></p>
            </div>
            <div class="stat-box" style="background: #fff; padding: 15px; border-left: 4px solid #d63638; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0; color: #d63638;">Total Participants</h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;"><?php echo $stats['total_participants']; ?></p>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get" style="display: inline-flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="puzzlepath-bookings">
                    
                    <select name="status">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pending</option>
                        <option value="paid" <?php selected($status_filter, 'paid'); ?>>Paid</option>
                        <option value="failed" <?php selected($status_filter, 'failed'); ?>>Failed</option>
                        <option value="refunded" <?php selected($status_filter, 'refunded'); ?>>Refunded</option>
                    </select>
                    
                    <select name="event_id">
                        <option value="">All Events</option>
                        <?php
                        $events = $wpdb->get_results("SELECT id, title FROM $events_table ORDER BY title");
                        foreach ($events as $event) {
                            echo '<option value="' . $event->id . '"' . selected($event_filter, $event->id, false) . '>' . esc_html($event->title) . '</option>';
                        }
                        ?>
                    </select>
                    
                    <select name="hunt_code">
                        <option value="">All Hunts</option>
                        <?php
                        $hunts = $wpdb->get_results("SELECT DISTINCT hunt_code, hunt_name FROM $events_table WHERE hunt_code IS NOT NULL AND hunt_code != '' ORDER BY hunt_code");
                        foreach ($hunts as $hunt) {
                            $label = $hunt->hunt_name ? $hunt->hunt_name . ' (' . $hunt->hunt_code . ')' : $hunt->hunt_code;
                            echo '<option value="' . esc_attr($hunt->hunt_code) . '"' . selected($hunt_filter, $hunt->hunt_code, false) . '>' . esc_html($label) . '</option>';
                        }
                        ?>
                    </select>
                    
                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="From Date">
                    <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="To Date">
                    
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search bookings...">
                    
                    <input type="submit" class="button" value="Filter">
                    
                    <?php if ($status_filter || $event_filter || $hunt_filter || $date_from || $date_to || $search): ?>
                        <a href="<?php echo admin_url('admin.php?page=puzzlepath-bookings'); ?>" class="button">Clear Filters</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="alignright actions">
                <a href="<?php echo admin_url('admin.php?page=puzzlepath-bookings&export=csv&' . http_build_query($_GET)); ?>" class="button">Export CSV</a>
            </div>
        </div>
        
        <!-- Bookings Table -->
        <form method="post">
            <?php wp_nonce_field('bulk-bookings'); ?>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="action">
                        <option value="-1">Bulk Actions</option>
                        <option value="bulk_refund">Refund Selected</option>
                        <option value="bulk_email">Resend Confirmation Emails</option>
                        <option value="bulk_delete" style="color: #d63638;">🗑️ Delete Selected (PERMANENT)</option>
                    </select>
                    <input type="submit" class="button action" value="Apply">
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></td>
                        <th class="manage-column sortable <?php echo ($orderby === 'booking_code') ? ($order === 'ASC' ? 'asc' : 'desc') : 'desc'; ?>">
                            <a href="<?php echo add_query_arg(array('orderby' => 'booking_code', 'order' => ($orderby === 'booking_code' && $order === 'ASC') ? 'desc' : 'asc')); ?>">
                                <span>Booking Code</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo ($orderby === 'customer_name') ? ($order === 'ASC' ? 'asc' : 'desc') : 'desc'; ?>">
                            <a href="<?php echo add_query_arg(array('orderby' => 'customer_name', 'order' => ($orderby === 'customer_name' && $order === 'ASC') ? 'desc' : 'asc')); ?>">
                                <span>Customer</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo ($orderby === 'event_title') ? ($order === 'ASC' ? 'asc' : 'desc') : 'desc'; ?>">
                            <a href="<?php echo add_query_arg(array('orderby' => 'event_title', 'order' => ($orderby === 'event_title' && $order === 'ASC') ? 'desc' : 'asc')); ?>">
                                <span>Event</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo ($orderby === 'hunt_name') ? ($order === 'ASC' ? 'asc' : 'desc') : 'desc'; ?>">
                            <a href="<?php echo add_query_arg(array('orderby' => 'hunt_name', 'order' => ($orderby === 'hunt_name' && $order === 'ASC') ? 'desc' : 'asc')); ?>">
                                <span>Hunt</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo ($orderby === 'tickets') ? ($order === 'ASC' ? 'asc' : 'desc') : 'desc'; ?>">
                            <a href="<?php echo add_query_arg(array('orderby' => 'tickets', 'order' => ($orderby === 'tickets' && $order === 'ASC') ? 'desc' : 'asc')); ?>">
                                <span>Tickets</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo ($orderby === 'total_price') ? ($order === 'ASC' ? 'asc' : 'desc') : 'desc'; ?>">
                            <a href="<?php echo add_query_arg(array('orderby' => 'total_price', 'order' => ($orderby === 'total_price' && $order === 'ASC') ? 'desc' : 'asc')); ?>">
                                <span>Total</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo ($orderby === 'payment_status') ? ($order === 'ASC' ? 'asc' : 'desc') : 'desc'; ?>">
                            <a href="<?php echo add_query_arg(array('orderby' => 'payment_status', 'order' => ($orderby === 'payment_status' && $order === 'ASC') ? 'desc' : 'asc')); ?>">
                                <span>Status</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo ($orderby === 'created_at') ? ($order === 'ASC' ? 'asc' : 'desc') : 'desc'; ?>">
                            <a href="<?php echo add_query_arg(array('orderby' => 'created_at', 'order' => ($orderby === 'created_at' && $order === 'ASC') ? 'desc' : 'asc')); ?>">
                                <span>Date</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 20px;">No bookings found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <th class="check-column"><input type="checkbox" name="booking_ids[]" value="<?php echo $booking->id; ?>"></th>
                                <td><strong><?php echo esc_html($booking->booking_code); ?></strong></td>
                                <td>
                                    <strong><?php echo esc_html($booking->customer_name); ?></strong><br>
                                    <small><?php echo esc_html($booking->customer_email); ?></small>
                                </td>
                                <td>
                                    <?php echo esc_html($booking->event_title); ?><br>
                                    <?php if ($booking->event_date): ?>
                                        <small><?php echo date('M j, Y g:i A', strtotime($booking->event_date)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($booking->hunt_name) {
                                        echo esc_html($booking->hunt_name);
                                        if ($booking->hunt_code) {
                                            echo '<br><small>(' . esc_html($booking->hunt_code) . ')</small>';
                                        }
                                    } elseif ($booking->hunt_code) {
                                        echo esc_html($booking->hunt_code);
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td><?php echo $booking->tickets; ?></td>
                                <td>$<?php echo number_format($booking->total_price, 2); ?>
                                    <?php if ($booking->coupon_code): ?>
                                        <br><small>Coupon: <?php echo esc_html($booking->coupon_code); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $status_colors = [
                                        'pending' => '#dba617',
                                        'paid' => '#00a32a', 
                                        'failed' => '#d63638',
                                        'refunded' => '#8c8f94'
                                    ];
                                    $status_color = isset($status_colors[$booking->payment_status]) ? $status_colors[$booking->payment_status] : '#8c8f94';
                                    ?>
                                    <span style="background: <?php echo $status_color; ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
                                        <?php echo esc_html($booking->payment_status); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($booking->created_at)); ?></td>
                                <td>
                                    <a href="#" onclick="showBookingDetails(<?php echo $booking->id; ?>); return false;" title="View Details">👁️</a>
                                    <a href="#" onclick="editBookingDetails(<?php echo $booking->id; ?>); return false;" title="Edit Booking">✏️</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=puzzlepath-bookings&action=resend_email&booking_id=' . $booking->id), 'puzzlepath_resend_' . $booking->id); ?>" title="Resend Email">📧</a>
                                    <?php if ($booking->payment_status === 'paid'): ?>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=puzzlepath-bookings&action=refund&booking_id=' . $booking->id), 'puzzlepath_refund_' . $booking->id); ?>" 
                                           onclick="return confirm('Are you sure you want to refund this booking?');" title="Refund">💸</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo $total_items; ?> items</span>
                        <?php
                        $page_links = paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        echo $page_links;
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Booking Details Modal -->
    <div id="booking-details-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;">
        <div style="background-color: #fefefe; margin: 2% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 700px; border-radius: 5px; max-height: 90vh; position: relative;">
            <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px;" onclick="closeBookingDetails()">&times;</span>
            <div id="booking-details-content" style="margin-top: 10px;">
                Loading...
            </div>
        </div>
    </div>
    
    <!-- Edit Booking Modal -->
    <div id="edit-booking-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;">
        <div style="background-color: #fefefe; margin: 1% auto 2% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 700px; border-radius: 5px; max-height: 95vh; overflow-y: auto; position: relative;">
            <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px; z-index: 10;" onclick="closeEditBooking()">&times;</span>
            <div id="edit-booking-content" style="margin-top: 10px; padding-right: 10px;">
                Loading...
            </div>
        </div>
    </div>
    
    <style>
    .manage-column.sortable a {
        text-decoration: none;
        color: inherit;
        display: block;
        position: relative;
    }
    .manage-column.sortable a:hover {
        color: #0073aa;
    }
    .manage-column.sortable .sorting-indicator {
        float: right;
        width: 0;
        height: 0;
        margin-top: 8px;
        margin-right: 7px;
    }
    .manage-column.sortable.asc .sorting-indicator {
        border-left: 4px solid transparent;
        border-right: 4px solid transparent;
        border-bottom: 8px solid #444;
    }
    .manage-column.sortable.desc .sorting-indicator {
        border-left: 4px solid transparent;
        border-right: 4px solid transparent;
        border-top: 8px solid #444;
    }
    .manage-column.sortable:not(.asc):not(.desc) .sorting-indicator {
        opacity: 0.3;
        border-left: 4px solid transparent;
        border-right: 4px solid transparent;
        border-top: 8px solid #444;
    }
    .manage-column.sortable:not(.asc):not(.desc):hover .sorting-indicator {
        opacity: 0.8;
    }
    
    /* Compact styling for edit modal */
    #edit-booking-modal .form-table th {
        padding: 10px 10px 10px 0;
        width: 150px;
    }
    #edit-booking-modal .form-table td {
        padding: 10px 10px 10px 0;
    }
    #edit-booking-modal .form-table tr {
        border-bottom: 1px solid #f1f1f1;
    }
    #edit-booking-modal .regular-text, #edit-booking-modal .large-text {
        width: 100%;
        max-width: 300px;
    }
    #edit-booking-modal .small-text {
        width: 80px;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Select all functionality
        $('#cb-select-all-1').on('click', function() {
            $('input[name="booking_ids[]"]').prop('checked', this.checked);
        });
        
        // Bulk actions confirmation
        $('form').on('submit', function(e) {
            var action = $('select[name="action"]').val();
            var selectedItems = $('input[name="booking_ids[]"]:checked');
            
            if (action === 'bulk_delete' && selectedItems.length > 0) {
                var message = 'WARNING: You are about to PERMANENTLY DELETE ' + selectedItems.length + ' booking(s).\n\n' +
                            'This action CANNOT be undone!\n\n' +
                            'All booking data will be permanently removed, but a complete audit trail will be preserved.\n\n' +
                            'Are you absolutely sure you want to continue?';
                
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            }
            
            if ((action === 'bulk_refund' || action === 'bulk_email') && selectedItems.length > 0) {
                var actionName = action === 'bulk_refund' ? 'refund' : 'resend emails for';
                if (!confirm('Are you sure you want to ' + actionName + ' ' + selectedItems.length + ' booking(s)?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    });
    
    function showBookingDetails(bookingId) {
        document.getElementById('booking-details-modal').style.display = 'block';
        document.getElementById('booking-details-content').innerHTML = 'Loading...';
        
        // AJAX call to get booking details
        jQuery.post(ajaxurl, {
            action: 'get_booking_details',
            booking_id: bookingId,
            nonce: '<?php echo wp_create_nonce('booking_details_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                document.getElementById('booking-details-content').innerHTML = response.data;
            } else {
                document.getElementById('booking-details-content').innerHTML = 'Error loading booking details.';
            }
        });
    }
    
    function closeBookingDetails() {
        document.getElementById('booking-details-modal').style.display = 'none';
    }
    
    function editBookingDetails(bookingId) {
        document.getElementById('edit-booking-modal').style.display = 'block';
        document.getElementById('edit-booking-content').innerHTML = 'Loading...';
        
        // AJAX call to get edit booking form
        jQuery.post(ajaxurl, {
            action: 'get_edit_booking_form',
            booking_id: bookingId,
            nonce: '<?php echo wp_create_nonce('edit_booking_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                document.getElementById('edit-booking-content').innerHTML = response.data;
            } else {
                document.getElementById('edit-booking-content').innerHTML = 'Error loading booking form.';
            }
        });
    }
    
    function closeEditBooking() {
        document.getElementById('edit-booking-modal').style.display = 'none';
    }
    
    function saveBookingChanges(bookingId) {
        var form = document.getElementById('edit-booking-form');
        var formData = new FormData(form);
        formData.append('action', 'save_booking_changes');
        formData.append('booking_id', bookingId);
        formData.append('nonce', '<?php echo wp_create_nonce('save_booking_nonce'); ?>');
        
        // Show loading
        document.getElementById('edit-booking-save-btn').disabled = true;
        document.getElementById('edit-booking-save-btn').textContent = 'Saving...';
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Booking updated successfully!');
                    closeEditBooking();
                    location.reload(); // Refresh the page to show changes
                } else {
                    alert('Error: ' + response.data);
                    document.getElementById('edit-booking-save-btn').disabled = false;
                    document.getElementById('edit-booking-save-btn').textContent = 'Save Changes';
                }
            },
            error: function() {
                alert('An error occurred while saving changes.');
                document.getElementById('edit-booking-save-btn').disabled = false;
                document.getElementById('edit-booking-save-btn').textContent = 'Save Changes';
            }
        });
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        var modal = document.getElementById('booking-details-modal');
        var editModal = document.getElementById('edit-booking-modal');
        if (event.target == modal) {
            modal.style.display = 'none';
        } else if (event.target == editModal) {
            editModal.style.display = 'none';
        }
    }
    </script>
    <?php
}

/**
 * Get booking statistics
 */
function puzzlepath_get_booking_stats($where_sql = '', $where_values = []) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    $events_table = $wpdb->prefix . 'pp_events';
    
    $base_query = "FROM $bookings_table b LEFT JOIN $events_table e ON b.event_id = e.id $where_sql";
    
    $stats = [];
    
    // Total bookings
    $query = "SELECT COUNT(*) $base_query";
    $stats['total_bookings'] = empty($where_values) ? $wpdb->get_var($query) : $wpdb->get_var($wpdb->prepare($query, $where_values));
    
    // Total revenue (paid bookings only)
    $revenue_where = $where_sql ? $where_sql . " AND b.payment_status = 'paid'" : "WHERE b.payment_status = 'paid'";
    $query = "SELECT COALESCE(SUM(b.total_price), 0) FROM $bookings_table b LEFT JOIN $events_table e ON b.event_id = e.id $revenue_where";
    $stats['total_revenue'] = empty($where_values) ? $wpdb->get_var($query) : $wpdb->get_var($wpdb->prepare($query, array_merge($where_values, ['paid'])));
    
    // Pending payments
    $pending_where = $where_sql ? $where_sql . " AND b.payment_status = 'pending'" : "WHERE b.payment_status = 'pending'";
    $query = "SELECT COUNT(*) FROM $bookings_table b LEFT JOIN $events_table e ON b.event_id = e.id $pending_where";
    $stats['pending_payments'] = empty($where_values) ? $wpdb->get_var($query) : $wpdb->get_var($wpdb->prepare($query, array_merge($where_values, ['pending'])));
    
    // Total participants
    $query = "SELECT COALESCE(SUM(b.tickets), 0) $base_query";
    $stats['total_participants'] = empty($where_values) ? $wpdb->get_var($query) : $wpdb->get_var($wpdb->prepare($query, $where_values));
    
    return $stats;
}

/**
 * Process refund through Stripe
 */
function puzzlepath_process_refund($booking_id) {
    global $wpdb;
    
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pp_bookings WHERE id = %d", 
        $booking_id
    ));
    
    if (!$booking || $booking->payment_status !== 'paid') {
        return ['success' => false, 'error' => 'Booking not found or not eligible for refund.'];
    }
    
    if (!class_exists('\Stripe\Stripe')) {
        return ['success' => false, 'error' => 'Stripe library not available.'];
    }
    
    try {
        // Get Stripe keys
        $test_mode = get_option('puzzlepath_stripe_test_mode', true);
        $secret_key = $test_mode ? 
            get_option('puzzlepath_stripe_secret_key') : 
            get_option('puzzlepath_stripe_live_secret_key');
        
        // Validate secret key before setting
        if (empty($secret_key)) {
            return ['success' => false, 'error' => 'Stripe secret key not configured. Please check your Stripe settings.'];
        }
        
        \Stripe\Stripe::setApiKey($secret_key);
        
        // Create refund
        $refund = \Stripe\Refund::create([
            'payment_intent' => $booking->stripe_payment_intent_id,
            'reason' => 'requested_by_customer'
        ]);
        
        if ($refund->status === 'succeeded') {
            // Update booking status
            $wpdb->update(
                $wpdb->prefix . 'pp_bookings',
                ['payment_status' => 'refunded'],
                ['id' => $booking_id]
            );
            
            // Restore event seats
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}pp_events SET seats = seats + %d WHERE id = %d",
                $booking->tickets,
                $booking->event_id
            ));
            
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Refund failed: ' . $refund->failure_reason];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Refund error: ' . $e->getMessage()];
    }
}

/**
 * Resend confirmation email
 */
function puzzlepath_resend_confirmation_email($booking_id) {
    global $wpdb;
    
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pp_bookings WHERE id = %d", 
        $booking_id
    ));
    
    if ($booking && class_exists('PuzzlePath_Stripe_Integration')) {
        $stripe_instance = PuzzlePath_Stripe_Integration::get_instance();
        if (method_exists($stripe_instance, 'send_confirmation_email')) {
            // Use reflection to call private method
            $reflection = new ReflectionClass($stripe_instance);
            $method = $reflection->getMethod('send_confirmation_email');
            $method->setAccessible(true);
            $method->invoke($stripe_instance, $booking, $booking->booking_code);
        }
    }
}

/**
 * Export bookings to CSV
 */
function puzzlepath_export_bookings_csv() {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    $events_table = $wpdb->prefix . 'pp_events';
    $coupons_table = $wpdb->prefix . 'pp_coupons';
    
    // Apply same filters as the main page
    $where_clauses = [];
    $where_values = [];
    
    // ... (copy filter logic from main function)
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    $query = "SELECT b.*, e.title as event_title, e.hunt_code, e.hunt_name, e.event_date, c.code as coupon_code
             FROM $bookings_table b 
             LEFT JOIN $events_table e ON b.event_id = e.id
             LEFT JOIN $coupons_table c ON b.coupon_id = c.id
             $where_sql
             ORDER BY b.created_at DESC";
    
    $bookings = empty($where_values) ? $wpdb->get_results($query) : $wpdb->get_results($wpdb->prepare($query, $where_values));
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="puzzlepath-bookings-' . date('Y-m-d-H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'Booking ID',
        'Booking Code', 
        'Customer Name',
        'Customer Email',
        'Event Title',
        'Hunt Code',
        'Hunt Name',
        'Event Date',
        'Tickets',
        'Total Price',
        'Coupon Code',
        'Payment Status',
        'Booking Date',
        'Participant Names'
    ]);
    
    // CSV data
    foreach ($bookings as $booking) {
        fputcsv($output, [
            $booking->id,
            $booking->booking_code,
            $booking->customer_name,
            $booking->customer_email,
            $booking->event_title,
            $booking->hunt_code,
            $booking->hunt_name,
            $booking->event_date ? date('Y-m-d H:i:s', strtotime($booking->event_date)) : '',
            $booking->tickets,
            $booking->total_price,
            $booking->coupon_code,
            $booking->payment_status,
            $booking->created_at,
            $booking->participant_names
        ]);
    }
    
    fclose($output);
}

/**
 * AJAX handler for booking details
 */
function puzzlepath_get_booking_details_ajax() {
    check_ajax_referer('booking_details_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    global $wpdb;
    $booking_id = intval($_POST['booking_id']);
    
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, e.title as event_title, e.hunt_code, e.hunt_name, e.event_date, e.location, c.code as coupon_code, c.discount_percent
         FROM {$wpdb->prefix}pp_bookings b 
         LEFT JOIN {$wpdb->prefix}pp_events e ON b.event_id = e.id
         LEFT JOIN {$wpdb->prefix}pp_coupons c ON b.coupon_id = c.id
         WHERE b.id = %d", 
        $booking_id
    ));
    
    if (!$booking) {
        wp_send_json_error('Booking not found');
        return;
    }
    
    ob_start();
    ?>
    <h2>Booking Details #<?php echo $booking->id; ?></h2>
    
    <table class="form-table">
        <tr>
            <th>Booking Code:</th>
            <td><strong><?php echo esc_html($booking->booking_code); ?></strong></td>
        </tr>
        <tr>
            <th>Customer:</th>
            <td><?php echo esc_html($booking->customer_name); ?> (<?php echo esc_html($booking->customer_email); ?>)</td>
        </tr>
        <tr>
            <th>Event:</th>
            <td><?php echo esc_html($booking->event_title); ?></td>
        </tr>
        <?php if ($booking->hunt_name || $booking->hunt_code): ?>
        <tr>
            <th>Hunt:</th>
            <td>
                <?php echo esc_html($booking->hunt_name ?: $booking->hunt_code); ?>
                <?php if ($booking->hunt_name && $booking->hunt_code): ?>
                    (<?php echo esc_html($booking->hunt_code); ?>)
                <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php if ($booking->event_date): ?>
        <tr>
            <th>Event Date:</th>
            <td><?php echo date('F j, Y, g:i A', strtotime($booking->event_date)); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($booking->location): ?>
        <tr>
            <th>Location:</th>
            <td><?php echo esc_html($booking->location); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>Tickets:</th>
            <td><?php echo $booking->tickets; ?></td>
        </tr>
        <tr>
            <th>Total Price:</th>
            <td>$<?php echo number_format($booking->total_price, 2); ?></td>
        </tr>
        <?php if ($booking->coupon_code): ?>
        <tr>
            <th>Coupon:</th>
            <td><?php echo esc_html($booking->coupon_code); ?> (<?php echo $booking->discount_percent; ?>% off)</td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>Payment Status:</th>
            <td>
                <span style="background: <?php 
                    echo $booking->payment_status === 'paid' ? '#00a32a' :
                         ($booking->payment_status === 'pending' ? '#dba617' : '#d63638'); 
                ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
                    <?php echo esc_html($booking->payment_status); ?>
                </span>
            </td>
        </tr>
        <?php if ($booking->stripe_payment_intent_id): ?>
        <tr>
            <th>Stripe Payment ID:</th>
            <td><code><?php echo esc_html($booking->stripe_payment_intent_id); ?></code></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>Booking Date:</th>
            <td><?php echo date('F j, Y, g:i A', strtotime($booking->created_at)); ?></td>
        </tr>
        <?php if ($booking->participant_names): ?>
        <tr>
            <th>Participant Names:</th>
            <td><?php echo nl2br(esc_html($booking->participant_names)); ?></td>
        </tr>
        <?php endif; ?>
    </table>
    
    <div style="margin-top: 20px;">
        <button type="button" class="button button-primary" onclick="closeBookingDetails(); editBookingDetails(<?php echo $booking->id; ?>);">Edit Booking</button>
        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=puzzlepath-bookings&action=resend_email&booking_id=' . $booking->id), 'puzzlepath_resend_' . $booking->id); ?>" class="button">Resend Confirmation Email</a>
        <?php if ($booking->payment_status === 'paid'): ?>
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=puzzlepath-bookings&action=refund&booking_id=' . $booking->id), 'puzzlepath_refund_' . $booking->id); ?>" 
               class="button button-secondary" onclick="return confirm('Are you sure you want to refund this booking?');">Process Refund</a>
        <?php endif; ?>
    </div>
    <?php
    
    $content = ob_get_clean();
    wp_send_json_success($content);
}
add_action('wp_ajax_get_booking_details', 'puzzlepath_get_booking_details_ajax');

/**
 * AJAX handler for edit booking form
 */
function puzzlepath_get_edit_booking_form_ajax() {
    check_ajax_referer('edit_booking_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    global $wpdb;
    $booking_id = intval($_POST['booking_id']);
    
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, e.title as event_title, e.hunt_code, e.hunt_name, e.event_date, e.location, c.code as coupon_code, c.discount_percent
         FROM {$wpdb->prefix}pp_bookings b 
         LEFT JOIN {$wpdb->prefix}pp_events e ON b.event_id = e.id
         LEFT JOIN {$wpdb->prefix}pp_coupons c ON b.coupon_id = c.id
         WHERE b.id = %d", 
        $booking_id
    ));
    
    if (!$booking) {
        wp_send_json_error('Booking not found');
        return;
    }
    
    ob_start();
    ?>
    <h2 style="margin: 0 0 15px 0; padding-right: 40px;">Edit Booking #<?php echo $booking->id; ?></h2>
    
    <form id="edit-booking-form">
        <table class="form-table" style="margin-top: 0;">
            <tr>
                <th><label for="edit-booking-code">Booking Code:</label></th>
                <td><input type="text" id="edit-booking-code" name="booking_code" value="<?php echo esc_attr($booking->booking_code); ?>" class="regular-text" readonly style="background: #f7f7f7;" /></td>
            </tr>
            <tr>
                <th><label for="edit-customer-name">Customer Name:</label></th>
                <td><input type="text" id="edit-customer-name" name="customer_name" value="<?php echo esc_attr($booking->customer_name); ?>" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="edit-customer-email">Customer Email:</label></th>
                <td><input type="email" id="edit-customer-email" name="customer_email" value="<?php echo esc_attr($booking->customer_email); ?>" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="edit-event-title">Event:</label></th>
                <td><input type="text" id="edit-event-title" value="<?php echo esc_attr($booking->event_title); ?>" class="regular-text" readonly style="background: #f7f7f7;" /></td>
            </tr>
            <?php if ($booking->hunt_name || $booking->hunt_code): ?>
            <tr>
                <th>Hunt:</th>
                <td>
                    <input type="text" value="<?php echo esc_attr($booking->hunt_name ?: $booking->hunt_code); ?><?php echo ($booking->hunt_name && $booking->hunt_code) ? ' (' . $booking->hunt_code . ')' : ''; ?>" class="regular-text" readonly style="background: #f7f7f7;" />
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><label for="edit-tickets">Number of Tickets:</label></th>
                <td><input type="number" id="edit-tickets" name="tickets" value="<?php echo esc_attr($booking->tickets); ?>" min="1" max="50" class="small-text" required /></td>
            </tr>
            <tr>
                <th><label for="edit-total-price">Total Price ($):</label></th>
                <td><input type="number" id="edit-total-price" name="total_price" value="<?php echo esc_attr($booking->total_price); ?>" step="0.01" min="0" class="regular-text" required /></td>
            </tr>
            <?php if ($booking->coupon_code): ?>
            <tr>
                <th>Coupon:</th>
                <td><input type="text" value="<?php echo esc_attr($booking->coupon_code); ?> (<?php echo $booking->discount_percent; ?>% off)" class="regular-text" readonly style="background: #f7f7f7;" /></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><label for="edit-payment-status">Payment Status:</label></th>
                <td>
                    <select id="edit-payment-status" name="payment_status" class="regular-text" required>
                        <option value="pending" <?php selected($booking->payment_status, 'pending'); ?>>Pending</option>
                        <option value="paid" <?php selected($booking->payment_status, 'paid'); ?>>Paid</option>
                        <option value="failed" <?php selected($booking->payment_status, 'failed'); ?>>Failed</option>
                        <option value="refunded" <?php selected($booking->payment_status, 'refunded'); ?>>Refunded</option>
                    </select>
                </td>
            </tr>
            <?php if ($booking->participant_names): ?>
            <tr>
                <th><label for="edit-participant-names">Participant Names:</label></th>
                <td><textarea id="edit-participant-names" name="participant_names" rows="4" class="large-text"><?php echo esc_textarea($booking->participant_names); ?></textarea></td>
            </tr>
            <?php endif; ?>
        </table>
    </form>
    
    <div style="margin-top: 20px; padding: 15px 0; border-top: 1px solid #ddd; background: #f9f9f9; margin-left: -20px; margin-right: -20px; padding-left: 20px; padding-right: 20px;">
        <button type="button" id="edit-booking-save-btn" class="button button-primary" onclick="saveBookingChanges(<?php echo $booking->id; ?>)" style="margin-right: 10px;">Save Changes</button>
        <button type="button" class="button" onclick="closeEditBooking()">Cancel</button>
        <div style="clear: both;"></div>
    </div>
    <?php
    
    $content = ob_get_clean();
    wp_send_json_success($content);
}
add_action('wp_ajax_get_edit_booking_form', 'puzzlepath_get_edit_booking_form_ajax');

/**
 * AJAX handler for saving booking changes
 */
function puzzlepath_save_booking_changes_ajax() {
    check_ajax_referer('save_booking_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    global $wpdb;
    $booking_id = intval($_POST['booking_id']);
    
    // Validate input
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $tickets = intval($_POST['tickets']);
    $total_price = floatval($_POST['total_price']);
    $payment_status = sanitize_text_field($_POST['payment_status']);
    $participant_names = isset($_POST['participant_names']) ? sanitize_textarea_field($_POST['participant_names']) : '';
    
    // Validation
    if (empty($customer_name) || empty($customer_email) || $tickets < 1 || $total_price < 0) {
        wp_send_json_error('Please fill in all required fields with valid values.');
        return;
    }
    
    if (!is_email($customer_email)) {
        wp_send_json_error('Please enter a valid email address.');
        return;
    }
    
    if (!in_array($payment_status, ['pending', 'paid', 'failed', 'refunded'])) {
        wp_send_json_error('Invalid payment status.');
        return;
    }
    
    // Update booking
    $update_data = [
        'customer_name' => $customer_name,
        'customer_email' => $customer_email,
        'tickets' => $tickets,
        'total_price' => $total_price,
        'payment_status' => $payment_status
    ];
    
    if ($participant_names !== '') {
        $update_data['participant_names'] = $participant_names;
    }
    
    $result = $wpdb->update(
        $wpdb->prefix . 'pp_bookings',
        $update_data,
        ['id' => $booking_id],
        ['%s', '%s', '%d', '%f', '%s', '%s'],
        ['%d']
    );
    
    if ($result === false) {
        wp_send_json_error('Database error: Could not update booking.');
        return;
    }
    
    wp_send_json_success('Booking updated successfully!');
}
add_action('wp_ajax_save_booking_changes', 'puzzlepath_save_booking_changes_ajax');


/**
 * AJAX handler for quest details
 */
function puzzlepath_get_quest_details_ajax() {
    // Add debugging
    error_log('Quest details AJAX called with data: ' . print_r($_POST, true));
    
    try {
        check_ajax_referer('quest_details_nonce', 'nonce');
    } catch (Exception $e) {
        wp_send_json_error('Nonce verification failed: ' . $e->getMessage());
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    $clues_table = $wpdb->prefix . 'pp_clues';
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    
    $quest_id = intval($_POST['quest_id']);
    
    if (!$quest_id) {
        wp_send_json_error('Invalid quest ID provided');
        return;
    }
    
    $quest = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$events_table} WHERE id = %d", 
        $quest_id
    ));
    
    if (!$quest) {
        wp_send_json_error('Quest not found with ID: ' . $quest_id);
        return;
    }
    
    if ($wpdb->last_error) {
        wp_send_json_error('Database error: ' . $wpdb->last_error);
        return;
    }
    
    // Get clue count and booking stats
    $clue_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$clues_table} WHERE hunt_id = %d AND is_active = 1",
        $quest->id
    ));
    
    $booking_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) as total_bookings, SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_bookings, SUM(CASE WHEN payment_status = 'paid' THEN total_price ELSE 0 END) as total_revenue FROM {$bookings_table} WHERE event_id = %d",
        $quest_id
    ));
    
    try {
        ob_start();
        ?>
        <h2 style="margin: 0 0 15px 0; padding-right: 40px;">Quest Details: <?php echo esc_html($quest->title); ?></h2>
    
    <table class="form-table" style="margin-top: 0;">
        <tr>
            <th>Quest Code:</th>
            <td><strong><?php echo esc_html($quest->hunt_code); ?></strong></td>
        </tr>
        <tr>
            <th>Quest Name:</th>
            <td><?php echo esc_html($quest->title); ?></td>
        </tr>
        <?php if ($quest->hunt_name && $quest->hunt_name != $quest->title): ?>
        <tr>
            <th>Hunt Name:</th>
            <td><?php echo esc_html($quest->hunt_name); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>Location:</th>
            <td><?php echo esc_html($quest->location); ?></td>
        </tr>
        <tr>
            <th>Quest Type:</th>
            <td><span style="background: <?php echo $quest->hosting_type === 'hosted' ? '#00a32a' : '#2271b1'; ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
                <?php echo esc_html($quest->hosting_type === 'hosted' ? 'LIVE' : 'ANYTIME'); ?>
            </span></td>
        </tr>
        <?php if ($quest->event_date): ?>
        <tr>
            <th>Event Date:</th>
            <td><?php echo date('F j, Y, g:i A', strtotime($quest->event_date)); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>Price:</th>
            <td>$<?php echo number_format($quest->price, 2); ?></td>
        </tr>
        <tr>
            <th>Seats Available:</th>
            <td><?php echo $quest->seats; ?> seats</td>
        </tr>
        <tr>
            <th>Number of Clues:</th>
            <td><?php echo $clue_count ?: 0; ?> clues</td>
        </tr>
        <?php if ($quest->duration_minutes): ?>
        <tr>
            <th>Duration:</th>
            <td>
                <?php 
                $hours = floor($quest->duration_minutes / 60);
                $minutes = $quest->duration_minutes % 60;
                if ($hours > 0 && $minutes > 0) {
                    echo $hours . 'h ' . $minutes . 'm';
                } elseif ($hours > 0) {
                    echo $hours . ' hour' . ($hours > 1 ? 's' : '');
                } else {
                    echo $minutes . ' minutes';
                }
                ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php if ($quest->medal_image_url): ?>
        <tr>
            <th>Medal Image:</th>
            <td>
                <img src="<?php echo esc_url($quest->medal_image_url); ?>" alt="Quest Medal" style="max-width: 120px; max-height: 120px; border: 2px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" />
                <p><small><a href="<?php echo esc_url($quest->medal_image_url); ?>" target="_blank">View full size</a></small></p>
            </td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>Total Bookings:</th>
            <td><?php echo $booking_stats->total_bookings ?: 0; ?> bookings</td>
        </tr>
        <tr>
            <th>Paid Bookings:</th>
            <td><?php echo $booking_stats->paid_bookings ?: 0; ?> paid</td>
        </tr>
        <tr>
            <th>Total Revenue:</th>
            <td>$<?php echo number_format($booking_stats->total_revenue ?: 0, 2); ?></td>
        </tr>
        <tr>
            <th>Status:</th>
            <td>
                <span style="background: <?php echo in_array($quest->hosting_type, ['hosted', 'self-hosted']) ? '#00a32a' : '#d63638'; ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
                    <?php echo in_array($quest->hosting_type, ['hosted', 'self-hosted']) ? 'ACTIVE' : 'INACTIVE'; ?>
                </span>
            </td>
        </tr>
        <tr>
            <th>Display on Site:</th>
            <td>
                <span style="background: <?php echo $quest->display_on_site ? '#00a32a' : '#666'; ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
                    <?php echo $quest->display_on_site ? '👁️ VISIBLE' : '🚫 HIDDEN'; ?>
                </span>
                <p><small><?php echo $quest->display_on_site ? 'This quest appears on the public website' : 'This quest is hidden from public view'; ?></small></p>
            </td>
        </tr>
        <tr>
            <th>Created:</th>
            <td><?php echo date('F j, Y, g:i A', strtotime($quest->created_at)); ?></td>
        </tr>
    </table>
    
    <div style="margin-top: 20px; padding: 15px 0; border-top: 1px solid #ddd; background: #f9f9f9; margin-left: -20px; margin-right: -20px; padding-left: 20px; padding-right: 20px;">
        <button type="button" class="button button-primary" onclick="closeQuestDetails(); editQuest(<?php echo $quest->id; ?>);" style="margin-right: 10px;">Edit Quest</button>
        <button type="button" class="button" onclick="closeQuestDetails(); manageClues(<?php echo $quest->id; ?>);">Manage Clues</button>
        <button type="button" class="button" onclick="closeQuestDetails()">Close</button>
    </div>
    <?php
    
        $content = ob_get_clean();
        wp_send_json_success($content);
        
    } catch (Exception $e) {
        if (ob_get_level()) {
            ob_end_clean();
        }
        wp_send_json_error('Error generating content: ' . $e->getMessage());
    }
}
add_action('wp_ajax_get_quest_details', 'puzzlepath_get_quest_details_ajax');

/**
 * AJAX handler for edit quest form
 */
function puzzlepath_get_edit_quest_form_ajax() {
    check_ajax_referer('edit_quest_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    
    $quest_id = intval($_POST['quest_id']);
    
    if (!$quest_id) {
        wp_send_json_error('Invalid quest ID provided');
        return;
    }
    
    $quest = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$events_table} WHERE id = %d", 
        $quest_id
    ));
    
    if (!$quest) {
        wp_send_json_error('Quest not found with ID: ' . $quest_id);
        return;
    }
    
    ob_start();
    ?>
    <h2 style="margin: 0 0 15px 0; padding-right: 40px;">Edit Quest: <?php echo esc_html($quest->title); ?></h2>
    
    <form id="edit-quest-form">
        <table class="form-table" style="margin-top: 0;">
            <tr>
                <th><label for="edit-quest-code">Quest Code:</label></th>
                <td><input type="text" id="edit-quest-code" name="hunt_code" value="<?php echo esc_attr($quest->hunt_code); ?>" class="regular-text" readonly style="background: #f7f7f7;" /></td>
            </tr>
            <tr>
                <th><label for="edit-quest-title">Quest Name:</label></th>
                <td><input type="text" id="edit-quest-title" name="title" value="<?php echo esc_attr($quest->title); ?>" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="edit-hunt-name">Hunt Name:</label></th>
                <td><input type="text" id="edit-hunt-name" name="hunt_name" value="<?php echo esc_attr($quest->hunt_name); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="edit-location">Location:</label></th>
                <td><input type="text" id="edit-location" name="location" value="<?php echo esc_attr($quest->location); ?>" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="edit-hosting-type">Quest Type:</label></th>
                <td>
                    <select id="edit-hosting-type" name="hosting_type" class="regular-text" required>
                        <option value="self-hosted" <?php selected($quest->hosting_type, 'self-hosted'); ?>>ANYTIME Quest (Self-hosted)</option>
                        <option value="hosted" <?php selected($quest->hosting_type, 'hosted'); ?>>LIVE Quest (Scheduled)</option>
                        <option value="inactive" <?php selected($quest->hosting_type, 'inactive'); ?>>Inactive</option>
                    </select>
                </td>
            </tr>
            <?php if ($quest->hosting_type === 'hosted'): ?>
            <tr>
                <th><label for="edit-event-date">Event Date & Time:</label></th>
                <td><input type="datetime-local" id="edit-event-date" name="event_date" value="<?php echo $quest->event_date ? date('Y-m-d\TH:i', strtotime($quest->event_date)) : ''; ?>" class="regular-text" /></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><label for="edit-price">Price ($):</label></th>
                <td><input type="number" id="edit-price" name="price" value="<?php echo esc_attr($quest->price); ?>" step="0.01" min="0" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="edit-seats">Available Seats:</label></th>
                <td><input type="number" id="edit-seats" name="seats" value="<?php echo esc_attr($quest->seats); ?>" min="1" max="1000" class="small-text" required /></td>
            </tr>
            <tr>
                <th><label for="edit-duration">Duration (minutes):</label></th>
                <td>
                    <input type="number" id="edit-duration" name="duration_minutes" value="<?php echo esc_attr($quest->duration_minutes ?: ''); ?>" min="0" max="600" class="small-text" placeholder="e.g., 90" />
                    <p class="description">Expected time to complete the quest in minutes (optional)</p>
                </td>
            </tr>
            <tr>
                <th><label for="edit-medal-image">Medal Image:</label></th>
                <td>
                    <div id="medal-image-container">
                        <?php if ($quest->medal_image_url): ?>
                            <div id="current-medal-image" style="margin-bottom: 10px;">
                                <img src="<?php echo esc_url($quest->medal_image_url); ?>" alt="Current Medal" style="max-width: 100px; max-height: 100px; border: 2px solid #ddd; border-radius: 5px;" />
                                <p><small>Current medal image</small></p>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="edit-medal-image" name="medal_image" accept="image/*" style="margin-bottom: 5px;" />
                        <input type="hidden" id="edit-medal-image-url" name="medal_image_url" value="<?php echo esc_attr($quest->medal_image_url ?: ''); ?>" />
                        <p class="description">Upload a medal image for quest completion (JPG, PNG, GIF - max 4MB)</p>
                        <?php if ($quest->medal_image_url): ?>
                            <p><button type="button" class="button" onclick="removeMedalImage()">Remove Current Image</button></p>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label for="edit-display-on-site">Display on Site:</label></th>
                <td>
                    <label style="display: flex; align-items: center;">
                        <input type="checkbox" id="edit-display-on-site" name="display_on_site" value="1" <?php echo $quest->display_on_site ? 'checked' : ''; ?> style="margin-right: 8px;" />
                        <span>Make this quest visible on the public website</span>
                    </label>
                    <p class="description">When checked, this quest will appear in public listings and be available for booking</p>
                </td>
            </tr>
        </table>
    </form>
    
    <div style="margin-top: 20px; padding: 15px 0; border-top: 1px solid #ddd; background: #f9f9f9; margin-left: -20px; margin-right: -20px; padding-left: 20px; padding-right: 20px;">
        <button type="button" id="quest-save-btn" class="button button-primary" onclick="saveQuestChanges(<?php echo $quest->id; ?>)" style="margin-right: 10px;">Save Changes</button>
        <button type="button" class="button" onclick="closeEditQuest()">Cancel</button>
        <div style="clear: both;"></div>
    </div>
    <?php
    
    $content = ob_get_clean();
    wp_send_json_success($content);
}
add_action('wp_ajax_get_edit_quest_form', 'puzzlepath_get_edit_quest_form_ajax');

/**
 * AJAX handler for saving quest changes
 */
function puzzlepath_save_quest_changes_ajax() {
    check_ajax_referer('save_quest_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    
    $quest_id = intval($_POST['quest_id']);
    
    if (!$quest_id) {
        wp_send_json_error('Invalid quest ID provided');
        return;
    }
    
    // Validate and sanitize input
    $title = sanitize_text_field($_POST['title']);
    $hunt_name = sanitize_text_field($_POST['hunt_name']);
    $location = sanitize_text_field($_POST['location']);
    $hosting_type = sanitize_text_field($_POST['hosting_type']);
    $price = floatval($_POST['price']);
    $seats = intval($_POST['seats']);
    $duration_minutes = !empty($_POST['duration_minutes']) ? intval($_POST['duration_minutes']) : null;
    $medal_image_url = sanitize_text_field($_POST['medal_image_url']);
    $display_on_site = isset($_POST['display_on_site']) ? 1 : 0;
    $event_date = !empty($_POST['event_date']) ? sanitize_text_field($_POST['event_date']) : null;
    
    // Handle medal image upload
    if (!empty($_FILES['medal_image']['name'])) {
        $upload_result = puzzlepath_handle_medal_image_upload($_FILES['medal_image']);
        if ($upload_result['success']) {
            $medal_image_url = $upload_result['url'];
        } else {
            wp_send_json_error('Medal image upload failed: ' . $upload_result['error']);
            return;
        }
    }
    
    // Validation
    if (empty($title) || empty($location) || $price < 0 || $seats < 1) {
        wp_send_json_error('Please fill in all required fields with valid values.');
        return;
    }
    
    if (!in_array($hosting_type, ['hosted', 'self-hosted', 'inactive'])) {
        wp_send_json_error('Invalid hosting type.');
        return;
    }
    
    // Prepare update data
    $update_data = [
        'title' => $title,
        'hunt_name' => $hunt_name,
        'location' => $location,
        'hosting_type' => $hosting_type,
        'price' => $price,
        'seats' => $seats,
        'duration_minutes' => $duration_minutes,
        'medal_image_url' => $medal_image_url,
        'display_on_site' => $display_on_site
    ];
    
    if ($event_date) {
        $update_data['event_date'] = date('Y-m-d H:i:s', strtotime($event_date));
    } elseif ($hosting_type !== 'hosted') {
        $update_data['event_date'] = null;
    }
    
    // Update quest
    $result = $wpdb->update(
        $events_table,
        $update_data,
        ['id' => $quest_id],
        ['%s', '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%d'],
        ['%d']
    );
    
    if ($result === false) {
        wp_send_json_error('Database error: Could not update quest. ' . $wpdb->last_error);
        return;
    }
    
    wp_send_json_success('Quest updated successfully!');
}
add_action('wp_ajax_save_quest_changes', 'puzzlepath_save_quest_changes_ajax');

/**
 * AJAX handler for toggling quest display on site
 */
function puzzlepath_toggle_quest_display_ajax() {
    check_ajax_referer('toggle_quest_display_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    
    $quest_id = intval($_POST['quest_id']);
    $display_on_site = intval($_POST['display_on_site']);
    
    if (!$quest_id) {
        wp_send_json_error('Invalid quest ID provided');
        return;
    }
    
    // Update display status
    $result = $wpdb->update(
        $events_table,
        ['display_on_site' => $display_on_site],
        ['id' => $quest_id],
        ['%d'],
        ['%d']
    );
    
    if ($result === false) {
        wp_send_json_error('Database error: Could not update display status. ' . $wpdb->last_error);
        return;
    }
    
    wp_send_json_success($display_on_site ? 'Quest is now visible on site' : 'Quest is now hidden from site');
}
add_action('wp_ajax_toggle_quest_display', 'puzzlepath_toggle_quest_display_ajax');

/**
 * AJAX handler for quest clues management
 */
function puzzlepath_get_quest_clues_ajax() {
    check_ajax_referer('quest_clues_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    $clues_table = $wpdb->prefix . 'pp_clues';
    
    $quest_id = intval($_POST['quest_id']);
    
    if (!$quest_id) {
        wp_send_json_error('Invalid quest ID provided');
        return;
    }
    
    // Get quest info
    $quest = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$events_table} WHERE id = %d", 
        $quest_id
    ));
    
    if (!$quest) {
        wp_send_json_error('Quest not found with ID: ' . $quest_id);
        return;
    }
    
    // Get all clues for this quest
    $clues = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$clues_table} WHERE hunt_id = %d ORDER BY clue_order ASC",
        $quest->id
    ));
    
    ob_start();
    ?>
    <h2 style="margin: 0 0 15px 0; padding-right: 40px;">Manage Clues: <?php echo esc_html($quest->title); ?></h2>
    
    <div style="margin-bottom: 20px; padding: 10px; background: #f0f0f1; border-radius: 4px;">
        <strong>Quest:</strong> <?php echo esc_html($quest->title); ?> (<?php echo esc_html($quest->hunt_code); ?>)<br>
        <strong>Location:</strong> <?php echo esc_html($quest->location); ?><br>
        <strong>Total Clues:</strong> <?php echo count($clues); ?>
    </div>
    
    <?php if (empty($clues)): ?>
        <div style="text-align: center; padding: 40px; background: #fff; border: 2px dashed #ddd; border-radius: 4px;">
            <h3>No Clues Found</h3>
            <p>This quest doesn't have any clues yet.</p>
            <p>Clues should be linked to the hunt_code: <strong><?php echo esc_html($quest->hunt_code); ?></strong></p>
            <button type="button" class="button button-primary" onclick="addNewClue('<?php echo esc_js($quest->hunt_code); ?>')">Add First Clue</button>
        </div>
    <?php else: ?>
        <div style="margin-bottom: 10px;">
            <button type="button" class="button button-primary" onclick="addNewClue('<?php echo esc_js($quest->hunt_code); ?>')">Add New Clue</button>
        </div>
        
        <div class="clues-list">
            <?php foreach ($clues as $clue): ?>
                <div class="clue-item" style="background: #fff; margin-bottom: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 10px 0; color: #2271b1;">
                                Clue #<?php echo $clue->clue_order; ?>
                                <?php if ($clue->title): ?>
                                    - <?php echo esc_html($clue->title); ?>
                                <?php endif; ?>
                                <span style="font-size: 12px; color: <?php echo $clue->is_active ? '#00a32a' : '#d63638'; ?>; margin-left: 10px;">
                                    <?php echo $clue->is_active ? '●ACTIVE' : '●INACTIVE'; ?>
                                </span>
                            </h4>
                            
                            <div style="margin-bottom: 10px;">
                                <strong>Clue Text:</strong><br>
                                <div style="background: #f9f9f9; padding: 8px; border-radius: 3px; margin-top: 5px;">
                                    <?php echo esc_html($clue->clue_text ?: 'No clue text'); ?>
                                </div>
                            </div>
                            
                            <?php if ($clue->task_description): ?>
                            <div style="margin-bottom: 10px;">
                                <strong>Task:</strong><br>
                                <div style="background: #f0f6ff; padding: 8px; border-radius: 3px; margin-top: 5px;">
                                    <?php echo esc_html($clue->task_description); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($clue->hint_text): ?>
                            <div style="margin-bottom: 10px;">
                                <strong>Hint:</strong><br>
                                <div style="background: #fff3cd; padding: 8px; border-radius: 3px; margin-top: 5px;">
                                    <?php echo esc_html($clue->hint_text); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($clue->latitude && $clue->longitude): ?>
                            <div style="margin-bottom: 10px;">
                                <strong>Location:</strong> 
                                Lat: <?php echo $clue->latitude; ?>, Lng: <?php echo $clue->longitude; ?>
                                <?php if ($clue->geofence_radius): ?>
                                    (<?php echo $clue->geofence_radius; ?>m radius)
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($clue->image_url): ?>
                            <div style="margin-bottom: 10px;">
                                <strong>Image:</strong> 
                                <a href="<?php echo esc_url($clue->image_url); ?>" target="_blank">View Image</a>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="margin-left: 15px;">
                            <button type="button" class="button button-small" onclick="editClue(<?php echo $clue->id; ?>)" title="Edit Clue">✏️ Edit</button>
                            <button type="button" class="button button-small" onclick="toggleClueStatus(<?php echo $clue->id; ?>, <?php echo $clue->is_active ? 'false' : 'true'; ?>)" title="<?php echo $clue->is_active ? 'Deactivate' : 'Activate'; ?> Clue">
                                <?php echo $clue->is_active ? '🚫' : '✅'; ?>
                            </button>
                            <button type="button" class="button button-small" onclick="deleteClue(<?php echo $clue->id; ?>)" title="Delete Clue" style="color: #d63638;">🗑️</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 20px; padding: 15px 0; border-top: 1px solid #ddd; background: #f9f9f9; margin-left: -20px; margin-right: -20px; padding-left: 20px; padding-right: 20px;">
        <button type="button" class="button" onclick="closeManageClues()">Close</button>
        <div style="clear: both;"></div>
    </div>
    
    <script>
    function addNewClue(huntCode) {
        alert('Add New Clue functionality coming soon for hunt: ' + huntCode);
    }
    
    function editClue(clueId) {
        document.getElementById('edit-clue-modal').style.display = 'block';
        document.getElementById('edit-clue-content').innerHTML = 'Loading...';
        
        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'get_clue',
            clue_id: clueId,
            nonce: '<?php echo wp_create_nonce('edit_clue_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                showEditClueForm(response.data);
            } else {
                document.getElementById('edit-clue-content').innerHTML = 'Error loading clue: ' + (response.data || 'Unknown error');
            }
        }).fail(function(xhr, status, error) {
            document.getElementById('edit-clue-content').innerHTML = 'AJAX Error: ' + error;
        });
    }
    
    function toggleClueStatus(clueId, newStatus) {
        if (confirm('Are you sure you want to ' + (newStatus === 'true' ? 'activate' : 'deactivate') + ' this clue?')) {
            alert('Toggle clue status functionality coming soon');
        }
    }
    
    function deleteClue(clueId) {
        if (confirm('Are you sure you want to delete this clue? This action cannot be undone.')) {
            alert('Delete clue functionality coming soon');
        }
    }
    
    function closeEditClue() {
        document.getElementById('edit-clue-modal').style.display = 'none';
    }
    
    function showEditClueForm(clue) {
        var formHtml = `
            <h2 style="margin: 0 0 20px 0; padding-right: 40px;">Edit Clue #${clue.clue_order}</h2>
            
            <form id="edit-clue-form">
                <input type="hidden" id="edit-clue-id" value="${clue.id}">
                <input type="hidden" id="edit-hunt-id" value="${clue.hunt_id}">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label for="edit-clue-order"><strong>Clue Order:</strong></label>
                        <input type="number" id="edit-clue-order" value="${clue.clue_order}" min="1" style="width: 100%; padding: 5px; margin-top: 5px;" required>
                    </div>
                    
                    <div>
                        <label for="edit-clue-title"><strong>Title:</strong></label>
                        <input type="text" id="edit-clue-title" value="${clue.title || ''}" style="width: 100%; padding: 5px; margin-top: 5px;">
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="edit-clue-text"><strong>Clue Text:</strong></label>
                    <textarea id="edit-clue-text" style="width: 100%; height: 100px; padding: 5px; margin-top: 5px; resize: vertical;" required>${clue.clue_text || ''}</textarea>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="edit-task-description"><strong>Task Description:</strong></label>
                    <textarea id="edit-task-description" style="width: 100%; height: 80px; padding: 5px; margin-top: 5px; resize: vertical;">${clue.task_description || ''}</textarea>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="edit-hint-text"><strong>Hint Text:</strong></label>
                    <textarea id="edit-hint-text" style="width: 100%; height: 60px; padding: 5px; margin-top: 5px; resize: vertical;">${clue.hint_text || ''}</textarea>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="edit-answer"><strong>Answer:</strong></label>
                    <input type="text" id="edit-answer" value="${clue.answer || ''}" style="width: 100%; padding: 5px; margin-top: 5px;" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label for="edit-latitude"><strong>Latitude:</strong></label>
                        <input type="number" id="edit-latitude" value="${clue.latitude || ''}" step="any" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="e.g. -27.4698">
                    </div>
                    
                    <div>
                        <label for="edit-longitude"><strong>Longitude:</strong></label>
                        <input type="number" id="edit-longitude" value="${clue.longitude || ''}" step="any" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="e.g. 153.0251">
                    </div>
                    
                    <div>
                        <label for="edit-geofence-radius"><strong>Geofence Radius (m):</strong></label>
                        <input type="number" id="edit-geofence-radius" value="${clue.geofence_radius || ''}" min="1" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="e.g. 50">
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="edit-image-url"><strong>Image URL:</strong></label>
                    <input type="url" id="edit-image-url" value="${clue.image_url || ''}" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="https://example.com/image.jpg">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; font-weight: bold;">
                        <input type="checkbox" id="edit-is-active" ${clue.is_active ? 'checked' : ''} style="margin-right: 8px;">
                        Clue is Active
                    </label>
                </div>
                
                <div style="padding: 15px 0; border-top: 1px solid #ddd; text-align: right;">
                    <button type="button" class="button" onclick="closeEditClue()" style="margin-right: 10px;">Cancel</button>
                    <button type="button" class="button button-primary" id="save-clue-btn" onclick="saveClueChanges()">Save Changes</button>
                </div>
            </form>
        `;
        
        document.getElementById('edit-clue-content').innerHTML = formHtml;
    }
    
    function saveClueChanges() {
        // Basic validation
        var clueOrder = document.getElementById('edit-clue-order').value;
        var clueText = document.getElementById('edit-clue-text').value.trim();
        var answer = document.getElementById('edit-answer').value.trim();
        var latitude = document.getElementById('edit-latitude').value;
        var longitude = document.getElementById('edit-longitude').value;
        
        if (!clueOrder || clueOrder < 1) {
            alert('Please enter a valid clue order (1 or greater)');
            return;
        }
        
        if (!clueText) {
            alert('Clue text is required');
            document.getElementById('edit-clue-text').focus();
            return;
        }
        
        if (!answer) {
            alert('Answer is required');
            document.getElementById('edit-answer').focus();
            return;
        }
        
        // Validate coordinates if provided
        if (latitude && (latitude < -90 || latitude > 90)) {
            alert('Latitude must be between -90 and 90 degrees');
            document.getElementById('edit-latitude').focus();
            return;
        }
        
        if (longitude && (longitude < -180 || longitude > 180)) {
            alert('Longitude must be between -180 and 180 degrees');
            document.getElementById('edit-longitude').focus();
            return;
        }
        
        var saveBtn = document.getElementById('save-clue-btn');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
        
        var formData = {
            action: 'save_clue',
            clue_id: document.getElementById('edit-clue-id').value,
            clue_order: document.getElementById('edit-clue-order').value,
            title: document.getElementById('edit-clue-title').value,
            clue_text: document.getElementById('edit-clue-text').value,
            task_description: document.getElementById('edit-task-description').value,
            hint_text: document.getElementById('edit-hint-text').value,
            answer: document.getElementById('edit-answer').value,
            latitude: document.getElementById('edit-latitude').value,
            longitude: document.getElementById('edit-longitude').value,
            geofence_radius: document.getElementById('edit-geofence-radius').value,
            image_url: document.getElementById('edit-image-url').value,
            is_active: document.getElementById('edit-is-active').checked ? 1 : 0,
            nonce: '<?php echo wp_create_nonce('save_clue_nonce'); ?>'
        };
        
        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', formData, function(response) {
            if (response.success) {
                alert('Clue updated successfully!');
                closeEditClue();
                // Refresh the clues list by reloading the current quest clues
                refreshCluesList();
            } else {
                alert('Error updating clue: ' + response.data);
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
            }
        }).fail(function() {
            alert('Network error occurred while saving clue.');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Changes';
        });
    }
    
    function refreshCluesList() {
        var currentModal = document.getElementById('manage-clues-modal');
        if (currentModal && currentModal.style.display === 'block') {
            // Get the current quest ID from the page content
            var questContent = document.getElementById('manage-clues-content').innerHTML;
            var questIdMatch = questContent.match(/manageClues\((\d+)\)/);
            
            if (questIdMatch) {
                var questId = questIdMatch[1];
                // Reload the clues for this quest
                document.getElementById('manage-clues-content').innerHTML = 'Loading...';
                
                jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'get_quest_clues',
                    quest_id: questId,
                    nonce: '<?php echo wp_create_nonce('quest_clues_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        document.getElementById('manage-clues-content').innerHTML = response.data;
                    } else {
                        document.getElementById('manage-clues-content').innerHTML = 'Error refreshing clues: ' + (response.data || 'Unknown error');
                    }
                }).fail(function(xhr, status, error) {
                    document.getElementById('manage-clues-content').innerHTML = 'AJAX Error: ' + error;
                });
            } else {
                // Fallback to page reload if we can't determine the quest ID
                location.reload();
            }
        }
    }
    </script>
    <?php
    
    $content = ob_get_clean();
    wp_send_json_success($content);
};
add_action('wp_ajax_get_quest_clues', 'puzzlepath_get_quest_clues_ajax');

/**
 * AJAX handler to get individual clue data for editing
 */
function puzzlepath_get_clue_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    if (!isset($_POST['clue_id']) || !wp_verify_nonce($_POST['nonce'], 'edit_clue_nonce')) {
        wp_send_json_error('Invalid request');
    }
    
    global $wpdb;
    $clues_table = $wpdb->prefix . 'pp_clues';
    $clue_id = intval($_POST['clue_id']);
    
    // Check if clues table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$clues_table}'");
    if (!$table_exists) {
        wp_send_json_error('Clues table does not exist. Please contact administrator.');
    }
    
    $clue = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$clues_table} WHERE id = %d",
        $clue_id
    ));
    
    if ($wpdb->last_error) {
        error_log('PuzzlePath get_clue error: ' . $wpdb->last_error);
        wp_send_json_error('Database error: ' . $wpdb->last_error);
    }
    
    if (!$clue) {
        wp_send_json_error('Clue not found with ID: ' . $clue_id);
    }
    
    wp_send_json_success($clue);
}
add_action('wp_ajax_get_clue', 'puzzlepath_get_clue_ajax');

/**
 * AJAX handler to save edited clue data
 */
function puzzlepath_save_clue_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    if (!isset($_POST['clue_id']) || !wp_verify_nonce($_POST['nonce'], 'save_clue_nonce')) {
        wp_send_json_error('Invalid request');
    }
    
    global $wpdb;
    $clues_table = $wpdb->prefix . 'pp_clues';
    
    // Check if clues table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$clues_table}'");
    if (!$table_exists) {
        wp_send_json_error('Clues table does not exist. Please contact administrator.');
    }
    
    $clue_id = intval($_POST['clue_id']);
    
    // Log the data being saved for debugging
    error_log('PuzzlePath save_clue: Updating clue ID ' . $clue_id . ' with data: ' . print_r($_POST, true));
    
    // Prepare data for update
    $data = [
        'clue_order' => intval($_POST['clue_order']),
        'title' => sanitize_text_field($_POST['title']),
        'clue_text' => sanitize_textarea_field($_POST['clue_text']),
        'task_description' => sanitize_textarea_field($_POST['task_description']),
        'hint_text' => sanitize_textarea_field($_POST['hint_text']),
        'answer' => sanitize_text_field($_POST['answer']),
        'latitude' => !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null,
        'longitude' => !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null,
        'geofence_radius' => !empty($_POST['geofence_radius']) ? intval($_POST['geofence_radius']) : null,
        'image_url' => !empty($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : null,
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    // Add new fields if they exist in the table structure
    if (isset($_POST['required_answer'])) {
        $data['required_answer'] = sanitize_text_field($_POST['required_answer']);
    }
    if (isset($_POST['input_type'])) {
        $data['input_type'] = sanitize_text_field($_POST['input_type']);
    }
    if (isset($_POST['is_case_sensitive'])) {
        $data['is_case_sensitive'] = intval($_POST['is_case_sensitive']);
    }
    if (isset($_POST['min_value']) && $_POST['min_value'] !== '') {
        $data['min_value'] = floatval($_POST['min_value']);
    }
    if (isset($_POST['max_value']) && $_POST['max_value'] !== '') {
        $data['max_value'] = floatval($_POST['max_value']);
    }
    // Handle answer_options JSON field properly
    if (isset($_POST['answer_options']) && $_POST['answer_options'] !== 'null' && trim($_POST['answer_options']) !== '') {
        $data['answer_options'] = $_POST['answer_options']; // Already JSON encoded from frontend
    } else {
        // Set to NULL for database when empty to avoid JSON validation errors
        $data['answer_options'] = null;
    }
    if (isset($_POST['photo_required'])) {
        $data['photo_required'] = intval($_POST['photo_required']);
    }
    if (isset($_POST['auto_advance'])) {
        $data['auto_advance'] = intval($_POST['auto_advance']);
    }
    
    $result = $wpdb->update($clues_table, $data, ['id' => $clue_id]);
    
    if ($result === false) {
        wp_send_json_error('Database error: ' . $wpdb->last_error);
    }
    
    wp_send_json_success('Clue updated successfully');
}
add_action('wp_ajax_save_clue', 'puzzlepath_save_clue_ajax');

/**
 * AJAX handler to create new clue
 */
function puzzlepath_create_clue_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    if (!isset($_POST['hunt_id']) || !wp_verify_nonce($_POST['nonce'], 'create_clue_nonce')) {
        wp_send_json_error('Invalid request');
    }
    
    global $wpdb;
    $clues_table = $wpdb->prefix . 'pp_clues';
    
    // Check if clues table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$clues_table}'");
    if (!$table_exists) {
        wp_send_json_error('Clues table does not exist. Please contact administrator.');
    }
    
    $hunt_id = intval($_POST['hunt_id']);
    
    // Validate required fields - check for required_answer first, then fall back to answer
    $required_answer = !empty($_POST['required_answer']) ? $_POST['required_answer'] : $_POST['answer'];
    if (empty($_POST['clue_text']) || empty($required_answer)) {
        wp_send_json_error('Clue text and required answer are required');
    }
    
    // Get requested clue order
    $clue_order = intval($_POST['clue_order']);
    if ($clue_order <= 0) {
        // Auto-determine next clue order if not provided
        $max_order = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(clue_order) FROM {$clues_table} WHERE hunt_id = %d",
            $hunt_id
        ));
        $clue_order = ($max_order ? $max_order : 0) + 1;
    } else {
        // Check if this order already exists - if so, we need to reorder existing clues
        $existing_clue = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$clues_table} WHERE hunt_id = %d AND clue_order = %d",
            $hunt_id, $clue_order
        ));
        
        if ($existing_clue) {
            // Reorder existing clues: increment clue_order for all clues >= requested order
            $reorder_result = $wpdb->query($wpdb->prepare(
                "UPDATE {$clues_table} SET clue_order = clue_order + 1 WHERE hunt_id = %d AND clue_order >= %d",
                $hunt_id, $clue_order
            ));
            
            if ($reorder_result === false) {
                wp_send_json_error('Failed to reorder existing clues: ' . $wpdb->last_error);
            }
            
            // Log the reordering action
            error_log('PuzzlePath create_clue: Reordered ' . $reorder_result . ' existing clues to make room for new clue at position ' . $clue_order);
        }
    }
    
    // Log the data being saved for debugging
    error_log('PuzzlePath create_clue: Creating new clue for hunt ID ' . $hunt_id . ' with data: ' . print_r($_POST, true));
    
    // Prepare data for insert
    $data = [
        'hunt_id' => $hunt_id,
        'clue_order' => $clue_order,
        'title' => sanitize_text_field($_POST['title']),
        'clue_text' => sanitize_textarea_field($_POST['clue_text']),
        'task_description' => sanitize_textarea_field($_POST['task_description']),
        'hint_text' => sanitize_textarea_field($_POST['hint_text']),
        'answer' => sanitize_text_field($_POST['answer']),
        'latitude' => !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null,
        'longitude' => !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null,
        'geofence_radius' => !empty($_POST['geofence_radius']) ? intval($_POST['geofence_radius']) : null,
        'image_url' => !empty($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : null,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'created_at' => current_time('mysql')
    ];
    
    // Add new fields if they exist in the table structure
    if (isset($_POST['required_answer'])) {
        $data['required_answer'] = sanitize_text_field($_POST['required_answer']);
    }
    if (isset($_POST['input_type'])) {
        $data['input_type'] = sanitize_text_field($_POST['input_type']);
    }
    if (isset($_POST['is_case_sensitive'])) {
        $data['is_case_sensitive'] = intval($_POST['is_case_sensitive']);
    }
    if (isset($_POST['min_value']) && $_POST['min_value'] !== '') {
        $data['min_value'] = floatval($_POST['min_value']);
    }
    if (isset($_POST['max_value']) && $_POST['max_value'] !== '') {
        $data['max_value'] = floatval($_POST['max_value']);
    }
    // Handle answer_options JSON field properly
    if (isset($_POST['answer_options']) && $_POST['answer_options'] !== 'null' && trim($_POST['answer_options']) !== '') {
        $data['answer_options'] = $_POST['answer_options']; // Already JSON encoded from frontend
    } else {
        // Set to NULL for database when empty to avoid JSON validation errors
        $data['answer_options'] = null;
    }
    if (isset($_POST['photo_required'])) {
        $data['photo_required'] = intval($_POST['photo_required']);
    }
    if (isset($_POST['auto_advance'])) {
        $data['auto_advance'] = intval($_POST['auto_advance']);
    }
    
    $result = $wpdb->insert($clues_table, $data);
    
    if ($result === false) {
        wp_send_json_error('Database error: ' . $wpdb->last_error);
    }
    
    $new_clue_id = $wpdb->insert_id;
    
    // Provide detailed success message
    $success_message = 'Clue created successfully';
    if (isset($reorder_result) && $reorder_result > 0) {
        $success_message .= '. ' . $reorder_result . ' existing clue(s) were automatically renumbered.';
    }
    
    wp_send_json_success(['message' => $success_message, 'clue_id' => $new_clue_id, 'reordered_count' => isset($reorder_result) ? $reorder_result : 0]);
}
add_action('wp_ajax_create_clue', 'puzzlepath_create_clue_ajax');

/**
 * Quest Management Page
 */
function puzzlepath_quests_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    $clues_table = $wpdb->prefix . 'pp_clues';
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    $completions_table = $wpdb->prefix . 'pp_quest_completions';
    
    // Handle actions
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'delete':
                if (isset($_GET['event_id']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_quest_' . $_GET['event_id'])) {
                    $event_id = intval($_GET['event_id']);
                    
                    // Get quest details before deletion for logging
                    $quest = $wpdb->get_row($wpdb->prepare("SELECT hunt_code, title FROM {$events_table} WHERE id = %d", $event_id));
                    
                    if ($quest) {
                        // Delete associated clues first
                        $wpdb->delete($clues_table, ['hunt_id' => $event_id]);
                        
                        // Delete the quest/event
                        $wpdb->delete($events_table, ['id' => $event_id]);
                        
                        // Note: We're not deleting bookings or completions to preserve historical data
                        // If you want to delete those too, uncomment the following lines:
                        // $wpdb->delete($bookings_table, ['event_id' => $event_id]);
                        // $wpdb->delete($completions_table, ['event_id' => $event_id]);
                    }
                    
                    wp_redirect(admin_url('admin.php?page=puzzlepath-quests&message=deleted'));
                    exit;
                }
                break;
        }
    }
    
    // Get all quests/events with clue counts and booking stats
    $quests = $wpdb->get_results("
        SELECT 
            e.*,
            e.title as quest_name,
            e.hunt_code as quest_code,
            e.hunt_name,
            COALESCE(clue_counts.clue_count, 0) as clue_count,
            COALESCE(booking_stats.total_bookings, 0) as total_completions,
            COALESCE(booking_stats.paid_bookings, 0) as paid_completions,
            'quest' as quest_type,
            CASE 
                WHEN e.hosting_type IN ('hosted', 'self-hosted') THEN 1
                WHEN e.hosting_type = 'inactive' THEN 0
                ELSE 1
            END as is_active
        FROM {$events_table} e
        LEFT JOIN (
            SELECT hunt_id, COUNT(*) as clue_count 
            FROM {$clues_table} 
            WHERE is_active = 1 
            GROUP BY hunt_id
        ) clue_counts ON e.id = clue_counts.hunt_id
        LEFT JOIN (
            SELECT 
                event_id,
                COUNT(*) as total_bookings,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_bookings
            FROM {$bookings_table}
            GROUP BY event_id
        ) booking_stats ON e.id = booking_stats.event_id
        WHERE e.hunt_code IS NOT NULL AND e.hunt_code != ''
        ORDER BY e.created_at DESC
    ");
    
    ?>
    <div class="wrap">
        <h1>Quest Management 
            <a href="#" class="page-title-action" onclick="showAddQuestModal(); return false;">Add New Quest</a>
        </h1>
        
        <?php if (isset($_GET['message'])): ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php 
                    switch($_GET['message']) {
                        case 'added': echo 'Quest created successfully!'; break;
                        case 'updated': echo 'Quest updated successfully!'; break;
                        case 'activated': echo 'Quest activated successfully!'; break;
                        case 'deactivated': echo 'Quest deactivated successfully!'; break;
                        case 'deleted': echo 'Quest deleted successfully!'; break;
                    }
                    ?>
                </p>
            </div>
        <?php endif; ?>
        
        <!-- Quest Statistics -->
        <div class="quest-stats" style="display: flex; gap: 20px; margin-bottom: 20px;">
            <div class="stat-box" style="background: #fff; padding: 15px; border-left: 4px solid #2271b1; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0; color: #2271b1;">Total Quests</h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;"><?php echo count($quests); ?></p>
            </div>
            <div class="stat-box" style="background: #fff; padding: 15px; border-left: 4px solid #00a32a; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0; color: #00a32a;">Active Quests</h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;"><?php echo count(array_filter($quests, function($q) { return $q->display_on_site; })); ?></p>
            </div>
            <div class="stat-box" style="background: #fff; padding: 15px; border-left: 4px solid #dba617; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0; color: #dba617;">Total Clues</h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;"><?php echo array_sum(array_column($quests, 'clue_count')); ?></p>
            </div>
            <div class="stat-box" style="background: #fff; padding: 15px; border-left: 4px solid #d63638; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0; color: #d63638;">Total Completions</h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;"><?php echo array_sum(array_column($quests, 'total_completions')); ?></p>
            </div>
        </div>
        
        <!-- Search and Filter Controls -->
        <div class="quest-controls" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #c3c4c7; border-radius: 4px;">
            <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <label for="quest-search" style="font-weight: 600; margin-right: 10px;">🔍 Search:</label>
                    <input type="text" id="quest-search" placeholder="Search by quest name, code, or location..." 
                           style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;" 
                           onkeyup="filterQuests()" />
                </div>
                <div>
                    <label for="status-filter" style="font-weight: 600; margin-right: 8px;">📊 Status:</label>
                    <select id="status-filter" onchange="filterQuests()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="all">All Quests</option>
                        <option value="active">✅ Active Only</option>
                        <option value="hidden">❌ Hidden Only</option>
                    </select>
                </div>
                <div>
                    <label for="type-filter" style="font-weight: 600; margin-right: 8px;">🎯 Type:</label>
                    <select id="type-filter" onchange="filterQuests()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="all">All Types</option>
                        <option value="hosted">🟢 Live Events</option>
                        <option value="self-hosted">🔵 Anytime Quests</option>
                    </select>
                </div>
                <div>
                    <button onclick="clearFilters()" class="button" style="padding: 8px 16px;">🔄 Clear Filters</button>
                </div>
            </div>
            <div id="quest-count-display" style="margin-top: 10px; font-style: italic; color: #666;">Showing all quests</div>
        </div>
        
        <!-- Quests Table -->
        <table class="wp-list-table widefat fixed striped" id="quests-table">
            <thead>
                <tr>
                    <th data-sort="quest_code" class="sortable-header">
                        Quest Code
                        <span class="sort-indicator"></span>
                    </th>
                    <th data-sort="quest_name" class="sortable-header">
                        Quest Name
                        <span class="sort-indicator"></span>
                    </th>
                    <th data-sort="location" class="sortable-header">
                        Location
                        <span class="sort-indicator"></span>
                    </th>
                    <th data-sort="is_hosted_event" class="sortable-header">
                        Type
                        <span class="sort-indicator"></span>
                    </th>
                    <th>Clues</th>
                    <th>Duration</th>
                    <th>Completions</th>
                    <th data-sort="is_hidden" class="sortable-header">
                        Status
                        <span class="sort-indicator"></span>
                    </th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quests)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 20px;">No quests found. <a href="#" onclick="showAddQuestModal()">Create your first quest</a>.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($quests as $quest): ?>
                        <tr>
                            <td><strong><?php echo esc_html($quest->quest_code ?: $quest->hunt_code); ?></strong></td>
                            <td>
                                <strong><?php echo esc_html($quest->quest_name ?: $quest->title); ?></strong>
                                <?php if ($quest->hunt_name && $quest->hunt_name != $quest->title): ?>
                                    <br><small style="color: #666;"><?php echo esc_html($quest->hunt_name); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($quest->location ?: 'Not specified'); ?></td>
                            <td>
                                <span class="quest-type type-<?php echo esc_attr($quest->hosting_type); ?>" style="padding: 2px 6px; border-radius: 3px; font-size: 11px; text-transform: uppercase; color: white; background: <?php echo $quest->hosting_type === 'hosted' ? '#00a32a' : '#2271b1'; ?>;">
                                    <?php echo esc_html($quest->hosting_type === 'hosted' ? 'LIVE' : 'ANYTIME'); ?>
                                </span><br>
                                <small>Quest Type</small>
                            </td>
                            <td>
                                <strong><?php echo $quest->clue_count; ?></strong> clues
                            </td>
                            <td>
                                <?php 
                                if ($quest->duration_minutes && $quest->duration_minutes > 0) {
                                    $hours = floor($quest->duration_minutes / 60);
                                    $minutes = $quest->duration_minutes % 60;
                                    
                                    if ($hours > 0 && $minutes > 0) {
                                        $duration_text = $hours . 'h ' . $minutes . 'm';
                                    } elseif ($hours > 0) {
                                        $duration_text = $hours . ' hour' . ($hours > 1 ? 's' : '');
                                    } else {
                                        $duration_text = $minutes . ' min';
                                    }
                                    echo '<span style="color: #2271b1; font-weight: 600;">⏱️ ' . $duration_text . '</span>';
                                } else {
                                    echo '<span style="color: #999;">Duration: TBD</span>';
                                }
                                ?><br>
                                <small>Price: $<?php echo number_format($quest->price, 2); ?></small>
                                <?php if ($quest->event_date): ?>
                                    <br><small style="color: #666;">Next: <?php echo date('M j, Y', strtotime($quest->event_date)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo $quest->total_completions; ?></strong> times
                            </td>
                            <td style="text-align: center;">
                                <label class="status-toggle" style="display: inline-flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" 
                                           id="display_<?php echo $quest->id; ?>" 
                                           <?php echo $quest->display_on_site ? 'checked' : ''; ?> 
                                           onchange="toggleQuestDisplay(<?php echo $quest->id; ?>, this.checked)"
                                           style="margin: 0; margin-right: 8px;" />
                                    <span class="quest-status" style="padding: 4px 8px; border-radius: 3px; font-size: 11px; text-transform: uppercase; color: white; background: <?php echo $quest->display_on_site ? '#00a32a' : '#d63638'; ?>; font-weight: bold;">
                                        <?php echo $quest->display_on_site ? '✅ ACTIVE' : '❌ HIDDEN'; ?>
                                    </span>
                                </label>
                            </td>
                            <td>
                                <a href="#" onclick="showQuestDetails(<?php echo $quest->id; ?>); return false;" title="View Details">👁️</a>
                                <a href="#" onclick="editQuest(<?php echo $quest->id; ?>); return false;" title="Edit Quest">✏️</a>
                                <a href="#" onclick="manageClues(<?php echo $quest->id; ?>); return false;" title="Manage Clues">🧩</a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=puzzlepath-quests&action=delete&event_id=' . $quest->id), 'delete_quest_' . $quest->id); ?>" 
                                   onclick="return confirmDeleteQuest('<?php echo esc_js($quest->quest_name ?: $quest->title); ?>', '<?php echo esc_js($quest->quest_code ?: $quest->hunt_code); ?>');" 
                                   title="Delete Quest" style="color: #d63638; text-decoration: none;">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Modals will be added here -->
    <?php puzzlepath_quest_modals(); ?>
    
    <style>
    .difficulty-easy { background: #00a32a !important; }
    .difficulty-medium { background: #dba617 !important; }
    .difficulty-hard { background: #d63638 !important; }
    .difficulty-expert { background: #8c8f94 !important; }
    
    /* Quest Management Search & Sort Styles */
    .sortable-header {
        transition: all 0.2s ease;
        position: relative;
        padding-right: 20px !important;
    }
    
    .sortable-header:hover {
        background-color: #f0f0f1 !important;
        color: #2271b1 !important;
    }
    
    .sort-indicator {
        font-size: 12px;
        font-weight: bold;
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
    }
    
    .quest-controls {
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    #quest-search:focus,
    #status-filter:focus,
    #type-filter:focus {
        border-color: #2271b1;
        box-shadow: 0 0 0 1px #2271b1;
        outline: none;
    }
    
    #quest-count-display {
        font-size: 13px;
        font-weight: 500;
    }
    
    /* Responsive adjustments */
    @media (max-width: 782px) {
        .quest-controls > div:first-child {
            flex-direction: column;
            gap: 10px;
        }
        
        .quest-controls > div:first-child > div {
            min-width: unset;
            width: 100%;
        }
    }
    </style>
    
    <script>
    // Quest Details Modal
    function showQuestDetails(questId) {
        document.getElementById('quest-details-modal').style.display = 'block';
        document.getElementById('quest-details-content').innerHTML = 'Loading...';
        
        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'get_quest_details',
            quest_id: questId,
            nonce: '<?php echo wp_create_nonce('quest_details_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                document.getElementById('quest-details-content').innerHTML = response.data;
            } else {
                document.getElementById('quest-details-content').innerHTML = 'Error loading quest details: ' + (response.data || 'Unknown error');
            }
        }).fail(function(xhr, status, error) {
            document.getElementById('quest-details-content').innerHTML = 'AJAX Error: ' + error + '<br>Status: ' + status + '<br>Response: ' + xhr.responseText;
        });
    }
    
    function closeQuestDetails() {
        document.getElementById('quest-details-modal').style.display = 'none';
    }
    
    // Edit Quest Modal
    function editQuest(questId) {
        document.getElementById('edit-quest-modal').style.display = 'block';
        document.getElementById('edit-quest-content').innerHTML = 'Loading...';
        
        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'get_edit_quest_form',
            quest_id: questId,
            nonce: '<?php echo wp_create_nonce('edit_quest_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                document.getElementById('edit-quest-content').innerHTML = response.data;
            } else {
                document.getElementById('edit-quest-content').innerHTML = 'Error loading quest form: ' + (response.data || 'Unknown error');
            }
        }).fail(function(xhr, status, error) {
            document.getElementById('edit-quest-content').innerHTML = 'AJAX Error: ' + error;
        });
    }
    
    function closeEditQuest() {
        document.getElementById('edit-quest-modal').style.display = 'none';
    }
    
    function saveQuestChanges(questId) {
        var form = document.getElementById('edit-quest-form');
        var formData = new FormData(form);
        formData.append('action', 'save_quest_changes');
        formData.append('quest_id', questId);
        formData.append('nonce', '<?php echo wp_create_nonce('save_quest_nonce'); ?>');
        
        document.getElementById('quest-save-btn').disabled = true;
        document.getElementById('quest-save-btn').textContent = 'Saving...';
        
        jQuery.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Quest updated successfully!');
                    closeEditQuest();
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    document.getElementById('quest-save-btn').disabled = false;
                    document.getElementById('quest-save-btn').textContent = 'Save Changes';
                }
            },
            error: function() {
                alert('An error occurred while saving changes.');
                document.getElementById('quest-save-btn').disabled = false;
                document.getElementById('quest-save-btn').textContent = 'Save Changes';
            }
        });
    }
    
    // Manage Clues Modal
    function manageClues(questId) {
        document.getElementById('manage-clues-modal').style.display = 'block';
        document.getElementById('manage-clues-content').innerHTML = 'Loading...';
        
        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'get_quest_clues',
            quest_id: questId,
            nonce: '<?php echo wp_create_nonce('quest_clues_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                document.getElementById('manage-clues-content').innerHTML = response.data;
            } else {
                document.getElementById('manage-clues-content').innerHTML = 'Error loading clues: ' + (response.data || 'Unknown error');
            }
        }).fail(function(xhr, status, error) {
            document.getElementById('manage-clues-content').innerHTML = 'AJAX Error: ' + error;
        });
    }
    
    function closeManageClues() {
        document.getElementById('manage-clues-modal').style.display = 'none';
    }
    
    // Edit Clue Functions (Global scope)
    function editClue(clueId) {
        document.getElementById('edit-clue-modal').style.display = 'block';
        document.getElementById('edit-clue-content').innerHTML = 'Loading...';
        
        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'get_clue',
            clue_id: clueId,
            nonce: '<?php echo wp_create_nonce('edit_clue_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                showEditClueForm(response.data);
            } else {
                document.getElementById('edit-clue-content').innerHTML = 'Error loading clue: ' + (response.data || 'Unknown error');
            }
        }).fail(function(xhr, status, error) {
            document.getElementById('edit-clue-content').innerHTML = 'AJAX Error: ' + error;
        });
    }
    
    function closeEditClue() {
        document.getElementById('edit-clue-modal').style.display = 'none';
    }
    
    function showEditClueForm(clue) {
        var formHtml = `
            <h2 style="margin: 0 0 20px 0; padding-right: 40px;">Edit Clue #${clue.clue_order}</h2>
            
            <form id="edit-clue-form">
                <input type="hidden" id="edit-clue-id" value="${clue.id}">
                <input type="hidden" id="edit-hunt-id" value="${clue.hunt_id}">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label for="edit-clue-order"><strong>Clue Order:</strong></label>
                        <input type="number" id="edit-clue-order" value="${clue.clue_order}" min="1" style="width: 100%; padding: 5px; margin-top: 5px;" required>
                    </div>
                    
                    <div>
                        <label for="edit-clue-title"><strong>Title:</strong></label>
                        <input type="text" id="edit-clue-title" value="${clue.title || ''}" style="width: 100%; padding: 5px; margin-top: 5px;">
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="edit-clue-text"><strong>Clue Text:</strong></label>
                    <textarea id="edit-clue-text" style="width: 100%; height: 100px; padding: 5px; margin-top: 5px; resize: vertical;" required>${clue.clue_text || ''}</textarea>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="edit-task-description"><strong>Task Description:</strong></label>
                    <textarea id="edit-task-description" style="width: 100%; height: 80px; padding: 5px; margin-top: 5px; resize: vertical;">${clue.task_description || ''}</textarea>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="edit-hint-text"><strong>Hint Text:</strong></label>
                    <textarea id="edit-hint-text" style="width: 100%; height: 60px; padding: 5px; margin-top: 5px; resize: vertical;">${clue.hint_text || ''}</textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label for="edit-answer"><strong>Answer:</strong> <span style="color: #666;">(for reference)</span></label>
                        <input type="text" id="edit-answer" value="${clue.answer || ''}" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="Answer description or hint">
                    </div>
                    <div>
                        <label for="edit-required-answer"><strong>Required Answer:</strong> <span style="color: #d63638;">*</span></label>
                        <input type="text" id="edit-required-answer" value="${clue.required_answer || clue.answer || ''}" style="width: 100%; padding: 5px; margin-top: 5px;" required placeholder="Exact answer participants must provide">
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="edit-input-type"><strong>Input Type:</strong></label>
                    <select id="edit-input-type" style="width: 100%; padding: 5px; margin-top: 5px;" onchange="toggleEditInputOptions()">
                        <option value="none" ${(clue.input_type || 'none') === 'none' ? 'selected' : ''}>None (Just mark as complete)</option>
                        <option value="text" ${clue.input_type === 'text' ? 'selected' : ''}>Text Input</option>
                        <option value="number" ${clue.input_type === 'number' ? 'selected' : ''}>Number Input</option>
                        <option value="multiple_choice" ${clue.input_type === 'multiple_choice' ? 'selected' : ''}>Multiple Choice</option>
                        <option value="photo" ${clue.input_type === 'photo' ? 'selected' : ''}>Photo Upload</option>
                    </select>
                </div>
                
                <div id="edit-text-options" class="input-type-options" style="display: none; margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 5px;">
                    <label style="display: flex; align-items: center;">
                        <input type="checkbox" id="edit-is-case-sensitive" ${clue.is_case_sensitive ? 'checked' : ''} style="margin-right: 8px;">
                        <span style="font-weight: bold;">Case Sensitive Answer</span>
                    </label>
                </div>
                
                <div id="edit-number-options" class="input-type-options" style="display: none; margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 5px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label><strong>Min Value:</strong></label>
                            <input type="number" id="edit-min-value" value="${clue.min_value || ''}" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="Minimum allowed">
                        </div>
                        <div>
                            <label><strong>Max Value:</strong></label>
                            <input type="number" id="edit-max-value" value="${clue.max_value || ''}" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="Maximum allowed">
                        </div>
                    </div>
                </div>
                
                <div id="edit-multiple-choice-options" class="input-type-options" style="display: none; margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 5px;">
                    <label><strong>Answer Options:</strong> <small>(format: A: First option\nB: Second option)</small></label>
                    <textarea id="edit-answer-options" style="width: 100%; height: 80px; padding: 5px; margin-top: 5px; resize: vertical;" placeholder="A: First option\nB: Second option\nC: Third option">${clue.answer_options ? Object.entries(JSON.parse(clue.answer_options) || {}).map(([k,v]) => k + ': ' + v).join('\n') : ''}</textarea>
                </div>
                
                <div id="edit-photo-options" class="input-type-options" style="display: none; margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 5px;">
                    <label style="display: flex; align-items: center;">
                        <input type="checkbox" id="edit-photo-required" ${clue.photo_required ? 'checked' : ''} style="margin-right: 8px;">
                        <span style="font-weight: bold;">Photo Required</span>
                    </label>
                    <label style="display: flex; align-items: center; margin-top: 10px;">
                        <input type="checkbox" id="edit-auto-advance" ${clue.auto_advance ? 'checked' : ''} style="margin-right: 8px;">
                        <span style="font-weight: bold;">Auto Advance (proceed to next clue automatically)</span>
                    </label>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label for="edit-latitude"><strong>Latitude:</strong></label>
                        <input type="number" id="edit-latitude" value="${clue.latitude || ''}" step="any" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="e.g. -27.4698">
                    </div>
                    
                    <div>
                        <label for="edit-longitude"><strong>Longitude:</strong></label>
                        <input type="number" id="edit-longitude" value="${clue.longitude || ''}" step="any" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="e.g. 153.0251">
                    </div>
                    
                    <div>
                        <label for="edit-geofence-radius"><strong>Geofence Radius (m):</strong></label>
                        <input type="number" id="edit-geofence-radius" value="${clue.geofence_radius || ''}" min="1" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="e.g. 50">
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="edit-image-url"><strong>Image URL:</strong></label>
                    <input type="url" id="edit-image-url" value="${clue.image_url || ''}" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="https://example.com/image.jpg">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; font-weight: bold;">
                        <input type="checkbox" id="edit-is-active" ${clue.is_active ? 'checked' : ''} style="margin-right: 8px;">
                        Clue is Active
                    </label>
                </div>
                
                <div style="padding: 15px 0; border-top: 1px solid #ddd; text-align: right;">
                    <button type="button" class="button" onclick="closeEditClue()" style="margin-right: 10px;">Cancel</button>
                    <button type="button" class="button button-primary" id="save-clue-btn" onclick="saveClueChanges()">Save Changes</button>
                </div>
            </form>
        `;
        
        document.getElementById('edit-clue-content').innerHTML = formHtml;
        
        // Initialize the input type options display
        setTimeout(function() {
            toggleEditInputOptions();
        }, 100);
    }
    
    function saveClueChanges() {
        // Basic validation
        var clueOrder = document.getElementById('edit-clue-order').value;
        var clueText = document.getElementById('edit-clue-text').value.trim();
        var answer = document.getElementById('edit-answer').value.trim();
        var latitude = document.getElementById('edit-latitude').value;
        var longitude = document.getElementById('edit-longitude').value;
        
        if (!clueOrder || clueOrder < 1) {
            alert('Please enter a valid clue order (1 or greater)');
            return;
        }
        
        if (!clueText) {
            alert('Clue text is required');
            document.getElementById('edit-clue-text').focus();
            return;
        }
        
        var requiredAnswer = document.getElementById('edit-required-answer').value.trim();
        
        if (!requiredAnswer) {
            alert('Required Answer is required');
            document.getElementById('edit-required-answer').focus();
            return;
        }
        
        // Validate coordinates if provided
        if (latitude && (latitude < -90 || latitude > 90)) {
            alert('Latitude must be between -90 and 90 degrees');
            document.getElementById('edit-latitude').focus();
            return;
        }
        
        if (longitude && (longitude < -180 || longitude > 180)) {
            alert('Longitude must be between -180 and 180 degrees');
            document.getElementById('edit-longitude').focus();
            return;
        }
        
        var saveBtn = document.getElementById('save-clue-btn');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
        
        // Process answer options for multiple choice
        var answerOptions = null;
        if (document.getElementById('edit-input-type').value === 'multiple_choice') {
            var optionsText = document.getElementById('edit-answer-options').value.trim();
            if (optionsText) {
                var options = {};
                optionsText.split('\n').forEach(function(line) {
                    line = line.trim();
                    if (line && line.includes(':')) {
                        var parts = line.split(':', 2);
                        options[parts[0].trim()] = parts[1].trim();
                    }
                });
                answerOptions = JSON.stringify(options);
            }
        }
        
        var formData = {
            action: 'save_clue',
            clue_id: document.getElementById('edit-clue-id').value,
            clue_order: document.getElementById('edit-clue-order').value,
            title: document.getElementById('edit-clue-title').value,
            clue_text: document.getElementById('edit-clue-text').value,
            task_description: document.getElementById('edit-task-description').value,
            hint_text: document.getElementById('edit-hint-text').value,
            answer: document.getElementById('edit-answer').value,
            required_answer: document.getElementById('edit-required-answer').value,
            input_type: document.getElementById('edit-input-type').value,
            is_case_sensitive: document.getElementById('edit-is-case-sensitive') ? (document.getElementById('edit-is-case-sensitive').checked ? 1 : 0) : 0,
            min_value: document.getElementById('edit-min-value') ? document.getElementById('edit-min-value').value : '',
            max_value: document.getElementById('edit-max-value') ? document.getElementById('edit-max-value').value : '',
            answer_options: answerOptions,
            photo_required: document.getElementById('edit-photo-required') ? (document.getElementById('edit-photo-required').checked ? 1 : 0) : 0,
            auto_advance: document.getElementById('edit-auto-advance') ? (document.getElementById('edit-auto-advance').checked ? 1 : 0) : 0,
            latitude: document.getElementById('edit-latitude').value,
            longitude: document.getElementById('edit-longitude').value,
            geofence_radius: document.getElementById('edit-geofence-radius').value,
            image_url: document.getElementById('edit-image-url').value,
            is_active: document.getElementById('edit-is-active').checked ? 1 : 0,
            nonce: '<?php echo wp_create_nonce('save_clue_nonce'); ?>'
        };
        
        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', formData, function(response) {
            if (response.success) {
                alert('Clue updated successfully!');
                closeEditClue();
                // Refresh the clues list by reloading the current quest clues
                refreshCluesList();
            } else {
                alert('Error updating clue: ' + response.data);
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
            }
        }).fail(function() {
            alert('Network error occurred while saving clue.');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Changes';
        });
    }
    
    function refreshCluesList() {
        var currentModal = document.getElementById('manage-clues-modal');
        if (currentModal && currentModal.style.display === 'block') {
            // Get the current quest ID from the page content
            var questContent = document.getElementById('manage-clues-content').innerHTML;
            var questIdMatch = questContent.match(/manageClues\((\d+)\)/);
            
            if (questIdMatch) {
                var questId = questIdMatch[1];
                // Reload the clues for this quest
                document.getElementById('manage-clues-content').innerHTML = 'Loading...';
                
                jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'get_quest_clues',
                    quest_id: questId,
                    nonce: '<?php echo wp_create_nonce('quest_clues_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        document.getElementById('manage-clues-content').innerHTML = response.data;
                    } else {
                        document.getElementById('manage-clues-content').innerHTML = 'Error refreshing clues: ' + (response.data || 'Unknown error');
                    }
                }).fail(function(xhr, status, error) {
                    document.getElementById('manage-clues-content').innerHTML = 'AJAX Error: ' + error;
                });
            } else {
                // Fallback to page reload if we can't determine the quest ID
                location.reload();
            }
        }
    }
    
    function addNewClue(huntCode) {
        // We need to find the quest ID from the manage clues modal
        // First try to get it from the URL match in the modal content
        var questContent = document.getElementById('manage-clues-content').innerHTML;
        var questIdMatch = questContent.match(/manageClues\((\d+)\)/);
        
        if (questIdMatch) {
            var huntId = questIdMatch[1];
            showAddClueForm(huntId, huntCode);
            return;
        }
        
        // Fallback: try to extract from existing clue data
        var clueButton = document.querySelector('[onclick*="editClue"]');
        if (clueButton) {
            // Get the hunt_id from existing clue data by making a quick AJAX call
            var clueId = clueButton.getAttribute('onclick').match(/editClue\((\d+)\)/)[1];
            
            jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'get_clue',
                clue_id: clueId,
                nonce: '<?php echo wp_create_nonce('edit_clue_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    showAddClueForm(response.data.hunt_id, huntCode);
                } else {
                    alert('Error: Could not determine quest ID');
                }
            });
            return;
        }
        
        // Last resort: ask user to refresh
        alert('Error: Could not determine quest ID. Please close and reopen the Manage Clues modal.');
    }
    
    function showAddClueForm(huntId, huntCode) {
        document.getElementById('add-clue-modal').style.display = 'block';
        
        // Get next clue order number
        var clueItems = document.querySelectorAll('.clue-item');
        var maxOrder = 0;
        clueItems.forEach(function(item) {
            var orderMatch = item.innerHTML.match(/Clue #(\d+)/);
            if (orderMatch) {
                maxOrder = Math.max(maxOrder, parseInt(orderMatch[1]));
            }
        });
        var nextOrder = maxOrder + 1;
        
        var formHtml = `
            <h2 style="margin: 0 0 20px 0; padding-right: 40px;">Add New Clue #${nextOrder}</h2>
            
            <form id="add-clue-form">
                <input type="hidden" id="add-hunt-id" value="${huntId}">
                <input type="hidden" id="add-hunt-code" value="${huntCode}">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label for="add-clue-order"><strong>Clue Order:</strong></label>
                        <input type="number" id="add-clue-order" value="${nextOrder}" min="1" style="width: 100%; padding: 5px; margin-top: 5px;" required>
                    </div>
                    
                    <div>
                        <label for="add-clue-title"><strong>Title:</strong></label>
                        <input type="text" id="add-clue-title" value="" style="width: 100%; padding: 5px; margin-top: 5px;">
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="add-clue-text"><strong>Clue Text:</strong> <span style="color: #d63638;">*</span></label>
                    <textarea id="add-clue-text" style="width: 100%; height: 100px; padding: 5px; margin-top: 5px; resize: vertical;" required placeholder="Enter the clue text that participants will see..."></textarea>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="add-task-description"><strong>Task Description:</strong></label>
                    <textarea id="add-task-description" style="width: 100%; height: 80px; padding: 5px; margin-top: 5px; resize: vertical;" placeholder="Optional: Describe what participants need to do..."></textarea>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="add-hint-text"><strong>Hint Text:</strong></label>
                    <textarea id="add-hint-text" style="width: 100%; height: 60px; padding: 5px; margin-top: 5px; resize: vertical;" placeholder="Optional: Provide a hint if participants get stuck..."></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label for="add-answer"><strong>Answer:</strong> <span style="color: #666;">(for reference)</span></label>
                        <input type="text" id="add-answer" value="" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="Answer description or hint">
                    </div>
                    <div>
                        <label for="add-required-answer"><strong>Required Answer:</strong> <span style="color: #d63638;">*</span></label>
                        <input type="text" id="add-required-answer" value="" style="width: 100%; padding: 5px; margin-top: 5px;" required placeholder="Exact answer participants must provide">
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="add-input-type"><strong>Input Type:</strong></label>
                    <select id="add-input-type" style="width: 100%; padding: 5px; margin-top: 5px;" onchange="toggleAddInputOptions()">
                        <option value="text" selected>Text Input</option>
                        <option value="none">None (Just mark as complete)</option>
                        <option value="number">Number Input</option>
                        <option value="multiple_choice">Multiple Choice</option>
                        <option value="photo">Photo Upload</option>
                    </select>
                </div>
                
                <div id="add-text-options" class="input-type-options" style="display: block; margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 5px;">
                    <label style="display: flex; align-items: center;">
                        <input type="checkbox" id="add-is-case-sensitive" style="margin-right: 8px;">
                        <span style="font-weight: bold;">Case Sensitive Answer</span>
                    </label>
                </div>
                
                <div id="add-number-options" class="input-type-options" style="display: none; margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 5px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label><strong>Min Value:</strong></label>
                            <input type="number" id="add-min-value" value="" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="Minimum allowed">
                        </div>
                        <div>
                            <label><strong>Max Value:</strong></label>
                            <input type="number" id="add-max-value" value="" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="Maximum allowed">
                        </div>
                    </div>
                </div>
                
                <div id="add-multiple-choice-options" class="input-type-options" style="display: none; margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 5px;">
                    <label><strong>Answer Options:</strong> <small>(format: A: First option\nB: Second option)</small></label>
                    <textarea id="add-answer-options" style="width: 100%; height: 80px; padding: 5px; margin-top: 5px; resize: vertical;" placeholder="A: First option\nB: Second option\nC: Third option"></textarea>
                </div>
                
                <div id="add-photo-options" class="input-type-options" style="display: none; margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 5px;">
                    <label style="display: flex; align-items: center;">
                        <input type="checkbox" id="add-photo-required" style="margin-right: 8px;">
                        <span style="font-weight: bold;">Photo Required</span>
                    </label>
                    <label style="display: flex; align-items: center; margin-top: 10px;">
                        <input type="checkbox" id="add-auto-advance" style="margin-right: 8px;">
                        <span style="font-weight: bold;">Auto Advance (proceed to next clue automatically)</span>
                    </label>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label for="add-latitude"><strong>Latitude:</strong></label>
                        <input type="number" id="add-latitude" value="" step="any" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="e.g. -27.4698">
                    </div>
                    
                    <div>
                        <label for="add-longitude"><strong>Longitude:</strong></label>
                        <input type="number" id="add-longitude" value="" step="any" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="e.g. 153.0251">
                    </div>
                    
                    <div>
                        <label for="add-geofence-radius"><strong>Geofence Radius (m):</strong></label>
                        <input type="number" id="add-geofence-radius" value="" min="1" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="e.g. 50">
                    </div>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label for="add-image-url"><strong>Image URL:</strong></label>
                    <input type="url" id="add-image-url" value="" style="width: 100%; padding: 5px; margin-top: 5px;" placeholder="https://example.com/image.jpg">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; font-weight: bold;">
                        <input type="checkbox" id="add-is-active" checked style="margin-right: 8px;">
                        Clue is Active
                    </label>
                </div>
                
                <div style="padding: 15px 0; border-top: 1px solid #ddd; text-align: right;">
                    <button type="button" class="button" onclick="closeAddClue()" style="margin-right: 10px;">Cancel</button>
                    <button type="button" class="button button-primary" id="create-clue-btn" onclick="createNewClue()">Create Clue</button>
                </div>
            </form>
        `;
        
        document.getElementById('add-clue-content').innerHTML = formHtml;
    }
    
    function closeAddClue() {
        document.getElementById('add-clue-modal').style.display = 'none';
    }
    
    // Toggle input type options for edit modal
    function toggleEditInputOptions() {
        // Hide all options first
        document.querySelectorAll('#edit-clue-modal .input-type-options').forEach(function(div) {
            div.style.display = 'none';
        });
        
        // Show the relevant options
        var inputType = document.getElementById('edit-input-type').value;
        var optionsDiv = document.getElementById('edit-' + inputType + '-options');
        if (optionsDiv) {
            optionsDiv.style.display = 'block';
        }
    }
    
    // Toggle input type options for add modal
    function toggleAddInputOptions() {
        // Hide all options first
        document.querySelectorAll('#add-clue-modal .input-type-options').forEach(function(div) {
            div.style.display = 'none';
        });
        
        // Show the relevant options
        var inputType = document.getElementById('add-input-type').value;
        var optionsDiv = document.getElementById('add-' + inputType + '-options');
        if (optionsDiv) {
            optionsDiv.style.display = 'block';
        }
    }
    
    function createNewClue() {
        // Basic validation
        var clueOrder = document.getElementById('add-clue-order').value;
        var clueText = document.getElementById('add-clue-text').value.trim();
        var answer = document.getElementById('add-answer').value.trim();
        var latitude = document.getElementById('add-latitude').value;
        var longitude = document.getElementById('add-longitude').value;
        
        if (!clueOrder || clueOrder < 1) {
            alert('Please enter a valid clue order (1 or greater)');
            return;
        }
        
        if (!clueText) {
            alert('Clue text is required');
            document.getElementById('add-clue-text').focus();
            return;
        }
        
        var requiredAnswer = document.getElementById('add-required-answer').value.trim();
        
        if (!requiredAnswer) {
            alert('Required Answer is required');
            document.getElementById('add-required-answer').focus();
            return;
        }
        
        // Validate coordinates if provided
        if (latitude && (latitude < -90 || latitude > 90)) {
            alert('Latitude must be between -90 and 90 degrees');
            document.getElementById('add-latitude').focus();
            return;
        }
        
        if (longitude && (longitude < -180 || longitude > 180)) {
            alert('Longitude must be between -180 and 180 degrees');
            document.getElementById('add-longitude').focus();
            return;
        }
        
        // Check if this clue order already exists and warn about reordering
        var requestedOrder = parseInt(clueOrder);
        var existingClues = document.querySelectorAll('.clue-item');
        var existingOrders = [];
        var willCauseReordering = false;
        var affectedClues = [];
        
        existingClues.forEach(function(item) {
            var orderMatch = item.innerHTML.match(/Clue #(\d+)/);
            if (orderMatch) {
                var existingOrder = parseInt(orderMatch[1]);
                existingOrders.push(existingOrder);
                
                if (existingOrder >= requestedOrder) {
                    willCauseReordering = true;
                    affectedClues.push(existingOrder);
                }
            }
        });
        
        // Show warning if reordering will occur
        if (willCauseReordering) {
            var maxAffected = Math.max(...affectedClues);
            var message = `⚠️ REORDERING NOTICE\n\n` +
                         `Adding a new clue at position ${requestedOrder} will cause all remaining clues to be renumbered:\n\n`;
            
            affectedClues.sort((a, b) => a - b).forEach(function(order) {
                message += `• Clue #${order} will become Clue #${order + 1}\n`;
            });
            
            message += `\nThis will affect ${affectedClues.length} existing clue(s).\n\n` +
                      `Do you want to continue with the reordering?`;
            
            if (!confirm(message)) {
                return; // User cancelled
            }
        }
        
        var createBtn = document.getElementById('create-clue-btn');
        createBtn.disabled = true;
        createBtn.textContent = 'Creating...';
        
        // Process answer options for multiple choice
        var answerOptions = null;
        if (document.getElementById('add-input-type').value === 'multiple_choice') {
            var optionsText = document.getElementById('add-answer-options').value.trim();
            if (optionsText) {
                var options = {};
                optionsText.split('\n').forEach(function(line) {
                    line = line.trim();
                    if (line && line.includes(':')) {
                        var parts = line.split(':', 2);
                        options[parts[0].trim()] = parts[1].trim();
                    }
                });
                answerOptions = JSON.stringify(options);
            }
        }
        
        var formData = {
            action: 'create_clue',
            hunt_id: document.getElementById('add-hunt-id').value,
            clue_order: document.getElementById('add-clue-order').value,
            title: document.getElementById('add-clue-title').value,
            clue_text: document.getElementById('add-clue-text').value,
            task_description: document.getElementById('add-task-description').value,
            hint_text: document.getElementById('add-hint-text').value,
            answer: document.getElementById('add-answer').value,
            required_answer: document.getElementById('add-required-answer').value,
            input_type: document.getElementById('add-input-type').value,
            is_case_sensitive: document.getElementById('add-is-case-sensitive') ? (document.getElementById('add-is-case-sensitive').checked ? 1 : 0) : 0,
            min_value: document.getElementById('add-min-value') ? document.getElementById('add-min-value').value : '',
            max_value: document.getElementById('add-max-value') ? document.getElementById('add-max-value').value : '',
            answer_options: answerOptions,
            photo_required: document.getElementById('add-photo-required') ? (document.getElementById('add-photo-required').checked ? 1 : 0) : 0,
            auto_advance: document.getElementById('add-auto-advance') ? (document.getElementById('add-auto-advance').checked ? 1 : 0) : 0,
            latitude: document.getElementById('add-latitude').value,
            longitude: document.getElementById('add-longitude').value,
            geofence_radius: document.getElementById('add-geofence-radius').value,
            image_url: document.getElementById('add-image-url').value,
            is_active: document.getElementById('add-is-active').checked ? 1 : 0,
            nonce: '<?php echo wp_create_nonce('create_clue_nonce'); ?>'
        };
        
        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', formData, function(response) {
            if (response.success) {
                // Show detailed success message including reordering info
                var message = response.data.message || 'Clue created successfully!';
                if (response.data.reordered_count > 0) {
                    message += '\n\n✅ ' + response.data.reordered_count + ' existing clue(s) were automatically renumbered to make room for your new clue.';
                }
                alert(message);
                closeAddClue();
                // Refresh the clues list
                refreshCluesList();
            } else {
                alert('Error creating clue: ' + response.data);
                createBtn.disabled = false;
                createBtn.textContent = 'Create Clue';
            }
        }).fail(function() {
            alert('Network error occurred while creating clue.');
            createBtn.disabled = false;
            createBtn.textContent = 'Create Clue';
        });
    }
    
    // Add Quest Modal
    function showAddQuestModal() {
        document.getElementById('add-quest-modal').style.display = 'block';
        document.getElementById('add-quest-content').innerHTML = 'Loading...';
        
        jQuery.post(ajaxurl, {
            action: 'get_add_quest_form',
            nonce: '<?php echo wp_create_nonce('add_quest_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                document.getElementById('add-quest-content').innerHTML = response.data;
            } else {
                document.getElementById('add-quest-content').innerHTML = 'Error loading form.';
            }
        });
    }
    
    function closeAddQuest() {
        document.getElementById('add-quest-modal').style.display = 'none';
    }
    
    function createNewQuest() {
        var form = document.getElementById('add-quest-form');
        var formData = new FormData(form);
        formData.append('action', 'create_new_quest');
        formData.append('nonce', '<?php echo wp_create_nonce('create_quest_nonce'); ?>');
        
        document.getElementById('create-quest-btn').disabled = true;
        document.getElementById('create-quest-btn').textContent = 'Creating...';
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Quest created successfully!');
                    closeAddQuest();
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    document.getElementById('create-quest-btn').disabled = false;
                    document.getElementById('create-quest-btn').textContent = 'Create Quest';
                }
            },
            error: function() {
                alert('An error occurred while creating quest.');
                document.getElementById('create-quest-btn').disabled = false;
                document.getElementById('create-quest-btn').textContent = 'Create Quest';
            }
        });
    }
    
    // Medal Image Functions
    function removeMedalImage() {
        if (confirm('Are you sure you want to remove the current medal image?')) {
            document.getElementById('current-medal-image').style.display = 'none';
            document.getElementById('edit-medal-image-url').value = '';
            document.getElementById('edit-medal-image').value = '';
        }
    }
    
    // Handle medal image file selection
    document.addEventListener('DOMContentLoaded', function() {
        // Add event listener when the edit modal content is loaded
        jQuery(document).on('change', '#edit-medal-image', function(e) {
            var file = e.target.files[0];
            if (file) {
                // Validate file size (4MB max)
                if (file.size > 4 * 1024 * 1024) {
                    alert('File size must be less than 4MB');
                    e.target.value = '';
                    return;
                }
                
                // Validate file type
                if (!file.type.match('image.*')) {
                    alert('Please select a valid image file');
                    e.target.value = '';
                    return;
                }
                
                // Show preview
                var reader = new FileReader();
                reader.onload = function(e) {
                    var currentImage = document.getElementById('current-medal-image');
                    if (currentImage) {
                        currentImage.querySelector('img').src = e.target.result;
                        currentImage.querySelector('p small').textContent = 'New medal image (preview)';
                        currentImage.style.display = 'block';
                    } else {
                        // Create preview if none exists
                        var preview = '<div id="current-medal-image" style="margin-bottom: 10px;">' +
                                     '<img src="' + e.target.result + '" alt="Medal Preview" style="max-width: 100px; max-height: 100px; border: 2px solid #ddd; border-radius: 5px;" />' +
                                     '<p><small>New medal image (preview)</small></p>' +
                                     '</div>';
                        document.getElementById('medal-image-container').insertAdjacentHTML('afterbegin', preview);
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    });
    
    // Toggle Quest Display/Status on Site
    function toggleQuestDisplay(questId, isDisplayed) {
        var statusSpan = document.querySelector('#display_' + questId).parentNode.querySelector('.quest-status');
        var originalText = statusSpan.textContent;
        var originalBg = statusSpan.style.background;
        
        statusSpan.textContent = '⏳ UPDATING...';
        statusSpan.style.background = '#999';
        
        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'toggle_quest_display',
            quest_id: questId,
            display_on_site: isDisplayed ? 1 : 0,
            nonce: '<?php echo wp_create_nonce('toggle_quest_display_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                statusSpan.textContent = isDisplayed ? '✅ ACTIVE' : '❌ HIDDEN';
                statusSpan.style.background = isDisplayed ? '#00a32a' : '#d63638';
            } else {
                alert('Error: ' + response.data);
                document.getElementById('display_' + questId).checked = !isDisplayed;
                statusSpan.textContent = originalText;
                statusSpan.style.background = originalBg;
            }
        }).fail(function() {
            alert('Network error occurred while updating quest status');
            document.getElementById('display_' + questId).checked = !isDisplayed;
            statusSpan.textContent = originalText;
            statusSpan.style.background = originalBg;
        });
    }
    
    // Delete Quest Confirmation
    function confirmDeleteQuest(questName, questCode) {
        var message = 'Are you sure you want to delete the quest "' + questName + '" (' + questCode + ')?\n\n' +
                     'This action will permanently delete:\n' +
                     '• The quest and all its details\n' +
                     '• All associated clues\n\n' +
                     'This action cannot be undone!\n\n' +
                     'Note: Booking history and completion records will be preserved.';
        
        return confirm(message);
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        var detailsModal = document.getElementById('quest-details-modal');
        var editModal = document.getElementById('edit-quest-modal');
        var cluesModal = document.getElementById('manage-clues-modal');
        var addModal = document.getElementById('add-quest-modal');
        var editClueModal = document.getElementById('edit-clue-modal');
        var addClueModal = document.getElementById('add-clue-modal');
        
        if (event.target == detailsModal) {
            detailsModal.style.display = 'none';
        } else if (event.target == editModal) {
            editModal.style.display = 'none';
        } else if (event.target == cluesModal) {
            cluesModal.style.display = 'none';
        } else if (event.target == addModal) {
            addModal.style.display = 'none';
        } else if (event.target == editClueModal) {
            editClueModal.style.display = 'none';
        } else if (event.target == addClueModal) {
            addClueModal.style.display = 'none';
        }
    }
    
    // QUEST TABLE SEARCH AND SORTING FUNCTIONALITY
    let questTableRows = [];
    let currentSort = null;
    let currentSortDirection = 'asc';
    
    // Initialize quest table on page load
    document.addEventListener('DOMContentLoaded', function() {
        initQuestTable();
    });
    
    function initQuestTable() {
        const table = document.getElementById('quests-table');
        if (!table) return;
        
        const tbody = table.querySelector('tbody');
        questTableRows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));
        
        // Add click handlers to sortable headers
        document.querySelectorAll('.sortable-header').forEach(header => {
            header.addEventListener('click', function() {
                const sortBy = this.dataset.sort;
                sortQuestsTable(sortBy);
            });
            
            // Style sortable headers
            header.style.cursor = 'pointer';
            header.style.userSelect = 'none';
            header.title = 'Click to sort';
        });
        
        updateQuestCount();
    }
    
    function filterQuests() {
        const searchTerm = document.getElementById('quest-search').value.toLowerCase();
        const statusFilter = document.getElementById('status-filter').value;
        const typeFilter = document.getElementById('type-filter').value;
        
        let visibleCount = 0;
        
        questTableRows.forEach(row => {
            const questCode = row.cells[0]?.textContent.toLowerCase() || '';
            const questName = row.cells[1]?.textContent.toLowerCase() || '';
            const location = row.cells[2]?.textContent.toLowerCase() || '';
            const statusCell = row.cells[7]?.querySelector('.quest-status')?.textContent || '';
            const typeCell = row.cells[3]?.textContent.toLowerCase() || '';
            
            // Search filter
            const matchesSearch = searchTerm === '' || 
                questCode.includes(searchTerm) || 
                questName.includes(searchTerm) || 
                location.includes(searchTerm);
            
            // Status filter
            let matchesStatus = true;
            if (statusFilter === 'active') {
                matchesStatus = statusCell.includes('ACTIVE');
            } else if (statusFilter === 'hidden') {
                matchesStatus = statusCell.includes('HIDDEN');
            }
            
            // Type filter
            let matchesType = true;
            if (typeFilter === 'hosted') {
                matchesType = typeCell.includes('live');
            } else if (typeFilter === 'self-hosted') {
                matchesType = typeCell.includes('anytime');
            }
            
            const shouldShow = matchesSearch && matchesStatus && matchesType;
            row.style.display = shouldShow ? '' : 'none';
            
            if (shouldShow) visibleCount++;
        });
        
        updateQuestCount(visibleCount);
    }
    
    function clearFilters() {
        document.getElementById('quest-search').value = '';
        document.getElementById('status-filter').value = 'all';
        document.getElementById('type-filter').value = 'all';
        filterQuests();
    }
    
    function sortQuestsTable(sortBy) {
        const table = document.getElementById('quests-table');
        const tbody = table.querySelector('tbody');
        
        // Update sort direction
        if (currentSort === sortBy) {
            currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            currentSortDirection = 'asc';
        }
        currentSort = sortBy;
        
        // Update visual indicators
        updateSortIndicators(sortBy, currentSortDirection);
        
        // Get column index for sorting
        let columnIndex;
        const headers = document.querySelectorAll('.sortable-header');
        headers.forEach((header, index) => {
            if (header.dataset.sort === sortBy) {
                columnIndex = index;
            }
        });
        
        // Sort rows
        const sortedRows = [...questTableRows].sort((a, b) => {
            let aValue = '';
            let bValue = '';
            
            switch (sortBy) {
                case 'quest_code':
                    aValue = a.cells[0]?.textContent.trim() || '';
                    bValue = b.cells[0]?.textContent.trim() || '';
                    break;
                case 'quest_name':
                    aValue = a.cells[1]?.textContent.trim() || '';
                    bValue = b.cells[1]?.textContent.trim() || '';
                    break;
                case 'location':
                    aValue = a.cells[2]?.textContent.trim() || '';
                    bValue = b.cells[2]?.textContent.trim() || '';
                    break;
                case 'is_hosted_event':
                    const aType = a.cells[3]?.textContent.toLowerCase() || '';
                    const bType = b.cells[3]?.textContent.toLowerCase() || '';
                    aValue = aType.includes('live') ? 'hosted' : 'self';
                    bValue = bType.includes('live') ? 'hosted' : 'self';
                    break;
                case 'is_hidden':
                    const aStatus = a.cells[7]?.querySelector('.quest-status')?.textContent || '';
                    const bStatus = b.cells[7]?.querySelector('.quest-status')?.textContent || '';
                    aValue = aStatus.includes('ACTIVE') ? 'active' : 'hidden';
                    bValue = bStatus.includes('ACTIVE') ? 'active' : 'hidden';
                    break;
                default:
                    aValue = a.cells[columnIndex]?.textContent.trim() || '';
                    bValue = b.cells[columnIndex]?.textContent.trim() || '';
            }
            
            // Natural sort for strings with numbers
            const comparison = aValue.localeCompare(bValue, undefined, {
                numeric: true,
                sensitivity: 'base'
            });
            
            return currentSortDirection === 'asc' ? comparison : -comparison;
        });
        
        // Clear and re-append sorted rows
        tbody.innerHTML = '';
        
        // Check if we have any quests
        if (questTableRows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 20px;">No quests found. <a href="#" onclick="showAddQuestModal()">Create your first quest</a>.</td></tr>';
        } else {
            sortedRows.forEach(row => tbody.appendChild(row));
        }
        
        // Refresh the quest rows array with new order
        questTableRows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));
        
        // Re-apply filters to maintain visibility
        filterQuests();
    }
    
    function updateSortIndicators(activeSort, direction) {
        // Clear all indicators
        document.querySelectorAll('.sort-indicator').forEach(indicator => {
            indicator.innerHTML = '';
            indicator.parentElement.style.color = '';
        });
        
        // Set active indicator
        const activeHeader = document.querySelector(`[data-sort="${activeSort}"]`);
        if (activeHeader) {
            const indicator = activeHeader.querySelector('.sort-indicator');
            indicator.innerHTML = direction === 'asc' ? ' ↑' : ' ↓';
            activeHeader.style.color = '#2271b1';
            activeHeader.style.fontWeight = 'bold';
        }
    }
    
    function updateQuestCount(visibleCount = null) {
        const display = document.getElementById('quest-count-display');
        const total = questTableRows.length;
        
        if (visibleCount === null) {
            display.textContent = `Showing all ${total} quest${total !== 1 ? 's' : ''}`;
        } else if (visibleCount === total) {
            display.textContent = `Showing all ${total} quest${total !== 1 ? 's' : ''}`;
        } else {
            display.textContent = `Showing ${visibleCount} of ${total} quest${total !== 1 ? 's' : ''}`;
        }
        
        // Update display color based on filter status
        display.style.color = visibleCount !== null && visibleCount < total ? '#d63638' : '#666';
    }
    </script>
    <?php
}

/**
 * Handle medal image upload
 */
function puzzlepath_handle_medal_image_upload($file) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'error' => 'Upload error: ' . $file['error']
        ];
    }
    
    // Validate file size (4MB max)
    if ($file['size'] > 4 * 1024 * 1024) {
        return [
            'success' => false,
            'error' => 'File size must be less than 4MB'
        ];
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        return [
            'success' => false,
            'error' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.'
        ];
    }
    
    // Set up upload directory
    $upload_dir = wp_upload_dir();
    $puzzlepath_dir = $upload_dir['basedir'] . '/puzzlepath-medals';
    $puzzlepath_url = $upload_dir['baseurl'] . '/puzzlepath-medals';
    
    // Create directory if it doesn't exist
    if (!file_exists($puzzlepath_dir)) {
        wp_mkdir_p($puzzlepath_dir);
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'medal-' . time() . '-' . wp_generate_password(8, false) . '.' . $file_extension;
    $file_path = $puzzlepath_dir . '/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return [
            'success' => true,
            'url' => $puzzlepath_url . '/' . $filename,
            'path' => $file_path
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Failed to move uploaded file'
        ];
    }
}

/**
 * Quest modals container
 */
function puzzlepath_quest_modals() {
    ?>
    <!-- Quest Details Modal -->
    <div id="quest-details-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;">
        <div style="background-color: #fefefe; margin: 50px auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 700px; border-radius: 5px; max-height: calc(100vh - 100px); overflow-y: auto; position: relative; min-height: 200px;">
            <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px;" onclick="closeQuestDetails()">&times;</span>
            <div id="quest-details-content" style="margin-top: 10px;">
                Loading...
            </div>
        </div>
    </div>
    
    <!-- Edit Quest Modal -->
    <div id="edit-quest-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;">
        <div style="background-color: #fefefe; margin: 20px auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 700px; border-radius: 5px; max-height: calc(100vh - 40px); overflow-y: auto; position: relative; min-height: 200px;">
            <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px; z-index: 10;" onclick="closeEditQuest()">&times;</span>
            <div id="edit-quest-content" style="margin-top: 10px; padding-right: 10px;">
                Loading...
            </div>
        </div>
    </div>
    
    <!-- Manage Clues Modal -->
    <div id="manage-clues-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;">
        <div style="background-color: #fefefe; margin: 20px auto; padding: 20px; border: 1px solid #888; width: 95%; max-width: 900px; border-radius: 5px; max-height: calc(100vh - 40px); overflow-y: auto; position: relative; min-height: 200px;">
            <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px; z-index: 10;" onclick="closeManageClues()">&times;</span>
            <div id="manage-clues-content" style="margin-top: 10px; padding-right: 10px;">
                Loading...
            </div>
        </div>
    </div>
    
    <!-- Add Quest Modal -->
    <div id="add-quest-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;">
        <div style="background-color: #fefefe; margin: 20px auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 700px; border-radius: 5px; max-height: calc(100vh - 40px); overflow-y: auto; position: relative; min-height: 200px;">
            <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px; z-index: 10;" onclick="closeAddQuest()">&times;</span>
            <div id="add-quest-content" style="margin-top: 10px; padding-right: 10px;">
                Loading...
            </div>
        </div>
    </div>
    
    <!-- Edit Clue Modal -->
    <div id="edit-clue-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;">
        <div style="background-color: #fefefe; margin: 20px auto; padding: 20px; border: 1px solid #888; width: 95%; max-width: 800px; border-radius: 5px; max-height: calc(100vh - 40px); overflow-y: auto; position: relative; min-height: 200px;">
            <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px; z-index: 10;" onclick="closeEditClue()">&times;</span>
            <div id="edit-clue-content" style="margin-top: 10px; padding-right: 10px;">
                Loading...
            </div>
        </div>
    </div>
    
    <!-- Add New Clue Modal -->
    <div id="add-clue-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;">
        <div style="background-color: #fefefe; margin: 20px auto; padding: 20px; border: 1px solid #888; width: 95%; max-width: 800px; border-radius: 5px; max-height: calc(100vh - 40px); overflow-y: auto; position: relative; min-height: 200px;">
            <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px; z-index: 10;" onclick="closeAddClue()">&times;</span>
            <div id="add-clue-content" style="margin-top: 10px; padding-right: 10px;">
                Loading...
            </div>
        </div>
    </div>
    <?php
}

/**
 * Email Settings functionality
 */
function puzzlepath_email_settings_init() {
    // Register email settings
    register_setting('puzzlepath_email_settings', 'puzzlepath_sender_email', 'sanitize_email');
    register_setting('puzzlepath_email_settings', 'puzzlepath_sender_name', 'sanitize_text_field');
    register_setting('puzzlepath_email_settings', 'puzzlepath_email_template');
    register_setting('puzzlepath_email_settings', 'puzzlepath_use_default_template');
    
    // Add settings section
    add_settings_section(
        'puzzlepath_email_section',
        'Email Configuration',
        'puzzlepath_email_section_callback',
        'puzzlepath-email-settings'
    );
    
    // Add sender email field
    add_settings_field(
        'puzzlepath_sender_email',
        'Sender Email Address',
        'puzzlepath_sender_email_callback',
        'puzzlepath-email-settings',
        'puzzlepath_email_section'
    );
    
    // Add sender name field
    add_settings_field(
        'puzzlepath_sender_name',
        'Sender Name',
        'puzzlepath_sender_name_callback',
        'puzzlepath-email-settings',
        'puzzlepath_email_section'
    );
    
    // Add email template field
    add_settings_field(
        'puzzlepath_email_template',
        'Email Template',
        'puzzlepath_email_template_callback',
        'puzzlepath-email-settings',
        'puzzlepath_email_section'
    );
}
add_action('admin_init', 'puzzlepath_email_settings_init');

function puzzlepath_email_section_callback() {
    echo '<p>Configure the email address and name that will appear as the sender for all PuzzlePath booking confirmation emails.</p>';
}

function puzzlepath_sender_email_callback() {
    $value = get_option('puzzlepath_sender_email', get_bloginfo('admin_email'));
    echo '<input type="email" name="puzzlepath_sender_email" value="' . esc_attr($value) . '" class="regular-text" required />';
    echo '<p class="description">The email address that booking confirmations will be sent from. Defaults to your WordPress admin email.</p>';
}

function puzzlepath_sender_name_callback() {
    $value = get_option('puzzlepath_sender_name', 'PuzzlePath Team');
    echo '<input type="text" name="puzzlepath_sender_name" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description">The name that will appear as the sender of booking confirmation emails.</p>';
}

function puzzlepath_email_template_callback() {
    $template = get_option('puzzlepath_email_template', puzzlepath_get_default_html_template());
    wp_editor(
        $template,
        'puzzlepath_email_template',
        array(
            'textarea_name' => 'puzzlepath_email_template',
            'textarea_rows' => 15,
            'media_buttons' => true,
            'teeny' => false,
            'tinymce' => true,
            'quicktags' => true
        )
    );
    echo '<p class="description">This template supports full HTML. The email will be sent as HTML with automatic plain-text fallback for better deliverability.</p>';
    echo '<p class="description"><strong>Available placeholders:</strong> {name}, {event_title}, {event_date}, {price}, {booking_code}, {logo_url}, {app_url}</p>';
}

function puzzlepath_email_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Handle reset to default template
    if (isset($_POST['reset_template']) && wp_verify_nonce($_POST['_wpnonce'], 'reset-template')) {
        update_option('puzzlepath_email_template', puzzlepath_get_default_html_template());
        echo '<div class="notice notice-success is-dismissible"><p>Email template has been reset to the default HTML template.</p></div>';
    }
    
    ?>
    <div class="wrap">
        <h1>📧 Email Settings</h1>
        
        <!-- Email Template Management -->
        <div class="card" style="margin-bottom: 20px;">
            <h2>📧 Email Template Reset</h2>
            <p>Reset to the professional default HTML template if you've made changes and want to start fresh.</p>
            
            <form method="post" style="display: inline;">
                <?php wp_nonce_field('reset-template'); ?>
                <input type="submit" name="reset_template" class="button" value="Reset to Professional Default Template" 
                       onclick="return confirm('This will replace your current template with the default HTML template. Continue?');" />
            </form>
        </div>
        
        <form method="post" action="options.php">
            <?php settings_fields('puzzlepath_email_settings'); ?>
            <?php do_settings_sections('puzzlepath-email-settings'); ?>
            <?php submit_button('Save Email Settings'); ?>
        </form>
        
        <div class="card">
            <h2>📋 Email Template Preview</h2>
            <p>All booking confirmation emails are sent using a professional template that includes:</p>
            <ul>
                <li>✅ Customer name and booking details</li>
                <li>🎯 Event information and booking code</li>
                <li>📞 Contact information</li>
                <li>📱 Professional formatting</li>
            </ul>
            <p><em>The sender email and name configured above will be used for all outgoing emails.</em></p>
        </div>
        
        <div class="card">
            <h2>🧪 Test Email Configuration</h2>
            <p>To test your email settings:</p>
            <ol>
                <li>Save your settings above</li>
                <li>Create a test booking using a 100% discount coupon</li>
                <li>Check the From address in the confirmation email</li>
            </ol>
            <div class="notice notice-info">
                <p><strong>Note:</strong> Email delivery depends on your WordPress site's email configuration. Consider using an SMTP plugin for better deliverability.</p>
            </div>
        </div>
        
        <div class="card">
            <h2>📝 Shortcode & Quick Actions</h2>
            <p>Use this shortcode to display the booking form on any page or post:</p>
            <p><code style="background: #f1f1f1; padding: 4px 8px; border-radius: 4px; font-family: monospace;">[puzzlepath_booking_form]</code></p>
            
            <h3>Quick Links</h3>
            <p>
                <a href="<?php echo admin_url('admin.php?page=puzzlepath-quests'); ?>" class="button">Manage Quests</a>
                <a href="<?php echo admin_url('admin.php?page=puzzlepath-coupons'); ?>" class="button">Manage Coupons</a>
                <a href="<?php echo admin_url('admin.php?page=puzzlepath-stripe-settings'); ?>" class="button">Stripe Settings</a>
            </p>
        </div>
        
        <!-- PuzzlePath Shortcode Reference -->
        <div class="card">
            <h2>🎯 PuzzlePath Shortcode Reference</h2>
            <p>Copy and paste these shortcodes to display PuzzlePath content on any page or post:</p>
            
            <h3>📋 Quest Display Shortcodes</h3>
            <div style="margin-bottom: 20px;">
                <h4>Basic Quest Display</h4>
                <code style="background: #f1f1f1; padding: 4px 8px; border-radius: 4px; font-family: monospace;">[puzzlepath_upcoming_adventures]</code>
                <p class="description">Shows all active quests using the default "featured" sorting.</p>
            </div>
            
            <div style="margin-bottom: 20px;">
                <h4>Quest Display with User Sorting</h4>
                <code style="background: #f1f1f1; padding: 4px 8px; border-radius: 4px; font-family: monospace;">[puzzlepath_upcoming_adventures show_sort_dropdown="true"]</code>
                <p class="description">Shows quests with a dropdown menu allowing users to sort by preference.</p>
            </div>
            
            <div style="margin-bottom: 20px;">
                <h4>Limited Quest Display</h4>
                <code style="background: #f1f1f1; padding: 4px 8px; border-radius: 4px; font-family: monospace;">[puzzlepath_upcoming_adventures sort="featured" limit="4"]</code>
                <p class="description">Shows only the top 4 featured quests (great for homepage).</p>
            </div>
            
            <h3>🔤 Available Sort Options</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin: 20px 0;">
                <div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
                    <strong>⭐ Featured:</strong> <code>sort="featured"</code><br>
                    <small>Featured quests first, then hosted events</small>
                </div>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
                    <strong>📈 Popular:</strong> <code>sort="popular"</code><br>
                    <small>Most bookings first (social proof)</small>
                </div>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
                    <strong>💰 Price Low:</strong> <code>sort="price_low"</code><br>
                    <small>Cheapest quests first</small>
                </div>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
                    <strong>💰 Price High:</strong> <code>sort="price_high"</code><br>
                    <small>Most expensive quests first</small>
                </div>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
                    <strong>🔤 A-Z:</strong> <code>sort="alphabetical"</code><br>
                    <small>Alphabetical by quest name</small>
                </div>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
                    <strong>🔤 Z-A:</strong> <code>sort="alphabetical_desc"</code><br>
                    <small>Reverse alphabetical</small>
                </div>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
                    <strong>🆕 Newest:</strong> <code>sort="newest"</code><br>
                    <small>Most recently created first</small>
                </div>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
                    <strong>📅 Oldest:</strong> <code>sort="oldest"</code><br>
                    <small>Oldest quests first</small>
                </div>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
                    <strong>⭐ Difficulty:</strong> <code>sort="difficulty"</code><br>
                    <small>Easy to hard (family-friendly)</small>
                </div>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
                    <strong>📍 Location:</strong> <code>sort="location"</code><br>
                    <small>Grouped by geographic area</small>
                </div>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
                    <strong>🚶‍♂️ Quest Type:</strong> <code>sort="quest_type"</code><br>
                    <small>Walking quests first, then driving</small>
                </div>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
                    <strong>🎲 Random:</strong> <code>sort="random"</code><br>
                    <small>Random order (fresh on repeat visits)</small>
                </div>
            </div>
            
            <h3>📝 Booking & Confirmation Shortcodes</h3>
            <div style="margin-bottom: 20px;">
                <h4>Booking Form</h4>
                <code style="background: #f1f1f1; padding: 4px 8px; border-radius: 4px; font-family: monospace;">[puzzlepath_booking_form]</code>
                <p class="description">Displays the complete booking form with Stripe payment integration.</p>
            </div>
            
            <div style="margin-bottom: 20px;">
                <h4>Booking Confirmation</h4>
                <code style="background: #f1f1f1; padding: 4px 8px; border-radius: 4px; font-family: monospace;">[puzzlepath_booking_confirmation]</code>
                <p class="description">Shows booking confirmation details (use on your confirmation page).</p>
            </div>
            
            <h3>🎯 Example Usage Scenarios</h3>
            <ul style="list-style-type: none; padding: 0;">
                <li style="margin-bottom: 15px; padding: 15px; background: #e8f4fd; border-left: 4px solid #3F51B5; border-radius: 4px;">
                    <strong>🏠 Homepage:</strong><br>
                    <code>[puzzlepath_upcoming_adventures sort="featured" limit="4"]</code><br>
                    <small>Shows your best 4 quests to entice visitors</small>
                </li>
                <li style="margin-bottom: 15px; padding: 15px; background: #f0f9ff; border-left: 4px solid #2196F3; border-radius: 4px;">
                    <strong>👨‍👩‍👧‍👦 Family Page:</strong><br>
                    <code>[puzzlepath_upcoming_adventures sort="difficulty"]</code><br>
                    <small>Easy quests first to build confidence for families</small>
                </li>
                <li style="margin-bottom: 15px; padding: 15px; background: #f3e8ff; border-left: 4px solid #9C27B0; border-radius: 4px;">
                    <strong>💰 Budget Page:</strong><br>
                    <code>[puzzlepath_upcoming_adventures sort="price_low" show_sort_dropdown="true"]</code><br>
                    <small>Cheapest first with user sorting options</small>
                </li>
                <li style="margin-bottom: 15px; padding: 15px; background: #e8f5e8; border-left: 4px solid #4CAF50; border-radius: 4px;">
                    <strong>🎯 Full Quest Page:</strong><br>
                    <code>[puzzlepath_upcoming_adventures show_sort_dropdown="true"]</code><br>
                    <small>Let users explore and sort by their preference</small>
                </li>
            </ul>
            
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin-top: 20px;">
                <h4 style="margin-top: 0;">💡 Pro Tips:</h4>
                <ul>
                    <li>Use <strong>"featured"</strong> sort for maximum control over quest positioning</li>
                    <li>Add <strong>limit="X"</strong> to show only X number of quests</li>
                    <li>Use <strong>show_sort_dropdown="true"</strong> to let users choose their sorting preference</li>
                    <li>Different sorting options are great for different audience types</li>
                    <li>Mark important quests as "Featured" in the quest editor for priority display</li>
                </ul>
            </div>
        </div>
    </div>
    
    <style>
        .card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            margin-top: 20px;
        }
        .card h2 {
            margin-top: 0;
            color: #1d2327;
        }
        .card ul li {
            margin-bottom: 8px;
        }
    </style>
    <?php
}

/**
 * Helper function to get sender email
 */
function puzzlepath_get_sender_email() {
    return get_option('puzzlepath_sender_email', get_bloginfo('admin_email'));
}

/**
 * Helper function to get sender name
 */
function puzzlepath_get_sender_name() {
    return get_option('puzzlepath_sender_name', 'PuzzlePath Team');
}

/**
 * Get default professional HTML email template
 * Used by settings page and email functions
 */
function puzzlepath_get_default_html_template() {
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PuzzlePath Booking Confirmation</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background-color: #f5f7fa; color: #333333;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 0; padding: 0; background-color: #f5f7fa;">
        <tr>
            <td style="padding: 20px 0;">
                <table role="presentation" style="width: 100%; max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header with Logo -->
                    <tr>
                        <td style="padding: 40px 40px 20px; text-align: center; background-color: #3F51B5; background: linear-gradient(135deg, #3F51B5 0%, #5C6BC0 100%); border-radius: 8px 8px 0 0;">
                            <img src="{logo_url}" alt="PuzzlePath Logo" style="width: 150px; height: auto; display: block; margin: 0 auto;" />
                            <h1 style="color: #ffffff !important; font-size: 28px; font-weight: 600; margin: 20px 0 10px; line-height: 1.2; text-shadow: 0 1px 2px rgba(0,0,0,0.1);">Booking Confirmed!</h1>
                            <p style="color: #ffffff !important; font-size: 16px; margin: 0; opacity: 0.95; text-shadow: 0 1px 2px rgba(0,0,0,0.1);">Your adventure awaits</p>
                        </td>
                    </tr>
                    
                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="color: #3F51B5; font-size: 24px; font-weight: 600; margin: 0 0 20px; line-height: 1.3;">Hello {name}! 👋</h2>
                            
                            <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 25px;">Thank you for booking your PuzzlePath adventure! We're excited to have you join us for an unforgettable treasure hunt experience.</p>
                            
                            <!-- Booking Details Card -->
                            <div style="background-color: #f8f9ff; border: 1px solid #e0e3ff; border-radius: 8px; padding: 25px; margin: 25px 0;">
                                <h3 style="color: #3F51B5; font-size: 18px; font-weight: 600; margin: 0 0 15px;">📋 Booking Details</h3>
                                
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="padding: 8px 0; font-weight: 600; color: #666666; width: 40%;">Event:</td>
                                        <td style="padding: 8px 0; color: #333333;">{event_title}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; font-weight: 600; color: #666666;">Date & Time:</td>
                                        <td style="padding: 8px 0; color: #333333;">{event_date}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; font-weight: 600; color: #666666;">Total Paid:</td>
                                        <td style="padding: 8px 0; color: #333333; font-weight: 600;">{price}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; font-weight: 600; color: #666666;">Booking Code:</td>
                                        <td style="padding: 8px 0; color: #3F51B5; font-family: 'Courier New', monospace; font-weight: 700; font-size: 16px;">{booking_code}</td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Call to Action -->
                            <div style="text-align: center; margin: 35px 0;">
                                <p style="font-size: 18px; color: #333333; margin: 0 0 20px; font-weight: 600;">Ready to start your quest?</p>
                                <a href="{app_url}" style="display: inline-block; background-color: #3F51B5; background: linear-gradient(135deg, #3F51B5 0%, #5C6BC0 100%); color: #ffffff !important; text-decoration: none; padding: 16px 32px; border-radius: 50px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 12px rgba(63, 81, 181, 0.3); border: 2px solid #3F51B5; text-shadow: 0 1px 2px rgba(0,0,0,0.1);" target="_blank">
                                    🚀 Open Your Quest
                                </a>
                                <p style="font-size: 14px; color: #888888; margin: 15px 0 0; line-height: 1.4;">Click the button above or visit:<br/><a href="{app_url}" style="color: #3F51B5; text-decoration: none;">{app_url}</a></p>
                            </div>
                            
                            <!-- Important Notes -->
                            <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 15px; margin: 25px 0;">
                                <p style="font-size: 14px; color: #856404; margin: 0; line-height: 1.5;">💡 <strong>Important:</strong> Please save your booking code <strong>{booking_code}</strong> - you'll need it to access your quest on the day of your adventure!</p>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #f8f9fa; border-radius: 0 0 8px 8px; text-align: center; border-top: 1px solid #e9ecef;">
                            <p style="font-size: 14px; color: #6c757d; margin: 0 0 10px; line-height: 1.5;">Questions about your booking? Contact us at <a href="mailto:support@puzzlepath.com.au" style="color: #3F51B5; text-decoration: none;">support@puzzlepath.com.au</a></p>
                            <p style="font-size: 12px; color: #adb5bd; margin: 0; line-height: 1.4;">© 2024 PuzzlePath. All rights reserved.<br/>This email was sent regarding your booking confirmation.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}
